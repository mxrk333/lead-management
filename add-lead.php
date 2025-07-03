<?php
// Enhanced add-lead.php with specific focus on project models fetching
session_start();

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php_errors.log');

// Debug logging function
function debugLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[ADD-LEAD DEBUG] $timestamp - $message");
}

// Enhanced error handling
function handleError($message, $redirect_page = 'leads.php') {
    debugLog("ERROR: $message");
    $_SESSION['error_message'] = $message;
    header("Location: $redirect_page");
    exit();
}

debugLog("Add lead script started");

// Check if required files exist
$required_files = [
    'config/database.php',
    'includes/functions.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        handleError("Required file missing: $file");
    }
}

try {
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    debugLog("Required files loaded successfully");
} catch (Exception $e) {
    handleError("Failed to load required files: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    debugLog("User not logged in or invalid session");
    header("Location: login.php?error=session_expired");
    exit();
}

$user_id = intval($_SESSION['user_id']);
debugLog("Processing request for user ID: $user_id");

// Get user information with error handling
try {
    if (!function_exists('getUserById')) {
        throw new Exception("getUserById function not found");
    }
    
    $user = getUserById($user_id);
    if (!$user || !is_array($user)) {
        throw new Exception("User not found or invalid user data for ID: $user_id");
    }
    debugLog("User found: " . ($user['name'] ?? 'Unknown'));
} catch (Exception $e) {
    handleError("Failed to get user information: " . $e->getMessage());
}

// Initialize variables
$developers = [];
$projectModels = [];
$leadSources = [];
$success = '';
$error = '';

// Enhanced function to get developers with fallback
function getDevelopersEnhanced() {
    debugLog("Getting developers");
    
    try {
        // Try the original function first
        if (function_exists('getDevelopers')) {
            $developers = getDevelopers();
            if (!empty($developers)) {
                debugLog("Got " . count($developers) . " developers from getDevelopers()");
                return $developers;
            }
        }
        
        // Fallback: Get directly from database
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $developers = [];
        
        // Try different possible table names and structures
        $possible_queries = [
            "SELECT DISTINCT name FROM developers WHERE status = 'active' ORDER BY name",
            "SELECT DISTINCT name FROM developers ORDER BY name",
            "SELECT DISTINCT developer_name as name FROM project_models ORDER BY developer_name",
            "SELECT DISTINCT developer as name FROM leads ORDER BY developer",
            "SELECT DISTINCT project as name FROM leads ORDER BY project"
        ];
        
        foreach ($possible_queries as $query) {
            try {
                debugLog("Trying query: $query");
                $result = $conn->query($query);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $developers[] = ['name' => $row['name']];
                    }
                    debugLog("Successfully got " . count($developers) . " developers");
                    break;
                }
            } catch (Exception $e) {
                debugLog("Query failed: " . $e->getMessage());
                continue;
            }
        }
        
        $conn->close();
        
        // If still no developers, provide defaults
        if (empty($developers)) {
            debugLog("No developers found, using defaults");
            $defaultDevelopers = ['Lancaster', 'Antipolo Heights', 'Pleasant Fields', 'Vista Verde', 'Golden Hills'];
            foreach ($defaultDevelopers as $dev) {
                $developers[] = ['name' => $dev];
            }
        }
        
        return $developers;
        
    } catch (Exception $e) {
        debugLog("Error getting developers: " . $e->getMessage());
        
        // Return default developers
        $defaultDevelopers = ['Lancaster', 'Antipolo Heights', 'Pleasant Fields', 'Vista Verde', 'Golden Hills'];
        $developers = [];
        foreach ($defaultDevelopers as $dev) {
            $developers[] = ['name' => $dev];
        }
        return $developers;
    }
}

