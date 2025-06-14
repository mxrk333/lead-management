<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if lead_id is provided
if (!isset($_GET['lead_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Lead ID is required']);
    exit();
}

$lead_id = intval($_GET['lead_id']);
$conn = getDbConnection();

// Get tracker data
$stmt = $conn->prepare("SELECT * FROM downpayment_tracker WHERE lead_id = ?");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$result = $stmt->get_result();
$tracker = $result->fetch_assoc();
$stmt->close();

header('Content-Type: application/json');
if ($tracker) {
    echo json_encode(['success' => true, 'tracker' => $tracker]);
} else {
    echo json_encode(['success' => false, 'message' => 'No tracker found for this lead']);
} 