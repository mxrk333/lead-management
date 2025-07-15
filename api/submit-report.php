<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// api/submit-report.php
header('Content-Type: application/json'); // Indicate JSON response

// Adjust path resolution to reach the root config/database.php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/send_email.php'; // Include the email utility

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $issue_type = trim($_POST['issue_type'] ?? '');
    $priority = trim($_POST['priority'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $browser_info = trim($_POST['browser_info'] ?? '');

    // Basic validation
    if (empty($username) || empty($phone) || empty($issue_type) || empty($priority) || empty($description)) {
        $response['message'] = 'Please fill in all required fields (Username, Phone, Issue Type, Priority, Description).';
        echo json_encode($response);
        exit();
    }

    try {
        $conn = getDbConnection();

        // Prepare and execute the insert statement
        $stmt = $conn->prepare("INSERT INTO problem_reports (username, phone, email, issue_type, priority, description, browser_info) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->bind_param("sssssss", $username, $phone, $email, $issue_type, $priority, $description, $browser_info);

        if ($stmt->execute()) {
            $report_id = $conn->insert_id;
            $response['success'] = true;
            $response['message'] = 'Your problem report has been submitted successfully. Report ID: #' . $report_id;

            // Send email notification to admin/support
            $admin_email = 'admin@example.com'; // Replace with your actual admin email
            $admin_name = 'Admin';
            $email_subject = "New Problem Report Submitted: #{$report_id} - " . ucfirst(str_replace('-', ' ', $issue_type));
            $email_body = "
                <p>A new problem report has been submitted:</p>
                <ul>
                    <li><strong>Report ID:</strong> #{$report_id}</li>
                    <li><strong>Username:</strong> " . htmlspecialchars($username) . "</li>
                    <li><strong>Phone:</strong> " . htmlspecialchars($phone) . "</li>
                    <li><strong>Email:</strong> " . (empty($email) ? 'N/A' : htmlspecialchars($email)) . "</li>
                    <li><strong>Issue Type:</strong> " . ucfirst(str_replace('-', ' ', $issue_type)) . "</li>
                    <li><strong>Priority:</strong> " . ucfirst($priority) . "</li>
                    <li><strong>Description:</strong><br>" . nl2br(htmlspecialchars($description)) . "</li>
                    <li><strong>Browser Info:</strong> " . htmlspecialchars($browser_info) . "</li>
                </ul>
                <p>Please log in to the system to view and manage this report.</p>
                <p>Link: <a href=\"http://yourdomain.com/admin/process-report.php\">View Reports</a></p>
            ";

            sendEmail($admin_email, $admin_name, $email_subject, $email_body);

        } else {
            $response['message'] = 'Failed to submit report: ' . $stmt->error;
        }

        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("Error in submit-report.php: " . $e->getMessage());
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>
