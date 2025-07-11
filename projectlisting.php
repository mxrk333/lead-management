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

// Initialize variables
$error_message = null;
$projects = [];
$high_priority_projects = [];
$provinces = [];
$cities = [];
$total_projects = 0;
$total_pages = 1;
$page = 1;
// Fetch all projects on one page for client-side searching
$items_per_page = 0; // 0 means no limit

// Default filters
$filters = [
    'search' => '',
    'province_id' => '',
    'city_id' => '',
    'price_range' => '',
    'commission' => '',
    'category' => ''
];

// Process filters from GET parameters
foreach ($filters as $key => $value) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $filters[$key] = $_GET[$key];
    }
}

// Set current page from GET parameter
if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
    $page = (int)$_GET['page'];
}

// Calculate offset for pagination
$offset = ($page - 1) * $items_per_page;

try {
    // Get database connection
    $conn = getDbConnection();
    
    // Fetch provinces
    $provinces_result = safeQuery($conn, "SELECT * FROM provinces ORDER BY name");
    if ($provinces_result) {
        while ($row = $provinces_result->fetch_assoc()) {
            $provinces[] = $row;
        }
    }
    
    // Fetch cities based on province filter
    $cities_query = "SELECT * FROM cities";
    $cities_params = [];
    $cities_types = "";
    
    if (!empty($filters['province_id'])) {
        $cities_query .= " WHERE province_id = ?";
        $cities_params[] = $filters['province_id'];
        $cities_types = "i";
    }
    
    $cities_query .= " ORDER BY name";
    $cities_result = safeQuery($conn, $cities_query, $cities_params, $cities_types);
    
    if ($cities_result) {
        while ($row = $cities_result->fetch_assoc()) {
            $cities[] = $row;
        }
    }
    
    // Build query for projects with filters
    $query = "SELECT p.*, c.name as city_name, pr.name as province_name 
              FROM projects p 
              LEFT JOIN cities c ON p.city_id = c.id 
              LEFT JOIN provinces pr ON p.province_id = pr.id 
              WHERE 1=1";
    
    $count_query = "SELECT COUNT(*) as total FROM projects p 
                    LEFT JOIN cities c ON p.city_id = c.id 
                    LEFT JOIN provinces pr ON p.province_id = pr.id 
                    WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Apply filters
    if (!empty($filters['search'])) {
        $search_term = "%" . $filters['search'] . "%";
        $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.developer LIKE ? OR c.name LIKE ? OR pr.name LIKE ?)";
        $count_query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.developer LIKE ? OR c.name LIKE ? OR pr.name LIKE ?)";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
        $types .= "sssss";
    }
    
    if (!empty($filters['province_id'])) {
        $query .= " AND p.province_id = ?";
        $count_query .= " AND p.province_id = ?";
        $params[] = $filters['province_id'];
        $types .= "i";
    }
    
    if (!empty($filters['city_id'])) {
        $query .= " AND p.city_id = ?";
        $count_query .= " AND p.city_id = ?";
        $params[] = $filters['city_id'];
        $types .= "i";
    }
    
    if (!empty($filters['commission'])) {
        $query .= " AND p.commission = ?";
        $count_query .= " AND p.commission = ?";
        $params[] = $filters['commission'];
        $types .= "d";
    }
    
    if (!empty($filters['price_range'])) {
        $price_range = explode('-', $filters['price_range']);
        if (count($price_range) == 2) {
            $min_price = $price_range[0];
            $max_price = $price_range[1];
            $query .= " AND ((p.price_min >= ? AND p.price_min <= ?) OR (p.price_max >= ? AND p.price_max <= ?))";
            $count_query .= " AND ((p.price_min >= ? AND p.price_min <= ?) OR (p.price_max >= ? AND p.price_max <= ?))";
            $params = array_merge($params, [$min_price, $max_price, $min_price, $max_price]);
            $types .= "dddd";
        } elseif (substr($filters['price_range'], -1) == '+') {
            $min_price = (int)substr($filters['price_range'], 0, -1);
            $query .= " AND (p.price_min >= ? OR p.price_max >= ?)";
            $count_query .= " AND (p.price_min >= ? OR p.price_max >= ?)";
            $params = array_merge($params, [$min_price, $min_price]);
            $types .= "dd";
        }
    }
    
    // Get total count (no longer used for pagination, but may be useful for stats)
    $count_result = safeQuery($conn, $count_query, $params, $types);
    if ($count_result && $row = $count_result->fetch_assoc()) {
        $total_projects = $row['total'];
        $total_pages = 1; // all projects displayed on single page
    }
    
    // Remove pagination limit – fetch all
    $query .= " ORDER BY FIELD(p.priority, 'high', 'medium', 'low'), p.name ASC";
    
    // Execute main query
    $result = safeQuery($conn, $query, $params, $types);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Format price range for display
            $price_min = number_format($row['price_min']);
            $price_max = number_format($row['price_max']);
            $row['price_range_display'] = "₱{$price_min} - ₱{$price_max}";
            
            $projects[] = $row;
        }
    }
    
    // Close connection
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error in project listing: " . $e->getMessage());
    $error_message = "An error occurred while loading projects. Please try again later.";
}

