<?php
require_once __DIR__ . '/../config.php';
$stmt = $pdo->query("SELECT name, body_type FROM product_models WHERE name IN ('Fronx', 'City', 'Swift', 'Fortuner', 'Creta', 'Virtus', 'Classic 350', 'Activa')");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['name']} => {$r['body_type']}\n";
}
