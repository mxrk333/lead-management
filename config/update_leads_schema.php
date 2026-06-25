<?php
require_once __DIR__ . '/database.php';

echo "Starting database schema update for leads table...\n";

$conn = getDbConnection();
if (!$conn) {
    echo "Error: Database connection failed.\n";
    exit(1);
}

// Columns to check and add
$columns = [
    'city' => "VARCHAR(255) NULL AFTER address",
    'job_title' => "VARCHAR(255) NULL AFTER city",
    'relationship_status' => "VARCHAR(100) NULL AFTER job_title",
    'ai_summary' => "TEXT NULL AFTER remarks",
    'lead_quality' => "ENUM('Low', 'Medium', 'High') NULL AFTER ai_summary",
    'recommended_action' => "TEXT NULL AFTER lead_quality",
    'google_sheet_row_id' => "VARCHAR(100) NULL AFTER recommended_action"
];

foreach ($columns as $columnName => $definition) {
    // Check if column exists
    $result = $conn->query("SHOW COLUMNS FROM leads LIKE '$columnName'");
    if ($result && $result->num_rows > 0) {
        echo "Column '$columnName' already exists in 'leads' table.\n";
    } else {
        // Add column
        $query = "ALTER TABLE leads ADD COLUMN $columnName $definition";
        if ($conn->query($query)) {
            echo "Successfully added column '$columnName'.\n";
        } else {
            echo "Error adding column '$columnName': " . $conn->error . "\n";
        }
    }
}

// Add an index on google_sheet_row_id to prevent duplicates efficiently
$indexCheck = $conn->query("SHOW INDEX FROM leads WHERE Key_name = 'idx_google_sheet_row_id'");
if ($indexCheck && $indexCheck->num_rows == 0) {
    $indexQuery = "ALTER TABLE leads ADD INDEX idx_google_sheet_row_id (google_sheet_row_id)";
    if ($conn->query($indexQuery)) {
        echo "Successfully added index on 'google_sheet_row_id'.\n";
    } else {
        echo "Warning: Could not create index on 'google_sheet_row_id': " . $conn->error . "\n";
    }
} else {
    echo "Index on 'google_sheet_row_id' already exists.\n";
}

$conn->close();
echo "Database schema update finished.\n";
?>