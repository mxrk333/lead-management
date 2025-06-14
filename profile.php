<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';

// Get database connection
$conn = getDbConnection();
if (!$conn) {
    $error_message = "Database connection failed. Please try again later.";
}

// Get user data
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

if (!$user) {
    $error_message = "Failed to retrieve user data. Please try again later.";
}

// Process profile update
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $profilePicture = isset($_FILES['profile_picture']) ? $_FILES['profile_picture'] : null;
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate inputs
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Check if email already exists (excluding current user)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        if (!$check_stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $check_stmt->bind_param("si", $email, $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Email address is already in use by another account.";
            } else {
                // Start transaction
                $conn->begin_transaction();
                $transaction_success = true;
                
                // Update basic profile information
                $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                if (!$update_stmt) {
                    $error_message = "Database error: " . $conn->error;
                    $transaction_success = false;
                } else {
                    $update_stmt->bind_param("ssi", $name, $email, $user_id);
                    
                    if (!$update_stmt->execute()) {
                        $error_message = "Error updating profile: " . $update_stmt->error;
                        $transaction_success = false;
                    } else {
                        // Update session data
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                    }
                }
                
                // Update password if requested
                if ($transaction_success && !empty($new_password)) {
                    if (empty($current_password)) {
                        $error_message = "Current password is required to change password.";
                        $transaction_success = false;
                    } elseif ($new_password !== $confirm_password) {
                        $error_message = "New passwords do not match.";
                        $transaction_success = false;
                    } elseif (strlen($new_password) < 8) {
                        $error_message = "New password must be at least 8 characters long.";
                        $transaction_success = false;
                    } else {
                        // Verify current password
                        $password_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                        if (!$password_stmt) {
                            $error_message = "Database error: " . $conn->error;
                            $transaction_success = false;
                        } else {
                            $password_stmt->bind_param("i", $user_id);
                            $password_stmt->execute();
                            $password_result = $password_stmt->get_result();
                            
                            if (!$password_result) {
                                $error_message = "Error verifying password: " . $password_stmt->error;
                                $transaction_success = false;
                            } else {
                                $user_data = $password_result->fetch_assoc();
                                
                                if (!password_verify($current_password, $user_data['password'])) {
                                    $error_message = "Current password is incorrect.";
                                    $transaction_success = false;
                                } else {
                                    // Hash new password and update
                                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                    $password_update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                                    
                                    if (!$password_update_stmt) {
                                        $error_message = "Database error: " . $conn->error;
                                        $transaction_success = false;
                                    } else {
                                        $password_update_stmt->bind_param("si", $hashed_password, $user_id);
                                        
                                        if (!$password_update_stmt->execute()) {
                                            $error_message = "Error updating password: " . $password_update_stmt->error;
                                            $transaction_success = false;
                                        }
                                        $password_update_stmt->close();
                                    }
                                }
                                $password_stmt->close();
                            }
                        }
                    }
                }
                
                // Handle profile picture upload
                if ($transaction_success && $profilePicture && $profilePicture['error'] === UPLOAD_ERR_OK) {
                    $file_extension = strtolower(pathinfo($profilePicture['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($file_extension, $allowed_extensions)) {
                        $error_message = "Invalid file type. Only JPG, PNG, and GIF files are allowed.";
                        $transaction_success = false;
                    } else {
                        $upload_dir = 'uploads/profile_pictures/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_name = uniqid('profile_') . '.' . $file_extension;
                        $target_file = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($profilePicture['tmp_name'], $target_file)) {
                            // Delete old profile picture if exists
                            if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                                unlink($user['profile_picture']);
                            }
                            
                            // Update profile picture in database
                            $picture_update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                            
                            if (!$picture_update_stmt) {
                                $error_message = "Database error: " . $conn->error;
                                $transaction_success = false;
                            } else {
                                $picture_update_stmt->bind_param("si", $target_file, $user_id);
                                
                                if (!$picture_update_stmt->execute()) {
                                    $error_message = "Error updating profile picture: " . $picture_update_stmt->error;
                                    $transaction_success = false;
                                } else {
                                    // Update session with new profile picture
                                    $_SESSION['user_profile_picture'] = $target_file;
                                }
                            }
                        } else {
                            $error_message = "Error uploading profile picture. Check directory permissions.";
                            $transaction_success = false;
                        }
                    }
                }
                
                // Commit or rollback transaction based on success
                if ($transaction_success) {
                    $conn->commit();
                    if (!empty($new_password)) {
                        $success_message = "Profile and password updated successfully.";
                    } else {
                        $success_message = "Profile information updated successfully.";
                    }
                    // Refresh user data
                    $user = getUserById($user_id);
                    if (!$user) {
                        $error_message = "Profile updated but failed to refresh user data.";
                    }
                } else {
                    $conn->rollback();
                    if (empty($error_message)) {
                        $error_message = "An error occurred while updating your profile.";
                    }
                }
            }
        }
    }
}

