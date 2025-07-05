CREATE TABLE problem_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    username VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(255) NULL,
    issue_type ENUM(
        'login-failed',
        'forgot-password', 
        'account-locked',
        'page-error',
        'performance',
        'feature-bug',
        'data-issue',
        'security-concern',
        'other'
    ) NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    description TEXT NOT NULL,
    browser_info VARCHAR(500) NULL,
    status ENUM('open', 'in-progress', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT NULL,
    resolution_notes TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    
    -- Indexes for better performance
    INDEX idx_username (username),
    INDEX idx_phone (phone_number),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_issue_type (issue_type),
    INDEX idx_created_at (created_at),
    INDEX idx_ticket_number (ticket_number)
);

-- Admin Users Table (for assignment)
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    role ENUM('admin', 'support', 'manager') DEFAULT 'support',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Problem Report Comments/Updates Table
CREATE TABLE report_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    admin_id INT NULL,
    comment_type ENUM('internal', 'customer', 'system') DEFAULT 'internal',
    comment TEXT NOT NULL,
    is_visible_to_customer BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (report_id) REFERENCES problem_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    
    INDEX idx_report_id (report_id),
    INDEX idx_created_at (created_at)
);

-- Problem Categories for better organization
CREATE TABLE problem_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO problem_categories (category_name, description) VALUES
('Authentication', 'Login, password, and account access issues'),
('Performance', 'Slow loading, timeouts, and performance problems'),
('Features', 'Feature bugs and functionality issues'),
('Data', 'Data accuracy and information problems'),
('Security', 'Security concerns and vulnerabilities'),
('General', 'Other general technical issues');

-- Insert sample admin users
INSERT INTO admin_users (username, full_name, email, role) VALUES
('admin', 'System Administrator', 'admin@innersparc.com', 'admin'),
('support1', 'John Support', 'support1@innersparc.com', 'support'),
('support2', 'Jane Support', 'support2@innersparc.com', 'support'),
('manager', 'Support Manager', 'manager@innersparc.com', 'manager');

-- Add foreign key constraint for assigned_to
ALTER TABLE problem_reports 
ADD CONSTRAINT fk_assigned_to 
FOREIGN KEY (assigned_to) REFERENCES admin_users(id) ON DELETE SET NULL;

-- Create a trigger to generate ticket numbers
DELIMITER //
CREATE TRIGGER generate_ticket_number 
BEFORE INSERT ON problem_reports
FOR EACH ROW
BEGIN
    DECLARE ticket_prefix VARCHAR(10) DEFAULT 'ISR-';
    DECLARE ticket_suffix VARCHAR(10);
    DECLARE current_year VARCHAR(4);
    DECLARE ticket_count INT;
    
    SET current_year = YEAR(NOW());
    
    -- Get count of tickets for current year
    SELECT COUNT(*) + 1 INTO ticket_count 
    FROM problem_reports 
    WHERE YEAR(created_at) = current_year;
    
    -- Generate ticket number: ISR-2024-0001
    SET ticket_suffix = LPAD(ticket_count, 4, '0');
    SET NEW.ticket_number = CONCAT(ticket_prefix, current_year, '-', ticket_suffix);
END//
DELIMITER ;

-- Create views for reporting
CREATE VIEW report_summary AS
SELECT 
    DATE(created_at) as report_date,
    COUNT(*) as total_reports,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_reports,
    SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as in_progress_reports,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority,
    SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium_priority,
    SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_priority
FROM problem_reports
GROUP BY DATE(created_at)
ORDER BY report_date DESC;

-- Create view for active reports with admin info
CREATE VIEW active_reports_with_admin AS
SELECT 
    pr.id,
    pr.ticket_number,
    pr.username,
    pr.phone_number,
    pr.email,
    pr.issue_type,
    pr.priority,
    pr.status,
    pr.description,
    pr.created_at,
    pr.updated_at,
    au.full_name as assigned_admin,
    au.email as admin_email
FROM problem_reports pr
LEFT JOIN admin_users au ON pr.assigned_to = au.id
WHERE pr.status IN ('open', 'in-progress')
ORDER BY 
    CASE pr.priority 
        WHEN 'high' THEN 1 
        WHEN 'medium' THEN 2 
        WHEN 'low' THEN 3 
    END,
    pr.created_at ASC;