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
    // Check if raffle_tickets table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'raffle_tickets'");
    if ($table_check->num_rows == 0) {
        error_log("raffle_tickets table does not exist");
        $tickets_data = ['success' => false, 'message' => 'Raffle tickets table not found. Please contact administrator.'];
    } else {
        $tickets_data = getRaffleTickets([], 50, 0);
        error_log("Initial tickets data loaded successfully");
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
        
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
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
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            animation: modalSlideIn 0.3s ease-out;
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
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        
        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 24px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 24px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .user-info-header {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .user-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-info-item i {
            color: #2563eb;
            font-size: 18px;
            width: 20px;
        }
        
        .user-info-item span {
            font-weight: 600;
            color: #374151;
        }
        
        .tickets-section {
            margin-top: 24px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tickets-grid {
            display: grid;
            gap: 12px;
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
            padding: 20px 24px;
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
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
        }
        
        .pagination button {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #f3f4f6;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
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
                        <button class="btn btn-success" onclick="printAllTickets()">
                            <i class="fas fa-print"></i> Print All Tickets
                        </button>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
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
                
                <!-- Filters Section -->
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
                
                <!-- Users Grid -->
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
                            <i class="fas fa-spinner fa-spin"></i> Loading tickets...
                        </div>
                    </div>
                </div>
                
                <!-- Modal for Ticket Details -->
                <div id="ticketModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title" id="modalTitle">Ticket Details</h2>
                            <button class="modal-close" onclick="closeModal()">&times;</button>
                        </div>
                        <div class="modal-body" id="modalBody">
                            <!-- Modal content will be loaded here -->
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
    
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let currentFilters = {};
        
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
            formData.append('limit', '50');
            formData.append('offset', (page - 1) * 50);
            
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
            
            let html = '<div class="users-grid">';
            
            users.forEach(user => {
                const latestDate = new Date(user.latest_ticket_date).toLocaleDateString();
                const firstDate = new Date(user.first_ticket_date).toLocaleDateString();
                
                html += '<div class="user-card">';
                html += '<div class="user-header">';
                html += '<h3 class="user-name">' + user.full_name + '</h3>';
                html += '<span class="ticket-count">' + user.ticket_count + ' tickets</span>';
                html += '</div>';
                
                html += '<div class="user-info">';
                html += '<div class="user-detail"><i class="fas fa-calendar-alt"></i>First Ticket: ' + firstDate + '</div>';
                html += '<div class="user-detail"><i class="fas fa-calendar-check"></i>Latest Ticket: ' + latestDate + '</div>';
                html += '</div>';
                
                html += '<div class="user-actions">';
                html += '<button class="btn btn-primary btn-small" onclick="showUserTicketsModal(' + user.user_id + ', \'' + user.full_name + '\')">';
                html += '<i class="fas fa-eye"></i> View Details';
                html += '</button>';
                html += '<button class="btn btn-success btn-small" onclick="printUserTickets(' + user.user_id + ', \'' + user.full_name + '\')">';
                html += '<i class="fas fa-print"></i> Print All';
                html += '</button>';
                html += '</div>';
                
                html += '</div>';
            });
            
            html += '</div>';
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
            totalPages = Math.ceil(total / 50);
            
            let paginationHtml = '<div class="pagination">';
            paginationHtml += '<button onclick="loadTickets(1)" ' + (currentPage === 1 ? 'disabled' : '') + '>First</button>';
            paginationHtml += '<button onclick="loadTickets(' + (currentPage - 1) + ')" ' + (currentPage === 1 ? 'disabled' : '') + '>Previous</button>';
            paginationHtml += '<span>Page ' + currentPage + ' of ' + totalPages + '</span>';
            paginationHtml += '<button onclick="loadTickets(' + (currentPage + 1) + ')" ' + (currentPage === totalPages ? 'disabled' : '') + '>Next</button>';
            paginationHtml += '<button onclick="loadTickets(' + totalPages + ')" ' + (currentPage === totalPages ? 'disabled' : '') + '>Last</button>';
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
                        <div class="ticket-info"><strong>Date:</strong> ${new Date(ticket.modification_date || ticket.created_at).toLocaleDateString()}</div>
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
            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading ticket details...</div>';
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