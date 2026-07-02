<?php
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $my_id = $_SESSION['user_id'];
} else if (isset($_REQUEST['user_id']) && !empty($_REQUEST['user_id'])) {
    $my_id = $_REQUEST['user_id'];
} else {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}
session_write_close(); // Release session file lock early to prevent blocking concurrent requests

try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.sender_id, m.product_id, u.username as sender_name, m.message_text, m.created_at 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.receiver_id = ? AND m.is_read = 0 AND m.deleted_by_receiver = 0
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$my_id]);
    $unread_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($unread_messages)) {
        $unread_ids = array_column($unread_messages, 'id');
        $placeholders = implode(',', array_fill(0, count($unread_ids), '?'));
        $upd_stmt = $pdo->prepare("UPDATE messages SET is_delivered = 1 WHERE id IN ($placeholders) AND is_delivered = 0");
        $upd_stmt->execute($unread_ids);
    }

    echo json_encode(['success' => true, 'messages' => $unread_messages]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'messages' => []]);
}
?>
