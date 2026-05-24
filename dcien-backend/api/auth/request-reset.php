<?php
/**
 * API: Solicitar recuperación de contraseña
 */

// 1. Forzar que cualquier error de PHP no se muestre como HTML
ini_set('display_errors', 0); 
error_reporting(E_ALL);
header('Content-Type: application/json');

$backend_root = dirname(dirname(__DIR__));

try {
    require_once $backend_root . '/config/database.php';
    require_once $backend_root . '/includes/cors.php';
    require_once $backend_root . '/includes/helpers.php';
    require_once $backend_root . '/includes/mailer.php';

    $input = getJsonInput();
    $email = sanitizeInput($input['email'] ?? '');

    if (!validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Email no válido']);
        exit;
    }

    // Buscar si el usuario existe
    $user = queryOne("SELECT id, username FROM users WHERE email = :email LIMIT 1", ['email' => $email]);

    if ($user) {
        // Generar token único
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Guardar token en el usuario
        query(
            "UPDATE users SET reset_token = :token, reset_expires_at = :expires WHERE id = :id",
            [
                'token' => $token,
                'expires' => $expires,
                'id' => $user['id']
            ]
        );

        // Configurar el enlace (apunta al puente PHP que creamos antes)
        $resetLink = "https://d-cien.es/api/auth/validate-reset-token.php?token=" . $token;

        // Intentar enviar email
        // IMPORTANTE: Asegúrate de tener un caso 'password_reset' en tu getEmailContent o usa uno genérico
        $htmlContent = "
            <div style='font-family: sans-serif; padding: 20px;'>
                <h2>Recuperación de cuenta DCIEN</h2>
                <p>Hola, <b>{$user['username']}</b>.</p>
                <p>Has solicitado restablecer tus credenciales. Pulsa el siguiente botón:</p>
                <a href='{$resetLink}' style='background: #000; color: #fff; padding: 10px 20px; text-decoration: none; display: inline-block;'>
                    Restablecer mi Cuenta
                </a>
                <p style='margin-top: 20px; font-size: 12px; color: #666;'>Este enlace expirará en 1 hora.</p>
            </div>
        ";

        sendEmail([
            'to' => $email,
            'subject' => 'Restablecer acceso - DCIEN',
            'html' => $htmlContent
        ]);
    }

    // Siempre devolvemos éxito por seguridad (para no dar pistas de qué emails existen)
    echo json_encode([
        'success' => true, 
        'message' => 'Si el email está registrado, recibirás un enlace de recuperación en unos minutos.'
    ]);

} catch (Throwable $e) {
    // Si algo falla, registramos el error en el log y devolvemos JSON limpio
    error_log("RESET_PASSWORD_ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor.'
    ]);
}