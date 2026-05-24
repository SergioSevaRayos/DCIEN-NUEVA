#!/usr/bin/env php
<?php
/**
 * GESTOR COMPLETO DE USUARIOS DCIEN
 * Gestión integral coordinada con todas las tablas de BD
 */

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN
// ═══════════════════════════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_NAME', 'u755459505_limited_tees');
define('DB_USER', 'u755459505_sergio');

$GLOBALS['pdo'] = null;

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE CONEXIÓN
// ═══════════════════════════════════════════════════════════════

function conectar_db($password) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800"
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        echo "❌ Error de conexión: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function verificar_conexion($pdo) {
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE CONSULTA
// ═══════════════════════════════════════════════════════════════

function obtener_usuarios($pdo) {
    $stmt = $pdo->query("
        SELECT 
            u.*,
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT ud.id) as total_discounts,
            COUNT(DISTINCT CASE WHEN ud.used_at IS NULL THEN ud.id END) as available_discounts
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id
        LEFT JOIN user_discounts ud ON ud.user_id = u.id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_usuario_detalle($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtener_pedidos_usuario($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            s.name as series_name,
            d.code as discount_code
        FROM orders o
        LEFT JOIN series s ON s.slug = o.series_slug
        LEFT JOIN discounts d ON d.id = o.discount_id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_descuentos_usuario($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            ud.*,
            d.code,
            d.description,
            d.type,
            d.value
        FROM user_discounts ud
        INNER JOIN discounts d ON d.id = ud.discount_id
        WHERE ud.user_id = ?
        ORDER BY ud.assigned_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_token_activacion($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM activation_tokens
        WHERE instagram_username = (
            SELECT instagram_username FROM users WHERE id = ?
        )
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE MODIFICACIÓN
// ═══════════════════════════════════════════════════════════════

function editar_usuario($pdo, $user_id, $data) {
    try {
        $fields = [];
        $params = [];
        
        if (isset($data['username'])) {
            $fields[] = "username = ?";
            $params[] = $data['username'];
        }
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        if (isset($data['instagram_username'])) {
            $fields[] = "instagram_username = ?";
            $params[] = $data['instagram_username'];
        }
        if (isset($data['can_purchase'])) {
            $fields[] = "can_purchase = ?";
            $params[] = $data['can_purchase'];
        }
        if (isset($data['is_verified'])) {
            $fields[] = "is_verified = ?";
            $params[] = $data['is_verified'];
        }
        
        if (empty($fields)) {
            return [false, "No hay campos para actualizar"];
        }
        
        $fields[] = "updated_at = NOW()";
        $params[] = $user_id;
        
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return [true, "Usuario actualizado correctamente"];
    } catch (Exception $e) {
        return [false, "Error: " . $e->getMessage()];
    }
}

function cambiar_password($pdo, $user_id, $new_password) {
    try {
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hash, $user_id]);
        return [true, "Contraseña cambiada correctamente"];
    } catch (Exception $e) {
        return [false, "Error: " . $e->getMessage()];
    }
}

function eliminar_usuario($pdo, $user_id) {
    try {
        // Verificar si tiene pedidos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $has_orders = $stmt->fetchColumn() > 0;
        
        if ($has_orders) {
            return [false, "No se puede eliminar: el usuario tiene pedidos asociados"];
        }
        
        $pdo->beginTransaction();
        
        // Eliminar descuentos asignados
        $stmt = $pdo->prepare("DELETE FROM user_discounts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Eliminar usuario
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        return [true, "Usuario eliminado correctamente"];
    } catch (Exception $e) {
        $pdo->rollBack();
        return [false, "Error: " . $e->getMessage()];
    }
}

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE INTERFAZ
// ═══════════════════════════════════════════════════════════════

function limpiar_pantalla() {
    echo str_repeat("\n", 50);
}

function mostrar_banner() {
    echo str_repeat("═", 70) . "\n";
    echo "   GESTOR DE USUARIOS DCIEN\n";
    echo str_repeat("═", 70) . "\n\n";
}

function listar_usuarios($usuarios) {
    echo "\n📋 USUARIOS REGISTRADOS:\n";
    echo str_repeat("-", 110) . "\n";
    printf("%-4s %-20s %-30s %-20s %-8s %-8s %-10s\n", 
        '#', 'Username', 'Email', 'Instagram', 'Pedidos', 'Comprar', 'Descuentos'
    );
    echo str_repeat("-", 110) . "\n";
    
    foreach ($usuarios as $idx => $user) {
        $username = $user['username'] ?: '(sin username)';
        $instagram = $user['instagram_username'] ?: '-';
        $can_purchase = $user['can_purchase'] ? '✓' : '✗';
        $discounts_info = $user['available_discounts'] . '/' . $user['total_discounts'];
        
        printf("%-4d %-20s %-30s @%-19s %-8s %-8s %-10s\n",
            $idx + 1,
            substr($username, 0, 20),
            substr($user['email'], 0, 30),
            substr($instagram, 0, 19),
            $user['total_orders'],
            $can_purchase,
            $discounts_info
        );
    }
    
    echo str_repeat("-", 110) . "\n";
    echo "Total: " . count($usuarios) . " usuarios\n";
}

function mostrar_detalle_usuario($pdo, $user) {
    echo "\n👤 DETALLE DE USUARIO\n";
    echo str_repeat("═", 70) . "\n\n";
    
    echo "ID: {$user['id']}\n";
    echo "Username: " . ($user['username'] ?: '(sin username)') . "\n";
    echo "Email: {$user['email']}\n";
    echo "Instagram: @" . ($user['instagram_username'] ?: 'sin instagram') . "\n";
    echo "Verificado: " . ($user['is_verified'] ? 'Sí' : 'No') . "\n";
    echo "Puede comprar: " . ($user['can_purchase'] ? 'Sí' : 'No') . "\n";
    echo "Registrado: {$user['created_at']}\n";
    echo "Última actualización: {$user['updated_at']}\n";
    
    // Token de activación
    $token = obtener_token_activacion($pdo, $user['id']);
    if ($token) {
        echo "\n📝 TOKEN DE ACTIVACIÓN:\n";
        echo "  Usado: " . ($token['used_at'] ? "Sí ({$token['used_at']})" : "No") . "\n";
        echo "  Expira: {$token['expires_at']}\n";
    }
    
    // Pedidos
    $pedidos = obtener_pedidos_usuario($pdo, $user['id']);
    echo "\n🛒 PEDIDOS ({" . count($pedidos) . "}):\n";
    if (empty($pedidos)) {
        echo "  (Sin pedidos)\n";
    } else {
        echo str_repeat("-", 70) . "\n";
        printf("  %-6s %-12s %-8s %-10s %-12s\n", 'ID', 'Serie', 'Unidad', 'Precio', 'Fecha');
        echo str_repeat("-", 70) . "\n";
        foreach ($pedidos as $pedido) {
            $desc = $pedido['discount_code'] ? " (-{$pedido['discount_code']})" : "";
            printf("  %-6s %-12s #%-7s €%-9s %s\n",
                $pedido['id'],
                $pedido['series_slug'],
                $pedido['unit_number'],
                $pedido['price'] . $desc,
                substr($pedido['created_at'], 0, 10)
            );
        }
    }
    
    // Descuentos
    $descuentos = obtener_descuentos_usuario($pdo, $user['id']);
    echo "\n🎁 DESCUENTOS ({" . count($descuentos) . "}):\n";
    if (empty($descuentos)) {
        echo "  (Sin descuentos)\n";
    } else {
        echo str_repeat("-", 70) . "\n";
        printf("  %-15s %-30s %-15s\n", 'Código', 'Descripción', 'Estado');
        echo str_repeat("-", 70) . "\n";
        foreach ($descuentos as $desc) {
            $estado = $desc['used_at'] ? "Usado" : "Disponible";
            printf("  %-15s %-30s %-15s\n",
                $desc['code'],
                substr($desc['description'], 0, 30),
                $estado
            );
        }
    }
    
    echo "\n" . str_repeat("═", 70) . "\n";
}

function menu_principal() {
    echo "\n🔹 MENÚ PRINCIPAL:\n";
    echo "  1. Ver lista de usuarios\n";
    echo "  2. Ver detalle de un usuario\n";
    echo "  3. Editar usuario\n";
    echo "  4. Cambiar contraseña de usuario\n";
    echo "  5. Activar/Desactivar permiso de compra\n";
    echo "  6. Eliminar usuario\n";
    echo "  7. Estadísticas generales\n";
    echo "  0. Salir\n\n";
    
    return trim(readline("Opción: "));
}

// ═══════════════════════════════════════════════════════════════
// ACCIONES DEL MENÚ
// ═══════════════════════════════════════════════════════════════

function accion_ver_lista($pdo, $usuarios) {
    limpiar_pantalla();
    mostrar_banner();
    listar_usuarios($usuarios);
    readline("\nPresiona Enter para continuar...");
}

function accion_ver_detalle($pdo, $usuarios) {
    limpiar_pantalla();
    mostrar_banner();
    
    listar_usuarios($usuarios);
    $idx = intval(readline("\nSelecciona usuario (#): ")) - 1;
    
    if ($idx < 0 || $idx >= count($usuarios)) {
        echo "❌ Usuario inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $user = $usuarios[$idx];
    $user_full = obtener_usuario_detalle($pdo, $user['id']);
    
    limpiar_pantalla();
    mostrar_banner();
    mostrar_detalle_usuario($pdo, $user_full);
    readline("\nPresiona Enter para continuar...");
}

function accion_editar_usuario($pdo, $usuarios) {
    limpiar_pantalla();
    mostrar_banner();
    
    listar_usuarios($usuarios);
    $idx = intval(readline("\nSelecciona usuario (#): ")) - 1;
    
    if ($idx < 0 || $idx >= count($usuarios)) {
        echo "❌ Usuario inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $user = $usuarios[$idx];
    
    echo "\n📝 EDITAR USUARIO @{$user['instagram_username']}\n";
    echo str_repeat("-", 50) . "\n";
    echo "Deja en blanco para mantener el valor actual\n\n";
    
    $data = [];
    
    $new_username = trim(readline("Username [{$user['username']}]: "));
    if ($new_username) $data['username'] = $new_username;
    
    $new_email = trim(readline("Email [{$user['email']}]: "));
    if ($new_email) $data['email'] = $new_email;
    
    $new_instagram = trim(readline("Instagram [@{$user['instagram_username']}]: "));
    if ($new_instagram) $data['instagram_username'] = $new_instagram;
    
    if (empty($data)) {
        echo "\n❌ No hay cambios para aplicar\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $confirmar = strtolower(trim(readline("\n¿Confirmar cambios? (s/n): ")));
    if ($confirmar !== 's') {
        echo "❌ Operación cancelada\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    list($success, $msg) = editar_usuario($pdo, $user['id'], $data);
    echo $success ? "\n✅ $msg\n" : "\n❌ $msg\n";
    readline("\nPresiona Enter para continuar...");
}

function accion_cambiar_password($pdo, $usuarios) {
    limpiar_pantalla();
    mostrar_banner();
    
    listar_usuarios($usuarios);
    $idx = intval(readline("\nSelecciona usuario (#): ")) - 1;
    
    if ($idx < 0 || $idx >= count($usuarios)) {
        echo "❌ Usuario inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $user = $usuarios[$idx];
    
    echo "\n🔐 CAMBIAR CONTRASEÑA DE @{$user['instagram_username']}\n";
    echo str_repeat("-", 50) . "\n";
    
    $new_password = trim(readline("Nueva contraseña (mín. 8 caracteres): "));
    
    if (strlen($new_password) < 8) {
        echo "\n❌ La contraseña debe tener al menos 8 caracteres\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $confirmar = strtolower(trim(readline("\n¿Confirmar cambio de contraseña? (s/n): ")));
    if ($confirmar !== 's') {
        echo "❌ Operación cancelada\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    list($success, $msg) = cambiar_password($pdo, $user['id'], $new_password);
    echo $success ? "\n✅ $msg\n" : "\n❌ $msg\n";
    readline("\nPresiona Enter para continuar...");
}

function accion_toggle_compra($pdo, $usuarios) {
    limpiar_pantalla();
    mostrar_banner();
    
    listar_usuarios($usuarios);
    $idx = intval(readline("\nSelecciona usuario (#): ")) - 1;
    
    if ($idx < 0 || $idx >= count($usuarios)) {
        echo "❌ Usuario inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $user = $usuarios[$idx];
    $new_status = $user['can_purchase'] ? 0 : 1;
    $action = $new_status ? 'ACTIVAR' : 'DESACTIVAR';
    
    $confirmar = strtolower(trim(readline("\n¿$action permiso de compra para @{$user['instagram_username']}? (s/n): ")));
    
    if ($confirmar !== 's') {
        echo "❌ Operación cancelada\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    list($success, $msg) = editar_usuario($pdo, $user['id'], ['can_purchase' => $new_status]);
    echo $success ? "\n✅ Permiso de compra $action\n" : "\n❌ $msg\n";
    readline("\nPresiona Enter para continuar...");
}

function accion_eliminar_usuario($pdo, $usuarios) {
    limpiar_pantalla();
    mostrar_banner();
    
    listar_usuarios($usuarios);
    $idx = intval(readline("\nSelecciona usuario (#): ")) - 1;
    
    if ($idx < 0 || $idx >= count($usuarios)) {
        echo "❌ Usuario inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $user = $usuarios[$idx];
    
    echo "\n⚠️  ELIMINAR USUARIO\n";
    echo str_repeat("-", 50) . "\n";
    echo "Usuario: @{$user['instagram_username']}\n";
    echo "Email: {$user['email']}\n";
    echo "Pedidos: {$user['total_orders']}\n";
    echo "\n⚠️  Esta acción NO se puede deshacer\n";
    
    $confirmar1 = trim(readline("\nEscribe 'ELIMINAR' para confirmar: "));
    if ($confirmar1 !== 'ELIMINAR') {
        echo "❌ Operación cancelada\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    list($success, $msg) = eliminar_usuario($pdo, $user['id']);
    echo $success ? "\n✅ $msg\n" : "\n❌ $msg\n";
    readline("\nPresiona Enter para continuar...");
}

function accion_estadisticas($pdo) {
    limpiar_pantalla();
    mostrar_banner();
    
    echo "📊 ESTADÍSTICAS GENERALES\n";
    echo str_repeat("═", 70) . "\n\n";
    
    // Total usuarios
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();
    
    // Usuarios verificados
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1");
    $verified_users = $stmt->fetchColumn();
    
    // Usuarios con permiso de compra
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE can_purchase = 1");
    $can_purchase_users = $stmt->fetchColumn();
    
    // Total pedidos
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt->fetchColumn();
    
    // Total en ventas
    $stmt = $pdo->query("SELECT SUM(price) FROM orders");
    $total_sales = $stmt->fetchColumn();
    
    // Usuarios con descuentos
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_discounts");
    $users_with_discounts = $stmt->fetchColumn();
    
    // Descuentos usados
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_discounts WHERE used_at IS NOT NULL");
    $discounts_used = $stmt->fetchColumn();
    
    echo "👥 Usuarios:\n";
    echo "  Total: $total_users\n";
    echo "  Verificados: $verified_users\n";
    echo "  Pueden comprar: $can_purchase_users\n";
    echo "  Con descuentos: $users_with_discounts\n\n";
    
    echo "🛒 Pedidos:\n";
    echo "  Total: $total_orders\n";
    echo "  Ventas totales: €" . number_format($total_sales, 2) . "\n";
    echo "  Promedio por pedido: €" . number_format($total_sales / max($total_orders, 1), 2) . "\n\n";
    
    echo "🎁 Descuentos:\n";
    echo "  Descuentos usados: $discounts_used\n\n";
    
    // Top usuarios por pedidos
    $stmt = $pdo->query("
        SELECT u.username, u.instagram_username, COUNT(o.id) as orders
        FROM users u
        INNER JOIN orders o ON o.user_id = u.id
        GROUP BY u.id
        ORDER BY orders DESC
        LIMIT 5
    ");
    $top_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "🏆 Top 5 usuarios por pedidos:\n";
    echo str_repeat("-", 50) . "\n";
    foreach ($top_users as $idx => $user) {
        $username = $user['username'] ?: $user['instagram_username'];
        echo "  " . ($idx + 1) . ". @$username - {$user['orders']} pedidos\n";
    }
    
    echo "\n" . str_repeat("═", 70) . "\n";
    readline("\nPresiona Enter para continuar...");
}

// ═══════════════════════════════════════════════════════════════
// MAIN
// ═══════════════════════════════════════════════════════════════

function main() {
    limpiar_pantalla();
    mostrar_banner();
    
    echo "Conexión a la base de datos:\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n\n";
    
    $password = readline("Password: ");
    
    echo "\n🔄 Conectando...\n";
    $pdo = conectar_db($password);
    echo "✅ Conectado correctamente\n\n";
    readline("Presiona Enter para continuar...");
    
    while (true) {
        if (!verificar_conexion($pdo)) {
            echo "\n❌ Conexión perdida. Reinicia el script.\n";
            exit(1);
        }
        
        // Recargar usuarios en cada iteración
        $usuarios = obtener_usuarios($pdo);
        
        limpiar_pantalla();
        mostrar_banner();
        echo "📊 {" . count($usuarios) . "} usuarios registrados\n";
        
        $opcion = menu_principal();
        
        switch ($opcion) {
            case '1':
                accion_ver_lista($pdo, $usuarios);
                break;
            case '2':
                accion_ver_detalle($pdo, $usuarios);
                break;
            case '3':
                accion_editar_usuario($pdo, $usuarios);
                break;
            case '4':
                accion_cambiar_password($pdo, $usuarios);
                break;
            case '5':
                accion_toggle_compra($pdo, $usuarios);
                break;
            case '6':
                accion_eliminar_usuario($pdo, $usuarios);
                break;
            case '7':
                accion_estadisticas($pdo);
                break;
            case '0':
                echo "\n👋 ¡Hasta pronto!\n";
                return;
            default:
                echo "\n❌ Opción inválida\n";
                readline("\nPresiona Enter para continuar...");
        }
    }
}

// Ejecutar
try {
    main();
} catch (Exception $e) {
    echo "\n❌ Error fatal: " . $e->getMessage() . "\n";
    exit(1);
}