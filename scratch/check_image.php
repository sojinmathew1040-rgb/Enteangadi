<?php
$file = dirname(__DIR__) . '/uploads/categories/fashion.png';
if (file_exists($file)) {
    $info = getimagesize($file);
    print_r($info);
} else {
    echo "File not found\n";
}
?>
