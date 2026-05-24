<?php
/**
 * API: Comprobar disponibilidad de nombre de usuario
 */

$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$input = getJsonInput();
$username = trim($input['username'] ?? '');

logError('CHECK USERNAME → INPUT', ['raw' => $input, 'parsed' => $username]);

if ($username === '') {
    jsonResponse(['available' => false, 'message' => 'Username requerido'], 200);
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
    logError('CHECK USERNAME → FORMATO INVALIDO', ['username' => $username]);
    jsonResponse(['available' => false, 'message' => 'Formato inválido'], 200);
}

try {
    $existing = queryOne(
        "SELECT id FROM users WHERE username = BINARY :u LIMIT 1",
        ['u' => $username]
    );

    logError('CHECK USERNAME → QUERY RESULT', [
        'username' => $username,
        'exists'   => (bool)$existing
    ]);

    jsonResponse([
        'available' => $existing === null,
        'username'  => $username
    ], 200);

} catch (Throwable $e) {
    logError('CHECK USERNAME → DB ERROR', [
        'username' => $username,
        'error'    => $e->getMessage()
    ]);

    jsonResponse([
        'available' => false,
        'message'   => 'Error interno al verificar'
    ], 200);
}
