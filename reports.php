<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get database connection
$conn = getDbConnection();
if (!$conn) {
    die("Database connection failed.");
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Check if user has permission to view reports
if ($user['role'] != 'manager' && $user['role'] != 'supervisor' && $user['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Get report parameters
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil(date('n') / 3);

// Add this code to handle month filter:
$month = isset($_GET['month']) ? intval($_GET['month']) : 0; // 0 means all months in the quarter

// Handle team member filter
$selected_team_member = isset($_GET['team_member']) && !empty($_GET['team_member']) ? $_GET['team_member'] : null;

// Modify the date range calculation to handle month filter
if ($month > 0) {
    // If a specific month is selected
    $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$year-$month-01"));
} else {
    // Calculate quarter date range (original code)
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3;
    $start_date = "$year-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = "$year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime("$year-$end_month-01"));
}

// Get team filter
$selected_team_id = null;
if ($user['role'] == 'admin') {
    // Admin can select any team
    $selected_team_id = isset($_GET['team_id']) ? $_GET['team_id'] : 'all';
} else {
    // Managers and supervisors can only see their team
    $selected_team_id = $user['team_id'];
}

// Get all teams for admin selection
$all_teams = [];
if ($user['role'] == 'admin') {
    $teams_query = "SELECT id, name FROM teams ORDER BY name ASC";
    $teams_result = $conn->query($teams_query);
    
    if ($teams_result && $teams_result->num_rows > 0) {
        while ($team = $teams_result->fetch_assoc()) {
            $all_teams[] = $team;
        }
    }
}

// FIX: Improved team members retrieval code
// Get team members based on user role and selected team
$teamMembers = [];

// For admin with "All Teams" selected
if ($user['role'] == 'admin' && $selected_team_id == 'all') {
    $all_members_query = "SELECT id, name FROM users WHERE role != 'admin' ORDER BY name ASC";
    $all_members_result = $conn->query($all_members_query);
    
    if ($all_members_result && $all_members_result->num_rows > 0) {
        while ($member = $all_members_result->fetch_assoc()) {
            $teamMembers[] = $member;
        }
    }
} 
// For admin with specific team selected or for managers/supervisors
else {
    $team_id_to_use = ($user['role'] == 'admin') ? $selected_team_id : $user['team_id'];
    
    $team_members_query = "SELECT id, name FROM users WHERE team_id = ? ORDER BY name ASC";
    $stmt = $conn->prepare($team_members_query);
    $stmt->bind_param("i", $team_id_to_use);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($member = $result->fetch_assoc()) {
            $teamMembers[] = $member;
        }
    }
    $stmt->close();
}

// Initialize report data structure
$reportData = [
    'total_leads' => 0,
    'presentations' => 0,
    'closed_deals' => 0,
    'conversion_rate' => 0,
    'status_distribution' => [],
    'temperature_distribution' => [],
    'top_projects' => [],
    'top_models' => [],
    'top_sources' => [],
    'team_performance' => []
];

// Get report data based on selected team and team member
if ($selected_team_id == 'all' && $user['role'] == 'admin') {
    // Get summary data for all teams
    if ($selected_team_member) {
        // Filter by selected team member
        $summary_query = "
            SELECT 
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
                COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
            FROM 
                leads l
            LEFT JOIN 
                lead_activities la ON la.lead_id = l.id
            WHERE 
                l.user_id = ? AND
                l.created_at BETWEEN ? AND ?
        ";
        
        $stmt = $conn->prepare($summary_query);
        $stmt->bind_param("iss", $selected_team_member, $start_date, $end_date);
    } else {
        // No team member filter
        $summary_query = "
            SELECT 
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
                COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
            FROM 
                leads l
            LEFT JOIN 
                lead_activities la ON la.lead_id = l.id
            WHERE 
                l.created_at BETWEEN ? AND ?
        ";
        
        $stmt = $conn->prepare($summary_query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $summary = $result->fetch_assoc();
        $reportData['total_leads'] = $summary['total_leads'];
        $reportData['presentations'] = $summary['presentations'];
        $reportData['closed_deals'] = $summary['closed_deals'];
        $reportData['total_value'] = $summary['total_value'];
        
        // Calculate conversion rate
        if ($reportData['total_leads'] > 0) {
            $reportData['conversion_rate'] = round(($reportData['closed_deals'] / $reportData['total_leads']) * 100, 1);
        }
    }
    
    // Get status distribution
    if ($selected_team_member) {
        $status_query = "
            SELECT 
                status,
                COUNT(id) as count,
                SUM(CASE WHEN status = 'Closed Deal' THEN price ELSE 0 END) as value
            FROM 
                leads
            WHERE 
                user_id = ? AND
                created_at BETWEEN ? AND ?
            GROUP BY 
                status
            ORDER BY 
                count DESC
        ";
        
        $stmt = $conn->prepare($status_query);
        $stmt->bind_param("iss", $selected_team_member, $start_date, $end_date);
    } else {
        $status_query = "
            SELECT 
                status,
                COUNT(id) as count,
                SUM(CASE WHEN status = 'Closed Deal' THEN price ELSE 0 END) as value
            FROM 
                leads
            WHERE 
                created_at BETWEEN ? AND ?
            GROUP BY 
                status
            ORDER BY 
                count DESC
        ";
        
        $stmt = $conn->prepare($status_query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['status_distribution'][] = $row;
        }
    }
    
    // Get temperature distribution
    if ($selected_team_member) {
        $temp_query = "
            SELECT 
                temperature,
                COUNT(id) as count,
                SUM(CASE WHEN status = 'Closed Deal' THEN price ELSE 0 END) as value
            FROM 
                leads
            WHERE 
                user_id = ? AND
                created_at BETWEEN ? AND ?
            GROUP BY 
                temperature
            ORDER BY 
                FIELD(temperature, 'Hot', 'Warm', 'Cold')
        ";
        
        $stmt = $conn->prepare($temp_query);
        $stmt->bind_param("iss", $selected_team_member, $start_date, $end_date);
    } else {
        $temp_query = "
            SELECT 
                temperature,
                COUNT(id) as count,
                SUM(CASE WHEN status = 'Closed Deal' THEN price ELSE 0 END) as value
            FROM 
                leads
            WHERE 
                created_at BETWEEN ? AND ?
            GROUP BY 
                temperature
            ORDER BY 
                FIELD(temperature, 'Hot', 'Warm', 'Cold')
        ";
        
        $stmt = $conn->prepare($temp_query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['temperature_distribution'][] = $row;
        }
    }
    
    // Get top projects
    if ($selected_team_member) {
        $projects_query = "
            SELECT 
                developer,
                COUNT(id) as count
            FROM 
                leads
            WHERE 
                user_id = ? AND
                created_at BETWEEN ? AND ?
            GROUP BY 
                developer
            ORDER BY 
                count DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($projects_query);
        $stmt->bind_param("iss", $selected_team_member, $start_date, $end_date);
    } else {
        $projects_query = "
            SELECT 
                developer,
                COUNT(id) as count
            FROM 
                leads
            WHERE 
                created_at BETWEEN ? AND ?
            GROUP BY 
                developer
            ORDER BY 
                count DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($projects_query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['top_projects'][] = $row;
        }
    }
    
    // Get top models
    if ($selected_team_member) {
        $models_query = "
            SELECT 
                project_model,
                COUNT(id) as count
            FROM 
                leads
            WHERE 
                user_id = ? AND
                created_at BETWEEN ? AND ?
            GROUP BY 
                project_model
            ORDER BY 
                count DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($models_query);
        $stmt->bind_param("iss", $selected_team_member, $start_date, $end_date);
    } else {
        $models_query = "
            SELECT 
                project_model,
                COUNT(id) as count
            FROM 
                leads
            WHERE 
                created_at BETWEEN ? AND ?
            GROUP BY 
                project_model
            ORDER BY 
                count DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($models_query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['top_models'][] = $row;
        }
    }
    
    // Get top sources
    if ($selected_team_member) {
        $sources_query = "
            SELECT 
                source,
                COUNT(id) as count
            FROM 
                leads
            WHERE 
                user_id = ? AND
                created_at BETWEEN ? AND ?
            GROUP BY 
                source
            ORDER BY 
                count DESC
            LIMIT 8
        ";
        
        $stmt = $conn->prepare($sources_query);
        $stmt->bind_param("iss", $selected_team_member, $start_date, $end_date);
    } else {
        $sources_query = "
            SELECT 
                source,
                COUNT(id) as count
            FROM 
                leads
            WHERE 
                created_at BETWEEN ? AND ?
            GROUP BY 
                source
            ORDER BY 
                count DESC
            LIMIT 8
        ";
        
        $stmt = $conn->prepare($sources_query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['top_sources'][] = $row;
        }
    }
    
    // Get team performance data
    if ($selected_team_member) {
        // If a team member is selected, only show that member's performance
        $team_query = "
            SELECT 
                u.id,
                u.name,
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
                COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
                CASE 
                    WHEN COUNT(DISTINCT l.id) > 0 
                    THEN ROUND((COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
                    ELSE 0
                END as conversion_rate,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
            FROM 
                users u
            LEFT JOIN 
                leads l ON l.user_id = u.id AND l.created_at BETWEEN ? AND ?
            LEFT JOIN 
                lead_activities la ON la.lead_id = l.id
            WHERE 
                u.id = ?
            GROUP BY 
                u.id, u.name
            ORDER BY 
                total_leads DESC
        ";
        
        $stmt = $conn->prepare($team_query);
        $stmt->bind_param("ssi", $start_date, $end_date, $selected_team_member);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $reportData['team_performance'][] = $row;
            }
        }
    } else {
        // Show all teams' performance, including those with no leads
        if ($user['role'] == 'admin' && $selected_team_id == 'all') {
            $team_query = "
                SELECT 
                    t.id,
                    t.name,
                    COUNT(DISTINCT l.id) as total_leads,
                    COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
                    COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
                    CASE 
                        WHEN COUNT(DISTINCT l.id) > 0 
                        THEN ROUND((COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
                        ELSE 0
                    END as conversion_rate,
                    SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
                FROM 
                    teams t
                LEFT JOIN 
                    users u ON u.team_id = t.id
                LEFT JOIN 
                    leads l ON l.user_id = u.id AND l.created_at BETWEEN ? AND ?
                LEFT JOIN 
                    lead_activities la ON la.lead_id = l.id
                GROUP BY 
                    t.id, t.name
                ORDER BY 
                    t.name ASC
            ";
            
            $stmt = $conn->prepare($team_query);
            $stmt->bind_param("ss", $start_date, $end_date);
        } else {
            // Get team member performance for specific team
            $team_id = ($user['role'] == 'admin') ? $selected_team_id : $user['team_id'];
            
            $team_query = "
                SELECT 
                    u.id,
                    u.name,
                    COUNT(DISTINCT l.id) as total_leads,
                    COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
                    COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
                    CASE 
                        WHEN COUNT(DISTINCT l.id) > 0 
                        THEN ROUND((COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
                        ELSE 0
                    END as conversion_rate,
                    SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
                FROM 
                    users u
                LEFT JOIN 
                    leads l ON l.user_id = u.id AND l.created_at BETWEEN ? AND ?
                LEFT JOIN 
                    lead_activities la ON la.lead_id = l.id
                WHERE 
                    u.team_id = ?
                GROUP BY 
                    u.id, u.name
                ORDER BY 
                    total_leads DESC
            ";
            
            $stmt = $conn->prepare($team_query);
            $stmt->bind_param("ssi", $start_date, $end_date, $team_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $reportData['team_performance'][] = $row;
            }
        }
    }
} else {
    // Handle specific team selection (non-admin or admin with specific team)
    $team_id = ($user['role'] == 'admin') ? $selected_team_id : $user['team_id'];
    
    // Get summary data for specific team
    if ($selected_team_member) {
        // Filter by selected team member
        $summary_query = "
            SELECT 
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
                COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
            FROM 
                leads l
            LEFT JOIN 
                lead_activities la ON la.lead_id = l.id
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                l.user_id = ? AND
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
        ";
        
        $stmt = $conn->prepare($summary_query);
        $stmt->bind_param("iiss", $selected_team_member, $team_id, $start_date, $end_date);
    } else {
        // No team member filter, get all data for the team
        $summary_query = "
            SELECT 
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
                COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
            FROM 
                leads l
            LEFT JOIN 
                lead_activities la ON la.lead_id = l.id
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
        ";
        
        $stmt = $conn->prepare($summary_query);
        $stmt->bind_param("iss", $team_id, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $summary = $result->fetch_assoc();
        $reportData['total_leads'] = $summary['total_leads'];
        $reportData['presentations'] = $summary['presentations'];
        $reportData['closed_deals'] = $summary['closed_deals'];
        $reportData['total_value'] = $summary['total_value'];
        
        // Calculate conversion rate
        if ($reportData['total_leads'] > 0) {
            $reportData['conversion_rate'] = round(($reportData['closed_deals'] / $reportData['total_leads']) * 100, 1);
        }
    }
    
    // Get status distribution for specific team
    if ($selected_team_member) {
        $status_query = "
            SELECT 
                l.status,
                COUNT(l.id) as count,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as value
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                l.user_id = ? AND
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.status
            ORDER BY 
                count DESC
        ";
        
        $stmt = $conn->prepare($status_query);
        $stmt->bind_param("iiss", $selected_team_member, $team_id, $start_date, $end_date);
    } else {
        $status_query = "
            SELECT 
                l.status,
                COUNT(l.id) as count,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as value
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.status
            ORDER BY 
                count DESC
        ";
        
        $stmt = $conn->prepare($status_query);
        $stmt->bind_param("iss", $team_id, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['status_distribution'][] = $row;
        }
    }
    
    // Get temperature distribution for specific team
    if ($selected_team_member) {
        $temp_query = "
            SELECT 
                l.temperature,
                COUNT(l.id) as count,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as value
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                l.user_id = ? AND
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.temperature
            ORDER BY 
                FIELD(l.temperature, 'Hot', 'Warm', 'Cold')
        ";
        
        $stmt = $conn->prepare($temp_query);
        $stmt->bind_param("iiss", $selected_team_member, $team_id, $start_date, $end_date);
    } else {
        $temp_query = "
            SELECT 
                l.temperature,
                COUNT(l.id) as count,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as value
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.temperature
            ORDER BY 
                FIELD(l.temperature, 'Hot', 'Warm', 'Cold')
        ";
        
        $stmt = $conn->prepare($temp_query);
        $stmt->bind_param("iss", $team_id, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['temperature_distribution'][] = $row;
        }
    }
    
    // Get top projects for specific team
    if ($selected_team_member) {
        $projects_query = "
            SELECT 
                l.developer,
                COUNT(l.id) as count
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                l.user_id = ? AND
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.developer
            ORDER BY 
                count DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($projects_query);
        $stmt->bind_param("iiss", $selected_team_member, $team_id, $start_date, $end_date);
    } else {
        $projects_query = "
            SELECT 
                l.developer,
                COUNT(l.id) as count
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.developer
            ORDER BY 
                count DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($projects_query);
        $stmt->bind_param("iss", $team_id, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['top_projects'][] = $row;
        }
    }
    
    // Get top models for specific team
    if ($selected_team_member) {
        $models_query = "
            SELECT 
                l.project_model,
                COUNT(l.id) as count
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                l.user_id = ? AND
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.project_model
            ORDER BY 
                count DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($models_query);
        $stmt->bind_param("iiss", $selected_team_member, $team_id, $start_date, $end_date);
    } else {
        $models_query = "
            SELECT 
                l.project_model,
                COUNT(l.id) as count
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.project_model
            ORDER BY 
                count DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($models_query);
        $stmt->bind_param("iss", $team_id, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['top_models'][] = $row;
        }
    }
    
    // Get top sources for specific team
    if ($selected_team_member) {
        $sources_query = "
            SELECT 
                l.source,
                COUNT(l.id) as count
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                l.user_id = ? AND
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.source
            ORDER BY 
                count DESC
            LIMIT 8
        ";
        
        $stmt = $conn->prepare($sources_query);
        $stmt->bind_param("iiss", $selected_team_member, $team_id, $start_date, $end_date);
    } else {
        $sources_query = "
            SELECT 
                l.source,
                COUNT(l.id) as count
            FROM 
                leads l
            LEFT JOIN 
                users u ON u.id = l.user_id
            WHERE 
                u.team_id = ? AND
                l.created_at BETWEEN ? AND ?
            GROUP BY 
                l.source
            ORDER BY 
                count DESC
            LIMIT 8
        ";
        
        $stmt = $conn->prepare($sources_query);
        $stmt->bind_param("iss", $team_id, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['top_sources'][] = $row;
        }
    }
    
    // Get team performance data for specific team
    if ($selected_team_member) {
        // If a team member is selected, only show that member's performance
        $team_query = "
            SELECT 
                u.id,
                u.name,
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
                COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
                CASE 
                    WHEN COUNT(DISTINCT l.id) > 0 
                    THEN ROUND((COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
                    ELSE 0
                END as conversion_rate,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
            FROM 
                users u
            LEFT JOIN 
                leads l ON l.user_id = u.id AND l.created_at BETWEEN ? AND ?
            LEFT JOIN 
                lead_activities la ON la.lead_id = l.id
            WHERE 
                u.id = ? AND u.team_id = ?
            GROUP BY 
                u.id, u.name
            ORDER BY 
                total_leads DESC
        ";
        
        $stmt = $conn->prepare($team_query);
        $stmt->bind_param("ssii", $start_date, $end_date, $selected_team_member, $team_id);
    } else {
        // Get team member performance for specific team
        $team_query = "
            SELECT 
                u.id,
                u.name,
                COUNT(DISTINCT l.id) as total_leads,
                COUNT(DISTINCT CASE WHEN la.activity_type = 'Presentation Stage' THEN l.id END) as presentations,
                COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) as closed_deals,
                CASE 
                    WHEN COUNT(DISTINCT l.id) > 0 
                    THEN ROUND((COUNT(DISTINCT CASE WHEN l.status = 'Closed Deal' THEN l.id END) * 100.0 / COUNT(DISTINCT l.id)), 1)
                    ELSE 0
                END as conversion_rate,
                SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_value
            FROM 
                users u
            LEFT JOIN 
                leads l ON l.user_id = u.id AND l.created_at BETWEEN ? AND ?
            LEFT JOIN 
                lead_activities la ON la.lead_id = l.id
            WHERE 
                u.team_id = ?
            GROUP BY 
                u.id, u.name
            ORDER BY 
                total_leads DESC
        ";
        
        $stmt = $conn->prepare($team_query);
        $stmt->bind_param("ssi", $start_date, $end_date, $team_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reportData['team_performance'][] = $row;
        }
    }
}

// Add this function to get months in the selected quarter
function getMonthsInQuarter($quarter) {
    $startMonth = ($quarter - 1) * 3 + 1;
    $months = [];
    
    for ($i = 0; $i < 3; $i++) {
        $monthNum = $startMonth + $i;
        $monthName = date('F', mktime(0, 0, 0, $monthNum, 1));
        $months[$monthNum] = $monthName;
    }
    
    return $months;
}

// Get months for the selected quarter
$monthsInQuarter = getMonthsInQuarter($quarter);

// Get team name for display
$team_name = "All Teams";
if ($selected_team_id != 'all' && $user['role'] == 'admin') {
    foreach ($all_teams as $team) {
        if ($team['id'] == $selected_team_id) {
            $team_name = $team['name'];
            break;
        }
    }
} elseif ($user['role'] != 'admin') {
    // Get team name for managers and supervisors
    $team_query = "SELECT name FROM teams WHERE id = ?";
    $stmt = $conn->prepare($team_query);
    $stmt->bind_param("i", $user['team_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $team = $result->fetch_assoc();
        $team_name = $team['name'];
    }
}

// Get team member name for display if selected
$team_member_name = "";
if ($selected_team_member) {
    foreach ($teamMembers as $member) {
        if ($member['id'] == $selected_team_member) {
            $team_member_name = $member['name'];
            break;
        }
    }
}

// Close database connection
$conn->close();

// Function to check if we have chart data
function hasChartData($reportData) {
    return !empty($reportData['status_distribution']) && 
           !empty($reportData['temperature_distribution']) && 
           !empty($reportData['top_projects']) && 
           !empty($reportData['top_models']) &&
           !empty($reportData['top_sources']);
}

$hasData = $reportData['total_leads'] > 0 || !empty($reportData['team_performance']);
$hasCharts = hasChartData($reportData);

// Get quarter name
$quarterNames = [
    1 => "Q1 (Jan-Mar)",
    2 => "Q2 (Apr-Jun)",
    3 => "Q3 (Jul-Sep)",
    4 => "Q4 (Oct-Dec)"
];
$quarterName = $quarterNames[$quarter];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quarterly Reports</title>
    <link rel="stylesheet" href="assets/css/style.css">
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

    /* Reports Page */
    .reports-page {
        flex: 1;
        padding: 1.5rem;
        width: 100%;
        margin: 0;
        min-height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
    }

    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--gray-light);
    }

    .page-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .page-title i {
        color: var(--primary);
    }

    .team-badge {
        display: inline-flex;
        align-items: center;
        background-color: var(--primary);
        color: var(--white);
        padding: 0.25rem 0.75rem;
        border-radius: var(--radius);
        font-size: 0.875rem;
        font-weight: 500;
        margin-left: 0.75rem;
    }

    .team-badge i {
        margin-right: 0.375rem;
        color: var(--white);
    }

    .period-badge {
        display: inline-flex;
        align-items: center;
        background-color: var(--secondary);
        color: var(--dark);
        padding: 0.25rem 0.75rem;
        border-radius: var(--radius);
        font-size: 0.875rem;
        font-weight: 500;
    }

    .period-badge i {
        margin-right: 0.375rem;
        color: var(--primary);
    }

    /* Filters */
    .report-filters {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }

    .form-group {
        flex: 1;
        min-width: 180px;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--dark);
        font-size: 0.875rem;
    }

    .form-group select {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--gray-light);
        border-radius: var(--radius);
        background-color: var(--white);
        font-size: 0.875rem;
        color: var(--dark);
        transition: var(--transition);
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
        padding-right: 2.5rem;
    }

    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px var(--primary-light);
    }

    /* Summary Cards */
    .report-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .summary-card {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        display: flex;
        align-items: center;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background-color: var(--primary);
    }

    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .summary-icon {
        width: 2.5rem;
        height: 2.5rem;
        background-color: var(--primary-light);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        color: var(--primary);
        font-size: 1rem;
        flex-shrink: 0;
    }

    .summary-info {
        flex: 1;
    }

    .summary-info h3 {
        margin: 0 0 0.25rem 0;
        font-size: 0.875rem;
        color: var(--gray);
        font-weight: 500;
    }

    .summary-info p {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
    }

    .summary-card.leads::before { background-color: var(--primary); }
    .summary-card.presentations::before { background-color: var(--info); }
    .summary-card.closed::before { background-color: var(--success); }
    .summary-card.rate::before { background-color: var(--warning); }

    .summary-card.leads .summary-icon { background-color: var(--primary-light); color: var(--primary); }
    .summary-card.presentations .summary-icon { background-color: var(--info-light); color: var(--info); }
    .summary-card.closed .summary-icon { background-color: var(--success-light); color: var(--success); }
    .summary-card.rate .summary-icon { background-color: var(--warning-light); color: var(--warning); }

    /* Charts */
    .report-charts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .chart-container {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        height: 320px;
        transition: var(--transition);
    }

    .chart-container:hover {
        box-shadow: var(--shadow);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .chart-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-title i {
        color: var(--primary);
    }

    .chart-body {
        height: calc(100% - 2.5rem);
        position: relative;
    }

    /* Sources Chart */
    .sources-chart-container {
        grid-column: 1 / -1;
        height: 400px;
    }

    /* Team Performance */
    .team-performance {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .team-performance-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }

    .team-performance-title {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .team-performance-title i {
        color: var(--primary);
    }

    /* Card Styles */
    .card {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .card-header {
        padding: 1.25rem;
        border-bottom: 1px solid var(--gray-light);
        background-color: var(--secondary);
    }

    .card-body {
        padding: 1.25rem;
    }

    /* Table Styles */
    .table-container {
        overflow-x: auto;
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
    }

    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background-color: var(--white);
        border-radius: var(--radius);
        overflow: hidden;
        margin-bottom: 0;
    }

    .table thead {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    }

    .table th {
        color: var(--white);
        font-weight: 600;
        font-size: 0.875rem;
        padding: 1rem;
        text-align: left;
        border-bottom: 2px solid var(--primary-dark);
        cursor: pointer;
        user-select: none;
        position: relative;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .table th:hover {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .table th i {
        margin-left: 0.5rem;
        font-size: 0.75rem;
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }

    .table th:hover i {
        opacity: 1;
    }

    .table th.asc::after,
    .table th.desc::after {
        content: '';
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
    }

    .table th.asc::after {
        border-bottom: 5px solid var(--white);
    }

    .table th.desc::after {
        border-top: 5px solid var(--white);
    }

    .table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--gray-light);
    }

    .table tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    .table tbody tr:nth-child(odd) {
        background-color: var(--white);
    }

    .table tbody tr:hover {
        background: linear-gradient(135deg, var(--primary-light) 0%, rgba(67, 97, 238, 0.05) 100%);
        transform: translateX(2px);
        box-shadow: 0 2px 8px rgba(67, 97, 238, 0.15);
        border-left: 4px solid var(--primary);
    }

    .table tbody tr:last-child {
        border-bottom: none;
    }

    .table td {
        padding: 1rem;
        vertical-align: middle;
        font-size: 0.875rem;
        color: var(--dark);
        border-bottom: 1px solid var(--gray-light);
        position: relative;
    }

    .table td.name {
        font-weight: 600;
        color: var(--primary-dark);
        font-size: 0.9rem;
    }

    .table td:first-child {
        border-left: 3px solid transparent;
        transition: border-left-color 0.2s ease;
    }

    .table tbody tr:hover td:first-child {
        border-left-color: var(--primary);
    }

    /* Metric Styles */
    .metric-value {
        font-weight: 600;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .metric-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }

    .metric-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    .metric-badge.high {
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
        color: var(--white);
    }

    .metric-badge.medium {
        background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
        color: var(--white);
    }

    .metric-badge.low {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        color: var(--white);
    }

    /* No Data */
    .no-data {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        padding: 3rem 1.5rem;
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .no-data i {
        font-size: 2.5rem;
        color: var(--gray);
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .no-data h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
    }

    .no-data p {
        margin: 0;
        color: var(--gray);
        max-width: 400px;
        margin: 0 auto;
    }

    /* Report Actions */
    .report-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1.5rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: var(--radius);
        font-weight: 500;
        font-size: 0.875rem;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        border: none;
    }

    .btn-primary {
        background-color: var(--primary);
        color: var(--white);
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
    }

    .btn-secondary {
        background-color: var(--secondary);
        color: var(--dark);
        border: 1px solid var(--gray-light);
    }

    .btn-secondary:hover {
        background-color: var(--gray-light);
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }

    .btn-outline {
        background-color: white;
        border: 1px solid var(--gray-light);
        color: var(--gray);
        transition: all 0.2s ease;
    }

    .btn-outline:hover,
    .btn-outline.active {
        background-color: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    /* Chart Actions */
    .chart-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Utility Classes */
    .text-center {
        text-align: center;
    }

    .d-flex {
        display: flex;
    }

    .align-items-center {
        align-items: center;
    }

    .me-2 {
        margin-right: 0.5rem;
    }

    .text-primary {
        color: var(--primary);
    }

    .text-info {
        color: var(--info);
    }

    .text-success {
        color: var(--success);
    }

    .text-warning {
        color: var(--warning);
    }

    /* COMPLETELY REVISED PRINT STYLES - FIXED CHART ALIGNMENT AND TABLE PLACEMENT */
    @media print {
        /* Basic print setup */
        @page {
            margin: 0.5in;
            size: A4 landscape;
        }
        
        * {
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        body {
            font-family: Arial, sans-serif !important;
            font-size: 10pt !important;
            line-height: 1.2 !important;
            color: black !important;
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        
        /* Hide non-essential elements */
        .sidebar,
        .header,
        .report-filters,
        .report-actions,
        .btn,
        .no-data,
        .chart-actions {
            display: none !important;
        }
        
        /* Show main content */
        .reports-page {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            min-height: auto !important;
            display: block !important;
        }
        
        /* Page header for print */
        .page-header {
            text-align: center !important;
            margin-bottom: 15px !important;
            padding-bottom: 8px !important;
            border-bottom: 2px solid black !important;
            page-break-after: avoid !important;
            display: block !important;
        }
        
        .page-title {
            font-size: 16pt !important;
            font-weight: bold !important;
            color: black !important;
            margin-bottom: 5px !important;
            display: block !important;
            text-align: center !important;
        }
        
        .team-badge,
        .period-badge {
            font-size: 10pt !important;
            color: black !important;
            background: #f0f0f0 !important;
            border: 1px solid black !important;
            padding: 3px 8px !important;
            margin: 0 5px !important;
            display: inline-block !important;
        }
        
        /* Summary cards - FIXED LAYOUT */
        .report-summary {
            width: 100% !important;
            margin-bottom: 15px !important;
            page-break-after: avoid !important;
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            justify-content: space-between !important;
        }
        
        .summary-card {
            width: 24% !important;
            border: 1px solid black !important;
            padding: 8px !important;
            text-align: center !important;
            background: #f8f8f8 !important;
            box-shadow: none !important;
            margin: 0 !important;
        }
        
        .summary-card::before {
            display: none !important;
        }
        
        .summary-icon {
            display: none !important;
        }
        
        .summary-info {
            display: block !important;
        }
        
        .summary-info h3 {
            font-size: 8pt !important;
            margin: 0 0 3px 0 !important;
            color: black !important;
            text-transform: uppercase !important;
            font-weight: bold !important;
        }
        
        .summary-info p {
            font-size: 12pt !important;
            font-weight: bold !important;
            color: black !important;
            margin: 0 !important;
        }
        
        /* REORDERED CONTENT - Team Performance Table First */
        /* This is a special container we'll add in the HTML */
        .print-order-wrapper {
            display: flex !important;
            flex-direction: column !important;
        }
        
        .print-first {
            order: -1 !important;
        }
        
        /* Team performance cards - FIXED */
        .card {
            border: 1px solid black !important;
            margin-bottom: 10px !important;
            box-shadow: none !important;
            page-break-inside: avoid !important;
            display: block !important;
            width: 100% !important;
        }
        
        .card-header {
            background: #e0e0e0 !important;
            border-bottom: 1px solid black !important;
            padding: 5px 8px !important;
            display: block !important;
        }
        
        .card-body {
            padding: 0 !important;
            display: block !important;
        }
        
        /* Table - FIXED DISPLAY */
        .table-container {
            overflow: visible !important;
            display: block !important;
        }
        
        .table {
            border-collapse: collapse !important;
            width: 100% !important;
            display: table !important;
            margin: 0 !important;
        }
        
        .table thead {
            display: table-header-group !important;
            background: #d0d0d0 !important;
        }
        
        .table tbody {
            display: table-row-group !important;
        }
        
        .table tr {
            display: table-row !important;
            page-break-inside: avoid !important;
        }
        
        .table th,
        .table td {
            display: table-cell !important;
            border: 1px solid black !important;
            padding: 4px 6px !important;
            text-align: left !important;
            font-size: 8pt !important;
            vertical-align: top !important;
        }
        
        .table th {
            background: #d0d0d0 !important;
            font-weight: bold !important;
            text-align: center !important;
            color: black !important;
        }
        
        .table tbody tr:nth-child(even) {
            background: #f8f8f8 !important;
        }
        
        .table tbody tr:nth-child(odd) {
            background: white !important;
        }
        
        /* Charts - COMPLETELY REVISED FOR BETTER ALIGNMENT */
        .report-charts {
            width: 100% !important;
            margin-bottom: 15px !important;
            page-break-before: always !important;
            display: block !important;
        }
        
        /* Make each chart container a block element with consistent sizing */
        .chart-container {
            display: block !important;
            width: 100% !important;
            height: auto !important;
            min-height: 200px !important;
            max-height: 250px !important;
            margin-bottom: 20px !important;
            border: 1px solid black !important;
            padding: 10px !important;
            background: white !important;
            box-shadow: none !important;
            page-break-inside: avoid !important;
            overflow: hidden !important;
        }
        
        .chart-header {
            margin-bottom: 5px !important;
            text-align: center !important;
            display: block !important;
        }
        
        .chart-title {
            font-size: 10pt !important;
            font-weight: bold !important;
            color: black !important;
            margin: 0 !important;
            display: block !important;
        }
        
        .chart-title i {
            display: none !important;
        }
        
        .chart-body {
            height: 180px !important;
            width: 100% !important;
            display: block !important;
            position: relative !important;
            text-align: center !important;
        }
        
        /* Canvas elements - FORCE THEM TO PRINT */
        canvas {
            max-width: 100% !important;
            height: 180px !important;
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            margin: 0 auto !important;
        }
        
        /* Sources chart - FULL WIDTH */
        .sources-chart-container {
            width: 100% !important;
            height: auto !important;
            min-height: 200px !important;
            margin-bottom: 20px !important;
        }
        
        /* Metric badges - SIMPLIFIED */
        .metric-badge {
            padding: 1px 4px !important;
            border-radius: 2px !important;
            font-size: 7pt !important;
            font-weight: bold !important;
            display: inline-block !important;
        }
        
        .metric-badge.high {
            background: #d4edda !important;
            color: #155724 !important;
            border: 1px solid #155724 !important;
        }
        
        .metric-badge.medium {
            background: #fff3cd !important;
            color: #856404 !important;
            border: 1px solid #856404 !important;
        }
        
        .metric-badge.low {
            background: #f8d7da !important;
            color: #721c24 !important;
            border: 1px solid #721c24 !important;
        }
        
        /* Hide all icons in print */
        .fas, .far, .fab, i[class*="fa"] {
            display: none !important;
        }
        
        /* Metric values - SIMPLIFIED */
        .metric-value {
            display: block !important;
            text-align: center !important;
        }
        
        /* Force visibility of all content */
        .reports-page * {
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        /* Two-column chart layout for first page */
        .two-column-charts {
            width: 100% !important;
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: wrap !important;
            justify-content: space-between !important;
            margin-bottom: 15px !important;
        }
        
        .two-column-charts .chart-container {
            width: 48% !important;
            display: inline-block !important;
            vertical-align: top !important;
        }
    }

    /* Animation for sorting */
    @keyframes rowSlideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .row-animate {
        animation: rowSlideIn 0.3s ease-out forwards;
    }
</style>

<!-- Add this right before the closing </head> tag -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Ensure data is available for charts
const reportData = {
    status_distribution: <?php echo json_encode($reportData['status_distribution'] ?? []); ?>,
    temperature_distribution: <?php echo json_encode($reportData['temperature_distribution'] ?? []); ?>,
    top_projects: <?php echo json_encode($reportData['top_projects'] ?? []); ?>,
    top_models: <?php echo json_encode($reportData['top_models'] ?? []); ?>,
    top_sources: <?php echo json_encode($reportData['top_sources'] ?? []); ?>,
    team_performance: <?php echo json_encode($reportData['team_performance'] ?? []); ?>
};
</script>
<script src="assets/js/reports-charts.js"></script>

<!-- Add this right after the report-charts div -->
<input type="hidden" id="statusData" value='<?php echo htmlspecialchars(json_encode($reportData['status_distribution'] ?? [])); ?>'>
<input type="hidden" id="temperatureData" value='<?php echo htmlspecialchars(json_encode($reportData['temperature_distribution'] ?? [])); ?>'>
<input type="hidden" id="projectsData" value='<?php echo htmlspecialchars(json_encode($reportData['top_projects'] ?? [])); ?>'>
<input type="hidden" id="modelsData" value='<?php echo htmlspecialchars(json_encode($reportData['top_models'] ?? [])); ?>'>
<input type="hidden" id="sourcesData" value='<?php echo htmlspecialchars(json_encode($reportData['top_sources'] ?? [])); ?>'>
<input type="hidden" id="teamPerformanceData" value='<?php echo htmlspecialchars(json_encode($reportData['team_performance'] ?? [])); ?>'>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="reports-page">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-chart-line"></i> Quarterly Reports
                            <?php if ($user['role'] == 'admin' && $selected_team_id != 'all'): ?>
                                <span class="team-badge">
                                    <i class="fas fa-users"></i> <?php echo htmlspecialchars($team_name); ?>
                                </span>
                            <?php elseif ($user['role'] != 'admin'): ?>
                                <span class="team-badge">
                                    <i class="fas fa-users"></i> <?php echo htmlspecialchars($team_name); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($selected_team_member): ?>
                                <span class="team-badge" style="background-color: var(--info);">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($team_member_name); ?>
                                </span>
                            <?php endif; ?>
                        </h1>
                    </div>
                    
                    <div class="period-badge">
                        <i class="fas fa-calendar-alt"></i> <?php echo $year; ?> - <?php echo $quarterName; ?>
                        <?php if ($month > 0): ?>
                            - <?php echo date('F', mktime(0, 0, 0, $month, 1)); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="report-filters">
                    <form method="GET" action="reports.php" class="filter-form">
                        <?php if ($user['role'] == 'admin'): ?>
                        <div class="form-group">
                            <label for="team_id">Team</label>
                            <select id="team_id" name="team_id" onchange="this.form.submit()">
                                <option value="all" <?php echo $selected_team_id == 'all' ? 'selected' : ''; ?>>All Teams</option>
                                <?php foreach ($all_teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" <?php echo $selected_team_id == $team['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($team['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="year">Year</label>
                            <select id="year" name="year" onchange="this.form.submit()">
                                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="quarter">Quarter</label>
                            <select id="quarter" name="quarter" onchange="this.form.submit()">
                                <option value="1" <?php echo $quarter == 1 ? 'selected' : ''; ?>>Q1 (Jan-Mar)</option>
                                <option value="2" <?php echo $quarter == 2 ? 'selected' : ''; ?>>Q2 (Apr-Jun)</option>
                                <option value="3" <?php echo $quarter == 3 ? 'selected' : ''; ?>>Q3 (Jul-Sep)</option>
                                <option value="4" <?php echo $quarter == 4 ? 'selected' : ''; ?>>Q4 (Oct-Dec)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="month">Month</label>
                            <select id="month" name="month" onchange="this.form.submit()">
                                <option value="0" <?php echo $month == 0 ? 'selected' : ''; ?>>All Months</option>
                                <?php foreach ($monthsInQuarter as $monthNum => $monthName): ?>
                                <option value="<?php echo $monthNum; ?>" <?php echo $month == $monthNum ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($monthName); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (count($teamMembers) > 0): ?>
                        <div class="form-group">
                            <label for="team_member">Team Member</label>
                            <select id="team_member" name="team_member" onchange="this.form.submit()">
                                <option value="">All Team Members</option>
                                <?php foreach ($teamMembers as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo $selected_team_member == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if ($hasData): ?>
                <!-- Wrap content in a print-order-wrapper to control print order -->
                <div class="print-order-wrapper">
                    <div class="report-summary">
                        <div class="summary-card leads">
                            <div class="summary-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Total Leads</h3>
                                <p><?php echo $reportData['total_leads']; ?></p>
                            </div>
                        </div>
                        
                        <div class="summary-card presentations">
                            <div class="summary-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Presentations</h3>
                                <p><?php echo $reportData['presentations']; ?></p>
                            </div>
                        </div>
                        
                        <div class="summary-card closed">
                            <div class="summary-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Closed Deals</h3>
                                <p><?php echo $reportData['closed_deals']; ?></p>
                            </div>
                        </div>
                        
                        <div class="summary-card rate">
                            <div class="summary-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="summary-info">
                                <h3>Closed Deal Rate</h3>
                                <p><?php echo $reportData['conversion_rate']; ?>%</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($reportData['team_performance'])): ?>
                    <!-- Team Performance Table - Now with print-first class to appear on first page -->
                    <div class="print-first">
                        <!-- Team Performance Table -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="chart-title">
                                    <?php if ($user['role'] == 'admin' && $selected_team_id == 'all'): ?>
                                        Team Performance Details
                                    <?php elseif ($user['role'] == 'admin'): ?>
                                        Team Member Performance
                                    <?php else: ?>
                                        Team Member Performance
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="table-container">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th data-column="name">Name</th>
                                                    <th data-column="total_leads">Total Leads</th>
                                                    <th data-column="presentations">Presentations</th>
                                                    <th data-column="closed_deals">Closed Deals</th>
                                                    <th data-column="conversion_rate">Conversion Rate</th>
                                                    <th data-column="total_value">Total Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($reportData['team_performance'] as $performer): ?>
                                                <tr data-name="<?php echo htmlspecialchars($performer['name']); ?>" 
                                                    data-leads="<?php echo $performer['total_leads']; ?>"
                                                    data-presentations="<?php echo $performer['presentations']; ?>"
                                                    data-closed="<?php echo $performer['closed_deals']; ?>"
                                                    data-rate="<?php echo $performer['conversion_rate']; ?>"
                                                    data-value="<?php echo $performer['total_value']; ?>">
                                                    <td class="name">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-user me-2 text-primary"></i>
                                                            <?php echo htmlspecialchars($performer['name']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="metric-value">
                                                            <i class="fas fa-users me-2 text-primary"></i>
                                                            <?php echo number_format($performer['total_leads']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="metric-value">
                                                            <i class="fas fa-handshake me-2 text-info"></i>
                                                            <?php echo number_format($performer['presentations']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="metric-value">
                                                            <i class="fas fa-check-circle me-2 text-success"></i>
                                                            <?php echo number_format($performer['closed_deals']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="metric-value">
                                                            <?php
                                                            $rate = floatval($performer['conversion_rate']);
                                                            $rateClass = $rate >= 50 ? 'high' : ($rate >= 25 ? 'medium' : 'low');
                                                            $rateIcon = $rate >= 50 ? 'fas fa-arrow-up' : ($rate >= 25 ? 'fas fa-minus' : 'fas fa-arrow-down');
                                                            ?>
                                                            <i class="<?php echo $rateIcon; ?> me-2"></i>
                                                            <span class="metric-badge <?php echo $rateClass; ?>">
                                                                <?php echo number_format($rate, 1); ?>%
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="metric-value">
                                                            <i class="fas fa-money-bill-wave me-2 text-warning"></i>
                                                            ₱<?php echo number_format($performer['total_value'], 2); ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($hasCharts): ?>
                    <!-- Charts section - will appear after team performance table in print -->
                    <div>
                        <!-- First row of charts - 2 column layout -->
                        <div class="two-column-charts">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title"><i class="fas fa-chart-pie"></i> Lead Status Distribution</h3>
                                </div>
                                <div class="chart-body">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title"><i class="fas fa-thermometer-half"></i> Lead Temperature</h3>
                                </div>
                                <div class="chart-body">
                                    <canvas id="temperatureChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Second row of charts - 2 column layout -->
                        <div class="two-column-charts">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title"><i class="fas fa-building"></i> Top Projects</h3>
                                </div>
                                <div class="chart-body">
                                    <canvas id="projectsChart"></canvas>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3 class="chart-title"><i class="fas fa-home"></i> Top Models Inquired</h3>
                                </div>
                                <div class="chart-body">
                                    <canvas id="modelsChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Sources chart - full width -->
                        <div class="chart-container sources-chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title"><i class="fas fa-bullhorn"></i> Lead Sources</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="sourcesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($reportData['team_performance'])): ?>
                    <!-- Team Performance Graph - will appear after charts in print -->
                    <div>
                        <div class="card">
                            <div class="card-header">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <h3 class="chart-title">
                                        <i class="fas fa-chart-bar"></i> Team Performance Overview
                                    </h3>
                                    <div class="chart-actions">
                                        <button type="button" class="btn btn-outline btn-sm active" onclick="updateTeamChart('leads')">
                                            <i class="fas fa-users"></i> Leads
                                        </button>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="updateTeamChart('conversion')">
                                            <i class="fas fa-percentage"></i> Conversion
                                        </button>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="updateTeamChart('value')">
                                            <i class="fas fa-money-bill-wave"></i> Value
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height: 400px;">
                                    <canvas id="teamPerformanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-chart-bar"></i>
                    <h3>No Data Available</h3>
                    <p>There is no data available for the selected period and team. Try changing your filters or selecting a different time period.</p>
                </div>
                <?php endif; ?>
                
                <?php if ($hasData): ?>
                <div class="report-actions">
                    <button onclick="exportReport()" class="btn btn-primary">
                        <i class="fas fa-file-export"></i> Export Report
                    </button>
                    <button onclick="printReport()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Function to handle report export
    function exportReport() {
        // Get current filter parameters
        const params = new URLSearchParams(window.location.search);
        let exportUrl = 'export-report.php?';
        
        // Add current filters to export URL
        if (params.has('year')) exportUrl += 'year=' + params.get('year') + '&';
        if (params.has('quarter')) exportUrl += 'quarter=' + params.get('quarter') + '&';
        if (params.has('month')) exportUrl += 'month=' + params.get('month') + '&';
        if (params.has('team_id')) exportUrl += 'team_id=' + params.get('team_id') + '&';
        if (params.has('team_member')) exportUrl += 'team_member=' + params.get('team_member');
        
        // Redirect to export URL
        window.location.href = exportUrl;
    }

    // COMPLETELY REVISED PRINT FUNCTION FOR BETTER CHART RENDERING
    function printReport() {
        console.log('Print function called');
        
        // Set chart defaults for better print rendering
        Chart.defaults.font.size = 10;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.legend.labels.boxWidth = 10;
        Chart.defaults.plugins.legend.labels.padding = 5;
        
        // Force all charts to render at their maximum size
        const charts = document.querySelectorAll('canvas');
        charts.forEach(canvas => {
            if (canvas.chart) {
                canvas.chart.resize();
            }
        });
        
        // Convert all charts to images before printing
        const chartImages = [];
        
        const processCharts = async () => {
            for (let i = 0; i < charts.length; i++) {
                const canvas = charts[i];
                try {
                    // Wait for any animations to complete
                    await new Promise(resolve => setTimeout(resolve, 100));
                    
                    // Convert canvas to image with high quality
                    const imageData = canvas.toDataURL('image/png', 1.0);
                    const img = document.createElement('img');
                    img.src = imageData;
                    img.style.maxWidth = '100%';
                    img.style.height = 'auto';
                    img.style.display = 'block';
                    img.style.margin = '0 auto';
                    img.className = 'chart-print-image';
                    
                    // Replace canvas with image
                    const container = canvas.parentElement;
                    container.appendChild(img);
                    canvas.style.display = 'none';
                    
                    chartImages.push({ canvas, img, container });
                    console.log(`Chart ${i + 1} converted to image`);
                } catch (error) {
                    console.error(`Error converting chart ${i + 1}:`, error);
                }
            }
            
            // Wait for images to load then print
            setTimeout(() => {
                console.log('Initiating print...');
                window.print();
                
                // Restore canvases after printing
                setTimeout(() => {
                    chartImages.forEach(({ canvas, img, container }) => {
                        canvas.style.display = 'block';
                        if (container.contains(img)) {
                            container.removeChild(img);
                        }
                    });
                    console.log('Charts restored after printing');
                }, 1000);
            }, 500);
        };
        
        processCharts();
    }

    // Enhanced table sorting functionality
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.querySelector('.table');
        if (!table) return;

        const tbody = table.querySelector('tbody');
        const headers = table.querySelectorAll('th[data-column]');
        
        let currentSort = {
            column: null,
            direction: 'asc'
        };

        // Add click event listeners to headers
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-column');
                sortTable(column);
            });
        });

        function sortTable(column) {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Remove existing sort classes
            headers.forEach(h => {
                h.classList.remove('asc', 'desc');
            });
            
            // Determine sort direction
            let direction = 'asc';
            if (currentSort.column === column && currentSort.direction === 'asc') {
                direction = 'desc';
            }
            
            // Update current sort
            currentSort = { column, direction };
            
            // Add sort class to current header
            const currentHeader = table.querySelector(`th[data-column="${column}"]`);
            currentHeader.classList.add(direction);
            
            // Sort rows
            rows.sort((a, b) => {
                let aValue, bValue;
                
                // Use data attributes for more reliable sorting
                switch(column) {
                    case 'name':
                        aValue = a.getAttribute('data-name').toLowerCase();
                        bValue = b.getAttribute('data-name').toLowerCase();
                        break;
                    case 'total_leads':
                        aValue = parseInt(a.getAttribute('data-leads'));
                        bValue = parseInt(b.getAttribute('data-leads'));
                        break;
                    case 'presentations':
                        aValue = parseInt(a.getAttribute('data-presentations'));
                        bValue = parseInt(b.getAttribute('data-presentations'));
                        break;
                    case 'closed_deals':
                        aValue = parseInt(a.getAttribute('data-closed'));
                        bValue = parseInt(b.getAttribute('data-closed'));
                        break;
                    case 'conversion_rate':
                        aValue = parseFloat(a.getAttribute('data-rate'));
                        bValue = parseFloat(b.getAttribute('data-rate'));
                        break;
                    case 'total_value':
                        aValue = parseFloat(a.getAttribute('data-value'));
                        bValue = parseFloat(b.getAttribute('data-value'));
                        break;
                    default:
                        aValue = 0;
                        bValue = 0;
                }
                
                if (direction === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
            
            // Reorder rows in the table
            rows.forEach(row => {
                tbody.appendChild(row);
            });
            
            // Add animation effect
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.classList.add('row-animate');
                setTimeout(() => {
                    row.classList.remove('row-animate');
                }, 300 + (index * 50));
            });
        }
    });
    </script>
</body>
</html>