<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/locale/providence/build.php :
 * Arma themes/default/conf/translations.conf con las traducciones propias de textos de Providence
 * (locale/providence/<idioma>.php). Providence usa ese archivo antes que su traducción, así se completan los
 * textos que su traducción deja en inglés y se cambian los que se decidió cambiar (locale/providence/forced.php).
 *
 * Uso, después de cambiar un archivo de locale/providence (el tema no está montado en el contenedor: el archivo
 * se escribe en la copia local y se publica al reconstruir la imagen):
 *   docker compose exec -T -u www-data providence php app/plugins/modulo2/locale/providence/build.php > themes/default/conf/translations.conf
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/../../../../../setup.php');
require_once(__DIR__.'/../../lib/Modulo2TranslationOverrides.php');

$overrides = new Modulo2TranslationOverrides(__DIR__, array_keys(Modulo2TranslationOverrides::providenceLocales()));
$strings = $overrides->strings(fn(string $locale, string $text) => Modulo2TranslationOverrides::providenceTranslates($locale, $text));
print Modulo2TranslationOverrides::conf($strings);
fwrite(STDERR, sizeof($strings).' textos'.PHP_EOL);
