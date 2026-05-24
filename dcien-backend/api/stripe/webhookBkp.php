<?php
/**
 * WEBHOOK STRIPE - VERSIÓN FINAL COMPLETA
 */

$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/vendor/autoload.php';

logError('Webhook: received');

$payload         = @file_get_contents('php://input');
$sig_header      = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET');

if (!$endpoint_secret) {
    logError('Webhook: STRIPE_WEBHOOK_SECRET missing');
    http_response_code(500);
    exit;
}

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch (\UnexpectedValueException $e) {
    logError('Webhook: invalid payload');
    http_response_code(400);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    logError('Webhook: invalid signature');
    http_response_code(400);
    exit;
}

if ($event->type !== 'checkout.session.completed') {
    http_response_code(200);
    exit;
}

$session = $event->data->object;

// ══════════════════════════════════════════════════════════════
// EXTRAER METADATA
// ══════════════════════════════════════════════════════════════
$order_id        = $session->metadata->order_id        ?? null;
$unit_number     = $session->metadata->unit_number     ?? null;
$series_slug     = $session->metadata->series_slug     ?? null;
$size            = $session->metadata->size            ?? null;
$color           = $session->metadata->color           ?? null;
$type            = $session->metadata->type            ?? 'standard';

$discount_id     = $session->metadata->discount_id     ?? null;
$discount_code   = $session->metadata->discount_code   ?? null;
$discount_amount = (float)($session->metadata->discount_amount ?? 0);
$user_id         = $session->metadata->user_id         ?? null;

$shipping_from_form = [];
if (!empty($session->metadata->shipping_json)) {
    $shipping_from_form = json_decode($session->metadata->shipping_json, true) ?: [];
}

