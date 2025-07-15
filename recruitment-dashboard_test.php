<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration and functions
require_once 'config/database.php';
require_once 'includes/functions.php';

// Enable error reporting during development
if (!isDreamHost()) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Ensure errors are logged
ini_set('log_errors', 1);
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}
ini_set('error_log', $log_dir . '/php_errors.log');

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Get user data before including sidebar
if (isset($_SESSION['user_id'])) {
    try {
        $conn = getDbConnection();
        $user = getUserById($_SESSION['user_id']);
    } catch (Exception $e) {
        error_log("Error getting user data: " . $e->getMessage());
        $user = null;
    }
}

$current_user_id = $_SESSION['user_id'];
$current_user = getUserById($current_user_id);

// Fetch the current user's team name
$current_user_team = "No Team";
if (!empty($current_user['team_id'])) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT name FROM teams WHERE id = ?");
    $stmt->bind_param("i", $current_user['team_id']);
    $stmt->execute();
    $stmt->bind_result($team_name);
    if ($stmt->fetch()) {
        $current_user_team = $team_name;
    }
    $stmt->close();
    $conn->close();
}

// Check if user has permission to edit users
if ($current_user['role'] != 'admin' && $current_user['role'] != 'manager') {
    header("Location: index.php");
    exit();
}

// Now include the sidebar after all dependencies are loaded
require_once 'includes/sidebar.php';
require_once 'includes/header.php';

// Custom style to ensure header sits next to sidebar
echo '<style>
    .main-header {
        left: var(--sidebar-width);
        width: calc(100% - var(--sidebar-width));
        z-index: 1100;
        transition: left 0.3s ease, width 0.3s ease;
    }
    .sidebar-collapsed .main-header {
        left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
    }
    @media (max-width: 1024px) {
        .main-header {
            left: 0;
            width: 100%;
        }
    }
    #project-modal {
        z-index: 1201;
    }
    #delete-modal {
        z-index: 1300;
    }
</style>';





