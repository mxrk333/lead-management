<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Add superuser function if not exists
if (!function_exists('isSuperUser')) {
    function isSuperUser($username) {
        $superusers = [
            'markpatigayon.innersparc',
            'gabriellibacao.founder', 
            'romeocorberta.itdept'
        ];
        return in_array($username, $superusers);
    }
}

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
if (!$lead) {
    header("Location: leads.php");
    exit();
}

// Enhanced permission check including superuser access
$canEdit = ($lead['user_id'] == $user_id) || isSuperUser($user['username']);

if (!$canEdit) {
    header("Location: leads.php");
    exit();
}

// Get developers, project models, and lead sources for dropdowns
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
$success = '';

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
        $developer = trim($_POST['developer']);
        $project_model = trim($_POST['project_model']);
        // Clean and convert price
        $price = str_replace(',', '', $_POST['price']);
        $price = floatval($price);
        $remarks = trim($_POST['remarks']);

        // Validate required fields
        if (empty($client_name) || empty($phone) || empty($email) || empty($temperature) || 
            empty($status) || empty($source) || empty($developer) || empty($project_model) || $price <= 0) {
            throw new Exception("Please fill in all required fields.");
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Track changes
        $changes = array();
        if ($client_name !== $lead['client_name']) {
            $changes[] = array(
                'field' => 'client_name',
                'old_value' => $lead['client_name'],
                'new_value' => $client_name
            );
        }
        if ($phone !== $lead['phone']) {
            $changes[] = array(
                'field' => 'phone',
                'old_value' => $lead['phone'],
                'new_value' => $phone
            );
        }
        if ($email !== $lead['email']) {
            $changes[] = array(
                'field' => 'email',
                'old_value' => $lead['email'],
                'new_value' => $email
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
        if (isset($conn)) {
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lead - Real Estate Lead Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base styles */
        :root {
            --container-padding: 25px;
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
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
        }

        @media (max-width: 768px) {
            :root {
                --container-padding: 15px;
            }
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--gray-800);
            background-color: var(--gray-50);
            margin: 0;
        }

        .edit-lead-page {
            padding: 2rem;
            background-color: var(--gray-50);
            min-height: 100vh;
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
            color: var(--gray-900);
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
            background: linear-gradient(to right, var(--primary-color), #8b5cf6);
            border-radius: 0.25rem;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 0.625rem 1rem;
            background-color: white;
            color: var(--primary-color);
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

        .lead-form {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(229, 231, 235, 0.5);
            overflow: hidden;
        }

        .form-section {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .form-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
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
            background: linear-gradient(to bottom, var(--primary-color), #8b5cf6);
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
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .required-field label::after {
            content: ' *';
            color: var(--danger-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--gray-800);
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
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

        .form-actions {
            display: flex;
            justify-content: flex-end;
            padding: 1.5rem 2rem;
            background-color: var(--gray-50);
            border-top: 1px solid var(--gray-100);
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
            text-decoration: none;
        }

        .btn-save {
            background-color: var(--primary-color);
            color: white;
            border: none;
            margin-left: 0.75rem;
        }

        .btn-save:hover {
            background-color: var(--primary-hover);
        }

        .btn-cancel {
            background-color: white;
            color: var(--gray-500);
            border: 1px solid var(--gray-300);
        }

        .btn-cancel:hover {
            background-color: var(--gray-100);
        }

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
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .required-note {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-bottom: 1rem;
        }

        .required-note span {
            color: var(--danger-color);
        }

        .optional-field {
            color: var(--gray-500);
            font-size: 0.75rem;
            font-weight: normal;
            margin-left: 0.25rem;
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
            .page-header h2 {
                font-size: 1.5rem;
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
                margin-left: 0;
            }
        }

        /* Loading state */
        .btn-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-save:disabled:hover {
            background-color: var(--primary-color);
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
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <div class="required-note">Fields marked with <span>*</span> are required</div>
                
                <form method="POST" class="lead-form" id="editLeadForm">
                    <div class="form-section">
                        <h3>Client Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="client_name">Client Name</label>
                                <input type="text" id="client_name" name="client_name" placeholder="Enter client's full name" value="<?php echo htmlspecialchars($lead['client_name']); ?>" required>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" placeholder="e.g. +63 912 345 6789" value="<?php echo htmlspecialchars($lead['phone']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="client@example.com" value="<?php echo htmlspecialchars($lead['email']); ?>" required>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="source">Lead Source</label>
                                <select id="source" name="source" required>
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
                                <input type="url" id="facebook" name="facebook" placeholder="https://facebook.com/profile" value="<?php echo htmlspecialchars($lead['facebook']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="linkedin">LinkedIn Profile <span class="optional-field">(Optional)</span></label>
                                <input type="url" id="linkedin" name="linkedin" placeholder="https://linkedin.com/in/profile" value="<?php echo htmlspecialchars($lead['linkedin']); ?>">
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
                                </select>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="project_model">Project Model</label>
                                <select id="project_model" name="project_model" required>
                                    <option value="">Select Project Model</option>
                                    <?php foreach ($projectModels as $model): ?>
                                        <?php if ($model['developer_name'] == $lead['developer']): ?>
                                            <option value="<?php echo htmlspecialchars($model['name']); ?>" 
                                                <?php echo ($model['name'] == $lead['project_model']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($model['name']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
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
                        <button type="submit" class="btn-save" id="saveBtn"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Function to load project models based on selected developer
        function loadProjectModels(developer) {
            const projectModelSelect = document.getElementById('project_model');
            projectModelSelect.innerHTML = '<option value="">Select Project Model</option>';
            
            if (developer) {
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
                
                const models = projectModelsData[developer] || [];
                
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
            }
        }

        // Price formatting
        document.addEventListener('DOMContentLoaded', function() {
            var priceInput = document.getElementById('price');
            
            priceInput.addEventListener('input', function(e) {
                // Get the current value and remove all non-digits and decimal points
                var value = this.value.replace(/[^\d.]/g, '');
                
                // Split by decimal point
                var parts = value.split('.');
                
                // Format the integer part with commas
                if (parts[0]) {
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                
                // Limit decimal places to 2
                if (parts[1]) {
                    parts[1] = parts[1].substring(0, 2);
                }
                
                // Rejoin the parts
                this.value = parts.join('.');
            });
            
            // Form validation and submission
            document.getElementById('editLeadForm').addEventListener('submit', function(e) {
                const saveBtn = document.getElementById('saveBtn');
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;
                
                // Check required fields
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = 'var(--danger-color)';
                    } else {
                        field.style.borderColor = 'var(--gray-300)';
                    }
                });
                
                // Validate email format
                const emailField = document.getElementById('email');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailField.value && !emailRegex.test(emailField.value)) {
                    isValid = false;
                    emailField.style.borderColor = 'var(--danger-color)';
                    alert('Please enter a valid email address.');
                }
                
                // Validate price
                const priceField = document.getElementById('price');
                const priceValue = parseFloat(priceField.value.replace(/,/g, ''));
                if (isNaN(priceValue) || priceValue <= 0) {
                    isValid = false;
                    priceField.style.borderColor = 'var(--danger-color)';
                    alert('Please enter a valid price.');
                }
                
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                
                // Clean price value before submission
                priceField.value = priceField.value.replace(/,/g, '');
            });
            
            // Auto-hide messages after 5 seconds
            setTimeout(function() {
                const messages = document.querySelectorAll('.success-message, .error-message');
                messages.forEach(function(message) {
                    message.style.opacity = '0';
                    setTimeout(() => message.style.display = 'none', 300);
                });
            }, 5000);
        });
    </script>

    <script src="assets/js/script.js"></script>
</body>
</html>
