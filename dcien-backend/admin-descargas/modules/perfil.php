<?php
/**
 * DCIEN - Perfil de Atleta
 * Centro de mando exclusivo para un único usuario
 */

require_once 'config.php';
require_once 'email_protocolo.php';

$pdo = get_db_connection();
$message = '';

if (!isset($_GET['id'])) {
    header("Location: usuarios.php");
    exit;
}
$user_id = (int)$_GET['id'];

// Obtener siempre los descuentos activos
$descuentos_activos = $pdo->query("SELECT id, code, description, type, value, series_slug FROM discounts WHERE is_active = 1")->fetchAll();

// PROCESAR POST (ACTUALIZAR DATOS, ASIGNAR/RENOVAR PROTOCOLOS, APUNTES)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Editar Datos Manuales
    if ($action === 'editar') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $can_purchase = isset($_POST['can_purchase']) ? 1 : 0;
        $new_password = trim($_POST['new_password'] ?? '');
        
        try {
            $pdo->prepare("UPDATE users SET username = ?, email = ?, instagram_username = ?, can_purchase = ? WHERE id = ?")
                ->execute([$username, $email, $instagram ?: null, $can_purchase, $user_id]);
            
            if (!empty($new_password)) {
                $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$password_hash, $user_id]);
                $message = show_message('success', '✅ Perfil y contraseña actualizados');
            } else {
                $message = show_message('success', '✅ Perfil de atleta actualizado correctamente');
            }
        } catch (Exception $e) {
            $message = show_message('error', '❌ Error: ' . $e->getMessage());
        }
    }

    // Añadir Apunte / Nota
    if ($action === 'add_note') {
        $note_text = trim($_POST['note_text'] ?? '');
        $admin_name = trim($_POST['admin_name'] ?? 'Admin');
        if (!empty($note_text)) {
            $pdo->prepare("INSERT INTO user_notes (user_id, note_text, created_by) VALUES (?, ?, ?)")
                ->execute([$user_id, $note_text, $admin_name]);
            $message = show_message('success', '✅ Apunte guardado en el diario.');
        }
    }

    // Editar Apunte / Nota
    if ($action === 'edit_note') {
        $note_id = (int)$_POST['note_id'];
        $note_text = trim($_POST['note_text'] ?? '');
        $admin_name = trim($_POST['admin_name'] ?? 'Admin');
        if (!empty($note_text)) {
            $pdo->prepare("UPDATE user_notes SET note_text = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND user_id = ?")
                ->execute([$note_text, $admin_name, $note_id, $user_id]);
            $message = show_message('success', '✅ Apunte modificado.');
        }
    }

    // Asignar Protocolo
    if ($action === 'asignar_descuento' && isset($_POST['discount_id'])) {
        $discount_id = (int)$_POST['discount_id'];
        $enviar_email = isset($_POST['enviar_email']);

        try {
            $check = $pdo->prepare("SELECT id FROM user_discounts WHERE user_id = ? AND discount_id = ? AND used_at IS NULL");
            $check->execute([$user_id, $discount_id]);
            
            if ($check->fetch()) {
                $message = show_message('warning', '⚠️ Este usuario ya tiene este protocolo asignado y pendiente de uso.');
            } else {
                $pdo->prepare("INSERT INTO user_discounts (user_id, discount_id, assigned_at, reminders_sent, last_reminder_at) VALUES (?, ?, NOW(), ?, ?)")
                    ->execute([$user_id, $discount_id, $enviar_email ? 1 : 0, $enviar_email ? date('Y-m-d H:i:s') : null]);
                
                if ($enviar_email) {
                    $stmt_info = $pdo->prepare("SELECT u.email, u.username, d.code, d.description, d.type, d.value, d.series_slug FROM users u JOIN discounts d ON d.id = ? WHERE u.id = ?");
                    $stmt_info->execute([$discount_id, $user_id]);
                    $info = $stmt_info->fetch();
                    
                    if ($info && enviar_email_protocolo($info['email'], $info['username'], $info['code'], $info['description'], $info['type'], $info['value'], $info['series_slug'])) {
                        $message = show_message('success', '✅ Protocolo asignado y 📧 Email enviado al atleta.');
                    } else {
                        $message = show_message('warning', '✅ Protocolo asignado, pero falló el envío del Email.');
                    }
                } else {
                    $message = show_message('success', '✅ Protocolo de validación asignado (Sin Email).');
                }
            }
        } catch (Exception $e) {
            $message = show_message('error', '❌ Error al asignar protocolo: ' . $e->getMessage());
        }
    }

    // Recordatorio de Protocolo (Reenviar Email)
    if ($action === 'recordatorio_descuento' && isset($_POST['discount_id']) && isset($_POST['ud_id'])) {
        $discount_id = (int)$_POST['discount_id'];
        $ud_id = (int)$_POST['ud_id'];
        
        $stmt_info = $pdo->prepare("SELECT u.email, u.username, d.code, d.description, d.type, d.value, d.series_slug FROM users u JOIN discounts d ON d.id = ? WHERE u.id = ?");
        $stmt_info->execute([$discount_id, $user_id]);
        $info = $stmt_info->fetch();
        
        if ($info && enviar_email_protocolo($info['email'], $info['username'], $info['code'], $info['description'], $info['type'], $info['value'], $info['series_slug'])) {
            $pdo->prepare("UPDATE user_discounts SET reminders_sent = reminders_sent + 1, last_reminder_at = NOW() WHERE id = ?")->execute([$ud_id]);
            $message = show_message('success', "✅ Recordatorio reenviado a {$info['email']}. Contador actualizado.");
        } else {
            $message = show_message('error', '❌ Falló el reenvío del Email.');
        }
    }

    // Revocar Protocolo
    if ($action === 'revocar_descuento' && isset($_POST['ud_id'])) {
        $ud_id = (int)$_POST['ud_id'];
        try {
            $pdo->prepare("DELETE FROM user_discounts WHERE id = ? AND used_at IS NULL AND user_id = ?")->execute([$ud_id, $user_id]);
            $message = show_message('success', '✅ Protocolo revocado con éxito.');
        } catch (Exception $e) {
            $message = show_message('error', '❌ Error al revocar: ' . $e->getMessage());
        }
    }

    // Si hay redirección para evitar reenvío de formulario (opcional), pero por ahora lo dejamos recargar.
}

