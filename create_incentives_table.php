<?php
require_once 'config/database.php';

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create incentives table
$incentives_sql = "CREATE TABLE IF NOT EXISTS incentives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    position VARCHAR(100) NOT NULL,
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    incentive_type ENUM('Local Tour', 'International Tour') NOT NULL,
    target_sales DECIMAL(15,2) NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user (user_id)
)";

if ($conn->query($incentives_sql) === TRUE) {
    echo "Incentives table created successfully.\n";
} else {
    echo "Error creating incentives table: " . $conn->error . "\n";
}

$conn->close();
?> 