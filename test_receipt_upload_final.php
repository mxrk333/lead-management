<?php
// Final test for receipt upload functionality
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['stage_receipt'])) {
    echo "<h2>Receipt Upload Test Results</h2>";
    
    // Debug: Log all received data
    error_log("=== FINAL UPLOAD TEST ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    echo "<h3>Received Data:</h3>";
    echo "<pre>POST: " . print_r($_POST, true) . "</pre>";
    echo "<pre>FILES: " . print_r($_FILES, true) . "</pre>";
    
    // Test file upload
    $upload_dir = 'uploads/receipts/';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            echo "<p style='color: red;'>❌ Failed to create upload directory: $upload_dir</p>";
            exit;
        }
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        echo "<p style='color: red;'>❌ Upload directory is not writable: $upload_dir</p>";
        echo "<p>Please run: <a href='fix_permissions.php'>Fix Permissions</a></p>";
        exit;
    } else {
        echo "<p style='color: green;'>✅ Upload directory is writable: $upload_dir</p>";
    }
    
    $files = $_FILES['stage_receipt'];
    $uploaded_count = 0;
    
    if (isset($files['tmp_name']) && is_array($files['tmp_name'])) {
        for ($i = 0; $i < count($files['tmp_name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                
                // Validate file type
                if (in_array($file_extension, ['png', 'jpg', 'jpeg'])) {
                    $filename = 'test_' . time() . '_' . $i . '.' . $file_extension;
                    $upload_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_path)) {
                        echo "<p style='color: green;'>✅ File uploaded successfully: $filename</p>";
                        
                        // Test database insertion
                        $conn = getDbConnection();
                        if ($conn) {
                            $stmt = $conn->prepare("INSERT INTO stage_receipts (lead_id, stage_type, filename, original_name, file_path, file_size, mime_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            if ($stmt) {
                                $lead_id = 1; // Test lead ID
                                $stage_type = 'downpayment';
                                $file_size = $files['size'][$i];
                                $mime_type = $files['type'][$i];
                                $created_by = 1; // Test user ID
                                
                                $stmt->bind_param("issssisi", $lead_id, $stage_type, $filename, $files['name'][$i], $upload_path, $file_size, $mime_type, $created_by);
                                if ($stmt->execute()) {
                                    echo "<p style='color: green;'>✅ File saved to database successfully (ID: " . $conn->insert_id . ")</p>";
                                    $uploaded_count++;
                                } else {
                                    echo "<p style='color: red;'>❌ Database insert failed: " . $stmt->error . "</p>";
                                }
                                $stmt->close();
                            } else {
                                echo "<p style='color: red;'>❌ Database prepare failed: " . $conn->error . "</p>";
                            }
                            $conn->close();
                        } else {
                            echo "<p style='color: red;'>❌ Database connection failed</p>";
                        }
                    } else {
                        echo "<p style='color: red;'>❌ File move failed for: " . $files['name'][$i] . "</p>";
                    }
                } else {
                    echo "<p style='color: red;'>❌ Invalid file type for: " . $files['name'][$i] . " (extension: $file_extension)</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Upload error for file $i: " . $files['error'][$i] . "</p>";
            }
        }
    }
    
    echo "<h3>Summary:</h3>";
    echo "<p>Files processed: " . count($files['tmp_name']) . "</p>";
    echo "<p>Files uploaded successfully: $uploaded_count</p>";
    
} else {
    // Show upload form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Final Receipt Upload Test</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .form-group { margin: 15px 0; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input[type="file"] { padding: 5px; border: 1px solid #ccc; border-radius: 4px; }
            button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #005a87; }
            table { border-collapse: collapse; width: 100%; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h2>Final Receipt Upload Test</h2>
        <p>This test will verify that receipt uploads work correctly with the stage_receipts table.</p>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="stage_receipt">Select receipt files (PNG/JPEG only):</label><br>
                <input type="file" id="stage_receipt" name="stage_receipt[]" accept=".png,.jpg,.jpeg" multiple required>
                <small>You can select multiple files at once</small>
            </div>
            <div class="form-group">
                <button type="submit">Upload Test Files</button>
            </div>
        </form>
        
        <h3>Current Files in Database:</h3>
        <?php
        $conn = getDbConnection();
        if ($conn) {
            $result = $conn->query("SELECT * FROM stage_receipts ORDER BY uploaded_at DESC LIMIT 10");
            if ($result && $result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Lead ID</th><th>Stage Type</th><th>Filename</th><th>Original Name</th><th>File Size</th><th>MIME Type</th><th>Uploaded At</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$row['id']}</td>";
                    echo "<td>{$row['lead_id']}</td>";
                    echo "<td>{$row['stage_type']}</td>";
                    echo "<td>{$row['filename']}</td>";
                    echo "<td>{$row['original_name']}</td>";
                    echo "<td>" . number_format($row['file_size']) . " bytes</td>";
                    echo "<td>{$row['mime_type']}</td>";
                    echo "<td>{$row['uploaded_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No files found in database</p>";
            }
            $conn->close();
        } else {
            echo "<p style='color: red;'>Database connection failed</p>";
        }
        ?>
        
        <h3>Instructions:</h3>
        <ol>
            <li>Select one or more PNG/JPEG files</li>
            <li>Click "Upload Test Files"</li>
            <li>Check if files appear in the database table above</li>
            <li>If successful, the main downpayment stage modal should work</li>
        </ol>
    </body>
    </html>
    <?php
}
?>
