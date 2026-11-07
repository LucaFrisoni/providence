-- Módulo 2: historial de una configuración ordenado por fecha.
-- El índice de 0002 sobre config_key encuentra los cambios de una clave, pero MySQL tiene que
-- leerlos todos y ordenarlos para mostrar los últimos o los de un rango de fechas. El índice
-- compuesto los devuelve ya ordenados y también sirve para buscar solo por config_key, así que
-- reemplaza al anterior.
CREATE INDEX idx_m2_change_log_config_key_changed_at ON m2_change_log (config_key, changed_at);
DROP INDEX idx_m2_change_log_config_key ON m2_change_log;
