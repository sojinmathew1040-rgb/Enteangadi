<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents(__DIR__ . '/db_schema.sql');
    $pdo->exec($sql);

    // Hash admin password and update
    $pdo->exec("USE enteangadi;");
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$admin_pass]);

    echo "Database created successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>