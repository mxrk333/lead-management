<?php
// Fix upload directory permissions
echo "<h2>Fixing Upload Directory Permissions</h2>";

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
        echo "<p style='color: blue;'>ℹ️ Directory exists: $dir</p>";
    }
    
    // Set permissions
    if (chmod($dir, 0777)) {
        echo "<p style='color: green;'>✅ Set permissions 777 for: $dir</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to set permissions for: $dir</p>";
    }
}

// Test write permission
$test_file = 'uploads/receipts/test_write_' . time() . '.txt';
if (file_put_contents($test_file, 'Test write permission')) {
    echo "<p style='color: green;'>✅ Write permission test successful</p>";
    unlink($test_file); // Clean up
} else {
    echo "<p style='color: red;'>❌ Write permission test failed</p>";
}

echo "<h3>Current Directory Status:</h3>";
foreach ($upload_dirs as $dir) {
    if (file_exists($dir)) {
        $perms = fileperms($dir);
        $readable = is_readable($dir) ? 'Yes' : 'No';
        $writable = is_writable($dir) ? 'Yes' : 'No';
        echo "<p><strong>$dir</strong> - Permissions: " . substr(sprintf('%o', $perms), -4) . " - Readable: $readable - Writable: $writable</p>";
    }
}

echo "<p><a href='test_receipt_upload_final.php'>Test Upload Again</a></p>";
?>
