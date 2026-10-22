-- Módulo 2: número estable de cada versión y versiones que se pueden borrar.
-- El número que ve el usuario se guarda, así no cambia cuando se borra una versión anterior.
ALTER TABLE m2_versions ADD COLUMN version_number INT UNSIGNED NULL AFTER version_id;
UPDATE m2_versions v
    JOIN (SELECT version_id, ROW_NUMBER() OVER (ORDER BY version_id) AS n FROM m2_versions) numbered
    ON numbered.version_id = v.version_id
    SET v.version_number = numbered.n;
ALTER TABLE m2_versions MODIFY version_number INT UNSIGNED NOT NULL;
CREATE UNIQUE INDEX uq_m2_versions_number ON m2_versions (version_number);

-- Al borrar una versión, el registro de cambios conserva el cambio sin el enlace a la versión
ALTER TABLE m2_change_log
    DROP FOREIGN KEY fk_m2_change_log_before,
    DROP FOREIGN KEY fk_m2_change_log_after;
ALTER TABLE m2_change_log
    ADD CONSTRAINT fk_m2_change_log_before FOREIGN KEY (version_before_id) REFERENCES m2_versions (version_id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_m2_change_log_after FOREIGN KEY (version_after_id) REFERENCES m2_versions (version_id) ON DELETE SET NULL;
