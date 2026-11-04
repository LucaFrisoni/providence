-- Módulo 3: ámbitos de administración (institución, área o unidad) y su historial de cambios.

-- Ámbito: una institución, un área o una unidad
CREATE TABLE m3_scopes (
    scope_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) COLLATE utf8mb4_bin NOT NULL COMMENT 'Identificador corto y estable, por ejemplo biblioteca',
    name VARCHAR(255) NOT NULL,
    kind VARCHAR(20) NOT NULL COMMENT 'institucion, area o unidad',
    parent_id INT UNSIGNED NULL COMMENT 'Ámbito del que cuelga; NULL en las instituciones',
    description TEXT NULL,
    created_by_user_id INT UNSIGNED NOT NULL COMMENT 'ca_users.user_id de Providence',
    created_by_user_name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by_user_id INT UNSIGNED NOT NULL COMMENT 'ca_users.user_id de Providence',
    updated_by_user_name VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (scope_id),
    UNIQUE KEY uq_m3_scopes_code (code),
    KEY idx_m3_scopes_parent (parent_id),
    CONSTRAINT fk_m3_scopes_parent FOREIGN KEY (parent_id) REFERENCES m3_scopes (scope_id) ON DELETE RESTRICT,
    CONSTRAINT ck_m3_scopes_kind CHECK (kind IN ('institucion', 'area', 'unidad'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Historial de cambios de los ámbitos (usuario, fecha y qué cambió).
CREATE TABLE m3_scope_log (
    log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope_id INT UNSIGNED NULL COMMENT 'Ámbito afectado; sin clave foránea para conservar la línea si se elimina',
    scope_code VARCHAR(50) COLLATE utf8mb4_bin NOT NULL,
    action VARCHAR(20) NOT NULL COMMENT 'creado, modificado o eliminado',
    detail MEDIUMTEXT NOT NULL COMMENT 'JSON con lo que cambió',
    user_id INT UNSIGNED NOT NULL COMMENT 'ca_users.user_id de Providence',
    user_name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_m3_scope_log_scope (scope_id),
    KEY idx_m3_scope_log_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
