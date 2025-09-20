<?php
// Test script to verify current_dp_stage calculation
require_once 'config/database.php';

echo "<h2>Current DP Stage Calculation Test</h2>";

$conn = getDbConnection();
if (!$conn) {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit;
}

echo "<p style='color: green;'>✅ Database connection successful</p>";

// Test with a specific lead (you can change this lead_id)
$test_lead_id = 1; // Change this to an actual lead ID in your database

echo "<h3>Testing Lead ID: $test_lead_id</h3>";

// Get tracker data
$tracker_stmt = $conn->prepare("SELECT * FROM downpayment_tracker WHERE lead_id = ? ORDER BY created_at DESC LIMIT 1");
$tracker_stmt->bind_param("i", $test_lead_id);
$tracker_stmt->execute();
$tracker_result = $tracker_stmt->get_result();
$tracker = $tracker_result->fetch_assoc();
$tracker_stmt->close();

if (!$tracker) {
    echo "<p style='color: orange;'>⚠️ No tracker found for lead ID $test_lead_id</p>";
    echo "<p>Please create a tracker entry first or use a different lead ID.</p>";
    exit;
}

echo "<h4>Current Tracker Data:</h4>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Value</th></tr>";
foreach ($tracker as $field => $value) {
    echo "<tr><td>$field</td><td>$value</td></tr>";
}
echo "</table>";

// Count actual receipts
$receipt_count_stmt = $conn->prepare("SELECT COUNT(*) as total_receipts FROM stage_receipts WHERE lead_id = ? AND stage_type = 'downpayment'");
$receipt_count_stmt->bind_param("i", $test_lead_id);
$receipt_count_stmt->execute();
$receipt_count_result = $receipt_count_stmt->get_result();
$total_receipts = $receipt_count_result->fetch_assoc()['total_receipts'];
$receipt_count_stmt->close();

echo "<h4>Receipt Count Analysis:</h4>";
echo "<p><strong>Total receipts in stage_receipts:</strong> $total_receipts</p>";
echo "<p><strong>Stored current_dp_stage:</strong> " . $tracker['current_dp_stage'] . "</p>";
echo "<p><strong>Total DP stages:</strong> " . $tracker['total_dp_stages'] . "</p>";
echo "<p><strong>Spot DP:</strong> " . ($tracker['spot_dp'] ? 'Yes' : 'No') . "</p>";

// Calculate what the current_dp_stage should be
$calculated_current_dp_stage = 0;
if ($tracker['spot_dp'] == 1) {
    $calculated_current_dp_stage = 1; // Spot DP is always stage 1
} else {
    $calculated_current_dp_stage = $total_receipts > 0 ? max(1, min($total_receipts, intval($tracker['total_dp_stages']))) : 0;
}

echo "<h4>Calculation Results:</h4>";
echo "<p><strong>Calculated current_dp_stage:</strong> $calculated_current_dp_stage</p>";

if ($tracker['current_dp_stage'] == $calculated_current_dp_stage) {
    echo "<p style='color: green;'>✅ Current DP stage is correct!</p>";
} else {
    echo "<p style='color: red;'>❌ Current DP stage is incorrect!</p>";
    echo "<p>Stored: " . $tracker['current_dp_stage'] . " | Calculated: $calculated_current_dp_stage</p>";
    
    // Offer to fix it
    echo "<h4>Fix Current DP Stage</h4>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='lead_id' value='$test_lead_id'>";
    echo "<button type='submit' name='fix_stage' style='background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Fix Current DP Stage</button>";
    echo "</form>";
}

// Handle the fix request
if (isset($_POST['fix_stage']) && isset($_POST['lead_id'])) {
    $lead_id = intval($_POST['lead_id']);
    
    // Update the current_dp_stage
    $update_stmt = $conn->prepare("
        UPDATE downpayment_tracker 
        SET current_dp_stage = ?, progress_rate = ?, updated_at = NOW()
        WHERE lead_id = ? AND id = (
            SELECT id FROM (
                SELECT id FROM downpayment_tracker 
                WHERE lead_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ) as latest_tracker
        )
    ");
    
    $progress_rate = $tracker['total_dp_stages'] > 0 ? ($calculated_current_dp_stage / $tracker['total_dp_stages']) * 100 : 0;
    $update_stmt->bind_param("idi", $calculated_current_dp_stage, $progress_rate, $lead_id, $lead_id);
    
    if ($update_stmt->execute()) {
        echo "<p style='color: green;'>✅ Current DP stage updated successfully!</p>";
        echo "<p>Refreshing page to show updated values...</p>";
        echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
    } else {
        echo "<p style='color: red;'>❌ Failed to update current DP stage: " . $update_stmt->error . "</p>";
    }
    $update_stmt->close();
}

// Show all receipts for this lead
echo "<h4>All Receipts for Lead ID $test_lead_id:</h4>";
$receipts_stmt = $conn->prepare("SELECT * FROM stage_receipts WHERE lead_id = ? AND stage_type = 'downpayment' ORDER BY uploaded_at ASC");
$receipts_stmt->bind_param("i", $test_lead_id);
$receipts_stmt->execute();
$receipts_result = $receipts_stmt->get_result();

if ($receipts_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Filename</th><th>Original Name</th><th>Uploaded At</th><th>File Size</th></tr>";
    while ($receipt = $receipts_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $receipt['id'] . "</td>";
        echo "<td>" . $receipt['filename'] . "</td>";
        echo "<td>" . $receipt['original_name'] . "</td>";
        echo "<td>" . $receipt['uploaded_at'] . "</td>";
        echo "<td>" . ($receipt['file_size'] ? number_format($receipt['file_size']) . ' bytes' : 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No receipts found for this lead</p>";
}

$receipts_stmt->close();
$conn->close();

echo "<hr>";
echo "<p><strong>Instructions:</strong></p>";
echo "<ul>";
echo "<li>Change the \$test_lead_id variable at the top of this file to test with different leads</li>";
echo "<li>Upload some receipts for the lead and refresh this page to see the calculation</li>";
echo "<li>The current_dp_stage should automatically update based on the number of uploaded receipts</li>";
echo "</ul>";
?>
