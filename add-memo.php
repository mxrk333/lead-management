<?php
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
$conn = getDbConnection();

// Check if user is admin or manager
$isAuthorized = ($user['role'] === 'admin' || $user['role'] === 'manager');

if (!$isAuthorized) {
    header("Location: memo.php");
    exit();
}

// Get all teams for dropdown
$teams_query = "SELECT id, name FROM teams ORDER BY name ASC";
$teams_result = $conn->query($teams_query);
$teams = [];
while ($team = $teams_result->fetch_assoc()) {
    $teams[$team['id']] = $team['name'];
}

// Get all users for person selection
$users_query = "SELECT id, name, username, role, team_id FROM users WHERE is_active = 1 ORDER BY name ASC";
$users_result = $conn->query($users_query);

// Debug: Check if query failed
if (!$users_result) {
    error_log("Users query failed: " . $conn->error);
    // Try without is_active filter
    $users_query_fallback = "SELECT id, name, username, role, team_id FROM users ORDER BY name ASC";
    $users_result = $conn->query($users_query_fallback);
    if (!$users_result) {
        error_log("Fallback users query also failed: " . $conn->error);
    }
}

$users = [];
if ($users_result) {
    while ($user_data = $users_result->fetch_assoc()) {
        $users[$user_data['id']] = $user_data;
    }
}

