<?php
// Enhanced add-lead.php with comprehensive cross-team duplicate prevention
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
    debugLog("User found: " . ($user['name'] ?? 'Unknown') . " from team: " . ($user['team_name'] ?? 'Unknown'));
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
$similar_leads = [];

// ENHANCED: Comprehensive duplicate detection across all teams
function checkComprehensiveDuplicates($clientName, $phone = '', $email = '') {
    debugLog("Checking comprehensive duplicates for: Name='$clientName', Phone='$phone', Email='$email'");
    
    try {
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $duplicates = [];
        $similar = [];
        
        // 1. EXACT NAME MATCH (case-insensitive, trimmed) - BLOCKING
        $stmt = $conn->prepare("
            SELECT 
                l.id, 
                l.client_name, 
                l.phone, 
                l.email, 
                l.status,
                l.created_at,
                l.updated_at,
                u.name as agent_name,
                t.name as team_name,
                t.id as team_id,
                'exact_name' as match_type,
                'BLOCKING' as severity
            FROM leads l
            LEFT JOIN users u ON l.user_id = u.id
            LEFT JOIN teams t ON u.team_id = t.id
            WHERE LOWER(TRIM(l.client_name)) = LOWER(TRIM(?))
            ORDER BY l.created_at DESC
            LIMIT 10
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare exact name check: " . $conn->error);
        }
        
        $stmt->bind_param("s", $clientName);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute exact name check: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $duplicates[] = $row;
        }
        $stmt->close();
        
        // 2. PHONE NUMBER MATCH (if provided) - BLOCKING
        if (!empty($phone) && strlen($phone) >= 10) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            
            $stmt = $conn->prepare("
                SELECT 
                    l.id, 
                    l.client_name, 
                    l.phone, 
                    l.email, 
                    l.status,
                    l.created_at,
                    l.updated_at,
                    u.name as agent_name,
                    t.name as team_name,
                    t.id as team_id,
                    'exact_phone' as match_type,
                    'BLOCKING' as severity
                FROM leads l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN teams t ON u.team_id = t.id
                WHERE REPLACE(REPLACE(REPLACE(l.phone, '-', ''), ' ', ''), '+63', '0') = ?
                   OR REPLACE(REPLACE(REPLACE(l.phone, '-', ''), ' ', ''), '+63', '0') = ?
                ORDER BY l.created_at DESC
                LIMIT 5
            ");
            
            if ($stmt) {
                $phoneVariant1 = $cleanPhone;
                $phoneVariant2 = '0' . substr($cleanPhone, -10); // Handle +63 vs 0 prefix
                
                $stmt->bind_param("ss", $phoneVariant1, $phoneVariant2);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        // Avoid duplicating if already found by name
                        $exists = false;
                        foreach ($duplicates as $dup) {
                            if ($dup['id'] == $row['id']) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $duplicates[] = $row;
                        }
                    }
                }
                $stmt->close();
            }
        }
        
        // 3. EMAIL MATCH (if provided) - BLOCKING
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("
                SELECT 
                    l.id, 
                    l.client_name, 
                    l.phone, 
                    l.email, 
                    l.status,
                    l.created_at,
                    l.updated_at,
                    u.name as agent_name,
                    t.name as team_name,
                    t.id as team_id,
                    'exact_email' as match_type,
                    'BLOCKING' as severity
                FROM leads l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN teams t ON u.team_id = t.id
                WHERE LOWER(TRIM(l.email)) = LOWER(TRIM(?))
                ORDER BY l.created_at DESC
                LIMIT 5
            ");
            
            if ($stmt) {
                $stmt->bind_param("s", $email);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        // Avoid duplicating if already found
                        $exists = false;
                        foreach ($duplicates as $dup) {
                            if ($dup['id'] == $row['id']) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $duplicates[] = $row;
                        }
                    }
                }
                $stmt->close();
            }
        }
        
        // 4. SIMILAR NAME MATCH - WARNING ONLY
        $nameWords = explode(' ', strtolower(trim($clientName)));
        if (count($nameWords) >= 2) {
            $firstName = $nameWords[0];
            $lastName = end($nameWords);
            
            $stmt = $conn->prepare("
                SELECT 
                    l.id, 
                    l.client_name, 
                    l.phone, 
                    l.email, 
                    l.status,
                    l.created_at,
                    l.updated_at,
                    u.name as agent_name,
                    t.name as team_name,
                    t.id as team_id,
                    'similar_name' as match_type,
                    'WARNING' as severity
                FROM leads l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN teams t ON u.team_id = t.id
                WHERE (LOWER(l.client_name) LIKE ? OR LOWER(l.client_name) LIKE ?)
                  AND LOWER(TRIM(l.client_name)) != LOWER(TRIM(?))
                ORDER BY l.created_at DESC
                LIMIT 5
            ");
            
            if ($stmt) {
                $firstNamePattern = "%$firstName%";
                $lastNamePattern = "%$lastName%";
                
                $stmt->bind_param("sss", $firstNamePattern, $lastNamePattern, $clientName);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $similar[] = $row;
                    }
                }
                $stmt->close();
            }
        }
        
        $conn->close();
        
        debugLog("Found " . count($duplicates) . " blocking duplicates and " . count($similar) . " similar leads");
        
        return [
            'blocking' => $duplicates,
            'similar' => $similar
        ];
        
    } catch (Exception $e) {
        debugLog("Error checking for duplicates: " . $e->getMessage());
        return ['blocking' => [], 'similar' => []];
    }
}

