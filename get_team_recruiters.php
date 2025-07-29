<?php
require_once 'config/database.php';
header('Content-Type: application/json');

$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : (isset($_POST['team_id']) ? intval($_POST['team_id']) : 0);
if (!$team_id) {
    echo json_encode(['success' => false, 'message' => 'No team_id provided']);
    exit;
}

try {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE team_id = ? AND is_active = 1 AND (role = 'manager' OR role = 'admin') ORDER BY name ASC");
    $stmt->bind_param('i', $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'role' => $row['role']
        ];
    }
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'users' => $users]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}