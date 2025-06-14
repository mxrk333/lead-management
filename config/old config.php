<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
if ($_SERVER['HTTP_HOST'] === 'leadsparc.innersparcagents.dreamhosters.com') {
    // Production environment (DreamHost)
    define('DB_HOST', 'leadsparc.innersparcagents.dreamhosters.com'); // DreamHost MySQL hostname format
    define('DB_USER', 'leadsparc');
    define('DB_PASS', 'leadsparc123');
    define('DB_NAME', 'leadsparc');
    define('DB_PORT', 3306);
    define('DB_SOCKET', null);
} else {
    // Local development environment
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'real_estate_leads');
    define('DB_PORT', 3306);
    define('DB_SOCKET', null);
}

// Define your production host (your DreamHost domain)
$productionHost = 'leadsparc.innersparcagents.dreamhosters.com'; // Updated domain

// Error reporting based on environment
if ($_SERVER['HTTP_HOST'] === $productionHost || $_SERVER['HTTP_HOST'] === 'www.' . $productionHost) {
    // Production environment - hide errors from users
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', '/home/dh_vggtu9/logs/php_errors.log');
} else {
    // Development environment - show errors for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Base URL configuration
if ($_SERVER['HTTP_HOST'] === $productionHost) {
    define('BASE_URL', 'https://' . $productionHost); // Use HTTPS for production
} else {
    define('BASE_URL', 'http://localhost/lead-management');
}

// File upload directories
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MEMO_IMAGES_DIR', UPLOAD_DIR . '/memo_images');
define('DOCUMENTS_DIR', UPLOAD_DIR . '/documents');

// Create upload directories if they don't exist
$directories = [UPLOAD_DIR, MEMO_IMAGES_DIR, DOCUMENTS_DIR];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/.htaccess', "Options -Indexes\nDeny from all");
    }
}

// Time zone
date_default_timezone_set('Asia/Manila');

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
]);

define('ALLOWED_DOCUMENT_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain'
]);

// Application settings
define('APP_NAME', 'Inners SPARC Realty Corporation');
define('APP_VERSION', '1.0.0');

// Pagination settings
define('ITEMS_PER_PAGE', 20);

// Lead temperature options
define('LEAD_TEMPERATURES', ['Hot', 'Warm', 'Cold']);

// Lead status options
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

// User roles
define('USER_ROLES', ['admin', 'manager', 'agent']);

// Email configuration (if you use PHPMailer)
define('MAIL_HOST', 'smtp.dreamhost.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', ''); // Set your email
define('MAIL_PASSWORD', ''); // Set your email password
define('MAIL_FROM_EMAIL', ''); // Set sender email
define('MAIL_FROM_NAME', APP_NAME);

// Security settings
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Helper function to get base URL
function getBaseUrl() {
    return BASE_URL;
}

// Helper function to get upload URL
function getUploadUrl($file = '') {
    return BASE_URL . '/uploads/' . ltrim($file, '/');
}

// Helper function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Helper function for file upload validation
function validateFileUpload($file, $allowedTypes = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    if ($allowedTypes && !in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    return true;
}

// Helper function to generate unique filename
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

// Debug function (only works in development)
function debug($data) {
    if (ini_get('display_errors')) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }
}
