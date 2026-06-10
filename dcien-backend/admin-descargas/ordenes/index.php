<?php
/**
 * DCIEN — Pipeline de Órdenes
 */

require_once '../modules/config.php';
$pdo = get_db_connection();

$message        = '';
$estado_actual  = $_GET['estado'] ?? 'nuevos';
$ESTADOS_NUEVOS = ['paid', 'pending', 'pendiente'];

// ═══════════════════════════════════════════════
// HELPERS — dirección de envío
// ═══════════════════════════════════════════════

function parse_shipping(array $shipping): array {
    if (isset($shipping['shipping_address'])) {
        $a = $shipping['shipping_address'];
        return [
            'nombre' => trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
            'linea1' => $a['line1'] ?? '',
            'linea2' => $a['line2'] ?? '',
            'cp'     => $a['postal_code'] ?? '',
            'ciudad' => $a['city'] ?? '',
            'prov'   => $a['province'] ?? '',
            'pais'   => $a['country'] ?? 'ES',
            'tel'    => $a['phone'] ?? '',
            'email'  => $a['email'] ?? '',
        ];
    }
    return [
        'nombre' => trim(($shipping['firstName'] ?? '') . ' ' . ($shipping['lastName'] ?? '')),
        'linea1' => $shipping['address'] ?? '',
        'linea2' => $shipping['addressExtra'] ?? '',
        'cp'     => $shipping['postalCode'] ?? '',
        'ciudad' => $shipping['city'] ?? '',
        'prov'   => $shipping['province'] ?? '',
        'pais'   => $shipping['country'] ?? 'ES',
        'tel'    => $shipping['phone'] ?? '',
        'email'  => $shipping['email'] ?? '',
    ];
}

function addr_html(array $addr): string {
    $parts = array_filter([
        e($addr['nombre']),
        e($addr['linea1']),
        $addr['linea2'] ? e($addr['linea2']) : '',
        e($addr['cp']) . ' ' . e($addr['ciudad']),
        $addr['prov'] ? e($addr['prov']) : '',
        e($addr['pais']),
        $addr['tel']   ? '<strong>Tel:</strong> ' . e($addr['tel'])   : '',
        $addr['email'] ? e($addr['email']) : '',
    ]);
    return implode('<br>', $parts);
}

// ═══════════════════════════════════════════════
// GENERACIÓN HTML ORDEN DE TRABAJO
// ═══════════════════════════════════════════════

