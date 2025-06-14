<?php
// Enable error reporting during development only
$is_development = false;

// Check if we're in a development environment
if (isset($_SERVER['SERVER_NAME']) && 
    ($_SERVER['SERVER_NAME'] == 'localhost' || 
     strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false ||
     strpos($_SERVER['SERVER_NAME'], '.test') !== false)) {
    $is_development = true;
}

// Set error reporting based on environment
if ($is_development) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Ensure errors are logged regardless of environment
ini_set('log_errors', 1);
$error_log_path = dirname(__DIR__) . '/logs/db_errors.log';

// Create logs directory if it doesn't exist
if (!file_exists(dirname($error_log_path))) {
    mkdir(dirname($error_log_path), 0755, true);
}

ini_set('error_log', $error_log_path);

// Database configuration
// DreamHost detection using multiple methods
function isDreamHost() {
    // Method 1: Check HTTP_HOST
    if (isset($_SERVER['HTTP_HOST'])) {
        $possible_dreamhost_domains = ['dreamhosters.com', 'dreamhost.com', 'innersparcagents.com'];
        foreach ($possible_dreamhost_domains as $domain) {
            if (strpos($_SERVER['HTTP_HOST'], $domain) !== false) {
                return true;
            }
        }
    }

    // Method 2: Check SERVER_SOFTWARE
    if (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'DreamHost') !== false) {
        return true;
    }

    // Method 3: Check file path for DreamHost structure
    if (isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['DOCUMENT_ROOT'], '/home/dh_') !== false) {
        return true;
    }

    return false;
}

// Get database connection
function getDbConnection() {
    try {
        // Determine environment
        $is_dreamhost = isDreamHost();
        
        // Log connection attempt
        error_log("Attempting database connection. DreamHost environment: " . ($is_dreamhost ? 'true' : 'false'));
        
        if ($is_dreamhost) {
            // DreamHost environment
            $host = 'managementlead.innersparcagents.dreamhosters.com';
            $username = 'managementlead';
            $password = 'innersparc123';
            $database = 'managementlead';
        } else {
            // Local development environment
            $host = 'localhost';
            $username = 'root';
            $password = '';
            $database = 'real_estate_leads';
        }

        // Create connection with error handling
        $conn = new mysqli($host, $username, $password, $database);
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset to utf8mb4 for proper Unicode support
        if (!$conn->set_charset("utf8mb4")) {
            error_log("Error setting charset to utf8mb4: " . $conn->error);
        }
        
        // Log successful connection
        error_log("Database connection successful to {$host}/{$database}");
        
        return $conn;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        
        // In production, don't expose error details
        if (!isDreamHost()) {
            throw $e; // Re-throw in development
        } else {
            // Generic error for production
            die("Database connection failed. Please try again later or contact support.");
        }
    }
}

// Function to check and reconnect if connection is lost
function ensureConnection($conn) {
    if (!$conn->ping()) {
        error_log("Database connection lost. Attempting to reconnect...");
        $conn->close();
        return getDbConnection();
    }
    return $conn;
}

// Function to safely execute queries with error handling
function safeQuery($conn, $query, $params = [], $types = "") {
    try {
        $conn = ensureConnection($conn);
        
        if (empty($params)) {
            // Simple query without parameters
            $result = $conn->query($query);
            if ($result === false) {
                throw new Exception("Query failed: " . $conn->error);
            }
            return $result;
        } else {
            // Prepared statement with parameters
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            if (!empty($types) && !empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        }
    } catch (Exception $e) {
        error_log("Query error: " . $e->getMessage() . " - Query: " . $query);
        
        if (!isDreamHost()) {
            throw $e; // Re-throw in development
        }
        return false;
    }
}

// Initialize database tables if they don't exist
function initializeDatabase() {
    $conn = null;
    try {
        $conn = getDbConnection();
        
        // Check if projects table exists
        $result = $conn->query("SHOW TABLES LIKE 'projects'");
        if ($result->num_rows == 0) {
            // Create projects table
            $conn->query("CREATE TABLE IF NOT EXISTS projects (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                house_model VARCHAR(100),
                status ENUM('rfo', 'preselling', 'ogc') NOT NULL DEFAULT 'preselling',
                developer VARCHAR(100),
                price_min DECIMAL(15,2),
                price_max DECIMAL(15,2),
                commission DECIMAL(5,2) NOT NULL DEFAULT 5.00,
                priority ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
                city_id INT(11),
                province_id INT(11),
                exact_location TEXT,
                image1 VARCHAR(255),
                image2 VARCHAR(255),
                image3 VARCHAR(255),
                image4 VARCHAR(255),
                drive_link VARCHAR(255),
                messenger_link VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL,
                FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE SET NULL
            )");
            
            error_log("Created projects table");
        }
        
        // Check if provinces table exists
        $result = $conn->query("SHOW TABLES LIKE 'provinces'");
        if ($result->num_rows == 0) {
            // Create provinces table
            $conn->query("CREATE TABLE IF NOT EXISTS provinces (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            error_log("Created provinces table");
        }
        
        // Check if cities table exists
        $result = $conn->query("SHOW TABLES LIKE 'cities'");
        if ($result->num_rows == 0) {
            // Create cities table
            $conn->query("CREATE TABLE IF NOT EXISTS cities (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                province_id INT(11) NOT NULL,
                name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE CASCADE
            )");
            
            error_log("Created cities table");
        }
        
        $conn->close();
    } catch (Exception $e) {
        error_log("Database initialization error: " . $e->getMessage());
        if ($conn) {
            $conn->close();
        }
    }
}

// Call the initialize function
initializeDatabase();
