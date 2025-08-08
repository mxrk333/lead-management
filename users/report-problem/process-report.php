<?php
// =========================================================================
// START: LOGIC TO HANDLE NEW PROBLEM REPORT SUBMISSION (AJAX)
// This block runs BEFORE any session checks or HTML output.
// It checks if the form was submitted by looking for the required 'username' and 'description' fields.
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['description']) && !isset($_POST['update_report']) && !isset($_POST['get_report_details'])) {
    
    // Set the header to indicate a JSON response is being sent.
    header('Content-Type: application/json');
    
    // Adjust the path to go up two directories to find the config and includes folders.
    $base_path = dirname(dirname(__DIR__)); 
    require_once $base_path . '/config/database.php';
    // functions.php is not strictly needed here but included for consistency.
    require_once $base_path . '/includes/functions.php';

    $conn = null;
    $stmt = null;

    try {
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Failed to get a database connection.");
        }

        // Basic validation for required fields from the form.
        $required_fields = ['username', 'phone', 'issue_type', 'priority', 'description'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("The field '{$field}' cannot be empty. Please fill out all required fields.");
            }
        }
        
        // Prepare the SQL INSERT statement to add the new report to the database.
        // The `status` is set to 'open' by default for new reports.
        $query = "INSERT INTO problem_reports (username, phone, email, issue_type, priority, description, browser_info, status, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database prepare statement failed: " . $conn->error);
        }

        // Bind the form data to the prepared statement to prevent SQL injection.
        $stmt->bind_param(
            "sssssss",
            $_POST['username'],
            $_POST['phone'],
            $_POST['email'], // Optional field
            $_POST['issue_type'],
            $_POST['priority'],
            $_POST['description'],
            $_POST['browser_info'] // Optional field
        );

        // Execute the statement and check for success.
        if ($stmt->execute()) {
            // Send a success message back to the browser.
            echo json_encode(['success' => true, 'message' => 'Thank you! Your problem report has been submitted successfully.']);
        } else {
            // The report failed to save.
            throw new Exception("Database execute statement failed: " . $stmt->error);
        }

    } catch (Exception $e) {
        // Log the detailed error for debugging purposes.
        error_log("Error creating problem report: " . $e->getMessage());
        
        // Send a generic, user-friendly error message back to the browser.
        http_response_code(500); // Set HTTP status to indicate a server error.
        echo json_encode(['success' => false, 'message' => 'An error occurred while submitting your report. Please try again.']);
        
    } finally {
        // Always close the statement and connection to free up resources.
        if ($stmt) $stmt->close();
        if ($conn) $conn->close();
    }
    
    // Crucially, stop the script here so it doesn't try to render the dashboard page.
    exit();
}

// =========================================================================
// START: AJAX ENDPOINT FOR GETTING REPORT DETAILS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_report_details'])) {
    header('Content-Type: application/json');
    
    $base_path = dirname(dirname(__DIR__));
    require_once $base_path . '/config/database.php';
    require_once $base_path . '/includes/functions.php';
    
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $report_id = (int)$_POST['report_id'];
    
    try {
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Failed to get database connection.");
        }
        
        // Start transaction for auto-assignment
        $conn->begin_transaction();
        
        // Get report details with assigned user info
        $query = "SELECT pr.*, u.username as assigned_username, u.name as assigned_name 
                  FROM problem_reports pr 
                  LEFT JOIN users u ON pr.assigned_to = u.id 
                  WHERE pr.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();
        
        if (!$report) {
            throw new Exception("Report not found.");
        }
        
        // Auto-assign if not already assigned
        if (empty($report['assigned_to'])) {
            $update_query = "UPDATE problem_reports SET assigned_to = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $user_id, $report_id);
            $update_stmt->execute();
            
            // Get current user info for display
            $user_query = "SELECT username, name FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($user_query);
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $current_user = $user_result->fetch_assoc();
            
            $report['assigned_to'] = $user_id;
            $report['assigned_username'] = $current_user['username'];
            $report['assigned_name'] = $current_user['name'];
        }
        
        $conn->commit();
        
        // Generate HTML for modal content
        $html = generateReportDetailsHTML($report);
        
        echo json_encode(['success' => true, 'html' => $html]);
        
    } catch (Exception $e) {
        if ($conn && $conn->inTransaction()) {
            $conn->rollback();
        }
        error_log("Error getting report details: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error loading report details: ' . $e->getMessage()]);
    } finally {
        if ($conn) $conn->close();
    }
    
    exit();
}

