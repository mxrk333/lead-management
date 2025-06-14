<?php
// Database connection
$servername = "localhost";
$username = "username";
$password = "password";
$dbname = "database";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/**
 * Acknowledge the memo by updating the read status in the database
 *
 * @param int $memo_id The ID of the memo
 * @param int $employee_id The ID of the employee
 * @return bool True on success, false on failure
 */
function acknowledgeMemo($memo_id, $employee_id) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO memo_read_status (memo_id, employee_id, read_status, read_at) 
                           VALUES (?, ?, 1, NOW()) 
                           ON DUPLICATE KEY UPDATE read_status = 1, read_at = NOW()");
    $stmt->bind_param("ii", $memo_id, $employee_id);
    return $stmt->execute();
}

/**
 * Get the read status of a memo
 *
 * @param int $memo_id The ID of the memo
 * @return array An associative array of user names and read timestamps
 */
function getMemoReadStatus($memo_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT u.name, mrs.read_at 
                           FROM memo_read_status mrs 
                           JOIN users u ON mrs.employee_id = u.id 
                           WHERE mrs.memo_id = ? AND mrs.read_status = 1");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Close connection
$conn->close();
?>