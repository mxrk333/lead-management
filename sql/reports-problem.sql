CREATE TABLE IF NOT EXISTS `problem_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `email` VARCHAR(255),
    `issue_type` VARCHAR(100) NOT NULL,
    `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `description` TEXT NOT NULL,
    `browser_info` TEXT,
    `status` ENUM('open', 'in-progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    `assigned_to` INT NULL, -- Just a number or reference, no constraint
    `resolution_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resolved_at` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

