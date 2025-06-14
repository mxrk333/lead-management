<?php
// Check if user variable exists
if (!isset($user) && isset($_SESSION['user_id'])) {
    $user = getUserById($_SESSION['user_id']);
}
?>

<!-- Mobile Toggle Button (outside sidebar for better accessibility) -->
<button id="mobile-toggle" class="mobile-toggle-btn" aria-label="Toggle Mobile Menu">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
</button>

<div class="sidebar-provider" id="sidebar">
    <div class="sidebar-container">
        <div class="sidebar-header">
            <div class="logo-section">
                <a href="index.php" class="logo-link">
                    <img src="assets/images/logo.png" alt="Inner Sparc Realty Logo" class="logo-image">
                </a>
                <span class="company-name">Inner SPARC Realty Corporation</span>
            </div>
            <button id="sidebar-toggle" class="sidebar-toggle-btn desktop-only" aria-label="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="sidebar-user-section">
            <div class="user-avatar">
                <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                <?php else: ?>
                    <span class="avatar-text"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name" style="color: white;"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></div>
                <div class="user-role" style="color: white;"><?php echo ucfirst(htmlspecialchars($user['role'] ?? 'user')); ?></div>
            </div>
        </div>
        
        <nav class="sidebar-navigation">
            <div class="nav-group">
                <div class="nav-group-content">
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                                <i class="fas fa-tachometer-alt nav-icon"></i>
                                <span class="nav-text">Dashboard</span>
                            </a>
                        </li>
                        
                        <li class="nav-item has-submenu">
                            <button class="nav-link submenu-trigger <?php echo in_array(basename($_SERVER['PHP_SELF']), ['leads.php', 'add-lead.php', 'edit-lead.php', 'lead-details.php', 'lead-conversion.php']) ? 'active' : ''; ?>" data-submenu="leads">
                                <i class="fas fa-users nav-icon"></i>
                                <span class="nav-text">Leads</span>
                                <i class="fas fa-chevron-down submenu-arrow"></i>
                            </button>
                            <ul class="nav-submenu" id="submenu-leads">
                                <li class="submenu-item">
                                    <a href="leads.php" class="submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'leads.php' && !isset($_GET['view']) ? 'active' : ''; ?>">
                                        <i class="fas fa-list submenu-icon"></i>
                                        <span class="submenu-text">All Leads</span>
                                    </a>
                                </li>
                                <li class="submenu-item">
                                    <a href="lead-conversion.php" class="submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'lead-conversion.php' && !isset($_GET['view']) ? 'active' : ''; ?>">
                                        <i class="fas fa-handshake submenu-icon"></i>
                                        <span class="submenu-text">Lead Conversion</span>
                                    </a>
                                </li>
                            </ul>   
                        </li>

                        <?php if (isset($user['role']) && in_array($user['role'], ['admin', 'manager', 'supervisor'])): ?>
                        <li class="nav-item">
                            <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-bar nav-icon"></i>
                                <span class="nav-text">Reports</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item has-submenu">
                            <button class="nav-link submenu-trigger <?php echo basename($_SERVER['PHP_SELF']) == 'dp-stage.php' ? 'active' : ''; ?>" data-submenu="downpayment">
                                <i class="fas fa-chart-line nav-icon"></i>
                                <span class="nav-text">Leads Milestone</span>
                                <i class="fas fa-chevron-down submenu-arrow"></i>
                            </button>
                            <ul class="nav-submenu" id="submenu-downpayment">
                                <li class="submenu-item">
                                    <a href="dp-stage.php" class="submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'dp-stage.php' && !isset($_GET['view']) ? 'active' : ''; ?>">
                                        <i class="fas fa-clock submenu-icon"></i>
                                        <span class="submenu-text">In Progress</span>
                                    </a>
                                </li>
                                <li class="submenu-item">
                                    <a href="dp-stage.php?view=completed" class="submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'dp-stage.php' && isset($_GET['view']) && $_GET['view'] == 'completed' ? 'active' : ''; ?>">
                                        <i class="fas fa-check-circle submenu-icon"></i>
                                        <span class="submenu-text">Completed</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item">
                            <a href="memo.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'memo.php' ? 'active' : ''; ?>">
                                <i class="fas fa-sticky-note nav-icon"></i>
                                <span class="nav-text">Memos</span>
                            </a>
                        </li>
                        
                        <?php if (isset($user['role']) && $user['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a href="users.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'add-user.php', 'edit-user.php', 'user-details.php']) ? 'active' : ''; ?>">
                                <i class="fas fa-user-cog nav-icon"></i>
                                <span class="nav-text">Users</span>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a href="teams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'teams.php' ? 'active' : ''; ?>">
                                <i class="fas fa-users-cog nav-icon"></i>
                                <span class="nav-text">Teams</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isset($user['role']) && in_array($user['role'], ['admin', 'manager', 'supervisor', 'agent'])): ?>
                        <li class="nav-item">
                            <a href="" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == '' ? 'active' : ''; ?>">
                                <i class="fas fa-house nav-icon"></i>
                                <span class="nav-text">Project Listing</span>
                            </a>
                        </li>

                        <li class="nav-item has-submenu">
                            <button class="nav-link submenu-trigger <?php echo in_array(basename($_SERVER['PHP_SELF']), ['handbook.php', 'vast.php', 'links.php']) ? 'active' : ''; ?>" data-submenu="materials">
                                <i class="fas fa-book-open nav-icon"></i>
                                <span class="nav-text">Other Materials</span>
                                <i class="fas fa-chevron-down submenu-arrow"></i>
                            </button>
                            <ul class="nav-submenu" id="submenu-materials">
                                <li class="submenu-item">
                                    <a href="handbook.php" class="submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'handbook.php' && !isset($_GET['view']) ? 'active' : ''; ?>">
                                        <i class="fas fa-book submenu-icon"></i>
                                        <span class="submenu-text">Handbook</span>
                                    </a>
                                </li>
                               <!--<li class="submenu-item">
                                    <a href="vast.php" class="submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'vast.php' && !isset($_GET['view']) ? 'active' : ''; ?>">
                                        <i class="fas fa-chalkboard submenu-icon"></i>
                                        <span class="submenu-text">Vast</span>
                                    </a>
                                </li> -->
                                <li class="submenu-item">
                                    <a href="links.php" class="submenu-link <?php echo basename($_SERVER['PHP_SELF']) == 'links.php' && !isset($_GET['view']) ? 'active' : ''; ?>">
                                        <i class="fas fa-link submenu-icon"></i>
                                        <span class="submenu-text">Links</span>
                                    </a>
                                </li>
                            </ul>   
                        </li>
                        <?php endif; ?>

                        <?php if (isset($user['role']) && $user['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                                <i class="fas fa-cog nav-icon"></i>
                                <span class="nav-text">Settings</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (isset($user['role']) && $user['role'] == 'manager'): ?>
                        <li class="nav-item">
                            <a href="teams.php?team_id=<?php echo htmlspecialchars($user['team_id'] ?? ''); ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'teams.php' ? 'active' : ''; ?>">
                                <i class="fas fa-user-friends nav-icon"></i>
                                <span class="nav-text">My Team</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </div>
</div>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<style>
/* Sidebar Variables */
:root {
    --sidebar-width: 280px;
    --sidebar-width-collapsed: 70px;
    --sidebar-bg: #1e3a5f;
    --sidebar-bg-hover: #2c4d76;
    --sidebar-text: #ffffff;
    --sidebar-text-muted: rgba(255, 255, 255, 0.8);
    --sidebar-border: rgba(255, 255, 255, 0.1);
    --sidebar-active: #2c4d76;
    --sidebar-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    --transition-duration: 0.3s;
    --header-height: 70px;
}

/* Reset and Base Styles */
* {
    box-sizing: border-box;
}

/* Mobile Toggle Button */
.mobile-toggle-btn {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1050;
    width: 50px;
    height: 50px;
    background: var(--sidebar-bg);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.mobile-toggle-btn:hover {
    background: var(--sidebar-bg-hover);
    transform: scale(1.05);
}

.mobile-toggle-btn:active {
    transform: scale(0.95);
}

.hamburger-line {
    width: 20px;
    height: 2px;
    background: white;
    border-radius: 1px;
    transition: all 0.3s ease;
}

.mobile-toggle-btn.active .hamburger-line:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
}

.mobile-toggle-btn.active .hamburger-line:nth-child(2) {
    opacity: 0;
}

.mobile-toggle-btn.active .hamburger-line:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -6px);
}

/* Sidebar Container */
.sidebar-provider {
    position: fixed;
    left: 0;
    top: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
    transition: all var(--transition-duration) ease;
    overflow-y: auto;
    overflow-x: hidden;
    box-shadow: var(--sidebar-shadow);
    z-index: 1040;
    display: flex;
    flex-direction: column;
}

.sidebar-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    width: 100%;
}

