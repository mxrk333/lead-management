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

    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('Project ID is required');
    }

    $project_id = intval($_POST['id']);
    
    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // First, get the project to check if it exists and get image filenames
    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Project not found');
    }
    
    $project = $result->fetch_assoc();
    $stmt->close();

    // Delete the project from database
    $delete_stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
    $delete_stmt->bind_param("i", $project_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception('Failed to delete project: ' . $delete_stmt->error);
    }
    
    $delete_stmt->close();
    $conn->close();

    // Delete associated image files
    $upload_dir = '../uploads/projects/';
    $image_fields = ['image1', 'image2', 'image3', 'image4'];
    
    foreach ($image_fields as $field) {
        if (!empty($project[$field])) {
            $file_path = $upload_dir . $project[$field];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Project deleted successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in delete_project.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>