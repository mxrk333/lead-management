-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: lecain.pdx1-mysql-a7-6b.dreamhost.com
-- Generation Time: Jun 16, 2025 at 02:41 AM
-- Server version: 8.0.28-0ubuntu0.20.04.3
-- PHP Version: 8.1.2-1ubuntu2.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `managementlead`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_leads_summary`
-- (See below for the actual view)
--
CREATE TABLE `active_leads_summary` (
`id` int
,`client_name` varchar(100)
,`phone` varchar(20)
,`email` varchar(100)
,`temperature` enum('Hot','Warm','Cold')
,`status` enum('Inquiry','Presentation Stage','Negotiation','Closed','Lost','Site Tour','Closed Deal','Requirement Stage','Downpayment Stage','Housing Loan Application','Loan Approval','Loan Takeout','House Inspection','House Turn Over')
,`source` enum('Facebook Groups','KKK','Facebook Ads','TikTok ads','Google Ads','Facebook live','Referral','Teleprospecting','Video Message','Organic Posting','Email Marketing','Follow up','Manning','Walk in','Flyering','Chat messaging','Property Listing','Landing Page','Networking Events','Organic Sharing','Youtube Marketing','LinkedIn','Open House')
,`developer` varchar(100)
,`project_model` varchar(100)
,`price` decimal(12,2)
,`expected_commission` decimal(12,2)
,`agent_name` varchar(100)
,`team_name` varchar(100)
,`follow_up_date` date
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `record_id` int DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int NOT NULL,
  `province_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `province_id`, `name`, `created_at`) VALUES
(16, 11, 'Trece Martires', '2025-06-16 03:46:26'),
(17, 11, 'Tanza', '2025-06-16 03:46:30'),
(18, 11, 'Bacoor', '2025-06-16 03:46:34'),
(19, 11, 'Imus', '2025-06-16 03:46:38'),
(20, 12, 'Pilila', '2025-06-16 05:19:24'),
(21, 12, 'Antipolo', '2025-06-16 05:22:43'),
(22, 13, 'Sta. Rosa', '2025-06-16 05:39:52'),
(23, 11, 'Naic', '2025-06-16 05:47:17'),
(24, 11, 'Kawit', '2025-06-16 06:02:28'),
(25, 11, 'Gentri', '2025-06-16 06:06:33');

-- --------------------------------------------------------

--
-- Table structure for table `developers`
--

CREATE TABLE `developers` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `contact_person` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `developers`
--

