<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$my_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$blocked_id = $_POST['blocked_id'] ?? 0;

if (!$blocked_id || !in_array($action, ['block', 'unblock'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

if ($blocked_id == $my_id) {
    echo json_encode(['success' => false, 'error' => 'You cannot block yourself']);
    exit;
}

try {
    if ($action === 'block') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->execute([$my_id, $blocked_id]);
        echo json_encode(['success' => true, 'is_blocked' => true]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$my_id, $blocked_id]);
        echo json_encode(['success' => true, 'is_blocked' => false]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
