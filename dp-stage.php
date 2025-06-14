<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$conn = getDbConnection();

// Initialize search parameters
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$filter_agent = isset($_GET['agent']) ? $_GET['agent'] : '';
$filter_developer = isset($_GET['developer']) ? $_GET['developer'] : '';
$filter_progress = isset($_GET['progress']) ? $_GET['progress'] : '';

// Get URL hash to determine view
$show_completed = isset($_GET['view']) && $_GET['view'] === 'completed';

// Get all leads in Downpayment Stage with search/filter
$query = "SELECT l.*, u.name as agent_name 
          FROM leads l 
          JOIN users u ON l.user_id = u.id 
          LEFT JOIN downpayment_tracker dt ON l.id = dt.lead_id
          WHERE l.status = 'Downpayment Stage'";

if ($show_completed) {
    $query .= " AND dt.requirements_complete = 1 
                AND dt.pagibig_bank_approval = 1 
                AND dt.loan_takeout = 1 
                AND dt.turnover = 1";
} else {
    $query .= " AND (dt.id IS NULL OR 
                NOT (dt.requirements_complete = 1 
                    AND dt.pagibig_bank_approval = 1 
                    AND dt.loan_takeout = 1 
                    AND dt.turnover = 1))";
}

// Add role-based restrictions
if ($user['role'] == 'agent') {
    // Agents can only see their own leads
    $query .= " AND l.user_id = " . $user_id;
} elseif ($user['role'] == 'supervisor' || $user['role'] == 'manager') {
    // Supervisors and managers can see leads from their team
    $query .= " AND u.team_id = " . $user['team_id'];
}
// Admins can see all leads (no additional restriction needed)

// Add search conditions (keep existing search logic)
if (!empty($search_query)) {
    $search_param = "%$search_query%";
    $query .= " AND (l.client_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)";
}

// Add filter conditions (keep existing filter logic)
if (!empty($filter_agent)) {
    // For non-admin users, ensure the filtered agent is in their team
    if ($user['role'] != 'admin') {
        $query .= " AND l.user_id = ? AND u.team_id = " . $user['team_id'];
    } else {
        $query .= " AND l.user_id = ?";
    }
}

if (!empty($filter_developer)) {
    $query .= " AND l.developer = ?";
}

$query .= " ORDER BY l.updated_at DESC";

// Prepare and execute the query with parameters
$stmt = $conn->prepare($query);

// Bind parameters if needed
if (!empty($search_query)) {
    if (!empty($filter_agent) && !empty($filter_developer)) {
        $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $filter_agent, $filter_developer);
    } elseif (!empty($filter_agent)) {
        $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $filter_agent);
    } elseif (!empty($filter_developer)) {
        $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $filter_developer);
    } else {
        $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    }
} else {
    if (!empty($filter_agent) && !empty($filter_developer)) {
        $stmt->bind_param("ss", $filter_agent, $filter_developer);
    } elseif (!empty($filter_agent)) {
        $stmt->bind_param("s", $filter_agent);
    } elseif (!empty($filter_developer)) {
        $stmt->bind_param("s", $filter_developer);
    }
}

$stmt->execute();
$result = $stmt->get_result();
$leads = [];
while ($row = $result->fetch_assoc()) {
    $leads[] = $row;
}
$stmt->close();

// Get all agents for filter dropdown
$agents_query = "SELECT id, name FROM users ORDER BY name";
$agents_result = $conn->query($agents_query);
$agents = [];
while ($agent = $agents_result->fetch_assoc()) {
    $agents[$agent['id']] = $agent['name'];
}

// Get all developers for filter dropdown
$developers_query = "SELECT DISTINCT developer FROM leads WHERE status = 'Downpayment Stage' ORDER BY developer";
$developers_result = $conn->query($developers_query);
$developers = [];
while ($dev = $developers_result->fetch_assoc()) {
    $developers[] = $dev['developer'];
}

// Get tracker data for each lead
$trackers = [];
if (!empty($leads)) {
    $lead_ids = array_column($leads, 'id');
    
    if (!empty($lead_ids)) {
        $tracker_query = "SELECT * FROM downpayment_tracker WHERE lead_id IN (" . implode(',', $lead_ids) . ")";
        $tracker_result = $conn->query($tracker_query);
        
        if ($tracker_result) {
            while ($tracker = $tracker_result->fetch_assoc()) {
                $trackers[$tracker['lead_id']] = $tracker;
            }
        }
    }
}

