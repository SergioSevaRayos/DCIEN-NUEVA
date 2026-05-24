<?php
/**
 * DCIEN - Auditor de Pedidos
 * Con función de email PROBADA del CLI
 */

require_once 'config.php';

$pdo = get_db_connection();
$message = '';
$pedidos = [];
$filtros_aplicados = [];

// ═══════════════════════════════════════════════════════════════
// FUNCIÓN DE EMAIL QUE SÍ FUNCIONA (del CLI)
// ═══════════════════════════════════════════════════════════════

function enviar_email_orden($pedido, $orden_info) {
    $to = ADMIN_EMAIL;
    $subject = "🔔 Nueva Orden de Producción #{$pedido['id']} - DCIEN";
    
    $message = "
    <html>
    <head>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                background: #f5f5f5;
                margin: 0;
                padding: 0;
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: #fff;
            }
            .header { 
                background: #000; 
                color: #fff; 
                padding: 30px; 
                text-align: center; 
            }
            .header h1 {
                font-size: 36px;
                letter-spacing: 8px;
                margin: 0;
                font-weight: 900;
            }
            .header p {
                margin: 10px 0 0 0;
                font-size: 12px;
                letter-spacing: 3px;
                text-transform: uppercase;
            }
            .content { 
                padding: 30px;
            }
            .order-id {
                background: #000;
                color: #fff;
                padding: 20px;
                text-align: center;
                font-size: 48px;
                font-weight: 900;
                font-family: monospace;
                letter-spacing: 6px;
                margin: 20px 0;
            }
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin: 20px 0;
            }
            .info-box {
                background: #f9f9f9;
                border-left: 4px solid #000;
                padding: 15px;
            }
            .info-label {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 2px;
                color: #666;
                margin-bottom: 5px;
            }
            .info-value {
                font-size: 16px;
                font-weight: bold;
            }
            .button { 
                display: block;
                width: 100%;
                padding: 15px;
                background: #000; 
                color: #fff; 
                text-decoration: none; 
                text-align: center;
                margin: 30px 0;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 2px;
                font-weight: 900;
            }
            .footer {
                background: #f5f5f5;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>DCIEN</h1>
                <p>Nueva Orden de Producción</p>
            </div>
            
            <div class='content'>
                <div class='order-id'>#{$pedido['id']}</div>
                
                <div class='info-grid'>
                    <div class='info-box'>
                        <div class='info-label'>Serie</div>
                        <div class='info-value'>{$pedido['series_name']}</div>
                    </div>
                    <div class='info-box'>
                        <div class='info-label'>Unidad</div>
                        <div class='info-value'>#{$pedido['unit_number']}</div>
                    </div>
                    <div class='info-box'>
                        <div class='info-label'>Talla</div>
                        <div class='info-value'>" . strtoupper($pedido['size']) . "</div>
                    </div>
                    <div class='info-box'>
                        <div class='info-label'>Color</div>
                        <div class='info-value'>" . ucfirst($pedido['color']) . "</div>
                    </div>
                </div>
                
                <div style='background:#fffacd;border-left:4px solid #ffa500;padding:15px;margin:20px 0'>
                    <strong>👤 Cliente:</strong> {$pedido['email']}<br>
                    <strong>📅 Fecha:</strong> " . date('d/m/Y H:i', strtotime($pedido['created_at'])) . "
                </div>
                
                <a href='{$orden_info['url']}' class='button'>
                    ▶ Ver Orden Completa
                </a>
                
                <p style='text-align:center;font-size:12px;color:#999;margin-top:20px'>
                    O accede al panel: <a href='https://d-cien.es/admin-descargas/' style='color:#000'>admin-descargas</a>
                </p>
            </div>
            
            <div class='footer'>
                <p><strong>DCIEN</strong> Sistema de Gestión de Producción</p>
                <p style='margin-top:5px'>Documento: {$orden_info['filename']}</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: DCIEN Producción <produccion@d-cien.es>" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// ═══════════════════════════════════════════════════════════════
// FUNCIÓN DE GENERACIÓN DE ÓRDENES (del CLI)
// ═══════════════════════════════════════════════════════════════

function generar_orden_trabajo($pedido) {
    $shipping = json_decode($pedido['shipping_data'], true);
    $html = generar_html_orden($pedido, $shipping);
    
    $filename = "orden_" . $pedido['id'] . "_" . date('Ymd_His') . ".html";
    $outputDir = dirname(__DIR__) . '/ordenes';
    $filepath = "$outputDir/$filename";
    
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    file_put_contents($filepath, $html);
    
    return [
        'success' => true,
        'filepath' => $filepath,
        'filename' => $filename,
        'url' => "https://d-cien.es/admin-descargas/ordenes/$filename"
    ];
}

function generar_html_orden($pedido, $shipping) {
    // Extraer dirección de envío
    $direccion = '';
    if (isset($shipping['shipping_address'])) {
        $addr = $shipping['shipping_address'];
        $direccion = ($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '') . "\n";
        $direccion .= ($addr['line1'] ?? '') . "\n";
        if (!empty($addr['line2'])) $direccion .= $addr['line2'] . "\n";
        $direccion .= ($addr['postal_code'] ?? '') . ' ' . ($addr['city'] ?? '') . "\n";
        if (!empty($addr['province'])) $direccion .= $addr['province'] . "\n";
        $direccion .= ($addr['country'] ?? 'ES');
        if (!empty($addr['phone'])) $direccion .= "\nTel: " . $addr['phone'];
    }
    
    $direccion = nl2br(htmlspecialchars($direccion));
    
    // Obtener imagen de la serie
    $series_image = '';
    if (!empty($pedido['series_images'])) {
        $images = json_decode($pedido['series_images'], true);
        if (is_array($images) && !empty($images[0])) {
            $series_image = 'https://d-cien.es' . $images[0];
        }
    }
    
    if (empty($series_image)) {
        $series_image = 'https://via.placeholder.com/270x270/000000/FFFFFF/?text=' . urlencode($pedido['series_name'] ?? 'DCIEN');
    }
    
    return "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Orden #{$pedido['id']} - DCIEN</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; line-height: 1.3; padding: 10mm; }
        .header { text-align: center; border-bottom: 3px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
        .header h1 { font-size: 24px; font-weight: 900; letter-spacing: 4px; }
        .header p { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; margin-top: 3px; }
        .block-product { display: grid; grid-template-columns: 270px 1fr; gap: 10px; margin-bottom: 10px; page-break-inside: avoid; }
        .series-image { width: 100%; height: 270px; object-fit: cover; border: 2px solid #000; }
        .product-details { border: 2px solid #000; padding: 10px; display: flex; flex-direction: column; justify-content: center; }
        .product-details .serie { font-size: 16px; font-weight: 900; letter-spacing: 1px; margin-bottom: 8px; text-align: center; }
        .product-details .unit { font-size: 36px; font-weight: 900; font-family: monospace; text-align: center; margin: 8px 0; letter-spacing: 2px; }
        .product-specs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-top: 8px; }
        .spec-box { text-align: center; padding: 5px; background: #f0f0f0; border: 1px solid #ccc; }
        .spec-label { font-size: 7px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; color: #666; }
        .spec-value { font-size: 12px; font-weight: 900; }
        .block-shipping { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; page-break-inside: avoid; }
        .info-box { border: 2px solid #000; padding: 8px; }
        .info-box h3 { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #000; padding-bottom: 3px; margin-bottom: 6px; }
        .info-row { display: flex; margin-bottom: 3px; }
        .info-label { font-weight: bold; width: 70px; flex-shrink: 0; font-size: 9px; }
        .info-value { flex: 1; font-size: 9px; }
        .shipping-address { line-height: 1.4; font-size: 9px; }
        .block-additional { page-break-inside: avoid; }
        .pedido-info { background: #f5f5f5; border: 2px solid #000; padding: 6px; margin-bottom: 10px; font-size: 8px; }
        .pedido-info strong { display: inline-block; width: 70px; }
        .checklist { border: 3px solid #000; padding: 8px; }
        .checklist h3 { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
        .checklist ul { list-style: none; padding: 0; }
        .checklist li { padding: 3px 0; font-size: 8px; border-bottom: 1px dashed #ddd; }
        .checklist li:last-child { border-bottom: none; }
        .footer { margin-top: 10px; padding-top: 6px; border-top: 2px solid #000; text-align: center; font-size: 7px; color: #666; }
        @media print { body { padding: 8mm; } @page { margin: 0; size: A4 portrait; } }
    </style>
</head>
<body>
    <div class='header'>
        <h1>DCIEN</h1>
        <p>Orden de Producción #{$pedido['id']}</p>
    </div>
    <div class='block-product'>
        <img src='$series_image' alt='Serie' class='series-image' onerror=\"this.style.display='none'\">
        <div class='product-details'>
            <div class='serie'>" . strtoupper($pedido['series_name'] ?? $pedido['series_slug']) . "</div>
            <div class='unit'>#" . str_pad($pedido['unit_number'], 3, '0', STR_PAD_LEFT) . "</div>
            <div class='product-specs'>
                <div class='spec-box'><div class='spec-label'>Talla</div><div class='spec-value'>" . strtoupper($pedido['size']) . "</div></div>
                <div class='spec-box'><div class='spec-label'>Color</div><div class='spec-value'>" . ucfirst($pedido['color']) . "</div></div>
                <div class='spec-box'><div class='spec-label'>Tipo</div><div class='spec-value'>" . ucfirst($pedido['type']) . "</div></div>
            </div>
        </div>
    </div>
    <div class='block-shipping'>
        <div class='info-box'>
            <h3>👤 CLIENTE</h3>
            <div class='info-row'><div class='info-label'>Usuario:</div><div class='info-value'>" . htmlspecialchars($pedido['username'] ?? 'N/A') . "</div></div>
            <div class='info-row'><div class='info-label'>Email:</div><div class='info-value'>" . htmlspecialchars($pedido['email']) . "</div></div>
            <div class='info-row'><div class='info-label'>Instagram:</div><div class='info-value'>@" . htmlspecialchars($pedido['instagram_username'] ?? 'N/A') . "</div></div>
        </div>
        <div class='info-box'>
            <h3>📦 DIRECCIÓN DE ENVÍO</h3>
            <div class='shipping-address'>$direccion</div>
        </div>
    </div>
    <div class='block-additional'>
        <div class='pedido-info'>
            <strong>ID:</strong> #{$pedido['id']} &nbsp;
            <strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($pedido['created_at'])) . " &nbsp;
            <strong>Estado:</strong> " . strtoupper($pedido['unit_status'] ?? 'UNKNOWN') . "
        </div>
        <div class='checklist'>
            <h3>⚙️ CHECKLIST DE PRODUCCIÓN</h3>
            <ul>
                <li>☐ 1. Verificar stock (Talla " . strtoupper($pedido['size']) . " / Color " . ucfirst($pedido['color']) . ")</li>
                <li>☐ 2. Preparar unidad #{$pedido['unit_number']}</li>
                <li>☐ 3. Imprimir diseño " . strtoupper($pedido['series_name'] ?? $pedido['series_slug']) . "</li>
                <li>☐ 4. Control de calidad</li>
                <li>☐ 5. Empaquetar con materiales DCIEN</li>
                <li>☐ 6. Incluir tarjeta de autenticidad #" . $pedido['unit_number'] . "</li>
                <li>☐ 7. Preparar para envío</li>
            </ul>
        </div>
    </div>
    <div class='footer'>
        <p><strong>DCIEN</strong> · Sistema de Gestión de Producción · Documento generado " . date('d/m/Y H:i:s') . "</p>
    </div>
</body>
</html>";
}

// ═══════════════════════════════════════════════════════════════
// RESTO DEL CÓDIGO IDÉNTICO AL ANTERIOR
// ═══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generar_orden' && isset($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                o.*,
                u.username,
                u.email,
                u.instagram_username,
                s.name as series_name,
                s.images as series_images,
                su.status as unit_status
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            LEFT JOIN series s ON s.slug = o.series_slug
            LEFT JOIN series_units su ON su.series_slug = o.series_slug AND su.unit_number = o.unit_number
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $pedido = $stmt->fetch();
        
        if ($pedido) {
            $result = generar_orden_trabajo($pedido);
            
            if ($result['success']) {
                if (isset($_POST['send_email']) && $_POST['send_email'] === '1') {
                    $email_sent = enviar_email_orden($pedido, $result);
                    
                    if ($email_sent) {
                        $message = show_message('success', "✅ Orden #{$order_id} generada y email enviado a " . ADMIN_EMAIL . ". <a href='{$result['url']}' target='_blank' style='color:#00ff00'>Ver orden</a>");
                    } else {
                        $message = show_message('warning', "⚠️ Orden #{$order_id} generada pero el email NO se pudo enviar. Revisa tu configuración de mail(). <a href='{$result['url']}' target='_blank' style='color:#00ff00'>Ver orden</a>");
                    }
                } else {
                    $message = show_message('success', "✅ Orden #{$order_id} generada. <a href='{$result['url']}' target='_blank' style='color:#00ff00'>Ver orden</a>");
                }
            } else {
                $message = show_message('error', "❌ Error al generar orden");
            }
        }
    }
    
    if ($action === 'generar_multiples' && isset($_POST['order_ids'])) {
        $order_ids = explode(',', $_POST['order_ids']);
        $exitosos = 0;
        $errores = 0;
        
        foreach ($order_ids as $order_id) {
            $order_id = (int)trim($order_id);
            if ($order_id <= 0) continue;
            
            $stmt = $pdo->prepare("
                SELECT 
                    o.*,
                    u.username,
                    u.email,
                    u.instagram_username,
                    s.name as series_name,
                    s.images as series_images,
                    su.status as unit_status
                FROM orders o
                LEFT JOIN users u ON u.id = o.user_id
                LEFT JOIN series s ON s.slug = o.series_slug
                LEFT JOIN series_units su ON su.series_slug = o.series_slug AND su.unit_number = o.unit_number
                WHERE o.id = ?
            ");
            $stmt->execute([$order_id]);
            $pedido = $stmt->fetch();
            
            if ($pedido) {
                $result = generar_orden_trabajo($pedido);
                if ($result['success']) $exitosos++; else $errores++;
            }
        }
        
        $message = show_message('success', "✅ Completado: {$exitosos} exitosos, {$errores} errores");
    }
    
    if ($action === 'exportar_csv') {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($_POST['serie'])) {
            $where[] = "o.series_slug = :serie";
            $params['serie'] = $_POST['serie'];
        }
        if (!empty($_POST['desde'])) {
            $where[] = "o.created_at >= :desde";
            $params['desde'] = $_POST['desde'] . ' 00:00:00';
        }
        if (!empty($_POST['hasta'])) {
            $where[] = "o.created_at <= :hasta";
            $params['hasta'] = $_POST['hasta'] . ' 23:59:59';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $pdo->prepare("
            SELECT 
                o.*,
                u.username,
                u.email,
                u.instagram_username,
                s.name as series_name,
                su.status as unit_status
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            LEFT JOIN series s ON s.slug = o.series_slug
            LEFT JOIN series_units su ON su.series_slug = o.series_slug AND su.unit_number = o.unit_number
            WHERE $whereClause
            ORDER BY o.created_at DESC
        ");
        $stmt->execute($params);
        $pedidos_export = $stmt->fetchAll();
        
        $filename = "pedidos_" . date('Ymd_His') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Fecha', 'Serie', 'Unidad', 'Talla', 'Color', 'Tipo', 'Cliente', 'Email', 'Instagram', 'Precio', 'Estado']);
        
        foreach ($pedidos_export as $p) {
            fputcsv($output, [
                $p['id'],
                $p['created_at'],
                $p['series_name'],
                $p['unit_number'],
                $p['size'],
                $p['color'],
                $p['type'],
                $p['username'] ?: 'N/A',
                $p['email'],
                $p['instagram_username'] ?: 'N/A',
                $p['price'],
                $p['unit_status'] ?? 'N/A'
            ]);
        }
        
        fclose($output);
        exit;
    }
}

$where = ['1=1'];
$params = [];

if (!empty($_GET['serie'])) {
    $where[] = "o.series_slug = :serie";
    $params['serie'] = $_GET['serie'];
    $filtros_aplicados[] = "Serie: " . htmlspecialchars($_GET['serie']);
}

if (!empty($_GET['desde'])) {
    $where[] = "o.created_at >= :desde";
    $params['desde'] = $_GET['desde'] . ' 00:00:00';
    $filtros_aplicados[] = "Desde: " . htmlspecialchars($_GET['desde']);
}

if (!empty($_GET['hasta'])) {
    $where[] = "o.created_at <= :hasta";
    $params['hasta'] = $_GET['hasta'] . ' 23:59:59';
    $filtros_aplicados[] = "Hasta: " . htmlspecialchars($_GET['hasta']);
}

if (!empty($_GET['search'])) {
    $where[] = "(u.username LIKE :search OR u.email LIKE :search OR o.id = :search_id)";
    $params['search'] = '%' . $_GET['search'] . '%';
    $params['search_id'] = is_numeric($_GET['search']) ? (int)$_GET['search'] : 0;
    $filtros_aplicados[] = "Búsqueda: " . htmlspecialchars($_GET['search']);
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT 
        o.*,
        u.username,
        u.email,
        u.instagram_username,
        s.name as series_name,
        su.status as unit_status
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    LEFT JOIN series s ON s.slug = o.series_slug
    LEFT JOIN series_units su ON su.series_slug = o.series_slug AND su.unit_number = o.unit_number
    WHERE $whereClause
    ORDER BY o.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$series = $pdo->query("SELECT slug, name FROM series ORDER BY name")->fetchAll();

$total_mostrados = count($pedidos);
$suma_total = array_sum(array_column($pedidos, 'price'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor de Pedidos - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
    <style>
        .checkbox-column { width: 40px; text-align: center; }
        .btn-group { display: flex; gap: 8px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div>
                <h1>AUDITOR DE PEDIDOS</h1>
                <p>Gestión y generación de órdenes</p>
            </div>
            <div class="header-actions">
                <a href="/admin-descargas/">← Dashboard</a>
            </div>
        </header>

        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="filters">
            <form method="GET">
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Serie</label>
                        <select name="serie">
                            <option value="">Todas</option>
                            <?php foreach ($series as $s): ?>
                                <option value="<?php echo e($s['slug']); ?>" <?php echo ($_GET['serie'] ?? '') === $s['slug'] ? 'selected' : ''; ?>>
                                    <?php echo e($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Desde</label>
                        <input type="date" name="desde" value="<?php echo e($_GET['desde'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Hasta</label>
                        <input type="date" name="hasta" value="<?php echo e($_GET['hasta'] ?? ''); ?>">
                    </div>
                    <div class="form-group search-box">
                        <label>Buscar</label>
                        <input type="text" name="search" placeholder="ID, usuario, email..." value="<?php echo e($_GET['search'] ?? ''); ?>">
                    </div>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn">🔍 Filtrar</button>
                    <a href="auditor.php" class="btn btn-secondary">✕ Limpiar</a>
                    <button type="button" onclick="exportarCSV()" class="btn btn-secondary">📥 CSV</button>
                    <button type="button" onclick="seleccionarTodos()" class="btn btn-secondary">☑️ Todos</button>
                    <button type="button" onclick="generarMultiples()" class="btn">📄 Generar</button>
                </div>
            </form>
        </div>

        <div style="margin-bottom: 20px; padding: 16px; background: #111; border: 2px solid #333;">
            <?php if (!empty($filtros_aplicados)): ?>
                <strong>Filtros:</strong> <?php echo implode(' · ', $filtros_aplicados); ?><br>
            <?php endif; ?>
            <strong>Mostrando:</strong> <?php echo $total_mostrados; ?> pedidos · <strong>Total:</strong> <?php echo format_price($suma_total); ?>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-column"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                        <th>ID</th>
                        <th>Serie</th>
                        <th>Unidad</th>
                        <th>Cliente</th>
                        <th>Talla</th>
                        <th>Color</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                        <tr><td colspan="11" style="text-align: center; padding: 40px; color: #666;">No se encontraron pedidos</td></tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $p): ?>
                            <tr>
                                <td class="checkbox-column"><input type="checkbox" class="order-checkbox" value="<?php echo $p['id']; ?>"></td>
                                <td><strong>#<?php echo $p['id']; ?></strong></td>
                                <td><?php echo e($p['series_name']); ?></td>
                                <td><strong>#<?php echo str_pad($p['unit_number'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo e($p['username'] ?: substr($p['email'], 0, 20)); ?></td>
                                <td><?php echo strtoupper($p['size']); ?></td>
                                <td><?php echo ucfirst($p['color']); ?></td>
                                <td><strong><?php echo format_price($p['price']); ?></strong></td>
                                <td><span class="badge <?php echo $p['unit_status'] === 'sold' ? 'badge-success' : 'badge-secondary'; ?>"><?php echo strtoupper($p['unit_status'] ?? 'N/A'); ?></span></td>
                                <td><?php echo format_date($p['created_at'], 'd/m/Y H:i'); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="generar_orden">
                                            <input type="hidden" name="order_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-small" title="Generar orden">📄</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="generar_orden">
                                            <input type="hidden" name="order_id" value="<?php echo $p['id']; ?>">
                                            <input type="hidden" name="send_email" value="1">
                                            <button type="submit" class="btn btn-small btn-secondary" title="Con email">📧</button>
                                        </form>
                                        <a href="ver-pedido.php?id=<?php echo $p['id']; ?>" class="btn btn-small btn-secondary" title="Ver">👁️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <footer class="footer">
            <p>DCIEN Auditor · Últimos 100 resultados · Email: <?php echo ADMIN_EMAIL; ?></p>
        </footer>
    </div>

    <script>
    function toggleAll(cb) { document.querySelectorAll('.order-checkbox').forEach(c => c.checked = cb.checked); }
    function seleccionarTodos() { document.querySelectorAll('.order-checkbox').forEach(c => c.checked = true); document.getElementById('selectAll').checked = true; }
    
    function generarMultiples() {
        const cbs = document.querySelectorAll('.order-checkbox:checked');
        if (cbs.length === 0) { alert('⚠️ Selecciona al menos un pedido'); return; }
        const ids = Array.from(cbs).map(c => c.value);
        if (!confirm(`¿Generar ${ids.length} órdenes?`)) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="generar_multiples"><input type="hidden" name="order_ids" value="${ids.join(',')}">`;
        document.body.appendChild(form);
        form.submit();
    }
    
    function exportarCSV() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="exportar_csv"><input type="hidden" name="serie" value="<?php echo e($_GET['serie'] ?? ''); ?>"><input type="hidden" name="desde" value="<?php echo e($_GET['desde'] ?? ''); ?>"><input type="hidden" name="hasta" value="<?php echo e($_GET['hasta'] ?? ''); ?>">`;
        document.body.appendChild(form);
        form.submit();
    }
    </script>
</body>
</html>