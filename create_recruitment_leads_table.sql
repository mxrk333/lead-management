-- Create recruitment_leads table
-- This table is missing from the database but required by the recruitment dashboard

CREATE TABLE IF NOT EXISTS `recruitment_leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `full_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `recruiter_name` varchar(100) DEFAULT NULL,
  `recruiter_id` int(11) DEFAULT NULL,
  `recruiter_team_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `source` varchar(100) DEFAULT NULL,
  `agent_onboarding_status` tinyint(1) DEFAULT 0,
  `onboarding_status` tinyint(1) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Training/Assessment columns
  `pre_assessment` tinyint(1) DEFAULT 0,
  `accreditation` tinyint(1) DEFAULT 0,
  `assessment` tinyint(1) DEFAULT 0,
  `sales_training` tinyint(1) DEFAULT 0,
  `site_tour` tinyint(1) DEFAULT 0,
  `onboarding` tinyint(1) DEFAULT 0,
  `habit_forming` tinyint(1) DEFAULT 0,
  `digital_training` tinyint(1) DEFAULT 0,
  `sales_training_materials` tinyint(1) DEFAULT 0,
  `objection_handling` tinyint(1) DEFAULT 0,
  `VAST` tinyint(1) DEFAULT 0,
  `sales_monitoring` tinyint(1) DEFAULT 0,
  `LMS` tinyint(1) DEFAULT 0,
  `comm_structure` tinyint(1) DEFAULT 0,
  `terminologies` tinyint(1) DEFAULT 0,
  `focus_projects` tinyint(1) DEFAULT 0,
  
  PRIMARY KEY (`id`),
  KEY `recruiter_id` (`recruiter_id`),
  KEY `recruiter_team_id` (`recruiter_team_id`),
  KEY `status` (`status`),
  KEY `onboarding_status` (`onboarding_status`),
  KEY `created_at` (`created_at`),
  KEY `email` (`email`),
  KEY `contact_number` (`contact_number`),
  
  -- Foreign key constraints
  CONSTRAINT `recruitment_leads_ibfk_1` FOREIGN KEY (`recruiter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recruitment_leads_ibfk_2` FOREIGN KEY (`recruiter_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add some sample data for testing (optional)
INSERT INTO `recruitment_leads` 
(`full_name`, `contact_number`, `email`, `recruiter_name`, `recruiter_id`, `recruiter_team_id`, `status`, `source`, `onboarding_status`, `remarks`) 
VALUES 
('John Doe', '09123456789', 'john.doe@gmail.com', 'Administrator', 1, 1, 'Active', 'Facebook Ads', 0, 'Sample recruitment lead'),
('Jane Smith', '09234567890', 'jane.smith@gmail.com', 'Administrator', 1, 1, 'Active', 'Referral', 1, 'Already onboarded'),
('Mike Johnson', '09345678901', 'mike.johnson@gmail.com', 'Administrator', 1, 2, 'Inactive', 'Walk-in', 0, 'Inactive candidate');

SELECT 'recruitment_leads table created successfully' as message;
