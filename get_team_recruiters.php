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

$team_id = $_GET['team_id'];

try {
    $sql = "SELECT id, name FROM users WHERE team_id = :team_id ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
    $stmt->execute();

    $recruiters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($recruiters);

} catch (Exception $e) {
    error_log("Error fetching team recruiters: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching recruiters: ' . $e->getMessage()
    ]);
}