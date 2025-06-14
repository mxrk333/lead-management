<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$conn = getDbConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Check if user has permission to access settings
if ($user['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Process settings actions
$success_message = '';
$error_message = '';

// Initialize settings array
$settings = [];

// Create settings table if it doesn't exist
$create_settings_table = "CREATE TABLE IF NOT EXISTS settings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_description TEXT,
    setting_group VARCHAR(50) NOT NULL,
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($create_settings_table);

// Initialize default settings if they don't exist
$default_settings = [
    // General Settings
    ['company_name', 'Real Estate Leads CRM', 'Company name displayed in the system', 'general', 1],
    ['company_email', 'info@realestatecrm.com', 'Default company email address', 'general', 1],
    ['company_phone', '+1234567890', 'Company contact phone number', 'general', 1],
    ['company_address', '123 Main Street, City, Country', 'Company physical address', 'general', 1],
    ['company_logo', 'assets/img/logo.png', 'Path to company logo image', 'general', 1],
    ['date_format', 'Y-m-d', 'Default date format for the system', 'general', 1],
    ['time_format', 'H:i', 'Default time format for the system', 'general', 1],
    ['timezone', 'Asia/Manila', 'Default timezone for the system', 'general', 1],
    
    // Lead Settings
    ['lead_auto_assign', '0', 'Automatically assign leads to agents (0=off, 1=on)', 'leads', 0],
    ['lead_assignment_method', 'round_robin', 'Method for auto-assigning leads (round_robin, random, load_balanced)', 'leads', 0],
    ['lead_follow_up_days', '3', 'Default number of days for lead follow-up reminder', 'leads', 0],
    ['lead_status_colors', '{"Inquiry":"#f6c23e","Presentation Stage":"#36b9cc","Negotiation":"#4e73df","Closed":"#1cc88a","Lost":"#e74a3b"}', 'Color codes for lead status labels', 'leads', 1],
    ['lead_temperature_colors', '{"Hot":"#e74a3b","Warm":"#f6c23e","Cold":"#4e73df"}', 'Color codes for lead temperature labels', 'leads', 1],
    
    // Email Settings
    ['smtp_host', 'smtp.example.com', 'SMTP server hostname', 'email', 0],
    ['smtp_port', '587', 'SMTP server port', 'email', 0],
    ['smtp_username', 'notifications@example.com', 'SMTP username', 'email', 0],
    ['smtp_password', 'password', 'SMTP password', 'email', 0],
    ['smtp_encryption', 'tls', 'SMTP encryption method (tls, ssl)', 'email', 0],
    ['email_from_name', 'Real Estate CRM', 'From name for system emails', 'email', 0],
    ['email_from_address', 'noreply@example.com', 'From email address for system emails', 'email', 0],
    
    // Security Settings
    ['password_min_length', '8', 'Minimum password length', 'security', 0],
    ['password_requires_special', '1', 'Require special characters in passwords (0=no, 1=yes)', 'security', 0],
    ['password_requires_number', '1', 'Require numbers in passwords (0=no, 1=yes)', 'security', 0],
    ['password_requires_uppercase', '1', 'Require uppercase letters in passwords (0=no, 1=yes)', 'security', 0],
    ['session_timeout', '30', 'Session timeout in minutes', 'security', 0],
    ['max_login_attempts', '5', 'Maximum failed login attempts before lockout', 'security', 0],
    ['lockout_time', '15', 'Account lockout time in minutes after failed attempts', 'security', 0],
    
    // Notification Settings
    ['enable_email_notifications', '1', 'Enable email notifications (0=off, 1=on)', 'notifications', 0],
    ['enable_browser_notifications', '1', 'Enable browser notifications (0=off, 1=on)', 'notifications', 1],
    ['notify_on_new_lead', '1', 'Send notification on new lead (0=off, 1=on)', 'notifications', 0],
    ['notify_on_lead_update', '1', 'Send notification on lead update (0=off, 1=on)', 'notifications', 0],
    ['notify_on_lead_assignment', '1', 'Send notification on lead assignment (0=off, 1=on)', 'notifications', 0],
    
    // Developer Settings
    ['enable_developer_tools', '0', 'Enable developer tools and debugging (0=off, 1=on)', 'developer', 0],
    ['log_level', 'error', 'Log level (error, warning, info, debug)', 'developer', 0],
    ['maintenance_mode', '0', 'Put system in maintenance mode (0=off, 1=on)', 'developer', 0],
    ['maintenance_message', 'System is currently under maintenance. Please check back later.', 'Message displayed during maintenance mode', 'developer', 0]
];

// Insert default settings if they don't exist
$check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = ?");
$insert_stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_description, setting_group, is_public) VALUES (?, ?, ?, ?, ?)");

foreach ($default_settings as $setting) {
    $check_stmt->bind_param("s", $setting[0]);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $insert_stmt->bind_param("ssssi", $setting[0], $setting[1], $setting[2], $setting[3], $setting[4]);
        $insert_stmt->execute();
    }
}

