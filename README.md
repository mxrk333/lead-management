# System Ownership and Usage Notice

This system is the proprietary asset of Inner SPARC Realty Corporation and the following developers:

- Mark Christian Patigayon (MAIN DEVLOPER)
- Yenzo Teo Gervacio (MAIN DEVELOPER)

# Contact these developer if you have any question about the codebase.

Unauthorized use, reproduction, redistribution, or inclusion of this system—whether in personal portfolios, commercial projects, or any other form—is strictly prohibited. Proper credit must always be given to the original developers listed above.

This system is intended solely for the operations and benefit of Inner SPARC Realty Corporation. Any misuse or use beyond its intended scope, without formal written consent, will be treated as a violation of intellectual property rights.

Legal Action Notice:
Any individual or entity found reproducing, copying, or misrepresenting ownership of this system without authorization will be subject to formal complaints, legal action, and potential civil liability as per applicable laws.

# Respect the ownership, credit the developers, and use the system only as authorized. Any form of misuse will not be tolerated and will result in serious consequences.


###### Mark Christian Patigayon: Happy Developing OJT's and future developers! :))) #####


-------------------------------------------------------------------------------------------------------


# InnerSPARC Lead Management System

A comprehensive web-based lead management system designed specifically for real estate professionals to track, manage, and convert leads efficiently.

![Dashboard Preview](https://leads.dreamhosters.com/login.php)

## 🚀 Features

### Core Functionality
- **Lead Management**: Create, edit, view, and track leads through various stages
- **Dashboard Analytics**: Real-time statistics and performance metrics
- **User Authentication**: Secure login system with role-based access control
- **Notification System**: Real-time notifications for lead activities and updates
- **Responsive Design**: Mobile-friendly interface that works on all devices

### Advanced Features
- **Temperature Tracking**: Hot, Warm, Cold lead classification
- **Activity Logging**: Track all interactions with leads
- **Superuser Access**: Enhanced permissions for administrators
- **Search & Filter**: Advanced search capabilities across all leads
- **Export Functionality**: Export lead data for reporting
- **Memo System**: Internal communication and notes

### User Roles
- **Agent**: Standard user with access to assigned leads
- **Manager**: Enhanced access to team leads and reports
- **Super Admin**: Full system access and user management

## 🛠️ Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Styling**: Custom CSS with CSS Grid and Flexbox
- **Icons**: Font Awesome 6.0
- **Fonts**: Inter (Google Fonts)

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- mod_rewrite enabled (for Apache)
- 512MB RAM minimum
- 1GB disk space

## 🔧 Installation

### 1. Clone the Repository
\`\`\`bash
git clone https://github.com/mxrk333/lead-management.git
cd innersparc-lead-management
\`\`\`

### 2. Database Setup
\`\`\`sql
-- Create database
CREATE DATABASE real_estate_leads;

-- Import the database schema
mysql -u your_username -p innersparc_leads < database/schema.sql

-- Import sample data (optional)
mysql -u your_username -p innersparc_leads < database/sample_data.sql
\`\`\`

### 3. Configuration
Copy the configuration template and update with your settings:
\`\`\`bash
cp config/database.example.php config/database.php
\`\`\`

Edit `config/database.php`:
\`\`\`php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'innersparc_leads');
define('DB_USER', 'managementleads');
define('DB_PASS', 'innersparc123');
define('DB_CHARSET', 'utf8mb4');
?>
\`\`\`

### 4. Web Server Configuration

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"