/* Sidebar Header */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid var(--sidebar-border);
    min-height: var(--header-height);
    position: relative;
}

.logo-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: all var(--transition-duration) ease;
    overflow: hidden;
    flex: 1;
}

.logo-link {
    display: block;
    text-decoration: none;
}

.logo-image {
    width: 60px;
    height: auto;
    display: block;
    transition: all var(--transition-duration) ease;
}

.company-name {
    display: block;
    text-align: center;
    font-weight: 600;
    font-size: 0.75rem;
    margin-top: 0.5rem;
    color: var(--sidebar-text);
    transition: all var(--transition-duration) ease;
    white-space: nowrap;
    line-height: 1.2;
}

/* Desktop Sidebar Toggle Button */
.sidebar-toggle-btn {
    background: transparent;
    border: none;
    color: var(--sidebar-text);
    cursor: pointer;
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-duration) ease;
    border-radius: 0.375rem;
    font-size: 1.125rem;
    position: relative;
    z-index: 1041;
}

.sidebar-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #4a90e2;
}

/* User Section */
.sidebar-user-section {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--sidebar-border);
    gap: 0.75rem;
}

.user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--sidebar-bg-hover);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 600;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.avatar-text {
    color: var(--sidebar-text);
}

.user-info {
    overflow: hidden;
    transition: all var(--transition-duration) ease;
    opacity: 1;
    flex: 1;
}

