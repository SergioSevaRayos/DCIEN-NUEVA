<?php
/**
 * Helpers para sistema de descuentos
 * Incluir en todos los archivos que manejen descuentos
 */

/**
 * Calcula el precio final aplicando un descuento
 * ESTA ES LA AUTORIDAD - El cálculo aquí es el que cuenta
 * 
 * @param float $basePrice Precio base del producto
 * @param array $discount Array con type, value del descuento
 * @return float Precio final con descuento aplicado
 */
function calculateDiscountedPrice($basePrice, $discount) {
    $basePrice = (float) $basePrice;
    
    if ($discount['type'] === 'percent') {
        $discountAmount = $basePrice * ((float)$discount['value'] / 100);
        $finalPrice = $basePrice - $discountAmount;
    } else { // fixed
        $finalPrice = $basePrice - (float)$discount['value'];
    }
    
    // Nunca precio negativo
    return max(0, round($finalPrice, 2));
}

/**
 * Calcula cuánto se ahorra con un descuento
 * 
 * @param float $basePrice Precio base
 * @param array $discount Datos del descuento
 * @return float Cantidad ahorrada
 */
function calculateSavings($basePrice, $discount) {
    $finalPrice = calculateDiscountedPrice($basePrice, $discount);
    return round($basePrice - $finalPrice, 2);
}

/**
 * Valida que un descuento esté disponible para un usuario
 * 
 * @param int $userId ID del usuario
 * @param int $discountId ID del descuento
 * @return array|null Datos del descuento si es válido, null si no
 */
function validateUserDiscount($userId, $discountId) {
    return queryOne(
        "SELECT 
            ud.id as user_discount_id,
            ud.user_id,
            ud.discount_id,
            ud.assigned_at,
            ud.used_at,
            d.code,
            d.description,
            d.type,
            d.value,
            d.is_active,
            d.max_uses,
            d.used_count,
            d.valid_from,
            d.valid_until
         FROM user_discounts ud
         INNER JOIN discounts d ON d.id = ud.discount_id
         WHERE ud.user_id = :user_id
           AND ud.discount_id = :discount_id
           AND ud.used_at IS NULL
           AND d.is_active = 1
           AND (d.valid_until IS NULL OR d.valid_until >= NOW())
           AND (d.max_uses IS NULL OR d.used_count < d.max_uses)
         LIMIT 1",
        [
            'user_id' => $userId,
            'discount_id' => $discountId
        ]
    );
}

/**
 * Marca un descuento como usado
 * Se ejecuta después de un pago exitoso
 * 
 * @param int $userDiscountId ID en tabla user_discounts
 * @param int $orderId ID de la orden creada
 * @return bool True si se marcó correctamente
 */
function markDiscountAsUsed($userDiscountId, $orderId) {
    try {
        // Marcar en user_discounts
        query(
            "UPDATE user_discounts 
             SET used_at = NOW(), order_id = :order_id 
             WHERE id = :id AND used_at IS NULL",
            [
                'id' => $userDiscountId,
                'order_id' => $orderId
            ]
        );
        
        // Incrementar contador en discounts
        query(
            "UPDATE discounts d
             INNER JOIN user_discounts ud ON ud.discount_id = d.id
             SET d.used_count = d.used_count + 1
             WHERE ud.id = :user_discount_id",
            ['user_discount_id' => $userDiscountId]
        );
        
        return true;
    } catch (Exception $e) {
        logError('Error marking discount as used', [
            'user_discount_id' => $userDiscountId,
            'order_id' => $orderId,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Genera el label de visualización de un descuento
 * 
 * @param array $discount Datos del descuento
 * @return string Label formateado (ej: "-15%" o "-€5.00")
 */
function getDiscountLabel($discount) {
    if ($discount['type'] === 'percent') {
        return '-' . (int)$discount['value'] . '%';
    } else {
        return '-€' . number_format($discount['value'], 2);
    }
}