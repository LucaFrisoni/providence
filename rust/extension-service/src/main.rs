//! Servicio HTTP único de la extensión de Providence.
//!
//! Cada módulo de la extensión vive en `modules/<modulo>.rs` y expone sus rutas
//! bajo `/api/<modulo>`. Los plugins de Providence se conectan a este
//! único servicio.

mod constants;
mod modules;
mod routes;

use std::env;
use std::net::SocketAddr;

use constants::{ADDR_ENV_VAR, DEFAULT_ADDR, SERVICE_NAME};

#[tokio::main]
async fn main() {
    let addr = listen_addr();
    let listener = tokio::net::TcpListener::bind(addr)
        .await
        .unwrap_or_else(|e| panic!("No se pudo escuchar en {addr}: {e}"));
    println!("{SERVICE_NAME} escuchando en http://{addr}");
    axum::serve(listener, routes::router())
        .await
        .expect("El servidor terminó con un error");
}

/// Lee la dirección de la variable de entorno o usa la dirección por defecto.
fn listen_addr() -> SocketAddr {
    let raw = env::var(ADDR_ENV_VAR).unwrap_or_else(|_| DEFAULT_ADDR.to_string());
    raw.parse()
        .unwrap_or_else(|e| panic!("{ADDR_ENV_VAR} inválida ({raw}): {e}"))
}
