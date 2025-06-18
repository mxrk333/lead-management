<?php
// Deployment script for project_models table
// This script will create the project_models table in the DreamHost database

echo "<h1>Project Models Table Deployment</h1>";

// DreamHost database configuration
$dreamhost_config = [
    'host' => 'managementlead.innersparcagents.dreamhosters.com',
    'username' => 'managementlead',
    'password' => 'innersparc123',
    'database' => 'managementlead'
];

// Local database configuration (for reference)
$local_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'real_estate_leads'
];

function testConnection($config, $label) {
    echo "<h2>Testing {$label} Connection</h2>";
    
    try {
        $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);
        
        if ($conn->connect_error) {
            echo "<p style='color:red'>✗ Connection failed: " . $conn->connect_error . "</p>";
            return null;
        }
        
        echo "<p style='color:green'>✓ Connection successful to {$config['host']}/{$config['database']}</p>";
        
        // Check if project_models table exists
        $result = $conn->query("SHOW TABLES LIKE 'project_models'");
        if ($result->num_rows > 0) {
            echo "<p style='color:orange'>⚠ project_models table already exists</p>";
            
            // Show table structure
            $structure = $conn->query("DESCRIBE project_models");
            echo "<h3>Current Table Structure:</h3>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $structure->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['Field'] . "</td>";
                echo "<td>" . $row['Type'] . "</td>";
                echo "<td>" . $row['Null'] . "</td>";
                echo "<td>" . $row['Key'] . "</td>";
                echo "<td>" . $row['Default'] . "</td>";
                echo "<td>" . $row['Extra'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color:blue'>ℹ project_models table does not exist</p>";
        }
        
        return $conn;
        
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
        return null;
    }
}

// Test both connections
$local_conn = testConnection($local_config, "Local");
$dreamhost_conn = testConnection($dreamhost_config, "DreamHost");

if (!$dreamhost_conn) {
    echo "<h2 style='color:red'>Cannot proceed - DreamHost connection failed</h2>";
    exit;
}

// Create project_models table in DreamHost
echo "<h2>Creating project_models table in DreamHost</h2>";

$create_table_sql = "
CREATE TABLE IF NOT EXISTS `project_models` (
  `id` int NOT NULL AUTO_INCREMENT,
  `developer_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `base_price` decimal(12,2) DEFAULT '0.00',
  `floor_area` decimal(8,2) DEFAULT NULL,
  `lot_area` decimal(8,2) DEFAULT NULL,
  `bedrooms` int DEFAULT NULL,
  `bathrooms` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `developer_id` (`developer_id`),
  CONSTRAINT `project_models_ibfk_1` FOREIGN KEY (`developer_id`) REFERENCES `developers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

if ($dreamhost_conn->query($create_table_sql)) {
    echo "<p style='color:green'>✓ project_models table created successfully</p>";
} else {
    echo "<p style='color:red'>✗ Error creating table: " . $dreamhost_conn->error . "</p>";
}

// Insert sample data
echo "<h2>Inserting sample project models data</h2>";

$insert_data_sql = "
INSERT INTO `project_models` (`developer_id`, `name`, `description`, `base_price`) VALUES
(1, 'Alice', 'Premium residential unit', 2900000.00),
(1, 'Alexandra', 'Luxury family home', 8500000.00),
(1, 'Briana', 'Modern townhouse', 2700000.00),
(2, 'Antipolo Heights Model A', 'Scenic hillside property', 3200000.00),
(3, 'Kennedy', 'Family-oriented townhouse', 2700000.00),
(3, 'Lincoln', 'Spacious family home', 3500000.00),
(3, 'Nyxon', 'Modern residential unit', 3200000.00),
(4, 'Bellefort Estate Model A', 'Luxury gated community home', 4500000.00),
(6, 'Sapphire', 'Affordable housing solution', 10000000.00),
(6, 'Pearl', 'Family townhouse', 10000000.00),
(7, 'Hana', 'Japanese-inspired modern living', 3100000.00),
(8, 'Paris', 'Contemporary urban development', 8000000.00),
(8, 'Sydney', 'Modern city living', 12000000.00),
(8, 'Tokyo', 'Urban lifestyle home', 14000000.00),
(8, 'Florida', 'Spacious family residence', 16000000.00),
(9, 'Kathleen Place Model A', 'Mid-rise condominium', 5900000.00),
(10, 'Amora', 'Sustainable eco-friendly housing', 2300000.00),
(11, 'Way', 'Trusted quality development', 100000.00);
";

if ($dreamhost_conn->query($insert_data_sql)) {
    echo "<p style='color:green'>✓ Sample data inserted successfully</p>";
} else {
    echo "<p style='color:red'>✗ Error inserting data: " . $dreamhost_conn->error . "</p>";
}

// Verify the table was created correctly
echo "<h2>Verifying table creation</h2>";

$result = $dreamhost_conn->query("SELECT COUNT(*) as count FROM project_models");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p style='color:green'>✓ project_models table contains {$row['count']} records</p>";
} else {
    echo "<p style='color:red'>✗ Error verifying table: " . $dreamhost_conn->error . "</p>";
}

// Show sample data
echo "<h2>Sample Data in project_models table:</h2>";
$result = $dreamhost_conn->query("
    SELECT pm.*, d.name as developer_name 
    FROM project_models pm 
    JOIN developers d ON pm.developer_id = d.id 
    ORDER BY d.name, pm.name 
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Developer</th><th>Model Name</th><th>Description</th><th>Base Price</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['developer_name'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . $row['description'] . "</td>";
        echo "<td>₱" . number_format($row['base_price'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>✗ No data found or error querying table</p>";
}

$dreamhost_conn->close();
if ($local_conn) $local_conn->close();

echo "<h2 style='color:green'>Deployment completed!</h2>";
echo "<p>You can now delete this file for security reasons.</p>";
?> 