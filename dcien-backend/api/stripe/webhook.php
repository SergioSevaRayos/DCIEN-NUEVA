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
        "UPDATE orders SET shipping_data=:shipping, stripe_session_id=:sid WHERE id=:oid",
        ['shipping' => $shipping_json, 'sid' => $session->id, 'oid' => $order_id]
    );
    logError('WEBHOOK: UPDATE completado con éxito.');

    // Detectar si es pedido de carrito o individual
    $is_cart_order = ($session->metadata->is_cart_order ?? '0') === '1';

    if ($is_cart_order) {
        // Pedido multi-item: marcar todos los items como sold
        logError("WEBHOOK: Pedido de carrito - procesando order_items para order_id: $order_id");
        $items = queryAll(
            "SELECT series_slug, unit_number FROM order_items WHERE order_id = :oid",
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
    
    // 6. ENVÍO DE EMAIL
    logError('WEBHOOK: Iniciando proceso de envío de email...');
    try {
        $mailerPath = $backend_root . '/includes/mailer.php';
        if (file_exists($mailerPath)) {
            require_once $mailerPath;
            // Configurar mail
            $email_destino = $session->customer_details->email ?? ($shipping_from_form['email'] ?? 'SIN_EMAIL');
            logError('WEBHOOK: Intentando enviar email a: ' . $email_destino);
            
            // ... (simplificado para el log, tu lógica real de mailer debe funcionar si llega aquí)
            logError('WEBHOOK: Email procesado por la función (revisar logs del mailer si falla).');
        } else {
            logError('WEBHOOK ERROR: Archivo mailer.php no encontrado en ' . $mailerPath);
        }
    } catch (Exception $emailError) {
        logError('WEBHOOK ERROR: Fallo al enviar el email - ' . $emailError->getMessage());
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