<?php
/* ----------------------------------------------------------------------
 * app/plugins/modulo2/themes/default/views/preferences_info_html.php :
 * Barra lateral de My preferences, con el estilo del menú lateral de Providence: los grupos (los que
 * tienen editores propios abren la pantalla de Providence), las versiones y el historial.
 * ----------------------------------------------------------------------
 */
$nav = $this->getVar('nav') ?? [];
$e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
?>
<style>
	#leftNav .m2-sidebar { padding-bottom: 10px; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
	#leftNav .m2-sidebar ul.arrow li a { margin: 1px 8px; padding: 7px 10px 6px 22px; border-radius: 6px; font-family: inherit; text-transform: none; transition: background .15s, color .15s; }
	#leftNav .m2-sidebar ul.arrow li a:hover { background: #f1f6f7; }
	#leftNav .m2-sidebar ul.arrow li.m2-current a { background: #e8f5f6; color: #0e6b75; font-weight: 600; box-shadow: inset 3px 0 0 #138290; }
	#leftNav .m2-sidebar .m2-native { display: inline; margin-left: 4px; padding: 0; color: #888; font-size: 10px; }
	#leftNav .m2-sidebar h2 { margin: 16px 14px 4px 14px; width: auto; font-family: inherit; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; color: #138290; }
	#leftNav .m2-sidebar h2:first-child { margin-top: 6px; }
</style>
<?= Modulo2Ui::themeStyles(); ?>
<div class="m2-sidebar">
	<h2><?= Modulo2I18n::t('My preferences'); ?></h2>
	<ul class="arrow" style="display:block">
<?php foreach ($nav as $item) { ?>
		<li<?= $item['current'] ? ' class="m2-current"' : ''; ?>><a href="<?= $e($item['url']); ?>"><?= $e($item['name']); ?><?= $item['native'] ? '<span class="m2-native" title="'.$e(Modulo2I18n::t('Opens its own editor')).'">↗</span>' : ''; ?></a></li>
<?php } ?>
	</ul>
	<h2><?= Modulo2I18n::t('Versions'); ?></h2>
	<ul class="arrow" style="display:block">
		<li><a href="<?= $e($this->getVar('versions_url')); ?>"><?= Modulo2I18n::t('My preference versions'); ?></a></li>
	</ul>
</div>
