<?php
/**
 * API: Captura de email para desbloquear herramientas de ARSENAL
 * POST /api/arsenal/subscribe-lead.php  { "email": "...", "tool_slug": "general" }
 * Sin sesión — captura ligera sin cuenta.
 */
$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$toolSlug = trim($input['tool_slug'] ?? '') ?: 'general';

if (empty($email) || !validateEmail($email)) {
    jsonError('El email no es válido', 400);
}

if (!preg_match('/^[a-z0-9\-]{1,100}$/', $toolSlug)) {
    $toolSlug = 'general';
}

$email = sanitizeInput($email);

try {
    query(
        "INSERT INTO arsenal_leads (email, tool_slug) VALUES (:email, :tool_slug)
         ON DUPLICATE KEY UPDATE created_at = NOW()",
        ['email' => $email, 'tool_slug' => $toolSlug]
    );
    jsonSuccess('Acceso desbloqueado', ['tool_slug' => $toolSlug]);
} catch (Exception $e) {
    logError('Error guardando lead de arsenal', ['error' => $e->getMessage()]);
    jsonError('Error del servidor', 500);
}
