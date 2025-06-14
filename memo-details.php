<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get memo ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: memo.php?error=invalid_id");
    exit();
}

$memo_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$conn = getDbConnection();

// Get user information with team name
$user_query = "SELECT u.*, t.name as team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id WHERE u.id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    header("Location: login.php");
    exit();
}

// Check if user is admin (can see all memos)
$isAdmin = ($user['role'] === 'admin');

// Check if user can create memos (admin or manager)
$canCreateMemos = ($user['role'] === 'admin' || $user['role'] === 'manager');

// ACCESS CONTROL:
// 1. Admins can access any memo
// 2. Managers and regular users can only access:
//    a. Public memos (visible_to_all = 1) OR
//    b. Memos specifically assigned to their team OR  
//    c. Memos created by them
if (!$isAdmin) {
    $access_query = "SELECT m.id FROM memos m WHERE m.id = ? AND (
        m.visible_to_all = 1 
        OR m.created_by = ?
        OR EXISTS (
            SELECT 1 FROM memo_team_visibility mtv 
            WHERE mtv.memo_id = m.id AND mtv.team_id = ?
        )
    )";
    
    $access_stmt = $conn->prepare($access_query);
    $access_stmt->bind_param("iii", $memo_id, $user_id, $user['team_id']);
    $access_stmt->execute();
    $access_result = $access_stmt->get_result();
    
    if ($access_result->num_rows === 0) {
        // User doesn't have access to this memo based on team visibility rules
        header("Location: memo.php?error=access_denied");
        exit();
    }
}

// Get memo details with visibility info
$query = "SELECT m.*, u.name as created_by_name, creator_team.name as creator_team_name,
                 mrs.read_status, mrs.read_at,
                 GROUP_CONCAT(DISTINCT vt.name ORDER BY vt.name SEPARATOR ', ') as visible_teams
          FROM memos m
          INNER JOIN users u ON m.created_by = u.id
          INNER JOIN teams creator_team ON m.team_id = creator_team.id
          LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?
          LEFT JOIN memo_team_visibility mtv ON m.id = mtv.memo_id
          LEFT JOIN teams vt ON mtv.team_id = vt.id
          WHERE m.id = ?
          GROUP BY m.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $memo_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: memo.php?error=not_found");
    exit();
}

$memo = $result->fetch_assoc();

// Get acknowledgment list (only for admins and managers)
$acknowledgments = [];
if ($canCreateMemos) {
    $ack_query = "SELECT mrs.read_status, mrs.read_at, u.name, u.role, t.name as team_name
                  FROM memo_read_status mrs
                  INNER JOIN users u ON mrs.employee_id = u.id
                  LEFT JOIN teams t ON u.team_id = t.id
                  WHERE mrs.memo_id = ? AND mrs.read_status = 1
                  ORDER BY mrs.read_at DESC";
    $ack_stmt = $conn->prepare($ack_query);
    $ack_stmt->bind_param("i", $memo_id);
    $ack_stmt->execute();
    $ack_result = $ack_stmt->get_result();
    
    while ($row = $ack_result->fetch_assoc()) {
        $acknowledgments[] = $row;
    }
}

