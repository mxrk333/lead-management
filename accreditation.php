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

require_once 'accreditation/core/dbConfig.php';
require_once 'accreditation/core/models.php';

$sheetId = '1jAxVlt4_tM1s2db22RbBobyxi6Wqla2kHb6UTRZDEJA';
$gid = '1410707098';
$url = "https://docs.google.com/spreadsheets/d/$sheetId/gviz/tq?tqx=out:csv&gid=$gid";

// USE THIS TO RESET INDEX
// TRUNCATE TABLE manual_data;

// Fetch all data from database
$stmt = $pdo->query("SELECT * FROM manual_data ORDER BY datetime ASC");
$dbResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monthlyData = getTeamMonthlyData($pdo);

// Process data for grouped view by year and month
$data = [];

foreach ($dbResults as $row) {
    $datetimeStr = $row['datetime'];
    $team = trim($row['team']);
    $toggle = trim($row['toggle']);

    if (!$datetimeStr || !$team)
        continue;

    $date = DateTime::createFromFormat('Y-m-d H:i:s', $datetimeStr);
    if (!$date)
        continue;

    $year = $date->format('Y');
    $month = $date->format('F');
    $quarter = getMondayWeekOfMonth($date);

    if (!isset($data[$year][$month][$team])) {
        $data[$year][$month][$team] = [
            'Week 1' => ['1' => 0, '0' => 0, 'blank' => 0, '2' => 0],
            'Week 2' => ['1' => 0, '0' => 0, 'blank' => 0, '2' => 0],
            'Week 3' => ['1' => 0, '0' => 0, 'blank' => 0, '2' => 0],
            'Week 4' => ['1' => 0, '0' => 0, 'blank' => 0, '2' => 0],
            'Week 5' => ['1' => 0, '0' => 0, 'blank' => 0, '2' => 0],
            'Week 6' => ['1' => 0, '0' => 0, 'blank' => 0, '2' => 0],
        ];
    }

    $key = ($toggle === '1' || $toggle === '0' || $toggle === '2') ? $toggle : 'blank';
    $data[$year][$month][$team][$quarter][$key]++;
}

$svg = [
    'check' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                                    class="size-6 text-green-500">
                                                    <path fill-rule="evenodd"
                                                        d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                                                        clip-rule="evenodd" />
                                                </svg>',
    'x_mark' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                                    class="size-6 text-red-500">
                                                    <path fill-rule="evenodd"
                                                        d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z"
                                                        clip-rule="evenodd" />',
    'signal_lost' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 text-yellow-500">
  <path fill-rule="evenodd" d="M2.47 2.47a.75.75 0 0 1 1.06 0l8.407 8.407a1.125 1.125 0 0 1 1.186 1.186l1.462 1.461a3.001 3.001 0 0 0-.464-3.645.75.75 0 1 1 1.061-1.061 4.501 4.501 0 0 1 .486 5.79l1.072 1.072a6.001 6.001 0 0 0-.497-7.923.75.75 0 0 1 1.06-1.06 7.501 7.501 0 0 1 .505 10.05l1.064 1.065a9 9 0 0 0-.508-12.176.75.75 0 0 1 1.06-1.06c3.923 3.922 4.093 10.175.512 14.3l1.594 1.594a.75.75 0 1 1-1.06 1.06l-2.106-2.105-2.121-2.122h-.001l-4.705-4.706a.747.747 0 0 1-.127-.126L2.47 3.53a.75.75 0 0 1 0-1.061Zm1.189 4.422a.75.75 0 0 1 .326 1.01 9.004 9.004 0 0 0 1.651 10.462.75.75 0 1 1-1.06 1.06C1.27 16.12.63 11.165 2.648 7.219a.75.75 0 0 1 1.01-.326ZM5.84 9.134a.75.75 0 0 1 .472.95 6 6 0 0 0 1.444 6.159.75.75 0 0 1-1.06 1.06A7.5 7.5 0 0 1 4.89 9.606a.75.75 0 0 1 .95-.472Zm2.341 2.653a.75.75 0 0 1 .848.638c.088.62.37 1.218.849 1.696a.75.75 0 0 1-1.061 1.061 4.483 4.483 0 0 1-1.273-2.546.75.75 0 0 1 .637-.848Z" clip-rule="evenodd" />
</svg>
',
    'onboarding' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 text-blue-500">
  <path fill-rule="evenodd" d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5ZM18 1.5a.75.75 0 0 1 .728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 0 1 0 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 0 1-1.456 0l-.258-1.036a2.625 2.625 0 0 0-1.91-1.91l-1.036-.258a.75.75 0 0 1 0-1.456l1.036-.258a2.625 2.625 0 0 0 1.91-1.91l.258-1.036A.75.75 0 0 1 18 1.5ZM16.5 15a.75.75 0 0 1 .712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 0 1 0 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 0 1-1.422 0l-.395-1.183a1.5 1.5 0 0 0-.948-.948l-1.183-.395a.75.75 0 0 1 0-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0 1 16.5 15Z" clip-rule="evenodd" />
</svg>
'
];

