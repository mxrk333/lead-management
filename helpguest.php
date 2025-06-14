<?php
session_start();

// If user is logged in, redirect to the regular help page
if (isset($_SESSION['user_id'])) {
    header("Location: help.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - Inner SPARC Realty Corporation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #dbeafe;
            --secondary: #f59e0b;
            --secondary-dark: #d97706;
            --success: #10b981;
            --danger: #ef4444;
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
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f5f8ff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--gray-800);
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('assets/images/bg3.png');
            background-size: cover;
            background-position: center;
            filter: brightness(0.4);
            z-index: -1;
        }

        .header {
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f0fd 100%);
            padding: 1.25rem 2rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 10;
            border-bottom: 1px solid rgba(79, 70, 229, 0.1);
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -30px;
            right: 10%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.1) 0%, rgba(30, 64, 175, 0) 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 30%;
            width: 200px;
            height: 20px;
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.15) 0%, rgba(30, 64, 175, 0) 100%);
            border-radius: 50%;
            z-index: 0;
        }

        .logo {
            display: flex;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.25rem;
            position: relative;
        }

        .logo-icon::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 4px;
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.3) 0%, rgba(30, 64, 175, 0) 100%);
            border-radius: 2px;
            filter: blur(2px);
        }

        .logo-text h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .logo-text p {
            font-size: 0.75rem;
            color: var(--gray-600);
            position: relative;
            padding-left: 0.5rem;
            border-left: 2px solid var(--primary-light);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            box-shadow: 0 2px 5px rgba(30, 64, 175, 0.2);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-dark) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(30, 64, 175, 0.3);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary-light);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .help-container {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-top: 2rem;
        }

        .help-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .help-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .help-header p {
            font-size: 1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        .help-content {
            padding: 2rem;
        }

        .help-section {
            margin-bottom: 2rem;
        }

        .help-section:last-child {
            margin-bottom: 0;
        }

        .help-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .help-section h3 i {
            margin-right: 0.75rem;
            color: var(--primary);
        }

        .faq-list {
            list-style: none;
        }

        .faq-item {
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 1rem;
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
            color: var(--gray-800);
            transition: all 0.2s ease;
        }

        .faq-question:hover {
            color: var(--primary);
        }

        .faq-question i {
            margin-right: 0.75rem;
            color: var(--primary);
            font-size: 0.875rem;
            transition: transform 0.2s ease;
        }

        .faq-item.active .faq-question i {
            transform: rotate(90deg);
        }

        .faq-answer {
            display: none;
            padding: 0 0 1rem 2rem;
            color: var(--gray-600);
            line-height: 1.6;
        }

        .faq-item.active .faq-answer {
            display: block;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .contact-card {
            background-color: var(--gray-50);
            border-radius: 8px;
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            transition: all 0.2s ease;
            border: 1px solid var(--gray-200);
        }

        .contact-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }

        .contact-icon {
            width: 48px;
            height: 48px;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .contact-icon i {
            font-size: 1.25rem;
        }

        .contact-info h4 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .contact-info p {
            color: var(--gray-600);
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .contact-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            font-size: 0.875rem;
        }

        .contact-link:hover {
            text-decoration: underline;
        }

        .contact-link i {
            margin-left: 0.375rem;
            font-size: 0.75rem;
        }

        .footer {
            background-color: rgba(31, 41, 55, 0.95);
            color: white;
            padding: 1.5rem 2rem;
            text-align: center;
            margin-top: auto;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-logo {
            display: flex;
            align-items: center;
        }

        .footer-logo-icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
        }

        .footer-logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .footer-logo-text {
            font-weight: 600;
            font-size: 1rem;
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-link {
            color: white;
            text-decoration: none;
            font-size: 0.875rem;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }

        .footer-link:hover {
            opacity: 1;
        }

        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
            z-index: 100;
        }

        .back-to-top.visible {
            opacity: 1;
        }

        .back-to-top:hover {
            transform: translateY(-3px);
            background: var(--primary-dark);
        }

        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }

            .main-content {
                padding: 1rem;
            }

            .help-header {
                padding: 1.5rem 1rem;
            }

            .help-content {
                padding: 1.5rem 1rem;
            }

            .contact-grid {
                grid-template-columns: 1fr;
            }

            .footer-content {
                flex-direction: column;
                gap: 1rem;
            }

            .footer-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <img src="assets/images/logo.png" alt="Inner SPARC Logo" style="width: 80px; height: 80px; object-fit: contain;">
            </div>
            <div class="logo-text">
                <h1>Inner SPARC Realty Corporation</h1>
                <p>Lead Management System</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Sign In</a>
        </div>
    </header>

    <div class="main-content">
        <div class="help-container">
            <div class="help-header">
                <h2>Help & Support</h2>
                <p>Find answers to common questions and get assistance with the Lead Management System</p>
            </div>
            
            <div class="help-content">
                <div class="help-section">
                    <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
                    <ul class="faq-list">
                        <li class="faq-item">
                            <div class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                <span>How do I access the Lead Management System?</span>
                            </div>
                            <div class="faq-answer">
                                <p>To access the Lead Management System, you need to sign in with your username and password provided by your administrator. If you don't have login credentials, please contact your team manager or system administrator.</p>
                            </div>
                        </li>
                        <li class="faq-item">
                            <div class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                <span>I forgot my password. How can I reset it?</span>
                            </div>
                            <div class="faq-answer">
                                <p>- You can reset your password by clicking the "Reset it here" link on the login page.</p>
                                <br>
                                <p>You’ll need to provide your username and the system's reset code. If you don’t have the reset code, you may need to contact the system administrator to proceed with changing your password.</p>
                            </div>
                        </li>
                        <li class="faq-item">
                            <div class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                <span>What are the system requirements for using the Lead Management System?</span>
                            </div>
                            <div class="faq-answer">
                                <p>The Lead Management System is web-based and works on most modern browsers including Chrome, Firefox, Safari, and Edge. We recommend keeping your browser updated to the latest version for the best experience. The system is also mobile-responsive and can be accessed on tablets and smartphones.</p>
                            </div>
                        </li>
                        <li class="faq-item">
                            <div class="faq-question">
                                <i class="fas fa-chevron-right"></i>
                                <span>How secure is my data in the Lead Management System?</span>
                            </div>
                            <div class="faq-answer">
                                <p>- We take data security very seriously. The system uses encrypted connections (HTTPS), secure password storage, and role-based access controls to ensure that your data is protected. Regular backups are performed to prevent data loss.</p>
                                <br>
                                <p>- If you're concerned about the privacy of your own leads, the system is capable of hiding their details. </p>
                                <br>
                                <p>When you add a lead, the admin can see your activity and that you've added a lead. However, there are limitations—the admin cannot edit or view the contact information of your leads. This means only you have access to your lead’s full details.</p>
                                <br>
                                <p>This system is designed to prevent gray areas or conflicts over lead ownership and to protect customer/client data.</p>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h3><i class="fas fa-headset"></i> Contact Support</h3>
                    <p>Need additional help? Our support team is ready to assist you.</p>
                    
                    <div class="contact-grid">
                        <div class="contact-card">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-info">
                                <h4>Email Support</h4>
                                <p>Send us an email for any technical issues or questions about the system.</p>
                                <a href="mailto:markpatigayon440@gmail.com" class="contact-link">markpatigayon440@gmail.com<i class="fas fa-external-link-alt"></i></a>
                                <a href="mailto:marveygervacio@gmail.com" class="contact-link">marveygervacio@gmail.com<i class="fas fa-external-link-alt"></i></a>
                            </div>
                        </div>
                        
                        <div class="contact-card">
                            <div class="contact-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div class="contact-info">
                                <h4>Phone Support</h4>
                                <p>Call our technical support team during business hours (9 AM - 5 PM, Monday to Friday).</p>
                                <a href="tel:+639194620030" class="contact-link">+63 919 4620 0030 <i class="fas fa-external-link-alt"></i></a>
                            </div>
                        </div>
                        
                        <div class="contact-card">
                            <div class="contact-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="contact-info">
                                <h4>User Guide</h4>
                                <p>Access the comprehensive user guide for detailed instructions on using the system.</p>
                                <p><small>(Available after login)</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">
                <div class="footer-logo-icon">
                    <img src="assets/images/logo.png" alt="Inner SPARC Logo" style="width: 80px; height: 80px; object-fit: contain;">
                </div>
                <div class="footer-logo-text">
                    Inner SPARC Realty Corporation
                </div>
            </div>
            <div class="footer-links">
                <a href="login.php" class="footer-link">Login</a>
                <a href="helpguest.php" class="footer-link">Help & Support</a>
                <a href="#" class="footer-link">Privacy Policy</a>
                <a href="#" class="footer-link">Terms of Service</a>
            </div>
        </div>
    </footer>

    <div class="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>
