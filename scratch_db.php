<?php
require_once 'config.php';

header('Content-Type: text/plain');

echo "=== STAFF PERMISSIONS LIST ===\n";
try {
    $stmt = $pdo->query("SELECT * FROM staff_permissions_list");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== ADMIN USERS ===\n";
try {
    $stmt = $pdo->query("SELECT id, username, email, is_admin, role, permissions FROM users WHERE is_admin = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>