// Enhanced function to get project models with fallback
function getProjectModelsEnhanced() {
    debugLog("Getting project models");
    
    try {
        // Try the original function first
        if (function_exists('getProjectModels')) {
            $models = getProjectModels();
            if (!empty($models)) {
                debugLog("Got " . count($models) . " project models from getProjectModels()");
                return $models;
            }
        }
        
        // Fallback: Get directly from database
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $models = [];
        
        // Try different possible table names and structures
        $possible_queries = [
            "SELECT developer_name, name FROM project_models WHERE status = 'active' ORDER BY developer_name, name",
            "SELECT developer_name, name FROM project_models ORDER BY developer_name, name",
            "SELECT developer_name, model_name as name FROM project_models ORDER BY developer_name, model_name",
            "SELECT project as developer_name, model as name FROM project_models ORDER BY project, model"
        ];
        
        foreach ($possible_queries as $query) {
            try {
                debugLog("Trying query: $query");
                $result = $conn->query($query);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $models[] = [
                            'developer_name' => $row['developer_name'],
                            'name' => $row['name']
                        ];
                    }
                    debugLog("Successfully got " . count($models) . " project models");
                    break;
                }
            } catch (Exception $e) {
                debugLog("Query failed: " . $e->getMessage());
                continue;
            }
        }
        
        $conn->close();
        
        // If still no models, provide defaults
        if (empty($models)) {
            debugLog("No project models found, using defaults");
            $defaultModels = [
                ['developer_name' => 'Lancaster', 'name' => 'Kennedy'],
                ['developer_name' => 'Lancaster', 'name' => 'Alexandra'],
                ['developer_name' => 'Lancaster', 'name' => 'Victoria'],
                ['developer_name' => 'Lancaster', 'name' => 'Elizabeth'],
                ['developer_name' => 'Antipolo Heights', 'name' => 'Sierra'],
                ['developer_name' => 'Antipolo Heights', 'name' => 'Montana'],
                ['developer_name' => 'Antipolo Heights', 'name' => 'Alpine'],
                ['developer_name' => 'Antipolo Heights', 'name' => 'Summit'],
                ['developer_name' => 'Pleasant Fields', 'name' => 'Meadow'],
                ['developer_name' => 'Pleasant Fields', 'name' => 'Garden'],
                ['developer_name' => 'Pleasant Fields', 'name' => 'Park'],
                ['developer_name' => 'Pleasant Fields', 'name' => 'Grove'],
                ['developer_name' => 'Vista Verde', 'name' => 'Emerald'],
                ['developer_name' => 'Vista Verde', 'name' => 'Sapphire'],
                ['developer_name' => 'Golden Hills', 'name' => 'Premium'],
                ['developer_name' => 'Golden Hills', 'name' => 'Deluxe']
            ];
            $models = $defaultModels;
        }
        
        return $models;
        
    } catch (Exception $e) {
        debugLog("Error getting project models: " . $e->getMessage());
        
        // Return default models
        $defaultModels = [
            ['developer_name' => 'Lancaster', 'name' => 'Kennedy'],
            ['developer_name' => 'Lancaster', 'name' => 'Alexandra'],
            ['developer_name' => 'Lancaster', 'name' => 'Victoria'],
            ['developer_name' => 'Lancaster', 'name' => 'Elizabeth'],
            ['developer_name' => 'Antipolo Heights', 'name' => 'Sierra'],
            ['developer_name' => 'Antipolo Heights', 'name' => 'Montana'],
            ['developer_name' => 'Antipolo Heights', 'name' => 'Alpine'],
            ['developer_name' => 'Antipolo Heights', 'name' => 'Summit'],
            ['developer_name' => 'Pleasant Fields', 'name' => 'Meadow'],
            ['developer_name' => 'Pleasant Fields', 'name' => 'Garden'],
            ['developer_name' => 'Pleasant Fields', 'name' => 'Park'],
            ['developer_name' => 'Pleasant Fields', 'name' => 'Grove']
        ];
        return $defaultModels;
    }
}

