<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'agent';
$user_team_id = $_SESSION['team_id'] ?? null;

// AJAX requests are handled in includes/functions.php

// Get initial data
$conn = getDbConnection();
$tickets_data = ['success' => true, 'tickets' => [], 'total' => 0];

try {
    // Check if lead_modifications table exists (now using lead_modifications instead of raffle_tickets)
    $table_check = $conn->query("SHOW TABLES LIKE 'lead_modifications'");
    if ($table_check->num_rows == 0) {
        error_log("lead_modifications table does not exist");
        $tickets_data = ['success' => false, 'message' => 'Lead modifications table not found. Please contact administrator.'];
    } else {
        $tickets_data = getRaffleTickets([], 50, 0);
        error_log("Initial tickets data loaded successfully from lead_modifications");
    }
} catch (Exception $e) {
    error_log("Error loading initial tickets data: " . $e->getMessage());
    $tickets_data = ['success' => false, 'message' => 'Error loading tickets: ' . $e->getMessage()];
}

// Get teams for filter dropdown
$teams = [];
try {
    $teams_stmt = $conn->prepare("SELECT id, name FROM teams ORDER BY name");
    $teams_stmt->execute();
    $teams_result = $teams_stmt->get_result();
    while ($row = $teams_result->fetch_assoc()) {
        $teams[] = $row;
    }
    $teams_stmt->close();
} catch (Exception $e) {
    error_log("Error loading teams: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raffle Tickets - InnerSPARC</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Wheel styles */
        .summary-view {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .summary-title {
            font-size: 24px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .summary-card h3 {
            margin-top: 0;
            color: #1e40af;
            font-size: 18px;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .participant-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .participant-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .participant-item:last-child {
            border-bottom: none;
        }

        .participant-name {
            font-weight: 500;
            color: #1e293b;
        }

        .participant-tickets {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .wheel-container {
            position: relative;
            width: 350px;
            height: 350px;
            margin: 20px auto;
            display: none;
        }
        
        .wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%; 
            position: relative;
            overflow: hidden;
            border: 10px solid #333;
            box-shadow: 0 0 0 5px #333, 0 0 0 15px #fff, 0 0 0 18px #333;
            transition: transform 5s cubic-bezier(0.17, 0.67, 0.12, 0.99);
        }
        
        .wheel-center {
            position: absolute;
            width: 40px;
            height: 40px;
            background: #333;
            border: 5px solid #fff;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            box-shadow: 0 0 5px rgba(0,0,0,0.5);
        }
        
        .wheel-arrow {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            color: #e74c3c;
            font-size: 60px;
            z-index: 5;
            text-shadow: 0 0 5px rgba(0,0,0,0.5);
        }
        
        .wheel-section {
            position: absolute;
            width: 50%;
            height: 50%;
            transform-origin: bottom right;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            text-align: center;
            padding: 20px;
            box-sizing: border-box;
            font-size: 14px;
            font-weight: bold;
            color: white;
            text-shadow: 0 0 2px #000;
        }
        
        .wheel-winner {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            text-align: center;
            z-index: 20;
            display: none;
        }
        
        .wheel-winner h3 {
            margin: 0 0 20px 0;
            font-size: 28px;
            color: #f1c40f;
        }
        
        .wheel-winner p {
            margin: 10px 0;
            font-size: 20px;
        }
        
        .spin-again-btn {
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 16px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .spin-again-btn:hover {
            background: #c0392b;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: #f8fafc;
        }
        
        .raffle-container {
            padding: 24px;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .raffle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .raffle-title {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.025em;
        }
        
        .raffle-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        
        .stat-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: #2563eb;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }
        
        .filters-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 32px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .filter-input {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: #f8fafc;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .filter-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
            border: 2px solid #2563eb;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
            border: 2px solid #64748b;
        }
        
        .btn-secondary:hover {
            background: #475569;
            border-color: #475569;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: #059669;
            color: white;
            border: 2px solid #059669;
        }
        
        .btn-success:hover {
            background: #047857;
            border-color: #047857;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2);
        }
        
        .team-section {
            margin-bottom: 40px;
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .team-name {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        
        .team-summary {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .team-stats {
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            color: #475569;
        }
        
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }
        
        .user-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 24px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #2563eb;
        }
        
        .user-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-4px);
            border-color: #2563eb;
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .user-name {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.025em;
        }
        
        .ticket-count {
            background: #2563eb;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
        }
        
        .user-info {
            margin-bottom: 20px;
        }
        
        .user-detail {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
            color: #64748b;
            padding: 4px 0;
        }
        
        .user-detail i {
            width: 18px;
            margin-right: 12px;
            color: #2563eb;
            font-size: 16px;
        }
        
        .user-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .btn-small {
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
        }
        
        /* Made modal much wider and close button more visible */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 1400px;
            width: 95%;
            max-height: 90vh;
            overflow: hidden;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @media (min-width: 1600px) {
            .modal-content {
                max-width: 1600px;
            }
        }
        
        @media (min-width: 1920px) {
            .modal-content {
                max-width: 1800px;
            }
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            background: #2563eb;
            color: white;
            padding: 28px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 26px;
            font-weight: 700;
            margin: 0;
        }
        
        .modal-close {
            background: rgba(255, 255, 255, 0.25);
            border: 2px solid rgba(255, 255, 255, 0.4);
            color: white;
            font-size: 28px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-weight: 700;
            line-height: 1;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.4);
            border-color: rgba(255, 255, 255, 0.6);
            transform: scale(1.1) rotate(90deg);
        }
        
        .modal-body {
            padding: 32px;
            max-height: calc(90vh - 200px);
            overflow-y: auto;
        }
        
        .user-info-header {
            background: #f8fafc;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 28px;
            border: 1px solid #e2e8f0;
        }
        
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .user-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-info-item i {
            color: #2563eb;
            font-size: 20px;
            width: 24px;
        }
        
        .user-info-item span {
            font-weight: 600;
            color: #374151;
            font-size: 15px;
        }
        
        .tickets-section {
            margin-top: 28px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tickets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 16px;
        }
        
        .ticket-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .ticket-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #2563eb;
        }
        
        .ticket-item:hover {
            background: #f1f5f9;
            border-color: #2563eb;
            transform: translateX(4px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.1);
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .ticket-number {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #2563eb;
            font-size: 18px;
        }
        
        .ticket-date {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }
        
        .ticket-details {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: center;
        }
        
        .ticket-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .ticket-source {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }
        
        .ticket-lead {
            font-size: 13px;
            color: #9ca3af;
        }
        
        .stage-source {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .stage-source.lead-status {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .stage-source.dp-stage {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .stage-source.requirement {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .stage-source.spot-dp {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .modal-footer {
            background: #f8fafc;
            padding: 24px 32px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .no-tickets {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        
        .no-tickets i {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 16px;
        }
        
        .tickets-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .pagination button {
            padding: 10px 16px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #f3f4f6;
            border-color: #2563eb;
            color: #2563eb;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination span {
            font-weight: 600;
            color: #374151;
        }
        
        .loading {
            text-align: center;
            padding: 60px 40px;
            color: #6b7280;
        }
        
        .loading i {
            font-size: 32px;
            margin-bottom: 12px;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 40px;
            color: #6b7280;
        }
        
        .print-ticket {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            font-family: 'Courier New', monospace;
        }
        
        .print-ticket h3 {
            margin: 0 0 10px 0;
            color: #1e293b;
        }
        
        .print-ticket .ticket-info {
            margin: 5px 0;
            color: #475569;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-ticket {
                border: 2px solid #000;
                margin: 10px 0;
                page-break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 98%;
                max-height: 95vh;
            }
            
            .modal-body {
                padding: 20px;
                max-height: calc(95vh - 180px);
            }
            
            .tickets-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="raffle-container">
                <div class="raffle-header">
                    <h1 class="raffle-title">Raffle Tickets</h1>
                    <div class="table-actions">
                        <button class="btn btn-primary" onclick="showSummary()">
                            <i class="fas fa-chart-pie"></i> View Summary
                        </button>
                        <button class="btn btn-success" onclick="printAllTickets()">
                            <i class="fas fa-print"></i> Print All Tickets
                        </button>
                    </div>
                </div>
                
                 Statistics Cards 
                <div class="raffle-stats">
                    <div class="stat-card">
                        <div class="stat-number" id="total-tickets">-</div>
                        <div class="stat-label">Total Tickets</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="lead-status-tickets">-</div>
                        <div class="stat-label">Lead Status Tickets</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="dp-stage-tickets">-</div>
                        <div class="stat-label">DP Stage Tickets</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="requirement-tickets">-</div>
                        <div class="stat-label">Requirement Tickets</div>
                    </div>
                </div>
                
                 Filters Section 
                <div class="filters-section">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Team</label>
                            <select class="filter-input" id="team-filter">
                                <option value="">All Teams</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Stage Source</label>
                            <select class="filter-input" id="stage-source-filter">
                                <option value="">All Sources</option>
                                <option value="Lead Status">Lead Status</option>
                                <option value="DP Stage">DP Stage</option>
                                <option value="Requirement">Requirement</option>
                                <option value="Spot DP">Spot DP</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Date From</label>
                            <input type="date" class="filter-input" id="date-from-filter">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Date To</label>
                            <input type="date" class="filter-input" id="date-to-filter">
                        </div>
                    </div>
                    <div class="filter-buttons">
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <button class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    </div>
                </div>
                
                 Users Grid 
                <div class="tickets-table-container">
                    <div class="table-header">
                        <h2 class="table-title">Raffle Tickets by User</h2>
                        <div class="table-actions">
                            <button class="btn btn-primary" onclick="refreshTickets()">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div id="tickets-content">
                        <div class="loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <div>Loading tickets...</div>
                        </div>
                    </div>
                </div>
                
                 Modal for Ticket Details 
                <div id="ticketModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title" id="modalTitle">Ticket Details</h2>
                            <button class="modal-close" onclick="closeModal()">&times;</button>
                        </div>
                        <div class="modal-body" id="modalBody">
                             Modal content will be loaded here 
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" onclick="closeModal()">
                                <i class="fas fa-times"></i> Close
                            </button>
                            <button class="btn btn-primary" onclick="printAllUserTickets()" id="printAllBtn">
                                <i class="fas fa-print"></i> Print All Tickets
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Modal -->
    <div id="summaryModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 class="modal-title">Ticket Summary</h2>
                <button class="modal-close" onclick="closeSummaryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="summary-card" style="background: #f0f9ff; border-radius: 10px; padding: 20px; border-left: 4px solid #0ea5e9;">
                        <h3 style="margin-top: 0; color: #0369a1;">Set A</h3>
                        <p style="font-size: 24px; font-weight: bold; margin: 10px 0;"><span id="setACount">0</span> tickets</p>
                        <p style="color: #64748b; margin: 5px 0; font-size: 14px;">10 tickets or more</p>
                        <button class="btn btn-primary" onclick="printSetTickets('A')" style="margin-top: 10px; width: 100%;">Print Set A</button>
                        <button class="btn btn-secondary" onclick="startWheel('A')" style="margin-top: 10px; width: 100%;">
                            <i class="fas fa-sync-alt"></i> Spin for Winner
                        </button>
                    </div>
                    <div class="summary-card" style="background: #f0fdf4; border-radius: 10px; padding: 20px; border-left: 4px solid #10b981;">
                        <h3 style="margin-top: 0; color:#047857;">Set B</h3>
                        <p style="font-size: 24px; font-weight: bold; margin: 10px 0;"><span id="setBCount">0</span> tickets</p>
                        <p style="color: #64748b; margin: 5px 0; font-size: 14px;">20 tickets or more</p>
                        <button class="btn btn-success" onclick="printSetTickets('B')" style="margin-top: 10px; width: 100%;">Print Set B</button>
                        <button class="btn btn-secondary" onclick="startWheel('B')" style="margin-top: 10px; width: 100%;">
                            <i class="fas fa-sync-alt"></i> Spin for Winner
                        </button>
                    </div>
                    <div class="summary-card" style="background: #fef2f2; border-radius: 10px; padding: 20px; border-left: 4px solid #ef4444;">
                        <h3 style="margin-top: 0; color: #b91c1c;">Set C</h3>
                        <p style="font-size: 24px; font-weight: bold; margin: 10px 0;"><span id="setCCount">0</span> tickets</p>
                        <p style="color: #64748b; margin: 5px 0; font-size: 14px;">50 tickets or more</p>
                        <button class="btn btn-danger" onclick="printSetTickets('C')" style="margin-top: 10px; width: 100%;">Print Set C</button>
                        <button class="btn btn-secondary" onclick="startWheel('C')" style="margin-top: 10px; width: 100%;">
                            <i class="fas fa-sync-alt"></i> Spin for Winner
                        </button>
                    </div>
                </div>
                <div class="wheel-container" id="wheelContainer">
                    <div class="wheel" id="wheel"></div>
                    <div class="wheel-center">SPIN</div>
                    <div class="wheel-arrow">▼</div>
                    <div class="wheel-winner" id="wheelWinner">
                        <h3>🎉 Winner! 🎉</h3>
                        <p id="winnerName"></p>
                        <p id="winnerTickets"></p>
                        <button class="spin-again-btn" onclick="resetWheel()">Spin Again</button>
                    </div>
                </div>
                <div class="ticket-preview" id="ticketPreview" style="max-height: 400px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-top: 20px; display: none;">
                    <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">Preview</h3>
                    <div id="ticketList"></div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 15px 20px; text-align: right; border-top: 1px solid #e2e8f0;">
                <button class="btn btn-secondary" onclick="closeSummaryModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let currentFilters = {};
        
        // Function to show summary modal
        async function showSummary() {
            // Show loading state
            const modalContent = document.querySelector('.modal-body');
            modalContent.innerHTML = '<div class="text-center p-8"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div><p class="mt-4 text-gray-600">Loading participants...</p></div>';
            
            // Show the modal
            const modal = document.getElementById('summaryModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            try {
                // Fetch participants for each set
                const [setAResponse, setBResponse, setCResponse] = await Promise.all([
                    fetchTicketsBySet('A'),
                    fetchTicketsBySet('B'),
                    fetchTicketsBySet('C')
                ]);

                const setA = setAResponse.tickets || [];
                const setB = setBResponse.tickets || [];
                const setC = setCResponse.tickets || [];

                // Update the modal content
                modalContent.innerHTML = `
                    <div class="summary-view">
                        <div class="summary-grid">
                            <div class="summary-card">
                                <h3>Set A - 10+ Tickets (${setA.length} participants)</h3>
                                <div class="participant-list">
                                    ${setA.length > 0 ? 
                                        setA.map(p => `
                                            <div class="participant-item">
                                                <span class="participant-name">${p.name}</span>
                                                <span class="participant-tickets">${p.ticket_count} tickets</span>
                                            </div>
                                        `).join('') 
                                        : '<div class="p-4 text-center text-gray-500">No participants in this set</div>'
                                    }
                                </div>
                            </div>
                            <div class="summary-card">
                                <h3>Set B - 20+ Tickets (${setB.length} participants)</h3>
                                <div class="participant-list">
                                    ${setB.length > 0 ? 
                                        setB.map(p => `
                                            <div class="participant-item">
                                                <span class="participant-name">${p.name}</span>
                                                <span class="participant-tickets">${p.ticket_count} tickets</span>
                                            </div>
                                        `).join('') 
                                        : '<div class="p-4 text-center text-gray-500">No participants in this set</div>'
                                    }
                                </div>
                            </div>
                            <div class="summary-card">
                                <h3>Set C - 50+ Tickets (${setC.length} participants)</h3>
                                <div class="participant-list">
                                    ${setC.length > 0 ? 
                                        setC.map(p => `
                                            <div class="participant-item">
                                                <span class="participant-name">${p.name}</span>
                                                <span class="participant-tickets">${p.ticket_count} tickets</span>
                                            </div>
                                        `).join('') 
                                        : '<div class="p-4 text-center text-gray-500">No participants in this set</div>'
                                    }
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button onclick="closeSummaryModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md text-gray-700">
                                Close
                            </button>
                        </div>
                    </div>
                `;

                // Update the count displays
                document.getElementById('setACount').textContent = setA.length;
                document.getElementById('setBCount').textContent = setB.length;
                document.getElementById('setCCount').textContent = setC.length;

            } catch (error) {
                console.error('Error loading summary:', error);
                modalContent.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-red-500 mb-4">
                            <i class="fas fa-exclamation-circle text-4xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Data</h3>
                        <p class="text-gray-600">Failed to load participant data. Please try again.</p>
                        <button onclick="showSummary()" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            Retry
                        </button>
                    </div>
                `;
            }
        }

        // Helper function to fetch tickets by set
        async function fetchTicketsBySet(set) {
            const formData = new FormData();
            formData.append('action', 'get_tickets_by_set');
            formData.append('set', set);
            
            // Add current filters if any
            Object.keys(currentFilters).forEach(key => {
                if (currentFilters[key]) formData.append(key, currentFilters[key]);
            });
            
            try {
                const response = await fetch('includes/functions.php', {
                    method: 'POST',
                    body: formData
                });
                return await response.json();
            } catch (error) {
                console.error(`Error fetching set ${set}:`, error);
                return { success: false, tickets: [] };
            }
        }
        
        // Function to close summary modal
        function closeSummaryModal() {
            document.getElementById('summaryModal').classList.remove('show');
            document.body.style.overflow = 'auto';
            document.getElementById('ticketPreview').style.display = 'none';
        }
        
        // Wheel variables
        let currentSet = null;
        let currentWheel = null;
        let spinning = false;
        let wheelItems = [];
        
        // Function to initialize the wheel
        function initWheel(items) {
            const wheel = document.getElementById('wheel');
            wheel.innerHTML = '';
            wheelItems = items;
            
            if (items.length === 0) {
                alert('No tickets available to spin!');
                return false;
            }
            
            // Create wheel sections
            const sectionAngle = 360 / items.length;
            const colors = ['#e74c3c', '#3498db', '#2ecc71', '#f1c40f', '#9b59b6', '#1abc9c', '#e67e22', '#e74c3c'];
            
            items.forEach((item, index) => {
                const section = document.createElement('div');
                section.className = 'wheel-section';
                section.style.transform = `rotate(${sectionAngle * index}deg) skewY(${90 - sectionAngle}deg)`;
                section.style.background = colors[index % colors.length];
                
                const content = document.createElement('div');
                content.style.transform = `skewY(${sectionAngle - 90}deg) rotate(${sectionAngle/2}deg)`;
                content.style.width = '100%';
                content.style.padding = '0 15px';
                content.textContent = item.name.split(' ').map(n => n[0]).join('.');
                content.title = `${item.name} (${item.ticket_count} tickets)`;
                
                section.appendChild(content);
                wheel.appendChild(section);
            });
            
            return true;
        }
        
        // Function to spin the wheel
        function spinWheel() {
            if (spinning) return;
            
            const wheel = document.getElementById('wheel');
            const winnerDisplay = document.getElementById('wheelWinner');
            const winnerName = document.getElementById('winnerName');
            const winnerTickets = document.getElementById('winnerTickets');
            
            // Select a random winner based on ticket count (more tickets = higher chance)
            const totalTickets = wheelItems.reduce((sum, item) => sum + item.ticket_count, 0);
            let random = Math.random() * totalTickets;
            let winnerIndex = 0;
            
            for (let i = 0; i < wheelItems.length; i++) {
                random -= wheelItems[i].ticket_count;
                if (random <= 0) {
                    winnerIndex = i;
                    break;
                }
            }
            
            const winner = wheelItems[winnerIndex];
            const sectionAngle = 360 / wheelItems.length;
            const targetAngle = 3600 + (360 - (winnerIndex * sectionAngle) - (sectionAngle / 2));
            
            spinning = true;
            wheel.style.transform = `rotate(${targetAngle}deg)`;
            
            // Show winner after spin completes
            setTimeout(() => {
                winnerName.textContent = winner.name;
                winnerTickets.textContent = `${winner.ticket_count} tickets`;
                winnerDisplay.style.display = 'flex';
                spinning = false;
                
                // Add winner to the winners list
                const winnerItem = document.createElement('div');
                winnerItem.className = 'winner-item';
                winnerItem.innerHTML = `
                    <strong>${winner.name}</strong> (${winner.ticket_count} tickets) - 
                    ${new Date().toLocaleString()}
                `;
                document.getElementById('winnersList').appendChild(winnerItem);
                
            }, 5500);
        }
        
        // Function to reset the wheel
        function resetWheel() {
            document.getElementById('wheelWinner').style.display = 'none';
            document.getElementById('wheel').style.transform = 'rotate(0deg)';
        }
        
        // Function to start the wheel for a specific set
        async function startWheel(set) {
            currentSet = set;
            const container = document.getElementById('wheelContainer');
            const preview = document.getElementById('ticketPreview');
            
            // Show loading state
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full p-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mb-4"></div>
                    <p class="text-gray-600">Loading participants for Set ${set}...</p>
                </div>
            `;
            container.style.display = 'block';
            preview.style.display = 'none';
            
            try {
                // Load tickets for this set
                const response = await fetchTicketsBySet(set);
                
                if (response.success && response.tickets && response.tickets.length > 0) {
                    // Rebuild the wheel container
                    container.innerHTML = `
                        <div class="wheel" id="wheel"></div>
                        <div class="wheel-center">SPIN</div>
                        <div class="wheel-arrow">▼</div>
                        <div class="wheel-winner" id="wheelWinner">
                            <h3>🎉 Winner! 🎉</h3>
                            <p id="winnerName"></p>
                            <p id="winnerTickets"></p>
                            <button class="spin-again-btn" onclick="resetWheel()">Spin Again</button>
                        </div>
                    `;
                    
                    if (initWheel(response.tickets)) {
                        // Scroll to wheel
                        container.scrollIntoView({ behavior: 'smooth' });
                        // Start spinning after a short delay
                        setTimeout(spinWheel, 500);
                    }
                } else {
                    throw new Error(response.message || 'No tickets found for this set');
                }
            } catch (error) {
                console.error('Error starting wheel:', error);
                container.innerHTML = `
                    <div class="p-8 text-center">
                        <div class="text-red-500 mb-4">
                            <i class="fas fa-exclamation-circle text-4xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Error</h3>
                        <p class="text-gray-600 mb-4">${error.message || 'Failed to load wheel data'}</p>
                        <button onclick="startWheel('${set}')" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            Try Again
                        </button>
                    </div>
                `;
            }
        }
        
        // Function to print tickets by set
        function printSetTickets(set) {
            const formData = new FormData();
            formData.append('action', 'get_tickets_by_set');
            formData.append('set', set);
            
            // Add current filters
            Object.keys(currentFilters).forEach(key => {
                if (currentFilters[key]) formData.append(key, currentFilters[key]);
            });
            
            fetch('includes/functions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const preview = document.getElementById('ticketPreview');
                    const ticketList = document.getElementById('ticketList');
                    ticketList.innerHTML = '';
                    
                    if (data.tickets.length === 0) {
                        ticketList.innerHTML = '<p>No tickets found in this set.</p>';
                    } else {
                        const list = document.createElement('ul');
                        data.tickets.forEach(ticket => {
                            const item = document.createElement('li');
                            item.textContent = `${ticket.name} - ${ticket.ticket_count} tickets`;
                            list.appendChild(item);
                        });
                        ticketList.appendChild(list);
                    }
                    
                    preview.style.display = 'block';
                    preview.scrollIntoView({ behavior: 'smooth' });
                } else {
                    alert('Error: ' + (data.message || 'Failed to load tickets'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading tickets');
            });
        }
        
        // Load tickets on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTickets();
            
            // Close modal when clicking outside
            document.getElementById('ticketModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
        });
        
        function loadTickets(page = 1) {
            currentPage = page;
            
            const filters = {
                team_id: document.getElementById('team-filter').value,
                stage_source: document.getElementById('stage-source-filter').value,
                date_from: document.getElementById('date-from-filter').value,
                date_to: document.getElementById('date-to-filter').value
            };
            
            currentFilters = filters;
            
            const formData = new FormData();
            formData.append('action', 'get_raffle_tickets');
            Object.keys(filters).forEach(key => {
                if (filters[key]) formData.append(key, filters[key]);
            });
            formData.append('limit', '1000'); // Show all users (high limit)
            formData.append('offset', (page - 1) * 1000);
            
            fetch('incentives.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayTickets(data.tickets);
                    updatePagination(data.total, page);
                    updateStats(data.tickets);
                } else {
                    document.getElementById('tickets-content').innerHTML = 
                        '<div class="no-data">Error loading tickets: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('tickets-content').innerHTML = 
                    '<div class="no-data">Error loading tickets</div>';
            });
        }
        
        function displayTickets(users) {
            if (users.length === 0) {
                document.getElementById('tickets-content').innerHTML = 
                    '<div class="no-data">No raffle tickets found</div>';
                return;
            }
            
            // Group users by team
            const teamsMap = {};
            users.forEach(user => {
                const teamName = user.team_name || 'No Team';
                const teamId = user.team_id || 'none';
                
                if (!teamsMap[teamId]) {
                    teamsMap[teamId] = {
                        name: teamName,
                        users: [],
                        totalTickets: 0,
                        roles: new Set()
                    };
                }
                
                teamsMap[teamId].users.push(user);
                teamsMap[teamId].totalTickets += parseInt(user.ticket_count);
                if (user.user_role) {
                    teamsMap[teamId].roles.add(user.user_role);
                }
            });
            
            let html = '';
            
            // Display each team section
            Object.keys(teamsMap).sort().forEach(teamId => {
                const team = teamsMap[teamId];
                const rolesList = Array.from(team.roles).join(', ');
                const userCount = team.users.length;
                
                // Team header with summary
                html += '<div class="team-section">';
                html += '<div class="team-header">';
                html += '<h3 class="team-name">' + (team.name || 'No Team') + '</h3>';
                html += '<div class="team-summary">';
                html += '<span class="team-stats">' + team.totalTickets + ' tickets / ' + userCount + ' ' + (userCount === 1 ? 'user' : 'users');
                if (rolesList) {
                    html += ' (' + rolesList + ')';
                }
                html += '</span>';
                html += '</div>';
                html += '</div>';
                
                // Users grid for this team
                html += '<div class="users-grid">';
                
                team.users.forEach(user => {
                    const latestDate = new Date(user.latest_ticket_date).toLocaleDateString();
                    const firstDate = new Date(user.first_ticket_date).toLocaleDateString();
                    
                    html += '<div class="user-card">';
                    html += '<div class="user-header">';
                    html += '<h3 class="user-name">' + user.full_name + '</h3>';
                    html += '<span class="ticket-count">' + user.ticket_count + ' tickets</span>';
                    html += '</div>';
                    
                    html += '<div class="user-info">';
                    if (user.user_role) {
                        html += '<div class="user-detail"><i class="fas fa-user-tag"></i>Role: ' + user.user_role + '</div>';
                    }
                    html += '<div class="user-detail"><i class="fas fa-calendar-alt"></i>First Ticket: ' + firstDate + '</div>';
                    html += '<div class="user-detail"><i class="fas fa-calendar-check"></i>Latest Ticket: ' + latestDate + '</div>';
                    html += '</div>';
                    
                    html += '<div class="user-actions">';
                    html += '<button class="btn btn-primary btn-small" onclick="showUserTicketsModal(' + user.user_id + ', \'' + user.full_name.replace(/'/g, "\\'") + '\')">';
                    html += '<i class="fas fa-eye"></i> View Details';
                    html += '</button>';
                    html += '<button class="btn btn-success btn-small" onclick="printUserTickets(' + user.user_id + ', \'' + user.full_name.replace(/'/g, "\\'") + '\')">';
                    html += '<i class="fas fa-print"></i> Print All';
                    html += '</button>';
                    html += '</div>';
                    
                    html += '</div>';
                });
                
                html += '</div>'; // Close users-grid
                html += '</div>'; // Close team-section
            });
            
            document.getElementById('tickets-content').innerHTML = html;
        }
        
        function getStageSourceClass(stageSource) {
            if (stageSource.includes('Lead Status')) return 'lead-status';
            if (stageSource.includes('DP Stage')) return 'dp-stage';
            if (stageSource.includes('Requirement')) return 'requirement';
            if (stageSource.includes('Spot DP')) return 'spot-dp';
            return '';
        }
        
        function updatePagination(total, currentPage) {
            totalPages = Math.ceil(total / 1000);
            
            // Only show pagination if there's more than one page
            if (totalPages <= 1) {
                return;
            }
            
            let paginationHtml = '<div class="pagination">';
            paginationHtml += '<button onclick="loadTickets(1)" ' + (currentPage === 1 ? 'disabled' : '') + '>First</button>';
            paginationHtml += '<button onclick="loadTickets(' + (currentPage - 1) + ')" ' + (currentPage === 1 ? 'disabled' : '') + '>Previous</button>';
            paginationHtml += '<span>Page ' + currentPage + ' of ' + totalPages + ' (Total: ' + total + ' users)</span>';
            paginationHtml += '<button onclick="loadTickets(' + (currentPage + 1) + ')" ' + (currentPage >= totalPages ? 'disabled' : '') + '>Next</button>';
            paginationHtml += '<button onclick="loadTickets(' + totalPages + ')" ' + (currentPage >= totalPages ? 'disabled' : '') + '>Last</button>';
            paginationHtml += '</div>';
            
            document.getElementById('tickets-content').innerHTML += paginationHtml;
        }
        
        function updateStats(users) {
            let totalTickets = 0;
            let leadStatusTickets = 0;
            let dpStageTickets = 0;
            let requirementTickets = 0;
            
            users.forEach(user => {
                totalTickets += user.ticket_count;
                
                // Parse stage sources from the concatenated string
                const stageSources = user.stage_sources.split(',');
                stageSources.forEach(source => {
                    if (source.includes('Lead Status')) leadStatusTickets++;
                    if (source.includes('DP Stage')) dpStageTickets++;
                    if (source.includes('Requirement')) requirementTickets++;
                });
            });
            
            document.getElementById('total-tickets').textContent = totalTickets;
            document.getElementById('lead-status-tickets').textContent = leadStatusTickets;
            document.getElementById('dp-stage-tickets').textContent = dpStageTickets;
            document.getElementById('requirement-tickets').textContent = requirementTickets;
        }
        
        function applyFilters() {
            loadTickets(1);
        }
        
        function clearFilters() {
            document.getElementById('team-filter').value = '';
            document.getElementById('stage-source-filter').value = '';
            document.getElementById('date-from-filter').value = '';
            document.getElementById('date-to-filter').value = '';
            loadTickets(1);
        }
        
        function refreshTickets() {
            loadTickets(currentPage);
        }
        
        function printTicket(ticketNumber, fullName, phone, email) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Raffle Ticket - ${ticketNumber}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .ticket { border: 2px solid #000; padding: 20px; text-align: center; max-width: 400px; margin: 0 auto; }
                        .ticket h2 { margin: 0 0 20px 0; color: #1e293b; }
                        .ticket-info { margin: 10px 0; font-size: 16px; }
                        .ticket-number { font-family: 'Courier New', monospace; font-weight: bold; color: #3b82f6; font-size: 18px; }
                    </style>
                </head>
                <body>
                    <div class="ticket">
                        <h2>RAFFLE TICKET</h2>
                        <div class="ticket-info">
                            <div class="ticket-number">${ticketNumber}</div>
                        </div>
                        <div class="ticket-info"><strong>Name:</strong> ${fullName}</div>
                        <div class="ticket-info"><strong>Date:</strong> ${new Date().toLocaleDateString()}</div>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        let currentModalUserId = null;
        let currentModalUserName = '';
        
        function showUserTicketsModal(userId, userName) {
            currentModalUserId = userId;
            currentModalUserName = userName;
            
            // Show modal
            const modal = document.getElementById('ticketModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            modalTitle.textContent = userName + ' - Ticket Details';
            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><div>Loading ticket details...</div></div>';
            modal.classList.add('show');
            
            // Fetch user tickets
            fetch('includes/functions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_user_tickets&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUserTicketsInModal(data.tickets, userName);
                } else {
                    modalBody.innerHTML = '<div class="no-tickets"><i class="fas fa-exclamation-triangle"></i><br>Error loading ticket details</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = '<div class="no-tickets"><i class="fas fa-exclamation-triangle"></i><br>Error loading ticket details</div>';
            });
        }
        
        function displayUserTicketsInModal(tickets, userName) {
            const modalBody = document.getElementById('modalBody');
            
            if (tickets.length === 0) {
                modalBody.innerHTML = '<div class="no-tickets"><i class="fas fa-ticket-alt"></i><br>No tickets found for this user</div>';
                return;
            }
            
            // Get user info from first ticket
            const firstTicket = tickets[0];
            
            let html = '<div class="user-info-header">';
            html += '<div class="user-info-grid">';
            html += '<div class="user-info-item"><i class="fas fa-user"></i><span>' + userName + '</span></div>';
            html += '<div class="user-info-item"><i class="fas fa-ticket-alt"></i><span>' + tickets.length + ' Total Tickets</span></div>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="tickets-section">';
            html += '<h3 class="section-title"><i class="fas fa-list"></i> All Tickets</h3>';
            html += '<div class="tickets-grid">';
            
            tickets.forEach(ticket => {
                const stageClass = getStageSourceClass(ticket.stage_source);
                const ticketDate = ticket.modification_date || ticket.created_at;
                const displayDate = new Date(ticketDate).toLocaleDateString();
                
                html += '<div class="ticket-item">';
                html += '<div class="ticket-header">';
                html += '<div class="ticket-number">' + ticket.ticket_number + '</div>';
                html += '<div class="ticket-date">' + displayDate + '</div>';
                html += '</div>';
                html += '<div class="ticket-details">';
                html += '<div class="ticket-info">';
                html += '<div class="ticket-source">' + ticket.stage_source + '</div>';
                if (ticket.client_name) {
                    html += '<div class="ticket-lead">Lead: ' + ticket.client_name + '</div>';
                }
                html += '</div>';
                html += '<span class="stage-source ' + stageClass + '">' + ticket.stage_source.split(':')[0] + '</span>';
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            html += '</div>';
            
            modalBody.innerHTML = html;
        }
        
        function closeModal() {
            const modal = document.getElementById('ticketModal');
            modal.classList.remove('show');
            currentModalUserId = null;
            currentModalUserName = '';
        }
        
        function printAllUserTickets() {
            if (currentModalUserId) {
                printUserTickets(currentModalUserId, currentModalUserName);
            }
        }
        
        function printUserTickets(userId, userName) {
            const formData = new FormData();
            formData.append('action', 'get_user_tickets');
            formData.append('user_id', userId);
            
            fetch('incentives.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const printWindow = window.open('', '_blank');
                    let printContent = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Raffle Tickets - ${userName}</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                .ticket { border: 2px solid #000; padding: 20px; text-align: center; max-width: 400px; margin: 20px auto; page-break-inside: avoid; }
                                .ticket h2 { margin: 0 0 20px 0; color: #1e293b; }
                                .ticket-info { margin: 10px 0; font-size: 16px; }
                                .ticket-number { font-family: 'Courier New', monospace; font-weight: bold; color: #3b82f6; font-size: 18px; }
                                .user-header { text-align: center; margin-bottom: 30px; }
                                .user-header h1 { color: #1e293b; margin-bottom: 10px; }
                                .user-header p { color: #6b7280; }
                            </style>
                        </head>
                        <body>
                            <div class="user-header">
                                <h1>RAFFLE TICKETS</h1>
                                <p><strong>User:</strong> ${userName}</p>
                                <p><strong>Total Tickets:</strong> ${data.tickets.length}</p>
                            </div>
                    `;
                    
                    data.tickets.forEach(ticket => {
                        printContent += `
                            <div class="ticket">
                                <h2>RAFFLE TICKET</h2>
                                <div class="ticket-info">
                                    <div class="ticket-number">${ticket.ticket_number}</div>
                                </div>
                                <div class="ticket-info"><strong>Name:</strong> ${ticket.full_name}</div>
                                <div class="ticket-info"><strong>Source:</strong> ${ticket.stage_source}</div>
                                <div class="ticket-info"><strong>Date:</strong> ${new Date(ticket.modification_date || ticket.created_at).toLocaleDateString()}</div>
                            </div>
                        `;
                    });
                    
                    printContent += '</body></html>';
                    printWindow.document.write(printContent);
                    printWindow.document.close();
                    printWindow.print();
                }
            });
        }
        
        function printAllTickets() {
            const formData = new FormData();
            formData.append('action', 'get_raffle_tickets');
            Object.keys(currentFilters).forEach(key => {
                if (currentFilters[key]) formData.append(key, currentFilters[key]);
            });
            formData.append('limit', '1000'); // Get all tickets
            
            fetch('incentives.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const printWindow = window.open('', '_blank');
                    let printContent = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>All Raffle Tickets</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                .ticket { border: 2px solid #000; padding: 20px; text-align: center; max-width: 400px; margin: 20px auto; page-break-inside: avoid; }
                                .ticket h2 { margin: 0 0 20px 0; color: #1e293b; }
                                .ticket-info { margin: 10px 0; font-size: 16px; }
                                .ticket-number { font-family: 'Courier New', monospace; font-weight: bold; color: #3b82f6; font-size: 18px; }
                            </style>
                        </head>
                        <body>
                    `;
                    
                    data.tickets.forEach(ticket => {
                        printContent += `
                            <div class="ticket">
                                <h2>RAFFLE TICKET</h2>
                                <div class="ticket-info">
                                    <div class="ticket-number">${ticket.ticket_number}</div>
                                </div>
                                <div class="ticket-info"><strong>Name:</strong> ${ticket.full_name}</div>
                                <div class="ticket-info"><strong>Source:</strong> ${ticket.stage_source}</div>
                                <div class="ticket-info"><strong>Date:</strong> ${new Date(ticket.modification_date || ticket.created_at).toLocaleDateString()}</div>
                            </div>
                        `;
                    });
                    
                    printContent += '</body></html>';
                    printWindow.document.write(printContent);
                    printWindow.document.close();
                    printWindow.print();
                }
            });
        }
    </script>
</body>
</html>
