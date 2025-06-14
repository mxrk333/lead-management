-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 28, 2025 at 07:40 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `real_estate_leads`
--

-- --------------------------------------------------------

--
-- Table structure for table `developers`
--

CREATE TABLE `developers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `developers`
--

INSERT INTO `developers` (`id`, `name`, `created_at`) VALUES
(1, 'Lancaster', '2025-05-16 02:45:20'),
(2, 'Antipolo Heights', '2025-05-16 02:45:20'),
(3, 'Pleasantfields', '2025-05-16 02:45:20'),
(4, 'Bellefort Estate', '2025-05-19 01:30:11'),
(6, 'Elisa Homes', '2025-05-19 01:32:10'),
(7, 'Minami Residence', '2025-05-19 01:45:53'),
(8, 'Anyana', '2025-05-19 01:47:16'),
(9, 'Kathleen Place 5', '2025-05-19 01:50:22'),
(10, 'Liora Homes', '2025-05-19 01:50:37'),
(11, 'Avida', '2025-05-19 02:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `downpayment_tracker`
--

CREATE TABLE `downpayment_tracker` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `downpayment_tracker`
--

INSERT INTO `downpayment_tracker` (`id`, `lead_id`, `reservation_date`, `requirements_complete`, `spot_dp`, `dp_terms`, `current_dp_stage`, `total_dp_stages`, `pagibig_bank_approval`, `loan_takeout`, `turnover`, `progress_rate`, `created_at`, `updated_at`) VALUES
(1, 7, '2025-05-22', 1, 0, '15', 15, 15, 1, 1, 1, 100.00, '2025-05-21 08:12:15', '2025-05-21 09:34:53'),
(2, 6, NULL, 1, 0, '24', 7, 24, 1, 1, 0, 60.00, '2025-05-21 08:28:07', '2025-05-23 01:29:48'),
(3, 8, '2025-05-21', 1, 0, '12', 12, 12, 1, 1, 1, 100.00, '2025-05-21 09:41:30', '2025-05-21 13:10:51'),
(4, 10, '2025-05-14', 0, 1, '6', 1, 1, 0, 0, 0, 20.00, '2025-05-28 03:34:53', '2025-05-28 03:34:53'),
(5, 9, '2025-05-28', 1, 1, '6', 1, 1, 1, 1, 1, 100.00, '2025-05-28 05:27:35', '2025-05-28 05:27:41');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `user_id`, `client_name`, `phone`, `email`, `facebook`, `linkedin`, `temperature`, `status`, `source`, `developer`, `project_model`, `price`, `remarks`, `created_at`, `updated_at`) VALUES
(6, 24, 'Leonard Pistano', '0919993939393', 'leoanrd10238983@gmail.com', '', '', 'Warm', 'Closed Deal', 'Facebook Groups', 'Antipolo Heights', 'Lot Only', 10000000.00, 'none', '2025-05-19 02:53:41', '2025-05-28 05:24:13'),
(7, 15, 'Jerome Badua ', '09292992929', 'jerome@gmail.com', 'fb.com', 'linkedin.com', 'Cold', 'Downpayment Stage', 'Landing Page', 'Avida', 'Way', 0.00, '0.00', '2025-05-21 05:03:02', '2025-05-21 09:32:51'),
(8, 15, 'Daniel Boni Pagilagan', '0919191919292', 'danielbonipagilagan@gmail.com', '', '', 'Hot', 'Downpayment Stage', 'Facebook Groups', 'Lancaster', 'Alice', 2000000.00, 'Follow up this client within the day. Pls thanks! ', '2025-05-21 09:37:48', '2025-05-21 09:40:17'),
(9, 30, 'Marvey Yenzo', '09191919', 'yenz@gmail.com', '', '', 'Hot', 'Downpayment Stage', 'Facebook Groups', 'Elisa Homes', 'Dahlia', 2000000.00, 'yes yes yes ', '2025-05-22 15:12:55', '2025-05-28 05:27:10'),
(10, 15, 'ajklsdjaskldj', '0912383838', 'aksdj@gmail.com', '', '', 'Warm', 'Downpayment Stage', 'Facebook Groups', 'Anyana', 'New York', 5000000.00, '2323123', '2025-05-28 03:34:27', '2025-05-28 03:34:36'),
(11, 15, 'dasasdasd', '12312312312', '12qwd@gmail.com', '', '', 'Warm', 'Downpayment Stage', 'Facebook Groups', 'Liora Homes', 'Amora', 120000.00, 'asdasda', '2025-05-28 03:42:13', '2025-05-28 03:42:13');

-- --------------------------------------------------------

--
-- Table structure for table `lead_activities`
--

CREATE TABLE `lead_activities` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `notes` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_activities`
--

