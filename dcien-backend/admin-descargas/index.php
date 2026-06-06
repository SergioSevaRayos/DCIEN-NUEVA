<?php
// Forzar trailing slash para que los paths relativos resuelvan correctamente
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '');
if ($uri === '/admin-descargas') {
    header('Location: /admin-descargas/');
    exit;
}

require_once 'modules/config.php';
$pdo = get_db_connection();

// --- BUSINESS INTELLIGENCE QUERIES ---

// 1. Pedidos
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_pedidos = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$pedidos_mes = $stmt->fetchColumn();

// 2. Facturación
$stmt = $pdo->query("SELECT COALESCE(SUM(price), 0) FROM orders");
$total_ventas = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(price), 0) FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$ventas_mes = $stmt->fetchColumn();

// 3. Usuarios
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1");
$total_usuarios = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM activation_tokens WHERE used_at IS NULL AND expires_at >= NOW()");
$tokens_pendientes = $stmt->fetchColumn();

// 4. Protocolos (Descuentos)
$stmt = $pdo->query("SELECT COUNT(*) FROM user_discounts");
$protocolos_asignados = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM user_discounts WHERE used_at IS NOT NULL");
$protocolos_usados = $stmt->fetchColumn();
$tasa_uso = $protocolos_asignados > 0 ? round(($protocolos_usados / $protocolos_asignados) * 100, 1) : 0;

$stmt = $pdo->query("SELECT COUNT(*) FROM discounts WHERE is_active = 1 AND (valid_until IS NULL OR valid_until >= NOW())");
$descuentos_activos = $stmt->fetchColumn();

// 5. Unidades
$stmt = $pdo->query("SELECT COUNT(*) FROM series_units WHERE status = 'sold'");
$unidades_vendidas = $stmt->fetchColumn();

// --- RANKINGS ---

