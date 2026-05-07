<?php
require_once '../config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN session_token VARCHAR(255) DEFAULT NULL");
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>