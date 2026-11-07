-- Módulo 2: tablas de versionado y registro de cambios de las configuraciones.
-- Versión: copia de los archivos de app/conf/local en un momento dado
CREATE TABLE m2_versions (
    version_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COMMENT 'Por defecto: fecha, hora y usuario',
    description TEXT NULL,
    is_automatic TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = creada sola antes de un cambio o una restauración',
    parent_version_id INT UNSIGNED NULL COMMENT 'Versión vigente cuando se creó esta',
    content_hash VARCHAR(64) NOT NULL COMMENT 'Hash del contenido, para detectar cambios hechos fuera del módulo',
    created_by_user_id INT UNSIGNED NOT NULL COMMENT 'ca_users.user_id de Providence',
    created_by_user_name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version_id),
    CONSTRAINT fk_m2_versions_parent FOREIGN KEY (parent_version_id) REFERENCES m2_versions (version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Contenido de cada archivo de una versión
CREATE TABLE m2_version_files (
    version_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(100) NOT NULL COMMENT 'Nombre en app/conf/local, por ejemplo app.conf',
    content MEDIUMTEXT NOT NULL,
    PRIMARY KEY (version_id, file_name),
    CONSTRAINT fk_m2_version_files_version FOREIGN KEY (version_id) REFERENCES m2_versions (version_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Registro de cambios: qué se cambió, quién, cuándo, sobre qué versión y con qué resultado
CREATE TABLE m2_change_log (
    change_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id INT UNSIGNED NOT NULL COMMENT 'ca_users.user_id de Providence',
    user_name VARCHAR(255) NOT NULL,
    action ENUM('edit', 'restore') NOT NULL,
    file_name VARCHAR(100) NULL COMMENT 'Para edit: archivo de app/conf/local',
    config_key VARCHAR(255) NULL COMMENT 'Para edit: clave del catálogo',
    old_value JSON NULL,
    new_value JSON NULL,
    version_before_id INT UNSIGNED NULL COMMENT 'Versión guardada antes del cambio',
    version_after_id INT UNSIGNED NULL COMMENT 'Para restore: versión restaurada',
    result ENUM('applied', 'rejected', 'failed', 'conflict') NOT NULL,
    message TEXT NULL COMMENT 'Motivo del rechazo o detalle del error',
    PRIMARY KEY (change_id),
    CONSTRAINT fk_m2_change_log_before FOREIGN KEY (version_before_id) REFERENCES m2_versions (version_id),
    CONSTRAINT fk_m2_change_log_after FOREIGN KEY (version_after_id) REFERENCES m2_versions (version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
