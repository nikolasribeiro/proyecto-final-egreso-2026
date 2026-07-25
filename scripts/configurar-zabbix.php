<?php
/**
 * Provisionamiento idempotente de usuarios en Zabbix para SSO.
 *
 * Crea (o reutiliza, si ya existen) los usuarios `admin@demo.com`
 * y `tecnico@demo.com` con los roles y grupos adecuados para
 * que el SSO via HTTP Authentication funcione.
 *
 * Configuración manual complementaria (una vez) en la UI de Zabbix:
 *   Users → Authentication → HTTP settings
 *     - HTTP authentication: ON
 *     - Default login form: HTTP login
 *     - Case-sensitive login: OFF
 *     - Strip domain name from: dejar vacío
 *
 * Ejecución:
 *   1. Esperar a que Zabbix termine de inicializarse (~60s).
 *   2. docker exec songbird_web php /var/www/html/../scripts/configurar-zabbix.php
 *      (o copiar el script a /var/www/html/scripts/ y ejecutarlo dentro del
 *      contenedor `web`).
 *   3. Verificar en la UI de Zabbix que ambos usuarios existan.
 *
 * Credenciales bootstrap usadas para login inicial vía API:
 *   Usuario: Admin
 *   Password: zabbix  (default de la imagen oficial; CAMBIAR en prod)
 *
 * Después de la primera ejecución cambiar la contraseña del Admin
 * en Users → Admin → Change password.
 */

declare(strict_types=1);

const ZABBIX_API_URL = 'http://zabbix-web:8080/api_jsonrpc.php';
const BOOTSTRAP_TIMEOUT = 120; // segundos esperando a que Zabbix arranque

$BOOTSTRAP_USER = 'Admin';
$BOOTSTRAP_PASS = 'zabbix';

/**
 * Espera activa hasta que Zabbix responda en api_jsonrpc.php.
 */
function esperarZabbixListo(int $timeoutSegundos): void
{
    $inicio = time();
    while (time() - $inicio < $timeoutSegundos) {
        $ch = curl_init(ZABBIX_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['jsonrpc' => '2.0', 'method' => 'apiinfo.version', 'id' => 1]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 2,
        ]);
        $respuesta = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $respuesta !== false) {
            echo "[OK] Zabbix responde en $httpCode.\n";
            return;
        }
        sleep(2);
    }
    throw new RuntimeException("Zabbix no respondió en {$timeoutSegundos}s.");
}

/**
 * Llama a la API JSON-RPC de Zabbix.
 *
 * @param array<string,mixed> $params
 * @return array<string,mixed>
 */
function zabbixApi(string $token, string $metodo, array $params = []): array
{
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method' => $metodo,
        'params' => $params,
        'id' => 1,
    ]);

    $ch = curl_init(ZABBIX_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $respuesta = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $respuesta, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Respuesta no-JSON de Zabbix (HTTP $httpCode): $respuesta");
    }
    if (isset($decoded['error'])) {
        throw new RuntimeException("Error Zabbix en $metodo: " . json_encode($decoded['error']));
    }
    return $decoded['result'] ?? [];
}

