<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Basic PHP info
echo "<h1>PHP Test Page</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Current Script: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";

// Test database connection
echo "<h2>Testing Database Connection</h2>";
try {
    $conn = new mysqli('localhost', 'leadmanagement', 'innersparc123', 'real_estate_leads');
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    echo "<p style='color: green;'>Database connection successful!</p>";
    $conn->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}

// Test file permissions
echo "<h2>File Permissions</h2>";
$files_to_check = [
    'index.php',
    'config/database.php',
    'includes/functions.php',
    'includes/header.php',
    'includes/sidebar.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        echo "<p>$file: " . substr(sprintf('%o', $perms), -4) . "</p>";
    } else {
        echo "<p style='color: red;'>$file does not exist</p>";
    }
}

// Test session
echo "<h2>Session Test</h2>";
session_start();
$_SESSION['test'] = 'Working';
if (isset($_SESSION['test'])) {
    echo "<p style='color: green;'>Session is working</p>";
} else {
    echo "<p style='color: red;'>Session is not working</p>";
}

echo "PHP is working";
?> 