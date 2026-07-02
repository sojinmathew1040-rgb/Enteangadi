<?php
// Set up mock session variables to satisfy admin check if needed, but we will test config & helpers directly
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

echo "Testing Dynamic Adult Content Moderation:\n\n";

try {
    // 1. Verify the key exists (seeding check)
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'adult_content_check'");
    $stmt->execute();
    $initial_val = $stmt->fetchColumn();
    echo "Initial adult_content_check value in DB: " . ($initial_val !== false ? "'$initial_val'" : "not set") . "\n";

    // 2. Test moderation when ENABLED
    echo "Testing when ENABLED (adult_content_check = 1):\n";
    $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = '1' WHERE setting_key = 'adult_content_check'");
    $stmt->execute();
    
    $inappropriate_text = "Check this nude phone body erotic post";
    $result_text_enabled = isTextInappropriate($inappropriate_text);
    echo " - Scanning text: '$inappropriate_text'\n";
    echo " - Result (expected true): " . ($result_text_enabled ? "TRUE (flagged inappropriate)" : "FALSE") . "\n";

    // 3. Test moderation when DISABLED
    echo "\nTesting when DISABLED (adult_content_check = 0):\n";
    $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = '0' WHERE setting_key = 'adult_content_check'");
    $stmt->execute();

    $result_text_disabled = isTextInappropriate($inappropriate_text);
    echo " - Scanning text: '$inappropriate_text'\n";
    echo " - Result (expected false): " . ($result_text_disabled ? "TRUE" : "FALSE (allowed directly)") . "\n";

    // Restore initial setting value
    if ($initial_val !== false) {
        $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'adult_content_check'");
        $stmt->execute([$initial_val]);
    }

    echo "\nTest Completed Successfully!\n";

} catch (Exception $e) {
    echo "Test failed: " . $e->getMessage() . "\n";
}
?>
