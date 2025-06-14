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

// Initialize success and error messages
$success_message = '';
$error_message = '';

// Establish database connection using the function from database.php
$conn = getDbConnection();

// Ensure incentives table exists
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

// Ensure tour_targets table exists
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

// Ensure teams table exists
$conn->query("CREATE TABLE IF NOT EXISTS teams (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add default team if none exists
$result = $conn->query("SELECT COUNT(*) as count FROM teams");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("INSERT INTO teams (name) VALUES ('Default Team')");
}

// Make sure users table has team_id and position columns
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'team_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN team_id INT(11) DEFAULT 1");
}

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'position'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN position VARCHAR(50) DEFAULT 'Agent'");
}

// Update users with default team if not set
$conn->query("UPDATE users SET team_id = 1 WHERE team_id IS NULL");

// Get all teams for the dropdown
$teams_result = $conn->query("SELECT id, name FROM teams ORDER BY name");

// Get all tour destinations for the dropdown
$conn->query("INSERT IGNORE INTO tour_targets (tour_type, destination, agent_target, supervisor_target, manager_target) VALUES 
    ('Local Tour', 'Boracay', 500000, 800000, 1000000),
    ('Local Tour', 'Baguio', 400000, 600000, 800000),
    ('International Tour', 'Malaysia/Indonesia', 800000, 1200000, 1500000),
    ('International Tour', 'Singapore', 600000, 900000, 1200000)");
$tours_result = $conn->query("SELECT DISTINCT tour_type, destination FROM tour_targets ORDER BY tour_type, destination");

// Get filter parameters
$selected_team = isset($_GET['team_id']) ? $_GET['team_id'] : ($user['role'] === 'manager' ? $user['team_id'] : 'all');
$selected_agent = isset($_GET['agent_id']) ? $_GET['agent_id'] : 'all';
$selected_tour_type = isset($_GET['tour_type']) ? $_GET['tour_type'] : 'Local Tour';
$selected_destination = isset($_GET['destination']) ? $_GET['destination'] : 'Boracay';

// Handle form submission for updating sales
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAuthorized) {
    if (isset($_POST['update_sales'])) {
        try {
            $target_user_id = $_POST['user_id'];
            $total_sales = $_POST['total_sales'];
            $incentive_type = $_POST['incentive_type'];
            $destination = $_POST['destination'];
            
            // Update or insert incentive record
            $stmt = $conn->prepare("INSERT INTO incentives (user_id, position, total_sales, incentive_type, destination) 
                                   VALUES (?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE total_sales = ?, incentive_type = ?, destination = ?");
            
            $position = getUserPosition($target_user_id);
            
            $stmt->bind_param("isssssss", $target_user_id, $position, $total_sales, $incentive_type, $destination, 
                             $total_sales, $incentive_type, $destination);
            $stmt->execute();
            
            $success_message = "Sales data updated successfully for " . getUserNameById($target_user_id);
        } catch (Exception $e) {
            $error_message = "Error updating sales data: " . $e->getMessage();
            error_log("Error updating incentives: " . $e->getMessage());
        }
    }
}

// Get tour targets
$tour_targets_query = "SELECT * FROM tour_targets WHERE tour_type = ? AND destination = ?";
$tour_targets_stmt = $conn->prepare($tour_targets_query);
$tour_targets_stmt->bind_param("ss", $selected_tour_type, $selected_destination);
$tour_targets_stmt->execute();
$tour_targets_result = $tour_targets_stmt->get_result();
$tour_targets = $tour_targets_result->fetch_assoc();

// If no targets found, use default values
if (!$tour_targets) {
    $tour_targets = [
        'agent_target' => 500000,
        'supervisor_target' => 800000,
        'manager_target' => 1000000
    ];
}

// Build the users query based on filters
$query = "SELECT 
    u.id, 
    u.name, 
    u.position,
    u.team_id,
    t.name as team_name,
    COALESCE(i.total_sales, 0) as total_sales,
    COALESCE(i.incentive_type, ?) as incentive_type,
    COALESCE(i.destination, ?) as destination
FROM users u 
LEFT JOIN teams t ON u.team_id = t.id
LEFT JOIN incentives i ON u.id = i.user_id
WHERE u.role != 'admin'";

// Add team filter
if ($selected_team !== 'all') {
    $query .= " AND u.team_id = ?";
}

// Add agent filter
if ($selected_agent !== 'all') {
    $query .= " AND u.id = ?";
}

// For managers, only show their team
if ($user['role'] === 'manager') {
    $query .= " AND u.team_id = ?";
}

$query .= " ORDER BY u.position, u.name";

// Prepare and execute the query
$stmt = $conn->prepare($query);

// Create parameter array
$param_types = "ss"; // For incentive_type and destination
$param_values = array($selected_tour_type, $selected_destination);

if ($selected_team !== 'all') {
    $param_types .= "i";
    $param_values[] = $selected_team;
}

if ($selected_agent !== 'all') {
    $param_types .= "i";
    $param_values[] = $selected_agent;
}

if ($user['role'] === 'manager') {
    $param_types .= "i";
    $param_values[] = $user['team_id'];
}

// Create array of references
$params = array($param_types);
foreach ($param_values as &$value) {
    $params[] = &$value;
}

// Get agents for the dropdown if needed
if ($user['role'] === 'admin' || $user['role'] === 'manager') {
    $agents_query = "SELECT id, name FROM users WHERE role != 'admin'";
    if ($user['role'] === 'manager') {
        $agents_query .= " AND team_id = ?";
    }
    if ($selected_team !== 'all') {
        $agents_query .= " AND team_id = ?";
    }
    $agents_query .= " ORDER BY name";
    
    $agents_stmt = $conn->prepare($agents_query);
    
    if ($user['role'] === 'manager' && $selected_team !== 'all') {
        $agents_stmt->bind_param("ii", $user['team_id'], $selected_team);
    } else if ($user['role'] === 'manager') {
        $agents_stmt->bind_param("i", $user['team_id']);
    } else if ($selected_team !== 'all') {
        $agents_stmt->bind_param("i", $selected_team);
    }
    
    $agents_stmt->execute();
    $agents_result = $agents_stmt->get_result();
}

// Execute the main query
call_user_func_array(array($stmt, 'bind_param'), $params);
$stmt->execute();
$result = $stmt->get_result();

// Get all teams for filter
$teams_query = "SELECT * FROM teams ORDER BY name";
$teams_result = $conn->query($teams_query);

// Get all tour types and destinations
$tours_query = "SELECT DISTINCT tour_type, destination FROM tour_targets ORDER BY tour_type, destination";
$tours_result = $conn->query($tours_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Agent Incentive Performance - Inners SPARC Realty Corporation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4338ca;
            --primary-light: #6366f1;
            --primary-dark: #3730a3;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #ca8a04;
            --background: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --text-light: #64748b;
            --border: #e2e8f0;
            --radius: 0.75rem;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        body {
            background-color: var(--background);
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .main-content {
            padding: 1.5rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        .content-wrapper {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .page-header {
            padding: 2rem;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .page-header h2 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .filter-section {
            padding: 1.5rem 2rem;
            background: var(--background);
            border-bottom: 1px solid var(--border);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .filter-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        .filter-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            background-color: var(--surface);
            color: var(--text);
            font-size: 0.875rem;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            transition: all 0.2s;
        }

        .filter-group select:hover {
            border-color: var(--primary);
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .table-container {
            padding: 1.5rem 2rem;
            overflow-x: auto;
        }

        .incentives-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.875rem;
        }

        .incentives-table th {
            background: var(--background);
            padding: 1rem;
            font-weight: 600;
            color: var(--text);
            text-align: left;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }

        .incentives-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            vertical-align: middle;
        }

        .incentives-table tr:hover td {
            background-color: var(--background);
        }

        .position-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .position-manager {
            background-color: #818cf8;
            color: white;
        }

        .position-supervisor {
            background-color: #f472b6;
            color: white;
        }

        .position-agent {
            background-color: #34d399;
            color: white;
        }

        .amount-cell {
            font-family: 'Inter', monospace;
            font-weight: 500;
            white-space: nowrap;
        }

        .progress-wrapper {
            width: 100%;
            min-width: 150px;
        }

        .progress-container {
            width: 100%;
            height: 0.5rem;
            background-color: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(to right, var(--primary-light), var(--primary));
            border-radius: 9999px;
            transition: width 0.5s ease;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .status-needed {
            background-color: #fee2e2;
            color: var(--danger);
        }

        .status-exceeded {
            background-color: #dcfce7;
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
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: var(--text);
            transition: all 0.2s;
        }

        .edit-form input[type="number"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .edit-form button {
            padding: 0.625rem 1.25rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .edit-form button:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .edit-form button:active {
            transform: translateY(0);
        }

        .total-row td {
            background: var(--background) !important;
            font-weight: 600;
            border-top: 2px solid var(--border);
        }

        .total-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text);
        }

        @media (max-width: 1024px) {
            .main-content {
                padding: 1rem;
            }

            .page-header,
            .filter-section,
            .table-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
 
<div class="min-h-screen bg-gray-50 py-4">
  <div class="container mx-auto px-4 sm:px-6 max-w-[95%]">
    <!-- Page Title -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl shadow-lg p-4 sm:p-6 mb-4 sm:mb-6">
      <h1 class="text-2xl sm:text-4xl font-bold text-white mb-1">Memo Management Dashboard</h1>
      <p class="text-base sm:text-xl text-blue-100">Create and manage your memos efficiently</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
      <!-- Memo Submission Form -->
      {% if request.user.is_superuser %}
      <div class="lg:col-span-3">
        <div class="bg-white rounded-xl shadow-lg p-4 sticky top-4">
          <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-3 flex items-center">
            <svg class="w-5 h-5 sm:w-6 sm:h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Create New Memo
          </h2>
          <form method="post" enctype="multipart/form-data" id="memo-form" class="space-y-3">
            {% csrf_token %}
            <div class="space-y-1">
              <label for="id_title" class="block text-base sm:text-lg font-medium text-gray-700">Title</label>
              {{ form.title|add_class:"mt-1 block w-full rounded-lg border-2 border-gray-300 px-3 sm:px-4 py-2 text-base shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition duration-150" }}
            </div>
            <div class="space-y-1">
              <label for="id_description" class="block text-base sm:text-lg font-medium text-gray-700">Description</label>
              {{ form.description|add_class:"mt-1 block w-full rounded-lg border-2 border-gray-300 px-3 sm:px-4 py-2 text-base shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition duration-150" }}
            </div>
            <div class="space-y-1">
              <label for="id_when" class="block text-base sm:text-lg font-medium text-gray-700">When</label>
              {{ form.when|add_class:"mt-1 block w-full rounded-lg border-2 border-gray-300 px-3 sm:px-4 py-2 text-base shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition duration-150" }}
            </div>
            <div class="space-y-1">
              <label for="id_where" class="block text-base sm:text-lg font-medium text-gray-700">Where (Optional)</label>
              {{ form.where|add_class:"mt-1 block w-full rounded-lg border-2 border-gray-300 px-3 sm:px-4 py-2 text-base shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition duration-150" }}
            </div>
            <div class="space-y-1">
              <label for="id_file" class="block text-base sm:text-lg font-medium text-gray-700">Upload File</label>
              <div class="mt-1 flex justify-center px-3 sm:px-4 py-3 border-2 border-gray-300 border-dashed rounded-lg">
                <div class="space-y-1 text-center">
                  <svg class="mx-auto h-6 w-6 sm:h-8 sm:w-8 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  {{ form.file|add_class:"sr-only" }}
                  <div class="flex text-sm sm:text-base text-gray-600 justify-center">
                    <label for="id_file" class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                      <span>Upload a file</span>
                    </label>
                    <p class="pl-1">or drag and drop</p>
                  </div>
                  <p class="text-sm sm:text-base text-gray-500">PDF, DOC up to 10MB</p>
                </div>
              </div>
            </div>
            <div class="pt-2">
              <button type="submit" class="w-full flex justify-center items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-150 text-lg font-semibold">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Create Memo
              </button>
            </div>
          </form>
        </div>
      </div>
      {% endif %}

      <!-- Memos List Section -->
      <div class="{% if request.user.is_superuser %}lg:col-span-9{% else %}lg:col-span-12{% endif %}">
        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-4 sm:mb-6">
          <h3 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-4 flex items-center">
            <svg class="w-6 h-6 sm:w-7 sm:h-7 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            Filter Memos
          </h3>
          <form method="get" class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="space-y-1">
              <label for="q" class="block text-base sm:text-lg font-medium text-gray-700">Search Title</label>
              <div class="mt-1 relative rounded-lg shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 sm:pl-4 flex items-center pointer-events-none">
                  <svg class="h-5 w-5 sm:h-6 sm:w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </div>
                <input type="text" 
                       name="q" 
                       id="q" 
                       value="{{ request.GET.q }}"
                       placeholder="Search memos..."
                       class="block w-full pl-10 sm:pl-12 pr-3 sm:pr-4 py-2 sm:py-3 border-2 border-gray-300 rounded-lg text-base sm:text-lg focus:ring-blue-500 focus:border-blue-500">
              </div>
            </div>
            <div class="space-y-1">
              <label for="date" class="block text-base sm:text-lg font-medium text-gray-700">Filter by Date</label>
              <input type="date" 
                     name="date" 
                     id="date" 
                     value="{{ request.GET.date }}" 
                     class="mt-1 block w-full rounded-lg border-2 border-gray-300 px-3 sm:px-4 py-2 sm:py-3 text-base sm:text-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="col-span-2">
              <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg text-base sm:text-lg font-medium transition duration-150 flex items-center justify-center">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Search
              </button>
            </div>
          </form>
        </div>

        <!-- View Toggle -->
        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-4 sm:mb-6">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h3 class="text-xl sm:text-2xl font-semibold text-gray-800 flex items-center">
              <svg class="w-6 h-6 sm:w-7 sm:h-7 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
              </svg>
              View Options
            </h3>
            <div class="flex flex-wrap gap-2">
              <button type="button" id="listViewBtn" class="view-toggle-btn px-3 sm:px-4 py-2 text-base sm:text-lg font-medium rounded-lg bg-blue-600 text-white">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                List
              </button>
              <button type="button" id="cardViewBtn" class="view-toggle-btn px-3 sm:px-4 py-2 text-base sm:text-lg font-medium rounded-lg text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2zm0 8a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2z" />
                </svg>
                Cards
              </button>
              <button type="button" id="calendarViewBtn" class="view-toggle-btn px-3 sm:px-4 py-2 text-base sm:text-lg font-medium rounded-lg text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Calendar
              </button>
            </div>
          </div>
        </div>

        <!-- Calendar View Container -->
        <div id="calendarViewContainer" class="hidden">
          <div class="calendar-container">
            <!-- Calendar Header -->
            <div class="calendar-toolbar">
              <div class="calendar-navigation">
                <button id="prevDate" class="p-2 hover:bg-gray-100 rounded-lg">
                  <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                  </svg>
                </button>
                <h2 id="currentDate" class="text-2xl font-bold text-gray-800">August 2023</h2>
                <button id="nextDate" class="p-2 hover:bg-gray-100 rounded-lg">
                  <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </button>
                <button id="today" class="ml-4 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg">
                  Today
                </button>
              </div>
              <div class="calendar-view-selector">
                <button data-view="month" class="calendar-view-btn px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white">
                  Month
                </button>
                <button data-view="week" class="calendar-view-btn px-4 py-2 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-100">
                  Week
                </button>
                <button data-view="day" class="calendar-view-btn px-4 py-2 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-100">
                  Day
                </button>
              </div>
            </div>
            
            <!-- Calendar Grid -->
            <div class="p-6">
              <!-- Month View -->
              <div id="monthView" class="calendar-view active">
                <div id="monthGrid" class="grid grid-cols-7 gap-px bg-gray-200">
                  <!-- Calendar days will be inserted here by JavaScript -->
                </div>
              </div>

              <!-- Week View -->
              <div id="weekView" class="calendar-view hidden">
                <div class="flex h-full">
                  <!-- Time column -->
                  <div class="w-20 flex-shrink-0 border-r border-gray-200">
                    <div class="h-16"></div> <!-- Header spacer -->
                    <div id="weekTimeColumn" class="relative">
                      <!-- Time slots will be inserted here -->
                    </div>
                  </div>
                  <!-- Days columns -->
                  <div class="flex-1">
                    <div class="grid grid-cols-7 border-b border-gray-200">
                      <!-- Day headers will be inserted here -->
                    </div>
                    <div id="weekGrid" class="grid grid-cols-7 relative">
                      <!-- Time grid will be inserted here -->
                      <div id="currentTimeIndicator" class="hidden absolute w-full border-t-2 border-red-500 z-50">
                        <div class="absolute -top-2 -left-3 w-3 h-3 rounded-full bg-red-500"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Day View -->
              <div id="dayView" class="calendar-view hidden">
                <div class="flex">
                  <!-- Time column -->
                  <div class="w-20 flex-shrink-0 border-r border-gray-200">
                    <div class="h-16"></div> <!-- Header spacer -->
                    <div id="dayTimeColumn" class="relative">
                      <!-- Time slots will be inserted here -->
                    </div>
                  </div>
                  <!-- Day column -->
                  <div class="flex-1">
                    <div id="dayHeader" class="h-16 border-b border-gray-200">
                      <!-- Day header will be inserted here -->
                    </div>
                    <div id="dayGrid" class="relative">
                      <!-- Time grid will be inserted here -->
                      <div id="dayCurrentTimeIndicator" class="hidden absolute w-full border-t-2 border-red-500 z-50">
                        <div class="absolute -top-2 -left-3 w-3 h-3 rounded-full bg-red-500"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- List View Container -->
        <div id="listViewContainer">
          <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 memo-table">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 sm:px-6 py-3 sm:py-4 text-left text-gray-500 tracking-wider">
                      <button class="sort-btn flex items-center space-x-2 hover:text-gray-700 group" data-sort="title">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div class="flex flex-col items-start">
                          <span class="text-base sm:text-lg font-semibold">Title</span>
                          <span class="text-xs sm:text-sm text-gray-400 font-normal sort-direction">A to Z</span>
                        </div>
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 sort-icon hidden ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                      </button>
                    </th>
                    <th class="px-6 py-4 text-left text-gray-500 tracking-wider">
                      <div class="flex items-center space-x-2">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <div class="flex flex-col">
                          <span class="text-lg font-semibold">View</span>
                          <span class="text-sm text-gray-400 font-normal">Preview file</span>
                        </div>
                      </div>
                    </th>
                    <th class="px-6 py-4 text-left text-gray-500 tracking-wider">
                      <button class="sort-btn flex items-center space-x-2 hover:text-gray-700 group" data-sort="when" data-type="date">
                        <svg class="w-6 h-6 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <div class="flex flex-col items-start">
                          <span class="text-lg font-semibold">When</span>
                          <span class="text-sm text-gray-400 font-normal">Sort by date</span>
                        </div>
                        <svg class="w-5 h-5 sort-icon hidden ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                      </button>
                    </th>
                    <th class="px-6 py-4 text-left text-gray-500 tracking-wider">
                      <button class="sort-btn flex items-center space-x-2 hover:text-gray-700 group" data-sort="where">
                        <svg class="w-6 h-6 text-gray-400 group-hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <div class="flex flex-col items-start">
                          <span class="text-lg font-semibold">Where</span>
                          <span class="text-sm text-gray-400 font-normal">Sort by location</span>
                        </div>
                        <svg class="w-5 h-5 sort-icon hidden ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                      </button>
                    </th>
                    <th class="px-6 py-4 text-left text-gray-500 tracking-wider">
                      <div class="flex items-center space-x-2">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <div class="flex flex-col">
                          <span class="text-lg font-semibold">Actions</span>
                          <span class="text-sm text-gray-400 font-normal">Available options</span>
                        </div>
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                  {% if page_obj %}
                    {% for memo in page_obj %}
                    <tr class="hover:bg-gray-50 transition duration-150">
                      <td class="px-6 py-3 whitespace-nowrap text-xl font-medium text-gray-900">{{ memo.title }}</td>
                      <td class="px-6 py-3 whitespace-nowrap text-xl text-gray-500">
                        {% if memo.file %}
                          {% if memo.file.url|lower|slice:"-4:" in ".pdf,.doc,docx,.txt" %}
                            <a href="{{ memo.file.url }}" target="_blank" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                              <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                              </svg>
                              View
                            </a>
                          {% else %}
                            <span class="text-gray-400">Not viewable</span>
                          {% endif %}
                        {% else %}
                          <span class="text-gray-400">No file</span>
                        {% endif %}
                      </td>
                      <td class="px-6 py-3 whitespace-nowrap text-xl text-gray-500">{{ memo.when|date:"Y-m-d" }}</td>
                      <td class="px-6 py-3 whitespace-nowrap text-xl text-gray-500">{{ memo.where|default:"—" }}</td>
                      <td class="px-6 py-3 whitespace-nowrap space-x-3">
                        <button data-memo-id="{{ memo.id }}" class="view-details inline-flex items-center px-4 py-2 border-2 border-blue-600 text-blue-600 hover:bg-blue-50 rounded-lg text-lg font-medium transition-colors duration-150">
                          <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          </svg>
                          Details
                        </button>
                        {% if request.user.is_superuser %}
                        <button data-memo-id="{{ memo.id }}" class="view-readers inline-flex items-center px-4 py-2 border-2 border-green-600 text-green-600 hover:bg-green-50 rounded-lg text-lg font-medium transition-colors duration-150">
                          <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                          </svg>
                          Readers
                        </button>
                        {% endif %}
                      </td>
                    </tr>
                    {% empty %}
                    <tr>
                      <td colspan="5" class="px-6 py-8 text-center">
                        <div class="flex flex-col items-center">
                          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                          </svg>
                          <p class="mt-2 text-xl text-gray-500">No memos found</p>
                        </div>
                      </td>
                    </tr>
                    {% endfor %}
                  {% else %}
                    <tr>
                      <td colspan="5" class="px-6 py-8 text-center text-gray-500 text-sm bg-gray-50">
                        <div class="flex flex-col items-center">
                          <svg class="h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                          </svg>
                          <p class="text-gray-600">Error loading memos. Please try refreshing the page.</p>
                        </div>
                      </td>
                    </tr>
                  {% endif %}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row justify-center items-center gap-4">
          {% if page_obj.has_previous %}
          <a href="?page={{ page_obj.previous_page_number }}{% if request.GET.q %}&q={{ request.GET.q }}{% endif %}{% if request.GET.date %}&date={{ request.GET.date }}{% endif %}"
             class="w-full sm:w-auto inline-flex items-center justify-center px-4 sm:px-6 py-2 sm:py-3 border-2 border-gray-300 rounded-lg text-base sm:text-xl font-medium text-gray-700 bg-white hover:bg-gray-50 transition duration-150">
            <svg class="w-5 h-5 sm:w-6 sm:h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Previous
          </a>
          {% endif %}

          <span class="text-base sm:text-xl text-gray-700">
            Page {{ page_obj.number }} of {{ page_obj.paginator.num_pages }}
          </span>

          {% if page_obj.has_next %}
          <a href="?page={{ page_obj.next_page_number }}{% if request.GET.q %}&q={{ request.GET.q }}{% endif %}{% if request.GET.date %}&date={{ request.GET.date }}{% endif %}"
             class="w-full sm:w-auto inline-flex items-center justify-center px-4 sm:px-6 py-2 sm:py-3 border-2 border-gray-300 rounded-lg text-base sm:text-xl font-medium text-gray-700 bg-white hover:bg-gray-50 transition duration-150">
            Next
            <svg class="w-5 h-5 sm:w-6 sm:h-6 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </a>
          {% endif %}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modals -->
<div id="detailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl p-0 relative mx-4">
    <!-- Modal Header -->
    <div class="border-b border-gray-200 px-8 py-5">
      <div class="flex items-center justify-between">
        <h3 class="text-3xl font-bold text-gray-900 leading-relaxed" id="memoTitle">Memo Details</h3>
        <button class="text-gray-400 hover:text-gray-600 transition duration-150 p-2" onclick="closeModal('detailsModal')" aria-label="Close modal">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Modal Content -->
    <div class="px-8 py-6 max-h-[calc(100vh-200px)] overflow-y-auto">
      <div id="detailsContent" class="space-y-8">
        <!-- Meta Information -->
        <div class="bg-gray-50 rounded-lg p-6 space-y-3">
          <div class="flex items-center text-gray-700">
            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <p class="text-lg" id="memoDate">Loading...</p>
          </div>
          <div class="flex items-center text-gray-700" id="locationContainer">
            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <p class="text-lg" id="memoLocation">Loading...</p>
          </div>
        </div>

        <!-- Description -->
        <div class="prose prose-lg max-w-none">
          <h4 class="text-2xl font-semibold text-gray-900 mb-4">Description</h4>
          <div id="memoDescription" class="text-lg text-gray-700 whitespace-pre-wrap leading-relaxed">Loading...</div>
        </div>

        <!-- File Attachment -->
        <div id="memoFile" class="hidden">
          <h4 class="text-2xl font-semibold text-gray-900 mb-4">Attachment</h4>
          <a href="#" id="memoFileLink" class="inline-flex items-center px-6 py-3 border-2 border-gray-300 shadow-sm text-lg font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
            <svg class="w-6 h-6 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Download Attachment
          </a>
        </div>

        <!-- Acknowledgment Section -->
        <div id="acknowledgmentSection" class="border-t-2 border-gray-200 pt-8 mt-8" {% if request.user.is_superuser %}style="display: none;"{% endif %}>
          <div id="notAcknowledged" class="hidden">
            <form id="acknowledgmentForm" class="space-y-6">
              <input type="hidden" id="memoId" name="memoId" value="">
              <div class="flex items-start space-x-4">
                <div class="flex items-center h-6 mt-1">
                  <input id="acknowledgmentCheckbox" type="checkbox" class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer" required>
                </div>
                <div class="flex-1">
                  <label for="acknowledgmentCheckbox" class="text-xl text-gray-900 font-medium">
                    Acknowledgment
                  </label>
                  <p class="text-lg text-gray-600 mt-2 leading-relaxed">
                    I confirm that I have read and understood the contents of this memo.
                  </p>
                </div>
              </div>
              <button type="submit" class="w-full inline-flex justify-center items-center px-6 py-4 border-2 border-transparent text-xl font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 transition-colors duration-150">
                <svg class="w-7 h-7 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Submit Acknowledgment
              </button>
            </form>
          </div>
          <div id="alreadyAcknowledged" class="hidden">
            <div class="bg-green-50 rounded-lg p-6">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <svg class="h-8 w-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="ml-4">
                  <h3 class="text-xl font-medium text-green-800">Memo Acknowledged</h3>
                  <div class="mt-2 text-lg text-green-700" id="acknowledgedDate"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="readersModal" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-8 relative mx-4">
    <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition duration-150" onclick="closeModal('readersModal')">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
    <h3 class="text-2xl font-bold text-gray-900 mb-4">Readers</h3>
    <div id="readersContent" class="prose prose-sm max-w-none text-gray-600">
      <!-- AJAX-loaded content -->
    </div>
  </div>
</div>

<!-- Quick Create Event Modal -->
<div id="quickCreateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative mx-4">
    <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-600" onclick="closeQuickCreateModal()">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
    <h3 class="text-xl font-bold text-gray-900 mb-4">Create Memo</h3>
    <form id="quickCreateForm" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Title</label>
        <input type="text" id="quickTitle" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Start</label>
          <input type="datetime-local" id="quickStart" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">End</label>
          <input type="datetime-local" id="quickEnd" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Location</label>
        <input type="text" id="quickLocation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Description</label>
        <textarea id="quickDescription" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
      </div>
      <div class="flex justify-end space-x-3">
        <button type="button" onclick="closeQuickCreateModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
          Cancel
        </button>
        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
          Save
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // View Details Button Click Handler
    document.querySelectorAll('.view-details').forEach(button => {
      button.addEventListener('click', function() {
        const memoId = this.getAttribute('data-memo-id');
        const detailsModal = document.getElementById('detailsModal');
        const detailsContent = document.getElementById('detailsContent');
        
        if (!detailsModal || !detailsContent) {
          console.error('Modal elements not found');
          return;
        }
        
        // Show modal
        detailsModal.classList.remove('hidden');
        
        // Set loading state
        const titleElement = document.getElementById('memoTitle');
        if (titleElement) {
          titleElement.textContent = 'Loading...';
        }

        // Get CSRF token
        const csrftoken = getCookie('csrftoken');
        
        // Fetch memo details
        fetch(`/memos/${memoId}/detail/`, {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRFToken': csrftoken,
            'Accept': 'application/json',
          },
          credentials: 'same-origin'
        })
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          // Update modal content
          if (detailsContent) {
            detailsContent.innerHTML = `
              <div class="prose prose-lg max-w-none">
                <h4 class="text-2xl font-semibold text-gray-900 mb-4">Description</h4>
                <div id="memoDescription" class="text-lg text-gray-700 whitespace-pre-wrap leading-relaxed">${data.description}</div>
              </div>

              <!-- File Attachment -->
              <div id="memoFile" class="hidden">
                <h4 class="text-2xl font-semibold text-gray-900 mb-4">Attachment</h4>
                <a href="#" id="memoFileLink" class="inline-flex items-center px-6 py-3 border-2 border-gray-300 shadow-sm text-lg font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                  <svg class="w-6 h-6 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  Download Attachment
                </a>
              </div>

              <!-- Acknowledgment Section -->
              <div id="acknowledgmentSection" class="border-t-2 border-gray-200 pt-8 mt-8" {% if request.user.is_superuser %}style="display: none;"{% endif %}>
                <div id="notAcknowledged" class="hidden">
                  <form id="acknowledgmentForm" class="space-y-6">
                    <input type="hidden" id="memoId" name="memoId" value="${memoId}">
                    <div class="flex items-start space-x-4">
                      <div class="flex items-center h-6 mt-1">
                        <input id="acknowledgmentCheckbox" type="checkbox" class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer" required>
                      </div>
                      <div class="flex-1">
                        <label for="acknowledgmentCheckbox" class="text-xl text-gray-900 font-medium">
                          Acknowledgment
                        </label>
                        <p class="text-lg text-gray-600 mt-2 leading-relaxed">
                          I confirm that I have read and understood the contents of this memo.
                        </p>
                      </div>
                    </div>
                    <button type="submit" class="w-full inline-flex justify-center items-center px-6 py-4 border-2 border-transparent text-xl font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 transition-colors duration-150">
                      <svg class="w-7 h-7 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                      Submit Acknowledgment
                    </button>
                  </form>
                </div>
                <div id="alreadyAcknowledged" class="hidden">
                  <div class="bg-green-50 rounded-lg p-6">
                    <div class="flex items-start">
                      <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </div>
                      <div class="ml-4">
                        <h3 class="text-xl font-medium text-green-800">Memo Acknowledged</h3>
                        <div class="mt-2 text-lg text-green-700" id="acknowledgedDate"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            `;
          }

          // Update title
          if (titleElement) {
            titleElement.textContent = data.title;
          }

          // Handle file attachment
          const fileSection = document.getElementById('memoFile');
          const fileLink = document.getElementById('memoFileLink');
          if (fileSection && fileLink) {
            if (data.file_url) {
              fileSection.classList.remove('hidden');
              fileLink.href = data.file_url;
            } else {
              fileSection.classList.add('hidden');
            }
          }

          // Handle acknowledgment section
          const acknowledgmentSection = document.getElementById('acknowledgmentSection');
          if (acknowledgmentSection && !document.body.classList.contains('is-superuser')) {
            fetch(`/memos/${memoId}/check-acknowledgment/`, {
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRFToken': csrftoken,
                'Accept': 'application/json',
              },
              credentials: 'same-origin'
            })
            .then(response => {
              if (!response.ok) throw new Error('Failed to check acknowledgment status');
              return response.json();
            })
            .then(ackData => {
              const notAcknowledgedSection = document.getElementById('notAcknowledged');
              const alreadyAcknowledgedSection = document.getElementById('alreadyAcknowledged');
              const acknowledgedDateElement = document.getElementById('acknowledgedDate');
              
              if (notAcknowledgedSection && alreadyAcknowledgedSection && acknowledgedDateElement) {
                if (ackData.acknowledged) {
                  notAcknowledgedSection.classList.add('hidden');
                  alreadyAcknowledgedSection.classList.remove('hidden');
                  acknowledgedDateElement.textContent = `Acknowledged on ${ackData.acknowledged_date}`;
                } else {
                  notAcknowledgedSection.classList.remove('hidden');
                  alreadyAcknowledgedSection.classList.add('hidden');
                }
              }
            })
            .catch(error => {
              console.error('Error checking acknowledgment status:', error);
              acknowledgmentSection.innerHTML = `
                <div class="bg-yellow-50 rounded-lg p-4">
                  <div class="flex">
                    <div class="flex-shrink-0">
                      <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.667-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                      </svg>
                    </div>
                    <div class="ml-3">
                      <h3 class="text-sm font-medium text-yellow-800">Unable to check acknowledgment status</h3>
                      <p class="mt-2 text-sm text-yellow-700">Please try refreshing the page or contact support if the issue persists.</p>
                    </div>
                  </div>
                </div>
              `;
            });
          }
        })
        .catch(error => {
          console.error('Error fetching memo details:', error);
          if (detailsContent) {
            detailsContent.innerHTML = `
              <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-xl font-medium text-gray-900">Error Loading Memo</h3>
                <p class="mt-1 text-gray-500">There was a problem loading the memo details. Please try again later.</p>
                <p class="mt-1 text-sm text-gray-400">${error.message}</p>
                <button onclick="closeModal('detailsModal')" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                  Close
                </button>
              </div>
            `;
          }
          // Update title to show error
          const titleElement = document.getElementById('memoTitle');
          if (titleElement) {
            titleElement.textContent = 'Error Loading Memo';
          }
        });
      });
    });

    // Handle acknowledgment form submission
    document.addEventListener('submit', function(e) {
      if (e.target && e.target.id === 'acknowledgmentForm') {
        e.preventDefault();
        const memoId = document.getElementById('memoId').value;
        const checkbox = document.getElementById('acknowledgmentCheckbox');
        
        if (!checkbox.checked) {
          alert('Please check the acknowledgment checkbox');
          return;
        }

        // Show loading state
        const submitButton = e.target.querySelector('button[type="submit"]');
        const originalButtonContent = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = `
          <svg class="animate-spin h-7 w-7 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Submitting...
        `;

        fetch(`/memos/mark-read/${memoId}/`, {
          method: 'POST',
          headers: {
            'X-CSRFToken': getCookie('csrftoken'),
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        })
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            // Update UI to show acknowledgment
            document.getElementById('notAcknowledged').classList.add('hidden');
            document.getElementById('alreadyAcknowledged').classList.remove('hidden');
            document.getElementById('acknowledgedDate').textContent = `Acknowledged on ${data.acknowledged_date}`;
          } else {
            throw new Error('Server returned unsuccessful response');
          }
        })
        .catch(error => {
          console.error('Error submitting acknowledgment:', error);
          // Restore button state
          submitButton.disabled = false;
          submitButton.innerHTML = originalButtonContent;
          // Show error message
          alert('Error submitting acknowledgment. Please try again.');
        });
      }
    });

    // Utility function to get CSRF token
    function getCookie(name) {
      let cookieValue = null;
      if (document.cookie && document.cookie !== '') {
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
          const cookie = cookies[i].trim();
          if (cookie.substring(0, name.length + 1) === (name + '=')) {
            cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
            break;
          }
        }
      }
      return cookieValue;
    }

    // Close modal function
    window.closeModal = function(modalId) {
      document.getElementById(modalId).classList.add('hidden');
    };

    // Close modal when clicking outside
    document.getElementById('detailsModal').addEventListener('click', function(e) {
      if (e.target === this) {
        this.classList.add('hidden');
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        document.getElementById('detailsModal').classList.add('hidden');
      }
    });

    // Calendar View Functionality
    const listViewBtn = document.getElementById('listViewBtn');
    const calendarViewBtn = document.getElementById('calendarViewBtn');
    const listViewContainer = document.getElementById('listViewContainer');
    const calendarViewContainer = document.getElementById('calendarViewContainer');
    const calendarViewBtns = document.querySelectorAll('.calendar-view-btn');
    const calendarViews = document.querySelectorAll('.calendar-view');
    const prevDateBtn = document.getElementById('prevDate');
    const nextDateBtn = document.getElementById('nextDate');
    const todayBtn = document.getElementById('today');
    const currentDateEl = document.getElementById('currentDate');

    let currentDate = new Date();
    let currentView = 'month';

    // View Toggle Handlers
    listViewBtn.addEventListener('click', () => {
      listViewBtn.classList.add('bg-blue-600', 'text-white');
      listViewBtn.classList.remove('text-gray-700', 'hover:bg-gray-100');
      calendarViewBtn.classList.remove('bg-blue-600', 'text-white');
      calendarViewBtn.classList.add('text-gray-700', 'hover:bg-gray-100');
      listViewContainer.classList.remove('hidden');
      calendarViewContainer.classList.add('hidden');
    });

    calendarViewBtn.addEventListener('click', () => {
      calendarViewBtn.classList.add('bg-blue-600', 'text-white');
      calendarViewBtn.classList.remove('text-gray-700', 'hover:bg-gray-100');
      listViewBtn.classList.remove('bg-blue-600', 'text-white');
      listViewBtn.classList.add('text-gray-700', 'hover:bg-gray-100');
      calendarViewContainer.classList.remove('hidden');
      listViewContainer.classList.add('hidden');
      renderCalendar();
    });

    // Calendar View Type Handlers
    function switchView(view) {
      currentView = view;
      
      // Hide all views first
      calendarViews.forEach(v => {
        v.classList.add('hidden');
        v.classList.remove('active');
      });
      
      // Show selected view
      const selectedView = document.getElementById(`${view}View`);
      if (selectedView) {
        selectedView.classList.remove('hidden');
        selectedView.classList.add('active');
      }
      
      // Update button styles
      calendarViewBtns.forEach(btn => {
        if (btn.dataset.view === view) {
          btn.classList.add('bg-blue-600', 'text-white');
          btn.classList.remove('text-gray-700', 'hover:bg-gray-100');
        } else {
          btn.classList.remove('bg-blue-600', 'text-white');
          btn.classList.add('text-gray-700', 'hover:bg-gray-100');
        }
      });
      
      // Re-render calendar with new view
      renderCalendar();
    }

    calendarViewBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const view = btn.dataset.view;
        switchView(view);
      });
    });

    // Navigation Handlers
    prevDateBtn.addEventListener('click', () => {
      switch(currentView) {
        case 'month':
          currentDate.setMonth(currentDate.getMonth() - 1);
          break;
        case 'week':
          currentDate.setDate(currentDate.getDate() - 7);
          break;
        case 'day':
          currentDate.setDate(currentDate.getDate() - 1);
          break;
      }
      renderCalendar();
    });

    nextDateBtn.addEventListener('click', () => {
      switch(currentView) {
        case 'month':
          currentDate.setMonth(currentDate.getMonth() + 1);
          break;
        case 'week':
          currentDate.setDate(currentDate.getDate() + 7);
          break;
        case 'day':
          currentDate.setDate(currentDate.getDate() + 1);
          break;
      }
      renderCalendar();
    });

    todayBtn.addEventListener('click', () => {
      currentDate = new Date();
      renderCalendar();
    });

    function renderCalendar() {
      updateCurrentDateDisplay();
      
      switch(currentView) {
        case 'month':
          renderMonthView();
          break;
        case 'week':
          renderWeekView();
          break;
        case 'day':
          renderDayView();
          break;
      }
    }

    function updateCurrentDateDisplay() {
      const options = { year: 'numeric', month: 'long' };
      if (currentView === 'day') {
        options.day = 'numeric';
      }
      currentDateEl.textContent = currentDate.toLocaleDateString('en-US', options);
    }

    function renderMonthView() {
      const monthGrid = document.getElementById('monthGrid');
      monthGrid.innerHTML = '';

      const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
      const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
      const startingDay = firstDay.getDay();
      const totalDays = lastDay.getDate();

      // Previous month days
      for (let i = 0; i < startingDay; i++) {
        const prevMonthLastDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 0).getDate();
        const day = prevMonthLastDay - startingDay + i + 1;
        monthGrid.appendChild(createDayCell(day, true));
      }

      // Current month days
      for (let i = 1; i <= totalDays; i++) {
        monthGrid.appendChild(createDayCell(i, false));
      }

      // Next month days
      const remainingCells = 42 - (startingDay + totalDays); // 42 = 6 rows × 7 days
      for (let i = 1; i <= remainingCells; i++) {
        monthGrid.appendChild(createDayCell(i, true));
      }
    }

    function createDayCell(day, isOtherMonth) {
      const cell = document.createElement('div');
      cell.className = `month-day-cell ${isOtherMonth ? 'other-month' : ''}`;
      
      const dateNum = document.createElement('span');
      dateNum.className = 'date-number';
      dateNum.textContent = day;
      
      const eventContainer = document.createElement('div');
      eventContainer.className = 'event-container';
      
      cell.appendChild(dateNum);
      cell.appendChild(eventContainer);

      // Only fetch and display memos for current month days
      if (!isOtherMonth) {
        const selectedDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), day);
        fetchAndDisplayMemos(selectedDate, eventContainer);
      }
      
      return cell;
    }

    function fetchAndDisplayMemos(date, container, forTimeGrid = false) {
      // Format date as YYYY-MM-DD for the API
      const formattedDate = date.toISOString().split('T')[0];
      
      // Get CSRF token
      const csrftoken = getCookie('csrftoken');
      
      // Use the same endpoint as the list view with date filter
      const searchParams = new URLSearchParams({
        date: formattedDate
      });
      
      console.log(`Fetching memos for date: ${formattedDate}`);  // Debug log
      
      fetch(`/memos/?${searchParams.toString()}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRFToken': csrftoken,
          'Accept': 'application/json',
        },
        credentials: 'same-origin'
      })
      .then(response => {
        console.log('Response status:', response.status);  // Debug log
        if (!response.ok) {
          return response.text().then(text => {
            console.error('Error response body:', text);  // Debug log
            throw new Error(`HTTP error! status: ${response.status}, body: ${text}`);
          });
        }
        return response.json().catch(error => {
          console.error('JSON parsing error:', error);  // Debug log
          throw new Error('Failed to parse JSON response');
        });
      })
      .then(data => {
        console.log('Received data:', data);  // Debug log
        
        if (!data || !Array.isArray(data.memos)) {
          console.error('Invalid response format:', data);  // Debug log
          throw new Error('Invalid response format: memos array not found');
        }
        
        // Clear existing events
        container.innerHTML = '';
        
        // Add new events
        data.memos.forEach(memo => {
          try {
            const memoElement = createEventElement(memo, forTimeGrid);
            container.appendChild(memoElement);
          } catch (error) {
            console.error('Error creating memo element:', error, memo);  // Debug log
          }
        });
        
        // Add has-events class if there are events
        if (!forTimeGrid && data.memos.length > 0) {
          container.parentElement.classList.add('has-events');
        }
        
        console.log(`Successfully displayed ${data.memos.length} memos`);  // Debug log
      })
      .catch(error => {
        console.error('Error in fetchAndDisplayMemos:', error);  // Debug log
        
        // Add error indicator to container
        container.innerHTML = '';  // Clear any partial content
        const errorDiv = document.createElement('div');
        errorDiv.className = 'text-sm text-red-600 mt-2 p-2 bg-red-50 rounded-md';
        errorDiv.innerHTML = `
          <div class="font-medium">Failed to load memos</div>
          <div class="text-xs mt-1">${error.message}</div>
        `;
        container.appendChild(errorDiv);
      });
    }

    function showMemoDetailsModal(memoId) {
      // Reuse the existing details modal
      const detailsModal = document.getElementById('detailsModal');
      const detailsContent = document.getElementById('detailsContent');
      
      if (!detailsModal || !detailsContent) return;

      // Show modal
      detailsModal.classList.remove('hidden');
      
      // Set loading state
      const titleElement = document.getElementById('memoTitle');
      if (titleElement) {
        titleElement.textContent = 'Loading...';
      }

      // Get CSRF token
      const csrftoken = getCookie('csrftoken');
      
      console.log('Fetching memo details for ID:', memoId);  // Debug log
      
      // Fetch memo details using the correct URL pattern
      fetch(`/memos/${memoId}/detail/`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRFToken': csrftoken,
          'Accept': 'application/json',
        },
        credentials: 'same-origin'
      })
      .then(response => {
        console.log('Details response status:', response.status);  // Debug log
        if (!response.ok) {
          return response.text().then(text => {
            console.error('Error response body:', text);  // Debug log
            throw new Error(`HTTP error! status: ${response.status}, body: ${text}`);
          });
        }
        return response.json();
      })
      .then(data => {
        console.log('Received memo details:', data);  // Debug log
        
        // Update modal content
        const elements = {
          title: document.getElementById('memoTitle'),
          date: document.getElementById('memoDate'),
          location: document.getElementById('memoLocation'),
          locationContainer: document.getElementById('locationContainer'),
          description: document.getElementById('memoDescription'),
          fileSection: document.getElementById('memoFile'),
          fileLink: document.getElementById('memoFileLink'),
        };

        // Update title if element exists
        if (elements.title) {
          elements.title.textContent = data.title || 'Untitled Memo';
        }

        // Update date if element exists
        if (elements.date) {
          const date = new Date(data.when);
          const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
          };
          elements.date.textContent = date.toLocaleDateString('en-US', options);
        }

        // Update location if elements exist
        if (elements.location && elements.locationContainer) {
          if (data.where) {
            elements.location.textContent = `Location: ${data.where}`;
            elements.locationContainer.classList.remove('hidden');
          } else {
            elements.locationContainer.classList.add('hidden');
          }
        }

        // Update description if element exists
        if (elements.description) {
          elements.description.textContent = data.description || 'No description available';
        }

        // Update file attachment if elements exist
        if (elements.fileSection && elements.fileLink) {
          if (data.file_url) {
            elements.fileSection.classList.remove('hidden');
            elements.fileLink.href = data.file_url;
          } else {
            elements.fileSection.classList.add('hidden');
          }
        }

        // Handle acknowledgment section
        const acknowledgmentSection = document.getElementById('acknowledgmentSection');
        if (acknowledgmentSection) {
          const memoIdInput = document.getElementById('memoId');
          if (memoIdInput) {
            memoIdInput.value = memoId;
          }

          fetch(`/memos/${memoId}/check-acknowledgment/`, {
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRFToken': csrftoken,
              'Accept': 'application/json',
            },
            credentials: 'same-origin'
          })
          .then(response => {
            if (!response.ok) throw new Error('Failed to check acknowledgment status');
            return response.json();
          })
          .then(ackData => {
            const notAcknowledgedSection = document.getElementById('notAcknowledged');
            const alreadyAcknowledgedSection = document.getElementById('alreadyAcknowledged');
            const acknowledgedDateElement = document.getElementById('acknowledgedDate');
            
            if (notAcknowledgedSection && alreadyAcknowledgedSection && acknowledgedDateElement) {
              if (ackData.acknowledged) {
                notAcknowledgedSection.classList.add('hidden');
                alreadyAcknowledgedSection.classList.remove('hidden');
                acknowledgedDateElement.textContent = `Acknowledged on ${ackData.acknowledged_date}`;
              } else {
                notAcknowledgedSection.classList.remove('hidden');
                alreadyAcknowledgedSection.classList.add('hidden');
              }
            }
          })
          .catch(error => {
            console.error('Error checking acknowledgment status:', error);
            acknowledgmentSection.innerHTML = `
              <div class="bg-yellow-50 rounded-lg p-4">
                <div class="flex">
                  <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.667-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                  </div>
                  <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Unable to check acknowledgment status</h3>
                    <p class="mt-2 text-sm text-yellow-700">Please try refreshing the page or contact support if the issue persists.</p>
                  </div>
                </div>
              </div>
            `;
          });
        }
      })
      .catch(error => {
        console.error('Error fetching memo details:', error);
        if (detailsContent) {
          detailsContent.innerHTML = `
            <div class="text-center py-8">
              <svg class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <h3 class="mt-2 text-xl font-medium text-gray-900">Error Loading Memo</h3>
              <p class="mt-1 text-gray-500">There was a problem loading the memo details. Please try again later.</p>
              <p class="mt-1 text-sm text-gray-400">${error.message}</p>
              <button onclick="closeModal('detailsModal')" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Close
              </button>
            </div>
          `;
        }
        // Update title to show error
        const titleElement = document.getElementById('memoTitle');
        if (titleElement) {
          titleElement.textContent = 'Error Loading Memo';
        }
      });
    }

    // Initialize calendar if it's the active view
    if (!calendarViewContainer.classList.contains('hidden')) {
      renderCalendar();
    }

    let dragStartY = 0;
    let dragStartTime = null;
    let currentDragEvent = null;
    let resizing = false;
    let dragging = false;

    function createTimeGrid(container, slots = 24) {
      container.innerHTML = '';
      const hourHeight = 60; // pixels per hour

      // Create background grid
      for (let i = 0; i < slots; i++) {
        const hour = document.createElement('div');
        hour.className = 'border-t border-gray-200';
        hour.style.height = `${hourHeight}px`;
        
        const label = document.createElement('div');
        label.className = 'absolute -top-3 left-2 text-sm text-gray-500';
        label.textContent = `${i.toString().padStart(2, '0')}:00`;
        
        hour.appendChild(label);
        container.appendChild(hour);
      }

      return hourHeight;
    }

    function renderWeekView() {
      const weekView = document.getElementById('weekView');
      const weekGrid = document.getElementById('weekGrid');
      const weekTimeColumn = document.getElementById('weekTimeColumn');
      
      // Create time column
      weekTimeColumn.innerHTML = '';
      for (let hour = 0; hour < 24; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        const timeLabel = document.createElement('div');
        timeLabel.className = 'time-label';
        timeLabel.textContent = hour.toString().padStart(2, '0') + ':00';
        timeSlot.appendChild(timeLabel);
        weekTimeColumn.appendChild(timeSlot);
      }

      // Clear and create day columns
      weekGrid.innerHTML = '';
      const startOfWeek = new Date(currentDate);
      startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());

      // Create day headers and columns
      const headerContainer = weekView.querySelector('.grid-cols-7');
      headerContainer.innerHTML = '';
      
      for (let i = 0; i < 7; i++) {
        const dayDate = new Date(startOfWeek);
        dayDate.setDate(startOfWeek.getDate() + i);
        
        // Create header
        const header = document.createElement('div');
        header.className = `day-header ${dayDate.toDateString() === new Date().toDateString() ? 'bg-blue-50' : ''} p-2 text-center border-b border-gray-200`;
        header.textContent = dayDate.toLocaleDateString('en-US', { 
          weekday: 'short', 
          month: 'numeric', 
          day: 'numeric' 
        });
        headerContainer.appendChild(header);

        // Create day column
        const dayColumn = document.createElement('div');
        dayColumn.className = 'day-column relative';
        dayColumn.style.height = '1440px'; // 24 hours * 60px
        
        // Create time slots for visual reference
        for (let hour = 0; hour < 24; hour++) {
          const timeSlot = document.createElement('div');
          timeSlot.className = 'time-slot';
          dayColumn.appendChild(timeSlot);
        }
        
        // Fetch and display events for this day
        fetchAndDisplayMemos(dayDate, dayColumn, true);
        
        weekGrid.appendChild(dayColumn);
      }

      updateCurrentTimeIndicator();
    }

    function renderDayView() {
      const dayView = document.getElementById('dayView');
      const dayGrid = document.getElementById('dayGrid');
      const dayTimeColumn = document.getElementById('dayTimeColumn');
      
      // Create time column
      dayTimeColumn.innerHTML = '';
      for (let hour = 0; hour < 24; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        const timeLabel = document.createElement('div');
        timeLabel.className = 'time-label';
        timeLabel.textContent = hour.toString().padStart(2, '0') + ':00';
        timeSlot.appendChild(timeLabel);
        dayTimeColumn.appendChild(timeSlot);
      }

      // Clear and setup day column
      dayGrid.innerHTML = '';
      dayGrid.style.height = '1440px'; // 24 hours * 60px

      // Create time slots for visual reference
      for (let hour = 0; hour < 24; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        dayGrid.appendChild(timeSlot);
      }

      // Update day header
      const dayHeader = document.getElementById('dayHeader');
      dayHeader.className = 'p-4 text-center text-lg font-semibold border-b border-gray-200';
      dayHeader.textContent = currentDate.toLocaleDateString('en-US', { 
        weekday: 'long', 
        month: 'long', 
        day: 'numeric' 
      });

      // Fetch and display events for this day
      fetchAndDisplayMemos(currentDate, dayGrid, true);

      updateCurrentTimeIndicator();
    }

    function createEventElement(memo, forTimeGrid = false) {
      const event = document.createElement('div');
      const startTime = new Date(memo.when);
      
      if (forTimeGrid) {
        // Calculate end time (default 1 hour if not specified)
        const endTime = memo.end_time ? new Date(memo.end_time) : new Date(startTime.getTime() + 60 * 60 * 1000);
        
        const hourHeight = 60; // pixels per hour
        const top = (startTime.getHours() + startTime.getMinutes() / 60) * hourHeight;
        const height = ((endTime.getHours() + endTime.getMinutes() / 60) - 
                       (startTime.getHours() + startTime.getMinutes() / 60)) * hourHeight;
        
        event.style.top = `${top}px`;
        event.style.height = `${height}px`;
        
        // Add resize handle
        const resizeHandle = document.createElement('div');
        resizeHandle.className = 'absolute bottom-0 left-0 right-0 h-2 cursor-ns-resize';
        event.appendChild(resizeHandle);
        
        resizeHandle.addEventListener('mousedown', (e) => {
          e.stopPropagation();
          resizing = true;
          currentDragEvent = event;
          dragStartY = e.clientY;
        });
      }

      const eventContent = document.createElement('div');
      eventContent.className = 'p-2';
      
      const titleDiv = document.createElement('div');
      titleDiv.className = 'font-medium';
      titleDiv.textContent = memo.title;
      eventContent.appendChild(titleDiv);
      
      if (forTimeGrid) {
        const timeDiv = document.createElement('div');
        timeDiv.className = 'text-sm text-gray-600';
        const eventTime = new Date(memo.when);
        timeDiv.textContent = eventTime.toLocaleTimeString('en-US', { 
          hour: 'numeric', 
          minute: '2-digit' 
        });
        eventContent.appendChild(timeDiv);
      }
      
      event.appendChild(eventContent);

      // Add drag handlers
      event.addEventListener('dragstart', (e) => {
        dragging = true;
        currentDragEvent = event;
        dragStartY = e.clientY;
        dragStartTime = new Date(memo.when);
        e.dataTransfer.setData('text/plain', memo.id);
        event.classList.add('dragging');
      });

      event.addEventListener('dragend', () => {
        event.classList.remove('dragging');
      });

      // Add click handler for modal
      event.addEventListener('click', (e) => {
        if (!dragging && !resizing) {
          e.stopPropagation();
          showMemoDetailsModal(memo.id);
        }
      });

      return event;
    }

    function handleDragOver(e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
    }

    function handleDrop(e) {
      e.preventDefault();
      const memoId = e.dataTransfer.getData('text/plain');
      const targetDate = e.currentTarget.getAttribute('data-date');
      
      if (!memoId || !targetDate) return;

      // Calculate new time based on drop position
      const rect = e.currentTarget.getBoundingClientRect();
      const hourHeight = 60;
      const hours = (e.clientY - rect.top) / hourHeight;
      
      const newDate = new Date(targetDate);
      newDate.setHours(Math.floor(hours));
      newDate.setMinutes((hours % 1) * 60);

      // Update memo with new date/time
      updateMemoDateTime(memoId, newDate);
    }

    function updateMemoDateTime(memoId, newDateTime) {
      const csrftoken = getCookie('csrftoken');
      
      fetch(`/memos/${memoId}/update-time/`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRFToken': csrftoken,
        },
        body: JSON.stringify({
          when: newDateTime.toISOString()
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          renderCalendar(); // Refresh the calendar view
        }
      })
      .catch(error => console.error('Error updating memo time:', error));
    }

    function updateCurrentTimeIndicator() {
      const now = new Date();
      const currentView = document.querySelector('.calendar-view:not(.hidden)');
      const indicator = currentView.querySelector('[id$="CurrentTimeIndicator"]');
      
      if (!indicator) return;

      const hourHeight = 60;
      const top = (now.getHours() + now.getMinutes() / 60) * hourHeight;
      
      indicator.style.top = `${top}px`;
      indicator.classList.remove('hidden');
    }

    // Set up interval to update current time indicator
    setInterval(updateCurrentTimeIndicator, 60000); // Update every minute

    // Quick create functionality
    function showQuickCreateModal(date, timeSlot = null) {
      const modal = document.getElementById('quickCreateModal');
      const startInput = document.getElementById('quickStart');
      const endInput = document.getElementById('quickEnd');
      
      const startDate = new Date(date);
      if (timeSlot) {
        startDate.setHours(timeSlot, 0, 0, 0);
      }
      
      const endDate = new Date(startDate);
      endDate.setHours(startDate.getHours() + 1);
      
      startInput.value = startDate.toISOString().slice(0, 16);
      endInput.value = endDate.toISOString().slice(0, 16);
      
      modal.classList.remove('hidden');
    }

    function closeQuickCreateModal() {
      document.getElementById('quickCreateModal').classList.add('hidden');
    }

    // Handle quick create form submission
    document.getElementById('quickCreateForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = {
        title: document.getElementById('quickTitle').value,
        when: document.getElementById('quickStart').value,
        where: document.getElementById('quickLocation').value,
        description: document.getElementById('quickDescription').value
      };

      const csrftoken = getCookie('csrftoken');
      
      fetch('/memos/create/', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRFToken': csrftoken,
        },
        body: JSON.stringify(formData)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          closeQuickCreateModal();
          renderCalendar();
        }
      })
      .catch(error => console.error('Error creating memo:', error));
    });

    // Add double-click handlers for quick create
    document.addEventListener('dblclick', function(e) {
      const timeGrid = e.target.closest('#weekGrid, #dayGrid');
      if (timeGrid) {
        const rect = timeGrid.getBoundingClientRect();
        const hourHeight = 60;
        const hours = Math.floor((e.clientY - rect.top) / hourHeight);
        const date = timeGrid.getAttribute('data-date');
        
        if (date) {
          showQuickCreateModal(date, hours);
        }
      }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
      if (!calendarViewContainer.classList.contains('hidden')) {
        renderCalendar();
      }
    });

    // Handle document-wide mouse events for dragging
    document.addEventListener('mousemove', function(e) {
      if (resizing && currentDragEvent) {
        const hourHeight = 60;
        const deltaY = e.clientY - dragStartY;
        const deltaHours = Math.round(deltaY / hourHeight * 2) / 2; // Snap to 30-minute intervals
        
        const newHeight = Math.max(hourHeight / 2, 
          parseInt(currentDragEvent.style.height) + deltaY);
        currentDragEvent.style.height = `${newHeight}px`;
        
        dragStartY = e.clientY;
      }
    });

    document.addEventListener('mouseup', function() {
      if (resizing && currentDragEvent) {
        const memoId = currentDragEvent.getAttribute('data-memo-id');
        const height = parseInt(currentDragEvent.style.height);
        const startTop = parseInt(currentDragEvent.style.top);
        const hourHeight = 60;
        
        const startTime = new Date(dragStartTime);
        const endTime = new Date(startTime.getTime() + (height / hourHeight) * 60 * 60 * 1000);
        
        updateMemoDateTime(memoId, startTime, endTime);
      }
      
      resizing = false;
      dragging = false;
      currentDragEvent = null;
    });

    // Add this at the end of your script section
    function initializeCalendar() {
      // Initial calendar render
      renderCalendar();
      
      // Set up interval to update current time indicator
      setInterval(updateCurrentTimeIndicator, 60000);
      
      // Add AJAX response type handler for memo list
      $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url.includes('/memo_combined/')) {
          try {
            const contentType = xhr.getResponseHeader('content-type');
            if (contentType && contentType.includes('application/json')) {
              const data = JSON.parse(xhr.responseText);
              if (data.page_obj) {
                renderCalendar(); // Re-render calendar when memo list updates
              }
            }
          } catch (e) {
            console.error('Error handling AJAX response:', e);
          }
        }
      });
    }

    // Initialize calendar when document is ready
    document.addEventListener('DOMContentLoaded', function() {
      initializeCalendar();
    });

    // Table Sorting Functionality
    const table = document.querySelector('.memo-table');
    const sortButtons = document.querySelectorAll('.sort-btn');
    
    sortButtons.forEach(button => {
      button.addEventListener('click', function() {
        const column = this.getAttribute('data-sort');
        const type = this.getAttribute('data-type') || 'text';
        const currentDirection = this.querySelector('.sort-direction').textContent;
        const isAscending = currentDirection.includes('A to Z') || currentDirection.includes('Oldest first');
        
        // Update sort direction text and icon
        const directionText = this.querySelector('.sort-direction');
        const sortIcon = this.querySelector('.sort-icon');
        
        if (column === 'title') {
          directionText.textContent = isAscending ? 'Z to A' : 'A to Z';
        } else if (column === 'when') {
          directionText.textContent = isAscending ? 'Newest first' : 'Oldest first';
        } else if (column === 'where') {
          directionText.textContent = isAscending ? 'Z to A' : 'A to Z';
        }
        
        sortIcon.classList.remove('hidden');
        sortIcon.style.transform = isAscending ? 'rotate(180deg)' : 'rotate(0deg)';
        
        // Get table rows and convert to array for sorting
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Sort rows
        rows.sort((a, b) => {
          let aValue = a.querySelector(`td:nth-child(${getColumnIndex(column)})`).textContent.trim();
          let bValue = b.querySelector(`td:nth-child(${getColumnIndex(column)})`).textContent.trim();
          
          if (type === 'date') {
            aValue = new Date(aValue);
            bValue = new Date(bValue);
          }
          
          if (aValue === bValue) return 0;
          
          if (type === 'date') {
            return isAscending ? aValue - bValue : bValue - aValue;
          } else {
            return isAscending ? 
              bValue.localeCompare(aValue) : 
              aValue.localeCompare(bValue);
          }
        });
        
        // Clear and re-append sorted rows
        while (tbody.firstChild) {
          tbody.removeChild(tbody.firstChild);
        }
        rows.forEach(row => tbody.appendChild(row));
      });
    });
    
    function getColumnIndex(column) {
      switch(column) {
        case 'title': return 1;
        case 'when': return 3;
        case 'where': return 4;
        default: return 1;
      }
    }
    
    // Readers Functionality
    document.querySelectorAll('.view-readers').forEach(button => {
      button.addEventListener('click', function() {
        const memoId = this.getAttribute('data-memo-id');
        const readersModal = document.getElementById('readersModal');
        const readersContent = document.getElementById('readersContent');
        
        if (!readersModal || !readersContent) {
          console.error('Modal elements not found');
          return;
        }
        
        // Show modal
        readersModal.classList.remove('hidden');
        
        // Set loading state
        readersContent.innerHTML = `
          <div class="flex justify-center items-center p-4">
            <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </div>
        `;
        
        // Fetch readers data
        fetch(`/get_memo_readers/${memoId}/`, {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          credentials: 'same-origin'
        })
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          if (!data || !Array.isArray(data.readers)) {
            throw new Error('Invalid response format: expected readers array');
          }
          
          if (data.readers.length === 0) {
            readersContent.innerHTML = `
              <div class="text-center py-6">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3 class="text-xl font-medium text-gray-900">${data.memo_title}</h3>
                <p class="mt-2 text-lg text-gray-500">No readers yet</p>
              </div>
            `;
            return;
          }
          
          // Create readers list
          const readersList = document.createElement('div');
          readersList.className = 'space-y-4';

          // Add memo title
          const titleDiv = document.createElement('div');
          titleDiv.className = 'mb-6 pb-4 border-b border-gray-200';
          titleDiv.innerHTML = `
            <h3 class="text-xl font-medium text-gray-900">${data.memo_title}</h3>
            <p class="mt-1 text-sm text-gray-500">${data.count} reader${data.count !== 1 ? 's' : ''}</p>
          `;
          readersList.appendChild(titleDiv);
          
          data.readers.forEach(username => {
            const readerItem = document.createElement('div');
            readerItem.className = 'flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors duration-150';
            
            readerItem.innerHTML = `
              <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                  <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p class="text-lg font-medium text-gray-900">${username}</p>
                </div>
              </div>
            `;
            
            readersList.appendChild(readerItem);
          });
          
          readersContent.innerHTML = '';
          readersContent.appendChild(readersList);
        })
        .catch(error => {
          console.error('Error fetching readers:', error);
          readersContent.innerHTML = `
            <div class="text-center py-6">
              <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <h3 class="mt-2 text-xl font-medium text-gray-900">Error Loading Readers</h3>
              <p class="mt-1 text-lg text-gray-500">Unable to load reader information.</p>
              <button onclick="closeModal('readersModal')" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Close
              </button>
            </div>
          `;
        });
      });
    });

    // Add card view button handler
    const cardViewBtn = document.getElementById('cardViewBtn');
    const cardViewContainer = document.getElementById('cardViewContainer');
    
    cardViewBtn.addEventListener('click', () => {
      // Update button styles
      cardViewBtn.classList.add('bg-blue-600', 'text-white');
      cardViewBtn.classList.remove('text-gray-700', 'hover:bg-gray-100');
      
      listViewBtn.classList.remove('bg-blue-600', 'text-white');
      listViewBtn.classList.add('text-gray-700', 'hover:bg-gray-100');
      
      calendarViewBtn.classList.remove('bg-blue-600', 'text-white');
      calendarViewBtn.classList.add('text-gray-700', 'hover:bg-gray-100');
      
      // Show/hide containers
      cardViewContainer.classList.remove('hidden');
      listViewContainer.classList.add('hidden');
      calendarViewContainer.classList.add('hidden');
    });

    // Update list view button handler
    listViewBtn.addEventListener('click', () => {
      listViewBtn.classList.add('bg-blue-600', 'text-white');
      listViewBtn.classList.remove('text-gray-700', 'hover:bg-gray-100');
      
      cardViewBtn.classList.remove('bg-blue-600', 'text-white');
      cardViewBtn.classList.add('text-gray-700', 'hover:bg-gray-100');
      
      calendarViewBtn.classList.remove('bg-blue-600', 'text-white');
      calendarViewBtn.classList.add('text-gray-700', 'hover:bg-gray-100');
      
      listViewContainer.classList.remove('hidden');
      cardViewContainer.classList.add('hidden');
      calendarViewContainer.classList.add('hidden');
    });

    // Update calendar view button handler
    calendarViewBtn.addEventListener('click', () => {
      calendarViewBtn.classList.add('bg-blue-600', 'text-white');
      calendarViewBtn.classList.remove('text-gray-700', 'hover:bg-gray-100');
      
      listViewBtn.classList.remove('bg-blue-600', 'text-white');
      listViewBtn.classList.add('text-gray-700', 'hover:bg-gray-100');
      
      cardViewBtn.classList.remove('bg-blue-600', 'text-white');
      cardViewBtn.classList.add('text-gray-700', 'hover:bg-gray-100');
      
      calendarViewContainer.classList.remove('hidden');
      listViewContainer.classList.add('hidden');
      cardViewContainer.classList.add('hidden');
      renderCalendar();
    });
  });
</script>

<!-- Update the table text sizes in the main list view -->
<style>
.memo-table th {
  font-size: 1.125rem !important;
  padding: 0.75rem 1.5rem !important;
}

.memo-table td {
  font-size: 1.125rem !important;
  padding: 0.75rem 1.5rem !important;
}

.memo-table button {
  font-size: 1.125rem !important;
  padding: 0.5rem 1rem !important;
}

/* Adjust modal content spacing */
.modal-content {
  padding: 1.5rem !important;
}

/* Adjust form elements spacing */
.form-input, .form-textarea, .form-select {
  padding: 0.75rem 1rem !important;
}

/* Adjust button spacing */
.btn {
  padding: 0.75rem 1.5rem !important;
}

/* Calendar Styles */
.calendar-view.active {
  display: block;
}

#monthGrid {
  min-height: calc(100vh - 400px);
}

@media (max-width: 768px) {
  #monthGrid {
    min-height: calc(100vh - 300px);
  }
  
  .month-day-cell {
    min-height: 120px;
    padding: 0.5rem;
  }
  
  .month-day-cell .date-number {
    font-size: 0.875rem;
    top: 0.25rem;
    right: 0.5rem;
  }
  
  .month-day-cell .event-container {
    margin-top: 1.5rem;
  }
  
  .calendar-event {
    padding: 4px 8px;
    margin: 1px 2px;
    font-size: 0.75rem;
  }
  
  /* Week/Day View Mobile Adjustments */
  #weekView, #dayView {
    height: calc(100vh - 250px);
  }
  
  .time-slot {
    height: 40px;
  }
  
  .time-label {
    font-size: 0.7rem;
    top: -8px;
  }
  
  /* Calendar Header Mobile Adjustments */
  .calendar-toolbar {
    padding: 0.75rem;
    flex-direction: column;
    gap: 0.75rem;
  }
  
  .calendar-navigation {
    width: 100%;
    justify-content: space-between;
  }
  
  .calendar-view-selector {
    width: 100%;
    justify-content: space-between;
  }
  
  .calendar-view-btn {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
  }
  
  /* Calendar Container Mobile Adjustments */
  .calendar-container {
    margin: 0 -1rem;
    border-radius: 0;
  }
}

/* Month View Day Cell */
.month-day-cell {
  background-color: white;
  padding: 1rem;
  min-height: 180px;
  position: relative;
  transition: all 0.2s ease-in-out;
}

.month-day-cell:hover {
  background-color: #f3f4f6;
}

.month-day-cell.has-events {
  background-color: #f0f9ff;
}

.month-day-cell.has-events:hover {
  background-color: #e0f2fe;
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.month-day-cell.other-month {
  background-color: #f9fafb;
  color: #9ca3af;
}

.month-day-cell.other-month:hover {
  background-color: #f3f4f6;
}

.month-day-cell .date-number {
  position: absolute;
  top: 0.5rem;
  right: 0.75rem;
  font-size: 1rem;
  font-weight: 500;
}

.month-day-cell .event-container {
  margin-top: 2rem;
  overflow-y: auto;
  max-height: calc(100% - 2.5rem);
}

.calendar-event {
  padding: 6px 10px;
  margin: 2px 4px;
  font-size: 0.875rem;
  line-height: 1.25rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  background-color: rgba(59, 130, 246, 0.1);
  color: rgb(37, 99, 235);
  border-radius: 4px;
  border-left: 3px solid rgb(37, 99, 235);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  transition: all 150ms ease-in-out;
  cursor: pointer;
}

.calendar-event:hover {
  background-color: rgba(59, 130, 246, 0.15);
  transform: translateX(2px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.calendar-event.dragging {
  opacity: 0.7;
  transform: scale(0.95);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Calendar Container */
#calendarViewContainer {
  margin-bottom: 2rem;
}

/* Calendar Views */
.calendar-view {
  display: none;
  background-color: white;
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.calendar-view.active {
  display: block;
}

/* Month View */
#monthView .grid-cols-7 > div {
  min-height: 120px;
  padding: 0.75rem;
  position: relative;
}

#monthView .bg-gray-50 {
  background-color: #f9fafb;
}

/* Week/Day View */
#weekView, #dayView {
  height: calc(100vh - 300px);
  overflow-y: auto;
}

.time-grid-container {
  position: relative;
  border-top: 1px solid #e5e7eb;
}

.time-slot {
  height: 60px;
  border-bottom: 1px solid #e5e7eb;
  position: relative;
}

.time-label {
  position: absolute;
  top: -10px;
  left: 8px;
  font-size: 0.75rem;
  color: #6b7280;
  background-color: white;
  padding: 0 4px;
}

/* Current Time Indicator */
.current-time-indicator {
  position: absolute;
  left: 0;
  right: 0;
  border-top: 2px solid #ef4444;
  z-index: 50;
}

.current-time-indicator::before {
  content: '';
  position: absolute;
  left: -6px;
  top: -4px;
  width: 8px;
  height: 8px;
  background-color: #ef4444;
  border-radius: 50%;
}

/* Event Styles */
.calendar-event {
  margin: 1px 2px;
  padding: 4px 8px;
  font-size: 0.875rem;
  border-radius: 4px;
  background-color: rgba(59, 130, 246, 0.1);
  border-left: 3px solid rgb(37, 99, 235);
  color: rgb(37, 99, 235);
  cursor: pointer;
  transition: all 150ms ease-in-out;
}

.calendar-event:hover {
  background-color: rgba(59, 130, 246, 0.15);
  transform: translateX(2px);
}

/* Time Grid Events */
.time-grid-event {
  position: absolute;
  left: 1px;
  right: 1px;
  background-color: rgba(59, 130, 246, 0.1);
  border-left: 3px solid rgb(37, 99, 235);
  border-radius: 4px;
  padding: 4px;
  font-size: 0.875rem;
  color: rgb(37, 99, 235);
  overflow: hidden;
  cursor: pointer;
  transition: all 150ms ease-in-out;
}

.time-grid-event:hover {
  background-color: rgba(59, 130, 246, 0.15);
  z-index: 10;
}

/* Calendar Header */
.calendar-header {
  background-color: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem;
}

.calendar-header button {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 150ms ease-in-out;
}

.calendar-header button:hover {
  background-color: #f3f4f6;
}

.calendar-header button.active {
  background-color: #2563eb;
  color: white;
}

/* Day Headers */
.day-header {
  padding: 0.75rem;
  text-align: center;
  border-bottom: 1px solid #e5e7eb;
  background-color: #f9fafb;
}

.day-header.today {
  background-color: #dbeafe;
  color: #2563eb;
}

/* Add these new styles for better visual hierarchy */
.calendar-container {
  background-color: white;
  border-radius: 0.75rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

.calendar-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.calendar-navigation {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.calendar-view-selector {
  display: flex;
  gap: 0.5rem;
}

/* Update the JavaScript to handle view switching */
function switchView(view) {
  // Hide all views
  document.querySelectorAll('.calendar-view').forEach(v => {
    v.classList.remove('active');
    v.style.display = 'none';
  });
  
  // Show selected view
  const selectedView = document.getElementById(`${view}View`);
  if (selectedView) {
    selectedView.classList.add('active');
    selectedView.style.display = 'block';
  }
  
  // Update buttons
  document.querySelectorAll('.calendar-view-btn').forEach(btn => {
    btn.classList.remove('bg-blue-600', 'text-white');
    btn.classList.add('text-gray-700', 'hover:bg-gray-100');
  });
  
  const activeBtn = document.querySelector(`[data-view="${view}"]`);
  if (activeBtn) {
    activeBtn.classList.add('bg-blue-600', 'text-white');
    activeBtn.classList.remove('text-gray-700', 'hover:bg-gray-100');
  }
  
  // Re-render the calendar with the new view
  currentView = view;
  renderCalendar();
}

// Update the click handlers for view buttons
document.querySelectorAll('.calendar-view-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const view = btn.dataset.view;
    switchView(view);
  });
});

/* Card View Styles */
#cardViewContainer .grid {
  margin-bottom: 2rem;
}

#cardViewContainer .bg-white {
  transition: all 0.2s ease-in-out;
}

#cardViewContainer .bg-white:hover {
  transform: translateY(-2px);
}

/* Ensure consistent button sizing in card view */
#cardViewContainer .view-details,
#cardViewContainer .view-readers {
  white-space: nowrap;
}

/* Add responsive padding for card content */
@media (min-width: 768px) {
  #cardViewContainer .p-6 {
    padding: 1.5rem;
  }
}

@media (min-width: 1024px) {
  #cardViewContainer .p-6 {
    padding: 2rem;
  }
}
</style>

<!-- Card View Container -->
<div id="cardViewContainer" class="hidden">
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    {% if page_obj %}
      {% for memo in page_obj %}
      <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-200">
        <div class="p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-gray-900 truncate">{{ memo.title }}</h3>
            <span class="flex-shrink-0 ml-2">
              {% if memo.file %}
                {% if memo.file.url|lower|slice:"-4:" in ".pdf,.doc,docx,.txt" %}
                  <a href="{{ memo.file.url }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </a>
                {% endif %}
              {% endif %}
            </span>
          </div>
          
          <div class="space-y-3">
            <div class="flex items-center text-gray-600">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span class="text-lg">{{ memo.when|date:"Y-m-d" }}</span>
            </div>
            
            {% if memo.where %}
            <div class="flex items-center text-gray-600">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span class="text-lg">{{ memo.where }}</span>
            </div>
            {% endif %}
          </div>

          <div class="mt-4 border-t border-gray-200 pt-4">
            <div class="flex justify-between items-center">
              <button data-memo-id="{{ memo.id }}" class="view-details inline-flex items-center px-4 py-2 border-2 border-blue-600 text-blue-600 hover:bg-blue-50 rounded-lg text-lg font-medium transition-colors duration-150">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Details
              </button>
              {% if request.user.is_superuser %}
              <button data-memo-id="{{ memo.id }}" class="view-readers inline-flex items-center px-4 py-2 border-2 border-green-600 text-green-600 hover:bg-green-50 rounded-lg text-lg font-medium transition-colors duration-150">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Readers
              </button>
              {% endif %}
            </div>
          </div>
        </div>
      </div>
      {% empty %}
      <div class="col-span-full">
        <div class="text-center px-6 py-16 bg-white rounded-xl shadow-sm">
          <div class="rounded-full bg-blue-100 h-20 w-20 flex items-center justify-center mx-auto">
            <svg class="h-10 w-10 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="mt-6 text-2xl font-medium text-gray-900">No Memos Found</h3>
          <p class="mt-3 text-lg text-gray-500 max-w-md mx-auto">
            There are no memos in the system at the moment.
          </p>
        </div>
      </div>
      {% endfor %}
    {% endif %}
  </div>
</div>

<!-- Memo Detail Modal -->
<div id="memoDetailModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
      <div class="bg-white px-4 sm:px-6 pt-5 sm:pt-6 pb-4 sm:pb-6">
        <div class="sm:flex sm:items-start">
          <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
            <h3 class="text-lg sm:text-2xl font-medium leading-6 text-gray-900 mb-4" id="modal-title">
              Memo Details
            </h3>
            <div class="mt-2 space-y-4">
              <div>
                <h4 class="text-base sm:text-lg font-medium text-gray-900">Title</h4>
                <p class="mt-1 text-sm sm:text-base text-gray-600" id="modalTitle"></p>
              </div>
              <div>
                <h4 class="text-base sm:text-lg font-medium text-gray-900">Description</h4>
                <p class="mt-1 text-sm sm:text-base text-gray-600" id="modalDescription"></p>
              </div>
              <div>
                <h4 class="text-base sm:text-lg font-medium text-gray-900">Date</h4>
                <p class="mt-1 text-sm sm:text-base text-gray-600" id="modalDate"></p>
              </div>
              <div>
                <h4 class="text-base sm:text-lg font-medium text-gray-900">Location</h4>
                <p class="mt-1 text-sm sm:text-base text-gray-600" id="modalLocation"></p>
              </div>
              <div>
                <h4 class="text-base sm:text-lg font-medium text-gray-900">File</h4>
                <p class="mt-1 text-sm sm:text-base text-gray-600" id="modalFile"></p>
              </div>
              <div>
                <h4 class="text-base sm:text-lg font-medium text-gray-900">Readers</h4>
                <div class="mt-1 text-sm sm:text-base text-gray-600" id="modalReaders"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 sm:flex sm:flex-row-reverse">
        <button type="button" class="w-full sm:w-auto mt-3 sm:mt-0 inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 sm:px-6 py-2 sm:py-3 bg-white text-base sm:text-lg font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeMemoDetailModal()">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

</body>
</html>