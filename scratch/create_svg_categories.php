<?php
require_once dirname(__DIR__) . '/config.php';

$dest_dir = dirname(__DIR__) . '/uploads/categories/';
if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0777, true);
}

$svg_data = [
    'mobiles.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#f1f5f9"/>
  <rect x="34" y="16" width="32" height="68" rx="7" fill="#1e293b"/>
  <rect x="38" y="22" width="24" height="52" rx="3" fill="#38bdf8"/>
  <circle cx="50" cy="19" r="1.5" fill="#94a3b8"/>
  <circle cx="50" cy="79" r="3" fill="#94a3b8"/>
</svg>',

    'farm_products.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#fef9c3"/>
  <path d="M50 20 C65 20, 70 40, 50 65 C30 40, 35 20, 50 20 Z" fill="#22c55e"/>
  <path d="M50 35 C60 35, 65 50, 50 70 C35 50, 40 35, 50 35 Z" fill="#86efac"/>
  <circle cx="42" cy="58" r="16" fill="#ef4444"/>
  <path d="M42 42 C44 38, 48 38, 48 42" stroke="#854d0e" stroke-width="3" fill="none"/>
</svg>',

    'cars.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#dbeafe"/>
  <path d="M25 54 L25 44 C25 36, 32 34, 50 34 C68 34, 75 36, 75 44 L75 54 L78 57 C80 59, 78 64, 74 64 L26 64 C22 64, 20 59, 22 57 Z" fill="#1e3a8a"/>
  <path d="M30 46 C30 38, 35 37, 50 37 C65 37, 70 38, 70 46 Z" fill="#93c5fd"/>
  <circle cx="32" cy="56" r="4" fill="#fef08a"/>
  <circle cx="68" cy="56" r="4" fill="#fef08a"/>
  <rect x="26" y="64" width="10" height="6" rx="2" fill="#0f172a"/>
  <rect x="64" y="64" width="10" height="6" rx="2" fill="#0f172a"/>
</svg>',

    'bikes.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#ccfbf1"/>
  <circle cx="32" cy="60" r="14" stroke="#0f172a" stroke-width="6" fill="none"/>
  <circle cx="68" cy="60" r="14" stroke="#0f172a" stroke-width="6" fill="none"/>
  <circle cx="32" cy="60" r="4" fill="#94a3b8"/>
  <circle cx="68" cy="60" r="4" fill="#94a3b8"/>
  <path d="M32 60 L48 60 L60 42 L42 42 L32 60" stroke="#0f172a" stroke-width="5" fill="none" stroke-linejoin="round"/>
  <path d="M48 60 L54 35" stroke="#0f172a" stroke-width="5" fill="none"/>
  <path d="M50 35 L60 35 L58 42" stroke="#0d9488" stroke-width="5" fill="none" stroke-linejoin="round"/>
  <path d="M38 42 L46 42" stroke="#0d9488" stroke-width="6" fill="none"/>
</svg>',

    'properties.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#f3e8ff"/>
  <path d="M22 48 L50 22 L78 48 Z" fill="#7e3af2"/>
  <rect x="28" y="48" width="44" height="32" fill="#d8b4fe"/>
  <rect x="44" y="60" width="12" height="20" fill="#6b21a8"/>
  <rect x="34" y="54" width="8" height="8" rx="1" fill="#ffffff"/>
  <rect x="58" y="54" width="8" height="8" rx="1" fill="#ffffff"/>
</svg>',

    'jobs.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#ffedd5"/>
  <rect x="24" y="32" width="52" height="42" rx="6" fill="#ea580c"/>
  <path d="M40 32 L40 24 C40 22, 42 20, 50 20 C58 20, 60 22, 60 24 L60 32" stroke="#d97706" stroke-width="5" fill="none"/>
  <rect x="46" y="48" width="8" height="10" rx="1" fill="#fef08a"/>
  <circle cx="50" cy="53" r="1.5" fill="#000000"/>
  <line x1="34" y1="32" x2="34" y2="74" stroke="#d97706" stroke-width="3"/>
  <line x1="66" y1="32" x2="66" y2="74" stroke="#d97706" stroke-width="3"/>
</svg>',

    'electronics__appliances.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#e0f2fe"/>
  <rect x="22" y="24" width="56" height="38" rx="4" fill="#0f172a"/>
  <rect x="26" y="28" width="48" height="30" fill="#38bdf8"/>
  <path d="M44 62 L56 62 L53 72 L47 72 Z" fill="#64748b"/>
  <rect x="36" y="72" width="28" height="4" rx="2" fill="#475569"/>
  <circle cx="70" cy="24" r="5" fill="#f59e0b"/>
</svg>',

    'commercial_vehicles__spares.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#f1f5f9"/>
  <rect x="20" y="28" width="42" height="34" rx="3" fill="#475569"/>
  <path d="M62 38 L72 38 L78 48 L78 62 L62 62 Z" fill="#0f172a"/>
  <path d="M64 42 L71 42 L74 48 L64 48 Z" fill="#94a3b8"/>
  <circle cx="32" cy="66" r="8" fill="#0f172a"/>
  <circle cx="32" cy="66" r="3" fill="#ffffff"/>
  <circle cx="66" cy="66" r="8" fill="#0f172a"/>
  <circle cx="66" cy="66" r="3" fill="#ffffff"/>
</svg>',

    'furniture.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#fef3c7"/>
  <rect x="25" y="32" width="50" height="24" rx="6" fill="#b45309"/>
  <rect x="20" y="44" width="10" height="22" rx="4" fill="#d97706"/>
  <rect x="70" y="44" width="10" height="22" rx="4" fill="#d97706"/>
  <rect x="28" y="46" width="44" height="14" rx="3" fill="#ea580c"/>
  <line x1="26" y1="64" x2="22" y2="76" stroke="#451a03" stroke-width="4"/>
  <line x1="74" y1="64" x2="78" y2="76" stroke="#451a03" stroke-width="4"/>
</svg>',

    'fashion.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#fce7f3"/>
  <path d="M50 28 C50 20, 54 20, 54 24" stroke="#475569" stroke-width="3" fill="none"/>
  <path d="M30 42 L50 28 L70 42 Z" stroke="#475569" stroke-width="3" fill="none" stroke-linejoin="round"/>
  <path d="M32 40 L42 40 L45 35 L55 35 L58 40 L68 40 L64 50 L64 74 L36 74 L36 50 Z" fill="#db2777"/>
  <path d="M47 35 L50 40 L53 35 Z" fill="#fce7f3"/>
</svg>',

    'books_sports__hobbies.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#ffedd5"/>
  <rect x="25" y="38" width="46" height="34" rx="3" fill="#ea580c" transform="rotate(-10 48 55)"/>
  <rect x="29" y="40" width="38" height="30" rx="1" fill="#fff" transform="rotate(-10 48 55)"/>
  <path d="M42 40 L42 76 L48 76 L48 40" fill="#dc2626" transform="rotate(-10 48 55)"/>
  <path d="M54 36 L66 36 L64 48 C64 54, 58 56, 58 60 L62 60 L62 64 L48 64" stroke="#d97706" stroke-width="3" fill="none" transform="rotate(10 50 50)"/>
</svg>',

    'pets.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#dcfce7"/>
  <path d="M50 68 C58 68, 64 62, 60 52 C57 44, 43 44, 40 52 C36 62, 42 68, 50 68 Z" fill="#15803d"/>
  <circle cx="32" cy="42" r="7" fill="#16a34a"/>
  <circle cx="44" cy="30" r="7" fill="#16a34a"/>
  <circle cx="56" cy="30" r="7" fill="#16a34a"/>
  <circle cx="68" cy="42" r="7" fill="#16a34a"/>
</svg>',

    'services.svg' => '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="50" fill="#ecfeff"/>
  <circle cx="50" cy="50" r="18" stroke="#0891b2" stroke-width="6" fill="none"/>
  <path d="M50 22 L50 28 M50 72 L50 78 M22 50 L28 50 M72 50 L78 50 M31 31 L35 35 M65 65 L69 69 M31 69 L35 65 M65 31 L69 35" stroke="#0891b2" stroke-width="6" stroke-linecap="round"/>
  <circle cx="50" cy="50" r="7" fill="#ecfeff"/>
  <circle cx="50" cy="50" r="4" fill="#0891b2"/>
</svg>'
];

