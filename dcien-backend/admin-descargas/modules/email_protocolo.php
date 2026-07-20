<?php
// FUNCIÓN DE EMAIL
// ═══════════════════════════════════════════════════════════════
function enviar_email_protocolo($email, $username, $code, $description, $type, $value, $series_slug, $userId = null) {
    $valor_texto = $type === 'percent' ? number_format($value, 0) . '%' : '€' . number_format($value, 2);
    $nombre      = strtoupper($username ?: 'Atleta');
    $year        = date('Y');
    $series_link = $series_slug
        ? "https://d-cien.es/series-activas/{$series_slug}"
        : "https://d-cien.es/series-activas";
    $series_label = $series_slug
        ? 'ACCEDER A LA SERIE'
        : 'VER SERIES ACTIVAS';
    $serie_txt = $series_slug
        ? '<tr>
                                            <td style="padding:6px 0;color:#888888;font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:1px;width:120px;">Válido para:</td>
                                            <td style="padding:6px 0;color:#ffffff;font-size:13px;font-family:monospace;font-weight:600;">' . strtoupper($series_slug) . '</td>
                                        </tr>'
        : '';

    $subject = "DCIEN | Nuevo Protocolo de Validación: {$code}";

    $message_html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Protocolo DCIEN</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        body { margin:0; padding:0; background-color:#121212; font-family:\'Inter\',-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased; }
        @media only screen and (max-width:600px) { .card { padding:40px 20px !important; } .title { font-size:20px !important; } }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#121212;">

    <table role="presentation" style="width:100%;border-collapse:collapse;background-color:#121212;" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding:60px 20px;">

                <!-- Card Principal -->
                <table role="presentation" class="card" style="max-width:600px;width:100%;border-collapse:collapse;background-color:#1a1a1a;border:1px solid #2a2a2a;" cellpadding="0" cellspacing="0">

                    <!-- Header Logo -->
                    <tr>
                        <td align="center" style="padding:50px 40px 30px 40px;">
                            <h1 style="margin:0;color:#ffffff;font-family:\'Outfit\',\'Arial Black\',sans-serif;font-size:40px;font-weight:900;letter-spacing:14px;text-transform:uppercase;line-height:1;">
                                DCIEN
                            </h1>
                            <div style="width:60px;height:1px;background-color:rgba(255,255,255,0.15);margin:20px auto 0 auto;"></div>
                        </td>
                    </tr>

                    <!-- Contenido -->
                    <tr>
                        <td style="padding:0 50px 50px 50px;">

                            <h2 class="title" style="margin:0 0 20px 0;color:#ffffff;font-family:\'Outfit\',\'Arial Black\',sans-serif;font-size:24px;font-weight:800;text-align:center;letter-spacing:2px;text-transform:uppercase;">
                                PROTOCOLO VALIDADO
                            </h2>

                            <p style="margin:0 0 30px 0;color:#a3a3a3;font-size:14px;line-height:1.8;text-align:center;font-weight:400;letter-spacing:0.5px;">
                                Hola <strong style="color:#ffffff;font-weight:600;">' . $nombre . '</strong>,<br>
                                Hemos emitido un crédito de desempeño a tu favor. A continuación encontrarás los detalles del protocolo asignado a tu perfil.
                            </p>

                            <!-- Código destacado -->
                            <table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:28px 20px;background-color:#000000;border:1px solid #333333;">
                                        <p style="margin:0 0 8px 0;color:#666666;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:4px;">Código de Protocolo</p>
                                        <p style="margin:0;color:#ffffff;font-family:monospace;font-size:30px;font-weight:700;letter-spacing:6px;">' . htmlspecialchars($code) . '</p>
                                        <p style="margin:8px 0 0 0;color:#888888;font-size:11px;letter-spacing:1px;">' . htmlspecialchars($valor_texto) . ' de descuento</p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Ficha técnica -->
                            <table role="presentation" style="width:100%;border-collapse:collapse;background-color:#222222;border:1px solid #2a2a2a;margin:0 0 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:24px 30px;">
                                        <p style="margin:0 0 16px 0;color:#888888;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:3px;">FICHA DE PROTOCOLO</p>
                                        <table role="presentation" style="width:100%;" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:6px 0;color:#888888;font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:1px;width:120px;">Atleta:</td>
                                                <td style="padding:6px 0;color:#ffffff;font-size:13px;font-family:monospace;font-weight:600;">' . $nombre . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;color:#888888;font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:1px;">Recompensa:</td>
                                                <td style="padding:6px 0;color:#ffffff;font-size:13px;font-family:monospace;font-weight:600;">' . htmlspecialchars($description) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;color:#888888;font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:1px;">Valor:</td>
                                                <td style="padding:6px 0;color:#4ade80;font-size:13px;font-weight:700;letter-spacing:1px;">' . htmlspecialchars($valor_texto) . '</td>
                                            </tr>
                                            ' . $serie_txt . '
                                            <tr>
                                                <td style="padding:6px 0;color:#888888;font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:1px;">Estado:</td>
                                                <td style="padding:6px 0;color:#4ade80;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;">● ACTIVO / PENDIENTE DE USO</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Instrucciones -->
                            <p style="margin:0 0 30px 0;color:#a3a3a3;font-size:13px;line-height:1.8;text-align:center;letter-spacing:0.3px;">
                                Este crédito ya está disponible en tu perfil. Cuando accedas a tu carrito de la compra, podrás activarlo seleccionando su casilla correspondiente o guardarlo para utilizarlo en futuros pedidos.
                            </p>

                            <!-- CTA -->
                            <table role="presentation" style="width:100%;margin:0 0 20px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="' . $series_link . '"
                                           style="display:inline-block;box-sizing:border-box;width:100%;padding:18px 40px;background-color:#ffffff;color:#1a1a1a;text-decoration:none;font-size:11px;font-weight:700;letter-spacing:3px;text-transform:uppercase;text-align:center;">
                                            ' . $series_label . '
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:30px 0 0 0;padding-top:25px;border-top:1px solid #2a2a2a;color:#666666;font-size:11px;line-height:1.6;text-align:center;letter-spacing:0.5px;">
                                Si tienes alguna duda sobre tu protocolo, contacta con nosotros en <a href="mailto:soporte@d-cien.es" style="color:#ffffff;text-decoration:underline;">soporte@d-cien.es</a>
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#151515;padding:30px 40px;text-align:center;border-top:1px solid #2a2a2a;">
                            <p style="margin:0 0 12px 0;color:#666666;font-size:11px;letter-spacing:1px;">
                                &copy; ' . $year . ' DCIEN &mdash; Ediciones Limitadas Exclusivas
                            </p>
                            <p style="margin:0;font-size:11px;">
                                <a href="https://www.instagram.com/dcien.esp/" style="color:#a3a3a3;text-decoration:none;margin:0 10px;font-weight:500;letter-spacing:0.5px;">Instagram</a>
                                <span style="color:#333333;">&middot;</span>
                                <a href="https://d-cien.es" style="color:#a3a3a3;text-decoration:none;margin:0 10px;font-weight:500;letter-spacing:0.5px;">Web Oficial</a>
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>';

    return sendAdminMail($email, $subject, $message_html, 'protocolo_descuento', $userId, $username);
}
