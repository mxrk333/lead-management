<?php
session_start();
require_once 'config/database.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $reset_code = $_POST['reset_code'];
    
    $error = '';
    $success = '';
    
    
    $valid_reset_code = 'boboka'; 
    
    if ($reset_code != $valid_reset_code) {
        $error = "Invalid reset code. Please contact the system administrator.";
    } elseif ($new_password != $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Update password in database
        $conn = getDbConnection();
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        $stmt->bind_param("ss", $hashed_password, $username);
        
        if ($stmt->execute()) {
            $success = "Password has been reset successfully. You can now <a href='login.php'>login</a> with your new password.";
        } else {
            $error = "Failed to reset password. Please try again.";
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Real Estate Lead Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>Reset Password</h1>
            <p>Enter your username and new password</p>
        </div>
        
        <?php if (isset($error) && $error): ?>
        <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success) && $success): ?>
        <div class="success-message"><?php echo $success; ?></div>
        <?php else: ?>
        
        <form method="POST" action="reset-password.php" class="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="reset_code">Reset Code</label>
                <input type="text" id="reset_code" name="reset_code" required>
                <small>Contact the system administrator for the reset code.</small>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn-login">Reset Password</button>
        </form>
        
        <div class="login-footer">
            <p>Remember your password? <a href="login.php">Login here</a></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
