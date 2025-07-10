<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
header("Location: login.php");
exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// Check if user variable exists for sidebar
if (!isset($user) && isset($_SESSION['user_id'])) {
$user = getUserById($_SESSION['user_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recruitment Management - InnerSPARC Lead Management System</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* Use your existing CSS variables and styles */
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
--sidebar-width: 280px;
--sidebar-collapsed-width: 80px;
--transition-duration: 0.3s;
--transition-timing: cubic-bezier(0.4, 0, 0.2, 1);
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
overflow-x: hidden;
}

/* CRITICAL: Override Bootstrap container constraints */
.container,
.container-fluid,
.container-sm,
.container-md,
.container-lg,
.container-xl,
.container-xxl {
max-width: none !important;
width: 100% !important;
padding-left: 0 !important;
padding-right: 0 !important;
margin-left: 0 !important;
margin-right: 0 !important;
}

/* FIXED: Remove flex from body and container to match your other pages */
.container {
width: 100vw !important;
min-height: 100vh;
position: relative;
max-width: none !important;
}

/* FIXED: Main content should fill the entire available space */
.main-content {
min-height: 100vh;
background-color: var(--gray-50);
margin-left: var(--sidebar-width);
transition: margin-left var(--transition-duration) var(--transition-timing);
/* CRITICAL: Force full width usage */
width: calc(100vw - var(--sidebar-width)) !important;
max-width: none !important;
position: relative;
overflow-x: hidden;
/* REMOVED: padding-top from here, it will be on .recruitment-dashboard */
padding: 0 !important;
}

/* FIXED: Sidebar collapsed state */
body.sidebar-collapsed .main-content {
margin-left: var(--sidebar-collapsed-width);
width: calc(100vw - var(--sidebar-collapsed-width)) !important;
}

/* FIXED: Dashboard container should use full available width */
.recruitment-dashboard {
padding: 1.5rem;
padding-top: calc(0.75rem + 60px); /* Add padding-top to account for header height */
/* CRITICAL: Force full width */
width: 100% !important;
max-width: none !important;
margin: 0 !important;
min-height: calc(100vh - 100px);
display: flex;
flex-direction: column;
transition: all var(--transition-duration) var(--transition-timing);
box-sizing: border-box;
}

/* Center main content when sidebar is collapsed */
body.sidebar-collapsed .recruitment-dashboard {
max-width: 1200px; /* Adjust this value as needed for your content width */
margin-left: auto;
margin-right: auto;
}

/* Ensure full width on mobile even if sidebar is collapsed */
@media (max-width: 768px) {
body.sidebar-collapsed .recruitment-dashboard {
    max-width: none;
    margin-left: 0;
    margin-right: 0;
}
}

.stats-container {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
gap: 1rem;
margin-bottom: 1.5rem;
width: 100% !important;
}

.stat-card {
background: white;
border-radius: var(--border-radius);
padding: 1.5rem;
box-shadow: var(--shadow);
border: 1px solid var(--gray-200);
transition: all var(--transition-duration) var(--transition-timing);
min-height: 120px;
display: flex;
flex-direction: column;
width: 100% !important;
}

.stat-card:hover {
transform: translateY(-2px);
box-shadow: var(--shadow-md);
}

.card {
border: 1px solid var(--gray-200);
box-shadow: var(--shadow);
/* FIXED: Ensure cards use full width */
width: 100% !important;
max-width: none !important;
}

.card-header {
background-color: var(--gray-50);
border-bottom: 1px solid var(--gray-200);
}

.card-body {
width: 100% !important;
padding: 1.5rem !important;
}

.table {
font-size: 0.875rem;
/* FIXED: Ensure table uses full width */
width: 100% !important;
max-width: none !important;
}

.table-responsive {
/* FIXED: Ensure responsive table container uses full width */
width: 100% !important;
max-width: none !important;
overflow-x: auto;
}

.badge {
font-size: 0.75rem;
}

.bg-danger { background-color: var(--danger) !important; }
.bg-warning { background-color: var(--warning) !important; }
.bg-info { background-color: var(--info) !important; }

/* CRITICAL: Override Bootstrap row and column constraints */
.row {
width: 100% !important;
max-width: none !important;
margin-left: 0 !important;
margin-right: 0 !important;
}

.col,
.col-1, .col-2, .col-3, .col-4, .col-5, .col-6,
.col-7, .col-8, .col-9, .col-10, .col-11, .col-12,
.col-auto,
.col-sm, .col-sm-1, .col-sm-2, .col-sm-3, .col-sm-4, .col-sm-5, .col-sm-6,
.col-sm-7, .col-sm-8, .col-sm-9, .col-sm-10, .col-sm-11, .col-sm-12,
.col-sm-auto,
.col-md, .col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6,
.col-md-7, .col-md-8, .col-md-9, .col-md-10, .col-md-11, .col-md-12,
.col-md-auto,
.col-lg, .col-lg-1, .col-lg-2, .col-lg-3, .col-lg-4, .col-lg-5, .col-lg-6,
.col-lg-7, .col-lg-8, .col-lg-9, .col-lg-10, .col-lg-11, .col-lg-12,
.col-lg-auto,
.col-xl, .col-xl-1, .col-xl-2, .col-xl-3, .col-xl-4, .col-xl-5, .col-xl-6,
.col-xl-7, .col-xl-8, .col-xl-9, .col-xl-10, .col-xl-11, .col-xl-12,
.col-xl-auto,
.col-xxl, .col-xxl-1, .col-xxl-2, .col-xxl-3, .col-xxl-4, .col-xxl-5, .col-xxl-6,
.col-xxl-7, .col-xxl-8, .col-xxl-9, .col-xxl-10, .col-xxl-11, .col-xxl-12,
.col-xxl-auto {
max-width: none !important;
flex: 1 !important;
}

.col-md-3, .col-md-6, .col-md-12 {
padding-left: 0.75rem;
padding-right: 0.75rem;
max-width: none !important;
}

/* FIXED: Form controls should be responsive */
.form-select, .form-control {
width: 100% !important;
max-width: none !important;
}

/* CRITICAL: Force full width on all elements */
.mb-4, .mb-3, .mb-2, .mb-1 {
width: 100% !important;
max-width: none !important;
}

/* FIXED: Mobile responsive adjustments */
@media (max-width: 991px) {
.main-content {
margin-left: 0 !important;
width: 100vw !important;
max-width: none !important;
}

body.sidebar-collapsed .main-content {
margin-left: 0 !important;
width: 100vw !important;
max-width: none !important;
}
}

@media (max-width: 768px) {
.recruitment-dashboard {
padding: 1rem;
padding-top: calc(0.75rem + 60px); /* Adjust for mobile header height */
}

.stats-container {
grid-template-columns: 1fr;
gap: 0.75rem;
}
}

/* CRITICAL: Override any inherited width constraints */
* {
max-width: none !important;
}

/* Exception: Only limit these specific elements */
.btn, .badge, .form-control, .form-select {
max-width: 100% !important;
}

/* Add these animation styles */
.fade-in {
animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
from { opacity: 0; transform: translateY(10px); }
to { opacity: 1; transform: translateY(0); }
}

.btn-group-sm .btn {
padding: 0.25rem 0.5rem;
font-size: 0.75rem;
}

.badge {
font-weight: 500;
}

.badge i {
margin-right: 0.25rem;
}

/* FIXED: Better spacing for filter elements */
.filter-row {
display: grid;
grid-template-columns: 1fr 1fr 1fr; /* Adjusted for 3 columns */
gap: 1rem;
align-items: end;
}

@media (max-width: 768px) {
.filter-row {
grid-template-columns: 1fr;
gap: 0.75rem;
}
}

</style>
</head>

<body>
<div class="container">
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/header.php'; ?> <!-- Header moved back inside main-content -->
    
    <div class="recruitment-dashboard">
        <!-- REMOVED: Old dashboard-header section -->
        <!-- <div class="dashboard-header">
            <h1>
                <i class="fas fa-user-plus"></i> Recruitment Management
            </h1>
            <div class="btn-group">
                <button type="button" class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add New Lead
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="refreshData()">
                    <i class="fas fa-refresh"></i> Refresh
                </button>
            </div>
        </div> -->

        <!-- Removed Stats Container -->
        <!-- <div class="stats-container">...</div> -->

      <div class="dashboard-header">
          <h1>
              <i class="fas fa-user-plus"></i> Recruitment Management
          </h1>
          <div class="btn-group">
              <button type="button" class="btn btn-primary" onclick="showAddModal()">
                  <i class="fas fa-plus"></i> Add New Lead
              </button>
              <button type="button" class="btn btn-outline-secondary" onclick="refreshData()">
                  <i class="fas fa-refresh"></i> Refresh
              </button>
          </div>
      </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-filter"></i> Filters
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary btn-sm" onclick="applyFilters()">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Clear All
                        </button>
                    </div>
                </div>
                
                <!-- Active Filters Display -->
                <div id="activeFilters" class="mb-3" style="display: none;">
                    <small class="text-muted">Active filters:</small>
                    <div id="activeFilterTags" class="mt-1"></div>
                </div>
                
                <!-- FIXED: Better filter layout with proper spacing -->
                <div class="filter-row">
                    <div>
                        <label for="filterStatus" class="form-label">Status</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">All Statuses</option>
                           
                            <option value="Accreditation">Accreditation</Accreditation>
                            <option value="Assessment">Assessment</Assessment>
                            <option value="Product Knowledge System">Product Knowledge Seminar</Product Knowledge System>
                            <option value="Site tour">Site tour</Site tour>
                            <option value="Onboarding">Onboarding</Onboarding>
                            
                          
                        </select>
                    </div>
                    
                    <div>
                        <label for="filterSource" class="form-label">Source</label>
                        <select class="form-select" id="filterSource">
                            <option value="">All Sources</option>
                            <option value="Facebook Ads">Facebook Ads</option>
                            <option value="TikTok ads">TikTok ads</TikTok ads>
                            <option value="Google Ads">Google Ads</Google Ads>
                            <option value="Referral">Referral</Referral>
                            <option value="Teleprospecting">Teleprospecting</Teleprospecting>
                            <option value="Video Message">Video Message</Video Message>
                            <option value="Organic Posting">Organic Posting</Organic Posting>
                            <option value="Email Marketing">Email Marketing</Email Marketing>
                            <option value="Follow up">Follow up</Follow up</Follow up>
                            <option value="Manning">Manning</Manning>
                            <option value="Walk in">Walk in</Walk in>
                            <option value="Flyering">Flyering</Flyering>
                            <option value="Facebook Groups">Facebook Groups</Facebook Groups>
                            <option value="KKK">KKK</KKK>
                            <option value="Chat Messaging">Chat Messaging</Chat Messaging>
                            <option value="Landing Page">Landing Page</Landing Page</Landing Page>
                            <option value="Networking Events">Networking Events</Networking Events>
                            <option value="Organic Sharing">Organic Sharing</Organic Sharing>
                            <option value="Youtube Marketing">Youtube Marketing</Youtube Marketing>
                            <option value="LinkedIn">LinkedIn</LinkedIn>
                            <option value="Open House">Open House</Open House>
                            <option value="Tiktok">Tiktok</Tiktok>
                        </select>
                    </div>
                    <div>
                        <label for="searchInput" class="form-label">Search</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search name, email, phone..." oninput="debouncedApplyFilters()">
                    </div>
                </div>
                
               
                
                <!-- Results Summary -->
                <div id="filterResults" class="mt-3" style="display: none;">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        <span id="resultsCount">0</span> results found
                        <span id="filterTime"></span>
                    </small>
                </div>
            </div>
        </div>

        <!-- Lead Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recruitment Leads</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="recruitmentTable">
                        <thead class="table-dark">
                            <tr>
                                <th onclick="sortTable('created_at')" style="cursor: pointer;">
                                    Timestamp <i class="fas fa-sort"></i>
                                </th>
                                <th onclick="sortTable('full_name')" style="cursor: pointer;">
                                    Name <i class="fas fa-sort"></i>
                                </th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Recruiter</th>
                                
                                <th>Status</th>
                                <th>Source</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recruitmentTableBody">
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading recruitment data...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Loading indicator -->
                <div id="loadingIndicator" class="text-center py-4" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="recruitmentModal" tabindex="-1">
<div class="modal-dialog modal-lg">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="modalTitle">Add New Recruitment Lead</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <form id="recruitmentForm">
                <input type="hidden" id="leadId" name="id">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="fullName" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="fullName" name="full_name" required>
                    </div>
                    <div class="col-md-6">
                        <label for="contactNumber" class="form-label">Contact Number *</label>
                        <input type="text" class="form-control" id="contactNumber" name="contact_number" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="col-md-6">
                        <label for="recruiterName" class="form-label">Recruiter Name</label>
                        <input type="text" class="form-control" id="recruiterName" name="recruiter_name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" readonly aria-readonly="true">
                    </div>
                </div>
                
                
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">Select Status</option>
                            
                            <option value="Accreditation">Accreditation</Accreditation>
                            <option value="Assessment">Assessment</Assessment>
                            <option value="Product Knowledge System">Product Knowledge Seminar</Product Knowledge System>
                            <option value="Site tour">Site tour</Site tour>
                            <option value="Onboarding">Onboarding</Onboarding>
                        
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="source" class="form-label">Source *</label>
                        <select class="form-select" id="source" name="source" required>
                            <option value="">Select Source</option>
                            <option value="Facebook Ads">Facebook Ads</option>
                            <option value="TikTok ads">TikTok ads</TikTok ads>
                            <option value="Google Ads">Google Ads</Google Ads>
                            <option value="Referral">Referral</Referral>
                            <option value="Teleprospecting">Teleprospecting</Teleprospecting>
                            <option value="Video Message">Video Message</Video Message>
                            <option value="Organic Posting">Organic Posting</Organic Posting>
                            <option value="Email Marketing">Email Marketing</Email Marketing>
                            <option value="Follow up">Follow up</Follow up</Follow up>
                            <option value="Manning">Manning</Manning>
                            <option value="Walk in">Walk in</Walk in>
                            <option value="Flyering">Flyering</Flyering>
                            <option value="Facebook Groups">Facebook Groups</Facebook Groups>
                            <option value="KKK">KKK</KKK>
                            <option value="Chat Messaging">Chat Messaging</Chat Messaging>
                            <option value="Landing Page">Landing Page</Landing Page</Landing Page>
                            <option value="Networking Events">Networking Events</Networking Events>
                            <option value="Organic Sharing">Organic Sharing</Organic Sharing>
                            <option value="Youtube Marketing">Youtube Marketing</Youtube Marketing>
                            <option value="LinkedIn">LinkedIn</LinkedIn>
                            <option value="Open House">Open House</Open House>
                            <option value="Tiktok">Tiktok</Tiktok>
                        </select>
                    </div>
                   
                
                <div class="mb-3">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveRecruitmentLead()">Save</button>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Recruitment Dashboard JavaScript - Clean and Working Version
const currentSort = { column: "created_at", order: "DESC" }
let currentFilters = {}
let allLeads = []

// Debounce function to limit how often a function is called
function debounce(func, delay) {
    let timeout;
    return function(...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), delay);
    };
}

