<?php
require __DIR__ . '/../admin-descargas/modules/config.php';
$pdo = get_db_connection();

$updates = [
    ['slug' => 'serie-0', 'units' => [21], 'notes' => 'Vendido (Entrega en mano)'],
    ['slug' => 'serie-01', 'units' => [1, 7, 12, 13, 14, 28], 'notes' => 'Marketing VIP: Influencer'],
    ['slug' => 'serie-06', 'units' => [62], 'notes' => 'Marketing VIP: Influencer'],
    ['slug' => 'serie-09', 'units' => [63, 96], 'notes' => 'Marketing VIP: Influencer'],
    ['slug' => 'serie-05', 'units' => [24], 'notes' => 'Marketing VIP: Influencer'],
    ['slug' => 'serie-07', 'units' => [56], 'notes' => 'Marketing VIP: Influencer'],
    ['slug' => 'serie-08', 'units' => [45], 'notes' => 'Marketing VIP: Influencer'],
    ['slug' => 'serie-12', 'units' => [88], 'notes' => 'Marketing VIP: Influencer'],
];

try {
    $pdo->beginTransaction();
    foreach ($updates as $up) {
        $slug = $up['slug'];
        $notes = $up['notes'];
        foreach ($up['units'] as $num) {
            $pdo->prepare("UPDATE series_units SET status = 'sold', sold_at = NOW(), notes = ?, reserved_by = NULL WHERE series_slug = ? AND unit_number = ?")
                ->execute([$notes, $slug, $num]);
            
            // Fix available count
            $pdo->prepare("UPDATE series SET available_units = (SELECT COUNT(*) FROM series_units WHERE series_slug = ? AND status = 'available'), sold_units = (SELECT COUNT(*) FROM series_units WHERE series_slug = ? AND status = 'sold') WHERE slug = ?")
                ->execute([$slug, $slug, $slug]);
        }
    }
    $pdo->commit();
    echo "OK - Actualizado";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
