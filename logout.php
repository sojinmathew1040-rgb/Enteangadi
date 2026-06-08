<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $upd = $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
    $upd->execute([$_SESSION['user_id']]);
}

// Clear persistent cookies
enteangadi_set_cookie('enteangadi_remember_user', '', time() - 3600);
enteangadi_set_cookie('enteangadi_remember_token', '', time() - 3600);

session_destroy();
header("Location: index.php?logged_out=1");
exit;
?>