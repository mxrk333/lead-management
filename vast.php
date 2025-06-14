<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$conn = getDbConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
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
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$quarter = isset($_GET['quarter']) ? $_GET['quarter'] : ceil(date('n') / 3);

// Get report data
$reportData = getQuarterlyReport($user_id, $user['role'], $year, $quarter);

// Get team members if user is a manager
$teamMembers = [];
if ($user['role'] == 'manager') {
    $teamMembers = getTeamMembers($user_id);
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$team_filter = isset($_GET['team']) ? $_GET['team'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query to get users based on permissions
$query = "SELECT u.*, t.name as team_name FROM users u 
          LEFT JOIN teams t ON u.team_id = t.id 
          WHERE 1=1 ";

// Apply permission filters
if ($user['role'] == 'manager') {
    $query .= "AND u.team_id = (SELECT team_id FROM users WHERE id = $user_id) ";
} elseif ($user['role'] == 'supervisor') {
    $query .= "AND u.team_id = (SELECT team_id FROM users WHERE id = $user_id) ";
}

// Apply search filter
if (!empty($search)) {
    $query .= "AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.username LIKE '%$search%') ";
}

// Apply role filter
if (!empty($role_filter)) {
    $query .= "AND u.role = '$role_filter' ";
}

// Apply team filter
if (!empty($team_filter)) {
    $query .= "AND u.team_id = $team_filter ";
}

// Order by
$query .= "ORDER BY u.name ASC";

// Execute query
$result = mysqli_query($conn, $query);
$users = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

// Get all teams for filter
$teams_query = "SELECT * FROM teams ORDER BY name ASC";
$teams_result = mysqli_query($conn, $teams_query);
$teams = [];
if ($teams_result) {
    while ($row = mysqli_fetch_assoc($teams_result)) {
        $teams[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Inner SPARC Realty Coporation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            <h1>iloveyou</h1>
</body>
</html>