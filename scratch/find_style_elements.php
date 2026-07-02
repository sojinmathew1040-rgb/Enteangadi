<?php
$content = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
$lines = explode("\n", $content);

echo "Searching for product-main-layout in style.css:\n";
foreach ($lines as $index => $line) {
    if (stripos($line, 'product-main-layout') !== false) {
        $lineNum = $index + 1;
        echo "Line $lineNum: " . trim($line) . "\n";
    }
}
?>
