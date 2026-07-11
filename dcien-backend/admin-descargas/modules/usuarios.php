<?php
/**
 * DCIEN - Gestor de Atletas (Lista y Asignación Masiva)
 * [Versión Refactorizada - Centro de Mando]
 */

require_once 'config.php';
require_once 'email_protocolo.php';
require_once 'email_campana.php';

$pdo = get_db_connection();
$message = '';

// Obtener siempre los descuentos activos para asignación masiva
$descuentos_activos = $pdo->query("SELECT id, code, description, type, value, series_slug FROM discounts WHERE is_active = 1 AND code NOT LIKE 'BONO_%'")->fetchAll();

// PROCESAR FORMULARIOS POST (Solo masivo y eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'asignar_masivo' && !empty($_POST['user_ids']) && !empty($_POST['discount_id'])) {
        $user_ids_array = explode(',', $_POST['user_ids']);
        $discount_id = (int)$_POST['discount_id'];
        $enviar_email = isset($_POST['enviar_email']);

        $stmt_disc = $pdo->prepare("SELECT * FROM discounts WHERE id = ?");
        $stmt_disc->execute([$discount_id]);
        $discount = $stmt_disc->fetch();

        if ($discount) {
            $asignados = 0; $enviados = 0; $omitidos = 0;

            foreach ($user_ids_array as $uid) {
                $uid = (int)trim($uid);
                if ($uid <= 0) continue;

                $check = $pdo->prepare("SELECT id FROM user_discounts WHERE user_id = ? AND discount_id = ? AND used_at IS NULL");
                $check->execute([$uid, $discount_id]);
                
                if ($check->fetch()) {
                    $omitidos++; 
                    continue;
                }

                $pdo->prepare("INSERT INTO user_discounts (user_id, discount_id, assigned_at, reminders_sent, last_reminder_at) VALUES (?, ?, NOW(), ?, ?)")
                    ->execute([$uid, $discount_id, $enviar_email ? 1 : 0, $enviar_email ? date('Y-m-d H:i:s') : null]);
                $asignados++;

                if ($enviar_email) {
                    $stmt_u = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
                    $stmt_u->execute([$uid]);
                    $u = $stmt_u->fetch();

                    if ($u && enviar_email_protocolo($u['email'], $u['username'], $discount['code'], $discount['description'], $discount['type'], $discount['value'], $discount['series_slug'])) {
                        $enviados++;
                    }
                }
            }

            $resultado = "✅ Operación completada: <strong>$asignados asignados</strong>.";
            if ($enviar_email) $resultado .= " 📧 <strong>$enviados emails</strong> enviados.";
            if ($omitidos > 0) $resultado .= " ⚠️ <strong>$omitidos omitidos</strong> (ya lo tenían).";
            
            $message = show_message('success', $resultado);
        }
    }

    if ($action === 'enviar_comunicado' && !empty($_POST['user_ids'])) {
        $user_ids_array = explode(',', $_POST['user_ids']);
        $subject = trim($_POST['subject']);
        $titulo = trim($_POST['titulo']);
        $cuerpo = trim($_POST['cuerpo']);
        $cta_texto = trim($_POST['cta_texto']);
        $cta_link = trim($_POST['cta_link']);

        $enviados = 0;
        $errores = 0;

        foreach ($user_ids_array as $uid) {
            $uid = (int)trim($uid);
            if ($uid <= 0) continue;

            $stmt_u = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
            $stmt_u->execute([$uid]);
            $u = $stmt_u->fetch();

            if ($u) {
                if (enviar_email_campana($u['email'], $u['username'], $subject, $titulo, $cuerpo, $cta_texto, $cta_link)) {
                    $enviados++;
                } else {
                    $errores++;
                }
            }
        }

        if ($errores > 0) {
            $message = show_message('warning', "✅ Campaña lanzada: <strong>$enviados enviados</strong>, ⚠️ $errores fallos.");
        } else {
            $message = show_message('success', "🚀 Campaña lanzada con éxito a <strong>$enviados atletas</strong>.");
        }
    }

    if ($action === 'eliminar_usuario' && !empty($_POST['user_id'])) {
        $uid_to_delete = (int)$_POST['user_id'];
        if ($uid_to_delete > 0) {
            try {
                // Limpieza en cascada manual por seguridad (evitar constraints)
                $pdo->prepare("DELETE FROM user_discounts WHERE user_id = ?")->execute([$uid_to_delete]);
                $pdo->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$uid_to_delete]);
                
                $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt_del->execute([$uid_to_delete]);
                
                if ($stmt_del->rowCount() > 0) {
                    $message = show_message('success', "✅ Atleta #$uid_to_delete eliminado correctamente y de forma permanente.");
                } else {
                    $message = show_message('error', "❌ No se pudo encontrar al atleta #$uid_to_delete.");
                }
            } catch (Exception $e) {
                $message = show_message('error', "❌ Error crítico al eliminar atleta: " . $e->getMessage());
            }
        }
    }
}

