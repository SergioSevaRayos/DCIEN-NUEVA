<?php
/**
 * WEBHOOK STRIPE - MODO DIAGNÓSTICO EXHAUSTIVO
 */

$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/vendor/autoload.php';

// 1. INICIO DEL LOG
logError('====================================================');
logError('WEBHOOK: Petición entrante iniciada');

$payload         = @file_get_contents('php://input');
$sig_header      = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET');

logError('WEBHOOK: Longitud del payload: ' . strlen($payload));
logError('WEBHOOK: Header de firma presente: ' . (!empty($sig_header) ? 'SÍ' : 'NO'));
logError('WEBHOOK: Secreto de entorno cargado: ' . (!empty($endpoint_secret) ? 'SÍ (Empieza por: ' . substr($endpoint_secret, 0, 6) . '...)' : 'NO (VACÍO)'));

if (!$endpoint_secret) {
    logError('WEBHOOK FATAL: Falta STRIPE_WEBHOOK_SECRET en .env');
    http_response_code(500);
    exit;
}

// 2. VERIFICACIÓN DE FIRMA
try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    logError('WEBHOOK: Firma verificada correctamente. ID Evento: ' . $event->id);
} catch (\UnexpectedValueException $e) {
    logError('WEBHOOK FATAL: Payload JSON inválido - ' . $e->getMessage());
    http_response_code(400);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    logError('WEBHOOK FATAL: Firma inválida. ¿Coincide el whsec_ de Live con el .env? - ' . $e->getMessage());
    http_response_code(400);
    exit;
}

// 3. TIPO DE EVENTO
logError('WEBHOOK: Tipo de evento recibido: ' . $event->type);
if ($event->type !== 'checkout.session.completed') {
    logError('WEBHOOK: Evento ignorado (No es checkout.session.completed)');
    http_response_code(200);
    exit;
}

$session = $event->data->object;
logError('WEBHOOK: Procesando sesión de Stripe ID: ' . $session->id);

// 4. EXTRACCIÓN DE METADATA
$order_id = $session->metadata->order_id ?? null;
logError('WEBHOOK: Order ID extraído de metadata: ' . ($order_id ? $order_id : 'NULO'));

if (!$order_id) {
    logError('WEBHOOK FATAL: No hay order_id en la metadata. Contenido de metadata: ' . json_encode($session->metadata));
    http_response_code(400);
    exit;
}

// Variables restantes
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
logError('WEBHOOK: Datos de envío en JSON parseados: ' . (!empty($shipping_from_form) ? 'SÍ' : 'NO'));