// Enhanced function to get team statistics for duplicate context
function getTeamDuplicateStats($clientName) {
    debugLog("Getting team duplicate statistics for: $clientName");
    
    try {
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $stmt = $conn->prepare("
            SELECT 
                t.name as team_name,
                t.id as team_id,
                COUNT(l.id) as lead_count,
                GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ') as agents
            FROM leads l
            LEFT JOIN users u ON l.user_id = u.id
            LEFT JOIN teams t ON u.team_id = t.id
            WHERE LOWER(TRIM(l.client_name)) = LOWER(TRIM(?))
            GROUP BY t.id, t.name
            ORDER BY lead_count DESC
        ");
        
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param("s", $clientName);
        if (!$stmt->execute()) {
            return [];
        }
        
        $result = $stmt->get_result();
        $stats = [];
        
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        return $stats;
        
    } catch (Exception $e) {
        debugLog("Error getting team stats: " . $e->getMessage());
        return [];
    }
}

// Enhanced getDevelopers function (keeping original logic)
function getDevelopersEnhanced() {
    debugLog("Getting developers");
    
    try {
        if (function_exists('getDevelopers')) {
            $developers = getDevelopers();
            if (!empty($developers)) {
                debugLog("Got " . count($developers) . " developers from getDevelopers()");
                return $developers;
            }
        }
        
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $developers = [];
        
        // Use the developers table as the primary source for projects
        $possible_queries = [
            "SELECT DISTINCT name FROM developers WHERE is_active = 1 ORDER BY name",
            "SELECT DISTINCT name FROM developers ORDER BY name"
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
        
        $defaultDevelopers = ['Lancaster', 'Antipolo Heights', 'Pleasant Fields', 'Vista Verde', 'Golden Hills'];
        $developers = [];
        foreach ($defaultDevelopers as $dev) {
            $developers[] = ['name' => $dev];
        }
        return $developers;
    }
}

// Enhanced getProjectModels function (keeping original logic)
function getProjectModelsEnhanced() {
    debugLog("Getting project models");
    
    try {
        if (function_exists('getProjectModels')) {
            $models = getProjectModels();
            if (!empty($models)) {
                debugLog("Got " . count($models) . " project models from getProjectModels()");
                return $models;
            }
        }
        
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $models = [];
        
        // Get project models with proper developer relationship
        $possible_queries = [
            "SELECT d.name as developer_name, pm.name 
             FROM project_models pm 
             JOIN developers d ON pm.developer_id = d.id 
             WHERE pm.is_active = 1 AND d.is_active = 1 
             ORDER BY d.name, pm.name",
            "SELECT d.name as developer_name, pm.name 
             FROM project_models pm 
             JOIN developers d ON pm.developer_id = d.id 
             ORDER BY d.name, pm.name"
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
        // Use the full international phone number if available, otherwise fallback to regular phone
        $phone = isset($_POST['phone_full']) && !empty(trim($_POST['phone_full'])) ? trim($_POST['phone_full']) : (isset($_POST['phone']) ? trim($_POST['phone']) : '');
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $facebook = isset($_POST['facebook']) ? trim($_POST['facebook']) : '';
        $linkedin = isset($_POST['linkedin']) ? trim($_POST['linkedin']) : '';
        $temperature = isset($_POST['temperature']) ? trim($_POST['temperature']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $leadClassification = isset($_POST['lead_classification']) ? trim($_POST['lead_classification']) : '';
        
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
        
        
        // ENHANCED: Comprehensive duplicate check across all teams
        if (empty($validation_errors) && !empty($clientName)) {
            $duplicateResults = checkComprehensiveDuplicates($clientName, $phone, $email);
            $blockingDuplicates = $duplicateResults['blocking'];
            $similarLeads = $duplicateResults['similar'];
            
            if (!empty($blockingDuplicates)) {
                $duplicate_found = true;
                $duplicate_details = $blockingDuplicates;
                
                // Get team statistics for context
                $teamStats = getTeamDuplicateStats($clientName);
                
                // Log comprehensive duplicate detection
                debugLog("COMPREHENSIVE DUPLICATE PREVENTED - Found " . count($blockingDuplicates) . " blocking duplicates for: $clientName");
                foreach ($blockingDuplicates as $dup) {
                    debugLog("Blocking duplicate: ID {$dup['id']}, Agent: {$dup['agent_name']}, Team: {$dup['team_name']}, Match: {$dup['match_type']}, Status: {$dup['status']}");
                }
                
                if (!empty($similarLeads)) {
                    debugLog("Also found " . count($similarLeads) . " similar leads (non-blocking)");
                    $similar_leads = $similarLeads;
                }
                
                // DO NOT PROCEED WITH SAVING - Set flag to show enhanced popup
                debugLog("Lead creation blocked due to comprehensive duplicate detection across teams");
            } elseif (!empty($similarLeads)) {
                // Store similar leads for warning display but allow saving
                $similar_leads = $similarLeads;
                debugLog("Found " . count($similarLeads) . " similar leads (warning only)");
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
                $temperature, $status, $source, $leadClassification, $developer, $projectModel, $price, $remarks
            );
            
            if ($result) {
                debugLog("Lead added successfully with result: " . (is_numeric($result) ? "ID $result" : "true"));
                $success = "Lead added successfully";
                
                // Log successful cross-team validation
                if (!empty($similar_leads)) {
                    debugLog("Lead saved despite " . count($similar_leads) . " similar leads found (warnings only)");
                }
                
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

// Enhanced getLeadSources function (keeping original logic)
function getLeadSources() {
    debugLog("Getting lead sources");
    
    try {
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $sources = [];
        
        $stmt = $conn->prepare("SHOW COLUMNS FROM leads WHERE Field = 'source'");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
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
    
    if (empty($sources)) {
        debugLog("Using default lead sources");
        $defaultSources = [
            'Facebook Groups', 'KKK', 'Facebook Ads', 'TikTok ads', 'Google Ads', 
            'Facebook live', 'Referral', 'Teleprospecting', 'Video Message', 
            'Organic Posting', 'Email Marketing', 'Follow up', 'Manning', 
            'Walk in', 'Flyering', 'Chat messaging', 'Property Listing', 
            'Landing Page', 'Networking Events', 'Organic Sharing', 
            'Youtube Marketing', 'LinkedIn', 'Open House', 'Facebook Page', 'OFW','Others'
        ];
        
        foreach ($defaultSources as $source) {
            $sources[] = [
                'id' => $source,
                'name' => $source
            ];
        }
    }
    
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
    
    <!-- International Phone Input -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>
    
    <style>
        /* Enhanced styles for comprehensive duplicate detection */
        :root {
            --container-padding: 25px;
            --primary-color: #4f46e5;
            --danger-color: #dc2626;
            --warning-color: #f59e0b;
            --success-color: #10b981;
        }

        @media (max-width: 768px) {
            :root {
                --container-padding: 15px;
            }
        }

        /* Base styles */
        body {
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background-color: #f9fafb;
        }
        
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
            background: linear-gradient(to right, var(--primary-color), #8b5cf6);
            border-radius: 0.25rem;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 0.625rem 1rem;
            background-color: white;
            color: var(--primary-color);
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
            background: linear-gradient(to bottom, var(--primary-color), #8b5cf6);
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
        
        .required-field label::after {
            content: ' *';
            color: var(--danger-color);
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
            border-color: var(--primary-color);
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

        .others-input {
            margin-top: 0.75rem;
            display: none;
        }

        .others-input.show {
            display: block;
        }

        .others-input input {
            border-color: var(--primary-color);
            background-color: #f8fafc;
        }

        .others-input label {
            font-size: 0.75rem;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
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
            background-color: var(--primary-color);
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
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* ENHANCED: Comprehensive Duplicate Modal Styles */
        .duplicate-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
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
            padding: 0;
            max-width: 800px;
            width: 95%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            animation: slideIn 0.3s ease;
        }

        .duplicate-modal-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .duplicate-modal-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .duplicate-modal-title i {
            font-size: 1.5rem;
        }

        .duplicate-modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }

        .duplicate-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .duplicate-modal-body {
            padding: 2rem;
            max-height: 60vh;
            overflow-y: auto;
        }

        .duplicate-warning-text {
            font-size: 1rem;
            color: #374151;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #fef2f2;
            border-left: 4px solid var(--danger-color);
            border-radius: 0.5rem;
        }

        .duplicate-warning-text strong {
            color: var(--danger-color);
        }

        .duplicate-section {
            margin-bottom: 2rem;
        }

        .duplicate-section h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .blocking-section h4 {
            color: var(--danger-color);
        }

        .blocking-section h4::before {
            content: '🚫';
            font-size: 1.25rem;
        }

        .similar-section h4 {
            color: var(--warning-color);
        }

        .similar-section h4::before {
            content: '⚠️';
            font-size: 1.25rem;
        }

        .duplicate-details {
            background-color: #f9fafb;
            border-radius: 0.5rem;
            padding: 1rem;
            border: 1px solid #e5e7eb;
        }

        .duplicate-item {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid var(--danger-color);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .duplicate-item:last-child {
            margin-bottom: 0;
        }

        .similar-item {
            border-left-color: var(--warning-color);
        }

        .duplicate-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .duplicate-item-name {
            font-weight: 600;
            color: #111827;
            font-size: 1rem;
            flex: 1;
        }

        .duplicate-item-match-type {
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            margin-left: 1rem;
        }

        .similar-item .duplicate-item-match-type {
            background: var(--warning-color);
        }

        .duplicate-item-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .duplicate-item-detail {
            display: flex;
            flex-direction: column;
        }

        .duplicate-item-detail strong {
            color: #374151;
            font-weight: 500;
            margin-bottom: 0.125rem;
        }

        .team-stats {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1.5rem;
        }

        .team-stats h5 {
            color: #0369a1;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .team-stats h5::before {
            content: '👥';
        }

        .team-stat-item {
            background: white;
            padding: 0.75rem;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
            border-left: 3px solid #0ea5e9;
        }

        .team-stat-item:last-child {
            margin-bottom: 0;
        }

        .duplicate-modal-footer {
            background-color: #f9fafb;
            padding: 1.5rem 2rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            border-top-color: var(--danger-color);
            animation: spin 1s linear infinite;
        }

        .duplicate-modal-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-modal-close {
            padding: 0.75rem 1.5rem;
            background-color: var(--danger-color);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
                width: 98%;
                margin: 1rem;
                max-height: 95vh;
            }

            .duplicate-modal-header {
                padding: 1rem 1.5rem;
            }

            .duplicate-modal-title {
                font-size: 1.125rem;
            }

            .duplicate-modal-body {
                padding: 1.5rem;
            }

            .duplicate-item-details {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .duplicate-modal-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
                padding: 1rem 1.5rem;
            }

            .duplicate-modal-actions {
                width: 100%;
                justify-content: center;
            }

            .btn-modal-close {
                flex: 1;
                justify-content: center;
            }
        }
        
        .required-note {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .required-note span {
            color: var(--danger-color);
        }
        
        .optional-field {
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: normal;
            margin-left: 0.25rem;
        }

        /* Similar leads warning (non-blocking) */
        .similar-warning {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #92400e;
        }

        .similar-warning h4 {
            color: #92400e;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .similar-warning h4::before {
            content: '⚠️';
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

        /* International phone input styles - Proper separated design */
        .iti {
            width: 100%;
            position: relative;
        }
        
        .iti__country-list {
            z-index: 9999;
            max-height: 250px;
            width: 600px;
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #d1d5db;
        }
        
        .iti__selected-flag {
            padding: 0.75rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            border-bottom-right-radius: 0;
            border-top-right-radius: 0;
            border-right: none;
            background: #f9fafb;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .iti__selected-flag:hover {
            border-color: #9ca3af;
            background-color: #f3f4f6;
        }
        
        .iti__selected-flag:focus {
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .iti__flag {
            margin-right: 0.5rem;
        }
        
        .iti__selected-dial-code {
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .iti__arrow {
            margin-left: 0.5rem;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 4px solid #6b7280;
        }
        
        .iti input[type="tel"] {
            border-left: none;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            padding-left: 1rem;
        }
        
        .iti input[type="tel"]:focus {
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        /* Make sure the flag and input align properly */
        .iti--separate-dial-code .iti__selected-flag {
            background-color: #f9fafb;
            min-width: 100px;
        }
        
        .iti--separate-dial-code input[type="tel"] {
            padding-left: 1rem;
        }
        
        /* Autocomplete dropdown styles */
        .autocomplete-container {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
        }
        
        .autocomplete-input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background-color: #fff;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .autocomplete-input:focus {
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-top: none;
            border-radius: 0 0 0.5rem 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .autocomplete-dropdown.show {
            display: block;
        }
        
        .autocomplete-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
            transition: background-color 0.15s ease;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .autocomplete-item:hover,
        .autocomplete-item.active {
            background-color: #f3f4f6;
        }
        
        .autocomplete-item.selected {
            background-color: var(--primary-light);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .autocomplete-no-results {
            padding: 1rem;
            text-align: center;
            color: #6b7280;
            font-style: italic;
            font-size: 0.875rem;
        }
        
        /* Clear button styles */
        .autocomplete-clear {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            transition: color 0.15s ease, background-color 0.15s ease;
            z-index: 2;
        }
        
        .autocomplete-clear:hover {
            background-color: #f3f4f6;
            color: #374151;
        }
        
        .autocomplete-clear:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* Add project item styles */
        .autocomplete-add-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
            background-color: #f8fafc;
            color: var(--primary-color);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .autocomplete-add-item:hover,
        .autocomplete-add-item.active {
            background-color: #e0e7ff;
        }
        
        .autocomplete-add-item .fas {
            font-size: 0.75rem;
        }

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

                <?php if (!empty($similar_leads) && !$duplicate_found): ?>
                <div class="similar-warning">
                    <h4>Similar Leads Found</h4>
                    <p>We found <?php echo count($similar_leads); ?> similar lead(s) in the system. Please review to ensure this is not a duplicate before saving.</p>
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
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       placeholder="Enter phone number">
                                <input type="hidden" id="phone_full" name="phone_full" value="">
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
                                <div class="autocomplete-container">
                                    <input type="text" id="source" name="source" required 
                                           class="autocomplete-input" 
                                           value="<?php echo htmlspecialchars($_POST['source'] ?? ''); ?>"
                                           placeholder="Type to search or select lead source..."
                                           autocomplete="off">
                                    <div id="source-dropdown" class="autocomplete-dropdown"></div>
                                </div>
                                <input type="hidden" id="source_original" name="source_original" value="">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="lead_classification">Lead Classification</label>
                                <select id="lead_classification" name="lead_classification">
                                    <option value="">Select Lead Classification</option>
                                    <option value="Locally/Internationally Employed" <?php echo (isset($_POST['lead_classification']) && $_POST['lead_classification'] === 'Locally/Internationally Employed') ? 'selected' : ''; ?>>
                                        Locally Employed
                                    </option>
                                    <option value="OFW" <?php echo (isset($_POST['lead_classification']) && $_POST['lead_classification'] === 'OFW') ? 'selected' : ''; ?>>
                                        OFW
                                    </option>
                                    <option value="Self employed" <?php echo (isset($_POST['lead_classification']) && $_POST['lead_classification'] === 'Self employed') ? 'selected' : ''; ?>>
                                        Self employed
                                    </option>
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
                                <div class="autocomplete-container">
                                    <input type="text" id="developer" name="developer" required 
                                           class="autocomplete-input" 
                                           value="<?php echo htmlspecialchars($_POST['developer'] ?? ''); ?>"
                                           placeholder="Type to search or select project..."
                                           autocomplete="off">
                                    <button type="button" class="autocomplete-clear" id="developer-clear" title="Clear project" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <div id="developer-dropdown" class="autocomplete-dropdown"></div>
                                </div>
                                <input type="hidden" id="developer_id" name="developer_id" value="">
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

    <!-- ENHANCED: Comprehensive Cross-Team Duplicate Detection Modal -->
    <?php if ($duplicate_found && !empty($duplicate_details)): ?>
    <div class="duplicate-modal show" id="duplicateModal">
        <div class="duplicate-modal-content">
            <div class="duplicate-modal-header">
                <div class="duplicate-modal-title">
                    <i class="fas fa-shield-alt"></i>
                    Cross-Team Duplicate Protection
                </div>
                <button class="duplicate-modal-close" onclick="closeDuplicateModal()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="duplicate-modal-body">
                <div class="duplicate-warning-text">
                    <strong>🚫 Lead Creation Blocked</strong><br><br>
                    Our comprehensive validation system has detected that this lead already exists in the system across teams. 
                    The lead was <strong>NOT SAVED</strong> to maintain data integrity and prevent duplicate entries across all teams.
                </div>
                
                <div class="duplicate-section blocking-section">
                    <h4>Blocking Duplicates Found</h4>
                    <div class="duplicate-details">
                        <?php foreach ($duplicate_details as $dup): ?>
                        <div class="duplicate-item">
                            <div class="duplicate-item-header">
                                <div class="duplicate-item-name"><?php echo htmlspecialchars($dup['client_name']); ?></div>
                                <div class="duplicate-item-match-type"><?php echo htmlspecialchars($dup['match_type']); ?></div>
                            </div>
                            <div class="duplicate-item-details">
                                <div class="duplicate-item-detail">
                                    <strong>Agent:</strong>
                                    <span><?php echo htmlspecialchars($dup['agent_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="duplicate-item-detail">
                                    <strong>Team:</strong>
                                    <span><?php echo htmlspecialchars($dup['team_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="duplicate-item-detail">
                                    <strong>Status:</strong>
                                    <span><?php echo htmlspecialchars($dup['status']); ?></span>
                                </div>
                                <div class="duplicate-item-detail">
                                    <strong>Created:</strong>
                                    <span><?php echo date('M j, Y g:i A', strtotime($dup['created_at'])); ?></span>
                                </div>
                                <?php if (!empty($dup['phone'])): ?>
                                <div class="duplicate-item-detail">
                                    <strong>Phone:</strong>
                                    <span><?php echo htmlspecialchars($dup['phone']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($dup['email'])): ?>
                                <div class="duplicate-item-detail">
                                    <strong>Email:</strong>
                                    <span><?php echo htmlspecialchars($dup['email']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($similar_leads)): ?>
                <div class="duplicate-section similar-section">
                    <h4>Additional Similar Leads</h4>
                    <div class="duplicate-details">
                        <?php foreach ($similar_leads as $similar): ?>
                        <div class="duplicate-item similar-item">
                            <div class="duplicate-item-header">
                                <div class="duplicate-item-name"><?php echo htmlspecialchars($similar['client_name']); ?></div>
                                <div class="duplicate-item-match-type"><?php echo htmlspecialchars($similar['match_type']); ?></div>
                            </div>
                            <div class="duplicate-item-details">
                                <div class="duplicate-item-detail">
                                    <strong>Agent:</strong>
                                    <span><?php echo htmlspecialchars($similar['agent_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="duplicate-item-detail">
                                    <strong>Team:</strong>
                                    <span><?php echo htmlspecialchars($similar['team_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="duplicate-item-detail">
                                    <strong>Status:</strong>
                                    <span><?php echo htmlspecialchars($similar['status']); ?></span>
                                </div>
                                <div class="duplicate-item-detail">
                                    <strong>Created:</strong>
                                    <span><?php echo date('M j, Y', strtotime($similar['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php 
                $teamStats = getTeamDuplicateStats($clientName);
                if (!empty($teamStats)): 
                ?>
                <div class="team-stats">
                    <h5>Cross-Team Impact Analysis</h5>
                    <?php foreach ($teamStats as $stat): ?>
                    <div class="team-stat-item">
                        <strong><?php echo htmlspecialchars($stat['team_name']); ?>:</strong>
                        <?php echo $stat['lead_count']; ?> lead(s) with this name
                        <br><small>Agents: <?php echo htmlspecialchars($stat['agents']); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="duplicate-warning-text">
                    <strong>Next Steps:</strong><br>
                    • Contact the existing lead's agent or team for coordination<br>
                    • If this is genuinely a different person, contact your system administrator<br>
                    • Report any system issues via the support channels<br><br>
                    This cross-team validation helps maintain data quality and prevents conflicts between teams.
                </div>
            </div>
            
            <div class="duplicate-modal-footer">
                <div class="auto-close-timer">
                    <div class="timer-circle"></div>
                    <span>Auto-closing in <span id="countdown">45</span> seconds</span>
                </div>
                <div class="duplicate-modal-actions">
                    <button class="btn-modal-close" onclick="closeDuplicateModal()">
                        <i class="fas fa-check"></i> Understood
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Enhanced JavaScript with comprehensive duplicate modal handling
        console.log('Enhanced Add Lead form script loaded');
        
        // Enhanced Duplicate Modal Management
        let countdownTimer = null;
        let countdownSeconds = 45; // Increased time for comprehensive review

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
        
        // Initialize international telephone input
        let iti;
        
        // Lead source autocomplete functionality
        let leadSourceAutocomplete;
        
        // Project autocomplete functionality
        let projectAutocomplete;
        
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
        
        // Initialize International Telephone Input
        function initIntlTelInput() {
            const input = document.querySelector("#phone");
            if (!input) return;
            
            iti = window.intlTelInput(input, {
                separateDialCode: true,
                initialCountry: "ph",
                preferredCountries: ["ph", "us", "gb", "au", "ca"],
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js"
            });
            
            // On form submit, set the hidden field value to the full international number
            document.querySelector("#leadForm").addEventListener("submit", function() {
                if (iti && iti.getNumber()) {
                    const fullNumber = iti.getNumber();
                    document.querySelector("#phone_full").value = fullNumber;
                    console.log('International phone number:', fullNumber);
                }
            });
            
            // Add formatting on blur
            input.addEventListener("blur", function() {
                if (iti) {
                    // Format number if it's valid
                    if (iti.isValidNumber()) {
                        const nationalNumber = iti.getNumber(intlTelInputUtils.numberFormat.NATIONAL);
                        input.value = nationalNumber;
                    }
                }
            });
            
            // Validate phone number on change
            input.addEventListener("change", function() {
                if (iti && input.value.trim()) {
                    const isValid = iti.isValidNumber();
                    input.classList.toggle('error', !isValid);
                    
                    if (isValid) {
                        input.title = 'Valid phone number';
                    } else {
                        input.title = 'Invalid phone number';
                    }
                }
            });
        }
        
        // Initialize Lead Source Autocomplete
        function initLeadSourceAutocomplete() {
            const input = document.querySelector("#source");
            const dropdown = document.querySelector("#source-dropdown");
            const originalInput = document.querySelector("#source_original");
            
            if (!input || !dropdown) return;
            
            // Setup input event listeners
            input.addEventListener("input", function() {
                const query = this.value.trim();
                if (query.length >= 1) {
                    fetchLeadSources(query, dropdown);
                } else {
                    dropdown.innerHTML = '';
                    dropdown.classList.remove('show');
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
            
            // Handle focus on input
            input.addEventListener('focus', function() {
                const query = this.value.trim();
                if (query.length >= 1) {
                    fetchLeadSources(query, dropdown);
                } else {
                    fetchLeadSources('', dropdown);
                }
            });
            
            // Fetch lead sources from API
            function fetchLeadSources(query, dropdown) {
                // Make AJAX call
                fetch('api/get_lead_sources.php?search=' + encodeURIComponent(query))
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Lead sources data:', data);
                        renderLeadSourceDropdown(data.sources, dropdown);
                    })
                    .catch(error => {
                        console.error('Error fetching lead sources:', error);
                    });
            }
            
            // Render lead sources dropdown
            function renderLeadSourceDropdown(sources, dropdown) {
                dropdown.innerHTML = '';
                
                if (sources.length === 0) {
                    dropdown.innerHTML = '<div class="autocomplete-no-results">No lead sources found</div>';
                    dropdown.classList.add('show');
                    return;
                }
                
                sources.forEach(source => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item';
                    item.textContent = source.name;
                    item.setAttribute('data-value', source.name);
                    item.setAttribute('data-type', source.type || 'custom');
                    
                    item.addEventListener('click', function() {
                        input.value = source.name;
                        originalInput.value = source.name;
                        dropdown.classList.remove('show');
                    });
                    
                    dropdown.appendChild(item);
                });
                
                // Add custom option if not in the list and the current value is not empty
                const currentValue = input.value.trim();
                if (currentValue && !sources.some(source => source.name.toLowerCase() === currentValue.toLowerCase())) {
                    const addItem = document.createElement('div');
                    addItem.className = 'autocomplete-add-item';
                    addItem.innerHTML = `<i class="fas fa-plus-circle"></i> Create "${currentValue}"`;;
                    addItem.setAttribute('data-value', currentValue);
                    
                    addItem.addEventListener('click', function() {
                        input.value = currentValue;
                        originalInput.value = currentValue;
                        dropdown.classList.remove('show');
                    });
                    
                    dropdown.appendChild(addItem);
                }
                
                dropdown.classList.add('show');
            }
            
            // Keyboard navigation and selection
            input.addEventListener('keydown', function(e) {
                const items = dropdown.querySelectorAll('.autocomplete-item, .autocomplete-add-item');
                if (!items.length || !dropdown.classList.contains('show')) return;
                
                let activeItem = dropdown.querySelector('.active');
                let activeIndex = -1;
                
                if (activeItem) {
                    for (let i = 0; i < items.length; i++) {
                        if (items[i] === activeItem) {
                            activeIndex = i;
                            break;
                        }
                    }
                }
                
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (activeItem) activeItem.classList.remove('active');
                        activeIndex = (activeIndex + 1) % items.length;
                        items[activeIndex].classList.add('active');
                        items[activeIndex].scrollIntoView({ block: 'nearest' });
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        if (activeItem) activeItem.classList.remove('active');
                        activeIndex = (activeIndex <= 0) ? items.length - 1 : activeIndex - 1;
                        items[activeIndex].classList.add('active');
                        items[activeIndex].scrollIntoView({ block: 'nearest' });
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (activeItem) {
                            const value = activeItem.getAttribute('data-value');
                            input.value = value;
                            originalInput.value = value;
                            dropdown.classList.remove('show');
                        }
                        break;
                        
                    case 'Escape':
                        dropdown.classList.remove('show');
                        break;
                }
            });
        }
        
        // Initialize Project Autocomplete
        function initProjectAutocomplete() {
            const input = document.querySelector("#developer");
            const dropdown = document.querySelector("#developer-dropdown");
            const clearBtn = document.querySelector("#developer-clear");
            const idInput = document.querySelector("#developer_id");
            
            if (!input || !dropdown || !clearBtn) return;
            
            // Setup input event listeners
            input.addEventListener("input", function() {
                const query = this.value.trim();
                if (query.length >= 1) {
                    fetchProjects(query, dropdown);
                    clearBtn.style.display = 'flex';
                } else {
                    dropdown.innerHTML = '';
                    dropdown.classList.remove('show');
                    clearBtn.style.display = 'none';
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target) && !clearBtn.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
            
            // Handle focus on input
            input.addEventListener('focus', function() {
                const query = this.value.trim();
                if (query.length >= 1) {
                    fetchProjects(query, dropdown);
                } else {
                    fetchProjects('', dropdown);
                }
                if (input.value) {
                    clearBtn.style.display = 'flex';
                }
            });
            
            // Clear button functionality
            clearBtn.addEventListener('click', function() {
                input.value = '';
                idInput.value = '';
                clearBtn.style.display = 'none';
                dropdown.innerHTML = '';
                dropdown.classList.remove('show');
                
                // Clear house models as well
                const projectModelSelect = document.getElementById('project_model');
                if (projectModelSelect) {
                    projectModelSelect.innerHTML = '<option value="">Select House Model</option>';
                }
                
                input.focus();
            });
            
            // Fetch projects from API
            function fetchProjects(query, dropdown) {
                // Make AJAX call
                fetch('api/get_projects.php?search=' + encodeURIComponent(query))
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Projects data:', data);
                        renderProjectDropdown(data.projects, dropdown);
                    })
                    .catch(error => {
                        console.error('Error fetching projects:', error);
                    });
            }
            
            // Render projects dropdown
            function renderProjectDropdown(projects, dropdown) {
                dropdown.innerHTML = '';
                
                if (projects.length === 0) {
                    dropdown.innerHTML = '<div class="autocomplete-no-results">No projects found</div>';
                    dropdown.classList.add('show');
                    return;
                }
                
                projects.forEach(project => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item';
                    if (!project.is_active) {
                        item.className += ' inactive';
                    }
                    item.textContent = project.name;
                    item.setAttribute('data-value', project.name);
                    item.setAttribute('data-id', project.id);
                    
                    item.addEventListener('click', function() {
                        input.value = project.name;
                        idInput.value = project.id;
                        dropdown.classList.remove('show');
                        clearBtn.style.display = 'flex';
                        
                        // Load house models for this project
                        loadProjectModelsFromAPI(project.name);
                    });
                    
                    dropdown.appendChild(item);
                });
                
                // Add custom option if not in the list and the current value is not empty
                const currentValue = input.value.trim();
                if (currentValue && !projects.some(project => project.name.toLowerCase() === currentValue.toLowerCase())) {
                    const addItem = document.createElement('div');
                    addItem.className = 'autocomplete-add-item';
                    addItem.innerHTML = `<i class="fas fa-plus-circle"></i> Create "${currentValue}"`;;
                    addItem.setAttribute('data-value', currentValue);
                    
                    addItem.addEventListener('click', function() {
                        input.value = currentValue;
                        idInput.value = ''; // No ID for custom project
                        dropdown.classList.remove('show');
                        clearBtn.style.display = 'flex';
                        
                        // For custom projects, set empty house models
                        const projectModelSelect = document.getElementById('project_model');
                        if (projectModelSelect) {
                            projectModelSelect.innerHTML = '<option value="">Select House Model</option><option value="Others">Others</option>';
                            toggleProjectModelOthers('Others');
                        }
                    });
                    
                    dropdown.appendChild(addItem);
                }
                
                dropdown.classList.add('show');
            }
            
            // Keyboard navigation and selection
            input.addEventListener('keydown', function(e) {
                const items = dropdown.querySelectorAll('.autocomplete-item, .autocomplete-add-item');
                if (!items.length || !dropdown.classList.contains('show')) return;
                
                let activeItem = dropdown.querySelector('.active');
                let activeIndex = -1;
                
                if (activeItem) {
                    for (let i = 0; i < items.length; i++) {
                        if (items[i] === activeItem) {
                            activeIndex = i;
                            break;
                        }
                    }
                }
                
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (activeItem) activeItem.classList.remove('active');
                        activeIndex = (activeIndex + 1) % items.length;
                        items[activeIndex].classList.add('active');
                        items[activeIndex].scrollIntoView({ block: 'nearest' });
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        if (activeItem) activeItem.classList.remove('active');
                        activeIndex = (activeIndex <= 0) ? items.length - 1 : activeIndex - 1;
                        items[activeIndex].classList.add('active');
                        items[activeIndex].scrollIntoView({ block: 'nearest' });
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (activeItem) {
                            const value = activeItem.getAttribute('data-value');
                            input.value = value;
                            
                            // If it's a project from the database
                            if (activeItem.hasAttribute('data-id')) {
                                idInput.value = activeItem.getAttribute('data-id');
                                // Load house models for this project
                                loadProjectModelsFromAPI(value);
                            } else {
                                // It's a custom project
                                idInput.value = '';
                                // For custom projects, set empty house models
                                const projectModelSelect = document.getElementById('project_model');
                                if (projectModelSelect) {
                                    projectModelSelect.innerHTML = '<option value="">Select House Model</option><option value="Others">Others</option>';
                                    toggleProjectModelOthers('Others');
                                }
                            }
                            
                            dropdown.classList.remove('show');
                            clearBtn.style.display = 'flex';
                        }
                        break;
                        
                    case 'Escape':
                        dropdown.classList.remove('show');
                        break;
                }
            });
        }
        
        // Price formatting function
        function initPriceFormatting() {
            const priceInput = document.getElementById('price');
            if (priceInput) {
                priceInput.addEventListener('input', function(e) {
                    let value = this.value.replace(/[^\d.]/g, '');
                    
                    const parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    
                    if (parts[1] && parts[1].length > 2) {
                        value = parts[0] + '.' + parts[1].substring(0, 2);
                    }
                    
                    if (value) {
                        const numParts = value.split('.');
                        numParts[0] = numParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        this.value = numParts.join('.');
                    }
                });
            }
        }
        
        // Function to load project models from API based on selected project
        function loadProjectModelsFromAPI(projectName) {
            console.log('Loading project models from API for project:', projectName);
            
            const projectModelSelect = document.getElementById('project_model');
            if (!projectModelSelect) {
                console.error('Project model select element not found');
                return;
            }
            
            // Show loading state
            projectModelSelect.innerHTML = '<option value="">Loading models...</option>';
            projectModelSelect.disabled = true;
            
            if (!projectName || projectName.trim() === '') {
                projectModelSelect.innerHTML = '<option value="">Select House Model</option>';
                projectModelSelect.disabled = false;
                return;
            }
            
            // Make AJAX call to fetch house models
            fetch('api/get_project_models.php?project=' + encodeURIComponent(projectName.trim()))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API Response for project "' + projectName + '":', data);
                    
                    // Clear loading state
                    projectModelSelect.innerHTML = '<option value="">Select House Model</option>';
                    projectModelSelect.disabled = false;
                    
                    if (data.success && data.models && data.models.length > 0) {
                        // Add model options from API
                        data.models.forEach(model => {
                            const option = document.createElement('option');
                            option.value = model.name;
                            option.textContent = model.name;
                            
                            // Add additional info as data attributes for potential future use
                            if (model.base_price) {
                                option.setAttribute('data-base-price', model.base_price);
                            }
                            if (model.bedrooms) {
                                option.setAttribute('data-bedrooms', model.bedrooms);
                            }
                            if (model.bathrooms) {
                                option.setAttribute('data-bathrooms', model.bathrooms);
                            }
                            
                            projectModelSelect.appendChild(option);
                        });
                        
                        console.log('✅ Successfully loaded', data.models.length, 'models for project:', projectName);
                        
                        // Show brief success notification for debugging
                        if (data.models.length > 0) {
                            showBriefNotification(`Found ${data.models.length} house models for ${projectName}`, 'success');
                        }
                    } else if (data.success && !data.project_found) {
                        // Project not found in database, this might be a new project
                        console.log('⚠️ Project not found in developers table:', projectName);
                        
                        const noModelsOption = document.createElement('option');
                        noModelsOption.value = '';
                        noModelsOption.textContent = 'No models available (New project)';
                        noModelsOption.disabled = true;
                        projectModelSelect.appendChild(noModelsOption);
                    } else {
                        // No models found for existing project
                        console.log('⚠️ No models found for existing project:', projectName);
                        
                        const noModelsOption = document.createElement('option');
                        noModelsOption.value = '';
                        noModelsOption.textContent = 'No models available';
                        noModelsOption.disabled = true;
                        projectModelSelect.appendChild(noModelsOption);
                    }
                    
                    // Always add "Others" option
                    const othersOption = document.createElement('option');
                    othersOption.value = 'Others';
                    othersOption.textContent = 'Others';
                    projectModelSelect.appendChild(othersOption);
                    
                    // Restore selected value if form was submitted with errors
                    const selectedModel = '<?php echo htmlspecialchars($_POST['project_model'] ?? ''); ?>';
                    if (selectedModel) {
                        // Check if the selected model exists in the options
                        const optionExists = Array.from(projectModelSelect.options).some(option => option.value === selectedModel);
                        if (optionExists) {
                            projectModelSelect.value = selectedModel;
                            toggleProjectModelOthers(selectedModel);
                            console.log('Restored selected model:', selectedModel);
                        }
                    }
                    
                })
                .catch(error => {
                    console.error('Error fetching project models:', error);
                    
                    // Reset to default state on error
                    projectModelSelect.innerHTML = '<option value="">Select House Model</option>';
                    projectModelSelect.disabled = false;
                    
                    // Add error option
                    const errorOption = document.createElement('option');
                    errorOption.value = '';
                    errorOption.textContent = 'Error loading models';
                    errorOption.disabled = true;
                    projectModelSelect.appendChild(errorOption);
                    
                    // Always add "Others" option even on error
                    const othersOption = document.createElement('option');
                    othersOption.value = 'Others';
                    othersOption.textContent = 'Others';
                    projectModelSelect.appendChild(othersOption);
                    
                    showBriefNotification('Failed to load house models. Please try again.', 'error');
                });
        }
        
        // Legacy function for backward compatibility (keeping the old static approach as fallback)
        function loadProjectModels(developer) {
            console.log('Using legacy loadProjectModels for:', developer);
            // Call the new API-based function
            loadProjectModelsFromAPI(developer);
        }

        // International phone input instance
        let phoneInput;
        
        // Lead sources data from PHP
        const leadSourcesData = <?php echo json_encode(array_column($leadSources, 'name'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        
        // Developers data from PHP
        const developersData = <?php echo json_encode(array_column($developers, 'name'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        
        // Autocomplete functionality
        function initAutocomplete(inputId, dropdownId, data) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            const clearButton = document.getElementById(inputId + '-clear');
            
            if (!input || !dropdown) return;
            
            let activeIndex = -1;
            const isProjectField = inputId === 'developer';
            
            function showDropdown(items, query = '') {
                dropdown.innerHTML = '';
                
                // For project field, add "Add New Project" option when there's a query
                if (isProjectField && query.trim() && !items.includes(query.trim())) {
                    const addNewItem = document.createElement('div');
                    addNewItem.className = 'autocomplete-add-item';
                    addNewItem.innerHTML = `<i class="fas fa-plus"></i> Add "${query.trim()}" as new project`;
                    addNewItem.addEventListener('click', () => addNewProject(query.trim()));
                    dropdown.appendChild(addNewItem);
                }
                
                if (items.length === 0 && (!isProjectField || !query.trim())) {
                    const noResults = document.createElement('div');
                    noResults.className = 'autocomplete-no-results';
                    noResults.textContent = 'No results found';
                    dropdown.appendChild(noResults);
                } else {
                    items.forEach((item, index) => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        div.textContent = item;
                        div.addEventListener('click', () => selectItem(item));
                        
                        // Adjust active index for add item
                        const adjustedIndex = isProjectField && query.trim() && !items.includes(query.trim()) ? index + 1 : index;
                        if (adjustedIndex === activeIndex) {
                            div.classList.add('active');
                        }
                        
                        dropdown.appendChild(div);
                    });
                }
                
                dropdown.classList.add('show');
            }
            
            function hideDropdown() {
                dropdown.classList.remove('show');
                activeIndex = -1;
            }
            
            function selectItem(value) {
                input.value = value;
                hideDropdown();
                updateClearButtonVisibility();
                input.focus();
                
                // Trigger change event for any dependent functionality
                if (inputId === 'developer') {
                    loadProjectModelsFromAPI(value);
                }
            }
            
            function addNewProject(projectName) {
                // Show loading state
                const addItem = dropdown.querySelector('.autocomplete-add-item');
                if (addItem) {
                    addItem.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding project...';
                    addItem.style.pointerEvents = 'none';
                }
                
                // Make AJAX call to add project
                fetch('api/quick_add_project.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        name: projectName
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Add to the autocomplete data array for future use
                        if (!developersData.includes(projectName)) {
                            developersData.push(projectName);
                        }
                        
                        // Set the input value
                        input.value = projectName;
                        hideDropdown();
                        updateClearButtonVisibility();
                        
                        // For new projects, load models if they exist, otherwise show Others
                        loadProjectModelsFromAPI(projectName);
                        
                        input.focus();
                        
                        // Show success message
                        if (data.exists) {
                            console.log('Project already exists:', projectName);
                        } else {
                            console.log('New project added successfully:', projectName);
                            
                            // Optional: Show a brief success notification
                            showBriefNotification('Project "' + projectName + '" added successfully!', 'success');
                        }
                    } else {
                        console.error('Failed to add project:', data.message);
                        showBriefNotification('Failed to add project: ' + data.message, 'error');
                        
                        // Reset the dropdown
                        hideDropdown();
                        
                        // Still set the input value so user doesn't lose their entry
                        input.value = projectName;
                        updateClearButtonVisibility();
                    }
                })
                .catch(error => {
                    console.error('Error adding project:', error);
                    showBriefNotification('Error adding project. Please try again.', 'error');
                    
                    // Reset the dropdown
                    hideDropdown();
                    
                    // Still set the input value so user doesn't lose their entry
                    input.value = projectName;
                    updateClearButtonVisibility();
                });
            }
            
            function updateClearButtonVisibility() {
                if (clearButton) {
                    clearButton.style.display = input.value.trim() ? 'flex' : 'none';
                }
            }
            
            function filterItems(query) {
                if (!query.trim()) return data.slice(0, 10);
                
                return data.filter(item => 
                    item.toLowerCase().includes(query.toLowerCase())
                ).slice(0, 10); // Limit to 10 results
            }
            
            // Input event listener
            input.addEventListener('input', function() {
                const query = this.value;
                const filteredItems = filterItems(query);
                updateClearButtonVisibility();
                
                if (query.trim()) {
                    showDropdown(filteredItems, query);
                } else {
                    hideDropdown();
                }
                
                activeIndex = -1;
            });
            
            // Focus event listener
            input.addEventListener('focus', function() {
                const query = this.value;
                if (query.trim()) {
                    const filteredItems = filterItems(query);
                    showDropdown(filteredItems, query);
                } else {
                    showDropdown(data.slice(0, 10)); // Show first 10 items
                }
            });
            
            // Keyboard navigation
            input.addEventListener('keydown', function(e) {
                const items = dropdown.querySelectorAll('.autocomplete-item, .autocomplete-add-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, items.length - 1);
                    updateActiveItem(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, -1);
                    updateActiveItem(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeIndex >= 0 && items[activeIndex]) {
                        const activeItem = items[activeIndex];
                        if (activeItem.classList.contains('autocomplete-add-item')) {
                            // Extract project name from the add item text
                            const query = this.value.trim();
                            addNewProject(query);
                        } else {
                            selectItem(activeItem.textContent);
                        }
                    }
                } else if (e.key === 'Escape') {
                    hideDropdown();
                }
            });
            
            function updateActiveItem(items) {
                items.forEach((item, index) => {
                    item.classList.toggle('active', index === activeIndex);
                });
            }
            
            // Clear button functionality
            if (clearButton) {
                clearButton.addEventListener('click', function() {
                    input.value = '';
                    hideDropdown();
                    updateClearButtonVisibility();
                    
                    // Clear project models if this is the project field
                    if (inputId === 'developer') {
                        const projectModelSelect = document.getElementById('project_model');
                        if (projectModelSelect) {
                            projectModelSelect.innerHTML = '<option value="">Select House Model</option>';
                        }
                    }
                    
                    input.focus();
                });
                
                // Initial visibility update
                updateClearButtonVisibility();
            }
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-container')) {
                document.querySelectorAll('.autocomplete-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        });
        
        // Brief notification system
        function showBriefNotification(message, type = 'info') {
            // Remove existing notifications
            const existing = document.querySelector('.brief-notification');
            if (existing) {
                existing.remove();
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `brief-notification ${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add styles
            Object.assign(notification.style, {
                position: 'fixed',
                top: '20px',
                right: '20px',
                padding: '12px 16px',
                backgroundColor: type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6',
                color: 'white',
                borderRadius: '8px',
                boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
                zIndex: '10000',
                fontSize: '14px',
                fontWeight: '500',
                maxWidth: '300px',
                opacity: '0',
                transform: 'translateX(100%)',
                transition: 'all 0.3s ease'
            });
            
            // Add to document
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            // Remove after delay
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }, 3000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing enhanced form with international phone and autocomplete');
            
            try {
                // Initialize components
                initIntlTelInput();
                initLeadSourceAutocomplete();
                initProjectAutocomplete();
                initPriceFormatting();
                
                // Initialize project models if developer is already selected
                const developerInput = document.getElementById('developer');
                if (developerInput && developerInput.value) {
                    console.log('Initializing with pre-selected developer:', developerInput.value);
                    loadProjectModelsFromAPI(developerInput.value);
                }
                
                // Add event listener for project model select change
                const projectModelSelect = document.getElementById('project_model');
                if (projectModelSelect) {
                    projectModelSelect.addEventListener('change', function() {
                        toggleProjectModelOthers(this.value);
                    });
                }
                
                // Form validation on submit
                const form = document.getElementById('leadForm');
                const saveBtn = document.getElementById('saveBtn');
                
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const requiredFields = form.querySelectorAll('[required]');
                        let isValid = true;
                        
                        requiredFields.forEach(field => {
                            if (!field.value.trim()) {
                                isValid = false;
                                field.classList.add('error');
                            } else {
                                field.classList.remove('error');
                            }
                        });
                        
                        if (!isValid) {
                            e.preventDefault();
                            alert('Please fill in all required fields.');
                            return false;
                        }
                        
                        if (saveBtn) {
                            saveBtn.disabled = true;
                            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                        }
                    });
                }
                
                // Initialize others inputs based on current values
                const currentProjectModel = '<?php echo htmlspecialchars($_POST['project_model'] ?? ''); ?>';
                
                if (currentProjectModel === 'Others') {
                    toggleProjectModelOthers('Others');
                }
                
                // Debug: Log current state
                console.log('Enhanced form initialization complete');
                console.log('Cross-team validation enabled');
                console.log('Available project models:', Object.keys(projectModelsData));
                console.log('Total project models:', Object.values(projectModelsData).flat().length);
                
            } catch (error) {
                console.error('Error initializing enhanced form:', error);
            }
        });

        // Enhanced debug function
        function debugProjectModels() {
            console.log('=== ENHANCED DEBUG PROJECT MODELS ===');
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
            
            console.log('Cross-team validation: ENABLED');
            console.log('=== END ENHANCED DEBUG ===');
        }

        // Make debug function available globally
        window.debugProjectModels = debugProjectModels;
    </script>
    
    <?php if (file_exists('assets/js/script.js')): ?>
        <script src="assets/js/script.js"></script>
    <?php endif; ?>
</body>
</html>
