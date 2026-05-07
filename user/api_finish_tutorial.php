<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$section = $_GET['section'] ?? '';
$valid_sections = ['tut_home', 'tut_post', 'tut_profile', 'tut_inbox'];

if (!in_array($section, $valid_sections)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid section']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET $section = 1 WHERE id = ?");
    if ($stmt->execute([$_SESSION['user_id']])) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update database']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
