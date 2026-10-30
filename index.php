<?php
// Fast-path root dispatcher: avoid DB round-trip unless auto-login cookies/headers need verification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$hasAuthFallback = !isset($_SESSION['user_id']) && (
    isset($_COOKIE['enteangadi_remember_user'], $_COOKIE['enteangadi_remember_token']) ||
    !empty($_SERVER['HTTP_X_USER_ID']) ||
    !empty($_SERVER['REDIRECT_HTTP_X_USER_ID'])
);
if ($hasAuthFallback) {
    require_once __DIR__ . '/config.php';
}
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = (isset($_SESSION['user_id']) ? 'user/index.php' : 'guest/index.php') . ($qs !== '' ? '?' . $qs : '');
session_write_close();
if (!headers_sent()) {
    header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
    header("X-Accel-Expires: 0");
    header("X-LiteSpeed-Cache-Control: no-cache");
    header("Vary: Cookie");
}
header("Location: " . $target);
exit;
