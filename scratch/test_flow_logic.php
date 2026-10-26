<?php
require_once __DIR__ . '/../config.php';

echo "=== Testing Filter Hierarchy with Body Type ===\n";

// Test 1: Given Body Type = 'Sedan', which brands have models?
$body_type = 'Sedan';
$stmt = $pdo->prepare("
    SELECT b.name as brand_name, COUNT(m.id) as model_count 
    FROM product_brands b 
    JOIN product_models m ON m.brand_id = b.id 
    WHERE b.category_id = 25 AND m.body_type = ? 
    GROUP BY b.id, b.name 
    ORDER BY model_count DESC, b.name
");
$stmt->execute([$body_type]);
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Brands with Sedans:\n";
foreach ($brands as $b) {
    echo " - {$b['brand_name']} ({$b['model_count']} sedans)\n";
}

// Test 2: Given Brand = 'Honda' and Body Type = 'Sedan', which models match?
$stmt = $pdo->prepare("
    SELECT m.name as model_name, m.body_type 
    FROM product_models m 
    JOIN product_brands b ON m.brand_id = b.id 
    WHERE b.category_id = 25 AND b.name = 'Honda' AND m.body_type = ?
");
$stmt->execute([$body_type]);
$models = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nHonda Sedans:\n";
foreach ($models as $m) {
    echo " - {$m['model_name']}\n";
}

// Test 3: Given Model = 'Fronx', what is its body type?
$stmt = $pdo->prepare("SELECT body_type FROM product_models WHERE name = 'Fronx'");
$stmt->execute();
$fronx_type = $stmt->fetchColumn();
echo "\nFronx Body Type: $fronx_type\n";

// Other body types that should be disabled for Fronx:
$all_body_types = ['Hatchback', 'Sedan', 'SUV', 'Compact SUV', 'MUV', 'Luxury'];
echo "Disabled body types for Fronx: " . implode(', ', array_diff($all_body_types, [$fronx_type])) . "\n";
