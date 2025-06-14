<?php
// Simple file checker to help debug file locations
echo "<h1>File Location Checker</h1>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>Current working directory: " . getcwd() . "</p>";

$files_to_check = [
    'mark-notifications-read.php',
    'config.php',
    'header.php',
    'notification-status.php'
];

echo "<h2>File Status:</h2>";
foreach ($files_to_check as $file) {
    $exists = file_exists($file);
    $status = $exists ? "✅ EXISTS" : "❌ MISSING";
    $path = $exists ? realpath($file) : "Not found";
    echo "<p><strong>{$file}:</strong> {$status} - {$path}</p>";
}

echo "<h2>Directory Contents:</h2>";
$files = scandir('.');
echo "<ul>";
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $type = is_dir($file) ? '[DIR]' : '[FILE]';
        echo "<li>{$type} {$file}</li>";
    }
}
echo "</ul>";

echo "<h2>Session Information:</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Server Information:</h2>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
?>
