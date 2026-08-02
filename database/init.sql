CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ci` INT UNIQUE NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) UNIQUE NOT NULL,
  `contrasena` VARCHAR(255) NOT NULL,
  `activo` BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `descripcion_rol` VARCHAR(100),
  `tipo_rol` ENUM('ADMINISTRATIVO', 'MEDICO', 'CHOFER', 'ENFERMERO') NOT NULL
);

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
  FOREIGN KEY (`id_tipo_vehiculo`) REFERENCES `tipo_vehiculo`(`id`)
);

CREATE TABLE IF NOT EXISTS `logs_auditoria` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT,
  `accion` ENUM('CREAR', 'ACTUALIZAR', 'ELIMINAR', 'LOGIN', 'LOGOUT') NOT NULL,
  `tabla_afectada` VARCHAR(100) NOT NULL,
  `registro_id` INT,
  `detalles` JSON,
  `ip_origen` VARCHAR(45),
  `fecha_hora` DATETIME,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`)
);


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