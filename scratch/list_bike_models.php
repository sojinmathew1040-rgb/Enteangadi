<?php
require_once __DIR__ . '/../config.php';
$stmt = $pdo->query("SELECT m.id, m.name as model_name, b.name as brand_name 
                     FROM product_models m 
                     JOIN product_brands b ON m.brand_id = b.id 
                     WHERE b.category_id = 27 
                     ORDER BY b.name, m.name");
$models = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total Bike Models in DB: " . count($models) . "\n";
foreach ($models as $m) {
    echo "{$m['brand_name']} -> {$m['model_name']}\n";
}
