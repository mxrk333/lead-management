<?php
// Check if user variable exists
if (!isset($user) && isset($_SESSION['user_id'])) {
    $user = getUserById($_SESSION['user_id']);
}

// Function to get recent notifications for the current user
function getRecentNotifications($user_id, $limit = 10) {
    if (empty($user_id)) {
        return array();
    }
    
    $conn = getDbConnection();
    $notifications = array();
    
    try {
        // Get the last time notifications were read
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
        
        // If not in database, check session
        if (!$last_read && isset($_SESSION['last_notification_read'])) {
            $last_read = $_SESSION['last_notification_read'];
        }
        
        // Debug logging
        error_log("getRecentNotifications - User ID: $user_id, Last Read: " . ($last_read ? $last_read : 'NULL'));
        
        // Get lead activities
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
        
        // Check if memos table exists and get memo notifications
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
                    'memo' as activity_relation
                FROM memos m
                JOIN users u ON m.created_by = u.id
                WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY m.created_at DESC
                LIMIT 5
            ";
            
            $memo_stmt = $conn->prepare($memo_query);
            if ($memo_stmt) {
                $memo_stmt->execute();
                $memo_result = $memo_stmt->get_result();
                
                while ($row = $memo_result->fetch_assoc()) {
                    $notifications[] = $row;
                }
                $memo_stmt->close();
            }
        }
        
        // Sort all notifications by created_at descending
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Limit to the requested number
        $notifications = array_slice($notifications, 0, $limit);
        
        // Mark notifications as read/unread based on last_read timestamp
        foreach ($notifications as &$notification) {
            $notification['is_read'] = true; // Default to read
            
            if ($last_read) {
                // Convert both timestamps to Unix timestamps for comparison
                $notification_time = strtotime($notification['created_at']);
                $last_read_time = strtotime($last_read);
                
                // If notification was created AFTER the last read time, it's unread
                if ($notification_time > $last_read_time) {
                    $notification['is_read'] = false;
                }
                
                // Debug logging for each notification
                error_log("Notification: {$notification['activity_type']}, Created: {$notification['created_at']} ($notification_time), Last Read: $last_read ($last_read_time), Is Read: " . ($notification['is_read'] ? 'true' : 'false'));
            } else {
                // If no last_read timestamp, consider all notifications as unread
                $notification['is_read'] = false;
                error_log("No last_read timestamp, marking as unread: {$notification['activity_type']}");
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

// Function to get notification URL
function getNotificationUrl($notification) {
    if ($notification['notification_type'] === 'memo') {
        // Check if memo.php exists, otherwise fallback to dashboard
        if (file_exists('memo.php')) {
            return 'memo.php';
        } else {
            return 'dashboard.php';
        }
    } else {
        return 'lead-details.php?id=' . $notification['lead_id'];
    }
}

// Get notifications for the current user
$notifications = getRecentNotifications($user_id ?? 0, 10);
$unread_count = count(array_filter($notifications, function($n) {
    return !$n['is_read'];
}));

// Debug output
error_log("Header loaded - Total notifications: " . count($notifications) . ", Unread count: $unread_count");
?>
<header class="main-header">
    <div class="header-container">
        <div class="header-left">
            <div class="mobile-toggle">
                <button id="sidebar-toggle" class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="header-search">
                <form action="leads.php" method="GET" class="search-form">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" placeholder="Search leads, clients, or projects..." class="search-input" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
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
                <div class="quick-actions">
                    <a href="add-lead.php" class="quick-action-btn" title="Add New Lead">
                        <i class="fas fa-plus"></i>
                        <span class="action-text">Add Lead</span>
                    </a>
                </div>
                
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
                                <span class="mark-all-read" onclick="event.preventDefault(); event.stopPropagation(); markAllNotificationsAsRead(); return false;">
                                    <span class="mark-text">Mark all as read</span>
                                    <span class="loading-spinner" style="display: none;">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $index => $notification): ?>
                                    <?php 
                                    $iconClass = getNotificationIcon($notification['activity_type']);
                                    $notificationUrl = getNotificationUrl($notification);
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
                            <a href="leads.php">View all leads</a>
                        </div>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="header-user-menu" id="userMenuDropdown">
                    <div class="user-menu-trigger">
                        <div class="user-avatar">
                            <?php if (isset($user) && !empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <span class="avatar-text"><?php echo isset($user) ? strtoupper(substr($user['name'], 0, 1)) : 'U'; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo isset($user) ? htmlspecialchars($user['name']) : 'User'; ?></span>
                            <span class="user-role"><?php echo isset($user) ? htmlspecialchars(ucfirst($user['role'])) : 'Agent'; ?></span>
                        </div>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    
                    <div class="user-menu-dropdown">
                        <div class="dropdown-header">
                            <div class="user-avatar large">
                                <?php if (isset($user) && !empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                    <span class="avatar-text"><?php echo isset($user) ? strtoupper(substr($user['name'], 0, 1)) : 'U'; ?></span>
                            <?php endif; ?>
                            </div>
                            <div class="user-details">
                                <h4><?php echo isset($user) ? htmlspecialchars($user['name']) : 'User'; ?></h4>
                                <p><?php echo isset($user) ? htmlspecialchars($user['email']) : 'user@example.com'; ?></p>
                            </div>
                        </div>
                        
                        <div class="dropdown-menu">
                            <a href="profile.php" class="menu-item">
                                <i class="fas fa-user"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="settings.php" class="menu-item">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </a>
                            <a href="help.php" class="menu-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Help & Support</span>
                            </a>
                            <div class="menu-divider"></div>
                            <a href="logout.php" class="menu-item logout">
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

<style>
/* Comprehensive reset to eliminate header spacing */
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

/* Ensure no spacing above header */
body > *:first-child,
.main-header {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* Remove any potential spacing from wrapper elements */
.header-wrapper,
.page-wrapper,
.container-fluid,
.main-content {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* Header Styles */
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

/* Mobile Toggle */
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

/* Search */
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
    left: 1rem;
    color: #6b7280;
    z-index: 2;
    font-size: 0.9rem;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
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
    right: 1rem;
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

/* Header Actions */
.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-left: auto;
}

/* Quick Actions */
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

/* Notifications */
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

/* Desktop Dropdown Styles */
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
}

.mark-all-read:hover {
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

/* User Menu */
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

/* Utility Classes */
.text-blue { color: #3b82f6; }
.text-green { color: #10b981; }
.text-orange { color: #f59e0b; }
.text-purple { color: #8b5cf6; }
.text-red { color: #ef4444; }
.text-gray { color: #6b7280; }

/* Animations */
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

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(100%);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile Overlay */
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

/* Responsive Design */
@media (max-width: 1200px) {
    .action-text {
        display: none;
    }
}

@media (min-width: 1200px) {
    .action-text {
        display: inline;
    }
}

@media (max-width: 768px) {
    .mobile-toggle {
        display: block;
    }
    
    .header-container {
        padding: 0.75rem 1rem;
    }
    
    .header-search {
        max-width: none;
        flex: 1;
    }
    
    .user-info {
        display: none;
    }
    
    .header-actions {
        gap: 0.5rem;
    }
    
    .quick-actions {
        display: none;
    }
    
    /* Mobile Dropdown Styles */
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
        max-height: 80vh !important;
        border-radius: 1rem 1rem 0 0 !important;
        z-index: 1600 !important;
        transform: translateY(100%);
        transition: transform 0.3s ease;
        box-shadow: 0 -10px 25px rgba(0, 0, 0, 0.15) !important;
    }
    
    .notification-dropdown.active,
    .user-menu-dropdown.active {
        display: block !important;
        transform: translateY(0) !important;
        animation: slideUp 0.3s ease;
    }
    
    .notification-list {
        max-height: calc(80vh - 120px) !important;
        -webkit-overflow-scrolling: touch;
    }
    
    .dropdown-menu {
        max-height: calc(80vh - 120px) !important;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Mobile close button */
    .mobile-close {
        display: block;
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: #f3f4f6;
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #6b7280;
        font-size: 1.2rem;
        z-index: 10;
    }
    
    .mobile-close:hover {
        background: #e5e7eb;
    }
    
    /* Add padding to account for close button */
    .notification-header,
    .dropdown-header {
        padding-right: 3rem !important;
    }
}

@media (max-width: 576px) {
    .header-container {
        padding: 0.5rem 0.75rem;
        gap: 0.75rem;
    }
    
    .search-input {
        font-size: 0.8rem;
        padding: 0.625rem 1rem 0.625rem 2.25rem;
    }
    
    .notification-item,
    .menu-item {
        padding: 0.875rem 1rem;
    }
    
    .search-input-wrapper {
        max-width: 100%;
    }
    
    .search-input {
        width: 100%;
    }
    
    .notification-btn,
    .sidebar-toggle {
        width: 36px;
        height: 36px;
    }
    
    .user-avatar {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .header-container {
        padding: 0.5rem;
        gap: 0.5rem;
    }
    
    .search-input {
        padding: 0.5rem 0.75rem 0.5rem 2rem;
    }
    
    .search-icon {
        left: 0.75rem;
    }
    
    .clear-search {
        right: 0.75rem;
    }
}

/* Force remove any top spacing - highest specificity */
html body .main-header,
body .main-header,
.main-header {
    margin-top: 0 !important;
    padding-top: 0 !important;
    top: 0 !important;
}

/* Remove spacing from any parent containers */
.main-header::before,
.main-header::after {
    display: none !important;
}

/* Desktop only styles */
@media (min-width: 769px) {
    .mobile-overlay,
    .mobile-close {
        display: none !important;
    }
}
</style>

<script>
    // Create mobile overlay element
    const mobileOverlay = document.createElement('div');
    mobileOverlay.className = 'mobile-overlay';
    document.body.appendChild(mobileOverlay);
    
    // Override any existing markAllAsRead function to prevent conflicts
    window.markAllAsRead = function() {
        markAllNotificationsAsRead();
    };
    
    // Toggle notification dropdown
    const notificationBtn = document.querySelector('.notification-btn');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    
    if (notificationBtn && notificationDropdown) {
        // Add mobile close button
        const notificationCloseBtn = document.createElement('button');
        notificationCloseBtn.className = 'mobile-close';
        notificationCloseBtn.innerHTML = '<i class="fas fa-times"></i>';
        notificationCloseBtn.style.display = 'none';
        notificationDropdown.appendChild(notificationCloseBtn);
        
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            // Close user menu if open
            const userMenuDropdown = document.querySelector('.user-menu-dropdown');
            const userMenuTrigger = document.querySelector('.user-menu-trigger');
            if (userMenuDropdown) {
                userMenuDropdown.classList.remove('active');
                mobileOverlay.classList.remove('active');
            }
            if (userMenuTrigger) {
                userMenuTrigger.classList.remove('active');
            }
            
            // Toggle notification dropdown
            const isActive = notificationDropdown.classList.contains('active');
            notificationDropdown.classList.toggle('active');
            
            // Handle mobile overlay and close button
            if (window.innerWidth <= 768) {
                mobileOverlay.classList.toggle('active');
                notificationCloseBtn.style.display = isActive ? 'none' : 'flex';
                document.body.style.overflow = isActive ? 'auto' : 'hidden';
            }
        });
        
        // Close button click handler
        notificationCloseBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.remove('active');
            mobileOverlay.classList.remove('active');
            notificationCloseBtn.style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    }
    
    // Toggle user menu dropdown
    const userMenuTrigger = document.querySelector('.user-menu-trigger');
    const userMenuDropdown = document.querySelector('.user-menu-dropdown');
    
    if (userMenuTrigger && userMenuDropdown) {
        // Add mobile close button
        const userCloseBtn = document.createElement('button');
        userCloseBtn.className = 'mobile-close';
        userCloseBtn.innerHTML = '<i class="fas fa-times"></i>';
        userCloseBtn.style.display = 'none';
        userMenuDropdown.appendChild(userCloseBtn);
        
        userMenuTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            // Close notification dropdown if open
            if (notificationDropdown) {
                notificationDropdown.classList.remove('active');
                const notificationCloseBtn = notificationDropdown.querySelector('.mobile-close');
                if (notificationCloseBtn) {
                    notificationCloseBtn.style.display = 'none';
                }
            }
            
            // Toggle user menu
            const isActive = userMenuDropdown.classList.contains('active');
            userMenuDropdown.classList.toggle('active');
            userMenuTrigger.classList.toggle('active');
            
            // Handle mobile overlay and close button
            if (window.innerWidth <= 768) {
                mobileOverlay.classList.toggle('active');
                userCloseBtn.style.display = isActive ? 'none' : 'flex';
                document.body.style.overflow = isActive ? 'auto' : 'hidden';
            }
        });
        
        // Close button click handler
        userCloseBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenuDropdown.classList.remove('active');
            userMenuTrigger.classList.remove('active');
            mobileOverlay.classList.remove('active');
            userCloseBtn.style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    }
    
    // Close dropdowns when clicking overlay
    mobileOverlay.addEventListener('click', function() {
        if (notificationDropdown) {
            notificationDropdown.classList.remove('active');
            const notificationCloseBtn = notificationDropdown.querySelector('.mobile-close');
            if (notificationCloseBtn) {
                notificationCloseBtn.style.display = 'none';
            }
        }
        if (userMenuDropdown) {
            userMenuDropdown.classList.remove('active');
            userMenuTrigger.classList.remove('active');
            const userCloseBtn = userMenuDropdown.querySelector('.mobile-close');
            if (userCloseBtn) {
                userCloseBtn.style.display = 'none';
            }
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
        
        if (notificationDropdown) {
            const notificationCloseBtn = notificationDropdown.querySelector('.mobile-close');
            if (window.innerWidth > 768) {
                if (notificationCloseBtn) notificationCloseBtn.style.display = 'none';
            }
        }
        
        if (userMenuDropdown) {
            const userCloseBtn = userMenuDropdown.querySelector('.mobile-close');
            if (window.innerWidth > 768) {
                userMenuDropdown.classList.remove('active');
                userMenuTrigger.classList.remove('active');
                if (userCloseBtn) userCloseBtn.style.display = 'none';
            }
        }
    });
    
    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            document.body.classList.toggle('sidebar-open');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth <= 768 && 
                sidebar && 
                !sidebar.contains(e.target) && 
                !sidebarToggle.contains(e.target) &&
                document.body.classList.contains('sidebar-open')) {
                document.body.classList.remove('sidebar-open');
            }
        });
    }
    
    // Search functionality
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    }
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

// Mark all notifications as read - this is our main function
function markAllNotificationsAsRead() {
    // Prevent any default behavior or propagation
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
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
    
    // Try multiple possible paths for the mark-notifications-read.php file
    const possiblePaths = [
        'mark-notifications-read.php',
        './mark-notifications-read.php',
        '/mark-notifications-read.php',
        '../mark-notifications-read.php',
        'mark-notification-read.php'  // In case there's a typo in filename
    ];
    
    let currentPathIndex = 0;
    
    function tryNextPath() {
        if (currentPathIndex >= possiblePaths.length) {
            console.error('All paths failed');
            // Hide loading spinner
            if (markText) markText.style.display = 'inline';
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            
            alert('Error: Could not find mark-notifications-read.php file. Please check if the file exists in the correct directory.');
            return;
        }
        
        const currentPath = possiblePaths[currentPathIndex];
        console.log('Trying path:', currentPath);
        
        // Send AJAX request to server to mark notifications as read in the database
        const xhr = new XMLHttpRequest();
        xhr.open('POST', currentPath, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                console.log('AJAX response received. Status:', xhr.status, 'Path:', currentPath);
                console.log('Response text:', xhr.responseText);
                
                if (xhr.status === 404) {
                    // Try next path
                    currentPathIndex++;
                    tryNextPath();
                    return;
                }
                
                // Hide loading spinner
                if (markText) markText.style.display = 'inline';
                if (loadingSpinner) loadingSpinner.style.display = 'none';
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Parsed response:', response);
                        
                        if (response.success) {
                            console.log('Notifications marked as read in database');
                            // Hide the mark all as read button
                            const markAllBtn = document.querySelector('.mark-all-read');
                            if (markAllBtn) {
                                markAllBtn.style.display = 'none';
                            }
                            
                            // Show success message briefly
                            const successMsg = document.createElement('div');
                            successMsg.textContent = 'All notifications marked as read!';
                            successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 10px 20px; border-radius: 5px; z-index: 9999;';
                            document.body.appendChild(successMsg);
                            setTimeout(() => {
                                document.body.removeChild(successMsg);
                            }, 3000);
                            
                        } else {
                            console.error('Server returned error:', response.error);
                            alert('Error: ' + response.error);
                        }
                    } catch (e) {
                        console.error('Failed to parse server response:', xhr.responseText);
                        alert('Server response error: ' + xhr.responseText);
                    }
                } else {
                    console.error('HTTP error:', xhr.status, xhr.responseText);
                    alert('HTTP Error ' + xhr.status + ': ' + xhr.responseText);
                }
            }
        };
        xhr.send('action=mark_all_read');
    }
    
    // Start trying paths
    tryNextPath();
    
    // Return false to prevent any further event handling
    return false;
}

// Override any existing markAllAsRead function globally
window.markAllAsRead = markAllNotificationsAsRead;
</script>
