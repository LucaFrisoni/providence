//! Servicio HTTP único de la extensión de Providence.
//!
//! Cada módulo de la extensión vive en `modules/<modulo>.rs` y expone sus rutas
//! bajo `/api/<modulo>`. Los plugins de Providence se conectan a este
//! único servicio.
//el servidor es sync, cada peticion se atiende en un hilo.

mod constants;
mod db;
mod models;
mod modules;
mod routes;
mod respuesta;

use std::env;
use std::io;
use std::net::SocketAddr;
use std::thread;

use constants::{ADDR_ENV_VAR, DEFAULT_ADDR, SERVICE_NAME, CABECERA_CONTENT_TYPE, TIPO_JSON};
use tiny_http::{Request, Server, Header, Response};
use respuesta::Respuesta;
use mysql::Pool;

fn main() {
    // Se conecta al iniciar para fallar temprano si la base no responde y para crear o actualizar sus tablas. El pool se guarda para los módulos que usan la base (hoy, el módulo 1)
    let pool = connect_database();
    run_migrations(&pool);
    db::guardar_pool(pool);
    let addr = listen_addr();
    let servidor =
        Server::http(addr).unwrap_or_else(|e| panic!("No se pudo escuchar en {addr}: {e}"));
    println!("{SERVICE_NAME} escuchando en http://{addr}");
    for peticion in servidor.incoming_requests() {
        thread::spawn(move || atender(peticion));
    }
}

/// Conecta a la base propia de la extensión con la configuración de las variables `EXT_DB_*`.
fn connect_database() -> Pool {
    let config = db::DbConfig::from_env().unwrap_or_else(|e| panic!("{e}"));
    let pool = db::connect(&config)
        .unwrap_or_else(|e| panic!("No se pudo conectar a la base {}: {e}", config.database));
    println!("{SERVICE_NAME} conectado a la base {} en {}:{}", config.database, config.host, config.port);
    pool
}

/// Aplica las migraciones pendientes de la base de la extensión (ver `db/migrations.rs`).
fn run_migrations(pool: &Pool) {
    let mut conn = pool
        .get_conn()
        .unwrap_or_else(|e| panic!("No se pudo obtener una conexión para las migraciones: {e}"));
    let applied = db::migrations::run(&mut conn, db::migrations::MIGRATIONS).unwrap_or_else(|e| panic!("{e}"));
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

fn leer_cuerpo(peticion: &mut Request) -> io::Result<String>{
    let mut cuerpo = String::new();
    peticion.as_reader().read_to_string(&mut cuerpo)?;
    Ok(cuerpo)
}

//resuelve la peticion y envia la respuesta json
fn atender(mut peticion: Request) {
    let respuesta = match leer_cuerpo(&mut peticion){
        Ok(cuerpo) => routes::responder(peticion.method(), peticion.url(), &cuerpo),
        Err(_) => Respuesta::peticion_incorrecta("el cuerpo no es un texto valido")
    };
        enviar(peticion, respuesta);
}

//convierte la respuesta del modulo en una respuesta http y la envia
fn enviar(peticion: Request, respuesta: Respuesta) {
    let cabecera = Header::from_bytes(CABECERA_CONTENT_TYPE, TIPO_JSON)
        .expect("La cabecera content-type es valida");
    let respuesta_http = Response::from_string(respuesta.cuerpo)
        .with_status_code(respuesta.estado)
        .with_header(cabecera);
    if let Err(e) = peticion.respond(respuesta_http) {
        eprintln!("Error al responder: {e}");
    }
}