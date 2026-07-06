<?php
header('Content-Type: text/plain; charset=utf-8');

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'enteangadi';

try {
    echo "Connecting to MySQL server...\n";
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating database if not exists: $dbname...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbname`;");
    
    $sql_file = dirname(__DIR__) . '/db/enteangadi.sql';
    if (!file_exists($sql_file)) {
        die("Error: SQL backup file not found at $sql_file\n");
    }
    
    echo "Reading SQL backup file...\n";
    $sql = file_get_contents($sql_file);
    
    // Detect UTF-16LE and convert to UTF-8
    if (substr($sql, 0, 2) === "\xFF\xFE") {
        echo "Detected UTF-16LE encoding. Converting to UTF-8...\n";
        // Remove BOM and convert
        $sql = mb_convert_encoding(substr($sql, 2), 'UTF-8', 'UTF-16LE');
    } elseif (substr($sql, 0, 2) === "\xFE\xFF") {
        echo "Detected UTF-16BE encoding. Converting to UTF-8...\n";
        // Remove BOM and convert
        $sql = mb_convert_encoding(substr($sql, 2), 'UTF-8', 'UTF-16BE');
    }
    
    echo "Executing SQL queries. This might take a few moments...\n";
    
    // We run the SQL query directly using exec()
    $pdo->exec($sql);
    
    echo "Database successfully updated using the backup!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
