<?php
$content = file_get_contents(dirname(__DIR__) . '/config.php');
$lines = explode("\n", $content);

echo "Searching for pdo in config.php:\n";
foreach ($lines as $index => $line) {
    if (stripos($line, 'pdo') !== false) {
        $lineNum = $index + 1;
        echo "Line $lineNum: " . trim($line) . "\n";
    }
}
?>
