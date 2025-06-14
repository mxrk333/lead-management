<?php
require_once 'config/database.php';

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add position column to users table if it doesn't exist
$check_column = "SHOW COLUMNS FROM users LIKE 'position'";
$result = $conn->query($check_column);

if ($result->num_rows == 0) {
    $alter_sql = "ALTER TABLE users ADD COLUMN position VARCHAR(100) DEFAULT 'Real Estate Agent'";
    if ($conn->query($alter_sql) === TRUE) {
        echo "Position column added successfully to users table.\n";
    } else {
        echo "Error adding position column: " . $conn->error . "\n";
    }
} else {
    echo "Position column already exists in users table.\n";
}

$conn->close();
?> 