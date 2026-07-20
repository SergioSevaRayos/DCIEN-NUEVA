<?php
// ═══════════════════════════════════════════════════════════════
// FUNCIÓN DE EMAIL: COMUNICACIONES GENÉRICAS (CAMPAÑAS)
// ═══════════════════════════════════════════════════════════════
function enviar_email_campana($email, $username, $subject, $titulo, $cuerpo, $cta_texto = '', $cta_link = '', $userId = null) {
    $nombre = strtoupper($username ?: 'Atleta');
    $year   = date('Y');

    // Procesar el cuerpo para convertir saltos de línea en <p>
    $cuerpo_html = '';
    $parrafos = explode("\n", $cuerpo);
    foreach ($parrafos as $p) {
        $p = trim($p);
        if (!empty($p)) {
            $cuerpo_html .= '<p style="margin:0 0 15px 0;color:#a3a3a3;font-size:14px;line-height:1.8;text-align:center;font-weight:400;letter-spacing:0.5px;">' . nl2br(htmlspecialchars($p)) . '</p>';
        }
    }

    $boton_html = '';
    if (!empty($cta_texto) && !empty($cta_link)) {
        $boton_html = '
        <table role="presentation" style="width:100%;margin:30px 0 20px 0;" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center">
                    <a href="' . htmlspecialchars($cta_link) . '"
                       style="display:inline-block;box-sizing:border-box;width:100%;padding:18px 40px;background-color:#ffffff;color:#1a1a1a;text-decoration:none;font-size:11px;font-weight:700;letter-spacing:3px;text-transform:uppercase;text-align:center;">
                        ' . htmlspecialchars($cta_texto) . '
                    </a>
                </td>
            </tr>
        </table>';
    }

    $message_html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($subject) . '</title>
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
                                ' . htmlspecialchars($titulo) . '
                            </h2>

                            <p style="margin:0 0 30px 0;color:#ffffff;font-size:16px;line-height:1.8;text-align:center;font-weight:600;letter-spacing:0.5px;">
                                Hola ' . $nombre . ',
                            </p>

                            ' . $cuerpo_html . '
                            ' . $boton_html . '

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

    return sendAdminMail($email, $subject, $message_html, 'comunicado', $userId, $username);
}
