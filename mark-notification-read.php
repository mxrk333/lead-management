<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests to this file
error_log("mark-notifications-read.php called with method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Session data: " . print_r($_SESSION, true));

// Try to include config file from multiple possible locations
$config_paths = [
    'config.php',
    './config.php',
    '../config.php',
    'includes/config.php',
    'inc/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        error_log("Config loaded from: " . $path);
        break;
    }
}

// If no config file found, create database connection inline
if (!$config_loaded) {
    error_log("No config.php found, using inline database connection");
    
    function getDbConnection() {
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'real_estate_leads';
        
        // Create connection
        $conn = new mysqli($host, $username, $password, $database);
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset to utf8mb4
        $conn->set_charset("utf8mb4");
        
        return $conn;
    }
    
    // Function to get user by ID
    function getUserById($user_id) {
        $conn = getDbConnection();
        $user = null;
        
        $query = "SELECT * FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $user = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        return $user;
    }
}

// Set proper headers for AJAX response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in - no user_id in session");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'session' => $_SESSION]);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("Processing request for user_id: $user_id");

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if action is mark_all_read
if (!isset($_POST['action']) || $_POST['action'] !== 'mark_all_read') {
    error_log("Invalid action: " . ($_POST['action'] ?? 'not set'));
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action', 'received_action' => $_POST['action'] ?? 'not set']);
    exit;
}

try {
    $conn = getDbConnection();
    error_log("Database connection established");
    
    // Get current timestamp
    $current_time = date('Y-m-d H:i:s');
    error_log("Setting last_notification_read to: $current_time");
    
    // Update the user's last notification read timestamp to current time
    $update_query = "UPDATE users SET last_notification_read = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $current_time, $user_id);
    $success = $stmt->execute();
    
    if (!$success) {
        throw new Exception('Failed to execute update: ' . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    error_log("Update executed. Affected rows: $affected_rows");
    
    $stmt->close();
    
    // Verify the update worked
    $verify_query = "SELECT last_notification_read FROM users WHERE id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_row = $verify_result->fetch_assoc();
    $verify_stmt->close();
    
    error_log("Verification - last_notification_read is now: " . $verify_row['last_notification_read']);
    
    // Store in session as backup
    $_SESSION['last_notification_read'] = $current_time;
    error_log("Stored in session as backup");
    
    $response = [
        'success' => true,
        'message' => 'Notifications marked as read',
        'timestamp' => $current_time,
        'affected_rows' => $affected_rows,
        'verified_timestamp' => $verify_row['last_notification_read'],
        'user_id' => $user_id,
        'config_loaded' => $config_loaded
    ];
    
    error_log("Sending success response: " . json_encode($response));
    echo json_encode($response);
    
    $conn->close();
    
} catch (Exception $e) {
    // Log the error
    error_log("Error marking notifications as read for user $user_id: " . $e->getMessage());
    
    // Store in session as fallback
    $_SESSION['last_notification_read'] = date('Y-m-d H:i:s');
    
    // Return error response
    http_response_code(500);
    $error_response = [
        'success' => false,
        'error' => 'Database error occurred',
        'message' => $e->getMessage(),
        'fallback' => true,
        'config_loaded' => $config_loaded
    ];
    
    error_log("Sending error response: " . json_encode($error_response));
    echo json_encode($error_response);
}
?>
