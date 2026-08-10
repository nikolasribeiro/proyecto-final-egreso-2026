<?php

/**
 * Tests de humo para `ModeloUsuario::autenticar()` — issue #114.
 *
 * Verifica:
 *  - Login OK por CI
 *  - Login OK por email
 *  - Login con password incorrecta (mismo error genérico → null)
 *  - Login con usuario inexistente (mismo error genérico → null)
 *  - Login con identifier vacío / password vacía
 *  - Login con usuario inactivo (rechazado)
 *  - Login no crea filas espurias (regresión del helper viejo
 *    `asegurarUsuario()` que inventaba "Auto Seed" en cada INSERT).
 *
 * El test es self-contained: crea un usuario temporal con credenciales
 * conocidas, ejecuta los casos, y borra el usuario. No depende del seed.
 *
 * Estilo: igual a tests/test_integracion.php (script PHP plano, sin
 * PHPUnit). Ejecutar con:
 *
 *   docker exec -it songbird_web php tests/test_auth.php
 */

require_once __DIR__ . '/../src/Nucleo/Conexion.php';
require_once __DIR__ . '/../src/Modelos/ModeloUsuario.php';

use Modelos\ModeloUsuario;
use Nucleo\Conexion;

$CI_TEST   = 99000001;
$EMAIL_TEST = 'auth_test_99000001@hospi.uy';
$PASS_TEST  = 'TestPass#114';

echo "=== TESTS DE AUTH (#114) ===\n\n";

$pass = 0;
$fail = 0;

function assert_true(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "[OK]    {$label}\n";
        $pass++;
    } else {
        echo "[FAIL]  {$label}\n";
        $fail++;
    }
}

$db = Conexion::obtenerInstancia();
$usuariosAntes = (int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

// --- Setup: crear usuario temporal con credenciales conocidas.
$hash = password_hash($PASS_TEST, PASSWORD_BCRYPT);
$db->prepare(
    'INSERT INTO usuarios (ci, nombre, apellido, email, contrasena, activo)
     VALUES (:ci, :n, :a, :e, :p, TRUE)'
)->execute([
    'ci' => $CI_TEST,
    'n'  => 'Auth',
    'a'  => 'Test',
    'e'  => $EMAIL_TEST,
    'p'  => $hash,
]);
$userId = (int)$db->lastInsertId();

// Asegurar rol "usuario" mínimo para que obtenerRolesUiPorUsuario no falle.
$rolId = (int)$db->query("SELECT id FROM roles WHERE tipo_rol = 'ADMINISTRATIVO' LIMIT 1")->fetchColumn();
if ($rolId > 0) {
    $db->prepare('INSERT IGNORE INTO usuario_roles (id_usuario, id_rol) VALUES (:u, :r)')
       ->execute(['u' => $userId, 'r' => $rolId]);
}

$modelo = new ModeloUsuario();

// 1. Login OK por CI.
$u = $modelo->autenticar((string)$CI_TEST, $PASS_TEST);
assert_true('login OK por CI devuelve array', is_array($u));
if (is_array($u)) {
    assert_true('login OK trae id numérico', isset($u['id']) && is_int($u['id']));
    assert_true('login OK trae ci correcto', isset($u['ci']) && (int)$u['ci'] === $CI_TEST);
    assert_true('login OK trae email correcto', ($u['email'] ?? '') === $EMAIL_TEST);
    assert_true('login OK trae roles[]', isset($u['roles']) && is_array($u['roles']));
    assert_true('login OK tiene rol administrador', in_array('administrador', $u['roles'], true));
    assert_true('login OK NO expone contrasena', !array_key_exists('contrasena', $u));
}

// 2. Login OK por email.
$u2 = $modelo->autenticar($EMAIL_TEST, $PASS_TEST);
assert_true('login OK por email devuelve array', is_array($u2));
if (is_array($u2)) {
    assert_true('login OK por email mismo id que por CI', (int)$u2['id'] === $userId);
}

// 3. Password incorrecta.
$f1 = $modelo->autenticar((string)$CI_TEST, 'wrong_password');
assert_true('password incorrecta devuelve null', $f1 === null);

// 4. Usuario inexistente.
$f2 = $modelo->autenticar('99999999', 'whatever');
assert_true('usuario inexistente devuelve null', $f2 === null);

// 5. Identifier vacío.
$f3 = $modelo->autenticar('', $PASS_TEST);
assert_true('identifier vacío devuelve null', $f3 === null);

// 6. Password vacía.
$f4 = $modelo->autenticar((string)$CI_TEST, '');
assert_true('password vacía devuelve null', $f4 === null);

// 7. Usuario inactivo: se rechaza aunque la contraseña sea correcta.
$db->prepare('UPDATE usuarios SET activo = FALSE WHERE id = :id')
   ->execute(['id' => $userId]);
$f5 = $modelo->autenticar((string)$CI_TEST, $PASS_TEST);
assert_true('usuario inactivo es rechazado', $f5 === null);
// Reactivar para cleanup coherente.
$db->prepare('UPDATE usuarios SET activo = TRUE WHERE id = :id')
   ->execute(['id' => $userId]);

// 8. password_needs_rehash no rompe cuando el hash ya está bien.
$f6 = $modelo->autenticar((string)$CI_TEST, $PASS_TEST);
assert_true('login sigue funcionando tras actualizar hash', is_array($f6));

// --- Cleanup: borrar usuario temporal.
$db->prepare('DELETE FROM usuario_roles WHERE id_usuario = :id')
   ->execute(['id' => $userId]);
$db->prepare('DELETE FROM usuarios WHERE id = :id')
   ->execute(['id' => $userId]);

$usuariosDespues = (int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
assert_true(
    "no quedan filas espurias ({$usuariosAntes} → {$usuariosDespues})",
    $usuariosAntes === $usuariosDespues
);

echo "\n";
echo "Pasaron:  {$pass}\n";
echo "Fallaron: {$fail}\n";
echo ($fail === 0)
    ? "✔ TODOS LOS TESTS DE AUTH PASARON.\n"
    : "✖ HAY TESTS DE AUTH FALLANDO.\n";

/*
docker exec -it songbird_web php tests/test_auth.php
*/