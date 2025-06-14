<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Establish database connection
$conn = getDbConnection();

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

$upload_dir = 'uploads/memo_images/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    if (is_string($input)) {
        $input = trim($input);
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $input;
    }
    
    return $input;
}

// Handle memo creation (admin only)
if (isset($_POST['create_memo']) && $user['role'] == 'admin') {
    $title = sanitizeInput($_POST['title']);
    $content = sanitizeInput($_POST['content']);
    $created_by = $user_id;
    
    // Insert memo
    $sql = "INSERT INTO memos (title, content, created_by) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $title, $content, $created_by);
    
    if ($stmt->execute()) {
        $memo_id = $conn->insert_id;
        
        // Set visibility for selected roles
        if (isset($_POST['visible_to'])) {
            foreach ($_POST['visible_to'] as $role) {
                $role = sanitizeInput($role);
                $sql = "INSERT INTO memo_visibility (memo_id, visible_to_role) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $memo_id, $role);
                $stmt->execute();
            }
        }
        
        // Handle image uploads
        if (!empty($_FILES['memo_images']['name'][0])) {
            $file_count = count($_FILES['memo_images']['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                $file_name = $_FILES['memo_images']['name'][$i];
                $file_tmp = $_FILES['memo_images']['tmp_name'][$i];
                $file_error = $_FILES['memo_images']['error'][$i];
                
                if ($file_error === 0) {
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = array('jpg', 'jpeg', 'png', 'gif');
                    
                    if (in_array($file_ext, $allowed_ext)) {
                        $new_file_name = uniqid('memo_') . '.' . $file_ext;
                        $file_destination = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $file_destination)) {
                            $sql = "INSERT INTO memo_images (memo_id, image_path, image_name) VALUES (?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("iss", $memo_id, $file_destination, $file_name);
                            $stmt->execute();
                        }
                    }
                }
            }
        }
        
        $success_message = "Memo created successfully!";
    } else {
        $error_message = "Error creating memo: " . $conn->error;
    }
}

// Handle memo update (admin only)
if (isset($_POST['update_memo']) && $user['role'] == 'admin') {
    $memo_id = (int)$_POST['memo_id'];
    $title = sanitizeInput($_POST['title']);
    $content = sanitizeInput($_POST['content']);
    
    $sql = "UPDATE memos SET title = ?, content = ? WHERE memo_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $title, $content, $memo_id);
    
    if ($stmt->execute()) {
        // Delete existing visibility settings
        $sql = "DELETE FROM memo_visibility WHERE memo_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        
        // Set new visibility for selected roles
        if (isset($_POST['visible_to'])) {
            foreach ($_POST['visible_to'] as $role) {
                $role = sanitizeInput($role);
                $sql = "INSERT INTO memo_visibility (memo_id, visible_to_role) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $memo_id, $role);
                $stmt->execute();
            }
        }
        
        $success_message = "Memo updated successfully!";
    } else {
        $error_message = "Error updating memo: " . $conn->error;
    }
}

