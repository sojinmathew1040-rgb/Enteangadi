<?php
require_once dirname(__DIR__) . '/config.php';

$stmt = $pdo->query("SELECT * FROM categories ORDER BY id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$values = [];
foreach ($rows as $row) {
    $parent = is_null($row['parent_id']) ? 'NULL' : $row['parent_id'];
    $photo = is_null($row['photo_path']) ? 'NULL' : "'" . addslashes($row['photo_path']) . "'";
    $values[] = "(" . $row['id'] . "," . $parent . ",'" . addslashes($row['name']) . "'," . $photo . "," . $row['is_perishable'] . ",'" . $row['created_at'] . "')";
}

$sql_dump = "INSERT INTO `categories` VALUES " . implode(",", $values) . ";";
file_put_contents('scratch/categories_dump.sql', $sql_dump);
echo "SQL Dump generated successfully!\n";
?>
