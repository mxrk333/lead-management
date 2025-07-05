<?php
session_start();

// Fix the path resolution - go up two directories to reach the root
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/config/database.php';
require_once $base_path . '/includes/functions.php';

// Set timezone to ensure accurate time display
date_default_timezone_set('Asia/Manila');

// Enable error reporting for development (disable in production)
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];

try {
    // Get database connection
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
        header("Location: ../../login.php?error=invalid_session");
        exit();
    }
} catch (Exception $e) {
    error_log("Database connection error in memo.php: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Check if user is admin (can see all memos)
$isAdmin = ($user['role'] === 'admin');

// Check if user can create memos (admin or manager)
$canCreateMemos = ($user['role'] === 'admin' || $user['role'] === 'manager');

// Pagination settings
$memos_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $memos_per_page;

// Handle memo acknowledgment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_memo'])) {
    try {
        $memo_id = (int)$_POST['memo_id'];
        $user_id = $_SESSION['user_id'];
        
        if ($memo_id <= 0) {
            throw new Exception("Invalid memo ID");
        }
        
        // Verify the user can access this memo
        $can_acknowledge = false;
        
        if ($isAdmin) {
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
            
            if (!$access_check) {
                throw new Exception("Failed to prepare access check query");
            }
            
            $access_check->bind_param("iii", $memo_id, $user_id, $user['team_id']);
            $access_check->execute();
            $access_result = $access_check->get_result();
            $can_acknowledge = ($access_result->num_rows > 0);
        }
        
        if (!$can_acknowledge) {
            throw new Exception("You don't have permission to acknowledge this memo.");
        }
        
        $conn->begin_transaction();
        
        // Check if already acknowledged
        $check_stmt = $conn->prepare("SELECT id FROM memo_read_status WHERE memo_id = ? AND employee_id = ?");
        if (!$check_stmt) {
            throw new Exception("Failed to prepare check statement");
        }
        
        $check_stmt->bind_param("ii", $memo_id, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        $current_time = date('Y-m-d H:i:s');
        
        if ($result->num_rows === 0) {
            // Insert new acknowledgment
            $stmt = $conn->prepare("INSERT INTO memo_read_status (memo_id, employee_id, read_status, read_at) VALUES (?, ?, 1, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare insert statement");
            }
            $stmt->bind_param("iis", $memo_id, $user_id, $current_time);
        } else {
            // Update existing acknowledgment
            $stmt = $conn->prepare("UPDATE memo_read_status SET read_status = 1, read_at = ? WHERE memo_id = ? AND employee_id = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement");
            }
            $stmt->bind_param("sii", $current_time, $memo_id, $user_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute acknowledgment query");
        }
        
        $conn->commit();
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?acknowledgment=success");
        exit();
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        $error_message = "Error acknowledging memo: " . $e->getMessage();
        error_log($error_message);
    }
}

// Initialize variables
$memos = [];
$total_memos = 0;
$stats = ['total_memos' => 0, 'public_memos' => 0, 'private_memos' => 0, 'read_memos' => 0];

try {
    // Build search conditions with proper sanitization
    $search_conditions = [];
    $search_params = [];
    $search_types = "";
    
    // Search functionality
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $search = trim($_GET['search']);
        $search_conditions[] = "(m.title LIKE ? OR m.description LIKE ? OR u.name LIKE ?)";
        $search_param = "%$search%";
        $search_params[] = $search_param;
        $search_params[] = $search_param;
        $search_params[] = $search_param;
        $search_types .= "sss";
    }
    
    // Team filter
    if (isset($_GET['team']) && !empty(trim($_GET['team']))) {
        $team_filter = trim($_GET['team']);
        $search_conditions[] = "creator_team.name = ?";
        $search_params[] = $team_filter;
        $search_types .= "s";
    }
    
    // Visibility filter
    if (isset($_GET['visibility']) && !empty($_GET['visibility'])) {
        $visibility_filter = $_GET['visibility'];
        if ($visibility_filter === 'all_teams') {
            $search_conditions[] = "m.visible_to_all = 1";
        } elseif ($visibility_filter === 'specific_teams') {
            $search_conditions[] = "m.visible_to_all = 0";
        }
    }
    
    // Status filter
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $status_filter = $_GET['status'];
        if ($status_filter === 'read') {
            $search_conditions[] = "mrs.read_status = 1";
        } elseif ($status_filter === 'unread') {
            $search_conditions[] = "(mrs.read_status IS NULL OR mrs.read_status = 0)";
        }
    }
    
    // Build additional WHERE conditions
    $additional_where = "";
    if (!empty($search_conditions)) {
        $additional_where = " AND " . implode(" AND ", $search_conditions);
    }
    
    // VISIBILITY RULES
    if ($isAdmin) {
        // Admins can see all memos
        $visibility_where = "WHERE 1=1";
        $base_params = [];
        $base_types = "";
    } else {
        // Team-based visibility for managers and regular users
        $visibility_where = "WHERE (
            m.visible_to_all = 1 
            OR m.created_by = ?
            OR EXISTS (
                SELECT 1 FROM memo_team_visibility mtv 
                WHERE mtv.memo_id = m.id AND mtv.team_id = ?
            )
        )";
        $base_params = [$user_id, $user['team_id']];
        $base_types = "ii";
    }
    
    $final_where = $visibility_where . $additional_where;
    $final_params = array_merge($base_params, $search_params);
    $final_types = $base_types . $search_types;
    
    // Count total memos
    $count_query = "SELECT COUNT(DISTINCT m.id) as total 
                    FROM memos m
                    INNER JOIN users u ON m.created_by = u.id
                    INNER JOIN teams creator_team ON m.team_id = creator_team.id
                    LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?
                    $final_where";
    
    $count_params = [$user_id];
    $count_params = array_merge($count_params, $final_params);
    $count_types = "i" . $final_types;
    
    $count_stmt = $conn->prepare($count_query);
    if (!$count_stmt) {
        throw new Exception("Failed to prepare count query");
    }
    
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_memos = $count_result->fetch_assoc()['total'];
    
    $total_pages = ceil($total_memos / $memos_per_page);
    $current_page = min($current_page, max(1, $total_pages));
    $offset = ($current_page - 1) * $memos_per_page;
    
    // Get actual memos
    $memo_query = "SELECT m.*, u.name as created_by_name, creator_team.name as creator_team_name,
                          mrs.read_status, mrs.read_at,
                          GROUP_CONCAT(DISTINCT vt.name ORDER BY vt.name SEPARATOR ', ') as visible_teams
                   FROM memos m
                   INNER JOIN users u ON m.created_by = u.id
                   INNER JOIN teams creator_team ON m.team_id = creator_team.id
                   LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?
                   LEFT JOIN memo_team_visibility mtv ON m.id = mtv.memo_id
                   LEFT JOIN teams vt ON mtv.team_id = vt.id
                   $final_where
                   GROUP BY m.id
                   ORDER BY m.created_at DESC
                   LIMIT ? OFFSET ?";
    
    $memo_params = [$user_id];
    $memo_params = array_merge($memo_params, $final_params);
    $memo_params[] = $memos_per_page;
    $memo_params[] = $offset;
    $memo_types = "i" . $final_types . "ii";
    
    $memo_stmt = $conn->prepare($memo_query);
    if (!$memo_stmt) {
        throw new Exception("Failed to prepare memo query");
    }
    
    $memo_stmt->bind_param($memo_types, ...$memo_params);
    $memo_stmt->execute();
    $memo_result = $memo_stmt->get_result();
    
    while ($row = $memo_result->fetch_assoc()) {
        $memos[] = $row;
    }
    
} catch (Exception $e) {
    $error_message = "Error retrieving memos: " . $e->getMessage();
    error_log($error_message);
}

