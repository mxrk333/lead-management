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

// Get current user information
$current_user_id = $_SESSION['user_id'];
$current_user = getUserById($current_user_id);

// Check if user has permission to edit users
if ($current_user['role'] != 'admin' && $current_user['role'] != 'manager') {
    header("Location: index.php");
    exit();
}

// Get user ID from URL
if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$edit_user_id = intval($_GET['id']);
$edit_user = getUserById($edit_user_id);

// Check if user exists
if (!$edit_user) {
    $_SESSION['error'] = "User not found.";
    header("Location: users.php");
    exit();
}

// Check if current user has permission to edit this user
if ($current_user['role'] == 'manager') {
    // Get manager's team ID
    $manager_team_id = $current_user['team_id'];
    
    // Check if the user being edited is in the manager's team
    if ($edit_user['team_id'] != $manager_team_id || 
        $edit_user['role'] == 'admin' || 
        $edit_user['role'] == 'manager') {
        $_SESSION['error'] = "You don't have permission to edit this user.";
        header("Location: users.php");
        exit();
    }
} elseif ($current_user['role'] != 'admin') {
    // Non-admin users cannot edit other users
    $_SESSION['error'] = "You don't have permission to edit users.";
    header("Location: users.php");
    exit();
}

// Get all teams for dropdown - Only for admin, managers see their own team
if ($current_user['role'] == 'admin') {
    $teams_query = "SELECT * FROM teams ORDER BY name ASC";
    $teams_result = mysqli_query($conn, $teams_query);
    $teams = [];
    if ($teams_result) {
        while ($row = mysqli_fetch_assoc($teams_result)) {
            $teams[] = $row;
        }
    }
} else if ($current_user['role'] == 'manager') {
    // For managers, only get their team
    $teams_query = "SELECT * FROM teams WHERE id = ? ORDER BY name ASC";
    $teams_stmt = $conn->prepare($teams_query);
    $teams_stmt->bind_param("i", $current_user['team_id']);
    $teams_stmt->execute();
    $teams_result = $teams_stmt->get_result();
    $teams = [];
    if ($teams_result) {
        while ($row = mysqli_fetch_assoc($teams_result)) {
            $teams[] = $row;
        }
    }
    $teams_stmt->close();
}

// Initialize variables for form data and errors
$errors = [];
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $team_id = isset($_POST['team_id']) ? intval($_POST['team_id']) : null;
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $phone = trim($_POST['phone']);

    // For managers, force team_id to their team
    if ($current_user['role'] == 'manager') {
        $team_id = $current_user['team_id'];
    }

    // Validate form data
    if (empty($name)) {
        $errors[] = "Name is required.";
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($username)) {
        $errors[] = "Username is required.";
    } else {
        // Check if username already exists (excluding current user)
        $check_query = "SELECT id FROM users WHERE username = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $username, $edit_user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $errors[] = "Username already exists.";
        }
        $check_stmt->close();
    }

    // Validate role based on current user's role
    if ($current_user['role'] == 'admin') {
        $allowed_roles = ['admin', 'manager', 'supervisor', 'agent'];
    } elseif ($current_user['role'] == 'manager') {
        $allowed_roles = ['supervisor', 'agent'];
        // Force team_id to manager's team for security
        $team_id = $current_user['team_id'];
    }
    
    // Validate the selected role
    if (!in_array($role, $allowed_roles)) {
        $errors[] = "Invalid role selected.";
    }

    // Password validation (only if password field is not empty)
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
    }

    // If no errors, update user
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Build the update query
            $update_fields = [
                "name = ?",
                "email = ?",
                "username = ?",
                "role = ?",
                "phone = ?",
                "team_id = ?" // Always include team_id in the update
            ];
            $params = [$name, $email, $username, $role, $phone, $team_id];
            $types = "sssssi";
            
            // Add password if it's set
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_fields[] = "password = ?";
                $params[] = $hashed_password;
                $types .= "s";
            }
            
            // Add user_id to params
            $params[] = $edit_user_id;
            $types .= "i";
            
            // Create the update query
            $update_query = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            
            // Prepare and execute the update
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param($types, ...$params);
            
            if ($update_stmt->execute()) {
                // Only update session data if the user is editing their own profile
                if ($edit_user_id == $current_user_id) {
                    // Get fresh user data
                    $updated_user = getUserById($edit_user_id);
                    if ($updated_user) {
                        $_SESSION['user_name'] = $updated_user['name'];
                        $_SESSION['user_email'] = $updated_user['email'];
                        $_SESSION['user_role'] = $updated_user['role'];
                        if (isset($updated_user['team_id'])) {
                            $_SESSION['user_team_id'] = $updated_user['team_id'];
                        }
                    }
                }
                
                $conn->commit();
                $success_message = "User updated successfully.";
                
                // Refresh user data
                $edit_user = getUserById($edit_user_id);
            } else {
                throw new Exception("Error updating user: " . $update_stmt->error);
            }
            
            $update_stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

// Set the user variable for the header and sidebar to be the current user
$user = $current_user;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/users.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Edit User Form Styles */
        .edit-user-form {
            background-color: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            min-width: 250px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #fff;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1);
            outline: none;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
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
        
        .form-section-title {
            font-size: 16px;
            color: #4a5568;
            font-weight: 600;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .password-info {
            font-size: 12px;
            color: #858796;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .form-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-header">
                <h2>Edit User</h2>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="edit-user-form">
                <form method="POST" action="">
                    <div class="form-section-title">Basic Information</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($edit_user['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo !empty($edit_user['phone']) ? htmlspecialchars($edit_user['phone']) : ''; ?>" pattern="[0-9]{11}" maxlength="11" placeholder="09123456789">
                            <small class="form-text text-muted">Enter 11-digit phone number</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required <?php echo ($current_user['role'] != 'admin' && $edit_user['role'] == 'admin') ? 'disabled' : ''; ?>>
                                <?php if ($current_user['role'] == 'admin'): ?>
                                    <option value="admin" <?php echo ($edit_user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    <option value="manager" <?php echo ($edit_user['role'] == 'manager') ? 'selected' : ''; ?>>Manager</option>
                                    <option value="supervisor" <?php echo ($edit_user['role'] == 'supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                                    <option value="agent" <?php echo ($edit_user['role'] == 'agent') ? 'selected' : ''; ?>>Agent</option>
                                <?php elseif ($current_user['role'] == 'manager'): ?>
                                    <option value="supervisor" <?php echo ($edit_user['role'] == 'supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                                     <option value="agent" <?php echo ($edit_user['role'] == 'agent') ? 'selected' : ''; ?>>Agent</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="team">Team</label>
                            <select id="team" name="team_id" <?php echo ($current_user['role'] == 'manager') ? 'readonly disabled' : ''; ?>>
                                <option value="">No Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>" <?php echo ($edit_user['team_id'] == $team['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($current_user['role'] == 'manager'): ?>
                                <input type="hidden" name="team_id" value="<?php echo $current_user['team_id']; ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-section-title">Change Password</div>
                    <p class="password-info">Leave blank to keep the current password</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password" minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="8">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                // Remove any non-digit characters
                this.value = this.value.replace(/\D/g, '');
                
                // Limit to 11 digits
                if (this.value.length > 11) {
                    this.value = this.value.slice(0, 11);
                }
            });
        }
    });
    </script>

    <script src="assets/js/script.js"></script>
</body>
</html>