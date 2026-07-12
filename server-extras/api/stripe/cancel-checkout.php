<?php
/**
 * Proxy para cancel de Stripe Checkout
 */

$backend_root = dirname(dirname(dirname(__DIR__)));
require_once $backend_root . '/dcien-backend/api/stripe/cancel-checkout.php';
