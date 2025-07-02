<?php
require_once 'dbConfig.php';
require_once 'models.php';


if (isset($_POST['insertData'])) {
    try {
        // Sanitize and assign POST data
        $datetime = $_POST['datetime'] ?? '';
        $name = $_POST['name'];
        $team = $_POST['team'];
        $toggle = $_POST['toggle'];

        // Insert logic
        if (!empty($datetime)) {
            $stmt = $pdo->prepare("INSERT INTO manual_data (datetime, name, team, toggle) VALUES (?, ?, ?, ?)");
            $stmt->execute([$datetime, $name, $team, $toggle]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO manual_data (datetime, name, team, toggle) VALUES (NOW(), ?, ?, ?)");
            $stmt->execute([$name, $team, $toggle]);
        }

        // Redirect after successful insert
        header("Location: ../../accreditation.php");
        exit;

    } catch (PDOException $e) {
        echo "Database error: " . htmlspecialchars($e->getMessage());
    }
}