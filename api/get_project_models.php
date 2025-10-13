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
    // Detect if developers table has is_active column
    $devHasIsActive = false;
    if ($devCols = $conn->query("SHOW COLUMNS FROM developers")) {
        while ($col = $devCols->fetch_assoc()) {
            if (strtolower($col['Field']) === 'is_active') { $devHasIsActive = true; break; }
        }
        $devCols->close();
    }

    $developerSql = $devHasIsActive
        ? "SELECT id, name FROM developers WHERE name = ? AND is_active = 1 LIMIT 1"
        : "SELECT id, name FROM developers WHERE name = ? LIMIT 1";

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

    // Determine project_models schema and fetch house models accordingly
    $models = [];

    // Detect schema columns
    $hasDeveloperIdCol = false;
    $hasProjectIdCol = false;
    $hasNameCol = false;
    $hasModelNameCol = false;
    
    if ($colsRes = $conn->query("SHOW COLUMNS FROM project_models")) {
        while ($col = $colsRes->fetch_assoc()) {
            $field = strtolower($col['Field']);
            if ($field === 'developer_id') $hasDeveloperIdCol = true;
            if ($field === 'project_id') $hasProjectIdCol = true;
            if ($field === 'name') $hasNameCol = true;
            if ($field === 'model_name') $hasModelNameCol = true;
        }
        $colsRes->close();
    }

    // Detect if project_models has is_active column
    $pmHasIsActive = false;
    if ($pmCols = $conn->query("SHOW COLUMNS FROM project_models")) {
        while ($col = $pmCols->fetch_assoc()) {
            if (strtolower($col['Field']) === 'is_active') { $pmHasIsActive = true; break; }
        }
        $pmCols->close();
    }

    if ($hasDeveloperIdCol && $hasNameCol) {
        // Current schema: project_models(developer_id, name, ...)
        $modelsSql = $pmHasIsActive
            ? "SELECT id, name, description, base_price, floor_area, lot_area, bedrooms, bathrooms, is_active FROM project_models WHERE developer_id = ? AND is_active = 1 ORDER BY name ASC"
            : "SELECT id, name, NULL AS description, NULL AS base_price, NULL AS floor_area, NULL AS lot_area, NULL AS bedrooms, NULL AS bathrooms, NULL AS is_active FROM project_models WHERE developer_id = ? ORDER BY name ASC";
        $modelsStmt = $conn->prepare($modelsSql);
        if (!$modelsStmt) {
            throw new Exception('Failed to prepare models statement (developer_id schema): ' . $conn->error);
        }
        $modelsStmt->bind_param("i", $developerId);
        $modelsStmt->execute();
        $modelsResult = $modelsStmt->get_result();
        while ($row = $modelsResult->fetch_assoc()) {
            $models[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'base_price' => $row['base_price'] ?? null,
                'floor_area' => $row['floor_area'] ?? null,
                'lot_area' => $row['lot_area'] ?? null,
                'bedrooms' => $row['bedrooms'] ?? null,
                'bathrooms' => $row['bathrooms'] ?? null
            ];
        }
        $modelsStmt->close();
    } elseif ($hasProjectIdCol && $hasModelNameCol) {
        // Legacy/alternate schema: project_models(project_id, model_name, price, ...)
        // Join via projects table on developer name
        $altSql = $pmHasIsActive
            ? "SELECT pm.id, pm.model_name AS name, pm.price AS base_price, pm.floor_area, pm.lot_area, pm.bedrooms, pm.bathrooms FROM project_models pm JOIN projects p ON pm.project_id = p.id WHERE p.developer = ? AND pm.is_active = 1 ORDER BY pm.model_name ASC"
            : "SELECT pm.id, pm.model_name AS name, pm.price AS base_price, pm.floor_area, pm.lot_area, pm.bedrooms, pm.bathrooms FROM project_models pm JOIN projects p ON pm.project_id = p.id WHERE p.developer = ? ORDER BY pm.model_name ASC";
        $altStmt = $conn->prepare($altSql);
        if (!$altStmt) {
            throw new Exception('Failed to prepare models statement (project_id/model_name schema): ' . $conn->error);
        }
        $altStmt->bind_param("s", $projectName);
        $altStmt->execute();
        $altRes = $altStmt->get_result();
        while ($row = $altRes->fetch_assoc()) {
            $models[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => null,
                'base_price' => $row['base_price'] ?? null,
                'floor_area' => $row['floor_area'] ?? null,
                'lot_area' => $row['lot_area'] ?? null,
                'bedrooms' => $row['bedrooms'] ?? null,
                'bathrooms' => $row['bathrooms'] ?? null
            ];
        }
        $altStmt->close();
    } else {
        // Unknown schema: return graceful empty
        error_log('get_project_models.php: Unknown project_models schema');
    }

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