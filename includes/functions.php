<?php
if (!function_exists('isSuperUser')) {
    function isSuperUser($username) {
        $superusers = [
            'markpatigayon.itadmin',
            'gabriellibacao.founder',
            'romeocorberta.itdept'
        ];
        return in_array($username, $superusers);
    }
}
// Goal tracking functions
function createGoal($user_id, $target_amount, $start_date, $end_date) {
    $conn = getDbConnection();
    
    try {
        // Check if there's already an active goal that overlaps with the new goal period
        $stmt = $conn->prepare("SELECT id FROM goals 
                              WHERE user_id = ? 
                              AND ((start_date <= ? AND end_date >= ?) 
                                   OR (start_date <= ? AND end_date >= ?) 
                                   OR (start_date >= ? AND end_date <= ?))");
        $stmt->bind_param("issssss", $user_id, $end_date, $start_date, $start_date, $start_date, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            error_log("Cannot create goal: Overlapping goal exists");
            return false;
        }
        
        // Create the new goal
        $stmt = $conn->prepare("INSERT INTO goals (user_id, target_amount, start_date, end_date, created_at) 
                              VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("idss", $user_id, $target_amount, $start_date, $end_date);
        $success = $stmt->execute();
        
        if ($success) {
            // Create the goals table if it doesn't exist
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
            
            error_log("Goal created successfully: " . $stmt->insert_id);
            return true;
        } else {
            error_log("Error creating goal: " . $stmt->error);
            return false;
        }
    } catch (Exception $e) {
        error_log("Error creating goal: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}

function getCurrentGoal($user_id) {
    $conn = getDbConnection();
    
    try {
        // Create the goals table if it doesn't exist
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
        
        // Get the current active goal
        $stmt = $conn->prepare("SELECT id, target_amount, current_amount, start_date, end_date, created_at 
                              FROM goals 
                              WHERE user_id = ? 
                              AND start_date <= CURDATE() 
                              AND end_date >= CURDATE() 
                              ORDER BY created_at DESC 
                              LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $goal = $result->fetch_assoc();
            
            // Calculate current amount from closed deals
            $stmt = $conn->prepare("SELECT SUM(price) as total 
                                  FROM leads 
                                  WHERE user_id = ? 
                                  AND (LOWER(status) = 'closed deal' OR LOWER(status) = 'closed') 
                                  AND price > 0
                                  AND updated_at BETWEEN ? AND ?");
            $stmt->bind_param("iss", $user_id, $goal['start_date'], $goal['end_date']);
            $stmt->execute();
            $amount_result = $stmt->get_result();
            $amount_row = $amount_result->fetch_assoc();
            
            // Update the current amount in the goal
            $current_amount = $amount_row['total'] ? $amount_row['total'] : 0;
            $update_stmt = $conn->prepare("UPDATE goals SET current_amount = ? WHERE id = ?");
            $update_stmt->bind_param("di", $current_amount, $goal['id']);
            $update_stmt->execute();
            
            $goal['current_amount'] = $current_amount;
            
            return $goal;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting current goal: " . $e->getMessage());
        return null;
    } finally {
        $conn->close();
    }
}

function acknowledgeMemo($memo_id, $employee_id) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO memo_read_status (memo_id, employee_id, read_status, read_at) 
                           VALUES (?, ?, 1, NOW()) 
                           ON DUPLICATE KEY UPDATE read_status = 1, read_at = NOW()");
    $stmt->bind_param("ii", $memo_id, $employee_id);
    return $stmt->execute();
}

function getMemoReadStatus($memo_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT u.name, mrs.read_at 
                           FROM memo_read_status mrs 
                           JOIN users u ON mrs.employee_id = u.id 
                           WHERE mrs.memo_id = ? AND mrs.read_status = 1");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getAllGoals($user_id) {
    $conn = getDbConnection();
    
    try {
        // Create the goals table if it doesn't exist
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
        
        $stmt = $conn->prepare("SELECT id, target_amount, current_amount, start_date, end_date, created_at 
                              FROM goals 
                              WHERE user_id = ? 
                              ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $goals = [];
        while ($row = $result->fetch_assoc()) {
            // Calculate achieved amount for each goal
            $achieved_stmt = $conn->prepare("SELECT SUM(price) as total 
                                          FROM leads 
                                          WHERE user_id = ? 
                                          AND (LOWER(status) = 'closed deal' OR LOWER(status) = 'closed') 
                                          AND price > 0
                                          AND updated_at BETWEEN ? AND ?");
            $achieved_stmt->bind_param("iss", $user_id, $row['start_date'], $row['end_date']);
            $achieved_stmt->execute();
            $achieved_result = $achieved_stmt->get_result();
            $achieved_row = $achieved_result->fetch_assoc();
            
            $row['achieved_amount'] = $achieved_row['total'] ? $achieved_row['total'] : 0;
            $goals[] = $row;
        }
        
        return $goals;
    } catch (Exception $e) {
        error_log("Error getting all goals: " . $e->getMessage());
        return [];
    } finally {
        $conn->close();
    }
}

function getGoalProgress($goal_id) {
    $conn = getDbConnection();
    
    try {
        // Get the goal details
        $stmt = $conn->prepare("SELECT user_id, start_date, end_date FROM goals WHERE id = ?");
        $stmt->bind_param("i", $goal_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [];
        }
        
        $goal = $result->fetch_assoc();
        
        // Get all closed deals within the goal period
        $stmt = $conn->prepare("SELECT id as lead_id, client_name, project_model, price, status, created_at, updated_at 
                              FROM leads 
                              WHERE user_id = ? 
                              AND (LOWER(status) = 'closed deal' OR LOWER(status) = 'closed') 
                              AND price > 0
                              AND updated_at BETWEEN ? AND ? 
                              ORDER BY updated_at DESC");
        $stmt->bind_param("iss", $goal['user_id'], $goal['start_date'], $goal['end_date']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $progress = [];
        while ($row = $result->fetch_assoc()) {
            $progress[] = $row;
        }
        
        return $progress;
    } catch (Exception $e) {
        error_log("Error getting goal progress: " . $e->getMessage());
        return [];
    } finally {
        $conn->close();
    }
}

// Get user position function for incentives.php
function getUserPosition($user_id) {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("SELECT position FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            return $user['position'];
        }
        
        return 'Agent'; // Default position if not found
    } catch (Exception $e) {
        error_log("Error getting user position: " . $e->getMessage());
        return 'Agent';
    } finally {
        $conn->close();
    }
}

// Get user name by ID function for incentives.php
function getUserNameById($user_id) {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            return $user['name'];
        }
        
        return 'Unknown User'; // Default name if not found
    } catch (Exception $e) {
        error_log("Error getting user name: " . $e->getMessage());
        return 'Unknown User';
    } finally {
        $conn->close();
    }
}

// User authentication functions
function validateLogin($username, $password) {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}

function getUserById($user_id) {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("Error getting user: " . $e->getMessage());
        return null;
    } finally {
        $conn->close();
    }
}

// Dashboard functions
function getDashboardData($userId, $userRole) {
    $conn = getDbConnection();
    $data = [];
    
    // Get total leads based on user role
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads");
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        // Get team members
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        // Get team members
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } else {
        // Agent - only see their own leads
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['total_leads'] = $row['count'];
    
    // Calculate total portfolio value
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT SUM(price) as total_value FROM leads");
        $stmt->execute();
    } elseif ($userRole == 'manager' || $userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT SUM(price) as total_value FROM leads 
                               WHERE user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT SUM(price) as total_value FROM leads WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['price'] = $row['total_value'] ? number_format($row['total_value'], 2) : '0.00';
    
    // Get presentation stage leads
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads WHERE status = 'Presentation Stage'");
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Presentation Stage' AND 
                               user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Presentation Stage' AND 
                               user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads WHERE status = 'Presentation Stage' AND user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['presentation_stage'] = $row['count'];
    
    // Get closed deals
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads WHERE status = 'Closed'");
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Closed' AND 
                               user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Closed' AND 
                               user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads WHERE status = 'Closed' AND user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['closed_deals'] = $row['count'];
    
    // Calculate closed deal rate
    $data['closed_deal_rate'] = ($data['total_leads'] > 0) ? round(($data['closed_deals'] / $data['total_leads']) * 100, 1) : 0;
    
    // Get most inquired project
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT developer, COUNT(*) as count FROM leads GROUP BY developer ORDER BY count DESC LIMIT 1");
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        $stmt = $conn->prepare("SELECT developer, COUNT(*) as count FROM leads 
                               WHERE user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               GROUP BY developer ORDER BY count DESC LIMIT 1");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT developer, COUNT(*) as count FROM leads 
                               WHERE user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               GROUP BY developer ORDER BY count DESC LIMIT 1");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT developer, COUNT(*) as count FROM leads 
                               WHERE user_id = ? GROUP BY developer ORDER BY count DESC LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $data['most_inquired_project'] = $row['developer'];
    } else {
        $data['most_inquired_project'] = 'N/A';
    }
    
    // Get most inquired model
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT project_model, COUNT(*) as count FROM leads GROUP BY project_model ORDER BY count DESC LIMIT 1");
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        $stmt = $conn->prepare("SELECT project_model, COUNT(*) as count FROM leads 
                               WHERE user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               GROUP BY project_model ORDER BY count DESC LIMIT 1");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT project_model, COUNT(*) as count FROM leads 
                               WHERE user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               GROUP BY project_model ORDER BY count DESC LIMIT 1");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT project_model, COUNT(*) as count FROM leads 
                               WHERE user_id = ? GROUP BY project_model ORDER BY count DESC LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $data['most_inquired_model'] = $row['project_model'];
    } else {
        $data['most_inquired_model'] = 'N/A';
    }
    
    // Get recent leads
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               ORDER BY l.created_at DESC LIMIT 5");
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC LIMIT 5");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC LIMIT 5");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.user_id = ? 
                               ORDER BY l.created_at DESC LIMIT 5");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $data['recent_leads'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['recent_leads'][] = $row;
    }
    
    // Additional data for managers
    if ($userRole == 'manager') {
        // Get team members count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE team_id = ?");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $data['team_members'] = $row['count'];
        
        // Get team leads count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $data['team_leads'] = $row['count'];
    }
    
    $stmt->close();
    $conn->close();
    return $data;
}

// Lead management functions
function getLeads($userId, $userRole) {
    $conn = getDbConnection();
    $leads = [];
    
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               ORDER BY l.created_at DESC");
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
    } else {
        // Agent - only see their own leads
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.user_id = ? 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $leads;
}

function searchLeads($search, $userId, $userRole) {
    $conn = getDbConnection();
    $leads = [];
    $searchTerm = "%$search%";
    
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.client_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? 
                               OR l.developer LIKE ? OR l.project_model LIKE ? 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("sssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE (l.client_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? 
                               OR l.developer LIKE ? OR l.project_model LIKE ?) 
                               AND l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("sssssi", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE (l.client_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? 
                               OR l.developer LIKE ? OR l.project_model LIKE ?) 
                               AND l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("sssssi", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $teamId);
        $stmt->execute();
    } else {
        // Agent - only see their own leads
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE (l.client_name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? 
                               OR l.developer LIKE ? OR l.project_model LIKE ?) 
                               AND l.user_id = ? 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("sssssi", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $leads;
}

function filterLeadsByStatus($status, $userId, $userRole) {
    $conn = getDbConnection();
    $leads = [];
    
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.status = ? 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("s", $status);
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.status = ? 
                               AND l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("si", $status, $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.status = ? 
                               AND l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("si", $status, $teamId);
        $stmt->execute();
    } else {
        // Agent - only see their own leads
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.status = ? AND l.user_id = ? 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("si", $status, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $leads;
}

function filterLeadsByTemperature($temperature, $userId, $userRole) {
    $conn = getDbConnection();
    $leads = [];
    
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.temperature = ? 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("s", $temperature);
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.temperature = ? 
                               AND l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("si", $temperature, $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.temperature = ? 
                               AND l.user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("si", $temperature, $teamId);
        $stmt->execute();
    } else {
        // Agent - only see their own leads
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.temperature = ? AND l.user_id = ? 
                               ORDER BY l.created_at DESC");
        $stmt->bind_param("si", $temperature, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $leads;
}

function getLeadById($leadId, $userId, $userRole) {
    $conn = getDbConnection();
    
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.id = ?");
        $stmt->bind_param("i", $leadId);
        $stmt->execute();
    } elseif ($userRole == 'manager') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.id = ? 
                               AND l.user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("ii", $leadId, $teamId);
        $stmt->execute();
    } elseif ($userRole == 'supervisor') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.id = ? 
                               AND l.user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("ii", $leadId, $teamId);
        $stmt->execute();
    } else {
        // Agent - only see their own leads
        $stmt = $conn->prepare("SELECT l.*, u.name as created_by_name FROM leads l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.id = ? AND l.user_id = ?");
        $stmt->bind_param("ii", $leadId, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $lead = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $lead;
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

function addLead($user_id, $client_name, $phone, $email, $facebook, $linkedin, $temperature, $status, $source, $lead_classification, $developer, $project_model, $price, $remarks) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO leads (user_id, client_name, phone, email, facebook, linkedin, temperature, status, source, lead_classification, developer, project_model, price, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("isssssssssssds", $user_id, $client_name, $phone, $email, $facebook, $linkedin, $temperature, $status, $source, $lead_classification, $developer, $project_model, $price, $remarks);
    
    $result = $stmt->execute();
    $insert_id = $conn->insert_id;
    
    $stmt->close();
    $conn->close();
    
    return $result ? $insert_id : false;
}

function updateLead($leadId, $clientName, $phone, $email, $facebook, $linkedin, 
                   $temperature, $status, $source, $leadClassification, $developer, $projectModel, $price, $remarks) {
    $conn = getDbConnection();
    
    // Get the old status before updating
    $old_status_stmt = $conn->prepare("SELECT status, user_id FROM leads WHERE id = ?");
    $old_status_stmt->bind_param("i", $leadId);
    $old_status_stmt->execute();
    $old_status_result = $old_status_stmt->get_result();
    $old_lead_data = $old_status_result->fetch_assoc();
    $old_status = $old_lead_data['status'] ?? '';
    $user_id = $old_lead_data['user_id'] ?? null;
    $old_status_stmt->close();
    
    $stmt = $conn->prepare("UPDATE leads SET client_name = ?, phone = ?, email = ?, 
                           facebook = ?, linkedin = ?, temperature = ?, status = ?, 
                           source = ?, lead_classification = ?, developer = ?, project_model = ?, price = ?, remarks = ? 
                           WHERE id = ?");

    $stmt->bind_param("sssssssssssdsi", $clientName, $phone, $email, $facebook, $linkedin,
                     $temperature, $status, $source, $leadClassification, $developer, $projectModel, $price, $remarks, $leadId);
    $result = $stmt->execute();
    
    $stmt->close();
    
    // AUTOMATICALLY award raffle tickets if status changed
    if ($result && $user_id && $old_status !== $status) {
        awardRaffleTicketsForStatusChange($leadId, $user_id, $status, $old_status);
    }
    
    $conn->close();
    return $result;
}

function deleteLead($leadId, $userId, $userRole) {
    $conn = getDbConnection();
    
    // Check if user has permission to delete this lead
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->bind_param("i", $leadId);
        $result = $stmt->execute();
    } elseif ($userRole == 'manager') {
        // Get team ID
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
        
        $stmt = $conn->prepare("DELETE FROM leads WHERE id = ? AND 
                               user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("ii", $leadId, $teamId);
        $result = $stmt->execute();
    } else {
        // Agents and supervisors can only delete their own leads
        $stmt = $conn->prepare("DELETE FROM leads WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $leadId, $userId);
        $result = $stmt->execute();
    }
    
    $stmt->close();
    $conn->close();
    return $result;
}

// Lead activity functions
function getLeadActivities($leadId) {
    $conn = getDbConnection();
    $activities = [];
    
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            u.name as user_name,
            l.client_name,
            l.status as lead_status,
            l.temperature as lead_temperature
        FROM lead_activities a 
        JOIN users u ON a.user_id = u.id 
        JOIN leads l ON a.lead_id = l.id
        WHERE a.lead_id = ? 
        ORDER BY a.created_at DESC
    ");
    
    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error);
        return $activities;
    }
    
    $stmt->bind_param("i", $leadId);
    
    if (!$stmt->execute()) {
        error_log("Error executing statement: " . $stmt->error);
        $stmt->close();
        $conn->close();
        return $activities;
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $activities;
}

function getRecentActivities($userId, $userRole, $limit = 10) {
    $conn = getDbConnection();
    $activities = [];
    
    // Base query with role-based conditions
    $query = "
        SELECT 
            a.*,
            u.name as user_name,
            l.client_name,
            l.status as lead_status,
            l.temperature as lead_temperature,
            l.id as lead_id
        FROM lead_activities a 
        JOIN users u ON a.user_id = u.id 
        JOIN leads l ON a.lead_id = l.id
    ";
    
    // Add role-based conditions
    if ($userRole == 'admin') {
        // Admin can see all activities
        $stmt = $conn->prepare($query . " ORDER BY a.created_at DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
    } elseif ($userRole == 'manager') {
        // Manager can see team activities
        $query .= " WHERE u.team_id = (SELECT team_id FROM users WHERE id = ?)";
        $stmt = $conn->prepare($query . " ORDER BY a.created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $userId, $limit);
    } else {
        // Others can only see their own activities
        $query .= " WHERE a.user_id = ?";
        $stmt = $conn->prepare($query . " ORDER BY a.created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $userId, $limit);
    }
    
    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error);
        return $activities;
    }
    
    if (!$stmt->execute()) {
        error_log("Error executing statement: " . $stmt->error);
        $stmt->close();
        $conn->close();
        return $activities;
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $activities;
}

function addLeadActivity($leadId, $userId, $activityType, $notes) {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("INSERT INTO lead_activities (lead_id, user_id, activity_type, notes) 
                               VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $leadId, $userId, $activityType, $notes);
        $result = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error adding lead activity: " . $e->getMessage());
        if ($stmt) {
            $stmt->close();
        }
        $conn->close();
        return false;
    }
}

// Report functions
function getQuarterlyReport($userId, $userRole, $year, $quarter) {
    $conn = getDbConnection();
    $data = [];
    
    // Calculate quarter date range
    $startMonth = ($quarter - 1) * 3 + 1;
    $endMonth = $quarter * 3;
    $startDate = "$year-$startMonth-01";
    $endDate = date('Y-m-t', strtotime("$year-$endMonth-01"));
    
    // Get team ID if manager or supervisor
    $teamId = null;
    if ($userRole == 'manager' || $userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $teamId = $user['team_id'];
    }
    
    // Get total leads for the quarter
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ?");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
    } elseif ($userRole == 'manager' || $userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? 
                               AND user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("ssi", $startDate, $endDate, $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? AND user_id = ?");
        $stmt->bind_param("ssi", $startDate, $endDate, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['total_leads'] = $row['count'];
    
    // Get presentations for the quarter
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Presentation Stage' AND created_at BETWEEN ? AND ?");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
    } elseif ($userRole == 'manager' || $userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Presentation Stage' AND created_at BETWEEN ? AND ? 
                               AND user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("ssi", $startDate, $endDate, $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Presentation Stage' AND created_at BETWEEN ? AND ? 
                               AND user_id = ?");
        $stmt->bind_param("ssi", $startDate, $endDate, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['presentations'] = $row['count'];
    
    // Get closed deals for the quarter
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Closed' AND created_at BETWEEN ? AND ?");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
    } elseif ($userRole == 'manager' || $userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Closed' AND created_at BETWEEN ? AND ? 
                               AND user_id IN (SELECT id FROM users WHERE team_id = ?)");
        $stmt->bind_param("ssi", $startDate, $endDate, $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leads 
                               WHERE status = 'Closed' AND created_at BETWEEN ? AND ? 
                               AND user_id = ?");
        $stmt->bind_param("ssi", $startDate, $endDate, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $data['closed_deals'] = $row['count'];
    
    // Calculate conversion rate
    $data['conversion_rate'] = ($data['total_leads'] > 0) ? round(($data['closed_deals'] / $data['total_leads']) * 100, 1) : 0;
    
    // Get status distribution
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? 
                               GROUP BY status");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
    } elseif ($userRole == 'manager' || $userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? 
                               AND user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               GROUP BY status");
        $stmt->bind_param("ssi", $startDate, $endDate, $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? AND user_id = ? 
                               GROUP BY status");
        $stmt->bind_param("ssi", $startDate, $endDate, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $data['status_distribution'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['status_distribution'][] = $row;
    }
    
    // Get temperature distribution
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT temperature, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? 
                               GROUP BY temperature");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
    } elseif ($userRole == 'manager' || $userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT temperature, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? 
                               AND user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               GROUP BY temperature");
        $stmt->bind_param("ssi", $startDate, $endDate, $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT temperature, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? AND user_id = ? 
                               GROUP BY temperature");
        $stmt->bind_param("ssi", $startDate, $endDate, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $data['temperature_distribution'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['temperature_distribution'][] = $row;
    }
    
    // Get top projects
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT developer, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? 
                               GROUP BY developer ORDER BY count DESC LIMIT 5");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
    } elseif ($userRole == 'manager' || $userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT developer, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? 
                               AND user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               GROUP BY developer ORDER BY count DESC LIMIT 5");
        $stmt->bind_param("ssi", $startDate, $endDate, $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT developer, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? AND user_id = ? 
                               GROUP BY developer ORDER BY count DESC LIMIT 5");
        $stmt->bind_param("ssi", $startDate, $endDate, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $data['top_projects'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['top_projects'][] = $row;
    }
    
    // Get top models
    if ($userRole == 'admin') {
        $stmt = $conn->prepare("SELECT project_model, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? 
                               GROUP BY project_model ORDER BY count DESC LIMIT 5");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
    } elseif ($userRole == 'manager' || $userRole == 'supervisor') {
        $stmt = $conn->prepare("SELECT project_model, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? 
                               AND user_id IN (SELECT id FROM users WHERE team_id = ?) 
                               GROUP BY project_model ORDER BY count DESC LIMIT 5");
        $stmt->bind_param("ssi", $startDate, $endDate, $teamId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT project_model, COUNT(*) as count FROM leads 
                               WHERE created_at BETWEEN ? AND ? AND user_id = ? 
                               GROUP BY project_model ORDER BY count DESC LIMIT 5");
        $stmt->bind_param("ssi", $startDate, $endDate, $userId);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $data['top_models'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['top_models'][] = $row;
    }
    
    // Get team performance if manager
    if ($userRole == 'manager') {
        $stmt = $conn->prepare("SELECT u.id, u.name, 
                               (SELECT COUNT(*) FROM leads WHERE user_id = u.id AND created_at BETWEEN ? AND ?) as total_leads,
                               (SELECT COUNT(*) FROM leads WHERE user_id = u.id AND status = 'Presentation Stage' AND created_at BETWEEN ? AND ?) as presentations,
                               (SELECT COUNT(*) FROM leads WHERE user_id = u.id AND status = 'Closed' AND created_at BETWEEN ? AND ?) as closed_deals
                               FROM users u WHERE u.team_id = ?");
        $stmt->bind_param("ssssssi", $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $teamId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $data['team_performance'] = [];
        while ($row = $result->fetch_assoc()) {
            // Calculate conversion rate for each team member
            $row['conversion_rate'] = ($row['total_leads'] > 0) ? round(($row['closed_deals'] / $row['total_leads']) * 100, 1) : 0;
            $data['team_performance'][] = $row;
        }
    }
    
    $stmt->close();
    $conn->close();
    return $data;
}

// Helper functions
function getDevelopers() {
    $conn = getDbConnection();
    $developers = [];
    try {
        $result = $conn->query("SELECT id, name FROM developers ORDER BY name");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $developers[] = $row;
            }
            $result->free();
        }
    } catch (Exception $e) {
        // Table may not exist in some environments; return empty list instead of fatal error
        error_log("getDevelopers error: " . $e->getMessage());
    }
    $conn->close();
    return $developers;
}

function getProjectModels() {
    $conn = getDbConnection();
    $models = [];
    try {
        $query = "SELECT pm.id, pm.name, pm.description, pm.base_price, d.name as developer_name 
                  FROM project_models pm 
                  JOIN developers d ON pm.developer_id = d.id 
                  WHERE pm.is_active = 1 
                  ORDER BY d.name, pm.name";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $models[] = $row;
            }
            $result->free();
        }
    } catch (Exception $e) {
        error_log("getProjectModels error: " . $e->getMessage());
    }
    $conn->close();
    return $models;
}

function getTeamMembers($managerId) {
    $conn = getDbConnection();
    $members = [];
    
    // Get manager's team ID
    $stmt = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return $members; // Return empty array if manager not found
    }
    
    $manager = $result->fetch_assoc();
    if (!$manager['team_id']) {
        $stmt->close();
        $conn->close();
        return $members; // Return empty array if no team_id
    }
    
    // Get team members
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE team_id = ? ORDER BY name");
    $stmt->bind_param("i", $manager['team_id']);
    $stmt->execute();
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $members;
}

// Notification functions
function getNotifications($userId, $limit = 5, $onlyUnread = false) {
    $conn = getDbConnection();
    $notifications = [];
    
    $query = "SELECT * FROM notifications WHERE user_id = ? ";
    if ($onlyUnread) {
        $query .= "AND is_read = 0 ";
    }
    $query .= "ORDER BY created_at DESC ";
    
    if ($limit > 0) {
        $query .= "LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $limit);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $notifications;
}

function getUnreadNotificationsCount($userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    return $row['count'];
}

function markNotificationAsRead($notificationId, $userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $userId);
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    return $result;
}

function markAllNotificationsAsRead($userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    return $result;
}

function createNotification($userId, $title, $message, $type, $relatedId = null, $relatedType = null) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, related_id, related_type) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $userId, $title, $message, $type, $relatedId, $relatedType);
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    return $result;
}

function getPaginatedLeads($userId, $userRole, $offset, $limit) {
    $conn = getDbConnection();
    $leads = [];
    
    // Base query
    $query = "SELECT l.*, u.name as created_by_name 
              FROM leads l 
              JOIN users u ON l.user_id = u.id";
              
    // Add role-based conditions
    if ($userRole == 'agent') {
        $query .= " WHERE l.user_id = ?";
        $query .= " ORDER BY l.updated_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $userId, $offset, $limit);
    } elseif ($userRole == 'supervisor' || $userRole == 'manager') {
        // Get team members
        $teamQuery = "SELECT id FROM users WHERE team_id = (SELECT team_id FROM users WHERE id = ?)";
        $teamStmt = $conn->prepare($teamQuery);
        $teamStmt->bind_param("i", $userId);
        $teamStmt->execute();
        $teamResult = $teamStmt->get_result();
        
        $teamIds = [];
        while ($row = $teamResult->fetch_assoc()) {
            $teamIds[] = $row['id'];
        }
        $teamStmt->close();
        
        if (!empty($teamIds)) {
            $placeholders = str_repeat('?,', count($teamIds) - 1) . '?';
            $query .= " WHERE l.user_id IN ($placeholders)";
            $query .= " ORDER BY l.updated_at DESC LIMIT ?, ?";
            
            // Create references array for bind_param
            $params = array_merge($teamIds, [$offset, $limit]);
            $types = str_repeat('i', count($params));
            $stmt = $conn->prepare($query);
            
            // Create array of references
            $bindParams = array();
            $bindParams[] = $types;
            foreach($params as $key => $value) {
                $bindParams[] = &$params[$key];
            }
            
            call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        }
    } else {
        // Admin can see all leads
        $query .= " ORDER BY l.updated_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $offset, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $leads;
}

function updateLeadStatus($conn, $lead_id, $new_status, $user_id) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get user role and team_id
        $user_stmt = $conn->prepare("SELECT role, team_id FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        $user_stmt->close();
        
        // Check if user has permission to update this lead
        $permission_check = false;
        if ($user['role'] == 'admin') {
            $permission_check = true;
        } elseif ($user['role'] == 'manager' || $user['role'] == 'supervisor') {
            // Check if lead belongs to user's team
            $check_stmt = $conn->prepare("SELECT l.id FROM leads l 
                                        JOIN users u ON l.user_id = u.id 
                                        WHERE l.id = ? AND u.team_id = ?");
            $check_stmt->bind_param("ii", $lead_id, $user['team_id']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $permission_check = ($check_result->num_rows > 0);
            $check_stmt->close();
        } else {
            // Agent can only update their own leads
            $check_stmt = $conn->prepare("SELECT id FROM leads WHERE id = ? AND user_id = ?");
            $check_stmt->bind_param("ii", $lead_id, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $permission_check = ($check_result->num_rows > 0);
            $check_stmt->close();
        }
        
        if (!$permission_check) {
            throw new Exception("User does not have permission to update this lead");
        }
        
        // Get current lead status
        $stmt = $conn->prepare("SELECT status FROM leads WHERE id = ?");
        $stmt->bind_param("i", $lead_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $lead = $result->fetch_assoc();
        $old_status = $lead['status'];
        $stmt->close();
        
        // Update lead status
        $update_stmt = $conn->prepare("UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $lead_id);
        $result = $update_stmt->execute();
        $update_stmt->close();
        
        // Add activity log
        $activity_note = "Status changed from {$old_status} to {$new_status}";
        addLeadActivity($lead_id, $user_id, "Status Change", $activity_note);
        
        // Award raffle ticket for status change
        awardRaffleTicketsForStatusChange($lead_id, $user_id, $new_status, $old_status);
        
        // Handle Downpayment Stage
        if ($new_status == 'Downpayment Stage') {
            $check_stmt = $conn->prepare("SELECT id FROM downpayment_tracker WHERE lead_id = ?");
            $check_stmt->bind_param("i", $lead_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $tracker = $check_result->fetch_assoc();
            $check_stmt->close();
            
            if (!$tracker) {
                $insert_stmt = $conn->prepare("INSERT INTO downpayment_tracker 
                                (lead_id, dp_terms, current_dp_stage, total_dp_stages) 
                                VALUES (?, '12', 1, 12)");
                $insert_stmt->bind_param("i", $lead_id);
                $insert_stmt->execute();
                $insert_stmt->close();
                
                addLeadActivity($lead_id, $user_id, "Downpayment Tracker", "Downpayment tracker created automatically");
            }
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error updating lead status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user by ID
 * 
 * @param int $user_id The user ID
 * @return array|null User data or null if not found
 */


function getDeveloperNameById($developer_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT name FROM developers WHERE id = ?");
    $stmt->bind_param("i", $developer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['name'];
    }
    
    return '';
}


if (!function_exists('getUniqueSources')) {
    function getUniqueSources() {
        $conn = getDbConnection();
        $sources = array();

        $query = "SELECT DISTINCT source FROM leads WHERE source IS NOT NULL AND source != '' ORDER BY source";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $sources[] = $row['source'];
            }
        }

        // If no sources found in database, return default enum values
        if (empty($sources)) {
            $sources = [
                'Facebook Groups', 'KKK', 'Facebook Ads', 'TikTok ads', 'Google Ads', 
                'Facebook live', 'Referral', 'Teleprospecting', 'Video Message',
                'Organic Posting', 'Email Marketing', 'Follow up', 'Manning',
                'Walk in', 'Flyering', 'Chat messaging', 'Property Listing',
                'Landing Page', 'Networking Events', 'Organic Sharing',
                'Youtube Marketing', 'LinkedIn', 'Open House'
            ];
        }

        return $sources;
    }
}

// Add these functions if they don't already exist in your functions.php file

if (!function_exists('getDevelopers')) {
    function getDevelopers() {
        $conn = getDbConnection();
        $developers = [];
        
        $query = "SELECT id, name, description FROM developers WHERE is_active = 1 ORDER BY name";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $developers[] = $row;
            }
        }
        
        return $developers;
    }
}

if (!function_exists('getProjectModels')) {
    function getProjectModels() {
        $conn = getDbConnection();
        $models = [];
        try {
            $query = "SELECT pm.id, pm.name, pm.description, pm.base_price, d.name as developer_name 
                      FROM project_models pm 
                      JOIN developers d ON pm.developer_id = d.id 
                      WHERE pm.is_active = 1 
                      ORDER BY d.name, pm.name";
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $models[] = $row;
                }
                $result->free();
            }
        } catch (Exception $e) {
            error_log("getProjectModels error: " . $e->getMessage());
        }
        $conn->close();
        return $models;
    }
}

if (!function_exists('getLeadSources')) {
    function getLeadSources() {
        // Get all possible values from the source ENUM
        $conn = getDbConnection();
        $sources = [];
        
        // Get ENUM values directly from the column (cannot be prepared on some hosts)
        $result = $conn->query("SHOW COLUMNS FROM leads WHERE Field = 'source'");
        if ($result) {
            $row = $result->fetch_assoc();
            // Parse ENUM definition e.g. enum('A','B')
            if ($row && preg_match("/^enum\\('(.*)'\\)$/", $row['Type'], $matches)) {
                $values = explode("','", $matches[1]);
                foreach ($values as $value) {
                    $sources[] = [
                        'id'   => $value,
                        'name' => $value
                    ];
                }
            }
            $result->free();
        }
        
        // If no sources found from database, provide default values based on the schema
        if (empty($sources)) {
            $defaultSources = [
                'Facebook Groups', 'KKK', 'Facebook Ads', 'TikTok ads', 'Google Ads', 
                'Facebook live', 'Referral', 'Teleprospecting', 'Video Message', 
                'Organic Posting', 'Email Marketing', 'Follow up', 'Manning', 
                'Walk in', 'Flyering', 'Chat messaging', 'Property Listing', 
                'Landing Page', 'Networking Events', 'Organic Sharing', 
                'Youtube Marketing', 'LinkedIn', 'Open House'
            ];
            
            foreach ($defaultSources as $source) {
                $sources[] = [
                    'id' => $source,
                    'name' => $source
                ];
            }
        }
        
        $conn->close();
        return $sources;
    }
}

// Find and replace the existing getUniqueTemperatures function with this:

if (!function_exists('getUniqueTemperatures')) {
    function getUniqueTemperatures() {
        $conn = getDbConnection();
        $temperatures = array();

        $query = "SELECT DISTINCT temperature FROM leads WHERE temperature IS NOT NULL AND temperature != '' ORDER BY
                  CASE temperature 
                    WHEN 'Hot' THEN 1 
                    WHEN 'Warm' THEN 2 
                    WHEN 'Cold' THEN 3 
                  END";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $temperatures[] = $row['temperature'];
            }
        }

        // If no temperatures found in database, return default enum values
        if (empty($temperatures)) {
            $temperatures = ['Hot', 'Warm', 'Cold'];
        }

        return $temperatures;
    }
}

// Find and replace the existing getUniqueStatuses function with this:

if (!function_exists('getUniqueStatuses')) {
    function getUniqueStatuses() {
        $conn = getDbConnection();
        $statuses = array();

        $query = "SELECT DISTINCT status FROM leads WHERE status IS NOT NULL AND status != '' ORDER BY status";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $statuses[] = $row['status'];
            }
        }

        // If no statuses found in database, return default enum values
        if (empty($statuses)) {
            $statuses = [
            'Inquiry', 'Presentation Stage', 'Negotiation', 'Closed', 
            'Site Tour', 'Closed Deal', 'Requirement Stage', 'Downpayment Stage',
            'Housing Loan Application', 'Loan Approval', 'Loan Takeout',
            'House Inspection', 'House Turn Over'
            ];
        }

        return $statuses;
    }
}

function getRecruitmentStats($user_id, $user_role) {
    $stats = [
        'total_recruited' => 0,
        'active_agents' => 0,
        'inactive_agents' => 0,
        'hot_prospects' => 0,
        'in_training' => 0,
        'onboarded_agents' => 0,
        'pending_onboard' => 0
    ];
    
    try {
        $conn = getDbConnection();
        if (!$conn) {
            error_log("Database connection failed in getRecruitmentStats");
            return $stats;
        }
        
        // Base query conditions based on user role
        $where_clause = "";
        $params = [];
        $param_types = "";
        
        if ($user_role === 'manager') {
            // Manager sees their team's recruitment data
            $user = getUserById($user_id);
            if (!empty($user['team_id'])) {
                $where_clause = " WHERE rl.recruiter_team_id = ?";
                $params[] = $user['team_id'];
                $param_types = "i";
            }
        } elseif ($user_role !== 'admin') {
            // Regular users see only their own data
            $where_clause = " WHERE rl.recruiter_id = ?";
            $params[] = $user_id;
            $param_types = "i";
        }
        // Admin sees all data (no WHERE clause)
        
        // Total recruited leads
        $query = "SELECT COUNT(*) as total FROM recruitment_leads rl" . $where_clause;
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['total_recruited'] = (int)$row['total'];
        }
        $stmt->close();
        
        // Active agents (onboarding_status = 1)
        $query = "SELECT COUNT(*) as count FROM recruitment_leads rl" . $where_clause . 
                 ($where_clause ? " AND" : " WHERE") . " rl.onboarding_status = 1";
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['active_agents'] = (int)$row['count'];
            $stats['onboarded_agents'] = (int)$row['count'];
        }
        $stmt->close();
        
        // Inactive agents (onboarding_status = 0 or NULL)
        $query = "SELECT COUNT(*) as count FROM recruitment_leads rl" . $where_clause . 
                 ($where_clause ? " AND" : " WHERE") . " (rl.onboarding_status = 0 OR rl.onboarding_status IS NULL)";
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['inactive_agents'] = (int)$row['count'];
            $stats['pending_onboard'] = (int)$row['count'];
        }
        $stmt->close();
        
        // Hot prospects
        $query = "SELECT COUNT(*) as count FROM recruitment_leads rl" . $where_clause . 
                 ($where_clause ? " AND" : " WHERE") . " rl.status = 'Hot'";
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['hot_prospects'] = (int)$row['count'];
        }
        $stmt->close();
        
        // In training
        $query = "SELECT COUNT(*) as count FROM recruitment_leads rl" . $where_clause . 
                 ($where_clause ? " AND" : " WHERE") . " rl.status LIKE '%training%'";
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['in_training'] = (int)$row['count'];
        }
        $stmt->close();
        
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Error fetching recruitment stats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Get recruitment leads
 */
function getRecruitmentLeads($filters = [], $sort_by = 'created_at', $sort_order = 'DESC', $limit = null, $offset = 0) {
    global $pdo;
    
    if (!isset($pdo)) {
        return [
            'success' => false, 
            'message' => 'Database connection not available'
        ];
    }
    
    try {
        // Base query
        $sql = "SELECT * FROM recruitment_leads WHERE 1=1";
        $params = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['interest_level'])) {
            $sql .= " AND interest_level = :interest_level";
            $params[':interest_level'] = $filters['interest_level'];
        }
        
        if (!empty($filters['source'])) {
            $sql .= " AND source = :source";
            $params[':source'] = $filters['source'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (full_name LIKE :search OR email LIKE :search OR contact_number LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        // Add sorting
        $allowed_sort_columns = ['id', 'full_name', 'contact_number', 'email', 'recruiter_name', 'interest_level', 'status', 'source', 'created_at'];
        if (in_array($sort_by, $allowed_sort_columns)) {
            $sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY {$sort_by} {$sort_order}";
        }
        
        // Add limit and offset
        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $pdo->prepare($sql);
        
        // Bind limit and offset separately as they need to be integers
        if ($limit !== null) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        }
        
        // Bind other parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $leads,
            'total' => count($leads)
        ];
        
    } catch (PDOException $e) {
        error_log("Database error in getRecruitmentLeads: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

function deleteRecruitmentLead($id) {
    global $pdo;
    
    if (!isset($pdo)) {
        return [
            'success' => false, 
            'message' => 'Database connection not available'
        ];
    }
    
    try {
        // Validate ID
        if (empty($id) || !is_numeric($id)) {
            return [
                'success' => false, 
                'message' => 'Invalid ID provided'
            ];
        }
        
        // Check if record exists
        $checkSql = "SELECT id FROM recruitment_leads WHERE id = :id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':id' => $id]);
        
        if (!$checkStmt->fetch()) {
            return [
                'success' => false, 
                'message' => 'Recruitment lead not found'
            ];
        }
        
        // Delete the record
        $sql = "DELETE FROM recruitment_leads WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([':id' => $id]);
        
        if ($result) {
            return [
                'success' => true, 
                'message' => 'Recruitment lead deleted successfully'
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Failed to delete recruitment lead'
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Database error in deleteRecruitmentLead: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate a unique raffle ticket number
 * 
 * @return string Unique ticket number
 */
function generateRaffleTicketNumber() {
    $conn = getDbConnection();
    
    // Get the next sequential number
    $result = $conn->query("SELECT COUNT(*) as count FROM raffle_tickets");
    $count = $result->fetch_assoc()['count'];
    
    // Generate LMS + sequential number (padded to 3 digits)
    $ticket_number = 'LMS' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    
    return $ticket_number;
}

/**
 * Award raffle tickets for lead status progression
 * 
 * @param int $lead_id Lead ID
 * @param int $user_id User ID
 * @param string $new_status New lead status
 * @param string $old_status Old lead status
 * @return bool Success status
 */
function awardRaffleTicketsForStatusChange($lead_id, $user_id, $new_status, $old_status) {
    $conn = getDbConnection();
    if (!$conn) {
        error_log("Database connection failed in awardRaffleTicketsForStatusChange");
        return false;
    }

    try {
        // Define the stages that award tickets
        $raffle_stages = [
            'Inquiry',
            'Presentation Stage', 
            'Negotiation',
            'Closed',
            'Lost',
            'Site Tour',
            'Closed Deal',
            'Requirement Stage',
            'Downpayment Stage',
            'Housing Loan Application',
            'Loan Approval',
            'Loan Takeout',
            'House Inspection',
            'House Turn Over'
        ];

        // Only award ticket if the new status is in our raffle stages
        if (!in_array($new_status, $raffle_stages)) {
            return true; // Not an error, just no ticket to award
        }

        // Get lead and user information
        $lead_stmt = $conn->prepare("SELECT l.client_name, l.phone, l.email, u.name as full_name, u.team_id 
                                   FROM leads l 
                                   JOIN users u ON l.user_id = u.id 
                                   WHERE l.id = ?");
        $lead_stmt->bind_param("i", $lead_id);
        $lead_stmt->execute();
        $lead_data = $lead_stmt->get_result()->fetch_assoc();
        $lead_stmt->close();

        if (!$lead_data) {
            error_log("Lead not found for raffle ticket generation: " . $lead_id);
            return false;
        }

        // Check if a ticket already exists for this lead and status to prevent duplicates
        $stage_source = "Lead Status: " . $new_status;
        $check_stmt = $conn->prepare("SELECT id FROM raffle_tickets 
                                     WHERE lead_id = ? AND stage_source = ? 
                                     LIMIT 1");
        $check_stmt->bind_param("is", $lead_id, $stage_source);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Ticket already exists for this status change, skip creating duplicate
            $check_stmt->close();
            error_log("Raffle ticket already exists for Lead ID $lead_id, Status: $new_status - skipping duplicate");
            return true;
        }
        $check_stmt->close();

        // Generate and insert raffle ticket
        $ticket_number = generateRaffleTicketNumber();
        
        $insert_stmt = $conn->prepare("INSERT INTO raffle_tickets 
                                     (user_id, lead_id, ticket_number, full_name, phone_number, email_address, team_id, stage_source) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("iissssis", 
            $user_id, 
            $lead_id, 
            $ticket_number, 
            $lead_data['full_name'], 
            $lead_data['phone'], 
            $lead_data['email'], 
            $lead_data['team_id'], 
            $stage_source
        );

        $result = $insert_stmt->execute();
        $insert_stmt->close();

        if ($result) {
            error_log("Raffle ticket awarded for lead status change: Lead ID $lead_id, Status: $new_status, Ticket: $ticket_number");
            return true;
        } else {
            error_log("Failed to award raffle ticket for lead status change: Lead ID $lead_id");
            return false;
        }

    } catch (Exception $e) {
        error_log("Error awarding raffle tickets for status change: " . $e->getMessage());
        return false;
    }
}

/**
 * Award raffle tickets for downpayment tracker stage updates
 * 
 * @param int $lead_id Lead ID
 * @param int $user_id User ID
 * @param int $current_stage Current DP stage
 * @return bool Success status
 */
function awardRaffleTicketsForDPStage($lead_id, $user_id, $current_stage) {
    $conn = getDbConnection();
    if (!$conn) {
        error_log("Database connection failed in awardRaffleTicketsForDPStage");
        return false;
    }

    try {
        // Get lead and user information
        $lead_stmt = $conn->prepare("SELECT l.client_name, l.phone, l.email, u.name as full_name, u.team_id 
                                   FROM leads l 
                                   JOIN users u ON l.user_id = u.id 
                                   WHERE l.id = ?");
        $lead_stmt->bind_param("i", $lead_id);
        $lead_stmt->execute();
        $lead_data = $lead_stmt->get_result()->fetch_assoc();
        $lead_stmt->close();

        if (!$lead_data) {
            error_log("Lead not found for DP stage raffle ticket generation: " . $lead_id);
            return false;
        }

        // Award tickets equal to the current stage number
        for ($i = 1; $i <= $current_stage; $i++) {
            $ticket_number = generateRaffleTicketNumber();
            $stage_source = "DP Stage: " . $i;
            
            $insert_stmt = $conn->prepare("INSERT INTO raffle_tickets 
                                         (user_id, lead_id, ticket_number, full_name, phone_number, email_address, team_id, stage_source) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("iissssis", 
                $user_id, 
                $lead_id, 
                $ticket_number, 
                $lead_data['full_name'], 
                $lead_data['phone'], 
                $lead_data['email'], 
                $lead_data['team_id'], 
                $stage_source
            );

            $result = $insert_stmt->execute();
            $insert_stmt->close();

            if (!$result) {
                error_log("Failed to award raffle ticket for DP stage $i: Lead ID $lead_id");
                return false;
            }
        }

        error_log("Raffle tickets awarded for DP stage update: Lead ID $lead_id, Stage: $current_stage");
        return true;

    } catch (Exception $e) {
        error_log("Error awarding raffle tickets for DP stage: " . $e->getMessage());
        return false;
    }
}

/**
 * Award raffle tickets for requirements completion
 * 
 * @param int $lead_id Lead ID
 * @param int $user_id User ID
 * @param array $requirements Array of requirement fields and their values
 * @return bool Success status
 */
function awardRaffleTicketsForRequirements($lead_id, $user_id, $requirements) {
    $conn = getDbConnection();
    if (!$conn) {
        error_log("Database connection failed in awardRaffleTicketsForRequirements");
        return false;
    }

    try {
        // Define requirement fields that award tickets
        $requirement_fields = [
            'requirements_complete' => 'Requirements Complete',
            'pagibig_bank_approval' => 'Pag-IBIG Bank Approval',
            'loan_takeout' => 'Loan Takeout',
            'turnover' => 'Turnover'
        ];

        // Get lead and user information
        $lead_stmt = $conn->prepare("SELECT l.client_name, l.phone, l.email, u.name as full_name, u.team_id 
                                   FROM leads l 
                                   JOIN users u ON l.user_id = u.id 
                                   WHERE l.id = ?");
        $lead_stmt->bind_param("i", $lead_id);
        $lead_stmt->execute();
        $lead_data = $lead_stmt->get_result()->fetch_assoc();
        $lead_stmt->close();

        if (!$lead_data) {
            error_log("Lead not found for requirements raffle ticket generation: " . $lead_id);
            return false;
        }

        $tickets_awarded = 0;

        // Check each requirement field
        foreach ($requirement_fields as $field => $description) {
            if (isset($requirements[$field]) && $requirements[$field] == 1) {
                $ticket_number = generateRaffleTicketNumber();
                $stage_source = "Requirement: " . $description;
                
                $insert_stmt = $conn->prepare("INSERT INTO raffle_tickets 
                                             (user_id, lead_id, ticket_number, full_name, phone_number, email_address, team_id, stage_source) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("iissssis", 
                    $user_id, 
                    $lead_id, 
                    $ticket_number, 
                    $lead_data['full_name'], 
                    $lead_data['phone'], 
                    $lead_data['email'], 
                    $lead_data['team_id'], 
                    $stage_source
                );

                $result = $insert_stmt->execute();
                $insert_stmt->close();

                if ($result) {
                    $tickets_awarded++;
                    error_log("Raffle ticket awarded for requirement: $description, Lead ID: $lead_id");
                } else {
                    error_log("Failed to award raffle ticket for requirement $description: Lead ID $lead_id");
                }
            }
        }

        error_log("Requirements raffle tickets awarded: $tickets_awarded tickets for Lead ID $lead_id");
        return true;

    } catch (Exception $e) {
        error_log("Error awarding raffle tickets for requirements: " . $e->getMessage());
        return false;
    }
}

/**
 * Award raffle tickets for spot downpayment based on DP terms
 * 
 * @param int $lead_id Lead ID
 * @param int $user_id User ID
 * @param int $dp_terms DP terms in months
 * @return bool Success status
 */
function awardRaffleTicketsForSpotDP($lead_id, $user_id, $dp_terms) {
    $conn = getDbConnection();
    if (!$conn) {
        error_log("Database connection failed in awardRaffleTicketsForSpotDP");
        return false;
    }

    try {
        // Get lead and user information
        $lead_stmt = $conn->prepare("SELECT l.client_name, l.phone, l.email, u.name as full_name, u.team_id 
                                   FROM leads l 
                                   JOIN users u ON l.user_id = u.id 
                                   WHERE l.id = ?");
        $lead_stmt->bind_param("i", $lead_id);
        $lead_stmt->execute();
        $lead_data = $lead_stmt->get_result()->fetch_assoc();
        $lead_stmt->close();

        if (!$lead_data) {
            error_log("Lead not found for spot DP raffle ticket generation: " . $lead_id);
            return false;
        }

        // Award tickets equal to DP terms
        for ($i = 1; $i <= $dp_terms; $i++) {
            $ticket_number = generateRaffleTicketNumber();
            $stage_source = "Spot DP: " . $i . "/" . $dp_terms . " months";
            
            $insert_stmt = $conn->prepare("INSERT INTO raffle_tickets 
                                         (user_id, lead_id, ticket_number, full_name, phone_number, email_address, team_id, stage_source) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("iissssis", 
                $user_id, 
                $lead_id, 
                $ticket_number, 
                $lead_data['full_name'], 
                $lead_data['phone'], 
                $lead_data['email'], 
                $lead_data['team_id'], 
                $stage_source
            );

            $result = $insert_stmt->execute();
            $insert_stmt->close();

            if (!$result) {
                error_log("Failed to award raffle ticket for spot DP $i: Lead ID $lead_id");
                return false;
            }
        }

        error_log("Raffle tickets awarded for spot DP: Lead ID $lead_id, Terms: $dp_terms months");
        return true;

    } catch (Exception $e) {
        error_log("Error awarding raffle tickets for spot DP: " . $e->getMessage());
        return false;
    }
}

/**
 * Get raffle tickets with filtering options - Gets data from leads table (current status)
 * 
 * @param array $filters Filter options
 * @param int $limit Limit results
 * @param int $offset Offset for pagination
 * @return array Raffle tickets data
 */
function getRaffleTickets($filters = [], $limit = null, $offset = 0) {
    $conn = getDbConnection();
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $where_conditions = [];
        $params = [];
        $types = '';

        // Define raffle-eligible statuses
        $raffle_stages = [
            'Inquiry', 'Presentation Stage', 'Negotiation', 'Closed', 'Lost',
            'Site Tour', 'Closed Deal', 'Requirement Stage', 'Downpayment Stage',
            'Housing Loan Application', 'Loan Approval', 'Loan Takeout',
            'House Inspection', 'House Turn Over'
        ];
        $raffle_stages_str = "'" . implode("','", $raffle_stages) . "'";
        
        // Base condition - only raffle-eligible statuses
        $where_conditions[] = "l.status IN ($raffle_stages_str)";

        // Build additional WHERE conditions based on filters
        if (!empty($filters['team_id'])) {
            $where_conditions[] = "u.team_id = ?";
            $params[] = $filters['team_id'];
            $types .= 'i';
        }

        if (!empty($filters['user_id'])) {
            $where_conditions[] = "l.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }

        if (!empty($filters['stage_source'])) {
            $where_conditions[] = "l.status LIKE ?";
            $params[] = '%' . $filters['stage_source'] . '%';
            $types .= 's';
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(l.updated_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(l.updated_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        // Simple query - get leads with raffle-eligible statuses, grouped by user
        // Include team information for transparency
        $query = "SELECT l.user_id, 
                         u.name as full_name, 
                         u.phone as phone_number, 
                         u.email as email_address, 
                         u.team_id,
                         t.name as team_name,
                         u.role as user_role,
                         COUNT(*) as ticket_count,
                         GROUP_CONCAT(CONCAT('LMS', LPAD(l.id, 3, '0')) ORDER BY l.updated_at DESC) as ticket_numbers,
                         GROUP_CONCAT(CONCAT('Lead Status: ', l.status) ORDER BY l.updated_at DESC) as stage_sources,
                         GROUP_CONCAT(l.updated_at ORDER BY l.updated_at DESC) as created_dates,
                         GROUP_CONCAT(l.updated_at ORDER BY l.updated_at DESC) as modification_dates,
                         MIN(l.updated_at) as first_ticket_date,
                         MAX(l.updated_at) as latest_ticket_date
                 FROM leads l
                 LEFT JOIN users u ON l.user_id = u.id
                 LEFT JOIN teams t ON u.team_id = t.id
                 $where_clause 
                 GROUP BY l.user_id
                 ORDER BY t.name ASC, latest_ticket_date DESC";

        if ($limit) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }

        $stmt = $conn->prepare($query);
        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tickets = [];
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
        $stmt->close();

        // Get total count for pagination
        $count_query = "SELECT COUNT(DISTINCT l.user_id) as total 
                       FROM leads l
                       LEFT JOIN users u ON l.user_id = u.id
                       $where_clause";
        $count_stmt = $conn->prepare($count_query);
        
        // Prepare count parameters (exclude limit/offset)
        $count_params = [];
        $count_types = '';
        if (!empty($params)) {
            $count_params = array_slice($params, 0, -2);
            $count_types = substr($types, 0, -2);
        }
        
        if (!empty($count_params) && !empty($count_types)) {
            $count_stmt->bind_param($count_types, ...$count_params);
        }
        
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();

        return [
            'success' => true,
            'tickets' => $tickets,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];

    } catch (Exception $e) {
        error_log("Error getting raffle tickets: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to retrieve raffle tickets: ' . $e->getMessage()];
    }
}

/**
 * Get detailed raffle tickets for a specific user - Gets data from leads table
 * 
 * @param int $user_id User ID
 * @return array Detailed tickets data
 */
function getUserRaffleTickets($user_id) {
    $conn = getDbConnection();
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Define raffle-eligible statuses
        $raffle_stages = [
            'Inquiry', 'Presentation Stage', 'Negotiation', 'Closed', 'Lost',
            'Site Tour', 'Closed Deal', 'Requirement Stage', 'Downpayment Stage',
            'Housing Loan Application', 'Loan Approval', 'Loan Takeout',
            'House Inspection', 'House Turn Over'
        ];
        $raffle_stages_str = "'" . implode("','", $raffle_stages) . "'";

        $query = "SELECT l.id,
                         l.id as lead_id,
                         l.user_id,
                         CONCAT('LMS', LPAD(l.id, 3, '0')) as ticket_number,
                         u.name as full_name,
                         l.client_name,
                         l.phone as phone_number,
                         l.email as email_address,
                         u.team_id,
                         CONCAT('Lead Status: ', l.status) as stage_source,
                         l.updated_at as created_at,
                         l.updated_at as modification_date,
                         l.status as lead_status,
                         l.updated_at as lead_updated_at
                  FROM leads l
                  LEFT JOIN users u ON l.user_id = u.id
                  WHERE l.user_id = ? 
                    AND l.status IN ($raffle_stages_str)
                  ORDER BY l.updated_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tickets = [];
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
        $stmt->close();

        return [
            'success' => true,
            'tickets' => $tickets,
            'total' => count($tickets)
        ];

    } catch (Exception $e) {
        error_log("Error getting user raffle tickets: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to retrieve user raffle tickets: ' . $e->getMessage()];
    }
}

/**
 * Get raffle tickets by set (A, B, or C) for the wheel spin
 * 
 * @param string $set The set to get tickets for (A, B, or C)
 * @param array $filters Additional filters
 * @return array Tickets data
 */
function getTicketsBySet($set, $filters = []) {
    $conn = getDbConnection();
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Define ticket count thresholds for each set
        $thresholds = [
            'A' => 10,  // 10+ tickets
            'B' => 20,  // 20+ tickets
            'C' => 50   // 50+ tickets
        ];

        if (!isset($thresholds[$set])) {
            return ['success' => false, 'message' => 'Invalid set specified'];
        }

        $threshold = $thresholds[$set];
        $params = [];
        $types = '';
        $where_conditions = ["l.status IN ('Closed Deal', 'Closed', 'Loan Takeout', 'House Turn Over')"];

        // Base query to get users with their ticket counts
        $query = "SELECT 
                    u.id as user_id,
                    u.name,
                    u.team_id,
                    t.name as team_name,
                    COUNT(DISTINCT l.id) as ticket_count
                  FROM users u
                  LEFT JOIN leads l ON u.id = l.user_id
                  LEFT JOIN teams t ON u.team_id = t.id
                  WHERE " . implode(' AND ', $where_conditions);

        // Add team filter if specified
        if (!empty($filters['team_id'])) {
            $query .= " AND u.team_id = ?";
            $params[] = $filters['team_id'];
            $types .= 'i';
        }

        // Add user filter if specified
        if (!empty($filters['user_id'])) {
            $query .= " AND u.id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }

               // Add date range filter if specified
        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(l.updated_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(l.updated_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        // Group by user and apply ticket count threshold
        $query .= " GROUP BY u.id, u.name, u.team_id, t.name
                    HAVING ticket_count >= ?
                    ORDER BY ticket_count DESC";
        
        $params[] = $threshold;
        $types .= 'i';

        $stmt = $conn->prepare($query);
        
        // Bind parameters if any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tickets = [];
        while ($row = $result->fetch_assoc()) {
            $tickets[] = [
                'id' => $row['user_id'],
                'name' => $row['name'],
                'team_id' => $row['team_id'],
                'team_name' => $row['team_name'],
                'ticket_count' => (int)$row['ticket_count']
            ];
        }
        $stmt->close();

        return [
            'success' => true,
            'tickets' => $tickets,
            'total' => count($tickets)
        ];

    } catch (Exception $e) {
        error_log("Error getting tickets by set: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to retrieve tickets: ' . $e->getMessage()];
    }
}


// AJAX Handler - This is crucial for the dashboard to work
if (isset($_POST['action'])) {
    // Enable error logging for AJAX requests
    error_log("AJAX request received: " . $_POST['action']);
    error_log("POST data: " . print_r($_POST, true));
    
    // Include database connection for AJAX requests
    if (!function_exists('getDbConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_recruitment_lead':
                $result = addRecruitmentLead($_POST);
                echo json_encode($result);
                exit;
                
            case 'delete_recruitment_lead':
                $result = deleteRecruitmentLead($_POST['id'] ?? null);
                echo json_encode($result);
                exit;
                
            case 'get_recruitment_leads':
                $filters = isset($_POST['filters']) ? json_decode($_POST['filters'], true) : [];
                $sort_by = $_POST['sort_by'] ?? 'created_at';
                $sort_order = $_POST['sort_order'] ?? 'DESC';
                $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : null;
                $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
                
                $result = getRecruitmentLeads($filters, $sort_by, $sort_order, $limit, $offset);
                echo json_encode($result);
                exit;
                
            case 'get_recruitment_stats':
                $result = getRecruitmentStats();
                echo json_encode($result);
                exit;
                
            case 'get_raffle_tickets':
                $filters = [
                    'team_id' => $_POST['team_id'] ?? null,
                    'user_id' => $_POST['user_id'] ?? null,
                    'stage_source' => $_POST['stage_source'] ?? null,
                    'date_from' => $_POST['date_from'] ?? null,
                    'date_to' => $_POST['date_to'] ?? null
                ];
                
                $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
                $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
                
                $result = getRaffleTickets($filters, $limit, $offset);
                echo json_encode($result);
                exit;
                
            case 'get_teams':
                $conn = getDbConnection();
                $stmt = $conn->prepare("SELECT id, name FROM teams ORDER BY name");
                $stmt->execute();
                $result = $stmt->get_result();
                $teams = [];
                while ($row = $result->fetch_assoc()) {
                    $teams[] = $row;
                }
                $stmt->close();
                echo json_encode(['success' => true, 'teams' => $teams]);
                exit;
                
            case 'get_user_tickets':
                $user_id = $_POST['user_id'] ?? null;
                if (!$user_id) {
                    echo json_encode(['success' => false, 'message' => 'User ID is required']);
                    exit;
                }
                $result = getUserRaffleTickets($user_id);
                echo json_encode($result);
                exit;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $_POST['action']]);
                exit;
        }
    } catch (Exception $e) {
        error_log("AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}
    