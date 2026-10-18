<?php
/* ----------------------------------------------------------------------
 * app/plugins/tests/integration/ExtensionDbIsolationTest.php :
 * Verifica que Providence trabaja en su base y no puede acceder a la base propia
 * de la extensión (ver README, "Base de datos de la extensión").
 * La prueba inversa (el servicio Rust no accede a la base de Providence) está en
 * rust/extension-service/src/db/access_tests.rs.
 * ----------------------------------------------------------------------
 */
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Extensión · aislamiento entre la base de Providence y la de la extensión')]
final class ExtensionDbIsolationTest extends TestCase {
	# -------------------------------------------------------
	/** Variable de entorno con el nombre de la base de la extensión (definida en compose.yaml) */
	const EXT_DB_ENV_VAR = 'EXT_DB_DATABASE';
	/** Códigos de error de MySQL: acceso denegado a una base y a una tabla */
	const ER_DBACCESS_DENIED = 1044;
	const ER_TABLEACCESS_DENIED = 1142;
	# -------------------------------------------------------
	private Db $db;
	# -------------------------------------------------------
	protected function setUp() : void {
		$this->db = new Db();
	}
	# -------------------------------------------------------
	#[TestDox('Providence lee su propia base (existe al menos el administrador)')]
	public function testProvidenceReadsItsOwnDatabase() : void {
		$result = $this->db->query('SELECT COUNT(*) AS c FROM ca_users');

		$this->assertTrue($result->nextRow());
		$this->assertGreaterThanOrEqual(1, (int)$result->get('c'), 'Debe existir al menos el administrador');
	}
	# -------------------------------------------------------
	#[TestDox('Providence no puede listar las tablas de la base de la extensión (error 1044)')]
	public function testProvidenceCannotListExtensionTables() : void {
		$this->assertMysqlError(self::ER_DBACCESS_DENIED, 'SHOW TABLES FROM `'.$this->extensionDb().'`');
	}
	# -------------------------------------------------------
	#[TestDox('Providence no puede crear tablas en la base de la extensión (error 1142)')]
	public function testProvidenceCannotCreateTablesInExtensionDatabase() : void {
		$sql = 'CREATE TABLE `'.$this->extensionDb().'`.test_aislamiento (id INT)';
		$this->assertMysqlError(self::ER_TABLEACCESS_DENIED, $sql);
	}
	# -------------------------------------------------------
	private function extensionDb() : string {
		$name = getenv(self::EXT_DB_ENV_VAR);
		$this->assertNotEmpty($name, 'Falta la variable de entorno '.self::EXT_DB_ENV_VAR.' (ver compose.yaml)');
		return $name;
	}
	# -------------------------------------------------------
	/**
	 * Ejecuta $sql y verifica que MySQL lo rechace con el código de error esperado
	 */
	private function assertMysqlError(int $expected_code, string $sql) : void {
		try {
			$this->db->query($sql);
		} catch (DatabaseException $e) {
			$this->assertSame($expected_code, (int)$e->getNumber(), $e->getMessage());
			return;
		}
		$this->fail("Se esperaba el error de MySQL {$expected_code} al ejecutar: {$sql}");
	}
	# -------------------------------------------------------
}
