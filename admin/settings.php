<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'branding') {
        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Ensure default keys exist
        $pdo->exec("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES 
            ('app_logo', ''),
            ('app_name', 'Enteangadi'),
            ('app_tagline', 'Your Local Marketplace')");

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

                if (move_uploaded_file($_FILES['app_logo']['tmp_name'], $target_path)) {
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
    } elseif (isset($_POST['action']) && $_POST['action'] === 'contact') {
        $fields = [
            'support_email',
            'support_phone',
            'whatsapp_number',
            'facebook_url',
            'instagram_url',
            'twitter_url'
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
        $product_id = $_POST['product_id'] ?? '';
        $new_status = ($_POST['current_status'] == 1) ? 0 : 1;
        if ($product_id) {
            $stmt = $pdo->prepare("UPDATE products SET is_verified = ? WHERE id = ?");
            $stmt->execute([$new_status, $product_id]);
            $success = "Verification status updated.";
        }
    }
}

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
// Fetch manageable products (Active/Inactive)
$active_stmt = $pdo->query("
    SELECT p.*, u.username, c.name as category_name,
    (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as main_image,
    (SELECT GROUP_CONCAT(image_path) FROM product_images WHERE product_id = p.id) as all_images
    FROM products p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    WHERE p.status IN ('active', 'inactive')
    ORDER BY p.updated_at DESC
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
        $user_stmt = $pdo->prepare("SELECT email, phone FROM users WHERE id = ?");
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
    $target_user_id = $_POST['target_user_id'];

    // 1. Fetch images before deletion
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $profile_pic = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT pi.image_path FROM product_images pi JOIN products p ON pi.product_id = p.id WHERE p.user_id = ?");
    $stmt->execute([$target_user_id]);
    $product_images = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Delete from DB (is_admin = 0 check for safety)
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
    if ($stmt->execute([$target_user_id])) {
        // 3. Physical cleanup
        if ($profile_pic && file_exists('../' . $profile_pic))
            unlink('../' . $profile_pic);
        foreach ($product_images as $img) {
            if ($img && file_exists('../' . $img))
                unlink('../' . $img);
        }
        echo json_encode(['success' => true, 'message' => 'User and media permanently removed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user or user is an admin']);
    }
    exit;
}

// Handle Administrative User Creation
if (isset($_POST['action']) && $_POST['action'] === 'admin_create_user') {
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

    $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password, is_admin, role, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)");
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

require_once 'includes/header.php';
?>

<style>
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
        gap: 8px;
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 24px;
        transition: all 0.2s;
        cursor: pointer;
        background: var(--white);
        border: 1px solid var(--border-color);
        padding: 8px 16px;
        border-radius: 12px;
        font-size: 14px;
    }

    .back-btn:hover {
        color: var(--primary-green-dark);
        border-color: var(--primary-green);
        background: #f0fdf4;
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
            <?php if (has_permission('manage_branding')): ?>
                <div class="settings-card" onclick="showSection('branding')">
                    <i class="fa fa-palette"></i>
                    <div>
                        <h3>Logo & Branding</h3>
                        <p>Customize visuals and assets</p>
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

                <div class="settings-card" onclick="openCreateUserModal()"
                    style="background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%); border-color: #10b981;">
                    <i class="fa fa-user-plus" style="color: #10b981;"></i>
                    <div>
                        <h3 style="color: #065f46;">Provision Staff</h3>
                        <p style="color: #047857;">Onboard new accounts</p>
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
        </div>
    </div>

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
                        <?php if (!empty($app_settings['app_logo'])): ?>
                            <img src="../<?= htmlspecialchars($app_settings['app_logo']) ?>" alt="Current Logo"
                                style="max-height: 80px; object-fit: contain;">
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
    class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'toggle_verify') ? 'active' : '' ?>">
    <button class="back-btn" onclick="showSection('grid')">
        <i class="fa fa-arrow-left"></i> Back to Dashboard
    </button>

    <div
        style="background: var(--white); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
        <div
            style="padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; font-size: 20px; font-weight: 800;">Manage All Listings</h2>
            <span
                style="background: #e0f2fe; color: #0369a1; padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 12px;">
                <?= count($active_products) ?> Total Ads
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted);">
                            Product</th>
                        <th style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted);">
                            Trust Status</th>
                        <th
                            style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted); text-align: right;">
                            Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_products as $p): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                            <td style="padding: 20px 24px;">
                                <div style="display: flex; gap: 16px; align-items: center;">
                                    <div
                                        style="width: 44px; height: 44px; border-radius: 8px; overflow: hidden; background: #eee; flex-shrink: 0;">
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
                                        <div style="font-size: 11px; color: var(--text-muted);">by
                                            <?= htmlspecialchars($p['username']) ?> &bull;
                                            #<?= htmlspecialchars($p['unique_id']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 20px 24px;">
                                <?php
                                $status_colors = [
                                    'active' => ['bg' => '#f0fdf4', 'text' => '#166534', 'label' => 'LIVE'],
                                    'sold' => ['bg' => '#f1f5f9', 'text' => '#475569', 'label' => 'SOLD'],
                                    'deleted' => ['bg' => '#fff1f2', 'text' => '#991b1b', 'label' => 'DELETED'],
                                    'inactive' => ['bg' => '#f8fafc', 'text' => '#64748b', 'label' => 'HIDDEN'],
                                    'expired' => ['bg' => '#fff7ed', 'text' => '#9a3412', 'label' => 'EXPIRED']
                                ];
                                $s = $status_colors[$p['status']] ?? ['bg' => '#eee', 'text' => '#666', 'label' => strtoupper($p['status'])];
                                ?>
                                <div style="margin-bottom: 6px;">
                                    <span
                                        style="background: <?= $s['bg'] ?>; color: <?= $s['text'] ?>; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                        <?= $s['label'] ?>
                                    </span>
                                </div>
                                <?php if ($p['is_verified']): ?>
                                    <span
                                        style="background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa fa-check-circle"></i> VERIFIED
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 20px 24px; text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <button type="button" class="btn-secondary"
                                        style="padding: 8px 12px; font-size: 12px; border-radius: 8px;" onclick='viewAdDetails(<?= htmlspecialchars(json_encode([
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
                                    <?php if ($p['status'] === 'active'): ?>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $p['is_verified'] ?>">
                                            <input type="hidden" name="action" value="toggle_verify">
                                            <button type="submit" class="btn-primary"
                                                style="padding: 8px 16px; font-size: 12px; border-radius: 8px; background: <?= $p['is_verified'] ? '#f1f5f9' : '#0369a1' ?>; color: <?= $p['is_verified'] ? '#64748b' : '#fff' ?>; border-color: <?= $p['is_verified'] ? '#e2e8f0' : '#0369a1' ?>;">
                                                <?= $p['is_verified'] ? 'Remove Badge' : 'Add Blue Tick' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
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
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); z-index: 4000; align-items: center; justify-content: center;">
    <div
        style="background: white; padding: 32px; border-radius: 32px; width: 95%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0; color: #0f172a; font-weight: 800; font-size: 22px;">Provision Account</h3>
            <button onclick="closeCreateUserModal()"
                style="background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 12px; color: #64748b; cursor: pointer;"><i
                    class="fa fa-times"></i></button>
        </div>

        <div style="display: grid; gap: 20px;">
            <div class="form-group">
                <label
                    style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; display: block;">Username</label>
                <input type="text" id="new_username" placeholder="Enter username"
                    style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 15px;">
            </div>
            <div class="form-group">
                <label
                    style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; display: block;">Email
                    Address</label>
                <input type="email" id="new_email" placeholder="email@example.com"
                    style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 15px;">
            </div>
            <div class="form-group">
                <label
                    style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; display: block;">Phone
                    Number</label>
                <input type="text" id="new_phone" placeholder="Contact number"
                    style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 15px;">
            </div>
            <div class="form-group">
                <label
                    style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; display: block;">Temporary
                    Password</label>
                <input type="password" id="new_password" placeholder="Set password"
                    style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 15px;">
            </div>
            <div class="form-group">
                <label
                    style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; display: block;">Account
                    Clearance</label>
                <select id="new_is_admin" onchange="togglePermissionMatrix(this.value)"
                    style="width: 100%; padding: 14px; border-radius: 16px; border: 1.5px solid #e2e8f0; font-size: 15px; background: white;">
                    <option value="0">Community Member (Standard)</option>
                    <option value="1">System Administrator (Custom Access)</option>
                </select>
            </div>

            <!-- Permission Matrix (Hidden by default) -->
            <div id="permissionMatrix"
                style="display: none; background: #f8fafc; padding: 20px; border-radius: 20px; border: 1px solid #e2e8f0;">
                <label
                    style="font-size: 11px; font-weight: 800; color: #db2777; text-transform: uppercase; margin-bottom: 12px; display: block;">Access
                    Privileges</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #db2777; font-weight: 800; cursor: pointer; grid-column: 1 / -1; background: #fff1f2; padding: 8px; border-radius: 10px; border: 1.5px dashed #fecdd3;">
                        <input type="checkbox" class="perm-check" value="*"> Grant Full Root Access
                    </label>
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="checkbox" class="perm-check" value="manage_users" checked> Users
                    </label>
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="checkbox" class="perm-check" value="manage_categories"> Categories
                    </label>
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="checkbox" class="perm-check" value="manage_pending" checked> Pending Ads
                    </label>
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="checkbox" class="perm-check" value="manage_listings" checked> Active Ads
                    </label>
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="checkbox" class="perm-check" value="manage_branding"> Branding
                    </label>
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="checkbox" class="perm-check" value="manage_security"> Security
                    </label>
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="checkbox" class="perm-check" value="manage_general"> General Config
                    </label>
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="checkbox" class="perm-check" value="manage_contact"> Contact/Social
                    </label>
                    <label
                        style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                        <input type="checkbox" class="perm-check" value="manage_approval"> Approval Mode
                    </label>
                </div>
            </div>
        </div>

        <button onclick="confirmCreateUser()"
            style="width: 100%; margin-top: 32px; padding: 16px; border-radius: 18px; border: none; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; font-weight: 800; font-size: 15px; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(16,185,129,0.3);">
            CREATE ACCOUNT
        </button>
    </div>
