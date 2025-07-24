<?php
// Enable all error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once 'config/database.php';

try {
    // Get database connection using the function from database.php
    $conn = getDbConnection();
    
    // Store connection in a global variable for backward compatibility
    global $conn;
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information - create a simple fallback if getUserById doesn't exist
if (function_exists('getUserById')) {
    $user = getUserById($_SESSION['user_id']);
} else {
    // Simple fallback to get user data
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT * FROM users WHERE id = $user_id";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if (!$user) {
        header("Location: login.php");
        exit();
    }
}

// Function to check if current user can edit a lead
function canEditLead($lead, $current_user_id) {
    global $user; // Access the global user variable
    
    // Check if user is a superuser
    if (isSuperUser($user['username'])) {
        return true;
    }
    
    // User can edit if they are the assigned agent
    return ($lead['user_id'] == $current_user_id);
}

// Enhanced role-based filtering with superuser support
if (!function_exists('isSuperUser')) {
    function isSuperUser($username) {
        $superusers = [
            'markpatigayon.intern',
            'gabriellibacao.founder', 
            'romeocorberta.itdept'
        ];
        return in_array($username, $superusers);
    }
}

// Pagination settings
$leads_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $leads_per_page;

// ENHANCED: Status filtering with validation
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$valid_filters = ['all', 'closed', 'lost', 'in_progress'];

if (!in_array($status_filter, $valid_filters)) {
    $status_filter = 'all';
}

// ENHANCED: Define conversion statuses with proper categorization
$closed_statuses = ['Closed Deal'];
$lost_statuses = ['Lost'];
$in_progress_statuses = ['Requirement Stage', 'Downpayment Stage', 'Housing Loan Application', 
                        'Loan Approval', 'Loan Takeout', 'House Inspection', 'House Turn Over'];

// Build status condition based on filter
switch ($status_filter) {
    case 'closed':
        $conversion_statuses = $closed_statuses;
        break;
    case 'lost':
        $conversion_statuses = $lost_statuses;
        break;
    case 'in_progress':
        $conversion_statuses = $in_progress_statuses;
        break;
    default: // 'all'
        $conversion_statuses = array_merge($closed_statuses, $lost_statuses, $in_progress_statuses);
        break;
}

$status_placeholders = str_repeat('?,', count($conversion_statuses) - 1) . '?';
$whereClause = "WHERE l.status IN ($status_placeholders)";

// Check if search is active
$search_active = false;
$search_term = '';
$search_param = '';
$search_condition = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $_GET['search'];
    $search_param = "%$search_term%";
    $search_condition = " AND (
        l.client_name LIKE ? OR 
        l.email LIKE ? OR 
        l.phone LIKE ? OR
        l.developer LIKE ? OR
        l.project_model LIKE ?
    )";
    $search_active = true;
}

// Role-based access control
if (isSuperUser($user['username'])) {
    // Superusers can see all conversion leads - no additional WHERE clause needed
} elseif ($user['role'] === 'agent') {
    $whereClause .= " AND l.user_id = " . $user['id'];
} elseif ($user['role'] === 'supervisor' || $user['role'] === 'manager') {
    // Get team members for supervisors/managers
    $team_query = "SELECT team_id FROM users WHERE id = " . $user['id'];
    $team_stmt = $conn->prepare($team_query);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    $team_data = $team_result->fetch_assoc();
    if ($team_data) {
        $whereClause .= " AND u.team_id = " . $team_data['team_id'];
    }
}

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM leads l
    LEFT JOIN users u ON l.user_id = u.id
    $whereClause
    $search_condition
";

$count_stmt = $conn->prepare($count_query);

// Bind parameters for status filtering
$param_types = str_repeat('s', count($conversion_statuses));
$params = $conversion_statuses;

