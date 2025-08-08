<?php
// includes/header.php - This file is designed to be included within another PHP page.

// Check if user variable exists, if not, try to load it from session
if (!isset($user) && isset($_SESSION['user_id'])) {
    // Assuming getUserById function is available from functions.php
    // which should be included by the parent page (e.g., process-report.php)
    if (function_exists('getUserById')) {
        $user = getUserById($_SESSION['user_id']);
    } else {
        // Fallback if getUserById is not available (should not happen if functions.php is included)
        $user = ['name' => 'Guest', 'email' => 'guest@example.com', 'role' => 'Guest', 'profile_picture' => null];
    }
}

// Ensure $base_url is defined, default to '/' if not passed from including script
if (!isset($base_url)) {
    $base_url = '/'; 
}

// Function to get recent notifications for the current user
// This function should ideally be in functions.php or a dedicated notification file
function getRecentNotifications($user_id, $limit = 10) {
    if (empty($user_id)) {
        return array();
    }
    
    $conn = getDbConnection(); // Assuming getDbConnection is available
    $notifications = array();
    
    try {
        $last_read = null;
        $user_query = "SELECT last_notification_read FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_query);
        if ($user_stmt) {
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            if ($row = $user_result->fetch_assoc()) {
                $last_read = $row['last_notification_read'];
            }
            $user_stmt->close();
        }
        
        if (!$last_read && isset($_SESSION['last_notification_read'])) {
            $last_read = $_SESSION['last_notification_read'];
        }
        
        $activity_query = "
            SELECT 
                la.id,
                la.activity_type,
                la.notes,
                la.created_at,
                l.client_name,
                l.id as lead_id,
                u.name as user_name,
                'activity' as notification_type,
                CASE 
                    WHEN la.user_id = ? THEN 'own_activity'
                    WHEN l.user_id = ? THEN 'lead_activity'
                    ELSE 'other'
                END as activity_relation
            FROM lead_activities la
            JOIN leads l ON la.lead_id = l.id
            JOIN users u ON la.user_id = u.id
            WHERE (l.user_id = ? OR la.user_id = ?)
            AND la.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY la.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($activity_query);
        if ($stmt) {
            $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            $stmt->close();
        }
        
        $table_check = $conn->query("SHOW TABLES LIKE 'memos'");
        if ($table_check && $table_check->num_rows > 0) {
            $memo_query = "
                SELECT 
                    m.id,
                    'Memo' as activity_type,
                    CONCAT('New memo: ', m.title) as notes,
                    m.created_at,
                    'System' as client_name,
                    m.id as lead_id,
                    u.name as user_name,
                    'memo' as notification_type,
                    'memo' as activity_relation,
                    COALESCE(mrs.read_status, 0) as memo_read_status
                FROM memos m
                JOIN users u ON m.created_by = u.id
                LEFT JOIN memo_read_status mrs ON m.id = mrs.memo_id AND mrs.employee_id = ?
                WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND (m.visible_to_all = 1 OR EXISTS (
                    SELECT 1 FROM memo_team_visibility mtv 
                    JOIN users u2 ON u2.team_id = mtv.team_id 
                    WHERE mtv.memo_id = m.id AND u2.id = ?
                ))
                ORDER BY m.created_at DESC
                LIMIT 5
            ";
            
            $memo_stmt = $conn->prepare($memo_query);
            if ($memo_stmt) {
                $memo_stmt->bind_param("ii", $user_id, $user_id);
                $memo_stmt->execute();
                $memo_result = $memo_stmt->get_result();
                
                while ($row = $memo_result->fetch_assoc()) {
                    $notifications[] = $row;
                }
                $memo_stmt->close();
            }
        }
        
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $notifications = array_slice($notifications, 0, $limit);
        
        foreach ($notifications as &$notification) {
            $notification['is_read'] = true; 
            
            if ($notification['notification_type'] === 'memo') {
                $notification['is_read'] = isset($notification['memo_read_status']) && $notification['memo_read_status'] == 1;
            } else {
                if ($last_read) {
                    $notification_time = strtotime($notification['created_at']);
                    $last_read_time = strtotime($last_read);
                    
                    if ($notification_time > $last_read_time) {
                        $notification['is_read'] = false;
                    }
                } else {
                    $notification['is_read'] = false;
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
    }
    
    $conn->close();
    return $notifications;
}

// Function to get notification icon based on activity type
function getNotificationIcon($activity_type) {
    $icons = [
        'Call' => 'fas fa-phone text-blue',
        'Email' => 'fas fa-envelope text-green',
        'Meeting' => 'fas fa-handshake text-orange',
        'Presentation' => 'fas fa-file-powerpoint text-purple',
        'Follow-up' => 'fas fa-reply text-blue',
        'Site Tour' => 'fas fa-building text-green',
        'Initial Contact' => 'fas fa-user-plus text-blue',
        'Negotiation' => 'fas fa-handshake text-orange',
        'Status Change' => 'fas fa-exchange-alt text-purple',
        'Lead Update' => 'fas fa-edit text-orange',
        'Downpayment Tracker' => 'fas fa-money-bill-wave text-green',
        'Memo' => 'fas fa-bullhorn text-red',
        'Other' => 'fas fa-comment text-gray'
    ];
    
    return isset($icons[$activity_type]) ? $icons[$activity_type] : $icons['Other'];
}

// Function to format time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

// Function to get notification URL - NOW USES $base_url
function getNotificationUrl($notification, $base_url) {
    if ($notification['notification_type'] === 'memo') {
        // Check if memo.php exists, otherwise fallback to dashboard
        // Always use $base_url for consistency
        return $base_url . 'memo.php?id=' . $notification['lead_id'];
    } else {
        return $base_url . 'lead-details.php?id=' . $notification['lead_id'];
    }
}

// Get notifications for the current user
$notifications = getRecentNotifications($user_id ?? 0, 10);
$unread_count = count(array_filter($notifications, function($n) {
    return !$n['is_read'];
}));
?>

<header class="main-header">
    <div class="header-container">
        <div class="header-left">
            <div class="header-search">
                <form action="<?php echo $base_url; ?>leads.php" method="GET" class="search-form">
                    <div class="search-input-wrapper">
                        <input type="text" name="search" placeholder="Search leads only" class="search-input" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <i class="fas fa-search search-icon"></i>
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <button type="button" class="clear-search" onclick="clearSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="header-right">
            <div class="header-actions">
                <!-- Quick Actions -->
                <?php if (empty($hide_add_button)): ?>
                    <div class="quick-actions">
                        <a href="add-lead.php" class="quick-action-btn" title="Add New Lead">
                            <i class="fas fa-plus"></i>
                            <span class="action-text">Add Lead</span>
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Notifications -->
                <div class="header-notification" id="notificationDropdown">
                    <button class="notification-btn" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="notification-dropdown">
                        <div class="notification-header">
                            <h4>Recent Activities</h4>
                            <?php if ($unread_count > 0): ?>
                                <button class="mark-all-read" onclick="markAllNotificationsAsRead(); return false;">
                                    <span class="mark-text">Mark all as read</span>
                                    <span class="loading-spinner" style="display: none;">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $index => $notification): ?>
                                    <?php 
                                    $iconClass = getNotificationIcon($notification['activity_type']);
                                    // Pass $base_url to getNotificationUrl
                                    $notificationUrl = getNotificationUrl($notification, $base_url);
                                    ?>
                                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                                         data-url="<?php echo htmlspecialchars($notificationUrl); ?>"
                                         data-index="<?php echo $index; ?>"
                                         onclick="handleNotificationClick(this)">
                                        <div class="notification-icon">
                                            <i class="<?php echo $iconClass; ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <p class="notification-title">
                                                <?php echo htmlspecialchars($notification['activity_type']); ?>
                                                <?php if ($notification['notification_type'] === 'activity' && $notification['client_name']): ?>
                                                    - <?php echo htmlspecialchars($notification['client_name']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="notification-desc">
                                                <?php 
                                                $notes = htmlspecialchars($notification['notes']);
                                                echo strlen($notes) > 80 ? substr($notes, 0, 80) . '...' : $notes;
                                                ?>
                                            </p>
                                            <div class="notification-meta">
                                                <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                                                <?php if ($notification['notification_type'] === 'activity' && $notification['activity_relation'] !== 'own_activity'): ?>
                                                    <span class="notification-user">by <?php echo htmlspecialchars($notification['user_name']); ?></span>
                                                <?php elseif ($notification['notification_type'] === 'memo'): ?>
                                                    <span class="notification-user">by <?php echo htmlspecialchars($notification['user_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notification-item no-notifications">
                                    <div class="notification-icon">
                                        <i class="fas fa-bell-slash text-gray"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p class="notification-title">No recent activities</p>
                                        <p class="notification-desc">When you or others add activities to your leads, or when memos are posted, they'll appear here.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="notification-footer">
                            <a href="<?php echo $base_url; ?>leads.php">View all leads</a>
                        </div>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="header-user-menu" id="userMenuDropdown">
                    <div class="user-menu-trigger">
                        <div class="user-avatar">
                            <?php
                            $imagePath = !empty($user['profile_picture']) ? $user['profile_picture']
                                    : (!empty($user['avatar']) ? $user['avatar'] : null);

                            if ($imagePath):
                                // Use $base_url for profile picture path
                                echo '<img src="' . $base_url . htmlspecialchars($imagePath) . '" alt="Profile Picture">';
                            else:
                                echo '<span class="avatar-text">' . strtoupper(substr($user['name'] ?? 'U', 0, 1)) . '</span>';
                            endif;
                            ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo isset($user) ? htmlspecialchars($user['name']) : 'User'; ?></span>
                            <span class="user-role"><?php echo isset($user) ? htmlspecialchars(ucfirst($user['role'])) : 'Agent'; ?></span>
                        </div>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    
                    <div class="user-menu-dropdown">
                        <div class="dropdown-header">
                                <div class="user-avatar">
                                    <?php
                                    $imagePath = !empty($user['profile_picture']) ? $user['profile_picture']
                                            : (!empty($user['avatar']) ? $user['avatar'] : null);

                                    if ($imagePath):
                                        // Use $base_url for profile picture path in dropdown header
                                        echo '<img src="' . $base_url . htmlspecialchars($imagePath) . '" alt="Profile Picture">';
                                    else:
                                        echo '<span class="avatar-text">' . strtoupper(substr($user['name'] ?? 'U', 0, 1)) . '</span>';
                                    endif;
                                    ?>
                                </div>
                            <div class="user-details">
                                <h4><?php echo isset($user) ? htmlspecialchars($user['name']) : 'User'; ?></h4>
                                <p><?php echo isset($user) ? htmlspecialchars($user['email']) : 'user@example.com'; ?></p>
                            </div>
                        </div>
                        
                        <div class="dropdown-menu">
                            <a href="<?php echo $base_url; ?>profile.php" class="menu-item" id="profile-link">
                                <i class="fas fa-user"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="<?php echo $base_url; ?>settings.php" class="menu-item">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </a>
                            <a href="<?php echo $base_url; ?>help.php" class="menu-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Help & Support</span>
                            </a>
                            <a href="#" class="menu-item" onclick="openReportModal(); return false;">
                                <i class="fas fa-bug"></i>
                                <span>Report a Problem</span>
                            </a>
                            <div class="menu-divider"></div>
                            <a href="<?php echo $base_url; ?>logout.php" class="menu-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Report Problem Modal - MOVED OUTSIDE HEADER -->
<div id="reportModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-bug"></i>Report a Problem</h3>
            <span class="close" onclick="closeReportModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="successMessage" class="success-message" style="display: none;">
                <i class="fas fa-check-circle"></i>
                Thank you! Your problem report has been submitted successfully. We'll investigate and get back to you soon.
            </div>
            
            <div id="errorMessage" class="error-message" style="display: none;">
            </div>
            
            <form id="reportForm" action="https://leads.dreamhosters.com/users/report-problem/process-report.php" method="POST">
                <div class="form-group">
                    <label for="report-username">Username <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="report-username" name="username" placeholder="Enter your username" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="report-phone">Phone Number <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="report-phone" name="phone" placeholder="+1 (555) 123-4567" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="report-email">Email Address (Optional)</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="report-email" name="email" placeholder="your.email@example.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="issue-type">Issue Type <span class="required">*</span></label>
                    <select id="issue-type" name="issue_type" required>
                        <option value="">Select issue type</option>
                        <option value="login-failed">Cannot sign in</option>
                        <option value="forgot-password">Password reset issues</option>
                        <option value="account-locked">Account locked/suspended</option>
                        <option value="page-error">Page not loading properly</option>
                        <option value="performance">Slow performance</option>
                        <option value="feature-bug">Feature not working</option>
                        <option value="data-issue">Data/information incorrect</option>
                        <option value="security-concern">Security concern</option>
                        <option value="other">Other technical issue</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Priority Level</label>
                    <div class="priority-selector">
                        <div class="priority-option low" data-priority="low">
                            <i class="fas fa-circle"></i> Low
                        </div>
                        <div class="priority-option medium selected" data-priority="medium">
                            <i class="fas fa-circle"></i> Medium
                        </div>
                        <div class="priority-option high" data-priority="high">
                            <i class="fas fa-circle"></i> High
                        </div>
                    </div>
                    <input type="hidden" id="priority" name="priority" value="medium">
                </div>
                
                <div class="form-group">
                    <label for="problem-description">Problem Description <span class="required">*</span></label>
                    <textarea id="problem-description" name="description" placeholder="Please describe the problem you're experiencing in detail. Include any error messages, steps you took, and when the issue occurred..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="browser-info">Browser & System Information</label>
                    <input type="text" id="browser-info" name="browser_info" readonly>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeReportModal()">Cancel</button>
            <button type="submit" form="reportForm" class="btn-primary" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Submit Report
            </button>
        </div>
    </div>
</div>

<style>
/* Reset and Base Styles */
html, body {
    margin: 0 !important;
    padding: 0 !important;
    border: 0;
    outline: 0;
    font-size: 100%;
    vertical-align: baseline;
    background: transparent;
}

* {
    box-sizing: border-box;
}

/* CRITICAL: Remove all top margins and padding */
body > *:first-child,
.main-header {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* CRITICAL: Ensure main content doesn't have excessive top padding */
.main-content {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

.main-header {
    margin: 0 !important;
    padding: 0 !important;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 1001;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    display: block;
    width: 100%;
    /* CRITICAL: Set explicit height to prevent expansion */
    height: auto;
    min-height: 60px;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1.5rem;
    max-width: 100%;
    gap: 1rem;
    width: 100%;
    margin: 0;
    /* CRITICAL: Prevent container from expanding */
    min-height: 60px;
    height: auto;
}

.header-left {
    display: flex;
    align-items: center;
    flex: 1;
    gap: 1rem;
    min-width: 0;
}

.header-right {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    margin-left: auto;
}

.mobile-toggle {
    display: none;
    flex-shrink: 0;
}

.sidebar-toggle {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    color: #374151;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    width: 40px;
    height: 40px;
}

.sidebar-toggle:hover {
    background: #e5e7eb;
}

.header-search {
    flex: 1;
    min-width: 0;
}

.search-form {
    width: 100%;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    right: 1rem;
    color: #6b7280;
    z-index: 2;
    font-size: 0.9rem;
}

.search-input {
    width: 100%;
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 2rem;
    background: #f9fafb;
    color: #1f2937;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.search-input::placeholder {
    color: #9ca3af;
}

.search-input:focus {
    outline: none;
    background: white;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.clear-search {
    position: absolute;
    right: 2.5rem;
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.clear-search:hover {
    color: #374151;
    background: #f3f4f6;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-left: auto;
}

.quick-actions {
    display: flex;
    gap: 0.5rem;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #4f46e5;
    color: white;
    text-decoration: none;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid #4f46e5;
    white-space: nowrap;
}

.quick-action-btn:hover {
    background: #4338ca;
    color: white;
    text-decoration: none;
}

.action-text {
    display: none;
}

.header-notification {
    position: relative;
    flex-shrink: 0;
}

.notification-btn {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    color: #374151;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    width: 40px;
    height: 40px;
    position: relative;
}

.notification-btn:hover {
    background: #e5e7eb;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.notification-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 0.5rem);
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    min-width: 380px;
    max-width: 420px;
    z-index: 1002;
    display: none;
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.notification-dropdown.active {
    display: block;
    animation: slideDown 0.2s ease;
}

.notification-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f1f3f4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
}

.mark-all-read {
    font-size: 0.8rem;
    color: #4f46e5;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: none;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease;
}

.mark-all-read:hover {
    background: rgba(79, 70, 229, 0.1);
    text-decoration: underline;
}

.loading-spinner {
    color: #4f46e5;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f1f3f4;
    display: flex;
    gap: 0.75rem;
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
}

.notification-item:hover {
    background: #f9fafb;
    transform: translateX(2px);
}

.notification-item.unread {
    background: rgba(79, 70, 229, 0.02);
    border-left: 3px solid #4f46e5;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
}

.notification-item.read {
    opacity: 0.8;
}

.notification-item.no-notifications {
    cursor: default;
    text-align: center;
    padding: 2rem 1.25rem;
}

.notification-item.no-notifications:hover {
    background: transparent;
    transform: none;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.notification-item:hover .notification-icon {
    background: #e5e7eb;
    transform: scale(1.05);
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-title {
    margin: 0 0 0.25rem 0;
    font-weight: 500;
    color: #1f2937;
    font-size: 0.875rem;
    line-height: 1.3;
}

.notification-desc {
    margin: 0 0 0.5rem 0;
    color: #6b7280;
    font-size: 0.8rem;
    line-height: 1.4;
    word-wrap: break-word;
}

.notification-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.notification-time {
    font-size: 0.75rem;
    color: #9ca3af;
}

.notification-user {
    font-size: 0.75rem;
    color: #6b7280;
    font-style: italic;
}

.notification-footer {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid #f1f3f4;
    text-align: center;
    background: #f9fafb;
}

.notification-footer a {
    color: #4f46e5;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
}

.notification-footer a:hover {
    text-decoration: underline;
}

.header-user-menu {
    position: relative;
    flex-shrink: 0;
}

.user-menu-trigger {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.75rem;
    transition: all 0.2s ease;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
}

.user-menu-trigger:hover {
    background: #e5e7eb;
}

.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    font-size: 0.875rem;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.user-avatar.large {
    width: 48px;
    height: 48px;
    font-size: 1.1rem;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.user-name {
    font-weight: 500;
    color: #1f2937;
    font-size: 0.875rem;
    line-height: 1.2;
}

.user-role {
    font-size: 0.75rem;
    color: #6b7280;
    line-height: 1.2;
}

.dropdown-arrow {
    color: #6b7280;
    font-size: 0.75rem;
    transition: transform 0.2s ease;
}

.user-menu-trigger.active .dropdown-arrow {
    transform: rotate(180deg);
}

.user-menu-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 0.5rem);
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    min-width: 280px;
    z-index: 1002;
    display: none;
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.user-menu-dropdown.active {
    display: block;
    animation: slideDown 0.2s ease;
}

.dropdown-header {
    padding: 1.5rem 1.25rem 1rem;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-details h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
}

.user-details p {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
}

.dropdown-menu {
    padding: 0.5rem 0;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    color: #374151;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    /* CRITICAL: Ensure menu items are clickable */
    position: relative;
    z-index: 1003;
}

.menu-item:hover {
    background: #f9fafb;
    color: #1f2937;
    text-decoration: none;
}

.menu-item i {
    width: 16px;
    color: #6b7280;
}

.menu-item.logout {
    color: #dc2626;
    border-top: 1px solid #f1f3f4;
    margin-top: 0.5rem;
}

.menu-item.logout:hover {
    background: #fef2f2;
    color: #dc2626;
}

.menu-divider {
    height: 1px;
    background: #f1f3f4;
    margin: 0.5rem 0;
}

.text-blue { color: #3b82f6; }
.text-green { color: #10b981; }
.text-orange { color: #f59e0b; }
.text-purple { color: #8b5cf6; }
.text-red { color: #ef4444; }
.text-gray { color: #6b7280; }

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.mobile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1500;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mobile-overlay.active {
    display: block;
    opacity: 1;
}

/* Mobile Close Button */
.mobile-close {
    display: none;
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: #f3f4f6;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    font-size: 1.2rem;
    z-index: 1004;
    transition: all 0.2s ease;
}

.mobile-close:hover {
    background: #e5e7eb;
}

/* MODAL STYLES - COPIED EXACTLY FROM LOGIN PAGE */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    animation: fadeIn 0.3s ease-out;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.modal.show::-webkit-scrollbar {
    display: none;
}

.modal-content {
    background-color: white;
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    animation: slideUp 0.3s ease-out;
    position: relative;
    margin: auto;
    overflow: hidden;
}

.modal-header {
    padding: 2rem 2rem 1rem 2rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #1e40af 0%, #f59e0b 100%);
    color: white;
    border-radius: 16px 16px 0 0;
}

.modal-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    margin: 0;
}

.modal-header h3 i {
    margin-right: 0.75rem;
    font-size: 1.25rem;
}

.close {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.75rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
}

.close:hover {
    color: white;
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem 2rem 2rem 2rem;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    border-top: 1px solid #e5e7eb;
    background: white;
    border-radius: 0 0 16px 16px;
}

.form-group {
    margin-bottom: 1.5rem;
    position: relative;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
}

.form-group label .required {
    color: #ef4444;
    margin-left: 0.25rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #1f2937;
    background-color: white;
    transition: all 0.2s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #1e40af;
    outline: none;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.form-group .input-with-icon {
    position: relative;
}

.form-group .input-with-icon input {
    padding-left: 2.75rem;
}

.form-group .input-with-icon i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
}

.priority-selector {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.priority-option {
    flex: 1;
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    font-weight: 500;
}

.priority-option.low {
    color: #10b981;
}

.priority-option.medium {
    color: #f59e0b;
}

.priority-option.high {
    color: #ef4444;
}

.priority-option.selected {
    border-color: currentColor;
    background-color: rgba(30, 64, 175, 0.05);
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background-color: #e5e7eb;
    color: #374151;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: #d1d5db;
    transform: translateY(-1px);
}

.btn-primary {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #1e40af 0%, #f59e0b 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.success-message {
    background-color: #d1fae5;
    color: #065f46;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    border-left: 4px solid #10b981;
}

.success-message i {
    margin-right: 0.5rem;
    font-size: 1rem;
}

.error-message {
    background-color: #fee2e2;
    color: #b91c1c;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    border-left: 4px solid #ef4444;
}

.error-message i {
    margin-right: 0.5rem;
    font-size: 1rem;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
    .mobile-toggle {
        display: block;
    }
    
    /* CRITICAL: Minimal mobile padding to eliminate white space */
    .main-header {
        min-height: 50px; /* Reduced height */
    }
    
    .header-container {
        padding: 0.25rem 0.5rem; /* Drastically reduced padding */
        gap: 0.5rem;
        min-height: 50px; /* Match header height */
    }
    
    /* CRITICAL: Make search bar icon-only on mobile */
    .header-search {
        flex: 0 0 auto; /* Don't grow, fixed size */
        width: 40px; /* Same width as other buttons */
    }
    
    .search-form {
        width: 40px;
        height: 40px;
        position: relative;
    }

    .search-input-wrapper {
        width: 40px;
        height: 40px;
        position: relative;
    }
    
    .search-input {
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 0.5rem; /* Square-ish like other buttons */
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        text-indent: -9999px; /* Hide text */
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .search-input:focus {
        /* When focused, expand to full width */
        position: fixed;
        top: 0.25rem;
        left: 0.5rem;
        right: 0.5rem;
        width: calc(100% - 1rem);
        height: 40px;
        z-index: 1010;
        text-indent: 0;
        padding: 0.5rem 2.5rem 0.5rem 1rem;
        border-radius: 2rem;
        background: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .search-input:focus::placeholder {
        color: #9ca3af;
    }

    .search-icon {
        position: absolute;
        left: 50%; /* Center horizontally */
        top: 50%; /* Center vertically */
        transform: translate(-50%, -50%); /* Adjust for exact centering */
        color: #6b7280;
        font-size: 0.9rem;
        pointer-events: none; /* Allow clicks to pass through to input */
        z-index: 2;
        transition: all 0.2s ease;
    }

    .search-input:focus + .search-icon {
        left: auto; /* Reset left positioning */
        right: 0.75rem; /* Position to the right */
        top: 50%;
        transform: translateY(-50%);
    }
    
    .clear-search {
        display: none; /* Hide on mobile unless focused */
    }
    
    .search-input:focus ~ .clear-search {
        display: block;
        position: absolute;
        right: 2.5rem;
        top: 50%;
        transform: translateY(-50%);
        z-index: 3;
    }
    
    /* CRITICAL: Ensure no extra spacing around elements */
    .header-left {
        flex: 1;
        min-width: 0;
        gap: 0.5rem;
        display: flex;
        align-items: center;
    }
    
    /* Hide user info text on mobile but keep avatar clickable */
    .user-info {
        display: none;
    }
    
    .dropdown-arrow {
        display: none;
    }
    
    /* Better mobile header actions alignment */
    .header-actions {
        gap: 0.5rem;
        align-items: center;
        display: flex;
    }
    
    .quick-actions {
        display: none;
    }
    
    /* Mobile user menu trigger - make it more clickable */
    .user-menu-trigger {
        padding: 0.5rem;
        min-width: 40px;
        min-height: 40px;
        justify-content: center;
    }
    
    .user-avatar {
        width: 30px;
        height: 30px;
        font-size: 0.75rem;
    }

    /* Mobile notification button */
    .notification-btn {
        width: 40px;
        height: 40px;
        padding: 0.5rem;
    }

    /* CRITICAL: Ensure main content doesn't have excessive top margin */
    .main-content {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    /* CRITICAL: Mobile dropdown positioning and interaction fixes */
    .notification-dropdown,
    .user-menu-dropdown {
        position: fixed !important;
        top: auto !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: auto !important;
        max-height: 90vh !important;
        border-radius: 1rem 1rem 0 0 !important;
        z-index: 1600 !important;
        transform: translateY(100%);
        transition: transform 0.3s ease;
        box-shadow: 0 -10px 25px rgba(0, 0, 0, 0.15) !important;
        margin: 0 !important;
    }

    .notification-dropdown.active,
    .user-menu-dropdown.active {
        display: block !important;
        transform: translateY(0) !important;
        animation: slideUp 0.3s ease;
    }

    /* Mobile dropdown content scrolling */
    .notification-list {
        max-height: calc(90vh - 120px) !important;
        -webkit-overflow-scrolling: touch;
        overflow-y: auto;
    }

    .dropdown-menu {
        max-height: calc(90vh - 120px) !important;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 0.75rem 0;
    }

    /* Show mobile close button */
    .mobile-close {
        display: flex !important;
    }

    /* Adjust header padding for mobile close button */
    .notification-header,
    .dropdown-header {
        padding-right: 3.5rem !important;
        position: relative;
    }

    /* CRITICAL: Better mobile menu item touch targets and clickability */
    .menu-item {
        padding: 1rem 1.25rem;
        font-size: 0.9rem;
        min-height: 52px; /* Larger touch target */
        display: flex;
        align-items: center;
        /* CRITICAL: Ensure proper z-index and pointer events */
        position: relative;
        z-index: 1005;
        pointer-events: auto;
        cursor: pointer;
    }

    /* CRITICAL: Ensure logout link is fully clickable */
    .menu-item.logout {
        /* Override any conflicting styles */
        pointer-events: auto !important;
        cursor: pointer !important;
        z-index: 1006 !important;
    }

    .notification-item {
        padding: 1rem 1.25rem;
        min-height: 60px;
    }

    /* Modal responsive adjustments */
    .modal {
        padding: 0.5rem;
    }
    
    .modal-content {
        max-width: 95vw;
        margin: 0.5rem auto;
    }

    .modal-header {
        padding: 1.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1.5rem;
        flex-direction: column;
    }

    .modal-footer .btn-secondary,
    .modal-footer .btn-primary {
        width: 100%;
    }

    .priority-selector {
        flex-direction: column;
    }
}

/* Extra small devices */
@media (max-width: 480px) {
    .header-container {
        padding: 0.2rem 0.4rem; /* Even more reduced for very small screens */
        gap: 0.4rem;
    }
    
    /* Keep search icon-only styling */
    .search-input:focus {
        left: 0.4rem;
        right: 0.4rem;
        width: calc(100% - 0.8rem);
    }
    
    .user-avatar {
        width: 28px;
        height: 28px;
        font-size: 0.7rem;
    }
    
    .notification-btn {
        width: 36px;
        height: 36px;
    }
    
    .user-menu-trigger {
        min-width: 36px;
        min-height: 36px;
        padding: 0.4rem;
    }
    
    .search-form,
    .search-input-wrapper,
    .search-input {
        width: 36px;
        height: 36px;
    }

    .modal-header {
        padding: 1rem;
    }

    .modal-body {
        padding: 1rem;
    }

    .modal-footer {
        padding: 1rem;
    }

    .modal-header h3 {
        font-size: 1.25rem;
    }
}
</style>

<script>
// Enhanced JavaScript for notification handling
document.addEventListener('DOMContentLoaded', function() {
    console.log('Header script initializing...');
    
    // --- Debugging: Log the href of the profile link on load ---
    const profileLink = document.getElementById('profile-link');
    if (profileLink) {
        console.log('Profile link href on DOMContentLoaded:', profileLink.href);
    }
    // ----------------------------------------------------------

    // Create mobile overlay element
    const mobileOverlay = document.createElement('div');
    mobileOverlay.className = 'mobile-overlay';
    document.body.appendChild(mobileOverlay);
    
    // Toggle notification dropdown
    const notificationBtn = document.querySelector('.notification-btn');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    
    if (notificationBtn && notificationDropdown) {
        // Add mobile close button
        const notificationCloseBtn = document.createElement('button');
        notificationCloseBtn.className = 'mobile-close';
        notificationCloseBtn.innerHTML = '<i class="fas fa-times"></i>';
        notificationDropdown.appendChild(notificationCloseBtn);
        
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            // Close user menu if open
            const userMenuDropdown = document.querySelector('.user-menu-dropdown');
            const userMenuTrigger = document.querySelector('.user-menu-trigger');
            if (userMenuDropdown) {
                userMenuDropdown.classList.remove('active');
            }
            if (userMenuTrigger) {
                userMenuTrigger.classList.remove('active');
            }
            
            // Toggle notification dropdown
            const isActive = notificationDropdown.classList.contains('active');
            notificationDropdown.classList.toggle('active');
            
            // Handle mobile overlay
            if (window.innerWidth <= 768) {
                mobileOverlay.classList.toggle('active');
                document.body.style.overflow = isActive ? 'auto' : 'hidden';
            }
        });
        
        // Close button click handler
        notificationCloseBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.remove('active');
            mobileOverlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
    }
    
    // Toggle user menu dropdown
    const userMenuTrigger = document.querySelector('.user-menu-trigger');
    const userMenuDropdown = document.querySelector('.user-menu-dropdown');
    
    if (userMenuTrigger && userMenuDropdown) {
        console.log('User menu elements found, setting up event listeners...');
        
        // Add mobile close button
        const userCloseBtn = document.createElement('button');
        userCloseBtn.className = 'mobile-close';
        userCloseBtn.innerHTML = '<i class="fas fa-times"></i>';
        userMenuDropdown.appendChild(userCloseBtn);
        
        userMenuTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            console.log('User menu trigger clicked');
            
            // Close notification dropdown if open
            if (notificationDropdown) {
                notificationDropdown.classList.remove('active');
            }
            
            // Toggle user menu
            const isActive = userMenuDropdown.classList.contains('active');
            userMenuDropdown.classList.toggle('active');
            userMenuTrigger.classList.toggle('active');
            
            console.log('User menu active state:', !isActive);
            
            
            if (window.innerWidth <= 0) {
                mobileOverlay.classList.toggle('active');
                document.body.style.overflow = isActive ? 'auto' : 'hidden';
            }
        });
        
        // Close button click handler
        userCloseBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenuDropdown.classList.remove('active');
            userMenuTrigger.classList.remove('active');
            mobileOverlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
        
        // CRITICAL: Handle menu item clicks properly - especially logout
        const menuItems = userMenuDropdown.querySelectorAll('.menu-item');
        console.log('Found menu items:', menuItems.length);
        
        menuItems.forEach((item, index) => {
            console.log('Setting up menu item', index, ':', item.textContent.trim());
            
            // Remove any existing event listeners that might interfere
            item.style.pointerEvents = 'auto';
            item.style.cursor = 'pointer';
            
            item.addEventListener('click', function(e) {
                console.log('Menu item clicked:', this.textContent.trim());
                
                // Don't prevent default - let the link work
                // Don't stop propagation - let it bubble up
                
                // For mobile, close the dropdown after a very short delay
                if (window.innerWidth <= 768) {
                    setTimeout(() => {
                        userMenuDropdown.classList.remove('active');
                        userMenuTrigger.classList.remove('active');
                        mobileOverlay.classList.remove('active');
                        document.body.style.overflow = 'auto';
                    }, 50); // Very short delay to allow navigation
                } else {
                    // For desktop, close immediately
                    userMenuDropdown.classList.remove('active');
                    userMenuTrigger.classList.remove('active');
                }
            });
            
            // Special handling for logout link
            if (item.classList.contains('logout')) {
                console.log('Setting up logout link specifically');
                item.addEventListener('touchstart', function(e) {
                    console.log('Logout touched');
                    // Ensure the touch event works
                    this.style.backgroundColor = '#fef2f2';
                });
                
                item.addEventListener('touchend', function(e) {
                    console.log('Logout touch ended');
                    this.style.backgroundColor = '';
                    // Navigate immediately
                    window.location.href = this.href;
                });
            }
        });
    } else {
        console.log('User menu elements not found');
    }
    
    // Close dropdowns when clicking overlay
    mobileOverlay.addEventListener('click', function() {
        console.log('Mobile overlay clicked');
        if (notificationDropdown) {
            notificationDropdown.classList.remove('active');
        }
        if (userMenuDropdown) {
            userMenuDropdown.classList.remove('active');
            userMenuTrigger.classList.remove('active');
        }
        mobileOverlay.classList.remove('active');
        document.body.style.overflow = 'auto';
    });
    
    // Close dropdowns when clicking outside (desktop)
    document.addEventListener('click', function(e) {
        if (window.innerWidth > 768) {
            if (notificationDropdown && !notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
                notificationDropdown.classList.remove('active');
            }
            
            if (userMenuDropdown && !userMenuDropdown.contains(e.target) && !userMenuTrigger.contains(e.target)) {
                userMenuDropdown.classList.remove('active');
                userMenuTrigger.classList.remove('active');
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        // Reset everything on resize
        document.body.style.overflow = 'auto';
        mobileOverlay.classList.remove('active');
        
        if (window.innerWidth > 768) {
            if (notificationDropdown) {
                notificationDropdown.classList.remove('active');
            }
            if (userMenuDropdown) {
                userMenuDropdown.classList.remove('active');
                userMenuTrigger.classList.remove('active');
            }
        }
    });
    
    // Enhanced mobile search functionality
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        // Handle search input focus on mobile
        searchInput.addEventListener('focus', function() {
            if (window.innerWidth <= 768) {
                // Create backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'search-backdrop';
                backdrop.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.3);
                    z-index: 1009;
                    opacity: 0;
                    transition: opacity 0.2s ease;
                `;
                document.body.appendChild(backdrop);
                
                // Fade in backdrop
                setTimeout(() => {
                    backdrop.style.opacity = '1';
                }, 10);
                
                // Close search when clicking backdrop
                backdrop.addEventListener('click', function() {
                    searchInput.blur();
                    document.body.removeChild(backdrop);
                });
            }
        });
        
        searchInput.addEventListener('blur', function() {
            if (window.innerWidth <= 768) {
                const backdrop = document.querySelector('.search-backdrop');
                if (backdrop) {
                    backdrop.style.opacity = '0';
                    setTimeout(() => {
                        if (backdrop.parentNode) {
                            backdrop.parentNode.removeChild(backdrop);
                        }
                    }, 200);
                }
            }
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
            if (e.key === 'Escape') {
                this.blur();
            }
        });
    }

    // REPORT MODAL FUNCTIONS - COPIED EXACTLY FROM LOGIN PAGE
    function getDetailedBrowserInfo() {
        const userAgent = navigator.userAgent;
        const platform = navigator.platform;
        const language = navigator.language;
        const cookieEnabled = navigator.cookieEnabled;
        const onLine = navigator.onLine;
        
        let browserName = 'Unknown Browser';
        let browserVersion = 'Unknown Version';
        let osName = 'Unknown OS';
        let osVersion = '';
        let architecture = '';

        // Detect Operating System
        if (userAgent.indexOf('Windows NT 10.0') !== -1) {
            osName = 'Windows 10/11';
        } else if (userAgent.indexOf('Windows NT 6.3') !== -1) {
            osName = 'Windows 8.1';
        } else if (userAgent.indexOf('Windows NT 6.2') !== -1) {
            osName = 'Windows 8';
        } else if (userAgent.indexOf('Windows NT 6.1') !== -1) {
            osName = 'Windows 7';
        } else if (userAgent.indexOf('Windows NT 6.0') !== -1) {
            osName = 'Windows Vista';
        } else if (userAgent.indexOf('Windows NT 5.1') !== -1) {
            osName = 'Windows XP';
        } else if (userAgent.indexOf('Windows') !== -1) {
            osName = 'Windows';
        } else if (userAgent.indexOf('Mac OS X') !== -1) {
            osName = 'macOS';
            const macVersion = userAgent.match(/Mac OS X ([0-9_]+)/);
            if (macVersion) {
                osVersion = macVersion[1].replace(/_/g, '.');
            }
        } else if (userAgent.indexOf('Linux') !== -1) {
            osName = 'Linux';
        } else if (userAgent.indexOf('Android') !== -1) {
            osName = 'Android';
        } else if (userAgent.indexOf('iPhone') !== -1 || userAgent.indexOf('iPad') !== -1) {
            osName = 'iOS';
        }

        // Detect Architecture
        if (userAgent.indexOf('WOW64') !== -1 || userAgent.indexOf('Win64') !== -1 || userAgent.indexOf('x64') !== -1) {
            architecture = '64-bit';
        } else if (userAgent.indexOf('Win32') !== -1 || userAgent.indexOf('x86') !== -1) {
            architecture = '32-bit';
        }

        // Detect Browser (Order matters!)
        if (userAgent.indexOf('Edg/') !== -1) {
            browserName = 'Microsoft Edge';
            const edgeVersion = userAgent.match(/Edg\/([0-9.]+)/);
            if (edgeVersion) browserVersion = edgeVersion[1];
        } else if (userAgent.indexOf('Edge/') !== -1) {
            browserName = 'Microsoft Edge (Legacy)';
            const edgeVersion = userAgent.match(/Edge\/([0-9.]+)/);
            if (edgeVersion) browserVersion = edgeVersion[1];
        } else if (userAgent.indexOf('OPR/') !== -1 || userAgent.indexOf('Opera/') !== -1) {
            browserName = 'Opera';
            const operaVersion = userAgent.match(/(?:OPR|Opera)\/([0-9.]+)/);
            if (operaVersion) browserVersion = operaVersion[1];
        } else if (userAgent.indexOf('Chrome/') !== -1 && userAgent.indexOf('Safari/') === -1) {
            browserName = 'Google Chrome';
            const chromeVersion = userAgent.match(/Chrome\/([0-9.]+)/);
            if (chromeVersion) browserVersion = chromeVersion[1];
        } else if (userAgent.indexOf('Firefox/') !== -1) {
            browserName = 'Mozilla Firefox';
            const firefoxVersion = userAgent.match(/Firefox\/([0-9.]+)/);
            if (firefoxVersion) browserVersion = firefoxVersion[1];
        } else if (userAgent.indexOf('Safari/') !== -1) {
            browserName = 'Safari';
            const safariVersion = userAgent.match(/Version\/([0-9.]+)/);
            if (safariVersion) browserVersion = safariVersion[1];
        } else if (userAgent.indexOf('MSIE') !== -1 || userAgent.indexOf('Trident/') !== -1) {
            browserName = 'Internet Explorer';
            const ieVersion = userAgent.match(/(?:MSIE |rv:)([0-9.]+)/);
            if (ieVersion) browserVersion = ieVersion[1];
        }

        // Get additional system info
        const screenInfo = `${screen.width}x${screen.height}`;
        const colorDepth = screen.colorDepth;
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const memory = navigator.deviceMemory ? `${navigator.deviceMemory}GB RAM` : 'Unknown RAM';
        const cores = navigator.hardwareConcurrency ? `${navigator.hardwareConcurrency} cores` : 'Unknown cores';

        // Build comprehensive info string
        let systemInfo = `${browserName} ${browserVersion}`;
        systemInfo += ` | ${osName}`;
        if (osVersion) systemInfo += ` ${osVersion}`;
        if (architecture) systemInfo += ` (${architecture})`;
        systemInfo += ` | Screen: ${screenInfo}`;
        systemInfo += ` | ${colorDepth}-bit color`;
        systemInfo += ` | ${cores}`;
        if (navigator.deviceMemory) systemInfo += ` | ${memory}`;
        systemInfo += ` | ${language}`;
        systemInfo += ` | ${timezone}`;
        systemInfo += ` | Cookies: ${cookieEnabled ? 'Enabled' : 'Disabled'}`;
        systemInfo += ` | Online: ${onLine ? 'Yes' : 'No'}`;

        return systemInfo;
    }

    // Priority selector for report modal
    const priorityOptions = document.querySelectorAll('.priority-option');
    const priorityInput = document.getElementById('priority');

    if (priorityOptions.length > 0 && priorityInput) {
        priorityOptions.forEach(option => {
            option.addEventListener('click', function() {
                priorityOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                priorityInput.value = this.dataset.priority;
            });
        });
    }

    // Phone number formatting for report modal
    const phoneInput = document.getElementById('report-phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            }
            e.target.value = value;
        });
    }

    // Handle report form submission - COPIED EXACTLY FROM LOGIN PAGE
    const reportForm = document.getElementById('reportForm');
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Report form submitted');
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            const successMessageDiv = document.getElementById('successMessage');
            const errorMessageDiv = document.getElementById('errorMessage');

            // Hide previous messages
            successMessageDiv.style.display = 'none';
            if (errorMessageDiv) errorMessageDiv.style.display = 'none';
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            const formData = new FormData(this);
            
            // Submit the form using the same method as login page
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successMessageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                    successMessageDiv.style.display = 'flex';
                    document.getElementById('reportForm').reset();
                    document.querySelectorAll('.priority-option').forEach(opt => opt.classList.remove('selected'));
                    document.querySelector('.priority-option[data-priority="medium"]').classList.add('selected');
                    document.getElementById('priority').value = 'medium';
                    
                    // Re-populate browser info after reset
                    const browserInfoField = document.getElementById('browser-info');
                    if (browserInfoField) {
                        browserInfoField.value = getDetailedBrowserInfo();
                    }
                    
                    // Auto close modal after 3 seconds
                    setTimeout(() => {
                        closeReportModal();
                    }, 3000);
                } else {
                    if (errorMessageDiv) {
                        errorMessageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.message || 'An error occurred');
                        errorMessageDiv.style.display = 'flex';
                    } else {
                        alert('Error: ' + (data.message || 'An error occurred'));
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (errorMessageDiv) {
                    errorMessageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred while submitting your report. Please try again.';
                    errorMessageDiv.style.display = 'flex';
                } else {
                    alert('An error occurred while submitting your report. Please try again.');
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    console.log('Header script initialization complete');
});

// Clear search function
function clearSearch() {
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
        // Submit form to clear search results
        searchInput.closest('form').submit();
    }
}

// Handle notification click with mark as read functionality
function handleNotificationClick(element) {
    const url = element.getAttribute('data-url');
    const index = element.getAttribute('data-index');
    
    // Mark as read visually
    element.classList.remove('unread');
    element.classList.add('read');
    
    // Update badge count
    updateNotificationBadge();
    
    // Close dropdown
    const notificationDropdown = document.querySelector('.notification-dropdown');
    if (notificationDropdown) {
        notificationDropdown.classList.remove('active');
        const mobileOverlay = document.querySelector('.mobile-overlay');
        if (mobileOverlay) mobileOverlay.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    // Navigate to the URL
    if (url) {
        window.location.href = url;
    }
}

// Update notification badge count
function updateNotificationBadge() {
    const unreadItems = document.querySelectorAll('.notification-item.unread');
    const badge = document.querySelector('.notification-badge');
    const markAllBtn = document.querySelector('.mark-all-read');
    
    if (unreadItems.length === 0) {
        if (badge) {
            badge.style.display = 'none';
        }
        if (markAllBtn) {
            markAllBtn.style.display = 'none';
        }
    } else {
        if (badge) {
            badge.textContent = unreadItems.length;
            badge.style.display = 'flex';
        }
    }
}

// Mark all notifications as read
function markAllNotificationsAsRead() {
    console.log('markAllNotificationsAsRead called');
    
    // Show loading spinner
    const markText = document.querySelector('.mark-text');
    const loadingSpinner = document.querySelector('.loading-spinner');
    if (markText) markText.style.display = 'none';
    if (loadingSpinner) loadingSpinner.style.display = 'inline-block';
    
    const unreadItems = document.querySelectorAll('.notification-item.unread');
    console.log('Found unread items:', unreadItems.length);
    
    unreadItems.forEach(item => {
        item.classList.remove('unread');
        item.classList.add('read');
    });
    
    // Update badge count
    updateNotificationBadge();
    
    // Send AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'mark-notification-read.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            console.log('AJAX response received. Status:', xhr.status);
            console.log('Response text:', xhr.responseText);
            
            // Hide loading spinner
            if (markText) markText.style.display = 'inline';
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    console.log('Parsed response:', response);
                    
                    if (response.success) {
                        console.log('Notifications marked as read in database');
                        const markAllBtn = document.querySelector('.mark-all-read');
                        if (markAllBtn) {
                            markAllBtn.style.display = 'none';
                        }
                        showNotificationMessage('All notifications marked as read!', 'success');
                    } else {
                        console.error('Server returned error:', response.error);
                        showNotificationMessage('Error: ' + response.error, 'error');
                        
                        // Revert UI changes on error
                        unreadItems.forEach(item => {
                            item.classList.add('unread');
                            item.classList.remove('read');
                        });
                        updateNotificationBadge();
                    }
                } catch (e) {
                    console.error('Failed to parse server response:', xhr.responseText);
                    showNotificationMessage('Server response error: ' + xhr.responseText, 'error');
                    
                    // Revert UI changes on error
                    unreadItems.forEach(item => {
                        item.classList.add('unread');
                        item.classList.remove('read');
                    });
                    updateNotificationBadge();
                }
            } else {
                console.error('HTTP error:', xhr.status, xhr.responseText);
                showNotificationMessage('HTTP Error ' + xhr.status + ': ' + xhr.responseText, 'error');
                
                // Revert UI changes on error
                unreadItems.forEach(item => {
                    item.classList.add('unread');
                    item.classList.remove('read');
                });
                updateNotificationBadge();
            }
        }
    };
    xhr.send('action=mark_all_read');
    
    return false;
}

// Show notification message
function showNotificationMessage(message, type = 'success') {
    const existingMsg = document.querySelector('.notification-message');
    if (existingMsg) {
        existingMsg.remove();
    }
    
    const msg = document.createElement('div');
    msg.className = 'notification-message';
    msg.textContent = message;
    msg.style.cssText = `
        position: fixed; 
        top: 20px; 
        right: 20px; 
        background: ${type === 'success' ? '#10b981' : '#ef4444'}; 
        color: white; 
        padding: 12px 20px; 
        border-radius: 8px; 
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-weight: 500;
        max-width: 300px;
    `;
    
    document.body.appendChild(msg);
    
    setTimeout(() => {
        if (msg.parentNode) {
            msg.parentNode.removeChild(msg);
        }
    }, 4000);
}

// REPORT MODAL FUNCTIONS - COPIED EXACTLY FROM LOGIN PAGE
function openReportModal() {
    console.log('Opening report modal...');
    const modal = document.getElementById('reportModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Reset form and hide success message
    document.getElementById('reportForm').reset();
    document.getElementById('successMessage').style.display = 'none';
    const errorMessageDiv = document.getElementById('errorMessage');
    if (errorMessageDiv) errorMessageDiv.style.display = 'none';
    
    // Reset priority to medium
    document.querySelectorAll('.priority-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelector('.priority-option[data-priority="medium"]').classList.add('selected');
    document.getElementById('priority').value = 'medium';

    // Re-populate browser info after reset
    const browserInfo = document.getElementById('browser-info');
    if (browserInfo) {
        function getDetailedBrowserInfo() {
            const userAgent = navigator.userAgent;
            const platform = navigator.platform;
            const language = navigator.language;
            const cookieEnabled = navigator.cookieEnabled;
            const onLine = navigator.onLine;
            
            let browserName = 'Unknown Browser';
            let browserVersion = 'Unknown Version';
            let osName = 'Unknown OS';
            let osVersion = '';
            let architecture = '';

            // Detect Operating System
            if (userAgent.indexOf('Windows NT 10.0') !== -1) {
                osName = 'Windows 10/11';
            } else if (userAgent.indexOf('Windows NT 6.3') !== -1) {
                osName = 'Windows 8.1';
            } else if (userAgent.indexOf('Windows NT 6.2') !== -1) {
                osName = 'Windows 8';
            } else if (userAgent.indexOf('Windows NT 6.1') !== -1) {
                osName = 'Windows 7';
            } else if (userAgent.indexOf('Windows NT 6.0') !== -1) {
                osName = 'Windows Vista';
            } else if (userAgent.indexOf('Windows NT 5.1') !== -1) {
                osName = 'Windows XP';
            } else if (userAgent.indexOf('Windows') !== -1) {
                osName = 'Windows';
            } else if (userAgent.indexOf('Mac OS X') !== -1) {
                osName = 'macOS';
                const macVersion = userAgent.match(/Mac OS X ([0-9_]+)/);
                if (macVersion) {
                    osVersion = macVersion[1].replace(/_/g, '.');
                }
            } else if (userAgent.indexOf('Linux') !== -1) {
                osName = 'Linux';
            } else if (userAgent.indexOf('Android') !== -1) {
                osName = 'Android';
            } else if (userAgent.indexOf('iPhone') !== -1 || userAgent.indexOf('iPad') !== -1) {
                osName = 'iOS';
            }

            // Detect Architecture
            if (userAgent.indexOf('WOW64') !== -1 || userAgent.indexOf('Win64') !== -1 || userAgent.indexOf('x64') !== -1) {
                architecture = '64-bit';
            } else if (userAgent.indexOf('Win32') !== -1 || userAgent.indexOf('x86') !== -1) {
                architecture = '32-bit';
            }

            // Detect Browser (Order matters!)
            if (userAgent.indexOf('Edg/') !== -1) {
                browserName = 'Microsoft Edge';
                const edgeVersion = userAgent.match(/Edg\/([0-9.]+)/);
                if (edgeVersion) browserVersion = edgeVersion[1];
            } else if (userAgent.indexOf('Edge/') !== -1) {
                browserName = 'Microsoft Edge (Legacy)';
                const edgeVersion = userAgent.match(/Edge\/([0-9.]+)/);
                if (edgeVersion) browserVersion = edgeVersion[1];
            } else if (userAgent.indexOf('OPR/') !== -1 || userAgent.indexOf('Opera/') !== -1) {
                browserName = 'Opera';
                const operaVersion = userAgent.match(/(?:OPR|Opera)\/([0-9.]+)/);
                if (operaVersion) browserVersion = operaVersion[1];
            } else if (userAgent.indexOf('Chrome/') !== -1 && userAgent.indexOf('Safari/') === -1) {
                browserName = 'Google Chrome';
                const chromeVersion = userAgent.match(/Chrome\/([0-9.]+)/);
                if (chromeVersion) browserVersion = chromeVersion[1];
            } else if (userAgent.indexOf('Firefox/') !== -1) {
                browserName = 'Mozilla Firefox';
                const firefoxVersion = userAgent.match(/Firefox\/([0-9.]+)/);
                if (firefoxVersion) browserVersion = firefoxVersion[1];
            } else if (userAgent.indexOf('Safari/') !== -1) {
                browserName = 'Safari';
                const safariVersion = userAgent.match(/Version\/([0-9.]+)/);
                if (safariVersion) browserVersion = safariVersion[1];
            } else if (userAgent.indexOf('MSIE') !== -1 || userAgent.indexOf('Trident/') !== -1) {
                browserName = 'Internet Explorer';
                const ieVersion = userAgent.match(/(?:MSIE |rv:)([0-9.]+)/);
                if (ieVersion) browserVersion = ieVersion[1];
            }

            // Get additional system info
            const screenInfo = `${screen.width}x${screen.height}`;
            const colorDepth = screen.colorDepth;
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const memory = navigator.deviceMemory ? `${navigator.deviceMemory}GB RAM` : 'Unknown RAM';
            const cores = navigator.hardwareConcurrency ? `${navigator.hardwareConcurrency} cores` : 'Unknown cores';

            // Build comprehensive info string
            let systemInfo = `${browserName} ${browserVersion}`;
            systemInfo += ` | ${osName}`;
            if (osVersion) systemInfo += ` ${osVersion}`;
            if (architecture) systemInfo += ` (${architecture})`;
            systemInfo += ` | Screen: ${screenInfo}`;
            systemInfo += ` | ${colorDepth}-bit color`;
            systemInfo += ` | ${cores}`;
            if (navigator.deviceMemory) systemInfo += ` | ${memory}`;
            systemInfo += ` | ${language}`;
            systemInfo += ` | ${timezone}`;
            systemInfo += ` | Cookies: ${cookieEnabled ? 'Enabled' : 'Disabled'}`;
            systemInfo += ` | Online: ${onLine ? 'Yes' : 'No'}`;

            return systemInfo;
        }

        browserInfo.value = getDetailedBrowserInfo();
    }
}

function closeReportModal() {
    const modal = document.getElementById('reportModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('reportModal');
    if (event.target == modal) {
        closeReportModal();
    }
}

// Override any existing markAllAsRead function globally
window.markAllAsRead = markAllNotificationsAsRead;

// MAKE MODAL FUNCTIONS GLOBALLY ACCESSIBLE
window.openReportModal = function() {
    console.log('Opening report modal...');
    const modal = document.getElementById('reportModal');
    if (!modal) {
        console.error('Report modal not found!');
        return;
    }
    
    modal.classList.add('show');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Reset form and hide success message
    const form = document.getElementById('reportForm');
    const successMsg = document.getElementById('successMessage');
    const errorMsg = document.getElementById('errorMessage');
    
    if (form) form.reset();
    if (successMsg) successMsg.style.display = 'none';
    if (errorMsg) errorMsg.style.display = 'none';
    
    // Reset priority to medium
    document.querySelectorAll('.priority-option').forEach(opt => opt.classList.remove('selected'));
    const mediumOption = document.querySelector('.priority-option[data-priority="medium"]');
    if (mediumOption) mediumOption.classList.add('selected');
    
    const priorityInput = document.getElementById('priority');
    if (priorityInput) priorityInput.value = 'medium';

    // Re-populate browser info
    const browserInfo = document.getElementById('browser-info');
    if (browserInfo) {
        browserInfo.value = getDetailedBrowserInfo();
    }
};

window.closeReportModal = function() {
    console.log('Closing report modal...');
    const modal = document.getElementById('reportModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
    }
    document.body.style.overflow = 'auto';
};

// Make browser info function globally accessible
window.getDetailedBrowserInfo = function() {
    const userAgent = navigator.userAgent;
    const platform = navigator.platform;
    const language = navigator.language;
    const cookieEnabled = navigator.cookieEnabled;
    const onLine = navigator.onLine;
    
    let browserName = 'Unknown Browser';
    let browserVersion = 'Unknown Version';
    let osName = 'Unknown OS';
    let osVersion = '';
    let architecture = '';

    // Detect Operating System
    if (userAgent.indexOf('Windows NT 10.0') !== -1) {
        osName = 'Windows 10/11';
    } else if (userAgent.indexOf('Windows NT 6.3') !== -1) {
        osName = 'Windows 8.1';
    } else if (userAgent.indexOf('Windows NT 6.2') !== -1) {
        osName = 'Windows 8';
    } else if (userAgent.indexOf('Windows NT 6.1') !== -1) {
        osName = 'Windows 7';
    } else if (userAgent.indexOf('Windows NT 6.0') !== -1) {
        osName = 'Windows Vista';
    } else if (userAgent.indexOf('Windows NT 5.1') !== -1) {
        osName = 'Windows XP';
    } else if (userAgent.indexOf('Windows') !== -1) {
        osName = 'Windows';
    } else if (userAgent.indexOf('Mac OS X') !== -1) {
        osName = 'macOS';
        const macVersion = userAgent.match(/Mac OS X ([0-9_]+)/);
        if (macVersion) {
            osVersion = macVersion[1].replace(/_/g, '.');
        }
    } else if (userAgent.indexOf('Linux') !== -1) {
        osName = 'Linux';
    } else if (userAgent.indexOf('Android') !== -1) {
        osName = 'Android';
    } else if (userAgent.indexOf('iPhone') !== -1 || userAgent.indexOf('iPad') !== -1) {
        osName = 'iOS';
    }

    // Detect Architecture
    if (userAgent.indexOf('WOW64') !== -1 || userAgent.indexOf('Win64') !== -1 || userAgent.indexOf('x64') !== -1) {
        architecture = '64-bit';
    } else if (userAgent.indexOf('Win32') !== -1 || userAgent.indexOf('x86') !== -1) {
        architecture = '32-bit';
    }

    // Detect Browser (Order matters!)
    if (userAgent.indexOf('Edg/') !== -1) {
        browserName = 'Microsoft Edge';
        const edgeVersion = userAgent.match(/Edg\/([0-9.]+)/);
        if (edgeVersion) browserVersion = edgeVersion[1];
    } else if (userAgent.indexOf('Edge/') !== -1) {
        browserName = 'Microsoft Edge (Legacy)';
        const edgeVersion = userAgent.match(/Edge\/([0-9.]+)/);
        if (edgeVersion) browserVersion = edgeVersion[1];
    } else if (userAgent.indexOf('OPR/') !== -1 || userAgent.indexOf('Opera/') !== -1) {
        browserName = 'Opera';
        const operaVersion = userAgent.match(/(?:OPR|Opera)\/([0-9.]+)/);
        if (operaVersion) browserVersion = operaVersion[1];
    } else if (userAgent.indexOf('Chrome/') !== -1 && userAgent.indexOf('Safari/') === -1) {
        browserName = 'Google Chrome';
        const chromeVersion = userAgent.match(/Chrome\/([0-9.]+)/);
        if (chromeVersion) browserVersion = chromeVersion[1];
    } else if (userAgent.indexOf('Firefox/') !== -1) {
        browserName = 'Mozilla Firefox';
        const firefoxVersion = userAgent.match(/Firefox\/([0-9.]+)/);
        if (firefoxVersion) browserVersion = firefoxVersion[1];
    } else if (userAgent.indexOf('Safari/') !== -1) {
        browserName = 'Safari';
        const safariVersion = userAgent.match(/Version\/([0-9.]+)/);
        if (safariVersion) browserVersion = safariVersion[1];
    } else if (userAgent.indexOf('MSIE') !== -1 || userAgent.indexOf('Trident/') !== -1) {
        browserName = 'Internet Explorer';
        const ieVersion = userAgent.match(/(?:MSIE |rv:)([0-9.]+)/);
        if (ieVersion) browserVersion = ieVersion[1];
    }

    // Get additional system info
    const screenInfo = `${screen.width}x${screen.height}`;
    const colorDepth = screen.colorDepth;
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const memory = navigator.deviceMemory ? `${navigator.deviceMemory}GB RAM` : 'Unknown RAM';
    const cores = navigator.hardwareConcurrency ? `${navigator.hardwareConcurrency} cores` : 'Unknown cores';

    // Build comprehensive info string
    let systemInfo = `${browserName} ${browserVersion}`;
    systemInfo += ` | ${osName}`;
    if (osVersion) systemInfo += ` ${osVersion}`;
    if (architecture) systemInfo += ` (${architecture})`;
    systemInfo += ` | Screen: ${screenInfo}`;
    systemInfo += ` | ${colorDepth}-bit color`;
    systemInfo += ` | ${cores}`;
    if (navigator.deviceMemory) systemInfo += ` | ${memory}`;
    systemInfo += ` | ${language}`;
    systemInfo += ` | ${timezone}`;
    systemInfo += ` | Cookies: ${cookieEnabled ? 'Enabled' : 'Disabled'}`;
    systemInfo += ` | Online: ${onLine ? 'Yes' : 'No'}`;

    return systemInfo;
};

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('reportModal');
    if (event.target === modal) {
        closeReportModal();
    }
});

console.log('Report modal functions made globally accessible');
</script>
