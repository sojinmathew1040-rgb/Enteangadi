<?php
require_once '../config.php';
header('Content-Type: application/json');

// Auto-login a default user for local demo testing in API calls if not logged in
if (!isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->query("SELECT id, username FROM users ORDER BY id ASC LIMIT 2");
        $users = $stmt->fetchAll();
        if (!empty($users)) {
            // Log in as the first user by default
            $_SESSION['user_id'] = $users[0]['id'];
            $_SESSION['username'] = $users[0]['username'];
        }
    } catch (Exception $e) {}
}

echo json_encode([
    'success' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null
]);
?>