// Create a debounced version of applyFilters
const debouncedApplyFilters = debounce(applyFilters, 500); // 500ms delay

// REMOVE THIS ENTIRE BLOCK:
// New debounced function for header search
// const debouncedHeaderSearch = debounce(function() {
//     const headerSearchInput = document.getElementById('headerSearchInput');
//     const mainSearchInput = document.getElementById('searchInput');
//     if (headerSearchInput && mainSearchInput) {
//         mainSearchInput.value = headerSearchInput.value; // Sync header search to main filter search
//     }
//     applyFilters();
// }, 500);


// Initialize dashboard
document.addEventListener("DOMContentLoaded", function() {
    console.log('Recruitment dashboard loading...');
    // Removed loadStats() call
    loadRecruitmentData()

    // Add keyboard shortcuts
    document.addEventListener('keydown', handleKeyboardShortcuts)
})

// Handle keyboard shortcuts
function handleKeyboardShortcuts(e) {
    // Keep Ctrl/Cmd + Enter for explicit search if desired, or remove if live search is preferred
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault()
        applyFilters()
    }
    if (e.key === 'Escape') {
        clearFilters()
    }
}

// Load recruitment data
function loadRecruitmentData() {
console.log('Loading recruitment data with filters:', currentFilters);
const startTime = Date.now()
document.getElementById("loadingIndicator").style.display = "block"

// Show loading state in table
document.getElementById("recruitmentTableBody").innerHTML = `
    <tr>
        <td colspan="8" class="text-center">
            <div class="spinner-border spinner-border-sm" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span class="ms-2">Loading recruitment data...</span>
        </td>
    </tr>
`

const formData = new FormData()
formData.append("action", "get_recruitment_leads")
formData.append("filters", JSON.stringify(currentFilters))
formData.append("sort_by", currentSort.column)
formData.append("sort_order", currentSort.order)

fetch("recruitment-api-debug.php", {
    method: "POST",
    body: formData,
})
.then(response => {
    if (!response.ok) {
        throw new Error('Network response was not ok');
    }
    return response.json();
})
.then(data => {
    const loadTime = Date.now() - startTime
    document.getElementById("loadingIndicator").style.display = "none"
    console.log('Data response:', data);
    
    // Add debug information
    if (data.debug) {
        console.log('Debug info:', data.debug);
        console.log('SQL Query:', data.debug.sql);
        console.log('Parameters:', data.debug.params);
        console.log('Filters sent:', data.debug.filters);
    }

    if (data.success) {
        allLeads = data.data || []
        displayRecruitmentData(allLeads)
        updateActiveFilters()
        updateResultsInfo(allLeads.length, loadTime)
    } else {
        console.error('Data error:', data.message);
        showAlert("Error loading data: " + data.message, "danger")
        document.getElementById("recruitmentTableBody").innerHTML = 
            '<tr><td colspan="8" class="text-center text-danger">Error loading data: ' + data.message + '</td></tr>'
    }
})
.catch(error => {
    document.getElementById("loadingIndicator").style.display = "none"
    console.error("Error loading data:", error)
    showAlert("Error connecting to server: " + error.message, "danger")
    document.getElementById("recruitmentTableBody").innerHTML = 
        '<tr><td colspan="8" class="text-center text-danger">Connection error: ' + error.message + '</td></tr>'
})
}

