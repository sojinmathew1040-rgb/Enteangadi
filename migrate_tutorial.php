<?php
require_once 'config.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN tutorial_shown TINYINT(1) DEFAULT 0");
    echo "Migration successful: Column 'tutorial_shown' added to 'users' table.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
unlink(__FILE__); // Delete this file after running
?>