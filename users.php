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
<style>.filter-section {
    background-color: #f8f9fc;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
    margin-bottom: 10px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #4a5568;
    font-size: 14px;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background-color: #fff;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.filter-group input:focus,
.filter-group select:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1);
    outline: none;
}

.filter-group button,
.filter-group .btn {
    padding: 10px 16px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #4e73df;
    color: white;
    border: none;
}

.btn-primary:hover {
    background-color: #3a5ccc;
}

.btn-secondary {
    background-color: #f8f9fc;
    color: #5a5c69;
    border: 1px solid #e2e8f0;
    margin-left: 8px;
}

.btn-secondary:hover {
    background-color: #eaecf4;
}

/* Table Styles */
.table-responsive {
    overflow-x: auto;
    margin-bottom: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    font-size: 14px;
}

.data-table th {
    background-color: #f8f9fc;
    color: #4a5568;
    font-weight: 600;
    text-align: left;
    padding: 15px;
    border-bottom: 2px solid #e2e8f0;
}

.data-table td {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    color: #5a5c69;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background-color: #f8f9fc;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-admin {
    background-color: #4e73df;
    color: white;
}

.badge-manager {
    background-color: #1cc88a;
    color: white;
}

.badge-supervisor {
    background-color: #36b9cc;
    color: white;
}

.badge-agent {
    background-color: #f6c23e;
    color: white;
}

.actions {
    white-space: nowrap;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background-color: #f8f9fc;
    color: #5a5c69;
    margin-right: 5px;
    transition: all 0.2s;
}

.btn-icon:hover {
    background-color: #eaecf4;
    color: #4e73df;
}

.btn-icon.btn-danger {
    color: #e74a3b;
}

.btn-icon.btn-danger:hover {
    background-color: #fdeaea;
}

.no-data {
    text-align: center;
    padding: 30px;
    color: #858796;
    font-style: italic;
}

/* Stats Section */
.stats-section {
    margin-bottom: 25px;
}

.stats-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}

.stats-card {
    flex: 1;
    min-width: 200px;
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-3px);
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 20px;
}

.stats-card:nth-child(1) .stats-icon {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

.stats-card:nth-child(2) .stats-icon {
    background-color: rgba(28, 200, 138, 0.1);
    color: #1cc88a;
}

.stats-card:nth-child(3) .stats-icon {
    background-color: rgba(54, 185, 204, 0.1);
    color: #36b9cc;
}

.stats-card:nth-child(4) .stats-icon {
    background-color: rgba(246, 194, 62, 0.1);
    color: #f6c23e;
}

.stats-info {
    flex: 1;
}

.stats-info h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #5a5c69;
    font-weight: 600;
}

.stats-number {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #4a5568;
}

/* Chart Section */
.chart-section {
    margin-bottom: 25px;
}

.chart-container {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.chart-container h3 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 16px;
    color: #4a5568;
    font-weight: 600;
}

#roleDistributionChart {
    height: 300px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .stats-row {
        flex-direction: column;
    }
    
    .stats-card {
        width: 100%;
    }
    
    .btn-icon {
        width: 28px;
        height: 28px;
    }
    
    .data-table {
        font-size: 13px;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px;
    }
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h2 {
    margin: 0;
    color: #4a5568;
    font-size: 24px;
    font-weight: 700;
}</style>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="reports-page">
                <div class="page-header">
                    <h2>Users</h2>
                </div>
                
                                <!-- User Statistics -->
                <div class="stats-section">
                    <div class="stats-row">
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stats-info">
                                <h3>Total Users</h3>
                                <p class="stats-number"><?php echo count($users); ?></p>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="stats-info">
                                <h3>Agents</h3>
                                <p class="stats-number">
                                    <?php 
                                    $agent_count = 0;
                                    foreach ($users as $user_item) {
                                        if ($user_item['role'] == 'agent') $agent_count++;
                                    }
                                    echo $agent_count;
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="stats-info">
                                <h3>Supervisors</h3>
                                <p class="stats-number">
                                    <?php 
                                    $supervisor_count = 0;
                                    foreach ($users as $user_item) {
                                        if ($user_item['role'] == 'supervisor') $supervisor_count++;
                                    }
                                    echo $supervisor_count;
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <div class="stats-info">
                                <h3>Managers</h3>
                                <p class="stats-number">
                                    <?php 
                                    $manager_count = 0;
                                    foreach ($users as $user_item) {
                                        if ($user_item['role'] == 'manager') $manager_count++;
                                    }
                                    echo $manager_count;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="search">Search:</label>
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Email or Username">
                            </div>
                            
                            <div class="filter-group">
                                <label for="role">Role:</label>
                                <select id="role" name="role">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="manager" <?php echo $role_filter == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                    <option value="supervisor" <?php echo $role_filter == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                    <option value="agent" <?php echo $role_filter == 'agent' ? 'selected' : ''; ?>>Agent</option>
                                </select>
                            </div>
                            
                            <?php if ($user['role'] == 'admin'): ?>
                            <div class="filter-group">
                                <label for="team">Team:</label>
                                <select id="team" name="team">
                                    <option value="">All Teams</option>
                                    <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>" <?php echo $team_filter == $team['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($team['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="filter-group">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="users.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>

                
                
                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Team</th>
                                <th>Created</th>
                                <?php if ($user['role'] == 'admin' || $user['role'] == 'manager'): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $user_item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user_item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                    <td><?php echo !empty($user_item['phone']) ? htmlspecialchars($user_item['phone']) : 'Not provided'; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($user_item['role']); ?>">
                                            <?php echo ucfirst($user_item['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user_item['team_name'] ? htmlspecialchars($user_item['team_name']) : 'No Team'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user_item['created_at'])); ?></td>
                                    <?php if ($user['role'] == 'admin' || $user['role'] == 'manager'): ?>
                                    <td class="actions">
                                        <a href="user-details.php?id=<?php echo $user_item['id']; ?>" class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-user.php?id=<?php echo $user_item['id']; ?>" class="btn-icon" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['role'] == 'admin'): ?>
                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $user_item['id']; ?>)" class="btn-icon btn-danger" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($user['role'] == 'admin' || $user['role'] == 'manager') ? '7' : '6'; ?>" class="no-data">No users found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                
                <!-- User Role Distribution Chart -->
                <div class="chart-section">
                    <div class="chart-container">
                        <h3>User Role Distribution</h3>
                        <canvas id="roleDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Delete confirmation
    function confirmDelete(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            window.location.href = 'delete-user.php?id=' + userId;
        }
    }
    
    // Role distribution chart
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('roleDistributionChart').getContext('2d');
        
        // Count roles
        const roles = {
            admin: 0,
            manager: 0,
            supervisor: 0,
            agent: 0
        };
        
        <?php foreach ($users as $user_item): ?>
        roles['<?php echo $user_item['role']; ?>']++;
        <?php endforeach; ?>
        
        const roleChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Admin', 'Manager', 'Supervisor', 'Agent'],
                datasets: [{
                    data: [roles.admin, roles.manager, roles.supervisor, roles.agent],
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    });
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>