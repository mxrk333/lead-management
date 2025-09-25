<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $sources = [];
    
    // First try to get ENUM values from the leads table
    $stmt = $conn->prepare("SHOW COLUMNS FROM leads WHERE Field = 'source'");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && preg_match("/^enum\('(.*)'\)$/", $row['Type'], $matches)) {
            $values = explode("','", $matches[1]);
            foreach ($values as $value) {
                $sources[] = [
                    'id' => $value,
                    'name' => $value,
                    'type' => 'database'
                ];
            }
        }
        $stmt->close();
    }
    
    // If no sources found from database, provide default values
    if (empty($sources)) {
        $defaultSources = [
            'Facebook Groups', 'KKK', 'Facebook Ads', 'TikTok ads', 'Google Ads', 
            'Facebook live', 'Referral', 'Teleprospecting', 'Video Message', 
            'Organic Posting', 'Email Marketing', 'Follow up', 'Manning', 
            'Walk in', 'Flyering', 'Chat messaging', 'Property Listing', 
            'Landing Page', 'Networking Events', 'Organic Sharing', 
            'Youtube Marketing', 'LinkedIn', 'Open House', 'Facebook Page', 'OFW'
        ];
        
        foreach ($defaultSources as $source) {
            $sources[] = [
                'id' => $source,
                'name' => $source,
                'type' => 'default'
            ];
        }
    }
    
    // Get search query if provided
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Filter sources based on search query
    if (!empty($search)) {
        $sources = array_filter($sources, function($source) use ($search) {
            return stripos($source['name'], $search) !== false;
        });
        $sources = array_values($sources); // Re-index array
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'sources' => $sources,
        'total' => count($sources),
        'search_term' => $search
    ]);

} catch (Exception $e) {
    error_log("Error in get_lead_sources.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'sources' => []
    ]);
}
?>