-- Disable foreign key checks at the start
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Drop existing tables if they exist
DROP TABLE IF EXISTS memo_images;
DROP TABLE IF EXISTS memo_visibility;
DROP TABLE IF EXISTS memos;
DROP TABLE IF EXISTS lead_activities;
DROP TABLE IF EXISTS downpayment_tracker;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS project_models;
DROP TABLE IF EXISTS developers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS settings;

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create tables in correct order (no dependencies first)

-- 1. Teams table (no dependencies)
CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert teams first
INSERT INTO `teams` (`id`, `name`, `created_at`) VALUES
(1, 'Blazing SPARCS', '2025-05-16 02:45:20'),
(2, 'Feisty Heroine', '2025-05-16 02:45:20'),
(3, 'Shinning Phoenix', '2025-05-16 02:45:20'),
(8, 'Flameborn Champions', '2025-05-16 02:45:20'),
(12, 'Fiery Achievers', '2025-05-19 02:25:43');

-- 2. Users table (depends on teams)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','supervisor','agent') NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Developers table (no dependencies)
CREATE TABLE `developers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Project Models table (depends on developers)
CREATE TABLE `project_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `developer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `developer_id` (`developer_id`),
  CONSTRAINT `project_models_ibfk_1` FOREIGN KEY (`developer_id`) REFERENCES `developers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Leads table (depends on users)
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `temperature` enum('Hot','Warm','Cold') NOT NULL,
  `status` enum('Inquiry','Presentation Stage','Negotiation','Closed','Lost','Site Tour','Closed Deal','Requirement Stage','Downpayment Stage','Housing Loan Application','Loan Approval','Loan Takeout','House Inspection','House Turn Over') NOT NULL,
  `source` enum('Facebook Groups','KKK','Facebook Ads','TikTok ads','Google Ads','Facebook live','Referral','Teleprospecting','Video Message','Organic Posting','Email Marketing','Follow up','Manning','Walk in','Flyering','Chat messaging','Property Listing','Landing Page','Networking Events','Organic Sharing','Youtube Marketing','LinkedIn','Open House') NOT NULL,
  `developer` varchar(100) NOT NULL,
  `project_model` varchar(100) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Downpayment Tracker table (depends on leads)
CREATE TABLE `downpayment_tracker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `reservation_date` date DEFAULT NULL,
  `requirements_complete` tinyint(1) DEFAULT 0,
  `spot_dp` tinyint(1) DEFAULT 0,
  `dp_terms` enum('6','9','12','15','18','24','36') NOT NULL,
  `current_dp_stage` int(11) DEFAULT 1,
  `total_dp_stages` int(11) DEFAULT NULL,
  `pagibig_bank_approval` tinyint(1) DEFAULT 0,
  `loan_takeout` tinyint(1) DEFAULT 0,
  `turnover` tinyint(1) DEFAULT 0,
  `progress_rate` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  CONSTRAINT `downpayment_tracker_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Lead Activities table (depends on leads and users)
CREATE TABLE `lead_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `notes` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `lead_activities_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_activities_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Memos table (depends on users)
CREATE TABLE `memos` (
  `memo_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`memo_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `memos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Memo Images table (depends on memos)
CREATE TABLE `memo_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `memo_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `memo_id` (`memo_id`),
  CONSTRAINT `memo_images_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`memo_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 10. Memo Visibility table (depends on memos)
CREATE TABLE `memo_visibility` (
  `visibility_id` int(11) NOT NULL AUTO_INCREMENT,
  `memo_id` int(11) NOT NULL,
  `visible_to_role` enum('manager','supervisor','agent') NOT NULL,
  PRIMARY KEY (`visibility_id`),
  KEY `memo_id` (`memo_id`),
  CONSTRAINT `memo_visibility_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`memo_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 11. Settings table (no dependencies)
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_description` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Now insert the data in the correct order
-- 1. Insert users data (after teams)
INSERT INTO `users` (`id`, `team_id`, `username`, `password`, `name`, `email`, `phone`, `role`, `profile_picture`, `created_at`) VALUES
(1, NULL, 'admin', '$2y$10$3iuZEf0f2.uXqGinef6fnOWVGjCYMaP27pTPELt3kPwaUm2xEqeGS', 'Administrator', 'innersparcservices@gmail.com', NULL, 'admin', NULL, '2025-05-16 02:45:20'),
(3, 8, 'shielamae.innersparc', '$2y$10$gbLWkM8Lc//nUt5oexmLXOkZSG6PVoIaSMntaSqlgqCB1mlLFRVeG', 'Sheila Mae Fontelo Fajutagana', 'francisheila05@gmail.com', NULL, 'agent', NULL, '2025-05-16 02:45:21'),
(4, 2, 'luzvimindalim.innersparc', '$2y$10$PUV7H0RlWs.dJZ6x8SqmvOFy3x9MlJIo0m7otynHJfAxHnktLhC4u', 'Luzviminda Labrado Lim', 'luz032069@gmail.com', NULL, 'supervisor', NULL, '2025-05-16 02:45:21'),
(5, 3, 'manager3', '$2y$10$rRuVdtrMXPrd1x.ySHEY3.9Vvjxds.9Js5rCvRPgQG7ug3NzLmMZO', 'Team 3 Manager', 'manager3@example.com', NULL, 'manager', NULL, '2025-05-16 02:45:21'),
(6, 1, 'mikegabrielbedionescarilla.innersparc', '$2y$10$wQ4nzPYxWM.8smNno/0vIOfwSvYWxq3yphH3y8QCXxK68d3i8uSPG', 'Mike Gabriel Bedion Escarilla', 'escarilla.mikegabriel@gmail.com', NULL, 'agent', NULL, '2025-05-16 02:45:21');

-- 2. Insert developers data
INSERT INTO `developers` (`id`, `name`, `created_at`) VALUES
(1, 'Lancaster', '2025-05-16 02:45:20'),
(2, 'Antipolo Heights', '2025-05-16 02:45:20'),
(3, 'Pleasantfields', '2025-05-16 02:45:20');

-- 3. Insert project models data
INSERT INTO `project_models` (`id`, `developer_id`, `name`, `created_at`) VALUES
(1, 1, 'Alice', '2025-05-16 02:45:20'),
(2, 2, 'Model A', '2025-05-16 02:45:20'),
(3, 3, 'Kennedy', '2025-05-16 02:45:20');

-- Add remaining data in the same order...

-- Re-enable foreign key checks at the end
SET FOREIGN_KEY_CHECKS=1;
COMMIT;