// OBTENER DATOS DEL ATLETA
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT o.id) as total_pedidos, 
           SUM(o.price) as total_gastado 
    FROM users u 
    LEFT JOIN orders o ON o.user_id = u.id 
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$user_id]);
$atleta = $stmt->fetch();

if (!$atleta || empty($atleta['id'])) {
    header("Location: usuarios.php");
    exit;
}

// Obtener protocolos asignados
$stmt_ud = $pdo->prepare("
    SELECT ud.id as ud_id, ud.assigned_at, ud.used_at, ud.reminders_sent, ud.last_reminder_at, d.id as discount_id, d.code, d.type, d.value 
    FROM user_discounts ud 
    JOIN discounts d ON ud.discount_id = d.id 
    WHERE ud.user_id = ? 
    ORDER BY ud.assigned_at DESC
");
$stmt_ud->execute([$user_id]);
$descuentos_usuario = $stmt_ud->fetchAll();

// Obtener historial de pedidos
$stmt_orders = $pdo->prepare("
    SELECT o.id, o.order_number, o.created_at, o.price, o.status, s.name as series_name 
    FROM orders o 
    LEFT JOIN series s ON s.slug = o.series_slug 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt_orders->execute([$user_id]);
$pedidos_usuario = $stmt_orders->fetchAll();

// Obtener notas (Diario)
$stmt_notes = $pdo->prepare("SELECT * FROM user_notes WHERE user_id = ? ORDER BY created_at DESC");
$stmt_notes->execute([$user_id]);
$notas_usuario = $stmt_notes->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil Atleta: <?php echo htmlspecialchars($atleta['username'] ?: 'Sin nombre'); ?> - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        .split-layout { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px; align-items:start; }
        .card { background:var(--surface); border:1px solid var(--border); padding:20px; border-radius:var(--radius); box-sizing:border-box; margin-bottom:20px; box-shadow:var(--shadow); }
        .section-title { font-size:11px; font-weight:600; color:var(--text-2); text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--border); padding-bottom:5px; margin:0 0 15px 0; }
        .status-board { display:flex; gap:15px; margin-bottom: 20px; flex-wrap:wrap; }
        .status-metric { background:#111; border:1px solid #333; padding:15px; flex:1; border-radius:var(--radius); min-width:120px; text-align:center; }
        .status-metric .val { font-size:24px; font-weight:bold; color:#fff; font-family:monospace; margin-bottom:5px; }
        .status-metric .lbl { font-size:10px; color:var(--text-2); text-transform:uppercase; letter-spacing:1px; }
        
        /* Badges */
        .badge-status { padding:4px 12px; font-size:10px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; border-radius:20px; display:inline-block; }
        .bg-success { background:rgba(74,222,128,0.1); color:#4ade80; border:1px solid rgba(74,222,128,0.2); }
        .bg-error { background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.2); }
        .bg-info { background:rgba(59,130,246,0.1); color:#3b82f6; border:1px solid rgba(59,130,246,0.2); }

        .table-mini { width:100%; border-collapse:collapse; font-size:12px; }
        .table-mini th { text-align:left; color:var(--text-2); font-weight:600; padding:10px; border-bottom:1px solid var(--border); }
        .table-mini td { padding:12px 10px; border-bottom:1px solid var(--border); vertical-align:middle; }
        
        .note-card { background:#1a1a1a; border-left:3px solid #3b82f6; padding:15px; margin-bottom:10px; border-radius:4px; position:relative;}
        .note-meta { font-size:10px; color:#666; margin-bottom:8px; display:flex; justify-content:space-between;}
        .note-text { font-size:13px; color:#eee; line-height:1.5; white-space:pre-wrap;}
        .note-edit-btn { background:none; border:none; color:#3b82f6; cursor:pointer; font-size:10px; text-transform:uppercase;}
        .note-edited-badge { font-size:9px; color:#e3a008; font-style:italic; display:block; margin-top:8px;}

        /* Modal Edición de Nota */
        #edit-note-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:1000; align-items:center; justify-content:center; padding:15px; }
        #edit-note-modal.active { display:flex; }
        
        @media (max-width:900px) { .split-layout { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
        <div class="main-content">
            <div class="container">
        <header class="header">
            <div>
                <h1>🛡️ PERFIL DEL ATLETA</h1>
                <p>Centro de mando individual · Usuario #<?php echo $user_id; ?></p>
            </div>
            <div class="header-actions">
                <a href="/admin-descargas/modules/usuarios.php">← Volver al Gestor</a>
            </div>
        </header>

        <?php if ($message) echo $message; ?>

        <!-- STATUS BOARD (CABECERA) -->
        <div class="status-board">
            <div class="status-metric">
                <div class="val"><?php echo $atleta['can_purchase'] ? '<span class="badge-status bg-success">ACTIVO</span>' : '<span class="badge-status bg-error">BLOQUEADO</span>'; ?></div>
                <div class="lbl">Estatus</div>
            </div>
            <div class="status-metric">
                <div class="val">€<?php echo number_format($atleta['total_gastado'] ?? 0, 2); ?></div>
                <div class="lbl">Total Gastado</div>
            </div>
            <div class="status-metric">
                <div class="val"><?php echo $atleta['total_pedidos']; ?></div>
                <div class="lbl">Pedidos</div>
            </div>
            <div class="status-metric">
                <div class="val" style="font-size:16px; margin-top:6px;"><?php echo date('d M Y', strtotime($atleta['created_at'])); ?></div>
                <div class="lbl">Alta</div>
            </div>
        </div>

        <div class="split-layout">
            
            <!-- COLUMNA IZQUIERDA: Configuración, Apuntes -->
            <div>
                <!-- GESTIÓN MANUAL DE DATOS -->
                <div class="card">
                    <h3 class="section-title">✏️ Configuración Manual</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="editar">
                        
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($atleta['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email de Contacto</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($atleta['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Instagram (Sin @)</label>
                            <input type="text" name="instagram" value="<?php echo htmlspecialchars($atleta['instagram_username'] ?: ''); ?>">
                        </div>
                        <div class="form-group" style="background: #222; padding: 12px; border: 1px solid #444; border-radius: 4px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                                <input type="checkbox" name="can_purchase" value="1" <?php echo $atleta['can_purchase'] ? 'checked' : ''; ?> style="width: 18px; height: 18px; flex-shrink:0;">
                                <span style="font-size: 12px; color: #fff; text-transform:none;">Puede Comprar (Validado)</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label style="color:#f39c12;">Nueva Contraseña (Opcional)</label>
                            <input type="password" name="new_password" placeholder="Rellenar sólo para cambiar">
                        </div>
                        <button type="submit" class="btn" style="width:100%;">💾 Guardar Configuración</button>
                    </form>
                </div>

                <!-- DIARIO DEL ATLETA (APUNTES) -->
                <div class="card">
                    <h3 class="section-title">📓 Diario del Atleta</h3>
                    <p style="font-size:11px; color:#888; margin-bottom:15px; line-height:1.4;">Anota comportamientos, hitos o problemas. Los apuntes son modificables pero dejan rastro de edición.</p>
                    
                    <form method="POST" style="margin-bottom:20px; background:#111; padding:15px; border-radius:4px; border:1px solid #333;">
                        <input type="hidden" name="action" value="add_note">
                        <div class="form-group">
                            <label>Nuevo Apunte</label>
                            <textarea name="note_text" rows="3" required style="width:100%; resize:vertical; background:#222; color:#fff; border:1px solid #444; padding:10px;" placeholder="Escribe aquí el apunte..."></textarea>
                        </div>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <input type="text" name="admin_name" value="Admin" placeholder="Tu firma (Ej: Sergio)" required style="width:120px; background:#222; border:1px solid #444; color:#fff; padding:6px 10px;">
                            <button type="submit" class="btn btn-small" style="background:#3b82f6; border:none;">Añadir Apunte</button>
                        </div>
                    </form>

                    <div class="notes-list">
                        <?php if (empty($notas_usuario)): ?>
                            <p style="font-size:11px; color:#555; text-align:center;">El diario está vacío.</p>
                        <?php else: ?>
                            <?php foreach ($notas_usuario as $nota): ?>
                                <div class="note-card">
                                    <div class="note-meta">
                                        <span>✍️ <?php echo htmlspecialchars($nota['created_by']); ?> · <?php echo date('d/m/Y H:i', strtotime($nota['created_at'])); ?></span>
                                        <button class="note-edit-btn" onclick='openEditNote(<?php echo json_encode($nota); ?>)'>[ Editar ]</button>
                                    </div>
                                    <div class="note-text"><?php echo htmlspecialchars($nota['note_text']); ?></div>
                                    <?php if ($nota['updated_at']): ?>
                                        <div class="note-edited-badge">
                                            (Modificado por <?php echo htmlspecialchars($nota['updated_by']); ?> el <?php echo date('d/m/Y H:i', strtotime($nota['updated_at'])); ?>)
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA: Protocolos, Historial -->
            <div>
                <!-- GESTIÓN DE PROTOCOLOS -->
                <div class="card">
                    <h3 class="section-title">🎁 Protocolos y Recompensas</h3>
                    
                    <form method="POST" style="margin-bottom: 20px; background: #1a1a1a; padding: 15px; border: 1px dashed #444; border-radius:4px;">
                        <input type="hidden" name="action" value="asignar_descuento">
                        
                        <div class="form-group" style="margin-bottom:10px;">
                            <select name="discount_id" required style="width: 100%;">
                                <option value="">Asignar un nuevo protocolo...</option>
                                <?php foreach ($descuentos_activos as $da): ?>
                                    <option value="<?php echo $da['id']; ?>">
                                        <?php echo htmlspecialchars($da['code']); ?> 
                                        (<?php echo $da['type'] === 'percent' ? number_format($da['value'],0).'%' : '€'.number_format($da['value'],2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                                <input type="checkbox" name="enviar_email" value="1" checked style="width: 16px; height: 16px; flex-shrink:0;">
                                <span style="font-size: 11px; color:var(--text-2); text-transform:none;">Notificar por Email</span>
                            </label>
                            <button type="submit" class="btn btn-small" style="background:#10b981; border:none; color:#000; font-weight:bold;">➕ Asignar</button>
                        </div>
                    </form>

                    <?php if (!empty($descuentos_usuario)): ?>
                        <div style="overflow-x: auto;">
                            <table class="table-mini">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Status / Comunicación</th>
                                        <th style="text-align:right;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($descuentos_usuario as $du): ?>
                                        <tr>
                                            <td>
                                                <strong style="color:#fff;"><?php echo htmlspecialchars($du['code']); ?></strong><br>
                                                <span style="font-size:9px; color:var(--text-2);"><?php echo $du['type'] === 'percent' ? number_format($du['value'],0).'%' : '€'.number_format($du['value'],2); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($du['used_at']): ?>
                                                    <span class="badge-status bg-info" style="margin-bottom:4px;">Usado el <?php echo date('d/m/Y', strtotime($du['used_at'])); ?></span>
                                                <?php else: ?>
                                                    <span class="badge-status bg-success" style="margin-bottom:4px;">Pendiente</span>
                                                <?php endif; ?>
                                                <br>
                                                <span style="font-size:9px; color:#888;">
                                                    Emails: <strong><?php echo $du['reminders_sent']; ?></strong>
                                                    <?php if($du['last_reminder_at']): ?>
                                                        <br>Últ: <?php echo date('d/m H:i', strtotime($du['last_reminder_at'])); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <?php if (!$du['used_at']): ?>
                                                    <div style="display:flex; justify-content:flex-end; gap:5px;">
                                                        <form method="POST" onsubmit="return confirm('¿Reenviar correo de recordatorio? Se aumentará el contador.');">
                                                            <input type="hidden" name="action" value="recordatorio_descuento">
                                                            <input type="hidden" name="discount_id" value="<?php echo $du['discount_id']; ?>">
                                                            <input type="hidden" name="ud_id" value="<?php echo $du['ud_id']; ?>">
                                                            <button type="submit" class="btn btn-small" style="background:#222; border:1px solid #444; color:#fff;" title="Reenviar Email">📧 Reenviar</button>
                                                        </form>
                                                        <form method="POST" onsubmit="return confirm('¿Revocar este protocolo? El atleta ya no podrá usarlo.');">
                                                            <input type="hidden" name="action" value="revocar_descuento">
                                                            <input type="hidden" name="ud_id" value="<?php echo $du['ud_id']; ?>">
                                                            <button type="submit" class="btn btn-small" style="background:rgba(239,68,68,0.2); border:1px solid rgba(239,68,68,0.4); color:#ef4444;" title="Revocar">✖</button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="font-size:11px; color:var(--text-2); text-align:center; padding:10px;">Sin protocolos asignados.</p>
                    <?php endif; ?>
                </div>

                <!-- HISTORIAL DE PEDIDOS -->
                <div class="card">
                    <h3 class="section-title">🛒 Historial de Pedidos</h3>
                    <?php if (empty($pedidos_usuario)): ?>
                        <p style="font-size:11px; color:#555; text-align:center;">El atleta no ha realizado ninguna compra.</p>
                    <?php else: ?>
                        <table class="table-mini">
                            <thead>
                                <tr>
                                    <th>Ref / Artículo</th>
                                    <th>Importe</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos_usuario as $pedido): ?>
                                    <tr>
                                        <td>
                                            <span style="font-family:monospace; color:#888;">#<?php echo $pedido['id']; ?></span><br>
                                            <strong style="color:#eee; font-size:11px;"><?php echo htmlspecialchars($pedido['series_name'] ?: 'Desconocido'); ?></strong><br>
                                            <span style="font-size:9px; color:#666;"><?php echo date('d/m/Y', strtotime($pedido['created_at'])); ?></span>
                                        </td>
                                        <td><strong style="color:#4ade80;">€<?php echo number_format($pedido['price'], 2); ?></strong></td>
                                        <td><span class="badge-status <?php echo $pedido['status'] === 'pending' ? 'bg-error' : 'bg-success'; ?>"><?php echo strtoupper($pedido['status']); ?></span></td>
                                        <td style="text-align:right;">
                                            <a href="/admin-descargas/modules/ver-pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-small" style="background:#222; border:1px solid #444;">Ver</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <footer style="margin-top: 40px; padding-bottom:20px; text-align:center; font-size:11px; color:var(--text-2);">
            <p>DCIEN · Sistema de Gestión Individual</p>
        </footer>
    </div>

    <!-- Modal Editar Nota -->
    <div id="edit-note-modal">
        <div class="card" style="width:100%; max-width:400px; margin:0;">
            <h3 class="section-title" style="color:#e3a008;">Editar Apunte</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_note">
                <input type="hidden" name="note_id" id="edit_note_id" value="">
                
                <div class="form-group">
                    <textarea name="note_text" id="edit_note_text" rows="4" required style="width:100%; resize:vertical; background:#111; color:#fff; border:1px solid #333; padding:10px;"></textarea>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:15px;">
                    <input type="text" name="admin_name" value="Admin" required style="width:120px; background:#111; border:1px solid #333; color:#fff; padding:6px 10px;">
                    <div>
                        <button type="button" class="btn btn-secondary btn-small" onclick="closeEditNote()">Cancelar</button>
                        <button type="submit" class="btn btn-small" style="background:#e3a008; border:none; color:#000; font-weight:bold;">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditNote(note) {
            document.getElementById('edit_note_id').value = note.id;
            document.getElementById('edit_note_text').value = note.note_text;
            document.getElementById('edit-note-modal').classList.add('active');
        }
        function closeEditNote() {
            document.getElementById('edit-note-modal').classList.remove('active');
        }
    </script>
        </div>
    </div>
</body>
</html>
