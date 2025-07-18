<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

try {
    $conn = getDbConnection();
    
    echo "<h2>Database Connection Test</h2>";
    echo "<p>Connected to database successfully.</p>";
    
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "<p>✓ Users table exists</p>";
        
        // Check table structure
        $structure = $conn->query("DESCRIBE users");
        echo "<h3>Users table structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Try the original query
        echo "<h3>Testing original query:</h3>";
        $users_query = "SELECT id, name, username, role, team_id FROM users WHERE is_active = 1 ORDER BY name ASC";
        $users_result = $conn->query($users_query);
        
        if (!$users_result) {
            echo "<p style='color: red;'>✗ Query failed: " . $conn->error . "</p>";
            
            // Try without is_active filter
            echo "<h3>Testing query without is_active filter:</h3>";
            $users_query_fallback = "SELECT id, name, username, role, team_id FROM users ORDER BY name ASC";
            $users_result = $conn->query($users_query_fallback);
            
            if (!$users_result) {
                echo "<p style='color: red;'>✗ Fallback query also failed: " . $conn->error . "</p>";
            } else {
                echo "<p style='color: green;'>✓ Fallback query succeeded</p>";
                $users = [];
                while ($user_data = $users_result->fetch_assoc()) {
                    $users[$user_data['id']] = $user_data;
                }
                echo "<p>Found " . count($users) . " users</p>";
                echo "<pre>" . print_r($users, true) . "</pre>";
            }
        } else {
            echo "<p style='color: green;'>✓ Original query succeeded</p>";
            $users = [];
            while ($user_data = $users_result->fetch_assoc()) {
                $users[$user_data['id']] = $user_data;
            }
            echo "<p>Found " . count($users) . " active users</p>";
            echo "<pre>" . print_r($users, true) . "</pre>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Users table does not exist</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 