// Filter by progress if needed
if (!empty($filter_progress)) {
    $filtered_leads = [];
    foreach ($leads as $lead) {
        if (isset($trackers[$lead['id']])) {
            $progress_rate = $trackers[$lead['id']]['progress_rate'];
            
            if ($filter_progress == 'low' && $progress_rate < 33) {
                $filtered_leads[] = $lead;
            } elseif ($filter_progress == 'medium' && $progress_rate >= 33 && $progress_rate < 66) {
                $filtered_leads[] = $lead;
            } elseif ($filter_progress == 'high' && $progress_rate >= 66) {
                $filtered_leads[] = $lead;
            }
        } elseif ($filter_progress == 'low') {
            // No tracker means 0% progress
            $filtered_leads[] = $lead;
        }
    }
    $leads = $filtered_leads;
}

// Handle form submission for updating tracker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracker'])) {
    $lead_id = $_POST['lead_id'];
    $reservation_date = !empty($_POST['reservation_date']) ? $_POST['reservation_date'] : null;
    $requirements_complete = isset($_POST['requirements_complete']) ? 1 : 0;
    $spot_dp = isset($_POST['spot_dp']) ? 1 : 0;
    $dp_terms = $spot_dp ? 1 : $_POST['dp_terms'];
    $current_dp_stage = $spot_dp ? 1 : $_POST['current_dp_stage'];
    $pagibig_bank_approval = isset($_POST['pagibig_bank_approval']) ? 1 : 0;
    $loan_takeout = isset($_POST['loan_takeout']) ? 1 : 0;
    $turnover = isset($_POST['turnover']) ? 1 : 0;
    
    // Calculate total stages based on DP terms
    $total_dp_stages = $dp_terms;
    
    // Calculate progress rate
    $completed_steps = 0;
    $total_steps = 4; // Base steps: requirements, current stage, pagibig/bank approval, loan takeout
    
    if ($requirements_complete) $completed_steps++;
    if ($spot_dp || $current_dp_stage == $total_dp_stages) $completed_steps++;
    if ($pagibig_bank_approval) $completed_steps++;
    if ($loan_takeout) $completed_steps++;
    if ($turnover) $completed_steps++;
    
    $progress_rate = ($completed_steps / ($total_steps + 1)) * 100; // +1 for turnover
    
    // Check if tracker exists
    $check_stmt = $conn->prepare("SELECT id FROM downpayment_tracker WHERE lead_id = ?");
    $check_stmt->bind_param("i", $lead_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $tracker_exists = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($tracker_exists) {
        // Update existing tracker
        $update_stmt = $conn->prepare("UPDATE downpayment_tracker SET 
                        reservation_date = ?, 
                        requirements_complete = ?, 
                        spot_dp = ?,
                        dp_terms = ?, 
                        current_dp_stage = ?, 
                        total_dp_stages = ?, 
                        pagibig_bank_approval = ?, 
                        loan_takeout = ?, 
                        turnover = ?, 
                        progress_rate = ?, 
                        updated_at = NOW() 
                        WHERE lead_id = ?");
        $update_stmt->bind_param("siisiiiiddi", 
            $reservation_date, 
            $requirements_complete, 
            $spot_dp,
            $dp_terms, 
            $current_dp_stage, 
            $total_dp_stages, 
            $pagibig_bank_approval, 
            $loan_takeout, 
            $turnover, 
            $progress_rate, 
            $lead_id
        );
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // Create new tracker
        $insert_stmt = $conn->prepare("INSERT INTO downpayment_tracker 
                        (lead_id, reservation_date, requirements_complete, spot_dp, dp_terms, current_dp_stage, 
                        total_dp_stages, pagibig_bank_approval, loan_takeout, turnover, progress_rate) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("isiisiiiidi", 
            $lead_id, 
            $reservation_date, 
            $requirements_complete, 
            $spot_dp,
            $dp_terms, 
            $current_dp_stage, 
            $total_dp_stages, 
            $pagibig_bank_approval, 
            $loan_takeout, 
            $turnover, 
            $progress_rate
        );
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    // Add activity log
    addLeadActivity($lead_id, $user_id, "Downpayment Tracker", "Updated downpayment tracker information");
    
    // Redirect to refresh the page with the same search/filter parameters
    $redirect_url = "dp-stage.php?success=1";
    if (!empty($search_query)) $redirect_url .= "&search=" . urlencode($search_query);
    if (!empty($filter_agent)) $redirect_url .= "&agent=" . urlencode($filter_agent);
    if (!empty($filter_developer)) $redirect_url .= "&developer=" . urlencode($filter_developer);
    if (!empty($filter_progress)) $redirect_url .= "&progress=" . urlencode($filter_progress);
    
    header("Location: $redirect_url");
    exit();
}

// Check for success message
$success = '';
if (isset($_GET['success'])) {
    $success = "Tracker updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $show_completed ? 'Completed' : 'In Progress' ?> Downpayment Leads - Real Estate Lead Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Additional CSS for the redesigned content -->
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
    }
    
    /* Main container styles */
    .container {
        display: flex;
        min-height: 100vh;
        width: 100%;
    }
    
    .main-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
    }
    
    /* Content area styles */
    .content {
        flex: 1;
        padding: 1.5rem;
        width: 100%;
        margin: 0;
        min-height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
    }
    
    /* When sidebar is collapsed, adjust content width and centering */
    .sidebar-collapsed .content {
        max-width: 1200px;
    }
    
    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .content h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 0.5rem;
    }
    
    .content p {
        color: var(--gray-600);
        margin-bottom: 1.5rem;
    }
    
    /* Success message */
    .success-message {
        background-color: var(--success-light);
        color: #065f46;
        border-left: 4px solid var(--success);
        border-radius: var(--border-radius);
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .success-message i {
        margin-right: 0.75rem;
        font-size: 1.25rem;
    }
    
    /* Card styles */
    .card {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        margin-bottom: 1.5rem;
        border: 1px solid var(--gray-200);
        overflow: hidden;
    }
    
    .card-header {
        background-color: var(--gray-50);
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-header h3 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-800);
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    /* Search and filter section */
    .search-filter-container {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        margin-bottom: 1.5rem;
        border: 1px solid var(--gray-200);
        padding: 1.25rem;
    }
    
    .search-filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }
    
    .search-filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .search-filter-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--gray-700);
        font-size: 0.875rem;
    }
    
    .search-filter-group input,
    .search-filter-group select {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--gray-300);
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: var(--gray-800);
        background-color: #fff;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .search-filter-group input:focus,
    .search-filter-group select:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .search-filter-buttons {
        display: flex;
        gap: 0.75rem;
    }
    
    /* Button styles - IMPROVED */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.25rem;
        border-radius: 0.375rem;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        white-space: nowrap;
        text-decoration: none;
        box-shadow: var(--shadow-sm);
    }
    
    .btn:active {
        transform: translateY(1px);
    }
    
    .btn i {
        margin-right: 0.5rem;
        font-size: 0.875rem;
    }
    
    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: var(--primary-hover);
        box-shadow: var(--shadow);
    }
    
    .btn-outline {
        background-color: white;
        border-color: var(--gray-300);
        color: var(--gray-700);
    }
    
    .btn-outline:hover {
        background-color: var(--gray-100);
        color: var(--gray-900);
    }
    
    /* Table styles */
    .table-container {
        overflow-x: auto;
        border-radius: var(--border-radius);
    }
    
    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.875rem;
    }
    
    .table th {
        background-color: var(--gray-50);
        color: var(--gray-700);
        font-weight: 600;
        text-align: left;
        padding: 0.75rem 1.5rem;
        border-bottom: 2px solid var(--gray-200);
        white-space: nowrap;
    }
    
    .table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        vertical-align: top;
    }
    
    .table tr:last-child td {
        border-bottom: none;
    }
    
    .table tr:hover {
        background-color: var(--gray-50);
    }
    
    .table-empty {
        text-align: center;
        padding: 3rem 0;
        color: var(--gray-500);
    }
    
    .table-empty i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    /* Progress bar */
    .progress-container {
        height: 0.5rem;
        background-color: var(--gray-200);
        border-radius: 1rem;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }
    
    .progress-bar {
        height: 100%;
        border-radius: 1rem;
        transition: width 0.3s ease;
    }
    
    .progress-low {
        background-color: var(--danger);
    }
    
    .progress-medium {
        background-color: var(--warning);
    }
    
    .progress-high {
        background-color: var(--success);
    }
    
    /* Status badges */
    .status-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.375rem 0.75rem;
        border-radius: 2rem;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    
    .status-badge i {
        margin-right: 0.375rem;
    }
    
    .status-complete {
        background-color: var(--success-light);
        color: #065f46;
    }
    
    .status-pending {
        background-color: var(--warning-light);
        color: #92400e;
    }
    
    .status-badge.tooltip {
        position: relative;
    }
    
    .status-badge.tooltip:hover::after {
        content: attr(data-title);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        padding: 0.5rem;
        background-color: var(--gray-800);
        color: white;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        white-space: nowrap;
        z-index: 10;
        margin-bottom: 0.5rem;
    }
    
    /* Client info */
    .client-name {
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 0.25rem;
    }
    
    .client-details {
        color: var(--gray-600);
        font-size: 0.75rem;
        margin-top: 0.25rem;
    }
    
    /* Action buttons - IMPROVED */
    .action-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        border-radius: 0.375rem;
        margin-bottom: 0.5rem;
        width: 100%;
        justify-content: center;
    }
    
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    /* Modal styles */
    #trackerModal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.2s ease-out;
    }
    
    .modal-content {
        background-color: #fff;
        margin: 2rem auto;
        border-radius: 1rem;
        box-shadow: var(--shadow-lg);
        width: 90%;
        max-width: 600px;
        position: relative;
        max-height: calc(100vh - 4rem);
        display: flex;
        flex-direction: column;
        animation: slideIn 0.3s ease-out;
    }
    
    .modal-header {
        background-color: var(--gray-50);
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        border-radius: 1rem 1rem 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .modal-header span {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-500);
        cursor: pointer;
        line-height: 1;
        padding: 0.25rem;
        border-radius: 0.375rem;
        transition: all 0.2s ease;
    }
    
    .modal-header span:hover {
        color: var(--gray-700);
        background-color: var(--gray-200);
    }
    
    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        max-height: calc(100vh - 13rem); /* Account for header and footer */
    }
    
    .modal-footer {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        background-color: #fff;
        border-radius: 0 0 1rem 1rem;
        flex-shrink: 0;
    }
    
    /* Form layout improvements */
    .form-section {
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .form-section:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--gray-700);
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--gray-300);
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: var(--gray-800);
        background-color: #fff;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .form-check {
        display: flex;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .form-check-input {
        margin-right: 0.5rem;
    }
    
    .info-message {
        background-color: var(--primary-light);
        color: var(--primary);
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        margin-top: 1rem;
    }
    
    .info-message i {
        margin-right: 0.5rem;
    }
    
    .no-results {
        text-align: center;
        padding: 3rem 0;
        color: var(--gray-500);
    }
    
    .no-results i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .no-results h4 {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
        color: var(--gray-700);
    }
    
    .no-results p {
        margin: 0;
    }
    
    .no-results a {
        color: var(--primary);
        text-decoration: none;
    }
    
    .no-results a:hover {
        text-decoration: underline;
    }
    
    /* Responsive adjustments */
    @media (max-width: 640px) {
        .modal-content {
            margin: 1rem;
            width: calc(100% - 2rem);
            max-height: calc(100vh - 2rem);
        }
        
        .modal-body {
            max-height: calc(100vh - 11rem);
        }
        
        .milestones-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-height: 700px) {
        .modal-content {
            margin: 1rem auto;
        }
        
        .modal-body {
            padding: 1rem;
        }
    }
    
    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideIn {
        from { 
            opacity: 0;
            transform: translateY(-20px);
        }
        to { 
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Milestones Grid Layout */
    .milestones-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-top: 0.5rem;
    }
    
    .milestone-item {
        background-color: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 0.5rem;
        padding: 1rem;
        transition: all 0.2s ease;
    }
    
    .milestone-item:hover {
        background-color: var(--gray-100);
        border-color: var(--gray-300);
    }
    
    .milestone-item .form-check {
        margin: 0;
        display: flex;
        align-items: flex-start;
    }
    
    .milestone-item .form-check-input {
        margin-top: 0.25rem;
    }
    
    .milestone-item label {
        margin-left: 0.5rem;
        font-weight: 500;
        color: var(--gray-700);
        line-height: 1.4;
        cursor: pointer;
    }
    
    .milestone-item i {
        display: block;
        margin-bottom: 0.25rem;
        color: var(--primary);
        font-size: 1rem;
    }
    
    /* Responsive adjustments */
    @media (max-width: 480px) {
        .milestones-grid {
            grid-template-columns: 1fr;
        }
    }
    
    /* Checked state styling */
    .milestone-item .form-check-input:checked + label {
        color: var(--primary);
    }
    
    .milestone-item .form-check-input:checked + label i {
        color: var(--primary);
    }
    
    .milestone-item:has(.form-check-input:checked) {
        background-color: var(--primary-light);
        border-color: var(--primary);
    }

    .view-toggle {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }

    .view-toggle .btn {
        min-width: 120px;
    }

    .completion-date {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--gray-700);
        font-size: 0.875rem;
    }

    .completion-date i {
        color: var(--success);
    }

    .completed-stages {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .stage-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.375rem 0.75rem;
        background-color: var(--success-light);
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: #065f46;
    }

    .stage-item i {
        width: 1rem;
        text-align: center;
    }

    .stage-date {
        margin-left: auto;
        font-size: 0.75rem;
        opacity: 0.8;
    }

    .status-badge.status-complete {
        background-color: var(--success-light);
        color: #065f46;
    }

    .status-badge i {
        margin-right: 0.375rem;
    }