if ($search_active) {
    $param_types .= 'sssss';
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_leads = $count_result->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = ceil($total_leads / $leads_per_page);

// Adjust current page if it exceeds total pages
if ($total_pages > 0) {
    $current_page = min($current_page, $total_pages);
}

// ENHANCED: Query to get conversion leads with comprehensive tracking
$query = "
    SELECT 
        l.id,
        l.client_name,
        l.phone,
        l.email,
        l.developer,
        l.project_model,
        l.price,
        l.expected_commission,
        l.status,
        l.created_at,
        l.updated_at,
        l.user_id,
        u.name as agent_name,
        t.name as team_name,
        dt.reservation_date,
        dt.requirements_complete,
        dt.spot_dp,
        dt.spot_dp_amount,
        dt.dp_terms,
        dt.monthly_dp_amount,
        dt.current_dp_stage,
        dt.total_dp_stages,
        dt.total_dp_paid,
        dt.remaining_dp_balance,
        dt.pagibig_bank_approval,
        dt.loan_amount,
        dt.loan_takeout,
        dt.loan_takeout_date,
        dt.turnover,
        dt.turnover_date,
        dt.progress_rate,
        dt.next_payment_date,
        CASE 
            WHEN l.status = 'House Turn Over' THEN 100
            WHEN l.status = 'House Inspection' THEN 90
            WHEN l.status = 'Loan Takeout' THEN 80
            WHEN l.status = 'Loan Approval' THEN 70
            WHEN l.status = 'Housing Loan Application' THEN 60
            WHEN l.status = 'Downpayment Stage' THEN 40
            WHEN l.status = 'Requirement Stage' THEN 20
            WHEN l.status = 'Closed Deal' THEN 10
            ELSE 0
        END as conversion_progress
    FROM leads l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN teams t ON u.team_id = t.id
    LEFT JOIN downpayment_tracker dt ON l.id = dt.lead_id
    $whereClause
    $search_condition
    ORDER BY 
        CASE 
            WHEN l.status = 'Lost' THEN 1 
            ELSE 0 
        END,
        l.updated_at DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);

// Prepare parameters for main query
$main_param_types = str_repeat('s', count($conversion_statuses));
$main_params = $conversion_statuses;

