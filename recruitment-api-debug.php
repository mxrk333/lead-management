<?php
session_start();

// Include database connection
// Ensure this file does NOT output anything (no spaces, newlines, or HTML outside <?php tags)
require_once 'config/pdo-database.php';

// Enable error reporting for debugging
ini_set('display_errors', 1); // Keep this ON for debugging, turn OFF in production
error_reporting(E_ALL);

// Set headers before any output
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Added Authorization for completeness

// Debug function to log information
function debugLog($message, $data = null)
{
    // Use error_log for server-side logging, not echo
    error_log("RECRUITMENT API DEBUG: " . $message . ($data ? " - " . json_encode($data) : ""));
}

// Get current user info from session
$current_user_role = $_SESSION['role'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? null;
$current_recruiter_name = '';
$current_recruiter_id = null;

if ($current_user_id && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $current_user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $current_recruiter_name = $user_data['name'];
            $current_recruiter_id = $user_data['id'];
        }
    } catch (Exception $e) {
        error_log("Error fetching recruiter info from session: " . $e->getMessage());
    }
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if PDO connection exists
if (!isset($pdo) || $pdo === null) {
    debugLog("Database connection failed: PDO object is null or not set.");
    echo json_encode(['success' => false, 'message' => 'Database connection not available.']);
    exit;
}

/**
 * Fetches recruitment leads based on filters and sorting.
 */
