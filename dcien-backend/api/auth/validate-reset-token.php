<?php
// api/auth/validate-reset-token.php

// Esto calcula la raíz de dcien-backend de forma absoluta
$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();

$token = $_GET['token'] ?? '';

if (!$token) {
    header('Location: https://d-cien.es/acceso?error=token_missing');
    exit;
}

try {
    // Usamos la función queryOne que ya tienes en tus helpers
    $user = queryOne(
        "SELECT id, username, email FROM users WHERE reset_token = :token AND reset_expires_at > NOW() LIMIT 1",
        ['token' => $token]
    );

    if ($user) {
        // Preparamos la sesión para que /registro/completar sea válido
        $_SESSION['activation_token'] = $token; 
        $_SESSION['is_recovery'] = true; 
        $_SESSION['temp_user_id'] = $user['id'];
        $_SESSION['instagram_username'] = $user['username'] ?? 'Usuario';

        // Redirigimos a la página de Astro
        header('Location: https://d-cien.es/registro/completar');
    } else {
        header('Location: https://d-cien.es/acceso?error=token_invalid_or_expired');
    }
} catch (Exception $e) {
    logError('VALIDATE_TOKEN_ERROR', ['error' => $e->getMessage()]);
    header('Location: https://d-cien.es/acceso?error=server_error');
}
exit;