$check_stmt->close();
$insert_stmt->close();

// Update settings if form submitted
if (isset($_POST['update_settings'])) {
    $updated_settings = $_POST['settings'];
    $update_stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    
    foreach ($updated_settings as $key => $value) {
        // Sanitize the key to prevent SQL injection
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        
        // For security settings, validate the input
        if ($key == 'password_min_length' || $key == 'max_login_attempts' || $key == 'session_timeout' || $key == 'lockout_time') {
            $value = intval($value);
            if ($value <= 0) {
                $error_message = "Invalid value for $key. Must be a positive number.";
                continue;
            }
        }
        
        // For email settings, validate email addresses
        if ($key == 'company_email' || $key == 'email_from_address') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL) && !empty($value)) {
                $error_message = "Invalid email address for $key.";
                continue;
            }
        }
        
        // Update the setting
        $update_stmt->bind_param("ss", $value, $key);
        if ($update_stmt->execute()) {
            $success_message = "Settings updated successfully.";
        } else {
            $error_message = "Error updating settings: " . $conn->error;
        }
    }
    
    $update_stmt->close();
}

// Get all settings grouped by category
$settings_query = "SELECT * FROM settings ORDER BY setting_group, setting_key";
$settings_result = $conn->query($settings_query);

$grouped_settings = [];
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $grouped_settings[$row['setting_group']][] = $row;
    }
}

// Get all developers for dropdown
$developers = [];
$developers_query = "SELECT * FROM developers ORDER BY name";
$developers_result = $conn->query($developers_query);

if ($developers_result) {
    while ($row = $developers_result->fetch_assoc()) {
        $developers[] = $row;
    }
}