INSERT INTO `lead_activities` (`id`, `lead_id`, `user_id`, `activity_type`, `notes`, `created_at`) VALUES
(16, 6, 15, 'Status Change', 'Status changed from  to Site Tour', '2025-05-19 06:19:39'),
(17, 6, 15, 'Status Change', 'Status changed from  to Loan Approval', '2025-05-19 06:21:25'),
(18, 6, 15, 'Status Change', 'Status changed from  to Requirement Stage', '2025-05-19 06:21:59'),
(19, 7, 15, 'Email', 'Emailing the client and sending the details about the inquired model\r\n', '2025-05-21 06:00:56'),
(26, 7, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-21 09:34:41'),
(27, 7, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-21 09:34:53'),
(28, 8, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-21 09:41:30'),
(29, 8, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-21 09:45:13'),
(30, 8, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-21 13:10:25'),
(31, 8, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-21 13:10:39'),
(32, 8, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-21 13:10:51'),
(33, 6, 24, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-23 01:29:48'),
(34, 10, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-28 03:34:53'),
(35, 9, 30, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-28 05:27:35'),
(36, 9, 30, 'Downpayment Tracker', 'Updated downpayment tracker information', '2025-05-28 05:27:41');

-- --------------------------------------------------------

--
-- Table structure for table `memos`
--

CREATE TABLE IF NOT EXISTS `memos` (
  `memo_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`memo_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `memos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `memo_images`
--

CREATE TABLE IF NOT EXISTS `memo_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `memo_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `memo_id` (`memo_id`),
  CONSTRAINT `memo_images_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`memo_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `memo_visibility`
--

CREATE TABLE `memo_visibility` (
  `visibility_id` int(11) NOT NULL,
  `memo_id` int(11) NOT NULL,
  `visible_to_role` enum('manager','supervisor','agent') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_models`
--

CREATE TABLE `project_models` (
  `id` int(11) NOT NULL,
  `developer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_models`
--

INSERT INTO `project_models` (`id`, `developer_id`, `name`, `created_at`) VALUES
(1, 1, 'Alice', '2025-05-16 02:45:20'),
(9, 3, 'Kennedy', '2025-05-16 02:45:20'),
(10, 3, 'Nixon', '2025-05-16 02:45:20'),
(11, 3, 'Lincoln', '2025-05-16 02:45:20'),
(13, 4, 'Vivienne', '2025-05-19 01:31:02'),
(14, 4, 'Sabine', '2025-05-19 01:31:37'),
(17, 2, 'Lot Only', '2025-05-19 01:45:36'),
(18, 7, 'Hana', '2025-05-19 01:45:59'),
(19, 6, 'Dahlia', '2025-05-19 01:46:46'),
(20, 6, 'Pearl', '2025-05-19 01:46:57'),
(21, 8, 'New York', '2025-05-19 01:47:24'),
(22, 8, 'Tokyo', '2025-05-19 01:47:30'),
(23, 8, 'Sydney', '2025-05-19 01:47:36'),
(24, 10, 'Amora', '2025-05-19 01:50:49'),
(25, 11, 'Way', '2025-05-19 02:50:41');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_description` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `setting_group`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'company_name', 'Inner SPARC Realty Corporation', 'Company name displayed in the system', 'general', 1, '2025-05-16 03:28:39', '2025-05-17 17:03:08'),
(2, 'company_email', 'innersparcrealtyservices@gmail.com', 'Default company email address', 'general', 1, '2025-05-16 03:28:39', '2025-05-17 17:03:22'),
(3, 'company_phone', '(046)458-0706', 'Company contact phone number', 'general', 1, '2025-05-16 03:28:39', '2025-05-17 17:03:22'),
(4, 'company_address', 'Blk 26 Lot 4 Phase 3, Avida Residences Sta. Catalina, Brgy. Salawag Dasmarinas, Cavite, Philippines', 'Company physical address', 'general', 1, '2025-05-16 03:28:39', '2025-05-17 17:02:50'),
(5, 'company_logo', 'assets/img/logo.png', 'Path to company logo image', 'general', 1, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(6, 'date_format', 'Y-m-d', 'Default date format for the system', 'general', 1, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(7, 'time_format', 'H:i', 'Default time format for the system', 'general', 1, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(8, 'timezone', 'Asia/Manila', 'Default timezone for the system', 'general', 1, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(9, 'lead_auto_assign', '0', 'Automatically assign leads to agents (0=off, 1=on)', 'leads', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(10, 'lead_assignment_method', 'round_robin', 'Method for auto-assigning leads (round_robin, random, load_balanced)', 'leads', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(11, 'lead_follow_up_days', '3', 'Default number of days for lead follow-up reminder', 'leads', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(12, 'lead_status_colors', '{\"Inquiry\":\"#f6c23e\",\"Presentation Stage\":\"#36b9cc\",\"Negotiation\":\"#4e73df\",\"Closed\":\"#1cc88a\",\"Lost\":\"#e74a3b\"}', 'Color codes for lead status labels', 'leads', 1, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(13, 'lead_temperature_colors', '{\"Hot\":\"#e74a3b\",\"Warm\":\"#f6c23e\",\"Cold\":\"#4e73df\"}', 'Color codes for lead temperature labels', 'leads', 1, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(14, 'smtp_host', 'smtp.example.com', 'SMTP server hostname', 'email', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(15, 'smtp_port', '587', 'SMTP server port', 'email', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(16, 'smtp_username', 'innersparcrealtyservices@gmail.com', 'SMTP username', 'email', 0, '2025-05-16 03:28:39', '2025-05-17 17:03:48'),
(17, 'smtp_password', 'password', 'SMTP password', 'email', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(18, 'smtp_encryption', 'tls', 'SMTP encryption method (tls, ssl)', 'email', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(19, 'email_from_name', 'Inner SPARC Realty Corpoation', 'From name for system emails', 'email', 0, '2025-05-16 03:28:39', '2025-05-17 17:03:48'),
(20, 'email_from_address', 'innersparcrealtyservices@gmail.com', 'From email address for system emails', 'email', 0, '2025-05-16 03:28:39', '2025-05-17 17:03:48'),
(21, 'password_min_length', '8', 'Minimum password length', 'security', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(22, 'password_requires_special', '1', 'Require special characters in passwords (0=no, 1=yes)', 'security', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(23, 'password_requires_number', '1', 'Require numbers in passwords (0=no, 1=yes)', 'security', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(24, 'password_requires_uppercase', '1', 'Require uppercase letters in passwords (0=no, 1=yes)', 'security', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(25, 'session_timeout', '30', 'Session timeout in minutes', 'security', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(26, 'max_login_attempts', '5', 'Maximum failed login attempts before lockout', 'security', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(27, 'lockout_time', '15', 'Account lockout time in minutes after failed attempts', 'security', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(28, 'enable_email_notifications', '1', 'Enable email notifications (0=off, 1=on)', 'notifications', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(29, 'enable_browser_notifications', '1', 'Enable browser notifications (0=off, 1=on)', 'notifications', 1, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(30, 'notify_on_new_lead', '1', 'Send notification on new lead (0=off, 1=on)', 'notifications', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(31, 'notify_on_lead_update', '1', 'Send notification on lead update (0=off, 1=on)', 'notifications', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(32, 'notify_on_lead_assignment', '1', 'Send notification on lead assignment (0=off, 1=on)', 'notifications', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(33, 'enable_developer_tools', '0', 'Enable developer tools and debugging (0=off, 1=on)', 'developer', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(34, 'log_level', 'error', 'Log level (error, warning, info, debug)', 'developer', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(35, 'maintenance_mode', '0', 'Put system in maintenance mode (0=off, 1=on)', 'developer', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39'),
(36, 'maintenance_message', 'System is currently under maintenance. Please check back later.', 'Message displayed during maintenance mode', 'developer', 0, '2025-05-16 03:28:39', '2025-05-16 03:28:39');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `name`, `created_at`) VALUES
(1, 'Blazing SPARCS', '2025-05-16 02:45:20'),
(2, 'Feisty Heroine', '2025-05-16 02:45:20'),
(3, 'Shinning Phoenix', '2025-05-16 02:45:20'),
(8, 'Flameborn Champions', '2025-05-16 02:45:20'),
(12, 'Fiery Achievers', '2025-05-19 02:25:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','supervisor','agent') NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `team_id`, `username`, `password`, `name`, `email`, `phone`, `role`, `profile_picture`, `created_at`) VALUES
(1, NULL, 'admin', '$2y$10$3iuZEf0f2.uXqGinef6fnOWVGjCYMaP27pTPELt3kPwaUm2xEqeGS', 'Administrator', 'innersparcservices@gmail.com', NULL, 'admin', NULL, '2025-05-16 02:45:20'),
(3, 8, 'shielamae.innersparc', '$2y$10$gbLWkM8Lc//nUt5oexmLXOkZSG6PVoIaSMntaSqlgqCB1mlLFRVeG', 'Sheila Mae Fontelo Fajutagana', 'francisheila05@gmail.com', NULL, 'agent', NULL, '2025-05-16 02:45:21'),
(4, 2, 'luzvimindalim.innersparc', '$2y$10$PUV7H0RlWs.dJZ6x8SqmvOFy3x9MlJIo0m7otynHJfAxHnktLhC4u', 'Luzviminda Labrado Lim', 'luz032069@gmail.com', NULL, 'supervisor', NULL, '2025-05-16 02:45:21'),
(5, 3, 'manager3', '$2y$10$rRuVdtrMXPrd1x.ySHEY3.9Vvjxds.9Js5rCvRPgQG7ug3NzLmMZO', 'Team 3 Manager', 'manager3@example.com', NULL, 'manager', NULL, '2025-05-16 02:45:21'),
(6, 1, 'mikegabrielbedionescarilla.innersparc', '$2y$10$wQ4nzPYxWM.8smNno/0vIOfwSvYWxq3yphH3y8QCXxK68d3i8uSPG', 'Mike Gabriel Bedion Escarilla', 'escarilla.mikegabriel@gmail.com', NULL, 'agent', NULL, '2025-05-16 02:45:21'),
(7, NULL, 'romeojrcernacorberta.itdept', '$2y$10$rRuVdtrMXPrd1x.ySHEY3.9Vvjxds.9Js5rCvRPgQG7ug3NzLmMZO', 'Romeo Jr. Cerna Cobreta', 'romxcob.innersparc@gmail.com', NULL, 'admin', NULL, '2025-05-16 02:45:21'),
(8, 8, 'charlenedellosa.innersparc', '$2y$10$7BvBgpFnDmhhX3/zSVikjebJCWefKLpsg36RYdkM0KhZDmjE/Zmmu', 'Charlene Dellosa', 'dellosacharlene1317@gmail.com', NULL, 'manager', 'uploads/profile_pictures/user_8_1747501769.png', '2019-05-16 02:45:23'),
(9, 2, 'alvinllaneta.innersparc', '$2y$10$.w9.aPUWHSS5GifQHPn9Fu2.JKn817TupAc0xAEDSekCnwFDH1UiW', 'Alvin  Llaneta', 'alvinllaneta8@gmail.com', NULL, 'agent', NULL, '2025-05-16 02:45:23'),
(10, 3, 'supervisor3', '$2y$10$rRuVdtrMXPrd1x.ySHEY3.9Vvjxds.9Js5rCvRPgQG7ug3NzLmMZO', 'Team 3 Supervisor', 'supervisor3@example.com', NULL, 'supervisor', NULL, '2025-05-16 02:45:23'),
(11, 2, 'clarencedanielleserdon.innersparc', '$2y$10$tRBIk1jyaXGFbpAveJE8gOqFpGW.O/hJInU3qJ/mnqr3k/RBneuQK', 'Clarence Danielle Lim Serdon', 'clarencedanielle98@gmail.com', NULL, 'agent', NULL, '2025-05-16 02:45:23'),
(12, 2, 'gabcyrosebenson.innersparc', '$2y$10$GnAbo83uu0hltK474fV1B.It7N5iLugZXtDFd.5vDRuFqso62qcXS', 'Gabcyrose Samsona Benson', 'gabcyrose@gmail.com', NULL, 'agent', NULL, '2025-05-16 02:45:23'),
(13, 1, 'lenizaflorespasion.innersparc', '$2y$10$oHDt9M8XyxG7WGe5TusQluJBQXhGLuIz67T5vXM7YYkcPO1y2cXIq', 'Leniza Flores pasion', 'lenizapasion51@gmail.com', NULL, 'agent', NULL, '2025-05-16 02:45:23'),
(14, 1, 'perlitasantiagogo.innersparc', '$2y$10$p4kmD3n.wjSirRlDZpwRaOz69x2uytmxIQcBsgRTPpJynvZ5o1I76', 'Perlita Santiago Go', 'gopearl43@yahoo.com', NULL, 'agent', NULL, '2025-05-16 02:45:23'),
(15, NULL, 'markpatigayon.itdept', '$2y$10$Tbq4hLp0VyDFdxogyElwm.A52Fh.OLnPqgayZTWrAlY4s.V7XCPdi', 'Mark Christian Patigayon', 'markpatigayon440@gmail.com', NULL, 'admin', 'uploads/profile_pictures/user_15_1747499626.jpg', '2019-05-22 02:45:23'),
(16, 1, 'verlynbizcondevesagas.innersparc', '$2y$10$PEX7IJamKPjc2Mg5859AZuLQjqB/anHrK9ty37.dejDgGTEVazAwy', 'Verlyn Bizconde Vesagas', 'vverlyn@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:08:49'),
(17, 1, 'rizelagrimas.innersparc', '$2y$10$ipV2yK499HzPJRz4Bw8G0OMKotbJFfC37LzMtCn1qPimCph6f9Dmy', 'Rize OwogOwog Lagrimas', 'rizielagrimas18@gmai.com', NULL, 'agent', NULL, '2025-05-19 02:09:39'),
(18, 1, 'ireneblanca.innersparc', '$2y$10$aEQppVOJLQ3050pCQEKrwepJCT8rw3frtf.AqkgMPqw7TjisrYmli', 'Irene Noble Blanca', 'ireneblanca1909@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:10:13'),
(19, 1, 'gabriellibacao.innersparc', '$2y$10$0A.MhdXz2UAcy4bUGR6EBOsQ82F9GtlZLgCw0a0n46KXTUuoM8t8a', 'Gabriel Jr. Villamor Libacao', 'libacaoga@gmail.com', NULL, 'admin', NULL, '2025-05-19 02:10:58'),
(20, 1, 'erwinbauioan.innersparc', '$2y$10$/I4c/Yn7lWXRlraQvK0m2ueNwa2vgAfJ1NSKVG9wNfXJUu51fjoeq', 'Erwin Gonzales Baguioan', 'irwindgonzales6@gmail.com', NULL, 'manager', NULL, '2025-05-19 02:11:50'),
(21, 1, 'nelynortega.innersparc', '$2y$10$pPrIuezkcJ9THUg5IHmFMuo68tv5W5MhSeLarPb4HIFc2rt5Jj8u.', 'Nelyn Serad Ortega', 'orteganelyn18@gmail.com', NULL, 'supervisor', NULL, '2025-05-19 02:12:41'),
(22, 1, 'sarahlopez.innersparc', '$2y$10$HFq2/p7EOrkbn0gFTWoCJOC.0jkJX3rAZj0Rg65xuEfvqa7K3cw7K', 'Sarah Jean Lagatic Lopez', 'sarahjeanlopez07@gmail.com', NULL, 'supervisor', NULL, '2025-05-19 02:13:10'),
(23, 2, 'nephelepanganiban', '$2y$10$uZrVk47hzppnbZsFYK/SWOAhQ5e04n/rqm8ti4Hkp7FgvWjq9WrW6', 'Nephele Telmo Panganiban', 'nephelepanganiban@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:18:33'),
(24, 2, 'joanbarceta.innersparc', '$2y$10$E.XU5PTwBNn6BryqVpHLTOGGmPOz9V1slWHXBZjzg4YeKC00K9o6K', 'Joan Mahinay Barceta', 'jobarceta22@gmail.com', NULL, 'manager', 'uploads/profile_pictures/user_24_1747622923.jpg', '2025-05-19 02:19:01'),
(25, 2, 'teresasandoval.innersparc', '$2y$10$WYxXqgw6uC.9r4ukTgt.SOApN.eDGquy0nbDiJpAkfUHGrMQd2bx2', 'Teresa Rosanto Sandoval', 'trscyl@yahoo.com', NULL, 'supervisor', NULL, '2025-05-19 02:20:20'),
(26, 2, 'ailynmdetorres.innersparc', '$2y$10$wNLuyURAbK/f1g53h1zuRu.XCIA5u/iFJqU529aWrzYijfu80iIwC', 'Ailyn Llaneta De Torres', 'ailyndetorres8@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:21:11'),
(27, 2, 'emilyncantuba.innersparc', '$2y$10$AL8RtPNTg4fhzeCoe8paGuFoFKQspNTe4WuvQmieLbw0BW06OyW/W', 'Emilyn Marcelo Cantuba', 'cantubaemhie@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:21:46'),
(28, 2, 'novelitatabudlong.innersparc', '$2y$10$VzOywJiBE7vbcCCgyXyqOO8r5qMiKWJfqXZmINuqXCvyziXMjM3wi', 'Novelita  Letran Tabudlong', 'novzpretty@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:22:15'),
(29, 8, 'leodellosa.innersparc', '$2y$10$Cv6hsi.jSxoikgaDi275KO6HQstKWsWIOLA.vIQUfBVCW8BJIX8.i', 'Leo Dellosa', 'leodellosa@example.com', NULL, 'agent', NULL, '2025-05-19 02:22:59'),
(30, 8, 'arleneumali.innersparc', '$2y$10$bnwqqAGYP9wCmWeFh6ykNO8Nn78VQ5w.7Owzuxy9ES7HTxAubjRQi', 'Arlene Umali', 'arleneumali@example.com', NULL, 'agent', NULL, '2025-05-19 02:24:33'),
(31, 12, 'mannyviolenta.innersparc', '$2y$10$q4AysMNRb0HqK6DV.BSHV.rTaEl86RZI2d1SG/24Sgnkg/G7QUfBi', 'Manny Alberto Violenta', 'violentamanny@gmail.com', NULL, 'manager', NULL, '2025-05-19 02:28:42'),
(32, 12, 'annalynviolenta.innersparc', '$2y$10$V/Znwb0nP.g1kKNUqZdMyOhPNKu2Z7A2FEmCReSfVeUy5ogANgND2', 'Annalyn Salting Violenta', 'anniemazing2@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:30:01'),
(33, 12, 'anelatabuyan.innersparc', '$2y$10$6ZN3s6dR/KaXtstCjmUpuO.4zc4pcfp9sXCCyelzuENqygUA4FxDa', 'Anela Dela Cruz Tabuyan', 'nela.tab5@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:30:31'),
(34, 12, 'jocelynsantos.innersparc', '$2y$10$NiykEjnqVjwx5muaon0Jj.EeIC9shVUcnTvUJk.gk.OB3iEQEAdSO', 'Jocelyn Santos', 'jhoymsantos15@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:30:57'),
(35, 12, 'lenilyntimajo.innersparc', '$2y$10$bEn5TV2cX/RhHX28meGlK.OY.XDhKJ2FKhQDCPyW8Urc9V4G2ciZm', 'Lenily  Rana Timajo', 'timajolenily@gmail.com', NULL, 'supervisor', NULL, '2025-05-19 02:31:24'),
(36, 12, 'jerusalinosantos', '$2y$10$hYIses3VZ0VyVq9iFYUYeOrjIwkvzoeZlN9spuoOv6bgXHRZzDGwW', 'Jerusalino Tan Santos', 'jerometsantos28@gmail.com', NULL, 'supervisor', NULL, '2025-05-19 02:32:16'),
(37, 12, 'novelynbualat.innersparc', '$2y$10$9QLMoE7w01X5z81AVAx6Bu2DX/AmlL8oJM8d4IhN75ZB4LwTWXn0K', 'Novelyn Macalam  Bualat', 'novelynbualat01@gmail.com', NULL, 'agent', NULL, '2025-05-19 02:33:04'),
(38, 12, 'edenrosedemerin.innersparc', '$2y$10$H75lWP/UOgcRwFOWNafMqONdmHhER8ZmXIenG3PTgPrXJocXQYYya', 'Eden Rose Ramos Demerin', 'apostolerogalapino@gmail.com0', NULL, 'supervisor', NULL, '2025-05-19 02:33:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `developers`
--
ALTER TABLE `developers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `downpayment_tracker`
--
ALTER TABLE `downpayment_tracker`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `lead_activities`
--
ALTER TABLE `lead_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `memos`
--
ALTER TABLE `memos`
  ADD PRIMARY KEY (`memo_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `memo_images`
--
ALTER TABLE `memo_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `memo_id` (`memo_id`);

--
-- Indexes for table `memo_visibility`
--
ALTER TABLE `memo_visibility`
  ADD PRIMARY KEY (`visibility_id`),
  ADD KEY `memo_id` (`memo_id`);

--
-- Indexes for table `project_models`
--
ALTER TABLE `project_models`
  ADD PRIMARY KEY (`id`),
  ADD KEY `developer_id` (`developer_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `team_id` (`team_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `developers`
--
ALTER TABLE `developers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `downpayment_tracker`
--
ALTER TABLE `downpayment_tracker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `lead_activities`
--
ALTER TABLE `lead_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `memos`
--
ALTER TABLE `memos`
  MODIFY `memo_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `memo_images`
--
ALTER TABLE `memo_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `memo_visibility`
--
ALTER TABLE `memo_visibility`
  MODIFY `visibility_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_models`
--
ALTER TABLE `project_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `downpayment_tracker`
--
ALTER TABLE `downpayment_tracker`
  ADD CONSTRAINT `downpayment_tracker_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lead_activities`
--
ALTER TABLE `lead_activities`
  ADD CONSTRAINT `lead_activities_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_activities_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `memos`
--
ALTER TABLE `memos`
  ADD CONSTRAINT `memos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `memo_images`
--
ALTER TABLE `memo_images`
  ADD CONSTRAINT `memo_images_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`memo_id`) ON DELETE CASCADE;

--
-- Constraints for table `memo_visibility`
--
ALTER TABLE `memo_visibility`
  ADD CONSTRAINT `memo_visibility_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`memo_id`) ON DELETE CASCADE;