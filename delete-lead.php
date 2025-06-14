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

// Get the lead ID from URL parameter
$lead_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$lead_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lead ID is required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Delete lead
$result = deleteLead($lead_id, $user_id, $user['role']);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Lead deleted successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete lead']);
}
exit();
?>
