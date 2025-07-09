<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    if (!isset($_GET['province_id']) || empty($_GET['province_id'])) {
        throw new Exception('Province ID is required');
    }

    $province_id = intval($_GET['province_id']);
    
    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Fetch cities for the given province
    $stmt = $conn->prepare("SELECT id, name FROM cities WHERE province_id = ? ORDER BY name");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $province_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $cities = [];
    
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'cities' => $cities
    ]);

} catch (Exception $e) {
    error_log("Error in get_cities.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>