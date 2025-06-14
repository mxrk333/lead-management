<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP Test Page</h1>";

// Basic server information
echo "<h2>Server Information:</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Path: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Current Directory: " . getcwd() . "\n";
echo "</pre>";

// File system check
echo "<h2>File System Check:</h2>";
echo "<pre>";
echo "index.php exists: " . (file_exists('index.php') ? 'Yes' : 'No') . "\n";
echo "login.php exists: " . (file_exists('login.php') ? 'Yes' : 'No') . "\n";
echo "config/database.php exists: " . (file_exists('config/database.php') ? 'Yes' : 'No') . "\n";
echo "</pre>";

// Directory listing
echo "<h2>Directory Contents:</h2>";
echo "<pre>";
$files = scandir('.');
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo $file . "\n";
    }
}
echo "</pre>";

// File permissions
echo "<h2>File Permissions:</h2>";
echo "<pre>";
$important_files = ['index.php', 'login.php', 'config/database.php'];
foreach ($important_files as $file) {
    if (file_exists($file)) {
        echo $file . ": " . substr(sprintf('%o', fileperms($file)), -4) . "\n";
    }
}
echo "</pre>";
?> 