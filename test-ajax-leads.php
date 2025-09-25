<?php
// Simple test to verify AJAX endpoint works
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set up test session
$_SESSION['user_id'] = 1; // Assuming user ID 1 exists

// Simulate AJAX request
$_POST['ajax'] = '1';
$_POST['search'] = 'test';

echo "Testing AJAX endpoint...\n";

// Include the main file to test the AJAX functionality
try {
    ob_start();
    include 'leads.php';
    $output = ob_get_clean();
    
    if (strpos($output, 'leads-table-container') !== false) {
        echo "✓ AJAX endpoint working correctly\n";
        echo "Response contains table container\n";
    } else {
        echo "✗ AJAX endpoint not working as expected\n";
        echo "Response preview: " . substr($output, 0, 200) . "...\n";
    }
} catch (Exception $e) {
    echo "✗ Error testing AJAX: " . $e->getMessage() . "\n";
}
?>