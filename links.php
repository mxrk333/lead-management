<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get database connection
$conn = getDbConnection();
if (!$conn) {
    die("Database connection failed.");
}

// Get user information
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);

// More robust role checking - check specifically for admin and manager roles
$is_admin = false;
$is_manager = false;

// Check for admin role
if (isset($user['role']) && $user['role'] === 'admin') {
    $is_admin = true;
} elseif (isset($user['user_role']) && $user['user_role'] === 'admin') {
    $is_admin = true;
} elseif (isset($user['type']) && $user['type'] === 'admin') {
    $is_admin = true;
}

// Check for manager role
if (isset($user['role']) && $user['role'] === 'manager') {
    $is_manager = true;
} elseif (isset($user['user_role']) && $user['user_role'] === 'manager') {
    $is_manager = true;
} elseif (isset($user['type']) && $user['type'] === 'manager') {
    $is_manager = true;
}

// Define the links
$links = [
        [
        'title' => 'VAST Training',
        'description' => 'A focused training program that sharpens sales, negotiation, and client service skills for real estate professionals.',
        'url' => 'https://classroom.google.com/c/NzY3MTcwNjQ1NTY5?cjc=lzv6j6du',
        'icon' => 'fas fa-laptop-code',
        'color' => '#f59e0b',
        'admin_only' => false,
        'manager_only' => false,
        'category' => 'resources'
        ],
    [
        'title' => 'Linktree',
        'description' => 'Access all our important links in one place',
        'url' => 'https://linktr.ee/rxcinnersparc?fbclid=IwY2xjawKnzmBleHRuA2FlbQIxMQABHkMv0C4xjnndO4fbn1fA1GIcPGpGc_9Lpk6h9FRke1xB4bP-qX5xpCU7B5EM_aem_z2IxzpMHw-7TbnzpxABDIg',
        'icon' => 'fas fa-link',
        'color' => '#4361ee',
        'admin_only' => true,
        'manager_only' => false,
        'category' => 'social'
    ],
    [
        'title' => 'YouTube Channel',
        'description' => 'Watch our training videos and property showcases',
        'url' => 'https://www.youtube.com/@InnerSPARCRealtyCorporation-01',
        'icon' => 'fa-brands fa-youtube',
        'color' => '#ef4444',
        'admin_only' => false,
        'manager_only' => false,
        'category' => 'social'
    ],
    [
        'title' => 'Inner SPARC — Asenso tayo!',
        'description' => 'Join our community discussions and updates',
        'url' => 'https://www.facebook.com/groups/228598320579630',
        'icon' => 'fa-brands fa-facebook',
        'color' => '#3b82f6',
        'admin_only' => false,
        'manager_only' => false,
        'category' => 'social'
    ],
    [
        'title' => 'Company Website',
        'description' => 'Visit our official website for more information',
        'url' => 'https://innersparcrealty.com/',
        'icon' => 'fas fa-globe',
        'color' => '#10b981',
        'admin_only' => false,
        'manager_only' => false,
        'category' => 'resources'
    ],
    [
        'title' => 'Lead Management Spreadsheet Manual',
        'description' => 'Download the manual for managing leads in spreadsheets',
        'url' => 'https://drive.google.com/drive/folders/1xPYW_kvak1hPA-62mR0dWRaH1TOtg7iK?usp=sharing',
        'icon' => 'fas fa-file-pdf',
        'color' => '#f59e0b',
        'admin_only' => false,
        'manager_only' => false,
        'category' => 'tools'
    ],
    [
        'title' => '2025 Daily Sales Template DST',
        'description' => 'Access the daily sales template for tracking and reporting sales activities',
        'url' => 'https://docs.google.com/forms/d/e/1FAIpQLSfwpTXPpa5CHuPPBRQfqW4i8MzNUC_8OO5SqZg53e4kPis2hA/viewform',
        'icon' => 'fas fa-chart-line',
        'color' => '#10b981',
        'admin_only' => false,
        'manager_only' => false,
        'category' => 'tools'
    ],
    [
        'title' => 'Agent Accreditation - Inner SPARC Realty Corporation',
        'description' => 'Access the agent accreditation form and process for Inner SPARC Realty',
        'url' => 'https://docs.google.com/forms/d/e/1FAIpQLSdfhF4c4d40hJ92_Ka7zfHQBOrmkzBTv7EVsyhap4csoyY7OA/viewform',
        'icon' => 'fas fa-user-check',
        'color' => '#3b82f6',
        'admin_only' => true,
        'manager_only' => true,
        'category' => 'tools'
    ],
    [
        'title' => 'AGENT DETAILS 2025 FORM',
        'description' => 'Access and update agent details and information for 2025',
        'url' => 'https://docs.google.com/forms/d/e/1FAIpQLSf9YoGnGNVcEZwBgJJYuQCDMDijXfwdUrK9GXfJIc293yHFyQ/viewform',
        'icon' => 'fas fa-id-card',
        'color' => '#8b5cf6',
        'admin_only' => false,
        'manager_only' => false,
        'category' => 'tools'
    ],
    [
        'title' => 'Pre-assessment Form Responder',
        'description' => 'Access the pre-assessment form responses and evaluation tools',
        'url' => 'https://docs.google.com/forms/d/e/1FAIpQLSdfhF4c4d40hJ92_Ka7zfHQBOrmkzBTv7EVsyhap4csoyY7OA/viewform',
        'icon' => 'fas fa-clipboard-check',
        'color' => '#ec4899',
        'admin_only' => true,
        'manager_only' => true,
        'category' => 'tools'
    ],
    [
        'title' => 'Project DIRECTORY - COMM RATE - COMM TRANCHE',
        'description' => 'Access the project directory with commission rates and tranches',
        'url' => 'https://docs.google.com/spreadsheets/d/1mKGQ73gNGLFtIQN6622pAxuG_Vx1NOGO15M3myG_Q3E/edit?gid=1228342976#gid=1228342976',
        'icon' => 'fas fa-project-diagram',
        'color' => '#f59e0b',
        'admin_only' => true,
        'manager_only' => true,
        'category' => 'tools'
    ],
    [
        'title' => 'Real Estate Related Webinars',
        'description' => 'A series of online seminars focused on real estate trends, strategies, tools, and best practices—designed to help agents, investors, and professionals stay informed and grow their expertise.',
        'url' => 'https://classroom.google.com/c/Nzc3Njg1MzQwMTA2?cjc=mtj6iut6',
        'icon' => 'fas fa-laptop-code',
        'color' => '#f59e0b',
        'admin_only' => false,
        'manager_only' => false,
        'category' => 'resources'
    ],

];

