<?php
// Simple diagnostic script to check notification system setup
session_start();

echo "<h2>Notification System Diagnostic</h2>";

// Check if required files exist
$required_files = [
    'mark-notifications-read.php',
    'config.php',
    'functions.php'
];

echo "<h3>File Check:</h3>";
foreach ($required_files as $file) {
    $exists = file_exists($file);
    echo "<p>$file: " . ($exists ? "✅ EXISTS" : "❌ MISSING") . "</p>";
}

// Check database connection
echo "<h3>Database Check:</h3>";
try {
    if (function_exists('getDbConnection')) {
        $conn = getDbConnection();
        echo "<p>Database connection: ✅ SUCCESS</p>";
        
        // Check required tables
        $tables = ['users', 'notification_reads', 'lead_activities', 'memos'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            echo "<p>Table '$table': " . ($result && $result->num_rows > 0 ? "✅ EXISTS" : "❌ MISSING") . "</p>";
        }
        
        $conn->close();
    } else {
        echo "<p>getDbConnection function: ❌ NOT FOUND</p>";
    }
} catch (Exception $e) {
    echo "<p>Database connection: ❌ ERROR - " . $e->getMessage() . "</p>";
}

// Check session status
echo "<h3>Session Check:</h3>";
echo "<p>Session started: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ YES" : "❌ NO") . "</p>";
echo "<p>User logged in: " . (isset($_SESSION['user_id']) ? "✅ YES (ID: " . $_SESSION['user_id'] . ")" : "❌ NO") . "</p>";
/// Check if notification functions are included
echo "<h3>Function Check:</h3>";
echo "<h3>Instructions:</h3>";
echo "<ol>";
echo "<li>Make sure all required files exist in the same directory as your header.php</li>";
echo "<li>Ensure your config.php and functions.php files are properly configured</li>";
echo "<li>Check that the database tables exist and have the correct structure</li>";
echo "<li>Make sure the user is logged in with a valid session</li>";
echo "<li>Include notification-functions.php in your header.php file</li>";
echo "<li>Include notification-handler.js in your HTML pages</li>";
echo "</ol>";
?>
