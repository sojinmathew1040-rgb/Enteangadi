<?php
require_once dirname(__DIR__) . '/config.php';

function replaceBackground($srcPath, $destPath, $targetR, $targetG, $targetB) {
    if (!file_exists($srcPath)) {
        echo "Source file does not exist: $srcPath\n";
        return false;
    }

    $img = imagecreatefromjpeg($srcPath);
    if (!$img) {
        echo "Failed to load JPEG image: $srcPath\n";
        return false;
    }

    $width = imagesx($img);
    $height = imagesy($img);

    // Allocate the target pastel color
    $pastelColor = imagecolorallocate($img, $targetR, $targetG, $targetB);

    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($img, $x, $y);
            $colors = imagecolorsforindex($img, $rgb);
            
            $r = $colors['red'];
            $g = $colors['green'];
            $b = $colors['blue'];

            // Calculate brightness of the pixel
            $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
            
            if ($brightness < 45) { 
                // Near-black background: replace completely
                imagesetpixel($img, $x, $y, $pastelColor);
            } else if ($brightness < 110) {
                // Soft shadow blending: interpolate to prevent halos
                $ratio = ($brightness - 45) / 65; // 0 to 1
                $newR = intval($r * $ratio + $targetR * (1 - $ratio));
                $newG = intval($g * $ratio + $targetG * (1 - $ratio));
                $newB = intval($b * $ratio + $targetB * (1 - $ratio));
                $blendColor = imagecolorallocate($img, $newR, $newG, $newB);
                imagesetpixel($img, $x, $y, $blendColor);
            }
        }
    }

    // Save as JPEG back to target location
    imagejpeg($img, $destPath, 92);
    imagedestroy($img);
    return true;
}

$categories_dir = dirname(__DIR__) . '/uploads/categories/';

// Map the 8 remaining dark categories to their soft light pastel colors (RGB)
$color_mappings = [
    'jobs.png' => [255, 243, 224],                      // soft pastel orange/peach
    'electronics__appliances.png' => [238, 242, 255],   // soft pastel blue/indigo
    'commercial_vehicles__spares.png' => [224, 242, 254], // soft pastel cyan
    'furniture.png' => [254, 243, 199],                 // soft pastel yellow/cream
    'fashion.png' => [253, 242, 248],                   // soft pastel pink
    'books_sports__hobbies.png' => [254, 226, 226],      // soft pastel red/pink
    'pets.png' => [240, 253, 244],                      // soft pastel green
    'services.png' => [250, 245, 255]                   // soft pastel purple/violet
];

foreach ($color_mappings as $filename => $rgb) {
    $filePath = $categories_dir . $filename;
    echo "Processing $filename...\n";
    if (replaceBackground($filePath, $filePath, $rgb[0], $rgb[1], $rgb[2])) {
        echo "Successfully updated background for $filename to RGB(" . implode(",", $rgb) . ")\n";
    }
}

echo "\nAll category backgrounds updated successfully!\n";
?>