$default_blue_number = '800';

$dataToggleTeamMonth = getToggleDataByTeamAndMonth($pdo);
$dataTeamPerMonth = getToggleDataByTeamPerMonth($pdo);

$months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec'
];

// Optional: labels for toggles
$toggleLabels = [
    '1' => '<div class="flex pt-2 items-center gap-2 md:gap-1 text-green-600 font-semibold md:text-lg text-[14px]">'
        . $svg['check'] . '<span>Accredited Agents <span class="text-sm opacity-50 text-gray-700">(Active
                                agents)</span></span></div>',

    '0' => '<div class="flex pt-2 items-center gap-2 md:gap-1 text-red-600 font-semibold md:text-lg text-[14px]">'
        . $svg['x_mark'] . '<span>Cancelled / Unresponsive <span class="text-sm opacity-50 text-gray-700">(No response or
                                cancelled applications)</span></span></div>',

    '' => '<div class="flex pt-2 items-center gap-2 md:gap-1 text-yellow-600 font-semibold md:text-lg text-[14px]">'
        . $svg['signal_lost'] . '<span>Former Agents <span class="text-sm opacity-50 text-gray-700">(Resigned or
                                withdrawn)</span></span></div>',

    '2' => '<div class="flex pt-2 items-center gap-2 md:gap-1 text-blue-500 font-semibold md:text-lg text-[14px]">'
        . $svg['onboarding'] . '<span>Onboarding <span class="text-sm opacity-50 text-gray-700">(New agents in onboarding)</span></span></div>',
];

$monthsIndex = [
    1 => "January",
    2 => "February",
    3 => "March",
    4 => "April",
    5 => "May",
    6 => "June",
    7 => "July",
    8 => "August",
    9 => "September",
    10 => "October",
    11 => "November",
    12 => "December"
];

$teamColors = [
    'Blazing SPARCS' => 'rgba(54, 162, 235, 0.6)',
    'Feisty Heroine' => 'rgba(75, 192, 192, 0.6)',
    'Fiery Achievers' => 'rgba(255, 99, 132, 0.6)',
    'Flameborn Champions' => 'rgba(255, 206, 86, 0.6)',
    'Shining Phoenix' => 'rgba(255, 206, 86, 0.6)',
    'default' => 'rgba(201, 203, 207, 0.6)'
];

