<?php
require_once 'config/database.php';

echo "<h2>Database Debug Information</h2>";

try {
    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        echo "<p style='color: red;'>❌ Database connection failed</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Get current database name
    $result = $conn->query("SELECT DATABASE() as db_name");
    $dbInfo = $result->fetch_assoc();
    echo "<p><strong>Connected to database:</strong> " . $dbInfo['db_name'] . "</p>";
    
    // Check if projects table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'projects'");
    if ($tableCheck->num_rows > 0) {
        echo "<p style='color: green;'>✅ Projects table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Projects table does not exist</p>";
        exit;
    }
    
    // Get all columns in projects table
    echo "<h3>Current Projects Table Structure:</h3>";
    $columns = $conn->query("DESCRIBE projects");
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $columnNames = [];
    while ($row = $columns->fetch_assoc()) {
        $columnNames[] = $row['Field'];
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check specifically for new pricing columns
    echo "<h3>New Pricing Columns Check:</h3>";
    $newColumns = [
        'total_contract_price',
        'reservation_fee',
        'bank_amortization',
        'required_salary',
        'monthly_downpayment_3mos',
        'monthly_downpayment_6mos',
        'monthly_downpayment_12mos',
        'monthly_downpayment_18mos'
    ];
    
    foreach ($newColumns as $column) {
        if (in_array($column, $columnNames)) {
            echo "<p style='color: green;'>✅ $column exists</p>";
        } else {
            echo "<p style='color: red;'>❌ $column missing</p>";
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
