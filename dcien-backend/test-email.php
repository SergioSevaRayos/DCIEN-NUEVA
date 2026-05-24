<?php
$backend_root = __DIR__;

require_once $backend_root . '/vendor/autoload.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/includes/mailer.php';

// CARGA MANUAL FORZADA
if (file_exists($backend_root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($backend_root);
    // Usamos load() y además verificamos qué cargó
    $variables = $dotenv->load();
}

echo "--- DEBUG DE VARIABLES ---\n";
echo "EMAIL_HOST: " . ($_ENV['EMAIL_HOST'] ?? 'VACÍO') . "\n";
echo "EMAIL_USER: " . ($_ENV['EMAIL_USER'] ?? 'VACÍO') . "\n";
echo "EMAIL_FROM: " . ($_ENV['EMAIL_FROM'] ?? 'VACÍO') . "\n";
echo "--------------------------\n";

if (empty($_ENV['EMAIL_HOST'])) {
    die("❌ Error: No se pudieron cargar las variables del archivo .env\n");
}

$html = getEmailContent('welcome', ['username' => 'Sergio Test', 'email' => 'test@d-cien.es']);

echo "Intentando conexión SMTP...\n";

// PASAMOS LOS DATOS MANUALMENTE PARA EL TEST
$resultado = sendEmail([
    'to'      => 'sergiosevarayos@gmail.com',
    'subject' => 'Prueba Final Bash D-CIEN',
    'html'    => $html
]);

if ($resultado) {
    echo "✅ ¡EMAIL ENVIADO CON ÉXITO!\n";
} else {
    echo "❌ FALLO EN EL ENVÍO.\n";
}