// Build base URL for pagination
$base_url = "projectlisting.php?";
foreach ($filters as $key => $value) {
    if (!empty($value)) {
        $base_url .= "{$key}=" . urlencode($value) . "&";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inner SPARC Projects</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            0%, 100% {
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
            .sidebar-provider.collapsed ~ .main-container {
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
<<<<<<< HEAD
<body class="bg-gray-50 min-h-screen">
    <!-- Main Container - Add ml-64 for sidebar space -->
    <div id="main-container" class="main-container relative transition-all" style="margin-left: var(--sidebar-width);">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Header Section -->
            <div class="mb-8">
                <div class="text-center">
                    <h1 class="text-2xl sm:text-3xl md:text-5xl font-bold text-blue-primary mb-4">
                        Inner SPARC Projects
                    </h1>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Gusto mo ng mabilisang benta? Check mo na 'tong list ng project listing na may full info, ready i-offer sa clients!                    </p>
                </div>
            </div>

=======

</html>
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

// Initialize variables
$error_message = null;
$projects = [];
$high_priority_projects = [];
$provinces = [];
$cities = [];
$total_projects = 0;
$total_pages = 1;
$page = 1;
// Fetch all projects on one page for client-side searching
$items_per_page = 0; // 0 means no limit

// Default filters
$filters = [
    'search' => '',
    'province_id' => '',
    'city_id' => '',
    'price_range' => '',
    'commission' => '',
    'category' => ''
];

// Process filters from GET parameters
foreach ($filters as $key => $value) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $filters[$key] = $_GET[$key];
    }
}

// Set current page from GET parameter
if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
    $page = (int)$_GET['page'];
}

// Calculate offset for pagination
$offset = ($page - 1) * $items_per_page;

try {
    // Get database connection
    $conn = getDbConnection();
    
    // Fetch provinces
    $provinces_result = safeQuery($conn, "SELECT * FROM provinces ORDER BY name");
    if ($provinces_result) {
        while ($row = $provinces_result->fetch_assoc()) {
            $provinces[] = $row;
        }
    }
    
    // Fetch cities based on province filter
    $cities_query = "SELECT * FROM cities";
    $cities_params = [];
    $cities_types = "";
    
    if (!empty($filters['province_id'])) {
        $cities_query .= " WHERE province_id = ?";
        $cities_params[] = $filters['province_id'];
        $cities_types = "i";
    }
    
    $cities_query .= " ORDER BY name";
    $cities_result = safeQuery($conn, $cities_query, $cities_params, $cities_types);
    
    if ($cities_result) {
        while ($row = $cities_result->fetch_assoc()) {
            $cities[] = $row;
        }
    }
    
    // Build query for projects with filters
    $query = "SELECT p.*, c.name as city_name, pr.name as province_name 
              FROM projects p 
              LEFT JOIN cities c ON p.city_id = c.id 
              LEFT JOIN provinces pr ON p.province_id = pr.id 
              WHERE 1=1";
    
    $count_query = "SELECT COUNT(*) as total FROM projects p 
                    LEFT JOIN cities c ON p.city_id = c.id 
                    LEFT JOIN provinces pr ON p.province_id = pr.id 
                    WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Apply filters
    if (!empty($filters['search'])) {
        $search_term = "%" . $filters['search'] . "%";
        $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.developer LIKE ? OR c.name LIKE ? OR pr.name LIKE ?)";
        $count_query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.developer LIKE ? OR c.name LIKE ? OR pr.name LIKE ?)";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
        $types .= "sssss";
    }
    
    if (!empty($filters['province_id'])) {
        $query .= " AND p.province_id = ?";
        $count_query .= " AND p.province_id = ?";
        $params[] = $filters['province_id'];
        $types .= "i";
    }
    
    if (!empty($filters['city_id'])) {
        $query .= " AND p.city_id = ?";
        $count_query .= " AND p.city_id = ?";
        $params[] = $filters['city_id'];
        $types .= "i";
    }
    
    if (!empty($filters['commission'])) {
        $query .= " AND p.commission = ?";
        $count_query .= " AND p.commission = ?";
        $params[] = $filters['commission'];
        $types .= "d";
    }
    
    if (!empty($filters['price_range'])) {
        $price_range = explode('-', $filters['price_range']);
        if (count($price_range) == 2) {
            $min_price = $price_range[0];
            $max_price = $price_range[1];
            $query .= " AND ((p.price_min >= ? AND p.price_min <= ?) OR (p.price_max >= ? AND p.price_max <= ?))";
            $count_query .= " AND ((p.price_min >= ? AND p.price_min <= ?) OR (p.price_max >= ? AND p.price_max <= ?))";
            $params = array_merge($params, [$min_price, $max_price, $min_price, $max_price]);
            $types .= "dddd";
        } elseif (substr($filters['price_range'], -1) == '+') {
            $min_price = (int)substr($filters['price_range'], 0, -1);
            $query .= " AND (p.price_min >= ? OR p.price_max >= ?)";
            $count_query .= " AND (p.price_min >= ? OR p.price_max >= ?)";
            $params = array_merge($params, [$min_price, $min_price]);
            $types .= "dd";
        }
    }
    
    // Get total count (no longer used for pagination, but may be useful for stats)
    $count_result = safeQuery($conn, $count_query, $params, $types);
    if ($count_result && $row = $count_result->fetch_assoc()) {
        $total_projects = $row['total'];
        $total_pages = 1; // all projects displayed on single page
    }
    
    // Remove pagination limit – fetch all
    $query .= " ORDER BY FIELD(p.priority, 'high', 'medium', 'low'), p.name ASC";
    
    // Execute main query
    $result = safeQuery($conn, $query, $params, $types);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Format price range for display
            $price_min = number_format($row['price_min']);
            $price_max = number_format($row['price_max']);
            $row['price_range_display'] = "₱{$price_min} - ₱{$price_max}";
            
            $projects[] = $row;
        }
    }
    
    // Close connection
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error in project listing: " . $e->getMessage());
    $error_message = "An error occurred while loading projects. Please try again later.";
}

