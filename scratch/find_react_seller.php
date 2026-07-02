<?php
$content = file_get_contents(dirname(__DIR__) . '/client/src/App.jsx');
$lines = explode("\n", $content);

echo "Searching for seller_username in App.jsx:\n";
foreach ($lines as $index => $line) {
    if (stripos($line, 'seller_username') !== false) {
        $lineNum = $index + 1;
        echo "Line $lineNum: " . trim($line) . "\n";
    }
}
?>