function get_order_items(PDO $pdo, array $pedido): array {
    if (!empty($pedido['is_cart_order'])) {
        $st = $pdo->prepare("
            SELECT oi.*, s.name as series_name
            FROM order_items oi
            LEFT JOIN series s ON s.slug = oi.series_slug
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $st->execute([$pedido['id']]);
        $items = $st->fetchAll();
        if (!empty($items)) return $items;
    }
    return [[
        'series_slug' => $pedido['series_slug'],
        'series_name' => $pedido['series_name'] ?? '',
        'unit_number' => $pedido['unit_number'],
        'size'        => $pedido['size'],
        'color'       => $pedido['color'],
        'type'        => $pedido['type'],
        'unit_price'  => $pedido['price'],
    ]];
}

function generar_orden_trabajo(array $pedido, PDO $pdo): array {
    $num_pedido = $pedido['order_number'] ?? $pedido['id'];
    $items    = get_order_items($pdo, $pedido);
    $shipping = json_decode($pedido['shipping_data'] ?? '{}', true) ?: [];
    $addr     = parse_shipping($shipping);
    $dir_html = addr_html($addr);

    $cliente_username = e($pedido['username'] ?: 'N/A');
    $cliente_email    = e($pedido['email']    ?: 'N/A');
    $cliente_ig       = e($pedido['instagram_username'] ?: 'N/A');

    // Bloque de productos
    $productos_html = '';
    foreach ($items as $item) {
        $color_slug  = strtolower(trim($item['color'] ?? ''));
        $serie_slug  = $item['series_slug'];
        $base        = "https://d-cien.es/images/series/$serie_slug/";
        $img_front   = $color_slug === 'blanco' ? $base . 'main.png'     : $base . 'detail-2.png';
        $img_back    = $color_slug === 'blanco' ? $base . 'detail-1.png' : $base . 'detail-3.png';
        $serie_upper = strtoupper($item['series_name'] ?: $item['series_slug']);
        $unit_pad    = str_pad($item['unit_number'], 3, '0', STR_PAD_LEFT);

        $productos_html .= "
        <div class='block-product'>
            <div class='image-pair'>
                <img src='$img_front' class='series-image' onerror=\"this.src='https://via.placeholder.com/150?text=FRONT'\">
                <img src='$img_back'  class='series-image' onerror=\"this.src='https://via.placeholder.com/150?text=BACK'\">
            </div>
            <div class='product-details'>
                <div class='serie'>$serie_upper</div>
                <div class='unit'>#$unit_pad</div>
                <div class='product-specs'>
                    <div class='spec-box'><div class='spec-label'>Talla</div><div class='spec-value'>" . strtoupper($item['size']) . "</div></div>
                    <div class='spec-box'><div class='spec-label'>Color</div><div class='spec-value'>" . ucfirst($item['color']) . "</div></div>
                    <div class='spec-box'><div class='spec-label'>Corte</div><div class='spec-value'>" . ucfirst($item['type']) . "</div></div>
                </div>
            </div>
        </div>";
    }

    // Checklist
    $checklist = '';
    $step = 1;
    foreach ($items as $item) {
        $checklist .= "<li>&#9744; $step. Verificar stock: <strong>" . strtoupper($item['size']) . " / " . ucfirst($item['color']) . " / " . ucfirst($item['type']) . "</strong> — " . strtoupper($item['series_name'] ?: $item['series_slug']) . " #" . str_pad($item['unit_number'], 3, '0', STR_PAD_LEFT) . "</li>";
        $step++;
    }
    $checklist .= "<li>&#9744; $step. Control de calidad final</li>";
    $step++;
    $checklist .= "<li>&#9744; $step. Empaquetar con tarjeta(s) de autenticidad DCIEN</li>";
    $step++;
    $checklist .= "<li>&#9744; $step. Imprimir etiqueta y preparar envío a <strong>" . e($addr['nombre']) . "</strong></li>";

    $html = "<!DOCTYPE html><html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Orden #{$num_pedido} - DCIEN</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, sans-serif; font-size:10px; line-height:1.3; padding:10mm; }
        .header { text-align:center; border-bottom:3px solid #000; padding-bottom:6px; margin-bottom:10px; }
        .header h1 { font-size:24px; font-weight:900; letter-spacing:4px; }
        .header p { font-size:9px; text-transform:uppercase; letter-spacing:2px; margin-top:3px; }
        .block-product { display:grid; grid-template-columns:320px 1fr; gap:10px; margin-bottom:12px; page-break-inside:avoid; }
        .image-pair { display:grid; grid-template-columns:1fr 1fr; gap:5px; }
        .series-image { width:100%; height:155px; object-fit:contain; border:1px solid #000; background:#fff; }
        .product-details { border:2px solid #000; padding:10px; display:flex; flex-direction:column; justify-content:center; }
        .product-details .serie { font-size:16px; font-weight:900; letter-spacing:1px; margin-bottom:8px; text-align:center; }
        .product-details .unit { font-size:36px; font-weight:900; font-family:monospace; text-align:center; margin:8px 0; letter-spacing:2px; }
        .product-specs { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; margin-top:8px; }
        .spec-box { text-align:center; padding:5px; background:#f0f0f0; border:1px solid #ccc; }
        .spec-label { font-size:7px; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px; color:#666; }
        .spec-value { font-size:12px; font-weight:900; text-transform:uppercase; }
        .block-shipping { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px; page-break-inside:avoid; }
        .info-box { border:2px solid #000; padding:8px; }
        .info-box h3 { font-size:9px; text-transform:uppercase; letter-spacing:1px; border-bottom:2px solid #000; padding-bottom:3px; margin-bottom:6px; }
        .info-row { display:flex; margin-bottom:3px; }
        .info-label { font-weight:bold; width:70px; flex-shrink:0; font-size:9px; }
        .info-value { flex:1; font-size:9px; }
        .shipping-address { line-height:1.6; font-size:9px; }
        .checklist { border:3px solid #000; padding:8px; page-break-inside:avoid; }
        .checklist h3 { font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; border-bottom:1px solid #000; padding-bottom:3px; }
        .checklist ul { list-style:none; padding:0; }
        .checklist li { padding:4px 0; font-size:9px; border-bottom:1px dashed #ddd; }
        .checklist li:last-child { border-bottom:none; }
        .footer { margin-top:10px; padding-top:6px; border-top:2px solid #000; text-align:center; font-size:7px; color:#666; }
        @media print { @page { margin:0; size:A4 portrait; } body { padding:8mm; } }
    </style>
</head>
<body>
    <div class='header'>
        <h1>DCIEN</h1>
        <p>Orden de Producción #{$num_pedido}</p>
    </div>

    $productos_html

    <div class='block-shipping'>
        <div class='info-box'>
            <h3>PERFIL CLIENTE</h3>
            <div class='info-row'><div class='info-label'>Usuario:</div><div class='info-value'>$cliente_username</div></div>
            <div class='info-row'><div class='info-label'>Email:</div><div class='info-value'>$cliente_email</div></div>
            <div class='info-row'><div class='info-label'>Instagram:</div><div class='info-value'>@$cliente_ig</div></div>
        </div>
        <div class='info-box'>
            <h3>DIRECCIÓN DE ENVÍO</h3>
            <div class='shipping-address'>$dir_html</div>
        </div>
    </div>

    <div class='checklist'>
        <h3>CHECKLIST DE PRODUCCIÓN</h3>
        <ul>$checklist</ul>
    </div>

    <div class='footer'>
        DCIEN · Orden generada el " . date('d/m/Y H:i:s') . "
    </div>
</body></html>";

    $filename = "orden_{$pedido['id']}_" . date('Ymd_His') . ".html";
    $filepath = __DIR__ . "/$filename";
    file_put_contents($filepath, $html);

    return [
        'success'  => true,
        'filepath' => $filepath,
        'filename' => $filename,
        'url'      => "https://d-cien.es/admin-descargas/ordenes/$filename",
    ];
}

// ═══════════════════════════════════════════════
// EMAILS
// ═══════════════════════════════════════════════

function email_produccion(array $pedido, array $result): bool {
    $num_pedido = $pedido['order_number'] ?? $pedido['id'];
    $subject = "Nueva Orden de Producción #{$num_pedido} — DCIEN";
    $html = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
        <div style='background:#000;color:#fff;padding:30px;text-align:center;'>
            <h1 style='font-size:32px;letter-spacing:6px;margin:0;'>DCIEN</h1>
            <p style='margin:8px 0 0;font-size:12px;letter-spacing:3px;text-transform:uppercase;'>Nueva Orden de Producción</p>
        </div>
        <div style='padding:30px;'>
            <div style='background:#000;color:#fff;padding:20px;text-align:center;font-size:42px;font-weight:900;font-family:monospace;letter-spacing:4px;margin:0 0 20px;'>
                #{$num_pedido}
            </div>
            <p style='margin:0 0 20px;color:#333;'>Se ha generado la orden de producción. Accede al documento para imprimir.</p>
            <a href='{$result['url']}' style='display:block;background:#000;color:#fff;padding:14px;text-align:center;text-decoration:none;font-weight:bold;letter-spacing:2px;text-transform:uppercase;'>
                Ver Orden de Producción
            </a>
        </div>
    </div>";
    return sendAdminMail(ADMIN_EMAIL, $subject, $html);
}

function email_enviado_cliente(array $pedido, array $items, array $addr): bool {
    $num_pedido = $pedido['order_number'] ?? $pedido['id'];
    $to = $addr['email'] ?: ($pedido['email'] ?? '');
    if (empty($to)) return false;

    $nombre = strtoupper($addr['nombre'] ?: ($pedido['username'] ?: 'Cliente'));

    $items_html = '';
    foreach ($items as $item) {
        $items_html .= "
        <div style='background:#f9f9f9;border-left:3px solid #000;padding:12px;margin:8px 0;'>
            <strong>" . strtoupper($item['series_name'] ?: $item['series_slug']) . " #" . str_pad($item['unit_number'], 3, '0', STR_PAD_LEFT) . "</strong><br>
            <span style='font-size:13px;color:#555;'>" . strtoupper($item['size']) . " · " . ucfirst($item['color']) . " · " . ucfirst($item['type']) . "</span>
        </div>";
    }

    $subject = "Tu pedido DCIEN #{$num_pedido} va en camino";
    $html = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;'>
        <div style='background:#000;color:#fff;padding:30px;text-align:center;'>
            <h1 style='font-size:28px;letter-spacing:6px;margin:0;font-weight:900;'>DCIEN</h1>
        </div>
        <div style='padding:30px;text-align:center;color:#111;'>
            <div style='font-size:40px;margin-bottom:16px;'>📦</div>
            <h2 style='margin:0 0 12px;'>Hola, $nombre</h2>
            <p style='color:#555;margin:0 0 24px;'>Tu pedido <strong>#{$num_pedido}</strong> ya ha salido de nuestras instalaciones y está en manos del transportista.</p>
            $items_html
            <p style='color:#777;font-size:13px;margin:24px 0;'>Si tienes alguna duda responde a este correo.</p>
            <a href='https://d-cien.es' style='display:inline-block;background:#000;color:#fff;padding:14px 32px;text-decoration:none;font-weight:bold;letter-spacing:2px;text-transform:uppercase;'>
                Visitar Tienda
            </a>
        </div>
        <div style='background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#888;border-top:1px solid #eee;'>
            Gracias por confiar en DCIEN.
        </div>
    </div>";

    return sendAdminMail($to, $subject, $html);
}

// ═══════════════════════════════════════════════
// ACCIONES POST
// ═══════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generar_orden' && isset($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        $stmt = $pdo->prepare("
            SELECT o.*, u.username, u.email, u.instagram_username,
                   s.name as series_name
            FROM orders o
            LEFT JOIN users u  ON u.id = o.user_id
            LEFT JOIN series s ON s.slug = o.series_slug
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            $result = generar_orden_trabajo($pedido, $pdo);
            if ($result['success']) {
                $pdo->prepare("UPDATE orders SET status = 'produccion' WHERE id = ?")->execute([$order_id]);
                $link = "<a href='{$result['url']}' target='_blank' style='color:#16a34a;text-decoration:underline;'>Abrir documento</a>";
                $message = show_message('success', "✓ Orden #{$order_id} generada y movida a Producción. $link");
            } else {
                $message = show_message('error', "Error al generar orden #{$order_id}.");
            }
        }
    }

    if ($action === 'generar_multiples' && isset($_POST['order_ids'])) {
        $order_ids = array_filter(array_map('intval', explode(',', $_POST['order_ids'])));
        $ok = 0; $ko = 0;
        foreach ($order_ids as $order_id) {
            $stmt = $pdo->prepare("
                SELECT o.*, u.username, u.email, u.instagram_username, s.name as series_name
                FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN series s ON s.slug = o.series_slug
                WHERE o.id = ?
            ");
            $stmt->execute([$order_id]);
            $pedido = $stmt->fetch();
            if ($pedido) {
                $result = generar_orden_trabajo($pedido, $pdo);
                if ($result['success']) {
                    $pdo->prepare("UPDATE orders SET status = 'produccion' WHERE id = ?")->execute([$order_id]);
                    $ok++;
                } else { $ko++; }
            }
        }
        $message = show_message('success', "✓ $ok orden(es) generadas y movidas a Producción." . ($ko ? " $ko errores." : ''));
    }

    if ($action === 'marcar_enviado' && isset($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        $pdo->prepare("UPDATE orders SET status = 'enviado' WHERE id = ?")->execute([$order_id]);

        $stmt = $pdo->prepare("
            SELECT o.*, u.username, u.email, u.instagram_username, s.name as series_name
            FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN series s ON s.slug = o.series_slug
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            $items   = get_order_items($pdo, $pedido);
            $ship    = json_decode($pedido['shipping_data'] ?? '{}', true) ?: [];
            $addr    = parse_shipping($ship);
            $sent    = email_enviado_cliente($pedido, $items, $addr);
            $message = show_message('success', "✓ Pedido #{$order_id} marcado como enviado." . ($sent ? ' Email enviado al cliente.' : ' (Email no enviado — revisa la dirección.)'));
        }
    }

    if ($action === 'eliminar_pedido' && isset($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $pedido_del = $stmt->fetch();

        if ($pedido_del) {
            if ($pedido_del['is_cart_order']) {
                $st = $pdo->prepare("SELECT series_slug, unit_number FROM order_items WHERE order_id = ?");
                $st->execute([$order_id]);
                foreach ($st->fetchAll() as $item) {
                    $pdo->prepare("UPDATE series_units SET status = 'available' WHERE series_slug = ? AND unit_number = ?")->execute([$item['series_slug'], $item['unit_number']]);
                }
            } else {
                $pdo->prepare("UPDATE series_units SET status = 'available' WHERE series_slug = ? AND unit_number = ?")->execute([$pedido_del['series_slug'], $pedido_del['unit_number']]);
            }
            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$order_id]);
            $message = show_message('success', "✓ Pedido #{$order_id} eliminado y unidades liberadas.");
        }
    }

    if ($action === 'exportar_csv') {
        $estado_csv  = $_POST['estado'] ?? 'nuevos';
        $where_parts = [];
        $params      = [];

        if ($estado_csv === 'nuevos') {
            $where_parts[] = "o.status IN ('paid','pending','pendiente')";
        } else {
            $where_parts[] = "o.status = :estado";
            $params['estado'] = $estado_csv;
        }
        if (!empty($_POST['serie']))  { $where_parts[] = "o.series_slug = :serie";  $params['serie']  = $_POST['serie']; }
        if (!empty($_POST['desde']))  { $where_parts[] = "o.created_at >= :desde";  $params['desde']  = $_POST['desde'] . ' 00:00:00'; }
        if (!empty($_POST['hasta']))  { $where_parts[] = "o.created_at <= :hasta";  $params['hasta']  = $_POST['hasta'] . ' 23:59:59'; }

        $stmt = $pdo->prepare("
            SELECT o.*, u.username, u.email, s.name as series_name
            FROM orders o
            LEFT JOIN users u  ON u.id = o.user_id
            LEFT JOIN series s ON s.slug = o.series_slug
            WHERE " . implode(' AND ', $where_parts) . "
            ORDER BY o.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pedidos_' . $estado_csv . '_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'Fecha', 'Serie', 'Unidad', 'Talla', 'Color', 'Tipo', 'Cliente', 'Email', 'Instagram', 'Precio', 'Estado', 'Carrito']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['created_at'], $r['series_name'], $r['unit_number'],
                $r['size'], $r['color'], $r['type'],
                $r['username'] ?: 'N/A', $r['email'],
                $r['instagram_username'] ?: 'N/A',
                $r['price'], strtoupper($r['status']),
                $r['is_cart_order'] ? 'Sí' : 'No',
            ]);
        }
        fclose($out);
        exit;
    }
}

// ═══════════════════════════════════════════════
// QUERIES — listado y contadores
// ═══════════════════════════════════════════════

$where_parts = [];
$params      = [];

if ($estado_actual === 'nuevos') {
    $where_parts[] = "o.status IN ('paid','pending','pendiente')";
} else {
    $where_parts[] = "o.status = :estado";
    $params['estado'] = $estado_actual;
}

if (!empty($_GET['serie'])) {
    $where_parts[] = "o.series_slug = :serie";
    $params['serie'] = $_GET['serie'];
}
if (!empty($_GET['desde'])) {
    $where_parts[] = "o.created_at >= :desde";
    $params['desde'] = $_GET['desde'] . ' 00:00:00';
}
if (!empty($_GET['hasta'])) {
    $where_parts[] = "o.created_at <= :hasta";
    $params['hasta'] = $_GET['hasta'] . ' 23:59:59';
}
if (!empty($_GET['search'])) {
    $where_parts[] = "(u.username LIKE :s1 OR u.email LIKE :s2 OR o.id = :sid)";
    $params['s1']  = '%' . $_GET['search'] . '%';
    $params['s2']  = '%' . $_GET['search'] . '%';
    $params['sid'] = is_numeric($_GET['search']) ? (int)$_GET['search'] : 0;
}

$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email, u.instagram_username, s.name as series_name
    FROM orders o
    LEFT JOIN users u  ON u.id = o.user_id
    LEFT JOIN series s ON s.slug = o.series_slug
    WHERE " . implode(' AND ', $where_parts) . "
    ORDER BY o.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$series     = $pdo->query("SELECT slug, name FROM series ORDER BY name")->fetchAll();
$conteo_raw = $pdo->query("SELECT status, COUNT(*) as qty FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$cant_nuevos    = ($conteo_raw['paid'] ?? 0) + ($conteo_raw['pending'] ?? 0) + ($conteo_raw['pendiente'] ?? 0);
$cant_prod      = $conteo_raw['produccion'] ?? 0;
$cant_enviados  = $conteo_raw['enviado'] ?? 0;
$cant_cancelado = $conteo_raw['cancelled'] ?? 0;

$total_mostrados = count($pedidos);
$suma_total      = array_sum(array_column($pedidos, 'price'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Pipeline de Órdenes — DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
    <style>
        .pipeline-tabs { display:flex; gap:8px; margin-bottom:24px; border-bottom:1px solid var(--border); padding-bottom:0; overflow-x:auto; }
        .tab-btn { padding:12px 20px; font-size:11px; font-weight:900; letter-spacing:2px; text-transform:uppercase; text-decoration:none; color:var(--text-2); border-bottom:3px solid transparent; white-space:nowrap; display:flex; align-items:center; gap:8px; transition:all .2s; font-family:'Courier New',monospace; }
        .tab-btn:hover { color:var(--text); }
        .tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
        .tab-count { background:var(--border); color:var(--text-2); padding:2px 8px; font-size:10px; border-radius:2px; }
        .tab-btn.active .tab-count { background:var(--accent); color:var(--accent-text); }

        .summary-bar { display:flex; gap:20px; padding:16px 20px; background:var(--surface); border:1px solid var(--border); margin-bottom:20px; font-size:12px; flex-wrap:wrap; }
        .summary-bar span { color:var(--text-2); }
        .summary-bar strong { color:var(--accent); }

        .filters-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:16px; }
        .filter-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:space-between; align-items:center; padding-top:16px; border-top:1px solid var(--border); }

        table { min-width:860px; }
        .col-check { width:40px; text-align:center; }
        .col-id { width:90px; }
        .col-producto { min-width:200px; }
        .col-cliente { min-width:140px; }
        .col-precio { width:80px; text-align:right; }
        .col-audit { width:90px; }
        .col-docs { width:80px; }
        .col-actions { width:140px; }

        .pill { display:inline-block; padding:3px 8px; font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:1px; border-radius:2px; }
        .pill-ok   { background:var(--sent-bg); color:var(--sent); border:1px solid var(--sent-border); }
        .pill-warn { background:var(--new-bg);  color:var(--new);  border:1px solid var(--new-border); }
        .pill-cart { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }

        .item-row { font-size:10px; color:var(--text-2); line-height:1.7; }
        .item-row strong { color:var(--text); font-size:11px; }

        .btn-group { display:flex; gap:6px; flex-wrap:wrap; }
        .checkbox-col { text-align:center; }

        @media (max-width:768px) {
            .filters-row { grid-template-columns:1fr; }
            .filter-actions { flex-direction:column; }
            .filter-actions .btn { width:100%; text-align:center; }
            .pipeline-tabs { gap:4px; }
            .tab-btn { padding:10px 12px; font-size:10px; }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
    <div class="main-content">
        <div class="container">

    <header class="header">
        <div>
            <h1>PIPELINE DE ÓRDENES</h1>
            <p>Control de fases de producción</p>
        </div>
        <div class="header-actions">
            <a href="/admin-descargas/">← Dashboard</a>
        </div>
    </header>

    <?php if ($message) echo $message; ?>

    <!-- TABS -->
    <nav class="pipeline-tabs">
        <a href="?estado=nuevos" class="tab-btn <?= $estado_actual === 'nuevos' ? 'active' : '' ?>">
            Nuevos <span class="tab-count"><?= $cant_nuevos ?></span>
        </a>
        <a href="?estado=produccion" class="tab-btn <?= $estado_actual === 'produccion' ? 'active' : '' ?>">
            Producción <span class="tab-count"><?= $cant_prod ?></span>
        </a>
        <a href="?estado=enviado" class="tab-btn <?= $estado_actual === 'enviado' ? 'active' : '' ?>">
            Enviados <span class="tab-count"><?= $cant_enviados ?></span>
        </a>
        <?php if ($cant_cancelado > 0): ?>
        <a href="?estado=cancelled" class="tab-btn <?= $estado_actual === 'cancelled' ? 'active' : '' ?>">
            Cancelados <span class="tab-count"><?= $cant_cancelado ?></span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- FILTROS -->
    <div class="filters">
        <form method="GET">
            <input type="hidden" name="estado" value="<?= e($estado_actual) ?>">
            <div class="filters-row">
                <div class="form-group" style="margin:0;">
                    <label>Serie</label>
                    <select name="serie">
                        <option value="">Todas</option>
                        <?php foreach ($series as $s): ?>
                            <option value="<?= e($s['slug']) ?>" <?= ($_GET['serie'] ?? '') === $s['slug'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?= e($_GET['desde'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Buscar</label>
                    <input type="text" name="search" placeholder="ID, email, usuario..." value="<?= e($_GET['search'] ?? '') ?>">
                </div>
            </div>
            <div class="filter-actions">
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" class="btn">Filtrar</button>
                    <a href="?estado=<?= e($estado_actual) ?>" class="btn btn-secondary">Limpiar</a>
                    <button type="button" onclick="exportarCSV()" class="btn btn-secondary">CSV</button>
                </div>
                <?php if ($estado_actual === 'nuevos'): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" onclick="seleccionarTodos()" class="btn btn-secondary">Seleccionar todos</button>
                    <button type="button" onclick="generarMultiples()" class="btn">A Producción</button>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- RESUMEN -->
    <div class="summary-bar">
        <span>Mostrando <strong><?= $total_mostrados ?></strong> pedidos</span>
        <span>Total fase: <strong><?= format_price($suma_total) ?></strong></span>
        <?php if (!empty($_GET['serie']) || !empty($_GET['search']) || !empty($_GET['desde'])): ?>
            <span style="color:var(--prod);">— filtros activos</span>
        <?php endif; ?>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <table id="ordersTable">
            <thead>
                <tr>
                    <th class="col-check"><input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="transform:scale(1.2);"></th>
                    <th class="col-id">ID / Fecha</th>
                    <th class="col-producto">Producto</th>
                    <th class="col-cliente">Cliente</th>
                    <th class="col-precio">Precio</th>
                    <th class="col-audit">Auditoría</th>
                    <th class="col-docs">Docs</th>
                    <th class="col-actions">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pedidos)): ?>
                <tr><td colspan="8" style="text-align:center;padding:48px;color:var(--text-2);">No hay pedidos en esta fase.</td></tr>
            <?php else: foreach ($pedidos as $p):
                $shipping_raw = json_decode($p['shipping_data'] ?? '{}', true) ?: [];
                $addr         = parse_shipping($shipping_raw);

                // Auditoría
                $warnings = [];
                $tiene_dir = !empty($addr['linea1']) || !empty($addr['nombre']);
                if (!$tiene_dir) $warnings[] = 'Sin dirección';
                if (isset($shipping_raw['payment']['status']) && $shipping_raw['payment']['status'] !== 'paid') {
                    $warnings[] = 'Pago no confirmado';
                }

                // Items del carrito si aplica
                $cart_items = [];
                if ($p['is_cart_order']) {
                    $st2 = $pdo->prepare("SELECT oi.*, s.name as series_name FROM order_items oi LEFT JOIN series s ON s.slug = oi.series_slug WHERE oi.order_id = ? ORDER BY oi.id ASC");
                    $st2->execute([$p['id']]);
                    $cart_items = $st2->fetchAll();
                }

                $archivos = glob(__DIR__ . "/orden_{$p['id']}_*.html") ?: [];
            ?>
                <tr>
                    <td class="col-check checkbox-col">
                        <input type="checkbox" class="order-checkbox" value="<?= $p['id'] ?>" style="transform:scale(1.2);">
                    </td>
                    <td class="col-id">
                        <strong style="color:var(--accent);">#<?= $p['order_number'] ?? $p['id'] ?></strong><br>
                        <span style="font-size:10px;color:var(--text-2);"><?= format_date($p['created_at'], 'd/m H:i') ?></span>
                    </td>
                    <td class="col-producto">
                        <?php if ($p['is_cart_order'] && !empty($cart_items)): ?>
                            <span class="pill pill-cart">Carrito (<?= count($cart_items) ?>)</span><br>
                            <div class="item-row">
                            <?php foreach ($cart_items as $ci): ?>
                                <strong><?= e($ci['series_name'] ?: $ci['series_slug']) ?> #<?= str_pad($ci['unit_number'], 3, '0', STR_PAD_LEFT) ?></strong>
                                — <?= strtoupper($ci['size']) ?>/<?= ucfirst($ci['color']) ?><br>
                            <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="item-row">
                                <strong><?= e($p['series_name'] ?: $p['series_slug']) ?></strong><br>
                                #<?= str_pad($p['unit_number'], 3, '0', STR_PAD_LEFT) ?>
                                · <?= strtoupper($p['size']) ?> / <?= ucfirst($p['color']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="col-cliente">
                        <span style="font-size:12px;"><?= e($p['username'] ?: '') ?></span><br>
                        <span style="font-size:10px;color:var(--text-2);"><?= e($p['email']) ?></span>
                    </td>
                    <td class="col-precio">
                        <strong style="color:var(--accent);"><?= format_price($p['price']) ?></strong>
                    </td>
                    <td class="col-audit">
                        <?php if (empty($warnings)): ?>
                            <span class="pill pill-ok">✓ OK</span>
                        <?php else: foreach ($warnings as $w): ?>
                            <span class="pill pill-warn"><?= e($w) ?></span><br>
                        <?php endforeach; endif; ?>
                    </td>
                    <td class="col-docs">
                        <?php if (!empty($archivos)): foreach ($archivos as $f): ?>
                            <a href="<?= basename($f) ?>" target="_blank" style="font-size:10px;color:#2563eb;text-decoration:underline;">Ver doc</a><br>
                        <?php endforeach; else: ?>
                            <span style="font-size:10px;color:var(--text-2);">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-actions">
                        <div class="btn-group">
                            <?php if ($estado_actual === 'nuevos'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="generar_orden">
                                    <input type="hidden" name="order_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-small" title="Generar doc y mover a Producción">Imprimir</button>
                                </form>
                            <?php elseif ($estado_actual === 'produccion'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="marcar_enviado">
                                    <input type="hidden" name="order_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-small" style="border-color:var(--sent);color:var(--sent);" title="Marcar enviado y notificar cliente">Enviado</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="generar_orden">
                                    <input type="hidden" name="order_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-small btn-secondary" title="Re-generar documento">Doc</button>
                                </form>
                            <?php endif; ?>
                            <a href="/admin-descargas/modules/ver-pedido.php?id=<?= $p['id'] ?>" class="btn btn-small btn-secondary" title="Ver detalle">Ver</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar pedido #<?= $p['order_number'] ?? $p['id'] ?> y liberar sus unidades? Esta acción no se puede deshacer.');">
                                <input type="hidden" name="action" value="eliminar_pedido">
                                <input type="hidden" name="order_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-small btn-danger" title="Eliminar pedido">✕</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <footer class="footer">
        <p>DCIEN Pipeline · Fase: <?= strtoupper($estado_actual) ?> · <?= $total_mostrados ?> pedidos</p>
    </footer>
        </div>
    </div>
</div>

<script>
function toggleAll(cb) {
    document.querySelectorAll('.order-checkbox').forEach(c => c.checked = cb.checked);
}
function seleccionarTodos() {
    document.querySelectorAll('.order-checkbox').forEach(c => c.checked = true);
    document.getElementById('selectAll').checked = true;
}
function generarMultiples() {
    const cbs = document.querySelectorAll('.order-checkbox:checked');
    if (!cbs.length) { alert('Selecciona al menos un pedido.'); return; }
    const ids = Array.from(cbs).map(c => c.value);
    if (!confirm(`¿Generar ${ids.length} orden(es) y mover a Producción?`)) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="action" value="generar_multiples"><input type="hidden" name="order_ids" value="${ids.join(',')}">`;
    document.body.appendChild(form);
    form.submit();
}
function exportarCSV() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="exportar_csv">
        <input type="hidden" name="estado" value="<?= e($estado_actual) ?>">
        <input type="hidden" name="serie" value="<?= e($_GET['serie'] ?? '') ?>">
        <input type="hidden" name="desde" value="<?= e($_GET['desde'] ?? '') ?>">
        <input type="hidden" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>
