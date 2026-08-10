CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ci` INT UNIQUE NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) UNIQUE NOT NULL,
  `contrasena` VARCHAR(255) NOT NULL,
  `activo` BOOLEAN DEFAULT TRUE,
  `fecha_alta` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `descripcion_rol` VARCHAR(100),
  `tipo_rol` ENUM('ADMINISTRATIVO', 'MEDICO', 'CHOFER', 'ENFERMERO', 'SOPORTE_TECNICO') NOT NULL
);

-- Seed automático del catálogo de roles (idempotente: INSERT IGNORE).
-- Garantiza que los 5 roles del sistema existan al levantar el proyecto
-- desde cero, sin depender de la ejecución manual de /seed.
INSERT IGNORE INTO `roles` (`descripcion_rol`, `tipo_rol`) VALUES
  ('Administrador del sistema',  'ADMINISTRATIVO'),
  ('Médico',                     'MEDICO'),
  ('Chofer',                     'CHOFER'),
  ('Enfermero',                  'ENFERMERO'),
  ('Soporte Técnico',            'SOPORTE_TECNICO');

CREATE TABLE IF NOT EXISTS `categorias_documentos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_categoria` VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS `encuestas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `segmento_dirigido` VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS `ubicaciones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_lugar` VARCHAR(150) NOT NULL,
  `direccion` VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS `estado_traslados` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `estado` ENUM('PENDIENTE', 'EN_TRANSITO', 'FINALIZADO', 'CANCELADO') NOT NULL
);

CREATE TABLE IF NOT EXISTS `tipo_vehiculo` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `descripcion` VARCHAR(100) NOT NULL
);

-- Seed del catálogo de tipos de vehículo (idempotente: INSERT IGNORE).
-- Garantiza que los 4 tipos básicos existan siempre, sin importar si el
-- seeder corrió o no. Issue #131.
INSERT IGNORE INTO `tipo_vehiculo` (`descripcion`) VALUES
  ('Ambulancia'),
  ('Auto'),
  ('Camión'),
  ('Otro');


CREATE TABLE IF NOT EXISTS `usuario_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL,
  `id_rol` INT NOT NULL,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_rol`) REFERENCES `roles`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `documentos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_categoria` INT NOT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `ruta_archivo` VARCHAR(255) NOT NULL,
  `documento_activo` BOOLEAN,
  `ci_funcionario` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_categoria`) REFERENCES `categorias_documentos`(`id`),
  FOREIGN KEY (`ci_funcionario`) REFERENCES `usuarios`(`ci`)
);

CREATE TABLE IF NOT EXISTS `respuestas_encuesta` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_encuesta` INT NOT NULL,
  `calificacion` INT NOT NULL,
  `fecha_respuesta` DATE DEFAULT CURRENT_DATE,
  FOREIGN KEY (`id_encuesta`) REFERENCES `encuestas`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `vehiculos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `estado` ENUM('DISPONIBLE', 'NO-DISPONIBLE'),
  `matricula` VARCHAR(20) UNIQUE NOT NULL,
  `id_tipo_vehiculo` INT NOT NULL,
  `activo` BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (`id_tipo_vehiculo`) REFERENCES `tipo_vehiculo`(`id`)
);

CREATE TABLE IF NOT EXISTS `logs_auditoria` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT,
  `accion` ENUM('CREAR', 'ACTUALIZAR', 'ELIMINAR', 'LOGIN', 'LOGOUT', 'LOGIN_FAIL') NOT NULL,
  `tabla_afectada` VARCHAR(100) NOT NULL,
  `registro_id` INT,
  `detalles` JSON,
  `ip_origen` VARCHAR(45),
  `fecha_hora` DATETIME,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`)
);

-- ============================================================
-- Migración fix/issue-114-auth-real-bd
-- Extiende el enum `logs_auditoria.accion` con `LOGIN_FAIL` para
-- registrar intentos de autenticación fallidos (id_usuario NULL en
-- ese caso, porque el identificador puede no corresponder a nadie).
-- Idempotente: MODIFY COLUMN reescribe el enum al valor deseado.
-- ============================================================
ALTER TABLE `logs_auditoria`
  MODIFY COLUMN `accion`
  ENUM('CREAR','ACTUALIZAR','ELIMINAR','LOGIN','LOGOUT','LOGIN_FAIL') NOT NULL;


