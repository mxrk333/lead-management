<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Set up error logging
$logFile = $logDir . '/php_errors.log';
ini_set('log_errors', 1);
ini_set('error_log', $logFile);

// Function to log debug information
function debugLog($message, $data = null) {
    global $logFile;
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logMessage .= " - Data: " . print_r($data, true);
    }
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
}

// Database credentials
define('DB_HOST', 'leadsparc.innersparcagents.dreamhosters.com');  // DreamHost MySQL hostname
define('DB_NAME', 'leadsparc');
define('DB_USER', 'leadsparc');
define('DB_PASS', 'leadsparc123');
define('DB_PORT', 3306);

/**
 * Establish a MySQL database connection using mysqli.
 *
 * @return mysqli
 */
function getDbConnection() {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        // Log server information
        debugLog("Server Information", [
            'HTTP_HOST' => $_SERVER['HTTP_HOST'],
            'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'],
            'PHP_VERSION' => phpversion(),
            'MYSQLI_CLIENT_INFO' => mysqli_get_client_info(),
            'MYSQLI_CLIENT_VERSION' => mysqli_get_client_version()
        ]);

        // Log connection attempt
        debugLog("Attempting DB connection", [
            'host' => DB_HOST,
            'user' => DB_USER,
            'database' => DB_NAME,
            'port' => DB_PORT
        ]);

        // Create connection using TCP/IP
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        if (!$conn->set_charset('utf8mb4')) {
            throw new Exception("Error setting charset: " . $conn->error);
        }

        debugLog("Database connection successful");
        return $conn;

    } catch (Exception $e) {
        $error_message = sprintf(
            "DB connection error: %s\nHost: %s\nUser: %s\nDB: %s\nPHP Version: %s\nServer: %s\nMySQL Client Info: %s",
            $e->getMessage(),
            DB_HOST,
            DB_USER,
            DB_NAME,
            phpversion(),
            $_SERVER['SERVER_SOFTWARE'],
            mysqli_get_client_info()
        );
        debugLog("Connection Error", $error_message);

        // Return JSON error for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            die(json_encode(['error' => $e->getMessage()]));
        }

        die("Database Error: " . htmlspecialchars($e->getMessage()));
    }
}
?>
