<?php
/**
 * API: Validar cupón de descuento introducido manualmente
 * POST /api/discounts/validate-discount.php
 * Body: { "code": "DCIEN10", "series_slug": "serie-0" }
 *
 * Valida si el código existe, está activo y aplica a la serie/usuario.
 * NO lo marca como usado (eso ocurre al completar el pago en el webhook).
 */

$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$input       = getJsonInput();
$code        = strtoupper(trim(sanitizeInput($input['code'] ?? '')));
$series_slug = sanitizeInput($input['series_slug'] ?? '');
$user_id     = getUserId();

if (!$code)        jsonError('Código de descuento requerido', 400);
if (!$series_slug) jsonError('series_slug requerido', 400);

try {
    // 1. Buscar el descuento por código
    $discount = queryOne(
        "SELECT * FROM discounts
         WHERE code       = :code
           AND is_active  = 1
           AND (series_slug IS NULL OR series_slug = :series_slug)
           AND (valid_from  IS NULL OR valid_from  <= NOW())
           AND (valid_until IS NULL OR valid_until >= NOW())
           AND (max_uses    IS NULL OR used_count  <  max_uses)",
        ['code' => $code, 'series_slug' => $series_slug]
    );

    if (!$discount) {
        jsonError('Cupón no válido o ya no disponible', 404);
    }

    // 2. Comprobar si el usuario ya lo usó
    $used = queryOne(
        "SELECT used_at FROM user_discounts
         WHERE user_id = :user_id AND discount_id = :discount_id",
        ['user_id' => $user_id, 'discount_id' => $discount['id']]
    );

    if ($used && $used['used_at'] !== null) {
        jsonError('Ya has utilizado este cupón anteriormente', 409);
    }

    // 3. Obtener precio base para calcular ahorro
    $series = queryOne("SELECT price FROM series WHERE slug = :slug", ['slug' => $series_slug]);
    $base_price = $series ? (float)$series['price'] : 0;

    $saving = 0;
    if ($discount['type'] === 'percent') {
        $saving = round($base_price * ($discount['value'] / 100), 2);
    } else {
        $saving = min((float)$discount['value'], $base_price);
    }

    jsonSuccess('Cupón válido', [
        'discount' => [
            'id'         => (int)$discount['id'],
            'code'       => $discount['code'],
            'description'=> $discount['description'],
            'type'       => $discount['type'],
            'value'      => (float)$discount['value'],
            'applies_to' => $discount['applies_to'],
            'saving'     => $saving,
            'label'      => $discount['type'] === 'percent'
                ? '-' . (int)$discount['value'] . '%'
                : '-€' . number_format($discount['value'], 2),
        ]
    ]);

} catch (Exception $e) {
    logError('validate-discount error', ['error' => $e->getMessage(), 'code' => $code]);
    jsonError('Error al validar cupón', 500);
}