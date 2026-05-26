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
        <td style="padding: 12px 0; border-top: 1px solid #2a2a2a;">
            <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color: #888888; font-size: 13px; font-weight: 500;">Precio original:</td>
                    <td align="right" style="color: #888888; font-size: 13px; font-weight: 500; text-decoration: line-through;">'
                        . htmlspecialchars($data['original_price'] ?? '—') .
                    '</td>
                </tr>
                <tr>
                    <td style="color: #4ade80; font-size: 13px; font-weight: 600; padding-top: 6px;">
                        Descuento (' . htmlspecialchars($data['discount_code']) . '):
                    </td>
                    <td align="right" style="color: #4ade80; font-size: 13px; font-weight: 700; padding-top: 6px;">
                        -€' . htmlspecialchars($data['discount_amount']) . '
                    </td>
                </tr>
            </table>
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
            <td style="padding: 14px 0; border-bottom: 1px solid #2a2a2a;">
                <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #ffffff; font-size: 14px; font-weight: 700; letter-spacing: 0.5px;">
                            ' . htmlspecialchars($seriesLabel) . ' <span style="font-family: monospace; color: #ffbd59;">#' . $unitPadded . '</span>
                        </td>
                        <td align="right" style="color: #888888; font-size: 12px; font-weight: 500; letter-spacing: 0.3px;">
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #121212;
            font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        @media only screen and (max-width: 600px) {
            .card {
                padding: 40px 20px !important;
            }
            .title {
                font-size: 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #121212;">

    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #121212;" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 60px 20px;">

                <!-- Card Principal -->
                <table role="presentation" class="card" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 0px;" cellpadding="0" cellspacing="0">
                    
                    <!-- Header Logo -->
                    <tr>
                        <td align="center" style="padding: 50px 40px 30px 40px;">
                            <h1 style="margin: 0; color: #ffffff; font-family: \'Outfit\', \'Arial Black\', sans-serif; font-size: 40px; font-weight: 900; letter-spacing: 14px; text-transform: uppercase; line-height: 1;">
                                DCIEN
                            </h1>
                            <div style="width: 60px; height: 1px; background-color: rgba(255, 255, 255, 0.15); margin: 20px auto 0 auto;"></div>
                        </td>
                    </tr>

                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 0 50px 50px 50px;">
                            
                            <h2 class="title" style="margin: 0 0 20px 0; color: #ffffff; font-family: \'Outfit\', \'Arial Black\', sans-serif; font-size: 22px; font-weight: 800; text-align: center; letter-spacing: 2px; text-transform: uppercase;">
                                PEDIDO CONFIRMADO
                            </h2>
                            
                            <p style="margin: 0 0 30px 0; color: #a3a3a3; font-size: 14px; line-height: 1.8; text-align: center; font-weight: 400; letter-spacing: 0.5px;">
                                Hola <strong style="color: #ffffff; font-weight: 600;">{username}</strong>,<br>
                                Tu pedido <strong style="color: #ffffff; font-weight: 600;">#{order_id}</strong> ha sido confirmado y registrado correctamente. A continuación, tienes los detalles del activo adquirido y el protocolo de entrega.
                            </p>
                            
                            <!-- Detalles de los Productos (Estilo técnico) -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #222222; border: 1px solid #2a2a2a; margin: 25px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 24px 30px;">
                                        <p style="margin: 0 0 14px 0; color: #888888; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 3px;">
                                            ACTIVOS ADQUIRIDOS
                                        </p>
                                        <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">
                                            ' . $items_rows . '
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Dirección de Envío (Ficha técnica) -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #222222; border: 1px solid #2a2a2a; margin: 25px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 24px 30px;">
                                        <p style="margin: 0 0 12px 0; color: #888888; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 3px;">
                                            DIRECCIÓN DE ENTREGA
                                        </p>
                                        <p style="margin: 0; color: #ffffff; font-size: 13px; line-height: 1.7; letter-spacing: 0.3px; font-weight: 400;">
                                            {shipping_address}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Desglose de Importes (Caja Negra) -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #0c0c0c; border: 1px solid #2a2a2a; margin: 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 24px 30px;">
                                        <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">
                                            ' . $discount_html . '
                                            <tr>
                                                <td style="padding: 6px 0;">
                                                    <table role="presentation" style="width: 100%;" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td style="color: #888888; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">
                                                                TOTAL PAGADO:
                                                            </td>
                                                            <td align="right" style="color: #ffffff; font-size: 26px; font-weight: 800; font-family: monospace;">
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

                            <!-- Próximos Pasos (Protocolo de entrega azul DCIEN) -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #101622; border-left: 3px solid #1800ad; margin: 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 10px 0; color: #728cfc; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px;">
                                            ℹ️ PROTOCOLO DE ENTREGA
                                        </p>
                                        <p style="margin: 0; color: #a4b6fc; font-size: 12px; line-height: 1.7; letter-spacing: 0.3px;">
                                            1. <strong>Producción Semanal:</strong> Los pedidos realizados antes de los miércoles a las 23:59h entran en el ciclo de producción de esa semana.<br>
                                            2. <strong>Plazo de Envío:</strong> Una vez fabricado, el plazo estimado de entrega es de 10 días laborables.<br>                                            
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; padding-top: 25px; border-top: 1px solid #2a2a2a; color: #666666; font-size: 11px; line-height: 1.6; text-align: center; letter-spacing: 0.5px;">
                                ¿Necesitas ayuda o realizar cambios en tu dirección de envío? Responde directamente a este correo o escríbenos a <a href="mailto:contacto@d-cien.es" style="color: #ffffff; text-decoration: underline;">soporte@d-cien.es</a>
                            </p>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #151515; padding: 30px 40px; text-align: center; border-top: 1px solid #2a2a2a;">
                            <p style="margin: 0 0 12px 0; color: #666666; font-size: 11px; letter-spacing: 1px;">
                                © ' . date('Y') . ' DCIEN - Ediciones Limitadas Exclusivas
                            </p>
                            <p style="margin: 0; font-size: 11px;">
                                <a href="https://www.instagram.com/dcien.esp/" style="color: #a3a3a3; text-decoration: none; margin: 0 10px; font-weight: 500; letter-spacing: 0.5px;">Instagram</a>
                                <span style="color: #333333;">·</span>
                                <a href="https://d-cien.es" style="color: #a3a3a3; text-decoration: none; margin: 0 10px; font-weight: 500; letter-spacing: 0.5px;">Web Oficial</a>
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
