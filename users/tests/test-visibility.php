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

echo "<h1>🔍 MEMO VISIBILITY TEST</h1>";
echo "<div style='background: #f0f0f0; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
echo "<h2>Current User Info:</h2>";
echo "<strong>User ID:</strong> {$user_id}<br>";
echo "<strong>Name:</strong> " . htmlspecialchars($user['name']) . "<br>";
echo "<strong>Team ID:</strong> {$user['team_id']}<br>";
echo "<strong>Team Name:</strong> " . htmlspecialchars($user['team_name']) . "<br>";
echo "<strong>Role:</strong> " . htmlspecialchars($user['role']) . "<br>";
echo "<strong>Is Admin/Manager:</strong> " . (($user['role'] === 'admin' || $user['role'] === 'manager') ? 'YES' : 'NO') . "<br>";
echo "</div>";

// Show ALL memos in database with detailed analysis
echo "<h2>📋 ALL MEMOS IN DATABASE:</h2>";
$all_memos = $conn->query("
    SELECT m.id, m.title, m.visible_to_all, m.created_by, m.team_id,
           u.name as creator_name, t.name as creator_team_name
    FROM memos m 
    JOIN users u ON m.created_by = u.id 
    JOIN teams t ON m.team_id = t.id 
    ORDER BY m.id DESC
");

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background: #333; color: white;'>
        <th>Memo ID</th>
        <th>Title</th>
        <th>Visible to All</th>
        <th>Creator</th>
        <th>Creator Team</th>
        <th>Team Assignments</th>
        <th>SHOULD YOU SEE IT?</th>
      </tr>";

while ($memo = $all_memos->fetch_assoc()) {
    $should_see = "❌ NO";
    $reason = "";
    
    // Check visibility logic step by step
    if ($user['role'] === 'admin' || $user['role'] === 'manager') {
        $should_see = "✅ YES";
        $reason = "You are admin/manager";
    } elseif ($memo['visible_to_all'] == 1) {
        $should_see = "✅ YES";
        $reason = "Public memo (visible_to_all = 1)";
    } elseif ($memo['created_by'] == $user_id) {
        $should_see = "✅ YES";
        $reason = "You created this memo";
    } else {
        // Check team assignments
        $team_check = $conn->prepare("SELECT team_id FROM memo_team_visibility WHERE memo_id = ? AND team_id = ?");
        $team_check->bind_param("ii", $memo['id'], $user['team_id']);
        $team_check->execute();
        $team_result = $team_check->get_result();
        
        if ($team_result->num_rows > 0) {
            $should_see = "✅ YES";
            $reason = "Assigned to your team";
        } else {
            $reason = "Not public, not yours, not assigned to your team";
        }
    }
    
    // Get team assignments
    $assignments_query = $conn->prepare("
        SELECT t.name 
        FROM memo_team_visibility mtv 
        JOIN teams t ON mtv.team_id = t.id 
        WHERE mtv.memo_id = ?
    ");
    $assignments_query->bind_param("i", $memo['id']);
    $assignments_query->execute();
    $assignments_result = $assignments_query->get_result();
    
    $assigned_teams = [];
    while ($team = $assignments_result->fetch_assoc()) {
        $assigned_teams[] = $team['name'];
    }
    $team_assignments = empty($assigned_teams) ? "None" : implode(", ", $assigned_teams);
    
    $row_color = ($should_see === "✅ YES") ? "#e8f5e8" : "#ffe8e8";
    
    echo "<tr style='background: {$row_color};'>";
    echo "<td>{$memo['id']}</td>";
    echo "<td>" . htmlspecialchars($memo['title']) . "</td>";
    echo "<td>" . ($memo['visible_to_all'] ? 'YES' : 'NO') . "</td>";
    echo "<td>" . htmlspecialchars($memo['creator_name']) . "</td>";
    echo "<td>" . htmlspecialchars($memo['creator_team_name']) . "</td>";
    echo "<td>{$team_assignments}</td>";
    echo "<td><strong>{$should_see}</strong><br><small>{$reason}</small></td>";
    echo "</tr>";
}
echo "</table>";

// Now test the actual query used in memos.php
echo "<h2>🔬 TESTING ACTUAL QUERY FROM MEMOS.PHP:</h2>";

$isAuthorized = ($user['role'] === 'admin' || $user['role'] === 'manager');

if ($isAuthorized) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "⚠️ <strong>You are admin/manager - you will see ALL memos</strong>";
    echo "</div>";
    
    $query = "SELECT m.id, m.title, m.visible_to_all, m.created_by, u.name as creator_name
              FROM memos m
              INNER JOIN users u ON m.created_by = u.id
              ORDER BY m.created_at DESC";
    $stmt = $conn->prepare($query);
} else {
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "🔒 <strong>You are regular user - visibility restrictions should apply</strong>";
    echo "</div>";
    
    $query = "SELECT m.id, m.title, m.visible_to_all, m.created_by, u.name as creator_name
              FROM memos m
              INNER JOIN users u ON m.created_by = u.id
              WHERE (
                  m.visible_to_all = 1 
                  OR m.created_by = ?
                  OR EXISTS (
                      SELECT 1 FROM memo_team_visibility mtv 
                      WHERE mtv.memo_id = m.id AND mtv.team_id = ?
                  )
              )
              ORDER BY m.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $user['team_id']);
}