.user-name {
    font-weight: 600;
    font-size: 0.875rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #ffffff;
    line-height: 1.2;
    margin-bottom: 0.25rem;
}

.user-role {
    font-size: 0.75rem;
    color: #ffffff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
    opacity: 0.9;
}

/* Navigation */
.sidebar-navigation {
    flex: 1;
    padding: 1rem 0;
    overflow-y: auto;
}

.nav-group {
    width: 100%;
}

.nav-group-content {
    width: 100%;
}

.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    width: 100%;
}

.nav-item {
    margin-bottom: 0.25rem;
    width: 100%;
}

/* FIXED: Navigation Links - Consistent Left Alignment */
.nav-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: var(--sidebar-text);
    text-decoration: none;
    transition: all var(--transition-duration) ease;
    border-radius: 0.5rem;
    margin: 0 0.5rem;
    position: relative;
    overflow: hidden;
    background: transparent;
    border: none;
    width: calc(100% - 1rem);
    cursor: pointer;
    font-size: 0.875rem;
    font-family: inherit;
    /* CRITICAL: Force left alignment for ALL nav links */
    justify-content: flex-start !important;
    text-align: left !important;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--sidebar-text);
    text-decoration: none;
    transform: translateX(2px);
}

.nav-link.active {
    background: var(--sidebar-active);
    color: var(--sidebar-text);
    font-weight: 500;
}

