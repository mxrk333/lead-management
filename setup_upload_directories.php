<?php
// Setup script for upload directories and database testing
require_once 'config/database.php';

echo "<h2>Stage Receipts Setup</h2>";

// 1. Create upload directories
echo "<h3>1. Creating Upload Directories</h3>";
$upload_dirs = [
    'uploads/',
    'uploads/receipts/',
    'uploads/receipts/downpayment/',
    'uploads/receipts/installment/',
    'uploads/receipts/turnover/'
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "<p style='color: green;'>✅ Created directory: $dir</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create directory: $dir</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Directory already exists: $dir</p>";
    }
}

// 2. Create .htaccess file for upload protection
echo "<h3>2. Setting up Upload Protection</h3>";
$htaccess_content = "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|pdf)$\">\nAllow from all\n</Files>";
if (file_put_contents('uploads/.htaccess', $htaccess_content)) {
    echo "<p style='color: green;'>✅ Created .htaccess file for upload protection</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to create .htaccess file</p>";
}

// 3. Test database connection
echo "<h3>3. Testing Database Connection</h3>";
$conn = getDbConnection();
if (!$conn) {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit;
}
echo "<p style='color: green;'>✅ Database connection successful</p>";

// 4. Check if stage_receipts table exists
echo "<h3>4. Checking stage_receipts Table</h3>";
$test_query = "SHOW TABLES LIKE 'stage_receipts'";
$test_result = $conn->query($test_query);
if ($test_result && $test_result->num_rows > 0) {
    echo "<p style='color: green;'>✅ stage_receipts table exists</p>";
    
    // Show table structure
    $structure_query = "DESCRIBE stage_receipts";
    $structure_result = $conn->query($structure_query);
    if ($structure_result) {
        echo "<h4>Table Structure:</h4>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $structure_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>❌ stage_receipts table does not exist</p>";
    echo "<p>Please run the SQL script: <code>setup_stage_receipts.sql</code></p>";
}

// 5. Test insert capability
echo "<h3>5. Testing Database Insert</h3>";
$test_insert = "INSERT INTO stage_receipts (lead_id, stage_type, filename, original_name, file_path, file_size, mime_type, created_by) VALUES (999, 'test', 'test_file.jpg', 'test_file.jpg', 'uploads/receipts/test_file.jpg', 1024, 'image/jpeg', 1)";
if ($conn->query($test_insert)) {
    echo "<p style='color: green;'>✅ Test insert successful</p>";
    
    // Clean up test data
    $conn->query("DELETE FROM stage_receipts WHERE lead_id = 999");
    echo "<p style='color: blue;'>ℹ️ Test data cleaned up</p>";
} else {
    echo "<p style='color: red;'>❌ Test insert failed: " . $conn->error . "</p>";
}

// 6. Check existing data
echo "<h3>6. Current Data in stage_receipts Table</h3>";
$count_query = "SELECT COUNT(*) as total FROM stage_receipts";
$count_result = $conn->query($count_query);
if ($count_result) {
    $count = $count_result->fetch_assoc()['total'];
    echo "<p>Total records in stage_receipts table: <strong>$count</strong></p>";
    
    if ($count > 0) {
        $recent_query = "SELECT * FROM stage_receipts ORDER BY uploaded_at DESC LIMIT 5";
        $recent_result = $conn->query($recent_query);
        if ($recent_result && $recent_result->num_rows > 0) {
            echo "<h4>Recent Records:</h4>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Lead ID</th><th>Stage Type</th><th>Filename</th><th>Original Name</th><th>Uploaded At</th></tr>";
            while ($row = $recent_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['lead_id']}</td>";
                echo "<td>{$row['stage_type']}</td>";
                echo "<td>{$row['filename']}</td>";
                echo "<td>{$row['original_name']}</td>";
                echo "<td>{$row['uploaded_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
}

// 7. Check upload directory permissions
echo "<h3>7. Checking Upload Directory Permissions</h3>";
$upload_dir = 'uploads/receipts/';
if (is_writable($upload_dir)) {
    echo "<p style='color: green;'>✅ Upload directory is writable: $upload_dir</p>";
} else {
    echo "<p style='color: red;'>❌ Upload directory is not writable: $upload_dir</p>";
    echo "<p>Please run: <code>chmod 777 uploads/receipts/</code></p>";
}

$conn->close();

echo "<h3>Setup Complete!</h3>";
echo "<p>If all checks passed, your stage_receipts system is ready to use.</p>";
echo "<p>You can now upload receipt files through the downpayment stage modal.</p>";
?>
