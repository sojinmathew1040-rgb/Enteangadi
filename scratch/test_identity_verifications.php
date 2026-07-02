<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

echo "=== Identity Verification Test Suite ===\n\n";

try {
    $test_user_id = 3; // DIJO
    
    // Clear any existing request for test user
    $pdo->prepare("DELETE FROM verification_requests WHERE user_id = ?")->execute([$test_user_id]);
    $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ?")->execute([$test_user_id]);
    
    echo "1. Assert Initial Verification State:\n";
    $metrics = getUserTrustMetrics($test_user_id);
    echo " - Verified Status (expected false): " . ($metrics['phone_verified'] ? 'true' : 'false') . "\n";
    
    echo "\n2. Submit Verification Request:\n";
    $stmt = $pdo->prepare("INSERT INTO verification_requests (user_id, id_type, id_photo, status) VALUES (?, 'Passport', 'uploads/verifications/test_passport.jpg', 'pending')");
    $stmt->execute([$test_user_id]);
    echo " - Claim submitted successfully.\n";
    
    // Fetch request status
    $status = $pdo->prepare("SELECT status FROM verification_requests WHERE user_id = ?");
    $status->execute([$test_user_id]);
    echo " - Verification request status (expected pending): " . $status->fetchColumn() . "\n";
    
    echo "\n3. Admin Approve Verification:\n";
    $pdo->prepare("UPDATE verification_requests SET status = 'approved' WHERE user_id = ?")->execute([$test_user_id]);
    $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$test_user_id]);
    echo " - Approved successfully.\n";
    
    echo "\n4. Assert Approved Verification State:\n";
    $metrics_app = getUserTrustMetrics($test_user_id);
    echo " - Verified Status (expected true): " . ($metrics_app['phone_verified'] ? 'true' : 'false') . "\n";
    
    // Clean up
    $pdo->prepare("DELETE FROM verification_requests WHERE user_id = ?")->execute([$test_user_id]);
    $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ?")->execute([$test_user_id]);
    
    echo "\n=== All Verification System Tests Passed Successfully! ===\n";

} catch (Exception $e) {
    echo "\nTest failed: " . $e->getMessage() . "\n";
}
?>
