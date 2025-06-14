-- Disable foreign key checks initially
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables if they exist
DROP TABLE IF EXISTS memo_images;
DROP TABLE IF EXISTS memo_files;
DROP TABLE IF EXISTS memos;
DROP TABLE IF EXISTS memo_read_status;
DROP TABLE IF EXISTS lead_activities;
DROP TABLE IF EXISTS downpayment_tracker;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS project_models;
DROP TABLE IF EXISTS developers;
DROP TABLE IF EXISTS settings;

-- Create tables in correct order
-- 1. Teams (no dependencies)
CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Users (depends on teams)
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
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `team_id` (`team_id`),
  KEY `role` (`role`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Developers
CREATE TABLE `developers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Project Models (depends on developers)
CREATE TABLE `project_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `developer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(12,2) DEFAULT NULL,
  `floor_area` decimal(8,2) DEFAULT NULL,
  `lot_area` decimal(8,2) DEFAULT NULL,
  `bedrooms` int(2) DEFAULT NULL,
  `bathrooms` int(2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `developer_id` (`developer_id`),
  KEY `is_active` (`is_active`),
  UNIQUE KEY `developer_model` (`developer_id`, `name`),
  CONSTRAINT `project_models_ibfk_1` FOREIGN KEY (`developer_id`) REFERENCES `developers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Leads (depends on users)
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `temperature` enum('Hot','Warm','Cold') NOT NULL,
  `status` enum('Inquiry','Presentation Stage','Negotiation','Closed','Lost','Site Tour','Closed Deal','Requirement Stage','Downpayment Stage','Housing Loan Application','Loan Approval','Loan Takeout','House Inspection','House Turn Over') NOT NULL,
  `source` enum('Facebook Groups','KKK','Facebook Ads','TikTok ads','Google Ads','Facebook live','Referral','Teleprospecting','Video Message','Organic Posting','Email Marketing','Follow up','Manning','Walk in','Flyering','Chat messaging','Property Listing','Landing Page','Networking Events','Organic Sharing','Youtube Marketing','LinkedIn','Open House') NOT NULL,
  `developer` varchar(100) NOT NULL,
  `project_model` varchar(100) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `expected_commission` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `temperature` (`temperature`),
  KEY `source` (`source`),
  KEY `follow_up_date` (`follow_up_date`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Downpayment Tracker (depends on leads)
CREATE TABLE `downpayment_tracker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `reservation_date` date DEFAULT NULL,
  `requirements_complete` tinyint(1) DEFAULT 0,
  `spot_dp` tinyint(1) DEFAULT 0,
  `spot_dp_amount` decimal(12,2) DEFAULT 0.00,
  `dp_terms` enum('6','9','12','15','18','24','36') DEFAULT NULL,
  `monthly_dp_amount` decimal(12,2) DEFAULT 0.00,
  `current_dp_stage` int(11) DEFAULT 1,
  `total_dp_stages` int(11) DEFAULT NULL,
  `total_dp_paid` decimal(12,2) DEFAULT 0.00,
  `remaining_dp_balance` decimal(12,2) DEFAULT 0.00,
  `pagibig_bank_approval` tinyint(1) DEFAULT 0,
  `loan_amount` decimal(12,2) DEFAULT 0.00,
  `loan_takeout` tinyint(1) DEFAULT 0,
  `loan_takeout_date` date DEFAULT NULL,
  `turnover` tinyint(1) DEFAULT 0,
  `turnover_date` date DEFAULT NULL,
  `progress_rate` decimal(5,2) DEFAULT 0.00,
  `next_payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_id` (`lead_id`),
  KEY `reservation_date` (`reservation_date`),
  KEY `next_payment_date` (`next_payment_date`),
  CONSTRAINT `downpayment_tracker_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Lead Activities (depends on leads and users)
CREATE TABLE `lead_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `notes` text NOT NULL,
  `scheduled_date` datetime DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  KEY `user_id` (`user_id`),
  KEY `activity_type` (`activity_type`),
  KEY `scheduled_date` (`scheduled_date`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `lead_activities_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_activities_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Memos table (depends on users and teams)
CREATE TABLE `memos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `file_path` varchar(255) DEFAULT NULL,
    `description` text NOT NULL,
    `memo_when` datetime NOT NULL,
    `memo_where` varchar(255) DEFAULT NULL,
    `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
    `is_active` tinyint(1) DEFAULT 1,
    `created_by` int(11) NOT NULL,
    `team_id` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `created_by` (`created_by`),
    KEY `team_id` (`team_id`),
    KEY `memo_when` (`memo_when`),
    KEY `priority` (`priority`),
    KEY `is_active` (`is_active`),
    CONSTRAINT `memos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `memos_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Memo Read Status table (depends on memos and users)
CREATE TABLE `memo_read_status` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `memo_id` int(11) NOT NULL,
    `employee_id` int(11) NOT NULL,
    `read_status` tinyint(1) NOT NULL DEFAULT '0',
    `read_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `memo_employee_unique` (`memo_id`, `employee_id`),
    KEY `employee_id` (`employee_id`),
    KEY `read_status` (`read_status`),
    CONSTRAINT `memo_read_status_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `memo_read_status_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Settings table for application configuration
CREATE TABLE `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text DEFAULT NULL,
    `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
    `description` text DEFAULT NULL,
    `is_editable` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- INSERT DATA
-- Insert Teams
INSERT INTO `teams` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Team Alpha', 'Primary sales team focusing on premium properties', '2025-05-16 02:45:20'),
(2, 'Team Beta', 'Secondary sales team handling mid-range properties', '2025-05-16 02:45:20'),
(3, 'Team Gamma', 'Specialized team for luxury developments', '2025-05-16 02:45:20'),
(8, 'Team Delta', 'New business development team', '2025-05-16 02:45:20'),
(12, 'Team Omega', 'Senior sales specialists team', '2025-05-16 02:45:20');

-- Insert Users
INSERT INTO `users` (`id`, `team_id`, `username`, `password`, `name`, `email`, `phone`, `role`, `profile_picture`, `is_active`, `created_at`) VALUES
(1, NULL, 'admin', '$2y$10$3iuZEf0f2.uXqGinef6fnOWVGjCYMaP27pTPELt3kPwaUm2xEqeGS', 'Administrator', 'innersparcservices@gmail.com', NULL, 'admin', NULL, 1, '2025-05-16 02:45:20'),
(3, 8, 'shielamae.innersparc', '$2y$10$gbLWkM8Lc//nUt5oexmLXOkZSG6PVoIaSMntaSqlgqCB1mlLFRVeG', 'Sheila Mae Fontelo Fajutagana', 'francisheila05@gmail.com', '09171234567', 'agent', NULL, 1, '2025-05-16 02:45:21'),
(4, 2, 'luzvimindalim.innersparc', '$2y$10$PUV7H0RlWs.dJZ6x8SqmvOFy3x9MlJIo0m7otynHJfAxHnktLhC4u', 'Luzviminda Labrado Lim', 'luz032069@gmail.com', '09181234567', 'supervisor', NULL, 1, '2025-05-16 02:45:21'),
(5, 3, 'manager3', '$2y$10$rRuVdtrMXPrd1x.ySHEY3.9Vvjxds.9Js5rCvRPgQG7ug3NzLmMZO', 'Team 3 Manager', 'manager3@example.com', '09191234567', 'manager', NULL, 1, '2025-05-16 02:45:21'),
(6, 1, 'mikegabrielbedionescarilla.innersparc', '$2y$10$wQ4nzPYxWM.8smNno/0vIOfwSvYWxq3yphH3y8QCXxK68d3i8uSPG', 'Mike Gabriel Bedion Escarilla', 'escarilla.mikegabriel@gmail.com', '09201234567', 'agent', NULL, 1, '2025-05-16 02:45:21'),
(7, NULL, 'romeojrcernacorberta.itdept', '$2y$10$rRuVdtrMXPrd1x.ySHEY3.9Vvjxds.9Js5rCvRPgQG7ug3NzLmMZO', 'Romeo Jr. Cerna Cobreta', 'romxcob.innersparc@gmail.com', '09211234567', 'admin', NULL, 1, '2025-05-16 02:45:21'),
(8, 8, 'charlenedellosa.innersparc', '$2y$10$7BvBgpFnDmhhX3/zSVikjebJCWefKLpsg36RYdkM0KhZDmjE/Zmmu', 'Charlene Dellosa', 'dellosacharlene1317@gmail.com', '09221234567', 'manager', 'uploads/profile_pictures/user_8_1747501769.png', 1, '2019-05-16 02:45:23'),
(9, 2, 'alvinllaneta.innersparc', '$2y$10$.w9.aPUWHSS5GifQHPn9Fu2.JKn817TupAc0xAEDSekCnwFDH1UiW', 'Alvin Llaneta', 'alvinllaneta8@gmail.com', '09231234567', 'agent', NULL, 1, '2025-05-16 02:45:23'),
(10, 3, 'supervisor3', '$2y$10$rRuVdtrMXPrd1x.ySHEY3.9Vvjxds.9Js5rCvRPgQG7ug3NzLmMZO', 'Team 3 Supervisor', 'supervisor3@example.com', '09241234567', 'supervisor', NULL, 1, '2025-05-16 02:45:23'),
(11, 2, 'clarencedanielleserdon.innersparc', '$2y$10$tRBIk1jyaXGFbpAveJE8gOqFpGW.O/hJInU3qJ/mnqr3k/RBneuQK', 'Clarence Danielle Lim Serdon', 'clarencedanielle98@gmail.com', '09251234567', 'agent', NULL, 1, '2025-05-16 02:45:23'),
(12, 2, 'gabcyrosebenson.innersparc', '$2y$10$GnAbo83uu0hltK474fV1B.It7N5iLugZXtDFd.5vDRuFqso62qcXS', 'Gabcyrose Samsona Benson', 'gabcyrose@gmail.com', '09261234567', 'agent', NULL, 1, '2025-05-16 02:45:23'),
(13, 1, 'lenizaflorespasion.innersparc', '$2y$10$oHDt9M8XyxG7WGe5TusQluJBQXhGLuIz67T5vXM7YYkcPO1y2cXIq', 'Leniza Flores Pasion', 'lenizapasion51@gmail.com', '09271234567', 'agent', NULL, 1, '2025-05-16 02:45:23'),
(14, 1, 'perlitasantiagogo.innersparc', '$2y$10$p4kmD3n.wjSirRlDZpwRaOz69x2uytmxIQcBsgRTPpJynvZ5o1I76', 'Perlita Santiago Go', 'gopearl43@yahoo.com', '09281234567', 'agent', NULL, 1, '2025-05-16 02:45:23'),
(15, NULL, 'markpatigayon.itdept', '$2y$10$Tbq4hLp0VyDFdxogyElwm.A52Fh.OLnPqgayZTWrAlY4s.V7XCPdi', 'Mark Christian Patigayon', 'markpatigayon440@gmail.com', '09291234567', 'admin', 'uploads/profile_pictures/user_15_1747499626.jpg', 1, '2019-05-22 02:45:23'),
(16, 1, 'verlynbizcondevesagas.innersparc', '$2y$10$PEX7IJamKPjc2Mg5859AZuLQjqB/anHrK9ty37.dejDgGTEVazAwy', 'Verlyn Bizconde Vesagas', 'vverlyn@gmail.com', '09301234567', 'agent', NULL, 1, '2025-05-19 02:08:49'),
(17, 1, 'rizelagrimas.innersparc', '$2y$10$ipV2yK499HzPJRz4Bw8G0OMKotbJFfC37LzMtCn1qPimCph6f9Dmy', 'Rize OwogOwog Lagrimas', 'rizielagrimas18@gmail.com', '09311234567', 'agent', NULL, 1, '2025-05-19 02:09:39'),
(18, 1, 'ireneblanca.innersparc', '$2y$10$aEQppVOJLQ3050pCQEKrwepJCT8rw3frtf.AqkgMPqw7TjisrYmli', 'Irene Noble Blanca', 'ireneblanca1909@gmail.com', '09321234567', 'agent', NULL, 1, '2025-05-19 02:10:13'),
(19, 1, 'gabriellibacao.innersparc', '$2y$10$0A.MhdXz2UAcy4bUGR6EBOsQ82F9GtlZLgCw0a0n46KXTUuoM8t8a', 'Gabriel Jr. Villamor Libacao', 'libacaoga@gmail.com', '09331234567', 'admin', NULL, 1, '2025-05-19 02:10:58'),
(20, 1, 'erwinbauioan.innersparc', '$2y$10$/I4c/Yn7lWXRlraQvK0m2ueNwa2vgAfJ1NSKVG9wNfXJUu51fjoeq', 'Erwin Gonzales Baguioan', 'irwindgonzales6@gmail.com', '09341234567', 'manager', NULL, 1, '2025-05-19 02:11:50'),
(21, 1, 'nelynortega.innersparc', '$2y$10$pPrIuezkcJ9THUg5IHmFMuo68tv5W5MhSeLarPb4HIFc2rt5Jj8u.', 'Nelyn Serad Ortega', 'orteganelyn18@gmail.com', '09351234567', 'supervisor', NULL, 1, '2025-05-19 02:12:41'),
(22, 1, 'sarahlopez.innersparc', '$2y$10$HFq2/p7EOrkbn0gFTWoCJOC.0jkJX3rAZj0Rg65xuEfvqa7K3cw7K', 'Sarah Jean Lagatic Lopez', 'sarahjeanlopez07@gmail.com', '09361234567', 'supervisor', NULL, 1, '2025-05-19 02:13:10'),
(23, 2, 'nephelepanganiban', '$2y$10$uZrVk47hzppnbZsFYK/SWOAhQ5e04n/rqm8ti4Hkp7FgvWjq9WrW6', 'Nephele Telmo Panganiban', 'nephelepanganiban@gmail.com', '09371234567', 'agent', NULL, 1, '2025-05-19 02:18:33'),
(24, 2, 'joanbarceta.innersparc', '$2y$10$E.XU5PTwBNn6BryqVpHLTOGGmPOz9V1slWHXBZjzg4YeKC00K9o6K', 'Joan Mahinay Barceta', 'jobarceta22@gmail.com', '09381234567', 'manager', 'uploads/profile_pictures/user_24_1747622923.jpg', 1, '2025-05-19 02:19:01'),
(25, 2, 'teresasandoval.innersparc', '$2y$10$WYxXqgw6uC.9r4ukTgt.SOApN.eDGquy0nbDiJpAkfUHGrMQd2bx2', 'Teresa Rosanto Sandoval', 'trscyl@yahoo.com', '09391234567', 'supervisor', NULL, 1, '2025-05-19 02:20:20'),
(26, 2, 'ailynmdetorres.innersparc', '$2y$10$wNLuyURAbK/f1g53h1zuRu.XCIA5u/iFJqU529aWrzYijfu80iIwC', 'Ailyn Llaneta De Torres', 'ailyndetorres8@gmail.com', '09401234567', 'agent', NULL, 1, '2025-05-19 02:21:11'),
(27, 2, 'emilyncantuba.innersparc', '$2y$10$AL8RtPNTg4fhzeCoe8paGuFoFKQspNTe4WuvQmieLbw0BW06OyW/W', 'Emilyn Marcelo Cantuba', 'cantubaemhie@gmail.com', '09411234567', 'agent', NULL, 1, '2025-05-19 02:21:46'),
(28, 2, 'novelitatabudlong.innersparc', '$2y$10$VzOywJiBE7vbcCCgyXyqOO8r5qMiKWJfqXZmINuqXCvyziXMjM3wi', 'Novelita Letran Tabudlong', 'novzpretty@gmail.com', '09421234567', 'agent', NULL, 1, '2025-05-19 02:22:15'),
(29, 8, 'leodellosa.innersparc', '$2y$10$Cv6hsi.jSxoikgaDi275KO6HQstKWsWIOLA.vIQUfBVCW8BJIX8.i', 'Leo Dellosa', 'leodellosa@example.com', '09431234567', 'agent', NULL, 1, '2025-05-19 02:22:59'),
(30, 8, 'arleneumali.innersparc', '$2y$10$bnwqqAGYP9wCmWeFh6ykNO8Nn78VQ5w.7Owzuxy9ES7HTxAubjRQi', 'Arlene Umali', 'arleneumali@example.com', '09441234567', 'agent', NULL, 1, '2025-05-19 02:24:33'),
(31, 12, 'mannyviolenta.innersparc', '$2y$10$q4AysMNRb0HqK6DV.BSHV.rTaEl86RZI2d1SG/24Sgnkg/G7QUfBi', 'Manny Alberto Violenta', 'violentamanny@gmail.com', '09451234567', 'manager', NULL, 1, '2025-05-19 02:28:42'),
(32, 12, 'annalynviolenta.innersparc', '$2y$10$V/Znwb0nP.g1kKNUqZdMyOhPNKu2Z7A2FEmCReSfVeUy5ogANgND2', 'Annalyn Salting Violenta', 'anniemazing2@gmail.com', '09461234567', 'agent', NULL, 1, '2025-05-19 02:30:01'),
(33, 12, 'anelatabuyan.innersparc', '$2y$10$6ZN3s6dR/KaXtstCjmUpuO.4zc4pcfp9sXCCyelzuENqygUA4FxDa', 'Anela Dela Cruz Tabuyan', 'nela.tab5@gmail.com', '09471234567', 'agent', NULL, 1, '2025-05-19 02:30:31'),
(34, 12, 'jocelynsantos.innersparc', '$2y$10$NiykEjnqVjwx5muaon0Jj.EeIC9shVUcnTvUJk.gk.OB3iEQEAdSO', 'Jocelyn Santos', 'jhoymsantos15@gmail.com', '09481234567', 'agent', NULL, 1, '2025-05-19 02:30:57'),
(35, 12, 'lenilyntimajo.innersparc', '$2y$10$bEn5TV2cX/RhHX28meGlK.OY.XDhKJ2FKhQDCPyW8Urc9V4G2ciZm', 'Lenily Rana Timajo', 'timajolenily@gmail.com', '09491234567', 'supervisor', NULL, 1, '2025-05-19 02:31:24'),
(36, 12, 'jerusalinosantos', '$2y$10$hYIses3VZ0VyVq9iFYUYeOrjIwkvzoeZlN9spuoOv6bgXHRZzDGwW', 'Jerusalino Tan Santos', 'jerometsantos28@gmail.com', '09501234567', 'supervisor', NULL, 1, '2025-05-19 02:32:16'),
(37, 12, 'novelynbualat.innersparc', '$2y$10$9QLMoE7w01X5z81AVAx6Bu2DX/AmlL8oJM8d4IhN75ZB4LwTWXn0K', 'Novelyn Macalam Bualat', 'novelynbualat01@gmail.com', '09511234567', 'agent', NULL, 1, '2025-05-19 02:33:04'),
(38, 12, 'edenrosedemerin.innersparc', '$2y$10$H75lWP/UOgcRwFOWNafMqONdmHhER8ZmXIenG3PTgPrXJocXQYYya', 'Eden Rose Ramos Demerin', 'apostolerogalapino@gmail.com', '09521234567', 'supervisor', NULL, 1, '2025-05-19 02:33:27');

-- Insert Developers
INSERT INTO `developers` (`id`, `name`, `description`, `contact_person`, `contact_email`, `contact_phone`, `is_active`, `created_at`) VALUES
(1, 'Lancaster', 'Premium residential developments with modern amenities', 'John Lancaster', 'contact@lancaster.com', '02-8123-4567', 1, '2025-05-16 02:45:20'),
(2, 'Antipolo Heights', 'Scenic hillside properties with panoramic views', 'Maria Santos', 'info@antipoloheights.com', '02-8234-5678', 1, '2025-05-16 02:45:20'),
(3, 'Pleasantfields', 'Family-oriented communities with green spaces', 'Robert Cruz', 'sales@pleasantfields.com', '02-8345-6789', 1, '2025-05-16 02:45:20'),
(4, 'Bellefort Estate', 'Luxury gated communities with world-class facilities', 'Catherine Belle', 'luxury@bellefort.com', '02-8456-7890', 1, '2025-05-19 01:30:11'),
(6, 'Elisa Homes', 'Affordable housing solutions for growing families', 'Elisa Rodriguez', 'homes@elisa.com', '02-8567-8901', 1, '2025-05-19 01:32:10'),
(7, 'Minami Residence', 'Japanese-inspired modern living spaces', 'Takeshi Minami', 'residence@minami.com', '02-8678-9012', 1, '2025-05-19 01:45:53'),
(8, 'Anyana', 'Contemporary urban developments', 'Anna Reyes', 'urban@anyana.com', '02-8789-0123', 1, '2025-05-19 01:47:16'),
(9, 'Kathleen Place 5', 'Mid-rise condominium developments', 'Kathleen Torres', 'condo@kathleenplace.com', '02-8890-1234', 1, '2025-05-19 01:50:22'),
(10, 'Liora Homes', 'Sustainable and eco-friendly housing', 'David Liora', 'eco@liorahomes.com', '02-8901-2345', 1, '2025-05-19 01:50:37'),
(11, 'Avida', 'Trusted name in quality residential developments', 'Michael Avida', 'quality@avida.com', '02-8012-3456', 1, '2025-05-19 02:50:31');

-- Insert Project Models
INSERT INTO `project_models` (`id`, `developer_id`, `name`, `description`, `base_price`, `floor_area`, `lot_area`, `bedrooms`, `bathrooms`, `is_active`, `created_at`) VALUES
(1, 1, 'Alice', 'Elegant 2-bedroom townhouse with modern amenities', 2500000.00, 85.50, 120.00, 2, 2, 1, '2025-05-16 02:45:20'),
(9, 3, 'Kennedy', 'Spacious 3-bedroom single detached home', 3200000.00, 120.00, 200.00, 3, 2, 1, '2025-05-16 02:45:20'),
(10, 3, 'Nixon', 'Premium 4-bedroom house with garden', 4500000.00, 150.00, 250.00, 4, 3, 1, '2025-05-16 02:45:20'),
(11, 3, 'Lincoln', 'Executive 5-bedroom family home', 6000000.00, 200.00, 300.00, 5, 4, 1, '2025-05-16 02:45:20'),
(13, 4, 'Vivienne', 'Luxury 3-bedroom villa with pool', 8500000.00, 180.00, 400.00, 3, 3, 1, '2025-05-19 01:31:02'),
(14, 4, 'Sabine', 'Grand 4-bedroom mansion with amenities', 12000000.00, 250.00, 500.00, 4, 4, 1, '2025-05-19 01:31:37'),
(17, 2, 'Lot Only', 'Prime residential lot for custom building', 1500000.00, 0.00, 150.00, 0, 0, 1, '2025-05-19 01:45:36'),
(18, 7, 'Hana', 'Japanese-inspired 2-bedroom home', 3800000.00, 95.00, 140.00, 2, 2, 1, '2025-05-19 01:45:59'),
(19, 6, 'Dahlia', 'Cozy 2-bedroom starter home', 1800000.00, 65.00, 100.00, 2, 1, 1, '2025-05-19 01:46:46'),
(20, 6, 'Pearl', 'Comfortable 3-bedroom family home', 2400000.00, 85.00, 120.00, 3, 2, 1, '2025-05-19 01:46:57'),
(21, 8, 'New York', 'Urban-style 1-bedroom loft', 2800000.00, 45.00, 0.00, 1, 1, 1, '2025-05-19 01:47:24'),
(22, 8, 'Tokyo', 'Compact 2-bedroom city unit', 3500000.00, 65.00, 0.00, 2, 1, 1, '2025-05-19 01:47:30'),
(23, 8, 'Sydney', 'Spacious 3-bedroom penthouse', 5200000.00, 95.00, 0.00, 3, 2, 1, '2025-05-19 01:47:36'),
(24, 10, 'Amora', 'Eco-friendly 2-bedroom sustainable home', 2200000.00, 75.00, 110.00, 2, 2, 1, '2025-05-19 01:50:49'),
(25, 11, 'Way', 'Modern 3-bedroom townhouse', 3000000.00, 100.00, 150.00, 3, 2, 1, '2025-05-19 02:50:41');

-- Insert Leads
INSERT INTO `leads` (`id`, `user_id`, `client_name`, `phone`, `email`, `facebook`, `linkedin`, `address`, `temperature`, `status`, `source`, `developer`, `project_model`, `price`, `commission_rate`, `expected_commission`, `remarks`, `follow_up_date`, `created_at`, `updated_at`) VALUES
(6, 24, 'Leonard Pistano', '0919993939393', 'leonard10238983@gmail.com', 'fb.com/leonard.pistano', '', 'Quezon City, Metro Manila', 'Warm', 'Closed Deal', 'Facebook Groups', 'Antipolo Heights', 'Lot Only', 10000000.00, 3.50, 350000.00, 'Client successfully closed the deal', NULL, '2025-05-19 02:53:41', '2025-05-28 05:24:13'),
(7, 15, 'Jerome Badua', '09292992929', 'jerome@gmail.com', 'fb.com/jerome.badua', 'linkedin.com/in/jerome-badua', 'Makati City, Metro Manila', 'Cold', 'Downpayment Stage', 'Landing Page', 'Avida', 'Way', 3000000.00, 2.50, 75000.00, 'Client is proceeding with downpayment', '2025-06-15', '2025-05-21 05:03:02', '2025-05-21 09:32:51'),
(8, 15, 'Daniel Boni Pagilagan', '0919191919292', 'danielbonipagilagan@gmail.com', '', '', 'Pasig City, Metro Manila', 'Hot', 'Downpayment Stage', 'Facebook Groups', 'Lancaster', 'Alice', 2500000.00, 3.00, 75000.00, 'Follow up this client within the day. Very interested buyer!', '2025-06-10', '2025-05-21 09:37:48', '2025-05-21 09:40:17'),
(9, 30, 'Marvey Yenzo', '09191919191', 'yenz@gmail.com', 'fb.com/marvey.yenzo', '', 'Taguig City, Metro Manila', 'Hot', 'Downpayment Stage', 'Facebook Groups', 'Elisa Homes', 'Dahlia', 1800000.00, 4.00, 72000.00, 'Very motivated buyer, ready to proceed', '2025-06-08', '2025-05-22 15:12:55', '2025-05-28 05:27:10'),
(10, 15, 'Alexander Johnson', '0912383838', 'alex.johnson@gmail.com', '', 'linkedin.com/in/alex-johnson', 'Mandaluyong City, Metro Manila', 'Warm', 'Downpayment Stage', 'Facebook Groups', 'Anyana', 'New York', 2800000.00, 2.75, 77000.00, 'Client interested in urban lifestyle', '2025-06-12', '2025-05-28 03:34:27', '2025-05-28 03:34:36'),
(11, 15, 'Maria Santos', '12312312312', 'maria.santos@gmail.com', 'fb.com/maria.santos', '', 'San Juan City, Metro Manila', 'Warm', 'Downpayment Stage', 'Facebook Groups', 'Liora Homes', 'Amora', 2200000.00, 3.25, 71500.00, 'Eco-conscious buyer looking for sustainable options', '2025-06-14', '2025-05-28 03:42:13', '2025-05-28 03:42:13');

-- Insert Downpayment Tracker
INSERT INTO `downpayment_tracker` (`id`, `lead_id`, `reservation_date`, `requirements_complete`, `spot_dp`, `spot_dp_amount`, `dp_terms`, `monthly_dp_amount`, `current_dp_stage`, `total_dp_stages`, `total_dp_paid`, `remaining_dp_balance`, `pagibig_bank_approval`, `loan_amount`, `loan_takeout`, `loan_takeout_date`, `turnover`, `turnover_date`, `progress_rate`, `next_payment_date`, `created_at`, `updated_at`) VALUES
(1, 6, '2025-05-19', 1, 1, 500000.00, '6', 83333.33, 6, 6, 1000000.00, 0.00, 1, 8500000.00, 1, '2025-05-25', 1, '2025-05-28', 100.00, NULL, '2025-05-19 02:53:41', '2025-05-28 05:24:13'),
(2, 7, '2025-05-21', 0, 0, 0.00, '12', 25000.00, 1, 12, 25000.00, 275000.00, 0, 2400000.00, 0, NULL, 0, NULL, 8.33, '2025-06-21', '2025-05-21 05:03:02', '2025-05-21 09:32:51'),
(3, 8, '2025-05-21', 1, 1, 125000.00, '6', 20833.33, 2, 6, 145833.33, 104166.67, 0, 2000000.00, 0, NULL, 0, NULL, 58.33, '2025-06-21', '2025-05-21 09:37:48', '2025-05-21 09:40:17'),
(4, 9, '2025-05-22', 1, 1, 90000.00, '6', 15000.00, 3, 6, 120000.00, 60000.00, 0, 1440000.00, 0, NULL, 0, NULL, 66.67, '2025-06-22', '2025-05-22 15:12:55', '2025-05-28 05:27:10'),
(5, 10, '2025-05-28', 0, 0, 0.00, '9', 31111.11, 1, 9, 31111.11, 248888.89, 0, 2240000.00, 0, NULL, 0, NULL, 11.11, '2025-06-28', '2025-05-28 03:34:27', '2025-05-28 03:34:36'),
(6, 11, '2025-05-28', 0, 0, 0.00, '12', 18333.33, 1, 12, 18333.33, 201666.67, 0, 1760000.00, 0, NULL, 0, NULL, 8.33, '2025-06-28', '2025-05-28 03:42:13', '2025-05-28 03:42:13');

-- Insert Lead Activities
INSERT INTO `lead_activities` (`id`, `lead_id`, `user_id`, `activity_type`, `notes`, `scheduled_date`, `completed_date`, `is_completed`, `created_at`) VALUES
(1, 6, 24, 'Follow Up', 'Followed up with client via phone call. Client confirmed interest in proceeding.', '2025-05-19 10:00:00', '2025-05-19 10:30:00', 1, '2025-05-19 02:53:41'),
(2, 7, 15, 'Initial Contact', 'Initial contact made via email. Sent property brochures and pricing information.', '2025-05-21 09:00:00', '2025-05-21 09:15:00', 1, '2025-05-21 05:03:02'),
(3, 8, 15, 'Site Tour', 'Conducted comprehensive site tour for the client. Very positive feedback received.', '2025-05-21 14:00:00', '2025-05-21 16:00:00', 1, '2025-05-21 09:37:48'),
(4, 9, 30, 'Negotiation', 'Negotiated terms with the client. Agreed on payment schedule and terms.', '2025-05-22 11:00:00', '2025-05-22 12:00:00', 1, '2025-05-22 15:12:55'),
(5, 10, 15, 'Follow Up', 'Followed up with client via email. Sent additional property information.', '2025-05-28 10:00:00', '2025-05-28 10:30:00', 1, '2025-05-28 03:34:27'),
(6, 11, 15, 'Initial Contact', 'Initial contact made via phone call. Scheduled property viewing appointment.', '2025-05-28 15:00:00', '2025-05-28 15:20:00', 1, '2025-05-28 03:42:13');

-- Insert Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_editable`) VALUES
('company_name', 'InnerSparc Real Estate Services', 'string', 'Company name displayed throughout the application', 1),
('company_email', 'innersparcservices@gmail.com', 'string', 'Primary company email address', 1),
('company_phone', '02-8123-4567', 'string', 'Primary company phone number', 1),
('company_address', 'Metro Manila, Philippines', 'string', 'Company business address', 1),
('default_commission_rate', '3.00', 'number', 'Default commission rate percentage for new leads', 1),
('currency_symbol', '₱', 'string', 'Currency symbol used in the application', 1),
('date_format', 'Y-m-d', 'string', 'Default date format for the application', 1),
('timezone', 'Asia/Manila', 'string', 'Application timezone', 1),
('max_file_upload_size', '10485760', 'number', 'Maximum file upload size in bytes (10MB)', 1),
('lead_follow_up_days', '7', 'number', 'Default number of days for lead follow-up reminders', 1),
('backup_retention_days', '30', 'number', 'Number of days to retain database backups', 1),
('enable_email_notifications', '1', 'boolean', 'Enable email notifications for important events', 1),
('enable_sms_notifications', '0', 'boolean', 'Enable SMS notifications for important events', 1),
('app_version', '2.0.0', 'string', 'Current application version', 0),
('maintenance_mode', '0', 'boolean', 'Enable maintenance mode to restrict access', 1);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create useful views for reporting
CREATE VIEW `active_leads_summary` AS
SELECT 
    l.id,
    l.client_name,
    l.phone,
    l.email,
    l.temperature,
    l.status,
    l.source,
    l.developer,
    l.project_model,
    l.price,
    l.expected_commission,
    u.name as agent_name,
    t.name as team_name,
    l.follow_up_date,
    l.created_at
FROM leads l
JOIN users u ON l.user_id = u.id
LEFT JOIN teams t ON u.team_id = t.id
WHERE l.status NOT IN ('Closed Deal', 'Lost')
ORDER BY l.follow_up_date ASC, l.created_at DESC;

CREATE VIEW `team_performance_summary` AS
SELECT 
    t.id as team_id,
    t.name as team_name,
    COUNT(DISTINCT u.id) as total_agents,
    COUNT(l.id) as total_leads,
    COUNT(CASE WHEN l.status = 'Closed Deal' THEN 1 END) as closed_deals,
    SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_sales,
    SUM(CASE WHEN l.status = 'Closed Deal' THEN l.expected_commission ELSE 0 END) as total_commission,
    ROUND(COUNT(CASE WHEN l.status = 'Closed Deal' THEN 1 END) * 100.0 / NULLIF(COUNT(l.id), 0), 2) as conversion_rate
FROM teams t
LEFT JOIN users u ON t.id = u.team_id AND u.role IN ('agent', 'supervisor', 'manager')
LEFT JOIN leads l ON u.id = l.user_id
GROUP BY t.id, t.name
ORDER BY total_sales DESC;

CREATE VIEW `monthly_sales_report` AS
SELECT 
    YEAR(l.updated_at) as year,
    MONTH(l.updated_at) as month,
    MONTHNAME(l.updated_at) as month_name,
    COUNT(CASE WHEN l.status = 'Closed Deal' THEN 1 END) as deals_closed,
    SUM(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE 0 END) as total_sales,
    SUM(CASE WHEN l.status = 'Closed Deal' THEN l.expected_commission ELSE 0 END) as total_commission,
    AVG(CASE WHEN l.status = 'Closed Deal' THEN l.price ELSE NULL END) as average_deal_size
FROM leads l
WHERE l.status = 'Closed Deal'
GROUP BY YEAR(l.updated_at), MONTH(l.updated_at)
ORDER BY year DESC, month DESC;