// Get total number of users who should see this memo (for managers only)
$total_users_count = 0;
if ($canCreateMemos) {
    if ($memo['visible_to_all'] == 1) {
        // Count all active users
        $count_query = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
        $count_result = $conn->query($count_query);
        $total_users_count = $count_result->fetch_assoc()['total'];
    } else {
        // Count users in teams that have visibility
        $count_query = "SELECT COUNT(DISTINCT u.id) as total 
                        FROM users u
                        INNER JOIN teams t ON u.team_id = t.id
                        INNER JOIN memo_team_visibility mtv ON t.id = mtv.team_id
                        WHERE mtv.memo_id = ? AND u.is_active = 1";
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param("i", $memo_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_users_count = $count_result->fetch_assoc()['total'];
    }
}

// Mark as read if not already read
if ($memo['read_status'] != 1) {
    $read_stmt = $conn->prepare("INSERT INTO memo_read_status (memo_id, employee_id, read_status, read_at) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE read_status = 1, read_at = NOW()");
    $read_stmt->bind_param("ii", $memo_id, $user_id);
    $read_stmt->execute();
}

// Handle acknowledgment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_memo'])) {
    try {
        // Verify the user can access this memo (they already passed the access check above, but let's be extra safe)
        $can_acknowledge = false;
        
        if ($isAdmin) {
            // Admins can acknowledge any memo
            $can_acknowledge = true;
        } else {
            // Check if user can access this memo based on visibility rules
            $access_check = $conn->prepare("SELECT m.id FROM memos m WHERE m.id = ? AND (
                m.visible_to_all = 1 
                OR m.created_by = ?
                OR EXISTS (
                    SELECT 1 FROM memo_team_visibility mtv 
                    WHERE mtv.memo_id = m.id AND mtv.team_id = ?
                )
            )");
            $access_check->bind_param("iii", $memo_id, $user_id, $user['team_id']);
            $access_check->execute();
            $access_result = $access_check->get_result();
            $can_acknowledge = ($access_result->num_rows > 0);
        }
        
        if (!$can_acknowledge) {
            throw new Exception("You don't have permission to acknowledge this memo.");
        }
        
        $stmt = $conn->prepare("INSERT INTO memo_read_status (memo_id, employee_id, read_status, read_at) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE read_status = 1, read_at = NOW()");
        $stmt->bind_param("ii", $memo_id, $user_id);
        $stmt->execute();
        
        header("Location: memo-details.php?id=" . $memo_id . "&acknowledged=1");
        exit();
    } catch (Exception $e) {
        $error_message = "Error acknowledging memo: " . $e->getMessage();
    }
}

// Function to check if a file is an image
function isImage($file_path) {
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($ext, $image_extensions);
}

