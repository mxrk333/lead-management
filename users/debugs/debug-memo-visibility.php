<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$conn = getDbConnection();

echo "<h1>Memo Visibility Debug</h1>";
echo "<p><strong>Current User:</strong> " . htmlspecialchars($user['name']) . " (ID: {$user_id})</p>";
echo "<p><strong>User Team:</strong> " . htmlspecialchars($user['team_name']) . " (ID: {$user['team_id']})</p>";
echo "<p><strong>User Role:</strong> " . htmlspecialchars($user['role']) . "</p>";

echo "<hr>";

// Show all memos in database
echo "<h2>All Memos in Database</h2>";
$all_memos_query = "SELECT m.id, m.title, m.visible_to_all, m.created_by, m.team_id, 
                           u.name as creator_name, t.name as creator_team
                    FROM memos m 
                    JOIN users u ON m.created_by = u.id 
                    JOIN teams t ON m.team_id = t.id 
                    ORDER BY m.id DESC";
$all_memos_result = $conn->query($all_memos_query);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Title</th><th>Visible to All</th><th>Creator</th><th>Creator Team</th><th>Should You See It?</th></tr>";

while ($memo = $all_memos_result->fetch_assoc()) {
    $should_see = "NO";
    
    if ($memo['visible_to_all'] == 1) {
        $should_see = "YES (Public)";
    } elseif ($memo['created_by'] == $user_id) {
        $should_see = "YES (You created it)";
    } else {
        // Check if assigned to user's team
        $team_check = $conn->prepare("SELECT COUNT(*) as count FROM memo_team_visibility WHERE memo_id = ? AND team_id = ?");
        $team_check->bind_param("ii", $memo['id'], $user['team_id']);
        $team_check->execute();
        $team_result = $team_check->get_result()->fetch_assoc();
        
        if ($team_result['count'] > 0) {
            $should_see = "YES (Assigned to your team)";
        }
    }
    
    echo "<tr>";
    echo "<td>" . $memo['id'] . "</td>";
    echo "<td>" . htmlspecialchars($memo['title']) . "</td>";
    echo "<td>" . ($memo['visible_to_all'] ? 'Yes' : 'No') . "</td>";
    echo "<td>" . htmlspecialchars($memo['creator_name']) . "</td>";
    echo "<td>" . htmlspecialchars($memo['creator_team']) . "</td>";
    echo "<td><strong>" . $should_see . "</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";

// Show memo_team_visibility table
echo "<h2>Memo Team Visibility Assignments</h2>";
$visibility_query = "SELECT mtv.memo_id, mtv.team_id, m.title, t.name as team_name
                     FROM memo_team_visibility mtv
                     JOIN memos m ON mtv.memo_id = m.id
                     JOIN teams t ON mtv.team_id = t.id
                     ORDER BY mtv.memo_id DESC";
$visibility_result = $conn->query($visibility_query);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Memo ID</th><th>Memo Title</th><th>Assigned Team</th><th>Your Team?</th></tr>";

while ($vis = $visibility_result->fetch_assoc()) {
    $is_your_team = ($vis['team_id'] == $user['team_id']) ? "YES" : "NO";
    echo "<tr>";
    echo "<td>" . $vis['memo_id'] . "</td>";
    echo "<td>" . htmlspecialchars($vis['title']) . "</td>";
    echo "<td>" . htmlspecialchars($vis['team_name']) . "</td>";
    echo "<td><strong>" . $is_your_team . "</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><a href='memos.php'>Back to Memos</a></p>";
?>
