<?php
require_once '../config.php';

header('Content-Type: text/plain');

$temp_categories = [
    "Vehicles",
    "Real Estate",
    "Jobs & Careers",
    "Home Services",
    "Books & Hobbies",
    "Fashion & Beauty",
    "Pets & Animals",
    "Sports & Fitness",
    "Toys & Games",
    "Music & Instruments"
];

echo "Adding temporary test categories...\n";

foreach ($temp_categories as $name) {
    // Check if category already exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND parent_id IS NULL");
    $stmt->execute([$name]);
    $cat = $stmt->fetch();

    if (!$cat) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, NULL)");
        $stmt->execute([$name]);
        echo "Inserted: $name\n";
    } else {
        echo "Already exists: $name\n";
    }
}

echo "Seeding completed successfully.\n";
?>