/* FIXED: Submenu Trigger - Force Left Alignment */
.submenu-trigger {
    /* Override any centering styles */
    justify-content: flex-start !important;
    text-align: left !important;
    display: flex !important;
    align-items: center !important;
}

/* Navigation Icons */
.nav-icon {
    margin-right: 0.75rem;
    width: 20px;
    text-align: center;
    font-size: 1rem;
    transition: all var(--transition-duration) ease;
    flex-shrink: 0;
    display: inline-block;
}

/* Navigation Text */
.nav-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: all var(--transition-duration) ease;
    flex: 1;
    /* CRITICAL: Force text left alignment */
    text-align: left !important;
}

/* FIXED: Submenu Arrow - Positioned to the right */
.submenu-arrow {
    font-size: 0.75rem;
    transition: transform var(--transition-duration) ease;
    margin-left: auto; /* Push arrow to the right */
    flex-shrink: 0;
}

.has-submenu.active .submenu-arrow {
    transform: rotate(180deg);
}

/* Submenu Styles */
.nav-submenu {
    display: none;
    list-style: none;
    padding: 0;
    margin: 0.25rem 0 0 0;
    padding-left: 2.5rem;
    animation: slideDown 0.3s ease;
}

.has-submenu.active .nav-submenu {
    display: block;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 200px;
    }
}

.submenu-item {
    margin-bottom: 0.125rem;
}

.submenu-link {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    color: var(--sidebar-text-muted);
    font-size: 0.8125rem;
    text-decoration: none;
    transition: all var(--transition-duration) ease;
    border-radius: 0.375rem;
    margin: 0 0.5rem;
}

.submenu-link:hover {
    color: var(--sidebar-text);
    background: rgba(255, 255, 255, 0.1);
    text-decoration: none;
    transform: translateX(2px);
}

.submenu-link.active {
    color: var(--sidebar-text);
    background: rgba(255, 255, 255, 0.15);
    font-weight: 500;
}

.submenu-icon {
    margin-right: 0.5rem;
    font-size: 0.875rem;
    width: 16px;
    text-align: center;
    flex-shrink: 0;
}

.submenu-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Main Content Layout Adjustments */
.main-content {
    margin-left: var(--sidebar-width);
    transition: margin-left var(--transition-duration) ease;
    width: calc(100% - var(--sidebar-width));
    min-height: 100vh;
    position: relative;
    z-index: 1;
}

/* Scrollbar Styling */
.sidebar-provider::-webkit-scrollbar {
    width: 4px;
}

.sidebar-provider::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-provider::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}

.sidebar-provider::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Animation for smooth transitions */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.nav-item {
    animation: slideIn 0.3s ease forwards;
}

.nav-item:nth-child(1) { animation-delay: 0.1s; }
.nav-item:nth-child(2) { animation-delay: 0.2s; }
.nav-item:nth-child(3) { animation-delay: 0.3s; }
.nav-item:nth-child(4) { animation-delay: 0.4s; }
.nav-item:nth-child(5) { animation-delay: 0.5s; }
.nav-item:nth-child(6) { animation-delay: 0.6s; }
.nav-item:nth-child(7) { animation-delay: 0.7s; }
.nav-item:nth-child(8) { animation-delay: 0.8s; }

