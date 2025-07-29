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

try {
    // Get all teams with their managers
    $sql = "SELECT t.id, t.name, u.id as manager_id, u.name as manager_name 
            FROM teams t 
            LEFT JOIN users u ON u.team_id = t.id AND u.role IN ('manager', 'admin')
            ORDER BY t.name, u.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by teams
    $teams = [];
    foreach ($results as $row) {
        $team_id = $row['id'];

        if (!isset($teams[$team_id])) {
            $teams[$team_id] = [
                'id' => $team_id,
                'name' => $row['name'],
                'managers' => []
            ];
        }

        if ($row['manager_id']) {
            $teams[$team_id]['managers'][] = [
                'id' => $row['manager_id'],
                'name' => $row['manager_name']
            ];
        }
    }

    // Convert to indexed array
    $teams_array = array_values($teams);

    echo json_encode([
        'success' => true,
        'teams' => $teams_array
    ]);

} catch (Exception $e) {
    error_log("Error fetching teams and managers: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching teams and managers: ' . $e->getMessage()
    ]);
}
?>