// Function to generate report details HTML
function generateReportDetailsHTML($report) {
    $html = '<div class="report-details-content">';
    $html .= '<div class="detail-item">';
    $html .= '<strong>Report ID:</strong> #' . htmlspecialchars($report['id']);
    $html .= '</div>';
    
    $html .= '<div class="detail-item">';
    $html .= '<strong>Username:</strong> ' . htmlspecialchars($report['username']);
    $html .= '</div>';
    
    $html .= '<div class="detail-item">';
    $html .= '<strong>Phone:</strong> ' . htmlspecialchars($report['phone']);
    $html .= '</div>';
    
    $html .= '<div class="detail-item">';
    $html .= '<strong>Email:</strong> ' . htmlspecialchars($report['email'] ?: 'Not provided');
    $html .= '</div>';
    
    $html .= '<div class="detail-item">';
    $html .= '<strong>Issue Type:</strong> ' . htmlspecialchars(ucfirst(str_replace('-', ' ', $report['issue_type'])));
    $html .= '</div>';
    
    $html .= '<div class="detail-item">';
    $html .= '<strong>Priority:</strong> <span class="priority-badge priority-' . $report['priority'] . '">' . htmlspecialchars(ucfirst($report['priority'])) . '</span>';
    $html .= '</div>';
    
    $html .= '<div class="detail-item">';
    $html .= '<strong>Current Status:</strong> <span class="status-badge status-' . $report['status'] . '">' . htmlspecialchars(ucfirst(str_replace('-', ' ', $report['status']))) . '</span>';
    $html .= '</div>';
    
    $html .= '<div class="detail-item">';
    $html .= '<strong>Assigned To:</strong> ' . htmlspecialchars($report['assigned_username'] ?: 'Unassigned');
    $html .= '</div>';
    
    $html .= '<div class="detail-item">';
    $html .= '<strong>Created:</strong> ' . date('M j, Y \a\t H:i', strtotime($report['created_at']));
    $html .= '</div>';
    
    $html .= '<div class="detail-item">';
    $html .= '<strong>Last Updated:</strong> ' . date('M j, Y \a\t H:i', strtotime($report['updated_at']));
    $html .= '</div>';
    
    if ($report['resolved_at']) {
        $html .= '<div class="detail-item">';
        $html .= '<strong>Resolved:</strong> ' . date('M j, Y \a\t H:i', strtotime($report['resolved_at']));
        $html .= '</div>';
    }
    
    $html .= '<div class="detail-item full-width">';
    $html .= '<strong>Description:</strong>';
    $html .= '<p>' . nl2br(htmlspecialchars($report['description'])) . '</p>';
    $html .= '</div>';
    
    if ($report['browser_info']) {
        $html .= '<div class="detail-item full-width">';
        $html .= '<strong>Browser Info:</strong>';
        $html .= '<p>' . htmlspecialchars($report['browser_info']) . '</p>';
        $html .= '</div>';
    }
    
    if ($report['resolution_notes']) {
        $html .= '<div class="detail-item full-width">';
        $html .= '<strong>Resolution Notes:</strong>';
        $html .= '<p>' . nl2br(htmlspecialchars($report['resolution_notes'])) . '</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    // Add status update form
    $html .= '<hr class="modal-separator">';
    $html .= '<form id="statusForm" onsubmit="updateReport(' . $report['id'] . '); return false;">';
    $html .= '<div class="form-group">';
    $html .= '<label for="status">Update Status:</label>';
    $html .= '<select id="status" name="status" required>';
    $html .= '<option value="open"' . ($report['status'] === 'open' ? ' selected' : '') . '>Open</option>';
    $html .= '<option value="in-progress"' . ($report['status'] === 'in-progress' ? ' selected' : '') . '>In Progress</option>';
    $html .= '<option value="resolved"' . ($report['status'] === 'resolved' ? ' selected' : '') . '>Resolved</option>';
    $html .= '<option value="closed"' . ($report['status'] === 'closed' ? ' selected' : '') . '>Closed</option>';
    $html .= '</select>';
    $html .= '</div>';
    
    $html .= '<div class="form-group">';
    $html .= '<label for="resolution_notes">Resolution Notes:</label>';
    $html .= '<textarea id="resolution_notes" name="resolution_notes" placeholder="Add notes about the resolution or progress...">' . htmlspecialchars($report['resolution_notes'] ?: '') . '</textarea>';
    $html .= '</div>';
    
    $html .= '<button type="submit" class="btn-primary">';
    $html .= '<i class="fas fa-save"></i> Update Report';
    $html .= '</button>';
    $html .= '</form>';
    
    return $html;
}

// =========================================================================
// END: NEW REPORT SUBMISSION LOGIC
// --- Original content of process-report.php continues below ---
// =========================================================================

session_start();

// Fix the path resolution - go up two directories to reach the root
// Assuming process-report.php is in a subdirectory like /admin/problem-reports/
$base_path = dirname(dirname(__DIR__)); // Go up two levels from current file to reach project root
require_once $base_path . '/config/database.php';
require_once $base_path . '/includes/functions.php';

// Set timezone to ensure accurate time display
date_default_timezone_set('Asia/Manila');

// Enable error reporting for development (disable in production)
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $base_path . "/login.php"); // Adjust path
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
try {
    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception("Failed to get database connection.");
    }
    $user_query = "SELECT u.*, u.profile_picture, t.name as team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id WHERE u.id = ?";
    $user_stmt = $conn->prepare($user_query);
    
    if (!$user_stmt) {
        throw new Exception("Failed to prepare user query: " . $conn->error);
    }
    
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if (!$user) {
        session_destroy();
        header("Location: " . $base_path . "/login.php?error=invalid_session"); // Adjust path
        exit();
    }
} catch (Exception $e) {
    error_log("Database connection error in process-report.php: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Check if user is admin or manager (can see problem reports)
$canViewReports = ($user['role'] === 'admin' || $user['role'] === 'manager');
if (!$canViewReports) {
    header("Location: " . $base_path . "/index.php?error=access_denied"); // Adjust path
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_report'])) {
    header('Content-Type: application/json'); // Ensure JSON response for AJAX
    try {
        $report_id = (int)$_POST['report_id'];
        $new_status = $_POST['status'];
        $resolution_notes = $_POST['resolution_notes'] ?? '';
        
        $valid_statuses = ['open', 'in-progress', 'resolved', 'closed'];
        if (!in_array($new_status, $valid_statuses)) {
            throw new Exception("Invalid status provided.");
        }
        
        $conn->begin_transaction();

        // Fetch current report details before update
        $current_report_query = "SELECT * FROM problem_reports WHERE id = ?";
        $current_report_stmt = $conn->prepare($current_report_query);
        $current_report_stmt->bind_param("i", $report_id);
        $current_report_stmt->execute();
        $current_report_result = $current_report_stmt->get_result();
        $current_report = $current_report_result->fetch_assoc();
        $current_report_stmt->close();

        if (!$current_report) {
            throw new Exception("Report not found for update.");
        }
        
        // Update query
        $update_query = "UPDATE problem_reports SET status = ?, resolution_notes = ?, updated_at = NOW()";
        $params = [$new_status, $resolution_notes];
        $types = "ss"; // s for status, s for resolution_notes
        
        if ($new_status === 'resolved' || $new_status === 'closed') {
            if (empty($current_report['resolved_at']) || ($current_report['status'] !== 'resolved' && $current_report['status'] !== 'closed')) {
                $update_query .= ", resolved_at = NOW()";
            }
        } else {
            if (!empty($current_report['resolved_at']) && ($current_report['status'] === 'resolved' || $current_report['status'] === 'closed')) {
                $update_query .= ", resolved_at = NULL";
            }
        }
        
        $update_query .= " WHERE id = ?";
        $params[] = $report_id;
        $types .= "i";
        
        $update_stmt = $conn->prepare($update_query);
        if (!$update_stmt) {
            throw new Exception("Failed to prepare update query: " . $conn->error);
        }
        $update_stmt->bind_param($types, ...$params);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to execute update query: " . $update_stmt->error);
        }
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Report updated successfully!']);
        exit();
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        $error_message = "Error updating report: " . $e->getMessage();
        error_log($error_message);
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit();
    } finally {
        if ($conn && $conn->ping()) { // Check if connection is still open before closing
            $conn->close();
        }
    }
}

// Determine active tab and corresponding statuses
$active_tab = $_GET['tab'] ?? 'active'; // Default to 'active'
$status_tab_filter = [];
if ($active_tab === 'active') {
    $status_tab_filter = ['open', 'in-progress'];
} elseif ($active_tab === 'completed') {
    $status_tab_filter = ['resolved', 'closed'];
}

// Pagination settings
$reports_per_page = 15;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $reports_per_page;

// Initialize variables
$reports = [];
$total_reports = 0;

// Initialize stats with default values
$stats = [
    'total_reports' => 0, 
    'open_reports' => 0, 
    'in_progress_reports' => 0, 
    'resolved_reports' => 0, 
    'closed_reports' => 0, 
    'high_priority' => 0, 
    'medium_priority' => 0, 
    'low_priority' => 0,
    'active_reports' => 0,
    'completed_reports' => 0
];

// Get statistics for ALL reports (not filtered by current tab or search)
try {
    // FIXED: Using backticks around aliases to avoid SQL syntax errors
    $stats_query = "SELECT 
                        COUNT(*) as `total_reports`,
                        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as `open_reports`,
                        SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as `in_progress_reports`,
                        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as `resolved_reports`,
                        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as `closed_reports`,
                        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as `high_priority`,
                        SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as `medium_priority`,
                        SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as `low_priority`
                    FROM problem_reports";
    
    $stats_result = $conn->query($stats_query);
    
    if ($stats_result && $stats_result->num_rows > 0) {
        $stats_row = $stats_result->fetch_assoc();
        
        // Safely convert to integers with null coalescing
        $stats['total_reports'] = (int)($stats_row['total_reports'] ?? 0);
        $stats['open_reports'] = (int)($stats_row['open_reports'] ?? 0);
        $stats['in_progress_reports'] = (int)($stats_row['in_progress_reports'] ?? 0);
        $stats['resolved_reports'] = (int)($stats_row['resolved_reports'] ?? 0);
        $stats['closed_reports'] = (int)($stats_row['closed_reports'] ?? 0);
        $stats['high_priority'] = (int)($stats_row['high_priority'] ?? 0);
        $stats['medium_priority'] = (int)($stats_row['medium_priority'] ?? 0);
        $stats['low_priority'] = (int)($stats_row['low_priority'] ?? 0);
        
        // Calculate derived stats
        $stats['active_reports'] = $stats['open_reports'] + $stats['in_progress_reports'];
        $stats['completed_reports'] = $stats['resolved_reports'] + $stats['closed_reports'];
    }
    
} catch (Exception $e) {
    error_log("Error getting stats: " . $e->getMessage());
    // Stats remain at default 0 values
}

try {
    // Build search conditions
    $search_conditions = [];
    $search_params = [];
    $search_types = "";
    
    // Add tab-specific status filter
    if (!empty($status_tab_filter)) {
        $placeholders = implode(',', array_fill(0, count($status_tab_filter), '?'));
        $search_conditions[] = "pr.status IN ($placeholders)";
        foreach ($status_tab_filter as $status) {
            $search_params[] = $status;
            $search_types .= "s";
        }
    }

    // Search functionality
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $search = trim($_GET['search']);
        $search_conditions[] = "(pr.username LIKE ? OR pr.description LIKE ? OR pr.email LIKE ?)";
        $search_param = "%$search%";
        $search_params[] = $search_param;
        $search_params[] = $search_param;
        $search_params[] = $search_param;
        $search_types .= "sss";
    }
    
    // Status filter (only if not already covered by tab filter or if tab is 'all')
    if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
        // Only apply if the filter is not already implied by the tab
        if (!in_array($_GET['status_filter'], $status_tab_filter)) {
            $status_filter = $_GET['status_filter'];
            $search_conditions[] = "pr.status = ?";
            $search_params[] = $status_filter;
            $search_types .= "s";
        }
    }
    
    // Priority filter
    if (isset($_GET['priority_filter']) && !empty($_GET['priority_filter'])) {
        $priority_filter = $_GET['priority_filter'];
        $search_conditions[] = "pr.priority = ?";
        $search_params[] = $priority_filter;
        $search_types .= "s";
    }
    
    // Issue type filter
    if (isset($_GET['issue_filter']) && !empty($_GET['issue_filter'])) {
        $issue_filter = $_GET['issue_filter'];
        $search_conditions[] = "pr.issue_type = ?";
        $search_params[] = $issue_filter;
        $search_types .= "s";
    }
    
    // Build WHERE clause
    $where_clause = "";
    if (!empty($search_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $search_conditions);
    }
    
    // Count total reports
    $count_query = "SELECT COUNT(*) as total FROM problem_reports pr $where_clause";
    $count_stmt = $conn->prepare($count_query);
    
    if (!empty($search_params)) {
        $count_stmt->bind_param($search_types, ...$search_params);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_reports = $count_result->fetch_assoc()['total'];
    
    $total_pages = ceil($total_reports / $reports_per_page);
    $current_page = min($current_page, max(1, $total_pages));
    $offset = ($current_page - 1) * $reports_per_page;
    
    // Get actual reports - NOW INCLUDING ASSIGNED USERNAME
    $reports_query = "SELECT pr.*, 
                             TIMESTAMPDIFF(HOUR, pr.created_at, COALESCE(pr.resolved_at, NOW())) as hours_open,
                             u.username as assigned_username
                      FROM problem_reports pr 
                      LEFT JOIN users u ON pr.assigned_to = u.id
                      $where_clause
                      ORDER BY pr.created_at DESC 
                      LIMIT ? OFFSET ?";
    
    $reports_params = $search_params;
    $reports_params[] = $reports_per_page;
    $reports_params[] = $offset;
    $reports_types = $search_types . "ii";
    
    $reports_stmt = $conn->prepare($reports_query);
    if (!$reports_stmt) {
        throw new Exception("Failed to prepare reports query: " . $conn->error);
    }
    $reports_stmt->bind_param($reports_types, ...$reports_params);
    $reports_stmt->execute();
    $reports_result = $reports_stmt->get_result();
    
    while ($row = $reports_result->fetch_assoc()) {
        $reports[] = $row;
    }
    
} catch (Exception $e) {
    $error_message = "Error retrieving reports: " . $e->getMessage();
    error_log($error_message);
}