// Get statistics with proper error handling
try {
    if ($isAdmin) {
        // Admin stats - all memos
        $stats_query = "SELECT 
            COUNT(DISTINCT m.id) as total_memos,
            SUM(CASE WHEN m.visible_to_all = 1 THEN 1 ELSE 0 END) as public_memos,
            SUM(CASE WHEN m.visible_to_all = 0 THEN 1 ELSE 0 END) as private_memos,
            SUM(CASE WHEN mrs.read_status = 1 AND mrs.employee_id = ? THEN 1 ELSE 0 END) as read_memos
        FROM memos m
        LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?";
        
        $stats_stmt = $conn->prepare($stats_query);
        if ($stats_stmt) {
            $stats_stmt->bind_param("ii", $user_id, $user_id);
        }
    } else {
        // Team-based stats for managers and regular users
        $stats_query = "SELECT 
            COUNT(DISTINCT m.id) as total_memos,
            SUM(CASE WHEN m.visible_to_all = 1 THEN 1 ELSE 0 END) as public_memos,
            SUM(CASE WHEN m.visible_to_all = 0 THEN 1 ELSE 0 END) as private_memos,
            SUM(CASE WHEN mrs.read_status = 1 AND mrs.employee_id = ? THEN 1 ELSE 0 END) as read_memos
        FROM memos m
        LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?
        WHERE (
            m.visible_to_all = 1 
            OR m.created_by = ?
            OR EXISTS (
                SELECT 1 FROM memo_team_visibility mtv 
                WHERE mtv.memo_id = m.id AND mtv.team_id = ?
            )
        )";
        
        $stats_stmt = $conn->prepare($stats_query);
        if ($stats_stmt) {
            $stats_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user['team_id']);
        }
    }
    
    if ($stats_stmt) {
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        if ($stats_result) {
            $stats = $stats_result->fetch_assoc();
        }
    }
} catch (Exception $e) {
    error_log("Error getting stats: " . $e->getMessage());
    // Keep default stats
}

