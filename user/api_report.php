<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? null;
    $reported_user_id = $_POST['reported_user_id'] ?? null;
    $reason = $_POST['reason'] ?? '';

    if (($product_id || $reported_user_id) && $reason) {
        try {
            $stmt = $pdo->prepare("INSERT INTO reports (product_id, reported_user_id, reported_by_user_id, reason) VALUES (?, ?, ?, ?)");
            $stmt->execute([$product_id, $reported_user_id, $_SESSION['user_id'], $reason]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
    }
}
?>