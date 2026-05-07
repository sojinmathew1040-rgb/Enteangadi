<?php
session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'enteangadi';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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

    // Global session token check for remote logout
    if (isset($_SESSION['user_id']) && !isset($_SESSION['is_admin_session'])) {
        $stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $db_token = $stmt->fetchColumn();

        if ($db_token) {
            if (!isset($_SESSION['session_token']) || $_SESSION['session_token'] !== $db_token) {
                session_destroy();
                // Redirect if not already on login or index
                $current_page = basename($_SERVER['PHP_SELF']);
                if ($current_page != 'login.php' && $current_page != 'index.php') {
                    header("Location: login.php");
                    exit;
                }
            }
        }
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>