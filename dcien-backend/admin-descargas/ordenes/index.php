<?php
/**
 * DCIEN — Pipeline de Órdenes
 */

require_once '../modules/config.php';
// vendor: produccion primero (dcien-backend/vendor/), fallback local (dcien-backend/vendor/ via relative)
$_autoload_prod  = __DIR__ . '/../../../dcien-backend/vendor/autoload.php';
$_autoload_local = __DIR__ . '/../../vendor/autoload.php';
require_once (file_exists($_autoload_prod) ? $_autoload_prod : $_autoload_local);
$pdo = get_db_connection();

$message        = '';
$estado_actual  = $_GET['estado'] ?? 'nuevos';
$ESTADOS_NUEVOS = ['paid', 'pending', 'pendiente'];

// ═══════════════════════════════════════════════
// HELPERS — dirección de envío
// ═══════════════════════════════════════════════

function parse_shipping(array $shipping): array {
    if (isset($shipping['shipping_address'])) {
        $a = $shipping['shipping_address'];
        return [
            'nombre' => trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
            'linea1' => $a['line1'] ?? '',
            'linea2' => $a['line2'] ?? '',
            'cp'     => $a['postal_code'] ?? '',
            'ciudad' => $a['city'] ?? '',
            'prov'   => $a['province'] ?? '',
            'pais'   => $a['country'] ?? 'ES',
            'tel'    => $a['phone'] ?? '',
            'email'  => $a['email'] ?? '',
        ];
    }
    return [
        'nombre' => trim(($shipping['firstName'] ?? '') . ' ' . ($shipping['lastName'] ?? '')),
        'linea1' => $shipping['address'] ?? '',
        'linea2' => $shipping['addressExtra'] ?? '',
        'cp'     => $shipping['postalCode'] ?? '',
        'ciudad' => $shipping['city'] ?? '',
        'prov'   => $shipping['province'] ?? '',
        'pais'   => $shipping['country'] ?? 'ES',
        'tel'    => $shipping['phone'] ?? '',
        'email'  => $shipping['email'] ?? '',
    ];
}

function addr_html(array $addr): string {
    $parts = array_filter([
        e($addr['nombre']),
        e($addr['linea1']),
        $addr['linea2'] ? e($addr['linea2']) : '',
        e($addr['cp']) . ' ' . e($addr['ciudad']),
        $addr['prov'] ? e($addr['prov']) : '',
        e($addr['pais']),
        $addr['tel']   ? '<strong>Tel:</strong> ' . e($addr['tel'])   : '',
        $addr['email'] ? e($addr['email']) : '',
    ]);
    return implode('<br>', $parts);
}

// ═══════════════════════════════════════════════
// GENERACIÓN HTML ORDEN DE TRABAJO
// ═══════════════════════════════════════════════

function get_order_items(PDO $pdo, array $pedido): array {
    if (!empty($pedido['is_cart_order'])) {
        $st = $pdo->prepare("
            SELECT oi.*, s.name as series_name
            FROM order_items oi
            LEFT JOIN series s ON s.slug = oi.series_slug
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $st->execute([$pedido['id']]);
        $items = $st->fetchAll();
        if (!empty($items)) return $items;
    }
    return [[
        'series_slug' => $pedido['series_slug'],
        'series_name' => $pedido['series_name'] ?? '',
        'unit_number' => $pedido['unit_number'],
        'size'        => $pedido['size'],
        'color'       => $pedido['color'],
        'type'        => $pedido['type'],
        'unit_price'  => $pedido['price'],
    ]];
}

function img_b64(string $serie_slug, string $filename, int $maxW = 320): string {
    // local: dcien-backend/admin-descargas/ordenes/ → public/images/
    // prod:  public_html/admin-descargas/ordenes/ → public_html/images/
    $base_local = realpath(__DIR__ . '/../../../public');
    $base_prod  = realpath(__DIR__ . '/../../');
    $base_dir   = ($base_local && is_dir($base_local . "/images")) ? $base_local : $base_prod;
    $base = $base_dir . "/images/series/{$serie_slug}/";
    $path = '';
    foreach ([$filename, str_replace('.png', '.webp', $filename), str_replace('.png', '.jpg', $filename)] as $f) {
        if (file_exists($base . $f)) { $path = $base . $f; break; }
    }
    if (!$path) return '';

    $mime = mime_content_type($path);

    // Redimensionar con GD para reducir peso y respetar proporciones
    $src = null;
    if ($mime === 'image/png')  $src = @imagecreatefrompng($path);
    elseif ($mime === 'image/webp') $src = @imagecreatefromwebp($path);
    elseif (in_array($mime, ['image/jpeg','image/jpg'])) $src = @imagecreatefromjpeg($path);

    if ($src) {
        $ow = imagesx($src); $oh = imagesy($src);
        $nw = min($maxW, $ow);
        $nh = (int)round($oh * $nw / $ow);
        $dst = imagecreatetruecolor($nw, $nh);
        // Mantener canal alpha (PNG con fondo transparente)
        imagealphablending($dst, false); imagesavealpha($dst, true);
        $trans = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $trans);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
        ob_start(); imagepng($dst, null, 7); $data = ob_get_clean();
        imagedestroy($src); imagedestroy($dst);
        return 'data:image/png;base64,' . base64_encode($data);
    }

    // Fallback sin GD
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
}

