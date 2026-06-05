<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $session_token = $_POST['session_token'] ?? '';

    if (!empty($user_id) && !empty($session_token)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND session_token = ?");
        $stmt->execute([$user_id, $session_token]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['session_token'] = $user['session_token'];

            echo json_encode(['success' => true]);
            exit;
        }
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid credentials or token expired']);
