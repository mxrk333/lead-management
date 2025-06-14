<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Configuration Test</h1>";

// Show server information
echo "<h2>Server Information:</h2>";
echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "<br>";
echo "SERVER_NAME: " . $_SERVER['SERVER_NAME'] . "<br>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Include database config
echo "<h2>Testing Database Configuration:</h2>";
require_once('config/database.php');

// Show defined constants
echo "<h3>Database Constants:</h3>";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'Not defined') . "<br>";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'Not defined') . "<br>";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'Not defined') . "<br>";

// Test connection using the configuration
echo "<h3>Testing Connection with Configuration:</h3>";
try {
    $conn = getDbConnection();
    if ($conn) {
        echo "<p style='color:green'>✓ Connection successful using configuration!</p>";
        
        // Show connection details
        echo "<h4>Connection Details:</h4>";
        echo "Server Info: " . mysqli_get_server_info($conn) . "<br>";
        echo "Character Set: " . mysqli_character_set_name($conn) . "<br>";
        
        // Test a simple query
        $result = mysqli_query($conn, "SELECT DATABASE()");
        if ($result) {
            $row = mysqli_fetch_row($result);
            echo "Connected to database: " . $row[0] . "<br>";
        }
        
        mysqli_close($conn);
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Connection failed: " . $e->getMessage() . "</p>";
}
?> 