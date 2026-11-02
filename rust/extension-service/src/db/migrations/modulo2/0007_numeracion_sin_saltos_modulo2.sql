-- Módulo 2: numeración de versiones sin saltos.
-- Desde ahora, al borrar una versión las siguientes bajan un número; esto corrige los saltos que
-- dejaron los borrados anteriores. Primero se corren todos los números para no chocar con el índice
-- único y después se numeran por orden de creación.
UPDATE m2_versions SET version_number = version_number + 1000000000;
UPDATE m2_versions v
    JOIN (SELECT version_id, ROW_NUMBER() OVER (ORDER BY version_id) AS n FROM m2_versions) numbered
    ON numbered.version_id = v.version_id
    SET v.version_number = numbered.n;
