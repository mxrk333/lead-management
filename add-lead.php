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

// Get developers, project models, and lead sources for dropdowns
$developers = getDevelopers();
$projectModels = getProjectModels();
$leadSources = getLeadSources();

$success = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect form data
    $clientName = $_POST['client_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $facebook = isset($_POST['facebook']) ? $_POST['facebook'] : '';
    $linkedin = isset($_POST['linkedin']) ? $_POST['linkedin'] : '';
    $temperature = $_POST['temperature'];
    $status = $_POST['status'];
    $developer = $_POST['developer'];
    $projectModel = $_POST['project_model'];
    // Clean and convert price
    $price = str_replace(',', '', $_POST['price']);
    $price = floatval($price);
    $remarks = $_POST['remarks'];
    $source = !empty($_POST['source']) ? $_POST['source'] : null;
    
    // Validate required fields
    if (empty($clientName) || empty($phone) || empty($email) || empty($temperature) || 
        empty($status) || empty($developer) || empty($projectModel) || empty($price) || empty($source)) {
        $error = "Please fill in all required fields";
    } else {
        // Add lead to database
        $result = addLead($user_id, $clientName, $phone, $email, $facebook, $linkedin, 
                          $temperature, $status, $source, $developer, $projectModel, $price, $remarks);
        
        if ($result) {
            $success = "Lead added successfully";
            // Redirect after short delay
            header("refresh:2;url=leads.php");
        } else {
            $error = "Failed to add lead. Please try again.";
        }
    }
}

function getLeadSources() {
    // Get all possible values from the source ENUM
    $conn = getDbConnection();
    $sources = [];
    
    // Get ENUM values directly from the column
    $stmt = $conn->prepare("SHOW COLUMNS FROM leads WHERE Field = 'source'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Parse ENUM values from the type definition
    if ($row && preg_match("/^enum$$'(.*)'\$$$/", $row['Type'], $matches)) {
        $values = explode("','", $matches[1]);
        foreach ($values as $value) {
            $sources[] = [
                'id' => $value,
                'name' => $value
            ];
        }
    }
    
    // If no sources found from database, provide default values based on the schema
    if (empty($sources)) {
        $defaultSources = [
            'Facebook Groups', 'KKK', 'Facebook Ads', 'TikTok ads', 'Google Ads', 
            'Facebook live', 'Referral', 'Teleprospecting', 'Video Message', 
            'Organic Posting', 'Email Marketing', 'Follow up', 'Manning', 
            'Walk in', 'Flyering', 'Chat messaging', 'Property Listing', 
            'Landing Page', 'Networking Events', 'Organic Sharing', 
            'Youtube Marketing', 'LinkedIn', 'Open House'
        ];
        
        foreach ($defaultSources as $source) {
            $sources[] = [
                'id' => $source,
                'name' => $source
            ];
        }
    }
    
    $stmt->close();
    $conn->close();
    return $sources;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Lead - Inners SPARC Realty Corporation</title>
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
            .add-lead-page {
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
            .add-lead-page {
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
        
        /* Add Lead page styles */
        .add-lead-page {
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
            
            <div class="add-lead-page">
                <div class="page-header">
                    <h2>Add New Lead</h2>
                    <a href="leads.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Leads</a>
                </div>
                
                <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="required-note">Fields marked with <span>*</span> are required</div>
                
                <form method="POST" action="add-lead.php" class="lead-form">
                    <div class="form-section">
                        <h3>Client Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="client_name">Client Name</label>
                                <input type="text" id="client_name" name="client_name" placeholder="Enter client's full name" required>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" placeholder="e.g. +63 912 345 6789" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="optional-field">(Optional)</span></label>
                                <input type="email" id="email" name="email" placeholder="client@example.com" required>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="source">Lead Source</label>
                                <select id="source" name="source" required class="source-select">
                                    <option value="">Select Lead Source</option>
                                    <?php foreach ($leadSources as $source): ?>
                                    <option value="<?php echo htmlspecialchars($source['name']); ?>">
                                        <?php echo htmlspecialchars($source['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="facebook">Facebook Profile <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="facebook" name="facebook" placeholder="Facebook profile URL">
                            </div>
                            
                            <div class="form-group">
                                <label for="linkedin">LinkedIn Profile <span class="optional-field">(Optional)</span></label>
                                <input type="text" id="linkedin" name="linkedin" placeholder="LinkedIn profile URL">
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
                                    <option value="Hot">Hot</option>
                                    <option value="Warm">Warm</option>
                                    <option value="Cold">Cold</option>
                                </select>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="Inquiry">Inquiry</option>
                                    <option value="Presentation Stage">Presentation Stage</option>
                                    <option value="Negotiation">Negotiation</option>
                                    <option value="Closed">Closed</option>
                                    <option value="Lost">Lost</option>
                                    <option value="Site Tour">Site Tour</option>
                                    <option value="Closed Deal">Closed Deal</option>
                                    <option value="Requirement Stage">Requirement Stage</option>
                                    <option value="Downpayment Stage">Downpayment Stage</option>
                                    <option value="Housing Loan Application">Housing Loan Application</option>
                                    <option value="Loan Approval">Loan Approval</option>
                                    <option value="Loan Takeout">Loan Takeout</option>
                                    <option value="House Inspection">House Inspection</option>
                                    <option value="House Turn Over">House Turn Over</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="developer">Developer</label>
                                <select id="developer" name="developer" required onchange="loadProjectModels(this.value)">
                                    <option value="">Select Developer</option>
                                    <?php foreach ($developers as $dev): ?>
                                    <option value="<?php echo htmlspecialchars($dev['name']); ?>"><?php echo htmlspecialchars($dev['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group required-field">
                                <label for="project_model">Project Model</label>
                                <select id="project_model" name="project_model" required>
                                    <option value="">Select Project Model</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required-field">
                                <label for="price">Total Selling Price (PHP)</label>
                                <input type="text" id="price" name="price" placeholder="e.g. 1,000,000.00" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="remarks">Remarks <span class="optional-field">(Optional)</span></label>
                                <textarea id="remarks" name="remarks" rows="4" placeholder="Add any additional notes or comments about this lead"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="leads.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Lead</button>
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
                    projectModelSelect.appendChild(option);
                });
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
        });
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
 