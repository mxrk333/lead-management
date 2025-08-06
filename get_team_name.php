<?php
session_start();

// Include database connection
require_once 'config/pdo-database.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if PDO connection exists
if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available.']);
    exit;
}

// Get team_id from GET parameter
$team_id = $_GET['team_id'] ?? null;

if (!$team_id || !is_numeric($team_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid team ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name FROM teams WHERE id = :team_id LIMIT 1");
    $stmt->execute([':team_id' => $team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($team) {
        echo json_encode([
            'success' => true,
            'name' => $team['name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'name' => 'No Team'
        ]);
    }

} catch (Exception $e) {
    error_log("Error fetching team name: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching team name: ' . $e->getMessage()
    ]);
}
?>