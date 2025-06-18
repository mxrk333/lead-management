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

// Check if user has permission to view teams
if ($user['role'] != 'admin' && $user['role'] != 'manager') {
    header("Location: index.php");
    exit();
}

// Redirect managers directly to their team view
if ($user['role'] == 'manager' && !isset($_GET['view'])) {
    header("Location: teams.php?view=" . $user['team_id']);
    exit();
}

// Process team actions (add, edit, delete)
$success_message = '';
$error_message = '';

// Add new team - Only admin can add teams
if (isset($_POST['add_team']) && $user['role'] == 'admin') {
    $team_name = trim($_POST['team_name']);
    
    if (empty($team_name)) {
        $error_message = "Team name is required.";
    } else {
        // Check if team name already exists - Using prepared statement to prevent SQL injection
        $check_stmt = $conn->prepare("SELECT id FROM teams WHERE name = ?");
        $check_stmt->bind_param("s", $team_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Team name already exists.";
        } else {
            // Using prepared statement for insert
            $insert_stmt = $conn->prepare("INSERT INTO teams (name) VALUES (?)");
            $insert_stmt->bind_param("s", $team_name);
            
            if ($insert_stmt->execute()) {
                $success_message = "Team added successfully.";
            } else {
                $error_message = "Error adding team: " . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Edit team - Only admin can edit teams
if (isset($_POST['edit_team']) && $user['role'] == 'admin') {
    $team_id = intval($_POST['team_id']);
    $team_name = trim($_POST['team_name']);
    
    if (empty($team_name)) {
        $error_message = "Team name is required.";
    } else {
        // Check if team name already exists (excluding current team) - Using prepared statement
        $check_stmt = $conn->prepare("SELECT id FROM teams WHERE name = ? AND id != ?");
        $check_stmt->bind_param("si", $team_name, $team_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Team name already exists.";
        } else {
            // Using prepared statement for update
            $update_stmt = $conn->prepare("UPDATE teams SET name = ? WHERE id = ?");
            $update_stmt->bind_param("si", $team_name, $team_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Team updated successfully.";
            } else {
                $error_message = "Error updating team: " . $conn->error;
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}

// Delete team - Only admin can delete teams
if (isset($_GET['delete']) && $user['role'] == 'admin') {
    $team_id = intval($_GET['delete']);
    
    // Check if team has members - Using prepared statement
    $check_stmt = $conn->prepare("SELECT COUNT(*) as member_count FROM users WHERE team_id = ?");
    $check_stmt->bind_param("i", $team_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $member_count = 0;
    
    if ($row = $check_result->fetch_assoc()) {
        $member_count = $row['member_count'];
    }
    
    if ($member_count > 0) {
        $error_message = "Cannot delete team with active members. Please transfer members first.";
    } else {
        // Using prepared statement for delete
        $delete_stmt = $conn->prepare("DELETE FROM teams WHERE id = ?");
        $delete_stmt->bind_param("i", $team_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Team deleted successfully.";
        } else {
            $error_message = "Error deleting team: " . $conn->error;
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
}

// Add user to team - Admin can add to any team, manager only to their team
if (isset($_POST['add_user_to_team'])) {
    $team_id = intval($_POST['team_id']);
    
    // Check if manager has permission for this team
    if ($user['role'] == 'manager' && $user['team_id'] != $team_id) {
        $error_message = "You don't have permission to add members to this team.";
    } else {
        $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
        
        if (empty($user_ids)) {
            $error_message = "Please select at least one user to add to the team.";
        } else {
            $success_count = 0;
            $error_count = 0;
            
            // Using prepared statement for update
            $update_stmt = $conn->prepare("UPDATE users SET team_id = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $team_id, $user_id_param);
            
            foreach ($user_ids as $user_id_param) {
                $user_id_param = intval($user_id_param);
                if ($update_stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            
            $update_stmt->close();
            
            if ($success_count > 0) {
                $success_message = "$success_count user(s) added to team successfully.";
                if ($error_count > 0) {
                    $error_message = "$error_count user(s) could not be added to the team.";
                }
            } else {
                $error_message = "Error adding users to team.";
            }
        }
    }
}

// Transfer user from team - Admin can transfer between any teams, manager only from their team
if (isset($_POST['transfer_member'])) {
    $user_id_to_transfer = intval($_POST['user_id']);
    $current_team_id = intval($_POST['current_team_id']);
    
    // Check if manager has permission for this team
    if ($user['role'] == 'manager' && $user['team_id'] != $current_team_id) {
        $error_message = "You don't have permission to transfer members from this team.";
    } else {
        $new_team_id = !empty($_POST['new_team_id']) ? intval($_POST['new_team_id']) : NULL;
        
        // Check if user exists and belongs to the current team
        $check_stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ? AND team_id = ?");
        $check_stmt->bind_param("ii", $user_id_to_transfer, $current_team_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $user_to_transfer = $check_result->fetch_assoc();
            
            // Using prepared statement for update
            $update_stmt = $conn->prepare("UPDATE users SET team_id = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_team_id, $user_id_to_transfer);
            
            if ($update_stmt->execute()) {
                if ($new_team_id) {
                    // Get new team name
                    $team_stmt = $conn->prepare("SELECT name FROM teams WHERE id = ?");
                    $team_stmt->bind_param("i", $new_team_id);
                    $team_stmt->execute();
                    $team_result = $team_stmt->get_result();
                    $new_team_name = ($team_result && $row = $team_result->fetch_assoc()) ? $row['name'] : "another team";
                    $team_stmt->close();
                    
                    $success_message = "User " . htmlspecialchars($user_to_transfer['name']) . " transferred to " . htmlspecialchars($new_team_name) . " successfully.";
                } else {
                    $success_message = "User " . htmlspecialchars($user_to_transfer['name']) . " removed from team successfully.";
                }
            } else {
                $error_message = "Error transferring user: " . $conn->error;
            }
            $update_stmt->close();
        } else {
            $error_message = "User not found or does not belong to this team.";
        }
        $check_stmt->close();
    }
}

// Delete team member - Admin can delete from any team, manager only from their team
if (isset($_POST['delete_member'])) {
    $user_id_to_delete = intval($_POST['user_id']);
    $team_id = intval($_POST['team_id']);
    
    // Check if manager has permission for this team
    if ($user['role'] == 'manager' && $user['team_id'] != $team_id) {
        $error_message = "You don't have permission to remove members from this team.";
    } else {
        // Check if user exists and belongs to the team
        $check_stmt = $conn->prepare("SELECT id, name, role FROM users WHERE id = ? AND team_id = ?");
        $check_stmt->bind_param("ii", $user_id_to_delete, $team_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $user_to_delete = $check_result->fetch_assoc();
            
            // Prevent managers from deleting other managers or admins
            if ($user['role'] == 'manager' && ($user_to_delete['role'] == 'manager' || $user_to_delete['role'] == 'admin')) {
                $error_message = "You cannot remove managers or admins from the team.";
            } else {
                // Remove user from team (set team_id to NULL)
                $update_stmt = $conn->prepare("UPDATE users SET team_id = NULL WHERE id = ?");
                $update_stmt->bind_param("i", $user_id_to_delete);
                
                if ($update_stmt->execute()) {
                    $success_message = "User " . htmlspecialchars($user_to_delete['name']) . " removed from team successfully.";
                } else {
                    $error_message = "Error removing user from team: " . $conn->error;
                }
                $update_stmt->close();
            }
        } else {
            $error_message = "User not found or does not belong to this team.";
        }
        $check_stmt->close();
    }
}

// Enhanced Create new user with role selection and stricter limitations
if (isset($_POST['create_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $phone = trim($_POST['phone']);
    $role = trim($_POST['role']); // Get selected role
    $team_id = isset($_POST['team_id']) ? intval($_POST['team_id']) : NULL;
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($username) || empty($password) || empty($phone) || empty($role)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif (!preg_match("/^[0-9]{11}$/", $phone)) {
        $error_message = "Phone number must be 11 digits.";
    } elseif (!in_array($role, ['admin', 'manager', 'supervisor', 'agent'])) {
        $error_message = "Invalid role selected.";
    } else {
        // Enhanced role permissions - Strict validation for managers
        if ($user['role'] == 'manager') {
            // Managers can ONLY create agents and supervisors
            if ($role == 'admin' || $role == 'manager') {
                $error_message = "Access Denied: Managers cannot create users with Admin or Manager roles. You can only create Agents and Supervisors.";
            }
        } elseif ($user['role'] == 'supervisor') {
            // Supervisors can only create agents (if we want to allow this)
            if ($role != 'agent') {
                $error_message = "Access Denied: Supervisors can only create Agent accounts.";
            }
        } elseif ($user['role'] != 'admin') {
            // Only admins can create any role
            $error_message = "Access Denied: You don't have permission to create user accounts.";
        }
        
        if (empty($error_message)) {
            // Check if username or email already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Username or email already exists.";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user with selected role
                $insert_stmt = $conn->prepare("INSERT INTO users (name, email, username, password, role, team_id, phone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $insert_stmt->bind_param("sssssss", $name, $email, $username, $hashed_password, $role, $team_id, $phone);
                
                if ($insert_stmt->execute()) {
                    $success_message = "New " . ucfirst($role) . " created successfully.";
                } else {
                    $error_message = "Error creating user: " . $conn->error;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    }
}

// Get teams based on user role
$teams = [];
if ($user['role'] == 'admin') {
    // Admin can see all teams
    $teams_stmt = $conn->prepare("SELECT t.*, COUNT(u.id) as member_count 
                    FROM teams t 
                    LEFT JOIN users u ON t.id = u.team_id 
                    GROUP BY t.id 
                    ORDER BY t.name ASC");
} else {
    // Manager can only see their team
    $teams_stmt = $conn->prepare("SELECT t.*, COUNT(u.id) as member_count 
                    FROM teams t 
                    LEFT JOIN users u ON t.id = u.team_id 
                    WHERE t.id = ?
                    GROUP BY t.id 
                    ORDER BY t.name ASC");
    $teams_stmt->bind_param("i", $user['team_id']);
}
$teams_stmt->execute();
$teams_result = $teams_stmt->get_result();

if ($teams_result) {
    while ($row = $teams_result->fetch_assoc()) {
        $teams[] = $row;
    }
}
$teams_stmt->close();

// Get all teams for transfer dropdown - Only for admin
$all_teams = [];
if ($user['role'] == 'admin') {
    $all_teams_stmt = $conn->prepare("SELECT id, name FROM teams ORDER BY name ASC");
    $all_teams_stmt->execute();
    $all_teams_result = $all_teams_stmt->get_result();

    if ($all_teams_result) {
        while ($row = $all_teams_result->fetch_assoc()) {
            $all_teams[] = $row;
        }
    }
    $all_teams_stmt->close();
}

// Get team members if viewing a specific team
$selected_team = null;
$team_members = [];

if (isset($_GET['view'])) {
    $team_id = intval($_GET['view']);
    
    // Get team details
    $team_stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $team_stmt->bind_param("i", $team_id);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    $selected_team = $team_result->fetch_assoc();
    $team_stmt->close();
    
    // Check if team exists and user has permission to view it
    if (!$selected_team) {
        $_SESSION['error'] = "Team not found.";
        header("Location: teams.php");
        exit();
    }
    
    // Check if manager has permission to view this team
    if ($user['role'] == 'manager' && $user['team_id'] != $team_id) {
        header("Location: teams.php");
        exit();
    }
    
    // Get team members
    $members_stmt = $conn->prepare("SELECT id, name, email, phone, role, username, created_at FROM users WHERE team_id = ? ORDER BY role, name");
    $members_stmt->bind_param("i", $team_id);
    $members_stmt->execute();
    $members_result = $members_stmt->get_result();
    $team_members = [];
    while ($row = $members_result->fetch_assoc()) {
        $team_members[] = $row;
    }
    $members_stmt->close();
}

// Get available users (not in any team or in a different team)
$available_users = [];
if ($selected_team && ($user['role'] == 'admin' || $user['role'] == 'manager')) {
    $users_stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE (team_id IS NULL OR team_id != ?) AND role != 'admin' ORDER BY name");
    $users_stmt->bind_param("i", $team_id);
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    
    if ($users_result) {
        while ($row = $users_result->fetch_assoc()) {
            $available_users[] = $row;
        }
    }
    $users_stmt->close();
}

// Get team performance data
$team_performance = [];

foreach ($teams as $team) {
    $team_id = $team['id'];
    
    // Get lead counts using prepared statement
    $lead_stmt = $conn->prepare("SELECT COUNT(*) as total_leads, 
                  SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_leads,
                  SUM(price) as total_value,
                  SUM(CASE WHEN status = 'Closed' THEN price ELSE 0 END) as closed_value
                  FROM leads 
                  WHERE user_id IN (SELECT id FROM users WHERE team_id = ?)");
    $lead_stmt->bind_param("i", $team_id);
    $lead_stmt->execute();
    $lead_result = $lead_stmt->get_result();
    
    if ($lead_result && $row = $lead_result->fetch_assoc()) {
        $team_performance[$team_id] = [
            'total_leads' => $row['total_leads'] ?: 0,
            'closed_leads' => $row['closed_leads'] ?: 0,
            'total_value' => $row['total_value'] ?: 0,
            'closed_value' => $row['closed_value'] ?: 0,
            'conversion_rate' => $row['total_leads'] > 0 ? round(($row['closed_leads'] / $row['total_leads']) * 100, 1) : 0
        ];
    } else {
        $team_performance[$team_id] = [
            'total_leads' => 0,
            'closed_leads' => 0,
            'total_value' => 0,
            'closed_value' => 0,
            'conversion_rate' => 0
        ];
    }
    $lead_stmt->close();
}

// Define available roles based on current user's permissions with stricter controls
$available_roles = [];
if ($user['role'] == 'admin') {
    // Only admins can create all roles
    $available_roles = [
        'agent' => ['label' => 'Agent', 'icon' => 'fa-user', 'description' => 'Handles leads and client interactions'],
        'supervisor' => ['label' => 'Supervisor', 'icon' => 'fa-user-tie', 'description' => 'Supervises agents and manages team operations'],
        'manager' => ['label' => 'Manager', 'icon' => 'fa-briefcase', 'description' => 'Manages teams and oversees operations'],
        'admin' => ['label' => 'Administrator', 'icon' => 'fa-crown', 'description' => 'Full system access and management']
    ];
} elseif ($user['role'] == 'manager') {
    // Managers can ONLY create agents and supervisors - NO admin or manager roles
    $available_roles = [
        'agent' => ['label' => 'Agent', 'icon' => 'fa-user', 'description' => 'Handles leads and client interactions'],
        'supervisor' => ['label' => 'Supervisor', 'icon' => 'fa-user-tie', 'description' => 'Supervises agents and manages team operations']
    ];
} elseif ($user['role'] == 'supervisor') {
    // Supervisors can only create agents (optional - you can remove this if supervisors shouldn't create users)
    $available_roles = [
        'agent' => ['label' => 'Agent', 'icon' => 'fa-user', 'description' => 'Handles leads and client interactions']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/users.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: rgba(67, 97, 238, 0.1);
            --primary-dark: #3a56d4;
            --secondary: #f8f9fc;
            --success: #10b981;
            --success-light: rgba(16, 185, 129, 0.1);
            --danger: #ef4444;
            --danger-light: rgba(239, 68, 68, 0.1);
            --warning: #f59e0b;
            --warning-light: rgba(245, 158, 11, 0.1);
            --info: #3b82f6;
            --info-light: rgba(59, 130, 246, 0.1);
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-sm: 0.25rem;
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --transition: all 0.2s ease-in-out;
        }

        /* General Styles */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f3f4f6;
            color: var(--dark);
            line-height: 1.5;
            margin: 0;
        }

        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .page-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h2 i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        /* Teams Grid */
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .team-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }
        
        .team-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }
        
        .team-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 1.5rem;
            position: relative;
        }
        
        .team-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--white);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .team-name i {
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem;
            border-radius: var(--radius-sm);
        }
        
        .team-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .team-action {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .team-action:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .team-action.delete:hover {
            background: var(--danger);
        }
        
        .team-content {
            padding: 1.5rem;
        }
        
        .team-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .team-stat {
            text-align: center;
            padding: 1rem;
            background: var(--secondary);
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .team-stat:hover {
            background: var(--primary-light);
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 0.25rem 0;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray);
            margin: 0;
        }
        
        .team-footer {
            padding: 1rem 1.5rem;
            background: var(--secondary);
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .team-meta {
            font-size: 0.875rem;
            color: var(--gray);
        }
        
        .team-view {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .team-view:hover {
            color: var(--primary-dark);
            transform: translateX(4px);
        }

        /* Team Detail View */
        .team-details-container {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .team-detail-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 2rem;
            color: var(--white);
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .team-detail-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .team-detail-subtitle {
            color: rgba(255, 255, 255, 0.8);
            margin: 0 0 1.5rem 0;
        }

        .team-detail-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Stats Grid */
        .team-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
            opacity: 0.5;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        /* Members Table */
        .team-members-section {
            margin: 0 1.5rem 1.5rem;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .section-header {
            padding: 1.5rem;
            background: var(--secondary);
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .members-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .members-table th,
        .members-table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }

        .members-table th {
            background: var(--secondary);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .members-table td {
            color: var(--dark);
            font-size: 0.875rem;
        }

        .members-table tr:hover td {
            background: var(--secondary);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.75rem;
            font-weight: 500;
            gap: 0.375rem;
        }

        .role-admin {
            background: var(--primary-light);
            color: var(--primary);
        }

        .role-manager {
            background: var(--success-light);
            color: var(--success);
        }

        .role-supervisor {
            background: var(--info-light);
            color: var(--info);
        }

        .role-agent {
            background: var(--warning-light);
            color: var(--warning);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--white);
            color: var(--dark);
            border: 1px solid var(--gray-light);
        }
        
        .btn-secondary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Modals */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 1000;
            overflow-y: auto;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 500px;
            position: relative;
            margin: 2rem auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--gray);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--dark);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.625rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--dark);
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }

        /* Enhanced Role Selection Styles */
        .role-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .role-option {
            position: relative;
            cursor: pointer;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .role-card {
            border: 2px solid var(--gray-light);
            border-radius: var(--radius);
            padding: 1rem;
            transition: var(--transition);
            background: var(--white);
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .role-option input[type="radio"]:checked + .role-card {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .role-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .role-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            transition: var(--transition);
        }

        .role-option input[type="radio"]:checked + .role-card .role-icon {
            background: var(--primary);
            color: var(--white);
        }

        .role-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .role-description {
            font-size: 0.75rem;
            color: var(--gray);
            line-height: 1.4;
        }

        .password-display {
            background: var(--secondary);
            border: 1px solid var(--gray-light);
            border-radius: var(--radius);
            padding: 0.625rem 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }

        .text-muted {
            color: var(--gray) !important;
        }

        .username-preview {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: var(--primary-light);
            border-radius: var(--radius-sm);
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            color: var(--primary);
            min-height: 1.5rem;
        }

        /* Transfer Modal Styles */
        .team-radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .team-radio-option {
            position: relative;
        }

        .team-radio {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .team-radio-label {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            background: var(--white);
            position: relative;
        }

        .team-radio-label:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .team-radio:checked + .team-radio-label {
            border-color: var(--primary);
            background: var(--primary-light);
            box-shadow: var(--shadow-sm);
        }

        .team-radio:checked + .team-radio-label::before {
            content: '';
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .team-radio:checked + .team-radio-label::after {
            content: '✓';
            position: absolute;
            right: 1.375rem;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
            z-index: 1;
        }

        .team-name {
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .team-radio:checked + .team-radio-label .team-name {
            color: var(--primary);
            font-weight: 600;
        }

        /* User Selection Styles for Add Members Modal */
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            z-index: 2;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--dark);
            transition: var(--transition);
        }

        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }

        .user-selection {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius);
            padding: 0.5rem;
        }

        .user-selection-item {
            margin-bottom: 0.5rem;
        }

        .user-selection-item:last-child {
            margin-bottom: 0;
        }

        .user-selection-item input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .user-selection-item label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            background: var(--white);
        }

        .user-selection-item label:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .user-selection-item input[type="checkbox"]:checked + label {
            border-color: var(--primary);
            background: var(--primary-light);
            box-shadow: var(--shadow-sm);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .user-email {
            font-size: 0.75rem;
            color: var(--gray);
        }

        .user-role {
            display: flex;
            align-items: center;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.625rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-admin {
            background: var(--primary-light);
            color: var(--primary);
        }

        .badge-manager {
            background: var(--success-light);
            color: var(--success);
        }

        .badge-supervisor {
            background: var(--info-light);
            color: var(--info);
        }

        .badge-agent {
            background: var(--warning-light);
            color: var(--warning);
        }

        /* Empty State Styles */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--gray);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state-text {
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }

        .empty-state-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Table Responsive */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: var(--gray-light);
            border-radius: var(--radius-sm);
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: var(--radius-sm);
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .teams-grid {
                grid-template-columns: 1fr;
            }
            
            .team-detail-header {
                padding: 1.5rem;
            }
            
            .team-detail-actions {
                flex-direction: column;
            }

            .team-detail-actions .btn {
                width: 100%;
            }
        
            .team-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
            }

            .section-header .btn-group {
                width: 100%;
                display: flex;
                gap: 0.5rem;
            }

            .section-header .btn {
                flex: 1;
            }

            .role-selection {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .team-stats-grid {
                grid-template-columns: 1fr;
            }

            .team-stats {
                grid-template-columns: 1fr;
            }

            .modal-content {
                margin: 1rem;
            }
        }

        /* Empty State */
        .no-teams {
            text-align: center;
            padding: 3rem 1.5rem;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
        }

        .no-teams i {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        .no-teams p {
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            background: var(--secondary);
            color: var(--gray);
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .btn-icon:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
        }

        .btn-transfer {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--primary-light);
            color: var(--primary);
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-transfer:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
        }

        .btn-delete {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid var(--danger);
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-delete:hover {
            background: var(--danger);
            color: var(--white);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <?php if (!isset($_GET['view'])): ?>
            <!-- Teams List View -->
            <div class="page-header">
                <h2>Teams Management</h2>
                <?php if ($user['role'] == 'admin'): ?>
                <button class="btn btn-primary" onclick="openAddTeamModal()" aria-label="Add New Team">
                    <i class="fas fa-plus" aria-hidden="true"></i> Add New Team
                </button>
                <?php endif; ?>
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
            
            <?php if (count($teams) > 0): ?>
            <div class="teams-grid">
                <?php foreach ($teams as $team): ?>
                <div class="team-card">
                    <div class="team-header">
                        <h3 class="team-name"><?php echo htmlspecialchars($team['name']); ?></h3>
                        <?php if ($user['role'] == 'admin'): ?>
                        <div class="team-actions">
                            <button type="button" onclick="openEditTeamModal(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars(addslashes($team['name'])); ?>')" class="team-action" aria-label="Edit Team <?php echo htmlspecialchars($team['name']); ?>">
                                <i class="fas fa-edit" aria-hidden="true"></i>
                            </button>
                            <button type="button" onclick="confirmDeleteTeam(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars(addslashes($team['name'])); ?>')" class="team-action delete" aria-label="Delete Team <?php echo htmlspecialchars($team['name']); ?>">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="team-content">
                        <div class="team-stats">
                            <div class="team-stat">
                                <div class="stat-value"><?php echo $team['member_count']; ?></div>
                                <div class="stat-label">Members</div>
                            </div>
                            <div class="team-stat">
                                <div class="stat-value"><?php echo $team_performance[$team['id']]['total_leads']; ?></div>
                                <div class="stat-label">Total Leads</div>
                            </div>
                            <div class="team-stat">
                                <div class="stat-value"><?php echo $team_performance[$team['id']]['closed_leads']; ?></div>
                                <div class="stat-label">Closed Deals</div>
                            </div>
                            <div class="team-stat">
                                <div class="stat-value"><?php echo $team_performance[$team['id']]['conversion_rate']; ?>%</div>
                                <div class="stat-label">Conversion Rate</div>
                            </div>
                        </div>
                    </div>
                    <div class="team-footer">
                        <div class="team-meta">Created: <?php echo date('M d, Y', strtotime($team['created_at'])); ?></div>
                        <a href="teams.php?view=<?php echo $team['id']; ?>" class="team-view">
                            View Details <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-teams">
                <i class="fas fa-users" aria-hidden="true"></i>
                <p>No teams found.</p>
                <?php if ($user['role'] == 'admin'): ?>
                <button class="btn btn-primary" onclick="openAddTeamModal()">
                    <i class="fas fa-plus" aria-hidden="true"></i> Add New Team
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <!-- Team Detail View -->
            <div class="team-details-container">
                <div class="team-detail-header">
                    <h2 class="team-detail-title">
                        <i class="fas fa-users"></i>
                        <?php echo htmlspecialchars($selected_team['name']); ?> Team
                    </h2>
                    <p class="team-detail-subtitle">
                        Manage team members and view team performance metrics
                    </p>
                    <div class="team-detail-actions">
                        <?php if ($user['role'] == 'admin'): ?>
                        <a href="teams.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Teams
                        </a>
                        <?php endif; ?>
                        <?php if ($user['role'] == 'admin' || $user['role'] == 'manager'): ?>
                        <button class="btn btn-primary" onclick="openAddMembersModal()">
                            <i class="fas fa-user-plus"></i> Add Members
                        </button>
                        <button class="btn btn-primary" onclick="openCreateUserModal()">
                            <i class="fas fa-user-plus"></i> Create New User
                        </button>
                        <?php endif; ?>
                        <?php if ($user['role'] == 'admin'): ?>
                        <button class="btn btn-primary" onclick="openEditTeamModal(<?php echo $selected_team['id']; ?>, '<?php echo htmlspecialchars(addslashes($selected_team['name'])); ?>')">
                            <i class="fas fa-edit"></i> Edit Team
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="team-stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo count($team_members); ?></div>
                        <div class="stat-label">Team Members</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-value"><?php echo $team_performance[$selected_team['id']]['total_leads']; ?></div>
                        <div class="stat-label">Total Leads</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $team_performance[$selected_team['id']]['closed_leads']; ?></div>
                        <div class="stat-label">Closed Deals</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-value"><?php echo $team_performance[$selected_team['id']]['conversion_rate']; ?>%</div>
                        <div class="stat-label">Conversion Rate</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-value">₱<?php echo number_format($team_performance[$selected_team['id']]['total_value'], 0); ?></div>
                        <div class="stat-label">Total Portfolio Value</div>
                    </div>
                </div>

                <div class="team-members-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-user-friends"></i>
                            Team Members (<?php echo count($team_members); ?>)
                        </h3>
                        <?php if (($user['role'] == 'admin' || $user['role'] == 'manager')): ?>
                        <div class="section-actions">
                            <button class="btn btn-primary btn-sm" onclick="openAddMembersModal()">
                                <i class="fas fa-user-plus"></i> Add Members
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="openCreateUserModal()">
                                <i class="fas fa-user-plus"></i> Create New User
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($team_members) > 0): ?>
                    <div class="table-responsive">
                        <table class="members-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($team_members as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['username']); ?></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower($member['role']); ?>">
                                            <i class="fas fa-<?php echo strtolower($member['role']) == 'admin' ? 'crown' : (strtolower($member['role']) == 'manager' ? 'briefcase' : (strtolower($member['role']) == 'supervisor' ? 'user-tie' : 'user')); ?>"></i>
                                            <?php echo ucfirst($member['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="user-details.php?id=<?php echo $member['id']; ?>" class="btn-icon" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit-user.php?id=<?php echo $member['id']; ?>" class="btn-icon" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" onclick="openTransferMemberModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars(addslashes($member['name'])); ?>', <?php echo $selected_team['id']; ?>)" class="btn-transfer">
                                                <i class="fas fa-exchange-alt"></i> Transfer
                                            </button>
                                            <?php if ($user['role'] == 'manager' || $user['role'] == 'admin'): ?>
                                            <button type="button" onclick="deleteTeamMember(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars(addslashes($member['name'])); ?>')" class="btn-delete" title="Remove from Team">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <p class="empty-state-text">No team members found</p>
                        <?php if ($user['role'] == 'admin' || $user['role'] == 'manager'): ?>
                        <div class="empty-state-actions">
                            <button class="btn btn-primary btn-sm" onclick="openAddMembersModal()">
                                <i class="fas fa-user-plus"></i> Add Members
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="openCreateUserModal()">
                                <i class="fas fa-user-plus"></i> Create New User
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Team Modal -->
    <div id="addTeamModal" class="modal" role="dialog" aria-labelledby="addTeamModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="addTeamModalTitle">Add New Team</h3>
                <button type="button" class="modal-close" onclick="closeAddTeamModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="team_name">Team Name</label>
                        <input type="text" id="team_name" name="team_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddTeamModal()">Cancel</button>
                    <button type="submit" name="add_team" class="btn btn-primary">Add Team</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Team Modal -->
    <div id="editTeamModal" class="modal" role="dialog" aria-labelledby="editTeamModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="editTeamModalTitle">Edit Team</h3>
                <button type="button" class="modal-close" onclick="closeEditTeamModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_team_name">Team Name</label>
                        <input type="text" id="edit_team_name" name="team_name" required>
                    </div>
                    <input type="hidden" id="edit_team_id" name="team_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditTeamModal()">Cancel</button>
                    <button type="submit" name="edit_team" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Members Modal -->
    <?php if ($selected_team && ($user['role'] == 'admin' || $user['role'] == 'manager')): ?>
    <div id="addMembersModal" class="modal" role="dialog" aria-labelledby="addMembersModalTitle" aria-hidden="true">
        <div class="modal-content" style="width: 600px; max-width: 95%;">
            <div class="modal-header">
                <h3 class="modal-title" id="addMembersModalTitle">Add Members to <?php echo htmlspecialchars($selected_team['name']); ?></h3>
                <button type="button" class="modal-close" onclick="closeAddMembersModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php if (count($available_users) > 0): ?>
                    <div class="search-box">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="text" id="userSearchInput" placeholder="Search users..." onkeyup="filterUsers()">
                    </div>
                    <div class="user-selection">
                        <?php foreach ($available_users as $avail_user): ?>
                        <div class="user-selection-item">
                            <input type="checkbox" id="user_<?php echo $avail_user['id']; ?>" name="user_ids[]" value="<?php echo $avail_user['id']; ?>">
                            <label for="user_<?php echo $avail_user['id']; ?>">
                                <div class="user-info">
                                    <span class="user-name"><?php echo htmlspecialchars($avail_user['name']); ?></span>
                                    <span class="user-email"><?php echo htmlspecialchars($avail_user['email']); ?></span>
                                </div>
                                <span class="user-role">
                                    <span class="badge badge-<?php echo $avail_user['role']; ?>">
                                        <?php echo ucfirst(htmlspecialchars($avail_user['role'])); ?>
                                    </span>
                                </span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="team_id" value="<?php echo $selected_team['id']; ?>">
                    <?php else: ?>
                    <p>No available users to add to this team.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddMembersModal()">Cancel</button>
                    <?php if (count($available_users) > 0): ?>
                    <button type="submit" name="add_user_to_team" class="btn btn-primary">Add Selected Users</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Transfer Member Modal -->
    <?php if ($selected_team && ($user['role'] == 'admin' || $user['role'] == 'manager')): ?>
    <div id="transferMemberModal" class="modal" role="dialog" aria-labelledby="transferMemberModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="transferMemberModalTitle">Transfer Member</h3>
                <button type="button" class="modal-close" onclick="closeTransferMemberModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <p>Transfer <strong id="transfer_member_name"></strong> to:</p>
                    <div class="form-group">
                        <div class="team-radio-group">
                            <div class="team-radio-option">
                                <input type="radio" id="no_team" name="new_team_id" value="" class="team-radio">
                                <label for="no_team" class="team-radio-label">
                                    <span class="team-name">No Team Assignment</span>
                                </label>
                            </div>
                            <?php foreach ($all_teams as $team): ?>
                                <?php if ($team['id'] != $selected_team['id']): ?>
                                <div class="team-radio-option">
                                    <input type="radio" id="team_<?php echo $team['id']; ?>" name="new_team_id" value="<?php echo $team['id']; ?>" class="team-radio">
                                    <label for="team_<?php echo $team['id']; ?>" class="team-radio-label">
                                        <span class="team-name"><?php echo htmlspecialchars($team['name']); ?></span>
                                    </label>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <input type="hidden" id="user_id_to_transfer" name="user_id">
                    <input type="hidden" name="current_team_id" value="<?php echo $selected_team['id']; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeTransferMemberModal()">Cancel</button>
                    <button type="submit" name="transfer_member" class="btn btn-primary">Transfer Member</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Enhanced Create User Modal with Role Selection -->
    <?php if ($selected_team && ($user['role'] == 'admin' || $user['role'] == 'manager')): ?>
    <div id="createUserModal" class="modal" role="dialog" aria-labelledby="createUserModalTitle" aria-hidden="true">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title" id="createUserModalTitle">Create New User</h3>
                <button type="button" class="modal-close" onclick="closeCreateUserModal()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" id="createUserForm">
                <div class="modal-body">
                    <!-- Role Restriction Notice for Managers -->
                    <?php if ($user['role'] == 'manager'): ?>
                    <div class="alert" style="background: var(--warning-light); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.2); margin-bottom: 1.5rem;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Role Restrictions:</strong> As a Manager, you can only create Agent and Supervisor accounts. Admin and Manager roles are restricted.
                    </div>
                    <?php elseif ($user['role'] == 'supervisor'): ?>
                    <div class="alert" style="background: var(--info-light); color: var(--info); border: 1px solid rgba(59, 130, 246, 0.2); margin-bottom: 1.5rem;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Role Restrictions:</strong> As a Supervisor, you can only create Agent accounts.
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="user_name">Full Name *</label>
                        <input type="text" id="user_name" name="name" required class="form-control" oninput="generateUsername()">
                        <small class="form-text text-muted">Enter the full name of the user</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_email">Email Address *</label>
                        <input type="email" id="user_email" name="email" required class="form-control">
                        <small class="form-text text-muted">Enter a valid email address</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_phone">Phone Number *</label>
                        <input type="tel" id="user_phone" name="phone" required pattern="[0-9]{11}" maxlength="11" placeholder="09123456789" class="form-control">
                        <small class="form-text text-muted">Enter 11-digit phone number</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_username">Username</label>
                        <input type="text" id="user_username" name="username" required class="form-control" readonly>
                        <small class="form-text text-muted">Username will be automatically generated based on the full name</small>
                        <div class="username-preview" id="usernamePreview"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Role * 
                            <?php if ($user['role'] != 'admin'): ?>
                            <span style="color: var(--warning); font-size: 0.75rem;">
                                (Limited based on your permissions)
                            </span>
                            <?php endif; ?>
                        </label>
                        <div class="role-selection">
                            <?php foreach ($available_roles as $role_key => $role_info): ?>
                            <div class="role-option">
                                <input type="radio" id="role_<?php echo $role_key; ?>" name="role" value="<?php echo $role_key; ?>" required>
                                <label for="role_<?php echo $role_key; ?>" class="role-card">
                                    <div class="role-icon">
                                        <i class="fas <?php echo $role_info['icon']; ?>"></i>
                                    </div>
                                    <div class="role-name"><?php echo $role_info['label']; ?></div>
                                    <div class="role-description"><?php echo $role_info['description']; ?></div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="form-text text-muted">
                            <?php if ($user['role'] == 'admin'): ?>
                                You can create users with any role
                            <?php elseif ($user['role'] == 'manager'): ?>
                                You can only create Agent and Supervisor accounts
                            <?php elseif ($user['role'] == 'supervisor'): ?>
                                You can only create Agent accounts
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Default Password</label>
                        <div class="password-display">123456789innersparc</div>
                        <small class="form-text text-muted">This is the default password that will be set for the new user</small>
                        <input type="hidden" name="password" value="123456789innersparc">
                        <input type="hidden" name="confirm_password" value="123456789innersparc">
                    </div>
                    
                    <input type="hidden" name="team_id" value="<?php echo $selected_team['id']; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateUserModal()">Cancel</button>
                    <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    // Add Team Modal
    function openAddTeamModal() {
        document.getElementById('addTeamModal').style.display = 'block';
        document.getElementById('team_name').focus();
    }
    
    function closeAddTeamModal() {
        document.getElementById('addTeamModal').style.display = 'none';
    }
    
    // Edit Team Modal
    function openEditTeamModal(teamId, teamName) {
        document.getElementById('edit_team_id').value = teamId;
        document.getElementById('edit_team_name').value = teamName;
        document.getElementById('editTeamModal').style.display = 'block';
        document.getElementById('edit_team_name').focus();
    }
    
    function closeEditTeamModal() {
        document.getElementById('editTeamModal').style.display = 'none';
    }
    
    // Add Members Modal
    function openAddMembersModal() {
        const addMembersModal = document.getElementById('addMembersModal');
        if (addMembersModal) {
            addMembersModal.style.display = 'block';
            const searchInput = document.getElementById('userSearchInput');
            if (searchInput) {
                searchInput.focus();
            }
        }
    }
    
    function closeAddMembersModal() {
        const addMembersModal = document.getElementById('addMembersModal');
        if (addMembersModal) {
            addMembersModal.style.display = 'none';
        }
    }
    
    // Enhanced Transfer Member Modal
    function openTransferMemberModal(userId, userName, teamId) {
        const transferModal = document.getElementById('transferMemberModal');
        if (transferModal) {
            document.getElementById('user_id_to_transfer').value = userId;
            document.getElementById('transfer_member_name').textContent = userName;
            
            // Clear any previously selected radio buttons
            const radioButtons = transferModal.querySelectorAll('input[type="radio"]');
            radioButtons.forEach(radio => {
                radio.checked = false;
            });
            
            // Show the modal
            transferModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Focus on the first radio option
            const firstRadio = transferModal.querySelector('input[type="radio"]');
            if (firstRadio) {
                setTimeout(() => firstRadio.focus(), 100);
            }
        }
    }

    function closeTransferMemberModal() {
        const transferModal = document.getElementById('transferMemberModal');
        if (transferModal) {
            transferModal.style.display = 'none';
            document.body.style.overflow = '';
            
            // Clear form
            const radioButtons = transferModal.querySelectorAll('input[type="radio"]');
            radioButtons.forEach(radio => {
                radio.checked = false;
            });
        }
    }
    
    // Enhanced Create User Modal
    function openCreateUserModal() {
        document.getElementById('createUserModal').style.display = 'block';
        document.getElementById('user_name').focus();
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
    
    function closeCreateUserModal() {
        document.getElementById('createUserModal').style.display = 'none';
        document.body.style.overflow = ''; // Restore scrolling
        // Reset form
        document.getElementById('createUserForm').reset();
        document.getElementById('usernamePreview').textContent = '';
    }
    
    // Filter users in the add members modal
    function filterUsers() {
        const input = document.getElementById('userSearchInput');
        const filter = input.value.toUpperCase();
        const userItems = document.querySelectorAll('.user-selection-item');
        
        userItems.forEach(item => {
            const userName = item.querySelector('.user-name').textContent;
            const userEmail = item.querySelector('.user-email').textContent;
            
            if (userName.toUpperCase().indexOf(filter) > -1 || userEmail.toUpperCase().indexOf(filter) > -1) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    // Delete Team Confirmation
    function confirmDeleteTeam(teamId, teamName) {
        if (confirm('Are you sure you want to delete the team "' + teamName + '"? This action cannot be undone.')) {
            window.location.href = 'teams.php?delete=' + teamId;
        }
    }
    
    // Delete Team Member Confirmation
    function deleteTeamMember(userId, userName) {
        if (confirm('Are you sure you want to remove "' + userName + '" from this team? This will unassign them from the team.')) {
            // Create a form to submit the delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userId;
            
            const teamIdInput = document.createElement('input');
            teamIdInput.type = 'hidden';
            teamIdInput.name = 'team_id';
            teamIdInput.value = <?php echo $selected_team ? $selected_team['id'] : 'null'; ?>;
            
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_member';
            deleteInput.value = '1';
            
            form.appendChild(userIdInput);
            form.appendChild(teamIdInput);
            form.appendChild(deleteInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target == document.getElementById('addTeamModal')) {
            closeAddTeamModal();
        }
        if (event.target == document.getElementById('editTeamModal')) {
            closeEditTeamModal();
        }
        const addMembersModal = document.getElementById('addMembersModal');
        if (addMembersModal && event.target == addMembersModal) {
            closeAddMembersModal();
        }
        const transferMemberModal = document.getElementById('transferMemberModal');
        if (transferMemberModal && event.target == transferMemberModal) {
            closeTransferMemberModal();
        }
        const createUserModal = document.getElementById('createUserModal');
        if (createUserModal && event.target == createUserModal) {
            closeCreateUserModal();
        }
    }
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAddTeamModal();
            closeEditTeamModal();
            closeAddMembersModal();
            closeTransferMemberModal();
            closeCreateUserModal();
        }
    });
    
    // Function to generate username from full name
    function generateUsername() {
        const fullName = document.getElementById('user_name').value.trim();
        if (fullName) {
            // Convert to lowercase and remove special characters
            let username = fullName.toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '') // Remove diacritics
                .replace(/[^a-z0-9\s]/g, '')     // Remove special characters
                .replace(/\s+/g, '.');           // Replace spaces with dots
            username = username + '.innersparc';
            document.getElementById('user_username').value = username;
            document.getElementById('usernamePreview').textContent = username;
        } else {
            document.getElementById('user_username').value = '';
            document.getElementById('usernamePreview').textContent = '';
        }
    }

    // Enhanced Form validation with role restrictions
    document.addEventListener('DOMContentLoaded', function() {
    const createUserForm = document.getElementById('createUserForm');
    const phoneInput = document.getElementById('user_phone');
    const roleInputs = document.querySelectorAll('input[name="role"]');

    if (createUserForm) {
        createUserForm.addEventListener('submit', function(e) {
            const name = document.getElementById('user_name').value.trim();
            const email = document.getElementById('user_email').value.trim();
            const phone = phoneInput.value.trim();
            const selectedRole = document.querySelector('input[name="role"]:checked');

            if (!name || !email || !phone || !selectedRole) {
                e.preventDefault();
                alert('Please fill in all required fields and select a role.');
                return false;
            }

            if (phone.length !== 11 || !/^[0-9]{11}$/.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid 11-digit phone number.');
                return false;
            }

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }

            // Additional frontend validation for role restrictions
            const userRole = '<?php echo $user['role']; ?>';
            const selectedRoleValue = selectedRole.value;
            
            if (userRole === 'manager' && (selectedRoleValue === 'admin' || selectedRoleValue === 'manager')) {
                e.preventDefault();
                alert('Access Denied: As a Manager, you can only create Agent and Supervisor accounts.');
                return false;
            }
            
            if (userRole === 'supervisor' && selectedRoleValue !== 'agent') {
                e.preventDefault();
                alert('Access Denied: As a Supervisor, you can only create Agent accounts.');
                return false;
            }
        });
    }

    // Phone number input formatting
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

    // Add visual feedback for role restrictions
    if (roleInputs.length > 0) {
        const userRole = '<?php echo $user['role']; ?>';
        
        roleInputs.forEach(input => {
            const roleValue = input.value;
            const roleCard = input.nextElementSibling;
            
            // Disable restricted roles visually and functionally
            if (userRole === 'manager' && (roleValue === 'admin' || roleValue === 'manager')) {
                input.disabled = true;
                roleCard.style.opacity = '0.5';
                roleCard.style.cursor = 'not-allowed';
                roleCard.title = 'Access Denied: Managers cannot create Admin or Manager accounts';
            } else if (userRole === 'supervisor' && roleValue !== 'agent') {
                input.disabled = true;
                roleCard.style.opacity = '0.5';
                roleCard.style.cursor = 'not-allowed';
                roleCard.title = 'Access Denied: Supervisors can only create Agent accounts';
            }
        });
    }

    // Add transfer form validation
    const transferForm = document.querySelector('#transferMemberModal form');
    if (transferForm) {
        transferForm.addEventListener('submit', function(e) {
            const selectedTeam = document.querySelector('input[name="new_team_id"]:checked');
            if (!selectedTeam) {
                e.preventDefault();
                alert('Please select a team to transfer the member to.');
                return false;
            }
            
            const memberName = document.getElementById('transfer_member_name').textContent;
            const teamName = selectedTeam.value === '' ? 'No Team Assignment' : 
                            selectedTeam.nextElementSibling.querySelector('.team-name').textContent;
            
            if (!confirm(`Are you sure you want to transfer ${memberName} to ${teamName}?`)) {
                e.preventDefault();
                return false;
            }
        });
    }
});
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
