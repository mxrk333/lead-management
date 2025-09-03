<?php
require_once 'config/database.php';

try {
    $conn = getDbConnection();
    
    // Check if column already exists
    $result = $conn->query("SHOW COLUMNS FROM leads LIKE 'lead_classification'");
    if ($result->num_rows > 0) {
        echo "lead_classification column already exists!\n";
        $conn->close();
        exit;
    }
    
    // Add the column
    $sql = "ALTER TABLE leads ADD COLUMN lead_classification VARCHAR(50) DEFAULT NULL AFTER source";
    
    if ($conn->query($sql)) {
        echo "lead_classification column added successfully!\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
