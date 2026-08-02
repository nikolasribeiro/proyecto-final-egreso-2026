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

            // 5. Ubicaciones
            $db->exec("INSERT INTO ubicaciones (nombre_lugar, direccion) VALUES 
                ('Hospital de Clínicas - Base Ambulancias', 'Av. Italia s/n, Piso 1'),
                ('Instituto Nacional de Cardiología', 'Av. Italia 2870'),
                ('Hospital Maciel', '25 de Mayo 174')");

            // 6. Estados de Traslado
            $db->exec("INSERT INTO estado_traslados (estado) VALUES 
                ('PENDIENTE'), ('EN_TRANSITO'), ('FINALIZADO'), ('CANCELADO')");

            // 7. Tipos de Vehículo y Vehículos
            $db->exec("INSERT INTO tipo_vehiculo (descripcion) VALUES 
                ('Ambulancia de Apoyo Vital Avanzado'), ('Ambulancia Básica'), ('Auto Utilitario')");

            $db->exec("INSERT INTO vehiculos (estado, matricula, id_tipo_vehiculo) VALUES 
                ('DISPONIBLE', 'SCH-1234', 1),
                ('DISPONIBLE', 'SCH-5678', 2)");

            // 8. Solicitud de Traslado de prueba (Usando las CIs Ficticias)
            $db->exec("INSERT INTO solicitud_traslados (
                id_ubicacion_origen, id_ubicacion_destino, fecha_hora_salida, fecha_hora_llegada_estimada, 
                id_estado, id_vehiculo, ci_chofer, ci_enfermero, ci_administrativo, ci_paciente_externo
            ) VALUES (
                1, 2, NOW(), DATE_ADD(NOW(), INTERVAL 45 MINUTE), 2, 1, 33333333, 44444444, 11111111, '12345678'
            )");

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