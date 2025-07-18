<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Test Save Lead Functionality</h2>";

// Test data
$testData = [
    'full_name' => 'Test User',
    'contact_number' => '09123456789',
    'email' => 'test@example.com',
    'recruiter_name' => 'Test Recruiter',
    'interest_level' => 'Hot',
    'status' => 'Inquiry',
    'source' => 'Facebook Ads',
    'agent_onboarding_status' => 'Pre-Recruitment',
    'remarks' => 'Test remarks'
];

echo "<h3>Test Data:</h3>";
echo "<pre>" . print_r($testData, true) . "</pre>";

// Test 1: Check if function exists
echo "<h3>1. Function Check</h3>";
if (function_exists('addRecruitmentLead')) {
    echo "✅ addRecruitmentLead function exists<br>";
    
    // Test the function
    try {
        $result = addRecruitmentLead($testData);
        echo "<h3>2. Function Result:</h3>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } catch (Exception $e) {
        echo "❌ Error calling function: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ addRecruitmentLead function does not exist<br>";
}

// Test 2: Direct database insert
echo "<h3>3. Direct Database Test</h3>";
try {
    $sql = "INSERT INTO recruitment_leads (
                full_name, 
                contact_number, 
                email, 
                recruiter_name, 
                interest_level, 
                status, 
                source, 
                agent_onboarding_status,
                remarks
            ) VALUES (
                :full_name, 
                :contact_number, 
                :email, 
                :recruiter_name, 
                :interest_level, 
                :status, 
                :source, 
                :agent_onboarding_status,
                :remarks
            )";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':full_name' => 'Direct Test User',
        ':contact_number' => '09999999999',
        ':email' => 'direct@test.com',
        ':recruiter_name' => 'Direct Recruiter',
        ':interest_level' => 'Warm',
        ':status' => 'Inquiry',
        ':source' => 'TikTok ads',
        ':agent_onboarding_status' => 'Recruitment',
        ':remarks' => 'Direct insert test'
    ]);
    
    if ($result) {
        echo "✅ Direct database insert successful<br>";
        echo "Last insert ID: " . $pdo->lastInsertId() . "<br>";
    } else {
        echo "❌ Direct database insert failed<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Direct database error: " . $e->getMessage() . "<br>";
}

// Test 3: AJAX simulation
echo "<h3>4. AJAX Simulation Test</h3>";
echo "<form id='testForm'>
    <input type='hidden' name='action' value='add_recruitment_lead'>
    <input type='hidden' name='full_name' value='AJAX Test User'>
    <input type='hidden' name='contact_number' value='09888888888'>
    <input type='hidden' name='email' value='ajax@test.com'>
    <input type='hidden' name='recruiter_name' value='AJAX Recruiter'>
    <input type='hidden' name='interest_level' value='Cold'>
    <input type='hidden' name='status' value='Accreditation'>
    <input type='hidden' name='source' value='Google Ads'>
    <input type='hidden' name='agent_onboarding_status' value='Pre-Recruitment'>
    <input type='hidden' name='remarks' value='AJAX test'>
    <button type='button' onclick='testAjaxSave()'>Test AJAX Save</button>
</form>";

echo "<div id='ajaxSaveResult'></div>";

echo "<script>
function testAjaxSave() {
    const form = document.getElementById('testForm');
    const formData = new FormData(form);
    
    fetch('includes/functions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('ajaxSaveResult').innerHTML = '<h4>AJAX Response:</h4><pre>' + data + '</pre>';
    })
    .catch(error => {
        document.getElementById('ajaxSaveResult').innerHTML = '<h4>AJAX Error:</h4>' + error;
    });
}
</script>";
?>
