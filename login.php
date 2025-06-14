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

        body {
            background-color: #f5f8ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
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

        .login-container {
            width: 100%;
            max-width: 1000px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            display: flex;
            position: relative;
        }

        .login-image {
            flex: 1;
            background-image: url('assets/images/bgfinal.png');
            background-size: 700px;
            background-position: center;
            position: relative;
            display: none;
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

        /* Show/Hide Password Icon */
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

        @media (min-width: 768px) {
            .login-image {
                display: block;
            }
        }

        @media (max-width: 767px) {
            .login-container {
                max-width: 450px;
            }
        }

        @media (max-width: 640px) {
            .login-form-container {
                padding: 1.5rem;
            }

            .login-logo {
                margin-bottom: 1.5rem;
            }

            .login-header {
                margin-bottom: 1.5rem;
            }

            .real-estate-features {
                display: none;
            }
            
            .login-footer {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
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
                    <?php echo $error; ?>
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
                    <p><a href="helpguest.php"><i class="fas fa-question-circle"></i> Help & Support</a></p>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordField = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                // Toggle the password field type between 'password' and 'text'
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                // Toggle the eye icon between 'eye' and 'eye-slash'
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>
