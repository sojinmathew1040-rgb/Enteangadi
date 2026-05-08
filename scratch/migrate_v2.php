<?php
require_once '../config.php';

try {
    // 1. Add email to users
    try {
        $pdo->query("SELECT email FROM users LIMIT 1");
        echo "Column 'email' already exists.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(155) UNIQUE AFTER phone_number");
        echo "Column 'email' added to 'users' table.\n";
    }

    // 2. Create password_resets table
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        method ENUM('email', 'whatsapp') NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Table 'password_resets' created or already exists.\n";

    // 3. Create otp_verifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS otp_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(15) NOT NULL,
        otp_code VARCHAR(10) NOT NULL,
        is_verified TINYINT(1) DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (phone_number),
        INDEX (otp_code)
    )");
    echo "Table 'otp_verifications' created or already exists.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>