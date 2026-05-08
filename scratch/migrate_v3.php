<?php
require_once 'config.php';

try {
    // 1. Add unique_id to products
    try {
        $pdo->query("SELECT unique_id FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN unique_id VARCHAR(20) UNIQUE AFTER id");

        // Update existing products with a unique ID
        $stmt = $pdo->query("SELECT id FROM products WHERE unique_id IS NULL");
        $products = $stmt->fetchAll();
        foreach ($products as $p) {
            $new_id = 'ENTAGD' . rand(1000, 9999);
            $pdo->prepare("UPDATE products SET unique_id = ? WHERE id = ?")->execute([$new_id, $p['id']]);
        }
        echo "Column 'unique_id' added and existing products updated.\n";
    }

    // 2. Update status ENUM to include 'pending'
    // Note: MySQL requires re-defining the ENUM
    $pdo->exec("ALTER TABLE products MODIFY COLUMN status ENUM('active', 'deleted', 'sold', 'expired', 'pending') DEFAULT 'active'");
    echo "Product status ENUM updated to include 'pending'.\n";

    // 3. Ensure ad_approval_mode exists in app_settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $stmt = $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute(['ad_approval_mode', 'auto']); // Default to auto
    echo "App setting 'ad_approval_mode' initialized.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>