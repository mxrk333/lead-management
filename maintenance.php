<?php
session_start();

// Debugging (REMOVE in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$conn = getDbConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance - Lead Management System</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      font-family: "Segoe UI", Tahoma, sans-serif;
      background: #ecf0f1;
      margin: 0;
    }
    .maintenance-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 90vh;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .maintenance-container h1 {
      font-size: 38px;
      margin: 20px 0 10px;
      color: #2c3e50;
    }
    .maintenance-container p {
      font-size: 18px;
      color: #555;
      max-width: 520px;
    }

    /* Scene */
    .scene {
      position: relative;
      width: 320px;
      height: 280px;
      margin-bottom: 30px;
    }

    /* Sun */
    .sun {
      position: absolute;
      top: -30px;
      right: -50px;
      width: 70px;
      height: 70px;
      background: #f1c40f;
      border-radius: 50%;
      box-shadow: 0 0 30px rgba(241,196,15,0.7);
      animation: spin 25s linear infinite;
    }

    /* Clouds */
    .cloud {
      position: absolute;
      background: #fff;
      border-radius: 50px;
      height: 40px;
      width: 110px;
      top: 40px;
      left: -200px;
      opacity: 0.9;
      animation: cloudMove 35s linear infinite;
    }
    .cloud::before, .cloud::after {
      content: '';
      position: absolute;
      background: #fff;
      border-radius: 50%;
    }
    .cloud::before {
      width: 55px;
      height: 55px;
      top: -20px;
      left: 15px;
    }
    .cloud::after {
      width: 70px;
      height: 70px;
      top: -30px;
      right: 15px;
    }

    /* Birds */
    .bird {
      position: absolute;
      width: 20px;
      height: 20px;
      top: 60px;
      left: -40px;
      font-size: 20px;
      color: #2c3e50;
      animation: birdFly 18s linear infinite;
    }

    /* Ground */
    .ground {
      position: absolute;
      bottom: 0;
      width: 100%;
      height: 50px;
      background: linear-gradient(to top, #27ae60, #2ecc71);
      border-top-left-radius: 50% 20px;
      border-top-right-radius: 50% 20px;
    }

    /* House */
    .house {
      position: absolute;
      bottom: 50px;
      left: 50%;
      transform: translateX(-50%) scale(0.9);
      animation: bounceIn 2s ease-out forwards;
    }
    .walls {
      width: 200px;
      height: 140px;
      background: #f39c12;
      border-radius: 6px;
      position: relative;
      box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }
    .roof {
      width: 0;
      height: 0;
      border-left: 110px solid transparent;
      border-right: 110px solid transparent;
      border-bottom: 90px solid #c0392b;
      position: absolute;
      top: -90px;
      left: -10px;
    }
    .chimney {
      width: 30px;
      height: 50px;
      background: #7f8c8d;
      position: absolute;
      top: -70px;
      right: 40px;
      border-radius: 3px;
    }
    .door {
      width: 50px;
      height: 80px;
      background: #6d4c41;
      border-radius: 4px;
      position: absolute;
      bottom: 0;
      left: 75px;
    }
    .window {
      width: 45px;
      height: 45px;
      background: #ecf0f1;
      border: 3px solid #2980b9;
      position: absolute;
      top: 25px;
      border-radius: 5px;
    }
    .window.left { left: 25px; }
    .window.right { right: 25px; }

    /* Worker */
    .worker {
      position: absolute;
      bottom: 60px;
      right: -70px;
      font-size: 60px;
      color: #34495e;
      animation: hammerWork 1s infinite alternate;
    }

    /* Crane */
    .crane {
      position: absolute;
      left: -80px;
      bottom: 50px;
      width: 100px;
      height: 150px;
    }
    .crane .mast {
      width: 10px;
      height: 100%;
      background: #7f8c8d;
      position: absolute;
      left: 45px;
    }
    .crane .arm {
      width: 120px;
      height: 10px;
      background: #7f8c8d;
      position: absolute;
      top: 20px;
      left: -20px;
    }
    .crane .hook {
      width: 6px;
      height: 40px;
      background: #2c3e50;
      position: absolute;
      top: 30px;
      left: 90px;
      animation: hookMove 2s ease-in-out infinite;
    }
    .crane .load {
      width: 30px;
      height: 20px;
      background: #e67e22;
      position: absolute;
      top: 70px;
      left: 78px;
    }

    /* Gears */
    .gear {
      position: absolute;
      bottom: 20px;
      font-size: 30px;
      color: #7f8c8d;
      animation: gearSpin 6s linear infinite;
    }
    .gear.left { left: -50px; }
    .gear.right { right: -50px; }

    /* Progress bar */
    .progress {
      margin-top: 20px;
      width: 250px;
      height: 18px;
      background: #ccc;
      border-radius: 10px;
      overflow: hidden;
      position: relative;
    }
    .progress-bar {
      width: 65%;
      height: 100%;
      background: linear-gradient(90deg, #27ae60, #2ecc71);
      animation: progressAnim 4s infinite;
    }

    /* Animations */
    @keyframes bounceIn {
      0% { transform: translateX(-50%) scale(0.3); opacity: 0; }
      60% { transform: translateX(-50%) scale(1.05); opacity: 1; }
      80% { transform: translateX(-50%) scale(0.95); }
      100% { transform: translateX(-50%) scale(0.9); }
    }
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    @keyframes cloudMove {
      from { left: -220px; }
      to { left: 400px; }
    }
    @keyframes birdFly {
      from { left: -50px; top: 70px; }
      to { left: 350px; top: 50px; }
    }
    @keyframes hammerWork {
      from { transform: rotate(-20deg); }
      to { transform: rotate(20deg); }
    }
    @keyframes hookMove {
      0%,100% { transform: translateY(0); }
      50% { transform: translateY(20px); }
    }
    @keyframes gearSpin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    @keyframes progressAnim {
      0% { width: 0%; }
      100% { width: 65%; }
    }
  </style>
</head>
<body>
  <div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
      <?php include 'includes/header.php'; ?>

      <div class="maintenance-container">
        <div class="scene">
          <div class="sun"></div>
          <div class="cloud"></div>
          <div class="bird">🐦</div>

          <!-- Crane -->
          <div class="crane">
            <div class="mast"></div>
            <div class="arm"></div>
            <div class="hook"></div>
            <div class="load"></div>
          </div>

          <!-- House -->
          <div class="house">
            <div class="roof"></div>
            <div class="chimney"></div>
            <div class="walls">
              <div class="door"></div>
              <div class="window left"></div>
              <div class="window right"></div>
            </div>
            <i class="fas fa-hard-hat worker"></i>
          </div>

          <!-- Gears -->
          <i class="fas fa-cog gear left"></i>
          <i class="fas fa-cog gear right"></i>

          <!-- Ground -->
          <div class="ground"></div>
        </div>

        <h1>We’re working on it!</h1>
        <p>Our system is under maintenance with some new improvements.<br>
        Please check back soon. Thanks for your patience!</p>

        <!-- Progress bar -->
        <div class="progress">
          <div class="progress-bar"></div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