// Handle memo deletion (admin only)
if (isset($_GET['delete_memo']) && $user['role'] == 'admin') {
    $memo_id = (int)$_GET['delete_memo'];
    
    // Get image paths before deleting memo
    $sql = "SELECT image_path FROM memo_images WHERE memo_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $image_paths = [];
    while ($row = $result->fetch_assoc()) {
        $image_paths[] = $row['image_path'];
    }
    
    // Delete memo
    $sql = "DELETE FROM memos WHERE memo_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $memo_id);
    
    if ($stmt->execute()) {
        // Delete image files from server
        foreach ($image_paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $success_message = "Memo deleted successfully!";
    } else {
        $error_message = "Error deleting memo: " . $conn->error;
    }
}

// Get current tab for filtering
$current_tab = isset($_GET['tab']) ? sanitizeInput($_GET['tab']) : 'all';

// Build the SQL query based on user role and tab
$base_sql = "";
$where_conditions = [];
$params = [];
$param_types = "";

if ($user['role'] == 'admin') {
    // Admins can see all memos with visibility info
    $base_sql = "SELECT m.*, u.name as creator_name, 
                GROUP_CONCAT(DISTINCT mv.visible_to_role) as visible_roles
                FROM memos m 
                JOIN users u ON m.created_by = u.id 
                LEFT JOIN memo_visibility mv ON m.memo_id = mv.memo_id";
    
    // Add tab-based filtering
    switch ($current_tab) {
        case 'today':
            $where_conditions[] = "DATE(m.created_at) = CURDATE()";
            break;
        case 'this_week':
            $where_conditions[] = "YEARWEEK(m.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'this_month':
            $where_conditions[] = "YEAR(m.created_at) = YEAR(CURDATE()) AND MONTH(m.created_at) = MONTH(CURDATE())";
            break;
        case 'manager':
        case 'supervisor':
        case 'agent':
            $where_conditions[] = "mv.visible_to_role = ?";
            $params[] = $current_tab;
            $param_types .= "s";
            break;
    }
} else {
    // Other roles see memos visible to their role
    $role = $user['role'];
    $base_sql = "SELECT m.*, u.name as creator_name 
                FROM memos m 
                JOIN users u ON m.created_by = u.id 
                JOIN memo_visibility mv ON m.memo_id = mv.memo_id";
    
    $where_conditions[] = "mv.visible_to_role = ?";
    $params[] = $role;
    $param_types .= "s";
    
    // Add date filtering for non-admin users
    switch ($current_tab) {
        case 'today':
            $where_conditions[] = "DATE(m.created_at) = CURDATE()";
            break;
        case 'this_week':
            $where_conditions[] = "YEARWEEK(m.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'this_month':
            $where_conditions[] = "YEAR(m.created_at) = YEAR(CURDATE()) AND MONTH(m.created_at) = MONTH(CURDATE())";
            break;
    }
}

// Search functionality
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = sanitizeInput($_GET['search']);
    $where_conditions[] = "(m.title LIKE ? OR m.content LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

// Complete the SQL query
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
$sql = $base_sql . " " . $where_clause . " GROUP BY m.memo_id ORDER BY m.created_at DESC";

// Execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$memos = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $memos[] = $row;
    }
}

// Get images for each memo
foreach ($memos as &$memo) {
    $memo_id = $memo['memo_id'];
    $sql = "SELECT * FROM memo_images WHERE memo_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $memo['images'] = [];
    while ($row = $result->fetch_assoc()) {
        $memo['images'][] = $row;
    }
}
unset($memo);

// Handle view memo request
$view_memo = null;
if (isset($_GET['view_memo'])) {
    $memo_id = (int)$_GET['view_memo'];
    
    // Get memo details based on user role
    if ($user['role'] == 'admin') {
        $sql = "SELECT m.*, u.name as creator_name, 
                GROUP_CONCAT(DISTINCT mv.visible_to_role) as visible_roles
                FROM memos m 
                JOIN users u ON m.created_by = u.id 
                LEFT JOIN memo_visibility mv ON m.memo_id = mv.memo_id 
                WHERE m.memo_id = ?
                GROUP BY m.memo_id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $memo_id);
    } else {
        $role = $user['role'];
        $sql = "SELECT m.*, u.name as creator_name 
                FROM memos m 
                JOIN users u ON m.created_by = u.id 
                JOIN memo_visibility mv ON m.memo_id = mv.memo_id 
                WHERE m.memo_id = ? AND mv.visible_to_role = ?
                GROUP BY m.memo_id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $memo_id, $role);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $view_memo = $result->fetch_assoc();
        
        // Get memo images
        $sql = "SELECT * FROM memo_images WHERE memo_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $memo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $view_memo['images'] = [];
        while ($row = $result->fetch_assoc()) {
            $view_memo['images'][] = $row;
        }
    }
}

