<?php
$content = file_get_contents(dirname(__DIR__) . '/client/src/App.jsx');
$lines = explode("\n", $content);

echo "Searching for send function in App.jsx:\n";
foreach ($lines as $index => $line) {
    if (stripos($line, 'send') !== false && (stripos($line, 'Message') !== false || stripos($line, 'text') !== false)) {
        $lineNum = $index + 1;
        echo "Line $lineNum: " . trim($line) . "\n";
    }
}
?>
