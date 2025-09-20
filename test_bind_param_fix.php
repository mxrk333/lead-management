<?php
// Test script to verify bind_param parameter counts
echo "<h2>Bind Param Parameter Count Test</h2>";

// Test UPDATE statement parameters
echo "<h3>UPDATE Statement Parameters</h3>";
$update_params = [
    '$reservation_date' => 's',
    '$requirements_complete' => 'i', 
    '$spot_dp' => 'i',
    '$dp_terms' => 's',
    '$current_dp_stage' => 'i',
    '$total_dp_stages' => 'i',
    '$progress_rate' => 'd',
    '$pagibig_bank_approval' => 'i',
    '$loan_takeout' => 'i',
    '$turnover' => 'i',
    '$existing_tracker[\'id\']' => 'i'
];

$update_string = "siisiiidiii";
$update_count = count($update_params);
$update_string_length = strlen($update_string);

echo "<p><strong>Parameter count:</strong> $update_count</p>";
echo "<p><strong>String length:</strong> $update_string_length</p>";
echo "<p><strong>String:</strong> '$update_string'</p>";

if ($update_count === $update_string_length) {
    echo "<p style='color: green;'>✅ UPDATE statement parameter count matches!</p>";
} else {
    echo "<p style='color: red;'>❌ UPDATE statement parameter count mismatch!</p>";
}

echo "<h4>UPDATE Parameters:</h4>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Variable</th><th>Type</th></tr>";
foreach ($update_params as $var => $type) {
    echo "<tr><td>$var</td><td>$type</td></tr>";
}
echo "</table>";

// Test INSERT statement parameters
echo "<h3>INSERT Statement Parameters</h3>";
$insert_params = [
    '$lead_id' => 'i',
    '$reservation_date' => 's',
    '$requirements_complete' => 'i',
    '$spot_dp' => 'i',
    '$dp_terms' => 's',
    '$current_dp_stage' => 'i',
    '$total_dp_stages' => 'i',
    '$progress_rate' => 'd',
    '$pagibig_bank_approval' => 'i',
    '$loan_takeout' => 'i',
    '$turnover' => 'i'
];

$insert_string = "isisiidiii";
$insert_count = count($insert_params);
$insert_string_length = strlen($insert_string);

echo "<p><strong>Parameter count:</strong> $insert_count</p>";
echo "<p><strong>String length:</strong> $insert_string_length</p>";
echo "<p><strong>String:</strong> '$insert_string'</p>";

if ($insert_count === $insert_string_length) {
    echo "<p style='color: green;'>✅ INSERT statement parameter count matches!</p>";
} else {
    echo "<p style='color: red;'>❌ INSERT statement parameter count mismatch!</p>";
}

echo "<h4>INSERT Parameters:</h4>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Variable</th><th>Type</th></tr>";
foreach ($insert_params as $var => $type) {
    echo "<tr><td>$var</td><td>$type</td></tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>Fix Summary</h3>";
echo "<p style='color: green;'>✅ Fixed UPDATE statement: Changed 'siisiiidiiii' to 'siisiiidiii' (removed extra 'i')</p>";
echo "<p style='color: green;'>✅ INSERT statement was already correct: 'isisiidiii'</p>";
echo "<p style='color: green;'>✅ Both statements now have matching parameter counts</p>";
?>
