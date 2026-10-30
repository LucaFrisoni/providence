<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/tests/integration/Modulo2ReportsIntegrationTest.php :
 * Qué hace Providence con las configuraciones de descargas (#9): genera el Excel y el Word de unos
 * resultados con el exportador real y mira su contenido.
 * ----------------------------------------------------------------------
 */
if (!defined('__CA_DONT_QUEUE_SEARCH_INDEXING__')) { define('__CA_DONT_QUEUE_SEARCH_INDEXING__', true); }
require_once(__CA_APP_DIR__.'/plugins/modulo2/tests/support/Modulo2BehaviorTestCase.php');
require_once(__CA_APP_DIR__.'/helpers/exportHelpers.php');
require_once(__CA_LIB_DIR__.'/Search/ObjectSearch.php');

use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Módulo 2 · descargas en Providence')]
final class Modulo2ReportsIntegrationTest extends Modulo2BehaviorTestCase {
	# -------------------------------------------------------
	/** Búsqueda que se muestra en el encabezado */
	const SEARCH = 'M2 informe';
	/** Archivos generados por la prueba, para borrarlos al final */
	private array $files = [];
	# -------------------------------------------------------
	protected function tearDown() : void {
		array_map('unlink', array_filter($this->files, 'is_file'));
		parent::tearDown();
	}
	# -------------------------------------------------------
	// Se prueba con objetos y entidades: la descarga es la misma para todos los tipos de dato. La búsqueda
	// necesita también el encabezado (por eso el catálogo las une en una sola configuración)
	#[TestDox('#9 El Excel lleva la búsqueda arriba y el pie abajo solo con sus opciones, en objetos y entidades')]
	public function testExcelSearchAndFooter() : void {
		$search = ['excel_report_show_search_term' => 1, 'excel_report_header_enabled' => 1];
		$cases = [
			'sin opciones' => [[], false, false],
			'búsqueda sin encabezado' => [['excel_report_show_search_term' => 1], false, false],
			'búsqueda' => [$search, true, false],
			'pie' => [['excel_report_footer_enabled' => 1], false, true]
		];
		foreach (['ca_objects', 'ca_entities'] as $table) {
			foreach ($cases as $case => [$settings, $with_search, $with_footer]) {
				$this->configure($settings);
				$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->export($table, '_xlsx_0'))->getActiveSheet()->getHeaderFooter();

				$this->assertSame($with_search, str_contains($sheet->getOddHeader(), self::SEARCH), "{$table} {$case}: búsqueda");
				$this->assertSame($with_footer, str_contains($sheet->getOddFooter(), '&P'), "{$table} {$case}: pie con el número de página");
			}
		}
	}
	# -------------------------------------------------------
	#[TestDox('#9 El Word lleva el nombre de cada campo junto a su valor solo con "Nombres de los campos en las descargas Word"')]
	public function testWordLabels() : void {
		// Con etiquetas, cada valor va precedido por "<campo>: " (por ejemplo, "Object identifier: ")
		$labelled = fn() => preg_match('/identifier: /i', $this->wordText()) === 1;

		$this->configure(['report_include_labels_in_docx_output' => 0]);
		$this->assertFalse($labelled());

		$this->configure(['report_include_labels_in_docx_output' => 1]);
		$this->assertTrue($labelled());
	}
	# -------------------------------------------------------
	/** Exporta como la descarga de resultados de Providence y devuelve el archivo */
	private function export(string $table, string $template) : string {
		$record = $this->newRecord($table, self::uniqueIdno());
		$this->assertTrue((bool)$record->insert(), join('; ', $record->getErrors()));
		$this->track($record);
		$result = $record->makeSearchResult($table, [$record->getPrimaryKey()]);
		$export = caExportResult($this->request(), $result, $template, 'm2test', ['output' => 'FILE', 'criteriaSummary' => self::SEARCH]);
		$this->assertIsArray($export, "No se generó {$template}");
		return $this->files[] = $export['path'];
	}
	# -------------------------------------------------------
	/** Texto del Word de unos resultados */
	private function wordText() : string {
		$zip = new ZipArchive();
		$this->assertTrue($zip->open($this->export('ca_objects', '_docx_0')) === true);
		$xml = (string)$zip->getFromName('word/document.xml');
		$zip->close();
		return strip_tags($xml);
	}
	# -------------------------------------------------------
}
