<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Validate required fields
    $required_fields = ['project_id', 'name', 'developer', 'status', 'province_id', 'city_id', 'price_min', 'price_max', 'commission'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    // Sanitize and validate input data
    $project_id = intval($_POST['project_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');
    $house_model = trim($_POST['house_model'] ?? '');
    $status = trim($_POST['status']);
    $developer = trim($_POST['developer']);
    $price_min = floatval($_POST['price_min']);
    $price_max = floatval($_POST['price_max']);
    $commission = floatval($_POST['commission']);
    $priority = trim($_POST['priority'] ?? 'medium');
    $city_id = intval($_POST['city_id']);
    $province_id = intval($_POST['province_id']);
    $exact_location = trim($_POST['exact_location'] ?? '');
    $drive_link = trim($_POST['drive_link'] ?? '');
    $messenger_link = trim($_POST['messenger_link'] ?? '');

    // Financial details
    $total_contract_price = !empty($_POST['total_contract_price']) ? floatval($_POST['total_contract_price']) : null;
    $reservation_fee = !empty($_POST['reservation_fee']) ? floatval($_POST['reservation_fee']) : null;
    $bank_amortization = !empty($_POST['bank_amortization']) ? floatval($_POST['bank_amortization']) : null;
    $required_salary = !empty($_POST['required_salary']) ? floatval($_POST['required_salary']) : null;
    $downpayment_percentage = !empty($_POST['downpayment_percentage']) ? floatval($_POST['downpayment_percentage']) : null;
    
    // New downpayment fields
    $downpayment_amount = !empty($_POST['downpayment_amount']) ? floatval($_POST['downpayment_amount']) : null;
    $downpayment_term = !empty($_POST['downpayment_term']) ? intval($_POST['downpayment_term']) : null;

    // Validate price range
    if ($price_min > $price_max) {
        throw new Exception('Minimum price cannot be greater than maximum price');
    }

    // Validate commission
    if ($commission < 0 || $commission > 100) {
        throw new Exception('Commission must be between 0 and 100');
    }

    // Check if project exists
    $check_stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
    $check_stmt->bind_param("i", $project_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Project not found');
    }
    
    $existing_project = $result->fetch_assoc();
    $check_stmt->close();

    // Handle file uploads
    $upload_dir = '../uploads/projects/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $image_fields = ['image1', 'image2', 'image3', 'image4'];
    $updated_images = [];

    foreach ($image_fields as $field) {
        // Check if user wants to delete this image
        if (isset($_POST["delete_$field"]) && $_POST["delete_$field"] == '1') {
            // Delete the existing file
            if (!empty($existing_project[$field]) && file_exists($upload_dir . $existing_project[$field])) {
                unlink($upload_dir . $existing_project[$field]);
            }
            $updated_images[$field] = null;
        } elseif (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            // New file uploaded
            $file = $_FILES[$field];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception("Invalid file type for $field. Only JPEG, PNG, and GIF are allowed.");
            }
            
            // Validate file size (5MB max)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("File size for $field is too large. Maximum 5MB allowed.");
            }
            
            // Delete old file if exists
            if (!empty($existing_project[$field]) && file_exists($upload_dir . $existing_project[$field])) {
                unlink($upload_dir . $existing_project[$field]);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $field . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $updated_images[$field] = $filename;
            } else {
                throw new Exception("Failed to upload $field");
            }
        } else {
            // Keep existing image
            $updated_images[$field] = $existing_project[$field];
        }
    }

    // Prepare SQL statement
    $sql = "UPDATE projects SET 
        name = ?, description = ?, house_model = ?, status = ?, developer = ?,
        price_min = ?, price_max = ?, commission = ?, priority = ?,
        city_id = ?, province_id = ?, exact_location = ?,
        image1 = ?, image2 = ?, image3 = ?, image4 = ?,
        drive_link = ?, messenger_link = ?,
        total_contract_price = ?, reservation_fee = ?, bank_amortization = ?,
        required_salary = ?, downpayment_percentage = ?,
        downpayment_amount = ?, downpayment_term = ?,
        updated_at = NOW()
        WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $stmt->bind_param(
        "sssssddssissssssssddddddii",
        $name, $description, $house_model, $status, $developer,
        $price_min, $price_max, $commission, $priority,
        $city_id, $province_id, $exact_location,
        $updated_images['image1'], $updated_images['image2'],
        $updated_images['image3'], $updated_images['image4'],
        $drive_link, $messenger_link,
        $total_contract_price, $reservation_fee, $bank_amortization,
        $required_salary, $downpayment_percentage,
        $downpayment_amount, $downpayment_term,
        $project_id
    );

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Project updated successfully!';
        
        echo json_encode([
            'success' => true,
            'message' => 'Project updated successfully!'
        ]);
    } else {
        throw new Exception('Failed to update project: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Error in update_project.php: " . $e->getMessage());
    
    $_SESSION['error_message'] = $e->getMessage();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
