-- Grant privileges to root
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;

-- Now select your database (replace lead_management with your actual database name)
USE lead_management;

-- Then run our cleanup script
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS memo_read_status;
DROP TABLE IF EXISTS memo_team_visibility;
DROP TABLE IF EXISTS memos;

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
    CONSTRAINT fk_memos_team FOREIGN KEY (team_id) REFERENCES teams(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS memo_team_visibility (
    memo_id INT NOT NULL,
    team_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (memo_id, team_id),
    CONSTRAINT fk_memo_visibility_memo FOREIGN KEY (memo_id) REFERENCES memos(id) ON DELETE CASCADE,
    CONSTRAINT fk_memo_visibility_team FOREIGN KEY (team_id) REFERENCES teams(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS memo_read_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    memo_id INT NOT NULL,
    employee_id INT NOT NULL,
    read_status TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_memo_read_status_memo FOREIGN KEY (memo_id) REFERENCES memos(id) ON DELETE CASCADE,
    CONSTRAINT fk_memo_read_status_employee FOREIGN KEY (employee_id) REFERENCES users(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS=1; 