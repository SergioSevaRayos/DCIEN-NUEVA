<?php
/**
 * API: Logout de Usuario
 */

$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();

// Destruir sesión
destroySession();

jsonSuccess('Logout exitoso');
