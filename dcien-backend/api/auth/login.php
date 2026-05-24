<?php
/**
 * API: Login de Usuario REAL
 * Acepta email o username definitivo
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

/**
 * SANEAMIENTO MEJORADO
 * Usamos un trim y limpieza manual para el identifier (email o username)
 * para asegurar que los caracteres como "_" o "." no se pierdan.
 */
$identifier = isset($input['identifier']) ? trim($input['identifier']) : '';
$identifier = strip_tags($identifier);
$password   = $input['password'] ?? '';

if (!$identifier || !$password) {
    jsonError('Credenciales incorrectas', 401);
}
try {
    // 1. Detectar si es email o username
    $isEmail = validateEmail($identifier);

    // 2. Consulta mejorada: 
    // Si es email, buscamos normal. 
    // Si es username, usamos LOWER() para que sea insensible a mayúsculas.
    if ($isEmail) {
        $user = queryOne(
            "SELECT * FROM users WHERE email = :id LIMIT 1",
            ['id' => $identifier]
        );
    } else {
        $user = queryOne(
            "SELECT * FROM users WHERE LOWER(username) = LOWER(:id) LIMIT 1",
            ['id' => $identifier]
        );
    }

    // 3. Verificar si el usuario existe y la contraseña es correcta
    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonError('Credenciales incorrectas', 401);
    }

    // 4. Verificar si la cuenta está activa
    if ((int)$user['is_verified'] !== 1) {
        jsonError('Usuario no verificado', 403);
    }

    // 5. LOGIN EXITOSO: Configurar la sesión
    $_SESSION['user_id']            = $user['id'];
    $_SESSION['email']              = $user['email'];
    $_SESSION['instagram_username'] = $user['instagram_username'];
    $_SESSION['is_authenticated']   = true;
    $_SESSION['login_time']         = time();

    // Seguridad: regenerar ID de sesión para evitar fijación
    session_regenerate_id(true);

    // Actualizar última actividad
    query(
        "UPDATE users SET updated_at = NOW() WHERE id = :id",
        ['id' => $user['id']]
    );

    jsonSuccess('Login exitoso', [
        'user' => [
            'id'                 => $user['id'],
            'email'              => $user['email'],
            'instagram_username' => $user['instagram_username'],
            'can_purchase'       => (bool)$user['can_purchase']
        ]
    ]);

} catch (Exception $e) {
    logError('Login error', ['error' => $e->getMessage()]);
    jsonError('Error del servidor', 500);
}