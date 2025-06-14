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

// Get current user information
$current_user_id = $_SESSION['user_id'];
$current_user = getUserById($current_user_id);

// Store the current user in a variable that will be used by sidebar and header
$user = $current_user;

// Check if user has permission to view user details
if ($current_user['role'] != 'admin' && $current_user['role'] != 'manager' && $current_user['role'] != 'supervisor') {
    header("Location: index.php");
    exit();
}

// Get user ID from URL
if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET['id']);

// Get detailed user information including phone
$user_query = "SELECT u.*, t.name as team_name 
               FROM users u 
               LEFT JOIN teams t ON u.team_id = t.id 
               WHERE u.id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$viewed_user = $user_result->fetch_assoc();
$user_stmt->close();

// Check if user exists
if (!$viewed_user) {
    $_SESSION['error'] = "User not found.";
    header("Location: users.php");
    exit();
}

// Check if current user has permission to view this user
if ($current_user['role'] != 'admin') {
    // Managers and supervisors can only view users in their team
    if ($viewed_user['team_id'] != $current_user['team_id']) {
        $_SESSION['error'] = "You don't have permission to view this user.";
        header("Location: users.php");
        exit();
    }
}

// Get user's leads
$leads_query = "SELECT * FROM leads WHERE user_id = $user_id ORDER BY created_at DESC";
$leads_result = mysqli_query($conn, $leads_query);
$leads = [];
if ($leads_result) {
    while ($row = mysqli_fetch_assoc($leads_result)) {
        $leads[] = $row;
    }
}

// Get user's lead activities
$activities_query = "SELECT la.*, l.client_name 
                    FROM lead_activities la 
                    JOIN leads l ON la.lead_id = l.id 
                    WHERE la.user_id = $user_id 
                    ORDER BY la.created_at DESC 
                    LIMIT 10";
$activities_result = mysqli_query($conn, $activities_query);
$activities = [];
if ($activities_result) {
    while ($row = mysqli_fetch_assoc($activities_result)) {
        $activities[] = $row;
    }
}

// Get lead status counts
$status_counts = [
    'Inquiry' => 0,
    'Presentation Stage' => 0,
    'Negotiation' => 0,
    'Closed' => 0,
    'Lost' => 0
];

$status_query = "SELECT status, COUNT(*) as count FROM leads WHERE user_id = $user_id GROUP BY status";
$status_result = mysqli_query($conn, $status_query);
if ($status_result) {
    while ($row = mysqli_fetch_assoc($status_result)) {
        $status_counts[$row['status']] = $row['count'];
    }
}

// Get lead temperature counts
$temperature_counts = [
    'Hot' => 0,
    'Warm' => 0,
    'Cold' => 0
];

$temperature_query = "SELECT temperature, COUNT(*) as count FROM leads WHERE user_id = $user_id GROUP BY temperature";
$temperature_result = mysqli_query($conn, $temperature_query);
if ($temperature_result) {
    while ($row = mysqli_fetch_assoc($temperature_result)) {
        $temperature_counts[$row['temperature']] = $row['count'];
    }
}

// Calculate total leads value
$value_query = "SELECT SUM(price) as total_value FROM leads WHERE user_id = $user_id";
$value_result = mysqli_query($conn, $value_query);
$total_value = 0;
if ($value_result && $row = mysqli_fetch_assoc($value_result)) {
    $total_value = $row['total_value'] ?: 0;
}

// Calculate closed leads value
$closed_value_query = "SELECT SUM(price) as closed_value FROM leads WHERE user_id = $user_id AND status = 'Closed'";
$closed_value_result = mysqli_query($conn, $closed_value_query);
$closed_value = 0;
if ($closed_value_result && $row = mysqli_fetch_assoc($closed_value_result)) {
    $closed_value = $row['closed_value'] ?: 0;
}

