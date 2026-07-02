<?php
/**
 * API: Canjear Bono QR
 * GET /dcien-backend/api/bonos/canjear.php?code=XXXX
 */

$backend_root = dirname(dirname(dirname(__DIR__)));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

header('Content-Type: application/json');

startSecureSession();

$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
$code = strtoupper(preg_replace('/[^A-Z0-9\-_]/', '', strtoupper($code)));

if (empty($code)) {
    jsonError('Código de bono requerido', 400);
}

try {
    $pdo = getDatabaseConnection();

    $bono = queryOne(
        "SELECT * FROM qr_bonos WHERE code = :code AND is_active = 1",
        ['code' => $code]
    );

    if (!$bono) {
        jsonError('Bono no válido o inactivo', 404);
    }

    $now = time();
    if ($bono['valid_from'] && strtotime($bono['valid_from']) > $now) {
        jsonError('Este bono todavía no está activo', 403);
    }
    if ($bono['valid_until'] && strtotime($bono['valid_until']) < $now) {
        jsonError('Este bono ha caducado', 410);
    }
    if ($bono['max_uses'] !== null && $bono['used_count'] >= $bono['max_uses']) {
        jsonError('Este bono ha alcanzado el límite de usos', 410);
    }

    $pdo->beginTransaction();

    query(
        "INSERT INTO qr_bonos_usos (bono_id, bono_code, ip, user_agent, session_id, user_id, canjeado_at)
         VALUES (:bid, :code, :ip, :ua, :sid, :uid, NOW())",
        [
            'bid'  => $bono['id'],
            'code' => $bono['code'],
            'ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'sid'  => session_id(),
            'uid'  => $_SESSION['user_id'] ?? null,
        ]
    );

    query(
        "UPDATE qr_bonos SET used_count = used_count + 1 WHERE id = :id",
        ['id' => $bono['id']]
    );

    $_SESSION['qr_bono'] = [
        'code'           => $bono['code'],
        'discount_type'  => $bono['discount_type'],
        'discount_value' => (float)$bono['discount_value'],
        'applies_to'     => $bono['applies_to'],
        'series_slug'    => $bono['series_slug'],
        'descripcion'    => $bono['descripcion'],
        'canjeado_at'    => date('c'),
    ];

    $pdo->commit();

    $valor_texto = $bono['discount_type'] === 'percent'
        ? number_format($bono['discount_value'], 0) . '%'
        : '€' . number_format($bono['discount_value'], 2);

    // Credenciales de activación asociadas a este bono (creadas junto al bono en el panel admin)
    $temp_username = !isAuthenticated() ? 'BONO_' . $bono['code'] : null;
    $temp_password = !isAuthenticated() ? ($bono['temp_password'] ?? null) : null;

    jsonSuccess('Bono canjeado correctamente', [
        'bono' => [
            'code'           => $bono['code'],
            'nombre'         => $bono['nombre'],
            'descripcion'    => $bono['descripcion'],
            'discount_type'  => $bono['discount_type'],
            'discount_value' => (float)$bono['discount_value'],
            'valor_texto'    => $valor_texto,
            'series_slug'    => $bono['series_slug'],
            'valid_until'    => $bono['valid_until'],
        ],
        'acceso_temporal' => !isAuthenticated(),
        'temp_username'   => $temp_username,
        'temp_password'   => $temp_password,
        'redirect_to'     => ($temp_password)
            ? '/registro/activar?from=bono'
            : ($bono['series_slug']
                ? '/series-activas/' . $bono['series_slug'] . '/'
                : '/series-activas/'),
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    logError('Error canjeando bono QR', ['code' => $code, 'error' => $e->getMessage()]);
    jsonError('Error interno al procesar el bono', 500);
}
