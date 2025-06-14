<?php
// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

// Show current environment
echo "<h2>Environment Information:</h2>";
echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "<br>";
echo "Server IP: " . $_SERVER['SERVER_ADDR'] . "<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "MySQL Client Version: " . mysqli_get_client_info() . "<br>";

// Test different database hosts
$possible_hosts = [
    'mysql.leadmanagement.innersparcrealty.com',
    'localhost',
    '127.0.0.1',
    'mysql.innersparcrealty.com'
];

$db_user = 'leadmanagement';
$db_pass = 'innersparc123';
$db_name = 'real_estate_leads';

foreach ($possible_hosts as $db_host) {
    echo "<hr>";
    echo "<h2>Testing Connection to: " . $db_host . "</h2>";
    echo "User: " . $db_user . "<br>";
    echo "Database: " . $db_name . "<br>";
    
    // Test connection without database selection first
    try {
        $conn = @mysqli_connect($db_host, $db_user, $db_pass);
        if ($conn) {
            echo "<p style='color:green'>✓ Basic connection successful to " . $db_host . "!</p>";
            
            // Get server information
            echo "MySQL Server Version: " . mysqli_get_server_info($conn) . "<br>";
            echo "Connection Character Set: " . mysqli_character_set_name($conn) . "<br>";
            
            // Try to list databases
            $result = @mysqli_query($conn, "SHOW DATABASES");
            if ($result) {
                echo "<p>Available databases:</p>";
                echo "<ul>";
                while ($row = mysqli_fetch_array($result)) {
                    echo "<li>" . $row[0] . "</li>";
                }
                echo "</ul>";
            }
            
            // Try selecting the specific database
            if (@mysqli_select_db($conn, $db_name)) {
                echo "<p style='color:green'>✓ Successfully selected database '" . $db_name . "'</p>";
                
                // Try a simple query
                $result = @mysqli_query($conn, "SHOW TABLES");
                if ($result) {
                    echo "<p style='color:green'>✓ Query execution successful!</p>";
                    echo "<h4>Tables in database:</h4>";
                    echo "<ul>";
                    while ($row = mysqli_fetch_array($result)) {
                        echo "<li>" . $row[0] . "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p style='color:red'>✗ Query failed: " . mysqli_error($conn) . "</p>";
                }
            } else {
                echo "<p style='color:red'>✗ Database selection failed: " . mysqli_error($conn) . "</p>";
            }
            
            mysqli_close($conn);
        } else {
            echo "<p style='color:red'>✗ Connection failed to " . $db_host . ": " . mysqli_connect_error() . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Error connecting to " . $db_host . ": " . $e->getMessage() . "</p>";
    }
}

// Test PDO connection as alternative
echo "<hr>";
echo "<h2>Testing PDO Connection:</h2>";
try {
    $dsn = "mysql:host=mysql.leadmanagement.innersparcrealty.com;dbname=" . $db_name;
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✓ PDO connection successful!</p>";
    
    // Get PDO driver information
    echo "PDO Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "<br>";
    echo "Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "<br>";
    
    $pdo = null; // Close connection
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ PDO Connection failed: " . $e->getMessage() . "</p>";
}
?> 