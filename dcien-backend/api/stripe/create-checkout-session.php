<?php
/**
 * API: Crear sesión de Stripe Checkout
 * ACTUALIZADO: Opción Nuclear para pedidos a coste 0 (Bypass definitivo)
 */

$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/includes/discount-helpers.php';

startSecureSession();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

require_once $backend_root . '/vendor/autoload.php';

$input = getJsonInput();

// Datos básicos
$seriesSlug = sanitizeInput($input['seriesSlug'] ?? '');
$unitNumber = (int)($input['unitNumber'] ?? 0);
$size = sanitizeInput($input['size'] ?? '');
$color = sanitizeInput($input['color'] ?? '');
$type = sanitizeInput($input['type'] ?? '');
$shippingData = $input['shippingData'] ?? [];

$discountData  = $input['discount']  ?? null;   // compat. / QR bono
$discountsData = $input['discounts'] ?? null;   // nuevo: array stackable
$userId = getUserId();

// Validaciones básicas
if (!$seriesSlug || !$unitNumber || !$size || !$color || !$type) {
    jsonError('Datos incompletos', 400);
}

try {
    $pdo = getDatabaseConnection();

    // Obtener datos de la serie
    $series = queryOne(
        "SELECT price, name FROM series WHERE slug = :slug AND is_active = 1",
        ['slug' => $seriesSlug]
    );

    if (!$series) {
        jsonError('Serie no encontrada', 404);
    }

    $basePrice       = (float)$series['price'];
    $discountAmount  = 0.0;
    $discountId      = null;
    $discountIds     = [];
    $userDiscountIds = [];
    $discountCodes   = [];

    // Nuevo flujo: array de descuentos stackables
    if (!empty($discountsData) && is_array($discountsData)) {
        $ids = array_filter(array_map(fn($d) => (int)($d['id'] ?? 0), $discountsData));
        if (!empty($ids)) {
            $validDiscounts = validateMultipleDiscounts($userId, $ids);
            if (!empty($validDiscounts)) {
                $discountAmount  = calculateMultipleDiscountsAmount($basePrice, $validDiscounts);
                $discountId      = $validDiscounts[0]['discount_id'];
                $discountIds     = array_column($validDiscounts, 'discount_id');
                $userDiscountIds = array_column($validDiscounts, 'user_discount_id');
                $discountCodes   = array_column($validDiscounts, 'code');
            }
        }
    }
    // Flujo antiguo: descuento único
    elseif ($discountData && isset($discountData['id'])) {
        $validDiscount = validateUserDiscount($userId, $discountData['id']);
        if ($validDiscount) {
            $discountAmount    = calculateSavings($basePrice, $validDiscount);
            $discountId        = $validDiscount['discount_id'];
            $discountIds[]     = $validDiscount['discount_id'];
            $userDiscountIds[] = $validDiscount['user_discount_id'];
            $discountCodes[]   = $validDiscount['code'];
        }
    }

    $discountCode = implode('+', array_filter($discountCodes));
    $finalPrice   = max(0, round($basePrice - $discountAmount, 2));

    // ════════════════════════════════════════════════════════════════
    // CÁLCULOS DE IVA Y ENVÍO
    // ════════════════════════════════════════════════════════════════
    $shippingFee = 10.00;

    if ($finalPrice <= 0.10) {
        $finalPrice  = 0.00;
        $shippingFee = 0.00;
    }

    $priceWithTax = $finalPrice;
    $ivaAmount = round($priceWithTax - ($priceWithTax / 1.21), 2);
    
    // Si entró en las reglas anteriores, $grandTotal será matemáticamente 0.00
    $grandTotal = $priceWithTax + $shippingFee;

    // ════════════════════════════════════════════════════════════════
    // 1. INICIAR TRANSACCIÓN E INSERTAR PEDIDO
    // ════════════════════════════════════════════════════════════════
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO orders
        (user_id, series_slug, unit_number, size, color, type, price, discount_id, discount_ids, created_at)
        VALUES (:uid, :slug, :num, :size, :color, :type, :price, :did, :dids, NOW())");

    $stmt->execute([
        'uid'   => $userId,
        'slug'  => $seriesSlug,
        'num'   => $unitNumber,
        'size'  => $size,
        'color' => $color,
        'type'  => $type,
        'price' => round($grandTotal, 2),
        'did'   => $discountId,
        'dids'  => !empty($discountIds) ? json_encode($discountIds) : null,
    ]);

    $order_id = $pdo->lastInsertId();

    // Generar número de pedido: AAMMDD-(ID+15000)
    $orderNumber = date('ymd') . '-' . ($order_id + 15000);
    query("UPDATE orders SET order_number = :num WHERE id = :oid", [
        'num' => $orderNumber,
        'oid' => $order_id
    ]);

    // ════════════════════════════════════════════════════════════════
    // 2. BYPASS DE STRIPE PARA PEDIDOS A COSTE 0
    // ════════════════════════════════════════════════════════════════
    if ($grandTotal < 0.10) {
        $freeSessionId = 'FREE_ORDER_' . $order_id;

        query("UPDATE orders SET stripe_session_id = :sid, status = 'paid' WHERE id = :oid", [
            'sid' => $freeSessionId,
            'oid' => $order_id
        ]);

        query("UPDATE series_units SET status = 'sold', reserved_by = :uid WHERE series_slug = :slug AND unit_number = :num", [
            'uid' => $userId,
            'slug' => $seriesSlug,
            'num' => $unitNumber
        ]);

        $pdo->commit();

        // Enviar email de confirmación para pedidos gratuitos (Stripe no genera webhook)
        try {
            require_once $backend_root . '/includes/mailer.php';
            $dbUser   = queryOne("SELECT username, email FROM users WHERE id = :uid", ['uid' => $userId]);
            $emailTo  = $dbUser['email'] ?? ($shippingData['email'] ?? null);
            $username = $dbUser['username'] ?? ($shippingData['firstName'] ?? 'Cliente');

            if ($emailTo) {
                $s = $shippingData;
                $address_lines = implode('<br>', array_filter([
                    trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? '')),
                    trim(($s['address'] ?? '') . ($s['addressExtra'] ? ', ' . $s['addressExtra'] : '')),
                    trim(($s['postalCode'] ?? '') . ' ' . ($s['city'] ?? '') . (isset($s['province']) ? ', ' . $s['province'] : '')),
                    ($s['email'] ?? $emailTo) . (isset($s['phone']) ? ' | ' . $s['phone'] : ''),
                ]));

                $html = getEmailTemplate('order_confirmation', [
                    'username'         => htmlspecialchars($username),
                    'order_id'         => $orderNumber,
                    'email_items'      => [[
                        'series_slug' => $seriesSlug,
                        'unit_number' => $unitNumber,
                        'size'        => $size,
                        'color'       => $color,
                        'type'        => $type,
                    ]],
                    'shipping_address' => $address_lines,
                    'total'            => '0.00',
                    'discount_code'    => $discountCode ?? '',
                    'discount_amount'  => $discountAmount > 0 ? number_format($discountAmount, 2, '.', '') : '',
                    'original_price'   => $discountAmount > 0 ? '€' . number_format($basePrice, 2, '.', '') : '',
                ]);

                sendEmail([
                    'to'      => $emailTo,
                    'subject' => 'Pedido Confirmado #' . $orderNumber . ' — DCIEN',
                    'html'    => $html,
                ]);
            }
        } catch (Exception $emailEx) {
            logError('Free order email error', ['error' => $emailEx->getMessage()]);
        }

        $successUrl = ($_ENV['STRIPE_SUCCESS_URL'] ?? getenv('STRIPE_SUCCESS_URL')) . '?session_id=' . $freeSessionId;
        jsonSuccess('Pedido gratuito procesado correctamente', [
            'url' => $successUrl,
            'session_id' => $freeSessionId
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════
    // 3. BLOQUEAR UNIDAD TEMPORALMENTE (SI NO ES GRATIS)
    // ════════════════════════════════════════════════════════════════
    query(
        "UPDATE series_units
         SET status = 'checkout',
             checkout_started_at = NOW(),
             reserved_by = :uid
         WHERE series_slug = :slug AND unit_number = :num",
        ['uid' => $userId, 'slug' => $seriesSlug, 'num' => $unitNumber]
    );

    // ════════════════════════════════════════════════════════════════
    // 4. CREAR SESIÓN DE STRIPE (SOLO SI HAY QUE COBRAR > 0€)
    // ════════════════════════════════════════════════════════════════
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY'));

    $session = \Stripe\Checkout\Session::create([
        'customer_email' => $shippingData['email'] ?? null,
        'payment_method_types' => ['card'],
        'invoice_creation' => ['enabled' => true],
        'line_items' => [
            [
                'price_data' => [
                    'currency' => 'eur',
                    'tax_behavior' => 'inclusive',
                    'product_data' => [
                        'name' => $series['name'] . ' #' . str_pad($unitNumber, 3, '0', STR_PAD_LEFT),
                        'description' => sprintf(
                            'Talla: %s | Color: %s | Tipo: %s%s',
                            strtoupper($size), ucfirst($color), ucfirst($type),
                            $discountCode ? " | Descuento: $discountCode" : ""
                        ),
                    ],
                    'unit_amount' => (int)round($priceWithTax * 100),
                ],
                'quantity' => 1,
            ],
            [
                'price_data' => [
                    'currency' => 'eur',
                    'tax_behavior' => 'inclusive',
                    'product_data' => [
                        'name' => 'Gastos de Envío',
                    ],
                    'unit_amount' => (int)round($shippingFee * 100),
                ],
                'quantity' => 1,
            ]
        ],
        'mode' => 'payment',
        'success_url' => ($_ENV['STRIPE_SUCCESS_URL'] ?? getenv('STRIPE_SUCCESS_URL')) . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?? 'https://d-cien.es', '/') . '/api/stripe/cancel-checkout.php?session_id={CHECKOUT_SESSION_ID}',
        'metadata' => [
            'order_id'         => (string)$order_id,
            'user_id'          => (string)$userId,
            'series_slug'      => $seriesSlug,
            'unit_number'      => (string)$unitNumber,
            'size'             => $size,
            'color'            => $color,
            'type'             => $type,
            'shipping_json'    => json_encode($shippingData),
            'original_price'   => (string)$basePrice,
            'final_price'      => (string)$priceWithTax,
            'shipping_fee'     => (string)$shippingFee,
            'grand_total'      => (string)$grandTotal,
            'discount_id'       => (string)$discountId,
            'discount_ids_json' => !empty($discountIds) ? json_encode($discountIds) : '',
            'user_discount_ids' => !empty($userDiscountIds) ? json_encode($userDiscountIds) : '',
            'discount_code'     => $discountCode,
            'discount_amount'   => (string)$discountAmount
        ],
    ]);

    query("UPDATE orders SET stripe_session_id = :sid WHERE id = :oid", [
        'sid' => $session->id,
        'oid' => $order_id
    ]);

    $pdo->commit();

    jsonSuccess('Sesión creada', [
        'url' => $session->url,
        'session_id' => $session->id
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jsonError('Error al procesar el pago con Stripe', 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jsonError('Error interno', 500);
}