// Get user's team information
$team_name = "Not Assigned";
if (!empty($user['team_id'])) {
    $team_stmt = $conn->prepare("SELECT name FROM teams WHERE id = ?");
    if ($team_stmt) {
        $team_stmt->bind_param("i", $user['team_id']);
        $team_stmt->execute();
        $team_result = $team_stmt->get_result();
        
        if ($team_row = $team_result->fetch_assoc()) {
            $team_name = $team_row['name'];
        }
    }
}

// Get user's lead statistics
$lead_stats = [
    'total' => 0,
    'hot' => 0,
    'warm' => 0,
    'cold' => 0,
    'closed' => 0,
    'lost' => 0
];

$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN temperature = 'Hot' THEN 1 ELSE 0 END) as hot,
        SUM(CASE WHEN temperature = 'Warm' THEN 1 ELSE 0 END) as warm,
        SUM(CASE WHEN temperature = 'Cold' THEN 1 ELSE 0 END) as cold,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed,
        SUM(CASE WHEN status = 'Lost' THEN 1 ELSE 0 END) as lost
    FROM leads 
    WHERE user_id = ?
");

if ($stats_stmt) {
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    
    if ($stats_row = $stats_result->fetch_assoc()) {
        $lead_stats = $stats_row;
    }
}