?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inner SPARC Projects</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Agent Accreditation</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'blue-primary': '#1e40af',
                    'blue-secondary': '#3b82f6',
                    'blue-light': '#dbeafe',
                    'blue-dark': '#1e3a8a'
                }
            }
        }
    }
    </script>
    <style>
    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: #64748b;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #475569;
    }

    /* Utility classes */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Smooth transitions */
    .transition-all {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Card hover effects */
    .property-card {
        transform: translateY(0);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .property-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    /* Focus states for accessibility */
    .focus-ring:focus {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    /* Loading animation */
    .loading-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: .5;
        }
    }

    /* Modal backdrop */
    .modal-backdrop {
        backdrop-filter: blur(8px);
        background-color: rgba(0, 0, 0, 0.6);
    }

    /* Responsive container */
    @media (max-width: 640px) {
        .container {
            padding-left: 1rem;
            padding-right: 1rem;
        }
    }

    /* Filter section responsive */
    @media (max-width: 1024px) {
        .filter-section {
            position: relative !important;
            top: 0 !important;
        }
    }

    /* Search input focus effect */
    .search-input:focus {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Button hover effects */
    .btn-primary {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        transform: translateY(-1px);
    }

    /* Price badge styles */
    .price-badge {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #92400e;
        font-weight: 700;
    }

    /* Priority badge styles */
    .priority-high {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    .priority-medium {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }

    .priority-low {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    /* Responsive adjustment for main container based on sidebar */
    .main-container {
        transition: margin-left 0.3s ease;
    }

    @media (min-width: 1024px) {
        .main-container {
            margin-left: var(--sidebar-width);
        }

        /* When sidebar is collapsed */
        .sidebar-provider.collapsed~.main-container {
            margin-left: var(--sidebar-width-collapsed);
        }
    }

    @media (max-width: 1023.98px) {
        .main-container {
            margin-left: 0 !important;
        }
    }
    </style>

</head>

<body class="bg-gray-50 min-h-screen">
    <!-- Main Container - Add ml-64 for sidebar space -->
    <div id="main-container" class="main-container relative transition-all" style="margin-left: var(--sidebar-width);">


        <!-- Main Content - Right Side -->
        <div class="w-full flex-grow">


            <div class="recruitment-dashboard p-[3%]">
                <!-- Dashboard Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                    <h1 class="text-[32px] font-bold flex items-center gap-2 ">
                        <i class="fas fa-user-plus"></i> Recruitment Management
                    </h1>
                    <div class="flex gap-2 mt-4 md:mt-0">
                        <button type="button"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 flex items-center gap-2"
                            onclick="showAddModal()">
                            <i class="fas fa-plus"></i> Add New Lead
                        </button>
                        <button type="button"
                            class="border border-gray-400 text-gray-700 px-4 py-2 rounded hover:bg-gray-100 flex items-center gap-2"
                            onclick="refreshData()">
                            <i class="fas fa-refresh"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow mb-4">
                    <div class="p-6">
                        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-3 gap-4">
                            <h5 class="text-lg font-semibold flex items-center gap-2 mb-0">
                                <i class="fas fa-filter"></i> Filters
                            </h5>
                            <div class="flex gap-2">
                                <button type="button"
                                    class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700 flex items-center gap-2"
                                    onclick="applyFilters()">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <button type="button"
                                    class="border border-gray-400 text-gray-700 px-3 py-1.5 rounded text-sm hover:bg-gray-100 flex items-center gap-2"
                                    onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear All
                                </button>
                            </div>
                        </div>
                        <!-- Active Filters Display -->
                        <div id="activeFilters" class="mb-3 hidden">
                            <small class="text-gray-500">Active filters:</small>
                            <div id="activeFilterTags" class="mt-1"></div>
                        </div>
                        <!-- Filter Row -->
                        <div class="flex flex-col md:flex-row gap-4">
                            <div class="flex-1">
                                <label for="filterStatus" class="block text-sm font-medium mb-1">Status</label>
                                <select
                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    id="filterStatus">
                                    <option value="">All Statuses</option>
                                    <option value="Accreditation">Accreditation</option>
                                    <option value="Assessment">Assessment</option>
                                    <option value="Product Knowledge System">Product Knowledge Seminar</option>
                                    <option value="Site tour">Site tour</option>
                                    <option value="Onboarding">Onboarding</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label for="filterSource" class="block text-sm font-medium mb-1">Source</label>
                                <select
                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    id="filterSource">
                                    <option value="">All Sources</option>
                                    <option value="Facebook Ads">Facebook Ads</option>
                                    <option value="TikTok ads">TikTok ads</option>
                                    <option value="Google Ads">Google Ads</option>
                                    <option value="Referral">Referral</option>
                                    <option value="Teleprospecting">Teleprospecting</option>
                                    <option value="Video Message">Video Message</option>
                                    <option value="Organic Posting">Organic Posting</option>
                                    <option value="Email Marketing">Email Marketing</option>
                                    <option value="Follow up">Follow up</option>
                                    <option value="Manning">Manning</option>
                                    <option value="Walk in">Walk in</option>
                                    <option value="Flyering">Flyering</option>
                                    <option value="Facebook Groups">Facebook Groups</option>
                                    <option value="KKK">KKK</option>
                                    <option value="Chat Messaging">Chat Messaging</option>
                                    <option value="Landing Page">Landing Page</option>
                                    <option value="Networking Events">Networking Events</option>
                                    <option value="Organic Sharing">Organic Sharing</option>
                                    <option value="Youtube Marketing">Youtube Marketing</option>
                                    <option value="LinkedIn">LinkedIn</option>
                                    <option value="Open House">Open House</option>
                                    <option value="Tiktok">Tiktok</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label for="searchInput" class="block text-sm font-medium mb-1">Search</label>
                                <input type="text"
                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    id="searchInput" placeholder="Search name, email, phone..."
                                    oninput="debouncedApplyFilters()">
                            </div>
                        </div>
                        <!-- Results Summary -->
                        <div id="filterResults" class="mt-3 hidden">
                            <small class="text-gray-500 flex items-center gap-2">
                                <i class="fas fa-info-circle"></i>
                                <span id="resultsCount">0</span> results found
                                <span id="filterTime"></span>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Lead Table -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b">
                        <h5 class="text-lg font-semibold mb-0">Recruitment Leads</h5>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 table-auto" id="recruitmentTable">
                                <thead class="bg-gray-800 text-white">
                                    <tr>
                                        <th class="px-4 py-2 cursor-pointer" onclick="sortTable('created_at')">
                                            Timestamp <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="px-4 py-2 cursor-pointer" onclick="sortTable('full_name')">
                                            Name <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="px-4 py-2">Contact</th>
                                        <th class="px-4 py-2">Email</th>
                                        <th class="px-4 py-2">Recruiter</th>
                                        <th class="px-4 py-2">Status</th>
                                        <th class="px-4 py-2">Source</th>
                                        <th class="px-4 py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="recruitmentTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center py-8">
                                            <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto"
                                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                    stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z">
                                                </path>
                                            </svg>
                                            <p class="mt-2 text-gray-500">Loading recruitment data...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Loading indicator -->
                        <div id="loadingIndicator" class="text-center py-4 hidden">
                            <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Modal (Tailwind) -->
            <div id="recruitmentModal"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50 hidden">
                <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90%] mx-4 overflow-y-auto">
                    <div class="flex justify-between items-center border-b px-6 py-4">
                        <h5 class="text-lg font-semibold" id="modalTitle">Add New Recruitment Lead</h5>
                        <button type="button" class="text-gray-500 hover:text-gray-700 text-2xl font-bold"
                            onclick="hideRecruitmentModal()">&times;</button>
                    </div>
                    <div class="p-6">
                        <form id="recruitmentForm">
                            <input type="hidden" id="leadId" name="id">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="fullName" class="block text-sm font-medium mb-1">Full Name *</label>
                                    <input type="text"
                                        class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        id="fullName" name="full_name" required>
                                </div>
                                <div>
                                    <label for="contactNumber" class="block text-sm font-medium mb-1">Contact Number
                                        *</label>
                                    <input type="text"
                                        class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        id="contactNumber" name="contact_number" required>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="email" class="block text-sm font-medium mb-1">Email</label>
                                    <input type="email"
                                        class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        id="email" name="email">
                                </div>
                                <div>
                                    <label for="recruiterName" class="block text-sm font-medium mb-1">Recruiter
                                        Name</label>
                                    <input type="text" class="border rounded px-3 py-2 w-full bg-gray-100"
                                        id="recruiterName" name="recruiter_name"
                                        value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" readonly
                                        aria-readonly="true">
                                </div>
                                <div>
                                    <label for="teamName" class="block text-sm font-medium mb-1">Team</label>
                                    <?php
                                    $teamName = "No Team";
                                    if (!empty($lead['recruiter_name'])) {
                                        $conn = getDbConnection();
                                        $stmt = $conn->prepare("SELECT t.name FROM users u LEFT JOIN teams t ON u.team_id = t.id WHERE u.name = ? LIMIT 1");
                                        $stmt->bind_param("s", $lead['recruiter_name']);
                                        $stmt->execute();
                                        $stmt->bind_result($foundTeamName);
                                        if ($stmt->fetch() && $foundTeamName) {
                                            $teamName = $foundTeamName;
                                        }
                                        $stmt->close();
                                        $conn->close();
                                    }
                                    ?>
                                    <input type="text" class="border rounded px-3 py-2 w-full bg-gray-100" id="teamName"
                                        name="team_name" value="<?php echo htmlspecialchars($teamName); ?>" readonly
                                        aria-readonly="true">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="status" class="block text-sm font-medium mb-1">Status *</label>
                                    <select
                                        class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="Accreditation">Accreditation</option>
                                        <option value="Assessment">Assessment</option>
                                        <option value="Product Knowledge System">Product Knowledge Seminar</option>
                                        <option value="Site tour">Site tour</option>
                                        <option value="Onboarding">Onboarding</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="source" class="block text-sm font-medium mb-1">Source *</label>
                                    <select
                                        class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        id="source" name="source" required>
                                        <option value="">Select Source</option>
                                        <option value="Facebook Ads">Facebook Ads</option>
                                        <option value="TikTok ads">TikTok ads</option>
                                        <option value="Google Ads">Google Ads</option>
                                        <option value="Referral">Referral</option>
                                        <option value="Teleprospecting">Teleprospecting</option>
                                        <option value="Video Message">Video Message</option>
                                        <option value="Organic Posting">Organic Posting</option>
                                        <option value="Email Marketing">Email Marketing</option>
                                        <option value="Follow up">Follow up</option>
                                        <option value="Manning">Manning</option>
                                        <option value="Walk in">Walk in</option>
                                        <option value="Flyering">Flyering</option>
                                        <option value="Facebook Groups">Facebook Groups</option>
                                        <option value="KKK">KKK</option>
                                        <option value="Chat Messaging">Chat Messaging</option>
                                        <option value="Landing Page">Landing Page</option>
                                        <option value="Networking Events">Networking Events</option>
                                        <option value="Organic Sharing">Organic Sharing</option>
                                        <option value="Youtube Marketing">Youtube Marketing</option>
                                        <option value="LinkedIn">LinkedIn</option>
                                        <option value="Open House">Open House</option>
                                        <option value="Tiktok">Tiktok</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="remarks" class="block text-sm font-medium mb-1">Remarks</label>
                                <textarea
                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    id="remarks" name="remarks" rows="3"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="flex justify-end gap-2 border-t px-6 py-4">
                        <button type="button" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                            onclick="saveRecruitmentLead()">Save</button>
                        <button type="button" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300"
                            onclick="hideRecruitmentModal()">Cancel</button>
                    </div>
                </div>
            </div>

        </div>
    </div>


    <script>
    // Show notification function (Tailwind)
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        let bg = 'bg-green-500',
            icon =
            '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>';
        if (type === 'error') {
            bg = 'bg-red-500';
            icon =
                '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>';
        } else if (type === 'info') {
            bg = 'bg-blue-500';
            icon =
                '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01" /></svg>';
        }
        notification.className =
            `fixed top-6 right-6 flex items-center px-6 py-3 rounded-lg text-white shadow-lg z-[99999] text-base font-medium gap-2 ${bg} animate-fade-in`;
        notification.innerHTML = `${icon}<span>${message}</span>`;
        notification.setAttribute('role', 'alert');
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.classList.add('opacity-0');
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 3000);
    }
    // Fade-in animation for notification
    const style = document.createElement('style');
    style.innerHTML =
        `@keyframes fade-in { from { opacity: 0; transform: translateY(-10px);} to { opacity: 1; transform: translateY(0);} } .animate-fade-in { animation: fade-in 0.3s ease; }`;
    document.head.appendChild(style);

    // -------- Layout responsiveness enhancements --------
    (function() {
        const sidebar = document.getElementById('sidebar');
        const container = document.getElementById('main-container');
        const headerEl = document.querySelector('.main-header');

        if (!sidebar || !container) return;

        function adjustLayout() {
            const desktop = window.innerWidth >= 1024;
            if (desktop) {
                const sideWidth = sidebar.getBoundingClientRect().width;
                container.style.marginLeft = sideWidth + 'px';
                if (headerEl) {
                    headerEl.style.left = sideWidth + 'px';
                    headerEl.style.width = `calc(100% - ${sideWidth}px)`;
                }
            } else {
                container.style.marginLeft = '0';
                if (headerEl) {
                    headerEl.style.left = '0';
                    headerEl.style.width = '100%';
                }
            }
        }

        // Observe sidebar width changes to capture collapse/expand.
        if ('ResizeObserver' in window) {
            const resizeObserver = new ResizeObserver(adjustLayout);
            resizeObserver.observe(sidebar);
        }

        // Adjust on viewport resize as well.
        window.addEventListener('resize', adjustLayout);

        // Initial call
        adjustLayout();
    })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Recruitment Dashboard JavaScript - Clean and Working Version
    const currentSort = {
        column: "created_at",
        order: "DESC"
    }
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
                    showNotification("Error loading data: " + data.message, "error")
                    document.getElementById("recruitmentTableBody").innerHTML =
                        '<tr><td colspan="8" class="text-center text-danger">Error loading data: ' + data.message +
                        '</td></tr>'
                }
            })
            .catch(error => {
                document.getElementById("loadingIndicator").style.display = "none"
                console.error("Error loading data:", error)
                showNotification("Error connecting to server: " + error.message, "error")
                document.getElementById("recruitmentTableBody").innerHTML =
                    '<tr><td colspan="8" class="text-center text-danger">Connection error: ' + error.message +
                    '</td></tr>'
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
            <div class="btn-group btn-group-sm flex items-center justify-center gap-3">
                <button class="btn btn-outline-primary text-yellow-500 border-2 p-1 rounded-md border-yellow-500 hover:border-yellow-500 hover:bg-yellow-500 hover:text-white transition-all" onclick="editLead(${lead.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-outline-danger text-red-500 border-2 p-1 rounded-md border-red-500 hover:border-red-500 hover:bg-red-500 hover:text-white transition-all" onclick="deleteLead(${lead.id})" title="Delete">
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
            showNotification(`Applied ${filterCount} filter${filterCount > 1 ? 's' : ''}`, "success")
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
        showNotification("All filters cleared", "info")
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
        showNotification(`Showing ${value} leads`, "info")
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
            tag.innerHTML =
                `${displayKey}: ${value} <i class="fas fa-times ms-1" onclick="removeFilter('${key}')"></i>`
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
        showNotification("Filter removed", "info");
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
        document.getElementById('modalTitle').textContent = 'Add New Recruitment Lead';
        document.getElementById('recruitmentForm').reset();
        document.getElementById('leadId').value = '';
        document.getElementById('recruiterName').value = "<?php echo htmlspecialchars($user['name'] ?? ''); ?>";
        document.getElementById('recruitmentModal').classList.remove('hidden');
    }

    function hideRecruitmentModal() {
        document.getElementById('recruitmentModal').classList.add('hidden');
    }

    // FIXED: Working edit function
    function editLead(id) {
        console.log('Editing lead with ID:', id);

        // Show loading state
        showNotification("Loading lead data...", "info")

        // Fetch the specific lead data
        const formData = new FormData()
        formData.append("action", "get_recruitment_leads")
        formData.append("filters", JSON.stringify({
            id: id
        }))

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
                    document.getElementById('recruitmentModal').classList.remove('hidden');

                    showNotification("Lead data loaded successfully", "success")
                } else {
                    showNotification("Error: Lead not found or no data returned", "error")
                    console.error('No lead data found:', data)
                }
            })
            .catch(error => {
                console.error("Error fetching lead data:", error)
                showNotification("Error loading lead data: " + error.message, "error")
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
                    showNotification(message, "success")

                    // Hide the modal
                    hideRecruitmentModal();

                    // Refresh the data
                    loadRecruitmentData()
                } else {
                    showNotification("Error: " + (data.message || "Unknown error occurred"), "error")
                }
            })
            .catch(error => {
                // Restore button state
                saveBtn.innerHTML = originalText
                saveBtn.disabled = false

                console.error("Error saving lead:", error)
                showNotification("Error saving lead: " + error.message, "error")
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
                        showNotification(data.message, "success")
                        loadRecruitmentData()
                    } else {
                        showNotification("Error: " + data.message, "error")
                    }
                })
                .catch(error => {
                    console.error("Error deleting lead:", error)
                    showNotification("Error deleting lead", "error")
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
        showNotification("Data refreshed successfully", "success")
    }

    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        let bg = 'bg-green-500',
            icon =
            '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>';
        if (type === 'error') {
            bg = 'bg-red-500';
            icon =
                '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>';
        } else if (type === 'info') {
            bg = 'bg-blue-500';
            icon =
                '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01" /></svg>';
        }
        notification.className =
            `fixed bottom-6 right-6 flex items-center px-6 py-3 rounded-lg text-white shadow-lg z-[99999] text-base font-medium gap-2 ${bg} animate-fade-in`;
        notification.innerHTML = `${icon}<span>${message}</span>`;
        notification.setAttribute('role', 'alert');
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.classList.add('opacity-0');
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 3000);
    }
    </script>
    <script src="assets/js/script.js"></script>
</body>

</html>