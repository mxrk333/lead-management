<?php
session_start();
require_once '../config/database.php';

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
    // Get the lead_id and file path before deleting
    $get_receipt_stmt = $conn->prepare("SELECT lead_id, file_path FROM stage_receipts WHERE id = ?");
    $get_receipt_stmt->bind_param("i", $receipt_id);
    $get_receipt_stmt->execute();
    $receipt_result = $get_receipt_stmt->get_result();
    $receipt = $receipt_result->fetch_assoc();
    $get_receipt_stmt->close();
    
    if (!$receipt) {
        http_response_code(404);
        echo json_encode(['error' => 'Receipt not found']);
        exit();
    }
    
    $lead_id = $receipt['lead_id'];
    $file_path = $receipt['file_path'];
    
    // Delete the receipt from database
    $delete_stmt = $conn->prepare("DELETE FROM stage_receipts WHERE id = ?");
    $delete_stmt->bind_param("i", $receipt_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Delete the physical file
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Update the current_dp_stage based on remaining receipts
    // Get the latest tracker for this lead
    $tracker_stmt = $conn->prepare("SELECT id, spot_dp, total_dp_stages FROM downpayment_tracker WHERE lead_id = ? ORDER BY created_at DESC LIMIT 1");
    $tracker_stmt->bind_param("i", $lead_id);
    $tracker_stmt->execute();
    $tracker_result = $tracker_stmt->get_result();
    $tracker = $tracker_result->fetch_assoc();
    $tracker_stmt->close();
    
    if ($tracker) {
        $spot_dp = $tracker['spot_dp'] == 1;
        $total_dp_stages = intval($tracker['total_dp_stages']);
        
        // Calculate current_dp_stage based on actual receipt count
        $current_dp_stage = 0;
        if ($spot_dp) {
            $current_dp_stage = 1; // Spot DP is always stage 1
        } else {
            // Count actual receipts from stage_receipts table
            $receipt_count_stmt = $conn->prepare("SELECT COUNT(*) as total_receipts FROM stage_receipts WHERE lead_id = ? AND stage_type = 'downpayment'");
            $receipt_count_stmt->bind_param("i", $lead_id);
            $receipt_count_stmt->execute();
            $receipt_count_result = $receipt_count_stmt->get_result();
            $total_receipts = $receipt_count_result->fetch_assoc()['total_receipts'];
            $receipt_count_stmt->close();
            
            // Current stage should be at least 1 if there are any receipts, and not exceed total terms
            $current_dp_stage = $total_receipts > 0 ? max(1, min($total_receipts, $total_dp_stages)) : 0;
        }
        
        // Calculate progress rate
        $progress_rate = $total_dp_stages > 0 ? ($current_dp_stage / $total_dp_stages) * 100 : 0;
        
        // Update the tracker with the correct current_dp_stage
        $update_stmt = $conn->prepare("
            UPDATE downpayment_tracker 
            SET current_dp_stage = ?, progress_rate = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->bind_param("idi", $current_dp_stage, $progress_rate, $tracker['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    $conn->close();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Receipt deleted successfully',
        'current_dp_stage' => $current_dp_stage ?? 0
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
