<?php
/**
 * Sistema de envío de emails con PHPMailer
 * Templates centralizados en /includes/emails/
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Envía un email usando PHPMailer y configuración SMTP
 * 
 * @param array $params ['to' => 'email@ejemplo.com', 'subject' => 'Asunto', 'html' => 'Contenido']
 * @return bool True si se envió, false si falló
 */
function sendEmail(array $params): bool {
    $mail = new PHPMailer(true);
    
    try {
        // ════════════════════════════════════════════════════════════
        // CARGAR VARIABLES DE ENTORNO
        // ════════════════════════════════════════════════════════════
        $host = $_ENV['EMAIL_HOST'] ?? getenv('EMAIL_HOST');
        $port = $_ENV['EMAIL_PORT'] ?? getenv('EMAIL_PORT') ?: 465;
        $user = $_ENV['EMAIL_USER'] ?? getenv('EMAIL_USER');
        $pass = $_ENV['EMAIL_PASS'] ?? getenv('EMAIL_PASS');
        if (empty($pass)) {
            $pass = $_ENV['EMAIL_PASSWORD'] ?? getenv('EMAIL_PASSWORD');
        }
        $from = $_ENV['EMAIL_FROM'] ?? getenv('EMAIL_FROM');
        $fromName = $_ENV['EMAIL_FROM_NAME'] ?? getenv('EMAIL_FROM_NAME') ?? 'DCIEN';

        // Validar que existan
        if (empty($host) || empty($user) || empty($pass)) {
            throw new Exception('Variables de email no configuradas en el entorno');
        }

        // ════════════════════════════════════════════════════════════
        // CONFIGURACIÓN SMTP
        // ════════════════════════════════════════════════════════════
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->CharSet    = 'UTF-8';
        $mail->Port       = $port;

        // Auto-configuración de cifrado según puerto
        if ($port == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Debug SOLO en desarrollo (Redirigido a log para no romper el JSON)
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = 'error_log';
        }

        // ════════════════════════════════════════════════════════════
        // REMITENTE Y DESTINATARIO
        // ════════════════════════════════════════════════════════════
        $mail->setFrom($from, $fromName);
        $mail->addAddress($params['to']);

        if (isset($params['reply_to'])) {
            $mail->addReplyTo($params['reply_to']);
        }

        // ════════════════════════════════════════════════════════════
        // CONTENIDO
        // ════════════════════════════════════════════════════════════
        $mail->isHTML(true);
        $mail->Subject = $params['subject'];
        $mail->Body    = $params['html'];
        $mail->AltBody = $params['text'] ?? strip_tags(str_replace('<br>', "\n", $params['html']));

        $mail->send();
        return true;

    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError('Email error', [
                'to' => $params['to'] ?? 'unknown',
                'error' => $mail->ErrorInfo,
                'exception' => $e->getMessage()
            ]);
        }
        return false;
    }
}

/**
 * Obtiene el contenido HTML de un template de email
 * Carga templates desde /includes/emails/
 *
 * @param string $template Nombre del template (welcome, password_reset, order_confirmation)
 * @param array $data Datos para reemplazar en el template
 * @return string HTML del email
 */
function getEmailTemplate(string $template, array $data = []): string {
    $templatePath = __DIR__ . '/emails/' . $template . '.php';

    if (!file_exists($templatePath)) {
        if (function_exists('logError')) {
            logError('Email template not found', ['template' => $template]);
        }
        return '';
    }

    // Ejecutar el template con $data en scope; el archivo hace `return '...'`
    $html = (function () use ($templatePath, $data) {
        return require $templatePath;
    })();

    // Reemplazar {placeholder} con valores simples
    foreach ($data as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            $html = str_replace('{' . $key . '}', (string) $value, $html);
        }
    }

    return (string) $html;
}
