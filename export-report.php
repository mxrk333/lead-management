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

// Get parameters
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil(date('n') / 3);
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$team_id = isset($_GET['team_id']) ? $_GET['team_id'] : 'all';
$team_member = isset($_GET['team_member']) ? $_GET['team_member'] : null;

// Calculate date range
if ($month > 0) {
    $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$year-$month-01"));
} else {
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3;
    $start_date = "$year-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = "$year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$year-$end_month-01"));
}

// Build query based on parameters
$query = "SELECT 
    u.name as agent_name,
    COUNT(DISTINCT l.id) as total_leads,
    SUM(CASE WHEN la.activity_type = 'Presentation' THEN 1 ELSE 0 END) as presentations,
    COUNT(DISTINCT CASE WHEN l.status = 'Closed' THEN l.id END) as closed_deals,
    ROUND(COUNT(DISTINCT CASE WHEN l.status = 'Closed' THEN l.id END) / COUNT(DISTINCT l.id) * 100, 1) as conversion_rate
FROM 
    users u
LEFT JOIN 
    leads l ON l.user_id = u.id AND l.created_at BETWEEN ? AND ?
LEFT JOIN 
    lead_activities la ON la.lead_id = l.id
WHERE 1=1";

// Add team filter
if ($team_id != 'all') {
    $query .= " AND u.team_id = ?";
}

// Add team member filter
if ($team_member) {
    $query .= " AND u.id = ?";
}

$query .= " GROUP BY u.id, u.name ORDER BY u.name";

// Prepare and execute query
$stmt = $conn->prepare($query);

if ($team_id != 'all' && $team_member) {
    $stmt->bind_param("ssii", $start_date, $end_date, $team_id, $team_member);
} elseif ($team_id != 'all') {
    $stmt->bind_param("ssi", $start_date, $end_date, $team_id);
} elseif ($team_member) {
    $stmt->bind_param("ssi", $start_date, $end_date, $team_member);
} else {
    $stmt->bind_param("ss", $start_date, $end_date);
}

$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="performance_report_' . $year . 'Q' . $quarter . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, array(
    'Agent Name',
    'Total Leads',
    'Presentations',
    'Closed Deals',
    'Conversion Rate (%)'
));

// Write data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, array(
        $row['agent_name'],
        $row['total_leads'],
        $row['presentations'],
        $row['closed_deals'],
        $row['conversion_rate']
    ));
}

// Close file handle
fclose($output);

// Close database connection
$conn->close();
exit(); 