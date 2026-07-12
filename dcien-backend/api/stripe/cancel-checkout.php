<?php
/**
 * Limpia un pedido pendiente cuando el usuario cancela el pago en Stripe.
 * Stripe redirige el navegador aquí con ?session_id=cs_xxx
 */

$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();

$session_id  = $_GET['session_id'] ?? '';
$app_url     = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?? 'https://d-cien.es', '/');
$redirect_to = $app_url . '/series-activas';

if ($session_id) {
    try {
        $order = queryOne(
            "SELECT id, series_slug, unit_number, is_cart_order
             FROM orders
             WHERE stripe_session_id = :sid AND status IN ('pending','pendiente')",
            ['sid' => $session_id]
        );

        if ($order) {
            $oid = $order['id'];

            if ($order['is_cart_order']) {
                $items = queryAll(
                    "SELECT series_slug, unit_number FROM order_items WHERE order_id = :oid",
                    ['oid' => $oid]
                );
                foreach ($items as $item) {
                    query(
                        "UPDATE series_units
                         SET status='available', reserved_by=NULL, checkout_started_at=NULL
                         WHERE series_slug=:slug AND unit_number=:num",
                        ['slug' => $item['series_slug'], 'num' => $item['unit_number']]
                    );
                }
                query("DELETE FROM order_items WHERE order_id = :oid", ['oid' => $oid]);
            } else {
                query(
                    "UPDATE series_units
                     SET status='available', reserved_by=NULL, checkout_started_at=NULL
                     WHERE series_slug=:slug AND unit_number=:num",
                    ['slug' => $order['series_slug'], 'num' => $order['unit_number']]
                );
            }

            query("DELETE FROM orders WHERE id = :oid", ['oid' => $oid]);
        }
    } catch (Exception $e) {
        logError('cancel-checkout error', ['error' => $e->getMessage(), 'session_id' => $session_id]);
    }
}

header('Location: ' . $redirect_to);
exit;
