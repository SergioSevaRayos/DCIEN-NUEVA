<?php
/**
 * DCIEN - GESTOR DE ACCESO PRIORITARIO
 * Permite dar acceso anticipado a atletas seleccionados antes del lanzamiento público.
 */

require_once 'config.php';
$pdo = get_db_connection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_periodo') {
        $slug          = trim($_POST['series_slug'] ?? '');
        $until         = trim($_POST['priority_until'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');
        if ($slug && $until) {
            $until_dt = str_replace('T', ' ', $until) . ':00';
            $stmt = $pdo->prepare("
                INSERT INTO series_priority (series_slug, priority_until, notes)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE priority_until = VALUES(priority_until), notes = VALUES(notes)
            ");
            $stmt->execute([$slug, $until_dt, $notes ?: null]);
            $message = show_message('success', 'Periodo prioritario configurado hasta <strong>' . htmlspecialchars(date('d/m/Y H:i', strtotime($until_dt))) . '</strong>.');
        } else {
            $message = show_message('error', 'Serie y fecha son obligatorias.');
        }
    }

    if ($action === 'del_periodo') {
        $slug = trim($_POST['series_slug'] ?? '');
        if ($slug) {
            $pdo->prepare("DELETE FROM series_priority WHERE series_slug = ?")->execute([$slug]);
            $pdo->prepare("DELETE FROM series_priority_users WHERE series_slug = ?")->execute([$slug]);
            $message = show_message('success', 'Periodo prioritario eliminado. La serie es accesible para todos.');
        }
    }

    if ($action === 'grant_access') {
        $slug     = trim($_POST['series_slug'] ?? '');
        $user_ids = array_map('intval', $_POST['user_ids'] ?? []);
        $added = 0;
        foreach ($user_ids as $uid) {
            if ($uid <= 0) continue;
            $stmt = $pdo->prepare("INSERT IGNORE INTO series_priority_users (series_slug, user_id) VALUES (?, ?)");
            $stmt->execute([$slug, $uid]);
            $added += $stmt->rowCount();
        }
        $message = show_message('success', "$added atleta(s) añadido(s) al acceso prioritario.");
    }

    if ($action === 'revoke_access') {
        $id = (int)($_POST['priority_user_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM series_priority_users WHERE id = ?")->execute([$id]);
            $message = show_message('success', 'Acceso revocado.');
        }
    }
}

// Datos para la vista
$series_all = $pdo->query("SELECT slug, name, is_active FROM series ORDER BY release_date DESC")->fetchAll();

$selected_slug = $_GET['slug'] ?? ($series_all[0]['slug'] ?? '');

$periodo_activo = null;
$priority_users = [];
$search_results = [];

if ($selected_slug) {
    $stmt = $pdo->prepare("SELECT * FROM series_priority WHERE series_slug = ?");
    $stmt->execute([$selected_slug]);
    $periodo_activo = $stmt->fetch();

    $stmt2 = $pdo->prepare("
        SELECT spu.id, spu.user_id, spu.granted_at, u.username, u.email
        FROM series_priority_users spu
        JOIN users u ON u.id = spu.user_id
        WHERE spu.series_slug = ?
        ORDER BY spu.granted_at DESC
    ");
    $stmt2->execute([$selected_slug]);
    $priority_users = $stmt2->fetchAll();
}

// Búsqueda de atletas
$search_q = trim($_GET['q'] ?? '');
if ($search_q && $selected_slug) {
    $like = '%' . $search_q . '%';
    $already_ids = array_column($priority_users, 'user_id');
    $exclude = $already_ids ? implode(',', array_map('intval', $already_ids)) : '0';
    $stmt3 = $pdo->prepare("
        SELECT id, username, email FROM users
        WHERE (username LIKE ? OR email LIKE ?)
          AND id NOT IN ($exclude)
        ORDER BY username
        LIMIT 30
    ");
    $stmt3->execute([$like, $like]);
    $search_results = $stmt3->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Prioritario - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
    <style>
        body { margin: 0; }
        .split { display: grid; grid-template-columns: 320px 1fr; gap: 20px; margin-top: 20px; align-items: start; }
        .split > div { min-width: 0; }
        .card { background: var(--surface); border: 1px solid var(--border); padding: 20px; border-radius: var(--radius); box-shadow: var(--shadow); margin-bottom: 20px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 10px; color: var(--text-2); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 9px 11px; background: var(--surface); color: var(--text); border: 1px solid var(--border-2); font-size: 13px; box-sizing: border-box; border-radius: var(--radius); font-family: inherit; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
        .section-sep { font-size: 10px; font-weight: 600; color: var(--text-2); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border); padding-bottom: 6px; margin: 20px 0 14px; }
        .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius); }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th, td { padding: 10px 12px; font-size: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--surface-2); color: var(--text-2); font-size: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--surface-2); }
        .badge-on { background: var(--sent-bg); color: var(--sent); border: 1px solid var(--sent-border); padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; }
        .badge-off { background: var(--border); color: var(--text-2); border: 1px solid var(--border-2); padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; }
        .badge-vip { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; }
        .countdown { font-size: 11px; color: var(--text-2); font-family: monospace; }
        .no-data { text-align: center; padding: 30px; color: var(--text-3); font-size: 12px; }
        .series-tab { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .series-tab a { font-size: 11px; padding: 5px 12px; border: 1px solid var(--border); border-radius: var(--radius); color: var(--text-2); text-decoration: none; }
        .series-tab a.active { background: var(--accent); color: var(--accent-text); border-color: var(--accent); }
        .priority-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius); padding: 16px; margin-bottom: 16px; }
        .priority-box.inactive { background: var(--surface-2); border-color: var(--border); }
        @media (max-width: 900px) { .split { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
        <div class="main-content">
            <div class="container">

    <header class="header">
        <div>
            <h1>🔐 ACCESO PRIORITARIO</h1>
            <p>Gestiona quién accede a una serie antes del lanzamiento público</p>
        </div>
        <div class="header-actions">
            <a href="/admin-descargas/">← Dashboard</a>
        </div>
    </header>

    <?php if ($message) echo $message; ?>

    <!-- Selector de serie como tabs -->
    <div class="series-tab">
        <?php foreach ($series_all as $s): ?>
            <a href="prioridad.php?slug=<?= urlencode($s['slug']) ?><?= $search_q ? '&q=' . urlencode($search_q) : '' ?>"
               class="<?= $s['slug'] === $selected_slug ? 'active' : '' ?>">
                <?= e($s['name']) ?>
                <?php
                $has_p = $pdo->prepare("SELECT 1 FROM series_priority WHERE series_slug = ? AND priority_until > NOW()");
                $has_p->execute([$s['slug']]);
                if ($has_p->fetch()) echo ' 🔐';
                ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($selected_slug): ?>
    <div class="split">
        <!-- COLUMNA IZQUIERDA: configuración del periodo -->
        <div>
            <div class="card">
                <h3 style="font-size:12px;color:var(--text-2);text-transform:uppercase;letter-spacing:2px;margin-bottom:16px">
                    Periodo de Acceso
                </h3>

                <?php if ($periodo_activo): ?>
                    <?php
                    $until_ts = strtotime($periodo_activo['priority_until']);
                    $expired  = $until_ts < time();
                    ?>
                    <div class="priority-box <?= $expired ? 'inactive' : '' ?>">
                        <div style="font-size:11px;color:var(--text-2);margin-bottom:4px">Acceso VIP activo hasta:</div>
                        <div style="font-size:16px;font-weight:700;color:var(--text)"><?= date('d/m/Y H:i', $until_ts) ?></div>
                        <?php if ($expired): ?>
                            <div style="font-size:11px;color:var(--new);margin-top:4px">⚠️ Expirado — la serie ya es accesible para todos</div>
                        <?php else: ?>
                            <div class="countdown" id="countdown" data-until="<?= $until_ts ?>">...</div>
                        <?php endif; ?>
                        <?php if ($periodo_activo['notes']): ?>
                            <div style="font-size:11px;color:var(--text-2);margin-top:8px;font-style:italic"><?= e($periodo_activo['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="priority-box inactive">
                        <div style="font-size:12px;color:var(--text-3)">Sin periodo prioritario activo — la serie es accesible para todos los atletas.</div>
                    </div>
                <?php endif; ?>

                <div class="section-sep">Configurar periodo</div>
                <form method="POST">
                    <input type="hidden" name="action" value="set_periodo">
                    <input type="hidden" name="series_slug" value="<?= e($selected_slug) ?>">
                    <div class="form-group">
                        <label>Acceso VIP hasta</label>
                        <input type="datetime-local" name="priority_until" required
                               value="<?= $periodo_activo ? date('Y-m-d\TH:i', strtotime($periodo_activo['priority_until'])) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Notas internas (opcional)</label>
                        <input type="text" name="notes" placeholder="Ej: Preventa atletas CrossFit"
                               value="<?= e($periodo_activo['notes'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">
                        <?= $periodo_activo ? 'Actualizar Periodo' : 'Activar Acceso VIP' ?>
                    </button>
                </form>

                <?php if ($periodo_activo): ?>
                    <form method="POST" style="margin-top:10px" onsubmit="return confirm('¿Eliminar el periodo y revocar todos los accesos?')">
                        <input type="hidden" name="action" value="del_periodo">
                        <input type="hidden" name="series_slug" value="<?= e($selected_slug) ?>">
                        <button type="submit" class="btn btn-secondary" style="width:100%;color:var(--new)">Eliminar periodo</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- COLUMNA DERECHA: atletas con acceso -->
        <div>
            <!-- Búsqueda para añadir atletas -->
            <div class="card">
                <h3 style="font-size:12px;color:var(--text-2);text-transform:uppercase;letter-spacing:2px;margin-bottom:16px">Añadir Atletas</h3>
                <form method="GET" style="display:flex;gap:8px;margin-bottom:14px">
                    <input type="hidden" name="slug" value="<?= e($selected_slug) ?>">
                    <input type="text" name="q" value="<?= e($search_q) ?>" placeholder="Buscar por nombre o email..." style="flex:1;padding:8px 11px;background:var(--surface);color:var(--text);border:1px solid var(--border-2);font-size:13px;border-radius:var(--radius);font-family:inherit">
                    <button type="submit" class="btn">Buscar</button>
                </form>

                <?php if ($search_q && empty($search_results)): ?>
                    <div class="no-data">Sin resultados para "<?= e($search_q) ?>"</div>
                <?php elseif (!empty($search_results)): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="grant_access">
                        <input type="hidden" name="series_slug" value="<?= e($selected_slug) ?>">
                        <div class="table-wrap" style="margin-bottom:10px">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:32px"><input type="checkbox" id="chk-all" onclick="document.querySelectorAll('.chk-user').forEach(c=>c.checked=this.checked)"></th>
                                        <th>Atleta</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $u): ?>
                                        <tr>
                                            <td><input type="checkbox" class="chk-user" name="user_ids[]" value="<?= $u['id'] ?>"></td>
                                            <td style="font-weight:600"><?= e($u['username']) ?></td>
                                            <td style="color:var(--text-2)"><?= e($u['email']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-primary">Conceder Acceso VIP</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Lista de atletas con acceso -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                    <h3 style="font-size:12px;color:var(--text-2);text-transform:uppercase;letter-spacing:2px;margin:0">
                        Atletas con Acceso VIP
                    </h3>
                    <span style="font-size:11px;color:var(--text-2)"><?= count($priority_users) ?> atleta(s)</span>
                </div>

                <?php if (empty($priority_users)): ?>
                    <div class="no-data">Ningún atleta tiene acceso prioritario para esta serie.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Atleta</th>
                                    <th>Email</th>
                                    <th>Concedido</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($priority_users as $pu): ?>
                                    <tr>
                                        <td>
                                            <a href="perfil.php?id=<?= $pu['user_id'] ?>" style="font-weight:600;color:var(--accent)"><?= e($pu['username']) ?></a>
                                        </td>
                                        <td style="color:var(--text-2);font-size:11px"><?= e($pu['email']) ?></td>
                                        <td style="color:var(--text-2);font-size:11px;white-space:nowrap"><?= date('d/m/Y', strtotime($pu['granted_at'])) ?></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('¿Revocar acceso?')">
                                                <input type="hidden" name="action" value="revoke_access">
                                                <input type="hidden" name="priority_user_id" value="<?= $pu['id'] ?>">
                                                <input type="hidden" name="series_slug" value="<?= e($selected_slug) ?>">
                                                <button type="submit" class="btn btn-small" style="color:var(--new)">Revocar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

            </div>
        </div>
    </div>
</body>
<script>
(function() {
    var el = document.getElementById('countdown');
    if (!el) return;
    var until = parseInt(el.dataset.until, 10) * 1000;
    function update() {
        var diff = Math.max(0, until - Date.now());
        if (diff === 0) { el.textContent = 'Expirado'; return; }
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        el.textContent = 'Tiempo restante: ' + (d > 0 ? d + 'd ' : '') + String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    }
    update();
    setInterval(update, 1000);
})();
</script>
</html>
