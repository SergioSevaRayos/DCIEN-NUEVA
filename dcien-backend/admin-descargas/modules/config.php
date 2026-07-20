<?php
/**
 * Configuración compartida del panel de administración
 */

// Configuración de base de datos (local vs producción)
$isLocal = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'] ?? '', '.test') !== false;
define('DB_HOST', $isLocal ? '127.0.0.1' : 'localhost');
define('DB_NAME', 'u755459505_limited_tees');
define('DB_USER', $isLocal ? 'root' : 'u755459505_sergio');
define('DB_PASS', $isLocal ? '' : '9400Jet_');

// Email
define('ADMIN_EMAIL', 'sergiosevarayos@gmail.com');
define('EMAIL_HOST', 'smtp.hostinger.com');
define('EMAIL_PORT', 465);
define('EMAIL_USER', 'contacto@d-cien.es');
define('EMAIL_PASS', '9400Jet_');
define('EMAIL_FROM', 'contacto@d-cien.es');
define('EMAIL_FROM_NAME', 'DCIEN');

date_default_timezone_set('Europe/Madrid');

function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    return $pdo;
}

function logMailError(string $message): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents(
        $logDir . '/mail-errors.log',
        date('[Y-m-d H:i:s] ') . $message . PHP_EOL,
        FILE_APPEND
    );
}

/**
 * Registra en admin_email_log cada intento de envío (éxito o fallo) hecho vía
 * sendAdminMail(). Centralizado aquí en vez de en cada punto de llamada para
 * que ninguna vía de envío futura se quede sin auditar.
 */
function logAdminEmail(
    string $to,
    ?string $username,
    string $subject,
    string $html,
    string $type,
    ?int $userId,
    string $status,
    ?string $error = null
): void {
    try {
        $pdo = get_db_connection();
        $pdo->prepare(
            "INSERT INTO admin_email_log
                (user_id, recipient_email, recipient_username, email_type, subject, body_html, status, error_message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$userId, $to, $username, $type, $subject, $html, $status, $error ? substr($error, 0, 500) : null]);
    } catch (Exception $e) {
        // Si el propio registro falla (tabla no creada todavía, etc.) no debe
        // tumbar el envío de email — solo lo dejamos en el log de fallback.
        logMailError("No se pudo registrar en admin_email_log el envío a $to: " . $e->getMessage());
    }
}

function sendAdminMail(
    string $to,
    string $subject,
    string $html,
    string $type = 'general',
    ?int $userId = null,
    ?string $username = null
): bool {
    // Igual que ordenes/index.php: admin-descargas/ se sirve desde public_html/
    // en producción, así que vendor/ está tres niveles arriba (dentro de
    // dcien-backend/), no dos como en local.
    $autoloadProd  = __DIR__ . '/../../../dcien-backend/vendor/autoload.php';
    $autoloadLocal = __DIR__ . '/../../vendor/autoload.php';
    $autoload = file_exists($autoloadProd) ? $autoloadProd : $autoloadLocal;

    if (!file_exists($autoload)) {
        $error = "No se encontró vendor/autoload.php (probado: $autoloadProd | $autoloadLocal)";
        logMailError("$error al enviar a $to");
        logAdminEmail($to, $username, $subject, $html, $type, $userId, 'failed', $error);
        return false;
    }
    require_once $autoload;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = EMAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_USER;
        $mail->Password   = EMAIL_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = EMAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->send();
        logAdminEmail($to, $username, $subject, $html, $type, $userId, 'sent');
        return true;
    } catch (Exception $e) {
        $error = "{$mail->ErrorInfo} | {$e->getMessage()}";
        logMailError("Fallo enviando a $to: $error");
        logAdminEmail($to, $username, $subject, $html, $type, $userId, 'failed', $error);
        return false;
    }
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function format_date($date, $format = 'd/m/Y H:i') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

function format_price($price) {
    return '€' . number_format($price, 2, ',', '.');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function show_message($type, $message) {
    $map = ['success' => 'alert-success', 'error' => 'alert-error', 'warning' => 'alert-warning'];
    $class = $map[$type] ?? 'alert-info';
    return "<div class='alert $class'>$message</div>";
}
