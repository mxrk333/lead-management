<?php
session_start();

// Include PDO database connection
require_once 'config/pdo-database.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if PDO connection exists
if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'PDO database connection not available']);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    switch ($_POST['action']) {
        case 'get_recruitment_stats':
            try {
                $stats = [];
                
                // Total leads
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM recruitment_leads");
                $result = $stmt->fetch();
                $stats['total_leads'] = $result ? (int)$result['total'] : 0;
                
                // Leads by interest level
                $stmt = $pdo->query("SELECT interest_level, COUNT(*) as count FROM recruitment_leads GROUP BY interest_level");
                $stats['by_interest_level'] = $stmt->fetchAll();
                
                // Recent leads (last 7 days)
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM recruitment_leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                $result = $stmt->fetch();
                $stats['recent_leads'] = $result ? (int)$result['count'] : 0;
                
                echo json_encode(['success' => true, 'data' => $stats]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_recruitment_leads':
            try {
                // Parse filters
                $filters = [];
                if (isset($_POST['filters']) && !empty($_POST['filters'])) {
                    $filters = json_decode($_POST['filters'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $filters = []; // Reset if JSON decoding fails
                    }
                }
                
                // Build base query
                $sql = "SELECT * FROM recruitment_leads WHERE 1=1";
                $params = [];
                
                // Add filter conditions
                if (!empty($filters['id'])) {
                    $sql .= " AND id = :id";
                    $params[':id'] = $filters['id'];
                }
                
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
                    $searchTerm = '%' . $filters['search'] . '%';
                    $sql .= " AND (full_name LIKE :search_name OR email LIKE :search_email OR contact_number LIKE :search_contact)";
                    $params[':search_name'] = $searchTerm;
                    $params[':search_email'] = $searchTerm;
                    $params[':search_contact'] = $searchTerm;
                }
                
                // Add sorting (with whitelisting for security)
                $allowed_sort_columns = ['created_at', 'full_name', 'contact_number', 'email', 'recruiter_name', 'interest_level', 'status', 'source'];
                $allowed_sort_orders = ['ASC', 'DESC'];

                $sort_by = $_POST['sort_by'] ?? 'created_at';
                $sort_order = $_POST['sort_order'] ?? 'DESC';

                // Validate sort_by column
                if (!in_array($sort_by, $allowed_sort_columns)) {
                    $sort_by = 'created_at'; // Default to a safe column
                }

                // Validate sort_order
                if (!in_array(strtoupper($sort_order), $allowed_sort_orders)) {
                    $sort_order = 'DESC'; // Default to a safe order
                }

                $sql .= " ORDER BY {$sort_by} " . strtoupper($sort_order);
                
                // Execute query
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $leads = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'data' => $leads, 'debug' => [
                    'sql' => $sql,
                    'params' => $params,
                    'filters' => $filters,
                    'count' => count($leads)
                ]]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'add_recruitment_lead':
            try {
                // Validate required fields
                $required_fields = ['full_name', 'contact_number', 'interest_level', 'status', 'source'];
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        echo json_encode(['success' => false, 'message' => "Required field '{$field}' is missing"]);
                        exit;
                    }
                }
                
                $sql = "INSERT INTO recruitment_leads (
                            full_name, contact_number, email, recruiter_name, 
                            interest_level, status, source, agent_onboarding_status, remarks
                        ) VALUES (
                            :full_name, :contact_number, :email, :recruiter_name, 
                            :interest_level, :status, :source, :agent_onboarding_status, :remarks
                        )";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    ':full_name' => $_POST['full_name'],
                    ':contact_number' => $_POST['contact_number'],
                    ':email' => $_POST['email'] ?? '',
                    ':recruiter_name' => $_POST['recruiter_name'] ?? '',
                    ':interest_level' => $_POST['interest_level'],
                    ':status' => $_POST['status'],
                    ':source' => $_POST['source'],
                    ':agent_onboarding_status' => $_POST['agent_onboarding_status'] ?? null,
                    ':remarks' => $_POST['remarks'] ?? ''
                ]);
                
                if ($result) {
                    $rowsAffected = $stmt->rowCount(); // Check rows affected for INSERT
                    echo json_encode(['success' => true, 'message' => 'Lead added successfully', 'id' => $pdo->lastInsertId()]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add lead']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'update_recruitment_lead':
            try {
                // Validate required fields
                $required_fields = ['id', 'full_name', 'contact_number', 'interest_level', 'status', 'source'];
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        echo json_encode(['success' => false, 'message' => "Required field '{$field}' is missing"]);
                        exit;
                    }
                }
                
                $sql = "UPDATE recruitment_leads SET 
                            full_name = :full_name,
                            contact_number = :contact_number,
                            email = :email,
                            recruiter_name = :recruiter_name,
                            interest_level = :interest_level,
                            status = :status,
                            source = :source,
                            agent_onboarding_status = :agent_onboarding_status,
                            remarks = :remarks,
                            updated_at = NOW()
                        WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    ':id' => $_POST['id'],
                    ':full_name' => $_POST['full_name'],
                    ':contact_number' => $_POST['contact_number'],
                    ':email' => $_POST['email'] ?? '',
                    ':recruiter_name' => $_POST['recruiter_name'] ?? '',
                    ':interest_level' => $_POST['interest_level'],
                    ':status' => $_POST['status'],
                    ':source' => $_POST['source'],
                    ':agent_onboarding_status' => $_POST['agent_onboarding_status'] ?? null,
                    ':remarks' => $_POST['remarks'] ?? ''
                ]);
                
                if ($result) {
                    $rowsAffected = $stmt->rowCount();
                    if ($rowsAffected > 0) {
                        echo json_encode(['success' => true, 'message' => 'Lead updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'No changes made or lead not found']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update lead']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        case 'delete_recruitment_lead':
            try {
                $id = $_POST['id'] ?? null;
                if (!$id || !is_numeric($id)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                    exit;
                }
                
                $sql = "DELETE FROM recruitment_leads WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([':id' => $id]);
                
                if ($result) {
                    $rowsAffected = $stmt->rowCount();
                    if ($rowsAffected > 0) {
                        echo json_encode(['success' => true, 'message' => 'Lead deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Lead not found']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete lead']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method or missing action']);
}
?>
