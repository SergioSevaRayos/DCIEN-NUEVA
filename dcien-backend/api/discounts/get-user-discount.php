<?php
/**
 * API: Obtener descuentos disponibles del usuario
 * GET /api/discounts/get-user-discount.php?series_slug=serie-01
 *
 * Responde con:
 *   discounts  → array de todos los descuentos stackables disponibles
 *   discount   → el mejor descuento único (compat. QR bono y flujo antiguo)
 */

$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/includes/discount-helpers.php';

startSecureSession();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

$user_id     = getUserId();
$series_slug = sanitizeInput($_GET['series_slug'] ?? '');
$is_cart     = (empty($series_slug) || $series_slug === '*');

// ── BONO QR ────────────────────────────────────────────────────────────────
if (!empty($_SESSION['qr_bono']) && !empty($_SESSION['is_guest_bono'])) {
    $bono = $_SESSION['qr_bono'];

    $canjeadoAt = strtotime($bono['canjeado_at'] ?? '');
    if (!$canjeadoAt || (time() - $canjeadoAt) >= 86400) {
        unset($_SESSION['qr_bono'], $_SESSION['is_guest_bono']);
        jsonSuccess('Sin descuento disponible', ['discount' => null, 'discounts' => []]);
    }

    if (!empty($bono['series_slug']) && $bono['series_slug'] !== $series_slug) {
        jsonSuccess('Sin descuento disponible', ['discount' => null, 'discounts' => []]);
    }

    $series     = queryOne("SELECT price FROM series WHERE slug = :slug", ['slug' => $series_slug]);
    $base_price = $series ? (float)$series['price'] : 0;
    $saving     = $bono['discount_type'] === 'percent'
        ? round($base_price * ($bono['discount_value'] / 100), 2)
        : min((float)$bono['discount_value'], $base_price);

    $label = $bono['discount_type'] === 'percent'
        ? '-' . (int)$bono['discount_value'] . '%'
        : '-€' . number_format($bono['discount_value'], 2);

    $qr_discount = [
        'id'               => 0,
        'user_discount_id' => 0,
        'code'             => $bono['code'],
        'description'      => $bono['descripcion'] ?? 'Descuento bono QR',
        'type'             => $bono['discount_type'],
        'value'            => (float)$bono['discount_value'],
        'applies_to'       => $bono['applies_to'],
        'is_stackable'     => 0,
        'saving'           => $saving,
        'label'            => $label,
        'is_qr_bono'       => true,
    ];

    jsonSuccess('Descuento disponible', [
        'discount'  => $qr_discount,
        'discounts' => [$qr_discount],
    ]);
}

// ── DESCUENTOS NORMALES ────────────────────────────────────────────────────
try {
    $groups = getUserDiscounts($user_id, $series_slug);

    $stackable  = $groups['stackable'];
    $standalone = $groups['standalone'];

    if (empty($stackable) && empty($standalone)) {
        jsonSuccess('Sin descuento disponible', ['discount' => null, 'discounts' => []]);
    }

    // Precio base para calcular ahorro (solo para items individuales)
    $base_price = 0;
    if (!$is_cart && $series_slug) {
        $series     = queryOne("SELECT price FROM series WHERE slug = :slug", ['slug' => $series_slug]);
        $base_price = $series ? (float)$series['price'] : 0;
    }

    $formatDiscount = function(array $d) use ($base_price, $is_cart): array {
        $saving = 0;
        if (!$is_cart && $base_price > 0) {
            $saving = $d['type'] === 'percent'
                ? round($base_price * ((float)$d['value'] / 100), 2)
                : min((float)$d['value'], $base_price);
        }
        return [
            'id'               => (int)$d['id'],
            'user_discount_id' => (int)$d['user_discount_id'],
            'code'             => $d['code'],
            'description'      => $d['description'],
            'type'             => $d['type'],
            'value'            => (float)$d['value'],
            'applies_to'       => $d['applies_to'],
            'is_stackable'     => (int)$d['is_stackable'],
            'saving'           => $saving,
            'label'            => $d['type'] === 'percent'
                ? '-' . (int)$d['value'] . '%'
                : '-€' . number_format($d['value'], 2),
            'is_qr_bono'       => false,
        ];
    };

    $formattedStackable  = array_map($formatDiscount, $stackable);
    $formattedStandalone = array_map($formatDiscount, $standalone);

    // discounts = stackables + mejor standalone (si no hay stackables)
    $activeDiscounts = !empty($formattedStackable)
        ? $formattedStackable
        : [$formattedStandalone[0]];

    // discount = compat. con flujo antiguo (mejor disponible)
    $primaryDiscount = $formattedStackable[0] ?? $formattedStandalone[0] ?? null;

    jsonSuccess('Descuentos disponibles', [
        'discount'  => $primaryDiscount,
        'discounts' => $activeDiscounts,
    ]);

} catch (Exception $e) {
    logError('get-user-discount error', ['error' => $e->getMessage(), 'user' => $user_id]);
    jsonError('Error al obtener descuento', 500);
}
