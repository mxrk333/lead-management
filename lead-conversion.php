<?php
session_start();

// Include database configuration and establish connection
require_once 'config/database.php';

// Check if connection was established
if (!isset($conn) || !$conn) {
    // Fallback database connection if config/database.php doesn't provide $conn
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "real_estate_leads";
    
    $conn = mysqli_connect($host, $username, $password, $database);
    
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }
}

// Include functions if available
if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
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
    $user_result = mysqli_query($conn, $user_query);
    $user = mysqli_fetch_assoc($user_result);
    
    if (!$user) {
        header("Location: login.php");
        exit();
    }
}

// Use the existing database connection from database.php
// No need to create a new connection here

// Pagination settings
$leads_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $leads_per_page;

// Build query based on user role and permissions
$whereClause = "WHERE l.status = 'Closed Deal'";

// Check if search is active
$search_active = false;
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search']);
    $whereClause .= " AND (
        l.client_name LIKE '%$search_term%' OR 
        l.email LIKE '%$search_term%' OR 
        l.phone LIKE '%$search_term%' OR
        l.developer LIKE '%$search_term%' OR
        l.project_model LIKE '%$search_term%'
    )";
    $search_active = true;
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

if (isSuperUser($user['username'])) {
    // Superusers can see all closed deals - no additional WHERE clause needed
} elseif ($user['role'] === 'agent') {
    $whereClause .= " AND l.user_id = $user_id";
} elseif ($user['role'] === 'supervisor' || $user['role'] === 'manager') {
    // Get team members for supervisors/managers
    $team_query = "SELECT team_id FROM users WHERE id = $user_id";
    $team_result = mysqli_query($conn, $team_query);
    $team_data = mysqli_fetch_assoc($team_result);
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
";
$count_result = mysqli_query($conn, $count_query);
$total_leads = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_leads / $leads_per_page);

// Adjust current page if it exceeds total pages
if ($total_pages > 0) {
    $current_page = min($current_page, $total_pages);
}

// Query to get closed deals with downpayment information
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
        l.created_at,
        l.updated_at,
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
        dt.next_payment_date
    FROM leads l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN teams t ON u.team_id = t.id
    LEFT JOIN downpayment_tracker dt ON l.id = dt.lead_id
    $whereClause
    ORDER BY l.updated_at DESC
    LIMIT $offset, $leads_per_page
";

$result = mysqli_query($conn, $query);
$leads = [];
while ($row = mysqli_fetch_assoc($result)) {
    $leads[] = $row;
}

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_closed,
        COALESCE(SUM(l.price), 0) as total_sales,
        COALESCE(SUM(l.expected_commission), 0) as total_commission,
        COUNT(CASE WHEN dt.turnover = 1 THEN 1 END) as turned_over,
        COUNT(CASE WHEN dt.loan_takeout = 1 THEN 1 END) as loan_takeouts,
        COUNT(CASE WHEN dt.pagibig_bank_approval = 1 THEN 1 END) as bank_approved
    FROM leads l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN downpayment_tracker dt ON l.id = dt.lead_id
    $whereClause
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

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

        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--success);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        .btn-export:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-back {
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

        .btn-back:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
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

        .amount-text {
            font-weight: 600;
            color: var(--success);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-view {
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
            background: var(--info-light);
            color: var(--info);
            text-decoration: none;
        }

        .btn-view:hover {
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
                    <h2><i class="fas fa-handshake"></i> Lead Conversion - Closed Deals</h2>
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
                
                <!-- Search Container -->
                <div class="search-container">
                    <form class="search-form" method="GET" action="">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Search by client name, phone, email, developer..." 
                            value="<?php echo htmlspecialchars($search_term); ?>"
                        >
                        <button type="submit" class="search-button">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                        <?php if ($search_active): ?>
                        <a href="lead-conversion.php" class="search-reset" title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Closed Deals</h3>
                            <p><?php echo number_format($stats['total_closed']); ?></p>
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
                        <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Commission</h3>
                            <p>₱<?php echo number_format($stats['total_commission'] ?? 0, 0); ?></p>
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
                </div>
                
                <div class="leads-table-container">
                    <table class="leads-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Property</th>
                                <th>Price</th>
                                <th>Agent</th>
                                <th>DP Progress</th>
                                <th>Loan Status</th>
                                <th>Closed Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-handshake"></i>
                                        <p>No closed deals found<?php echo $search_active ? ' matching your search' : ''; ?></p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($leads as $lead): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($lead['client_name']); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                <?php echo htmlspecialchars($lead['phone']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($lead['developer']); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                <?php echo htmlspecialchars($lead['project_model']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="amount-text">₱<?php echo number_format($lead['price'], 0); ?></div>
                                        <?php if ($lead['expected_commission'] > 0): ?>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                Comm: ₱<?php echo number_format($lead['expected_commission'], 0); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($lead['agent_name']); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                <?php echo htmlspecialchars($lead['team_name'] ?? 'No Team'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($lead['reservation_date']): ?>
                                            <div style="margin-bottom: 0.25rem;">
                                                <span style="font-size: 0.75rem; color: var(--gray-500);">
                                                    <?php echo round($lead['progress_rate'] ?? 0); ?>% Complete
                                                </span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo round($lead['progress_rate'] ?? 0); ?>%;"></div>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                                                Stage <?php echo $lead['current_dp_stage'] ?? 0; ?>/<?php echo $lead['total_dp_stages'] ?? 0; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="status-badge info">
                                                <i class="fas fa-info-circle"></i>
                                                No DP Data
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($lead['loan_amount'] > 0): ?>
                                            <div style="margin-bottom: 0.25rem;">
                                                <?php if ($lead['pagibig_bank_approval']): ?>
                                                    <span class="status-badge success">
                                                        <i class="fas fa-check"></i>
                                                        Approved
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge warning">
                                                        <i class="fas fa-clock"></i>
                                                        Pending
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($lead['loan_takeout']): ?>
                                                <div style="font-size: 0.75rem; color: var(--success);">
                                                    <i class="fas fa-check-circle"></i>
                                                    Takeout Complete
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-badge info">
                                                <i class="fas fa-minus"></i>
                                                Cash
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($lead['updated_at'])); ?></div>
                                        <?php if ($lead['turnover']): ?>
                                            <div style="font-size: 0.75rem; color: var(--success);">
                                                <i class="fas fa-home"></i>
                                                Turned Over
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="lead-details.php?id=<?php echo $lead['id']; ?>" class="btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination-info">
                    Showing <?php echo min(($current_page - 1) * $leads_per_page + 1, $total_leads); ?> to 
                    <?php echo min($current_page * $leads_per_page, $total_leads); ?> of 
                    <?php echo $total_leads; ?> closed deals
                </div>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo buildPaginationUrl($current_page - 1); ?>" class="pagination-button">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php 
                    $pagination_range = getPaginationRange($current_page, $total_pages);
                    foreach ($pagination_range as $page): 
                        if ($page === '...'): 
                    ?>
                        <span class="pagination-button disabled">...</span>
                    <?php else: ?>
                        <a href="<?php echo buildPaginationUrl($page); ?>" 
                           class="pagination-button <?php echo ($current_page == $page) ? 'active' : ''; ?>">
                            <?php echo $page; ?>
                        </a>
                    <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo buildPaginationUrl($current_page + 1); ?>" class="pagination-button">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>
