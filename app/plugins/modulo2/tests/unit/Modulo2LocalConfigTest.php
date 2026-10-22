<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/unit/Modulo2LocalConfigTest.php :
 * Pruebas unitarias de la lectura y escritura de app/conf/local, sobre una carpeta temporal: no
 * tocan la configuración real de Providence.
 * ----------------------------------------------------------------------
 */
require_once(__CA_APP_DIR__.'/plugins/modulo2/lib/Modulo2LocalConfig.php');

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Módulo 2 · archivos de app/conf/local (carpeta temporal)')]
final class Modulo2LocalConfigTest extends TestCase {
	# -------------------------------------------------------
	private string $dir;
	private Modulo2LocalConfig $local;
	# -------------------------------------------------------
	protected function setUp() : void {
		$this->dir = sys_get_temp_dir().'/modulo2_local_'.bin2hex(random_bytes(4));
		mkdir($this->dir);
		$this->local = new Modulo2LocalConfig($this->dir, $this->dir.'.lock');
	}
	# -------------------------------------------------------
	protected function tearDown() : void {
		foreach (glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [] as $path) { if (is_file($path)) { unlink($path); } }
		if (is_dir($this->dir.'/sub')) { rmdir($this->dir.'/sub'); }
		rmdir($this->dir);
		@unlink($this->dir.'.lock');
	}
	# -------------------------------------------------------
	// Una versión guarda solo los .conf: .htaccess, temporales y subcarpetas no son configuración
	#[TestDox('Lista solo los archivos .conf, en orden alfabético, sin .htaccess, temporales ni subcarpetas')]
	public function testFilesListsOnlyConfFiles() : void {
		foreach (['b.conf' => 'b = 1', 'app.conf' => 'a = 1', '.htaccess' => 'deny', '.app.conf.x.tmp' => 'x', 'notas.txt' => 'x'] as $name => $content) {
			file_put_contents($this->dir.'/'.$name, $content);
		}
		mkdir($this->dir.'/sub');

		$this->assertSame(['app.conf' => 'a = 1', 'b.conf' => 'b = 1'], $this->local->files());
	}
	# -------------------------------------------------------
	#[TestDox('Escribe un archivo sin dejar temporales, lo reemplaza y lo borra con contenido null')]
	public function testWriteReplacesAndDeletes() : void {
		$this->local->write('app.conf', "a = 1\n");
		$this->local->write('app.conf', "a = 2\n");
		$this->assertSame("a = 2\n", $this->local->read('app.conf'));
		$this->assertSame(['app.conf'], array_values(array_diff(scandir($this->dir), ['.', '..'])), 'Sin archivos temporales');

		$this->local->write('app.conf', null);
		$this->assertNull($this->local->read('app.conf'));
		$this->local->write('app.conf', null);
	}
	# -------------------------------------------------------
	// El módulo solo escribe en app/conf/local: un nombre con ruta podría escribir fuera de la carpeta
	#[TestDox('Rechaza nombres con rutas, sin .conf o vacíos, sin escribir nada')]
	public function testRejectsNamesOutsideTheFolder() : void {
		foreach (['../app.conf', 'sub/app.conf', 'app.txt', '.conf', '', "app.conf\n", '.htaccess'] as $name) {
			try {
				$this->local->write($name, 'x');
				$this->fail('Se esperaba Modulo2ConfigException: '.var_export($name, true));
			} catch (Modulo2ConfigException $e) {
				$this->assertNotEmpty($e->getMessage());
			}
		}
		$this->assertFileDoesNotExist(dirname($this->dir).'/app.conf');
		$this->assertSame([], $this->local->files());
	}
	# -------------------------------------------------------
	#[TestDox('replaceAll deja exactamente los .conf dados y no toca los demás archivos')]
	public function testReplaceAllLeavesExactlyTheGivenFiles() : void {
		file_put_contents($this->dir.'/viejo.conf', 'v = 1');
		file_put_contents($this->dir.'/app.conf', 'a = 1');
		file_put_contents($this->dir.'/.htaccess', 'deny');

		$this->local->replaceAll(['app.conf' => 'a = 2', 'nuevo.conf' => 'n = 1']);

		$this->assertSame(['app.conf' => 'a = 2', 'nuevo.conf' => 'n = 1'], $this->local->files());
		$this->assertFileExists($this->dir.'/.htaccess');
	}
	# -------------------------------------------------------
	#[TestDox('Lee las claves con el lector de Providence: textos, listas y bloques, sin combinar con app/conf')]
	public function testEntriesAreReadByProvidence() : void {
		file_put_contents($this->dir.'/app.conf', "# comentario\nca_places_disable = 1\nlista = [a, \"b c\"]\nbloque = {\n\tx = 1,\n\ty = { z = 2 }\n}\n");

		$this->assertSame(['ca_places_disable' => '1', 'lista' => ['a', 'b c'], 'bloque' => ['x' => '1', 'y' => ['z' => '2']]], $this->local->entries('app.conf'));
		$this->assertSame([], $this->local->entries('inexistente.conf'));
	}
	# -------------------------------------------------------
	#[TestDox('Un archivo que Providence no puede leer lanza Modulo2ConfigException')]
	public function testUnreadableFileThrows() : void {
		file_put_contents($this->dir.'/app.conf', "bloque = {\n\tx = 1\n");

		$this->expectException(Modulo2ConfigException::class);
		$this->local->entries('app.conf');
	}
	# -------------------------------------------------------
	// Si una operación falla con la carpeta bloqueada, el bloqueo no puede quedar tomado
	#[TestDox('withLock devuelve el resultado de la operación y libera el bloqueo aunque falle')]
	public function testLockIsReleasedEvenOnFailure() : void {
		$this->assertSame(42, $this->local->withLock(fn() => 42));
		try {
			$this->local->withLock(function() { throw new RuntimeException('falla'); });
		} catch (RuntimeException $e) {
			$this->assertSame('falla', $e->getMessage());
		}
		$handle = fopen($this->dir.'.lock', 'c');
		$this->assertTrue(flock($handle, LOCK_EX | LOCK_NB), 'El bloqueo quedó libre');
		flock($handle, LOCK_UN);
		fclose($handle);
	}
	# -------------------------------------------------------
}
