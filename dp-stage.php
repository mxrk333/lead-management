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
    $query .= " AND l.user_id = " . $user_id;
} elseif ($user['role'] == 'supervisor' || $user['role'] == 'manager') {
    $query .= " AND u.team_id = " . $user['team_id'];
}

// Add search conditions
if (!empty($search_query)) {
    $search_param = "%$search_query%";
    $query .= " AND (l.client_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ?)";
}

// Add filter conditions
if (!empty($filter_agent)) {
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
    $dp_terms = $spot_dp ? '1' : $_POST['dp_terms'];
    $current_dp_stage = $spot_dp ? 1 : intval($_POST['current_dp_stage']);
    $pagibig_bank_approval = isset($_POST['pagibig_bank_approval']) ? 1 : 0;
    $loan_takeout = isset($_POST['loan_takeout']) ? 1 : 0;
    $turnover = isset($_POST['turnover']) ? 1 : 0;
    
    // Calculate total stages based on DP terms
    $total_dp_stages = intval($dp_terms);
    
    // Calculate progress rate
    $completed_steps = 0;
    $total_steps = 5; // requirements, dp stages, pagibig/bank approval, loan takeout, turnover
    
    if ($requirements_complete) $completed_steps++;
    if ($spot_dp || $current_dp_stage == $total_dp_stages) $completed_steps++;
    if ($pagibig_bank_approval) $completed_steps++;
    if ($loan_takeout) $completed_steps++;
    if ($turnover) $completed_steps++;
    
    $progress_rate = ($completed_steps / $total_steps) * 100;
    
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
        $update_stmt->bind_param("siisiiiiidi", 
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
        $insert_stmt->bind_param("isiisiiiiid", 
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
    
    // Redirect to refresh the page
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
    
    /* Button styles */
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
    
    .btn-success {
        background-color: var(--success);
        color: white;
    }
    
    .btn-success:hover {
        background-color: #059669;
        box-shadow: var(--shadow);
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
    
    /* Action buttons */
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
    #trackerModal, #DpModaviewl {
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
        max-width: 900px;
        position: relative;
        max-height: calc(100vh - 4rem);
        display: flex;
        flex-direction: column;
        animation: slideIn 0.3s ease-out;
    }
    
    .modal-header {
        background: linear-gradient(135deg, var(--primary), #6366f1);
        color: white;
        padding: 1.5rem 2rem;
        border-radius: 1rem 1rem 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .modal-header h3 i {
        font-size: 1.75rem;
        opacity: 0.9;
    }
    
    .modal-header .close {
        font-size: 2rem;
        font-weight: 300;
        cursor: pointer;
        line-height: 1;
        padding: 0.5rem;
        border-radius: 0.5rem;
        transition: all 0.2s ease;
        opacity: 0.8;
        background: rgba(255, 255, 255, 0.1);
        width: 3rem;
        height: 3rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-header .close:hover {
        opacity: 1;
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }
    
    .modal-body {
        padding: 2rem;
        overflow-y: auto;
        max-height: calc(100vh - 16rem);
        flex: 1;
    }
    
    .modal-footer {
        padding: 1.5rem 2rem;
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        background-color: var(--gray-50);
        border-radius: 0 0 1rem 1rem;
        flex-shrink: 0;
    }
    
    /* View DP Modal Specific Styles */
    .client-info-card {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        border: 2px solid var(--primary);
        border-radius: 1rem;
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .client-info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), #6366f1, var(--success));
    }
    
    .client-info-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .client-info-main {
        flex: 1;
    }
    
    .client-name-large {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--gray-900);
        margin-bottom: 0.5rem;
        line-height: 1.2;
    }
    
    .project-info {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .project-detail {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--gray-700);
        font-size: 1rem;
    }
    
    .project-detail i {
        color: var(--primary);
        width: 1.25rem;
        text-align: center;
    }
    
    .price-display {
        background: var(--success);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 2rem;
        font-size: 1.25rem;
        font-weight: 700;
        text-align: center;
        box-shadow: var(--shadow-md);
    }
    
    /* DP Terms Display */
    .dp-terms-section {
        margin-bottom: 2rem;
    }
    
    .section-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--gray-200);
    }
    
    .section-title i {
        color: var(--primary);
        font-size: 1.5rem;
    }
    
    .dp-terms-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .terms-card {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 1rem;
        padding: 2rem;
        position: relative;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
    }
    
    .terms-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .terms-card.spot-dp {
        background: linear-gradient(135deg, var(--success-light), #d1fae5);
        border-color: var(--success);
    }
    
    .terms-card.installment {
        background: linear-gradient(135deg, var(--primary-light), #e0e7ff);
        border-color: var(--primary);
    }
    
    .terms-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .terms-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }
    
    .terms-card.spot-dp .terms-icon {
        background: var(--success);
    }
    
    .terms-card.installment .terms-icon {
        background: var(--primary);
    }
    
    .terms-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }
    
    .terms-card.spot-dp .terms-title {
        color: #065f46;
    }
    
    .terms-card.installment .terms-title {
        color: var(--primary);
    }
    
    .terms-details {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .terms-detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        background: rgba(255, 255, 255, 0.7);
        border-radius: 0.5rem;
        border: 1px solid rgba(255, 255, 255, 0.5);
    }
    
    .terms-detail-label {
        font-weight: 600;
        color: var(--gray-700);
    }
    
    .terms-detail-value {
        font-weight: 700;
        font-size: 1.125rem;
    }
    
    .reservation-card {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 1rem;
        padding: 2rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
    }
    
    .reservation-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .reservation-icon {
        width: 4rem;
        height: 4rem;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 1rem;
    }
    
    .reservation-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: 0.5rem;
    }
    
    .reservation-date {
        font-size: 1.125rem;
        color: var(--gray-600);
        font-weight: 500;
    }
    
    /* Monthly Progress Section */
    .monthly-progress-section {
        margin-bottom: 2rem;
    }
    
    .monthly-progress-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .monthly-progress-item {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 0.75rem;
        padding: 1rem;
        text-align: center;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .monthly-progress-item.completed {
        border-color: var(--success);
        background: var(--success-light);
    }
    
    .monthly-progress-item.current {
        border-color: var(--warning);
        background: var(--warning-light);
        animation: pulse 2s infinite;
    }
    
    .monthly-progress-item.pending {
        border-color: var(--gray-300);
        background: var(--gray-50);
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
    }
    
    .monthly-progress-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--gray-300);
    }
    
    .monthly-progress-item.completed::before {
        background: var(--success);
    }
    
    .monthly-progress-item.current::before {
        background: var(--warning);
    }
    
    .month-number {
        font-size: 1.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    
    .monthly-progress-item.completed .month-number {
        color: var(--success);
    }
    
    .monthly-progress-item.current .month-number {
        color: var(--warning);
    }
    
    .monthly-progress-item.pending .month-number {
        color: var(--gray-500);
    }
    
    .month-status {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .monthly-progress-item.completed .month-status {
        color: #065f46;
    }
    
    .monthly-progress-item.current .month-status {
        color: #92400e;
    }
    
    .monthly-progress-item.pending .month-status {
        color: var(--gray-500);
    }
    
    /* Overall Progress Section */
    .progress-section {
        margin-bottom: 2rem;
    }
    
    .progress-overview-card {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        border: 2px solid var(--gray-200);
        border-radius: 1rem;
        padding: 2rem;
        text-align: center;
    }
    
    .progress-circle-container {
        position: relative;
        display: inline-block;
        margin-bottom: 1.5rem;
    }
    
    .progress-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: conic-gradient(var(--success) 0deg, var(--success) var(--progress-angle, 0deg), var(--gray-200) var(--progress-angle, 0deg));
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .progress-circle::before {
        content: '';
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: white;
        position: absolute;
    }
    
    .progress-percentage {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--gray-800);
        position: relative;
        z-index: 1;
    }
    
    .progress-label {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-600);
        margin-bottom: 1rem;
    }
    
    /* Milestones Section */
    .milestones-section {
        margin-bottom: 2rem;
    }
    
    .milestones-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .milestone-card {
        background: white;
        border: 2px solid var(--gray-200);
        border-radius: 1rem;
        padding: 0;
        transition: all 0.3s ease;
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    
    .milestone-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .milestone-card.completed {
        border-color: var(--success);
        background: var(--success-light);
    }
    
    .milestone-card.pending {
        border-color: var(--gray-300);
        background: var(--gray-50);
    }
    
    .milestone-content {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        gap: 1.5rem;
    }
    
    .milestone-icon-container {
        width: 4rem;
        height: 4rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        flex-shrink: 0;
    }
    
    .milestone-card.completed .milestone-icon-container {
        background: var(--success);
        color: white;
    }
    
    .milestone-card.pending .milestone-icon-container {
        background: var(--gray-300);
        color: var(--gray-600);
    }
    
    .milestone-info {
        flex: 1;
    }
    
    .milestone-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .milestone-card.completed .milestone-title {
        color: #065f46;
    }
    
    .milestone-card.pending .milestone-title {
        color: var(--gray-700);
    }
    
    .milestone-description {
        font-size: 0.875rem;
        line-height: 1.5;
    }
    
    .milestone-card.completed .milestone-description {
        color: #047857;
    }
    
    .milestone-card.pending .milestone-description {
        color: var(--gray-600);
    }
    
    .milestone-status-indicator {
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .milestone-card.completed .milestone-status-indicator {
        background: var(--success);
        color: white;
    }
    
    .milestone-card.pending .milestone-status-indicator {
        background: var(--gray-200);
        color: var(--gray-500);
    }
    
    /* Form styles for edit modal */
    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 2rem;
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
        font-weight: 600;
        color: var(--gray-700);
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-200);
        border-radius: 0.5rem;
        font-size: 1rem;
        color: var(--gray-800);
        background-color: #fff;
        transition: all 0.2s ease;
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
        margin-bottom: 1rem;
        padding: 1rem;
        background: var(--gray-50);
        border: 2px solid var(--gray-200);
        border-radius: 0.75rem;
        transition: all 0.2s ease;
    }
    
    .form-check:hover {
        background: var(--gray-100);
        border-color: var(--gray-300);
    }
    
    .form-check-input {
        margin-right: 1rem;
        width: 1.25rem;
        height: 1.25rem;
    }
    
    .form-check label {
        font-weight: 600;
        color: var(--gray-700);
        cursor: pointer;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-check-input:checked + label {
        color: var(--primary);
    }
    
    .form-check:has(.form-check-input:checked) {
        background: var(--primary-light);
        border-color: var(--primary);
    }
    
    .info-message {
        background: linear-gradient(135deg, var(--primary-light), #e0e7ff);
        border: 2px solid var(--primary);
        color: var(--primary);
        padding: 1rem 1.5rem;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        margin-top: 1.5rem;
    }
    
    .info-message i {
        margin-right: 0.75rem;
        font-size: 1.25rem;
    }
    
    .view-toggle {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }
    
    .view-toggle .btn {
        min-width: 140px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dp-terms-grid {
            grid-template-columns: 1fr;
        }
        
        .client-info-header {
            flex-direction: column;
            gap: 1rem;
        }
        
        .monthly-progress-grid {
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        }
        
        .milestone-content {
            padding: 1rem;
            gap: 1rem;
        }
        
        .milestone-icon-container {
            width: 3rem;
            height: 3rem;
            font-size: 1.5rem;
        }
    }
    
    /* Animations */
    @keyframes slideIn {
        from { 
            opacity: 0;
            transform: translateY(-30px) scale(0.95);
        }
        to { 
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    @keyframes progressFill {
        from { width: 0%; }
        to { width: var(--progress-width); }
    }
    
    .progress-bar.animate {
        animation: progressFill 1.5s ease-out;
    }
    
    /* No results styling */
    .no-results {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--gray-500);
    }
    
    .no-results i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.5;
        color: var(--gray-400);
    }
    
    .no-results h4 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
        color: var(--gray-700);
        font-weight: 600;
    }
    
    .no-results p {
        margin: 0;
        font-size: 1rem;
        line-height: 1.6;
    }
    
    .no-results a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }
    
    .no-results a:hover {
        text-decoration: underline;
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
                                                    <span class="status-badge <?= $trackers[$lead['id']]['requirements_complete'] ? 'status-complete' : 'status-pending' ?>">
                                                        <i class="fas <?= $trackers[$lead['id']]['requirements_complete'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Requirements
                                                    </span>
                                                    <span class="status-badge <?= $trackers[$lead['id']]['pagibig_bank_approval'] ? 'status-complete' : 'status-pending' ?>">
                                                        <i class="fas <?= $trackers[$lead['id']]['pagibig_bank_approval'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Approval
                                                    </span>
                                                    <span class="status-badge <?= $trackers[$lead['id']]['loan_takeout'] ? 'status-complete' : 'status-pending' ?>">
                                                        <i class="fas <?= $trackers[$lead['id']]['loan_takeout'] ? 'fa-check' : 'fa-clock' ?>"></i>
                                                        Takeout
                                                    </span>
                                                    <span class="status-badge <?= $trackers[$lead['id']]['turnover'] ? 'status-complete' : 'status-pending' ?>">
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
                                                <!-- View DP Button -->
                                                <button class="btn btn-success action-btn" onclick="openViewDpModal(<?= $lead['id'] ?>, '<?= htmlspecialchars($lead['client_name']) ?>', '<?= htmlspecialchars($lead['developer']) ?>', '<?= htmlspecialchars($lead['project_model']) ?>', <?= $lead['price'] ?? 0 ?>)">
                                                    <i class="fas fa-eye"></i> <span>View DP</span>
                                                </button>
                                                
                                                <?php if (!$show_completed): ?>
                                                <button class="btn btn-primary action-btn" onclick="openTrackerModal(<?= $lead['id'] ?>, '<?= htmlspecialchars($lead['client_name']) ?>', '<?= htmlspecialchars($lead['developer']) ?>', '<?= htmlspecialchars($lead['project_model']) ?>')">
                                                    <i class="fas fa-edit"></i> <span>Update</span>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <a href="lead-details.php?id=<?= $lead['id'] ?>" class="btn btn-outline action-btn">
                                                    <i class="fas fa-user"></i> <span>Profile</span>
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
    
    <!-- View DP Modal -->
    <div id="viewDpModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-chart-pie"></i> Downpayment Progress Overview</h3>
                <span class="close" onclick="closeViewDpModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Client Information Card -->
                <div class="client-info-card">
                    <div class="client-info-header">
                        <div class="client-info-main">
                            <div id="view_client_name" class="client-name-large"></div>
                            <div class="project-info">
                                <div class="project-detail">
                                    <i class="fas fa-building"></i>
                                    <span id="view_developer"></span>
                                </div>
                                <div class="project-detail">
                                    <i class="fas fa-home"></i>
                                    <span id="view_project_model"></span>
                                </div>
                            </div>
                        </div>
                        <div class="price-display" id="view_price">
                            <!-- Price will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- DP Terms Section -->
                <div class="dp-terms-section">
                    <div class="section-title">
                        <i class="fas fa-credit-card"></i>
                        Downpayment Terms
                    </div>
                    <div class="dp-terms-grid">
                        <div id="dp_terms_card" class="terms-card">
                            <!-- Will be populated by JavaScript -->
                        </div>
                        <div id="reservation_card" class="reservation-card">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Monthly Progress Section -->
                <div class="monthly-progress-section" id="monthly_progress_section" style="display: none;">
                    <div class="section-title">
                        <i class="fas fa-calendar-check"></i>
                        Monthly Payment Progress
                    </div>
                    <div class="monthly-progress-grid" id="monthly_progress_grid">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Overall Progress Section -->
                <div class="progress-section">
                    <div class="section-title">
                        <i class="fas fa-chart-line"></i>
                        Overall Progress
                    </div>
                    <div class="progress-overview-card">
                        <div class="progress-circle-container">
                            <div class="progress-circle" id="progress_circle">
                                <div class="progress-percentage" id="view_progress_percentage">0%</div>
                            </div>
                        </div>
                        <div class="progress-label">Project Completion</div>
                    </div>
                </div>

                <!-- Milestones Section -->
                <div class="milestones-section">
                    <div class="section-title">
                        <i class="fas fa-tasks"></i>
                        Project Milestones
                    </div>
                    <div class="milestones-list">
                        <div class="milestone-card" id="milestone_requirements">
                            <div class="milestone-content">
                                <div class="milestone-icon-container">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="milestone-info">
                                    <div class="milestone-title">Requirements Complete</div>
                                    <div class="milestone-description">All required documents submitted and verified by the processing team</div>
                                </div>
                                <div class="milestone-status-indicator">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>

                        <div class="milestone-card" id="milestone_dp_stage">
                            <div class="milestone-content">
                                <div class="milestone-icon-container">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="milestone-info">
                                    <div class="milestone-title">Downpayment Stage</div>
                                    <div class="milestone-description" id="dp_stage_description">Monthly payment progress tracking</div>
                                </div>
                                <div class="milestone-status-indicator">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>

                        <div class="milestone-card" id="milestone_approval">
                            <div class="milestone-content">
                                <div class="milestone-icon-container">
                                    <i class="fas fa-stamp"></i>
                                </div>
                                <div class="milestone-info">
                                    <div class="milestone-title">Pag-IBIG/Bank Approval</div>
                                    <div class="milestone-description">Loan application approved by financial institution</div>
                                </div>
                                <div class="milestone-status-indicator">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>

                        <div class="milestone-card" id="milestone_takeout">
                            <div class="milestone-content">
                                <div class="milestone-icon-container">
                                    <i class="fas fa-money-check-alt"></i>
                                </div>
                                <div class="milestone-info">
                                    <div class="milestone-title">Loan Takeout</div>
                                    <div class="milestone-description">Loan amount released and processed for property purchase</div>
                                </div>
                                <div class="milestone-status-indicator">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>

                        <div class="milestone-card" id="milestone_turnover">
                            <div class="milestone-content">
                                <div class="milestone-icon-container">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div class="milestone-info">
                                    <div class="milestone-title">Property Turnover</div>
                                    <div class="milestone-description">Property keys and documents handed over to client</div>
                                </div>
                                <div class="milestone-status-indicator">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeViewDpModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" onclick="openEditFromView()">
                    <i class="fas fa-edit"></i> Edit Details
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tracker Modal -->
    <?php if (!$show_completed): ?>
    <div id="trackerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-chart-line"></i> Update Downpayment Tracker</h3>
                <span class="close" onclick="closeTrackerModal()">&times;</span>
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
                        
                        <div class="form-check">
                            <input type="checkbox" id="spot_dp" name="spot_dp" class="form-check-input">
                            <label for="spot_dp">
                                <i class="fas fa-lightning-bolt"></i>
                                Spot Downpayment (Full payment upfront)
                            </label>
                        </div>
                    </div>
                    
                    <div id="terms_section" class="form-section">
                        <div class="form-group">
                            <label for="dp_terms">Downpayment Terms:</label>
                            <select id="dp_terms" name="dp_terms" required>
                                <option value="6">6 months</option>
                                <option value="9">9 months</option>
                                <option value="12" selected>12 months</option>
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
                    
                    <div class="form-section">
                        <label style="font-weight: 600; color: var(--gray-700); margin-bottom: 1rem; display: block;">
                            <i class="fas fa-tasks"></i> Project Milestones
                        </label>
                        
                        <div class="form-check">
                            <input type="checkbox" id="requirements_complete" name="requirements_complete" class="form-check-input">
                            <label for="requirements_complete">
                                <i class="fas fa-file-alt"></i>
                                Requirements Complete
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="pagibig_bank_approval" name="pagibig_bank_approval" class="form-check-input">
                            <label for="pagibig_bank_approval">
                                <i class="fas fa-stamp"></i>
                                Pag-IBIG/Bank Approval
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="loan_takeout" name="loan_takeout" class="form-check-input">
                            <label for="loan_takeout">
                                <i class="fas fa-money-check-alt"></i>
                                Loan Takeout
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="turnover" name="turnover" class="form-check-input">
                            <label for="turnover">
                                <i class="fas fa-key"></i>
                                Property Turnover
                            </label>
                        </div>
                    </div>
                    
                    <div class="info-message">
                        <i class="fas fa-info-circle"></i> 
                        Progress is automatically calculated based on completed milestones and current payment stage.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeTrackerModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    // Global variables
    let currentViewData = null;
    
    // Function to open the View DP modal
    function openViewDpModal(leadId, clientName, developer, projectModel, price) {
        // Set basic info
        document.getElementById('view_client_name').textContent = clientName;
        document.getElementById('view_developer').textContent = developer;
        document.getElementById('view_project_model').textContent = projectModel;
        
        if (price && price > 0) {
            document.getElementById('view_price').textContent = '₱' + parseFloat(price).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        } else {
            document.getElementById('view_price').textContent = 'Price not set';
        }
        
        // Store lead data for potential edit action
        currentViewData = {
            leadId: leadId,
            clientName: clientName,
            developer: developer,
            projectModel: projectModel,
            price: price
        };
        
        // Fetch and display tracker data
        fetchViewTrackerData(leadId);
        
        // Show the modal
        document.getElementById('viewDpModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    // Function to close the View DP modal
    function closeViewDpModal() {
        document.getElementById('viewDpModal').style.display = 'none';
        document.body.style.overflow = '';
        currentViewData = null;
    }
    
    // Function to fetch tracker data for view modal
    function fetchViewTrackerData(leadId) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'api/get-tracker.php?lead_id=' + leadId, true);
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.tracker) {
                        displayViewTrackerData(response.tracker);
                    } else {
                        displayEmptyViewTrackerData();
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    displayEmptyViewTrackerData();
                }
            } else {
                console.error('Server returned an error');
                displayEmptyViewTrackerData();
            }
        };
        
        xhr.onerror = function() {
            console.error('Connection error');
            displayEmptyViewTrackerData();
        };
        
        xhr.send();
    }
    
    // Function to display tracker data in view modal
    function displayViewTrackerData(tracker) {
        // Display DP Terms
        var termsCard = document.getElementById('dp_terms_card');
        var reservationCard = document.getElementById('reservation_card');
        var monthlyProgressSection = document.getElementById('monthly_progress_section');
        
        if (tracker.spot_dp == 1) {
            termsCard.className = 'terms-card spot-dp';
            termsCard.innerHTML = `
                <div class="terms-header">
                    <div class="terms-icon">
                        <i class="fas fa-lightning-bolt"></i>
                    </div>
                    <div class="terms-title">Spot Downpayment</div>
                </div>
                <div class="terms-details">
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Payment Type:</span>
                        <span class="terms-detail-value" style="color: #065f46;">Full Payment</span>
                    </div>
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Status:</span>
                        <span class="terms-detail-value" style="color: #065f46;">Completed</span>
                    </div>
                </div>
            `;
            monthlyProgressSection.style.display = 'none';
        } else {
            termsCard.className = 'terms-card installment';
            var progressPercentage = Math.round((tracker.current_dp_stage / tracker.total_dp_stages) * 100);
            termsCard.innerHTML = `
                <div class="terms-header">
                    <div class="terms-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="terms-title">Installment Plan</div>
                </div>
                <div class="terms-details">
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Payment Terms:</span>
                        <span class="terms-detail-value" style="color: var(--primary);">${tracker.dp_terms} months</span>
                    </div>
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Current Stage:</span>
                        <span class="terms-detail-value" style="color: var(--primary);">Month ${tracker.current_dp_stage} of ${tracker.total_dp_stages}</span>
                    </div>
                    <div class="terms-detail-item">
                        <span class="terms-detail-label">Progress:</span>
                        <span class="terms-detail-value" style="color: ${tracker.current_dp_stage === tracker.total_dp_stages ? 'var(--success)' : 'var(--warning)'};">${progressPercentage}%</span>
                    </div>
                </div>
            `;
            
            // Display monthly progress
            displayMonthlyProgress(tracker);
            monthlyProgressSection.style.display = 'block';
        }
        
        // Display reservation info
        if (tracker.reservation_date) {
            var reservationDate = new Date(tracker.reservation_date);
            reservationCard.innerHTML = `
                <div class="reservation-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="reservation-title">Reserved</div>
                <div class="reservation-date">
                    ${reservationDate.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })}
                </div>
            `;
        } else {
            reservationCard.innerHTML = `
                <div class="reservation-icon" style="background: var(--gray-300); color: var(--gray-600);">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="reservation-title" style="color: var(--gray-600);">No Reservation</div>
                <div class="reservation-date" style="color: var(--gray-500);">Date not set</div>
            `;
        }
        
        // Display overall progress
        var progress = parseFloat(tracker.progress_rate) || 0;
        var progressCircle = document.getElementById('progress_circle');
        var progressText = document.getElementById('view_progress_percentage');
        
        var progressAngle = (progress / 100) * 360;
        var progressClass = 'var(--danger)';
        if (progress >= 75) progressClass = 'var(--success)';
        else if (progress >= 50) progressClass = 'var(--warning)';
        else if (progress >= 25) progressClass = 'var(--primary)';
        
        progressCircle.style.setProperty('--progress-angle', progressAngle + 'deg');
        progressCircle.style.background = `conic-gradient(${progressClass} 0deg, ${progressClass} ${progressAngle}deg, var(--gray-200) ${progressAngle}deg)`;
        progressText.textContent = Math.round(progress) + '%';
        
        // Display milestones
        updateViewMilestoneStatus('milestone_requirements', tracker.requirements_complete == 1);
        
        // DP Stage milestone
        var dpStageCompleted = tracker.spot_dp == 1 || tracker.current_dp_stage == tracker.total_dp_stages;
        updateViewMilestoneStatus('milestone_dp_stage', dpStageCompleted);
        
        var dpStageDesc = document.getElementById('dp_stage_description');
        if (tracker.spot_dp == 1) {
            dpStageDesc.textContent = 'Spot downpayment completed successfully';
        } else {
            dpStageDesc.textContent = `Monthly payment progress: ${tracker.current_dp_stage} of ${tracker.total_dp_stages} months completed`;
        }
        
        updateViewMilestoneStatus('milestone_approval', tracker.pagibig_bank_approval == 1);
        updateViewMilestoneStatus('milestone_takeout', tracker.loan_takeout == 1);
        updateViewMilestoneStatus('milestone_turnover', tracker.turnover == 1);
    }
    
    // Function to display monthly progress
    function displayMonthlyProgress(tracker) {
        var grid = document.getElementById('monthly_progress_grid');
        grid.innerHTML = '';
        
        for (var i = 1; i <= tracker.dp_terms; i++) {
            var item = document.createElement('div');
            var isCompleted = i < tracker.current_dp_stage;
            var isCurrent = i == tracker.current_dp_stage;
            var isPending = i > tracker.current_dp_stage;
            
            var statusClass = 'pending';
            var statusText = 'Pending';
            
            if (isCompleted) {
                statusClass = 'completed';
                statusText = 'Paid';
            } else if (isCurrent) {
                statusClass = 'current';
                statusText = 'Current';
            }
            
            item.className = `monthly-progress-item ${statusClass}`;
            item.innerHTML = `
                <div class="month-number">Month ${i}</div>
                <div class="month-status">${statusText}</div>
            `;
            
            grid.appendChild(item);
        }
    }
    
    // Function to display empty tracker data for view modal
    function displayEmptyViewTrackerData() {
        var termsCard = document.getElementById('dp_terms_card');
        termsCard.className = 'terms-card';
        termsCard.innerHTML = `
            <div style="text-align: center; color: var(--gray-500); padding: 2rem;">
                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                <div style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">No Terms Set</div>
                <div style="font-size: 0.875rem;">Downpayment terms have not been configured yet</div>
            </div>
        `;
        
        var reservationCard = document.getElementById('reservation_card');
        reservationCard.innerHTML = `
            <div class="reservation-icon" style="background: var(--gray-300); color: var(--gray-600);">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="reservation-title" style="color: var(--gray-600);">No Reservation</div>
            <div class="reservation-date" style="color: var(--gray-500);">Date not set</div>
        `;
        
        // Hide monthly progress section
        document.getElementById('monthly_progress_section').style.display = 'none';
        
        // Reset progress
        var progressCircle = document.getElementById('progress_circle');
        var progressText = document.getElementById('view_progress_percentage');
        progressCircle.style.background = `conic-gradient(var(--gray-300) 0deg, var(--gray-300) 0deg, var(--gray-200) 0deg)`;
        progressText.textContent = '0%';
        
        // Reset all milestones to pending
        updateViewMilestoneStatus('milestone_requirements', false);
        updateViewMilestoneStatus('milestone_dp_stage', false);
        updateViewMilestoneStatus('milestone_approval', false);
        updateViewMilestoneStatus('milestone_takeout', false);
        updateViewMilestoneStatus('milestone_turnover', false);
        
        document.getElementById('dp_stage_description').textContent = 'Monthly payment progress tracking';
    }
    
    // Function to update milestone status in view modal
    function updateViewMilestoneStatus(milestoneId, isCompleted) {
        var milestone = document.getElementById(milestoneId);
        var statusIndicator = milestone.querySelector('.milestone-status-indicator i');
        
        if (isCompleted) {
            milestone.className = 'milestone-card completed';
            statusIndicator.className = 'fas fa-check';
        } else {
            milestone.className = 'milestone-card pending';
            statusIndicator.className = 'fas fa-clock';
        }
    }
    
    // Function to open edit modal from view modal
    function openEditFromView() {
        if (currentViewData) {
            closeViewDpModal();
            // Small delay to ensure view modal is closed before opening edit modal
            setTimeout(function() {
                openTrackerModal(
                    currentViewData.leadId,
                    currentViewData.clientName,
                    currentViewData.developer,
                    currentViewData.projectModel
                );
            }, 100);
        }
    }
    
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
        document.body.style.overflow = 'hidden';
    }
    
    // Function to close the tracker modal
    function closeTrackerModal() {
        document.getElementById('trackerModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    // Function to update DP stages dropdown based on selected terms
    function updateDpStages() {
        var terms = parseInt(document.getElementById('dp_terms').value);
        var currentStage = document.getElementById('current_dp_stage');
        var selectedValue = currentStage.value;
        
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
            currentStage.value = 1;
        }
    }
    
    // Function to fetch tracker data for edit modal
    function fetchTrackerData(leadId) {
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
    
    // Function to toggle terms section visibility
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
    
    // Event listeners
    document.getElementById('spot_dp').addEventListener('change', function() {
        toggleTermsSection();
        if (this.checked) {
            document.getElementById('dp_terms').value = '1';
            updateDpStages();
            document.getElementById('current_dp_stage').value = '1';
        } else {
            document.getElementById('dp_terms').value = '12';
            updateDpStages();
        }
    });
    
    document.getElementById('dp_terms').addEventListener('change', function() {
        updateDpStages();
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        var trackerModal = document.getElementById('trackerModal');
        var viewDpModal = document.getElementById('viewDpModal');
        
        if (event.target == trackerModal) {
            closeTrackerModal();
        }
        
        if (event.target == viewDpModal) {
            closeViewDpModal();
        }
    });
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Highlight active filters
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
        
        // Initialize DP stages dropdown
        updateDpStages();
    });
</script>   
</body>
</html>