// Get edit memo data
$edit_memo = null;
if (isset($_GET['edit_memo']) && $user['role'] == 'admin') {
    $memo_id = (int)$_GET['edit_memo'];
    
    $sql = "SELECT m.*, GROUP_CONCAT(DISTINCT mv.visible_to_role) as visible_roles 
            FROM memos m 
            LEFT JOIN memo_visibility mv ON m.memo_id = mv.memo_id 
            WHERE m.memo_id = ?
            GROUP BY m.memo_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_memo = $result->fetch_assoc();
        $edit_memo['visible_roles_array'] = explode(',', $edit_memo['visible_roles']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memos - InnerSPARC Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Enhanced Memo Management Styles */
        :root {
            --primary: #0ea5e9;
            --primary-light: #38bdf8;
            --primary-dark: #0284c7;
            --secondary: #8b5cf6;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f8fafc;
            --dark: #0f172a;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --gray-dark: #334155;
            --border-radius: 0.5rem;
            --box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --box-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --box-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --transition: all 0.2s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f1f5f9;
            color: var(--dark);
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 0;
        }

        .memo-page {
            padding: 1.5rem;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .page-title-icon {
            width: 2.5rem;
            height: 2.5rem;
            background-color: var(--primary-light);
            color: white;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        /* Search */
        .search-container {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background-color: white;
            box-shadow: var(--box-shadow);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        /* Tabs */
        .memo-tabs {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--gray-light);
            overflow-x: auto;
            background-color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            box-shadow: var(--box-shadow);
        }

        .memo-tab {
            padding: 1rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray);
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            text-decoration: none;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
        }

        .memo-tab:hover {
            color: var(--primary);
            background-color: rgba(14, 165, 233, 0.05);
        }

        .memo-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background-color: rgba(14, 165, 233, 0.1);
        }

        /* Memo Grid */
        .memo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .memo-card {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            min-height: 400px;
            cursor: pointer;
        }

        .memo-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-lg);
        }

        .memo-image {
            height: 250px;
            overflow: hidden;
            position: relative;
        }

        .memo-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .memo-card:hover .memo-image img {
            transform: scale(1.05);
        }

        .memo-image-count {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background-color: rgba(0, 0, 0, 0.6);
            color: white;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
        }

        .memo-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 250px;
        }

        .memo-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .memo-text {
            color: var(--gray-dark);
            font-size: 1rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
            flex: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 5;
            -webkit-box-orient: vertical;
        }

        .memo-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
            font-size: 0.875rem;
            color: var(--gray);
            margin-top: auto;
        }

        .memo-author {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .memo-author-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .memo-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background-color: #f8fafc;
            border-top: 1px solid var(--gray-light);
        }

        .memo-visibility {
            display: flex;
            gap: 0.5rem;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.35rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: #f1f5f9;
            color: var(--gray);
        }

        .role-badge.manager {
            background-color: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }

        .role-badge.supervisor {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .role-badge.agent {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .memo-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: var(--transition);
            cursor: pointer;
            color: var(--gray);
            background-color: white;
            border: 1px solid var(--gray-light);
            text-decoration: none;
        }

        .btn-icon:hover {
            background-color: var(--gray-light);
        }

        .btn-icon.edit {
            color: var(--info);
            border-color: rgba(59, 130, 246, 0.2);
        }

        .btn-icon.edit:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .btn-icon.delete {
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .btn-icon.delete:hover {
            background-color: rgba(239, 68, 68, 0.1);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.75);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-lg);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--gray);
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: 50%;
        }

        .modal-close:hover {
            color: var(--danger);
            background-color: rgba(239, 68, 68, 0.1);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--gray-light);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: var(--transition);
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.2);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn-secondary {
            background-color: white;
            color: var(--gray-dark);
            border: 1px solid var(--gray-light);
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-secondary:hover {
            background-color: var(--gray-light);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .empty-state-icon {
            font-size: 3rem;
            color: var(--gray-light);
            margin-bottom: 1.5rem;
        }

        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }

        .empty-state-text {
            color: var(--gray);
            max-width: 30rem;
            margin: 0 auto;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: rgba(34, 197, 94, 0.1);
            border-left: 4px solid var(--success);
            color: var(--success);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .memo-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .memo-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .memo-tabs {
                overflow-x: auto;
            }

            .modal {
                width: 95%;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="memo-page">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title">
                        <div class="page-title-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h1>Memo Management</h1>
                    </div>
                    
                    <div class="header-actions">
                        <?php if ($user['role'] == 'admin'): ?>
                            <button onclick="openCreateModal()" class="btn-primary">
                                <i class="fas fa-plus"></i> Create New Memo
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Alerts -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Search -->
                <div class="search-container">
                    <form action="" method="GET">
                        <div class="search-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <input type="text" name="search" class="search-input" 
                               placeholder="Search memos by title or content..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <?php if ($current_tab != 'all'): ?>
                            <input type="hidden" name="tab" value="<?php echo $current_tab; ?>">
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Tabs -->
                <div class="memo-tabs">
                    <a href="?tab=all" class="memo-tab <?php echo $current_tab == 'all' ? 'active' : ''; ?>">All Memos</a>
                    <a href="?tab=today" class="memo-tab <?php echo $current_tab == 'today' ? 'active' : ''; ?>">Today</a>
                    <a href="?tab=this_week" class="memo-tab <?php echo $current_tab == 'this_week' ? 'active' : ''; ?>">This Week</a>
                    <a href="?tab=this_month" class="memo-tab <?php echo $current_tab == 'this_month' ? 'active' : ''; ?>">This Month</a>
                    <?php if ($user['role'] == 'admin'): ?>
                        <a href="?tab=manager" class="memo-tab <?php echo $current_tab == 'manager' ? 'active' : ''; ?>">Manager</a>
                        <a href="?tab=supervisor" class="memo-tab <?php echo $current_tab == 'supervisor' ? 'active' : ''; ?>">Supervisor</a>
                        <a href="?tab=agent" class="memo-tab <?php echo $current_tab == 'agent' ? 'active' : ''; ?>">Agent</a>
                    <?php endif; ?>
                </div>
                
                <!-- Memos Display -->
                <?php if (empty($memos)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="empty-state-title">No memos found</h3>
                        <p class="empty-state-text">
                            <?php if (isset($_GET['search'])): ?>
                                No memos match your search criteria. Try different keywords.
                            <?php elseif ($user['role'] == 'admin'): ?>
                                Create your first memo by clicking the "Create New Memo" button above.
                            <?php else: ?>
                                No memos have been shared with <?php echo $user['role']; ?>s yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="memo-grid">
                        <?php foreach ($memos as $memo): ?>
                            <div class="memo-card" onclick="viewMemo(<?php echo $memo['memo_id']; ?>)">
                                <?php if (!empty($memo['images'])): ?>
                                    <div class="memo-image">
                                        <img src="<?php echo htmlspecialchars($memo['images'][0]['image_path']); ?>" alt="Memo image">
                                        <?php if (count($memo['images']) > 1): ?>
                                            <div class="memo-image-count">
                                                +<?php echo count($memo['images']) - 1; ?> more
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="memo-content">
                                    <h3 class="memo-title"><?php echo htmlspecialchars($memo['title']); ?></h3>
                                    <div class="memo-text"><?php echo nl2br(htmlspecialchars($memo['content'])); ?></div>
                                    
                                    <div class="memo-meta">
                                        <div class="memo-author">
                                            <div class="memo-author-avatar">
                                                <?php echo strtoupper(substr($memo['creator_name'], 0, 1)); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($memo['creator_name']); ?></span>
                                        </div>
                                        <div class="memo-date">
                                            <?php echo date('M j, Y', strtotime($memo['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($user['role'] == 'admin'): ?>
                                    <div class="memo-footer">
                                        <div class="memo-visibility">
                                            <?php 
                                            if (isset($memo['visible_roles'])) {
                                                $roles = explode(',', $memo['visible_roles']);
                                                foreach ($roles as $role): 
                                                    if (!empty(trim($role))):
                                            ?>
                                                <span class="role-badge <?php echo trim($role); ?>">
                                                    <i class="fas fa-<?php echo trim($role) == 'manager' ? 'user-tie' : (trim($role) == 'supervisor' ? 'user-shield' : 'user'); ?>"></i>
                                                    <?php echo ucfirst(trim($role)); ?>
                                                </span>
                                            <?php 
                                                    endif;
                                                endforeach; 
                                            }
                                            ?>
                                        </div>
                                        
                                        <div class="memo-actions">
                                            <a href="?edit_memo=<?php echo $memo['memo_id']; ?>" class="btn-icon edit" title="Edit" onclick="event.stopPropagation();">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete_memo=<?php echo $memo['memo_id']; ?>" class="btn-icon delete" title="Delete" 
                                               onclick="event.stopPropagation(); return confirm('Are you sure you want to delete this memo?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Memo Modal -->
    <div id="createMemoModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Create New Memo</h3>
                <button type="button" class="modal-close" onclick="closeCreateModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title" class="form-label">Memo Title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="content" class="form-label">Memo Content</label>
                        <textarea id="content" name="content" class="form-control" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Visible to:</label>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="visible_to[]" value="manager">
                                Managers
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="visible_to[]" value="supervisor">
                                Supervisors
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="visible_to[]" value="agent">
                                Agents
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="memo_images" class="form-label">Images (Optional)</label>
                        <input type="file" name="memo_images[]" id="memo_images" class="form-control" accept="image/*" multiple>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeCreateModal()">Cancel</button>
                        <button type="submit" name="create_memo" class="btn-primary">Create Memo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Memo Modal -->
    <?php if ($edit_memo): ?>
    <div id="editMemoModal" class="modal-overlay active">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Edit Memo</h3>
                <button type="button" class="modal-close" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="memo_id" value="<?php echo $edit_memo['memo_id']; ?>">
                    
                    <div class="form-group">
                        <label for="edit_title" class="form-label">Memo Title</label>
                        <input type="text" id="edit_title" name="title" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_memo['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_content" class="form-label">Memo Content</label>
                        <textarea id="edit_content" name="content" class="form-control" required><?php echo htmlspecialchars($edit_memo['content']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Visible to:</label>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="visible_to[]" value="manager" 
                                       <?php echo in_array('manager', $edit_memo['visible_roles_array']) ? 'checked' : ''; ?>>
                                Managers
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="visible_to[]" value="supervisor" 
                                       <?php echo in_array('supervisor', $edit_memo['visible_roles_array']) ? 'checked' : ''; ?>>
                                Supervisors
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="visible_to[]" value="agent" 
                                       <?php echo in_array('agent', $edit_memo['visible_roles_array']) ? 'checked' : ''; ?>>
                                Agents
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_memo_images" class="form-label">Add More Images (Optional)</label>
                        <input type="file" name="memo_images[]" id="edit_memo_images" class="form-control" accept="image/*" multiple>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" name="update_memo" class="btn-primary">Update Memo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- View Memo Modal -->
    <?php if ($view_memo): ?>
    <div id="viewMemoModal" class="modal-overlay active">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title"><?php echo htmlspecialchars($view_memo['title']); ?></h3>
                <button type="button" class="modal-close" onclick="closeViewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <div style="width: 2.5rem; height: 2.5rem; border-radius: 50%; background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                            <?php echo strtoupper(substr($view_memo['creator_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($view_memo['creator_name']); ?></div>
                            <div style="font-size: 0.875rem; color: var(--gray);"><?php echo date('F j, Y \a\t g:i A', strtotime($view_memo['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($user['role'] == 'admin' && isset($view_memo['visible_roles'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong>Visible to:</strong>
                            <?php 
                            $roles = explode(',', $view_memo['visible_roles']);
                            foreach ($roles as $role): 
                                if (!empty(trim($role))):
                            ?>
                                <span class="role-badge <?php echo trim($role); ?>" style="margin-left: 0.5rem;">
                                    <i class="fas fa-<?php echo trim($role) == 'manager' ? 'user-tie' : (trim($role) == 'supervisor' ? 'user-shield' : 'user'); ?>"></i>
                                    <?php echo ucfirst(trim($role)); ?>
                                </span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div style="white-space: pre-line; line-height: 1.6; margin-bottom: 1.5rem;">
                    <?php echo nl2br(htmlspecialchars($view_memo['content'])); ?>
                </div>
                
                <?php if (!empty($view_memo['images'])): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem;">
                        <?php foreach ($view_memo['images'] as $image): ?>
                            <div style="aspect-ratio: 1; border-radius: 0.5rem; overflow: hidden; cursor: pointer;" onclick="openImageModal('<?php echo htmlspecialchars($image['image_path']); ?>')">
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="<?php echo htmlspecialchars($image['image_name']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <?php if ($user['role'] == 'admin'): ?>
                    <a href="?edit_memo=<?php echo $view_memo['memo_id']; ?>" class="btn-primary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                <?php endif; ?>
                <button type="button" class="btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Image Modal -->
    <div id="imageModal" class="modal-overlay">
        <div class="modal" style="max-width: 90%; max-height: 90%; background: transparent; box-shadow: none;">
            <div style="position: relative; text-align: center;">
                <img id="modalImage" src="/placeholder.svg" alt="Full size image" style="max-width: 100%; max-height: 90vh; border-radius: 0.5rem;">
                <button type="button" class="modal-close" onclick="closeImageModal()" 
                        style="position: absolute; top: -3rem; right: 0; background: rgba(255,255,255,0.2); color: white;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openCreateModal() {
            document.getElementById('createMemoModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeCreateModal() {
            document.getElementById('createMemoModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function closeEditModal() {
            window.location.href = 'memo.php';
        }

        function closeViewModal() {
            window.location.href = 'memo.php';
        }

        function viewMemo(memoId) {
            window.location.href = '?view_memo=' + memoId;
        }

        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const checkboxes = this.querySelectorAll('input[name="visible_to[]"]:checked');
                    if (checkboxes.length === 0 && this.querySelector('input[name="visible_to[]"]')) {
                        e.preventDefault();
                        alert('Please select at least one role that can view this memo.');
                    }
                });
            });
        });
    </script>
</body>
</html>