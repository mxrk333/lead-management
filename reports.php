<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get database connection
$conn = getDbConnection();
if (!$conn) {
    die("Database connection failed.");
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Check if user has permission to view reports
if ($user['role'] != 'manager' && $user['role'] != 'supervisor' && $user['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Get report parameters
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil(date('n') / 3);
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$selected_team_member = isset($_GET['team_member']) && !empty($_GET['team_member']) ? $_GET['team_member'] : null;

// Handle date range calculation
if ($month > 0) {
    $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$year-$month-01"));
} else {
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3;
    $start_date = "$year-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = "$year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$year-$end_month-01"));
}

// Get team filter
$selected_team_id = null;
if ($user['role'] == 'admin') {
    $selected_team_id = isset($_GET['team_id']) ? $_GET['team_id'] : 'all';
} else {
    $selected_team_id = $user['team_id'];
}

// Get all teams for admin selection
$all_teams = [];
if ($user['role'] == 'admin') {
    $teams_query = "SELECT id, name FROM teams ORDER BY name ASC";
    $teams_result = $conn->query($teams_query);
    
    if ($teams_result && $teams_result->num_rows > 0) {
        while ($team = $teams_result->fetch_assoc()) {
            $all_teams[] = $team;
        }
    }
}

// Get team members - FIXED: Ensure proper team filtering
$teamMembers = [];
if ($user['role'] == 'admin' && $selected_team_id == 'all') {
    $all_members_query = "SELECT id, name FROM users WHERE role != 'admin' ORDER BY name ASC";
    $all_members_result = $conn->query($all_members_query);
    
    if ($all_members_result && $all_members_result->num_rows > 0) {
        while ($member = $all_members_result->fetch_assoc()) {
            $teamMembers[] = $member;
        }
    }
} else {
    $team_id_to_use = ($user['role'] == 'admin') ? $selected_team_id : $user['team_id'];
    
    if ($team_id_to_use && $team_id_to_use != 'all') {
        $team_members_query = "SELECT id, name FROM users WHERE team_id = ? ORDER BY name ASC";
        $stmt = $conn->prepare($team_members_query);
        $stmt->bind_param("i", $team_id_to_use);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($member = $result->fetch_assoc()) {
                $teamMembers[] = $member;
            }
        }
        $stmt->close();
    }
}

// Initialize report data structure
$reportData = [
    'total_leads' => 0,
    'presentations' => 0,
    'closed_deals' => 0,
    'conversion_rate' => 0,
    'total_value' => 0,
    'status_distribution' => [],
    'temperature_distribution' => [],
    'top_projects' => [],
    'top_models' => [],
    'top_sources' => [],
    'team_performance' => []
];

// FIXED: Build proper WHERE clause for all queries
function buildWhereClause($user, $selected_team_id, $selected_team_member, $start_date, $end_date) {
    $where_conditions = [];
    $params = [];
    $param_types = "";

    // Always add date filter
    $where_conditions[] = "l.created_at BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";

    // Add team/member filters
    if ($selected_team_member) {
        // If specific team member is selected
        $where_conditions[] = "l.user_id = ?";
        $params[] = $selected_team_member;
        $param_types .= "i";
    } elseif ($user['role'] == 'admin' && $selected_team_id != 'all') {
        // If admin selects specific team
        $where_conditions[] = "u.team_id = ?";
        $params[] = $selected_team_id;
        $param_types .= "i";
    } elseif ($user['role'] != 'admin') {
        // If non-admin user, filter by their team
        $where_conditions[] = "u.team_id = ?";
        $params[] = $user['team_id'];
        $param_types .= "i";
    }
    // If admin selects 'all', no additional team filter needed

    return [
        'where_clause' => implode(" AND ", $where_conditions),
        'params' => $params,
        'param_types' => $param_types
    ];
}

$whereData = buildWhereClause($user, $selected_team_id, $selected_team_member, $start_date, $end_date);
$where_clause = $whereData['where_clause'];
$params = $whereData['params'];
$param_types = $whereData['param_types'];

// Get summary data
$summary_query = "
    SELECT 
        COUNT(DISTINCT l.id) as total_leads,
        COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
        COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
        SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
    FROM 
        leads l
    LEFT JOIN 
        users u ON u.id = l.user_id
    LEFT JOIN 
        lead_activities la ON la.lead_id = l.id
    WHERE 
        $where_clause
";

$stmt = $conn->prepare($summary_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $summary = $result->fetch_assoc();
    $reportData['total_leads'] = $summary['total_leads'];
    $reportData['presentations'] = $summary['presentations'];
    $reportData['closed_deals'] = $summary['closed_deals'];
    $reportData['total_value'] = $summary['total_value'];
    
    // Calculate conversion rate
    if ($reportData['total_leads'] > 0) {
        $reportData['conversion_rate'] = round(($reportData['closed_deals'] / $reportData['total_leads']) * 100, 1);
    }
}
$stmt->close();

// Get status distribution
$status_query = "
    SELECT 
        l.status,
        COUNT(l.id) as count,
        SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as value
    FROM 
        leads l
    LEFT JOIN 
        users u ON u.id = l.user_id
    WHERE 
        $where_clause
    GROUP BY 
        l.status
    ORDER BY 
        count DESC
";

$stmt = $conn->prepare($status_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reportData['status_distribution'][] = $row;
    }
}
$stmt->close();

// Get temperature distribution
$temp_query = "
    SELECT 
        l.temperature,
        COUNT(l.id) as count,
        SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as value
    FROM 
        leads l
    LEFT JOIN 
        users u ON u.id = l.user_id
    WHERE 
        $where_clause
    GROUP BY 
        l.temperature
    ORDER BY 
        FIELD(l.temperature, 'Hot', 'Warm', 'Cold')
";

$stmt = $conn->prepare($temp_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reportData['temperature_distribution'][] = $row;
    }
}
$stmt->close();

// Get top projects
$projects_query = "
    SELECT 
        l.developer,
        COUNT(l.id) as count
    FROM 
        leads l
    LEFT JOIN 
        users u ON u.id = l.user_id
    WHERE 
        $where_clause
    GROUP BY 
        l.developer
    ORDER BY 
        count DESC
    LIMIT 5