// Filter out admin-only and manager-only links for non-admin/non-manager users
$filtered_links = [];
foreach ($links as $link) {
    // Check if link should be shown based on user role
    $show_link = true;
    
    // If link is admin-only and user is not admin, don't show
    if ($link['admin_only'] && !$is_admin) {
        $show_link = false;
    }
    
    // If link is manager-only and user is not manager or admin, don't show
    if ($link['manager_only'] && !$is_manager && !$is_admin) {
        $show_link = false;
    }
    
    if ($show_link) {
        $filtered_links[] = $link;
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Important Links - Inner SPARC Realty</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Updated Font Awesome to include all styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
<style>
    :root {
        --primary: #4361ee;
        --primary-light: rgba(67, 97, 238, 0.1);
        --primary-dark: #3a56d4;
        --secondary: #f8f9fc;
        --success: #10b981;
        --success-light: rgba(16, 185, 129, 0.1);
        --danger: #ef4444;
        --danger-light: rgba(239, 68, 68, 0.1);
        --warning: #f59e0b;
        --warning-light: rgba(245, 158, 11, 0.1);
        --info: #3b82f6;
        --info-light: rgba(59, 130, 246, 0.1);
        --dark: #1f2937;
        --gray: #6b7280;
        --gray-light: #e5e7eb;
        --white: #ffffff;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --radius-sm: 0.25rem;
        --radius: 0.5rem;
        --radius-lg: 0.75rem;
        --transition: all 0.2s ease-in-out;
    }

    /* General Styles */
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: #f3f4f6;
        color: var(--dark);
        line-height: 1.5;
        margin: 0;
    }

    /* Links Page */
    .links-page {
        flex: 1;
        padding: 1.5rem;
        width: 100%;
        margin: 0;
        min-height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
    }

    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--gray-light);
    }

    .page-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .page-title i {
        color: var(--primary);
    }

    /* Links Grid */
    .links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .link-card {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .link-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }

    .link-header {
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        border-bottom: 1px solid var(--gray-light);
    }

    .link-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .link-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
    }

    .link-body {
        padding: 1.5rem;
        flex: 1;
    }

    .link-description {
        margin: 0 0 1.5rem 0;
        color: var(--gray);
    }

    .link-footer {
        padding: 1rem 1.5rem;
        background-color: var(--secondary);
        border-top: 1px solid var(--gray-light);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius);
        font-weight: 500;
        font-size: 0.875rem;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        border: none;
        width: 100%;
    }

    .btn-primary {
        background-color: var(--primary);
        color: var(--white);
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
    }

    /* Featured Link */
    .featured-link {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .featured-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(to right, var(--primary), var(--info));
    }

    .featured-content {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .featured-header {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .featured-icon {
        width: 4rem;
        height: 4rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        background-color: var(--primary-light);
        color: var(--primary);
    }

    .featured-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--dark);
    }

    .featured-description {
        font-size: 1.125rem;
        color: var(--gray);
        max-width: 600px;
    }

    .featured-actions {
        display: flex;
        gap: 1rem;
    }

    .featured-actions .btn {
        width: auto;
    }

    /* Search */
    .search-container {
        margin-bottom: 2rem;
    }

    .search-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--gray-light);
        border-radius: var(--radius);
        font-size: 1rem;
        transition: var(--transition);
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px var(--primary-light);
    }

    /* Categories */
    .category-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
    }

    .category-tab {
        padding: 0.5rem 1rem;
        border-radius: var(--radius);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
        background-color: var(--secondary);
        color: var(--gray);
        border: none;
    }

    .category-tab.active {
        background-color: var(--primary);
        color: var(--white);
    }

    .category-tab:hover:not(.active) {
        background-color: var(--gray-light);
    }

    /* No Results */
    .no-results {
        text-align: center;
        padding: 3rem;
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
    }

    .no-results i {
        font-size: 3rem;
        color: var(--gray);
        margin-bottom: 1rem;
    }

    .no-results h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.25rem;
        color: var(--dark);
    }

    .no-results p {
        margin: 0;
        color: var(--gray);
    }

    /* Admin Badge */
    .admin-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        background-color: var(--warning-light);
        color: var(--warning);
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: auto;
    }
    
    /* Manager Badge */
    .manager-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        background-color: var(--info-light);
        color: var(--info);
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .links-grid {
            grid-template-columns: 1fr;
        }

        .featured-content {
            flex-direction: column;
        }

        .featured-actions {
            flex-direction: column;
        }
    }

    /* Animation */
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

    .link-card {
        animation: fadeIn 0.3s ease-out forwards;
    }

    .link-card:nth-child(1) { animation-delay: 0.1s; }
    .link-card:nth-child(2) { animation-delay: 0.2s; }
    .link-card:nth-child(3) { animation-delay: 0.3s; }
    .link-card:nth-child(4) { animation-delay: 0.4s; }
    .link-card:nth-child(5) { animation-delay: 0.5s; }

    /* Role Access Banner */
    .role-access-banner {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        border-left: 4px solid;
        border-color: <?php echo $is_admin ? 'var(--warning)' : 'var(--info)'; ?>;
    }

    .role-access-content {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .role-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        background-color: <?php echo $is_admin ? 'var(--warning-light)' : 'var(--info-light)'; ?>;
        color: <?php echo $is_admin ? 'var(--warning)' : 'var(--info)'; ?>;
    }

    .role-info h3 {
        margin: 0 0 0.25rem 0;
        font-size: 1rem;
        font-weight: 600;
        color: <?php echo $is_admin ? 'var(--warning)' : 'var(--info)'; ?>;
    }

    .role-info p {
        margin: 0;
        font-size: 0.875rem;
        color: var(--gray);
    }
