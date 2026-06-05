<?php
require_once '../config.php';

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch();

    if (!$user) {
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password, is_admin, role, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@enteangadi.com', '9876543210', $hashed, 1, 'admin', '*']);
        echo "SUCCESS: Admin user 'admin' with password 'admin123' created successfully.\n";
    } else {
        // Ensure role is admin and reset/update password to admin123
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin', is_admin = 1, permissions = '*' WHERE username = ?");
        $stmt->execute([$hashed, 'admin']);
        echo "SUCCESS: Admin user verified and password reset to 'admin123'.\n";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
