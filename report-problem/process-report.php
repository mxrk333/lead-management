<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $issue_type = $_POST['issue_type'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $description = trim($_POST['description'] ?? '');
    $browser_info = $_POST['browser_info'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^[\+]?[1-9][\d]{0,15}$/', preg_replace('/[^\d+]/', '', $phone))) {
        $errors[] = 'Please enter a valid phone number';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($issue_type)) {
        $errors[] = 'Issue type is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Problem description is required';
    } elseif (strlen($description) < 10) {
        $errors[] = 'Please provide a more detailed description (at least 10 characters)';
    }
    
    if (!in_array($priority, ['low', 'medium', 'high'])) {
        $priority = 'medium';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
    
    // Get client information
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Clean phone number
    $phone = preg_replace('/[^\d+]/', '', $phone);
    
    // Prepare SQL statement
    $sql = "INSERT INTO problem_reports (
        username, 
        phone_number, 
        email, 
        issue_type, 
        priority, 
        description, 
        browser_info, 
        ip_address, 
        user_agent
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $username,
        $phone,
        $email ?: null,
        $issue_type,
        $priority,
        $description,
        $browser_info,
        $ip_address,
        $user_agent
    ]);
    
    if ($result) {
        $report_id = $pdo->lastInsertId();
        
        // Get the generated ticket number
        $ticket_stmt = $pdo->prepare("SELECT ticket_number FROM problem_reports WHERE id = ?");
        $ticket_stmt->execute([$report_id]);
        $ticket_number = $ticket_stmt->fetchColumn();
        
        // Log the submission
        error_log("Problem report submitted - Ticket: $ticket_number, User: $username, Phone: $phone");
        
        // Send notification email to admin (optional)
        // sendAdminNotification($ticket_number, $username, $issue_type, $priority);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Your problem report has been submitted successfully.',
            'ticket_number' => $ticket_number
        ]);
    } else {
        throw new Exception('Failed to save problem report');
    }
    
} catch (Exception $e) {
    error_log("Problem report error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while submitting your report. Please try again.'
    ]);
}

// Optional: Function to send admin notification
function sendAdminNotification($ticket_number, $username, $issue_type, $priority) {
    $to = 'support@innersparc.com';
    $subject = "New Problem Report - $ticket_number [$priority Priority]";
    $message = "
    A new problem report has been submitted:
    
    Ticket Number: $ticket_number
    Username: $username
    Issue Type: $issue_type
    Priority: $priority
    
    Please check the admin panel for full details.
    ";
    
    $headers = 'From: noreply@innersparc.com' . "\r\n" .
               'Reply-To: noreply@innersparc.com' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    
    mail($to, $subject, $message, $headers);
}
?>