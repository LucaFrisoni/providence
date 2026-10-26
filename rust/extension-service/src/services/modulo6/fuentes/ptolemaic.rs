//! PCO (Ptolemaic Coins Online): monedas del Egipto ptolemaico, de la American Numismatic Society.
//! Equivale al plugin nativo `Ptolemaic.php`.
//!
//! Es una colección Numishare (ver `numishare.rs`): solo cambia la dirección.

use super::numishare::ColeccionNumishare;

pub static PTOLEMAIC: ColeccionNumishare = ColeccionNumishare {
    codigo: "ptolemaic",
    nombre: "Ptolemaic Coins Online (ANS)",
    descripcion: "Monedas del Egipto ptolemaico.",
    base: "http://numismatics.org/pco/", // misma dirección que Ptolemaic.php
    variable_base: None,
};
