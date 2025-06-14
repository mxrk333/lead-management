<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Function to check if user is a superuser
function isSuperUser($username) {
    $superusers = [
        'markpatigayon.intern',
        'gabriellibacao.founder', 
        'romeocorberta.itdept'
    ];
    return in_array($username, $superusers);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "User not logged in";
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

echo "<h2>Superuser Debug Information</h2>";
echo "<p><strong>User ID:</strong> " . $user_id . "</p>";
echo "<p><strong>Username:</strong> " . htmlspecialchars($user['username']) . "</p>";
echo "<p><strong>Is Superuser:</strong> " . (isSuperUser($user['username']) ? 'YES' : 'NO') . "</p>";

echo "<h3>Superuser List:</h3>";
$superusers = [
    'markpatigayon.intern',
    'gabriellibacao.founder', 
    'romeocorberta.itdept'
];

foreach ($superusers as $superuser) {
    echo "<p>- " . htmlspecialchars($superuser) . "</p>";
}

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>User Data:</h3>";
echo "<pre>";
print_r($user);
echo "</pre>";
?>
