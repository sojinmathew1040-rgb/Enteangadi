<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit a review.']);
    exit;
}

$reviewer_id = $_SESSION['user_id'];
$reviewee_id = isset($_POST['reviewee_id']) ? (int)$_POST['reviewee_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = trim($_POST['comment'] ?? '');

if (!$reviewee_id || !$rating) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters. Please select a rating.']);
    exit;
}

if ($reviewer_id === $reviewee_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot review yourself.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO user_ratings (reviewer_id, reviewee_id, rating, comment) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)
    ");
    $stmt->execute([$reviewer_id, $reviewee_id, $rating, $comment]);

    echo json_encode(['success' => true, 'message' => 'Review submitted successfully!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
