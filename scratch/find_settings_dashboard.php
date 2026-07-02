<?php
$content = file_get_contents(dirname(__DIR__) . '/admin/settings.php');
$lines = explode("\n", $content);

echo "Searching for dashboard card elements in settings.php:\n";
foreach ($lines as $index => $line) {
    if (stripos($line, 'showSection(') !== false || stripos($line, 'settings-card') !== false || stripos($line, 'card-') !== false) {
        $lineNum = $index + 1;
        echo "Line $lineNum: " . trim($line) . "\n";
    }
}
?>
