<?php

namespace Controladores;

use Nucleo\Conexion;
use PDO;

class ControladorSeed {

    public function ejecutar(): void {
        header('Content-Type: application/json');

        // Validacion de Seguridad: Previene ejecuciones indeseadas en Producción
        $token = $_GET['token'] ?? '';
        $seedSecret = $_ENV['SEED_SECRET'] ?? 'secret_dev_key_2026';

        if (($_ENV['APP_ENV'] ?? 'dev') === 'production' && $token !== $seedSecret) {
            http_response_code(403);
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Acceso denegado: El ejecutor /seed está desactivado en producción o requiere un token válido.'
            ]);
            return;
        }

        try {
            $db = Conexion::obtenerInstancia();

            // Verificación previa: Evita duplicar datos si las tablas ya tienen registros
            $stmt = $db->query("SELECT COUNT(*) AS total FROM usuarios");
            if ($stmt->fetch()['total'] > 0) {
                echo json_encode([
                    'exito' => true,
                    'mensaje' => 'La base de datos ya contiene información. Se omitió la siembra de datos para preservar la integridad.'
                ]);
                return;
            }

            $db->beginTransaction();

            // 1. Roles
            $db->exec("INSERT INTO roles (descripcion_rol, tipo_rol) VALUES
                ('Administrador DTI', 'ADMINISTRATIVO'),
                ('Médico Especialista', 'MEDICO'),
                ('Chofer de Ambulancia', 'CHOFER'),
                ('Enfermero de Traslado', 'ENFERMERO')");

            // 2. Usuarios Iniciales (Contraseñas con HASH seguro) - DATOS FICTICIOS
            $passHash = password_hash('12345678', PASSWORD_BCRYPT);
            $db->exec("INSERT INTO usuarios (ci, nombre, apellido, email, contrasena, activo) VALUES
                (11111111, 'Administrador', 'Prueba', 'admin@hospital.com', '{$passHash}', TRUE),
                (22222222, 'Medico', 'Prueba', 'medico@hospital.com', '{$passHash}', TRUE),
                (33333333, 'Chofer', 'Prueba', 'chofer@hospital.com', '{$passHash}', TRUE),
                (44444444, 'Enfermero', 'Prueba', 'enfermero@hospital.com', '{$passHash}', TRUE)");

            // Asignar Roles
            $db->exec("INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (1, 1), (2, 2), (3, 3), (4, 4)");

            // 3. Categorías de Documentos Médicos (Casos reales del Hospital de Clínicas)
            $db->exec("INSERT INTO categorias_documentos (nombre_categoria) VALUES
                ('Ginecobstetricia - IVE'),
                ('Urología - Post-operatorio'),
                ('Imagenología - Preparaciones'),
                ('Cardiología - Tratamientos CONTINUOS')");

            // 4. Documentos de Muestra (Usando la CI del Administrador Ficticio: 11111111)
            $db->exec("INSERT INTO documentos (id_categoria, titulo, ruta_archivo, documento_activo, ci_funcionario) VALUES
                (1, 'Indicaciones de Interrupción Voluntaria del Embarazo', '/uploads/ive_pautas.pdf', TRUE, 11111111),
                (2, 'Prostatectomía Radical: Guía de Cuidados para el Paciente', '/uploads/prostatectomia_guia.pdf', TRUE, 11111111),
                (4, 'Pauta e Indicaciones para Pacientes en Tratamiento con Warfarina', '/uploads/warfarina_tratamiento.pdf', TRUE, 11111111)");

            // 5. Ubicaciones (10 destinos reales del Hospital de Clínicas)
            $db->exec("INSERT INTO ubicaciones (nombre_lugar, direccion) VALUES
                ('Hospital de Clínicas - Base Ambulancias', 'Av. Italia s/n, Piso 1'),
                ('Instituto Nacional de Cardiología', 'Av. Italia 2870'),
                ('Hospital Maciel', '25 de Mayo 174'),
                ('Hospital Británico', 'Av. Italia s/n'),
                ('ASSE - Centro Hospitalario Pereira Rossell', '18 de Julio 1892'),
                ('Médica Uruguaya', 'Constituyente 1824'),
                ('Sanatorio Americano', 'Guido 1900'),
                ('Hospital Pasteur', 'Monte Caseros 2629'),
                ('Hospital Militar', 'Av. 8 de Octubre 3060'),
                ('Centro de Salud Ciudad Vieja', 'Juncal 1395')");

            // 6. Estados de Traslado
            $db->exec("INSERT INTO estado_traslados (estado) VALUES
                ('PENDIENTE'), ('EN_TRANSITO'), ('FINALIZADO'), ('CANCELADO')");

            // 7. Tipos de Vehículo
            $db->exec("INSERT INTO tipo_vehiculo (descripcion) VALUES
                ('Ambulancia de Apoyo Vital Avanzado'),
                ('Ambulancia Básica'),
                ('Auto Utilitario'),
                ('Camión de carga')");

            // 8. Vehículos (10, mezcla de tipos + algunos NO-DISPONIBLES)
            $db->exec("INSERT INTO vehiculos (estado, matricula, id_tipo_vehiculo) VALUES
                ('DISPONIBLE',     'SCH-1234', 1),
                ('DISPONIBLE',     'SCH-5678', 2),
                ('DISPONIBLE',     'SCH-9100', 2),
                ('DISPONIBLE',     'SCH-1111', 3),
                ('DISPONIBLE',     'SCH-2222', 3),
                ('DISPONIBLE',     'SCH-3333', 1),
                ('DISPONIBLE',     'CAM-0001', 4),
                ('DISPONIBLE',     'CAM-0002', 4),
                ('NO-DISPONIBLE',  'SCH-9999', 1),
                ('NO-DISPONIBLE',  'SCH-8888', 2)");

            // 9. Usuarios extra (10) — 6 choferes + 4 enfermeros
            $db->exec("INSERT INTO usuarios (ci, nombre, apellido, email, contrasena, activo) VALUES
                (50000001, 'Juan',      'Pérez',       'juan.perez@hospi.uy',     '{$passHash}', TRUE),
                (50000002, 'Carlos',    'Rodríguez',   'carlos@hospi.uy',         '{$passHash}', TRUE),
                (50000003, 'María',     'García',      'maria@hospi.uy',          '{$passHash}', TRUE),
                (50000004, 'Pedro',     'Martínez',    'pedro@hospi.uy',          '{$passHash}', TRUE),
                (50000005, 'Lucía',     'Fernández',   'lucia@hospi.uy',          '{$passHash}', TRUE),
                (50000006, 'Diego',     'Silva',       'diego@hospi.uy',          '{$passHash}', TRUE),
                (50000007, 'Ana',       'Martínez',    'ana.martinez@hospi.uy',   '{$passHash}', TRUE),
                (50000008, 'Roberto',   'López',       'roberto@hospi.uy',        '{$passHash}', TRUE),
                (50000009, 'Laura',     'González',    'laura@hospi.uy',          '{$passHash}', TRUE),
                (50000010, 'Sofía',     'Pereira',     'sofia@hospi.uy',          '{$passHash}', TRUE)");

            // Asignar roles: 50000001-50000006 son CHOFERES, 50000007-50000010 son ENFERMEROS
            // Obtener IDs de los usuarios recién insertados (CI 50000001 → id=5, etc.)
            $stmt = $db->query("SELECT id, ci FROM usuarios WHERE ci IN (50000001,50000002,50000003,50000004,50000005,50000006,50000007,50000008,50000009,50000010)");
            $usuariosExtra = [];
            foreach ($stmt->fetchAll() as $u) {
                $usuariosExtra[$u['ci']] = (int)$u['id'];
            }
            $rolValues = [];
            foreach ([50000001, 50000002, 50000003, 50000004, 50000005, 50000006] as $ci) {
                if (isset($usuariosExtra[$ci])) {
                    $rolValues[] = '(' . $usuariosExtra[$ci] . ', 3)';
                }
            }
            foreach ([50000007, 50000008, 50000009, 50000010] as $ci) {
                if (isset($usuariosExtra[$ci])) {
                    $rolValues[] = '(' . $usuariosExtra[$ci] . ', 4)';
                }
            }
            if (!empty($rolValues)) {
                $db->exec("INSERT INTO usuario_roles (id_usuario, id_rol) VALUES " . implode(',', $rolValues));
            }

            // 10. Tres traslados activos (variedad de tipos y prioridades)
            $db->exec("INSERT INTO solicitud_traslados (
                id_ubicacion_origen, id_ubicacion_destino, fecha_hora_salida,
                fecha_hora_llegada_estimada, id_estado, id_vehiculo,
                ci_chofer, ci_enfermero, ci_administrativo, ci_paciente_externo,
                tipo, estado_critico, requiere_camilla, tipo_diagnostico,
                jerarquia_enfermero, volver_al_origen, prioridad
            ) VALUES
                (1, 2, NOW(), DATE_ADD(NOW(), INTERVAL 45 MINUTE), 2, 1,
                 33333333, 44444444, 11111111, '12345678',
                 'paciente_alta', 1, 1, 'Cardiológico', 'licenciado', 0, 'rojo'),
                (1, 3, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE), 2, 6,
                 50000001, 50000007, 11111111, '87654321',
                 'paciente_alta', 0, 0, NULL, NULL, 1, 'verde'),
                (1, 4, NOW(), DATE_ADD(NOW(), INTERVAL 60 MINUTE), 1, 7,
                 50000002, 50000008, 11111111, NULL,
                 'biologico', 0, 0, NULL, 'auxiliar', 0, 'amarillo')");

            // 11. Destinos para los traslados anteriores (orden 1 para cada uno)
            $db->exec("INSERT INTO destinos_traslado
                (id_solicitud, orden, id_ubicacion, fecha_llegada_estimada, estado_destino)
                VALUES
                (1, 1, 2, DATE_ADD(NOW(), INTERVAL 45 MINUTE), 'EN_TRANSITO'),
                (2, 1, 3, DATE_ADD(NOW(), INTERVAL 30 MINUTE), 'EN_TRANSITO'),
                (2, 2, 1, DATE_ADD(NOW(), INTERVAL 75 MINUTE), 'PENDIENTE'),
                (3, 1, 4, DATE_ADD(NOW(), INTERVAL 60 MINUTE), 'PENDIENTE')");

            $db->commit();

            echo json_encode([
                'exito' => true,
                'mensaje' => 'La base de datos del S.I.G.S.M. fue poblada satisfactoriamente con los registros semilla iniciales.'
            ]);

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Error durante el seeding: ' . $e->getMessage()
            ]);
        }
    }
}