function generar_orden_trabajo(array $pedido, PDO $pdo): array {
    $num_pedido = $pedido['order_number'] ?? $pedido['id'];
    $items    = get_order_items($pdo, $pedido);
    $shipping = json_decode($pedido['shipping_data'] ?? '{}', true) ?: [];
    $addr     = parse_shipping($shipping);

    $cliente_username = e($pedido['username'] ?: 'N/A');
    $cliente_email    = e($pedido['email']    ?: 'N/A');
    $cliente_ig       = e($pedido['instagram_username'] ?: '—');

    // Dirección de envío formateada para PDF
    $dir_lines = array_filter([
        strtoupper($addr['nombre'] ?? ''),
        $addr['linea1'] ?? '',
        $addr['linea2'] ?? '',
        trim(($addr['cp'] ?? '') . ' ' . ($addr['ciudad'] ?? '')),
        $addr['prov'] ?? '',
        strtoupper($addr['pais'] ?? 'ES'),
        !empty($addr['tel'])   ? 'Tel: ' . $addr['tel']   : '',
        !empty($addr['email']) ? $addr['email']            : '',
    ]);
    $dir_html = implode('<br>', array_map('e', $dir_lines));

    // ── Bloques de producto ──────────────────────────────────────────────────
    $productos_html = '';
    foreach ($items as $item) {
        $color_slug  = strtolower(trim($item['color'] ?? ''));
        $serie_slug  = $item['series_slug'];
        $es_blanco   = $color_slug === 'blanco';
        $src_front   = img_b64($serie_slug, $es_blanco ? 'main.png'     : 'detail-2.png');
        $src_back    = img_b64($serie_slug, $es_blanco ? 'detail-1.png' : 'detail-3.png');
        $img_front   = $src_front ? "<img src='{$src_front}' style='width:100%;height:auto;display:block;' />" : "<div style='height:180px;background:#f5f5f5;'></div>";
        $img_back    = $src_back  ? "<img src='{$src_back}'  style='width:100%;height:auto;display:block;' />" : "<div style='height:180px;background:#f5f5f5;'></div>";
        $serie_upper = strtoupper($item['series_name'] ?: $item['series_slug']);
        $unit_pad    = str_pad($item['unit_number'], 3, '0', STR_PAD_LEFT);

        $productos_html .= "
        <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;margin-bottom:14px;border:1px solid #000;'>
          <tr>
            <!-- Imágenes -->
            <td width='45%' style='border-right:1px solid #000;background:#fff;padding:0;'>
              <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;'>
                <tr>
                  <td width='50%' style='padding:14px;border-right:1px solid #eee;'>{$img_front}</td>
                  <td width='50%' style='padding:14px;'>{$img_back}</td>
                </tr>
              </table>
            </td>
            <!-- Datos producto -->
            <td valign='middle' style='padding:20px 28px;text-align:center;background:#fff;'>
              <div style='font-size:11px;font-weight:bold;letter-spacing:3px;color:#888;text-transform:uppercase;margin-bottom:4px;'>{$serie_upper}</div>
              <div style='font-size:52px;font-weight:900;font-family:Courier,monospace;letter-spacing:2px;line-height:1;margin:8px 0;color:#000;'>#{$unit_pad}</div>
              <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;border-top:1px solid #eee;margin-top:12px;padding-top:12px;'>
                <tr>
                  <td style='text-align:center;padding:8px 4px;border-right:1px solid #eee;'>
                    <div style='font-size:7px;text-transform:uppercase;letter-spacing:1px;color:#999;margin-bottom:4px;'>Talla</div>
                    <div style='font-size:14px;font-weight:900;'>" . strtoupper($item['size']) . "</div>
                  </td>
                  <td style='text-align:center;padding:8px 4px;border-right:1px solid #eee;'>
                    <div style='font-size:7px;text-transform:uppercase;letter-spacing:1px;color:#999;margin-bottom:4px;'>Color</div>
                    <div style='font-size:14px;font-weight:900;'>" . strtoupper($item['color']) . "</div>
                  </td>
                  <td style='text-align:center;padding:8px 4px;'>
                    <div style='font-size:7px;text-transform:uppercase;letter-spacing:1px;color:#999;margin-bottom:4px;'>Corte</div>
                    <div style='font-size:14px;font-weight:900;'>" . strtoupper($item['type'] === 'king-size' ? 'Oversize' : 'Standard') . "</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>";
    }

    // ── Sección de autenticidad (una línea por ítem) ─────────────────────────
    $auth_items = '';
    foreach ($items as $item) {
        $serie_label = strtoupper($item['series_name'] ?: $item['series_slug']);
        $unit_label  = '#' . str_pad($item['unit_number'], 3, '0', STR_PAD_LEFT);
        $auth_items .= "<div style='font-size:11px;font-weight:bold;letter-spacing:1px;color:#111;margin-bottom:2px;'>{$serie_label} &mdash; {$unit_label}</div>";
    }

    // ── Checklist ────────────────────────────────────────────────────────────
    // Casilla de verificación: borde negro 13×13px (Dompdf no soporta <input>)
    $cb = "<div style='width:13px;height:13px;border:1.5px solid #333;margin-top:1px;'></div>";
    $row_style  = 'border-bottom:1px solid #eee;';
    $num_style  = 'width:20px;padding:10px 6px 10px 0;font-size:10px;color:#111;vertical-align:top;';
    $box_style  = 'width:22px;padding:10px 10px 10px 0;vertical-align:top;';
    $text_style = 'padding:10px 0;font-size:10px;color:#111;vertical-align:top;';

    $checklist_rows = '';
    $step = 1;
    foreach ($items as $item) {
        $label = strtoupper($item['series_name'] ?: $item['series_slug'])
               . ' #' . str_pad($item['unit_number'], 3, '0', STR_PAD_LEFT)
               . ' &mdash; ' . strtoupper($item['size'])
               . ' / ' . strtoupper($item['color'])
               . ' / ' . strtoupper($item['type'] === 'king-size' ? 'Oversize' : 'Standard');
        $checklist_rows .= "<tr style='{$row_style}'>
          <td style='{$num_style}'>{$step}.</td>
          <td style='{$box_style}'>{$cb}</td>
          <td style='{$text_style}'>Verificar stock y preparar unidad: <strong>{$label}</strong></td>
        </tr>";
        $step++;
    }
    $checklist_rows .= "<tr style='{$row_style}'>
      <td style='{$num_style}'>{$step}.</td>
      <td style='{$box_style}'>{$cb}</td>
      <td style='{$text_style}'>Control de calidad final: revisar costuras, estampado y etiqueta numerada</td>
    </tr>"; $step++;
    $checklist_rows .= "<tr style='{$row_style}'>
      <td style='{$num_style}'>{$step}.</td>
      <td style='{$box_style}'>{$cb}</td>
      <td style='{$text_style}'>Firmar autenticidad y numerar la unidad</td>
    </tr>"; $step++;
    $checklist_rows .= "<tr style='{$row_style}'>
      <td style='{$num_style}'>{$step}.</td>
      <td style='{$box_style}'>{$cb}</td>
      <td style='{$text_style}'>Empaquetar y precintar</td>
    </tr>"; $step++;
    $checklist_rows .= "<tr>
      <td style='{$num_style}'>{$step}.</td>
      <td style='{$box_style}'>{$cb}</td>
      <td style='{$text_style}'>Imprimir etiqueta de envio y preparar bulto a nombre de <strong>" . e(strtoupper($addr['nombre'] ?? '')) . "</strong></td>
    </tr>";

    // ── HTML del documento ───────────────────────────────────────────────────
    $fecha = date('d/m/Y H:i');
    $html = "<!DOCTYPE html><html lang='es'>
<head>
<meta charset='UTF-8'>
<title>Orden #{$num_pedido} - DCIEN</title>
<style>
  html, body, table, td, th, div, p { margin:0; padding:0; }
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    line-height: 1.5;
    color: #111;
    background: #fff;
    padding: 14mm 17mm;
  }
  a, a:link, a:visited { color:#111 !important; text-decoration:none !important; }
  td, div, p, span { color:#111; }
  @page { size: A4 portrait; margin: 0; }
</style>
</head>
<body>

  <!-- ═══ CABECERA ══════════════════════════════════════════════════════════ -->
  <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;background:#000;margin-bottom:16px;'>
    <tr>
      <td style='padding:14px 20px;'>
        <div style='font-size:22px;font-weight:900;letter-spacing:6px;color:#fff;'>D C I E N</div>
        <div style='font-size:8px;letter-spacing:3px;color:#999;text-transform:uppercase;margin-top:2px;'>Orden de Producción</div>
      </td>
      <td style='padding:14px 20px;text-align:right;'>
        <div style='font-size:20px;font-weight:900;color:#fff;font-family:Courier,monospace;letter-spacing:2px;'>#{$num_pedido}</div>
        <div style='font-size:8px;color:#666;margin-top:2px;'>{$fecha}</div>
      </td>
    </tr>
  </table>

  <!-- ═══ PRODUCTO(S) ═══════════════════════════════════════════════════════ -->
  {$productos_html}

  <!-- ═══ CLIENTE + DIRECCIÓN ══════════════════════════════════════════════ -->
  <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;margin-bottom:14px;border:1px solid #000;'>
    <tr>
      <td width='50%' valign='top' style='padding:16px 18px;border-right:1px solid #000;'>
        <div style='font-size:8px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#999;border-bottom:1px solid #eee;padding-bottom:6px;margin-bottom:10px;'>Perfil Cliente</div>
        <table cellspacing='0' cellpadding='3' style='border-collapse:collapse;width:100%;'>
          <tr><td style='font-size:9px;color:#888;width:72px;'>Usuario</td><td style='font-size:9px;font-weight:bold;'>{$cliente_username}</td></tr>
          <tr><td style='font-size:9px;color:#888;'>Email</td><td style='font-size:9px;'>{$cliente_email}</td></tr>
          <tr><td style='font-size:9px;color:#888;'>Instagram</td><td style='font-size:9px;'>@{$cliente_ig}</td></tr>
        </table>
      </td>
      <td width='50%' valign='top' style='padding:16px 18px;'>
        <div style='font-size:8px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#999;border-bottom:1px solid #eee;padding-bottom:6px;margin-bottom:10px;'>Dirección de Envío</div>
        <div style='font-size:9px;line-height:1.7;'>{$dir_html}</div>
      </td>
    </tr>
  </table>

  <!-- ═══ CHECKLIST ════════════════════════════════════════════════════════ -->
  <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;border:1px solid #000;'>
    <tr>
      <td colspan='3' style='background:#000;padding:10px 18px;'>
        <div style='font-size:8px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#fff;'>Checklist de Produccion</div>
      </td>
    </tr>
    <tr>
      <td colspan='3' style='padding:4px 18px 10px;'>
        <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;'>
          {$checklist_rows}
        </table>
      </td>
    </tr>
  </table>

  <!-- ═══ AUTENTICIDAD ════════════════════════════════════════════════════ -->
  <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;margin-top:14px;'>
    <tr>
      <!-- Columna izquierda vacía -->
      <td width='50%'></td>
      <!-- Columna derecha: bloque de firma y sello -->
      <td width='50%' valign='top' style='border:1px solid #000;'>
        <div style='background:#000;padding:8px 14px;'>
          <div style='font-size:8px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#fff;'>Certificado de Autenticidad</div>
        </div>
        <div style='padding:12px 14px;'>
          {$auth_items}
          <div style='font-size:8px;color:#888;letter-spacing:1px;text-transform:uppercase;margin-top:6px;margin-bottom:28px;'>Responsable de produccion</div>
          <!-- Línea de firma -->
          <div style='border-top:1px solid #333;margin-bottom:4px;'></div>
          <div style='font-size:8px;color:#aaa;letter-spacing:1px;'>Firma</div>
          <!-- Espacio para sello físico -->
          <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;margin-top:14px;'>
            <tr>
              <td width='55%'>
                <div style='font-size:8px;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;'>Sello</div>
                <div style='border:1.5px dashed #ccc;height:52px;width:80px;'></div>
              </td>
              <td width='45%' valign='bottom'>
                <div style='font-size:7px;color:#bbb;text-align:right;'>DCIEN &mdash; {$fecha}</div>
              </td>
            </tr>
          </table>
        </div>
      </td>
    </tr>
  </table>

  <!-- ═══ PIE ══════════════════════════════════════════════════════════════ -->
  <table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;margin-top:12px;'>
    <tr>
      <td style='font-size:7px;color:#bbb;text-align:center;letter-spacing:1px;'>
        DCIEN &mdash; Documento de uso interno &mdash; Generado el {$fecha}
      </td>
    </tr>
  </table>

</body></html>";

    // Usar order_number como clave de fichero para evitar colisiones si se reutiliza el id
    $file_key = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($pedido['order_number'] ?? $pedido['id']));

    // Eliminar versiones anteriores (PDF y HTML legacy)
    foreach (array_merge(
        glob(__DIR__ . "/orden_{$file_key}_*.pdf")  ?: [],
        glob(__DIR__ . "/orden_{$file_key}_*.html") ?: [],
        glob(__DIR__ . "/orden_{$pedido['id']}_*.html") ?: []
    ) as $old) {
        @unlink($old);
    }

    $filename = "orden_{$file_key}_" . date('Ymd_His') . ".pdf";
    $filepath = __DIR__ . "/$filename";

    // Renderizar PDF con Dompdf
    $options = new \Dompdf\Options();
    $options->setIsRemoteEnabled(true);
    $options->setDefaultFont('Arial');
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    file_put_contents($filepath, $dompdf->output());

    return [
        'success'  => true,
        'filepath' => $filepath,
        'filename' => $filename,
        'url'      => "https://d-cien.es/admin-descargas/ordenes/$filename",
    ];
}

