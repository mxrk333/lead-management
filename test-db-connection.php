<?php
// Test database connection - separate file
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

// Test 1: Check MySQLi connection from database.php
try {
    $conn = getDbConnection();
    echo "✅ MySQLi connection successful<br>";
    echo "Connected to: " . (isDreamHost() ? 'DreamHost' : 'Local') . " environment<br>";
    
    // Test recruitment table
    $result = $conn->query("SELECT COUNT(*) as count FROM recruitment_leads");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✅ recruitment_leads table accessible<br>";
        echo "Records found: " . $row['count'] . "<br>";
        
        // Show sample data
        $result = $conn->query("SELECT * FROM recruitment_leads LIMIT 3");
        if ($result) {
            echo "<h3>Sample Data:</h3>";
            while ($row = $result->fetch_assoc()) {
                echo "- " . $row['full_name'] . " (" . $row['interest_level'] . ")<br>";
            }
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ MySQLi error: " . $e->getMessage() . "<br>";
}

// Test 2: Create PDO connection for recruitment functions
echo "<h3>PDO Connection Test</h3>";
try {
    // Determine environment using the same logic
    if (function_exists('isDreamHost') && isDreamHost()) {
        // DreamHost environment
        $host = 'managementlead.innersparcagents.dreamhosters.com';
        $username = 'managementlead';
        $password = 'innersparc123';
        $database = 'managementlead';
    } else {
        // Local development environment
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'real_estate_leads';
    }
    
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    echo "✅ PDO connection successful<br>";
    
    // Test recruitment table with PDO
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM recruitment_leads");
    $result = $stmt->fetch();
    echo "✅ PDO can access recruitment_leads table<br>";
    echo "Records found: " . $result['count'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ PDO error: " . $e->getMessage() . "<br>";
}
?>
