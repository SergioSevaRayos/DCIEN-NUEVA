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

function sendAdminMail(string $to, string $subject, string $html): bool {
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoload)) return false;
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
        return true;
    } catch (Exception $e) {
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
