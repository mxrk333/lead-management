<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Add superuser function if not exists
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

// Check if lead ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: leads.php");
    exit();
}

$lead_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Get lead details
$lead = getLeadById($lead_id, $user_id, $user['role']);

// Check if lead exists and user has permission to view it
if (!$lead) {
    header("Location: leads.php");
    exit();
}

// Get lead history/activities
$activities = getLeadActivities($lead_id);

// Get lead modifications
$modifications = array();
try {
    $conn = getDbConnection();
    $modifications_query = "
        SELECT lm.*, u.name as modifier_name, la.notes as activity_notes
        FROM lead_modifications lm
        LEFT JOIN users u ON lm.user_id = u.id
        LEFT JOIN lead_activities la ON lm.activity_id = la.id
        WHERE lm.lead_id = ?
        ORDER BY lm.created_at DESC
    ";

    $mod_stmt = $conn->prepare($modifications_query);
    if ($mod_stmt) {
        $mod_stmt->bind_param("i", $lead_id);
        $mod_stmt->execute();
        $modifications = $mod_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $mod_stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching modifications: " . $e->getMessage());
    $modifications = array();
}

// Permission checks - Superusers have full access to everything
$canViewFullContact = false;
$isLeadOwner = ($user_id == $lead['user_id']);
$isSuperUser = isSuperUser($user['username']);

// Superusers can ALWAYS see full contact details regardless of ownership
if ($isSuperUser || $isLeadOwner) {
    $canViewFullContact = true;
}

// Store original values - superusers see everything unmasked
$display_phone = $lead['phone'];
$display_email = $lead['email'];
$display_facebook = $lead['facebook'];
$display_linkedin = $lead['linkedin'];

// Only mask for non-superusers who don't own the lead
if (!$isSuperUser && !$isLeadOwner) {
    // Mask phone number
    $length = strlen($lead['phone']);
    if ($length > 6) {
        $visible_start = substr($lead['phone'], 0, 4);
        $visible_end = substr($lead['phone'], -2);
        $masked = str_repeat('*', $length - 6);
        $display_phone = $visible_start . $masked . $visible_end;
    }
    
    // Mask email
    $parts = explode('@', $lead['email']);
    if (count($parts) == 2) {
        $name = $parts[0];
        $domain = $parts[1];
        $masked_name = substr($name, 0, 2) . str_repeat('*', strlen($name) - 2);
        $display_email = $masked_name . '@' . $domain;
    }
    
    // Hide social links
    $display_facebook = '#';
    $display_linkedin = '#';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Details - Real Estate Lead Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Base styles */
        :root {
            --container-padding: 25px;
            --primary-color: #4f46e5;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        @media (max-width: 768px) {
            :root {
                --container-padding: 15px;
            }
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background-color: #f9fafb;
            margin: 0;
        }

        .lead-details-page {
            padding: var(--container-padding);
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-edit, .btn-back {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-edit {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-edit:hover {
            background-color: #4338ca;
        }

        .btn-back {
            background-color: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }

        .btn-back:hover {
            background-color: var(--gray-200);
        }

        .btn-edit i, .btn-back i {
            margin-right: 0.375rem;
        }

        .lead-details-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 1024px) {
            .lead-details-container {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1.5fr);
            }
        }

        .lead-info-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            border: 1px solid rgba(229, 231, 235, 0.5);
        }

        .lead-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .lead-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .temperature {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
        }

        .temperature.hot {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .temperature.warm {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .temperature.cold {
            background-color: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .lead-status {
            display: flex;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .status-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-500);
            margin-right: 0.5rem;
        }

        .status-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            padding: 0.25rem 0.625rem;
            background-color: var(--gray-100);
            border-radius: 0.375rem;
        }

        .lead-info-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .lead-info-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .lead-info-section h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin: 0 0 0.75rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed var(--gray-200);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
        }

        .info-label i {
            margin-right: 0.375rem;
            color: var(--primary-color);
            font-size: 0.875rem;
        }

        .info-value {
            font-size: 0.875rem;
            color: var(--gray-900);
        }

        .info-value a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .info-value a:hover {
            text-decoration: underline;
        }

        .remarks-content {
            font-size: 0.875rem;
            color: var(--gray-700);
            line-height: 1.5;
        }

        .lead-meta {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 0.75rem;
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        @media (min-width: 640px) {
            .lead-meta {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .meta-value {
            color: var(--gray-700);
        }

        .lead-activity-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            border: 1px solid rgba(229, 231, 235, 0.5);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .activity-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .btn-add-activity {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            background-color: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-add-activity:hover {
            background-color: #4338ca;
        }

        .btn-add-activity i {
            margin-right: 0.375rem;
        }

        .activity-form {
            background-color: var(--gray-50);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--gray-200);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.375rem;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            font-size: 0.875rem;
            color: var(--gray-900);
            background-color: white;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-save, .btn-cancel {
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-save {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-save:hover {
            background-color: #4338ca;
        }

        .btn-cancel {
            background-color: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }

        .btn-cancel:hover {
            background-color: var(--gray-200);
        }

        .activity-timeline {
            position: relative;
            padding-left: 1.5rem;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0.5rem;
            width: 2px;
            background-color: var(--gray-200);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .timeline-icon {
            position: absolute;
            top: 0;
            left: -1.5rem;
            width: 1.75rem;
            height: 1.75rem;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            z-index: 1;
        }

        .timeline-content {
            background-color: var(--gray-50);
            border-radius: 0.5rem;
            padding: 1rem;
            border: 1px solid var(--gray-200);
        }

        .timeline-content h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.5rem 0;
        }

        .timeline-content p {
            font-size: 0.875rem;
            color: var(--gray-700);
            margin: 0 0 0.75rem 0;
            line-height: 1.5;
        }

        .timeline-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .meta-user {
            font-weight: 500;
        }

        .no-activities {
            text-align: center;
            padding: 2rem 0;
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .masked-link {
            color: var(--gray-500);
            font-style: italic;
            font-size: 0.875rem;
        }

        .alert-info {
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: #f0f7ff;
            border: 1px solid #bae0ff;
            border-radius: 0.375rem;
            color: #1e40af;
            font-size: 0.875rem;
        }

        .alert-info i {
            margin-right: 0.5rem;
        }

        .alert-success {
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            border-radius: 0.375rem;
            color: #065f46;
            font-size: 0.875rem;
        }

        .alert-danger {
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 0.375rem;
            color: #991b1b;
            font-size: 0.875rem;
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

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .lead-details-page {
                padding: var(--container-padding);
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                width: 100%;
                display: flex;
                gap: 10px;
            }
            
            .btn-edit,
            .btn-back {
                flex: 1;
                justify-content: center;
            }
            
            .lead-info-card,
            .lead-activity-card {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }

        @media (max-width: 576px) {
            .page-header h2 {
                font-size: 1.25rem;
            }
            
            .lead-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .lead-status {
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-save,
            .btn-cancel {
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
            
            <div class="lead-details-page">
                <div class="page-header">
                    <h2>
                        Lead Details
                        <?php if ($isSuperUser): ?>
                            <span class="superuser-badge">
                                <i class="fas fa-crown"></i> Super Admin
                            </span>
                        <?php endif; ?>
                    </h2>
                    <div class="header-actions">
                        <?php if ($isLeadOwner || $isSuperUser): ?>
                            <a href="edit-lead.php?id=<?php echo $lead_id; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit Lead</a>
                        <?php endif; ?>
                        <a href="leads.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Leads</a>
                    </div>
                </div>
                
                <?php if (!$canViewFullContact): ?>
                    <div class="alert-info">
                        <i class="fas fa-info-circle"></i> For privacy reasons, contact information and social media links are only visible to the lead owner.
                        <br>
                        <i class="fas fa-lock"></i> Only the lead owner can edit details or add activities.
                    </div>
                <?php endif; ?>
                
                <div class="lead-details-container">
                    <div class="lead-info-card">
                        <div class="lead-header">
                            <h3><?php echo htmlspecialchars($lead['client_name']); ?></h3>
                            <span class="temperature <?php echo strtolower($lead['temperature']); ?>">
                                <?php if ($lead['temperature'] == 'Hot'): ?>
                                    <i class="fas fa-fire"></i>
                                <?php elseif ($lead['temperature'] == 'Warm'): ?>
                                    <i class="fas fa-sun"></i>
                                <?php else: ?>
                                    <i class="fas fa-snowflake"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($lead['temperature']); ?>
                            </span>
                        </div>
                        
                        <div class="lead-status">
                            <span class="status-label">Status:</span>
                            <span class="status-value"><?php echo htmlspecialchars($lead['status']); ?></span>
                        </div>
                        
                        <div class="lead-info-section">
                            <h4>Contact Information</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-phone"></i> Phone:</span>
                                    <span class="info-value">
                                        <?php if ($canViewFullContact): ?>
                                            <a href="tel:<?php echo htmlspecialchars($display_phone); ?>"><?php echo htmlspecialchars($display_phone); ?></a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($display_phone); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-envelope"></i> Email:</span>
                                    <span class="info-value">
                                        <?php if ($canViewFullContact): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($display_email); ?>"><?php echo htmlspecialchars($display_email); ?></a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($display_email); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($lead['facebook'])): ?>
                                <div class="info-item">
                                    <span class="info-label"><i class="fab fa-facebook"></i> Facebook:</span>
                                    <span class="info-value">
                                        <?php if ($canViewFullContact): ?>
                                            <a href="<?php echo htmlspecialchars($display_facebook); ?>" target="_blank"><?php echo htmlspecialchars($display_facebook); ?></a>
                                        <?php else: ?>
                                            <span class="masked-link">Link hidden for privacy</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($lead['linkedin'])): ?>
                                <div class="info-item">
                                    <span class="info-label"><i class="fab fa-linkedin"></i> LinkedIn:</span>
                                    <span class="info-value">
                                        <?php if ($canViewFullContact): ?>
                                            <a href="<?php echo htmlspecialchars($display_linkedin); ?>" target="_blank"><?php echo htmlspecialchars($display_linkedin); ?></a>
                                        <?php else: ?>
                                            <span class="masked-link">Link hidden for privacy</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="lead-info-section">
                            <h4>Property Interest</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-building"></i> Developer:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($lead['developer']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-home"></i> Project Model:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($lead['project_model']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-tag"></i> Price:</span>
                                    <span class="info-value"><?php echo number_format($lead['price']); ?> PHP</span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-bullhorn"></i> Lead Source:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($lead['source'] ?? 'Not specified'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="lead-info-section">
                            <h4>Remarks</h4>
                            <div class="remarks-content">
                                <?php echo !empty($lead['remarks']) ? nl2br(htmlspecialchars($lead['remarks'])) : 'No remarks added.'; ?>
                            </div>
                        </div>
                        
                        <div class="lead-meta">
                            <div class="meta-item">
                                <span class="meta-label">Created by:</span>
                                <span class="meta-value"><?php echo htmlspecialchars($lead['created_by_name']); ?></span>
                            </div>
                            
                            <div class="meta-item">
                                <span class="meta-label">Created on:</span>
                                <span class="meta-value"><?php echo date('M d, Y h:i A', strtotime($lead['created_at'])); ?></span>
                            </div>
                            
                            <div class="meta-item">
                                <span class="meta-label">Last updated:</span>
                                <span class="meta-value"><?php echo date('M d, Y h:i A', strtotime($lead['updated_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="lead-activity-card">
                        <div class="activity-header">
                            <h3>Lead Activity</h3>
                            <?php if ($isLeadOwner || $isSuperUser): ?>
                                <button id="add-activity-btn" class="btn-add-activity"><i class="fas fa-plus"></i> Add Activity</button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo htmlspecialchars($_GET['error']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert-success">
                                <i class="fas fa-check-circle"></i>
                                Activity added successfully!
                            </div>
                        <?php endif; ?>
                        
                        <div id="activity-form" class="activity-form" style="display: none;">
                            <?php if ($isLeadOwner || $isSuperUser): ?>
                                <form method="POST" action="add-activity.php" id="activityForm">
                                    <input type="hidden" name="lead_id" value="<?php echo htmlspecialchars($lead_id); ?>">
                                    
                                    <div class="form-group">
                                        <label for="activity_type">Activity Type</label>
                                        <select id="activity_type" name="activity_type" required class="form-control">
                                            <option value="">Select Activity Type</option>
                                            <option value="Call">Call</option>
                                            <option value="Email">Email</option>
                                            <option value="Meeting">Meeting</option>
                                            <option value="Presentation">Presentation</option>
                                            <option value="Follow-up">Follow-up</option>
                                            <option value="Site Tour">Site Tour</option>
                                            <option value="Initial Contact">Initial Contact</option>
                                            <option value="Negotiation">Negotiation</option>
                                            <option value="Status Change">Status Change</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="activity_notes">Notes</label>
                                        <textarea id="activity_notes" name="activity_notes" rows="3" required class="form-control"></textarea>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn-save">Save Activity</button>
                                        <button type="button" id="cancel-activity-btn" class="btn-cancel">Cancel</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <div class="activity-timeline">
                            <?php if (!empty($activities)): ?>
                                <?php foreach ($activities as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon">
                                        <?php
                                        $icon = 'fas fa-comment';
                                        switch ($activity['activity_type']) {
                                            case 'Call':
                                                $icon = 'fas fa-phone';
                                                break;
                                            case 'Email':
                                                $icon = 'fas fa-envelope';
                                                break;
                                            case 'Meeting':
                                                $icon = 'fas fa-handshake';
                                                break;
                                            case 'Presentation':
                                                $icon = 'fas fa-file-powerpoint';
                                                break;
                                            case 'Follow-up':
                                                $icon = 'fas fa-reply';
                                                break;
                                            case 'Site Tour':
                                                $icon = 'fas fa-building';
                                                break;
                                            case 'Initial Contact':
                                                $icon = 'fas fa-user-plus';
                                                break;
                                            case 'Negotiation':
                                                $icon = 'fas fa-handshake';
                                                break;
                                            case 'Status Change':
                                                $icon = 'fas fa-exchange-alt';
                                                break;
                                            case 'Downpayment Tracker':
                                                $icon = 'fas fa-money-bill-wave';
                                                break;
                                            default:
                                                $icon = 'fas fa-comment';
                                        }
                                        ?>
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h4><?php echo htmlspecialchars($activity['activity_type']); ?></h4>
                                        <p><?php echo nl2br(htmlspecialchars($activity['notes'])); ?></p>
                                        <div class="timeline-meta">
                                            <span class="meta-user"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                            <span class="meta-date"><?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-activities">
                                    <p>No activities recorded for this lead yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle activity form
        document.getElementById('add-activity-btn')?.addEventListener('click', function() {
            document.getElementById('activity-form').style.display = 'block';
            this.style.display = 'none';
        });
        
        document.getElementById('cancel-activity-btn')?.addEventListener('click', function() {
            document.getElementById('activity-form').style.display = 'none';
            document.getElementById('add-activity-btn').style.display = 'block';
            document.getElementById('activityForm').reset();
        });

        // Form validation
        document.getElementById('activityForm')?.addEventListener('submit', function(e) {
            const activityType = document.getElementById('activity_type').value;
            const activityNotes = document.getElementById('activity_notes').value;
            
            if (!activityType || !activityNotes.trim()) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-danger, .alert-info');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 300);
            });
        }, 5000);
    </script>

    <script src="assets/js/script.js"></script>
</body>
</html>
