-- Módulo 1: decisiones institucionales y versiones del perfil del modelo de información.
-- Decisión: el propósito o criterio que explica una parte del modelo o de su configuración.
CREATE TABLE m1_decisions (
    decision_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference VARCHAR(255) COLLATE utf8mb4_bin NOT NULL COMMENT 'Clave de la parte del perfil, por ejemplo configuracion:ca_objects/Jerarquía',
    purpose TEXT NOT NULL,
    updated_by_user_id INT UNSIGNED NOT NULL COMMENT 'ca_users.user_id de Providence',
    updated_by_user_name VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (decision_id),
    UNIQUE KEY uq_m1_decisions_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Versión del perfil: copia completa del perfil en json en un momento dado
CREATE TABLE m1_profile_versions (
    version_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    content_hash CHAR(16) NOT NULL COMMENT 'Huella del perfil, para saber si cambió desde la última versión',
    content MEDIUMTEXT NOT NULL COMMENT 'Perfil completo en JSON',
    created_by_user_id INT UNSIGNED NOT NULL COMMENT 'ca_users.user_id de Providence',
    created_by_user_name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Lista de versiones ordenada por fecha
CREATE INDEX idx_m1_profile_versions_created_at ON m1_profile_versions (created_at);
