//! Entorno de las pruebas de integración: conexión al MySQL de Docker y datos únicos.
//! No tiene pruebas; lo usan los demás archivos de pruebas.

use std::sync::OnceLock;

use mysql::prelude::Queryable;
use mysql::{Pool, PooledConn};

use crate::db::connect;
use crate::types::db::DbConfig;

/// Conexión de las pruebas de integración, con las variables `EXT_DB_*`.
pub(crate) fn config() -> DbConfig {
    DbConfig::from_env()
        .expect("faltan las variables EXT_DB_* (correr con tests/extension/test-rust.sh)")
}

/// Pool único: un pool por prueba supera el máximo de conexiones de MySQL al correr en paralelo.
pub(crate) fn pool() -> &'static Pool {
    static POOL: OnceLock<Pool> = OnceLock::new();
    POOL.get_or_init(|| connect(&config()).expect("no se pudo conectar a la base de la extensión"))
}

pub(crate) fn open_connection() -> PooledConn {
    pool()
        .get_conn()
        .expect("no se pudo obtener una conexión del pool")
}

/// Conexión con las migraciones aplicadas.
pub(crate) fn migrated_connection() -> PooledConn {
    let mut conn = open_connection();
    crate::db::migrations::run(&mut conn, crate::db::migrations::MIGRATIONS).unwrap();
    conn
}

/// Texto distinto en cada llamada, para no repetir el hash de versiones que ya estén en la base.
pub(crate) fn unique_text() -> String {
    use std::sync::atomic::{AtomicU64, Ordering};
    use std::time::{SystemTime, UNIX_EPOCH};
    static COUNTER: AtomicU64 = AtomicU64::new(0);
    let nanos = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .unwrap()
        .as_nanos();
    format!("prueba-{nanos}-{}", COUNTER.fetch_add(1, Ordering::Relaxed))
}

/// Usuario de una prueba cuyos datos quedan confirmados (las rutas hacen commit). Al terminar la
/// prueba, aunque falle, borra los cambios y las versiones de ese usuario.
pub(crate) struct CommittedUser {
    pub(crate) name: String,
}

impl CommittedUser {
    pub(crate) fn new() -> Self {
        Self {
            name: unique_text(),
        }
    }
}

impl Drop for CommittedUser {
    fn drop(&mut self) {
        let mut conn = open_connection();
        let name = (&self.name,);
        conn.exec_drop("DELETE FROM m2_change_log WHERE user_name = ?", name)
            .expect("no se pudieron borrar los cambios de la prueba");
        let ids: Vec<u32> = conn
            .exec(
                "SELECT version_id FROM m2_versions WHERE created_by_user_name = ?",
                name,
            )
            .expect("no se pudieron leer las versiones de la prueba");
        // Una versión creada después puede tenerla como anterior
        for id in ids {
            conn.exec_drop(
                "UPDATE m2_versions SET parent_version_id = NULL WHERE parent_version_id = ?",
                (id,),
            )
            .expect("no se pudo soltar la versión anterior");
        }
        conn.exec_drop(
            "DELETE FROM m2_versions WHERE created_by_user_name = ?",
            name,
        )
        .expect("no se pudieron borrar las versiones de la prueba");
    }
}
