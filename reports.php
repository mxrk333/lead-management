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

// Get status distribution with percentages
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

$status_total = 0;
$status_temp = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status_temp[] = $row;
        $status_total += $row['count'];
    }
}

// Calculate percentages for status distribution
foreach ($status_temp as $status) {
    $percentage = $status_total > 0 ? round(($status['count'] / $status_total) * 100, 1) : 0;
    $reportData['status_distribution'][] = [
        'status' => $status['status'],
        'count' => $status['count'],
        'value' => $status['value'],
        'percentage' => $percentage
    ];
}
$stmt->close();

// Get temperature distribution with percentages
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

$temp_total = 0;
$temp_temp = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $temp_temp[] = $row;
        $temp_total += $row['count'];
    }
}

// Calculate percentages for temperature distribution
foreach ($temp_temp as $temp) {
    $percentage = $temp_total > 0 ? round(($temp['count'] / $temp_total) * 100, 1) : 0;
    $reportData['temperature_distribution'][] = [
        'temperature' => $temp['temperature'],
        'count' => $temp['count'],
        'value' => $temp['value'],
        'percentage' => $percentage
    ];
}
$stmt->close();

// Get top projects with percentages
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

$projects_total = 0;
$projects_temp = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $projects_temp[] = $row;
        $projects_total += $row['count'];
    }
}

// Calculate percentages for projects
foreach ($projects_temp as $project) {
    $percentage = $projects_total > 0 ? round(($project['count'] / $projects_total) * 100, 1) : 0;
    $reportData['top_projects'][] = [
        'developer' => $project['developer'],
        'count' => $project['count'],
        'percentage' => $percentage
    ];
}
$stmt->close();

// Get top models with percentages
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

$models_total = 0;
$models_temp = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $models_temp[] = $row;
        $models_total += $row['count'];
    }
}

// Calculate percentages for models
foreach ($models_temp as $model) {
    $percentage = $models_total > 0 ? round(($model['count'] / $models_total) * 100, 1) : 0;
    $reportData['top_models'][] = [
        'project_model' => $model['project_model'],
        'count' => $model['count'],
        'percentage' => $percentage
    ];
}
$stmt->close();

// Get top sources with percentages
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

$sources_total = 0;
$sources_temp = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sources_temp[] = $row;
        $sources_total += $row['count'];
    }
}

// Calculate percentages for sources
foreach ($sources_temp as $source) {
    $percentage = $sources_total > 0 ? round(($source['count'] / $sources_total) * 100, 1) : 0;
    $reportData['top_sources'][] = [
        'source' => $source['source'],
        'count' => $source['count'],
        'percentage' => $percentage
    ];
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

// Calculate team performance totals for percentage calculation
$team_total_leads = 0;
$team_total_presentations = 0;
$team_total_closed = 0;
$team_temp = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $team_temp[] = $row;
        $team_total_leads += $row['total_leads'];
        $team_total_presentations += $row['presentations'];
        $team_total_closed += $row['closed_deals'];
    }
}

