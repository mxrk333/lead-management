<?php
session_start();

// Set Philippine timezone at the very beginning
date_default_timezone_set('Asia/Manila');

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// isSuperUser is provided globally in includes/functions.php

// Function to get current Philippine time
function getCurrentPhilippineTime($format = 'Y-m-d H:i:s') {
    $date = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $date->format($format);
}

// Function to format datetime for Philippine timezone
function formatPhilippineDateTime($datetime, $format = 'M j, Y g:i A') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    try {
        // Create DateTime object and set to Philippine timezone
        $date = new DateTime($datetime);
        $date->setTimezone(new DateTimeZone('Asia/Manila'));
        return $date->format($format);
    } catch (Exception $e) {
        return $datetime; // Return original if formatting fails
    }
}

// Enhanced database connection with timezone setting
function getDbConnectionWithTimezone() {
    $conn = getDbConnection();
    if ($conn) {
        // Set MySQL session timezone to Philippine time
        $conn->query("SET time_zone = '+08:00'");
    }
    return $conn;
}

// Function to automatically log status changes with Philippine time
function logStatusChange($lead_id, $user_id, $old_status, $new_status) {
    try {
        $conn = getDbConnectionWithTimezone();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        // Create activity log entry for status change
        $activity_notes = "Status changed from '{$old_status}' to '{$new_status}'";
        $current_time = getCurrentPhilippineTime();
        
        $stmt = $conn->prepare("
            INSERT INTO lead_activities (lead_id, user_id, activity_type, notes, created_at) 
            VALUES (?, ?, 'Status Change', ?, ?)
        ");
        
        if ($stmt) {
            $stmt->bind_param("iiss", $lead_id, $user_id, $activity_notes, $current_time);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                error_log("Status change logged with Philippine time: Lead ID $lead_id, User ID $user_id, $old_status -> $new_status at $current_time");
                return true;
            }
        }
        
        $conn->close();
        return false;
        
    } catch (Exception $e) {
        error_log("Error logging status change: " . $e->getMessage());
        return false;
    }
}

// Function to log lead modifications with Philippine time
function logLeadModification($lead_id, $user_id, $field_name, $old_value, $new_value, $activity_id = null) {
    try {
        $conn = getDbConnectionWithTimezone();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $current_time = getCurrentPhilippineTime();
        
        $stmt = $conn->prepare("
            INSERT INTO lead_modifications (lead_id, user_id, field_name, old_value, new_value, activity_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt) {
            $stmt->bind_param("iisssss", $lead_id, $user_id, $field_name, $old_value, $new_value, $activity_id, $current_time);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                error_log("Lead modification logged with Philippine time: Lead ID $lead_id, Field: $field_name, $old_value -> $new_value at $current_time");
                return true;
            }
        }
        
        $conn->close();
        return false;
        
    } catch (Exception $e) {
        error_log("Error logging lead modification: " . $e->getMessage());
        return false;
    }
}

// Check if lead ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: leads.php?error=invalid_lead");
    exit();
}

$lead_id = intval($_GET['id']);

// Get lead information - FIXED: Now passing all required parameters
$lead = getLeadById($lead_id, $user_id, $user['role']);
if (!$lead) {
    header("Location: leads.php?error=lead_not_found");
    exit();
}

// Store original lead data for comparison
$original_lead = $lead;

// Check if user can edit this lead
$canEdit = isSuperUser($user['username']) || ($lead['user_id'] == $user_id);
if (!$canEdit) {
    header("Location: leads.php?error=access_denied");
    exit();
}

// Get dropdown data
$developers = getDevelopers();
$projectModels = getProjectModels();

