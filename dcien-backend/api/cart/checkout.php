<?php
/**
 * API: Crear sesión Stripe para carrito multi-item
 * POST /dcien-backend/api/cart/checkout.php
 */

$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/includes/discount-helpers.php';
require_once $backend_root . '/vendor/autoload.php';

startSecureSession();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metodo no permitido', 405);
}

$input        = getJsonInput();
$items        = $input['items']        ?? [];
$shippingData = $input['shippingData'] ?? [];
$discountData = $input['discount']     ?? null;

if (empty($items) || !is_array($items)) {
    jsonError('El carrito esta vacio', 400);
}

$userId = getUserId();

try {
    $pdo = getDatabaseConnection();
    $pdo->beginTransaction();

    // 1. Validar items y obtener precios reales
    $validatedItems = [];
    $subtotal = 0.0;

    foreach ($items as $item) {
        $seriesSlug = sanitizeInput($item['seriesSlug'] ?? '');
        $unitNumber = (int)($item['unitNumber'] ?? 0);
        $size       = sanitizeInput($item['size']   ?? '');
        $color      = sanitizeInput($item['color']  ?? '');
        $type       = sanitizeInput($item['type']   ?? 'standard');

        if (!$seriesSlug || !$unitNumber || !$size || !$color) {
            $pdo->rollBack();
            jsonError('Item invalido: datos incompletos', 400);
        }

        $series = queryOne(
            'SELECT price, name FROM series WHERE slug = :slug AND is_active = 1',
            ['slug' => $seriesSlug]
        );

        if (!$series) {
            $pdo->rollBack();
            jsonError('Serie no encontrada: ' . $seriesSlug, 404);
        }

        $unit = queryOne(
            'SELECT status FROM series_units WHERE series_slug = :slug AND unit_number = :num',
            ['slug' => $seriesSlug, 'num' => $unitNumber]
        );

        if (!$unit || $unit['status'] === 'sold') {
            $pdo->rollBack();
            jsonError('El numero ' . $unitNumber . ' de ' . $series['name'] . ' ya ha sido vendido', 409);
        }

        $unitPrice = (float)$series['price'];
        $subtotal += $unitPrice;

        $validatedItems[] = [
            'seriesSlug' => $seriesSlug,
            'seriesName' => $series['name'],
            'unitNumber' => $unitNumber,
            'size'       => $size,
            'color'      => $color,
            'type'       => $type,
            'unitPrice'  => $unitPrice,
        ];
    }

    // 2. Calcular descuento
    $discountAmount = 0.0;
    $discountId     = null;
    $discountCode   = null;

    if ($discountData) {
        $isQrBono = isset($discountData['discount_type']) && ($discountData['id'] ?? -1) === 0;

        if ($isQrBono || isset($discountData['discount_type'])) {
            $type  = $discountData['discount_type'] ?? $discountData['type'] ?? 'percent';
            $value = (float)($discountData['discount_value'] ?? $discountData['value'] ?? 0);
            if ($type === 'percent') {
                $discountAmount = $subtotal * ($value / 100);
            } else {
                $discountAmount = min($value, $subtotal);
            }
            $discountCode = $discountData['code'] ?? null;
        } elseif (!empty($discountData['id'])) {
            $validDiscount = validateUserDiscount($userId, $discountData['id']);
            if ($validDiscount) {
                $discountAmount = calculateSavings($subtotal, $validDiscount);
                $discountId     = $validDiscount['discount_id'];
                $discountCode   = $validDiscount['code'];
            }
        }
    }

    $discountAmount     = round($discountAmount, 2);
    $shippingFee        = 5.00;
    $priceAfterDiscount = max(0, $subtotal - $discountAmount);

    if ($priceAfterDiscount <= 0.10) {
        $priceAfterDiscount = 0.00;
        $shippingFee = 0.00;
    }

    $ivaAmount  = round($priceAfterDiscount * 0.21, 2);
    $grandTotal = round($priceAfterDiscount + $ivaAmount + $shippingFee, 2);

    // 3. Crear cabecera de orden
    $firstItem = $validatedItems[0];
    $shippingJson = json_encode($shippingData, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare('INSERT INTO orders
        (user_id, series_slug, unit_number, size, color, type, price, discount_id, shipping_data,
         subtotal, discount_amount, shipping_fee, is_cart_order, created_at)
        VALUES (:uid,:slug,:num,:size,:color,:type,:price,:did,:shipping,:subtotal,:disc,:ship,1,NOW())');

    $stmt->execute([
        'uid'      => $userId,
        'slug'     => $firstItem['seriesSlug'],
        'num'      => $firstItem['unitNumber'],
        'size'     => $firstItem['size'],
        'color'    => $firstItem['color'],
        'type'     => $firstItem['type'],
        'price'    => $grandTotal,
        'did'      => $discountId,
        'shipping' => $shippingJson,
        'subtotal' => $subtotal,
        'disc'     => $discountAmount,
        'ship'     => $shippingFee,
    ]);

    $orderId = $pdo->lastInsertId();

    // 4. Crear order_items y poner en checkout
    $stmtItem = $pdo->prepare('INSERT INTO order_items
        (order_id, series_slug, unit_number, size, color, type, unit_price)
        VALUES (:oid,:slug,:num,:size,:color,:type,:price)');

    foreach ($validatedItems as $vi) {
        $stmtItem->execute([
            'oid'   => $orderId,
            'slug'  => $vi['seriesSlug'],
            'num'   => $vi['unitNumber'],
            'size'  => $vi['size'],
            'color' => $vi['color'],
            'type'  => $vi['type'],
            'price' => $vi['unitPrice'],
        ]);

        query(
            "UPDATE series_units
             SET status='checkout', checkout_started_at=NOW(), reserved_by=:uid
             WHERE series_slug=:slug AND unit_number=:num AND status IN ('available','reserved','checkout')",
            ['uid' => $userId, 'slug' => $vi['seriesSlug'], 'num' => $vi['unitNumber']]
        );
    }

    // 5. Bypass pedido gratuito
    if ($grandTotal < 0.10) {
        $freeSessionId = 'FREE_ORDER_' . $orderId;
        query("UPDATE orders SET stripe_session_id=:sid WHERE id=:oid",
            ['sid' => $freeSessionId, 'oid' => $orderId]);
        foreach ($validatedItems as $vi) {
            query("UPDATE series_units SET status='sold', reserved_by=:uid
                   WHERE series_slug=:slug AND unit_number=:num",
                ['uid' => $userId, 'slug' => $vi['seriesSlug'], 'num' => $vi['unitNumber']]);
        }
        $pdo->commit();
        $successUrl = ($_ENV['STRIPE_SUCCESS_URL'] ?? getenv('STRIPE_SUCCESS_URL')) . '?session_id=' . $freeSessionId;
        jsonSuccess('Pedido gratuito procesado', ['url' => $successUrl, 'session_id' => $freeSessionId]);
        exit;
    }

    // 6. Crear sesión Stripe
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY'));

    $lineItems = [];
    foreach ($validatedItems as $vi) {
        $itemPrice = $vi['unitPrice'];
        if ($discountAmount > 0 && $subtotal > 0) {
            $prop      = $vi['unitPrice'] / $subtotal;
            $itemDisc  = round($discountAmount * $prop, 2);
            $itemPrice = max(0, $vi['unitPrice'] - $itemDisc);
        }
        $itemWithTax = round($itemPrice * 1.21, 2);

        $lineItems[] = [
            'price_data' => [
                'currency'     => 'eur',
                'product_data' => [
                    'name'        => $vi['seriesName'] . ' #' . str_pad($vi['unitNumber'], 3, '0', STR_PAD_LEFT),
                    'description' => 'Talla: ' . strtoupper($vi['size'])
                        . ' | Color: ' . ucfirst($vi['color'])
                        . ' | Tipo: '  . ucfirst($vi['type'])
                        . ($discountCode ? ' | Desc: ' . $discountCode : ''),
                ],
                'unit_amount' => (int)round($itemWithTax * 100),
            ],
            'quantity' => 1,
        ];
    }

    $lineItems[] = [
        'price_data' => [
            'currency'     => 'eur',
            'product_data' => ['name' => 'Gastos de Envio'],
            'unit_amount'  => (int)round($shippingFee * 100),
        ],
        'quantity' => 1,
    ];

    $itemsSummary = implode(',', array_map(function($vi) {
        return $vi['seriesSlug'] . ':' . $vi['unitNumber'];
    }, $validatedItems));

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items'           => $lineItems,
        'mode'                 => 'payment',
        'success_url'          => ($_ENV['STRIPE_SUCCESS_URL'] ?? getenv('STRIPE_SUCCESS_URL')) . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => $_ENV['STRIPE_CANCEL_URL'] ?? getenv('STRIPE_CANCEL_URL'),
        'metadata'             => [
            'order_id'        => (string)$orderId,
            'user_id'         => (string)$userId,
            'is_cart_order'   => '1',
            'items_summary'   => substr($itemsSummary, 0, 500),
            'shipping_json'   => json_encode($shippingData),
            'subtotal'        => (string)$subtotal,
            'discount_amount' => (string)$discountAmount,
            'discount_code'   => (string)($discountCode ?? ''),
            'shipping_fee'    => (string)$shippingFee,
            'grand_total'     => (string)$grandTotal,
        ],
    ]);

    query("UPDATE orders SET stripe_session_id=:sid WHERE id=:oid",
        ['sid' => $session->id, 'oid' => $orderId]);

    $pdo->commit();

    jsonSuccess('Sesion creada', ['url' => $session->url, 'session_id' => $session->id]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    logError('Cart checkout Stripe error', ['error' => $e->getMessage()]);
    jsonError('Error al procesar el pago con Stripe', 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    logError('Cart checkout error', ['error' => $e->getMessage()]);
    jsonError('Error interno: ' . $e->getMessage(), 500);
}
