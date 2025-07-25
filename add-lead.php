<?php
// Enhanced add-lead.php with strict duplicate prevention and popup modal
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
$duplicate_found = false;
$duplicate_details = [];

// ENHANCED: Function to check for exact duplicate leads (case-insensitive)
function checkExactDuplicateLead($clientName) {
    debugLog("Checking for exact duplicate lead: $clientName");
    
    try {
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        // Search for leads with exact same name (case-insensitive, trimmed)
        $stmt = $conn->prepare("
            SELECT 
                l.id, 
                l.client_name, 
                l.phone, 
                l.email, 
                l.status,
                l.created_at,
                u.name as agent_name,
                t.name as team_name
            FROM leads l
            LEFT JOIN users u ON l.user_id = u.id
            LEFT JOIN teams t ON u.team_id = t.id
            WHERE LOWER(TRIM(l.client_name)) = LOWER(TRIM(?))
            ORDER BY l.created_at DESC
            LIMIT 5
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare duplicate check statement: " . $conn->error);
        }
        
        $stmt->bind_param("s", $clientName);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute duplicate check: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $duplicates = [];
        
        while ($row = $result->fetch_assoc()) {
            $duplicates[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        debugLog("Found " . count($duplicates) . " exact duplicates for: $clientName");
        
        return $duplicates;
        
    } catch (Exception $e) {
        debugLog("Error checking for duplicates: " . $e->getMessage());
        return [];
    }
}

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
        
        // Handle "Others" option for developer/project
        $developer = isset($_POST['developer']) ? trim($_POST['developer']) : '';
        if ($developer === 'Others' && isset($_POST['developer_other']) && !empty(trim($_POST['developer_other']))) {
            $developer = trim($_POST['developer_other']);
        }
        
        // Handle "Others" option for project model
        $projectModel = isset($_POST['project_model']) ? trim($_POST['project_model']) : '';
        if ($projectModel === 'Others' && isset($_POST['project_model_other']) && !empty(trim($_POST['project_model_other']))) {
            $projectModel = trim($_POST['project_model_other']);
        }
        
        $priceRaw = isset($_POST['price']) ? trim($_POST['price']) : '';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
        
        // Handle "Others" option for lead source
        $source = isset($_POST['source']) ? trim($_POST['source']) : '';
        if ($source === 'Others' && isset($_POST['source_other']) && !empty(trim($_POST['source_other']))) {
            $source = trim($_POST['source_other']);
        }
        
        debugLog("Form data collected - Client: '$clientName', Phone: '$phone', Email: '$email'");
        debugLog("Project data - Developer: '$developer', Model: '$projectModel'");
        debugLog("Source data - Source: '$source'");
        
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
        
        // ENHANCED: Check for exact duplicate leads - PREVENT SAVING IF FOUND
        if (empty($validation_errors) && !empty($clientName)) {
            $duplicates = checkExactDuplicateLead($clientName);
            
            if (!empty($duplicates)) {
                $duplicate_found = true;
                $duplicate_details = $duplicates;
                
                // Log duplicate detection
                debugLog("DUPLICATE PREVENTED - Exact duplicate lead found for: $clientName");
                foreach ($duplicates as $dup) {
                    debugLog("Existing lead: ID {$dup['id']}, Agent: {$dup['agent_name']}, Team: {$dup['team_name']}, Status: {$dup['status']}");
                }
                
                // DO NOT PROCEED WITH SAVING - Set flag to show popup
                debugLog("Lead creation blocked due to duplicate name");
            }
        }
        
        if (!empty($validation_errors)) {
            $error = implode(", ", $validation_errors);
            debugLog("Validation failed: $error");
        } elseif (!$duplicate_found) {
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

// Enhanced getLeadSources function with better error handling and "Others" option
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
            'Youtube Marketing', 'LinkedIn', 'Open House', 'Facebook Page', 'Others'
        ];
        
        foreach ($defaultSources as $source) {
            $sources[] = [
                'id' => $source,
                'name' => $source
            ];
        }
    }
    
    // Always add "Others" option at the end
    $sources[] = [
        'id' => 'Others',
        'name' => 'Others'
    ];
    
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

        /* Others input field styling */
        .others-input {
            margin-top: 0.75rem;
            display: none;
        }

        .others-input.show {
            display: block;
        }

        .others-input input {
            border-color: #4f46e5;
            background-color: #f8fafc;
        }

        .others-input label {
            font-size: 0.75rem;
            color: #4f46e5;
            font-weight: 600;
            margin-bottom: 0.25rem;
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
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
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

        /* NEW: Duplicate Modal Styles */
        .duplicate-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            animation: fadeIn 0.3s ease;
        }

        .duplicate-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .duplicate-modal-content {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            animation: slideIn 0.3s ease;
        }

        .duplicate-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .duplicate-modal-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: #dc2626;
        }

        .duplicate-modal-title i {
            font-size: 1.5rem;
            color: #dc2626;
        }

        .duplicate-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }

        .duplicate-modal-close:hover {
            background-color: #f3f4f6;
            color: #374151;
        }

        .duplicate-modal-body {
            margin-bottom: 1.5rem;
        }

        .duplicate-warning-text {
            font-size: 1rem;
            color: #374151;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .duplicate-details {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .duplicate-details h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 0.75rem;
        }

        .duplicate-item {
            background: white;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-left: 3px solid #dc2626;
        }

        .duplicate-item:last-child {
            margin-bottom: 0;
        }

        .duplicate-item-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }

        .duplicate-item-details {
            font-size: 0.75rem;
            color: #6b7280;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .duplicate-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .auto-close-timer {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .timer-circle {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #e5e7eb;
            border-top-color: #dc2626;
            animation: spin 1s linear infinite;
        }

        .duplicate-modal-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-modal-close {
            padding: 0.5rem 1rem;
            background-color: #dc2626;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-modal-close:hover {
            background-color: #b91c1c;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Mobile responsive for modal */
        @media (max-width: 768px) {
            .duplicate-modal-content {
                width: 95%;
                padding: 1.5rem;
                margin: 1rem;
            }

            .duplicate-modal-title {
                font-size: 1.125rem;
            }

            .duplicate-modal-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .duplicate-modal-actions {
                width: 100%;
                justify-content: center;
            }

            .btn-modal-close {
                flex: 1;
            }
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
                    <i class="fas fa-check-circle"></i> 
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> 
                    <span><?php echo htmlspecialchars($error); ?></span>
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
                            
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       placeholder="e.g. 09123456789" maxlength="11" pattern="\d{11}">
                            </div>      
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="optional-field">(Optional)</span></label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="client@example.com" maxlength="100">
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="source">Lead Source</label>
                                <select id="source" name="source" required class="source-select" onchange="toggleSourceOthers(this.value)">
                                    <option value="">Select Lead Source</option>
                                    <?php foreach ($leadSources as $source): ?>
                                    <option value="<?php echo htmlspecialchars($source['name']); ?>"
                                            <?php echo (isset($_POST['source']) && $_POST['source'] === $source['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($source['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="others-input" id="source-others">
                                    <label for="source_other">Specify Lead Source</label>
                                    <input type="text" id="source_other" name="source_other" 
                                           value="<?php echo htmlspecialchars($_POST['source_other'] ?? ''); ?>"
                                           placeholder="Enter lead source" maxlength="100">
                                </div>
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
                                    <option value="Others" <?php echo (isset($_POST['developer']) && $_POST['developer'] === 'Others') ? 'selected' : ''; ?>>Others</option>
                                </select>
                                <div class="others-input" id="developer-others">
                                    <label for="developer_other">Specify Project</label>
                                    <input type="text" id="developer_other" name="developer_other" 
                                           value="<?php echo htmlspecialchars($_POST['developer_other'] ?? ''); ?>"
                                           placeholder="Enter project name" maxlength="100">
                                </div>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="project_model">House Model</label>
                                <select id="project_model" name="project_model" required onchange="toggleProjectModelOthers(this.value)">
                                    <option value="">Select House Model</option>
                                </select>
                                <div class="others-input" id="project-model-others">
                                    <label for="project_model_other">Specify House Model</label>
                                    <input type="text" id="project_model_other" name="project_model_other" 
                                           value="<?php echo htmlspecialchars($_POST['project_model_other'] ?? ''); ?>"
                                           placeholder="Enter house model name" maxlength="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="price">Total Contract Price (PHP)</label>
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

    <!-- NEW: Duplicate Detection Modal -->
    <?php if ($duplicate_found && !empty($duplicate_details)): ?>
    <div class="duplicate-modal show" id="duplicateModal">
        <div class="duplicate-modal-content">
            <div class="duplicate-modal-header">
                <div class="duplicate-modal-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Duplicate Lead Detected
                </div>
                <button class="duplicate-modal-close" onclick="closeDuplicateModal()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="duplicate-modal-body">
                <div class="duplicate-warning-text">
                    <strong>A lead with this exact name already exists in the system.</strong><br><br>
                    The lead was <strong>NOT SAVED</strong> to prevent duplicate entries. This helps maintain data integrity across all teams.
                </div>
                
                <div class="duplicate-details">
                    <h4>Existing Lead(s) Found:</h4>
                    <?php foreach ($duplicate_details as $dup): ?>
                    <div class="duplicate-item">
                        <div class="duplicate-item-name"><?php echo htmlspecialchars($dup['client_name']); ?></div>
                        <div class="duplicate-item-details">
                            <span><strong>Agent:</strong> <?php echo htmlspecialchars($dup['agent_name'] ?? 'N/A'); ?></span>
                            <span><strong>Team:</strong> <?php echo htmlspecialchars($dup['team_name'] ?? 'N/A'); ?></span>
                            <span><strong>Status:</strong> <?php echo htmlspecialchars($dup['status']); ?></span>
                            <span><strong>Created:</strong> <?php echo date('M j, Y', strtotime($dup['created_at'])); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="duplicate-warning-text">
                    If you believe this is an error or the leads are genuinely different people with the same name, please:
                    <ul style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                        <li>Report it to the system administrator via the login page, or</li>
                        <li>Notify management directly for manual review</li>
                    </ul>
                </div>
            </div>
            
            <div class="duplicate-modal-footer">
                <div class="auto-close-timer">
                    <div class="timer-circle"></div>
                    <span>Auto-closing in <span id="countdown">30</span> seconds</span>
                </div>
                <div class="duplicate-modal-actions">
                    <button class="btn-modal-close" onclick="closeDuplicateModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Enhanced JavaScript with duplicate modal handling
        console.log('Add Lead form script loaded');
        
        // NEW: Duplicate Modal Management
        let countdownTimer = null;
        let countdownSeconds = 30;

        function closeDuplicateModal() {
            const modal = document.getElementById('duplicateModal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
            
            if (countdownTimer) {
                clearInterval(countdownTimer);
                countdownTimer = null;
            }
        }

        function startCountdown() {
            const countdownElement = document.getElementById('countdown');
            if (!countdownElement) return;

            countdownTimer = setInterval(() => {
                countdownSeconds--;
                countdownElement.textContent = countdownSeconds;
                
                if (countdownSeconds <= 0) {
                    closeDuplicateModal();
                }
            }, 1000);
        }

        // Initialize countdown if modal is shown
        <?php if ($duplicate_found): ?>
        document.addEventListener('DOMContentLoaded', function() {
            startCountdown();
            
            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDuplicateModal();
                }
            });
            
            // Close modal on backdrop click
            const modal = document.getElementById('duplicateModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeDuplicateModal();
                    }
                });
            }
        });
        <?php endif; ?>
        
        // Function to toggle source others input
        function toggleSourceOthers(value) {
            const othersDiv = document.getElementById('source-others');
            const othersInput = document.getElementById('source_other');
            
            if (value === 'Others') {
                othersDiv.classList.add('show');
                othersInput.required = true;
            } else {
                othersDiv.classList.remove('show');
                othersInput.required = false;
                othersInput.value = '';
            }
        }
        
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
        
        // Function to toggle developer others input
        function toggleDeveloperOthers(value) {
            const othersDiv = document.getElementById('developer-others');
            const othersInput = document.getElementById('developer_other');
            
            if (value === 'Others') {
                othersDiv.classList.add('show');
                othersInput.required = true;
            } else {
                othersDiv.classList.remove('show');
                othersInput.required = false;
                othersInput.value = '';
            }
        }

        // Function to toggle project model others input
        function toggleProjectModelOthers(value) {
            const othersDiv = document.getElementById('project-model-others');
            const othersInput = document.getElementById('project_model_other');
            
            if (value === 'Others') {
                othersDiv.classList.add('show');
                othersInput.required = true;
            } else {
                othersDiv.classList.remove('show');
                othersInput.required = false;
                othersInput.value = '';
            }
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
            projectModelSelect.innerHTML = '<option value="">Select House Model</option>';
            
            // Toggle developer others input
            toggleDeveloperOthers(developer);
            
            if (developer && developer !== 'Others') {
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
                    
                    // Add "Others" option
                    const othersOption = document.createElement('option');
                    othersOption.value = 'Others';
                    othersOption.textContent = 'Others';
                    projectModelSelect.appendChild(othersOption);
                    
                    // Restore selected value if form was submitted with errors
                    const selectedModel = '<?php echo htmlspecialchars($_POST['project_model'] ?? ''); ?>';
                    if (selectedModel && (models.includes(selectedModel) || selectedModel === 'Others')) {
                        projectModelSelect.value = selectedModel;
                        toggleProjectModelOthers(selectedModel);
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
            } else if (developer === 'Others') {
                // Add "Others" option for custom developer
                const othersOption = document.createElement('option');
                othersOption.value = 'Others';
                othersOption.textContent = 'Others';
                projectModelSelect.appendChild(othersOption);
                
                // Auto-select Others if it was previously selected
                const selectedModel = '<?php echo htmlspecialchars($_POST['project_model'] ?? ''); ?>';
                if (selectedModel === 'Others') {
                    projectModelSelect.value = 'Others';
                    toggleProjectModelOthers('Others');
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
                
                // Add event listener for developer select change
                if (developerSelect) {
                    developerSelect.addEventListener('change', function() {
                        loadProjectModels(this.value);
                    });
                }

                // Add event listener for project model select change
                const projectModelSelect = document.getElementById('project_model');
                if (projectModelSelect) {
                    projectModelSelect.addEventListener('change', function() {
                        toggleProjectModelOthers(this.value);
                    });
                }

                // Add event listener for source select change
                const sourceSelect = document.getElementById('source');
                if (sourceSelect) {
                    sourceSelect.addEventListener('change', function() {
                        toggleSourceOthers(this.value);
                    });
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
                
                // Initialize others inputs based on current values
                const currentSource = '<?php echo htmlspecialchars($_POST['source'] ?? ''); ?>';
                const currentDeveloper = '<?php echo htmlspecialchars($_POST['developer'] ?? ''); ?>';
                const currentProjectModel = '<?php echo htmlspecialchars($_POST['project_model'] ?? ''); ?>';
                
                if (currentSource === 'Others') {
                    toggleSourceOthers('Others');
                }
                
                if (currentDeveloper === 'Others') {
                    toggleDeveloperOthers('Others');
                }
                
                if (currentProjectModel === 'Others') {
                    toggleProjectModelOthers('Others');
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