</div>

<!-- Edit Permissions Modal -->
<div id="editPermissionsModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); z-index: 4000; align-items: center; justify-content: center;">
    <div
        style="background: white; padding: 32px; border-radius: 32px; width: 95%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0; color: #0f172a; font-weight: 800; font-size: 22px;">Recalibrate Access</h3>
            <button onclick="closeEditPermissionsModal()"
                style="background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 12px; color: #64748b; cursor: pointer;"><i
                    class="fa fa-times"></i></button>
        </div>

        <p style="margin: -10px 0 24px 0; font-size: 14px; color: #64748b;">Adjusting privileges for <strong
                id="editPermUsername" style="color: #1e293b;"></strong></p>
        <input type="hidden" id="editPermUserId">

        <div style="margin-bottom: 24px;">
            <label
                style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 12px; display: block;">Account
                Status</label>
            <label
                style="display: flex; align-items: center; justify-content: space-between; background: #f1f5f9; padding: 12px 20px; border-radius: 16px; cursor: pointer;">
                <span style="font-size: 14px; font-weight: 700; color: #1e293b;">Administrative Access</span>
                <input type="checkbox" id="editIsAdmin" style="width: 20px; height: 20px; accent-color: #8b5cf6;">
            </label>
        </div>

        <div id="editPermissionMatrix"
            style="background: #f8fafc; padding: 20px; border-radius: 20px; border: 1px solid #e2e8f0;">
            <label
                style="font-size: 11px; font-weight: 800; color: #db2777; text-transform: uppercase; margin-bottom: 12px; display: block;">Access
                Matrix</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #db2777; font-weight: 800; cursor: pointer; grid-column: 1 / -1; background: #fff1f2; padding: 8px; border-radius: 10px; border: 1.5px dashed #fecdd3;">
                    <input type="checkbox" class="edit-perm-check" value="*"> Grant Full Root Access
                </label>
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                    <input type="checkbox" class="edit-perm-check" value="manage_users"> Users
                </label>
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                    <input type="checkbox" class="edit-perm-check" value="manage_categories"> Categories
                </label>
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                    <input type="checkbox" class="edit-perm-check" value="manage_pending"> Pending Ads
                </label>
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                    <input type="checkbox" class="edit-perm-check" value="manage_listings"> Active Ads
                </label>
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                    <input type="checkbox" class="edit-perm-check" value="manage_branding"> Branding
                </label>
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                    <input type="checkbox" class="edit-perm-check" value="manage_security"> Security
                </label>
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                    <input type="checkbox" class="edit-perm-check" value="manage_general"> General Config
                </label>
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                    <input type="checkbox" class="edit-perm-check" value="manage_contact"> Contact/Social
                </label>
                <label
                    style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
                    <input type="checkbox" class="edit-perm-check" value="manage_approval"> Approval Mode
                </label>
            </div>
        </div>

        <button onclick="confirmUpdatePermissions()"
            style="width: 100%; margin-top: 32px; padding: 16px; border-radius: 18px; border: none; background: #1e293b; color: white; font-weight: 800; font-size: 15px; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(30,41,59,0.3);">
            SAVE PRIVILEGES
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

        // Show target section
        if (sectionId === 'grid') {
            document.getElementById('grid-view').classList.add('active');
            window.location.hash = '';
        } else {
            const section = document.getElementById('section-' + sectionId);
            if (section) {
                section.classList.add('active');
                window.location.hash = sectionId;
            } else if (sectionId === 'security') { // Map legacy ID
                document.getElementById('section-security').classList.add('active');
                window.location.hash = 'security';
            }
        }
    }

    // Support for deep linking via hash
    window.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash.substring(1);
        if (hash === 'branding' || hash === 'password' || hash === 'general' || hash === 'contact' || hash === 'approval' || hash === 'pending' || hash === 'manage_ads' || hash === 'closure_feedback' || hash === 'users') {
            showSection(hash);
        }
    });

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
        if (!confirm(`CRITICAL ACTION: Are you sure you want to PERMANENTLY delete ${username}? This will remove all their ads and images and CANNOT be undone.`)) return;

        const formData = new FormData();
        formData.append('action', 'admin_delete_user');
        formData.append('target_user_id', userId);

        fetch('settings.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.success) location.reload();
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

    window.onclick = function (event) {
        if (event.target == document.getElementById('revealModal')) closeRevealModal();
    }
</script>

<?php require_once 'includes/footer.php'; ?>