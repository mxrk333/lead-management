<?php
require_once __DIR__ . '/database.php';

echo "Checking notifications table in database...\n";

$conn = getDbConnection();
if (!$conn) {
    echo "Error: Database connection failed.\n";
    exit(1);
}

$tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "Table 'notifications' already exists.\n";
} else {
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) NOT NULL,
            related_id INT NULL,
            related_type VARCHAR(50) NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    if ($conn->query($createTableQuery)) {
        echo "Successfully created 'notifications' table!\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
}

$conn->close();
echo "Notification table check finished.\n";
?>