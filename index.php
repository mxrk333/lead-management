<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable error reporting for development/debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configure error logging to a file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// isSuperUser is provided globally via includes/functions.php (with function_exists guard)

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Add logging for user details
error_log("User logged in: ID=" . $user_id . ", Role=" . ($user['role'] ?? 'N/A') . ", Team ID=" . ($user['team_id'] ?? 'N/A'));

// Function to check if current user can edit a lead (enhanced with superuser support)
function canEditLead($lead, $current_user_id) {
    global $user;
    
    if (isset($user['username']) && isSuperUser($user['username'])) {
        return true;
    }
    
    return ($lead['user_id'] == $current_user_id);
}

// Function to check if current user can see full lead info
function canSeeFullLeadInfo($lead, $current_user_id) {
    global $user;
    
    if (isset($user['username']) && isSuperUser($user['username'])) {
        return true;
    }
    
    // FIXED: Allow managers to see full info for their team members
    if ($user['role'] === 'Manager' && isset($user['team_id']) && !empty($user['team_id'])) {
        // Check if the lead belongs to someone in the manager's team
        $conn = getDbConnection();
        if ($conn) {
            $query = "SELECT u.team_id FROM users u WHERE u.id = ?";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("i", $lead['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $row = $result->fetch_assoc()) {
                    $stmt->close();
                    $conn->close();
                    return ($row['team_id'] == $user['team_id']);
                }
                $stmt->close();
            }
            $conn->close();
        }
    }
    
    return ($lead['user_id'] == $current_user_id);
}

// FIXED: Enhanced function to get user's team_id with better error handling
function getUserTeamId($user_id) {
    global $user;
    
    // First try to get from global user object
    if (isset($user['team_id']) && !empty($user['team_id']) && $user['team_id'] > 0) {
        return $user['team_id'];
    }
    
    // Fallback: query database
    $conn = getDbConnection();
    if (!$conn) {
        error_log("Failed to get database connection for getUserTeamId");
        return null;
    }
    
    $query = "SELECT team_id FROM users WHERE id = ? AND team_id IS NOT NULL AND team_id > 0";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare statement for getUserTeamId: " . $conn->error);
        $conn->close();
        return null;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $team_id = null;
    if ($result && $row = $result->fetch_assoc()) {
        $team_id = $row['team_id'];
    }
    
    $stmt->close();
    $conn->close();
    
    error_log("getUserTeamId for user $user_id: " . ($team_id ?? 'NULL'));
    return $team_id;
}

