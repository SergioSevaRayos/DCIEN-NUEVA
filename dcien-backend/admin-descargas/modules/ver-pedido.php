<?php
/**
 * DCIEN - Ver Detalle de Pedido (soporte multi-item carrito)
 */

require_once 'config.php';
$pdo = get_db_connection();

if (!isset($_GET['id'])) { header('Location: ../ordenes/index.php'); exit; }

$order_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email, u.instagram_username,
           s.name as series_name, s.images as series_images, s.price as series_base_price,
           d.code as discount_code, d.type as discount_type, d.value as discount_value
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    LEFT JOIN series s ON s.slug = o.series_slug
    LEFT JOIN discounts d ON d.id = o.discount_id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$pedido = $stmt->fetch();

if (!$pedido) { echo 'Pedido no encontrado'; exit; }

// Obtener items del carrito si es pedido multi-item
$order_items = [];
if ($pedido['is_cart_order']) {
    $stmt2 = $pdo->prepare("
        SELECT oi.*, s.name as series_name, s.images as series_images,
               su.status as unit_status
        FROM order_items oi
        LEFT JOIN series s ON s.slug = oi.series_slug
        LEFT JOIN series_units su ON su.series_slug = oi.series_slug AND su.unit_number = oi.unit_number
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt2->execute([$order_id]);
    $order_items = $stmt2->fetchAll();
} else {
    // Pedido individual - simular array de items
    $su = $pdo->prepare("SELECT status FROM series_units WHERE series_slug=? AND unit_number=?");
    $su->execute([$pedido['series_slug'], $pedido['unit_number']]);
    $unit = $su->fetch();
    $order_items = [[
        'series_slug'  => $pedido['series_slug'],
        'series_name'  => $pedido['series_name'],
        'unit_number'  => $pedido['unit_number'],
        'size'         => $pedido['size'],
        'color'        => $pedido['color'],
        'type'         => $pedido['type'],
        'unit_price'   => $pedido['series_base_price'],
        'unit_status'  => $unit['status'] ?? 'N/A',
        'series_images'=> $pedido['series_images'],
    ]];
}

$shipping = json_decode($pedido['shipping_data'], true);

// Extraer dirección en cualquier formato
$addr_html = '';
if (isset($shipping['shipping_address'])) {
    $a = $shipping['shipping_address'];
    $addr_html = e(($a['first_name']??'').' '.($a['last_name']??'')) . '<br>'
        . e($a['line1']??'') . '<br>'
        . (!empty($a['line2']) ? e($a['line2']).'<br>' : '')
        . e($a['postal_code']??'') . ' ' . e($a['city']??'') . '<br>'
        . e($a['country']??'ES')
        . (!empty($a['phone']) ? '<br><strong>Tel:</strong> '.e($a['phone']) : '');
} elseif (isset($shipping['firstName']) || isset($shipping['address'])) {
    $addr_html = e(($shipping['firstName']??'').' '.($shipping['lastName']??'')) . '<br>'
        . e($shipping['address']??'') . '<br>'
        . (!empty($shipping['addressExtra']) ? e($shipping['addressExtra']).'<br>' : '')
        . e($shipping['postalCode']??'') . ' ' . e($shipping['city']??'') . '<br>'
        . e($shipping['country']??'ES')
        . (!empty($shipping['phone']) ? '<br><strong>Tel:</strong> '.e($shipping['phone']) : '')
        . (!empty($shipping['email']) ? '<br>'.e($shipping['email']) : '');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?= $pedido['order_number'] ?? $order_id ?> - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
    <style>
        .detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:20px}
        .detail-card{background:var(--surface);border:1px solid var(--border);padding:20px;border-radius:var(--radius);box-shadow:var(--shadow)}
        .detail-card h3{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--text-2);border-bottom:1px solid var(--border);padding-bottom:8px;margin-bottom:16px}
        .detail-row{display:flex;padding:7px 0;border-bottom:1px solid var(--border)}
        .detail-row:last-child{border-bottom:none}
        .detail-label{font-weight:600;width:130px;flex-shrink:0;font-size:11px;color:var(--text-2)}
        .detail-value{flex:1;font-size:13px;color:var(--text)}
        .item-card{background:var(--surface-2);border:1px solid var(--border);padding:16px;margin-bottom:12px;border-radius:var(--radius)}
        .item-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
        .item-serie{font-size:14px;font-weight:700;letter-spacing:1px;color:var(--text)}
        .item-num{font-size:22px;font-weight:900;font-family:monospace;color:var(--accent)}
        .item-specs{display:flex;gap:12px;font-size:11px;color:var(--text-2);text-transform:uppercase}
    </style>
