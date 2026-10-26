<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// AJAX: Search ads by phone
if (isset($_GET['action']) && $_GET['action'] === 'search_ads_by_phone') {
    header('Content-Type: application/json');
    $phone = trim($_GET['phone'] ?? '');
    
    if (empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'Phone number is required.']);
        exit;
    }
    
    try {
        // Find user by phone number
        $stmt_u = $pdo->prepare("SELECT id, username, phone_number, profile_picture FROM users WHERE phone_number = ? OR phone_number LIKE ?");
        $stmt_u->execute([$phone, "%$phone"]);
        $user = $stmt_u->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }
        
        // Find ads posted by this user
        $stmt_a = $pdo->prepare("
            SELECT p.id, p.title, p.price, p.status, p.created_at, p.type,
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as thumbnail
            FROM products p
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt_a->execute([$user['id']]);
        $ads = $stmt_a->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'ads' => $ads
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// AJAX: Delete user ads
if (isset($_POST['action']) && $_POST['action'] === 'delete_user_ads') {
    header('Content-Type: application/json');
    $ad_ids = $_POST['ad_ids'] ?? [];
    $admin_password = $_POST['admin_password'] ?? '';

    // Privilege check: Must have manage_listings permission
    if (!has_permission('manage_listings')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied: You do not have privileges to delete ads.']);
        exit;
    }

    // Verify currently logged in user's password (Root Admin or Privileged Community Member)
    $stmt_admin = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt_admin->execute([$_SESSION['admin_id'] ?? 0]);
    $admin_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);

    if (!$admin_user || empty($admin_password) || !password_verify($admin_password, $admin_user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Authentication failed: Incorrect account password.']);
        exit;
    }
    
    if (empty($ad_ids)) {
        echo json_encode(['success' => false, 'error' => 'No ads selected.']);
        exit;
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($ad_ids), '?'));
        
        // 1. Delete product images from disk
        $stmt_img = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id IN ($placeholders)");
        $stmt_img->execute($ad_ids);
        $images = $stmt_img->fetchAll(PDO::FETCH_COLUMN);
        foreach ($images as $img) {
            $full_path = '../' . $img;
            if (is_file($full_path)) {
                @unlink($full_path);
            }
        }
        
        // 2. Delete product ratings, clicks, images, reports, and wishlist records from DB
        $pdo->prepare("DELETE FROM product_images WHERE product_id IN ($placeholders)")->execute($ad_ids);
        $pdo->prepare("DELETE FROM wishlist WHERE product_id IN ($placeholders)")->execute($ad_ids);
        $pdo->prepare("DELETE FROM analytics_clicks WHERE product_id IN ($placeholders)")->execute($ad_ids);
        $pdo->prepare("DELETE FROM reports WHERE product_id IN ($placeholders)")->execute($ad_ids);
        
        // 3. Delete messages for these products
        $pdo->prepare("DELETE FROM messages WHERE product_id IN ($placeholders)")->execute($ad_ids);
        
        // 4. Delete products
        $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)")->execute($ad_ids);
        
        $backup_file = dirname(__DIR__) . '/db/enteangadi.sql';
        if (function_exists('exportFullDatabaseToSql')) {
            exportFullDatabaseToSql($pdo, $backup_file);
        }

        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// AJAX: Get Admin Chat Thread
