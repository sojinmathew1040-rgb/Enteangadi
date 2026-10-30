<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header("Location: ../guest/index.php" . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

$is_guest_feed = false;
require_once '../includes/home_feed.php';
