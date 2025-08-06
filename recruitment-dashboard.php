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
                            onclick="showAddModal()">
                            <i class="fas fa-plus"></i> New Agent
                        </button>
                        <button type="button"
                            class="border border-gray-400 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-100 flex items-center gap-2"
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
                                    class="bg-blue-600 text-white px-3 py-1.5 rounded-md text-sm hover:bg-blue-700 flex items-center gap-2"
                                    onclick="applyFilters()">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <button type="button"
                                    class="border border-gray-400 text-gray-700 px-3 py-1.5 rounded-md text-sm hover:bg-gray-100 flex items-center gap-2"
                                    onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear All
                                </button>
                            </div>
                        </div>
                        <!-- Active Filters Display -->
                        <div id="activeFilters" class="mb-3 hidden">
                            <small class="">Active filters:</small>
                            <div id="activeFilterTags" class="mt-1 flex flex-row"></div>
                        </div>
                        <!-- Filter Row -->
                        <div class="flex flex-col md:flex-row gap-4">
                            <div class="flex-1">
                                <label for="searchInput" class="block text-sm font-medium mb-1">Search</label>
                                <input type="text"
                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    id="searchInput" placeholder="Search name, email, phone..."
                                    oninput="debouncedApplyFilters()">
                            </div>
                            <div class="flex-1">
                                <label for="filterStatus" class="block text-sm font-medium mb-1">Status</label>
                                <select
                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    id="filterStatus">
                                    <option value="">All Statuses</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <?php if ($user['role'] === 'admin'): ?>
                            <div class="flex-1">
                                <label for="filterTeam" class="block text-sm font-medium mb-1">Team</label>
                                <select
                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    id="filterTeam">
                                    <option value="">All Teams</option>
                                    <!-- Teams will be populated by JavaScript -->

                                    <?php foreach ($teams as $team): ?>
                                    <option value="<?= htmlspecialchars($team['id']) ?>">
                                        <?= htmlspecialchars($team['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="flex-1 hidden">
                                <label for="filterSource" class="block text-sm font-medium mb-1">Recruited by</label>
                                <select
                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    id="filterSource">
                                </select>
                            </div>

                        </div>
                        <!-- Results Summary -->
                        <div id="filterResults" class="mt-3 hidden">
                            <small class="text-gray-500 flex items-center gap-2">
                                <i class="fas fa-info-circle"></i>
                                <span id="resultsCount">0</span> Results found
                                <span id="filterTime"></span>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Agents Table -->
                <div class="rounded-xl shadow bg-white pb-3">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h5 class="text-lg font-semibold mb-0">Recruited Agents</h5>
                    </div>
                    <div class="">
                        <div class="overflow-x-auto">
                            <div class="max-h-[400px] overflow-y-auto">
                                <!-- Recruitment Table -->
                                <table id="recruitmentTable"
                                    class="w-full min-w-[900px] border-separate border-spacing-0 rounded-xl text-left bg-white">
                                    <thead class="sticky top-0 z-10 bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-4 font-semibold text-xs uppercase tracking-wider text-gray-600 border-b border-gray-200 cursor-pointer whitespace-nowrap bg-gray-50"
                                                onclick="sortTable('created_at')">
                                                Date of Entry <i class="fas fa-sort"></i>
                                            </th>
                                            <th
                                                class="px-6 py-4 font-semibold text-xs uppercase tracking-wider text-gray-600 border-b border-gray-200 whitespace-nowrap bg-gray-50">
                                                Status</th>
                                            <th class="px-6 py-4 font-semibold text-xs uppercase tracking-wider text-gray-600 border-b border-gray-200 cursor-pointer whitespace-nowrap bg-gray-50"
                                                onclick="sortTable('full_name')">
                                                Agent Details <i class="fas fa-sort"></i>
                                            </th>
                                            <th
                                                class="px-6 py-4 font-semibold text-xs uppercase tracking-wider text-gray-600 border-b border-gray-200 whitespace-nowrap bg-gray-50">
                                                Manager</th>
                                            <th
                                                class="px-6 py-4 font-semibold text-xs uppercase tracking-wider text-gray-600 border-b border-gray-200 whitespace-nowrap bg-gray-50">
                                                Progress</th>
                                            <th
                                                class="px-6 py-4 font-semibold text-xs uppercase tracking-wider text-gray-600 border-b border-gray-200 whitespace-nowrap bg-gray-50">
                                                Recruited by</th>
                                            <th
                                                class="px-6 py-4 font-semibold text-xs uppercase tracking-wider text-gray-600 border-b border-gray-200 whitespace-nowrap bg-gray-50">
                                                Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recruitmentTableBody">
                                        <tr>
                                            <td colspan="8" class="text-center py-8">
                                                <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8v8z">
                                                    </path>
                                                </svg>
                                                <p class="mt-2 text-gray-500">Loading recruitment data...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

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

                <!-- Active/Inactive Agents by Team Section -->
                <div class="mt-6 p-6 py-4 rounded-xl shadow bg-white" id="team-status-section">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between mb-4 gap-4">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 w-full">
                            <span id="teamStatusTitle"
                                class="text-lg font-semibold flex items-center gap-2 whitespace-nowrap">
                                <!-- Title will be set by JS -->
                            </span>
                            <div class="flex flex-col items-end gap-2">
                                <div class="flex gap-2 bg-gray-100 rounded-md p-1.5">
                                    <?php
                                    $filter_style = 'grouping-toggle px-3 py-1.5 rounded-md text-xs font-medium tracking-wider focus:ring-2 focus:ring-blue-500 hover:bg-blue-600 hover:text-white active:bg-blue-700 active:text-white uppercase transition-colors duration-200';
                                    ?>
                                    <button id="grouping-year"
                                        class="<?php echo htmlspecialchars($filter_style); ?> bg-blue-600 text-white"
                                        onclick="setGrouping('year')">Year</button>
                                    <button id="grouping-month"
                                        class="<?php echo htmlspecialchars($filter_style); ?> bg-blue-600 text-white"
                                        onclick="setGrouping('month')">Month</button>
                                    <button id="grouping-quarter"
                                        class="<?php echo htmlspecialchars($filter_style); ?> bg-blue-600 text-white"
                                        onclick="setGrouping('quarter')">Quarter</button>
                                </div>
                                <div>
                                    <select id="filterYear" class="border rounded-md px-3 py-1.5 text-sm ml-2"></select>
                                    <select id="filterMonth"
                                        class="border rounded-md px-3 py-1.5 text-sm ml-2 hidden"></select>
                                    <select id="filterQuarter"
                                        class="border rounded-md px-3 py-1.5 text-sm ml-2 hidden">
                                        <option value="1">Quarter 1 (January - March)</option>
                                        <option value="2">Quarter 2 (April - June)</option>
                                        <option value="3">Quarter 3 (July - September)</option>
                                        <option value="4">Quarter 4 (October - December)</option>
                                    </select>
                                </div>

                            </div>
                        </div>
                        <div class="flex flex-row items-center justify-end gap-2 md:mt-0">
                            <button type="button"
                                class="bg-white border border-gray-300 text-gray-500 px-4 py-2 rounded-md hover:border-gray-500 hover:text-gray-700 flex items-center gap-2"
                                onclick="refreshTeamStatus()" title="Refresh Team Status">
                                <i class="fas fa-refresh"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
                        <!-- Active Table & Chart -->
                        <div
                            class="border hover:border-green-500 bg-gradient-to-br from-gray-100 via-white to-gray-100 hover:from-gray-100 hover:via-green-100 hover:to-gray-100 p-6 rounded-xl transition-colors transition-transform duration-300 ease-in-out hover:-translate-y-1 hover:shadow-lg group">
                            <h3
                                class="text-lg font-medium mb-2 text-green-700 flex items-center gap-2 pb-2 border-b border-gray-300 uppercase font-medium tracking-wider">
                                <i class="fas fa-user-check"></i> Active Agents
                            </h3>
                            <!-- Active Chart -->
                            <canvas id="activeAgentsChart" height="180"></canvas>
                            <!-- Active Teams Table -->
                            <div class="overflow-x-auto mt-4 rounded-xl">
                                <table
                                    class="w-full min-w-[300px] border-separate border-spacing-2  text-left bg-white">
                                    <thead class="text-white">
                                        <tr class="whitespace-nowrap">
                                            <th
                                                class="px-4 py-2 font-bold rounded-lg text-center bg-green-600 text-white text-xs uppercase tracking-wider">
                                                Team</th>
                                            <th
                                                class="px-4 py-2 font-bold rounded-lg text-center bg-green-600 text-white text-xs uppercase tracking-wider">
                                                Total Active Count</th>
                                        </tr>
                                    </thead>
                                    <tbody id="activeAgentsTableBody">
                                        <tr>
                                            <td colspan="2" class="text-center text-gray-400 py-4">No data</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- Inactive Table & Chart -->
                        <div
                            class="border hover:border-red-500 bg-gradient-to-br from-gray-100 via-white to-gray-100 hover:from-gray-100 hover:via-red-100 hover:to-gray-100 p-6 rounded-xl transition-all transition-transform duration-300 ease-in-out hover:-translate-y-1 hover:shadow-lg group">
                            <h3
                                class="text-lg font-medium mb-2 text-red-700 flex items-center gap-2 pb-2 border-b border-gray-300 uppercase font-medium tracking-wider">
                                <i class="fas fa-user-times"></i>
                                Inactive Agents
                            </h3>
                            <!-- Inactive Chart -->
                            <canvas id="inactiveAgentsChart" height="180"></canvas>
                            <!-- Inactive Teams Table -->
                            <div class="overflow-x-auto mt-4 rounded-xl">
                                <table
                                    class="w-full min-w-[300px] border-separate border-spacing-2  text-left bg-white">
                                    <thead class="text-white">
                                        <tr class="whitespace-nowrap">
                                            <th
                                                class="px-4 py-2 font-bold rounded-lg text-center bg-red-600 text-white text-xs uppercase tracking-wider">
                                                Team</th>
                                            <th
                                                class="px-4 py-2 font-bold rounded-lg text-center bg-red-600 text-white text-xs uppercase tracking-wider">
                                                Total Inactive Count</th>
                                        </tr>
                                    </thead>
                                    <tbody id="inactiveAgentsTableBody">
                                        <tr>
                                            <td colspan="2" class="text-center text-gray-400 py-4">No data</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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
                                                </label>
                                                <input type="datetime-local"
                                                    class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    id="timestamp" name="timestamp">
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
                                                <label for="recruiterName"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Manager
                                                    <?= $automatic_mark; ?></label>
                                                <?php if ($current_user['role'] === 'admin'): ?>
                                                <!-- <select id="recruiterName" name="recruiter_id"
                                                class="border rounded px-3 py-2 w-full bg-white"></select> -->
                                                <!-- Readonly Manager Field -->
                                                <input type="text" id="recruiterName" name="recruiter_name"
                                                    class="border rounded px-3 py-2 w-full bg-gray-100" readonly
                                                    aria-readonly="true">

                                                <!-- Hidden ID for form processing -->
                                                <input type="hidden" id="recruiterId" name="recruiter_id">
                                                <?php else: ?>
                                                <input type="text" class="border rounded px-3 py-2 w-full bg-gray-100"
                                                    id="recruiterName" name="recruiter_name"
                                                    value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                                                    readonly aria-readonly="true">
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <label for="teamName"
                                                    class="<?= htmlspecialchars($label_name_style) ?>">Team
                                                    <?= $automatic_mark; ?></label>
                                                <?php if ($current_user['role'] === 'admin'): ?>
                                                <select id="teamName" name="team_id"
                                                    class="border rounded px-3 py-2 w-full bg-white"></select>
                                                <?php else: ?>
                                                <input type="text" class="border rounded px-3 py-2 w-full bg-gray-100"
                                                    id="teamName" name="team_name"
                                                    value="<?php echo htmlspecialchars($teamName); ?>" readonly
                                                    aria-readonly="true">
                                                <?php endif; ?>
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
                                                    <?= $required_mark; ?></label>
                                                <!-- <select
                                            class="border rounded px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            id="source" name="source" required>
                                            <option value="Referral">Referral</option>
                                        </select> -->
                                                <select id="source" name="source"
                                                    class="border rounded px-3 py-2 w-full bg-white">
                                                    <option value="">Select a member</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="recruitment_progress"
                                                class="<?= htmlspecialchars($label_name_style) ?>">Recruitment
                                                Progress
                                                <?= $required_mark; ?></label>

                                            <?php

                                            $checklist_style = 'w-full text-center py-2 px-3 border rounded-lg transition-all duration-300 cursor-pointer bg-white text-gray-800';

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
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">
                                                            Pre-Assessment
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="accreditation" name="accreditation"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">
                                                            Accreditation
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="assessment" name="assessment"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">
                                                            Assessment
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="sales_training" name="sales_training"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">
                                                            Sales 101
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="site_tour" name="site_tour" value="1"
                                                            class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">
                                                            Site Tour
                                                        </div>
                                                    </label>
                                                    <label class="block">
                                                        <input type="checkbox" id="focus_projects" name="focus_projects"
                                                            value="1" class="peer hidden">
                                                        <div
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">
                                                            Focus Projects
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
                                                            class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500">
                                                            Habit Forming
                                                        </div>
                                                    </label>
                                                    <div class="space-y-2">
                                                        <label for="training_materials"
                                                            class="<?php echo htmlspecialchars($label_style) ?> text-xs">
                                                            Training Materials
                                                        </label>
                                                        <div class="flex justify-end">
                                                            <div
                                                                class="<?php echo htmlspecialchars($label_subsection_style) ?>">
                                                                <label class="block">
                                                                    <input type="checkbox" id="digital_training"
                                                                        name="digital_training" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500">
                                                                        Digital Marketing Training
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="sales_training_materials"
                                                                        name="sales_training_materials" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500">
                                                                        Sales Training
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="objection_handling"
                                                                        name="objection_handling" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500">
                                                                        Objection Handling
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label for="tools_familiarization"
                                                            class="<?php echo htmlspecialchars($label_style) ?> text-xs">
                                                            Tools Familiarization
                                                        </label>
                                                        <div class="flex justify-end">
                                                            <div
                                                                class="<?php echo htmlspecialchars($label_subsection_style) ?>">
                                                                <label class="block">
                                                                    <input type="checkbox" id="VAST" name="VAST"
                                                                        value="1" class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500">
                                                                        VAST Training
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="sales_monitoring"
                                                                        name="sales_monitoring" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500">
                                                                        Google Site (Sales Monitoring)
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="LMS" name="LMS" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500">
                                                                        Lead Management System
                                                                    </div>
                                                                </label>
                                                                <label class="block">
                                                                    <input type="checkbox" id="comm_structure"
                                                                        name="comm_structure" value="1"
                                                                        class="peer hidden">
                                                                    <div
                                                                        class="<?php echo htmlspecialchars($checklist_style); ?> peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500">
                                                                        Comm Structure
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
        // Removed loadStats() call
        loadRecruitmentData()

        // Add keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts)

        populateTeamAndRecruiterDropdowns();
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
        // Only show all leads for admin, else filter by recruiter_id
        let visibleLeads = leads;
        if (CURRENT_USER_ROLE === 'manager') {
            visibleLeads = leads.filter(lead => lead.recruiter_id == CURRENT_USER_ID);
        }

        const tbody = document.getElementById("recruitmentTableBody");
        tbody.innerHTML = "";

        if (!visibleLeads || visibleLeads.length === 0) {
            tbody.innerHTML = `
        <tr>
            <td colspan="8" class="text-center text-muted py-4">
                <i class="fas fa-search fa-2x mb-2 d-block"></i>
                <p class="mb-0">No recruitment leads found</p>
                <small>Try adjusting your filters or search terms</small>
            </td>
        </tr>
    `;
            return;
        }

        visibleLeads.forEach((lead, index) => {
            const row = document.createElement("tr")
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

            row.style.animationDelay = `${index * 50}ms`
            row.className = 'fade-in hover:bg-gray-50 transition-colors';

            function maskWord(word) {
                if (word.length <= 2) return word[0] + '*'.repeat(word.length - 1);
                if (word.length <= 4) return word[0] + '*'.repeat(word.length - 2) + word[word.length - 1];
                return word.slice(0, 2) + '*'.repeat(word.length - 4) + word.slice(-2);
            }

            function maskText(text) {
                if (!text) return '';
                return text.split(' ').map(maskWord).join(' ');
            }

            row.innerHTML = `
        <td class="px-6 py-4 align-middle">${timestamp || 'N/A'}</td>
        <td class="px-6 py-4 align-middle">
  ${lead.status === 'Active' ? `<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Active</span>` :
                        lead.status === 'Inactive' ? `<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Inactive</span>` :
                            `<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">${lead.status || 'N/A'}</span>`}
        </td>
        <td class="px-6 py-4 align-middle whitespace-nowrap">
            <strong class="block max-w-[200px] truncate" title="${lead.full_name}">
                ${canSeeDetails ? (lead.full_name || 'N/A') : maskText(lead.full_name || '')}
            </strong>
        <div class="align-middle opacity-50 text-xs"><i class="fa fa-phone" aria-hidden="true"></i> ${canSeeDetails ? (lead.contact_number || 'No contact number found') : maskText(lead.contact_number || '')}</div>
        <div class="align-middle opacity-50 text-xs"><i class="fa fa-envelope" aria-hidden="true"></i> ${canSeeDetails ? (lead.email || 'No email found') : maskText(lead.email || '')}</div>
        </td>
        <td class="px-6 py-4 align-middle">
            <div>
                <span>${lead.recruiter_name || 'N/A'}</span>
                <br>
                <span class="text-xs text-gray-500">${lead.recruiter_team || 'No Team'}</span>
            </div>
        </td>
        <td class="px-6 py-4 align-middle">
        <div class='flex flex-col items-start gap-2 w-full'>
            
            <!-- Pre-recruitment Progress Bar -->
            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden mt-1">
                <div class="progress-bar h-3 bg-blue-300 transition-all duration-300" style="width: ${getPreRecruitmentPercent(lead)}%"></div>
            </div>
            <div class="text-xs text-left text-gray-600 whitespace-nowrap font-semibold">Pre: ${getPreRecruitmentPercent(lead)}%</div>
            <!-- Post-recruitment Progress Bar -->
            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden mt-1">
                <div class="progress-bar h-3 bg-orange-300 transition-all duration-300" style="width: ${getPostRecruitmentPercent(lead)}%"></div>
            </div>
            <div class="text-xs text-left text-gray-600 whitespace-nowrap font-semibold">Post: ${getPostRecruitmentPercent(lead)}%</div>
            <!-- Overall Progress Bar -->
            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                <div class="progress-bar h-3 bg-green-300 transition-all duration-300" style="width: ${getProgressPercent(lead)}%"></div>
            </div>
            <div class="text-xs text-left text-gray-600 whitespace-nowrap font-semibold">Overall: ${getProgressPercent(lead)}%</div>
        </div>
        </td>
        <td class="px-6 py-4 align-middle"><small>${lead.source || 'N/A'}</small></td>
        <td class="px-6 py-4 align-middle">
            <div class="btn-group btn-group-sm flex items-center justify-center gap-2">
                ${canEditDelete ? `
                <button class="btn btn-outline-onboard text-blue-500 border-2 p-1 rounded-md border-blue-500    hover:border-blue-500 hover:bg-blue-500 hover:text-white transition-all hidden"
                onclick="showOnboardModal(${lead.id})"
                    title="Onboarding Details"
                    id="onboardBtn-${lead.id}">
                    <i class="fa fa-rocket" aria-hidden="true"></i>
                </button>
                <button class="btn btn-outline-primary text-yellow-500 border-2 p-1 rounded-md border-yellow-500 hover:border-yellow-500 hover:bg-yellow-500 hover:text-white transition-all" onclick="editLead(${lead.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                     <button class="btn btn-outline-danger text-red-500 border-2 p-1 rounded-md border-red-500 hover:border-red-500 hover:bg-red-500 hover:text-white transition-all" onclick="deleteLead(${lead.id})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
                ` : ''}
            </div>
        </td>
    `
            tbody.appendChild(row);

            // Show the onboard button if pre-recruitment progress is 100%
            const prePercent = getPreRecruitmentPercent(lead);
            if (prePercent === 100) {
                const onboardBtn = document.getElementById(`onboardBtn-${lead.id}`);
                if (onboardBtn) {
                    onboardBtn.classList.remove("hidden");
                    onboardBtn.classList.add("inline-block"); // or "block" if needed
                }
            }
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
        const team = document.getElementById("filterTeam").value.trim()

        if (status) currentFilters.status = status
        if (source) currentFilters.source = source
        if (search) currentFilters.search = search
        if (team) currentFilters.team = team

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
        document.getElementById("filterTeam").value = ""
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
                });
        }
    }


    // Modal and CRUD functions
    function showAddModal() {
        document.getElementById('modalTitle').textContent = 'Add New Recruited Agent';
        document.getElementById('recruitmentForm').reset();
        document.getElementById('leadId').value = '';
        document.getElementById('recruiterName').value = "<?php echo htmlspecialchars($user['name'] ?? ''); ?>";

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

    function showOnboardModal(id) {
        document.getElementById('onboardModal').classList.remove('hidden');
        const modalContent = document.querySelector('#onboardModal .p-0');
        modalContent.innerHTML = `
        <div class="p-6 overflow-y-auto max-h-[70vh]">
            <form id="onboardUserForm">
                <div class="flex flex-col items-center justify-center gap-2">
                    
                <div class="flex justify-center items-center overflow-visible">
                    <i class="fa-solid fa-user-astronaut text-[100px] bg-gradient-to-br from-blue-500 via-green-500 to-yellow-500 bg-clip-text text-transparent leading-[1.1] pt-2"></i>
                </div>

                    <div class="mb-4 mx-8">
                        <div>
                            <div>
                                <input type="text" id="onboard_name" name="name"
                                    class="text-center w-full focus:outline-none text-2xl font-bold" placeholder="Full Name" readonly>
                            </div>
                            <input type="text" id="onboard_username" name="username"
                                class=" w-full focus:outline-none text-center text-sm " placeholder="user.name.innersparc" readonly>
                        </div>
                        <hr class="my-3">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 bg-red-5001 p-2 pt-0 text-gray-500 text-sm">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-envelope"></i>
                                <input type="email" id="onboard_email" name="email"
                                    class="w-full whitespace-nowrap focus:outline-none" placeholder="yourname@gmail.com" readonly>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-key"></i>
                                <input type="text" value="123456789innersparc"
                                    class="w-full whitespace-nowrap focus:outline-none " placeholder="123456789innersparc" readonly>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-phone"></i>
                                <input type="text" id="onboard_phone" name="phone"
                                    class="w-full whitespace-nowrap focus:outline-none " placeholder="09123456789" readonly>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-user-tie"></i>
                                <input type="text" value="Agent"
                                    class="w-full whitespace-nowrap focus:outline-none " placeholder="Agent" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-4">
                    <small class="text-gray-500 hidden" id="onboard_username_preview"></small>
                </div>
                <div id="onboardActionSection"></div>
                <div class="text-sm text-gray-500 mb-4 text-sm text-center">
                    <p>Note: The agent will be created with the default password
                        <strong>123456789innersparc</strong>. Ensure to change the password after onboarding.
                    </p>
                </div>
            </form>
            <div id="onboardLoading" class="text-center text-gray-400">Loading agent details...</div>
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
                    document.getElementById('onboard_username').value = username;
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
                                actionSection.innerHTML = `
                        <div class="p-6 text-center border rounded-lg mb-4">
                        <i class="fa-solid fa-info text-[30px] text-yellow-500"></i>
                            <h2 class="text-xl font-mono font-bold my-4">Agent has been onboarded.</h2>
                            <p class="mb-6 text-sm font-mono opacity-75 mx-[10%]">
                                <span class="font-semibold">${lead.full_name}</span> has already been onboarded in ${lead.recruiter_team}. <br>
                                If this was done by mistake, you can choose to <span class="text-yellow-700 font-semibold">undo</span> the onboarding.
                            </p>

                            <div class="flex flex-row gap-2">
                                <button type="button" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded w-full font-semibold transition-all" onclick="undoOnboardAgent('${username}')">Unboard this Agent</button>
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
                                        const username = document.getElementById('onboard_username').value;
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
    function undoOnboardAgent(username) {
        if (!confirm('Are you sure you want to undo this onboard action?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_agent_by_username');
        fd.append('username', username);

        fetch('recruitment-api-debug.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification('Agent successfully removed.', 'success');
                    hideOnboardModal();
                    loadRecruitmentData();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            });
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
                document.getElementById("timestamp").value = lead.timestamp || "";

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
                            teamSelect.innerHTML = '<option value="">Select Team</option>';
                            teamsData.teams.forEach(team => {
                                const opt = document.createElement('option');
                                opt.value = team.id;
                                opt.textContent = team.name;
                                teamSelect.appendChild(opt);
                            });

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
                                    sourceSelect.innerHTML = '<option value="">Select a member</option>';
                                }
                            };

                            // Preselect team if exists and load members
                            if (lead.recruiter_team_id) {
                                teamSelect.value = lead.recruiter_team_id;
                                loadTeamMembers(lead.recruiter_team_id, lead.source);
                            }

                            // Fallback: set recruiter directly if not found in team
                            if (lead.recruiter_name) recruiterInput.value = lead.recruiter_name;
                            if (lead.recruiter_id) recruiterIdInput.value = lead.recruiter_id;
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
    // --- Team Status Summary Section ---
    let teamStatusGrouping = 'year';
    let teamStatusFilters = {
        year: new Date().getFullYear()
    };

    function setGrouping(grouping) {
        teamStatusGrouping = grouping;
        // Toggle button styles
        document.querySelectorAll('.grouping-toggle').forEach(btn => btn.classList.remove('bg-blue-600', 'text-white'));
        document.getElementById('grouping-' + grouping).classList.add('bg-blue-600', 'text-white');
        // Show/hide filters
        document.getElementById('filterYear').classList.remove('hidden');
        document.getElementById('filterMonth').classList.toggle('hidden', grouping !== 'month');
        document.getElementById('filterQuarter').classList.toggle('hidden', grouping !== 'quarter');
        // Reset filters
        if (grouping === 'year') {
            teamStatusFilters = {
                year: document.getElementById('filterYear').value
            };
        } else if (grouping === 'month') {
            teamStatusFilters = {
                year: document.getElementById('filterYear').value,
                month: document.getElementById('filterMonth').value
            };
        } else if (grouping === 'quarter') {
            teamStatusFilters = {
                year: document.getElementById('filterYear').value,
                quarter: document.getElementById('filterQuarter').value
            };
        }
        updateTeamStatusTitle();
        refreshTeamStatus();
    }

    function populateYearMonthFilters() {
        // Populate year dropdown (last 5 years)
        const yearSel = document.getElementById('filterYear');
        const now = new Date();
        const thisYear = now.getFullYear();
        yearSel.innerHTML = '';
        for (let y = thisYear; y >= thisYear - 4; y--) {
            yearSel.innerHTML += `<option value="${y}">${y}</option>`;
        }
        // Populate month dropdown
        const monthSel = document.getElementById('filterMonth');
        monthSel.innerHTML = '<option value="">All Months</option>';
        ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November",
            "December"
        ].forEach((m, i) => {
            monthSel.innerHTML += `<option value="${i + 1}">${m}</option>`;
        });
    }

    function updateTeamStatusTitle() {
        const titleEl = document.getElementById('teamStatusTitle');
        let title = ' Recruited Agents';
        const year = document.getElementById('filterYear').value;
        if (teamStatusGrouping === 'year') {
            title = `Recruited Agents in <span class='text-blue-700'>${year}</span> `;
        } else if (teamStatusGrouping === 'month') {
            const monthVal = document.getElementById('filterMonth').value;
            const monthName = monthVal ? ["January", "February", "March", "April", "May", "June", "July", "August",
                "September", "October", "November", "December"
            ][parseInt(monthVal, 10) - 1] : '';
            title =
                `Recruited Agents${monthName ? ' in <span class="text-blue-700">' + monthName + '</span>' : ''} <span class='text-blue-700'>${year}</span>`;
        } else if (teamStatusGrouping === 'quarter') {
            const quarterVal = document.getElementById('filterQuarter').value;
            title =
                `Recruited Agents in <span class='text-blue-700'>Quarter ${quarterVal}</span>of <span class='text-blue-700'>${year}</span>`;
        }
        titleEl.innerHTML = title;
    }

    document.addEventListener('DOMContentLoaded', function() {
        populateYearMonthFilters();
        setGrouping('year');
        document.getElementById('filterYear').addEventListener('change', function() {
            teamStatusFilters.year = this.value;
            if (teamStatusGrouping === 'month') {
                teamStatusFilters.month = document.getElementById('filterMonth').value;
            } else if (teamStatusGrouping === 'quarter') {
                teamStatusFilters.quarter = document.getElementById('filterQuarter').value;
            }
            updateTeamStatusTitle();
            refreshTeamStatus();
        });
        document.getElementById('filterMonth').addEventListener('change', function() {
            teamStatusFilters.month = this.value;
            updateTeamStatusTitle();
            refreshTeamStatus();
        });
        document.getElementById('filterQuarter').addEventListener('change', function() {
            teamStatusFilters.quarter = this.value;
            updateTeamStatusTitle();
            refreshTeamStatus();
        });
        updateTeamStatusTitle();
    });

    function refreshTeamStatus() {
        // Show loading in tables
        document.getElementById('activeAgentsTableBody').innerHTML =
            '<tr><td colspan="2" class="text-center text-gray-400 py-4">Loading...</td></tr>';
        document.getElementById('inactiveAgentsTableBody').innerHTML =
            '<tr><td colspan="2" class="text-center text-gray-400 py-4">Loading...</td></tr>';
        // Fetch data
        const formData = new FormData();
        formData.append('action', 'get_team_status_summary');
        formData.append('filters', JSON.stringify(teamStatusFilters));
        fetch('recruitment-api-debug.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Failed to load');
                renderTeamStatusTables(data.data);
                renderTeamStatusCharts(data.data);
            })
            .catch(err => {
                document.getElementById('activeAgentsTableBody').innerHTML =
                    `<tr><td colspan="2" class="text-center text-red-400 py-4">Error: ${err.message}</td></tr>`;
                document.getElementById('inactiveAgentsTableBody').innerHTML =
                    `<tr><td colspan="2" class="text-center text-red-400 py-4">Error: ${err.message}</td></tr>`;
            });
    }

    function renderTeamStatusTables(data) {
        // Group by status
        const active = {},
            inactive = {};
        data.forEach(row => {
            if (row.status === 'Active') {
                active[row.team_name || 'No Team'] = row.count;
            } else if (row.status === 'Inactive') {
                inactive[row.team_name || 'No Team'] = row.count;
            }
        });
        // Helper to sort teams alphabetically, but keep 'OJT (Intern)' at the bottom
        function getSortedTeams(obj) {
            let teams = Object.keys(obj);
            const ojtIndex = teams.indexOf('OJT (Intern)');
            let ojtTeam = null;
            if (ojtIndex !== -1) {
                ojtTeam = teams.splice(ojtIndex, 1)[0];
            }
            teams = teams.sort((a, b) => a.localeCompare(b));
            if (ojtTeam) teams.push(ojtTeam);
            return teams;
        }
        // Render Active table
        const activeBody = document.getElementById('activeAgentsTableBody');
        activeBody.innerHTML = '';
        const sortedActiveTeams = getSortedTeams(active);
        let activeTotal = 0;
        if (sortedActiveTeams.length === 0) {
            activeBody.innerHTML = '<tr><td colspan="2" class="text-center text-gray-400 py-4">No data</td></tr>';
        } else {
            sortedActiveTeams.forEach(team => {
                if (team === 'OJT (Intern)') return; // Skip OJT team in active table
                activeBody.innerHTML +=
                    `<tr class=" border-b border-green-700"><td class="px-4 py-2 text-center">${team}</td><td class="px-4 py-2 text-green-700 font-bold text-center">${active[team]}</td></tr>`;
                activeTotal += active[team];
            });
            // Add total row
            activeBody.innerHTML +=
                `<tr class="">
            <td class="px-4 py-2 font-bold rounded-lg text-center bg-none text-green-600 border border-green-700  text-sm uppercase tracking-wider">Total</td>
            <td class="px-4 py-2 font-bold rounded-lg text-center bg-none text-green-600 border border-green-700  text-sm uppercase tracking-wider">${activeTotal}</td>
        </tr>`;
        }
        // Render Inactive table
        const inactiveBody = document.getElementById('inactiveAgentsTableBody');
        inactiveBody.innerHTML = '';
        const sortedInactiveTeams = getSortedTeams(inactive);
        let inactiveTotal = 0;
        if (sortedInactiveTeams.length === 0) {
            inactiveBody.innerHTML = '<tr><td colspan="2" class="text-center text-gray-400 py-4">No data</td></tr>';
        } else {
            sortedInactiveTeams.forEach(team => {
                if (team === 'OJT (Intern)') return; // Skip OJT team in inactive table
                inactiveBody.innerHTML +=
                    `<tr><td class="px-4 py-2 text-center">${team}</td><td class="px-4 py-2 text-red-700 font-bold text-center">${inactive[team]}</td></tr>`;
                inactiveTotal += inactive[team];
            });
            // Add total row
            inactiveBody.innerHTML +=
                `<tr>
            <td class="px-4 py-2 font-bold rounded-lg text-center bg-none text-red-600 border border-red-700  text-sm uppercase tracking-wider">Total</td>
            <td class="px-4 py-2 font-bold rounded-lg text-center bg-none text-red-600 border border-red-700  text-sm uppercase tracking-wider">${inactiveTotal}</td>
        </tr>`;
        }
    }

    function renderTeamStatusCharts(data) {
        // Prepare data for charts
        let teams = [...new Set(data.map(row => row.team_name || 'No Team'))];

        // Sort alphabetically and move 'OJT (Intern)' to the end
        const ojtIndex = teams.indexOf('OJT (Intern)');
        let ojtTeam = null;
        if (ojtIndex !== -1) {
            ojtTeam = teams.splice(ojtIndex, 1)[0];
        }
        teams = teams.sort((a, b) => a.localeCompare(b));
        if (ojtTeam) teams.push(ojtTeam); // OJT is still in the last index

        // Exclude OJT (Intern) from chart rendering arrays
        teams = teams.filter(team => team !== 'OJT (Intern)');

        // Prepare counts
        const activeCounts = teams.map(team => {
            if (team === 'OJT (Intern)') return 0; // Hide it by setting to 0
            const found = data.find(row => (row.team_name || 'No Team') === team && row.status === 'Active');
            return found ? found.count : 0;
        });

        const inactiveCounts = teams.map(team => {
            if (team === 'OJT (Intern)') return 0; // Hide it by setting to 0
            const found = data.find(row => (row.team_name || 'No Team') === team && row.status === 'Inactive');
            return found ? found.count : 0;
        });

        // Destroy previous charts if any
        if (window.activeAgentsChartObj) window.activeAgentsChartObj.destroy();
        if (window.inactiveAgentsChartObj) window.inactiveAgentsChartObj.destroy();

        // Active chart
        const ctxA = document.getElementById('activeAgentsChart').getContext('2d');
        window.activeAgentsChartObj = new Chart(ctxA, {
            type: 'bar',
            data: {
                labels: teams,
                datasets: [{
                    label: 'Active Agents',
                    data: activeCounts,
                    backgroundColor: teams.map(team => team === 'OJT (Intern)' ? 'rgba(0,0,0,0)' :
                        '#22c55e')
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            callback: function(value, index, ticks) {
                                const label = this.getLabelForValue(value);
                                return label === 'OJT (Intern)' ? '' : label;
                            }
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Inactive chart
        const ctxI = document.getElementById('inactiveAgentsChart').getContext('2d');
        window.inactiveAgentsChartObj = new Chart(ctxI, {
            type: 'bar',
            data: {
                labels: teams,
                datasets: [{
                    label: 'Inactive Agents',
                    data: inactiveCounts,
                    backgroundColor: teams.map(team => team === 'OJT (Intern)' ? 'rgba(0,0,0,0)' :
                        '#ef4444')
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            callback: function(value, index, ticks) {
                                const label = this.getLabelForValue(value);
                                return label === 'OJT (Intern)' ? '' : label;
                            }
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    </script>
    <script src="assets/js/script.js"></script>
</body>

</html>