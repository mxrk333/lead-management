<?php

require_once '../core/dbConfig.php';

// $host = 'localhost';
// $dbname = 'inner_sparc_accreditation';
// $user = 'root';
// $pass = '';
// $charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    // $pdo = new PDO($dsn, $user, $pass, $options);

    if (!isset($_POST['id'], $_POST['team'], $_POST['toggle'])) {
        exit("❌ Missing parameters.");
    }

    $id = $_POST['id'];
    $team = $_POST['team'];
    $toggle = $_POST['toggle'];

    if (!in_array($toggle, ['1', '0', '', '2'], true)) {
        exit("❌ Invalid toggle value.");
    }

    $stmt = $pdo->prepare("UPDATE manual_data SET team = :team, toggle = :toggle WHERE id = :id");
    $stmt->execute([
        ':team' => $team,
        ':toggle' => $toggle,
        ':id' => $id
    ]);

    echo "✅ Updated ID $id";
} catch (PDOException $e) {
    echo "❌ DB Error: " . $e->getMessage();
}