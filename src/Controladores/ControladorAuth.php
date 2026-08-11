<?php

namespace Controladores;

use Modelos\ModeloUsuario;
use Nucleo\Conexion;
use Nucleo\Sesion;
use PDO;
use Throwable;

class ControladorAuth
{
    private string $rutaDashboard = '/dashboard/documentos';

    /**
     * Umbral de intentos fallidos antes de bloquear al usuario.
     * El contador se mantiene en sesión; se resetea con login OK
     * o cuando expira la ventana.
     */
    private const MAX_INTENTOS_FALLIDOS = 5;
    private const VENTANA_INTENTOS_SEGUNDOS = 15 * 60;

    /**
     * Muestra el formulario de login
     */
    public function login(): void
    {

        if (Sesion::obtener('user')) {
            redirigir($this->rutaDashboard);
            return;
        }

        // Generar token CSRF
        $csrfToken = Sesion::generarTokenCsrf();

        // Obtener mensaje de error si existe
        $errorMessage = Sesion::obtener('error_login');
        Sesion::eliminar('error_login');

        vista('auth/login', [
            'csrf_token' => $csrfToken,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Procesa el login (POST) contra la BD real.
     *
     * Reemplaza al mock con 4 usuarios hardcoded que vivía acá antes de
     * #114. El identificador es CI (numérico) o email; la contraseña se
     * verifica con `password_verify` contra el hash bcrypt de la tabla
     * `usuarios`. El mensaje de error es SIEMPRE genérico para no
     * permitir enumeración de cuentas.
     */
    public function autenticar(): void
    {
        // Verificar método POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirigir('/login?error=method');
            return;
        }

        // Verificar token CSRF
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (!Sesion::validarTokenCsrf($tokenEnviado)) {
            Sesion::guardar('error_login', 'csrf');
            redirigir('/login?error=csrf');
            return;
        }

        // Rate-limit básico por sesión. No sobrevive a `session_destroy`
        // (logout), pero sí a requests que solo fallan el login.
        if ($this->bloqueadoPorRateLimit()) {
            Sesion::guardar('error_login',
                'Demasiados intentos fallidos. Esperá unos minutos antes de reintentar.'
            );
            redirigir('/login?error=rate_limit');
            return;
        }

        $identifier = trim((string)($_POST['username'] ?? ''));
        $password   = (string)($_POST['password'] ?? '');

        // Validación temprana: campos vacíos NO cuentan como intento
        // fallido (no queremos que el rate-limit bloquee por typos).
        if ($identifier === '' || $password === '') {
            Sesion::guardar('error_login', 'Por favor, completá todos los campos.');
            redirigir('/login?error=empty');
            return;
        }

        try {
            $modelo = new ModeloUsuario();
            $usuario = $modelo->autenticar($identifier, $password);
        } catch (Throwable $e) {
            // Falla técnica de BD: mismo mensaje genérico que credenciales
            // inválidas para no filtrar información al atacante.
            error_log('Auth: error de BD en autenticar(): ' . $e->getMessage());
            $this->registrarIntentoFallido($identifier);
            Sesion::guardar('error_login', 'Credenciales inválidas. Intentá de nuevo.');
            redirigir('/login?error=invalid');
            return;
        }

        if ($usuario === null) {
            $this->registrarIntentoFallido($identifier);
            Sesion::guardar('error_login', 'Credenciales inválidas. Intentá de nuevo.');
            redirigir('/login?error=invalid');
            return;
        }

        // Login OK. Elegimos el rol primario para la sesión: el primero
        // de la lista ordenada alfabéticamente (mismo criterio que ya
        // usaba la matriz de permisos). Si el usuario no tiene roles
        // asignados, caemos a 'usuario' genérico.
        $rol = $usuario['roles'][0] ?? 'usuario';

        Sesion::guardar('user', [
            'id'        => (int)$usuario['id'],
            'username'  => $identifier,
            'nombre'    => $usuario['nombre'],
            'apellido'  => $usuario['apellido'],
            'ci'        => (int)$usuario['ci'],
            'email'     => $usuario['email'],
            'rol'       => $rol,
            'roles'     => $usuario['roles'],
            'login_at'  => date('Y-m-d H:i:s'),
        ]);

        // Reset del contador de intentos fallidos.
        Sesion::eliminar('login_intentos');
        Sesion::eliminar('login_intentos_hasta');

        // Regenerar sesión para prevenir session fixation.
        session_regenerate_id(true);

        $this->registrarAuditoriaLogin($usuario);

        // Si la cuenta fue marcada con debe_cambiar_password (caso típico:
        // el usuario root recién creado con password 'root'), forzamos
        // el cambio antes de dejarlo entrar al sistema (#40).
        if (!empty($usuario['debe_cambiar_password'])) {
            redirigir('/cambiar-password');
            return;
        }

        redirigir($this->rutaDashboard);
    }

    /**
     * Cierra la sesión
     */
    public function logout(): void
    {
        // Logueamos el LOGOUT con el id real del usuario antes de
        // destruir la sesión, para no perder trazabilidad.
        $user = Sesion::obtener('user');
        if (is_array($user) && !empty($user['id'])) {
            try {
                $db = Conexion::obtenerInstancia();
                $stmt = $db->prepare(
                    "INSERT INTO logs_auditoria
                       (id_usuario, accion, tabla_afectada, registro_id, detalles, ip_origen, fecha_hora)
                     VALUES (:u, 'LOGOUT', 'usuarios', :uid, JSON_OBJECT('username', :un), :ip, NOW())"
                );
                $stmt->execute([
                    'u'   => (int)$user['id'],
                    'uid' => (int)$user['id'],
                    'un'  => (string)($user['username'] ?? ''),
                    'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (Throwable $e) {
                error_log('Auth: no se pudo registrar LOGOUT: ' . $e->getMessage());
            }
        }

        Sesion::destruir();
        redirigir('/login?message=logout');
    }

    /**
     * Muestra el formulario de cambio de contraseña obligatorio (#40).
     * Lo usa ControladorAuth::autenticar() cuando el usuario tiene
     * `debe_cambiar_password = TRUE` (caso típico: bootstrap del root).
     */
    public function cambiarPassword(): void
    {
        $user = Sesion::obtener('user');
        if (!is_array($user) || empty($user['id'])) {
            redirigir('/login');
            return;
        }

        $errorMessage = Sesion::obtener('error_password');
        Sesion::eliminar('error_password');

        vista('auth/cambiar-password', [
            'csrf_token'   => Sesion::generarTokenCsrf(),
            'error_message' => $errorMessage,
            'usuario'      => $user,
        ]);
    }

    /**
     * Procesa el cambio de contraseña obligatorio (#40).
     *
     * Validaciones:
     *   - Sesión activa con usuario logueado.
     *   - Password actual correcto (re-autenticación).
     *   - Password nueva distinta de la actual.
     *   - Password nueva ≥ 8 chars y confirmación matchea.
     *
     * Si todo OK: baja el flag `debe_cambiar_password`, hashea y persiste
     * la nueva, regenera sesión, redirige al dashboard.
     */
    public function cambiarPasswordSubmit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirigir('/cambiar-password');
            return;
        }

        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (!Sesion::validarTokenCsrf($tokenEnviado)) {
            Sesion::guardar('error_password', 'csrf');
            redirigir('/cambiar-password?error=csrf');
            return;
        }

        $user = Sesion::obtener('user');
        if (!is_array($user) || empty($user['id'])) {
            redirigir('/login');
            return;
        }

        $passActual  = (string)($_POST['password_actual'] ?? '');
        $passNueva   = (string)($_POST['password_nueva'] ?? '');
        $passConfirma = (string)($_POST['password_confirma'] ?? '');

        if ($passNueva === '' || strlen($passNueva) < 8) {
            Sesion::guardar('error_password', 'La nueva contraseña debe tener al menos 8 caracteres.');
            redirigir('/cambiar-password?error=weak');
            return;
        }
        if ($passNueva !== $passConfirma) {
            Sesion::guardar('error_password', 'La confirmación no coincide con la nueva contraseña.');
            redirigir('/cambiar-password?error=mismatch');
            return;
        }
        if ($passNueva === $passActual) {
            Sesion::guardar('error_password', 'La nueva contraseña debe ser distinta de la actual.');
            redirigir('/cambiar-password?error=same');
            return;
        }

        try {
            $db = Conexion::obtenerInstancia();
            $stmt = $db->prepare('SELECT contrasena FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int)$user['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($passActual, (string)$row['contrasena'])) {
                Sesion::guardar('error_password', 'La contraseña actual no es correcta.');
                redirigir('/cambiar-password?error=wrong_current');
                return;
            }

            $nuevoHash = password_hash($passNueva, PASSWORD_BCRYPT);
            $upd = $db->prepare(
                'UPDATE usuarios
                    SET contrasena = :h, debe_cambiar_password = FALSE
                  WHERE id = :id'
            );
            $upd->execute(['h' => $nuevoHash, 'id' => (int)$user['id']]);

            // Auditoría.
            $aud = $db->prepare(
                "INSERT INTO logs_auditoria
                   (id_usuario, accion, tabla_afectada, registro_id, detalles, ip_origen, fecha_hora)
                 VALUES (:u, 'ACTUALIZAR', 'usuarios', :uid,
                         JSON_OBJECT('evento', 'cambio_password_obligatorio'),
                         :ip, NOW())"
            );
            $aud->execute([
                'u'   => (int)$user['id'],
                'uid' => (int)$user['id'],
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            // Refrescar la sesión para que el flag quede en false.
            Sesion::guardar('user', array_merge($user, ['debe_cambiar_password' => false]));

            redirigir($this->rutaDashboard);
        } catch (\Throwable $e) {
            error_log('cambiarPasswordSubmit: ' . $e->getMessage());
            Sesion::guardar('error_password', 'Error interno al cambiar la contraseña.');
            redirigir('/cambiar-password?error=internal');
        }
    }

    /**
     * Indica si la sesión actual está bloqueada por exceso de intentos
     * fallidos. Usa una ventana deslizante simple (timestamp de expiración
     * en sesión).
     */
    private function bloqueadoPorRateLimit(): bool
    {
        $hasta = Sesion::obtener('login_intentos_hasta');
        if ($hasta !== null && (int)$hasta > time()) {
            return true;
        }
        if ($hasta !== null && (int)$hasta <= time()) {
            // Ventana expirada: limpiamos y dejamos pasar.
            Sesion::eliminar('login_intentos');
            Sesion::eliminar('login_intentos_hasta');
        }
        return false;
    }

    /**
     * Suma 1 al contador de intentos fallidos y, si supera el umbral,
     * setea la ventana de bloqueo.
     */
    private function registrarIntentoFallido(string $identifier): void
    {
        $intentos = (int)Sesion::obtener('login_intentos', 0) + 1;
        Sesion::guardar('login_intentos', $intentos);
        if ($intentos >= self::MAX_INTENTOS_FALLIDOS) {
            Sesion::guardar(
                'login_intentos_hasta',
                time() + self::VENTANA_INTENTOS_SEGUNDOS
            );
        }
        $this->registrarAuditoriaLoginFail($identifier);
    }

    /**
     * Inserta una fila en `logs_auditoria` con accion='LOGIN' y el id
     * real del usuario. Falla silenciosa: un fallo de logging no debe
     * impedir el login (mismo criterio que el helper canónico en los
     * modelos).
     */
    private function registrarAuditoriaLogin(array $usuario): void
    {
        try {
            $db = Conexion::obtenerInstancia();
            $stmt = $db->prepare(
                "INSERT INTO logs_auditoria
                   (id_usuario, accion, tabla_afectada, registro_id, detalles, ip_origen, fecha_hora)
                 VALUES (:u, 'LOGIN', 'usuarios', :uid, JSON_OBJECT('username', :un), :ip, NOW())"
            );
            $stmt->execute([
                'u'   => (int)$usuario['id'],
                'uid' => (int)$usuario['id'],
                'un'  => (string)($usuario['email'] ?? ''),
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('Auth: no se pudo registrar LOGIN: ' . $e->getMessage());
        }
    }

    /**
     * Inserta una fila en `logs_auditoria` con accion='LOGIN_FAIL' y
     * `id_usuario = NULL` (porque no necesariamente corresponde a nadie).
     * El detalle lleva el identificador que se tipeó, útil para detectar
     * patrones de ataque.
     */
    private function registrarAuditoriaLoginFail(string $identifier): void
    {
        try {
            $db = Conexion::obtenerInstancia();
            $stmt = $db->prepare(
                "INSERT INTO logs_auditoria
                   (id_usuario, accion, tabla_afectada, registro_id, detalles, ip_origen, fecha_hora)
                 VALUES (NULL, 'LOGIN_FAIL', 'usuarios', NULL, JSON_OBJECT('identifier_tipeado', :id), :ip, NOW())"
            );
            $stmt->execute([
                'id' => $identifier,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('Auth: no se pudo registrar LOGIN_FAIL: ' . $e->getMessage());
        }
    }
}