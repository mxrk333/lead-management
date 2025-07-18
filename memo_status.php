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
$user = getUserById($user_id);
$conn = getDbConnection();

// Check if memo ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Memo ID is required");
}

// Get memo ID from URL
$memo_id = (int)$_GET['id'];

try {
    // Get memo details
    $stmt = $conn->prepare("SELECT m.*, u.name as created_by_name, t.name as team_name 
                           FROM memos m 
                           JOIN users u ON m.created_by = u.id 
                           JOIN teams t ON m.team_id = t.id 
                           WHERE m.id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare memo query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $memo_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute memo query: " . $stmt->error);
    }
    
    $memo = $stmt->get_result()->fetch_assoc();
    
    // Check if memo exists and user has permission
    if (!$memo) {
        die("Memo not found");
    }
    
    // Check if user is authorized (admin, manager, or part of the team)
    $isAuthorized = ($user['role'] === 'admin' || 
                     $user['role'] === 'manager' || 
                     $memo['team_id'] === $user['team_id']);
                     
    if (!$isAuthorized) {
        die("You don't have permission to view this memo");
    }
    
    // Get read status for all team members
    $stmt = $conn->prepare("SELECT u.name, mrs.read_status, mrs.read_at 
                           FROM users u 
                           LEFT JOIN memo_read_status mrs ON u.id = mrs.employee_id 
                               AND mrs.memo_id = ? 
                           WHERE u.team_id = ?
                           ORDER BY u.name ASC");
    if (!$stmt) {
        throw new Exception("Failed to prepare status query: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $memo_id, $memo['team_id']);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute status query: " . $stmt->error);
    }
    
    $read_status = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate read statistics
    $total_members = count($read_status);
    $read_count = array_filter($read_status, function($status) { return $status['read_status']; });
    $read_percentage = $total_members > 0 ? round((count($read_count) / $total_members) * 100, 1) : 0;
    
} catch (Exception $e) {
    error_log("Memo status error: " . $e->getMessage());
    die("An error occurred while fetching memo status. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memo Read Status - Inner SPARC Realty Corporation</title>
    
    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        /* Container and layout */
        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        /* Dashboard styles */
        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Back button */
        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 12px 20px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border-radius: 10px;
            text-decoration: none;
            margin-bottom: 24px;
            font-weight: 500;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
        }

        .back-button:hover {
            background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
            color: white;
            text-decoration: none;
        }

        .back-button i {
            margin-right: 8px;
            font-size: 14px;
        }

        /* Status container */
        .status-container {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin: 20px 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #f1f5f9;
        }

        /* Header section */
        .status-header {
            margin-bottom: 32px;
        }

        .status-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-header h1 i {
            color: #4f46e5;
            font-size: 24px;
        }

        .status-subtitle {
            color: #64748b;
            font-size: 16px;
        }

        /* Memo details section */
        .memo-details {
            margin-bottom: 32px;
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            border-left: 4px solid #4f46e5;
        }

        .memo-details h3 {
            color: #1e293b;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .memo-details h3 i {
            color: #4f46e5;
            font-size: 18px;
        }

        .memo-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .memo-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            font-size: 14px;
        }

        .memo-meta-item i {
            color: #64748b;
            width: 16px;
            text-align: center;
        }

        .memo-meta-item strong {
            color: #374151;
            font-weight: 600;
        }

        /* Statistics section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border: 2px solid #f1f5f9;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
        }

        .stat-card:hover {
            border-color: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            display: block;
        }

        .stat-read {
            color: #10b981;
        }

        .stat-unread {
            color: #ef4444;
        }

        .stat-percentage {
            color: #4f46e5;
        }

        .stat-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #f1f5f9;
            border-radius: 4px;
            margin: 12px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
            border-radius: 4px;
            transition: width 0.8s ease;
        }

        /* Table styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: white;
        }

        .status-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .status-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
        }

        .status-table th:first-child {
            border-top-left-radius: 12px;
        }

        .status-table th:last-child {
            border-top-right-radius: 12px;
        }

        .status-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .status-table tbody tr:hover {
            background-color: #fafbfc;
        }

        .status-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Member name styling */
        .member-name {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            color: #374151;
        }

        .member-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-read {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .status-unread {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .status-badge i {
            font-size: 12px;
        }

        /* Date styling */
        .read-date {
            color: #64748b;
            font-size: 14px;
        }

        .read-date.empty {
            color: #9ca3af;
            font-style: italic;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #374151;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .status-container {
                padding: 20px;
            }

            .memo-meta {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .status-table th,
            .status-table td {
                padding: 12px 8px;
                font-size: 13px;
            }

            .status-header h1 {
                font-size: 24px;
            }

            .member-name {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }

            .status-container {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .status-header h1 {
                font-size: 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }

        /* Loading animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-container {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>

<body class="dashboard-page">
    <div class="container">
        <?php include_once 'includes/sidebar.php'; ?>
        
        <div class="main-wrapper">
            <?php include_once 'includes/header.php'; ?>
            
            <main class="main-content">
                <div class="dashboard">
                    <a href="memo.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Back to Memos
                    </a>

                    <div class="status-container">
                        <div class="status-header">
                            <h1><i class="fas fa-chart-bar"></i> Memo Read Status</h1>
                            <p class="status-subtitle">Track who has read this memo and when</p>
                        </div>

                        <?php if ($memo): ?>
                            <div class="memo-details">
                                <h3><i class="fas fa-file-alt"></i><?php echo htmlspecialchars($memo['title']); ?></h3>
                                <div class="memo-meta">
                                    <div class="memo-meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><strong>Created by:</strong> <?php echo htmlspecialchars($memo['created_by_name']); ?></span>
                                    </div>
                                    <div class="memo-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><strong>Created:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($memo['created_at'])); ?></span>
                                    </div>
                                    <div class="memo-meta-item">
                                        <i class="fas fa-users"></i>
                                        <span><strong>Team:</strong> <?php echo htmlspecialchars($memo['team_name']); ?></span>
                                    </div>
                                    <?php if (!empty($memo['memo_when'])): ?>
                                    <div class="memo-meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><strong>Scheduled:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($memo['memo_when'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($memo['description'])): ?>
                                <div class="memo-meta-item">
                                    <i class="fas fa-align-left"></i>
                                    <span><strong>Description:</strong> <?php echo nl2br(htmlspecialchars(substr($memo['description'], 0, 150))); ?><?php echo strlen($memo['description']) > 150 ? '...' : ''; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="stats-grid">
                                <div class="stat-card">
                                    <span class="stat-number stat-read"><?php echo count($read_count); ?></span>
                                    <div class="stat-label">Members Read</div>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-number stat-unread"><?php echo $total_members - count($read_count); ?></span>
                                    <div class="stat-label">Not Read Yet</div>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-number stat-percentage"><?php echo $read_percentage; ?>%</span>
                                    <div class="stat-label">Read Rate</div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $read_percentage; ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($read_status)): ?>
                            <div class="table-container">
                                <table class="status-table">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-user" style="margin-right: 8px;"></i>Team Member</th>
                                            <th><i class="fas fa-eye" style="margin-right: 8px;"></i>Read Status</th>
                                            <th><i class="fas fa-calendar-check" style="margin-right: 8px;"></i>Read Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($read_status as $status): ?>
                                            <tr>
                                                <td>
                                                    <div class="member-name">
                                                        <div class="member-avatar">
                                                            <?php echo strtoupper(substr($status['name'], 0, 1)); ?>
                                                        </div>
                                                        <span><?php echo htmlspecialchars($status['name']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($status['read_status']): ?>
                                                        <span class="status-badge status-read">
                                                            <i class="fas fa-check-circle"></i>
                                                            Read
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-unread">
                                                            <i class="fas fa-clock"></i>
                                                            Unread
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($status['read_at']): ?>
                                                        <span class="read-date">
                                                            <?php echo date('M j, Y \a\t g:i A', strtotime($status['read_at'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="read-date empty">Not read yet</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-users-slash"></i>
                                    <h3>No team members found</h3>
                                    <p>There are no team members to display for this memo.</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3>Memo not found</h3>
                                <p>The memo you're looking for doesn't exist or you don't have permission to view it.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add JavaScript for sidebar functionality -->
    <script src="assets/js/sidebar.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bar
            const progressBar = document.querySelector('.progress-fill');
            if (progressBar) {
                const width = progressBar.style.width;
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.width = width;
                }, 500);
            }
            
            // Add tooltip functionality for truncated descriptions
            const descriptions = document.querySelectorAll('.memo-meta-item span');
            descriptions.forEach(desc => {
                if (desc.textContent.endsWith('...')) {
                    desc.title = 'Click to view full description';
                    desc.style.cursor = 'pointer';
                }
            });
        });
    </script>
</body>
</html>