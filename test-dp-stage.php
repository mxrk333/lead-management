<?php
// Simple test script to diagnose DP Stage issues on production
session_start();

echo "<h1>DP Stage Production Test</h1>";

// Test 1: Basic PHP functionality
echo "<h2>1. PHP Environment</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . "</p>";
echo "<p>Upload Max Filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>Post Max Size: " . ini_get('post_max_size') . "</p>";

// Test 2: File system
echo "<h2>2. File System</h2>";
echo "<p>Current Directory: " . getcwd() . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";

// Test 3: Directory permissions
echo "<h2>3. Directory Permissions</h2>";
$upload_dir = 'uploads/receipts/';
echo "<p>Upload Directory: " . $upload_dir . "</p>";
echo "<p>Directory Exists: " . (file_exists($upload_dir) ? 'Yes' : 'No') . "</p>";
echo "<p>Directory Writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</p>";

// Test 4: Database connection
echo "<h2>4. Database Connection</h2>";
try {
    require_once 'config/database.php';
    $conn = getDbConnection();
    echo "<p>Database Connection: <span style='color: green;'>Success</span></p>";
    
    // Test a simple query
    $result = $conn->query("SELECT 1 as test");
    if ($result) {
        echo "<p>Database Query: <span style='color: green;'>Success</span></p>";
    } else {
        echo "<p>Database Query: <span style='color: red;'>Failed</span></p>";
    }
    $conn->close();
} catch (Exception $e) {
    echo "<p>Database Connection: <span style='color: red;'>Failed - " . $e->getMessage() . "</span></p>";
}

// Test 5: Required functions
echo "<h2>5. Required Functions</h2>";
$required_functions = ['getDbConnection', 'getUserById', 'addLeadActivity'];
foreach ($required_functions as $func) {
    echo "<p>$func: " . (function_exists($func) ? '<span style="color: green;">Available</span>' : '<span style="color: red;">Missing</span>') . "</p>";
}

// Test 6: Session
echo "<h2>6. Session</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID in Session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "</p>";

// Test 7: File upload test
echo "<h2>7. File Upload Test</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<p>File received: " . $_FILES['test_file']['name'] . "</p>";
    echo "<p>File size: " . $_FILES['test_file']['size'] . " bytes</p>";
    echo "<p>File type: " . $_FILES['test_file']['type'] . "</p>";
    echo "<p>Upload error: " . $_FILES['test_file']['error'] . "</p>";
} else {
    echo "<form method='post' enctype='multipart/form-data'>";
    echo "<input type='file' name='test_file' accept='image/*'>";
    echo "<button type='submit'>Test File Upload</button>";
    echo "</form>";
}

echo "<hr>";
echo "<p><a href='dp-stage.php'>Go to DP Stage</a> | <a href='dp-stage.php?debug=1'>DP Stage Debug</a></p>";
?>
