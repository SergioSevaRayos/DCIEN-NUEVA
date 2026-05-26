<?php
/**
 * Email Template: Recuperación de Contraseña
 * Datos esperados: username, reset_link
 */

return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - DCIEN</title>
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
                                RECUPERAR ACCESO
                            </h2>
                            
                            <p style="margin: 0 0 24px 0; color: #a3a3a3; font-size: 14px; line-height: 1.8; text-align: center; font-weight: 400; letter-spacing: 0.5px;">
                                Hola <strong style="color: #ffffff; font-weight: 600;">{username}</strong>,<br>
                                Se ha solicitado una solicitud de recuperación de contraseña para tu cuenta. Si has sido tú, puedes continuar haciendo clic en el botón de abajo.
                            </p>
                            
                            <!-- Botón CTA -->
                            <table role="presentation" style="width: 100%; margin: 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{reset_link}" 
                                           style="display: inline-block; box-sizing: border-box; width: 100%; padding: 18px 40px; background-color: #ffffff; color: #1a1a1a; text-decoration: none; font-size: 11px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; text-align: center; transition: all 0.3s ease;">
                                            RESTABLECER CONTRASEÑA
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Alerta de seguridad (Estilo minimalista técnico) -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #241c0c; border-left: 3px solid #ffbd59; margin: 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 8px 0; color: #ffbd59; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px;">
                                            ⏱️ Enlace temporal
                                        </p>
                                        <p style="margin: 0; color: #d4a34b; font-size: 12px; line-height: 1.6; letter-spacing: 0.3px;">
                                            Este enlace expira automáticamente en 1 hora. Si no has solicitado este cambio, puedes ignorar este correo y tu contraseña actual seguirá siendo válida.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 30px 0 0 0; padding-top: 25px; border-top: 1px solid #2a2a2a; color: #666666; font-size: 11px; line-height: 1.7; text-align: center; letter-spacing: 0.5px;">
                                Si el botón no funciona, copia y pega este enlace directo en tu navegador web:<br>
                                <a href="{reset_link}" style="color: #a3a3a3; text-decoration: underline; word-break: break-all;">{reset_link}</a>
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