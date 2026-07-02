<?php
require_once dirname(__DIR__) . '/config.php';

$dest_dir = dirname(__DIR__) . '/uploads/categories/';
if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0777, true);
}

// 1. Unsplash Photographic Images URLs (curated for natural, real, and pleasant look)
$image_sources = [
    'mobiles.png' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&h=400&fit=crop',
    'farm_products.png' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&h=400&fit=crop',
    'cars.png' => 'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?w=400&h=400&fit=crop',
    'bikes.png' => 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?w=400&h=400&fit=crop',
    'properties.png' => 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=400&h=400&fit=crop',
    'jobs.png' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=400&h=400&fit=crop',
    'electronics__appliances.png' => 'https://images.unsplash.com/photo-1588508065123-287b28e013da?w=400&h=400&fit=crop',
    'commercial_vehicles__spares.png' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=400&h=400&fit=crop',
    'furniture.png' => 'https://images.unsplash.com/photo-1592078615290-033ee584e267?w=400&h=400&fit=crop',
    'fashion.png' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=400&h=400&fit=crop',
    'books_sports__hobbies.png' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=400&h=400&fit=crop',
    'pets.png' => 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?w=400&h=400&fit=crop',
    'services.png' => 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?w=400&h=400&fit=crop'
];

// Target pastel background color: warm pastel beige/cream (RGB: 245, 243, 239)
$bgR = 245;
$bgG = 243;
$bgB = 239;
$destSize = 300;

function downloadImage($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && !empty($data)) {
        return imagecreatefromstring($data);
    }
    return null;
}

function processAndFrameImage($srcImg, $destSize, $bgR, $bgG, $bgB) {
    $srcW = imagesx($srcImg);
    $srcH = imagesy($srcImg);
    
    // Create new destination canvas
    $dstImg = imagecreatetruecolor($destSize, $destSize);
    
    // Allocate pastel background
    $bgColor = imagecolorallocate($dstImg, $bgR, $bgG, $bgB);
    imagefill($dstImg, 0, 0, $bgColor);
    
    // Circle parameters (circle occupies 85% of canvas area to leave a nice pastel margin)
    $circleRadius = ($destSize * 0.85) / 2;
    $circleX = $destSize / 2;
    $circleY = $destSize / 2;
    
    // Crop center square from source
    $minDim = min($srcW, $srcH);
    $srcX = ($srcW - $minDim) / 2;
    $srcY = ($srcH - $minDim) / 2;
    
    // Compositing the photographic image inside the circle
    for ($x = 0; $x < $destSize; $x++) {
        for ($y = 0; $y < $destSize; $y++) {
            $dx = $x - $circleX;
            $dy = $y - $circleY;
            $dist = sqrt($dx*$dx + $dy*$dy);
            
            // Antialiased edge thresholding
            if ($dist <= $circleRadius) {
                // Map to source coordinate system
                $normX = ($x - ($circleX - $circleRadius)) / ($circleRadius * 2);
                $normY = ($y - ($circleY - $circleRadius)) / ($circleRadius * 2);
                
                $sx = intval($srcX + $normX * $minDim);
                $sy = intval($srcY + $normY * $minDim);
                
                if ($sx >= 0 && $sx < $srcW && $sy >= 0 && $sy < $srcH) {
                    $rgb = imagecolorat($srcImg, $sx, $sy);
                    
                    // Simple anti-aliasing on the edge boundary
                    if ($circleRadius - $dist < 1.0) {
                        // Blend edge pixel with pastel background
                        $colors = imagecolorsforindex($srcImg, $rgb);
                        $alpha = $circleRadius - $dist; // 0 to 1
                        $r = intval($colors['red'] * $alpha + $bgR * (1 - $alpha));
                        $g = intval($colors['green'] * $alpha + $bgG * (1 - $alpha));
                        $b = intval($colors['blue'] * $alpha + $bgB * (1 - $alpha));
                        $blendColor = imagecolorallocate($dstImg, $r, $g, $b);
                        imagesetpixel($dstImg, $x, $y, $blendColor);
                    } else {
                        imagesetpixel($dstImg, $x, $y, $rgb);
                    }
                }
            }
        }
    }
    
    return $dstImg;
}

try {
    $pdo->beginTransaction();

    foreach ($image_sources as $filename => $url) {
        echo "Processing category: $filename...\n";
        
        $srcImg = downloadImage($url);
        if (!$srcImg) {
            echo "Failed to download or parse image for: $filename from $url\n";
            continue;
        }
        
        $processedImg = processAndFrameImage($srcImg, $destSize, $bgR, $bgG, $bgB);
        imagedestroy($srcImg);
        
        if ($processedImg) {
            $destPath = $dest_dir . $filename;
            
            // Save as JPEG to keep size efficient and format consistent
            imagejpeg($processedImg, $destPath, 92);
            imagedestroy($processedImg);
            
            // Map back to DB category
            $category_name = '';
            if ($filename === 'mobiles.png') $category_name = 'Mobiles';
            elseif ($filename === 'farm_products.png') $category_name = 'Farm products';
            elseif ($filename === 'cars.png') $category_name = 'Cars';
            elseif ($filename === 'bikes.png') $category_name = 'Bikes';
            elseif ($filename === 'properties.png') $category_name = 'Properties';
            elseif ($filename === 'jobs.png') $category_name = 'Jobs';
            elseif ($filename === 'electronics__appliances.png') $category_name = 'Electronics & Appliances';
            elseif ($filename === 'commercial_vehicles__spares.png') $category_name = 'Commercial Vehicles & Spares';
            elseif ($filename === 'furniture.png') $category_name = 'Furniture';
            elseif ($filename === 'fashion.png') $category_name = 'Fashion';
            elseif ($filename === 'books_sports__hobbies.png') $category_name = 'Books, Sports & Hobbies';
            elseif ($filename === 'pets.png') $category_name = 'Pets';
            elseif ($filename === 'services.png') $category_name = 'Services';
            
            if (!empty($category_name)) {
                $db_relative_path = 'uploads/categories/' . $filename;
                
                $stmt = $pdo->prepare("UPDATE categories SET photo_path = ? WHERE name = ? AND parent_id IS NULL");
                $stmt->execute([$db_relative_path, $category_name]);
                
                echo "Successfully created and updated DB mapping for: $category_name -> $db_relative_path\n";
            }
        }
    }

    // 2. Propagate photo_path to subcategories
    $propagate_stmt = $pdo->prepare("
        UPDATE categories c
        JOIN categories p ON c.parent_id = p.id
        SET c.photo_path = p.photo_path
        WHERE c.parent_id IS NOT NULL AND p.photo_path IS NOT NULL
    ");
    $propagate_stmt->execute();
    echo "Successfully propagated photo paths from parent categories to subcategories.\n";

    $pdo->commit();
    echo "\nAll category images regenerated and updated successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>
