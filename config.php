<?php
// Configure secure session cookie params before starting session
if (session_status() === PHP_SESSION_NONE) {
    $secure = false;
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) {
        $secure = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $secure = true;
    }

    // Default secure to true on production hosts
    if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
        // Localhost: keep secure as detected
    } else {
        $secure = true;
    }

    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        session_set_cookie_params([
            'lifetime' => 0, // Session cookie (until browser closes)
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/; SameSite=Lax', '', $secure, true);
    }
}
session_start();

// Set base URL dynamically
if (!isset($base_url)) {
    $current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base_url = ($current_dir == '/' || $current_dir == '.') ? '' : $current_dir;
    if (basename($base_url) == 'user' || basename($base_url) == 'admin' || basename($base_url) == 'guest' || basename($base_url) == 'api' || basename($base_url) == 'includes') {
        $base_url = dirname($base_url);
    }
    if (basename($base_url) == 'user' || basename($base_url) == 'admin' || basename($base_url) == 'guest' || basename($base_url) == 'api' || basename($base_url) == 'includes') {
        $base_url = dirname($base_url);
    }
    $base_url = str_replace('\\', '/', $base_url);
    if ($base_url == '/') {
        $base_url = '';
    }
}

// Unified Cookie Helper Function
if (!function_exists('enteangadi_set_cookie')) {
    function enteangadi_set_cookie($name, $value, $expiry)
    {
        $secure = false;
        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) {
            $secure = true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $secure = true;
        }

        // Detect localhost and local private network IPs to avoid enforcing secure flags on HTTP dev envs
        $is_local = false;
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            if (
                strpos($host, 'localhost') !== false ||
                strpos($host, '127.0.0.1') !== false ||
                strpos($host, '10.') === 0 ||
                strpos($host, '192.168.') !== false ||
                preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host)
            ) {
                $is_local = true;
            }
        }

        if ($is_local) {
            // Local development: keep secure as detected (usually false)
        } else {
            // Live server (production): default secure to true since it uses HTTPS
            $secure = true;
        }

        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            setcookie($name, $value, [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '', // Default to current host
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            setcookie($name, $value, $expiry, '/; SameSite=Lax', '', $secure, true);
        }
    }
}

// Restore user location from cookie if session is missing
if (!isset($_SESSION['user_location']) && isset($_COOKIE['user_location'])) {
    $cookie_loc = json_decode($_COOKIE['user_location'], true);
    if ($cookie_loc && isset($cookie_loc['name']) && isset($cookie_loc['lat']) && isset($cookie_loc['lng'])) {
        $_SESSION['user_location'] = [
            'name' => $cookie_loc['name'],
            'lat' => $cookie_loc['lat'],
            'lng' => $cookie_loc['lng']
        ];
    }
}

// --- CONTENT MODERATION SETTINGS ---
define('SIGHTENGINE_USER', ''); // Enter your Sightengine User ID here
define('SIGHTENGINE_SECRET', ''); // Enter your Sightengine Secret Key here
define('MODERATION_STRICTNESS', 0.70); // Probability threshold (70%)

// Allow Cross-Origin Resource Sharing (CORS) with Session Credentials (cookies) for Decoupled Clients (Vite, Capacitor)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: " . ($origin !== '*' ? $origin : '*'));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Return immediately for OPTIONS preflight checks
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'enteangadi';



