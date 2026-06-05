<?php
// Safety Check: Prevent running this database-wiping script on a live production server.
$allowed_hosts = ['localhost', '127.0.0.1', '[::1]'];
$current_host = $_SERVER['HTTP_HOST'] ?? '';
$is_local = false;
foreach ($allowed_hosts as $host_pattern) {
    if (stripos($current_host, $host_pattern) !== false) {
        $is_local = true;
        break;
    }
}
if (!$is_local && PHP_SAPI !== 'cli') {
    die("ERROR: setup_db.php can only be executed in a local development environment (localhost). Running this on a live hosted server is blocked for safety because it drops the database.");
}

$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Drop and recreate database to ensure fresh start with all columns
    $pdo->exec("DROP DATABASE IF EXISTS enteangadi;");
    $pdo->exec("CREATE DATABASE enteangadi;");
    $pdo->exec("USE enteangadi;");

    $sql = file_get_contents(__DIR__ . '/db_schema.sql');
    // Remove USE statement from SQL if it exists to avoid conflict
    $sql = str_replace("CREATE DATABASE IF NOT EXISTS enteangadi;", "", $sql);
    $sql = str_replace("USE enteangadi;", "", $sql);
    
    $pdo->exec($sql);

    // Hash admin password and update (the INSERT in db_schema handles the admin user)
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$admin_pass]);

    echo "Database reset and created successfully! All columns synchronized.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>