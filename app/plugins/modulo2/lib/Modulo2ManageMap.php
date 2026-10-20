<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/lib/Modulo2ManageMap.php :
 * Organización del panel de gestión: las secciones de Manage de Providence (menú "manage" de
 * navigation.conf) agrupadas por tema, con su descripción, su estado en el módulo y, si
 * corresponde, el módulo del proyecto que las completa (ver docs/modulo2/README.md, "Panel de gestión").
 *
 * Los nombres, enlaces y permisos de cada sección se leen de navigation.conf: si Providence cambia
 * su menú, el panel lo sigue. Acá solo está lo que Providence no sabe.
 *
 * Cada sección es una clave de manage/navigation en navigation.conf ("keys": la primera que el
 * usuario pueda ver, para las que Providence define en variantes) o una pantalla propia del módulo
 * ("internal": [controlador, acción] del plugin). "owner": módulo del proyecto que la completa, si
 * no es el 2.
 * ----------------------------------------------------------------------
 */
final class Modulo2ManageMap {
	# -------------------------------------------------------
	/** Estado de una sección en el módulo */
	const STATUS_NATIVE = 'native';      // Se usa la pantalla de Providence (enlace)
	const STATUS_PLANNED = 'planned';    // Hay una mejora prevista; mientras tanto, la pantalla de Providence
	const STATUS_IMPROVED = 'improved';  // Pantalla propia del módulo
	# -------------------------------------------------------
	/** Grupos del panel, en orden: [id => ['title' => ..., 'sections' => [id => sección]]] */
	public static function groups() : array {
		return [
			'configuration' => ['title' => _t('Configuración y administración'), 'sections' => self::configurationSections()],
			'work' => ['title' => _t('Mi trabajo'), 'sections' => self::workSections()],
			'data' => ['title' => _t('Datos y vocabularios'), 'sections' => self::dataSections()],
			'access' => ['title' => _t('Usuarios y acceso'), 'sections' => self::accessSections()],
			'community' => ['title' => _t('Contenido y comunidad'), 'sections' => self::communitySections()],
			'monitoring' => ['title' => _t('Seguimiento'), 'sections' => self::monitoringSections()]
		];
	}
	# -------------------------------------------------------
	/** Nombre de cada módulo del proyecto, para la nota de las secciones que completa otro módulo */
	public static function moduleNames() : array {
		return [
			1 => _t('Módulo 1 (modelo de información)'),
			3 => _t('Módulo 3 (administración por institución o área)'),
			5 => _t('Módulo 5 (mappings e interoperabilidad)')
		];
	}
	# -------------------------------------------------------
	private static function configurationSections() : array {
		return [
			'configurations' => self::internal(['Configurations', 'Index'], _t('Nuevas configuraciones'),
				_t('Configuraciones que Providence solo permite cambiar editando sus archivos y que este módulo trae a la interfaz: tipos de dato, menús, identificadores, búsqueda, textos y más.')),
			'system_config' => self::native(['system_config'], _t('Interfaces de edición, elementos de metadatos, tipos de relación, idiomas, diccionario de datos, chequeo de configuración y mantenimiento (reindexar, exportar la configuración).'), self::STATUS_PLANNED),
			'preferences' => self::native(['preferences'], _t('Cómo trabaja cada usuario en Providence: idioma y tema de la interfaz, pantallas de edición por tipo de dato, búsqueda, alta rápida, qué se copia al duplicar un registro y unidades de medida.'), self::STATUS_PLANNED)
		];
	}
	# -------------------------------------------------------
	private static function workSections() : array {
		return [
			'sets' => self::native(['sets', 'sets_submenu'], _t('Conjuntos de registros para trabajar o publicar juntos.')),
			'user_displays' => self::native(['user_displays'], _t('Formatos de visualización: qué campos se muestran en resultados e informes.')),
			'search_tools' => self::native(['search_tools'], _t('Formularios de búsqueda, búsquedas guardadas y ordenamientos propios.')),
			'watched_items' => self::native(['watched_items'], _t('Registros que el usuario sigue para enterarse de sus cambios.')),
			'metadata_alerts' => self::native(['metadata_alerts'], _t('Reglas que avisan cuando un registro cumple una condición.')),
			'export_download' => self::native(['export_download'], _t('Descargas generadas por exportaciones e informes.')),
			'plugin_tools' => self::native(['plugin_tools'], _t('Herramientas que agregan los plugins instalados.'))
		];
	}
	# -------------------------------------------------------
	private static function dataSections() : array {
		return [
			'list_editor_menu_item' => self::native(['list_editor_menu_item'], _t('Listas y vocabularios, incluidos los subtipos de cada tipo de dato y el orden de sus ítems.')) + ['owner' => 1],
			'export' => self::native(['export'], _t('Exportadores de metadatos a otros formatos.')) + ['owner' => 5]
		];
	}
	# -------------------------------------------------------
	private static function accessSections() : array {
		return [
			'access_control' => self::native(['access_control'], _t('Usuarios, grupos y roles con sus permisos.')) + ['owner' => 3],
			'user_groups' => self::native(['user_groups'], _t('Equipos de trabajo del usuario.')) + ['owner' => 3]
		];
	}
	# -------------------------------------------------------
	private static function communitySections() : array {
		return [
			'user_generated_content' => self::native(['user_generated_content'], _t('Moderación de comentarios y etiquetas que dejan los usuarios.')),
			'pawtucket' => self::native(['pawtucket'], _t('Páginas y textos del sitio público (Pawtucket), si la institución lo usa.'))
		];
	}
	# -------------------------------------------------------
	private static function monitoringSections() : array {
		return [
			'logs' => self::native(['logs'], _t('Registros de cambios, eventos, búsquedas y descargas.')),
			'statistics' => self::native(['statistics'], _t('Estadísticas de uso del sistema.'))
		];
	}
	# -------------------------------------------------------
	/** Sección de Manage de Providence: nombre, enlaces y permisos salen de navigation.conf */
	private static function native(array $keys, string $description, string $status = self::STATUS_NATIVE) : array {
		return ['keys' => $keys, 'description' => $description, 'status' => $status];
	}
	# -------------------------------------------------------
	/** Pantalla propia del módulo */
	private static function internal(array $route, string $label, string $description) : array {
		return ['internal' => $route, 'label' => $label, 'description' => $description, 'status' => self::STATUS_IMPROVED];
	}
	# -------------------------------------------------------
}