CREATE TABLE IF NOT EXISTS `solicitud_traslados` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_ubicacion_origen` INT NOT NULL,
  `id_ubicacion_destino` INT NOT NULL,
  `fecha_hora_salida` DATETIME NOT NULL,
  `fecha_hora_llegada_estimada` DATETIME NOT NULL,
  `fecha_hora_llegada_efectiva` DATETIME,
  `id_estado` INT NOT NULL,
  `id_vehiculo` INT NOT NULL,
  `ci_chofer` INT NOT NULL,
  `ci_enfermero` INT,
  `ci_administrativo` INT NOT NULL,
  `ci_paciente_externo` VARCHAR(50),
  FOREIGN KEY (`id_ubicacion_origen`) REFERENCES `ubicaciones`(`id`),
  FOREIGN KEY (`id_ubicacion_destino`) REFERENCES `ubicaciones`(`id`),
  FOREIGN KEY (`id_estado`) REFERENCES `estado_traslados`(`id`),
  FOREIGN KEY (`id_vehiculo`) REFERENCES `vehiculos`(`id`),
  FOREIGN KEY (`ci_chofer`) REFERENCES `usuarios`(`ci`),
  FOREIGN KEY (`ci_enfermero`) REFERENCES `usuarios`(`ci`),
  FOREIGN KEY (`ci_administrativo`) REFERENCES `usuarios`(`ci`)
);

CREATE TABLE IF NOT EXISTS `reporte_reemplazo` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_solicitud` INT NOT NULL,
  `razon` VARCHAR(255) NOT NULL,
  FOREIGN KEY (`id_solicitud`) REFERENCES `solicitud_traslados`(`id`) ON DELETE CASCADE
);

-- ============================================================
-- Migración feat/99-integrar-modulo-traslados
-- Ampliación de solicitud_traslados + tablas hijas para
-- multi-destino y reportes por destino.
-- ============================================================

-- Ampliar solicitud_traslados con tipo, datos clínicos, prioridad, vuelta
ALTER TABLE `solicitud_traslados`
  ADD COLUMN IF NOT EXISTS `tipo` ENUM('paciente_alta','biologico','equipamiento') NOT NULL DEFAULT 'paciente_alta' AFTER `id`,
  ADD COLUMN IF NOT EXISTS `estado_critico` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `requiere_camilla` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `tipo_diagnostico` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `jerarquia_enfermero` ENUM('licenciado','auxiliar','profesional') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `volver_al_origen` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `prioridad` ENUM('rojo','amarillo','verde') NOT NULL DEFAULT 'verde';

-- Tabla hija: múltiples destinos por solicitud, con estado individual
CREATE TABLE IF NOT EXISTS `destinos_traslado` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_solicitud` INT NOT NULL,
  `orden` INT NOT NULL,
  `id_ubicacion` INT NOT NULL,
  `fecha_llegada_estimada` DATETIME DEFAULT NULL,
  `fecha_llegada_efectiva` DATETIME DEFAULT NULL,
  `estado_destino` ENUM('PENDIENTE','EN_TRANSITO','ARRIBADO') NOT NULL DEFAULT 'PENDIENTE',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_solicitud`) REFERENCES `solicitud_traslados`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_ubicacion`) REFERENCES `ubicaciones`(`id`),
  UNIQUE KEY `uq_solicitud_orden` (`id_solicitud`, `orden`)
);

-- Reportes por destino (reemplaza reporte_reemplazo de forma específica)
CREATE TABLE IF NOT EXISTS `reportes_destino` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_destino` INT NOT NULL,
  `tipo_problema` VARCHAR(100) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `fecha_reporte` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_destino`) REFERENCES `destinos_traslado`(`id`) ON DELETE CASCADE
);

-- Agregar la columna slug a la tabla
ALTER TABLE categorias_documentos 
ADD COLUMN slug VARCHAR(80) UNIQUE;

-- Poblar los slugs existentes transformando el nombre de la categoría
-- (Convierte a minúsculas y reemplaza espacios por guiones)
UPDATE categorias_documentos
SET slug = LOWER(REPLACE(nombre_categoria, ' ', '-'))
WHERE slug IS NULL;

-- ============================================================
-- Migración feat/127-gestion-usuarios
-- Ampliación del catálogo de roles + columna fecha_alta en usuarios,
-- requeridos por el CRUD de gestión de usuarios.
-- ============================================================

-- Soporte Técnico no existía en el enum original. Se agrega para soportar
-- el rol del catálogo definido en `Nucleo\Constantes\Roles`.
ALTER TABLE `roles`
  MODIFY COLUMN `tipo_rol`
  ENUM('ADMINISTRATIVO','MEDICO','CHOFER','ENFERMERO','SOPORTE_TECNICO') NOT NULL;

-- Fecha de alta automática: la pide el issue para que la tabla muestre
-- "Fecha de Alta" sin tener que inferirla por orden de id.
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `fecha_alta` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `activo`;