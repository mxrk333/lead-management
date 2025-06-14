<?php
// This is a special admin password reset script
// It should be deleted after use for security reasons

require_once 'config/database.php';

// Set a new password for the admin user
function resetAdminPassword() {
    $conn = getDbConnection();
    
    // New password: admin123
    $new_password = 'admin123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->bind_param("s", $hashed_password);
    
    if ($stmt->execute()) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px;'>";
        echo "<h3>Admin Password Reset Successfully</h3>";
        echo "<p>The admin password has been reset to: <strong>admin123</strong></p>";
        echo "<p>Please <a href='login.php'>login</a> with these credentials and change your password immediately.</p>";
        echo "<p><strong>IMPORTANT:</strong> Delete this file (admin-reset.php) from your server immediately for security reasons.</p>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
        echo "<h3>Password Reset Failed</h3>";
        echo "<p>Error: " . $stmt->error . "</p>";
        echo "</div>";
    }
    
    $stmt->close();
    $conn->close();
}

// Execute the reset
resetAdminPassword();
?>
