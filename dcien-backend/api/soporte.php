<?php
/**
 * API: Recibir formulario de soporte de acceso
 * Endpoint: /api/soporte.php
 */

$backend_root = dirname(__DIR__);

require_once $backend_root . '/vendor/autoload.php';

// Cargar variables de entorno
if (file_exists($backend_root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($backend_root);
    $dotenv->safeLoad();
}

require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/includes/mailer.php';

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$input = getJsonInput();

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$messageText = trim($input['message'] ?? '');

// Validación básica
if (empty($name) || empty($email) || empty($messageText)) {
    jsonError('Todos los campos son obligatorios', 400);
}

if (!validateEmail($email)) {
    jsonError('El email no es válido', 400);
}

// Saneamiento para prevenir inyecciones HTML en el correo
$name = sanitizeInput($name);
$email = sanitizeInput($email);
$messageText = sanitizeInput($messageText);

// Configurar parámetros del correo
$to = 'sergiosevarayos@gmail.com';
$subject = "SOPORTE DCIEN - Incidencia de Acceso de: $name";

// Construir el cuerpo del mensaje en HTML
$html = "
    <h2>Nueva solicitud de Soporte de Acceso</h2>
    <p><strong>Usuario/Instagram:</strong> {$name}</p>
    <p><strong>Email de Contacto:</strong> {$email}</p>
    <hr>
    <p><strong>Mensaje / Incidencia:</strong></p>
    <p>" . nl2br($messageText) . "</p>
";

$emailParams = [
    'to' => $to,
    'reply_to' => $email,
    'subject' => $subject,
    'html' => $html
];

// Enviar el correo usando el sistema centralizado de DCIEN (PHPMailer)
$result = sendEmail($emailParams);

if ($result) {
    jsonSuccess('Mensaje enviado correctamente');
} else {
    jsonError('Error del servidor al intentar enviar el correo. Por favor contacta por Instagram.', 500);
}
