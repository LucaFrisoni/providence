//! Configuración de la conexión a la base propia de la extensión (`ca_extension`).
//!
//! El servicio es el único dueño de esta base. El usuario de MySQL que usa solo tiene
//! permisos sobre ella, no sobre la base de Providence (ver README, "Base de datos de la extensión").

use mysql::Pool;

use crate::types::db::DbConfig;

/// Crea el pool de conexiones. Falla si la base no responde o las credenciales no son válidas.
pub fn connect(config: &DbConfig) -> Result<Pool, mysql::Error> {
    Pool::new(config.opts())
}