// Function to check if a file is a PDF
function isPDF($file_path) {
    return strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) === 'pdf';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($memo['title']); ?> - Inner SPARC Realty Corporation</title>
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
        
        .memo-details-page {
            flex: 1;
            padding: 1.5rem;
            width: 100%;
            margin: 0;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }

        /* Access notice */
        .access-notice {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .access-notice h3 {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .access-notice p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.875rem;
        }

        /* Admin access notice */
        .admin-access-notice {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .admin-access-notice h3 {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .admin-access-notice p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.875rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
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

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--gray-100);
            color: var(--gray-700);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-back:hover {
            background: var(--gray-200);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .memo-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        .memo-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .memo-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .memo-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .meta-item i {
            color: var(--primary);
            width: 1rem;
        }

        .memo-visibility {
            margin-top: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--border-radius);
        }

        .visibility-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .visibility-badge.public {
            background: var(--info-light);
            color: var(--info);
        }

        .visibility-badge.private {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .priority-badge.low {
            background: var(--info-light);
            color: var(--info);
        }

        .priority-badge.medium {
            background: var(--warning-light);
            color: var(--warning);
        }

        .priority-badge.high {
            background: var(--danger-light);
            color: var(--danger);
        }

        .priority-badge.urgent {
            background: var(--danger-light);
            color: var(--danger);
            font-weight: 600;
        }

        .visible-teams {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
        }

        .memo-content {
            padding: 2rem;
        }

        .memo-description {
            font-size: 1rem;
            line-height: 1.7;
            color: var(--gray-700);
            white-space: pre-wrap;
        }

        .memo-attachment {
            margin-top: 2rem;
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
        }

        .attachment-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .attachment-preview {
            margin-bottom: 1rem;
            text-align: center;
        }

        .attachment-preview img {
            max-width: 100%;
            max-height: 400px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .attachment-preview iframe {
            width: 100%;
            height: 500px;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .attachment-link:hover {
            background: var(--primary-hover);
            transform: translate(-1px);
        }

        .memo-actions {
            padding: 1.5rem 2rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-badge.read {
            background: var(--success-light);
            color: var(--success);
        }

        .status-badge.unread {
            background: var(--warning-light);
            color: var(--warning);
        }

        .btn-acknowledge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--success);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-acknowledge:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-acknowledged {
            background: var(--gray-100);
            color: var(--gray-600);
            cursor: not-allowed;
        }

        .btn-acknowledged:hover {
            background: var(--gray-100);
            transform: none;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: var(--success-light);
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-danger {
            background-color: var(--danger-light);
            border-color: #fecaca;
            color: #dc2626;
        }

        .acknowledgment-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
        }

        .acknowledgment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .acknowledgment-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .acknowledgment-stats {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .acknowledgment-progress {
            width: 100%;
            height: 0.5rem;
            background-color: var(--gray-200);
            border-radius: 9999px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background-color: var(--success);
            border-radius: 9999px;
        }

        .acknowledgment-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
        }

        .acknowledgment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .acknowledgment-item:last-child {
            border-bottom: none;
        }

        .acknowledgment-user {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 500;
            color: var(--gray-800);
        }

        .user-team {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .acknowledgment-date {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .empty-acknowledgments {
            padding: 2rem;
            text-align: center;
            color: var(--gray-400);
        }

        .empty-acknowledgments i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .memo-details-page {
                padding: 1rem;
            }

            .memo-container {
                margin: 0;
            }

            .memo-header,
            .memo-content,
            .memo-actions {
                padding: 1.5rem 1rem;
            }

            .memo-title {
                font-size: 1.5rem;
            }

            .memo-meta {
                grid-template-columns: 1fr;
            }

            .memo-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .btn-acknowledge {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="memo-details-page">
                <!-- Access notice based on role -->
                <?php if ($isAdmin): ?>
                <div class="admin-access-notice">
                    <h3>👑 Admin Access</h3>
                    <p>As an admin, you can access all memos in the system regardless of team assignment.</p>
                </div>
                <?php else: ?>
                <div class="access-notice">
                    <h3>✅ Access Granted</h3>
                    <p>You can access this memo because it's either public, assigned to your team<?php echo !empty($user['team_name']) ? ' (' . htmlspecialchars($user['team_name']) . ')' : ''; ?>, or created by you.</p>
                </div>
                <?php endif; ?>

                <div class="page-header">
                    <h2><i class="fas fa-envelope-open"></i> Memo Details</h2>
                    <a href="memo.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Back to Memos
                    </a>
                </div>

                <?php if (isset($_GET['acknowledged'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Memo acknowledged successfully!
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="memo-container">
                    <div class="memo-header">
                        <h1 class="memo-title"><?php echo htmlspecialchars($memo['title']); ?></h1>
                        
                        <div class="memo-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span>Created by: <strong><?php echo htmlspecialchars($memo['created_by_name']); ?></strong></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>Date: <strong><?php echo date('F j, Y \a\t g:i A', strtotime($memo['created_at'])); ?></strong></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-users"></i>
                                <span>Team: <strong><?php echo htmlspecialchars($memo['creator_team_name']); ?></strong></span>
                            </div>
                            <?php if (!empty($memo['priority'])): ?>
                            <div class="meta-item">
                                <i class="fas fa-flag"></i>
                                <span>Priority: 
                                    <span class="priority-badge <?php echo strtolower($memo['priority']); ?>">
                                        <?php echo htmlspecialchars($memo['priority']); ?>
                                    </span>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="memo-visibility">
                            <?php if ($memo['visible_to_all'] == 1): ?>
                                <span class="visibility-badge public">
                                    <i class="fas fa-globe"></i> Visible to All Teams
                                </span>
                                <div class="visible-teams">
                                    This memo is visible to all teams in the organization.
                                </div>
                            <?php else: ?>
                                <span class="visibility-badge private">
                                    <i class="fas fa-users"></i> Visible to Specific Teams
                                </span>
                                <?php if (!empty($memo['visible_teams'])): ?>
                                    <div class="visible-teams">
                                        <strong>Visible to teams:</strong> <?php echo htmlspecialchars($memo['visible_teams']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="memo-content">
                        <div class="memo-description">
                            <?php echo nl2br(htmlspecialchars($memo['description'])); ?>
                        </div>

                        <?php if (!empty($memo['file_path']) && file_exists($memo['file_path'])): ?>
                            <div class="memo-attachment">
                                <div class="attachment-header">
                                    <i class="fas fa-paperclip"></i>
                                    Attachment
                                </div>
                                
                                <?php if (isImage($memo['file_path'])): ?>
                                    <div class="attachment-preview">
                                        <img src="<?php echo htmlspecialchars($memo['file_path']); ?>" alt="Memo attachment">
                                    </div>
                                <?php elseif (isPDF($memo['file_path'])): ?>
                                    <div class="attachment-preview">
                                        <iframe src="<?php echo htmlspecialchars($memo['file_path']); ?>" title="PDF attachment"></iframe>
                                    </div>
                                <?php endif; ?>
                                
                                <a href="<?php echo htmlspecialchars($memo['file_path']); ?>" 
                                   target="_blank" class="attachment-link">
                                    <i class="fas fa-download"></i>
                                    Download Attachment
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="memo-actions">
                        <div>
                            <?php if ($memo['read_status'] == 1): ?>
                                <span class="status-badge read">
                                    <i class="fas fa-check-circle"></i> 
                                    Acknowledged on <?php echo date('M j, Y', strtotime($memo['read_at'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge unread">
                                    <i class="fas fa-circle"></i> Not Acknowledged
                                </span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <?php if ($memo['read_status'] == 1): ?>
                                <button class="btn-acknowledge btn-acknowledged" disabled>
                                    <i class="fas fa-check"></i> Already Acknowledged
                                </button>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="acknowledge_memo" class="btn-acknowledge">
                                        <i class="fas fa-check"></i> Acknowledge Memo
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($canCreateMemos): ?>
                        <div class="memo-content">
                            <div class="acknowledgment-section">
                                <div class="acknowledgment-header">
                                    <div class="acknowledgment-title">
                                        <i class="fas fa-clipboard-check"></i> Acknowledgment Tracking
                                    </div>
                                    <div class="acknowledgment-stats">
                                        <?php 
                                        $ack_count = count($acknowledgments);
                                        $percentage = $total_users_count > 0 ? round(($ack_count / $total_users_count) * 100) : 0;
                                        ?>
                                        <span><?php echo $ack_count; ?> of <?php echo $total_users_count; ?> users acknowledged</span>
                                        <strong>(<?php echo $percentage; ?>%)</strong>
                                    </div>
                                </div>
                                
                                <div class="acknowledgment-progress">
                                    <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                
                                <div class="acknowledgment-list">
                                    <?php if (empty($acknowledgments)): ?>
                                        <div class="empty-acknowledgments">
                                            <i class="fas fa-inbox"></i>
                                            <p>No acknowledgments yet</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($acknowledgments as $ack): ?>
                                            <div class="acknowledgment-item">
                                                <div class="acknowledgment-user">
                                                <span class="user-name" style="color: black;"><?php echo htmlspecialchars($ack['name']); ?></span>

                                                    <span class="user-team"><?php echo htmlspecialchars($ack['team_name']); ?> (<?php echo ucfirst(htmlspecialchars($ack['role'])); ?>)</span>
                                                </div>
                                                <div class="acknowledgment-date">
                                                    <?php echo date('M j, Y \a\t g:i A', strtotime($ack['read_at'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>
