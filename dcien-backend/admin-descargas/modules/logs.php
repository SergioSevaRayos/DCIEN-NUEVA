<?php
/**
 * DCIEN - GESTOR DE LOGS
 * Visor nativo de logs generados por el sistema (app.log)
 */

require_once 'config.php';
$message = '';

$logDir = dirname(dirname(__DIR__)) . '/logs';
$logFile = $logDir . '/app.log';

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clear_logs') {
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            $message = show_message('success', '✅ Archivo de logs vaciado correctamente.');
        } else {
            $message = show_message('warning', '⚠️ El archivo de logs ya está vacío o no existe.');
        }
    }
}

// Leer logs
$logs = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        // Invertir el array para mostrar los más recientes primero
        $lines = array_reverse($lines);
        foreach ($lines as $line) {
            // Un log típico se ve así: [2026-06-17 14:00:00] Mensaje de error | Context: {"key":"value"}
            preg_match('/^\[(.*?)\] (.*?)(?: \| Context: (.*))?$/', $line, $matches);
            if (!empty($matches)) {
                $logs[] = [
                    'date' => $matches[1],
                    'message' => $matches[2],
                    'context' => $matches[3] ?? null,
                    'raw' => $line
                ];
            } else {
                // Si no coincide con el formato, lo metemos en crudo
                $logs[] = [
                    'date' => '-',
                    'message' => $line,
                    'context' => null,
                    'raw' => $line
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs Sistema - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        .split-layout { display:block; margin-top:20px; align-items:start; }
        .card { background:var(--surface); border:1px solid var(--border); padding:20px; border-radius:var(--radius); box-sizing:border-box; width:100%; margin-bottom:20px; box-shadow:var(--shadow); }
        .table-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:var(--radius); }
        table { width:100%; border-collapse:collapse; min-width:800px; }
        th, td { padding:12px 14px; font-size:12px; text-align:left; border-bottom:1px solid var(--border); }
        th { background:var(--surface-2); color:var(--text-2); font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:600; }
        tr:hover td { background:var(--surface-2); }
        .log-date { font-family:monospace; color:var(--text-2); white-space:nowrap; }
        .log-msg { color:var(--new); font-weight:500; word-break:break-word; }
        .log-ctx { font-family:monospace; font-size:11px; background:#f3f4f6; padding:4px 8px; border-radius:4px; display:block; margin-top:4px; color:#374151; word-break:break-all; }
        .empty-logs { text-align:center; padding:40px; color:var(--text-3); font-size:13px; }
        .header-actions form { display:inline-block; margin:0; }
        
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-box input {
            flex-grow: 1;
            padding: 10px 14px;
            border: 1px solid var(--border-2);
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 13px;
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
                        <h1>📋 LOGS DEL SISTEMA</h1>
                        <p>Visor de registro de errores y depuración (app.log)</p>
                    </div>
                    <div class="header-actions">
                        <a href="/admin-descargas/">← Dashboard</a>
                        <?php if (count($logs) > 0): ?>
                        <form method="POST" onsubmit="return confirm('¿Estás seguro de que deseas vaciar todos los logs? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="submit" class="btn btn-danger">🗑️ Vaciar Logs</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if ($message) echo $message; ?>

                <div class="card">
                    <?php if (count($logs) > 0): ?>
                        <div class="search-box">
                            <input type="text" id="search-input" placeholder="Buscar en los logs por fecha, error o contexto..." onkeyup="filterLogs()">
                        </div>

                        <div class="table-wrap">
                            <table id="logs-table">
                                <thead>
                                    <tr>
                                        <th style="width:160px">Fecha / Hora</th>
                                        <th>Mensaje de Error / Contexto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="log-date"><?php echo htmlspecialchars($log['date']); ?></td>
                                        <td>
                                            <div class="log-msg"><?php echo htmlspecialchars($log['message']); ?></div>
                                            <?php if ($log['context']): ?>
                                                <div class="log-ctx"><?php echo htmlspecialchars($log['context']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-logs">
                            <span style="font-size:24px;display:block;margin-bottom:10px">✨</span>
                            El archivo de logs está vacío. ¡Todo funciona correctamente!
                        </div>
                    <?php endif; ?>
                </div>

                <footer style="margin-top: 40px; padding-bottom:20px; text-align:center; font-size:11px; color:var(--text-2);">
                    <p>DCIEN · Sistema de Gestión de Logs</p>
                </footer>
            </div>
        </div>
    </div>

<script>
function filterLogs() {
    let input = document.getElementById('search-input').value.toLowerCase();
    let rows = document.querySelectorAll('#logs-table tbody tr');

    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
}
</script>
</body>
</html>
