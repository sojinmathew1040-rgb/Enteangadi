<?php
$res_dir = dirname(__DIR__) . '/client/android/app/src/main/res';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($res_dir));

echo "Res files list:\n";
foreach ($files as $file) {
    if ($file->isFile()) {
        $name = $file->getFilename();
        if (stripos($name, 'icon') !== false || stripos($name, 'launcher') !== false || stripos($name, 'stat') !== false) {
            echo $file->getPathname() . "\n";
        }
    }
}
?>
