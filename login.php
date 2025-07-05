<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Validate login credentials
    $user = validateLogin($username, $password);
    
    if ($user) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect to dashboard
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inner SPARC Realty Corporation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* COMPLETELY HIDE ALL SCROLLBARS */
        ::-webkit-scrollbar {
            display: none;
        }

        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        body {
            background-color: #f5f8ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        body::before {
            content: '';
            position: fixed;
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

        .login-container {
            width: 100%;
            max-width: 1000px;
            max-width: min(1000px, 95vw);
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            display: flex;
            position: relative;
            min-height: 0;
            max-height: 95vh;
            overflow-y: auto;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-image {
            flex: 1;
            background-image: url('assets/images/bgfinal.png');
            background-size: 700px;
            background-position: center;
            position: relative;
            display: none;
            min-width: 300px;
        }

        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(33, 19, 232, 0.67) 0%, rgba(190, 114, 32, 0.62) 100%);
            opacity: 0.8;
        }

        .login-image-content {
            position: absolute;
            bottom: 2rem;
            left: 2rem;
            right: 2rem;
            color: white;
        }

        .login-image-content h2 {
            font-size: 1.75rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .login-image-content p {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        .login-form-container {
            flex: 1;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            min-width: 350px;
            scroll-behavior: smooth;
        }

        .login-logo {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }

        .login-logo-icon {
            width: 50px;
            height: 50px;
            background-color: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            box-shadow: var(--shadow);
        }

        .login-logo-icon i {
            font-size: 1.75rem;
            color: white;
        }

        .login-logo-text h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.2;
        }

        .login-logo-text p {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .login-header {
            margin-bottom: 2rem;
        }

        .login-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .error-message {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            border-left: 4px solid var(--danger);
        }

        .error-message i {
            margin-right: 0.5rem;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            color: var(--gray-800);
            background-color: white;
            transition: all 0.2s ease;
        }

        .form-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .form-group i.input-icon {
            position: absolute;
            left: 1rem;
            top: 2.5rem;
            color: var(--gray-500);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 2.5rem;
            color: var(--gray-500);
            cursor: pointer;
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .btn-login {
            display: block;
            width: 100%;
            padding: 1rem 1.5rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow);
            margin-top: 1rem;
        }

        .btn-login:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            margin-top: 1.5rem;
            text-align: center;
            color: var(--gray-600);
            font-size: 0.875rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .login-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .login-footer a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .report-problem {
            text-align: center;
            margin-top: 1rem;
        }

        .report-problem a {
            color: var(--danger);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            transition: color 0.2s ease;
            cursor: pointer;
        }

        .report-problem a:hover {
            color: var(--secondary-dark);
            text-decoration: underline;
        }

        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2rem;
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .secure-badge i {
            margin-right: 0.375rem;
            color: var(--success);
        }

        .real-estate-features {
            display: flex;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .feature-item {
            flex: 1;
            min-width: 120px;
            text-align: center;
            padding: 1rem 0.5rem;
        }

        .feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            margin-bottom: 0.5rem;
        }

        .feature-icon i {
            font-size: 1.25rem;
        }

        .feature-text {
            font-size: 0.75rem;
            color: var(--gray-600);
            line-height: 1.4;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            animation: fadeIn 0.3s ease-out;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-content {
            background-color: white;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease-out;
            position: relative;
        }

        .modal-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 16px 16px 0 0;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        .modal-header h3 i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }

        .close {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.75rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }

        .close:hover {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .modal-form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .modal-form-group label .required {
            color: var(--danger);
            margin-left: 0.25rem;
        }

        .modal-form-group input,
        .modal-form-group select,
        .modal-form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--gray-800);
            background-color: white;
            transition: all 0.2s ease;
        }

        .modal-form-group input:focus,
        .modal-form-group select:focus,
        .modal-form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .modal-form-group textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .modal-form-group .input-with-icon {
            position: relative;
        }

        .modal-form-group .input-with-icon input {
            padding-left: 2.75rem;
        }

        .modal-form-group .input-with-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
        }

        .priority-selector {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .priority-option {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .priority-option.low {
            color: var(--success);
        }

        .priority-option.medium {
            color: var(--secondary);
        }

        .priority-option.high {
            color: var(--danger);
        }

        .priority-option.selected {
            border-color: currentColor;
            background-color: rgba(30, 64, 175, 0.05);
        }

        .modal-footer {
            padding: 1.5rem 2rem 2rem 2rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .btn-secondary {
            padding: 0.75rem 1.5rem;
            background-color: var(--gray-200);
            color: var(--gray-700);
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background-color: var(--gray-300);
            transform: translateY(-1px);
        }

        .btn-primary {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .success-message {
            background-color: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            border-left: 4px solid var(--success);
        }

        .success-message i {
            margin-right: 0.5rem;
            font-size: 1rem;
        }

        /* Custom Scroll Indicator */
        .scroll-hint {
            position: fixed;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
            pointer-events: none;
        }

        .scroll-hint.visible {
            opacity: 0.6;
        }

        .scroll-hint.visible:hover {
            opacity: 1;
        }

        .scroll-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--gray-400);
            margin: 3px 0;
            transition: all 0.3s ease;
        }

        .scroll-dot.active {
            background-color: var(--primary);
            transform: scale(1.2);
            box-shadow: 0 0 10px rgba(30, 64, 175, 0.5);
        }

        .login-container {
            -webkit-overflow-scrolling: touch;
        }

        /* Responsive Design */
        @media (min-width: 768px) {
            .login-image {
                display: block;
            }
        }

        @media (max-width: 1024px) {
            .login-container {
                max-width: 90vw;
            }
            
            .login-form-container {
                min-width: 300px;
                padding: 2rem;
            }
            
            .login-image {
                min-width: 250px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 0.5rem;
                align-items: flex-start;
                padding-top: 1rem;
                padding-bottom: 1rem;
            }
            
            .login-container {
                max-width: 100%;
                max-height: none;
                min-height: auto;
            }
            
            .login-form-container {
                min-width: auto;
                padding: 1.5rem;
            }

            .scroll-hint {
                display: none;
            }

            .modal-content {
                max-width: 95vw;
                margin: 1rem;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 1.5rem;
            }

            .priority-selector {
                flex-direction: column;
            }
        }

        @media (max-width: 640px) {
            .login-form-container {
                padding: 1.5rem;
            }

            .login-logo {
                margin-bottom: 1.5rem;
                flex-direction: column;
                text-align: center;
            }
            
            .login-logo-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .login-header {
                margin-bottom: 1.5rem;
                text-align: center;
            }

            .real-estate-features {
                display: none;
            }
            
            .login-footer {
                flex-direction: column;
                gap: 0.75rem;
                text-align: center;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer .btn-secondary,
            .modal-footer .btn-primary {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 0.25rem;
            }
            
            .login-container {
                border-radius: 8px;
            }
            
            .login-form-container {
                padding: 1rem;
            }
            
            .login-logo-text h1 {
                font-size: 1.25rem;
            }
            
            .login-header h2 {
                font-size: 1.25rem;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 1rem;
            }
        }

        @media (max-height: 700px) {
            body {
                align-items: flex-start;
                padding-top: 1rem;
                padding-bottom: 1rem;
            }
            
            .login-container {
                max-height: 95vh;
                overflow-y: auto;
            }
            
            .login-form-container {
                padding: 1.5rem;
            }
            
            .login-logo {
                margin-bottom: 1rem;
            }
            
            .login-header {
                margin-bottom: 1rem;
            }
            
            .form-group {
                margin-bottom: 1rem;
            }
            
            .real-estate-features {
                margin-top: 1rem;
            }
            
            .secure-badge {
                margin-top: 1rem;
            }
        }

        @media (max-height: 600px) {
            .login-form-container {
                padding: 1rem;
            }
            
            .real-estate-features {
                display: none;
            }
        }

        @media (max-height: 500px) and (orientation: landscape) {
            body {
                align-items: flex-start;
                padding: 0.5rem;
            }
            
            .login-container {
                max-height: 95vh;
            }
            
            .login-logo {
                flex-direction: row;
                margin-bottom: 0.5rem;
            }
            
            .login-logo-icon {
                margin-right: 1rem;
                margin-bottom: 0;
                width: 40px;
                height: 40px;
            }
            
            .login-logo-text h1 {
                font-size: 1.125rem;
            }
            
            .login-header {
                margin-bottom: 1rem;
            }
            
            .login-header h2 {
                font-size: 1.125rem;
            }
            
            .form-group {
                margin-bottom: 0.75rem;
            }
            
            .secure-badge,
            .real-estate-features {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Elegant Scroll Indicator -->
    <div class="scroll-hint" id="scrollHint">
        <div class="scroll-dot" data-section="0"></div>
        <div class="scroll-dot" data-section="1"></div>
        <div class="scroll-dot" data-section="2"></div>
    </div>

    <div class="login-container">
        <div class="login-image">
            <div class="login-image-content">
                <h2>Welcome to Inner SPARC Realty Corporation</h2>
                <p>Access your lead management system to track clients, monitor sales progress, and grow your real estate business.</p>
            </div>
        </div>
        
        <div class="login-form-container">
            <div class="login-logo">
                <div class="login-logo-icon">
                    <i class="fas fa-home"></i>
                </div>
                <div class="login-logo-text">
                    <h1>Inner SPARC Realty Corporation</h1>
                </div>
            </div>
            
            <div class="login-header">
                <h2>Sign in to your account</h2>
                <p>Enter your credentials to access the Lead Monitoring System</p>
            </div>
            
            <form method="POST" action="login.php">
                <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
                
                <button type="submit" class="btn-login">
                    Sign In <i class="fas fa-sign-in-alt" style="margin-left: 0.5rem;"></i>
                </button>
                
                <div class="login-footer">
                    <p>Forgot your password? <a href="reset-password.php">Reset it here</a></p>
                    <p><a href="helpguest.php"><i class="fas fa-question-circle"></i> Help & support</a></p>
                </div>

                <div class="report-problem">
                    <p><a href="#" onclick="openReportModal()"><i class="fas fa-exclamation-triangle"></i> Report a problem</a></p>
                </div>
                
                <div class="secure-badge">
                    <i class="fas fa-shield-alt"></i> Secure Login
                </div>
                
                <div class="real-estate-features">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="feature-text">Lead Tracking</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="feature-text">Client Management</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="feature-text">Property Listings</div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Problem Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-bug"></i>Report a Problem</h3>
                <span class="close" onclick="closeReportModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="successMessage" class="success-message" style="display: none;">
                    <i class="fas fa-check-circle"></i>
                    Thank you! Your problem report has been submitted successfully. We'll investigate and get back to you soon.
                </div>
                
                <form id="reportForm">
                    <div class="modal-form-group">
                        <label for="report-username">Username <span class="required">*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="report-username" name="username" placeholder="Enter your username" required>
                        </div>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="report-phone">Phone Number <span class="required">*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="report-phone" name="phone" placeholder="+1 (555) 123-4567" required>
                        </div>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="report-email">Email Address (Optional)</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="report-email" name="email" placeholder="your.email@example.com">
                        </div>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="issue-type">Issue Type <span class="required">*</span></label>
                        <select id="issue-type" name="issue_type" required>
                            <option value="">Select issue type</option>
                            <option value="login-failed">Cannot sign in</option>
                            <option value="forgot-password">Password reset issues</option>
                            <option value="account-locked">Account locked/suspended</option>
                            <option value="page-error">Page not loading properly</option>
                            <option value="performance">Slow performance</option>
                            <option value="feature-bug">Feature not working</option>
                            <option value="data-issue">Data/information incorrect</option>
                            <option value="security-concern">Security concern</option>
                            <option value="other">Other technical issue</option>
                        </select>
                    </div>
                    
                    <div class="modal-form-group">
                        <label>Priority Level</label>
                        <div class="priority-selector">
                            <div class="priority-option low" data-priority="low">
                                <i class="fas fa-circle"></i> Low
                            </div>
                            <div class="priority-option medium selected" data-priority="medium">
                                <i class="fas fa-circle"></i> Medium
                            </div>
                            <div class="priority-option high" data-priority="high">
                                <i class="fas fa-circle"></i> High
                            </div>
                        </div>
                        <input type="hidden" id="priority" name="priority" value="medium">
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="problem-description">Problem Description <span class="required">*</span></label>
                        <textarea id="problem-description" name="description" placeholder="Please describe the problem you're experiencing in detail. Include any error messages, steps you took, and when the issue occurred..." required></textarea>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="browser-info">Browser & System Information</label>
                        <input type="text" id="browser-info" name="browser_info" readonly>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeReportModal()">Cancel</button>
                <button type="submit" form="reportForm" class="btn-primary" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Submit Report
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordField = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });

            // Elegant scroll indicator
            const scrollHint = document.getElementById('scrollHint');
            const loginContainer = document.querySelector('.login-container');
            const scrollDots = document.querySelectorAll('.scroll-dot');

            function updateScrollIndicator() {
                if (loginContainer.scrollHeight > loginContainer.clientHeight) {
                    const scrollTop = loginContainer.scrollTop;
                    const scrollHeight = loginContainer.scrollHeight - loginContainer.clientHeight;
                    const scrollPercentage = scrollTop / scrollHeight;
                    
                    scrollHint.classList.add('visible');
                    
                    scrollDots.forEach((dot, index) => {
                        dot.classList.remove('active');
                        const sectionStart = index / scrollDots.length;
                        const sectionEnd = (index + 1) / scrollDots.length;
                        
                        if (scrollPercentage >= sectionStart && scrollPercentage < sectionEnd) {
                            dot.classList.add('active');
                        }
                    });
                    
                    if (scrollPercentage >= 0.9) {
                        scrollDots[scrollDots.length - 1].classList.add('active');
                    }
                } else {
                    scrollHint.classList.remove('visible');
                }
            }

            loginContainer.addEventListener('scroll', updateScrollIndicator);
            window.addEventListener('scroll', updateScrollIndicator);
            updateScrollIndicator();

            // Enhanced Browser Detection Function
            function getDetailedBrowserInfo() {
                const userAgent = navigator.userAgent;
                const platform = navigator.platform;
                const language = navigator.language;
                const cookieEnabled = navigator.cookieEnabled;
                const onLine = navigator.onLine;
                
                let browserName = 'Unknown Browser';
                let browserVersion = 'Unknown Version';
                let osName = 'Unknown OS';
                let osVersion = '';
                let architecture = '';

                // Detect Operating System
                if (userAgent.indexOf('Windows NT 10.0') !== -1) {
                    osName = 'Windows 10/11';
                } else if (userAgent.indexOf('Windows NT 6.3') !== -1) {
                    osName = 'Windows 8.1';
                } else if (userAgent.indexOf('Windows NT 6.2') !== -1) {
                    osName = 'Windows 8';
                } else if (userAgent.indexOf('Windows NT 6.1') !== -1) {
                    osName = 'Windows 7';
                } else if (userAgent.indexOf('Windows NT 6.0') !== -1) {
                    osName = 'Windows Vista';
                } else if (userAgent.indexOf('Windows NT 5.1') !== -1) {
                    osName = 'Windows XP';
                } else if (userAgent.indexOf('Windows') !== -1) {
                    osName = 'Windows';
                } else if (userAgent.indexOf('Mac OS X') !== -1) {
                    osName = 'macOS';
                    const macVersion = userAgent.match(/Mac OS X ([0-9_]+)/);
                    if (macVersion) {
                        osVersion = macVersion[1].replace(/_/g, '.');
                    }
                } else if (userAgent.indexOf('Linux') !== -1) {
                    osName = 'Linux';
                } else if (userAgent.indexOf('Android') !== -1) {
                    osName = 'Android';
                } else if (userAgent.indexOf('iPhone') !== -1 || userAgent.indexOf('iPad') !== -1) {
                    osName = 'iOS';
                }

                // Detect Architecture
                if (userAgent.indexOf('WOW64') !== -1 || userAgent.indexOf('Win64') !== -1 || userAgent.indexOf('x64') !== -1) {
                    architecture = '64-bit';
                } else if (userAgent.indexOf('Win32') !== -1 || userAgent.indexOf('x86') !== -1) {
                    architecture = '32-bit';
                }

                // Detect Browser (Order matters!)
                if (userAgent.indexOf('Edg/') !== -1) {
                    // Microsoft Edge (Chromium-based)
                    browserName = 'Microsoft Edge';
                    const edgeVersion = userAgent.match(/Edg\/([0-9.]+)/);
                    if (edgeVersion) browserVersion = edgeVersion[1];
                } else if (userAgent.indexOf('Edge/') !== -1) {
                    // Microsoft Edge (Legacy)
                    browserName = 'Microsoft Edge (Legacy)';
                    const edgeVersion = userAgent.match(/Edge\/([0-9.]+)/);
                    if (edgeVersion) browserVersion = edgeVersion[1];
                } else if (userAgent.indexOf('OPR/') !== -1 || userAgent.indexOf('Opera/') !== -1) {
                    // Opera
                    browserName = 'Opera';
                    const operaVersion = userAgent.match(/(?:OPR|Opera)\/([0-9.]+)/);
                    if (operaVersion) browserVersion = operaVersion[1];
                } else if (userAgent.indexOf('Chrome/') !== -1 && userAgent.indexOf('Safari/') !== -1) {
                    // Google Chrome
                    browserName = 'Google Chrome';
                    const chromeVersion = userAgent.match(/Chrome\/([0-9.]+)/);
                    if (chromeVersion) browserVersion = chromeVersion[1];
                } else if (userAgent.indexOf('Firefox/') !== -1) {
                    // Mozilla Firefox
                    browserName = 'Mozilla Firefox';
                    const firefoxVersion = userAgent.match(/Firefox\/([0-9.]+)/);
                    if (firefoxVersion) browserVersion = firefoxVersion[1];
                } else if (userAgent.indexOf('Safari/') !== -1 && userAgent.indexOf('Chrome') === -1) {
                    // Safari
                    browserName = 'Safari';
                    const safariVersion = userAgent.match(/Version\/([0-9.]+)/);
                    if (safariVersion) browserVersion = safariVersion[1];
                } else if (userAgent.indexOf('MSIE') !== -1 || userAgent.indexOf('Trident/') !== -1) {
                    // Internet Explorer
                    browserName = 'Internet Explorer';
                    const ieVersion = userAgent.match(/(?:MSIE |rv:)([0-9.]+)/);
                    if (ieVersion) browserVersion = ieVersion[1];
                }

                // Get additional system info
                const screenInfo = `${screen.width}x${screen.height}`;
                const colorDepth = screen.colorDepth;
                const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                const memory = navigator.deviceMemory ? `${navigator.deviceMemory}GB RAM` : 'Unknown RAM';
                const cores = navigator.hardwareConcurrency ? `${navigator.hardwareConcurrency} cores` : 'Unknown cores';

                // Build comprehensive info string
                let systemInfo = `${browserName} ${browserVersion}`;
                systemInfo += ` | ${osName}`;
                if (osVersion) systemInfo += ` ${osVersion}`;
                if (architecture) systemInfo += ` (${architecture})`;
                systemInfo += ` | Screen: ${screenInfo}`;
                systemInfo += ` | ${colorDepth}-bit color`;
                systemInfo += ` | ${cores}`;
                if (navigator.deviceMemory) systemInfo += ` | ${memory}`;
                systemInfo += ` | ${language}`;
                systemInfo += ` | ${timezone}`;
                systemInfo += ` | Cookies: ${cookieEnabled ? 'Enabled' : 'Disabled'}`;
                systemInfo += ` | Online: ${onLine ? 'Yes' : 'No'}`;

                return systemInfo;
            }

            // Auto-fill browser information with enhanced detection
            const browserInfo = document.getElementById('browser-info');
            if (browserInfo) {
                browserInfo.value = getDetailedBrowserInfo();
            }

            // Priority selector
            const priorityOptions = document.querySelectorAll('.priority-option');
            const priorityInput = document.getElementById('priority');

            priorityOptions.forEach(option => {
                option.addEventListener('click', function() {
                    priorityOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    priorityInput.value = this.dataset.priority;
                });
            });

            // Phone number formatting
            const phoneInput = document.getElementById('report-phone');
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 10) {
                    value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                }
                e.target.value = value;
            });
        });

        function openReportModal() {
            const modal = document.getElementById('reportModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Reset form and hide success message
            document.getElementById('reportForm').reset();
            document.getElementById('successMessage').style.display = 'none';
            
            // Reset priority to medium
            document.querySelectorAll('.priority-option').forEach(opt => opt.classList.remove('selected'));
            document.querySelector('.priority-option[data-priority="medium"]').classList.add('selected');
            document.getElementById('priority').value = 'medium';

            // Re-populate browser info after reset
            const browserInfo = document.getElementById('browser-info');
            if (browserInfo) {
                // Enhanced browser detection function (same as above)
                function getDetailedBrowserInfo() {
                    const userAgent = navigator.userAgent;
                    const platform = navigator.platform;
                    const language = navigator.language;
                    const cookieEnabled = navigator.cookieEnabled;
                    const onLine = navigator.onLine;
                    
                    let browserName = 'Unknown Browser';
                    let browserVersion = 'Unknown Version';
                    let osName = 'Unknown OS';
                    let osVersion = '';
                    let architecture = '';

                    // Detect Operating System
                    if (userAgent.indexOf('Windows NT 10.0') !== -1) {
                        osName = 'Windows 10/11';
                    } else if (userAgent.indexOf('Windows NT 6.3') !== -1) {
                        osName = 'Windows 8.1';
                    } else if (userAgent.indexOf('Windows NT 6.2') !== -1) {
                        osName = 'Windows 8';
                    } else if (userAgent.indexOf('Windows NT 6.1') !== -1) {
                        osName = 'Windows 7';
                    } else if (userAgent.indexOf('Windows NT 6.0') !== -1) {
                        osName = 'Windows Vista';
                    } else if (userAgent.indexOf('Windows NT 5.1') !== -1) {
                        osName = 'Windows XP';
                    } else if (userAgent.indexOf('Windows') !== -1) {
                        osName = 'Windows';
                    } else if (userAgent.indexOf('Mac OS X') !== -1) {
                        osName = 'macOS';
                        const macVersion = userAgent.match(/Mac OS X ([0-9_]+)/);
                        if (macVersion) {
                            osVersion = macVersion[1].replace(/_/g, '.');
                        }
                    } else if (userAgent.indexOf('Linux') !== -1) {
                        osName = 'Linux';
                    } else if (userAgent.indexOf('Android') !== -1) {
                        osName = 'Android';
                    } else if (userAgent.indexOf('iPhone') !== -1 || userAgent.indexOf('iPad') !== -1) {
                        osName = 'iOS';
                    }

                    // Detect Architecture
                    if (userAgent.indexOf('WOW64') !== -1 || userAgent.indexOf('Win64') !== -1 || userAgent.indexOf('x64') !== -1) {
                        architecture = '64-bit';
                    } else if (userAgent.indexOf('Win32') !== -1 || userAgent.indexOf('x86') !== -1) {
                        architecture = '32-bit';
                    }

                    // Detect Browser (Order matters!)
                    if (userAgent.indexOf('Edg/') !== -1) {
                        browserName = 'Microsoft Edge';
                        const edgeVersion = userAgent.match(/Edg\/([0-9.]+)/);
                        if (edgeVersion) browserVersion = edgeVersion[1];
                    } else if (userAgent.indexOf('Edge/') !== -1) {
                        browserName = 'Microsoft Edge (Legacy)';
                        const edgeVersion = userAgent.match(/Edge\/([0-9.]+)/);
                        if (edgeVersion) browserVersion = edgeVersion[1];
                    } else if (userAgent.indexOf('OPR/') !== -1 || userAgent.indexOf('Opera/') !== -1) {
                        browserName = 'Opera';
                        const operaVersion = userAgent.match(/(?:OPR|Opera)\/([0-9.]+)/);
                        if (operaVersion) browserVersion = operaVersion[1];
                    } else if (userAgent.indexOf('Chrome/') !== -1 && userAgent.indexOf('Safari/') !== -1) {
                        browserName = 'Google Chrome';
                        const chromeVersion = userAgent.match(/Chrome\/([0-9.]+)/);
                        if (chromeVersion) browserVersion = chromeVersion[1];
                    } else if (userAgent.indexOf('Firefox/') !== -1) {
                        browserName = 'Mozilla Firefox';
                        const firefoxVersion = userAgent.match(/Firefox\/([0-9.]+)/);
                        if (firefoxVersion) browserVersion = firefoxVersion[1];
                    } else if (userAgent.indexOf('Safari/') !== -1 && userAgent.indexOf('Chrome') === -1) {
                        browserName = 'Safari';
                        const safariVersion = userAgent.match(/Version\/([0-9.]+)/);
                        if (safariVersion) browserVersion = safariVersion[1];
                    } else if (userAgent.indexOf('MSIE') !== -1 || userAgent.indexOf('Trident/') !== -1) {
                        browserName = 'Internet Explorer';
                        const ieVersion = userAgent.match(/(?:MSIE |rv:)([0-9.]+)/);
                        if (ieVersion) browserVersion = ieVersion[1];
                    }

                    // Get additional system info
                    const screenInfo = `${screen.width}x${screen.height}`;
                    const colorDepth = screen.colorDepth;
                    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    const memory = navigator.deviceMemory ? `${navigator.deviceMemory}GB RAM` : 'Unknown RAM';
                    const cores = navigator.hardwareConcurrency ? `${navigator.hardwareConcurrency} cores` : 'Unknown cores';

                    // Build comprehensive info string
                    let systemInfo = `${browserName} ${browserVersion}`;
                    systemInfo += ` | ${osName}`;
                    if (osVersion) systemInfo += ` ${osVersion}`;
                    if (architecture) systemInfo += ` (${architecture})`;
                    systemInfo += ` | Screen: ${screenInfo}`;
                    systemInfo += ` | ${colorDepth}-bit color`;
                    systemInfo += ` | ${cores}`;
                    if (navigator.deviceMemory) systemInfo += ` | ${memory}`;
                    systemInfo += ` | ${language}`;
                    systemInfo += ` | ${timezone}`;
                    systemInfo += ` | Cookies: ${cookieEnabled ? 'Enabled' : 'Disabled'}`;
                    systemInfo += ` | Online: ${onLine ? 'Yes' : 'No'}`;

                    return systemInfo;
                }

                browserInfo.value = getDetailedBrowserInfo();
            }
        }

        function closeReportModal() {
            const modal = document.getElementById('reportModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('reportModal');
            if (event.target == modal) {
                closeReportModal();
            }
        }

        // Handle form submission
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            // Simulate form submission (replace with actual AJAX call)
            setTimeout(() => {
                // Show success message
                document.getElementById('successMessage').style.display = 'flex';
                
                // Reset button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                // Auto close modal after 3 seconds
                setTimeout(() => {
                    closeReportModal();
                }, 3000);
                
            }, 2000);
        });
    </script>
</body>
</html>