echo "<h3>Query being executed:</h3>";
echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
echo htmlspecialchars($query);
echo "</pre>";

if (!$isAuthorized) {
    echo "<p><strong>Parameters:</strong> user_id = {$user_id}, team_id = {$user['team_id']}</p>";
}

$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Results from this query:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background: #333; color: white;'>
        <th>Memo ID</th>
        <th>Title</th>
        <th>Visible to All</th>
        <th>Creator</th>
        <th>Why Visible?</th>
      </tr>";

$found_memos = [];
while ($row = $result->fetch_assoc()) {
    $found_memos[] = $row;
    
    $why_visible = "";
    if ($isAuthorized) {
        $why_visible = "Admin/Manager access";
    } elseif ($row['visible_to_all'] == 1) {
        $why_visible = "Public memo";
    } elseif ($row['created_by'] == $user_id) {
        $why_visible = "You created it";
    } else {
        $why_visible = "Assigned to your team";
    }
    
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
    echo "<td>" . ($row['visible_to_all'] ? 'YES' : 'NO') . "</td>";
    echo "<td>" . htmlspecialchars($row['creator_name']) . "</td>";
    echo "<td>{$why_visible}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>📊 SUMMARY:</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>";
echo "<p><strong>Total memos found by query:</strong> " . count($found_memos) . "</p>";

if (!$isAuthorized) {
    echo "<p><strong>⚠️ If you see memos you shouldn't, there's a bug in the query logic!</strong></p>";
    
    // Check for problematic memos
    $problematic = [];
    foreach ($found_memos as $memo) {
        if ($memo['visible_to_all'] == 0 && $memo['created_by'] != $user_id) {
            // This should only be visible if assigned to user's team
            $team_check = $conn->prepare("SELECT COUNT(*) as count FROM memo_team_visibility WHERE memo_id = ? AND team_id = ?");
            $team_check->bind_param("ii", $memo['id'], $user['team_id']);
            $team_check->execute();
            $team_result = $team_check->get_result()->fetch_assoc();
            
            if ($team_result['count'] == 0) {
                $problematic[] = $memo;
            }
        }
    }
    
    if (!empty($problematic)) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>🚨 PROBLEMATIC MEMOS (YOU SHOULDN'T SEE THESE):</h3>";
        foreach ($problematic as $memo) {
            echo "<p>Memo ID {$memo['id']}: {$memo['title']} - Not public, not yours, not assigned to your team!</p>";
        }
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>✅ VISIBILITY LOOKS CORRECT!</h3>";
        echo "<p>All memos you can see are either public, created by you, or assigned to your team.</p>";
        echo "</div>";
    }
}
echo "</div>";

echo "<hr>";
echo "<p><a href='memos.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back to Memos</a></p>";
?>