// Function to build pagination URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Get unique teams for filter (only from visible memos)
$team_names = [];
if (!empty($memos)) {
    $team_names = array_unique(array_column($memos, 'creator_team_name'));
    sort($team_names);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memos - Inner SPARC Realty Corporation</title>
    <link rel="stylesheet" href="../assets/styles/main-memo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

</head>
<body>
    <div class="container">
        <?php 
        // Include sidebar with error handling
        $sidebar_path = $base_path . '/includes/sidebar.php';
        if (file_exists($sidebar_path)) {
            include $sidebar_path;
        } else {
            echo '<div style="width: 250px; background: #f3f4f6; padding: 1rem;">Sidebar not found</div>';
        }
        ?>
        
        <div class="main-content">
            <?php 
            // Include header with error handling
            $header_path = $base_path . '/includes/header.php';
            if (file_exists($header_path)) {
                include $header_path;
            } else {
                echo '<div style="height: 60px; background: white; border-bottom: 1px solid #e5e7eb; padding: 1rem;">Header not found</div>';
            }
            ?>
            
            <div class="memos-page">
                <!-- Visibility notice based on role -->
                <?php if ($isAdmin): ?>
                <div class="admin-notice">
                    <h3>👑 Admin Access</h3>
                    <p>As an admin, you can see all memos in the system regardless of team assignment.</p>
                </div>
                <?php else: ?>
                <div class="visibility-notice">
                    <h3>🔒 Team-Based Memo Access</h3>
                    <p>You can only see memos that are public, assigned to your team<?php echo !empty($user['team_name']) ? ' (' . htmlspecialchars($user['team_name']) . ')' : ''; ?>, or created by you.</p>
                </div>
                <?php endif; ?>

                <div class="page-header">
                    <h2><i class="fas fa-envelope"></i> Memos Management</h2>
                    <?php if ($canCreateMemos): ?>
                    <a href="add-memo.php" class="btn-add">
                        <i class="fas fa-plus"></i>
                        Add New Memo
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (isset($_GET['acknowledgment']) && $_GET['acknowledgment'] === 'success'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Memo acknowledged successfully!
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--primary-light); color: var(--primary);">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="summary-info">
                            <h3><?php echo $isAdmin ? 'Total Memos' : 'Accessible Memos'; ?></h3>
                            <p><?php echo $stats['total_memos']; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--info-light); color: var(--info);">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Public Memos</h3>
                            <p><?php echo $stats['public_memos']; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Team-Specific Memos</h3>
                            <p><?php echo $stats['private_memos']; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Read by You</h3>
                            <p><?php echo $stats['read_memos']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="filters-container">
                    <form class="search-form" method="GET" action="">
                        <div class="form-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by title, description, author..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Team</label>
                            <select name="team" class="form-control">
                                <option value="">All Teams</option>
                                <?php foreach ($team_names as $team_name): ?>
                                    <option value="<?php echo htmlspecialchars($team_name); ?>" 
                                        <?php echo (isset($_GET['team']) && $_GET['team'] === $team_name) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Visibility</label>
                            <select name="visibility" class="form-control">
                                <option value="">All Visibility</option>
                                <option value="all_teams" <?php echo (isset($_GET['visibility']) && $_GET['visibility'] === 'all_teams') ? 'selected' : ''; ?>>
                                    All Teams
                                </option>
                                <option value="specific_teams" <?php echo (isset($_GET['visibility']) && $_GET['visibility'] === 'specific_teams') ? 'selected' : ''; ?>>
                                    Specific Teams
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="read" <?php echo (isset($_GET['status']) && $_GET['status'] === 'read') ? 'selected' : ''; ?>>
                                    Read
                                </option>
                                <option value="unread" <?php echo (isset($_GET['status']) && $_GET['status'] === 'unread') ? 'selected' : ''; ?>>
                                    Unread
                                </option>
                            </select>
                        </div>
                    </form>
                </div>
                
                <?php if (empty($memos)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No memos found</h3>
                        <p>Try adjusting your search criteria or filters</p>
                        <?php if (!$isAdmin): ?>
                        <p><small><strong>Note:</strong> You can only see memos that are public, assigned to your team<?php echo !empty($user['team_name']) ? ' (' . htmlspecialchars($user['team_name']) . ')' : ''; ?>, or created by you.</small></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="memos-grid">
                        <?php foreach ($memos as $memo): ?>
                            <div class="memo-card <?php echo ($memo['read_status'] == 1) ? 'read' : 'unread'; ?>" 
                                 onclick="window.location.href='memo-details.php?id=<?php echo $memo['id']; ?>'">
                                
                                <?php if (!empty($memo['file_path'])): ?>
                                    <div class="attachment-indicator">
                                        <i class="fas fa-paperclip"></i> Attachment
                                    </div>
                                <?php endif; ?>
                                
                                <div class="memo-header">
                                    <h3 class="memo-title"><?php echo htmlspecialchars($memo['title']); ?></h3>
                                    <div class="memo-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($memo['created_by_name']); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($memo['created_at'])); ?></span>
                                        <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($memo['creator_team_name']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="memo-content">
                                    <p class="memo-description"><?php echo htmlspecialchars($memo['description']); ?></p>
                                </div>
                                
                                <div class="memo-footer">
                                    <div class="memo-visibility">
                                        <?php if ($memo['visible_to_all'] == 1): ?>
                                            <span class="visibility-badge public">
                                                <i class="fas fa-globe"></i> All Teams
                                            </span>
                                        <?php else: ?>
                                            <span class="visibility-badge private">
                                                <i class="fas fa-users"></i> Specific Teams
                                            </span>
                                            <?php if (!empty($memo['visible_teams'])): ?>
                                                <div class="visible-teams">
                                                    Visible to: <?php echo htmlspecialchars($memo['visible_teams']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="memo-actions">
                                        <?php if ($memo['read_status'] == 1): ?>
                                            <button class="btn-acknowledge btn-acknowledged" disabled>
                                                <i class="fas fa-check"></i> Acknowledged
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;" onclick="event.stopPropagation();">
                                                <div style="margin-bottom: 0.5rem; font-size: 0.75rem;">
                                                    <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer;">
                                                        <input type="checkbox" id="acknowledge-checkbox-<?php echo $memo['id']; ?>" 
                                                               onchange="toggleAcknowledgeButton(<?php echo $memo['id']; ?>)" 
                                                               style="margin-top: 0.125rem;">
                                                        <span style="line-height: 1.3; font-style: italic;">Acknowledged with clarity and confidence — this memo reflects our collective goals, and I am proud to contribute to them.</span>
                                                    </label>
                                                </div>
                                                <input type="hidden" name="memo_id" value="<?php echo $memo['id']; ?>">
                                                <button type="submit" name="acknowledge_memo" 
                                                        id="acknowledge-btn-<?php echo $memo['id']; ?>"
                                                        class="btn-acknowledge" disabled>
                                                    <i class="fas fa-check"></i> Acknowledge
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing <?php echo min(($current_page - 1) * $memos_per_page + 1, $total_memos); ?> to 
                            <?php echo min($current_page * $memos_per_page, $total_memos); ?> of 
                            <?php echo $total_memos; ?> memos
                        </div>
                        <div>
                            <?php if ($current_page > 1): ?>
                                <a href="<?php echo buildPaginationUrl($current_page - 1); ?>" class="pagination-button">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                <a href="<?php echo buildPaginationUrl($i); ?>" 
                                   class="pagination-button <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?php echo buildPaginationUrl($current_page + 1); ?>" class="pagination-button">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.querySelector('.search-form');
        const filterInputs = filterForm.querySelectorAll('input, select');

        filterInputs.forEach(input => {
            input.addEventListener('change', () => filterForm.submit());
        });
    });

    function openMemoModal(memoId) {
        if (memoId && memoId > 0) {
            window.location.href = 'memo-details.php?id=' + encodeURIComponent(memoId);
        }
    }

    function toggleAcknowledgeButton(memoId) {
        const checkbox = document.getElementById('acknowledge-checkbox-' + memoId);
        const button = document.getElementById('acknowledge-btn-' + memoId);
        
        if (checkbox && button) {
            if (checkbox.checked) {
                button.disabled = false;
                button.style.opacity = '1';
                button.style.cursor = 'pointer';
            } else {
                button.disabled = true;
                button.style.opacity = '0.5';
                button.style.cursor = 'not-allowed';
            }
        }
    }
    </script>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