</style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="links-page">
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-link"></i> Important Links
                    </h1>
                </div>
                
                <!-- Role Access Banner -->
                <?php if ($is_manager || $is_admin): ?>
                <div class="role-access-banner">
                    <div class="role-access-content">
                        <div class="role-icon">
                            <?php if ($is_admin): ?>
                                <i class="fas fa-shield-alt"></i>
                            <?php elseif ($is_manager): ?>
                                <i class="fas fa-user-tie"></i>
                            <?php endif; ?>
                        </div>
                        <div class="role-info">
                            <h3>
                                <?php if ($is_admin): ?>
                                    Admin Access
                                <?php elseif ($is_manager): ?>
                                    Manager Access
                                <?php endif; ?>
                            </h3>
                            <p>You have access to restricted links and resources.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Featured Link -->
                <div class="featured-link">
                    <div class="featured-content">
                        <div class="featured-header">
                            <div class="featured-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <h2 class="featured-title">Welcome to Inner SPARC Realty Resources</h2>
                        </div>
                        <p class="featured-description">
                            Access all the important links and resources you need for your work at Inner SPARC Realty. 
                            Bookmark this page for quick access to all essential tools and information.
                            <?php if ($is_admin): ?>
                                <span class="admin-badge"><i class="fas fa-shield-alt"></i> Admin Access Enabled</span>
                            <?php endif; ?>
                            <?php if ($is_manager): ?>
                                <span class="manager-badge"><i class="fas fa-user-tie"></i> Manager Access Enabled</span>
                            <?php endif; ?>
                        </p>
                        <div class="featured-actions">
                            <a href="#all-links" class="btn btn-primary">
                                <i class="fas fa-link"></i> View All Links
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Search -->
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search for links..." onkeyup="filterLinks()">
                </div>
                
                <!-- Categories -->
                <div class="category-tabs">
                    <button class="category-tab active" onclick="filterCategory('all')">All</button>
                    <button class="category-tab" onclick="filterCategory('social')">Social Media</button>
                    <button class="category-tab" onclick="filterCategory('tools')">Tools</button>
                    <button class="category-tab" onclick="filterCategory('resources')">Resources</button>
                    <?php if ($is_admin || $is_manager): ?>
                    <button class="category-tab" onclick="filterCategory('restricted')">Restricted Access</button>
                    <?php endif; ?>
                </div>
                
                <!-- Links Grid -->
                <div id="all-links" class="links-grid">
                    <?php foreach ($filtered_links as $index => $link): ?>
                        <?php 
                            // Determine restricted category for filtering
                            $restricted = '';
                            if ($link['admin_only'] || $link['manager_only']) {
                                $restricted = 'restricted';
                            }
                        ?>
                        <div class="link-card" data-category="<?php echo $link['category'] . ' ' . $restricted; ?>">
                            <div class="link-header">
                                <div class="link-icon" style="background-color: <?php echo $link['color']; ?>20; color: <?php echo $link['color']; ?>">
                                    <i class="<?php echo $link['icon']; ?>"></i>
                                </div>
                                <h3 class="link-title">
                                    <?php echo htmlspecialchars($link['title']); ?>
                                    <?php if ($link['admin_only']): ?>
                                        <span class="admin-badge"><i class="fas fa-lock"></i> Admin Only</span>
                                    <?php elseif ($link['manager_only']): ?>
                                        <span class="manager-badge"><i class="fas fa-user-tie"></i> Manager Access</span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <div class="link-body">
                                <p class="link-description"><?php echo htmlspecialchars($link['description']); ?></p>
                            </div>
                            <div class="link-footer">
                                <a href="<?php echo $link['url']; ?>" target="_blank" class="btn btn-primary" style="background-color: <?php echo $link['color']; ?>">
                                    <i class="fas fa-external-link-alt"></i> Open Link
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- No Results (hidden by default) -->
                <div id="noResults" class="no-results" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h3>No Links Found</h3>
                    <p>We couldn't find any links matching your search. Try different keywords or clear your search.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to filter links based on search input
        function filterLinks() {
            const searchInput = document.getElementById('searchInput');
            const filter = searchInput.value.toLowerCase();
            const linkCards = document.querySelectorAll('.link-card');
            const noResults = document.getElementById('noResults');
            
            let resultsFound = false;
            
            linkCards.forEach(card => {
                const title = card.querySelector('.link-title').textContent.toLowerCase();
                const description = card.querySelector('.link-description').textContent.toLowerCase();
                
                if (title.includes(filter) || description.includes(filter)) {
                    card.style.display = '';
                    resultsFound = true;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            noResults.style.display = resultsFound ? 'none' : 'block';
        }
        
        // Function to filter links by category
        function filterCategory(category) {
            // Update active tab
            const tabs = document.querySelectorAll('.category-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.textContent.toLowerCase().includes(category) || 
                    (category === 'all' && tab.textContent.toLowerCase() === 'all')) {
                    tab.classList.add('active');
                }
            });
            
            // Filter cards
            const linkCards = document.querySelectorAll('.link-card');
            const noResults = document.getElementById('noResults');
            
            let resultsFound = false;
            
            linkCards.forEach(card => {
                if (category === 'all' || card.dataset.category.includes(category)) {
                    card.style.display = '';
                    resultsFound = true;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            noResults.style.display = resultsFound ? 'none' : 'block';
            
            // Clear search input
            document.getElementById('searchInput').value = '';
        }
    </script>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
