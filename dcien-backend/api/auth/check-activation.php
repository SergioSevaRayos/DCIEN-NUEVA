<?php
$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();

jsonResponse([
    'has_activation_token' => isset($_SESSION['activation_token']),
    'instagram_username'   => $_SESSION['instagram_username'] ?? null
], 200);
