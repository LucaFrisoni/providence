<?php
/* ----------------------------------------------------------------------
 * app/plugins/tests/bootstrap.php :
 * Carga Providence con el setup.php del entorno Docker (la base real de desarrollo)
 * antes de ejecutar las pruebas de la extensión.
 * ----------------------------------------------------------------------
 */
require_once(dirname(__DIR__, 3).'/setup.php');
require_once(__CA_LIB_DIR__.'/Db.php');