function get_recruitment_leads(PDO $pdo, array $filters = [])
{
    debugLog("get_recruitment_leads called", $filters);

    try {
        // $sql = "SELECT rl.id, rl.timestamp, rl.full_name, rl.contact_number, rl.email, rl.recruiter_name, rl.status, rl.source, rl.agent_onboarding_status, rl.remarks, rl.created_at, rl.updated_at, rl.recruiter_id, rl.recruiter_team_id, t.name AS recruiter_team, rl.pre_assessment, rl.accreditation, rl.assessment, rl.sales_training, rl.site_tour, rl.onboarding, rl.habit_forming, rl.digital_training, rl.sales_training_materials, rl.objection_handling, rl.VAST, rl.sales_monitoring, rl.LMS, rl.comm_structure, rl.terminologies, rl.focus_projects
        // FROM recruitment_leads rl
        // LEFT JOIN teams t ON rl.recruiter_team_id = t.id
        // WHERE 1=1";

        $sql = "SELECT rl.id, rl.timestamp, rl.full_name, rl.contact_number, rl.email, rl.recruiter_name, rl.status, rl.source, u.name AS source_name, rl.agent_onboarding_status, rl.onboarding_status, rl.remarks, rl.created_at, rl.updated_at, rl.recruiter_id, rl.recruiter_team_id, t.name AS recruiter_team, rl.pre_assessment, rl.accreditation, rl.assessment, rl.sales_training, rl.site_tour, rl.onboarding, rl.habit_forming, rl.digital_training, rl.sales_training_materials, rl.objection_handling, rl.VAST, rl.sales_monitoring, rl.LMS, rl.comm_structure, rl.terminologies, rl.focus_projects
        FROM recruitment_leads rl
        LEFT JOIN teams t ON rl.recruiter_team_id = t.id
        LEFT JOIN users u ON rl.source = u.id
        WHERE 1=1";

        $params = [];

        // Add filter conditions
        if (!empty($filters['id'])) {
            $sql .= " AND rl.id = :id";
            $params[':id'] = $filters['id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['source'])) {
            $sql .= " AND source = :source";
            $params[':source'] = $filters['source'];
        }

        if (!empty($filters['team'])) {
            $sql .= " AND rl.recruiter_team_id = :team";
            $params[':team'] = $filters['team'];
        }

        // Important: allow filtering when value is 0 (Not Onboarded)
        if (isset($filters['onboardStatus']) && $filters['onboardStatus'] !== '') {
            $sql .= " AND rl.onboarding_status = :onboardStatus";
            // Ensure we bind as int 0/1
            $params[':onboardStatus'] = (int)$filters['onboardStatus'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sql .= " AND (rl.full_name LIKE :search_name OR rl.email LIKE :search_email OR rl.contact_number LIKE :search_contact)";
            $params[':search_name'] = $searchTerm;
            $params[':search_email'] = $searchTerm;
            $params[':search_contact'] = $searchTerm;
        }

        if (!empty($filters['onboarding'])) {
            if ($filters['onboarding'] === 'Onboarded') {
                $sql .= " AND rl.LMS = 1";
            } elseif ($filters['onboarding'] === 'Not Onboarded') {
                $sql .= " AND (rl.LMS = 0 OR rl.LMS IS NULL)";
            }
        }

        // Add year and month filters
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(rl.created_at) = :year";
            $params[':year'] = (int)$filters['year'];
        }

        if (!empty($filters['month'])) {
            $sql .= " AND MONTH(rl.created_at) = :month";
            $params[':month'] = (int)$filters['month'];
        }

        // Add sorting
        $allowed_sort_columns = ['created_at', 'full_name', 'contact_number', 'email', 'recruiter_name', 'status', 'source', 'recruiter_team_id'];
        $allowed_sort_orders = ['ASC', 'DESC'];

        $sort_by = $_POST['sort_by'] ?? 'created_at';
        $sort_order = $_POST['sort_order'] ?? 'DESC';

        if (!in_array($sort_by, $allowed_sort_columns)) {
            $sort_by = 'created_at';
        }

        if (!in_array(strtoupper($sort_order), $allowed_sort_orders)) {
            $sort_order = 'DESC';
        }

        $sql .= " ORDER BY {$sort_by} " . strtoupper($sort_order);

        debugLog("Final SQL", $sql);
        debugLog("SQL Parameters", $params);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leads = $stmt->fetchAll();

        debugLog("Query executed successfully", "Found " . count($leads) . " leads");
        return [
            'success' => true,
            'data' => $leads,
            'debug' => [
                'sql' => $sql,
                'params' => $params,
                'filters' => $filters,
                'count' => count($leads)
            ]
        ];

    } catch (Exception $e) {
        debugLog("Query error", $e->getMessage());
        return ['success' => false, 'message' => 'Error fetching leads: ' . $e->getMessage()];
    }
}

/**
 * Adds a new recruitment lead to the database.
 * FIXED: Now properly handles admin recruiter/team selection
 */
function add_recruitment_lead(PDO $pdo, array $data)
{
    global $current_user_role, $current_user_id, $current_recruiter_name, $current_recruiter_id;

    debugLog("add_recruitment_lead called", $data);
    debugLog("Current user role", $current_user_role);

    try {
        // Validate required fields
        $required_fields = ['full_name', 'contact_number', 'status', 'source'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Required field '{$field}' is missing"];
            }
        }

        // Determine recruiter info based on user role
        $final_recruiter_name = '';
        $final_recruiter_id = null;
        $final_recruiter_team_id = null;

        if ($current_user_role === 'admin') {
            // Admin can select any recruiter
            if (!empty($data['recruiter_id'])) {
                $final_recruiter_id = $data['recruiter_id'];

                // Get recruiter name and team from database
                $stmt = $pdo->prepare("SELECT name, team_id FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $final_recruiter_id]);
                $recruiter_data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($recruiter_data) {
                    $final_recruiter_name = $recruiter_data['name'];
                    $final_recruiter_team_id = $recruiter_data['team_id'];
                } else {
                    return ['success' => false, 'message' => 'Selected recruiter not found'];
                }
            } else {
                return ['success' => false, 'message' => 'Recruiter selection is required for admin'];
            }
        } else {
            // Non-admin users use their own info
            $final_recruiter_name = $current_recruiter_name;
            $final_recruiter_id = $current_recruiter_id;

            // Get current user's team
            if ($current_user_id) {
                $stmt = $pdo->prepare("SELECT team_id FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $current_user_id]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user_data) {
                    $final_recruiter_team_id = $user_data['team_id'];
                }
            }
        }

        debugLog("Final recruiter info", [
            'name' => $final_recruiter_name,
            'id' => $final_recruiter_id,
            'team_id' => $final_recruiter_team_id
        ]);

        $sql = "INSERT INTO recruitment_leads (
                  created_at, full_name, contact_number, email, recruiter_name, recruiter_id, recruiter_team_id,
                  status, source, agent_onboarding_status, onboarding_status, remarks,
                  pre_assessment, accreditation, assessment, sales_training, site_tour, onboarding, digital_training, sales_training_materials, objection_handling, VAST, sales_monitoring, LMS, comm_structure, terminologies, focus_projects, habit_forming
              ) VALUES (
                  :created_at, :full_name, :contact_number, :email, :recruiter_name, :recruiter_id, :recruiter_team_id,
                  :status, :source, :agent_onboarding_status, :onboarding_status, :remarks,
                  :pre_assessment, :accreditation, :assessment, :sales_training, :site_tour, :onboarding, :digital_training, :sales_training_materials, :objection_handling, :VAST, :sales_monitoring, :LMS, :comm_structure, :terminologies, :focus_projects, :habit_forming
              )";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':created_at' => !empty($data['timestamp']) ? date('Y-m-d H:i:s', strtotime($data['timestamp'])) : date('Y-m-d H:i:s'),
            ':full_name' => $data['full_name'],
            ':contact_number' => $data['contact_number'],
            ':email' => $data['email'] ?? '',
            ':recruiter_name' => $final_recruiter_name,
            ':recruiter_id' => $final_recruiter_id,
            ':recruiter_team_id' => $final_recruiter_team_id,
            // ':recruiter_team_id' => $data['recruiter_team_id'],
            ':status' => $data['status'],
            ':source' => $data['source'],
            ':agent_onboarding_status' => $data['agent_onboarding_status'] ?? 0,
            ':onboarding_status' => $data['onboarding_status'] ?? 0,
            ':remarks' => $data['remarks'] ?? '',
            ':pre_assessment' => !empty($data['pre-assessment']) ? 1 : 0,
            ':accreditation' => !empty($data['accreditation']) ? 1 : 0,
            ':assessment' => !empty($data['assessment']) ? 1 : 0,
            ':sales_training' => !empty($data['sales_training']) ? 1 : 0,
            ':site_tour' => !empty($data['site_tour']) ? 1 : 0,
            ':onboarding' => !empty($data['onboarding']) ? 1 : 0,
            ':digital_training' => !empty($data['digital_training']) ? 1 : 0,
            ':sales_training_materials' => !empty($data['sales_training_materials']) ? 1 : 0,
            ':objection_handling' => !empty($data['objection_handling']) ? 1 : 0,
            ':VAST' => !empty($data['VAST']) ? 1 : 0,
            ':sales_monitoring' => !empty($data['sales_monitoring']) ? 1 : 0,
            ':LMS' => !empty($data['LMS']) ? 1 : 0,
            ':comm_structure' => !empty($data['comm_structure']) ? 1 : 0,
            ':terminologies' => !empty($data['terminologies']) ? 1 : 0,
            ':focus_projects' => !empty($data['focus_projects']) ? 1 : 0,
            ':habit_forming' => !empty($data['habit_forming']) ? 1 : 0
        ]);

        if ($result) {
            return ['success' => true, 'message' => 'Lead added successfully', 'id' => $pdo->lastInsertId()];
        } else {
            return ['success' => false, 'message' => 'Failed to add lead'];
        }

    } catch (Exception $e) {
        debugLog("Add lead error", $e->getMessage());
        return ['success' => false, 'message' => 'Error adding lead: ' . $e->getMessage()];
    }
}

