<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Add this function right after the includes and before the canEditLead function
function isSuperUser($username) {
    $superusers = [
        'markpatigayon.intern',
        'gabriellibacao.founder', 
        'romeocorberta.itdept'
    ];
    return in_array($username, $superusers);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

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

// Helper functions for active leads (excluding closed deals)
function getActiveLeads($user_id, $role, $username = null) {
    $conn = getDbConnection();
    
    $whereClause = "WHERE l.status != 'Closed Deal'";
    
    // Superusers can see all leads
    if ($username && isSuperUser($username)) {
        // No additional WHERE clause needed - show all active leads
    } elseif ($role === 'agent') {
        $whereClause .= " AND l.user_id = $user_id";
    } elseif ($role === 'supervisor' || $role === 'manager') {
        $team_query = "SELECT team_id FROM users WHERE id = $user_id";
        $team_result = mysqli_query($conn, $team_query);
        $team_data = mysqli_fetch_assoc($team_result);
        if ($team_data && $team_data['team_id']) {
            $whereClause .= " AND u.team_id = " . $team_data['team_id'];
        }
    }
    
    $query = "
        SELECT l.*, u.name as agent_name, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
    ";
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getPaginatedActiveLeads($user_id, $role, $offset, $limit) {
    $conn = getDbConnection();
    
    $whereClause = "WHERE l.status != 'Closed Deal'";
    
    if ($role === 'agent') {
        $whereClause .= " AND l.user_id = $user_id";
    } elseif ($role === 'supervisor' || $role === 'manager') {
        $team_query = "SELECT team_id FROM users WHERE id = $user_id";
        $team_result = mysqli_query($conn, $team_query);
        $team_data = mysqli_fetch_assoc($team_result);
        if ($team_data && $team_data['team_id']) {
            $whereClause .= " AND u.team_id = " . $team_data['team_id'];
        }
    }
    
    $query = "
        SELECT l.*, u.name as agent_name, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
        LIMIT $offset, $limit
    ";
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function searchActiveLeads($search, $user_id, $role, $username = null) {
    $conn = getDbConnection();
    $search = mysqli_real_escape_string($conn, $search);
    
    if ($username && isSuperUser($username)) {
        // Superusers see all results, no additional filtering by user/team
        $whereClause = "WHERE l.status != 'Closed Deal' AND (
            l.client_name LIKE '%$search%' OR 
            l.email LIKE '%$search%' OR 
            l.phone LIKE '%$search%'
        )";
    } else {
        $whereClause = "WHERE l.status != 'Closed Deal' AND (
            l.client_name LIKE '%$search%' OR 
            l.email LIKE '%$search%' OR 
            l.phone LIKE '%$search%'
        )";
        
        if ($role === 'agent') {
            $whereClause .= " AND l.user_id = $user_id";
        } elseif ($role === 'supervisor' || $role === 'manager') {
            $team_query = "SELECT team_id FROM users WHERE id = $user_id";
            $team_result = mysqli_query($conn, $team_query);
            $team_data = mysqli_fetch_assoc($team_result);
            if ($team_data && $team_data['team_id']) {
                $whereClause .= " AND u.team_id = " . $team_data['team_id'];
            }
        }
    }
    
    $query = "
        SELECT l.*, u.name as agent_name, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
    ";
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function filterActiveLeadsByStatus($status, $user_id, $role, $username = null) {
    $conn = getDbConnection();
    $status = mysqli_real_escape_string($conn, $status);
    
    if ($username && isSuperUser($username)) {
        // Superusers see all results, no additional filtering by user/team
        $whereClause = "WHERE l.status != 'Closed Deal' AND l.status = '$status'";
    } else {
        $whereClause = "WHERE l.status != 'Closed Deal' AND l.status = '$status'";
        
        if ($role === 'agent') {
            $whereClause .= " AND l.user_id = $user_id";
        } elseif ($role === 'supervisor' || $role === 'manager') {
            $team_query = "SELECT team_id FROM users WHERE id = $user_id";
            $team_result = mysqli_query($conn, $team_query);
            $team_data = mysqli_fetch_assoc($team_result);
            if ($team_data && $team_data['team_id']) {
                $whereClause .= " AND u.team_id = " . $team_data['team_id'];
            }
        }
    }
    
    $query = "
        SELECT l.*, u.name as agent_name, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
    ";
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function filterActiveLeadsByTemperature($temperature, $user_id, $role, $username = null) {
    $conn = getDbConnection();
    $temperature = mysqli_real_escape_string($conn, $temperature);
    
    if ($username && isSuperUser($username)) {
        // Superusers see all results, no additional filtering by user/team
        $whereClause = "WHERE l.status != 'Closed Deal' AND l.temperature = '$temperature'";
    } else {
        $whereClause = "WHERE l.status != 'Closed Deal' AND l.temperature = '$temperature'";
        
        if ($role === 'agent') {
            $whereClause .= " AND l.user_id = $user_id";
        } elseif ($role === 'supervisor' || $role === 'manager') {
            $team_query = "SELECT team_id FROM users WHERE id = $user_id";
            $team_result = mysqli_query($conn, $team_query);
            $team_data = mysqli_fetch_assoc($team_result);
            if ($team_data && $team_data['team_id']) {
                $whereClause .= " AND u.team_id = " . $team_data['team_id'];
            }
        }
    }
    
    $query = "
        SELECT l.*, u.name as agent_name, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
    ";
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function filterActiveLeadsBySource($source, $user_id, $role, $username = null) {
    $conn = getDbConnection();
    $source = mysqli_real_escape_string($conn, $source);
    
    if ($username && isSuperUser($username)) {
        // Superusers see all results, no additional filtering by user/team
        $whereClause = "WHERE l.status != 'Closed Deal' AND l.source = '$source'";
    } else {
        $whereClause = "WHERE l.status != 'Closed Deal' AND l.source = '$source'";
        
        if ($role === 'agent') {
            $whereClause .= " AND l.user_id = $user_id";
        } elseif ($role === 'supervisor' || $role === 'manager') {
            $team_query = "SELECT team_id FROM users WHERE id = $user_id";
            $team_result = mysqli_query($conn, $team_query);
            $team_data = mysqli_fetch_assoc($team_result);
            if ($team_data && $team_data['team_id']) {
                $whereClause .= " AND u.team_id = " . $team_data['team_id'];
            }
        }
    }
    
    $query = "
        SELECT l.*, u.name as agent_name, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
    ";
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to filter leads by ownership (My Leads)
function filterMyLeads($user_id) {
    $conn = getDbConnection();
    
    $query = "
        SELECT l.*, u.name as agent_name, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        WHERE l.status != 'Closed Deal' AND l.user_id = $user_id
        ORDER BY l.created_at DESC
    ";
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getUniqueActiveStatuses() {
    $conn = getDbConnection();
    $query = "SELECT DISTINCT status FROM leads WHERE status != 'Closed Deal' ORDER BY status";
    $result = mysqli_query($conn, $query);
    $statuses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $statuses[] = $row['status'];
    }
    return $statuses;
}

function getClosedDealsCount($user_id, $role) {
    $conn = getDbConnection();
    
    $whereClause = "WHERE l.status = 'Closed Deal'";
    
    if ($role === 'agent') {
        $whereClause .= " AND l.user_id = $user_id";
    } elseif ($role === 'supervisor' || $role === 'manager') {
        $team_query = "SELECT team_id FROM users WHERE id = $user_id";
        $team_result = mysqli_query($conn, $team_query);
        $team_data = mysqli_fetch_assoc($team_result);
        if ($team_data && $team_data['team_id']) {
            $whereClause .= " AND u.team_id = " . $team_data['team_id'];
        }
    }
    
    $query = "SELECT COUNT(*) as count FROM leads l LEFT JOIN users u ON l.user_id = u.id $whereClause";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    return $data['count'];
}

// Pagination settings
$leads_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $leads_per_page;

// Get all active leads based on user role (for counting total)
$all_leads = getActiveLeads($user_id, $user['role'], $user['username']);

// Initialize filter flags
$search_active = false;
$status_filter_active = false;
$temp_filter_active = false;
$source_filter_active = false;
$my_leads_filter_active = false;

// Apply filters if set
if (isset($_GET['my_leads']) && $_GET['my_leads'] == '1') {
    $my_leads_filter_active = true;
    $leads = filterMyLeads($user_id);
} elseif (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $search_active = true;
    $leads = searchActiveLeads($search, $user_id, $user['role'], $user['username']);
} elseif (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $_GET['status'];
    $status_filter_active = true;
    $leads = filterActiveLeadsByStatus($status, $user_id, $user['role'], $user['username']);
} elseif (isset($_GET['temperature']) && !empty($_GET['temperature'])) {
    $temperature = $_GET['temperature'];
    $temp_filter_active = true;
    $leads = filterActiveLeadsByTemperature($temperature, $user_id, $user['role'], $user['username']);
} elseif (isset($_GET['source']) && !empty($_GET['source'])) {
    $source = $_GET['source'];
    $source_filter_active = true;
    $leads = filterActiveLeadsBySource($source, $user_id, $user['role'], $user['username']);
} else {
    // No filters, get paginated leads
    $leads = getPaginatedActiveLeads($user_id, $user['role'], $offset, $leads_per_page);
}

// Count total leads after filtering
$total_leads = count($leads);
$total_pages = ceil($total_leads / $leads_per_page);

// Adjust current page if it exceeds total pages
if ($total_pages > 0) {
    $current_page = min($current_page, $total_pages);
}

// Apply pagination to filtered results
if ($search_active || $status_filter_active || $temp_filter_active || $source_filter_active || $my_leads_filter_active) {
    $leads = array_slice($leads, $offset, $leads_per_page);
}

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
    
    if ($start > 1) {
        $result[] = 1;
        if ($start > 2) {
            $result[] = '...';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $result[] = $i;
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $result[] = '...';
        }
        $result[] = $total_pages;
    }
    
    return $result;
}

// Get temperature counts (ACTIVE ONLY)
$hotLeads = array_filter($all_leads, function($lead) {
    return $lead['temperature'] === 'Hot';
});
$hotLeadsCount = count($hotLeads);

$warmLeads = array_filter($all_leads, function($lead) {
    return $lead['temperature'] === 'Warm';
});
$warmLeadsCount = count($warmLeads);

$coldLeads = array_filter($all_leads, function($lead) {
    return $lead['temperature'] === 'Cold';
});
$coldLeadsCount = count($coldLeads);

// Get my leads count
$myLeads = array_filter($all_leads, function($lead) use ($user_id) {
    return $lead['user_id'] == $user_id;
});
$myLeadsCount = count($myLeads);

// Get closed deals count
$closedDealsCount = getClosedDealsCount($user_id, $user['role']);

// Get filter options from database (EXCLUDING CLOSED DEAL STATUS)
$sources = getUniqueSources();
$temperatures = getUniqueTemperatures();
$statuses = getUniqueActiveStatuses();

// Check if current user is superuser
$isSuperUser = isSuperUser($user['username']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Leads - InnerSPARC Lead Management System</title>
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
            color: var(--primary);
        }

        .superuser-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            background-color: #10b981;
            color: white;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .superuser-badge i {
            margin-right: 0.25rem;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
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

        .btn-conversion {
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
        }

        .btn-conversion:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
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

        .filters-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            width: 100%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(5, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 0;
            width: 100%;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: auto;
        }
        
        .search-form > * {
            justify-self: center;
            width: 100%;
            max-width: 250px;
        }
        
        .search-form input,
        .filter-select-container select {
            width: 100%;
            min-width: 180px;
            max-width: 250px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: var(--gray-50);
            height: 2.75rem;
            text-align: left;
            margin: 0 auto;
        }
        
        .search-form input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background-color: white;
        }
        
        .filter-options {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            width: 100%;
        }
        
        .filter-select-container {
            position: relative;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: left;
        }

        .filter-select-container::after {
            content: attr(data-label);
            position: absolute;
            top: -0.625rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 0 0.25rem;
            font-size: 0.75rem;
            background-color: white;
            color: var(--gray-500);
            pointer-events: none;
            white-space: nowrap;
        }

        .filter-select-container select {
            width: 100%;
            min-width: 180px;
            max-width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            appearance: none;
            background-color: var(--gray-50);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            transition: all 0.2s ease;
            cursor: pointer;
            color: var(--gray-700);
            height: 2.75rem;
            text-align: left;
            text-align-last: left;
        }

        .filter-select-container select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background-color: white;
        }

        .search-form input {
            text-align: left;
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

        .temperature {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .temperature.hot {
            background: var(--danger-light);
            color: var(--danger);
        }

        .temperature.warm {
            background: var(--warning-light);
            color: var(--warning);
        }

        .temperature.cold {
            background: var(--info-light);
            color: var(--info);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-view,
        .btn-edit,
        .btn-delete {
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

        /* Enhanced gray styling for disabled edit buttons */
        .btn-edit.disabled {
            background-color: #d1d5db !important;
            color: #6b7280 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            opacity: 1 !important;
            border: 1px solid #9ca3af !important;
        }

        .btn-delete {
            background: var(--danger-light);
            color: var(--danger);
        }

        .btn-view:hover,
        .btn-edit:hover:not(.disabled),
        .btn-delete:hover {
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

        /* Filter active indicator */
        .filter-active {
            background-color: var(--primary-light) !important;
            border-color: var(--primary) !important;
        }

        /* Search button */
        .btn-search {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            height: 2.75rem;
            width: 100%;
            max-width: 250px;
        }

        .btn-search:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .filter-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 1.5rem;
            width: 100%;
            grid-column: 1 / -1;
        }

        /* Clear filters button */
        .btn-clear-filters {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background-color: var(--gray-100);
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            color: var(--gray-700);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            height: 2.75rem;
            text-decoration: none;
        }

        .btn-clear-filters:hover {
            background-color: var(--gray-200);
            color: var(--gray-900);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 1200px) {
            .search-form {
                grid-template-columns: repeat(3, minmax(180px, 1fr));
                max-width: 900px;
                gap: 1.5rem;
            }
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

            .btn-add,
            .btn-conversion {
                width: 100%;
                justify-content: center;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .search-form {
                grid-template-columns: 1fr;
                max-width: 280px;
                gap: 1rem;
            }

            .search-form > * {
                max-width: 100%;
            }

            .search-form input,
            .filter-select-container select {
                max-width: 100%;
            }

            .filters-container {
                padding: 1rem;
                margin-left: 1rem;
                margin-right: 1rem;
                width: calc(100% - 2rem);
            }

            .filter-options {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
                gap: 0.75rem;
                margin-top: 1rem;
            }
            
            .btn-search,
            .btn-clear-filters {
                width: 100%;
                max-width: 280px;
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
                    <h2>
                        <i class="fas fa-users"></i> Active Leads Management
                        <?php if ($isSuperUser): ?>
                            <span class="superuser-badge">
                                <i class="fas fa-crown"></i> Super Admin
                            </span>
                        <?php endif; ?>
                    </h2>
                    <div class="header-actions">
                        <a href="lead-conversion.php" class="btn-conversion">
                            <i class="fas fa-handshake"></i>
                            View Closed Deals (<?php echo $closedDealsCount; ?>)
                        </a>
                        <a href="add-lead.php" class="btn-add">
                            <i class="fas fa-plus"></i>
                            Add New Lead
                        </a>
                    </div>
                </div>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--primary-light); color: var(--primary);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Active Leads</h3>
                            <p><?php echo count($all_leads); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="summary-info">
                            <h3>My Leads</h3>
                            <p><?php echo $myLeadsCount; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--danger-light); color: var(--danger);">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Hot Leads</h3>
                            <p><?php echo $hotLeadsCount; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-thermometer-half"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Warm Leads</h3>
                            <p><?php echo $warmLeadsCount; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--info-light); color: var(--info);">
                            <i class="fas fa-icicles"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Cold Leads</h3>
                            <p><?php echo $coldLeadsCount; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="filters-container">
                    <form class="search-form" method="GET" action="">
                        <div class="filter-select-container" data-label="Search">
                            <input type="text" name="search" placeholder="Search by name, email, phone..." 
                                   value="<?php echo htmlspecialchars($search ?? ''); ?>"
                                   class="<?php echo $search_active ? 'filter-active' : ''; ?>">
                        </div>
                        <div class="filter-select-container" data-label="My Leads">
                            <select name="my_leads" id="my_leads" class="<?php echo $my_leads_filter_active ? 'filter-active' : ''; ?>">
                                <option value="">All Leads</option>
                                <option value="1" <?php echo $my_leads_filter_active ? 'selected' : ''; ?>>My Leads Only</option>
                            </select>
                        </div>
                        <div class="filter-select-container" data-label="Temperature">
                            <select name="temperature" id="temperature" class="<?php echo $temp_filter_active ? 'filter-active' : ''; ?>">
                                <option value="">All Temperatures</option>
                                <?php foreach ($temperatures as $temp): ?>
                                    <option value="<?php echo htmlspecialchars($temp); ?>" 
                                        <?php echo (isset($temperature) && $temperature === $temp) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($temp); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-select-container" data-label="Status">
                            <select name="status" id="status" class="<?php echo $status_filter_active ? 'filter-active' : ''; ?>">
                                <option value="">All Status</option>
                                <?php foreach ($statuses as $stat): ?>
                                    <option value="<?php echo htmlspecialchars($stat); ?>" 
                                        <?php echo (isset($status) && $status === $stat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-select-container" data-label="Source">
                            <select name="source" id="source" class="<?php echo $source_filter_active ? 'filter-active' : ''; ?>">
                                <option value="">All Sources</option>
                                <?php foreach ($sources as $src): ?>
                                    <option value="<?php echo htmlspecialchars($src); ?>" 
                                        <?php echo (isset($source) && $source === $src) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($src); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn-search">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="leads.php" class="btn-clear-filters">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="leads-table-container">
                    <table class="leads-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Temperature</th>
                                <th>Status</th>
                                <th>Source</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem;">
                                    <div style="color: var(--gray-400);">
                                        <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                        <p>No active leads found</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($leads as $lead): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lead['client_name']); ?></td>
                                    <td>
                                        <?php 
                                        // Check if user can see full contact info (superuser or lead owner)
                                        if (isSuperUser($user['username']) || $lead['user_id'] == $user_id) {
                                            echo htmlspecialchars($lead['email']);
                                        } else {
                                            // Mask email for privacy
                                            $email = $lead['email'];
                                            $atPos = strpos($email, '@');
                                            if ($atPos !== false && $atPos > 2) {
                                                $maskedEmail = substr($email, 0, 2) . str_repeat('*', $atPos - 2) . substr($email, $atPos);
                                                echo htmlspecialchars($maskedEmail);
                                            } else {
                                                echo '***@***';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Check if user can see full contact info (superuser or lead owner)
                                        if (isSuperUser($user['username']) || $lead['user_id'] == $user_id) {
                                            echo htmlspecialchars($lead['phone']);
                                        } else {
                                            // Mask phone for privacy
                                            $phone = $lead['phone'];
                                            if (strlen($phone) > 4) {
                                                $maskedPhone = substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 6) . substr($phone, -3);
                                                echo htmlspecialchars($maskedPhone);
                                            } else {
                                                echo '***-***';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="temperature <?php echo strtolower($lead['temperature']); ?>">
                                            <?php echo htmlspecialchars($lead['temperature']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($lead['status']); ?></td>
                                    <td><?php echo htmlspecialchars($lead['source']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($lead['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="lead-details.php?id=<?php echo $lead['id']; ?>" class="btn-view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if (canEditLead($lead, $user_id)): ?>
                                                <a href="edit-lead.php?id=<?php echo $lead['id']; ?>" class="btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn-edit disabled" 
                                                        title="You can only edit leads assigned to you" 
                                                        disabled>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn-delete" onclick="deleteLead(<?php echo $lead['id']; ?>)" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php echo min(($current_page - 1) * $leads_per_page + 1, $total_leads); ?> to 
                        <?php echo min($current_page * $leads_per_page, $total_leads); ?> of 
                        <?php echo $total_leads; ?> active leads
                    </div>
                    <div>
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
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function deleteLead(id) {
        if (confirm('Are you sure you want to delete this lead?')) {
            fetch(`delete-lead.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error deleting lead');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting lead');
            });
        }
    }
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