/* ======= DESKTOP ONLY STYLES ======= */
@media (min-width: 769px) {
    /* Desktop collapsed state */
    .sidebar-provider.collapsed {
        width: var(--sidebar-width-collapsed);
    }
    
    .sidebar-provider.collapsed .logo-section {
        opacity: 0;
        max-width: 0;
        margin: 0;
        padding: 0;
        visibility: hidden;
    }
    
    .sidebar-provider.collapsed .sidebar-header {
        justify-content: center;
        padding: 1rem 0.5rem;
    }
    
    .sidebar-provider.collapsed .user-info {
        opacity: 0;
        max-width: 0;
        margin: 0;
        visibility: hidden;
    }
    
    .sidebar-provider.collapsed .nav-text,
    .sidebar-provider.collapsed .submenu-text {
        opacity: 0;
        max-width: 0;
        margin: 0;
        visibility: hidden;
    }
    
    .sidebar-provider.collapsed .nav-link {
        justify-content: center;
        padding: 1rem 0;
    }
    
    .sidebar-provider.collapsed .nav-icon {
        margin-right: 0;
        font-size: 1.125rem;
    }
    
    .sidebar-provider.collapsed .submenu-arrow {
        display: none;
    }
    
    .sidebar-provider.collapsed .nav-submenu {
        display: none !important;
    }
    
    .sidebar-provider.collapsed ~ .container .main-content,
    .sidebar-provider.collapsed + .main-content {
        margin-left: var(--sidebar-width-collapsed);
        width: calc(100% - var(--sidebar-width-collapsed));
    }
}

/* ======= MOBILE RESPONSIVE STYLES ======= */
@media (max-width: 768px) {
    /* Show mobile toggle */
    .mobile-toggle-btn {
        display: flex;
    }
    
    /* Hide desktop toggle */
    .desktop-only {
        display: none !important;
    }
    
    /* Mobile sidebar positioning */
    .sidebar-provider {
        transform: translateX(-100%);
        width: var(--sidebar-width);
        max-width: 85vw;
    }
    
    .sidebar-provider.mobile-open {
        transform: translateX(0);
    }
    
    /* Mobile overlay */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1030;
        opacity: 0;
        transition: opacity var(--transition-duration) ease;
    }
    
    .sidebar-overlay.active {
        display: block;
        opacity: 1;
    }
    
    /* Mobile content positioning */
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
        padding-top: 80px;
    }
    
    /* CRITICAL: Force user section visibility on mobile */
    .sidebar-user-section {
        display: flex !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    .user-info {
        display: block !important;
        opacity: 1 !important;
        max-width: none !important;
        visibility: visible !important;
    }
    
    .user-name, 
    .user-role {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
        max-width: none !important;
    }
    
    /* Mobile navigation adjustments */
    .nav-link {
        padding: 1rem;
        font-size: 0.9rem;
    }
    
    .nav-icon {
        font-size: 1.1rem;
        margin-right: 1rem;
    }
    
    .submenu-link {
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
    }
    
    /* Better touch targets for mobile */
    .nav-link,
    .submenu-link {
        min-height: 48px;
    }
}

/* Extra small devices */
@media (max-width: 480px) {
    .sidebar-provider {
        width: 90vw;
        max-width: 300px;
    }
    
    .mobile-toggle-btn {
        width: 44px;
        height: 44px;
        top: 12px;
        left: 12px;
    }
    
    .hamburger-line {
        width: 18px;
    }
    
    .company-name {
        font-size: 0.7rem;
    }
    
    .nav-link {
        padding: 0.875rem;
    }
    
    .submenu-link {
        padding: 0.625rem 0.875rem;
    }
}

/* Landscape mobile devices */
@media (max-width: 768px) and (orientation: landscape) {
    .sidebar-provider {
        width: 60vw;
        max-width: 280px;
    }
    
    .main-content {
        padding-top: 60px;
    }
}

