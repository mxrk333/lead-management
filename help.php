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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - Inner SPARC Realty Corporation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base styles */
        body {
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background-color: #f9fafb;
            margin: 0;
        }
        
        /* Help page styles */
        .help-page {
            padding: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.025em;
        }
        
        .page-header p {
            font-size: 1rem;
            color: #6b7280;
            margin: 0;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* Tab Navigation - Fixed Layout */
        .tab-navigation {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 2rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            background: white;
            border-radius: 0.5rem 0.5rem 0 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .tab-navigation::-webkit-scrollbar {
            display: none;
        }

        .tab-button {
            padding: 1rem 1.5rem;
            background: white;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            position: relative;
            flex: 1;
            text-align: center;
        }

        .tab-button:hover {
            color: #4f46e5;
            background: rgba(79, 70, 229, 0.05);
        }

        .tab-button.active {
            color: #4f46e5;
            border-bottom-color: #4f46e5;
            background: rgba(79, 70, 229, 0.05);
            font-weight: 600;
        }

        /* Tab Content - Fixed Container with Consistent Height */
        .tab-content-container {
            background: white;
            border-radius: 0 0 0.75rem 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            height: 600px; /* Fixed height for all tabs */
            position: relative;
            overflow: hidden;
        }

        .tab-content {
            display: none;
            padding: 1.5rem;
            opacity: 0;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow-y: auto;
            height: 100%; /* Full height of container */
            box-sizing: border-box;
        }

        .tab-content.active {
            display: block;
            opacity: 1;
            position: relative;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Help cards - Simplified */
        .help-card {
            background: transparent;
            border: none;
            padding: 0;
            box-shadow: none;
            margin: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .help-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0; /* Prevent header from shrinking */
        }
        
        .help-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 0.75rem;
            color: white;
            flex-shrink: 0;
        }
        
        .help-card-icon.faq {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .help-card-icon.docs {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .help-card-icon.contact {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
        }
        
        .help-card h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        .help-card p {
            color: #6b7280;
            line-height: 1.5;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            flex-shrink: 0; /* Prevent description from shrinking */
        }

        /* Content area that can scroll */
        .help-card-content {
            flex: 1;
            overflow-y: auto;
            min-height: 0; /* Allow flex item to shrink */
        }

        /* Quick Links */
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-shrink: 0; /* Prevent quick links from shrinking */
        }

        .quick-link {
            background: #f3f4f6;
            color: #4f46e5;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }

        .quick-link:hover {
            background: #e5e7eb;
            color: #4338ca;
        }

        .quick-link.active {
            background: #4f46e5;
            color: white;
        }
        
        /* FAQ Styles */
        .faq-section {
            margin-top: 0.5rem;
            flex: 1;
            overflow-y: auto;
        }

        .faq-item {
            border-bottom: 1px solid #f3f4f6;
            margin-bottom: 0.5rem;
        }

        .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .faq-question {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 0.75rem 0;
            font-weight: 500;
            color: #374151;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .faq-question:hover {
            color: #4f46e5;
        }

        .faq-question i {
            margin-right: 0.5rem;
            color: #4f46e5;
            font-size: 0.75rem;
            transition: transform 0.2s ease;
            min-width: 12px;
        }

        .faq-item.active .faq-question i {
            transform: rotate(45deg);
        }

        .faq-answer {
            display: none;
            padding: 0 0 0.75rem 1.5rem;
            color: #6b7280;
            line-height: 1.5;
            font-size: 0.8125rem;
        }

        .faq-item.active .faq-answer {
            display: block;
        }

        .faq-answer p {
            margin: 0;
        }

        .faq-answer strong {
            color: #374151;
            font-weight: 600;
        }

        /* User Guide Styles */
        .guide-section {
            margin-top: 0.5rem;
            flex: 1;
            overflow-y: auto;
        }

        .guide-category {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .guide-category:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .guide-category h4 {
            display: flex;
            align-items: center;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 0.5rem 0;
            cursor: pointer;
        }

        .guide-category h4 i {
            margin-right: 0.5rem;
            color: #4f46e5;
            font-size: 0.75rem;
            transition: transform 0.2s ease;
        }

        .guide-category.collapsed .guide-list {
            display: none;
        }

        .guide-category.collapsed h4 i.fa-chevron-down {
            transform: rotate(-90deg);
        }

        .guide-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .guide-list li {
            display: flex;
            align-items: center;
            padding: 0.25rem 0;
            color: #6b7280;
            font-size: 0.8125rem;
            line-height: 1.4;
        }

        .guide-list li i {
            margin-right: 0.5rem;
            color: #4f46e5;
            font-size: 0.625rem;
            min-width: 8px;
        }

        .guide-list li:hover {
            color: #374151;
        }

        /* Developer contact section */
        .developer-contact {
            background: linear-gradient(135deg, #1e3a5f, #2c4d76);
            border-radius: 0.75rem;
            padding: 1.5rem;
            color: white;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .developer-contact h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 0.75rem 0;
        }
        
        .developer-contact p {
            font-size: 0.875rem;
            opacity: 0.9;
            margin: 0 0 1rem 0;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .contact-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .contact-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 0.75rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            text-align: left;
        }
        
        .contact-card:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .contact-card-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .contact-info {
            flex: 1;
        }
        
        .contact-card h4 {
            font-size: 0.875rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }
        
        .contact-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.75rem;
        }
        
        .contact-link {
            color: #93c5fd;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8125rem;
            display: block;
            margin-top: 0.25rem;
        }
        
        .contact-link:hover {
            color: #dbeafe;
            text-decoration: underline;
        }
        
        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 480px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1rem;
            background: white;
            color: #1e3a5f;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s ease;
            border: 2px solid rgba(255, 255, 255, 0.2);
            font-size: 0.875rem;
        }
        
        .action-btn:hover {
            background: #f8fafc;
            transform: translateY(-1px);
            text-decoration: none;
            color: #1e3a5f;
        }
        
        .action-btn i {
            margin-right: 0.5rem;
            font-size: 1rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .help-page {
                padding: 1rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .tab-content {
                padding: 1rem;
            }

            .tab-content-container {
                height: 500px; /* Slightly smaller on mobile */
            }
        }
        
        /* Status indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 500;
            margin-left: auto;
        }
        
        .status-indicator.online {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .status-indicator.busy {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        /* Back to top button */
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4f46e5;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        .back-to-top.visible {
            opacity: 1;
        }

        .back-to-top:hover {
            transform: translateY(-3px);
            background: #4338ca;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="help-page">
                <div class="page-header">
                    <h1>Help & Support</h1>
                    <p>Get assistance with your lead management system</p>
                </div>
                
                <div class="tab-navigation">
                    <button class="tab-button active" data-tab="faq">Frequently Asked Questions</button>
                    <button class="tab-button" data-tab="guide">User Guide</button>
                    <button class="tab-button" data-tab="contact">Developer Contact</button>
                </div>
                
                <div class="tab-content-container">
                    <!-- FAQ Tab -->
                    <div id="faq-tab" class="tab-content active">
                        <div class="help-card">
                            <div class="help-card-header">
                                <div class="help-card-icon faq">
                                    <i class="fas fa-question-circle"></i>
                                </div>
                                <h3>Frequently Asked Questions</h3>
                            </div>
                            <p>Quick answers to the most common questions about using the lead management system.</p>
                            
                            <div class="quick-links">
                                <button class="quick-link active" data-category="all">All</button>
                                <button class="quick-link" data-category="leads">Leads</button>
                                <button class="quick-link" data-category="users">Users & Roles</button>
                                <button class="quick-link" data-category="payments">Payments</button>
                                <button class="quick-link" data-category="system">System</button>
                            </div>
                            
                            <div class="help-card-content">
                                <div class="faq-section">
                                    <div class="faq-item" data-category="leads">
                                        <div class="faq-question">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>How do I add a new lead?</span>
                                        </div>
                                        <div class="faq-answer">
                                            <p>Navigate to the Leads page and click "Add New Lead". Fill in the required fields: Client Name, Phone, Email, Lead Source, Temperature, Status, Developer, Project Model, and Price.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="faq-item" data-category="leads">
                                        <div class="faq-question">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>How do I update a lead's status or temperature?</span>
                                        </div>
                                        <div class="faq-answer">
                                            <p>Go to the lead details page and click "Edit Lead". You can update the status (Inquiry, Presentation Stage, Negotiation, etc.) and temperature (Hot, Warm, Cold).</p>
                                        </div>
                                    </div>
                                    
                                    <div class="faq-item" data-category="users">
                                        <div class="faq-question">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>What are the different user roles and permissions?</span>
                                        </div>
                                        <div class="faq-answer">
                                            <p><strong>Admin:</strong> Full system access, user management, settings<br>
                                            <strong>Manager:</strong> Team management, reports, all leads<br>
                                            <strong>Supervisor:</strong> Team oversight, reports<br>
                                            <strong>Agent:</strong> Own leads, basic functions</p>
                                        </div>
                                    </div>
                                    
                                    <div class="faq-item" data-category="payments">
                                        <div class="faq-question">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>How does the Downpayment Stage tracking work?</span>
                                        </div>
                                        <div class="faq-answer">
                                            <p>Leads with "Downpayment Stage" status appear in the DP Stage section. Track payment schedules, amounts, and completion status. View both in-progress and completed downpayments.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="faq-item" data-category="system">
                                        <div class="faq-question">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>How do notifications work?</span>
                                        </div>
                                        <div class="faq-answer">
                                            <p>You receive notifications for activities on your leads, team activities (if manager/supervisor), and new memos. Click the bell icon to view recent activities.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="faq-item" data-category="leads">
                                        <div class="faq-question">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>How can I search and filter leads?</span>
                                        </div>
                                        <div class="faq-answer">
                                            <p>Use the search bar in the header to search by client name, phone, or email. On the Leads page, filter by status, temperature, source, developer, or date range.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="faq-item" data-category="users">
                                        <div class="faq-question">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>Why can't I see some lead contact information?</span>
                                        </div>
                                        <div class="faq-answer">
                                            <p>For privacy protection, full contact details are only visible to the lead owner. Other team members see masked information unless they have manager/admin privileges.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="faq-item" data-category="system">
                                        <div class="faq-question">
                                            <i class="fas fa-plus-circle"></i>
                                            <span>How do I reset my password?</span>
                                        </div>
                                        <div class="faq-answer">
                                            <p>Contact your system administrator or use the "Forgot Password" link on the login page. Admins can reset passwords through the Users management section.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Guide Tab -->
                    <div id="guide-tab" class="tab-content">
                        <div class="help-card">
                            <div class="help-card-header">
                                <div class="help-card-icon docs">
                                    <i class="fas fa-book"></i>
                                </div>
                                <h3>User Guide</h3>
                            </div>
                            <p>Comprehensive step-by-step guide to help you master all system features and workflows.</p>
                            
                            <div class="help-card-content">
                                <div class="guide-section">
                                    <div class="guide-category">
                                        <h4><i class="fas fa-chevron-down"></i> Getting Started</h4>
                                        <ul class="guide-list">
                                            <li><i class="fas fa-chevron-right"></i> System login and navigation</li>
                                            <li><i class="fas fa-chevron-right"></i> Understanding the dashboard</li>
                                            <li><i class="fas fa-chevron-right"></i> Profile setup and preferences</li>
                                            <li><i class="fas fa-chevron-right"></i> Notification settings</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="guide-category collapsed">
                                        <h4><i class="fas fa-chevron-down"></i> Lead Management</h4>
                                        <ul class="guide-list">
                                            <li><i class="fas fa-chevron-right"></i> Adding new leads with all required fields</li>
                                            <li><i class="fas fa-chevron-right"></i> Lead temperature system (Hot/Warm/Cold)</li>
                                            <li><i class="fas fa-chevron-right"></i> Status progression workflow</li>
                                            <li><i class="fas fa-chevron-right"></i> Editing and updating lead information</li>
                                            <li><i class="fas fa-chevron-right"></i> Lead activity tracking and history</li>
                                            <li><i class="fas fa-chevron-right"></i> Adding activities and notes</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="guide-category collapsed">
                                        <h4><i class="fas fa-chevron-down"></i> Lead Sources & Tracking</h4>
                                        <ul class="guide-list">
                                            <li><i class="fas fa-chevron-right"></i> Available lead sources (Facebook, Google Ads, Referrals, etc.)</li>
                                            <li><i class="fas fa-chevron-right"></i> Developer and project model selection</li>
                                            <li><i class="fas fa-chevron-right"></i> Price formatting and calculations</li>
                                            <li><i class="fas fa-chevron-right"></i> Lead modification history</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="guide-category collapsed">
                                        <h4><i class="fas fa-chevron-down"></i> Downpayment Management</h4>
                                        <ul class="guide-list">
                                            <li><i class="fas fa-chevron-right"></i> Moving leads to downpayment stage</li>
                                            <li><i class="fas fa-chevron-right"></i> Tracking payment schedules</li>
                                            <li><i class="fas fa-chevron-right"></i> Monitoring completion status</li>
                                            <li><i class="fas fa-chevron-right"></i> Viewing completed downpayments</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="guide-category collapsed">
                                        <h4><i class="fas fa-chevron-down"></i> Team Collaboration</h4>
                                        <ul class="guide-list">
                                            <li><i class="fas fa-chevron-right"></i> Understanding user roles and permissions</li>
                                            <li><i class="fas fa-chevron-right"></i> Team lead visibility and privacy</li>
                                            <li><i class="fas fa-chevron-right"></i> Manager team oversight features</li>
                                            <li><i class="fas fa-chevron-right"></i> Memo system for announcements</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="guide-category collapsed">
                                        <h4><i class="fas fa-chevron-down"></i> Reports & Analytics</h4>
                                        <ul class="guide-list">
                                            <li><i class="fas fa-chevron-right"></i> Generating lead reports</li>
                                            <li><i class="fas fa-chevron-right"></i> Performance analytics</li>
                                            <li><i class="fas fa-chevron-right"></i> Team performance tracking</li>
                                            <li><i class="fas fa-chevron-right"></i> Export and sharing options</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="guide-category collapsed">
                                        <h4><i class="fas fa-chevron-down"></i> System Features</h4>
                                        <ul class="guide-list">
                                            <li><i class="fas fa-chevron-right"></i> Search and filtering capabilities</li>
                                            <li><i class="fas fa-chevron-right"></i> Notification management</li>
                                            <li><i class="fas fa-chevron-right"></i> Data privacy and security</li>
                                            <li><i class="fas fa-chevron-right"></i> Mobile responsiveness</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Developer Contact Tab -->
                    <div id="contact-tab" class="tab-content">
                        <div class="help-card">
                            <div class="help-card-header">
                                <div class="help-card-icon contact">
                                    <i class="fas fa-headset"></i>
                                </div>
                                <h3>Developer Contact Information</h3>
                            </div>
                            <p>For technical support, bug reports, feature requests, or any system-related inquiries, please contact our development team.</p>
                            
                            <div class="help-card-content">
                                <div class="developer-contact">
                                    <div class="contact-grid">
                                        <div class="contact-card">
                                            <div class="contact-card-icon">
                                                <i class="fas fa-envelope"></i>
                                            </div>
                                            <div class="contact-info">
                                                <h4>Email Support</h4>
                                                <p style="color: white;">Available 9AM - 5PM</p>
                                                <p style="color: white;">Monday - Friday</p>
                                                <a href="mailto:patigayon.innersparc@gmail.com" class="contact-link">patigayon.innersparc@gmail.com</a>
                                                <a href="mailto:gervacio.innersparc@gmail.com" class="contact-link">gervacio.innersparc@gmail.com</a>
                                                <div class="status-indicator online">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem; margin-right: 0.25rem;"></i>
                                                    Active
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="contact-card">
                                            <div class="contact-card-icon">
                                                <i class="fas fa-phone"></i>
                                            </div>
                                            <div class="contact-info">
                                                <h4>Phone Support</h4>
                                                <p style="color: white;">Available 9AM - 5PM</p>
                                                <p style="color: white;">Monday - Friday</p>
                                                <a href="tel:+639194620030" class="contact-link">+63 919 4620 030</a>
                                                <a href="tel:+639944800720" class="contact-link">+63 994 4800 720</a>
                                                <div class="status-indicator online">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem; margin-right: 0.25rem;"></i>
                                                    Available
                                                </div>
                                            </div>
                                        </div>
                                        
                                      <!--   <div class="contact-card">
                                            <div class="contact-card-icon">
                                                <i class="fab fa-whatsapp"></i>
                                            </div>
                                           <div class="contact-info">
                                                <h4>WhatsApp</h4>
                                                <a href="https://wa.me/639123456789" class="contact-link" target="_blank">+63 912 345 6789</a>
                                                <div class="status-indicator online">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem; margin-right: 0.25rem;"></i>
                                                    Online
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="contact-card">
                                            <div class="contact-card-icon">
                                                <i class="fab fa-telegram"></i>
                                            </div>
                                            <div class="contact-info">
                                                <h4>Telegram</h4>
                                                <a href="https://t.me/innersparc_support" class="contact-link" target="_blank">@innersparc_support</a>
                                                <div class="status-indicator busy">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem; margin-right: 0.25rem;"></i>
                                                    Busy
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="contact-card">
                                            <div class="contact-card-icon">
                                                <i class="fab fa-skype"></i>
                                            </div>
                                            <div class="contact-info">
                                                <h4>Skype</h4>
                                                <a href="skype:innersparc.support?chat" class="contact-link">innersparc.support</a>
                                                <div class="status-indicator online">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem; margin-right: 0.25rem;"></i>
                                                    Available
                                                </div>
                                            </div>
                                        </div>-->
                                        
                                        <div class="contact-card">
                                            <div class="contact-card-icon">
                                                <i class="fas fa-globe"></i>
                                            </div>
                                            <div class="contact-info">
                                                <h4>Website</h4>
                                                <a href="https://www.innersparcrealty.com/" class="contact-link" target="_blank">www.innersparcrealty.com</a>
                                                <div class="status-indicator online">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem; margin-right: 0.25rem;"></i>
                                                    Live
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="quick-actions">
                                        <a href="mailto:markpatigayon440@gmail.com?subject=Lead Management System Support Request" class="action-btn">
                                            <i class="fas fa-paper-plane"></i>
                                            Send Email Now
                                        </a>
                                        <a href="tel:+639194620030" class="action-btn">
                                            <i class="fas fa-phone-alt"></i>
                                            Call Support
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back to top button -->
    <div class="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </div>
    
    <script src="assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Simple and clean tab switching
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Remove active from all buttons
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Remove active from all content
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active to clicked button
                    this.classList.add('active');
                    
                    // Add active to target content
                    const targetContent = document.getElementById(targetTab + '-tab');
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                });
            });
            
            // FAQ Toggle functionality
            document.querySelectorAll('.faq-question').forEach(question => {
                question.addEventListener('click', function() {
                    const faqItem = this.closest('.faq-item');
                    const isActive = faqItem.classList.contains('active');
                    
                    // Close all other FAQ items
                    document.querySelectorAll('.faq-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // Toggle current item
                    if (!isActive) {
                        faqItem.classList.add('active');
                    }
                });
            });
            
            // Guide category toggle
            document.querySelectorAll('.guide-category h4').forEach(header => {
                header.addEventListener('click', function() {
                    const category = this.closest('.guide-category');
                    category.classList.toggle('collapsed');
                });
            });
            
            // Quick links filter
            document.querySelectorAll('.quick-link').forEach(link => {
                link.addEventListener('click', function() {
                    const category = this.getAttribute('data-category');
                    
                    // Update active state
                    document.querySelectorAll('.quick-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Filter FAQ items
                    document.querySelectorAll('.faq-item').forEach(item => {
                        if (category === 'all' || item.getAttribute('data-category') === category) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
            
            // Auto-expand first FAQ item
            const firstFaq = document.querySelector('.faq-item');
            if (firstFaq) {
                firstFaq.classList.add('active');
            }
            
            // Back to top button
            const backToTopButton = document.querySelector('.back-to-top');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopButton.classList.add('visible');
                } else {
                    backToTopButton.classList.remove('visible');
                }
            });
            
            backToTopButton.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Copy email to clipboard functionality
            document.querySelectorAll('a[href^="mailto:"]').forEach(emailLink => {
                emailLink.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    const email = this.href.replace('mailto:', '').split('?')[0];
                    navigator.clipboard.writeText(email).then(() => {
                        // Show temporary notification
                        const notification = document.createElement('div');
                        notification.textContent = 'Email copied to clipboard!';
                        notification.style.cssText = `
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            background: #10b981;
                            color: white;
                            padding: 0.75rem 1rem;
                            border-radius: 0.5rem;
                            z-index: 1000;
                            font-size: 0.875rem;
                            font-weight: 500;
                        `;
                        document.body.appendChild(notification);
                        
                        setTimeout(() => {
                            notification.remove();
                        }, 2000);
                    });
                });
            });
        });
    </script>
</body>
</html>
