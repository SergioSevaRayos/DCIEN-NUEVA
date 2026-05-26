<?php
$backend_root = __DIR__;

require_once $backend_root . '/vendor/autoload.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/includes/mailer.php';

// CARGA MANUAL FORZADA
if (file_exists($backend_root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($backend_root);
    $variables = $dotenv->load();
}

$type = $argv[1] ?? 'all';

echo "--- DEBUG DE VARIABLES ---\n";
echo "EMAIL_HOST: " . ($_ENV['EMAIL_HOST'] ?? 'VACÍO') . "\n";
echo "EMAIL_USER: " . ($_ENV['EMAIL_USER'] ?? 'VACÍO') . "\n";
echo "EMAIL_PASS: " . (!empty($_ENV['EMAIL_PASS']) ? 'CONFIGURADO' : 'VACÍO') . "\n";
echo "EMAIL_PASSWORD: " . (!empty($_ENV['EMAIL_PASSWORD']) ? 'CONFIGURADO' : 'VACÍO') . "\n";
echo "EMAIL_FROM: " . ($_ENV['EMAIL_FROM'] ?? 'VACÍO') . "\n";
echo "--------------------------\n";

if (empty($_ENV['EMAIL_HOST'])) {
    die("❌ Error: No se pudieron cargar las variables del archivo .env\n");
}

$templates = [];

if ($type === 'welcome' || $type === 'all') {
    $templates['welcome'] = [
        'subject' => 'Prueba Bienvenida - DCIEN',
        'html' => getEmailTemplate('welcome', [
            'username' => 'Sergio Test',
            'email' => 'test@d-cien.es'
        ])
    ];
}

if ($type === 'reset' || $type === 'all') {
    $templates['password_reset'] = [
        'subject' => 'Prueba Recuperación Contraseña - DCIEN',
        'html' => getEmailTemplate('password_reset', [
            'username' => 'Sergio Test',
            'reset_link' => 'https://d-cien.es/acceso/restablecer?token=test_token_123'
        ])
    ];
}

if ($type === 'order' || $type === 'all') {
    $templates['order_confirmation'] = [
        'subject' => 'Prueba Confirmación Pedido - DCIEN',
        'html' => getEmailTemplate('order_confirmation', [
            'username' => 'Sergio Test',
            'order_id' => '9999',
            'email_items' => [
                [
                    'series_slug' => 'serie-0',
                    'unit_number' => 42,
                    'size' => 'M',
                    'color' => 'Negro',
                    'type' => 'Standard'
                ]
            ],
            'shipping_address' => "Sergio Test<br>Calle Mayor 123, 2B<br>28001 Madrid, España<br>Tlf: 600000000",
            'total' => '45.00',
            'discount_code' => 'TEST10',
            'discount_amount' => '5.00',
            'original_price' => '50.00'
        ])
    ];
}

if (empty($templates)) {
    die("❌ Error: Tipo de test desconocido. Usa: welcome, reset, order o all\n");
}

foreach ($templates as $name => $emailData) {
    echo "Intentando enviar plantilla '$name'...\n";
    $resultado = sendEmail([
        'to'      => 'sergiosevarayos@gmail.com',
        'subject' => $emailData['subject'],
        'html'    => $emailData['html']
    ]);

    if ($resultado) {
        echo "✅ Plantilla '$name' enviada con éxito.\n";
    } else {
        echo "❌ Falló el envío de la plantilla '$name'.\n";
    }
}