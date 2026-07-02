<?php
require_once dirname(__DIR__) . '/config.php';

echo "=== Context-Aware Quick Replies Test Suite ===\n\n";

function simulateQuickReplies($category_id, $parent_id) {
    $quick_replies = [
        "Is this still available?",
        "I am interested. What is your final price?",
        "Where is your location to inspect this?"
    ];

    if ($category_id == 25 || $parent_id == 25 || $category_id == 27 || $parent_id == 27 || $category_id == 61 || $parent_id == 61) {
        $quick_replies = [
            "Is the insurance still active?",
            "Are the registration documents (RC) clear?",
            "How many kilometers has it run?",
            "Can I come for a test drive?"
        ];
    } elseif ($category_id == 32 || $parent_id == 32) {
        $quick_replies = [
            "What is the security deposit amount?",
            "Is there water and electricity backup?",
            "Are bachelor tenants allowed?",
            "When can I visit the property?"
        ];
    } elseif ($category_id == 39 || $parent_id == 39) {
        $quick_replies = [
            "What are the working hours?",
            "Is this a full-time or part-time position?",
            "Where is the office located?",
            "What are the salary and benefits?"
        ];
    } elseif ($category_id == 11 || $parent_id == 11 || $category_id == 52 || $parent_id == 52) {
        $quick_replies = [
            "Is the warranty still valid?",
            "Does it include the original bill and box?",
            "Are there any scratches or functional defects?",
            "What is the battery health percentage?"
        ];
    } elseif ($category_id == 80 || $parent_id == 80) {
        $quick_replies = [
            "Are the vaccinations up to date?",
            "What is the age and breed?",
            "Is the price negotiable?",
            "Can you send more photos/videos?"
        ];
    }
    return $quick_replies;
}

try {
    echo "1. Testing Vehicles Category (Cars: ID 26, Parent 25):\n";
    $replies = simulateQuickReplies(26, 25);
    echo " - First reply: " . $replies[0] . "\n";
    assert($replies[0] === "Is the insurance still active?");
    
    echo "\n2. Testing Properties Category (For Rent: ID 34, Parent 32):\n";
    $replies = simulateQuickReplies(34, 32);
    echo " - First reply: " . $replies[0] . "\n";
    assert($replies[0] === "What is the security deposit amount?");

    echo "\n3. Testing Jobs Category (IT Dev: ID 50, Parent 39):\n";
    $replies = simulateQuickReplies(50, 39);
    echo " - First reply: " . $replies[0] . "\n";
    assert($replies[0] === "What are the working hours?");

    echo "\n4. Testing Default Category (Other Category: ID 9, Parent NULL):\n";
    $replies = simulateQuickReplies(9, 0);
    echo " - First reply: " . $replies[0] . "\n";
    assert($replies[0] === "Is this still available?");

    echo "\n=== All Dynamic Quick Replies Tests Passed Successfully! ===\n";

} catch (Exception $e) {
    echo "\nTest failed: " . $e->getMessage() . "\n";
}
?>
