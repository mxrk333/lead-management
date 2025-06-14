-- First, disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS=0;

-- Drop all possible foreign key constraints that might reference memos
SELECT CONCAT('ALTER TABLE ', TABLE_NAME, ' DROP FOREIGN KEY ', CONSTRAINT_NAME, ';')
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME = 'memos'
AND CONSTRAINT_NAME != 'PRIMARY'
AND TABLE_SCHEMA = DATABASE();

-- Drop specific foreign keys if they exist
ALTER TABLE memo_team_visibility
DROP FOREIGN KEY IF EXISTS fk_memo_visibility_memo;

ALTER TABLE memo_team_visibility
DROP FOREIGN KEY IF EXISTS fk_memo_visibility_team;

ALTER TABLE memo_read_status
DROP FOREIGN KEY IF EXISTS fk_memo_read_status_memo;

ALTER TABLE memos
DROP FOREIGN KEY IF EXISTS fk_memos_team;

-- Drop related tables first
DROP TABLE IF EXISTS memo_read_status;
DROP TABLE IF EXISTS memo_team_visibility;

-- Drop the main table
DROP TABLE IF EXISTS memos;

-- Now recreate the tables in correct order
CREATE TABLE IF NOT EXISTS memos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    memo_when DATETIME NOT NULL,
    file_path VARCHAR(255),
    created_by INT NOT NULL,
    team_id INT NOT NULL,
    visible_to_all TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS memo_team_visibility (
    memo_id INT NOT NULL,
    team_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (memo_id, team_id),
    FOREIGN KEY (memo_id) REFERENCES memos(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS memo_read_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    memo_id INT NOT NULL,
    employee_id INT NOT NULL,
    read_status TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (memo_id) REFERENCES memos(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;