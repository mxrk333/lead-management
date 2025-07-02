<?php

require_once '../core/dbConfig.php';

// $host = 'localhost';
// $dbname = 'inner_sparc_accreditation';
// $user = 'root';
// $pass = '';
// $charset = 'utf8mb4';

// $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
// $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    // $pdo = new PDO($dsn, $user, $pass, $options);

    if (!isset($_POST['id'])) {
        exit("❌ Missing ID.");
    }

    $id = $_POST['id'];
    $name = $_POST['name'] ?? 'Unknown'; // Use name if provided, otherwise 'Unknown'

    $stmt = $pdo->prepare("DELETE FROM manual_data WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo "🗑️ Deleted entry: $name (ID: $id)";
    } else {
        echo "❌ No entry found with ID: $id";
    }
} catch (PDOException $e) {
    echo "❌ DB Error: " . $e->getMessage();
}