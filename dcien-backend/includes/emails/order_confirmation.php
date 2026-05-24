<?php
/**
 * Email Template: Confirmación de Pedido
 * Datos esperados en $data:
 *   username, order_id, email_items (array con keys: series_slug, unit_number, size, color, type),
 *   shipping_address (string HTML), total,
 *   discount_code (opcional), discount_amount (opcional), original_price (opcional)
 */

// Bloque de descuento
$discount_html = '';
if (!empty($data['discount_code']) && !empty($data['discount_amount'])) {
    $discount_html = '
    <tr>
        <td style="padding: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);">
            <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color: rgba(255,255,255,0.6); font-size: 13px;">Precio original:</td>
                    <td align="right" style="color: rgba(255,255,255,0.6); font-size: 13px; text-decoration: line-through;">'
                        . htmlspecialchars($data['original_price'] ?? '—') .
                    '</td>
                </tr>
                <tr>
                    <td style="color: #4ade80; font-size: 14px; font-weight: 600; padding-top: 8px;">
                        Descuento (' . htmlspecialchars($data['discount_code']) . '):
                    </td>
                    <td align="right" style="color: #4ade80; font-size: 14px; font-weight: 700; padding-top: 8px;">
                        -€' . htmlspecialchars($data['discount_amount']) . '
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding: 12px 0;">
            <div style="height: 1px; background-color: rgba(255,255,255,0.2);"></div>
        </td>
    </tr>';
}

// Bloque de items (soporta 1 o N prendas)
$items_rows = '';
$email_items = $data['email_items'] ?? [];
foreach ($email_items as $item) {
    $seriesLabel = strtoupper(str_replace('-', ' ', $item['series_slug'] ?? ''));
    $unitPadded  = str_pad($item['unit_number'] ?? 0, 3, '0', STR_PAD_LEFT);
    $size        = strtoupper($item['size'] ?? '');
    $color       = ucfirst($item['color'] ?? '');
    $type        = ucfirst($item['type'] ?? 'standard');

    $items_rows .= '
        <tr>
            <td colspan="2" style="padding: 10px 0; border-bottom: 1px solid #e8e8e8;">
                <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #000000; font-size: 15px; font-weight: 700;">
                            ' . htmlspecialchars($seriesLabel) . ' <span style="font-family: monospace;">#' . $unitPadded . '</span>
                        </td>
                        <td align="right" style="color: #555555; font-size: 13px; white-space: nowrap;">
                            ' . htmlspecialchars($size) . ' · ' . htmlspecialchars($color) . ' · ' . htmlspecialchars($type) . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>';
}

return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pedido - DCIEN</title>
</head>
<body style="margin: 0; padding: 0; background-color: #000000; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Arial, sans-serif;">

    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #000000;" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 60px 20px;">

                <table role="presentation" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff;" cellpadding="0" cellspacing="0">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 50px 40px 30px 40px;">
                            <h1 style="margin: 0; color: #000000; font-size: 48px; font-weight: 900; letter-spacing: 16px; text-transform: uppercase; line-height: 1;">
                                DCIEN
                            </h1>
                            <div style="width: 80px; height: 2px; background-color: #000000; margin: 20px auto 0 auto;"></div>
                        </td>
                    </tr>

                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 0 50px 50px 50px;">

                            <h2 style="margin: 0 0 25px 0; color: #000000; font-size: 22px; font-weight: 700; text-align: center; letter-spacing: 2px; text-transform: uppercase;">
                                ¡Pedido Confirmado!
                            </h2>

                            <p style="margin: 0 0 30px 0; color: #333333; font-size: 15px; line-height: 1.7; text-align: center;">
                                Hola <strong>{username}</strong>,<br>
                                Tu pedido <strong>#{order_id}</strong> está siendo procesado.
                            </p>

                            <!-- Detalles del/los productos -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f8f8f8; margin: 25px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 30px;">
                                        <p style="margin: 0 0 16px 0; color: #999999; font-size: 10px; text-transform: uppercase; letter-spacing: 3px; font-weight: 600;">
                                            Detalles del Pedido
                                        </p>
                                        <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">
                                            ' . $items_rows . '
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Dirección de envío -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f8f8f8; margin: 25px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 30px;">
                                        <p style="margin: 0 0 12px 0; color: #999999; font-size: 10px; text-transform: uppercase; letter-spacing: 3px; font-weight: 600;">
                                            Dirección de Envío
                                        </p>
                                        <p style="margin: 0; color: #000000; font-size: 14px; line-height: 1.6;">
                                            {shipping_address}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Total (fondo negro) -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #000000; margin: 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 30px;">
                                        <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">

                                            ' . $discount_html . '

                                            <tr>
                                                <td style="padding: 15px 0;">
                                                    <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td style="color: rgba(255,255,255,0.8); font-size: 14px; text-transform: uppercase; letter-spacing: 2px;">
                                                                Total Pagado:
                                                            </td>
                                                            <td align="right" style="color: #ffffff; font-size: 32px; font-weight: 900; font-family: monospace;">
                                                                €{total}
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Próximos pasos -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f0f9ff; border-left: 4px solid #3b82f6; margin: 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 25px;">
                                        <p style="margin: 0 0 12px 0; color: #1e40af; font-size: 13px; font-weight: 700;">
                                            Próximos Pasos
                                        </p>
                                        <p style="margin: 0; color: #1e3a8a; font-size: 13px; line-height: 1.7;">
                                            1. Preparamos tu pedido (24-48h)<br>
                                            2. Cuando esté listo te avisaremos (7 días máx.)<br>
                                            3. Recibes tu DCIEN en casa
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; padding-top: 25px; border-top: 1px solid #e0e0e0; color: #999999; font-size: 13px; line-height: 1.6; text-align: center;">
                                ¿Necesitas ayuda? Responde a este email o contáctanos en<br>
                                <a href="mailto:soporte@d-cien.es" style="color: #000000; text-decoration: underline;">soporte@d-cien.es</a>
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f8f8; padding: 30px 40px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0 0 15px 0; color: #999999; font-size: 12px;">
                                © ' . date('Y') . ' DCIEN - Ediciones Limitadas Exclusivas
                            </p>
                            <p style="margin: 0; font-size: 11px;">
                                <a href="https://www.instagram.com/dcien.esp/" style="color: #999999; text-decoration: none; margin: 0 10px;">Instagram</a>
                                <span style="color: #cccccc;">·</span>
                                <a href="https://d-cien.es" style="color: #999999; text-decoration: none; margin: 0 10px;">Web</a>
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
';
