<?php
session_start();

// Production-safe error handling
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    // Only show errors in debug mode
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} else {
    // Production mode - log errors but don't display them
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

// Increase memory and execution time limits for production
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 60);

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$conn = getDbConnection();

// Initialize search parameters
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$filter_agent = isset($_GET['agent']) ? $_GET['agent'] : '';
$filter_developer = isset($_GET['developer']) ? $_GET['developer'] : '';
$filter_progress = isset($_GET['progress']) ? $_GET['progress'] : '';

// Get URL hash to determine view
$show_completed = isset($_GET['view']) && $_GET['view'] === 'completed';

// Get all leads in Downpayment Stage with search/filter
$query = "SELECT l.*, u.name as agent_name 
          FROM leads l 
          JOIN users u ON l.user_id = u.id 
          LEFT JOIN downpayment_tracker dt ON l.id = dt.lead_id
          WHERE l.status = 'Downpayment Stage'";

if ($show_completed) {
    // A lead is considered fully completed if all 5 milestones are met
    $query .= " AND dt.requirements_complete = 1 
                AND (dt.spot_dp = 1 OR dt.current_dp_stage = dt.total_dp_stages)
                AND dt.pagibig_bank_approval = 1 
                AND dt.loan_takeout = 1 
                AND dt.turnover = 1";
} else {
    // A lead is in progress if it's not fully completed OR if there's no tracker data yet
    $query .= " AND (dt.id IS NULL OR 
                NOT (dt.requirements_complete = 1 
                    AND (dt.spot_dp = 1 OR dt.current_dp_stage = dt.total_dp_stages)
                    AND dt.pagibig_bank_approval = 1 
                    AND dt.loan_takeout = 1 
                    AND dt.turnover = 1))";
}

// Add role-based restrictions
if ($user['role'] == 'agent') {
    $query .= " AND l.user_id = " . $user_id;
} elseif ($user['role'] == 'supervisor' || $user['role'] == 'manager') {
    $query .= " AND u.team_id = " . $user['team_id'];
}

// Add search conditions
if (!empty($search_query)) {
    $search_param = "%$search_query%";
    $query .= " AND (l.client_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)";
}

// Add filter conditions
if (!empty($filter_agent)) {
    if ($user['role'] != 'admin') {
        $query .= " AND l.user_id = ? AND u.team_id = " . $user['team_id'];
    } else {
        $query .= " AND l.user_id = ?";
    }
}

if (!empty($filter_developer)) {
    $query .= " AND l.developer = ?";
}

$query .= " ORDER BY l.updated_at DESC";

// Prepare and execute the query with parameters
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Bind parameters if needed
$param_types = "";
$param_values = [];

if (!empty($search_query)) {
    $param_types .= "sss";
    $param_values[] = $search_param;
    $param_values[] = $search_param;
    $param_values[] = $search_param;
}

if (!empty($filter_agent)) {
    $param_types .= "s";
    $param_values[] = $filter_agent;
}

if (!empty($filter_developer)) {
    $param_types .= "s";
    $param_values[] = $filter_developer;
}

if (!empty($param_values)) {
    $stmt->bind_param($param_types, ...$param_values);
}

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
$leads = [];
while ($row = $result->fetch_assoc()) {
    $leads[] = $row;
}
$stmt->close();

// Get all agents for filter dropdown
$agents_query = "SELECT id, name FROM users ORDER BY name";
$agents_result = $conn->query($agents_query);
$agents = [];
while ($agent = $agents_result->fetch_assoc()) {
    $agents[$agent['id']] = $agent['name'];
}

// Get all developers for filter dropdown
$developers_query = "SELECT DISTINCT developer FROM leads WHERE status = 'Downpayment Stage' ORDER BY developer";
$developers_result = $conn->query($developers_query);
$developers = [];
while ($dev = $developers_result->fetch_assoc()) {
    $developers[] = $dev['developer'];
}

// Get tracker data for each lead
$trackers = [];
if (!empty($leads)) {
    $lead_ids = array_column($leads, 'id');
    
    if (!empty($lead_ids)) {
        // Get the most recent tracker entry for each lead (since multiple entries are now allowed)
        $tracker_query = "SELECT dt1.* FROM downpayment_tracker dt1
                         INNER JOIN (
                             SELECT lead_id, MAX(created_at) as max_created_at
                             FROM downpayment_tracker 
                             WHERE lead_id IN (" . implode(',', $lead_ids) . ")
                             GROUP BY lead_id
                         ) dt2 ON dt1.lead_id = dt2.lead_id AND dt1.created_at = dt2.max_created_at
                         ORDER BY dt1.lead_id";
        $tracker_result = $conn->query($tracker_query);
        
        if ($tracker_result) {
            while ($tracker = $tracker_result->fetch_assoc()) {
                // Calculate progress details for the modal's edit button logic
                $requirements_complete = $tracker['requirements_complete'] == 1;
                $spot_dp = $tracker['spot_dp'] == 1;
                $total_dp_stages = intval($tracker['total_dp_stages']);
                $pagibig_bank_approval = $tracker['pagibig_bank_approval'] == 1;
                $loan_takeout = $tracker['loan_takeout'] == 1;
                $turnover = $tracker['turnover'] == 1;

                // Dynamically calculate current_dp_stage based on actual receipt count
                $current_dp_stage = 0;
                if ($spot_dp) {
                    $current_dp_stage = 1; // Spot DP is always stage 1
                } else {
                    // Count actual receipts from stage_receipts table
                    $receipt_count_stmt = $conn->prepare("SELECT COUNT(*) as total_receipts FROM stage_receipts WHERE lead_id = ? AND stage_type = 'downpayment'");
                    $receipt_count_stmt->bind_param("i", $tracker['lead_id']);
                    $receipt_count_stmt->execute();
                    $receipt_count_result = $receipt_count_stmt->get_result();
                    $total_receipts = $receipt_count_result->fetch_assoc()['total_receipts'];
                    $receipt_count_stmt->close();
                    
                    // Current stage should be at least 1 if there are any receipts, and not exceed total terms
                    $current_dp_stage = $total_receipts > 0 ? max(1, min($total_receipts, $total_dp_stages)) : 0;
                }

                $is_dp_stage_complete = $spot_dp || ($current_dp_stage > 0 && $current_dp_stage == $total_dp_stages);

                $is_fully_complete = $requirements_complete && $is_dp_stage_complete && $pagibig_bank_approval && $loan_takeout && $turnover;

                // Update the tracker with the dynamically calculated current_dp_stage
                $tracker['current_dp_stage'] = $current_dp_stage;
                $tracker['progress_details'] = [
                    'is_fully_complete' => $is_fully_complete
                ];

                $trackers[$tracker['lead_id']] = $tracker;
            }
        }
    }
}

// Filter by progress if needed
if (!empty($filter_progress)) {
    $filtered_leads = [];
    foreach ($leads as $lead) {
        if (isset($trackers[$lead['id']])) {
            $progress_rate = $trackers[$lead['id']]['progress_rate'];
            
            if ($filter_progress == 'low' && $progress_rate < 33) {
                $filtered_leads[] = $lead;
            } elseif ($filter_progress == 'medium' && $progress_rate >= 33 && $progress_rate < 66) {
                $filtered_leads[] = $lead;
            } elseif ($filter_progress == 'high' && $progress_rate >= 66) {
                $filtered_leads[] = $lead;
            }
        } elseif ($filter_progress == 'low') {
            $filtered_leads[] = $lead;
        }
    }
    $leads = $filtered_leads;
}

// Function to update current_dp_stage based on actual receipt count
function updateCurrentDpStage($conn, $lead_id) {
    // Get the latest tracker for this lead
    $tracker_stmt = $conn->prepare("SELECT id, spot_dp, total_dp_stages FROM downpayment_tracker WHERE lead_id = ? ORDER BY created_at DESC LIMIT 1");
    $tracker_stmt->bind_param("i", $lead_id);
    $tracker_stmt->execute();
    $tracker_result = $tracker_stmt->get_result();
    $tracker = $tracker_result->fetch_assoc();
    $tracker_stmt->close();
    
    if (!$tracker) {
        return; // No tracker found
    }
    
    $spot_dp = $tracker['spot_dp'] == 1;
    $total_dp_stages = intval($tracker['total_dp_stages']);
    
    // Calculate current_dp_stage based on actual receipt count
    $current_dp_stage = 0;
    if ($spot_dp) {
        $current_dp_stage = 1; // Spot DP is always stage 1
    } else {
        // Count actual receipts from stage_receipts table
        $receipt_count_stmt = $conn->prepare("SELECT COUNT(*) as total_receipts FROM stage_receipts WHERE lead_id = ? AND stage_type = 'downpayment'");
        $receipt_count_stmt->bind_param("i", $lead_id);
        $receipt_count_stmt->execute();
        $receipt_count_result = $receipt_count_stmt->get_result();
        $total_receipts = $receipt_count_result->fetch_assoc()['total_receipts'];
        $receipt_count_stmt->close();
        
        // Current stage should be at least 1 if there are any receipts, and not exceed total terms
        $current_dp_stage = $total_receipts > 0 ? max(1, min($total_receipts, $total_dp_stages)) : 0;
    }
    
    // Calculate progress rate
    $progress_rate = $total_dp_stages > 0 ? ($current_dp_stage / $total_dp_stages) * 100 : 0;
    
    // Update the tracker with the correct current_dp_stage
    $update_stmt = $conn->prepare("
        UPDATE downpayment_tracker 
        SET current_dp_stage = ?, progress_rate = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->bind_param("idi", $current_dp_stage, $progress_rate, $tracker['id']);
    $update_stmt->execute();
    $update_stmt->close();
    
    return $current_dp_stage;
}

// Handle file upload for receipts
function handleReceiptUpload($files, $lead_id, $stage_type) {
    // Use absolute path for better compatibility
    $upload_dir = dirname(__FILE__) . '/uploads/receipts/';
    
    // Ensure directory exists with proper permissions
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        throw new Exception("Upload directory is not writable");
    }
    
    $uploaded_files = [];
    
    // Handle both single file and multiple file uploads
    if (isset($files['tmp_name'])) {
        $file_count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 1;
        
        for ($i = 0; $i < $file_count; $i++) {
            $tmp_name = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $file_error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            
            if ($file_error === UPLOAD_ERR_OK && !empty($tmp_name)) {
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Validate file type (only PNG and JPEG)
                if (in_array($file_extension, ['png', 'jpg', 'jpeg'])) {
                    $new_filename = $lead_id . '_' . $stage_type . '_' . time() . '_' . $i . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Additional security check
                    if (is_uploaded_file($tmp_name)) {
                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            $uploaded_files[] = [
                                'filename' => $new_filename,
                                'original_name' => $file_name,
                                'path' => 'uploads/receipts/' . $new_filename, // Use relative path for database
                                'size' => filesize($upload_path),
                                'type' => mime_content_type($upload_path)
                            ];
                        } else {
                            throw new Exception("Failed to move uploaded file: " . $file_name);
                        }
                    } else {
                        throw new Exception("Invalid file upload: " . $file_name);
                    }
                } else {
                    throw new Exception("Invalid file type. Only PNG and JPEG files are allowed.");
                }
            } elseif ($file_error !== UPLOAD_ERR_NO_FILE) {
                throw new Exception("File upload error: " . $file_error);
            }
        }
    }
    
    return $uploaded_files;
}

