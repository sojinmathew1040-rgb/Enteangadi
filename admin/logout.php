<?php
session_start();
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin') {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_role']);
    unset($_SESSION['admin_username']);
}
header("Location: login.php");
exit;
?>