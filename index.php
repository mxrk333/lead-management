<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!function_exists('isSuperUser')) {
    function isSuperUser($username) {
        $superusers = [
            'markpatigayon.intern',
            'gabriellibacao.founder', 
            'romeocorberta.itdept'
        ];
        return in_array($username, $superusers);
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Function to check if current user can edit a lead (enhanced with superuser support)
function canEditLead($lead, $current_user_id) {
    global $user; // Access the global user variable
    
    // Check if user is a superuser
    if (isset($user['username']) && isSuperUser($user['username'])) {
        return true;
    }
    
    // User can edit if they are the assigned agent
    return ($lead['user_id'] == $current_user_id);
}

// Get dashboard data based on user role
$dashboardData = getDashboardData($user_id, $user['role']);

// Check if current user is superuser
$isSuperUser = isSuperUser($user['username']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - InnerSPARC Lead Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #e0e7ff;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --border-radius: 0.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-family);
            background-color: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.5;
            margin: 0;
            min-height: 100vh;
            display: flex;
        }
        
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--gray-50);
        }
        
        .dashboard {
            flex: 1;
            padding: 1.5rem;
            width: 100%;
            margin: 0;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }
        
        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }
        
        .dashboard h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dashboard h2 i {
            color: var(--primary);
        }

        .superuser-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            background-color: #10b981;
            color: white;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .superuser-badge i {
            margin-right: 0.25rem;
        }
        
        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }
        
        .stat-icon {
            margin-bottom: 1rem;
            font-size: 1.5rem;
            background: var(--primary-light);
            color: var(--primary);
            width: 3rem;
            height: 3rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-info h3 {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin: 0 0 0.5rem 0;
            font-weight: 500;
        }
        
        .stat-info p {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        /* Recent Leads Section */
        .recent-leads {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .recent-leads-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gray-50);
        }
        
        .recent-leads h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-all {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .view-all:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .leads-table-container {
            overflow: auto;
        }
        
        .leads-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .leads-table th {
            background: var(--gray-50);
            padding: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
            text-align: left;
            white-space: nowrap;
        }
        
        .leads-table td {
            padding: 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .leads-table tr:hover td {
            background: var(--gray-50);
        }
        
        /* Temperature badges */
        .temperature {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .temperature.hot {
            background: var(--danger-light);
            color: var(--danger);
        }
        
        .temperature.warm {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .temperature.cold {
            background: var(--info-light);
            color: var(--info);
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-view, 
        .btn-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            text-decoration: none;
        }
        
        .btn-view {
            background: var(--info-light);
            color: var(--info);
        }
        
        .btn-edit {
            background: var(--warning-light);
            color: var(--warning);
        }

        /* Enhanced gray styling for disabled edit buttons */
        .btn-edit.disabled {
            background-color: #d1d5db !important;
            color: #6b7280 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            opacity: 1 !important;
            border: 1px solid #9ca3af !important;
        }
        
        .btn-view:hover,
        .btn-edit:hover:not(.disabled) {
            transform: translateY(-1px);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .dashboard {
                padding: 1rem;
            }
            
            .dashboard-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .recent-leads {
                margin: 0;
                border-radius: 0;
            }
            
            .recent-leads-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .view-all {
                width: 100%;
                justify-content: center;
            }
            
            .leads-table th.hide-mobile,
            .leads-table td.hide-mobile {
                display: none;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-view,
            .btn-edit {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard">
                <div class="dashboard-header">
                    <h2>
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                        <?php if ($isSuperUser): ?>
                            <span class="superuser-badge">
                                <i class="fas fa-crown"></i> Super Admin
                            </span>
                        <?php endif; ?>
                    </h2>
                </div>
                
                <!-- Stats Section -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Leads</h3>
                            <p><?php echo htmlspecialchars($dashboardData['total_leads']); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Presentation Stage</h3>
                            <p><?php echo htmlspecialchars($dashboardData['presentation_stage']); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Closed Deals</h3>
                            <p><?php echo htmlspecialchars($dashboardData['closed_deals']); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Closed Deal Rate</h3>
                            <p><?php echo htmlspecialchars($dashboardData['closed_deal_rate']); ?>%</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Estimated Portfolio Value</h3>
                            <p>₱<?php echo htmlspecialchars($dashboardData['price']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Leads Section -->
                <div class="recent-leads">
                    <div class="recent-leads-header">
                        <h3><i class="fas fa-list"></i> Recent Leads</h3>
                        <a href="leads.php" class="view-all">
                            View All <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="leads-table-container">
                        <table class="leads-table">
                            <thead>
                                <tr>
                                    <th>Client Name</th>
                                    <th>Temperature</th>
                                    <th>Status</th>
                                    <th class="hide-mobile">Developer</th>
                                    <th class="hide-mobile">Project Model</th>
                                    <th class="hide-mobile">Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($dashboardData['recent_leads'])): ?>
                                    <?php foreach ($dashboardData['recent_leads'] as $lead): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($lead['client_name']); ?></td>
                                            <td>
                                                <span class="temperature <?php echo strtolower($lead['temperature']); ?>">
                                                    <?php echo htmlspecialchars($lead['temperature']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($lead['status']); ?></td>
                                            <td class="hide-mobile"><?php echo htmlspecialchars($lead['developer']); ?></td>
                                            <td class="hide-mobile"><?php echo htmlspecialchars($lead['project_model']); ?></td>
                                            <td class="hide-mobile"><?php echo number_format($lead['price']); ?> PHP</td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="lead-details.php?id=<?php echo $lead['id']; ?>" class="btn-view" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if (canEditLead($lead, $user_id)): ?>
                                                        <a href="edit-lead.php?id=<?php echo $lead['id']; ?>" class="btn-edit" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn-edit disabled" 
                                                                title="You can only edit leads assigned to you" 
                                                                disabled>
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 2rem;">
                                            <div style="color: var(--gray-400);">
                                                <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                                <p>No leads found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
