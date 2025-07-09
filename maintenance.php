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

// Maintenance configuration
$maintenance_start = "2025-01-10 02:00:00";
$maintenance_end = "2025-01-15 09:00:00";
$current_time = date('Y-m-d H:i:s');
$is_maintenance_active = true; // Set to false when maintenance is complete

// Calculate time remaining
$end_timestamp = strtotime($maintenance_end);
$current_timestamp = time();
$time_remaining = $end_timestamp - $current_timestamp;

// Emergency contacts
$emergency_contacts = [
    'phone' => '(555) 123-REAL',
    'email' => 'emergency@innersparc.com',
    'after_hours' => '(555) 456-7890'
];

// Services being updated
$services_updating = [
    [
        'icon' => 'fas fa-search',
        'title' => 'Property Search',
        'description' => 'Enhanced search filters and faster results'
    ],
    [
        'icon' => 'fas fa-home',
        'title' => 'Virtual Tours',
        'description' => 'Improved 3D viewing experience'
    ],
    [
        'icon' => 'fas fa-users',
        'title' => 'Client Portal',
        'description' => 'Better communication tools'
    ],
    [
        'icon' => 'fas fa-chart-line',
        'title' => 'Market Analytics',
        'description' => 'Real-time market data and insights'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Inner SPARC Realty Corporation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        .maintenance-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            font-family: 'Inter', sans-serif;
        }
        
        .maintenance-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
        }
        
        .maintenance-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
        }
        
        .maintenance-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        }
        
        .company-logo {
            position: relative;
            z-index: 1;
            margin-bottom: 1rem;
        }
        
        .company-logo i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .company-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .maintenance-content {
            padding: 3rem 2rem;
        }
        
        .maintenance-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }
        
        .maintenance-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .maintenance-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .maintenance-description {
            font-size: 1.125rem;
            color: #6b7280;
            text-align: center;
            line-height: 1.6;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .countdown-container {
            background: linear-gradient(135deg, #fef3c7, #fbbf24);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            border: 2px solid #f59e0b;
        }
        
        .countdown-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 1rem;
        }
        
        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .countdown-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            min-width: 80px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .countdown-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            display: block;
        }
        
        .countdown-label {
            font-size: 0.875rem;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .service-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .service-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }
        
        .service-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .service-icon i {
            color: white;
            font-size: 1.25rem;
        }
        
        .service-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .service-description {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .contact-section {
            background: #f1f5f9;
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .contact-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .contact-item {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .contact-item:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
        }
        
        .contact-icon {
            font-size: 2rem;
            color: #3b82f6;
            margin-bottom: 1rem;
        }
        
        .contact-method {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .contact-info {
            color: #3b82f6;
            font-weight: 500;
            text-decoration: none;
        }
        
        .contact-info:hover {
            text-decoration: underline;
        }
        
        .progress-bar {
            background: #e5e7eb;
            border-radius: 10px;
            height: 8px;
            margin: 1rem 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #3b82f6, #1e40af);
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .footer-info {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e5e7eb;
            color: #6b7280;
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            background: #1e40af;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .maintenance-container {
                padding: 1rem;
            }
            
            .maintenance-header {
                padding: 2rem 1rem;
            }
            
            .maintenance-content {
                padding: 2rem 1rem;
            }
            
            .maintenance-title {
                font-size: 2rem;
            }
            
            .countdown-timer {
                gap: 0.5rem;
            }
            
            .countdown-item {
                min-width: 60px;
                padding: 0.75rem;
            }
            
            .countdown-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-card">
            <!-- Header -->
            <div class="maintenance-header">
                <div class="company-logo">
                    <i class="fas fa-building"></i>
                    <div class="company-name">Inner SPARC Realty Corporation</div>
                    <div style="font-size: 1rem; opacity: 0.9;">Your Trusted Real Estate Partner</div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="maintenance-content">
                <div class="maintenance-icon">
                    <i class="fas fa-tools"></i>
                </div>

                <h1 class="maintenance-title">We're Under Maintenance</h1>

                <p class="maintenance-description">
                    We're currently upgrading our systems to provide you with an even better real estate experience. 
                    Our platform will be back online soon with enhanced features and improved performance.
                </p>

                <!-- Countdown Timer -->
                <?php if ($time_remaining > 0): ?>
                <div class="countdown-container">
                    <div class="countdown-title">Expected Return Time</div>
                    <div class="countdown-timer" id="countdown">
                        <!-- Countdown will be populated by JavaScript -->
                    </div>
                    <div style="margin-top: 1rem; font-weight: 600; color: #92400e;">
                        <?php echo date('F j, Y \a\t g:i A T', strtotime($maintenance_end)); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Progress Bar -->
                <div style="text-align: center; margin: 2rem 0;">
                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.5rem;">Maintenance Progress</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 65%;"></div>
                    </div>
                    <div style="font-size: 0.875rem; color: #6b7280;">65% Complete</div>
                </div>

                <!-- Services Being Updated -->
                <div style="text-align: center; margin: 2rem 0;">
                    <h3 style="font-size: 1.5rem; font-weight: 600; color: #1f2937; margin-bottom: 1rem;">
                        What We're Improving
                    </h3>
                    <div class="services-grid">
                        <?php foreach ($services_updating as $service): ?>
                        <div class="service-item">
                            <div class="service-icon">
                                <i class="<?php echo $service['icon']; ?>"></i>
                            </div>
                            <div class="service-title"><?php echo $service['title']; ?></div>
                            <div class="service-description"><?php echo $service['description']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="contact-section">
                    <h3 class="contact-title">Need Immediate Assistance?</h3>
                    <div class="contact-grid">
                        <div class="contact-item">
                            <i class="fas fa-phone contact-icon"></i>
                            <div class="contact-method">Call Our Office</div>
                            <a href="tel:<?php echo $emergency_contacts['phone']; ?>" class="contact-info">
                                <?php echo $emergency_contacts['phone']; ?>
                            </a>
                            <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                                Mon-Fri: 9AM-6PM
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-envelope contact-icon"></i>
                            <div class="contact-method">Email Support</div>
                            <a href="mailto:<?php echo $emergency_contacts['email']; ?>" class="contact-info">
                                <?php echo $emergency_contacts['email']; ?>
                            </a>
                            <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                                Response within 2 hours
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-clock contact-icon"></i>
                            <div class="contact-method">After Hours</div>
                            <a href="tel:<?php echo $emergency_contacts['after_hours']; ?>" class="contact-info">
                                <?php echo $emergency_contacts['after_hours']; ?>
                            </a>
                            <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                                Emergency line only
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="footer-info">
                    <p>&copy; <?php echo date('Y'); ?> Inner SPARC Realty Corporation. All rights reserved.</p>
                    <p style="font-size: 0.875rem; margin-top: 0.5rem;">
                        Licensed Real Estate Brokerage | License #RE123456
                    </p>
                    
                    <div class="social-links">
                        <a href="#" class="social-link" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="social-link" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Countdown Timer
        function updateCountdown() {
            const endTime = new Date('<?php echo $maintenance_end; ?>').getTime();
            const now = new Date().getTime();
            const timeLeft = endTime - now;

            if (timeLeft > 0) {
                const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

                document.getElementById('countdown').innerHTML = `
                    <div class="countdown-item">
                        <span class="countdown-number">${days}</span>
                        <span class="countdown-label">Days</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number">${hours}</span>
                        <span class="countdown-label">Hours</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number">${minutes}</span>
                        <span class="countdown-label">Minutes</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number">${seconds}</span>
                        <span class="countdown-label">Seconds</span>
                    </div>
                `;
            } else {
                document.getElementById('countdown').innerHTML = '<div style="font-size: 1.5rem; font-weight: 600; color: #10b981;">Maintenance Complete!</div>';
            }
        }

        // Update countdown every second
        <?php if ($time_remaining > 0): ?>
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>

        // Copy email functionality
        document.querySelectorAll('a[href^="mailto:"]').forEach(emailLink => {
            emailLink.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                const email = this.href.replace('mailto:', '').split('?')[0];
                navigator.clipboard.writeText(email).then(() => {
                    // Show notification
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
                        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                    `;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                });
            });
        });

        // Auto-refresh page every 5 minutes to check if maintenance is complete
        setTimeout(() => {
            location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>