// LÓGICA DE FILTRADO AVANZADO
$search = $_GET['search'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$min_pedidos = $_GET['min_pedidos'] ?? '';

$where = ['1=1'];
$having = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(u.username LIKE :search_u OR u.email LIKE :search_e OR u.instagram_username LIKE :search_ig)";
    $params['search_u']  = "%$search%";
    $params['search_e']  = "%$search%";
    $params['search_ig'] = "%$search%";
}
if ($desde) {
    $where[] = "u.created_at >= :desde";
    $params['desde'] = $desde . ' 00:00:00';
}
if ($hasta) {
    $where[] = "u.created_at <= :hasta";
    $params['hasta'] = $hasta . ' 23:59:59';
}
if ($min_pedidos !== '') {
    $having[] = "total_pedidos >= :min_pedidos";
    $params['min_pedidos'] = (int)$min_pedidos;
}

$whereClause = implode(' AND ', $where);
$havingClause = implode(' AND ', $having);

$stmt = $pdo->prepare("
    SELECT u.*, COUNT(DISTINCT o.id) as total_pedidos, SUM(o.price) as total_gastado
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE $whereClause
    GROUP BY u.id
    HAVING $havingClause
    ORDER BY u.created_at DESC LIMIT 200
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$total_mostrados = count($usuarios);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestor de Atletas - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:15px; }
        .checkbox-column { width:40px; text-align:center; }
        .card { background:var(--surface); border:1px solid var(--border); padding:20px; border-radius:var(--radius); box-sizing:border-box; width:100%; margin-bottom:20px; box-shadow:var(--shadow); }
        .badge-status { padding:3px 10px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-radius:20px; display:inline-block; }
        .bg-success { background:var(--sent-bg); color:var(--sent); border:1px solid var(--sent-border); }
        .bg-error { background:var(--new-bg); color:var(--new); border:1px solid var(--new-border); }

        #bulk-modal, #comunicado-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; padding:15px; }
        .modal-content { background:var(--surface); border:1px solid var(--border); padding:25px; width:100%; max-width:500px; border-radius:var(--radius); box-shadow:var(--shadow-md); max-height:90vh; overflow-y:auto; }
        @media (max-width:768px) { .filters-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
        <div class="main-content">
            <div class="container">
        <header class="header">
            <div>
                <h1>👥 GESTOR DE ATLETAS</h1>
                <p>Base de datos general de clientes y filtros para campañas masivas</p>
            </div>
            <div class="header-actions">
                <a href="/admin-descargas/">← Dashboard</a>
            </div>
        </header>

        <?php if ($message) echo $message; ?>

        <!-- MODAL ASIGNACIÓN MASIVA -->
        <div id="bulk-modal">
            <div class="modal-content">
                <h3 style="color:var(--sent); margin-bottom:10px; border-bottom:1px solid #333; padding-bottom:10px;">🎁 Asignación Masiva</h3>
                <p style="color:var(--text-2); font-size:12px; margin-bottom:20px;">Aplicar protocolo a <strong id="bulk-count" style="color:var(--text);">0</strong> atletas.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="asignar_masivo">
                    <input type="hidden" name="user_ids" id="bulk_user_ids" value="">
                    
                    <div class="form-group">
                        <label>Selecciona el Protocolo (Descuento)</label>
                        <select name="discount_id" required>
                            <option value="">-- Elige un crédito --</option>
                            <?php foreach ($descuentos_activos as $da): ?>
                                <option value="<?php echo $da['id']; ?>">
                                    <?php echo htmlspecialchars($da['code']); ?> 
                                    (<?php echo $da['type'] === 'percent' ? number_format($da['value'],0).'%' : '€'.number_format($da['value'],2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="background: #222; padding: 15px; border: 1px dashed #444; border-radius: 4px; margin-top: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="enviar_email" value="1" checked style="width: 18px; height: 18px; flex-shrink: 0;">
                            <span style="font-size: 12px; color: #fff; text-transform:none; line-height: 1.4;">📧 Enviar Notificación por Email a los atletas</span>
                        </label>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px; margin-top: 25px;">
                        <button type="submit" class="btn" style="background:#3b82f6; border:none;">Lanzar Campaña</button>
                        <button type="button" class="btn btn-secondary" onclick="closeBulkModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL COMUNICADO (EMAIL MARKETING) -->
        <div id="comunicado-modal">
            <div class="modal-content" style="max-width:600px;">
                <h3 style="color:#e3a008; margin-bottom:10px; border-bottom:1px solid #333; padding-bottom:10px;">📧 Redactar Comunicado</h3>
                <p style="color:var(--text-2); font-size:12px; margin-bottom:20px;">Enviar email a <strong id="comunicado-count" style="color:var(--text);">0</strong> atletas.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="enviar_comunicado">
                    <input type="hidden" name="user_ids" id="comunicado_user_ids" value="">
                    
                    <div class="form-group">
                        <label>Asunto del Correo (Subject)</label>
                        <input type="text" name="subject" placeholder="Ej: DCIEN | Nueva Misión: Comparte y Gana" required>
                    </div>

                    <div class="form-group">
                        <label>Título (Interior del correo)</label>
                        <input type="text" name="titulo" placeholder="Ej: MISIÓN DE RECLUTAMIENTO" required>
                    </div>

                    <div class="form-group">
                        <label>Cuerpo del Mensaje</label>
                        <textarea name="cuerpo" rows="5" placeholder="Escribe aquí tu mensaje... Puedes usar saltos de línea para crear párrafos." required style="resize:vertical;"></textarea>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; background:#111; padding:15px; border:1px solid #333; border-radius:4px; margin-top:15px;">
                        <div class="form-group" style="margin:0;">
                            <label>Texto del Botón (Opcional)</label>
                            <input type="text" name="cta_texto" placeholder="Ej: Ver Post de Instagram">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>URL del Botón (Opcional)</label>
                            <input type="text" name="cta_link" placeholder="Ej: https://instagram.com/p/...">
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px; margin-top: 25px;">
                        <button type="submit" class="btn" style="background:#e3a008; color:#000; font-weight:bold; border:none;">🚀 Enviar a Todos</button>
                        <button type="button" class="btn btn-secondary" onclick="closeComunicadoModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="card">
            <form method="GET">
                <div class="filters-grid">
                    <div class="form-group" style="margin: 0;">
                        <label>Buscar Texto</label>
                        <input type="text" name="search" placeholder="Username, email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label>Reg. Desde</label>
                        <input type="date" name="desde" value="<?php echo htmlspecialchars($desde); ?>">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label>Reg. Hasta</label>
                        <input type="date" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label>Mínimo Pedidos</label>
                        <input type="number" name="min_pedidos" placeholder="Ej: 1" min="0" value="<?php echo htmlspecialchars($min_pedidos); ?>">
                    </div>
                </div>
                <div class="filter-actions" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; border-top: 1px solid #333; padding-top: 15px; flex-wrap: wrap; gap: 15px;">
                    <div style="display:flex; gap:10px; flex-wrap: wrap;">
                        <button type="submit" class="btn">🔍 Filtrar</button>
                        <a href="usuarios.php" class="btn btn-secondary" style="text-align: center;">✕ Limpiar</a>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" onclick="openComunicadoModal()" class="btn" style="background:#222; color:#fff; border:1px solid #e3a008;">📧 Redactar Comunicado</button>
                        <button type="button" onclick="openBulkModal()" class="btn" style="background:#10b981; color:#000; font-weight:bold; border:none;">🎁 Asignar a Seleccionados</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- TABLA PRINCIPAL -->
        <div class="card">
            <h3 style="border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; font-size: 14px;">📋 Base de Datos (<?php echo $total_mostrados; ?>)</h3>
            
            <div class="table-container">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid #333;">
                            <th class="checkbox-column" style="padding:15px 10px;">
                                <input type="checkbox" onclick="toggleAll(this)" style="transform: scale(1.2);">
                            </th>
                            <th>ID / Perfil</th>
                            <th>Contacto</th>
                            <th>Métricas</th>
                            <th>Acceso</th>
                            <th style="text-align:right;">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr><td colspan="6" style="text-align: center; padding: 40px; color:var(--text-2);">No se encontraron atletas con esos filtros</td></tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                                <tr style="border-bottom:1px solid #222;">
                                    <td class="checkbox-column" style="padding:15px 10px;">
                                        <input type="checkbox" class="user-checkbox" value="<?php echo $u['id']; ?>" style="transform: scale(1.2);">
                                    </td>
                                    <td>
                                        <strong>#<?php echo $u['id']; ?></strong><br>
                                        <?php echo htmlspecialchars($u['username'] ?: '-'); ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px;"><?php echo htmlspecialchars($u['email']); ?></span><br>
                                        <span style="font-size: 11px; color:var(--text-2);">@<?php echo htmlspecialchars($u['instagram_username'] ?: '-'); ?></span>
                                    </td>
                                    <td>
                                        Ped: <strong><?php echo $u['total_pedidos']; ?></strong> (<?php echo number_format($u['total_gastado']??0,2);?>€)<br>
                                        <span style="font-size: 10px; color:var(--text-2);"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($u['can_purchase']): ?>
                                            <span class="badge-status bg-success">VAL</span>
                                        <?php else: ?>
                                            <span class="badge-status bg-error">BLOQ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('⚠️ ¿Estás COMPLETAMENTE seguro de que deseas ELIMINAR PERMANENTEMENTE a este atleta? Se borrarán sus pedidos y códigos asignados. Esta acción no tiene vuelta atrás.');">
                                            <input type="hidden" name="action" value="eliminar_usuario">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn" style="background:#ef4444; color:#fff; border:none; padding:8px 10px; border-radius:4px; margin-right:5px; cursor:pointer;" title="Eliminar Atleta permanentemente">🗑️</button>
                                        </form>
                                        <a href="/admin-descargas/modules/perfil.php?id=<?php echo $u['id']; ?>" class="btn" style="background:#3b82f6; border:none; padding:8px 15px; border-radius:4px;">Ver Perfil 👉</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer style="margin-top: 40px; padding-bottom:20px; text-align:center; font-size:11px; color:var(--text-2);">
            <p>DCIEN · Base de Datos General</p>
        </footer>
    </div>

    <script>
    function toggleAll(cb) {
        document.querySelectorAll('.user-checkbox').forEach(c => c.checked = cb.checked);
    }

    function openBulkModal() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (checked.length === 0) {
            alert('⚠️ Selecciona al menos un atleta marcando la casilla de la izquierda.');
            return;
        }
        
        const ids = Array.from(checked).map(c => c.value);
        document.getElementById('bulk_user_ids').value = ids.join(',');
        document.getElementById('bulk-count').innerText = ids.length;
        document.getElementById('bulk-modal').style.display = 'flex';
    }

    function closeBulkModal() {
        document.getElementById('bulk-modal').style.display = 'none';
    }

    function openComunicadoModal() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (checked.length === 0) {
            alert('⚠️ Selecciona al menos un atleta para enviarle un comunicado.');
            return;
        }
        
        const ids = Array.from(checked).map(c => c.value);
        document.getElementById('comunicado_user_ids').value = ids.join(',');
        document.getElementById('comunicado-count').innerText = ids.length;
        document.getElementById('comunicado-modal').style.display = 'flex';
    }

    function closeComunicadoModal() {
        document.getElementById('comunicado-modal').style.display = 'none';
    }
    </script>
        </div>
    </div>
</body>
</html>