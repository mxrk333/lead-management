<?php
echo "<h1>Lead Management Setup Checker</h1>";

// Check current directory
echo "<h2>Directory Information</h2>";
echo "<p><strong>Current Directory:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

// Check required files
echo "<h2>Required Files</h2>";
$required_files = [
    'config.php' => 'Database configuration file',
    'mark-notifications-read.php' => 'AJAX handler for marking notifications as read',
    'header.php' => 'Main header component',
    'test-db-connection.php' => 'Database connection tester'
];

$all_files_exist = true;
foreach ($required_files as $file => $description) {
    $exists = file_exists($file);
    $status = $exists ? "✅ EXISTS" : "❌ MISSING";
    echo "<p><strong>{$file}:</strong> {$status} - {$description}</p>";
    if (!$exists) $all_files_exist = false;
}

// Check database connection
echo "<h2>Database Connection</h2>";
try {
    $conn = new mysqli('localhost', 'root', '', 'real_estate_leads');
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Check if users table exists and has the required column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'last_notification_read'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✅ last_notification_read column exists in users table</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ last_notification_read column missing - will be created automatically</p>";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Check session
echo "<h2>Session Information</h2>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";
} else {
    echo "<p style='color: orange;'>⚠️ No user logged in - setting test user ID</p>";
    $_SESSION['user_id'] = 15; // Set a test user ID
}

// Recommendations
echo "<h2>Setup Recommendations</h2>";
if (!$all_files_exist) {
    echo "<p style='color: red;'>❌ Some required files are missing. Please ensure all files are in the same directory as this checker.</p>";
}

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='test-db-connection.php'>Test Database Connection</a></li>";
echo "<li>Include header.php in your main pages</li>";
echo "<li>Test the notification functionality</li>";
echo "</ol>";

// Show directory contents
echo "<h2>Directory Contents</h2>";
$files = scandir('.');
echo "<ul>";
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $type = is_dir($file) ? '[DIR]' : '[FILE]';
        $size = is_file($file) ? ' (' . filesize($file) . ' bytes)' : '';
        echo "<li>{$type} {$file}{$size}</li>";
    }
}
echo "</ul>";
?>
