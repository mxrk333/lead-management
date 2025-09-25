<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get project name from request
    $projectName = '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $projectName = isset($_GET['project']) ? trim($_GET['project']) : '';
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $projectName = isset($input['project']) ? trim($input['project']) : '';
    }

    if (empty($projectName)) {
        throw new Exception('Project name is required');
    }

    // First, find the developer ID by name (developers table is the correct source for projects)
    $developerSql = "SELECT id, name FROM developers WHERE name = ? AND is_active = 1 LIMIT 1";
    $developerStmt = $conn->prepare($developerSql);
    if (!$developerStmt) {
        throw new Exception('Failed to prepare developer statement: ' . $conn->error);
    }
    
    $developerStmt->bind_param("s", $projectName);
    $developerStmt->execute();
    $developerResult = $developerStmt->get_result();
    
    if ($developerResult->num_rows === 0) {
        // Developer/Project not found, return empty models
        echo json_encode([
            'success' => true,
            'project_found' => false,
            'project_name' => $projectName,
            'models' => [],
            'message' => 'Project not found in developers table'
        ]);
        exit();
    }
    
    $developer = $developerResult->fetch_assoc();
    $developerId = $developer['id'];
    $developerStmt->close();

    // Now fetch house models for this developer/project
    $modelsSql = "SELECT id, name, description, base_price, floor_area, lot_area, 
                         bedrooms, bathrooms, is_active
                  FROM project_models 
                  WHERE developer_id = ? AND is_active = 1 
                  ORDER BY name ASC";
    
    $modelsStmt = $conn->prepare($modelsSql);
    if (!$modelsStmt) {
        throw new Exception('Failed to prepare models statement: ' . $conn->error);
    }
    
    $modelsStmt->bind_param("i", $developerId);
    $modelsStmt->execute();
    $modelsResult = $modelsStmt->get_result();
    
    $models = [];
    while ($row = $modelsResult->fetch_assoc()) {
        $models[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'base_price' => $row['base_price'],
            'floor_area' => $row['floor_area'],
            'lot_area' => $row['lot_area'],
            'bedrooms' => $row['bedrooms'],
            'bathrooms' => $row['bathrooms']
        ];
    }
    
    $modelsStmt->close();
    $conn->close();

    // Log the query for debugging
    error_log("Fetched " . count($models) . " models for developer/project: {$projectName} (ID: {$developerId})");

    echo json_encode([
        'success' => true,
        'project_found' => true,
        'developer_id' => $developerId,
        'project_name' => $projectName,
        'models' => $models,
        'message' => 'Models fetched successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in get_project_models.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'models' => []
    ]);
}
?>