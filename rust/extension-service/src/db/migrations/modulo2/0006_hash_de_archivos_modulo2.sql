-- Módulo 2: hash de solo los archivos .conf de cada versión.
-- Una restauración vuelve a los archivos de una versión, pero no a la configuración en base: con este
-- hash se reconoce que la configuración actual tiene los archivos de esa versión aunque la base cambió.
-- Mismo formato que State::files_hash (types/modulo2/versions.rs): por archivo, en orden de bytes,
-- "nombre\nlargo\n" + contenido + "\n".
ALTER TABLE m2_versions ADD COLUMN files_hash VARCHAR(64) NULL AFTER content_hash;
SET SESSION group_concat_max_len = 1073741824;
UPDATE m2_versions v SET files_hash = SHA2(COALESCE((
    SELECT GROUP_CONCAT(CONCAT(f.file_name, '\n', LENGTH(f.content), '\n', f.content, '\n')
        ORDER BY f.file_name COLLATE utf8mb4_bin SEPARATOR '')
    FROM m2_version_files f
    WHERE f.version_id = v.version_id AND f.file_name <> 'database_profile.xml'
), ''), 256);
ALTER TABLE m2_versions MODIFY files_hash VARCHAR(64) NOT NULL;
CREATE INDEX idx_m2_versions_files_hash ON m2_versions (files_hash);