// Calculate percentages for team performance
foreach ($team_temp as $performer) {
    $leads_percentage = $team_total_leads > 0 ? round(($performer['total_leads'] / $team_total_leads) * 100, 1) : 0;
    $presentations_percentage = $team_total_presentations > 0 ? round(($performer['presentations'] / $team_total_presentations) * 100, 1) : 0;
    $closed_percentage = $team_total_closed > 0 ? round(($performer['closed_deals'] / $team_total_closed) * 100, 1) : 0;
    
    $reportData['team_performance'][] = [
        'id' => $performer['id'],
        'name' => $performer['name'],
        'total_leads' => $performer['total_leads'],
        'presentations' => $performer['presentations'],
        'closed_deals' => $performer['closed_deals'],
        'conversion_rate' => $performer['conversion_rate'],
        'total_value' => $performer['total_value'],
        'leads_percentage' => $leads_percentage,
        'presentations_percentage' => $presentations_percentage,
        'closed_percentage' => $closed_percentage
    ];
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

// Function to format percentage display
function formatPercentageDisplay($label, $count, $percentage) {
    return $label . "\n" . number_format($count) . " (" . $percentage . "%)";
}

// Function to get chart data with percentages for JavaScript
function getChartDataWithPercentages($data, $labelKey, $countKey) {
    $result = [
        'labels' => [],
        'data' => [],
        'percentages' => []
    ];
    
    foreach ($data as $item) {
        $result['labels'][] = $item[$labelKey];
        $result['data'][] = $item[$countKey];
        $result['percentages'][] = $item['percentage'];
    }
    
    return $result;
}

// Prepare chart data with percentages
$statusChartData = getChartDataWithPercentages($reportData['status_distribution'], 'status', 'count');
$temperatureChartData = getChartDataWithPercentages($reportData['temperature_distribution'], 'temperature', 'count');
$projectsChartData = getChartDataWithPercentages($reportData['top_projects'], 'developer', 'count');
$modelsChartData = getChartDataWithPercentages($reportData['top_models'], 'project_model', 'count');
$sourcesChartData = getChartDataWithPercentages($reportData['top_sources'], 'source', 'count');
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

    /* PRINT NOTE STYLES */
    .print-note {
        display: none; /* Hidden on screen */
        font-style: italic;
        text-align: center;
        margin: 15px 0;
        padding: 10px;
        font-size: 10pt;
        color: #666;
        border-top: 1px solid #ccc;
        border-bottom: 1px solid #ccc;
        background: #f9f9f9;
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

    /* Charts - Screen Layout (2x2 grid) */
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

    /* IMPROVED: Better circular chart sizing and layout for screen view */
    .small-chart-container {
        background: linear-gradient(135deg, var(--white) 0%, var(--secondary) 100%);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        padding: 1.25rem;
        height: 400px; /* INCREASED: Much larger container */
        transition: var(--transition);
        border: 1px solid var(--gray-light);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .small-chart-container .chart-body {
        height: calc(100% - 3rem);
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    /* SPECIFIC IMPROVEMENTS FOR CIRCULAR CHARTS */
    #statusChart,
    #temperatureChart {
        max-width: 320px !important;
        max-height: 320px !important;
        width: 320px !important;
        height: 320px !important;
    }

    .small-chart-container:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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

    /* FIRST PAGE CHARTS */
    .first-page-charts {
        margin: 20px 0;
        page-break-inside: avoid;
        page-break-after: avoid;
    }

    .circular-charts-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1rem;
    }

    /* IMPROVED: Better chart containers for screen view */
    .first-page-charts .chart-container {
        background: linear-gradient(135deg, var(--white) 0%, var(--secondary) 100%);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        padding: 1.5rem;
        height: 450px; /* INCREASED: Larger height for better display */
        transition: var(--transition);
        border: 1px solid var(--gray-light);
        display: flex;
        flex-direction: column;
    }

    .first-page-charts .chart-container:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .first-page-charts .chart-body {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        min-height: 350px;
    }

    /* SPECIFIC IMPROVEMENTS FOR CIRCULAR CHARTS ON SCREEN */
    .first-page-charts #statusChart,
    .first-page-charts #temperatureChart {
        max-width: 350px !important;
        max-height: 350px !important;
        width: 350px !important;
        height: 350px !important;
    }

    /* PAGE BREAK */
    .page-break-before {
        page-break-before: always;
    }

    /* REMAINING CHARTS */
    .remaining-charts-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    /* IMPROVED: Better chart containers for remaining charts */
    .remaining-charts-grid .chart-container {
        background: linear-gradient(135deg, var(--white) 0%, var(--secondary) 100%);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        padding: 1.5rem;
        height: 400px; /* INCREASED: Better height */
        transition: var(--transition);
        border: 1px solid var(--gray-light);
        display: flex;
        flex-direction: column;
    }

    .remaining-charts-grid .chart-container:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .remaining-charts-grid .chart-body {
        flex: 1;
        position: relative;
        min-height: 300px;
    }

    /* UPDATED PRINT STYLES - HIDE HEADER, SIDEBAR AND TOGGLE BUTTONS */
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
        
        /* HIDE ALL NAVIGATION ELEMENTS INCLUDING HEADER, SIDEBAR AND TOGGLES */
        .container > .sidebar,
        .main-content > .header,
        .sidebar,
        .header,
        nav,
        .navbar,
        .navigation,
        .menu,
        .toggle,
        .toggle-btn,
        .sidebar-toggle,
        .menu-toggle,
        .hamburger,
        .nav-toggle,
        [class*="toggle"],
        [class*="sidebar"],
        [class*="header"],
        [class*="nav"],
        .report-filters,
        .report-actions,
        .btn,
        .no-data,
        .chart-actions {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
        
        /* FORCE BODY TO START IMMEDIATELY */
        body {
            font-family: 'Arial', sans-serif !important;
            font-size: 11pt !important;
            line-height: 1.3 !important;
            color: #000 !important;
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .container {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            page-break-before: avoid !important;
        }

        .main-content {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            page-break-before: avoid !important;
        }
        
        /* FORCE SHOW ONLY REPORT CONTENT - START IMMEDIATELY */
        .reports-page {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: auto !important;
        }
        
        /* FIXED: PAGE HEADER - COMPACT AND IMMEDIATE */
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
            border: 2px solid #000 !important;
            padding: 4px 8px !important;
            margin: 2px 6px !important;
            border-radius: 3px !important;
            font-weight: bold !important;
        }
        
        /* SHOW PRINT NOTE ONLY IN PRINT */
        .print-note {
            display: block !important;
            font-style: italic !important;
            text-align: center !important;
            margin: 10px 0 15px 0 !important;
            padding: 8px !important;
            font-size: 9pt !important;
            color: #000 !important;
            border-top: 1px solid #000 !important;
            border-bottom: 1px solid #000 !important;
            background: #f5f5f5 !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
        }
        
        /* FIXED: SUMMARY CARDS - COMPACT AND IMMEDIATE */
        .report-summary {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 10px !important;
            margin: 15px 0 !important;
            padding: 0 !important;
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
            border-radius: 6px !important;
            height: 70px !important;
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
            letter-spacing: 0.3px !important;
        }
        
        .summary-info p {
            font-size: 14pt !important;
            font-weight: bold !important;
            color: #000 !important;
            margin: 0 !important;
        }
        
        /* FIXED: TEAM PERFORMANCE TABLE - NO OVERFLOW */
        .card {
            display: block !important;
            border: 2px solid #000 !important;
            margin: 15px 0 25px 0 !important;
            padding: 0 !important;
            border-radius: 6px !important;
            background: #fff !important;
            page-break-inside: avoid !important;
            page-break-before: avoid !important;
            width: 100% !important;
        }
        
        .card + .first-page-charts {
            margin-top: 35px !important;
            border-top: 2px solid #ccc !important;
            padding-top: 15px !important;
        }
        
        .card-body {
            padding: 8px !important;
        }
        
        /* FIXED: TABLE WITH PROPER ALIGNMENT - ADJUSTED COLUMN WIDTHS AND FONT SIZES */
        .table-container {
            display: block !important;
            overflow: visible !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .table {
            display: table !important;
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 0 !important;
            font-size: 8pt !important;
            table-layout: fixed !important;
            border-spacing: 0 !important;
        }

        .table thead {
            display: table-header-group !important;
        }

        .table tbody {
            display: table-row-group !important;
        }

        .table tr {
            display: table-row !important;
            page-break-inside: avoid !important;
            width: 100% !important;
        }

        .table th {
            display: table-cell !important;
            background: #d0d0d0 !important;
            border: 1px solid #000 !important;
            padding: 6px 4px !important;
            text-align: center !important;
            font-weight: bold !important;
            color: #000 !important;
            font-size: 7pt !important;
            text-transform: uppercase !important;
            letter-spacing: 0.2px !important;
            vertical-align: middle !important;
            word-wrap: break-word !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
        }

        .table th:nth-child(1) { width: 20% !important; }
        .table th:nth-child(2) { width: 16% !important; }
        .table th:nth-child(3) { width: 16% !important; }
        .table th:nth-child(4) { width: 16% !important; }
        .table th:nth-child(5) { width: 16% !important; }
        .table th:nth-child(6) { width: 16% !important; }

        .table td {
            display: table-cell !important;
            border: 1px solid #000 !important;
            padding: 6px 4px !important;
            text-align: center !important;
            font-size: 7pt !important;
            vertical-align: middle !important;
            color: #000 !important;
            word-wrap: break-word !important;
            overflow: hidden !important;
            line-height: 1.2 !important;
            box-sizing: border-box !important;
        }

        .table td:nth-child(1) { 
            width: 20% !important; 
            text-align: left !important;
            padding-left: 6px !important;
        }
        .table td:nth-child(2) { width: 16% !important; }
        .table td:nth-child(3) { width: 16% !important; }
        .table td:nth-child(4) { width: 16% !important; }
        .table td:nth-child(5) { width: 16% !important; }
        .table td:nth-child(6) { width: 16% !important; }

        .table tbody tr:nth-child(even) {
            background: #f8f8f8 !important;
        }

        .table tbody tr:nth-child(odd) {
            background: #fff !important;
        }

        .table td.name {
            text-align: left !important;
            font-weight: bold !important;
            padding-left: 6px !important;
        }

        .table td small {
            font-size: 6pt !important;
            display: block !important;
            margin-top: 1px !important;
            line-height: 1.1 !important;
        }

        .table td .metric-value {
            display: block !important;
            text-align: center !important;
            color: #000 !important;
            line-height: 1.1 !important;
            width: 100% !important;
        }

        .table td .metric-value small {
            font-size: 6pt !important;
            color: #666 !important;
            font-weight: normal !important;
            display: block !important;
            margin-top: 1px !important;
        }

        /* ENSURE PROPER TABLE STRUCTURE */
        .card {
            display: block !important;
            border: 2px solid #000 !important;
            margin: 15px 0 25px 0 !important;
            padding: 0 !important;
            border-radius: 6px !important;
            background: #fff !important;
            page-break-inside: avoid !important;
            page-break-before: avoid !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* FIXED: FIRST PAGE CIRCULAR CHARTS - PROPERLY CENTERED AND ALIGNED */
        .first-page-charts {
            display: block !important;
            page-break-before: avoid !important;
            page-break-after: avoid !important;
            page-break-inside: avoid !important;
            margin: 25px 0 15px 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .circular-charts-row {
            display: flex !important;
            justify-content: space-between !important;
            align-items: flex-start !important;
            gap: 20px !important;
            margin: 0 !important;
            page-break-inside: avoid !important;
            width: 100% !important;
        }

        .first-page-charts .chart-container {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-start !important;
            width: 48% !important;
            height: 280px !important;
            border: 2px solid #000 !important;
            padding: 10px !important;
            background: #fff !important;
            border-radius: 6px !important;
            page-break-inside: avoid !important;
            position: relative !important;
            box-sizing: border-box !important;
        }

        .first-page-charts .chart-title {
            display: block !important;
            font-size: 9pt !important;
            font-weight: bold !important;
            color: #000 !important;
            margin: 0 0 8px 0 !important;
            text-transform: uppercase !important;
            text-align: center !important;
            letter-spacing: 0.5px !important;
            padding: 4px 0 !important;
            border-bottom: 1px solid #ccc !important;
            width: 100% !important;
        }

        .first-page-charts .chart-body {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            height: calc(100% - 40px) !important;
            flex: 1 !important;
            position: relative !important;
        }

        /* FIXED: CIRCULAR CHARTS - PROPER SIZING AND CENTERING */
        .first-page-charts #statusChart,
        .first-page-charts #temperatureChart {
            width: 180px !important;
            height: 180px !important;
            max-width: 180px !important;
            max-height: 180px !important;
            aspect-ratio: 1 / 1 !important;
            object-fit: contain !important;
            margin: 0 auto !important;
            display: block !important;
        }

        /* ENSURE CANVAS ELEMENTS ARE PROPERLY CONTAINED */
        .first-page-charts canvas {
            display: block !important;
            width: 180px !important;
            height: 180px !important;
            max-width: 180px !important;
            max-height: 180px !important;
            margin: 0 auto !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
        }
        
        /* PAGE 2: REMAINING CHARTS */
        .page-break-before {
            page-break-before: always !important;
        }
        
        .remaining-charts-grid {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 25px !important;
            margin: 25px 0 !important;
            padding: 0 !important;
        }
        
        .remaining-charts-grid .chart-container {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-start !important;
            width: 100% !important;
            height: 350px !important;
            border: 2px solid #000 !important;
            padding: 15px !important;
            background: #fff !important;
            border-radius: 6px !important;
            page-break-inside: avoid !important;
            position: relative !important;
        }
        
        .remaining-charts-grid .chart-title {
            display: block !important;
            font-size: 11pt !important;
            font-weight: bold !important;
            color: #000 !important;
            margin: 0 0 15px 0 !important;
            text-transform: uppercase !important;
            text-align: center !important;
            letter-spacing: 0.5px !important;
            padding: 8px 0 !important;
            border-bottom: 2px solid #000 !important;
            width: 100% !important;
        }
        
        .remaining-charts-grid .chart-body {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            height: calc(100% - 60px) !important;
            flex: 1 !important;
        }
        
        /* FIXED: ALL CANVAS ELEMENTS - PROPER SIZING */
        canvas {
            display: block !important;
            max-width: 100% !important;
            margin: 0 auto !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        /* SPECIFIC SIZING FOR DIFFERENT CHART TYPES */
        #statusChart,
        #temperatureChart {
            width: 200px !important;
            height: 200px !important;
            aspect-ratio: 1 / 1 !important;
            object-fit: contain !important;
        }
        
        #projectsChart,
        #modelsChart,
        #sourcesChart,
        #performanceChart {
            width: 100% !important;
            height: 250px !important;
            max-width: 500px !important;
        }
        
        /* HIDE ALL ICONS */
        .fas, .far, .fab, i[class*="fa"] {
            display: none !important;
        }
        
        /* METRIC BADGES WITH BLACK TEXT */
        .metric-badge {
            padding: 1px 3px !important;
            border-radius: 2px !important;
            font-size: 6pt !important;
            font-weight: bold !important;
            border: 1px solid #000 !important;
            color: #000 !important;
            background: #fff !important;
        }
        
        .metric-value {
            display: block !important;
            text-align: center !important;
            color: #000 !important;
        }
        
        .d-flex {
            display: block !important;
        }
        
        /* FIXED: ENSURE CHART TITLES ARE VISIBLE IN PRINT */
        .chart-title,
        .first-page-charts .chart-title,
        .remaining-charts-grid .chart-title {
            display: block !important;
            visibility: visible !important;
            font-size: 11pt !important;
            font-weight: bold !important;
            color: #000 !important;
            margin: 0 0 15px 0 !important;
            text-transform: uppercase !important;
            text-align: center !important;
            letter-spacing: 0.5px !important;
            padding: 8px 0 !important;
            border-bottom: 2px solid #000 !important;
            width: 100% !important;
            background: #fff !important;
            opacity: 1 !important;
            height: auto !important;
            overflow: visible !important;
        }

        /* FORCE CHART HEADER TO BE VISIBLE */
        .chart-header,
        .first-page-charts .chart-header,
        .remaining-charts-grid .chart-header {
            display: block !important;
            visibility: visible !important;
            width: 100% !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            opacity: 1 !important;
            overflow: visible !important;
        }

        /* ENSURE CHART CONTAINERS SHOW TITLES */
        .first-page-charts .chart-container,
        .remaining-charts-grid .chart-container {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            justify-content: flex-start !important;
            width: 100% !important;
            border: 2px solid #000 !important;
            padding: 15px !important;
            background: #fff !important;
            border-radius: 6px !important;
            page-break-inside: avoid !important;
            position: relative !important;
        }
        
        /* OVERRIDE ANY HIDING RULES FOR CHART ELEMENTS */
        .chart-title,
        .chart-header,
        h3.chart-title,
        .first-page-charts h3,
        .remaining-charts-grid h3 {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            height: auto !important;
            width: 100% !important;
            margin: 0 0 10px 0 !important;
            padding: 5px 0 !important;
            color: #000 !important;
            font-size: 10pt !important;
            font-weight: bold !important;
            text-align: center !important;
            text-transform: uppercase !important;
            border-bottom: 1px solid #ccc !important;
            background: #fff !important;
        }
    
    /* REMOVE SCROLL BAR IN PRINT */
    
    /* HIDE SCROLL BARS */
    html, body {
        overflow: hidden !important;
        height: auto !important;
        max-height: none !important;
    }
    
    /* ENSURE NO OVERFLOW ON CONTAINERS */
    .container,
    .main-content,
    .reports-page {
        overflow: visible !important;
        height: auto !important;
        max-height: none !important;
    }
    
    /* HIDE ANY SCROLL BARS */
    ::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
    }
    
    * {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
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
    // FIXED: Ensure chart data is properly passed and updated with percentages
    const reportData = {
        status_distribution: <?php echo json_encode($reportData['status_distribution'] ?? []); ?>,
        temperature_distribution: <?php echo json_encode($reportData['temperature_distribution'] ?? []); ?>,
        top_projects: <?php echo json_encode($reportData['top_projects'] ?? []); ?>,
        top_models: <?php echo json_encode($reportData['top_models'] ?? []); ?>,
        top_sources: <?php echo json_encode($reportData['top_sources'] ?? []); ?>,
        team_performance: <?php echo json_encode($reportData['team_performance'] ?? []); ?>
    };

    // Chart data with percentages
    const statusChartData = <?php echo json_encode($statusChartData); ?>;
    const temperatureChartData = <?php echo json_encode($temperatureChartData); ?>;
    const projectsChartData = <?php echo json_encode($projectsChartData); ?>;
    const modelsChartData = <?php echo json_encode($modelsChartData); ?>;
    const sourcesChartData = <?php echo json_encode($sourcesChartData); ?>;

    console.log('Report Data with Percentages:', reportData);

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
                <div class="page-header">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-chart-line"></i> QUARTERLY LEADS REPORTS
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
                
                <!-- ADD PRINT NOTE HERE -->
                <div class="print-note">
                    <em>This report analysis is exported from the Lead Management System (LMS). It provides a detailed summary of each individual or agent's performance, including the number of leads handled, follow-ups conducted, lead statuses, and overall conversion rates. The purpose of this report is to track productivity, identify strengths and weaknesses, and support better decision-making through data-driven insights.</em>
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
<!-- PROPERLY ORDERED PRINT LAYOUT -->
<div class="print-order-wrapper">
    <!-- 1. EXECUTIVE SUMMARY CARDS - FIRST -->
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
    <!-- 2. TEAM PERFORMANCE TABLE - SECOND -->
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
                            <th data-column="total_leads">Total Leads (%)</th>
                            <th data-column="presentations">Presentations (%)</th>
                            <th data-column="closed_deals">Closed Deals (%)</th>
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
                                    <small>(<?php echo $performer['leads_percentage']; ?>%)</small>
                                </div>
                            </td>
                            <td>
                                <div class="metric-value">
                                    <i class="fas fa-handshake me-2 text-info"></i>
                                    <?php echo number_format($performer['presentations']); ?>
                                    <small>(<?php echo $performer['presentations_percentage']; ?>%)</small>
                                </div>
                            </td>
                            <td>
                                <div class="metric-value">
                                    <i class="fas fa-check-circle me-2 text-success"></i>
                                    <?php echo number_format($performer['closed_deals']); ?>
                                    <small>(<?php echo $performer['closed_percentage']; ?>%)</small>
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
    <!-- 3. FIRST PAGE CIRCULAR CHARTS - THIRD -->
    <div class="first-page-charts">
        <div class="circular-charts-row">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">LEAD STATUS DISTRIBUTION</h3>
                </div>
                <div class="chart-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">LEAD TEMPERATURE</h3>
                </div>
                <div class="chart-body">
                    <canvas id="temperatureChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($hasCharts): ?>
<!-- 4. PAGE 2: REMAINING CHARTS - FOURTH (SEPARATE PAGE) -->
<div class="page-break-before">
    <div class="remaining-charts-grid">
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">TOP PROJECTS</h3>
            </div>
            <div class="chart-body">
                <canvas id="projectsChart"></canvas>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">TOP MODELS INQUIRED</h3>
            </div>
            <div class="chart-body">
                <canvas id="modelsChart"></canvas>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">LEAD SOURCES ANALYSIS</h3>
            </div>
            <div class="chart-body">
                <canvas id="sourcesChart"></canvas>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">PERFORMANCE OVERVIEW</h3>
            </div>
            <div class="chart-body">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
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
    // FIXED PRINT FUNCTION - PROPER CHART ORIENTATION AND BLACK TEXT
    function printReport() {
        console.log('Preparing print layout with fixed chart orientation...');
        
        // Set print-optimized chart configurations
        Chart.defaults.font.size = 10;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.legend.labels.boxWidth = 12;
        Chart.defaults.plugins.legend.labels.padding = 8;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.font = {
            size: 10,
            weight: 'bold'
        };
        
        // Force chart resize for print with proper aspect ratio
        const charts = document.querySelectorAll('canvas');
        const chartPromises = [];
        
        charts.forEach((canvas, index) => {
            if (canvas.chart) {
                // FIXED: Proper sizing for circular charts
                if (canvas.id === 'statusChart' || canvas.id === 'temperatureChart') {
                    canvas.chart.resize(200, 200);
                    canvas.chart.options.maintainAspectRatio = true;
                    canvas.chart.options.aspectRatio = 1;
                    canvas.chart.options.plugins.legend.labels.font.size = 8;
                } else {
                    canvas.chart.resize(500, 250);
                    canvas.chart.options.maintainAspectRatio = false;
                }
                
                const promise = new Promise((resolve) => {
                    setTimeout(() => {
                        canvas.chart.update('none');
                        resolve();
                    }, 200 * index);
                });
                chartPromises.push(promise);
            }
        });
        
        // Wait for charts to render, then print
        Promise.all(chartPromises).then(() => {
            setTimeout(() => {
                console.log('Initiating print with fixed chart orientation and black text...');
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

    // FIXED: Chart initialization function with proper percentages inside bars
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

        // FIXED: Status Chart with black text for percentages
        if (hasValidChartData(reportData.status_distribution)) {
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: statusChartData.labels,
                        datasets: [{
                            data: statusChartData.data,
                            backgroundColor: colorSchemes.status,
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverBorderWidth: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        aspectRatio: 1,
                        cutout: '40%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    },
                                    boxWidth: 15,
                                    boxHeight: 15,
                                    generateLabels: function(chart) {
                                        const data = chart.data;
                                        if (data.labels.length && data.datasets.length) {
                                            return data.labels.map((label, i) => {
                                                const percentage = statusChartData.percentages[i];
                                                return {
                                                    text: `${label} (${percentage}%)`,
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    strokeStyle: data.datasets[0].borderColor,
                                                    lineWidth: data.datasets[0].borderWidth,
                                                    hidden: false,
                                                    index: i
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.9)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#2563eb',
                                borderWidth: 2,
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        const percentage = statusChartData.percentages[context.dataIndex];
                                        return `${context.label}: ${context.parsed} leads (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'centerText',
                        afterDatasetsDraw: function(chart) {
                            const ctx = chart.ctx;
                            
                            chart.data.datasets.forEach((dataset, i) => {
                                const meta = chart.getDatasetMeta(i);
                                meta.data.forEach((element, index) => {
                                    const percentage = statusChartData.percentages[index];
                                    const position = element.tooltipPosition();
                                    
                                    // FIXED: Black text with white stroke for better visibility
                                    ctx.fillStyle = '#000';
                                    ctx.strokeStyle = '#fff';
                                    ctx.lineWidth = 3;
                                    ctx.font = 'bold 14px Arial';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'middle';
                                    
                                    ctx.strokeText(percentage + '%', position.x, position.y);
                                    ctx.fillText(percentage + '%', position.x, position.y);
                                });
                            });
                        }
                    }]
                });
            }
        }

        // FIXED: Temperature Chart with black text for percentages
        if (hasValidChartData(reportData.temperature_distribution)) {
            const tempCtx = document.getElementById('temperatureChart');
            if (tempCtx) {
                new Chart(tempCtx, {
                    type: 'pie',
                    data: {
                        labels: temperatureChartData.labels,
                        datasets: [{
                            data: temperatureChartData.data,
                            backgroundColor: colorSchemes.temperature,
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverBorderWidth: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        aspectRatio: 1,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    },
                                    boxWidth: 15,
                                    boxHeight: 15,
                                    generateLabels: function(chart) {
                                        const data = chart.data;
                                        if (data.labels.length && data.datasets.length) {
                                            return data.labels.map((label, i) => {
                                                const percentage = temperatureChartData.percentages[i];
                                                return {
                                                    text: `${label} (${percentage}%)`,
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    strokeStyle: data.datasets[0].borderColor,
                                                    lineWidth: data.datasets[0].borderWidth,
                                                    hidden: false,
                                                    index: i
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.9)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#2563eb',
                                borderWidth: 2,
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        const percentage = temperatureChartData.percentages[context.dataIndex];
                                        return `${context.label}: ${context.parsed} leads (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'centerText',
                        afterDatasetsDraw: function(chart) {
                            const ctx = chart.ctx;
                            
                            chart.data.datasets.forEach((dataset, i) => {
                                const meta = chart.getDatasetMeta(i);
                                meta.data.forEach((element, index) => {
                                    const percentage = temperatureChartData.percentages[index];
                                    const position = element.tooltipPosition();
                                    
                                    // FIXED: Black text with white stroke for better visibility
                                    ctx.fillStyle = '#000';
                                    ctx.strokeStyle = '#fff';
                                    ctx.lineWidth = 3;
                                    ctx.font = 'bold 14px Arial';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'middle';
                                    
                                    ctx.strokeText(percentage + '%', position.x, position.y);
                                    ctx.fillText(percentage + '%', position.x, position.y);
                                });
                            });
                        }
                    }]
                });
            }
        }

        // FIXED: Initialize Projects Chart with percentages INSIDE bars
        if (hasValidChartData(reportData.top_projects)) {
            const projectsCtx = document.getElementById('projectsChart');
            if (projectsCtx) {
                new Chart(projectsCtx, {
                    type: 'bar',
                    data: {
                        labels: projectsChartData.labels,
                        datasets: [{
                            label: 'Leads',
                            data: projectsChartData.data,
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
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        const percentage = projectsChartData.percentages[context.dataIndex];
                                        return `${context.label}: ${context.parsed} (${percentage}%)`;
                                    }
                                }
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
                                        size: 11,
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
                                        size: 11,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'datalabels',
                        afterDatasetsDraw: function(chart) {
                            const ctx = chart.ctx;
                            chart.data.datasets.forEach((dataset, i) => {
                                const meta = chart.getDatasetMeta(i);
                                meta.data.forEach((element, index) => {
                                    const percentage = projectsChartData.percentages[index];
                                    
                                    // FIXED: Position percentage INSIDE the bar
                                    const barHeight = element.height;
                                    const barTop = element.y;
                                    const barBottom = element.base;
                                    const centerY = barTop + (barHeight / 2);
                                    
                                    // White text with black stroke for visibility inside colored bars
                                    ctx.fillStyle = '#fff';
                                    ctx.strokeStyle = '#000';
                                    ctx.lineWidth = 2;
                                    ctx.font = 'bold 12px Arial';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'middle';
                                    
                                    // Only show percentage if bar is tall enough
                                    if (barHeight > 20) {
                                        ctx.strokeText(percentage + '%', element.x, centerY);
                                        ctx.fillText(percentage + '%', element.x, centerY);
                                    }
                                });
                            });
                        }
                    }]
                });
            }
        }

        // FIXED: Initialize Models Chart with percentages INSIDE bars
        if (hasValidChartData(reportData.top_models)) {
            const modelsCtx = document.getElementById('modelsChart');
            if (modelsCtx) {
                new Chart(modelsCtx, {
                    type: 'bar',
                    data: {
                        labels: modelsChartData.labels,
                        datasets: [{
                            label: 'Inquiries',
                            data: modelsChartData.data,
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
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        const percentage = modelsChartData.percentages[context.dataIndex];
                                        return `${context.label}: ${context.parsed} (${percentage}%)`;
                                    }
                                }
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
                                        size: 11,
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
                                        size: 11,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'datalabels',
                        afterDatasetsDraw: function(chart) {
                            const ctx = chart.ctx;
                            chart.data.datasets.forEach((dataset, i) => {
                                const meta = chart.getDatasetMeta(i);
                                meta.data.forEach((element, index) => {
                                    const percentage = modelsChartData.percentages[index];
                                    
                                    // FIXED: Position percentage INSIDE the bar
                                    const barHeight = element.height;
                                    const barTop = element.y;
                                    const barBottom = element.base;
                                    const centerY = barTop + (barHeight / 2);
                                    
                                    // White text with black stroke for visibility inside colored bars
                                    ctx.fillStyle = '#fff';
                                    ctx.strokeStyle = '#000';
                                    ctx.lineWidth = 2;
                                    ctx.font = 'bold 12px Arial';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'middle';
                                    
                                    // Only show percentage if bar is tall enough
                                    if (barHeight > 20) {
                                        ctx.strokeText(percentage + '%', element.x, centerY);
                                        ctx.fillText(percentage + '%', element.x, centerY);
                                    }
                                });
                            });
                        }
                    }]
                });
            }
        }

        // FIXED: Initialize Sources Chart with percentages INSIDE bars
        if (hasValidChartData(reportData.top_sources)) {
            const sourcesCtx = document.getElementById('sourcesChart');
            if (sourcesCtx) {
                new Chart(sourcesCtx, {
                    type: 'bar',
                    data: {
                        labels: sourcesChartData.labels,
                        datasets: [{
                            label: 'Leads',
                            data: sourcesChartData.data,
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
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        const percentage = sourcesChartData.percentages[context.dataIndex];
                                        return `${context.label}: ${context.parsed} (${percentage}%)`;
                                    }
                                }
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
                                        size: 11,
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
                                        size: 11,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'datalabels',
                        afterDatasetsDraw: function(chart) {
                            const ctx = chart.ctx;
                            chart.data.datasets.forEach((dataset, i) => {
                                const meta = chart.getDatasetMeta(i);
                                meta.data.forEach((element, index) => {
                                    const percentage = sourcesChartData.percentages[index];
                                    
                                    // FIXED: Position percentage INSIDE the bar
                                    const barHeight = element.height;
                                    const barTop = element.y;
                                    const barBottom = element.base;
                                    const centerY = barTop + (barHeight / 2);
                                    
                                    // White text with black stroke for visibility inside colored bars
                                    ctx.fillStyle = '#fff';
                                    ctx.strokeStyle = '#000';
                                    ctx.lineWidth = 2;
                                    ctx.font = 'bold 12px Arial';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'middle';
                                    
                                    // Only show percentage if bar is tall enough
                                    if (barHeight > 20) {
                                        ctx.strokeText(percentage + '%', element.x, centerY);
                                        ctx.fillText(percentage + '%', element.x, centerY);
                                    }
                                });
                            });
                        }
                    }]
                });
            }
        }

        // FIXED: Initialize Performance Overview Chart with total leads count in blue bars and conversion rate percentages in green bars
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
                                        size: 11,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#2563eb',
                                borderWidth: 1,
                                callbacks: {
                                    afterLabel: function(context) {
                                        const memberData = reportData.team_performance[context.dataIndex];
                                        if (context.datasetIndex === 0) {
                                            return `(${memberData.leads_percentage}% of total leads)`;
                                        } else if (context.datasetIndex === 1) {
                                            return `(${memberData.closed_percentage}% of total closed deals)`;
                                        }
                                        return '';
                                    }
                                }
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
                                        size: 11,
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
                                        size: 11,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'datalabels',
                        afterDatasetsDraw: function(chart) {
                            const ctx = chart.ctx;
                            chart.data.datasets.forEach((dataset, datasetIndex) => {
                                const meta = chart.getDatasetMeta(datasetIndex);
                                meta.data.forEach((element, index) => {
                                    const memberData = reportData.team_performance[index];
                                    
                                    // FIXED: Position values INSIDE the bars
                                    const barHeight = element.height;
                                    const barTop = element.y;
                                    const centerY = barTop + (barHeight / 2);
                                    
                                    // White text with black stroke for visibility inside colored bars
                                    ctx.fillStyle = '#fff';
                                    ctx.strokeStyle = '#000';
                                    ctx.lineWidth = 2;
                                    ctx.font = 'bold 11px Arial';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'middle';
                                    
                                    let displayText = '';
                                    
                                    // FIXED: Show total leads count in blue bars (dataset 0) and conversion rate percentage in green bars (dataset 1)
                                    if (datasetIndex === 0) {
                                        // Blue bars - show total leads count
                                        displayText = memberData.total_leads.toString();
                                    } else if (datasetIndex === 1) {
                                        // Green bars - show conversion rate percentage
                                        displayText = memberData.conversion_rate + '%';
                                    }
                                    
                                    // Only show text if bar is tall enough
                                    if (barHeight > 20 && displayText) {
                                        ctx.strokeText(displayText, element.x, centerY);
                                        ctx.fillText(displayText, element.x, centerY);
                                    }
                                });
                            });
                        }
                    }]
                });
            }
        }

        console.log('All charts initialized successfully with percentages inside bars');
    }
    </script>
</body>
</html>
