<?php
// api/auth/reset-password.php
require_once '../../bootstrap.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$newPassword = $input['password'] ?? '';

if (empty($token) || strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos o contraseña demasiado corta.']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // 1. Buscar usuario con token válido y no expirado
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE reset_token = ? 
        AND reset_expires_at > NOW() 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'El enlace ha expirado o es inválido.']);
        exit;
    }

    // 2. Actualizar contraseña y limpiar el token
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $update = $pdo->prepare("
        UPDATE users 
        SET password_hash = ?, 
            reset_token = NULL, 
            reset_expires_at = NULL 
        WHERE id = ?
    ");
    $update->execute([$newHash, $user['id']]);

    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}