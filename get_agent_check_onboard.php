<?php
session_start();
require_once 'config/pdo-database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available.']);
    exit;
}

if (!isset($_GET['team_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing team_id']);
    exit;
}

// Get POST data (sanitize/validate as needed)
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$name = $_POST['name'] ?? '';

// Response structure
$response = ['exists' => false];

// Example using PDO:
if (!empty($username)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $exists = $stmt->fetchColumn() > 0;
}

echo json_encode(['exists' => $exists]);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);