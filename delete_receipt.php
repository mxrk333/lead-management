<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if receipt_id is provided
if (!isset($_POST['receipt_id']) || empty($_POST['receipt_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Receipt ID is required']);
    exit();
}

$receipt_id = intval($_POST['receipt_id']);
$conn = getDbConnection();

try {
    // First, get the receipt information to verify it exists and get file path
    $get_receipt_stmt = $conn->prepare("SELECT sr.*, l.id as lead_id FROM stage_receipts sr JOIN leads l ON sr.lead_id = l.id WHERE sr.id = ?");
    if (!$get_receipt_stmt) {
        throw new Exception("Failed to prepare receipt query: " . $conn->error);
    }
    
    $get_receipt_stmt->bind_param("i", $receipt_id);
    if (!$get_receipt_stmt->execute()) {
        throw new Exception("Failed to execute receipt query: " . $get_receipt_stmt->error);
    }
    
    $receipt_result = $get_receipt_stmt->get_result();
    $receipt = $receipt_result->fetch_assoc();
    $get_receipt_stmt->close();
    
    if (!$receipt) {
        http_response_code(404);
        echo json_encode(['error' => 'Receipt not found']);
        exit();
    }
    
    // Check if user has permission to delete this receipt
    // Get user info to check permissions
    $user_id = $_SESSION['user_id'];
    $user_stmt = $conn->prepare("SELECT role, username FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    $user_stmt->close();
    
    // Include functions to check permissions
    require_once 'includes/functions.php';
    
    // Check if user can delete this receipt
    $can_delete = false;
    
    if (isSuperUser($user['username'])) {
        $can_delete = true; // Superusers can delete any receipt
    } elseif ($user['role'] === 'agent') {
        // Agents can only delete receipts for their own leads
        $can_delete = ($receipt['lead_id'] == $user_id);
    } elseif (in_array($user['role'], ['supervisor', 'manager'])) {
        // Supervisors/managers can delete receipts for their team members
        $team_stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $team_stmt->bind_param("i", $user_id);
        $team_stmt->execute();
        $team_result = $team_stmt->get_result();
        $user_team = $team_result->fetch_assoc();
        $team_stmt->close();
        
        if ($user_team) {
            $lead_team_stmt = $conn->prepare("SELECT team_id FROM users WHERE id = (SELECT user_id FROM leads WHERE id = ?)");
            $lead_team_stmt->bind_param("i", $receipt['lead_id']);
            $lead_team_stmt->execute();
            $lead_team_result = $lead_team_stmt->get_result();
            $lead_team = $lead_team_result->fetch_assoc();
            $lead_team_stmt->close();
            
            $can_delete = ($user_team['team_id'] == $lead_team['team_id']);
        }
    }
    
    if (!$can_delete) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to delete this receipt']);
        exit();
    }
    
    // Delete the receipt from database
    $delete_stmt = $conn->prepare("DELETE FROM stage_receipts WHERE id = ?");
    if (!$delete_stmt) {
        throw new Exception("Failed to prepare delete query: " . $conn->error);
    }
    
    $delete_stmt->bind_param("i", $receipt_id);
    if (!$delete_stmt->execute()) {
        throw new Exception("Failed to delete receipt: " . $delete_stmt->error);
    }
    
    $affected_rows = $delete_stmt->affected_rows;
    $delete_stmt->close();
    
    if ($affected_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Receipt not found or already deleted']);
        exit();
    }
    
    // Delete the physical file if it exists
    $file_path = dirname(__FILE__) . '/' . $receipt['file_path'];
    if (file_exists($file_path)) {
        if (!unlink($file_path)) {
            // Log the error but don't fail the operation since the database record is already deleted
            error_log("Warning: Failed to delete physical file: " . $file_path);
        }
    }
    
    // Update the current_dp_stage for the lead since receipt count changed
    $lead_id = $receipt['lead_id'];
    
    // Get the latest tracker for this lead
    $tracker_stmt = $conn->prepare("SELECT id, spot_dp, total_dp_stages FROM downpayment_tracker WHERE lead_id = ? ORDER BY created_at DESC LIMIT 1");
    $tracker_stmt->bind_param("i", $lead_id);
    $tracker_stmt->execute();
    $tracker_result = $tracker_stmt->get_result();
    $tracker = $tracker_result->fetch_assoc();
    $tracker_stmt->close();
    
    if ($tracker) {
        // Count remaining receipts
        $receipt_count_stmt = $conn->prepare("SELECT COUNT(*) as total_receipts FROM stage_receipts WHERE lead_id = ? AND stage_type = 'downpayment'");
        $receipt_count_stmt->bind_param("i", $lead_id);
        $receipt_count_stmt->execute();
        $receipt_count_result = $receipt_count_stmt->get_result();
        $total_receipts = $receipt_count_result->fetch_assoc()['total_receipts'];
        $receipt_count_stmt->close();
        
        // Calculate new current_dp_stage
        $current_dp_stage = 0;
        if ($tracker['spot_dp']) {
            $current_dp_stage = 1; // Spot DP is always stage 1
        } else {
            $current_dp_stage = $total_receipts > 0 ? max(1, min($total_receipts, $tracker['total_dp_stages'])) : 0;
        }
        
        // Update the tracker with new current_dp_stage
        $update_stmt = $conn->prepare("UPDATE downpayment_tracker SET current_dp_stage = ?, progress_rate = (? / total_dp_stages) * 100 WHERE id = ?");
        $update_stmt->bind_param("idi", $current_dp_stage, $current_dp_stage, $tracker['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    $conn->close();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Receipt deleted successfully',
        'lead_id' => $lead_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
