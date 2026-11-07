//! Servicio HTTP único de la extensión de Providence: conecta la base, aplica las migraciones y
//! atiende cada petición en un hilo (las rutas están en `routes/`).

mod constants;
mod db;
mod json;
mod models;
mod routes;
mod services;
mod types;

use std::env;
use std::net::SocketAddr;
use std::thread;

use constants::{ADDR_ENV_VAR, DEFAULT_ADDR, SERVICE_NAME};

use mysql::Pool;
use tiny_http::Server;

fn main() {
    //El pool se comparte entre los hilos de las peticiones
    let pool = connect_database();
    run_migrations(&pool);
    let addr = listen_addr();
    let server =
        Server::http(addr).unwrap_or_else(|e| panic!("No se pudo escuchar en {addr}: {e}"));
    println!("{SERVICE_NAME} escuchando en http://{addr}");
    for request in server.incoming_requests() {
        let pool = pool.clone();
        thread::spawn(move || routes::handle(request, &pool));
    }
}

/// Conecta a la base propia de la extensión con la configuración de las variables `EXT_DB_*`.
fn connect_database() -> Pool {
    let config = types::db::DbConfig::from_env().unwrap_or_else(|e| panic!("{e}"));
    let pool = db::connect(&config)
        .unwrap_or_else(|e| panic!("No se pudo conectar a la base {}: {e}", config.database));
    println!(
        "{SERVICE_NAME} conectado a la base {} en {}:{}",
        config.database, config.host, config.port
    );
    pool
}

/// Aplica las migraciones pendientes de la base de la extensión (ver `db/migrations.rs`).
fn run_migrations(pool: &Pool) {
    let mut conn = pool
        .get_conn()
        .unwrap_or_else(|e| panic!("No se pudo obtener una conexión para las migraciones: {e}"));
    let applied = db::migrations::run(&mut conn, db::migrations::MIGRATIONS)
        .unwrap_or_else(|e| panic!("{e}"));
    for migration in applied {
        println!("{SERVICE_NAME} aplicó la migración {migration}");
    }
}

/// Lee la dirección de la variable de entorno o usa la dirección por defecto.
fn listen_addr() -> SocketAddr {
    let raw = env::var(ADDR_ENV_VAR).unwrap_or_else(|_| DEFAULT_ADDR.to_string());
    raw.parse()
        .unwrap_or_else(|e| panic!("{ADDR_ENV_VAR} inválida ({raw}): {e}"))
}
