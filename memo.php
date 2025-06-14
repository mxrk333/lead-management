<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
// Get user information with team name
$conn = getDbConnection();
$user_query = "SELECT u.*, t.name as team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id WHERE u.id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    header("Location: login.php");
    exit();
}

// Check if user is admin (can see all memos)
$isAdmin = ($user['role'] === 'admin');

// Check if user can create memos (admin or manager)
$canCreateMemos = ($user['role'] === 'admin' || $user['role'] === 'manager');

// Pagination settings
$memos_per_page = 12;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $memos_per_page;

// Handle memo acknowledgment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_memo'])) {
    try {
        $memo_id = $_POST['memo_id'];
        $user_id = $_SESSION['user_id'];
        
        // First, verify the user can access this memo (same logic as viewing)
        $can_acknowledge = false;
        
        if ($isAdmin) {
            // Admins can acknowledge any memo
            $can_acknowledge = true;
        } else {
            // Check if user can access this memo based on visibility rules
            $access_check = $conn->prepare("SELECT m.id FROM memos m WHERE m.id = ? AND (
                m.visible_to_all = 1 
                OR m.created_by = ?
                OR EXISTS (
                    SELECT 1 FROM memo_team_visibility mtv 
                    WHERE mtv.memo_id = m.id AND mtv.team_id = ?
                )
            )");
            $access_check->bind_param("iii", $memo_id, $user_id, $user['team_id']);
            $access_check->execute();
            $access_result = $access_check->get_result();
            $can_acknowledge = ($access_result->num_rows > 0);
        }
        
        if (!$can_acknowledge) {
            throw new Exception("You don't have permission to acknowledge this memo.");
        }
        
        $conn->begin_transaction();
        
        $check_stmt = $conn->prepare("SELECT id FROM memo_read_status WHERE memo_id = ? AND employee_id = ?");
        $check_stmt->bind_param("ii", $memo_id, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO memo_read_status (memo_id, employee_id, read_status, read_at) VALUES (?, ?, 1, NOW())");
            $stmt->bind_param("ii", $memo_id, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE memo_read_status SET read_status = 1, read_at = NOW() WHERE memo_id = ? AND employee_id = ?");
            $stmt->bind_param("ii", $memo_id, $user_id);
        }
        
        $stmt->execute();
        $conn->commit();
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?acknowledgment=success");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error acknowledging memo: " . $e->getMessage();
    }
}

// VISIBILITY RULES:
// 1. Admins can see ALL memos
// 2. Managers and regular users can only see:
//    a. Public memos (visible_to_all = 1) OR
//    b. Memos specifically assigned to their team OR  
//    c. Memos created by them
$memos = [];
$total_memos = 0;

