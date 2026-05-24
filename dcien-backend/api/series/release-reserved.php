<?php
/**
 * API: Liberar unidad reservada al eliminar del carrito
 * POST /dcien-backend/api/series/release-reserved.php
 */

$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metodo no permitido', 405);
}

$input      = getJsonInput();
$seriesSlug = sanitizeInput($input['series_slug'] ?? '');
$unitNumber = (int)($input['unit_number'] ?? 0);
$userId     = getUserId();

if (!$seriesSlug || !$unitNumber) {
    jsonError('Datos incompletos', 400);
}

try {
    $result = query(
        "UPDATE series_units
         SET status='available', reserved_at=NULL, reserved_by=NULL, checkout_started_at=NULL
         WHERE series_slug=:slug AND unit_number=:num
           AND reserved_by=:uid
           AND status IN ('reserved','checkout')",
        ['slug' => $seriesSlug, 'num' => $unitNumber, 'uid' => $userId]
    );

    jsonSuccess('Unidad liberada', ['rows' => $result->rowCount()]);

} catch (Exception $e) {
    logError('release-reserved error', ['error' => $e->getMessage()]);
    jsonError('Error al liberar unidad', 500);
}
