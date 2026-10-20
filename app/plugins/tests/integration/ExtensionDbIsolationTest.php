<?php
/* ----------------------------------------------------------------------
 * app/plugins/tests/integration/ExtensionDbIsolationTest.php :
 * Verifica que Providence trabaja en su base y no puede acceder a la base propia
 * de la extensión (ver README, "Base de datos de la extensión").
 * La prueba inversa (el servicio Rust no accede a la base de Providence) está en
 * rust/extension-service/src/db/tests/access.rs.
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
	/** Tabla compartida de la base de la extensión (MIGRATIONS_TABLE en constants.rs) */
	const EXT_SHARED_TABLE = 'ext_migrations';
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
	#[TestDox('Providence no puede leer ni escribir las tablas de la extensión (error 1142)')]
	public function testProvidenceCannotReadOrWriteExtensionTables() : void {
		// Las escrituras no afectan ninguna fila aunque el permiso existiera por error
		$table = '`'.$this->extensionDb().'`.'.self::EXT_SHARED_TABLE;
		foreach ([
			"SELECT 1 FROM {$table} LIMIT 1",
			"INSERT INTO {$table} (module) SELECT 'prueba' FROM DUAL WHERE 1 = 0",
			"UPDATE {$table} SET name = 'prueba' WHERE 1 = 0",
			"DELETE FROM {$table} WHERE 1 = 0"
		] as $sql) {
			$this->assertMysqlError(self::ER_TABLEACCESS_DENIED, $sql);
		}
	}
	# -------------------------------------------------------
	#[TestDox('Providence no puede crear, modificar ni borrar tablas en la base de la extensión (error 1142)')]
	public function testProvidenceCannotChangeExtensionSchema() : void {
		// Apuntan a tablas que no existen: MySQL revisa el permiso antes que la existencia de la tabla
		$db = '`'.$this->extensionDb().'`';
		foreach ([
			"CREATE TABLE {$db}.test_aislamiento (id INT)",
			"ALTER TABLE {$db}.test_aislamiento_inexistente ADD COLUMN id INT",
			"DROP TABLE {$db}.test_aislamiento_inexistente"
		] as $sql) {
			$this->assertMysqlError(self::ER_TABLEACCESS_DENIED, $sql);
		}
	}
	# -------------------------------------------------------
	#[TestDox('Providence solo tiene permisos sobre su propia base')]
	public function testProvidenceOnlyHasPrivilegesOnItsOwnDatabase() : void {
		$own_database = 'ON `'.__CA_DB_DATABASE__.'`.*';
		$grants = [];
		$result = $this->db->query('SHOW GRANTS');
		while ($result->nextRow()) { $grants[] = (string)array_values($result->getRow())[0]; }

		foreach ($grants as $grant) {
			$allowed = str_starts_with($grant, 'GRANT USAGE ON *.*') || str_contains($grant, $own_database);
			$this->assertTrue($allowed, "Permiso inesperado: {$grant}");
		}
		$this->assertNotEmpty(array_filter($grants, fn($grant) => str_contains($grant, $own_database)), implode("
", $grants));
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