";

$stmt = $conn->prepare($projects_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reportData['top_projects'][] = $row;
    }
}
$stmt->close();

// Get top models
$models_query = "
    SELECT 
        l.project_model,
        COUNT(l.id) as count
    FROM 
        leads l
    LEFT JOIN 
        users u ON u.id = l.user_id
    WHERE 
        $where_clause
    GROUP BY 
        l.project_model
    ORDER BY 
        count DESC
    LIMIT 5
";

$stmt = $conn->prepare($models_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reportData['top_models'][] = $row;
    }
}
$stmt->close();

// Get top sources
$sources_query = "
    SELECT 
        l.source,
        COUNT(l.id) as count
    FROM 
        leads l
    LEFT JOIN 
        users u ON u.id = l.user_id
    WHERE 
        $where_clause
    GROUP BY 
        l.source
    ORDER BY 
        count DESC
    LIMIT 8
";

$stmt = $conn->prepare($sources_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reportData['top_sources'][] = $row;
    }
}
$stmt->close();

// Get team performance data
if ($selected_team_member) {
    // If a team member is selected, only show that member's performance
    $team_query = "
        SELECT 
            u.id,
            u.name,
            COUNT(DISTINCT l.id) as total_leads,
            COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
            COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
            CASE 
                WHEN COUNT(DISTINCT l.id) > 0 
                THEN ROUND((COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
                ELSE 0
            END as conversion_rate,
            SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
        FROM 
            users u
        LEFT JOIN 
            leads l ON l.user_id = u.id AND l.created_at BETWEEN ? AND ?
        LEFT JOIN 
            lead_activities la ON la.lead_id = l.id
        WHERE 
            u.id = ?
        GROUP BY 
            u.id, u.name
        ORDER BY 
            total_leads DESC
    ";
    
    $stmt = $conn->prepare($team_query);
    $stmt->bind_param("ssi", $start_date, $end_date, $selected_team_member);
} elseif ($user['role'] == 'admin' && $selected_team_id == 'all') {
    // Show all teams' performance
    $team_query = "
        SELECT 
            t.id,
            t.name,
            COUNT(DISTINCT l.id) as total_leads,
            COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
            COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
            CASE 
                WHEN COUNT(DISTINCT l.id) > 0 
                THEN ROUND((COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
                ELSE 0
            END as conversion_rate,
            SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
        FROM 
            teams t
        LEFT JOIN 
            users u ON u.team_id = t.id
        LEFT JOIN 
            leads l ON l.user_id = u.id AND l.created_at BETWEEN ? AND ?
        LEFT JOIN 
            lead_activities la ON la.lead_id = l.id
        GROUP BY 
            t.id, t.name
        ORDER BY 
            t.name ASC
    ";
    
    $stmt = $conn->prepare($team_query);
    $stmt->bind_param("ss", $start_date, $end_date);
} else {
    // Get team member performance for specific team
    $team_id = ($user['role'] == 'admin') ? $selected_team_id : $user['team_id'];
    
    $team_query = "
        SELECT 
            u.id,
            u.name,
            COUNT(DISTINCT l.id) as total_leads,
            COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
            COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
            CASE 
                WHEN COUNT(DISTINCT l.id) > 0 
                THEN ROUND((COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
                ELSE 0
            END as conversion_rate,
            SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
        FROM 
            users u
        LEFT JOIN 
            leads l ON l.user_id = u.id AND l.created_at BETWEEN ? AND ?
        LEFT JOIN 
            lead_activities la ON la.lead_id = l.id
        WHERE 
            u.team_id = ?
        GROUP BY 
            u.id, u.name
        ORDER BY 
            total_leads DESC
    ";
    
    $stmt = $conn->prepare($team_query);
    $stmt->bind_param("ssi", $start_date, $end_date, $team_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reportData['team_performance'][] = $row;
    }
}
$stmt->close();

// Get quarter name
$quarterNames = [
    1 => "Q1 (Jan-Mar)",
    2 => "Q2 (Apr-Jun)", 
    3 => "Q3 (Jul-Sep)",
    4 => "Q4 (Oct-Dec)"
];
$quarterName = $quarterNames[$quarter];

// Get team name for display
$team_name = "All Teams";
if ($selected_team_id != 'all' && $user['role'] == 'admin') {
    foreach ($all_teams as $team) {
        if ($team['id'] == $selected_team_id) {
            $team_name = $team['name'];
            break;
        }
    }
} elseif ($user['role'] != 'admin') {
    $team_query = "SELECT name FROM teams WHERE id = ?";
    $stmt = $conn->prepare($team_query);
    $stmt->bind_param("i", $user['team_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $team = $result->fetch_assoc();
        $team_name = $team['name'];
    }
    $stmt->close();
}

// Get team member name for display if selected
$team_member_name = "";
if ($selected_team_member) {
    foreach ($teamMembers as $member) {
        if ($member['id'] == $selected_team_member) {
            $team_member_name = $member['name'];
            break;
        }
    }
}

$conn->close();

function hasChartData($reportData) {
    return !empty($reportData['status_distribution']) || 
           !empty($reportData['temperature_distribution']) || 
           !empty($reportData['top_projects']) || 
           !empty($reportData['top_models']) ||
           !empty($reportData['top_sources']);
}

$hasData = $reportData['total_leads'] > 0 || !empty($reportData['team_performance']);
$hasCharts = hasChartData($reportData);

function getMonthsInQuarter($quarter) {
    $startMonth = ($quarter - 1) * 3 + 1;
    $months = [];
    
    for ($i = 0; $i < 3; $i++) {
        $monthNum = $startMonth + $i;
        $monthName = date('F', mktime(0, 0, 0, $monthNum, 1));
        $months[$monthNum] = $monthName;
    }
    
    return $months;
}

$monthsInQuarter = getMonthsInQuarter($quarter);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quarterly Reports</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
<style>
    :root {
        --primary: #2563eb;
        --primary-light: rgba(37, 99, 235, 0.1);
        --primary-dark: #1d4ed8;
        --secondary: #f8fafc;
        --success: #059669;
        --success-light: rgba(5, 150, 105, 0.1);
        --danger: #dc2626;
        --danger-light: rgba(220, 38, 38, 0.1);
        --warning: #d97706;
        --warning-light: rgba(217, 119, 6, 0.1);
        --info: #0891b2;
        --info-light: rgba(8, 145, 178, 0.1);
        --dark: #1f2937;
        --gray: #6b7280;
        --gray-light: #e5e7eb;
        --white: #ffffff;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --radius-sm: 0.25rem;
        --radius: 0.5rem;
        --radius-lg: 0.75rem;
        --transition: all 0.2s ease-in-out;
    }

    /* General Styles */
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: #f8fafc;
        color: var(--dark);
        line-height: 1.5;
        margin: 0;
    }

    .reports-page {
        flex: 1;
        padding: 1.5rem;
        width: 100%;
        margin: 0;
        min-height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
    }

    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-light);
    }

    .page-title {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .page-title i {
        color: var(--primary);
        font-size: 1.5rem;
    }

    .team-badge {
        display: inline-flex;
        align-items: center;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: var(--white);
        padding: 0.375rem 1rem;
        border-radius: var(--radius-lg);
        font-size: 0.875rem;
        font-weight: 600;
        margin-left: 1rem;
        box-shadow: var(--shadow);
    }

    .team-badge i {
        margin-right: 0.5rem;
        color: var(--white);
    }

    .period-badge {
        display: inline-flex;
        align-items: center;
        background: linear-gradient(135deg, var(--secondary) 0%, #e2e8f0 100%);
        color: var(--dark);
        padding: 0.5rem 1.25rem;
        border-radius: var(--radius-lg);
        font-size: 1rem;
        font-weight: 600;
        border: 2px solid var(--primary);
        box-shadow: var(--shadow);
    }

    .period-badge i {
        margin-right: 0.5rem;
        color: var(--primary);
    }

    /* Filters */
    .report-filters {
        background: linear-gradient(135deg, var(--white) 0%, var(--secondary) 100%);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--gray-light);
    }

    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 1.25rem;
        align-items: flex-end;
    }

    .form-group {
        flex: 1;
        min-width: 200px;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--dark);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-light);
        border-radius: var(--radius);
        background-color: var(--white);
        font-size: 0.875rem;
        color: var(--dark);
        transition: var(--transition);
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 1rem;
        padding-right: 3rem;
        font-weight: 500;
    }

    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-light);
    }

    /* Summary Cards */
    .report-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .summary-card {
        background: linear-gradient(135deg, var(--white) 0%, var(--secondary) 100%);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        padding: 1.5rem;
        display: flex;
        align-items: center;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        border: 1px solid var(--gray-light);
    }

    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 5px;
        height: 100%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    }

    .summary-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .summary-icon {
        width: 3.5rem;
        height: 3.5rem;
        background: linear-gradient(135deg, var(--primary-light) 0%, rgba(37, 99, 235, 0.2) 100%);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1.25rem;
        color: var(--primary);
        font-size: 1.25rem;
        flex-shrink: 0;
        border: 2px solid var(--primary-light);
    }

    .summary-info {
        flex: 1;
    }

    .summary-info h3 {
        margin: 0 0 0.5rem 0;
        font-size: 0.875rem;
        color: var(--gray);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .summary-info p {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--dark);
    }

    .summary-card.leads::before { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
    .summary-card.presentations::before { background: linear-gradient(135deg, var(--info) 0%, #0e7490 100%); }
    .summary-card.closed::before { background: linear-gradient(135deg, var(--success) 0%, #047857 100%); }
    .summary-card.rate::before { background: linear-gradient(135deg, var(--warning) 0%, #b45309 100%); }

    .summary-card.leads .summary-icon { background: linear-gradient(135deg, var(--primary-light) 0%, rgba(37, 99, 235, 0.2) 100%); color: var(--primary); border-color: var(--primary-light); }
    .summary-card.presentations .summary-icon { background: linear-gradient(135deg, var(--info-light) 0%, rgba(8, 145, 178, 0.2) 100%); color: var(--info); border-color: var(--info-light); }
    .summary-card.closed .summary-icon { background: linear-gradient(135deg, var(--success-light) 0%, rgba(5, 150, 105, 0.2) 100%); color: var(--success); border-color: var(--success-light); }
    .summary-card.rate .summary-icon { background: linear-gradient(135deg, var(--warning-light) 0%, rgba(217, 119, 6, 0.2) 100%); color: var(--warning); border-color: var(--warning-light); }

    /* Charts - Redesigned Layout */
    .report-charts {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-container {
        background: linear-gradient(135deg, var(--white) 0%, var(--secondary) 100%);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        padding: 1.5rem;
        height: 350px;
        transition: var(--transition);
        border: 1px solid var(--gray-light);
    }

    .chart-container:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--gray-light);
    }

    .chart-title {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .chart-title i {
        color: var(--primary);
        font-size: 1rem;
    }

    .chart-body {
        height: calc(100% - 3.5rem);
        position: relative;
    }

    /* Small Charts Grid */
    .small-charts-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .small-chart-container {
        background: linear-gradient(135deg, var(--white) 0%, var(--secondary) 100%);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        padding: 1.25rem;
        height: 280px;
        transition: var(--transition);
        border: 1px solid var(--gray-light);
    }

    .small-chart-container:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .small-chart-container .chart-header {
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
    }

    .small-chart-container .chart-title {
        font-size: 1rem;
    }

    .small-chart-container .chart-body {
        height: calc(100% - 2.5rem);
    }

    /* Card Styles */
    .card {
        background: linear-gradient(135deg, var(--white) 0%, var(--secondary) 100%);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        margin-bottom: 2rem;
        overflow: hidden;
        border: 1px solid var(--gray-light);
    }

    .card-header {
        padding: 1.5rem;
        border-bottom: 2px solid var(--gray-light);
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    }

    .card-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--white);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .card-body {
        padding: 0;
    }

    /* Table Styles */
    .table-container {
        overflow-x: auto;
        border-radius: var(--radius-lg);
    }

    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background-color: var(--white);
        margin-bottom: 0;
    }

    .table thead {
        background: linear-gradient(135deg, var(--dark) 0%, #374151 100%);
    }

    .table th {
        color: var(--white);
        font-weight: 700;
        font-size: 0.875rem;
        padding: 1.25rem 1rem;
        text-align: center;
        border-bottom: 3px solid var(--primary);
        cursor: pointer;
        user-select: none;
        position: relative;
        transition: all 0.2s ease;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table th:hover {
        background: linear-gradient(135deg, #374151 0%, var(--dark) 100%);
        transform: translateY(-1px);
    }

    .table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--gray-light);
    }

    .table tbody tr:nth-child(even) {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .table tbody tr:nth-child(odd) {
        background: var(--white);
    }

    .table tbody tr:hover {
        background: linear-gradient(135deg, var(--primary-light) 0%, rgba(37, 99, 235, 0.05) 100%);
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        border-left: 4px solid var(--primary);
    }

    .table td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        font-size: 0.875rem;
        color: var(--dark);
        border-bottom: 1px solid var(--gray-light);
        position: relative;
        text-align: center;
        font-weight: 500;
    }

    .table td.name {
        font-weight: 700;
        color: var(--primary-dark);
        font-size: 0.9rem;
        text-align: left;
    }

    /* Metric Styles */
    .metric-value {
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .metric-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 700;
        white-space: nowrap;
        box-shadow: var(--shadow);
        transition: all 0.2s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .metric-badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .metric-badge.high {
        background: linear-gradient(135deg, var(--success) 0%, #047857 100%);
        color: var(--white);
    }

    .metric-badge.medium {
        background: linear-gradient(135deg, var(--warning) 0%, #b45309 100%);
        color: var(--white);
    }

    .metric-badge.low {
        background: linear-gradient(135deg, var(--danger) 0%, #b91c1c 100%);
        color: var(--white);
    }

    /* No Data */
    .no-data {
        background: linear-gradient(135deg, var(--white) 0%, var(--secondary) 100%);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        padding: 4rem 2rem;
        text-align: center;
        margin-bottom: 2rem;
        border: 1px solid var(--gray-light);
    }

    .no-data i {
        font-size: 3rem;
        color: var(--gray);
        margin-bottom: 1.5rem;
        opacity: 0.5;
    }

    .no-data h3 {
        margin: 0 0 1rem 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark);
    }

    .no-data p {
        margin: 0;
        color: var(--gray);
        max-width: 500px;
        margin: 0 auto;
        font-size: 1.1rem;
    }

    /* Report Actions */
    .report-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-lg);
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        border: none;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: var(--shadow);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: var(--white);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.25);
    }

    .btn-secondary {
        background: linear-gradient(135deg, var(--secondary) 0%, #e2e8f0 100%);
        color: var(--dark);
        border: 2px solid var(--gray-light);
    }

    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        border-color: var(--primary);
    }

    /* Utility Classes */
    .text-center { text-align: center; }
    .d-flex { display: flex; }
    .align-items-center { align-items: center; }
    .me-2 { margin-right: 0.5rem; }
    .text-primary { color: var(--primary); }
    .text-info { color: var(--info); }
    .text-success { color: var(--success); }
    .text-warning { color: var(--warning); }

    /* OPTIMIZED COMPACT PRINT STYLES - NO WASTED PAGES */
    @media print {
        @page {
            margin: 0.5in;
            size: A4 portrait;
        }
        
        * {
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
            print-color-adjust: exact !important;
            box-shadow: none !important;
        }
        
        body {
            font-family: 'Arial', sans-serif !important;
            font-size: 11pt !important;
            line-height: 1.3 !important;
            color: #000 !important;
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* FORCE HIDE ALL SCREEN ELEMENTS */
        .sidebar,
        .header,
        .main-content > .header,
        .report-filters,
        .report-actions,
        .btn,
        .no-data,
        .chart-actions,
        nav,
        .navbar,
        .menu,
        .navigation {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* FORCE SHOW ONLY REPORT CONTENT */
        .container {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .main-content {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .reports-page {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: auto !important;
        }
        
        /* COMPACT HEADER - NO PAGE WASTE */
        .page-header {
            display: block !important;
            text-align: center !important;
            margin-bottom: 15px !important;
            padding: 10px 0 !important;
            border-bottom: 2px solid #000 !important;
            background: #f5f5f5 !important;
            page-break-after: avoid !important;
        }
        
        .page-title {
            display: block !important;
            font-size: 16pt !important;
            font-weight: bold !important;
            color: #000 !important;
            margin: 0 0 8px 0 !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            text-align: center !important;
        }
        
        .team-badge,
        .period-badge {
            display: inline-block !important;
            font-size: 9pt !important;
            color: #000 !important;
            background: #fff !important;
            border: 1px solid #000 !important;
            padding: 4px 8px !important;
            margin: 2px 5px !important;
            border-radius: 3px !important;
            font-weight: bold !important;
        }
        
        /* COMPACT EXECUTIVE SUMMARY - SAME PAGE AS HEADER */
        .report-summary {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 10px !important;
            margin: 15px 0 !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
            width: 100% !important;
        }
        
        .summary-card {
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            border: 2px solid #000 !important;
            padding: 12px 8px !important;
            text-align: center !important;
            background: #f9f9f9 !important;
            border-radius: 5px !important;
            height: 60px !important;
            margin: 0 !important;
        }
        
        .summary-card::before {
            display: none !important;
        }
        
        .summary-icon {
            display: none !important;
        }
        
        .summary-info {
            display: block !important;
            width: 100% !important;
        }
        
        .summary-info h3 {
            font-size: 7pt !important;
            margin: 0 0 4px 0 !important;
            color: #000 !important;
            text-transform: uppercase !important;
            font-weight: bold !important;
            letter-spacing: 0.5px !important;
        }
        
        .summary-info p {
            font-size: 14pt !important;
            font-weight: bold !important;
            color: #000 !important;
            margin: 0 !important;
        }
        
        /* COMPACT TEAM PERFORMANCE TABLE - SAME PAGE */
        .card {
            display: block !important;
            border: 2px solid #000 !important;
            margin: 15px 0 !important;
            border-radius: 5px !important;
            background: #fff !important;
            page-break-inside: avoid !important;
            page-break-before: avoid !important;
            width: 100% !important;
        }
        
        .card-header {
            display: block !important;
            background: #e0e0e0 !important;
            border-bottom: 1px solid #000 !important;
            padding: 8px !important;
            text-align: center !important;
        }
        
        .card-header h3 {
            font-size: 11pt !important;
            font-weight: bold !important;
            color: #000 !important;
            margin: 0 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }
        
        .card-body {
            display: block !important;
            padding: 0 !important;
        }
        
        /* COMPACT TABLE */
        .table-container {
            display: block !important;
            overflow: visible !important;
            width: 100% !important;
        }
        
        .table {
            display: table !important;
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 0 !important;
            font-size: 8pt !important;
        }
        
        .table thead {
            display: table-header-group !important;
            background: #d0d0d0 !important;
        }
        
        .table tbody {
            display: table-row-group !important;
        }
        
        .table tr {
            display: table-row !important;
            page-break-inside: avoid !important;
        }
        
        .table th {
            display: table-cell !important;
            background: #d0d0d0 !important;
            border: 1px solid #000 !important;
            padding: 6px 4px !important;
            text-align: center !important;
            font-weight: bold !important;
            color: #000 !important;
            font-size: 8pt !important;
            text-transform: uppercase !important;
            letter-spacing: 0.3px !important;
            vertical-align: middle !important;
        }
        
        .table td {
            display: table-cell !important;
            border: 1px solid #000 !important;
            padding: 6px 4px !important;
            text-align: center !important;
            font-size: 8pt !important;
            vertical-align: middle !important;
            color: #000 !important;
        }
        
        .table tbody tr:nth-child(even) {
            background: #f8f8f8 !important;
        }
        
        .table tbody tr:nth-child(odd) {
            background: #fff !important;
        }
        
        .table td.name {
            text-align: left !important;
            font-weight: bold !important;
        }
        
        /* CHARTS SECTION - NEW PAGE BUT COMPACT */
        .small-charts-grid {
            page-break-before: always !important;
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 15px !important;
            margin: 20px 0 !important;
            width: 100% !important;
        }
        
        .small-chart-container {
            display: block !important;
            width: 100% !important;
            height: 250px !important;
            border: 2px solid #000 !important;
            padding: 10px !important;
            background: #fff !important;
            border-radius: 5px !important;
            page-break-inside: avoid !important;
        }
        
        .report-charts {
            page-break-before: always !important;
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 15px !important;
            margin: 20px 0 !important;
            width: 100% !important;
        }
        
        .chart-container {
            display: block !important;
            width: 100% !important;
            height: 280px !important;
            border: 2px solid #000 !important;
            padding: 10px !important;
            background: #fff !important;
            border-radius: 5px !important;
            page-break-inside: avoid !important;
        }
        
        .chart-header {
            display: block !important;
            text-align: center !important;
            margin-bottom: 8px !important;
            border-bottom: 1px solid #ccc !important;
            padding-bottom: 5px !important;
        }
        
        .chart-title {
            display: block !important;
            font-size: 9pt !important;
            font-weight: bold !important;
            color: #000 !important;
            margin: 0 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }
        
        .chart-title i {
            display: none !important;
        }
        
        .chart-body {
            display: block !important;
            height: calc(100% - 30px) !important;
            width: 100% !important;
        }
        
        /* CANVAS OPTIMIZATION */
        canvas {
            display: block !important;
            max-width: 100% !important;
            max-height: 200px !important;
            margin: 0 auto !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        /* METRIC BADGES */
        .metric-badge {
            padding: 2px 5px !important;
            border-radius: 3px !important;
            font-size: 7pt !important;
            font-weight: bold !important;
            border: 1px solid #000 !important;
        }
        
        .metric-badge.high {
            background: #e8f5e8 !important;
            color: #000 !important;
        }
        
        .metric-badge.medium {
            background: #fff8e1 !important;
            color: #000 !important;
        }
        
        .metric-badge.low {
            background: #ffebee !important;
            color: #000 !important;
        }
        
        /* HIDE ALL ICONS */
        .fas, .far, .fab, i[class*="fa"] {
            display: none !important;
        }
        
        /* METRIC VALUES */
        .metric-value {
            display: block !important;
            text-align: center !important;
        }
        
        .d-flex {
            display: block !important;
        }
        
        /* FORCE VISIBILITY AND COMPACT LAYOUT */
        .print-order-wrapper {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .print-order-wrapper > * {
            page-break-before: avoid !important;
            margin-top: 0 !important;
        }
        
        /* ENSURE FIRST PAGE CONTENT */
        .print-order-wrapper > .report-summary {
            page-break-before: avoid !important;
            page-break-after: avoid !important;
        }
        
        .print-order-wrapper > .card:first-of-type {
            page-break-before: avoid !important;
        }
        
        /* PROFESSIONAL FOOTER */
        .print-footer {
            position: fixed;
            bottom: 0.3in;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 5px;
            background: #fff;
        }
        
        /* REMOVE ALL UNNECESSARY SPACING */
        * {
            margin-top: 0 !important;
        }
        
        .page-header + * {
            margin-top: 0 !important;
        }
    }

    /* Animation for sorting */
    @keyframes rowSlideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .row-animate {
        animation: rowSlideIn 0.3s ease-out forwards;
    }
</style>

<script>
// FIXED: Ensure chart data is properly passed and updated
const reportData = {
    status_distribution: <?php echo json_encode($reportData['status_distribution'] ?? []); ?>,
    temperature_distribution: <?php echo json_encode($reportData['temperature_distribution'] ?? []); ?>,
    top_projects: <?php echo json_encode($reportData['top_projects'] ?? []); ?>,
    top_models: <?php echo json_encode($reportData['top_models'] ?? []); ?>,
    top_sources: <?php echo json_encode($reportData['top_sources'] ?? []); ?>,
    team_performance: <?php echo json_encode($reportData['team_performance'] ?? []); ?>
};

console.log('Report Data:', reportData);

// FIXED: Add data validation to ensure charts only render with valid data
function hasValidChartData(data) {
    return data && Array.isArray(data) && data.length > 0;
}
</script>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="reports-page">
                <div class="print-footer" style="display: none;">
                    <strong>CONFIDENTIAL BUSINESS REPORT</strong> | Generated: <?php echo date('F j, Y \a\t g:i A'); ?> | <?php echo htmlspecialchars($team_name); ?>
                </div>
                
                <div class="page-header">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-chart-line"></i> QUARTERLY BUSINESS REPORTS
                            <?php if ($user['role'] == 'admin' && $selected_team_id != 'all'): ?>
                                <span class="team-badge">
                                    <i class="fas fa-users"></i> <?php echo htmlspecialchars($team_name); ?>
                                </span>
                            <?php elseif ($user['role'] != 'admin'): ?>
                                <span class="team-badge">
                                    <i class="fas fa-users"></i> <?php echo htmlspecialchars($team_name); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($selected_team_member): ?>
                                <span class="team-badge" style="background: linear-gradient(135deg, var(--info) 0%, #0e7490 100%);">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($team_member_name); ?>
                                </span>
                            <?php endif; ?>
                        </h1>
                    </div>
                    
                    <div class="period-badge">
                        <i class="fas fa-calendar-alt"></i> <?php echo $year; ?> - <?php echo $quarterName; ?>
                        <?php if ($month > 0): ?>
                            - <?php echo date('F', mktime(0, 0, 0, $month, 1)); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="report-filters">
                    <form method="GET" action="reports.php" class="filter-form">
                        <?php if ($user['role'] == 'admin'): ?>
                        <div class="form-group">
                            <label for="team_id">Team Selection</label>
                            <select id="team_id" name="team_id" onchange="this.form.submit()">
                                <option value="all" <?php echo $selected_team_id == 'all' ? 'selected' : ''; ?>>All Teams</option>
                                <?php foreach ($all_teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" <?php echo $selected_team_id == $team['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($team['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="year">Report Year</label>
                            <select id="year" name="year" onchange="this.form.submit()">
                                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="quarter">Quarter Period</label>
                            <select id="quarter" name="quarter" onchange="this.form.submit()">
                                <option value="1" <?php echo $quarter == 1 ? 'selected' : ''; ?>>Q1 (Jan-Mar)</option>
                                <option value="2" <?php echo $quarter == 2 ? 'selected' : ''; ?>>Q2 (Apr-Jun)</option>
                                <option value="3" <?php echo $quarter == 3 ? 'selected' : ''; ?>>Q3 (Jul-Sep)</option>
                                <option value="4" <?php echo $quarter == 4 ? 'selected' : ''; ?>>Q4 (Oct-Dec)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="month">Month Filter</label>
                            <select id="month" name="month" onchange="this.form.submit()">
                                <option value="0" <?php echo $month == 0 ? 'selected' : ''; ?>>All Months</option>
                                <?php foreach ($monthsInQuarter as $monthNum => $monthName): ?>
                                <option value="<?php echo $monthNum; ?>" <?php echo $month == $monthNum ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($monthName); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (count($teamMembers) > 0): ?>
                        <div class="form-group">
                            <label for="team_member">Team Member</label>
                            <select id="team_member" name="team_member" onchange="this.form.submit()">
                                <option value="">All Team Members</option>
                                <?php foreach ($teamMembers as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo $selected_team_member == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if ($hasData): ?>
                <div class="print-order-wrapper">
                    <!-- Executive Summary Cards -->
                    <div class="report-summary">
                        <div class="summary-card leads">
                            <div class="summary-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Total Leads</h3>
                                <p><?php echo number_format($reportData['total_leads']); ?></p>
                            </div>
                        </div>
                        
                        <div class="summary-card presentations">
                            <div class="summary-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Presentations</h3>
                                <p><?php echo number_format($reportData['presentations']); ?></p>
                            </div>
                        </div>
                        
                        <div class="summary-card closed">
                            <div class="summary-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Closed Deals</h3>
                                <p><?php echo number_format($reportData['closed_deals']); ?></p>
                            </div>
                        </div>
                        
                        <div class="summary-card rate">
                            <div class="summary-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Conversion Rate</h3>
                                <p><?php echo $reportData['conversion_rate']; ?>%</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($reportData['team_performance'])): ?>
                    <!-- Team Performance Overview -->
                    <div class="card">
                        <div class="card-header">
                            <h3>
                                <?php if ($user['role'] == 'admin' && $selected_team_id == 'all'): ?>
                                    Team Performance Overview
                                <?php else: ?>
                                    Team Member Performance Details
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th data-column="name">Name</th>
                                            <th data-column="total_leads">Total Leads</th>
                                            <th data-column="presentations">Presentations</th>
                                            <th data-column="closed_deals">Closed Deals</th>
                                            <th data-column="conversion_rate">Conversion Rate</th>
                                            <th data-column="total_value">Total Value (₱)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['team_performance'] as $performer): ?>
                                        <tr data-name="<?php echo htmlspecialchars($performer['name']); ?>" 
                                            data-leads="<?php echo $performer['total_leads']; ?>"
                                            data-presentations="<?php echo $performer['presentations']; ?>"
                                            data-closed="<?php echo $performer['closed_deals']; ?>"
                                            data-rate="<?php echo $performer['conversion_rate']; ?>"
                                            data-value="<?php echo $performer['total_value']; ?>">
                                            <td class="name">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user me-2 text-primary"></i>
                                                    <?php echo htmlspecialchars($performer['name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="metric-value">
                                                    <i class="fas fa-users me-2 text-primary"></i>
                                                    <?php echo number_format($performer['total_leads']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="metric-value">
                                                    <i class="fas fa-handshake me-2 text-info"></i>
                                                    <?php echo number_format($performer['presentations']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="metric-value">
                                                    <i class="fas fa-check-circle me-2 text-success"></i>
                                                    <?php echo number_format($performer['closed_deals']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="metric-value">
                                                    <?php
                                                    $rate = floatval($performer['conversion_rate']);
                                                    $rateClass = $rate >= 50 ? 'high' : ($rate >= 25 ? 'medium' : 'low');
                                                    $rateIcon = $rate >= 50 ? 'fas fa-arrow-up' : ($rate >= 25 ? 'fas fa-minus' : 'fas fa-arrow-down');
                                                    ?>
                                                    <i class="<?php echo $rateIcon; ?> me-2"></i>
                                                    <span class="metric-badge <?php echo $rateClass; ?>">
                                                        <?php echo number_format($rate, 1); ?>%
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="metric-value">
                                                    <i class="fas fa-money-bill-wave me-2 text-warning"></i>
                                                    <?php echo number_format($performer['total_value'], 2); ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($hasCharts): ?>
                    <!-- Small Charts Grid (4 charts in 2x2) -->
                    <div class="small-charts-grid">
                        <div class="small-chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-chart-pie"></i> Lead Status Distribution</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="small-chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-thermometer-half"></i> Lead Temperature</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="temperatureChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="small-chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-building"></i> Top Projects</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="projectsChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="small-chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-home"></i> Top Models Inquired</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="modelsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Large Charts Grid (2 charts side by side) -->
                    <div class="report-charts">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-bullhorn"></i> Lead Sources Analysis</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="sourcesChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-chart-bar"></i> Performance Overview</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-chart-bar"></i>
                    <h3>No Data Available</h3>
                    <p>There is no data available for the selected period and team. Please try changing your filters or selecting a different time period to view reports.</p>
                </div>
                <?php endif; ?>
                
                <?php if ($hasData): ?>
                <div class="report-actions">
                    <button onclick="exportReport()" class="btn btn-primary">
                        <i class="fas fa-file-export"></i> Export Report
                    </button>
                    <button onclick="printReport()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // OPTIMIZED PRINT FUNCTION - NO WASTED PAGES
    function printReport() {
        console.log('Preparing compact print layout...');
        
        // Force all content to be visible and compact before printing
        const reportPage = document.querySelector('.reports-page');
        const printWrapper = document.querySelector('.print-order-wrapper');
        const pageHeader = document.querySelector('.page-header');
        const reportSummary = document.querySelector('.report-summary');
        const teamCard = document.querySelector('.card');
        
        // Force visibility and remove any spacing that causes page breaks
        if (reportPage) {
            reportPage.style.display = 'block';
            reportPage.style.visibility = 'visible';
            reportPage.style.opacity = '1';
            reportPage.style.margin = '0';
            reportPage.style.padding = '0';
        }
        
        if (printWrapper) {
            printWrapper.style.display = 'block';
            printWrapper.style.visibility = 'visible';
            printWrapper.style.opacity = '1';
            printWrapper.style.margin = '0';
            printWrapper.style.padding = '0';
        }
        
        if (pageHeader) {
            pageHeader.style.marginBottom = '10px';
            pageHeader.style.paddingBottom = '5px';
        }
        
        if (reportSummary) {
            reportSummary.style.marginTop = '0';
            reportSummary.style.marginBottom = '10px';
            reportSummary.style.pageBreakBefore = 'avoid';
            reportSummary.style.pageBreakAfter = 'avoid';
        }
        
        if (teamCard) {
            teamCard.style.marginTop = '0';
            teamCard.style.pageBreakBefore = 'avoid';
        }
        
        // Set compact print-optimized chart configurations
        Chart.defaults.font.size = 8;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.legend.labels.boxWidth = 8;
        Chart.defaults.plugins.legend.labels.padding = 3;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.font = {
            size: 7,
            weight: 'normal'
        };
        
        // Force chart resize for compact print optimization
        const charts = document.querySelectorAll('canvas');
        const chartPromises = [];
        
        charts.forEach((canvas, index) => {
            if (canvas.chart) {
                // Compact print size for charts
                canvas.chart.resize(300, 200);
                
                const promise = new Promise((resolve) => {
                    setTimeout(() => {
                        canvas.chart.update('none');
                        resolve();
                    }, 100 * index);
                });
                chartPromises.push(promise);
            }
        });
        
        // Wait for charts to render, then print
        Promise.all(chartPromises).then(() => {
            setTimeout(() => {
                console.log('Initiating compact print...');
                window.print();
            }, 500);
        });
    }

    // Export function
    function exportReport() {
        const params = new URLSearchParams(window.location.search);
        let exportUrl = 'export-report.php?';
        
        if (params.has('year')) exportUrl += 'year=' + params.get('year') + '&';
        if (params.has('quarter')) exportUrl += 'quarter=' + params.get('quarter') + '&';
        if (params.has('month')) exportUrl += 'month=' + params.get('month') + '&';
        if (params.has('team_id')) exportUrl += 'team_id=' + params.get('team_id') + '&';
        if (params.has('team_member')) exportUrl += 'team_member=' + params.get('team_member');
        
        window.location.href = exportUrl;
    }

    // Enhanced table sorting
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.querySelector('.table');
        if (!table) return;

        const tbody = table.querySelector('tbody');
        const headers = table.querySelectorAll('th[data-column]');
        
        let currentSort = { column: null, direction: 'asc' };

        headers.forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-column');
                sortTable(column);
            });
        });

        function sortTable(column) {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            headers.forEach(h => h.classList.remove('asc', 'desc'));
            
            let direction = 'asc';
            if (currentSort.column === column && currentSort.direction === 'asc') {
                direction = 'desc';
            }
            
            currentSort = { column, direction };
            
            const currentHeader = table.querySelector(`th[data-column="${column}"]`);
            currentHeader.classList.add(direction);
            
            rows.sort((a, b) => {
                let aValue, bValue;
                
                switch(column) {
                    case 'name':
                        aValue = a.getAttribute('data-name').toLowerCase();
                        bValue = b.getAttribute('data-name').toLowerCase();
                        break;
                    case 'total_leads':
                        aValue = parseInt(a.getAttribute('data-leads'));
                        bValue = parseInt(b.getAttribute('data-leads'));
                        break;
                    case 'presentations':
                        aValue = parseInt(a.getAttribute('data-presentations'));
                        bValue = parseInt(b.getAttribute('data-presentations'));
                        break;
                    case 'closed_deals':
                        aValue = parseInt(a.getAttribute('data-closed'));
                        bValue = parseInt(b.getAttribute('data-closed'));
                        break;
                    case 'conversion_rate':
                        aValue = parseFloat(a.getAttribute('data-rate'));
                        bValue = parseFloat(b.getAttribute('data-rate'));
                        break;
                    case 'total_value':
                        aValue = parseFloat(a.getAttribute('data-value'));
                        bValue = parseFloat(b.getAttribute('data-value'));
                        break;
                    default:
                        aValue = 0;
                        bValue = 0;
                }
                
                if (direction === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
            
            rows.forEach(row => tbody.appendChild(row));
            
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.classList.add('row-animate');
                setTimeout(() => {
                    row.classList.remove('row-animate');
                }, 300 + (index * 50));
            });
        }
        
        // Initialize charts after DOM is loaded
        initializeCharts();
    });

    // FIXED: Chart initialization function with proper data validation
    function initializeCharts() {
        // Chart color schemes
        const colorSchemes = {
            primary: ['#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#dbeafe'],
            status: ['#2563eb', '#059669', '#d97706', '#dc2626', '#8b5cf6', '#06b6d4'],
            temperature: ['#dc2626', '#d97706', '#2563eb'],
            projects: '#2563eb',
            models: '#059669',
            sources: ['#2563eb', '#059669', '#d97706', '#dc2626', '#8b5cf6', '#06b6d4', '#84cc16', '#f59e0b']
        };

        // FIXED: Initialize Status Chart with data validation
        if (hasValidChartData(reportData.status_distribution)) {
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: reportData.status_distribution.map(item => item.status),
                        datasets: [{
                            data: reportData.status_distribution.map(item => item.count),
                            backgroundColor: colorSchemes.status,
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 10,
                                    usePointStyle: true,
                                    font: {
                                        size: 10,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#2563eb',
                                borderWidth: 1
                            }
                        }
                    }
                });
            }
        }

        // FIXED: Initialize Temperature Chart with data validation
        if (hasValidChartData(reportData.temperature_distribution)) {
            const tempCtx = document.getElementById('temperatureChart');
            if (tempCtx) {
                new Chart(tempCtx, {
                    type: 'pie',
                    data: {
                        labels: reportData.temperature_distribution.map(item => item.temperature),
                        datasets: [{
                            data: reportData.temperature_distribution.map(item => item.count),
                            backgroundColor: colorSchemes.temperature,
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 10,
                                    usePointStyle: true,
                                    font: {
                                        size: 10,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#2563eb',
                                borderWidth: 1
                            }
                        }
                    }
                });
            }
        }

        // FIXED: Initialize Projects Chart with data validation
        if (hasValidChartData(reportData.top_projects)) {
            const projectsCtx = document.getElementById('projectsChart');
            if (projectsCtx) {
                new Chart(projectsCtx, {
                    type: 'bar',
                    data: {
                        labels: reportData.top_projects.map(item => item.developer),
                        datasets: [{
                            label: 'Leads',
                            data: reportData.top_projects.map(item => item.count),
                            backgroundColor: colorSchemes.projects,
                            borderColor: '#1d4ed8',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#2563eb',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    font: {
                                        size: 9,
                                        weight: '500'
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 9,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // FIXED: Initialize Models Chart with data validation
        if (hasValidChartData(reportData.top_models)) {
            const modelsCtx = document.getElementById('modelsChart');
            if (modelsCtx) {
                new Chart(modelsCtx, {
                    type: 'bar',
                    data: {
                        labels: reportData.top_models.map(item => item.project_model),
                        datasets: [{
                            label: 'Inquiries',
                            data: reportData.top_models.map(item => item.count),
                            backgroundColor: colorSchemes.models,
                            borderColor: '#047857',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#059669',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    font: {
                                        size: 9,
                                        weight: '500'
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 9,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // FIXED: Initialize Sources Chart with data validation
        if (hasValidChartData(reportData.top_sources)) {
            const sourcesCtx = document.getElementById('sourcesChart');
            if (sourcesCtx) {
                new Chart(sourcesCtx, {
                    type: 'bar',
                    data: {
                        labels: reportData.top_sources.map(item => item.source),
                        datasets: [{
                            label: 'Leads',
                            data: reportData.top_sources.map(item => item.count),
                            backgroundColor: colorSchemes.sources,
                            borderColor: colorSchemes.sources.map(color => color.replace('0.8', '1')),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#2563eb',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    font: {
                                        size: 10,
                                        weight: '500'
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 10,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // FIXED: Initialize Performance Overview Chart with data validation
        if (hasValidChartData(reportData.team_performance)) {
            const performanceCtx = document.getElementById('performanceChart');
            if (performanceCtx) {
                new Chart(performanceCtx, {
                    type: 'bar',
                    data: {
                        labels: reportData.team_performance.map(item => item.name),
                        datasets: [
                            {
                                label: 'Total Leads',
                                data: reportData.team_performance.map(item => item.total_leads),
                                backgroundColor: '#2563eb',
                                borderColor: '#1d4ed8',
                                borderWidth: 1
                            },
                            {
                                label: 'Closed Deals',
                                data: reportData.team_performance.map(item => item.closed_deals),
                                backgroundColor: '#059669',
                                borderColor: '#047857',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 10,
                                    usePointStyle: true,
                                    font: {
                                        size: 10,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#2563eb',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    font: {
                                        size: 10,
                                        weight: '500'
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 10,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        console.log('All charts initialized successfully with data validation');
    }
    </script>
</body>
</html>