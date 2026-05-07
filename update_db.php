<?php
require_once 'config.php';
try {
    $pdo->exec('ALTER TABLE products ADD COLUMN whatsapp_number VARCHAR(20) DEFAULT NULL AFTER price');
    echo "Column added successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
