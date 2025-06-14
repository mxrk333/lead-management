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

// Initialize message variables
$success_message = '';
$error_message = '';

// Ensure goals table exists
$conn = getDbConnection();
$conn->query("CREATE TABLE IF NOT EXISTS goals (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    target_amount DECIMAL(15,2) NOT NULL,
    current_amount DECIMAL(15,2) DEFAULT 0.00,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$conn->close();

// Debug: Check for closed deals directly
$conn = getDbConnection();
$debug_stmt = $conn->prepare("SELECT id, client_name, status, price, updated_at 
                             FROM leads 
                             WHERE user_id = ? 
                             AND (LOWER(status) = 'closed deal' OR LOWER(status) = 'closed')
                             AND price > 0");
$debug_stmt->bind_param("i", $user_id);
$debug_stmt->execute();
$debug_result = $debug_stmt->get_result();

error_log("DEBUG - Checking closed deals for user_id: " . $user_id);
while ($row = $debug_result->fetch_assoc()) {
    error_log("Found closed deal - ID: " . $row['id'] . 
              ", Client: " . $row['client_name'] . 
              ", Status: " . $row['status'] . 
              ", Price: " . $row['price'] . 
              ", Updated: " . $row['updated_at']);
}
$debug_stmt->close();

// Get current active goal and all goals
$current_goal = getCurrentGoal($user_id);
if ($current_goal) {
    error_log("DEBUG - Current Goal Found - ID: " . $current_goal['id'] . 
              ", Target: " . $current_goal['target_amount'] . 
              ", Start: " . $current_goal['start_date'] . 
              ", End: " . $current_goal['end_date']);
} else {
    error_log("DEBUG - No current goal found for user");
}

// Get goal progress if there's a current goal
$goal_progress = [];
if ($current_goal) {
    $goal_progress = getGoalProgress($current_goal['id']);
    error_log("DEBUG - Goal Progress Count: " . count($goal_progress));
    foreach ($goal_progress as $progress) {
        error_log("Progress Entry - Lead ID: " . $progress['lead_id'] . 
                 ", Client: " . $progress['client_name'] . 
                 ", Price: " . $progress['price'] . 
                 ", Status: " . $progress['status']);
    }
}

// Debug: Print all variables
error_log("DEBUG - Current Goal: " . print_r($current_goal, true));
error_log("DEBUG - Goal Progress: " . print_r($goal_progress, true));

// Handle form submission for new goal

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_goal'])) {
    $target_amount = str_replace(',', '', $_POST['target_amount']);
    $target_amount = floatval($target_amount);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if ($target_amount <= 0) {
        $error_message = "Please enter a valid target amount greater than zero.";
    } else if ($start_date >= $end_date) {
        $error_message = "End date must be after start date.";
    } else if (createGoal($user_id, $target_amount, $start_date, $end_date)) {
        $success_message = "Goal created successfully!";
    } else {
        $error_message = "Error creating goal. Please try again.";
    }
}

// Refresh current goal if a new one was created and get all goals
if ($success_message) {
    $current_goal = getCurrentGoal($user_id);
}
$all_goals = getAllGoals($user_id);

// Get goal progress if there's a current goal
$goal_progress = [];
if ($current_goal) {
    $goal_progress = getGoalProgress($current_goal['id']);
    
    // Debug information
    error_log("Current Goal ID: " . $current_goal['id']);
    error_log("Goal Progress Count: " . count($goal_progress));
    if (!empty($goal_progress)) {
        foreach ($goal_progress as $progress) {
            error_log("Progress Entry - Client: " . $progress['client_name'] . 
                     ", Price: " . $progress['price'] . 
                     ", Status: " . $progress['status']);
        }
    } else {
        error_log("No progress entries found");
        
        // Check for closed deals directly
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT id, client_name, price, status, updated_at 
                               FROM leads 
                               WHERE user_id = ? 
                               AND (LOWER(status) = 'closed deal' OR LOWER(status) = 'closed')
                               AND price > 0
                               AND updated_at BETWEEN ? AND ?");
        $stmt->bind_param("iss", $user_id, $current_goal['start_date'], $current_goal['end_date']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        error_log("Direct Closed Deals Check:");
        while ($row = $result->fetch_assoc()) {
            error_log("Found Closed Deal - ID: " . $row['id'] . 
                     ", Client: " . $row['client_name'] . 
                     ", Price: " . $row['price'] . 
                     ", Status: " . $row['status'] . 
                     ", Updated: " . $row['updated_at']);
        }
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Tracker - Inners SPARC Realty Corporation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .goal-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .goal-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .goal-card h3 {
            margin: 0 0 1rem;
            color: var(--primary);
        }
        
        .goal-stats {
            margin-bottom: 1.5rem;
        }
        
        .goal-stat {
            margin-bottom: 1rem;
        }
        
        .goal-stat-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .goal-stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .progress-container {
            background: var(--gray-100);
            height: 8px;
            border-radius: 4px;
            margin: 1rem 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .goal-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1.5rem;
        }
        
        .goal-table th,
        .goal-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .goal-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .goal-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: var(--gray-700);
            padding: 0.75rem 1rem;
        }
        
        .goal-table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }
        
        .goal-table tr:hover {
            background-color: #f8fafc;
        }
        
        .goal-form {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: black;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary i {
            font-size: 1rem;
        }
        
        .btn-outline {
            background-color: white;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .btn-outline:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-400);
            color: var(--gray-900);
            transform: translateY(-1px);
        }

        .btn-outline i {
            font-size: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .action-btn {
            min-width: 100px;
            justify-content: center;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #34d399;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }

        .form-actions {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
            color:rgb(0, 0, 0);
        }

        .section-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-card h3 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .text-right {
            text-align: right !important;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-badge.closed-deal {
            background-color: #d1fae5;
            color: #065f46;
        }

        .total-row {
            background-color: #f8fafc;
            font-weight: 600;
        }

        .total-row td {
            border-top: 2px solid var(--gray-200) !important;
        }

        .alert-info {
            background-color: #e0f2fe;
            color: #075985;
            border: 1px solid #7dd3fc;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary);
        }

        .close-modal {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--gray-500);
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }

        .close-modal:hover {
            color: var(--gray-700);
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-secondary {
            background-color: var(--gray-200);
            color: var(--gray-700);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background-color: var(--gray-300);
        }
    </style>
</head>
<body>
    <div class="containers">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-wrapper">
            <div class="main-content">
                <?php include 'includes/header.php'; ?>
                
                <div class="dashboard">
                <div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h1>Goal Tracker</h1>
                    <button class="btn-primary" onclick="openNewGoalModal()">
                        <i class="fas fa-plus"></i> Set New Goal
                    </button>
                </div>
                
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>
                
                <div class="goal-cards">
                    <?php if ($current_goal): ?>
                    <div class="goal-card">
                        <h3>Current Goal Progress</h3>
                        <div class="goal-stats">
                            <div class="goal-stat">
                                <div class="goal-stat-label">Target Amount</div>
                                <div class="goal-stat-value">₱<?php echo number_format($current_goal['target_amount'], 2); ?></div>
                            </div>
                            <div class="goal-stat">
                                <div class="goal-stat-label">Current Progress</div>
                                <div class="goal-stat-value">₱<?php 
                                    $total_progress = $current_goal['current_amount'];
                                    echo number_format($total_progress, 2); 
                                ?></div>
                            </div>
                            <div class="goal-stat">
                                <div class="goal-stat-label">Period</div>
                                <div class="goal-stat-value" style="font-size: 1rem;">
                                    <?php echo date('M d, Y', strtotime($current_goal['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($current_goal['end_date'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        $progress_percentage = ($total_progress / $current_goal['target_amount']) * 100;
                        $progress_percentage = min(100, $progress_percentage);
                        ?>
                        
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo $progress_percentage; ?>%"></div>
                        </div>
                        <div style="text-align: right; font-size: 0.875rem; color: var(--gray-600);">
                            <?php echo number_format($progress_percentage, 1); ?>% Complete
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- New Goal Modal -->
                <div id="newGoalModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Set New Goal</h3>
                            <button class="close-modal" onclick="closeNewGoalModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="newGoalForm" class="goal-form" method="POST">
                                <div class="form-group">
                                    <label for="target_amount">Target Amount (₱)</label>
                                    <input type="text" id="target_amount" name="target_amount" placeholder="e.g. 1,000,000.00" required>
                                </div>
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="end_date">End Date</label>
                                    <input type="date" id="end_date" name="end_date" required>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn-secondary" onclick="closeNewGoalModal()">Cancel</button>
                                    <button type="submit" name="create_goal" class="btn-primary">
                                        <i class="fas fa-plus"></i> Create Goal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($current_goal && !empty($goal_progress)): ?>
                <div class="section-card">
                    <h3>Progress Details</h3>
                    <div class="table-container">
                        <table class="goal-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_amount = 0;
                                foreach ($goal_progress as $progress): 
                                    $total_amount += floatval($progress['price']);
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($progress['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($progress['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($progress['project_model']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $progress['status'])); ?>">
                                            <?php echo htmlspecialchars($progress['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-right">₱<?php echo number_format($progress['price'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="4" class="text-right"><strong>Total:</strong></td>
                                    <td class="text-right"><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif ($current_goal): ?>
                <div class="section-card">
                    <div class="alert alert-info">
                        No closed deals found for the current goal period.
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($all_goals)): ?>
                <h2>Goal History</h2>
                <div class="table-container">
                    <table class="goal-table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Target Amount</th>
                                <th class="text-right">Achieved Amount</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_goals as $goal): ?>
                            <tr>
                                <td>
                                    <?php echo date('M d, Y', strtotime($goal['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($goal['end_date'])); ?>
                                </td>
                                <td class="text-right">₱<?php echo number_format($goal['target_amount'], 2); ?></td>
                                <td class="text-right">₱<?php echo number_format($goal['achieved_amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $goal_progress = ($goal['achieved_amount'] / $goal['target_amount']) * 100;
                                    $goal_progress = min(100, $goal_progress);
                                    ?>
                                    <div class="progress-container" style="width: 100px; display: inline-block; margin-right: 1rem;">
                                        <div class="progress-bar" style="width: <?php echo $goal_progress; ?>%"></div>
                                    </div>
                                    <?php echo number_format($goal_progress, 1); ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/script.js"></script>
    <script>
        // Format number with commas and decimals
        function formatNumber(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Parse formatted number back to float
        function parseFormattedNumber(formatted) {
            return parseFloat(formatted.replace(/,/g, ''));
        }

        // Handle target amount input
        document.getElementById('target_amount').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d.]/g, '');
            let parts = value.split('.');
            
            if (parts.length > 2) {
                parts = [parts[0], parts.slice(1).join('')];
            }
            
            if (parts[1] && parts[1].length > 2) {
                parts[1] = parts[1].slice(0, 2);
            }
            
            value = parts.join('.');
            
            if (value) {
                const number = parseFloat(value);
                if (!isNaN(number)) {
                    e.target.value = formatNumber(value);
                }
            }
        });

        // Handle form submission
        document.getElementById('newGoalForm').addEventListener('submit', function(e) {
            const targetInput = document.getElementById('target_amount');
            const formattedValue = targetInput.value;
            const numericValue = parseFormattedNumber(formattedValue);
            
            if (isNaN(numericValue) || numericValue <= 0) {
                e.preventDefault();
                alert('Please enter a valid target amount');
                return;
            }
            
            // Update the input value to the numeric version before submitting
            targetInput.value = numericValue;
        });

        // Modal functions
        function openNewGoalModal() {
            document.getElementById('newGoalModal').style.display = 'block';
            // Set minimum date as today for start date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').min = today;
        }

        function closeNewGoalModal() {
            document.getElementById('newGoalModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('newGoalModal');
            if (event.target == modal) {
                closeNewGoalModal();
            }
        }

        // Update end date min value when start date changes
        document.getElementById('start_date').addEventListener('change', function(e) {
            document.getElementById('end_date').min = e.target.value;
        });
    </script>
</body>
</html>
