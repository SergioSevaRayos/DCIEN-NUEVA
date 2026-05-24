#!/usr/bin/env php
<?php
/**
 * AUDITOR DE PEDIDOS DCIEN v2.2 FINAL
 * Layout optimizado de 3 bloques para impresión en 1 página
 */

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN
// ═══════════════════════════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_NAME', 'u755459505_limited_tees');
define('DB_USER', 'u755459505_limited_tees');
define('ADMIN_EMAIL', 'sergiosevarayos@gmail.com');

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE CONEXIÓN
// ═══════════════════════════════════════════════════════════════

function conectar_db($password) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800"
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        echo "❌ Error de conexión: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE CONSULTA
// ═══════════════════════════════════════════════════════════════

function obtener_pedidos($pdo, $filtros = []) {
    $where = [];
    $params = [];
    
    if (!empty($filtros['series_slug'])) {
        $where[] = "o.series_slug = :series";
        $params['series'] = $filtros['series_slug'];
    }
    
    if (!empty($filtros['desde'])) {
        $where[] = "o.created_at >= :desde";
        $params['desde'] = $filtros['desde'];
    }
    
    if (!empty($filtros['hasta'])) {
        $where[] = "o.created_at <= :hasta";
        $params['hasta'] = $filtros['hasta'] . ' 23:59:59';
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            u.username,
            u.email,
            u.instagram_username,
            s.name as series_name,
            s.price as series_base_price,
            s.images as series_images,
            d.code as discount_code,
            d.type as discount_type,
            d.value as discount_value,
            su.status as unit_status
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN series s ON s.slug = o.series_slug
        LEFT JOIN discounts d ON d.id = o.discount_id
        LEFT JOIN series_units su ON su.series_slug = o.series_slug AND su.unit_number = o.unit_number
        $whereClause
        ORDER BY o.created_at DESC
    ");
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_pedido_detalle($pdo, $order_id) {
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            u.username,
            u.email,
            u.instagram_username,
            s.name as series_name,
            s.description as series_description,
            s.images as series_images,
            s.price as series_base_price,
            d.code as discount_code,
            d.type as discount_type,
            d.value as discount_value,
            d.description as discount_description,
            su.status as unit_status,
            su.sold_at
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN series s ON s.slug = o.series_slug
        LEFT JOIN discounts d ON d.id = o.discount_id
        LEFT JOIN series_units su ON su.series_slug = o.series_slug AND su.unit_number = o.unit_number
        WHERE o.id = ?
    ");
    
    $stmt->execute([$order_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtener_series($pdo) {
    $stmt = $pdo->query("SELECT slug, name FROM series ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_estadisticas($pdo) {
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $stats['total_pedidos'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT SUM(price) FROM orders");
    $stats['total_ventas'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT o.series_slug, s.name, COUNT(*) as total, SUM(o.price) as ventas
        FROM orders o
        LEFT JOIN series s ON s.slug = o.series_slug
        GROUP BY o.series_slug
        ORDER BY total DESC
    ");
    $stats['por_serie'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE discount_id IS NOT NULL");
    $stats['con_descuento'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT u.username, u.instagram_username, COUNT(o.id) as pedidos, SUM(o.price) as total_gastado
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        GROUP BY o.user_id
        ORDER BY pedidos DESC
        LIMIT 5
    ");
    $stats['top_clientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE GENERACIÓN DE ÓRDENES DE TRABAJO
// ═══════════════════════════════════════════════════════════════

function generar_orden_trabajo($pedido) {
    $shipping = json_decode($pedido['shipping_data'], true);
    $html = generar_html_orden($pedido, $shipping);
    
    $filename = "orden_" . $pedido['id'] . "_" . date('Ymd_His') . ".html";
    $outputDir = "/home/u755459505/domains/d-cien.es/public_html/admin-descargas/ordenes";
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
        
        body { 
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.3;
            padding: 10mm;
        }
        
        /* HEADER */
        .header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 4px;
        }
        
        .header p {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 3px;
        }
        
        /* BLOQUE 1: IMAGEN + DETALLES PRODUCTO */
        .block-product {
            display: grid;
            grid-template-columns: 270px 1fr;
            gap: 10px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        
        .series-image {
            width: 100%;
            height: 270px;
            object-fit: cover;
            border: 2px solid #000;
        }
        
        .product-details {
            border: 2px solid #000;
            padding: 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .product-details .serie {
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 1px;
            margin-bottom: 8px;
            text-align: center;
        }
        
        .product-details .unit {
            font-size: 36px;
            font-weight: 900;
            font-family: monospace;
            text-align: center;
            margin: 8px 0;
            letter-spacing: 2px;
        }
        
        .product-specs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-top: 8px;
        }
        
        .spec-box {
            text-align: center;
            padding: 5px;
            background: #f0f0f0;
            border: 1px solid #ccc;
        }
        
        .spec-label {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
            color: #666;
        }
        
        .spec-value {
            font-size: 12px;
            font-weight: 900;
        }
        
        /* BLOQUE 2: CLIENTE + ENVÍO */
        .block-shipping {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        
        .info-box {
            border: 2px solid #000;
            padding: 8px;
        }
        
        .info-box h3 {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #000;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 3px;
        }
        
        .info-label {
            font-weight: bold;
            width: 70px;
            flex-shrink: 0;
            font-size: 9px;
        }
        
        .info-value {
            flex: 1;
            font-size: 9px;
        }
        
        .shipping-address {
            line-height: 1.4;
            font-size: 9px;
        }
        
        /* BLOQUE 3: DATOS ADICIONALES + CHECKLIST */
        .block-additional {
            page-break-inside: avoid;
        }
        
        .pedido-info {
            background: #f5f5f5;
            border: 2px solid #000;
            padding: 6px;
            margin-bottom: 10px;
            font-size: 8px;
        }
        
        .pedido-info strong {
            display: inline-block;
            width: 70px;
        }
        
        .checklist {
            border: 3px solid #000;
            padding: 8px;
        }
        
        .checklist h3 {
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        
        .checklist ul {
            list-style: none;
            padding: 0;
        }
        
        .checklist li {
            padding: 3px 0;
            font-size: 8px;
            border-bottom: 1px dashed #ddd;
        }
        
        .checklist li:last-child {
            border-bottom: none;
        }
        
        .footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 2px solid #000;
            text-align: center;
            font-size: 7px;
            color: #666;
        }
        
        @media print {
            body {
                padding: 8mm;
            }
            @page {
                margin: 0;
                size: A4 portrait;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <div class='header'>
        <h1>DCIEN</h1>
        <p>Orden de Producción #{$pedido['id']}</p>
    </div>

    <!-- BLOQUE 1: IMAGEN + DETALLES PRODUCTO -->
    <div class='block-product'>
        <img src='$series_image' alt='Serie' class='series-image' onerror=\"this.style.display='none'\">
        
        <div class='product-details'>
            <div class='serie'>" . strtoupper($pedido['series_name'] ?? $pedido['series_slug']) . "</div>
            <div class='unit'>#" . str_pad($pedido['unit_number'], 3, '0', STR_PAD_LEFT) . "</div>
            
            <div class='product-specs'>
                <div class='spec-box'>
                    <div class='spec-label'>Talla</div>
                    <div class='spec-value'>" . strtoupper($pedido['size']) . "</div>
                </div>
                <div class='spec-box'>
                    <div class='spec-label'>Color</div>
                    <div class='spec-value'>" . ucfirst($pedido['color']) . "</div>
                </div>
                <div class='spec-box'>
                    <div class='spec-label'>Tipo</div>
                    <div class='spec-value'>" . ucfirst($pedido['type']) . "</div>
                </div>
            </div>
        </div>
    </div>

    <!-- BLOQUE 2: CLIENTE + ENVÍO -->
    <div class='block-shipping'>
        <!-- Cliente -->
        <div class='info-box'>
            <h3>👤 CLIENTE</h3>
            <div class='info-row'>
                <div class='info-label'>Usuario:</div>
                <div class='info-value'>" . htmlspecialchars($pedido['username'] ?? 'N/A') . "</div>
            </div>
            <div class='info-row'>
                <div class='info-label'>Email:</div>
                <div class='info-value'>" . htmlspecialchars($pedido['email']) . "</div>
            </div>
            <div class='info-row'>
                <div class='info-label'>Instagram:</div>
                <div class='info-value'>@" . htmlspecialchars($pedido['instagram_username'] ?? 'N/A') . "</div>
            </div>
        </div>
        
        <!-- Envío -->
        <div class='info-box'>
            <h3>📦 DIRECCIÓN DE ENVÍO</h3>
            <div class='shipping-address'>
                $direccion
            </div>
        </div>
    </div>

    <!-- BLOQUE 3: DATOS ADICIONALES + CHECKLIST -->
    <div class='block-additional'>
        <!-- Info del pedido -->
        <div class='pedido-info'>
            <strong>ID:</strong> #{$pedido['id']} &nbsp;
            <strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($pedido['created_at'])) . " &nbsp;
            <strong>Estado:</strong> " . strtoupper($pedido['unit_status'] ?? 'UNKNOWN') . "
        </div>
        
        <!-- Checklist -->
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

    <!-- FOOTER -->
    <div class='footer'>
        <p><strong>DCIEN</strong> · Sistema de Gestión de Producción · Documento generado " . date('d/m/Y H:i:s') . "</p>
    </div>
</body>
</html>";
}

// ═══════════════════════════════════════════════════════════════
// FUNCIÓN DE ENVÍO DE EMAIL
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
// FUNCIONES DE INTERFAZ
// ═══════════════════════════════════════════════════════════════

function limpiar_pantalla() {
    echo str_repeat("\n", 50);
}

function mostrar_banner() {
    echo str_repeat("═", 70) . "\n";
    echo "   AUDITOR DE PEDIDOS DCIEN v2.2\n";
    echo str_repeat("═", 70) . "\n\n";
}

function listar_pedidos($pedidos) {
    echo "\n📋 PEDIDOS:\n";
    echo str_repeat("-", 120) . "\n";
    printf("%-5s %-12s %-8s %-20s %-15s %-10s %-12s\n",
        '#', 'Serie', 'Unidad', 'Cliente', 'Precio', 'Descuento', 'Fecha'
    );
    echo str_repeat("-", 120) . "\n";
    
    foreach ($pedidos as $idx => $pedido) {
        $cliente = $pedido['username'] ?: substr($pedido['email'], 0, 20);
        $descuento = $pedido['discount_code'] ?: '-';
        $fecha = date('d/m/Y', strtotime($pedido['created_at']));
        
        printf("%-5d %-12s #%-7s %-20s €%-9s %-10s %-12s\n",
            $idx + 1,
            $pedido['series_slug'],
            $pedido['unit_number'],
            substr($cliente, 0, 20),
            $pedido['price'],
            substr($descuento, 0, 10),
            $fecha
        );
    }
    
    echo str_repeat("-", 120) . "\n";
    echo "Total: " . count($pedidos) . " pedidos | Suma: €" . number_format(array_sum(array_column($pedidos, 'price')), 2) . "\n";
}

function mostrar_detalle_pedido($pedido) {
    $shipping = json_decode($pedido['shipping_data'], true);
    
    echo "\n📦 DETALLE DEL PEDIDO #{$pedido['id']}\n";
    echo str_repeat("═", 70) . "\n\n";
    
    echo "📋 INFORMACIÓN GENERAL:\n";
    echo "  ID: #{$pedido['id']}\n";
    echo "  Fecha: " . date('d/m/Y H:i:s', strtotime($pedido['created_at'])) . "\n";
    echo "  Sesión Stripe: " . substr($pedido['stripe_session_id'] ?? 'N/A', 0, 40) . "...\n";
    echo "  Estado unidad: " . strtoupper($pedido['unit_status'] ?? 'unknown') . "\n\n";
    
    echo "👤 CLIENTE:\n";
    echo "  Usuario: " . ($pedido['username'] ?: '(sin username)') . "\n";
    echo "  Email: {$pedido['email']}\n";
    echo "  Instagram: @" . ($pedido['instagram_username'] ?: 'N/A') . "\n\n";
    
    echo "🎽 PRODUCTO:\n";
    echo "  Serie: {$pedido['series_name']} ({$pedido['series_slug']})\n";
    echo "  Unidad: #{$pedido['unit_number']}\n";
    echo "  Talla: " . strtoupper($pedido['size']) . "\n";
    echo "  Color: " . ucfirst($pedido['color']) . "\n";
    echo "  Tipo: " . ucfirst($pedido['type']) . "\n\n";
    
    if ($pedido['discount_code']) {
        echo "🎁 DESCUENTO APLICADO:\n";
        echo "  Código: {$pedido['discount_code']}\n";
        echo "  Tipo: {$pedido['discount_type']}\n";
        echo "  Valor: {$pedido['discount_value']}" . ($pedido['discount_type'] === 'percent' ? '%' : '€') . "\n";
        echo "  Descripción: {$pedido['discount_description']}\n\n";
    }
    
    echo "💰 PRECIO:\n";
    if ($pedido['discount_code']) {
        $original = $pedido['series_base_price'] ?? $pedido['price'];
        $ahorro = $original - $pedido['price'];
        echo "  Precio original: €" . number_format($original, 2) . "\n";
        echo "  Descuento: -€" . number_format($ahorro, 2) . "\n";
    }
    echo "  TOTAL PAGADO: €" . number_format($pedido['price'], 2) . "\n\n";
    
    if (isset($shipping['shipping_address'])) {
        echo "📬 DIRECCIÓN DE ENVÍO:\n";
        $addr = $shipping['shipping_address'];
        echo "  " . ($addr['first_name'] ?? '') . " " . ($addr['last_name'] ?? '') . "\n";
        echo "  " . ($addr['line1'] ?? '') . "\n";
        if (!empty($addr['line2'])) echo "  " . $addr['line2'] . "\n";
        echo "  " . ($addr['postal_code'] ?? '') . " " . ($addr['city'] ?? '') . "\n";
        if (!empty($addr['province'])) echo "  " . $addr['province'] . "\n";
        echo "  " . ($addr['country'] ?? 'ES') . "\n";
        if (!empty($addr['phone'])) echo "  Tel: " . $addr['phone'] . "\n";
    }
    
    echo "\n" . str_repeat("═", 70) . "\n";
}

function menu_principal() {
    echo "\n🔹 MENÚ PRINCIPAL:\n";
    echo "  1. Ver todos los pedidos\n";
    echo "  2. Filtrar pedidos por serie\n";
    echo "  3. Filtrar pedidos por fecha\n";
    echo "  4. Ver detalle de un pedido\n";
    echo "  5. Añadir a órdenes de trabajo\n";
    echo "  6. Estadísticas generales\n";
    echo "  7. Exportar listado a CSV\n";
    echo "  8. Procesar múltiples órdenes\n";
    echo "  0. Salir\n\n";
    
    return trim(readline("Opción: "));
}

function accion_ver_todos($pdo) {
    limpiar_pantalla();
    mostrar_banner();
    
    $pedidos = obtener_pedidos($pdo);
    listar_pedidos($pedidos);
    
    readline("\nPresiona Enter para continuar...");
    return $pedidos;
}

function accion_filtrar_serie($pdo) {
    limpiar_pantalla();
    mostrar_banner();
    
    $series = obtener_series($pdo);
    
    echo "SERIES DISPONIBLES:\n";
    echo str_repeat("-", 40) . "\n";
    foreach ($series as $idx => $serie) {
        echo ($idx + 1) . ". {$serie['name']} ({$serie['slug']})\n";
    }
    echo str_repeat("-", 40) . "\n";
    
    $seleccion = intval(readline("\nSelecciona serie (#): ")) - 1;
    
    if ($seleccion < 0 || $seleccion >= count($series)) {
        echo "❌ Serie inválida\n";
        readline("\nPresiona Enter para continuar...");
        return [];
    }
    
    $serie_slug = $series[$seleccion]['slug'];
    $pedidos = obtener_pedidos($pdo, ['series_slug' => $serie_slug]);
    
    limpiar_pantalla();
    mostrar_banner();
    echo "📊 PEDIDOS DE: {$series[$seleccion]['name']}\n";
    listar_pedidos($pedidos);
    
    readline("\nPresiona Enter para continuar...");
    return $pedidos;
}

function accion_filtrar_fecha($pdo) {
    limpiar_pantalla();
    mostrar_banner();
    
    echo "FILTRAR POR FECHA:\n";
    echo str_repeat("-", 40) . "\n";
    
    $desde = trim(readline("Desde (YYYY-MM-DD) o Enter para omitir: "));
    $hasta = trim(readline("Hasta (YYYY-MM-DD) o Enter para omitir: "));
    
    $filtros = [];
    if ($desde) $filtros['desde'] = $desde;
    if ($hasta) $filtros['hasta'] = $hasta;
    
    $pedidos = obtener_pedidos($pdo, $filtros);
    
    limpiar_pantalla();
    mostrar_banner();
    echo "📊 PEDIDOS FILTRADOS:\n";
    if ($desde) echo "Desde: $desde\n";
    if ($hasta) echo "Hasta: $hasta\n";
    listar_pedidos($pedidos);
    
    readline("\nPresiona Enter para continuar...");
    return $pedidos;
}

function accion_ver_detalle($pdo, $pedidos) {
    limpiar_pantalla();
    mostrar_banner();
    
    if (empty($pedidos)) {
        $pedidos = obtener_pedidos($pdo);
    }
    
    listar_pedidos($pedidos);
    $idx = intval(readline("\nSelecciona pedido (#): ")) - 1;
    
    if ($idx < 0 || $idx >= count($pedidos)) {
        echo "❌ Pedido inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $pedido = obtener_pedido_detalle($pdo, $pedidos[$idx]['id']);
    
    limpiar_pantalla();
    mostrar_banner();
    mostrar_detalle_pedido($pedido);
    
    readline("\nPresiona Enter para continuar...");
}

function accion_generar_orden($pdo, $pedidos) {
    limpiar_pantalla();
    mostrar_banner();
    
    if (empty($pedidos)) {
        $pedidos = obtener_pedidos($pdo);
    }
    
    listar_pedidos($pedidos);
    $idx = intval(readline("\nSelecciona pedido (#): ")) - 1;
    
    if ($idx < 0 || $idx >= count($pedidos)) {
        echo "❌ Pedido inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $pedido = obtener_pedido_detalle($pdo, $pedidos[$idx]['id']);
    
    echo "\n🔄 Generando orden de trabajo...\n";
    $orden_info = generar_orden_trabajo($pedido);
    
    if ($orden_info['success']) {
        echo "✅ Orden generada correctamente\n";
        echo "\n📥 Acceso:\n";
        echo "   Panel: https://d-cien.es/admin-descargas/\n";
        echo "   Archivo: {$orden_info['filename']}\n";
        
        $enviar = strtolower(trim(readline("\n📧 ¿Enviar copia por email? (s/n): ")));
        
        if ($enviar === 's') {
            echo "📨 Enviando email...\n";
            if (enviar_email_orden($pedido, $orden_info)) {
                echo "✅ Email enviado a " . ADMIN_EMAIL . "\n";
            } else {
                echo "❌ Error al enviar email\n";
            }
        }
    } else {
        echo "❌ Error al generar orden\n";
    }
    
    readline("\nPresiona Enter para continuar...");
}

function accion_procesar_multiples($pdo, $pedidos) {
    limpiar_pantalla();
    mostrar_banner();
    
    if (empty($pedidos)) {
        $pedidos = obtener_pedidos($pdo);
    }
    
    listar_pedidos($pedidos);
    
    echo "\n🎯 Seleccionar pedidos (separar con comas, ej: 1,3,5 o 'all' para todos):\n";
    $seleccion = trim(readline("> "));
    
    $indices = [];
    if (strtolower($seleccion) === 'all') {
        $indices = range(0, count($pedidos) - 1);
    } else {
        $indices_input = array_map('trim', explode(',', $seleccion));
        foreach ($indices_input as $idx) {
            $idx = intval($idx) - 1;
            if ($idx >= 0 && $idx < count($pedidos)) {
                $indices[] = $idx;
            }
        }
    }
    
    if (empty($indices)) {
        echo "❌ No se seleccionaron pedidos válidos\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    echo "\n⚠️  Se generarán " . count($indices) . " órdenes de trabajo\n";
    $confirmar = strtolower(trim(readline("¿Continuar? (s/n): ")));
    
    if ($confirmar !== 's') {
        echo "❌ Operación cancelada\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    echo "\n🔄 Procesando pedidos...\n";
    $exitosos = 0;
    $errores = 0;
    
    foreach ($indices as $idx) {
        $pedido = obtener_pedido_detalle($pdo, $pedidos[$idx]['id']);
        $orden_info = generar_orden_trabajo($pedido);
        
        if ($orden_info['success']) {
            $exitosos++;
            echo "  ✓ Orden #{$pedido['id']} generada\n";
        } else {
            $errores++;
            echo "  ✗ Error en pedido #{$pedido['id']}\n";
        }
    }
    
    echo "\n✅ Completado: $exitosos exitosos, $errores errores\n";
    echo "📥 Ver todas en: https://d-cien.es/admin-descargas/\n";
    
    readline("\nPresiona Enter para continuar...");
}

function accion_estadisticas($pdo) {
    limpiar_pantalla();
    mostrar_banner();
    
    echo "📊 ESTADÍSTICAS DE PEDIDOS\n";
    echo str_repeat("═", 70) . "\n\n";
    
    $stats = obtener_estadisticas($pdo);
    
    echo "📈 RESUMEN GENERAL:\n";
    echo "  Total pedidos: {$stats['total_pedidos']}\n";
    echo "  Total ventas: €" . number_format($stats['total_ventas'], 2) . "\n";
    echo "  Promedio por pedido: €" . number_format($stats['total_ventas'] / max($stats['total_pedidos'], 1), 2) . "\n";
    echo "  Pedidos con descuento: {$stats['con_descuento']} (" . round($stats['con_descuento'] / max($stats['total_pedidos'], 1) * 100, 1) . "%)\n\n";
    
    echo "📦 PEDIDOS POR SERIE:\n";
    echo str_repeat("-", 70) . "\n";
    foreach ($stats['por_serie'] as $serie) {
        echo "  {$serie['name']}: {$serie['total']} pedidos (€" . number_format($serie['ventas'], 2) . ")\n";
    }
    
    echo "\n🏆 TOP 5 CLIENTES:\n";
    echo str_repeat("-", 70) . "\n";
    foreach ($stats['top_clientes'] as $idx => $cliente) {
        $nombre = $cliente['username'] ?: '@' . $cliente['instagram_username'];
        echo "  " . ($idx + 1) . ". $nombre - {$cliente['pedidos']} pedidos (€" . number_format($cliente['total_gastado'], 2) . ")\n";
    }
    
    echo "\n" . str_repeat("═", 70) . "\n";
    readline("\nPresiona Enter para continuar...");
}

function accion_exportar_csv($pdo, $pedidos) {
    limpiar_pantalla();
    mostrar_banner();
    
    if (empty($pedidos)) {
        $pedidos = obtener_pedidos($pdo);
    }
    
    $filename = "pedidos_" . date('Ymd_His') . ".csv";
    $outputDir = "/home/u755459505/domains/d-cien.es/public_html/admin-descargas/ordenes";
    $filepath = "$outputDir/$filename";
    
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $fp = fopen($filepath, 'w');
    
    fputcsv($fp, [
        'ID', 'Fecha', 'Serie', 'Unidad', 'Talla', 'Color', 'Tipo',
        'Cliente', 'Email', 'Instagram', 'Precio', 'Descuento', 'Estado', 'Stripe Session'
    ]);
    
    foreach ($pedidos as $pedido) {
        fputcsv($fp, [
            $pedido['id'],
            $pedido['created_at'],
            $pedido['series_slug'],
            $pedido['unit_number'],
            $pedido['size'],
            $pedido['color'],
            $pedido['type'],
            $pedido['username'] ?: 'N/A',
            $pedido['email'],
            $pedido['instagram_username'] ?: 'N/A',
            $pedido['price'],
            $pedido['discount_code'] ?: 'N/A',
            $pedido['unit_status'] ?? 'N/A',
            $pedido['stripe_session_id'] ?? 'N/A'
        ]);
    }
    
    fclose($fp);
    
    echo "✅ Exportado correctamente\n";
    echo "   Total registros: " . count($pedidos) . "\n";
    echo "\n📥 Descarga en:\n";
    echo "   https://d-cien.es/admin-descargas/\n";
    echo "\n📄 Archivo: $filename\n";
    
    readline("\nPresiona Enter para continuar...");
}

function main() {
    limpiar_pantalla();
    mostrar_banner();
    
    echo "Conexión a la base de datos:\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n";
    echo "Email notificaciones: " . ADMIN_EMAIL . "\n\n";
    
    $password = readline("Password: ");
    
    echo "\n🔄 Conectando...\n";
    $pdo = conectar_db($password);
    echo "✅ Conectado correctamente\n\n";
    readline("Presiona Enter para continuar...");
    
    $pedidos_actuales = [];
    
    while (true) {
        limpiar_pantalla();
        mostrar_banner();
        
        $total_pedidos = count(obtener_pedidos($pdo));
        echo "📊 Total pedidos en sistema: $total_pedidos\n";
        
        $opcion = menu_principal();
        
        switch ($opcion) {
            case '1':
                $pedidos_actuales = accion_ver_todos($pdo);
                break;
            case '2':
                $pedidos_actuales = accion_filtrar_serie($pdo);
                break;
            case '3':
                $pedidos_actuales = accion_filtrar_fecha($pdo);
                break;
            case '4':
                accion_ver_detalle($pdo, $pedidos_actuales);
                break;
            case '5':
                accion_generar_orden($pdo, $pedidos_actuales);
                break;
            case '6':
                accion_estadisticas($pdo);
                break;
            case '7':
                accion_exportar_csv($pdo, $pedidos_actuales);
                break;
            case '8':
                accion_procesar_multiples($pdo, $pedidos_actuales);
                break;
            case '0':
                echo "\n👋 ¡Hasta pronto!\n";
                return;
            default:
                echo "\n❌ Opción inválida\n";
                readline("\nPresiona Enter para continuar...");
        }
    }
}

try {
    main();
} catch (Exception $e) {
    echo "\n❌ Error fatal: " . $e->getMessage() . "\n";
    exit(1);
}