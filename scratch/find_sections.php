<?php
$content = file_get_contents(dirname(__DIR__) . '/admin/settings.php');
$lines = explode("\n", $content);

echo "Searching for section IDs:\n";
foreach ($lines as $index => $line) {
    if (preg_match('/id="section-[^"]+"/', $line, $matches)) {
        $lineNum = $index + 1;
        echo "Line $lineNum: " . $matches[0] . " - " . trim($line) . "\n";
    }
}
?>