// Get recent activity dates
$recent_activity_query = "SELECT DATE(created_at) as activity_date, COUNT(*) as count 
                         FROM lead_activities 
                         WHERE user_id = $user_id 
                         GROUP BY DATE(created_at) 
                         ORDER BY activity_date DESC 
                         LIMIT 7";
$recent_activity_result = mysqli_query($conn, $recent_activity_query);
$activity_dates = [];
$activity_counts = [];
if ($recent_activity_result) {
    while ($row = mysqli_fetch_assoc($recent_activity_result)) {
        $activity_dates[] = date('M d', strtotime($row['activity_date']));
        $activity_counts[] = $row['count'];
    }
}
// Reverse arrays to show chronological order
$activity_dates = array_reverse($activity_dates);
$activity_counts = array_reverse($activity_counts);

// Get user's join date in readable format
$join_date = date('F j, Y', strtotime($viewed_user['created_at']));

// Calculate days since joining
$join_datetime = new DateTime($viewed_user['created_at']);
$now_datetime = new DateTime();
$interval = $join_datetime->diff($now_datetime);
$days_since_joining = $interval->days;

// Get team information
$team_name = 'No Team';
if (!empty($viewed_user['team_id'])) {
    $team_query = "SELECT name FROM teams WHERE id = " . $viewed_user['team_id'];
    $team_result = mysqli_query($conn, $team_query);
    if ($team_result && $team_row = mysqli_fetch_assoc($team_result)) {
        $team_name = $team_row['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?php echo htmlspecialchars($viewed_user['name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/users.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* User Details Page Styles */
        .user-profile {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .profile-sidebar {
            flex: 1;
            min-width: 300px;
            max-width: 350px;
        }
        
        .profile-main {
            flex: 2;
            min-width: 500px;
        }
        
        .profile-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .profile-header {
            background-color: #4e73df;
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 40px;
            color: #4e73df;
        }
        
        .profile-name {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 5px 0;
        }
        
        .profile-role {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: rgba(255, 255, 255, 0.2);
            margin-bottom: 10px;
        }
        
        .profile-info {
            padding: 20px;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 12px;
            color: #858796;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 14px;
            color: #4a5568;
        }
        
        .section-title {
            font-size: 16px;
            color: #4a5568;
            font-weight: 600;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #4a5568;
            margin: 0 0 5px 0;
        }
        
        .stat-label {
            font-size: 12px;
            color: #858796;
            margin: 0;
        }
        
        .chart-container {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .chart-row {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .chart-col {
            flex: 1;
            min-width: 300px;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: flex-start;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #f8f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #4e73df;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-size: 14px;
            font-weight: 600;
            color: #4a5568;
            margin: 0 0 5px 0;
        }
        
        .activity-meta {
            font-size: 12px;
            color: #858796;
            margin: 0;
        }
        
        .leads-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .leads-table th {
            background-color: #f8f9fc;
            color: #4a5568;
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .leads-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #5a5c69;
        }
        
        .leads-table tr:last-child td {
            border-bottom: none;
        }
        
        .leads-table tr:hover {
            background-color: #f8f9fc;
        }
        
        .badge-hot {
            background-color: #e74a3b;
            color: white;
        }
        
        .badge-warm {
            background-color: #f6c23e;
            color: white;
        }
        
        .badge-cold {
            background-color: #36b9cc;
            color: white;
        }
        
        .badge-inquiry {
            background-color: #858796;
            color: white;
        }
        
        .badge-presentation {
            background-color: #4e73df;
            color: white;
        }
        
        .badge-negotiation {
            background-color: #f6c23e;
            color: white;
        }
        
        .badge-closed {
            background-color: #1cc88a;
            color: white;
        }
        
        .badge-lost {
            background-color: #e74a3b;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        }
        
        .btn-secondary:hover {
            background-color: #eaecf4;
        }
        
        .btn-danger {
            background-color: #e74a3b;
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background-color: #d52a1a;
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: #858796;
            font-style: italic;
        }
        
        @media (max-width: 992px) {
            .profile-sidebar {
                max-width: 100%;
            }
            
            .profile-main {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-header">
                <h2>User Details</h2>
                <div class="action-buttons">
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                    <?php if ($current_user['role'] == 'admin' || ($current_user['role'] == 'manager' && $viewed_user['role'] != 'admin' && $viewed_user['role'] != 'manager')): ?>
                    <a href="edit-user.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit User
                    </a>
                    <?php endif; ?>
                    <?php if ($current_user['role'] == 'admin'): ?>
                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $user_id; ?>)" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete User
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="user-profile">
                <div class="profile-sidebar">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?php if (!empty($viewed_user['profile_picture']) && file_exists($viewed_user['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($viewed_user['profile_picture']); ?>" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <h3 class="profile-name"><?php echo htmlspecialchars($viewed_user['name']); ?></h3>
                            <span class="profile-role"><?php echo ucfirst(htmlspecialchars($viewed_user['role'])); ?></span>
                        </div>
                        <div class="profile-info">
                            <div class="info-group">
                                <div class="info-label">Username</div>
                                <div class="info-value"><?php echo htmlspecialchars($viewed_user['username']); ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($viewed_user['email']); ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value">
                                    <?php echo !empty($viewed_user['phone']) ? htmlspecialchars($viewed_user['phone']) : 'Not provided'; ?>
                                </div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Team</div>
                                <div class="info-value"><?php echo htmlspecialchars($team_name); ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Joined</div>
                                <div class="info-value"><?php echo $join_date; ?> (<?php echo $days_since_joining; ?> days ago)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-card">
                        <div class="profile-info">
                            <h4 class="section-title">Performance Summary</h4>
                            
                            <div class="info-group">
                                <div class="info-label">Total Leads</div>
                                <div class="info-value"><?php echo count($leads); ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Closed Deals</div>
                                <div class="info-value"><?php echo $status_counts['Closed']; ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Conversion Rate</div>
                                <div class="info-value">
                                    <?php 
                                    echo count($leads) > 0 
                                        ? round(($status_counts['Closed'] / count($leads)) * 100, 1) . '%' 
                                        : '0%'; 
                                    ?>
                                </div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Total Portfolio Value</div>
                                <div class="info-value">₱<?php echo number_format($total_value, 2); ?></div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Closed Deals Value</div>
                                <div class="info-value">₱<?php echo number_format($closed_value, 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-main">
                    <div class="profile-card">
                        <div class="profile-info">
                            <h4 class="section-title">Lead Statistics</h4>
                            
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo count($leads); ?></div>
                                    <div class="stat-label">Total Leads</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $temperature_counts['Hot']; ?></div>
                                    <div class="stat-label">Hot Leads</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $temperature_counts['Warm']; ?></div>
                                    <div class="stat-label">Warm Leads</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $temperature_counts['Cold']; ?></div>
                                    <div class="stat-label">Cold Leads</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $status_counts['Inquiry']; ?></div>
                                    <div class="stat-label">Inquiries</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $status_counts['Presentation Stage']; ?></div>
                                    <div class="stat-label">Presentations</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $status_counts['Negotiation']; ?></div>
                                    <div class="stat-label">Negotiations</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $status_counts['Closed']; ?></div>
                                    <div class="stat-label">Closed Deals</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-row">
                        <div class="chart-col">
                            <div class="chart-container">
                                <h4 class="section-title">Lead Status Distribution</h4>
                                <canvas id="statusChart" height="220"></canvas>
                            </div>
                        </div>
                        <div class="chart-col">
                            <div class="chart-container">
                                <h4 class="section-title">Lead Temperature</h4>
                                <canvas id="temperatureChart" height="220"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <h4 class="section-title">Recent Activity</h4>
                        <?php if (count($activity_dates) > 0): ?>
                        <canvas id="activityChart" height="100"></canvas>
                        <?php else: ?>
                        <p class="no-data">No recent activity data available</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-card">
                        <div class="profile-info">
                            <h4 class="section-title">Recent Activities</h4>
                            
                            <?php if (count($activities) > 0): ?>
                            <ul class="activity-list">
                                <?php foreach ($activities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-icon">
                                        <?php 
                                        $icon = 'fa-comment';
                                        switch ($activity['activity_type']) {
                                            case 'Call':
                                                $icon = 'fa-phone';
                                                break;
                                            case 'Email':
                                                $icon = 'fa-envelope';
                                                break;
                                            case 'Meeting':
                                                $icon = 'fa-handshake';
                                                break;
                                            case 'Presentation':
                                                $icon = 'fa-presentation';
                                                break;
                                            case 'Follow-up':
                                                $icon = 'fa-reply';
                                                break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h5 class="activity-title">
                                            <?php echo htmlspecialchars($activity['activity_type']); ?> - 
                                            <?php echo htmlspecialchars($activity['client_name']); ?>
                                        </h5>
                                        <p class="activity-meta">
                                            <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                        </p>
                                        <p><?php echo htmlspecialchars($activity['notes']); ?></p>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="no-data">No activities found</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="profile-card">
                        <div class="profile-info">
                            <h4 class="section-title">Leads (<?php echo count($leads); ?>)</h4>
                            
                            <?php if (count($leads) > 0): ?>
                            <div class="table-responsive">
                                <table class="leads-table">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Developer</th>
                                            <th>Project</th>
                                            <th>Temperature</th>
                                            <th>Status</th>
                                            <th>Price</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leads as $lead): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($lead['client_name']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['developer']); ?></td>
                                            <td><?php echo htmlspecialchars($lead['project_model']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($lead['temperature']); ?>">
                                                    <?php echo htmlspecialchars($lead['temperature']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = 'inquiry';
                                                switch ($lead['status']) {
                                                    case 'Presentation Stage':
                                                        $status_class = 'presentation';
                                                        break;
                                                    case 'Negotiation':
                                                        $status_class = 'negotiation';
                                                        break;
                                                    case 'Closed':
                                                        $status_class = 'closed';
                                                        break;
                                                    case 'Lost':
                                                        $status_class = 'lost';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge badge-<?php echo $status_class; ?>">
                                                    <?php echo htmlspecialchars($lead['status']); ?>
                                                </span>
                                            </td>
                                            <td>₱<?php echo number_format($lead['price'], 2); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="no-data">No leads found</p>
                            <?php endif; ?>
                        </div>
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
    
    // Charts
    document.addEventListener('DOMContentLoaded', function() {
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Inquiry', 'Presentation', 'Negotiation', 'Closed', 'Lost'],
                datasets: [{
                    data: [
                        <?php echo $status_counts['Inquiry']; ?>,
                        <?php echo $status_counts['Presentation Stage']; ?>,
                        <?php echo $status_counts['Negotiation']; ?>,
                        <?php echo $status_counts['Closed']; ?>,
                        <?php echo $status_counts['Lost']; ?>
                    ],
                    backgroundColor: [
                        '#858796',
                        '#4e73df',
                        '#f6c23e',
                        '#1cc88a',
                        '#e74a3b'
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
        
        // Temperature Chart
        const tempCtx = document.getElementById('temperatureChart').getContext('2d');
        const tempChart = new Chart(tempCtx, {
            type: 'doughnut',
            data: {
                labels: ['Hot', 'Warm', 'Cold'],
                datasets: [{
                    data: [
                        <?php echo $temperature_counts['Hot']; ?>,
                        <?php echo $temperature_counts['Warm']; ?>,
                        <?php echo $temperature_counts['Cold']; ?>
                    ],
                    backgroundColor: [
                        '#e74a3b',
                        '#f6c23e',
                        '#36b9cc'
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
        
        <?php if (count($activity_dates) > 0): ?>
        // Activity Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($activity_dates); ?>,
                datasets: [{
                    label: 'Activities',
                    data: <?php echo json_encode($activity_counts); ?>,
                    backgroundColor: '#4e73df',
                    borderColor: '#4e73df',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    });
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>