// Get team members for assignment (still needed for the modal's team_members array, even if dropdown is removed)
$team_members = [];
try {
    $team_query = "SELECT id, name, username FROM users WHERE role IN ('admin', 'manager') ORDER BY name";
    $team_result = $conn->query($team_query);
    while ($member = $team_result->fetch_assoc()) {
        $team_members[] = $member;
    }
} catch (Exception $e) {
    error_log("Error getting team members: " . $e->getMessage());
}

// Function to build pagination URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Function to build tab URL
function buildTabUrl($tab_name) {
    $params = $_GET;
    $params['tab'] = $tab_name;
    unset($params['page']); // Reset page when changing tabs
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Problem Reports - Inner SPARC Realty Corporation</title>
    <link rel="stylesheet" href="../assets/styles/main-memo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Layout */
        body.fade-in {
            opacity: 1;
            transition: opacity 0.3s ease-in;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
        }

        
        .container {
            display: flex;
            width: 100%;
        }

        .main-content {
            flex-grow: 1;
            padding: 0; /* Remove padding here, add to reports-container */
            background-color: #f3f4f6;
        }

        .reports-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%; /* Ensure it takes full width within its parent */
        }
        
        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .reports-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #1f2937;
        }
        
        .reports-title i {
            font-size: 1.5rem;
            color: #ef4444;
        }
        
        /* Tabs Styling */
        .tabs {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
            width: 100%;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            color: #6b7280;
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
            position: relative;
            top: 2px; /* To align with the border */
        }

        .tab-button:hover {
            color: #374151;
        }

        .tab-button.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3b82f6;
        }
        
        .stat-card.open { border-left-color: #ef4444; }
        .stat-card.progress { border-left-color: #f59e0b; }
        .stat-card.resolved { border-left-color: #10b981; }
        .stat-card.closed { border-left-color: #6b7280; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-filter {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-filter:hover {
            background: #2563eb;
        }
        
        .reports-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: separate; /* Use separate for rounded corners on cells */
            border-spacing: 0; /* Remove default spacing */
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb; /* Add right border for cell separation */
        }

        th:last-child, td:last-child {
            border-right: none; /* Remove right border for last column */
        }

        tr:last-child td {
            border-bottom: none; /* Remove bottom border for last row */
        }
        
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        /* Rounded corners for table header */
        .reports-table thead th:first-child {
            border-top-left-radius: 12px;
        }
        .reports-table thead th:last-child {
            border-top-right-radius: 12px;
        }
        /* Rounded corners for table body (if only one row, or for first/last cells of first/last row) */
        .reports-table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 12px;
        }
        .reports-table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 12px;
        }

        tbody tr:hover {
            background-color: #f9fafb; /* Subtle hover effect */
        }
        
        td {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priority-high {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .priority-medium {
            background: #fef3c7;
            color: #d97706;
        }
        
        .priority-low {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-open {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .status-in-progress {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-resolved {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-closed {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .btn-view {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s ease;
        }
        
        .btn-view:hover {
            background: #2563eb;
        }

        .btn-view:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .description-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            text-decoration: none;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .pagination a:hover {
            background: #f9fafb;
        }
        
        .pagination .current {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        /* START: Scoped Modal Styles for View Details Modal */
        #viewDetailsModal {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 2000; /* High z-index to be on top */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6); /* Dark overlay */
            backdrop-filter: blur(8px); /* Blur background */
            animation: fadeIn 0.3s ease-out;
            align-items: center; /* Center content vertically */
            justify-content: center; /* Center content horizontally */
            padding: 1rem; /* Padding for smaller screens */
        }

        #viewDetailsModal.show {
            display: flex; /* Show as flex to center content */
        }
        
        #viewDetailsModal .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            animation: slideUp 0.3s ease-out;
        }
        
        #viewDetailsModal .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        #viewDetailsModal .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        #viewDetailsModal .close:hover {
            color: #000;
        }
        
        #viewDetailsModal .form-group {
            margin-bottom: 1rem;
        }
        
        #viewDetailsModal .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        #viewDetailsModal .form-group input,
        #viewDetailsModal .form-group select,
        #viewDetailsModal .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            box-sizing: border-box;
        }
        
        #viewDetailsModal .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        #viewDetailsModal .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        #viewDetailsModal .btn-primary:hover {
            background: #2563eb;
        }
        
        #viewDetailsModal .description-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        #viewDetailsModal .report-details-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            padding-bottom: 1rem;
        }
        #viewDetailsModal .detail-item {
            word-break: break-word;
            padding: 0.5rem 0;
        }
        #viewDetailsModal .detail-item strong {
            color: #1f2937;
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
        }
        #viewDetailsModal .detail-item.full-width {
            grid-column: span 2;
        }
        #viewDetailsModal .detail-item p {
            margin-top: 0.5rem;
            background-color: #f9fafb;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            font-size: 0.875rem;
            color: #4b5563;
        }

        #viewDetailsModal .modal-separator {
            margin: 1.5rem 0;
            border: 0;
            border-top: 1px solid #e5e7eb;
        }

        #viewDetailsModal .modal-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            font-size: 0.875rem;
        }
        #viewDetailsModal .modal-message.success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        #viewDetailsModal .modal-message.error {
            background-color: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }
        #viewDetailsModal .modal-message i {
            margin-right: 0.5rem;
            font-size: 1rem;
        }

        /* Keyframes are global and do not need to be scoped */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .reports-container {
                padding: 1rem;
            }
            .reports-header {
                flex-direction: column;
                align-items: stretch;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filters-grid {
                grid-template-columns: 1fr;
            }
            .table-container {
                font-size: 0.75rem;
            }
            th, td {
                padding: 0.5rem;
            }

            #viewDetailsModal .modal-content {
                width: 95%;
                margin: 1rem auto;
                padding: 1.5rem;
            }
            #viewDetailsModal .modal-header {
                padding: 1rem 1.5rem 0.5rem 1.5rem;
            }
            #viewDetailsModal .report-details-content {
                grid-template-columns: 1fr;
            }
        }
        /* END: Scoped Modal Styles */
    </style>
