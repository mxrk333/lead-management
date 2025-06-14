<?php
session_start();
require_once 'config.php';

// Set proper content type
header('Content-Type: text/html; charset=utf-8');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<h1>Error: Not logged in</h1>";
    echo "<p>Please log in first.</p>";
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $conn = getDbConnection();
    
    // Get user information
    $user = getUserById($user_id);
    
    // Get notification read timestamp
    $last_read = null;
    $user_query = "SELECT last_notification_read FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_query);
    if ($user_stmt) {
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($row = $user_result->fetch_assoc()) {
            $last_read = $row['last_notification_read'];
        }
        $user_stmt->close();
    }
    
    // Get recent notifications
    $notifications = [];
    $activity_query = "
        SELECT 
            la.id,
            la.activity_type,
            la.notes,
            la.created_at,
            l.client_name,
            l.id as lead_id,
            u.name as user_name,
            'activity' as notification_type
        FROM lead_activities la
        JOIN leads l ON la.lead_id = l.id
        JOIN users u ON la.user_id = u.id
        WHERE (l.user_id = ? OR la.user_id = ?)
        ORDER BY la.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($activity_query);
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        $stmt->close();
    }
    
    // Get memo notifications
    $memo_query = "
        SELECT 
            m.id,
            'Memo' as activity_type,
            CONCAT('New memo: ', m.title) as notes,
            m.created_at,
            'System' as client_name,
            m.id as lead_id,
            u.name as user_name,
            'memo' as notification_type
        FROM memos m
        JOIN users u ON m.created_by = u.id
        WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY m.created_at DESC
        LIMIT 5
    ";
    
    $memo_stmt = $conn->prepare($memo_query);
    if ($memo_stmt) {
        $memo_stmt->execute();
        $memo_result = $memo_stmt->get_result();
        
        while ($row = $memo_result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $memo_stmt->close();
    }
    
    // Sort all notifications by created_at descending
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Limit to 10 notifications
    $notifications = array_slice($notifications, 0, 10);
    
    // Mark notifications as read/unread based on last_read timestamp
    foreach ($notifications as &$notification) {
        $notification['is_read'] = true; // Default to read
        
        if ($last_read) {
            // Convert both timestamps to Unix timestamps for comparison
            $notification_time = strtotime($notification['created_at']);
            $last_read_time = strtotime($last_read);
            
            // If notification was created AFTER the last read time, it's unread
            if ($notification_time > $last_read_time) {
                $notification['is_read'] = false;
            }
        } else {
            // If no last_read timestamp, consider all notifications as unread
            $notification['is_read'] = false;
        }
    }
    
    $conn->close();
    
    // Count unread notifications
    $unread_count = count(array_filter($notifications, function($n) {
        return !$n['is_read'];
    }));
    
    // Display the results
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Notification Status</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .container { max-width: 800px; margin: 0 auto; }
            .card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 20px; }
            .header { background: #f5f5f5; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
            .notification { border-bottom: 1px solid #eee; padding: 10px 0; }
            .notification.unread { background-color: #f0f7ff; }
            .badge { background: #ff4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 12px; }
            .timestamp { color: #777; font-size: 12px; }
            .button { background: #4f46e5; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
            .button:hover { background: #4338ca; }
            pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>Notification Status</h1>
            
            <div class='card'>
                <h2>User Information</h2>
                <p><strong>User ID:</strong> {$user_id}</p>
                <p><strong>Name:</strong> {$user['name']}</p>
                <p><strong>Email:</strong> {$user['email']}</p>
                <p><strong>Last Notification Read:</strong> " . ($last_read ? $last_read : 'Never') . "</p>
                <p><strong>Session Last Read:</strong> " . (isset($_SESSION['last_notification_read']) ? $_SESSION['last_notification_read'] : 'Not set') . "</p>
                <p><strong>Unread Count:</strong> <span class='badge'>{$unread_count}</span></p>
                
                <form method='post' action='mark-notifications-read.php' id='markReadForm'>
                    <input type='hidden' name='action' value='mark_all_read'>
                    <button type='button' class='button' onclick='markAllRead()'>Mark All as Read</button>
                </form>
                <div id='result'></div>
            </div>
            
            <div class='card'>
                <h2>Recent Notifications</h2>";
                
    if (count($notifications) > 0) {
        foreach ($notifications as $notification) {
            $readClass = $notification['is_read'] ? 'read' : 'unread';
            $timestamp = date('M j, Y g:i A', strtotime($notification['created_at']));
            
            echo "<div class='notification {$readClass}'>
                <h3>{$notification['activity_type']} - {$notification['client_name']}</h3>
                <p>{$notification['notes']}</p>
                <p class='timestamp'>By {$notification['user_name']} on {$timestamp}</p>
                <p><strong>Status:</strong> " . ($notification['is_read'] ? 'Read' : 'Unread') . "</p>
            </div>";
        }
    } else {
        echo "<p>No recent notifications found.</p>";
    }
    
    echo "</div>
        </div>
        
        <script>
        function markAllRead() {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'mark-notifications-read.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    document.getElementById('result').innerHTML = 
                        '<h3>Response Status: ' + xhr.status + '</h3>' +
                        '<pre>' + xhr.responseText + '</pre>' +
                        '<p>Reloading page in 3 seconds...</p>';
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 3000);
                }
            };
            xhr.send('action=mark_all_read');
        }
        </script>
    </body>
    </html>";
    
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>{$e->getMessage()}</p>";
}
?>