// 5. INICIO DE BASE DE DATOS
try {
    logError('WEBHOOK: Conectando a Base de Datos y abriendo transacción...');
    $pdo = getDatabaseConnection();
    $pdo->beginTransaction();

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
    logError('WEBHOOK: Ejecutando UPDATE en BD para order_id: ' . $order_id);

    query(
        "UPDATE orders SET shipping_data=:shipping, stripe_session_id=:sid, status='paid' WHERE id=:oid",
        ['shipping' => $shipping_json, 'sid' => $session->id, 'oid' => $order_id]
    );
    logError('WEBHOOK: UPDATE completado con éxito (status=paid).');

    // Detectar si es pedido de carrito o individual
    $is_cart_order = ($session->metadata->is_cart_order ?? '0') === '1';

    if ($is_cart_order) {
        // Pedido multi-item: marcar todos los items como sold
        logError("WEBHOOK: Pedido de carrito - procesando order_items para order_id: $order_id");
        $items = queryAll(
            "SELECT series_slug, unit_number, size, color, type FROM order_items WHERE order_id = :oid",
            ['oid' => $order_id]
        );
        foreach ($items as $item) {
            query(
                "UPDATE series_units SET status='sold', sold_at=NOW(), order_id=:oid, reserved_at=NULL, reserved_by=NULL, checkout_started_at=NULL, updated_at=NOW() WHERE series_slug=:slug AND unit_number=:num AND status IN ('checkout','reserved')",
                ['oid' => $order_id, 'slug' => $item['series_slug'], 'num' => $item['unit_number']]
            );
            query(
                "UPDATE series SET available_units=GREATEST(0, available_units-1), sold_units=sold_units+1, updated_at=NOW() WHERE slug=:slug",
                ['slug' => $item['series_slug']]
            );
        }
        logError("WEBHOOK: " . count($items) . " unidades marcadas como sold");
    } else {
        // Pedido individual (flujo original)
        if ($unit_number && $series_slug) {
            logError("WEBHOOK: Actualizando estado de unidad $unit_number a 'sold'");
            query(
                "UPDATE series_units SET status='sold', sold_at=NOW(), order_id=:oid, reserved_at=NULL, reserved_by=NULL, checkout_started_at=NULL, updated_at=NOW() WHERE series_slug=:slug AND unit_number=:num AND (status='checkout' OR status='reserved')",
                ['oid' => $order_id, 'slug' => $series_slug, 'num' => $unit_number]
            );
        }
        query("UPDATE series SET available_units=GREATEST(0, available_units-1), sold_units=sold_units+1, updated_at=NOW() WHERE slug=:slug", ['slug' => $series_slug]);
    }
    
    // 6. ENVÍO DE EMAIL DE CONFIRMACIÓN
    logError('WEBHOOK: Iniciando envío de email de confirmación...');
    try {
        require_once $backend_root . '/includes/mailer.php';

        // Datos del usuario (username + email de registro)
        $dbUser        = queryOne("SELECT username, email FROM users WHERE id = :uid", ['uid' => $user_id]);
        $email_destino = $dbUser['email']
            ?? ($session->customer_details->email ?? ($shipping_from_form['email'] ?? null));
        $username      = $dbUser['username'] ?? ($shipping_from_form['firstName'] ?? 'Cliente');

        if (!$email_destino) {
            logError('WEBHOOK ERROR: No se pudo determinar email de destino para order_id: ' . $order_id);
        } else {
            // Lista de items para el email
            if ($is_cart_order) {
                $email_items = $items; // Ya se cargaron de order_items arriba
            } else {
                $email_items = [[
                    'series_slug' => $series_slug,
                    'unit_number' => $unit_number,
                    'size'        => $size,
                    'color'       => $color,
                    'type'        => $type,
                ]];
            }

            // Dirección de envío formateada
            $s = $shipping_from_form;
            $address_lines = implode('<br>', array_filter([
                trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? '')),
                trim(($s['address'] ?? '') . ($s['addressExtra'] ? ', ' . $s['addressExtra'] : '')),
                trim(($s['postalCode'] ?? '') . ' ' . ($s['city'] ?? '') . (isset($s['province']) ? ', ' . $s['province'] : '')),
                ($s['email'] ?? $email_destino) . (isset($s['phone']) ? ' | ' . $s['phone'] : ''),
            ]));

            $total_pagado = number_format($session->amount_total / 100, 2, '.', '');

            $email_data = [
                'username'         => htmlspecialchars($username),
                'order_id'         => $order_id,
                'email_items'      => $email_items,
                'shipping_address' => $address_lines,
                'total'            => $total_pagado,
                'discount_code'    => $discount_code ?? '',
                'discount_amount'  => $discount_amount > 0 ? number_format($discount_amount, 2, '.', '') : '',
                'original_price'   => $discount_amount > 0
                    ? '€' . number_format(($session->amount_total / 100) + $discount_amount, 2, '.', '')
                    : '',
            ];

            $html = getEmailTemplate('order_confirmation', $email_data);

            $sent = sendEmail([
                'to'      => $email_destino,
                'subject' => 'Pedido Confirmado #' . $order_id . ' — DCIEN',
                'html'    => $html,
            ]);

            logError('WEBHOOK: Email ' . ($sent ? 'enviado correctamente' : 'FALLÓ') . ' a: ' . $email_destino);
        }
    } catch (Exception $emailError) {
        logError('WEBHOOK ERROR: Excepción enviando email - ' . $emailError->getMessage());
    }

    $pdo->commit();
    logError('WEBHOOK EXITO: Transacción finalizada y guardada. Código HTTP 200.');

    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    logError('WEBHOOK FATAL: Excepción capturada en BD o lógica - ' . $e->getMessage() . ' en la línea ' . $e->getLine());
    http_response_code(500);
}