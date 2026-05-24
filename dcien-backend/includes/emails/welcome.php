<?php
/**
 * Email Template: Bienvenida
 * Datos esperados: username, email
 */

return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a DCIEN</title>
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
                                Bienvenido, {username}
                            </h2>
                            
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 15px; line-height: 1.7; text-align: center;">
                                Tu cuenta ha sido <strong>activada correctamente</strong>.<br>
                                Ya formas parte de nuestra comunidad exclusiva.
                            </p>
                            
                            <!-- Caja de info -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f8f8f8; margin: 30px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 30px;">
                                        <p style="margin: 0 0 12px 0; color: #999999; font-size: 10px; text-transform: uppercase; letter-spacing: 3px; font-weight: 600;">
                                            Datos de tu cuenta
                                        </p>
                                        <p style="margin: 0 0 10px 0; color: #000000; font-size: 14px;">
                                            <strong>Usuario:</strong> {username}
                                        </p>
                                        <p style="margin: 0; color: #000000; font-size: 14px;">
                                            <strong>Email:</strong> {email}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Botón CTA -->
                            <table role="presentation" style="margin: 40px 0;" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="https://d-cien.es/series-activas" 
                                           style="display: inline-block; padding: 16px 40px; background-color: #000000; color: #ffffff; text-decoration: none; font-size: 11px; font-weight: 700; letter-spacing: 4px; text-transform: uppercase; transition: all 0.3s ease;">
                                            Ver Series Activas
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 30px 0 0 0; padding-top: 25px; border-top: 1px solid #e0e0e0; color: #999999; font-size: 13px; line-height: 1.6; text-align: center;">
                                Inicia sesión en <a href="https://d-cien.es/acceso" style="color: #000000; text-decoration: underline;">d-cien.es/acceso</a>
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