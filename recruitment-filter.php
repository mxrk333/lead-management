<?php
require_once 'config/pdo-database.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    $filters = [];
    if (isset($_POST['filters']) && !empty($_POST['filters'])) {
        $filters = json_decode($_POST['filters'], true);
    }

    $sql = "SELECT rl.*, t.name AS recruiter_team 
            FROM recruitment_leads rl
            LEFT JOIN teams t ON rl.recruiter_team_id = t.id
            WHERE 1=1";
    
    $params = [];

    // Handle onboarding status filter explicitly
    if (isset($filters['onboardStatus']) && $filters['onboardStatus'] !== '') {
        $onboard_value = $filters['onboardStatus'];
        error_log("Applying onboard filter with value: " . $onboard_value);
        
        if ($onboard_value === '1') {
            $sql .= " AND rl.onboarding_status = 1";
        } else if ($onboard_value === '0') {
            $sql .= " AND (rl.onboarding_status = 0 OR rl.onboarding_status IS NULL)";
        }
    }

    // Handle other filters
    if (!empty($filters['status'])) {
        $sql .= " AND rl.status = :status";
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['team'])) {
        $sql .= " AND rl.recruiter_team_id = :team_id";
        $params[':team_id'] = $filters['team'];
    }

    if (!empty($filters['search'])) {
        $searchTerm = '%' . $filters['search'] . '%';
        $sql .= " AND (rl.full_name LIKE :search OR rl.email LIKE :search OR rl.contact_number LIKE :search)";
        $params[':search'] = $searchTerm;
    }

    $sql .= " ORDER BY rl.created_at DESC";

    error_log("Final SQL: " . $sql);
    error_log("Parameters: " . json_encode($params));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug logging
    error_log("Total results: " . count($results));
    if (count($results) > 0) {
        error_log("First record onboarding_status: " . $results[0]['onboarding_status']);
    }

    echo json_encode([
        'success' => true,
        'data' => $results,
        'debug' => [
            'sql' => $sql,
            'params' => $params,
            'filters' => $filters
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in recruitment filter: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}