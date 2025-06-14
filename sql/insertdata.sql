-- Update memo database structure script
-- This script will safely add missing columns and tables

-- First, let's check and add the visible_to_all column to memos table
ALTER TABLE memos 
ADD COLUMN IF NOT EXISTS visible_to_all TINYINT(1) DEFAULT 1 AFTER team_id;

-- Create memo_team_visibility table if it doesn't exist
CREATE TABLE IF NOT EXISTS memo_team_visibility (
    id INT AUTO_INCREMENT PRIMARY KEY,
    memo_id INT NOT NULL,
    team_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (memo_id) REFERENCES memos(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_memo_team (memo_id, team_id)
);

-- Create memo_read_status table if it doesn't exist
CREATE TABLE IF NOT EXISTS memo_read_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    memo_id INT NOT NULL,
    employee_id INT NOT NULL,
    read_status TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (memo_id) REFERENCES memos(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_memo_employee (memo_id, employee_id)
);

-- Update existing memos to have visible_to_all = 1 by default
UPDATE memos SET visible_to_all = 1 WHERE visible_to_all IS NULL;

-- Show success message
SELECT 'Memo database structure updated successfully! All missing columns and tables have been created.' as message;
