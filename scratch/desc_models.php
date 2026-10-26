<?php
require_once __DIR__ . '/../config.php';
$cols = $pdo->query("DESCRIBE product_models")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
