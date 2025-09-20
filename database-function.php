<?php

// Function to get tracker data with proper date and boolean handling
function getTrackerData($lead_id, $connection) {
    $sql = "SELECT 
                reservation_date,
                requirements_complete,
                pagibig_bank_approval,
                loan_takeout,
                turnover,
                spot_dp,
                dp_terms,
                current_dp_stage,
                total_dp_stages,
                progress_rate
            FROM tracker_table 
            WHERE lead_id = ?";
    
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $lead_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $row['requirements_complete'] = (int)$row['requirements_complete'];
        $row['pagibig_bank_approval'] = (int)$row['pagibig_bank_approval'];
        $row['loan_takeout'] = (int)$row['loan_takeout'];
        $row['turnover'] = (int)$row['turnover'];
        $row['spot_dp'] = (int)$row['spot_dp'];
        
        if ($row['reservation_date'] && $row['reservation_date'] !== '0000-00-00') {
            $row['reservation_date'] = date('Y-m-d', strtotime($row['reservation_date']));
        } else {
            $row['reservation_date'] = null;
        }
        
        return $row;
    }
    
    return null;
}

// Function to update tracker data
function updateTrackerData($lead_id, $data, $connection) {
    $sql = "UPDATE tracker_table SET 
                reservation_date = ?,
                requirements_complete = ?,
                pagibig_bank_approval = ?,
                loan_takeout = ?,
                turnover = ?,
                spot_dp = ?,
                dp_terms = ?,
                current_dp_stage = ?
            WHERE lead_id = ?";
    
    $stmt = mysqli_prepare($connection, $sql);
    
    $reservation_date = !empty($data['reservation_date']) ? $data['reservation_date'] : null;
    $requirements_complete = isset($data['requirements_complete']) ? 1 : 0;
    $pagibig_bank_approval = isset($data['pagibig_bank_approval']) ? 1 : 0;
    $loan_takeout = isset($data['loan_takeout']) ? 1 : 0;
    $turnover = isset($data['turnover']) ? 1 : 0;
    $spot_dp = isset($data['spot_dp']) ? 1 : 0;
    $dp_terms = (int)$data['dp_terms'];
    $current_dp_stage = (int)$data['current_dp_stage'];
    
    mysqli_stmt_bind_param($stmt, "siiiiiii", 
        $reservation_date,
        $requirements_complete,
        $pagibig_bank_approval,
        $loan_takeout,
        $turnover,
        $spot_dp,
        $dp_terms,
        $current_dp_stage,
        $lead_id
    );
    
    return mysqli_stmt_execute($stmt);
}

// AJAX endpoint to get tracker data
if (isset($_GET['action']) && $_GET['action'] === 'get_tracker_data') {
    $lead_id = (int)$_GET['lead_id'];
    $tracker_data = getTrackerData($lead_id, $connection);
    
    header('Content-Type: application/json');
    echo json_encode($tracker_data);
    exit;
}

// AJAX endpoint to update tracker data
if (isset($_POST['action']) && $_POST['action'] === 'update_tracker_data') {
    $lead_id = (int)$_POST['lead_id'];
    $success = updateTrackerData($lead_id, $_POST, $connection);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
    exit;
}
?>
