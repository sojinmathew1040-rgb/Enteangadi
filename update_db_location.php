<?php
require_once 'config.php';

try {
    // Add location and contact columns to products table
    $pdo->exec("ALTER TABLE products 
        ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS location_name VARCHAR(255) DEFAULT NULL, 
        ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) DEFAULT NULL, 
        ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) DEFAULT NULL");
    
    // Create locations table for manual selection (optional but good for a list of cities)
    $pdo->exec("CREATE TABLE IF NOT EXISTS locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8)
    )");

    // Insert some default cities if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM locations");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO locations (name, latitude, longitude) VALUES 
            ('Kochi', 9.9312, 76.2673),
            ('Trivandrum', 8.5241, 76.9366),
            ('Calicut', 11.2588, 75.7804),
            ('Thrissur', 10.5276, 76.2144),
            ('Kottayam', 9.5916, 76.5221)");
    }

    echo "Database updated successfully!";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
