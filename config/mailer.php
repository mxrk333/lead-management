<?php
// config/mailer.php
// This file holds your SMTP configuration for PHPMailer.
// IMPORTANT: In a production environment, these values should be loaded from environment variables
// or a secure configuration management system, NOT hardcoded directly in the file.

define('SMTP_HOST', 'smtp.example.com'); // Your SMTP server host (e.g., smtp.gmail.com, smtp.mailgun.org)
define('SMTP_USERNAME', 'your_email@example.com'); // Your SMTP username (usually your email address)
define('SMTP_PASSWORD', 'your_email_password'); // Your SMTP password
define('SMTP_PORT', 587); // SMTP port (e.g., 587 for TLS, 465 for SSL)
define('SMTP_ENCRYPTION', 'tls'); // Encryption method ('ssl' or 'tls')
define('MAIL_FROM_EMAIL', 'noreply@innersparc.com'); // Email address to send from
define('MAIL_FROM_NAME', 'Inner SPARC Support'); // Name to display as sender