// Debug: Log user data
error_log("Users fetched: " . count($users));
error_log("Users data: " . print_r($users, true));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $when = date('Y-m-d H:i:s');
        $file_path = null;
        $visibility_type = $_POST['visibility_type'] ?? 'all';
        $priority = $_POST['priority'] ?? 'Medium';
        
        // Debug: Log the visibility data
        error_log("Memo creation - Visibility type: " . $visibility_type);
        error_log("Memo creation - POST data: " . print_r($_POST, true));
        
        if (isset($_FILES['memo_file']) && $_FILES['memo_file']['size'] > 0) {
            $target_dir = "uploads/memos/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_path = $target_dir . time() . '_' . basename($_FILES['memo_file']['name']);
            move_uploaded_file($_FILES['memo_file']['tmp_name'], $file_path);
        }
        
        $conn->begin_transaction();
        
        $team_id = $user['team_id'];
        $visible_to_all = ($visibility_type === 'all') ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO memos (title, file_path, description, memo_when, priority, created_by, team_id, visible_to_all, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssiii", $title, $file_path, $description, $when, $priority, $_SESSION['user_id'], $team_id, $visible_to_all);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating memo: " . $stmt->error);
        }
        
        $memo_id = $conn->insert_id;
        error_log("Memo created with ID: " . $memo_id);
        
        // Handle team visibility
        if ($visibility_type === 'teams') {
            $selected_teams = (isset($_POST['team_ids']) && is_array($_POST['team_ids'])) 
                ? array_map('intval', $_POST['team_ids']) 
                : [];
            
            error_log("Selected teams: " . print_r($selected_teams, true));
            
            if (!empty($selected_teams)) {
                $insert_stmt = $conn->prepare("INSERT INTO memo_team_visibility (memo_id, team_id) VALUES (?, ?)");
                foreach ($selected_teams as $valid_team_id) {
                    $insert_stmt->bind_param("ii", $memo_id, $valid_team_id);
                    $insert_stmt->execute();
                }
                error_log("Team visibility records inserted");
            }
        }
        
        // Handle specific person visibility
        if ($visibility_type === 'persons') {
            $selected_persons = (isset($_POST['person_ids']) && is_array($_POST['person_ids'])) 
                ? array_map('intval', $_POST['person_ids']) 
                : [];
            
            error_log("Selected persons: " . print_r($selected_persons, true));
            
            if (!empty($selected_persons)) {
                // Create memo_person_visibility table if it doesn't exist
                $create_table_sql = "
                CREATE TABLE IF NOT EXISTS `memo_person_visibility` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `memo_id` int NOT NULL,
                  `user_id` int NOT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `memo_user_unique` (`memo_id`,`user_id`),
                  KEY `memo_id` (`memo_id`),
                  KEY `user_id` (`user_id`),
                  CONSTRAINT `memo_person_visibility_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `memo_person_visibility_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ";
                $conn->query($create_table_sql);
                
                $insert_stmt = $conn->prepare("INSERT INTO memo_person_visibility (memo_id, user_id) VALUES (?, ?)");
                foreach ($selected_persons as $valid_user_id) {
                    $insert_stmt->bind_param("ii", $memo_id, $valid_user_id);
                    $insert_stmt->execute();
                }
                error_log("Person visibility records inserted");
            }
        }
        
        $conn->commit();
        error_log("Memo creation completed successfully");
        header("Location: memo.php?success=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Memo creation error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Memo - Inner SPARC Realty Corporation</title>
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
        
        .add-memo-page {
            flex: 1;
            padding: 1.5rem;
            width: 100%;
            margin: 0;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header h2 i {
            color: var(--primary);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--gray-100);
            color: var(--gray-700);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-back:hover {
            background: var(--gray-200);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .form-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .radio-option input[type="radio"] {
            width: 1rem;
            height: 1rem;
        }

        .priority-options {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .priority-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .priority-option input[type="radio"] {
            width: 1rem;
            height: 1rem;
        }

        .priority-label {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .priority-label.low {
            background: var(--info-light);
            color: var(--info);
        }

        .priority-label.medium {
            background: var(--warning-light);
            color: var(--warning);
        }

        .priority-label.high {
            background: var(--danger-light);
            color: var(--danger);
        }

        .priority-label.urgent {
            background: var(--danger-light);
            color: var(--danger);
            font-weight: 600;
        }

        .selector-container {
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-top: 1rem;
            display: none;
        }

        .selector-header {
            background-color: var(--gray-50);
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selector-actions {
            display: flex;
            gap: 1rem;
        }

        .selector-action {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .selector-action:hover {
            text-decoration: underline;
        }

        .selector-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 0.75rem;
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
        }

        .selector-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .selector-checkbox:hover {
            background-color: var(--gray-50);
        }

        .selector-checkbox input {
            width: 1rem;
            height: 1rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .user-name {
            font-weight: 500;
            color: var(--gray-900);
        }

        .user-details {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .search-box {
            padding: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .search-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--gray-200);
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-danger {
            background-color: var(--danger-light);
            border-color: #fecaca;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .add-memo-page {
                padding: 1rem;
            }

            .form-container {
                padding: 1.5rem;
            }

            .selector-grid {
                grid-template-columns: 1fr;
            }

            .radio-group {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="add-memo-page">
                <div class="page-header">
                    <h2><i class="fas fa-plus-circle"></i> Add New Memo</h2>
                    <a href="memo.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Back to Memos
                    </a>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="Enter memo title">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" required placeholder="Enter memo description"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <div class="priority-options">
                                <div class="priority-option">
                                    <input type="radio" name="priority" id="priority_low" value="Low">
                                    <label for="priority_low" class="priority-label low">Low</label>
                                </div>
                                <div class="priority-option">
                                    <input type="radio" name="priority" id="priority_medium" value="Medium" checked>
                                    <label for="priority_medium" class="priority-label medium">Medium</label>
                                </div>
                                <div class="priority-option">
                                    <input type="radio" name="priority" id="priority_high" value="High">
                                    <label for="priority_high" class="priority-label high">High</label>
                                </div>
                                <div class="priority-option">
                                    <input type="radio" name="priority" id="priority_urgent" value="Urgent">
                                    <label for="priority_urgent" class="priority-label urgent">Urgent</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Attachment (Optional)</label>
                            <input type="file" name="memo_file" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Visibility</label>
                            <div class="radio-group">
                                <div class="radio-option">
                                    <input type="radio" name="visibility_type" id="visibility_all" value="all" checked>
                                    <label for="visibility_all">All Teams</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" name="visibility_type" id="visibility_teams" value="teams">
                                    <label for="visibility_teams">Specific Teams</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" name="visibility_type" id="visibility_persons" value="persons">
                                    <label for="visibility_persons">Specific Persons</label>
                                </div>
                            </div>
                            
                            <!-- Team Selection -->
                            <div id="team-selection" class="selector-container">
                                <div class="selector-header">
                                    <span>Select teams to share with:</span>
                                    <div class="selector-actions">
                                        <a href="#" id="select-all-teams" class="selector-action">Select All</a>
                                        <a href="#" id="deselect-all-teams" class="selector-action">Deselect All</a>
                                    </div>
                                </div>
                                <div class="selector-grid">
                                    <?php foreach ($teams as $team_id => $team_name): ?>
                                        <div class="selector-checkbox">
                                            <input type="checkbox" id="team_<?php echo $team_id; ?>" 
                                                   name="team_ids[]" value="<?php echo $team_id; ?>"
                                                   <?php echo $team_id == $user['team_id'] ? 'checked' : ''; ?>>
                                            <label for="team_<?php echo $team_id; ?>"><?php echo htmlspecialchars($team_name); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Person Selection -->
                            <div id="person-selection" class="selector-container">
                                <div class="selector-header">
                                    <span>Select persons to share with:</span>
                                    <div class="selector-actions">
                                        <a href="#" id="select-all-persons" class="selector-action">Select All</a>
                                        <a href="#" id="deselect-all-persons" class="selector-action">Deselect All</a>
                                    </div>
                                </div>
                                <div class="search-box">
                                    <input type="text" id="person-search" class="search-input" placeholder="Search persons...">
                                </div>
                                <div class="selector-grid">
                                    <?php foreach ($users as $user_id => $user_data): ?>
                                        <div class="selector-checkbox person-item" data-name="<?php echo strtolower($user_data['name']); ?>" data-username="<?php echo strtolower($user_data['username']); ?>">
                                            <input type="checkbox" id="person_<?php echo $user_id; ?>" 
                                                   name="person_ids[]" value="<?php echo $user_id; ?>"
                                                   <?php echo $user_id == $_SESSION['user_id'] ? 'checked' : ''; ?>>
                                            <div class="user-info">
                                                <label for="person_<?php echo $user_id; ?>" class="user-name"><?php echo htmlspecialchars($user_data['name']); ?></label>
                                                <div class="user-details">
                                                    <?php echo htmlspecialchars($user_data['username']); ?> • 
                                                    <?php echo ucfirst($user_data['role']); ?>
                                                    <?php if (isset($teams[$user_data['team_id']])): ?>
                                                        • <?php echo htmlspecialchars($teams[$user_data['team_id']]); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Create Memo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const visibilityRadios = document.querySelectorAll('input[name="visibility_type"]');
            const teamSelection = document.getElementById('team-selection');
            const personSelection = document.getElementById('person-selection');
            const selectAllTeamsBtn = document.getElementById('select-all-teams');
            const deselectAllTeamsBtn = document.getElementById('deselect-all-teams');
            const selectAllPersonsBtn = document.getElementById('select-all-persons');
            const deselectAllPersonsBtn = document.getElementById('deselect-all-persons');
            const teamCheckboxes = document.querySelectorAll('input[name="team_ids[]"]');
            const personCheckboxes = document.querySelectorAll('input[name="person_ids[]"]');
            const personSearch = document.getElementById('person-search');
            const personItems = document.querySelectorAll('.person-item');

            function toggleVisibility() {
                const selectedVisibility = document.querySelector('input[name="visibility_type"]:checked').value;
                
                // Hide all selectors
                teamSelection.style.display = 'none';
                personSelection.style.display = 'none';
                
                // Show relevant selector
                if (selectedVisibility === 'teams') {
                    teamSelection.style.display = 'block';
                } else if (selectedVisibility === 'persons') {
                    personSelection.style.display = 'block';
                }
            }

            // Handle visibility radio changes
            visibilityRadios.forEach(radio => {
                radio.addEventListener('change', toggleVisibility);
            });

            // Team selection handlers
            if (selectAllTeamsBtn) {
                selectAllTeamsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    teamCheckboxes.forEach(checkbox => checkbox.checked = true);
                });
            }

            if (deselectAllTeamsBtn) {
                deselectAllTeamsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    teamCheckboxes.forEach(checkbox => checkbox.checked = false);
                });
            }

            // Person selection handlers
            if (selectAllPersonsBtn) {
                selectAllPersonsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    personCheckboxes.forEach(checkbox => checkbox.checked = true);
                });
            }

            if (deselectAllPersonsBtn) {
                deselectAllPersonsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    personCheckboxes.forEach(checkbox => checkbox.checked = false);
                });
            }

            // Person search functionality
            if (personSearch) {
                personSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    personItems.forEach(item => {
                        const name = item.dataset.name;
                        const username = item.dataset.username;
                        
                        if (name.includes(searchTerm) || username.includes(searchTerm)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            // Initialize visibility
            toggleVisibility();
        });
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