try {
    // 1. Connect to MySQL server without dbname first to ensure we can create it if missing
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    } catch (PDOException $e) {
        // Fallback to 127.0.0.1 if localhost socket connection fails (common on live Unix/Linux servers)
        if ($host === 'localhost') {
            $host = '127.0.0.1';
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        } else {
            throw $e;
        }
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbname`;");

    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 3. Check if core tables exist (e.g. check if 'users' table exists)
    $table_check = $pdo->query("SHOW TABLES LIKE 'users'");
    if (!$table_check->fetch()) {
        // If users table is missing, import the entire schema from db_schema.sql
        $schema_file = dirname(__FILE__) . '/db_schema.sql';
        if (file_exists($schema_file)) {
            $sql = file_get_contents($schema_file);
            // Remove database creation statements from the schema if they exist to avoid conflicts
            $sql = str_replace("CREATE DATABASE IF NOT EXISTS enteangadi;", "", $sql);
            $sql = str_replace("USE enteangadi;", "", $sql);
            $pdo->exec($sql);
        }
    }

    // Cleanup expired chats for products sold/deleted more than 15 days ago
    try {
        $cutoff_date = date('Y-m-d H:i:s', time() - (15 * 86400));
        $stmt_exp = $pdo->prepare("SELECT id FROM products WHERE status IN ('sold', 'deleted') AND updated_at < ?");
        $stmt_exp->execute([$cutoff_date]);
        $expired_products = $stmt_exp->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($expired_products)) {
            foreach ($expired_products as $p_id) {
                // Find all messages with files (audio or images) for this product
                $file_stmt = $pdo->prepare("SELECT message_text FROM messages WHERE product_id = ? AND (message_text LIKE '[AUDIO]:%' OR message_text LIKE '[IMAGE]:%')");
                $file_stmt->execute([$p_id]);
                $files = $file_stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($files as $file_text) {
                    $prefix = '';
                    if (strpos($file_text, '[AUDIO]:') === 0) {
                        $prefix = '[AUDIO]:';
                    } elseif (strpos($file_text, '[IMAGE]:') === 0) {
                        $prefix = '[IMAGE]:';
                    }
                    if (!empty($prefix)) {
                        $relative_path = substr($file_text, strlen($prefix));
                        $absolute_path = dirname(__FILE__) . '/' . $relative_path;
                        if (file_exists($absolute_path)) {
                            @unlink($absolute_path);
                        }
                    }
                }

                // Delete all messages associated with this product
                $del_msgs = $pdo->prepare("DELETE FROM messages WHERE product_id = ?");
                $del_msgs->execute([$p_id]);
            }
        }
    } catch (Exception $exp_err) {
        // Fail silently
    }

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

    // Ensure default admin user exists and has a valid password
    $stmt_check_admin = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt_check_admin->execute();
    $admin_user = $stmt_check_admin->fetch();

    $default_admin_hash = '$2y$10$OWyw9HIV10p07vkWgiRrh.aWK83dyOhUPH8cxvlxOTR6PeJjkn2QG'; // hash of admin123
    $old_broken_hash = '$2y$10$K8pe9htFbLrJD/EjOE3In.RPOFpPz2WZ44lwQVt8RJRmUgXNnfnSC'; // old broken hash in schema

    if (!$admin_user) {
        // Insert admin user if missing
        $stmt_insert_admin = $pdo->prepare("
            INSERT IGNORE INTO users (username, phone_number, email, password, role, is_admin, permissions) 
            VALUES ('admin', '1234567890', 'admin@enteangadi.com', ?, 'admin', 1, '*')
        ");
        $stmt_insert_admin->execute([$default_admin_hash]);
    } else {
        // If the admin user exists but has the old broken hash, update it to the default working hash
        if ($admin_user['password'] === $old_broken_hash) {
            $stmt_update_admin = $pdo->prepare("
                UPDATE users 
                SET password = ? 
                WHERE username = 'admin'
            ");
            $stmt_update_admin->execute([$default_admin_hash]);
        }
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

    // Auto-create blocked_users table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT NOT NULL,
        blocked_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (blocker_id, blocked_id),
        FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Auto-add reported_user_id column to reports if missing
    try {
        $pdo->query("SELECT reported_user_id FROM reports LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE reports ADD COLUMN reported_user_id INT DEFAULT NULL AFTER product_id");
        $pdo->exec("ALTER TABLE reports ADD FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE");
    }


    // Auto-add session_token column to users if missing
    try {
        $pdo->query("SELECT session_token FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN session_token VARCHAR(255) DEFAULT NULL");
    }

    // Auto-add type column to products if missing (for Sell vs Buy) and support 'rent'
    try {
        $pdo->query("SELECT type FROM products LIMIT 1");
        $pdo->exec("ALTER TABLE products MODIFY COLUMN type ENUM('sell', 'buy', 'rent') DEFAULT 'sell'");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN type ENUM('sell', 'buy', 'rent') DEFAULT 'sell' AFTER category_id");
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
    $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)")->execute(['play_store_url', 'https://play.google.com/store/apps/details?id=com.enteangadi.app']);
    $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)")->execute(['app_store_url', 'https://apps.apple.com/app/enteangadi']);
    $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)")->execute(['adult_content_check', '1']);

    // [TRUST & ANALYTICS SYSTEMS] Auto-create user_ratings and analytics_clicks tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reviewer_id INT NOT NULL,
        reviewee_id INT NOT NULL,
        rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reviewer_reviewee (reviewer_id, reviewee_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        click_type ENUM('view', 'favorite', 'chat') NOT NULL,
        click_date DATE NOT NULL,
        click_count INT DEFAULT 1,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_product_type_date (product_id, click_type, click_date)
    )");

    // Add is_verified column to users table if not exists
    try {
        $pdo->query("SELECT is_verified FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
    }

    // Auto-create verification_requests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS verification_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        id_type VARCHAR(100) NOT NULL,
        id_photo VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        rejection_reason TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // [SESSION TIMEOUT] Auto-add last_activity to users
    try {
        $pdo->query("SELECT last_activity FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL AFTER session_token");
    }

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

    // Cookie-Based Server-Side Auto-Login
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['enteangadi_remember_user']) && isset($_COOKIE['enteangadi_remember_token'])) {
        $cookie_user = $_COOKIE['enteangadi_remember_user'];
        $cookie_token = $_COOKIE['enteangadi_remember_token'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND session_token = ?");
        $stmt->execute([$cookie_user, $cookie_token]);
        $user = $stmt->fetch();

        if ($user) {
            // Check if user is active (has activity in the last 30 days)
            $last_act = $user['last_activity'] ? strtotime($user['last_activity']) : strtotime($user['created_at']);
            if ((time() - $last_act) <= (30 * 24 * 60 * 60)) {
                // Log the user in
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['session_token'] = $user['session_token'];

                // Update last activity
                $upd = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
                $upd->execute([$user['id']]);

                // Renew cookies for 30 days
                enteangadi_set_cookie('enteangadi_remember_user', $user['id'], time() + 30 * 24 * 60 * 60);
                enteangadi_set_cookie('enteangadi_remember_token', $user['session_token'], time() + 30 * 24 * 60 * 60);
            } else {
                // Inactivity > 30 days. Clear cookies.
                enteangadi_set_cookie('enteangadi_remember_user', '', time() - 3600);
                enteangadi_set_cookie('enteangadi_remember_token', '', time() - 3600);
            }
        } else {
            // Invalid token/user. Clear cookies.
            enteangadi_set_cookie('enteangadi_remember_user', '', time() - 3600);
            enteangadi_set_cookie('enteangadi_remember_token', '', time() - 3600);
        }
    }

    // Global session token & inactivity check for remote logout, deleted users, and 30-day inactivity
    if (isset($_SESSION['user_id']) && !isset($_SESSION['is_admin_session'])) {
        $stmt = $pdo->prepare("SELECT session_token, last_activity, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_record = $stmt->fetch();

        // 1. If user is deleted or token doesn't match
        if (!$user_record || (isset($user_record['session_token']) && (!isset($_SESSION['session_token']) || $_SESSION['session_token'] !== $user_record['session_token']))) {
            session_destroy();
            enteangadi_set_cookie('enteangadi_remember_user', '', time() - 3600);
            enteangadi_set_cookie('enteangadi_remember_token', '', time() - 3600);

            $current_page = basename($_SERVER['PHP_SELF']);
            if ($current_page != 'login.php' && $current_page != 'index.php') {
                header("Location: login.php?msg=account_inactive");
                exit;
            }
        } else {
            // 2. Inactivity Check (30 days)
            $last_act = $user_record['last_activity'] ? strtotime($user_record['last_activity']) : strtotime($user_record['created_at']);
            if ((time() - $last_act) > (30 * 24 * 60 * 60)) {
                // Exceeded 30 days inactivity. Log out.
                $upd = $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
                $upd->execute([$_SESSION['user_id']]);

                session_destroy();
                enteangadi_set_cookie('enteangadi_remember_user', '', time() - 3600);
                enteangadi_set_cookie('enteangadi_remember_token', '', time() - 3600);

                // Determine redirect path depending on current location
                $current_dir = basename(dirname($_SERVER['PHP_SELF']));
                $redirect_prefix = ($current_dir == 'user' || $current_dir == 'admin' || $current_dir == 'guest') ? '../' : '';
                header("Location: " . $redirect_prefix . "login.php?msg=session_expired");
                exit;
            } else {
                // 3. Update activity and renew cookies
                // To avoid writing to DB on every request, write only if last update was > 15 minutes (900 seconds) ago
                if (!$user_record['last_activity'] || (time() - strtotime($user_record['last_activity'])) > 900) {
                    $upd = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
                    $upd->execute([$_SESSION['user_id']]);
                }

                // Renew cookies
                enteangadi_set_cookie('enteangadi_remember_user', $_SESSION['user_id'], time() + 30 * 24 * 60 * 60);
                enteangadi_set_cookie('enteangadi_remember_token', $_SESSION['session_token'], time() + 30 * 24 * 60 * 60);
            }
        }
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Intercept unauthenticated visits to protected user pages to perform client-side auto-login
if (!isset($_SESSION['user_id'])) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $current_page = basename($_SERVER['PHP_SELF']);

    $is_user_page = (strpos($request_uri, '/user/') !== false);
    $is_api_call = (strpos($current_page, 'api_') === 0 || $current_page === 'toggle_wishlist.php' || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false));

    if ($is_user_page && !$is_api_call) {
        ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <title>Authenticating...</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <script>
                (function () {
                    const uid = localStorage.getItem('enteangadi_user_id');
                    const token = localStorage.getItem('enteangadi_session_token');
                    if (uid && token) {
                        fetch('../api/auto_login.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'user_id=' + encodeURIComponent(uid) + '&session_token=' + encodeURIComponent(token)
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    setTimeout(() => {
                                        location.reload();
                                    }, 100);
                                } else {
                                    localStorage.removeItem('enteangadi_user_id');
                                    localStorage.removeItem('enteangadi_session_token');
                                    window.location.href = '../login.php';
                                }
                            })
                            .catch(() => {
                                window.location.href = '../login.php';
                            });
                    } else {
                        window.location.href = '../login.php';
                    }
                })();
            </script>
        </head>

        <body>
            <div id="loader-wrapper" class="loader-active" style="background: var(--background);">
                <div class="loader-logo">
                    <?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?>
                </div>
                <div class="loader-location-status" style="color: var(--text-dark);">
                    <i class="fa fa-spinner fa-spin" style="color: var(--primary-green);"></i> Restoring session...
                </div>
            </div>
        </body>

        </html>
        <?php
        exit;
    }
}
?>