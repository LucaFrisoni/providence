//! Servicio HTTP único de la extensión de Providence.
//!
//! Cada módulo de la extensión vive en `modules/<modulo>.rs` y expone sus rutas
//! bajo `/api/<modulo>`. Los plugins de Providence se conectan a este
//! único servicio.
//el servidor es sync, cada peticion se atiende en un hilo.

mod constants;
mod modules;
mod routes;
mod respuesta; 

use std::env;
use std::net::SocketAddr;
use std::thread;

use constants::{ADDR_ENV_VAR, DEFAULT_ADDR, SERVICE_NAME, CABECERA_CONTENT_TYPE, TIPO_JSON};

use tiny_http::{Header, Request, Response, Server};

fn main() {
    let addr = listen_addr();
    let servidor = Server::http(addr)
        .unwrap_or_else(|e| panic!("No se pudo escuchar en {addr}: {e}"));
    println!("{SERVICE_NAME} escuchando en http://{addr}");
    for peticion in servidor.incoming_requests() {
        thread::spawn(move || atender(peticion));
    }
}

/// Lee la dirección de la variable de entorno o usa la dirección por defecto.
fn listen_addr() -> SocketAddr {
    let raw = env::var(ADDR_ENV_VAR).unwrap_or_else(|_| DEFAULT_ADDR.to_string());
    raw.parse()
        .unwrap_or_else(|e| panic!("{ADDR_ENV_VAR} inválida ({raw}): {e}"))
}

//resuelve la peticion y envia la respuesta json
fn atender(peticion: Request){
    let respuesta = routes::responder(peticion.method(), peticion.url());
    let cabecera = Header::from_bytes(CABECERA_CONTENT_TYPE, TIPO_JSON)
        .expect("La cabecera content-type es valida");
    let respuesta_http = Response::from_string(respuesta.cuerpo)
        .with_status_code(respuesta.estado)
        .with_header(cabecera);

    if let Err(e) = peticion.respond(respuesta_http) {
        eprintln!("Error al responder: {e}");
    }
}
