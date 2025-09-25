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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['name'])) {
        throw new Exception('Project name is required');
    }

    $projectName = trim($input['name']);
    
    // Validate project name length
    if (strlen($projectName) < 2) {
        throw new Exception('Project name must be at least 2 characters long');
    }
    
    if (strlen($projectName) > 100) {
        throw new Exception('Project name must be less than 100 characters');
    }

    // Check if project already exists
    $checkSql = "SELECT id FROM projects WHERE name = ?";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception('Failed to prepare check statement');
    }
    
    $checkStmt->bind_param("s", $projectName);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $checkStmt->close();
        echo json_encode([
            'success' => true,
            'exists' => true,
            'message' => 'Project already exists',
            'project_name' => $projectName
        ]);
        exit();
    }
    
    $checkStmt->close();

    // Insert the new project with minimal required fields
    $sql = "INSERT INTO projects (
        name, 
        developer,
        status, 
        price_min, 
        price_max, 
        commission,
        city_id,
        province_id,
        created_at, 
        updated_at
    ) VALUES (?, ?, 'preselling', 0, 0, 0, 1, 1, NOW(), NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    // Use the project name as both name and developer for quick creation
    $stmt->bind_param("ss", $projectName, $projectName);

    if ($stmt->execute()) {
        $projectId = $conn->insert_id;
        
        // Log activity
        error_log("Quick project created: {$projectName} (ID: {$projectId}) by user: {$_SESSION['user_id']}");
        
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'Project added successfully',
            'project_id' => $projectId,
            'project_name' => $projectName
        ]);
    } else {
        throw new Exception('Failed to create project: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Error in quick_add_project.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>