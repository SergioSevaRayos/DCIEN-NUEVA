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

$input         = getJsonInput();
$items         = $input['items']        ?? [];
$shippingData  = $input['shippingData'] ?? [];
$discountData  = $input['discount']     ?? null;   // compat. flujo antiguo / QR bono
$discountsData = $input['discounts']    ?? null;   // nuevo: array de descuentos

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

    // 2. Calcular descuento(s)
    $discountAmount   = 0.0;
    $discountId       = null;
    $discountIds      = [];   // array de discount.id aplicados
    $userDiscountIds  = [];   // array de user_discounts.id para marcar usados
    $discountCodes    = [];

    // 2a. Nuevo flujo: array de descuentos stackables
    if (!empty($discountsData) && is_array($discountsData)) {
        $ids = array_filter(array_map(fn($d) => (int)($d['id'] ?? 0), $discountsData));
        if (!empty($ids)) {
            $validDiscounts = validateMultipleDiscounts($userId, $ids);
            if (!empty($validDiscounts)) {
                $discountAmount  = calculateMultipleDiscountsAmount($subtotal, $validDiscounts);
                $discountId      = $validDiscounts[0]['discount_id'];
                $discountIds     = array_column($validDiscounts, 'discount_id');
                $userDiscountIds = array_column($validDiscounts, 'user_discount_id');
                $discountCodes   = array_column($validDiscounts, 'code');
            }
        }
    }
    // 2b. Flujo antiguo: descuento único (QR bono o item individual)
    elseif ($discountData) {
        $isQrBono = isset($discountData['discount_type']) && ($discountData['id'] ?? -1) === 0;

        if ($isQrBono || isset($discountData['discount_type'])) {
            $type  = $discountData['discount_type'] ?? $discountData['type'] ?? 'percent';
            $value = (float)($discountData['discount_value'] ?? $discountData['value'] ?? 0);
            $discountAmount = $type === 'percent'
                ? $subtotal * ($value / 100)
                : min($value, $subtotal);
            $discountCodes[] = $discountData['code'] ?? null;
        } elseif (!empty($discountData['id'])) {
            $validDiscount = validateUserDiscount($userId, $discountData['id']);
            if ($validDiscount) {
                $discountAmount  = calculateSavings($subtotal, $validDiscount);
                $discountId      = $validDiscount['discount_id'];
                $discountIds[]   = $validDiscount['discount_id'];
                $userDiscountIds[] = $validDiscount['user_discount_id'];
                $discountCodes[] = $validDiscount['code'];
            }
        }
    }

    $discountCode = implode('+', array_filter($discountCodes));

    $discountAmount     = round($discountAmount, 2);
    $shippingFee        = 10.00;
    $priceAfterDiscount = max(0, $subtotal - $discountAmount);

    if ($priceAfterDiscount <= 0.10) {
        $priceAfterDiscount = 0.00;
        $shippingFee = 0.00;
    }

    $ivaAmount  = round($priceAfterDiscount - ($priceAfterDiscount / 1.21), 2);
    $grandTotal = round($priceAfterDiscount + $shippingFee, 2);

    // 3. Crear cabecera de orden
    $firstItem = $validatedItems[0];
    $shippingJson = json_encode($shippingData, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare('INSERT INTO orders
        (user_id, series_slug, unit_number, size, color, type, price, discount_id, discount_ids,
         shipping_data, subtotal, discount_amount, shipping_fee, is_cart_order, created_at)
        VALUES (:uid,:slug,:num,:size,:color,:type,:price,:did,:dids,:shipping,:subtotal,:disc,:ship,1,NOW())');

    $stmt->execute([
        'uid'      => $userId,
        'slug'     => $firstItem['seriesSlug'],
        'num'      => $firstItem['unitNumber'],
        'size'     => $firstItem['size'],
        'color'    => $firstItem['color'],
        'type'     => $firstItem['type'],
        'price'    => $grandTotal,
        'did'      => $discountId,
        'dids'     => !empty($discountIds) ? json_encode($discountIds) : null,
        'shipping' => $shippingJson,
        'subtotal' => $subtotal,
        'disc'     => $discountAmount,
        'ship'     => $shippingFee,
    ]);

    $orderId = $pdo->lastInsertId();

    // Generar número de pedido: AAMMDD-(ID+15000)
    $orderNumber = date('ymd') . '-' . ($orderId + 15000);
    query(
        "UPDATE orders SET order_number = :num WHERE id = :oid",
        ['num' => $orderNumber, 'oid' => $orderId]
    );

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

        // Enviar email de confirmación para pedidos gratuitos
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

                $email_items = [];
                foreach ($validatedItems as $vi) {
                    $email_items[] = [
                        'series_slug' => $vi['seriesSlug'],
                        'unit_number' => $vi['unitNumber'],
                        'size'        => $vi['size'],
                        'color'       => $vi['color'],
                        'type'        => $vi['type'],
                    ];
                }

                $html = getEmailTemplate('order_confirmation', [
                    'username'         => htmlspecialchars($username),
                    'order_id'         => $orderNumber,
                    'email_items'      => $email_items,
                    'shipping_address' => $address_lines,
                    'total'            => '0.00',
                    'discount_code'    => $discountCode ?? '',
                    'discount_amount'  => $discountAmount > 0 ? number_format($discountAmount, 2, '.', '') : '',
                    'original_price'   => $discountAmount > 0 ? '€' . number_format($subtotal, 2, '.', '') : '',
                ]);

                sendEmail([
                    'to'      => $emailTo,
                    'subject' => 'Pedido Confirmado #' . $orderNumber . ' — DCIEN',
                    'html'    => $html,
                ]);
            }
        } catch (Exception $emailEx) {
            logError('Free cart order email error', ['error' => $emailEx->getMessage()]);
        }

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
        $itemWithTax = $itemPrice; // El precio de la camiseta ya incluye el IVA

        $lineItems[] = [
            'price_data' => [
                'currency'     => 'eur',
                'tax_behavior' => 'inclusive',
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
            'tax_behavior' => 'inclusive',
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
            'discount_amount'   => (string)$discountAmount,
            'discount_code'     => (string)($discountCode ?? ''),
            'discount_ids_json' => !empty($discountIds) ? json_encode($discountIds) : '',
            'user_discount_ids' => !empty($userDiscountIds) ? json_encode($userDiscountIds) : '',
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