</style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <div class="content-header">
                    <div>
                        <h1><?= $show_completed ? 'Completed Downpayment Leads' : 'Downpayment Stage Tracker' ?></h1>
                        <p><?= $show_completed ? 'View all completed downpayment leads.' : 'Track and manage leads in the downpayment stage.' ?></p>
                    </div>
                    <div class="view-toggle">
                        <a href="dp-stage.php" class="btn <?= !$show_completed ? 'btn-primary' : 'btn-outline' ?>">
                            <i class="fas fa-clock"></i> In Progress
                        </a>
                        <a href="dp-stage.php?view=completed" class="btn <?= $show_completed ? 'btn-primary' : 'btn-outline' ?>">
                            <i class="fas fa-check-circle"></i> Completed
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
                <?php endif; ?>
                
                <!-- Search and Filter Section -->
                <div class="search-filter-container">
                    <form class="search-filter-form" method="GET" action="dp-stage.php">
                        <?php if ($show_completed): ?>
                        <input type="hidden" name="view" value="completed">
                        <?php endif; ?>
                        <div class="search-filter-group">
                            <label for="search">Search Client</label>
                            <input type="text" id="search" name="search" placeholder="Name, phone or email" value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                        
                        <div class="search-filter-group">
                            <label for="agent">Filter by Agent</label>
                            <select id="agent" name="agent">
                                <option value="">All Agents</option>
                                <?php foreach ($agents as $id => $name): ?>
                                <option value="<?= $id ?>" <?= $filter_agent == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="search-filter-group">
                            <label for="developer">Filter by Developer</label>
                            <select id="developer" name="developer">
                                <option value="">All Developers</option>
                                <?php foreach ($developers as $dev): ?>
                                <option value="<?= $dev ?>" <?= $filter_developer == $dev ? 'selected' : '' ?>><?= htmlspecialchars($dev) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (!$show_completed): ?>
                        <div class="search-filter-group">
                            <label for="progress">Filter by Progress</label>
                            <select id="progress" name="progress">
                                <option value="">All Progress</option>
                                <option value="low" <?= $filter_progress == 'low' ? 'selected' : '' ?>>Low (0-33%)</option>
                                <option value="medium" <?= $filter_progress == 'medium' ? 'selected' : '' ?>>Medium (34-66%)</option>
                                <option value="high" <?= $filter_progress == 'high' ? 'selected' : '' ?>>High (67-100%)</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="search-filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="dp-stage.php<?= $show_completed ? '?view=completed' : '' ?>" class="btn btn-outline">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas <?= $show_completed ? 'fa-check-circle' : 'fa-chart-line' ?>"></i> 
                            <?= $show_completed ? 'Completed Leads' : 'In Progress Leads' ?>
                            <?php if (count($leads) > 0): ?>
                            <span style="font-size: 0.875rem; color: var(--gray-500); margin-left: 0.5rem;">
                                (<?= count($leads) ?> <?= count($leads) == 1 ? 'lead' : 'leads' ?>)
                            </span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($leads)): ?>
                        <div class="no-results">
                            <i class="fas <?= $show_completed ? 'fa-check-circle' : 'fa-search' ?>"></i>
                            <h4>No <?= $show_completed ? 'completed' : '' ?> leads found</h4>
                            <p>
                                <?php if (!$show_completed && (!empty($search_query) || !empty($filter_agent) || !empty($filter_developer) || !empty($filter_progress))): ?>
                                    Try adjusting your search filters or <a href="dp-stage.php">view all leads</a>.
                                <?php else: ?>
                                    <?= $show_completed ? 'Completed leads will appear here once all milestones are achieved.' : 'There are currently no leads in the downpayment stage.' ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Agent</th>
                                        <th>Project Details</th>
                                        <th>DP Terms</th>
                                        <th>Current Stage</th>
                                        <th>Progress</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leads as $lead): ?>
                                    <tr>
                                        <td>
                                            <div class="client-name"><?= htmlspecialchars($lead['client_name']) ?></div>
                                            <div class="client-details">
                                                <?php if (!empty($lead['phone'])): ?>
                                                <div><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($lead['phone']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($lead['email'])): ?>
                                                <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($lead['email']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($lead['agent_name']) ?></td>
                                        <td>
                                            <div><strong><?= htmlspecialchars($lead['developer']) ?></strong></div>
                                            <div class="client-details"><?= htmlspecialchars($lead['project_model']) ?></div>
                                            <?php if (!empty($lead['price'])): ?>
                                            <div class="client-details">₱<?= number_format($lead['price'], 2) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($trackers[$lead['id']])): ?>
                                                <?php if ($trackers[$lead['id']]['spot_dp']): ?>
                                                    <span class="status-badge status-complete">
                                                        <i class="fas fa-check"></i> Spot DP
                                                    </span>
                                                <?php else: ?>
                                                <span class="status-badge <?= $trackers[$lead['id']]['dp_terms'] <= 12 ? 'status-complete' : 'status-pending' ?>">
                                                    <?= htmlspecialchars($trackers[$lead['id']]['dp_terms']) ?> months
                                                </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--gray-500); font-style: italic;">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($trackers[$lead['id']])): ?>
                                                <?php if ($trackers[$lead['id']]['spot_dp']): ?>
                                                    <div>
                                                        <strong>Spot Downpayment</strong>
                                                    </div>
                                                <?php else: ?>
                                                <div>
                                                    <strong>Month <?= htmlspecialchars($trackers[$lead['id']]['current_dp_stage']) ?></strong> of 
                                                    <?= htmlspecialchars($trackers[$lead['id']]['total_dp_stages']) ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($trackers[$lead['id']]['reservation_date']): ?>
                                                    <div class="client-details">
                                                        <i class="far fa-calendar-check"></i> Reserved: <?= date('M d, Y', strtotime($trackers[$lead['id']]['reservation_date'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--gray-500); font-style: italic;">Not started</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $progress = 0;
                                            $progressClass = 'progress-low';
                                            
                                            if (isset($trackers[$lead['id']])) {
                                                $progress = $trackers[$lead['id']]['progress_rate'];
                                                if ($progress >= 66) {
                                                    $progressClass = 'progress-high';
                                                } elseif ($progress >= 33) {
                                                    $progressClass = 'progress-medium';
                                                }
                                            }
                                            ?>
                                            <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                                <div class="progress-container" style="width: 100px; margin-right: 0.75rem;">
                                                    <div class="progress-bar <?= $progressClass ?>" style="width: <?= number_format($progress, 0) ?>%;"></div>
                                                </div>
                                                <span style="font-weight: 600;"><?= number_format($progress, 0) ?>%</span>
                                            </div>
                                            
                                            <?php if (isset($trackers[$lead['id']])): ?>
                                                <div class="status-badges">
                                                    <span class="status-badge <?= $trackers[$lead['id']]['requirements_complete'] ? 'status-complete' : 'status-pending' ?> tooltip">
                                                        <i class="fas <?= $trackers[$lead['id']]['requirements_complete'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Requirement Stage
                                                    </span>
                                                    <span class="status-badge <?= $trackers[$lead['id']]['pagibig_bank_approval'] ? 'status-complete' : 'status-pending' ?> tooltip">
                                                        <i class="fas <?= $trackers[$lead['id']]['pagibig_bank_approval'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Pag-IBIG/Bank Approval
                                                    </span>
                                                    <span class="status-badge <?= $trackers[$lead['id']]['loan_takeout'] ? 'status-complete' : 'status-pending' ?> tooltip">
                                                        <i class="fas <?= $trackers[$lead['id']]['loan_takeout'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Loan Takeout
                                                    </span>
                                                    <span class="status-badge <?= $trackers[$lead['id']]['turnover'] ? 'status-complete' : 'status-pending' ?> tooltip">
                                                        <i class="fas <?= $trackers[$lead['id']]['turnover'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Turnover
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div style="font-size: 0.75rem; color: var(--gray-500);">No tracker data</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if (!$show_completed): ?>
                                                <button class="btn btn-primary action-btn" onclick="openTrackerModal(<?= $lead['id'] ?>, '<?= htmlspecialchars($lead['client_name']) ?>', '<?= htmlspecialchars($lead['developer']) ?>', '<?= htmlspecialchars($lead['project_model']) ?>')">
                                                    <i class="fas fa-edit"></i> <span>Update</span>
                                                </button>
                                                <?php endif; ?>
                                                <a href="lead-details.php?id=<?= $lead['id'] ?>" class="btn btn-outline action-btn">
                                                    <i class="fas fa-eye"></i> <span>View</span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tracker Modal -->
    <?php if (!$show_completed): ?>
    <div id="trackerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-chart-line"></i> Update Downpayment Tracker</h3>
                <span onclick="closeTrackerModal()">&times;</span>
            </div>
            <form id="trackerForm" method="post">
                <div class="modal-body">
                    <input type="hidden" name="lead_id" id="lead_id">
                    <input type="hidden" name="update_tracker" value="1">
                    
                    <div class="form-section">
                        <div class="form-group">
                            <label for="client_name">Client Name:</label>
                            <input type="text" id="client_name" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="project_details">Project:</label>
                            <input type="text" id="project_details" readonly>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="form-group">
                            <label for="reservation_date">Reservation Date:</label>
                            <input type="date" id="reservation_date" name="reservation_date">
                        </div>
                        
                        <div style="margin: 1.5rem 0; padding: 1rem; background: var(--gray-50); border-radius: var(--border-radius); border: 1px solid var(--gray-200);">
                            <div class="form-check" style="margin-bottom: 0;">
                                <input type="checkbox" id="spot_dp" name="spot_dp" class="form-check-input">
                                <label for="spot_dp" style="font-weight: 600; color: var(--gray-800);">
                                    <i class="fas fa-lightning-bolt"></i> Spot Downpayment
                                </label>
                                <div style="margin-left: 1.75rem; font-size: 0.875rem; color: var(--gray-600);">
                                    Check this if the client paid the downpayment in full
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="terms_section" class="form-section">
                        <div class="form-group">
                            <label for="dp_terms">Downpayment Terms:</label>
                            <select id="dp_terms" name="dp_terms" required>
                                <option value="6">6 months</option>
                                <option value="9">9 months</option>
                                <option value="12">12 months</option>
                                <option value="15">15 months</option>
                                <option value="18">18 months</option>
                                <option value="24">24 months</option>
                                <option value="36">36 months</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="current_dp_stage">Current Downpayment Stage:</label>
                            <select id="current_dp_stage" name="current_dp_stage" required>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; margin-bottom: 1rem;">
                        <label style="font-weight: 600; color: var(--gray-700); margin-bottom: 1rem; display: block;">
                            <i class="fas fa-tasks"></i> Milestones
                        </label>
                        <div class="milestones-grid">
                            <div class="milestone-item">
                                <div class="form-check">
                                    <input type="checkbox" id="requirements_complete" name="requirements_complete" class="form-check-input">
                                    <label for="requirements_complete">
                                        <i class="fas fa-file-alt"></i>
                                        Requirements Complete
                                    </label>
                                </div>
                            </div>
                            
                            <div class="milestone-item">
                                <div class="form-check">
                                    <input type="checkbox" id="pagibig_bank_approval" name="pagibig_bank_approval" class="form-check-input">
                                    <label for="pagibig_bank_approval">
                                        <i class="fas fa-stamp"></i>
                                        Pag-IBIG/Bank Approval
                                    </label>
                                </div>
                            </div>
                            
                            <div class="milestone-item">
                                <div class="form-check">
                                    <input type="checkbox" id="loan_takeout" name="loan_takeout" class="form-check-input">
                                    <label for="loan_takeout">
                                        <i class="fas fa-money-check-alt"></i>
                                        Loan Takeout
                                    </label>
                                </div>
                            </div>
                            
                            <div class="milestone-item">
                                <div class="form-check">
                                    <input type="checkbox" id="turnover" name="turnover" class="form-check-input">
                                    <label for="turnover">
                                        <i class="fas fa-key"></i>
                                        Turnover
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-message">
                        <i class="fas fa-info-circle"></i> Progress is automatically calculated based on completed steps.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeTrackerModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    // Function to open the tracker modal
    function openTrackerModal(leadId, clientName, developer, projectModel) {
        document.getElementById('lead_id').value = leadId;
        document.getElementById('client_name').value = clientName;
        document.getElementById('project_details').value = developer + ' - ' + projectModel;
        
        // Clear form
        document.getElementById('reservation_date').value = '';
        document.getElementById('requirements_complete').checked = false;
        document.getElementById('spot_dp').checked = false;
        document.getElementById('dp_terms').value = '12';
        document.getElementById('pagibig_bank_approval').checked = false;
        document.getElementById('loan_takeout').checked = false;
        document.getElementById('turnover').checked = false;
        
        // Populate DP stages dropdown
        updateDpStages();
        
        // Fetch existing tracker data if available
        fetchTrackerData(leadId);
        
        // Show the modal
        document.getElementById('trackerModal').style.display = 'block';
        
        // Add body class to prevent scrolling
        document.body.style.overflow = 'hidden';
    }
    
    // Function to close the tracker modal
    function closeTrackerModal() {
        document.getElementById('trackerModal').style.display = 'none';
        
        // Remove body class to allow scrolling
        document.body.style.overflow = '';
    }
    
    // Function to update DP stages dropdown based on selected terms
    function updateDpStages() {
        var terms = parseInt(document.getElementById('dp_terms').value);
        var currentStage = document.getElementById('current_dp_stage');
        var selectedValue = currentStage.value; // Store current selection
        
        // Clear current options
        currentStage.innerHTML = '';
        
        // Add options based on terms
        for (var i = 1; i <= terms; i++) {
            var option = document.createElement('option');
            option.value = i;
            option.text = 'Month ' + i + ' of ' + terms;
            currentStage.appendChild(option);
        }
        
        // Restore selection if it's still valid
        if (selectedValue && selectedValue <= terms) {
            currentStage.value = selectedValue;
        } else {
            currentStage.value = 1; // Default to first month
        }
    }
    
    // Function to fetch tracker data
    function fetchTrackerData(leadId) {
        // Create a new XMLHttpRequest
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'api/get-tracker.php?lead_id=' + leadId, true);
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.tracker) {
                        var tracker = response.tracker;
                        
                        if (tracker.reservation_date) {
                            document.getElementById('reservation_date').value = tracker.reservation_date;
                        }
                        
                        document.getElementById('requirements_complete').checked = tracker.requirements_complete == 1;
                        document.getElementById('spot_dp').checked = tracker.spot_dp == 1;
                        
                        // Set DP terms first
                        document.getElementById('dp_terms').value = tracker.dp_terms;
                        
                        // Update DP stages dropdown based on terms
                        updateDpStages();
                        
                        // Then set the current stage
                        document.getElementById('current_dp_stage').value = tracker.current_dp_stage;
                        
                        document.getElementById('pagibig_bank_approval').checked = tracker.pagibig_bank_approval == 1;
                        document.getElementById('loan_takeout').checked = tracker.loan_takeout == 1;
                        document.getElementById('turnover').checked = tracker.turnover == 1;
                        
                        // Update terms section visibility
                        toggleTermsSection();
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                }
            } else {
                console.error('Server returned an error');
            }
        };
        
        xhr.onerror = function() {
            console.error('Connection error');
        };
        
        xhr.send();
    }
    
    // Function to toggle terms section visibility with smooth transition
    function toggleTermsSection() {
        var termsSection = document.getElementById('terms_section');
        var spotDpCheckbox = document.getElementById('spot_dp');
        var dpTermsSelect = document.getElementById('dp_terms');
        var currentDpStageSelect = document.getElementById('current_dp_stage');
        
        if (spotDpCheckbox.checked) {
            termsSection.style.opacity = '0.5';
            termsSection.style.pointerEvents = 'none';
            dpTermsSelect.disabled = true;
            currentDpStageSelect.disabled = true;
        } else {
            termsSection.style.opacity = '1';
            termsSection.style.pointerEvents = 'auto';
            dpTermsSelect.disabled = false;
            currentDpStageSelect.disabled = false;
        }
    }
    
    // Add event listener for spot DP checkbox with immediate effect
    document.getElementById('spot_dp').addEventListener('change', function() {
        toggleTermsSection();
        if (this.checked) {
            // Reset and disable terms fields
            document.getElementById('dp_terms').value = '1';
            updateDpStages();
            document.getElementById('current_dp_stage').value = '1';
        } else {
            // Re-enable and set default values
            document.getElementById('dp_terms').value = '12';
            updateDpStages();
        }
    });
    
    // Add event listener for DP terms change
    document.getElementById('dp_terms').addEventListener('change', function() {
        updateDpStages();
    });
    
    // Close the modal when clicking outside of it
    window.onclick = function(event) {
        var modal = document.getElementById('trackerModal');
        if (event.target == modal) {
            closeTrackerModal();
        }
    };
    
    // Highlight active filters
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('search') && urlParams.get('search') !== '') {
            document.getElementById('search').classList.add('filter-active');
        }
        
        if (urlParams.has('agent') && urlParams.get('agent') !== '') {
            document.getElementById('agent').classList.add('filter-active');
        }
        
        if (urlParams.has('developer') && urlParams.get('developer') !== '') {
            document.getElementById('developer').classList.add('filter-active');
        }
        
        if (urlParams.has('progress') && urlParams.get('progress') !== '') {
            document.getElementById('progress').classList.add('filter-active');
        }
    });
    
    // Function to check if sidebar is collapsed and adjust content accordingly
    function checkSidebarState() {
        // This selector may need to be adjusted based on your sidebar implementation
        const sidebarElement = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (sidebarElement) {
            // Check if sidebar is collapsed (this class name may need to be adjusted)
            const isCollapsed = sidebarElement.classList.contains('collapsed') || 
                               getComputedStyle(sidebarElement).width === '0px' ||
                               getComputedStyle(sidebarElement).width === '60px' ||
                               !sidebarElement.offsetWidth;
            
            if (isCollapsed) {
                mainContent.classList.add('sidebar-collapsed');
            } else {
                mainContent.classList.remove('sidebar-collapsed');
            }
        }
    }
    
    // Run on page load
    document.addEventListener('DOMContentLoaded', checkSidebarState);
    
    // Run when window is resized
    window.addEventListener('resize', checkSidebarState);
    
    // Check periodically for sidebar state changes
    setInterval(checkSidebarState, 1000);

    // Update the sidebar active state based on the view
    document.addEventListener('DOMContentLoaded', function() {
        const dpMenuItem = document.querySelector('.has-submenu');
        const submenuLinks = document.querySelectorAll('.submenu-link');
        if (dpMenuItem) {
            dpMenuItem.classList.add('active');
        }
        
        // Check if we're in completed view
        const urlParams = new URLSearchParams(window.location.search);
        const isCompleted = urlParams.get('view') === 'completed';
        
        // Set active class on appropriate submenu link
        if (submenuLinks.length > 1) {
            if (isCompleted) {
                submenuLinks[1].classList.add('active');
            } else {
                submenuLinks[0].classList.add('active');
            }
        }
    });
</script>   
</body>
</html>