// ═══════════════════════════════════════════════
// EMAILS
// ═══════════════════════════════════════════════

function email_produccion(array $pedido, array $result): bool {
    $num_pedido = $pedido['order_number'] ?? $pedido['id'];
    $subject = "Nueva Orden de Producción #{$num_pedido} — DCIEN";
    $html = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
        <div style='background:#000;color:#fff;padding:30px;text-align:center;'>
            <h1 style='font-size:32px;letter-spacing:6px;margin:0;'>DCIEN</h1>
            <p style='margin:8px 0 0;font-size:12px;letter-spacing:3px;text-transform:uppercase;'>Nueva Orden de Producción</p>
        </div>
        <div style='padding:30px;'>
            <div style='background:#000;color:#fff;padding:20px;text-align:center;font-size:42px;font-weight:900;font-family:monospace;letter-spacing:4px;margin:0 0 20px;'>
                #{$num_pedido}
            </div>
            <p style='margin:0 0 20px;color:#333;'>Se ha generado la orden de producción. Accede al documento para imprimir.</p>
            <a href='{$result['url']}' style='display:block;background:#000;color:#fff;padding:14px;text-align:center;text-decoration:none;font-weight:bold;letter-spacing:2px;text-transform:uppercase;'>
                Ver Orden de Producción
            </a>
        </div>
    </div>";
    return sendAdminMail(ADMIN_EMAIL, $subject, $html);
}

