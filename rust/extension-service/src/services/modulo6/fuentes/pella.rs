//! PELLA: catálogo de las monedas de Alejandro Magno y sus sucesores en Macedonia, de la American
//! Numismatic Society. Equivale al plugin nativo `Pella.php`.
//!
//! No tiene código propio: es una colección Numishare (ver `numishare.rs`) con su dirección.
//! Este archivo muestra lo fácil que es agregar otra colección con la misma API.

use super::numishare::ColeccionNumishare;

pub static PELLA: ColeccionNumishare = ColeccionNumishare {
    codigo: "pella",
    nombre: "PELLA (ANS)",
    descripcion: "Monedas de Alejandro Magno y los reyes macedonios.",
    base: "http://numismatics.org/pella/", // misma dirección que Pella.php
    variable_base: None,                   // la dirección es fija
};