/**
 * Updates an existing recruitment lead in the database.
 * FIXED: Now properly handles admin recruiter/team selection
 */
function update_recruitment_lead(PDO $pdo, array $data)
{
    global $current_user_role, $current_user_id;

    debugLog("update_recruitment_lead called", $data);
    debugLog("Current user role", $current_user_role);

    try {
        // Validate required fields
        $required_fields = ['id', 'full_name', 'contact_number', 'status', 'source'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Required field '{$field}' is missing"];
            }
        }

        // Get existing lead data
        $stmt = $pdo->prepare("SELECT recruiter_name, recruiter_id, recruiter_team_id FROM recruitment_leads WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $data['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            return ['success' => false, 'message' => 'Lead not found'];
        }

        // Determine recruiter info based on user role
        $final_recruiter_name = $existing['recruiter_name'];
        $final_recruiter_id = $existing['recruiter_id'];
        $final_recruiter_team_id = $existing['recruiter_team_id'];

        if ($current_user_role === 'admin') {
            // Admin can change recruiter if provided
            if (!empty($data['recruiter_id'])) {
                $final_recruiter_id = $data['recruiter_id'];

                // Get new recruiter name and team from database
                $stmt = $pdo->prepare("SELECT name, team_id FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $final_recruiter_id]);
                $recruiter_data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($recruiter_data) {
                    $final_recruiter_name = $recruiter_data['name'];
                    $final_recruiter_team_id = $recruiter_data['team_id'];
                } else {
                    return ['success' => false, 'message' => 'Selected recruiter not found'];
                }
            } elseif (!empty($data['source'])) {
                // Fallback: recruiter name is provided via 'source' field
                $stmt = $pdo->prepare("SELECT id, team_id FROM users WHERE name = :name LIMIT 1");
                $stmt->execute([':name' => $data['source']]);
                $recruiter_data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($recruiter_data) {
                    $final_recruiter_name = $data['source'];
                    $final_recruiter_id = $recruiter_data['id'];
                    $final_recruiter_team_id = $recruiter_data['team_id'];
                } else {
                    return ['success' => false, 'message' => 'Recruiter name provided but not found'];
                }
            }
        }
        // For non-admin users, keep existing recruiter info (no changes allowed)

        debugLog("Final recruiter info for update", [
            'name' => $final_recruiter_name,
            'id' => $final_recruiter_id,
            'team_id' => $final_recruiter_team_id
        ]);

        $sql = "UPDATE recruitment_leads SET 
                  created_at = :created_at,
                  full_name = :full_name,
                  contact_number = :contact_number,
                  email = :email,
                  recruiter_name = :recruiter_name,
                  recruiter_id = :recruiter_id,
                  recruiter_team_id = :recruiter_team_id,
                  status = :status,
                  source = :source,
                  agent_onboarding_status = :agent_onboarding_status,
                  onboarding_status = :onboarding_status,
                  remarks = :remarks,
                  pre_assessment = :pre_assessment,
                  accreditation = :accreditation,
                  assessment = :assessment,
                  sales_training = :sales_training,
                  site_tour = :site_tour,
                  onboarding = :onboarding,
                  digital_training = :digital_training,
                  sales_training_materials = :sales_training_materials,
                  objection_handling = :objection_handling,
                  VAST = :VAST,
                  sales_monitoring = :sales_monitoring,
                  LMS = :LMS,
                  comm_structure = :comm_structure,
                  terminologies = :terminologies,
                  focus_projects = :focus_projects,
                  habit_forming = :habit_forming,
                  updated_at = NOW()
              WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':created_at' => !empty($data['timestamp']) ? date('Y-m-d H:i:s', strtotime($data['timestamp'])) : date('Y-m-d H:i:s'),
            ':id' => $data['id'],
            ':full_name' => $data['full_name'],
            ':contact_number' => $data['contact_number'],
            ':email' => $data['email'] ?? '',
            ':recruiter_name' => $final_recruiter_name,
            ':recruiter_id' => $final_recruiter_id,
            ':recruiter_team_id' => $final_recruiter_team_id,
            ':status' => $data['status'],
            ':source' => $data['source'],
            ':agent_onboarding_status' => $data['agent_onboarding_status'] ?? null,
            ':onboarding_status' => $data['onboarding_status'] ?? null,
            ':remarks' => $data['remarks'] ?? '',
            ':pre_assessment' => !empty($data['pre-assessment']) ? 1 : 0,
            ':accreditation' => !empty($data['accreditation']) ? 1 : 0,
            ':assessment' => !empty($data['assessment']) ? 1 : 0,
            ':sales_training' => !empty($data['sales_training']) ? 1 : 0,
            ':site_tour' => !empty($data['site_tour']) ? 1 : 0,
            ':onboarding' => !empty($data['onboarding']) ? 1 : 0,
            ':digital_training' => !empty($data['digital_training']) ? 1 : 0,
            ':sales_training_materials' => !empty($data['sales_training_materials']) ? 1 : 0,
            ':objection_handling' => !empty($data['objection_handling']) ? 1 : 0,
            ':VAST' => !empty($data['VAST']) ? 1 : 0,
            ':sales_monitoring' => !empty($data['sales_monitoring']) ? 1 : 0,
            ':LMS' => !empty($data['LMS']) ? 1 : 0,
            ':comm_structure' => !empty($data['comm_structure']) ? 1 : 0,
            ':terminologies' => !empty($data['terminologies']) ? 1 : 0,
            ':focus_projects' => !empty($data['focus_projects']) ? 1 : 0,
            ':habit_forming' => !empty($data['habit_forming']) ? 1 : 0
        ]);

        if ($result) {
            $rowsAffected = $stmt->rowCount();
            if ($rowsAffected > 0) {
                return ['success' => true, 'message' => 'Lead updated successfully'];
            } else {
                return ['success' => false, 'message' => 'No changes made or lead not found'];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to update lead'];
        }

    } catch (Exception $e) {
        debugLog("Update lead error", $e->getMessage());
        return ['success' => false, 'message' => 'Error updating lead: ' . $e->getMessage()];
    }
}

