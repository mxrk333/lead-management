<?php
require_once 'config/database.php';

// Check if database exists, if not create it
function createDatabase() {
    // Connect without selecting a database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if ($conn->query($sql) === TRUE) {
        echo "Database created successfully or already exists.<br>";
    } else {
        echo "Error creating database: " . $conn->error . "<br>";
    }
    
    $conn->close();
}

// Main installation function
function installDatabase() {
    echo "<h1>Lead Management System - Database Installation</h1>";
    
    // Step 1: Create database if it doesn't exist
    echo "<h2>Step 1: Creating Database</h2>";
    createDatabase();
    
    // Step 2: Import SQL file
    echo "<h2>Step 2: Importing Database Structure and Sample Data</h2>";
    $sqlFile = __DIR__ . '/sql/database.sql';
    
    if (file_exists($sqlFile)) {
        // Connect to the database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Read SQL file
        $sql = file_get_contents($sqlFile);
        
        // Split SQL file into individual statements
        $statements = explode(';', $sql);
        
        // Execute each statement
        $success = true;
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                if ($conn->query($statement) === FALSE) {
                    echo "Error executing statement: " . $conn->error . "<br>";
                    echo "Statement: " . $statement . "<br><br>";
                    $success = false;
                }
            }
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
            echo "Incentives table created successfully.<br>";
        } else {
            echo "Error creating incentives table: " . $conn->error . "<br>";
            $success = false;
        }
        
        if ($success) {
            echo "Database structure and sample data imported successfully.<br>";
        } else {
            echo "There were errors during the import process.<br>";
        }
        
        $conn->close();
    } else {
        echo "SQL file not found: $sqlFile<br>";
    }
    
    echo "<h2>Installation Complete</h2>";
    echo "<p>You can now <a href='index.php'>login to the system</a>.</p>";
    echo "<p>Default admin credentials:</p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
}

// Run the installation
installDatabase();
?>