if (isset($_GET['action']) && $_GET['action'] === 'get_admin_chat_thread') {
    header('Content-Type: application/json');
    $product_id = (int)($_GET['product_id'] ?? 0);
    $user_id = (int)($_GET['user_id'] ?? 0);
    $current_admin_id = $_SESSION['admin_id'];

    if (!$product_id || !$user_id) {
        echo json_encode(['success' => false, 'error' => 'Missing product_id or user_id.']);
        exit;
    }

    try {
        // Mark customer messages to admin as read
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE product_id = ? AND sender_id = ? AND receiver_id = ?")
            ->execute([$product_id, $user_id, $current_admin_id]);

        // Product Details
        $stmt_p = $pdo->prepare("
            SELECT p.id, p.title, p.price, p.status, p.created_at, p.unique_id, p.is_admin_posted, p.user_id as ad_owner_id,
                   (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as thumbnail
            FROM products p WHERE p.id = ?
        ");
        $stmt_p->execute([$product_id]);
        $product = $stmt_p->fetch(PDO::FETCH_ASSOC);

        // Customer Details
        $stmt_u = $pdo->prepare("SELECT id, username, email, phone_number, profile_picture, is_verified, created_at FROM users WHERE id = ?");
        $stmt_u->execute([$user_id]);
        $customer = $stmt_u->fetch(PDO::FETCH_ASSOC);

        $ad_owner_id = $product['ad_owner_id'] ?? $current_admin_id;

        // Messages
        $stmt_m = $pdo->prepare("
            SELECT m.id, m.sender_id, m.receiver_id, m.product_id, m.message_text, m.is_read, m.created_at,
                   u.username as sender_username, u.profile_picture as sender_profile_picture, u.is_admin as sender_is_admin
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.product_id = ? 
              AND ((m.sender_id = ? AND (m.receiver_id = ? OR m.receiver_id = ?)) 
                OR ((m.sender_id = ? OR m.sender_id = ?) AND m.receiver_id = ?))
            ORDER BY m.created_at ASC
        ");
        $stmt_m->execute([$product_id, $user_id, $current_admin_id, $ad_owner_id, $current_admin_id, $ad_owner_id, $user_id]);
        $messages = $stmt_m->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'product' => $product,
            'customer' => $customer,
            'current_admin_id' => $current_admin_id,
            'messages' => $messages
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// AJAX: Send Admin Reply
if (isset($_POST['action']) && $_POST['action'] === 'send_admin_reply') {
    header('Content-Type: application/json');
    $product_id = (int)($_POST['product_id'] ?? 0);
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $current_admin_id = $_SESSION['admin_id'];

    if (!$product_id || !$receiver_id || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Product ID, recipient, and message text are required.']);
        exit;
    }

    require_once '../includes/helpers.php';
    if (function_exists('isTextInappropriate') && isTextInappropriate($message)) {
        echo json_encode(['success' => false, 'error' => 'Inappropriate content detected in message text.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, message_text, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$current_admin_id, $receiver_id, $product_id, $message]);
        $msg_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message_id' => $msg_id,
            'created_at' => date('Y-m-d H:i:s'),
            'message_text' => $message
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// AJAX: Delete Admin Chat
if (isset($_POST['action']) && $_POST['action'] === 'delete_admin_chat') {
    header('Content-Type: application/json');
    $product_id = (int)($_POST['product_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $current_admin_id = $_SESSION['admin_id'];

    if (!$product_id || !$user_id) {
        echo json_encode(['success' => false, 'error' => 'Parameters missing.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE product_id = ? AND ((sender_id = ? AND (receiver_id = ? OR receiver_id IN (SELECT id FROM users WHERE is_admin = 1))) OR ((sender_id = ? OR sender_id IN (SELECT id FROM users WHERE is_admin = 1)) AND receiver_id = ?))");
        $stmt->execute([$product_id, $user_id, $current_admin_id, $current_admin_id, $user_id]);
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Download SQL Backup File
if (isset($_GET['download_db_backup']) && $_GET['download_db_backup'] === '1') {
    if (!has_permission('*') && !has_permission('manage_security') && !has_permission('manage_general')) {
        die('Unauthorized: Insufficient administrative privileges.');
    }
    $backup_file = dirname(__DIR__) . '/db/enteangadi.sql';
    if (!file_exists($backup_file)) {
        exportFullDatabaseToSql($pdo, $backup_file);
    }
    if (file_exists($backup_file)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="enteangadi_backup_' . date('Y-m-d_His') . '.sql"');
        header('Content-Length: ' . filesize($backup_file));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');
        readfile($backup_file);
        exit;
    }
}

// Download Database Backup: redirect legacy download_clean_sql to standard backup download
if (isset($_GET['download_clean_sql']) && $_GET['download_clean_sql'] === '1') {
    header("Location: settings.php?download_db_backup=1");
    exit;
}

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Ensure dynamic SQL tables exist on load
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS interstitial_ads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        media_file VARCHAR(255) NOT NULL,
        media_type VARCHAR(50) NOT NULL,
        link_url VARCHAR(255) DEFAULT '',
        duration INT DEFAULT 5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_permissions_list (
        id INT AUTO_INCREMENT PRIMARY KEY,
        perm_key VARCHAR(50) UNIQUE NOT NULL,
        perm_label VARCHAR(100) NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $pdo->exec("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES 
        ('app_logo', ''),
        ('app_name', 'Enteangadi'),
        ('app_tagline', 'Your Local Marketplace'),
        ('announcement_poster', ''),
        ('adult_content_check', '1')");

    // Check count and seed if zero
    $count = $pdo->query("SELECT COUNT(*) FROM staff_permissions_list")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO staff_permissions_list (perm_key, perm_label) VALUES 
            ('manage_users', 'Users'),
            ('manage_categories', 'Categories'),
            ('manage_pending', 'Pending Ads'),
            ('manage_listings', 'Active Ads'),
            ('manage_branding', 'Branding'),
            ('manage_security', 'Security'),
            ('manage_general', 'General Config'),
            ('manage_contact', 'Contact/Social'),
            ('manage_approval', 'Approval Mode'),
            ('manage_announcements', 'Announcement Poster'),
            ('manage_database_backup', 'Database Backup')");
    }
} catch (PDOException $e) {
    // Fail silently
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'backup_database') {
        if (!has_permission('*') && !has_permission('manage_security') && !has_permission('manage_general') && !has_permission('manage_database_backup')) {
            $_SESSION['error_message'] = "Unauthorized: You do not have permission to run database backups.";
            header("Location: settings.php#database_backup");
            exit;
        }

        // Verify CSRF
        if (function_exists('verifyCsrfToken') && !verifyCsrfToken()) {
            $_SESSION['error_message'] = "Security validation failed (invalid CSRF token). Please retry.";
            header("Location: settings.php#database_backup");
            exit;
        }

        $backup_file = dirname(__DIR__) . '/db/enteangadi.sql';
        $res = exportFullDatabaseToSql($pdo, $backup_file);
        if ($res['success']) {
            $sizeFmt = round($res['bytes'] / 1024, 2) . ' KB';
            if ($res['bytes'] > 1048576) {
                $sizeFmt = round($res['bytes'] / 1048576, 2) . ' MB';
            }
            $_SESSION['success_message'] = "Full database backup generated successfully! Overwrote db/enteangadi.sql (" . $res['tables'] . " tables, " . $res['rows'] . " rows, " . $sizeFmt . ").";
        } else {
            $_SESSION['error_message'] = "Database backup failed: " . ($res['error'] ?? 'Unknown error');
        }
        header("Location: settings.php#database_backup");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'clean_production_sql') {
        if (!has_permission('*') && !has_permission('manage_security') && !has_permission('manage_general') && !has_permission('manage_database_backup')) {
            $_SESSION['error_message'] = "Unauthorized: You do not have permission to execute clean production reset.";
            header("Location: settings.php#database_backup");
            exit;
        }

        // Verify Admin / User Password
        $admin_password = $_POST['admin_password'] ?? '';
        $stmt_admin = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_admin->execute([$_SESSION['admin_id'] ?? 0]);
        $admin_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);

        if (!$admin_user || empty($admin_password) || !password_verify($admin_password, $admin_user['password'])) {
            $_SESSION['error_message'] = "Authentication failed: Incorrect account password. Operation aborted.";
            header("Location: settings.php#database_backup");
            exit;
        }

        // 1. Purge dummy test data from database and media files
        if (function_exists('purgeDummyTestData')) {
            $purge_res = purgeDummyTestData($pdo);
            if (!$purge_res['success']) {
                $_SESSION['error_message'] = "Cleanup error: " . ($purge_res['error'] ?? 'Failed to purge dummy data');
                header("Location: settings.php#database_backup");
                exit;
            }
        }

        // 2. Export clean database directly into standard db/enteangadi.sql
        $standard_backup = dirname(__DIR__) . '/db/enteangadi.sql';
        $export_res = exportFullDatabaseToSql($pdo, $standard_backup);

        // Remove legacy separate clean file if present
        $legacy_clean_file = dirname(__DIR__) . '/db/enteangadi_clean_production.sql';
        if (file_exists($legacy_clean_file)) {
            @unlink($legacy_clean_file);
        }

        if ($export_res['success']) {
            $sizeFmt = round($export_res['bytes'] / 1024, 2) . ' KB';
            $_SESSION['success_message'] = "🚀 Dummy test data wiped! Clean production database backup updated directly in db/enteangadi.sql ($sizeFmt).";
        } else {
            $_SESSION['error_message'] = "Clean backup failed: " . ($export_res['error'] ?? 'Unknown error');
        }
        header("Location: settings.php#database_backup");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'branding') {
        $tagline = $_POST['app_tagline'] ?? '';


        // Update tagline
        $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'app_tagline'");
        $stmt->execute([$tagline]);

        // Handle logo deletion
        if (isset($_POST['delete_logo']) && $_POST['delete_logo'] === '1') {
            $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'app_logo'");
            $stmt->execute();
            $current_logo = $stmt->fetchColumn();

            if (!empty($current_logo)) {
                $file_to_delete = '../' . $current_logo;
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
            }
            $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = '' WHERE setting_key = 'app_logo'");
            $stmt->execute();
        }

        // Handle logo upload
        if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/logo/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['app_logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp'];

            if (in_array($file_ext, $allowed)) {
                $file_name = 'logo_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $file_name;

                // For PNG and SVG logos, preserve transparency directly without altering alpha channel
                if ($file_ext === 'png' || $file_ext === 'svg') {
                    $uploaded = move_uploaded_file($_FILES['app_logo']['tmp_name'], $target_path);
                } else {
                    $uploaded = compressAndResizeImage($_FILES['app_logo']['tmp_name'], $target_path, 600, 85) || move_uploaded_file($_FILES['app_logo']['tmp_name'], $target_path);
                }

                if ($uploaded) {
                    @chmod($target_path, 0644);
                    $db_path = 'uploads/logo/' . $file_name;
                    $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'app_logo'");
                    $stmt->execute([$db_path]);
                }
            }
        }
        $success = "Visual identity updated successfully.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'general') {
        $app_name = $_POST['app_name'] ?? 'Enteangadi';
        $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'app_name'");
        $stmt->execute([$app_name]);
        $success = "General settings updated successfully.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'adult_content') {
        $check = isset($_POST['adult_content_check']) ? '1' : '0';
        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('adult_content_check', ?) 
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$check]);
        $success = "Adult content settings updated successfully.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'app_features') {
        $bg_active = isset($_POST['bg_notification_active']) ? '1' : '0';
        $bg_interval = $_POST['bg_notification_interval'] ?? '30';

        $fields = [
            'bg_notification_active' => $bg_active,
            'bg_notification_interval' => $bg_interval
        ];

        foreach ($fields as $key => $val) {
            $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) 
                                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $val]);
        }
        $success = "App feature configurations updated successfully.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'contact') {
        $fields = [
            'support_email',
            'support_phone',
            'whatsapp_number',
            'facebook_url',
            'instagram_url',
            'twitter_url',
            'play_store_url',
            'app_store_url'
        ];

        foreach ($fields as $field) {
            $value = $_POST[$field] ?? '';
            // Upsert logic
            $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) 
                                 VALUES (?, ?) 
                                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$field, $value]);
        }
        $success = "Contact and social settings updated successfully.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } else {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $user = $stmt->fetch();

                if (password_verify($current_password, $user['password'])) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update->execute([$hashed, $_SESSION['admin_id']]);
                    $success = "Password updated successfully.";
                } else {
                    $error = "Current password is incorrect.";
                }
            }
        } else {
            $error = "Please fill in all fields.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'approval') {
        $mode = $_POST['ad_approval_mode'] ?? 'auto';
        $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'ad_approval_mode'");
        $stmt->execute([$mode]);
        $success = "Ad approval mode updated to " . strtoupper($mode) . ".";
    } elseif (isset($_POST['action']) && ($_POST['action'] === 'approve_ad' || $_POST['action'] === 'reject_ad')) {
        $product_id = $_POST['product_id'] ?? '';
        if ($product_id) {
            if ($_POST['action'] === 'approve_ad') {
                $stmt = $pdo->prepare("UPDATE products SET status = 'active', is_verified = 1 WHERE id = ?");
                $stmt->execute([$product_id]);
                $success = "Ad approved and verified successfully.";
            } else {
                $stmt = $pdo->prepare("UPDATE products SET status = 'deleted' WHERE id = ?");
                $stmt->execute([$product_id]);
                $success = "Ad rejected and hidden.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'toggle_verify') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $current_status = (int)($_POST['current_status'] ?? 0);
        $new_status = $current_status ? 0 : 1;
        if ($product_id) {
            $stmt = $pdo->prepare("UPDATE products SET is_verified = ? WHERE id = ?");
            $stmt->execute([$new_status, $product_id]);
            $_SESSION['success_message'] = $new_status ? "Trust Badge (Verified) granted to listing #$product_id successfully!" : "Verification badge removed from listing #$product_id.";
            header("Location: settings.php#manage_ads");
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_ad_item') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $admin_password = $_POST['admin_password'] ?? '';

        // Privilege check: Must have manage_listings permission
        if (!has_permission('manage_listings')) {
            $_SESSION['error_message'] = "Permission denied: You do not have privileges to delete listings.";
            header("Location: settings.php#manage_ads");
            exit;
        }

        // Verify currently logged in user's password (Root Admin or Privileged Community Member)
        $stmt_admin = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_admin->execute([$_SESSION['admin_id'] ?? 0]);
        $admin_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);

        if (!$admin_user || empty($admin_password) || !password_verify($admin_password, $admin_user['password'])) {
            $_SESSION['error_message'] = "Authentication failed: Incorrect account password. Listing deletion aborted.";
            header("Location: settings.php#manage_ads");
            exit;
        }

        if ($product_id) {
            // Delete product images from disk
            $stmt_img = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $stmt_img->execute([$product_id]);
            $images = $stmt_img->fetchAll(PDO::FETCH_COLUMN);
            foreach ($images as $img) {
                $full_path = '../' . $img;
                if (is_file($full_path)) {
                    @unlink($full_path);
                }
            }

            // Clean up DB records
            $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$product_id]);
            $pdo->prepare("DELETE FROM wishlist WHERE product_id = ?")->execute([$product_id]);
            $pdo->prepare("DELETE FROM analytics_clicks WHERE product_id = ?")->execute([$product_id]);
            $pdo->prepare("DELETE FROM reports WHERE product_id = ?")->execute([$product_id]);
            $pdo->prepare("DELETE FROM messages WHERE product_id = ?")->execute([$product_id]);

            // Delete product
            $stmt_del = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt_del->execute([$product_id]);

            // Synchronize DB backup
            $backup_file = dirname(__DIR__) . '/db/enteangadi.sql';
            if (function_exists('exportFullDatabaseToSql')) {
                exportFullDatabaseToSql($pdo, $backup_file);
            }

            $_SESSION['success_message'] = "Listing #$product_id has been permanently deleted from the marketplace.";
            header("Location: settings.php#manage_ads");
            exit;
        }
    } elseif (isset($_POST['action']) && in_array($_POST['action'], ['approve_profile_verification', 'reject_profile_verification'])) {
        $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        $action = $_POST['action'];
        
        if ($request_id) {
            // Fetch request details
            $stmt_req = $pdo->prepare("SELECT user_id, id_photo FROM verification_requests WHERE id = ?");
            $stmt_req->execute([$request_id]);
            $req_data = $stmt_req->fetch();
            
            if ($req_data) {
                $user_id = $req_data['user_id'];
                
                if ($action === 'approve_profile_verification') {
                    try {
                        $pdo->beginTransaction();
                        
                        // Update request status
                        $stmt_up_req = $pdo->prepare("UPDATE verification_requests SET status = 'approved' WHERE id = ?");
                        $stmt_up_req->execute([$request_id]);
                        
                        // Update user verified flag
                        $stmt_up_usr = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                        $stmt_up_usr->execute([$user_id]);
                        
                        $pdo->commit();
                        $success = "Verification request approved successfully. User badge activated.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "Failed to approve request: " . $e->getMessage();
                    }
                } elseif ($action === 'reject_profile_verification') {
                    $reason = trim($_POST['rejection_reason'] ?? 'Document is not clear or does not match profile details.');
                    
                    try {
                        $pdo->beginTransaction();
                        
                        // Update request status
                        $stmt_up_req = $pdo->prepare("UPDATE verification_requests SET status = 'rejected', rejection_reason = ? WHERE id = ?");
                        $stmt_up_req->execute([$reason, $request_id]);
                        
                        // Reset user verified flag if previously verified
                        $stmt_up_usr = $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
                        $stmt_up_usr->execute([$user_id]);
                        
                        $pdo->commit();
                        $success = "Verification request rejected and user notified.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "Failed to reject request: " . $e->getMessage();
                    }
                }
                
                // Redirect to keep profile_verifications active and prevent form resubmission
                if (empty($error)) {
                    $_SESSION['success_message'] = $success;
                    header("Location: settings.php#profile_verifications");
                    exit;
                }
            } else {
                $error = "Request details not found.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'announcement') {
        if (!has_permission('manage_announcements')) {
            $error = "Unauthorized: Access Denied.";
        } else {
            // Handle deletion
            if (isset($_POST['delete_poster']) && $_POST['delete_poster'] === '1') {
                $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'announcement_poster'");
                $stmt->execute();
                $current = $stmt->fetchColumn();

                if (!empty($current)) {
                    $file_to_delete = '../' . $current;
                    if (file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                }
                $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = '' WHERE setting_key = 'announcement_poster'");
                $stmt->execute();
                $success = "Announcement poster removed.";
            }

            // Handle upload
            if (isset($_FILES['announcement_poster']) && $_FILES['announcement_poster']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/posters/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_ext = strtolower(pathinfo($_FILES['announcement_poster']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($file_ext, $allowed)) {
                    $file_name = 'poster_' . time() . '.' . $file_ext;
                    $target_path = $upload_dir . $file_name;

                    if (compressAndResizeImage($_FILES['announcement_poster']['tmp_name'], $target_path, 1200, 80) || move_uploaded_file($_FILES['announcement_poster']['tmp_name'], $target_path)) {
                        @chmod($target_path, 0644);
                        $db_path = 'uploads/posters/' . $file_name;
                        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('announcement_poster', ?) 
                                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                        $stmt->execute([$db_path]);
                        $success = "Announcement poster uploaded successfully.";
                    }
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_custom_permission') {
        if (($_SESSION['admin_permissions'] ?? '') !== '*') {
            $error = "Unauthorized: Root Access Required.";
        } else {
            $perm_key = strtolower(trim($_POST['perm_key'] ?? ''));
            $perm_label = trim($_POST['perm_label'] ?? '');

            // Remove spaces and non-alphanumeric chars for key
            $perm_key = preg_replace('/[^a-z0-9_]/', '', $perm_key);

            if (empty($perm_key) || empty($perm_label)) {
                $error = "Both privilege key and display label are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO staff_permissions_list (perm_key, perm_label) VALUES (?, ?)");
                    $stmt->execute([$perm_key, $perm_label]);
                    $success = "New privilege '$perm_label' successfully added to checklist!";
                } catch (PDOException $e) {
                    $error = "Privilege key '$perm_key' already exists.";
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_custom_permission') {
        if (($_SESSION['admin_permissions'] ?? '') !== '*') {
            $error = "Unauthorized: Root Access Required.";
        } else {
            $perm_id = (int) ($_POST['perm_id'] ?? 0);
            if ($perm_id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM staff_permissions_list WHERE id = ?");
                    $stmt->execute([$perm_id]);
                    $success = "Privilege removed successfully.";
                } catch (PDOException $e) {
                    $error = "Failed to remove privilege.";
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_interstitial_ad') {
        if (!has_permission('manage_ads')) {
            $error = "Unauthorized: Access Denied.";
        } else {
            $ad_id = (int) ($_POST['ad_id'] ?? 0);
            if ($ad_id > 0) {
                $stmt = $pdo->prepare("SELECT media_file FROM interstitial_ads WHERE id = ?");
                $stmt->execute([$ad_id]);
                $file = $stmt->fetchColumn();

                if (!empty($file)) {
                    $file_to_delete = '../' . $file;
                    if (file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM interstitial_ads WHERE id = ?");
                $stmt->execute([$ad_id]);
                $success = "Ad removed from the rotation queue successfully.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'interstitial_ads') {
        if (!has_permission('manage_ads')) {
            $error = "Unauthorized: Access Denied.";
        } else {
            // Handle global settings updates
            $ad_active = isset($_POST['interstitial_ad_active']) ? '1' : '0';
            $ad_frequency = (int) ($_POST['interstitial_ad_frequency'] ?? 10);

            $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('interstitial_ad_active', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$ad_active]);

            $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('interstitial_ad_frequency', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$ad_frequency]);

            $success = "Global interstitial ad configurations updated.";

            // Handle new ad upload if provided
            if (isset($_FILES['interstitial_ad_file']) && $_FILES['interstitial_ad_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/interstitial_ads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_ext = strtolower(pathinfo($_FILES['interstitial_ad_file']['name'], PATHINFO_EXTENSION));
                $allowed_imgs = ['jpg', 'jpeg', 'png', 'webp'];
                $allowed_vids = ['mp4', 'webm', 'ogg'];
                $allowed = array_merge($allowed_imgs, $allowed_vids);

                if (in_array($file_ext, $allowed)) {
                    $file_name = 'ad_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                    $target_path = $upload_dir . $file_name;

                    $is_compressed = false;
                    $ad_type = in_array($file_ext, $allowed_vids) ? 'video' : 'image';
                    if ($ad_type === 'image') {
                        $is_compressed = compressAndResizeImage($_FILES['interstitial_ad_file']['tmp_name'], $target_path, 1080, 80);
                    }
                    
                    if ($is_compressed || move_uploaded_file($_FILES['interstitial_ad_file']['tmp_name'], $target_path)) {
                        @chmod($target_path, 0644);
                        $db_path = 'uploads/interstitial_ads/' . $file_name;
                        $ad_link = $_POST['interstitial_ad_link'] ?? '';
                        $ad_duration = (int) ($_POST['interstitial_ad_duration'] ?? 5);

                        $stmt = $pdo->prepare("INSERT INTO interstitial_ads (media_file, media_type, link_url, duration) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$db_path, $ad_type, $ad_link, $ad_duration]);
                        $success = "New ad published to rotation queue successfully.";
                    }
                } else {
                    $error = "Unsupported file format. Please upload JPG, PNG, WebP or MP4.";
                }
            }
        }
    }

    // Direct Ad Posting by Admin
    if (isset($_POST['admin_post_ad'])) {
        $type = $_POST['type'] ?? 'sell';
        $title = trim($_POST['title'] ?? '');
        $category_id = $_POST['category_id'] ?? '';
        $price = isset($_POST['price']) ? preg_replace('/[^-0-9.]/', '', $_POST['price']) : '0';
        $contact_whatsapp = isset($_POST['contact_whatsapp']) ? 1 : 0;
        $contact_phone = isset($_POST['contact_phone']) ? 1 : 0;

        $whatsapp_number = $contact_whatsapp ? trim($_POST['whatsapp_number'] ?? '') : '';
        $phone_number = $contact_phone ? trim($_POST['phone_number'] ?? '') : '';
        $description = trim($_POST['description'] ?? '');
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

        $location_name = trim($_POST['location_name'] ?? '');
        $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;

        if (!empty($title) && !empty($category_id)) {
            try {
                $pdo->beginTransaction();

                $unique_id = 'ENTAGD' . rand(1000, 9999);
                $check_stmt = $pdo->prepare("SELECT 1 FROM products WHERE unique_id = ?");
                $check_stmt->execute([$unique_id]);
                while ($check_stmt->fetch()) {
                    $unique_id = 'ENTAGD' . rand(1000, 9999);
                    $check_stmt->execute([$unique_id]);
                }

                // Admin posted ads are automatically active, verified, and flagged with is_admin_posted = 1
                $status = 'active';
                $is_verified = 1;
                $is_admin_posted = 1;

                $stmt = $pdo->prepare("INSERT INTO products (user_id, unique_id, category_id, type, title, description, price, expiry_date, whatsapp_number, phone_number, location_name, latitude, longitude, status, is_verified, is_admin_posted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['admin_id'], $unique_id, $category_id, $type, $title, $description, $price, $expiry_date, $whatsapp_number, $phone_number, $location_name, $latitude, $longitude, $status, $is_verified, $is_admin_posted]);
                $product_id = $pdo->lastInsertId();

                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    $upload_dir = '../uploads/products/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $cover_idx = (int) ($_POST['cover_index'] ?? 0);
                    $indices = array_keys($_FILES['images']['tmp_name']);

                    if (in_array($cover_idx, $indices)) {
                        $indices = array_diff($indices, [$cover_idx]);
                        array_unshift($indices, $cover_idx);
                    }

                    foreach ($indices as $key) {
                        $tmp_name = $_FILES['images']['tmp_name'][$key];
                        if (empty($tmp_name)) continue;

                        $file_name = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['images']['name'][$key]));
                        $target_file = $upload_dir . $file_name;

                        if (function_exists('compressAndResizeImage') && compressAndResizeImage($tmp_name, $target_file, 800, 75)) {
                            @chmod($target_file, 0644);
                            $stmt_img = $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                            $stmt_img->execute([$product_id, 'uploads/products/' . $file_name]);
                        } elseif (move_uploaded_file($tmp_name, $target_file)) {
                            @chmod($target_file, 0644);
                            $stmt_img = $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                            $stmt_img->execute([$product_id, 'uploads/products/' . $file_name]);
                        }
                    }
                }

                $pdo->commit();
                $success = "Official Admin Advertisement posted successfully! <a href='../product.php?id={$product_id}' target='_blank' style='font-weight: 800; color: #047857; text-decoration: underline; margin-left: 8px;'><i class='fa fa-external-link-alt'></i> View Live Ad #{$unique_id}</a>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error posting advertisement: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all mandatory fields (Title, Category).";
        }
    }
}

// Fetch categories for Admin Ad Posting
$all_cats = [];
$admin_l1_cats = [];
$admin_l2_cats = [];
$admin_cat_details = [];
try {
    $stmt_cats = $pdo->query("SELECT * FROM categories ORDER BY name");
    $all_cats = $stmt_cats->fetchAll();
    foreach ($all_cats as $cat) {
        $admin_cat_details[$cat['id']] = ['is_perishable' => $cat['is_perishable']];
        if (empty($cat['parent_id'])) {
            $admin_l1_cats[] = $cat;
        } else {
            $admin_l2_cats[$cat['parent_id']][] = $cat;
        }
    }
} catch (Exception $e) {
    // Keep empty
}
$admin_l2_json = json_encode($admin_l2_cats);
$admin_cat_details_json = json_encode($admin_cat_details);

// Fetch current app settings
try {
    $stmt = $pdo->query("SELECT * FROM app_settings");
    $settings_raw = $stmt->fetchAll();
    $app_settings = [];
    foreach ($settings_raw as $s) {
        $app_settings[$s['setting_key']] = $s['setting_value'];
    }
} catch (PDOException $e) {
    $app_settings = ['app_name' => 'Enteangadi'];
}

// Fetch active interstitial ads list for the queue display
$active_ads = [];
try {
    $stmt = $pdo->query("SELECT * FROM interstitial_ads ORDER BY id ASC");
    $active_ads = $stmt->fetchAll();
} catch (PDOException $e) {
    $active_ads = [];
}

// Fetch dynamic permissions checklist
$available_permissions = [];
try {
    $available_permissions = $pdo->query("SELECT * FROM staff_permissions_list ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $available_permissions = [];
}

// Fetch pending products for the integrated approval section
$pending_stmt = $pdo->query("
    SELECT p.*, u.username, u.phone_number as user_phone, c.name as category_name,
    (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as main_image,
    (SELECT GROUP_CONCAT(image_path) FROM product_images WHERE product_id = p.id) as all_images
    FROM products p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
");
$pending_products = $pending_stmt->fetchAll();

// Fetch all active products for management
$active_stmt = $pdo->query("
    SELECT p.*, u.username, u.phone_number as seller_phone, c.name as category_name,
    (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as main_image,
    (SELECT GROUP_CONCAT(image_path) FROM product_images WHERE product_id = p.id) as all_images
    FROM products p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    WHERE p.status IN ('active', 'inactive')
    ORDER BY p.created_at DESC
");
$active_products = $active_stmt->fetchAll();

// Fetch closure feedback (Sold/Deleted/Expired)
$feedback_stmt = $pdo->query("
    SELECT p.*, u.username, c.name as category_name,
    (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as main_image
    FROM products p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    WHERE p.status IN ('sold', 'deleted', 'expired') AND p.status_reason IS NOT NULL
    ORDER BY p.updated_at DESC
    LIMIT 150
");
$closure_feedback = $feedback_stmt->fetchAll();

// Fetch account closures feedback
$account_feedback_stmt = $pdo->query("
    SELECT * FROM account_closures 
    ORDER BY created_at DESC 
    LIMIT 100
");
$account_feedback = $account_feedback_stmt->fetchAll();

// Fetch Profile Verification requests (pending, approved, rejected)
$pending_verifications = [];
$approved_verifications = [];
$rejected_verifications = [];
try {
    $stmt_p = $pdo->prepare("
        SELECT vr.*, u.username, u.email, u.phone_number 
        FROM verification_requests vr
        JOIN users u ON vr.user_id = u.id
        WHERE vr.status = 'pending'
        ORDER BY vr.created_at DESC
    ");
    $stmt_p->execute();
    $pending_verifications = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

    $stmt_a = $pdo->prepare("
        SELECT vr.*, u.username, u.email, u.phone_number 
        FROM verification_requests vr
        JOIN users u ON vr.user_id = u.id
        WHERE vr.status = 'approved'
        ORDER BY vr.created_at DESC
    ");
    $stmt_a->execute();
    $approved_verifications = $stmt_a->fetchAll(PDO::FETCH_ASSOC);

    $stmt_r = $pdo->prepare("
        SELECT vr.*, u.username, u.email, u.phone_number 
        FROM verification_requests vr
        JOIN users u ON vr.user_id = u.id
        WHERE vr.status = 'rejected'
        ORDER BY vr.created_at DESC
    ");
    $stmt_r->execute();
    $rejected_verifications = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch all users for Management
$all_users_stmt = $pdo->query("
    SELECT u.id, u.username, u.email, 
    IFNULL(u.phone, u.phone_number) as phone, 
    u.profile_picture, u.is_admin, u.permissions, u.created_at,
    (SELECT COUNT(*) FROM products WHERE user_id = u.id AND status = 'active') as active_ads,
    (SELECT COUNT(*) FROM products WHERE user_id = u.id) as total_ads
    FROM users u 
    ORDER BY u.created_at DESC
");
$all_users = $all_users_stmt->fetchAll();

// Handle AJAX Request for Secure Data Reveal
if (isset($_POST['action']) && $_POST['action'] === 'reveal_user_data') {
    $admin_pass = $_POST['admin_password'];
    $target_user_id = $_POST['target_user_id'];

    // Check admin password using the correct session key 'admin_id'
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND is_admin = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($admin_pass, $admin['password'])) {
        $user_stmt = $pdo->prepare("SELECT email, phone_number AS phone FROM users WHERE id = ?");
        $user_stmt->execute([$target_user_id]);
        echo json_encode(['success' => true, 'data' => $user_stmt->fetch()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid admin password']);
    }
    exit;
}

// Handle Administrative Password Reset
if (isset($_POST['action']) && $_POST['action'] === 'admin_reset_password') {
    $target_user_id = $_POST['target_user_id'];
    $new_pass = $_POST['new_password'];

    // Security Check: Sub-admins cannot reset the password of a Root Admin (*)
    $check_target = $pdo->prepare("SELECT permissions FROM users WHERE id = ?");
    $check_target->execute([$target_user_id]);
    $target_perms = $check_target->fetchColumn();

    if ($target_perms === '*' && ($_SESSION['admin_permissions'] ?? '') !== '*') {
        echo json_encode(['success' => false, 'message' => 'CRITICAL SECURITY: Unauthorized attempt to modify Root credentials. Action logged.']);
        exit;
    }

    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$hashed, $target_user_id])) {
        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Handle User Deletion (with media cleanup)
if (isset($_POST['action']) && $_POST['action'] === 'admin_delete_user') {
    $target_user_id = $_POST['target_user_id'] ?? 0;
    $admin_password = $_POST['admin_password'] ?? '';

    // Privilege check: Must have manage_users permission
    if (!has_permission('manage_users')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied: You do not have privileges to delete user profiles.']);
        exit;
    }

    // Verify currently logged in user's password (Root Admin or Privileged Community Member)
    $stmt_admin = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt_admin->execute([$_SESSION['admin_id'] ?? 0]);
    $admin_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);

    if (!$admin_user || empty($admin_password) || !password_verify($admin_password, $admin_user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Authentication failed: Incorrect account password.']);
        exit;
    }

    // 1. Prevent self-deletion
    if ($target_user_id == $_SESSION['admin_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own administrative account']);
        exit;
    }

    // 2. Fetch target user details for security checks
    $stmt_check = $pdo->prepare("SELECT permissions, username, profile_picture FROM users WHERE id = ?");
    $stmt_check->execute([$target_user_id]);
    $target_user = $stmt_check->fetch();

    if (!$target_user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // 3. Security: Sub-admins cannot delete Root Admins or users with * permissions
    if ($target_user['permissions'] === '*' && ($_SESSION['admin_permissions'] ?? '') !== '*') {
        echo json_encode(['success' => false, 'message' => 'CRITICAL SECURITY: Non-Root accounts cannot delete a Root Administrator']);
        exit;
    }

    // 4. Fetch product images before deletion
    $profile_pic = $target_user['profile_picture'];
    $stmt = $pdo->prepare("SELECT pi.image_path FROM product_images pi JOIN products p ON pi.product_id = p.id WHERE p.user_id = ?");
    $stmt->execute([$target_user_id]);
    $product_images = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 5. Delete from DB
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$target_user_id])) {
        // 6. Physical cleanup
        if ($profile_pic && file_exists('../' . $profile_pic))
            unlink('../' . $profile_pic);
        foreach ($product_images as $img) {
            if ($img && file_exists('../' . $img))
                unlink('../' . $img);
        }
        echo json_encode(['success' => true, 'message' => 'User and media permanently removed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user due to a database error']);
    }
    exit;
}

// Handle Administrative User Creation
if (isset($_POST['action']) && $_POST['action'] === 'admin_create_user') {
    if (($_SESSION['admin_permissions'] ?? '') !== '*') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Root Access Required']);
        exit;
    }
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $is_admin = (int) $_POST['is_admin'];

    // 1. Validation
    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username, Email, and Password are required']);
        exit;
    }

    // 2. Check existence
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or Email already exists']);
        exit;
    }

    // 3. Create User
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : ''; // Comma separated string

    $stmt = $pdo->prepare("INSERT INTO users (username, email, phone_number, password, is_admin, role, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $role = $is_admin ? 'admin' : 'user';

    if ($stmt->execute([$username, $email, $phone, $hashed, $is_admin, $role, $permissions])) {
        echo json_encode(['success' => true, 'message' => 'New user provisioned with assigned roles']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error during creation']);
    }
    exit;
}

// Handle Admin Permissions Edit
if (isset($_POST['action']) && $_POST['action'] === 'admin_edit_permissions') {
    if (($_SESSION['admin_permissions'] ?? '') !== '*') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Root Access Required']);
        exit;
    }

    $target_user_id = (int) $_POST['user_id'];
    $permissions = $_POST['permissions']; // Comma separated or *
    $is_admin = (int) $_POST['is_admin'];
    $role = $is_admin ? 'admin' : 'user';

    $stmt = $pdo->prepare("UPDATE users SET permissions = ?, is_admin = ?, role = ? WHERE id = ?");
    if ($stmt->execute([$permissions, $is_admin, $role, $target_user_id])) {
        echo json_encode(['success' => true, 'message' => 'Account status and privileges updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Handle AJAX Request for User Ads History
if (isset($_POST['action']) && $_POST['action'] === 'get_user_ads') {
    header('Content-Type: application/json');
    $target_user_id = $_POST['target_user_id'];
    try {
        $stmt = $pdo->prepare("
            SELECT title, price, status, created_at 
            FROM products 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$target_user_id]);
        $ads = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $ads]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Database Backup status variables
$backup_file_rel = 'db/enteangadi.sql';
$backup_file_path = dirname(__DIR__) . '/' . $backup_file_rel;
$backup_file_exists = file_exists($backup_file_path);
$backup_file_size = $backup_file_exists ? filesize($backup_file_path) : 0;
$backup_file_time = $backup_file_exists ? filemtime($backup_file_path) : 0;

$all_db_tables = [];
$total_db_records_count = 0;
try {
    $all_db_tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($all_db_tables as $tbl) {
        $cnt = $pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        $total_db_records_count += intval($cnt);
    }
} catch (Exception $e) {}

// Fetch conversations for Admin-posted ads
$admin_conversations = [];
$admin_unread_count = 0;
$current_admin_id = $_SESSION['admin_id'] ?? 1;

try {
    // 1. Unread count
    $stmt_ur = $pdo->prepare("
        SELECT COUNT(*) FROM messages m
        JOIN products p ON m.product_id = p.id
        WHERE (p.is_admin_posted = 1 OR p.user_id = ? OR p.user_id IN (SELECT id FROM users WHERE is_admin = 1))
          AND m.receiver_id = ?
          AND m.is_read = 0
    ");
    $stmt_ur->execute([$current_admin_id, $current_admin_id]);
    $admin_unread_count = (int)$stmt_ur->fetchColumn();

    // 2. Distinct conversations
    $stmt_conv = $pdo->prepare("
        SELECT 
            p.id as product_id,
            p.title as product_title,
            p.price as product_price,
            p.status as product_status,
            p.is_admin_posted,
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as product_thumbnail,
            u.id as user_id,
            u.username,
            u.email,
            u.phone_number,
            u.profile_picture,
            u.is_verified as user_is_verified,
            MAX(m.created_at) as last_message_time,
            SUM(CASE WHEN m.receiver_id = :admin_id AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count
        FROM messages m
        JOIN products p ON m.product_id = p.id
        JOIN users u ON (u.id = CASE WHEN m.sender_id = :admin_id OR m.sender_id = p.user_id THEN m.receiver_id ELSE m.sender_id END)
        WHERE (p.is_admin_posted = 1 OR p.user_id = :admin_id OR p.user_id IN (SELECT id FROM users WHERE is_admin = 1))
          AND u.id != :admin_id
          AND u.is_admin = 0
        GROUP BY p.id, p.title, p.price, p.status, p.is_admin_posted, u.id, u.username, u.email, u.phone_number, u.profile_picture, u.is_verified
        ORDER BY last_message_time DESC
    ");
    $stmt_conv->execute(['admin_id' => $current_admin_id]);
    $admin_conversations = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the actual last message snippet and sender for each conversation
    foreach ($admin_conversations as &$c) {
        $stmt_lm = $pdo->prepare("
            SELECT message_text, sender_id, created_at FROM messages
            WHERE product_id = ?
              AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt_lm->execute([$c['product_id'], $c['user_id'], $current_admin_id, $current_admin_id, $c['user_id']]);
        $lm = $stmt_lm->fetch(PDO::FETCH_ASSOC);
        $c['last_message'] = $lm ? $lm['message_text'] : '';
        $c['last_sender_id'] = $lm ? $lm['sender_id'] : 0;
        if ($lm && !empty($lm['created_at'])) {
            $c['last_message_time'] = $lm['created_at'];
        }
    }
    unset($c);
} catch (Exception $e) {
    $admin_conversations = [];
    $admin_unread_count = 0;
}

require_once 'includes/header.php';
?>

<style>
    /* Enteangadi Official Admin Ad Messenger */
    #section-admin_messages.active {
        display: flex !important;
        flex-direction: column;
        height: calc(100vh - 130px);
        max-height: calc(100vh - 130px);
        min-height: 480px;
        overflow: hidden;
    }

    .ea-messenger-container {
        display: grid;
        grid-template-columns: 360px 1fr;
        flex: 1;
        min-height: 0;
        height: 100%;
        max-height: 100%;
        background: #ffffff;
        border-radius: 20px;
        border: 1.5px solid #e2e8f0;
        box-shadow: 0 10px 25px rgba(0,0,0,0.06);
        overflow: hidden;
        font-family: inherit;
    }

    .ea-messenger-sidebar {
        background: #f8fafc;
        border-right: 1.5px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        overflow: hidden;
    }

    .ea-sidebar-header {
        height: 64px;
        padding: 12px 18px;
        background: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1.5px solid #f1f5f9;
    }

    .ea-sidebar-title {
        font-size: 18px;
        font-weight: 800;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: -0.3px;
    }

    .ea-sidebar-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .ea-icon-btn {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 14px;
        cursor: pointer;
        padding: 7px 10px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .ea-icon-btn:hover {
        background: #ecfdf5;
        color: #059669;
        border-color: #a7f3d0;
    }

    .ea-search-wrapper {
        padding: 12px 16px 8px;
        background: #f8fafc;
    }

    .ea-search-bar {
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: border-color 0.2s;
    }

    .ea-search-bar:focus-within {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    }

    .ea-search-bar i {
        color: #94a3b8;
        font-size: 13px;
    }

    .ea-search-bar input {
        border: none;
        background: transparent;
        width: 100%;
        font-size: 13.5px;
        color: #1e293b;
        outline: none;
    }

    .ea-filter-chips {
        padding: 6px 16px 12px;
        display: flex;
        gap: 6px;
        align-items: center;
        border-bottom: 1.5px solid #f1f5f9;
        background: #f8fafc;
    }

    .ea-filter-chip {
        padding: 5px 12px;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .ea-filter-chip:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .ea-filter-chip.active {
        background: #ecfdf5;
        color: #047857;
        border-color: #6ee7b7;
        box-shadow: 0 1px 3px rgba(5, 150, 105, 0.1);
    }

    .ea-chip-badge {
        background: #ef4444;
        color: white;
        font-size: 10px;
        font-weight: 800;
        padding: 1px 6px;
        border-radius: 10px;
    }

    /* Permanent, Visible Styled Scrollbar for Chats List and Messages Body */
    .ea-chats-list {
        flex: 1;
        overflow-y: scroll !important;
        background: #f8fafc;
        scrollbar-width: thin !important;
        scrollbar-color: #cbd5e1 #f8fafc !important;
    }

    .ea-chats-list::-webkit-scrollbar {
        width: 6px !important;
        display: block !important;
    }

    .ea-chats-list::-webkit-scrollbar-track {
        background: #f8fafc !important;
    }

    .ea-chats-list::-webkit-scrollbar-thumb {
        background: #cbd5e1 !important;
        border-radius: 4px !important;
    }

    .ea-chats-list::-webkit-scrollbar-thumb:hover {
        background: #94a3b8 !important;
    }

    .ea-no-chats {
        padding: 48px 24px;
        text-align: center;
        color: #94a3b8;
    }

    .ea-no-chats i {
        font-size: 36px;
        color: #059669;
        margin-bottom: 12px;
        display: block;
    }

    .ea-chat-item {
        display: flex;
        padding: 12px 16px;
        gap: 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        background: #f8fafc;
        transition: all 0.15s;
        position: relative;
    }

    .ea-chat-item:hover {
        background: #ffffff;
    }

    .ea-chat-item.active {
        background: #ffffff;
        border-left: 4px solid #059669;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .ea-avatar-wrap {
        position: relative;
        flex-shrink: 0;
    }

    .ea-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 1.5px solid #e2e8f0;
    }

    .ea-avatar-initials {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 800;
        border: 1.5px solid #a7f3d0;
    }

    .ea-chat-content {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .ea-chat-top-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 2px;
    }

    .ea-chat-name {
        font-size: 14px;
        font-weight: 700;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .ea-verified-icon {
        color: #10b981;
        font-size: 12px;
    }

    .ea-chat-time {
        font-size: 11px;
        color: #94a3b8;
        flex-shrink: 0;
    }

    .ea-chat-item.is-unread .ea-chat-time {
        color: #059669;
        font-weight: 700;
    }

    .ea-chat-ad-pill {
        font-size: 11px;
        color: #059669;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 2px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .ea-chat-bottom-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ea-chat-preview {
        font-size: 12.5px;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .ea-unread-badge {
        background: #ef4444;
        color: white;
        font-size: 10px;
        font-weight: 800;
        min-width: 18px;
        height: 18px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
        flex-shrink: 0;
    }

    .ea-main-pane {
        background: #ffffff;
        display: flex;
        flex-direction: column;
        height: 100%;
        position: relative;
    }

    .ea-empty-state {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 40px 24px;
        background: #ffffff;
    }

    .ea-empty-icon-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: white;
        font-size: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        box-shadow: 0 8px 20px rgba(5, 150, 105, 0.25);
    }

    .ea-empty-state h2 {
        font-size: 22px;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 8px;
        letter-spacing: -0.3px;
    }

    .ea-empty-state p {
        font-size: 13.5px;
        color: #64748b;
        max-width: 440px;
        line-height: 1.5;
        margin: 0 0 28px;
    }

    .ea-empty-actions {
        display: flex;
        gap: 16px;
        margin-bottom: 32px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .ea-empty-action-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 16px 20px;
        border-radius: 14px;
        min-width: 110px;
        transition: all 0.2s;
    }

    .ea-empty-action-card:hover {
        background: #f0fdf4;
        border-color: #86efac;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.12);
    }

    .ea-empty-action-card i {
        font-size: 22px;
        color: #059669;
    }

    .ea-empty-action-card span {
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }

    .ea-active-room {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    .ea-room-header {
        height: 64px;
        padding: 10px 20px;
        background: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1.5px solid #f1f5f9;
        z-index: 10;
    }

    .ea-room-user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .ea-room-user-name {
        font-size: 15px;
        font-weight: 800;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .ea-room-price-badge {
        background: #059669;
        color: white;
        font-size: 11px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 6px;
    }

    .ea-room-subtext {
        font-size: 12px;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
    }

    .ea-room-ad-title {
        color: #059669;
        font-weight: 700;
        max-width: 240px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ea-room-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .ea-top-btn {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.2s;
    }

    .ea-top-btn:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .ea-delete-btn:hover {
        background: #fee2e2;
        color: #b91c1c;
        border-color: #fca5a5;
    }

    /* Permanent Visible Scrollbar on Messages Body */
    .ea-messages-body {
        flex: 1;
        overflow-y: scroll !important;
        padding: 20px 24px;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
        gap: 12px;
        scrollbar-width: thin !important;
        scrollbar-color: #94a3b8 #f1f5f9 !important;
    }

    .ea-messages-body::-webkit-scrollbar {
        width: 8px !important;
        display: block !important;
    }

    .ea-messages-body::-webkit-scrollbar-track {
        background: #f1f5f9 !important;
        border-radius: 4px !important;
    }

    .ea-messages-body::-webkit-scrollbar-thumb {
        background: #94a3b8 !important;
        border-radius: 4px !important;
    }

    .ea-messages-body::-webkit-scrollbar-thumb:hover {
        background: #059669 !important;
    }

    .ea-bubble-wrap {
        display: flex;
        flex-direction: column;
        max-width: 70%;
    }

    .ea-bubble-wrap.sent {
        align-self: flex-end;
    }

    .ea-bubble-wrap.received {
        align-self: flex-start;
    }

    .ea-bubble {
        padding: 10px 14px 8px;
        border-radius: 14px;
        font-size: 14px;
        line-height: 1.45;
        word-break: break-word;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .ea-bubble-wrap.sent .ea-bubble {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        color: #ffffff;
        border-top-right-radius: 2px;
    }

    .ea-bubble-wrap.received .ea-bubble {
        background: #ffffff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-top-left-radius: 2px;
    }

    .ea-bubble-admin-tag {
        font-size: 9.5px;
        font-weight: 900;
        color: #d1fae5;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .ea-bubble-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        margin-top: 4px;
        font-size: 10.5px;
        color: #94a3b8;
    }

    .ea-bubble-wrap.sent .ea-bubble-meta {
        color: rgba(255, 255, 255, 0.8);
    }

    .ea-quick-replies-bar {
        background: #ffffff;
        padding: 8px 18px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        gap: 8px;
        align-items: center;
        overflow-x: auto;
        white-space: nowrap;
    }

    .ea-qr-label {
        font-size: 11px;
        font-weight: 800;
        color: #059669;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .ea-qr-pill {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s;
    }

    .ea-qr-pill:hover {
        background: #ecfdf5;
        border-color: #86efac;
        color: #047857;
    }

    .ea-input-bar {
        padding: 12px 18px;
        background: #ffffff;
        display: flex;
        align-items: center;
        gap: 10px;
        border-top: 1.5px solid #f1f5f9;
    }

    .ea-input-wrap {
        flex: 1;
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 9px 14px;
        display: flex;
        align-items: center;
        transition: border-color 0.2s;
    }

    .ea-input-wrap:focus-within {
        border-color: #10b981;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    }

    .ea-input-wrap input {
        border: none;
        background: transparent;
        width: 100%;
        font-size: 14px;
        color: #1e293b;
        outline: none;
    }

    .ea-send-btn {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        color: white;
        border: none;
        font-size: 15px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        transition: all 0.2s;
    }

    .ea-send-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(5, 150, 105, 0.4);
    }

    @media (max-width: 900px) {
        .wa-app-container {
            grid-template-columns: 1fr;
            height: auto;
        }
    }

    .user-card:hover {
        transform: translateY(-8px) scale(1.02);
        border-color: #8b5cf6;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        z-index: 10;
    }

    .premium-action-btn {
        flex: 1;
        border: none;
        padding: 12px;
        border-radius: 16px;
        font-size: 11px;
        font-weight: 900;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .reveal-btn {
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .reveal-btn:hover {
        background: #e2e8f0;
        color: #1e293b;
        transform: scale(1.05);
    }

    .listings-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
    }

    .listings-btn:hover {
        box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.4);
        transform: scale(1.05);
    }

    .reset-btn {
        background: #f5f3ff;
        color: #8b5cf6;
        border: 1px solid #ddd6fe;
    }

    .reset-btn:hover {
        background: #8b5cf6;
        color: white;
    }

    .delete-btn {
        background: #fff1f2;
        color: #ef4444;
        border: 1px solid #fecdd3;
    }

    .delete-btn:hover {
        background: #ef4444;
        color: white;
    }

    /* Custom Scrollbar for Dropdown */
    .user-ads-dropdown::-webkit-scrollbar {
        width: 4px;
    }

    .user-ads-dropdown::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    .user-ads-dropdown::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    /* Segment Buttons */
    .segment-btn {
        padding: 12px 24px;
        border-radius: 16px;
        border: 2px solid transparent;
        background: #f1f5f9;
        color: #64748b;
        font-weight: 800;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .segment-btn.active {
        background: #f5f3ff;
        color: #8b5cf6;
        border-color: #8b5cf6;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
    }

    .perm-badge {
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 4px 10px;
        border-radius: 8px;
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .perm-badge.full-access {
        background: #fdf2f8;
        color: #db2777;
        border-color: #fbcfe8;
    }

    /* Manage All Listings Styles */
    .manage-ads-header-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 20px 24px;
        border: 1.5px solid #e2e8f0;
        margin-bottom: 20px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.03);
    }
    .manage-ads-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
    }
    .manage-ads-search {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1.5px solid #cbd5e1;
        border-radius: 12px;
        padding: 8px 14px;
        width: 290px;
        transition: all 0.2s;
    }
    .manage-ads-search:focus-within {
        border-color: #059669;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(5,150,105,0.12);
    }
    .manage-ads-search input {
        border: none;
        background: transparent;
        outline: none;
        width: 100%;
        font-size: 13px;
        color: #1e293b;
    }
    .manage-ads-table-wrap {
        background: #ffffff;
        border-radius: 20px;
        border: 1.5px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.03);
    }
    .manage-ads-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    .manage-ads-table thead th {
        background: #f8fafc;
        padding: 14px 20px;
        font-size: 12px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1.5px solid #e2e8f0;
    }
    .manage-ads-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }
    .manage-ads-table tbody tr:hover {
        background: #f8fafc;
    }
    .manage-ads-table td {
        padding: 16px 20px;
        vertical-align: middle;
    }
    .btn-ad-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 13px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 750;
        cursor: pointer;
        border: 1.5px solid transparent;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        white-space: nowrap;
    }
    .btn-ad-action.btn-details {
        background: #f1f5f9;
        color: #334155;
        border-color: #cbd5e1;
    }
    .btn-ad-action.btn-details:hover {
        background: #e2e8f0;
        color: #0f172a;
        transform: translateY(-1px);
    }
    .btn-ad-action.btn-add-badge {
        background: #ecfdf5;
        color: #047857;
        border-color: #a7f3d0;
    }
    .btn-ad-action.btn-add-badge:hover {
        background: #059669;
        color: #ffffff;
        border-color: #059669;
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(5,150,105,0.25);
    }
    .btn-ad-action.btn-remove-badge {
        background: #fffbeb;
        color: #b45309;
        border-color: #fde68a;
    }
    .btn-ad-action.btn-remove-badge:hover {
        background: #f59e0b;
        color: #ffffff;
        border-color: #f59e0b;
        transform: translateY(-1px);
    }
    .btn-ad-action.btn-delete {
        background: #fee2e2;
        color: #b91c1c;
        border-color: #fecaca;
    }
    .btn-ad-action.btn-delete:hover {
        background: #dc2626;
        color: #ffffff;
        border-color: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(220,38,38,0.25);
    }

    .settings-container {
        max-width: 1000px;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 20px;
        margin-top: 24px;
    }

    .settings-card {
        background: var(--white);
        border-radius: 16px;
        padding: 24px;
        text-align: center;
        text-decoration: none;
        color: var(--text-dark);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid var(--border-color);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        box-shadow: var(--shadow-sm);
    }

    .settings-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-green);
    }

    .settings-card i {
        font-size: 24px;
        color: var(--primary-green);
        background: #f0fdf4;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .settings-card:hover i {
        background: var(--primary-green);
        color: var(--white);
    }

    .settings-card h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
    }

    .settings-card p {
        margin: 0;
        color: var(--text-muted);
        font-size: 11px;
    }

    .settings-section {
        display: none;
        animation: slideUp 0.4s ease-out;
    }

    .settings-section.active {
        display: block;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        color: var(--text-dark);
        text-decoration: none;
        font-weight: 700;
        margin-bottom: 24px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        background: var(--white);
        border: 1px solid var(--border-color);
        padding: 14px 24px;
        border-radius: 20px;
        font-size: 13px;
        box-shadow: var(--shadow-sm);
        text-transform: uppercase;
        letter-spacing: 1px;
        outline: none;
        -webkit-appearance: none;
        width: auto;
    }

    .back-btn:hover {
        color: white;
        border-color: var(--primary-green);
        background: var(--primary-green);
        transform: translateX(-8px);
        box-shadow: 0 15px 30px rgba(34, 197, 94, 0.3);
    }

    .back-btn i {
        font-size: 16px;
    }

    .settings-form-wrapper {
        background: var(--white);
        padding: 40px;
        border-radius: 24px;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
    }

    .section-header {
        margin-bottom: 32px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .section-header h2 {
        margin: 0;
        font-size: 22px;
        color: var(--text-dark);
    }

    .logo-preview-box {
        margin-bottom: 24px;
        background: #f8fafc;
        padding: 24px;
        border-radius: 16px;
        border: 2px dashed var(--border-color);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }

    .form-group-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .clear-field {
        font-size: 11px;
        color: #ef4444;
        cursor: pointer;
        font-weight: 600;
        text-transform: uppercase;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 4px;
        opacity: 0.7;
    }

    .clear-field:hover {
        opacity: 1;
        text-decoration: underline;
    }

    .form-divider {
        margin: 32px 0;
        border-top: 1px solid var(--border-color);
        position: relative;
        text-align: center;
    }

    .form-divider span {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: white;
        padding: 0 16px;
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Premium Alert Box */
    .alert-box {
        padding: 16px 20px;
        border-radius: 16px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* iOS Toggle Switch */
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 32px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .4s;
        border-radius: 32px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 24px;
        width: 24px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    input:checked+.slider {
        background-color: var(--primary-green);
    }

    input:checked+.slider:before {
        transform: translateX(28px);
    }

    .approval-status-label {
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
    }
</style>

<div class="settings-container">

    <!-- Messages -->
    <?php if ($error): ?>
        <div class="alert-box" style="background: #fee2e2; color: #dc2626; border-left: 5px solid #dc2626;">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-box" style="background: #f0fdf4; color: #16a34a; border-left: 5px solid #16a34a;">
            <i class="fa fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- Grid View -->
    <div id="grid-view"
        class="settings-section <?= empty($error) && empty($success) && !isset($_POST['action']) ? 'active' : '' ?>">
        <h1 style="margin-bottom: 8px; font-weight: 800; letter-spacing: -0.5px;">Platform Settings</h1>
        <p style="color: var(--text-muted); margin-bottom: 32px;">Configure your application's identity and security
            protocols.</p>

        <div class="settings-grid">
            <div class="settings-card" onclick="showSection('admin_post_ad')" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1.5px solid #86efac;">
                <i class="fa fa-plus-circle" style="background: var(--primary-green); color: white;"></i>
                <div>
                    <h3 style="color: var(--primary-green-dark);">Post Ad as Admin</h3>
                    <p style="color: var(--primary-green);">Create official verified ad</p>
                </div>
            </div>

            <div class="settings-card" onclick="showSection('admin_messages')" style="background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%); border: 1.5px solid #5eead4; position: relative;">
                <i class="fa fa-comments" style="background: #0d9488; color: white;"></i>
                <div>
                    <h3 style="color: #115e59;">Admin Ad Messages</h3>
                    <p style="color: #0d9488;">Inquiries on official ads</p>
                </div>
                <?php if ($admin_unread_count > 0): ?>
                    <span style="position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; border: 2px solid white; box-shadow: var(--shadow-sm);">
                        <?= $admin_unread_count ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (has_permission('manage_branding')): ?>
                <div class="settings-card" onclick="showSection('branding')">
                    <i class="fa fa-palette"></i>
                    <div>
                        <h3>Logo & Branding</h3>
                        <p>Customize visuals and assets</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('manage_announcements')): ?>
                <div class="settings-card" onclick="showSection('announcement')">
                    <i class="fa fa-bullhorn"></i>
                    <div>
                        <h3>Announcement</h3>
                        <p>Manage home page poster</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('manage_ads')): ?>
                <div class="settings-card" onclick="showSection('interstitial_ads')">
                    <i class="fa fa-photo-video"></i>
                    <div>
                        <h3>Interstitial Ads</h3>
                        <p>Manage full-screen ads</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('manage_security')): ?>
                <div class="settings-card" onclick="showSection('security')">
                    <i class="fa fa-key"></i>
                    <div>
                        <h3>Security Settings</h3>
                        <p>Manage admin access</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('manage_general')): ?>
                <div class="settings-card" onclick="showSection('general')">
                    <i class="fa fa-sliders"></i>
                    <div>
                        <h3>General Config</h3>
                        <p>Site name and global info</p>
                    </div>
                </div>

                <div class="settings-card" onclick="showSection('adult_content')">
                    <i class="fa fa-ban" style="color: #ef4444;"></i>
                    <div>
                        <h3>Adult Content</h3>
                        <p>Toggle verification logic</p>
                    </div>
                </div>

                <div class="settings-card" onclick="showSection('app_features')">
                    <i class="fa fa-sliders-h" style="color: #6366f1;"></i>
                    <div>
                        <h3>App Features</h3>
                        <p>Manage calls & background checks</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('manage_contact')): ?>
                <div class="settings-card" onclick="showSection('contact')">
                    <i class="fa fa-address-book"></i>
                    <div>
                        <h3>Contact & Social</h3>
                        <p>Support info and social links</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('manage_approval')): ?>
                <div class="settings-card" onclick="showSection('approval')">
                    <i class="fa fa-check-double"></i>
                    <div>
                        <h3>Approval Mode</h3>
                        <p>Manual vs Auto mode</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('manage_pending')): ?>
                <div class="settings-card" onclick="showSection('pending')" style="position: relative;">
                    <i class="fa fa-clock-rotate-left"></i>
                    <div>
                        <h3>Pending Ads</h3>
                        <p>Review new submissions</p>
                    </div>
                    <?php if (count($pending_products) > 0): ?>
                        <span
                            style="position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; border: 2px solid white; box-shadow: var(--shadow-sm);">
                            <?= count($pending_products) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (has_permission('manage_listings')): ?>
                <div class="settings-card" onclick="showSection('manage_ads')">
                    <i class="fa fa-list-check"></i>
                    <div>
                        <h3>Manage Ads</h3>
                        <p>Verified badge & status</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('manage_users')): ?>
                <div class="settings-card" onclick="showSection('users')">
                    <i class="fa fa-users-cog"></i>
                    <div>
                        <h3>User Management</h3>
                        <p>Manage users & privacy</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('*')): ?>
                <div class="settings-card" onclick="showSection('profile_verifications')" style="position: relative;">
                    <i class="fa fa-id-card" style="color: var(--primary-green);"></i>
                    <div>
                        <h3>Profile Verifications</h3>
                        <p>Approve or reject verification claims</p>
                    </div>
                    <?php if (count($pending_verifications) > 0): ?>
                        <span
                            style="position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; border: 2px solid white; box-shadow: var(--shadow-sm);">
                            <?= count($pending_verifications) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (($_SESSION['admin_permissions'] ?? '') === '*'): ?>
                <div class="settings-card" onclick="openCreateUserModal()"
                    style="background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%); border-color: #10b981;">
                    <i class="fa fa-user-plus" style="color: #10b981;"></i>
                    <div>
                        <h3 style="color: #065f46;">Provision Staff</h3>
                        <p style="color: #047857;">Onboard new accounts</p>
                    </div>
                </div>

                <div class="settings-card" onclick="showSection('privileges')"
                    style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border-color: #a855f7;">
                    <i class="fa fa-shield-alt" style="color: #a855f7;"></i>
                    <div>
                        <h3 style="color: #6b21a8;">Staff Privileges</h3>
                        <p style="color: #7e22ce;">Custom admin role items</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('*')): ?>
                <div class="settings-card" onclick="showSection('closure_feedback')">
                    <i class="fa fa-comments" style="color: #6366f1;"></i>
                    <div>
                        <h3>Closure Insights</h3>
                        <p>User feedback reasons</p>
                    </div>
                    <?php if (count($closure_feedback) > 0): ?>
                        <span
                            style="position: absolute; top: -10px; right: -10px; background: #6366f1; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; border: 2px solid white; box-shadow: var(--shadow-sm);">
                            <?= count($closure_feedback) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (has_permission('*')): ?>
                <div class="settings-card" onclick="showSection('search_delete_ads')" style="background: linear-gradient(135deg, #fff5f5 0%, #fff0f0 100%); border-color: var(--danger);">
                    <i class="fa fa-search-minus" style="color: var(--danger);"></i>
                    <div>
                        <h3>Delete Ads by Phone</h3>
                        <p>Search & bulk delete user ads</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (has_permission('*') || has_permission('manage_security') || has_permission('manage_general') || has_permission('manage_database_backup')): ?>
                <div class="settings-card" onclick="showSection('database_backup')" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1.5px solid #93c5fd;">
                    <i class="fa fa-database" style="background: #2563eb; color: white;"></i>
                    <div>
                        <h3 style="color: #1e40af;">Database Backup</h3>
                        <p style="color: #2563eb;">Full SQL Dump (db/enteangadi.sql)</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Post Ad as Admin Section -->
    <div id="section-admin_post_ad" class="settings-section <?= (isset($_POST['admin_post_ad']) && !empty($error)) ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')" style="margin-bottom: 24px;">
            <i class="fa fa-arrow-left"></i> Back to Platform Settings
        </button>

        <div class="settings-form-wrapper" style="max-width: 900px; padding: 36px; background: white; border-radius: 28px; border: 1.5px solid #e2e8f0; box-shadow: var(--shadow-sm);">
            <div class="section-header" style="margin-bottom: 28px; border-bottom: 2px solid #f1f5f9; padding-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0; color: #1e293b; font-weight: 800; font-size: 24px; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-bullhorn" style="color: var(--primary-green);"></i> Post Ad as Admin
                    </h2>
                    <p style="margin: 6px 0 0 0; color: #64748b; font-size: 14px;">
                        Ads published here are immediately <strong>Active</strong>, <strong>Verified</strong>, and feature an official <span style="background: #ecfdf5; color: #059669; font-weight: 800; padding: 2px 6px; border-radius: 4px; font-size: 11px;">POSTED BY ADMIN</span> badge across all pages.
                    </p>
                </div>
                <span style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white; padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-shield-alt"></i> Official Platform Ad
                </span>
            </div>

            <form method="POST" action="settings.php" enctype="multipart/form-data" id="adminPostAdForm" style="display: grid; gap: 24px;">
                <!-- 1. Ad Type Selector -->
                <div>
                    <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; display: block;">Listing Type *</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                        <label style="cursor: pointer;">
                            <input type="radio" name="type" value="sell" checked style="display: none;" onchange="adminUpdateTypeSelection(this)">
                            <div class="admin-type-chip active" id="chip-type-sell" style="padding: 14px; text-align: center; border-radius: 16px; border: 2px solid var(--primary-green); background: #f0fdf4; font-weight: 700; color: var(--primary-green-dark); transition: all 0.2s;">
                                <i class="fa fa-tag" style="margin-right: 6px;"></i> For Sale
                            </div>
                        </label>
                        <label style="cursor: pointer;">
                            <input type="radio" name="type" value="rent" style="display: none;" onchange="adminUpdateTypeSelection(this)">
                            <div class="admin-type-chip" id="chip-type-rent" style="padding: 14px; text-align: center; border-radius: 16px; border: 2px solid #e2e8f0; background: #f8fafc; font-weight: 700; color: #64748b; transition: all 0.2s;">
                                <i class="fa fa-key" style="margin-right: 6px;"></i> For Rent
                            </div>
                        </label>
                        <label style="cursor: pointer;">
                            <input type="radio" name="type" value="buy" style="display: none;" onchange="adminUpdateTypeSelection(this)">
                            <div class="admin-type-chip" id="chip-type-buy" style="padding: 14px; text-align: center; border-radius: 16px; border: 2px solid #e2e8f0; background: #f8fafc; font-weight: 700; color: #64748b; transition: all 0.2s;">
                                <i class="fa fa-search" style="margin-right: 6px;"></i> Wanted / Looking For
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 2. Categories -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">Primary Category *</label>
                        <select id="admin_parent_cat_select" class="form-control" style="width: 100%; padding: 12px 14px; border-radius: 14px; border: 1.5px solid #cbd5e1; font-weight: 600; font-size: 14px;" onchange="adminOnParentCatChange(this.value)" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($admin_l1_cats as $p_cat): ?>
                                <option value="<?= $p_cat['id'] ?>"><?= htmlspecialchars($p_cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">Subcategory *</label>
                        <select id="admin_sub_cat_select" name="category_id" class="form-control" style="width: 100%; padding: 12px 14px; border-radius: 14px; border: 1.5px solid #cbd5e1; font-weight: 600; font-size: 14px;" onchange="adminOnSubCatChange(this.value)" required>
                            <option value="">-- Select Primary First --</option>
                        </select>
                    </div>
                </div>

                <!-- 3. Ad Title -->
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">Ad Title *</label>
                        <span id="admin_title_char_count" style="font-size: 11px; color: #94a3b8; font-weight: 600;">0/70</span>
                    </div>
                    <input type="text" name="title" id="admin_ad_title" class="form-control" required maxlength="70" placeholder="e.g. 2022 Honda City ZX Petrol Automatic / Fresh Organic Farm Vegetables" style="width: 100%; padding: 12px 16px; border-radius: 14px; border: 1.5px solid #cbd5e1; font-size: 15px; font-weight: 600;" oninput="document.getElementById('admin_title_char_count').innerText = this.value.length + '/70'">
                </div>

                <!-- 4. Price & Expiry Date -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">Price / Budget (₹ INR) *</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-weight: 800; color: #64748b; font-size: 16px;">₹</span>
                            <input type="number" name="price" class="form-control" required min="0" step="any" placeholder="0" style="width: 100%; padding: 12px 16px 12px 34px; border-radius: 14px; border: 1.5px solid #cbd5e1; font-size: 15px; font-weight: 700;">
                        </div>
                    </div>

                    <div class="form-group" id="admin_expiry_wrapper">
                        <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">
                            Expiry Date <span id="admin_perishable_badge" style="display:none; color: #ef4444; font-size: 10px; text-transform: uppercase; font-weight: 800;">(Required for Perishable)</span>
                        </label>
                        <input type="date" name="expiry_date" id="admin_expiry_date" class="form-control" min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 12px 16px; border-radius: 14px; border: 1.5px solid #cbd5e1; font-size: 14px;">
                    </div>
                </div>

                <!-- 5. Location -->
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">Location *</label>
                        <button type="button" onclick="adminAutoDetectLocation()" style="background: #f0fdf4; border: 1px solid #86efac; color: var(--primary-green-dark); padding: 4px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                            <i class="fa fa-crosshairs"></i> Auto-Detect My GPS
                        </button>
                    </div>
                    <input type="text" name="location_name" id="admin_location_name" class="form-control" required placeholder="e.g. Edappally, Kochi, Kerala" style="width: 100%; padding: 12px 16px; border-radius: 14px; border: 1.5px solid #cbd5e1; font-size: 14px;">
                    <input type="hidden" name="latitude" id="admin_lat">
                    <input type="hidden" name="longitude" id="admin_lng">
                    <p id="admin_loc_status" style="font-size: 11px; color: #64748b; margin: 4px 0 0 0;"></p>
                </div>

                <!-- 6. Contact Information -->
                <div style="background: #f8fafc; padding: 20px; border-radius: 18px; border: 1px solid #e2e8f0;">
                    <div style="font-weight: 800; font-size: 13px; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px;">Contact Options</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px; font-weight: 700; font-size: 13px; color: #15803d;">
                                <input type="checkbox" name="contact_whatsapp" id="admin_chk_whatsapp" value="1" checked onchange="document.getElementById('admin_whatsapp_num').disabled = !this.checked">
                                <i class="fab fa-whatsapp" style="font-size: 16px;"></i> Show WhatsApp Number
                            </label>
                            <input type="text" name="whatsapp_number" id="admin_whatsapp_num" class="form-control" placeholder="e.g. 9876543210" style="width: 100%; padding: 10px 14px; border-radius: 12px; border: 1.5px solid #cbd5e1;">
                        </div>
                        <div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px; font-weight: 700; font-size: 13px; color: #0284c7;">
                                <input type="checkbox" name="contact_phone" id="admin_chk_phone" value="1" checked onchange="document.getElementById('admin_phone_num').disabled = !this.checked">
                                <i class="fa fa-phone-alt" style="font-size: 14px;"></i> Show Phone Call Number
                            </label>
                            <input type="text" name="phone_number" id="admin_phone_num" class="form-control" placeholder="e.g. 9876543210" style="width: 100%; padding: 10px 14px; border-radius: 12px; border: 1.5px solid #cbd5e1;">
                        </div>
                    </div>
                </div>

                <!-- 7. Description -->
                <div class="form-group">
                    <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">Description</label>
                    <textarea name="description" id="admin_ad_desc" class="form-control" rows="5" placeholder="Provide complete specifications, features, condition, or special terms..." style="width: 100%; padding: 14px 16px; border-radius: 16px; border: 1.5px solid #cbd5e1; font-size: 14px; line-height: 1.5;"></textarea>
                </div>

                <!-- 8. Image Uploads with Live Previews -->
                <div class="form-group">
                    <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">Product Photos (Up to 10)</label>
                    <div style="border: 2px dashed #cbd5e1; padding: 24px; border-radius: 18px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('admin_ad_images').click()">
                        <i class="fa fa-cloud-arrow-up" style="font-size: 36px; color: var(--primary-green); margin-bottom: 8px;"></i>
                        <div style="font-weight: 700; color: #1e293b; font-size: 15px;">Click to select photos</div>
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">PNG, JPG, JPEG or WEBP (Cover image selectable below)</div>
                        <input type="file" id="admin_ad_images" name="images[]" multiple accept="image/*" style="display: none;" onchange="adminHandleImagePreview(this)">
                    </div>

                    <input type="hidden" name="cover_index" id="admin_cover_index" value="0">
                    <div id="admin_image_preview_grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; margin-top: 16px;"></div>
                </div>

                <!-- Submit Button -->
                <button type="submit" name="admin_post_ad" class="btn-primary" style="padding: 16px; font-size: 16px; font-weight: 800; border-radius: 18px; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; box-shadow: 0 10px 20px rgba(34, 197, 94, 0.25); cursor: pointer;">
                    <i class="fa fa-paper-plane"></i> Publish Ad as Admin (Live & Verified)
                </button>
            </form>
        </div>
    </div>

    <!-- Admin Ad Inquiries Section (Enteangadi Official Messenger) -->
    <div id="section-admin_messages" class="settings-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-shrink: 0; flex-wrap: wrap; gap: 12px;">
            <button class="back-btn" onclick="showSection('grid')" style="margin-bottom: 0;">
                <i class="fa fa-arrow-left"></i> Back to Platform Settings
            </button>
            <div style="display: flex; gap: 10px; align-items: center;">
                <span style="background: #ecfdf5; color: #047857; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 800; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-shield-alt" style="font-size: 13px; color: #059669;"></i> Enteangadi Live Inquiries
                </span>
                <?php if ($admin_unread_count > 0): ?>
                    <span id="adminHeaderUnreadBadge" style="background: #fee2e2; color: #b91c1c; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 800; border: 1px solid #fca5a5;">
                        <?= $admin_unread_count ?> Unread
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="ea-messenger-container">
            <!-- Left Sidebar -->
            <div class="ea-messenger-sidebar">
                <!-- Sidebar Header -->
                <div class="ea-sidebar-header">
                    <div class="ea-sidebar-title">
                        <i class="fa fa-comments" style="color: #059669;"></i> Customer Inquiries
                    </div>
                    <div class="ea-sidebar-actions">
                        <button type="button" class="ea-icon-btn" title="Refresh Inquiries" onclick="loadAdminChatThread(true)">
                            <i class="fa fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="ea-search-wrapper">
                    <div class="ea-search-bar">
                        <i class="fa fa-search"></i>
                        <input type="text" id="eaChatSearch" placeholder="Search user or ad title..." onkeyup="filterEaConversations(this.value)">
                    </div>
                </div>

                <!-- Filter Chips Row -->
                <div class="ea-filter-chips">
                    <div class="ea-filter-chip active" id="chip-all" onclick="setAdminChatFilter('all')">
                        All
                    </div>
                    <div class="ea-filter-chip" id="chip-unread" onclick="setAdminChatFilter('unread')">
                        Unread <?php if ($admin_unread_count > 0): ?><span class="ea-chip-badge" id="eaUnreadChipCount"><?= $admin_unread_count ?></span><?php endif; ?>
                    </div>
                    <div class="ea-filter-chip" id="chip-verified" onclick="setAdminChatFilter('verified')">
                        <i class="fa fa-check-circle" style="color: #10b981; font-size: 11px;"></i> Verified
                    </div>
                </div>

                <!-- Chat Conversations List with Permanent Visible Scrollbar -->
                <div class="ea-chats-list" id="eaConversationsList">
                    <?php if (empty($admin_conversations)): ?>
                        <div class="ea-no-chats">
                            <i class="fa fa-inbox"></i>
                            <h4 style="margin: 0 0 4px; color: #1e293b; font-size: 15px;">No Inquiries Yet</h4>
                            <p style="margin: 0; font-size: 12.5px; line-height: 1.4;">When buyers ask questions on your admin-posted ads, their messages will appear here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($admin_conversations as $conv): ?>
                            <?php 
                            $is_unr = ($conv['unread_count'] > 0);
                            $last_txt = $conv['last_message'] ?? '';
                            $is_last_admin = ($conv['last_sender_id'] == $current_admin_id);
                            ?>
                            <div class="ea-chat-item <?= $is_unr ? 'is-unread' : '' ?>" 
                                 id="ea-conv-<?= $conv['product_id'] ?>-<?= $conv['user_id'] ?>" 
                                 data-product-id="<?= $conv['product_id'] ?>" 
                                 data-user-id="<?= $conv['user_id'] ?>" 
                                 data-unread="<?= (int)$conv['unread_count'] ?>"
                                 data-verified="<?= !empty($conv['user_is_verified']) ? '1' : '0' ?>"
                                 data-search="<?= strtolower(htmlspecialchars($conv['username'] . ' ' . $conv['product_title'] . ' ' . ($conv['phone_number'] ?? ''))) ?>" 
                                 onclick="openAdminChat(<?= $conv['product_id'] ?>, <?= $conv['user_id'] ?>)">
                                
                                <!-- User Avatar -->
                                <div class="ea-avatar-wrap">
                                    <?php if (!empty($conv['profile_picture']) && file_exists('../' . $conv['profile_picture'])): ?>
                                        <img src="../<?= htmlspecialchars($conv['profile_picture']) ?>" class="ea-avatar" alt="Avatar">
                                    <?php else: ?>
                                        <div class="ea-avatar-initials">
                                            <?= strtoupper(substr($conv['username'] ?? 'U', 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Chat Summary Info -->
                                <div class="ea-chat-content">
                                    <div class="ea-chat-top-row">
                                        <div class="ea-chat-name">
                                            <?= htmlspecialchars($conv['username']) ?>
                                            <?php if (!empty($conv['user_is_verified'])): ?>
                                                <i class="fa fa-check-circle ea-verified-icon" title="Verified Customer"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ea-chat-time">
                                            <?= !empty($conv['last_message_time']) ? date('g:i a', strtotime($conv['last_message_time'])) : '' ?>
                                        </div>
                                    </div>

                                    <div class="ea-chat-ad-pill">
                                        <i class="fa fa-tag" style="font-size: 10px;"></i> <?= htmlspecialchars($conv['product_title']) ?> · ₹<?= htmlspecialchars($conv['product_price']) ?>
                                    </div>

                                    <div class="ea-chat-bottom-row">
                                        <div class="ea-chat-preview">
                                            <?php if ($is_last_admin): ?>
                                                <i class="fa fa-check-double" style="color: #059669; font-size: 11px;"></i>
                                            <?php endif; ?>
                                            <?php 
                                            if (strpos($last_txt, '[AUDIO]:') === 0) {
                                                echo '<i class="fa fa-microphone" style="color:#059669;"></i> Voice Message';
                                            } elseif (strpos($last_txt, '[IMAGE]:') === 0) {
                                                echo '<i class="fa fa-camera" style="color:#059669;"></i> Photo Attachment';
                                            } else {
                                                echo htmlspecialchars($last_txt ?: 'No messages yet');
                                            }
                                            ?>
                                        </div>

                                        <?php if ($is_unr): ?>
                                            <span class="ea-unread-badge" id="eaItemBadge-<?= $conv['product_id'] ?>-<?= $conv['user_id'] ?>">
                                                <?= $conv['unread_count'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Main Pane -->
            <div class="ea-main-pane">
                <!-- Enteangadi Messenger Empty State -->
                <div class="ea-empty-state" id="adminChatEmptyState">
                    <div class="ea-empty-icon-circle">
                        <i class="fa fa-comments"></i>
                    </div>
                    <h2>Enteangadi Live Ad Support</h2>
                    <p>Manage and reply to customer inquiries on your official marketplace listings in real-time with instant responses.</p>
                    
                    <div class="ea-empty-actions">
                        <div class="ea-empty-action-card" onclick="showSection('manage_ads')">
                            <i class="fa fa-store"></i>
                            <span>Manage Ads</span>
                        </div>
                        <div class="ea-empty-action-card" onclick="showSection('users')">
                            <i class="fa fa-users"></i>
                            <span>User Profiles</span>
                        </div>
                        <div class="ea-empty-action-card" onclick="document.getElementById('eaChatSearch').focus()">
                            <i class="fa fa-search"></i>
                            <span>Search Chats</span>
                        </div>
                    </div>

                    <div style="font-size: 12.5px; color: #64748b; display: flex; align-items: center; gap: 6px; background: #f8fafc; padding: 6px 16px; border-radius: 20px; border: 1px solid #e2e8f0;">
                        <i class="fa fa-shield-alt" style="color: #059669;"></i> Enteangadi Secure Official Platform Messenger
                    </div>
                </div>

                <!-- Active Chat Room Box -->
                <div class="ea-active-room" id="adminChatActiveBox" style="display: none;">
                    <!-- Top Room Header -->
                    <div class="ea-room-header">
                        <div class="ea-room-user-info">
                            <div class="ea-avatar-wrap">
                                <img id="activeChatCustomerAvatar" src="" class="ea-avatar" alt="Avatar" style="display: none;" onerror="this.style.display='none'; document.getElementById('activeChatCustomerInitials').style.display='flex';">
                                <div id="activeChatCustomerInitials" class="ea-avatar-initials" style="display: flex;">U</div>
                            </div>
                            <div>
                                <div class="ea-room-user-name">
                                    <span id="activeChatCustomerName"></span>
                                    <span class="ea-room-price-badge" id="activeChatProductPrice"></span>
                                </div>
                                <div class="ea-room-subtext">
                                    <span class="ea-room-ad-title" id="activeChatProductTitle"></span>
                                    <span id="activeChatCustomerPhone"></span>
                                </div>
                            </div>
                        </div>

                        <div class="ea-room-actions">
                            <a id="activeChatProductLink" href="#" target="_blank" class="ea-top-btn" title="View product ad page">
                                <i class="fa fa-external-link-alt"></i> View Ad
                            </a>
                            <button type="button" onclick="deleteActiveAdminChat()" class="ea-top-btn ea-delete-btn" title="Delete conversation">
                                <i class="fa fa-trash-alt"></i> Delete
                            </button>
                        </div>
                    </div>

                    <!-- Messages Thread Body with Permanent Visible Scrollbar -->
                    <div class="ea-messages-body" id="adminChatMessagesBox">
                        <!-- Injected dynamically -->
                    </div>

                    <!-- Quick Replies Bar -->
                    <div class="ea-quick-replies-bar">
                        <div class="ea-qr-label"><i class="fa fa-bolt"></i> Quick:</div>
                        <button type="button" class="ea-qr-pill" onclick="insertAdminQuickReply('Yes, this item is available in stock.')">In Stock</button>
                        <button type="button" class="ea-qr-pill" onclick="insertAdminQuickReply('The listed price is fixed as official.')">Fixed Price</button>
                        <button type="button" class="ea-qr-pill" onclick="insertAdminQuickReply('Please share your delivery address and contact number to proceed.')">Request Details</button>
                        <button type="button" class="ea-qr-pill" onclick="insertAdminQuickReply('Thank you for contacting the Enteangadi Support Team!')">Thank You</button>
                    </div>

                    <!-- Bottom Input Bar -->
                    <div class="ea-input-bar">
                        <button type="button" class="ea-icon-btn" title="Emoji" onclick="insertAdminQuickReply('👍')"><i class="far fa-smile"></i></button>
                        <div class="ea-input-wrap">
                            <input type="text" id="adminReplyText" placeholder="Type official response as Admin..." onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendAdminReply(); }">
                        </div>
                        <button type="button" class="ea-send-btn" id="adminSendReplyBtn" onclick="sendAdminReply()" title="Send reply">
                            <i class="fa fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (($_SESSION['admin_permissions'] ?? '') === '*'): ?>
        <!-- Staff Privileges Section -->
        <div id="section-privileges"
            class="settings-section <?= (isset($_POST['action']) && ($_POST['action'] === 'add_custom_permission' || $_POST['action'] === 'delete_custom_permission')) ? 'active' : '' ?>">
            <button class="back-btn" onclick="showSection('grid')" style="margin-bottom: 24px;">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </button>

            <div class="settings-form-wrapper"
                style="max-width: 1000px; padding: 32px; background: white; border-radius: 32px; border: 1.5px solid #e2e8f0; box-shadow: var(--shadow-sm);">
                <div class="section-header"
                    style="margin-bottom: 28px; border-bottom: 2px solid #f1f5f9; padding-bottom: 16px;">
                    <h2 style="margin: 0; color: #1e293b; font-weight: 800; font-size: 24px;">Configure Admin Privileges
                    </h2>
                    <p style="margin: 4px 0 0 0; color: #64748b; font-size: 14px;">Add or remove custom feature access keys
                        that populate in the System Administrator checkboxes.</p>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 36px;">
                    <!-- Left panel: Add new privilege -->
                    <div style="background: #faf5ff; padding: 28px; border-radius: 24px; border: 1.5px solid #f3e8ff;">
                        <h3
                            style="margin-top: 0; margin-bottom: 20px; color: #6b21a8; font-weight: 800; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-plus-circle"></i> Add Custom Privilege
                        </h3>
                        <form method="POST" action="settings.php" style="display: grid; gap: 20px;">
                            <input type="hidden" name="action" value="add_custom_permission">

                            <div class="form-group">
                                <label
                                    style="font-size: 11px; font-weight: 800; color: #7e22ce; text-transform: uppercase; margin-bottom: 8px; display: block;">Select
                                    Privilege Key (Unique ID)</label>
                                <select id="perm_key_select" onchange="handlePrivilegeSelect(this.value)"
                                    style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #d8b4fe; font-size: 15px; background: white; cursor: pointer; margin-bottom: 12px; font-weight: 700; color: #1e293b;">
                                    <option value="">-- Choose Settings Feature Key --</option>
                                    <option value="manage_users" data-label="User Management / Onboard Staff">manage_users
                                        (User Management / Onboard Staff)</option>
                                    <option value="manage_pending" data-label="Pending Ads Review">manage_pending (Pending
                                        Ads Review)</option>
                                    <option value="manage_listings" data-label="Active Ads / Manage Ads">manage_listings
                                        (Active Ads / Manage Ads)</option>
                                    <option value="manage_announcements" data-label="Announcement Poster">
                                        manage_announcements
                                        (Announcement Poster)</option>
                                    <option value="manage_branding" data-label="Visual Identity (Branding)">manage_branding
                                        (Visual Identity (Branding))</option>
                                    <option value="manage_general" data-label="General Config">manage_general (General
                                        Config)</option>
                                    <option value="manage_contact" data-label="Contact & Social Media">manage_contact
                                        (Contact & Social Media)</option>
                                    <option value="manage_approval" data-label="Ad Approval Mode Settings">manage_approval
                                        (Ad Approval Mode Settings)</option>
                                    <option value="manage_ads" data-label="Sponsored Ads (Interstitial Queue)">manage_ads
                                        (Sponsored Ads (Interstitial Queue))</option>
                                    <option value="manage_closure" data-label="Closure Insights (User Feedback)">
                                        manage_closure (Closure Insights (User Feedback))</option>
                                    <option value="custom">Other (Create Custom Key...)</option>
                                </select>

                                <div id="custom_key_wrapper" style="display: none;">
                                    <input type="text" id="perm_key_input" name="perm_key"
                                        placeholder="Enter custom unique key (e.g. manage_billing)" required
                                        style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #d8b4fe; font-size: 15px; background: white;">
                                    <small
                                        style="color: #8b5cf6; display: block; margin-top: 6px; font-weight: 600;">Lowercase
                                        alphanumeric and underscores only.</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label
                                    style="font-size: 11px; font-weight: 800; color: #7e22ce; text-transform: uppercase; margin-bottom: 8px; display: block;">Display
                                    Label</label>
                                <input type="text" id="perm_label_input" name="perm_label"
                                    placeholder="e.g. Interstitial Ads" required
                                    style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #d8b4fe; font-size: 15px; background: white;">
                            </div>

                            <button type="submit"
                                style="width: 100%; padding: 16px; border-radius: 18px; border: none; background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); color: white; font-weight: 800; font-size: 15px; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(168,85,247,0.3); margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="fa fa-shield-alt"></i> ADD PRIVILEGE
                            </button>
                        </form>
                    </div>

                    <!-- Right panel: Active Privileges list -->
                    <div
                        style="background: white; padding: 28px; border-radius: 24px; border: 1.5px solid #e2e8f0; display: flex; flex-direction: column; max-height: 480px;">
                        <h3
                            style="margin-top: 0; margin-bottom: 16px; color: #0f172a; font-weight: 800; font-size: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-list-ul"></i> Active Privilege Checklist
                        </h3>
                        <div style="overflow-y: auto; flex: 1; display: grid; gap: 12px; padding-right: 4px;">
                            <?php foreach ($available_permissions as $perm): ?>
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-radius: 16px; background: #f8fafc; border: 1px solid #e2e8f0;">
                                    <div>
                                        <span
                                            style="font-size: 14px; font-weight: 700; color: #1e293b; display: block;"><?= htmlspecialchars($perm['perm_label']) ?></span>
                                        <code
                                            style="font-size: 11px; color: #a855f7; font-weight: 700; font-family: 'Courier New', monospace;"><?= htmlspecialchars($perm['perm_key']) ?></code>
                                    </div>
                                    <!-- Delete action -->
                                    <form method="POST" action="settings.php"
                                        onsubmit="return confirm('Are you sure you want to delete this privilege? Current staff with this permission will retain access unless their profile is updated.');"
                                        style="margin: 0;">
                                        <input type="hidden" name="action" value="delete_custom_permission">
                                        <input type="hidden" name="perm_id" value="<?= $perm['id'] ?>">
                                        <button type="submit"
                                            style="background: #fef2f2; border: none; width: 38px; height: 38px; border-radius: 12px; color: #ef4444; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;"
                                            title="Remove Privilege">
                                            <i class="fa fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Branding Section -->
    <div id="section-branding"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'branding') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>Visual Identity</h2>
            </div>

            <form method="POST" action="settings.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="branding">

                <div class="form-group">
                    <label>Platform Logo</label>
                    <div class="logo-preview-box">
                        <?php if (!empty($app_settings['app_logo'])): 
                            $logo_disk = '../' . $app_settings['app_logo'];
                            $logo_v = file_exists($logo_disk) ? filemtime($logo_disk) : time();
                        ?>
                            <img src="../<?= htmlspecialchars($app_settings['app_logo']) ?>?v=<?= $logo_v ?>" alt="Current Logo"
                                style="max-height: 80px; object-fit: contain; background: transparent !important;">
                            <label
                                style="display: flex; align-items: center; gap: 8px; color: #dc2626; cursor: pointer; font-size: 13px; font-weight: 600; margin-top: 12px;">
                                <input type="checkbox" name="delete_logo" value="1"> Remove current logo
                            </label>
                        <?php else: ?>
                            <i class="fa fa-image" style="font-size: 40px; color: #cbd5e1;"></i>
                            <span style="color: var(--text-muted); font-size: 13px;">No logo set</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="app_logo" class="form-control" accept="image/*">
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <div class="form-group-header">
                        <label>Tagline / Motto</label>
                        <span class="clear-field" onclick="clearField('app_tagline')"><i class="fa fa-times-circle"></i>
                            Clear</span>
                    </div>
                    <input type="text" id="app_tagline" name="app_tagline" class="form-control"
                        value="<?= htmlspecialchars($app_settings['app_tagline'] ?? '') ?>"
                        placeholder="e.g. Your Local Marketplace">
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Save
                    Visual Changes</button>
            </form>
        </div>
    </div>

    <!-- Announcement Section -->
    <div id="section-announcement"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'announcement') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>Announcement Poster</h2>
                <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">Upload a poster to show on the
                    index page (Shown once per day per user).</p>
            </div>

            <form method="POST" action="settings.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="announcement">

                <div class="form-group">
                    <label>Active Poster Preview</label>
                    <div class="logo-preview-box" style="height: auto; min-height: 200px;">
                        <?php if (!empty($app_settings['announcement_poster'])): 
                            $poster_disk = '../' . $app_settings['announcement_poster'];
                            $poster_v = file_exists($poster_disk) ? filemtime($poster_disk) : time();
                        ?>
                            <img src="../<?= htmlspecialchars($app_settings['announcement_poster']) ?>?v=<?= $poster_v ?>" alt="Current Poster"
                                style="max-width: 100%; border-radius: 12px; box-shadow: var(--shadow-sm);">
                            <label
                                style="display: flex; align-items: center; gap: 8px; color: #dc2626; cursor: pointer; font-size: 13px; font-weight: 600; margin-top: 12px;">
                                <input type="checkbox" name="delete_poster" value="1"> Remove and disable poster
                            </label>
                        <?php else: ?>
                            <i class="fa fa-bullhorn" style="font-size: 40px; color: #cbd5e1;"></i>
                            <span style="color: var(--text-muted); font-size: 13px;">No announcement poster active</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <label>Upload New Poster</label>
                    <input type="file" name="announcement_poster" class="form-control" accept="image/*">
                    <small style="color: var(--text-muted); display: block; margin-top: 8px;">Recommended: High
                        resolution image. Mobile responsive cropping will apply.</small>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Update
                    Announcement Poster</button>
            </form>
        </div>
    </div>

    <!-- Interstitial Ads Section -->
    <div id="section-interstitial_ads"
        class="settings-section <?= (isset($_POST['action']) && ($_POST['action'] === 'interstitial_ads' || $_POST['action'] === 'delete_interstitial_ad')) ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper" style="max-width: 1200px;">
            <div class="section-header">
                <h2>Full-Screen Interstitial Ads Playlist</h2>
                <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">Publish multiple image or
                    video advertisements. They will be displayed to users sequentially in a circular rotation.</p>
            </div>

            <!-- Two-column layouts grid -->
            <div class="interstitial-grid-container"
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 24px; align-items: start;">

                <!-- Left panel: config & upload -->
                <div class="interstitial-panel-left"
                    style="background: #ffffff; padding: 24px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                    <h3
                        style="font-size: 16px; font-weight: 700; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-color);">
                        <i class="fa fa-plus-circle" style="color: var(--primary-green);"></i> Add New Ad / Edit
                        Config
                    </h3>

                    <form method="POST" action="settings.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="interstitial_ads">

                        <div class="form-group"
                            style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                            <div>
                                <label
                                    style="font-weight: 700; margin-bottom: 2px; display: block; cursor: pointer; font-size: 13px;">Enable
                                    Interstitial Ads</label>
                                <span style="font-size: 11px; color: var(--text-muted);">Toggle ads globally.</span>
                            </div>
                            <label class="switch" style="transform: scale(0.9);">
                                <input type="checkbox" name="interstitial_ad_active" value="1"
                                    <?= ($app_settings['interstitial_ad_active'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-size: 13px; font-weight: 700;">Page Views Threshold</label>
                            <input type="number" name="interstitial_ad_frequency" class="form-control" min="1" max="100"
                                style="padding: 10px;"
                                value="<?= htmlspecialchars($app_settings['interstitial_ad_frequency'] ?? '10') ?>">
                            <small
                                style="color: var(--text-muted); display: block; margin-top: 4px; font-size: 11px;">If
                                set to 10, the ad displays ONLY on the 10th page view (and resets to repeat every 10
                                pages).</small>
                        </div>

                        <div style="margin-top: 24px; border-top: 1px dashed var(--border-color); padding-top: 20px;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-size: 13px; font-weight: 700;">Publish Ad File (Image / MP4
                                    Video)</label>
                                <input type="file" name="interstitial_ad_file" class="form-control"
                                    accept="image/*,video/*" style="padding: 8px;">
                                <small
                                    style="color: var(--text-muted); display: block; margin-top: 4px; font-size: 11px;">Select
                                    a premium photo or video clip to publish to the active playlist
                                    rotation.</small>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-size: 13px; font-weight: 700;">Redirect Target URL</label>
                                <input type="url" name="interstitial_ad_link" class="form-control"
                                    style="padding: 10px;" placeholder="https://example.com/promo or internal link">
                                <small
                                    style="color: var(--text-muted); display: block; margin-top: 4px; font-size: 11px;">Specify
                                    where the user is redirected when tapping this ad.</small>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-size: 13px; font-weight: 700;">Countdown Wait Time
                                    (Seconds)</label>
                                <input type="number" name="interstitial_ad_duration" class="form-control" min="0"
                                    max="60" style="padding: 10px;" value="5">
                                <small
                                    style="color: var(--text-muted); display: block; margin-top: 4px; font-size: 11px;">Number
                                    of seconds the user must watch before the skip/close button unlocks.</small>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary"
                            style="margin-top: 24px; width: 100%; padding: 12px; font-size: 14px; font-weight: 700;">Update
                            Settings & Add to Queue</button>
                    </form>
                </div>

                <!-- Right panel: playlist queue -->
                <div class="interstitial-panel-right"
                    style="background: #ffffff; padding: 24px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); min-height: 450px;">
                    <h3
                        style="font-size: 16px; font-weight: 700; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; color: var(--text-color);">
                        <i class="fa fa-list" style="color: var(--primary-green);"></i> Active Playlist Queue
                        (<?= count($active_ads) ?> ads)
                    </h3>

                    <?php if (empty($active_ads)): ?>
                        <div
                            style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; color: var(--text-muted); border: 2px dashed var(--border-color); border-radius: 12px; background: #fdfdfd; padding: 20px; text-align: center;">
                            <i class="fa fa-photo-video" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
                            <span style="font-weight: 600; font-size: 14px; color: #64748b;">No Ads Published Yet</span>
                            <span style="font-size: 12px; color: #94a3b8; max-width: 250px; margin-top: 6px;">Upload
                                your first video or image ad on the left to start the rotation playlist!</span>
                        </div>
                    <?php else: ?>
                        <div class="interstitial-playlist-roster"
                            style="display: flex; flex-direction: column; gap: 16px; max-height: 520px; overflow-y: auto; padding-right: 4px;">
                            <?php
                            $idx = 0;
                            foreach ($active_ads as $ad):
                                $idx++;
                                ?>
                                <div class="playlist-item"
                                    style="display: flex; gap: 16px; align-items: center; background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid var(--border-color); position: relative; transition: all 0.3s ease;">
                                    <div class="ad-order-badge"
                                        style="position: absolute; top: -8px; left: -8px; background: var(--primary-green); color: white; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; border: 2px solid white; box-shadow: var(--shadow-sm);">
                                        <?= $idx ?>
                                    </div>

                                    <!-- Ad thumbnail -->
                                    <div
                                        style="width: 80px; height: 60px; border-radius: 8px; overflow: hidden; background: #000; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php if ($ad['media_type'] === 'video'): ?>
                                            <video src="../<?= htmlspecialchars($ad['media_file']) ?>"
                                                style="width: 100%; height: 100%; object-fit: cover;" muted autoplay loop
                                                playsinline></video>
                                        <?php else: ?>
                                            <img src="../<?= htmlspecialchars($ad['media_file']) ?>"
                                                style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php endif; ?>
                                    </div>

                                    <!-- Ad details -->
                                    <div style="flex-grow: 1; min-width: 0;">
                                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                                            <span
                                                style="font-size: 11px; font-weight: 800; text-transform: uppercase; background: <?= $ad['media_type'] === 'video' ? '#dbeafe; color: #1e40af;' : '#dcfce7; color: #166534;' ?>; padding: 2px 6px; border-radius: 4px; letter-spacing: 0.5px;"><?= htmlspecialchars($ad['media_type']) ?></span>
                                            <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;"><i
                                                    class="fa fa-clock"></i> <?= htmlspecialchars($ad['duration']) ?>s
                                                wait</span>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-color); font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                            title="<?= htmlspecialchars($ad['link_url']) ?>">
                                            <i class="fa fa-link" style="color: #94a3b8;"></i>
                                            <?= !empty($ad['link_url']) ? htmlspecialchars($ad['link_url']) : '<span style="color: #cbd5e1; font-weight:500;">No Redirect URL</span>' ?>
                                        </div>
                                    </div>

                                    <!-- Delete action -->
                                    <form method="POST" action="settings.php"
                                        onsubmit="return confirm('Are you sure you want to remove this ad from the rotation playlist?');">
                                        <input type="hidden" name="action" value="delete_interstitial_ad">
                                        <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                        <button type="submit"
                                            style="background: none; border: none; color: #ef4444; font-size: 16px; cursor: pointer; padding: 8px; transition: transform 0.2s;"
                                            onmouseover="this.style.transform='scale(1.1)'"
                                            onmouseout="this.style.transform='scale(1)'">
                                            <i class="fa fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Backup Section -->
    <div id="section-database_backup" class="settings-section">
        <button class="back-btn" onclick="showSection('grid')" style="margin-bottom: 24px;">
            <i class="fa fa-arrow-left"></i> Back to Platform Settings
        </button>

        <div class="settings-form-wrapper" style="max-width: 900px; padding: 36px; background: white; border-radius: 28px; border: 1.5px solid #e2e8f0; box-shadow: var(--shadow-sm);">
            <div class="section-header" style="margin-bottom: 28px; border-bottom: 2px solid #f1f5f9; padding-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="margin: 0; color: #1e293b; font-weight: 800; font-size: 24px; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-database" style="color: #2563eb;"></i> Full Database Backup
                    </h2>
                    <p style="margin: 6px 0 0 0; color: #64748b; font-size: 13px;">
                        Generate a complete phpMyAdmin-compatible SQL dump with full table structures and data, overwriting <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 6px; color: #0f172a; font-weight: 700;">db/enteangadi.sql</code>.
                    </p>
                </div>
                <span style="background: #eff6ff; color: #1d4ed8; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 800; border: 1px solid #bfdbfe; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-shield-alt"></i> phpMyAdmin Standard DDL
                </span>
            </div>

            <!-- Metrics Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px;">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #64748b; letter-spacing: 0.5px; margin-bottom: 6px;">Target File</div>
                    <div style="font-size: 14px; font-weight: 800; color: #0f172a; word-break: break-all;">db/enteangadi.sql</div>
                    <div style="margin-top: 6px; font-size: 11px; color: <?= $backup_file_exists ? '#16a34a' : '#ea580c' ?>; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                        <i class="fa fa-circle" style="font-size: 8px;"></i> <?= $backup_file_exists ? 'File Ready (Will Overwrite)' : 'File Will Be Created' ?>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px;">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #64748b; letter-spacing: 0.5px; margin-bottom: 6px;">Current File Size</div>
                    <div style="font-size: 18px; font-weight: 800; color: #0f172a;">
                        <?= $backup_file_exists ? ($backup_file_size > 1048576 ? round($backup_file_size / 1048576, 2) . ' MB' : round($backup_file_size / 1024, 2) . ' KB') : '0 KB' ?>
                    </div>
                    <div style="margin-top: 6px; font-size: 11px; color: #64748b;">
                        <?= number_format($backup_file_size) ?> bytes
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px;">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #64748b; letter-spacing: 0.5px; margin-bottom: 6px;">Last Backup Taken</div>
                    <div style="font-size: 14px; font-weight: 800; color: #0f172a;">
                        <?= $backup_file_time ? date('d M Y, h:i A', $backup_file_time) : 'Never' ?>
                    </div>
                    <div style="margin-top: 6px; font-size: 11px; color: #64748b;">
                        <?= $backup_file_time ? (round((time() - $backup_file_time) / 60) < 60 ? round((time() - $backup_file_time) / 60) . ' mins ago' : round((time() - $backup_file_time) / 3600) . ' hours ago') : 'No backup yet' ?>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px;">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #64748b; letter-spacing: 0.5px; margin-bottom: 6px;">Database Tables & Rows</div>
                    <div style="font-size: 18px; font-weight: 800; color: #2563eb;">
                        <?= count($all_db_tables) ?> Tables
                    </div>
                    <div style="margin-top: 6px; font-size: 11px; color: #64748b;">
                        <?= number_format($total_db_records_count) ?> Total Records
                    </div>
                </div>
            </div>

            <!-- Unified Database Backup Box -->
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1.5px solid #e2e8f0; border-radius: 20px; padding: 26px; margin-bottom: 28px;">
                <h3 style="margin: 0 0 8px 0; font-size: 17px; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-database" style="color: var(--primary-green);"></i> Backup Database (db/enteangadi.sql)
                </h3>
                <p style="margin: 0 0 20px 0; color: #64748b; font-size: 13.5px; line-height: 1.5;">
                    Extracts all database tables and records into the single standard backup file <code style="color:#0f172a; font-weight:700;">db/enteangadi.sql</code> using phpMyAdmin-compatible standard SQL syntax (<code style="color:#0f172a; font-weight:600;">DROP TABLE IF EXISTS</code> + <code style="color:#0f172a; font-weight:600;">CREATE TABLE</code> + batched data inserts).
                </p>

                <div style="display: flex; gap: 14px; flex-wrap: wrap; align-items: center; margin-bottom: 22px;">
                    <form method="POST" action="settings.php" onsubmit="return confirm('Are you sure you want to take a database backup and overwrite db/enteangadi.sql?');">
                        <?= function_exists('csrfField') ? csrfField() : '' ?>
                        <input type="hidden" name="action" value="backup_database">
                        <button type="submit" class="btn-primary" style="padding: 14px 26px; font-size: 14px; font-weight: 800; border-radius: 14px; display: inline-flex; align-items: center; gap: 10px; cursor: pointer; background: var(--primary-green); border: none; color: white; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);">
                            <i class="fa fa-database"></i> Backup Database (db/enteangadi.sql)
                        </button>
                    </form>

                    <a href="settings.php?download_db_backup=1" style="padding: 14px 22px; font-size: 14px; font-weight: 700; border-radius: 14px; display: inline-flex; align-items: center; gap: 8px; background: white; color: #1e293b; border: 1.5px solid #cbd5e1; text-decoration: none; transition: all 0.2s ease; box-shadow: var(--shadow-sm);">
                        <i class="fa fa-download" style="color: #2563eb;"></i> Download .SQL File
                    </a>
                </div>

                <!-- Clean Production Mode inside same box -->
                <div style="border-top: 1.5px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <div style="font-size: 13.5px; font-weight: 800; color: #334155; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-broom" style="color: #6366f1;"></i> Clean Production Mode (Live Deployment)
                        </div>
                        <div style="font-size: 12.5px; color: #64748b; margin-top: 4px;">
                            Purge dummy test data (preserving Admin, Categories & Settings) and update <code style="color:#0f172a; font-weight:600;">db/enteangadi.sql</code> directly.
                        </div>
                    </div>
                    <button type="button" onclick="confirmCleanProductionAction()" style="padding: 11px 20px; font-size: 13px; font-weight: 700; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; background: #eef2ff; color: #4f46e5; border: 1.5px solid #c7d2fe; transition: all 0.2s; box-shadow: var(--shadow-sm);">
                        <i class="fa fa-trash-alt"></i> Purge Test Data & Update Backup
                    </button>
                </div>

                <form id="cleanProductionForm" method="POST" action="settings.php" style="display: none;">
                    <?= function_exists('csrfField') ? csrfField() : '' ?>
                    <input type="hidden" name="action" value="clean_production_sql">
                    <input type="hidden" name="admin_password" id="cleanProductionPasswordInput" value="">
                </form>
            </div>

            <!-- Details Callout -->
            <div style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 18px; padding: 20px;">
                <h4 style="margin: 0 0 8px 0; color: #065f46; font-size: 14px; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-check-circle" style="color: #10b981;"></i> What is included in this backup?
                </h4>
                <ul style="margin: 0; padding-left: 20px; color: #047857; font-size: 12.5px; line-height: 1.6;">
                    <li><strong>Exact phpMyAdmin Structure</strong>: Uses standard MySQL dump syntax, character set <code style="font-weight:700;">utf8mb4</code>, and transaction-safe statements.</li>
                    <li><strong>Full Table DDL</strong>: Includes <code style="font-weight:700;">DROP TABLE IF EXISTS</code> and <code style="font-weight:700;">CREATE TABLE</code> for all <?= count($all_db_tables) ?> platform tables.</li>
                    <li><strong>Complete Row Data</strong>: Batched <code style="font-weight:700;">INSERT INTO</code> statements for products, categories, users, images, messages, and settings.</li>
                    <li><strong>In-Place Replacement</strong>: Overwrites <code style="font-weight:700;">db/enteangadi.sql</code> directly so your repository and restore scripts always stay up to date without cluttering disk space.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Security Section -->
    <div id="section-security"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'password') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>Account Security</h2>
            </div>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="password">
                <div class="form-group">
                    <label>Current Admin Password</label>
                    <input type="password" name="current_password" class="form-control"
                        placeholder="Required for verification" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="At least 8 characters"
                        required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                        placeholder="Repeat new password" required>
                </div>
                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Update
                    Security Credentials</button>
            </form>
        </div>
    </div>

    <!-- General Section -->
    <div id="section-general"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'general') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>General Configuration</h2>
            </div>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="general">
                <div class="form-group">
                    <label>Platform Name</label>
                    <input type="text" name="app_name" class="form-control"
                        value="<?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?>"
                        placeholder="e.g. Enteangadi">
                    <small style="color: var(--text-muted); display: block; margin-top: 8px;">This is the name that
                        appears in the site header and browser tab.</small>
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <label>Site Status</label>
                    <select class="form-control" disabled style="background: #f8fafc; cursor: not-allowed;">
                        <option>Active / Online</option>
                    </select>
                    <small style="color: var(--text-muted); display: block; margin-top: 8px;">Maintenance mode options
                        coming soon.</small>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Save
                    General Settings</button>
            </form>
        </div>
    </div>

    <!-- Adult Content Section -->
    <div id="section-adult_content"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'adult_content') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>Adult Content Settings</h2>
            </div>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="adult_content">

                <div style="background: #fff8f8; padding: 24px; border-radius: 20px; border: 1.5px solid #fee2e2; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 16px;">
                    <i class="fa fa-exclamation-triangle" style="font-size: 24px; color: #ef4444; margin-top: 2px;"></i>
                    <div>
                        <h4 style="margin: 0 0 6px 0; color: #991b1b; font-weight: 800; font-size: 16px;">Moderation Policy</h4>
                        <p style="margin: 0; color: #7f1d1d; font-size: 13px; line-height: 1.5;">
                            When enabled, Enteangadi automatically scans ad titles, descriptions, and uploaded images/profile pictures for inappropriate keywords and NSFW content before allowing them to be published.
                        </p>
                    </div>
                </div>

                <div class="form-group"
                    style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                    <div>
                        <label
                            style="font-weight: 700; margin-bottom: 2px; display: block; cursor: pointer; font-size: 13px;">Enable Adult Content Check</label>
                        <span style="font-size: 11px; color: var(--text-muted);">Enable 18+ keyword scans and image verification.</span>
                    </div>
                    <label class="switch" style="transform: scale(0.9);">
                        <input type="checkbox" name="adult_content_check" value="1"
                            <?= ($app_settings['adult_content_check'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Save Settings</button>
            </form>
        </div>
    </div>

    <!-- App Features Section -->
    <div id="section-app_features"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'app_features') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>App Features Config</h2>
                <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">Enable or disable mobile functionality and fine-tune intervals.</p>
            </div>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="app_features">

                <!-- 2. Background Notification Toggle -->
                <div class="form-group"
                    style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                    <div>
                        <label style="font-weight: 700; margin-bottom: 2px; display: block; cursor: pointer; font-size: 13px;">Enable Background Notification Polling</label>
                        <span style="font-size: 11px; color: var(--text-muted);">Run background sync intervals to check for new inbox messages.</span>
                    </div>
                    <label class="switch" style="transform: scale(0.9);">
                        <input type="checkbox" name="bg_notification_active" value="1"
                            <?= ($app_settings['bg_notification_active'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- 3. Background Notification Interval (Seconds) -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 700;">Notification Sync Interval (Seconds)</label>
                    <input type="number" name="bg_notification_interval" class="form-control" min="5" max="86400"
                        style="padding: 10px;"
                        value="<?= htmlspecialchars($app_settings['bg_notification_interval'] ?? '30') ?>">
                    <small style="color: var(--text-muted); display: block; margin-top: 4px; font-size: 11px;">Specify how frequently (in seconds) the background service checks for notifications (e.g. 10 seconds, 3600 seconds for 1 hour).</small>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Save Settings</button>
            </form>
        </div>
    </div>

    <!-- Ad Approval Section -->
    <div id="section-approval"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'approval') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>Ad Approval Workflow</h2>
            </div>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="approval">

                <div
                    style="background: #f8fafc; padding: 30px; border-radius: 20px; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h3 style="margin: 0 0 8px 0; font-size: 18px;">Approval Mode</h3>
                        <p style="margin: 0; color: var(--text-muted); font-size: 13px;">
                            <span id="mode-desc-manual"
                                style="<?= ($app_settings['ad_approval_mode'] ?? 'auto') === 'manual' ? 'color: var(--primary-green); font-weight: 700;' : '' ?>">Manual:</span>
                            Ads require admin review before appearing on the site.<br>
                            <span id="mode-desc-auto"
                                style="<?= ($app_settings['ad_approval_mode'] ?? 'auto') === 'auto' ? 'color: var(--primary-green); font-weight: 700;' : '' ?>">Auto:</span>
                            Ads go live immediately after submission.
                        </p>
                    </div>

                    <div style="text-align: center;">
                        <div style="margin-bottom: 10px; font-size: 12px; font-weight: 700; color: var(--text-muted);"
                            id="switch-label">
                            <?= ($app_settings['ad_approval_mode'] ?? 'auto') === 'manual' ? 'MANUAL' : 'AUTO' ?>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="ad_approval_mode_toggle" id="approval-toggle"
                                <?= ($app_settings['ad_approval_mode'] ?? 'auto') === 'auto' ? 'checked' : '' ?>
                                onchange="updateApprovalMode(this)">
                            <span class="slider"></span>
                        </label>
                        <input type="hidden" name="ad_approval_mode" id="ad_approval_mode_input"
                            value="<?= htmlspecialchars($app_settings['ad_approval_mode'] ?? 'auto') ?>">
                    </div>
                </div>

                <div
                    style="margin-top: 40px; background: #fffbeb; border: 1px solid #fde68a; padding: 20px; border-radius: 16px; display: flex; gap: 12px;">
                    <i class="fa fa-lightbulb" style="color: #d97706; font-size: 20px; margin-top: 4px;"></i>
                    <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.6;">
                        <strong>Tip:</strong> Use <strong>Manual Mode</strong> if you are experiencing issues with spam
                        or inappropriate content. Use <strong>Auto Mode</strong> for a faster user experience.
                    </p>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Update
                    Approval Workflow</button>
            </form>
        </div>
    </div>

    <!-- Pending Approvals Section -->
    <div id="section-pending"
        class="settings-section <?= (isset($_POST['action']) && ($_POST['action'] === 'approve_ad' || $_POST['action'] === 'reject_ad')) ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div
            style="background: var(--white); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
            <div
                style="padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 20px; font-weight: 800;">Ad Review Queue</h2>
                <span
                    style="background: #e8f5e9; color: var(--primary-green-dark); padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 12px;">
                    <?= count($pending_products) ?> Pending
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                            <th
                                style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted);">
                                Product</th>
                            <th
                                style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted);">
                                Details</th>
                            <th
                                style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted); text-align: right;">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_products)): ?>
                            <tr>
                                <td colspan="3" style="padding: 48px; text-align: center; color: var(--text-muted);">
                                    <i class="fa fa-mug-hot"
                                        style="font-size: 32px; display: block; margin-bottom: 16px; opacity: 0.3;"></i>
                                    All clear! No ads waiting for review.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_products as $p): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                                    <td style="padding: 20px 24px;">
                                        <div style="display: flex; gap: 16px; align-items: center;">
                                            <div
                                                style="width: 50px; height: 50px; border-radius: 10px; overflow: hidden; background: #eee; flex-shrink: 0;">
                                                <?php if ($p['main_image']): ?>
                                                    <img src="../<?= htmlspecialchars($p['main_image']) ?>"
                                                        style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div
                                                        style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ccc;">
                                                        <i class="fa fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div
                                                    style="font-weight: 700; color: var(--text-dark); margin-bottom: 2px; font-size: 14px;">
                                                    <?= htmlspecialchars($p['title']) ?>
                                                </div>
                                                <div style="font-size: 11px; font-weight: 700; color: var(--primary-green);">
                                                    #<?= htmlspecialchars($p['unique_id']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 20px 24px;">
                                        <div style="font-size: 12px; font-weight: 700; color: #1e293b;">
                                            ₹<?= number_format($p['price'], (fmod($p['price'], 1) == 0) ? 0 : 2) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);">
                                            <?= htmlspecialchars($p['category_name']) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 20px 24px; text-align: right;">
                                        <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                            <button type="button" class="btn-secondary"
                                                style="padding: 6px 12px; font-size: 12px; border-radius: 8px;" onclick='viewAdDetails(<?= htmlspecialchars(json_encode([
                                                    "title" => $p["title"],
                                                    "unique_id" => $p["unique_id"],
                                                    "description" => $p["description"],
                                                    "price" => number_format($p["price"], (fmod($p["price"], 1) == 0) ? 0 : 2),
                                                    "location" => $p["location_name"],
                                                    "category" => $p["category_name"],
                                                    "images" => explode(",", $p["all_images"])
                                                ]), ENT_QUOTES, "UTF-8") ?>)'>
                                                <i class="fa fa-eye"></i> Details
                                            </button>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="action" value="approve_ad">
                                                <button type="submit" class="btn-primary"
                                                    style="padding: 6px 12px; font-size: 12px; border-radius: 8px; background: #16a34a; border-color: #16a34a;">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="action" value="reject_ad">
                                                <button type="submit" class="btn-secondary"
                                                    style="padding: 6px 12px; font-size: 12px; border-radius: 8px; color: #dc2626; border-color: #fecaca; background: #fef2f2;">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ad Details Modal (Integrated) -->
    <div id="adDetailsModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div
            style="background: white; width: 90%; max-width: 600px; max-height: 85vh; border-radius: 24px; overflow: hidden; display: flex; flex-direction: column; box-shadow: var(--shadow-lg);">
            <div
                style="padding: 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 id="modalTitle" style="margin: 0; font-size: 18px; font-weight: 800;"></h3>
                    <div id="modalId"
                        style="font-size: 12px; color: var(--primary-green); font-weight: 700; margin-top: 2px;"></div>
                </div>
                <button onclick="closeModal()"
                    style="background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;"><i
                        class="fa fa-times"></i></button>
            </div>
            <div style="padding: 24px; overflow-y: auto; flex: 1;">
                <div id="modalImages"
                    style="display: flex; gap: 10px; overflow-x: auto; padding-bottom: 15px; margin-bottom: 20px; scrollbar-width: thin;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                    <div>
                        <label
                            style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 4px;">Price</label>
                        <div id="modalPrice" style="font-weight: 700; color: var(--text-dark); font-size: 18px;"></div>
                    </div>
                    <div>
                        <label
                            style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 4px;">Location</label>
                        <div id="modalLocation" style="font-weight: 600; color: var(--text-dark);"><i
                                class="fa fa-map-marker-alt" style="color: #ef4444; margin-right: 4px;"></i>
                            <span></span>
                        </div>
                    </div>
                </div>
                <label
                    style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;">Description</label>
                <div id="modalDescription"
                    style="background: #f8fafc; padding: 16px; border-radius: 12px; font-size: 14px; line-height: 1.6; color: #334155; white-space: pre-wrap;">
                </div>
                <div id="modalCategory" style="font-weight: 700; color: var(--primary-green);"></div>
                <div id="modalReasonWrapper"
                    style="margin-top: 16px; padding: 12px; background: #fff7ed; border: 1px solid #ffedd5; border-radius: 8px; display: none;">
                    <label
                        style="font-size: 11px; font-weight: 700; color: #9a3412; text-transform: uppercase; display: block; margin-bottom: 4px;">Closure
                        Reason (User Feedback)</label>
                    <div id="modalReason" style="font-weight: 600; color: #7c2d12; font-size: 13px;"></div>
                </div>
            </div>
            <div style="padding: 20px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9; text-align: right;">
                <button onclick="closeModal()" class="btn-secondary"
                    style="padding: 10px 24px; border-radius: 10px; font-weight: 600;">Close Preview</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage All Active Ads Section -->
<div id="section-manage_ads"
    class="settings-section <?= (isset($_POST['action']) && in_array($_POST['action'], ['toggle_verify', 'delete_ad_item'])) ? 'active' : '' ?>">
    <button class="back-btn" onclick="showSection('grid')">
        <i class="fa fa-arrow-left"></i> Back to Dashboard
    </button>

    <div class="manage-ads-header-card">
        <div class="manage-ads-toolbar">
            <div>
                <h2 style="margin: 0 0 4px; font-size: 22px; font-weight: 850; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                    <i class="fa fa-boxes-stacked" style="color: #059669;"></i> Manage All Listings
                </h2>
                <div style="font-size: 13px; color: #64748b; font-weight: 500;">
                    Search, verify trust badges, view complete details, and delete individual listings.
                </div>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <div class="manage-ads-search">
                    <i class="fa fa-search" style="color: #94a3b8; font-size: 13px;"></i>
                    <input type="text" id="manageAdsSearch" placeholder="Search title, ad ID, seller, price..." onkeyup="filterManageAdsTable()">
                </div>
                <span style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #ffffff; padding: 7px 16px; border-radius: 12px; font-weight: 800; font-size: 12.5px; box-shadow: 0 3px 8px rgba(5,150,105,0.25);">
                    <i class="fa fa-tags" style="font-size: 11px; margin-right: 4px;"></i> <?= count($active_products) ?> Total Ads
                </span>
            </div>
        </div>

        <!-- Filter Chips Bar -->
        <?php
        $ver_cnt = 0;
        $unver_cnt = 0;
        $live_cnt = 0;
        $sold_cnt = 0;
        foreach ($active_products as $ap) {
            if (!empty($ap['is_verified'])) $ver_cnt++;
            else $unver_cnt++;
            if ($ap['status'] === 'active') $live_cnt++;
            elseif ($ap['status'] === 'sold') $sold_cnt++;
        }
        ?>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9;">
            <span style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-right: 4px;">Quick Filters:</span>
            <button type="button" class="ea-filter-chip active" id="chip-ad-all" onclick="setAdFilter('all', this)">
                <i class="fa fa-layer-group"></i> All (<?= count($active_products) ?>)
            </button>
            <button type="button" class="ea-filter-chip" id="chip-ad-verified" onclick="setAdFilter('verified', this)" style="color: #059669;">
                <i class="fa fa-check-circle"></i> Verified (<?= $ver_cnt ?>)
            </button>
            <button type="button" class="ea-filter-chip" id="chip-ad-unverified" onclick="setAdFilter('unverified', this)" style="color: #d97706;">
                <i class="fa fa-clock"></i> Unverified (<?= $unver_cnt ?>)
            </button>
            <button type="button" class="ea-filter-chip" id="chip-ad-live" onclick="setAdFilter('live', this)" style="color: #2563eb;">
                <i class="fa fa-store"></i> Live (<?= $live_cnt ?>)
            </button>
            <?php if ($sold_cnt > 0): ?>
                <button type="button" class="ea-filter-chip" id="chip-ad-sold" onclick="setAdFilter('sold', this)" style="color: #8b5cf6;">
                    <i class="fa fa-handshake"></i> Sold (<?= $sold_cnt ?>)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Table Card Container -->
    <div class="manage-ads-table-wrap">
        <div style="overflow-x: auto;">
            <table class="manage-ads-table" id="manageAdsTable">
                <thead>
                    <tr>
                        <th style="width: 38%;">Product & Seller</th>
                        <th style="width: 18%;">Category & Price</th>
                        <th style="width: 18%;">Trust & Status</th>
                        <th style="width: 26%; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($active_products)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 45px 20px; color: #94a3b8;">
                                <div style="font-size: 36px; margin-bottom: 8px; color: #cbd5e1;"><i class="fa fa-folder-open"></i></div>
                                <div style="font-weight: 750; font-size: 15px; color: #475569;">No marketplace listings found.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($active_products as $p): 
                            $is_v = !empty($p['is_verified']) ? 1 : 0;
                            $st = strtolower($p['status'] ?? 'active');
                            $search_haystack = strtolower($p['title'] . ' ' . $p['unique_id'] . ' ' . $p['username'] . ' ' . ($p['seller_phone'] ?? '') . ' ' . ($p['category_name'] ?? '') . ' ' . $p['price']);
                        ?>
                            <tr class="manage-ad-row" 
                                data-verified="<?= $is_v ?>" 
                                data-status="<?= $st ?>" 
                                data-search="<?= htmlspecialchars($search_haystack) ?>">
                                <td>
                                    <div style="display: flex; gap: 14px; align-items: center;">
                                        <div style="width: 48px; height: 48px; border-radius: 12px; overflow: hidden; background: #f1f5f9; border: 1.5px solid #e2e8f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                            <?php if (!empty($p['main_image'])): ?>
                                                <img src="../<?= htmlspecialchars($p['main_image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="fa fa-image" style="color: #94a3b8; font-size: 16px;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div style="min-width: 0;">
                                            <a href="../product.php?id=<?= $p['id'] ?>" target="_blank" style="font-weight: 800; color: #0f172a; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;" title="View live ad on marketplace">
                                                <?= htmlspecialchars($p['title']) ?>
                                                <i class="fa fa-external-link-alt" style="font-size: 10.5px; color: #059669;"></i>
                                            </a>
                                            <div style="font-size: 11.5px; color: #64748b; margin-top: 3px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                                <span style="font-weight: 700; color: #059669;">#<?= htmlspecialchars($p['unique_id']) ?></span>
                                                &bull;
                                                <span>by <strong><?= htmlspecialchars($p['username']) ?></strong></span>
                                                <?php if (!empty($p['seller_phone'])): ?>
                                                    &bull;
                                                    <span><i class="fa fa-phone-alt" style="font-size: 9.5px; color: #64748b;"></i> <?= htmlspecialchars($p['seller_phone']) ?></span>
                                                <?php endif; ?>
                                                &bull;
                                                <span style="color: #94a3b8;"><?= date('M d, Y', strtotime($p['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 850; color: #059669; font-size: 15px; margin-bottom: 4px;">
                                        ₹<?= number_format($p['price'], (fmod($p['price'], 1) == 0) ? 0 : 2) ?>
                                    </div>
                                    <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                        <span style="font-size: 10.5px; font-weight: 750; background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                            <?= htmlspecialchars($p['category_name']) ?>
                                        </span>
                                        <span style="font-size: 10px; font-weight: 800; background: #faf5ff; color: #7c3aed; padding: 2px 6px; border-radius: 6px; text-transform: uppercase;">
                                            <?= htmlspecialchars($p['type'] ?? 'SELL') ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status_map = [
                                        'active' => ['bg' => '#ecfdf5', 'color' => '#047857', 'border' => '#a7f3d0', 'icon' => 'fa-circle-check', 'label' => 'LIVE'],
                                        'sold' => ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0', 'icon' => 'fa-handshake', 'label' => 'SOLD'],
                                        'deleted' => ['bg' => '#fef2f2', 'color' => '#b91c1c', 'border' => '#fecaca', 'icon' => 'fa-trash', 'label' => 'DELETED'],
                                        'inactive' => ['bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#e2e8f0', 'icon' => 'fa-eye-slash', 'label' => 'HIDDEN'],
                                        'expired' => ['bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fde68a', 'icon' => 'fa-hourglass-end', 'label' => 'EXPIRED']
                                    ];
                                    $st_info = $status_map[$st] ?? ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0', 'icon' => 'fa-info-circle', 'label' => strtoupper($st)];
                                    ?>
                                    <div style="margin-bottom: 6px;">
                                        <span style="background: <?= $st_info['bg'] ?>; color: <?= $st_info['color'] ?>; border: 1px solid <?= $st_info['border'] ?>; padding: 3px 9px; border-radius: 8px; font-size: 10.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa <?= $st_info['icon'] ?>" style="font-size: 9px;"></i> <?= $st_info['label'] ?>
                                        </span>
                                    </div>

                                    <?php if ($is_v): ?>
                                        <span style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); color: #047857; border: 1px solid #6ee7b7; padding: 3px 9px; border-radius: 8px; font-size: 10.5px; font-weight: 850; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 3px rgba(5,150,105,0.1);">
                                            <i class="fa fa-check-circle" style="color: #059669;"></i> VERIFIED AD
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 3px 9px; border-radius: 8px; font-size: 10.5px; font-weight: 750; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-clock" style="color: #d97706; font-size: 10px;"></i> Standard
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end; flex-wrap: wrap;">
                                        <!-- 1. Details Modal Button -->
                                        <button type="button" class="btn-ad-action btn-details" onclick='viewAdDetails(<?= htmlspecialchars(json_encode([
                                            "title" => $p["title"],
                                            "unique_id" => $p["unique_id"],
                                            "description" => $p["description"],
                                            "price" => number_format($p["price"], (fmod($p["price"], 1) == 0) ? 0 : 2),
                                            "location" => $p["location_name"] ?: "Kerala",
                                            "category" => $p["category_name"],
                                            "images" => explode(",", $p["all_images"])
                                        ]), ENT_QUOTES, "UTF-8") ?>)' title="Preview listing details">
                                            <i class="fa fa-eye"></i> Details
                                        </button>

                                        <!-- 2. Toggle Verification Badge -->
                                        <form method="POST" style="margin: 0; display: inline;">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $is_v ?>">
                                            <input type="hidden" name="action" value="toggle_verify">
                                            <?php if ($is_v): ?>
                                                <button type="submit" class="btn-ad-action btn-remove-badge" title="Remove verification badge from this ad">
                                                    <i class="fa fa-shield-alt"></i> Remove Badge
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn-ad-action btn-add-badge" title="Grant verified blue badge to this ad">
                                                    <i class="fa fa-check-circle"></i> Add Badge
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- 3. Delete Each Post Separately -->
                                        <form method="POST" style="margin: 0; display: inline;">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="action" value="delete_ad_item">
                                            <button type="button" class="btn-ad-action btn-delete" 
                                                    data-uid="<?= htmlspecialchars($p['unique_id']) ?>" 
                                                    data-title="<?= htmlspecialchars($p['title'], ENT_QUOTES) ?>" 
                                                    onclick="confirmDeleteListing(this)" 
                                                    title="Permanently delete this post">
                                                <i class="fa fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr id="manageAdsNoMatchRow" style="display: none;">
                        <td colspan="4" style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                            <div style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"><i class="fa fa-search"></i></div>
                            <div style="font-weight: 750; font-size: 14.5px; color: #475569;">No listings match your search or filter criteria.</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Closure Feedback Section -->
<div id="section-closure_feedback" class="settings-section">
    <button class="back-btn" onclick="showSection('grid')">
        <i class="fa fa-arrow-left"></i> Back to Dashboard
    </button>

    <div
        style="background: var(--white); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
        <div
            style="padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; font-size: 20px; font-weight: 800;">Closure Insights</h2>
            <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 12px; gap: 4px;">
                <button onclick="switchFeedback('ads')" id="btn-feedback-ads"
                    style="border: none; padding: 6px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; background: white; color: var(--primary-green-dark); box-shadow: var(--shadow-sm);">Ads
                    Feedback</button>
                <button onclick="switchFeedback('accounts')" id="btn-feedback-accounts"
                    style="border: none; padding: 6px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; background: transparent; color: #64748b;">Account
                    Feedback</button>
            </div>
        </div>

        <div style="padding: 24px;">
            <!-- Ads Feedback Tab -->
            <div id="tab-feedback-ads">
                <?php if (empty($closure_feedback)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fa fa-comment-slash" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                        No ad feedback received yet.
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 20px;">
                        <?php foreach ($closure_feedback as $f): ?>
                            <div
                                style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; display: flex; gap: 20px; align-items: flex-start;">
                                <div
                                    style="width: 60px; height: 60px; border-radius: 12px; overflow: hidden; background: #eee; flex-shrink: 0;">
                                    <?php if ($f['main_image']): ?>
                                        <img src="../<?= htmlspecialchars($f['main_image']) ?>"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div
                                            style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ccc;">
                                            <i class="fa fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                        <div>
                                            <h4 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-dark);">
                                                <?= htmlspecialchars($f['title']) ?>
                                            </h4>
                                            <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--text-muted);">by
                                                <?= htmlspecialchars($f['username']) ?> &bull;
                                                <?= htmlspecialchars($f['category_name']) ?>
                                            </p>
                                        </div>
                                        <?php
                                        $status_color = '#f0fdf4';
                                        $text_color = '#166534';
                                        if ($f['status'] === 'deleted') {
                                            $status_color = '#fff1f2';
                                            $text_color = '#991b1b';
                                        }
                                        if ($f['status'] === 'expired') {
                                            $status_color = '#fffbeb';
                                            $text_color = '#92400e';
                                        }
                                        ?>
                                        <span
                                            style="padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; background: <?= $status_color ?>; color: <?= $text_color ?>;">
                                            <?= strtoupper($f['status']) ?>
                                        </span>
                                    </div>
                                    <div
                                        style="background: white; border: 1px solid #e2e8f0; padding: 12px 16px; border-radius: 12px; margin-top: 12px; position: relative;">
                                        <div
                                            style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">
                                            User Reason</div>
                                        <div style="font-weight: 600; color: var(--text-dark); font-size: 14px;">
                                            "<?= htmlspecialchars($f['status_reason']) ?>"</div>
                                        <div
                                            style="font-size: 10px; color: var(--text-muted); margin-top: 8px; text-align: right;">
                                            <?= date('M d, Y h:i A', strtotime($f['updated_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Accounts Feedback Tab -->
            <div id="tab-feedback-accounts" style="display: none;">
                <?php if (empty($account_feedback)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fa fa-user-minus" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                        No account closure feedback yet.
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 20px;">
                        <?php foreach ($account_feedback as $af): ?>
                            <div style="background: #fff1f2; border: 1px solid #fecaca; border-radius: 16px; padding: 20px;">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                    <div>
                                        <h4 style="margin: 0; font-size: 16px; font-weight: 800; color: #991b1b;">
                                            <?= htmlspecialchars($af['username']) ?>
                                        </h4>
                                        <p style="margin: 4px 0 0 0; font-size: 12px; color: #b91c1c;">
                                            <?= htmlspecialchars($af['email']) ?>
                                        </p>
                                    </div>
                                    <span
                                        style="padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; background: #991b1b; color: white;">ACCOUNT
                                        DELETED</span>
                                </div>
                                <div
                                    style="background: white; border: 1px solid #fecaca; padding: 12px 16px; border-radius: 12px; position: relative;">
                                    <div
                                        style="font-size: 11px; font-weight: 700; color: #b91c1c; text-transform: uppercase; margin-bottom: 4px;">
                                        Departure Reason</div>
                                    <div style="font-weight: 600; color: var(--text-dark); font-size: 14px;">
                                        "<?= htmlspecialchars($af['reason']) ?>"</div>
                                    <div style="font-size: 10px; color: var(--text-muted); margin-top: 8px; text-align: right;">
                                        <?= date('M d, Y h:i A', strtotime($af['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Search & Delete Ads Section -->
<div id="section-search_delete_ads" class="settings-section">
    <button class="back-btn" onclick="showSection('grid')">
        <i class="fa fa-arrow-left"></i> Back to Dashboard
    </button>

    <div style="background: var(--white); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); padding: 24px;">
        <h2 style="margin: 0 0 16px 0; font-size: 20px; font-weight: 800;">Delete Ads by Phone Number</h2>
        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 24px;">Search for a user by their registered phone number to manage and delete their active or pending listings.</p>

        <!-- Search Bar -->
        <div style="display: flex; gap: 12px; margin-bottom: 30px;">
            <input type="text" id="delete-ads-search-phone" class="form-control" placeholder="Enter user phone number (e.g. 9876543210)" style="max-width: 400px; padding: 12px;">
            <button onclick="searchUserAds()" class="btn-primary" style="padding: 12px 24px;"><i class="fa fa-search"></i> Search</button>
        </div>

        <!-- Loader -->
        <div id="search-ads-loader" style="display: none; text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fa fa-spinner fa-spin" style="font-size: 32px; color: var(--primary-green); margin-bottom: 12px; display: block;"></i>
            Searching user listings...
        </div>

        <!-- User Info & Results Container -->
        <div id="search-ads-results" style="display: none;">
            <div id="delete-ads-user-card" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 16px;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary-green-light); color: var(--primary-green-dark); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800;" id="delete-ads-user-avatar">U</div>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 800;" id="delete-ads-user-name">User Name</h3>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--text-muted);" id="delete-ads-user-phone">Phone Number</p>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h4 style="margin: 0; font-size: 15px; font-weight: 700;">User Listings</h4>
                <button id="btn-delete-selected-ads" onclick="deleteSelectedAds()" class="btn-danger" style="padding: 8px 16px; font-size: 13px; display: none; align-items: center; gap: 6px;">
                    <i class="fa fa-trash-alt"></i> Delete Selected (<span id="delete-selected-count">0</span>)
                </button>
            </div>

            <!-- Table of Ads -->
            <div style="overflow-x: auto; border: 1px solid var(--border-color); border-radius: 16px; background: white;">
                <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color); text-align: left;">
                            <th style="padding: 14px 16px; width: 50px;"><input type="checkbox" id="chk-delete-all-ads" onchange="toggleSelectAllAds(this)"></th>
                            <th style="padding: 14px 16px;">Product Detail</th>
                            <th style="padding: 14px 16px;">Price</th>
                            <th style="padding: 14px 16px;">Status</th>
                            <th style="padding: 14px 16px;">Posted Date</th>
                            <th style="padding: 14px 16px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="delete-ads-table-body">
                        <!-- Dynamic rows injected here -->
                    </tbody>
                </table>
            </div>
        </div>

        <div id="search-ads-empty" style="display: none; text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fa fa-info-circle" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
            No active or pending listings found for this user.
        </div>
    </div>
</div>

<!-- Profile Verifications Section -->
<div id="section-profile_verifications" class="settings-section">
    <button class="back-btn" onclick="showSection('grid')">
        <i class="fa fa-arrow-left"></i> Back to Dashboard
    </button>

    <div style="background: var(--white); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
        <div style="padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="margin: 0; font-size: 20px; font-weight: 800;">Profile Verifications</h2>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-muted);">Approve or reject uploaded user identification documents to award the verified badge.</p>
            </div>
            
            <!-- Tabs inside Settings Section -->
            <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 12px; gap: 4px;">
                <button onclick="switchVerificationTab('pending')" id="btn-verification-pending"
                    style="border: none; padding: 6px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; background: white; color: var(--primary-green-dark); box-shadow: var(--shadow-sm);">
                    Pending (<?= count($pending_verifications) ?>)
                </button>
                <button onclick="switchVerificationTab('approved')" id="btn-verification-approved"
                    style="border: none; padding: 6px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; background: transparent; color: #64748b;">
                    Approved (<?= count($approved_verifications) ?>)
                </button>
                <button onclick="switchVerificationTab('rejected')" id="btn-verification-rejected"
                    style="border: none; padding: 6px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; background: transparent; color: #64748b;">
                    Rejected (<?= count($rejected_verifications) ?>)
                </button>
            </div>
        </div>

        <div style="padding: 24px;">
            <!-- Pending Claims Tab -->
            <div id="tab-verification-pending">
                <?php if (empty($pending_verifications)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fa fa-id-card" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                        No pending profile verification claims.
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto; border: 1px solid var(--border-color); border-radius: 16px;">
                        <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color); text-align: left;">
                                    <th style="padding: 14px 16px;">User Details</th>
                                    <th style="padding: 14px 16px;">ID Type</th>
                                    <th style="padding: 14px 16px;">Uploaded ID Photo</th>
                                    <th style="padding: 14px 16px;">Submitted</th>
                                    <th style="padding: 14px 16px; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_verifications as $req): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color); font-size: 14px;">
                                        <td style="padding: 16px;">
                                            <strong style="color: var(--text-dark);"><?= htmlspecialchars($req['username']) ?></strong>
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                                Email: <?= htmlspecialchars($req['email']) ?><br>
                                                Phone: <?= htmlspecialchars($req['phone_number']) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 16px; font-weight: 600; color: var(--text-dark);">
                                            <?= htmlspecialchars($req['id_type']) ?>
                                        </td>
                                        <td style="padding: 16px;">
                                            <a href="../<?= htmlspecialchars($req['id_photo']) ?>" target="_blank" title="Click to view full screen">
                                                <img src="../<?= htmlspecialchars($req['id_photo']) ?>" style="max-width: 100px; max-height: 60px; border-radius: 6px; border: 1px solid var(--border-color); object-fit: contain; cursor: pointer;">
                                            </a>
                                        </td>
                                        <td style="padding: 16px; color: var(--text-muted); font-size: 12px;">
                                            <?= date('M d, Y H:i', strtotime($req['created_at'])) ?>
                                        </td>
                                        <td style="padding: 16px; text-align: right;">
                                            <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                                <form method="POST" style="display: inline; margin: 0;">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <input type="hidden" name="action" value="approve_profile_verification">
                                                    <button type="submit" class="btn-primary" style="font-size: 12px; padding: 6px 12px;"><i class="fa fa-check"></i> Approve</button>
                                                </form>
                                                <button type="button" onclick="openProfileRejectModal(<?= $req['id'] ?>)" class="btn-danger" style="font-size: 12px; padding: 6px 12px;"><i class="fa fa-times"></i> Reject</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Approved Claims Tab -->
            <div id="tab-verification-approved" style="display: none;">
                <?php if (empty($approved_verifications)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fa fa-id-card" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                        No approved profile claims.
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto; border: 1px solid var(--border-color); border-radius: 16px;">
                        <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color); text-align: left;">
                                    <th style="padding: 14px 16px;">User Details</th>
                                    <th style="padding: 14px 16px;">ID Type</th>
                                    <th style="padding: 14px 16px;">Uploaded ID Photo</th>
                                    <th style="padding: 14px 16px;">Approved Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_verifications as $req): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color); font-size: 14px;">
                                        <td style="padding: 16px;">
                                            <strong style="color: var(--text-dark);"><?= htmlspecialchars($req['username']) ?></strong>
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                                Email: <?= htmlspecialchars($req['email']) ?><br>
                                                Phone: <?= htmlspecialchars($req['phone_number']) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 16px; font-weight: 600; color: var(--text-dark);">
                                            <?= htmlspecialchars($req['id_type']) ?>
                                        </td>
                                        <td style="padding: 16px;">
                                            <a href="../<?= htmlspecialchars($req['id_photo']) ?>" target="_blank" title="Click to view full screen">
                                                <img src="../<?= htmlspecialchars($req['id_photo']) ?>" style="max-width: 100px; max-height: 60px; border-radius: 6px; border: 1px solid var(--border-color); object-fit: contain; cursor: pointer;">
                                            </a>
                                        </td>
                                        <td style="padding: 16px; color: var(--text-muted); font-size: 12px;">
                                            <?= date('M d, Y H:i', strtotime($req['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Rejected Claims Tab -->
            <div id="tab-verification-rejected" style="display: none;">
                <?php if (empty($rejected_verifications)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fa fa-id-card" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                        No rejected profile claims.
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto; border: 1px solid var(--border-color); border-radius: 16px;">
                        <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color); text-align: left;">
                                    <th style="padding: 14px 16px;">User Details</th>
                                    <th style="padding: 14px 16px;">ID Type</th>
                                    <th style="padding: 14px 16px;">Uploaded ID Photo</th>
                                    <th style="padding: 14px 16px;">Rejection Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejected_verifications as $req): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color); font-size: 14px;">
                                        <td style="padding: 16px;">
                                            <strong style="color: var(--text-dark);"><?= htmlspecialchars($req['username']) ?></strong>
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                                Email: <?= htmlspecialchars($req['email']) ?><br>
                                                Phone: <?= htmlspecialchars($req['phone_number']) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 16px; font-weight: 600; color: var(--text-dark);">
                                            <?= htmlspecialchars($req['id_type']) ?>
                                        </td>
                                        <td style="padding: 16px;">
                                            <a href="../<?= htmlspecialchars($req['id_photo']) ?>" target="_blank" title="Click to view full screen">
                                                <img src="../<?= htmlspecialchars($req['id_photo']) ?>" style="max-width: 100px; max-height: 60px; border-radius: 6px; border: 1px solid var(--border-color); object-fit: contain; cursor: pointer;">
                                            </a>
                                        </td>
                                        <td style="padding: 16px; color: #991b1b; font-size: 12px; max-width: 250px; word-break: break-word;">
                                            <?= htmlspecialchars($req['rejection_reason']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Profile Rejection Modal Overlay -->
<div id="profileRejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 28px; border-radius: 16px; width: 100%; max-width: 420px; box-shadow: var(--shadow-lg);">
        <h3 style="margin-top: 0; margin-bottom: 12px; font-weight: 800; color: #991b1b;">Reject Verification Request</h3>
        <form method="POST">
            <input type="hidden" id="profile_reject_req_id" name="request_id">
            <input type="hidden" name="action" value="reject_profile_verification">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 13px;">Reason for Rejection</label>
                <textarea name="rejection_reason" required placeholder="Describe why this ID document was rejected e.g., Photo is blurry, name does not match profile, etc." style="width: 100%; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; font-size: 13px; min-height: 100px; resize: vertical; outline: none;"></textarea>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeProfileRejectModal()" class="btn-secondary" style="font-size: 13px; padding: 8px 16px;">Cancel</button>
                <button type="submit" class="btn-danger" style="font-size: 13px; padding: 8px 16px;">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>

<!-- User Management Section -->
<div id="section-users" class="settings-section">
    <button class="back-btn" onclick="showSection('grid')">
        <i class="fa fa-arrow-left"></i> Back to Dashboard
    </button>

    <div
        style="background: var(--white); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
        <div
            style="padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="margin: 0; font-size: 20px; font-weight: 800;">User Management</h2>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-muted);">Manage platform users and
                    sensitive data</p>
            </div>
            <div style="position: relative; flex: 1; max-width: 300px;">
                <i class="fa fa-search"
                    style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px;"></i>
                <input type="text" id="userSearch" placeholder="Search users..." onkeyup="filterUsers()"
                    style="width: 100%; padding: 10px 14px 10px 40px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 14px; outline: none; transition: border-color 0.2s; background: #f8fafc;">
            </div>
        </div>

        <div style="padding: 24px; background: #f8fafc;">
            <!-- Segment Toggle -->
            <div style="display: flex; gap: 16px; margin-bottom: 32px; justify-content: center;">
                <button onclick="toggleUserSegment('staff')" id="btn-seg-staff" class="segment-btn active">
                    <i class="fa fa-shield-alt"></i> Executive Staff
                </button>
                <button onclick="toggleUserSegment('community')" id="btn-seg-community" class="segment-btn">
                    <i class="fa fa-users"></i> Community Members
                </button>
            </div>

            <?php if (empty($all_users)): ?>
                <div
                    style="text-align: center; padding: 60px 20px; background: white; border-radius: 32px; border: 2px dashed #e2e8f0;">
                    <div
                        style="width: 80px; height: 80px; background: #f1f5f9; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fa fa-users-slash" style="font-size: 32px; color: #94a3b8;"></i>
                    </div>
                    <h3 style="margin: 0; color: #1e293b; font-size: 18px; font-weight: 800;">No Members Found</h3>
                    <p style="margin: 8px 0 0 0; color: #64748b; font-size: 14px;">The directory is currently empty.</p>
                </div>
            <?php else: ?>
                <div id="userGrid"
                    style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
                    <?php foreach ($all_users as $user): ?>
                        <?php
                        $is_admin = $user['is_admin'];
                        $emailParts = explode('@', $user['email'] ?? '');
                        $maskEmail = count($emailParts) > 1
                            ? substr($emailParts[0], 0, 2) . '***@' . $emailParts[1]
                            : 'Invalid Email';
                        $maskPhone = substr($user['phone'] ?? 'N/A', 0, 3) . '****' . substr($user['phone'] ?? 'N/A', -2);
                        ?>
                        <div class="user-card" data-name="<?= strtolower(htmlspecialchars($user['username'])) ?>"
                            data-is-admin="<?= $user['is_admin'] ?>"
                            style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 28px; padding: 24px; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; display: <?= $is_admin ? 'block' : 'none' ?>; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">

                            <!-- Premium Header -->
                            <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 24px;">
                                <div style="position: relative;">
                                    <div
                                        style="width: 64px; height: 64px; border-radius: 20px; overflow: hidden; background: #f1f5f9; border: 3px solid #fff; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
                                        <?php
                                        $pic = $user['profile_picture'];
                                        $pic_path = (!empty($pic) && file_exists('../' . $pic)) ? '../' . $pic : null;
                                        ?>
                                        <?php if ($pic_path): ?>
                                            <img src="<?= $pic_path ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div
                                                style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #cbd5e1;">
                                                <i class="fa fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($user['is_admin']): ?>
                                        <div
                                            style="position: absolute; -right: 6px; -bottom: 6px; background: #8b5cf6; color: white; width: 24px; height: 24px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 10px; border: 2px solid white; box-shadow: 0 4px 6px rgba(139,92,246,0.3);">
                                            <i class="fa fa-check"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <h4
                                        style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </h4>
                                    <div style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px;">
                                        <?php if ($user['is_admin']): ?>
                                            <?php
                                            $perms = explode(',', $user['permissions'] ?? '');
                                            if ($user['permissions'] === '*') {
                                                echo '<span class="perm-badge full-access">Full Root Access</span>';
                                            } else if (empty($user['permissions'])) {
                                                echo '<span class="perm-badge">No Permissions</span>';
                                            } else {
                                                foreach ($perms as $p) {
                                                    $lbl = str_replace('_', ' ', str_replace('manage_', '', $p));
                                                    echo '<span class="perm-badge">' . ucfirst($lbl) . '</span>';
                                                }
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span
                                                style="font-size: 9px; font-weight: 900; color: #64748b; background: #f1f5f9; padding: 3px 10px; border-radius: 100px; text-transform: uppercase; letter-spacing: 0.8px; border: 1px solid #e2e8f0;">Community
                                                Member</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Modern Stats Grid -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px;">
                                <div
                                    style="background: #f8fafc; border-radius: 18px; padding: 12px; border: 1px solid #f1f5f9; text-align: center;">
                                    <div
                                        style="font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
                                        Active</div>
                                    <div style="font-size: 20px; font-weight: 900; color: #10b981;"><?= $user['active_ads'] ?>
                                    </div>
                                </div>
                                <div
                                    style="background: #f8fafc; border-radius: 18px; padding: 12px; border: 1px solid #f1f5f9; text-align: center;">
                                    <div
                                        style="font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
                                        Total</div>
                                    <div style="font-size: 20px; font-weight: 900; color: #334155;"><?= $user['total_ads'] ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Secure Data Section -->
                            <div id="secure-info-<?= $user['id'] ?>" data-mask-email="<?= htmlspecialchars($maskEmail) ?>"
                                data-mask-phone="<?= htmlspecialchars($maskPhone) ?>"
                                style="background: #ffffff; border: 1.5px solid #f1f5f9; border-radius: 20px; padding: 16px; margin-bottom: 24px;">
                                <div
                                    style="font-size: 13px; margin-bottom: 12px; color: #475569; display: flex; align-items: center; gap: 12px; font-weight: 500;">
                                    <div
                                        style="width: 28px; height: 28px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                        <i class="fa fa-envelope" style="font-size: 12px;"></i>
                                    </div>
                                    <span
                                        style="font-family: 'Courier New', monospace; letter-spacing: -0.5px;"><?= htmlspecialchars($maskEmail) ?></span>
                                </div>
                                <div
                                    style="font-size: 13px; color: #475569; display: flex; align-items: center; gap: 12px; font-weight: 500;">
                                    <div
                                        style="width: 28px; height: 28px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                        <i class="fa fa-phone-alt" style="font-size: 12px;"></i>
                                    </div>
                                    <span style="letter-spacing: 1px;"><?= htmlspecialchars($maskPhone) ?></span>
                                </div>
                            </div>

                            <div id="user-ads-<?= $user['id'] ?>" class="user-ads-dropdown"
                                style="display: none; margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; max-height: 250px; overflow-y: auto; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                                <!-- Ads will load here -->
                            </div>
                            <!-- Interactive Actions -->
                            <div style="display: flex; gap: 8px;">
                                <button onclick="revealData(<?= $user['id'] ?>)" class="premium-action-btn reveal-btn"
                                    title="Reveal Details">
                                    <i class="fa fa-key"></i>
                                </button>

                                <?php if (($_SESSION['admin_permissions'] ?? '') === '*'): ?>
                                    <button
                                        onclick="openEditPermissionsModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['permissions'] ?? '') ?>', <?= $user['is_admin'] ?>)"
                                        class="premium-action-btn edit-btn"
                                        style="background: #fff7ed; color: #c2410c; border: 1px solid #fdba74;" title="Edit Access">
                                        <i class="fa fa-user-gear"></i>
                                    </button>
                                <?php endif; ?>

                                <?php
                                // Only show reset button if:
                                // 1. The target is NOT a root admin
                                // 2. OR the logged-in admin IS the root admin
                                $can_reset = ($user['permissions'] !== '*') || (($_SESSION['admin_permissions'] ?? '') === '*');
                                ?>
                                <?php if ($can_reset): ?>
                                    <button
                                        onclick="openResetModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                        class="premium-action-btn reset-btn" title="Reset Password">
                                        <i class="fa fa-lock-open"></i>
                                    </button>
                                <?php endif; ?>
                                <button onclick="toggleUserAds(<?= $user['id'] ?>)" id="ads-btn-<?= $user['id'] ?>"
                                    class="premium-action-btn listings-btn" style="flex: 2;">
                                    <i class="fa fa-layer-group"></i> Listings
                                </button>
                                <?php if (!$user['is_admin'] || (($_SESSION['admin_permissions'] ?? '') === '*' && $user['permissions'] !== '*')): ?>
                                    <button
                                        onclick="adminDeleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                        class="premium-action-btn delete-btn" title="Delete User">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="adminResetModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); z-index: 3000; align-items: center; justify-content: center;">
    <div
        style="background: white; padding: 32px; border-radius: 28px; width: 90%; max-width: 400px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <h3 style="margin: 0 0 8px 0; color: #0f172a; font-weight: 800;">Reset Password</h3>
        <p id="resetUserLabel" style="margin: 0 0 24px 0; color: #64748b; font-size: 14px;"></p>

        <input type="password" id="admin_new_password" placeholder="Enter new password"
            style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; margin-bottom: 24px; font-size: 15px;">

        <div style="display: flex; gap: 12px;">
            <button onclick="closeResetModal()"
                style="flex: 1; padding: 12px; border-radius: 16px; border: 1.5px solid #e2e8f0; background: white; color: #64748b; font-weight: 700; cursor: pointer;">Cancel</button>
            <button onclick="confirmAdminReset()"
                style="flex: 1; padding: 12px; border-radius: 16px; border: none; background: #8b5cf6; color: white; font-weight: 700; cursor: pointer;">Update</button>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div id="createUserModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(12px); z-index: 4000; align-items: center; justify-content: center; transition: all 0.3s ease;">
    <div
        style="background: white; padding: 36px; border-radius: 36px; width: 95%; max-width: 550px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.3); border: 1px solid rgba(226, 232, 240, 0.8); position: relative; scrollbar-width: thin;">

        <!-- Header -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; border-bottom: 2px solid #f1f5f9; padding-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div
                    style="width: 48px; height: 48px; background: #ecfdf5; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #10b981; font-size: 20px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.15);">
                    <i class="fa fa-user-plus"></i>
                </div>
                <div>
                    <h3 style="margin: 0; color: #0f172a; font-weight: 850; font-size: 22px; letter-spacing: -0.5px;">
                        Onboard Staff</h3>
                    <p style="margin: 2px 0 0 0; color: #64748b; font-size: 13px;">Create a secure credentials profile
                        for new administrators.</p>
                </div>
            </div>
            <button onclick="closeCreateUserModal()"
                style="background: #f1f5f9; border: none; width: 40px; height: 40px; border-radius: 14px; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;"
                onmouseover="this.style.background='#fee2e2'; this.style.color='#ef4444';"
                onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b';">
                <i class="fa fa-times" style="font-size: 16px;"></i>
            </button>
        </div>

        <!-- Form fields inside grid layout -->
        <div style="display: grid; gap: 20px;">
            <!-- Row 1: Username & Password -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label
                        style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-user" style="color: #10b981;"></i> Username
                    </label>
                    <input type="text" id="new_username" placeholder="e.g. john_doe"
                        style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 14px; color: #1e293b; font-weight: 600; outline: none; transition: border-color 0.2s;"
                        onfocus="this.style.borderColor='#10b981';" onblur="this.style.borderColor='#e2e8f0';">
                </div>
                <div class="form-group">
                    <label
                        style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-key" style="color: #10b981;"></i> Password
                    </label>
                    <input type="password" id="new_password" placeholder="Set temporary key"
                        style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 14px; color: #1e293b; font-weight: 600; outline: none; transition: border-color 0.2s;"
                        onfocus="this.style.borderColor='#10b981';" onblur="this.style.borderColor='#e2e8f0';">
                </div>
            </div>

            <!-- Row 2: Email & Phone -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label
                        style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-envelope" style="color: #10b981;"></i> Email Address
                    </label>
                    <input type="email" id="new_email" placeholder="john@enteangadi.com"
                        style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 14px; color: #1e293b; font-weight: 600; outline: none; transition: border-color 0.2s;"
                        onfocus="this.style.borderColor='#10b981';" onblur="this.style.borderColor='#e2e8f0';">
                </div>
                <div class="form-group">
                    <label
                        style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-phone" style="color: #10b981;"></i> Phone Number
                    </label>
                    <input type="text" id="new_phone" placeholder="10-digit number"
                        style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 14px; color: #1e293b; font-weight: 600; outline: none; transition: border-color 0.2s;"
                        onfocus="this.style.borderColor='#10b981';" onblur="this.style.borderColor='#e2e8f0';">
                </div>
            </div>

            <!-- Clearance Dropdown -->
            <div class="form-group">
                <label
                    style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-shield-alt" style="color: #10b981;"></i> Account Clearance Role
                </label>
                <select id="new_is_admin" onchange="togglePermissionMatrix(this.value)"
                    style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 14px; background: white; font-weight: 700; color: #1e293b; cursor: pointer; transition: all 0.2s; outline: none;"
                    onfocus="this.style.borderColor='#10b981';" onblur="this.style.borderColor='#e2e8f0';">
                    <option value="0">Community Member (Standard Platform Account)</option>
                    <option value="1">System Administrator (Custom Permission Matrix)</option>
                </select>
            </div>

            <!-- Permission Matrix (Hidden by default) -->
            <div id="permissionMatrix"
                style="display: none; background: #faf5ff; padding: 24px; border-radius: 24px; border: 1.5px solid #f3e8ff; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); transition: all 0.3s ease;">
                <label
                    style="font-size: 11px; font-weight: 800; color: #7e22ce; text-transform: uppercase; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-unlock-alt"></i> Fine-Grained Permissions
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <label
                        style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #7e22ce; font-weight: 800; cursor: pointer; grid-column: 1 / -1; background: #f3e8ff; padding: 12px 16px; border-radius: 14px; border: 1.5px dashed #c084fc; transition: all 0.2s;"
                        onmouseover="this.style.background='#ebe0ff';" onmouseout="this.style.background='#f3e8ff';">
                        <input type="checkbox" class="perm-check" value="*"
                            style="width: 18px; height: 18px; accent-color: #8b5cf6;"> Grant Full Root Access
                    </label>
                    <?php foreach ($available_permissions as $perm):
                        $checked = in_array($perm['perm_key'], ['manage_users', 'manage_pending', 'manage_listings']) ? 'checked' : '';
                        ?>
                        <label
                            style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #3b0764; font-weight: 700; cursor: pointer; background: white; padding: 10px 14px; border-radius: 12px; border: 1px solid #e9d5ff; transition: all 0.2s;"
                            onmouseover="this.style.borderColor='#c084fc'; this.style.background='#fdfaff';"
                            onmouseout="this.style.borderColor='#e9d5ff'; this.style.background='white';">
                            <input type="checkbox" class="perm-check" value="<?= htmlspecialchars($perm['perm_key']) ?>"
                                <?= $checked ?> style="width: 16px; height: 16px; accent-color: #8b5cf6;">
                            <?= htmlspecialchars($perm['perm_label']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <button onclick="confirmCreateUser()"
            style="width: 100%; margin-top: 32px; padding: 16px; border-radius: 18px; border: none; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; font-weight: 800; font-size: 15px; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(16,185,129,0.35); display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;"
            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 20px -3px rgba(16,185,129,0.45)';"
            onmouseout="this.style.transform='none'; this.style.boxShadow='0 10px 15px -3px rgba(16,185,129,0.35)';">
            <i class="fa fa-user-check"></i> PROVISION STAFF ACCOUNT
        </button>
    </div>
</div>

<!-- Edit Permissions Modal -->
<div id="editPermissionsModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(12px); z-index: 4000; align-items: center; justify-content: center; transition: all 0.3s ease;">
    <div
        style="background: white; padding: 36px; border-radius: 36px; width: 95%; max-width: 550px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.3); border: 1px solid rgba(226, 232, 240, 0.8); position: relative; scrollbar-width: thin;">

        <!-- Header -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; border-bottom: 2px solid #f1f5f9; padding-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div
                    style="width: 48px; height: 48px; background: #f5f3ff; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #8b5cf6; font-size: 20px; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.15);">
                    <i class="fa fa-user-shield"></i>
                </div>
                <div>
                    <h3 style="margin: 0; color: #0f172a; font-weight: 850; font-size: 22px; letter-spacing: -0.5px;">
                        Recalibrate Access</h3>
                    <p style="margin: 2px 0 0 0; color: #64748b; font-size: 13px;">Adjust privileges for <strong
                            id="editPermUsername" style="color: #1e293b;"></strong></p>
                </div>
            </div>
            <button onclick="closeEditPermissionsModal()"
                style="background: #f1f5f9; border: none; width: 40px; height: 40px; border-radius: 14px; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;"
                onmouseover="this.style.background='#fee2e2'; this.style.color='#ef4444';"
                onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b';">
                <i class="fa fa-times" style="font-size: 16px;"></i>
            </button>
        </div>

        <input type="hidden" id="editPermUserId">

        <div style="display: grid; gap: 24px;">
            <!-- Administrative Access Toggle -->
            <div>
                <label
                    style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 10px; display: block; letter-spacing: 0.5px;">Account
                    Status</label>
                <label
                    style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 16px 20px; border-radius: 20px; border: 1.5px solid #e2e8f0; cursor: pointer; transition: all 0.2s;"
                    onmouseover="this.style.borderColor='#8b5cf6';" onmouseout="this.style.borderColor='#e2e8f0';">
                    <span style="font-size: 15px; font-weight: 750; color: #1e293b;">Administrative Access
                        Enabled</span>
                    <input type="checkbox" id="editIsAdmin"
                        style="width: 22px; height: 22px; accent-color: #8b5cf6; cursor: pointer;">
                </label>
            </div>

            <!-- Permission Matrix -->
            <div id="editPermissionMatrix"
                style="background: #faf5ff; padding: 24px; border-radius: 24px; border: 1.5px solid #f3e8ff; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                <label
                    style="font-size: 11px; font-weight: 800; color: #7e22ce; text-transform: uppercase; margin-bottom: 16px; display: flex; align-items: center; gap: 6px; letter-spacing: 0.5px;">
                    <i class="fa fa-unlock-alt"></i> Access Matrix Privileges
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <label
                        style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #7e22ce; font-weight: 800; cursor: pointer; grid-column: 1 / -1; background: #f3e8ff; padding: 12px 16px; border-radius: 14px; border: 1.5px dashed #c084fc; transition: all 0.2s;"
                        onmouseover="this.style.background='#ebe0ff';" onmouseout="this.style.background='#f3e8ff';">
                        <input type="checkbox" class="edit-perm-check" value="*"
                            style="width: 18px; height: 18px; accent-color: #8b5cf6;"> Grant Full Root Access
                    </label>
                    <?php foreach ($available_permissions as $perm): ?>
                        <label
                            style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #3b0764; font-weight: 700; cursor: pointer; background: white; padding: 10px 14px; border-radius: 12px; border: 1px solid #e9d5ff; transition: all 0.2s;"
                            onmouseover="this.style.borderColor='#c084fc'; this.style.background='#fdfaff';"
                            onmouseout="this.style.borderColor='#e9d5ff'; this.style.background='white';">
                            <input type="checkbox" class="edit-perm-check"
                                value="<?= htmlspecialchars($perm['perm_key']) ?>"
                                style="width: 16px; height: 16px; accent-color: #8b5cf6;">
                            <?= htmlspecialchars($perm['perm_label']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <button onclick="confirmUpdatePermissions()"
            style="width: 100%; margin-top: 32px; padding: 16px; border-radius: 18px; border: none; background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: white; font-weight: 800; font-size: 15px; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(139,92,246,0.35); display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;"
            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 20px -3px rgba(139,92,246,0.45)';"
            onmouseout="this.style.transform='none'; this.style.boxShadow='0 10px 15px -3px rgba(139,92,246,0.35)';">
            <i class="fa fa-save"></i> SAVE PRIVILEGES CONFIG
        </button>
    </div>
</div>

<!-- Password Verification Modal -->
<div id="revealModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 20000; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
    <div
        style="background: white; padding: 32px; border-radius: 28px; width: 90%; max-width: 400px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="text-align: center; margin-bottom: 28px;">
            <div
                style="width: 64px; height: 64px; background: #fff1f2; color: #e11d48; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 28px; transform: rotate(-10deg);">
                <i class="fa fa-fingerprint"></i>
            </div>
            <h3 style="margin: 0; font-size: 22px; font-weight: 800; color: var(--text-dark);">Identity Verification
            </h3>
            <p style="color: var(--text-muted); font-size: 14px; margin-top: 8px; line-height: 1.5;">Please confirm your
                administrator password to decrypt this user's personal information.</p>
        </div>

        <input type="password" id="adminPassInput" class="form-control" placeholder="Admin Password"
            style="margin-bottom: 20px; border-radius: 14px; padding: 16px; border: 2px solid #f1f5f9; outline: none; transition: border-color 0.2s; font-size: 16px; text-align: center;">

        <div style="display: flex; gap: 14px;">
            <button onclick="closeRevealModal()"
                style="flex: 1; border-radius: 14px; padding: 14px; font-weight: 800; border: none; background: #f1f5f9; color: #64748b; cursor: pointer; transition: background 0.2s;">CANCEL</button>
            <button onclick="confirmReveal()"
                style="flex: 1.5; border-radius: 14px; padding: 14px; font-weight: 800; border: none; background: var(--primary-green); color: white; cursor: pointer; transition: opacity 0.2s;">VERIFY
                ACCESS</button>
        </div>
    </div>
</div>

<!-- Admin Action Security Confirmation Modal -->
<div id="adminActionSecurityModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 25000; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
    <div
        style="background: white; padding: 32px; border-radius: 28px; width: 90%; max-width: 430px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); text-align: center;">
        <div
            style="width: 64px; height: 64px; background: #fef2f2; color: #dc2626; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 26px;">
            <i class="fa fa-shield-alt"></i>
        </div>
        <h3 id="adminSecurityModalTitle" style="margin: 0; font-size: 20px; font-weight: 800; color: #1e293b;">
            Security Verification Required
        </h3>
        <p id="adminSecurityModalDescription" style="color: #64748b; font-size: 13.5px; margin: 10px 0 20px; line-height: 1.5;">
            To execute this permanent deletion, please verify your account credentials.
        </p>

        <form id="adminSecurityModalForm" onsubmit="event.preventDefault(); submitAdminSecurityAction();" style="margin: 0;">
            <div style="margin-bottom: 20px;">
                <input type="password" id="adminSecurityPasswordInput" class="form-control" placeholder="Enter Your Password" required autocomplete="current-password"
                    style="border-radius: 14px; padding: 14px; border: 2px solid #e2e8f0; outline: none; transition: border-color 0.2s; font-size: 15px; text-align: center; width: 100%; box-sizing: border-box;">
                <div id="adminSecurityErrorMsg" style="display: none; color: #dc2626; font-size: 12.5px; font-weight: 600; margin-top: 8px;"></div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="closeAdminSecurityModal()"
                    style="flex: 1; border-radius: 14px; padding: 12px; font-weight: 700; border: none; background: #f1f5f9; color: #64748b; cursor: pointer; transition: background 0.2s;">
                    CANCEL
                </button>
                <button type="submit" id="adminSecurityConfirmBtn"
                    style="flex: 1.5; border-radius: 14px; padding: 12px; font-weight: 700; border: none; background: #dc2626; color: white; cursor: pointer; transition: opacity 0.2s;">
                    CONFIRM DELETE
                </button>
            </div>
        </form>
    </div>
</div>

<div id="section-contact"
    class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'contact') ? 'active' : '' ?>">
    <button class="back-btn" onclick="showSection('grid')">
        <i class="fa fa-arrow-left"></i> Back to Dashboard
    </button>

    <div class="settings-form-wrapper">
        <div class="section-header">
            <h2>Contact & Social Info</h2>
        </div>

        <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="contact">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <div class="form-group-header">
                        <label>Support Email</label>
                        <span class="clear-field" onclick="clearField('support_email')"><i
                                class="fa fa-times-circle"></i> Clear</span>
                    </div>
                    <input type="email" id="support_email" name="support_email" class="form-control"
                        value="<?= htmlspecialchars($app_settings['support_email'] ?? '') ?>"
                        placeholder="support@example.com">
                </div>
                <div class="form-group">
                    <div class="form-group-header">
                        <label>Support Phone</label>
                        <span class="clear-field" onclick="clearField('support_phone')"><i
                                class="fa fa-times-circle"></i> Clear</span>
                    </div>
                    <input type="text" id="support_phone" name="support_phone" class="form-control"
                        value="<?= htmlspecialchars($app_settings['support_phone'] ?? '') ?>"
                        placeholder="+91 9876543210">
                </div>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <div class="form-group-header">
                    <label>WhatsApp Number</label>
                    <span class="clear-field" onclick="clearField('whatsapp_number')"><i class="fa fa-times-circle"></i>
                        Clear</span>
                </div>
                <input type="text" id="whatsapp_number" name="whatsapp_number" class="form-control"
                    value="<?= htmlspecialchars($app_settings['whatsapp_number'] ?? '') ?>"
                    placeholder="WhatsApp number with country code">
            </div>

            <div class="form-divider"><span>SOCIAL MEDIA LINKS</span></div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <div class="form-group-header">
                        <label><i class="fab fa-facebook" style="color: #1877F2;"></i> Facebook URL</label>
                        <span class="clear-field" onclick="clearField('facebook_url')"><i
                                class="fa fa-times-circle"></i> Clear</span>
                    </div>
                    <input type="url" id="facebook_url" name="facebook_url" class="form-control"
                        value="<?= htmlspecialchars($app_settings['facebook_url'] ?? '') ?>"
                        placeholder="https://facebook.com/yourpage">
                </div>
                <div class="form-group">
                    <div class="form-group-header">
                        <label><i class="fab fa-instagram" style="color: #E4405F;"></i> Instagram URL</label>
                        <span class="clear-field" onclick="clearField('instagram_url')"><i
                                class="fa fa-times-circle"></i> Clear</span>
                    </div>
                    <input type="url" id="instagram_url" name="instagram_url" class="form-control"
                        value="<?= htmlspecialchars($app_settings['instagram_url'] ?? '') ?>"
                        placeholder="https://instagram.com/yourpage">
                </div>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <div class="form-group-header">
                    <label><i class="fab fa-twitter" style="color: #1DA1F2;"></i> Twitter URL</label>
                    <span class="clear-field" onclick="clearField('twitter_url')"><i class="fa fa-times-circle"></i>
                        Clear</span>
                </div>
                <input type="url" id="twitter_url" name="twitter_url" class="form-control"
                    value="<?= htmlspecialchars($app_settings['twitter_url'] ?? '') ?>"
                    placeholder="https://twitter.com/yourpage">
            </div>

            <div class="form-divider"><span>MOBILE APP DOWNLOAD LINKS</span></div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <div class="form-group-header">
                        <label><i class="fab fa-google-play" style="color: #34A853;"></i> Google Play Store URL</label>
                        <span class="clear-field" onclick="clearField('play_store_url')"><i
                                class="fa fa-times-circle"></i> Clear</span>
                    </div>
                    <input type="url" id="play_store_url" name="play_store_url" class="form-control"
                        value="<?= htmlspecialchars($app_settings['play_store_url'] ?? '') ?>"
                        placeholder="https://play.google.com/store/apps/details?id=...">
                </div>
                <div class="form-group">
                    <div class="form-group-header">
                        <label><i class="fab fa-apple" style="color: #000000;"></i> Apple App Store URL</label>
                        <span class="clear-field" onclick="clearField('app_store_url')"><i
                                class="fa fa-times-circle"></i> Clear</span>
                    </div>
                    <input type="url" id="app_store_url" name="app_store_url" class="form-control"
                        value="<?= htmlspecialchars($app_settings['app_store_url'] ?? '') ?>"
                        placeholder="https://apps.apple.com/app/...">
                </div>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Save
                Contact Details</button>
        </form>
    </div>
</div>

</div>

<script>
    function clearField(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
            field.focus();
        }
    }

    window.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash;
        if (hash === '#user') {
            showSection('users');
        } else if (hash === '#feedback') {
            showSection('feedback');
        }
    });

    function showSection(sectionId) {
        // Hide all sections
        document.querySelectorAll('.settings-section').forEach(section => {
            section.classList.remove('active');
        });

        const adminContent = document.querySelector('.admin-content');

        // Show target section
        if (sectionId === 'grid') {
            document.getElementById('grid-view').classList.add('active');
            window.location.hash = '';
            if (adminContent) {
                adminContent.style.overflow = 'auto';
                adminContent.style.padding = '32px';
            }
        } else {
            const section = document.getElementById('section-' + sectionId);
            if (section) {
                section.classList.add('active');
                window.location.hash = sectionId;
            } else if (sectionId === 'security') { // Map legacy ID
                document.getElementById('section-security').classList.add('active');
                window.location.hash = 'security';
            }

            if (adminContent) {
                if (sectionId === 'admin_messages') {
                    adminContent.style.overflow = 'hidden';
                    adminContent.style.padding = '16px 32px';
                } else {
                    adminContent.style.overflow = 'auto';
                    adminContent.style.padding = '32px';
                }
            }
        }
    }

    // Support for deep linking via hash
    window.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash.substring(1);
        if (hash === 'branding' || hash === 'password' || hash === 'general' || hash === 'contact' || hash === 'approval' || hash === 'pending' || hash === 'manage_ads' || hash === 'closure_feedback' || hash === 'users' || hash === 'privileges' || hash === 'search_delete_ads' || hash === 'profile_verifications' || hash === 'admin_post_ad' || hash === 'database_backup' || hash === 'admin_messages') {
            showSection(hash);
            if (hash === 'admin_messages') {
                const firstChat = document.querySelector('.ea-chat-item');
                if (firstChat) {
                    const pid = firstChat.getAttribute('data-product-id');
                    const uid = firstChat.getAttribute('data-user-id');
                    if (pid && uid) openAdminChat(pid, uid);
                }
            }
        }
    });

    const adminL2Cats = <?= $admin_l2_json ?>;
    const adminCatDetails = <?= $admin_cat_details_json ?>;

    function adminUpdateTypeSelection(radio) {
        document.querySelectorAll('.admin-type-chip').forEach(el => {
            el.classList.remove('active');
            el.style.borderColor = '#e2e8f0';
            el.style.background = '#f8fafc';
            el.style.color = '#64748b';
        });
        const chip = document.getElementById('chip-type-' + radio.value);
        if (chip) {
            chip.classList.add('active');
            chip.style.borderColor = 'var(--primary-green)';
            chip.style.background = '#f0fdf4';
            chip.style.color = 'var(--primary-green-dark)';
        }
    }

    function adminOnParentCatChange(parentId) {
        const subSelect = document.getElementById('admin_sub_cat_select');
        subSelect.innerHTML = '<option value="">-- Choose Subcategory --</option>';

        if (parentId && adminL2Cats[parentId] && adminL2Cats[parentId].length > 0) {
            adminL2Cats[parentId].forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.id;
                opt.textContent = sub.name;
                subSelect.appendChild(opt);
            });
        } else if (parentId) {
            const opt = document.createElement('option');
            opt.value = parentId;
            opt.textContent = 'General / All';
            subSelect.appendChild(opt);
        }
        adminCheckPerishable();
    }

    function adminOnSubCatChange(subId) {
        adminCheckPerishable();
    }

    function adminCheckPerishable() {
        const subId = document.getElementById('admin_sub_cat_select').value;
        const parentId = document.getElementById('admin_parent_cat_select').value;
        const targetId = subId || parentId;
        const isPerishable = targetId && adminCatDetails[targetId] && adminCatDetails[targetId].is_perishable == 1;
        const badge = document.getElementById('admin_perishable_badge');
        const expiryInput = document.getElementById('admin_expiry_date');

        if (isPerishable) {
            badge.style.display = 'inline';
            expiryInput.required = true;
            expiryInput.style.borderColor = '#ef4444';
        } else {
            badge.style.display = 'none';
            expiryInput.required = false;
            expiryInput.style.borderColor = '#cbd5e1';
        }
    }

    function adminAutoDetectLocation() {
        const statusEl = document.getElementById('admin_loc_status');
        if (!navigator.geolocation) {
            statusEl.innerText = 'Geolocation is not supported by your browser.';
            return;
        }
        statusEl.innerText = 'Detecting GPS location...';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                document.getElementById('admin_lat').value = lat;
                document.getElementById('admin_lng').value = lng;
                statusEl.innerHTML = `<span style="color: var(--primary-green); font-weight: 700;"><i class="fa fa-check-circle"></i> GPS Locked: ${lat.toFixed(4)}, ${lng.toFixed(4)}</span>`;

                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.display_name) {
                            const nameParts = [data.address.suburb || data.address.neighbourhood || data.address.village || data.address.town || data.address.city, data.address.state_district || data.address.state].filter(Boolean);
                            document.getElementById('admin_location_name').value = nameParts.join(', ') || data.display_name;
                        }
                    })
                    .catch(() => {});
            },
            (err) => {
                statusEl.innerText = 'Unable to retrieve GPS automatically. Please type your location name manually.';
            }
        );
    }

    function adminHandleImagePreview(input) {
        const grid = document.getElementById('admin_image_preview_grid');
        grid.innerHTML = '';
        const files = Array.from(input.files).slice(0, 10);

        files.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const card = document.createElement('div');
                card.style.cssText = 'position: relative; border-radius: 12px; overflow: hidden; border: 2px solid #e2e8f0; aspect-ratio: 1; background: #000;';
                card.id = `admin_img_card_${index}`;

                card.innerHTML = `
                    <img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">
                    <label style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.75); color: white; font-size: 10px; font-weight: 700; padding: 4px 6px; text-align: center; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px;">
                        <input type="radio" name="admin_cover_select" value="${index}" ${index === 0 ? 'checked' : ''} onchange="document.getElementById('admin_cover_index').value = ${index}" style="margin: 0; width: 12px; height: 12px;"> Cover
                    </label>
                `;
                grid.appendChild(card);
            };
            reader.readAsDataURL(file);
        });
    }

    function switchVerificationTab(tab) {
        // Toggle tabs visibility
        document.getElementById('tab-verification-pending').style.display = tab === 'pending' ? 'block' : 'none';
        document.getElementById('tab-verification-approved').style.display = tab === 'approved' ? 'block' : 'none';
        document.getElementById('tab-verification-rejected').style.display = tab === 'rejected' ? 'block' : 'none';

        // Toggle buttons style
        const btnPending = document.getElementById('btn-verification-pending');
        const btnApproved = document.getElementById('btn-verification-approved');
        const btnRejected = document.getElementById('btn-verification-rejected');

        [btnPending, btnApproved, btnRejected].forEach(btn => {
            btn.style.background = 'transparent';
            btn.style.color = '#64748b';
            btn.style.boxShadow = 'none';
        });

        const activeBtn = tab === 'pending' ? btnPending : (tab === 'approved' ? btnApproved : btnRejected);
        activeBtn.style.background = 'white';
        activeBtn.style.color = 'var(--primary-green-dark)';
        activeBtn.style.boxShadow = 'var(--shadow-sm)';
    }

    function openProfileRejectModal(id) {
        document.getElementById('profile_reject_req_id').value = id;
        document.getElementById('profileRejectModal').style.display = 'flex';
    }

    function closeProfileRejectModal() {
        document.getElementById('profileRejectModal').style.display = 'none';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function searchUserAds() {
        const phone = document.getElementById('delete-ads-search-phone').value.trim();
        if (!phone) {
            alert('Please enter a phone number.');
            return;
        }

        document.getElementById('search-ads-loader').style.display = 'block';
        document.getElementById('search-ads-results').style.display = 'none';
        document.getElementById('search-ads-empty').style.display = 'none';

        fetch('settings.php?action=search_ads_by_phone&phone=' + encodeURIComponent(phone))
            .then(res => res.json())
            .then(data => {
                document.getElementById('search-ads-loader').style.display = 'none';
                if (data.success) {
                    // Populate user info
                    document.getElementById('delete-ads-user-name').innerText = data.user.username;
                    document.getElementById('delete-ads-user-phone').innerText = data.user.phone_number;
                    if (data.user.profile_picture) {
                        document.getElementById('delete-ads-user-avatar').innerHTML = `<img src="../${data.user.profile_picture}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                    } else {
                        document.getElementById('delete-ads-user-avatar').innerHTML = data.user.username.charAt(0).toUpperCase();
                    }

                    // Populate table
                    const tbody = document.getElementById('delete-ads-table-body');
                    tbody.innerHTML = '';
                    
                    if (data.ads.length === 0) {
                        document.getElementById('search-ads-results').style.display = 'none';
                        document.getElementById('search-ads-empty').style.display = 'block';
                        return;
                    }

                    data.ads.forEach(ad => {
                        const tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid var(--border-color)';
                        
                        const typeBadge = ad.type === 'buy' ? '<span class="badge-wanted" style="font-size: 8px; padding: 2px 6px; display: inline-block;">Wanted</span>' : (ad.type === 'rent' ? '<span class="badge-rent" style="font-size: 8px; padding: 2px 6px; display: inline-block;">Rent</span>' : '<span class="badge-selling" style="font-size: 8px; padding: 2px 6px; display: inline-block;">Sale</span>');
                        const thumb = ad.thumbnail ? `<img src="../${ad.thumbnail}" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; flex-shrink: 0;">` : '<div style="width: 40px; height: 40px; border-radius: 8px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fa fa-image" style="color: #cbd5e1;"></i></div>';
                        const statusClass = ad.status === 'active' ? 'status-active' : (ad.status === 'pending' ? 'status-pending' : 'status-expired');
                        
                        tr.innerHTML = `
                            <td style="padding: 14px 16px;"><input type="checkbox" class="chk-delete-ad" value="${ad.id}" onchange="updateDeleteSelectedButton()"></td>
                            <td style="padding: 14px 16px; display: flex; align-items: center; gap: 12px;">
                                ${thumb}
                                <div style="min-width: 0;">
                                    <div style="font-weight: 700; font-size: 13px; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;">${escapeHtml(ad.title)}</div>
                                    <div style="margin-top: 2px;">${typeBadge}</div>
                                </div>
                            </td>
                            <td style="padding: 14px 16px; font-weight: 700; color: var(--primary-green-dark);">₹${parseFloat(ad.price).toLocaleString()}</td>
                            <td style="padding: 14px 16px;"><span class="status-badge ${statusClass}">${ad.status.toUpperCase()}</span></td>
                            <td style="padding: 14px 16px; font-size: 12px; color: var(--text-muted);">${new Date(ad.created_at).toLocaleDateString()}</td>
                            <td style="padding: 14px 16px; text-align: right;">
                                <button onclick="deleteSingleAd(${ad.id})" class="btn-danger" style="padding: 6px 12px; font-size: 11px;"><i class="fa fa-trash"></i> Delete</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    document.getElementById('chk-delete-all-ads').checked = false;
                    updateDeleteSelectedButton();
                    document.getElementById('search-ads-results').style.display = 'block';
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => {
                document.getElementById('search-ads-loader').style.display = 'none';
                console.error(err);
                alert('An error occurred during search.');
            });
    }

    // Add search listener for Enter key
    document.getElementById('delete-ads-search-phone').addEventListener('keyup', function(event) {
        if (event.key === 'Enter') {
            searchUserAds();
        }
    });

    function toggleSelectAllAds(master) {
        document.querySelectorAll('.chk-delete-ad').forEach(chk => {
            chk.checked = master.checked;
        });
        updateDeleteSelectedButton();
    }

    function updateDeleteSelectedButton() {
        const checked = document.querySelectorAll('.chk-delete-ad:checked');
        const btn = document.getElementById('btn-delete-selected-ads');
        const countSpan = document.getElementById('delete-selected-count');
        
        if (checked.length > 0) {
            countSpan.innerText = checked.length;
            btn.style.display = 'inline-flex';
        } else {
            btn.style.display = 'none';
        }
    }

    function deleteSingleAd(id) {
        requestAdminPassword({
            title: 'Delete User Listing',
            description: 'Are you sure you want to permanently delete this ad? This action cannot be undone.<br><br>Please enter your <b>Account Password</b> to confirm:',
            confirmText: 'DELETE AD',
            btnColor: '#dc2626'
        }, function(adminPassword) {
            performAdsDeletion([id], adminPassword);
        });
    }

    function deleteSelectedAds() {
        const checked = document.querySelectorAll('.chk-delete-ad:checked');
        const ids = Array.from(checked).map(chk => chk.value);
        if (ids.length === 0) return;
        
        requestAdminPassword({
            title: 'Delete Selected Listings',
            description: `Are you sure you want to permanently delete <b>${ids.length}</b> selected ad(s)? This will remove all photos and inquiries.<br><br>Please enter your <b>Account Password</b> to confirm:`,
            confirmText: `DELETE ${ids.length} ADS`,
            btnColor: '#dc2626'
        }, function(adminPassword) {
            performAdsDeletion(ids, adminPassword);
        });
    }

    function performAdsDeletion(ids, adminPassword) {
        const formData = new FormData();
        formData.append('action', 'delete_user_ads');
        formData.append('admin_password', adminPassword);
        ids.forEach(id => formData.append('ad_ids[]', id));

        fetch('settings.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Selected ads deleted successfully.');
                searchUserAds(); // Refresh listings
            } else {
                alert('Error: ' + (data.error || 'Failed to delete ads.'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('An error occurred during deletion.');
        });
    }

    function viewAdDetails(product) {
        document.getElementById('modalTitle').innerText = product.title;
        document.getElementById('modalId').innerText = '#' + product.unique_id;
        document.getElementById('modalPrice').innerText = '₹ ' + product.price;
        document.getElementById('modalLocation').querySelector('span').innerText = product.location;
        document.getElementById('modalDescription').innerText = product.description;

        const imagesContainer = document.getElementById('modalImages');
        imagesContainer.innerHTML = '';
        product.images.forEach(img => {
            if (img) {
                const div = document.createElement('div');
                div.style.minWidth = '120px';
                div.style.height = '120px';
                div.style.borderRadius = '12px';
                div.style.overflow = 'hidden';
                div.style.border = '1px solid #e2e8f0';
                div.innerHTML = `<img src="../${img}" style="width: 100%; height: 100%; object-fit: cover;">`;
                imagesContainer.appendChild(div);
            }
        });

        document.getElementById('adDetailsModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('adDetailsModal').style.display = 'none';
    }

    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });

    // Close on click outside
    window.onclick = function (event) {
        const modal = document.getElementById('adDetailsModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    function updateApprovalMode(toggle) {
        const input = document.getElementById('ad_approval_mode_input');
        const label = document.getElementById('switch-label');
        const descManual = document.getElementById('mode-desc-manual');
        const descAuto = document.getElementById('mode-desc-auto');

        if (toggle.checked) {
            input.value = 'auto';
            label.innerText = 'AUTO';
            descAuto.style.color = 'var(--primary-green)';
            descAuto.style.fontWeight = '700';
            descManual.style.color = 'inherit';
            descManual.style.fontWeight = 'normal';
        } else {
            input.value = 'manual';
            label.innerText = 'MANUAL';
            descManual.style.color = 'var(--primary-green)';
            descManual.style.fontWeight = '700';
            descAuto.style.color = 'inherit';
            descAuto.style.fontWeight = 'normal';
        }
    }

    // Support for browser back button
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.substring(1);
        if (hash) {
            showSection(hash);
        } else {
            showSection('grid');
        }
    });
    function switchFeedback(tab) {
        const adsTab = document.getElementById('tab-feedback-ads');
        const accountsTab = document.getElementById('tab-feedback-accounts');
        const adsBtn = document.getElementById('btn-feedback-ads');
        const accountsBtn = document.getElementById('btn-feedback-accounts');

        if (tab === 'ads') {
            adsTab.style.display = 'block';
            accountsTab.style.display = 'none';
            adsBtn.style.background = 'white';
            adsBtn.style.color = 'var(--primary-green-dark)';
            adsBtn.style.boxShadow = 'var(--shadow-sm)';
            accountsBtn.style.background = 'transparent';
            accountsBtn.style.color = '#64748b';
            accountsBtn.style.boxShadow = 'none';
        } else {
            adsTab.style.display = 'none';
            accountsTab.style.display = 'block';
            accountsBtn.style.background = 'white';
            accountsBtn.style.color = '#991b1b';
            accountsBtn.style.boxShadow = 'var(--shadow-sm)';
            adsBtn.style.background = 'transparent';
            adsBtn.style.color = '#64748b';
            adsBtn.style.boxShadow = 'none';
        }
    }
    // User Management Functions
    let currentTargetUserId = null;

    function filterUsers() {
        const query = document.getElementById('userSearch').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.user-card');

        cards.forEach(card => {
            const name = card.getAttribute('data-name');
            const isAdmin = card.getAttribute('data-is-admin') === '1';

            if (query === '') {
                // Initial State: Show only Admins
                card.style.display = isAdmin ? 'block' : 'none';
            } else {
                // Search State: Show all matches
                card.style.display = name.includes(query) ? 'block' : 'none';
            }
        });
    }

    function revealData(userId) {
        currentTargetUserId = userId;
        document.getElementById('adminPassInput').value = '';
        document.getElementById('revealModal').style.display = 'flex';
        document.getElementById('adminPassInput').focus();
    }

    function closeRevealModal() {
        document.getElementById('revealModal').style.display = 'none';
        currentTargetUserId = null;
    }

    function handlePrivilegeSelect(val) {
        const customWrapper = document.getElementById('custom_key_wrapper');
        const keyInput = document.getElementById('perm_key_input');
        const labelInput = document.getElementById('perm_label_input');
        const selectEl = document.getElementById('perm_key_select');

        if (val === 'custom') {
            customWrapper.style.display = 'block';
            keyInput.value = '';
            keyInput.placeholder = "Enter custom unique key (e.g. manage_billing)";
            labelInput.value = '';
        } else if (val === '') {
            customWrapper.style.display = 'none';
            keyInput.value = '';
            labelInput.value = '';
        } else {
            customWrapper.style.display = 'none';
            keyInput.value = val;

            // Find selected option's data-label attribute
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const label = selectedOption.getAttribute('data-label');
            if (label) {
                labelInput.value = label;
            }
        }
    }

    function confirmReveal() {
        const pass = document.getElementById('adminPassInput').value;
        if (!pass) return alert('Please enter password');

        const formData = new FormData();
        formData.append('action', 'reveal_user_data');
        formData.append('admin_password', pass);
        formData.append('target_user_id', currentTargetUserId);

        fetch('settings.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(res => {
                if (res.success) {
                    const infoDiv = document.getElementById(`secure-info-${currentTargetUserId}`);
                    infoDiv.style.transition = 'all 0.5s ease';
                    infoDiv.style.background = '#f0fdf4';
                    infoDiv.style.borderColor = '#10b981';
                    infoDiv.style.transform = 'scale(1.02)';

                    infoDiv.innerHTML = `
                            <div style="font-size: 13px; margin-bottom: 12px; color: #065f46; display: flex; align-items: center; gap: 12px; font-weight: 700;">
                                <div style="width: 28px; height: 28px; background: #10b981; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fa fa-envelope" style="font-size: 12px;"></i>
                                </div>
                                <span style="font-family: 'Courier New', monospace;">${res.data.email}</span>
                            </div>
                            <div style="font-size: 13px; color: #065f46; display: flex; align-items: center; gap: 12px; font-weight: 700; margin-bottom: 16px;">
                                <div style="width: 28px; height: 28px; background: #10b981; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fa fa-phone-alt" style="font-size: 12px;"></i>
                                </div>
                                <span>${res.data.phone || 'N/A'}</span>
                            </div>
                            <button onclick="reLockData(${currentTargetUserId})" 
                                    style="width: 100%; background: #065f46; color: white; border: none; padding: 8px; border-radius: 12px; font-size: 10px; font-weight: 800; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fa fa-lock"></i> HIDE SENSITIVE DATA
                            </button>
                        `;
                    closeRevealModal();

                    // Find the button and disable it
                    const btn = infoDiv.nextElementSibling;
                    btn.innerHTML = '<i class="fa fa-check"></i> DATA DECRYPTED';
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.borderColor = 'var(--primary-green)';
                    btn.style.color = 'var(--primary-green)';
                } else {
                    alert(res.message);
                }
            });
    }

    let currentResetUserId = null;

    function openResetModal(userId, username) {
        currentResetUserId = userId;
        document.getElementById('resetUserLabel').innerText = `Setting new password for ${username}`;
        document.getElementById('adminResetModal').style.display = 'flex';
    }

    function closeResetModal() {
        document.getElementById('adminResetModal').style.display = 'none';
        document.getElementById('admin_new_password').value = '';
    }

    function confirmAdminReset() {
        const newPass = document.getElementById('admin_new_password').value;
        if (!newPass) return alert('Enter a password');

        const formData = new FormData();
        formData.append('action', 'admin_reset_password');
        formData.append('target_user_id', currentResetUserId);
        formData.append('new_password', newPass);

        fetch('settings.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.success) closeResetModal();
            });
    }

    function openCreateUserModal() {
        document.getElementById('createUserModal').style.display = 'flex';
        togglePermissionMatrix('0'); // Reset visibility
    }

    function togglePermissionMatrix(val) {
        document.getElementById('permissionMatrix').style.display = (val == '1' ? 'block' : 'none');
    }

    function closeCreateUserModal() {
        document.getElementById('createUserModal').style.display = 'none';
        // Clear fields
        ['new_username', 'new_email', 'new_phone', 'new_password'].forEach(id => {
            document.getElementById(id).value = '';
        });
        document.querySelectorAll('.perm-check').forEach(c => c.checked = false);
    }

    function confirmCreateUser() {
        const username = document.getElementById('new_username').value;
        const email = document.getElementById('new_email').value;
        const phone = document.getElementById('new_phone').value;
        const password = document.getElementById('new_password').value;
        const isAdmin = document.getElementById('new_is_admin').value;

        // Collect permissions
        let perms = [];
        if (isAdmin == '1') {
            document.querySelectorAll('.perm-check:checked').forEach(c => perms.push(c.value));
        }

        if (!username || !email || !password) return alert('Fill all required fields');

        // Check if root asterisk was selected
        const finalPerms = perms.includes('*') ? '*' : perms.join(',');

        const formData = new FormData();
        formData.append('action', 'admin_create_user');
        formData.append('username', username);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('password', password);
        formData.append('is_admin', isAdmin);
        formData.append('permissions', finalPerms);

        fetch('settings.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.success) {
                    closeCreateUserModal();
                    location.reload();
                }
            });
    }

    function openEditPermissionsModal(userId, username, permissions, isAdmin) {
        document.getElementById('editPermUserId').value = userId;
        document.getElementById('editPermUsername').innerText = username;
        document.getElementById('editIsAdmin').checked = (isAdmin == 1);

        // Reset and populate checks
        const checks = document.querySelectorAll('.edit-perm-check');
        checks.forEach(c => {
            if (permissions === '*') {
                c.checked = true;
            } else {
                c.checked = permissions.split(',').includes(c.value);
            }
        });

        document.getElementById('editPermissionsModal').style.display = 'flex';
    }

    function closeEditPermissionsModal() {
        document.getElementById('editPermissionsModal').style.display = 'none';
    }

    function confirmUpdatePermissions() {
        const userId = document.getElementById('editPermUserId').value;
        const isAdmin = document.getElementById('editIsAdmin').checked ? 1 : 0;
        let perms = [];
        document.querySelectorAll('.edit-perm-check:checked').forEach(c => perms.push(c.value));

        const finalPerms = perms.includes('*') ? '*' : perms.join(',');

        const formData = new FormData();
        formData.append('action', 'admin_edit_permissions');
        formData.append('user_id', userId);
        formData.append('is_admin', isAdmin);
        formData.append('permissions', finalPerms);

        fetch('settings.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.success) {
                    closeEditPermissionsModal();
                    location.reload();
                }
            });
    }

    function adminDeleteUser(userId, username) {
        requestAdminPassword({
            title: 'Delete User Account',
            description: `CRITICAL ACTION: Are you sure you want to PERMANENTLY delete user <b>${username}</b>? All their listings, photos, and inquiries will be permanently removed.<br><br>Please enter your <b>Account Password</b> to confirm:`,
            confirmText: 'PERMANENTLY DELETE USER',
            btnColor: '#dc2626'
        }, function(adminPassword) {
            const formData = new FormData();
            formData.append('action', 'admin_delete_user');
            formData.append('target_user_id', userId);
            formData.append('admin_password', adminPassword);

            fetch('settings.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    if (res.success) location.reload();
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred while deleting the user.');
                });
        });
    }

    function reLockData(userId) {
        const infoDiv = document.getElementById(`secure-info-${userId}`);
        const maskEmail = infoDiv.getAttribute('data-mask-email');
        const maskPhone = infoDiv.getAttribute('data-mask-phone');

        infoDiv.style.background = '#ffffff';
        infoDiv.style.borderColor = '#f1f5f9';
        infoDiv.style.transform = 'scale(1)';

        infoDiv.innerHTML = `
            <div style="font-size: 13px; margin-bottom: 12px; color: #475569; display: flex; align-items: center; gap: 12px; font-weight: 500;">
                <div style="width: 28px; height: 28px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                    <i class="fa fa-envelope" style="font-size: 12px;"></i>
                </div>
                <span style="font-family: 'Courier New', monospace; letter-spacing: -0.5px;">${maskEmail}</span>
            </div>
            <div style="font-size: 13px; color: #475569; display: flex; align-items: center; gap: 12px; font-weight: 500;">
                <div style="width: 28px; height: 28px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                    <i class="fa fa-phone-alt" style="font-size: 12px;"></i>
                </div>
                <span style="letter-spacing: 1px;">${maskPhone}</span>
            </div>
        `;
    }

    function toggleUserSegment(seg) {
        // Toggle Buttons
        document.getElementById('btn-seg-staff').classList.toggle('active', seg === 'staff');
        document.getElementById('btn-seg-community').classList.toggle('active', seg === 'community');

        // Toggle Grid Content
        document.querySelectorAll('.user-card').forEach(card => {
            const isAdmin = card.getAttribute('data-is-admin') == '1';
            if (seg === 'staff') {
                card.style.display = isAdmin ? 'block' : 'none';
            } else {
                card.style.display = !isAdmin ? 'block' : 'none';
            }
        });
    }

    function toggleUserAds(userId) {
        const adDiv = document.getElementById(`user-ads-${userId}`);
        const btn = document.getElementById(`ads-btn-${userId}`);

        if (adDiv.style.display === 'block') {
            adDiv.style.display = 'none';
            btn.innerHTML = '<i class="fa fa-list"></i> VIEW LISTINGS';
            return;
        }

        // Fetch ads if first time
        if (!adDiv.classList.contains('loaded')) {
            adDiv.innerHTML = '<div style="padding: 10px; text-align: center; font-size: 11px; color: #64748b;"><i class="fa fa-spinner fa-spin"></i> Loading listings...</div>';

            const formData = new FormData();
            formData.append('action', 'get_user_ads');
            formData.append('target_user_id', userId);

            fetch('settings.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        let html = '<div style="padding: 12px 16px; font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #e2e8f0; background: #fff; position: sticky; top: 0; z-index: 5;">Activity History</div>';
                        res.data.forEach(ad => {
                            let color = '#10b981'; // active
                            if (ad.status === 'sold') color = '#3b82f6';
                            if (ad.status === 'expired') color = '#f59e0b';
                            if (ad.status === 'deleted') color = '#ef4444';

                            html += `
                                <div style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: white; transition: background 0.2s;">
                                    <div style="flex: 1; min-width: 0; padding-right: 12px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;">${ad.title}</div>
                                        <div style="font-size: 10.5px; color: #64748b; font-weight: 600;">
                                            <span style="color: var(--primary-green);">₹${Number(ad.price).toLocaleString('en-IN')}</span> &bull; ${new Date(ad.created_at).toLocaleDateString()}
                                        </div>
                                    </div>
                                    <span style="font-size: 9px; font-weight: 900; padding: 4px 8px; border-radius: 6px; background: ${color}15; color: ${color}; text-transform: uppercase; border: 1px solid ${color}30; white-space: nowrap; letter-spacing: 0.5px;">${ad.status}</span>
                                </div>
                            `;
                        });
                        adDiv.innerHTML = html;
                        adDiv.classList.add('loaded');
                    } else {
                        adDiv.innerHTML = '<div style="padding: 20px; text-align: center; font-size: 11px; color: #94a3b8;">No listings found for this user.</div>';
                    }
                })
                .catch(err => {
                    console.error('Error fetching ads:', err);
                    adDiv.innerHTML = '<div style="padding: 10px; text-align: center; font-size: 11px; color: #ef4444;">Connection error. Please try again.</div>';
                });
        }

        adDiv.style.display = 'block';
        btn.innerHTML = '<i class="fa fa-times"></i> CLOSE HISTORY';
    }

    function viewUserAds(username) {
        // 1. Switch to Ads Section
        showSection('manage_ads');

        // 2. Filter the table (assuming simple search or filtering exists)
        const adSearch = document.getElementById('adSearch');
        if (adSearch) {
            adSearch.value = username;
            // Trigger the filter function for ads
            if (typeof filterAds === 'function') filterAds();
        }
    }

    // --- Enteangadi Admin Ad Inquiries Live Messenger JavaScript ---
    let activeAdminChatProductId = null;
    let activeAdminChatUserId = null;
    let adminChatPollInterval = null;
    let currentAdminChatFilter = 'all';

    function setAdminChatFilter(filterType) {
        currentAdminChatFilter = filterType;
        document.querySelectorAll('.ea-filter-chip').forEach(el => el.classList.remove('active'));
        const activeChip = document.getElementById(`chip-${filterType}`);
        if (activeChip) activeChip.classList.add('active');

        const searchVal = (document.getElementById('eaChatSearch')?.value || '').toLowerCase().trim();
        filterEaConversations(searchVal);
    }

    function filterEaConversations(keyword) {
        const term = (keyword || '').toLowerCase().trim();
        document.querySelectorAll('.ea-chat-item').forEach(item => {
            const searchData = item.getAttribute('data-search') || '';
            const unreadCount = parseInt(item.getAttribute('data-unread') || '0');
            const isVerified = (item.getAttribute('data-verified') === '1');
            
            let matchesFilter = true;
            if (currentAdminChatFilter === 'unread') {
                matchesFilter = (unreadCount > 0);
            } else if (currentAdminChatFilter === 'verified') {
                matchesFilter = isVerified;
            }

            const matchesSearch = (term === '' || searchData.includes(term));

            if (matchesFilter && matchesSearch) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function openAdminChat(productId, userId) {
        activeAdminChatProductId = productId;
        activeAdminChatUserId = userId;

        // Active highlight in left list
        document.querySelectorAll('.ea-chat-item').forEach(el => el.classList.remove('active'));
        const activeItem = document.getElementById(`ea-conv-${productId}-${userId}`);
        if (activeItem) {
            activeItem.classList.add('active');
            activeItem.classList.remove('is-unread');
            activeItem.setAttribute('data-unread', '0');
            const unreadBadge = document.getElementById(`eaItemBadge-${productId}-${userId}`);
            if (unreadBadge) unreadBadge.remove();
        }

        // Show Enteangadi active room, hide empty state
        const emptyState = document.getElementById('adminChatEmptyState');
        const activeBox = document.getElementById('adminChatActiveBox');
        if (emptyState) emptyState.style.display = 'none';
        if (activeBox) activeBox.style.display = 'flex';
        
        loadAdminChatThread(true);

        if (adminChatPollInterval) clearInterval(adminChatPollInterval);
        adminChatPollInterval = setInterval(() => {
            if (activeAdminChatProductId && activeAdminChatUserId) {
                loadAdminChatThread(false);
            }
        }, 4000);
    }

    function loadAdminChatThread(isFirstLoad = false) {
        if (!activeAdminChatProductId || !activeAdminChatUserId) return;

        const box = document.getElementById('adminChatMessagesBox');
        if (!box) return;

        if (isFirstLoad) {
            box.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:240px; color:#64748b;"><i class="fa fa-spinner fa-spin" style="font-size:24px; margin-right:10px; color:#059669;"></i> Loading conversation thread...</div>';
        }

        fetch(`settings.php?action=get_admin_chat_thread&product_id=${activeAdminChatProductId}&user_id=${activeAdminChatUserId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    if (isFirstLoad) box.innerHTML = `<div style="color:#ef4444; padding:20px; text-align:center;">Failed to load chat: ${data.error || 'Unknown error'}</div>`;
                    return;
                }

                // Populate Top Room Header
                const p = data.product || {};
                const u = data.customer || {};
                const initialLetter = (u.username || 'U').charAt(0).toUpperCase();
                
                const initialsEl = document.getElementById('activeChatCustomerInitials');
                if (initialsEl) initialsEl.textContent = initialLetter;

                const avatarEl = document.getElementById('activeChatCustomerAvatar');
                if (avatarEl) {
                    if (u.profile_picture) {
                        avatarEl.src = `../${u.profile_picture}`;
                        avatarEl.style.display = 'block';
                        if (initialsEl) initialsEl.style.display = 'none';
                    } else {
                        avatarEl.style.display = 'none';
                        if (initialsEl) initialsEl.style.display = 'flex';
                    }
                }

                const nameEl = document.getElementById('activeChatCustomerName');
                if (nameEl) {
                    let nameHtml = escapeHtml(u.username || 'Customer');
                    if (u.is_verified == 1) {
                        nameHtml += ' <i class="fa fa-check-circle ea-verified-icon" title="Verified Customer"></i>';
                    }
                    nameEl.innerHTML = nameHtml;
                }

                const priceEl = document.getElementById('activeChatProductPrice');
                if (priceEl) priceEl.textContent = `₹ ${parseFloat(p.price || 0).toLocaleString()}`;

                const titleEl = document.getElementById('activeChatProductTitle');
                if (titleEl) titleEl.textContent = p.title ? `Ad: ${p.title}` : 'Admin Ad Inquiry';

                const phoneEl = document.getElementById('activeChatCustomerPhone');
                if (phoneEl) phoneEl.innerHTML = u.phone_number ? `· <i class="fa fa-phone-alt" style="font-size:10px;"></i> ${escapeHtml(u.phone_number)}` : '';

                const linkEl = document.getElementById('activeChatProductLink');
                if (linkEl && p.id) linkEl.href = `../product.php?id=${p.id}`;

                // Render Message Bubbles
                let html = '';
                const myAdminId = data.current_admin_id;

                if (!data.messages || data.messages.length === 0) {
                    html = `
                        <div style="text-align:center; padding:30px; margin:auto;">
                            <div style="display:inline-block; background:rgba(255,255,255,0.95); padding:10px 18px; border-radius:12px; font-size:13px; color:#475569; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                                <i class="fa fa-shield-alt" style="font-size:12px; margin-right:6px; color:#059669;"></i> Buyer initiated an inquiry for this listing. Send a reply below.
                            </div>
                        </div>
                    `;
                } else {
                    data.messages.forEach(msg => {
                        const isAdminMsg = (parseInt(msg.sender_id) === parseInt(myAdminId) || msg.sender_is_admin == 1);
                        const bubbleClass = isAdminMsg ? 'sent' : 'received';
                        const adminHeader = isAdminMsg ? '<div class="ea-bubble-admin-tag"><i class="fa fa-shield-alt"></i> ENTEANGADI ADMIN</div>' : '';
                        const checkmarks = isAdminMsg ? '<i class="fa fa-check-double" style="color:rgba(255,255,255,0.85); font-size:10px; margin-left:3px;"></i>' : '';

                        let body = escapeHtml(msg.message_text || '');
                        if (msg.message_text && msg.message_text.startsWith('[AUDIO]:')) {
                            const audioSrc = '../' + msg.message_text.replace('[AUDIO]:', '');
                            body = `<div style="display:flex; align-items:center; gap:8px; padding:4px 0;"><i class="fa fa-microphone" style="font-size:18px; color:#059669;"></i><audio controls style="max-width: 220px; height: 32px;"><source src="${audioSrc}" type="audio/wav"></audio></div>`;
                        } else if (msg.message_text && msg.message_text.startsWith('[IMAGE]:')) {
                            const imgSrc = '../' + msg.message_text.replace('[IMAGE]:', '');
                            body = `<a href="${imgSrc}" target="_blank"><img src="${imgSrc}" style="max-width:220px; max-height:220px; border-radius:8px; object-fit:cover; display:block; margin-bottom:4px;"></a>`;
                        }

                        html += `
                            <div class="ea-bubble-wrap ${bubbleClass}">
                                <div class="ea-bubble">
                                    ${adminHeader}
                                    <div class="ea-bubble-text">${body}</div>
                                    <div class="ea-bubble-meta">
                                        <span>${formatMessageTime(msg.created_at)}</span>
                                        ${checkmarks}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }

                box.innerHTML = html;
                if (isFirstLoad) {
                    box.scrollTop = box.scrollHeight;
                }
            })
            .catch(err => {
                if (isFirstLoad) box.innerHTML = `<div style="color:#ef4444; padding:20px; text-align:center;">Network error loading conversation.</div>`;
            });
    }

    function sendAdminReply() {
        const input = document.getElementById('adminReplyText');
        const text = input ? input.value.trim() : '';
        const btn = document.getElementById('adminSendReplyBtn');

        if (!text || !activeAdminChatProductId || !activeAdminChatUserId) return;

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        }

        const formData = new FormData();
        formData.append('action', 'send_admin_reply');
        formData.append('product_id', activeAdminChatProductId);
        formData.append('receiver_id', activeAdminChatUserId);
        formData.append('message', text);

        fetch('settings.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-paper-plane"></i>';
            }
            if (data.success) {
                if (input) input.value = '';
                
                // Optimistically append bubble immediately
                const box = document.getElementById('adminChatMessagesBox');
                if (box) {
                    const bubbleHtml = `
                        <div class="ea-bubble-wrap sent">
                            <div class="ea-bubble">
                                <div class="ea-bubble-admin-tag"><i class="fa fa-shield-alt"></i> ENTEANGADI ADMIN</div>
                                <div class="ea-bubble-text">${escapeHtml(text)}</div>
                                <div class="ea-bubble-meta">
                                    <span>${formatMessageTime(data.created_at || new Date().toISOString())}</span>
                                    <i class="fa fa-check-double" style="color:rgba(255,255,255,0.85); font-size:10px; margin-left:3px;"></i>
                                </div>
                            </div>
                        </div>
                    `;
                    box.insertAdjacentHTML('beforeend', bubbleHtml);
                    box.scrollTop = box.scrollHeight;
                }

                // Update preview in left sidebar
                const convItem = document.getElementById(`ea-conv-${activeAdminChatProductId}-${activeAdminChatUserId}`);
                if (convItem) {
                    const preview = convItem.querySelector('.ea-chat-preview');
                    if (preview) {
                        preview.innerHTML = `<i class="fa fa-check-double" style="color: #059669; font-size: 11px;"></i> ${escapeHtml(text)}`;
                    }
                }
            } else {
                alert(data.error || 'Failed to send message.');
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-paper-plane"></i>';
            }
            alert('Failed to connect to server.');
        });
    }

    function insertAdminQuickReply(text) {
        const input = document.getElementById('adminReplyText');
        if (input) {
            input.value = text;
            input.focus();
        }
    }

    function deleteActiveAdminChat() {
        if (!activeAdminChatProductId || !activeAdminChatUserId) return;
        if (!confirm('Are you sure you want to delete this chat inquiry permanently?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_admin_chat');
        formData.append('product_id', activeAdminChatProductId);
        formData.append('user_id', activeAdminChatUserId);

        fetch('settings.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const item = document.getElementById(`ea-conv-${activeAdminChatProductId}-${activeAdminChatUserId}`);
                if (item) item.remove();
                const activeBox = document.getElementById('adminChatActiveBox');
                const emptyState = document.getElementById('adminChatEmptyState');
                if (activeBox) activeBox.style.display = 'none';
                if (emptyState) emptyState.style.display = 'flex';
                activeAdminChatProductId = null;
                activeAdminChatUserId = null;
            } else {
                alert(data.error || 'Failed to delete chat.');
            }
        });
    }

    function formatMessageTime(datetimeStr) {
        if (!datetimeStr) return '';
        const d = new Date(datetimeStr.replace(/-/g, '/'));
        if (isNaN(d)) return datetimeStr;
        return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Manage All Listings Real-Time Filter & Search
    let currentManageAdFilter = 'all';

    function setAdFilter(filterType, btnEl) {
        currentManageAdFilter = filterType;
        document.querySelectorAll('#section-manage_ads .ea-filter-chip').forEach(el => el.classList.remove('active'));
        if (btnEl) btnEl.classList.add('active');
        filterManageAdsTable();
    }

    function filterManageAdsTable() {
        const query = (document.getElementById('manageAdsSearch')?.value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('.manage-ad-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            const isVerified = (row.getAttribute('data-verified') === '1');
            const status = row.getAttribute('data-status') || 'active';

            let matchesFilter = true;
            if (currentManageAdFilter === 'verified') {
                matchesFilter = isVerified;
            } else if (currentManageAdFilter === 'unverified') {
                matchesFilter = !isVerified;
            } else if (currentManageAdFilter === 'live') {
                matchesFilter = (status === 'active');
            } else if (currentManageAdFilter === 'sold') {
                matchesFilter = (status === 'sold');
            }

            const matchesSearch = (query === '' || searchData.includes(query));

            if (matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noMatchRow = document.getElementById('manageAdsNoMatchRow');
        if (noMatchRow) {
            noMatchRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
    }

    // Admin Security Password Action Modal Logic
    let adminSecurityCallback = null;

    function requestAdminPassword(options, onConfirmed) {
        document.getElementById('adminSecurityModalTitle').innerText = options.title || 'Security Verification Required';
        document.getElementById('adminSecurityModalDescription').innerHTML = options.description || 'To execute this permanent deletion, please verify your account credentials.';
        const confirmBtn = document.getElementById('adminSecurityConfirmBtn');
        confirmBtn.innerText = options.confirmText || 'CONFIRM DELETE';
        confirmBtn.style.background = options.btnColor || '#dc2626';
        const pwdInput = document.getElementById('adminSecurityPasswordInput');
        pwdInput.value = '';
        document.getElementById('adminSecurityErrorMsg').style.display = 'none';
        adminSecurityCallback = onConfirmed;

        const modal = document.getElementById('adminActionSecurityModal');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => pwdInput.focus(), 60);
        }
    }

    function closeAdminSecurityModal() {
        const modal = document.getElementById('adminActionSecurityModal');
        if (modal) modal.style.display = 'none';
        adminSecurityCallback = null;
    }

    function submitAdminSecurityAction() {
        const pwd = document.getElementById('adminSecurityPasswordInput').value;
        if (!pwd) {
            const err = document.getElementById('adminSecurityErrorMsg');
            err.innerText = 'Please enter your account password';
            err.style.display = 'block';
            return;
        }
        if (typeof adminSecurityCallback === 'function') {
            const cb = adminSecurityCallback;
            closeAdminSecurityModal();
            cb(pwd);
        }
    }

    function confirmDeleteListing(btn) {
        if (!btn) return;
        const uid = btn.getAttribute('data-uid') || '';
        const title = btn.getAttribute('data-title') || 'this listing';
        const form = btn.closest('form');
        if (!form) return;

        requestAdminPassword({
            title: 'Delete Listing #' + uid,
            description: `⚠️ <b>PERMANENT DELETION:</b> Are you sure you want to permanently delete listing <b>#${uid}</b> ("${title}")?<br><br>All advertisement photos, inquiries, and wishlist records will be removed.<br><br>Please confirm with your <b>Account Password</b>:`,
            confirmText: 'DELETE LISTING',
            btnColor: '#dc2626'
        }, function(adminPassword) {
            let pwdInput = form.querySelector('input[name="admin_password"]');
            if (!pwdInput) {
                pwdInput = document.createElement('input');
                pwdInput.type = 'hidden';
                pwdInput.name = 'admin_password';
                form.appendChild(pwdInput);
            }
            pwdInput.value = adminPassword;
            form.submit();
        });
    }

    function confirmCleanProductionAction() {
        requestAdminPassword({
            title: 'Purge Dummy Data & Update Backup',
            description: '⚠️ <b>PRODUCTION DEPLOYMENT ACTION:</b><br><br>This will permanently purge all dummy listings, fake site visits, test clicks, chat messages, and non-admin test users, retaining ONLY the <b>Root Administrator Account, Platform App Settings, and All Categories</b>.<br><br>The single backup file <b>db/enteangadi.sql</b> will be updated directly.<br><br>Please enter your <b>Account Password</b> to confirm:',
            confirmText: 'PURGE & UPDATE BACKUP',
            btnColor: '#4f46e5'
        }, function(adminPassword) {
            const form = document.getElementById('cleanProductionForm');
            if (form) {
                document.getElementById('cleanProductionPasswordInput').value = adminPassword;
                form.submit();
            }
        });
    }

    window.onclick = function (event) {
        if (event.target == document.getElementById('revealModal')) closeRevealModal();
        if (event.target == document.getElementById('adminActionSecurityModal')) closeAdminSecurityModal();
    }
</script>

<?php require_once 'includes/footer.php'; ?>