// Get enhanced dashboard data with chart data
function getEnhancedDashboardData($user_id, $user_role) {
    $conn = getDbConnection();
    $data = [];

    // Debugging: Check database connection
    if (!$conn) {
        error_log("Database connection failed in getEnhancedDashboardData");
        return getEmptyDashboardData();
    }
    
    error_log("Entering getEnhancedDashboardData for User ID: " . $user_id . ", Role: " . $user_role);
    $isSuperUser = isSuperUser($GLOBALS['user']['username']);
    error_log("Is SuperUser: " . ($isSuperUser ? 'true' : 'false'));

    // Determine query type based on user role
    $is_admin_or_superuser = ($user_role === 'Admin' || $isSuperUser);
    $is_manager = ($user_role === 'Manager');
    
    // FIXED: Better team_id retrieval with validation
    $team_id = null;
    if ($is_manager || !$is_admin_or_superuser) {
        $team_id = getUserTeamId($user_id);
        
        // FIXED: For managers, team_id is required
        if ($is_manager && (empty($team_id) || $team_id <= 0)) {
            error_log("CRITICAL: Manager role detected but team_id is invalid: " . ($team_id ?? 'NULL'));
            // Return empty data for security - managers without valid team_id shouldn't see anything
            $conn->close();
            return getEmptyDashboardData();
        }
    }
    
    error_log("User's Team ID: " . ($team_id ?? 'NULL'));
    error_log("Is Admin/Superuser: " . ($is_admin_or_superuser ? 'true' : 'false') . ", Is Manager: " . ($is_manager ? 'true' : 'false'));

    // FIXED: Helper function with better error handling and logging
    $executeAndLog = function($query, $param_type = null, ...$params) use ($conn) {
        error_log("Executing query: " . $query);
        if ($param_type && !empty($params)) {
            error_log("With params: " . implode(", ", array_map(function($p) { return is_null($p) ? 'NULL' : $p; }, $params)) . " (types: " . $param_type . ")");
        }
        
        try {
            if ($param_type && !empty($params)) {
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
                    return null;
                }
                
                $stmt->bind_param($param_type, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
            } else {
                $result = $conn->query($query);
                if (!$result) {
                    error_log("Query failed: (" . $conn->errno . ") " . $conn->error);
                    return null;
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Exception during query execution: " . $e->getMessage());
            return null;
        }
    };

    try {
        // FIXED: Determine base query components for leads-related data with better logic
        $leads_from_clause = "FROM leads l";
        $leads_where_clause = "";
        $leads_params = [];
        $leads_param_type = null;

        if ($is_admin_or_superuser) {
            // Admins and superusers see everything
            error_log("Query mode: Admin/Superuser - no restrictions");
        } elseif ($is_manager) {
            // FIXED: Managers see leads from their team members
            $leads_from_clause .= " JOIN users u ON l.user_id = u.id";
            $leads_where_clause = " WHERE u.team_id = ?";
            $leads_params = [$team_id];
            $leads_param_type = "i";
            error_log("Query mode: Manager - filtering by team_id: " . $team_id);
        } else {
            // Regular agents see only their own leads
            $leads_where_clause = " WHERE l.user_id = ?";
            $leads_params = [$user_id];
            $leads_param_type = "i";
            error_log("Query mode: Agent - filtering by user_id: " . $user_id);
        }

        // --- Lead Statistics ---
        $result = $executeAndLog("SELECT COUNT(*) as total " . $leads_from_clause . $leads_where_clause, $leads_param_type, ...$leads_params);
        $data['total_leads'] = $result ? $result->fetch_assoc()['total'] : 0;
        error_log("Total Leads: " . $data['total_leads']);

        // Safely append WHERE/AND clauses
        $where_prefix = (trim($leads_where_clause) === '') ? ' WHERE ' : ' AND ';
        $result = $executeAndLog("SELECT COUNT(*) as count " . $leads_from_clause . $leads_where_clause . $where_prefix . "l.status = 'Presentation Stage'", $leads_param_type, ...$leads_params);
        $data['presentation_stage'] = $result ? $result->fetch_assoc()['count'] : 0;
        error_log("Presentation Stage: " . $data['presentation_stage']);
        
        $where_prefix = (trim($leads_where_clause) === '') ? ' WHERE ' : ' AND ';
        $result = $executeAndLog("SELECT COUNT(*) as count " . $leads_from_clause . $leads_where_clause . $where_prefix . "l.status IN ('Closed Deal', 'Closed')", $leads_param_type, ...$leads_params);
        $data['closed_deals'] = $result ? $result->fetch_assoc()['count'] : 0;
        error_log("Closed Deals: " . $data['closed_deals']);
        
        $where_prefix = (trim($leads_where_clause) === '') ? ' WHERE ' : ' AND ';
        $result = $executeAndLog("SELECT SUM(l.price) as total " . $leads_from_clause . $leads_where_clause . $where_prefix . "l.status IN ('Closed Deal', 'Closed')", $leads_param_type, ...$leads_params);
        $portfolio_result = $result ? $result->fetch_assoc() : ['total' => 0];
        $data['portfolio_value'] = $portfolio_result['total'] ? number_format($portfolio_result['total'], 2) : '0.00';
        error_log("Portfolio Value: " . $data['portfolio_value']);
        
        // FIXED: Recent leads query with proper agent name handling
        $recent_leads_query = "SELECT l.*, u.name as agent_name FROM leads l LEFT JOIN users u ON l.user_id = u.id";
        $recent_leads_params = [];
        $recent_leads_param_type = null;
        
        if ($is_admin_or_superuser) {
            // No additional filtering needed
        } elseif ($is_manager) {
            $recent_leads_query .= " WHERE u.team_id = ?";
            $recent_leads_params = [$team_id];
            $recent_leads_param_type = "i";
        } else {
            $recent_leads_query .= " WHERE l.user_id = ?";
            $recent_leads_params = [$user_id];
            $recent_leads_param_type = "i";
        }
        $recent_leads_query .= " ORDER BY l.created_at DESC LIMIT 10";
        
        $result = $executeAndLog($recent_leads_query, $recent_leads_param_type, ...$recent_leads_params);
        $data['recent_leads'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        error_log("Recent Leads Count: " . count($data['recent_leads']));
        
        // Calculate conversion rate
        $data['conversion_rate'] = $data['total_leads'] > 0 ? 
            number_format(($data['closed_deals'] / $data['total_leads']) * 100, 1) : '0.0';
        
        // --- Chart data queries ---
        $result = $executeAndLog("SELECT project_model, COUNT(*) as count " . $leads_from_clause . $leads_where_clause . " GROUP BY project_model ORDER BY count DESC LIMIT 10", $leads_param_type, ...$leads_params);
        $data['most_inquired_models'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        error_log("Most Inquired Models Count: " . count($data['most_inquired_models']));
        
        $result = $executeAndLog("SELECT developer, COUNT(*) as count " . $leads_from_clause . $leads_where_clause . " GROUP BY developer ORDER BY count DESC LIMIT 10", $leads_param_type, ...$leads_params);
        $data['top_projects'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        error_log("Top Projects Count: " . count($data['top_projects']));
        
        // --- FIXED: Downpayment tracker statistics with proper team filtering ---
        $dp_from_clause = "FROM downpayment_tracker dt JOIN leads l ON dt.lead_id = l.id";
        $dp_where_clause = "";
        $dp_params = [];
        $dp_param_type = null;

        if ($is_admin_or_superuser) {
            // No additional WHERE clause
        } elseif ($is_manager) {
            $dp_from_clause .= " JOIN users u ON l.user_id = u.id";
            $dp_where_clause = " WHERE u.team_id = ?";
            $dp_params = [$team_id];
            $dp_param_type = "i";
        } else {
            $dp_where_clause = " WHERE l.user_id = ?";
            $dp_params = [$user_id];
            $dp_param_type = "i";
        }

        $dp_query = "SELECT 
            COUNT(*) as total_tracked,
            SUM(CASE WHEN requirements_complete = 1 THEN 1 ELSE 0 END) as requirements_complete,
            SUM(CASE WHEN pagibig_bank_approval = 1 THEN 1 ELSE 0 END) as bank_approved,
            SUM(CASE WHEN loan_takeout = 1 THEN 1 ELSE 0 END) as loan_takeouts,
            SUM(CASE WHEN turnover = 1 THEN 1 ELSE 0 END) as turnovers,
            AVG(progress_rate) as avg_progress
        " . $dp_from_clause . $dp_where_clause;
        
        $result = $executeAndLog($dp_query, $dp_param_type, ...$dp_params);
        $dp_result = $result ? $result->fetch_assoc() : null;
        $data['downpayment_stats'] = $dp_result ?: [
            'total_tracked' => 0, 'requirements_complete' => 0, 'bank_approved' => 0,
            'loan_takeouts' => 0, 'turnovers' => 0, 'avg_progress' => 0
        ];
        error_log("Downpayment Stats: " . json_encode($data['downpayment_stats']));
        
        // --- FIXED: Recent memos with proper visibility logic ---
        $memos_query = "SELECT m.*, u.name as created_by_name 
                       FROM memos m 
                       LEFT JOIN users u ON m.created_by = u.id 
                       WHERE m.is_active = 1";
        $memo_params = [];
        $memo_param_type = null;

        if ($is_admin_or_superuser) {
            // Superadmins/Admins see all active memos
        } else {
            // FIXED: Better memo visibility logic for managers and agents
            $memos_query .= " AND (
                               m.visible_to_all = 1 
                               OR EXISTS (
                                   SELECT 1 FROM memo_person_visibility mpv 
                                   WHERE mpv.memo_id = m.id AND mpv.user_id = ?
                               )";
            
            // Add team visibility if user has a team
            if (!empty($team_id)) {
                $memos_query .= " OR EXISTS (
                                   SELECT 1 FROM memo_team_visibility mtv 
                                   WHERE mtv.memo_id = m.id AND mtv.team_id = ?
                               )";
                $memo_params = [$user_id, $team_id];
                $memo_param_type = "ii";
            } else {
                $memo_params = [$user_id];
                $memo_param_type = "i";
            }
            
            $memos_query .= ")";
        }
        $memos_query .= " ORDER BY m.memo_when DESC LIMIT 5";

        error_log("Memo Query Params: user_id=" . $user_id . ", team_id=" . ($team_id ?? 'NULL'));
        $result = $executeAndLog($memos_query, $memo_param_type, ...$memo_params);
        $data['recent_memos'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        error_log("Recent Memos Count: " . count($data['recent_memos']));
        
        // --- FIXED: Recruitment leads (for managers and admins) ---
        if ($is_admin_or_superuser || $is_manager) {
            $recruitment_from_clause = "FROM recruitment_leads rl";
            $recruitment_where_clause = "";
            $recruitment_params = [];
            $recruitment_param_type = null;

            if ($is_manager && !$is_admin_or_superuser) {
                $recruitment_from_clause .= " JOIN users u ON rl.recruiter_id = u.id";
                $recruitment_where_clause = " WHERE u.team_id = ?";
                $recruitment_params = [$team_id];
                $recruitment_param_type = "i";
            }
            
            $recruitment_query = "SELECT 
                COUNT(*) as total_recruitment,
                SUM(CASE WHEN interest_level = 'Hot' THEN 1 ELSE 0 END) as hot_prospects,
                SUM(CASE WHEN status = 'Training Sir Gab' THEN 1 ELSE 0 END) as in_training,
                SUM(CASE WHEN status = '1st Reservation sale' THEN 1 ELSE 0 END) as first_sales
            " . $recruitment_from_clause . $recruitment_where_clause;
            
            $result = $executeAndLog($recruitment_query, $recruitment_param_type, ...$recruitment_params);
            $data['recruitment_stats'] = $result ? $result->fetch_assoc() : [
                'total_recruitment' => 0, 'hot_prospects' => 0, 'in_training' => 0, 'first_sales' => 0
            ];
            error_log("Recruitment Stats: " . json_encode($data['recruitment_stats']));
            
            // Recent recruitment leads
            $recent_recruitment_query = "SELECT rl.* " . $recruitment_from_clause . $recruitment_where_clause . " ORDER BY rl.timestamp DESC LIMIT 5";
            $result = $executeAndLog($recent_recruitment_query, $recruitment_param_type, ...$recruitment_params);
            $data['recent_recruitment'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            error_log("Recent Recruitment Count: " . count($data['recent_recruitment']));
        }
        
        // --- FIXED: Team performance (for managers and admins) ---
        if ($is_admin_or_superuser || $is_manager) {
            $team_performance_query = "SELECT * FROM team_performance_summary";
            $team_performance_params = [];
            $team_performance_param_type = null;

            // If manager, filter team performance by their own team
            if ($is_manager && !$is_admin_or_superuser) {
                $team_performance_query .= " WHERE team_id = ?";
                $team_performance_params = [$team_id];
                $team_performance_param_type = "i";
            }
            $team_performance_query .= " ORDER BY total_sales DESC LIMIT 5";
            
            $result = $executeAndLog($team_performance_query, $team_performance_param_type, ...$team_performance_params);
            $data['team_performance'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            error_log("Team Performance Count: " . count($data['team_performance']));
        }
        
    } catch (Exception $e) {
        error_log("Dashboard data error: " . $e->getMessage());
        $data = getEmptyDashboardData();
    } finally {
        if ($conn) {
            $conn->close();
        }
    }

    return $data;
}

// FIXED: Helper function to return empty dashboard data structure
function getEmptyDashboardData() {
    return [
        'total_leads' => 0,
        'presentation_stage' => 0,
        'closed_deals' => 0,
        'conversion_rate' => '0.0',
        'portfolio_value' => '0.00',
        'recent_leads' => [],
        'most_inquired_models' => [],
        'top_projects' => [],
        'downpayment_stats' => [
            'total_tracked' => 0,
            'requirements_complete' => 0,
            'bank_approved' => 0,
            'loan_takeouts' => 0,
            'turnovers' => 0,
            'avg_progress' => 0
        ],
        'recent_memos' => [],
        'recruitment_stats' => [
            'total_recruitment' => 0,
            'hot_prospects' => 0,
            'in_training' => 0,
            'first_sales' => 0
        ],
        'recent_recruitment' => [],
        'team_performance' => []
    ];
}

// Get dashboard data based on user role
$dashboardData = getEnhancedDashboardData($user_id, $user['role']);

// Check if current user is superuser
$isSuperUser = isSuperUser($user['username']);
$isManager = ($user['role'] === 'Manager');
$isAdmin = ($user['role'] === 'Admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - InnerSPARC Lead Management System</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- CSS styles remain the same as original file -->
    <style>
    /* All the original CSS styles remain unchanged */
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
        --border-radius: 0.75rem;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 80px;
        --transition-duration: 0.3s;
        --transition-timing: cubic-bezier(0.4, 0, 0.2, 1);
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
        overflow-x: hidden;
    }
    
    .container {
        display: flex;
        width: 100%;
        min-height: 100vh;
        position: relative;
    }

    .main-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background-color: var(--gray-50);
        margin-left: var(--sidebar-width);
        transition: margin-left var(--transition-duration) var(--transition-timing);
        width: calc(100vw - var(--sidebar-width));
        max-width: calc(100vw - var(--sidebar-width));
        position: relative;
        overflow-x: hidden;
    }

    body.sidebar-collapsed .main-content {
        margin-left: var(--sidebar-collapsed-width);
        width: calc(100vw - var(--sidebar-collapsed-width));
        max-width: calc(100vw - var(--sidebar-collapsed-width));
    }

    body.sidebar-open .main-content {
        transform: translateX(var(--sidebar-width));
    }

    .dashboard {
        flex: 1;
        padding: 1.5rem;
        width: 100%;
        max-width: 100%;
        margin: 0;
        min-height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
        transition: all var(--transition-duration) var(--transition-timing);
        box-sizing: border-box;
    }

    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        border: 1px solid var(--gray-200);
        transition: all var(--transition-duration) var(--transition-timing);
    }
    
    .dashboard h2 {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--gray-900);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .dashboard h2 i {
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

    .manager-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        background-color: #f59e0b;
        color: white;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .admin-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        background-color: #8b5cf6;
        color: white;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .superuser-badge i,
    .manager-badge i,
    .admin-badge i {
        margin-right: 0.25rem;
    }

    /* Redesigned Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: var(--border-radius);
        padding: 1.25rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--gray-200);
        transition: all var(--transition-duration) var(--transition-timing);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        position: relative;
        overflow: hidden;
        min-height: 120px;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--card-accent-color, var(--primary)), transparent);
        opacity: 0;
        transition: opacity var(--transition-duration) var(--transition-timing);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .stat-card:hover::before {
        opacity: 1;
    }

    .stat-card.primary { --card-accent-color: var(--primary); }
    .stat-card.success { --card-accent-color: var(--success); }
    .stat-card.warning { --card-accent-color: var(--warning); }
    .stat-card.info { --card-accent-color: var(--info); }
    .stat-card.danger { --card-accent-color: var(--danger); }
    
    .stat-icon {
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        transition: all var(--transition-duration) var(--transition-timing);
        position: relative;
    }

    .stat-icon::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: inherit;
        opacity: 0.1;
        transition: all var(--transition-duration) var(--transition-timing);
    }

    .stat-card:hover .stat-icon::before {
        opacity: 0.2;
        transform: scale(1.1);
    }

    .stat-icon.primary {
        background: var(--primary-light);
        color: var(--primary);
    }

    .stat-icon.success {
        background: var(--success-light);
        color: var(--success);
    }

    .stat-icon.warning {
        background: var(--warning-light);
        color: var(--warning);
    }

    .stat-icon.info {
        background: var(--info-light);
        color: var(--info);
    }

    .stat-icon.danger {
        background: var(--danger-light);
        color: var(--danger);
    }
    
    .stat-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .stat-info h3 {
        font-size: 0.8rem;
        color: var(--gray-500);
        margin: 0 0 0.5rem 0;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .stat-info p {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-900);
        margin: 0;
        line-height: 1;
    }

    /* Content Grid */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    @media (min-width: 1200px) {
        .content-grid {
            grid-template-columns: 2fr 1fr;
        }
    }

    /* Charts Grid - Fixed sizing */
    .charts-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    @media (min-width: 768px) {
        .charts-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    /* Chart Container - Fixed sizing */
    .chart-container {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        transition: all var(--transition-duration) var(--transition-timing);
        display: flex;
        flex-direction: column;
        height: 450px; /* Fixed height */
    }

    .chart-container:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .chart-header {
        padding: 1.25rem;
        border-bottom: 1px solid var(--gray-200);
        background: var(--gray-50);
        flex-shrink: 0;
    }

    .chart-header h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--gray-900);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-body {
        flex: 1;
        padding: 1rem;
        position: relative;
        min-height: 0; /* Important for flex child */
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chart-canvas {
        max-width: 100% !important;
        max-height: 100% !important;
        width: auto !important;
        height: auto !important;
    }

    /* Card Styles */
    .card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        transition: all var(--transition-duration) var(--transition-timing);
    }
    
    .card-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--gray-50);
    }
    
    .card h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-900);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .card-body {
        padding: 1.5rem;
    }

    /* Table Styles */
    .table-container {
        overflow-x: auto;
        transition: all var(--transition-duration) var(--transition-timing);
        width: 100%;
        max-width: 100%;
    }
    
    .table {
        width: 100%;
        min-width: 600px;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: auto;
    }
    
    .table th {
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
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .table td {
        padding: 1rem;
        font-size: 0.875rem;
        color: var(--gray-700);
        border-bottom: 1px solid var(--gray-200);
        transition: all var(--transition-duration) var(--transition-timing);
    }
    
    .table tr:hover td {
        background: var(--gray-50);
    }

    /* Temperature badges */
    .temperature {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        transition: all var(--transition-duration) var(--transition-timing);
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

    /* Priority badges */
    .priority {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .priority.urgent {
        background: var(--danger-light);
        color: var(--danger);
    }

    .priority.high {
        background: var(--warning-light);
        color: var(--warning);
    }

    .priority.medium {
        background: var(--info-light);
        color: var(--info);
    }

    .priority.low {
        background: var(--success-light);
        color: var(--success);
    }

    /* Action buttons */
    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-view, 
    .btn-edit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: var(--border-radius);
        border: none;
        cursor: pointer;
        transition: all var(--transition-duration) var(--transition-timing);
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

    .btn-edit.disabled {
        background-color: #d1d5db !important;
        color: #6b7280 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
        opacity: 1 !important;
        border: 1px solid #9ca3af !important;
    }
    
    .btn-view:hover,
    .btn-edit:hover:not(.disabled) {
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    .view-all {
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
        transition: all var(--transition-duration) var(--transition-timing);
    }
    
    .view-all:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
        text-decoration: none;
        color: white;
    }

    /* Memo styles */
    .memo-item {
        padding: 1rem;
        border-bottom: 1px solid var(--gray-200);
        transition: all var(--transition-duration) var(--transition-timing);
    }

    .memo-item:last-child {
        border-bottom: none;
    }

    .memo-item:hover {
        background: var(--gray-50);
    }

    .memo-title {
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 0.5rem;
    }

    .memo-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-bottom: 0.5rem;
    }

    .memo-description {
        font-size: 0.875rem;
        color: var(--gray-700);
        line-height: 1.5;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: var(--gray-400);
    }

    .empty-state i {
        font-size: 2rem;
        margin-bottom: 1rem;
    }

    /* Responsive Design */
    @media (max-width: 991px) {
        .main-content {
            margin-left: 0;
            width: 100vw;
            max-width: 100vw;
        }

        body.sidebar-collapsed .main-content {
            margin-left: 0;
            width: 100vw;
            max-width: 100vw;
        }

        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .charts-grid {
            grid-template-columns: 1fr;
        }

        .chart-container {
            height: 400px;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard {
            padding: 1rem;
        }
        
        .dashboard-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
            padding: 1rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .stat-card {
            padding: 1rem;
            min-height: 100px;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            font-size: 1.25rem;
        }

        .stat-info p {
            font-size: 1.5rem;
        }

        .content-grid {
            grid-template-columns: 1fr;
        }

        .charts-grid {
            grid-template-columns: 1fr;
        }

        .chart-container {
            height: 350px;
        }

        .chart-body {
            padding: 0.75rem;
        }
        
        .card-header {
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
        }
        
        .view-all {
            width: 100%;
            justify-content: center;
        }

        .table {
            min-width: 500px;
        }
        
        .table th.hide-mobile,
        .table td.hide-mobile {
            display: none;
        }
        
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        position: relative; /* Ensure z-index works */
        z-index: 10; /* Higher z-index to be clickable over row */
    }
    
    .btn-view,
    .btn-edit {
        width: 100%;
        height: 1.75rem;
        font-size: 0.75rem;
    }

        body.sidebar-open::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 1;
            transition: opacity var(--transition-duration) var(--transition-timing);
        }

        body.sidebar-open .main-content {
            transform: translateX(0);
        }
    }

    @media (max-width: 576px) {
        .dashboard {
            padding: 0.75rem;
        }

        .dashboard-header {
            padding: 0.75rem;
            margin-bottom: 1rem;
        }

        .dashboard h2 {
            font-size: 1.25rem;
        }

        .superuser-badge,
        .manager-badge,
        .admin-badge {
            font-size: 0.625rem;
            padding: 0.125rem 0.375rem;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-card {
            padding: 0.75rem;
        }

        .card-header {
            padding: 0.75rem;
        }

        .card-body {
            padding: 0.75rem;
        }

        .chart-header {
            padding: 0.75rem;
        }

        .chart-container {
            height: 300px;
        }

        .chart-body {
            padding: 0.5rem;
        }

        .table th,
        .table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.8rem;
        }

        .temperature,
        .priority {
            font-size: 0.625rem;
            padding: 0.125rem 0.5rem;
        }
    }

    .fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

/* Memo clickable styles */
.memo-item-link {
    display: block;
    text-decoration: none;
    color: inherit;
    transition: all var(--transition-duration) var(--transition-timing);
}

.memo-item-link:hover {
    text-decoration: none;
    color: inherit;
}

.memo-item-link:hover .memo-item {
    background: var(--primary-light);
    transform: translateX(4px);
    border-left: 3px solid var(--primary);
}

.memo-item {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-200);
    transition: all var(--transition-duration) var(--transition-timing);
    cursor: pointer;
    position: relative;
}

.memo-item:last-child {
    border-bottom: none;
}

/* Table row clickable styles */
.table-row-clickable {
    cursor: pointer;
    transition: all var(--transition-duration) var(--transition-timing);
}

.table-row-clickable:hover td {
    background: var(--primary-light) !important;
    color: var(--primary) !important;
    font-weight: 500;
}

.lead-name-link {
    color: inherit;
    text-decoration: none;
    font-weight: 500;
    transition: all var(--transition-duration) var(--transition-timing);
}

.lead-name-link:hover {
    color: var(--primary);
    text-decoration: none;
}

.clickable-cell {
    position: relative;
}

.clickable-cell::before {
    content: '';
    position: absolute;
    left: -0.5rem;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 0;
    background: var(--primary);
    transition: height var(--transition-duration) var(--transition-timing);
}

.table-row-clickable:hover .clickable-cell::before {
    height: 70%;
}

/* Enhanced hover effects for better visibility */
.table tr:hover td {
    background: var(--gray-100) !important;
}

.table-row-clickable:hover td {
    background: var(--primary-light) !important;
    color: var(--primary) !important;
    font-weight: 500;
}

/* Action buttons - prevent row click when clicking buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    position: relative;
    z-index: 10;
}

/* Improve memo hover visibility */
.memo-item:hover {
    background: var(--gray-100);
}

.memo-item-link:hover .memo-item {
    background: var(--primary-light) !important;
    transform: translateX(4px);
    border-left: 3px solid var(--primary);
    box-shadow: var(--shadow-sm);
}

/* Add cursor pointer to indicate clickable elements */
.memo-item,
.table-row-clickable,
.lead-name-link {
    cursor: pointer;
}

/* Improve stat card hover effects */
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--card-accent-color, var(--primary));
}

.stat-card:hover::before {
    opacity: 1;
    height: 4px;
}
</style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard fade-in">
                <div class="dashboard-header">
                    <h2>
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                        <?php if ($isSuperUser): ?>
                            <span class="superuser-badge">
                                <i class="fas fa-crown"></i> Super Admin
                            </span>
                        <?php elseif ($isAdmin): ?>
                            <span class="admin-badge">
                                <i class="fas fa-user-shield"></i> Admin
                            </span>
                        <?php elseif ($isManager): ?>
                            <span class="manager-badge">
                                <i class="fas fa-user-tie"></i> Manager
                            </span>
                        <?php endif; ?>
                    </h2>
                </div>
                
                <!-- FIXED: Added debug info for managers -->
                <?php if ($isManager && isset($_GET['debug'])): ?>
                <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem;">
                    <strong>Debug Info (Manager):</strong><br>
                    User ID: <?php echo $user_id; ?><br>
                    Team ID: <?php echo $user['team_id'] ?? 'NULL'; ?><br>
                    Total Leads: <?php echo $dashboardData['total_leads']; ?><br>
                    Recent Leads Count: <?php echo count($dashboardData['recent_leads']); ?>
                </div>
                <?php endif; ?>
                
                <!-- Enhanced Stats Grid -->
                <div class="stats-grid">
                    <!-- Lead Statistics -->
                    <div class="stat-card primary">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Leads</h3>
                            <p><?php echo htmlspecialchars($dashboardData['total_leads']); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-icon warning">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Presentation Stage</h3>
                            <p><?php echo htmlspecialchars($dashboardData['presentation_stage']); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Closed Deals</h3>
                            <p><?php echo htmlspecialchars($dashboardData['closed_deals']); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-icon info">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Conversion Rate</h3>
                            <p><?php echo htmlspecialchars($dashboardData['conversion_rate']); ?>%</p>
                        </div>
                    </div>

                <!--    <div class="stat-card success">
                        <div class="stat-icon success">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Portfolio Value</h3>
                            <p>₱<?php echo htmlspecialchars($dashboardData['portfolio_value']); ?></p>
                        </div>
                    </div> -->

                    <!-- Downpayment Statistics -->
                    <?php if (!empty($dashboardData['downpayment_stats'])): ?>
                    <div class="stat-card warning">
                        <div class="stat-icon warning">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="stat-info">
                            <h3>DP Tracked</h3>
                            <p><?php echo htmlspecialchars($dashboardData['downpayment_stats']['total_tracked'] ?? 0); ?></p>
                        </div>
                    </div>

                    <div class="stat-card info">
                        <div class="stat-icon info">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Bank Approved</h3>
                            <p><?php echo htmlspecialchars($dashboardData['downpayment_stats']['bank_approved'] ?? 0); ?></p>
                        </div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-icon success">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Turnovers</h3>
                            <p><?php echo htmlspecialchars($dashboardData['downpayment_stats']['turnovers'] ?? 0); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Recruitment Statistics (for managers/admins) -->
                    <?php if (($isManager || $isAdmin || $isSuperUser) && !empty($dashboardData['recruitment_stats'])): ?>
                    <div class="stat-card primary">
                        <div class="stat-icon primary">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Recruitment Leads</h3>
                            <p><?php echo htmlspecialchars($dashboardData['recruitment_stats']['total_recruitment'] ?? 0); ?></p>
                        </div>
                    </div>

                    <div class="stat-card danger">
                        <div class="stat-icon danger">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Hot Prospects</h3>
                            <p><?php echo htmlspecialchars($dashboardData['recruitment_stats']['hot_prospects'] ?? 0); ?></p>
                        </div>
                    </div>

                    <div class="stat-card warning">
                        <div class="stat-icon warning">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-info">
                            <h3>In Training</h3>
                            <p><?php echo htmlspecialchars($dashboardData['recruitment_stats']['in_training'] ?? 0); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Charts Section -->
                <div class="charts-grid">
                    <!-- Most Inquired Models Chart -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3><i class="fas fa-chart-bar"></i> Most Inquired Models</h3>
                        </div>
                        <div class="chart-body">
                            <canvas id="modelsChart" class="chart-canvas"></canvas>
                        </div>
                    </div>

                    <!-- Top Projects Chart -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3><i class="fas fa-chart-pie"></i> Top Projects</h3>
                        </div>
                        <div class="chart-body">
                            <canvas id="projectsChart" class="chart-canvas"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Recent Leads -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> Recent Leads</h3>
                            <a href="leads.php" class="view-all">
                                View All <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Client Name</th>
                                        <th>Temperature</th>
                                        <th>Status</th>
                                        <th class="hide-mobile">Developer</th>
                                        <th class="hide-mobile">Price</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dashboardData['recent_leads'])): ?>
                                        <?php foreach ($dashboardData['recent_leads'] as $lead): ?>
                                            <tr class="table-row-clickable" onclick="window.location.href='lead-details.php?id=<?php echo $lead['id']; ?>'">
                                                <td class="clickable-cell">
                                                    <a href="lead-details.php?id=<?php echo $lead['id']; ?>" class="lead-name-link">
                                                        <?php
                                                            if (canSeeFullLeadInfo($lead, $user_id)) {
                                                                echo htmlspecialchars($lead['client_name']);
                                                            } else {
                                                                // Match masking logic used in leads.php
                                                                $name = $lead['client_name'];
                                                                $spacePos = strpos($name, ' ');
                                                                if ($spacePos !== false && $spacePos > 2) {
                                                                    $maskedName = substr($name, 0, 2) . str_repeat('*', $spacePos - 2) . substr($name, $spacePos);
                                                                    echo htmlspecialchars($maskedName);
                                                                } else {
                                                                    echo '************';
                                                                }
                                                            }
                                                        ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="temperature <?php echo strtolower($lead['temperature']); ?>">
                                                        <?php echo htmlspecialchars($lead['temperature']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($lead['status']); ?></td>
                                                <td class="hide-mobile"><?php echo htmlspecialchars($lead['developer']); ?></td>
                                                <td class="hide-mobile">
                                                    <?php
                                                    if (canSeeFullLeadInfo($lead, $user_id)) {
                                                        echo '₱' . number_format($lead['price']);
                                                    } else {
                                                        echo '₱***,***';
                                                    }
                                                    ?>
                                                </td>
                                                <td onclick="event.stopPropagation();">
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
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6">
                                                <div class="empty-state">
                                                    <i class="fas fa-search"></i>
                                                    <p>No leads found</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sidebar Content -->
                    <div>
                        <!-- Recent Memos -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-header">
                                <h3><i class="fas fa-bullhorn"></i> Recent Memos</h3>
                                <?php if ($isManager || $isAdmin || $isSuperUser): ?>
                                    <a href="users/memo/memo.php" class="view-all">
                                        View All <i class="fas fa-arrow-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body" style="padding: 0;">
                                <?php if (!empty($dashboardData['recent_memos'])): ?>
                                    <?php foreach ($dashboardData['recent_memos'] as $memo): ?>
                                        <a href="memo-details.php?id=<?php echo $memo['id']; ?>" class="memo-item-link">
                                            <div class="memo-item">
                                                <div class="memo-title"><?php echo htmlspecialchars($memo['title']); ?></div>
                                                <div class="memo-meta">
                                                    <span class="priority <?php echo strtolower($memo['priority']); ?>">
                                                        <?php echo htmlspecialchars($memo['priority']); ?>
                                                    </span>
                                                    <span><?php echo date('M d, Y', strtotime($memo['memo_when'])); ?></span>
                                                </div>
                                                <div class="memo-description">
                                                    <?php echo htmlspecialchars(substr($memo['description'], 0, 100)) . (strlen($memo['description']) > 100 ? '...' : ''); ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-bullhorn"></i>
                                        <p>No memos available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Team Performance (for managers/admins) -->
                        <?php if (($isManager || $isAdmin || $isSuperUser) && !empty($dashboardData['team_performance'])): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Top Teams</h3>
                                <a href="team-performance.php" class="view-all">
                                    View All <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                            
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Team</th>
                                            <th>Sales</th>
                                            <th>Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dashboardData['team_performance'] as $team): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                                                <td>₱<?php echo number_format($team['total_sales'] ?? 0); ?></td>
                                                <td><?php echo number_format($team['conversion_rate'] ?? 0, 1); ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const body = document.body;
            
            // Chart data from PHP
            const modelsData = <?php echo json_encode($dashboardData['most_inquired_models']); ?>;
            const projectsData = <?php echo json_encode($dashboardData['top_projects']); ?>;
            
            // Color palettes for charts
            const colors = [
                '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
            ];
            
            // Most Inquired Models Chart (Bar Chart)
            if (modelsData && modelsData.length > 0) {
                const modelsCtx = document.getElementById('modelsChart').getContext('2d');
                new Chart(modelsCtx, {
                    type: 'bar',
                    data: {
                        labels: modelsData.map(item => item.project_model),
                        datasets: [{
                            label: 'Number of Inquiries',
                            data: modelsData.map(item => item.count),
                            backgroundColor: colors.slice(0, modelsData.length),
                            borderColor: colors.slice(0, modelsData.length),
                            borderWidth: 1,
                            borderRadius: 4,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#4f46e5',
                                borderWidth: 1,
                                cornerRadius: 8,
                                displayColors: false,
                                callbacks: {
                                    title: function(context) {
                                        return context[0].label;
                                    },
                                    label: function(context) {
                                        return `Inquiries: ${context.parsed.y}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    color: '#6b7280'
                                },
                                grid: {
                                    color: '#e5e7eb'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#6b7280',
                                    maxRotation: 45,
                                    minRotation: 0
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        animation: {
                            duration: 1000,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            } else {
                // Show empty state for models chart
                document.getElementById('modelsChart').parentElement.innerHTML = 
                    '<div class="empty-state"><i class="fas fa-chart-bar"></i><p>No model data available</p></div>';
            }
            
            // Top Projects Chart (Doughnut Chart)
            if (projectsData && projectsData.length > 0) {
                const projectsCtx = document.getElementById('projectsChart').getContext('2d');
                new Chart(projectsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: projectsData.map(item => item.developer),
                        datasets: [{
                            data: projectsData.map(item => item.count),
                            backgroundColor: colors.slice(0, projectsData.length),
                            borderColor: '#fff',
                            borderWidth: 2,
                            hoverBorderWidth: 3,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    color: '#374151',
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#4f46e5',
                                borderWidth: 1,
                                cornerRadius: 8,
                                callbacks: {
                                    title: function(context) {
                                        return context[0].label;
                                    },
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return `Leads: ${context.parsed} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '60%',
                        animation: {
                            animateRotate: true,
                            duration: 1000,
                            easing: 'easeOutQuart'
                        }
                    }
                });
            } else {
                // Show empty state for projects chart
                document.getElementById('projectsChart').parentElement.innerHTML = 
                    '<div class="empty-state"><i class="fas fa-chart-pie"></i><p>No project data available</p></div>';
            }
            
            // Handle sidebar toggle events
            document.addEventListener('sidebarToggle', function(e) {
                const isCollapsed = e.detail.collapsed;
                
                if (isCollapsed) {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
                
                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                }, 300);
            });
            
            // Handle mobile sidebar events
            document.addEventListener('mobileSidebarToggle', function(e) {
                const isOpen = e.detail.open;
                
                if (isOpen) {
                    body.classList.add('sidebar-open');
                } else {
                    body.classList.remove('sidebar-open');
                }
            });
            
            // Add loading states for action buttons
            document.querySelectorAll('.btn-view, .btn-edit:not(.disabled)').forEach(button => {
                button.addEventListener('click', function() {
                    if (!this.classList.contains('disabled')) {
                        this.classList.add('loading');
                        this.style.pointerEvents = 'none';
                        
                        setTimeout(() => {
                            this.classList.remove('loading');
                            this.style.pointerEvents = '';
                        }, 2000);
                    }
                });
            });
            
            // Add intersection observer for fade-in animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.stat-card, .table tr, .chart-container').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>
