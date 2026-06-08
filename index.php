<?php
require_once 'config.php';

// Route dispatcher based on login status
$query_string = $_SERVER['QUERY_STRING'] ?? '';
$redirect_url = isset($_SESSION['user_id']) ? 'user/index.php' : 'guest/index.php';
if (!empty($query_string)) {
    $redirect_url .= '?' . $query_string;
}
header("Location: " . $redirect_url);
exit;
?>