<?php
/**
 * API: Validar token de activación temporal
 */

$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$input = getJsonInput();
validateRequired($input, ['username', 'password']);

/**
 * SANEAMIENTO MEJORADO
 * Usamos trim() y un filtrado que respeta guiones bajos para el nombre temporal
 */
$username = isset($input['username']) ? trim($input['username']) : '';
$username = preg_replace('/[^a-zA-Z0-9._\-]/', '', $username);
$password = $input['password'];

try {
    // Buscar token temporal activo
    $token = queryOne(
        "SELECT * FROM activation_tokens 
         WHERE temp_username = :username 
         AND expires_at > NOW() 
         AND used_at IS NULL 
         LIMIT 1",
        ['username' => $username]
    );
    
    if (!$token) {
        // Log para debug interno (puedes quitarlo después)
        logError('Intento de activación fallido: usuario no encontrado o token expirado', ['username' => $username]);
        jsonError('Credenciales temporales inválidas o expiradas', 401);
    }
    
    // Verificar contraseña temporal
    if (!password_verify($password, $token['temp_password_hash'])) {
        jsonError('Credenciales temporales incorrectas', 401);
    }
    
    // Guardar datos en sesión para el paso final
    // Estos datos son cruciales para que complete-registration.php y check-username.php funcionen
    $_SESSION['activation_token'] = $token['token'];
    $_SESSION['instagram_username'] = $token['instagram_username'];
    
    jsonSuccess('Token válido', [
        'instagram_username' => $token['instagram_username'],
        'next_step' => '/registro/completar'
    ]);
    
} catch (Exception $e) {
    logError('Error validating token', ['error' => $e->getMessage()]);
    jsonError('Error del servidor', 500);
}