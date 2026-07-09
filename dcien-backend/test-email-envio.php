<?php
/**
 * Script de prueba — email "pedido enviado" con empresa y tracking
 * Ejecutar: php test-email-envio.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-descargas/modules/config.php';

$pedido = [
    'id'           => 999,
    'order_number' => 'DCIEN-TEST-001',
    'username'     => 'sergio',
    'email'        => 'sergiosevarayos@gmail.com',
];

$items = [
    [
        'series_slug' => 'serie-01',
        'series_name' => 'SERIE 01',
        'unit_number' => 42,
        'size'        => 'L',
        'color'       => 'Negro',
        'type'        => 'standard',
    ],
];

$addr = [
    'nombre' => 'Sergio Seva',
    'email'  => 'sergiosevarayos@gmail.com',
];

$empresa  = 'Correos Express';
$tracking = 'ES123456789ES';

// Función copiada de ordenes/index.php
function email_enviado_cliente_test(array $pedido, array $items, array $addr, string $empresa = '', string $tracking = ''): bool {
    $num_pedido = $pedido['order_number'] ?? $pedido['id'];
    $to = $addr['email'] ?: ($pedido['email'] ?? '');
    if (empty($to)) return false;

    $nombre = strtoupper($addr['nombre'] ?: ($pedido['username'] ?: 'Cliente'));

    $items_html = '';
    foreach ($items as $item) {
        $items_html .= "
        <div style='background:#f9f9f9;border-left:3px solid #000;padding:12px;margin:8px 0;'>
            <strong>" . strtoupper($item['series_name'] ?: $item['series_slug']) . " #" . str_pad($item['unit_number'], 3, '0', STR_PAD_LEFT) . "</strong><br>
            <span style='font-size:13px;color:#555;'>" . strtoupper($item['size']) . " · " . ucfirst($item['color']) . " · " . ucfirst($item['type']) . "</span>
        </div>";
    }

    $tracking_html = '';
    if (!empty($empresa) || !empty($tracking)) {
        $tracking_html = "
        <div style='background:#f0f0f0;border:1px solid #ddd;border-radius:4px;padding:16px;margin:20px 0;text-align:left;'>
            <p style='margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#888;'>Información de envío</p>" .
            (!empty($empresa) ? "<p style='margin:0 0 6px;font-size:14px;'>Transportista: <strong>" . htmlspecialchars($empresa) . "</strong></p>" : '') .
            (!empty($tracking) ? "<p style='margin:0;font-size:14px;'>Nº seguimiento: <strong style='font-family:monospace;'>" . htmlspecialchars($tracking) . "</strong></p>" : '') . "
        </div>";
    }

    $subject = "[TEST] Tu pedido DCIEN #{$num_pedido} va en camino";
    $html = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;'>
        <div style='background:#000;color:#fff;padding:30px;text-align:center;'>
            <h1 style='font-size:28px;letter-spacing:6px;margin:0;font-weight:900;'>DCIEN</h1>
        </div>
        <div style='padding:30px;text-align:center;color:#111;'>
            <div style='font-size:40px;margin-bottom:16px;'>📦</div>
            <h2 style='margin:0 0 12px;'>Hola, $nombre</h2>
            <p style='color:#555;margin:0 0 24px;'>Tu pedido <strong>#{$num_pedido}</strong> ya ha salido de nuestras instalaciones y está en manos del transportista.</p>
            $items_html
            $tracking_html
            <p style='color:#777;font-size:13px;margin:24px 0;'>Si tienes alguna duda responde a este correo.</p>
            <a href='https://d-cien.es' style='display:inline-block;background:#000;color:#fff;padding:14px 32px;text-decoration:none;font-weight:bold;letter-spacing:2px;text-transform:uppercase;'>
                Visitar Tienda
            </a>
        </div>
        <div style='background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#888;border-top:1px solid #eee;'>
            Gracias por confiar en DCIEN.
        </div>
    </div>";

    return sendAdminMail($to, $subject, $html);
}

$ok = email_enviado_cliente_test($pedido, $items, $addr, $empresa, $tracking);
echo $ok ? "✓ Email de prueba enviado a sergiosevarayos@gmail.com\n" : "✗ Error al enviar el email. Revisa las credenciales SMTP en modules/config.php\n";
