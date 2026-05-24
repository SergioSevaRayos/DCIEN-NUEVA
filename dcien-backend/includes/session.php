<?php
/**
 * Configuración de Sesiones Seguras - DCIEN
 */

$backend_root = dirname(__DIR__);

// Cargar .env antes de tocar sesiones
if (file_exists($backend_root . '/.env')) {
    require_once $backend_root . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable($backend_root);
    $dotenv->load();
}

// Nombre de sesión
session_name(getenv('SESSION_NAME') ?: 'dcien_session');

/**
 * Inicia sesión segura compartida con frontend Astro
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {

        $isLocal = (getenv('APP_ENV') ?: 'production') === 'development';

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $isLocal ? '' : '.d-cien.es',
            'secure'   => !$isLocal,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', 1800); // 30 min (activación)

        session_start();
    }
}

/**
 * Auth helpers
 */
function isAuthenticated() {
    return !empty($_SESSION['is_authenticated']) && !empty($_SESSION['user_id']);
}

function requireAuth() {
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserEmail() {
    return $_SESSION['email'] ?? null;
}

/**
 * Destruir sesión correctamente
 */
function destroySession() {
    $_SESSION = [];

    if (isset($_COOKIE[session_name()])) {
        $isLocal = (getenv('APP_ENV') ?: 'production') === 'development';
        setcookie(
            session_name(),
            '',
            time() - 3600,
            '/',
            $isLocal ? '' : '.d-cien.es',
            !$isLocal,
            true
        );
    }

    session_destroy();
}
