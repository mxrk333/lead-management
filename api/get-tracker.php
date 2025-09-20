<?php
require_once '../config/database.php';
require_once '../includes/functions.php'; // Assuming functions.php contains addLeadActivity and other necessary functions

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_GET['lead_id'])) {
    $response['message'] = 'Lead ID is required.';
    echo json_encode($response);
    exit();
}

$lead_id = intval($_GET['lead_id']);
$conn = getDbConnection();

if (!$conn) {
    $response['message'] = 'Database connection failed.';
    echo json_encode($response);
    exit();
}

// Fetch the most recent tracker data for this lead (since multiple entries are now allowed)
$stmt = $conn->prepare("SELECT * FROM downpayment_tracker WHERE lead_id = ? ORDER BY created_at DESC LIMIT 1");
if (!$stmt) {
    $response['message'] = 'Failed to prepare statement: ' . $conn->error;
    echo json_encode($response);
    exit();
}

$stmt->bind_param("i", $lead_id);
$stmt->execute();
$result = $stmt->get_result();
$tracker = $result->fetch_assoc();
$stmt->close();

if ($tracker) {
    $response['success'] = true;
    $response['tracker'] = $tracker;

    // Calculate progress details for the modal's edit button logic
    $requirements_complete = $tracker['requirements_complete'] == 1;
    $spot_dp = $tracker['spot_dp'] == 1;
    $total_dp_stages = intval($tracker['total_dp_stages']);
    $pagibig_bank_approval = $tracker['pagibig_bank_approval'] == 1;
    $loan_takeout = $tracker['loan_takeout'] == 1;
    $turnover = $tracker['turnover'] == 1;

    // Dynamically calculate current_dp_stage based on actual receipt count
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

    $is_dp_stage_complete = $spot_dp || ($current_dp_stage > 0 && $current_dp_stage == $total_dp_stages);

    $is_fully_complete = $requirements_complete && $is_dp_stage_complete && $pagibig_bank_approval && $loan_takeout && $turnover;

    // Update the tracker with the dynamically calculated current_dp_stage
    $response['tracker']['current_dp_stage'] = $current_dp_stage;
    $response['tracker']['progress_details'] = [
        'is_fully_complete' => $is_fully_complete
    ];

} else {
    $response['success'] = true; // Still success, just no tracker data found
    $response['message'] = 'No tracker data found for this lead.';
    $response['tracker'] = null; // Explicitly set to null
}

$conn->close();
echo json_encode($response);
?>