// Enhanced getLeadSources function with "Others" option
function getLeadSourcesWithOthers() {
    try {
        $conn = getDbConnectionWithTimezone();
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
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $sources = [];
    }
    
    // If no sources found from database, provide default values
    if (empty($sources)) {
        $defaultSources = [
            'Facebook Groups', 'KKK', 'Facebook Ads', 'TikTok ads', 'Google Ads', 
            'Facebook live', 'Referral', 'Teleprospecting', 'Video Message', 
            'Organic Posting', 'Email Marketing', 'Follow up', 'Manning', 
            'Walk in', 'Flyering', 'Chat messaging', 'Property Listing', 
            'Landing Page', 'Networking Events', 'Organic Sharing', 
            'Youtube Marketing', 'LinkedIn', 'Open House', 'Facebook Page'
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

$leadSources = getLeadSourcesWithOthers();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Collect and sanitize form data
        $clientName = trim($_POST['client_name']);
        // Use the full international phone number if available, otherwise fallback to regular phone
        $phone = isset($_POST['phone_full']) && !empty(trim($_POST['phone_full'])) ? trim($_POST['phone_full']) : (isset($_POST['phone']) ? trim($_POST['phone']) : '');
        $email = trim($_POST['email']);
        $facebook = trim($_POST['facebook']);
        $linkedin = trim($_POST['linkedin']);
        $temperature = trim($_POST['temperature']);
        $status = trim($_POST['status']);
        $leadClassification = trim($_POST['lead_classification']);
        
        // Handle "Others" option for developer/project
        $developer = trim($_POST['developer']);
        if ($developer === 'Others' && isset($_POST['developer_other']) && !empty(trim($_POST['developer_other']))) {
            $developer = trim($_POST['developer_other']);
        }
        
        // Handle "Others" option for project model
        $projectModel = trim($_POST['project_model']);
        if ($projectModel === 'Others' && isset($_POST['project_model_other']) && !empty(trim($_POST['project_model_other']))) {
            $projectModel = trim($_POST['project_model_other']);
        }
        
        $priceRaw = trim($_POST['price']);
        $remarks = trim($_POST['remarks']);
        
        // Handle "Others" option for lead source
        $source = trim($_POST['source']);
        if ($source === 'Others' && isset($_POST['source_other']) && !empty(trim($_POST['source_other']))) {
            $source = trim($_POST['source_other']);
        }
        
        // Clean and convert price
        $price = str_replace([',', ' '], '', $priceRaw);
        $price = floatval($price);
        
        // Validation
        $errors = [];
        
        if (empty($clientName)) {
            $errors[] = "Client name is required";
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address format";
        }
        
        if (empty($temperature)) {
            $errors[] = "Temperature is required";
        }
        
        if (empty($status)) {
            $errors[] = "Status is required";
        }
        
        if (empty($developer)) {
            $errors[] = "Project is required";
        }
        
        if (empty($projectModel)) {
            $errors[] = "Project model is required";
        }
        
        if ($price <= 0) {
            $errors[] = "Valid price is required";
        }
        
        if (empty($source)) {
            $errors[] = "Lead source is required";
        }
        
        if (empty($leadClassification)) {
            $errors[] = "Lead classification is required";
        }
        
        if (empty($errors)) {
            // Read extended demographics and AI fields
            $city = isset($_POST['city']) ? trim($_POST['city']) : null;
            $jobTitle = isset($_POST['job_title']) ? trim($_POST['job_title']) : null;
            $relationshipStatus = isset($_POST['relationship_status']) ? trim($_POST['relationship_status']) : null;
            $aiSummary = isset($_POST['ai_summary']) ? trim($_POST['ai_summary']) : null;
            $leadQuality = isset($_POST['lead_quality']) && !empty($_POST['lead_quality']) ? trim($_POST['lead_quality']) : null;
            $recommendedAction = isset($_POST['recommended_action']) ? trim($_POST['recommended_action']) : null;
            $googleSheetRowId = isset($_POST['google_sheet_row_id']) ? trim($_POST['google_sheet_row_id']) : null;

            // ENHANCED: Check for status change and log it automatically
            $statusChanged = ($original_lead['status'] !== $status);
            
            // Update lead in database
            $result = updateLead(
                $lead_id, $clientName, $phone, $email, $facebook, $linkedin, 
                $temperature, $status, $source, $leadClassification, $developer, $projectModel, $price, $remarks,
                $city, $jobTitle, $relationshipStatus, $aiSummary, $leadQuality, $recommendedAction, $googleSheetRowId
            );
            
            if ($result) {
                // AUTOMATIC STATUS CHANGE LOGGING with Philippine time
                if ($statusChanged) {
                    $logResult = logStatusChange($lead_id, $user_id, $original_lead['status'], $status);
                    if ($logResult) {
                        error_log("Automatic status change logged successfully for lead ID: $lead_id with Philippine time");
                    } else {
                        error_log("Failed to log automatic status change for lead ID: $lead_id");
                    }
                }
                
                // ENHANCED: Log other significant field changes with Philippine time
                $fieldsToTrack = [
                    'client_name' => [$original_lead['client_name'], $clientName],
                    'phone' => [$original_lead['phone'], $phone],
                    'email' => [$original_lead['email'], $email],
                    'temperature' => [$original_lead['temperature'], $temperature],
                    'source' => [$original_lead['source'], $source],
                    'lead_classification' => [$original_lead['lead_classification'], $leadClassification],
                    'developer' => [$original_lead['developer'], $developer],
                    'project_model' => [$original_lead['project_model'], $projectModel],
                    'price' => [$original_lead['price'], $price],
                    'city' => [$original_lead['city'] ?? '', $city],
                    'job_title' => [$original_lead['job_title'] ?? '', $jobTitle],
                    'relationship_status' => [$original_lead['relationship_status'] ?? '', $relationshipStatus],
                    'lead_quality' => [$original_lead['lead_quality'] ?? '', $leadQuality],
                    'ai_summary' => [$original_lead['ai_summary'] ?? '', $aiSummary],
                    'recommended_action' => [$original_lead['recommended_action'] ?? '', $recommendedAction]
                ];
                
                foreach ($fieldsToTrack as $fieldName => $values) {
                    $oldValue = $values[0];
                    $newValue = $values[1];
                    
                    // Only log if the value actually changed
                    if ($oldValue != $newValue) {
                        logLeadModification($lead_id, $user_id, $fieldName, $oldValue, $newValue);
                    }
                }
                
                $_SESSION['success_message'] = "Lead updated successfully" . ($statusChanged ? " and status change logged with Philippine time" : "");
                header("Location: leads.php");
                exit();
            } else {
                $error = "Failed to update lead";
            }
        } else {
            $error = implode(", ", $errors);
        }
        
    } catch (Exception $e) {
        $error = "Failed to update lead: " . $e->getMessage();
        error_log("Edit lead error: " . $e->getMessage());
    }
}

// Check if current source is not in the predefined list (custom source)
$isCustomSource = true;
$currentSourceValue = 'Others'; // Default to Others for custom sources

foreach ($leadSources as $sourceOption) {
    if ($sourceOption['name'] === $lead['source']) {
        $isCustomSource = false;
        $currentSourceValue = $lead['source'];
        break;
    }
}

// If it's a custom source, we need to set up the form to show "Others" selected
// and populate the custom input field
if ($isCustomSource) {
    $customSourceValue = $lead['source'];
} else {
    $customSourceValue = '';
}

// Check if current developer is custom
$isCustomDeveloper = true;
foreach ($developers as $dev) {
    if ($dev['name'] === $lead['developer']) {
        $isCustomDeveloper = false;
        break;
    }
}

// Check if current project model is custom
$isCustomProjectModel = true;
foreach ($projectModels as $model) {
    if ($model['name'] === $lead['project_model'] && $model['developer_name'] === $lead['developer']) {
        $isCustomProjectModel = false;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lead - Inners SPARC Realty Corporation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- International Phone Input -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>
    <style>
        /* Enhanced styles matching add-lead.php design */
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
        
        .edit-lead-page {
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

        /* NEW: Status change indicator with Philippine time notice */
        .status-change-indicator {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #92400e;
        }

        .status-change-indicator i {
            margin-right: 0.5rem;
            color: #f59e0b;
        }

        .timezone-notice {
            background-color: #e0f2fe;
            border: 1px solid #0288d1;
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #01579b;
        }

        .timezone-notice i {
            margin-right: 0.5rem;
            color: #0288d1;
        }

        /* International phone input styles - Proper separated design */
        .iti {
            width: 100%;
            position: relative;
        }
        
        .iti__country-list {
            z-index: 9999;
            max-height: 250px;
            width: 400px;
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

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .edit-lead-page {
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
            .edit-lead-page {
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
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="edit-lead-page">
                <div class="page-header">
                    <h2>Edit Lead</h2>
                    <a href="leads.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Leads</a>
                </div>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> 
                    <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> 
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <!-- NEW: Philippine timezone notice -->
                <div class="timezone-notice">
                    <i class="fas fa-clock"></i>
                    <strong>Philippine Time Zone:</strong> All timestamps are recorded in Philippine Standard Time (UTC+8). Current time: <?php echo formatPhilippineDateTime(getCurrentPhilippineTime(), 'M j, Y g:i A'); ?>
                </div>

                <!-- NEW: Status change notification -->
                <div class="status-change-indicator">
                    <i class="fas fa-info-circle"></i>
                    <strong>Automatic Activity Logging:</strong> Any status changes will be automatically recorded in the lead activity log with Philippine timestamp and your name.
                </div>
                
                <div class="required-note">Fields marked with <span>*</span> are required</div>
                
                <form method="POST" action="" class="lead-form" id="editLeadForm">
                    <div class="form-section">
                        <h3>Client Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="client_name">Client Name</label>
                                <input type="text" id="client_name" name="client_name" 
                                       value="<?php echo htmlspecialchars($lead['client_name']); ?>"
                                       placeholder="Enter client's full name" maxlength="100" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="optional-field">(Optional)</span></label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($lead['phone']); ?>"
                                       placeholder="Enter phone number">
                                <input type="hidden" id="phone_full" name="phone_full" value="">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="optional-field">(Optional)</span></label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($lead['email']); ?>"
                                       placeholder="client@example.com" maxlength="100">
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="source">Lead Source</label>
                                <div class="autocomplete-container">
                                    <input type="text" id="source" name="source" required 
                                           class="autocomplete-input" 
                                           value="<?php echo $isCustomSource ? htmlspecialchars($customSourceValue) : htmlspecialchars($currentSourceValue); ?>"
                                           placeholder="Type to search or select lead source..."
                                           autocomplete="off">
                                    <div id="source-dropdown" class="autocomplete-dropdown"></div>
                                </div>
                                <input type="hidden" id="source_original" name="source_original" value="<?php echo htmlspecialchars($lead['source']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="lead_classification">Lead Classification</label>
                                <select id="lead_classification" name="lead_classification" required>
                                    <option value="">Select Lead Classification</option>
                                    <option value="Locally/Internationally Employed" <?php echo (isset($lead['lead_classification']) && $lead['lead_classification'] === 'Locally/Internationally Employed') ? 'selected' : ''; ?>>
                                        Locally Employed
                                    </option>
                                    <option value="OFW" <?php echo (isset($lead['lead_classification']) && $lead['lead_classification'] === 'OFW') ? 'selected' : ''; ?>>
                                        OFW
                                    </option>
                                    <option value="Self employed" <?php echo (isset($lead['lead_classification']) && $lead['lead_classification'] === 'Self employed') ? 'selected' : ''; ?>>
                                        Self employed
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="facebook">Facebook Profile <span class="optional-field">(Optional)</span></label>
                                <input type="url" id="facebook" name="facebook" 
                                       value="<?php echo htmlspecialchars($lead['facebook']); ?>"
                                       placeholder="Facebook profile URL" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label for="linkedin">LinkedIn Profile <span class="optional-field">(Optional)</span></label>
                                <input type="url" id="linkedin" name="linkedin" 
                                       value="<?php echo htmlspecialchars($lead['linkedin']); ?>"
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
                                    <option value="Hot" <?php echo ($lead['temperature'] === 'Hot') ? 'selected' : ''; ?>>Hot</option>
                                    <option value="Warm" <?php echo ($lead['temperature'] === 'Warm') ? 'selected' : ''; ?>>Warm</option>
                                    <option value="Cold" <?php echo ($lead['temperature'] === 'Cold') ? 'selected' : ''; ?>>Cold</option>
                                </select>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="status">Status</label>
                                <select id="status" name="status" required onchange="highlightStatusChange(this.value)">
                                    <option value="">Select Status</option>
                                    <?php 
                                    $statuses = [
                                        'Inquiry', 'Presentation Stage', 'Negotiation', 'Site Tour',
                                         'Requirement Stage', 'Downpayment Stage', 'Housing Loan Application',
                                        'Loan Approval', 'Loan Takeout', 'House Inspection', 'House Turn Over', 'Closed Deal', 'Lost'   
                                    ];
                                    foreach ($statuses as $status_option): ?>
                                    <option value="<?php echo htmlspecialchars($status_option); ?>"
                                            <?php echo ($lead['status'] === $status_option) ? 'selected' : ''; ?>>
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
                                           value="<?php echo htmlspecialchars($lead['developer']); ?>"
                                           placeholder="Type to search or select project..."
                                           autocomplete="off">
                                    <button type="button" class="autocomplete-clear" id="developer-clear" title="Clear project" style="display: <?php echo $lead['developer'] ? 'flex' : 'none'; ?>">
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
                                    <?php if (!empty($lead['project_model'])): ?>
                                        <option value="<?php echo htmlspecialchars($lead['project_model']); ?>" selected>
                                            <?php echo htmlspecialchars($lead['project_model']); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <div class="others-input" id="project-model-others">
                                    <label for="project_model_other">Specify House Model</label>
                                    <input type="text" id="project_model_other" name="project_model_other" 
                                           value="<?php echo htmlspecialchars($isCustomProjectModel ? ($lead['project_model'] ?? '') : ''); ?>"
                                           placeholder="Enter house model name" maxlength="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="price">Total Selling Price (PHP)</label>
                                <input type="text" id="price" name="price" 
                                       value="<?php echo number_format($lead['price'], 2); ?>"
                                       placeholder="e.g. 1,000,000.00" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="remarks">Remarks <span class="optional-field">(Optional)</span></label>
                                <textarea id="remarks" name="remarks" rows="4" maxlength="1000"
                                          placeholder="Add any additional notes or comments about this lead"><?php echo htmlspecialchars($lead['remarks']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>AI Insights & Facebook Demographics</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City / Location <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="city" name="city" 
                                       value="<?php echo htmlspecialchars($lead['city'] ?? ''); ?>"
                                       placeholder="e.g. Cavite" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label for="job_title">Job Title / Occupation <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="job_title" name="job_title" 
                                       value="<?php echo htmlspecialchars($lead['job_title'] ?? ''); ?>"
                                       placeholder="e.g. Engineer" maxlength="255">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="relationship_status">Relationship Status <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="relationship_status" name="relationship_status" 
                                       value="<?php echo htmlspecialchars($lead['relationship_status'] ?? ''); ?>"
                                       placeholder="e.g. Married, Single" maxlength="100">
                            </div>
                            
                            <div class="form-group">
                                <label for="lead_quality">AI Lead Quality <span class="optional-field">(Optional)</span></label>
                                <select id="lead_quality" name="lead_quality">
                                    <option value="" <?php echo empty($lead['lead_quality']) ? 'selected' : ''; ?>>Not Scored</option>
                                    <option value="Low" <?php echo ($lead['lead_quality'] ?? '') === 'Low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="Medium" <?php echo ($lead['lead_quality'] ?? '') === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="High" <?php echo ($lead['lead_quality'] ?? '') === 'High' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="ai_summary">AI Summary <span class="optional-field">(Optional)</span></label>
                                <textarea id="ai_summary" name="ai_summary" rows="3" placeholder="AI-generated profile summary..."><?php echo htmlspecialchars($lead['ai_summary'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="recommended_action">AI Recommended Action <span class="optional-field">(Optional)</span></label>
                                <textarea id="recommended_action" name="recommended_action" rows="3" placeholder="AI-generated recommended sales action..."><?php echo htmlspecialchars($lead['recommended_action'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="google_sheet_row_id">Google Sheet Row ID / Import Reference <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="google_sheet_row_id" name="google_sheet_row_id" 
                                       value="<?php echo htmlspecialchars($lead['google_sheet_row_id'] ?? ''); ?>"
                                       placeholder="e.g. sheet_row_12" maxlength="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="leads.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="fas fa-save"></i> Update Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Store original status for comparison
        const originalStatus = '<?php echo htmlspecialchars($lead['status']); ?>';
        
        // Initialize international telephone input
        let iti;
        
        // Project models data from PHP
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
        } catch (error) {
            console.error('Error loading project models data:', error);
            projectModelsData = {};
        }
        
        // Current lead data
        const currentLead = {
            developer: '<?php echo htmlspecialchars($lead['developer']); ?>',
            project_model: '<?php echo htmlspecialchars($lead['project_model']); ?>',
            source: '<?php echo htmlspecialchars($lead['source']); ?>'
        };
        
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
            
            // Set initial value if exists
            const currentPhone = '<?php echo htmlspecialchars($lead['phone']); ?>';
            if (currentPhone) {
                input.value = currentPhone;
            }
            
            // On form submit, set the hidden field value to the full international number
            document.querySelector("#editLeadForm").addEventListener("submit", function() {
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
                        
                        // Load house models for this project (do not preselect existing on user-initiated change)
                        loadProjectModelsFromAPI(project.name, false);
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
                                // Load house models for this project (do not preselect existing on user-initiated change)
                                loadProjectModelsFromAPI(value, false);
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
        
        // NEW: Function to highlight status changes with Philippine time notice
        function highlightStatusChange(newStatus) {
            const statusSelect = document.getElementById('status');
            const indicator = document.querySelector('.status-change-indicator');
            
            if (newStatus !== originalStatus && newStatus !== '') {
                statusSelect.style.borderColor = '#f59e0b';
                statusSelect.style.backgroundColor = '#fef3c7';
                indicator.innerHTML = `
                    <i class="fas fa-exchange-alt"></i>
                    <strong>Status Change Detected:</strong> Changing from "${originalStatus}" to "${newStatus}". This will be automatically logged with Philippine time (UTC+8) in the activity timeline.
                `;
                indicator.style.backgroundColor = '#fef3c7';
                indicator.style.borderColor = '#f59e0b';
            } else {
                statusSelect.style.borderColor = '#d1d5db';
                statusSelect.style.backgroundColor = '#fff';
                indicator.innerHTML = `
                    <i class="fas fa-info-circle"></i>
                    <strong>Automatic Activity Logging:</strong> Any status changes will be automatically recorded in the lead activity log with Philippine timestamp and your name.
                `;
                indicator.style.backgroundColor = '#f0f7ff';
                indicator.style.borderColor = '#bae0ff';
            }
        }
        
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
        
        // Function to load project models from API based on selected project (developer)
        function loadProjectModelsFromAPI(projectName, preselectExisting = true) {
            const projectModelSelect = document.getElementById('project_model');
            if (!projectModelSelect) {
                console.error('Project model select element not found');
                return;
            }

            // Toggle developer others input
            toggleDeveloperOthers(projectName);

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
                    // Reset options
                    projectModelSelect.innerHTML = '<option value="">Select House Model</option>';
                    projectModelSelect.disabled = false;

                    if (data.success && Array.isArray(data.models) && data.models.length > 0) {
                        // Populate models from API
                        data.models.forEach(model => {
                            const option = document.createElement('option');
                            // API returns model.name; keep using name as the select value/text
                            option.value = model.name;
                            option.textContent = model.name;
                            projectModelSelect.appendChild(option);
                        });
                    } else if (data.success && !data.project_found) {
                        const noModelsOption = document.createElement('option');
                        noModelsOption.value = '';
                        noModelsOption.textContent = 'No models available (New project)';
                        noModelsOption.disabled = true;
                        projectModelSelect.appendChild(noModelsOption);
                    } else {
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

                    if (preselectExisting) {
                        // Preselect the existing model from the current lead if available
                        const existingModel = currentLead && currentLead.project_model ? String(currentLead.project_model).trim() : '';
                        if (existingModel) {
                            const norm = s => String(s || '').trim().toLowerCase();
                            const options = Array.from(projectModelSelect.options);
                            // First try strict normalized equality
                            let match = options.find(opt => norm(opt.value) === norm(existingModel));
                            if (!match) {
                                // Try matching on textContent as well
                                match = options.find(opt => norm(opt.textContent) === norm(existingModel));
                            }
                            if (match && match.value) {
                                projectModelSelect.value = match.value;
                                toggleProjectModelOthers(match.value);
                            } else {
                                // Not in list: inject a custom option that mirrors the saved value
                                const customOption = document.createElement('option');
                                customOption.value = existingModel;
                                customOption.textContent = existingModel;
                                customOption.setAttribute('data-custom', '1');
                                projectModelSelect.insertBefore(customOption, projectModelSelect.firstChild.nextSibling);
                                projectModelSelect.value = existingModel;
                                toggleProjectModelOthers(existingModel);
                            }
                        }
                    } else {
                        // On project change by user, do not preselect anything and hide the Others input
                        toggleProjectModelOthers('');
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

                    // Even on error, show the existing model by injecting it as a selectable option
                    const existingModel = currentLead && currentLead.project_model ? String(currentLead.project_model).trim() : '';
                    if (existingModel) {
                        const customOption = document.createElement('option');
                        customOption.value = existingModel;
                        customOption.textContent = existingModel;
                        customOption.setAttribute('data-custom', '1');
                        projectModelSelect.insertBefore(customOption, projectModelSelect.firstChild.nextSibling);
                        projectModelSelect.value = existingModel;
                        toggleProjectModelOthers(existingModel);
                    }
                });
        }

        // Legacy function kept for callers; delegate to API-based loader
        function loadProjectModels(developer, preselectExisting = false) {
            loadProjectModelsFromAPI(developer, preselectExisting);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize components
            initIntlTelInput();
            initLeadSourceAutocomplete();
            initProjectAutocomplete();
            initPriceFormatting();
            
            // Initialize project models if developer is already selected
            const developerInput = document.getElementById('developer');
            if (developerInput && developerInput.value) {
                // Initial page load: preselect existing model if available
                loadProjectModelsFromAPI(developerInput.value, true);
            }
            
            // Price formatting
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
            
            // Phone number validation
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/\D/g, '');
                    if (this.value.length > 11) {
                        this.value = this.value.substring(0, 11);
                    }
                });
            }
            
            // Form submission handling with status change confirmation
            const form = document.getElementById('editLeadForm');
            const saveBtn = document.getElementById('saveBtn');
            
            if (form && saveBtn) {
                form.addEventListener('submit', function(e) {
                    const currentStatus = document.getElementById('status').value;
                    
                    // Show confirmation if status is changing
                    if (currentStatus !== originalStatus && currentStatus !== '') {
                        const confirmMessage = `You are changing the status from "${originalStatus}" to "${currentStatus}". This change will be automatically logged with Philippine time (UTC+8) in the lead activity. Do you want to continue?`;
                        
                        if (!confirm(confirmMessage)) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating with Philippine Time...';
                    saveBtn.disabled = true;
                    
                    // Clean price value for submission
                    if (priceInput) {
                        const price = priceInput.value.replace(/,/g, '');
                        priceInput.value = price;
                    }
                });
            }
        });
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>