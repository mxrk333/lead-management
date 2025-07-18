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

// Check if lead ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: leads.php");
    exit();
}

$lead_id = $_GET['id'];
$lead = getLeadById($lead_id, $user_id, $user['role']);

// Check if lead exists and user has permission to edit it
if (!$lead || $lead['user_id'] != $user_id) {
    header("Location: leads.php");
    exit();
}

// Get developers, project models, and lead sources for dropdowns - using the same functions as add-lead.php
$developers = getDevelopers();
$projectModels = getProjectModels();
$leadSources = getLeadSources();

// Get temperature and status options
$temperatures = ['Hot', 'Warm', 'Cold'];
$statuses = [
    'Inquiry', 'Presentation Stage', 'Negotiation', 'Closed', 'Lost', 
    'Site Tour', 'Closed Deal', 'Requirement Stage', 'Downpayment Stage', 
    'Housing Loan Application', 'Loan Approval', 'Loan Takeout', 
    'House Inspection', 'House Turn Over'
];

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDbConnection();
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get form data
        $client_name = trim($_POST['client_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $facebook = trim($_POST['facebook']);
        $linkedin = trim($_POST['linkedin']);
        $temperature = $_POST['temperature'];
        $status = $_POST['status'];
        $source = $_POST['source'];
        
        // Handle "Others" option for developer/project
        $developer = isset($_POST['developer']) ? trim($_POST['developer']) : '';
        if ($developer === 'Others' && isset($_POST['developer_other']) && !empty(trim($_POST['developer_other']))) {
            $developer = trim($_POST['developer_other']);
        }
        
        // Handle "Others" option for project model
        $project_model = isset($_POST['project_model']) ? trim($_POST['project_model']) : '';
        if ($project_model === 'Others' && isset($_POST['project_model_other']) && !empty(trim($_POST['project_model_other']))) {
            $project_model = trim($_POST['project_model_other']);
        }
        
        // Clean and convert price
        $price = str_replace(',', '', $_POST['price']);
        $price = floatval($price);
        $remarks = trim($_POST['remarks']);

        // Track changes
        $changes = array();
        if ($client_name !== $lead['client_name']) {
            $changes[] = array(
                'field' => 'client_name',
                'old_value' => $lead['client_name'],
                'new_value' => $client_name
            );
        }
        if ($facebook !== $lead['facebook']) {
            $changes[] = array(
                'field' => 'facebook',
                'old_value' => $lead['facebook'],
                'new_value' => $facebook
            );
        }
        if ($linkedin !== $lead['linkedin']) {
            $changes[] = array(
                'field' => 'linkedin',
                'old_value' => $lead['linkedin'],
                'new_value' => $linkedin
            );
        }
        if ($temperature !== $lead['temperature']) {
            $changes[] = array(
                'field' => 'temperature',
                'old_value' => $lead['temperature'],
                'new_value' => $temperature
            );
        }
        if ($status !== $lead['status']) {
            $changes[] = array(
                'field' => 'status',
                'old_value' => $lead['status'],
                'new_value' => $status
            );
        }
        if ($source !== $lead['source']) {
            $changes[] = array(
                'field' => 'source',
                'old_value' => $lead['source'],
                'new_value' => $source
            );
        }
        if ($developer !== $lead['developer']) {
            $changes[] = array(
                'field' => 'developer',
                'old_value' => $lead['developer'],
                'new_value' => $developer
            );
        }
        if ($project_model !== $lead['project_model']) {
            $changes[] = array(
                'field' => 'project_model',
                'old_value' => $lead['project_model'],
                'new_value' => $project_model
            );
        }
        if ($price !== floatval($lead['price'])) {
            $changes[] = array(
                'field' => 'price',
                'old_value' => $lead['price'],
                'new_value' => $price
            );
        }
        if ($remarks !== $lead['remarks']) {
            $changes[] = array(
                'field' => 'remarks',
                'old_value' => $lead['remarks'],
                'new_value' => $remarks
            );
        }

        // Update lead
        $update_stmt = $conn->prepare("
            UPDATE leads 
            SET client_name = ?, phone = ?, email = ?, facebook = ?, linkedin = ?,
                temperature = ?, status = ?, source = ?, developer = ?, project_model = ?,
                price = ?, remarks = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $update_stmt->bind_param(
            "ssssssssssdsi",
            $client_name, $phone, $email, $facebook, $linkedin,
            $temperature, $status, $source, $developer, $project_model,
            $price, $remarks, $lead_id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update lead");
        }

        // If there are changes, create an activity and record modifications
        if (!empty($changes)) {
            // Create activity entry
            $activity_notes = "Lead details updated:\n";
            foreach ($changes as $change) {
                $activity_notes .= "- Changed {$change['field']} from '{$change['old_value']}' to '{$change['new_value']}'\n";
            }

            $activity_stmt = $conn->prepare("
                INSERT INTO lead_activities (lead_id, user_id, activity_type, notes)
                VALUES (?, ?, 'Lead Update', ?)
            ");
            $activity_stmt->bind_param("iis", $lead_id, $user_id, $activity_notes);
            
            if (!$activity_stmt->execute()) {
                throw new Exception("Failed to create activity record");
            }
            $activity_id = $activity_stmt->insert_id;

            // Record each modification
            $mod_stmt = $conn->prepare("
                INSERT INTO lead_modifications 
                (lead_id, user_id, modification_type, old_value, new_value, activity_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($changes as $change) {
                $mod_type = $change['field'] . '_change';
                $mod_stmt->bind_param(
                    "iisssi",
                    $lead_id,
                    $user_id,
                    $mod_type,
                    $change['old_value'],
                    $change['new_value'],
                    $activity_id
                );
                
                if (!$mod_stmt->execute()) {
                    throw new Exception("Failed to record modification");
                }
            }
        }

        // Commit transaction
        $conn->commit();
        
        header("Location: lead-details.php?id=$lead_id&success=updated");
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = $e->getMessage();
    } finally {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lead - Inners SPARC Realty Corporation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base styles */
        :root {
            --container-padding: 25px;
        }

        @media (max-width: 768px) {
            :root {
                --container-padding: 15px;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .edit-lead-page {
                padding: var(--container-padding);
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .btn-back {
                width: 100%;
                justify-content: center;
            }
            
            .lead-form {
                border-radius: 0.75rem;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .form-row {
                margin: 0 -10px 1.25rem;
            }
            
            .form-group {
                padding: 0 10px;
                margin-bottom: 15px;
                min-width: 100%;
            }
        }

        @media (max-width: 576px) {
            .edit-lead-page {
                padding: var(--container-padding);
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .page-header h2::after {
                width: 2rem;
            }
            
            .form-section {
                padding: 15px;
            }
            
            .form-section h3 {
                font-size: 1.1rem;
                margin-bottom: 1.25rem;
            }
            
            .form-group label {
                font-size: 0.8rem;
                margin-bottom: 0.375rem;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 0.625rem 0.875rem;
                font-size: 0.8rem;
                border-radius: 0.375rem;
            }
            
            .form-group select {
                padding-right: 2rem;
                background-size: 0.875rem;
            }
            
            .form-actions {
                padding: 15px;
                flex-direction: column-reverse;
                gap: 10px;
            }
            
            .btn-save,
            .btn-cancel {
                width: 100%;
                padding: 0.625rem;
                font-size: 0.8rem;
            }
            
            .required-note {
                font-size: 0.7rem;
                margin-bottom: 0.75rem;
            }
            
            .optional-field {
                font-size: 0.7rem;
            }
            
            .success-message,
            .error-message {
                padding: 0.75rem;
                font-size: 0.8rem;
                margin-bottom: 1rem;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) {
            .btn-save:hover,
            .btn-cancel:hover,
            .btn-back:hover {
                transform: none;
                box-shadow: none;
            }
            
            .form-group input:focus,
            .form-group select:focus,
            .form-group textarea:focus {
                box-shadow: none;
            }
        }
        
        /* Base styles */
        body {
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background-color: #f9fafb;
        }
        
        /* Edit Lead page styles */
        .edit-lead-page {
            padding: 2rem;
            background-color: #f9fafb;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
            letter-spacing: -0.025em;
            position: relative;
            display: inline-block;
        }
        
        .page-header h2::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 0;
            width: 2.5rem;
            height: 0.25rem;
            background: linear-gradient(to right, #4f46e5, #8b5cf6);
            border-radius: 0.25rem;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 0.625rem 1rem;
            background-color: white;
            color: #4f46e5;
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-back:hover {
            background-color: rgba(79, 70, 229, 0.05);
            border-color: rgba(79, 70, 229, 0.3);
        }
        
        .btn-back i {
            margin-right: 0.5rem;
        }
        
        /* Form styles */
        .lead-form {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(229, 231, 235, 0.5);
            overflow: hidden;
        }
        
        .form-section {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .form-section:last-of-type {
            border-bottom: none;
        }
        
        .form-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin-top: 0;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .form-section h3::before {
            content: '';
            display: inline-block;
            width: 0.25rem;
            height: 1.25rem;
            background: linear-gradient(to bottom, #4f46e5, #8b5cf6);
            margin-right: 0.75rem;
            border-radius: 0.125rem;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap; 
            margin: 0 -0.75rem 1.5rem;
        }
        
        .form-row:last-child {
            margin-bottom: 0;
        }
        
        .form-group {
            flex: 1;
            min-width: 250px;
            padding: 0 0.75rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-group {
                flex: 0 0 100%;
            }
        }
        
        .form-group.full-width {
            flex: 0 0 100%;
        }
        
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        /* Required field indicator */
        .required-field label::after {
            content: ' *';
            color: #ef4444;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #1f2937;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #4f46e5;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Others input field styling */
        .others-input {
            margin-top: 0.75rem;
            display: none;
        }

        .others-input.show {
            display: block;
        }

        .others-input input {
            border-color: #4f46e5;
            background-color: #f8fafc;
        }

        .others-input label {
            font-size: 0.75rem;
            color: #4f46e5;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        /* Form actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            padding: 1.5rem 2rem;
            background-color: #f9fafb;
            border-top: 1px solid #f3f4f6;
        }
        
        .btn-save,
        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .btn-save {
            background-color: #4f46e5;
            color: white;
            border: none;
            margin-left: 0.75rem;
        }
        
        .btn-save:hover {
            background-color: #4338ca;
        }
        
        .btn-cancel {
            background-color: white;
            color: #6b7280;
            border: 1px solid #d1d5db;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            background-color: #f3f4f6;
        }
        
        /* Success and error messages */
        .success-message,
        .error-message {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .success-message {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        /* Required field indicator */
        .required-note {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .required-note span {
            color: #ef4444;
        }
        
        /* Source select styling */
        .source-select {
            max-height: 15rem;
            overflow-y: auto;
        }
        
        /* Optional field styling */
        .optional-field {
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: normal;
            margin-left: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="edit-lead-page">
                <div class="page-header">
                    <h2>Edit Lead</h2>
                    <a href="lead-details.php?id=<?php echo $lead_id; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Lead Details</a>
                </div>
                
                <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="required-note">Fields marked with <span>*</span> are required</div>
                
                <form method="POST" class="lead-form">
                    <div class="form-section">
                        <h3>Client Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="client_name">Client Name</label>
                                <input type="text" id="client_name" name="client_name" placeholder="Enter client's full name" value="<?php echo htmlspecialchars($lead['client_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="phone" name="phone" placeholder="e.g. +63 912 345 6789" value="<?php echo htmlspecialchars($lead['phone']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="optional-field">(Optional)</span></label>
                                <input type="email" id="email" name="email" placeholder="client@example.com" value="<?php echo htmlspecialchars($lead['email']); ?>">
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="source">Lead Source</label>
                                <select id="source" name="source" required class="source-select">
                                    <option value="">Select Lead Source</option>
                                    <?php foreach ($leadSources as $source): ?>
                                    <option value="<?php echo htmlspecialchars($source['name']); ?>" <?php echo ($source['name'] == $lead['source']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($source['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="facebook">Facebook Profile <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="facebook" name="facebook" placeholder="Facebook profile URL" value="<?php echo htmlspecialchars($lead['facebook']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="linkedin">LinkedIn Profile <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="linkedin" name="linkedin" placeholder="LinkedIn profile URL" value="<?php echo htmlspecialchars($lead['linkedin']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Lead Details</h3>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="temperature">Temperature</label>
                                <select id="temperature" name="temperature" required>
                                    <option value="">Select Temperature</option>
                                    <?php foreach ($temperatures as $temp): ?>
                                    <option value="<?php echo htmlspecialchars($temp); ?>" <?php echo ($temp == $lead['temperature']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($temp); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <?php foreach ($statuses as $stat): ?>
                                    <option value="<?php echo htmlspecialchars($stat); ?>" <?php echo ($stat == $lead['status']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stat); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="developer">Developer</label>
                                <select id="developer" name="developer" required onchange="loadProjectModels(this.value)">
                                    <option value="">Select Developer</option>
                                    <?php foreach ($developers as $dev): ?>
                                    <option value="<?php echo htmlspecialchars($dev['name']); ?>" <?php echo ($dev['name'] == $lead['developer']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dev['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <option value="Others" <?php echo ($lead['developer'] === 'Others' || !in_array($lead['developer'], array_column($developers, 'name'))) ? 'selected' : ''; ?>>Others</option>
                                </select>
                                <div class="others-input" id="developer-others">
                                    <label for="developer_other">Specify Project</label>
                                    <input type="text" id="developer_other" name="developer_other" 
                                           value="<?php echo (!in_array($lead['developer'], array_column($developers, 'name'))) ? htmlspecialchars($lead['developer']) : ''; ?>"
                                           placeholder="Enter project name" maxlength="100">
                                </div>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="project_model">Project Model</label>
                                <select id="project_model" name="project_model" required onchange="toggleProjectModelOthers(this.value)">
                                    <option value="">Select Project Model</option>
                                    <?php 
                                    $currentDeveloper = $lead['developer'];
                                    $modelFound = false;
                                    foreach ($projectModels as $model): 
                                        if ($model['developer_name'] == $currentDeveloper): 
                                            if ($model['name'] == $lead['project_model']) {
                                                $modelFound = true;
                                            }
                                    ?>
                                        <option value="<?php echo htmlspecialchars($model['name']); ?>" 
                                            <?php echo ($model['name'] == $lead['project_model']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($model['name']); ?>
                                        </option>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                    <option value="Others" <?php echo (!$modelFound && !empty($lead['project_model'])) ? 'selected' : ''; ?>>Others</option>
                                </select>
                                <div class="others-input" id="project-model-others">
                                    <label for="project_model_other">Specify House Model</label>
                                    <input type="text" id="project_model_other" name="project_model_other" 
                                           value="<?php echo (!$modelFound && !empty($lead['project_model'])) ? htmlspecialchars($lead['project_model']) : ''; ?>"
                                           placeholder="Enter house model name" maxlength="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="price">Total Selling Price (PHP)</label>
                                <input type="text" id="price" name="price" placeholder="e.g. 1,000,000.00" value="<?php echo number_format($lead['price'], 2); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="remarks">Remarks <span class="optional-field">(Optional)</span></label>
                                <textarea id="remarks" name="remarks" rows="4" placeholder="Add any additional notes or comments about this lead"><?php echo htmlspecialchars($lead['remarks']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="lead-details.php?id=<?php echo $lead_id; ?>" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Function to toggle developer others input
        function toggleDeveloperOthers(value) {
            const othersDiv = document.getElementById('developer-others');
            const othersInput = document.getElementById('developer_other');
            
            if (value === 'Others') {
                othersDiv.classList.add('show');
                othersInput.required = true;
            } else {
                othersDiv.classList.remove('show');
                othersInput.required = false;
                othersInput.value = '';
            }
        }

        // Function to toggle project model others input
        function toggleProjectModelOthers(value) {
            const othersDiv = document.getElementById('project-model-others');
            const othersInput = document.getElementById('project_model_other');
            
            if (value === 'Others') {
                othersDiv.classList.add('show');
                othersInput.required = true;
            } else {
                othersDiv.classList.remove('show');
                othersInput.required = false;
                othersInput.value = '';
            }
        }

        // Function to load project models based on selected developer
        function loadProjectModels(developer) {
            const projectModelSelect = document.getElementById('project_model');
            projectModelSelect.innerHTML = '<option value="">Select Project Model</option>';
            
            // Toggle developer others input
            toggleDeveloperOthers(developer);
            
            if (developer && developer !== 'Others') {
                // Get project models from PHP as JSON
                const projectModelsData = <?php 
                    $modelsArray = [];
                    foreach ($projectModels as $model) {
                        if (!isset($modelsArray[$model['developer_name']])) {
                            $modelsArray[$model['developer_name']] = [];
                        }
                        $modelsArray[$model['developer_name']][] = $model['name'];
                    }
                    echo json_encode($modelsArray);
                ?>;
                
                // Use the data from PHP or fallback to hardcoded values
                const models = projectModelsData[developer] || {
                    'Lancaster': ['Kennedy', 'Alexandra', 'Victoria', 'Elizabeth'],
                    'Antipolo Heights': ['Sierra', 'Montana', 'Alpine', 'Summit'],
                    'Pleasant Fields': ['Meadow', 'Garden', 'Park', 'Grove']
                }[developer] || [];
                
                models.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model;
                    option.textContent = model;
                    
                    // Check if this model is the currently selected one
                    if (model === '<?php echo addslashes($lead['project_model']); ?>') {
                        option.selected = true;
                    }
                    
                    projectModelSelect.appendChild(option);
                });

                // Add "Others" option
                const othersOption = document.createElement('option');
                othersOption.value = 'Others';
                othersOption.textContent = 'Others';
                
                // Check if current project model is not in the list (should be "Others")
                const currentModel = '<?php echo addslashes($lead['project_model']); ?>';
                if (currentModel && !models.includes(currentModel)) {
                    othersOption.selected = true;
                    toggleProjectModelOthers('Others');
                }
                
                projectModelSelect.appendChild(othersOption);
            } else if (developer === 'Others') {
                // Add "Others" option for custom developer
                const othersOption = document.createElement('option');
                othersOption.value = 'Others';
                othersOption.textContent = 'Others';
                othersOption.selected = true;
                projectModelSelect.appendChild(othersOption);
                toggleProjectModelOthers('Others');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var priceInput = document.getElementById('price');
            
            priceInput.addEventListener('input', function(e) {
                // Get the current value and remove all non-digits
                var value = this.value.replace(/\D/g, '');
                
                // Convert to number
                var number = parseInt(value);
                
                // If it's a valid number
                if (!isNaN(number)) {
                    // Convert to string and add decimals
                    var withDecimals = (number / 100).toFixed(2);
                    
                    // Add commas for thousands
                    var parts = withDecimals.toString().split('.');
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    
                    // Update the input value
                    this.value = parts.join('.');
                } else {
                    this.value = '';
                }
            });
            
            // Handle form submission
            document.querySelector('form').addEventListener('submit', function(e) {
                e.preventDefault();
                var price = priceInput.value.replace(/,/g, '');
                priceInput.value = price;
                this.submit();
            });

            // Initialize the form based on current values
            const currentDeveloper = '<?php echo addslashes($lead['developer']); ?>';
            const availableDevelopers = <?php echo json_encode(array_column($developers, 'name')); ?>;
            
            // Check if current developer is in the list
            if (currentDeveloper && !availableDevelopers.includes(currentDeveloper)) {
                // Current developer is not in the list, so it should be "Others"
                document.getElementById('developer').value = 'Others';
                toggleDeveloperOthers('Others');
            }

            // Initialize project models for the current developer
            if (currentDeveloper) {
                loadProjectModels(currentDeveloper);
            }

            // Add event listeners
            document.getElementById('developer').addEventListener('change', function() {
                loadProjectModels(this.value);
            });

            document.getElementById('project_model').addEventListener('change', function() {
                toggleProjectModelOthers(this.value);
            });
        });
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
