<?php
// Function to replace near-black background with a soft pastel color
function replaceBackground($srcPath, $destPath, $targetR, $targetG, $targetB) {
    if (!file_exists($srcPath)) {
        echo "File does not exist: $srcPath\n";
        return false;
    }
    
    // Read from JPEG
    $img = imagecreatefromjpeg($srcPath);
    if (!$img) {
        echo "Failed to load image: $srcPath\n";
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
            } else if ($brightness < 100) {
                // Soft shadow blending: interpolate to prevent halos
                $ratio = ($brightness - 45) / 55; // 0 to 1
                $newR = intval($r * $ratio + $targetR * (1 - $ratio));
                $newG = intval($g * $ratio + $targetG * (1 - $ratio));
                $newB = intval($b * $ratio + $targetB * (1 - $ratio));
                $blendColor = imagecolorallocate($img, $newR, $newG, $newB);
                imagesetpixel($img, $x, $y, $blendColor);
            }
        }
    }

    // Save as JPEG (overwriting original file correctly)
    imagejpeg($img, $destPath, 90);
    imagedestroy($img);
    return true;
}

// Test with fashion
$src = dirname(__DIR__) . '/uploads/categories/fashion.png';
$dest = __DIR__ . '/fashion_light.png';
if (replaceBackground($src, $dest, 254, 242, 242)) {
    echo "Replaced background successfully!\n";
}
?>
