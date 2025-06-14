-- Add foreign key constraint to memos table
ALTER TABLE memos
ADD CONSTRAINT fk_memos_team
FOREIGN KEY (team_id) REFERENCES teams(id)
ON DELETE RESTRICT
ON UPDATE CASCADE;

-- Add index on team_id if not exists
ALTER TABLE memos
ADD INDEX idx_team_id (team_id);

-- Make sure memo_team_visibility has correct structure and constraints
DROP TABLE IF EXISTS memo_team_visibility;

CREATE TABLE IF NOT EXISTS memo_team_visibility (
    memo_id INT NOT NULL,
    team_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (memo_id, team_id),
    CONSTRAINT fk_memo_visibility_memo
        FOREIGN KEY (memo_id) REFERENCES memos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_memo_visibility_team
        FOREIGN KEY (team_id) REFERENCES teams(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;