function email_enviado_cliente(array $pedido, array $items, array $addr, string $empresa = '', string $tracking = ''): bool {
    $num_pedido = $pedido['order_number'] ?? $pedido['id'];
    $to = $addr['email'] ?: ($pedido['email'] ?? '');
    if (empty($to)) return false;

    $nombre = strtoupper($addr['nombre'] ?: ($pedido['username'] ?: 'Cliente'));

    $items_html = '';
    foreach ($items as $item) {
        $items_html .= "
        <div style='background:#f9f9f9;border-left:3px solid #000;padding:12px;margin:8px 0;'>
            <strong>" . strtoupper($item['series_name'] ?: $item['series_slug']) . " #" . str_pad($item['unit_number'], 3, '0', STR_PAD_LEFT) . "</strong><br>
            <span style='font-size:13px;color:#555;'>" . strtoupper($item['size']) . " · " . ucfirst($item['color']) . " · " . ucfirst($item['type'] === 'king-size' ? 'Oversize' : 'Standard') . "</span>
        </div>";
    }

    $tracking_html = '';
    if (!empty($empresa) || !empty($tracking)) {
        $tracking_html = "
        <div style='background:#f0f0f0;border:1px solid #ddd;border-radius:4px;padding:16px;margin:20px 0;text-align:left;'>
            <p style='margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#888;'>Información de envío</p>" .
            (!empty($empresa) ? "<p style='margin:0 0 6px;font-size:14px;'>Transportista: <strong>" . htmlspecialchars($empresa) . "</strong></p>" : '') .
            (!empty($tracking) ? "<p style='margin:0;font-size:14px;'>Nº seguimiento: <strong style='font-family:monospace;'>" . htmlspecialchars($tracking) . "</strong></p>" : '') . "
        </div>";
    }

    $subject = "Tu pedido DCIEN #{$num_pedido} va en camino";
    $html = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;'>
        <div style='background:#000;color:#fff;padding:30px;text-align:center;'>
            <h1 style='font-size:28px;letter-spacing:6px;margin:0;font-weight:900;'>DCIEN</h1>
        </div>
        <div style='padding:30px;text-align:center;color:#111;'>
            <div style='font-size:40px;margin-bottom:16px;'>📦</div>
            <h2 style='margin:0 0 12px;'>Hola, $nombre</h2>
            <p style='color:#555;margin:0 0 24px;'>Tu pedido <strong>#{$num_pedido}</strong> ya ha salido de nuestras instalaciones y está en manos del transportista.</p>
            $items_html
            $tracking_html
            <p style='color:#777;font-size:13px;margin:24px 0;'>Si tienes alguna duda responde a este correo.</p>
            <a href='https://d-cien.es' style='display:inline-block;background:#000;color:#fff;padding:14px 32px;text-decoration:none;font-weight:bold;letter-spacing:2px;text-transform:uppercase;'>
                Visitar Tienda
            </a>
        </div>
        <div style='background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#888;border-top:1px solid #eee;'>
            Gracias por confiar en DCIEN.
        </div>
    </div>";

    return sendAdminMail($to, $subject, $html);
}

