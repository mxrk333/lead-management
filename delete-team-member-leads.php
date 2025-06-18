<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Check if user has permission (manager or admin only)
if ($user['role'] != 'manager' && $user['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$target_user_id = isset($input['user_id']) ? intval($input['user_id']) : null;
$year = isset($input['year']) ? intval($input['year']) : date('Y');
$quarter = isset($input['quarter']) ? intval($input['quarter']) : ceil(date('n') / 3);
$month = isset($input['month']) ? intval($input['month']) : 0;
$team_id = isset($input['team_id']) ? $input['team_id'] : 'all';

if (!$target_user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

// Get database connection
$conn = getDbConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Calculate date range
if ($month > 0) {
    // If a specific month is selected
    $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$year-$month-01"));
} else {
    // Calculate quarter date range
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3;
    $start_date = "$year-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = "$year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$year-$end_month-01"));
}

// Verify the target user exists and check permissions
$target_user = getUserById($target_user_id);
if (!$target_user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Check if manager has permission to delete leads for this user
if ($user['role'] == 'manager') {
    // Managers can only delete leads for users in their team
    if ($target_user['team_id'] != $user['team_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only delete leads for users in your team']);
        exit();
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // First, get the lead IDs to delete
    $leads_query = "
        SELECT id FROM leads 
        WHERE user_id = ? AND created_at BETWEEN ? AND ?
    ";
    
    $stmt = $conn->prepare($leads_query);
    $stmt->bind_param("iss", $target_user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lead_ids = [];
    while ($row = $result->fetch_assoc()) {
        $lead_ids[] = $row['id'];
    }
    
    if (empty($lead_ids)) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'No leads found for the specified time period']);
        exit();
    }
    
    // Delete lead activities first (foreign key constraint)
    $activities_query = "DELETE FROM lead_activities WHERE lead_id IN (" . implode(',', array_fill(0, count($lead_ids), '?')) . ")";
    $stmt = $conn->prepare($activities_query);
    
    // Create parameter array for binding
    $params = array_merge([str_repeat('i', count($lead_ids))], $lead_ids);
    call_user_func_array([$stmt, 'bind_param'], $params);
    $stmt->execute();
    
    // Delete leads
    $delete_query = "DELETE FROM leads WHERE id IN (" . implode(',', array_fill(0, count($lead_ids), '?')) . ")";
    $stmt = $conn->prepare($delete_query);
    
    // Create parameter array for binding
    $params = array_merge([str_repeat('i', count($lead_ids))], $lead_ids);
    call_user_func_array([$stmt, 'bind_param'], $params);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Successfully deleted ' . count($lead_ids) . ' leads',
        'deleted_count' => count($lead_ids)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?> 