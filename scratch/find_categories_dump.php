<?php
$content = file_get_contents(dirname(__DIR__) . '/db/enteangadi.sql');
$lines = explode("\n", $content);

echo "Searching for categories in enteangadi.sql:\n";
foreach ($lines as $index => $line) {
    if (stripos($line, 'categories') !== false) {
        $lineNum = $index + 1;
        echo "Line $lineNum: " . trim($line) . "\n";
    }
}
?>
