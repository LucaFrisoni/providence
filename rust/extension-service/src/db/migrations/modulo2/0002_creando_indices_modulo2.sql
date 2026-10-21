-- Módulo 2: índices de las tablas creadas en 0001_creando_tablas_modulo2.

-- Lista de versiones ordenada por fecha
CREATE INDEX idx_m2_versions_created_at ON m2_versions (created_at);
-- Búsqueda de una versión idéntica al estado actual, y no permite dos versiones iguales
CREATE UNIQUE INDEX uq_m2_versions_content_hash ON m2_versions (content_hash);

-- Registro de cambios por fecha y por configuración
CREATE INDEX idx_m2_change_log_changed_at ON m2_change_log (changed_at);
CREATE INDEX idx_m2_change_log_config_key ON m2_change_log (config_key);
