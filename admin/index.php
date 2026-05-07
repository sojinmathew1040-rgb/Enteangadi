<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch stats
$users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$products_count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$reports_count = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();

require_once 'includes/header.php';
?>

<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;">
    <div
        style="background: var(--white); padding: 24px; border-radius: var(--border-radius); box-shadow: var(--shadow-sm); text-align: center;">
        <h3 style="color: var(--text-muted); font-size: 16px;">Total Users</h3>
        <p style="font-size: 36px; font-weight: 700; color: var(--primary-green);"><?= $users_count ?></p>
    </div>
    <div
        style="background: var(--white); padding: 24px; border-radius: var(--border-radius); box-shadow: var(--shadow-sm); text-align: center;">
        <h3 style="color: var(--text-muted); font-size: 16px;">Total Ads Posted</h3>
        <p style="font-size: 36px; font-weight: 700; color: var(--primary-green);"><?= $products_count ?></p>
    </div>
    <div
        style="background: var(--white); padding: 24px; border-radius: var(--border-radius); box-shadow: var(--shadow-sm); text-align: center;">
        <h3 style="color: var(--text-muted); font-size: 16px;">Pending Reports</h3>
        <p style="font-size: 36px; font-weight: 700; color: var(--danger);"><?= $reports_count ?></p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>