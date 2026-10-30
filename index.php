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
if (isset($_SESSION['user_id'])) {
    $target = 'user/index.php' . ($qs !== '' ? '?' . $qs : '');
} else {
    $target = 'guest/index.php?' . ($qs !== '' ? $qs : '_g=1');
}
session_write_close();
if (!headers_sent()) {
    header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
    header("X-Accel-Expires: 0");
    header("X-LiteSpeed-Cache-Control: no-cache");
    header("Vary: Cookie");
    header("Set-Cookie: wordpress_logged_in_1=1; Path=/; Max-Age=31536000; SameSite=Lax", false);
}
header("Location: " . $target);
exit;
