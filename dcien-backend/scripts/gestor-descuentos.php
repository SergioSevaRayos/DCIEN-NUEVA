#!/usr/bin/env php
<?php
/**
 * Gestor de Descuentos DCIEN
 * Versión PHP - funciona directamente en el servidor
 */

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN
// ═══════════════════════════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_NAME', 'u755459505_limited_tees');
define('DB_USER', 'u755459505_sergio');

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE BASE DE DATOS
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
                PDO::ATTR_TIMEOUT => 60,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800"
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        echo "❌ Error de conexión: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function obtener_usuarios($pdo) {
    $stmt = $pdo->query("
        SELECT id, username, email, instagram_username, created_at
        FROM users 
        ORDER BY created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_descuentos($pdo) {
    $stmt = $pdo->query("
        SELECT id, code, description, type, value, is_active, used_count, max_uses
        FROM discounts 
        WHERE is_active = 1
        ORDER BY code
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_descuentos_usuario($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            ud.id as user_discount_id,
            d.code,
            d.description,
            ud.assigned_at,
            ud.used_at,
            ud.order_id
        FROM user_discounts ud
        INNER JOIN discounts d ON d.id = ud.discount_id
        WHERE ud.user_id = ?
        ORDER BY ud.assigned_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function asignar_descuento($pdo, $user_id, $discount_id) {
    try {
        // Verificar conexión antes de hacer queries
        try {
            $pdo->query('SELECT 1');
        } catch (PDOException $e) {
            // Reconectar si la conexión se perdió
            return [false, "Conexión perdida - reinicia el script"];
        }
        
        // Verificar si ya existe
        $stmt = $pdo->prepare("
            SELECT id FROM user_discounts 
            WHERE user_id = ? AND discount_id = ?
        ");
        $stmt->execute([$user_id, $discount_id]);
        
        if ($stmt->fetch()) {
            return [false, "Ya tiene este descuento asignado"];
        }
        
        // Insertar
        $stmt = $pdo->prepare("
            INSERT INTO user_discounts (user_id, discount_id, assigned_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $discount_id]);
        
        return [true, "Descuento asignado correctamente"];
    } catch (Exception $e) {
        return [false, "Error: " . $e->getMessage()];
    }
}

function revocar_descuento($pdo, $user_discount_id) {
    try {
        // Verificar si está usado
        $stmt = $pdo->prepare("SELECT used_at FROM user_discounts WHERE id = ?");
        $stmt->execute([$user_discount_id]);
        $result = $stmt->fetch();
        
        if ($result && $result['used_at']) {
            return [false, "No se puede revocar un descuento ya usado"];
        }
        
        // Eliminar
        $stmt = $pdo->prepare("DELETE FROM user_discounts WHERE id = ?");
        $stmt->execute([$user_discount_id]);
        
        return [true, "Descuento revocado correctamente"];
    } catch (Exception $e) {
        return [false, "Error: " . $e->getMessage()];
    }
}

// ═══════════════════════════════════════════════════════════════
// FUNCIONES DE INTERFAZ
// ═══════════════════════════════════════════════════════════════

function limpiar_pantalla() {
    // Simular limpieza con líneas en blanco
    echo str_repeat("\n", 50);
}

function mostrar_banner() {
    echo str_repeat("═", 60) . "\n";
    echo "   GESTOR DE DESCUENTOS DCIEN\n";
    echo str_repeat("═", 60) . "\n\n";
}

function listar_usuarios($usuarios) {
    echo "\n📋 USUARIOS REGISTRADOS:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-4s %-20s %-30s %-20s\n", '#', 'Username', 'Email', 'Instagram');
    echo str_repeat("-", 80) . "\n";
    
    foreach ($usuarios as $idx => $user) {
        $instagram = $user['instagram_username'] ?: '-';
        printf("%-4d %-20s %-30s @%-19s\n", 
            $idx + 1, 
            substr($user['username'], 0, 20), 
            substr($user['email'], 0, 30), 
            substr($instagram, 0, 19)
        );
    }
    
    echo str_repeat("-", 80) . "\n";
    echo "Total: " . count($usuarios) . " usuarios\n";
}

function listar_descuentos($descuentos) {
    echo "\n🎁 DESCUENTOS DISPONIBLES:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-4s %-15s %-35s %-10s %-10s\n", '#', 'Código', 'Descripción', 'Tipo', 'Valor');
    echo str_repeat("-", 80) . "\n";
    
    foreach ($descuentos as $idx => $desc) {
        $valor = $desc['type'] === 'percent' ? $desc['value'] . '%' : '€' . $desc['value'];
        printf("%-4d %-15s %-35s %-10s %-10s\n",
            $idx + 1,
            substr($desc['code'], 0, 15),
            substr($desc['description'], 0, 35),
            $desc['type'],
            $valor
        );
    }
    
    echo str_repeat("-", 80) . "\n";
    echo "Total: " . count($descuentos) . " descuentos\n";
}

function mostrar_descuentos_usuario($pdo, $user) {
    $descuentos = obtener_descuentos_usuario($pdo, $user['id']);
    
    echo "\n📦 DESCUENTOS DE @{$user['username']}:\n";
    echo str_repeat("-", 80) . "\n";
    
    if (empty($descuentos)) {
        echo "   (Sin descuentos asignados)\n";
    } else {
        printf("%-4s %-15s %-30s %-15s\n", '#', 'Código', 'Descripción', 'Estado');
        echo str_repeat("-", 80) . "\n";
        
        foreach ($descuentos as $idx => $desc) {
            $estado = $desc['used_at'] ? "✓ Usado (#{$desc['order_id']})" : "○ Disponible";
            printf("%-4d %-15s %-30s %-15s\n",
                $idx + 1,
                substr($desc['code'], 0, 15),
                substr($desc['description'], 0, 30),
                $estado
            );
        }
    }
    
    echo str_repeat("-", 80) . "\n";
}

function menu_principal() {
    echo "\n🔹 MENÚ PRINCIPAL:\n";
    echo "  1. Asignar descuentos masivamente\n";
    echo "  2. Asignar descuento a un usuario\n";
    echo "  3. Ver descuentos de un usuario\n";
    echo "  4. Revocar descuento de un usuario\n";
    echo "  5. Listar todos los usuarios\n";
    echo "  6. Listar todos los descuentos\n";
    echo "  0. Salir\n\n";
    
    return trim(readline("Opción: "));
}

function seleccionar_multiples($items, $tipo) {
    echo "\n🎯 Seleccionar $tipo (separar con comas, ej: 1,3,5 o 'all' para todos):\n";
    $seleccion = strtolower(trim(readline("> ")));
    
    if ($seleccion === 'all') {
        return range(0, count($items) - 1);
    }
    
    $indices = array_map('trim', explode(',', $seleccion));
    $indices = array_filter($indices, function($idx) use ($items) {
        $idx = intval($idx) - 1;
        return $idx >= 0 && $idx < count($items);
    });
    
    return array_map(function($idx) { return intval($idx) - 1; }, $indices);
}

// ═══════════════════════════════════════════════════════════════
// ACCIONES
// ═══════════════════════════════════════════════════════════════

function asignar_masivo($pdo, $usuarios, $descuentos) {
    limpiar_pantalla();
    mostrar_banner();
    
    listar_usuarios($usuarios);
    $indices_usuarios = seleccionar_multiples($usuarios, "usuarios");
    
    if (empty($indices_usuarios)) {
        echo "❌ No se seleccionaron usuarios válidos\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    listar_descuentos($descuentos);
    $indices_descuentos = seleccionar_multiples($descuentos, "descuentos");
    
    if (empty($indices_descuentos)) {
        echo "❌ No se seleccionaron descuentos válidos\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $usuarios_sel = array_map(function($i) use ($usuarios) { return $usuarios[$i]; }, $indices_usuarios);
    $descuentos_sel = array_map(function($i) use ($descuentos) { return $descuentos[$i]; }, $indices_descuentos);
    
    echo "\n⚠️  CONFIRMACIÓN:\n";
    echo "   Usuarios: " . count($usuarios_sel) . "\n";
    echo "   Descuentos: " . count($descuentos_sel) . "\n";
    echo "   Total operaciones: " . (count($usuarios_sel) * count($descuentos_sel)) . "\n";
    
    $confirmar = strtolower(trim(readline("\n¿Continuar? (s/n): ")));
    
    if ($confirmar !== 's') {
        echo "❌ Operación cancelada\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    echo "\n🔄 Asignando descuentos...\n";
    $exitosos = 0;
    $errores = 0;
    
    foreach ($usuarios_sel as $user) {
        foreach ($descuentos_sel as $desc) {
            list($success, $msg) = asignar_descuento($pdo, $user['id'], $desc['id']);
            if ($success) {
                $exitosos++;
                echo "  ✓ {$user['username']} → {$desc['code']}\n";
            } else {
                $errores++;
                echo "  ✗ {$user['username']} → {$desc['code']} ($msg)\n";
            }
        }
    }
    
    echo "\n✅ Completado: $exitosos exitosos, $errores errores\n";
    readline("\nPresiona Enter para continuar...");
}

function asignar_individual($pdo, $usuarios, $descuentos) {
    limpiar_pantalla();
    mostrar_banner();
    
    listar_usuarios($usuarios);
    $idx_user = intval(readline("\nSelecciona usuario (#): ")) - 1;
    
    if ($idx_user < 0 || $idx_user >= count($usuarios)) {
        echo "❌ Usuario inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $user = $usuarios[$idx_user];
    mostrar_descuentos_usuario($pdo, $user);
    
    listar_descuentos($descuentos);
    $idx_desc = intval(readline("\nSelecciona descuento (#): ")) - 1;
    
    if ($idx_desc < 0 || $idx_desc >= count($descuentos)) {
        echo "❌ Descuento inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $desc = $descuentos[$idx_desc];
    list($success, $msg) = asignar_descuento($pdo, $user['id'], $desc['id']);
    
    if ($success) {
        echo "\n✅ $msg\n";
        echo "   Usuario: @{$user['username']}\n";
        echo "   Descuento: {$desc['code']} - {$desc['description']}\n";
    } else {
        echo "\n❌ $msg\n";
    }
    
    readline("\nPresiona Enter para continuar...");
}

function ver_descuentos_usuario_menu($pdo, $usuarios) {
    limpiar_pantalla();
    mostrar_banner();
    
    listar_usuarios($usuarios);
    $idx_user = intval(readline("\nSelecciona usuario (#): ")) - 1;
    
    if ($idx_user < 0 || $idx_user >= count($usuarios)) {
        echo "❌ Usuario inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    mostrar_descuentos_usuario($pdo, $usuarios[$idx_user]);
    readline("\nPresiona Enter para continuar...");
}

function revocar_descuento_menu($pdo, $usuarios) {
    limpiar_pantalla();
    mostrar_banner();
    
    listar_usuarios($usuarios);
    $idx_user = intval(readline("\nSelecciona usuario (#): ")) - 1;
    
    if ($idx_user < 0 || $idx_user >= count($usuarios)) {
        echo "❌ Usuario inválido\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $user = $usuarios[$idx_user];
    $descuentos_user = obtener_descuentos_usuario($pdo, $user['id']);
    
    if (empty($descuentos_user)) {
        echo "\n❌ @{$user['username']} no tiene descuentos asignados\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    echo "\n📦 DESCUENTOS DE @{$user['username']}:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-4s %-15s %-30s %-15s\n", '#', 'Código', 'Descripción', 'Estado');
    echo str_repeat("-", 80) . "\n";
    
    foreach ($descuentos_user as $idx => $desc) {
        $estado = $desc['used_at'] ? "✓ Usado (no revocable)" : "○ Disponible";
        printf("%-4d %-15s %-30s %-15s\n",
            $idx + 1,
            $desc['code'],
            substr($desc['description'], 0, 30),
            $estado
        );
    }
    
    echo str_repeat("-", 80) . "\n";
    
    $idx_desc = intval(readline("\nSelecciona descuento a revocar (#): ")) - 1;
    
    if ($idx_desc < 0 || $idx_desc >= count($descuentos_user)) {
        echo "\n❌ Selección inválida\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $desc_a_revocar = $descuentos_user[$idx_desc];
    
    if ($desc_a_revocar['used_at']) {
        echo "\n❌ No se puede revocar un descuento ya usado\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    $confirmar = strtolower(trim(readline("\n⚠️  ¿Revocar descuento '{$desc_a_revocar['code']}' de @{$user['username']}? (s/n): ")));
    
    if ($confirmar !== 's') {
        echo "❌ Operación cancelada\n";
        readline("\nPresiona Enter para continuar...");
        return;
    }
    
    list($success, $msg) = revocar_descuento($pdo, $desc_a_revocar['user_discount_id']);
    
    echo $success ? "\n✅ $msg\n" : "\n❌ $msg\n";
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
    
    $usuarios = obtener_usuarios($pdo);
    $descuentos = obtener_descuentos($pdo);
    
    if (empty($usuarios)) {
        echo "❌ No hay usuarios en la base de datos\n";
        exit(1);
    }
    
    if (empty($descuentos)) {
        echo "❌ No hay descuentos activos en la base de datos\n";
        exit(1);
    }
    
    while (true) {
        limpiar_pantalla();
        mostrar_banner();
        echo "📊 " . count($usuarios) . " usuarios | 🎁 " . count($descuentos) . " descuentos activos\n";
        
        $opcion = menu_principal();
        
        switch ($opcion) {
            case '1':
                asignar_masivo($pdo, $usuarios, $descuentos);
                break;
            case '2':
                asignar_individual($pdo, $usuarios, $descuentos);
                break;
            case '3':
                ver_descuentos_usuario_menu($pdo, $usuarios);
                break;
            case '4':
                revocar_descuento_menu($pdo, $usuarios);
                break;
            case '5':
                limpiar_pantalla();
                mostrar_banner();
                listar_usuarios($usuarios);
                readline("\nPresiona Enter para continuar...");
                break;
            case '6':
                limpiar_pantalla();
                mostrar_banner();
                listar_descuentos($descuentos);
                readline("\nPresiona Enter para continuar...");
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