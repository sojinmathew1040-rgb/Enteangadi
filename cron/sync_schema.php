<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain');

$sql_file = __DIR__ . '/../db/enteangadi.sql';

try {
    $res = exportFullDatabaseToSql($pdo, $sql_file);
    if ($res['success']) {
        echo "SUCCESS: Database backup synchronized to db/enteangadi.sql (" . $res['tables'] . " tables, " . $res['rows'] . " rows, " . $res['bytes'] . " bytes)\n";
    } else {
        echo "ERROR: " . ($res['error'] ?? 'Backup export failed') . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