// ═══════════════════════════════════════════════
// ACCIONES POST
// ═══════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generar_orden' && isset($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        $stmt = $pdo->prepare("
            SELECT o.*, u.username, u.email, u.instagram_username,
                   s.name as series_name
            FROM orders o
            LEFT JOIN users u  ON u.id = o.user_id
            LEFT JOIN series s ON s.slug = o.series_slug
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            $result = generar_orden_trabajo($pedido, $pdo);
            if ($result['success']) {
                $estados_avanzables = ['paid', 'pending', 'pendiente'];
                if (in_array($pedido['status'], $estados_avanzables)) {
                    $pdo->prepare("UPDATE orders SET status = 'produccion' WHERE id = ?")->execute([$order_id]);
                }
                $link = "<a href='{$result['url']}' target='_blank' style='color:#16a34a;text-decoration:underline;'>Abrir documento</a>";
                $message = show_message('success', "✓ Documento generado para orden #{$order_id}. $link");
            } else {
                $message = show_message('error', "Error al generar orden #{$order_id}.");
            }
        }
    }

    if ($action === 'generar_multiples' && isset($_POST['order_ids'])) {
        $order_ids = array_filter(array_map('intval', explode(',', $_POST['order_ids'])));
        $ok = 0; $ko = 0;
        foreach ($order_ids as $order_id) {
            $stmt = $pdo->prepare("
                SELECT o.*, u.username, u.email, u.instagram_username, s.name as series_name
                FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN series s ON s.slug = o.series_slug
                WHERE o.id = ?
            ");
            $stmt->execute([$order_id]);
            $pedido = $stmt->fetch();
            if ($pedido) {
                $result = generar_orden_trabajo($pedido, $pdo);
                if ($result['success']) {
                    $estados_avanzables = ['paid', 'pending', 'pendiente'];
                    if (in_array($pedido['status'], $estados_avanzables)) {
                        $pdo->prepare("UPDATE orders SET status = 'produccion' WHERE id = ?")->execute([$order_id]);
                    }
                    $ok++;
                } else { $ko++; }
            }
        }
        $message = show_message('success', "✓ $ok orden(es) generadas y movidas a Producción." . ($ko ? " $ko errores." : ''));
    }

    if ($action === 'marcar_enviado' && isset($_POST['order_id'])) {
        $order_id         = (int)$_POST['order_id'];
        $shipping_company = trim($_POST['empresa_paqueteria'] ?? '');
        $tracking_id      = trim($_POST['tracking_id'] ?? '');

        $pdo->prepare("UPDATE orders SET status = 'enviado', shipping_company = ?, tracking_id = ? WHERE id = ?")
            ->execute([$shipping_company, $tracking_id, $order_id]);

        $stmt = $pdo->prepare("
            SELECT o.*, u.username, u.email, u.instagram_username, s.name as series_name
            FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN series s ON s.slug = o.series_slug
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            $items   = get_order_items($pdo, $pedido);
            $ship    = json_decode($pedido['shipping_data'] ?? '{}', true) ?: [];
            $addr    = parse_shipping($ship);
            $sent    = email_enviado_cliente($pedido, $items, $addr, $shipping_company, $tracking_id);
            $message = show_message('success', "✓ Pedido #{$order_id} marcado como enviado." . ($sent ? ' Email enviado al cliente.' : ' (Email no enviado — revisa la dirección.)'));
        }
    }

    // Eliminación de pedidos deshabilitada en la interfaz.
    // Usar sentencias SQL directas para eliminar pedidos de prueba.

    if ($action === 'subir_documento' && isset($_POST['order_id']) && isset($_FILES['documento'])) {
        $order_id = (int)$_POST['order_id'];
        $file     = $_FILES['documento'];
        $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        $max_size     = 10 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = show_message('error', 'Error al recibir el archivo (código ' . $file['error'] . ').');
        } elseif (!in_array($file['type'], $allowed_mime, true)) {
            $message = show_message('error', 'Tipo de archivo no permitido. Solo PDF, JPG, PNG o WEBP.');
        } elseif ($file['size'] > $max_size) {
            $message = show_message('error', 'El archivo supera el límite de 10 MB.');
        } else {
            $doc_dir = __DIR__ . '/docs/' . $order_id;
            if (!is_dir($doc_dir)) mkdir($doc_dir, 0755, true);

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $basename = preg_replace('/[^a-z0-9_-]/i', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $filename = $basename . '_' . date('Ymd_His') . '.' . $ext;

            if (move_uploaded_file($file['tmp_name'], $doc_dir . '/' . $filename)) {
                $pdo->prepare("INSERT INTO order_documents (order_id, filename, original_name, mime_type, size_bytes) VALUES (?,?,?,?,?)")
                    ->execute([$order_id, $filename, $file['name'], $file['type'], $file['size']]);
                $message = show_message('success', "✓ Documento adjuntado al pedido #{$order_id}.");
            } else {
                $message = show_message('error', 'No se pudo guardar el archivo en el servidor.');
            }
        }
    }

    if ($action === 'eliminar_documento' && isset($_POST['order_id']) && isset($_POST['doc_id'])) {
        $order_id = (int)$_POST['order_id'];
        $doc_id   = (int)$_POST['doc_id'];
        $stmt     = $pdo->prepare("SELECT filename FROM order_documents WHERE id = ? AND order_id = ?");
        $stmt->execute([$doc_id, $order_id]);
        $doc = $stmt->fetch();
        if ($doc) {
            $filepath = __DIR__ . '/docs/' . $order_id . '/' . $doc['filename'];
            if (file_exists($filepath)) unlink($filepath);
            $pdo->prepare("DELETE FROM order_documents WHERE id = ?")->execute([$doc_id]);
            $message = show_message('success', "✓ Documento eliminado.");
        }
    }

    if ($action === 'exportar_csv') {
        $estado_csv  = $_POST['estado'] ?? 'nuevos';
        $where_parts = [];
        $params      = [];

        if ($estado_csv === 'nuevos') {
            $where_parts[] = "o.status IN ('paid','pending','pendiente')";
        } else {
            $where_parts[] = "o.status = :estado";
            $params['estado'] = $estado_csv;
        }
        if (!empty($_POST['serie']))  { $where_parts[] = "o.series_slug = :serie";  $params['serie']  = $_POST['serie']; }
        if (!empty($_POST['desde']))  { $where_parts[] = "o.created_at >= :desde";  $params['desde']  = $_POST['desde'] . ' 00:00:00'; }
        if (!empty($_POST['hasta']))  { $where_parts[] = "o.created_at <= :hasta";  $params['hasta']  = $_POST['hasta'] . ' 23:59:59'; }

        $stmt = $pdo->prepare("
            SELECT o.*, u.username, u.email, s.name as series_name
            FROM orders o
            LEFT JOIN users u  ON u.id = o.user_id
            LEFT JOIN series s ON s.slug = o.series_slug
            WHERE " . implode(' AND ', $where_parts) . "
            ORDER BY o.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pedidos_' . $estado_csv . '_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'Fecha', 'Serie', 'Unidad', 'Talla', 'Color', 'Tipo', 'Cliente', 'Email', 'Instagram', 'Precio', 'Estado', 'Carrito']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['created_at'], $r['series_name'], $r['unit_number'],
                $r['size'], $r['color'], ($r['type'] === 'king-size' ? 'Oversize' : 'Standard'),
                $r['username'] ?: 'N/A', $r['email'],
                $r['instagram_username'] ?: 'N/A',
                $r['price'], strtoupper($r['status']),
                $r['is_cart_order'] ? 'Sí' : 'No',
            ]);
        }
        fclose($out);
        exit;
    }
}

// ═══════════════════════════════════════════════
// QUERIES — listado y contadores
// ═══════════════════════════════════════════════

$where_parts = [];
$params      = [];

if ($estado_actual === 'nuevos') {
    $where_parts[] = "o.status IN ('paid','pending','pendiente')";
} else {
    $where_parts[] = "o.status = :estado";
    $params['estado'] = $estado_actual;
}

if (!empty($_GET['serie'])) {
    $where_parts[] = "o.series_slug = :serie";
    $params['serie'] = $_GET['serie'];
}
if (!empty($_GET['desde'])) {
    $where_parts[] = "o.created_at >= :desde";
    $params['desde'] = $_GET['desde'] . ' 00:00:00';
}
if (!empty($_GET['hasta'])) {
    $where_parts[] = "o.created_at <= :hasta";
    $params['hasta'] = $_GET['hasta'] . ' 23:59:59';
}
if (!empty($_GET['search'])) {
    $where_parts[] = "(u.username LIKE :s1 OR u.email LIKE :s2 OR o.id = :sid)";
    $params['s1']  = '%' . $_GET['search'] . '%';
    $params['s2']  = '%' . $_GET['search'] . '%';
    $params['sid'] = is_numeric($_GET['search']) ? (int)$_GET['search'] : 0;
}

$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email, u.instagram_username, s.name as series_name
    FROM orders o
    LEFT JOIN users u  ON u.id = o.user_id
    LEFT JOIN series s ON s.slug = o.series_slug
    WHERE " . implode(' AND ', $where_parts) . "
    ORDER BY o.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$series     = $pdo->query("SELECT slug, name FROM series ORDER BY name")->fetchAll();