// Get recent activities
$recent_activities = [];
$activities_stmt = $conn->prepare("
    SELECT la.*, l.client_name, l.id as lead_id
    FROM lead_activities la
    JOIN leads l ON la.lead_id = l.id
    WHERE la.user_id = ?
    ORDER BY la.created_at DESC
    LIMIT 5
");

if ($activities_stmt) {
    $activities_stmt->bind_param("i", $user_id);
    $activities_stmt->execute();
    $activities_result = $activities_stmt->get_result();
    
    while ($activity = $activities_result->fetch_assoc()) {
        $recent_activities[] = $activity;
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Real Estate Leads CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Profile Page Specific Styles */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .page-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: #4a5568;
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
        }
        
        .profile-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        /* Profile Card */
        .profile-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .profile-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        /* Profile Header */
        .profile-header {
            position: relative;
            padding: 0;
        }
        
        .profile-cover {
            height: 500px;
            background-image: url('assets/images/innersparc.jpg'); 
            background-size: cover;
            background-position: center;
            position: relative;
            display: flex;
            align-items: flex-end;
            padding: 0 100px;
        }
        
        .profile-header-content {
            display: flex;
            align-items: flex-end;
            width: 100%;
            position: relative;
            bottom: -60px;
        }
        
        .profile-avatar-container {
            flex-shrink: 0;
            margin-right: 30px;
        }
        
        .profile-avatar {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #4e73df;
            color: white;
            font-size: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 5px solid white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar-edit {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: #4e73df;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            border: 2px solid white;
            transition: all 0.2s;
            z-index: 10;
        }
        
        .profile-avatar-edit:hover {
            background-color: #3a5ccc;
            transform: scale(1.1);
        }
        
        .profile-header-info {
            flex: 1;
            padding-bottom: 15px;
        }
        
        .profile-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #fff;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }
        
        .profile-role {
            display: inline-block;
            font-size: 14px;
            color: #4e73df;
            background-color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 15px;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .profile-header-details {
            padding: 70px 30px 25px;
            background-color: #fff;
        }
        
        .profile-quick-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .quick-info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background-color: #f8f9fc;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .quick-info-item:hover {
            background-color: #eaecf4;
            transform: translateY(-2px);
        }
        
        .quick-info-icon {
            color: #4e73df;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        .quick-info-text {
            font-size: 14px;
            color: #4a5568;
            font-weight: 500;
        }
        
        /* Stats Section */
        .stats-section {
            padding: 25px 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #4a5568;
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #4e73df;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .profile-header-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .profile-avatar-container {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .profile-header-info {
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background-color: #f8f9fc;
            border-radius: 10px;
            padding: 20px 15px;
            text-align: center;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .stat-card.total-card {
            border-bottom-color: #4e73df;
        }
        
        .stat-card.hot-card {
            border-bottom-color: #e74a3b;
        }
        
        .stat-card.warm-card {
            border-bottom-color: #f6c23e;
        }
        
        .stat-card.cold-card {
            border-bottom-color: #36b9cc;
        }
        
        .stat-card.closed-card {
            border-bottom-color: #1cc88a;
        }
        
        .stat-card.lost-card {
            border-bottom-color: #858796;
        }
        
        .stat-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .total-card .stat-icon {
            color: #4e73df;
        }
        
        .hot-card .stat-icon {
            color: #e74a3b;
        }
        
        .warm-card .stat-icon {
            color: #f6c23e;
        }
        
        .cold-card .stat-icon {
            color: #36b9cc;
        }
        
        .closed-card .stat-icon {
            color: #1cc88a;
        }
        
        .lost-card .stat-icon {
            color: #858796;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
        }
        
        /* Main Content */
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
        }
        
        @media (max-width: 992px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Activity Feed */
        .activity-feed {
            padding: 25px 30px;
        }
        
        .activity-list {
            margin-bottom: 25px;
        }
        
        .activity-item {
            display: flex;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 10px;
            transition: all 0.2s;
            background-color: #f8f9fc;
            border-left: 4px solid #4e73df;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        
        .activity-item:hover {
            background-color: #eaecf4;
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .activity-item:active {
            transform: translateX(5px) scale(0.98);
        }
        
        .activity-item:last-child {
            margin-bottom: 0;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background-color: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #4e73df;
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-size: 14px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 5px;
        }
        
        .activity-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-client {
            font-size: 12px;
            color: #4e73df;
            font-weight: 500;
            padding: 2px 8px;
            background-color: rgba(78, 115, 223, 0.1);
            border-radius: 4px;
        }
        
        .activity-time {
            font-size: 12px;
            color: #718096;
        }
        
        /* Profile Form */
        .profile-form {
            padding: 25px 30px;
        }
        
        .tab-container {
            margin-bottom: 25px;
        }
        
        .tab-nav {
            display: flex;
            margin-bottom: 25px;
            border-radius: 8px;
            background-color: #f8f9fc;
            padding: 5px;
            overflow: hidden;
        }
        
        .tab-link {
            flex: 1;
            padding: 12px 20px;
            font-weight: 500;
            color: #718096;
            cursor: pointer;
            text-align: center;
            border-radius: 6px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .tab-link.active {
            color: #fff;
            background-color: #4e73df;
            box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-section {
            margin-bottom: 30px;
            background-color: #f8f9fc;
            border-radius: 10px;
            padding: 20px;
        }
        
        .form-section:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
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
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: #fff;
            font-size: 14px;
            transition: all 0.2s;
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
            padding: 12px 20px;
            border-radius: 8px;
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
            box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3);
        }
        
        .btn-primary:hover {
            background-color: #3a5ccc;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(78, 115, 223, 0.4);
        }
        
        .btn-secondary {
            background-color: #f8f9fc;
            color: #5a5c69;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background-color: #eaecf4;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: transparent;
            color: #4e73df;
            border: 1px solid #4e73df;
        }
        
        .btn-outline:hover {
            background-color: #4e73df;
            color: #fff;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-icon {
            font-size: 20px;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-danger {
            background-color: #fdeaea;
            color: #e74a3b;
            border-left: 4px solid #e74a3b;
        }
        
        .alert-success {
            background-color: #e6f8f0;
            color: #1cc88a;
            border-left: 4px solid #1cc88a;
        }
        
        .hidden-file-input {
            display: none;
        }
        
        .debug-info {
            background-color: #f8f9fc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            color: #4a5568;
        }
        
        /* Progress Bar */
        .progress-container {
            margin-bottom: 25px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .progress-title {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
        }
        
        .progress-percentage {
            font-size: 14px;
            font-weight: 600;
            color: #4e73df;
        }
        
        .progress-bar-container {
            height: 8px;
            background-color: #eaecf4;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="alert-content">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="profile-container">
                <!-- Profile Header Card -->
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-cover">
                            <div class="profile-header-content">
                                <div class="profile-avatar-container">
                                    <div class="profile-avatar">
                                        <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        <?php endif; ?>
                                        <label for="profile_picture_upload" class="profile-avatar-edit">
                                            <i class="fas fa-camera"></i>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="profile-header-info">
                                    <h3 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h3>
                                    <span class="profile-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-header-details">
                            <div class="profile-quick-info">
                                <div class="quick-info-item">
                                    <div class="quick-info-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="quick-info-text"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                
                                <div class="quick-info-item">
                                    <div class="quick-info-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="quick-info-text"><?php echo htmlspecialchars($team_name); ?></div>
                                </div>
                                
                                <div class="quick-info-item">
                                    <div class="quick-info-icon">
                                        <i class="fas fa-user-tag"></i>
                                    </div>
                                    <div class="quick-info-text"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                
                                <div class="quick-info-item">
                                    <div class="quick-info-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="quick-info-text">Member since <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Completion -->
                <div class="profile-card">
                    <div class="stats-section">
                    <!--    <div class="progress-container">
                            <div class="progress-header">
                                <div class="progress-title">Profile Completion</div>
                                <div class="progress-percentage">75%</div>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: 75%"></div>
                            </div>
                        </div> -->
                        
                        <h3 class="section-title">
                            <i class="fas fa-chart-pie"></i>
                            Lead Statistics
                        </h3>
                        
                        <div class="stats-grid">
                            <div class="stat-card total-card">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-value"><?php echo $lead_stats['total']; ?></div>
                                <div class="stat-label">Total Leads</div>
                            </div>
                            
                            <div class="stat-card hot-card">
                                <div class="stat-icon">
                                    <i class="fas fa-fire"></i>
                                </div>
                                <div class="stat-value"><?php echo $lead_stats['hot']; ?></div>
                                <div class="stat-label">Hot Leads</div>
                            </div>
                            
                            <div class="stat-card warm-card">
                                <div class="stat-icon">
                                    <i class="fas fa-sun"></i>
                                </div>
                                <div class="stat-value"><?php echo $lead_stats['warm']; ?></div>
                                <div class="stat-label">Warm Leads</div>
                            </div>
                            
                            <div class="stat-card cold-card">
                                <div class="stat-icon">
                                    <i class="fas fa-snowflake"></i>
                                </div>
                                <div class="stat-value"><?php echo $lead_stats['cold']; ?></div>
                                <div class="stat-label">Cold Leads</div>
                            </div>
                            
                            <div class="stat-card closed-card">
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $lead_stats['closed']; ?></div>
                                <div class="stat-label">Closed Deals</div>
                            </div>
                            
                            <div class="stat-card lost-card">
                                <div class="stat-icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="stat-value"><?php echo $lead_stats['lost']; ?></div>
                                <div class="stat-label">Lost Deals</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="profile-content">
                    <!-- Left Sidebar - Recent Activities -->
                    <div class="profile-card">
                        <div class="activity-feed">
                            <h3 class="section-title">
                                <i class="fas fa-history"></i>
                                Recent Activities
                            </h3>
                            
                            <?php if (!empty($recent_activities)): ?>
                            <div class="activity-list">
                                <?php foreach ($recent_activities as $activity): ?>
                                <a href="lead-details.php?id=<?php echo htmlspecialchars($activity['lead_id']); ?>" class="activity-item">
                                    <div class="activity-icon">
                                        <?php
                                        $icon = 'fa-comment';
                                        switch ($activity['activity_type']) {
                                            case 'Call':
                                                $icon = 'fa-phone';
                                                break;
                                            case 'Email':
                                                $icon = 'fa-envelope';
                                                break;
                                            case 'Meeting':
                                                $icon = 'fa-handshake';
                                                break;
                                            case 'Presentation':
                                                $icon = 'fa-file-powerpoint';
                                                break;
                                            case 'Follow-up':
                                                $icon = 'fa-clipboard-check';
                                                break;
                                            case 'Status Change':
                                                $icon = 'fa-exchange-alt';
                                                break;
                                            case 'Downpayment Tracker':
                                                $icon = 'fa-money-bill-wave';
                                                break;
                                            case 'Site Tour':
                                                $icon = 'fa-building';
                                                break;
                                            case 'Initial Contact':
                                                $icon = 'fa-user-plus';
                                                break;
                                            case 'Negotiation':
                                                $icon = 'fa-handshake';
                                                break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?php echo htmlspecialchars($activity['activity_type']); ?>: 
                                            <?php echo htmlspecialchars(substr($activity['notes'], 0, 50)); ?>
                                            <?php echo strlen($activity['notes']) > 50 ? '...' : ''; ?>
                                        </div>
                                        <div class="activity-meta">
                                            <div class="activity-client"><?php echo htmlspecialchars($activity['client_name']); ?></div>
                                            <div class="activity-time"><?php echo date('M d, g:i A', strtotime($activity['created_at'])); ?></div>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <p>No recent activities found.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Right Main Content - Profile Form -->
                    <div class="profile-card">
                        <div class="profile-form">
                            <div class="tab-container">
                                <div class="tab-nav">
                                    <div class="tab-link active" data-tab="profile">
                                        <i class="fas fa-user"></i> Profile Information
                                    </div>
                                    <div class="tab-link" data-tab="password">
                                        <i class="fas fa-lock"></i> Change Password
                                    </div>
                                </div>
                                
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                                    <!-- Hidden file input for profile picture -->
                                    <input type="file" id="profile_picture_upload" name="profile_picture" class="hidden-file-input" accept="image/jpeg,image/png,image/gif">
                                    
                                    <div class="tab-content active" id="tab-profile">
                                        <div class="form-section">
                                            <h3 class="section-title">
                                                <i class="fas fa-id-card"></i>
                                                Basic Information
                                            </h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="name">Full Name</label>
                                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="email">Email Address</label>
                                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-section">
                                            <h3 class="section-title">
                                                <i class="fas fa-shield-alt"></i>
                                                Account Information
                                            </h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="username">Username</label>
                                                    <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                                    <div class="help-text">Username cannot be changed.</div>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="role">Role</label>
                                                    <input type="text" id="role" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" disabled>
                                                    <div class="help-text">Role can only be changed by an administrator.</div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="team">Team</label>
                                                    <input type="text" id="team" value="<?php echo htmlspecialchars($team_name); ?>" disabled>
                                                    <div class="help-text">Team assignment can only be changed by an administrator.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="tab-content" id="tab-password">
                                        <div class="form-section">
                                            <h3 class="section-title">
                                                <i class="fas fa-key"></i>
                                                Change Password
                                            </h3>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="current_password">Current Password</label>
                                                    <input type="password" id="current_password" name="current_password">
                                                    <div class="help-text">Enter your current password to verify your identity.</div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="new_password">New Password</label>
                                                    <input type="password" id="new_password" name="new_password">
                                                    <div class="help-text">Password should be at least 8 characters long.</div>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="confirm_password">Confirm New Password</label>
                                                    <input type="password" id="confirm_password" name="confirm_password">
                                                    <div class="help-text">Re-enter your new password to confirm.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabLinks.forEach(link => {
            link.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs
                tabLinks.forEach(tab => tab.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Add active class to current tab
                this.classList.add('active');
                document.getElementById('tab-' + tabId).classList.add('active');
            });
        });
        
        // Profile picture upload
        const profilePictureUpload = document.getElementById('profile_picture_upload');
        const profileAvatar = document.querySelector('.profile-avatar');
        
        profilePictureUpload.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Check if there's already an image
                    let img = profileAvatar.querySelector('img');
                    
                    if (!img) {
                        // Create new image element if it doesn't exist
                        img = document.createElement('img');
                        // Remove text content (the initial)
                        profileAvatar.textContent = '';
                        profileAvatar.appendChild(img);
                    }
                    
                    // Set the image source to the selected file
                    img.src = e.target.result;
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Password validation
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        confirmPasswordInput.addEventListener('input', function() {
            if (newPasswordInput.value !== this.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        newPasswordInput.addEventListener('input', function() {
            if (confirmPasswordInput.value && confirmPasswordInput.value !== this.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        });
        
        // Card hover effects
        const profileCards = document.querySelectorAll('.profile-card');
        profileCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Form section hover effects
        const formSections = document.querySelectorAll('.form-section');
        formSections.forEach(section => {
            section.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.05)';
            });
            
            section.addEventListener('mouseleave', function() {
                this.style.boxShadow = 'none';
            });
        });
    });
    </script>
</body>
</html>