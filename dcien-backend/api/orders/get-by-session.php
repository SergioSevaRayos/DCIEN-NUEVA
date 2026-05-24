<?php
/**
 * API: Obtener pedido por session_id de Stripe
 * GET /api/orders/get-by-session.php?session_id=cs_...
 */
$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Metodo no permitido', 405);
}

$session_id = $_GET['session_id'] ?? '';
if (empty($session_id)) {
    jsonError('session_id requerido', 400);
}

try {
    $user_id = getUserId();

    $order = queryOne(
        "SELECT o.*, s.name as series_name, d.code as discount_code
         FROM orders o
         LEFT JOIN series s ON s.slug = o.series_slug
         LEFT JOIN discounts d ON d.id = o.discount_id
         WHERE o.stripe_session_id = :session_id
           AND o.user_id = :user_id
         LIMIT 1",
        ['session_id' => $session_id, 'user_id' => $user_id]
    );

    if (!$order) {
        jsonError('Pedido no encontrado', 404);
    }

    // Si es pedido de carrito obtener items
    $items = [];
    if ($order['is_cart_order']) {
        $items = queryAll(
            "SELECT oi.*, s.name as series_name
             FROM order_items oi
             LEFT JOIN series s ON s.slug = oi.series_slug
             WHERE oi.order_id = :oid
             ORDER BY oi.id ASC",
            ['oid' => $order['id']]
        );
    }

    jsonSuccess('Pedido encontrado', [
        'order' => $order,
        'items' => $items,
        'is_cart' => (bool)$order['is_cart_order']
    ]);

} catch (Exception $e) {
    logError('Error getting order by session', ['error' => $e->getMessage()]);
    jsonError('Error del servidor', 500);
}
