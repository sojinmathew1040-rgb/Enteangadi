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
            // Check if user is active (has activity in the last 30 days)
            $last_act = $user['last_activity'] ? strtotime($user['last_activity']) : strtotime($user['created_at']);
            if ((time() - $last_act) <= (30 * 24 * 60 * 60)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['session_token'] = $user['session_token'];

                // Update last activity
                $upd = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
                $upd->execute([$user['id']]);

                // Renew cookies for 30 days
                enteangadi_set_cookie('enteangadi_remember_user', $user['id'], time() + 30 * 24 * 60 * 60);
                enteangadi_set_cookie('enteangadi_remember_token', $user['session_token'], time() + 30 * 24 * 60 * 60);

                echo json_encode(['success' => true]);
                exit;
            } else {
                // Invalidate session token on database due to 30 days inactivity
                $upd = $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
                $upd->execute([$user['id']]);
            }
        }
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid credentials or token expired']);
