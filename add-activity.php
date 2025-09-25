<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Add superuser function if not exists
if (!function_exists('isSuperUser')) {
    // isSuperUser is provided globally in includes/functions.php
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: leads.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Get form data
$lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
$activity_type = isset($_POST['activity_type']) ? trim($_POST['activity_type']) : '';
$notes = isset($_POST['activity_notes']) ? trim($_POST['activity_notes']) : '';

// Validate required fields
if (empty($lead_id) || empty($activity_type) || empty($notes)) {
    header("Location: lead-details.php?id=$lead_id&error=" . urlencode("Please fill in all required fields."));
    exit();
}

// Get database connection
$conn = getDbConnection();

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get lead details and check permissions
    $lead = getLeadById($lead_id, $user_id, $user['role']);
    if (!$lead) {
        throw new Exception("Lead not found or access denied");
    }
    
    // Check permissions - only lead owner or superuser can add activities
    $canAddActivity = ($lead['user_id'] == $user_id) || isSuperUser($user['username']);
    if (!$canAddActivity) {
        throw new Exception("You don't have permission to add activities to this lead");
    }
    
    // Validate activity type
    $valid_types = array(
        'Call', 'Email', 'Meeting', 'Presentation', 'Follow-up', 'Site Tour',
        'Initial Contact', 'Negotiation', 'Status Change', 'Downpayment Tracker', 'Other'
    );
    
    if (!in_array($activity_type, $valid_types)) {
        throw new Exception("Invalid activity type");
    }
    
    // Add activity to lead_activities table
    $activity_stmt = $conn->prepare("
        INSERT INTO lead_activities (lead_id, user_id, activity_type, notes, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $activity_stmt->bind_param("iiss", $lead_id, $user_id, $activity_type, $notes);
    
    if (!$activity_stmt->execute()) {
        throw new Exception("Failed to add activity: " . $activity_stmt->error);
    }
    
    $activity_id = $activity_stmt->insert_id;
    $activity_stmt->close();
    
    // Update lead's timestamp (only using existing columns)
    $update_stmt = $conn->prepare("UPDATE leads SET updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $lead_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update lead timestamp: " . $update_stmt->error);
    }
    $update_stmt->close();
    
    // Record the modification in lead_modifications table (if table exists)
    try {
        $mod_stmt = $conn->prepare("
            INSERT INTO lead_modifications 
            (lead_id, user_id, modification_type, old_value, new_value, activity_id, created_at)
            VALUES (?, ?, 'activity_added', NULL, ?, ?, NOW())
        ");
        $mod_stmt->bind_param("iisi", $lead_id, $user_id, $activity_type, $activity_id);
        $mod_stmt->execute();
        $mod_stmt->close();
    } catch (Exception $mod_error) {
        // If modifications table doesn't exist, just log and continue
        error_log("Could not record modification: " . $mod_error->getMessage());
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log the activity
    error_log("Activity added successfully: Lead ID $lead_id, Activity: $activity_type, User: {$user['name']}");
    
    header("Location: lead-details.php?id=$lead_id&success=1");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error adding lead activity: " . $e->getMessage());
    header("Location: lead-details.php?id=$lead_id&error=" . urlencode($e->getMessage()));
    exit();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
