<?php
/**
 * API: Obtener descuento disponible del usuario
 * GET /api/discounts/get-user-discount.php?series_slug=serie-0
 */

$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

$user_id     = getUserId();
$series_slug = sanitizeInput($_GET['series_slug'] ?? '');
$is_cart = (empty($series_slug) || $series_slug === '*');
if (!$is_cart && !$series_slug) {
    jsonError('series_slug requerido', 400);
}

// ── BONO QR: si el usuario entró por QR devolver su descuento ──
if (!empty($_SESSION['qr_bono']) && !empty($_SESSION['is_guest_bono'])) {
    $bono = $_SESSION['qr_bono'];

    // Verificar que el bono no ha expirado (24h)
    $canjeadoAt = strtotime($bono['canjeado_at'] ?? '');
    if (!$canjeadoAt || (time() - $canjeadoAt) >= 86400) {
        unset($_SESSION['qr_bono'], $_SESSION['is_guest_bono']);
        jsonSuccess('Sin descuento disponible', ['discount' => null]);
    }

    // Verificar que aplica a esta serie
    if (!empty($bono['series_slug']) && $bono['series_slug'] !== $series_slug) {
        jsonSuccess('Sin descuento disponible', ['discount' => null]);
    }

    // Calcular ahorro
    $series = queryOne("SELECT price FROM series WHERE slug = :slug", ['slug' => $series_slug]);
    $base_price = $series ? (float)$series['price'] : 0;

    $saving = 0;
    if ($bono['discount_type'] === 'percent') {
        $saving = round($base_price * ($bono['discount_value'] / 100), 2);
    } else {
        $saving = min((float)$bono['discount_value'], $base_price);
    }

    $label = $bono['discount_type'] === 'percent'
        ? '-' . (int)$bono['discount_value'] . '%'
        : '-€' . number_format($bono['discount_value'], 2);

    jsonSuccess('Descuento disponible', [
        'discount' => [
            'id'               => 0,
            'user_discount_id' => 0,
            'code'             => $bono['code'],
            'description'      => $bono['descripcion'] ?? 'Descuento bono QR',
            'type'             => $bono['discount_type'],
            'value'            => (float)$bono['discount_value'],
            'applies_to'       => $bono['applies_to'],
            'saving'           => $saving,
            'label'            => $label,
            'is_qr_bono'       => true,
        ]
    ]);
}

// ── DESCUENTO NORMAL: buscar en user_discounts ─────────────────
try {
    $series_filter = $is_cart
        ? "AND (d.series_slug IS NULL)"
        : "AND (d.series_slug IS NULL OR d.series_slug = :series_slug)";

    $params = $is_cart
        ? ['user_id' => $user_id]
        : ['user_id' => $user_id, 'series_slug' => $series_slug];

    $user_discount = queryOne(
        "SELECT 
            d.id, d.code, d.description, d.type, d.value, d.applies_to,
            d.series_slug AS discount_series,
            ud.id AS user_discount_id, ud.used_at
         FROM user_discounts ud
         INNER JOIN discounts d ON d.id = ud.discount_id
         WHERE ud.user_id    = :user_id
           AND ud.used_at    IS NULL
           AND d.is_active   = 1
           $series_filter
           AND (d.valid_until IS NULL OR d.valid_until >= NOW())
           AND (d.max_uses IS NULL OR d.used_count < d.max_uses)
         ORDER BY d.value DESC
         LIMIT 1",
        $params
    );

    if (!$user_discount) {
        jsonSuccess('Sin descuento disponible', ['discount' => null]);
    }

    $series = queryOne("SELECT price FROM series WHERE slug = :slug", ['slug' => $series_slug]);
    $base_price = $series ? (float)$series['price'] : 0;

    $saving = 0;
    if ($user_discount['type'] === 'percent') {
        $saving = round($base_price * ($user_discount['value'] / 100), 2);
    } else {
        $saving = min((float)$user_discount['value'], $base_price);
    }

    jsonSuccess('Descuento disponible', [
        'discount' => [
            'id'               => (int)$user_discount['id'],
            'user_discount_id' => (int)$user_discount['user_discount_id'],
            'code'             => $user_discount['code'],
            'description'      => $user_discount['description'],
            'type'             => $user_discount['type'],
            'value'            => (float)$user_discount['value'],
            'applies_to'       => $user_discount['applies_to'],
            'saving'           => $saving,
            'label'            => $user_discount['type'] === 'percent'
                ? '-' . (int)$user_discount['value'] . '%'
                : '-€' . number_format($user_discount['value'], 2),
            'is_qr_bono'       => false,
        ]
    ]);

} catch (Exception $e) {
    logError('get-user-discount error', ['error' => $e->getMessage(), 'user' => $user_id]);
    jsonError('Error al obtener descuento', 500);
}
