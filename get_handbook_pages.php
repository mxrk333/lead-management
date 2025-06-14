<?php
// get_handbook_pages.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Get database connection
$conn = getDbConnection();
if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if handbook ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid handbook ID']);
    exit();
}

$handbook_id = (int)$_GET['id'];

// Get handbook pages
$query = "SELECT * FROM handbook_pages WHERE handbook_id = $handbook_id ORDER BY page_number ASC";
$result = mysqli_query($conn, $query);
$pages = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pages[] = $row;
    }
}

// Close database connection
$conn->close();

// Return pages as JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'pages' => $pages]);
?>