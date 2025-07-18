<?php
session_start();

// Simple test file to check if mark-notifications-read.php is working
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Set a test user ID
}

echo "<h2>Test Mark Notifications as Read</h2>";
echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";

// Include your database connection
require_once 'config.php'; // Adjust path as needed

try {
    $conn = getDbConnection();
    
    // Check current last_notification_read value
    $user_id = $_SESSION['user_id'];
    $query = "SELECT last_notification_read FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    echo "<p>Current last_notification_read: " . ($user['last_notification_read'] ?? 'NULL') . "</p>";
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<button onclick="testMarkAsRead()">Test Mark All as Read</button>
<div id="result"></div>

<script>
function testMarkAsRead() {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'mark-notifications-read.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            document.getElementById('result').innerHTML = 
                '<h3>Response Status: ' + xhr.status + '</h3>' +
                '<pre>' + xhr.responseText + '</pre>';
        }
    };
    xhr.send('action=mark_all_read');
}
</script>
