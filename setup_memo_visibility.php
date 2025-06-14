<?php
// Include database connection
require_once 'includes/config.php';
$conn = getDbConnection();

// Basic HTML styling
echo "<!DOCTYPE html>
<html>
<head>
    <title>Memo Visibility Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        .container { max-width: 800px; margin: 0 auto; }
        .btn { display: inline-block; padding: 8px 16px; background-color: #4f46e5; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Setting up memo visibility database structure</h1>

    try {
        // Step 1: Add visible_to_all column if it doesn't exist
        $conn->query("ALTER TABLE memos ADD COLUMN visible_to_all TINYINT(1) NOT NULL DEFAULT 0");
        echo "<p class='success'>✓ Added visible_to_all column to memos table</p>";
    } catch (Exception $e) {
        // Column might already exist
        echo "<p>Note: visible_to_all column may already exist</p>";
    }
    
    try {
        // Step 2: Create the memo_team_visibility table
        $conn->query("CREATE TABLE IF NOT EXISTS memo_team_visibility (
            id INT(11) NOT NULL AUTO_INCREMENT,
            memo_id INT(11) NOT NULL,
            team_id INT(11) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY memo_team (memo_id, team_id)
        )");
        echo "<p class='success'>✓ Created or verified memo_team_visibility table</p>";
        
        // Add indexes without foreign keys (to avoid potential issues)
        $conn->query("ALTER TABLE memo_team_visibility ADD INDEX (memo_id)");
        $conn->query("ALTER TABLE memo_team_visibility ADD INDEX (team_id)");
        echo "<p class='success'>✓ Added indexes to improve query performance</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>Error creating table: " . $e->getMessage() . "</p>";
    }
    
    try {
        // Step 3: Set existing memos to be visible to their teams
        $conn->query("UPDATE memos SET visible_to_all = 0");
        echo "<p class='success'>✓ Updated existing memos visibility settings</p>";
        
        // Step 4: For each memo, add an entry in memo_team_visibility for its team
        $result = $conn->query("SELECT id, team_id FROM memos WHERE team_id > 0");
        $added_count = 0;
        
        while ($memo = $result->fetch_assoc()) {
            // Insert entry, ignore if it already exists
            $conn->query("INSERT IGNORE INTO memo_team_visibility (memo_id, team_id) VALUES ({$memo['id']}, {$memo['team_id']})");
            $added_count++;
        }
        
        echo "<p class='success'>✓ Processed {$added_count} existing memos</p>";
    } catch (Exception $e) {
        echo "<p class='error'>Error setting up memo visibility: " . $e->getMessage() . "</p>";
    }
    
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #e6f7ff; border-radius: 5px; border: 1px solid #b3d7ff;'>";
    echo "<h2>Setup Complete</h2>";
    echo "<p>Database changes have been applied. You can now create memos with team visibility filtering.</p>";
    echo "<p><a href='memo.php' class='btn'>Return to Memos</a></p>";
    echo "</div>";
    
    echo "</div>\n</body>\n</html>";
?>
