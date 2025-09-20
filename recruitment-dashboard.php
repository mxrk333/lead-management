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

// Fetch all teams for admin filter dropdown
$teams = [];
if ($current_user['role'] === 'admin') {
    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT id, name FROM teams ORDER BY name");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $teams[] = $row;
        }
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Error fetching teams: " . $e->getMessage());
        $teams = [];
    }
}

// Check if user has permission to edit users
if ($current_user['role'] != 'admin' && $current_user['role'] != 'manager') {
    header("Location: index.php");
    exit();
}


// Get recruitment statistics
$recruitmentStats = getRecruitmentStats($current_user_id, $current_user['role']);

// Now include the sidebar after all dependencies are loaded
$hide_add_button = true;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

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
    
    /* Enhanced Recruitment Progress Button Styles */
    .recruitment-progress-btn {
        position: relative;
        overflow: hidden;
    }
    
    .recruitment-progress-btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: width 0.3s ease, height 0.3s ease;
        z-index: 1;
    }
    
    .recruitment-progress-btn:hover::before {
        width: 200px;
        height: 200px;
    }
    
    .recruitment-progress-btn:active {
        transform: scale(0.95);
        transition: transform 0.1s ease;
    }
    
    /* Pulse effect for interactive feedback */
    .recruitment-progress-btn:hover {
        animation: subtle-pulse 1.5s infinite;
    }
    
    @keyframes subtle-pulse {
        0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
        70% { box-shadow: 0 0 0 6px rgba(59, 130, 246, 0); }
        100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
    }
    
    /* Orange pulse for post-recruitment buttons */
    .recruitment-progress-btn.orange:hover {
        animation: subtle-pulse-orange 1.5s infinite;
    }
    
    @keyframes subtle-pulse-orange {
        0% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4); }
        70% { box-shadow: 0 0 0 6px rgba(249, 115, 22, 0); }
        100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0); }
    }
    
    /* Smooth text transitions */
    .recruitment-progress-btn span {
        position: relative;
        z-index: 2;
        transition: all 0.3s ease;
    }
    
    /* Icon animation */
    .recruitment-progress-btn .fas {
        transition: transform 0.3s ease;
    }
    
    .recruitment-progress-btn:hover .fas {
        transform: scale(1.1) rotate(5deg);
    }
    
    /* Checked state enhancements */
    .recruitment-progress-btn.checked {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    /* Enhanced Card Animations */
    @keyframes slideInUp {
        0% {
            opacity: 0;
            transform: translateY(30px) scale(0.95);
        }
        100% {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    @keyframes float {
        0%, 100% {
            transform: translateY(0px);
        }
        50% {
            transform: translateY(-5px);
        }
    }
    
    /* Card hover animations */
    .modern-agent-card:hover {
        animation: float 3s ease-in-out infinite;
    }
    
    /* Custom scrollbar styles */
    .scrollbar-thin::-webkit-scrollbar {
        width: 6px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    .scrollbar-thumb-gray-300::-webkit-scrollbar-thumb {
        background: #d1d5db;
    }
    
    .scrollbar-track-gray-100::-webkit-scrollbar-track {
        background: #f3f4f6;
    }
    
    /* Glass morphism effect for filters */
    .backdrop-blur-sm {
        backdrop-filter: blur(4px);
    }
    
    /* Enhanced button animations */
    .group\/btn:hover {
        transform: translateY(-2px) scale(1.05);
    }
    
    .group\/btn:active {
        transform: translateY(0) scale(0.98);
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
                    <h1 class="text-[32px] font-semibold flex items-center gap-2 ">
                        <i class="fas fa-user-plus text-blue-600"></i> Recruitment Management
                    </h1>
                    <div class="flex gap-2 mt-4 md:mt-0">
                        <button type="button"
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2"
                            onclick="console.log('Button clicked'); showAddModal();">
                            <i class="fas fa-plus"></i> New Agent
                        </button>
                        <button type="button"
                            class="border border-gray-400 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-100 flex items-center gap-2"
                            onclick="refreshData()">
                            <i class="fas fa-refresh"></i> Refresh
                        </button>
                    </div>
                </div>
<!-- Statistics Grid -->
<div class="container mx-auto px-4">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-6">
        <!-- Total Recruited Agents -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Total Agents</p>
                    <p id="totalAgentsStat" class="text-3xl font-bold text-gray-900">
                        <?php echo $recruitmentStats['total_recruited']; ?>
                    </p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Active Agents -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Active Agents</p>
                    <p id="activeAgentsStat" class="text-3xl font-bold text-green-600">
                        <?php echo $recruitmentStats['active_agents']; ?>
                    </p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-check text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Inactive Agents -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Inactive Agents</p>
                    <p id="inactiveAgentsStat" class="text-3xl font-bold text-red-600">
                        <?php echo $recruitmentStats['inactive_agents']; ?>
                    </p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-times text-red-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Onboarded Agents -->
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Onboarded</p>
                    <p id="onboardedAgentsStat" class="text-3xl font-bold text-purple-600">
                        <?php echo $recruitmentStats['onboarded_agents']; ?>
                    </p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-rocket text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Combined Filters and Agents Card -->
<div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
    <!-- Enhanced Header Section -->
    <div class="bg-blue-600 px-8 py-6 text-white">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-700 rounded-xl flex items-center justify-center shadow-sm">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold mb-1">Recruited Agents</h2>
                    <p class="text-blue-100 text-sm">Manage and filter your recruitment database</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                    class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center gap-2 border border-blue-500"
                    onclick="applyFilters()">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="button"
                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center gap-2 border border-gray-500"
                    onclick="clearFilters()">
                    <i class="fas fa-times"></i> Clear All
                </button>
            </div>
        </div>
    </div>
</div>


                    <!-- Enhanced Filters Section -->
                    <div class="p-6 bg-gray-50 border-b border-gray-200">
                        <!-- Active Filters Display -->
                        <div id="activeFilters" class="mb-4 hidden">
                            <p class="text-sm font-medium text-gray-600 mb-2 flex items-center gap-2">
                                <i class="fas fa-filter text-blue-500"></i>
                                Active Filters:
                            </p>
                            <div id="activeFilterTags" class="flex flex-wrap gap-2"></div>
                        </div>

                        <!-- Filter Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                            <!-- Search Filter -->
                            <div class="xl:col-span-2">
                                <label for="searchInput" class="block text-sm font-semibold text-gray-700 mb-2">Search Agent</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                    <input type="text"
                                        class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 bg-white shadow-sm"
                                        id="searchInput" placeholder="Name, email, or phone..."
                                        oninput="debouncedApplyFilters()">
                                </div>
                            </div>

                            <!-- Activity Status Filter -->
                            <div>
                                <label for="filterStatus" class="block text-sm font-semibold text-gray-700 mb-2">Activity Status</label>
                                <select class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 bg-white shadow-sm"
                                    id="filterStatus" onchange="applyFilters()">
                                    <option value="">All Status</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>

                            <!-- Recruitment Status Filter -->
                            <div>
                                <label for="filterRecruitmentStatus" class="block text-sm font-semibold text-gray-700 mb-2">Onboarding</label>
                                <select class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 bg-white shadow-sm"
                                    id="filterRecruitmentStatus" onchange="applyFilters()">
                                    <option value="">All Agents</option>
                                    <option value="1">Onboarded</option>
                                    <option value="0">Not Onboarded</option>
                                </select>
                            </div>

                            <!-- Year Filter -->
                            <div>
                                <label for="filterYear" class="block text-sm font-semibold text-gray-700 mb-2">Year</label>
                                <select class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 bg-white shadow-sm"
                                    id="filterYear" onchange="applyFilters()">
                                    <option value="">All Years</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                            </div>

                            <!-- Month Filter -->
                            <div>
                                <label for="filterMonth" class="block text-sm font-semibold text-gray-700 mb-2">Month</label>
                                <select class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 bg-white shadow-sm"
                                    id="filterMonth" onchange="applyFilters()">
                                    <option value="">All Months</option>
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>

                            <?php if ($user['role'] === 'admin'): ?>
                            <!-- Team Filter (Admin Only) -->
                            <div class="xl:col-span-1">
                                <label for="filterTeam" class="block text-sm font-semibold text-gray-700 mb-2">Team</label>
                                <select class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 bg-white shadow-sm"
                                    id="filterTeam" onchange="applyFilters()">
                                    <option value="">All Teams</option>
                                    <?php foreach ($teams as $team): ?>
                                    <option value="<?= htmlspecialchars($team['id']) ?>">
                                        <?= htmlspecialchars($team['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Results Summary -->
                        <div id="filterResults" class="mt-4 hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <p class="text-sm text-blue-700 flex items-center gap-2">
                                    <i class="fas fa-info-circle"></i>
                                    Found <span id="resultsCount" class="font-semibold">0</span> agents
                                    <span id="filterTime" class="text-blue-500"></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Agents Grid Section -->
                    <div class="p-6">
                        <div class="max-h-[800px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                            <!-- Enhanced Cards Container -->
                            <div id="recruitmentTableBody" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <!-- Enhanced Loading state -->
                                <div class="col-span-full flex flex-col items-center justify-center py-16">
                                    <div class="relative">
                                        <div class="w-16 h-16 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <i class="fas fa-users text-blue-600 text-lg"></i>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 text-lg font-medium mt-4">Loading recruitment data...</p>
                                    <p class="text-gray-500 text-sm">Please wait while we fetch agent information</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Enhanced Loading indicator -->
                        <div id="loadingIndicator" class="text-center py-8 hidden">
                            <div class="inline-flex items-center gap-3">
                                <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                                <span class="text-gray-600 font-medium">Updating results...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onboard Modal -->
                <div id="onboardModal"
                    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50 hidden">
                    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90%] mx-4 overflow-y-auto">
                        <div class="flex justify-between items-center border-b px-6 py-4">
                            <h5 class="text-lg font-semibold" id="onboardTitle">Onboarding</h5>
                            <i class="fa-solid fa-user-hat-tie"></i>
                            <button type="button" class="text-gray-500 hover:text-gray-700 text-2xl font-bold"
                                onclick="hideOnboardModal()">&times;</button>
                        </div>
                        <div class="p-0">
                            <!-- Inside your onboardModal .p-0 div -->
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- Add/Edit Modal (Tailwind) -->
            <div id="recruitmentModal"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50 hidden">
                <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90%] mx-4">
                    <div class="max-h-[90%]">
                        <div class="flex justify-between items-center border-b px-6 py-4">
                            <h5 class="text-lg font-semibold" id="modalTitle">Add New Recruited Agent</h5>
                            <button type="button" class="text-gray-500 hover:text-gray-700 text-2xl font-bold"
                                onclick="hideRecruitmentModal()">&times;</button>
                        </div>
                        <div class="p-6 overflow-y-auto max-h-[76vh]">

                            <div
                                class="flex flex-row gap-2 mb-4 p-2 bg-yellow-50 border-l-2 border-yellow-200 rounded-md text-sm font-mono text-yellow-800">
                                <span class="font-semibold">Note:</span>
                                <span>Kindly verify and complete the agent's information accurately to ensure data
                                    reliability.</span>
                            </div>

                            <form id="recruitmentForm">
                                <?php

                                $required_mark = '<span class="text-[12px] text-red-500">*</span>';
                                $automatic_mark = '<span class="text-[12px] text-gray-500">(Automatic)</span>';
                                $optional_mark = '<span class="text-[12px] text-gray-500">(Optional)</span>';
                                $label_name_style = 'block text-sm  mb-1 text-gray-500';
                                $header_style = 'block text-sm text-black font-medium';

                                ?>
                                <input type="hidden" id="leadId" name="id">

                                <!-- Modal Contents -->
                                <div class="flex flex-col gap-3">
                                    <div title="Agent Details" class="">
                                        <div class="pb-2 capitalize">
                                            <span for="agentType" class="<?= htmlspecialchars($header_style) ?>">Agent
                                                Details
                                            </span>
                                            <hr class="border-gray-300 my-1">
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <label for="fullName"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Full
                                                    Name
                                                    <?= $required_mark; ?></label>
                                                <input type="text"
                                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    id="fullName" name="full_name" required>
                                            </div>
                                            <div>
                                                <label for="contactNumber"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Contact Number
                                                    <?= $required_mark; ?></label>
                                                <input type="text"
                                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    id="contactNumber" name="contact_number" required maxlength="13"
                                                    placeholder="0912-345-6789">

                                            </div>

                                            <div>
                                                <label for="email"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Email
                                                    <?= $required_mark; ?></label>
                                                <input type="email"
                                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    id="email" name="email" required
                                                    pattern="[a-zA-Z0-9._%+\-]+@gmail\.com"
                                                    title="Only Gmail addresses are allowed"
                                                    placeholder="e.g. innersparc@gmail.com">
                                            </div>

                                            <div>
                                                <label for="timestamp"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Date
                                                    of Entry
                                                    <span id="timestampLabel"><?= $automatic_mark; ?></span>
                                                </label>
                                                <input type="date"
                                                    class="border rounded px-3 py-2 w-full bg-gray-100 text-gray-600"
                                                    id="timestamp" name="timestamp" 
                                                    value=""
                                                    readonly
                                                    aria-readonly="true">
                                            </div>
                                        </div>
                                    </div>

                                    <div title="Management Details" class="">
                                        <div class="pb-2 capitalize">
                                            <span for="recruiterType"
                                                class="<?= htmlspecialchars($header_style) ?>">Management
                                                Details
                                            </span>
                                            <hr class="border-gray-300 my-1">
                                        </div>
                                        <!-- <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="recruiterName" class="<?= htmlspecialchars($label_name_style) ?>">Recruiter
                                            Name <?= $automatic_mark; ?></label>
                                        <input type="text" class="border rounded px-3 py-2 w-full bg-gray-100"
                                            id="recruiterName" name="recruiter_name"
                                            value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" readonly
                                            aria-readonly="true">
                                    </div>
                                    <div>
                                        <label for="teamName" class="<?= htmlspecialchars($label_name_style) ?>">Team
                                            <?= $automatic_mark; ?></label>
                                        <?php
                                        $teamName = "No Team";
                                        // Edit mode: show the team of the recruiter who added the lead
                                        if (!empty($lead['recruiter_team_id'])) {
                                            $conn = getDbConnection();
                                            $stmt = $conn->prepare("SELECT name FROM teams WHERE id = ? LIMIT 1");
                                            $stmt->bind_param("i", $lead['recruiter_team_id']);
                                            $stmt->execute();
                                            $stmt->bind_result($foundTeamName);
                                            if ($stmt->fetch() && $foundTeamName) {
                                                $teamName = $foundTeamName;
                                            }
                                            $stmt->close();
                                            $conn->close();
                                        }
                                        // Add mode: show the current user's team
                                        elseif (!empty($current_user['team_id'])) {
                                            $conn = getDbConnection();
                                            $stmt = $conn->prepare("SELECT name FROM teams WHERE id = ? LIMIT 1");
                                            $stmt->bind_param("i", $current_user['team_id']);
                                            $stmt->execute();
                                            $stmt->bind_result($foundTeamName);
                                            if ($stmt->fetch() && $foundTeamName) {
                                                $teamName = $foundTeamName;
                                            }
                                            $stmt->close();
                                            $conn->close();
                                        }
                                        ?>
                                        <input type="text" class="border rounded px-3 py-2 w-full bg-gray-100"
                                            id="teamName" name="team_name"
                                            value="<?php echo htmlspecialchars($teamName); ?>" readonly
                                            aria-readonly="true">
                                    </div>
                                </div> -->

                                        <!-- Recruitment Info Section -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <label for="managerName"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Manager
                                                    <span id="managerLabel"><?= $automatic_mark; ?></span></label>
                                                <?php 
                                                // Get all managers for dropdown
                                                $managers = [];
                                                try {
                                                    $conn = getDbConnection();
                                                    $stmt = $conn->prepare("SELECT id, name, team_id FROM users WHERE role = 'manager' ORDER BY name");
                                                    $stmt->execute();
                                                    $result = $stmt->get_result();
                                                    while ($row = $result->fetch_assoc()) {
                                                        $managers[] = $row;
                                                    }
                                                    $stmt->close();
                                                    $conn->close();
                                                } catch (Exception $e) {
                                                    error_log("Error fetching managers: " . $e->getMessage());
                                                }
                                                ?>
                                                <!-- Text input for add mode -->
                                                <input type="text" 
                                                    class="border rounded px-3 py-2 w-full bg-gray-100 text-gray-600"
                                                    id="managerName" 
                                                    name="manager_name"
                                                    value=""
                                                    readonly 
                                                    aria-readonly="true">
                                                <!-- Select dropdown for edit mode (hidden by default) -->
                                                <select id="managerSelect" name="manager_id_select" 
                                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 hidden">
                                                    <option value="">Select Manager</option>
                                                    <?php foreach ($managers as $manager): ?>
                                                    <option value="<?= htmlspecialchars($manager['id']) ?>">
                                                        <?= htmlspecialchars($manager['name']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" 
                                                    id="managerId" 
                                                    name="manager_id" 
                                                    value="">
                                            </div>
                                            <div class="relative group">
                                                <label for="teamName"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Team
                                                    <span id="teamLabel"><?= $automatic_mark; ?></span></label>
                                                <div class="relative">
                                                    <!-- Text input for add mode -->
                                                    <div id="teamNameDiv" class="flex items-center border rounded-lg px-4 py-2.5 bg-gray-50 text-gray-600">
                                                        <span class="flex-grow" id="teamNameText"></span>
                                                        <input type="hidden" id="teamId" name="team_id" value="">
                                                    </div>
                                                    <!-- Select dropdown for edit mode (hidden by default) -->
                                                    <select id="teamSelect" name="team_id_select" 
                                                        class="appearance-none border rounded-lg px-4 py-2.5 w-full bg-white text-gray-800 
                                                               focus:ring-2 focus:ring-blue-500 focus:border-transparent 
                                                               transition-all duration-200 ease-in-out shadow-sm hidden">
                                                        <option value="">Select Team</option>
                                                        <?php foreach ($teams as $team): ?>
                                                        <option value="<?= htmlspecialchars($team['id']) ?>">
                                                            <?= htmlspecialchars($team['name']) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div id="teamSelectArrow" class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700 hidden">
                                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                            <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div title="Recruitment Details" class="">
                                        <div class="pb-2 capitalize">
                                            <span for="agentProgress"
                                                class="<?= htmlspecialchars($header_style) ?>">Recruitment
                                                Details
                                            </span>
                                            <hr class="border-gray-300 my-1">
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <label for="status"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Status
                                                    <?= $required_mark; ?></label>
                                                <select
                                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    id="status" name="status" required>
                                                    <option value="Active">Active</option>
                                                    <option value="Inactive">Inactive</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="source"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Recruited
                                                    by
                                                    <span id="sourceLabel"><?= $automatic_mark; ?></span></label>
                                                <?php 
                                                // Get all users for dropdown
                                                $recruiters = [];
                                                try {
                                                    $conn = getDbConnection();
                                                    $stmt = $conn->prepare("SELECT id, name FROM users WHERE role IN ('admin', 'manager', 'user') ORDER BY name");
                                                    $stmt->execute();
                                                    $result = $stmt->get_result();
                                                    while ($row = $result->fetch_assoc()) {
                                                        $recruiters[] = $row;
                                                    }
                                                    $stmt->close();
                                                    $conn->close();
                                                } catch (Exception $e) {
                                                    error_log("Error fetching recruiters: " . $e->getMessage());
                                                }
                                                ?>
                                                <!-- Text input for add mode -->
                                                <input type="text" 
                                                    class="border rounded px-3 py-2 w-full bg-gray-100 text-gray-600"
                                                    id="source" 
                                                    name="source"
                                                    value=""
                                                    readonly 
                                                    aria-readonly="true">
                                                <!-- Select dropdown for edit mode (hidden by default) -->
                                                <select id="sourceSelect" name="source_id_select" 
                                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 hidden">
                                                    <option value="">Select Recruiter</option>
                                                    <?php foreach ($recruiters as $recruiter): ?>
                                                    <option value="<?= htmlspecialchars($recruiter['id']) ?>">
                                                        <?= htmlspecialchars($recruiter['name']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" 
                                                    id="sourceId" 
                                                    name="source_id" 
                                                    value="">
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="recruitment_progress"
                                                class="<?= htmlspecialchars($label_name_style) ?>">Recruitment
                                                Progress
                                                <?= $required_mark; ?></label>

                                            <?php

                                            $checklist_style = 'recruitment-progress-btn w-full text-center py-3 px-4 border rounded-lg transition-all duration-300 cursor-pointer bg-white text-gray-800 hover:shadow-lg hover:scale-105 transform active:scale-95 select-none font-medium';

                                            $label_style = 'text-gray-600 text-sm';

                                            $label_subsection_style = 'space-y-2 border-l border-gray-300 pl-2 w-[97%]';
                                            ?>
                                            <div id="progressChecklist" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div class="space-y-2">
                                                    <div class="flex flex-row justify-center items-center gap-2">
                                                        <label for="pre-recruitment"
                                                            class="<?php echo htmlspecialchars($label_style) ?> whitespace-nowrap">Pre-recruitment
                                                        </label>
                                                        <div
                                                            class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                                            <div id="preRecruitmentProgressBar"
                                                                class="h-3 bg-blue-500 transition-all duration-300"
                                                                style="width: 0%;">
                                                            </div>
                                                        </div>
                                                        <div class="text-xs text-right text-gray-600"
                                                            id="preRecruitmentProgressText">0%
                                                        </div>
                                                    </div>
                                                    <!-- Pre-recruitment checkboxes -->
                                                    <label class="block">
                                                        <input type="checkbox" id="pre-assessment" name="pre-assessment"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-800 peer-checked:hover:bg-blue-700 peer-checked:hover:border-blue-700 relative group">
                                                            <span class="peer-checked:hidden">Pre-Assessment</span>
                                                            <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                <i class="fas fa-check text-white"></i>
                                                                Pre-Assessment
                                                            </span>
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="accreditation" name="accreditation"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-800 peer-checked:hover:bg-blue-700 peer-checked:hover:border-blue-700 relative group">
                                                            <span class="peer-checked:hidden">Accreditation</span>
                                                            <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                <i class="fas fa-check text-white"></i>
                                                                Accreditation
                                                            </span>
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="assessment" name="assessment"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-800 peer-checked:hover:bg-blue-700 peer-checked:hover:border-blue-700 relative group">
                                                            <span class="peer-checked:hidden">Assessment</span>
                                                            <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                <i class="fas fa-check text-white"></i>
                                                                Assessment
                                                            </span>
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="sales_training" name="sales_training"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-800 peer-checked:hover:bg-blue-700 peer-checked:hover:border-blue-700 relative group">
                                                            <span class="peer-checked:hidden">Sales 101</span>
                                                            <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                <i class="fas fa-check text-white"></i>
                                                                Sales 101
                                                            </span>
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="site_tour" name="site_tour" value="1"
                                                            class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-800 peer-checked:hover:bg-blue-700 peer-checked:hover:border-blue-700 relative group">
                                                            <span class="peer-checked:hidden">Site Tour</span>
                                                            <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                <i class="fas fa-check text-white"></i>
                                                                Site Tour
                                                            </span>
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="focus_projects" name="focus_projects"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-800 peer-checked:hover:bg-blue-700 peer-checked:hover:border-blue-700 relative group">
                                                            <span class="peer-checked:hidden">Focus Projects</span>
                                                            <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                <i class="fas fa-check text-white"></i>
                                                                Focus Projects
                                                            </span>
                                                        </div>
                                                    </label>
                                                </div>

                                                <div class="space-y-2">
                                                    <div class="flex flex-row justify-center items-center gap-2">
                                                        <label for="post-recruitment"
                                                            class="<?php echo htmlspecialchars($label_style) ?> whitespace-nowrap">Post-recruitment</label>
                                                        <div
                                                            class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                                            <div id="postRecruitmentProgressBar"
                                                                class="h-3 bg-orange-500 transition-all duration-300"
                                                                style="width: 0%;">
                                                            </div>
                                                        </div>
                                                        <div class="text-xs text-right text-gray-600"
                                                            id="postRecruitmentProgressText">
                                                            0%</div>
                                                    </div>

                                                    <!-- Post-recruitment checkboxes -->
                                                    <label class="block">
                                                        <input type="checkbox" id="habit_forming" name="habit_forming"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> orange peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 hover:bg-orange-50 hover:border-orange-400 hover:text-orange-800 peer-checked:hover:bg-orange-600 peer-checked:hover:border-orange-600 relative group">
                                                            <span class="peer-checked:hidden">Habit Forming</span>
                                                            <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                <i class="fas fa-check text-white"></i>
                                                                Habit Forming
                                                            </span>
                                                        </div>
                                                    </label>
                                                    <div class="space-y-2 group">
                                                        <div class="flex items-center justify-between">
                                                            <label for="training_materials"
                                                                class="<?php echo htmlspecialchars($label_style) ?> text-xs font-medium text-gray-600 group-hover:text-gray-800 transition-colors">
                                                                Training Materials
                                                            </label>
                                                            <span class="text-xs text-gray-400 group-hover:text-gray-600 transition-colors">
                                                                Track progress
                                                            </span>
                                                        </div>
                                                        <div class="w-full">
                                                            <div class="space-y-2 pl-4 border-l-2 border-gray-200 group-hover:border-blue-300 transition-colors w-full">
                                                                <label class="block">
                                                                    <input type="checkbox" id="digital_training"
                                                                        name="digital_training" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> orange peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 hover:bg-orange-50 hover:border-orange-400 hover:text-orange-800 peer-checked:hover:bg-orange-600 peer-checked:hover:border-orange-600 relative group">
                                                                        <span class="peer-checked:hidden">Digital Marketing Training</span>
                                                                        <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                            <i class="fas fa-check text-white"></i>
                                                                            Digital Marketing Training
                                                                        </span>
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="sales_training_materials"
                                                                        name="sales_training_materials" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> orange peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 hover:bg-orange-50 hover:border-orange-400 hover:text-orange-800 peer-checked:hover:bg-orange-600 peer-checked:hover:border-orange-600 relative group">
                                                                        <span class="peer-checked:hidden">Training Materials</span>
                                                                        <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                            <i class="fas fa-check text-white"></i>
                                                                            Training Materials
                                                                        </span>
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="objection_handling"
                                                                        name="objection_handling" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> orange peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 hover:bg-orange-50 hover:border-orange-400 hover:text-orange-800 peer-checked:hover:bg-orange-600 peer-checked:hover:border-orange-600 relative group">
                                                                        <span class="peer-checked:hidden">Objection Handling</span>
                                                                        <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                            <i class="fas fa-check text-white"></i>
                                                                            Objection Handling
                                                                        </span>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="space-y-2 group">
                                                        <div class="flex items-center justify-between">
                                                            <label for="tools_familiarization"
                                                                class="<?php echo htmlspecialchars($label_style) ?> text-xs font-medium text-gray-600 group-hover:text-gray-800 transition-colors">
                                                                Tools Familiarization
                                                            </label>
                                                            <span class="text-xs text-gray-400 group-hover:text-gray-600 transition-colors">
                                                                Track progress
                                                            </span>
                                                        </div>
                                                        <div class="w-full">
                                                            <div class="space-y-2 pl-4 border-l-2 border-gray-200 group-hover:border-blue-300 transition-colors w-full">
                                                                <label class="block">
                                                                    <input type="checkbox" id="VAST" name="VAST"
                                                                        value="1" class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> orange peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 hover:bg-orange-50 hover:border-orange-400 hover:text-orange-800 peer-checked:hover:bg-orange-600 peer-checked:hover:border-orange-600 relative group">
                                                                        <span class="peer-checked:hidden">VAST Training</span>
                                                                        <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                            <i class="fas fa-check text-white"></i>
                                                                            VAST Training
                                                                        </span>
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="sales_monitoring"
                                                                        name="sales_monitoring" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> orange peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 hover:bg-orange-50 hover:border-orange-400 hover:text-orange-800 peer-checked:hover:bg-orange-600 peer-checked:hover:border-orange-600 relative group">
                                                                        <span class="peer-checked:hidden">Google Site (Sales Monitoring)</span>
                                                                        <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                            <i class="fas fa-check text-white"></i>
                                                                            Google Site (Sales Monitoring)
                                                                        </span>
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="LMS" name="LMS" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> orange peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 hover:bg-orange-50 hover:border-orange-400 hover:text-orange-800 peer-checked:hover:bg-orange-600 peer-checked:hover:border-orange-600 relative group">
                                                                        <span class="peer-checked:hidden">Lead Management System</span>
                                                                        <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                            <i class="fas fa-check text-white"></i>
                                                                            Lead Management System
                                                                        </span>
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="comm_structure"
                                                                        name="comm_structure" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> orange peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 hover:bg-orange-50 hover:border-orange-400 hover:text-orange-800 peer-checked:hover:bg-orange-600 peer-checked:hover:border-orange-600 relative group">
                                                                        <span class="peer-checked:hidden">Comm Structure</span>
                                                                        <span class="hidden peer-checked:inline-flex items-center gap-2">
                                                                            <i class="fas fa-check text-white"></i>
                                                                            Comm Structure
                                                                        </span>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Overall progress bar -->
                                            <div class="w-full bg-gray-200 rounded-full h-6 overflow-hidden mt-6">
                                                <div id="progressBar"
                                                    class="h-6 bg-green-500 transition-all duration-300"
                                                    style="width: 0%;"></div>
                                            </div>
                                            <div class="text-sm text-right text-gray-600" id="progressText">0%</div>
                                            <div class="mb-4">
                                                <label for="remarks" class="block text-sm font-medium mb-1">Remarks
                                                    <?= $optional_mark; ?>
                                                </label>
                                                <textarea
                                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    id="remarks" name="remarks" rows="3"></textarea>
                                            </div>
                                        </div>
                                    </div>
                            </form>
                        </div>
                        <div class="flex justify-end gap-2 border-t px-6 pb-0 pr-0 py-4">
                            <button type="button" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                                onclick="saveRecruitmentLead()">Save</button>
                            <button type="button" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300"
                                onclick="hideRecruitmentModal()">Cancel</button>
                        </div>
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
    document.getElementById('contactNumber').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove all non-digits
        if (value.length > 11) value = value.slice(0, 11);

        // Apply format: 0912-345-6789
        let formatted = value;
        if (value.length > 4 && value.length <= 7) {
            formatted = value.slice(0, 4) + '-' + value.slice(4);
        } else if (value.length > 7) {
            formatted = value.slice(0, 4) + '-' + value.slice(4, 7) + '-' + value.slice(7);
        }

        e.target.value = formatted;
    });

    // Recruitment Dashboard JavaScript - Clean and Working Version
    const currentSort = {
        column: "created_at",
        order: "DESC"
    }
    let currentFilters = {}
    let allLeads = []

    // Inject current user info from PHP
    const CURRENT_USER_ID = <?php echo json_encode($current_user['id']); ?>;
    const CURRENT_USER_ROLE = <?php echo json_encode($current_user['role']); ?>;
    const CURRENT_USER_TEAM_ID = <?php echo json_encode($current_user['team_id'] ?? null); ?>;

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
        populateYearFilter(); // Populate year dropdown
        loadRecruitmentData()

        // Add keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts)

        populateTeamAndRecruiterDropdowns();
    })
    
    // Populate year filter dropdown
    function populateYearFilter() {
        const yearSelect = document.getElementById('filterYear');
        const currentYear = new Date().getFullYear();
        
        // Add years from current year back to 5 years ago
        for (let year = currentYear; year >= currentYear - 5; year--) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            yearSelect.appendChild(option);
        }
    }

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

    // Display recruitment data in cards
    function displayRecruitmentData(leads) {
        // Only show all leads for admin, else filter by recruiter_id
        let visibleLeads = leads;
        if (CURRENT_USER_ROLE === 'manager') {
            visibleLeads = leads.filter(lead => lead.recruiter_id == CURRENT_USER_ID);
        }

        const cardsContainer = document.getElementById("recruitmentTableBody");
        cardsContainer.innerHTML = "";

        if (!visibleLeads || visibleLeads.length === 0) {
            cardsContainer.innerHTML = `
                <div class="col-span-full flex flex-col items-center justify-center py-12">
                    <i class="fas fa-search text-4xl mb-4 text-gray-400"></i>
                    <p class="text-lg font-medium text-gray-600 mb-2">No recruitment leads found</p>
                    <small class="text-gray-500">Try adjusting your filters or search terms</small>
                </div>
            `;
            return;
        }

        visibleLeads.forEach((lead, index) => {
            const dateObj = new Date(lead.created_at);
            const timestamp = dateObj.toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }).replace(',', '').replace(/(\d{4}) (\d{1,2}):/, '$1 at $2:');

            // Masking logic: only recruiter or admin can see full details
            const isOwnLead = (CURRENT_USER_ID == lead.recruiter_id);
            const isAdmin = (CURRENT_USER_ROLE === 'admin');
            const canSeeDetails = isOwnLead || isAdmin;
            const canEditDelete = isOwnLead || isAdmin;

            function maskWord(word) {
                if (word.length <= 2) return word[0] + '*'.repeat(word.length - 1);
                if (word.length <= 4) return word[0] + '*'.repeat(word.length - 2) + word[word.length - 1];
                return word.slice(0, 2) + '*'.repeat(word.length - 4) + word.slice(-2);
            }

            function maskText(text) {
                if (!text) return '';
                return text.split(' ').map(maskWord).join(' ');
            }

            // Create card element
            const card = document.createElement("div");
            card.className = "modern-agent-card transform transition-all duration-500 hover:scale-105";
            card.style.animationDelay = `${index * 100}ms`;
            card.style.animation = `slideInUp 0.6s ease-out ${index * 100}ms both`;
            
            card.innerHTML = `
                <div class="group relative bg-gradient-to-br from-white via-slate-50 to-blue-50/30 border-2 border-gray-200/60 rounded-3xl p-6 shadow-lg hover:shadow-2xl hover:border-blue-300/80 transition-all duration-500 transform hover:-translate-y-2 backdrop-blur-sm overflow-hidden">
                    <!-- Decorative Background Elements -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-100/30 to-purple-100/30 rounded-full blur-2xl transform translate-x-16 -translate-y-16 group-hover:scale-150 transition-transform duration-700"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-gradient-to-tr from-emerald-100/30 to-blue-100/30 rounded-full blur-xl transform -translate-x-12 translate-y-12 group-hover:scale-125 transition-transform duration-500"></div>
                    
                    <!-- Enhanced Header Section -->
                    <div class="relative flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="relative flex-shrink-0">
                                <!-- Enhanced Avatar with Better Gradients -->
                                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 via-purple-600 to-pink-500 rounded-2xl flex items-center justify-center text-white font-bold text-lg shadow-xl ring-4 ring-white/50 group-hover:ring-8 group-hover:ring-blue-200/50 transition-all duration-300">
                                    ${(lead.full_name || 'N').charAt(0).toUpperCase()}
                                </div>
                                <!-- Enhanced Status Indicator -->
                                <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-white rounded-full flex items-center justify-center shadow-lg ring-2 ring-white">
                                    ${lead.status === 'Active' ? 
                                        '<div class="w-3 h-3 bg-gradient-to-r from-emerald-400 to-emerald-500 rounded-full animate-pulse shadow-sm"></div>' :
                                        lead.status === 'Inactive' ? 
                                        '<div class="w-3 h-3 bg-gradient-to-r from-red-400 to-red-500 rounded-full shadow-sm"></div>' :
                                        '<div class="w-3 h-3 bg-gradient-to-r from-gray-400 to-gray-500 rounded-full shadow-sm"></div>'
                                    }
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-gray-900 text-lg truncate mb-1 group-hover:text-blue-700 transition-colors duration-300" title="${lead.full_name}">
                                    ${canSeeDetails ? (lead.full_name || 'N/A') : maskText(lead.full_name || '')}
                                </h3>
                                <p class="text-sm text-gray-500 font-medium flex items-center gap-1">
                                    <i class="fas fa-calendar text-xs text-blue-400"></i>
                                    ${new Date(lead.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}
                                </p>
                            </div>
                        </div>
                        <!-- Enhanced Status Badge -->
                        <div class="flex-shrink-0">
                            ${lead.status === 'Active' ? 
                                '<span class="inline-flex items-center px-3 py-2 rounded-xl text-sm font-bold bg-gradient-to-r from-emerald-100 to-emerald-50 text-emerald-700 border-2 border-emerald-200/50 shadow-sm"><span class="w-2 h-2 bg-emerald-400 rounded-full mr-2 animate-pulse"></span>Active</span>' :
                                lead.status === 'Inactive' ? 
                                '<span class="inline-flex items-center px-3 py-2 rounded-xl text-sm font-bold bg-gradient-to-r from-red-100 to-red-50 text-red-700 border-2 border-red-200/50 shadow-sm"><span class="w-2 h-2 bg-red-400 rounded-full mr-2"></span>Inactive</span>' :
                                `<span class="inline-flex items-center px-3 py-2 rounded-xl text-sm font-bold bg-gradient-to-r from-gray-100 to-gray-50 text-gray-700 border-2 border-gray-200/50 shadow-sm">${lead.status || 'N/A'}</span>`
                            }
                        </div>
                    </div>
                    
                    <!-- Enhanced Contact Grid -->
                    <div class="relative bg-gradient-to-br from-white/80 to-gray-50/80 backdrop-blur-sm rounded-2xl p-4 mb-4 border border-gray-200/50 shadow-inner">
                        <div class="grid grid-cols-1 gap-3">
                            <!-- Phone & Email Row -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex items-center gap-2 min-w-0 bg-white/60 rounded-lg p-2 hover:bg-blue-50/80 transition-colors duration-200">
                                    <div class="w-6 h-6 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-phone text-white text-xs"></i>
                                    </div>
                                    <span class="text-gray-800 text-sm font-medium truncate">
                                        ${canSeeDetails ? (lead.contact_number || 'N/A') : maskText(lead.contact_number || '')}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 min-w-0 bg-white/60 rounded-lg p-2 hover:bg-purple-50/80 transition-colors duration-200">
                                    <div class="w-6 h-6 bg-gradient-to-br from-purple-400 to-purple-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-envelope text-white text-xs"></i>
                                    </div>
                                    <span class="text-gray-800 text-sm font-medium truncate" title="${canSeeDetails ? (lead.email || 'N/A') : maskText(lead.email || '')}">
                                        ${canSeeDetails ? (lead.email ? (lead.email.length > 15 ? lead.email.substring(0, 15) + '...' : lead.email) : 'N/A') : maskText(lead.email || '')}
                                    </span>
                                </div>
                            </div>
                            <!-- Recruiter & Team Row -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex items-center gap-2 min-w-0 bg-white/60 rounded-lg p-2 hover:bg-emerald-50/80 transition-colors duration-200">
                                    <div class="w-6 h-6 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-user-tie text-white text-xs"></i>
                                    </div>
                                    <span class="text-gray-800 text-sm font-medium truncate" title="${lead.recruiter_name || 'N/A'}">
                                        ${lead.recruiter_name || 'N/A'}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 min-w-0 bg-white/60 rounded-lg p-2 hover:bg-orange-50/80 transition-colors duration-200">
                                    <div class="w-6 h-6 bg-gradient-to-br from-orange-400 to-orange-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-users text-white text-xs"></i>
                                    </div>
                                    <span class="text-gray-800 text-sm font-medium truncate" title="${lead.recruiter_team || 'No Team'}">
                                        ${lead.recruiter_team || 'No Team'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Progress Section -->
                    <div class="relative bg-gradient-to-br from-white/60 to-gray-50/60 backdrop-blur-sm rounded-2xl p-4 mb-4 border border-gray-200/50 space-y-4">
                        <div class="flex items-center justify-center mb-3">
                            <h4 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                                <i class="fas fa-chart-line text-blue-500"></i>
                                Progress Tracking
                            </h4>
                        </div>
                        
                        <!-- Pre-Recruitment Progress -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full shadow-sm"></div>
                                    <span class="font-semibold text-blue-700 text-sm">Pre-Recruitment</span>
                                </div>
                                <span class="font-bold text-blue-700 text-sm bg-gradient-to-r from-blue-50 to-blue-100 px-3 py-1 rounded-lg border border-blue-200/50">
                                    ${getPreRecruitmentPercent(lead)}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 shadow-inner">
                                <div class="h-2 bg-gradient-to-r from-blue-400 via-blue-500 to-blue-600 rounded-full transition-all duration-700 shadow-sm relative overflow-hidden" 
                                     style="width: ${getPreRecruitmentPercent(lead)}%">
                                    <div class="absolute inset-0 bg-gradient-to-r from-white/30 to-transparent rounded-full"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Post-Recruitment Progress -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-gradient-to-r from-orange-400 to-orange-600 rounded-full shadow-sm"></div>
                                    <span class="font-semibold text-orange-700 text-sm">Post-Recruitment</span>
                                </div>
                                <span class="font-bold text-orange-700 text-sm bg-gradient-to-r from-orange-50 to-orange-100 px-3 py-1 rounded-lg border border-orange-200/50">
                                    ${getPostRecruitmentPercent(lead)}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 shadow-inner">
                                <div class="h-2 bg-gradient-to-r from-orange-400 via-orange-500 to-orange-600 rounded-full transition-all duration-700 shadow-sm relative overflow-hidden" 
                                     style="width: ${getPostRecruitmentPercent(lead)}%">
                                    <div class="absolute inset-0 bg-gradient-to-r from-white/30 to-transparent rounded-full"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Overall Progress -->
                        <div class="space-y-2 pt-2 border-t border-gray-200/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-full shadow-sm"></div>
                                    <span class="font-bold text-emerald-700 text-sm">Overall Progress</span>
                                </div>
                                <span class="font-bold text-emerald-700 text-lg bg-gradient-to-r from-emerald-50 to-emerald-100 px-4 py-2 rounded-xl border-2 border-emerald-200/50 shadow-sm">
                                    ${getProgressPercent(lead)}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 shadow-inner">
                                <div class="h-3 bg-gradient-to-r from-emerald-400 via-emerald-500 to-emerald-600 rounded-full transition-all duration-700 shadow-md relative overflow-hidden" 
                                     style="width: ${getProgressPercent(lead)}%">
                                    <div class="absolute inset-0 bg-gradient-to-r from-white/40 to-transparent rounded-full"></div>
                                    <div class="absolute inset-0 bg-gradient-to-t from-white/20 to-transparent rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Footer -->
                    <div class="relative pt-4 border-t border-gray-200/50">
                        <div class="flex items-center justify-between">
                            <!-- Source Information -->
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-xl flex items-center justify-center border-2 border-white shadow-sm">
                                    <i class="fas fa-user-plus text-indigo-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Recruited by</p>
                                    <p class="text-sm font-bold text-gray-700 truncate max-w-[100px]" title="${lead.source || 'N/A'}">
                                        ${lead.source || 'N/A'}
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2">
                                ${canEditDelete ? `
                                    ${(lead.LMS == 1 || lead.LMS === '1' || getPostRecruitmentPercent(lead) >= 75 || getProgressPercent(lead) >= 86) ? `
                                        <button class="group/btn relative w-10 h-10 bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 text-white rounded-xl transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/25 hover:scale-110 active:scale-95 flex items-center justify-center overflow-hidden" 
                                                onclick="showOnboardModal(${lead.id})" title="Onboarding" id="onboardBtn-${lead.id}">
                                            <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></div>
                                            <i class="fas fa-rocket text-sm relative z-10 group-hover/btn:animate-bounce"></i>
                                        </button>
                                    ` : ''}
                                    <button class="group/btn relative w-10 h-10 bg-gradient-to-r from-amber-500 via-orange-500 to-orange-600 text-white rounded-xl transition-all duration-300 hover:shadow-lg hover:shadow-orange-500/25 hover:scale-110 active:scale-95 flex items-center justify-center overflow-hidden" 
                                            onclick="editLead(${lead.id})" title="Edit Agent">
                                        <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></div>
                                        <i class="fas fa-edit text-sm relative z-10 group-hover/btn:rotate-12 transition-transform duration-200"></i>
                                    </button>
                                    <button class="group/btn relative w-10 h-10 bg-gradient-to-r from-red-500 via-red-600 to-rose-600 text-white rounded-xl transition-all duration-300 hover:shadow-lg hover:shadow-red-500/25 hover:scale-110 active:scale-95 flex items-center justify-center overflow-hidden" 
                                            onclick="deleteLead(${lead.id})" title="Delete Agent">
                                        <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></div>
                                        <i class="fas fa-trash text-sm relative z-10 group-hover/btn:animate-pulse"></i>
                                    </button>
                                ` : `
                                    <div class="flex items-center gap-1 px-3 py-2 bg-gray-100 rounded-lg">
                                        <i class="fas fa-lock text-gray-400 text-xs"></i>
                                        <span class="text-xs text-gray-500 font-medium">View Only</span>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enhanced hover effects -->
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50/0 via-purple-50/0 to-indigo-50/0 group-hover:from-blue-50/40 group-hover:via-purple-50/20 group-hover:to-indigo-50/30 rounded-3xl transition-all duration-700 pointer-events-none"></div>
                    
                    <!-- Subtle border glow on hover -->
                    <div class="absolute inset-0 rounded-3xl ring-0 ring-blue-200/0 group-hover:ring-2 group-hover:ring-blue-200/50 transition-all duration-500 pointer-events-none"></div>
                </div>
            `;
            
            cardsContainer.appendChild(card);
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
        const source = document.getElementById("filterSource") ? document.getElementById("filterSource").value.trim() : ''
        const search = document.getElementById("searchInput").value.trim()
        const team = document.getElementById("filterTeam") ? document.getElementById("filterTeam").value.trim() : ''
        const recruitmentStatus = document.getElementById("filterRecruitmentStatus").value.trim()
        const year = document.getElementById("filterYear").value.trim()
        const month = document.getElementById("filterMonth").value.trim()

        // Debug log
        console.log('Current filter values:', {
            status,
            source,
            search,
            team,
            recruitmentStatus,
            year,
            month
        });

        if (status) currentFilters.status = status
        if (source) currentFilters.source = source
        if (search) currentFilters.search = search
        if (team) currentFilters.team = team
        if (year) currentFilters.year = year
        if (month) currentFilters.month = month
        if (recruitmentStatus !== '') {
            currentFilters.onboardStatus = recruitmentStatus
            console.log('Setting onboardStatus filter to:', recruitmentStatus)
        }

        console.log('Applied filters:', currentFilters);

        // Load data with filters
        loadRecruitmentData()
        
        // Also update statistics with filters
        loadRecruitmentStats()

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
        if (document.getElementById("filterSource")) document.getElementById("filterSource").value = ""
        if (document.getElementById("filterTeam")) document.getElementById("filterTeam").value = ""
        document.getElementById("filterRecruitmentStatus").value = ""
        document.getElementById("searchInput").value = ""
        document.getElementById("filterYear").value = ""
        document.getElementById("filterMonth").value = ""

        document.getElementById("activeFilters").style.display = "none"
        document.getElementById("filterResults").style.display = "none"

        loadRecruitmentData()
        loadRecruitmentStats()  // Also refresh statistics when filters are cleared
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
            document.getElementById("filterTeam").value = ""
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
        const filterIcons = {
            search: {
                icon: 'fas fa-search',
                label: 'Search'
            },
            status: {
                icon: 'fas fa-signal',
                label: 'Status'
            },
            source: {
                icon: 'fas fa-share-alt',
                label: 'Source'
            },
            timestamp: {
                icon: 'fas fa-clock',
                label: 'Date'
            },
            team: {
                icon: 'fa-solid fa-people-group',
                label: 'Team'
            },
            recruiter: {
                icon: 'fas fa-user-tie',
                label: 'Recruiter'
            },
            onboardStatus: {
                icon: 'fas fa-rocket',
                label: 'Onboard Status'
            },
            year: {
                icon: 'fas fa-calendar-alt',
                label: 'Year'
            },
            month: {
                icon: 'fas fa-calendar-week',
                label: 'Month'
            }
            // Add more keys as needed
        }


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

            // const displayKey = key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
            const iconData = filterIcons[key] || {
                icon: 'fas fa-filter',
                label: key
            }

            // Convert team ID to team name for display
            let displayValue = value;
            if (key === 'team') {
                const teamSelect = document.getElementById('filterTeam');
                const selectedOption = teamSelect.querySelector(`option[value="${value}"]`);
                if (selectedOption) {
                    displayValue = selectedOption.textContent;
                }
            }

            // Show human-friendly status for onboarding
            if (key === 'onboardStatus') {
                const v = String(value);
                if (v === '1') displayValue = 'Onboarded';
                else if (v === '0') displayValue = 'Not Onboarded';
            }

            // Convert month number to month name for display
            if (key === 'month') {
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                                  'July', 'August', 'September', 'October', 'November', 'December'];
                const monthIndex = parseInt(value) - 1;
                if (monthIndex >= 0 && monthIndex < 12) {
                    displayValue = monthNames[monthIndex];
                }
            }

            // tag.innerHTML =
            //     `<span class="text-white bg-gray-400 rounded-full px-3 py-1">${displayKey}: ${value} <i class="fas fa-times ms-1" onclick="removeFilter('${key}')"></i></span>`
            tag.innerHTML = `
                    <span class="w-fit text-white text-xs bg-blue-500 rounded-full px-3 py-1 pr-1 flex items-center gap-2">
                        <i class="${iconData.icon} w-full"></i>
                        ${displayValue}
                        <button
                            class="bg-white text-blue-500 hover:bg-blue-100 hover:text-blue-700 rounded-full w-5 h-5 flex items-center justify-center transition duration-150 ease-in-out"
                            title="Remove this filter"
                            onclick="removeFilter('${key}')"
                        >
                            <i class="fas fa-times text-xs w-full"></i>
                        </button>
                    </span>
                `

            activeFilterTags.appendChild(tag)
        })
    }

    // Remove individual filter
    function removeFilter(key) {
        delete currentFilters[key];

        const fieldMap = {
            'status': 'filterStatus',
            'source': 'filterSource',
            'team': 'filterTeam',
            'onboardStatus': 'filterRecruitmentStatus',
            'search': 'searchInput',
            'year': 'filterYear',
            'month': 'filterMonth'
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

    function populateTeamAndRecruiterDropdowns() {
        if (CURRENT_USER_ROLE === 'admin') {
            const teamSelect = document.getElementById('teamName');
            const recruiterInput = document.getElementById('recruiterName');
            const recruiterIdInput = document.getElementById('recruiterId');
            const teamMembersSelect = document.getElementById('source');

            teamSelect.innerHTML = '<option value="">Select Team</option>';
            teamMembersSelect.innerHTML = '<option value="">Select the Recruiter</option>';

            // Prevent duplicate listeners
            const newTeamSelect = teamSelect.cloneNode(true);
            teamSelect.parentNode.replaceChild(newTeamSelect, teamSelect);

            fetch('get_teams_and_managers.php')
                .then(res => res.json())
                .then(data => {
                    data.teams.forEach(team => {
                        newTeamSelect.innerHTML += `<option value="${team.id}">${team.name}</option>`;
                    });

                    newTeamSelect.addEventListener('change', function() {
                        const selectedTeamId = this.value;
                        recruiterInput.value = '';
                        recruiterIdInput.value = '';
                        teamMembersSelect.innerHTML = '<option value="">Select the Recruiter</option>';

                        if (selectedTeamId) {
                            fetch(`get_team_recruiters.php?team_id=${selectedTeamId}`)
                                .then(res => res.json())
                                .then(recruitersData => {
                                    if (Array.isArray(recruitersData) && recruitersData.length > 0) {
                                        recruitersData.forEach(recruiter => {
                                            teamMembersSelect.innerHTML +=
                                                `<option value="${recruiter.name}">${recruiter.name}</option>`;
                                        });
                                    }
                                });

                            const selectedTeam = data.teams.find(t => t.id == selectedTeamId);
                            if (selectedTeam?.managers?.length > 0) {
                                const manager = selectedTeam.managers[0];
                                recruiterInput.value = manager.name;
                                recruiterIdInput.value = manager.id;
                            }
                        }
                    });
                });

        } else if (CURRENT_USER_ROLE === 'manager' && CURRENT_USER_TEAM_ID) {
            const sourceSelect = document.getElementById('source');
            sourceSelect.innerHTML = '<option value="">Select the Recruiter</option>';

            fetch(`get_team_recruiters.php?team_id=${CURRENT_USER_TEAM_ID}`)
                .then(res => res.json())
                .then(recruitersData => {
                    if (Array.isArray(recruitersData) && recruitersData.length > 0) {
                        recruitersData.forEach(recruiter => {
                            sourceSelect.innerHTML +=
                                `<option value="${recruiter.name}">${recruiter.name}</option>`;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching team recruiters for manager:', error);
                });
        }
    }

    // Modal and CRUD functions
    function showAddModal() {
        console.log('showAddModal function called');
        
        // Check if elements exist before accessing them
        const modalTitle = document.getElementById('modalTitle');
        const recruitmentForm = document.getElementById('recruitmentForm');
        const leadId = document.getElementById('leadId');
        
        if (modalTitle) modalTitle.textContent = 'Add New Recruited Agent';
        if (recruitmentForm) recruitmentForm.reset();
        if (leadId) leadId.value = '';
        
        // Auto-populate fields for NEW entries only
        // Date of entry
        const timestampField = document.getElementById('timestamp');
        if (timestampField) {
            timestampField.value = new Date().toISOString().split('T')[0]; // Today's date
            timestampField.readOnly = true;
        }
        
        // Manager field - auto-populate with current user's manager
        const managerField = document.getElementById('managerName');
        const managerIdField = document.getElementById('managerId');
        
        // Team field - auto-populate with current user's team
        const teamNameText = document.getElementById('teamNameText');
        const teamIdField = document.getElementById('teamId');
        
        // Source/recruited by field - auto-populate with current user
        const sourceField = document.getElementById('source');
        const sourceIdField = document.getElementById('sourceId');
        
        // Set current user as recruiter
        if (sourceField) sourceField.value = "<?php echo htmlspecialchars($user['name'] ?? ''); ?>";
        if (sourceIdField) sourceIdField.value = "<?php echo htmlspecialchars($user['id'] ?? ''); ?>";
        
        // Set current user's team
        if (teamNameText) teamNameText.textContent = "<?php echo htmlspecialchars($current_user_team); ?>";
        if (teamIdField) teamIdField.value = "<?php echo htmlspecialchars($current_user['team_id'] ?? ''); ?>";
        
        // Auto-populate manager based on current user's team
        <?php
        $auto_manager_name = '';
        $auto_manager_id = '';
        $teamId = $current_user['team_id'] ?? null;
        
        if ($teamId) {
            $conn = getDbConnection();
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE team_id = ? AND role = 'manager' LIMIT 1");
            $stmt->bind_param("i", $teamId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $manager = $result->fetch_assoc();
                $auto_manager_name = $manager['name'];
                $auto_manager_id = $manager['id'];
            } else {
                if ($current_user_team === 'Blazing SPARCS') {
                    $auto_manager_name = 'Erwin Baguioan';
                    $stmt = $conn->prepare("SELECT id FROM users WHERE name LIKE '%Erwin Baguioan%' LIMIT 1");
                    $stmt->execute();
                    $stmt->bind_result($erwinId);
                    $stmt->fetch();
                    $auto_manager_id = $erwinId ?? null;
                }
            }
            $stmt->close();
            $conn->close();
        }
        ?>
        
        if (managerField) managerField.value = "<?php echo htmlspecialchars($auto_manager_name); ?>";
        if (managerIdField) managerIdField.value = "<?php echo htmlspecialchars($auto_manager_id); ?>";
        
        // Make sure fields are in "add mode" (readonly/display mode for auto-populated fields)
        setFieldsToAddMode();
        
        // Update labels to show "(Automatic)"
        const timestampLabel = document.getElementById('timestampLabel');
        const managerLabel = document.getElementById('managerLabel');
        const teamLabel = document.getElementById('teamLabel');
        const sourceLabel = document.getElementById('sourceLabel');
        
        if (timestampLabel) timestampLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Automatic)</span>';
        if (managerLabel) managerLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Automatic)</span>';
        if (teamLabel) teamLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Automatic)</span>';
        if (sourceLabel) sourceLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Automatic)</span>';

        // Reset all checklist checkboxes and progress bar
        const checklistKeys = allKeys;
        checklistKeys.forEach(id => {
            const cb = document.getElementById(id);
            if (cb) cb.checked = false;
        });
        updateProgress(); // Ensure progress bars reset

        if (CURRENT_USER_ROLE === 'admin') {
            const teamSelect = document.getElementById('teamName');
            const recruiterInput = document.getElementById('recruiterName');
            const recruiterIdInput = document.getElementById('recruiterId');
            const teamMembersSelect = document.getElementById('source');

            // Check if elements exist before accessing them
            if (!teamSelect || !recruiterInput || !recruiterIdInput || !teamMembersSelect) {
                console.warn('Some form elements are missing for admin role');
                document.getElementById('recruitmentModal').classList.remove('hidden');
                return;
            }

            // Clear existing options
            teamSelect.innerHTML = '<option value="">Select Team</option>';
            teamMembersSelect.innerHTML = '<option value="">Select a Recruiter</option>';

            // Remove any existing event listeners to prevent duplicates
            const newTeamSelect = teamSelect.cloneNode(true);
            teamSelect.parentNode.replaceChild(newTeamSelect, teamSelect);

            fetch('get_teams_and_managers.php')
                .then(res => res.json())
                .then(data => {
                    // Populate team dropdown
                    data.teams.forEach(team => {
                        newTeamSelect.innerHTML += `<option value="${team.id}">${team.name}</option>`;
                    });

                    // Add fresh event listener
                    newTeamSelect.addEventListener('change', function() {
                        const selectedTeamId = this.value;
                        const sourceSelect = document.getElementById('source');

                        // Clear recruiter fields
                        recruiterInput.value = '';
                        recruiterIdInput.value = '';
                        sourceSelect.innerHTML = '<option value="">Select the Recruiter</option>';

                        if (selectedTeamId) {
                            // Fetch and populate team members for 'source'
                            fetch(`get_team_recruiters.php?team_id=${selectedTeamId}`)
                                .then(res => res.json())
                                .then(recruitersData => {
                                    // recruitersData is directly an array of recruiters
                                    if (Array.isArray(recruitersData) && recruitersData.length > 0) {
                                        recruitersData.forEach(recruiter => {
                                            sourceSelect.innerHTML +=
                                                `<option value="${recruiter.name}">${recruiter.name}</option>`;
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error('Error fetching team recruiters:', error);
                                });

                            // Assign default manager if available
                            const selectedTeam = data.teams.find(t => t.id == selectedTeamId);
                            if (selectedTeam && selectedTeam.managers && selectedTeam.managers.length > 0) {
                                const manager = selectedTeam.managers[0];
                                recruiterInput.value = manager.name;
                                recruiterIdInput.value = manager.id;
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error fetching teams and managers:', error);
                });
        } else if (CURRENT_USER_ROLE === 'manager' && CURRENT_USER_TEAM_ID) {
            // For managers, automatically populate the recruited by dropdown based on their team
            const sourceSelect = document.getElementById('source');
            sourceSelect.innerHTML = '<option value="">Select the Recruiter</option>';

            // Fetch team members for the manager's team
            fetch(`get_team_recruiters.php?team_id=${CURRENT_USER_TEAM_ID}`)
                .then(res => res.json())
                .then(recruitersData => {
                    // recruitersData is directly an array of recruiters
                    if (Array.isArray(recruitersData) && recruitersData.length > 0) {
                        recruitersData.forEach(recruiter => {
                            sourceSelect.innerHTML +=
                                `<option value="${recruiter.name}">${recruiter.name}</option>`;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching team recruiters for manager:', error);
                });
        }

        document.getElementById('recruitmentModal').classList.remove('hidden');
    }

    function hideRecruitmentModal() {
        document.getElementById('recruitmentModal').classList.add('hidden');
    }
    
    // Helper function to set fields to "add mode" (auto-populated, readonly)
    function setFieldsToAddMode() {
        // Show readonly text inputs, hide dropdowns
        const managerName = document.getElementById('managerName');
        const managerSelect = document.getElementById('managerSelect');
        const teamNameDiv = document.getElementById('teamNameDiv');
        const teamSelect = document.getElementById('teamSelect');
        const source = document.getElementById('source');
        const sourceSelect = document.getElementById('sourceSelect');
        const timestamp = document.getElementById('timestamp');
        
        // Manager field
        if (managerName) {
            managerName.classList.remove('hidden');
            managerName.readOnly = true;
            managerName.classList.add('bg-gray-100', 'text-gray-600');
        }
        if (managerSelect) managerSelect.classList.add('hidden');
        
        // Team field
        if (teamNameDiv) teamNameDiv.classList.remove('hidden');
        if (teamSelect) teamSelect.classList.add('hidden');
        
        // Source field
        if (source) {
            source.classList.remove('hidden');
            source.readOnly = true;
            source.classList.add('bg-gray-100', 'text-gray-600');
        }
        if (sourceSelect) sourceSelect.classList.add('hidden');
        
        // Timestamp field
        if (timestamp) {
            timestamp.readOnly = true;
            timestamp.classList.add('bg-gray-100', 'text-gray-600');
        }
    }
    
    // Helper function to set fields to "edit mode" (editable dropdowns)
    function setFieldsToEditMode() {
        // Hide readonly text inputs, show dropdowns
        const managerName = document.getElementById('managerName');
        const managerSelect = document.getElementById('managerSelect');
        const teamNameDiv = document.getElementById('teamNameDiv');
        const teamSelect = document.getElementById('teamSelect');
        const source = document.getElementById('source');
        const sourceSelect = document.getElementById('sourceSelect');
        const timestamp = document.getElementById('timestamp');
        
        // Manager field - show dropdown for editing (admin only)
        if (CURRENT_USER_ROLE === 'admin') {
            if (managerName) managerName.classList.add('hidden');
            if (managerSelect) {
                managerSelect.classList.remove('hidden');
                managerSelect.classList.add('border', 'rounded', 'px-3', 'py-2', 'w-full', 'focus:outline-none', 'focus:ring-2', 'focus:ring-blue-500');
            }
        } else {
            // For non-admin users, keep manager field as display only
            if (managerName) {
                managerName.classList.remove('hidden');
                managerName.readOnly = true;
                managerName.classList.add('bg-gray-100', 'text-gray-600');
            }
            if (managerSelect) managerSelect.classList.add('hidden');
        }
        
        // Team field - show dropdown for editing (admin only)
        if (CURRENT_USER_ROLE === 'admin') {
            if (teamNameDiv) teamNameDiv.classList.add('hidden');
            if (teamSelect) teamSelect.classList.remove('hidden');
        } else {
            // For non-admin users, keep team field as display only
            if (teamNameDiv) teamNameDiv.classList.remove('hidden');
            if (teamSelect) teamSelect.classList.add('hidden');
        }
        
        // Source field - show dropdown for editing (admin and manager only)
        if (CURRENT_USER_ROLE === 'admin' || CURRENT_USER_ROLE === 'manager') {
            if (source) source.classList.add('hidden');
            if (sourceSelect) {
                sourceSelect.classList.remove('hidden');
                sourceSelect.classList.add('border', 'rounded', 'px-3', 'py-2', 'w-full', 'focus:outline-none', 'focus:ring-2', 'focus:ring-blue-500');
            }
        } else {
            // For regular users, keep source field as display only
            if (source) {
                source.classList.remove('hidden');
                source.readOnly = true;
                source.classList.add('bg-gray-100', 'text-gray-600');
            }
            if (sourceSelect) sourceSelect.classList.add('hidden');
        }
        
        // Timestamp field - make editable
        if (timestamp) {
            timestamp.readOnly = false;
            timestamp.classList.remove('bg-gray-100', 'text-gray-600');
            timestamp.classList.add('border', 'rounded', 'px-3', 'py-2', 'w-full', 'focus:outline-none', 'focus:ring-2', 'focus:ring-blue-500');
        }
        
        // Update labels to show they are editable (based on role)
        const timestampLabel = document.getElementById('timestampLabel');
        const managerLabel = document.getElementById('managerLabel');
        const teamLabel = document.getElementById('teamLabel');
        const sourceLabel = document.getElementById('sourceLabel');
        
        if (timestampLabel) timestampLabel.innerHTML = '<span class="text-[12px] text-red-500">*</span>';
        
        if (CURRENT_USER_ROLE === 'admin') {
            if (managerLabel) managerLabel.innerHTML = '<span class="text-[12px] text-red-500">*</span>';
            if (teamLabel) teamLabel.innerHTML = '<span class="text-[12px] text-red-500">*</span>';
        } else {
            if (managerLabel) managerLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Read-only)</span>';
            if (teamLabel) teamLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Read-only)</span>';
        }
        
        if (CURRENT_USER_ROLE === 'admin' || CURRENT_USER_ROLE === 'manager') {
            if (sourceLabel) sourceLabel.innerHTML = '<span class="text-[12px] text-red-500">*</span>';
        } else {
            if (sourceLabel) sourceLabel.innerHTML = '<span class="text-[12px] text-gray-500">(Read-only)</span>';
        }
    }

    function showOnboardModal(id) {
        document.getElementById('onboardModal').classList.remove('hidden');
        const modalContent = document.querySelector('#onboardModal .p-0');
        modalContent.innerHTML = `
        <div class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-[70vh] overflow-y-auto">
            <!-- Header Section -->
            <div class="relative bg-white border-b border-gray-100 px-8 py-8 shadow-sm">
                <div class="text-center">
                    <!-- Avatar with modern styling -->
                    <div class="relative inline-block mb-6">
                        <div class="w-24 h-24 bg-gradient-to-br from-emerald-400 via-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg ring-4 ring-white">
                            <i class="fa-solid fa-user-astronaut text-white text-3xl"></i>
                        </div>
                        <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-green-500 rounded-full border-4 border-white flex items-center justify-center">
                            <i class="fa-solid fa-plus text-white text-xs"></i>
                        </div>
                    </div>
                    
                    <!-- Agent Name -->
                    <div class="mb-2">
                        <input type="text" id="onboard_name" name="name"
                            class="text-center w-full focus:outline-none text-3xl font-bold text-gray-800 bg-transparent border-b-2 border-transparent hover:border-blue-200 transition-all duration-300 pb-2" 
                            placeholder="Full Name" readonly>
                    </div>
                    
                    <div class="flex items-center justify-center gap-2 text-gray-500">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-sm font-medium">Ready for Onboarding</span>
                    </div>
                </div>
            </div>

            <form id="onboardUserForm" class="px-8 py-6">
                <!-- Login Credentials Section -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 mb-6 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                        <div class="flex items-center gap-3 text-white">
                            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-key text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold">Login Credentials</h3>
                                <p class="text-blue-100 text-sm">Generated automatically for this agent</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <!-- Username -->
                        <div class="group">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2 block">Username</label>
                            <div class="relative">
                                <div class="absolute left-4 top-1/2 transform -translate-y-1/2">
                                    <i class="fa-solid fa-user text-blue-500"></i>
                                </div>
                                <div id="onboard_username" class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl font-mono text-lg text-gray-800 select-all cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-all duration-200 focus:ring-4 focus:ring-blue-100">
                                    user.name.innersparc
                                </div>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="group">
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2 block">Password</label>
                            <div class="relative">
                                <div class="absolute left-4 top-1/2 transform -translate-y-1/2">
                                    <i class="fa-solid fa-lock text-green-500"></i>
                                </div>
                                <div class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl font-mono text-lg text-gray-800 select-all cursor-pointer hover:bg-green-50 hover:border-green-300 transition-all duration-200 focus:ring-4 focus:ring-green-100">
                                    123456789innersparc
                                </div>
                            </div>
                        </div>

                        <!-- Security Notice -->
                        <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
                            <div class="flex items-start gap-3">
                                <div class="w-6 h-6 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <i class="fa-solid fa-shield-halved text-amber-600 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-amber-800 mb-1">Security Notice</h4>
                                    <p class="text-sm text-amber-700">Agent should change password after first login</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 mb-6 overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-600 to-gray-700 px-6 py-4">
                        <div class="flex items-center gap-3 text-white">
                            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-address-card text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold">Contact Information</h3>
                                <p class="text-gray-200 text-sm">Agent details and role</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Email -->
                            <div class="group">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2 block">Email</label>
                                <div class="relative">
                                    <div class="absolute left-4 top-1/2 transform -translate-y-1/2">
                                        <i class="fa-solid fa-envelope text-blue-500"></i>
                                    </div>
                                    <input type="email" id="onboard_email" name="email"
                                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-800 focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-300 transition-all duration-200" 
                                        placeholder="yourname@gmail.com" readonly>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="group">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2 block">Phone</label>
                                <div class="relative">
                                    <div class="absolute left-4 top-1/2 transform -translate-y-1/2">
                                        <i class="fa-solid fa-phone text-green-500"></i>
                                    </div>
                                    <input type="text" id="onboard_phone" name="phone"
                                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-800 focus:outline-none focus:ring-4 focus:ring-green-100 focus:border-green-300 transition-all duration-200" 
                                        placeholder="09123456789" readonly>
                                </div>
                            </div>

                            <!-- Role -->
                            <div class="group">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2 block">Role</label>
                                <div class="relative">
                                    <div class="absolute left-4 top-1/2 transform -translate-y-1/2">
                                        <i class="fa-solid fa-user-tie text-purple-500"></i>
                                    </div>
                                    <input type="text" value="Agent"
                                        class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-800 focus:outline-none focus:ring-4 focus:ring-purple-100 focus:border-purple-300 transition-all duration-200" 
                                        placeholder="Agent" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden Elements -->
                <div class="mb-4">
                    <small class="text-gray-500 hidden" id="onboard_username_preview"></small>
                </div>
                
                <!-- Action Section -->
                <div id="onboardActionSection"></div>
            </form>
            
            <!-- Loading State -->
            <div id="onboardLoading" class="flex flex-col items-center justify-center py-16">
                <div class="relative">
                    <div class="w-16 h-16 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <i class="fa-solid fa-user text-blue-600 text-lg"></i>
                    </div>
                </div>
                <p class="mt-4 text-gray-600 text-lg font-medium">Loading agent details...</p>
                <p class="text-gray-500 text-sm">Please wait while we fetch the information</p>
            </div>
        </div>
    `;

        let onboardLead = null;

        // Fetch the agent details
        const formData = new FormData();
        formData.append("action", "get_recruitment_leads");
        formData.append("filters", JSON.stringify({
            id: id
        }));

        fetch("recruitment-api-debug.php", {
                method: "POST",
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('onboardLoading').remove();
                if (data.success && data.data && data.data.length > 0) {
                    const lead = data.data[0];
                    onboardLead = lead;

                    // Generate automatic username
                    let username = '';
                    if (lead.full_name) {
                        username = lead.full_name.toLowerCase()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .replace(/[^a-z0-9\s]/g, '')
                            .replace(/\s+/g, '');
                        username = username + '.innersparc';
                    }

                    // Fill in the agent details
                    document.getElementById('onboard_name').value = lead.full_name || '';
                    document.getElementById('onboard_email').value = lead.email || '';
                    document.getElementById('onboard_phone').value = lead.contact_number || '';
                    document.getElementById('onboard_username').textContent = username;
                    document.getElementById('onboard_username_preview').textContent = lead.full_name || '';

                    // --- Username existence check ---
                    const checkForm = new FormData();
                    checkForm.append('action', 'check_username_exists');
                    checkForm.append('username', username);

                    fetch('recruitment-api-debug.php', {
                            method: 'POST',
                            body: checkForm
                        })
                        .then(res => res.json())
                        .then(checkData => {
                            const actionSection = document.getElementById('onboardActionSection');
                            if (checkData.success && checkData.exists) {
                                // Username exists, show "already onboarded" message and undo button
                                // Prepare safely escaped args for inline handler
                                const _username = username;
                                const _email = (lead.email || '').replace(/'/g, "\\'");
                                const _phone = (lead.contact_number || '').replace(/'/g, "\\'");
                                const _leadId = lead.id;

                                actionSection.innerHTML = `
                        <div class="p-6 text-center border rounded-lg mb-4">
                        <i class="fa-solid fa-info text-[30px] text-yellow-500"></i>
                            <h2 class="text-xl font-mono font-bold my-4">Agent has been onboarded.</h2>
                            <p class="mb-6 text-sm font-mono opacity-75 mx-[10%]">
                                <span class="font-semibold">${lead.full_name}</span> has already been onboarded in ${lead.recruiter_team}. <br>
                                If this was done by mistake, you can choose to <span class="text-yellow-700 font-semibold">undo</span> the onboarding.
                            </p>

                            <div class="flex flex-row gap-2">
                                <button type="button" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded w-full font-semibold transition-all" onclick="undoOnboardAgent(this, '${_username}', '${_email}', '${_phone}', ${_leadId})">Unboard this Agent</button>
                                <button type="button" class="bg-gray-200 text-gray-700 hover:bg-gray-300 px-4 py-2 rounded w-full transition-all" onclick="hideOnboardModal()">Cancel</button>
                            </div>
                        </div>

                    `;
                            } else {
                                // Not onboarded, show the normal action section (form submit, etc.)
                                actionSection.innerHTML = `
                        <div class="flex flex-col items-center justify-center gap-4 border rounded-lg p-6 mb-4">
                        <i class="fa-solid fa-person-walking-luggage text-[30px] text-blue-500"></i>
                        <h2 class="text-xl font-mono font-bold ">Onboard this Agent</h2>
                        <span class="font-mono">
                        <input type="text" id="onboard_recruiter"
                                class="w-full focus:outline-none text-center font-semibold"
                                placeholder="Your name should appear here" readonly>
                            <p class="text-center text-sm opacity-75 mx-[10%]">Do you want to officially <span
                                    class="text-blue-700 font-semibold">onboard</span>
                                this agent to your
                                team, <input type="text" id="onboard_recruiter_team"
                                    class="inline-block mx-1 bg-none rounded-full border border-blue-700 text-blue-700 italic font-semibold focus:outline-none text-center hover:bg-blue-700 hover:text-white transition-all"
                                    value="Your Team should appear here" oninput="this.style.width = (this.value.length + 1) + 'ch';"
                                    style="width: auto;" readonly>?</p>
                        </span>
                            <div class="flex flex-col md:flex-row items-center mt-2 gap-2 w-full">
                                <button type="submit" id="onboardSubmitBtn"
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full font-semibold transition-all">
                                    Onboard this Agent
                                </button>
                                <button type="button"
                                    class="bg-gray-200 text-gray-700 hover:bg-gray-300 px-4 py-2 rounded  w-full transition-all"
                                    onclick="hideOnboardModal()">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    `;
                                // Fill recruiter and team fields
                                document.getElementById('onboard_recruiter').value = lead.recruiter_name || '';
                                document.getElementById('onboard_recruiter_team').value = lead.recruiter_team ||
                                    'No Team';
                                // Attach the submit handler
                                const onboardForm = document.getElementById('onboardUserForm');
                                if (onboardForm) {
                                    onboardForm.onsubmit = function(e) {
                                        e.preventDefault();
                                        // Collect values
                                        const name = document.getElementById('onboard_name').value;
                                        const email = document.getElementById('onboard_email').value;
                                        const phone = document.getElementById('onboard_phone').value;
                                        const username = document.getElementById('onboard_username').textContent;
                                        const recruiter = document.getElementById('onboard_recruiter')
                                            .value;
                                        const recruiter_team = document.getElementById(
                                            'onboard_recruiter_team').value;
                                        const password = '123456789innersparc';
                                        const role = 'agent';
                                        const team_id = onboardLead ? onboardLead.recruiter_team_id || '' :
                                            '';

                                        const formData = new FormData();
                                        formData.append('action', 'onboard_agent');
                                        formData.append('name', name);
                                        formData.append('email', email);
                                        formData.append('phone', phone);
                                        formData.append('username', username);
                                        formData.append('password', password);
                                        formData.append('role', role);
                                        formData.append('team_id', team_id);

                                        fetch('recruitment-api-debug.php', {
                                                method: 'POST',
                                                body: formData
                                            })
                                            .then(res => res.json())
                                            .then(data => {
                                                console.log('Onboard agent response:', data);
                                                if (data.success) {
                                                    showNotification('Agent onboarded successfully!',
                                                        'success');
                                                    hideOnboardModal();
                                                    loadRecruitmentData();
                                                } else {
                                                    if (data.message === 'Agent already exists') {
                                                        showNotification(
                                                            'This agent is already onboarded.',
                                                            'warning');
                                                    } else {
                                                        showNotification('Error: ' + data.message,
                                                            'error');
                                                    }
                                                }
                                            })
                                            .catch(() => {
                                                showNotification('Error onboarding agent.', 'error');
                                            });
                                    };
                                }
                            }
                        });
                    // --- End username existence check ---
                } else {
                    modalContent.innerHTML +=
                        '<div class="text-center text-red-500">Agent details not found.</div>';
                }
            })
            .catch(() => {
                modalContent.innerHTML +=
                    '<div class="text-center text-red-500">Error loading agent details.</div>';
            });
    }

    // Undo onboard function
    function undoOnboardAgent(btnEl, username, email = '', phone = '', leadId = null) {
        console.log('[UNDO] Clicked Unboard with:', { username, email, phone, leadId });
        try {
            if (!confirm('Are you sure you want to undo this onboard action?')) return;

            // Show temporary loading state on the button if present
            const btn = btnEl || null;
            const originalText = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Unboarding...';
            }

            const fd = new FormData();
            fd.append('action', 'delete_agent_by_username');
            fd.append('username', username);
            if (email) fd.append('email', email);
            if (phone) fd.append('phone', phone);
            if (leadId !== null && leadId !== undefined) fd.append('lead_id', leadId);

            console.log('[UNDO] Sending payload:', Array.from(fd.entries()));

            fetch('recruitment-api-debug.php', {
                    method: 'POST',
                    body: fd
                })
                .then(async res => {
                    const text = await res.text();
                    console.log('[UNDO] Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response');
                    }
                })
                .then(data => {
                    console.log('[UNDO] Parsed response:', data);
                    if (data.success) {
                        showNotification('Agent successfully removed. Updated leads: ' + (data.updated_leads ?? 0), 'success');
                        hideOnboardModal();
                        loadRecruitmentData();
                    } else {
                        showNotification('Error: ' + (data.message || 'Failed to unboard agent'), 'error');
                    }
                })
                .catch(err => {
                    console.error('[UNDO] Request failed:', err);
                    showNotification('Network or server error while unboarding.', 'error');
                })
                .finally(() => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                });
        } catch (e) {
            console.error('[UNDO] Unexpected error:', e);
            showNotification('Unexpected error. Please try again.', 'error');
        }
    }

    function generateUsername() {
        const fullName = document.getElementById('user_name').value.trim();
        if (fullName) {
            let username = fullName.toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '') // Remove diacritics
                .replace(/[^a-z0-9\s]/g, '') // Remove special characters
                .replace(/\s+/g, ''); // Replace spaces with dots
            username = username + '.innersparc';
            document.getElementById('user_username').value = username;
            document.getElementById('usernamePreview').textContent = username;
        } else {
            document.getElementById('user_username').value = '';
            document.getElementById('usernamePreview').textContent = '';
        }
    }

    function hideOnboardModal() {
        document.getElementById('onboardModal').classList.add('hidden');
    }

    function handleOnboardingFilter(value) {
        console.log('Handling onboarding filter change:', value);
        const currentFilter = document.getElementById('currentFilter');
        
        // Store the raw value for debugging
        currentFilter.setAttribute('data-value', value);
        
        // Show current filter value
        currentFilter.textContent = value === '' ? '' : 
                                  value === '1' ? 'Showing Onboarded' : 
                                  'Showing Pending';
        
        // Apply filters with debug logging
        console.log('Before filter application - currentFilters:', {...currentFilters});
        applyFilters();
    }

    // FIXED: Working edit function
    function editLead(id) {
        console.log('Editing lead with ID:', id);
        showNotification("Loading lead data...", "info");

        const formData = new FormData();
        formData.append("action", "get_recruitment_leads");
        formData.append("filters", JSON.stringify({
            id
        }));

        // Step 1: Fetch lead data first
        fetch("recruitment-api-debug.php", {
                method: "POST",
                body: formData,
            })
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                if (!(data.success && data.data?.length > 0)) {
                    showNotification("Lead not found", "error");
                    return;
                }

                const lead = data.data[0];

                // Step 2: Populate non-dependent fields
                document.getElementById("modalTitle").textContent = "Edit Recruited Agent";
                document.getElementById("leadId").value = lead.id;
                document.getElementById("fullName").value = lead.full_name || "";
                document.getElementById("contactNumber").value = lead.contact_number || "";
                document.getElementById("email").value = lead.email || "";
                document.getElementById("status").value = lead.status || "";
                document.getElementById("remarks").value = lead.remarks || "";
                
                // Set fields to edit mode first
                setFieldsToEditMode();

                // Populate timestamp (date of entry) from original data
                if (lead.created_at) {
                    // Convert "YYYY-MM-DD HH:MM:SS" to "YYYY-MM-DD"
                    const dateOnly = lead.created_at.split(' ')[0];
                    document.getElementById("timestamp").value = dateOnly;
                } else {
                    document.getElementById("timestamp").value = "";
                }
                
                // Populate manager data from lead (preserve original)
                const managerNameField = document.getElementById("managerName");
                const managerSelectField = document.getElementById("managerSelect");
                const managerIdField = document.getElementById("managerId");
                
                if (lead.recruiter_name) {
                    if (managerNameField) managerNameField.value = lead.recruiter_name;
                    if (managerSelectField) {
                        // Try to find and select the matching manager in dropdown
                        for (let option of managerSelectField.options) {
                            if (option.text === lead.recruiter_name) {
                                option.selected = true;
                                break;
                            }
                        }
                    }
                }
                if (lead.recruiter_id && managerIdField) {
                    managerIdField.value = lead.recruiter_id;
                }
                
                // Populate team data from lead (preserve original)
                const teamNameText = document.getElementById("teamNameText");
                const teamIdField = document.getElementById("teamId");
                const teamSelectField = document.getElementById("teamSelect");
                
                if (lead.recruiter_team) {
                    if (teamNameText) teamNameText.textContent = lead.recruiter_team;
                    if (teamSelectField) {
                        teamSelectField.value = lead.recruiter_team_id || '';
                    }
                }
                if (lead.recruiter_team_id && teamIdField) {
                    teamIdField.value = lead.recruiter_team_id;
                }
                
                // Populate source (recruited by) from lead (preserve original)
                const sourceField = document.getElementById("source");
                const sourceSelectField = document.getElementById("sourceSelect");
                const sourceIdField = document.getElementById("sourceId");
                
                if (lead.source) {
                    if (sourceField) sourceField.value = lead.source;
                    if (sourceSelectField) {
                        // Try to find and select the matching recruiter in dropdown
                        for (let option of sourceSelectField.options) {
                            if (option.text === lead.source) {
                                option.selected = true;
                                break;
                            }
                        }
                    }
                }
                if (lead.source_id && sourceIdField) {
                    sourceIdField.value = lead.source_id;
                }

                // Set checklist checkboxes
                allKeys.forEach(id => {
                    const cb = document.getElementById(id);
                    const dbKey = id.replace(/-/g, '_');
                    if (cb) cb.checked = !!lead[dbKey];
                });
                updateProgress();

                // Step 3: For admin, fetch teams then populate dropdowns based on lead
                if (CURRENT_USER_ROLE === 'admin') {
                    fetch('get_teams_and_managers.php')
                        .then(res => res.json())
                        .then(teamsData => {
                            const teamSelect = document.getElementById("teamName");
                            const recruiterInput = document.getElementById("recruiterName");
                            const recruiterIdInput = document.getElementById("recruiterId");
                            const sourceSelect = document.getElementById("source");

                            // Clear and populate team dropdown
                            if (teamSelect) {
                                teamSelect.innerHTML = '<option value="">Select Team</option>';
                                teamsData.teams.forEach(team => {
                                    const opt = document.createElement('option');
                                    opt.value = team.id;
                                    opt.textContent = team.name;
                                    teamSelect.appendChild(opt);
                                });
                            }

                            // Helper: populate team members into 'source'
                            const loadTeamMembers = async (teamId, selectedSource = null) => {
                                const sourceSelect = document.getElementById("source");

                                // Clear ALL children options (this is more foolproof than setting innerHTML)
                                while (sourceSelect.firstChild) {
                                    sourceSelect.removeChild(sourceSelect.firstChild);
                                }

                                // Add default option
                                const defaultOpt = document.createElement('option');
                                defaultOpt.value = '';
                                defaultOpt.textContent = 'Select a member';
                                sourceSelect.appendChild(defaultOpt);

                                try {
                                    const res = await fetch('get_team_recruiters.php?team_id=' +
                                        teamId);
                                    const members = await res.json();

                                    const addedNames = new Set(); // prevent duplicates

                                    members.forEach(member => {
                                        if (!addedNames.has(member.name)) {
                                            addedNames.add(member.name);
                                            const opt = document.createElement('option');
                                            opt.value = member.name;
                                            opt.textContent = member.name;
                                            sourceSelect.appendChild(opt);
                                        }
                                    });

                                    // Preselect the source if given
                                    if (selectedSource) {
                                        sourceSelect.value = selectedSource;
                                    }

                                } catch (err) {
                                    console.error("Failed to load team members:", err);
                                }
                            };


                            // Handle team change
                            teamSelect.onchange = function() {
                                const selectedTeam = teamsData.teams.find(t => t.id == this.value);

                                recruiterInput.value = selectedTeam?.managers?. [0]?.name || '';
                                recruiterIdInput.value = selectedTeam?.managers?. [0]?.id || '';

                                if (this.value) {
                                    loadTeamMembers(this.value); // without selectedSource
                                } else {
                                    if (sourceSelect) {
                                        sourceSelect.innerHTML = '<option value="">Select a member</option>';
                                    }
                                }
                            };

                            // Preselect team if exists and load members
                            if (lead.recruiter_team_id) {
                                teamSelect.value = lead.recruiter_team_id;
                                loadTeamMembers(lead.recruiter_team_id, lead.source);
                            }

                            if (lead.recruiter_name) {
                                recruiterInput.value = lead.recruiter_name;
                            }
                            if (lead.recruiter_id) {
                                recruiterIdInput.value = lead.recruiter_id;
                            }

                            // Set source field to show who recruited this agent (from database)
                            if (lead.source) {
                                // For edit mode, show the actual recruiter from database, not current user
                                const sourceField = document.getElementById("source");
                                if (sourceField) {
                                    sourceField.value = lead.source;
                                    sourceField.readOnly = false; // Allow editing in admin mode
                                }
                                const sourceIdField = document.getElementById("sourceId");
                                if (sourceIdField && lead.source_id) {
                                    sourceIdField.value = lead.source_id;
                                }
                            }
                        })

                }

                // Show modal
                document.getElementById('recruitmentModal').classList.remove('hidden');
                showNotification("Lead data loaded successfully", "success");
            })
            .catch(err => {
                console.error("Error loading lead:", err);
                showNotification("Error loading lead data", "error");
            });
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
                    const message = isUpdate ? "Agent updated successfully!" : "Agent added successfully!"
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
        if (confirm("Are you sure you want to delete this recruited agent?")) {
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

    // Load recruitment statistics with current filters
    function loadRecruitmentStats() {
        console.log('Loading recruitment statistics with filters:', currentFilters);
        
        const formData = new FormData();
        formData.append('action', 'get_recruitment_stats_with_filters');
        formData.append('filters', JSON.stringify(currentFilters));
        
        fetch('recruitment-api-debug.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update statistics cards
                document.getElementById('totalAgentsStat').textContent = data.stats.total_recruited || 0;
                document.getElementById('activeAgentsStat').textContent = data.stats.active_agents || 0;
                document.getElementById('inactiveAgentsStat').textContent = data.stats.inactive_agents || 0;
                document.getElementById('onboardedAgentsStat').textContent = data.stats.onboarded_agents || 0;
                
                console.log('Statistics updated successfully');
            } else {
                console.error('Error loading statistics:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching statistics:', error);
        });
    }

    function refreshData() {
        console.log('Refreshing all data...');
        loadRecruitmentStats(); // Refresh statistics
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

    function getProgressPercent(lead) {
        const keys = [
            'pre_assessment', 'accreditation', 'assessment', 'sales_training', 'site_tour', 'focus_projects',
            'habit_forming', 'digital_training', 'sales_training_materials', 'objection_handling', 'VAST',
            'sales_monitoring',
            'LMS', 'comm_structure'
        ];
        let checked = 0;
        keys.forEach(k => {
            if (lead[k]) checked++;
        });
        return Math.round((checked / keys.length) * 100);
    }

    function getPreRecruitmentPercent(lead) {
        const keys = [
            'pre_assessment', 'accreditation', 'assessment', 'sales_training', 'site_tour', 'focus_projects'
        ];
        let checked = 0;
        keys.forEach(k => {
            if (lead[k]) checked++;
        });

        const percent = Math.round((checked / keys.length) * 100);

        // Show button if 100%, hide otherwise
        const btn = document.getElementById(`onboardBtn-${lead.id}`);
        if (btn) {
            if (percent === 100) {
                btn.classList.remove("hidden");
                btn.classList.add("inline-block");
            } else {
                btn.classList.add("hidden");
                btn.classList.remove("inline-block");
            }
        }

        return percent;
    }


    function getPostRecruitmentPercent(lead) {
        const keys = [
            'habit_forming', 'digital_training', 'sales_training_materials', 'objection_handling', 'VAST',
            'sales_monitoring',
            'LMS', 'comm_structure'
        ];
        let checked = 0;
        keys.forEach(k => {
            if (lead[k]) checked++;
        });
        return Math.round((checked / keys.length) * 100);
    }
    </script>
    <script>
    // Checklist keys for each section (make global)
    const preRecruitmentKeys = [
        'pre-assessment', 'accreditation', 'assessment', 'sales_training', 'site_tour', 'focus_projects'
    ];
    const postRecruitmentKeys = [
        'habit_forming', 'digital_training', 'sales_training_materials', 'objection_handling', 'VAST',
        'sales_monitoring', 'LMS', 'comm_structure'
    ];
    const allKeys = preRecruitmentKeys.concat(postRecruitmentKeys);
    let checkboxes = [];
    let progressBar, progressText, preRecruitmentProgressBar, preRecruitmentProgressText, postRecruitmentProgressBar,
        postRecruitmentProgressText;

    function updateProgress() {
        // Re-query checkboxes in case DOM changed
        checkboxes = allKeys.map(id => document.getElementById(id)).filter(Boolean);
        progressBar = document.getElementById('progressBar');
        progressText = document.getElementById('progressText');
        preRecruitmentProgressBar = document.getElementById('preRecruitmentProgressBar');
        preRecruitmentProgressText = document.getElementById('preRecruitmentProgressText');
        postRecruitmentProgressBar = document.getElementById('postRecruitmentProgressBar');
        postRecruitmentProgressText = document.getElementById('postRecruitmentProgressText');
        // Overall
        let checked = 0;
        checkboxes.forEach(cb => {
            if (cb && cb.checked) checked++;
        });
        const percent = checkboxes.length > 0 ? Math.round((checked / checkboxes.length) * 100) : 0;
        if (progressBar) progressBar.style.width = percent + '%';
        if (progressText) progressText.textContent = percent + '%';
        // Pre-recruitment
        let preChecked = 0;
        preRecruitmentKeys.forEach(id => {
            const cb = document.getElementById(id);
            if (cb && cb.checked) preChecked++;
        });
        const prePercent = preRecruitmentKeys.length > 0 ? Math.round((preChecked / preRecruitmentKeys.length) * 100) :
            0;
        if (preRecruitmentProgressBar) preRecruitmentProgressBar.style.width = prePercent + '%';
        if (preRecruitmentProgressText) preRecruitmentProgressText.textContent = prePercent + '%';
        // Post-recruitment
        let postChecked = 0;
        postRecruitmentKeys.forEach(id => {
            const cb = document.getElementById(id);
            if (cb && cb.checked) postChecked++;
        });
        const postPercent = postRecruitmentKeys.length > 0 ? Math.round((postChecked / postRecruitmentKeys.length) *
            100) : 0;
        if (postRecruitmentProgressBar) postRecruitmentProgressBar.style.width = postPercent + '%';
        if (postRecruitmentProgressText) postRecruitmentProgressText.textContent = postPercent + '%';
    }

    document.addEventListener('DOMContentLoaded', function() {
        checkboxes = allKeys.map(id => document.getElementById(id)).filter(Boolean);
        checkboxes.forEach(cb => cb && cb.addEventListener('change', updateProgress));
        updateProgress();
    });
    </script>
    <script>
    
    // Enhanced recruitment progress button interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Add click feedback to all recruitment progress buttons
        const progressButtons = document.querySelectorAll('.recruitment-progress-btn');
        
        progressButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Add ripple effect
                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                ripple.style.pointerEvents = 'none';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple-effect 0.6s linear';
                
                const rect = button.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                
                button.appendChild(ripple);
                
                // Remove ripple after animation
                setTimeout(() => {
                    if (ripple.parentNode) {
                        ripple.parentNode.removeChild(ripple);
                    }
                }, 600);
                
                // Add temporary "pressed" class for enhanced feedback
                button.classList.add('button-pressed');
                setTimeout(() => {
                    button.classList.remove('button-pressed');
                }, 150);
            });
            
            // Add hover sound effect (optional - can be enabled if desired)
            button.addEventListener('mouseenter', function() {
                // Subtle scale increase on hover
                button.style.transform = 'scale(1.02)';
            });
            
            button.addEventListener('mouseleave', function() {
                button.style.transform = 'scale(1)';
            });
        });
    });
    
    // Add ripple effect keyframes
    const rippleStyle = document.createElement('style');
    rippleStyle.innerHTML = `
        @keyframes ripple-effect {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .button-pressed {
            transform: scale(0.98) !important;
            filter: brightness(0.95);
        }
        
        .recruitment-progress-btn {
            overflow: hidden !important;
        }
        
        /* Tooltip enhancement */
        .recruitment-progress-btn {
            position: relative;
        }
        
        .recruitment-progress-btn:hover::after {
            content: 'Click to toggle';
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            animation: tooltip-fade-in 0.3s ease forwards;
        }
        
        @keyframes tooltip-fade-in {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        /* Enhanced glow effect for better interactivity indication */
        .recruitment-progress-btn:hover {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
        }
        
        .recruitment-progress-btn.orange:hover {
            box-shadow: 0 0 20px rgba(249, 115, 22, 0.3);
        }
        
        /* Modern card entrance animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modern-agent-card {
            animation: slideInUp 0.4s ease-out forwards;
            animation-fill-mode: both;
        }
        
        /* Stagger animation delay for cards */
        .modern-agent-card:nth-child(1) { animation-delay: 0.1s; }
        .modern-agent-card:nth-child(2) { animation-delay: 0.15s; }
        .modern-agent-card:nth-child(3) { animation-delay: 0.2s; }
        .modern-agent-card:nth-child(4) { animation-delay: 0.25s; }
        .modern-agent-card:nth-child(5) { animation-delay: 0.3s; }
        .modern-agent-card:nth-child(6) { animation-delay: 0.35s; }
        .modern-agent-card:nth-child(7) { animation-delay: 0.4s; }
        .modern-agent-card:nth-child(8) { animation-delay: 0.45s; }
        .modern-agent-card:nth-child(n+9) { animation-delay: 0.5s; }
        
        /* Enhanced progress bar animations */
        .progress-bar-animated {
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.4) 50%, transparent 70%);
            background-size: 25px 25px;
            animation: progress-shimmer 2s infinite;
        }
        
        @keyframes progress-shimmer {
            0% { background-position: -25px 0; }
            100% { background-position: 100px 0; }
        }
        
        /* Status badge pulse */
        .status-active {
            animation: status-pulse 2s infinite;
        }
        
        @keyframes status-pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4);
            }
            50% {
                box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
            }
        }
    `;
    document.head.appendChild(rippleStyle);

</script>
<script src="assets/js/script.js"></script>
</body>
</html>