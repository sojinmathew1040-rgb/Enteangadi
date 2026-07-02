<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$my_id = $_SESSION['user_id'];
session_write_close(); // Release session file lock early to prevent blocking concurrent requests

// Security check: Check if product is deleted/sold, self-chat, or user is blocked
if (in_array($action, ['send', 'send_audio', 'send_image'])) {
    $product_id = $_POST['product_id'] ?? 0;
    $receiver_id = $_POST['receiver_id'] ?? 0;

    if ($receiver_id == $my_id) {
        echo json_encode(['success' => false, 'error' => 'You cannot chat with yourself.']);
        exit;
    }

    if ($product_id) {
        $prod_stmt = $pdo->prepare("SELECT status FROM products WHERE id = ?");
        $prod_stmt->execute([$product_id]);
        $prod_status = $prod_stmt->fetchColumn();
        if ($prod_status === 'deleted' || $prod_status === 'sold') {
            echo json_encode(['success' => false, 'error' => 'This product listing is no longer active.']);
            exit;
        }
    }

    if ($receiver_id) {
        $check_block = $pdo->prepare("SELECT COUNT(*) FROM blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
        $check_block->execute([$my_id, $receiver_id, $receiver_id, $my_id]);
        if ($check_block->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Messaging is blocked between these users.']);
            exit;
        }
    }
}

function logChatSession($pdo, $my_id, $receiver_id, $product_id) {
    try {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND product_id = ?");
        $check_stmt->execute([$my_id, $receiver_id, $product_id]);
        $msg_count = $check_stmt->fetchColumn();
        if ($msg_count == 1) {
            $stmt_an = $pdo->prepare("INSERT INTO analytics_clicks (product_id, click_type, click_date, click_count) 
                                     VALUES (?, 'chat', CURRENT_DATE, 1) 
                                     ON DUPLICATE KEY UPDATE click_count = click_count + 1");
            $stmt_an->execute([$product_id]);
        }
    } catch (Exception $e) {}
}

if ($action === 'send') {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $product_id = $_POST['product_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');

    if ($receiver_id && $product_id && !empty($message)) {
        require_once '../includes/helpers.php';
        if (isTextInappropriate($message)) {
            echo json_encode(['success' => false, 'error' => 'Inappropriate content detected in message text.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, message_text) VALUES (?, ?, ?, ?)");
            $stmt->execute([$my_id, $receiver_id, $product_id, $message]);
            logChatSession($pdo, $my_id, $receiver_id, $product_id);
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
                @chmod($target_file, 0644);
                $db_path = '[AUDIO]:uploads/voice_chats/' . $file_name;

                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, message_text) VALUES (?, ?, ?, ?)");
                $stmt->execute([$my_id, $receiver_id, $product_id, $db_path]);
                logChatSession($pdo, $my_id, $receiver_id, $product_id);
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
} elseif ($action === 'send_image') {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $product_id = $_POST['product_id'] ?? 0;

    if ($receiver_id && $product_id && isset($_FILES['image_data'])) {
        try {
            require_once '../includes/helpers.php';
            if (isImageNSFW($_FILES['image_data']['tmp_name'])) {
                echo json_encode(['success' => false, 'error' => 'Inappropriate/adult content detected in the image.']);
                exit;
            }

            $upload_dir = '../uploads/chat_images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $ext = pathinfo($_FILES['image_data']['name'], PATHINFO_EXTENSION);
            if (empty($ext)) $ext = 'jpg';
            $file_name = 'img_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target_file = $upload_dir . $file_name;

            if (compressAndResizeImage($_FILES['image_data']['tmp_name'], $target_file, 800, 75) || move_uploaded_file($_FILES['image_data']['tmp_name'], $target_file)) {
                @chmod($target_file, 0644);
                $db_path = '[IMAGE]:uploads/chat_images/' . $file_name;

                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, message_text) VALUES (?, ?, ?, ?)");
                $stmt->execute([$my_id, $receiver_id, $product_id, $db_path]);
                logChatSession($pdo, $my_id, $receiver_id, $product_id);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save image file']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid image parameters']);
    }
} elseif ($action === 'fetch') {
    $other_id = $_POST['other_id'] ?? $_GET['other_id'] ?? 0;
    $product_id = $_POST['product_id'] ?? $_GET['product_id'] ?? 0;

    if ($other_id && $product_id) {
        try {
            // Mark messages as read and delivered
            $update_stmt = $pdo->prepare("UPDATE messages SET is_read = 1, is_delivered = 1 WHERE receiver_id = ? AND sender_id = ? AND product_id = ? AND is_read = 0 AND deleted_by_receiver = 0");
            $update_stmt->execute([$my_id, $other_id, $product_id]);

            // Fetch messages (only those not deleted by the current user)
            $stmt = $pdo->prepare("
                SELECT m.*, u_sender.username as sender_name 
                FROM messages m
                JOIN users u_sender ON m.sender_id = u_sender.id
                WHERE m.product_id = ? 
                AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                AND ((m.sender_id = ? AND m.deleted_by_sender = 0) OR (m.receiver_id = ? AND m.deleted_by_receiver = 0))
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$product_id, $my_id, $other_id, $other_id, $my_id, $my_id, $my_id]);
            $messages = $stmt->fetchAll();

            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
} elseif ($action === 'delete_message') {
    $message_id = $_POST['message_id'] ?? $_GET['message_id'] ?? 0;
    $delete_type = $_POST['delete_type'] ?? $_GET['delete_type'] ?? 'for_me';

    if ($message_id) {
        try {
            $stmt_msg = $pdo->prepare("SELECT sender_id, receiver_id FROM messages WHERE id = ?");
            $stmt_msg->execute([$message_id]);
            $msg = $stmt_msg->fetch();

            if ($msg) {
                if ($msg['sender_id'] != $my_id && $msg['receiver_id'] != $my_id) {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    exit;
                }

                if ($delete_type === 'for_everyone') {
                    if ($msg['sender_id'] == $my_id) {
                        $stmt_del = $pdo->prepare("DELETE FROM messages WHERE id = ?");
                        $stmt_del->execute([$message_id]);
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Only the sender can delete this message for everyone.']);
                    }
                } else {
                    if ($msg['sender_id'] == $my_id) {
                        $stmt_upd = $pdo->prepare("UPDATE messages SET deleted_by_sender = 1 WHERE id = ?");
                    } else {
                        $stmt_upd = $pdo->prepare("UPDATE messages SET deleted_by_receiver = 1 WHERE id = ?");
                    }
                    $stmt_upd->execute([$message_id]);
                    echo json_encode(['success' => true]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Message not found']);
            }
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