try {
    $pdo->beginTransaction();

    // 1. Write the SVG files and update their main category mappings in the DB
    foreach ($svg_data as $filename => $svg_content) {
        $dest_path = $dest_dir . $filename;
        file_put_contents($dest_path, $svg_content);
        
        // Map filename to main category name in DB
        $category_name = '';
        if ($filename === 'mobiles.svg') $category_name = 'Mobiles';
        elseif ($filename === 'farm_products.svg') $category_name = 'Farm products';
        elseif ($filename === 'cars.svg') $category_name = 'Cars';
        elseif ($filename === 'bikes.svg') $category_name = 'Bikes';
        elseif ($filename === 'properties.svg') $category_name = 'Properties';
        elseif ($filename === 'jobs.svg') $category_name = 'Jobs';
        elseif ($filename === 'electronics__appliances.svg') $category_name = 'Electronics & Appliances';
        elseif ($filename === 'commercial_vehicles__spares.svg') $category_name = 'Commercial Vehicles & Spares';
        elseif ($filename === 'furniture.svg') $category_name = 'Furniture';
        elseif ($filename === 'fashion.svg') $category_name = 'Fashion';
        elseif ($filename === 'books_sports__hobbies.svg') $category_name = 'Books, Sports & Hobbies';
        elseif ($filename === 'pets.svg') $category_name = 'Pets';
        elseif ($filename === 'services.svg') $category_name = 'Services';
        
        if (!empty($category_name)) {
            $db_relative_path = 'uploads/categories/' . $filename;
            
            $stmt = $pdo->prepare("UPDATE categories SET photo_path = ? WHERE name = ? AND parent_id IS NULL");
            $stmt->execute([$db_relative_path, $category_name]);
            
            echo "Wrote & Updated Main Category: $category_name -> $db_relative_path\n";
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
    echo "Propagated SVG photo paths from parent categories to subcategories.\n";

    $pdo->commit();
    echo "\nAll categories and subcategories updated to crisp SVGs successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>
