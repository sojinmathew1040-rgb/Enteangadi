<?php
require_once 'config.php';
$stmt = $pdo->query("DESCRIBE products");
$columns = $stmt->fetchAll();
header('Content-Type: application/json');
echo json_encode($columns);
?>