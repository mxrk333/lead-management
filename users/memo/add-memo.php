<?php
session_start();

// Determine the correct base path - this file is in users/memo/ directory, need to go up 2 levels
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/config/database.php';
require_once $base_path . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];

try {
    $conn = getDbConnection();
    
    // Get user information
    $user_query = "SELECT u.*, t.name as team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id WHERE u.id = ?";
    $user_stmt = $conn->prepare($user_query);
    
    if (!$user_stmt) {
        throw new Exception("Failed to prepare user query: " . $conn->error);
    }
    
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();

    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Database connection error in add-memo.php: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Check if user is admin or manager
$isAuthorized = ($user['role'] === 'admin' || $user['role'] === 'manager');
if (!$isAuthorized) {
    header("Location: memo.php");
    exit();
}

// Get all teams for dropdown
try {
    $teams_query = "SELECT id, name FROM teams ORDER BY name ASC";
    $teams_result = $conn->query($teams_query);
    $teams = [];
    
    if ($teams_result) {
        while ($team = $teams_result->fetch_assoc()) {
            $teams[$team['id']] = $team['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching teams: " . $e->getMessage());
    $teams = [];
}

// Get all users for person selection
try {
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
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $when = date('Y-m-d H:i:s');
        $file_path = null;
        $visibility_type = $_POST['visibility_type'] ?? 'all';
        $priority = $_POST['priority'] ?? 'Medium';
        
        // Validate required fields
        if (empty($title) || empty($description)) {
            throw new Exception("Title and description are required.");
        }
        
        // Debug: Log the visibility data
        error_log("Memo creation - Visibility type: " . $visibility_type);
        error_log("Memo creation - POST data: " . print_r($_POST, true));
        
        // Handle file upload
        if (isset($_FILES['memo_file']) && $_FILES['memo_file']['size'] > 0) {
            $target_dir = "uploads/memos/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            // Validate file
            $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
            $file_extension = strtolower(pathinfo($_FILES['memo_file']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_types)) {
                throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_types));
            }
            
            if ($_FILES['memo_file']['size'] > 10 * 1024 * 1024) { // 10MB limit
                throw new Exception("File size too large. Maximum size is 10MB.");
            }
            
            $file_path = $target_dir . time() . '_' . basename($_FILES['memo_file']['name']);
            
            if (!move_uploaded_file($_FILES['memo_file']['tmp_name'], $file_path)) {
                throw new Exception("Failed to upload file.");
            }
        }
        
        $conn->begin_transaction();
        
        $team_id = $user['team_id'];
        $visible_to_all = ($visibility_type === 'all') ? 1 : 0;
        
        // Insert memo
        $stmt = $conn->prepare("INSERT INTO memos (title, file_path, description, memo_when, priority, created_by, team_id, visible_to_all, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare memo insert statement: " . $conn->error);
        }
        
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
                if (!$insert_stmt) {
                    throw new Exception("Failed to prepare team visibility statement: " . $conn->error);
                }
                
                foreach ($selected_teams as $valid_team_id) {
                    $insert_stmt->bind_param("ii", $memo_id, $valid_team_id);
                    if (!$insert_stmt->execute()) {
                        throw new Exception("Failed to insert team visibility: " . $insert_stmt->error);
                    }
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
                
                if (!$conn->query($create_table_sql)) {
                    error_log("Failed to create memo_person_visibility table: " . $conn->error);
                }
                
                $insert_stmt = $conn->prepare("INSERT INTO memo_person_visibility (memo_id, user_id) VALUES (?, ?)");
                if (!$insert_stmt) {
                    throw new Exception("Failed to prepare person visibility statement: " . $conn->error);
                }
                
                foreach ($selected_persons as $valid_user_id) {
                    $insert_stmt->bind_param("ii", $memo_id, $valid_user_id);
                    if (!$insert_stmt->execute()) {
                        throw new Exception("Failed to insert person visibility: " . $insert_stmt->error);
                    }
                }
                error_log("Person visibility records inserted");
            }
        }
        
        $conn->commit();
        error_log("Memo creation completed successfully");
        header("Location: memo.php?success=1");
        exit();
        
    } catch (Exception $e) {
        if ($conn && $conn->inTransaction()) {
            $conn->rollback();
        }
        error_log("Memo creation error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}
?>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Memo - Inner SPARC Realty Corporation</title>
    <link rel="stylesheet" href="../assets/styles/add-memo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <?php 
        // Include sidebar with error handling
        $sidebar_path = $base_path . '/includes/sidebar.php';
        if (file_exists($sidebar_path)) {
            include $sidebar_path;
        } else {
            echo '<div style="width: 250px; background: #f3f4f6; padding: 1rem;">Sidebar not found at: ' . $sidebar_path . '</div>';
        }
        ?>
        
        <div class="main-content">
            <?php 
            // Include header with error handling
            $header_path = $base_path . '/includes/header.php';
            if (file_exists($header_path)) {
                include $header_path;
            } else {
                echo '<div style="height: 60px; background: white; border-bottom: 1px solid #e5e7eb; padding: 1rem;">Header not found at: ' . $header_path . '</div>';
            }
            ?>
            
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
                            <input type="text" name="title" class="form-control" required placeholder="Enter memo title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" required placeholder="Enter memo description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <div class="priority-options">
                                <div class="priority-option">
                                    <input type="radio" name="priority" id="priority_low" value="Low" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'Low') ? 'checked' : ''; ?>>
                                    <label for="priority_low" class="priority-label low">Low</label>
                                </div>
                                <div class="priority-option">
                                    <input type="radio" name="priority" id="priority_medium" value="Medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] === 'Medium') ? 'checked' : ''; ?>>
                                    <label for="priority_medium" class="priority-label medium">Medium</label>
                                </div>
                                <div class="priority-option">
                                    <input type="radio" name="priority" id="priority_high" value="High" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'High') ? 'checked' : ''; ?>>
                                    <label for="priority_high" class="priority-label high">High</label>
                                </div>
                                <div class="priority-option">
                                    <input type="radio" name="priority" id="priority_urgent" value="Urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'Urgent') ? 'checked' : ''; ?>>
                                    <label for="priority_urgent" class="priority-label urgent">Urgent</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Attachment (Optional)</label>
                            <input type="file" name="memo_file" class="form-control" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                            <small style="color: var(--gray-500); font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                                Allowed file types: PDF, DOC, DOCX, TXT, JPG, JPEG, PNG, GIF (Max: 10MB)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Visibility</label>
                            <div class="radio-group">
                                <div class="radio-option">
                                    <input type="radio" name="visibility_type" id="visibility_all" value="all" <?php echo (!isset($_POST['visibility_type']) || $_POST['visibility_type'] === 'all') ? 'checked' : ''; ?>>
                                    <label for="visibility_all">All Teams</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" name="visibility_type" id="visibility_teams" value="teams" <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] === 'teams') ? 'checked' : ''; ?>>
                                    <label for="visibility_teams">Specific Teams</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" name="visibility_type" id="visibility_persons" value="persons" <?php echo (isset($_POST['visibility_type']) && $_POST['visibility_type'] === 'persons') ? 'checked' : ''; ?>>
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
                                                   <?php echo ($team_id == $user['team_id'] || (isset($_POST['team_ids']) && in_array($team_id, $_POST['team_ids']))) ? 'checked' : ''; ?>>
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
                                    <?php foreach ($users as $user_id_loop => $user_data): ?>
                                        <div class="selector-checkbox person-item" data-name="<?php echo strtolower($user_data['name']); ?>" data-username="<?php echo strtolower($user_data['username']); ?>">
                                            <input type="checkbox" id="person_<?php echo $user_id_loop; ?>"
                                                   name="person_ids[]" value="<?php echo $user_id_loop; ?>"
                                                   <?php echo ($user_id_loop == $_SESSION['user_id'] || (isset($_POST['person_ids']) && in_array($user_id_loop, $_POST['person_ids']))) ? 'checked' : ''; ?>>
                                            <div class="user-info">
                                                <label for="person_<?php echo $user_id_loop; ?>" class="user-name"><?php echo htmlspecialchars($user_data['name']); ?></label>
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


    
    <script src="../assets/js/add-memo.js"></script>
</body>
</html>