// Top 5 Atletas (VIPs)
$stmt = $pdo->query("
    SELECT u.username, u.email, SUM(o.price) as total_gastado, COUNT(o.id) as num_pedidos 
    FROM users u 
    JOIN orders o ON u.id = o.user_id 
    GROUP BY u.id 
    ORDER BY total_gastado DESC 
    LIMIT 5
");
$top_atletas = $stmt->fetchAll();

// Top 5 Series Más Vendidas
$stmt = $pdo->query("
    SELECT s.name, COUNT(o.id) as num_ventas 
    FROM series s 
    JOIN orders o ON s.slug = o.series_slug 
    GROUP BY s.id 
    ORDER BY num_ventas DESC 
    LIMIT 5
");
$top_series = $stmt->fetchAll();

// Últimos Movimientos
$stmt = $pdo->query("
    SELECT o.id, o.created_at, o.price, s.name as series_name, o.unit_number, u.username, u.email
    FROM orders o
    LEFT JOIN series s ON s.slug = o.series_slug
    LEFT JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC LIMIT 5
");
$ultimos_pedidos = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard BI - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        .insights-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 30px;
        }
        .bi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .card { 
            background:var(--surface); 
            border:1px solid var(--border); 
            padding:20px; 
            border-radius:var(--radius); 
            box-shadow:var(--shadow); 
        }
        .section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-2);
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
            margin-bottom: 15px;
            margin-top: 0;
        }
        .table-mini { width:100%; border-collapse:collapse; font-size:12px; }
        .table-mini th { text-align:left; color:var(--text-2); font-weight:600; padding:10px 5px; border-bottom:1px solid var(--border); }
        .table-mini td { padding:10px 5px; border-bottom:1px solid var(--border); vertical-align:middle; color:var(--text); }
        
        @media (max-width: 1100px) {
            .insights-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .insights-row { grid-template-columns: 1fr; }
            .bi-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once __DIR__ . '/includes/nav.php'; ?>
        <div class="main-content">
            <div class="container">
                <header class="header">
                    <div>
                        <h1>D C I E N</h1>
                        <p>BUSINESS INTELLIGENCE & CONTROL PANEL</p>
                    </div>
                </header>

                <!-- Tarjetas Principales (KPIs Primarios) -->
                <div class="stats-grid" style="margin-bottom: 20px;">
                    <div class="stat-card">
                        <div class="stat-icon" style="color:#4ade80;">💶</div>
                        <div class="stat-value">€<?php echo number_format($total_ventas, 2); ?></div>
                        <div class="stat-label">Facturación Global</div>
                        <div class="stat-sublabel">Total acumulado</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color:#60a5fa;">📅</div>
                        <div class="stat-value">€<?php echo number_format($ventas_mes, 2); ?></div>
                        <div class="stat-label">Ingresos del Mes</div>
                        <div class="stat-sublabel">Mes actual</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color:#a78bfa;">👥</div>
                        <div class="stat-value"><?php echo $total_usuarios; ?></div>
                        <div class="stat-label">Atletas</div>
                        <div class="stat-sublabel"><?php echo $tokens_pendientes; ?> tokens de invitación</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color:#f472b6;">📦</div>
                        <div class="stat-value"><?php echo $total_pedidos; ?></div>
                        <div class="stat-label">Pedidos</div>
                        <div class="stat-sublabel">+<?php echo $pedidos_mes; ?> este mes</div>
                    </div>
                </div>

                <!-- Segunda fila de KPIs (Secundarios) -->
                <div class="insights-row">
                    <div class="stat-secondary">
                        <div class="stat-sec-label">Ticket Promedio (AOV)</div>
                        <div class="stat-sec-value">€<?php echo $total_pedidos > 0 ? number_format($total_ventas / $total_pedidos, 2) : '0.00'; ?></div>
                    </div>
                    <div class="stat-secondary">
                        <div class="stat-sec-label">Unidades Vendidas</div>
                        <div class="stat-sec-value"><?php echo $unidades_vendidas; ?> pcs</div>
                    </div>
                    <div class="stat-secondary">
                        <div class="stat-sec-label">Éxito de Protocolos</div>
                        <div class="stat-sec-value"><?php echo $tasa_uso; ?>%</div>
                    </div>
                    <div class="stat-secondary">
                        <div class="stat-sec-label">Protocolos Activos</div>
                        <div class="stat-sec-value"><?php echo $descuentos_activos; ?></div>
                    </div>
                </div>

                <!-- Paneles Analíticos (Grid Inferior) -->
                <div class="bi-grid">
                    
                    <!-- Panel Últimos Movimientos -->
                    <div class="card">
                        <h3 class="section-title">📊 Últimos Movimientos</h3>
                        <div class="activity-list" style="margin-top: -10px;">
                            <?php if (!empty($ultimos_pedidos)): ?>
                                <?php foreach ($ultimos_pedidos as $pedido): ?>
                                    <div class="activity-item" style="padding: 12px 0;">
                                        <div class="activity-content">
                                            <div class="activity-title" style="font-weight:bold; font-size:12px;">
                                                #<?php echo $pedido['id']; ?> - <?php echo htmlspecialchars($pedido['series_name']); ?>
                                            </div>
                                            <div class="activity-meta" style="font-size:11px;">
                                                <?php echo htmlspecialchars((string)($pedido['username'] ?: ($pedido['email'] ?: 'Atleta Anónimo'))); ?><br>
                                                <span style="color:#059669; font-weight:bold;">€<?php echo number_format($pedido['price'], 2); ?></span> <span style="color:#888;">· <?php echo date('d/m H:i', strtotime($pedido['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <a href="/admin-descargas/modules/ver-pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-small" style="background:#222; border:1px solid #444; color:#fff;">Ver</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 20px; color: #666; font-size:12px;">Sin movimientos recientes</div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: center; margin-top: 15px;">
                            <a href="/admin-descargas/modules/auditor.php" class="btn btn-secondary btn-small" style="width:100%;">Auditoría Completa</a>
                        </div>
                    </div>

                    <!-- Panel Top Atletas -->
                    <div class="card">
                        <h3 class="section-title">💎 Top Atletas (VIP)</h3>
                        <?php if (empty($top_atletas)): ?>
                            <p style="font-size:12px; color:#666; text-align:center;">No hay datos suficientes.</p>
                        <?php else: ?>
                            <table class="table-mini">
                                <thead>
                                    <tr>
                                        <th>Atleta</th>
                                        <th style="text-align:center;">Pedidos</th>
                                        <th style="text-align:right;">Gasto Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_atletas as $index => $atleta): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $index + 1; ?>. <?php echo htmlspecialchars($atleta['username'] ?: 'Sin nombre'); ?></strong><br>
                                                <span style="font-size:9px; color:#888;"><?php echo htmlspecialchars($atleta['email']); ?></span>
                                            </td>
                                            <td style="text-align:center;"><?php echo $atleta['num_pedidos']; ?></td>
                                            <td style="text-align:right; font-weight:bold; color:#4ade80;">€<?php echo number_format($atleta['total_gastado'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Panel Top Series -->
                    <div class="card">
                        <h3 class="section-title">🔥 Series Más Vendidas</h3>
                        <?php if (empty($top_series)): ?>
                            <p style="font-size:12px; color:#666; text-align:center;">No hay datos suficientes.</p>
                        <?php else: ?>
                            <table class="table-mini">
                                <thead>
                                    <tr>
                                        <th>Serie</th>
                                        <th style="text-align:right;">Unid. Vendidas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_series as $index => $serie): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $index + 1; ?>. <?php echo htmlspecialchars($serie['name']); ?></strong>
                                            </td>
                                            <td style="text-align:right;">
                                                <span class="badge badge-success"><?php echo $serie['num_ventas']; ?> pcs</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <footer class="footer">
                <p>&copy; <?php echo date('Y'); ?> DCIEN. Business Intelligence Dashboard.</p>
            </footer>
        </div>
    </div>
</body>
</html>