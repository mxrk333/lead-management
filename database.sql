-- Add missing columns to memos table if they don't exist

-- Check if visible_to_all column exists, if not add it
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'memos' 
AND column_name = 'visible_to_all';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE memos ADD COLUMN visible_to_all TINYINT(1) DEFAULT 1 AFTER team_id',
    'SELECT "visible_to_all column already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

SELECT 'Memo database structure updated successfully' as message;
