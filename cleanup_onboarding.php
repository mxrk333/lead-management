<?php
require_once 'config/pdo-database.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

function cleanupOnboardingStatus($pdo) {
    try {
        // First, fix any NULL values
        $fixNullSql = "UPDATE recruitment_leads SET onboarding_status = 0 WHERE onboarding_status IS NULL";
        $pdo->exec($fixNullSql);
        error_log("Fixed NULL onboarding_status values");

        // Get all duplicate records by email or phone
        $findDuplicatesSql = "
            SELECT email, contact_number 
            FROM recruitment_leads 
            WHERE (email != '' AND email IN (
                SELECT email FROM recruitment_leads 
                WHERE email != '' 
                GROUP BY email 
                HAVING COUNT(*) > 1
            ))
            OR (contact_number != '' AND contact_number IN (
                SELECT contact_number 
                FROM recruitment_leads 
                WHERE contact_number != '' 
                GROUP BY contact_number 
                HAVING COUNT(*) > 1
            ))
            GROUP BY email, contact_number";
        
        $duplicates = $pdo->query($findDuplicatesSql)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($duplicates as $dup) {
            // For each set of duplicates, if any record is onboarded, make them all onboarded
            $updateDuplicatesSql = "
                UPDATE recruitment_leads 
                SET onboarding_status = CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM (
                            SELECT id FROM recruitment_leads 
                            WHERE (email = ? OR contact_number = ?) 
                            AND onboarding_status = 1
                        ) AS subquery
                    ) 
                    THEN 1 
                    ELSE 0 
                END
                WHERE email = ? OR contact_number = ?";
            
            $stmt = $pdo->prepare($updateDuplicatesSql);
            $stmt->execute([
                $dup['email'], 
                $dup['contact_number'],
                $dup['email'],
                $dup['contact_number']
            ]);
            
            error_log("Updated duplicate records for email: {$dup['email']}, phone: {$dup['contact_number']}");
        }
        
        // Verify record ID 28
        $verifyStmt = $pdo->query("SELECT * FROM recruitment_leads WHERE id = 28");
        $record28 = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Record ID 28 after cleanup: " . json_encode($record28));
        
        echo json_encode(['success' => true, 'message' => 'Onboarding status cleanup completed']);
    } catch (Exception $e) {
        error_log("Error during cleanup: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Execute the cleanup
cleanupOnboardingStatus($pdo);