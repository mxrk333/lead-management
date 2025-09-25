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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

    // Get search query if provided
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build the SQL query with optional search filtering
    $sql = "SELECT id, name, description, is_active FROM developers WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $sql .= " AND name LIKE ?";
        $params[] = "%{$search}%";
        $types .= "s";
    }
    
    $sql .= " ORDER BY is_active DESC, name ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projects[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'is_active' => (bool)$row['is_active']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'projects' => $projects,
        'total' => count($projects),
        'search_term' => $search
    ]);

} catch (Exception $e) {
    error_log("Error in get_projects.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'projects' => []
    ]);
}
?>