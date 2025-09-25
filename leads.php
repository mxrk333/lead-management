<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Add this function right after the includes and before the canEditLead function
function isSuperUser($username) {
    $superusers = [
        'markpatigayon.itadmin',
        'gabriellibacao.founder',
        'romeocorberta.itdept'
    ];
    return in_array($username, $superusers);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Function to check if current user can edit a lead
function canEditLead($lead, $current_user_id) {
    global $user; // Access the global user variable

    // Check if user is a superuser
    if (isSuperUser($user['username'])) {
        return true;
    }

    // User can edit if they are the assigned agent
    return ($lead['user_id'] == $current_user_id);
}

// Helper functions for active leads (excluding closed deals)
function getActiveLeads($user_id, $role, $username = null) {
    $conn = getDbConnection();

    $whereClause = "WHERE l.status != 'Closed Deal'";

    // Superusers can see all leads
    if ($username && isSuperUser($username)) {
        // No additional WHERE clause needed - show all active leads
    } elseif ($role === 'agent') {
        $whereClause .= " AND l.user_id = $user_id";
    } elseif ($role === 'supervisor' || $role === 'manager') {
        $team_query = "SELECT team_id FROM users WHERE id = $user_id";
        $team_result = mysqli_query($conn, $team_query);
        $team_data = mysqli_fetch_assoc($team_result);
        if ($team_data && $team_data['team_id']) {
            $whereClause .= " AND u.team_id = " . $team_data['team_id'];
        }
    }

    $query = "
        SELECT l.*, u.name as agent_name, u.profile_picture as agent_profile_picture, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
    ";

    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Build a combined WHERE clause for active leads with optional filters
function buildActiveLeadsWhereClause($conn, $user_id, $role, $username, $filters) {
    $conditions = ["l.status != 'Closed Deal'"];

    // Role-based visibility unless superuser
    if (!($username && isSuperUser($username))) {
        if ($role === 'agent') {
            $conditions[] = "l.user_id = $user_id";
        } elseif ($role === 'supervisor' || $role === 'manager') {
            $team_query = "SELECT team_id FROM users WHERE id = $user_id";
            $team_result = mysqli_query($conn, $team_query);
            $team_data = mysqli_fetch_assoc($team_result);
            if ($team_data && $team_data['team_id']) {
                $conditions[] = "u.team_id = " . $team_data['team_id'];
            }
        }
    }

    // Apply filters if present
    if (!empty($filters['search'])) {
        $s = mysqli_real_escape_string($conn, $filters['search']);
        $conditions[] = "(l.client_name LIKE '%$s%' OR l.email LIKE '%$s%' OR l.phone LIKE '%$s%')";
    }

    if (!empty($filters['status'])) {
        $status = mysqli_real_escape_string($conn, $filters['status']);
        $conditions[] = "l.status = '$status'";
    }

    if (!empty($filters['temperature'])) {
        $temperature = mysqli_real_escape_string($conn, $filters['temperature']);
        $conditions[] = "l.temperature = '$temperature'";
    }

    if (!empty($filters['source'])) {
        $source = mysqli_real_escape_string($conn, $filters['source']);
        $conditions[] = "l.source = '$source'";
    }

    if (!empty($filters['my_leads'])) {
        // Explicitly restrict to current user's leads
        $conditions[] = "l.user_id = $user_id";
    }

    if (!empty($filters['agent'])) {
        // Filter by specific agent (for superusers and managers)
        $agent_id = mysqli_real_escape_string($conn, $filters['agent']);
        $conditions[] = "l.user_id = '$agent_id'";
    }

    return 'WHERE ' . implode(' AND ', $conditions);
}

// Get all active leads with combined filters applied (no pagination)
function getFilteredActiveLeads($user_id, $role, $username, $filters) {
    $conn = getDbConnection();
    $whereClause = buildActiveLeadsWhereClause($conn, $user_id, $role, $username, $filters);

    $query = "
        SELECT l.*, u.name as agent_name, u.profile_picture as agent_profile_picture, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
    ";

    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Get paginated active leads with combined filters
function getPaginatedFilteredActiveLeads($user_id, $role, $username, $filters, $offset, $limit) {
    $conn = getDbConnection();
    $whereClause = buildActiveLeadsWhereClause($conn, $user_id, $role, $username, $filters);

    $query = "
        SELECT l.*, u.name as agent_name, u.profile_picture as agent_profile_picture, t.name as team_name
        FROM leads l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN teams t ON u.team_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
        LIMIT $offset, $limit
    ";

    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to get all agents for filtering (superusers see all, managers see team only)
function getAllAgents($team_id = null) {
    $conn = getDbConnection();

    if ($team_id) {
        // Get agents from specific team
        $query = "SELECT id, name, profile_picture FROM users WHERE role = 'agent' AND team_id = ? ORDER BY name ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // Get all agents (for superusers)
        $query = "SELECT id, name, profile_picture FROM users WHERE role = 'agent' ORDER BY name ASC";
        $result = mysqli_query($conn, $query);
    }

    $agents = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $agents[] = $row;
    }

    if ($team_id) {
        $stmt->close();
    }

    return $agents;
}

function getClosedDealsCount($user_id, $role) {
    $conn = getDbConnection();

    $whereClause = "WHERE l.status = 'Closed Deal'";

    if ($role === 'agent') {
        $whereClause .= " AND l.user_id = $user_id";
    } elseif ($role === 'supervisor' || $role === 'manager') {
        $team_query = "SELECT team_id FROM users WHERE id = $user_id";
        $team_result = mysqli_query($conn, $team_query);
        $team_data = mysqli_fetch_assoc($team_result);
        if ($team_data && $team_data['team_id']) {
            $whereClause .= " AND u.team_id = " . $team_data['team_id'];
        }
    }

    $query = "SELECT COUNT(*) as count FROM leads l LEFT JOIN users u ON l.user_id = u.id $whereClause";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    return $data['count'];
}

function getUniqueActiveStatuses() {
    $conn = getDbConnection();
    $query = "SELECT DISTINCT status FROM leads WHERE status != 'Closed Deal' ORDER BY status";
    $result = mysqli_query($conn, $query);
    $statuses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $statuses[] = $row['status'];
    }
    return $statuses;
}

function getUniqueSources() {
    $conn = getDbConnection();
    $query = "SELECT DISTINCT source FROM leads WHERE source IS NOT NULL AND source != '' ORDER BY source";
    $result = mysqli_query($conn, $query);
    $sources = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $sources[] = $row['source'];
    }
    return $sources;
}

function getUniqueTemperatures() {
    $conn = getDbConnection();
    $query = "SELECT DISTINCT temperature FROM leads WHERE temperature IS NOT NULL AND temperature != '' ORDER BY temperature";
    $result = mysqli_query($conn, $query);
    $temperatures = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $temperatures[] = $row['temperature'];
    }
    return $temperatures;
}

