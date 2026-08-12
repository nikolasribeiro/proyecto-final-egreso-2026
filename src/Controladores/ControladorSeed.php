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

            // 1. Roles — se siembran SIEMPRE que la tabla esté vacía,
            //    independientemente de si hay usuarios o no. El catálogo de
            //    roles es requisito para que el módulo de usuarios funcione
            //    (los checkboxes de roles se hidratan desde acá).
            $stmt = $db->query("SELECT COUNT(*) AS total FROM roles");
            $rolesSembrados = 0;
            if ($stmt->fetch()['total'] === 0) {
                $db->exec("INSERT INTO roles (descripcion_rol, tipo_rol) VALUES
                    ('Administrador del sistema', 'ADMINISTRATIVO'),
                    ('Médico',                    'MEDICO'),
                    ('Chofer',                    'CHOFER'),
                    ('Enfermero',                 'ENFERMERO'),
                    ('Soporte Técnico',           'SOPORTE_TECNICO')");
                $rolesSembrados = 5;
            }

            // Verificación previa para usuarios: Evita duplicar datos si las tablas ya tienen registros
            $stmt = $db->query("SELECT COUNT(*) AS total FROM usuarios");
            if ($stmt->fetch()['total'] > 0) {
                echo json_encode([
                    'exito' => true,
                    'mensaje' => $rolesSembrados > 0
                        ? "Se sembraron {$rolesSembrados} roles del catálogo. La base de datos ya contiene usuarios; se omitió el resto del seed para preservar la integridad."
                        : 'La base de datos ya contiene información. Se omitió la siembra de datos para preservar la integridad.'
                ]);
                return;
            }

            $db->beginTransaction();

            // 2. Usuarios Iniciales (Contraseñas con HASH seguro) - DATOS FICTICIOS
            $passHash = password_hash('12345678', PASSWORD_BCRYPT);
            $db->exec("INSERT INTO usuarios (ci, nombre, apellido, email, contrasena, activo) VALUES
                (11111111, 'Administrador', 'Prueba', 'admin@hospital.com', '{$passHash}', TRUE),
                (22222222, 'Medico', 'Prueba', 'medico@hospital.com', '{$passHash}', TRUE),
                (33333333, 'Chofer', 'Prueba', 'chofer@hospital.com', '{$passHash}', TRUE),
                (44444444, 'Enfermero', 'Prueba', 'enfermero@hospital.com', '{$passHash}', TRUE),
                (55555555, 'Soporte', 'Prueba', 'soporte@hospital.com', '{$passHash}', TRUE)");

            // 2.b Root — usuario bootstrap con privilegios máximos (issue #40).
            //     Username implícito: root. Password inicial: 'root' (temporal,
            //     debe cambiar en el primer login). CI 99999999 para evitar
            //     colisión con los 5 usuarios demo.
            //
            //     ADVERTENCIA: no usar en producción sin regenerar la password
            //     con un valor aleatorio.
            $rootPassHash = password_hash('root', PASSWORD_BCRYPT);
            $db->exec("INSERT INTO usuarios (ci, nombre, apellido, email, contrasena, activo, debe_cambiar_password) VALUES
                (99999999, 'Root', 'Sistema', 'root@hospital.local', '{$rootPassHash}', TRUE, TRUE)");

            // Asignar Roles (los 5 demo por id hardcoded + root por lookup
            // del enum para que no se rompa si cambian los IDs de seed).
            $db->exec("INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (1, 1), (2, 2), (3, 3), (4, 4), (5, 5)");

            // Root → rol ADMINISTRATIVO (id 1 según init.sql).
            $rootId = (int)$db->query("SELECT id FROM usuarios WHERE ci = 99999999")->fetchColumn();
            if ($rootId > 0) {
                $db->exec("INSERT IGNORE INTO usuario_roles (id_usuario, id_rol) VALUES ({$rootId}, 1)");
            }

            // 3. Categorías de Documentos Médicos (Casos reales del Hospital de Clínicas)
            //    Insertamos con slug explícito para que el filtro de la
            //    vista de documentos funcione (#109 + fix del filtro).
            $db->exec("INSERT INTO categorias_documentos (nombre_categoria, slug) VALUES
                ('Ginecobstetricia - IVE',                'ginecobstetricia-ive'),
                ('Urología - Post-operatorio',            'urologia-post-operatorio'),
                ('Imagenología - Preparaciones',          'imagenologia-preparaciones'),
                ('Cardiología - Tratamientos CONTINUOS',  'cardiologia-tratamientos-continuos')");

            // 3.b Self-healing: cualquier categoría existente que haya
            //     quedado con slug NULL (ej. seed anterior a este fix)
            //     se le autocalcula el slug. La columna tiene constraint
            //     UNIQUE así que si dos nombres generan el mismo slug,
            //     el primero gana.
            $db->exec("UPDATE categorias_documentos
                       SET slug = LOWER(REPLACE(nombre_categoria, ' ', '-'))
                       WHERE slug IS NULL OR slug = ''");

            // 4. Documentos de Muestra (Usando la CI del Administrador Ficticio: 11111111)
            $db->exec("INSERT INTO documentos (id_categoria, titulo, ruta_archivo, documento_activo, ci_funcionario) VALUES
                (1, 'Indicaciones de Interrupción Voluntaria del Embarazo', '/uploads/ive_pautas.pdf', TRUE, 11111111),
                (2, 'Prostatectomía Radical: Guía de Cuidados para el Paciente', '/uploads/prostatectomia_guia.pdf', TRUE, 11111111),
                (4, 'Pauta e Indicaciones para Pacientes en Tratamiento con Warfarina', '/uploads/warfarina_tratamiento.pdf', TRUE, 11111111)");

            // SEED: Módulo de Encuestas (Ticket #97)
             $sqlEncuestas = "INSERT IGNORE INTO `encuestas` (`id`, `segmento_dirigido`, `es_anonima`, `token_publico`, `fecha_vencimiento`) VALUES
                (1, 'Pacientes Alta General (Nominada)', 0, NULL, '2027-12-31 23:59:59'),
                (2, 'Atención Puerta Emergencia (Anónima)', 1, 'token_emergencia_anonimo_2026', '2027-12-31 23:59:59')";
                $db->query($sqlEncuestas);

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

            // 7. Tipos de Vehículo (idempotente con init.sql — INSERT IGNORE)
            $db->exec("INSERT IGNORE INTO tipo_vehiculo (descripcion) VALUES
                ('Ambulancia'),
                ('Auto'),
                ('Camión'),
                ('Otro')");

            // 8. Vehículos (10, mezcla de tipos + algunos NO-DISPONIBLES)
            // Mapeo de tipos: 1=Ambulancia, 2=Auto, 3=Camión, 4=Otro.
            // Las matrículas usan prefijo por tipo para que sean legibles
            // a simple vista en el wizard de traslados.
            $db->exec("INSERT INTO vehiculos (estado, matricula, id_tipo_vehiculo, activo) VALUES
                ('DISPONIBLE',     'SCH-1234', 1, TRUE),
                ('DISPONIBLE',     'SCH-5678', 1, TRUE),
                ('DISPONIBLE',     'SCH-9100', 1, TRUE),
                ('DISPONIBLE',     'SCH-1111', 2, TRUE),
                ('DISPONIBLE',     'SCH-2222', 2, TRUE),
                ('DISPONIBLE',     'SCH-3333', 1, TRUE),
                ('DISPONIBLE',     'CAM-0001', 3, TRUE),
                ('DISPONIBLE',     'CAM-0002', 3, TRUE),
                ('NO-DISPONIBLE',  'SCH-9999', 1, TRUE),
                ('NO-DISPONIBLE',  'SCH-8888', 2, TRUE)");

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