// Handle form submission for updating tracker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracker'])) {
    try {
        // Validate required fields
        if (empty($_POST['lead_id'])) {
            throw new Exception("Lead ID is required");
        }
        
        $lead_id = intval($_POST['lead_id']);
        if ($lead_id <= 0) {
            throw new Exception("Invalid Lead ID");
        }
        
        $reservation_date = !empty($_POST['reservation_date']) ? $_POST['reservation_date'] : null;
        $requirements_complete = isset($_POST['requirements_complete']) ? 1 : 0;
        $spot_dp = isset($_POST['spot_dp']) ? 1 : 0;
        
        // Validate and sanitize dp_terms to match ENUM values
        $allowed_dp_terms = ['6', '9', '12', '15', '18', '24', '36'];
        $raw_dp_terms = $spot_dp ? '6' : (isset($_POST['dp_terms']) ? trim($_POST['dp_terms']) : '12');
        $dp_terms = in_array($raw_dp_terms, $allowed_dp_terms) ? (string)$raw_dp_terms : '12';
        
        // Force to string and ensure it's exactly one of the allowed values
        $dp_terms = (string)$dp_terms;
        if (!in_array($dp_terms, $allowed_dp_terms)) {
            $dp_terms = '12'; // Fallback to default
        }
        
        // Additional validation - ensure it's exactly what we expect
        $dp_terms = trim($dp_terms);
        $dp_terms = preg_replace('/[^0-9]/', '', $dp_terms); // Remove any non-numeric characters
        if (!in_array($dp_terms, $allowed_dp_terms)) {
            $dp_terms = '12'; // Final fallback
        }
    
        $pagibig_bank_approval = isset($_POST['pagibig_bank_approval']) ? 1 : 0;
        $loan_takeout = isset($_POST['loan_takeout']) ? 1 : 0;
        $turnover = isset($_POST['turnover']) ? 1 : 0;
    
        // Handle receipt uploads (only DP receipts)
        $receipt_uploads = [];
        if (isset($_FILES['dp_receipt']) && !empty($_FILES['dp_receipt']['name'][0])) {
            $receipt_uploads['downpayment'] = handleReceiptUpload($_FILES['dp_receipt'], $lead_id, 'downpayment');
        }
        
        if (!$spot_dp) {
            // Count existing receipts for this lead
            $receipt_count_stmt = $conn->prepare("SELECT COUNT(*) as total_receipts FROM stage_receipts WHERE lead_id = ? AND stage_type = 'downpayment'");
            if (!$receipt_count_stmt) {
                throw new Exception("Failed to prepare receipt count query: " . $conn->error);
            }
            
            $receipt_count_stmt->bind_param("i", $lead_id);
            if (!$receipt_count_stmt->execute()) {
                throw new Exception("Failed to execute receipt count query: " . $receipt_count_stmt->error);
            }
            
            $receipt_count_result = $receipt_count_stmt->get_result();
            $total_receipts = $receipt_count_result->fetch_assoc()['total_receipts'];
            $receipt_count_stmt->close();
            
            // Add newly uploaded receipts to count
            if (!empty($receipt_uploads['downpayment'])) {
                $total_receipts += count($receipt_uploads['downpayment']);
            }
            
            // Current stage should be at least 1 if there are any receipts, and not exceed total terms
            $current_dp_stage = $total_receipts > 0 ? max(1, min($total_receipts, intval($dp_terms))) : 0;
        } else {
            $current_dp_stage = 1; // Spot DP is always stage 1
        }
        
        // Calculate total stages based on DP terms
        $total_dp_stages = intval($dp_terms);
        
        // Calculate progress rate
        $progress_rate = $total_dp_stages > 0 ? ($current_dp_stage / $total_dp_stages) * 100 : 0;
        
        // Ensure dp_terms is a string for ENUM compatibility
        $dp_terms = (string)$dp_terms;
    
        if (!empty($receipt_uploads['downpayment'])) {
            foreach ($receipt_uploads['downpayment'] as $receipt) {
                $receipt_stmt = $conn->prepare("INSERT INTO stage_receipts (lead_id, stage_type, filename, original_name, file_path, file_size, mime_type) VALUES (?, 'downpayment', ?, ?, ?, ?, ?)");
                if (!$receipt_stmt) {
                    throw new Exception("Failed to prepare receipt insert query: " . $conn->error);
                }
                
                $file_path = 'uploads/receipts/' . $receipt['filename'];
                $file_size = isset($receipt['size']) ? $receipt['size'] : null;
                $mime_type = isset($receipt['type']) ? $receipt['type'] : null;
                $receipt_stmt->bind_param("isssis", $lead_id, $receipt['filename'], $receipt['original_name'], $file_path, $file_size, $mime_type);
                
                if (!$receipt_stmt->execute()) {
                    throw new Exception("Failed to insert receipt: " . $receipt_stmt->error);
                }
                $receipt_stmt->close();
            }
            
            // Update the current_dp_stage in the database after uploading receipts
            updateCurrentDpStage($conn, $lead_id);
        }

        // Check if tracker entry exists for this lead
        $check_stmt = $conn->prepare("SELECT id FROM downpayment_tracker WHERE lead_id = ? ORDER BY created_at DESC LIMIT 1");
        if (!$check_stmt) {
            throw new Exception("Failed to prepare tracker check query: " . $conn->error);
        }
        
        $check_stmt->bind_param("i", $lead_id);
        if (!$check_stmt->execute()) {
            throw new Exception("Failed to execute tracker check query: " . $check_stmt->error);
        }
        
        $check_result = $check_stmt->get_result();
        $existing_tracker = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($existing_tracker) {
            // Update existing tracker entry
            $update_stmt = $conn->prepare("
                UPDATE downpayment_tracker 
                SET reservation_date = ?, requirements_complete = ?, spot_dp = ?, dp_terms = ?, 
                    current_dp_stage = ?, total_dp_stages = ?, progress_rate = ?, 
                    pagibig_bank_approval = ?, loan_takeout = ?, turnover = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            if (!$update_stmt) {
                throw new Exception("Failed to prepare tracker update query: " . $conn->error);
            }
            
            $update_stmt->bind_param(
                "siisiidiiii",
                $reservation_date,
                $requirements_complete,
                $spot_dp,
                $dp_terms,
                $current_dp_stage,
                $total_dp_stages,
                $progress_rate,
                $pagibig_bank_approval,
                $loan_takeout,
                $turnover,
                $existing_tracker['id']
            );
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update tracker: " . $update_stmt->error);
            }
            $update_stmt->close();
        } else {
            // Insert new tracker entry if none exists
            $insert_stmt = $conn->prepare("
                INSERT INTO downpayment_tracker 
                (lead_id, reservation_date, requirements_complete, spot_dp, dp_terms, 
                 current_dp_stage, total_dp_stages, progress_rate, pagibig_bank_approval, 
                 loan_takeout, turnover, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$insert_stmt) {
                throw new Exception("Failed to prepare tracker insert query: " . $conn->error);
            }

            $insert_stmt->bind_param(
                "isiisidiiii",
                $lead_id,
                $reservation_date,
                $requirements_complete,
                $spot_dp,
                $dp_terms,
                $current_dp_stage,
                $total_dp_stages,
                $progress_rate,
                $pagibig_bank_approval,
                $loan_takeout,
                $turnover
            );
            
            if (!$insert_stmt->execute()) {
                throw new Exception("Failed to insert tracker: " . $insert_stmt->error);
            }
            $insert_stmt->close();
        }

        $success_message = "Tracker updated successfully! Current DP Stage: $current_dp_stage out of $total_dp_stages";
        if (!empty($receipt_uploads['downpayment'])) {
            $receipt_count = count($receipt_uploads['downpayment']);
            $success_message .= " ($receipt_count new receipt(s) uploaded)";
        }
        
        // Add JavaScript to update main dashboard display
        $success_message .= "
        <script>
            // Update main dashboard stage display after successful upload
            if (typeof updateMainDashboardStage === 'function') {
                updateMainDashboardStage($lead_id, $current_dp_stage, $total_dp_stages);
            }
        </script>";
        
        // Add activity log
        addLeadActivity($lead_id, $user_id, "Downpayment Tracker", "Updated downpayment tracker information");
        
        // Award raffle tickets for DP stage progression
        awardRaffleTicketsForDPStage($lead_id, $user_id, $current_dp_stage);
        
        // Award raffle tickets for requirements completion
        $requirements = [
            'requirements_complete' => $requirements_complete,
            'pagibig_bank_approval' => $pagibig_bank_approval,
            'loan_takeout' => $loan_takeout,
            'turnover' => $turnover
        ];
        awardRaffleTicketsForRequirements($lead_id, $user_id, $requirements);
        
        // Award raffle tickets for spot downpayment
        if ($spot_dp) {
            awardRaffleTicketsForSpotDP($lead_id, $user_id, intval($dp_terms));
        }
        
        // Redirect to refresh the page
        $redirect_url = "dp-stage.php?success=1";
        if (!empty($search_query)) $redirect_url .= "&search=" . urlencode($search_query);
        if (!empty($filter_agent)) $redirect_url .= "&agent=" . urlencode($filter_agent);
        if (!empty($filter_developer)) $redirect_url .= "&developer=" . urlencode($filter_developer);
        if (!empty($filter_progress)) $redirect_url .= "&progress=" . urlencode($filter_progress);
        
        header("Location: $redirect_url");
        exit();
        
    } catch (Exception $e) {
        // Log detailed error information
        $error_details = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'post_data' => $_POST,
            'files_data' => $_FILES,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log("DP Stage Form Error: " . json_encode($error_details));
        
        // Set user-friendly error message
        $error_message = "Error processing request. Please try again.";
        
        // In debug mode, show more details
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            $error_message = "Error: " . $e->getMessage() . " (Line: " . $e->getLine() . ")";
        }
        
        // Redirect back with error
        $redirect_url = "dp-stage.php?error=" . urlencode($error_message);
        if (!empty($search_query)) $redirect_url .= "&search=" . urlencode($search_query);
        if (!empty($filter_agent)) $redirect_url .= "&agent=" . urlencode($filter_agent);
        if (!empty($filter_developer)) $redirect_url .= "&developer=" . urlencode($filter_developer);
        if (!empty($filter_progress)) $redirect_url .= "&progress=" . urlencode($filter_progress);
        
        header("Location: $redirect_url");
        exit();
    }
}

// Debug endpoint for production troubleshooting
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo "<h2>Debug Information</h2>";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
    echo "<p><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</p>";
    echo "<p><strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . "</p>";
    echo "<p><strong>Upload Max Filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
    echo "<p><strong>Post Max Size:</strong> " . ini_get('post_max_size') . "</p>";
    echo "<p><strong>Current Directory:</strong> " . getcwd() . "</p>";
    echo "<p><strong>File Path:</strong> " . __FILE__ . "</p>";
    echo "<p><strong>Upload Directory Exists:</strong> " . (file_exists('uploads/receipts/') ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Upload Directory Writable:</strong> " . (is_writable('uploads/receipts/') ? 'Yes' : 'No') . "</p>";
    
    // Test database connection
    try {
        $test_conn = getDbConnection();
        echo "<p><strong>Database Connection:</strong> Success</p>";
        $test_conn->close();
    } catch (Exception $e) {
        echo "<p><strong>Database Connection:</strong> Failed - " . $e->getMessage() . "</p>";
    }
    
    echo "<p><a href='dp-stage.php'>Back to DP Stage</a></p>";
    exit();
}

