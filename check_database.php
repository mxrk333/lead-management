<?php
// Quick database check
require_once 'config/database.php';

echo "<h2>Database Check</h2>";

$conn = getDbConnection();
if (!$conn) {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit;
}
echo "<p style='color: green;'>✅ Database connection successful</p>";

// Check if table exists
$test_query = "SHOW TABLES LIKE 'stage_receipts'";
$test_result = $conn->query($test_query);
if ($test_result && $test_result->num_rows > 0) {
    echo "<p style='color: green;'>✅ stage_receipts table exists</p>";
    
    // Show table structure
    $structure_query = "DESCRIBE stage_receipts";
    $structure_result = $conn->query($structure_query);
    if ($structure_result) {
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $structure_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test insert
    echo "<h3>Testing Database Insert:</h3>";
    $test_insert = "INSERT INTO stage_receipts (lead_id, stage_type, filename, original_name, file_path, file_size, mime_type, created_by) VALUES (999, 'test', 'test_file.jpg', 'test_file.jpg', 'uploads/receipts/test_file.jpg', 1024, 'image/jpeg', 1)";
    if ($conn->query($test_insert)) {
        echo "<p style='color: green;'>✅ Test insert successful (ID: " . $conn->insert_id . ")</p>";
        
        // Show the inserted record
        $select_query = "SELECT * FROM stage_receipts WHERE lead_id = 999";
        $select_result = $conn->query($select_query);
        if ($select_result && $select_result->num_rows > 0) {
            $row = $select_result->fetch_assoc();
            echo "<p>Inserted record: ID={$row['id']}, Lead ID={$row['lead_id']}, Stage Type={$row['stage_type']}, Filename={$row['filename']}</p>";
        }
        
        // Clean up
        $conn->query("DELETE FROM stage_receipts WHERE lead_id = 999");
        echo "<p style='color: blue;'>ℹ️ Test data cleaned up</p>";
    } else {
        echo "<p style='color: red;'>❌ Test insert failed: " . $conn->error . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ stage_receipts table does not exist</p>";
}

$conn->close();
?>
