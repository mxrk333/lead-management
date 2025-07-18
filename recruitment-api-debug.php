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

// --- Start of new code for automatic recruiter name and id ---
$current_recruiter_name = '';
$current_recruiter_id = null;
if (isset($_SESSION['user_id']) && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $current_recruiter_name = $user_data['name'];
            $current_recruiter_id = $user_data['id'];
        }
    } catch (Exception $e) {
        error_log("Error fetching recruiter info from session in debug API: " . $e->getMessage());
        $current_recruiter_name = '';
        $current_recruiter_id = null;
    }
}
// --- End of new code ---

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0); // Exit immediately for OPTIONS requests
}

// Debug: Log all incoming data
debugLog("Request Method", $_SERVER['REQUEST_METHOD']);
debugLog("POST data", $_POST);

// Check if PDO connection exists
if (!isset($pdo) || $pdo === null) {
    debugLog("Database connection failed: PDO object is null or not set.");
    echo json_encode(['success' => false, 'message' => 'Database connection not available. Please check server logs.']);
    exit; // Exit if no database connection
}

/**
 * Fetches recruitment leads based on filters and sorting.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param array $filters An associative array of filters.
 * @return array An associative array containing success status, data, and debug info.
 */
function get_recruitment_leads(PDO $pdo, array $filters = [])
{
    debugLog("get_recruitment_leads called", $filters);

    try {
        // Build base query
        $sql = "SELECT id, timestamp, full_name, contact_number, email, recruiter_name, status, source, agent_onboarding_status, remarks, created_at, updated_at, recruiter_id, recruiter_team_id, pre_assessment, accreditation, assessment, sales_training, site_tour, onboarding, habit_forming, digital_training, sales_training_materials, objection_handling, VAST, sales_monitoring, LMS, comm_structure, terminologies, focus_projects FROM recruitment_leads WHERE 1=1";
        $params = [];

        // Add filter conditions
        if (!empty($filters['id'])) {
            $sql .= " AND id = :id";
            $params[':id'] = $filters['id'];
            debugLog("Added ID filter", $filters['id']);
        }

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
            debugLog("Added status filter", $filters['status']);
        }

        if (!empty($filters['source'])) {
            $sql .= " AND source = :source";
            $params[':source'] = $filters['source'];
            debugLog("Added source filter", $filters['source']);
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%'; // Define search term once
            $sql .= " AND (full_name LIKE :search_name OR email LIKE :search_email OR contact_number LIKE :search_contact)";
            $params[':search_name'] = $searchTerm;
            $params[':search_email'] = $searchTerm;
            $params[':search_contact'] = $searchTerm;
            debugLog("Added search filter", $filters['search']);
        }

        // Add sorting (with whitelisting for security)
        $allowed_sort_columns = ['created_at', 'full_name', 'contact_number', 'email', 'recruiter_name', 'status', 'source'];
        $allowed_sort_orders = ['ASC', 'DESC'];

        $sort_by = $_POST['sort_by'] ?? 'created_at';
        $sort_order = $_POST['sort_order'] ?? 'DESC';

        // Validate sort_by column
        if (!in_array($sort_by, $allowed_sort_columns)) {
            $sort_by = 'created_at'; // Default to a safe column
            debugLog("Invalid sort_by column, defaulting to created_at", $_POST['sort_by']);
        }

        // Validate sort_order
        if (!in_array(strtoupper($sort_order), $allowed_sort_orders)) {
            $sort_order = 'DESC'; // Default to a safe order
            debugLog("Invalid sort_order, defaulting to DESC", $_POST['sort_order']);
        }

        $sql .= " ORDER BY {$sort_by} " . strtoupper($sort_order);

        debugLog("Final SQL", $sql);
        debugLog("SQL Parameters", $params);

        // Execute query
        $stmt = $pdo->prepare($sql); // Line 123
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
 *
 * @param PDO $pdo The PDO database connection object.
 * @param array $data An associative array of lead data.
 * @param string $recruiter_name_override Optional: Override recruiter name from session.
 * @return array An associative array containing success status and message.
 */
function add_recruitment_lead(PDO $pdo, array $data, string $recruiter_name_override = '', $recruiter_id_override = null)
{
    debugLog("add_recruitment_lead called", $data);
    try {
        // Validate required fields
        $required_fields = ['full_name', 'contact_number', 'status', 'source'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Required field '{$field}' is missing"];
            }
        }

        // Use override if provided, otherwise use data['recruiter_name'] (which will be ignored from POST)
        $final_recruiter_name = $recruiter_name_override ?: ($data['recruiter_name'] ?? '');
        $final_recruiter_id = $recruiter_id_override ?: null;
        $final_recruiter_team_id = null;

        // Fetch the recruiter's team_id at insert time
        if ($final_recruiter_id) {
            $stmt = $pdo->prepare("SELECT team_id FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $final_recruiter_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['team_id']) {
                $final_recruiter_team_id = $row['team_id'];
            }
        }

        $sql = "INSERT INTO recruitment_leads (
                  full_name, contact_number, email, recruiter_name, recruiter_id, recruiter_team_id,
                  status, source, agent_onboarding_status, remarks,
                  pre_assessment, accreditation, assessment, sales_training, site_tour, onboarding, digital_training, sales_training_materials, objection_handling, VAST, sales_monitoring, LMS, comm_structure, terminologies, focus_projects, habit_forming
              ) VALUES (
                  :full_name, :contact_number, :email, :recruiter_name, :recruiter_id, :recruiter_team_id,
                  :status, :source, :agent_onboarding_status, :remarks,
                  :pre_assessment, :accreditation, :assessment, :sales_training, :site_tour, :onboarding, :digital_training, :sales_training_materials, :objection_handling, :VAST, :sales_monitoring, :LMS, :comm_structure, :terminologies, :focus_projects, :habit_forming
              )";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':full_name' => $data['full_name'],
            ':contact_number' => $data['contact_number'],
            ':email' => $data['email'] ?? '',
            ':recruiter_name' => $final_recruiter_name, // Use the determined recruiter name
            ':recruiter_id' => $final_recruiter_id,
            ':recruiter_team_id' => $final_recruiter_team_id,
            ':status' => $data['status'],
            ':source' => $data['source'],
            ':agent_onboarding_status' => $data['agent_onboarding_status'] ?? null,
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
 *
 * @param PDO $pdo The PDO database connection object.
 * @param array $data An associative array of lead data including 'id'.
 * @param string $recruiter_name_override Optional: Override recruiter name from session.
 * @return array An associative array containing success status and message.
 */
function update_recruitment_lead(PDO $pdo, array $data, string $recruiter_name_override = '', $recruiter_id_override = null)
{
    debugLog("update_recruitment_lead called", $data);
    try {
        // Validate required fields
        $required_fields = ['id', 'full_name', 'contact_number', 'status', 'source'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Required field '{$field}' is missing"];
            }
        }

        // Use override if provided, otherwise use data['recruiter_name'] (which will be ignored from POST)
        $final_recruiter_name = $recruiter_name_override ?: ($data['recruiter_name'] ?? '');
        $final_recruiter_id = $recruiter_id_override ?: null;

        // Ensure recruiter_team_id is set
        $final_recruiter_team_id = $data['recruiter_team_id'] ?? null;
        if (!$final_recruiter_team_id && $final_recruiter_id) {
            $stmt = $pdo->prepare("SELECT team_id FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $final_recruiter_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['team_id']) {
                $final_recruiter_team_id = $row['team_id'];
            }
        }

        $sql = "UPDATE recruitment_leads SET 
                  full_name = :full_name,
                  contact_number = :contact_number,
                  email = :email,
                  recruiter_name = :recruiter_name,
                  recruiter_id = :recruiter_id,
                  recruiter_team_id = :recruiter_team_id,
                  status = :status,
                  source = :source,
                  agent_onboarding_status = :agent_onboarding_status,
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
            ':id' => $data['id'],
            ':full_name' => $data['full_name'],
            ':contact_number' => $data['contact_number'],
            ':email' => $data['email'] ?? '',
            ':recruiter_name' => $final_recruiter_name, // Use the determined recruiter name
            ':recruiter_id' => $final_recruiter_id,
            ':recruiter_team_id' => $final_recruiter_team_id,
            ':status' => $data['status'],
            ':source' => $data['source'],
            ':agent_onboarding_status' => $data['agent_onboarding_status'] ?? null,
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
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $id The ID of the lead to delete.
 * @return array An associative array containing success status and message.
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

                // Total leads
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM recruitment_leads");
                $result = $stmt->fetch();
                $stats['total_leads'] = $result ? (int) $result['total'] : 0;

                // Recent leads (last 7 days)
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM recruitment_leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                $result = $stmt->fetch();
                $stats['recent_leads'] = $result ? (int) $result['count'] : 0;

                debugLog("Stats loaded successfully", $stats);
                echo json_encode(['success' => true, 'data' => $stats]);

            } catch (Exception $e) {
                debugLog("Stats error", $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error loading statistics: ' . $e->getMessage()]);
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
                        $filters = []; // Reset if JSON decoding fails
                    }
                }

                debugLog("Filters received", $filters);

                // Pass the $pdo object to the function
                $result = get_recruitment_leads($pdo, $filters);
                echo json_encode($result);

            } catch (Exception $e) {
                debugLog("Query error", $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error fetching leads: ' . $e->getMessage()]);
            }
            break;

        case 'add_recruitment_lead':
            // Pass the $pdo object and $_POST data to the function
            $result = add_recruitment_lead($pdo, $_POST, $current_recruiter_name, $current_recruiter_id);
            echo json_encode($result);
            break;

        case 'update_recruitment_lead':
            // Pass the $pdo object and $_POST data to the function
            $result = update_recruitment_lead($pdo, $_POST, $current_recruiter_name, $current_recruiter_id);
            echo json_encode($result);
            break;

        case 'delete_recruitment_lead':
            // Pass the $pdo object and the ID to the function
            $id = $_POST['id'] ?? null;
            $result = delete_recruitment_lead($pdo, (int) $id); // Cast to int for type hint
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method or missing action']);
}
?>