</head>
<body>
    <div class="container">
        <?php include $base_path . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include $base_path . '/includes/header.php'; ?>
            
            <div class="reports-container">
                <div class="reports-header">
                    <div class="reports-title">
                        <i class="fas fa-bug"></i>
                        <h1>Problem Reports Dashboard</h1>
                    </div>
                </div>

                <div class="tabs">
                    <a href="<?php echo buildTabUrl('active'); ?>" class="tab-button <?php echo ($active_tab === 'active' ? 'active' : ''); ?>">
                        Active Reports (<?php echo number_format($stats['active_reports'] ?? 0); ?>)
                    </a>
                    <a href="<?php echo buildTabUrl('completed'); ?>" class="tab-button <?php echo ($active_tab === 'completed' ? 'active' : ''); ?>">
                        Completed Reports (<?php echo number_format($stats['completed_reports'] ?? 0); ?>)
                    </a>
                </div>
                
                <div class="stats-grid">
                    <?php if ($active_tab === 'active'): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($stats['active_reports'] ?? 0); ?></div>
                            <div class="stat-label">Total Active Reports</div>
                        </div>
                        <div class="stat-card open">
                            <div class="stat-number"><?php echo number_format($stats['open_reports'] ?? 0); ?></div>
                            <div class="stat-label">Open Reports</div>
                        </div>
                        <div class="stat-card progress">
                            <div class="stat-number"><?php echo number_format($stats['in_progress_reports'] ?? 0); ?></div>
                            <div class="stat-label">In Progress</div>
                        </div>
                        <div class="stat-card resolved">
                            <div class="stat-number"><?php echo number_format($stats['high_priority'] ?? 0); ?></div>
                            <div class="stat-label">High Priority</div>
                        </div>
                    <?php elseif ($active_tab === 'completed'): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($stats['completed_reports'] ?? 0); ?></div>
                            <div class="stat-label">Total Completed Reports</div>
                        </div>
                        <div class="stat-card resolved">
                            <div class="stat-number"><?php echo number_format($stats['resolved_reports'] ?? 0); ?></div>
                            <div class="stat-label">Resolved Reports</div>
                        </div>
                        <div class="stat-card closed">
                            <div class="stat-number"><?php echo number_format($stats['closed_reports'] ?? 0); ?></div>
                            <div class="stat-label">Closed Reports</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($stats['high_priority'] ?? 0); ?></div>
                            <div class="stat-label">High Priority</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="filters-section">
                    <form method="GET" action="">
                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label for="search">Search</label>
                                <input type="text" id="search" name="search" placeholder="Search username, email, or description..." 
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="status_filter">Status</label>
                                <select id="status_filter" name="status_filter">
                                    <option value="">All Statuses</option>
                                    <?php if ($active_tab === 'active'): ?>
                                        <option value="open" <?php echo ($_GET['status_filter'] ?? '') === 'open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="in-progress" <?php echo ($_GET['status_filter'] ?? '') === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <?php elseif ($active_tab === 'completed'): ?>
                                        <option value="resolved" <?php echo ($_GET['status_filter'] ?? '') === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo ($_GET['status_filter'] ?? '') === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="priority_filter">Priority</label>
                                <select id="priority_filter" name="priority_filter">
                                    <option value="">All Priorities</option>
                                    <option value="high" <?php echo ($_GET['priority_filter'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="medium" <?php echo ($_GET['priority_filter'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="low" <?php echo ($_GET['priority_filter'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="issue_filter">Issue Type</label>
                                <select id="issue_filter" name="issue_filter">
                                    <option value="">All Issues</option>
                                    <option value="login-failed" <?php echo ($_GET['issue_filter'] ?? '') === 'login-failed' ? 'selected' : ''; ?>>Login Failed</option>
                                    <option value="forgot-password" <?php echo ($_GET['issue_filter'] ?? '') === 'forgot-password' ? 'selected' : ''; ?>>Password Reset</option>
                                    <option value="account-locked" <?php echo ($_GET['issue_filter'] ?? '') === 'account-locked' ? 'selected' : ''; ?>>Account Locked</option>
                                    <option value="page-error" <?php echo ($_GET['issue_filter'] ?? '') === 'page-error' ? 'selected' : ''; ?>>Page Error</option>
                                    <option value="performance" <?php echo ($_GET['issue_filter'] ?? '') === 'performance' ? 'selected' : ''; ?>>Performance</option>
                                    <option value="feature-bug" <?php echo ($_GET['issue_filter'] ?? '') === 'feature-bug' ? 'selected' : ''; ?>>Feature Bug</option>
                                    <option value="data-issue" <?php echo ($_GET['issue_filter'] ?? '') === 'data-issue' ? 'selected' : ''; ?>>Data Issue</option>
                                    <option value="security-concern" <?php echo ($_GET['issue_filter'] ?? '') === 'security-concern' ? 'selected' : ''; ?>>Security</option>
                                    <option value="other" <?php echo ($_GET['issue_filter'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <button type="submit" class="btn-filter">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="reports-table">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Contact</th>
                                    <th>Issue Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Description</th>
                                    <th>Created</th>
                                    <th>Hours Open</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reports)): ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; padding: 2rem; color: #6b7280;">
                                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                        No problem reports found for this view.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><strong>#<?php echo $report['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($report['username']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($report['phone']); ?></div>
                                        <?php if ($report['email']): ?>
                                        <div style="font-size: 0.75rem; color: #6b7280;">
                                            <?php echo htmlspecialchars($report['email']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo ucfirst(str_replace('-', ' ', $report['issue_type'])); ?></td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo $report['priority']; ?>">
                                            <?php echo ucfirst($report['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $report['status']; ?>">
                                            <?php echo ucfirst(str_replace('-', ' ', $report['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($report['assigned_username'] ?: 'Unassigned'); ?>
                                    </td>
                                    <td>
                                        <div class="description-preview" title="<?php echo htmlspecialchars($report['description']); ?>">
                                            <?php echo htmlspecialchars(substr($report['description'], 0, 50)) . (strlen($report['description']) > 50 ? '...' : ''); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($report['created_at'])); ?></div>
                                        <div style="font-size: 0.75rem; color: #6b7280;">
                                            <?php echo date('H:i', strtotime($report['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo $report['hours_open']; ?>h</td>
                                    <td>
                                        <button class="btn-view" onclick="viewReport(<?php echo $report['id']; ?>, <?php echo $user_id; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo buildPaginationUrl(1); ?>">First</a>
                        <a href="<?php echo buildPaginationUrl($current_page - 1); ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <?php if ($i == $current_page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo buildPaginationUrl($i); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo buildPaginationUrl($current_page + 1); ?>">Next</a>
                        <a href="<?php echo buildPaginationUrl($total_pages); ?>">Last</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="viewDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Report Details</h2>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div id="viewDetailsModalMessage" class="modal-message" style="display: none;"></div>
            <div id="reportDetails">
                </div>
        </div>
    </div>

    <script>
        const basePath = '<?php echo rtrim(str_replace(basename($_SERVER['DOCUMENT_ROOT']), '', $base_path), '/'); ?>';

        function viewReport(reportId, currentUserId) {
            const modal = document.getElementById('viewDetailsModal');
            const reportDetailsDiv = document.getElementById('reportDetails');
            const modalMessageDiv = document.getElementById('viewDetailsModalMessage');

            // Clear previous content and messages
            reportDetailsDiv.innerHTML = '<div style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading details...</p></div>';
            modalMessageDiv.style.display = 'none';
            modalMessageDiv.className = 'modal-message';

            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Create form data for POST request
            const formData = new FormData();
            formData.append('get_report_details', '1');
            formData.append('report_id', reportId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    reportDetailsDiv.innerHTML = data.html;
                } else {
                    throw new Error(data.message || 'Unknown error occurred');
                }
            })
            .catch(error => {
                reportDetailsDiv.innerHTML = '<p style="color: #ef4444; text-align:center;">Error loading report details. Please try again.</p>';
                console.error('Error loading report details:', error);
            });
        }

        function closeDetailsModal() {
            const modal = document.getElementById('viewDetailsModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
            // Reload to reflect any status changes made in the modal
            location.reload(); 
        }

        // Close modal when clicking outside of it
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('viewDetailsModal');
            if (event.target == modal) {
                closeDetailsModal();
            }
        });
   
        function updateReport(reportId) {
            // Note: This function is called from the content loaded via AJAX into the modal
            const form = document.getElementById('statusForm');
            const formData = new FormData(form);
            formData.append('update_report', '1');
            formData.append('report_id', reportId);
            
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            const modalMessageDiv = document.getElementById('viewDetailsModalMessage');

            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            modalMessageDiv.style.display = 'none';
            modalMessageDiv.className = 'modal-message';

            fetch(window.location.href, { // Submit to self (process-report.php)
                method: 'POST',
                body: formData
            })
            .then(response => response.json().catch(() => { throw new Error('Invalid JSON response from server.'); }))
            .then(data => {
                if (data.success) {
                    modalMessageDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                    modalMessageDiv.classList.add('success');
                    modalMessageDiv.style.display = 'flex';
                    setTimeout(() => {
                        closeDetailsModal();
                    }, 1500); 
                } else {
                    throw new Error(data.message || 'An unknown error occurred.');
                }
            })
            .catch(error => {
                modalMessageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${error.message}`;
                modalMessageDiv.classList.add('error');
                modalMessageDiv.style.display = 'flex';
                console.error('Fetch error during report update:', error);
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        }
    </script>

    <script>
        // Add the 'fade-in' class to the body to trigger the transition
        document.body.classList.add('fade-in');
    </script>
</body>
</html>
