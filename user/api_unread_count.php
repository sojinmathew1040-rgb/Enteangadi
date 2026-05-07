<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

$my_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$my_id]);
    $count = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'count' => (int) $count]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'count' => 0]);
}
?>