INSERT INTO `developers` (`id`, `name`, `description`, `contact_person`, `contact_email`, `contact_phone`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Lancaster', 'Premium residential developments with modern amenities', 'John Lancaster', 'contact@lancaster.com', '02-8123-4567', 1, '2025-05-16 09:45:20', '2025-06-06 06:26:13'),
(2, 'Antipolo Heights', 'Scenic hillside properties with panoramic views', 'Maria Santos', 'info@antipoloheights.com', '02-8234-5678', 1, '2025-05-16 09:45:20', '2025-06-06 06:26:13'),
(3, 'Pleasantfields', 'Family-oriented communities with green spaces', 'Robert Cruz', 'sales@pleasantfields.com', '02-8345-6789', 1, '2025-05-16 09:45:20', '2025-06-06 06:26:13'),
(4, 'Bellefort Estate', 'Luxury gated communities with world-class facilities', 'Catherine Belle', 'luxury@bellefort.com', '02-8456-7890', 1, '2025-05-19 08:30:11', '2025-06-06 06:26:13'),
(6, 'Elisa Homes', 'Affordable housing solutions for growing families', 'Elisa Rodriguez', 'homes@elisa.com', '02-8567-8901', 1, '2025-05-19 08:32:10', '2025-06-06 06:26:13'),
(7, 'Minami Residence', 'Japanese-inspired modern living spaces', 'Takeshi Minami', 'residence@minami.com', '02-8678-9012', 1, '2025-05-19 08:45:53', '2025-06-06 06:26:13'),
(8, 'Anyana', 'Contemporary urban developments', 'Anna Reyes', 'urban@anyana.com', '02-8789-0123', 1, '2025-05-19 08:47:16', '2025-06-06 06:26:13'),
(9, 'Kathleen Place 5', 'Mid-rise condominium developments', 'Kathleen Torres', 'condo@kathleenplace.com', '02-8890-1234', 1, '2025-05-19 08:50:22', '2025-06-06 06:26:13'),
(10, 'Liora Homes', 'Sustainable and eco-friendly housing', 'David Liora', 'eco@liorahomes.com', '02-8901-2345', 1, '2025-05-19 08:50:37', '2025-06-06 06:26:13'),
(11, 'Avida', 'Trusted name in quality residential developments', 'Michael Avida', 'quality@avida.com', '02-8012-3456', 1, '2025-05-19 09:50:31', '2025-06-06 06:26:13');

-- --------------------------------------------------------

--
-- Table structure for table `downpayment_tracker`
--

CREATE TABLE `downpayment_tracker` (
  `id` int NOT NULL,
  `lead_id` int NOT NULL,
  `reservation_date` date DEFAULT NULL,
  `requirements_complete` tinyint(1) DEFAULT '0',
  `spot_dp` tinyint(1) DEFAULT '0',
  `spot_dp_amount` decimal(12,2) DEFAULT '0.00',
  `dp_terms` enum('6','9','12','15','18','24','36') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `monthly_dp_amount` decimal(12,2) DEFAULT '0.00',
  `current_dp_stage` int DEFAULT '1',
  `total_dp_stages` int DEFAULT NULL,
  `total_dp_paid` decimal(12,2) DEFAULT '0.00',
  `remaining_dp_balance` decimal(12,2) DEFAULT '0.00',
  `pagibig_bank_approval` tinyint(1) DEFAULT '0',
  `loan_amount` decimal(12,2) DEFAULT '0.00',
  `loan_takeout` tinyint(1) DEFAULT '0',
  `loan_takeout_date` date DEFAULT NULL,
  `turnover` tinyint(1) DEFAULT '0',
  `turnover_date` date DEFAULT NULL,
  `progress_rate` decimal(5,2) DEFAULT '0.00',
  `next_payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `downpayment_tracker`
--

INSERT INTO `downpayment_tracker` (`id`, `lead_id`, `reservation_date`, `requirements_complete`, `spot_dp`, `spot_dp_amount`, `dp_terms`, `monthly_dp_amount`, `current_dp_stage`, `total_dp_stages`, `total_dp_paid`, `remaining_dp_balance`, `pagibig_bank_approval`, `loan_amount`, `loan_takeout`, `loan_takeout_date`, `turnover`, `turnover_date`, `progress_rate`, `next_payment_date`, `created_at`, `updated_at`) VALUES
(7, 25, NULL, 1, 1, 0.00, '6', 0.00, 1, 1, 0.00, 0.00, 1, 0.00, 1, NULL, 1, NULL, 100.00, NULL, '2025-06-06 09:25:10', '2025-06-06 09:26:03'),
(8, 28, NULL, 1, 1, 0.00, '6', 0.00, 1, 1, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 40.00, NULL, '2025-06-09 07:24:54', '2025-06-09 07:24:54'),
(9, 29, NULL, 1, 0, 0.00, '6', 0.00, 6, 6, 0.00, 0.00, 1, 0.00, 1, NULL, 1, NULL, 100.00, NULL, '2025-06-13 02:34:43', '2025-06-13 02:35:34');

-- --------------------------------------------------------

--
-- Table structure for table `handbooks`
--

CREATE TABLE `handbooks` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Uncategorized',
  `cover_image` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `pdf_file` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `handbooks`
--

INSERT INTO `handbooks` (`id`, `title`, `description`, `category`, `cover_image`, `pdf_file`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Handbook: Version 1', '', '', 'uploads/handbook_covers/1749801234_Screenshot 2025-06-13 154516.png', 'uploads/handbook_pdfs/1749801234_Copy of Blue and Gold Modern Simple Professional Employee Handbook Booklet.pdf', 54, '2025-06-13 07:53:54', '2025-06-13 07:53:54'),
(2, 'From Zero to Hero:', 'A Rookie Agent''s 16-step Ultimate Playbook', '', 'uploads/handbook_covers/1750055656_Screenshot 2025-06-16 141054.png', 'uploads/handbook_pdfs/1750055656_Copy of HandBook_20250616_140352_0000 (1).pdf', 15, '2025-06-16 06:34:20', '2025-06-16 06:34:20');

-- --------------------------------------------------------

--
-- Table structure for table `handbook_pages`
--

CREATE TABLE `handbook_pages` (
  `id` int NOT NULL,
  `handbook_id` int NOT NULL,
  `page_number` int NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `caption` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `client_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `facebook` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `linkedin` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `temperature` enum('Hot','Warm','Cold') COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('Inquiry','Presentation Stage','Negotiation','Closed','Lost','Site Tour','Closed Deal','Requirement Stage','Downpayment Stage','Housing Loan Application','Loan Approval','Loan Takeout','House Inspection','House Turn Over') COLLATE utf8mb4_general_ci NOT NULL,
  `source` enum('Facebook Groups','KKK','Facebook Ads','TikTok ads','Google Ads','Facebook live','Referral','Teleprospecting','Video Message','Organic Posting','Email Marketing','Follow up','Manning','Walk in','Flyering','Chat messaging','Property Listing','Landing Page','Networking Events','Organic Sharing','Youtube Marketing','LinkedIn','Open House') COLLATE utf8mb4_general_ci NOT NULL,
  `developer` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `project_model` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT '0.00',
  `expected_commission` decimal(12,2) DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_general_ci,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `user_id`, `client_name`, `phone`, `email`, `facebook`, `linkedin`, `address`, `temperature`, `status`, `source`, `developer`, `project_model`, `price`, `commission_rate`, `expected_commission`, `remarks`, `follow_up_date`, `created_at`, `updated_at`) VALUES
(21, 25, 'Elena Villanueva', '09371234586', 'elena.villanueva@email.com', 'facebook.com/elena.villanueva', 'linkedin.com/in/elena-villanueva', '1717 Katipunan Ave, QC', 'Warm', 'Loan Takeout', 'LinkedIn', 'Liora Homes', 'Amora', 2750000.00, 0.03, 68750.00, 'This is not an accurate data; this is for testing only.', '2025-06-21', '2025-06-21 15:00:00', '2025-06-06 08:05:58'),
(22, 26, 'Rafael Mendoza', '09471234587', 'rafael.mendoza@email.com', 'facebook.com/rafael.mendoza', 'linkedin.com/in/rafael-mendoza', '1818 España St, Manila', 'Cold', 'Loan Approval', 'Referral', 'Lancaster', 'Alice', 2900000.00, 0.03, 87000.00, 'This is not an accurate data; this is for testing only.', '2025-06-24', '2025-06-22 16:30:00', '2025-06-06 08:09:23'),
(23, 27, 'Beatriz Santos', '09571234588', 'beatriz.santos@email.com', 'facebook.com/beatriz.santos', 'linkedin.com/in/beatriz-santos', '1919 Quezon Ave, QC', 'Hot', 'Negotiation', 'Facebook Ads', 'Anyana', 'Sydney', 3200000.00, 0.03, 96000.00, 'This is not an accurate data; this is for testing only.', '2025-06-23', '2025-06-23 17:15:00', '2025-06-06 08:05:15'),
(24, 28, 'Carlos Diaz', '09671234589', 'carlos.diaz@email.com', 'facebook.com/carlos.diaz', 'linkedin.com/in/carlos-diaz', '2020 Magsaysay Blvd, Mandaluyong', 'Warm', 'Negotiation', 'Manning', 'Lancaster', 'Alice', 2600000.00, 0.03, 65000.00, 'This is not an accurate data; this is for testing only.', '2025-06-25', '2025-06-24 15:45:00', '2025-06-06 08:07:16'),
(25, 29, 'Diana Lopez', '09771234590', 'diana.lopez@email.com', 'facebook.com/diana.lopez', 'linkedin.com/in/diana-lopez', '2121 Shaw Blvd, Mandaluyong', 'Cold', 'Downpayment Stage', 'Follow up', 'Minami Residence', 'Hana', 3100000.00, 0.03, 93000.00, 'This is not an accurate data; this is for testing only.', '2025-06-26', '2025-06-25 18:00:00', '2025-06-06 08:07:50'),
(26, 56, 'Dyryyd', 'Yeueyr', 'jericho.innersparc@gmail.com', 'Dududu', 'Ueueueu', NULL, 'Warm', 'Site Tour', 'Facebook Ads', 'Avida', 'Way', 100000.00, 0.00, 0.00, 'Eurufufuf', NULL, '2025-06-09 03:28:39', '2025-06-09 03:28:39'),
(27, 58, 'Phoenix Zeta', '09171520934', 'ginine.innersparc@gmail.com', 'https://www.facebook.com/share/17sb12Bo2u/', '', NULL, 'Hot', 'Closed Deal', 'Facebook Groups', 'Pleasantfields', 'Kennedy', 3900000.00, 0.00, 0.00, 'Keri lang.', NULL, '2025-06-09 03:41:02', '2025-06-09 07:12:01'),
(28, 58, 'seedd', '09171520934', 'ginineangelique9@gmail.com', '', '', NULL, 'Warm', 'Downpayment Stage', 'Google Ads', 'Avida', 'Way', 3900000.00, 0.00, 0.00, '', NULL, '2025-06-09 07:24:12', '2025-06-09 07:24:12'),
(29, 15, 'Mark', '09191823388', 'iasdisj@gmail.com', '', '', NULL, 'Warm', 'Closed Deal', 'Organic Sharing', 'Pleasantfields', 'Kennedy', 19238388.22, 0.00, 0.00, '', NULL, '2025-06-13 02:32:27', '2025-06-13 02:36:01');

-- --------------------------------------------------------

--
-- Table structure for table `lead_activities`
--

CREATE TABLE `lead_activities` (
  `id` int NOT NULL,
  `lead_id` int NOT NULL,
  `user_id` int NOT NULL,
  `activity_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `notes` text COLLATE utf8mb4_general_ci NOT NULL,
  `scheduled_date` datetime DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_activities`
--

INSERT INTO `lead_activities` (`id`, `lead_id`, `user_id`, `activity_type`, `notes`, `scheduled_date`, `completed_date`, `is_completed`, `created_at`) VALUES
(7, 23, 27, 'Lead Update', 'Lead details updated:\n- Changed developer from \'Greenfield Builders\' to \'Anyana\'\n- Changed project_model from \'Model C\' to \'Sydney\'\n', NULL, NULL, 0, '2025-06-06 08:05:15'),
(8, 21, 25, 'Lead Update', 'Lead details updated:\n- Changed developer from \'Greenfield Builders\' to \'Liora Homes\'\n- Changed project_model from \'Model A\' to \'Amora\'\n', NULL, NULL, 0, '2025-06-06 08:05:58'),
(9, 24, 28, 'Lead Update', 'Lead details updated:\n- Changed developer from \'Sunrise Dev\' to \'Lancaster\'\n- Changed project_model from \'Model A\' to \'Alice\'\n', NULL, NULL, 0, '2025-06-06 08:07:16'),
(10, 25, 29, 'Lead Update', 'Lead details updated:\n- Changed developer from \'Greenfield Builders\' to \'Minami Residence\'\n- Changed project_model from \'Model B\' to \'Hana\'\n', NULL, NULL, 0, '2025-06-06 08:07:50'),
(11, 22, 26, 'Lead Update', 'Lead details updated:\n- Changed developer from \'Sunrise Dev\' to \'Lancaster\'\n- Changed project_model from \'Model B\' to \'Alice\'\n', NULL, NULL, 0, '2025-06-06 08:09:23'),
(12, 25, 8, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-06-06 09:25:10'),
(13, 25, 8, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-06-06 09:25:37'),
(14, 25, 8, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-06-06 09:26:03'),
(16, 27, 58, 'Lead Update', 'Lead details updated:\n- Changed status from \'Loan Takeout\' to \'Closed Deal\'\n', NULL, NULL, 0, '2025-06-09 07:12:01'),
(17, 28, 58, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-06-09 07:24:54'),
(18, 29, 15, 'Lead Update', 'Lead details updated:\n- Changed status from \'Inquiry\' to \'Downpayment Stage\'\n', NULL, NULL, 0, '2025-06-13 02:33:21'),
(19, 29, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-06-13 02:34:43'),
(20, 29, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-06-13 02:35:20'),
(21, 29, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-06-13 02:35:34'),
(22, 29, 15, 'Lead Update', 'Lead details updated:\n- Changed status from \'Downpayment Stage\' to \'Closed Deal\'\n', NULL, NULL, 0, '2025-06-13 02:36:01');

-- --------------------------------------------------------

--
-- Table structure for table `lead_modifications`
--

CREATE TABLE `lead_modifications` (
  `id` int NOT NULL,
  `lead_id` int NOT NULL,
  `user_id` int NOT NULL,
  `modification_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `old_value` text COLLATE utf8mb4_general_ci,
  `new_value` text COLLATE utf8mb4_general_ci,
  `activity_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_modifications`
--

INSERT INTO `lead_modifications` (`id`, `lead_id`, `user_id`, `modification_type`, `old_value`, `new_value`, `activity_id`, `created_at`) VALUES
(1, 23, 27, 'developer_change', 'Greenfield Builders', 'Anyana', 7, '2025-06-06 08:05:15'),
(2, 23, 27, 'project_model_change', 'Model C', 'Sydney', 7, '2025-06-06 08:05:15'),
(3, 21, 25, 'developer_change', 'Greenfield Builders', 'Liora Homes', 8, '2025-06-06 08:05:58'),
(4, 21, 25, 'project_model_change', 'Model A', 'Amora', 8, '2025-06-06 08:05:58'),
(5, 24, 28, 'developer_change', 'Sunrise Dev', 'Lancaster', 9, '2025-06-06 08:07:16'),
(6, 24, 28, 'project_model_change', 'Model A', 'Alice', 9, '2025-06-06 08:07:16'),
(7, 25, 29, 'developer_change', 'Greenfield Builders', 'Minami Residence', 10, '2025-06-06 08:07:50'),
(8, 25, 29, 'project_model_change', 'Model B', 'Hana', 10, '2025-06-06 08:07:50'),
(9, 22, 26, 'developer_change', 'Sunrise Dev', 'Lancaster', 11, '2025-06-06 08:09:23'),
(10, 22, 26, 'project_model_change', 'Model B', 'Alice', 11, '2025-06-06 08:09:23'),
(11, 27, 58, 'status_change', 'Loan Takeout', 'Closed Deal', 16, '2025-06-09 07:12:01'),
(12, 29, 15, 'status_change', 'Inquiry', 'Downpayment Stage', 18, '2025-06-13 02:33:21'),
(13, 29, 15, 'status_change', 'Downpayment Stage', 'Closed Deal', 22, '2025-06-13 02:36:01');

-- --------------------------------------------------------

--
-- Table structure for table `memos`
--

CREATE TABLE `memos` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `memo_when` datetime NOT NULL,
  `memo_where` varchar(255) DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `team_id` int NOT NULL,
  `visible_to_all` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `memos`
--

INSERT INTO `memos` (`id`, `title`, `file_path`, `description`, `memo_when`, `memo_where`, `priority`, `is_active`, `created_by`, `team_id`, `visible_to_all`, `created_at`, `updated_at`) VALUES
(2, 'Welcome Users!', 'uploads/memos/1749197573_Login Manual.pdf', 'The Lead Management System is now online at:\r\nhttps://leads.dreamhosters.com/\r\n\r\nTHIS IS FOR BETA TESTING ONLY!\r\n\r\nPlease note:\r\n\r\nNot all data or report results are accurate.\r\n\r\nThe current contents and leads are only sample data for admins, managers, supervisors, and agents.\r\n\r\nThis does not reflect your actual performance.\r\n', '2025-06-06 01:12:53', NULL, 'Medium', 1, 15, 13, 1, '2025-06-06 08:12:53', '2025-06-06 08:12:53'),
(5, 'Savia Parkway: Incentives', 'uploads/memos/1750061548_ange.pdf', 'Incentives for new agent: CAR - honda', '2025-06-16 01:12:28', NULL, 'High', 1, 8, 8, 1, '2025-06-16 08:12:28', '2025-06-16 08:12:28');

-- --------------------------------------------------------

--
-- Table structure for table `memo_images`
--

CREATE TABLE `memo_images` (
  `id` int NOT NULL,
  `memo_id` int NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `image_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `memo_read_status`
--

CREATE TABLE `memo_read_status` (
  `id` int NOT NULL,
  `memo_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `read_status` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `memo_read_status`
--

INSERT INTO `memo_read_status` (`id`, `memo_id`, `employee_id`, `read_status`, `read_at`, `created_at`) VALUES
(3, 2, 15, 1, '2025-06-06 20:13:04', '2025-06-06 08:12:56'),
(5, 2, 8, 1, '2025-06-06 20:52:37', '2025-06-06 08:52:37'),
(6, 2, 58, 1, '2025-06-08 17:26:36', '2025-06-09 05:26:36'),
(7, 2, 40, 1, '2025-06-08 17:28:45', '2025-06-09 05:28:45'),
(8, 2, 57, 1, '2025-06-08 17:30:52', '2025-06-09 05:30:52'),
(9, 2, 53, 1, '2025-06-08 18:41:43', '2025-06-09 06:41:43'),
(10, 2, 59, 1, '2025-06-08 18:59:53', '2025-06-09 06:59:53'),
(11, 2, 55, 1, '2025-06-09 19:06:04', '2025-06-09 07:06:04'),
(12, 2, 56, 1, '2025-06-09 19:10:14', '2025-06-09 07:10:14'),
(13, 2, 39, 1, '2025-06-09 22:15:36', '2025-06-09 10:14:09');

-- --------------------------------------------------------

--
-- Table structure for table `memo_team_visibility`
--

CREATE TABLE `memo_team_visibility` (
  `id` int NOT NULL,
  `memo_id` int NOT NULL,
  `team_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `memo_visibility`
--

CREATE TABLE `memo_visibility` (
  `id` int NOT NULL,
  `memo_id` int NOT NULL,
  `visible_to_role` enum('manager','supervisor','agent') COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `monthly_sales_report`
-- (See below for the actual view)
--
CREATE TABLE `monthly_sales_report` (
`year` int
,`month` int
,`month_name` varchar(9)
,`deals_closed` bigint
,`total_sales` decimal(34,2)
,`total_commission` decimal(34,2)
,`average_deal_size` decimal(16,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `house_model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('rfo','preselling','ogc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'preselling',
  `developer` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_min` decimal(15,2) NOT NULL,
  `price_max` decimal(15,2) NOT NULL,
  `commission` decimal(5,2) NOT NULL DEFAULT '5.00',
  `priority` enum('high','medium','low') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `city_id` int DEFAULT NULL,
  `province_id` int DEFAULT NULL,
  `exact_location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drive_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `messenger_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `house_model`, `status`, `developer`, `price_min`, `price_max`, `commission`, `priority`, `city_id`, `province_id`, `exact_location`, `image1`, `image2`, `image3`, `image4`, `drive_link`, `messenger_link`, `created_at`, `updated_at`) VALUES
(14, 'Hana South', 'Townhouse', 'Lyca, Erica, Rosanna', 'preselling', 'ACM Homes', 2200000.00, 2200000.00, 2.50, 'low', 16, 11, 'Brgy. Cabuco Sitio Ilaya, Trece Martires Cavite', 'project_14_1_1750060210.jpg', 'project_14_2_1750055980.jpg', 'project_14_3_1750055980.jpg', 'project_14_4_1750055980.jpg', '', '', '2025-06-16 03:47:28', '2025-06-16 07:50:10'),
(15, 'New Leaf', 'Single Detached, Attached, Townhouse', 'Diana', 'preselling', 'Filinvest', 2700000.00, 3200000.00, 2.50, 'medium', 16, 11, 'NEW LEAF Osorio Rd, Brgy Hugo Perez, Trece Martirez Cavite', 'project_15_1_1750055649.jpg', NULL, NULL, NULL, '', '', '2025-06-16 05:10:57', '2025-06-16 06:34:09'),
(16, 'Pilila Heights', 'Lot Only', '', 'preselling', 'MCR Realty Ventures OPC', 500000.00, 10000000.00, 3.00, 'high', 20, 12, 'Sitio Matagbak, Daang Mulawin Brgy. Bagumbayan Pililla Rizal', 'project_16_1_1750059138.jpeg', 'project_16_2_1750059138.jpeg', 'project_16_3_1750059138.jpeg', 'project_16_4_1750059138.jpeg', 'https://drive.google.com/drive/folders/1zeimM42UrSuAMFGp7PNlji1D-LkXOBhh', '', '2025-06-16 05:21:29', '2025-06-16 07:32:18'),
(17, 'Antipolo Heights', 'Lot Only', '', 'preselling', 'MCR Realty Ventures OPC', 560000.00, 10000000.00, 5.00, 'high', 21, 12, '', 'project_17_1_1750057050.jpg', 'project_17_2_1750057050.jpg', 'project_17_3_1750057050.jpg', NULL, '', '', '2025-06-16 05:23:40', '2025-06-16 06:57:30'),
(18, 'Golden Montana', 'Lot Only', '', 'preselling', 'MCR Realty Ventures OPC', 500000.00, 10000000.00, 3.00, 'high', 21, 12, 'Barangay Lanang Maybangkal Morong Rizal', 'project_18_1_1750059235.jpg', 'project_18_2_1750059235.jpg', 'project_18_3_1750059235.jpg', NULL, 'https://drive.google.com/drive/folders/1zeimM42UrSuAMFGp7PNlji1D-LkXOBhh', '', '2025-06-16 05:24:27', '2025-06-16 07:33:55'),
(19, 'Pacific Ace Village', 'Lot Only', '', 'preselling', 'Pacific Ace Real Estate', 1100000.00, 1300000.00, 2.50, 'low', 17, 11, 'Capt. E-Bocalan St, Amaya II, Tanza', 'project_19_1_1750059458.jpg', 'project_19_2_1750059458.jpg', 'project_19_3_1750059458.jpg', 'project_19_4_1750059458.png', 'https://drive.google.com/drive/mobile/folders/1WSd5M9CNBdfyVwAPtYvAchFHUQp-5YMq?usp=drive_link', '', '2025-06-16 05:26:05', '2025-06-16 07:37:38'),
(21, 'Westdale 2', 'Townhouse', 'Elena, Stefania, Marquesa', 'rfo', 'HomeMark Peakland', 1800000.00, 2300000.00, 5.00, 'medium', 17, 11, 'Punta Dos, Tanza, Cavite', 'project_21_1_1750059694.JPG', 'project_21_2_1750059694.JPG', 'project_21_3_1750059694.JPG', 'project_21_4_1750059694.JPG', '', '', '2025-06-16 05:26:46', '2025-06-16 07:41:34'),
(22, 'Istana Tanza', 'Townhouse', '', 'rfo', 'Charles Builders Group of Companies', 2300000.00, 3600000.00, 3.00, 'medium', 17, 11, 'Barangay Biga, Tanza, Cavite', 'project_22_1_1750054213.jpg', 'project_22_2_1750054213.jpg', 'project_22_3_1750054213.jpg', 'project_22_4_1750054213.jpg', '', '', '2025-06-16 05:29:39', '2025-06-16 06:10:13'),
(23, 'Southdale', 'Townhouse', 'Selena', 'preselling', 'HomeMark Peakland', 1700000.00, 1900000.00, 3.00, 'medium', 17, 11, 'Brgy Santol Tanza Cavite', 'project_23_1_1750059849.png', 'project_23_2_1750059614.JPG', 'project_23_3_1750059614.JPG', 'project_23_4_1750059614.JPG', '', '', '2025-06-16 05:29:46', '2025-06-16 07:44:09'),
(24, 'Northdale Estate', 'Townhouse', 'selena', 'preselling', 'HomeMark Peakland', 1300000.00, 1500000.00, 3.00, 'medium', 23, 11, 'Brgy San Roque Naic, Cavite', 'project_24_1_1750059581.JPG', 'project_24_2_1750059581.JPG', 'project_24_3_1750059581.JPG', 'project_24_4_1750059581.JPG', '', '', '2025-06-16 05:34:32', '2025-06-16 07:39:41'),
(25, 'Pagsikat Place', 'Townhouse', '', 'rfo', 'Raemulan', 1200000.00, 1300000.00, 2.50, 'low', 23, 11, 'Brgy. Labac Muzon, Naic, Cavite', 'project_25_1_1750056021.jpg', 'project_25_2_1750056021.jpg', 'project_25_3_1750056021.jpg', 'project_25_4_1750056021.jpg', '', '', '2025-06-16 05:36:27', '2025-06-16 06:40:21'),
(26, 'Estanzia Enclave', 'Twinhome', '', 'rfo', 'MetroLand', 2600000.00, 3600000.00, 3.00, 'low', 17, 11, 'Barangay Sahud-Ulan, Antero Soriano Hwy, Tanza, Cavite', 'project_26_1_1750060160.jpg', 'project_26_2_1750058746.jpg', 'project_26_3_1750058746.jpg', 'project_26_4_1750058746.jpg', '', '', '2025-06-16 05:37:07', '2025-06-16 07:49:20'),
(27, 'Pineview', 'Single Detached', 'Molave, Wallnut', 'rfo', 'Filinvest', 3400000.00, 4700000.00, 2.50, 'medium', 17, 11, 'Remulla Drive Tanza-Naic Road, Barangay Sahud-Ulan, Tanza, Cavite', 'project_27_1_1750060573.jpg', 'project_27_2_1750059297.jpg', NULL, NULL, '', '', '2025-06-16 05:37:47', '2025-06-16 07:56:13'),
(28, '3 Verde Rosa', 'Single Attached, Duplex', 'Rosa', 'rfo', 'CRC Realty', 3600000.00, 3800000.00, 3.00, 'medium', 17, 11, 'Brgy. Sanja Major Tanza Cavite.', 'project_28_1_1750056869.jpg', 'project_28_2_1750056869.jpg', NULL, NULL, '', '', '2025-06-16 05:38:32', '2025-06-16 06:54:29'),
(29, 'Anyana', 'Single Detached/ Lot Only', 'Paris, Sydney, Tokyo, Florida', 'preselling', 'Antel Land', 8000000.00, 16000000.00, 3.00, 'medium', 17, 11, 'Barangay, Sanja Mayor, Tanza, 4108 Cavite', 'project_29_1_1750056066.jpg', 'project_29_2_1750056066.jpg', 'project_29_3_1750056066.jpg', 'project_29_4_1750056066.jpg', 'https://drive.google.com/drive/mobile/folders/13kk8inizpPzUfpTLTlx-sWZbwMVMyaSt?usp=sharing', '', '2025-06-16 05:39:05', '2025-06-16 06:41:06'),
(30, 'Rosepointe Subdivision', 'Single Attached', '', 'rfo', 'CCRC REALTY', 3600000.00, 5800000.00, 3.00, 'medium', 22, 13, 'Brgy. Tagapo, Santa Rosa City, Laguna', 'project_30_1_1750056323.jpg', 'project_30_2_1750056323.jpg', 'project_30_3_1750056323.jpg', 'project_30_4_1750056323.jpg', '', '', '2025-06-16 05:40:34', '2025-06-16 06:45:23'),
(31, 'Golden Vista', 'Lot Only', '', 'preselling', 'MCR Realty Ventures OPC', 500000.00, 10000000.00, 3.00, 'high', 21, 12, '', 'project_31_1_1750059393.jpeg', 'project_31_2_1750059393.jpeg', 'project_31_3_1750059393.jpeg', 'project_31_4_1750059393.jpeg', 'https://drive.google.com/drive/folders/1zeimM42UrSuAMFGp7PNlji1D-LkXOBhh', '', '2025-06-16 05:41:37', '2025-06-16 07:36:33'),
(32, 'Pagsibol Village', 'Townhouse', 'Santan, Aubergine, Turqoise', 'preselling', 'Raemulan', 1700000.00, 1900000.00, 2.50, 'low', 23, 11, 'Pagsibol 3, Dynamism St, Naic, Cavite', 'project_32_1_1750055909.jpg', 'project_32_2_1750055909.jpg', 'project_32_3_1750055909.jpg', NULL, '', '', '2025-06-16 05:42:12', '2025-06-16 06:40:18'),
(34, 'Liora Homes', 'Townhouse', 'Amora', 'rfo', 'CitiHomes Builders', 2300000.00, 2700000.00, 3.00, 'high', 23, 11, 'Brgy. Malainen Bago, Naic, Cavite', 'project_34_1_1750055942.jpg', 'project_34_2_1750055942.jpg', 'project_34_3_1750055942.jpg', NULL, 'https://drive.google.com/drive/folders/1NtyOFRM8i8-kiQ9vkuestBr7xWO-0p8_?usp=sharing', '', '2025-06-16 05:59:38', '2025-06-16 06:40:57'),
(35, 'Kaia Homes', 'Townhouse, Row House', 'Helena', 'rfo', 'KAIA Homes', 1400000.00, 2300000.00, 3.00, 'high', 23, 11, 'Brgy. Palangue 2 & 3, Naic, Cavite.', 'project_35_1_1750060644.jpg', 'project_35_2_1750055889.jpg', 'project_35_3_1750055889.jpg', 'project_35_4_1750055889.jpg', 'https://drive.google.com/drive/folders/1EbkVjmOzxSqckfp83xRFowuAZrUE9Q1j?usp=drive_link', '', '2025-06-16 06:01:09', '2025-06-16 07:57:24'),
(36, 'Comelec VIllage', 'Single Attached, Detached, Townhouse', 'Chesca, Audrey, Felicity, Danna, Era', 'rfo', 'Masaito', 2700000.00, 6600000.00, 3.00, 'high', 24, 11, 'Advincula Road Brgy.Alapan 2A Imus ,Cavite', 'project_36_1_1750055861.jpg', 'project_36_2_1750055861.jpg', 'project_36_3_1750055861.jpg', 'project_36_4_1750059194.png', 'https://drive.google.com/drive/folders/1-82LBN7LaLOvCNWcvdJwh3WXXm4jnufj?usp=drive_link', '', '2025-06-16 06:03:44', '2025-06-16 07:33:14'),
(37, 'Lancaster New City', 'Single Attached, Detached, Townhouse, Condo', 'Chessa, Gabrielle, Margareth, Thea, Aira, Alice, Alexandra, Briana', 'rfo', 'ProFriends', 2700000.00, 8500000.00, 2.50, 'high', 19, 11, 'Advincula Avenue, Alapan II-B, Imus City Cavite', 'project_37_1_1750060697.jpg', 'project_37_2_1750055554.jpg', 'project_37_3_1750055554.jpg', 'project_37_4_1750055554.jpg', 'https://drive.google.com/drive/u/0/folders/18u7cwWbwON-PGZJBTiceAbpATc6OLTlz', '', '2025-06-16 06:05:32', '2025-06-16 07:58:17'),
(38, 'Lanello Heights', 'Single Attached, Detached, Townhouse', 'Abbie, Brenda, Chelsea', 'rfo', 'Masaito', 3700000.00, 5300000.00, 3.00, 'low', 25, 11, 'Barangay Pasong Camachile II, General Trias, Cavite', 'project_38_1_1750059938.jpg', 'project_38_2_1750055533.jpg', 'project_38_3_1750055533.jpg', 'project_38_4_1750055533.jpg', 'https://drive.google.com/drive/mobile/folders/12JmMKDVdV8EjcrOdzDVEmwvEcgkciNhM', '', '2025-06-16 06:07:50', '2025-06-16 07:45:38'),
(39, 'Minami Residences', 'Quadruplex', '', 'preselling', 'Pro Friends', 4300000.00, 4700000.00, 3.00, 'low', 25, 11, 'Barangay Santiago, General Trias, Cavite', 'project_39_1_1750059980.jpg', 'project_39_2_1750055499.jpg', 'project_39_3_1750055499.jpg', NULL, 'https://drive.google.com/drive/mobile/folders/12JmMKDVdV8EjcrOdzDVEmwvEcgkciNhM', '', '2025-06-16 06:10:12', '2025-06-16 07:46:20'),
(40, 'Masaito Homes', '', 'Abela, Bailey, Daniella', 'rfo', 'Masaito', 1000000.00, 1600000.00, 3.00, 'low', 18, 11, '', 'project_40_1_1750055464.jpg', 'project_40_2_1750055464.jpg', 'project_40_3_1750055464.jpg', 'project_40_4_1750055464.jpg', 'https://drive.google.com/drive/u/0/folders/12JmMKDVdV8EjcrOdzDVEmwvEcgkciNhM', '', '2025-06-16 06:17:23', '2025-06-16 06:31:04'),
(41, 'Meridian Place', 'Single Detached, Attached, Townhouse', 'Danessa, Bernice, Caroline', 'rfo', 'Filinvest', 1900000.00, 1900000.00, 2.50, 'medium', 25, 11, 'Brgy. Pasong Kawayan II, General Trias, Cavite', 'project_41_1_1750059463.jpg', NULL, NULL, NULL, '', '', '2025-06-16 06:19:50', '2025-06-16 07:37:43'),
(42, 'Pleasantfields', 'Townhouse', 'Kennedy, Lincoln, Nyxon', 'rfo', '650 Homes', 2700000.00, 3500000.00, 3.00, 'low', 17, 11, 'Barangay, Purok 3 Bukal Rd, Tanza, Cavite', 'project_42_1_1750056102.jpg', 'project_42_2_1750056102.jpeg', 'project_42_3_1750056102.jpeg', 'project_42_4_1750056102.jpeg', 'https://docs.google.com/document/d/1JIacA1FfzsT3WbwzSGqN0SZxC2AsvW9_U7H10VfIfwM/edit?tab=t.0', 'https://m.me/j/AbbyYHgjvFMCa_U3/', '2025-06-16 06:21:43', '2025-06-16 08:00:56'),
(43, 'Elisa Homes', 'Single Attached, Townhouse', 'Sapphire, Pearl, Dahlia, Canalily', 'rfo', 'F&E De Castro', 10000000.00, 10000000.00, 3.00, 'medium', 18, 11, 'Molino Rd, Molino 4, Bacoor Cavite', 'project_43_1_1750056162.jpg', 'project_43_2_1750056162.jpg', 'project_43_3_1750056162.jpg', 'project_43_4_1750056162.jpg', '', '', '2025-06-16 06:24:55', '2025-06-16 06:42:42'),
(44, 'LaVerne', 'Single Attached', 'Isabelle, Megan', 'ogc', '650 Homes', 7799999.00, 8500000.00, 3.00, 'medium', 18, 11, 'Habay 2, Bacoor, Cavite', 'project_44_1_1750060507.jpg', 'project_44_2_1750058945.jpg', 'project_44_3_1750058945.jpg', 'project_44_4_1750058945.jpg', 'https://docs.google.com/document/d/1ZLJbSblgKsWIaDE74qhOsUNH_1YtHwUcuA_SI7boD-U/edit?tab=t.0#heading=h.19hku0byct89', 'https://m.me/j/AbbyYHgjvFMCa_U3/', '2025-06-16 06:32:28', '2025-06-16 08:00:21'),
(45, 'Kathleen Place 5', 'Townhouse', '', 'preselling', 'JKY', 5900000.00, 9000000.00, 2.50, 'medium', 18, 11, 'Gawaran Avenue Brgy. Molino 7, Bacoor, Cavite', 'project_45_1_1750060017.jpeg', 'project_45_2_1750060017.jpeg', 'project_45_3_1750056135.jpeg', 'project_45_4_1750056135.jpeg', 'https://drive.google.com/drive/folders/14lKiVhu0GIqE-itWkkHupejj49dTRK_Q', '', '2025-06-16 06:33:58', '2025-06-16 07:46:57');

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`id`, `name`, `region`, `created_at`) VALUES
(11, 'Cavite', NULL, '2025-06-16 03:46:11'),
(12, 'Rizal', NULL, '2025-06-16 05:13:20'),
(13, 'Laguna', NULL, '2025-06-16 05:39:43');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci NOT NULL,
  `setting_description` text COLLATE utf8mb4_general_ci,
  `setting_group` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `setting_group`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'company_name', 'Real Estate Leads CRM', 'Company name displayed in the system', 'general', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(2, 'company_email', 'info@realestatecrm.com', 'Default company email address', 'general', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(3, 'company_phone', '+1234567890', 'Company contact phone number', 'general', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(4, 'company_address', '123 Main Street, City, Country', 'Company physical address', 'general', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(5, 'company_logo', 'assets/img/logo.png', 'Path to company logo image', 'general', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(6, 'date_format', 'Y-m-d', 'Default date format for the system', 'general', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(7, 'time_format', 'H:i', 'Default time format for the system', 'general', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(8, 'timezone', 'Asia/Manila', 'Default timezone for the system', 'general', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(9, 'lead_auto_assign', '0', 'Automatically assign leads to agents (0=off, 1=on)', 'leads', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(10, 'lead_assignment_method', 'round_robin', 'Method for auto-assigning leads (round_robin, random, load_balanced)', 'leads', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(11, 'lead_follow_up_days', '3', 'Default number of days for lead follow-up reminder', 'leads', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(12, 'lead_status_colors', '{\"Inquiry\":\"#f6c23e\",\"Presentation Stage\":\"#36b9cc\",\"Negotiation\":\"#4e73df\",\"Closed\":\"#1cc88a\",\"Lost\":\"#e74a3b\"}', 'Color codes for lead status labels', 'leads', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(13, 'lead_temperature_colors', '{\"Hot\":\"#e74a3b\",\"Warm\":\"#f6c23e\",\"Cold\":\"#4e73df\"}', 'Color codes for lead temperature labels', 'leads', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(14, 'smtp_host', 'smtp.example.com', 'SMTP server hostname', 'email', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(15, 'smtp_port', '587', 'SMTP server port', 'email', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(16, 'smtp_username', 'notifications@example.com', 'SMTP username', 'email', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(17, 'smtp_password', 'password', 'SMTP password', 'email', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(18, 'smtp_encryption', 'tls', 'SMTP encryption method (tls, ssl)', 'email', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(19, 'email_from_name', 'Real Estate CRM', 'From name for system emails', 'email', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(20, 'email_from_address', 'noreply@example.com', 'From email address for system emails', 'email', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(21, 'password_min_length', '8', 'Minimum password length', 'security', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(22, 'password_requires_special', '1', 'Require special characters in passwords (0=no, 1=yes)', 'security', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(23, 'password_requires_number', '1', 'Require numbers in passwords (0=no, 1=yes)', 'security', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(24, 'password_requires_uppercase', '1', 'Require uppercase letters in passwords (0=no, 1=yes)', 'security', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(25, 'session_timeout', '30', 'Session timeout in minutes', 'security', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(26, 'max_login_attempts', '5', 'Maximum failed login attempts before lockout', 'security', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(27, 'lockout_time', '15', 'Account lockout time in minutes after failed attempts', 'security', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(28, 'enable_email_notifications', '1', 'Enable email notifications (0=off, 1=on)', 'notifications', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(29, 'enable_browser_notifications', '1', 'Enable browser notifications (0=off, 1=on)', 'notifications', 1, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(30, 'notify_on_new_lead', '1', 'Send notification on new lead (0=off, 1=on)', 'notifications', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(31, 'notify_on_lead_update', '1', 'Send notification on lead update (0=off, 1=on)', 'notifications', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(32, 'notify_on_lead_assignment', '1', 'Send notification on lead assignment (0=off, 1=on)', 'notifications', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(33, 'enable_developer_tools', '0', 'Enable developer tools and debugging (0=off, 1=on)', 'developer', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(34, 'log_level', 'error', 'Log level (error, warning, info, debug)', 'developer', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(35, 'maintenance_mode', '1', 'Put system in maintenance mode (0=off, 1=on)', 'developer', 0, '2025-06-09 06:54:53', '2025-06-09 08:36:52'),
(36, 'maintenance_message', 'System is currently under maintenance. Please check back later.', 'Message displayed during maintenance mode', 'developer', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Blazing SPARCS', NULL, '2025-05-16 01:45:20', '2025-06-04 21:30:24'),
(2, 'Feisty Heroine', NULL, '2025-05-16 01:45:20', '2025-06-04 21:30:24'),
(3, 'Shining Phoenix', NULL, '2025-05-16 01:45:20', '2025-06-09 10:42:48'),
(8, 'Flameborn Champions', NULL, '2025-05-16 01:45:20', '2025-06-04 21:30:24'),
(12, 'Fiery Achievers', NULL, '2025-05-19 01:25:43', '2025-06-04 21:30:24'),
(13, 'OJT (Intern)', NULL, '2025-06-04 01:25:43', '2025-06-09 09:39:07');

-- --------------------------------------------------------

--
-- Stand-in structure for view `team_performance_summary`
-- (See below for the actual view)
--
CREATE TABLE `team_performance_summary` (
`team_id` int
,`team_name` varchar(100)
,`total_agents` bigint
,`total_leads` bigint
,`closed_deals` bigint
,`total_sales` decimal(34,2)
,`total_commission` decimal(34,2)
,`conversion_rate` decimal(26,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `team_id` int DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('admin','manager','supervisor','agent') COLLATE utf8mb4_general_ci NOT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `team_id`, `username`, `password`, `name`, `email`, `phone`, `role`, `profile_picture`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', '$2y$10$W5MMTTxFbaz/aT8Jc5pH8.NqiRgBvufr4MhDq5eMSTM4.vjE5259C', 'Administrator', 'innersparcservices@gmail.com', NULL, 'admin', NULL, 1, NULL, '2025-05-16 08:45:20', '2025-06-06 07:19:11'),
(3, 8, 'shielamaefajutagana.accounting', '$2y$10$gbLWkM8Lc//nUt5oexmLXOkZSG6PVoIaSMntaSqlgqCB1mlLFRVeG', 'Sheila Mae Fontelo Fajutagana', 'francisheila05@gmail.com', '09151082974', 'agent', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-06 07:36:59'),
(4, 2, 'luzvimindalim.innersparc', '$2y$10$PUV7H0RlWs.dJZ6x8SqmvOFy3x9MlJIo0m7otynHJfAxHnktLhC4u', 'Luzviminda Labrado Lim', 'luz032069@gmail.com', '09062717602', 'supervisor', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-04 22:01:31'),
(5, 3, 'eduardalizatorres.innersparc', '$2y$10$vlTHTcCwPNcRnoG1NVMyO.FjL9e8rnpVuLsgmzZkr3iJriC8UHEw.', 'Eduardaliza Dulay Torres', 'edtorres797426@gmail.com', '09367465749', 'supervisor', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-04 22:01:31'),
(6, 1, 'mikegabrielescarilla.innersparc', '$2y$10$wQ4nzPYxWM.8smNno/0vIOfwSvYWxq3yphH3y8QCXxK68d3i8uSPG', 'Mike Gabriel Bedion Escarilla', 'escarilla.mikegabriel@gmail.com', '09269979145', 'agent', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-06 07:00:52'),
(7, 1, 'romeocorberta.itdept', '$2y$10$rRuVdtrMXPrd1x.ySHEY3.9Vvjxds.9Js5rCvRPgQG7ug3NzLmMZO', 'Romeo Cerna Cobreta Jr.', 'romxcob.innersparc@gmail.com', '09090326945', 'admin', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-09 03:44:52'),
(8, 8, 'charlenedellosa.opsman', '$2y$10$7BvBgpFnDmhhX3/zSVikjebJCWefKLpsg36RYdkM0KhZDmjE/Zmmu', 'Charlene Dellosa', 'dellosacharlene1317@gmail.com', '09169994124', 'manager', 'uploads/profile_pictures/user_8_1747501769.png', 1, NULL, '2019-05-16 08:45:23', '2025-06-06 07:31:50'),
(9, 2, 'alvinllaneta.innersparc', '$2y$10$.w9.aPUWHSS5GifQHPn9Fu2.JKn817TupAc0xAEDSekCnwFDH1UiW', 'Alvin  Llaneta', 'alvinllaneta8@gmail.com', '09613825054', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-04 22:01:31'),
(11, 2, 'clarencedanielleserdon.innersparc', '$2y$10$tRBIk1jyaXGFbpAveJE8gOqFpGW.O/hJInU3qJ/mnqr3k/RBneuQK', 'Clarence Danielle Lim Serdon', 'clarencedanielle98@gmail.com', '09996916107', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-04 22:01:31'),
(12, 2, 'gabcyrosebenson.innersparc', '$2y$10$GnAbo83uu0hltK474fV1B.It7N5iLugZXtDFd.5vDRuFqso62qcXS', 'Gabcyrose Samsona Benson', 'gabcyrose@gmail.com', '09263939124', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-04 22:01:31'),
(13, 1, 'lenizapasion.innersparc', '$2y$10$t/pbiM/TxOEK7suqW.8/ruSPdj77rekzZpzLTubXSytzBC5QDiPXa', 'Leniza Flores pasion', 'lenizapasion51@gmail.com', '09177179863', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-06 07:01:28'),
(14, 1, 'perlitago.innersparc', '$2y$10$p4kmD3n.wjSirRlDZpwRaOz69x2uytmxIQcBsgRTPpJynvZ5o1I76', 'Perlita Santiago Go', 'gopearl43@yahoo.com', '09777261123', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-06 07:01:35'),
(15, 13, 'markpatigayon.intern', '$2y$10$Tbq4hLp0VyDFdxogyElwm.A52Fh.OLnPqgayZTWrAlY4s.V7XCPdi', 'Mark Christian Patigayon', 'markpatigayon440@gmail.com', '09194620030', 'admin', 'uploads/profile_pictures/user_15_1747499626.jpg', 1, NULL, '2019-05-22 08:45:23', '2025-06-06 07:12:38'),
(16, 1, 'verlynvesagas.innersparc', '$2y$10$PEX7IJamKPjc2Mg5859AZuLQjqB/anHrK9ty37.dejDgGTEVazAwy', 'Verlyn Bizconde Vesagas', 'vverlyn@gmail.com', '09915573606', 'agent', NULL, 1, NULL, '2025-05-19 08:08:49', '2025-06-06 07:00:25'),
(17, 1, 'rizelagrimas.innersparc', '$2y$10$ipV2yK499HzPJRz4Bw8G0OMKotbJFfC37LzMtCn1qPimCph6f9Dmy', 'Rize OwogOwog Lagrimas', 'rizielagrimas18@gmail.com', '09202474501', 'agent', NULL, 1, NULL, '2025-05-19 08:09:39', '2025-06-06 07:13:53'),
(18, 1, 'ireneblanca.innersparc', '$2y$10$aEQppVOJLQ3050pCQEKrwepJCT8rw3frtf.AqkgMPqw7TjisrYmli', 'Irene Noble Blanca', 'ireneblanca1909@gmail.com', '09943843721', 'agent', NULL, 1, NULL, '2025-05-19 08:10:13', '2025-06-04 22:01:31'),
(19, 1, 'gabriellibacao.founder', '$2y$10$0A.MhdXz2UAcy4bUGR6EBOsQ82F9GtlZLgCw0a0n46KXTUuoM8t8a', 'Gabriel Jr. Villamor Libacao', 'libacaoga@gmail.com', '09178534875', 'admin', NULL, 1, NULL, '2025-05-19 08:10:58', '2025-06-06 07:31:02'),
(20, 1, 'erwingonzales.cofounder', '$2y$10$/I4c/Yn7lWXRlraQvK0m2ueNwa2vgAfJ1NSKVG9wNfXJUu51fjoeq', 'Erwin Gonzales Baguioan', 'irwindgonzales6@gmail.com', '09669533188', 'manager', NULL, 1, NULL, '2025-05-19 08:11:50', '2025-06-06 07:40:01'),
(21, 1, 'nelynortega.innersparc', '$2y$10$pPrIuezkcJ9THUg5IHmFMuo68tv5W5MhSeLarPb4HIFc2rt5Jj8u.', 'Nelyn Serad Ortega', 'orteganelyn18@gmail.com', '09650984075', 'supervisor', NULL, 1, NULL, '2025-05-19 08:12:41', '2025-06-04 22:01:31'),
(22, 1, 'sarahlopez.innersparc', '$2y$10$HFq2/p7EOrkbn0gFTWoCJOC.0jkJX3rAZj0Rg65xuEfvqa7K3cw7K', 'Sarah Jean Lagatic Lopez', 'sarahjeanlopez07@gmail.com', '09329757344', 'supervisor', NULL, 1, NULL, '2025-05-19 08:13:10', '2025-06-04 22:01:31'),
(23, 2, 'nephelepanganiban', '$2y$10$uZrVk47hzppnbZsFYK/SWOAhQ5e04n/rqm8ti4Hkp7FgvWjq9WrW6', 'Nephele Telmo Panganiban', 'nephelepanganiban@gmail.com', '09662974629', 'agent', NULL, 1, NULL, '2025-05-19 08:18:33', '2025-06-04 22:01:31'),
(24, 2, 'joanbarceta.innersparc', '$2y$10$E.XU5PTwBNn6BryqVpHLTOGGmPOz9V1slWHXBZjzg4YeKC00K9o6K', 'Joan Mahinay Barceta', 'jobarceta22@gmail.com', '09649589052', 'manager', 'uploads/profile_pictures/user_24_1747622923.jpg', 1, NULL, '2025-05-19 08:19:01', '2025-06-04 22:01:31'),
(25, 2, 'teresasandoval.innersparc', '$2y$10$WYxXqgw6uC.9r4ukTgt.SOApN.eDGquy0nbDiJpAkfUHGrMQd2bx2', 'Teresa Rosanto Sandoval', 'trscyl@yahoo.com', '09932967582', 'supervisor', NULL, 1, NULL, '2025-05-19 08:20:20', '2025-06-04 22:01:31'),
(26, 2, 'ailyndetorres.innersparc', '$2y$10$lSuCUikZuhPTgOE3iChWz.ISA0/A91g7LZPbO0ts/CGhagzFId00e', 'Ailyn Llaneta De Torres', 'ailyndetorres8@gmail.com', '09501409792', 'agent', NULL, 1, NULL, '2025-05-19 08:21:11', '2025-06-06 08:09:08'),
(27, 2, 'emilyncantuba.innersparc', '$2y$10$AL8RtPNTg4fhzeCoe8paGuFoFKQspNTe4WuvQmieLbw0BW06OyW/W', 'Emilyn Marcelo Cantuba', 'cantubaemhie@gmail.com', '09362898373', 'agent', NULL, 1, NULL, '2025-05-19 08:21:46', '2025-06-04 22:01:31'),
(28, 2, 'novelitatabudlong.innersparc', '$2y$10$VzOywJiBE7vbcCCgyXyqOO8r5qMiKWJfqXZmINuqXCvyziXMjM3wi', 'Novelita  Letran Tabudlong', 'novzpretty@gmail.com', '09366512502', 'agent', NULL, 1, NULL, '2025-05-19 08:22:15', '2025-06-04 22:01:31'),
(29, 8, 'leodellosa.innersparc', '$2y$10$Cv6hsi.jSxoikgaDi275KO6HQstKWsWIOLA.vIQUfBVCW8BJIX8.i', 'Leo Dellosa', 'leodellosa@example.com', '09169994124', 'agent', NULL, 1, NULL, '2025-05-19 08:22:59', '2025-06-04 22:01:31'),
(30, 8, 'arleneumali.innersparc', '$2y$10$bnwqqAGYP9wCmWeFh6ykNO8Nn78VQ5w.7Owzuxy9ES7HTxAubjRQi', 'Arlene Umali', 'arleneumali@example.com', '09159293382', 'agent', 'uploads/profile_pictures/profile_684087a52d9e4.jpg', 1, NULL, '2025-05-19 08:24:33', '2025-06-05 00:51:33'),
(31, 12, 'mannyviolenta.innersparc', '$2y$10$q4AysMNRb0HqK6DV.BSHV.rTaEl86RZI2d1SG/24Sgnkg/G7QUfBi', 'Manny Alberto Violenta', 'violentamanny@gmail.com', '09380326931', 'manager', NULL, 1, NULL, '2025-05-19 08:28:42', '2025-06-04 22:01:31'),
(32, 12, 'annalynviolenta.innersparc', '$2y$10$V/Znwb0nP.g1kKNUqZdMyOhPNKu2Z7A2FEmCReSfVeUy5ogANgND2', 'Annalyn Salting Violenta', 'anniemazing2@gmail.com', '09084776982', 'agent', NULL, 1, NULL, '2025-05-19 08:30:01', '2025-06-04 22:01:31'),
(33, 12, 'anelatabuyan.innersparc', '$2y$10$6ZN3s6dR/KaXtstCjmUpuO.4zc4pcfp9sXCCyelzuENqygUA4FxDa', 'Anela Dela Cruz Tabuyan', 'nela.tab5@gmail.com', '09356088954', 'agent', NULL, 1, NULL, '2025-05-19 08:30:31', '2025-06-04 22:01:31'),
(34, 12, 'jocelynsantos.innersparc', '$2y$10$NiykEjnqVjwx5muaon0Jj.EeIC9shVUcnTvUJk.gk.OB3iEQEAdSO', 'Jocelyn Santos', 'jhoymsantos15@gmail.com', '09694569711', 'agent', NULL, 1, NULL, '2025-05-19 08:30:57', '2025-06-04 22:01:31'),
(35, 12, 'lenilyntimajo.innersparc', '$2y$10$bEn5TV2cX/RhHX28meGlK.OY.XDhKJ2FKhQDCPyW8Urc9V4G2ciZm', 'Lenily  Rana Timajo', 'timajolenily@gmail.com', '09129988330', 'supervisor', NULL, 1, NULL, '2025-05-19 08:31:24', '2025-06-04 22:01:31'),
(36, 12, 'jerusalinosantos.innersparc', '$2y$10$hYIses3VZ0VyVq9iFYUYeOrjIwkvzoeZlN9spuoOv6bgXHRZzDGwW', 'Jerusalino Tan Santos', 'jerometsantos28@gmail.com', '09516319674', 'supervisor', NULL, 1, NULL, '2025-05-19 08:32:16', '2025-06-04 22:01:31'),
(37, 12, 'novelynbualat.innersparc', '$2y$10$9QLMoE7w01X5z81AVAx6Bu2DX/AmlL8oJM8d4IhN75ZB4LwTWXn0K', 'Novelyn Macalam  Bualat', 'novelynbualat01@gmail.com', '09281505191', 'agent', NULL, 1, NULL, '2025-05-19 08:33:04', '2025-06-04 22:01:31'),
(38, 12, 'edenrosedemerin.innersparc', '$2y$10$H75lWP/UOgcRwFOWNafMqONdmHhER8ZmXIenG3PTgPrXJocXQYYya', 'Eden Rose Ramos Demerin', 'apostolerogalapino@gmail.com0', '09380196696', 'supervisor', NULL, 1, NULL, '2025-05-19 08:33:27', '2025-06-04 22:01:31'),
(39, 13, 'markbacli.intern', '$2y$10$niHLEGNqlqxrDNGyWTow4eTlk1DoGKYIDvdzDQpjMrtjzivkE4jJC', 'Mark Vincent Bacli', 'markvincentbacli@gmail.com', '09953009113', 'admin', NULL, 1, NULL, '2025-06-02 04:31:33', '2025-06-06 07:10:33'),
(40, 13, 'jeromebadua.intern', '$2y$10$SpXkbMJAeMxZzO3tBAhTcuD9aR1GsSgwtEu52ZU7JKcYfU2AxAhJa', 'Jerome Badua', 'jeromebadua@gmail.com', '09239203920', 'admin', 'uploads/profile_pictures/profile_68467168bda0c.jpg', 1, NULL, '2025-06-02 05:29:54', '2025-06-16 05:43:44'),
(41, 3, 'charitopalonson.innersparc', '$2y$10$ReD4hQktqiS8J3Q1rPmh..jks5ESi/XHHldh6uWKLIAVFrQb9GCje', 'Charito Palonson', 'charitabasbas@gmail.com', '09664380890', 'agent', NULL, 1, NULL, '2025-06-02 05:43:02', '2025-06-04 22:01:31'),
(42, 3, 'jesselieabayon.innersparc', '$2y$10$K9vFRytBXD2Obz7wrW3o0e3AHbqp/c01KlAkzXA/oIIsw.O9SZX3O', 'Jesselie Abayon', 'ajesselie44@gmail.com', '09398151934', 'agent', NULL, 1, NULL, '2025-06-02 05:46:27', '2025-06-04 22:01:31'),
(43, 3, 'janaerrolretuya.innersparc', '$2y$10$JWUgo7SPSP7Z1HwIyw99XOxUxiosQTdoiXRyhTFYzuUUOUl0edOda', 'jan Aerrol Retuya', 'janaerrol14@gmail.com', '09062161114', 'agent', NULL, 1, NULL, '2025-06-02 05:49:20', '2025-06-04 22:01:31'),
(44, 3, 'dennisalizano.innersparc', '$2y$10$gj1SxsNFSPmk4FC/fSvoEOjBCJpT3FpmrdWRCRjPEqccxQKW5w0HO', 'Dennisa Anne  Lizano', 'dennisaannelegaspi0721@gmail.com', '09705213040', 'agent', NULL, 1, NULL, '2025-06-02 05:50:45', '2025-06-04 22:01:31'),
(45, 3, 'mercytubania.innersparc', '$2y$10$0i7ymO6rShlh0m4t8IJwnO0MvZcEpXnWXCnjR6LQ8kDMu4MNeb7/G', 'Mercy  Tubania', 'cyro19@yahoo.com', '09988511450', 'agent', NULL, 1, NULL, '2025-06-02 05:52:03', '2025-06-04 22:01:31'),
(46, 3, 'myramagbagay.innersparc', '$2y$10$4.YXvOLH7qdnox0eF2gJOeHhiN5k0el80J92v/qK93GxILc5TjsLK', 'Myra  Magbagay', 'm56249180@gmail.com', '09285751285', 'supervisor', NULL, 1, NULL, '2025-06-02 05:52:59', '2025-06-04 22:01:31'),
(47, 3, 'edselcaraballo.innersparc', '$2y$10$q3wvYP6M3K.DkUI01GWgw.OT9A/aQzqreIKbf.alAIEqNO.sCKIp6', 'Edsel  Caraballo', 'caraballoedsel1@gmail.com', '09816607650', 'agent', NULL, 1, NULL, '2025-06-02 05:55:08', '2025-06-04 22:01:31'),
(48, 3, 'cynthiacaballes.innersparc', '$2y$10$Cj3wAqABaItBoju.lFcfIOfCdY7hIldynh25VyyC8qb30UaGw82t6', 'Cynthia Caballes', 'cynthia.p.caballes@gmail.com', '09177214309', 'manager', NULL, 1, NULL, '2025-06-02 05:56:55', '2025-06-16 08:57:07'),
(49, 3, 'rebeccaresurreccion.innersparc', '$2y$10$Wjr0icOiBYuPWlveaj1Iiu603Zn5TcCHTD5De7bOXaHSTzRydzKMa', 'Rebecca   Resurreccion', 'omrehacceber@gmail.com', '09918715817', 'supervisor', NULL, 1, NULL, '2025-06-02 05:59:34', '2025-06-04 22:01:31'),
(50, 3, 'johnpalonson.innersparc', '$2y$10$XQ16kAT7oCWuboHFAUZSXOXOWCV5BaBnNY6NfvonX.UuDiGq4BoM6', 'John   Palonson', 'mendrosjohn@gmail.com', '09696093699', 'agent', NULL, 1, NULL, '2025-06-02 06:01:56', '2025-06-04 22:01:31'),
(51, 3, 'desireejacosalem.innersparc', '$2y$10$iQXCvPSSPbrPc.4PI7E2L.T51D6cmiPFfmFnP2rjzBa1GptDR7rYG', 'Desiree   Jacosalem', 'dhez_sanchez@yahoo.com', '09567857546', 'agent', NULL, 1, NULL, '2025-06-02 06:03:34', '2025-06-04 22:01:31'),
(52, 3, 'marycorullo.innersparc', '$2y$10$oF2jZFQhw8XVTqCK9FkAu.erWDXuVln/xMtC8Tgc4HChCvSjKTiRW', 'Mary Angeli    Corullo', 'angelicorullo1@gmail.com', '09984721802', 'agent', NULL, 1, NULL, '2025-06-02 06:04:52', '2025-06-04 22:01:31'),
(53, 13, 'yenzogervacio.intern', '$2y$10$MuVoIUcNqQzYT1QEZ2ixpOlF4mUzbo.NZ94ln0QL.ZvER6cFrP1b6', 'Yenzo Teo Gervacio', 'marverygervacio@gmail.com', '09128288333', 'admin', 'uploads/profile_pictures/profile_6846824f5b3d9.jpg', 1, NULL, '2025-06-06 07:03:48', '2025-06-09 06:42:23'),
(54, 13, 'genesiscontreras.intern', '$2y$10$MMjHP.aYMF1IR30LhXIKvughT6jdx1h8JjG7zWNQz8FD9Rb9ZuVRy', 'Genesis Contreras', 'genesiscontreras@gmail.com', '09129382938', 'admin', NULL, 1, NULL, '2025-06-06 07:04:06', '2025-06-06 07:11:40'),
(55, 13, 'angelicarubrico.intern', '$2y$10$K.8eonEZAPaE.SlESF36O./AQX1bCyy2dp04Mi3KxEYSkDt9AoW6K', 'Angelica Rubrico', 'angelica@gmail.com', '09189283283', 'admin', 'uploads/profile_pictures/profile_6846881844a1d.jpg', 1, NULL, '2025-06-06 07:04:25', '2025-06-09 07:07:04'),
(56, 13, 'jerichosantiago.intern', '$2y$10$/ZFXTP37oQPnmQt72u7u8OVC7Or9CItyK99KlzmO1ErPu9CGYSWia', 'Jericho jericho', 'jericho@gmail.com', '09123829382', 'agent', NULL, 1, NULL, '2025-06-06 07:04:40', '2025-06-09 03:35:42'),
(57, 13, 'leonardpistano.intern', '$2y$10$w81317xjdkeDDONYBsL3neTeH6RQgmQB8Bvcq7Ys/r..LN1eMZ3Xa', 'Leonard Pistano', 'leonardpistano@gmail.com', '09827328731', 'admin', 'uploads/profile_pictures/profile_684672216b847.jpg', 1, NULL, '2025-06-06 07:04:59', '2025-06-09 05:33:21'),
(58, 13, 'ginineangelique.intern', '$2y$10$mU/Bxb/5gAq7FgdmcPvZ.u4jTvlF3U480IoSGIOB2d2f2w5TEGP3.', 'Ginine Angelique', 'ginine.innersparc@gmail.com', '09812398129', 'agent', 'uploads/profile_pictures/profile_6846559bbd931.png', 1, NULL, '2025-06-06 07:06:22', '2025-06-09 07:08:35'),
(59, 13, 'danielpagilagan.intern', '$2y$10$cFm.oaARNgZRi60DQUJSp.juOikayA5WOSK1qd4J2sgODY8LJ3TLi', 'Daniel Pagilagan', 'daniel@gmail.com', '09122938298', 'admin', 'uploads/profile_pictures/profile_68468695826b4.jpg', 1, NULL, '2025-06-09 06:57:55', '2025-06-09 07:00:37'),
(60, 1, '8aisdhasidhsajdh.innersparc', '$2y$10$01NJC.KtaEVQcPScHJHkjuReVaeTzjUynzt6b1PngtNgGPJL9jfxG', '8aisdhasidhsajdh', 'askdj@gmail.com', '09128383838', 'agent', NULL, 1, NULL, '2025-06-13 02:41:51', '2025-06-13 02:41:51'),
(61, 8, 'juandelacruz.innersparc', '$2y$10$IcX.QpMAIA84BPCqcHjn7uIwOpGoLfoP00sUjck2nxDYkKTVWNl7.', 'juandelacruz', 'markkksjd@gmail.com', '09182382828', 'agent', NULL, 1, NULL, '2025-06-16 08:39:45', '2025-06-16 08:39:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `province_id` (`province_id`);

--
-- Indexes for table `developers`
--
ALTER TABLE `developers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `downpayment_tracker`
--
ALTER TABLE `downpayment_tracker`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lead_id` (`lead_id`),
  ADD KEY `reservation_date` (`reservation_date`),
  ADD KEY `next_payment_date` (`next_payment_date`);

--
-- Indexes for table `handbooks`
--
ALTER TABLE `handbooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `handbook_pages`
--
ALTER TABLE `handbook_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `handbook_id` (`handbook_id`,`page_number`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `temperature` (`temperature`),
  ADD KEY `source` (`source`),
  ADD KEY `follow_up_date` (`follow_up_date`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `lead_activities`
--
ALTER TABLE `lead_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `activity_type` (`activity_type`),
  ADD KEY `scheduled_date` (`scheduled_date`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `lead_modifications`
--
ALTER TABLE `lead_modifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `activity_id` (`activity_id`);

--
-- Indexes for table `memos`
--
ALTER TABLE `memos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `memo_when` (`memo_when`),
  ADD KEY `priority` (`priority`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `memo_images`
--
ALTER TABLE `memo_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`);

--
-- Indexes for table `memo_read_status`
--
ALTER TABLE `memo_read_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `memo_employee_unique` (`memo_id`,`employee_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `read_status` (`read_status`);

--
-- Indexes for table `memo_team_visibility`
--
ALTER TABLE `memo_team_visibility`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_memo_team` (`memo_id`,`team_id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indexes for table `memo_visibility`
--
ALTER TABLE `memo_visibility`
  ADD PRIMARY KEY (`id`),
  ADD KEY `memo_id` (`memo_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `priority` (`priority`),
  ADD KEY `price_min` (`price_min`),
  ADD KEY `price_max` (`price_max`),
  ADD KEY `city_id` (`city_id`),
  ADD KEY `province_id` (`province_id`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `role` (`role`),
  ADD KEY `is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `developers`
--
ALTER TABLE `developers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `downpayment_tracker`
--
ALTER TABLE `downpayment_tracker`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `handbooks`
--
ALTER TABLE `handbooks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `handbook_pages`
--
ALTER TABLE `handbook_pages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `lead_activities`
--
ALTER TABLE `lead_activities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `lead_modifications`
--
ALTER TABLE `lead_modifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `memos`
--
ALTER TABLE `memos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `memo_images`
--
ALTER TABLE `memo_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `memo_read_status`
--
ALTER TABLE `memo_read_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `memo_team_visibility`
--
ALTER TABLE `memo_team_visibility`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `memo_visibility`
--
ALTER TABLE `memo_visibility`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

-- --------------------------------------------------------

--
-- Structure for view `active_leads_summary`
--
DROP TABLE IF EXISTS `active_leads_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`managementlead`@`66.33.192.0/255.255.224.0` SQL SECURITY DEFINER VIEW `active_leads_summary`  AS SELECT `l`.`id` AS `id`, `l`.`client_name` AS `client_name`, `l`.`phone` AS `phone`, `l`.`email` AS `email`, `l`.`temperature` AS `temperature`, `l`.`status` AS `status`, `l`.`source` AS `source`, `l`.`developer` AS `developer`, `l`.`project_model` AS `project_model`, `l`.`price` AS `price`, `l`.`expected_commission` AS `expected_commission`, `u`.`name` AS `agent_name`, `t`.`name` AS `team_name`, `l`.`follow_up_date` AS `follow_up_date`, `l`.`created_at` AS `created_at` FROM ((`leads` `l` join `users` `u` on((`l`.`user_id` = `u`.`id`))) left join `teams` `t` on((`u`.`team_id` = `t`.`id`))) WHERE (`l`.`status` not in ('Closed Deal','Lost')) ORDER BY `l`.`follow_up_date` ASC, `l`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `monthly_sales_report`
--
DROP TABLE IF EXISTS `monthly_sales_report`;

CREATE ALGORITHM=UNDEFINED DEFINER=`managementlead`@`66.33.192.0/255.255.224.0` SQL SECURITY DEFINER VIEW `monthly_sales_report`  AS SELECT year(`l`.`updated_at`) AS `year`, month(`l`.`updated_at`) AS `month`, monthname(`l`.`updated_at`) AS `month_name`, count((case when (`l`.`status` = 'Closed Deal') then 1 end)) AS `deals_closed`, sum((case when (`l`.`status` = 'Closed Deal') then `l`.`price` else 0 end)) AS `total_sales`, sum((case when (`l`.`status` = 'Closed Deal') then `l`.`expected_commission` else 0 end)) AS `total_commission`, avg((case when (`l`.`status` = 'Closed Deal') then `l`.`price` else NULL end)) AS `average_deal_size` FROM `leads` AS `l` WHERE (`l`.`status` = 'Closed Deal') GROUP BY year(`l`.`updated_at`), month(`l`.`updated_at`) ORDER BY `year` DESC, `month` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `team_performance_summary`
--
DROP TABLE IF EXISTS `team_performance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`managementlead`@`66.33.192.0/255.255.224.0` SQL SECURITY DEFINER VIEW `team_performance_summary`  AS SELECT `t`.`id` AS `team_id`, `t`.`name` AS `team_name`, count(distinct `u`.`id`) AS `total_agents`, count(`l`.`id`) AS `total_leads`, count((case when (`l`.`status` = 'Closed Deal') then 1 end)) AS `closed_deals`, sum((case when (`l`.`status` = 'Closed Deal') then `l`.`price` else 0 end)) AS `total_sales`, sum((case when (`l`.`status` = 'Closed Deal') then `l`.`expected_commission` else 0 end)) AS `total_commission`, round(((count((case when (`l`.`status` = 'Closed Deal') then 1 end)) * 100.0) / nullif(count(`l`.`id`),0)),2) AS `conversion_rate` FROM ((`teams` `t` left join `users` `u` on(((`t`.`id` = `u`.`team_id`) and (`u`.`role` in ('agent','supervisor','manager'))))) left join `leads` `l` on((`u`.`id` = `l`.`user_id`))) GROUP BY `t`.`id`, `t`.`name` ORDER BY `total_sales` DESC ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cities`
--
ALTER TABLE `cities`
  ADD CONSTRAINT `cities_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `downpayment_tracker`
--
ALTER TABLE `downpayment_tracker`
  ADD CONSTRAINT `downpayment_tracker_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `handbooks`
--
ALTER TABLE `handbooks`
  ADD CONSTRAINT `handbooks_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `handbook_pages`
--
ALTER TABLE `handbook_pages`
  ADD CONSTRAINT `handbook_pages_ibfk_1` FOREIGN KEY (`handbook_id`) REFERENCES `handbooks` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `lead_modifications`
--
ALTER TABLE `lead_modifications`
  ADD CONSTRAINT `lead_modifications_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_modifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_modifications_ibfk_3` FOREIGN KEY (`activity_id`) REFERENCES `lead_activities` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `memos`
--
ALTER TABLE `memos`
  ADD CONSTRAINT `memos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `memos_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Constraints for table `memo_images`
--
ALTER TABLE `memo_images`
  ADD CONSTRAINT `memo_images_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `memo_read_status`
--
ALTER TABLE `memo_read_status`
  ADD CONSTRAINT `memo_read_status_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_read_status_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `memo_team_visibility`
--
ALTER TABLE `memo_team_visibility`
  ADD CONSTRAINT `memo_team_visibility_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_team_visibility_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `memo_visibility`
--
ALTER TABLE `memo_visibility`
  ADD CONSTRAINT `memo_visibility_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `projects_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
