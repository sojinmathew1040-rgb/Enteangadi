<?php
require_once dirname(__DIR__) . '/config.php';

try {
    $pdo->beginTransaction();

    // 1. Rename existing categories to match OLX category structure and retain product references
    // ID 11: ELECTRONICS -> Mobiles
    $stmt = $pdo->prepare("UPDATE categories SET name = 'Mobiles' WHERE id = 11");
    $stmt->execute();

    // ID 12: Mobile -> Mobile Phones
    $stmt = $pdo->prepare("UPDATE categories SET name = 'Mobile Phones' WHERE id = 12");
    $stmt->execute();

    // ID 13: Tablet -> Tablets
    $stmt = $pdo->prepare("UPDATE categories SET name = 'Tablets' WHERE id = 13");
    $stmt->execute();

    // ID 14: ACCESORIES -> Accessories
    $stmt = $pdo->prepare("UPDATE categories SET name = 'Accessories' WHERE id = 14");
    $stmt->execute();

    // 2. Define all the other OLX main categories and their subcategories
    $olx_structure = [
        'Cars' => [
            'Cars'
        ],
        'Bikes' => [
            'Motorcycles',
            'Scooters',
            'Spare Parts',
            'Bicycles'
        ],
        'Properties' => [
            'For Sale: Houses & Apartments',
            'For Rent: Houses & Apartments',
            'Lands & Plots',
            'For Rent: Shops & Offices',
            'For Sale: Shops & Offices',
            'PG & Guest Houses'
        ],
        'Jobs' => [
            'Data Entry & Back Office',
            'Sales & Marketing',
            'BPO & Telecaller',
            'Driver',
            'Office Assistant',
            'Delivery & Collection',
            'Teacher',
            'Cook',
            'Receptionist & Front Office',
            'Operator & Technician',
            'IT Design & Developer',
            'Other Jobs'
        ],
        'Electronics & Appliances' => [
            'TVs, Video - Audio',
            'Kitchen & Other Appliances',
            'Computers & Laptops',
            'Cameras & Lenses',
            'Games & Entertainment',
            'Fridges',
            'Washing Machines',
            'ACs'
        ],
        'Commercial Vehicles & Spares' => [
            'Commercial & Other Vehicles',
            'Spare Parts'
        ],
        'Furniture' => [
            'Sofa & Dining',
            'Beds & Wardrobes',
            'Home Decor & Garden',
            'Kids Furniture',
            'Other Household Items'
        ],
        'Fashion' => [
            'Men',
            'Women',
            'Kids'
        ],
        'Books, Sports & Hobbies' => [
            'Books',
            'Gym & Fitness',
            'Musical Instruments',
            'Sports Equipment',
            'Other Hobbies'
        ],
        'Pets' => [
            'Dogs',
            'Aquarium & Fish',
            'Pet Food & Accessories',
            'Other Pets'
        ],
        'Services' => [
            'Education & Classes',
            'Web Development',
            'Electronics & Computer Repair',
            'Drivers & Taxi',
            'Health & Beauty',
            'Other Services'
        ]
    ];

    foreach ($olx_structure as $main_cat => $sub_cats) {
        // Check if main category already exists
        $check_stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND parent_id IS NULL");
        $check_stmt->execute([$main_cat]);
        $main_id = $check_stmt->fetchColumn();

        if (!$main_id) {
            $insert_main = $pdo->prepare("INSERT INTO categories (name, parent_id, photo_path) VALUES (?, NULL, NULL)");
            $insert_main->execute([$main_cat]);
            $main_id = $pdo->lastInsertId();
            echo "Inserted Main Category: $main_cat (ID: $main_id)\n";
        } else {
            echo "Main Category already exists: $main_cat (ID: $main_id)\n";
        }

        foreach ($sub_cats as $sub_cat) {
            // Check if subcategory already exists under this parent
            $check_sub = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND parent_id = ?");
            $check_sub->execute([$sub_cat, $main_id]);
            $sub_id = $check_sub->fetchColumn();

            if (!$sub_id) {
                $insert_sub = $pdo->prepare("INSERT INTO categories (name, parent_id, photo_path) VALUES (?, ?, NULL)");
                $insert_sub->execute([$sub_cat, $main_id]);
                $sub_id = $pdo->lastInsertId();
                echo "  Inserted Subcategory: $sub_cat (ID: $sub_id)\n";
            } else {
                echo "  Subcategory already exists: $sub_cat under $main_cat\n";
            }
        }
    }

    $pdo->commit();
    echo "\nCategory structure updated successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>