function zabbixLogin(string $user, string $pass): string
{
    $ch = curl_init(ZABBIX_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'jsonrpc' => '2.0',
            'method' => 'user.login',
            'params' => ['username' => $user, 'password' => $pass],
            'id' => 1,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $respuesta = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode((string) $respuesta, true);
    if (!is_array($decoded) || isset($decoded['error'])) {
        throw new RuntimeException('Login Zabbix falló: ' . ($respuesta ?: 'sin respuesta'));
    }
    return (string) $decoded['result'];
}

/**
 * Busca un usuario por nombre. Retorna userid o null.
 */
function buscarUsuario(string $token, string $username): ?string
{
    $result = zabbixApi($token, 'user.get', [
        'output' => ['userid'],
        'filter' => ['username' => $username],
    ]);
    if (!empty($result[0]['userid'])) {
        return (string) $result[0]['userid'];
    }
    return null;
}

/**
 * Busca un grupo por nombre. Retorna usrgrpid o null.
 */
function buscarGrupo(string $token, string $nombre): ?string
{
    $result = zabbixApi($token, 'usergroup.get', [
        'output' => ['usrgrpid'],
        'filter' => ['name' => $nombre],
    ]);
    if (!empty($result[0]['usrgrpid'])) {
        return (string) $result[0]['usrgrpid'];
    }
    return null;
}

function crearGrupo(string $token, string $nombre): string
{
    $result = zabbixApi($token, 'usergroup.create', [
        'name' => $nombre,
    ]);
    if (empty($result['usrgrpid'])) {
        throw new RuntimeException("No se pudo crear grupo $nombre: " . json_encode($result));
    }
    echo "[OK] Grupo '$nombre' creado (usrgrpid={$result['usrgrpid']}).\n";
    return (string) $result['usrgrpid'];
}

/**
 * Crea un usuario en Zabbix. La contraseña es el mismo email (login único).
 */
function crearUsuario(string $token, array $params): string
{
    $result = zabbixApi($token, 'user.create', $params);
    if (empty($result['userids'][0])) {
        throw new RuntimeException('No se pudo crear usuario: ' . json_encode($params));
    }
    echo "[OK] Usuario '{$params['username']}' creado (userid={$result['userids'][0]}).\n";
    return (string) $result['userids'][0];
}

// ==========================================
// FLUJO PRINCIPAL
// ==========================================

echo "==> Provisionador de Zabbix para SSO\n";
echo "==> Esperando a que Zabbix esté disponible...\n";
esperarZabbixListo(BOOTSTRAP_TIMEOUT);

echo "==> Login bootstrap ({$BOOTSTRAP_USER})...\n";
$token = zabbixLogin($BOOTSTRAP_USER, $BOOTSTRAP_PASS);
echo "[OK] Token obtenido.\n";

echo "==> Configurando HTTP Authentication en Zabbix...\n";
// Solo pasamos parámetros soportados por Zabbix 7.0.
// El "case sensitive" login se setea por separado.
try {
    zabbixApi($token, 'authentication.update', [
        'http_auth_enabled' => 1,
        'http_case_sensitive' => 0,
    ]);
    echo "[OK] HTTP Authentication habilitada.\n";
} catch (RuntimeException $e) {
    echo "[WARN] No se pudo actualizar authentication.update automáticamente: {$e->getMessage()}\n";
    echo "       Configurar manualmente en Zabbix UI: Users → Authentication → HTTP settings.\n";
}

// ==========================================
// Usuarios
// ==========================================

echo "==> Provisionando usuarios...\n";

// Usuarios en Zabbix con username corto (sin email) por compatibilidad
// con el parser `CADNameAttributeParser` de Zabbix 7.0 HTTP Auth.
// El PHP sigue identificando al usuario por su email completo, pero
// al enviar REMOTE_USER sólo enviamos la parte local (antes del @).

// admin — Super admin
$adminGrupoId = buscarGrupo($token, 'Zabbix administrators');
if ($adminGrupoId === null) {
    throw new RuntimeException('Grupo Zabbix administrators no existe. La instalación está corrupta.');
}

$adminUserId = buscarUsuario($token, 'admin');
if ($adminUserId === null) {
    crearUsuario($token, [
        'username' => 'admin',
        'passwd' => 'ZbbxDemo2026!',
        'name' => 'Administrador Demo',
        'surname' => 'PHP',
        'roleid' => '3', // Super admin role
        'usrgrps' => [['usrgrpid' => $adminGrupoId]],
    ]);
} else {
    echo "[SKIP] Usuario 'admin' ya existe (userid=$adminUserId).\n";
}

// tecnico — User con permisos de lectura
$tecnicoGrupoId = buscarGrupo($token, 'Tecnicos');
if ($tecnicoGrupoId === null) {
    $tecnicoGrupoId = crearGrupo($token, 'Tecnicos');
}

$tecnicoUserId = buscarUsuario($token, 'tecnico');
if ($tecnicoUserId === null) {
    crearUsuario($token, [
        'username' => 'tecnico',
        'passwd' => 'ZbbxTecno2026!',
        'name' => 'Técnico',
        'surname' => 'Demo',
        'roleid' => '1', // User (lectura)
        'usrgrps' => [['usrgrpid' => $tecnicoGrupoId]],
    ]);
} else {
    echo "[SKIP] Usuario 'tecnico' ya existe (userid=$tecnicoUserId).\n";
}

echo "==> Listo. Probá SSO desde {$BOOTSTRAP_USER}.\n";
