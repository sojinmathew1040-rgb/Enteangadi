<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT id, title, price, type FROM products ORDER BY id DESC LIMIT 1");
$product = $stmt->fetch();
echo "<h3>Last Product Debug</h3>";
echo "Title: " . $product['title'] . "<br>";
echo "Type: " . $product['type'] . "<br>";
echo "DB Price: " . $product['price'] . "<br>";
echo "Formatted: " . number_format($product['price'], 2) . "<br>";
?>