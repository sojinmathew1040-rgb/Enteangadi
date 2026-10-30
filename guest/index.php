<?php
require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header("Location: ../user/index.php" . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

$is_guest_feed = true;
require_once '../includes/home_feed.php';
