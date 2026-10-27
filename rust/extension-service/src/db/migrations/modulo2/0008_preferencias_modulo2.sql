-- Módulo 2: versiones de las preferencias de cada usuario y el registro de sus cambios.
-- Las preferencias son de un solo usuario (Providence las guarda en ca_users): cada usuario tiene sus
-- propias versiones, con su numeración, y solo él las ve.
CREATE TABLE m2_preference_versions (
    version_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL COMMENT 'ca_users.user_id de Providence, dueño de la versión',
    user_name VARCHAR(255) NOT NULL,
    version_number INT UNSIGNED NOT NULL COMMENT 'Correlativo por usuario, sin saltos',
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    parent_version_id INT UNSIGNED NULL COMMENT 'Versión anterior del mismo usuario',
    content_hash VARCHAR(64) NOT NULL,
    preferences MEDIUMTEXT NOT NULL COMMENT 'Preferencias del usuario en JSON, con las claves ordenadas',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version_id),
    UNIQUE KEY uq_m2_preference_versions_hash (user_id, content_hash),
    UNIQUE KEY uq_m2_preference_versions_number (user_id, version_number),
    CONSTRAINT fk_m2_preference_versions_parent FOREIGN KEY (parent_version_id) REFERENCES m2_preference_versions (version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Cada guardado o restauración de preferencias, con las que cambiaron
CREATE TABLE m2_preference_changes (
    change_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id INT UNSIGNED NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    action ENUM('edit', 'restore') NOT NULL,
    pref_group VARCHAR(50) NULL COMMENT 'Para edit: grupo de preferencias de Providence (ui, cataloguing...)',
    changes JSON NULL COMMENT 'Preferencias que cambiaron: [{key, old, new}]',
    version_before_id INT UNSIGNED NULL,
    version_after_id INT UNSIGNED NULL COMMENT 'Versión guardada después, o la restaurada',
    result ENUM('pending', 'applied', 'rejected', 'failed', 'conflict') NOT NULL,
    message TEXT NULL,
    PRIMARY KEY (change_id),
    KEY idx_m2_preference_changes_user (user_id, change_id),
    CONSTRAINT fk_m2_preference_changes_before FOREIGN KEY (version_before_id) REFERENCES m2_preference_versions (version_id) ON DELETE SET NULL,
    CONSTRAINT fk_m2_preference_changes_after FOREIGN KEY (version_after_id) REFERENCES m2_preference_versions (version_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
