<?php
// Simple test for database insertion
require_once 'config/database.php';

echo "<h2>Simple Database Insert Test</h2>";

$conn = getDbConnection();
if (!$conn) {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit;
}
echo "<p style='color: green;'>✅ Database connection successful</p>";

// Test insert with all fields
$test_data = [
    'lead_id' => 1,
    'stage_type' => 'downpayment',
    'filename' => 'test_' . time() . '.jpg',
    'original_name' => 'test_file.jpg',
    'file_path' => 'uploads/receipts/test_' . time() . '.jpg',
    'file_size' => 1024,
    'mime_type' => 'image/jpeg',
    'created_by' => 1
];

$stmt = $conn->prepare("INSERT INTO stage_receipts (lead_id, stage_type, filename, original_name, file_path, file_size, mime_type, created_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
if ($stmt) {
    $stmt->bind_param("issssisi", 
        $test_data['lead_id'], 
        $test_data['stage_type'], 
        $test_data['filename'], 
        $test_data['original_name'], 
        $test_data['file_path'], 
        $test_data['file_size'], 
        $test_data['mime_type'], 
        $test_data['created_by']
    );
    
    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Test insert successful! Insert ID: $insert_id</p>";
        
        // Verify the insert
        $verify_stmt = $conn->prepare("SELECT * FROM stage_receipts WHERE id = ?");
        $verify_stmt->bind_param("i", $insert_id);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<h3>Inserted Record:</h3>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            foreach ($row as $field => $value) {
                echo "<tr><td>$field</td><td>$value</td></tr>";
            }
            echo "</table>";
        }
        
        // Clean up
        $cleanup_stmt = $conn->prepare("DELETE FROM stage_receipts WHERE id = ?");
        $cleanup_stmt->bind_param("i", $insert_id);
        $cleanup_stmt->execute();
        echo "<p style='color: blue;'>ℹ️ Test record cleaned up</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Test insert failed: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: red;'>❌ Failed to prepare statement: " . $conn->error . "</p>";
}

$conn->close();
?>
