//! Consulta varias fuentes a la vez, junta los resultados, los puntúa y los ordena.

use std::collections::HashSet;
use std::thread;

use crate::types::modulo6::fuente::{Candidato, Consulta, Fuente};

pub struct Resultado {
    pub candidatos: Vec<Candidato>,
    pub errores: Vec<(String, String)>, // (fuente, motivo)
}

pub fn buscar(fuentes: &[&dyn Fuente], consulta: &Consulta) -> Resultado {
    let mut resultado = Resultado { candidatos: Vec::new(), errores: Vec::new() };

    // 1. Un hilo por fuente: todas consultan al mismo tiempo.
    thread::scope(|s| {
        let hilos: Vec<_> = fuentes
            .iter()
            .map(|f| (f.codigo(), s.spawn(move || f.buscar(consulta))))
            .collect();

        // 2. Juntar: lo que anduvo va a candidatos y lo que falló va a errores.
        for (codigo, hilo) in hilos {
            match hilo.join() {
                Ok(Ok(mut lista)) => resultado.candidatos.append(&mut lista),
                Ok(Err(motivo)) => resultado.errores.push((codigo.into(), motivo)),
                Err(_) => resultado.errores.push((codigo.into(), "la fuente se cayó".into())),
            }
        }
    });

    // 3. Puntuar todos con la misma regla.
    for c in &mut resultado.candidatos {
        c.puntaje = puntaje(consulta.texto, &c.etiqueta);
    }
    // 4. Ordenar de mejor a peor, sacar repetidos y respetar el límite.
    resultado.candidatos.sort_by(|a, b| b.puntaje.total_cmp(&a.puntaje));
    let mut vistas = HashSet::new();
    resultado.candidatos.retain(|c| vistas.insert(c.uri.clone()));
    resultado.candidatos.truncate(consulta.limite);
    resultado
}

/// Versión simple a propósito: después se puede cambiar por Jaro-Winkler sin tocar nada más.
fn puntaje(consulta: &str, etiqueta: &str) -> f64 {
    let (q, e) = (normalizar(consulta), normalizar(etiqueta));
    if q == e { 1.0 } else if e.starts_with(&q) { 0.8 } else if e.contains(&q) { 0.6 } else { 0.3 }
}

fn normalizar(texto: &str) -> String {
    texto.to_lowercase().split_whitespace().collect::<Vec<_>>().join(" ")
}