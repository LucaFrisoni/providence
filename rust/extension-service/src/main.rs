//! Servicio HTTP único de la extensión de Providence.
//!
//! Cada módulo de la extensión vive en `modules/<modulo>.rs` y expone sus rutas
//! bajo `/api/<modulo>`. Los plugins de Providence se conectan a este
//! único servicio.
//el servidor es sync, cada peticion se atiende en un hilo.

mod constants;
mod modules;
mod routes;

use std::env;
use std::io::{self, Read};
use std::net::SocketAddr;
use std::thread;

use constants::{ADDR_ENV_VAR, DEFAULT_ADDR, SERVICE_NAME,};
use modules::peticion_incorrecta;
use tiny_http::{Request, Server};

fn main() {
    let addr = listen_addr();
    let servidor =
        Server::http(addr).unwrap_or_else(|e| panic!("No se pudo escuchar en {addr}: {e}"));
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

fn leer_cuerpo(peticion: &mut Request) -> io::Result<String>{
    let mut cuerpo = String::new();
    peticion.as_reader().read_to_string(&mut cuerpo)?;
    Ok(cuerpo)
}

//resuelve la peticion y envia la respuesta json
fn atender(mut peticion: Request) {
    let respuesta = match leer_cuerpo(&mut peticion){
        Ok(cuerpo) => routes::responder(peticion.method(), peticion.url(), &cuerpo),
        Err(_) => peticion_incorrecta("el cuerpo no es un texto valido")
    };
    if let Err(e) = peticion.respond(respuesta) {
        eprintln!("Error al responder: {e}");
    }
}
