<?php
require_once 'config/pdo-database.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // Check record ID 28
    $stmt = $pdo->prepare("SELECT * FROM recruitment_leads WHERE id = 28");
    $stmt->execute();
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check all records with the same email/phone
    $duplicatesStmt = $pdo->prepare("
        SELECT id, full_name, email, contact_number, onboarding_status, created_at, updated_at
        FROM recruitment_leads 
        WHERE email = :email 
        OR contact_number = :phone
        ORDER BY created_at DESC
    ");
    $duplicatesStmt->execute([
        ':email' => $record['email'],
        ':phone' => $record['contact_number']
    ]);
    $duplicates = $duplicatesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Test both filter conditions
    $onboardedStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM recruitment_leads
        WHERE id = 28
        AND onboarding_status = 1
    ");
    $onboardedStmt->execute();
    $onboardedCount = $onboardedStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $notOnboardedStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM recruitment_leads
        WHERE id = 28
        AND (onboarding_status = 0 OR onboarding_status IS NULL)
    ");
    $notOnboardedStmt->execute();
    $notOnboardedCount = $notOnboardedStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'record' => $record,
        'duplicates' => $duplicates,
        'filter_tests' => [
            'matches_onboarded_filter' => $onboardedCount > 0,
            'matches_not_onboarded_filter' => $notOnboardedCount > 0,
            'onboarded_count' => $onboardedCount,
            'not_onboarded_count' => $notOnboardedCount
        ],
        'raw_value' => [
            'onboarding_status' => $record['onboarding_status'],
            'type' => gettype($record['onboarding_status']),
            'as_int' => intval($record['onboarding_status']),
            'as_bool' => (bool)$record['onboarding_status']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}