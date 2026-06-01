<?php
session_start();

// --- CONTENT MODERATION SETTINGS ---
define('SIGHTENGINE_USER', ''); // Enter your Sightengine User ID here
define('SIGHTENGINE_SECRET', ''); // Enter your Sightengine Secret Key here
define('MODERATION_STRICTNESS', 0.70); // Probability threshold (70%)

// Allow Cross-Origin Resource Sharing (CORS) for Decoupled Clients (Vite, Capacitor)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Return immediately for OPTIONS preflight checks
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'enteangadi';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- HELPER FUNCTIONS ---

    /**
     * Compresses an image significantly to save storage
     * Used for expired/sold/deleted products
     */
    if (!function_exists('compressProductImage')) {
        function compressProductImage($source_path, $quality = 30)
        {
            $abs_path = dirname(__FILE__) . '/' . $source_path;
            if (!file_exists($abs_path))
                return false;

            $info = getimagesize($abs_path);
            if (!$info)
                return false;

            if ($info['mime'] == 'image/jpeg')
                $image = imagecreatefromjpeg($abs_path);
            elseif ($info['mime'] == 'image/gif')
                $image = imagecreatefromgif($abs_path);
            elseif ($info['mime'] == 'image/png')
                $image = imagecreatefrompng($abs_path);
            else
                return false;

            // Save as JPEG with high compression
            imagejpeg($image, $abs_path, $quality);
            return true;
        }
    }

    // --- AUTOMATED MAINTENANCE ---

    // 1. Auto-expire ads older than 60 days
    $expiry_days = 60;
    $expire_stmt = $pdo->prepare("
        SELECT p.id, pi.image_path 
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id
        WHERE p.status = 'active' 
        AND p.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $expire_stmt->execute([$expiry_days]);
    $to_expire = $expire_stmt->fetchAll();

    if (!empty($to_expire)) {
        $ids_to_upd = array_unique(array_column($to_expire, 'id'));

        // Update Status
        $update_stmt = $pdo->prepare("
            UPDATE products 
            SET status = 'expired', 
                status_reason = 'System auto-expiry (60 days)',
                updated_at = NOW() 
            WHERE id = ?
        ");

        foreach ($ids_to_upd as $id) {
            $update_stmt->execute([$id]);
        }

        // Compress images for expired ads
        foreach ($to_expire as $item) {
            if ($item['image_path']) {
                compressProductImage($item['image_path'], 20); // Extra compression
            }
        }
    }

    // Auto-create messages table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        product_id INT NOT NULL,
        message_text TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");

    // Migration: Add email, phone, and is_admin columns to users if they don't exist
    $check_email = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
    if (!$check_email->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(155) UNIQUE DEFAULT NULL AFTER phone_number");
    }

    $check_is_admin = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
    if (!$check_is_admin->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER role");
    }

    $check_phone = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if (!$check_phone->fetch()) {
        // Safe check: Only use AFTER if email exists, otherwise just add it
        $after_clause = "AFTER email";
        $check_email = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
        if (!$check_email->fetch())
            $after_clause = "";
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL $after_clause");
    }

    $check_perm = $pdo->query("SHOW COLUMNS FROM users LIKE 'permissions'");
    if (!$check_perm->fetch()) {
        $after_clause = "AFTER is_admin";
        $check_is_admin = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
        if (!$check_is_admin->fetch())
            $after_clause = "";
        $pdo->exec("ALTER TABLE users ADD COLUMN permissions TEXT DEFAULT NULL $after_clause");
    }

    // Auto-fix: Ensure the currently logged-in main admin has all permissions
    if (isset($_SESSION['admin_id'])) {
        $stmt_admin = $pdo->prepare("SELECT permissions FROM users WHERE id = ?");
        $stmt_admin->execute([$_SESSION['admin_id']]);
        $_SESSION['admin_permissions'] = $stmt_admin->fetchColumn();
    }

    function has_permission($module)
    {
        if (!isset($_SESSION['admin_permissions']))
            return false;
        if ($_SESSION['admin_permissions'] === '*')
            return true;
        $perms = explode(',', $_SESSION['admin_permissions']);
        return in_array($module, $perms);
    }

    // Auto-create wishlist table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_wishlist (user_id, product_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");

    // Auto-add session_token column to users if missing
    try {
        $pdo->query("SELECT session_token FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN session_token VARCHAR(255) DEFAULT NULL");
    }

    // Auto-add type column to products if missing (for Sell vs Buy)
    try {
        $pdo->query("SELECT type FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN type ENUM('sell', 'buy') DEFAULT 'sell' AFTER category_id");
    }

    // Auto-add is_perishable column to categories if missing
    try {
        $pdo->query("SELECT is_perishable FROM categories LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN is_perishable TINYINT(1) DEFAULT 0");
    }

    // Auto-add expiry_date column to products if missing
    try {
        $pdo->query("SELECT expiry_date FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN expiry_date DATE DEFAULT NULL AFTER price");
    }

    // Auto-add updated_at column to products if missing
    try {
        $pdo->query("SELECT updated_at FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // [AD APPROVAL SYSTEM] Auto-add unique_id to products
    try {
        $pdo->query("SELECT unique_id FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN unique_id VARCHAR(20) UNIQUE AFTER id");
        // Update existing
        $stmt = $pdo->query("SELECT id FROM products WHERE unique_id IS NULL");
        $products_to_update = $stmt->fetchAll();
        foreach ($products_to_update as $p) {
            $uid = 'ENTAGD' . rand(1000, 9999);
            $pdo->prepare("UPDATE products SET unique_id = ? WHERE id = ?")->execute([$uid, $p['id']]);
        }
    }

    // [LOCATION & CONTACT] Auto-add missing columns to products
    try {
        $pdo->query("SELECT whatsapp_number FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN whatsapp_number VARCHAR(20) DEFAULT NULL AFTER expiry_date");
    }
    try {
        $pdo->query("SELECT phone_number FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN phone_number VARCHAR(20) DEFAULT NULL AFTER whatsapp_number");
    }
    try {
        $pdo->query("SELECT location_name FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN location_name VARCHAR(255) DEFAULT NULL AFTER phone_number");
    }
    try {
        $pdo->query("SELECT latitude FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL AFTER location_name");
    }
    try {
        $pdo->query("SELECT longitude FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL AFTER latitude");
    }

    // [AD APPROVAL SYSTEM] Auto-add is_verified to products
    try {
        $pdo->query("SELECT is_verified FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER status");
    }

    // [AD APPROVAL SYSTEM] Auto-add is_notified to products (for one-time success message)
    try {
        $pdo->query("SELECT is_notified FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN is_notified TINYINT(1) DEFAULT 0 AFTER is_verified");
    }

    // [AD APPROVAL SYSTEM] Auto-add status_reason to products
    try {
        $pdo->query("SELECT status_reason FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN status_reason TEXT DEFAULT NULL AFTER status");
    }

    // [AD ANALYTICS] Auto-add views column to products
    try {
        $pdo->query("SELECT views FROM products LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN views INT DEFAULT 0 AFTER status_reason");
    }

    // [ACCOUNT CLOSURE FEEDBACK] Create table
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_closures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255),
        email VARCHAR(255),
        reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // [AD APPROVAL SYSTEM] Update status ENUM
    try {
        $pdo->exec("ALTER TABLE products MODIFY COLUMN status ENUM('active', 'deleted', 'sold', 'expired', 'pending', 'inactive') DEFAULT 'active'");
    } catch (Exception $e) {
    }

    // [AD APPROVAL SYSTEM] Auto-create app_settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)")->execute(['ad_approval_mode', 'auto']);

    // Include shared helper functions
    require_once dirname(__FILE__) . '/includes/helpers.php';

    // Auto-deactivate expired items (Perishable/Edible)
    // We check for products that reached their expiry date today or earlier
    $stmt = $pdo->query("SELECT id FROM products WHERE expiry_date IS NOT NULL AND expiry_date <= CURRENT_DATE AND status = 'active'");
    $expired_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($expired_ids)) {
        foreach ($expired_ids as $pid) {
            // 1. Mark as expired
            $pdo->prepare("UPDATE products SET status = 'expired' WHERE id = ?")->execute([$pid]);

            // 2. Compress images to save space (since they are now archived)
            $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $img_stmt->execute([$pid]);
            $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($images as $img) {
                $fullPath = dirname(__FILE__) . '/' . $img;
                recompressTo50kb($fullPath);
            }
        }
    }

    // --- PURGE ARCHIVED ADS AFTER 60 DAYS ---
    // Permanently remove ads and images that have been inactive for more than 60 days
    $purge_interval = 60;
    $purge_stmt = $pdo->prepare("
        SELECT p.id, pi.image_path 
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id
        WHERE p.status IN ('deleted', 'sold', 'expired') 
        AND p.updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $purge_stmt->execute([$purge_interval]);
    $to_purge = $purge_stmt->fetchAll();

    if (!empty($to_purge)) {
        $purge_ids = array_unique(array_column($to_purge, 'id'));

        // 1. Physical Cleanup (Delete image files)
        foreach ($to_purge as $item) {
            if ($item['image_path']) {
                $fullPath = dirname(__FILE__) . '/' . $item['image_path'];
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }

        // 2. Database Cleanup (Cascading deletes handle child tables)
        $final_delete = $pdo->prepare("DELETE FROM products WHERE id = ?");
        foreach ($purge_ids as $pid) {
            $final_delete->execute([$pid]);
        }
    }

    // Global session token check for remote logout & deleted users
    if (isset($_SESSION['user_id']) && !isset($_SESSION['is_admin_session'])) {
        $stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_record = $stmt->fetch();

        // If user is deleted or token doesn't match
        if (!$user_record || (isset($user_record['session_token']) && (!isset($_SESSION['session_token']) || $_SESSION['session_token'] !== $user_record['session_token']))) {
            session_destroy();
            // Redirect to login if not already there
            $current_page = basename($_SERVER['PHP_SELF']);
            if ($current_page != 'login.php' && $current_page != 'index.php') {
                header("Location: login.php?msg=account_inactive");
                exit;
            }
        }
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>