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

// Fetch tracker data
$stmt = $conn->prepare("SELECT * FROM downpayment_tracker WHERE lead_id = ?");
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
$conn->close();

if ($tracker) {
    $response['success'] = true;
    $response['tracker'] = $tracker;

    // Calculate progress details for the modal's edit button logic
    $requirements_complete = $tracker['requirements_complete'] == 1;
    $spot_dp = $tracker['spot_dp'] == 1;
    $current_dp_stage = intval($tracker['current_dp_stage']);
    $total_dp_stages = intval($tracker['total_dp_stages']);
    $pagibig_bank_approval = $tracker['pagibig_bank_approval'] == 1;
    $loan_takeout = $tracker['loan_takeout'] == 1;
    $turnover = $tracker['turnover'] == 1;

    $is_dp_stage_complete = $spot_dp || ($current_dp_stage > 0 && $current_dp_stage == $total_dp_stages);

    $is_fully_complete = $requirements_complete && $is_dp_stage_complete && $pagibig_bank_approval && $loan_takeout && $turnover;

    $response['tracker']['progress_details'] = [
        'is_fully_complete' => $is_fully_complete
    ];

} else {
    $response['success'] = true; // Still success, just no tracker data found
    $response['message'] = 'No tracker data found for this lead.';
    $response['tracker'] = null; // Explicitly set to null
}

echo json_encode($response);
?>
