<?php
require __DIR__ . '/dcien-backend/config/database.php';
require __DIR__ . '/dcien-backend/includes/mailer.php';

$html = getEmailTemplate('order_confirmation', [
    'username' => 'Sergio Seva Rayos',
    'order_id' => '260610-15125',
    'email_items' => [
        [
            'series_slug' => 'serie-0',
            'unit_number' => '009',
            'size' => 'L',
            'color' => 'Negro',
            'type' => 'Standard'
        ],
        [
            'series_slug' => 'serie-0',
            'unit_number' => '006',
            'size' => 'XL',
            'color' => 'Negro',
            'type' => 'Standard'
        ]
    ],
    'shipping_address' => 'Calle Ronda de la Estacion<br>03349 San Isidro, valencia<br>sergiosevarayos@gmail.com | +34633841203',
    'total' => '77.00',
    'discount_code' => 'DCIEN10',
    'discount_amount' => '8.00',
    'original_price' => '€85.00'
]);

$_ENV['EMAIL_HOST'] = 'smtp.hostinger.com';
$_ENV['EMAIL_PORT'] = '587';
$_ENV['EMAIL_USER'] = 'soporte@d-cien.es';
$_ENV['EMAIL_PASS'] = '9400Jet_';
$_ENV['EMAIL_FROM'] = 'soporte@d-cien.es';
$_ENV['EMAIL_FROM_NAME'] = 'DCIEN';

$success = sendEmail([
    'to' => 'sergiosevarayos@gmail.com',
    'subject' => 'PEDIDO CONFIRMADO #260610-15125 — DCIEN',
    'html' => $html
]);

if ($success) {
    echo "ENVIADO";
} else {
    echo "ERROR. Ver logError o variables: " . $_ENV['EMAIL_HOST'];
}
