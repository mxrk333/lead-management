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

// Check if required parameters are provided
if (!isset($_POST['lead_id']) || !isset($_POST['amount'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$user_id = $_SESSION['user_id'];
$lead_id = intval($_POST['lead_id']);
$amount = floatval($_POST['amount']);

// Get current active goal
$current_goal = getCurrentGoal($user_id);

if (!$current_goal) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No active goal found']);
    exit();
}

// Update goal progress
if (updateGoalProgress($current_goal['id'], $lead_id, $amount)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Goal progress updated successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating goal progress']);
} 