<?php
/**
 * DCIEN - GESTOR DE SERIES (PRODUCTOS)
 * Reflejo exacto 1:1 de la tabla 'series' en BD.
 * INCLUYE AUTOGENERADOR DE 100 UNIDADES.
 * [Versión 100% Responsive - Layout Corregido]
 */

require_once 'config.php';
$pdo = get_db_connection();
$message = '';

// ═══════════════════════════════════════════════════════════════
// PROCESAR FORMULARIOS (CREAR Y EDITAR)
// ═══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ACCIÓN: CREAR NUEVA SERIE Y SUS 100 UNIDADES
    if ($action === 'crear_serie') {
        $name = trim($_POST['new_name']);
        $slug = trim($_POST['new_slug']);
        $price = (float)$_POST['new_price'];
        
        try {
            // Verificar si el slug ya existe
            $check = $pdo->prepare("SELECT id FROM series WHERE slug = ?");
            $check->execute([$slug]);
            if ($check->fetch()) {
                throw new Exception("El slug '{$slug}' ya existe. Debe ser único.");
            }

            $pdo->beginTransaction();

            // Insertar la serie maestra (Oculta por defecto, y con arrays JSON vacíos)
            $stmt = $pdo->prepare("
                INSERT INTO series (name, slug, price, description, total_units, available_units, sold_units, is_active, images, colors, types, sizes) 
                VALUES (?, ?, ?, 'Descripción pendiente...', 100, 100, 0, 0, '[]', '[]', '[]', '[]')
            ");
            $stmt->execute([$name, $slug, $price]);

            // Generar las 100 unidades en series_units
            $insert_unit = $pdo->prepare("
                INSERT INTO series_units (series_slug, unit_number, status) 
                VALUES (?, ?, 'available')
            ");
            for ($i = 1; $i <= 100; $i++) {
                $insert_unit->execute([$slug, $i]);
            }

            $pdo->commit();
            $message = show_message('success', "✅ Serie '{$name}' creada con éxito junto con sus 100 unidades de stock. ¡Lista para editar!");

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = show_message('error', "❌ Error al crear: " . $e->getMessage());
        }
    }

    // 2. ACCIÓN: TOGGLE RÁPIDO is_active
    if ($action === 'toggle_active' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE series SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?")->execute([$id]);
        $s = $pdo->prepare("SELECT name, is_active FROM series WHERE id = ?");
        $s->execute([$id]);
        $row = $s->fetch();
        $estado = $row['is_active'] ? 'activada (visible en web)' : 'archivada (oculta del frontend)';
        $message = show_message('success', "Serie <strong>" . htmlspecialchars($row['name']) . "</strong> $estado.");
    }

    // 3. ACCIÓN: EDITAR SERIE EXISTENTE
    if ($action === 'editar_serie') {
        $id = (int)$_POST['id'];
        
        $name = trim($_POST['name']);
        $slug = trim($_POST['slug']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $gender = in_array($_POST['gender'] ?? '', ['male','female','unisex']) ? $_POST['gender'] : 'unisex';

        $release_date = !empty($_POST['release_date']) ? str_replace('T', ' ', $_POST['release_date']) . ':00' : null;
        $end_date = !empty($_POST['end_date']) ? str_replace('T', ' ', $_POST['end_date']) . ':00' : null;

        $seo_title = trim($_POST['seo_title']);
        $seo_description = trim($_POST['seo_description']);
        $seo_keywords = trim($_POST['seo_keywords']);

        $images_array = array_values(array_filter(array_map('trim', explode("\n", $_POST['images']))));
        $images_json = json_encode($images_array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $colors_array = array_values(array_filter(array_map('trim', explode(",", $_POST['colors']))));
        $colors_json = json_encode($colors_array, JSON_UNESCAPED_UNICODE);

        $types_array = array_values(array_filter(array_map('trim', explode(",", $_POST['types']))));
        $types_json = json_encode($types_array, JSON_UNESCAPED_UNICODE);

        $sizes_array = array_values(array_filter(array_map('trim', explode(",", $_POST['sizes']))));
        $sizes_json = json_encode($sizes_array, JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $pdo->prepare("
                UPDATE series
                SET name = ?, slug = ?, description = ?, price = ?, is_active = ?, gender = ?, release_date = ?, end_date = ?, images = ?, colors = ?, types = ?, sizes = ?, seo_title = ?, seo_description = ?, seo_keywords = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $slug, $description, $price, $is_active, $gender, $release_date, $end_date, $images_json, $colors_json, $types_json, $sizes_json, $seo_title, $seo_description, $seo_keywords, $id]);
            
            $message = show_message('success', "✅ Serie '{$name}' actualizada correctamente en BD.");
        } catch (Exception $e) {
            $message = show_message('error', "❌ Error al actualizar: " . $e->getMessage());
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// OBTENER DATOS
// ═══════════════════════════════════════════════════════════════

$series = $pdo->query("SELECT * FROM series ORDER BY id DESC")->fetchAll();

$serie_editar = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
    $stmt->execute([$edit_id]);
    $serie_editar = $stmt->fetch();
}

function decode_for_form($json_string, $separator = ", ") {
    if (empty($json_string)) return '';
    $arr = json_decode($json_string, true);
    return is_array($arr) ? implode($separator, $arr) : '';
}

function format_date_for_input($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return '';
    return date('Y-m-d\TH:i', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestor de Series - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
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
    <div class="app-layout">
        <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
        <div class="main-content">
            <div class="container">
        <header class="header">
            <div>
                <h1>👕 GESTIÓN DE SERIES</h1>
                <p>Control total sobre el catálogo de BD</p>
            </div>
            <div class="header-actions">
                <a href="/admin-descargas/">← Dashboard</a>
                <button onclick="document.getElementById('create-modal').style.display='flex'" class="btn">➕ Nueva Serie</button>
            </div>
        </header>

        <?php if ($message) echo $message; ?>

        <div id="create-modal">
            <div class="modal-content">
                <h3 style="color:var(--sent); margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:10px;">➕ Crear Nueva Colección (100 Uds)</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="crear_serie">
                    <div class="form-group">
                        <label>Nombre de la Serie</label>
                        <input type="text" name="new_name" placeholder="Ej: SERIE 03" required>
                    </div>
                    <div class="form-group">
                        <label>Slug (Debe ser idéntico al de series.ts)</label>
                        <input type="text" name="new_slug" placeholder="Ej: serie-03" required>
                    </div>
                    <div class="form-group">
                        <label>Precio Base (€)</label>
                        <input type="number" name="new_price" step="0.01" placeholder="Ej: 50.00" required>
                    </div>
                    <p style="font-size:11px; color:var(--text-2); margin-bottom:15px; line-height: 1.4;">*Al crear, se generarán automáticamente las 100 unidades en la BD y la serie estará OCULTA por defecto.</p>
                    <div style="display:flex; flex-direction: column; gap:10px;">
                        <button type="submit" class="btn" style="background:#3b82f6; border:none;">Crear y Generar Stock</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('create-modal').style.display='none'">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="split-layout">
            <div>
                <div class="card">
                    <?php if ($serie_editar): ?>
                        <h3 style="border-bottom: 2px solid #00ff00; padding-bottom: 10px; margin-bottom: 20px; font-size: 14px;">✏️ Editando: <?php echo htmlspecialchars($serie_editar['name']); ?></h3>
                        
                        <form method="POST" action="series.php?edit=<?php echo $serie_editar['id']; ?>">
                            <input type="hidden" name="action" value="editar_serie">
                            <input type="hidden" name="id" value="<?php echo $serie_editar['id']; ?>">
                            
                            <div class="section-title" style="margin-top: 0;">Información Principal</div>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($serie_editar['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Slug (URL) ⚠️</label>
                                    <input type="text" name="slug" value="<?php echo htmlspecialchars($serie_editar['slug']); ?>" required>
                                </div>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Precio (€)</label>
                                    <input type="number" name="price" step="0.01" value="<?php echo htmlspecialchars($serie_editar['price']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Género</label>
                                    <select name="gender">
                                        <option value="unisex" <?php echo ($serie_editar['gender'] ?? 'unisex') === 'unisex' ? 'selected' : ''; ?>>Unisex</option>
                                        <option value="male"   <?php echo ($serie_editar['gender'] ?? '') === 'male'   ? 'selected' : ''; ?>>Hombre</option>
                                        <option value="female" <?php echo ($serie_editar['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Mujer</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="padding-top: 4px;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="is_active" value="1" <?php echo $serie_editar['is_active'] ? 'checked' : ''; ?> style="width: 18px; height: 18px; flex-shrink: 0;">
                                    <span style="font-size: 13px; color: var(--text);">Visible en web (activa)</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label>Descripción de la Colección</label>
                                <textarea name="description" rows="3" required><?php echo htmlspecialchars($serie_editar['description']); ?></textarea>
                            </div>

                            <div class="section-title">Fechas de Lanzamiento</div>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Release Date</label>
                                    <input type="datetime-local" name="release_date" value="<?php echo format_date_for_input($serie_editar['release_date']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>End Date (Opcional)</label>
                                    <input type="datetime-local" name="end_date" value="<?php echo format_date_for_input($serie_editar['end_date']); ?>">
                                </div>
                            </div>

                            <div class="section-title">Atributos de Producto (Vectores JSON)</div>
                            <div class="form-group">
                                <label>Imágenes (Una URL por línea)</label>
                                <textarea name="images" rows="4"><?php echo htmlspecialchars(decode_for_form($serie_editar['images'], "\n")); ?></textarea>
                            </div>
                            
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Colores (Sep. por comas)</label>
                                    <input type="text" name="colors" value="<?php echo htmlspecialchars(decode_for_form($serie_editar['colors'])); ?>" placeholder="Negro, Blanco">
                                </div>
                                <div class="form-group">
                                    <label>Tallas (Sep. por comas)</label>
                                    <input type="text" name="sizes" value="<?php echo htmlspecialchars(decode_for_form($serie_editar['sizes'])); ?>" placeholder="S, M, L, XL, XXL">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Tipos de Corte (Sep. por comas)</label>
                                <input type="text" name="types" value="<?php echo htmlspecialchars(decode_for_form($serie_editar['types'])); ?>" placeholder="Standard, King Size">
                            </div>

                            <div class="section-title">Metadatos / SEO</div>
                            <div class="form-group">
                                <label>SEO Title</label>
                                <input type="text" name="seo_title" value="<?php echo htmlspecialchars($serie_editar['seo_title']); ?>">
                            </div>
                            <div class="form-group">
                                <label>SEO Description</label>
                                <textarea name="seo_description" rows="2"><?php echo htmlspecialchars($serie_editar['seo_description']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>SEO Keywords</label>
                                <input type="text" name="seo_keywords" value="<?php echo htmlspecialchars($serie_editar['seo_keywords']); ?>">
                            </div>

                            <div class="brand-rule">
                                <strong>REGLA DCIEN:</strong> El stock total está bloqueado por base de datos en <strong>100 unidades exclusivas</strong>. <br><br>
                                <em>Nota: Asegúrate de que los cambios en el Slug coincidan con tu 'series.ts' del frontend si aún no lees desde la API.</em>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" class="btn" style="flex: 1;">💾 Guardar Cambios</button>
                                <a href="series.php" class="btn btn-secondary" style="text-align: center;">Cancelar</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; color:var(--text-2); padding: 80px 0;">
                            <div style="font-size: 50px; margin-bottom: 20px;">👈</div>
                            <p style="font-size: 14px;">Selecciona una serie del catálogo<br>para editar todos sus atributos.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="card">
                    <h3 style="border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; font-size: 14px;">📋 Catálogo</h3>
                    
                    <div class="table-container" style="max-height: 75vh;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Serie & Slug</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Est.</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($series as $s): ?>
                                    <tr style="<?php echo (isset($serie_editar) && $serie_editar['id'] === $s['id']) ? 'background-color: rgba(0,255,0,0.1);' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
                                            <span style="font-size: 10px; color:var(--text-2);"><?php echo htmlspecialchars($s['slug']); ?></span>
                                        </td>
                                        <td>€<?php echo number_format($s['price'], 2); ?></td>
                                        <td>
                                            Disp: <strong><?php echo $s['available_units']; ?></strong><br>
                                            <span style="font-size: 10px; color:var(--text-2);">De: 100</span>
                                        </td>
                                        <td>
                                            <?php if ($s['is_active']): ?>
                                                <span class="badge-status bg-success">ON</span>
                                            <?php else: ?>
                                                <span class="badge-status bg-danger">OFF</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                                <a href="series.php?edit=<?php echo $s['id']; ?>" class="btn btn-small">✏️ Ed.</a>
                                                <form method="POST" style="display:inline">
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                    <button type="submit" class="btn btn-small" title="<?php echo $s['is_active'] ? 'Archivar' : 'Activar'; ?>">
                                                        <?php echo $s['is_active'] ? '🙈 Off' : '👁️ On'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> DCIEN. Gestión de Series.</p>
        </footer>
    </div>
        </div>
    </div>
</body>
</html>