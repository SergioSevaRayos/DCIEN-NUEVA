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
</head>
<body style="margin: 0; padding: 0; background-color: #000000; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Arial, sans-serif;">
    
    <!-- Container principal -->
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #000000;" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 60px 20px;">
                
                <!-- Card blanco -->
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
                                Restablecer Contraseña
                            </h2>
                            
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 15px; line-height: 1.7; text-align: center;">
                                Hola <strong>{username}</strong>,<br>
                                Has solicitado restablecer tu contraseña.
                            </p>
                            
                            <p style="margin: 0 0 30px 0; color: #666666; font-size: 14px; line-height: 1.7; text-align: center;">
                                Haz clic en el botón de abajo para crear una nueva contraseña.
                            </p>
                            
                            <!-- Botón CTA -->
                            <table role="presentation" style="margin: 40px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{reset_link}" 
                                           style="display: inline-block; padding: 16px 40px; background-color: #000000; color: #ffffff; text-decoration: none; font-size: 11px; font-weight: 700; letter-spacing: 4px; text-transform: uppercase;">
                                            Restablecer Contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Info de seguridad -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #fff3cd; border-left: 4px solid #ffc107; margin: 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 8px 0; color: #856404; font-size: 12px; font-weight: 600;">
                                            ⏱️ Este enlace expira en 1 hora
                                        </p>
                                        <p style="margin: 0; color: #856404; font-size: 12px; line-height: 1.5;">
                                            Si no solicitaste cambiar tu contraseña, ignora este email. Tu cuenta permanece segura.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 30px 0 0 0; padding-top: 25px; border-top: 1px solid #e0e0e0; color: #999999; font-size: 12px; line-height: 1.6; text-align: center;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                <a href="{reset_link}" style="color: #666666; word-break: break-all;">{reset_link}</a>
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