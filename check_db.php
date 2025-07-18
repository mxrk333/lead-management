<?php
require_once 'config/database.php';

// Get database connection
$conn = getDbConnection();

// Check memo_team_visibility table structure
$result = $conn->query("SHOW CREATE TABLE memo_team_visibility");
$table_structure = $result->fetch_assoc();
echo "Table Structure:\n";
print_r($table_structure);

// Check existing records
$result = $conn->query("SELECT * FROM memo_team_visibility");
echo "\nCurrent Records:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

// Check for any missing constraints
$result = $conn->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                       FROM information_schema.KEY_COLUMN_USAGE 
                       WHERE TABLE_NAME = 'memo_team_visibility' 
                       AND REFERENCED_TABLE_NAME IS NOT NULL");
echo "\nForeign Key Constraints:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?> 