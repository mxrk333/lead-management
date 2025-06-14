<?php
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

function addLead($userId, $clientName, $phone, $email, $facebook, $linkedin, 
                $temperature, $status, $source, $developer, $projectModel, $price, $remarks) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO leads (user_id, client_name, phone, email, facebook, linkedin, 
                           temperature, status, source, developer, project_model, price, remarks) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssssssds", $userId, $clientName, $phone, $email, $facebook, $linkedin, 
                     $temperature, $status, $source, $developer, $projectModel, $price, $remarks);
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    return $result;
}

function updateLead($leadId, $clientName, $phone, $email, $facebook, $linkedin, 
                   $temperature, $status, $source, $developer, $projectModel, $price, $remarks) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE leads SET client_name = ?, phone = ?, email = ?, 
                           facebook = ?, linkedin = ?, temperature = ?, status = ?, 
                           source = ?, developer = ?, project_model = ?, price = ?, remarks = ? 
                           WHERE id = ?");

    $stmt->bind_param("ssssssssssdsi", $clientName, $phone, $email, $facebook, $linkedin,
                     $temperature, $status, $source, $developer, $projectModel, $price, $remarks, $leadId);
    $result = $stmt->execute();
    
    $stmt->close();
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
    
    $stmt = $conn->prepare("SELECT * FROM developers ORDER BY name");
    $stmt->execute();
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $developers[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $developers;
}

function getProjectModels() {
    $conn = getDbConnection();
    $models = [];
    
    $stmt = $conn->prepare("SELECT pm.*, d.name as developer_name 
                           FROM project_models pm 
                           JOIN developers d ON pm.developer_id = d.id 
                           ORDER BY d.name, pm.name");
    $stmt->execute();
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $models[] = $row;
    }
    
    $stmt->close();
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

// Function to get unique sources from leads table
function getUniqueSources() {
    $conn = getDbConnection();
    $sources = array();
    
    $query = "SELECT DISTINCT source FROM leads ORDER BY source";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sources[] = $row['source'];
        }
    }
    
    // Don't close the connection here
    return $sources;
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
        }
        
        return $models;
    }
}

if (!function_exists('getLeadSources')) {
    function getLeadSources() {
        // Get all possible values from the source ENUM
        $conn = getDbConnection();
        $sources = [];
        
        // Get ENUM values directly from the column
        $stmt = $conn->prepare("SHOW COLUMNS FROM leads WHERE Field = 'source'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // Parse ENUM values from the type definition
        if ($row && preg_match("/^enum$$'(.*)'$$$/", $row['Type'], $matches)) {
            $values = explode("','", $matches[1]);
            foreach ($values as $value) {
                $sources[] = [
                    'id' => $value,
                    'name' => $value
                ];
            }
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
        
        $stmt->close();
        return $sources;
    }
}

// Find and replace the existing getUniqueTemperatures function with this:

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

// Find and replace the existing getUniqueStatuses function with this:

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
            'Inquiry', 'Presentation Stage', 'Negotiation', 'Closed', 'Lost', 
            'Site Tour', 'Closed Deal', 'Requirement Stage', 'Downpayment Stage', 
            'Housing Loan Application', 'Loan Approval', 'Loan Takeout', 
            'House Inspection', 'House Turn Over'
        ];
    }
    
    return $statuses;
}