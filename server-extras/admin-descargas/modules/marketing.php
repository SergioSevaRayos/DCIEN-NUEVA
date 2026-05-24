<?php
/**
 * DCIEN - GESTOR DE UNIDADES PARA MARKETING
 * Retira unidades del stock para regalos/influencers manteniendo sincronizados los contadores
 * Y GENERANDO EL DOCUMENTO HTML DE LA ORDEN DE TRABAJO COMPLETA.
 * [Versión 100% Responsive]
 */

require_once 'config.php';
$pdo = get_db_connection();
$message = '';

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE GENERACIÓN HTML (PARA MARKETING)
// ═══════════════════════════════════════════════════════════════

function generar_orden_marketing_html($pedido) {
    $shipping = json_decode($pedido['shipping_data'], true);

    // Dirección de envío completa
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

    // Lógica de imágenes estándar por color
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

    // Datos del Cliente (Influencer)
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
    // Ajuste de ruta: Subimos de 'modules' a 'admin-descargas' y bajamos a 'ordenes'
    $filepath = dirname(__DIR__) . "/ordenes/$filename";
    file_put_contents($filepath, $html);

    return "https://d-cien.es/admin-descargas/ordenes/$filename";
}

// ═══════════════════════════════════════════════════════════════
// PROCESAR FORMULARIOS
// ═══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ------------------------------------------------------------------
    // ACCIÓN NUEVA: GENERAR ORDEN DE TRABAJO COMPLETA (0€)
    // ------------------------------------------------------------------
    if ($action === 'generar_orden') {
        $series_slug = $_POST['series_slug'] ?? '';
        $unit_number = (int)($_POST['unit_number'] ?? 0);
        $size = $_POST['size'] ?? '';
        $color = $_POST['color'] ?? '';
        $type = $_POST['type'] ?? 'standard'; // Corte: standard o king-size
        
        $nombre = $_POST['nombre'] ?? '';
        $email = $_POST['email'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $cp = $_POST['cp'] ?? '';
        $ciudad = $_POST['ciudad'] ?? '';
        $provincia = $_POST['provincia'] ?? '';
        $telefono = $_POST['telefono'] ?? '';

        if ($series_slug && $unit_number && $size && $nombre && $direccion) {
            try {
                $pdo->beginTransaction();

                // 1. Verificar disponibilidad de unidad
                $stmt = $pdo->prepare("SELECT id, status FROM series_units WHERE series_slug = ? AND unit_number = ?");
                $stmt->execute([$series_slug, $unit_number]);
                $unidad = $stmt->fetch();

                if (!$unidad) throw new Exception("La unidad #$unit_number no existe.");
                if ($unidad['status'] !== 'available') throw new Exception("La unidad #$unit_number no está disponible (estado: {$unidad['status']}).");

                // 2. Obtener Nombre de la Serie (Para el HTML)
                $stmtS = $pdo->prepare("SELECT name FROM series WHERE slug = ?");
                $stmtS->execute([$series_slug]);
                $serie_nombre = $stmtS->fetchColumn();

                // 3. Crear JSON de envío completo
                $shippingData = [
                    "customer" => ["name" => $nombre, "email" => $email, "phone" => $telefono ?: null],
                    "shipping_address" => [
                        "first_name" => $nombre,
                        "last_name" => "(Marketing)",
                        "line1" => $direccion, "line2" => "",
                        "postal_code" => $cp, "city" => $ciudad,
                        "province" => $provincia, "country" => "ES",
                        "email" => $email, "phone" => $telefono
                    ],
                    "payment" => ["status" => "paid", "amount_total" => 0, "amount_euros" => 0, "currency" => "EUR"],
                    "processed_by" => "admin_marketing"
                ];

                $fakeSessionId = 'PROMO_MARKETING_' . time();

                // 4. Insertar en tabla orders (estado: produccion)
                $insertOrder = $pdo->prepare("INSERT INTO orders 
                    (user_id, series_slug, unit_number, size, color, type, price, stripe_session_id, shipping_data, created_at, status) 
                    VALUES (0, :slug, :num, :size, :color, :type, 0.00, :session_id, :shipping, NOW(), 'produccion')");

                $insertOrder->execute([
                    'slug' => $series_slug, 'num' => $unit_number, 'size' => $size, 'color' => $color, 'type' => $type,
                    'session_id' => $fakeSessionId, 'shipping' => json_encode($shippingData)
                ]);
                $order_id = $pdo->lastInsertId();

                // 5. Actualizar unidad y contadores
                $pdo->prepare("UPDATE series_units SET status = 'sold', sold_at = NOW(), reserved_by = ?, order_id = ? WHERE id = ?")
                    ->execute(["Marketing: $nombre", $order_id, $unidad['id']]);
                
                $pdo->prepare("UPDATE series SET available_units = available_units - 1, sold_units = sold_units + 1 WHERE slug = ?")
                    ->execute([$series_slug]);

                $pdo->commit();

                // 6. GENERAR EL HTML Y GUARDARLO
                $pedido_mock = [
                    'id' => $order_id,
                    'series_slug' => $series_slug,
                    'series_name' => $serie_nombre,
                    'unit_number' => $unit_number,
                    'size' => $size,
                    'color' => $color,
                    'type' => $type,
                    'username' => $nombre,
                    'email' => $email,
                    'shipping_data' => json_encode($shippingData)
                ];
                $doc_url = generar_orden_marketing_html($pedido_mock);

                $message = show_message('success', "✅ Orden de trabajo creada. La unidad #$unit_number se ha asignado a $nombre. <br><br> <a href='$doc_url' target='_blank' style='display:inline-block; margin-top:10px; background:#00ff00; color:#000; padding:10px 15px; border-radius:4px; font-weight:bold; text-decoration:none;'>📄 VER / IMPRIMIR ORDEN HTML</a>");

            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $message = show_message('error', "❌ Error: " . $e->getMessage());
            }
        } else {
            $message = show_message('error', "⚠️ Faltan datos obligatorios para generar la orden.");
        }
    }

    // ------------------------------------------------------------------
    // ACCIÓN ANTIGUA: RETIRAR UNIDADES (SIN ENVÍO)
    // ------------------------------------------------------------------
    elseif ($action === 'retirar') {
        $series_slug = $_POST['series_slug'] ?? '';
        $numeros_raw = $_POST['numeros'] ?? '';
        $notas = trim($_POST['notas'] ?? 'Retirado para Marketing');

        $numeros = array_filter(array_map('intval', explode(',', $numeros_raw)));

        if (!empty($series_slug) && !empty($numeros)) {
            try {
                $pdo->beginTransaction();
                $exitosos = 0;
                $errores = [];

                foreach ($numeros as $num) {
                    $stmt = $pdo->prepare("SELECT id, status FROM series_units WHERE series_slug = ? AND unit_number = ?");
                    $stmt->execute([$series_slug, $num]);
                    $unidad = $stmt->fetch();

                    if (!$unidad) {
                        $errores[] = "#$num (No existe)";
                    } elseif ($unidad['status'] !== 'available') {
                        $errores[] = "#$num (No disponible)";
                    } else {
                        $pdo->prepare("UPDATE series_units SET status = 'sold', sold_at = NOW(), reserved_by = ? WHERE id = ?")
                            ->execute([$notas, $unidad['id']]);
                        $exitosos++;
                    }
                }

                if ($exitosos > 0) {
                    $pdo->prepare("UPDATE series SET available_units = available_units - ?, sold_units = sold_units + ? WHERE slug = ?")
                        ->execute([$exitosos, $exitosos, $series_slug]);
                }

                $pdo->commit();

                if ($exitosos > 0 && empty($errores)) {
                    $message = show_message('success', "✅ Se han retirado $exitosos unidades (Sin envío).");
                } elseif ($exitosos > 0 && !empty($errores)) {
                    $message = show_message('warning', "⚠️ Se retiraron $exitosos unidades, problemas: " . implode(', ', $errores));
                } else {
                    $message = show_message('error', "❌ Error: " . implode(', ', $errores));
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $message = show_message('error', "❌ Error DB: " . $e->getMessage());
            }
        }
    }

    // ------------------------------------------------------------------
    // ACCIÓN: DEVOLVER A STOCK
    // ------------------------------------------------------------------
    elseif ($action === 'devolver' && isset($_POST['unit_id'])) {
        $unit_id = (int)$_POST['unit_id'];

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT series_slug, unit_number FROM series_units WHERE id = ?");
            $stmt->execute([$unit_id]);
            $unidad = $stmt->fetch();

            if ($unidad) {
                $pdo->prepare("UPDATE series_units SET status = 'available', sold_at = NULL, reserved_by = NULL WHERE id = ?")->execute([$unit_id]);
                $pdo->prepare("UPDATE series SET available_units = available_units + 1, sold_units = sold_units - 1 WHERE slug = ?")->execute([$unidad['series_slug']]);
                $message = show_message('success', "🔄 Unidad #{$unidad['unit_number']} devuelta al stock.");
            }
            $pdo->commit();
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $message = show_message('error', "❌ Error al devolver al stock.");
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// OBTENER DATOS PARA LA VISTA
// ═══════════════════════════════════════════════════════════════

$series = $pdo->query("SELECT slug, name, available_units FROM series ORDER BY name")->fetchAll();

$retiradas = $pdo->query("
    SELECT su.*, s.name as series_name
    FROM series_units su
    JOIN series s ON su.series_slug = s.slug
    WHERE su.status = 'sold' AND su.order_id IS NULL
    ORDER BY su.sold_at DESC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Stock de Marketing - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
    <style>
        /* Layout */
        .split-layout { display:grid; grid-template-columns:1fr 2fr; gap:20px; margin-top:20px; align-items:start; }
        .split-layout > div { min-width:0; }
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:15px; }
        .checkbox-column { width:40px; text-align:center; }
        .assign-actions { display:flex; gap:8px; }

        /* Cards de módulo */
        .card { background:var(--surface); border:1px solid var(--border); padding:20px; border-radius:var(--radius); box-sizing:border-box; width:100%; margin-bottom:20px; box-shadow:var(--shadow); }

        /* Títulos de sección */
        .section-title { font-size:11px; font-weight:600; color:var(--text-2); text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--border); padding-bottom:5px; margin:24px 0 12px; }
        .section-title.blue { color:#2563eb; }

        /* Ayuda */
        .help-text { font-size:11px; color:var(--text-2); margin-top:4px; display:block; line-height:1.4; }

        /* Badges de estado */
        .badge-status { padding:3px 10px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-radius:20px; display:inline-block; }
        .bg-success { background:var(--sent-bg); color:var(--sent); border:1px solid var(--sent-border); }
        .bg-danger, .bg-error { background:var(--new-bg); color:var(--new); border:1px solid var(--new-border); }
        .badge-secondary { background:var(--border); color:var(--text-2); padding:3px 8px; border-radius:20px; font-size:10px; font-weight:600; white-space:nowrap; display:inline-block; }

        /* Regla de marca */
        .brand-rule { background:var(--surface-2); border-left:4px solid var(--border-2); padding:15px; font-size:11px; color:var(--text-2); margin-top:20px; border-radius:var(--radius); line-height:1.5; }

        /* Modales */
        #create-modal, #bulk-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; padding:15px; }
        .modal-content { background:var(--surface); border:1px solid var(--border); padding:25px; width:100%; max-width:500px; border-radius:var(--radius); box-shadow:var(--shadow-md); max-height:90vh; overflow-y:auto; }

        /* Responsive */
        @media (max-width:1100px) { .split-layout { grid-template-columns:1fr; } }
        @media (max-width:768px) { .grid-2 { grid-template-columns:1fr; } .filters-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div>
                <h1>🎁 GESTIÓN DE MARKETING</h1>
                <p>Generar envíos o retirar números de stock</p>
            </div>
            <div class="header-actions">
                <a href="/admin-descargas/">← Dashboard</a>
            </div>
        </header>

        <?php if ($message) echo $message; ?>

        <div class="split-layout">
            <div>
                <div class="card" style="border-color: #00d2ff40;">
                    <div class="section-title blue" style="margin-top:0;">📦 Crear Envío Marketing (0.00€)</div>
                    <p style="font-size: 11px; color:var(--text-2); margin-bottom: 15px;">Genera una orden de trabajo real para el proveedor y la guarda en Producción.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="generar_orden">
                        
                        <div class="form-group">
                            <label>Colección / Serie</label>
                            <select name="series_slug" required>
                                <option value="">Selecciona una serie...</option>
                                <?php foreach ($series as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['slug']); ?>">
                                        <?php echo htmlspecialchars($s['name']); ?> (Quedan: <?php echo $s['available_units']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Unidad N°</label>
                                <input type="number" name="unit_number" required placeholder="Ej: 24">
                            </div>
                            <div class="form-group">
                                <label>Talla</label>
                                <select name="size" required>
                                    <option value="S">S</option>
                                    <option value="M">M</option>
                                    <option value="L">L</option>
                                    <option value="XL">XL</option>
                                    <option value="XXL">XXL</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label>Color</label>
                                <input type="text" name="color" value="negro" required>
                            </div>
                            <div class="form-group">
                                <label>Corte / Fit</label>
                                <select name="type" required>
                                    <option value="standard">Standard Fit</option>
                                    <option value="king-size">King Size</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:20px; border-top: 1px solid #333; padding-top:15px;">
                            <label>Nombre Influencer / Destinatario</label>
                            <input type="text" name="nombre" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email (Opcional - Tracking)</label>
                            <input type="email" name="email" value="marketing@d-cien.es">
                        </div>

                        <div class="form-group">
                            <label>Dirección de Envío Completa</label>
                            <input type="text" name="direccion" required placeholder="Calle, Número, Piso...">
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
                                <label>Teléfono (Opcional)</label>
                                <input type="text" name="telefono" placeholder="Para el transportista">
                            </div>
                        </div>

                        <button type="submit" class="btn" style="width: 100%; margin-top: 10px; background-color: #00d2ff; color: #000;">
                            🚀 GENERAR ORDEN DE TRABAJO
                        </button>
                    </form>
                </div>

                <div class="card">
                    <div class="section-title" style="margin-top:0;">⬇️ Retiro Simple (Sin Envío)</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="retirar">
                        <div class="form-group">
                            <label>Colección / Serie</label>
                            <select name="series_slug" required>
                                <option value="">Selecciona una serie...</option>
                                <?php foreach ($series as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['slug']); ?>">
                                        <?php echo htmlspecialchars($s['name']); ?> (Quedan: <?php echo $s['available_units']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Números a retirar</label>
                            <input type="text" name="numeros" required placeholder="Ej: 7, 14, 21">
                        </div>
                        <div class="form-group">
                            <label>Motivo</label>
                            <input type="text" name="notas" placeholder="Ej: Apartado para sesión de fotos">
                        </div>
                        <button type="submit" class="btn" style="width: 100%; margin-top: 10px; background-color: #f39c12; color: #000;">⚠️ Retirar Stock</button>
                    </form>
                </div>
            </div>

            <div>
                <div class="card" style="padding: 0;">
                    <div style="padding: 15px; border-bottom: 2px solid #333;">
                        <h3 style="margin: 0; font-size: 14px;">📋 Historial de Retiros Simples</h3>
                        <p style="font-size:11px; color:var(--text-2); margin: 5px 0 0 0;">Las órdenes con envío aparecen en tu panel general de Pedidos.</p>
                    </div>

                    <div class="table-container" style="max-height: 800px; border:none; border-radius: 0 0 4px 4px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Serie</th>
                                    <th>Unidad</th>
                                    <th>Fecha</th>
                                    <th>Motivo</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($retiradas)): ?>
                                    <tr><td colspan="5" style="text-align: center; color:var(--text-2); padding: 40px;">No hay retiros simples recientes.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($retiradas as $r): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($r['series_name']); ?></strong></td>
                                            <td><span class="badge-secondary">#<?php echo str_pad($r['unit_number'], 3, '0', STR_PAD_LEFT); ?></span></td>
                                            <td style="font-size: 11px; color:var(--text-2);"><?php echo date('d/m/Y H:i', strtotime($r['sold_at'])); ?></td>
                                            <td style="font-size: 11px; color:var(--text-2);" title="<?php echo htmlspecialchars($r['reserved_by'] ?? '-'); ?>">
                                                <?php echo htmlspecialchars($r['reserved_by'] ?? '-'); ?>
                                            </td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('¿Seguro que quieres devolver la unidad #<?php echo $r['unit_number']; ?> al stock de venta?');" style="margin:0;">
                                                    <input type="hidden" name="action" value="devolver">
                                                    <input type="hidden" name="unit_id" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" class="btn btn-small" style="background: transparent; border: 1px solid #666; color: #ccc;">↩️ Restaurar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <footer style="margin-top: 40px; padding-bottom:20px; text-align:center; font-size:11px; color:var(--text-2);">
            <p>DCIEN · Sistema de Gestión de Marketing</p>
        </footer>
    </div>
</body>
</html>