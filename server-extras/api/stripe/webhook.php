<?php
/**
 * Proxy para el Webhook de Stripe
 * Redirige la ejecución al script del backend seguro fuera de public_html
 */

$backend_root = dirname(dirname(dirname(__DIR__)));
require_once $backend_root . '/dcien-backend/api/stripe/webhook.php';
