<?php
// Forzar trailing slash para que los paths relativos resuelvan correctamente
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '');
if ($uri === '/admin-descargas') {
    header('Location: /admin-descargas/');
    exit;
}

require_once 'modules/config.php';
$pdo = get_db_connection();

// --- LOGICA DE QUERIES (Mantenida exactamente igual) ---
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_pedidos = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$pedidos_semana = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM discounts WHERE is_active = 1 AND (valid_until IS NULL OR valid_until >= NOW())");
$descuentos_activos = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1");
$total_usuarios = $stmt->fetchColumn();

$ordenesDir = __DIR__ . '/ordenes';
$ordenes_generadas = 0;
if (is_dir($ordenesDir)) {
    $archivos = glob($ordenesDir . '/orden_*.html');
    $ordenes_generadas = count($archivos);
}

$stmt = $pdo->query("SELECT COALESCE(SUM(price), 0) FROM orders");
$total_ventas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE discount_id IS NOT NULL");
$pedidos_con_descuento = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM series_units WHERE status = 'sold'");
$unidades_vendidas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM activation_tokens WHERE used_at IS NULL AND expires_at >= NOW()");
$tokens_pendientes = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
    <style>
        .dashboard-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 20px;
            margin-top: 24px;
        }
        .modules-section {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px;
        }
        .insights-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 20px;
        }
        .section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-2);
            margin-bottom: 12px;
        }
        @media (max-width: 1100px) {
            .dashboard-layout { grid-template-columns: 1fr; }
            .insights-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .insights-row { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>D C I E N</h1>
            <p>SISTEMA CENTRAL DE CONTROL</p>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-value"><?php echo $total_pedidos; ?></div>
                <div class="stat-label">Pedidos</div>
                <div class="stat-sublabel">+<?php echo $pedidos_semana; ?> últimos 7 días</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $total_usuarios; ?></div>
                <div class="stat-label">Usuarios</div>
                <div class="stat-sublabel"><?php echo $tokens_pendientes; ?> registros pendientes</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?php echo $descuentos_activos; ?></div>
                <div class="stat-label">Cupones</div>
                <div class="stat-sublabel">Descuentos activos hoy</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📄</div>
                <div class="stat-value"><?php echo $ordenes_generadas; ?></div>
                <div class="stat-label">Órdenes</div>
                <div class="stat-sublabel">Archivos en servidor</div>
            </div>
        </div>

        <div class="dashboard-layout">
            <section>
                <p class="section-title">Módulos de Gestión</p>
                <div class="modules-section">
                    
                    <a href="/admin-descargas/ordenes/index.php" class="module-card">
                        <div class="module-icon">📋</div>
                        <h3>Gestor de Órdenes</h3>
                        <p>Auditoría y documentos HTML</p>
                        <span class="badge badge-primary">Acceder</span>
                    </a>

                    <a href="/admin-descargas/modules/activacion.php" class="module-card">
                        <div class="module-icon">🎟️</div>
                        <h3>Tokens</h3>
                        <p>Generar llaves de acceso</p>
                        <span class="badge badge-secondary">Gestionar</span>
                    </a>

                    <a href="/admin-descargas/modules/descuentos.php" class="module-card">
                        <div class="module-icon">💰</div>
                        <h3>Promociones</h3>
                        <p>Configurar códigos de descuento</p>
                        <span class="badge badge-success">Configurar</span>
                    </a>
                    <a href="/admin-descargas/modules/marketing.php" class="module-card">
                        <div class="module-icon">🎁</div>
                        <h3>Stock Marketing</h3>
                        <p>Retirar unidades para regalos/influencers</p>
                        <span class="badge badge-warning">Gestionar</span>
                    </a>

                    <a href="/admin-descargas/modules/usuarios.php" class="module-card">
                        <div class="module-icon">👥</div>
                        <h3>Base de Datos</h3>
                        <p>Administración de clientes</p>
                        <span class="badge badge-info">Ver lista</span>
                    </a>
                    
                    <a href="/admin-descargas/modules/series.php" class="module-card">
                        <div class="module-icon">👕</div>
                        <h3>Gestor de Series</h3>
                        <p>Editar precios, textos y estado</p>
                        <span class="badge badge-primary">Configurar</span>
                    </a>

                    <a href="/admin-descargas/modules/bonos.php" class="module-card">
                        <div class="module-icon">📲</div>
                        <h3>Bonos QR</h3>
                        <p>Genera accesos con descuento para marketing</p>
                        <span class="badge badge-primary">Gestionar</span>
                    </a>

                    <a href="https://d-cien.es" target="_blank" class="module-card">
                        <div class="module-icon">🌐</div>
                        <h3>Tienda Online</h3>
                        <p>Vista de cliente externa</p>
                        <span class="badge badge-secondary">Visitar</span>
                    </a>
                </div>
            </section>

            <aside class="recent-activity">
                <h3>📊 Últimos Movimientos</h3>
                <?php
                $stmt = $pdo->query("
                    SELECT o.id, o.created_at, o.price, s.name as series_name, o.unit_number, u.username, u.email
                    FROM orders o
                    LEFT JOIN series s ON s.slug = o.series_slug
                    LEFT JOIN users u ON u.id = o.user_id
                    ORDER BY o.created_at DESC LIMIT 5
                ");
                $ultimos_pedidos = $stmt->fetchAll();
                ?>
                <div class="activity-list">
                    <?php if (!empty($ultimos_pedidos)): ?>
                        <?php foreach ($ultimos_pedidos as $pedido): ?>
                            <div class="activity-item">
                                <div class="activity-content">
                                    <div class="activity-title" style="font-weight:bold;">
                                        #<?php echo $pedido['id']; ?> - <?php echo $pedido['series_name']; ?>
                                    </div>
                                    <div class="activity-meta">
                                        <?php echo $pedido['username'] ?: $pedido['email']; ?><br>
                                        €<?php echo number_format($pedido['price'], 2); ?> · <?php echo date('d/m H:i', strtotime($pedido['created_at'])); ?>
                                    </div>
                                </div>
                                <a href="/admin-descargas/modules/ver-pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-small btn-secondary">Ver</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #444;">Sin movimientos</div>
                    <?php endif; ?>
                </div>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="/admin-descargas/ordenes/index.php" class="btn btn-secondary btn-small">Ver historial completo</a>
                </div>
            </aside>

            <div class="insights-row">
                <div class="stat-secondary">
                    <div class="stat-sec-label">Facturación Total</div>
                    <div class="stat-sec-value">€<?php echo number_format($total_ventas, 2); ?></div>
                </div>
                <div class="stat-secondary">
                    <div class="stat-sec-label">Ticket Promedio</div>
                    <div class="stat-sec-value">€<?php echo $total_pedidos > 0 ? number_format($total_ventas / $total_pedidos, 2) : '0.00'; ?></div>
                </div>
                <div class="stat-secondary">
                    <div class="stat-sec-label">Unidades Vendidas</div>
                    <div class="stat-sec-value"><?php echo $unidades_vendidas; ?> pcs</div>
                </div>
                <div class="stat-secondary">
                    <div class="stat-sec-label">Uso de Cupones</div>
                    <div class="stat-sec-value"><?php echo $total_pedidos > 0 ? round(($pedidos_con_descuento / $total_pedidos) * 100, 1) : 0; ?>%</div>
                </div>
            </div>
        </div>

        <footer class="footer">
            <p>DCIEN CORE v2.5 · <?php echo date('Y'); ?></p>
        </footer>
    </div>
</body>
</html>