try {
    // Build search conditions
    $search_conditions = [];
    $search_params = [];
    $search_types = "";
    
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = $_GET['search'];
        $search_conditions[] = "(m.title LIKE ? OR m.description LIKE ? OR u.name LIKE ?)";
        $search_param = "%$search%";
        $search_params[] = $search_param;
        $search_params[] = $search_param;
        $search_params[] = $search_param;
        $search_types .= "sss";
    }
    
    if (isset($_GET['team']) && !empty($_GET['team'])) {
        $team_filter = $_GET['team'];
        $search_conditions[] = "creator_team.name = ?";
        $search_params[] = $team_filter;
        $search_types .= "s";
    }
    
    if (isset($_GET['visibility']) && !empty($_GET['visibility'])) {
        $visibility_filter = $_GET['visibility'];
        if ($visibility_filter === 'all_teams') {
            $search_conditions[] = "m.visible_to_all = 1";
        } elseif ($visibility_filter === 'specific_teams') {
            $search_conditions[] = "m.visible_to_all = 0";
        }
    }
    
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $status_filter = $_GET['status'];
        if ($status_filter === 'read') {
            $search_conditions[] = "mrs.read_status = 1";
        } elseif ($status_filter === 'unread') {
            $search_conditions[] = "(mrs.read_status IS NULL OR mrs.read_status = 0)";
        }
    }
    
    // Build additional WHERE conditions
    $additional_where = "";
    if (!empty($search_conditions)) {
        $additional_where = " AND " . implode(" AND ", $search_conditions);
    }
    
    // VISIBILITY RULES
    if ($isAdmin) {
        // Admins can see all memos
        $visibility_where = "WHERE 1=1"; // No restrictions
        $base_params = [];
        $base_types = "";
    } else {
        // Managers and regular users have team-based visibility
        $visibility_where = "WHERE (
            m.visible_to_all = 1 
            OR m.created_by = ?
            OR EXISTS (
                SELECT 1 FROM memo_team_visibility mtv 
                WHERE mtv.memo_id = m.id AND mtv.team_id = ?
            )
        )";
        $base_params = [$user_id, $user['team_id']];
        $base_types = "ii";
    }
    
    $final_where = $visibility_where . $additional_where;
    
    // Combine with search parameters
    $final_params = array_merge($base_params, $search_params);
    $final_types = $base_types . $search_types;
    
    // Count total memos
    $count_query = "SELECT COUNT(DISTINCT m.id) as total 
                    FROM memos m
                    INNER JOIN users u ON m.created_by = u.id
                    INNER JOIN teams creator_team ON m.team_id = creator_team.id
                    LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?
                    $final_where";
    
    // Add user_id for memo_read_status join to parameters
    $count_params = [$user_id];
    $count_params = array_merge($count_params, $final_params);
    $count_types = "i" . $final_types;
    
    $count_stmt = $conn->prepare($count_query);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_memos = $count_result->fetch_assoc()['total'];
    
    $total_pages = ceil($total_memos / $memos_per_page);
    $current_page = min($current_page, max(1, $total_pages));
    $offset = ($current_page - 1) * $memos_per_page;
    
    // Get actual memos
    $memo_query = "SELECT m.*, u.name as created_by_name, creator_team.name as creator_team_name,
                          mrs.read_status, mrs.read_at,
                          GROUP_CONCAT(DISTINCT vt.name ORDER BY vt.name SEPARATOR ', ') as visible_teams
                   FROM memos m
                   INNER JOIN users u ON m.created_by = u.id
                   INNER JOIN teams creator_team ON m.team_id = creator_team.id
                   LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?
                   LEFT JOIN memo_team_visibility mtv ON m.id = mtv.memo_id
                   LEFT JOIN teams vt ON mtv.team_id = vt.id
                   $final_where
                   GROUP BY m.id
                   ORDER BY m.created_at DESC
                   LIMIT ? OFFSET ?";
    
    $memo_params = [$user_id];
    $memo_params = array_merge($memo_params, $final_params);
    $memo_params[] = $memos_per_page;
    $memo_params[] = $offset;
    $memo_types = "i" . $final_types . "ii";
    
    $memo_stmt = $conn->prepare($memo_query);
    $memo_stmt->bind_param($memo_types, ...$memo_params);
    $memo_stmt->execute();
    $memo_result = $memo_stmt->get_result();
    
    while ($row = $memo_result->fetch_assoc()) {
        $memos[] = $row;
    }
    
} catch (Exception $e) {
    $error_message = "Error retrieving memos: " . $e->getMessage();
}

// Get statistics with appropriate filtering
$stats = ['total_memos' => 0, 'public_memos' => 0, 'private_memos' => 0, 'read_memos' => 0];

try {
    if ($isAdmin) {
        // Admin stats - all memos
        $stats_query = "SELECT 
            COUNT(DISTINCT m.id) as total_memos,
            SUM(CASE WHEN m.visible_to_all = 1 THEN 1 ELSE 0 END) as public_memos,
            SUM(CASE WHEN m.visible_to_all = 0 THEN 1 ELSE 0 END) as private_memos,
            SUM(CASE WHEN mrs.read_status = 1 AND mrs.employee_id = ? THEN 1 ELSE 0 END) as read_memos
        FROM memos m
        LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?";
        
        $stats_stmt = $conn->prepare($stats_query);
        $stats_stmt->bind_param("ii", $user_id, $user_id);
    } else {
        // Team-based stats for managers and regular users
        $stats_query = "SELECT 
            COUNT(DISTINCT m.id) as total_memos,
            SUM(CASE WHEN m.visible_to_all = 1 THEN 1 ELSE 0 END) as public_memos,
            SUM(CASE WHEN m.visible_to_all = 0 THEN 1 ELSE 0 END) as private_memos,
            SUM(CASE WHEN mrs.read_status = 1 AND mrs.employee_id = ? THEN 1 ELSE 0 END) as read_memos
        FROM memos m
        LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?
        WHERE (
            m.visible_to_all = 1 
            OR m.created_by = ?
            OR EXISTS (
                SELECT 1 FROM memo_team_visibility mtv 
                WHERE mtv.memo_id = m.id AND mtv.team_id = ?
            )
        )";
        
        $stats_stmt = $conn->prepare($stats_query);
        $stats_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user['team_id']);
    }
    
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    // Keep default stats
}

