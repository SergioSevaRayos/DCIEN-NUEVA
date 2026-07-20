<?php
/**
 * DCIEN - Detalle de un email del historial (admin_email_log)
 */

require_once 'config.php';

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM admin_email_log WHERE id = ?");
$stmt->execute([$id]);
$log = $stmt->fetch();

if (!$log) {
    http_response_code(404);
    die('Email no encontrado.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email #<?php echo $log['id']; ?> - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        body { margin: 0; padding: 24px; }
        .meta-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:20px; }
        .meta-item { font-size:12px; }
        .meta-item .label { color:var(--text-2); text-transform:uppercase; letter-spacing:1px; font-size:10px; display:block; margin-bottom:3px; }
        .badge-status { padding:3px 10px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-radius:20px; display:inline-block; }
        .bg-success { background:var(--sent-bg); color:var(--sent); border:1px solid var(--sent-border); }
        .bg-error { background:var(--new-bg); color:var(--new); border:1px solid var(--new-border); }
        iframe { width:100%; height:70vh; border:1px solid var(--border); border-radius:var(--radius); background:#fff; }
    </style>
</head>
<body>
    <header class="header" style="margin-bottom:20px;">
        <div>
            <h1>📧 Email #<?php echo $log['id']; ?></h1>
        </div>
        <div class="header-actions">
            <a href="email-log.php">← Volver al historial</a>
        </div>
    </header>

    <div class="card">
        <div class="meta-grid">
            <div class="meta-item"><span class="label">Destinatario</span><?php echo e($log['recipient_username'] ?: 'N/A'); ?> — <?php echo e($log['recipient_email']); ?></div>
            <div class="meta-item"><span class="label">Fecha</span><?php echo format_date($log['created_at']); ?></div>
            <div class="meta-item"><span class="label">Tipo</span><?php echo e($log['email_type']); ?></div>
            <div class="meta-item">
                <span class="label">Estado</span>
                <?php if ($log['status'] === 'sent'): ?>
                    <span class="badge-status bg-success">ENVIADO</span>
                <?php else: ?>
                    <span class="badge-status bg-error">FALLIDO</span>
                <?php endif; ?>
            </div>
            <div class="meta-item" style="grid-column: 1 / -1;"><span class="label">Asunto</span><?php echo e($log['subject']); ?></div>
            <?php if ($log['error_message']): ?>
                <div class="meta-item" style="grid-column: 1 / -1; color:var(--new);"><span class="label">Error</span><?php echo e($log['error_message']); ?></div>
            <?php endif; ?>
        </div>

        <p style="font-size:10px; color:var(--text-2); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Contenido enviado</p>
        <iframe srcdoc="<?php echo e($log['body_html'] ?? ''); ?>" sandbox=""></iframe>
    </div>
</body>
</html>
