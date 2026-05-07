<?php
require_once '../config.php';
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN type ENUM('sell', 'buy') DEFAULT 'sell' AFTER category_id");
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>