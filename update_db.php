<?php
require_once 'config.php';
try {
    // Modify products type to support 'rent'
    $pdo->exec("ALTER TABLE products MODIFY COLUMN type ENUM('sell', 'buy', 'rent') DEFAULT 'sell'");
    echo "Database modified successfully: ENUM('sell', 'buy', 'rent') applied to type column.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
