<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Set base URL dynamically to support both local subfolder and production root hosting
$current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_url = ($current_dir == '/' || $current_dir == '.') ? '' : $current_dir;
if (basename($base_url) == 'user' || basename($base_url) == 'admin' || basename($base_url) == 'guest') {
    $base_url = dirname($base_url);
}
if ($base_url == '/') {
    $base_url = '';
}
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
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/admin.css">
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
                has_permission('manage_listings') || has_permission('manage_users') ||
                has_permission('manage_announcements') || has_permission('manage_ads');
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
                <span
                    style="font-weight: 500; font-size: 14px; display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2;">
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-user-circle" style="color: var(--primary-green);"></i>
                        <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
                    </span>
                    <span
                        style="font-size: 10px; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; text-align: right; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                        title="<?php
                        if (($_SESSION['admin_permissions'] ?? '') === '*') {
                            echo 'Root Administrator';
                        } else {
                            $assigned_perms = explode(',', $_SESSION['admin_permissions'] ?? '');
                            if (!empty($assigned_perms) && !empty($_SESSION['admin_permissions'])) {
                                $in_clause = implode(',', array_fill(0, count($assigned_perms), '?'));
                                try {
                                    $stmt_labels = $pdo->prepare("SELECT perm_label FROM staff_permissions_list WHERE perm_key IN ($in_clause)");
                                    $stmt_labels->execute($assigned_perms);
                                    $labels = $stmt_labels->fetchAll(PDO::FETCH_COLUMN);
                                    echo !empty($labels) ? htmlspecialchars(implode(' | ', $labels)) : 'System Administrator';
                                } catch (Exception $e) {
                                    echo 'System Administrator';
                                }
                            } else {
                                echo 'System Administrator (No Permissions)';
                            }
                        }
                        ?>">
                        <?php
                        if (($_SESSION['admin_permissions'] ?? '') === '*') {
                            echo 'Root Administrator';
                        } else {
                            $assigned_perms = explode(',', $_SESSION['admin_permissions'] ?? '');
                            if (!empty($assigned_perms) && !empty($_SESSION['admin_permissions'])) {
                                $in_clause = implode(',', array_fill(0, count($assigned_perms), '?'));
                                try {
                                    $stmt_labels = $pdo->prepare("SELECT perm_label FROM staff_permissions_list WHERE perm_key IN ($in_clause)");
                                    $stmt_labels->execute($assigned_perms);
                                    $labels = $stmt_labels->fetchAll(PDO::FETCH_COLUMN);
                                    echo !empty($labels) ? htmlspecialchars(implode(' | ', $labels)) : 'System Administrator';
                                } catch (Exception $e) {
                                    echo 'System Administrator';
                                }
                            } else {
                                echo 'System Administrator (No Permissions)';
                            }
                        }
                        ?>
                    </span>
                </span>
            </div>
        </header>
        <div class="admin-content">