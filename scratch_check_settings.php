<?php
require_once 'config.php';
try {
    $stmt = $pdo->query("SELECT * FROM app_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "APP SETTINGS:\n";
    print_r($settings);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
