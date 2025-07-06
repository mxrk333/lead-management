<?php
session_start();

// Fix the path resolution - go up two directories to reach the root
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/config/database.php';
require_once $base_path . '/includes/functions.php';

// Set timezone to ensure accurate time display
date_default_timezone_set('Asia/Manila'); // Adjust to your timezone

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get memo ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: memo.php?error=invalid_id");
    exit();
}

$memo_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $conn = getDbConnection();
    
    // Get user information with team name
    $user_query = "SELECT u.*, t.name as team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id WHERE u.id = ?";
    $user_stmt = $conn->prepare($user_query);
    
    if (!$user_stmt) {
        throw new Exception("Failed to prepare user query: " . $conn->error);
    }
    
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();

    if (!$user) {
        session_destroy();
        header("Location: ../../login.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Database connection error in memo-details.php: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
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
    try {
        $access_query = "SELECT m.id FROM memos m WHERE m.id = ? AND (
            m.visible_to_all = 1 
            OR m.created_by = ?
            OR EXISTS (
                SELECT 1 FROM memo_team_visibility mtv 
                WHERE mtv.memo_id = m.id AND mtv.team_id = ?
            )
        )";
        
        $access_stmt = $conn->prepare($access_query);
        if (!$access_stmt) {
            throw new Exception("Failed to prepare access query: " . $conn->error);
        }
        
        $access_stmt->bind_param("iii", $memo_id, $user_id, $user['team_id']);
        $access_stmt->execute();
        $access_result = $access_stmt->get_result();
        
        if ($access_result->num_rows === 0) {
            // User doesn't have access to this memo based on team visibility rules
            header("Location: memo.php?error=access_denied");
            exit();
        }
    } catch (Exception $e) {
        error_log("Access control error: " . $e->getMessage());
        header("Location: memo.php?error=access_error");
        exit();
    }
}

// Get memo details with visibility info
try {
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
    if (!$stmt) {
        throw new Exception("Failed to prepare memo query: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $user_id, $memo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: memo.php?error=not_found");
        exit();
    }
    
    $memo = $result->fetch_assoc();
} catch (Exception $e) {
    error_log("Error retrieving memo: " . $e->getMessage());
    header("Location: memo.php?error=database_error");
    exit();
}

// Get acknowledgment list (only for admins and managers)
$acknowledgments = [];
if ($canCreateMemos) {
    try {
        $ack_query = "SELECT mrs.read_status, mrs.read_at, u.name, u.role, t.name as team_name
                      FROM memo_read_status mrs
                      INNER JOIN users u ON mrs.employee_id = u.id
                      LEFT JOIN teams t ON u.team_id = t.id
                      WHERE mrs.memo_id = ? AND mrs.read_status = 1
                      ORDER BY mrs.read_at DESC";
                      
        $ack_stmt = $conn->prepare($ack_query);
        if ($ack_stmt) {
            $ack_stmt->bind_param("i", $memo_id);
            $ack_stmt->execute();
            $ack_result = $ack_stmt->get_result();
            
            while ($row = $ack_result->fetch_assoc()) {
                $acknowledgments[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error retrieving acknowledgments: " . $e->getMessage());
    }
}

// Get total number of users who should see this memo (for managers only)
$total_users_count = 0;
if ($canCreateMemos) {
    try {
        if ($memo['visible_to_all'] == 1) {
            // Count all active users
            $count_query = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
            $count_result = $conn->query($count_query);
            if ($count_result) {
                $total_users_count = $count_result->fetch_assoc()['total'];
            }
        } else {
            // Count users in teams that have visibility
            $count_query = "SELECT COUNT(DISTINCT u.id) as total
                           FROM users u
                           INNER JOIN teams t ON u.team_id = t.id
                           INNER JOIN memo_team_visibility mtv ON t.id = mtv.team_id
                           WHERE mtv.memo_id = ? AND u.is_active = 1";
                           
            $count_stmt = $conn->prepare($count_query);
            if ($count_stmt) {
                $count_stmt->bind_param("i", $memo_id);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                if ($count_result) {
                    $total_users_count = $count_result->fetch_assoc()['total'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error counting users: " . $e->getMessage());
    }
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
            
            if ($access_check) {
                $access_check->bind_param("iii", $memo_id, $user_id, $user['team_id']);
                $access_check->execute();
                $access_result = $access_check->get_result();
                $can_acknowledge = ($access_result->num_rows > 0);
            }
        }
        
        if (!$can_acknowledge) {
            throw new Exception("You don't have permission to acknowledge this memo.");
        }
        
        // Use current timestamp to ensure accurate time recording
        $current_time = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO memo_read_status (memo_id, employee_id, read_status, read_at) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE read_status = 1, read_at = ?");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare acknowledgment query: " . $conn->error);
        }
        
        $stmt->bind_param("iiss", $memo_id, $user_id, $current_time, $current_time);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute acknowledgment query: " . $stmt->error);
        }
        
        header("Location: memo-details.php?id=" . $memo_id . "&acknowledged=1");
        exit();
    } catch (Exception $e) {
        $error_message = "Error acknowledging memo: " . $e->getMessage();
        error_log($error_message);
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
    <link rel="stylesheet" href="../assets/styles/memo-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <?php include $base_path . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include $base_path . '/includes/header.php'; ?>
            
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
                        <div class="memo-actions-top">
                            <div>
                                <?php if ($memo['read_status'] == 1): ?>
                                    <span class="status-badge read">
                                        <i class="fas fa-check-circle"></i> 
                                        Acknowledged on <?php echo date('F j, Y \a\t g:i A', strtotime($memo['read_at'])); ?>
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
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($memo['read_status'] != 1): ?>
                        <div>
                            <form method="POST" class="acknowledgment-form">
                                <label class="acknowledgment-checkbox">
                                    <input type="checkbox" id="acknowledge-checkbox-details"
                                           onchange="toggleAcknowledgeButton()">
                                    <span style="line-height: 1.3; font-style: italic;">Acknowledged with clarity and confidence — this memo reflects our collective goals, and I am proud to contribute to them.</span>
                                </label>
                                <button type="submit" name="acknowledge_memo"
                                        id="acknowledge-btn-details"
                                        class="btn-acknowledge" disabled>
                                    <i class="fas fa-check"></i> Acknowledge Memo
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
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


    
    <script src="../assets/scripts/memo-details.js"></script>
</body>
</html>