// Get data for dropdowns with enhanced error handling
try {
    debugLog("Loading dropdown data");
    
    $developers = getDevelopersEnhanced();
    debugLog("Loaded " . count($developers) . " developers");
    
    $projectModels = getProjectModelsEnhanced();
    debugLog("Loaded " . count($projectModels) . " project models");
    
    $leadSources = getLeadSources();
    debugLog("Loaded " . count($leadSources) . " lead sources");
    
} catch (Exception $e) {
    debugLog("Error loading dropdown data: " . $e->getMessage());
    $error = "Failed to load form data. Please refresh the page.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    debugLog("Processing form submission");
    
    try {
        // Collect and sanitize form data
        $clientName = isset($_POST['client_name']) ? trim($_POST['client_name']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $facebook = isset($_POST['facebook']) ? trim($_POST['facebook']) : '';
        $linkedin = isset($_POST['linkedin']) ? trim($_POST['linkedin']) : '';
        $temperature = isset($_POST['temperature']) ? trim($_POST['temperature']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $developer = isset($_POST['developer']) ? trim($_POST['developer']) : '';
        $projectModel = isset($_POST['project_model']) ? trim($_POST['project_model']) : '';
        $priceRaw = isset($_POST['price']) ? trim($_POST['price']) : '';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
        $source = isset($_POST['source']) ? trim($_POST['source']) : '';
        
        debugLog("Form data collected - Client: '$clientName', Phone: '$phone', Email: '$email'");
        debugLog("Project data - Developer: '$developer', Model: '$projectModel'");
        
        // Clean and convert price
        $price = str_replace([',', ' '], '', $priceRaw);
        $price = floatval($price);
        
        debugLog("Price converted from '$priceRaw' to $price");
        
        // Enhanced validation
        $validation_errors = [];
        
        if (empty($clientName)) {
            $validation_errors[] = "Client name is required";
        } elseif (strlen($clientName) > 100) {
            $validation_errors[] = "Client name is too long (max 100 characters)";
        }
        
        if (empty($phone)) {
            $validation_errors[] = "Phone number is required";
        } elseif (!preg_match('/^\d{11}$/', $phone)) {
            $validation_errors[] = "Phone number must be 11 digits";
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
             $validation_errors[] = "Invalid email address format";
        }
        
        if (empty($temperature)) {
            $validation_errors[] = "Temperature is required";
        } elseif (!in_array($temperature, ['Hot', 'Warm', 'Cold'])) {
            $validation_errors[] = "Invalid temperature value";
        }
        
        if (empty($status)) {
            $validation_errors[] = "Status is required";
        }
        
        if (empty($developer)) {
            $validation_errors[] = "Project is required";
        }
        
        if (empty($projectModel)) {
            $validation_errors[] = "Project model is required";
        }
        
        if ($price <= 0) {
            $validation_errors[] = "Valid price is required";
        } elseif ($price > 999999999.99) {
            $validation_errors[] = "Price is too large";
        }
        
        if (empty($source)) {
            $validation_errors[] = "Lead source is required";
        }
        
        if (!empty($validation_errors)) {
            $error = implode(", ", $validation_errors);
            debugLog("Validation failed: $error");
        } else {
            debugLog("Validation passed, attempting to add lead");
            
            // Check if addLead function exists
            if (!function_exists('addLead')) {
                throw new Exception("addLead function not found");
            }
            
            // Add lead to database
            $result = addLead(
                $user_id, $clientName, $phone, $email, $facebook, $linkedin, 
                $temperature, $status, $source, $developer, $projectModel, $price, $remarks
            );
            
            if ($result) {
                debugLog("Lead added successfully with result: " . (is_numeric($result) ? "ID $result" : "true"));
                $success = "Lead added successfully";
                
                // Clear form data on success
                $_POST = [];
                
                // Redirect after short delay
                header("refresh:2;url=leads.php");
            } else {
                throw new Exception("addLead function returned false/null");
            }
        }
        
    } catch (Exception $e) {
        $error = "Failed to add lead: " . $e->getMessage();
        debugLog("CRITICAL ERROR: $error");
    }
}

// Enhanced getLeadSources function with better error handling
function getLeadSources() {
    debugLog("Getting lead sources");
    
    try {
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $sources = [];
        
        // Get ENUM values directly from the column
        $stmt = $conn->prepare("SHOW COLUMNS FROM leads WHERE Field = 'source'");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // Parse ENUM values from the type definition
        if ($row && preg_match("/^enum$$'(.*)'$$$/", $row['Type'], $matches)) {
            $values = explode("','", $matches[1]);
            foreach ($values as $value) {
                $sources[] = [
                    'id' => $value,
                    'name' => $value
                ];
            }
            debugLog("Found " . count($sources) . " lead sources from database");
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        debugLog("Error getting lead sources from database: " . $e->getMessage());
        $sources = [];
    }
    
    // If no sources found from database, provide default values
    if (empty($sources)) {
        debugLog("Using default lead sources");
        $defaultSources = [
            'Facebook Groups', 'KKK', 'Facebook Ads', 'TikTok ads', 'Google Ads', 
            'Facebook live', 'Referral', 'Teleprospecting', 'Video Message', 
            'Organic Posting', 'Email Marketing', 'Follow up', 'Manning', 
            'Walk in', 'Flyering', 'Chat messaging', 'Property Listing', 
            'Landing Page', 'Networking Events', 'Organic Sharing', 
            'Youtube Marketing', 'LinkedIn', 'Open House'
        ];
        
        foreach ($defaultSources as $source) {
            $sources[] = [
                'id' => $source,
                'name' => $source
            ];
        }
    }
    
    return $sources;
}

// Check for session messages
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

debugLog("Page rendering started");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Lead - Inners SPARC Realty Corporation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base styles */
        :root {
            --container-padding: 25px;
        }

        @media (max-width: 768px) {
            :root {
                --container-padding: 15px;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .add-lead-page {
                padding: var(--container-padding);
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .btn-back {
                width: 100%;
                justify-content: center;
            }
            
            .lead-form {
                border-radius: 0.75rem;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .form-row {
                margin: 0 -10px 1.25rem;
            }
            
            .form-group {
                padding: 0 10px;
                margin-bottom: 15px;
                min-width: 100%;
            }
        }

        @media (max-width: 576px) {
            .add-lead-page {
                padding: var(--container-padding);
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .page-header h2::after {
                width: 2rem;
            }
            
            .form-section {
                padding: 15px;
            }
            
            .form-section h3 {
                font-size: 1.1rem;
                margin-bottom: 1.25rem;
            }
            
            .form-group label {
                font-size: 0.8rem;
                margin-bottom: 0.375rem;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 0.625rem 0.875rem;
                font-size: 0.8rem;
                border-radius: 0.375rem;
            }
            
            .form-group select {
                padding-right: 2rem;
                background-size: 0.875rem;
            }
            
            .form-actions {
                padding: 15px;
                flex-direction: column-reverse;
                gap: 10px;
            }
            
            .btn-save,
            .btn-cancel {
                width: 100%;
                padding: 0.625rem;
                font-size: 0.8rem;
            }
            
            .required-note {
                font-size: 0.7rem;
                margin-bottom: 0.75rem;
            }
            
            .optional-field {
                font-size: 0.7rem;
            }
            
            .success-message,
            .error-message {
                padding: 0.75rem;
                font-size: 0.8rem;
                margin-bottom: 1rem;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) {
            .btn-save:hover,
            .btn-cancel:hover,
            .btn-back:hover {
                transform: none;
                box-shadow: none;
            }
            
            .form-group input:focus,
            .form-group select:focus,
            .form-group textarea:focus {
                box-shadow: none;
            }
        }
        
        /* Base styles */
        body {
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background-color: #f9fafb;
        }
        
        /* Add Lead page styles */
        .add-lead-page {
            padding: 2rem;
            background-color: #f9fafb;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            letter-spacing: -0.025em;
            position: relative;
            display: inline-block;
        }
        
        .page-header h2::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 0;
            width: 2.5rem;
            height: 0.25rem;
            background: linear-gradient(to right, #4f46e5, #8b5cf6);
            border-radius: 0.25rem;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 0.625rem 1rem;
            background-color: white;
            color: #4f46e5;
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-back:hover {
            background-color: rgba(79, 70, 229, 0.05);
            border-color: rgba(79, 70, 229, 0.3);
        }
        
        .btn-back i {
            margin-right: 0.5rem;
        }
        
        /* Form styles */
        .lead-form {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(229, 231, 235, 0.5);
            overflow: hidden;
        }
        
        .form-section {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .form-section:last-of-type {
            border-bottom: none;
        }
        
        .form-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin-top: 0;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .form-section h3::before {
            content: '';
            display: inline-block;
            width: 0.25rem;
            height: 1.25rem;
            background: linear-gradient(to bottom, #4f46e5, #8b5cf6);
            margin-right: 0.75rem;
            border-radius: 0.125rem;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap; 
            margin: 0 -0.75rem 1.5rem;
        }
        
        .form-row:last-child {
            margin-bottom: 0;
        }
        
        .form-group {
            flex: 1;
            min-width: 250px;
            padding: 0 0.75rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-group {
                flex: 0 0 100%;
            }
        }
        
        .form-group.full-width {
            flex: 0 0 100%;
        }
        
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        /* Required field indicator */
        .required-field label::after {
            content: ' *';
            color: #ef4444;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #1f2937;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #4f46e5;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Form actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            padding: 1.5rem 2rem;
            background-color: #f9fafb;
            border-top: 1px solid #f3f4f6;
        }
        
        .btn-save,
        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .btn-save {
            background-color: #4f46e5;
            color: white;
            border: none;
            margin-left: 0.75rem;
        }
        
        .btn-save:hover {
            background-color: #4338ca;
        }
        
        .btn-cancel {
            background-color: white;
            color: #6b7280;
            border: 1px solid #d1d5db;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            background-color: #f3f4f6;
        }
        
        /* Success and error messages */
        .success-message,
        .error-message {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .success-message {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        /* Required field indicator */
        .required-note {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .required-note span {
            color: #ef4444;
        }
        
        /* Source select styling */
        .source-select {
            max-height: 15rem;
            overflow-y: auto;
        }
        
        /* Optional field styling */
        .optional-field {
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: normal;
            margin-left: 0.25rem;
        }

        /* Loading state */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #4f46e5;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Debug info styling */
        .debug-info {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.75rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (file_exists('includes/sidebar.php')): ?>
            <?php include 'includes/sidebar.php'; ?>
        <?php endif; ?>
        
        <div class="main-content">
            <?php if (file_exists('includes/header.php')): ?>
                <?php include 'includes/header.php'; ?>
            <?php endif; ?>
            
            <div class="add-lead-page">
                <div class="page-header">
                    <h2>Add New Lead</h2>
                    <a href="leads.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Leads</a>
                </div>
                
                <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Debug Information (remove in production) -->
                <?php if (isset($_GET['debug'])): ?>
                <div class="debug-info">
                    <strong>Debug Information:</strong><br>
                    Developers loaded: <?php echo count($developers); ?><br>
                    Project models loaded: <?php echo count($projectModels); ?><br>
                    Lead sources loaded: <?php echo count($leadSources); ?><br>
                    <br>
                    <strong>Developers:</strong><br>
                    <?php foreach (array_slice($developers, 0, 5) as $dev): ?>
                        - <?php echo htmlspecialchars($dev['name']); ?><br>
                    <?php endforeach; ?>
                    <br>
                    <strong>Project Models (first 10):</strong><br>
                    <?php foreach (array_slice($projectModels, 0, 10) as $model): ?>
                        - <?php echo htmlspecialchars($model['developer_name']); ?>: <?php echo htmlspecialchars($model['name']); ?><br>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="required-note">Fields marked with <span>*</span> are required</div>
                
                <form method="POST" action="add-lead.php" class="lead-form" id="leadForm">
                    <div class="form-section">
                        <h3>Client Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="client_name">Client Name</label>
                                <input type="text" id="client_name" name="client_name" 
                                       value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>"
                                       placeholder="Enter client's full name" maxlength="100" required>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       placeholder="e.g. 09123456789" maxlength="11" pattern="\d{11}" required>
                            </div>      
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Addres <span class="optional-field">(Optional)</span></label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="client@example.com" maxlength="100">
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="source">Lead Source</label>
                                <select id="source" name="source" required class="source-select">
                                    <option value="">Select Lead Source</option>
                                    <?php foreach ($leadSources as $source): ?>
                                    <option value="<?php echo htmlspecialchars($source['name']); ?>"
                                            <?php echo (isset($_POST['source']) && $_POST['source'] === $source['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($source['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="facebook">Facebook Profile <span class="optional-field">(Optional)</span></label>
                                <input type="url" id="facebook" name="facebook" 
                                       value="<?php echo htmlspecialchars($_POST['facebook'] ?? ''); ?>"
                                       placeholder="Facebook profile URL" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label for="linkedin">LinkedIn Profile <span class="optional-field">(Optional)</span></label>
                                <input type="url" id="linkedin" name="linkedin" 
                                       value="<?php echo htmlspecialchars($_POST['linkedin'] ?? ''); ?>"
                                       placeholder="LinkedIn profile URL" maxlength="255">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Lead Details</h3>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="temperature">Temperature</label>
                                <select id="temperature" name="temperature" required>
                                    <option value="">Select Temperature</option>
                                    <option value="Hot" <?php echo (isset($_POST['temperature']) && $_POST['temperature'] === 'Hot') ? 'selected' : ''; ?>>Hot</option>
                                    <option value="Warm" <?php echo (isset($_POST['temperature']) && $_POST['temperature'] === 'Warm') ? 'selected' : ''; ?>>Warm</option>
                                    <option value="Cold" <?php echo (isset($_POST['temperature']) && $_POST['temperature'] === 'Cold') ? 'selected' : ''; ?>>Cold</option>
                                </select>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <?php 
                                    $statuses = [
                                        'Inquiry', 'Presentation Stage', 'Negotiation', 'Lost', 'Site Tour',
                                        'Closed Deal', 'Requirement Stage', 'Downpayment Stage', 'Housing Loan Application',
                                        'Loan Approval', 'Loan Takeout', 'House Inspection', 'House Turn Over'
                                    ];
                                    foreach ($statuses as $status_option): ?>
                                    <option value="<?php echo htmlspecialchars($status_option); ?>"
                                            <?php echo (isset($_POST['status']) && $_POST['status'] === $status_option) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($status_option); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="developer">Project</label>
                                <select id="developer" name="developer" required onchange="loadProjectModels(this.value)">
                                    <option value="">Select Project</option>
                                    <?php foreach ($developers as $dev): ?>
                                    <option value="<?php echo htmlspecialchars($dev['name']); ?>"
                                            <?php echo (isset($_POST['developer']) && $_POST['developer'] === $dev['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dev['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="project_model">House Model</label>
                                <select id="project_model" name="project_model" required>
                                    <option value="">Select House Model</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="price">Total Selling Price (PHP)</label>
                                <input type="text" id="price" name="price" 
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                       placeholder="e.g. 1,000,000.00" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="remarks">Remarks <span class="optional-field">(Optional)</span></label>
                                <textarea id="remarks" name="remarks" rows="4" maxlength="1000"
                                          placeholder="Add any additional notes or comments about this lead"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="leads.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="fas fa-save"></i> Save Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Enhanced JavaScript with comprehensive project model handling
        console.log('Add Lead form script loaded');
        
        // Project models data from PHP - with enhanced error handling
        let projectModelsData = {};
        try {
            projectModelsData = <?php 
                $modelsArray = [];
                foreach ($projectModels as $model) {
                    if (!isset($modelsArray[$model['developer_name']])) {
                        $modelsArray[$model['developer_name']] = [];
                    }
                    $modelsArray[$model['developer_name']][] = $model['name'];
                }
                echo json_encode($modelsArray, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            ?>;
            console.log('Project models data loaded:', projectModelsData);
        } catch (error) {
            console.error('Error loading project models data:', error);
            // Fallback data
            projectModelsData = {
                'Lancaster': ['Kennedy', 'Alexandra', 'Victoria', 'Elizabeth'],
                'Antipolo Heights': ['Sierra', 'Montana', 'Alpine', 'Summit'],
                'Pleasant Fields': ['Meadow', 'Garden', 'Park', 'Grove'],
                'Vista Verde': ['Emerald', 'Sapphire'],
                'Golden Hills': ['Premium', 'Deluxe']
            };
        }
        
        // Function to load project models based on selected developer
        function loadProjectModels(developer) {
            console.log('Loading project models for developer:', developer);
            
            const projectModelSelect = document.getElementById('project_model');
            if (!projectModelSelect) {
                console.error('Project model select element not found');
                return;
            }
            
            // Clear existing options
            projectModelSelect.innerHTML = '<option value="">Select Project Model</option>';
            
            if (developer) {
                try {
                    // Get models for the selected developer
                    const models = projectModelsData[developer] || [];
                    
                    console.log('Models for', developer, ':', models);
                    
                    if (models.length === 0) {
                        console.warn('No models found for developer:', developer);
                        // Add a message option
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No models available';
                        option.disabled = true;
                        projectModelSelect.appendChild(option);
                    } else {
                        // Add model options
                        models.forEach(model => {
                            const option = document.createElement('option');
                            option.value = model;
                            option.textContent = model;
                            projectModelSelect.appendChild(option);
                        });
                    }
                    
                    // Restore selected value if form was submitted with errors
                    const selectedModel = '<?php echo htmlspecialchars($_POST['project_model'] ?? ''); ?>';
                    if (selectedModel && models.includes(selectedModel)) {
                        projectModelSelect.value = selectedModel;
                        console.log('Restored selected model:', selectedModel);
                    }
                    
                } catch (error) {
                    console.error('Error loading project models:', error);
                    
                    // Add error option
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'Error loading models';
                    option.disabled = true;
                    projectModelSelect.appendChild(option);
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing form');
            
            try {
                // Initialize project models if developer is already selected
                const developerSelect = document.getElementById('developer');
                if (developerSelect && developerSelect.value) {
                    console.log('Initializing with pre-selected developer:', developerSelect.value);
                    loadProjectModels(developerSelect.value);
                }
                
                // Price formatting
                const priceInput = document.getElementById('price');
                if (priceInput) {
                    priceInput.addEventListener('input', function(e) {
                        try {
                            // Get the current value and remove all non-digits and decimal points
                            let value = this.value.replace(/[^\d.]/g, '');
                            
                            // Ensure only one decimal point
                            const parts = value.split('.');
                            if (parts.length > 2) {
                                value = parts[0] + '.' + parts.slice(1).join('');
                            }
                            
                            // Limit decimal places to 2
                            if (parts[1] && parts[1].length > 2) {
                                value = parts[0] + '.' + parts[1].substring(0, 2);
                            }
                            
                            // Add commas for thousands
                            if (value) {
                                const numParts = value.split('.');
                                numParts[0] = numParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                this.value = numParts.join('.');
                            }
                            
                        } catch (error) {
                            console.error('Error formatting price:', error);
                        }
                    });
                }
                
                // Phone number validation
                const phoneInput = document.getElementById('phone');
                if (phoneInput) {
                    phoneInput.addEventListener('input', function(e) {
                        // Remove all non-digits
                        this.value = this.value.replace(/\D/g, '');
                        
                        // Limit to 11 digits
                        if (this.value.length > 11) {
                            this.value = this.value.substring(0, 11);
                        }
                    });
                }
                
                // Form submission handling
                const form = document.getElementById('leadForm');
                const saveBtn = document.getElementById('saveBtn');
                
                if (form && saveBtn) {
                    form.addEventListener('submit', function(e) {
                        console.log('Form submission started');
                        
                        try {
                            // Show loading state
                            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                            saveBtn.disabled = true;
                            
                            // Clean price value for submission
                            if (priceInput) {
                                const price = priceInput.value.replace(/,/g, '');
                                priceInput.value = price;
                            }
                            
                            console.log('Form data prepared for submission');
                            
                        } catch (error) {
                            console.error('Error preparing form submission:', error);
                            e.preventDefault();
                            
                            // Reset button state
                            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Lead';
                            saveBtn.disabled = false;
                        }
                    });
                }
                
                // Debug: Log current state
                console.log('Form initialization complete');
                console.log('Available developers:', Object.keys(projectModelsData));
                console.log('Total project models:', Object.values(projectModelsData).flat().length);
                
            } catch (error) {
                console.error('Error initializing form:', error);
            }
        });

        // Debug function to test project model loading
        function debugProjectModels() {
            console.log('=== DEBUG PROJECT MODELS ===');
            console.log('Project Models Data:', projectModelsData);
            
            const developerSelect = document.getElementById('developer');
            const projectModelSelect = document.getElementById('project_model');
            
            console.log('Developer Select:', developerSelect);
            console.log('Project Model Select:', projectModelSelect);
            
            if (developerSelect) {
                console.log('Current Developer Value:', developerSelect.value);
                console.log('Available Options:', Array.from(developerSelect.options).map(opt => opt.value));
            }
            
            if (projectModelSelect) {
                console.log('Current Project Model Value:', projectModelSelect.value);
                console.log('Available Options:', Array.from(projectModelSelect.options).map(opt => opt.value));
            }
            
            console.log('=== END DEBUG ===');
        }

        // Make debug function available globally
        window.debugProjectModels = debugProjectModels;
    </script>
    
    <?php if (file_exists('assets/js/script.js')): ?>
        <script src="assets/js/script.js"></script>
    <?php endif; ?>
</body>
</html>