if (!$order_id) {
    logError('Webhook: no order_id in metadata');
    http_response_code(400);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $pdo->beginTransaction();

    // ══════════════════════════════════════════════════════════════
    // 1. CONSTRUIR SHIPPING_DATA COMPLETO
    // ══════════════════════════════════════════════════════════════
    $shipping_data = [
        'customer' => [
            'name'  => $session->customer_details->name  ?? null,
            'email' => $session->customer_details->email ?? null,
            'phone' => $session->customer_details->phone ?? null,
        ],
        'shipping_address' => [
            'first_name'  => $shipping_from_form['firstName']    ?? null,
            'last_name'   => $shipping_from_form['lastName']     ?? null,
            'line1'       => $shipping_from_form['address']      ?? null,
            'line2'       => $shipping_from_form['addressExtra'] ?? null,
            'postal_code' => $shipping_from_form['postalCode']   ?? null,
            'city'        => $shipping_from_form['city']         ?? null,
            'province'    => $shipping_from_form['province']     ?? null,
            'country'     => $shipping_from_form['country']      ?? 'ES',
            'email'       => $shipping_from_form['email']        ?? null,
            'phone'       => $shipping_from_form['phone']        ?? null,
        ],
        'payment' => [
            'status'       => $session->payment_status,
            'amount_total' => $session->amount_total,
            'amount_euros' => $session->amount_total / 100,
            'currency'     => strtoupper($session->currency ?? 'EUR'),
        ],
        'stripe_session_id' => $session->id,
        'processed_at'      => date('Y-m-d H:i:s'),
    ];

    $shipping_json = json_encode($shipping_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // ══════════════════════════════════════════════════════════════
    // 2. ACTUALIZAR ORDER (CORREGIDO: SE ELIMINÓ 'status=paid' PARA EVITAR ERROR SQL)
    // ══════════════════════════════════════════════════════════════
    query(
        "UPDATE orders SET shipping_data=:shipping, stripe_session_id=:sid WHERE id=:oid",
        ['shipping' => $shipping_json, 'sid' => $session->id, 'oid' => $order_id]
    );

    // ══════════════════════════════════════════════════════════════
    // 3. MARCAR NÚMERO COMO SOLD
    // ══════════════════════════════════════════════════════════════
    $affected = 0;
    if ($unit_number && $series_slug) {
        $stmt = query(
            "UPDATE series_units
             SET status='sold',
                 sold_at=NOW(),
                 order_id=:oid,
                 reserved_at=NULL,
                 reserved_by=NULL,
                 checkout_started_at=NULL,
                 updated_at=NOW()
             WHERE series_slug=:slug
               AND unit_number=:num
               AND (status='checkout' OR status='reserved')",
            ['oid' => $order_id, 'slug' => $series_slug, 'num' => $unit_number]
        );
        $affected = $stmt->rowCount();
    }

    if ($affected === 0) {
        logError('Webhook Warning: Unit was not updated', ['unit' => $unit_number, 'slug' => $series_slug]);
    }

    // ══════════════════════════════════════════════════════════════
    // 4. ACTUALIZAR CONTADORES DE SERIE
    // ══════════════════════════════════════════════════════════════
    query(
        "UPDATE series
         SET available_units=GREATEST(0, available_units-1),
             sold_units=sold_units+1,
             updated_at=NOW()
         WHERE slug=:slug",
        ['slug' => $series_slug]
    );

    // ══════════════════════════════════════════════════════════════
    // 5. GESTIÓN DE DESCUENTOS
    // ══════════════════════════════════════════════════════════════
    if (!empty($discount_id) && !empty($user_id)) {
        query(
            "UPDATE user_discounts
             SET used_at=NOW(), order_id=:oid
             WHERE user_id=:uid AND discount_id=:did AND used_at IS NULL",
            ['oid' => $order_id, 'uid' => $user_id, 'did' => $discount_id]
        );

        query(
            "UPDATE discounts SET used_count=used_count+1 WHERE id=:did",
            ['did' => $discount_id]
        );
    }

    // ══════════════════════════════════════════════════════════════
    // 6. ENVIAR EMAIL DE CONFIRMACIÓN
    // ══════════════════════════════════════════════════════════════
    try {
        $mailerPath = $backend_root . '/includes/mailer.php';
        if (file_exists($mailerPath)) {
            require_once $mailerPath;

            $series = queryOne("SELECT name FROM series WHERE slug=:slug", ['slug' => $series_slug]);

            $ship = $shipping_from_form ?: [];
            $full_address = ($ship['address'] ?? '') .
                          (!empty($ship['addressExtra']) ? ', ' . $ship['addressExtra'] : '') .
                          '. ' . ($ship['postalCode'] ?? '') . ' ' .
                          ($ship['city'] ?? '') .
                          (!empty($ship['province']) ? ' (' . $ship['province'] . ')' : '');

            $emailData = [
                'username'         => $ship['firstName'] ?? 'Cliente',
                'order_id'         => $order_id,
                'product_name'     => $series['name'] ?? 'DCIEN',
                'unit_number'      => str_pad($unit_number, 2, '0', STR_PAD_LEFT),
                'size'             => strtoupper($size ?? ''),
                'color'            => ucfirst($color ?? ''),
                'type'             => ucfirst($type ?? 'standard'),
                'total'            => number_format($session->amount_total / 100, 2, ',', '.'),
                'shipping_address' => $full_address,
                'discount_code'    => $discount_code,
                'discount_amount'  => $discount_amount > 0 ? number_format($discount_amount, 2, ',', '.') : null
            ];

            $html = getEmailContent('order_confirmation', $emailData);
            sendEmail([
                'to'      => $session->customer_details->email ?? ($ship['email'] ?? ''),
                'subject' => 'Confirmación de tu pedido DCIEN #' . $emailData['unit_number'],
                'html'    => $html
            ]);
        }
    } catch (Exception $emailError) {
        logError('Webhook: Email error', ['error' => $emailError->getMessage()]);
    }

    $pdo->commit();
    logError('Webhook: SUCCESS', ['order_id' => $order_id]);

    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    logError('Webhook: CRITICAL ERROR', ['error' => $e->getMessage()]);
    http_response_code(500);
}