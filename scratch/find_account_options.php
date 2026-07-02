<?php
$content = file_get_contents(dirname(__DIR__) . '/client/src/App.jsx');
$lines = explode("\n", $content);

echo "Searching for account buttons in App.jsx:\n";
foreach ($lines as $index => $line) {
    if (stripos($line, 'Account') !== false || stripos($line, 'My Ads') !== false) {
        $lineNum = $index + 1;
        echo "Line $lineNum: " . trim($line) . "\n";
    }
}
?>
