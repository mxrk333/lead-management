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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $when = date('Y-m-d H:i:s');
        $file_path = null;
        $visible_to_all = isset($_POST['visible_to_all']) ? (int)$_POST['visible_to_all'] : 0;
        $priority = $_POST['priority'] ?? 'Medium';
        
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
        
        $stmt = $conn->prepare("INSERT INTO memos (title, file_path, description, memo_when, priority, created_by, team_id, visible_to_all, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssiii", $title, $file_path, $description, $when, $priority, $_SESSION['user_id'], $team_id, $visible_to_all);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating memo: " . $stmt->error);
        }
        
        $memo_id = $conn->insert_id;
        
        // Handle team visibility
        if ($visible_to_all == 0) {
            $selected_teams = (isset($_POST['team_ids']) && is_array($_POST['team_ids'])) 
                ? array_map('intval', $_POST['team_ids']) 
                : [];
            
            if (!empty($selected_teams)) {
                $insert_stmt = $conn->prepare("INSERT INTO memo_team_visibility (memo_id, team_id) VALUES (?, ?)");
                foreach ($selected_teams as $valid_team_id) {
                    $insert_stmt->bind_param("ii", $memo_id, $valid_team_id);
                    $insert_stmt->execute();
                }
            }
        }
        
        $conn->commit();
        header("Location: memo.php?success=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
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

        .team-selector {
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-top: 1rem;
            display: none;
        }

        .team-selector-header {
            background-color: var(--gray-50);
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .team-actions {
            display: flex;
            gap: 1rem;
        }

        .team-action {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .team-action:hover {
            text-decoration: underline;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
            padding: 1rem;
            max-height: 200px;
            overflow-y: auto;
        }

        .team-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .team-checkbox:hover {
            background-color: var(--gray-50);
        }

        .team-checkbox input {
            width: 1rem;
            height: 1rem;
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

            .team-grid {
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
                                    <input type="radio" name="visible_to_all" id="visible_all" value="1" checked>
                                    <label for="visible_all">All Teams</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" name="visible_to_all" id="visible_specific" value="0">
                                    <label for="visible_specific">Specific Teams</label>
                                </div>
                            </div>
                            
                            <div id="team-selection" class="team-selector">
                                <div class="team-selector-header">
                                    <span>Select teams to share with:</span>
                                    <div class="team-actions">
                                        <a href="#" id="select-all" class="team-action">Select All</a>
                                        <a href="#" id="deselect-all" class="team-action">Deselect All</a>
                                    </div>
                                </div>
                                <div class="team-grid">
                                    <?php foreach ($teams as $team_id => $team_name): ?>
                                        <div class="team-checkbox">
                                            <input type="checkbox" id="team_<?php echo $team_id; ?>" 
                                                   name="team_ids[]" value="<?php echo $team_id; ?>"
                                                   <?php echo $team_id == $user['team_id'] ? 'checked' : ''; ?>>
                                            <label for="team_<?php echo $team_id; ?>"><?php echo htmlspecialchars($team_name); ?></label>
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
            const visibilityRadios = document.querySelectorAll('input[name="visible_to_all"]');
            const teamSelection = document.getElementById('team-selection');
            const selectAllBtn = document.getElementById('select-all');
            const deselectAllBtn = document.getElementById('deselect-all');
            const teamCheckboxes = document.querySelectorAll('input[name="team_ids[]"]');

            function toggleTeamSelection() {
                const isVisibleToAll = document.querySelector('input[name="visible_to_all"]:checked').value === '1';
                teamSelection.style.display = isVisibleToAll ? 'none' : 'block';
            }

            visibilityRadios.forEach(radio => {
                radio.addEventListener('change', toggleTeamSelection);
            });

            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    teamCheckboxes.forEach(checkbox => checkbox.checked = true);
                });
            }

            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    teamCheckboxes.forEach(checkbox => checkbox.checked = false);
                });
            }

            toggleTeamSelection();
        });
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
