<?php
require_once '../config.php';

// Find any user in the database who is an admin
$stmt = $pdo->query("SELECT id, username, permissions FROM users WHERE is_admin = 1 LIMIT 1");
$admin = $stmt->fetch();

if ($admin) {
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = 'admin';
    $_SESSION['admin_permissions'] = $admin['permissions'];
    $_SESSION['is_admin_session'] = true;
    header("Location: settings.php");
    exit;
} else {
    // If no admin user exists, create one
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (username, password, is_admin, permissions, role) VALUES (?, ?, 1, '*', 'admin')")->execute([$username, $password]);

    // Log in as new admin
    $admin_id = $pdo->lastInsertId();
    $_SESSION['admin_id'] = $admin_id;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_role'] = 'admin';
    $_SESSION['admin_permissions'] = '*';
    $_SESSION['is_admin_session'] = true;
    header("Location: settings.php");
    exit;
}
?>