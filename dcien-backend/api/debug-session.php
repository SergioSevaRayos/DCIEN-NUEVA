<?php
/**
 * Diagnóstico de sesión — SOLO para desarrollo local. ELIMINAR antes de producción.
 */
$backend_root = dirname(__DIR__);

require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';

startSecureSession();

// Intentar escribir algo en la sesión para comprobar que se guarda
$_SESSION['debug_test'] = time();

$save_path = session_save_path() ?: sys_get_temp_dir();
$session_file = $save_path . '/sess_' . session_id();

header('Content-Type: application/json');

echo json_encode([
    'session_name'        => session_name(),
    'session_id'          => session_id(),
    'session_status'      => session_status(), // 2 = PHP_SESSION_ACTIVE
    'session_save_path'   => $save_path,
    'session_file_exists' => file_exists($session_file),
    'save_path_writable'  => is_writable($save_path),
    'session_data'        => $_SESSION,
    'cookies_received'    => $_COOKIE,
    'app_env'             => getenv('APP_ENV'),
    'is_authenticated'    => !empty($_SESSION['is_authenticated']),
    'origin_received'     => $_SERVER['HTTP_ORIGIN'] ?? '(none)',
], JSON_PRETTY_PRINT);
