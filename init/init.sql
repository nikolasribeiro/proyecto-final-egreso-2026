-- ============================================
-- Schema y seed inicial — proyecto SIGSM
-- Solo se ejecuta cuando MariaDB inicializa un volumen VACÍO.
-- Si el volumen `mariadb_data` ya tiene datos, este script NO se
-- ejecuta automáticamente. Consultar README.md ("Autenticación")
-- para la alternativa manual.
-- ============================================

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    rol ENUM('admin', 'tecnico') NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Seed de usuarios demo (SOLO DESARROLLO)
-- Credenciales:
--   admin@demo.com    / admin    (rol=admin)
--   tecnico@demo.com  / tecnico  (rol=tecnico)
-- Los hashes bcrypt se generaron con:
--   php -r "echo password_hash('admin', PASSWORD_BCRYPT);"
--   php -r "echo password_hash('tecnico', PASSWORD_BCRYPT);"
-- Re-generar y actualizar antes de cualquier despliegue real.
-- ============================================

INSERT INTO usuarios (email, password_hash, nombre, rol)
VALUES
    (
        'admin@demo.com',
        '$2y$12$ASEKVU/uS6bDWFmjXqAEEuggRSWxTNACc/tAHyOnw/ZgCTuU/9MhK',
        'Administrador Demo',
        'admin'
    ),
    (
        'tecnico@demo.com',
        '$2y$12$J/EroPZ1plERyK/o3TZ5tuMqQ.Vd7o2yIKrJ2.pOyuUUI10m1Q00e',
        'Técnico Demo',
        'tecnico'
    )
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    rol = VALUES(rol);
