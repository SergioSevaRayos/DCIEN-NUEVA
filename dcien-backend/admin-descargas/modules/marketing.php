<?php
/**
 * DCIEN - MAPA VISUAL DE MARKETING
 * Cuadrícula topológica de unidades para gestión masiva y VIP
 */

require_once 'config.php';
$pdo = get_db_connection();
$message = '';

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE GENERACIÓN HTML (PARA MARKETING)
// ═══════════════════════════════════════════════════════════════

function generar_orden_marketing_html($pedido) {
    $shipping = json_decode($pedido['shipping_data'], true);

    $direccion = '';
    $nombre_envio = 'Cliente';
    if (isset($shipping['shipping_address'])) {
        $addr = $shipping['shipping_address'];
        $nombre_envio = ($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '');
        $direccion = $nombre_envio . "\n";
        $direccion .= ($addr['line1'] ?? '') . "\n";
        if (!empty($addr['line2'])) $direccion .= $addr['line2'] . "\n";
        $direccion .= ($addr['postal_code'] ?? '') . ' ' . ($addr['city'] ?? '') . "\n";
        if (!empty($addr['province'])) $direccion .= $addr['province'] . "\n";
        $direccion .= ($addr['country'] ?? 'ES');
        if (!empty($addr['phone'])) $direccion .= "\nTel: " . $addr['phone'];
    }
    $direccion = nl2br(htmlspecialchars($direccion));

    $color_slug = trim(strtolower($pedido['color'] ?? ''));
    $serie_slug = $pedido['series_slug']; 
    $base_url = "https://d-cien.es/images/series/$serie_slug/";

    if ($color_slug === 'blanco') {
        $img_front = $base_url . "main.png";
        $img_back  = $base_url . "detail-1.png";
    } else {
        $img_front = $base_url . "detail-2.png";
        $img_back  = $base_url . "detail-3.png";
    }

    $cliente_username = htmlspecialchars($pedido['username'] ?: 'N/A');
    $cliente_email = htmlspecialchars($pedido['email'] ?: 'N/A');
    $cliente_ig = 'MARKETING / INFLUENCER';

    $html = "<!DOCTYPE html><html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Orden #{$pedido['id']} - DCIEN (MARKETING)</title>
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:Arial,sans-serif; font-size:10px; line-height:1.3; padding:10mm; }
            .header { text-align:center; border-bottom:3px solid #000; padding-bottom:6px; margin-bottom:10px; }
            .header h1 { font-size:24px; font-weight:900; letter-spacing:4px; }
            .header span { font-size:9px; text-transform:uppercase; letter-spacing:2px; color:#666; }
            .block-product { display:grid; grid-template-columns:320px 1fr; gap:10px; margin-bottom:12px; page-break-inside:avoid; }
            .image-pair { display:grid; grid-template-columns:1fr 1fr; gap:5px; }
            .series-image { width:100%; height:155px; object-fit:contain; border:1px solid #000; background:#fff; }
            .product-details { border:2px solid #000; padding:10px; display:flex; flex-direction:column; justify-content:center; background:#f9f9f9; }
            .product-details .serie { font-size:16px; font-weight:900; letter-spacing:1px; margin-bottom:8px; text-align:center; }
            .product-details .unit { font-size:36px; font-weight:900; font-family:monospace; text-align:center; margin:8px 0; letter-spacing:2px; }
            .product-specs { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; margin-top:8px; }
            .spec-box { text-align:center; padding:5px; background:#f0f0f0; border:1px solid #ccc; }
            .spec-label { font-size:7px; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px; color:#666; }
            .spec-value { font-size:12px; font-weight:900; text-transform:uppercase; }
            .info-box { border:2px solid #000; padding:8px; }
            .info-box h3 { font-size:9px; text-transform:uppercase; letter-spacing:1px; border-bottom:2px solid #000; padding-bottom:3px; margin-bottom:6px; }
            .checklist { border:3px solid #000; padding:8px; page-break-inside:avoid; }
            .checklist ul { list-style:none; padding:0; }
            .checklist li { padding:4px 0; font-size:9px; border-bottom:1px dashed #ddd; }
            .doc-footer { margin-top:10px; padding-top:6px; border-top:2px solid #000; text-align:center; font-size:7px; color:#666; }
            @media print { @page { margin:0; size:A4 portrait; } body { padding:8mm; } }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>DCIEN <span style='font-size:12px; color:var(--text-2);'>[MARKETING]</span></h1>
            <p>Orden de Producción #{$pedido['id']}</p>
        </div>
        <div class='block-product'>
            <div class='image-pair'>
                <img src='$img_front' class='series-image' onerror=\"this.src='https://via.placeholder.com/150?text=FRONT'\">
                <img src='$img_back' class='series-image' onerror=\"this.src='https://via.placeholder.com/150?text=BACK'\">
            </div>
            <div class='product-details'>
                <div class='serie'>" . strtoupper($pedido['series_name'] ?? '') . "</div>
                <div class='unit'>#" . str_pad($pedido['unit_number'], 3, '0', STR_PAD_LEFT) . "</div>
                <div class='product-specs'>
                    <div class='spec-box'><div class='spec-label'>Talla</div><div class='spec-value'>{$pedido['size']}</div></div>
                    <div class='spec-box'><div class='spec-label'>Color</div><div class='spec-value'>{$pedido['color']}</div></div>
                    <div class='spec-box'><div class='spec-label'>Corte</div><div class='spec-value'>{$pedido['type']}</div></div>
                </div>
            </div>
        </div>
        <div class='block-shipping'>
            <div class='info-box'>
                <h3>👤 PERFIL ATLETA / INFLUENCER</h3>
                <div class='info-row'><div class='info-label'>Usuario:</div><div class='info-value'>$cliente_username</div></div>
                <div class='info-row'><div class='info-label'>Email:</div><div class='info-value'>$cliente_email</div></div>
                <div class='info-row'><div class='info-label'>Perfil:</div><div class='info-value'>$cliente_ig</div></div>
            </div>
            <div class='info-box'>
                <h3>📦 DIRECCIÓN DE ENVÍO</h3>
                <div class='shipping-address'>$direccion</div>
            </div>
        </div>
        <div class='checklist'>
            <h3>⚙️ CHECKLIST DE PRODUCCIÓN</h3>
            <ul>
                <li>☐ 1. Verificar stock en almacén <strong>(Talla " . strtoupper($pedido['size']) . " / Color " . ucfirst($pedido['color']) . " / Corte " . ucfirst($pedido['type']) . ")</strong></li>
                <li>☐ 2. Preparar unidad <strong>#" . str_pad($pedido['unit_number'], 3, '0', STR_PAD_LEFT) . "</strong></li>
                <li>☐ 3. Imprimir diseño " . strtoupper($pedido['series_name'] ?? '') . "</li>
                <li>☐ 4. Control de calidad final</li>
                <li>☐ 5. Empaquetar con tarjeta de autenticidad DCIEN</li>
                <li>☐ 6. Imprimir etiqueta y preparar envío a nombre de <strong>$nombre_envio</strong></li>
            </ul>
        </div>
        <div class='footer'>
            Documento generado el " . date('d/m/Y H:i:s') . " (Vía Panel de Marketing)
        </div>
    </body></html>";

    $filename = "orden_" . $pedido['id'] . "_" . date('Ymd_His') . ".html";
    $filepath = dirname(__DIR__) . "/ordenes/$filename";
    file_put_contents($filepath, $html);

    return "https://d-cien.es/admin-descargas/ordenes/$filename";
}

// ═══════════════════════════════════════════════════════════════
// PROCESAR FORMULARIOS
// ═══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- RETIRAR MÚLTIPLES (VISUAL) --
    if ($action === 'retirar_grid') {
        $unit_ids_raw = $_POST['unit_ids'] ?? '';
        $notas = trim($_POST['notas'] ?? 'Retiro Simple');
        $unit_ids = array_filter(array_map('intval', explode(',', $unit_ids_raw)));

        if (!empty($unit_ids)) {
            try {
                $pdo->beginTransaction();
                $exitosos = 0;
                foreach ($unit_ids as $uid) {
                    $stmt = $pdo->prepare("SELECT id, status, series_slug, unit_number FROM series_units WHERE id = ?");
                    $stmt->execute([$uid]);
                    $u = $stmt->fetch();
                    if ($u && $u['status'] === 'available') {
                        $pdo->prepare("UPDATE series_units SET status = 'sold', sold_at = NOW(), reserved_by = ? WHERE id = ?")
                            ->execute([$notas, $uid]);
                        $pdo->prepare("UPDATE series SET available_units = available_units - 1, sold_units = sold_units + 1 WHERE slug = ?")
                            ->execute([$u['series_slug']]);
                        $exitosos++;
                    }
                }
                $pdo->commit();
                if ($exitosos > 0) $message = show_message('success', "✅ Se retiraron $exitosos unidades del stock.");
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = show_message('error', "❌ Error DB: " . $e->getMessage());
            }
        }
    }

    // -- RESTAURAR MÚLTIPLES (VISUAL) --
    elseif ($action === 'restaurar_grid') {
        $unit_ids_raw = $_POST['unit_ids'] ?? '';
        $unit_ids = array_filter(array_map('intval', explode(',', $unit_ids_raw)));

        if (!empty($unit_ids)) {
            try {
                $pdo->beginTransaction();
                $exitosos = 0;
                foreach ($unit_ids as $uid) {
                    // Solo restaurar si es retiro simple (order_id IS NULL)
                    $stmt = $pdo->prepare("SELECT id, status, order_id, series_slug FROM series_units WHERE id = ?");
                    $stmt->execute([$uid]);
                    $u = $stmt->fetch();
                    if ($u && $u['status'] === 'sold' && is_null($u['order_id'])) {
                        $pdo->prepare("UPDATE series_units SET status = 'available', sold_at = NULL, reserved_by = NULL WHERE id = ?")
                            ->execute([$uid]);
                        $pdo->prepare("UPDATE series SET available_units = available_units + 1, sold_units = sold_units - 1 WHERE slug = ?")
                            ->execute([$u['series_slug']]);
                        $exitosos++;
                    }
                }
                $pdo->commit();
                if ($exitosos > 0) $message = show_message('success', "🔄 Se restauraron $exitosos unidades a la venta.");
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = show_message('error', "❌ Error DB: " . $e->getMessage());
            }
        }
    }

    // -- GENERAR ENVÍO VIP (MODAL) --
    elseif ($action === 'generar_orden') {
        $unit_id = (int)$_POST['vip_unit_id'];
        $size = $_POST['size'] ?? '';
        $color = $_POST['color'] ?? '';
        $type = $_POST['type'] ?? 'standard';
        
        $nombre = $_POST['nombre'] ?? '';
        $email = $_POST['email'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $cp = $_POST['cp'] ?? '';
        $ciudad = $_POST['ciudad'] ?? '';
        $provincia = $_POST['provincia'] ?? '';
        $telefono = $_POST['telefono'] ?? '';

        if ($unit_id && $size && $nombre && $direccion) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM series_units WHERE id = ?");
                $stmt->execute([$unit_id]);
                $unidad = $stmt->fetch();

                if (!$unidad) throw new Exception("La unidad no existe.");
                if ($unidad['status'] !== 'available') throw new Exception("La unidad no está disponible.");

                $series_slug = $unidad['series_slug'];
                $unit_number = $unidad['unit_number'];

                $stmtS = $pdo->prepare("SELECT name FROM series WHERE slug = ?");
                $stmtS->execute([$series_slug]);
                $serie_nombre = $stmtS->fetchColumn();

                $shippingData = [
                    "customer" => ["name" => $nombre, "email" => $email, "phone" => $telefono ?: null],
                    "shipping_address" => [
                        "first_name" => $nombre, "last_name" => "(Marketing)",
                        "line1" => $direccion, "line2" => "",
                        "postal_code" => $cp, "city" => $ciudad,
                        "province" => $provincia, "country" => "ES",
                        "email" => $email, "phone" => $telefono
                    ],
                    "payment" => ["status" => "paid", "amount_total" => 0, "amount_euros" => 0, "currency" => "EUR"],
                    "processed_by" => "admin_marketing"
                ];

                $fakeSessionId = 'PROMO_VIP_' . time();

                $insertOrder = $pdo->prepare("INSERT INTO orders 
                    (user_id, series_slug, unit_number, size, color, type, price, stripe_session_id, shipping_data, created_at, status) 
                    VALUES (0, :slug, :num, :size, :color, :type, 0.00, :session_id, :shipping, NOW(), 'produccion')");

                $insertOrder->execute([
                    'slug' => $series_slug, 'num' => $unit_number, 'size' => $size, 'color' => $color, 'type' => $type,
                    'session_id' => $fakeSessionId, 'shipping' => json_encode($shippingData)
                ]);
                $order_id = $pdo->lastInsertId();

                $pdo->prepare("UPDATE series_units SET status = 'sold', sold_at = NOW(), reserved_by = ?, order_id = ? WHERE id = ?")
                    ->execute(["Marketing VIP: $nombre", $order_id, $unit_id]);
                
                $pdo->prepare("UPDATE series SET available_units = available_units - 1, sold_units = sold_units + 1 WHERE slug = ?")
                    ->execute([$series_slug]);

                $pdo->commit();

                $pedido_mock = [
                    'id' => $order_id, 'series_slug' => $series_slug, 'series_name' => $serie_nombre,
                    'unit_number' => $unit_number, 'size' => $size, 'color' => $color, 'type' => $type,
                    'username' => $nombre, 'email' => $email, 'shipping_data' => json_encode($shippingData)
                ];
                $doc_url = generar_orden_marketing_html($pedido_mock);

                $message = show_message('success', "✅ Envío VIP creado para unidad #$unit_number. <br><br><a href='$doc_url' target='_blank' style='display:inline-block; margin-top:10px; background:#4ade80; color:#000; padding:10px 15px; border-radius:4px; font-weight:bold; text-decoration:none;'>📄 VER ORDEN HTML</a>");

            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $message = show_message('error', "❌ Error: " . $e->getMessage());
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// OBTENER DATOS PARA LA VISTA
// ═══════════════════════════════════════════════════════════════

$series = $pdo->query("SELECT slug, name, available_units FROM series ORDER BY name")->fetchAll();
$current_series_slug = $_GET['series'] ?? ($series[0]['slug'] ?? '');

$grid_units = [];
if ($current_series_slug) {
    $stmt = $pdo->prepare("SELECT * FROM series_units WHERE series_slug = ? ORDER BY unit_number ASC");
    $stmt->execute([$current_series_slug]);
    $grid_units = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Visual de Marketing - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        .header-controls { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        
        .series-selector { background:#111; padding:15px; border-radius:var(--radius); border:1px solid #333; display:flex; gap:15px; align-items:center; }
        .series-selector select { width:250px; }
        
        .legend { display:flex; gap:15px; font-size:11px; color:var(--text-2); background:var(--surface); padding:10px 15px; border-radius:4px; border:1px solid var(--border); }
        .legend-item { display:flex; align-items:center; gap:6px; }
        .legend-box { width:12px; height:12px; border-radius:2px; }
        
        /* Cajas visuales */
        .stock-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(60px, 1fr)); gap:8px; margin-bottom:100px; }
        .unit-box { 
            aspect-ratio: 1; 
            display:flex; 
            flex-direction:column;
            align-items:center; 
            justify-content:center; 
            font-size:16px; 
            font-weight:900; 
            font-family:'Outfit', monospace;
            border-radius:6px; 
            cursor:pointer; 
            user-select:none; 
            transition:all 0.15s ease;
            position:relative;
        }
        
        /* Estados */
        .box-available { background:#fff; color:#000; border:2px solid #ddd; }
        .box-available:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(255,255,255,0.2); }
        .box-available.selected { background:#3b82f6; color:#fff; border-color:#2563eb; transform:scale(0.95); }
        
        .box-withdrawn { background:#f97316; color:#fff; border:2px solid #ea580c; }
        .box-withdrawn:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(249,115,22,0.3); }
        .box-withdrawn.selected { background:#fcd34d; color:#000; border-color:#f59e0b; transform:scale(0.95); }
        
        .box-marketing { background:#a855f7; color:#fff; border:2px solid #9333ea; cursor:not-allowed; opacity:0.8; }
        .box-sold { background:#222; color:#555; border:2px solid #333; cursor:not-allowed; }

        /* Floating Action Bar */
        .action-bar { 
            position:fixed; bottom:-300px; left:50%; transform:translateX(-50%); 
            background:rgba(10,10,10,0.95); backdrop-filter:blur(10px);
            padding:15px 25px; border-radius:50px; border:1px solid #333;
            box-shadow:0 10px 40px rgba(0,0,0,0.5); display:flex; gap:15px; align-items:center;
            transition:bottom 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index:90; /* Debajo del botón D flotante */
            width: max-content;
            max-width: 90vw;
        }
        .action-bar.visible { bottom:30px; }
        
        /* Modal VIP */
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:1000; justify-content:center; align-items:center; padding:20px; backdrop-filter:blur(4px); }
        .modal.active { display:flex; }
        .modal-content { background:#111; padding:30px; border-radius:8px; border:1px solid #333; max-width:600px; width:100%; max-height:90vh; overflow-y:auto; }
        
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:15px; }

        /* --- Optimizaciones Móviles --- */
        @media (max-width: 600px) {
            .series-selector { width: 100%; flex-direction: column; align-items: stretch; gap: 8px; }
            .series-selector select { width: 100%; }
            .legend { flex-wrap: wrap; justify-content: center; gap: 10px; font-size: 10px; }
            .stock-grid { grid-template-columns: repeat(auto-fill, minmax(55px, 1fr)); gap: 6px; }
            
            .action-bar { 
                flex-direction: column; 
                width: calc(100% - 40px); 
                border-radius: 16px; 
                padding: 15px; 
            }
            .action-bar.visible { bottom: 90px; } /* Por encima del botón hamburguesa */
            #action-buttons { flex-direction: column; width: 100%; }
            #action-buttons button { width: 100%; justify-content: center; padding: 12px; font-size: 12px; }
            #selection-count { width: 100%; text-align: center; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 5px; }
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
                        <h1>🗺️ MAPA DE STOCK VISUAL</h1>
                        <p>Gestión topológica de la serie en tiempo real</p>
                    </div>
                </header>

                <?php if ($message) echo $message; ?>

                <div class="header-controls">
                    <div class="series-selector">
                        <label style="color:#888; font-size:11px; text-transform:uppercase;">Serie Activa:</label>
                        <select onchange="window.location.href='?series='+this.value">
                            <?php foreach ($series as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['slug']); ?>" <?php if($s['slug'] === $current_series_slug) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="legend">
                        <div class="legend-item"><div class="legend-box" style="background:#fff; border:1px solid #ccc;"></div> Disponible</div>
                        <div class="legend-item"><div class="legend-box" style="background:#f97316;"></div> Retiro Simple</div>
                        <div class="legend-item"><div class="legend-box" style="background:#a855f7;"></div> Envío VIP</div>
                        <div class="legend-item"><div class="legend-box" style="background:#222;"></div> Vendido Cliente</div>
                    </div>
                </div>

                <!-- EL GRID VISUAL -->
                <div class="card" style="padding:30px;">
                    <?php if (empty($grid_units)): ?>
                        <p style="text-align:center; color:#666;">Selecciona una serie válida o no hay stock creado para esta serie.</p>
                    <?php else: ?>
                        <div class="stock-grid" id="stock-grid">
                            <?php foreach ($grid_units as $u): 
                                $class = 'box-sold';
                                $type = 'sold';
                                $title = "Vendido / Bloqueado";
                                
                                if ($u['status'] === 'available') {
                                    $class = 'box-available';
                                    $type = 'available';
                                    $title = "Libre - Clic para seleccionar";
                                } elseif ($u['status'] === 'sold' && is_null($u['order_id'])) {
                                    $class = 'box-withdrawn';
                                    $type = 'withdrawn';
                                    $title = "Retiro: " . htmlspecialchars($u['reserved_by'] ?? 'Manual') . " - Clic para restaurar";
                                } elseif ($u['status'] === 'sold' && !is_null($u['order_id']) && strpos($u['reserved_by'] ?? '', 'Marketing VIP') !== false) {
                                    $class = 'box-marketing';
                                    $type = 'vip';
                                    $title = "Envío VIP: " . htmlspecialchars($u['reserved_by']);
                                }
                            ?>
                                <div class="unit-box <?php echo $class; ?>" 
                                     data-id="<?php echo $u['id']; ?>" 
                                     data-number="<?php echo $u['unit_number']; ?>"
                                     data-type="<?php echo $type; ?>"
                                     title="<?php echo $title; ?>"
                                     onclick="toggleSelection(this)">
                                     <?php echo $u['unit_number']; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Barra Flotante de Acciones -->
    <div class="action-bar" id="action-bar">
        <span id="selection-count" style="font-weight:bold; color:#fff; font-size:14px;">0 seleccionados</span>
        <div id="action-buttons" style="display:flex; gap:10px;">
            <!-- Los botones se inyectan por JS -->
        </div>
    </div>

    <!-- Formularios Ocultos para envío POST -->
    <form id="form-retiro" method="POST" style="display:none;">
        <input type="hidden" name="action" value="retirar_grid">
        <input type="hidden" name="unit_ids" id="retiro_ids">
        <input type="hidden" name="notas" id="retiro_notas" value="Retiro Múltiple Visual">
    </form>
    <form id="form-restaurar" method="POST" style="display:none;">
        <input type="hidden" name="action" value="restaurar_grid">
        <input type="hidden" name="unit_ids" id="restaurar_ids">
    </form>

    <!-- Modal Envío VIP -->
    <div class="modal" id="vip-modal">
        <div class="modal-content">
            <h3 style="color:#4ade80; margin-top:0; border-bottom:1px solid #333; padding-bottom:10px;">🎁 Preparar Envío VIP</h3>
            <p style="color:#aaa; font-size:12px; margin-bottom:20px;">Vas a vincular la <strong id="vip-unit-display" style="color:#fff;"></strong> de esta serie a un envío real de influencia.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="generar_orden">
                <input type="hidden" name="vip_unit_id" id="vip-unit-id">
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>Talla</label>
                        <select name="size" required>
                            <option value="S">S</option><option value="M">M</option>
                            <option value="L">L</option><option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Corte / Fit</label>
                        <select name="type" required>
                            <option value="standard">Standard Fit</option>
                            <option value="king-size">King Size</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Color</label>
                    <input type="text" name="color" value="negro" required>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Nombre del Influencer / VIP</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>Email (Tracking)</label>
                    <input type="email" name="email" value="marketing@d-cien.es">
                </div>
                <div class="form-group">
                    <label>Dirección de Envío Completa</label>
                    <input type="text" name="direccion" required placeholder="Calle, Piso...">
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>C. Postal</label>
                        <input type="text" name="cp" required>
                    </div>
                    <div class="form-group">
                        <label>Ciudad</label>
                        <input type="text" name="ciudad" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Provincia</label>
                        <input type="text" name="provincia" required>
                    </div>
                    <div class="form-group">
                        <label>Teléfono (Transportista)</label>
                        <input type="text" name="telefono">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeVipModal()" style="flex:1;">Cancelar</button>
                    <button type="submit" class="btn" style="flex:2; background:#4ade80; color:#000;">🚀 Generar PDF y Enviar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS INTERACTIVO -->
    <script>
        let selectionType = null; // 'available' o 'withdrawn'
        let selectedBoxes = [];

        function toggleSelection(box) {
            const type = box.getAttribute('data-type');
            if(type === 'sold' || type === 'vip') return; // Bloqueados

            const id = box.getAttribute('data-id');
            const num = box.getAttribute('data-number');

            if(box.classList.contains('selected')) {
                box.classList.remove('selected');
                selectedBoxes = selectedBoxes.filter(b => b.id !== id);
            } else {
                // Verificar que no se mezclen colores
                if(selectedBoxes.length > 0 && selectionType !== type) {
                    alert('No puedes seleccionar casillas libres y retiradas al mismo tiempo. Selecciona solo un tipo.');
                    return;
                }
                selectionType = type;
                box.classList.add('selected');
                selectedBoxes.push({ id, num, type });
            }

            updateActionBar();
        }

        function updateActionBar() {
            const bar = document.getElementById('action-bar');
            const count = document.getElementById('selection-count');
            const btns = document.getElementById('action-buttons');
            
            if(selectedBoxes.length === 0) {
                bar.classList.remove('visible');
                selectionType = null;
                return;
            }

            bar.classList.add('visible');
            count.innerText = `${selectedBoxes.length} casilla(s)`;

            let html = '';
            if(selectionType === 'available') {
                html += `<button class="btn btn-small" style="background:#f97316; border:none;" onclick="ejecutarRetiro()">⚠️ Retirar Stock</button>`;
                if(selectedBoxes.length === 1) {
                    html += `<button class="btn btn-small" style="background:#4ade80; border:none; color:#000; font-weight:bold;" onclick="abrirVipModal()">🎁 Generar Envío VIP</button>`;
                }
            } else if (selectionType === 'withdrawn') {
                html += `<button class="btn btn-small" style="background:#3b82f6; border:none; color:#fff;" onclick="ejecutarRestaurar()">🔄 Restaurar a la Venta</button>`;
            }
            
            btns.innerHTML = html;
        }

        function ejecutarRetiro() {
            const ids = selectedBoxes.map(b => b.id).join(',');
            let notas = prompt('Motivo del retiro múltiple (Opcional):', 'Retiro Visual Múltiple');
            if(notas !== null) {
                document.getElementById('retiro_ids').value = ids;
                document.getElementById('retiro_notas').value = notas;
                document.getElementById('form-retiro').submit();
            }
        }

        function ejecutarRestaurar() {
            if(confirm(`¿Seguro que quieres devolver ${selectedBoxes.length} unidad(es) a la venta?`)) {
                const ids = selectedBoxes.map(b => b.id).join(',');
                document.getElementById('restaurar_ids').value = ids;
                document.getElementById('form-restaurar').submit();
            }
        }

        function abrirVipModal() {
            if(selectedBoxes.length !== 1) return;
            const unit = selectedBoxes[0];
            document.getElementById('vip-unit-display').innerText = 'Unidad #' + String(unit.num).padStart(3, '0');
            document.getElementById('vip-unit-id').value = unit.id;
            document.getElementById('vip-modal').classList.add('active');
        }

        function closeVipModal() {
            document.getElementById('vip-modal').classList.remove('active');
        }

        // Cierre del modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            const modal = document.getElementById('vip-modal');
            if (event.target == modal) {
                closeVipModal();
            }
        }
    </script>
</body>
</html>