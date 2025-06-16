<?php
session_start();

// Simple test to check if notifications are working
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 15; // Use your test user ID
    echo "<p>Set test user_id to 15 for testing</p>";
}

// Include your config and functions
require_once 'config/config.php'; // Adjust path as needed

echo "<h2>Notification System Test</h2>";

// Test database connection
try {
    $conn = getDbConnection();
    echo "<p>✅ Database connection successful</p>";
    
    // Test user query
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT id, name, last_notification_read FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "<p>✅ User found: " . htmlspecialchars($row['name']) . "</p>";
        echo "<p>Last notification read: " . ($row['last_notification_read'] ? $row['last_notification_read'] : 'Never') . "</p>";
    } else {
        echo "<p>❌ User not found</p>";
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test mark-notification-read.php
echo "<h3>Test Mark Notifications</h3>";
echo "<button onclick='testMarkNotifications()'>Test Mark All as Read</button>";
echo "<div id='test-result'></div>";

?>

<script>
function testMarkNotifications() {
    const resultDiv = document.getElementById('test-result');
    resultDiv.innerHTML = 'Testing...';
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'mark-notification-read.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resultDiv.innerHTML = '<pre>' + JSON.stringify(response, null, 2) + '</pre>';
                } catch (e) {
                    resultDiv.innerHTML = 'Response: ' + xhr.responseText;
                }
            } else {
                resultDiv.innerHTML = 'Error: ' + xhr.status + ' - ' + xhr.responseText;
            }
        }
    };
    xhr.send('action=mark_all_read');
}
</script>