// Build base URL for pagination
$base_url = "projectlisting.php?";
foreach ($filters as $key => $value) {
    if (!empty($value)) {
        $base_url .= "{$key}=" . urlencode($value) . "&";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inner SPARC Projects</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            0%, 100% {
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
            .sidebar-provider.collapsed ~ .main-container {
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
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Header Section -->
            <div class="mb-8">
                <div class="text-center">
                    <h1 class="text-2xl sm:text-3xl md:text-5xl font-bold text-blue-primary mb-4">
                        Inner SPARC Projects
                    </h1>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Gusto mo ng mabilisang benta? Check mo na ‘tong list ng project listing na may full info, ready i-offer sa clients!                    </p>
                </div>
            </div>

>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Filter Section - Left Side -->
                <div class="lg:w-1/4 flex-shrink-0">
                    <div class="filter-section bg-white rounded-2xl shadow-lg p-6 border border-gray-100 sticky top-6">
                        <div class="mb-6">
                            <h3 class="text-2xl font-bold text-blue-primary mb-2 flex items-center">
                                <i class="fas fa-filter mr-3 text-blue-secondary"></i>
                                Find Projects
                            </h3>
                            <p class="text-gray-600 leading-relaxed">
                                Use the filters below to discover your perfect project match.
                            </p>
                        </div>
                        
                        <form id="filter-form" class="space-y-5" onsubmit="event.preventDefault(); event.stopPropagation(); return false;">
                            <!-- Search Bar -->
                            <div>
                                <label for="project_search" class="block text-sm font-semibold text-gray-700 mb-2">
                                    Search Property
                                </label>
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-grow">
                                        <div class="absolute left-4 top-1/2 transform -translate-y-1/2 pointer-events-none">
                                            <i class="fas fa-search text-gray-400"></i>
                                        </div>
                                        <input 
                                            type="text" 
                                            id="project_search" 
                                            data-search-input="true"
                                            value="<?php echo htmlspecialchars($filters['search']); ?>"
                                            class="search-input w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-secondary focus:border-blue-secondary transition-all duration-300 focus-ring" 
                                            placeholder="Search by name, location, or developer"
                                            aria-label="Search properties"
                                            onkeydown="if(event.key === 'Enter') { event.preventDefault(); event.stopPropagation(); performLiveSearch(); return false; }"
                                            onkeyup="event.stopPropagation()"
                                            onkeypress="event.stopPropagation()"
                                        >
                                    </div>
                                    <button type="button" onclick="event.preventDefault(); event.stopPropagation(); performLiveSearch(); return false;" class="px-4 py-3 rounded-xl bg-blue-primary text-white hover:bg-blue-dark transition-all duration-300 focus-ring whitespace-nowrap">
                                        <i class="fas fa-search mr-2"></i> Search
                                    </button>
                                </div>
                            </div>
                              
                            <!-- Province Dropdown -->
                            <div>
                                <label for="province_id" class="block text-sm font-semibold text-gray-700 mb-2">
                                    Province
                                </label>
                                <div class="relative">
                                    <select 
                                        id="province_id" 
                                        name="province_id" 
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-secondary appearance-none transition-all duration-300 focus-ring"
                                        aria-label="Select province"
                                    >
                                        <option value="">All Provinces</option>
                                        <?php foreach ($provinces as $province): ?>
                                            <option value="<?php echo $province['id']; ?>" <?php echo ($filters['province_id'] == $province['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($province['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- City Dropdown -->
                            <div>
                                <label for="city_id" class="block text-sm font-semibold text-gray-700 mb-2">
                                    City
                                </label>
                                <div class="relative">
                                    <select 
                                        id="city_id" 
                                        name="city_id" 
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-secondary appearance-none transition-all duration-300 focus-ring"
                                        aria-label="Select city"
                                    >
                                        <option value="">All Cities</option>
                                        <?php foreach ($cities as $city): ?>
                                            <option value="<?php echo $city['id']; ?>" <?php echo ($filters['city_id'] == $city['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($city['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Price Range Dropdown -->
                            <div>
                                <label for="price_range" class="block text-sm font-semibold text-gray-700 mb-2">
                                    Price Range
                                </label>
                                <div class="relative">
                                    <select 
                                        id="price_range" 
                                        name="price_range" 
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-secondary appearance-none transition-all duration-300 focus-ring"
                                        aria-label="Select price range"
                                    >
                                        <option value="">All Price Ranges</option>
                                        <option value="0-500000" <?php echo ($filters['price_range'] == '0-500000') ? 'selected' : ''; ?>>₱0 - ₱500,000</option>
                                        <option value="500000-1000000" <?php echo ($filters['price_range'] == '500000-1000000') ? 'selected' : ''; ?>>₱500,000 - ₱1,000,000</option>
                                        <option value="1000000-2000000" <?php echo ($filters['price_range'] == '1000000-2000000') ? 'selected' : ''; ?>>₱1,000,000 - ₱2,000,000</option>
                                        <option value="2000000-5000000" <?php echo ($filters['price_range'] == '2000000-5000000') ? 'selected' : ''; ?>>₱2,000,000 - ₱5,000,000</option>
                                        <option value="5000000-10000000" <?php echo ($filters['price_range'] == '5000000-10000000') ? 'selected' : ''; ?>>₱5,000,000 - ₱10,000,000</option>
                                        <option value="10000000+" <?php echo ($filters['price_range'] == '10000000+') ? 'selected' : ''; ?>>₱10,000,000+</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Commission Dropdown -->
<div>
    <label for="commission" class="block text-sm font-semibold text-gray-700 mb-2">
        Commission (%)
    </label>
    <div class="relative">
        <select id="commission" name="commission" onchange="this.form.submit()" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-secondary appearance-none transition-all duration-300 focus-ring"
            aria-label="Select commission rate">
            <option value="">All Rates</option>
            <?php foreach ([2, 2.5, 3, 3.5, 4, 4.5, 5] as $rate): ?>
                <option value="<?php echo $rate; ?>"
                        <?php echo ($filters['commission'] == $rate) ? 'selected' : ''; ?>>
                    <?php echo $rate; ?>%
                </option>
            <?php endforeach; ?>
        </select>
        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
            <i class="fas fa-chevron-down text-gray-400"></i>
        </div>
    </div>
</div> 
                                                       <!-- Action Buttons -->
                            <div class="flex flex-col gap-3 pt-4">
                                <a 
                                    href="projectlisting.php" 
                                    class="w-full px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all duration-300 flex items-center justify-center gap-2 focus-ring"
                                    aria-label="Clear all filters"
                                >
                                    <i class="fas fa-times"></i>
                                    Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Main Content - Right Side -->
                <div class="lg:w-3/4 flex-grow"> <!-- Added flex-grow -->
                    <!-- Results Header -->
                    <div class="mb-6">
                        <div class="bg-white rounded-2xl shadow-md p-6 border border-gray-100">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                <div>
                                    <p class="text-2xl font-bold text-gray-800">
                                        <span class="text-blue-primary" id="results-count"><?php echo number_format($total_projects); ?></span> 
                                        <span class="text-gray-600">properties found</span>
                                    </p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Showing page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                                    </p>
                                </div>
                                
                                <div class="flex gap-3">
                                        <a href="add-project.php" 
                                            class="btn-primary px-6 py-3 text-white rounded-xl font-semibold transition-all duration-300 flex items-center gap-2 focus-ring"
                                            aria-label="Add new project">
                                            <i class="fas fa-plus"></i>
                                            Add Project
                                        </a>
                                
                                    </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="loading-state" class="hidden">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                                    <div class="w-full h-56 bg-gray-200 rounded-xl mb-6 loading-pulse"></div>
                                    <div class="h-6 bg-gray-200 rounded mb-4 loading-pulse"></div>
                                    <div class="h-4 bg-gray-200 rounded mb-2 loading-pulse"></div>
                                    <div class="h-4 bg-gray-200 rounded w-3/4 loading-pulse"></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Content Container -->
                    <div id="content-container">
                        <?php if (isset($error_message)): ?>
                            <!-- Error State -->
                            <div class="bg-red-50 border border-red-200 p-12 rounded-2xl text-center">
                                <i class="fas fa-exclamation-triangle text-red-400 text-6xl mb-6"></i>
                                <h3 class="text-2xl font-semibold text-red-800 mb-3">Error Loading Properties</h3>
                                <p class="text-red-600 text-lg"><?php echo htmlspecialchars($error_message); ?></p>
                                <button 
                                    onclick="location.reload()" 
                                    class="mt-6 px-6 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 transition-all duration-300 focus-ring"
                                >
                                    Try Again
                                </button>
                            </div>
                        <?php elseif (empty($projects)): ?>
                            <!-- No Results State -->
                            <div class="bg-gray-50 border border-gray-200 p-12 rounded-2xl text-center">
                                <i class="fas fa-home text-gray-400 text-6xl mb-6"></i>
                                <h3 class="text-2xl font-semibold text-gray-900 mb-3">No Properties Found</h3>
                                <p class="text-gray-600 text-lg mb-6">
                                    Try adjusting your search criteria or check back later for new listings.
                                </p>
                                <button 
                                    onclick="clearAllFilters()" 
                                    class="btn-primary px-6 py-3 text-white rounded-xl font-semibold transition-all duration-300 focus-ring"
                                >
                                    View All Properties
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Projects Grid -->
                            <div id="projects-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
                                <?php foreach ($projects as $project): ?>
                                    <article class="property-card bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden cursor-pointer focus-ring" 
                                             onclick="showProjectDetails(<?php echo $project['id']; ?>)"
                                             tabindex="0"
                                             role="button"
                                             aria-label="View details for <?php echo htmlspecialchars($project['name']); ?>"
                                             onkeydown="if(event.key==='Enter'||event.key===' ') showProjectDetails(<?php echo $project['id']; ?>)">
                                        
                                        <?php if (!empty($project['image1'])): ?>
                                            <div class="w-full h-56 overflow-hidden">
                                                <img 
                                                    src="<?php echo htmlspecialchars(!empty($project['image1']) ? 'uploads/projects/' . $project['image1'] : ''); ?>" 
                                                    alt="<?php echo htmlspecialchars($project['name']); ?>" 
                                                    class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                    loading="lazy"
                                                    onerror="this.onerror=null; this.src='images/placeholder-property.png';"
                                                >
                                            </div>
                                        <?php else: ?>
                                            <div class="w-full h-56 bg-gray-200 flex items-center justify-center">
                                                <i class="fas fa-image text-gray-400 text-4xl"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="p-6">                                            <h4 class="text-xl font-bold text-gray-800 mb-1 line-clamp-2">
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </h4>
                                            <div class="text-sm text-gray-600 mb-4">
                                                <i class="fas fa-building mr-1"></i>
                                                <?php echo htmlspecialchars($project['developer']); ?>
                                            </div>
                                            
                                            <div class="space-y-4 mb-6">
                                                <!-- Badges -->
                                                <div class="flex flex-wrap gap-2">
                                                    <span class="price-badge px-3 py-1 rounded-full text-sm font-bold">
                                                        <?php echo $project['commission']; ?>% COMM
                                                    </span>
                                                    
                                                    <span class="priority-<?php echo $project['priority']; ?> px-3 py-1 rounded-full text-sm font-semibold">
                                                        <?php echo ucfirst($project['priority']); ?> Priority
                                                    </span>
                                                </div>

                                                <!-- Price -->
                                                <div class="text-blue-primary text-xl font-bold">
                                                    <?php echo $project['price_range_display']; ?>
                                                </div>

                                                <!-- Location -->
                                                <div class="text-gray-500 flex items-center">
                                                    <i class="fas fa-map-marker-alt mr-2 text-blue-secondary"></i>
                                                    <span class="line-clamp-1">
                                                        <?php echo htmlspecialchars($project['city_name'] . ', ' . $project['province_name']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="flex flex-col gap-2">
                                                <button 
                                                    onclick="event.stopPropagation(); showProjectDetails(<?php echo $project['id']; ?>);" 
                                                    class="w-full px-4 py-3 bg-blue-light text-blue-primary rounded-xl font-semibold hover:bg-blue-200 transition-all duration-300 flex items-center justify-center gap-2 focus-ring"
                                                    aria-label="View details for <?php echo htmlspecialchars($project['name']); ?>"
                                                >
                                                    <i class="fas fa-eye"></i>
                                                    View Details
                                                </button>
                                                
                                              <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
    <a 
        href="edit-project.php?id=<?php echo $project['id']; ?>" 
        onclick="event.stopPropagation();"
        class="w-full px-4 py-3 bg-blue-100 text-blue-700 rounded-xl font-semibold hover:bg-blue-200 transition-all duration-300 flex items-center justify-center gap-2 focus-ring"
        aria-label="Edit <?php echo htmlspecialchars($project['name']); ?>"
    >
        <i class="fas fa-edit"></i>
        Edit Project
    </a>
<?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav class="flex justify-center" aria-label="Pagination Navigation">
                                    <div class="flex items-center space-x-2">
                                        <?php if ($page > 1): ?>
                                            <a 
                                                href="<?php echo $base_url . '&page=' . ($page - 1); ?>" 
                                                class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-300 focus-ring"
                                                aria-label="Previous page"
                                            >
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start = max(1, min($page - 2, $total_pages - 4));
                                        $end = min($total_pages, max(5, $page + 2));
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <a 
                                                href="<?php echo $base_url . '&page=' . $i; ?>" 
                                                class="px-4 py-2 border rounded-lg transition-all duration-300 focus-ring <?php echo $i === $page ? 'bg-blue-primary text-white border-blue-primary' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>"
                                                aria-label="Page <?php echo $i; ?>"
                                                <?php echo $i === $page ? 'aria-current="page"' : ''; ?>
                                            >
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a 
                                                href="<?php echo $base_url . '&page=' . ($page + 1); ?>" 
                                                class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-300 focus-ring"
                                                aria-label="Next page"
                                            >
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

<<<<<<< HEAD
                   <!-- High Priority Projects Section -->
=======
                    <!-- High Priority Projects Section -->
>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c
                    <?php /* if (!empty($high_priority_projects) && empty(array_filter($filters))): ?>
                        <section class="mt-20" aria-labelledby="priority-heading">
                            <div class="text-center mb-10">
                                <h2 id="priority-heading" class="text-3xl font-bold text-blue-primary mb-4">
                                    High Priority Projects
                                </h2>
                                <div class="w-24 h-1 bg-blue-secondary mx-auto rounded-full"></div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                                <?php foreach ($high_priority_projects as $project): ?>
                                    <article class="property-card bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden cursor-pointer focus-ring" 
                                             onclick="showProjectDetails(<?php echo $project['id']; ?>)"
                                             tabindex="0"
                                             role="button"
                                             aria-label="View details for featured property <?php echo htmlspecialchars($project['name']); ?>"
                                             onkeydown="if(event.key==='Enter'||event.key===' ') showProjectDetails(<?php echo $project['id']; ?>)">
                                        
                                        <?php if (!empty($project['image1'])): ?>
                                             <div class="w-full h-56 overflow-hidden">
                                                 <img 
                                                     src="<?php echo htmlspecialchars('uploads/projects/' . $project['image1']); ?>" 
                                                     alt="<?php echo htmlspecialchars($project['name']); ?>" 
                                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                     loading="lazy"
                                                     onerror="this.onerror=null; this.src='images/placeholder-property.png';"
                                                 >
                                             </div>
                                         <?php else: ?>
                                             <div class="w-full h-56 bg-gray-200 flex items-center justify-center">
                                                 <i class="fas fa-image text-gray-400 text-4xl"></i>
                                             </div>
                                         <?php endif; ?>
                                        
                                        <div class="p-6">
                                            <h4 class="text-xl font-bold text-gray-800 mb-4">
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </h4>
                                            
                                            <div class="flex flex-wrap gap-2 mb-4">
                                                <span class="price-badge px-3 py-1 rounded-full text-sm font-bold">
                                                    <?php echo $project['commission']; ?>% COMM
                                                </span>
                                                <span class="priority-high px-3 py-1 rounded-full text-sm font-semibold">
                                                    High Priority
                                                </span>
                                            </div>
                                            
                                            <div class="text-blue-primary text-xl font-bold mb-4">
                                                <?php echo $project['price_range_display']; ?>
                                            </div>

                                            <div class="text-gray-500 flex items-center mb-6">
                                                <i class="fas fa-map-marker-alt mr-2 text-blue-secondary"></i>
                                                <?php echo htmlspecialchars($project['city_name'] . ', ' . $project['province_name']); ?>
                                            </div>
                                            
                                            <div class="flex flex-col gap-2">
                                                <button 
                                                    onclick="event.stopPropagation(); showProjectDetails(<?php echo $project['id']; ?>);" 
                                                    class="w-full px-4 py-3 bg-blue-light text-blue-primary rounded-xl font-semibold hover:bg-blue-200 transition-all duration-300 flex items-center justify-center gap-2 focus-ring"
                                                >
                                                    <i class="fas fa-eye"></i>
                                                    View Details
                                                </button>
                                                
                                                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                                                    <a 
                                                        href="editproperty.php?id=<?php echo $project['id']; ?>" 
                                                        onclick="event.stopPropagation();"
                                                        class="w-full px-4 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all duration-300 flex items-center justify-center gap-2 focus-ring"
                                                    >
                                                        <i class="fas fa-edit"></i>
                                                        Edit Project
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
<<<<<<< HEAD
                    <?php endif; */ ?> 
=======
                    <?php endif; */ ?>
>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c
                </div>
            </div>
        </div>
    </div>    <!-- Project Details Modal -->
    <div id="project-modal" class="fixed inset-0 modal-backdrop flex justify-center items-start sm:items-center z-50 px-4 py-8 overflow-y-auto hidden" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl relative mx-auto my-auto border border-gray-100 max-h-[90vh] overflow-y-auto">
            <!-- Close Button -->
            <button 
                onclick="closeProjectModal()" 
                class="absolute top-4 right-4 z-10 p-2 bg-white bg-opacity-90 text-gray-700 rounded-full hover:bg-gray-100 transition-all duration-300 focus-ring"
                aria-label="Close modal"
            >
                <i class="fas fa-times text-xl"></i>
            </button>            <!-- Modal Content -->
            <div id="modal-content" class="p-6">
                <!-- Content will be loaded here -->
            </div>

            <!-- Actions -->
            <div id="project-actions" class="p-6 bg-gray-50 border-t border-gray-100 flex justify-end space-x-4">
                <button onclick="editProject()" class="px-4 py-2 bg-blue-primary text-white rounded-lg hover:bg-blue-dark transition-colors duration-200">
                    <i class="fas fa-edit mr-2"></i>Edit Project
                </button>
                <button onclick="confirmDelete()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                    <i class="fas fa-trash-alt mr-2"></i>Delete Project
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 modal-backdrop flex justify-center items-center z-60 hidden">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Confirm Deletion</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete this project? This action cannot be undone.</p>
            <div class="flex justify-end space-x-4">
                <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                    Cancel
                </button>
                <button onclick="deleteProject()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                    Delete Project
                </button>
            </div>
        </div>
    </div>

<!-- Messenger Link Modal -->
<div id="messenger-modal" class="fixed inset-0 bg-black bg-opacity-75 flex justify-center items-center z-[2000] px-4 sm:px-6 overflow-y-auto hidden backdrop-blur-sm">
  <div class="bg-white rounded-xl p-6 shadow-2xl w-full max-w-3xl relative mt-6 mb-6 overflow-y-auto max-h-[80vh] border border-gray-100">
    <!-- Close Button -->
    <button id="close-messenger-modal" class="absolute top-3 right-3 p-2 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
    <!-- Title -->
    <div class="flex items-center gap-4 mb-6">
      <div class="bg-blue-100 p-3 rounded-full">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03 8 9 8s9 3.582 9 8z" />
        </svg>
      </div>
      <h3 class="text-2xl font-semibold text-gray-800">Contact via Messenger</h3>
    </div>
    <!-- Link Copy Box -->
    <div class="bg-gray-50 p-4 rounded-lg mb-6">
      <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
        <input id="messenger-url-input" type="text" class="flex-1 px-4 py-3 text-lg border border-gray-300 rounded-lg" readonly>
        <button id="copy-link-btn" class="px-6 py-3 bg-blue-600 text-white text-lg rounded-lg hover:bg-blue-700 transition flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          Copy
        </button>
      </div>
      <p id="copy-success" class="text-green-600 text-sm mt-3 hidden">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        Link copied successfully!
      </p>
    </div>
    <!-- Tutorial Steps -->
    <div class="text-lg text-gray-700 space-y-6">
      <h4 class="font-semibold text-center text-2xl mb-6">Paano gamitin:</h4>
      <div class="space-y-4">
        <div class="flex gap-4 items-center">
          <img src="/media/messenger/step2.jpg" alt="Step 1" class="w-28 h-28 object-cover rounded-lg border">
          <p class="text-lg"><strong class="text-blue-600">Step 1:</strong> Buksan ang Messenger app o Messenger.com</p>
        </div>
        <div class="flex gap-4 items-center">
          <img src="/media/messenger/step3.jpg" alt="Step 2"class="w-28 h-28 object-cover rounded-lg border">
          <p class="text-lg"><strong class="text-blue-600">Step 2:</strong> I-paste ang link sa kahit anong chat.</p>
        </div>
        <div class="flex gap-4 items-center">
          <img src="/media/messenger/step4.jpg" alt="Step 3" class="w-28 h-28 object-cover rounded-lg border">
          <p class="text-lg"><strong class="text-blue-600">Step 3:</strong> I-tap ang \"View Chat\" para makita ang contact.</p>
        </div>
        <div class="flex gap-4 items-center">
          <img src="/media/messenger/step5.jpg" alt="Step 4" class="w-28 h-28 object-cover rounded-lg border">
          <p class="text-lg"><strong class="text-blue-600">Step 4:</strong> Pwede ka nang mag-message!</p>
        </div>
      </div>
      <p class="text-sm text-gray-500 mt-3 bg-gray-50 p-3 rounded-lg border text-center">
        <em>Tip: Mas gumagana ang links sa Messenger app kaysa browser.</em>
      </p>
    </div>
  </div>
</div>

    <script>
        // Global variables
<<<<<<< HEAD
        var searchTimeout;
        var isLoading = false;
        var currentProjectId = null;
=======
        let searchTimeout;
        let isLoading = false;
        let currentProjectId = null;
>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Show loading state
        function showLoading() {
            if (isLoading) return;
            isLoading = true;
            document.getElementById('loading-state').classList.remove('hidden');
            document.getElementById('content-container').classList.add('hidden');
        }

        // Hide loading state
        function hideLoading() {
            isLoading = false;
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('content-container').classList.remove('hidden');
        }

        // Update cities based on province selection
        function updateCities() {
            const provinceId = document.getElementById('province_id').value;
            const citySelect = document.getElementById('city_id');
            
            citySelect.innerHTML = '<option value="">All Cities</option>';
            
            if (provinceId) {
                fetch(`api/get_cities.php?province_id=${provinceId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            data.cities.forEach(city => {
                                const option = document.createElement('option');
                                option.value = city.id;
                                option.textContent = city.name;
                                citySelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => console.error('Error loading cities:', error));
            }
        }

        // Perform live search
<<<<<<< HEAD
        var performLiveSearch = debounce(() => {
=======
        const performLiveSearch = debounce(() => {
>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c
            // Get search input value
            const searchInput = document.getElementById('project_search');
            const searchValue = searchInput ? searchInput.value.trim() : '';
            
            // Get other form values
            const provinceId = document.getElementById('province_id')?.value || '';
            const cityId = document.getElementById('city_id')?.value || '';
            const priceRange = document.getElementById('price_range')?.value || '';
            const commission = document.getElementById('commission')?.value || '';
            
            // Build URL with parameters
            const params = new URLSearchParams();
            
            if (searchValue) params.append('search', searchValue);
            if (provinceId) params.append('province_id', provinceId);
            if (cityId) params.append('city_id', cityId);
            if (priceRange) params.append('price_range', priceRange);
            if (commission) params.append('commission', commission);
            
            // Redirect to the same page with filters
            window.location.href = `projectlisting.php?${params.toString()}`;
            
        }, 500);

        // Clear all filters
        function clearAllFilters() {
            window.location.href = 'projectlisting.php';
        }

        // Edit project function
        function editProject() {
            if (currentProjectId) {
                window.location.href = `edit-project.php?id=${currentProjectId}`;
            }
        }

        // Show delete confirmation modal
        function confirmDelete() {
            // show delete confirmation on top of the existing project modal
            document.getElementById('delete-modal').classList.remove('hidden');
        }

        // Close delete confirmation modal
        function closeDeleteModal() {
            document.getElementById('delete-modal').classList.add('hidden');
        }


        // Show notification function
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
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

        // Add event listener for the search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('project_search');
            if (searchInput) {
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        performLiveSearch();
                        return false;
                    }
                }, true); // Use capture phase to catch the event early
                
                // Also handle keyup just in case
                searchInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }
                }, true);
            }
            
            // Prevent form submission entirely
            const form = document.getElementById('filter-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    return false;
                }, true);
            }
        });

        // Show project details modal with new functionality
        function showProjectDetails(projectId) {
            currentProjectId = projectId; // Store the current project ID
            const modal = document.getElementById('project-modal');
            const modalContent = document.getElementById('modal-content');
            
            modalContent.innerHTML = `
                <div class="flex items-center justify-center p-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-primary"></div>
                    <span class="ml-3 text-lg text-gray-600">Loading project details...</span>
                </div>
            `;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            fetch(`api/get_project.php?id=${projectId}&t=${Date.now()}`)
                .then(response => response.json())
                .then(data => {                    if (data.success) {
                        modalContent.innerHTML = generateProjectModalContent(data.project);
                    } else {
                        throw new Error(data.error || 'Failed to load project details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalContent.innerHTML = `
                        <div class="text-center p-12">
                            <i class="fas fa-exclamation-triangle text-red-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-medium text-red-800 mb-2">Error Loading Project</h3>
                            <p class="text-red-600 mb-4">${error.message}</p>
                            <button onclick="closeProjectModal()" class="px-4 py-2 bg-blue-primary text-white rounded-lg hover:bg-blue-dark transition focus-ring">
                                Close
                            </button>
                        </div>
                    `;
                });
        }

        // Generate project modal content
        function generateProjectModalContent(project) {
    const makePath = img => {
        if (!img) return null;
        // If already contains a slash, assume it is a full path
        return img.includes('/') ? img : `uploads/projects/${img}`;
    }
    const images = [makePath(project.image1), makePath(project.image2), makePath(project.image3), makePath(project.image4)].filter(Boolean);
    
    let imageGallery = '';
    if (images.length > 0) {
        imageGallery = `
            <div class="mb-6">
                <div class="w-full h-96 overflow-hidden rounded-xl mb-4 shadow-lg">
                    <img id="main-image" src="${images[0]}" alt="${project.name}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='images/placeholder-property.png';">
                </div>
                ${images.length > 1 ? `
                    <div class="grid grid-cols-4 gap-2">
                        ${images.map((img, index) => `
                            <div class="h-20 overflow-hidden rounded-lg shadow-sm cursor-pointer" onclick="changeMainImage('${img}', ${index})">
                                <img src="${img}" alt="${project.name} ${index + 1}" class="w-full h-full object-cover border-2 ${index === 0 ? 'border-blue-primary' : 'border-transparent'} hover:border-blue-primary transition" id="thumb-${index}" onerror="this.onerror=null; this.src='images/placeholder-property.png';">
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    }

    const statusMap = {
        'rfo': 'RFO (Ready For Occupancy)',
        'preselling': 'Preselling',
        'ogc': 'OGC (On Going Construction)',
        'rfo_preselling': 'RFO/Preselling',
        'preselling_ogc': 'Preselling/OGC'
    };
    const formattedStatus = statusMap[project.status] || project.status;

    // Format financial details with proper currency formatting
    const formatCurrency = (amount) => {
        if (!amount || amount == 0) return 'Not specified';
        return '₱' + Number(amount).toLocaleString();
    };

    const formatPercentage = (percentage) => {
        if (!percentage || percentage == 0) return 'Not specified';
        return percentage + '%';
    };

    // Build financial details section with improved layout
    let financialDetails = '';
    if (project.total_contract_price || project.reservation_fee || project.bank_amortization || 
        project.required_salary || project.downpayment_percentage || 
<<<<<<< HEAD
        project.downpayment_amount || project.downpayment_term) {
        
            
        financialDetails = `
            <div class="bg-blue-50 rounded-2xl p-6 mb-8">
                <h4 class="text-2xl font-semibold text-blue-primary mb-6 flex items-center">
                    <i class="fas fa-calculator mr-3"></i>Sample Computation
                </h4>
                <div class="flex flex-col sm:flex-row justify-between text-sm text-gray-500 mb-8 px-2">
    
    <div>
        <i class="far fa-clock mr-1"></i>
        Data as of: ${project.updated_at ? new Date(project.updated_at.replace(' ', 'T')).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}
    </div>
</div>


=======
        project.monthly_downpayment_3mos || project.monthly_downpayment_6mos || 
        project.monthly_downpayment_12mos || project.monthly_downpayment_18mos) {
        
        financialDetails = `
            <div class="bg-blue-50 rounded-2xl p-6 mb-8">
                <h4 class="text-2xl font-semibold text-blue-primary mb-6 flex items-center">
                    <i class="fas fa-calculator mr-3"></i>Financial Details
                </h4>
>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c
                <div class="grid grid-cols-1 gap-4">
                    ${project.total_contract_price ? `
                        <div class="flex flex-col sm:flex-row sm:items-center py-2 border-b border-blue-100 last:border-b-0">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">Total Contract Price</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-lg font-bold text-gray-800">${formatCurrency(project.total_contract_price)}</span>
                            </div>
                        </div>
                    ` : ''}
                    ${project.reservation_fee ? `
                        <div class="flex flex-col sm:flex-row sm:items-center py-2 border-b border-blue-100 last:border-b-0">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">Reservation Fee</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-lg font-bold text-gray-800">${formatCurrency(project.reservation_fee)}</span>
                            </div>
                        </div>
                    ` : ''}
                    ${project.bank_amortization ? `
                        <div class="flex flex-col sm:flex-row sm:items-center py-2 border-b border-blue-100 last:border-b-0">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">Bank Amortization</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-lg font-bold text-gray-800">${formatCurrency(project.bank_amortization)}</span>
                            </div>
                        </div>
                    ` : ''}
                    ${project.required_salary ? `
                        <div class="flex flex-col sm:flex-row sm:items-center py-2 border-b border-blue-100 last:border-b-0">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">Required Salary</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-lg font-bold text-gray-800">${formatCurrency(project.required_salary)}</span>
                            </div>
                        </div>
                    ` : ''}
                    ${project.downpayment_percentage ? `
                        <div class="flex flex-col sm:flex-row sm:items-center py-2 border-b border-blue-100 last:border-b-0">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">Downpayment</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-lg font-bold text-gray-800">${formatPercentage(project.downpayment_percentage)}</span>
                            </div>
                        </div>
                    ` : ''}
                </div>
                
<<<<<<< HEAD
                ${(project.downpayment_amount && project.downpayment_term) ? `
                    <div class="mt-6">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Downpayment Option</h5>
                        <div class="text-lg font-medium text-gray-800">
                            ₱${parseFloat(project.downpayment_amount).toLocaleString()} - ${project.downpayment_term} months
=======
                ${(project.monthly_downpayment_3mos || project.monthly_downpayment_6mos || 
                   project.monthly_downpayment_12mos || project.monthly_downpayment_18mos) ? `
                    <div class="mt-8">
                        <h5 class="text-lg font-semibold text-gray-700 mb-4">Monthly Downpayment Options</h5>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            ${project.monthly_downpayment_3mos ? `
                                <div class="text-center p-4 bg-white rounded-lg border border-blue-200 shadow-sm">
                                    <div class="text-sm text-gray-600 mb-2">3 Months</div>
                                    <div class="text-lg font-bold text-blue-primary">${formatCurrency(project.monthly_downpayment_3mos)}</div>
                                </div>
                            ` : ''}
                            ${project.monthly_downpayment_6mos ? `
                                <div class="text-center p-4 bg-white rounded-lg border border-blue-200 shadow-sm">
                                    <div class="text-sm text-gray-600 mb-2">6 Months</div>
                                    <div class="text-lg font-bold text-blue-primary">${formatCurrency(project.monthly_downpayment_6mos)}</div>
                                </div>
                            ` : ''}
                            ${project.monthly_downpayment_12mos ? `
                                <div class="text-center p-4 bg-white rounded-lg border border-blue-200 shadow-sm">
                                    <div class="text-sm text-gray-600 mb-2">12 Months</div>
                                    <div class="text-lg font-bold text-blue-primary">${formatCurrency(project.monthly_downpayment_12mos)}</div>
                                </div>
                            ` : ''}
                            ${project.monthly_downpayment_18mos ? `
                                <div class="text-center p-4 bg-white rounded-lg border border-blue-200 shadow-sm">
                                    <div class="text-sm text-gray-600 mb-2">18 Months</div>
                                    <div class="text-lg font-bold text-blue-primary">${formatCurrency(project.monthly_downpayment_18mos)}</div>
                                </div>
                            ` : ''}
>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    return `
        <div class="flex flex-col lg:flex-row gap-8">
            <div class="lg:w-1/2">
                ${imageGallery}
            </div>
            <div class="lg:w-1/2">
                <h2 id="modal-title" class="text-3xl font-bold text-gray-800 mb-4">${project.name}</h2>
                
                <div class="flex flex-wrap gap-3 mb-6">
                    <span class="price-badge px-4 py-2 rounded-lg text-lg font-bold">
                        ${project.commission}% COMM
                    </span>
<<<<<<< HEAD
                    <span class="priority-${['high','medium','low'].includes((project.priority||'').toLowerCase()) ? project.priority.toLowerCase() : 'low'} px-4 py-2 rounded-lg text-lg font-semibold">
                        ${project.priority ? (project.priority.charAt(0).toUpperCase() + project.priority.slice(1)) : 'Low'} Priority
=======
                    <span class="priority-${project.priority} px-4 py-2 rounded-lg text-lg font-semibold">
                        ${project.priority.charAt(0).toUpperCase() + project.priority.slice(1)} Priority
>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c
                    </span>
                </div>

                <div class="mb-8">
                    <div class="text-center py-6 px-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl border border-blue-200">
                        <div class="text-4xl lg:text-5xl font-bold text-blue-primary leading-relaxed">
                            ₱${Number(project.price_min).toLocaleString()} - ₱${Number(project.price_max).toLocaleString()}
                        </div>
                        <div class="text-sm text-blue-600 mt-2 font-medium">Price Range</div>
                    </div>
                </div>

                ${financialDetails}

<<<<<<< HEAD
                <div class="bg-gray-50 rounded-2xl p-6 mb-8">
=======
                <div class="flex flex-col sm:flex-row justify-between text-sm text-gray-500 mb-8 px-2">
    <div>
        <i class="far fa-calendar-plus mr-1"></i>
        Added: ${project.created_at ? new Date(project.created_at.replace(' ', 'T')).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}
    </div>
    <div>
        <i class="far fa-clock mr-1"></i>
        Updated: ${project.updated_at ? new Date(project.updated_at.replace(' ', 'T')).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}
    </div>
</div>

<div class="bg-gray-50 rounded-2xl p-6 mb-8">
    <h4 class="text-2xl font-semibold text-gray-800 mb-6">Project Details</h4>
    ...
</div>
>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c
                    <h4 class="text-2xl font-semibold text-gray-800 mb-6">Project Details</h4>
                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row py-2 border-b border-gray-200 last:border-b-0">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">House Model</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${project.house_model || 'Not specified'}</span>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row py-2 border-b border-gray-200 last:border-b-0">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">House Type</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${project.description || 'Not specified'}</span>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row py-2 border-b border-gray-200 last:border-b-0">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">Construction Status</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${formattedStatus}</span>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row py-2 border-b border-gray-200 last:border-b-0">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">Developer</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${project.developer || 'Not specified'}</span>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row py-2">
                            <div class="w-full sm:w-48 flex-shrink-0 mb-1 sm:mb-0">
                                <span class="text-sm font-medium text-gray-600">Location</span>
                            </div>
                            <div class="flex-grow">
                                <span class="text-base text-gray-800">${project.city_name}, ${project.province_name}</span>
                                ${project.exact_location ? `<br><span class="text-sm text-gray-600">${project.exact_location}</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    ${project.drive_link ? `
                        <a href="${project.drive_link}" target="_blank" rel="noopener noreferrer"
                           class="w-full flex items-center justify-center px-6 py-3 btn-primary text-white rounded-xl text-lg font-semibold transition-all duration-300 focus-ring">
                            <i class="fab fa-google-drive mr-3"></i>
                            View on Google Drive
                        </a>
                    ` : ''}
                    
                    ${project.messenger_link ? `
                        <a href="#" data-messenger-link="${project.messenger_link}"
                           class="w-full flex items-center justify-center px-6 py-3 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-300 focus-ring">
                            <i class="fab fa-facebook-messenger mr-3"></i>
                            Contact via Messenger
                        </a>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

        // Change main image in modal
        function changeMainImage(imageSrc, index) {
            document.getElementById('main-image').src = imageSrc;
            
            document.querySelectorAll('[id^="thumb-"]').forEach((thumb, i) => {
                thumb.classList.toggle('border-blue-primary', i === index);
                thumb.classList.toggle('border-transparent', i !== index);
            });
        }

        // Close project modal
        function closeProjectModal() {
            document.getElementById('project-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput      = document.getElementById('project_search');
            const provinceSelect   = document.getElementById('province_id');
            const citySelect       = document.getElementById('city_id');
            const priceSelect      = document.getElementById('price_range');
            const commissionSelect = document.getElementById('commission');

            // Live search when typing (debounced) and on Enter / blur
            if (searchInput) {
                searchInput.addEventListener('keyup', function(e){
                    if(e.key==='Enter'){
                        performLiveSearch();
                    }
                }); // trigger on Enter
                // when leaving field
                searchInput.addEventListener('change', performLiveSearch);
            }

            // Province -> search
            if (provinceSelect) {
                provinceSelect.addEventListener('change', function () {
                    performLiveSearch();
                });
            }
            // Other dropdowns trigger search directly
            if (citySelect)       citySelect.addEventListener('change', performLiveSearch);
            if (priceSelect)      priceSelect.addEventListener('change', performLiveSearch);
            if (commissionSelect) commissionSelect.addEventListener('change', performLiveSearch);

            // Messenger modal setup
            const messengerModal       = document.getElementById('messenger-modal');
            const closeMessengerModal  = document.getElementById('close-messenger-modal');
            const copyLinkBtn          = document.getElementById('copy-link-btn');
            const messengerUrlInput    = document.getElementById('messenger-url-input');
            const copySuccess          = document.getElementById('copy-success');

            // Delegate clicks to elements carrying data-messenger-link attribute
            document.body.addEventListener('click', function(e){
                const trigger = e.target.closest('[data-messenger-link]');
                if(trigger){
                    e.preventDefault();
                    e.stopPropagation();
                    const url = trigger.getAttribute('data-messenger-link');
                    if(messengerUrlInput) messengerUrlInput.value = url || '';
                    if(messengerModal) messengerModal.classList.remove('hidden');
                }
            });

            closeMessengerModal?.addEventListener('click', () => messengerModal?.classList.add('hidden'));
            copyLinkBtn?.addEventListener('click', () => {
                if(!messengerUrlInput) return;
                navigator.clipboard.writeText(messengerUrlInput.value).then(() => {
                    if(copySuccess){
                        copySuccess.classList.remove('hidden');
                        setTimeout(()=>copySuccess.classList.add('hidden'), 2000);
                    }
                });
            });

        });

           function deleteProject() {
        if (typeof currentProjectId === 'undefined' || !currentProjectId) {
            console.error("No project ID set for deletion.");
            showNotification('Project ID missing.', 'error');
            return;
        }

        fetch(`api/delete_project.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${currentProjectId}`
        })
        .then(async (response) => {
            const text = await response.text();
            console.log("Raw response from PHP:", text);

            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error("Invalid JSON from server: " + text);
            }
        })
        .then(data => {
            if (data.success) {
                closeDeleteModal?.();
                closeProjectModal?.();

                const projectElement = document.querySelector(`[data-project-id="${currentProjectId}"]`);
                if (projectElement) {
                    projectElement.remove();
                }

                showNotification('Project successfully deleted', 'success');
                    // Reload the page after a short delay to reflect removal everywhere
                    setTimeout(() => window.location.reload(), 600);

            } else {
                throw new Error(data.error || 'Failed to delete project');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification(error.message, 'error');
        });
    }
    </script>
<script>
// -------- Layout responsiveness enhancements --------
(function () {
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
<<<<<<< HEAD
</html>
=======
</html>
>>>>>>> 3b2a244df7964cf36815ef656249ddb92d38ae3c
