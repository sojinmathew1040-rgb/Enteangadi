<?php
require_once 'config.php';

$l1_name = "Agriculture & Farm Products";

// Check if L1 exists
$stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND parent_id IS NULL");
$stmt->execute([$l1_name]);
$l1 = $stmt->fetch();

if (!$l1) {
    $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, NULL)");
    $stmt->execute([$l1_name]);
    $l1_id = $pdo->lastInsertId();
    echo "Created L1: $l1_name (ID: $l1_id)\n";
} else {
    $l1_id = $l1['id'];
    echo "L1 exists: $l1_name (ID: $l1_id)\n";
}

$l2s = [
    "Farming Equipment & Machinery",
    "Seeds & Plants",
    "Livestock",
    "Organic & Fresh Produce",
    "Fertilizers & Pesticides",
    "Feed & Nutrition",
    "Tools & Implements"
];

foreach ($l2s as $l2_name) {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND parent_id = ?");
    $stmt->execute([$l2_name, $l1_id]);
    $l2 = $stmt->fetch();
    if (!$l2) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
        $stmt->execute([$l2_name, $l1_id]);
        echo "Created L2: $l2_name under L1 $l1_id\n";
    } else {
        echo "L2 exists: $l2_name under L1 $l1_id\n";
    }
}
echo "Done.";
?>
