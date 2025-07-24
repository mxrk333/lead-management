<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Function to check if user is superuser
function isSuperUser($username) {
    $superusers = [
        'markpatigayon.intern',
        'gabriellibacao.founder', 
        'romeocorberta.itdept'
    ];
    return in_array($username, $superusers);
}

// Check if lead ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: leads.php?error=invalid_lead");
    exit();
}

$lead_id = intval($_GET['id']);

// Get lead information - FIXED: Now passing all required parameters
$lead = getLeadById($lead_id, $user_id, $user['role']);
if (!$lead) {
    header("Location: leads.php?error=lead_not_found");
    exit();
}

// Check if user can edit this lead
$canEdit = isSuperUser($user['username']) || ($lead['user_id'] == $user_id);
if (!$canEdit) {
    header("Location: leads.php?error=access_denied");
    exit();
}

// Get dropdown data
$developers = getDevelopers();
$projectModels = getProjectModels();

// Enhanced getLeadSources function with "Others" option
function getLeadSourcesWithOthers() {
    try {
        $conn = getDbConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $sources = [];
        
        // Get ENUM values directly from the column
        $stmt = $conn->prepare("SHOW COLUMNS FROM leads WHERE Field = 'source'");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // Parse ENUM values from the type definition
        if ($row && preg_match("/^enum\$$'(.*)'\$$$/", $row['Type'], $matches)) {
            $values = explode("','", $matches[1]);
            foreach ($values as $value) {
                $sources[] = [
                    'id' => $value,
                    'name' => $value
                ];
            }
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $sources = [];
    }
    
    // If no sources found from database, provide default values
    if (empty($sources)) {
        $defaultSources = [
            'Facebook Groups', 'KKK', 'Facebook Ads', 'TikTok ads', 'Google Ads', 
            'Facebook live', 'Referral', 'Teleprospecting', 'Video Message', 
            'Organic Posting', 'Email Marketing', 'Follow up', 'Manning', 
            'Walk in', 'Flyering', 'Chat messaging', 'Property Listing', 
            'Landing Page', 'Networking Events', 'Organic Sharing', 
            'Youtube Marketing', 'LinkedIn', 'Open House', 'Facebook Page'
        ];
        
        foreach ($defaultSources as $source) {
            $sources[] = [
                'id' => $source,
                'name' => $source
            ];
        }
    }
    
    // Always add "Others" option at the end
    $sources[] = [
        'id' => 'Others',
        'name' => 'Others'
    ];
    
    return $sources;
}

$leadSources = getLeadSourcesWithOthers();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Collect and sanitize form data
        $clientName = trim($_POST['client_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $facebook = trim($_POST['facebook']);
        $linkedin = trim($_POST['linkedin']);
        $temperature = trim($_POST['temperature']);
        $status = trim($_POST['status']);
        
        // Handle "Others" option for developer/project
        $developer = trim($_POST['developer']);
        if ($developer === 'Others' && isset($_POST['developer_other']) && !empty(trim($_POST['developer_other']))) {
            $developer = trim($_POST['developer_other']);
        }
        
        // Handle "Others" option for project model
        $projectModel = trim($_POST['project_model']);
        if ($projectModel === 'Others' && isset($_POST['project_model_other']) && !empty(trim($_POST['project_model_other']))) {
            $projectModel = trim($_POST['project_model_other']);
        }
        
        $priceRaw = trim($_POST['price']);
        $remarks = trim($_POST['remarks']);
        
        // Handle "Others" option for lead source
        $source = trim($_POST['source']);
        if ($source === 'Others' && isset($_POST['source_other']) && !empty(trim($_POST['source_other']))) {
            $source = trim($_POST['source_other']);
        }
        
        // Clean and convert price
        $price = str_replace([',', ' '], '', $priceRaw);
        $price = floatval($price);
        
        // Validation
        $errors = [];
        
        if (empty($clientName)) {
            $errors[] = "Client name is required";
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address format";
        }
        
        if (empty($temperature)) {
            $errors[] = "Temperature is required";
        }
        
        if (empty($status)) {
            $errors[] = "Status is required";
        }
        
        if (empty($developer)) {
            $errors[] = "Project is required";
        }
        
        if (empty($projectModel)) {
            $errors[] = "Project model is required";
        }
        
        if ($price <= 0) {
            $errors[] = "Valid price is required";
        }
        
        if (empty($source)) {
            $errors[] = "Lead source is required";
        }
        
        if (empty($errors)) {
            // Update lead in database
            $result = updateLead(
                $lead_id, $clientName, $phone, $email, $facebook, $linkedin, 
                $temperature, $status, $source, $developer, $projectModel, $price, $remarks
            );
            
            if ($result) {
                $_SESSION['success_message'] = "Lead updated successfully";
                header("Location: leads.php");
                exit();
            } else {
                $error = "Failed to update lead";
            }
        } else {
            $error = implode(", ", $errors);
        }
        
    } catch (Exception $e) {
        $error = "Failed to update lead: " . $e->getMessage();
    }
}

// Check if current source is not in the predefined list (custom source)
$isCustomSource = true;
$currentSourceValue = 'Others'; // Default to Others for custom sources

foreach ($leadSources as $sourceOption) {
    if ($sourceOption['name'] === $lead['source']) {
        $isCustomSource = false;
        $currentSourceValue = $lead['source'];
        break;
    }
}

// If it's a custom source, we need to set up the form to show "Others" selected
// and populate the custom input field
if ($isCustomSource) {
    $customSourceValue = $lead['source'];
} else {
    $customSourceValue = '';
}

// Check if current developer is custom
$isCustomDeveloper = true;
foreach ($developers as $dev) {
    if ($dev['name'] === $lead['developer']) {
        $isCustomDeveloper = false;
        break;
    }
}

// Check if current project model is custom
$isCustomProjectModel = true;
foreach ($projectModels as $model) {
    if ($model['name'] === $lead['project_model'] && $model['developer_name'] === $lead['developer']) {
        $isCustomProjectModel = false;
        break;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background-color: #f9fafb;
        }
        
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
            background: linear-gradient(to right, #f59e0b, #d97706);
            border-radius: 0.25rem;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 0.625rem 1rem;
            background-color: white;
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-back:hover {
            background-color: rgba(245, 158, 11, 0.05);
            border-color: rgba(245, 158, 11, 0.3);
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
            background: linear-gradient(to bottom, #f59e0b, #d97706);
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
            border-color: #f59e0b;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
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

        .others-input {
            margin-top: 0.75rem;
            display: none;
        }

        .others-input.show {
            display: block;
        }

        .others-input input {
            border-color: #f59e0b;
            background-color: #fef3c7;
        }

        .others-input label {
            font-size: 0.75rem;
            color: #f59e0b;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
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
            background-color: #f59e0b;
            color: white;
            border: none;
            margin-left: 0.75rem;
        }
        
        .btn-save:hover {
            background-color: #d97706;
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
        
        .required-note {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .required-note span {
            color: #ef4444;
        }
        
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
                    <a href="javascript:history.back()" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <div class="required-note">Fields marked with <span>*</span> are required</div>
                
                <form method="POST" action="" class="lead-form" id="editLeadForm">
                    <div class="form-section">
                        <h3>Client Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="client_name">Client Name</label>
                                <input type="text" id="client_name" name="client_name" 
                                       value="<?php echo htmlspecialchars($lead['client_name']); ?>"
                                       placeholder="Enter client's full name" maxlength="100" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($lead['phone']); ?>"
                                       placeholder="e.g. 09123456789" maxlength="11">
                            </div>      
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="optional-field">(Optional)</span></label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($lead['email']); ?>"
                                       placeholder="client@example.com" maxlength="100">
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="source">Lead Source</label>
                                <select id="source" name="source" required onchange="toggleSourceOthers(this.value)">
                                    <option value="">Select Lead Source</option>
                                    <?php foreach ($leadSources as $source): ?>
                                    <option value="<?php echo htmlspecialchars($source['name']); ?>"
                                            <?php echo ($currentSourceValue === $source['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($source['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="facebook">Facebook Profile <span class="optional-field">(Optional)</span></label>
                                <input type="url" id="facebook" name="facebook" 
                                       value="<?php echo htmlspecialchars($lead['facebook']); ?>"
                                       placeholder="Facebook profile URL" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label for="linkedin">LinkedIn Profile <span class="optional-field">(Optional)</span></label>
                                <input type="url" id="linkedin" name="linkedin" 
                                       value="<?php echo htmlspecialchars($lead['linkedin']); ?>"
                                       placeholder="LinkedIn profile URL" maxlength="255">
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
                                    <option value="Hot" <?php echo ($lead['temperature'] === 'Hot') ? 'selected' : ''; ?>>Hot</option>
                                    <option value="Warm" <?php echo ($lead['temperature'] === 'Warm') ? 'selected' : ''; ?>>Warm</option>
                                    <option value="Cold" <?php echo ($lead['temperature'] === 'Cold') ? 'selected' : ''; ?>>Cold</option>
                                </select>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <?php 
                                    $statuses = [
                                        'Inquiry', 'Presentation Stage', 'Negotiation', 'Lost', 'Site Tour',
                                         'Requirement Stage', 'Downpayment Stage', 'Housing Loan Application',
                                        'Loan Approval', 'Loan Takeout', 'House Inspection', 'House Turn Over', 'Closed Deal'
                                    ];
                                    foreach ($statuses as $status_option): ?>
                                    <option value="<?php echo htmlspecialchars($status_option); ?>"
                                            <?php echo ($lead['status'] === $status_option) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($status_option); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="developer">Project</label>
                                <select id="developer" name="developer" required onchange="loadProjectModels(this.value)">
                                    <option value="">Select Project</option>
                                    <?php foreach ($developers as $dev): ?>
                                    <option value="<?php echo htmlspecialchars($dev['name']); ?>"
                                            <?php echo ($lead['developer'] === $dev['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dev['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <option value="Others" <?php echo $isCustomDeveloper ? 'selected' : ''; ?>>Others</option>
                                </select>
                                <div class="others-input <?php echo $isCustomDeveloper ? 'show' : ''; ?>" id="developer-others">
                                    <label for="developer_other">Specify Project</label>
                                    <input type="text" id="developer_other" name="developer_other" 
                                           value="<?php echo $isCustomDeveloper ? htmlspecialchars($lead['developer']) : ''; ?>"
                                           placeholder="Enter project name" maxlength="100"
                                           <?php echo $isCustomDeveloper ? 'required' : ''; ?>>
                                </div>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="project_model">House Model</label>
                                <select id="project_model" name="project_model" required onchange="toggleProjectModelOthers(this.value)">
                                    <option value="">Select House Model</option>
                                </select>
                                <div class="others-input" id="project-model-others">
                                    <label for="project_model_other">Specify House Model</label>
                                    <input type="text" id="project_model_other" name="project_model_other" 
                                           value=""
                                           placeholder="Enter house model name" maxlength="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="price">Total Selling Price (PHP)</label>
                                <input type="text" id="price" name="price" 
                                       value="<?php echo number_format($lead['price'], 2); ?>"
                                       placeholder="e.g. 1,000,000.00" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="remarks">Remarks <span class="optional-field">(Optional)</span></label>
                                <textarea id="remarks" name="remarks" rows="4" maxlength="1000"
                                          placeholder="Add any additional notes or comments about this lead"><?php echo htmlspecialchars($lead['remarks']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="javascript:history.back()" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="fas fa-save"></i> Update Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Project models data from PHP
        let projectModelsData = {};
        try {
            projectModelsData = <?php 
                $modelsArray = [];
                foreach ($projectModels as $model) {
                    if (!isset($modelsArray[$model['developer_name']])) {
                        $modelsArray[$model['developer_name']] = [];
                    }
                    $modelsArray[$model['developer_name']][] = $model['name'];
                }
                echo json_encode($modelsArray, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            ?>;
        } catch (error) {
            console.error('Error loading project models data:', error);
            projectModelsData = {};
        }
        
        // Current lead data
        const currentLead = {
            developer: '<?php echo htmlspecialchars($lead['developer']); ?>',
            project_model: '<?php echo htmlspecialchars($lead['project_model']); ?>',
            source: '<?php echo htmlspecialchars($lead['source']); ?>'
        };
        
        function toggleSourceOthers(value) {
            const othersDiv = document.getElementById('source-others');
            const othersInput = document.getElementById('source_other');
            
            if (value === 'Others') {
                othersDiv.classList.add('show');
                othersInput.required = true;
            } else {
                othersDiv.classList.remove('show');
                othersInput.required = false;
                othersInput.value = '';
            }
        }
        
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
        
        function loadProjectModels(developer) {
            const projectModelSelect = document.getElementById('project_model');
            if (!projectModelSelect) return;
            
            // Clear existing options
            projectModelSelect.innerHTML = '<option value="">Select House Model</option>';
            
            // Toggle developer others input
            toggleDeveloperOthers(developer);
            
            if (developer && developer !== 'Others') {
                const models = projectModelsData[developer] || [];
                
                // Check if current project model exists in the list
                let isCustomModel = true;
                
                // Add model options
                models.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model;
                    option.textContent = model;
                    if (model === currentLead.project_model) {
                        option.selected = true;
                        isCustomModel = false;
                    }
                    projectModelSelect.appendChild(option);
                });
                
                // Add "Others" option
                const othersOption = document.createElement('option');
                othersOption.value = 'Others';
                othersOption.textContent = 'Others';
                if (isCustomModel && currentLead.project_model) {
                    othersOption.selected = true;
                    toggleProjectModelOthers('Others');
                    document.getElementById('project_model_other').value = currentLead.project_model;
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
                document.getElementById('project_model_other').value = currentLead.project_model;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize project models if developer is already selected
            const developerSelect = document.getElementById('developer');
            if (developerSelect && developerSelect.value) {
                loadProjectModels(developerSelect.value);
            }
            
            // Initialize source others if needed
            const sourceSelect = document.getElementById('source');
            const isCustomSource = <?php echo $isCustomSource ? 'true' : 'false'; ?>;

            if (sourceSelect && isCustomSource) {
                // Set dropdown to "Others" and show the custom input
                sourceSelect.value = 'Others';
                toggleSourceOthers('Others');
                
                // Set the custom value
                const customInput = document.getElementById('source_other');
                if (customInput) {
                    customInput.value = '<?php echo htmlspecialchars($customSourceValue); ?>';
                }
            } else if (sourceSelect && sourceSelect.value === 'Others') {
                toggleSourceOthers('Others');
            }
            
            // Price formatting
            const priceInput = document.getElementById('price');
            if (priceInput) {
                priceInput.addEventListener('input', function(e) {
                    let value = this.value.replace(/[^\d.]/g, '');
                    
                    const parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    
                    if (parts[1] && parts[1].length > 2) {
                        value = parts[0] + '.' + parts[1].substring(0, 2);
                    }
                    
                    if (value) {
                        const numParts = value.split('.');
                        numParts[0] = numParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        this.value = numParts.join('.');
                    }
                });
            }
            
            // Phone number validation
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/\D/g, '');
                    if (this.value.length > 11) {
                        this.value = this.value.substring(0, 11);
                    }
                });
            }
            
            // Form submission handling
            const form = document.getElementById('editLeadForm');
            const saveBtn = document.getElementById('saveBtn');
            
            if (form && saveBtn) {
                form.addEventListener('submit', function(e) {
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                    saveBtn.disabled = true;
                    
                    // Clean price value for submission
                    if (priceInput) {
                        const price = priceInput.value.replace(/,/g, '');
                        priceInput.value = price;
                    }
                });
            }
        });
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
