<?php
/**
 * API: Verificar Sesión
 */
$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/config/database.php';

startSecureSession();

if (isAuthenticated()) {
    $userId = getUserId();
    $userData = queryOne(
        "SELECT can_purchase FROM users WHERE id = :id",
        ['id' => $userId]
    );
    $canPurchase = $userData && intval($userData['can_purchase']) === 1;

    jsonResponse([
        'authenticated'  => true,
        'can_purchase'   => $canPurchase,
        'user' => [
            'id'                 => $userId,
            'email'              => getUserEmail(),
            'instagram_username' => $_SESSION['instagram_username'] ?? null
        ]
    ], 200);

} elseif (!empty($_SESSION['qr_bono'])) {
    // Acceso temporal por bono QR
    $bono = $_SESSION['qr_bono'];

    // Verificar que el bono no ha expirado en sesión (24h)
    $canjeadoAt = strtotime($bono['canjeado_at'] ?? '');
    $valido = $canjeadoAt && (time() - $canjeadoAt) < 86400;

    if ($valido) {
        jsonResponse([
            'authenticated' => true,
            'can_purchase'  => true,
            'guest_bono'    => true,
            'bono' => [
                'code'           => $bono['code'],
                'discount_type'  => $bono['discount_type'],
                'discount_value' => $bono['discount_value'],
                'applies_to'     => $bono['applies_to'],
                'series_slug'    => $bono['series_slug'],
            ]
        ], 200);
    } else {
        // Bono expirado, limpiar sesión
        unset($_SESSION['qr_bono'], $_SESSION['guest_bono_access']);
        jsonResponse(['authenticated' => false, 'can_purchase' => false], 200);
    }

} else {
    jsonResponse([
        'authenticated' => false,
        'can_purchase'  => false
    ], 200);
}
