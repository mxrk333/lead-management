<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'real_estate_leads';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL to create memo_files table
$sql = "DROP TABLE IF EXISTS memo_files;
CREATE TABLE `memo_files` (
    `file_id` int(11) NOT NULL AUTO_INCREMENT,
    `memo_id` int(11) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `file_type` enum('image','pdf') NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`file_id`),
    KEY `memo_id` (`memo_id`),
    CONSTRAINT `memo_files_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`memo_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Execute multi query
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    echo "Table created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close(); 