$conteo_raw = $pdo->query("SELECT status, COUNT(*) as qty FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$cant_nuevos    = ($conteo_raw['paid'] ?? 0) + ($conteo_raw['pending'] ?? 0) + ($conteo_raw['pendiente'] ?? 0);
$cant_prod      = $conteo_raw['produccion'] ?? 0;
$cant_enviados  = $conteo_raw['enviado'] ?? 0;
$cant_cancelado = $conteo_raw['cancelled'] ?? 0;

$total_mostrados = count($pedidos);
$suma_total      = array_sum(array_column($pedidos, 'price'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Pipeline de Órdenes — DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
    <style>
        .pipeline-tabs { display:flex; gap:8px; margin-bottom:24px; border-bottom:1px solid var(--border); padding-bottom:0; overflow-x:auto; }
        .tab-btn { padding:12px 20px; font-size:11px; font-weight:900; letter-spacing:2px; text-transform:uppercase; text-decoration:none; color:var(--text-2); border-bottom:3px solid transparent; white-space:nowrap; display:flex; align-items:center; gap:8px; transition:all .2s; font-family:'Courier New',monospace; }
        .tab-btn:hover { color:var(--text); }
        .tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
        .tab-count { background:var(--border); color:var(--text-2); padding:2px 8px; font-size:10px; border-radius:2px; }
        .tab-btn.active .tab-count { background:var(--accent); color:var(--accent-text); }

        .summary-bar { display:flex; gap:20px; padding:16px 20px; background:var(--surface); border:1px solid var(--border); margin-bottom:20px; font-size:12px; flex-wrap:wrap; }
        .summary-bar span { color:var(--text-2); }
        .summary-bar strong { color:var(--accent); }

        .filters-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:16px; }
        .filter-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:space-between; align-items:center; padding-top:16px; border-top:1px solid var(--border); }

        table { min-width:920px; }
        .col-check   { width:36px; text-align:center; }
        .col-id      { width:110px; }
        .col-producto { min-width:190px; }
        .col-cliente { min-width:150px; }
        .col-precio  { width:76px; text-align:right; }
        .col-audit   { width:82px; }
        .col-docs    { min-width:170px; }
        .col-actions { width:110px; }

        /* Status pills */
        .pill { display:inline-block; padding:3px 8px; font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:1px; border-radius:2px; }
        .pill-ok   { background:var(--sent-bg); color:var(--sent); border:1px solid var(--sent-border); }
        .pill-warn { background:var(--new-bg);  color:var(--new);  border:1px solid var(--new-border); }
        .pill-cart { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }

        /* Producto */
        .item-row { font-size:10px; color:var(--text-2); line-height:1.7; }
        .item-row strong { color:var(--text); font-size:11px; }

        /* Botones de acción */
        .btn-group { display:flex; gap:5px; align-items:center; flex-wrap:nowrap; }
        .checkbox-col { text-align:center; }

        /* Docs en tabla */
        .doc-list { display:flex; flex-direction:column; gap:4px; }
        .doc-chip { display:inline-flex; align-items:center; gap:4px; padding:3px 7px; border-radius:3px; font-size:10px; font-weight:600; text-decoration:none; white-space:nowrap; max-width:160px; overflow:hidden; text-overflow:ellipsis; line-height:1.4; }
        .doc-chip-orden { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        .doc-chip-orden:hover { background:#dbeafe; }
        .doc-chip-adjunto { background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; }
        .doc-chip-adjunto:hover { background:#ede9fe; }
        .doc-add-btn { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:3px; font-size:10px; font-weight:700; background:transparent; border:1px dashed var(--border); color:var(--text-2); cursor:pointer; margin-top:2px; }
        .doc-add-btn:hover { border-color:var(--accent); color:var(--accent); background:transparent; }

        /* Tracking badge en tabla */
        .tracking-badge { display:inline-flex; align-items:center; gap:4px; font-size:10px; color:var(--text-2); margin-top:2px; }
        .tracking-badge strong { color:var(--text); font-family:monospace; font-size:10px; }

        /* Acciones — botones iguales */
        .col-actions .btn-group { flex-wrap:wrap; }
        .btn-action { display:inline-flex; align-items:center; justify-content:center; padding:5px 10px; font-size:11px; font-weight:700; border-radius:3px; border:1px solid var(--border); background:transparent; color:var(--text); cursor:pointer; text-decoration:none; white-space:nowrap; letter-spacing:.5px; transition:border-color .15s,color .15s; }
        .btn-action:hover { border-color:var(--text); }
        .btn-action-primary { border-color:var(--accent); color:var(--accent); }
        .btn-action-primary:hover { background:var(--accent); color:var(--accent-text); }
        .btn-action-sent { border-color:var(--sent); color:var(--sent); }
        .btn-action-sent:hover { background:var(--sent); color:#fff; }
        .btn-action-danger { border-color:#dc2626; color:#dc2626; }
        .btn-action-danger:hover { background:#dc2626; color:#fff; }

        @media (max-width:768px) {
            .filters-row { grid-template-columns:1fr; }
            .filter-actions { flex-direction:column; }
            .filter-actions .btn { width:100%; text-align:center; }
            .pipeline-tabs { gap:4px; }
            .tab-btn { padding:10px 12px; font-size:10px; }
        }

        /* Modal de documentos */
        #modal-doc { display:none; position:fixed; inset:0; z-index:1001; align-items:center; justify-content:center; background:rgba(0,0,0,.6); backdrop-filter:blur(2px); }
        #modal-doc .modal-card { max-width:440px; }
        .file-drop { border:2px dashed var(--border); border-radius:4px; padding:24px; text-align:center; cursor:pointer; color:var(--text-2); font-size:13px; transition:border-color .2s; }
        .file-drop:hover, .file-drop.over { border-color:var(--accent); color:var(--text); }
        .file-drop input[type=file] { display:none; }
        .file-name { font-size:12px; color:var(--text-2); margin-top:8px; min-height:18px; }

        /* Modal de envío */
        #modal-envio {
            display:none; position:fixed; inset:0; z-index:1000;
            align-items:center; justify-content:center;
            background:rgba(0,0,0,.6); backdrop-filter:blur(2px);
        }
        .modal-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:4px; padding:32px; width:100%; max-width:420px;
            box-shadow:0 8px 32px rgba(0,0,0,.4);
        }
        .modal-card h3 {
            margin:0 0 6px; font-size:14px; font-weight:900;
            letter-spacing:2px; text-transform:uppercase;
        }
        .modal-card .modal-sub {
            font-size:12px; color:var(--text-2); margin:0 0 24px;
        }
        .modal-card .form-group { margin:0 0 16px; }
        .modal-card label { display:block; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; margin-bottom:6px; color:var(--text-2); }
        .modal-card input[type=text] { width:100%; }
        .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:24px; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
    <div class="main-content">
        <div class="container">

    <header class="header">
        <div>
            <h1>PIPELINE DE ÓRDENES</h1>
            <p>Control de fases de producción</p>
        </div>
        <div class="header-actions">
            <a href="/admin-descargas/">← Dashboard</a>
        </div>
    </header>

    <?php if ($message) echo $message; ?>

    <!-- TABS -->
    <nav class="pipeline-tabs">
        <a href="?estado=nuevos" class="tab-btn <?= $estado_actual === 'nuevos' ? 'active' : '' ?>">
            Nuevos <span class="tab-count"><?= $cant_nuevos ?></span>
        </a>
        <a href="?estado=produccion" class="tab-btn <?= $estado_actual === 'produccion' ? 'active' : '' ?>">
            Producción <span class="tab-count"><?= $cant_prod ?></span>
        </a>
        <a href="?estado=enviado" class="tab-btn <?= $estado_actual === 'enviado' ? 'active' : '' ?>">
            Enviados <span class="tab-count"><?= $cant_enviados ?></span>
        </a>
        <?php if ($cant_cancelado > 0): ?>
        <a href="?estado=cancelled" class="tab-btn <?= $estado_actual === 'cancelled' ? 'active' : '' ?>">
            Cancelados <span class="tab-count"><?= $cant_cancelado ?></span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- FILTROS -->
    <div class="filters">
        <form method="GET">
            <input type="hidden" name="estado" value="<?= e($estado_actual) ?>">
            <div class="filters-row">
                <div class="form-group" style="margin:0;">
                    <label>Serie</label>
                    <select name="serie">
                        <option value="">Todas</option>
                        <?php foreach ($series as $s): ?>
                            <option value="<?= e($s['slug']) ?>" <?= ($_GET['serie'] ?? '') === $s['slug'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?= e($_GET['desde'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Buscar</label>
                    <input type="text" name="search" placeholder="ID, email, usuario..." value="<?= e($_GET['search'] ?? '') ?>">
                </div>
            </div>
            <div class="filter-actions">
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" class="btn">Filtrar</button>
                    <a href="?estado=<?= e($estado_actual) ?>" class="btn btn-secondary">Limpiar</a>
                    <button type="button" onclick="exportarCSV()" class="btn btn-secondary">CSV</button>
                </div>
                <?php if ($estado_actual === 'nuevos'): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" onclick="seleccionarTodos()" class="btn btn-secondary">Seleccionar todos</button>
                    <button type="button" onclick="generarMultiples()" class="btn">A Producción</button>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- RESUMEN -->
    <div class="summary-bar">
        <span>Mostrando <strong><?= $total_mostrados ?></strong> pedidos</span>
        <span>Total fase: <strong><?= format_price($suma_total) ?></strong></span>
        <?php if (!empty($_GET['serie']) || !empty($_GET['search']) || !empty($_GET['desde'])): ?>
            <span style="color:var(--prod);">— filtros activos</span>
        <?php endif; ?>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <table id="ordersTable">
            <thead>
                <tr>
                    <th class="col-check"><input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="transform:scale(1.2);"></th>
                    <th class="col-id">ID / Fecha</th>
                    <th class="col-producto">Producto</th>
                    <th class="col-cliente">Cliente</th>
                    <th class="col-precio">Precio</th>
                    <th class="col-audit">Auditoría</th>
                    <th class="col-docs">Docs</th>
                    <th class="col-actions">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pedidos)): ?>
                <tr><td colspan="8" style="text-align:center;padding:48px;color:var(--text-2);">No hay pedidos en esta fase.</td></tr>
            <?php else: foreach ($pedidos as $p):
                $shipping_raw = json_decode($p['shipping_data'] ?? '{}', true) ?: [];
                $addr         = parse_shipping($shipping_raw);

                // Auditoría
                $warnings = [];
                $tiene_dir = !empty($addr['linea1']) || !empty($addr['nombre']);
                if (!$tiene_dir) $warnings[] = 'Sin dirección';
                if (isset($shipping_raw['payment']['status']) && $shipping_raw['payment']['status'] !== 'paid') {
                    $warnings[] = 'Pago no confirmado';
                }

                // Items del carrito si aplica
                $cart_items = [];
                if ($p['is_cart_order']) {
                    $st2 = $pdo->prepare("SELECT oi.*, s.name as series_name FROM order_items oi LEFT JOIN series s ON s.slug = oi.series_slug WHERE oi.order_id = ? ORDER BY oi.id ASC");
                    $st2->execute([$p['id']]);
                    $cart_items = $st2->fetchAll();
                }

                $file_key_p = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($p['order_number'] ?? $p['id']));
                $archivos   = array_unique(array_merge(
                    glob(__DIR__ . "/orden_{$file_key_p}_*.pdf")  ?: [],
                    glob(__DIR__ . "/orden_{$file_key_p}_*.html") ?: [],
                    glob(__DIR__ . "/orden_{$p['id']}_*.html")    ?: []
                ));
                $stmt_docs  = $pdo->prepare("SELECT id, filename, original_name FROM order_documents WHERE order_id = ? ORDER BY uploaded_at ASC");
                $stmt_docs->execute([$p['id']]);
                $docs_subidos = $stmt_docs->fetchAll();
            ?>
                <tr>
                    <td class="col-check checkbox-col">
                        <input type="checkbox" class="order-checkbox" value="<?= $p['id'] ?>" style="transform:scale(1.2);">
                    </td>
                    <td class="col-id">
                        <strong style="color:var(--accent);">#<?= $p['order_number'] ?? $p['id'] ?></strong><br>
                        <span style="font-size:10px;color:var(--text-2);"><?= format_date($p['created_at'], 'd/m H:i') ?></span>
                    </td>
                    <td class="col-producto">
                        <?php if ($p['is_cart_order'] && !empty($cart_items)): ?>
                            <span class="pill pill-cart">Carrito (<?= count($cart_items) ?>)</span><br>
                            <div class="item-row">
                            <?php foreach ($cart_items as $ci): ?>
                                <strong><?= e($ci['series_name'] ?: $ci['series_slug']) ?> #<?= str_pad($ci['unit_number'], 3, '0', STR_PAD_LEFT) ?></strong>
                                — <?= strtoupper($ci['size']) ?>/<?= ucfirst($ci['color']) ?><br>
                            <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="item-row">
                                <strong><?= e($p['series_name'] ?: $p['series_slug']) ?></strong><br>
                                #<?= str_pad($p['unit_number'], 3, '0', STR_PAD_LEFT) ?>
                                · <?= strtoupper($p['size']) ?> / <?= ucfirst($p['color']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="col-cliente">
                        <span style="font-size:12px;"><?= e($p['username'] ?: '') ?></span><br>
                        <span style="font-size:10px;color:var(--text-2);"><?= e($p['email']) ?></span>
                    </td>
                    <td class="col-precio">
                        <strong style="color:var(--accent);"><?= format_price($p['price']) ?></strong>
                    </td>
                    <td class="col-audit">
                        <?php if (empty($warnings)): ?>
                            <span class="pill pill-ok">✓ OK</span>
                        <?php else: foreach ($warnings as $w): ?>
                            <span class="pill pill-warn"><?= e($w) ?></span><br>
                        <?php endforeach; endif; ?>
                    </td>
                    <td class="col-docs">
                        <div class="doc-list">
                            <?php foreach ($archivos as $f): ?>
                                <?php $is_pdf = str_ends_with($f, '.pdf'); ?>
                                <a href="<?= basename($f) ?>" target="_blank" class="doc-chip doc-chip-orden"
                                   title="<?= $is_pdf ? 'Orden de producción PDF' : 'Orden de trabajo HTML (legacy)' ?>">
                                    <?= $is_pdf ? '📋 Orden PDF' : '📄 Orden HTML' ?>
                                </a>
                            <?php endforeach; ?>
                            <?php foreach ($docs_subidos as $doc): ?>
                                <a href="docs/<?= $p['id'] ?>/<?= e($doc['filename']) ?>" target="_blank"
                                   class="doc-chip doc-chip-adjunto"
                                   title="<?= e($doc['original_name']) ?>">
                                    📎 <?= e(mb_strimwidth($doc['original_name'], 0, 18, '…')) ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($archivos) && empty($docs_subidos)): ?>
                                <span style="font-size:10px;color:var(--text-2);">Sin documentos</span>
                            <?php endif; ?>
                            <?php if ($estado_actual === 'enviado'): ?>
                                <button type="button" class="doc-add-btn"
                                    onclick="abrirModalDoc(<?= $p['id'] ?>, '<?= e($p['order_number'] ?? $p['id']) ?>')">
                                    + Adjuntar
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($estado_actual === 'enviado' && !empty($p['shipping_company'])): ?>
                            <div class="tracking-badge" title="Nº seguimiento: <?= e($p['tracking_id'] ?? '') ?>">
                                🚚 <strong><?= e($p['shipping_company']) ?></strong>
                                <?php if (!empty($p['tracking_id'])): ?>
                                    · <strong><?= e($p['tracking_id']) ?></strong>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="col-actions">
                        <div class="btn-group">
                            <?php if ($estado_actual === 'nuevos'): ?>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="action" value="generar_orden">
                                    <input type="hidden" name="order_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-action btn-action-primary" title="Generar documento y mover a Producción">Imprimir</button>
                                </form>
                            <?php elseif ($estado_actual === 'produccion'): ?>
                                <button type="button" class="btn-action btn-action-sent"
                                    title="Marcar como enviado y notificar al cliente"
                                    onclick="abrirModalEnvio(<?= $p['id'] ?>, '<?= e($p['order_number'] ?? $p['id']) ?>')">
                                    ✓ Enviado
                                </button>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="action" value="generar_orden">
                                    <input type="hidden" name="order_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-action" title="Re-generar documento">Doc</button>
                                </form>
                            <?php endif; ?>
                            <a href="/admin-descargas/modules/ver-pedido.php?id=<?= $p['id'] ?>" class="btn-action" title="Ver detalle completo">Ver</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <footer class="footer">
        <p>DCIEN Pipeline · Fase: <?= strtoupper($estado_actual) ?> · <?= $total_mostrados ?> pedidos</p>
    </footer>
        </div>
    </div>
</div>

<!-- Modal: adjuntar documento -->
<div id="modal-doc">
    <div class="modal-card">
        <h3>Adjuntar documento</h3>
        <p class="modal-sub" id="modal-doc-titulo"></p>
        <form method="POST" enctype="multipart/form-data" id="form-modal-doc">
            <input type="hidden" name="action" value="subir_documento">
            <input type="hidden" name="order_id" id="modal-doc-order-id">
            <div class="form-group">
                <label>Archivo</label>
                <div class="file-drop" id="file-drop-zone" onclick="document.getElementById('modal-doc-file').click()">
                    <input type="file" id="modal-doc-file" name="documento" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                    <span id="file-drop-text">Haz clic o arrastra un archivo aquí</span>
                    <div class="file-name" id="file-name-preview"></div>
                </div>
                <div style="font-size:10px;color:var(--text-2);margin-top:6px;">PDF, JPG, PNG o WEBP · máx. 10 MB</div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalDoc()">Cancelar</button>
                <button type="submit" class="btn">Subir documento</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: datos de envío -->
<div id="modal-envio">
    <div class="modal-card">
        <h3>Marcar como enviado</h3>
        <p class="modal-sub" id="modal-envio-titulo"></p>
        <form method="POST" id="form-modal-envio">
            <input type="hidden" name="action" value="marcar_enviado">
            <input type="hidden" name="order_id" id="modal-envio-order-id">
            <div class="form-group">
                <label for="modal-empresa">Empresa de paquetería</label>
                <input type="text" id="modal-empresa" name="empresa_paqueteria" placeholder="Correos, MRW, SEUR, GLS..." required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="modal-tracking">Nº de seguimiento</label>
                <input type="text" id="modal-tracking" name="tracking_id" placeholder="Ej: ES123456789ES" autocomplete="off">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalEnvio()">Cancelar</button>
                <button type="submit" class="btn" style="border-color:var(--sent);color:var(--sent);">Confirmar envío</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAll(cb) {
    document.querySelectorAll('.order-checkbox').forEach(c => c.checked = cb.checked);
}
function seleccionarTodos() {
    document.querySelectorAll('.order-checkbox').forEach(c => c.checked = true);
    document.getElementById('selectAll').checked = true;
}
function generarMultiples() {
    const cbs = document.querySelectorAll('.order-checkbox:checked');
    if (!cbs.length) { alert('Selecciona al menos un pedido.'); return; }
    const ids = Array.from(cbs).map(c => c.value);
    if (!confirm(`¿Generar ${ids.length} orden(es) y mover a Producción?`)) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="action" value="generar_multiples"><input type="hidden" name="order_ids" value="${ids.join(',')}">`;
    document.body.appendChild(form);
    form.submit();
}
function abrirModalDoc(orderId, orderNum) {
    document.getElementById('modal-doc-order-id').value = orderId;
    document.getElementById('modal-doc-titulo').textContent = 'Pedido #' + orderNum;
    document.getElementById('modal-doc-file').value = '';
    document.getElementById('file-name-preview').textContent = '';
    document.getElementById('modal-doc').style.display = 'flex';
}
function cerrarModalDoc() {
    document.getElementById('modal-doc').style.display = 'none';
}
document.getElementById('modal-doc').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalDoc();
});
document.getElementById('modal-doc-file').addEventListener('change', function() {
    document.getElementById('file-name-preview').textContent = this.files[0] ? this.files[0].name : '';
});
(function() {
    const zone = document.getElementById('file-drop-zone');
    const input = document.getElementById('modal-doc-file');
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('over'); });
    zone.addEventListener('dragleave', function() { zone.classList.remove('over'); });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        zone.classList.remove('over');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            document.getElementById('file-name-preview').textContent = e.dataTransfer.files[0].name;
        }
    });
})();
function abrirModalEnvio(orderId, orderNum) {
    document.getElementById('modal-envio-order-id').value = orderId;
    document.getElementById('modal-envio-titulo').textContent = 'Pedido #' + orderNum;
    document.getElementById('modal-envio').style.display = 'flex';
    document.getElementById('modal-empresa').focus();
}
function cerrarModalEnvio() {
    document.getElementById('modal-envio').style.display = 'none';
    document.getElementById('modal-empresa').value = '';
    document.getElementById('modal-tracking').value = '';
}
document.getElementById('modal-envio').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalEnvio();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModalEnvio();
});
function exportarCSV() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="exportar_csv">
        <input type="hidden" name="estado" value="<?= e($estado_actual) ?>">
        <input type="hidden" name="serie" value="<?= e($_GET['serie'] ?? '') ?>">
        <input type="hidden" name="desde" value="<?= e($_GET['desde'] ?? '') ?>">
        <input type="hidden" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>
