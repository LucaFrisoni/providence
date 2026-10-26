//! SCO (Seleucid Coins Online): monedas del Imperio seléucida, de la American Numismatic Society.
//! Equivale al plugin nativo `Seleucid.php`.
//!
//! Es una colección Numishare (ver `numishare.rs`): solo cambia la dirección.

use super::numishare::ColeccionNumishare;

pub static SELEUCID: ColeccionNumishare = ColeccionNumishare {
    codigo: "seleucid",
    nombre: "Seleucid Coins Online (ANS)",
    descripcion: "Monedas del Imperio seléucida.",
    base: "http://numismatics.org/sco/", // misma dirección que Seleucid.php
    variable_base: None,
};
