<?php
require_once dirname(__DIR__) . '/config.php';

$dest_dir = dirname(__DIR__) . '/uploads/categories/';
if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0777, true);
}

$image_mappings = [
    'Cars' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_cars_1782971007526.png',
    'Bikes' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_bikes_1782971022029.png',
    'Properties' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_properties_1782971036595.png',
    'Jobs' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_jobs_1782971054020.png',
    'Electronics & Appliances' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_electronics_1782971069116.png',
    'Commercial Vehicles & Spares' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_commercial_1782971081287.png',
    'Furniture' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_furniture_1782971095462.png',
    'Fashion' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_fashion_1782971114595.png',
    'Books, Sports & Hobbies' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_books_1782971128421.png',
    'Pets' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_pets_1782971140636.png',
    'Services' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\cat_services_1782971154352.png'
];

try {
    $pdo->beginTransaction();

    foreach ($image_mappings as $category_name => $source_path) {
        if (!file_exists($source_path)) {
            echo "Source image does not exist: $source_path\n";
            continue;
        }

        // Clean filename, e.g. cat_cars.png
        $clean_name = strtolower(str_replace([' ', '&', ','], ['_', '', ''], $category_name)) . '.png';
        $dest_path = $dest_dir . $clean_name;

        if (copy($source_path, $dest_path)) {
            $db_relative_path = 'uploads/categories/' . $clean_name;
            
            // Update database photo_path for this main category
            $stmt = $pdo->prepare("UPDATE categories SET photo_path = ? WHERE name = ? AND parent_id IS NULL");
            $stmt->execute([$db_relative_path, $category_name]);
            
            echo "Successfully copied and updated database for: $category_name -> $db_relative_path\n";
        } else {
            echo "Failed to copy image for: $category_name\n";
        }
    }

    $pdo->commit();
    echo "\nImages copied and database updated successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>
