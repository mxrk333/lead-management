<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set content type to JSON and prevent any HTML output
header('Content-Type: application/json');

// Turn off error display to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(0);

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Project ID is required');
    }

    $project_id = intval($_GET['id']);
    
    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Fetch project with location details - updated to use new column names
    $stmt = $conn->prepare("
        SELECT p.*, c.name as city_name, pr.name as province_name 
        FROM projects p 
        LEFT JOIN cities c ON p.city_id = c.id 
        LEFT JOIN provinces pr ON p.province_id = pr.id 
        WHERE p.id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $project_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Project not found');
    }
    
    $project = $result->fetch_assoc();
    
    // Format numeric values for display
    if ($project['price_min']) {
        $project['price_min_formatted'] = number_format($project['price_min'], 2);
    }
    if ($project['price_max']) {
        $project['price_max_formatted'] = number_format($project['price_max'], 2);
    }
    if ($project['total_contract_price']) {
        $project['total_contract_price_formatted'] = number_format($project['total_contract_price'], 2);
    }
    if ($project['reservation_fee']) {
        $project['reservation_fee_formatted'] = number_format($project['reservation_fee'], 2);
    }
    if ($project['bank_amortization']) {
        $project['bank_amortization_formatted'] = number_format($project['bank_amortization'], 2);
    }
    if ($project['required_salary']) {
        $project['required_salary_formatted'] = number_format($project['required_salary'], 2);
    }
    if ($project['downpayment_amount']) {
        $project['downpayment_amount_formatted'] = number_format($project['downpayment_amount'], 2);
    }
    
    $stmt->close();
    $conn->close();
    
    // Clean output buffer to ensure no extra content
    if (ob_get_level()) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => true,
        'project' => $project
    ]);

} catch (Exception $e) {
    // Log error but don't display it
    error_log("Error in get_project.php: " . $e->getMessage());
    
    // Clean output buffer
    if (ob_get_level()) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Ensure no additional output
exit;
?>
