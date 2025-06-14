<?php
require_once 'config/database.php';

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tour_targets table
$tour_targets_sql = "CREATE TABLE IF NOT EXISTS tour_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tour_type ENUM('Local Tour', 'International Tour') NOT NULL,
    destination VARCHAR(100) NOT NULL,
    manager_target DECIMAL(15,2) NOT NULL,
    supervisor_target DECIMAL(15,2) NOT NULL,
    agent_target DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($tour_targets_sql) === TRUE) {
    echo "Tour targets table created successfully.\n";
    
    // Insert tour targets
    $targets = [
        // Local Tours
        ['Local Tour', 'Boracay', 40000000, 30000000, 20000000],
        ['Local Tour', 'Bohol', 50000000, 40000000, 30000000],
        ['Local Tour', 'Coron', 60000000, 50000000, 40000000],
        
        // International Tours
        ['International Tour', 'Malaysia/Indonesia', 70000000, 65000000, 60000000],
        ['International Tour', 'Singapore/Taiwan', 75000000, 70000000, 65000000],
        ['International Tour', 'Thailand/Vietnam', 80000000, 75000000, 70000000],
        ['International Tour', 'Japan/Korea', 85000000, 80000000, 75000000]
    ];
    
    // Prepare insert statement
    $stmt = $conn->prepare("INSERT INTO tour_targets (tour_type, destination, manager_target, supervisor_target, agent_target) 
                           VALUES (?, ?, ?, ?, ?)");
    
    foreach ($targets as $target) {
        $stmt->bind_param("ssddd", $target[0], $target[1], $target[2], $target[3], $target[4]);
        $stmt->execute();
    }
    
    echo "Tour targets data inserted successfully.\n";
} else {
    echo "Error creating tour targets table: " . $conn->error . "\n";
}

// Modify incentives table to include destination
$alter_incentives_sql = "ALTER TABLE incentives 
    ADD COLUMN destination VARCHAR(100) NOT NULL DEFAULT 'Boracay' AFTER incentive_type";

if ($conn->query($alter_incentives_sql) === TRUE) {
    echo "Incentives table modified successfully.\n";
} else {
    echo "Error modifying incentives table: " . $conn->error . "\n";
}

$conn->close();
?> 