<?php
$content = file_get_contents(dirname(__DIR__) . '/admin/settings.php');
$lines = explode("\n", $content);

echo "Searching for app_settings load code:\n";
foreach ($lines as $index => $line) {
    if (stripos($line, 'app_settings') !== false) {
        $lineNum = $index + 1;
        echo "Line $lineNum: " . trim($line) . "\n";
    }
}
?>
