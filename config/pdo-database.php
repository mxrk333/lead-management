<?php

require_once __DIR__ . '/database.php';

$pdo = null;

function getPdoConnection() {
    global $pdo;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        // Use the same environment detection as your MySQLi setup
        if (function_exists('isDreamHost') && isDreamHost()) {
            // DreamHost environment
            $host = 'managementlead.innersparcagents.dreamhosters.com';
            $username = 'managementlead';
            $password = 'innersparc123';
            $database = 'managementlead';
        } else {
            // Local development environment
            $host = 'localhost';
            $username = 'root';
            $password = '';
            $database = 'real_estate_leads';
        }
        
        $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
        
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        error_log("PDO connection successful to {$host}/{$database}");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("PDO connection error: " . $e->getMessage());
        throw $e;
    }
}

// Initialize the global PDO connection
try {
    $pdo = getPdoConnection();
} catch (Exception $e) {
    error_log("Failed to initialize PDO connection: " . $e->getMessage());
    $pdo = null;
}
?>
