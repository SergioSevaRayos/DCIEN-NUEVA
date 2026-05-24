<?php
// debug-order-email.php
$backend_root = __DIR__;

require_once $backend_root . '/vendor/autoload.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/includes/mailer.php';

// CARGA MANUAL (Indispensable para que funcione en terminal/bash)
if (file_exists($backend_root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($backend_root);
    $dotenv->load();
}

// 1. Preparamos datos ficticios pero realistas
$orderData = [
    'username'         => 'Sergio',
    'order_id'         => '2026-0001',
    'product_name'     => 'CAMISETA DCIEN ORIGIN',
    'size'             => 'L',
    'color'            => 'BLACK',
    'unit_number'      => '07', // Tu número favorito para el test
    'total'            => '45.00',
    'shipping_address' => 'Calle Gran Vía 1, 28013 Madrid, España'
];

// 2. Generamos el HTML usando el template que configuramos
$html = getEmailContent('order_confirmation', $orderData);

echo "--- INICIANDO PRUEBA DE COMPRA ---\n";
echo "Enviando comprobante a: sergiosevarayos@gmail.com\n";

$resultado = sendEmail([
    'to'      => 'sergiosevarayos@gmail.com',
    'subject' => 'Tu pedido DCIEN #' . $orderData['order_id'] . ' ha sido confirmado',
    'html'    => $html
]);

if ($resultado) {
    echo "✅ COMPROBANTE ENVIADO CON ÉXITO.\n";
} else {
    echo "❌ ERROR: Revisa el log o activa SMTPDebug en mailer.php\n";
}