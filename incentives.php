<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Check if user is admin or manager
$isAuthorized = ($user['role'] === 'admin' || $user['role'] === 'manager');
$isAdmin = ($user['role'] === 'admin');

// Initialize messages
$success_message = '';
$error_message = '';

// Establish database connection
$conn = getDbConnection();

// Ensure required tables exist
$conn->query("CREATE TABLE IF NOT EXISTS incentives (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    position VARCHAR(50) NOT NULL,
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    incentive_type VARCHAR(50) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_incentive (user_id, incentive_type, destination),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS tour_targets (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tour_type VARCHAR(50) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    agent_target DECIMAL(15,2) DEFAULT 500000,
    supervisor_target DECIMAL(15,2) DEFAULT 800000,
    manager_target DECIMAL(15,2) DEFAULT 1000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY tour_destination (tour_type, destination)
)");

// Insert default tour targets
$conn->query("INSERT IGNORE INTO tour_targets (tour_type, destination, agent_target, supervisor_target, manager_target) VALUES 
('Local Tour', 'Boracay', 500000, 800000, 1000000),
('Local Tour', 'Baguio', 400000, 600000, 800000),
('International Tour', 'Malaysia/Indonesia', 800000, 1200000, 1500000),
('International Tour', 'Singapore', 600000, 900000, 1200000)");

// Ensure users have position column and check for profile column
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'position'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN position VARCHAR(50) DEFAULT 'Agent'");
}
$conn->query("UPDATE users SET position = 'Agent' WHERE position IS NULL OR position = ''");

// Check for profile column (could be 'profile', 'profile_picture', or 'avatar')
$profile_column = null;
$possible_columns = ['profile', 'profile_picture', 'avatar', 'photo'];
foreach ($possible_columns as $col) {
    $result = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($result->num_rows > 0) {
        $profile_column = $col;
        break;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_sales']) && $isAuthorized) {
        try {
            $target_user_id = intval($_POST['user_id']);
            $total_sales = floatval($_POST['total_sales']);
            
            // Get user position
            $user_query = $conn->prepare("SELECT position FROM users WHERE id = ?");
            $user_query->bind_param("i", $target_user_id);
            $user_query->execute();
            $user_result = $user_query->get_result();
            $user_data = $user_result->fetch_assoc();
            $position = $user_data['position'] ?? 'Agent';
            
            // Get all tour type and destination combinations
            $tours_query = "SELECT DISTINCT tour_type, destination FROM tour_targets";
            $tours_result = $conn->query($tours_query);
            
            $updated_count = 0;
            
            // Apply the same sales amount to ALL tour type/destination combinations
            while ($tour = $tours_result->fetch_assoc()) {
                $stmt = $conn->prepare("INSERT INTO incentives (user_id, position, total_sales, incentive_type, destination) 
                                      VALUES (?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE total_sales = ?, position = ?");
                
                $stmt->bind_param("issssds", $target_user_id, $position, $total_sales, 
                                $tour['tour_type'], $tour['destination'], $total_sales, $position);
                $stmt->execute();
                $updated_count++;
            }
            
            $success_message = "Sales data updated successfully across all {$updated_count} tour combinations!";
        } catch (Exception $e) {
            $error_message = "Error updating sales data: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['add_tour_target']) && $isAdmin) {
        try {
            $tour_type = $_POST['new_tour_type'];
            $destination = $_POST['new_destination'];
            $agent_target = floatval($_POST['agent_target']);
            $supervisor_target = floatval($_POST['supervisor_target']);
            $manager_target = floatval($_POST['manager_target']);
            
            $stmt = $conn->prepare("INSERT INTO tour_targets (tour_type, destination, agent_target, supervisor_target, manager_target) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddd", $tour_type, $destination, $agent_target, $supervisor_target, $manager_target);
            $stmt->execute();
            
            $success_message = "New tour target added successfully!";
        } catch (Exception $e) {
            $error_message = "Error adding tour target: " . $e->getMessage();
        }
    }

    if (isset($_POST['edit_tour_target']) && $isAdmin) {
        try {
            $tour_id = intval($_POST['tour_id']);
            $tour_type = $_POST['edit_tour_type'];
            $destination = $_POST['edit_destination'];
            $agent_target = floatval($_POST['edit_agent_target']);
            $supervisor_target = floatval($_POST['edit_supervisor_target']);
            $manager_target = floatval($_POST['edit_manager_target']);
            
            $stmt = $conn->prepare("UPDATE tour_targets SET tour_type = ?, destination = ?, agent_target = ?, supervisor_target = ?, manager_target = ? WHERE id = ?");
            $stmt->bind_param("ssdddi", $tour_type, $destination, $agent_target, $supervisor_target, $manager_target, $tour_id);
            $stmt->execute();
            
            $success_message = "Tour target updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating tour target: " . $e->getMessage();
        }
    }

    if (isset($_POST['delete_tour_target']) && $isAdmin) {
        try {
            $tour_id = intval($_POST['tour_id']);
            
            $stmt = $conn->prepare("DELETE FROM tour_targets WHERE id = ?");
            $stmt->bind_param("i", $tour_id);
            $stmt->execute();
            
            $success_message = "Tour target deleted successfully!";
        } catch (Exception $e) {
            $error_message = "Error deleting tour target: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$selected_team = isset($_GET['team_id']) ? $_GET['team_id'] : ($user['role'] === 'manager' ? $user['team_id'] : 'all');
$selected_agent = isset($_GET['agent_id']) ? $_GET['agent_id'] : 'all';
$selected_tour_type = isset($_GET['tour_type']) ? $_GET['tour_type'] : 'Local Tour';
$selected_destination = isset($_GET['destination']) ? $_GET['destination'] : 'Boracay';
$selected_position = isset($_GET['position']) ? $_GET['position'] : 'all';

// Get tour targets
$tour_targets_query = "SELECT * FROM tour_targets WHERE tour_type = ? AND destination = ?";
$tour_targets_stmt = $conn->prepare($tour_targets_query);
$tour_targets_stmt->bind_param("ss", $selected_tour_type, $selected_destination);
$tour_targets_stmt->execute();
$tour_targets_result = $tour_targets_stmt->get_result();
$tour_targets = $tour_targets_result->fetch_assoc();

// Default values if no targets found
if (!$tour_targets) {
    $tour_targets = [
        'agent_target' => 500000,
        'supervisor_target' => 800000,
        'manager_target' => 1000000
    ];
}

// Build the users query with ranking - include profile column if it exists
$profile_select = $profile_column ? ", u.$profile_column as profile_picture" : ", NULL as profile_picture";

$query = "SELECT 
    u.id, 
    u.name, 
    u.position,
    u.team_id
    $profile_select,
    t.name as team_name,
    COALESCE(i.total_sales, 0) as total_sales,
    COALESCE(i.incentive_type, ?) as incentive_type,
    COALESCE(i.destination, ?) as destination
FROM users u 
LEFT JOIN teams t ON u.team_id = t.id
LEFT JOIN incentives i ON u.id = i.user_id AND i.incentive_type = ? AND i.destination = ?
WHERE u.role != 'admin' AND u.is_active = 1";

$params = [$selected_tour_type, $selected_destination, $selected_tour_type, $selected_destination];
$param_types = "ssss";

// Add filters
if ($selected_team !== 'all') {
    $query .= " AND u.team_id = ?";
    $params[] = $selected_team;
    $param_types .= "i";
}

if ($selected_agent !== 'all') {
    $query .= " AND u.id = ?";
    $params[] = $selected_agent;
    $param_types .= "i";
}

if ($selected_position !== 'all') {
    $query .= " AND u.position = ?";
    $params[] = $selected_position;
    $param_types .= "s";
}

if ($user['role'] === 'manager') {
    $query .= " AND u.team_id = ?";
    $params[] = $user['team_id'];
    $param_types .= "i";
}

$query .= " ORDER BY total_sales DESC, u.name";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get teams for filter
$teams_query = "SELECT * FROM teams ORDER BY name";
$teams_result = $conn->query($teams_query);

// Get agents for filter
$agents_query = "SELECT id, name FROM users WHERE role != 'admin' AND is_active = 1";
if ($user['role'] === 'manager') {
    $agents_query .= " AND team_id = " . intval($user['team_id']);
}
$agents_query .= " ORDER BY name";
$agents_result = $conn->query($agents_query);

// Get tour types and destinations
$tours_query = "SELECT DISTINCT tour_type, destination FROM tour_targets ORDER BY tour_type, destination";
$tours_result = $conn->query($tours_query);

// Get existing tour types for modal dropdown
$existing_tour_types_query = "SELECT DISTINCT tour_type FROM tour_targets ORDER BY tour_type";
$existing_tour_types_result = $conn->query($existing_tour_types_query);

// Get all tour targets for management
$all_tour_targets_query = "SELECT * FROM tour_targets ORDER BY tour_type, destination";
$all_tour_targets_result = $conn->query($all_tour_targets_query);

// Helper functions
function calculateProgress($sales, $target) {
    if ($target <= 0) return 0;
    return min(100, ($sales / $target) * 100);
}

function getTargetForPosition($position, $targets) {
    switch (strtolower($position)) {
        case 'manager':
            return $targets['manager_target'];
        case 'supervisor':
            return $targets['supervisor_target'];
        default:
            return $targets['agent_target'];
    }
}

function getRankSuffix($rank) {
    if ($rank % 100 >= 11 && $rank % 100 <= 13) {
        return $rank . 'th';
    }
    switch ($rank % 10) {
        case 1: return $rank . 'st';
        case 2: return $rank . 'nd';
        case 3: return $rank . 'rd';
        default: return $rank . 'th';
    }
}

function getProfilePicture($profile_picture, $name) {
    if ($profile_picture) {
        // Check multiple possible paths
        $possible_paths = [
            'uploads/profiles/' . $profile_picture,
            'uploads/' . $profile_picture,
            'assets/images/profiles/' . $profile_picture,
            'images/profiles/' . $profile_picture,
            $profile_picture // Direct path
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
    }
    
    // Generate initials as fallback
    $initials = '';
    $name_parts = explode(' ', $name);
    foreach ($name_parts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper($part[0]);
        }
    }
    return $initials;
}

// Calculate statistics
$all_agents = [];
$total_sales_all = 0;
$total_target_all = 0;
$agents_on_track = 0;

$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    $target = getTargetForPosition($row['position'], $tour_targets);
    $progress = calculateProgress($row['total_sales'], $target);
    
    $all_agents[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'position' => $row['position'],
        'team_name' => $row['team_name'],
        'profile_picture' => $row['profile_picture'],
        'total_sales' => $row['total_sales'],
        'target' => $target,
        'progress' => $progress
    ];
    
    $total_sales_all += $row['total_sales'];
    $total_target_all += $target;
    
    if ($progress >= 100) {
        $agents_on_track++;
    }
}

$agent_count = count($all_agents);
$overall_progress = $total_target_all > 0 ? calculateProgress($total_sales_all, $total_target_all) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Incentive Performance - InnerSPARC Real Estate</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #e0e7ff;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --border-radius: 0.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --gold: #ffd700;
            --silver: #c0c0c0;
            --bronze: #cd7f32;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.5;
            margin: 0;
            min-height: 100vh;
            display: flex;
        }
        
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--gray-50);
        }
        
        .incentives-page {
            flex: 1;
            padding: 2rem;
            width: 100%;
            margin: 0;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .page-header h2 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h2 i {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        .btn-add:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-manage {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: var(--info);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        .btn-manage:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.75rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            border: 1px solid var(--gray-200);
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .summary-icon {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.25rem;
            font-size: 1.5rem;
        }

        .summary-info {
            flex: 1;
        }

        .summary-info h3 {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin: 0 0 0.5rem 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-info p {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }

        /* Minimalistic Filter Design */
        .filters-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }
        
        .filter-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 160px;
        }

        .filter-item label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            white-space: nowrap;
            min-width: fit-content;
        }

        .filter-item select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            font-size: 0.875rem;
            background-color: white;
            color: var(--gray-700);
            transition: all 0.2s ease;
            min-width: 120px;
            flex: 1;
        }

        .filter-item select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-light);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            margin-left: auto;
        }

        .btn-filter-mini {
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .btn-filter-mini:hover {
            background: var(--primary-hover);
        }

        .btn-reset-mini {
            padding: 0.5rem 1rem;
            background: var(--gray-100);
            color: var(--gray-600);
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            text-decoration: none;
        }

        .btn-reset-mini:hover {
            background: var(--gray-200);
            border-color: var(--gray-400);
        }

        .rankings-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .rankings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .rankings-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
        }

        .top-performers {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 2rem;
        }

        .performer-card {
            background: var(--gray-50);
            border-radius: 0.75rem;
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            min-height: 140px;
        }

        .performer-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .performer-card.rank-1 {
            border-color: var(--gold);
            background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 100%);
        }

        .performer-card.rank-2 {
            border-color: var(--silver);
            background: linear-gradient(135deg, #f3f4f6 0%, #9ca3af 100%);
        }

        .performer-card.rank-3 {
            border-color: var(--bronze);
            background: linear-gradient(135deg, #fed7aa 0%, #ea580c 100%);
        }

        .performer-avatar {
            position: relative;
            flex-shrink: 0;
        }

        .rank-badge {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            background: var(--primary);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .performer-card.rank-1 .rank-badge {
            background: var(--gold);
            color: var(--gray-900);
        }

        .performer-card.rank-2 .rank-badge {
            background: var(--silver);
            color: var(--gray-900);
        }

        .performer-card.rank-3 .rank-badge {
            background: var(--bronze);
            color: white;
        }

        .profile-picture {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: var(--shadow-md);
            margin-left: 1rem;
        }

        .profile-initials {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.125rem;
            border: 3px solid white;
            box-shadow: var(--shadow-md);
            margin-left: 1rem;
        }

        .performer-info {
            flex: 1;
            min-width: 0;
        }

        .performer-info h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            line-height: 1.3;
        }

        .performer-info p {
            margin: 0.25rem 0;
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .performer-stats {
            text-align: right;
            flex-shrink: 0;
            min-width: 140px;
        }

        .sales-amount {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.75rem;
            line-height: 1.2;
        }

        .progress-mini {
            width: 140px;
            height: 8px;
            background: var(--gray-200);
            border-radius: 4px;
            margin: 0.75rem 0;
        }

        .progress-bar-mini {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .progress-text-mini {
            font-size: 0.8rem;
            color: var(--gray-600);
            font-weight: 600;
        }

        .incentives-table-container {
            flex: 1;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: auto;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-200);
        }
        
        .incentives-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .incentives-table thead {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--gray-50);
        }
        
        .incentives-table th {
            background: var(--gray-50);
            padding: 1.25rem 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200);
            text-align: left;
            white-space: nowrap;
        }
        
        .incentives-table td {
            padding: 1.25rem 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }

        .incentives-table tr:last-child td {
            border-bottom: none;
        }

        .incentives-table tbody tr {
            transition: all 0.2s ease;
        }

        .incentives-table tbody tr:hover {
            background: var(--gray-50);
        }

        .top-performer {
            background: var(--warning-light) !important;
        }

        .rank-cell {
            text-align: center;
        }

        .rank-badge-table {
            padding: 0.5rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .rank-badge-table.gold {
            background-color: var(--gold);
            color: var(--gray-900);
        }

        .rank-badge-table.silver {
            background-color: var(--silver);
            color: var(--gray-900);
        }

        .rank-badge-table.bronze {
            background-color: var(--bronze);
            color: white;
        }

        .position-badge {
            padding: 0.5rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .position-manager {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .position-supervisor {
            background-color: var(--info-light);
            color: var(--info);
        }

        .position-agent {
            background-color: var(--success-light);
            color: var(--success);
        }

        .amount-cell {
            font-family: 'Inter', monospace;
            font-weight: 600;
            white-space: nowrap;
        }

        .progress-wrapper {
            width: 100%;
            min-width: 180px;
        }

        .progress-container {
            width: 100%;
            height: 0.75rem;
            background-color: var(--gray-200);
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(to right, var(--primary), var(--primary-hover));
            border-radius: 9999px;
            transition: width 0.5s ease;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-needed {
            background-color: var(--danger-light);
            color: var(--danger);
        }

        .status-exceeded {
            background-color: var(--success-light);
            color: var(--success);
        }

        .edit-form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .edit-form input[type="number"] {
            width: 140px;
            padding: 0.625rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            color: var(--gray-700);
            font-weight: 500;
        }

        .edit-form input[type="number"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn-update {
            padding: 0.625rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            box-shadow: var(--shadow-sm);
        }

        .btn-update:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .alert {
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid;
        }

        .alert-success {
            background-color: var(--success-light);
            color: var(--success);
            border-color: #bbf7d0;
        }

        .alert-error {
            background-color: var(--danger-light);
            color: var(--danger);
            border-color: #fecaca;
        }

        .total-row td {
            background: var(--gray-50) !important;
            font-weight: 700;
            border-top: 3px solid var(--gray-300);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-dialog {
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h5 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn-secondary {
            padding: 0.75rem 1.5rem;
            background: var(--gray-100);
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            border-color: var(--gray-400);
        }

        .btn-edit {
            padding: 0.5rem 1rem;
            background: var(--warning);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 0.5rem;
        }

        .btn-edit:hover {
            background: #d97706;
        }

        .btn-delete {
            padding: 0.5rem 1rem;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-400);
            padding: 0.5rem;
            border-radius: var(--border-radius);
            transition: all 0.2s ease;
        }

        .close:hover {
            color: var(--gray-600);
            background: var(--gray-100);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .custom-tour-input {
            display: none;
            margin-top: 0.75rem;
        }

        .custom-tour-input.show {
            display: block;
        }

        .global-update-notice {
            background: var(--info-light);
            border: 1px solid var(--info);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            color: var(--info);
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tours-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
        }

        .tours-table th,
        .tours-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .tours-table th {
            background: var(--gray-50);
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--gray-700);
        }

        .tours-table td {
            font-size: 0.875rem;
        }

        .number-input {
            text-align: right;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .incentives-page {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                flex-wrap: wrap;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-item {
                min-width: auto;
                flex-direction: column;
                align-items: stretch;
                gap: 0.25rem;
            }

            .filter-item label {
                font-size: 0.8rem;
            }

            .filter-actions {
                margin-left: 0;
                justify-content: stretch;
                gap: 0.5rem;
            }

            .btn-filter-mini,
            .btn-reset-mini {
                flex: 1;
                justify-content: center;
            }

            .top-performers {
                grid-template-columns: 1fr;
            }

            .performer-card {
                min-height: auto;
                padding: 1.5rem;
                gap: 1rem;
            }

            .rank-badge {
                width: 3rem;
                height: 3rem;
                font-size: 0.875rem;
            }

            .profile-picture,
            .profile-initials {
                width: 3rem;
                height: 3rem;
            }

            .performer-info h4 {
                font-size: 1.125rem;
            }

            .sales-amount {
                font-size: 1.25rem;
            }

            .progress-mini {
                width: 120px;
            }

            .incentives-table-container {
                margin: 0 -1rem;
                border-radius: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="incentives-page">
                <div class="page-header">
                    <h2>
                        <i class="fas fa-trophy"></i> Sales Incentive Performance
                    </h2>
                    <div class="header-actions">
                        <?php if ($isAdmin): ?>
                        <button type="button" class="btn-manage" onclick="showModal('manageTourModal')">
                            <i class="fas fa-cog"></i> Manage Tours
                        </button>
                        <button type="button" class="btn-add" onclick="showModal('addTourModal')">
                            <i class="fas fa-plus"></i> Add Tour Target
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Global Update Notice -->
                <?php if ($isAuthorized): ?>
                <div class="global-update-notice">
                    <i class="fas fa-info-circle"></i>
                    <span><strong>Note:</strong> When you update sales amounts, they will be applied across ALL tour types and destinations for that agent.</span>
                </div>
                <?php endif; ?>
                
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--primary-light); color: var(--primary);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Agents</h3>
                            <p><?php echo $agent_count; ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Sales</h3>
                            <p>₱<?php echo number_format($total_sales_all, 0); ?></p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-target"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Overall Progress</h3>
                            <p><?php echo number_format($overall_progress, 1); ?>%</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--info-light); color: var(--info);">
                            <i class="fas fa-medal"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Achieved Target</h3>
                            <p><?php echo $agents_on_track; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Minimalistic Filters -->
                <div class="filters-container">
                    <form class="filter-form" method="GET" action="">
                        <?php if ($user['role'] === 'admin'): ?>
                        <div class="filter-item">
                            <label for="team_id">Team:</label>
                            <select name="team_id" id="team_id">
                                <option value="all" <?php echo $selected_team === 'all' ? 'selected' : ''; ?>>All Teams</option>
                                <?php 
                                $teams_result->data_seek(0);
                                while ($team = $teams_result->fetch_assoc()): ?>
                                    <option value="<?php echo $team['id']; ?>" <?php echo $selected_team == $team['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="filter-item">
                            <label for="agent_id">Agent:</label>
                            <select name="agent_id" id="agent_id">
                                <option value="all" <?php echo $selected_agent === 'all' ? 'selected' : ''; ?>>All Agents</option>
                                <?php 
                                $agents_result->data_seek(0);
                                while ($agent = $agents_result->fetch_assoc()): ?>
                                    <option value="<?php echo $agent['id']; ?>" <?php echo $selected_agent == $agent['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($agent['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label for="position">Position:</label>
                            <select name="position" id="position">
                                <option value="all" <?php echo $selected_position === 'all' ? 'selected' : ''; ?>>All Positions</option>
                                <option value="Agent" <?php echo $selected_position === 'Agent' ? 'selected' : ''; ?>>Agent</option>
                                <option value="Supervisor" <?php echo $selected_position === 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="Manager" <?php echo $selected_position === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label for="tour_type">Tour Type:</label>
                            <select name="tour_type" id="tour_type">
                                <?php 
                                $tours_result->data_seek(0);
                                $tour_types = [];
                                while ($tour = $tours_result->fetch_assoc()) {
                                    if (!in_array($tour['tour_type'], $tour_types)) {
                                        $tour_types[] = $tour['tour_type'];
                                        echo '<option value="' . htmlspecialchars($tour['tour_type']) . '"' . 
                                             ($selected_tour_type === $tour['tour_type'] ? ' selected' : '') . '>' . 
                                             htmlspecialchars($tour['tour_type']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label for="destination">Destination:</label>
                            <select name="destination" id="destination">
                                <?php 
                                $tours_result->data_seek(0);
                                while ($tour = $tours_result->fetch_assoc()) {
                                    if ($tour['tour_type'] === $selected_tour_type) {
                                        echo '<option value="' . htmlspecialchars($tour['destination']) . '"' . 
                                             ($selected_destination === $tour['destination'] ? ' selected' : '') . '>' . 
                                             htmlspecialchars($tour['destination']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <a href="incentives.php" class="btn-reset-mini">
                                <i class="fas fa-undo"></i> Clear
                            </a>
                            <button type="submit" class="btn-filter-mini">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Rankings Section -->
                <div class="rankings-section">
                    <div class="rankings-header">
                        <h3><i class="fas fa-medal"></i> Top Performers - <?php echo htmlspecialchars($selected_tour_type . ' to ' . $selected_destination); ?></h3>
                    </div>
                    <div class="top-performers">
                        <?php 
                        $top_performers = array_slice($all_agents, 0, 5);
                        foreach ($top_performers as $index => $performer): 
                            $rank = $index + 1;
                            $profile_pic = getProfilePicture($performer['profile_picture'], $performer['name']);
                        ?>
                            <div class="performer-card rank-<?php echo $rank; ?>">
                                <div class="performer-avatar">
                                    <div class="rank-badge">
                                        <?php if ($rank == 1): ?>
                                            <i class="fas fa-crown"></i>
                                        <?php elseif ($rank == 2): ?>
                                            <i class="fas fa-medal"></i>
                                        <?php elseif ($rank == 3): ?>
                                            <i class="fas fa-award"></i>
                                        <?php else: ?>
                                            <?php echo $rank; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (strpos($profile_pic, '/') !== false || strpos($profile_pic, '.') !== false): ?>
                                        <img src="<?php echo $profile_pic; ?>" alt="<?php echo htmlspecialchars($performer['name']); ?>" class="profile-picture">
                                    <?php else: ?>
                                        <div class="profile-initials"><?php echo $profile_pic; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="performer-info">
                                    <h4><?php echo htmlspecialchars($performer['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($performer['team_name'] ?? 'No Team'); ?></p>
                                    <p><?php echo htmlspecialchars($performer['position']); ?></p>
                                </div>
                                <div class="performer-stats">
                                    <div class="sales-amount">₱<?php echo number_format($performer['total_sales'], 0); ?></div>
                                    <div class="progress-mini">
                                        <div class="progress-bar-mini" style="width: <?php echo $performer['progress']; ?>%"></div>
                                    </div>
                                    <div class="progress-text-mini"><?php echo number_format($performer['progress'], 1); ?>% of target</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="incentives-table-container">
                    <table class="incentives-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Agent</th>
                                <th>Team</th>
                                <th>Position</th>
                                <th>Current Sales</th>
                                <th>Target</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <?php if ($isAuthorized): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_agents)): ?>
                            <tr>
                                <td colspan="<?php echo $isAuthorized ? 9 : 8; ?>" style="text-align: center; padding: 3rem;">
                                    <div style="color: var(--gray-400);">
                                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                        <p style="font-size: 1.125rem;">No agents found matching the current filters.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($all_agents as $index => $agent): 
                                    $rank = $index + 1;
                                    $status = $agent['total_sales'] >= $agent['target'] ? 'exceeded' : 'needed';
                                ?>
                                <tr class="<?php echo $rank <= 3 ? 'top-performer' : ''; ?>">
                                    <td class="rank-cell">
                                        <?php if ($rank == 1): ?>
                                            <span class="rank-badge-table gold"><i class="fas fa-crown"></i> <?php echo getRankSuffix($rank); ?></span>
                                        <?php elseif ($rank == 2): ?>
                                            <span class="rank-badge-table silver"><i class="fas fa-medal"></i> <?php echo getRankSuffix($rank); ?></span>
                                        <?php elseif ($rank == 3): ?>
                                            <span class="rank-badge-table bronze"><i class="fas fa-award"></i> <?php echo getRankSuffix($rank); ?></span>
                                        <?php else: ?>
                                            <span class="rank-badge-table"><?php echo getRankSuffix($rank); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($agent['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($agent['team_name'] ?? 'No Team'); ?></td>
                                    <td>
                                        <span class="position-badge position-<?php echo strtolower($agent['position']); ?>">
                                            <?php echo htmlspecialchars($agent['position']); ?>
                                        </span>
                                    </td>
                                    <td class="amount-cell">₱<?php echo number_format($agent['total_sales'], 2); ?></td>
                                    <td class="amount-cell">₱<?php echo number_format($agent['target'], 2); ?></td>
                                    <td>
                                        <div class="progress-wrapper">
                                            <div class="progress-container">
                                                <div class="progress-bar" style="width: <?php echo $agent['progress']; ?>%"></div>
                                            </div>
                                            <div class="progress-text">
                                                <span><?php echo number_format($agent['progress'], 1); ?>%</span>
                                                <span>₱<?php echo number_format($agent['target'] - $agent['total_sales'], 0); ?> needed</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $status; ?>">
                                            <?php if ($status === 'exceeded'): ?>
                                                <i class="fas fa-check"></i> Target Achieved
                                            <?php else: ?>
                                                <i class="fas fa-clock"></i> In Progress
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <?php if ($isAuthorized): ?>
                                    <td>
                                        <form method="POST" class="edit-form">
                                            <input type="hidden" name="user_id" value="<?php echo $agent['id']; ?>">
                                            <input type="number" 
                                                   name="total_sales" 
                                                   value="<?php echo $agent['total_sales']; ?>" 
                                                   step="0.01" 
                                                   min="0"
                                                   placeholder="Enter sales amount"
                                                   title="This amount will be applied to ALL tour types and destinations">
                                            <button type="submit" name="update_sales" class="btn-update">
                                                <i class="fas fa-save"></i> Update All
                                            </button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                                
                                <tr class="total-row">
                                    <td colspan="4">
                                        <strong><i class="fas fa-calculator"></i> TOTALS (<?php echo $agent_count; ?> agents)</strong>
                                    </td>
                                    <td class="amount-cell">
                                        <strong>₱<?php echo number_format($total_sales_all, 2); ?></strong>
                                    </td>
                                    <td class="amount-cell">
                                        <strong>₱<?php echo number_format($total_target_all, 2); ?></strong>
                                    </td>
                                    <td>
                                        <div class="progress-wrapper">
                                            <div class="progress-container">
                                                <div class="progress-bar" style="width: <?php echo $overall_progress; ?>%"></div>
                                            </div>
                                            <div class="progress-text">
                                                <span><strong><?php echo number_format($overall_progress, 1); ?>%</strong></span>
                                                <span><strong>₱<?php echo number_format($total_target_all - $total_sales_all, 0); ?> needed</strong></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td colspan="<?php echo $isAuthorized ? 2 : 1; ?>">
                                        <span class="status-badge status-<?php echo $total_sales_all >= $total_target_all ? 'exceeded' : 'needed'; ?>">
                                            <?php if ($total_sales_all >= $total_target_all): ?>
                                                <i class="fas fa-trophy"></i> Team Target Achieved!
                                            <?php else: ?>
                                                <i class="fas fa-target"></i> Team Target In Progress
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Tour Target Modal -->
    <?php if ($isAdmin): ?>
    <div class="modal" id="addTourModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5><i class="fas fa-plus-circle"></i> Add New Tour Target</h5>
                    <button type="button" class="close" onclick="hideModal('addTourModal')">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="new_tour_type">Tour Type</label>
                            <select class="form-control" id="new_tour_type" name="new_tour_type" onchange="toggleCustomTourInput()" required>
                                <option value="">Select Tour Type</option>
                                <?php 
                                $existing_tour_types_result->data_seek(0);
                                while ($tour_type = $existing_tour_types_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($tour_type['tour_type']); ?>">
                                        <?php echo htmlspecialchars($tour_type['tour_type']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="custom">+ Add New Tour Type</option>
                            </select>
                            <div class="custom-tour-input" id="customTourInput">
                                <input type="text" class="form-control" id="custom_tour_type" name="custom_tour_type" placeholder="Enter new tour type">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_destination">Destination</label>
                            <input type="text" class="form-control" id="new_destination" name="new_destination" placeholder="e.g., Palawan, Thailand, Japan" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="agent_target">Agent Target (₱)</label>
                                <input type="text" class="form-control number-input" id="agent_target" name="agent_target" placeholder="500,000" required>
                            </div>
                            <div class="form-group">
                                <label for="supervisor_target">Supervisor Target (₱)</label>
                                <input type="text" class="form-control number-input" id="supervisor_target" name="supervisor_target" placeholder="800,000" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="manager_target">Manager Target (₱)</label>
                            <input type="text" class="form-control number-input" id="manager_target" name="manager_target" placeholder="1,000,000" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="hideModal('addTourModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="add_tour_target" class="btn-add">
                            <i class="fas fa-plus"></i> Add Target
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Tours Modal -->
    <div class="modal" id="manageTourModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5><i class="fas fa-cog"></i> Manage Tour Targets</h5>
                    <button type="button" class="close" onclick="hideModal('manageTourModal')">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <table class="tours-table">
                        <thead>
                            <tr>
                                <th>Tour Type</th>
                                <th>Destination</th>
                                <th>Agent Target</th>
                                <th>Supervisor Target</th>
                                <th>Manager Target</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $all_tour_targets_result->data_seek(0);
                            while ($tour = $all_tour_targets_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tour['tour_type']); ?></td>
                                    <td><?php echo htmlspecialchars($tour['destination']); ?></td>
                                    <td>₱<?php echo number_format($tour['agent_target'], 0); ?></td>
                                    <td>₱<?php echo number_format($tour['supervisor_target'], 0); ?></td>
                                    <td>₱<?php echo number_format($tour['manager_target'], 0); ?></td>
                                    <td>
                                        <button type="button" class="btn-edit" onclick="editTour(<?php echo $tour['id']; ?>, '<?php echo htmlspecialchars($tour['tour_type']); ?>', '<?php echo htmlspecialchars($tour['destination']); ?>', <?php echo $tour['agent_target']; ?>, <?php echo $tour['supervisor_target']; ?>, <?php echo $tour['manager_target']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this tour target?')">
                                            <input type="hidden" name="tour_id" value="<?php echo $tour['id']; ?>">
                                            <button type="submit" name="delete_tour_target" class="btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="hideModal('manageTourModal')">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Tour Target Modal -->
    <div class="modal" id="editTourModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5><i class="fas fa-edit"></i> Edit Tour Target</h5>
                    <button type="button" class="close" onclick="hideModal('editTourModal')">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST" id="editTourForm">
                    <div class="modal-body">
                        <input type="hidden" name="tour_id" id="edit_tour_id">
                        
                        <div class="form-group">
                            <label for="edit_tour_type">Tour Type</label>
                            <input type="text" class="form-control" id="edit_tour_type" name="edit_tour_type" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_destination">Destination</label>
                            <input type="text" class="form-control" id="edit_destination" name="edit_destination" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_agent_target">Agent Target (₱)</label>
                                <input type="text" class="form-control number-input" id="edit_agent_target" name="edit_agent_target" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_supervisor_target">Supervisor Target (₱)</label>
                                <input type="text" class="form-control number-input" id="edit_supervisor_target" name="edit_supervisor_target" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_manager_target">Manager Target (₱)</label>
                            <input type="text" class="form-control number-input" id="edit_manager_target" name="edit_manager_target" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="hideModal('editTourModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="edit_tour_target" class="btn-add">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // Dynamic destination filtering
    document.getElementById('tour_type').addEventListener('change', function() {
        const tourType = this.value;
        const destinationSelect = document.getElementById('destination');
        
        // Get all tour data from PHP
        const tours = <?php 
        $tours_result->data_seek(0);
        $tours_data = [];
        while ($tour = $tours_result->fetch_assoc()) {
            $tours_data[] = $tour;
        }
        echo json_encode($tours_data);
        ?>;
        
        destinationSelect.innerHTML = '';
        
        tours.forEach(tour => {
            if (tour.tour_type === tourType) {
                const option = document.createElement('option');
                option.value = tour.destination;
                option.textContent = tour.destination;
                destinationSelect.appendChild(option);
            }
        });
    });

    // Auto-submit form when filters change
    document.querySelectorAll('#team_id, #agent_id, #position, #tour_type, #destination').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });

    // Form validation with global update warning
    document.querySelectorAll('.edit-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const salesInput = this.querySelector('input[name="total_sales"]');
            const salesValue = parseFloat(salesInput.value);
            
            if (isNaN(salesValue) || salesValue < 0) {
                e.preventDefault();
                alert('Please enter a valid sales amount (0 or greater).');
                salesInput.focus();
                return false;
            }
            
            if (salesValue > 10000000) {
                if (!confirm('You entered a large amount (₱' + salesValue.toLocaleString() + '). Are you sure this is correct?')) {
                    e.preventDefault();
                    return false;
                }
            }

            // Confirm global update
            if (!confirm('This will update the sales amount for ALL tour types and destinations for this agent. Continue?')) {
                e.preventDefault();
                return false;
            }
        });
    });

    // Number formatting for currency inputs
    function formatNumber(input) {
        let value = input.value.replace(/,/g, '');
        if (!isNaN(value) && value !== '') {
            input.value = parseFloat(value).toLocaleString();
        }
    }

    function unformatNumber(input) {
        input.value = input.value.replace(/,/g, '');
    }

    // Add event listeners to number inputs
    document.querySelectorAll('.number-input').forEach(input => {
        input.addEventListener('blur', function() {
            formatNumber(this);
        });
        
        input.addEventListener('focus', function() {
            unformatNumber(this);
        });
    });

    // Modal functionality
    function showModal(modalId) {
        document.getElementById(modalId).classList.add('show');
    }

    function hideModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    // Edit tour function
    function editTour(id, tourType, destination, agentTarget, supervisorTarget, managerTarget) {
        document.getElementById('edit_tour_id').value = id;
        document.getElementById('edit_tour_type').value = tourType;
        document.getElementById('edit_destination').value = destination;
        document.getElementById('edit_agent_target').value = agentTarget.toLocaleString();
        document.getElementById('edit_supervisor_target').value = supervisorTarget.toLocaleString();
        document.getElementById('edit_manager_target').value = managerTarget.toLocaleString();
        
        hideModal('manageTourModal');
        showModal('editTourModal');
    }

    // Toggle custom tour type input
    function toggleCustomTourInput() {
        const select = document.getElementById('new_tour_type');
        const customInput = document.getElementById('customTourInput');
        const customField = document.getElementById('custom_tour_type');
        
        if (select.value === 'custom') {
            customInput.classList.add('show');
            customField.required = true;
            customField.focus();
        } else {
            customInput.classList.remove('show');
            customField.required = false;
            customField.value = '';
        }
    }

    // Handle form submission for custom tour type
    document.querySelector('#addTourModal form').addEventListener('submit', function(e) {
        const tourTypeSelect = document.getElementById('new_tour_type');
        const customTourType = document.getElementById('custom_tour_type');
        
        if (tourTypeSelect.value === 'custom') {
            if (!customTourType.value.trim()) {
                e.preventDefault();
                alert('Please enter a custom tour type.');
                customTourType.focus();
                return false;
            }
            // Set the custom value as the tour type
            tourTypeSelect.value = customTourType.value.trim();
        }

        // Convert formatted numbers back to numeric values
        const agentTarget = document.getElementById('agent_target');
        const supervisorTarget = document.getElementById('supervisor_target');
        const managerTarget = document.getElementById('manager_target');
        
        agentTarget.value = agentTarget.value.replace(/,/g, '');
        supervisorTarget.value = supervisorTarget.value.replace(/,/g, '');
        managerTarget.value = managerTarget.value.replace(/,/g, '');
    });

    // Handle edit form submission
    document.getElementById('editTourForm').addEventListener('submit', function(e) {
        // Convert formatted numbers back to numeric values
        const agentTarget = document.getElementById('edit_agent_target');
        const supervisorTarget = document.getElementById('edit_supervisor_target');
        const managerTarget = document.getElementById('edit_manager_target');
        
        agentTarget.value = agentTarget.value.replace(/,/g, '');
        supervisorTarget.value = supervisorTarget.value.replace(/,/g, '');
        managerTarget.value = managerTarget.value.replace(/,/g, '');
    });

    // Auto-hide messages
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    </script>
</body>
</html>