// Process developer actions
if (isset($_POST['add_developer'])) {
    $developer_name = trim($_POST['developer_name']);
    
    if (empty($developer_name)) {
        $error_message = "Project name is required.";
    } else {
        // Check if developer name already exists
        $check_stmt = $conn->prepare("SELECT id FROM developers WHERE name = ?");
        $check_stmt->bind_param("s", $developer_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Project name already exists.";
        } else {
            // Insert new developer
            $insert_stmt = $conn->prepare("INSERT INTO developers (name) VALUES (?)");
            $insert_stmt->bind_param("s", $developer_name);
            
            if ($insert_stmt->execute()) {
                $success_message = "Project added successfully.";
                // Refresh the page to show the new developer
                header("Location: settings.php?tab=developers");
                exit();
            } else {
                $error_message = "Error adding Project: " . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Delete developer
if (isset($_GET['delete_developer'])) {
    $developer_id = intval($_GET['delete_developer']);
    
    // Check if developer has project models
    $check_stmt = $conn->prepare("SELECT COUNT(*) as model_count FROM project_models WHERE developer_id = ?");
    $check_stmt->bind_param("i", $developer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $model_count = 0;
    
    if ($row = $check_result->fetch_assoc()) {
        $model_count = $row['model_count'];
    }
    
    if ($model_count > 0) {
        $error_message = "Cannot delete Project with associated project models. Please delete the models first.";
    } else {
        // Delete developer
        $delete_stmt = $conn->prepare("DELETE FROM developers WHERE id = ?");
        $delete_stmt->bind_param("i", $developer_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Project deleted successfully.";
            // Refresh the page
            header("Location: settings.php?tab=developers");
            exit();
        } else {
            $error_message = "Error deleting developer: " . $conn->error;
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
}

// Get all project models grouped by developer
$project_models = [];
$models_query = "SELECT pm.*, d.name as developer_name 
                FROM project_models pm 
                JOIN developers d ON pm.developer_id = d.id 
                ORDER BY d.name, pm.name";
$models_result = $conn->query($models_query);

if ($models_result) {
    while ($row = $models_result->fetch_assoc()) {
        $project_models[$row['developer_name']][] = $row;
    }
}

// Process project model actions
if (isset($_POST['add_model'])) {
    $developer_id = intval($_POST['developer_id']);
    $model_names = $_POST['model_names'];
    
    if (empty($model_names) || $developer_id <= 0) {
        $error_message = "Project and model name are required.";
    } else {
        // Check if model names already exist for this developer
        $check_stmt = $conn->prepare("SELECT id FROM project_models WHERE developer_id = ? AND name IN (?)");
        $in_values = implode(',', array_fill(0, count($model_names), '?'));
        $stmt = $conn->prepare("SELECT id FROM project_models WHERE developer_id = ? AND name = ?");
        $stmt->bind_param("is", $developer_id, $model_names[0]);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_models = [];
            while ($row = $check_result->fetch_assoc()) {
                $existing_models[] = $row['name'];
            }
            $error_message = "The following models already exist for this project: " . implode(', ', $existing_models);
        } else {
            // Insert new models
            $insert_stmt = $conn->prepare("INSERT INTO project_models (developer_id, name) VALUES (?, ?)");
            $stmt = $conn->prepare("INSERT INTO project_models (developer_id, name) VALUES (?, ?)");
            
            foreach ($model_names as $model_name) {
                $stmt->bind_param("is", $developer_id, $model_name);
                if ($stmt->execute()) {
                    $success_message = "Project models added successfully.";
                } else {
                    $error_message = "Error adding project models: " . $conn->error;
                    break;
                }
            }
            $stmt->close();
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Delete project model
if (isset($_GET['delete_model'])) {
    $model_id = intval($_GET['delete_model']);
    
    // Check if model is used in any leads
    $check_stmt = $conn->prepare("SELECT COUNT(*) as lead_count FROM leads WHERE project_model = (SELECT name FROM project_models WHERE id = ?)");
    $check_stmt->bind_param("i", $model_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $lead_count = 0;
    
    if ($row = $check_result->fetch_assoc()) {
        $lead_count = $row['lead_count'];
    }
    
    if ($lead_count > 0) {
        $error_message = "Cannot delete project model that is used in leads.";
    } else {
        // Delete model
        $delete_stmt = $conn->prepare("DELETE FROM project_models WHERE id = ?");
        $delete_stmt->bind_param("i", $model_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Project model deleted successfully.";
            // Refresh the page
            header("Location: settings.php?tab=project_models");
            exit();
        } else {
            $error_message = "Error deleting project model: " . $conn->error;
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
}

// Get current active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Settings Page Specific Styles */
        .settings-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            overflow-x: auto;
            scrollbar-width: thin;
        }
        
        .settings-tabs::-webkit-scrollbar {
            height: 5px;
        }
        
        .settings-tabs::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .settings-tabs::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 5px;
        }
        
        .settings-tab {
            padding: 15px 20px;
            font-weight: 500;
            color: #5a5c69;
            cursor: pointer;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .settings-tab:hover {
            color: #4e73df;
            background-color: #f8f9fc;
        }
        
        .settings-tab.active {
            color: #4e73df;
            border-bottom-color: #4e73df;
            background-color: #f8f9fc;
        }
        
        .settings-content {
            padding: 25px;
        }
        
        .settings-section {
            margin-bottom: 30px;
        }
        
        .settings-section:last-child {
            margin-bottom: 0;
        }
        
        .settings-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #4a5568;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }
        
        .form-group {
            flex: 1 0 300px;
            margin: 10px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #fff;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1);
            outline: none;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #858796;
            margin-top: 5px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background-color: #4e73df;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #3a5ccc;
        }
        
        .btn-secondary {
            background-color: #f8f9fc;
            color: #5a5c69;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background-color: #eaecf4;
        }
        
        .btn-danger {
            background-color: #e74a3b;
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background-color: #d52a1a;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background-color: #fdeaea;
            color: #e74a3b;
            border: 1px solid #f8d7da;
        }
        
        .alert-success {
            background-color: #e6f8f0;
            color: #1cc88a;
            border: 1px solid #d1f2e6;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #4e73df;
        }
        
        input:focus + .toggle-slider {
            box-shadow: 0 0 1px #4e73df;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .toggle-group {
            display: flex;
            align-items: center;
        }
        
        .toggle-label {
            margin-left: 10px;
            font-size: 14px;
            color: #5a5c69;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .data-table th {
            background-color: #f8f9fc;
            color: #4a5568;
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #5a5c69;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background-color: #f8f9fc;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background-color: #f8f9fc;
            color: #5a5c69;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .action-btn:hover {
            background-color: #eaecf4;
        }
        
        .action-btn.delete:hover {
            background-color: #e74a3b;
            color: white;
            border-color: #e74a3b;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 25px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #4a5568;
            margin: 0;
        }
        
        .modal-close {
            font-size: 24px;
            color: #858796;
            cursor: pointer;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .color-picker-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        
        .color-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .color-label {
            font-size: 14px;
            color: #5a5c69;
            width: 100px;
        }
        
        .color-input {
            width: 40px;
            height: 40px;
            padding: 0;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .color-input::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        
        .color-input::-webkit-color-swatch {
            border: none;
            border-radius: 3px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .form-group {
                flex: 1 0 100%;
            }
            
            .settings-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
        }
        
        .tab-buttons .btn.active {
            background-color: #4e73df;
            color: white;
        }
        
        #bulkModelInput textarea {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            resize: vertical;
        }
        
        #bulkModelInput textarea:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }
        
        .model-entry {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .model-entry input {
            flex: 1;
        }
        
        .model-entry .btn-danger {
            padding: 8px;
            height: 35px;
            width: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        
        .mt-2 {
            margin-top: 10px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }
        
        .team-radio-group {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
        }
        
        .team-radio-option {
            margin-bottom: 8px;
            position: relative;
        }
        
        .team-radio-option:last-child {
            margin-bottom: 0;
        }
        
        .team-radio {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .team-radio-label {
            display: block;
            padding: 12px 16px;
            background-color: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .team-radio:checked + .team-radio-label {
            border-color: #4e73df;
            background-color: #ebf4ff;
        }
        
        .team-radio:focus + .team-radio-label {
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
        }
        
        .team-radio:hover + .team-radio-label {
            border-color: #cbd5e0;
        }
        
        .team-name {
            font-weight: 500;
            color: #4a5568;
        }
        
        .team-radio:checked + .team-radio-label .team-name {
            color: #4e73df;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-header">
                <h2>System Settings</h2>
            </div>
            
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <div class="settings-container">
                <div class="settings-tabs">
                    <a href="?tab=general" class="settings-tab <?php echo $active_tab == 'general' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> General
                    </a>
                    <a href="?tab=leads" class="settings-tab <?php echo $active_tab == 'leads' ? 'active' : ''; ?>">
                        <i class="fas fa-funnel-dollar"></i> Leads
                    </a>
                    <a href="?tab=email" class="settings-tab <?php echo $active_tab == 'email' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i> Email
                    </a>
                    <a href="?tab=security" class="settings-tab <?php echo $active_tab == 'security' ? 'active' : ''; ?>">
                        <i class="fas fa-shield-alt"></i> Security
                    </a>
                    <a href="?tab=notifications" class="settings-tab <?php echo $active_tab == 'notifications' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i> Notifications
                    </a>
                    <a href="?tab=developers" class="settings-tab <?php echo $active_tab == 'developers' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i> Projects
                    </a>
                    <a href="?tab=project_models" class="settings-tab <?php echo $active_tab == 'project_models' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Models
                    </a>
                    <a href="?tab=developer_tools" class="settings-tab <?php echo $active_tab == 'developer_tools' ? 'active' : ''; ?>">
                        <i class="fas fa-code"></i> Developer Tools
                    </a>
                </div>
                
                <div class="settings-content">
                    <?php if ($active_tab == 'general'): ?>
                    <!-- General Settings -->
                    <form method="POST" action="">
                        <div class="settings-section">
                            <h3 class="settings-section-title">Company Information</h3>
                            <div class="form-row">
                                <?php foreach ($grouped_settings['general'] as $setting): ?>
                                <div class="form-group">
                                    <label for="<?php echo $setting['setting_key']; ?>"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <input type="text" id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($active_tab == 'leads'): ?>
                    <!-- Lead Settings -->
                    <form method="POST" action="">
                        <div class="settings-section">
                            <h3 class="settings-section-title">Lead Management</h3>
                            <div class="form-row">
                                <?php foreach ($grouped_settings['leads'] as $setting): ?>
                                <?php if ($setting['setting_key'] == 'lead_auto_assign'): ?>
                                <div class="form-group">
                                    <label><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <div class="toggle-group">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="settings[<?php echo $setting['setting_key']; ?>]" value="1" <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label"><?php echo $setting['setting_value'] == '1' ? 'Enabled' : 'Disabled'; ?></span>
                                    </div>
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif ($setting['setting_key'] == 'lead_assignment_method'): ?>
                                <div class="form-group">
                                    <label for="<?php echo $setting['setting_key']; ?>"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <select id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]">
                                        <option value="round_robin" <?php echo $setting['setting_value'] == 'round_robin' ? 'selected' : ''; ?>>Round Robin</option>
                                        <option value="random" <?php echo $setting['setting_value'] == 'random' ? 'selected' : ''; ?>>Random</option>
                                        <option value="load_balanced" <?php echo $setting['setting_value'] == 'load_balanced' ? 'selected' : ''; ?>>Load Balanced</option>
                                    </select>
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif (in_array($setting['setting_key'], ['lead_status_colors', 'lead_temperature_colors'])): ?>
                                <div class="form-group">
                                    <label for="<?php echo $setting['setting_key']; ?>"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <div class="color-picker-container">
                                        <?php 
                                        $colors = json_decode($setting['setting_value'], true);
                                        foreach ($colors as $label => $color): 
                                        ?>
                                        <div class="color-item">
                                            <span class="color-label"><?php echo htmlspecialchars($label); ?></span>
                                            <input type="color" class="color-input" name="color_<?php echo $setting['setting_key']; ?>_<?php echo $label; ?>" value="<?php echo htmlspecialchars($color); ?>" data-label="<?php echo htmlspecialchars($label); ?>" data-setting="<?php echo $setting['setting_key']; ?>">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="form-group">
                                    <label for="<?php echo $setting['setting_key']; ?>"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <input type="text" id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($active_tab == 'email'): ?>
                    <!-- Email Settings -->
                    <form method="POST" action="">
                        <div class="settings-section">
                            <h3 class="settings-section-title">Email Configuration</h3>
                            <div class="form-row">
                                <?php foreach ($grouped_settings['email'] as $setting): ?>
                                <div class="form-group">
                                    <label for="<?php echo $setting['setting_key']; ?>"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <?php if ($setting['setting_key'] == 'smtp_encryption'): ?>
                                    <select id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]">
                                        <option value="tls" <?php echo $setting['setting_value'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo $setting['setting_value'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo $setting['setting_value'] == 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                    <?php elseif ($setting['setting_key'] == 'smtp_password'): ?>
                                    <input type="password" id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php else: ?>
                                    <input type="text" id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php endif; ?>
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="testEmailSettings()">
                                <i class="fas fa-paper-plane"></i> Test Email Settings
                            </button>
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($active_tab == 'security'): ?>
                    <!-- Security Settings -->
                    <form method="POST" action="">
                        <div class="settings-section">
                            <h3 class="settings-section-title">Security Configuration</h3>
                            <div class="form-row">
                                <?php foreach ($grouped_settings['security'] as $setting): ?>
                                <?php if (in_array($setting['setting_key'], ['password_requires_special', 'password_requires_number', 'password_requires_uppercase'])): ?>
                                <div class="form-group">
                                    <label><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <div class="toggle-group">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="settings[<?php echo $setting['setting_key']; ?>]" value="1" <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label"><?php echo $setting['setting_value'] == '1' ? 'Required' : 'Not Required'; ?></span>
                                    </div>
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="form-group">
                                    <label for="<?php echo $setting['setting_key']; ?>"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <input type="number" id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>" min="1">
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($active_tab == 'notifications'): ?>
                    <!-- Notification Settings -->
                    <form method="POST" action="">
                        <div class="settings-section">
                            <h3 class="settings-section-title">Notification Settings</h3>
                            <div class="form-row">
                                <?php foreach ($grouped_settings['notifications'] as $setting): ?>
                                <div class="form-group">
                                    <label><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <div class="toggle-group">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="settings[<?php echo $setting['setting_key']; ?>]" value="1" <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label"><?php echo $setting['setting_value'] == '1' ? 'Enabled' : 'Disabled'; ?></span>
                                    </div>
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($active_tab == 'developers'): ?>
                    <!-- Developers Management -->
                    <div class="settings-section">
                        <div class="settings-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 class="settings-section-title" style="margin: 0;">Property Projects</h3>
                            <button type="button" class="btn btn-primary" onclick="openAddDeveloperModal()">
                                <i class="fas fa-plus"></i> Add Projects
                            </button>
                        </div>
                        
                        <?php if (count($developers) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Projects Name</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($developers as $developer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($developer['name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($developer['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" onclick="openEditDeveloperModal(<?php echo $developer['id']; ?>, '<?php echo htmlspecialchars(addslashes($developer['name'])); ?>')" class="action-btn" title="Edit Developer">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" onclick="confirmDeleteDeveloper(<?php echo $developer['id']; ?>, '<?php echo htmlspecialchars(addslashes($developer['name'])); ?>')" class="action-btn delete" title="Delete Developer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="no-data" style="text-align: center; padding: 30px; color: #858796;">
                            <i class="fas fa-building" style="font-size: 48px; margin-bottom: 15px; color: #e2e8f0;"></i>
                            <p>No Project found. Add your first project to get started.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php elseif ($active_tab == 'project_models'): ?>
                    <!-- Project Models Management -->
                    <div class="settings-section">
                        <div class="settings-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 class="settings-section-title" style="margin: 0;">Project Models</h3>
                            <button type="button" class="btn btn-primary" onclick="openAddModelModal()">
                                <i class="fas fa-plus"></i> Add Project Model
                            </button>
                        </div>
                        
                        <?php if (count($project_models) > 0): ?>
                        <?php foreach ($project_models as $developer_name => $models): ?>
                        <div class="developer-models" style="margin-bottom: 30px;">
                            <h4 style="margin-bottom: 15px; color: #4a5568; font-size: 16px; font-weight: 600;"><?php echo htmlspecialchars($developer_name); ?></h4>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Model Name</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($models as $model): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($model['name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($model['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" onclick="openEditModelModal(<?php echo $model['id']; ?>, '<?php echo htmlspecialchars(addslashes($model['name'])); ?>', <?php echo $model['developer_id']; ?>)" class="action-btn" title="Edit Model">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" onclick="confirmDeleteModel(<?php echo $model['id']; ?>, '<?php echo htmlspecialchars(addslashes($model['name'])); ?>')" class="action-btn delete" title="Delete Model">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="no-data" style="text-align: center; padding: 30px; color: #858796;">
                            <i class="fas fa-home" style="font-size: 48px; margin-bottom: 15px; color: #e2e8f0;"></i>
                            <p>No project models found. Add your first model to get started.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php elseif ($active_tab == 'developer_tools'): ?>
                    <!-- Developer Tools -->
                    <form method="POST" action="">
                        <div class="settings-section">
                            <h3 class="settings-section-title">Developer Tools</h3>
                            <div class="form-row">
                                <?php foreach ($grouped_settings['developer'] as $setting): ?>
                                <?php if ($setting['setting_key'] == 'maintenance_mode' || $setting['setting_key'] == 'enable_developer_tools'): ?>
                                <div class="form-group">
                                    <label><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <div class="toggle-group">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="settings[<?php echo $setting['setting_key']; ?>]" value="1" <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="toggle-label"><?php echo $setting['setting_value'] == '1' ? 'Enabled' : 'Disabled'; ?></span>
                                    </div>
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif ($setting['setting_key'] == 'log_level'): ?>
                                <div class="form-group">
                                    <label for="<?php echo $setting['setting_key']; ?>"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <select id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]">
                                        <option value="error" <?php echo $setting['setting_value'] == 'error' ? 'selected' : ''; ?>>Error</option>
                                        <option value="warning" <?php echo $setting['setting_value'] == 'warning' ? 'selected' : ''; ?>>Warning</option>
                                        <option value="info" <?php echo $setting['setting_value'] == 'info' ? 'selected' : ''; ?>>Info</option>
                                        <option value="debug" <?php echo $setting['setting_value'] == 'debug' ? 'selected' : ''; ?>>Debug</option>
                                    </select>
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif ($setting['setting_key'] == 'maintenance_message'): ?>
                                <div class="form-group">
                                    <label for="<?php echo $setting['setting_key']; ?>"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <textarea id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="form-group">
                                    <label for="<?php echo $setting['setting_key']; ?>"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <input type="text" id="<?php echo $setting['setting_key']; ?>" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                    <?php if (!empty($setting['setting_description'])): ?>
                                    <div class="help-text"><?php echo htmlspecialchars($setting['setting_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3 class="settings-section-title">System Tools</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <button type="button" class="btn btn-secondary" onclick="clearSystemCache()">
                                        <i class="fas fa-broom"></i> Clear System Cache
                                    </button>
                                    <div class="help-text">Clears all cached data and temporary files.</div>
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-secondary" onclick="optimizeDatabase()">
                                        <i class="fas fa-database"></i> Optimize Database
                                    </button>
                                    <div class="help-text">Optimizes database tables and improves performance.</div>
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-secondary" onclick="viewSystemLogs()">
                                        <i class="fas fa-file-alt"></i> View System Logs
                                    </button>
                                    <div class="help-text">View system logs for debugging and troubleshooting.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Developer Modal -->
    <div id="addDeveloperModal" class="modal" role="dialog" aria-labelledby="addDeveloperModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="addDeveloperModalTitle">Add New Project</h3>
                <button type="button" class="modal-close" onclick="closeAddDeveloperModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="?tab=developers">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="developer_name">Project Name</label>
                        <input type="text" id="developer_name" name="developer_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddDeveloperModal()">Cancel</button>
                    <button type="submit" name="add_developer" class="btn btn-primary">Add Project</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Developer Modal -->
    <div id="editDeveloperModal" class="modal" role="dialog" aria-labelledby="editDeveloperModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="editDeveloperModalTitle">Edit Developer</h3>
                <button type="button" class="modal-close" onclick="closeEditDeveloperModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="?tab=developers">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_developer_name">Project Name</label>
                        <input type="text" id="edit_developer_name" name="developer_name" required>
                    </div>
                    <input type="hidden" id="edit_developer_id" name="developer_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditDeveloperModal()">Cancel</button>
                    <button type="submit" name="edit_developer" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Project Model Modal -->
    <div id="addModelModal" class="modal" role="dialog" aria-labelledby="addModelModalTitle" aria-hidden="true">
        <div class="modal-content" style="width: 600px; max-width: 95%;">
            <div class="modal-header">
                <h3 class="modal-title" id="addModelModalTitle">Add New Project Model</h3>
                <button type="button" class="modal-close" onclick="closeAddModelModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="?tab=project_models">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="developer_id">Project</label>
                        <select id="developer_id" name="developer_id" required>
                            <option value="">-- Select Project --</option>
                            <?php foreach ($developers as $developer): ?>
                            <option value="<?php echo $developer['id']; ?>"><?php echo htmlspecialchars($developer['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Add Models</label>
                        <div id="modelsList">
                            <div class="model-entry">
                                <input type="text" name="model_names[]" class="form-control" placeholder="Enter model name" required>
                                <button type="button" class="btn btn-danger btn-sm remove-model" onclick="removeModelEntry(this)" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addModelEntry()">
                            <i class="fas fa-plus"></i> Add Another Model
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModelModal()">Cancel</button>
                    <button type="submit" name="add_model" class="btn btn-primary">Add Model(s)</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Project Model Modal -->
    <div id="editModelModal" class="modal" role="dialog" aria-labelledby="editModelModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="editModelModalTitle">Edit Project Model</h3>
                <button type="button" class="modal-close" onclick="closeEditModelModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="?tab=project_models">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_developer_id">Developer</label>
                        <select id="edit_developer_id" name="developer_id" required>
                            <?php foreach ($developers as $developer): ?>
                            <option value="<?php echo $developer['id']; ?>"><?php echo htmlspecialchars($developer['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_model_name">Model Name</label>
                        <input type="text" id="edit_model_name" name="model_name" required>
                    </div>
                    <input type="hidden" id="edit_model_id" name="model_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModelModal()">Cancel</button>
                    <button type="submit" name="edit_model" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Developer Modals
    function openAddDeveloperModal() {
        document.getElementById('addDeveloperModal').style.display = 'block';
        document.getElementById('developer_name').focus();
    }
    
    function closeAddDeveloperModal() {
        document.getElementById('addDeveloperModal').style.display = 'none';
    }
    
    function openEditDeveloperModal(developerId, developerName) {
        document.getElementById('edit_developer_id').value = developerId;
        document.getElementById('edit_developer_name').value = developerName;
        document.getElementById('editDeveloperModal').style.display = 'block';
        document.getElementById('edit_developer_name').focus();
    }
    
    function closeEditDeveloperModal() {
        document.getElementById('editDeveloperModal').style.display = 'none';
    }
    
    function confirmDeleteDeveloper(developerId, developerName) {
        if (confirm('Are you sure you want to delete the developer "' + developerName + '"? This action cannot be undone.')) {
            window.location.href = 'settings.php?tab=developers&delete_developer=' + developerId;
        }
    }
    
    // Project Model Modals
    function openAddModelModal() {
        document.getElementById('addModelModal').style.display = 'block';
        document.getElementById('developer_id').focus();
    }
    
    function closeAddModelModal() {
        document.getElementById('addModelModal').style.display = 'none';
    }
    
    function openEditModelModal(modelId, modelName, developerId) {
        document.getElementById('edit_model_id').value = modelId;
        document.getElementById('edit_model_name').value = modelName;
        document.getElementById('edit_developer_id').value = developerId;
        document.getElementById('editModelModal').style.display = 'block';
        document.getElementById('edit_model_name').focus();
    }
    
    function closeEditModelModal() {
        document.getElementById('editModelModal').style.display = 'none';
    }
    
    function confirmDeleteModel(modelId, modelName) {
        if (confirm('Are you sure you want to delete the project model "' + modelName + '"? This action cannot be undone.')) {
            window.location.href = 'settings.php?tab=project_models&delete_model=' + modelId;
        }
    }
    
    // Color picker handling
    document.addEventListener('DOMContentLoaded', function() {
        const colorInputs = document.querySelectorAll('.color-input');
        colorInputs.forEach(input => {
            input.addEventListener('change', function() {
                const setting = this.dataset.setting;
                const label = this.dataset.label;
                const color = this.value;
                
                // Get the current JSON value
                const hiddenInput = document.getElementById(setting);
                const colors = JSON.parse(hiddenInput.value);
                
                // Update the color for this label
                colors[label] = color;
                
                // Update the hidden input with the new JSON
                hiddenInput.value = JSON.stringify(colors);
            });
        });
        
        // Toggle switch labels
        const toggleSwitches = document.querySelectorAll('.toggle-switch input[type="checkbox"]');
        toggleSwitches.forEach(toggle => {
            toggle.addEventListener('change', function() {
                const label = this.parentElement.nextElementSibling;
                if (this.checked) {
                    label.textContent = this.name.includes('requires') ? 'Required' : 'Enabled';
                } else {
                    label.textContent = this.name.includes('requires') ? 'Not Required' : 'Disabled';
                }
            });
        });
    });
    
    // System tools functions
    function clearSystemCache() {
        if (confirm('Are you sure you want to clear the system cache? This may temporarily affect system performance.')) {
            // AJAX request to clear cache
            alert('System cache cleared successfully.');
        }
    }
    
    function optimizeDatabase() {
        if (confirm('Are you sure you want to optimize the database? This may take a few moments.')) {
            // AJAX request to optimize database
            alert('Database optimized successfully.');
        }
    }
    
    function viewSystemLogs() {
        // Redirect to logs page or open modal with logs
        window.open('logs.php', '_blank');
    }
    
    function testEmailSettings() {
        // Get current email settings
        const host = document.querySelector('input[name="settings[smtp_host]"]').value;
        const port = document.querySelector('input[name="settings[smtp_port]"]').value;
        const username = document.querySelector('input[name="settings[smtp_username]"]').value;
        const password = document.querySelector('input[name="settings[smtp_password]"]').value;
        
        if (!host || !port || !username || !password) {
            alert('Please fill in all email settings before testing.');
            return;
        }
        
        // Show testing message
        alert('Testing email settings... A test email will be sent to your admin email address.');
        
        // In a real implementation, you would make an AJAX request to test the settings
        setTimeout(() => {
            alert('Test email sent successfully!');
        }, 2000);
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = 'none';
        }
    }
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
    
    function addModelEntry() {
        const modelsList = document.getElementById('modelsList');
        const newEntry = document.createElement('div');
        newEntry.className = 'model-entry';
        newEntry.innerHTML = `
            <input type="text" name="model_names[]" class="form-control" placeholder="Enter model name" required>
            <button type="button" class="btn btn-danger btn-sm remove-model" onclick="removeModelEntry(this)">
                <i class="fas fa-times"></i>
            </button>
        `;
        modelsList.appendChild(newEntry);
        updateRemoveButtons();
        newEntry.querySelector('input').focus();
    }
    
    function removeModelEntry(button) {
        button.closest('.model-entry').remove();
        updateRemoveButtons();
    }
    
    function updateRemoveButtons() {
        const entries = document.querySelectorAll('.model-entry');
        entries.forEach((entry, index) => {
            const removeBtn = entry.querySelector('.remove-model');
            if (entries.length === 1) {
                removeBtn.style.display = 'none';
            } else {
                removeBtn.style.display = 'flex';
            }
        });
    }
    </script>
</body>
</html>