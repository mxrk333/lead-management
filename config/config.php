<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define production host
$productionHost = 'leads.dreamhosters.com';

// Environment detection
if ($_SERVER['HTTP_HOST'] === $productionHost || $_SERVER['HTTP_HOST'] === 'www.' . $productionHost) {
    // Production environment
    define('ENVIRONMENT', 'production');
    define('DEBUG_MODE', false);
    define('BASE_URL', 'https://' . $productionHost);
    
    // Production paths
    define('UPLOAD_DIR', '/home/dh_k9az8v/htdocs/uploads');
    define('LOGS_DIR', '/home/dh_k9az8v/htdocs/logs');
    
    // Production error handling
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_DIR . '/error.log');
} else {
    // Development environment
    define('ENVIRONMENT', 'development');
    define('DEBUG_MODE', true);
    define('BASE_URL', 'http://localhost/lead-management');
    
    // Development paths
    define('UPLOAD_DIR', __DIR__ . '/../uploads');
    define('LOGS_DIR', __DIR__ . '/../logs');
    
    // Development error handling
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_DIR . '/error.log');
}

// Shared paths
define('MEMO_IMAGES_DIR', UPLOAD_DIR . '/memo_images');

// Create required directories
foreach ([UPLOAD_DIR, LOGS_DIR, MEMO_IMAGES_DIR] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Time zone
date_default_timezone_set('Asia/Manila');

// Maximum file upload size (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed file types for memo images
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif'
]);

// Session configuration
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400);
session_start();

// Application settings
define('APP_NAME', 'Inners SPARC Realty Corporation');
define('APP_VERSION', '1.0.0');

// Pagination settings
define('ITEMS_PER_PAGE', 20);

// Lead settings
define('LEAD_TEMPERATURES', ['Hot', 'Warm', 'Cold']);
define('LEAD_STATUSES', [
    'New',
    'Contacted', 
    'Qualified',
    'Presentation',
    'Negotiation',
    'Closed-Won',
    'Closed-Lost',
    'Follow-up'
]);

// Security settings
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_TIMEOUT', 3600);

// Helper functions
function getBaseUrl() {
    return BASE_URL;
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isProduction() {
    return ENVIRONMENT === 'production';
}

function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    error_log("[$timestamp] $message$contextStr");
}

function getUploadMaxSize() {
    return min(
        convertToBytes(ini_get('upload_max_filesize')),
        convertToBytes(ini_get('post_max_size')),
        MAX_UPLOAD_SIZE
    );
}

function convertToBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// Database configuration
if (isProduction()) {
    define('DB_HOST', 'managementlead.innersparcagents.dreamhosters.com');
    define('DB_NAME', 'managementlead');
    define('DB_USER', 'managementlead');
    define('DB_PASS', 'innersparc123');
    define('DB_PORT', 3306);
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'managementlead');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_PORT', 3306);
}

// Enhanced database connection function
function getDbConnection() {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        $conn = mysqli_init();
        if (!$conn) {
            throw new Exception("mysqli_init failed");
        }
        
        // Set connection options
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 30);
        $conn->options(MYSQLI_INIT_COMMAND, 'SET NAMES utf8mb4');
        
        // Attempt connection
        if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        logError("Database connection successful", [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'user' => DB_USER
        ]);
        
        return $conn;
        
    } catch (Exception $e) {
        logError("Database connection failed", [
            'error' => $e->getMessage(),
            'host' => DB_HOST,
            'database' => DB_NAME
        ]);
        
        if (DEBUG_MODE) {
            throw $e;
        } else {
            die("Database connection failed. Please try again later.");
        }
    }
}


// Database configuration
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

<?php
// Database configuration
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

// Function to sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to check if user is a superuser
function isSuperUser($username) {
    $superusers = [
        'markpatigayon.intern',
        'gabriellibacao.founder', 
        'romeocorberta.itdept'
    ];
    return in_array($username, $superusers);
}

// Enhanced function to check if current user can edit a lead (including superuser check)
function canEditLeadEnhanced($lead, $current_user_id, $current_username) {
    // Superusers can edit any lead
    if (isSuperUser($current_username)) {
        return true;
    }
    
    // Regular users can only edit their own leads
    return ($lead['user_id'] == $current_user_id);
}

// Enhanced function to check if current user can view full contact details
function canViewFullContactEnhanced($lead, $current_user_id, $current_username) {
    // Superusers can view all contact details
    if (isSuperUser($current_username)) {
        return true;
    }
    
    // Regular users can only view their own lead's full contact details
    return ($lead['user_id'] == $current_user_id);
}

// Set timezone
date_default_timezone_set('Asia/Manila');
?>

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
        ],

    ],

    'migrations' => 'migrations',

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

    ],

];

// Function to check if user is a superuser
function isSuperUser($username) {
    $superusers = [
        'markpatigayon.intern',
        'gabriellibacao.founder', 
        'romeocorberta.itdept'
    ];
    return in_array($username, $superusers);
}

// Enhanced function to check if current user can edit a lead (including superuser check)
function canEditLeadEnhanced($lead, $current_user_id, $current_username) {
    // Superusers can edit any lead
    if (isSuperUser($current_username)) {
        return true;
    }
    
    // Regular users can only edit their own leads
    return ($lead['user_id'] == $current_user_id);
}

// Enhanced function to check if current user can view full contact details
function canViewFullContactEnhanced($lead, $current_user_id, $current_username) {
    // Superusers can view all contact details
    if (isSuperUser($current_username)) {
        return true;
    }
    
    // Regular users can only view their own lead's full contact details
    return ($lead['user_id'] == $current_user_id);
}


// ...existing code for other database functions...