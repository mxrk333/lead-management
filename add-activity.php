<?php
session_start();
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

// Get form data
$lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
$activity_type = isset($_POST['activity_type']) ? trim($_POST['activity_type']) : '';
$notes = isset($_POST['activity_notes']) ? trim($_POST['activity_notes']) : '';

// Validate data
if (empty($lead_id) || empty($activity_type) || empty($notes)) {
    header("Location: lead-details.php?id=$lead_id&error=missing_fields");
    exit();
}

// Get database connection
$conn = getDbConnection();

try {
    // Start transaction
    $conn->begin_transaction();

    // Get lead details before modification
    $lead = getLeadById($lead_id, $user_id, $user['role']);
    if (!$lead) {
        throw new Exception("Access denied to this lead");
    }

    // Validate activity type
    $valid_types = array('Call', 'Email', 'Meeting', 'Presentation', 'Follow-up', 'Site Tour', 
                        'Initial Contact', 'Negotiation', 'Status Change', 'Other');
    if (!in_array($activity_type, $valid_types)) {
        throw new Exception("Invalid activity type");
    }

    // Add activity
    $activity_stmt = $conn->prepare("INSERT INTO lead_activities (lead_id, user_id, activity_type, notes) VALUES (?, ?, ?, ?)");
    $activity_stmt->bind_param("iiss", $lead_id, $user_id, $activity_type, $notes);
    
    if (!$activity_stmt->execute()) {
        throw new Exception("Failed to add activity");
    }
    $activity_id = $activity_stmt->insert_id;
    $activity_stmt->close();

    // Update lead's last activity timestamp and modification tracking
    $update_stmt = $conn->prepare("
        UPDATE leads 
        SET 
            updated_at = NOW(),
            last_activity_date = NOW(),
            last_activity_type = ?,
            last_modified_by = ?
        WHERE id = ?
    ");
    $update_stmt->bind_param("sii", $activity_type, $user_id, $lead_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update lead timestamp");
    }
    $update_stmt->close();

    // Record the modification in lead_modifications table
    $mod_stmt = $conn->prepare("
        INSERT INTO lead_modifications 
        (lead_id, user_id, modification_type, old_value, new_value, activity_id, created_at) 
        VALUES (?, ?, 'activity_added', NULL, ?, ?, NOW())
    ");
    $mod_stmt->bind_param("iisi", $lead_id, $user_id, $activity_type, $activity_id);
    $mod_stmt->execute();
    $mod_stmt->close();

    // Create notification for team members
    if ($user['team_id']) {
        $notify_stmt = $conn->prepare("
            SELECT DISTINCT u.id 
            FROM users u 
            WHERE u.team_id = ? AND u.id != ?
        ");
        $notify_stmt->bind_param("ii", $user['team_id'], $user_id);
        $notify_stmt->execute();
        $result = $notify_stmt->get_result();
        
        while ($team_member = $result->fetch_assoc()) {
            $notification_title = "New Lead Activity";
            $notification_message = "{$user['name']} added a {$activity_type} activity for {$lead['client_name']}";
            createNotification($team_member['id'], $notification_title, $notification_message, 'lead_activity', $lead_id, 'lead');
        }
        $notify_stmt->close();
    }

    // Commit transaction
    $conn->commit();
    
    header("Location: lead-details.php?id=$lead_id&success=activity_added");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error adding lead activity: " . $e->getMessage());
    header("Location: lead-details.php?id=$lead_id&error=" . urlencode($e->getMessage()));
    exit();
} finally {
    $conn->close();
}
?>