/**
 * Deletes a recruitment lead from the database.
 */
function delete_recruitment_lead(PDO $pdo, int $id)
{
    debugLog("delete_recruitment_lead called", ['id' => $id]);
    try {
        if (!$id || !is_numeric($id)) {
            return ['success' => false, 'message' => 'Invalid ID'];
        }

        $sql = "DELETE FROM recruitment_leads WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([':id' => $id]);

        if ($result) {
            $rowsAffected = $stmt->rowCount();
            if ($rowsAffected > 0) {
                return ['success' => true, 'message' => 'Lead deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Lead not found'];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to delete lead'];
        }

    } catch (Exception $e) {
        debugLog("Delete lead error", $e->getMessage());
        return ['success' => false, 'message' => 'Error deleting lead: ' . $e->getMessage()];
    }
}

// Main request handling logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    debugLog("Action received", $_POST['action']);

    switch ($_POST['action']) {
        case 'get_recruitment_stats':
            try {
                $stats = [];

                // Total agents
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM recruitment_leads");
                $result = $stmt->fetch();
                $stats['total_agents'] = $result ? (int) $result['total'] : 0;

                // Active agents
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM recruitment_leads WHERE status = 'Active'");
                $result = $stmt->fetch();
                $stats['active_agents'] = $result ? (int) $result['count'] : 0;

                // Inactive agents
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM recruitment_leads WHERE status = 'Inactive'");
                $result = $stmt->fetch();
                $stats['inactive_agents'] = $result ? (int) $result['count'] : 0;

                // Onboarded agents (those with LMS = 1 or onboarding_status = 1)
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM recruitment_leads WHERE (LMS = 1 OR onboarding_status = 1)");
                $result = $stmt->fetch();
                $stats['onboarded_agents'] = $result ? (int) $result['count'] : 0;

                debugLog("Stats loaded successfully", $stats);
                echo json_encode(['success' => true, 'stats' => $stats]);

            } catch (Exception $e) {
                debugLog("Stats error", $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error loading statistics: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_recruitment_stats_with_filters':
            try {
                // Parse filters
                $filters = [];
                if (isset($_POST['filters']) && !empty($_POST['filters'])) {
                    $filters = json_decode($_POST['filters'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        debugLog("JSON decode error for filters", json_last_error_msg());
                        $filters = [];
                    }
                }
                
                debugLog("Stats filters received", $filters);
                
                $stats = [];
                
                // Build base SQL with filters
                $where_conditions = [];
                $params = [];
                
                if (!empty($filters['status'])) {
                    $where_conditions[] = 'rl.status = :filter_status';
                    $params[':filter_status'] = $filters['status'];
                }
                
                if (!empty($filters['team'])) {
                    $where_conditions[] = 'rl.recruiter_team_id = :team';
                    $params[':team'] = $filters['team'];
                }
                
                if (isset($filters['onboardStatus']) && $filters['onboardStatus'] !== '') {
                    $where_conditions[] = 'rl.onboarding_status = :onboardStatus';
                    $params[':onboardStatus'] = (int)$filters['onboardStatus'];
                }
                
                if (!empty($filters['search'])) {
                    $searchTerm = '%' . $filters['search'] . '%';
                    $where_conditions[] = '(rl.full_name LIKE :search_name OR rl.email LIKE :search_email OR rl.contact_number LIKE :search_contact)';
                    $params[':search_name'] = $searchTerm;
                    $params[':search_email'] = $searchTerm;
                    $params[':search_contact'] = $searchTerm;
                }
                
                if (!empty($filters['year'])) {
                    $where_conditions[] = 'YEAR(rl.created_at) = :year';
                    $params[':year'] = (int)$filters['year'];
                }
                
                if (!empty($filters['month'])) {
                    $where_conditions[] = 'MONTH(rl.created_at) = :month';
                    $params[':month'] = (int)$filters['month'];
                }
                
                $where_clause = '';
                if (!empty($where_conditions)) {
                    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
                }
                
                // Total recruited agents
                $sql = "SELECT COUNT(*) as total FROM recruitment_leads rl {$where_clause}";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $result = $stmt->fetch();
                $stats['total_recruited'] = $result ? (int) $result['total'] : 0;
                
                // Active agents
                $active_where = $where_clause ? $where_clause . ' AND rl.status = \'Active\'' : 'WHERE rl.status = \'Active\'';
                $sql = "SELECT COUNT(*) as count FROM recruitment_leads rl {$active_where}";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $result = $stmt->fetch();
                $stats['active_agents'] = $result ? (int) $result['count'] : 0;
                
                // Inactive agents
                $inactive_where = $where_clause ? $where_clause . ' AND rl.status = \'Inactive\'' : 'WHERE rl.status = \'Inactive\'';
                $sql = "SELECT COUNT(*) as count FROM recruitment_leads rl {$inactive_where}";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $result = $stmt->fetch();
                $stats['inactive_agents'] = $result ? (int) $result['count'] : 0;
                
                // Onboarded agents
                $onboarded_where = $where_clause ? $where_clause . ' AND (rl.LMS = 1 OR rl.onboarding_status = 1)' : 'WHERE (rl.LMS = 1 OR rl.onboarding_status = 1)';
                $sql = "SELECT COUNT(*) as count FROM recruitment_leads rl {$onboarded_where}";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $result = $stmt->fetch();
                $stats['onboarded_agents'] = $result ? (int) $result['count'] : 0;
                
                debugLog("Filtered stats loaded successfully", $stats);
                echo json_encode(['success' => true, 'stats' => $stats]);
                
            } catch (Exception $e) {
                debugLog("Filtered stats error", $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error loading filtered statistics: ' . $e->getMessage()]);
            }
            break;

        case 'get_recruitment_leads':
            try {
                // Parse filters
                $filters = [];
                if (isset($_POST['filters']) && !empty($_POST['filters'])) {
                    $filters = json_decode($_POST['filters'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        debugLog("JSON decode error for filters", json_last_error_msg());
                        $filters = [];
                    }
                }

                debugLog("Filters received", $filters);

                // Restrict data for managers
                if ($current_user_role === 'manager' && $current_user_id) {
                    $filters['recruiter_id'] = $current_user_id;
                }

                $result = get_recruitment_leads($pdo, $filters);
                echo json_encode($result);

            } catch (Exception $e) {
                debugLog("Query error", $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error fetching leads: ' . $e->getMessage()]);
            }
            break;

        case 'get_lead':
            try {
                $id = $_POST['id'] ?? null;
                if (!$id || !is_numeric($id)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid or missing ID']);
                    break;
                }

                debugLog("get_lead called", ['id' => $id]);

                // Use the existing function with ID filter
                $filters = ['id' => $id];
                $result = get_recruitment_leads($pdo, $filters);
                
                if ($result['success'] && !empty($result['data'])) {
                    echo json_encode($result);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Lead not found']);
                }

            } catch (Exception $e) {
                debugLog("Get lead error", $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error fetching lead: ' . $e->getMessage()]);
            }
            break;

        case 'add_recruitment_lead':
            // FIXED: Remove the override parameters, let the function handle role-based logic
            $result = add_recruitment_lead($pdo, $_POST);
            echo json_encode($result);
            break;

        case 'update_recruitment_lead':
            // FIXED: Remove the override parameters, let the function handle role-based logic
            $result = update_recruitment_lead($pdo, $_POST);
            echo json_encode($result);
            break;

        case 'delete_recruitment_lead':
            $id = $_POST['id'] ?? null;
            $result = delete_recruitment_lead($pdo, (int) $id);
            echo json_encode($result);
            break;

        case 'onboard_agent':
            // Collect and sanitize input
            $name = $_POST['name'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $username = $_POST['username'];
            $team_id = $_POST['team_id'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $_POST['role'];

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'This agent is already onboarded.']);
                exit;
            }

            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (team_id, username, password, name, email, phone, role, is_active, position) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'Agent')");
            $success = $stmt->execute([$team_id, $username, $password, $name, $email, $phone, $role]);

            if ($success) {
                // Update both agent_onboarding_status and onboarding_status to 1 in recruitment_leads table
                // First check for duplicates
                $checkStmt = $pdo->prepare("SELECT id, full_name, email, contact_number, onboarding_status FROM recruitment_leads WHERE email = ? OR contact_number = ?");
                $checkStmt->execute([$email, $phone]);
                $duplicates = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Found " . count($duplicates) . " records for Email: $email, Phone: $phone");
                foreach ($duplicates as $dup) {
                    error_log("Record ID: {$dup['id']}, Name: {$dup['full_name']}, Email: {$dup['email']}, Phone: {$dup['contact_number']}, Current onboarding_status: {$dup['onboarding_status']}");
                }
                
                // Update all matching records to be onboarded
                $updateStmt = $pdo->prepare("UPDATE recruitment_leads SET onboarding_status = 1 WHERE email = ? OR contact_number = ?");
                $updateResult = $updateStmt->execute([$email, $phone]);
                $rowsAffected = $updateStmt->rowCount();
                
                // Debug logging
                error_log("Onboard update - Email: $email, Phone: $phone, Rows affected: $rowsAffected, Update result: " . ($updateResult ? 'true' : 'false'));
                
                echo json_encode(['success' => true, 'debug' => ['rows_affected' => $rowsAffected, 'email' => $email, 'phone' => $phone]]);
            } else {
                $errorInfo = $stmt->errorInfo();
                echo json_encode(['success' => false, 'message' => 'Failed to insert user', 'error' => $errorInfo]);
            }
            exit;
            break;

        case 'check_username_exists':
            $username = $_POST['username'] ?? '';

            if (!$username) {
                echo json_encode(['success' => false, 'message' => 'No username provided']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $exists = $stmt->fetchColumn() > 0;

            echo json_encode(['success' => true, 'exists' => $exists]);
            exit;

        case 'delete_agent_by_username':
            $username = $_POST['username'] ?? '';
            if (!$username) {
                echo json_encode(['success' => false, 'message' => 'No username provided']);
                exit;
            }

            // 1) Fetch email and phone before deleting the user
            $infoStmt = $pdo->prepare("SELECT email, phone FROM users WHERE username = ? LIMIT 1");
            $infoStmt->execute([$username]);
            $userInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);

            // Read optional overrides from POST (from the front-end modal)
            $postedEmail = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
            $postedPhone = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
            $postedLeadId = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : null;

            // 2) Delete the user record
            $delStmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
            $deleted = $delStmt->execute([$username]);

            // 3) If we have email/phone, reset onboarding flags on matching recruitment leads
            $updatedRows = 0;
            // Determine best-available identifiers: prefer POSTed values, fallback to user's stored values
            $email = $postedEmail !== '' ? $postedEmail : ($userInfo['email'] ?? null);
            $phone = $postedPhone !== '' ? $postedPhone : ($userInfo['phone'] ?? null);

            // Build dynamic WHERE clauses
            $whereParts = [];
            $params = [];
            if (!empty($postedLeadId)) {
                $whereParts[] = 'id = :lead_id';
                $params[':lead_id'] = $postedLeadId;
            }
            if (!empty($email)) {
                $whereParts[] = 'email = :email';
                $params[':email'] = $email;
            }
            if (!empty($phone)) {
                $whereParts[] = 'contact_number = :phone';
                $params[':phone'] = $phone;
            }

            if (!empty($whereParts)) {
                // Build normalized WHERE for email/phone to handle case/format differences
                $normalizedWhere = [];
                $normalizedParams = [];

                foreach ($whereParts as $part) {
                    if ($part === 'id = :lead_id') {
                        $normalizedWhere[] = $part;
                        $normalizedParams[':lead_id'] = $params[':lead_id'];
                    } elseif ($part === 'email = :email') {
                        $normalizedWhere[] = 'LOWER(email) = LOWER(:email)';
                        $normalizedParams[':email'] = $params[':email'];
                    } elseif ($part === 'contact_number = :phone') {
                        // Strip spaces, dashes, and plus signs for comparison
                        $normalizedWhere[] = "REPLACE(REPLACE(REPLACE(contact_number, '-', ''), ' ', ''), '+', '') = REPLACE(REPLACE(REPLACE(:phone, '-', ''), ' ', ''), '+', '')";
                        $normalizedParams[':phone'] = $params[':phone'];
                    }
                }

                $sql = 'UPDATE recruitment_leads SET onboarding_status = 0, agent_onboarding_status = 0 WHERE ' . implode(' OR ', $normalizedWhere);
                $updStmt = $pdo->prepare($sql);
                $updStmt->execute($normalizedParams);
                $updatedRows = $updStmt->rowCount();
            }

            echo json_encode([
                'success' => (bool)$deleted,
                'updated_leads' => $updatedRows
            ]);
            exit;

        case 'get_team_status_summary':
            debugLog("get_team_status_summary called");
            
            $filters = [];
            if (isset($_POST['filters']) && !empty($_POST['filters'])) {
                $filters = json_decode($_POST['filters'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $filters = [];
                }
            }
            debugLog("Filters received", $filters);

            // First, let's check if there's any data in recruitment_leads
            $check_sql = "SELECT COUNT(*) as total, 
                                 SUM(CASE WHEN onboarding_status = 1 THEN 1 ELSE 0 END) as active_count,
                                 SUM(CASE WHEN onboarding_status = 0 OR onboarding_status IS NULL THEN 1 ELSE 0 END) as inactive_count
                          FROM recruitment_leads";
            $check_stmt = $pdo->query($check_sql);
            $data_check = $check_stmt->fetch(PDO::FETCH_ASSOC);
            debugLog("Data check", $data_check);

            // Build date filters
            $where_conditions = [];
            $params = [];
            
            if (!empty($filters['year'])) {
                $where_conditions[] = 'YEAR(rl.created_at) = ?';
                $params[] = $filters['year'];
            }
            if (!empty($filters['month'])) {
                $where_conditions[] = 'MONTH(rl.created_at) = ?';
                $params[] = $filters['month'];
            }
            if (!empty($filters['quarter'])) {
                $where_conditions[] = 'QUARTER(rl.created_at) = ?';
                $params[] = $filters['quarter'];
            }
            
            // Fix the WHERE clause positioning - use onboarding_status for Active/Inactive
            $where_sql = '';
            if (!empty($where_conditions)) {
                $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
            }
            
            $sql = "SELECT 
                        COALESCE(t.id, 0) as team_id, 
                        COALESCE(t.name, 'No Team') as team_name, 
                        CASE WHEN rl.onboarding_status = 1 THEN 'Active' ELSE 'Inactive' END as status, 
                        COUNT(*) as count
                    FROM recruitment_leads rl
                    LEFT JOIN teams t ON rl.recruiter_team_id = t.id
                    $where_sql
                    GROUP BY t.id, t.name, CASE WHEN rl.onboarding_status = 1 THEN 'Active' ELSE 'Inactive' END
                    ORDER BY t.name, status";
            
            debugLog("SQL Query", $sql);
            debugLog("SQL Parameters", $params);

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                debugLog("Raw query results", $results);

                if (empty($results)) {
                    // If no results, create dummy data for all teams
                    $all_teams_stmt = $pdo->query('SELECT id, name FROM teams ORDER BY name');
                    $all_teams = $all_teams_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $output = [];
                    foreach ($all_teams as $team) {
                        $output[] = [
                            'team_id' => $team['id'],
                            'team_name' => $team['name'],
                            'status' => 'Active',
                            'count' => 0
                        ];
                        $output[] = [
                            'team_id' => $team['id'],
                            'team_name' => $team['name'],
                            'status' => 'Inactive',
                            'count' => 0
                        ];
                    }
                } else {
                    // Process results and fill in missing combinations
                    $all_teams_stmt = $pdo->query('SELECT id, name FROM teams ORDER BY name');
                    $all_teams = $all_teams_stmt->fetchAll(PDO::FETCH_ASSOC);
                    $output = [];
                    
                    foreach ($all_teams as $team) {
                        foreach (['Active', 'Inactive'] as $status) {
                            $found = false;
                            foreach ($results as $row) {
                                if ($row['team_id'] == $team['id'] && $row['status'] === $status) {
                                    $output[] = [
                                        'team_id' => $team['id'],
                                        'team_name' => $team['name'],
                                        'status' => $status,
                                        'count' => (int) $row['count']
                                    ];
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $output[] = [
                                    'team_id' => $team['id'],
                                    'team_name' => $team['name'],
                                    'status' => $status,
                                    'count' => 0
                                ];
                            }
                        }
                    }
                }
                
                debugLog("Final output", $output);
                echo json_encode(['success' => true, 'data' => $output, 'debug' => ['sql' => $sql, 'params' => $params, 'raw_results' => $results]]);
                
            } catch (Exception $e) {
                debugLog("SQL Error", $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => ['sql' => $sql, 'params' => $params]]);
            }
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method or missing action']);
}
?>