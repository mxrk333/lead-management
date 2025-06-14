<?php
require_once 'config/database.php';

// Establish database connection
$conn = getDbConnection();

// SQL to create memo_files table
$sql = "CREATE TABLE IF NOT EXISTS `memo_files` (
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

// Execute the query
try {
    if ($conn->query($sql)) {
        echo "memo_files table created successfully!";
    } else {
        echo "Error creating table: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 