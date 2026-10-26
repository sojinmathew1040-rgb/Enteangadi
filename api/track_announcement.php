<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $poster = trim($_POST['poster'] ?? $app_settings['announcement_poster'] ?? '');
    
    // Fallback: If not passed, fetch from DB
    if (empty($poster)) {
        $stmt_p = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'announcement_poster'");
        $poster = trim($stmt_p->fetchColumn() ?: '');
    }

    if (!empty($poster)) {
        $v_date = date('Y-m-d');
        $v_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (strpos($v_ip, ',') !== false) {
            $v_ip = trim(explode(',', $v_ip)[0]);
        }
        $v_uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);

        $stmt = $pdo->prepare("INSERT INTO announcement_views (poster_image, view_date, user_id, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$poster, $v_date, $v_uid, $v_ip]);

        echo json_encode(['success' => true, 'message' => 'Announcement view recorded']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'No active poster']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
