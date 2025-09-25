# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Development Environment

### Starting the Application
```bash
# Start XAMPP services (required for local development)
sudo /Applications/XAMPP/xamppfiles/xampp start

# Or individually:
sudo /Applications/XAMPP/xamppfiles/xampp startapache
sudo /Applications/XAMPP/xamppfiles/xampp startmysql

# Access application locally
open http://localhost/lead-management

# Stop services when done
sudo /Applications/XAMPP/xamppfiles/xampp stop
```

### Database Testing & Connectivity
```bash
# Test database connection
php test-db-connection.php

# Comprehensive database testing
php db-test.php

# Check specific functionality
php test-save-lead.php
php test-notification.php
```

### Development Testing
```bash
# Test individual components
php test-index.php          # Main dashboard functionality
php test-dp-stage.php       # Downpayment stage tracking
php test-mark-read.php      # Memo acknowledgment system
php simple-recruitment-test.php  # Recruitment system

# Database maintenance
php maintenance.php         # System maintenance tasks
php cleanup_onboarding.php  # Clean up incomplete onboarding records
```

## Architecture Overview

### Core Structure
This is a PHP-based lead management system with a multi-tier architecture:

1. **Frontend Layer**: PHP-rendered HTML with custom CSS, JavaScript for interactivity
2. **Application Layer**: PHP business logic in individual page files
3. **Data Layer**: MySQL database with comprehensive schema
4. **API Layer**: RESTful endpoints in the `/api` directory

### Key Components

#### Configuration Management
- **`config/database.php`**: Environment-aware database connection handling
  - Auto-detects DreamHost vs local environment
  - Provides `getDbConnection()`, `safeQuery()`, `ensureConnection()` functions
- **Environment Detection**: Uses HTTP_HOST, SERVER_SOFTWARE, and file paths

#### User Management & Permissions
- **Superuser System**: Hardcoded usernames in `includes/functions.php`
  - `markpatigayon.itadmin`, `gabriellibacao.founder`, `romeocorberta.itdept`
- **Role-Based Access**: `admin`, `manager`, `supervisor`, `agent`
- **Team-Based Visibility**: Users see leads based on team membership

#### Lead Management Core
- **Status Pipeline**: 14 distinct stages from Inquiry to House Turn Over
- **Temperature Classification**: Hot, Warm, Cold
- **Source Tracking**: 20+ lead source types
- **Activity Tracking**: Complete audit trail with `lead_activities` table

#### Specialized Systems
- **Downpayment Tracker**: Complex financial tracking with stages and progress
- **Recruitment Dashboard**: Separate candidate management system
- **Memo System**: Internal communications with read status tracking
- **Goal Tracking**: Target setting and progress monitoring

### Database Schema Highlights

#### Primary Tables
- **`leads`**: Core lead information with status, temperature, pricing
- **`users`**: User accounts with team assignments and roles  
- **`teams`**: Organizational structure
- **`downpayment_tracker`**: Financial progress tracking
- **`lead_activities`**: Complete activity audit trail
- **`memos`**: Internal communication system
- **`recruitment_leads`**: Separate recruitment tracking

#### Key Relationships
- Users belong to teams (team-based visibility)
- Leads are owned by users (creator-based access)
- Activities are linked to both leads and users
- Downpayment tracking is 1:1 with leads

## Development Patterns

### Database Access Patterns
```php
// Always use the centralized connection function
$conn = getDbConnection();

// Use prepared statements for all queries
$stmt = $conn->prepare("SELECT * FROM leads WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

// Leverage the safe query wrapper for complex operations
$result = safeQuery($conn, $query, $params, $types);
```

### Permission Checking Patterns
```php
// Always check superuser status first
if (isSuperUser($user['username'])) {
    // Full access
}

// Then check role-based access
if ($user['role'] === 'Manager' && $user['team_id'] == $lead_team_id) {
    // Team-based access
}

// Finally owner-based access
if ($lead['user_id'] == $current_user_id) {
    // Owner access
}
```

### Error Handling Standards
- All database operations include comprehensive error logging
- Development vs production error handling (based on environment detection)
- Consistent use of `error_log()` for debugging
- JSON responses for AJAX endpoints

## API Endpoints

### Core API Structure (`/api` directory)
- **Lead Management**: `get-tracker.php`, `update-tracker.php`
- **Project Management**: `get_project.php`, `save_project.php`, `delete_project.php`
- **Reporting**: `submit-report.php`, `get-report-details.php`
- **Statistics**: `get-conversion-stats.php`
- **Utilities**: `get_cities.php`, `delete_receipt.php`

### AJAX Response Format
All API endpoints return JSON with consistent error handling and logging.

## Key Business Logic

### Lead Visibility Rules
1. **Superusers**: See all leads across all teams
2. **Managers/Supervisors**: See leads from their team members only
3. **Agents**: See only their own leads
4. **Special Cases**: Some system functions allow cross-team visibility for managers

### Financial Tracking
The downpayment tracker handles complex financial workflows:
- Reservation dates and requirements completion
- Spot downpayment vs installment terms (6-36 months)
- Progress tracking through multiple stages
- Integration with loan approval and turnover processes

### Recruitment System
Separate but integrated candidate management:
- Independent lead source and classification
- Interest level tracking
- Onboarding status management
- Team assignment and follow-up scheduling

## File Structure Guidelines

### Page Organization
- **Main Pages**: `index.php`, `leads.php`, `profile.php`, etc. (direct access pages)
- **Detail Pages**: `lead-details.php`, `edit-lead.php`, `user-details.php`
- **Utility Pages**: Files prefixed with `test-`, `debug-`, `check-`
- **Maintenance**: Scripts for data cleanup and system maintenance

### Asset Organization
- **`/uploads`**: User-generated content (receipts, profile pictures)
- **`/assets`**: Static assets (CSS, JS, images)
- **`/sql`**: Database schemas and migration scripts
- **`/migrations`**: Versioned database changes

## Common Development Tasks

### Adding New Lead Sources
Update the ENUM in the leads table schema and corresponding dropdown menus in add/edit forms.

### Role-Based Feature Development
Always implement the three-tier permission check: superuser → role-based → ownership-based.

### Database Schema Changes
1. Create migration script in `/migrations` with timestamp
2. Update main schema file in `/sql`
3. Test with existing data using test scripts

### AJAX Endpoint Development
Follow the established pattern in `/api` directory with proper error handling and JSON responses.

This system prioritizes data integrity, role-based security, and comprehensive audit trails. The architecture supports both the real estate lead management core and the recruitment system as integrated but distinct workflows.
