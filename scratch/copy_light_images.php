<?php
require_once dirname(__DIR__) . '/config.php';

$dest_dir = dirname(__DIR__) . '/uploads/categories/';
if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0777, true);
}

$light_images = [
    'Mobiles' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\light_mobiles_1782971319554.png',
    'Farm products' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\light_farm_products_1782971332427.png',
    'Cars' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\light_cars_1782971421007.png',
    'Bikes' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\light_bikes_1782971438775.png',
    'Properties' => 'C:\Users\Dijo J Perumaly\.gemini\antigravity-ide\brain\e30de3d6-5313-4f60-9014-ea4fecc0549e\light_properties_1782971454579.png'
];

try {
    $pdo->beginTransaction();

    // 1. Copy the new light images and update their parent categories
    foreach ($light_images as $category_name => $source_path) {
        if (!file_exists($source_path)) {
            echo "Source image does not exist: $source_path\n";
            continue;
        }

        $clean_name = strtolower(str_replace([' ', '&', ','], ['_', '', ''], $category_name)) . '.png';
        $dest_path = $dest_dir . $clean_name;

        if (copy($source_path, $dest_path)) {
            $db_relative_path = 'uploads/categories/' . $clean_name;
            
            $stmt = $pdo->prepare("UPDATE categories SET photo_path = ? WHERE name = ? AND parent_id IS NULL");
            $stmt->execute([$db_relative_path, $category_name]);
            
            echo "Copied & Updated Main Category: $category_name -> $db_relative_path\n";
        } else {
            echo "Failed to copy image for: $category_name\n";
        }
    }

    // 2. Propagate parent category images to all subcategories
    $propagate_stmt = $pdo->prepare("
        UPDATE categories c
        JOIN categories p ON c.parent_id = p.id
        SET c.photo_path = p.photo_path
        WHERE c.parent_id IS NOT NULL AND p.photo_path IS NOT NULL
    ");
    $propagate_stmt->execute();
    echo "Successfully propagated photo paths from parent categories to subcategories.\n";

    $pdo->commit();
    echo "\nSubcategories updated with images successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>
