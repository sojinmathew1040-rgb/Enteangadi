<?php
require_once 'config.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Insert default values if not exists
    $pdo->exec("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES 
        ('app_logo', ''),
        ('app_tagline', 'Your Local Marketplace')");

    echo "Table app_settings created/verified successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>