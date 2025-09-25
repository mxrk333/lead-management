<?php
// Test file to verify live search AJAX functionality
session_start();

// Simulate being logged in as user ID 1
$_SESSION['user_id'] = 1;

// Test AJAX request
$_POST['ajax'] = '1';
$_POST['search'] = 'test';

// Include the main file and capture output
ob_start();
include 'leads.php';
$output = ob_get_clean();

// Check if the response contains expected elements
$success_indicators = [
    'leads-table-container' => strpos($output, 'leads-table-container') !== false,
    'summary-cards' => strpos($output, 'summary-cards') !== false,
    'Created column' => strpos($output, '<th>Created</th>') !== false,
    'Agent column' => strpos($output, '<th>Agent</th>') !== false
];

echo "Live Search AJAX Test Results:\n";
echo "===============================\n";

foreach ($success_indicators as $test => $result) {
    echo sprintf("%-20s: %s\n", $test, $result ? "✓ PASS" : "✗ FAIL");
}

if (all_tests_passed($success_indicators)) {
    echo "\n✅ All tests PASSED! Live search AJAX is working correctly.\n";
} else {
    echo "\n❌ Some tests FAILED. Check the implementation.\n";
    echo "\nResponse preview (first 300 characters):\n";
    echo substr($output, 0, 300) . "...\n";
}

function all_tests_passed($indicators) {
    foreach ($indicators as $result) {
        if (!$result) return false;
    }
    return true;
}
?>