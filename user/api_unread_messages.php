<?php
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

$my_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.sender_id, u.username as sender_name, m.message_text, m.created_at 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.receiver_id = ? AND m.is_read = 0 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$my_id]);
    $unread_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $unread_messages]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'messages' => []]);
}
?>