// Display recruitment data in table
function displayRecruitmentData(leads) {
const tbody = document.getElementById("recruitmentTableBody")
tbody.innerHTML = ""

if (!leads || leads.length === 0) {
    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="text-center text-muted py-4">
                <i class="fas fa-search fa-2x mb-2 d-block"></i>
                <p class="mb-0">No recruitment leads found</p>
                <small>Try adjusting your filters or search terms</small>
            </td>
        </tr>
    `
    return
}

leads.forEach((lead, index) => {
    const row = document.createElement("tr")
    const timestamp = new Date(lead.created_at).toLocaleString()
    // Removed interestBadge
    
    row.style.animationDelay = `${index * 50}ms`
    row.className = 'fade-in'

    row.innerHTML = `
        <td>${timestamp}</td>
        <td><strong>${lead.full_name || 'N/A'}</strong></td>
        <td>${lead.contact_number || 'N/A'}</td>
        <td>${lead.email || 'N/A'}</td>
        <td>${lead.recruiter_name || 'N/A'}</td>
        <td><span class="badge bg-secondary">${lead.status || 'N/A'}</span></td>
        <td><small>${lead.source || 'N/A'}</small></td>
        <td>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="editLead(${lead.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="deleteLead(${lead.id})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    `
    tbody.appendChild(row)
})
}

// Apply filters - Main function
function applyFilters() {
console.log('Applying filters...');

// Show loading feedback
const searchBtn = document.querySelector('button[onclick="applyFilters()"]')
const originalText = searchBtn.innerHTML
searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...'
searchBtn.disabled = true

// Collect filter values
currentFilters = {}
const status = document.getElementById("filterStatus").value.trim()
const source = document.getElementById("filterSource").value.trim()
const search = document.getElementById("searchInput").value.trim() // Get value from main search input

if (status) currentFilters.status = status
if (source) currentFilters.source = source
if (search) currentFilters.search = search

console.log('Applied filters:', currentFilters);

// Load data with filters
loadRecruitmentData()

// Restore button state
setTimeout(() => {
    searchBtn.innerHTML = originalText
    searchBtn.disabled = false
}, 500)

// Show success message
loadRecruitmentData()

// Restore button state
setTimeout(() => {
    searchBtn.innerHTML = originalText
    searchBtn.disabled = false
}, 500)

// Show success message
const filterCount = Object.keys(currentFilters).length
if (filterCount > 0) {
    showAlert(`Applied ${filterCount} filter${filterCount > 1 ? 's' : ''}`, "success")
}
}

// Clear filters
function clearFilters() {
console.log('Clearing all filters...');

currentFilters = {}
document.getElementById("filterStatus").value = ""
document.getElementById("filterSource").value = ""
document.getElementById("searchInput").value = ""
// REMOVE THIS LINE: const headerSearchInput = document.getElementById("headerSearchInput").value = "";

document.getElementById("activeFilters").style.display = "none"
document.getElementById("filterResults").style.display = "none"

loadRecruitmentData()
showAlert("All filters cleared", "info")
}

// Quick filter function
function quickFilter(field, value) {
console.log('Quick filter:', field, value);

currentFilters = {}
currentFilters[field] = value

if (field === 'status') {
    document.getElementById("filterStatus").value = value
    document.getElementById("filterSource").value = ""
}
document.getElementById("searchInput").value = ""
// REMOVE THIS LINE: document.getElementById("headerSearchInput").value = "";

loadRecruitmentData()
showAlert(`Showing ${value} leads`, "info")
}

// Update active filters display
function updateActiveFilters() {
const activeFiltersDiv = document.getElementById("activeFilters")
const activeFilterTags = document.getElementById("activeFilterTags")

if (Object.keys(currentFilters).length === 0) {
  activeFiltersDiv.style.display = "none"
  return
}

activeFiltersDiv.style.display = "block"
activeFilterTags.innerHTML = ""

Object.entries(currentFilters).forEach(([key, value]) => {
  const tag = document.createElement("span")
  tag.className = "badge bg-primary me-2 mb-1"
  tag.style.cursor = "pointer"

  const displayKey = key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
  tag.innerHTML = `${displayKey}: ${value} <i class="fas fa-times ms-1" onclick="removeFilter('${key}')"></i>`
  activeFilterTags.appendChild(tag)
})
}

// Remove individual filter
function removeFilter(key) {
delete currentFilters[key];

const fieldMap = {
  'status': 'filterStatus',
  'source': 'filterSource',
  'search': 'searchInput'
};

if (fieldMap[key]) {
  document.getElementById(fieldMap[key]).value = "";
}
// REMOVE THIS BLOCK:
// If the removed filter was 'search', also clear the header search input
// if (key === 'search') {
//     document.getElementById('headerSearchInput').value = '';
// }

loadRecruitmentData();
showAlert("Filter removed", "info");
}

// Update results info
function updateResultsInfo(count, loadTime) {
const resultsDiv = document.getElementById("filterResults")
const resultsCount = document.getElementById("resultsCount")
const filterTime = document.getElementById("filterTime")

resultsCount.textContent = count
filterTime.textContent = `(loaded in ${loadTime}ms)`
resultsDiv.style.display = "block"
}

// Modal and CRUD functions
function showAddModal() {
document.getElementById("modalTitle").textContent = "Add New Recruitment Lead"
document.getElementById("recruitmentForm").reset()
document.getElementById("leadId").value = ""
// Set recruiter name after form reset
document.getElementById("recruiterName").value = "<?php echo htmlspecialchars($user['name'] ?? ''); ?>";

const modal = new bootstrap.Modal(document.getElementById("recruitmentModal"))
modal.show()
}

// FIXED: Working edit function
function editLead(id) {
console.log('Editing lead with ID:', id);

// Show loading state
showAlert("Loading lead data...", "info")

// Fetch the specific lead data
const formData = new FormData()
formData.append("action", "get_recruitment_leads")
formData.append("filters", JSON.stringify({ id: id }))

fetch("recruitment-api-debug.php", {
  method: "POST",
  body: formData,
})
.then(response => {
  if (!response.ok) {
      throw new Error('Network response was not ok');
  }
  return response.json();
})
.then(data => {
  console.log('Edit lead response:', data);
  
  if (data.success && data.data && data.data.length > 0) {
      const lead = data.data[0]
      
      // Update modal title
      document.getElementById("modalTitle").textContent = "Edit Recruitment Lead"
      
      // Populate form fields
      document.getElementById("leadId").value = lead.id
      document.getElementById("fullName").value = lead.full_name || ""
      document.getElementById("contactNumber").value = lead.contact_number || ""
      document.getElementById("email").value = lead.email || ""
      document.getElementById("recruiterName").value = lead.recruiter_name || ""
      // document.getElementById("interestLevel").value = lead.interest_level || "" // Removed
      document.getElementById("status").value = lead.status || ""
      document.getElementById("source").value = lead.source || ""
      // Removed this line as 'agentOnboardingStatus' input is no longer in the form
      // document.getElementById("agentOnboardingStatus").value = lead.agent_onboarding_status || "" 
      document.getElementById("remarks").value = lead.remarks || ""

      // Show the modal
      const modal = new bootstrap.Modal(document.getElementById("recruitmentModal"))
      modal.show()
      
      showAlert("Lead data loaded successfully", "success")
  } else {
      showAlert("Error: Lead not found or no data returned", "danger")
      console.error('No lead data found:', data)
  }
})
.catch(error => {
  console.error("Error fetching lead data:", error)
  showAlert("Error loading lead data: " + error.message, "danger")
})
}

// Save recruitment lead (add or update)
function saveRecruitmentLead() {
const form = document.getElementById("recruitmentForm")
const formData = new FormData(form)

// Get the lead ID to determine if this is an add or update
const leadId = document.getElementById("leadId").value
const isUpdate = leadId && leadId.trim() !== ""
const action = isUpdate ? "update_recruitment_lead" : "add_recruitment_lead"

formData.append("action", action)
if (isUpdate) {
  formData.append("id", leadId)
}

// Show loading state
const saveBtn = document.querySelector('button[onclick="saveRecruitmentLead()"]')
const originalText = saveBtn.innerHTML
saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'
saveBtn.disabled = true;

console.log('Saving lead with action:', action, 'ID:', leadId);

fetch("recruitment-api-debug.php", {
  method: "POST",
  body: formData,
})
.then(response => {
  if (!response.ok) {
      throw new Error('Network response was not ok');
  }
  return response.json();
})
.then(data => {
  console.log('Save response:', data);
  
  // Restore button state
  saveBtn.innerHTML = originalText
  saveBtn.disabled = false
  
  if (data.success) {
      const message = isUpdate ? "Lead updated successfully!" : "Lead added successfully!"
      showAlert(message, "success")
      
      // Hide the modal
      const modalInstance = bootstrap.Modal.getInstance(document.getElementById("recruitmentModal"))
      if (modalInstance) {
          modalInstance.hide()
      }
      
      // Refresh the data
      loadRecruitmentData()
  } else {
      showAlert("Error: " + (data.message || "Unknown error occurred"), "danger")
  }
})
.catch(error => {
  // Restore button state
  saveBtn.innerHTML = originalText
  saveBtn.disabled = false
  
  console.error("Error saving lead:", error)
  showAlert("Error saving lead: " + error.message, "danger")
})
}

function deleteLead(id) {
if (confirm("Are you sure you want to delete this recruitment lead?")) {
  const formData = new FormData()
  formData.append("action", "delete_recruitment_lead")
  formData.append("id", id)

  fetch("recruitment-api-debug.php", {
      method: "POST",
      body: formData,
  })
  .then(response => response.json())
  .then(data => {
      if (data.success) {
          showAlert(data.message, "success")
          loadRecruitmentData()
      } else {
          showAlert("Error: " + data.message, "danger")
      }
  })
  .catch(error => {
      console.error("Error deleting lead:", error)
      showAlert("Error deleting lead", "danger")
  })
}
}

function sortTable(column) {
if (currentSort.column === column) {
  currentSort.order = currentSort.order === "ASC" ? "DESC" : "ASC"
} else {
  currentSort.column = column
  currentSort.order = "ASC"
}
loadRecruitmentData()
}

function refreshData() {
console.log('Refreshing all data...');
loadRecruitmentData()
showAlert("Data refreshed successfully", "success")
}

function showAlert(message, type) {
const alertDiv = document.createElement("div")
alertDiv.className = `alert alert-${type} alert-dismissible fade show`
alertDiv.innerHTML = `
  <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'}"></i>
  ${message}
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
`

const dashboard = document.querySelector(".recruitment-dashboard")
dashboard.insertBefore(alertDiv, dashboard.firstChild)

setTimeout(() => {
  if (alertDiv.parentNode) {
      alertDiv.remove()
  }
}, 5000)
}
</script>
<script src="assets/js/script.js"></script>

<script>
// -------- Layout responsiveness enhancements --------
(function () {
const sidebar = document.getElementById('sidebar');
const mainContent = document.querySelector('.main-content');
const headerEl = document.querySelector('.main-header');

if (!sidebar || !mainContent || !headerEl) return;

function adjustLayout() {
  const desktop = window.innerWidth >= 992;
  const sideWidth = sidebar.getBoundingClientRect().width;
  
  if (desktop) {
      mainContent.style.marginLeft = sideWidth + 'px';
      mainContent.style.width = `calc(100vw - ${sideWidth}px)`;
      // Header is now inside main-content, so it will naturally inherit its parent's position and width.
      // Remove manual positioning for header here.
      headerEl.style.left = '0'; // Reset if previously set
      headerEl.style.width = '100%'; // Reset if previously set
  } else {
      mainContent.style.marginLeft = '0';
      mainContent.style.width = '100vw';
      headerEl.style.left = '0';
      headerEl.style.width = '100%';
  }
}

if ('ResizeObserver' in window) {
  const resizeObserver = new ResizeObserver(entries => {
      // Only adjust if the sidebar's width actually changed
      for (let entry of entries) {
          if (entry.target === sidebar) {
              adjustLayout();
              break;
          }
      }
  });
  resizeObserver.observe(sidebar);
}

window.addEventListener('resize', adjustLayout);
adjustLayout(); // Initial call
})();
</script>

<script>
// REMOVED: Duplicate layout responsiveness script. The one above is sufficient.
</script>
</body>
</html>
