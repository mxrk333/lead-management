<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

try {
    $conn = getDbConnection();
    
    // Build query based on user role
    if ($user['role'] == 'admin') {
        // Admin can see all leads
        $closed_deals_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                                           WHERE status IN ('Closed Deal', 'Requirement Stage', 'Downpayment Stage', 
                                                          'Housing Loan Application', 'Loan Approval', 'Loan Takeout', 
                                                          'House Inspection', 'House Turn Over')");
        $closed_deals_stmt->execute();
        
        $house_turnover_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads WHERE status = 'House Turn Over'");
        $house_turnover_stmt->execute();
        
        $total_value_stmt = $conn->prepare("SELECT SUM(price) as total FROM leads 
                                          WHERE status IN ('Closed Deal', 'Requirement Stage', 'Downpayment Stage', 
                                                         'Housing Loan Application', 'Loan Approval', 'Loan Takeout', 
                                                         'House Inspection', 'House Turn Over')");
        $total_value_stmt->execute();
        
    } elseif ($user['role'] == 'manager' || $user['role'] == 'supervisor') {
        // Get team members
        $team_stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $team_stmt->bind_param("i", $user_id);
        $team_stmt->execute();
        $team_result = $team_stmt->get_result();
        $team_user = $team_result->fetch_assoc();
        $teamId = $team_user['team_id'];
        $team_stmt->close();
        
        $closed_deals_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                                           WHERE status IN ('Closed Deal', 'Requirement Stage', 'Downpayment Stage', 
                                                          'Housing Loan Application', 'Loan Approval', 'Loan Takeout', 
                                                          'House Inspection', 'House Turn Over')
                                           AND user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $closed_deals_stmt->bind_param("i", $teamId);
        $closed_deals_stmt->execute();
        
        $house_turnover_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                                             WHERE status = 'House Turn Over'
                                             AND user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $house_turnover_stmt->bind_param("i", $teamId);
        $house_turnover_stmt->execute();
        
        $total_value_stmt = $conn->prepare("SELECT SUM(price) as total FROM leads 
                                          WHERE status IN ('Closed Deal', 'Requirement Stage', 'Downpayment Stage', 
                                                         'Housing Loan Application', 'Loan Approval', 'Loan Takeout', 
                                                         'House Inspection', 'House Turn Over')
                                          AND user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $total_value_stmt->bind_param("i", $teamId);
        $total_value_stmt->execute();
        
    } else {
        // Agent - only see their own leads
        $closed_deals_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                                           WHERE status IN ('Closed Deal', 'Requirement Stage', 'Downpayment Stage', 
                                                          'Housing Loan Application', 'Loan Approval', 'Loan Takeout', 
                                                          'House Inspection', 'House Turn Over')
                                           AND user_id = ?");
        $closed_deals_stmt->bind_param("i", $user_id);
        $closed_deals_stmt->execute();
        
        $house_turnover_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                                             WHERE status = 'House Turn Over' AND user_id = ?");
        $house_turnover_stmt->bind_param("i", $user_id);
        $house_turnover_stmt->execute();
        
        $total_value_stmt = $conn->prepare("SELECT SUM(price) as total FROM leads 
                                          WHERE status IN ('Closed Deal', 'Requirement Stage', 'Downpayment Stage', 
                                                         'Housing Loan Application', 'Loan Approval', 'Loan Takeout', 
                                                         'House Inspection', 'House Turn Over')
                                          AND user_id = ?");
        $total_value_stmt->bind_param("i", $user_id);
        $total_value_stmt->execute();
    }
    
    // Get results
    $closed_deals_result = $closed_deals_stmt->get_result();
    $closed_deals_row = $closed_deals_result->fetch_assoc();
    $closed_deals = $closed_deals_row['count'];
    
    $house_turnover_result = $house_turnover_stmt->get_result();
    $house_turnover_row = $house_turnover_result->fetch_assoc();
    $house_turnover = $house_turnover_row['count'];
    
    $total_value_result = $total_value_stmt->get_result();
    $total_value_row = $total_value_result->fetch_assoc();
    $total_value = $total_value_row['total'] ? floatval($total_value_row['total']) : 0;
    
    // Close statements
    $closed_deals_stmt->close();
    $house_turnover_stmt->close();
    $total_value_stmt->close();
    $conn->close();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'closed_deals' => $closed_deals,
        'house_turnover' => $house_turnover,
        'total_value' => $total_value
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-conversion-stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
