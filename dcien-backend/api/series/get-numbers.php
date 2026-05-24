<?php
/**
 * API: Obtener números disponibles de una serie
 * GET /api/series/get-numbers.php?slug=serie-0
 */

$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/helpers.php';

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

$series_slug = $_GET['slug'] ?? '';

if (empty($series_slug)) {
    jsonError('Slug de serie requerido', 400);
}

try {
    // Obtener todos los números con su estado
    $units = queryAll(
        "SELECT 
            unit_number,
            status,
            reserved_at,
            sold_at
         FROM series_units
         WHERE series_slug = :slug
         ORDER BY unit_number ASC",
        ['slug' => $series_slug]
    );
    
    if (empty($units)) {
        jsonError('Serie no encontrada', 404);
    }
    
    // Formatear respuesta
    $numbers = array_map(function($unit) {
        return [
            'number' => $unit['unit_number'],
            'status' => $unit['status'],
            'available' => $unit['status'] === 'available'
        ];
    }, $units);
    
    // Contar por estado
    $stats = [
        'total' => count($units),
        'available' => count(array_filter($units, fn($u) => $u['status'] === 'available')),
        'reserved' => count(array_filter($units, fn($u) => $u['status'] === 'reserved')),
        'sold' => count(array_filter($units, fn($u) => $u['status'] === 'sold'))
    ];
    
    jsonSuccess('Números obtenidos', [
        'numbers' => $numbers,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    logError('Error getting numbers', ['error' => $e->getMessage()]);
    jsonError('Error del servidor', 500);
}