if ($search_active) {
    $main_param_types .= 'sssss';
    $main_params = array_merge($main_params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

$main_param_types .= 'ii';
$main_params = array_merge($main_params, [$offset, $leads_per_page]);

$stmt->bind_param($main_param_types, ...$main_params);
$stmt->execute();
$result = $stmt->get_result();
$leads = [];
while ($row = $result->fetch_assoc()) {
    $leads[] = $row;
}
$stmt->close();

// ENHANCED: Get comprehensive statistics for all conversion statuses
$all_conversion_statuses = array_merge($closed_statuses, $lost_statuses, $in_progress_statuses);
$all_status_placeholders = str_repeat('?,', count($all_conversion_statuses) - 1) . '?';

$stats_query = "
SELECT 
    COUNT(*) as total_deals,
    COUNT(CASE WHEN l.status IN ('" . implode("','", $closed_statuses) . "') THEN 1 END) as total_closed,
    COUNT(CASE WHEN l.status = 'Lost' THEN 1 END) as total_lost,
    COUNT(CASE WHEN l.status IN ('" . implode("','", $in_progress_statuses) . "') THEN 1 END) as in_progress,
    COALESCE(SUM(CASE WHEN l.status NOT IN ('Lost') THEN l.price ELSE 0 END), 0) as total_sales,
    COALESCE(SUM(CASE WHEN l.status NOT IN ('Lost') THEN l.expected_commission ELSE 0 END), 0) as total_commission,
    COALESCE(SUM(CASE WHEN l.status = 'Lost' THEN l.price ELSE 0 END), 0) as lost_sales,
    COUNT(CASE WHEN l.status = 'House Turn Over' THEN 1 END) as turned_over,
    COUNT(CASE WHEN dt.loan_takeout = 1 AND l.status IN ('Loan Takeout', 'House Inspection', 'House Turn Over') THEN 1 END) as loan_takeouts,
    COUNT(CASE WHEN dt.pagibig_bank_approval = 1 AND l.status IN ('Loan Approval', 'Loan Takeout', 'House Inspection', 'House Turn Over') THEN 1 END) as bank_approved,
    AVG(CASE WHEN l.status NOT IN ('Lost') THEN 
        CASE 
            WHEN l.status = 'House Turn Over' THEN 100
            WHEN l.status = 'House Inspection' THEN 90
            WHEN l.status = 'Loan Takeout' THEN 80
            WHEN l.status = 'Loan Approval' THEN 70
            WHEN l.status = 'Housing Loan Application' THEN 60
            WHEN l.status = 'Downpayment Stage' THEN 40
            WHEN l.status = 'Requirement Stage' THEN 20
            WHEN l.status = 'Closed Deal' THEN 10
            ELSE 0
        END 
    END) as avg_conversion_progress
FROM leads l
LEFT JOIN users u ON l.user_id = u.id
LEFT JOIN downpayment_tracker dt ON l.id = dt.lead_id
WHERE l.status IN ($all_status_placeholders)
";

// Apply role-based filtering to stats query
if (isSuperUser($user['username'])) {
    // No additional filtering for superusers
} elseif ($user['role'] === 'agent') {
    $stats_query .= " AND l.user_id = " . $user['id'];
} elseif ($user['role'] === 'supervisor' || $user['role'] === 'manager') {
    if (isset($team_data) && $team_data) {
        $stats_query .= " AND u.team_id = " . $team_data['team_id'];
    }
}

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param(str_repeat('s', count($all_conversion_statuses)), ...$all_conversion_statuses);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Function to build pagination URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Calculate which page numbers to show
function getPaginationRange($current_page, $total_pages, $range = 2) {
    $result = [];
    $start = max(1, $current_page - $range);
    $end = min($total_pages, $current_page + $range);

    // Always include first page
    if ($start > 1) {
        $result[] = 1;
        if ($start > 2) {
            $result[] = '...';
        }
    }

    // Add range around current page
    for ($i = $start; $i <= $end; $i++) {
        $result[] = $i;
    }

    // Always include last page
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $result[] = '...';
        }
        $result[] = $total_pages;
    }

    return $result;
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'House Turn Over':
        case 'Loan Takeout':
            return 'success';
        case 'Lost':
            return 'danger';
        case 'Closed Deal':
        case 'Loan Approval':
        case 'House Inspection':
            return 'info';
        case 'Requirement Stage':
        case 'Downpayment Stage':
        case 'Housing Loan Application':
            return 'warning';
        default:
            return 'info';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Conversion - Real Estate Lead Management System</title>
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

        .leads-page {
            flex: 1;
            padding: 1.5rem;
            width: 100%;
            margin: 0;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
            position: relative;
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
            color: var(--success);
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .btn-export, .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        .btn-export {
            background: var(--success);
            color: white;
        }

        .btn-export:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-back {
            background: var(--primary);
            color: white;
        }

        .btn-back:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .filters-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .filter-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background-color: var(--gray-50);
            transition: all 0.2s ease;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background-color: white;
        }

        .search-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .search-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: var(--gray-50);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background-color: white;
        }

        .search-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .search-button:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .search-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .search-reset:hover {
            background: var(--gray-200);
        }

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

        .leads-table-container {
            flex: 1;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: auto;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .leads-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .leads-table thead {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--gray-50);
        }

        .leads-table th {
            background: var(--gray-50);
            padding: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
            text-align: left;
            white-space: nowrap;
        }

        .leads-table td {
            padding: 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
            background: white;
            vertical-align: top;
        }

        .leads-table tr:last-child td {
            border-bottom: none;
        }

        .leads-table tbody tr {
            transition: all 0.2s ease;
        }

        .leads-table tbody tr:hover {
            background: var(--gray-50);
        }

        /* Lost deal highlighting styles */
        .leads-table tbody tr.lost-deal {
            background-color: #fef2f2 !important;
            border-left: 4px solid var(--danger);
        }

        .leads-table tbody tr.lost-deal:hover {
            background-color: #fecaca !important;
        }

        .leads-table tbody tr.lost-deal td {
            background-color: transparent;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.success {
            background: var(--success-light);
            color: var(--success);
        }

        .status-badge.warning {
            background: var(--warning-light);
            color: var(--warning);
        }

        .status-badge.info {
            background: var(--info-light);
            color: var(--info);
        }

        .status-badge.danger {
            background: var(--danger-light);
            color: var(--danger);
        }

        .progress-bar {
            width: 100%;
            height: 0.5rem;
            background: var(--gray-200);
            border-radius: 9999px;
            overflow: hidden;
            margin: 0.25rem 0;
        }

        .progress-fill {
            height: 100%;
            background: var(--success);
            transition: width 0.3s ease;
        }

        .conversion-progress {
            width: 100%;
            height: 0.375rem;
            background: var(--gray-200);
            border-radius: 9999px;
            overflow: hidden;
            margin: 0.25rem 0;
        }

        .conversion-progress .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--warning), var(--success));
            transition: width 0.3s ease;
        }

        .amount-text {
            font-weight: 600;
            color: var(--success);
        }

        .lost-deal .amount-text {
            color: var(--danger);
            text-decoration: line-through;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-view,
        .btn-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .btn-view {
            background: var(--info-light);
            color: var(--info);
        }

        .btn-edit {
            background: var(--warning-light);
            color: var(--warning);
        }

        .btn-edit.disabled {
            background-color: #d1d5db !important;
            color: #6b7280 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            opacity: 1 !important;
            border: 1px solid #9ca3af !important;
        }

        .btn-view:hover,
        .btn-edit:hover:not(.disabled) {
            transform: translateY(-1px);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
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

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-400);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .empty-state p {
            font-size: 1rem;
            margin: 0;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .leads-page {
                padding: 1rem;
                min-height: calc(100vh - 60px);
            }

            .leads-table-container {
                margin: -1rem;
                margin-top: 1rem;
                border-radius: 0;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn-export, .btn-back {
                width: 100%;
                justify-content: center;
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .filter-select {
                min-width: auto;
                width: 100%;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }

            .search-button, .search-reset {
                width: 100%;
                justify-content: center;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .leads-table {
                font-size: 0.75rem;
            }

            .leads-table th,
            .leads-table td {
                padding: 0.5rem;
            }
        }

        @media print {
            .filters-container,
            .search-container,
            .header-actions,
            .pagination {
                display: none !important;
            }
            
            .page-header h2 {
                font-size: 1.25rem;
            }
            
            .leads-table {
                font-size: 0.75rem;
            }
            
            .leads-table th,
            .leads-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="leads-page">
                <div class="page-header">
                    <h2><i class="fas fa-handshake"></i> Lead Conversion Tracking</h2>
                    <div class="header-actions">
                        <a href="leads.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i>
                            Back to Active Leads
                        </a>
                        <button class="btn-export" onclick="window.print()">
                            <i class="fas fa-print"></i>
                            Print Report
                        </button>
                    </div>
                </div>
                
                <!-- Enhanced Filters Container -->
                <div class="filters-container">
                    <form method="GET" action="" class="filters-row">
                        <div class="filter-group">
                            <label class="filter-label">Status Filter</label>
                            <select name="status_filter" class="filter-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Conversion Leads</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed Deals Only</option>
                                <option value="lost" <?php echo $status_filter === 'lost' ? 'selected' : ''; ?>>Lost Deals Only</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            </select>
                        </div>
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Search Container -->
                <div class="search-container">
                    <form class="search-form" method="GET" action="">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Search by client name, phone, email, developer, or project" 
                            value="<?php echo htmlspecialchars($search_term); ?>"
                        >
                        <?php if ($status_filter !== 'all'): ?>
                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <?php endif; ?>
                        <button type="submit" class="search-button">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                        <?php if ($search_active || $status_filter !== 'all'): ?>
                        <a href="lead-conversion.php" class="search-reset" title="Clear all filters">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Enhanced Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Closed Deals</h3>
                            <p><?php echo number_format($stats['total_closed']); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="summary-info">
                            <h3>In Progress</h3>
                            <p><?php echo number_format($stats['in_progress']); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--danger-light); color: var(--danger);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Lost Deals</h3>
                            <p><?php echo number_format($stats['total_lost']); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--primary-light); color: var(--primary);">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Sales</h3>
                            <p>₱<?php echo number_format($stats['total_sales'] ?? 0, 0); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--info-light); color: var(--info);">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Turned Over</h3>
                            <p><?php echo number_format($stats['turned_over']); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Commission</h3>
                            <p>₱<?php echo number_format($stats['total_commission'] ?? 0, 0); ?></p>
                        </div>
                    </div>
                </div>

                <div class="leads-table-container">
                    <?php if (count($leads) > 0): ?>
                    <table class="leads-table">
                        <thead>
                            <tr>
                                <th>STATUS</th>
                                <th>CLIENT</th>
                                <th>PROPERTY</th>
                                <th>PRICE</th>
                                <th>AGENT</th>
                                <th>CONVERSION PROGRESS</th>
                                <th>DP PROGRESS</th>
                                <th>LOAN STATUS</th>
                                <th>DATE</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leads as $lead): ?>
                                <tr class="<?php echo ($lead['status'] == 'Lost') ? 'lost-deal' : ''; ?>">
                                    <td>
                                        <span class="status-badge <?php echo getStatusBadgeClass($lead['status']); ?>">
                                            <?php echo htmlspecialchars($lead['status']); ?>
                                        </span>
                                        <?php if ($lead['status'] == 'Lost'): ?>
                                            <div style="font-size: 0.75rem; color: var(--danger); margin-top: 0.25rem;">
                                                Lost Deal
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($lead['client_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);"><?php echo htmlspecialchars($lead['phone']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($lead['developer']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);"><?php echo htmlspecialchars($lead['project_model']); ?></div>
                                    </td>
                                    <td>
                                        <span class="amount-text">₱<?php echo number_format($lead['price'], 0); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($lead['agent_name'] ?? 'N/A'); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);"><?php echo htmlspecialchars($lead['team_name'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($lead['status'] != 'Lost'): ?>
                                            <div class="conversion-progress">
                                                <div class="progress-fill" style="width: <?php echo $lead['conversion_progress']; ?>%"></div>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                <?php echo $lead['conversion_progress']; ?>% Complete
                                            </div>
                                        <?php else: ?>
                                            <span class="status-badge danger">Lost</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($lead['status'] != 'Lost' && in_array($lead['status'], ['Downpayment Stage', 'Housing Loan Application', 'Loan Approval'])): ?>
                                            <?php if ($lead['total_dp_paid'] && $lead['price']): ?>
                                                <?php $dp_percentage = ($lead['total_dp_paid'] / ($lead['price'] * 0.2)) * 100; ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo min(100, $dp_percentage); ?>%"></div>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                    <?php echo number_format($dp_percentage, 1); ?>% Complete
                                                </div>
                                            <?php else: ?>
                                                <span class="status-badge info">No DP Data</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-badge info">
                                                <?php 
                                                if ($lead['status'] == 'Lost') {
                                                    echo '—';
                                                } elseif (in_array($lead['status'], ['Closed Deal', 'Requirement Stage'])) {
                                                    echo 'N/A';
                                                } else {
                                                    echo 'Complete';
                                                }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($lead['status'] != 'Lost'): ?>
                                            <?php if ($lead['loan_takeout']): ?>
                                                <span class="status-badge success">Approved</span>
                                            <?php elseif ($lead['pagibig_bank_approval']): ?>
                                                <span class="status-badge warning">Processing</span>
                                            <?php elseif (in_array($lead['status'], ['Housing Loan Application', 'Loan Approval'])): ?>
                                                <span class="status-badge info">Applied</span>
                                            <?php else: ?>
                                                <span class="status-badge info">Cash</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-badge danger">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
$display_date = $lead['updated_at'];
if ($lead['status'] == 'House Turn Over' && $lead['turnover_date']) {
    $display_date = $lead['turnover_date'];
}
echo date('M j, Y', strtotime($display_date)); 
?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="lead-details.php?id=<?php echo $lead['id']; ?>" class="btn-view" title="View Lead">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (canEditLead($lead, $_SESSION['user_id'])): ?>
                                                <a href="edit-lead.php?id=<?php echo $lead['id']; ?>" class="btn-edit" title="Edit Lead">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="#" class="btn-edit disabled" title="You are not authorized to edit this lead" style="pointer-events: none; opacity: 0.5; border: 1px solid #9ca3af; color: #6b7280; background-color: #d1d5db;">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>
                            <?php 
                            if ($search_active) {
                                echo "No leads found matching your search criteria.";
                            } elseif ($status_filter !== 'all') {
                                echo "No leads found for the selected status filter.";
                            } else {
                                echo "No conversion leads found.";
                            }
                            ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $paginationRange = getPaginationRange($current_page, $total_pages);
                    ?>
                    <div class="pagination-info">
                        Showing <?php echo (($current_page - 1) * $leads_per_page) + 1; ?> to <?php echo min($current_page * $leads_per_page, $total_leads); ?> of <?php echo $total_leads; ?> leads
                    </div>
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo buildPaginationUrl($current_page - 1); ?>" class="pagination-button">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-button disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>

                    <?php foreach ($paginationRange as $page): ?>
                        <?php if ($page === '...'): ?>
                            <span class="pagination-button disabled">...</span>
                        <?php else: ?>
                            <a href="<?php echo buildPaginationUrl($page); ?>" class="pagination-button <?php echo ($page == $current_page) ? 'active' : ''; ?>"><?php echo $page; ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo buildPaginationUrl($current_page + 1); ?>" class="pagination-button">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-button disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