try {
    $pdo = getPDO();
    $rows = getFilteredManualData($pdo); // Get all data initially
    $teamOptions = getAllTeams($pdo);
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

$staticOptions = [
    "Blazing SPARCS",
    "Feisty Heroine",
    "Fiery Achievers",
    "Flameborn Champions",
    "Shining Phoenix"
];
$allTeamOptions = array_unique(array_merge($teamOptions, $staticOptions));

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
    <!-- <script src="js/chart.js"></script> -->
    <script>
    // Output all chart data and team colors as JS objects
    const chartData = <?= json_encode($monthlyData) ?>;
    const teamColors = <?= json_encode($teamColors) ?>;
    const initializedCharts = {};

    function initChart(monthKey) {
        const chartId = 'chart_' + monthKey.replace(/-/g, '_');
        if (initializedCharts[chartId]) return; // Already initialized

        const ctx = document.getElementById(chartId);
        if (!ctx) return;

        const teams = chartData[monthKey];
        if (!teams) return;

        const labels = Object.keys(teams);
        const data = Object.values(teams);

        // Prepare colors per team
        const backgroundColors = [];
        const borderColors = [];
        labels.forEach(teamName => {
            const bg = teamColors[teamName] || teamColors['default'];
            const bd = bg.replace('0.6', '1');
            backgroundColors.push(bg);
            borderColors.push(bd);
        });

        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: monthKey,
                    data: data,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true, // ✅ Enables responsiveness
                maintainAspectRatio: false, // ✅ Allows canvas to fill parent height
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        initializedCharts[chartId] = true;
    }

    function filterByYear(year) {
        document.querySelectorAll('.year-group').forEach(el => {
            el.classList.add('hidden');
        });
        const yearGroup = document.getElementById(`year-${year}`);
        yearGroup.classList.remove('hidden');

        // Reset month filter when year changes
        document.getElementById('month-filter').value = 'all';
        showAllMonths();

        // Initialize charts for all visible month sections in this year
        yearGroup.querySelectorAll('.month-section').forEach(section => {
            const monthKey = section.getAttribute('data-monthkey');
            if (monthKey) initChart(monthKey);
        });
    }

    function filterByMonth(month) {
        const yearGroups = document.querySelectorAll('.year-group:not(.hidden)');
        yearGroups.forEach(yearGroup => {
            const monthSections = yearGroup.querySelectorAll('.month-section');
            monthSections.forEach(section => {
                if (month === 'all' || section.dataset.month === month) {
                    section.classList.remove('hidden');
                    // Initialize chart for this month section
                    const monthKey = section.getAttribute('data-monthkey');
                    if (monthKey) initChart(monthKey);
                } else {
                    section.classList.add('hidden');
                }
            });
        });
    }

    function showAllMonths() {
        const yearGroups = document.querySelectorAll('.year-group:not(.hidden)');
        yearGroups.forEach(yearGroup => {
            const monthSections = yearGroup.querySelectorAll('.month-section');
            monthSections.forEach(section => {
                section.classList.remove('hidden');
                // Initialize chart for this month section
                const monthKey = section.getAttribute('data-monthkey');
                if (monthKey) initChart(monthKey);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts for all visible month sections on page load
        document.querySelectorAll('.year-group:not(.hidden) .month-section').forEach(section => {
            const monthKey = section.getAttribute('data-monthkey');
            if (monthKey) initChart(monthKey);
        });
    });
    </script>
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

            <div class="bg-gray-100 text-gray-800 py-6 sm:px-[15%] md:px-[4%] px-[5%]">

                <!-- ENCODING form -->
                <section class="bg-white rounded-2xl shadow p-6 mb-6 mx-[15%] min-w-[25%]">
                    <div class="flex justify-center items-center bg-blue-1001 pt-4">

                        <div class="bg-red-3001 w-full px-10 h-fit flex justify-center items-center">
                            <form method="post" action="accreditation/core/handleForms.php" class="mb-6">

                                <!-- FIRST DIV -->
                                <div class="text-center">
                                    <h1 class="font-bold text-[32px] text-blue-<?= $default_blue_number; ?>">Agent
                                        Accreditation</h1>
                                </div>

                                <!-- LINE -->
                                <hr class="h-px my-4 bg-gray-200 border-0 dark:bg-blue-<?= $default_blue_number; ?>">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                    <!-- SECOND DIV -->

                                    <?php
                                    $labelDesign = "text-blue-" . $default_blue_number . " font-semibold";
                                    $sharedInputStyles = "mt-1 w-full px-3 py-2 rounded-md border border-blue-$default_blue_number focus:outline-none focus:border-blue-$default_blue_number bg-transparent appearance-none cursor-pointer";
                                    $importantIcon = "text-red-500";
                                    ?>

                                    <label for="name">
                                        <span class="<?= $labelDesign ?>">Agent Name <span
                                                class="<?= $importantIcon; ?>">*</span></span>
                                        <input type="text" name="name" placeholder="Enter agent's full name"
                                            class="<?= $sharedInputStyles ?> cursor-text" required>
                                    </label>

                                    <label for="team">
                                        <span class="<?= $labelDesign ?>">Team <span
                                                class="<?= $importantIcon; ?>">*</span></span>
                                        <select name="team" class="<?= $sharedInputStyles ?>" required>
                                            <option value="Blazing SPARCS">Blazing SPARCS</option>
                                            <option value="Feisty Heroine">Feisty Heroine</option>
                                            <option value="Fiery Achievers">Fiery Achievers</option>
                                            <option value="Flameborn Champions">Flameborn Champions</option>
                                            <option value="Shining Phoenix">Shining Phoenix</option>
                                        </select>
                                    </label>

                                    <label for="datetime">
                                        <span class="<?= $labelDesign ?>">Date of Entry</span>
                                        <input type="datetime-local" name="datetime" class="<?= $sharedInputStyles ?>">
                                    </label>

                                    <label for="toggle">
                                        <span class="<?= $labelDesign ?>">Status <span
                                                class="<?= $importantIcon; ?>">*</span></span>
                                        <select name="toggle" class="<?= $sharedInputStyles ?>" required>
                                            <option value="1">Accredited</option>
                                            <option value="0">No Response</option>
                                            <option class="hidden" value="null">Left</option>
                                            <option value="2">Onboarding</option>
                                        </select>
                                    </label>



                                </div>

                                <?php

                                $hover_blue_number = $default_blue_number + 100;

                                ?>

                                <!-- FOURTH DIV -->
                                <button type="submit" name="insertData"
                                    class="py-2 mt-4 bg-blue-<?= $default_blue_number; ?> w-full text-white rounded-lg hover:bg-blue-<?= $hover_blue_number; ?> hover:shadow-mg font-semibold transition-all duration-300">Add
                                    this
                                    Entry
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <div class="flex flex-wrap items-center text-gray-700 gap-4 mb-4 py-3 hidden">
                    <span class="text-lg font-bold">Team</span>
                    <?php
                    $printedTeams = [];
                    foreach ($data as $year => $months):
                        foreach ($months as $month => $teams):
                            foreach ($teams as $team => $quarters):
                                if (in_array($team, $printedTeams))
                                    continue;
                                $printedTeams[] = $team;
                                ?>
                    <span
                        class="px-3 py-1 bg-blue-100 text-blue-800 rounded-lg shadow-sm hover:-translate-y-1 transition-all ease-in-out"><?= htmlspecialchars($team) ?></span>
                    <?php
                            endforeach;
                        endforeach;
                    endforeach;
                    ?>
                </div>



                <div class="bg-white rounded-2xl shadow pt-2 py-6 px-4 mb-6">

                    <h3 class="text-xl font-semibold my-2">Users</h3>
                    <hr>

                    <div class="filters bg-blue-1001 flex flex-wrap gap-2 py-4">

                        <!-- tailwind css for filters -->
                        <?php
                        $filterClass = "bg-gray-100 rounded-xl pl-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-800 w-full sm:w-auto flex-1 min-w-[150px]";
                        ?>

                        <input type="text" id="search-name" placeholder="Search by name" class="<?= $filterClass ?>" />

                        <select id="filter-team" class="<?= $filterClass ?>">
                            <option value="">All Teams</option>
                            <?php foreach ($teamOptions as $team): ?>
                            <option value="<?= htmlspecialchars($team) ?>"><?= htmlspecialchars($team) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select id="filter-toggle" class="<?= $filterClass ?>">
                            <option value="">All Status</option>
                            <option value="1">Finished</option>
                            <option value="0">No Response</option>
                            <option class="hidden" value="null">Left</option>
                            <option value="2">Onboarding</option>
                        </select>

                        <select id="filter-month" class="<?= $filterClass ?>">
                            <option value="">All Months</option>
                            <?php foreach ($monthsIndex as $num => $name): ?>
                            <option value="<?= str_pad($num, 2, '0', STR_PAD_LEFT) ?>"><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>

                    </div>


                    <!-- Scrollable area with fixed height -->
                    <div class="overflow-y-auto overflow-x-auto" style="max-height: 300px;">
                        <table class="w-full text-left border-separate border-spacing-2">
                            <thead class="sticky top-0 bg-white text-center mx-2">
                                <tr class="bg-white">
                                    <th class="rounded-lg p-2 m-2 bg-blue-<?= $default_blue_number; ?> text-white">
                                        Entry DateTime
                                    </th>
                                    <th class="rounded-lg p-2 m-2 bg-blue-<?= $default_blue_number; ?> text-white">
                                        Name
                                    </th>
                                    <th class="rounded-lg p-2 m-2 bg-blue-<?= $default_blue_number; ?> text-white">
                                        Team
                                    </th>
                                    <th class="rounded-lg p-2 m-2 bg-blue-<?= $default_blue_number; ?> text-white">
                                        Status
                                    </th>
                                    <th class="rounded-lg p-2 m-2 bg-red-600 text-white">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="user-table-body" class="rounded-lg">
                                <?php foreach ($rows as $row): ?>
                                <tr data-id="<?= $row['id'] ?>" data-name="<?= $row['name'] ?>">
                                    <!-- Static Date -->

                                    <td class="p-0 max-w-[160px]">
                                        <div
                                            class="bg-gray-100 rounded-lg px-3 py-2 text-sm truncate whitespace-nowrap">
                                            <!-- Changes datetime format to readable view -->
                                            <?= date('F j, Y \a\t g:i A', strtotime($row['datetime'])) ?>
                                        </div>
                                    </td>


                                    <!-- Static Name -->
                                    <td class="p-0 max-w-[160px]">
                                        <div
                                            class="bg-gray-100 rounded-lg px-3 py-2 text-sm truncate whitespace-nowrap">
                                            <?= htmlspecialchars($row['name']) ?>
                                        </div>
                                    </td>

                                    <!-- Team Select -->
                                    <td class="p-0">
                                        <select
                                            class="team-select w-full block bg-gray-100 rounded-lg px-3 py-2 focus:outline-none">
                                            <?php foreach ($allTeamOptions as $teamOption): ?>
                                            <option value="<?= htmlspecialchars($teamOption) ?>"
                                                <?= $row['team'] === $teamOption ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($teamOption) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>

                                    </td>


                                    <!-- Toggle Select -->
                                    <td class="p-0">
                                        <select
                                            class="toggle-select w-full block bg-gray-100 rounded-lg px-3 py-2 focus:outline-none">
                                            <option value="1" <?= $row['toggle'] === '1' ? 'selected' : '' ?>>Accredited
                                            </option>
                                            <option value="0" <?= $row['toggle'] === '0' ? 'selected' : '' ?>>No
                                                Response</option>
                                            <option class="hidden" value=""
                                                <?= $row['toggle'] === '' ? 'selected' : '' ?>>Left
                                            </option>
                                            <option value="2" <?= $row['toggle'] === '2' ? 'selected' : '' ?>>Onboarding
                                            </option>
                                        </select>
                                    </td>

                                    <!-- Delete Button -->
                                    <td class="p-0">
                                        <button
                                            class="delete-btn w-full block bg-red-400 text-white rounded-lg px-3 py-2 hover:bg-red-600 transition flex items-center justify-center"
                                            title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                fill="currentColor" class="size-4 text-white">
                                                <path fill-rule="evenodd"
                                                    d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z"
                                                    clip-rule="evenodd" />
                                            </svg>

                                        </button>
                                    </td>

                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>





                <label class="block mb-4">
                    <div class="flex flex-col md:flex-row justify-between items-center gap-1 mb-2 text-md">
                        <div class="flex gap-1 items-center">
                            <span class="text-gray-700 font-bold">Filter by
                                <select onchange="filterByYear(this.value)"
                                    class="font-bold mb-4 text-center text-blue-<?= $default_blue_number; ?> bg-transparent appearance-none1">
                                    <?php foreach (array_keys($data) as $year): ?>
                                    <option value="<?= $year ?>"><?= $year ?></option>
                                    <?php endforeach; ?>
                                </select>
                                and
                                <select id="month-filter" onchange="filterByMonth(this.value)"
                                    class="font-bold mb-4 text-center text-blue-<?= $default_blue_number; ?> bg-transparent appearance-none1">
                                    <option value="all">All Months</option>
                                    <?php foreach ($monthsIndex as $num => $name): ?>
                                    <option value="<?= $name ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </span>

                            <!-- <span class="text-gray-700 font-bold">
                </span> -->
                        </div>

                        <div>
                            <form method="post" action="accreditation/core/sync_sheets.php">
                                <button type="submit"
                                    class="py-2 px-4 border border-blue-<?= $default_blue_number; ?> text-blue-<?= $default_blue_number; ?> hover:bg-blue-<?= $default_blue_number; ?> w-full hover:text-white rounded-lg hover:bg-blue-<?= $default_blue_number; ?> hover:shadow-mg transition-all ease-in-out hidden1">Re-Sync
                                    from Google
                                    Sheet</button>
                            </form>
                        </div>
                    </div>
                </label>

                <?php foreach ($data as $year => $months): ?>
                <div id="year-<?= $year ?>"
                    class="year-group <?= $year !== array_key_first($data) ? 'hidden' : '' ?> text-gray-700">

                    <!-- display year -->
                    <!-- <h2 class="text-lg font-bold text-center text-blue-<?= $default_blue_number; ?>">Year</h2> -->

                    <!-- DISPLAY THE YEAR -->
                    <div class="grid grid-cols-[1fr_auto_1fr] place-items-center">
                        <hr class="w-full border-blue-<?= $default_blue_number; ?>">
                        <h2
                            class="text-[42px] px-4 font-bold mt-4 mb-6 text-center text-blue-<?= $default_blue_number; ?>">
                            <?= $year ?>
                        </h2>
                        <hr class="w-full border-blue-<?= $default_blue_number; ?>">
                    </div>

                    <div class="bg-white rounded-2xl shadow pt-2 pb-1 px-4 mb-6 ">
                        <h3 class="text-xl font-semibold my-2">Legend</h3>
                        <hr>
                        <div class="flex flex-col justify-center items-center gap-4 my-4 md:flex-row sm:gap-4">
                            <div class="flex flex-col justify-center items-center font-semibold border rounded-xl p-2 py-4 text-center gap-1"
                                title="Accredited Agents">
                                <?= $svg['check'] ?>
                                <span class="ml-2">Accredited Agents<br><span class="text-sm opacity-50">(Active
                                        agents)</span></span>
                            </div>
                            <div class="flex flex-col justify-center items-center font-semibold border rounded-xl p-2 py-4 text-center gap-1"
                                title="Unresponsive">
                                <?= $svg['x_mark'] ?>
                                <span class="ml-2">Unresponsive<br><span class="text-sm opacity-50">(No
                                        response from agents)</span></span>
                            </div>
                            <div class="flex flex-col justify-center items-center font-semibold border rounded-xl p-2 py-4 text-center gap-1 hidden"
                                title="Former Agents">
                                <?php echo str_replace('bg-red-500', 'text-gray-500', $svg['signal_lost']);
                                    ?>
                                <span class="ml-2">Former Agents <br><span class="text-sm opacity-50">(Resigned
                                        or
                                        withdrawn)</span></span>
                            </div>
                            <div class="flex flex-col justify-center items-center font-semibold border rounded-xl p-2 py-4 text-center gap-1"
                                title="Onboarding">
                                <?= $svg['onboarding'] ?>
                                <span class="ml-2">Onboarding<br><span class="text-sm opacity-50">(Agents in
                                        Progress)</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow pt-2 pb-1 px-4 mb-6">

                        <?php
                            // Get data for current year
                            $yearData = $dataTeamPerMonth[$year] ?? [];
                            foreach ($yearData as $toggle => $team_s):
                                ?>

                        <!-- DISPLAY THE MARK LABELS (FROM LEGEND) -->
                        <div class="mb-2">
                            <div class="flex flex-row justify-between items-end">

                                <h2 class="text-xl font-bold">
                                    <?= $toggleLabels[$toggle] ?? 'Unknown!' ?>
                                </h2>
                                <h2 class="text-2xl font-semibold text-gray-700">
                                    <?= $year ?>
                                </h2>
                            </div>

                            <hr class="h-px my-4 mt-2">
                        </div>

                        <!-- 3-column grid -->
                        <div class="grid grid-cols-[auto,3fr,auto] gap-4 mb-4 hidden md:grid">
                            <!-- Teams per toggle -->
                            <div class="">
                                <div
                                    class="bg-white border rounded-xl p-2 px-4 text-center flex flex-col gap-4 overflow-x-auto">
                                    <div class="font-bold">Teams</div>
                                    <?php foreach (array_keys($team_s) as $team): ?>
                                    <div class=""><?= htmlspecialchars($team) ?></div>
                                    <?php endforeach; ?>
                                </div>

                                <div
                                    class="font-bold border rounded-xl p-2 mt-4 text-center bg-white flex flex-col gap-4">
                                    Total
                                </div>
                            </div>

                            <!-- Month grid -->
                            <div class="w-full bg-white overflow-x-auto text-gray-700 hide-scrollbar rounded-xl">
                                <div class="flex gap-4 min-w-max">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <div class="w-[130px]">
                                        <div
                                            class="bg-gray-50 border rounded-xl p-2 text-center flex flex-col justify-evenly items-center gap-4">
                                            <div class="font-bold"><?= $monthsIndex[$m] ?></div>
                                            <?php
                                                        $monthTotal = 0;
                                                        foreach ($team_s as $team => $count_s):
                                                            $count = $count_s[$m] ?? 0;
                                                            $monthTotal += $count;
                                                            ?>
                                            <p class=""><?= $count ?></p>
                                            <?php endforeach; ?>
                                        </div>

                                        <div
                                            class="font-semibold border rounded-xl p-2 mt-4 text-center bg-white flex flex-col gap-4">
                                            <?= $monthTotal ?>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- Grand total -->
                            <div class="text-white">
                                <div
                                    class="bg-blue-<?= $default_blue_number; ?> border rounded-xl p-2 px-4 text-center flex flex-col gap-4 overflow-x-auto">
                                    <div class="font-bold">Annual Total</div>
                                    <?php foreach ($team_s as $team => $count_s): ?>
                                    <div class="font-semibold"><?= array_sum($count_s) ?></div>
                                    <?php endforeach; ?>
                                </div>

                                <div
                                    class="font-bold border rounded-xl p-2 mt-4 text-center bg-blue-<?= $default_blue_number; ?> flex flex-col gap-4">
                                    <?= array_sum(array_map('array_sum', $team_s)) ?>
                                </div>
                            </div>
                        </div>

                        <?php endforeach; ?>

                    </div>

                    <?php foreach ($months as $month => $teams): ?>
                    <?php
                            $monthNumber = array_search($month, $monthsIndex);
                            $currentMonthKey = sprintf('%04d-%02d', $year, $monthNumber);
                            ?>
                    <div class="month-section" data-month="<?= htmlspecialchars($month) ?>"
                        data-monthkey="<?= $currentMonthKey ?>">

                        <div class="grid grid-cols-[1fr_auto_1fr] place-items-center">
                            <hr class="w-full border-blue-<?= $default_blue_number; ?>">
                            <h2
                                class="text-[24px] md:text-[32px] px-4 font-bold mt-4 mb-6 text-center text-blue-<?= $default_blue_number; ?>">
                                <?= htmlspecialchars($month) ?>
                            </h2>
                            <hr class="w-full border-blue-<?= $default_blue_number; ?>">
                        </div>

                        <?php foreach ($teams as $team => $quarters): ?>
                        <div class="bg-white rounded-2xl shadow pt-2 pb-1 px-4 mb-6 ">
                            <!-- display team -->
                            <h3
                                class="text-xl md:text-2xl font-semibold my-2 flex justify-between items-center md:flex-row flex-col">
                                <p class="text-blue-<?= $default_blue_number; ?> font-semibold">
                                    <?= htmlspecialchars($team) ?>
                                </p>
                                <p class="hidden md:block"><?= htmlspecialchars($month) ?></p>
                            </h3>
                            <hr class="hidden md:block">
                            <?php
                                        $hasWeek6 = isset($quarters['Week 6']) && (
                                            ($quarters['Week 6']['1'] ?? 0) > 0 ||
                                            ($quarters['Week 6']['0'] ?? 0) > 0 ||
                                            ($quarters['Week 6']['blank'] ?? 0) > 0 ||
                                            ($quarters['Week 6']['2'] ?? 0) > 0
                                        );
                                        ?>

                            <div
                                class="grid <?= $hasWeek6 ? 'grid-cols-[80px_repeat(7,_1fr)]' : 'grid-cols-[80px_repeat(6,_1fr)]' ?> gap-4 py-4 hidden md:grid">

                                <div class="border rounded-xl p-2 text-center flex flex-col gap-4">
                                    <div class="font-bold">Marks</div>
                                    <div class="flex justify-center items-center gap-2">
                                        <button class="flex justify-center items-center font-bold"
                                            title="Accredited Agents">
                                            <?= $svg['check'] ?>
                                        </button>
                                    </div>
                                    <div class="flex justify-center items-center gap-2">
                                        <button class="flex justify-center items-center font-bold"
                                            title="Unresponsive Agents">
                                            <?= $svg['x_mark'] ?>
                                        </button>
                                    </div>
                                    <div class="flex justify-center items-center gap-2 hidden">
                                        <button class="flex justify-center items-center font-bold"
                                            title="Former Agents">
                                            <?= $svg['signal_lost'] ?>
                                        </button>
                                    </div>
                                    <div class="flex justify-center items-center gap-2">
                                        <button class="flex justify-center items-center font-bold"
                                            title="Onboarding Progress">
                                            <?= $svg['onboarding'] ?>
                                        </button>
                                    </div>
                                </div>


                                <?php
                                            $weekLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'];
                                            if ($hasWeek6) {
                                                $weekLabels[] = 'Week 6';
                                            }
                                            foreach ($weekLabels as $q):
                                                ?>

                                <!-- PRINTS THE WEEKS -->
                                <div class="border rounded-xl p-2 text-center bg-gray-50 flex flex-col gap-4 ">
                                    <div class="font-bold">
                                        <span class="hidden xl:inline"><?= $q ?></span>
                                        <span class="inline xl:hidden"><?= 'W' . substr($q, -1) ?></span>
                                    </div>


                                    <div class=""><?= $quarters[$q]['1'] ?? 0 ?></div>
                                    <div class=""><?= $quarters[$q]['0'] ?? 0 ?></div>
                                    <div class="hidden"><?= $quarters[$q]['blank'] ?? 0 ?></div>
                                    <div class=""><?= $quarters[$q]['2'] ?? 0 ?></div>
                                </div>
                                <?php endforeach; ?>
                                <!-- this will show TOTAL per marks -->
                                <div
                                    class="rounded-xl py-2 text-center flex w-full h-full flex-col gap-4 bg-blue-<?= $default_blue_number; ?> text-white">
                                    <div class="font-bold">
                                        <span class="hidden xl:inline">Month Total</span>
                                        <span class="inline xl:hidden">MT</span>
                                    </div>


                                    <?php
                                                $totals_by_quarter = [];
                                                foreach ($weekLabels as $label) {
                                                    $totals_by_quarter[$label] = 0;
                                                }
                                                $total_check = 0;
                                                $total_x = 0;
                                                $total_blank = 0;
                                                $total_onboarding = 0;
                                                // Only process quarters that are in our weekLabels array
                                                foreach ($quarters as $quarter_name => $counts) {
                                                    // Only process if this quarter is in our weekLabels
                                                    if (in_array($quarter_name, $weekLabels)) {
                                                        $total_check += $counts['1'] ?? 0;
                                                        $total_x += $counts['0'] ?? 0;
                                                        $total_blank += $counts['blank'] ?? 0;
                                                        $total_onboarding += $counts['2'] ?? 0;
                                                        $totals_by_quarter[$quarter_name] += ($counts['1'] ?? 0) + ($counts['0'] ?? 0) + ($counts['blank'] ?? 0) + ($counts['2'] ?? 0);
                                                    }
                                                }
                                                // Grand total (sum of all marks)
                                                $grand_total = $total_check + $total_x + $total_blank + $total_onboarding;
                                                ?>

                                    <div class="hover:bg-white group">
                                        <span
                                            class="font-semibold flex justify-center items-center group-hover:text-blue-<?= $default_blue_number; ?>">
                                            <?= $total_check ?>
                                        </span>
                                    </div>
                                    <div class="hover:bg-white group">
                                        <span
                                            class="font-semibold flex justify-center items-center group-hover:text-blue-<?= $default_blue_number; ?>">
                                            <?= $total_x ?>
                                        </span>
                                    </div>
                                    <div class="hover:bg-white group hidden">
                                        <span
                                            class="font-semibold flex justify-center items-center group-hover:text-blue-<?= $default_blue_number; ?>">
                                            <?= $total_blank ?>
                                        </span>
                                    </div>
                                    <div class="hover:bg-white group">
                                        <span
                                            class="font-semibold flex justify-center items-center group-hover:text-blue-<?= $default_blue_number; ?>">
                                            <?= $total_onboarding ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Grand Total -->
                                <div class="border rounded-xl p-2 text-center flex flex-col gap-4">
                                    <div class="font-bold">Total</div>
                                </div>

                                <?php foreach ($weekLabels as $week): ?>
                                <div class="border font-semibold rounded-xl p-2 text-center">
                                    <?= ($quarters[$week]['1'] ?? 0) + ($quarters[$week]['0'] ?? 0) + ($quarters[$week]['blank'] ?? 0) + ($quarters[$week]['2'] ?? 0); ?>
                                </div>
                                <?php endforeach; ?>

                                <div
                                    class="border rounded-xl p-2 text-center flex flex-col gap-4 bg-blue-<?= $default_blue_number; ?> text-white font-semibold">
                                    <?= $grand_total ?>
                                </div>
                            </div>
                        </div>

                        <?php endforeach; ?>

                        <!-- DISPLAY CHARTS FOR EACH MONTH -->
                        <div class="bg-white rounded-2xl shadow pt-2 pb-4 px-4 mb-6">

                            <?php
                                    // Always render the chart canvas for every month-section
                                    if (isset($monthlyData[$currentMonthKey])):
                                        ?>
                            <h3
                                class="text-xl md:text-2xl font-semibold my-2 flex justify-between items-center md:flex-row flex-col">
                                <p class="text-blue-<?= $default_blue_number; ?> font-semibold">
                                    <?= date('F', strtotime($currentMonthKey . '-01')) // Month only ?>
                                </p>

                                <p class="hidden md:block">
                                    <?= date('Y', strtotime($currentMonthKey . '-01')) // Year only ?>
                                </p>
                            </h3>

                            <hr class="h-px my-4 mt-2">

                            <div class="relative w-full max-w-full sm:max-w-3xl mx-auto h-[300px] sm:h-[400px]">
                                <canvas id="chart_<?= str_replace('-', '_', $currentMonthKey) ?>"
                                    class="w-full h-full"></canvas>
                            </div>

                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

                <!-- Manual Data Table JavaScript -->
                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const nameInput = document.getElementById("search-name");
                    const teamFilter = document.getElementById("filter-team");
                    const toggleFilter = document.getElementById("filter-toggle");
                    const monthFilter = document.getElementById("filter-month");

                    let searchTimeout; // For debouncing the search

                    function fetchFilteredData() {
                        const name = nameInput.value;
                        const team = teamFilter.value;
                        const toggle = toggleFilter.value;
                        const month = monthFilter.value;

                        // Show loading indicator
                        const tableBody = document.getElementById("user-table-body");
                        tableBody.innerHTML =
                            '<tr><td colspan="5" class="text-center py-4 text-gray-500">Loading...</td></tr>';

                        const xhr = new XMLHttpRequest();
                        xhr.open("POST", "accreditation/ajax/fetch_filtered_data.php", true);
                        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                document.getElementById("user-table-body").innerHTML = xhr.responseText;
                                // Reattach jQuery event handlers after content update
                                attachEventHandlers();
                            } else {
                                tableBody.innerHTML =
                                    '<tr><td colspan="5" class="text-center py-4 text-red-500">Error loading data</td></tr>';
                            }
                        };
                        xhr.onerror = function() {
                            tableBody.innerHTML =
                                '<tr><td colspan="5" class="text-center py-4 text-red-500">Error loading data</td></tr>';
                        };
                        xhr.send(
                            `name=${encodeURIComponent(name)}&team=${encodeURIComponent(team)}&toggle=${encodeURIComponent(toggle)}&month=${encodeURIComponent(month)}`
                        );
                    }

                    // Debounced search function
                    function debouncedSearch() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(fetchFilteredData, 500); // 500ms delay
                    }

                    // Add event listeners with different behaviors
                    nameInput.addEventListener("input", debouncedSearch); // Debounced for typing
                    teamFilter.addEventListener("change", fetchFilteredData); // Immediate for dropdowns
                    toggleFilter.addEventListener("change", fetchFilteredData); // Immediate for dropdowns
                    monthFilter.addEventListener("change", fetchFilteredData); // Immediate for month
                });

                function attachEventHandlers() {
                    // Automatically update on change of team or toggle
                    $('select.team-select, select.toggle-select').off('change').on('change', function() {
                        const row = $(this).closest('tr');
                        const id = row.data('id');
                        const team = row.find('.team-select').val();
                        const toggle = row.find('.toggle-select').val();

                        $.post('accreditation/ajax/update_data.php', {
                            id,
                            team,
                            toggle
                        }, function(response) {
                            console.log("✅ " + response);
                        }).fail(function() {
                            alert("❌ Failed to update.");
                        });
                    });

                    // Delete button event handler
                    $('.delete-btn').off('click').on('click', function() {
                        if (!confirm("Are you sure you want to delete this entry?")) return;

                        const row = $(this).closest('tr');
                        const id = row.data('id');
                        const name = row.data('name');

                        $.post('accreditation/ajax/delete_data.php', {
                            id,
                            name
                        }, function(response) {
                            alert(response);
                            row.remove();
                        }).fail(function() {
                            alert("❌ Failed to delete.");
                        });
                    });
                }

                $(document).ready(function() {
                    // Initial attachment of event handlers
                    attachEventHandlers();
                });
                </script>

            </div>


        </div>
    </div>


    <script>
    // Show notification function
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg text-white ${type === 'success' ? 'bg-green-500' : 'bg-red-500'
                } shadow-lg z-50 transition-opacity duration-500`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 3000);
    }

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
</body>

</html>