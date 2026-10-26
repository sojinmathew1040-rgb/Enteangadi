<?php
require_once __DIR__ . '/../config.php';

echo "=== TEST 1: Model Fronx Body Type & Incompatible Body Types ===\n";
chdir(__DIR__ . '/../guest');
$_GET = ['category_id' => '25', 'brand' => 'Maruti Suzuki', 'model' => 'Fronx'];
ob_start();
include __DIR__ . '/../guest/index.php';
$html = ob_get_clean();

// Check if Sedan is disabled
if (strpos($html, 'text-decoration: line-through;" title="Not available for Fronx"') !== false && strpos($html, 'Sedan') !== false) {
    echo "PASS: Sedan is disabled when Fronx is selected.\n";
} else {
    echo "FAIL: Sedan disabling not found in HTML.\n";
}

// Check if Compact SUV is NOT disabled
if (strpos($html, 'title="Not available for Fronx">Compact SUV') === false) {
    echo "PASS: Compact SUV is available for Fronx.\n";
} else {
    echo "FAIL: Compact SUV was incorrectly marked disabled.\n";
}

echo "\n=== TEST 2: Filter by Body Type = Sedan ===\n";
$_GET = ['category_id' => '25', 'body_type' => 'Sedan'];
ob_start();
include __DIR__ . '/../guest/index.php';
$html2 = ob_get_clean();

// Brands that make sedans vs brands that don't
$has_honda = strpos($html2, 'Honda') !== false;
$has_skoda = strpos($html2, 'Skoda') !== false;
$has_jeep = strpos($html2, 'Jeep') !== false; // Jeep makes no sedans in our catalog
echo "Sedan filter brand list - Honda: " . ($has_honda ? 'YES' : 'NO') . ", Skoda: " . ($has_skoda ? 'YES' : 'NO') . ", Jeep: " . ($has_jeep ? 'YES' : 'NO') . "\n";

echo "\n=== TEST 3: Filter by Body Type = Sedan AND Brand = Honda ===\n";
$_GET = ['category_id' => '25', 'body_type' => 'Sedan', 'brand' => 'Honda'];
ob_start();
include __DIR__ . '/../guest/index.php';
$html3 = ob_get_clean();

$has_city = strpos($html3, 'City') !== false;
$has_amaze = strpos($html3, 'Amaze') !== false;
$has_elevate = strpos($html3, 'Elevate') !== false; // Elevate is SUV, not Sedan!
echo "Honda Sedan models - City: " . ($has_city ? 'YES' : 'NO') . ", Amaze: " . ($has_amaze ? 'YES' : 'NO') . ", Elevate: " . ($has_elevate ? 'YES' : 'NO') . "\n";

echo "\n=== TEST 4: user/index.php identical behavior ===\n";
chdir(__DIR__ . '/../user');
$_SESSION['user_id'] = 1;
$_GET = ['category_id' => '25', 'brand' => 'Maruti Suzuki', 'model' => 'Fronx'];
ob_start();
include __DIR__ . '/../user/index.php';
$html_user = ob_get_clean();

if (strpos($html_user, 'text-decoration: line-through;" title="Not available for Fronx"') !== false) {
    echo "PASS: user/index.php correctly disables incompatible body types for Fronx.\n";
} else {
    echo "FAIL: user/index.php disabling failed.\n";
}

echo "\nALL AUTOMATED CHECKS COMPLETED.\n";
