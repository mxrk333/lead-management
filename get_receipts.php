<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if lead_id is provided
if (!isset($_GET['lead_id']) || empty($_GET['lead_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Lead ID is required']);
    exit();
}

$lead_id = intval($_GET['lead_id']);
$conn = getDbConnection();

try {
    // Get receipts for the lead, specifically for DP stage
    $query = "SELECT 
                sr.id,
                sr.filename,
                sr.original_name,
                sr.file_path,
                sr.uploaded_at,
                sr.stage_type,
                CASE 
                    WHEN sr.stage_type = 'downpayment' THEN 'DP Payment'
                    ELSE sr.stage_type
                END as stage_display
              FROM stage_receipts sr 
              WHERE sr.lead_id = ? AND sr.stage_type = 'downpayment'
              ORDER BY sr.uploaded_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $lead_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $receipts = [];
    while ($row = $result->fetch_assoc()) {
        $receipts[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($receipts);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