</head>
<body>
<div class="container">
    <header class="header">
        <div>
            <h1>PEDIDO #<?= $pedido['order_number'] ?? $order_id ?></h1>
            <p><?= $pedido['is_cart_order'] ? 'Carrito — '.count($order_items).' prenda(s)' : 'Pedido Individual' ?></p>
        </div>
        <div class="header-actions">
            <a href="/admin-descargas/ordenes/index.php">← Volver</a>
        </div>
    </header>

    <!-- ITEMS -->
    <div class="detail-card" style="margin-bottom:20px">
        <h3>🎽 Producto(s)</h3>
        <?php foreach ($order_items as $item): ?>
            <div class="item-card">
                <div class="item-header">
                    <div>
                        <div class="item-serie"><?= e($item['series_name'] ?? $item['series_slug']) ?></div>
                        <div class="item-num">#<?= str_pad($item['unit_number'],3,'0',STR_PAD_LEFT) ?></div>
                        <div class="item-specs">
                            <span>Talla: <?= strtoupper($item['size']) ?></span>
                            <span>Color: <?= ucfirst($item['color']) ?></span>
                            <span>Tipo: <?= ucfirst($item['type']) ?></span>
                        </div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:16px;font-weight:bold">€<?= number_format($item['unit_price'],2) ?></div>
                        <span class="badge <?= ($item['unit_status']??'') === 'sold' ? 'badge-success' : 'badge-secondary' ?>">
                            <?= strtoupper($item['unit_status'] ?? 'N/A') ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="detail-grid">
        <!-- INFO GENERAL -->
        <div class="detail-card">
            <h3>📋 Información General</h3>
            <div class="detail-row"><div class="detail-label">Nº Pedido:</div><div class="detail-value"><strong>#<?= $pedido['order_number'] ?? $pedido['id'] ?></strong></div></div>
            <div class="detail-row"><div class="detail-label">Fecha:</div><div class="detail-value"><?= format_date($pedido['created_at']) ?></div></div>
            <div class="detail-row"><div class="detail-label">Tipo:</div><div class="detail-value"><?= $pedido['is_cart_order'] ? 'Carrito multi-prenda' : 'Individual' ?></div></div>
            <div class="detail-row"><div class="detail-label">Stripe ID:</div><div class="detail-value" style="font-size:10px;word-break:break-all"><?= e($pedido['stripe_session_id']??'N/A') ?></div></div>
        </div>

        <!-- CLIENTE -->
        <div class="detail-card">
            <h3>👤 Cliente</h3>
            <div class="detail-row"><div class="detail-label">Username:</div><div class="detail-value"><?= e($pedido['username']?:'N/A') ?></div></div>
            <div class="detail-row"><div class="detail-label">Email:</div><div class="detail-value"><?= e($pedido['email']??'N/A') ?></div></div>
            <div class="detail-row"><div class="detail-label">Instagram:</div><div class="detail-value">@<?= e($pedido['instagram_username']?:'N/A') ?></div></div>
        </div>

        <!-- PRECIO -->
        <div class="detail-card">
            <h3>💰 Precio</h3>
            <?php if ($pedido['subtotal']): ?>
            <div class="detail-row"><div class="detail-label">Subtotal:</div><div class="detail-value">€<?= number_format($pedido['subtotal'],2) ?></div></div>
            <?php endif; ?>
            <?php if ($pedido['discount_amount'] > 0): ?>
            <div class="detail-row"><div class="detail-label">Descuento:</div><div class="detail-value" style='color:var(--sent)'>-€<?= number_format($pedido['discount_amount'],2) ?><?= $pedido['discount_code'] ? ' ('.$pedido['discount_code'].')' : '' ?></div></div>
            <?php endif; ?>
            <?php if ($pedido['shipping_fee'] >= 0): ?>
            <div class="detail-row"><div class="detail-label">Envío:</div><div class="detail-value"><?= $pedido['shipping_fee'] == 0 ? 'GRATIS' : '€'.number_format($pedido['shipping_fee'],2) ?></div></div>
            <?php endif; ?>
            <div class="detail-row"><div class="detail-label"><strong>TOTAL:</strong></div><div class="detail-value"><strong style="font-size:18px">€<?= number_format($pedido['price'],2) ?></strong></div></div>
        </div>

        <!-- DIRECCIÓN -->
        <?php if ($addr_html): ?>
        <div class="detail-card">
            <h3>📦 Dirección de Envío</h3>
            <div style="line-height:1.8;font-size:13px"><?= $addr_html ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $stmt_docs = $pdo->prepare("SELECT * FROM order_documents WHERE order_id = ? ORDER BY uploaded_at ASC");
    $stmt_docs->execute([$order_id]);
    $documentos = $stmt_docs->fetchAll();
    ?>

    <?php if (!empty($documentos)): ?>
    <div class="detail-card" style="margin-bottom:20px">
        <h3>📎 Documentos adjuntos</h3>
        <?php foreach ($documentos as $doc): ?>
        <div class="detail-row" style="align-items:center;">
            <div class="detail-label" style="width:auto;flex:1;">
                <a href="../ordenes/docs/<?= $order_id ?>/<?= e($doc['filename']) ?>" target="_blank"
                   style="color:var(--accent);text-decoration:underline;font-size:13px;">
                    <?= e($doc['original_name']) ?>
                </a>
                <span style="font-size:10px;color:var(--text-2);margin-left:8px;"><?= format_date($doc['uploaded_at']) ?> · <?= round($doc['size_bytes']/1024) ?> KB</span>
            </div>
            <form method="POST" action="../ordenes/index.php" style="margin:0;" onsubmit="return confirm('¿Eliminar este documento?');">
                <input type="hidden" name="action" value="eliminar_documento">
                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                <button type="submit" class="btn btn-small btn-danger" style="padding:2px 8px;font-size:11px;">✕</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($pedido['shipping_company']) || !empty($pedido['tracking_id'])): ?>
    <div class="detail-card" style="margin-bottom:20px">
        <h3>🚚 Información de envío</h3>
        <?php if (!empty($pedido['shipping_company'])): ?>
        <div class="detail-row"><div class="detail-label">Transportista:</div><div class="detail-value"><strong><?= e($pedido['shipping_company']) ?></strong></div></div>
        <?php endif; ?>
        <?php if (!empty($pedido['tracking_id'])): ?>
        <div class="detail-row"><div class="detail-label">Nº seguimiento:</div><div class="detail-value"><strong style="font-family:monospace"><?= e($pedido['tracking_id']) ?></strong></div></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:12px;margin-top:20px">
        <?php if (!in_array($pedido['status'], ['enviado', 'cancelled'])): ?>
        <form method="POST" action="../ordenes/index.php">
            <input type="hidden" name="action" value="generar_orden">
            <input type="hidden" name="order_id" value="<?= $pedido['id'] ?>">
            <button type="submit" class="btn">📄 Generar Orden</button>
        </form>
        <?php endif; ?>
        <form method="POST" action="../ordenes/index.php" enctype="multipart/form-data" id="form-subir-doc-vp">
            <input type="hidden" name="action" value="subir_documento">
            <input type="hidden" name="order_id" value="<?= $pedido['id'] ?>">
            <label class="btn btn-secondary" style="cursor:pointer;margin:0;">
                📎 Adjuntar documento
                <input type="file" name="documento" accept=".pdf,.jpg,.jpeg,.png,.webp"
                    style="display:none;" onchange="document.getElementById('form-subir-doc-vp').submit()">
            </label>
        </form>
        <a href="/admin-descargas/ordenes/index.php" class="btn btn-secondary">← Volver</a>
    </div>

    <footer class="footer"><p>DCIEN Detalle de Pedido</p></footer>
</div>
</body>
</html>
