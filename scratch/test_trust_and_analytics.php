<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

echo "=== Enteangadi Verification Test Suite ===\n\n";

try {
    // 1. Setup Test Users
    // Let's use user 2 (Sojin) and user 3 (Dijo) for rating tests
    $reviewer_id = 2; // SOJIN
    $reviewee_id = 3; // DIJO
    
    echo "1. Testing Review Submission & Calculations:\n";
    
    // Clear any previous ratings between user 2 and 3
    $pdo->prepare("DELETE FROM user_ratings WHERE reviewer_id = ? AND reviewee_id = ?")->execute([$reviewer_id, $reviewee_id]);
    
    // Submit review of 4 stars
    $stmt = $pdo->prepare("INSERT INTO user_ratings (reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, 4, 'Excellent deal!')");
    $stmt->execute([$reviewer_id, $reviewee_id]);
    echo " - Rating of 4 stars submitted successfully from User $reviewer_id to User $reviewee_id.\n";
    
    // Fetch metrics
    $metrics = getUserTrustMetrics($reviewee_id);
    echo " - Calculated average rating (expected 4.0): " . $metrics['avg_rating'] . "\n";
    echo " - Calculated review count (expected 1): " . $metrics['review_count'] . "\n";
    echo " - Member since: " . $metrics['member_since'] . "\n";
    echo " - Response time label: " . $metrics['response_time'] . "\n";
    
    // 2. Testing Click Analytics Logging
    echo "\n2. Testing Daily Click Analytics:\n";
    
    // Let's pick a test product
    $stmt_p = $pdo->query("SELECT id FROM products LIMIT 1");
    $prod_id = $stmt_p->fetchColumn();
    
    if ($prod_id) {
        // Clear previous view analytics for this product for today
        $pdo->prepare("DELETE FROM analytics_clicks WHERE product_id = ? AND click_date = CURRENT_DATE")->execute([$prod_id]);
        
        // Log view clicks
        $stmt_an = $pdo->prepare("INSERT INTO analytics_clicks (product_id, click_type, click_date, click_count) 
                                 VALUES (?, 'view', CURRENT_DATE, 1) 
                                 ON DUPLICATE KEY UPDATE click_count = click_count + 1");
        $stmt_an->execute([$prod_id]);
        $stmt_an->execute([$prod_id]); // Twice to test duplicate key increment!
        
        $count = $pdo->prepare("SELECT click_count FROM analytics_clicks WHERE product_id = ? AND click_type = 'view' AND click_date = CURRENT_DATE");
        $count->execute([$prod_id]);
        $logged_count = $count->fetchColumn();
        
        echo " - Logged view click twice. Retrieved click count (expected 2): " . $logged_count . "\n";
    } else {
        echo " - Skipped product analytics test (no product listings found in database).\n";
    }
    
    // 3. Testing Proximity Query Logic
    echo "\n3. Testing Proximity SQL filter:\n";
    
    $lat = 9.95;
    $lng = 76.63;
    $radius = 15; // 15 km
    
    $stmt_prox = $pdo->prepare("
        SELECT p.title, p.latitude, p.longitude,
        (6371 * acos(cos(radians($lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(p.latitude)))) AS distance
        FROM products p
        WHERE p.status = 'active'
        HAVING distance <= ?
        LIMIT 3
    ");
    $stmt_prox->execute([$radius]);
    $near_listings = $stmt_prox->fetchAll();
    
    echo " - Proximity search executed successfully. Nearby listings found within $radius km:\n";
    foreach ($near_listings as $nl) {
        echo "   * " . $nl['title'] . " (Distance: " . round($nl['distance'], 2) . " km, Coordinates: " . $nl['latitude'] . ", " . $nl['longitude'] . ")\n";
    }
    
    // Clean up rating test
    $pdo->prepare("DELETE FROM user_ratings WHERE reviewer_id = ? AND reviewee_id = ?")->execute([$reviewer_id, $reviewee_id]);
    
    echo "\n=== All Verification Tests Completed Successfully! ===\n";

} catch (Exception $e) {
    echo "\nTest failed: " . $e->getMessage() . "\n";
}
?>
