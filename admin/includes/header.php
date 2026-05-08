<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base_url = '/Enteangadi';
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enteangadi - Admin Dashboard</title>
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: var(--background);
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 260px;
            background: var(--white);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            box-shadow: var(--shadow-sm);
            z-index: 100;
        }

        .admin-brand {
            padding: 24px;
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-green-dark);
            letter-spacing: -0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        .admin-nav {
            flex: 1;
            padding: 24px 0;
            overflow-y: auto;
        }

        .admin-nav a {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }

        .admin-nav a:hover {
            background: var(--background);
            color: var(--primary-green);
        }

        .admin-nav a.active {
            background: #e8f5e9;
            color: var(--primary-green-dark);
            border-left-color: var(--primary-green);
        }

        .admin-nav i {
            width: 24px;
            font-size: 18px;
            margin-right: 12px;
        }

        .admin-footer {
            padding: 24px;
            border-top: 1px solid var(--border-color);
        }

        .admin-main {
            flex: 1;
            margin-left: 260px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .admin-header {
            background: var(--white);
            padding: 16px 32px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .admin-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        .admin-content {
            padding: 32px;
            flex: 1;
            overflow-y: auto;
        }
    </style>
</head>

<body>

    <div class="admin-sidebar">
        <div class="admin-brand">Enteangadi Admin</div>
        <nav class="admin-nav">
            <a href="index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>"><i class="fa fa-home"></i>
                Dashboard</a>

            <a href="reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>"><i
                    class="fa fa-flag"></i> Reports</a>

            <?php if (has_permission('manage_categories')): ?>
                <a href="categories.php" class="<?= $current_page === 'categories.php' ? 'active' : '' ?>"><i
                        class="fa fa-list"></i> Categories</a>
            <?php endif; ?>

            <?php
            $has_any_setting = has_permission('manage_branding') || has_permission('manage_security') ||
                has_permission('manage_general') || has_permission('manage_contact') ||
                has_permission('manage_approval') || has_permission('manage_pending') ||
                has_permission('manage_listings') || has_permission('manage_users');
            ?>

            <?php if ($has_any_setting): ?>
                <div
                    style="margin: 24px 24px 8px; font-size: 12px; font-weight: 700; color: #999; text-transform: uppercase;">
                    Preferences</div>
                <a href="settings.php" class="<?= $current_page === 'settings.php' ? 'active' : '' ?>"><i
                        class="fa fa-cog"></i> Settings</a>
            <?php endif; ?>
        </nav>
        <div class="admin-footer">
            <a href="logout.php" class="btn-danger"
                style="display: block; text-align: center; text-decoration: none;"><i class="fa fa-sign-out-alt"></i>
                Logout</a>
        </div>
    </div>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Overview</h1>
            <div style="display: flex; gap: 16px; align-items: center;">
                <a href="../guest/index.php" target="_blank" class="btn-secondary"
                    style="padding: 6px 12px; font-size: 13px;"><i class="fa fa-external-link-alt"></i> View Site</a>
                <span style="font-weight: 500; font-size: 14px;"><i class="fa fa-user-circle"
                        style="color: var(--primary-green);"></i>
                    <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></span>
            </div>
        </header>
        <div class="admin-content">