// Function to build pagination URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Get unique teams for filter (only from visible memos)
$team_names = array_unique(array_column($memos, 'creator_team_name'));
sort($team_names);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memos - Inner SPARC Realty Corporation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #e0e7ff;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
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
            --border-radius: 0.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.5;
            margin: 0;
            min-height: 100vh;
            display: flex;
        }
        
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--gray-50);
        }
        
        .memos-page {
            flex: 1;
            padding: 1.5rem;
            width: 100%;
            margin: 0;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }

        /* Team-based visibility notice */
        .visibility-notice {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .visibility-notice h3 {
            margin-bottom: 0.5rem;
            font-size: 1.125rem;
        }

        .visibility-notice p {
            margin: 0;
            opacity: 0.9;
        }

        /* Admin visibility notice */
        .admin-notice {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .admin-notice h3 {
            margin-bottom: 0.5rem;
            font-size: 1.125rem;
        }

        .admin-notice p {
            margin: 0;
            opacity: 0.9;
        }

        /* Page header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header h2 i {
            color: var(--primary);
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-add:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Summary cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .summary-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .summary-icon {
            width: 3rem;
            height: 3rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.25rem;
        }

        .summary-info {
            flex: 1;
        }

        .summary-info h3 {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin: 0 0 0.25rem 0;
            font-weight: 500;
        }

        .summary-info p {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        /* Filters */
        .filters-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        /* Memo cards grid */
        .memos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .memo-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid var(--gray-200);
            position: relative;
        }

        .memo-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        .memo-card.unread {
            border-left: 4px solid var(--warning);
        }

        .memo-card.read {
            border-left: 4px solid var(--success);
        }

        .memo-header {
            padding: 1.25rem 1.25rem 0 1.25rem;
        }

        .memo-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .memo-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .memo-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .memo-content {
            padding: 0 1.25rem;
            margin-bottom: 1rem;
        }

        .memo-description {
            color: var(--gray-600);
            font-size: 0.875rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .memo-footer {
            padding: 1rem 1.25rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .memo-visibility {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .visibility-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .visibility-badge.public {
            background: var(--info-light);
            color: var(--info);
        }

        .visibility-badge.private {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .visible-teams {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .memo-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-acknowledge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            background: var(--success);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-acknowledge:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-acknowledged {
            background: var(--gray-100);
            color: var(--gray-600);
            cursor: not-allowed;
        }

        .btn-acknowledged:hover {
            background: var(--gray-100);
            transform: none;
        }

        .attachment-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.read {
            background: var(--success-light);
            color: var(--success);
        }

        .status-badge.unread {
            background: var(--warning-light);
            color: var(--warning);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-400);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: var(--gray-600);
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination-info {
            text-align: center;
            color: var(--gray-500);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .pagination-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.5rem;
            border-radius: var(--border-radius);
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .pagination-button.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-button:hover:not(.active):not(.disabled) {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }

        .pagination-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Alert messages */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: var(--success-light);
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-danger {
            background-color: var(--danger-light);
            border-color: #fecaca;
            color: #dc2626;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .memos-page {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .btn-add {
                width: 100%;
                justify-content: center;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .search-form {
                grid-template-columns: 1fr;
            }

            .memos-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="memos-page">
                <!-- Visibility notice based on role -->
                <?php if ($isAdmin): ?>
                <div class="admin-notice">
                    <h3>👑 Admin Access</h3>
                    <p>As an admin, you can see all memos in the system regardless of team assignment.</p>
                </div>
                <?php else: ?>
                <div class="visibility-notice">
                    <h3>🔒 Team-Based Memo Access</h3>
                    <p>You can only see memos that are public, assigned to your team<?php echo !empty($user['team_name']) ? ' (' . htmlspecialchars($user['team_name']) . ')' : ''; ?>, or created by you.</p>
                </div>
                <?php endif; ?>

                <div class="page-header">
                    <h2><i class="fas fa-envelope"></i> Memos Management</h2>
                    <?php if ($canCreateMemos): ?>
                    <a href="add-memo.php" class="btn-add">
                        <i class="fas fa-plus"></i>
                        Add New Memo
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (isset($_GET['acknowledgment']) && $_GET['acknowledgment'] === 'success'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Memo acknowledged successfully!
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--primary-light); color: var(--primary);">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="summary-info">
                            <h3><?php echo $isAdmin ? 'Total Memos' : 'Accessible Memos'; ?></h3>
                            <p><?php echo $stats['total_memos']; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--info-light); color: var(--info);">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Public Memos</h3>
                            <p><?php echo $stats['public_memos']; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Team-Specific Memos</h3>
                            <p><?php echo $stats['private_memos']; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Read by You</h3>
                            <p><?php echo $stats['read_memos']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="filters-container">
                    <form class="search-form" method="GET" action="">
                        <div class="form-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by title, description, author..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Team</label>
                            <select name="team" class="form-control">
                                <option value="">All Teams</option>
                                <?php foreach ($team_names as $team_name): ?>
                                    <option value="<?php echo htmlspecialchars($team_name); ?>" 
                                        <?php echo (isset($_GET['team']) && $_GET['team'] === $team_name) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Visibility</label>
                            <select name="visibility" class="form-control">
                                <option value="">All Visibility</option>
                                <option value="all_teams" <?php echo (isset($_GET['visibility']) && $_GET['visibility'] === 'all_teams') ? 'selected' : ''; ?>>
                                    All Teams
                                </option>
                                <option value="specific_teams" <?php echo (isset($_GET['visibility']) && $_GET['visibility'] === 'specific_teams') ? 'selected' : ''; ?>>
                                    Specific Teams
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="read" <?php echo (isset($_GET['status']) && $_GET['status'] === 'read') ? 'selected' : ''; ?>>
                                    Read
                                </option>
                                <option value="unread" <?php echo (isset($_GET['status']) && $_GET['status'] === 'unread') ? 'selected' : ''; ?>>
                                    Unread
                                </option>
                            </select>
                        </div>
                    </form>
                </div>
                
                <?php if (empty($memos)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No memos found</h3>
                        <p>Try adjusting your search criteria or filters</p>
                        <?php if (!$isAdmin): ?>
                        <p><small><strong>Note:</strong> You can only see memos that are public, assigned to your team<?php echo !empty($user['team_name']) ? ' (' . htmlspecialchars($user['team_name']) . ')' : ''; ?>, or created by you.</small></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="memos-grid">
                        <?php foreach ($memos as $memo): ?>
                            <div class="memo-card <?php echo ($memo['read_status'] == 1) ? 'read' : 'unread'; ?>" 
                                 onclick="openMemoModal(<?php echo $memo['id']; ?>)">
                                
                                <?php if (!empty($memo['file_path'])): ?>
                                    <div class="attachment-indicator">
                                        <i class="fas fa-paperclip"></i> Attachment
                                    </div>
                                <?php endif; ?>
                                
                                <div class="memo-header">
                                    <h3 class="memo-title"><?php echo htmlspecialchars($memo['title']); ?></h3>
                                    <div class="memo-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($memo['created_by_name']); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($memo['created_at'])); ?></span>
                                        <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($memo['creator_team_name']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="memo-content">
                                    <p class="memo-description"><?php echo htmlspecialchars($memo['description']); ?></p>
                                </div>
                                
                                <div class="memo-footer">
                                    <div class="memo-visibility">
                                        <?php if ($memo['visible_to_all'] == 1): ?>
                                            <span class="visibility-badge public">
                                                <i class="fas fa-globe"></i> All Teams
                                            </span>
                                        <?php else: ?>
                                            <span class="visibility-badge private">
                                                <i class="fas fa-users"></i> Specific Teams
                                            </span>
                                            <?php if (!empty($memo['visible_teams'])): ?>
                                                <div class="visible-teams">
                                                    Visible to: <?php echo htmlspecialchars($memo['visible_teams']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    

                                    
                                    <div class="memo-actions">
                                        <?php if ($memo['read_status'] == 1): ?>
                                            <button class="btn-acknowledge btn-acknowledged" disabled>
                                                <i class="fas fa-check"></i> Acknowledged
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;" onclick="event.stopPropagation();">
                                                <input type="hidden" name="memo_id" value="<?php echo $memo['id']; ?>">
                                                <button type="submit" name="acknowledge_memo" class="btn-acknowledge">
                                                    <i class="fas fa-check"></i> Acknowledge
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing <?php echo min(($current_page - 1) * $memos_per_page + 1, $total_memos); ?> to 
                            <?php echo min($current_page * $memos_per_page, $total_memos); ?> of 
                            <?php echo $total_memos; ?> memos
                        </div>
                        <div>
                            <?php if ($current_page > 1): ?>
                                <a href="<?php echo buildPaginationUrl($current_page - 1); ?>" class="pagination-button">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                <a href="<?php echo buildPaginationUrl($i); ?>" 
                                   class="pagination-button <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?php echo buildPaginationUrl($current_page + 1); ?>" class="pagination-button">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.querySelector('.search-form');
        const filterInputs = filterForm.querySelectorAll('input, select');

        filterInputs.forEach(input => {
            input.addEventListener('change', () => filterForm.submit());
        });
    });

    function openMemoModal(memoId) {
        window.location.href = 'memo-details.php?id=' + memoId;
    }
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