// Handle AJAX requests for live filtering
if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    // Collect filters from POST data
    $filters = [
        'search' => isset($_POST['search']) ? trim($_POST['search']) : '',
        'status' => isset($_POST['status']) ? trim($_POST['status']) : '',
        'temperature' => isset($_POST['temperature']) ? trim($_POST['temperature']) : '',
        'source' => isset($_POST['source']) ? trim($_POST['source']) : '',
        'my_leads' => (isset($_POST['my_leads']) && $_POST['my_leads'] == '1') ? '1' : '',
        'agent' => isset($_POST['agent']) ? trim($_POST['agent']) : ''
    ];
    
    // Pagination settings for AJAX
    $leads_per_page = 10;
    $current_page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $offset = ($current_page - 1) * $leads_per_page;
    
    // Get filtered data
    $filtered_all = getFilteredActiveLeads($user_id, $user['role'], $user['username'], $filters);
    $total_leads = count($filtered_all);
    $leads = getPaginatedFilteredActiveLeads($user_id, $user['role'], $user['username'], $filters, $offset, $leads_per_page);
    
    // Calculate total pages
    $total_pages = ceil(max(0, $total_leads) / $leads_per_page);
    if ($total_pages > 0) {
        $current_page = min($current_page, $total_pages);
    }
    
    // Recalculate summary data with filters applied
    $all_leads = $filtered_all; // Use filtered data for summary cards
    
    // Update temperature counts based on filtered data
    $hotLeads = array_filter($all_leads, function($lead) {
        return $lead['temperature'] === 'Hot';
    });
    $hotLeadsCount = count($hotLeads);
    
    $warmLeads = array_filter($all_leads, function($lead) {
        return $lead['temperature'] === 'Warm';
    });
    $warmLeadsCount = count($warmLeads);
    
    $coldLeads = array_filter($all_leads, function($lead) {
        return $lead['temperature'] === 'Cold';
    });
    $coldLeadsCount = count($coldLeads);
    
    // Get my leads count from filtered data
    $myLeads = array_filter($all_leads, function($lead) use ($user_id) {
        return $lead['user_id'] == $user_id;
    });
    $myLeadsCount = count($myLeads);
    
    // Summary card title and count for AJAX
    $my_leads_filter_active = $filters['my_leads'] !== '';
    $activeCardTitle = $my_leads_filter_active ? 'My Leads' : 'All Leads';
    $activeCardCount = $my_leads_filter_active ? count($filtered_all) : count($filtered_all);
    
    // Function to build pagination URL for AJAX
    function buildPaginationUrlAjax($page, $filters) {
        $params = $filters;
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
    
    // Generate only the necessary HTML and return it
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>AJAX Response</title></head>
    <body>
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-icon" style="background: var(--primary-light); color: var(--primary);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="summary-info">
                    <h3><?php echo $activeCardTitle; ?></h3>
                    <p><?php echo $activeCardCount; ?></p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon" style="background: var(--danger-light); color: var(--danger);">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="summary-info">
                    <h3>Hot Leads</h3>
                    <p><?php echo $hotLeadsCount; ?></p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                    <i class="fas fa-thermometer-half"></i>
                </div>
                <div class="summary-info">
                    <h3>Warm Leads</h3>
                    <p><?php echo $warmLeadsCount; ?></p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon" style="background: var(--info-light); color: var(--info);">
                    <i class="fas fa-icicles"></i>
                </div>
                <div class="summary-info">
                    <h3>Cold Leads</h3>
                    <p><?php echo $coldLeadsCount; ?></p>
                </div>
            </div>
        </div>

        <div class="leads-table-container">
            <table class="leads-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Temperature</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Agent</th>
                        <th>Created</th>
                        <th>Actions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem;">
                            <div style="color: var(--gray-400);">
                                <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <p>No active leads found</p>
                                <?php if (!empty(array_filter($filters))): ?>
                                    <p style="font-size: 0.875rem; margin-top: 0.5rem;">
                                        Try adjusting your filters or <a href="leads.php" style="color: var(--primary);">clear all filters</a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td><?php
                                // Check if user can see full contact info (superuser or lead owner)
                                if (isSuperUser($user['username']) || $lead['user_id'] == $user_id) {
                                    echo htmlspecialchars($lead['client_name']);
                                } else {
                                    // Mask name for privacy
                                    $name = $lead['client_name'];
                                    $spacePos = strpos($name, ' ');
                                    if ($spacePos !== false && $spacePos > 2) {
                                        $maskedName = substr($name, 0, 2) . str_repeat('*', $spacePos - 2) . substr($name, $spacePos);
                                        echo htmlspecialchars($maskedName);
                                    } else {
                                        echo '************';
                                    }
                                }
                            ?></td>
                            <td>
                                <?php
                                 // Check if user can see full contact info (superuser or lead owner)
                                if (isSuperUser($user['username']) || $lead['user_id'] == $user_id) {
                                    echo htmlspecialchars($lead['email']);
                                } else {
                                    // Mask email for privacy
                                    $email = $lead['email'];
                                    $atPos = strpos($email, '@');
                                    if ($atPos !== false && $atPos > 2) {
                                        $maskedEmail = substr($email, 0, 2) . str_repeat('*', $atPos - 2) . substr($email, $atPos);
                                        echo htmlspecialchars($maskedEmail);
                                    } else {
                                        echo '***@***';
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                 // Check if user can see full contact info (superuser or lead owner)
                                if (isSuperUser($user['username']) || $lead['user_id'] == $user_id) {
                                    echo htmlspecialchars($lead['phone']);
                                } else {
                                    // Mask phone for privacy
                                    $phone = $lead['phone'];
                                    if (strlen($phone) > 4) {
                                        $maskedPhone = substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 6) . substr($phone, -3);
                                        echo htmlspecialchars($maskedPhone);
                                    } else {
                                        echo '***-***';
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <span class="temperature <?php echo strtolower($lead['temperature']); ?>">
                                    <?php echo htmlspecialchars($lead['temperature']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($lead['status']); ?></td>
                            <td><?php echo htmlspecialchars($lead['source']); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <?php
                                    $profile_picture = $lead['agent_profile_picture'] ?? '';
                                    $agent_name = $lead['agent_name'] ?? 'Unknown Agent';

                                    $possible_paths = [
                                        'uploads/profile_pictures/' . $profile_picture,
                                        'profile_pictures/' . $profile_picture,
                                        'images/profiles/' . $profile_picture,
                                        'assets/images/profiles/' . $profile_picture,
                                        $profile_picture // In case it's already a full path
                                    ];
                                    
                                    $working_path = null;
                                    $image_exists = false;
                                    
                                    if (!empty($profile_picture) && trim($profile_picture) !== '') {
                                        foreach ($possible_paths as $path) {
                                            if (file_exists($path)) {
                                                $working_path = $path;
                                                $image_exists = true;
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                        <?php if ($image_exists && $working_path): ?>
                                            <img src="<?php echo htmlspecialchars($working_path); ?>"
                                                 alt="<?php echo htmlspecialchars($agent_name); ?>"
                                                 class="agent-profile-picture"
                                                 style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb;"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="agent-profile-fallback" style="display: none; width: 32px; height: 32px; border-radius: 50%; background: #f3f4f6; color: #6b7280; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 600;">
                                                <?php
                                                $initials = '';
                                                $nameParts = explode(' ', trim($agent_name));
                                                foreach ($nameParts as $part) {
                                                    if (!empty($part)) {
                                                        $initials .= strtoupper($part[0]);
                                                        if (strlen($initials) >= 2) break;
                                                    }
                                                }
                                                echo htmlspecialchars($initials ?: 'U');
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="agent-profile-fallback" style="width: 32px; height: 32px; border-radius: 50%; background: #f3f4f6; color: #6b7280; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 600;">
                                                <?php
                                                $initials = '';
                                                $nameParts = explode(' ', trim($agent_name));
                                                foreach ($nameParts as $part) {
                                                    if (!empty($part)) {
                                                        $initials .= strtoupper($part[0]);
                                                        if (strlen($initials) >= 2) break;
                                                    }
                                                }
                                                echo htmlspecialchars($initials ?: 'U');
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    <span><?php echo htmlspecialchars($agent_name); ?></span>
                                </div>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($lead['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="lead-details.php?id=<?php echo $lead['id']; ?>" class="btn-view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <?php if (canEditLead($lead, $user_id)): ?>
                                        <a href="edit-lead.php?id=<?php echo $lead['id']; ?>" class="btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn-edit disabled"
                                                title="You can only edit leads assigned to you"
                                                disabled>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (isSuperUser($user['username']) || $lead['user_id'] == $user_id): ?>
                                        <button class="btn-call" onclick="openCallModal('<?php echo htmlspecialchars($lead['client_name']); ?>', '<?php echo htmlspecialchars($lead['phone']); ?>')" title="Call">
                                            <i class="fas fa-phone"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-call disabled"
                                                title="You can only call leads assigned to you"
                                                disabled>
                                            <i class="fas fa-phone"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button class="btn-delete" onclick="deleteLead(<?php echo $lead['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination for AJAX -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div>
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo buildPaginationUrlAjax($current_page - 1, $filters); ?>" class="pagination-button">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $pagination_range = getPaginationRange($current_page, $total_pages);
                foreach ($pagination_range as $page):
                    if ($page === '...'):
                ?>
                    <span class="pagination-button disabled">...</span>
                <?php else: ?>
                    <a href="<?php echo buildPaginationUrlAjax($page, $filters); ?>"
                       class="pagination-button <?php echo ($current_page == $page) ? 'active' : ''; ?>">
                        <?php echo $page; ?>
                    </a>
                <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo buildPaginationUrlAjax($current_page + 1, $filters); ?>" class="pagination-button">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>

                <div class="pagination-info">
                    Showing <?php echo min(($current_page - 1) * $leads_per_page + 1, $total_leads); ?> to
                    <?php echo min($current_page * $leads_per_page, $total_leads); ?> of
                    <?php echo $total_leads; ?> active leads
                </div>
            </div>
        </div>
        <?php endif; ?>
    </body>
    </html>
    <?php
    $response = ob_get_clean();
    echo $response;
    exit;
}

// Pagination settings
$leads_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $leads_per_page;

// Get all active leads based on user role (for summary cards and default view)
$all_leads = getActiveLeads($user_id, $user['role'], $user['username']);

// Collect filters (allow multiple to work together)
$filters = [
    'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
    'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
    'temperature' => isset($_GET['temperature']) ? trim($_GET['temperature']) : '',
    'source' => isset($_GET['source']) ? trim($_GET['source']) : '',
    'my_leads' => (isset($_GET['my_leads']) && $_GET['my_leads'] == '1') ? '1' : '',
    'agent' => isset($_GET['agent']) ? trim($_GET['agent']) : ''
];

// Mirror to individual variables used in the template
$search = $filters['search'];
$status = $filters['status'];
$temperature = $filters['temperature'];
$source = $filters['source'];
$agent = $filters['agent'];

// Flags for UI highlighting
$search_active = $filters['search'] !== '';
$status_filter_active = $filters['status'] !== '';
$temp_filter_active = $filters['temperature'] !== '';
$source_filter_active = $filters['source'] !== '';
$my_leads_filter_active = $filters['my_leads'] !== '';
$agent_filter_active = $filters['agent'] !== '';

// Determine dataset using combined filters
$filtered_all = getFilteredActiveLeads($user_id, $user['role'], $user['username'], $filters);
$total_leads = count($filtered_all);
$leads = getPaginatedFilteredActiveLeads($user_id, $user['role'], $user['username'], $filters, $offset, $leads_per_page);

// Calculate total pages
$total_pages = ceil(max(0, $total_leads) / $leads_per_page);

// Adjust current page if it exceeds total pages
if ($total_pages > 0) {
    $current_page = min($current_page, $total_pages);
}

// Function to build pagination URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Calculate which page numbers to show
function getPaginationRange($current_page, $total_pages, $range = 2) {
    $result = [];
    $start = max(1, $current_page - $range);
    $end = min($total_pages, $current_page + $range);

    if ($start > 1) {
        $result[] = 1;
        if ($start > 2) {
            $result[] = '...';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $result[] = $i;
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $result[] = '...';
        }
        $result[] = $total_pages;
    }

    return $result;
}

// Get temperature counts (ACTIVE ONLY)
$hotLeads = array_filter($all_leads, function($lead) {
    return $lead['temperature'] === 'Hot';
});
$hotLeadsCount = count($hotLeads);

$warmLeads = array_filter($all_leads, function($lead) {
    return $lead['temperature'] === 'Warm';
});
$warmLeadsCount = count($warmLeads);

$coldLeads = array_filter($all_leads, function($lead) {
    return $lead['temperature'] === 'Cold';
});
$coldLeadsCount = count($coldLeads);

// Get my leads count
$myLeads = array_filter($all_leads, function($lead) use ($user_id) {
    return $lead['user_id'] == $user_id;
});
$myLeadsCount = count($myLeads);

// Get closed deals count
$closedDealsCount = getClosedDealsCount($user_id, $user['role']);

// Get filter options from database (EXCLUDING CLOSED DEAL STATUS)
$sources = getUniqueSources();
$temperatures = getUniqueTemperatures();
$statuses = getUniqueActiveStatuses();

// Check if current user is superuser
$isSuperUser = isSuperUser($user['username']);

// Build allowed users list for Agent typeahead
$filterUsers = [];
$managerTeamId = null;

if ($isSuperUser) {
    // Super Admin: all users
    $conn = getDbConnection();
    $q = "SELECT id, name, role, team_id, profile_picture FROM users ORDER BY name ASC";
    if ($res = mysqli_query($conn, $q)) {
        while ($row = mysqli_fetch_assoc($res)) {
            $filterUsers[] = $row;
        }
    }
} elseif ($user['role'] === 'manager') {
    // Manager: only supervisors and agents within their team
    $conn = getDbConnection();
    $team_query = "SELECT team_id FROM users WHERE id = ?";
    $stmt = $conn->prepare($team_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $team_data = $result->fetch_assoc();
    $stmt->close();

    if ($team_data && $team_data['team_id']) {
        $managerTeamId = (int)$team_data['team_id'];
        $q = "SELECT id, name, role, team_id, profile_picture FROM users WHERE team_id = ? AND role IN ('agent','supervisor') ORDER BY role ASC, name ASC";
        $stmt2 = $conn->prepare($q);
        $stmt2->bind_param("i", $managerTeamId);
        $stmt2->execute();
        $r2 = $stmt2->get_result();
        while ($row = $r2->fetch_assoc()) {
            $filterUsers[] = $row;
        }
        $stmt2->close();
    }
}

// Resolve selected agent name for typeahead display
$selectedAgentName = '';
if (!empty($agent) && !empty($filterUsers)) {
    foreach ($filterUsers as $a) {
        if (strval($a['id']) === strval($agent)) {
            $selectedAgentName = $a['name'];
            break;
        }
    }
}

// Summary card title and count: switch to "My Leads" when filter is active
$activeCardTitle = $my_leads_filter_active ? 'My Leads' : 'All Leads';
$activeCardCount = $my_leads_filter_active ? count($filtered_all) : count($all_leads);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads - InnerSPARC Lead Management System</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS styles remain the same as in original file */
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

        .leads-page {
            flex: 1;
            padding: 1.5rem;
            width: 100%;
            margin: 0;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
            position: relative;
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

        .superuser-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            background-color: #10b981;
            color: white;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .superuser-badge i {
            margin-right: 0.25rem;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-add:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-conversion {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--success);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-conversion:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .summary-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .summary-icon {
            width: 3rem;
            height: 3rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.25rem;
        }

        .summary-info {
            flex: 1;
        }

        .summary-info h3 {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin: 0 0 0.25rem 0;
            font-weight: 500;
        }

        .summary-info p {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .filters-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            width: 100%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(5, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 0;
            width: 100%;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: auto;
        }

        /* Make Agent filter span all columns and fill full width */
        .filter-select-container[data-label="Agent"] {
            grid-column: 1 / -1;
            justify-self: stretch;
        }

        .search-form > * {
            justify-self: center;
            width: 100%;
            max-width: 250px;
        }

        .search-form input,
        .filter-select-container select {
            width: 100%;
            min-width: 180px;
            max-width: 250px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: var(--gray-50);
            height: 2.75rem;
            text-align: left;
            margin: 0 auto;
        }

        .search-form input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background-color: white;
        }

        .filter-options {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            width: 100%;
        }

        .filter-select-container {
            position: relative;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: left;
        }

        .filter-select-container::after {
            content: attr(data-label);
            position: absolute;
            top: -0.625rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 0 0.25rem;
            font-size: 0.75rem;
            background-color: white;
            color: var(--gray-500);
            pointer-events: none;
            white-space: nowrap;
        }

        .filter-select-container select {
            width: 100%;
            min-width: 180px;
            max-width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            appearance: none;
            background-color: var(--gray-50);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            transition: all 0.2s ease;
            cursor: pointer;
            color: var(--gray-700);
            height: 2.75rem;
            text-align: left;
            text-align-last: left;
        }

        .filter-select-container select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background-color: white;
        }

        .search-form input {
            text-align: left;
        }

        /* Agent autocomplete styles */
        .agent-typeahead-wrapper {
            position: relative;
            width: 100%;
            max-width: 600px;
        }

        .agent-input {
            width: 100%;
            min-width: 300px;
            max-width: 100%;
            padding: 0.75rem 1.25rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background-color: var(--gray-50);
            height: 2.75rem;
        }

        .agent-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background: white;
        }

        .agent-suggestions {
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--gray-200);
            border-top: none;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: var(--shadow-md);
            z-index: 20;
            display: none;
            max-height: 300px;
            overflow-y: auto;
        }

        .agent-suggestion-item {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .agent-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--gray-200);
            color: var(--gray-700);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            overflow: hidden;
            flex-shrink: 0;
        }

        /* Added styles for profile picture display */
        .agent-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .agent-profile-picture {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gray-200);
        }

        .agent-profile-fallback {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--gray-200);
            color: var(--gray-700);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid var(--gray-200);
        }

        .agent-meta {
            display: flex;
            flex-direction: column;
        }

        .agent-name {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.9rem;
        }

        .agent-role {
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .agent-suggestion-item:hover,
        .agent-suggestion-item.active {
            background-color: var(--gray-50);
        }

        .leads-table-container {
            flex: 1;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: auto;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .leads-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .leads-table thead {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--gray-50);
        }

        .leads-table th {
            background: var(--gray-50);
            padding: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
            text-align: left;
            white-space: nowrap;
        }

        .leads-table td {
            padding: 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }

        .leads-table tr:last-child td {
            border-bottom: none;
        }

        .leads-table tbody tr {
            transition: all 0.2s ease;
        }

        .leads-table tbody tr:hover {
            background: var(--gray-50);
        }

        .temperature {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .temperature.hot {
            background: var(--danger-light);
            color: var(--danger);
        }

        .temperature.warm {
            background: var(--warning-light);
            color: var(--warning);
        }

        .temperature.cold {
            background: var(--info-light);
            color: var(--info);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-view,
        .btn-edit,
        .btn-delete,
        .btn-call {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .btn-view {
            background: var(--info-light);
            color: var(--info);
        }

        .btn-edit {
            background: var(--warning-light);
            color: var(--warning);
        }

        /* Enhanced gray styling for disabled edit buttons */
        .btn-edit.disabled {
            background-color: #d1d5db !important;
            color: #6b7280 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            opacity: 1 !important;
            border: 1px solid #9ca3af !important;
        }

        .btn-delete {
            background: var(--danger-light);
            color: var(--danger);
        }

        .btn-call {
            background: var(--success-light);
            color: var(--success);
        }

        .btn-call.disabled {
            background-color: #d1d5db !important;
            color: #6b7280 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            opacity: 1 !important;
            border: 1px solid #9ca3af !important;
        }

        .btn-view:hover,
        .btn-edit:hover:not(.disabled),
        .btn-delete:hover,
        .btn-call:hover:not(.disabled) {
            transform: translateY(-1px);
        }

        .call-options {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .call-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--gray-700);
            transition: all 0.2s ease;
            background: white;
        }

        .call-option:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .call-option i {
            font-size: 1.25rem;
            margin-right: 1rem;
            width: 1.5rem;
            text-align: center;
        }

        .call-option-content {
            flex: 1;
        }

        .call-option-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .call-option-description {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .call-option.phone i {
            color: var(--success);
        }

        .call-option.whatsapp i {
            color: #25d366;
        }

        .call-option.viber i {
            color: #665cac;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .pagination-info {
            text-align: center;
            color: var(--gray-500);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .pagination-button {
            display: inline-flex;
            align-items: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.5rem;
            border-radius: var(--border-radius);
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .pagination-button.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-button:hover:not(.active):not(.disabled) {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }

        .pagination-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Filter active indicator */
        .filter-active {
            background-color: var(--primary-light) !important;
            border-color: var(--primary) !important;
        }

        /* Search button */
        .btn-search {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            height: 2.75rem;
            width: 100%;
            max-width: 250px;
        }

        .btn-search:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .filter-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 1.5rem;
            width: 100%;
            grid-column: 1 / -1;
        }

        /* Clear filters button */
        .btn-clear-filters {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background-color: var(--gray-100);
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            color: var(--gray-700);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            height: 2.75rem;
            text-decoration: none;
        }

        .btn-clear-filters:hover {
            background-color: var(--gray-200);
            color: var(--gray-900);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 1200px) {
            .search-form {
                grid-template-columns: repeat(3, minmax(180px, 1fr));
                max-width: 900px;
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .leads-page {
                padding: 1rem;
                min-height: calc(100vh - 60px);
            }

            /* Reset Agent filter to single column on mobile */
            .filter-select-container[data-label="Agent"] {
                grid-column: 1;
                justify-self: center;
            }

            .leads-table-container {
                margin: -1rem;
                margin-top: 1rem;
                border-radius: 0;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn-add,
            .btn-conversion {
                width: 100%;
                justify-content: center;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .search-form {
                grid-template-columns: 1fr;
                max-width: 280px;
                gap: 1rem;
            }

            .search-form > * {
                max-width: 100%;
            }

            .search-form input,
            .filter-select-container select {
                max-width: 100%;
            }

            .filters-container {
                padding: 1rem;
                margin-left: 1rem;
                margin-right: 1rem;
                width: calc(100% - 2rem);
            }

            .filter-options {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
                gap: 0.75rem;
                margin-top: 1rem;
            }

            .btn-search,
            .btn-clear-filters {
                width: 100%;
                max-width: 280px;
            }
        }

        #callModal {
            display: none;
            position: fixed;
            z-index: 1000;
            inset: 0;
            background-color: rgba(0,0,0,0.5);
        }

        /* When shown, center & allow page-level scrolling */
        #callModal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        /* Hide WebKit scrollbar */
        #callModal.show::-webkit-scrollbar {
            display: none;
        }

        /* The white "card" */
        #callModal .modal-content {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            max-width: 400px;
            width: 90%;
            max-height: 90vh;
        }

        /* Scale up on show */
        #callModal.show .modal-content {
            transform: scale(1);
        }

        /* Header */
        #callModal .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Title */
        #callModal .modal-header h3 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Close button */
        #callModal .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-400);
            cursor: pointer;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--border-radius);
            transition: all 0.2s ease;
        }

        #callModal .modal-close:hover {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        /* Body */
        #callModal .modal-body {
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="leads-page">
                <div class="page-header">
                    <h2>
                        <i class="fas fa-users"></i>Leads Management
                        <?php if ($isSuperUser): ?>
                            <span class="superuser-badge">
                                <i class="fas fa-crown"></i> Super Admin
                            </span>
                        <?php endif; ?>
                    </h2>
                    <div class="header-actions">
                        <a href="lead-conversion.php" class="btn-conversion">
                            <i class="fas fa-handshake"></i>
                            View Closed Deals (<?php echo $closedDealsCount; ?>)
                        </a>
                        <a href="add-lead.php" class="btn-add">
                            <i class="fas fa-plus"></i>
                            Add New Lead
                        </a>
                    </div>
                </div>

                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--primary-light); color: var(--primary);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="summary-info">
                            <h3><?php echo $activeCardTitle; ?></h3>
                            <p><?php echo $activeCardCount; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--danger-light); color: var(--danger);">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Hot Leads</h3>
                            <p><?php echo $hotLeadsCount; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-thermometer-half"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Warm Leads</h3>
                            <p><?php echo $warmLeadsCount; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--info-light); color: var(--info);">
                            <i class="fas fa-icicles"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Cold Leads</h3>
                            <p><?php echo $coldLeadsCount; ?></p>
                        </div>
                    </div>
                </div>

                <div class="filters-container">
                    <form class="search-form" method="GET" action="" id="searchForm">
                        <input type="hidden" name="page" value="1">

                        <div class="filter-select-container" data-label="Search">
                            <input type="text" name="search" placeholder="Search by name, email, phone..."
                                   value="<?php echo htmlspecialchars($search ?? ''); ?>"
                                   class="<?php echo $search_active ? 'filter-active' : ''; ?>">
                        </div>

                        <div class="filter-select-container" data-label="My Leads">
                            <select name="my_leads" id="my_leads" class="<?php echo $my_leads_filter_active ? 'filter-active' : ''; ?>">
                                <option value="">All Leads</option>
                                <option value="1" <?php echo $my_leads_filter_active ? 'selected' : ''; ?>>My Leads Only</option>
                            </select>
                        </div>

                        <div class="filter-select-container" data-label="Temperature">
                            <select name="temperature" id="temperature" class="<?php echo $temp_filter_active ? 'filter-active' : ''; ?>">
                                <option value="">All Temperatures</option>
                                <?php foreach ($temperatures as $temp): ?>
                                    <option value="<?php echo htmlspecialchars($temp); ?>"
                                            <?php echo (isset($temperature) && $temperature === $temp) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($temp); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-select-container" data-label="Status">
                            <select name="status" id="status" class="<?php echo $status_filter_active ? 'filter-active' : ''; ?>">
                                <option value="">All Status</option>
                                <?php foreach ($statuses as $stat): ?>
                                    <option value="<?php echo htmlspecialchars($stat); ?>"
                                            <?php echo (isset($status) && $status === $stat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-select-container" data-label="Source">
                            <select name="source" id="source" class="<?php echo $source_filter_active ? 'filter-active' : ''; ?>">
                                <option value="">All Sources</option>
                                <?php foreach ($sources as $src): ?>
                                    <option value="<?php echo htmlspecialchars($src); ?>"
                                            <?php echo (isset($source) && $source === $src) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($src); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if (($isSuperUser || $user['role'] === 'manager') && !empty($filterUsers)): ?>
                        <div class="filter-select-container" data-label="Agent">
                            <div class="agent-typeahead-wrapper">
                                <input type="text" id="agentInput" class="agent-input <?php echo $agent_filter_active ? 'filter-active' : ''; ?>"
                                       placeholder="Type to search user..."
                                       value="<?php echo htmlspecialchars($selectedAgentName); ?>"
                                       autocomplete="off">
                                <input type="hidden" name="agent" id="agentHidden" value="<?php echo htmlspecialchars($agent); ?>">
                                <div id="agentSuggestions" class="agent-suggestions"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="filter-actions">
                            <button type="submit" class="btn-search">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="leads.php" class="btn-clear-filters">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>

                <div class="leads-table-container">
                    <table class="leads-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Temperature</th>
                                <th>Status</th>
                                <th>Source</th>
                                <th>Agent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 2rem;">
                                    <div style="color: var(--gray-400);">
                                        <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                        <p>No active leads found</p>
                                        <?php if (!empty(array_filter($filters))): ?>
                                            <p style="font-size: 0.875rem; margin-top: 0.5rem;">
                                                Try adjusting your filters or <a href="leads.php" style="color: var(--primary);">clear all filters</a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($leads as $lead): ?>
                                <tr>
                                    <td><?php
                                        // Check if user can see full contact info (superuser or lead owner)
                                        if (isSuperUser($user['username']) || $lead['user_id'] == $user_id) {
                                            echo htmlspecialchars($lead['client_name']);
                                        } else {
                                            // Mask name for privacy
                                            $name = $lead['client_name'];
                                            $spacePos = strpos($name, ' ');
                                            if ($spacePos !== false && $spacePos > 2) {
                                                $maskedName = substr($name, 0, 2) . str_repeat('*', $spacePos - 2) . substr($name, $spacePos);
                                                echo htmlspecialchars($maskedName);
                                            } else {
                                                echo '************';
                                            }
                                        }
                                    ?></td>
                                    <td>
                                        <?php
                                         // Check if user can see full contact info (superuser or lead owner)
                                        if (isSuperUser($user['username']) || $lead['user_id'] == $user_id) {
                                            echo htmlspecialchars($lead['email']);
                                        } else {
                                            // Mask email for privacy
                                            $email = $lead['email'];
                                            $atPos = strpos($email, '@');
                                            if ($atPos !== false && $atPos > 2) {
                                                $maskedEmail = substr($email, 0, 2) . str_repeat('*', $atPos - 2) . substr($email, $atPos);
                                                echo htmlspecialchars($maskedEmail);
                                            } else {
                                                echo '***@***';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                         // Check if user can see full contact info (superuser or lead owner)
                                        if (isSuperUser($user['username']) || $lead['user_id'] == $user_id) {
                                            echo htmlspecialchars($lead['phone']);
                                        } else {
                                            // Mask phone for privacy
                                            $phone = $lead['phone'];
                                            if (strlen($phone) > 4) {
                                                $maskedPhone = substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 6) . substr($phone, -3);
                                                echo htmlspecialchars($maskedPhone);
                                            } else {
                                                echo '***-***';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="temperature <?php echo strtolower($lead['temperature']); ?>">
                                            <?php echo htmlspecialchars($lead['temperature']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($lead['status']); ?></td>
                                    <td><?php echo htmlspecialchars($lead['source']); ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <?php
                                            $profile_picture = $lead['agent_profile_picture'] ?? '';
                                            $agent_name = $lead['agent_name'] ?? 'Unknown Agent';

                                            if (isset($_GET['debug_profiles']) || true) { // Temporarily always show
                                                echo "<!-- Debug Lead ID {$lead['id']}: Profile Picture = '{$profile_picture}', Agent = '{$agent_name}' -->";
                                            }

                                            $possible_paths = [
                                                'uploads/profile_pictures/' . $profile_picture,
                                                'profile_pictures/' . $profile_picture,
                                                'images/profiles/' . $profile_picture,
                                                'assets/images/profiles/' . $profile_picture,
                                                $profile_picture // In case it's already a full path
                                            ];
                                            
                                            $working_path = null;
                                            $image_exists = false;
                                            
                                            if (!empty($profile_picture) && trim($profile_picture) !== '') {
                                                foreach ($possible_paths as $path) {
                                                    if (file_exists($path)) {
                                                        $working_path = $path;
                                                        $image_exists = true;
                                                        break;
                                                    }
                                                }
                                                
                                                if (isset($_GET['debug_profiles']) || true) {
                                                    echo "<!-- Debug: Tried paths: " . implode(', ', $possible_paths) . " | Working path: " . ($working_path ?? 'NONE') . " -->";
                                                }
                                            }
                                            ?>
                                                <?php if ($image_exists && $working_path): ?>
                                                    <img src="<?php echo htmlspecialchars($working_path); ?>"
                                                         alt="<?php echo htmlspecialchars($agent_name); ?>"
                                                         class="agent-profile-picture"
                                                         style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb;"
                                                         onerror="console.log('[v0] Profile image failed to load: <?php echo $working_path; ?>'); this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="agent-profile-fallback" style="display: none; width: 32px; height: 32px; border-radius: 50%; background: #f3f4f6; color: #6b7280; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 600;">
                                                        <?php
                                                        $initials = '';
                                                        $nameParts = explode(' ', trim($agent_name));
                                                        foreach ($nameParts as $part) {
                                                            if (!empty($part)) {
                                                                $initials .= strtoupper($part[0]);
                                                                if (strlen($initials) >= 2) break;
                                                            }
                                                        }
                                                        echo htmlspecialchars($initials ?: 'U');
                                                        ?>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- No profile picture in database or file not found, show initials -->
                                                    <div class="agent-profile-fallback" style="width: 32px; height: 32px; border-radius: 50%; background: #f3f4f6; color: #6b7280; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 600;">
                                                        <?php
                                                        $initials = '';
                                                        $nameParts = explode(' ', trim($agent_name));
                                                        foreach ($nameParts as $part) {
                                                            if (!empty($part)) {
                                                                $initials .= strtoupper($part[0]);
                                                                if (strlen($initials) >= 2) break;
                                                            }
                                                        }
                                                        echo htmlspecialchars($initials ?: 'U');
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                    <span><?php echo htmlspecialchars($agent_name); ?></span>
                                </div>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($lead['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                            <a href="lead-details.php?id=<?php echo $lead['id']; ?>" class="btn-view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <?php if (canEditLead($lead, $user_id)): ?>
                                                <a href="edit-lead.php?id=<?php echo $lead['id']; ?>" class="btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn-edit disabled"
                                                        title="You can only edit leads assigned to you"
                                                        disabled>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if (isSuperUser($user['username']) || $lead['user_id'] == $user_id): ?>
                                                <button class="btn-call" onclick="openCallModal('<?php echo htmlspecialchars($lead['client_name']); ?>', '<?php echo htmlspecialchars($lead['phone']); ?>')" title="Call">
                                                    <i class="fas fa-phone"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn-call disabled"
                                                        title="You can only call leads assigned to you"
                                                        disabled>
                                                    <i class="fas fa-phone"></i>
                                                </button>
                                            <?php endif; ?>

                                            <button class="btn-delete" onclick="deleteLead(<?php echo $lead['id']; ?>)" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div>
                        <?php if ($current_page > 1): ?>
                            <a href="<?php echo buildPaginationUrl($current_page - 1); ?>" class="pagination-button">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $pagination_range = getPaginationRange($current_page, $total_pages);
                        foreach ($pagination_range as $page):
                            if ($page === '...'):
                        ?>
                            <span class="pagination-button disabled">...</span>
                        <?php else: ?>
                            <a href="<?php echo buildPaginationUrl($page); ?>"
                               class="pagination-button <?php echo ($current_page == $page) ? 'active' : ''; ?>">
                                <?php echo $page; ?>
                            </a>
                        <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="<?php echo buildPaginationUrl($current_page + 1); ?>" class="pagination-button">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>

                        <div class="pagination-info">
                            Showing <?php echo min(($current_page - 1) * $leads_per_page + 1, $total_leads); ?> to
                            <?php echo min($current_page * $leads_per_page, $total_leads); ?> of
                            <?php echo $total_leads; ?> active leads
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <!-- End Pagination -->
            </div>
        </div>
    </div>

    <!-- Call Modal -->
    <div id="callModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-phone"></i>
                    Contact <span id="modalClientName"></span>
                </h3>
                <button class="modal-close" onclick="closeCallModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="call-options">
                    <a href="#" id="phoneCallLink" class="call-option phone">
                        <i class="fas fa-phone"></i>
                        <div>
                            <strong>Phone Call</strong>
                            <p>Make a regular phone call</p>
                        </div>
                    </a>
                    <a href="#" id="whatsappLink" class="call-option whatsapp" target="_blank">
                        <i class="fab fa-whatsapp"></i>
                        <div class="call-option-content">
                            <div class="call-option-title">WhatsApp</div>
                            <div class="call-option-description">Send a WhatsApp message</div>
                        </div>
                    </a>
                    <a href="#" id="viberLink" class="call-option viber" target="_blank">
                        <i class="fab fa-viber"></i>
                        <div class="call-option-content">
                            <div class="call-option-title">Viber</div>
                            <div class="call-option-description">Send a Viber message</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Enhanced live search functionality with AJAX for seamless filtering
    (function() {
        const form = document.getElementById('searchForm');
        const tableContainer = document.querySelector('.leads-table-container');
        if (!form || !tableContainer) return;

        // Debounce function to prevent excessive requests
        function debounce(fn, delay) {
            let t;
            return function(...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        // Function to show loading state
        function showLoading() {
            const tableContainer = document.querySelector('.leads-table-container');
            if (tableContainer) {
                tableContainer.style.opacity = '0.6';
                tableContainer.style.pointerEvents = 'none';
                
                // Add loading overlay if it doesn't exist
                if (!document.querySelector('.loading-overlay')) {
                    const overlay = document.createElement('div');
                    overlay.className = 'loading-overlay';
                    overlay.style.cssText = `
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(255, 255, 255, 0.8);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 10;
                        border-radius: var(--border-radius);
                    `;
                    overlay.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; color: var(--primary);"></i>';
                    tableContainer.style.position = 'relative';
                    tableContainer.appendChild(overlay);
                }
            }
        }
        
        // Function to hide loading state
        function hideLoading() {
            const tableContainer = document.querySelector('.leads-table-container');
            const overlay = document.querySelector('.loading-overlay');
            if (tableContainer) {
                tableContainer.style.opacity = '';
                tableContainer.style.pointerEvents = '';
            }
            if (overlay) {
                overlay.remove();
            }
        }

        // Function to update table content via AJAX
        async function updateTableContent(formData) {
            // Make this function globally accessible
            window.updateTableContent = updateTableContent;
            try {
                showLoading();
                
                // Add a flag to indicate AJAX request
                formData.append('ajax', '1');
                formData.set('page', '1'); // Reset to first page

                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });

                if (response.ok) {
                    const html = await response.text();
                    
                    // Parse the response HTML
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update the table content
                    const newTableContainer = doc.querySelector('.leads-table-container');
                    const newPagination = doc.querySelector('.pagination');
                    const newSummaryCards = doc.querySelector('.summary-cards');
                    
                    if (newTableContainer) {
                        tableContainer.innerHTML = newTableContainer.innerHTML;
                    }
                    
                    // Update pagination if it exists
                    const currentPagination = document.querySelector('.pagination');
                    if (newPagination && currentPagination) {
                        currentPagination.outerHTML = newPagination.outerHTML;
                    } else if (newPagination && !currentPagination) {
                        // Add pagination if it doesn't exist
                        tableContainer.insertAdjacentHTML('afterend', newPagination.outerHTML);
                    } else if (!newPagination && currentPagination) {
                        // Remove pagination if no longer needed
                        currentPagination.remove();
                    }
                    
                    // Update summary cards
                    if (newSummaryCards) {
                        const currentSummaryCards = document.querySelector('.summary-cards');
                        if (currentSummaryCards) {
                            currentSummaryCards.innerHTML = newSummaryCards.innerHTML;
                        }
                    }
                }
                
                hideLoading();
            } catch (error) {
                console.error('Error updating table:', error);
                hideLoading();
                // Fallback to form submission if AJAX fails
                form.submit();
            }
        }

        // Live search for text input
        const searchInput = form.querySelector('input[name="search"]');
        if (searchInput) {
            const debouncedSearch = debounce(() => {
                const formData = new FormData(form);
                updateTableContent(formData);
            }, 300); // Reduced delay for more responsive search

            searchInput.addEventListener('input', debouncedSearch);
        }

        // Immediate filtering for select dropdowns
        const selects = form.querySelectorAll('select');
        selects.forEach(sel => {
            sel.addEventListener('change', () => {
                const formData = new FormData(form);
                updateTableContent(formData);
            });
        });

        // Manual search button (fallback)
        const searchButton = form.querySelector('.btn-search');
        if (searchButton) {
            searchButton.addEventListener('click', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                updateTableContent(formData);
            });
        }
    })();

    function deleteLead(id) {
        if (confirm('Are you sure you want to delete this lead?')) {
            fetch(`delete-lead.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error deleting lead');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting lead');
            });
        }
    }

    function openCallModal(clientName, phoneNumber) {
        const modal = document.getElementById('callModal');
        const modalClientName = document.getElementById('modalClientName');
        const phoneCallLink = document.getElementById('phoneCallLink');
        const whatsappLink = document.getElementById('whatsappLink');
        const viberLink = document.getElementById('viberLink');

        // Set client name
        modalClientName.textContent = clientName;

        // Set phone call link
        phoneCallLink.href = `tel:${phoneNumber}`;

        // Clean phone number for messaging apps
        let cleanPhone = phoneNumber.replace(/[^0-9+]/g, '');
        
        // Handle Philippine numbers - add country code if missing
        if (cleanPhone.startsWith('09') && cleanPhone.length === 11) {
            cleanPhone = '63' + cleanPhone.substring(1); // Convert 09XXXXXXXXX to 639XXXXXXXXX
        } else if (cleanPhone.startsWith('639') && cleanPhone.length === 12) {
            // Already formatted correctly
        } else if (cleanPhone.startsWith('+63')) {
            cleanPhone = cleanPhone.substring(1); // Remove + symbol
        } else if (cleanPhone.startsWith('63') && cleanPhone.length === 12) {
            // Already has correct format
        } else if (!cleanPhone.startsWith('63') && cleanPhone.length === 10) {
            // Handle 10-digit numbers without leading 0
            cleanPhone = '639' + cleanPhone;
        }
        
        // Set WhatsApp link with proper formatting
        whatsappLink.href = `https://wa.me/${cleanPhone}`;
        
        // Set Viber link - Viber can use either tel: protocol or viber://chat?number=
        viberLink.href = `viber://chat?number=${cleanPhone}`;

        // Show modal
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeCallModal() {
        const modal = document.getElementById('callModal');
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    (function() {
        const agentInput = document.getElementById('agentInput');
        const agentHidden = document.getElementById('agentHidden');
        const suggestions = document.getElementById('agentSuggestions');

        if (!agentInput || !agentHidden || !suggestions) return;

        // Build local list from PHP-provided agents
        const agents = <?php echo json_encode($filterUsers); ?>;
        let items = agents || [];
        let activeIndex = -1;

        function render(list) {
            if (!list || list.length === 0) {
                suggestions.style.display = 'none';
                suggestions.innerHTML = '';
                activeIndex = -1;
                return;
            }

            suggestions.innerHTML = '';
            list.forEach((a, idx) => {
                const div = document.createElement('div');
                div.className = 'agent-suggestion-item' + (idx === activeIndex ? ' active' : '');

                let avatarHtml = '';
                if (a.profile_picture && a.profile_picture.trim() !== '') {
                    // Show profile picture if available
                    const profilePicPath = `uploads/profile_pictures/${a.profile_picture}`;
                    avatarHtml = `<span class="agent-avatar">
                        <img src="${profilePicPath}"
                             alt="${a.name || 'User'}"
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                             onerror="this.style.display='none'; this.parentElement.innerHTML='${(a.name || 'U').split(' ').map(p => p.charAt(0)).slice(0,2).join('').toUpperCase()}';"
                             onload="this.style.display='block';">
                    </span>`;
                } else {
                    // Show initials if no profile picture
                    const initials = (a.name || 'U').split(' ').map(p => p.charAt(0)).slice(0,2).join('').toUpperCase();
                    avatarHtml = `<span class="agent-avatar">${initials}</span>`;
                }

                const role = (a.role || '').charAt(0).toUpperCase() + (a.role || '').slice(1);

                div.innerHTML = `
                    ${avatarHtml}
                    <span class="agent-meta">
                        <span class="agent-name">${a.name}</span>
                        <span class="agent-role">${role}</span>
                    </span>
                `;

                div.addEventListener('click', () => {
                    agentInput.value = a.name;
                    agentHidden.value = a.id;
                    suggestions.style.display = 'none';

                    // Use AJAX to filter immediately when agent is selected
                    const form = document.getElementById('searchForm');
                    if (form) {
                        const formData = new FormData(form);
                        // Make sure the agent value is set
                        formData.set('agent', a.id);
                        formData.set('page', '1');
                        
                        // Use the AJAX update function directly
                        if (typeof updateTableContent === 'function') {
                            updateTableContent(formData);
                        } else {
                            // Fallback to form submission if AJAX function is not available
                            form.submit();
                        }
                    }
                });

                suggestions.appendChild(div);
            });

            suggestions.style.display = 'block';
        }

        function filter(query) {
            const q = (query || '').toLowerCase().trim();
            if (q === '') return [];
            return items.filter(a => a.name.toLowerCase().includes(q)).slice(0, 20);
        }

        function debounce(fn, delay) {
            let t;
            return function(...args){
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        const onInput = debounce(() => {
            const q = agentInput.value;

            // If cleared, clear hidden value
            if (q.trim() === '') {
                agentHidden.value = '';
                suggestions.style.display = 'none';
                return;
            }

            activeIndex = -1;
            render(filter(q));
        }, 150);

        agentInput.addEventListener('input', onInput);

        agentInput.addEventListener('focus', () => {
            if (agentInput.value.trim() !== '') {
                render(filter(agentInput.value));
            }
        });

        agentInput.addEventListener('keydown', (e) => {
            const visible = suggestions.style.display === 'block';
            const children = Array.from(suggestions.children);

            if (!visible || children.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % children.length;
                updateActiveItem(children);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + children.length) % children.length;
                updateActiveItem(children);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex >= 0 && activeIndex < children.length) {
                    children[activeIndex].click();
                }
            } else if (e.key === 'Escape') {
                suggestions.style.display = 'none';
            }
        });

        function updateActiveItem(children) {
            children.forEach((child, idx) => {
                child.classList.toggle('active', idx === activeIndex);
            });
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', (evt) => {
            const container = document.querySelector('.filter-select-container[data-label="Agent"]');
            if (container && !container.contains(evt.target)) {
                suggestions.style.display = 'none';
            }
        });
    })();

    // Close modal when clicking outside
    document.getElementById('callModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCallModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCallModal();
        }
    });
    </script>

    <script src="assets/js/script.js"></script>
</body>
</html>
