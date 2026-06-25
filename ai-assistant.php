<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Initialize database connection
$connection = getDbConnection();

if (!$connection) {
    die('Database connection failed');
}

// Get overall statistics
$statsQuery = "SELECT 
                COUNT(*) as total_leads,
                SUM(CASE WHEN status = 'Closed Deal' THEN 1 ELSE 0 END) as total_closed,
                SUM(CASE WHEN status = 'Presentation Stage' THEN 1 ELSE 0 END) as presentation_leads,
                SUM(expected_commission) as total_commissions,
                COUNT(DISTINCT user_id) as total_agents
         FROM leads";
$statsResult = mysqli_query($connection, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Get campaigns list grouped by source
$campaignsQuery = "SELECT 
                    source,
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals,
                    SUM(CASE WHEN status = 'Presentation Stage' THEN 1 ELSE 0 END) as presentation_leads,
                    SUM(expected_commission) as total_commission
                  FROM leads
                  GROUP BY source
                  ORDER BY total_leads DESC";
$campaignsResult = mysqli_query($connection, $campaignsQuery);
$campaigns = mysqli_fetch_all($campaignsResult, MYSQLI_ASSOC);

// Get top performer agent
$topAgentQuery = "SELECT u.name, 
                  COUNT(l.id) as total_leads,
                  SUM(CASE WHEN l.status = 'Closed Deal' THEN 1 ELSE 0 END) as closed_deals
           FROM users u
           LEFT JOIN leads l ON u.id = l.user_id
           WHERE u.role = 'agent'
           GROUP BY u.id
           ORDER BY closed_deals DESC
           LIMIT 1";
$topAgentResult = mysqli_query($connection, $topAgentQuery);
$topAgent = mysqli_fetch_assoc($topAgentResult);

// Check if current user is superuser
$isSuperUser = isSuperUser($user['username'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Optimizer - InnerSPARC Lead Management System</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Custom styles to complement style.css for Campaign Optimizer Page */
        .optimizer-page {
            padding: 1.5rem;
            width: 100%;
            max-width: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .card-container {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-tabs {
            display: flex;
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .card-tab {
            padding: 0.875rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-500);
            border-right: 1px solid var(--gray-200);
            cursor: pointer;
            transition: all var(--transition-duration) var(--transition-timing);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: none;
            border: none;
        }
        
        .card-tab.active {
            background-color: white;
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
        }
        
        .card-tab:hover:not(.active) {
            background-color: var(--gray-100);
        }

        .table-controls {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .search-container i {
            position: absolute;
            left: 0.75rem;
            color: var(--gray-400);
            font-size: 0.875rem;
        }
        
        .search-input {
            padding: 0.5rem 1rem 0.5rem 2rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.375rem;
            font-size: 0.875rem;
            width: 240px;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn-system {
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-system:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            color: var(--gray-900);
        }

        .btn-system-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .btn-system-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            color: white;
        }

        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }

        .optimizer-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.875rem;
        }
        
        .optimizer-table th {
            background-color: var(--gray-50);
            padding: 0.75rem 1rem;
            color: var(--gray-500);
            font-weight: 600;
            border-bottom: 1px solid var(--gray-200);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        
        .optimizer-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
            color: var(--gray-900);
        }
        
        .optimizer-table tr:hover {
            background-color: var(--gray-50);
        }

        /* Delivery Toggle */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--gray-300);
            transition: .3s;
            border-radius: 20px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--success);
        }
        
        input:checked + .slider:before {
            transform: translateX(16px);
        }
        
        .delivery-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--success);
        }
        
        .campaign-name-cell {
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
        }
        
        .campaign-name-cell:hover {
            text-decoration: underline;
        }

        /* AI Optimizer Insights Panel */
        .ai-panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .ai-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 1rem;
        }

        .ai-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .ai-title i {
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.3rem;
        }

        .insights-box {
            background-color: var(--gray-50);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
            padding: 1.5rem;
            min-height: 200px;
        }

        /* Shimmer Loading */
        .loading-shimmer {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .shimmer-line {
            height: 16px;
            background: linear-gradient(90deg, var(--gray-100) 25%, var(--gray-200) 50%, var(--gray-100) 75%);
            background-size: 200% 100%;
            animation: loading-shimmer 1.5s infinite linear;
            border-radius: 4px;
        }
        
        .shimmer-line.header-line {
            width: 40%;
            height: 24px;
            margin-bottom: 0.5rem;
        }
        
        .shimmer-line.body-1 { width: 90%; }
        .shimmer-line.body-2 { width: 85%; }
        .shimmer-line.body-3 { width: 95%; }
        .shimmer-line.body-4 { width: 70%; }
        
        @keyframes loading-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="optimizer-page">
                <!-- Header Summary -->
                <div class="dashboard-header" style="margin-bottom: 0.5rem;">
                    <h2>
                        <i class="fa-solid fa-wand-magic-sparkles"></i> AI Campaign Optimizer
                        <?php if ($isSuperUser): ?>
                            <span class="superuser-badge">
                                <i class="fas fa-crown"></i> Super Admin
                            </span>
                        <?php endif; ?>
                    </h2>
                    <div class="header-actions">
                        <button class="btn-system" onclick="location.reload();">
                            <i class="fa-solid fa-rotate"></i> Reset View
                        </button>
                        <button class="btn-system btn-system-primary" onclick="generateAiReport();">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Optimize Campaign
                        </button>
                    </div>
                </div>

                <!-- KPI Cards Ribbon -->
                <div class="summary-cards">
                    <!-- Reach Card -->
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--info-light); color: var(--info);">
                            <i class="fa-solid fa-users-viewfinder"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Reach (Total Leads)</h3>
                            <p><?php echo number_format($stats['total_leads'] ?? 0); ?></p>
                        </div>
                    </div>

                    <!-- Clicks Card -->
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fa-solid fa-hand-pointer"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Presentation Leads</h3>
                            <p><?php echo number_format($stats['presentation_leads'] ?? 0); ?></p>
                        </div>
                    </div>

                    <!-- Conversions Card -->
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="fa-solid fa-bullseye"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Conversions (Closed)</h3>
                            <p><?php echo number_format($stats['total_closed'] ?? 0); ?></p>
                        </div>
                    </div>

                    <!-- Pipeline Value Card -->
                    <div class="summary-card">
                        <div class="summary-icon" style="background: var(--primary-light); color: var(--primary);">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Pipeline Value</h3>
                            <p>₱<?php echo number_format($stats['total_commissions'] ?? 0, 0); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Campaigns Table Card -->
                <div class="card-container">
                    <div class="card-tabs">
                        <div class="card-tab active">
                            <i class="fa-solid fa-folder-open"></i> Campaigns (Sources)
                        </div>
                        <div class="card-tab">
                            <i class="fa-solid fa-layer-group"></i> Ad Sets (Targeting)
                        </div>
                        <div class="card-tab">
                            <i class="fa-solid fa-image"></i> Ads (Creative)
                        </div>
                    </div>
                    
                    <!-- Table Controls -->
                    <div class="table-controls">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" class="search-input" placeholder="Search campaign name..." id="campaignSearch">
                            </div>
                            <button class="btn-system"><i class="fa-solid fa-filter"></i> Filter</button>
                        </div>
                        <div style="color: var(--gray-500); font-size: 0.85rem; font-weight: 500;">
                            <span>Data Updated: Real-time logs</span>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="optimizer-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" checked disabled></th>
                                    <th style="width: 80px;">On/Off</th>
                                    <th>Campaign Name</th>
                                    <th>Delivery</th>
                                    <th>Budget</th>
                                    <th>Results (Closed)</th>
                                    <th>Reach (Leads)</th>
                                    <th>CTR (Click-Through)</th>
                                    <th>Amount Spent (Value)</th>
                                    <th>Conversions (Rate)</th>
                                </tr>
                            </thead>
                            <tbody id="campaignTableBody">
                                <?php if (!empty($campaigns)): ?>
                                    <?php foreach ($campaigns as $campaign): 
                                        $sourceName = !empty($campaign['source']) ? htmlspecialchars($campaign['source']) : 'Direct / Organic';
                                        $ctr = $campaign['total_leads'] > 0 ? round(($campaign['presentation_leads'] / $campaign['total_leads']) * 100, 1) : 0;
                                        $convRate = $campaign['total_leads'] > 0 ? round(($campaign['closed_deals'] / $campaign['total_leads']) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><input type="checkbox" checked></td>
                                            <td>
                                                <label class="toggle-switch">
                                                    <input type="checkbox" checked>
                                                    <span class="slider"></span>
                                                </label>
                                            </td>
                                            <td>
                                                <a href="#" class="campaign-name-cell">
                                                    🎯 Campaign_LeadGen_<?php echo $sourceName; ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="delivery-status">
                                                    <span class="status-dot"></span> Active
                                                </div>
                                            </td>
                                            <td><strong>Using Commission Pipeline</strong></td>
                                            <td><strong><?php echo $campaign['closed_deals']; ?></strong> closed</td>
                                            <td><?php echo number_format($campaign['total_leads']); ?></td>
                                            <td><?php echo $ctr; ?>%</td>
                                            <td>₱<?php echo number_format($campaign['total_commission'], 2); ?></td>
                                            <td><strong><?php echo $convRate; ?>%</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" style="text-align: center; color: var(--gray-500); padding: 30px;">
                                            No campaign logs or lead sources found in database.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- AI Insights Assistant Panel -->
                <div class="ai-panel">
                    <div class="ai-panel-header">
                        <div class="ai-title">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                            <span>Campaign Optimizer AI Assistant</span>
                        </div>
                        <button class="btn-system btn-system-primary" onclick="generateAiReport();">
                            <i class="fa-solid fa-rotate"></i> Refresh AI Report
                        </button>
                    </div>
                    <div class="insights-box" id="insightsContent">
                        <div class="loading-shimmer">
                            <div class="shimmer-line header-line"></div>
                            <div class="shimmer-line body-1"></div>
                            <div class="shimmer-line body-2"></div>
                            <div class="shimmer-line body-3"></div>
                            <div class="shimmer-line body-4"></div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initial AI recommendation pull
            generateAiReport();

            // Simple search filter
            const searchInput = document.getElementById('campaignSearch');
            searchInput.addEventListener('keyup', function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll('#campaignTableBody tr');
                
                rows.forEach(row => {
                    const text = row.querySelector('.campaign-name-cell');
                    if (text) {
                        const campaignName = text.textContent.toLowerCase();
                        if (campaignName.includes(query)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            });
        });

        function generateAiReport() {
            const container = document.getElementById('insightsContent');
            
            // Insert shimmering skeleton loading
            container.innerHTML = `
                <div class="loading-shimmer">
                    <div class="shimmer-line header-line"></div>
                    <div class="shimmer-line body-1"></div>
                    <div class="shimmer-line body-2"></div>
                    <div class="shimmer-line body-3"></div>
                    <div class="shimmer-line body-4"></div>
                </div>
            `;

            fetch('api/ai-insights.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    container.innerHTML = data.insights;
                } else {
                    container.innerHTML = `<p style="color: #e53e3e; font-weight: 600;"><i class="fa-solid fa-circle-exclamation"></i> Error loading suggestions: ${data.error || 'Unknown error'}</p>`;
                }
            })
            .catch(error => {
                console.error('Error fetching insights:', error);
                container.innerHTML = '<p style="color: #e53e3e; font-weight: 600;"><i class="fa-solid fa-circle-exclamation"></i> Failed to reach Campaign Optimizer AI. Please try again later.</p>';
            });
        }
    </script>
</body>
</html>
