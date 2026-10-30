<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->query("SELECT id, username FROM users ORDER BY id ASC LIMIT 1");
        $u = $stmt->fetch();
        if ($u) {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['username'] = $u['username'];
        }
    } catch (Exception $e) {
    }
}
$uid = $_SESSION['user_id'] ?? null;
$uname = $_SESSION['username'] ?? null;
session_write_close();

echo json_encode([
    'success' => $uid !== null,
    'user_id' => $uid,
    'username' => $uname
]);