/* High DPI displays */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .logo-image {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Sidebar script loading...');
    
    const mobileToggle = document.getElementById('mobile-toggle');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const userInfo = document.querySelector('.user-info');
    console.log('User role:', <?php echo json_encode($user['role'] ?? 'unknown'); ?>);
    
    // Function to check if we're on mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    // Function to toggle sidebar
    function toggleSidebar(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (isMobile()) {
            // Mobile behavior - slide in/out
            const isOpen = sidebar.classList.contains('mobile-open');
            
            if (isOpen) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        } else {
            // Desktop behavior - collapse/expand
            sidebar.classList.toggle('collapsed');
        }
    }
    
    // Function to open mobile sidebar
    function openMobileSidebar() {
        sidebar.classList.add('mobile-open');
        sidebarOverlay.classList.add('active');
        mobileToggle.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Force user info visibility in mobile mode
        if (userInfo) {
            userInfo.style.display = 'block';
            userInfo.style.opacity = '1';
            userInfo.style.visibility = 'visible';
        }
    }
    
    // Function to close mobile sidebar
    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        sidebarOverlay.classList.remove('active');
        mobileToggle.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Event listeners for toggle buttons
    if (mobileToggle) {
        mobileToggle.addEventListener('click', toggleSidebar);
        mobileToggle.addEventListener('touchstart', function(e) {
            e.preventDefault();
            toggleSidebar(e);
        });
    }
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Close sidebar when clicking the overlay (mobile)
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeMobileSidebar);
        sidebarOverlay.addEventListener('touchstart', function(e) {
            e.preventDefault();
            closeMobileSidebar();
        });
    }
    
    // Handle submenu toggles
    const submenuTriggers = document.querySelectorAll('.submenu-trigger');
    submenuTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parentItem = this.closest('.nav-item');
            const isActive = parentItem.classList.contains('active');
            
            // Close all other submenus
            document.querySelectorAll('.nav-item.has-submenu').forEach(item => {
                if (item !== parentItem) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle current submenu
            parentItem.classList.toggle('active', !isActive);
        });
    });
    
    // Auto-expand submenu if on specific pages
        function autoExpandSubmenus() {
        const currentPage = window.location.pathname.split('/').pop();
        
        // Auto-expand leads submenu
        if (['leads.php', 'add-lead.php', 'edit-lead.php', 'lead-details.php', 'lead-conversion.php'].includes(currentPage)) {
            const leadsSubmenu = document.querySelector('[data-submenu="leads"]');
            if (leadsSubmenu) {
                leadsSubmenu.closest('.nav-item').classList.add('active');
            }
        }
        
        // Auto-expand downpayment submenu
        if (currentPage.includes('dp-stage.php')) {
            const dpSubmenu = document.querySelector('[data-submenu="downpayment"]');
            if (dpSubmenu) {
                dpSubmenu.closest('.nav-item').classList.add('active');
            }   
        }
        
        // Auto-expand materials submenu
        if (['handbook.php', 'vast.php', 'links.php'].includes(currentPage)) {
            const materialsSubmenu = document.querySelector('[data-submenu="materials"]');
            if (materialsSubmenu) {
                materialsSubmenu.closest('.nav-item').classList.add('active');
            }
        }
        console.log('Current page:', currentPage);
        console.log('Materials submenu element:', document.querySelector('[data-submenu="materials"]'));
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (!isMobile() && sidebar.classList.contains('mobile-open')) {
            closeMobileSidebar();
        }
        
        // Force user info visibility on mobile
        if (userInfo && isMobile()) {
            userInfo.style.display = 'block';
            userInfo.style.opacity = '1';
            userInfo.style.visibility = 'visible';
        }
    });
    
    // Handle clicks outside sidebar on mobile
    document.addEventListener('click', function(e) {
        if (isMobile() && 
            sidebar.classList.contains('mobile-open') &&
            !sidebar.contains(e.target) && 
            !mobileToggle.contains(e.target)) {
            closeMobileSidebar();
        }
    });
    
    // ESC key to close mobile sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isMobile() && sidebar.classList.contains('mobile-open')) {
            closeMobileSidebar();
        }
    });
    
    // Initialize
    autoExpandSubmenus();
    
    // Close mobile sidebar when clicking on navigation links
    const regularNavLinks = document.querySelectorAll('.nav-link:not(.submenu-trigger), .submenu-link');
    regularNavLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (isMobile()) {
                setTimeout(closeMobileSidebar, 150);
            }
        });
    });
    
    // Force user info visibility on mobile
    if (isMobile() && userInfo) {
        userInfo.style.display = 'block';
        userInfo.style.opacity = '1';
        userInfo.style.visibility = 'visible';
    }
    
    console.log('Sidebar script loaded successfully');
});
</script>