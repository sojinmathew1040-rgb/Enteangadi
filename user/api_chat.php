<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$my_id = $_SESSION['user_id'];

if ($action === 'send') {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $product_id = $_POST['product_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');

    if ($receiver_id && $product_id && !empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, message_text) VALUES (?, ?, ?, ?)");
            $stmt->execute([$my_id, $receiver_id, $product_id, $message]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
} elseif ($action === 'send_audio') {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $product_id = $_POST['product_id'] ?? 0;

    if ($receiver_id && $product_id && isset($_FILES['audio_data'])) {
        try {
            $upload_dir = '../uploads/voice_chats/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_name = 'voice_' . time() . '_' . rand(1000, 9999) . '.wav';
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['audio_data']['tmp_name'], $target_file)) {
                $db_path = '[AUDIO]:uploads/voice_chats/' . $file_name;

                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, message_text) VALUES (?, ?, ?, ?)");
                $stmt->execute([$my_id, $receiver_id, $product_id, $db_path]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save audio file']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid audio parameters']);
    }
} elseif ($action === 'fetch') {
    $other_id = $_POST['other_id'] ?? $_GET['other_id'] ?? 0;
    $product_id = $_POST['product_id'] ?? $_GET['product_id'] ?? 0;

    if ($other_id && $product_id) {
        try {
            // Mark messages as read
            $update_stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND product_id = ? AND is_read = 0");
            $update_stmt->execute([$my_id, $other_id, $product_id]);

            // Fetch messages
            $stmt = $pdo->prepare("
                SELECT m.*, u_sender.username as sender_name 
                FROM messages m
                JOIN users u_sender ON m.sender_id = u_sender.id
                WHERE m.product_id = ? 
                AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$product_id, $my_id, $other_id, $other_id, $my_id]);
            $messages = $stmt->fetchAll();

            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
} elseif ($action === 'delete_chat') {
    $other_id = $_POST['other_id'] ?? 0;
    $product_id = $_POST['product_id'] ?? 0;

    if ($other_id && $product_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM messages WHERE product_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
            $stmt->execute([$product_id, $my_id, $other_id, $other_id, $my_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>