// Check for success and error messages
$success = '';
$error = '';
if (isset($_GET['success'])) {
    $success = "Tracker updated successfully!";
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $show_completed ? 'Completed' : 'In Progress' ?> Downpayment Leads - Real Estate Lead Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    /* CSS Variables - Updated to blue-only color scheme */
    :root {
        --primary: #2563eb; /* Changed from blue/orange to blue only */
        --primary-dark: #1d4ed8;
        --primary-light: #dbeafe;
        --secondary: #64748b; /* Changed secondary to neutral gray */
        --secondary-light: #f1f5f9;
        --success: #10b981;
        --success-light: #d1fae5;
        --warning: #f59e0b;
        --warning-light: #fef3c7;
        --danger: #ef4444;
        --danger-light: #fee2e2;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;
        --border-radius: 0.75rem;
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    /* Main container styles */
    .container {
        display: flex;
        min-height: 100vh;
        width: 100%;
    }
    
    .main-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
    }
    
    /* Content area styles */
    .content {
        flex: 1;
        padding: 1.5rem;
        width: 100%;
        margin: 0;
        min-height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
    }
    
    .sidebar-collapsed .content {
        max-width: 1200px;
    }
    
    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .content h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 0.5rem;
    }
    
    .content p {
        color: var(--gray-600);
        margin-bottom: 1.5rem;
    }
    
    /* Success message */
    .success-message {
        background-color: var(--success-light);
        color: #065f46;
        border-left: 4px solid var(--success);
        border-radius: var(--border-radius);
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .success-message i {
        margin-right: 0.75rem;
        font-size: 1.25rem;
    }
    
    /* Card styles */
    .card {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        margin-bottom: 1.5rem;
        border: 1px solid var(--gray-200);
        overflow: hidden;
    }
    
    .card-header {
        background-color: var(--gray-50);
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-header h3 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    /* Search and filter section */
    .search-filter-container {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        margin-bottom: 1.5rem;
        border: 1px solid var(--gray-200);
        padding: 1.25rem;
    }
    
    .search-filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }
    
    .search-filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .search-filter-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--gray-700);
        font-size: 0.875rem;
    }
    
    .search-filter-group input,
    .search-filter-group select {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--gray-300);
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: var(--gray-800);
        background-color: #fff;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .search-filter-group input:focus,
    .search-filter-group select:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .search-filter-buttons {
        display: flex;
        gap: 0.75rem;
    }
    
    /* Button styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.25rem;
        border-radius: 0.375rem;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        white-space: nowrap;
        text-decoration: none;
        box-shadow: var(--shadow-sm);
    }
    
    .btn:active {
        transform: translateY(1px);
    }
    
    .btn i {
        margin-right: 0.5rem;
        font-size: 0.875rem;
    }
    
    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: var(--primary-hover);
        box-shadow: var(--shadow);
    }

    /* Force blue theme for specific UI areas */
    .view-toggle .btn,
    .search-filter-buttons .btn,
    #edit_mode_btn {
        background: #3b82f6;
        border-color: #3b82f6;
        color: #ffffff;
    }

    .view-toggle .btn:hover,
    .search-filter-buttons .btn:hover,
    #edit_mode_btn:hover {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
    }
    
    .btn-outline {
        background-color: white;
        border-color: var(--gray-300);
        color: var(--gray-700);
    }
    
    .btn-outline:hover {
        background-color: var(--gray-100);
        color: var(--gray-900);
    }
    
    .btn-success {
        background-color: var(--success);
        color: white;
    }
    
    .btn-success:hover {
        background-color: #059669;
        box-shadow: var(--shadow);
    }
    
    /* Table styles */
    .table-container {
        overflow-x: auto;
        border-radius: var(--border-radius);
    }
    
    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.875rem;
    }
    
    .table th {
        background-color: var(--gray-50);
        color: var(--gray-700);
        font-weight: 600;
        text-align: left;
        padding: 0.75rem 1.5rem;
        border-bottom: 2px solid var(--gray-200);
        white-space: nowrap;
    }
    
    .table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        vertical-align: top;
    }
    
    .table tr:last-child td {
        border-bottom: none;
    }
    
    .table tr:hover {
        background-color: var(--gray-50);
    }
    
    .table-empty {
        text-align: center;
        padding: 3rem 0;
        color: var(--gray-500);
    }
    
    .table-empty i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    /* Progress bar */
    .progress-container {
        height: 0.5rem;
        background-color: var(--gray-200);
        border-radius: 1rem;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }
    
    .progress-bar {
        height: 100%;
        border-radius: 1rem;
        transition: width 0.3s ease;
    }
    
    .progress-low {
        background-color: var(--danger);
    }
    
    .progress-medium {
        background-color: var(--warning);
    }
    
    .progress-high {
        background-color: var(--success);
    }
    
    /* Status badges */
    .status-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.375rem 0.75rem;
        border-radius: 2rem;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    
    .status-badge i {
        margin-right: 0.375rem;
        font-size: 0.875rem;
    }
    
    .status-complete {
        background-color: var(--success-light);
        color: #065f46;
    }
    
    .status-pending {
        background-color: var(--warning-light);
        color: #92400e;
    }
    
    /* Client info */
    .client-name {
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 0.25rem;
    }
    
    .client-details {
        color: var(--gray-600);
        font-size: 0.75rem;
        margin-top: 0.25rem;
    }
    
    /* Action buttons */
    .action-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        border-radius: 0.375rem;
        margin-bottom: 0.5rem;
        width: 100%;
        justify-content: center;
    }
    
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    /* Make action buttons all blue */
    .action-buttons .btn {
        background: #3b82f6;
        color: white;
        border: 1px solid #3b82f6;
        transition: all 0.2s ease;
    }
    
    .action-buttons .btn:hover {
        background: #2563eb;
        border-color: #2563eb;
        transform: translateY(-1px);
    }
    
    .action-buttons .btn-primary {
        background: #3b82f6;
        border-color: #3b82f6;
    }
    
    .action-buttons .btn-primary:hover {
        background: #2563eb;
        border-color: #2563eb;
    }
    
    /* Modal styles - Increased modal width */
    #dpDetailsModal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.2s ease-out;
    }
    
    #dpDetailsModal .modal-content {
        background-color: #fff;
        margin: 2rem auto;
        border-radius: 1rem;
        box-shadow: var(--shadow-lg);
        width: 90%; /* Modest width by default */
        max-width: 1000px; /* Hard cap to avoid oversized modals */
        position: relative;
        max-height: calc(100vh - 4rem);
        display: flex;
        flex-direction: column;
        animation: slideIn 0.3s ease-out;
    }
    
    /* Responsive modal sizing - just make the modal bigger on larger screens */
    @media (min-width: 1024px) {
        #dpDetailsModal .modal-content {
            width: 85%;
            max-width: 1000px; /* keep cap steady */
        }
    }
    
    @media (min-width: 1280px) {
        #dpDetailsModal .modal-content {
            width: 80%;
            max-width: 1000px; /* keep cap steady */
        }
    }
    
    @media (min-width: 1536px) {
        #dpDetailsModal .modal-content {
            width: 78%;
            max-width: 1000px; /* keep cap steady */
        }
    }
    
    #dpDetailsModal .modal-header {
        background: var(--primary);
        color: white;
        padding: 1.5rem 2rem;
        border-radius: 1rem 1rem 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    #dpDetailsModal .modal-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    #dpDetailsModal .modal-header h3 i {
        font-size: 1.75rem;
        opacity: 0.9;
    }
    
    #dpDetailsModal .modal-header .close {
        font-size: 2rem;
        font-weight: 300;
        cursor: pointer;
        line-height: 1;
        padding: 0.5rem;
        border-radius: 0.5rem;
        transition: all 0.2s ease;
        opacity: 0.8;
        background: rgba(255, 255, 255, 0.1);
        width: 3rem;
        height: 3rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #dpDetailsModal .modal-header .close:hover {
        opacity: 1;
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }
    
    #dpDetailsModal .modal-body {
        padding: 2rem;
        overflow-y: auto;
        max-height: calc(100vh - 16rem);
        flex: 1;
    }
    
    #dpDetailsModal .modal-footer {
        padding: 1.5rem 2rem;
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        background-color: var(--gray-50);
        border-radius: 0 0 1rem 1rem;
        flex-shrink: 0;
    }
    
    /* View DP Modal Specific Styles */
    .client-info-card {
        background: var(--gray-50);
        border: 2px solid var(--primary);
        border-radius: 1rem;
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .client-info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary);
    }
    
    .client-info-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .client-info-main {
        flex: 1;
    }
    
    .client-name-large {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--gray-900);
        margin-bottom: 0.5rem;
        line-height: 1.2;
    }
    
    .project-info {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .project-detail {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--gray-700);
        font-size: 1rem;
    }
    
    .project-detail i {
        color: var(--primary);
        width: 1.25rem;
        text-align: center;
    }
    
    .price-display {
        background: var(--success);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 2rem;
        font-size: 1.25rem;
        font-weight: 700;
        text-align: center;
        box-shadow: var(--shadow-md);
    }
    
    /* DP Terms Display */
    .dp-terms-section {
        margin-bottom: 2rem;
    }
    
    .section-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--gray-200);
    }
    
    .section-title i {
        color: var(--primary);
        font-size: 1.5rem;
    }
    
    .dp-terms-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .terms-card {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 1rem;
        padding: 2rem;
        position: relative;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
    }
    
    .terms-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .terms-card.spot-dp {
        background: var(--success-light);
        border-color: var(--success);
    }
    
    .terms-card.installment {
        background: var(--primary-light);
        border-color: var(--primary);
    }
    
    .reservation-card {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 1rem;
        padding: 2rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
    }
    
    .reservation-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .reservation-icon {
        width: 4rem;
        height: 4rem;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 1rem;
    }
    
    .reservation-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: 0.5rem;
    }
    
    .reservation-date {
        font-size: 1.125rem;
        color: var(--gray-600);
        font-weight: 500;
    }
    
    /* Monthly Progress Section */
    .monthly-progress-section {
        margin-bottom: 2rem;
    }
    
    .monthly-progress-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .monthly-progress-item {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 0.75rem;
        padding: 1rem;
        text-align: center;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .monthly-progress-item.completed {
        border-color: var(--success);
        background: var(--success-light);
    }
    
    .monthly-progress-item.current {
        border-color: var(--warning);
        background: var(--warning-light);
        animation: pulse 2s infinite;
    }
    
    .monthly-progress-item.pending {
        border-color: var(--gray-300);
        background: var(--gray-50);
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
    }
    
    .monthly-progress-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--gray-300);
    }
    
    .monthly-progress-item.completed::before {
        background: var(--success);
    }
    
    .monthly-progress-item.current::before {
        background: var(--warning);
    }
    
    .monthly-progress-item.pending::before {
        background: var(--gray-300);
    }
    
    .month-number {
        font-size: 1.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .monthly-progress-item.completed .month-number {
        color: var(--success);
    }
    
    .monthly-progress-item.current .month-number {
        color: var(--warning);
    }
    
    .monthly-progress-item.pending .month-number {
        color: var(--gray-500);
    }
    
    .month-status {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .monthly-progress-item.completed .month-status {
        color: #065f46;
    }
    
    .monthly-progress-item.current .month-status {
        color: #92400e;
    }
    
    .monthly-progress-item.pending .month-status {
        color: var(--gray-500);
    }
    
    /* Overall Progress Section */
    .progress-section {
        margin-bottom: 2rem;
    }
    
    .progress-overview-card {
        background: var(--gray-50);
        border: 2px solid var(--gray-200);
        border-radius: 1rem;
        padding: 2rem;
        text-align: center;
    }
    
    .progress-circle-container {
        position: relative;
        display: inline-block;
        margin-bottom: 1.5rem;
    }
    
    .progress-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .progress-circle::before {
        content: '';
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: white;
        position: absolute;
    }
    
    .progress-percentage {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--gray-800);
        position: relative;
        z-index: 1;
    }
    
    .progress-label {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-600);
        margin-bottom: 1rem;
    }
    
    /* Milestones Section */
    .milestones-section {
        margin-bottom: 2rem;
    }
    
    .milestones-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .milestone-card {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 1rem;
        padding: 0;
        transition: all 0.3s ease;
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    
    .milestone-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .milestone-card.completed {
        border-color: var(--success);
        background: var(--success-light);
    }
    
    .milestone-card.pending {
        border-color: var(--gray-300);
        background: var(--gray-50);
    }
    
    .milestone-content {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        gap: 1.5rem;
    }
    
    .milestone-icon-container {
        width: 4rem;
        height: 4rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        flex-shrink: 0;
    }
    
    .milestone-card.completed .milestone-icon-container {
        background: var(--success);
        color: white;
    }
    
    .milestone-card.pending .milestone-icon-container {
        background: var(--gray-300);
        color: var(--gray-600);
    }
    
    .milestone-info {
        flex: 1;
    }
    
    .milestone-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .milestone-card.completed .milestone-title {
        color: #065f46;
    }
    
    .milestone-card.pending .milestone-title {
        color: var(--gray-700);
    }
    
    .milestone-description {
        font-size: 0.875rem;
        line-height: 1.5;
    }
    
    .milestone-card.completed .milestone-description {
        color: #047857;
    }
    
    .milestone-card.pending .milestone-description {
        color: var(--gray-600);
    }
    
    .milestone-status-indicator {
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .milestone-card.completed .milestone-status-indicator {
        background: var(--success);
        color: white;
    }
    
    .milestone-card.pending .milestone-status-indicator {
        background: var(--gray-200);
        color: var(--gray-500);
    }
    
    /* Form styles for edit modal */
    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .form-section:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--gray-700);
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: 0.5rem;
        font-size: 1rem;
        color: var(--gray-800);
        background-color: #fff;
        transition: all 0.2s ease;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .form-check {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        padding: 1rem;
        background: var(--gray-50);
        border: 2px solid var(--gray-200);
        border-radius: 0.75rem;
        transition: all 0.2s ease;
    }
    
    .form-check:hover {
        background: var(--gray-100);
        border-color: var(--gray-300);
    }
    
    .form-check-input {
        margin-right: 1rem;
        width: 1.25rem;
        height: 1.25rem;
    }
    
    .form-check label {
        font-weight: 600;
        color: var(--gray-700);
        cursor: pointer;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-check-input:checked + label {
        color: var(--primary);
    }
    
    .form-check:has(.form-check-input:checked) {
        background: var(--primary-light);
        border-color: var(--primary);
    }
    
    .info-message {
        background: var(--primary-light);
        border: 2px solid var(--primary);
        color: var(--primary);
        padding: 1rem 1.5rem;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        margin-top: 1.5rem;
    }
    
    .info-message i {
        margin-right: 0.75rem;
        font-size: 1.25rem;
    }
    
    /* File Upload Styles */
    .file-upload-section {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--border-radius);
        padding: 1rem;
        margin: 0.75rem 0;
    }
    
    .file-input {
        width: 100%;
        padding: 0.75rem;
        border: 2px dashed var(--gray-300);
        border-radius: var(--border-radius);
        background: var(--gray-50);
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }
    
    .file-input:hover {
        border-color: var(--primary);
        background: var(--primary-light);
    }
    
    .file-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    /* DP Receipt Section Styles */
    .dp-receipt-section {
        background: var(--gray-50);
        border: 2px solid var(--gray-200);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-top: 1rem;
    }
    
    .uploaded-receipts {
        margin-top: 1.5rem;
    }
    
    .receipt-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .receipt-item {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: var(--border-radius);
        padding: 1rem;
        text-align: center;
        transition: all 0.2s ease;
        position: relative;
    }
    
    .receipt-item:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .receipt-image-container {
        cursor: pointer;
        margin-bottom: 0.5rem;
    }
    
    .receipt-thumbnail {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: var(--border-radius);
    }
    
    .receipt-info {
        font-size: 0.75rem;
        color: var(--gray-600);
        margin-bottom: 0.5rem;
    }
    
    .receipt-delete-btn {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        width: 2rem; /* Larger for easier tapping */
        height: 2rem; /* Larger for easier tapping */
        border: none;
        border-radius: 9999px;
        background: var(--danger);
        color: white;
        font-size: 0.875rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 1; /* Always visible */
        transition: all 0.2s ease;
        z-index: 20; /* Ensure above image */
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    
    .receipt-item:hover .receipt-delete-btn {
        transform: scale(1.05);
    }
    
    .receipt-delete-btn:hover {
        background: #dc2626;
        transform: scale(1.1);
    }
    
    .receipt-stage {
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 0.25rem;
    }
    
    /* Image Modal Styles */
    .image-modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        animation: fadeIn 0.2s ease-out;
    }
    
    .image-modal-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        max-width: 90%;
        max-height: 90%;
        background: white;
        border-radius: var(--border-radius);
        padding: 1rem;
        box-shadow: var(--shadow-lg);
    }
    
    .image-modal img {
        max-width: 100%;
        max-height: 80vh;
        object-fit: contain;
        border-radius: var(--border-radius);
    }
    
    .image-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .image-modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-900);
    }
    
    .image-modal-close {
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--gray-500);
        padding: 0.5rem;
        border-radius: var(--border-radius);
        transition: all 0.2s ease;
    }
    
    .image-modal-close:hover {
        background: var(--gray-100);
        color: var(--gray-900);
    }
    
    .view-toggle {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }
    
    .view-toggle .btn {
        min-width: 140px;
    }

    /* Added style for disabled button */
    .btn:disabled, .btn[disabled] {
        opacity: 0.6;
        cursor: not-allowed;
        box-shadow: none;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dp-terms-grid {
            grid-template-columns: 1fr;
        }
        
        .client-info-header {
            flex-direction: column;
            gap: 1rem;
        }
        
        .monthly-progress-grid {
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        }
        
        .milestone-content {
            padding: 1rem;
            gap: 1rem;
        }
        
        .milestone-icon-container {
            width: 3rem;
            height: 3rem;
            font-size: 1.5rem;
        }
    }
    
    /* Animations */
    @keyframes slideIn {
        from { 
            opacity: 0;
            transform: translateY(-30px) scale(0.95);
        }
        to { 
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    @keyframes progressFill {
        from { width: 0%; }
        to { width: var(--progress-width); }
    }
    
    .progress-bar.animate {
        animation: progressFill 1.5s ease-out;
    }
    
    /* No results styling */
    .no-results {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--gray-500);
    }
    
    .no-results i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.5;
        color: var(--gray-400);
    }
    
    .no-results h4 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
        color: var(--gray-700);
        font-weight: 600;
    }
    
    .no-results p {
        margin: 0;
        font-size: 1rem;
        line-height: 1.6;
    }
    
    .no-results a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }
    
    .no-results a:hover {
        text-decoration: underline;
    }

    /* Updated color scheme to use only blue variants */
    .monthly-progress-item.completed {
        background-color: #3b82f6;
        color: white;
        border: 2px solid #1d4ed8;
    }
    
    .monthly-progress-item.current {
        background-color: #60a5fa;
        color: white;
        border: 2px solid #2563eb;
        animation: pulse 2s infinite;
    }
    
    .monthly-progress-item.pending {
        background-color: #f1f5f9;
        color: #64748b;
        border: 2px solid #e2e8f0;
    }
    
    .progress-bar-fill {
        background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .btn-primary {
        background-color: #3b82f6;
        border-color: #3b82f6;
    }
    
    .btn-primary:hover {
        background-color: #2563eb;
        border-color: #2563eb;
    }
    
    .text-primary {
        color: #3b82f6 !important;
    }
    
    .bg-primary {
        background-color: #3b82f6 !important;
    }
</style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <div class="content-header">
                    <div>
                        <h1><?= $show_completed ? 'Completed Downpayment Leads' : 'Downpayment Stage Tracker' ?></h1>
                        <p><?= $show_completed ? 'View all completed downpayment leads.' : 'Track and manage leads in the downpayment stage.' ?></p>
                    </div>
                    <div class="view-toggle">
                        <a href="dp-stage.php" class="btn <?= !$show_completed ? 'btn-primary' : 'btn-outline' ?>">
                            <i class="fas fa-clock"></i> In Progress
                        </a>
                        <a href="dp-stage.php?view=completed" class="btn <?= $show_completed ? 'btn-primary' : 'btn-outline' ?>">
                            <i class="fas fa-check-circle"></i> Completed
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="error-message" style="background-color: var(--danger-light); color: #991b1b; border-left: 4px solid var(--danger); border-radius: var(--border-radius); padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; animation: fadeIn 0.5s ease-out;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.75rem; font-size: 1.25rem;"></i> <?= $error ?>
                </div>
                <?php endif; ?>
                
                <!-- Search and Filter Section -->
                <div class="search-filter-container">
                    <form class="search-filter-form" method="GET" action="dp-stage.php">
                        <?php if ($show_completed): ?>
                        <input type="hidden" name="view" value="completed">
                        <?php endif; ?>
                        <div class="search-filter-group">
                            <label for="search">Search Client</label>
                            <input type="text" id="search" name="search" placeholder="Name, phone or email" value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                        
                        <div class="search-filter-group">
                            <label for="agent">Filter by Agent</label>
                            <select id="agent" name="agent">
                                <option value="">All Agents</option>
                                <?php foreach ($agents as $id => $name): ?>
                                <option value="<?= $id ?>" <?= $filter_agent == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="search-filter-group">
                            <label for="developer">Filter by Developer</label>
                            <select id="developer" name="developer">
                                <option value="">All Developers</option>
                                <?php foreach ($developers as $dev): ?>
                                <option value="<?= $dev ?>" <?= $filter_developer == $dev ? 'selected' : '' ?>><?= htmlspecialchars($dev) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (!$show_completed): ?>
                        <div class="search-filter-group">
                            <label for="progress">Filter by Progress</label>
                            <select id="progress" name="progress">
                                <option value="">All Progress</option>
                                <option value="low" <?= $filter_progress == 'low' ? 'selected' : '' ?>>Low (0-33%)</option>
                                <option value="medium" <?= $filter_progress == 'medium' ? 'selected' : '' ?>>Medium (34-66%)</option>
                                <option value="high" <?= $filter_progress == 'high' ? 'selected' : '' ?>>High (67-100%)</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="search-filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="dp-stage.php<?= $show_completed ? '?view=completed' : '' ?>" class="btn btn-outline">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas <?= $show_completed ? 'fa-check-circle' : 'fa-chart-line' ?>"></i> 
                            <?= $show_completed ? 'Completed Leads' : 'In Progress Leads' ?>
                            <?php if (count($leads) > 0): ?>
                            <span style="font-size: 0.875rem; color: var(--gray-500); margin-left: 0.5rem;">
                                (<?= count($leads) ?> <?= count($leads) == 1 ? 'lead' : 'leads' ?>)
                            </span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($leads)): ?>
                        <div class="no-results">
                            <i class="fas <?= $show_completed ? 'fa-check-circle' : 'fa-search' ?>"></i>
                            <h4>No <?= $show_completed ? 'completed' : '' ?> leads found</h4>
                            <p>
                                <?php if (!$show_completed && (!empty($search_query) || !empty($filter_agent) || !empty($filter_developer) || !empty($filter_progress))): ?>
                                    Try adjusting your search filters or <a href="dp-stage.php">view all leads</a>.
                                <?php else: ?>
                                    <?= $show_completed ? 'Completed leads will appear here once all milestones are achieved.' : 'There are currently no leads in the downpayment stage.' ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Agent</th>
                                        <th>Project Details</th>
                                        <th>DP Terms</th>
                                        <th>Current Stage</th>
                                        <th>Progress</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leads as $lead): 
                                        $current_tracker = $trackers[$lead['id']] ?? null;
                                        $tracker_json = json_encode($current_tracker);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="client-name"><?= htmlspecialchars($lead['client_name']) ?></div>
                                            <div class="client-details">
                                                <?php if (!empty($lead['phone'])): ?>
                                                <div><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($lead['phone']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($lead['email'])): ?>
                                                <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($lead['email']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($lead['agent_name']) ?></td>
                                        <td>
                                            <div><strong><?= htmlspecialchars($lead['developer']) ?></strong></div>
                                            <div class="client-details"><?= htmlspecialchars($lead['project_model']) ?></div>
                                            <?php if (!empty($lead['price'])): ?>
                                            <div class="client-details">₱<?= number_format($lead['price'], 2) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($trackers[$lead['id']])): ?>
                                                <?php if ($trackers[$lead['id']]['spot_dp']): ?>
                                                    <span class="status-badge status-complete">
                                                        <i class="fas fa-check"></i> Spot DP
                                                    </span>
                                                <?php else: ?>
                                                <span class="status-badge <?= $trackers[$lead['id']]['dp_terms'] <= 12 ? 'status-complete' : 'status-pending' ?>">
                                                    <?= htmlspecialchars($trackers[$lead['id']]['dp_terms']) ?> months
                                                </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--gray-500); font-style: italic;">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($trackers[$lead['id']])): ?>
                                                <?php if ($trackers[$lead['id']]['spot_dp']): ?>
                                                    <div>
                                                        <strong>Spot Downpayment</strong>
                                                    </div>
                                                <?php else: ?>
                                                <div class="stage-display" id="stage_display_<?= $lead['id'] ?>">
                                                    <strong>Month <?= htmlspecialchars($trackers[$lead['id']]['current_dp_stage']) ?></strong> of 
                                                    <?= htmlspecialchars($trackers[$lead['id']]['total_dp_stages']) ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($trackers[$lead['id']]['reservation_date']): ?>
                                                    <div class="client-details">
                                                        <i class="far fa-calendar-check"></i> Reserved: <?= date('M d, Y', strtotime($trackers[$lead['id']]['reservation_date'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--gray-500); font-style: italic;">Not started</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $progress = 0;
                                            $progressClass = 'progress-low';
                                            
                                            if (isset($trackers[$lead['id']])) {
                                                $progress = $trackers[$lead['id']]['progress_rate'];
                                                if ($progress >= 66) {
                                                    $progressClass = 'progress-high';
                                                } elseif ($progress >= 33) {
                                                    $progressClass = 'progress-medium';
                                                }
                                            }
                                            ?>
                                            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                                <div class="progress-container" style="width: 100px; margin-right: 0.75rem;">
                                                    <div class="progress-bar <?= $progressClass ?>" style="width: <?= number_format($progress, 0) ?>%;"></div>
                                                </div>
                                                <span style="font-weight: 600;"><?= number_format($progress, 0) ?>%</span>
                                            </div>
                                            
                                            <?php if (isset($trackers[$lead['id']])): ?>
                                                <div class="status-badges">
                                                    <span class="status-badge <?= $trackers[$lead['id']]['requirements_complete'] ? 'status-complete' : 'status-pending' ?>">
                                                        <i class="fas <?= $trackers[$lead['id']]['requirements_complete'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Requirements
                                                    </span>
                                                    <span class="status-badge <?= $trackers[$lead['id']]['pagibig_bank_approval'] ? 'status-complete' : 'status-pending' ?>">
                                                        <i class="fas <?= $trackers[$lead['id']]['pagibig_bank_approval'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Approval
                                                    </span>
                                                    <span class="status-badge <?= $trackers[$lead['id']]['loan_takeout'] ? 'status-complete' : 'status-pending' ?>">
                                                        <i class="fas <?= $trackers[$lead['id']]['loan_takeout'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Takeout
                                                    </span>
                                                    <span class="status-badge <?= $trackers[$lead['id']]['turnover'] ? 'status-complete' : 'status-pending' ?>">
                                                        <i class="fas <?= $trackers[$lead['id']]['turnover'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Turnover
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div style="font-size: 0.75rem; color: var(--gray-500);">No tracker data</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- Unified Manage DP Button -->
                                                <button class="btn btn-primary action-btn manage-dp-btn" 
                                                        data-lead-id="<?= $lead['id'] ?>"
                                                        data-client-name="<?= htmlspecialchars($lead['client_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-developer="<?= htmlspecialchars($lead['developer'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-project-model="<?= htmlspecialchars($lead['project_model'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-price="<?= $lead['price'] ?? 0 ?>"
                                                        data-tracker-data="<?= $current_tracker ? htmlspecialchars(json_encode($current_tracker), ENT_QUOTES, 'UTF-8') : '' ?>"
                                                        data-mode="view"
                                                        onclick="handleManageDpClick(this); return false;">
                                                    <i class="fas fa-tasks"></i> <span>Manage DP</span>
                                                </button>
                                                
                                                <a href="lead-details.php?id=<?= $lead['id'] ?>" class="btn btn-outline action-btn">
                                                    <i class="fas fa-user"></i> <span>Profile</span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Unified DP Details Modal -->
    <div id="dpDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal_title"><i class="fas fa-chart-pie"></i> Downpayment Details</h3>
                <span class="close" onclick="closeDpDetailsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- View Mode Content -->
                <div id="view_mode_content">
                    <!-- Client Information Card -->
                    <div class="client-info-card">
                        <div class="client-info-header">
                            <div class="client-info-main">
                                <div id="view_client_name" class="client-name-large"></div>
                                <div class="project-info">
                                    <div class="project-detail">
                                        <i class="fas fa-building"></i>
                                        <span id="view_developer"></span>
                                    </div>
                                    <div class="project-detail">
                                        <i class="fas fa-home"></i>
                                        <span id="view_project_model"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="price-display" id="view_price">
                                <!-- Price will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- DP Terms Section -->
                    <div class="dp-terms-section">
                        <div class="section-title">
                            <i class="fas fa-credit-card"></i>
                            Downpayment Terms
                        </div>
                        <div class="dp-terms-grid">
                            <div id="dp_terms_card" class="terms-card">
                                <!-- Will be populated by JavaScript -->
                            </div>
                            <div id="reservation_card" class="reservation-card">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Progress Section -->
                    <div class="monthly-progress-section" id="monthly_progress_section" style="display: none;">
                        <div class="section-title">
                            <i class="fas fa-calendar-check"></i>
                            Monthly Payment Progress
                        </div>
                        <div class="monthly-progress-grid" id="monthly_progress_grid">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Overall Progress Section -->
                    <div class="progress-section">
                        <div class="section-title">
                            <i class="fas fa-chart-line"></i>
                            Overall Progress
                        </div>
                        <div class="progress-overview-card">
                            <div class="progress-circle-container">
                                <div class="progress-circle" id="progress_circle">
                                    <div class="progress-percentage" id="view_progress_percentage">0%</div>
                                </div>
                            </div>
                            <div class="progress-label">Project Completion</div>
                        </div>
                    </div>

                    <!-- Milestones Section -->
                    <div class="milestones-section">
                        <div class="section-title">
                            <i class="fas fa-tasks"></i>
                            Project Milestones
                        </div>
                        <div class="milestones-list">
                            <div class="milestone-card" id="milestone_requirements">
                                <div class="milestone-content">
                                    <div class="milestone-icon-container">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="milestone-info">
                                        <div class="milestone-title">Requirements Complete</div>
                                        <div class="milestone-description">All required documents submitted and verified by the processing team</div>
                                    </div>
                                    <div class="milestone-status-indicator">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="milestone-card" id="milestone_dp_stage">
                                <div class="milestone-content">
                                    <div class="milestone-icon-container">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div class="milestone-info">
                                        <div class="milestone-title">Downpayment Stage</div>
                                        <div class="milestone-description" id="dp_stage_description">Monthly payment progress tracking</div>
                                    </div>
                                    <div class="milestone-status-indicator">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="milestone-card" id="milestone_approval">
                                <div class="milestone-content">
                                    <div class="milestone-icon-container">
                                        <i class="fas fa-stamp"></i>
                                    </div>
                                    <div class="milestone-info">
                                        <div class="milestone-title">Pag-IBIG/Bank Approval</div>
                                        <div class="milestone-description">Loan application approved by financial institution</div>
                                    </div>
                                    <div class="milestone-status-indicator">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="milestone-card" id="milestone_takeout">
                                <div class="milestone-content">
                                    <div class="milestone-icon-container">
                                        <i class="fas fa-money-check-alt"></i>
                                    </div>
                                    <div class="milestone-info">
                                        <div class="milestone-title">Loan Takeout</div>
                                        <div class="milestone-description">Loan amount released and processed for property purchase</div>
                                    </div>
                                    <div class="milestone-status-indicator">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="milestone-card" id="milestone_turnover">
                                <div class="milestone-content">
                                    <div class="milestone-icon-container">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div class="milestone-info">
                                        <div class="milestone-title">Property Turnover</div>
                                        <div class="milestone-description">Property keys and documents handed over to client</div>
                                    </div>
                                    <div class="milestone-status-indicator">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Receipts Section (View Mode) -->
                    <div class="receipts-section">
                        <div class="section-title">
                            <i class="fas fa-receipt"></i>
                            Uploaded Receipts
                        </div>
                        <div class="uploaded-receipts" id="view_uploaded_receipts">
                            <!-- Receipts will be displayed here -->
                        </div>
                    </div>
                </div>

                <!-- Edit Mode Content (Form) -->
                <form id="trackerForm" method="post" enctype="multipart/form-data" style="display: none;">
                    <input type="hidden" name="lead_id" id="edit_lead_id">
                    <input type="hidden" name="update_tracker" value="1">
                    
                    <div class="form-section">
                        <div class="form-group">
                            <label for="edit_client_name">Client Name:</label>
                            <input type="text" id="edit_client_name" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_project_details">Project:</label>
                            <input type="text" id="edit_project_details" readonly>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-group">
                            <label for="edit_reservation_date">Reservation Date:</label>
                            <input type="date" id="edit_reservation_date" name="reservation_date">
                            <div id="reservation_date_error" style="color: var(--danger); font-size: 0.875rem; margin-top: 0.25rem; display: none;"></div>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="edit_spot_dp" name="spot_dp" class="form-check-input">
                            <label for="edit_spot_dp">
                                <i class="fas fa-lightning-bolt"></i>
                                Spot Downpayment (Full payment upfront)
                            </label>
                        </div>
                    </div>
                    
                    <div id="edit_terms_section" class="form-section">
                        <div class="form-group">
                            <label for="edit_dp_terms">Downpayment Terms:</label>
                            <select id="edit_dp_terms" name="dp_terms" required>
                                <option value="6">6 months</option>
                                <option value="9">9 months</option>
                                <option value="12" selected>12 months</option>
                                <option value="15">15 months</option>
                                <option value="18">18 months</option>
                                <option value="24">24 months</option>
                                <option value="36">36 months</option>
                            </select>
                        </div>
                        
                        <!-- Removed Current Downpayment Stage dropdown as requested -->
                        
                        <div class="form-group">
                            <label style="font-size: 1rem; color: var(--gray-700); font-weight: 600; margin-bottom: 1rem;">
                                <i class="fas fa-receipt"></i> DP Payment Receipt Upload
                            </label>
                            
                            <!-- Added receipt counter display -->
                            <div class="receipt-counter" id="receipt_counter" style="background: var(--primary-light); padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1rem; text-align: center; font-weight: 600; color: var(--primary-dark);">
                                <i class="fas fa-receipt"></i> <span id="receipt_count_text">0 out of 12 receipts uploaded</span>
                            </div>
                            
                            <div class="dp-receipt-section">
                                <!-- Single upload field instead of multiple stages -->
                                <div class="single-receipt-upload" style="padding: 1.5rem; border: 2px dashed var(--primary); border-radius: var(--border-radius); background: var(--primary-light); text-align: center;">
                                    <div style="margin-bottom: 1rem;">
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem;"></i>
                                        <div style="font-weight: 600; color: var(--primary-dark); margin-bottom: 0.25rem;">Upload Receipt</div>
                                        <div style="font-size: 0.875rem; color: var(--gray-600);">Take a photo or choose from gallery</div>
                                    </div>

                                    <!-- Explicit options for better mobile UX -->
                                    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; margin-bottom: 0.75rem;">
                                        <button type="button" class="btn btn-primary" onclick="document.getElementById('dp_receipt_camera').click();">
                                            <i class="fas fa-camera"></i> Use Camera
                                        </button>
                                        <button type="button" class="btn btn-outline" onclick="document.getElementById('dp_receipt_gallery').click();">
                                            <i class="fas fa-image"></i> Choose from Gallery
                                        </button>
                                    </div>

                                    <!-- Hidden inputs (same field name so backend stays the same) -->
                                    <input type="file" id="dp_receipt_camera" name="dp_receipt[]" accept="image/*" capture="environment" style="display:none;" onchange="updateReceiptCounter()">
                                    <input type="file" id="dp_receipt_gallery" name="dp_receipt[]" accept=".png,.jpg,.jpeg,image/*" style="display:none;" onchange="updateReceiptCounter()">
                                    <!-- Keep original input for desktop fallback, but hidden to avoid a third prompt -->
                                    <input type="file" id="dp_receipt_single" name="dp_receipt[]" accept=".png,.jpg,.jpeg,image/*" style="display:none;" onchange="updateReceiptCounter()">

                                    <div style="font-size: 0.75rem; color: var(--gray-500);">
                                        Each upload counts as one month of your DP payment plan
                                    </div>
                                </div>
                                
                                <div class="uploaded-receipts" id="uploaded_receipts_display">
                                    <!-- Uploaded receipts will be displayed here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <label style="font-weight: 600; color: var(--gray-700); margin-bottom: 1rem; display: block;">
                            <i class="fas fa-tasks"></i> Project Milestones
                        </label>
                        
                        <div class="form-check">
                            <input type="checkbox" id="edit_requirements_complete" name="requirements_complete" class="form-check-input">
                            <label for="edit_requirements_complete">
                                <i class="fas fa-file-alt"></i>
                                Requirements Complete
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="edit_pagibig_bank_approval" name="pagibig_bank_approval" class="form-check-input">
                            <label for="edit_pagibig_bank_approval">
                                <i class="fas fa-stamp"></i>
                                Pag-IBIG/Bank Approval
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="edit_loan_takeout" name="loan_takeout" class="form-check-input">
                            <label for="edit_loan_takeout">
                                <i class="fas fa-money-check-alt"></i>
                                Loan Takeout
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="edit_turnover" name="turnover" class="form-check-input">
                            <label for="edit_turnover">
                                <i class="fas fa-key"></i>
                                Property Turnover
                            </label>
                        </div>
                    </div>
                    
                    <div class="info-message">
                        <i class="fas fa-info-circle"></i> 
                        Progress is automatically calculated based on completed milestones and current payment stage.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDpDetailsModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="edit_mode_btn" onclick="toggleMode('edit')">
                    <i class="fas fa-edit"></i> Edit Details
                </button>
                <button type="button" class="btn btn-outline" id="cancel_edit_btn" onclick="toggleMode('view')" style="display: none;">
                    <i class="fas fa-ban"></i> Cancel Edit
                </button>
                <button type="submit" form="trackerForm" class="btn btn-primary" id="save_changes_btn" style="display: none;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
    
    <!-- Image Viewing Modal -->
    <div id="imageModal" class="image-modal">
        <div class="image-modal-content">
            <div class="image-modal-header">
                <div class="image-modal-title" id="imageModalTitle">Receipt Image</div>
                <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
            </div>
            <img id="imageModalImg" src="/placeholder.svg" alt="Receipt Image">
        </div>
    </div>
    
    <script>
    // Global variables
    let currentLeadData = null; // Stores lead info (clientName, developer, etc.)
    let currentTrackerData = null; // Stores fetched tracker data
    let initialReservationDate = null; // Stores the reservation date when modal opens
    
    // Image viewing functions
    function openImageModal(imagePath, title) {
        document.getElementById('imageModalImg').src = imagePath;
        document.getElementById('imageModalTitle').textContent = title;
        document.getElementById('imageModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('imageModal');
        if (event.target == modal) {
            closeImageModal();
        }
    }
    
    // Load and display uploaded receipts for a lead
    function loadUploadedReceipts(leadId) {
        fetch(`get_receipts.php?lead_id=${leadId}`)
            .then(response => response.json())
            .then(receipts => {
                // Check if we're in edit mode (edit form is visible)
                const editForm = document.getElementById('trackerForm');
                const isEditMode = editForm && editForm.style.display !== 'none';
                displayReceipts(receipts, isEditMode);
            })
            .catch(error => {
                console.error('Error loading receipts:', error);
            });
    }
    
    function updateReceiptCounter() {
        const fileInput = document.getElementById('dp_receipt_single');
        const dpTermsField = document.getElementById('edit_dp_terms');
        const dpTerms = parseInt(dpTermsField.value) || 12;
        
        // Get current uploaded receipts count from the database/display
        const uploadedReceipts = document.querySelectorAll('#uploaded_receipts_display .receipt-item').length;
        
        // Add selected files count
        const selectedFiles = fileInput.files ? fileInput.files.length : 0;
        const totalReceipts = uploadedReceipts + selectedFiles;
        
        const currentStageField = document.getElementById('edit_current_dp_stage');
        if (currentStageField && !document.getElementById('edit_spot_dp').checked) {
            const currentStage = totalReceipts > 0 ? Math.max(1, Math.min(totalReceipts, dpTerms)) : 0;
            currentStageField.value = currentStage;
            currentStageField.readOnly = true; // Prevent manual editing
            currentStageField.style.backgroundColor = '#f3f4f6'; // Visual indication it's calculated
        }
        
        // Update counter display
        const counterText = document.getElementById('receipt_count_text');
        if (counterText) {
            counterText.textContent = `${totalReceipts} out of ${dpTerms} receipts uploaded`;
        }
        
        // Update progress indication
        const receiptCounter = document.getElementById('receipt_counter');
        if (receiptCounter) {
            const percentage = (totalReceipts / dpTerms) * 100;
            if (percentage >= 100) {
                receiptCounter.style.background = 'var(--success-light)';
                receiptCounter.style.color = '#065f46';
            } else if (percentage >= 50) {
                receiptCounter.style.background = 'var(--warning-light)';
                receiptCounter.style.color = '#92400e';
            } else {
                receiptCounter.style.background = 'var(--primary-light)';
                receiptCounter.style.color = 'var(--primary-dark)';
            }
        }
        
        updateMonthlyProgressDisplay();
        
        // Update the main table stage display if we're in edit mode for a specific lead
        if (typeof currentLeadData !== 'undefined' && currentLeadData && currentLeadData.leadId) {
            const stageDisplay = document.getElementById('stage_display_' + currentLeadData.leadId);
            console.log('Updating stage display for lead:', currentLeadData.leadId, 'Element found:', !!stageDisplay);
            if (stageDisplay) {
                const currentStage = totalReceipts > 0 ? Math.max(1, Math.min(totalReceipts, dpTerms)) : 0;
                console.log('Updating stage display:', { totalReceipts, dpTerms, currentStage });
                stageDisplay.innerHTML = `<strong>Month ${currentStage}</strong> of ${dpTerms}`;
            }
        }
    }
    
    // Function to update main dashboard stage display for any lead
    function updateMainDashboardStage(leadId, currentStage, totalStages) {
        const stageDisplay = document.getElementById('stage_display_' + leadId);
        if (stageDisplay) {
            console.log('Updating main dashboard stage for lead:', leadId, 'Stage:', currentStage, 'Total:', totalStages);
            stageDisplay.innerHTML = `<strong>Month ${currentStage}</strong> of ${totalStages}`;
        }
    }
    
    // Function to handle Manage DP button clicks directly
    function handleManageDpClick(button) {
        console.log('Direct Manage DP button click detected!');
        const leadId = button.getAttribute('data-lead-id');
        const clientName = button.getAttribute('data-client-name');
        const developer = button.getAttribute('data-developer');
        const projectModel = button.getAttribute('data-project-model');
        const price = button.getAttribute('data-price');
        const trackerData = button.getAttribute('data-tracker-data');
        const mode = button.getAttribute('data-mode');
        
        console.log('Manage DP button clicked with data:', {
            leadId, clientName, developer, projectModel, price, trackerData, mode
        });
        
        // Parse tracker data if it exists
        let parsedTrackerData = null;
        if (trackerData && trackerData !== '') {
            try {
                parsedTrackerData = JSON.parse(trackerData);
            } catch (e) {
                console.error('Error parsing tracker data:', e);
                parsedTrackerData = null;
            }
        }
        
        openDpDetailsModal(leadId, clientName, developer, projectModel, price, parsedTrackerData, mode);
    }
    
    // Function to refresh all stage displays on page load
    function refreshAllStageDisplays() {
        // Get all stage display elements
        const stageDisplays = document.querySelectorAll('[id^="stage_display_"]');
        stageDisplays.forEach(function(stageDisplay) {
            const leadId = stageDisplay.id.replace('stage_display_', '');
            // Fetch current tracker data for this lead
            fetch(`api/get-tracker.php?lead_id=${leadId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.tracker) {
                        const tracker = data.tracker;
                        if (tracker.spot_dp) {
                            stageDisplay.innerHTML = '<strong>Spot Downpayment</strong>';
                        } else {
                            stageDisplay.innerHTML = `<strong>Month ${tracker.current_dp_stage}</strong> of ${tracker.total_dp_stages}`;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing stage display for lead', leadId, ':', error);
                });
        });
    }

    // These event listeners are now handled in attachEditFormEventListeners()
    // document.getElementById('edit_dp_terms').addEventListener('input', function() {
    //     updateReceiptCounter();
    // });

    // document.getElementById('edit_spot_dp').addEventListener('change', function() {
    //     const currentStageField = document.getElementById('edit_current_dp_stage');
    //     if (this.checked) {
    //         currentStageField.value = 1;
    //         currentStageField.readOnly = true;
    //     } else {
    //         updateReceiptCounter();
    //     }
    // });
    
    function updateMonthlyProgressDisplay() {
        const monthlyProgressGrid = document.getElementById('monthly_progress_grid');
        if (!monthlyProgressGrid) return;
        
        const dpTerms = parseInt(document.getElementById('edit_dp_terms').value) || 12;
        const uploadedReceipts = document.querySelectorAll('#uploaded_receipts_display .receipt-item').length;
        const selectedFiles = document.getElementById('dp_receipt_single').files ? document.getElementById('dp_receipt_single').files.length : 0;
        const totalReceipts = uploadedReceipts + selectedFiles;
        const currentStage = totalReceipts > 0 ? Math.min(totalReceipts, dpTerms) : 0;
        
        // Clear and rebuild monthly progress
        monthlyProgressGrid.innerHTML = '';
        
        for (let i = 1; i <= dpTerms; i++) {
            const monthItem = document.createElement('div');
            monthItem.classList.add('monthly-progress-item');
            
            let monthStatus = 'pending';
            if (i <= currentStage) {
                monthStatus = 'completed';
                monthItem.classList.add('completed');
            } else if (i === currentStage + 1 && currentStage < dpTerms) {
                monthStatus = 'current';
                monthItem.classList.add('current');
            } else {
                monthItem.classList.add('pending');
            }
            
            monthItem.innerHTML = `
                <div class="month-number">${i}</div>
                <div class="month-status">${monthStatus.toUpperCase()}</div>
            `;
            
            monthlyProgressGrid.appendChild(monthItem);
        }
        
        const currentStageDisplay = document.querySelector('.current-stage-display');
        if (currentStageDisplay) {
            currentStageDisplay.textContent = `Month ${currentStage} of ${dpTerms}`;
        }
    }

    function generateDPReceiptFields(dpTerms) {
        // Update the receipt counter when terms change
        updateReceiptCounter();
    }
    
    function displayReceipts(receipts, showDeleteButtons = false) {
        const editContainer = document.getElementById('uploaded_receipts_display');
        const viewContainer = document.getElementById('view_uploaded_receipts');
        
        let html = '';
        
        if (receipts.length === 0) {
            html = '<p style="color: var(--gray-500); font-style: italic; text-align: center; padding: 2rem;">No receipts uploaded yet</p>';
        } else {
            html += '<div class="receipt-grid">';
            
            receipts.forEach((receipt, index) => {
                const monthNumber = index + 1; // Each receipt represents a month
                const deleteButton = `
                    <button class="receipt-delete-btn" onclick="event.stopPropagation(); deleteReceipt(${receipt.id})" title="Delete Receipt">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                html += `
                    <div class="receipt-item" data-receipt-id="${receipt.id}">
                        <div class="receipt-image-container" onclick="openImageModal('${receipt.file_path}', '${receipt.original_name}')">
                            <img src="${receipt.file_path}" alt="${receipt.original_name}" class="receipt-thumbnail" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0zNSA0MEg2NVY2MEgzNVY0MFoiIGZpbGw9IiM5Q0EzQUYiLz4KPHN2Zz4K'">
                        </div>
                        <div class="receipt-info">
                            <div class="receipt-stage">Month ${monthNumber}</div>
                            <div style="font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">${receipt.original_name}</div>
                            <div style="font-size: 0.6875rem; color: var(--gray-400);">${new Date(receipt.uploaded_at).toLocaleDateString()}</div>
                        </div>
                        ${deleteButton}
                    </div>
                `;
            });
            
            html += '</div>';
        }
        
        // Update both containers with appropriate delete button visibility
        if (editContainer) editContainer.innerHTML = html;
        if (viewContainer) {
            // Show delete buttons in view mode as well for easy cleanup
            viewContainer.innerHTML = html;
        }
        
        updateReceiptCounter();
    }
    
    function updateDpStages(termsSelectId, currentStageSelectId) {
        // Update receipt counter when terms change
        updateReceiptCounter();
    }
    
    // Function to delete a receipt
    function deleteReceipt(receiptId) {
        if (!confirm('Are you sure you want to delete this receipt? This action cannot be undone.')) {
            return;
        }
        
        // Show loading state
        const deleteBtn = document.querySelector(`[data-receipt-id="${receiptId}"] .receipt-delete-btn`);
        if (deleteBtn) {
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            deleteBtn.disabled = true;
        }
        
        // Send delete request
        fetch('delete_receipt.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `receipt_id=${receiptId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the receipt item from the display
                const receiptItem = document.querySelector(`[data-receipt-id="${receiptId}"]`);
                if (receiptItem) {
                    receiptItem.remove();
                }
                
                // Reload receipts to update the display and counters
                if (currentLeadData && currentLeadData.leadId) {
                    loadUploadedReceipts(currentLeadData.leadId);
                }
                
                // Show success message
                showNotification('Receipt deleted successfully', 'success');
            } else {
                throw new Error(data.error || 'Failed to delete receipt');
            }
        })
        .catch(error => {
            console.error('Error deleting receipt:', error);
            showNotification('Failed to delete receipt: ' + error.message, 'error');
            
            // Reset button state
            if (deleteBtn) {
                deleteBtn.innerHTML = '<i class="fas fa-times"></i>';
                deleteBtn.disabled = false;
            }
        });
    }
    
    // Simple notification function
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 600;
            z-index: 10000;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        
        // Set background color based on type
        switch (type) {
            case 'success':
                notification.style.backgroundColor = '#10b981';
                break;
            case 'error':
                notification.style.backgroundColor = '#ef4444';
                break;
            default:
                notification.style.backgroundColor = '#3b82f6';
        }
        
        notification.textContent = message;
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // Event listeners for edit form
    document.getElementById('edit_spot_dp').addEventListener('change', function() {
        toggleTermsSection('edit_terms_section', 'edit_spot_dp', 'edit_dp_terms', 'edit_current_dp_stage');
    });

    document.getElementById('edit_dp_terms').addEventListener('change', function() {
        updateReceiptCounter(); // Update counter when terms change
    });


    // Function to open the unified DP Details modal
    function openDpDetailsModal(leadId, clientName, developer, projectModel, price, trackerData, mode = 'view') {
        console.log('openDpDetailsModal called with:', { leadId, clientName, developer, projectModel, price, trackerData, mode });
        
        // Store basic lead info
        currentLeadData = { leadId, clientName, developer, projectModel, price };
        
        // Debug: Log the raw tracker data
        console.log('Raw tracker data received:', trackerData);
        console.log('Type of tracker data:', typeof trackerData);
        
        // Parse tracker data if it's a string (JSON)
        if (typeof trackerData === 'string' && trackerData !== 'null') {
            try {
                currentTrackerData = JSON.parse(trackerData);
                console.log('Parsed tracker data:', currentTrackerData);
            } catch (e) {
                console.error('Error parsing tracker data:', e);
                currentTrackerData = null;
            }
        } else if (trackerData && trackerData !== 'null') {
            currentTrackerData = trackerData;
            console.log('Using tracker data as object:', currentTrackerData);
        } else {
            currentTrackerData = null;
            console.log('No tracker data available');
        }
        
        initialReservationDate = currentTrackerData ? currentTrackerData.reservation_date : null; // Store initial date

        // Set basic info for both view and edit sections
        document.getElementById('view_client_name').textContent = clientName;
        document.getElementById('view_developer').textContent = developer;
        document.getElementById('view_project_model').textContent = projectModel;
        document.getElementById('edit_client_name').value = clientName;
        document.getElementById('edit_project_details').value = developer + ' - ' + projectModel;

        if (price && price > 0) {
            const formattedPrice = '₱' + parseFloat(price).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('view_price').textContent = formattedPrice;
        } else {
            document.getElementById('view_price').textContent = 'Price not set';
        }

        // Load uploaded receipts for this lead
        loadUploadedReceipts(leadId);
        
        // Attach event listeners for edit form elements (they now exist)
        attachEditFormEventListeners();
        
        // Display in requested mode
        toggleMode(mode);
        
        // Show the modal
        const modal = document.getElementById('dpDetailsModal');
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } else {
            console.error('Modal element not found!');
        }
    }

    // Function to close the unified DP Details modal
    function closeDpDetailsModal() {
        document.getElementById('dpDetailsModal').style.display = 'none';
        document.body.style.overflow = '';
        currentLeadData = null;
        currentTrackerData = null;
        initialReservationDate = null; // Reset initial reservation date
        document.getElementById('reservation_date_error').style.display = 'none'; // Hide any error messages
    }

    // Function to switch between view and edit modes
    function toggleMode(mode) {
        const viewContent = document.getElementById('view_mode_content');
        const editForm = document.getElementById('trackerForm');
        const editBtn = document.getElementById('edit_mode_btn');
        const saveBtn = document.getElementById('save_changes_btn');
        const cancelEditBtn = document.getElementById('cancel_edit_btn');
        const modalTitle = document.getElementById('modal_title');

        if (mode === 'edit') {
            viewContent.style.display = 'none';
            editForm.style.display = 'block';
            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-flex';
            cancelEditBtn.style.display = 'inline-flex';
            modalTitle.innerHTML = '<i class="fas fa-edit"></i> Update Downpayment Tracker';
            // Small delay to ensure form elements are ready, then populate
            setTimeout(() => {
                populateEditForm(currentTrackerData);
            }, 100);
        } else { // mode === 'view'
            viewContent.style.display = 'block';
            editForm.style.display = 'none';
            editBtn.style.display = 'inline-flex';
            saveBtn.style.display = 'none';
            cancelEditBtn.style.display = 'none';
            modalTitle.innerHTML = '<i class="fas fa-chart-pie"></i> Downpayment Progress Overview';
            displayViewModeContent(currentTrackerData); // Re-display view data when switching back
        }
    }

    // Display functions for view mode content
    function displayViewModeContent(tracker) {
        // Display DP Terms
        var termsCard = document.getElementById('dp_terms_card');
        var reservationCard = document.getElementById('reservation_card');
        var monthlyProgressSection = document.getElementById('monthly_progress_section');
        
        if (tracker && tracker.spot_dp == 1) {
            termsCard.className = 'terms-card spot-dp';
            termsCard.innerHTML = `
                <div class="terms-header">
                    <div class="terms-icon">
                        <i class="fas fa-lightning-bolt"></i>
                    </div>
                    <div class="terms-title">Spot Downpayment</div>
                </div>
                <div class="terms-details">
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Payment Type:</span>
                        <span class="terms-detail-value" style="color: #065f46;">Full Payment</span>
                    </div>
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Status:</span>
                        <span class="terms-detail-value" style="color: #065f46;">Completed</span>
                    </div>
                </div>
            `;
            monthlyProgressSection.style.display = 'none';
        } else if (tracker) {
            termsCard.className = 'terms-card installment';
            var progressPercentage = Math.round((tracker.current_dp_stage / tracker.total_dp_stages) * 100);
            termsCard.innerHTML = `
                <div class="terms-header">
                    <div class="terms-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="terms-title">Installment Plan</div>
                </div>
                <div class="terms-details">
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Payment Terms:</span>
                        <span class="terms-detail-value" style="color: var(--primary);">${tracker.dp_terms} months</span>
                    </div>
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Current Stage:</span>
                        <span class="terms-detail-value" style="color: var(--primary);">Month ${tracker.current_dp_stage} of ${tracker.total_dp_stages}</span>
                    </div>
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Progress:</span>
                        <span class="terms-detail-value" style="color: ${tracker.current_dp_stage === tracker.total_dp_stages ? 'var(--success)' : 'var(--warning)'};">${progressPercentage}%</span>
                    </div>
                </div>
            `;
            
            // Display monthly progress
            displayMonthlyProgress(tracker);
            monthlyProgressSection.style.display = 'block';
        } else {
            displayEmptyViewModeContent();
        }
        
        // Display reservation info
        if (tracker && tracker.reservation_date) {
            var reservationDate = new Date(tracker.reservation_date);
            reservationCard.innerHTML = `
                <div class="reservation-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="reservation-title">Reserved</div>
                <div class="reservation-date">
                    ${reservationDate.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })}
                </div>
            `;
        } else {
            reservationCard.innerHTML = `
                <div class="reservation-icon" style="background: var(--gray-300); color: var(--gray-600);">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="reservation-title" style="color: var(--gray-600);">No Reservation</div>
                <div class="reservation-date" style="color: var(--gray-500);">Date not set</div>
            `;
        }
        
        // Display overall progress
        var progress = (tracker && parseFloat(tracker.progress_rate)) || 0;
        var progressCircle = document.getElementById('progress_circle');
        var progressText = document.getElementById('view_progress_percentage');
        
        var progressAngle = (progress / 100) * 360;
        var progressColor = 'var(--danger)';
        if (progress >= 75) progressColor = 'var(--success)';
        else if (progress >= 50) progressColor = 'var(--warning)';
        else if (progress >= 25) progressColor = 'var(--primary)';
        
        progressCircle.style.setProperty('--progress-angle', progressAngle + 'deg');
        progressCircle.style.background = progressColor;
        progressText.textContent = Math.round(progress) + '%';
        
        // Display milestones
        updateViewMilestoneStatus('milestone_requirements', tracker && tracker.requirements_complete == 1);
        
        // DP Stage milestone
        var dpStageCompleted = tracker && (tracker.spot_dp == 1 || tracker.current_dp_stage == tracker.total_dp_stages);
        updateViewMilestoneStatus('milestone_dp_stage', dpStageCompleted);
        
        var dpStageDesc = document.getElementById('dp_stage_description');
        if (tracker && tracker.spot_dp == 1) {
            dpStageDesc.textContent = 'Spot downpayment completed successfully';
        } else if (tracker) {
            dpStageDesc.textContent = `Monthly payment progress: ${tracker.current_dp_stage} of ${tracker.total_dp_stages} months completed`;
        } else {
            dpStageDesc.textContent = 'Monthly payment progress tracking';
        }
        
        updateViewMilestoneStatus('milestone_approval', tracker && tracker.pagibig_bank_approval == 1);
        updateViewMilestoneStatus('milestone_takeout', tracker && tracker.loan_takeout == 1);
        updateViewMilestoneStatus('milestone_turnover', tracker && tracker.turnover == 1);

        // Disable edit button if lead is fully complete
        const editDetailsBtn = document.getElementById('edit_mode_btn');
        if (tracker && tracker.progress_details && tracker.progress_details.is_fully_complete) {
            editDetailsBtn.disabled = true;
            editDetailsBtn.title = 'Cannot edit completed leads.';
        } else {
            editDetailsBtn.disabled = false;
            editDetailsBtn.title = '';
        }
    }

    // Function to display empty tracker data for view mode
    function displayEmptyViewModeContent() {
        var termsCard = document.getElementById('dp_terms_card');
        termsCard.className = 'terms-card';
        termsCard.innerHTML = `
            <div style="text-align: center; color: var(--gray-500); padding: 2rem;">
                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                <div style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">No Terms Set</div>
                <div style="font-size: 0.875rem;">Downpayment terms have not been configured yet</div>
            </div>
        `;
        
        var reservationCard = document.getElementById('reservation_card');
        reservationCard.innerHTML = `
            <div class="reservation-icon" style="background: var(--gray-300); color: var(--gray-600);">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="reservation-title" style="color: var(--gray-600);">No Reservation</div>
            <div class="reservation-date" style="color: var(--gray-500);">Date not set</div>
        `;
        
        // Hide monthly progress section
        document.getElementById('monthly_progress_section').style.display = 'none';
        
        // Reset progress
        var progressCircle = document.getElementById('progress_circle');
        var progressText = document.getElementById('view_progress_percentage');
        progressCircle.style.background = 'var(--gray-200)';
        progressText.textContent = '0%';
        
        // Reset all milestones to pending
        updateViewMilestoneStatus('milestone_requirements', false);
        updateViewMilestoneStatus('milestone_dp_stage', false);
        updateViewMilestoneStatus('milestone_approval', false);
        updateViewMilestoneStatus('milestone_takeout', false);
        updateViewMilestoneStatus('milestone_turnover', false);
        
        document.getElementById('dp_stage_description').textContent = 'Monthly payment progress tracking';

        // Enable edit button if no tracker data
        const editDetailsBtn = document.getElementById('edit_mode_btn');
        editDetailsBtn.disabled = false;
        editDetailsBtn.title = '';
    }

    // Function to update milestone status in view mode
    function updateViewMilestoneStatus(milestoneId, isCompleted) {
        var milestone = document.getElementById(milestoneId);
        var statusIndicator = milestone.querySelector('.milestone-status-indicator i');
        
        if (isCompleted) {
            milestone.className = 'milestone-card completed';
            statusIndicator.className = 'fas fa-check';
        } else {
            milestone.className = 'milestone-card pending';
            statusIndicator.className = 'fas fa-clock';
        }
    }

    // Function to populate the edit form fields
    function populateEditForm(tracker) {
        console.log('Populating edit form with tracker data:', tracker);
        document.getElementById('edit_lead_id').value = currentLeadData.leadId;
        
        // Set default values first
        document.getElementById('edit_reservation_date').value = '';
        document.getElementById('edit_requirements_complete').checked = false;
        document.getElementById('edit_spot_dp').checked = false;
        document.getElementById('edit_dp_terms').value = '12'; // Default value
        document.getElementById('edit_pagibig_bank_approval').checked = false;
        document.getElementById('edit_loan_takeout').checked = false;
        document.getElementById('edit_turnover').checked = false;
        
        // Populate form with existing tracker data if available
        if (tracker) {
            console.log('Loading existing tracker data into form');
            
            // Set reservation date
            if (tracker.reservation_date && tracker.reservation_date !== '0000-00-00' && tracker.reservation_date !== null) {
                // Convert MySQL date format to HTML date input format (YYYY-MM-DD)
                var dateValue = tracker.reservation_date;
                if (dateValue.includes(' ')) {
                    dateValue = dateValue.split(' ')[0]; // Remove time part if exists
                }
                document.getElementById('edit_reservation_date').value = dateValue;
                console.log('Set reservation date to:', dateValue);
            }
            
            // Set milestone checkboxes
            document.getElementById('edit_requirements_complete').checked = (tracker.requirements_complete == 1 || tracker.requirements_complete === true);
            document.getElementById('edit_spot_dp').checked = (tracker.spot_dp == 1 || tracker.spot_dp === true);
            document.getElementById('edit_pagibig_bank_approval').checked = (tracker.pagibig_bank_approval == 1 || tracker.pagibig_bank_approval === true);
            document.getElementById('edit_loan_takeout').checked = (tracker.loan_takeout == 1 || tracker.loan_takeout === true);
            document.getElementById('edit_turnover').checked = (tracker.turnover == 1 || tracker.turnover === true);
            
            console.log('Set milestones:', {
                requirements_complete: tracker.requirements_complete,
                spot_dp: tracker.spot_dp,
                pagibig_bank_approval: tracker.pagibig_bank_approval,
                loan_takeout: tracker.loan_takeout,
                turnover: tracker.turnover
            });
            
            // Set DP terms and stages
            if (tracker.dp_terms) {
                document.getElementById('edit_dp_terms').value = tracker.dp_terms;
                console.log('Set DP terms to:', tracker.dp_terms);
            }
        }
        
        // Update DP stages dropdown and generate receipt fields after setting all values
        updateDpStages('edit_dp_terms', 'edit_current_dp_stage');
        
        // Set current stage if tracker data exists
        if (tracker && tracker.current_dp_stage) {
            document.getElementById('edit_current_dp_stage').value = tracker.current_dp_stage;
            console.log('Set current DP stage to:', tracker.current_dp_stage);
        }
        
        // Generate receipt upload fields
        generateDPReceiptFields(document.getElementById('edit_dp_terms').value);
        
        // Update receipt counter after loading existing data
        updateReceiptCounter();
    }
    
    // Function to update DP stages dropdown based on selected terms
    function updateDpStages(termsSelectId, currentStageSelectId) {
        var termsSelect = document.getElementById(termsSelectId);
        var currentStage = document.getElementById(currentStageSelectId);
        
        // Check if elements exist before proceeding
        if (!termsSelect || !currentStage) {
            console.log('updateDpStages: Elements not found', { termsSelectId, currentStageSelectId });
            return;
        }
        
        var terms = parseInt(termsSelect.value);
        var selectedValue = currentStage.value;
        
        // Clear current options
        currentStage.innerHTML = '';
        
        // Add options based on terms
        for (var i = 1; i <= terms; i++) {
            var option = document.createElement('option');
            option.value = i;
            option.text = 'Month ' + i + ' of ' + terms;
            currentStage.appendChild(option);
        }
        
        // Restore selection if it's still valid
        if (selectedValue && selectedValue <= terms) {
            currentStage.value = selectedValue;
        } else {
            currentStage.value = 1;
        }
    }
    
    // Function to toggle terms section visibility
    function toggleTermsSection(termsSectionId, spotDpCheckboxId, dpTermsSelectId, currentDpStageSelectId) {
        var termsSection = document.getElementById(termsSectionId);
        var spotDpCheckbox = document.getElementById(spotDpCheckboxId);
        var dpTermsSelect = document.getElementById(dpTermsSelectId);
        var currentDpStageSelect = document.getElementById(currentDpStageSelectId);
        
        if (spotDpCheckbox.checked) {
            termsSection.style.opacity = '0.5';
            termsSection.style.pointerEvents = 'none';
            dpTermsSelect.disabled = true;
            currentDpStageSelect.disabled = true;
        } else {
            termsSection.style.opacity = '1';
            termsSection.style.pointerEvents = 'auto';
            dpTermsSelect.disabled = false;
            currentDpStageSelect.disabled = false;
        }
    }

    // Event listeners for edit form - only attach if elements exist
    function attachEditFormEventListeners() {
        const editSpotDp = document.getElementById('edit_spot_dp');
        const editDpTerms = document.getElementById('edit_dp_terms');
        
        if (editSpotDp) {
            editSpotDp.addEventListener('change', function() {
                toggleTermsSection('edit_terms_section', 'edit_spot_dp', 'edit_dp_terms', 'edit_current_dp_stage');
                if (this.checked) {
                    document.getElementById('edit_dp_terms').value = '6'; // Use valid ENUM value
                    updateDpStages('edit_dp_terms', 'edit_current_dp_stage');
                    document.getElementById('edit_current_dp_stage').value = '1';
                    // Generate receipt field for spot DP (single payment)
                    generateDPReceiptFields('6');
                } else {
                    document.getElementById('edit_dp_terms').value = '12'; // Default back to 12 months
                    updateDpStages('edit_dp_terms', 'edit_current_dp_stage');
                    // Generate receipt fields for installment plan
                    generateDPReceiptFields('12');
                }
            });
        }
        
        if (editDpTerms) {
            editDpTerms.addEventListener('change', function() {
                updateDpStages('edit_dp_terms', 'edit_current_dp_stage');
                // Regenerate receipt upload fields based on new DP terms
                generateDPReceiptFields(this.value);
            });
            
            editDpTerms.addEventListener('input', function() {
                updateReceiptCounter();
            });
        }
        
        if (editSpotDp) {
            editSpotDp.addEventListener('change', function() {
                const currentStageField = document.getElementById('edit_current_dp_stage');
                if (this.checked) {
                    if (currentStageField) {
                        currentStageField.value = 1;
                        currentStageField.readOnly = true;
                    }
                } else {
                    updateReceiptCounter();
                }
            });
        }
    }
    
    // Call the function to attach event listeners
    attachEditFormEventListeners();
    
    // Add event listener for Manage DP buttons using data attributes
    document.addEventListener('click', function(event) {
        console.log('Click event detected on:', event.target);
        console.log('Event target class:', event.target.className);
        console.log('Event target closest manage-dp-btn:', event.target.closest('.manage-dp-btn'));
        
        if (event.target.closest('.manage-dp-btn')) {
            console.log('Manage DP button click detected!');
            event.preventDefault();
            event.stopPropagation();
            const button = event.target.closest('.manage-dp-btn');
            const leadId = button.getAttribute('data-lead-id');
            const clientName = button.getAttribute('data-client-name');
            const developer = button.getAttribute('data-developer');
            const projectModel = button.getAttribute('data-project-model');
            const price = button.getAttribute('data-price');
            const trackerData = button.getAttribute('data-tracker-data');
            const mode = button.getAttribute('data-mode');
            
            console.log('Manage DP button clicked with data:', {
                leadId, clientName, developer, projectModel, price, trackerData, mode
            });
            
            // Parse tracker data if it exists
            let parsedTrackerData = null;
            if (trackerData && trackerData !== '') {
                try {
                    parsedTrackerData = JSON.parse(trackerData);
                } catch (e) {
                    console.error('Error parsing tracker data:', e);
                    parsedTrackerData = null;
                }
            }
            
            openDpDetailsModal(leadId, clientName, developer, projectModel, price, parsedTrackerData, mode);
        }
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        var dpDetailsModal = document.getElementById('dpDetailsModal');
        
        if (event.target == dpDetailsModal) {
            closeDpDetailsModal();
        }
    });

    // Add validation for reservation_date on form submission
    document.getElementById('trackerForm').addEventListener('submit', function(event) {
        const reservationDateInput = document.getElementById('edit_reservation_date');
        const reservationDateError = document.getElementById('reservation_date_error');
        reservationDateError.style.display = 'none'; // Clear previous errors

        const newReservationDate = reservationDateInput.value;

        // 1. Validate if reservation date is empty
        if (!newReservationDate) {
            if (initialReservationDate) {
                // If it had a value previously, it cannot be cleared
                reservationDateError.textContent = 'Reservation Date cannot be cleared.';
                reservationDateError.style.display = 'block';
                event.preventDefault();
                reservationDateInput.focus();
                return;
            } else {
                // If it was initially empty, it must be filled
                reservationDateError.textContent = 'Reservation Date is required.';
                reservationDateError.style.display = 'block';
                event.preventDefault();
                reservationDateInput.focus();
                return;
            }
        }

        // 2. Validate if reservation date is in the future
        const reservationDate = new Date(newReservationDate);
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Normalize today's date to compare only date part

        if (reservationDate > today) {
            reservationDateError.textContent = 'Reservation Date cannot be in the future.';
            reservationDateError.style.display = 'block';
            event.preventDefault(); // Prevent form submission
            reservationDateInput.focus();
            return;
        }
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Highlight active filters
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('search') && urlParams.get('search') !== '') {
            document.getElementById('search').classList.add('filter-active');
        }
        
        if (urlParams.has('agent') && urlParams.get('agent') !== '') {
            document.getElementById('agent').classList.add('filter-active');
        }
        
        if (urlParams.has('developer') && urlParams.get('developer') !== '') {
            document.getElementById('developer').classList.add('filter-active');
        }   
        
        if (urlParams.has('progress') && urlParams.get('progress') !== '') {
            document.getElementById('progress').classList.add('filter-active');
        }
        
        // Initialize DP stages dropdown for the edit form - removed as elements don't exist on page load
        // updateDpStages('edit_dp_terms', 'edit_current_dp_stage');
    });

    // Function to display monthly progress
    function displayMonthlyProgress(tracker) {
        const monthlyProgressGrid = document.getElementById('monthly_progress_grid');
        monthlyProgressGrid.innerHTML = ''; // Clear existing content

        const totalMonths = parseInt(tracker.dp_terms);
        const currentMonth = parseInt(tracker.current_dp_stage);

        const currentStageDisplay = document.querySelector('.current-stage-display');
        if (currentStageDisplay) {
            currentStageDisplay.textContent = `Month ${currentMonth} of ${totalMonths}`;
        }

        // Determine if the entire DP term is completed
        const isDpTermFullyCompleted = (currentMonth === totalMonths);

        for (let i = 1; i <= totalMonths; i++) {
            const monthItem = document.createElement('div');
            monthItem.classList.add('monthly-progress-item');

            let monthStatus = 'pending';
            if (isDpTermFullyCompleted) {
                // If the entire term is completed, all months are 'completed'
                monthStatus = 'completed';
                monthItem.classList.add('completed');
            } else if (i <= currentMonth) {
                monthStatus = 'completed';
                monthItem.classList.add('completed');
            } else if (i === currentMonth + 1 && currentMonth < totalMonths) {
                monthStatus = 'current';
                monthItem.classList.add('current');
            } else {
                monthStatus = 'pending';
            }

            monthItem.innerHTML = `
                <div class="month-number">${i}</div>
                <div class="month-status">${monthStatus.toUpperCase()}</div>
            `;

            monthlyProgressGrid.appendChild(monthItem);
        }
    }
    </script>   
</body>
</html>
