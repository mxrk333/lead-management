-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: lecain.pdx1-mysql-a7-6a.dreamhost.com
-- Generation Time: Jun 03, 2026 at 11:16 PM
-- Server version: 8.0.41-0ubuntu0.24.04.1
-- PHP Version: 8.1.2-1ubuntu2.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `managementleaddb`
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
,`status` enum('Inquiry','Presentation Stage','Negotiation','Closed','Site Tour','Closed Deal','Requirement Stage','Downpayment Stage','Housing Loan Application','Loan Approval','Loan Takeout','House Inspection','House Turn Over','Lost')
,`source` enum('Facebook Groups','KKK','Facebook Ads','TikTok ads','Google Ads','Facebook live','Referral','Teleprospecting','Video Message','Organic Posting','Email Marketing','Follow up','Manning','Walk in','Flyering','Chat messaging','Property Listing','Landing Page','Networking Events','Organic Sharing','Youtube Marketing','LinkedIn','Open House','Facebook Page','Others')
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
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int NOT NULL,
  `province_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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
(25, 11, 'Gentri', '2025-06-16 06:06:33'),
(26, 15, 'San Jose Del Monte', '2025-07-03 02:01:32'),
(27, 14, 'Balete', '2025-07-03 02:04:15'),
(28, 14, 'Laiya', '2025-07-03 02:06:21'),
(29, 14, 'Lipa', '2025-07-03 02:06:43'),
(30, 13, 'Biñan', '2025-07-03 02:33:25'),
(31, 15, 'Marilao', '2025-07-03 02:47:11'),
(32, 14, 'San Juan', '2025-07-03 09:47:11'),
(33, 11, 'Dasmariñas', '2025-07-04 09:47:11'),
(34, 16, 'Pasay', '2025-07-07 01:28:09'),
(35, 16, 'Quezon City', '2025-07-07 01:41:10'),
(36, 16, 'Makati', '2025-07-07 01:49:44'),
(37, 16, 'Manila', '2025-07-07 01:53:50'),
(38, 16, 'Pasig', '2025-07-07 02:00:47'),
(39, 16, 'Mandaluyong', '2025-07-07 02:08:09'),
(40, 14, 'Sto. Tomas', '2025-07-07 03:40:15'),
(41, 14, 'Tanauan', '2025-07-07 03:48:17'),
(42, 13, 'San Pedro', '2025-07-10 09:47:11'),
(43, 13, 'Calamba', '2025-07-10 09:47:11');

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
(11, 'Avida', 'Trusted name in quality residential developments', 'Michael Avida', 'quality@avida.com', '02-8012-3456', 1, '2025-05-19 09:50:31', '2025-06-06 06:26:13'),
(12, 'Evo City', NULL, NULL, NULL, NULL, 1, '2025-07-03 05:54:38', '2025-07-03 05:54:38'),
(13, 'Kaia Homes', 'Affordable 2BR Townhouse Unit', 'Kaia Arceta', 'kaiahomesofficial@gmail.com', '02-8912-3756', 1, '2025-07-15 07:29:29', '2025-07-15 07:29:29'),
(14, 'Northdale Estates', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:47:56', '2025-07-18 09:47:56'),
(16, 'Axeia Dasmariñas', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:50:27', '2025-07-18 09:50:27'),
(17, 'Neuville Townhomes', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:50:52', '2025-07-18 09:50:52'),
(18, 'Sapphire Residences', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:51:10', '2025-07-18 09:51:10'),
(19, 'Pacific Ace Village', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:51:28', '2025-07-18 09:51:28'),
(20, 'Istana Tanza', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:51:38', '2025-07-18 09:51:38'),
(21, 'Estanzia Enclaves', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:51:44', '2025-07-18 09:51:44'),
(22, '3 Verde Rosa', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:51:54', '2025-07-18 09:51:54'),
(23, 'Emerald Residences', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:52:01', '2025-07-18 09:52:01'),
(24, 'Micara Estates', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:52:05', '2025-07-18 09:52:05'),
(25, 'Savia Parkway', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:52:10', '2025-07-18 09:52:10'),
(26, 'Westwind at Lancaster City', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:52:18', '2025-07-18 09:52:18'),
(27, 'Pineview', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:52:23', '2025-07-18 09:52:23'),
(28, 'Southdale Villas', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:52:29', '2025-07-18 09:52:29'),
(30, 'Antel', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:53:33', '2025-07-18 09:53:33'),
(31, 'Hana South', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:53:38', '2025-07-18 09:53:38'),
(32, 'Erinville', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:53:42', '2025-07-18 09:53:42'),
(33, 'Masaito Homes Trece', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:53:51', '2025-07-18 09:53:51'),
(34, 'Beverly Homes', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:53:57', '2025-07-18 09:53:57'),
(35, 'New Leaf', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:54:04', '2025-07-18 09:54:04'),
(36, 'Mozzafiato', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:54:16', '2025-07-18 09:54:16'),
(37, 'Porto Laiya', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:54:23', '2025-07-18 09:54:23'),
(38, 'Axeia Batangas', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:54:27', '2025-07-18 09:54:27'),
(39, 'The Granary', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:54:52', '2025-07-18 09:54:52'),
(40, 'Rosepointe', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:54:56', '2025-07-18 09:54:56'),
(41, 'Vista Rosa', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:55:17', '2025-07-18 09:55:17'),
(42, 'Golden Montana', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:55:24', '2025-07-18 09:55:24'),
(43, 'Golden Vista', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:55:42', '2025-07-18 09:55:42'),
(44, 'Pililla Heights', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:55:53', '2025-07-18 09:55:53'),
(45, 'Berde Ville', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:55:59', '2025-07-18 09:55:59'),
(46, 'Pagsikat Muzon', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:56:30', '2025-07-18 09:56:30'),
(47, 'Merrydale St. Joseph', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:56:39', '2025-07-18 09:56:39'),
(48, 'Tarragona Place', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:58:06', '2025-07-18 09:58:06'),
(49, 'Amaia Skies Avenida', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:58:16', '2025-07-18 09:58:16'),
(50, 'Greenway', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:58:27', '2025-07-18 09:58:27'),
(51, 'Pasinaya Homes', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:58:36', '2025-07-18 09:58:36'),
(52, 'Pagsibol Village', NULL, NULL, NULL, NULL, 1, '2025-07-18 09:58:42', '2025-07-18 09:58:42'),
(53, 'Villas De Trece', NULL, NULL, NULL, NULL, 1, '2025-07-18 10:02:58', '2025-07-18 10:02:58'),
(54, 'Lanello Heights', NULL, NULL, NULL, NULL, 1, '2025-07-18 10:10:53', '2025-07-18 10:10:53'),
(55, 'Monte Royale (Parc Royal)', NULL, NULL, NULL, NULL, 1, '2025-07-18 10:16:18', '2025-07-18 10:16:18'),
(56, 'Park Infina (Parc Royal)', NULL, NULL, NULL, NULL, 1, '2025-07-18 10:16:27', '2025-07-18 10:16:27'),
(57, 'COMELEC Village (Parc Royal)', NULL, NULL, NULL, NULL, 1, '2025-07-18 10:16:53', '2025-07-18 10:16:53'),
(58, 'Parksville Residences (Parc Royal)', NULL, NULL, NULL, NULL, 1, '2025-07-18 10:18:52', '2025-07-18 10:18:52'),
(59, 'Deca Homes Hampton – Imus Cavite', NULL, NULL, NULL, NULL, 1, '2025-11-12 04:17:33', '2025-11-12 04:17:33'),
(60, 'Monterra Verde 2', NULL, NULL, NULL, NULL, 1, '2025-11-15 00:24:13', '2025-11-15 00:24:13'),
(61, 'Bria', NULL, NULL, NULL, NULL, 1, '2026-04-10 06:31:03', '2026-04-10 06:31:03'),
(62, 'Lessandra by Bria', NULL, NULL, NULL, NULL, 1, '2026-04-10 06:33:27', '2026-04-10 06:33:27');

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
(15, 128, NULL, 1, 1, 0.00, '6', 0.00, 1, 1, 0.00, 0.00, 1, 0.00, 1, NULL, 1, NULL, 100.00, NULL, '2025-07-18 09:20:51', '2025-07-18 09:20:51'),
(21, 202, '2025-08-03', 1, 1, 0.00, '6', 0.00, 1, 1, 0.00, 0.00, 1, 0.00, 1, NULL, 1, NULL, 100.00, NULL, '2025-08-04 07:13:21', '2025-08-04 07:13:21'),
(22, 214, '2025-08-27', 1, 0, 0.00, '6', 0.00, 6, 6, 0.00, 0.00, 1, 0.00, 1, NULL, 1, NULL, 100.00, NULL, '2025-09-01 06:41:34', '2025-09-01 06:42:15'),
(23, 209, '2025-06-29', 1, 0, 0.00, '12', 0.00, 6, 12, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 50.00, NULL, '2025-09-06 10:58:26', '2025-12-25 01:32:46'),
(24, 210, '2025-07-12', 1, 0, 0.00, '12', 0.00, 5, 12, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 41.67, NULL, '2025-09-06 11:05:45', '2025-12-25 01:31:16'),
(25, 208, '2025-03-03', 1, 0, 0.00, '12', 0.00, 7, 12, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 58.33, NULL, '2025-09-06 11:08:22', '2025-12-25 02:23:09'),
(26, 222, '2025-08-24', 1, 0, 0.00, '12', 0.00, 1, 12, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 20.00, NULL, '2025-09-06 11:25:54', '2025-09-06 11:25:54'),
(46, 264, '2025-10-06', 1, 0, 0.00, '12', 0.00, 1, 12, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 8.33, NULL, '2025-10-08 08:59:48', '2025-10-08 09:18:54'),
(47, 403, '2025-09-30', 1, 0, 0.00, '12', 0.00, 3, 12, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 25.00, NULL, '2025-10-10 07:15:17', '2025-12-30 13:42:23'),
(48, 205, '2025-08-18', 1, 1, 0.00, '6', 0.00, 1, 6, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 16.00, NULL, '2025-10-10 07:17:38', '2025-10-10 07:17:38'),
(49, 314, '2025-09-24', 1, 0, 0.00, '36', 0.00, 4, 36, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 11.11, NULL, '2025-10-21 06:34:16', '2025-12-25 01:16:34'),
(50, 443, '2025-11-03', 0, 0, 0.00, '12', 0.00, 1, 12, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 8.00, NULL, '2025-12-25 03:01:21', '2025-12-25 03:01:21'),
(51, 476, '2026-01-15', 1, 0, 0.00, '6', 0.00, 1, 6, 0.00, 0.00, 0, 0.00, 0, NULL, 0, NULL, 16.67, NULL, '2026-02-21 17:08:23', '2026-02-21 17:10:02');

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
(2, 'From Zero to Hero:', 'A Rookie Agent’s\r\n\r\n16-step Ultimate Playbook', '', 'uploads/handbook_covers/1750055656_Screenshot 2025-06-16 141054.png', 'uploads/handbook_pdfs/1750055656_Copy of HandBook_20250616_140352_0000 (1).pdf', 15, '2025-06-16 06:34:20', '2025-06-16 06:34:20');

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
-- Table structure for table `incentives`
--

CREATE TABLE `incentives` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `position` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `total_sales` decimal(15,2) DEFAULT '0.00',
  `incentive_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `destination` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incentives`
--

INSERT INTO `incentives` (`id`, `user_id`, `position`, `total_sales`, `incentive_type`, `destination`, `created_at`) VALUES
(1, 8, 'Agent', 500000.00, 'International Tour', 'Malaysia/Indonesia', '2025-06-19 13:26:49'),
(2, 8, 'Agent', 500000.00, 'International Tour', 'Singapore', '2025-06-19 13:26:49'),
(3, 8, 'Agent', 500000.00, 'Local Tour', 'Baguio', '2025-06-19 13:26:49'),
(4, 8, 'Agent', 500000.00, 'Local Tour', 'Boracay', '2025-06-19 13:26:49'),
(5, 58, 'Agent', 100000.00, 'International Tour', 'Malaysia/Indonesia', '2025-06-19 13:27:19'),
(6, 58, 'Agent', 100000.00, 'International Tour', 'Singapore', '2025-06-19 13:27:19'),
(7, 58, 'Agent', 100000.00, 'Local Tour', 'Baguio', '2025-06-19 13:27:19'),
(8, 58, 'Agent', 100000.00, 'Local Tour', 'Boracay', '2025-06-19 13:27:19'),
(13, 56, 'Agent', 100.00, 'International Tour', 'Malaysia/Indonesia', '2025-06-20 03:05:00'),
(14, 56, 'Agent', 100.00, 'International Tour', 'Singapore', '2025-06-20 03:05:00'),
(15, 56, 'Agent', 100.00, 'Local Tour', 'Baguio', '2025-06-20 03:05:00'),
(16, 56, 'Agent', 100.00, 'Local Tour', 'Boracay', '2025-06-20 03:05:00');

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
  `status` enum('Inquiry','Presentation Stage','Negotiation','Closed','Site Tour','Closed Deal','Requirement Stage','Downpayment Stage','Housing Loan Application','Loan Approval','Loan Takeout','House Inspection','House Turn Over','Lost') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source` enum('Facebook Groups','KKK','Facebook Ads','TikTok ads','Google Ads','Facebook live','Referral','Teleprospecting','Video Message','Organic Posting','Email Marketing','Follow up','Manning','Walk in','Flyering','Chat messaging','Property Listing','Landing Page','Networking Events','Organic Sharing','Youtube Marketing','LinkedIn','Open House','Facebook Page','Others') COLLATE utf8mb4_general_ci NOT NULL,
  `lead_classification` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Employment classification: Locally/Internationally Employed, OFW, Self employed',
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

INSERT INTO `leads` (`id`, `user_id`, `client_name`, `phone`, `email`, `facebook`, `linkedin`, `address`, `temperature`, `status`, `source`, `lead_classification`, `developer`, `project_model`, `price`, `commission_rate`, `expected_commission`, `remarks`, `follow_up_date`, `created_at`, `updated_at`) VALUES
(27, 58, 'Phoenix Zeta', '09171520934', 'ginine.innersparc@gmail.com', 'https://www.facebook.com/share/17sb12Bo2u/', '', NULL, 'Hot', 'Negotiation', 'Facebook Page', NULL, 'Pleasantfields', 'Kennedy', 3900000.00, 0.00, 0.00, 'Keri lang.', NULL, '2025-06-09 03:41:02', '2025-07-18 01:51:27'),
(42, 31, 'riziel', '+639622113506', 'violentamanny@gmail.com', '', '', NULL, 'Warm', 'Lost', 'Organic Sharing', 'Locally/Internationally Employed', 'Pleasantfields', 'Kennedy', 3000000.00, 0.00, 0.00, 'already tripping in sapphire residences last june 28.\r\nthey choose lot only, nakkauha na', NULL, '2025-07-03 04:04:31', '2025-11-09 07:04:39'),
(46, 9, 'Nix Bambilla', '09613825054', 'alvinllaneta8@gmail.com', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Groups', NULL, 'Liora Homes', 'Amora', 2516400.00, 0.00, 0.00, '', NULL, '2025-07-04 08:50:59', '2025-07-04 09:01:17'),
(48, 24, 'April Talana', '09649589052', 'jobarceta@gmail.com', '', '', NULL, 'Warm', 'Lost', 'KKK', NULL, 'Kaia Homes', 'Helena', 2000000.00, 0.00, 0.00, 'Nag iba ng plan, kukuha na lang ng lot para sama sama sa isang compound', NULL, '2025-07-04 08:59:06', '2025-07-22 05:00:10'),
(49, 13, 'Philip DE gracia', '09560149128', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Facebook Ads', NULL, 'Minami Residence', 'Hana', 4100000.00, 0.00, 0.00, 'Reserved a unit on going downpayment completion of requirements.', NULL, '2025-07-04 08:59:11', '2025-09-10 23:20:30'),
(50, 12, 'Mark Angelo Altamera', '09605666637', '', '', '', NULL, 'Warm', 'Negotiation', 'KKK', NULL, 'Lancaster', 'Thea', 1900000.00, 0.00, 0.00, '', NULL, '2025-07-04 08:59:40', '2025-07-04 08:59:40'),
(57, 55, 'Biboy Hilario', '09602531452', '', 'https://www.facebook.com/kris.hilario.940', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 6998713.00, 0.00, 0.00, '', NULL, '2025-07-15 06:53:19', '2025-07-15 06:53:19'),
(58, 58, 'Leo Anuled', '09933892608', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Pleasantfields', 'Kennedy', 3064689.81, 0.00, 0.00, '', NULL, '2025-07-15 06:56:08', '2025-07-15 06:56:08'),
(62, 55, 'Patty Capada - Luna', '09063770476', 'mafatimacapada@gmail.com', 'https://www.facebook.com/patty.capada', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 3228145.00, 0.00, 0.00, 'Lead inquired for Dahlia Model', NULL, '2025-07-15 06:58:57', '2025-07-15 06:58:57'),
(63, 55, 'Chy Chy', '', '', 'https://www.facebook.com/mugiwarachy', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 3550960.00, 0.00, 0.00, 'Client inquire Dahlia Model', NULL, '2025-07-15 07:02:53', '2025-07-15 07:02:53'),
(64, 56, 'Glazie Jane Sode-Gacelos', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 07:03:59', '2025-07-15 07:03:59'),
(65, 58, 'Mhay Llasus Sarino', '09933892608', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Pleasantfields', 'Lincoln', 3886222.93, 0.00, 0.00, '', NULL, '2025-07-15 07:05:29', '2025-07-15 07:05:29'),
(66, 55, 'Jamie Bonsay', '', '', 'https://www.facebook.com/jamieeeow', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 5325995.00, 0.00, 0.00, 'Client inquired Canalily model', NULL, '2025-07-15 07:06:47', '2025-07-15 07:06:47'),
(67, 55, 'Charish Dimayuga', '', '', 'https://www.facebook.com/chaaarish', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 6998713.00, 0.00, 0.00, 'client didn\'t inquire any house model', NULL, '2025-07-15 07:12:34', '2025-07-15 07:12:34'),
(68, 55, 'Thomas Vicente', '09610176827', 'mttvicente.sbcm@gmail.com', 'https://www.facebook.com/thomvicente', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 6998713.00, 0.00, 0.00, 'didn\'t inquire for any house model', NULL, '2025-07-15 07:15:12', '2025-07-15 07:15:12'),
(69, 56, 'Mau Gutier', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 07:15:17', '2025-07-15 07:15:17'),
(70, 55, 'Joshua Espanillo', '', '', 'https://www.facebook.com/joswa1022', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 6998713.00, 0.00, 0.00, 'client inquire for Dahlia Model', NULL, '2025-07-15 07:16:57', '2025-07-15 07:16:57'),
(71, 58, 'Lloyd Baguio Rubio', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Pleasantfields', 'Lincoln', 2899746.84, 0.00, 0.00, '', NULL, '2025-07-15 07:19:09', '2025-07-15 07:19:09'),
(72, 55, 'Sharlyn Pagdanganan', '', '', 'https://www.facebook.com/sha.pagdanganan', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 6998713.00, 0.00, 0.00, 'client inquire Dahlia model', NULL, '2025-07-15 07:20:27', '2025-07-15 07:20:27'),
(73, 55, 'Bob Israel', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Pleasantfields', 'Kennedy', 3064689.81, 0.00, 0.00, '', NULL, '2025-07-15 07:24:18', '2025-07-15 07:24:18'),
(74, 55, 'Alliah Redondo Alcaparaz', '', '', 'https://www.facebook.com/Alcaparaz.Alliah', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2744000.00, 0.00, 0.00, '', NULL, '2025-07-15 07:26:03', '2025-07-15 07:26:03'),
(75, 55, 'Mumu JU', '', '', 'https://www.facebook.com/joyley08', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2543520.00, 0.00, 0.00, '', NULL, '2025-07-15 07:27:54', '2025-07-15 07:27:54'),
(76, 56, 'Lei Andrea', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 07:28:05', '2025-07-15 07:28:05'),
(77, 56, 'Ahmad Aziz', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 07:31:12', '2025-07-15 07:31:12'),
(78, 55, 'Hazel Cap', '', '', 'https://www.facebook.com/hazel.cap', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 3550960.00, 0.00, 0.00, '', NULL, '2025-07-15 07:31:14', '2025-07-15 07:35:41'),
(79, 56, 'Cath Inocencio', '09171149002', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 07:35:24', '2025-07-15 07:35:24'),
(81, 58, 'Thea Loreto', '09933892608', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Kaia Homes', 'Helena', 1493503.00, 0.00, 0.00, '', NULL, '2025-07-15 07:35:43', '2025-07-15 07:35:43'),
(82, 58, 'Alondra Sangil', '09933892608', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Pleasantfields', 'Nixon', 2899946.84, 0.00, 0.00, '', NULL, '2025-07-15 07:37:42', '2025-07-15 07:37:42'),
(83, 56, 'Eper Quiño', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Minami Residence', 'Hana', 4647400.00, 0.00, 0.00, '', NULL, '2025-07-15 07:40:48', '2025-07-15 07:40:48'),
(84, 58, 'Ryan Basa', '09933892608', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Pleasantfields', 'Lincoln', 3877422.93, 0.00, 0.00, 'Client did not answer properly if he wants it or not', NULL, '2025-07-15 07:41:07', '2025-07-15 07:41:07'),
(85, 56, 'Jouwaher Javar - Jamias', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 07:42:48', '2025-07-15 07:42:48'),
(86, 56, 'Judith Ann Nuñez', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'client didn\'t inquire any model house', NULL, '2025-07-15 07:48:05', '2025-07-15 07:48:05'),
(87, 58, 'Prince John Coles', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Pleasantfields', 'Lincoln', 2989000.00, 0.00, 0.00, 'Client asks for details and computation only, without further messages.', NULL, '2025-07-15 07:53:26', '2025-07-15 07:53:26'),
(88, 56, 'Odysseus Kyle Cuera', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 07:53:38', '2025-07-15 07:53:38'),
(89, 58, 'Garry Jatico Dela Peña', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Pleasantfields', 'Lincoln', 2989000.00, 0.00, 0.00, 'Client asks for how much monthly only, without futher other messages.', NULL, '2025-07-15 07:55:06', '2025-07-15 07:55:06'),
(90, 56, 'Carmela Dela Rosa-Santiago', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'The Client didn\'t pick model house', NULL, '2025-07-15 07:57:34', '2025-07-15 07:57:34'),
(91, 58, 'Mitch Laurel', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Pleasantfields', 'Lincoln', 2989000.00, 0.00, 0.00, 'Client didn\'t pick any model houses.', NULL, '2025-07-15 07:59:41', '2025-07-15 07:59:41'),
(92, 56, 'Roschell Carrido', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Client didn\'t pick any house model', NULL, '2025-07-15 08:02:14', '2025-07-15 08:02:14'),
(93, 56, 'Jocelyn Piliin', '09725875509', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'client didn\'t pick model house yet', NULL, '2025-07-15 08:04:58', '2025-07-15 08:04:58'),
(94, 56, 'Charizze Esto', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Client decided not to procced because of aqualink', NULL, '2025-07-15 08:12:09', '2025-07-15 08:12:09'),
(95, 56, 'Angelica Viado Abuan', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'client inquires only waiting for the response', NULL, '2025-07-15 08:14:20', '2025-07-15 08:14:20'),
(96, 56, 'Jena VM', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Client is undecided', NULL, '2025-07-15 08:22:54', '2025-07-15 08:22:54'),
(97, 56, 'Tricia Pauline', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Didn\'t response', NULL, '2025-07-15 08:24:59', '2025-07-15 08:24:59'),
(98, 56, 'Hussien Nawab', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 08:31:21', '2025-07-15 08:31:21'),
(99, 56, 'Robert Rodriguez', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 08:35:55', '2025-07-15 08:35:55'),
(100, 56, 'Jeicelle Bagalawis-Melo', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'not responding yet', NULL, '2025-07-15 08:38:18', '2025-07-15 08:38:18'),
(101, 56, 'Cristina Semilla', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'didn\'t response', NULL, '2025-07-15 08:40:04', '2025-07-15 08:40:04'),
(102, 56, 'Catherine', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Didn\'t response', NULL, '2025-07-15 08:41:10', '2025-07-15 08:41:10'),
(103, 56, 'Sally Barrete Çıtak', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'didn\'t respond', NULL, '2025-07-15 08:42:18', '2025-07-15 08:42:18'),
(104, 56, 'Margie Sgarbossa', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 08:45:45', '2025-07-15 08:45:45'),
(105, 56, 'Dorine Cubacub', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, 'Didn\'t pick house yet', NULL, '2025-07-15 08:48:24', '2025-07-21 01:30:19'),
(106, 56, 'Harold Hade Bustarga', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, 'didn\'t pick any house yet', NULL, '2025-07-15 08:50:47', '2025-07-21 01:34:34'),
(107, 56, 'Alvv Aguirre', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, 'didn\'t pick any house yet', NULL, '2025-07-15 08:53:28', '2025-07-21 01:34:56'),
(108, 56, 'Vic Del Rosario', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, '', NULL, '2025-07-15 08:57:52', '2025-07-21 01:35:10'),
(109, 56, 'Rey Ruel Luba Gemodo', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 09:01:26', '2025-07-15 09:01:26'),
(110, 56, 'David Arquero', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, '', NULL, '2025-07-15 09:20:14', '2025-07-21 01:35:22'),
(111, 56, 'Jinky Toledo - Nardo', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, 'didn\'t respond after', NULL, '2025-07-15 09:31:07', '2025-07-21 01:35:46'),
(112, 56, 'Enilegnave Zapanta', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, 'deciding', NULL, '2025-07-15 09:36:04', '2025-07-21 01:36:04'),
(113, 56, 'Emmanuel Ferrer', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Facebook Page', NULL, 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, '', NULL, '2025-07-15 09:43:09', '2025-07-15 09:43:09'),
(114, 56, 'Sajad Bloch', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, 'Didn\'t response', NULL, '2025-07-15 09:46:06', '2025-07-21 01:27:35'),
(115, 56, 'Rachelle Encontro', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, 'didn\'t response', NULL, '2025-07-15 09:47:39', '2025-07-21 01:27:50'),
(116, 56, 'Hanzoo Gonzales', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, '', NULL, '2025-07-15 10:01:44', '2025-07-21 01:28:07'),
(117, 56, 'Joanna Mae Maguliman', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, '', NULL, '2025-07-15 10:05:32', '2025-07-21 01:28:58'),
(118, 56, 'Melissa Alvarez Zamora - Sedantes', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, '', NULL, '2025-07-15 10:10:17', '2025-07-21 01:29:23'),
(119, 56, 'Nicole Rosales Mendoza', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-07-15 12:01:40', '2025-07-15 13:02:03'),
(120, 56, 'Mhay Llasus Sarino', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, 'didn\'t pick house yet but she\'s waiting for the response of admin', NULL, '2025-07-15 12:04:20', '2025-07-21 01:29:39'),
(121, 56, 'Sugar Jusay', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, '', NULL, '2025-07-15 12:08:09', '2025-07-21 01:19:57'),
(122, 56, 'Rachel Morales', '09569331552', 'rachelronelle@gmail.com', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, '', NULL, '2025-07-15 12:12:03', '2025-07-21 01:19:41'),
(123, 56, 'Alexis Grace Afable - Roque', '09569331552', 'rachelronelle@gmail.com', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, '', NULL, '2025-07-15 12:18:37', '2025-07-21 01:19:30'),
(124, 56, 'Andre Opiana', '09569331552', 'rachelronelle@gmail.com', '', '', NULL, 'Warm', 'Negotiation', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2783000.00, 0.00, 0.00, '', NULL, '2025-07-15 12:56:38', '2025-07-21 01:19:16'),
(126, 32, 'KATHELEEN HILLARY MANANGAN', '', '', '', '', NULL, 'Hot', 'House Turn Over', 'Facebook Page', NULL, 'Pleasantfields', 'Kennedy', 3168198.98, 0.00, 0.00, 'COMPLETED 100 COMM RELEASE', NULL, '2025-07-18 02:41:46', '2025-07-18 02:41:46'),
(127, 32, 'SAJJADE TUGANO', '', '', '', '', NULL, 'Hot', 'House Turn Over', 'Organic Posting', NULL, 'Pleasantfields', 'Kennedy', 368198.98, 0.00, 0.00, 'COMPLETE...WAITING FOR COMM RELEASE', NULL, '2025-07-18 02:46:26', '2025-07-18 02:46:26'),
(128, 32, 'AJ MAULION LLANES', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Organic Posting', NULL, 'Pleasantfields', 'Kennedy', 3064689.81, 0.00, 0.00, 'STILL WAITING FOR THE REPLY.(FB ACCOUNT-ANNALYN)', NULL, '2025-07-18 02:52:06', '2025-08-14 11:35:23'),
(130, 55, 'Joemar Villamena Balboa', '09922930323', '', 'https://www.facebook.com/balongskie.balboa', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3550960.00, 0.00, 0.00, '', NULL, '2025-07-18 09:13:32', '2025-07-18 09:13:55'),
(131, 55, 'Charito AV Espino', '', '', 'https://www.facebook.com/charito.espino.9', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3550960.00, 0.00, 0.00, '', NULL, '2025-07-18 09:19:41', '2025-07-18 09:19:41'),
(132, 55, 'Loida Saguinsin', '', '', 'https://www.facebook.com/loida.saguinsin', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3550960.00, 0.00, 0.00, '', NULL, '2025-07-18 09:21:22', '2025-07-18 09:21:22'),
(133, 55, 'Christine Atienza Go', '', '', 'https://www.facebook.com/tintin.d.go', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3550960.00, 0.00, 0.00, '', NULL, '2025-07-18 09:23:46', '2025-07-18 09:23:46'),
(134, 55, 'Abs Quiambao', '', '', 'https://www.facebook.com/gael.quiambao', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-18 09:27:42', '2025-07-18 09:27:42'),
(135, 55, 'Cherielyn Feliciano', '', '', 'https://www.facebook.com/rielynfeliciano', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-18 09:29:52', '2025-07-18 09:29:52'),
(136, 55, 'Reagan Gonzales', '', '', 'https://www.facebook.com/reaganbgonzales', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3550960.00, 0.00, 0.00, '', NULL, '2025-07-18 09:34:12', '2025-07-18 09:34:12'),
(137, 55, 'Jelica Aycardo Ajusan', '', '', 'https://www.facebook.com/jelica.ajusan', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Pearl', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-18 09:36:00', '2025-07-18 09:36:00'),
(138, 55, 'Esperanza Tayag', '', '', 'https://www.facebook.com/esperanza.tayag.2025', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3550960.00, 0.00, 0.00, '', NULL, '2025-07-21 08:45:48', '2025-07-21 08:45:48'),
(151, 13, 'Ma Imelda Hernandez', '', '', '', '', NULL, 'Warm', 'Site Tour', 'Facebook Page', NULL, 'Lancaster', 'Thea', 3900000.00, 0.00, 0.00, 'She\'s the representative of her siblings interested to avail a unit.', NULL, '2025-07-23 13:57:34', '2025-07-23 13:57:34'),
(152, 13, 'Roxy Buang', '', '', 'https://www.facebook.com/roxybuang01', '', NULL, 'Warm', 'Site Tour', 'Facebook Page', NULL, 'Monte Royale (Parc Royal)', 'Gabby', 2590500.00, 0.00, 0.00, 'Inquire for Lancaster unit, converted to Masaito needs RFO unit. Will schedule site tripping to check on units.', NULL, '2025-07-23 14:17:40', '2025-07-23 14:17:40'),
(153, 13, 'Maritez Hall', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook live', NULL, 'Lancaster', 'Aira', 8120320.00, 0.00, 0.00, 'Requesting computation for Aira.', NULL, '2025-07-23 14:25:38', '2025-07-23 14:25:38'),
(154, 13, 'Emmanuel Ferrer', '09150610183', '', '', '', NULL, 'Warm', 'Site Tour', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'Re schedule tripping', NULL, '2025-07-23 14:31:01', '2025-07-23 14:31:01'),
(155, 13, 'Antonio Contreras Alfante', '', '', '', '', NULL, 'Warm', 'Site Tour', 'Facebook live', NULL, 'Lancaster', 'Thea', 3900000.00, 0.00, 0.00, 'During facebook live he inquire for Thea unit.', NULL, '2025-07-23 14:36:22', '2025-07-23 14:36:22'),
(156, 13, 'Vic Del Rosario', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Facebook Page', NULL, 'Lancaster', 'Aira, Briana, Chessa', 8120320.00, 0.00, 0.00, 'Still on gong negotiation.', NULL, '2025-07-23 14:39:10', '2025-07-23 14:39:10'),
(157, 13, 'Inna Carla Mucncal Aguilucho', '', '', '', '', NULL, 'Warm', 'Site Tour', 'Facebook Page', NULL, 'Lancaster', 'Thea', 3900000.00, 0.00, 0.00, 'Schedule tripping to check on units.', NULL, '2025-07-24 05:47:51', '2025-07-24 05:47:51'),
(158, 13, 'Quennie Coroz Silava', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'Inquire for Alice unit looking for end or corner lot.', NULL, '2025-07-24 08:05:54', '2025-07-24 08:05:54'),
(159, 13, 'Maria Veronica Ortega', '', '', 'https://www.facebook.com/mariaveronica.ortega.5', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Lancaster', 'Thea', 3900000.00, 0.00, 0.00, 'For next year plan.', NULL, '2025-07-24 08:16:36', '2025-07-24 08:16:36'),
(160, 13, 'NJ Marcelino', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-07-24 08:19:03', '2025-07-24 08:19:03'),
(161, 13, 'Larry Obedoza Ballucanag Jr.', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Lancaster', 'Aira', 8120320.00, 0.00, 0.00, '', NULL, '2025-07-24 08:22:30', '2025-07-24 08:22:30'),
(162, 13, 'Maria imelda Hernandez', '', '', '', '', NULL, 'Warm', 'Site Tour', 'Facebook Page', NULL, 'Lancaster', 'Thea', 3900000.00, 0.00, 0.00, 'Inquiry for her siblings interested to avail a unit.', NULL, '2025-07-24 08:25:19', '2025-07-24 08:25:19'),
(163, 13, 'Joanna Mae Mandap', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Follow up', NULL, 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-07-24 08:36:25', '2025-07-24 08:36:25'),
(164, 13, 'Chen Estrella', '', '', '', '', NULL, 'Warm', 'Site Tour', 'Follow up', NULL, 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-07-24 08:44:05', '2025-07-24 08:44:05'),
(165, 13, 'Kristel  Rol', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Follow up', NULL, 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'Follow up', NULL, '2025-07-24 08:49:19', '2025-07-24 08:49:19'),
(169, 55, 'Maria Moreno', '', '', 'https://www.facebook.com/profile.php?id=100092266576311', '', NULL, 'Cold', 'Inquiry', '', NULL, 'Elisa Homes', 'Pearl', 6998713.00, 0.00, 0.00, '', NULL, '2025-07-29 01:23:02', '2025-07-29 01:23:02'),
(172, 55, 'Joel Patiluna', '', '', 'https://www.facebook.com/joel.patiluna24', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 07:58:02', '2025-07-29 07:58:02'),
(173, 55, 'Sheryl Cruz', '', '', 'https://www.facebook.com/sheryl.cruz.167527', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:01:36', '2025-07-29 08:01:36'),
(174, 55, 'Rina Capili', '', '', 'https://www.facebook.com/rina.capili', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:12:54', '2025-07-29 08:12:54'),
(175, 55, 'Resmar Eisma', '', '', 'https://www.facebook.com/resmarrrr', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:19:57', '2025-07-29 08:19:57'),
(176, 55, 'Bhabes Mendoza-Mariano D-yao', '', '', 'https://www.facebook.com/bhabesmcmmd', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:27:09', '2025-07-29 08:27:09'),
(177, 55, 'Leilanie Namit', '', '', 'https://www.facebook.com/leilanie.namit', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:31:27', '2025-07-29 08:31:27'),
(178, 55, 'Bbert Gilberto', '', '', 'https://www.facebook.com/kookai.shobaki', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:33:48', '2025-07-29 08:33:48'),
(179, 55, 'Maricris R Wagan', '', '', 'https://www.facebook.com/maricris.vila', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:37:56', '2025-07-29 08:37:56'),
(180, 55, 'Ricanny Parcon', '', '', 'https://www.facebook.com/ricanny.parcon', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:41:05', '2025-07-29 08:41:05'),
(181, 55, 'Jo Foxflyt', '', '', 'https://www.facebook.com/Foxflyt123', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:42:16', '2025-07-29 08:42:16'),
(182, 55, 'Fe C Olandag', '', '', 'https://www.facebook.com/feycrem.capundag', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:43:56', '2025-07-29 08:43:56'),
(183, 55, 'Indhy Billones-Jader', '', '', 'https://www.facebook.com/mrsindhyjader', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:45:56', '2025-07-29 08:45:56'),
(184, 55, 'Shayne Valerio', '', '', 'https://www.facebook.com/shayne.valerio.5', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:52:13', '2025-07-29 08:52:13'),
(185, 55, 'Enrica Eunice C. Nery', '', '', 'https://www.facebook.com/itsmeprettyeunice', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:53:19', '2025-07-29 08:53:19'),
(186, 55, 'Joel Balaba', '', '', 'https://www.facebook.com/engr.joelbalaba', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:55:55', '2025-07-29 08:55:55'),
(187, 55, 'FloranGel FLores', '', '', 'https://www.facebook.com/francinegel27', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 08:58:25', '2025-07-29 08:58:25'),
(188, 55, 'Jan Arnold Buenvenida', '', '', 'https://www.facebook.com/jan.arn.buen', '', NULL, 'Cold', 'Presentation Stage', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 09:00:50', '2025-07-29 09:00:50'),
(189, 55, 'Menzo Bulatao', '', '', 'https://www.facebook.com/manuellorenzo.bulatao', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 09:02:37', '2025-07-29 09:02:37'),
(190, 55, 'Rosabel Bernal', '', '', 'https://www.facebook.com/rebernal', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Sapphire', 6998712.00, 0.00, 0.00, '', NULL, '2025-07-29 09:06:18', '2025-07-29 09:06:18'),
(191, 55, 'Rhoms RM', '', '', 'https://www.facebook.com/Smohr.svaram', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 09:08:30', '2025-07-29 09:08:30'),
(192, 55, 'Melquisedec King Estandarte Luna', '', '', 'https://www.facebook.com/mutzky', '', NULL, 'Cold', 'Presentation Stage', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 09:10:16', '2025-07-29 09:10:16'),
(193, 55, 'Novie Rosie', '', '', 'https://www.facebook.com/novieroseel', '', NULL, 'Cold', 'Presentation Stage', 'Facebook Page', NULL, 'Elisa Homes', 'Sapphire', 6998713.00, 0.00, 0.00, '', NULL, '2025-07-29 09:17:39', '2025-07-29 09:17:39'),
(194, 55, 'Unah Bacsa', '09150334080', '', 'https://www.facebook.com/unah.bacsa', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 09:20:51', '2025-07-29 09:20:51'),
(195, 55, 'Mila Rendor', '', '', 'https://www.facebook.com/mila.rendor', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 09:22:30', '2025-07-29 09:22:30'),
(196, 55, 'Lea Peusca Maquiling-Arroyo', '', '', 'https://www.facebook.com/arroyolea', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Elisa Homes', 'Dahlia', 3228145.00, 0.00, 0.00, '', NULL, '2025-07-29 09:28:59', '2025-07-29 09:28:59'),
(197, 24, 'Mary Rose Magsaysay', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', NULL, 'Antipolo', 'Townhomes', 1500000.00, 0.00, 0.00, 'Antipolo Project', NULL, '2025-07-30 00:28:53', '2025-07-30 00:28:53'),
(198, 24, 'Glaiza Florentino-Garlota', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', NULL, 'Kaia Homes', 'Helena', 1500000.00, 0.00, 0.00, '', NULL, '2025-07-30 00:32:30', '2025-07-30 00:32:30'),
(199, 24, 'Ma Del Gaddao', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', NULL, 'Kaia Homes', 'Halie', 797160.00, 0.00, 0.00, '', NULL, '2025-07-30 00:38:13', '2025-07-30 00:38:13'),
(202, 105, 'Juan Santos', '09999990990', 'juansantos@gmail.com', '', '', NULL, 'Warm', 'Closed Deal', 'KKK', NULL, 'Lancaster', 'Aira', 8000000.00, 0.00, 0.00, 'Done with presentation stage via zoom', NULL, '2025-08-04 07:11:05', '2025-08-04 07:13:54'),
(203, 15, 'Shan Olaguer', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Groups', NULL, 'Monte Royale', 'Gabby', 2519850.00, 0.00, 0.00, 'I\'ve already provided the information about Pag-IBIG financing. I’ll follow up with him in 1–2 days if I don’t receive a response.', NULL, '2025-08-05 16:03:48', '2025-08-05 16:06:21'),
(204, 15, 'Mariajezza Mendoza', '', '', 'https://www.facebook.com/mariajezza14', '', NULL, 'Cold', 'Inquiry', 'Facebook Groups', NULL, 'Monte Royale', 'Gabby', 2000000.00, 0.00, 0.00, 'Already gave the Details of Gabby Unit.', NULL, '2025-08-06 07:22:46', '2025-08-06 07:22:46'),
(205, 32, 'TAMIO VINCE', '', 'vincetamio52@gmail.com', '', '', NULL, 'Hot', 'Housing Loan Application', 'Organic Posting', 'OFW', 'Southdale Villas', 'DANNA', 1880000.00, 0.00, 0.00, 'on going process kay pag ibig', NULL, '2025-08-14 11:22:51', '2025-12-25 00:58:46'),
(206, 32, 'VALDEAVILLA PRINCESS ANN', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Organic Posting', NULL, 'Park Infina (Parc Royal)', 'Era', 6951560.00, 0.00, 0.00, 'FOR TRIPPING', NULL, '2025-08-14 11:26:45', '2025-08-14 11:26:45'),
(207, 32, 'LAYAOEN  MARK', '', '', '', '', NULL, 'Warm', 'Site Tour', 'Organic Posting', NULL, 'COMELEC Village (Parc Royal)', 'Chesca', 2526000.00, 0.00, 0.00, 'ALREADY TRIPPING ,WAITING FOR FINAL DISCISSION', NULL, '2025-08-14 11:29:46', '2025-08-14 11:29:46'),
(208, 32, 'NECIO ARNOLD', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Organic Posting', NULL, 'Berde Ville', 'LOT ONLY', 2800000.00, 0.00, 0.00, '', NULL, '2025-08-14 11:37:16', '2025-08-14 11:37:16'),
(209, 32, 'SERUT CECILLE', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', NULL, 'COMELEC Village (Parc Royal)', 'Chesca', 2520000.00, 0.00, 0.00, 'NAKA PAG 1ST DOWNPAYMENT NASA COMELEC, NAGCANCELLED SIYA SA LANCASTER', NULL, '2025-08-14 11:38:59', '2025-08-14 11:54:01'),
(210, 32, 'LEBANIA ROAN JANE', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Organic Posting', NULL, 'Sapphire Residences', 'SAPPHIRE', 2514000.00, 0.00, 0.00, 'NAKA PAG 1ST DP NA', NULL, '2025-08-14 11:41:43', '2025-08-14 11:42:18'),
(211, 32, 'PARAL NASH', '', '', '', '', NULL, 'Cold', 'Site Tour', 'Facebook Page', NULL, 'Pleasantfields', 'Kennedy', 3138502.19, 0.00, 0.00, 'FOR TRIPPING ON SUNDAY ....07/18', NULL, '2025-08-14 12:09:17', '2025-08-14 12:09:17'),
(212, 8, 'Ronald Tanagon', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Youtube Marketing', NULL, 'Vista Rosa', 'Aurora', 4400000.00, 0.00, 0.00, '-  Details sent to the client, checking for other options nearby. \r\n- Follow up next week \r\n- OFW from Japan\r\n- possible in house financing\r\n- previous client in Terraverde\r\n- possible for investment', NULL, '2025-08-17 07:02:10', '2025-08-18 06:35:32'),
(213, 8, 'Erna Cas', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'KKK', NULL, 'Lancaster', 'Alice', 2880000.00, 0.00, 0.00, '* Looking for rent to own or RFO \r\n* combined income / co borrower husband \r\n* GMI is 60k combined \r\n* BPO', NULL, '2025-08-18 06:34:11', '2025-08-18 06:34:11'),
(214, 20, 'Mark', '09128383829', '', '', '', NULL, 'Cold', 'Downpayment Stage', 'KKK', NULL, 'Minami Residence', 'Hana', 2100000.00, 0.00, 0.00, 'Assist this ASAP', NULL, '2025-09-01 06:34:24', '2025-09-01 06:40:12'),
(215, 20, 'Danny boy', '09669633188', 'irwindgonzales6@gmail.com', '', '', NULL, 'Cold', 'Inquiry', 'KKK', NULL, 'Lancaster', 'Briana', 8000000.00, 0.00, 0.00, '', NULL, '2025-09-01 07:14:57', '2025-09-01 07:14:57'),
(216, 8, 'Jerome Padolina', '+639215700895', 'jerome.padolina1998@gmail.com', '', '', NULL, 'Hot', 'Lost', 'KKK', 'Locally/Internationally Employed', 'Brookkstone Park', 'Audrina', 4999761.20, 0.00, 0.00, '9/16\r\n- co borrower\'s requirement', NULL, '2025-09-01 13:30:57', '2026-02-16 14:12:08'),
(217, 8, 'Joel Libacao', '', '', '', '', NULL, 'Hot', 'Presentation Stage', 'KKK', NULL, 'Southdale Villas', 'Aurora', 1800000.00, 0.00, 0.00, '- Joel\'s officemate \r\n- 27 yrs old \r\n- Pag-Ibig \r\n- will complete requirements', NULL, '2025-09-01 21:45:41', '2025-09-01 21:45:41'),
(218, 30, 'Marvin Mercado', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Organic Posting', NULL, 'Locally/Internationally Employed', 'Grandview Heights 2', 1400000.00, 0.00, 0.00, '1400000', NULL, '2025-09-04 09:35:28', '2025-09-04 09:37:20'),
(219, 30, 'Annie Bondoc', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Organic Posting', NULL, 'Locally/Internationally Employed', 'Masaito Homes Trece', 0.00, 0.00, 0.00, '2211000', NULL, '2025-09-04 09:41:20', '2025-09-04 09:41:20'),
(220, 30, 'Esperanza Destura', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Organic Posting', NULL, 'Locally/Internationally Employed', 'Whistler Village Phase 2', 2276350.00, 0.00, 0.00, 'Inquired on my organic posting', NULL, '2025-09-04 09:47:58', '2025-09-04 09:54:26'),
(221, 30, 'Lorna Torres', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Organic Posting', NULL, 'Locally/Internationally Employed', 'Whistler Village Phase 2', 0.00, 0.00, 0.00, '2276350', NULL, '2025-09-04 09:53:08', '2025-09-04 09:53:08'),
(222, 32, 'TRINIDAD VICTORIA HARRISON', '', '', '', '', NULL, 'Hot', 'Lost', 'Referral', 'OFW', 'OFW', 'COMELEC Village (Parc Royal)', 3418800.00, 0.00, 0.00, 'Buyers decide to cancelled this account because according to them earthquakes happen frequently here in the Phils.', NULL, '2025-09-06 11:25:13', '2025-10-21 06:39:06'),
(223, 97, 'Mam Calaod', '', '', '', '', NULL, 'Cold', 'Inquiry', 'KKK', NULL, 'Locally/Internationally Employed', 'San Pedro area', 0.00, 0.00, 0.00, '1800000', NULL, '2025-09-08 14:46:13', '2025-09-08 14:46:13'),
(224, 8, 'Eis Sen', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Organic Posting', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2538000.00, 0.00, 0.00, '', NULL, '2025-09-09 00:34:16', '2025-09-25 18:37:44'),
(225, 13, 'Maricel Beltran', '09561270012', '', '', '', NULL, 'Hot', 'Lost', 'Facebook Ads', 'Locally/Internationally Employed', 'Locally/Internationally Employed', 'Lancaster', 3101280.00, 0.00, 0.00, 'Unit for cancellation due to non-payment.', NULL, '2025-09-10 23:24:20', '2025-09-18 06:18:26'),
(226, 13, 'Sebastian Caole Crisostomo', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Facebook Ads', NULL, 'Locally/Internationally Employed', 'Lancaster', 0.00, 0.00, 0.00, '3754800', NULL, '2025-09-10 23:30:59', '2025-09-10 23:30:59'),
(227, 13, 'Elena Cordoba Palisoc', '', '', '', '', NULL, 'Hot', 'Housing Loan Application', 'Organic Posting', 'Locally/Internationally Employed', 'Locally/Internationally Employed', 'Merrydale St. Joseph', 819840.00, 0.00, 0.00, 'For loan processing and approval', NULL, '2025-09-10 23:36:55', '2026-01-19 06:26:59'),
(228, 13, 'Sri Melati Binte Kassim', '', '', '', '', NULL, 'Hot', 'Lost', 'Facebook Ads', NULL, 'OFW', 'Westwind at Lancaster City', 3.00, 0.00, 0.00, '6316095', NULL, '2025-09-10 23:43:39', '2025-09-11 05:42:07'),
(229, 13, 'Marie Joy Collado', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', NULL, 'Locally/Internationally Employed', 'Beverly Homes', 0.00, 0.00, 0.00, '770500', NULL, '2025-09-11 01:26:45', '2025-09-11 01:26:45'),
(230, 13, 'Norhana M. Hadji Ali', '', '', '', '', NULL, 'Hot', 'Lost', 'Facebook Ads', NULL, 'Locally/Internationally Employed', 'Lancaster', 0.00, 0.00, 0.00, '2800000', NULL, '2025-09-11 05:36:19', '2025-09-11 05:36:19'),
(231, 13, 'Yves Silvestre', '', '', '', '', NULL, 'Warm', 'Site Tour', 'Chat messaging', NULL, 'Locally/Internationally Employed', 'Lancaster', 0.00, 0.00, 0.00, '2800000', NULL, '2025-09-11 05:39:02', '2025-09-11 05:39:02'),
(232, 13, 'Bernadette', '09561270012', '', '', '', NULL, 'Warm', 'Site Tour', 'Facebook Ads', NULL, 'Locally/Internationally Employed', 'Lancaster', 0.00, 0.00, 0.00, '3900000', NULL, '2025-09-11 05:41:05', '2025-09-11 05:41:05'),
(233, 8, 'Benjie Libacao', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'KKK', 'Locally/Internationally Employed', 'Locally/Internationally Employed', 'Masaito Homes Trece', 1000000.00, 0.00, 0.00, '', NULL, '2025-09-12 12:18:23', '2025-09-25 18:36:51'),
(234, 13, 'Emma Mosquito', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Locally/Internationally Employed', 'Lancaster', 0.00, 0.00, 0.00, '2800000', NULL, '2025-09-12 13:56:15', '2025-09-12 13:56:15'),
(235, 97, 'Florida silvestre', '09991557569', '', '', '', NULL, 'Warm', 'Negotiation', 'Facebook Page', NULL, 'Courtyard Town homes', 'Elena Town homes', 2115666.50, 0.00, 0.00, 'A warm mother of of a new couple who was positively charged in negotiate the project 2 newly working couple combine salary around 11k -12k mos. Amortation thru pagibig I offer (Trece)Bernice single 27.2x60 provision of bedroom T&B and a carport', NULL, '2025-09-13 00:40:47', '2025-09-13 07:02:09'),
(236, 15, 'Aaron James Abiero Culagbang', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Manning', 'Self employed', 'Lancaster', 'Alice', 2965000.00, 0.00, 0.00, '', NULL, '2025-09-13 09:21:21', '2025-09-16 06:35:22'),
(237, 8, 'Carla Jane Rumaga', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', NULL, 'Self employed', 'Lancaster', 2740000.00, 0.00, 0.00, 'Alice \r\nwith bf foreigner \r\ngarden', NULL, '2025-09-13 14:51:38', '2025-09-14 12:25:54'),
(238, 97, 'Edel Aromañep Perez', '', '', 'www.fachttps://www.facebook.com/edd.aromanep.zerep', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', NULL, 'Self employed', 'Lancaster', 0.00, 0.00, 0.00, '2700000', NULL, '2025-09-14 02:32:16', '2025-09-14 02:32:16'),
(239, 97, 'Maria Eva Joyce Manzon- Gabrinao', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', NULL, 'Self employed', 'Lancaster', 0.00, 0.00, 0.00, '2858000', NULL, '2025-09-15 01:56:36', '2025-09-15 01:56:36'),
(240, 3, 'Reva Barrientos', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-09-16 06:52:14', '2025-09-16 06:52:14'),
(241, 3, 'Jhon Nallon', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-09-16 06:54:49', '2025-09-16 06:54:49'),
(242, 3, 'Joyce Mende', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-09-16 06:57:40', '2025-09-16 06:57:40'),
(243, 3, 'Yvette Ang', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-09-16 06:59:08', '2025-09-16 06:59:08'),
(244, 3, 'Azella Zargoza', '09171577649', 'azellavarquez@gmail.com', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-09-16 07:03:07', '2025-09-16 07:03:07'),
(245, 3, 'Cristina Guevarra', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Facebook Page', 'OFW', 'Lancaster', 'Chessa', 6148000.00, 0.00, 0.00, '', NULL, '2025-09-16 07:06:23', '2025-09-16 07:06:23'),
(246, 3, 'Ruzzel Trinidad', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Groups', 'Locally/Internationally Employed', 'COMELEC Village (Parc Royal)', 'Chesca', 2700000.00, 0.00, 0.00, '', NULL, '2025-09-16 07:09:07', '2025-09-16 07:09:07'),
(247, 3, 'Jean Chavez', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', 'Locally/Internationally Employed', 'COMELEC Village (Parc Royal)', 'Chesca', 2700000.00, 0.00, 0.00, '', NULL, '2025-09-16 07:12:42', '2025-09-16 07:12:42'),
(248, 3, 'Kriza Genelsa', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', 'Locally/Internationally Employed', 'COMELEC Village (Parc Royal)', 'Chesca', 2700000.00, 0.00, 0.00, '', NULL, '2025-09-16 07:15:09', '2025-09-16 07:15:09'),
(249, 3, 'JOVIE REDAP', '09279818014', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Follow up', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'Inquiry from Pleasantfields page', NULL, '2025-09-18 07:02:17', '2025-09-18 07:02:17'),
(250, 97, 'Remod Lepat', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Inquiry but slight busy and less time to handle on owning a house les budget', NULL, '2025-09-18 12:44:35', '2025-09-18 12:44:35'),
(251, 97, 'Arizielle Layne', '', '', 'https://www.facebook.com/Arizielley06', '', NULL, 'Hot', 'Site Tour', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Thea', 3900000.00, 0.00, 0.00, 'Budget and time with 3-4 m max', NULL, '2025-09-18 12:46:55', '2025-09-25 08:52:29'),
(252, 97, 'Jessa Belwar', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Less Budget and no idea asking Po sa basic requirements how to get real state', NULL, '2025-09-18 12:49:51', '2025-09-18 12:49:51'),
(253, 97, 'Emmanuel Clariño', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Self employed', 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Details send need follow up', NULL, '2025-09-19 07:15:24', '2025-09-19 07:15:24'),
(254, 97, 'Yhamordp Egot Pertimos', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', 'Self employed', 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Nagbigay ako Ng days kailan Ang tripping kasi nag Tanong na siya', NULL, '2025-09-22 08:28:48', '2025-09-22 08:28:48'),
(255, 97, 'Gladys Farales Sia', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', 'Self employed', 'Southdale Villas', 'Town homes', 3900000.00, 0.00, 0.00, 'Prefer RFo', NULL, '2025-09-22 08:32:25', '2025-09-22 08:38:42'),
(256, 97, 'Cianne', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Thea', 3900000.00, 0.00, 0.00, 'Gusto niya  RFO Thea ay bank .gusto niya pag ibig monthly payment.Ayaw Ng Alice then nag block napo sa fb page', NULL, '2025-09-22 08:36:03', '2025-09-26 20:18:37'),
(257, 12, 'ROMLY VICENTE LEODIA', '', '', '', '', NULL, 'Warm', 'Negotiation', 'KKK', 'Locally/Internationally Employed', 'Pasinaya Homes', 'N', 814000.00, 0.00, 0.00, '', NULL, '2025-09-23 08:46:27', '2025-09-23 09:10:57'),
(258, 20, 'Sharhan Allie Asdali', '', '', 'https://www.facebook.com/share/16FtioyWjt/', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Self employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, 'To follow up', NULL, '2025-09-23 08:47:24', '2025-09-23 08:51:57'),
(259, 4, 'jennylyn cubera', '', '', '', '', NULL, 'Hot', 'Presentation Stage', 'Facebook Ads', 'OFW', 'Liora Homes', 'Amora', 1800000.00, 0.00, 0.00, '', NULL, '2025-09-23 08:47:43', '2025-09-27 06:31:28'),
(260, 32, 'Analyn Yanson', '', '', '', '', NULL, 'Hot', 'Site Tour', 'Organic Posting', 'OFW', 'Lancaster', 'Thea', 4274000.00, 0.00, 0.00, '', NULL, '2025-09-23 08:47:43', '2025-09-25 02:29:50'),
(261, 15, 'Navora Josefine', '09194620030', '', 'https://www.facebook.com/josie.navora', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Aira', 8000000.00, 0.00, 0.00, 'looking for single attached', NULL, '2025-09-23 08:48:29', '2025-09-23 08:49:42'),
(262, 94, 'Marlan Manlolo', '', '', 'https://www.facebook.com/share/1Awk3MQVT4/', '', NULL, 'Hot', 'Negotiation', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, 'For requirements', NULL, '2025-09-23 08:53:09', '2025-09-23 08:53:09'),
(263, 3, 'Nilda Berganio Rodriguez', '09635466570', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'Sister of the principal buyer', NULL, '2025-09-23 08:53:59', '2025-09-23 09:13:57'),
(264, 15, 'Juan Dela Cruz', '09192812818', 'juandelacruz@gmail.com', 'https://www.facebook.com/mariajezza14', '', NULL, 'Warm', 'Downpayment Stage', 'Facebook Groups', 'Locally/Internationally Employed', 'Kaia Homes', 'Helena', 2000000.00, 0.00, 0.00, 'From Lancaster to Kaia Homes', NULL, '2025-09-23 08:59:18', '2025-09-23 09:27:41'),
(265, 20, 'Janissen Lim', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Self employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, 'To follow up', NULL, '2025-09-23 09:03:39', '2025-09-23 09:03:39'),
(266, 20, 'Ronzie Habiag Pinede', '', '', '', '', NULL, 'Cold', 'Downpayment Stage', 'Facebook Page', 'Self employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, 'To follow up', NULL, '2025-09-23 09:06:41', '2025-09-23 09:08:42'),
(267, 94, 'Angelo Guilas', '', '', '', '', NULL, 'Warm', 'Negotiation', 'KKK', 'Locally/Internationally Employed', 'Masaito Homes Trece', 'Gabby', 2590500.00, 0.00, 0.00, 'For requirements', NULL, '2025-09-23 09:18:03', '2025-09-23 09:18:03'),
(268, 94, 'Sandrey Deluria', '', '', '', '', NULL, 'Hot', 'Site Tour', 'KKK', 'Locally/Internationally Employed', 'Pagsibol Village', 'Duplex', 1800000.00, 0.00, 0.00, 'For requirements', NULL, '2025-09-23 09:30:48', '2025-12-30 12:02:00'),
(269, 94, 'Catherine Martin', '', '', '', '', NULL, 'Hot', 'Negotiation', 'KKK', 'Locally/Internationally Employed', 'Southdale Villas', 'Danna', 1800000.00, 0.00, 0.00, '', NULL, '2025-09-23 09:34:23', '2025-09-23 09:34:23'),
(270, 94, 'Tricia David', '', '', '', '', NULL, 'Cold', 'Presentation Stage', 'Chat messaging', 'Locally/Internationally Employed', 'Masaito Homes Trece', 'Chesca', 2900000.00, 0.00, 0.00, '', NULL, '2025-09-23 09:40:52', '2025-09-23 09:40:52'),
(271, 4, 'Shiela Mae Aballe', '09930668161', 'aballeshielamae30@gmail.com', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'OFW', 'Four J/ Berde ville 1', 'Lot Only', 1000000.00, 0.00, 0.00, '', NULL, '2025-09-23 22:45:09', '2025-09-23 22:45:09'),
(272, 4, 'Clarence Danielle Serdon', '09996916107', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'Self employed', 'Berde Ville', 'Lot Only', 796500.00, 0.00, 0.00, '', NULL, '2025-09-23 22:48:57', '2025-09-23 22:48:57'),
(273, 4, 'Algen Boneo', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'Locally/Internationally Employed', 'Neuville Townhomes', 'Astrid', 3780000.00, 0.00, 0.00, '', NULL, '2025-09-23 23:02:29', '2025-09-23 23:02:29'),
(274, 4, 'Shiela Mae Lim Aballe', '', '', '', '', NULL, 'Hot', 'Closed Deal', 'KKK', 'OFW', 'Pacific Ace Village', 'Lycaste', 5118000.00, 0.00, 0.00, 'fullypaid', NULL, '2025-09-23 23:37:48', '2025-09-27 06:36:05'),
(275, 20, 'Trisha Mae Fajardo', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Self employed', 'Micara Estates', 'Portia', 2461920.00, 0.00, 0.00, 'Inquiry🫶🏡🍳', NULL, '2025-09-24 01:29:05', '2025-09-24 01:29:05'),
(276, 20, 'Joseph Gardiana', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Self employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'Inquiry 🫶🍳', NULL, '2025-09-24 01:32:19', '2025-09-24 01:32:19'),
(277, 7, 'Rai Abanes Infante', '', '', 'https://www.facebook.com/rai.abanes.9', '', NULL, 'Cold', 'Lost', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838800.00, 0.00, 0.00, 'blocked', NULL, '2025-09-24 01:48:17', '2025-09-24 01:48:17'),
(278, 7, 'Eva Nogara Batiao', '09633237752', '', 'https://www.facebook.com/eva.batiaonogara.5', '', NULL, 'Hot', 'Site Tour', 'Facebook Ads', 'Locally/Internationally Employed', 'Kaia Homes', 'Helena', 1595493.00, 0.00, 0.00, 'tatawagan pag free time nya nasa work lang daw\r\n\r\nas of sep 24 1pm: interested sa kaia homes helena model, for tripping this weekend', NULL, '2025-09-24 01:54:44', '2025-09-24 06:45:29'),
(279, 14, 'Grace Acain Ripdos', '09567424249', 'acainemma16@gmail.com', '', '', NULL, 'Hot', 'Closed Deal', 'TikTok ads', 'OFW', 'Kaia Homes', 'Helena', 1800000.00, 0.00, 0.00, 'This account was cancelled last July 15.2025', NULL, '2025-09-24 05:11:36', '2025-09-24 09:17:00');
INSERT INTO `leads` (`id`, `user_id`, `client_name`, `phone`, `email`, `facebook`, `linkedin`, `address`, `temperature`, `status`, `source`, `lead_classification`, `developer`, `project_model`, `price`, `commission_rate`, `expected_commission`, `remarks`, `follow_up_date`, `created_at`, `updated_at`) VALUES
(280, 14, 'Mai Mofan', '09777678179', '', '', '', NULL, 'Warm', 'Inquiry', 'TikTok ads', 'Locally/Internationally Employed', 'Monte Royale (Parc Royal)', 'Gabby', 2800000.00, 0.00, 0.00, 'Prerfer Pagibig financing, kaso mataas po DP hindi daw po kaya', NULL, '2025-09-24 06:09:07', '2025-09-24 06:09:07'),
(281, 14, 'Jc Campillanos', '09814661993', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Locally/Internationally Employed', 'Kaia Homes', 'Helena', 1800000.00, 0.00, 0.00, 'Interesado sila, kaso malaki daw po equity, tatawag balang daw po kapag ready na/', NULL, '2025-09-24 06:15:51', '2025-09-24 06:15:51'),
(282, 14, 'Leila Molano', '09283809644', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Locally/Internationally Employed', 'Sta Rosa Laguna', '2 storey house', 3000000.00, 0.00, 0.00, 'no available project', NULL, '2025-09-24 06:21:49', '2025-09-24 06:21:49'),
(283, 7, 'Rowena Malabed Dalumpines', '', '', 'https://www.facebook.com/rddelrosario', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, 'follow up', NULL, '2025-09-24 06:31:52', '2025-09-24 06:33:29'),
(284, 7, 'Ian Tabor', '', '', 'https://www.facebook.com/ian.tabor.2024', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, 'follow up', NULL, '2025-09-24 06:34:31', '2025-09-24 06:35:05'),
(285, 7, 'Sofronio Villacote Jr.', '', '', 'https://www.facebook.com/sofroniovillacotejr', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, 'follow up', NULL, '2025-09-24 06:36:05', '2025-09-24 06:36:52'),
(286, 7, 'Yhexel Redito', '', '', 'https://www.facebook.com/joi.redito', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, '\"Where is your location?\"', NULL, '2025-09-24 06:38:33', '2025-09-24 06:39:19'),
(287, 7, 'Efren Seco Reyes', '', '', 'https://www.facebook.com/boszef', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, 'details please', NULL, '2025-09-24 06:40:20', '2025-09-24 06:40:52'),
(288, 7, 'Ana Pabiona', '', '', 'https://www.facebook.com/ana.pabiona.7', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, 'tcp pls', NULL, '2025-09-24 06:41:45', '2025-09-24 06:42:26'),
(289, 14, 'Aida dado', '09368107942', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Locally/Internationally Employed', 'Pleasantfields', 'Kennedy', 3200000.00, 0.00, 0.00, '', NULL, '2025-09-24 06:47:13', '2025-09-24 06:47:13'),
(290, 14, 'Daine Deguzman', '09760047317', '', '', '', NULL, 'Cold', 'Inquiry', 'TikTok ads', 'Self employed', 'Lancaster', 'AIRA', 7900000.00, 0.00, 0.00, 'Self employed, no complete documents', NULL, '2025-09-24 06:51:39', '2025-09-24 06:51:39'),
(291, 7, 'Liz Solenn', '', '', 'https://www.facebook.com/liza.soliverespangilinan', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, 'Where is your location?', NULL, '2025-09-24 06:54:42', '2025-09-24 06:55:39'),
(292, 14, 'Josephine Cansino', '09261000613', '', '', '', NULL, 'Warm', 'Negotiation', 'TikTok ads', 'Self employed', 'Sapphire Residences', 'town house', 2400000.00, 0.00, 0.00, 'Capable to pay, but no complete docs', NULL, '2025-09-24 06:55:32', '2025-09-24 06:55:32'),
(293, 14, 'Teacher Jenny', '09073186259', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2600000.00, 0.00, 0.00, 'Preferred PAGIBIG financing para daw magamit pagibig nya, kaso taas daw po DP di kakayanin', NULL, '2025-09-24 07:00:00', '2025-09-24 07:00:00'),
(294, 14, 'Cristy', '09171841671', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Locally/Internationally Employed', 'Liora Homes', 'Amora', 2400000.00, 0.00, 0.00, 'Marital problem', NULL, '2025-09-24 07:03:23', '2025-09-24 07:03:23'),
(295, 14, 'haydee Cuerdo', '09754575204', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Locally/Internationally Employed', 'Kaia Homes', 'Halie', 800000.00, 0.00, 0.00, 'maliit ang house unit', NULL, '2025-09-24 07:06:39', '2025-09-24 07:06:39'),
(296, 14, 'Angie santillana', '09754205031', '', '', '', NULL, 'Cold', 'Inquiry', 'TikTok ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2600000.00, 0.00, 0.00, 'after mag inquire seen mode', NULL, '2025-09-24 07:10:21', '2025-09-24 07:10:21'),
(297, 14, 'Nilda', '09625102616', '', '', '', NULL, 'Cold', 'Inquiry', 'TikTok ads', 'Locally/Internationally Employed', 'Kaia Homes', 'Halie', 800000.00, 0.00, 0.00, 'after mag usap sa phone seen mode', NULL, '2025-09-24 07:12:48', '2025-09-24 07:12:48'),
(298, 14, 'Beth', '09772062502', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Self employed', 'Kaia Homes', 'Halie', 800000.00, 0.00, 0.00, 'Carinderia owner. capable to pay but no DOCS', NULL, '2025-09-24 07:16:32', '2025-09-24 07:16:32'),
(299, 14, 'Janning Padua', '09365923347', '', '', '', NULL, 'Warm', 'Negotiation', 'TikTok ads', 'OFW', 'Liora Homes', 'Amora', 2400000.00, 0.00, 0.00, 'eager to avail.  kaso Marital problem', NULL, '2025-09-24 07:20:01', '2025-09-24 07:20:01'),
(300, 14, 'Hassan', '09931770975', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Locally/Internationally Employed', 'Kaia Homes', 'Halie', 800000.00, 0.00, 0.00, 'need no equity, lipat agad', NULL, '2025-09-24 07:22:26', '2025-09-24 07:22:26'),
(301, 14, 'alex', '09155418387', '', '', '', NULL, 'Cold', 'Inquiry', 'TikTok ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2600000.00, 0.00, 0.00, 'just inquire, not yet ready', NULL, '2025-09-24 07:28:31', '2025-09-24 07:28:31'),
(302, 20, 'Cheryll Rose Gari', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Self employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'For Inquiry✅❤️', NULL, '2025-09-24 07:29:53', '2025-09-24 07:29:53'),
(303, 14, 'Shirley Duran', '09956532469', '', '', '', NULL, 'Cold', 'Inquiry', 'TikTok ads', 'Locally/Internationally Employed', 'Pleasantfields', 'Kennedy', 3200000.00, 0.00, 0.00, 'short sa budget, mataas daw po price', NULL, '2025-09-24 07:33:12', '2025-09-24 07:33:12'),
(304, 14, 'Maribel', '09919041440', '', '', '', NULL, 'Warm', 'Negotiation', 'TikTok ads', 'OFW', 'Lancaster', 'Alice', 2600000.00, 0.00, 0.00, 'scheduling booking. due to bad weather for 1 week di natuloy. nakaalis na si client. pero nag fofollowup padin till now', NULL, '2025-09-24 07:37:13', '2025-09-24 07:37:13'),
(305, 14, 'Rowena Berceli', '09564339254', '', '', '', NULL, 'Warm', 'Site Tour', 'TikTok ads', 'Locally/Internationally Employed', 'bataan', 'bataan project', 1600000.00, 0.00, 0.00, 'dipa kaya pang DP ipon daw muna', NULL, '2025-09-24 07:40:36', '2025-09-24 07:40:36'),
(306, 14, 'Rex Picardal', '', '', '', '', NULL, 'Hot', 'Negotiation', 'TikTok ads', 'Locally/Internationally Employed', 'house pasok sa 20k', 'laguna', 1000000.00, 0.00, 0.00, 'still waiting for co barrower', NULL, '2025-09-24 08:03:43', '2025-09-24 08:03:43'),
(307, 14, 'Melinda Refugio', '09054841373', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Locally/Internationally Employed', 'Kaia Homes', 'Helena', 1800000.00, 0.00, 0.00, 'capable to pay, marital problem', NULL, '2025-09-24 08:07:01', '2025-09-24 08:07:01'),
(308, 14, 'Crisanto Tabanao', '09665373281', '', '', '', NULL, 'Warm', 'Presentation Stage', 'TikTok ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2600000.00, 0.00, 0.00, 'waiting for descission', NULL, '2025-09-24 08:12:40', '2025-09-24 08:12:40'),
(309, 14, 'boss dre', '09555131617', '', '', '', NULL, 'Hot', 'Negotiation', 'TikTok ads', 'Locally/Internationally Employed', 'Kaia Homes', 'Halie', 800000.00, 0.00, 0.00, 'bumalik na client to confirm na waiting lang sila a little time', NULL, '2025-09-24 08:50:32', '2025-09-24 08:50:32'),
(310, 14, 'Raquel Nuqui', '09380608794', 'raqueinuqui@gmail.com', '', '', NULL, 'Hot', 'Closed Deal', 'TikTok ads', 'Locally/Internationally Employed', 'Sapphire Residences', 'town house', 2600000.00, 0.00, 0.00, 'closed may21,2025\r\nfirst DP was june 21', NULL, '2025-09-24 09:11:41', '2025-09-24 09:11:41'),
(311, 14, 'Mary Angenith M Maagad', '09918658627', 'angenith@gmail.com', '', '', NULL, 'Hot', 'Closed Deal', 'Facebook Ads', 'Locally/Internationally Employed', 'Kaia Homes', 'Halie', 807000.00, 0.00, 0.00, 'closed deal last Sept. 3,2025', NULL, '2025-09-24 09:31:26', '2025-09-24 09:31:26'),
(312, 97, 'Samson Lozano', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'He said struggling budget', NULL, '2025-09-24 12:57:08', '2025-09-24 12:57:08'),
(313, 97, 'Lyka Faith Symbol Villalon', '', '', '', '', NULL, 'Hot', 'Presentation Stage', 'Facebook Ads', 'OFW', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Asking Alice and pag ibig financing send requirements checklist already', NULL, '2025-09-24 12:58:37', '2025-09-28 18:23:35'),
(314, 32, 'Trinidad Victoria Harrison', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Referral', 'OFW', 'Berde Ville', 'Lot Only', 2120000.00, 0.00, 0.00, '2 lots sold', NULL, '2025-09-25 02:40:21', '2025-09-25 03:04:12'),
(318, 30, 'Lester RC', '', '', 'https://www.facebook.com/LesterrcVA', '', NULL, 'Warm', 'Inquiry', 'Organic Posting', 'Locally/Internationally Employed', 'Minami Residence', 'Hana', 4647400.00, 0.00, 0.00, 'Inquired from organic posting.', NULL, '2025-09-25 08:35:14', '2025-09-25 08:38:39'),
(319, 18, 'Sean Herald dela cruz', '09082992660', '', '', '', NULL, 'Hot', 'Closed Deal', 'KKK', 'Locally/Internationally Employed', 'Antipolo Heights', 'Lot', 1000000.00, 0.00, 0.00, 'Monthy amort', NULL, '2025-09-25 08:37:02', '2025-09-25 08:37:02'),
(320, 97, 'Jeffrey Hingpit', '', '', 'https://www.facebook.com/jeffrey.hingpit', '', NULL, 'Cold', 'Inquiry', 'KKK', '', 'Antipolo Heights', 'Antipolo Heights Model A', 1000000.00, 0.00, 0.00, 'after 2 years follow up due to child high paying school', NULL, '2025-09-25 08:40:36', '2025-09-25 08:40:36'),
(321, 22, 'sarah jean lopez', '09329757344', 'sarahjeanlopez07@gmail.com', '', '', NULL, 'Warm', 'Site Tour', 'KKK', 'Self employed', 'Berde Ville', 'lot only', 1000.00, 0.00, 0.00, 'view concerns', NULL, '2025-09-25 08:40:57', '2025-09-25 08:40:57'),
(322, 17, 'Jon Marty Dioso', '09295068211', 'bossJM23@gmail.com', '', '', NULL, 'Hot', 'Requirement Stage', 'KKK', 'OFW', 'Neuville Townhomes', 'Astrid', 6000000.00, 0.00, 0.00, '', NULL, '2025-09-25 08:41:04', '2025-09-26 20:34:14'),
(323, 35, 'Lee Marvin Ibasco', '', 'lmibasco@yahoo.com', '', '', NULL, 'Cold', 'Inquiry', '', 'OFW', 'Golden Vista', 'phase 2', 700000.00, 0.00, 0.00, 'for callback', NULL, '2025-09-25 08:41:21', '2025-09-25 08:41:21'),
(325, 18, 'Maricris sablayan', '', '', '', '', NULL, 'Hot', 'Closed Deal', 'KKK', 'Locally/Internationally Employed', 'Antipolo Heights', 'Lot only', 1200000.00, 0.00, 0.00, 'Monthly amort', NULL, '2025-09-25 08:44:43', '2026-01-07 05:10:09'),
(326, 35, 'Rommel Laderas', '', 'romladeras@gmail.com', '', '', NULL, 'Cold', 'Inquiry', 'Property Listing', 'OFW', 'Golden Montana', 'p', 1000000.00, 0.00, 0.00, '', NULL, '2025-09-25 08:47:32', '2025-09-25 08:47:32'),
(327, 17, 'Carl jousha Lagrimas comighod', '09202474501', '', '', '', NULL, 'Warm', 'Site Tour', 'KKK', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000000.00, 0.00, 0.00, '', NULL, '2025-09-25 08:47:32', '2025-09-25 08:50:42'),
(328, 17, 'Jennifer lagrimas', '', '', '', '', NULL, 'Warm', 'Inquiry', 'KKK', 'OFW', 'Neuville Townhomes', 'Astrid', 3800000.00, 0.00, 0.00, '', NULL, '2025-09-25 08:54:51', '2025-09-25 08:54:51'),
(330, 8, 'Axl Jeff Garcia', '09359182851', 'granzelle11@gmail.com', '', '', NULL, 'Hot', 'Negotiation', 'Referral', 'OFW', 'Sapphire Residences', 'Sapphire', 2514000.00, 0.00, 0.00, '- waiting for reopen unit t Blocks 5,6 or 7 ( facing sunrise ) \r\n- AIF wifie ( Ranzelle Garcia )\r\n- OFW from Cambodia\r\n- divert to Richdalle in gen,Tri \r\n- 3.4M - Vatable', NULL, '2025-09-26 05:25:16', '2025-10-02 23:24:27'),
(331, 12, 'GABCYROSE B. DEL SOCORRO', '09263939124', 'gabcyrose@gmail.com', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'Locally/Internationally Employed', 'Kaia Homes', 'Helena', 1815470.00, 0.00, 0.00, '', NULL, '2025-09-26 06:11:01', '2025-09-26 06:11:01'),
(332, 7, 'Alvin Alingasa', '', '', 'https://www.facebook.com/alvin.alingasa', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, 'Where is your location?\r\nHow much is the initial house investment?', NULL, '2025-09-26 06:21:11', '2025-09-26 06:22:32'),
(333, 97, 'Sheila Mae Balancar', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Town homes lost cost', 2115666.50, 0.00, 0.00, 'Inquiry 3:am in sep 27', NULL, '2025-09-26 20:16:47', '2025-09-26 20:16:47'),
(334, 17, 'Roldan Padit', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'OFW', 'Southdale Villas', 'Southdale', 1880000.00, 0.00, 0.00, '', NULL, '2025-09-26 20:43:05', '2025-09-26 20:43:05'),
(335, 4, 'NEMRAC NELEB', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-09-27 05:46:42', '2025-09-27 05:46:42'),
(336, 4, 'Kim Eun La', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'tripping with in this month. need to follow up', NULL, '2025-09-27 05:49:07', '2025-09-27 05:49:07'),
(337, 4, 'Kim Abuel', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'not qualified sa alice. divert to other project but not responding yet', NULL, '2025-09-27 05:52:53', '2025-09-27 05:52:53'),
(338, 4, 'Gilda Baldonado', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'for follow up', NULL, '2025-09-27 05:58:21', '2025-09-27 05:58:21'),
(339, 4, 'Aishiteru  Murphy', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Manning', 'Self employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'follow up', NULL, '2025-09-27 06:05:36', '2025-09-27 06:05:36'),
(340, 4, 'Rho when nha', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'need to kulitin para maging warm maybe she just think muna', NULL, '2025-09-27 06:08:16', '2025-09-27 06:08:16'),
(341, 4, 'Naomih reyes', '', '', '', '', NULL, 'Hot', 'Negotiation', 'Manning', 'OFW', 'Lancaster', 'anica', 3781299.00, 0.00, 0.00, '', NULL, '2025-09-27 06:14:30', '2025-09-27 06:14:30'),
(342, 4, 'Jayson DG Mahinay', '09171395562', '', '', '', NULL, 'Hot', 'Presentation Stage', 'Manning', 'Locally/Internationally Employed', 'Minami Residence', 'Hana', 3661000.00, 0.00, 0.00, 'follow up', NULL, '2025-09-27 06:18:13', '2025-10-01 09:46:34'),
(343, 4, 'MC Akon', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', 'Self employed', 'n/a', 'n/a', 700000.00, 0.00, 0.00, 'not qualified not reqs.', NULL, '2025-09-27 06:22:17', '2025-09-27 06:22:17'),
(344, 97, 'Seb Irinco Giray Petilla', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 3900000.00, 0.00, 0.00, 'Asking sample computation Town houses and single with 🙏 emoji', NULL, '2025-09-27 21:03:50', '2025-09-27 21:03:50'),
(345, 20, 'Gascon Gascon', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'Nag inquire alice townhouse + Thea Townhouse', NULL, '2025-09-28 00:30:28', '2025-09-28 00:30:28'),
(346, 32, 'TATIANA  ROSEL REYS', '', '', '', '', NULL, 'Hot', 'Closed Deal', 'Facebook Page', 'Locally/Internationally Employed', 'Sapphire Residences', 'SAPPHIRE', 2514000.00, 0.00, 0.00, 'WAITING FOR FINAL DECISSION', NULL, '2025-09-28 13:38:27', '2025-09-30 23:06:31'),
(347, 97, 'Rachelle Chua', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquire at 10pm week in', NULL, '2025-09-28 18:22:01', '2025-09-28 18:22:01'),
(348, 97, 'Mark Pansacala', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', 'OFW', 'Beverly Homes', 'Town homes lost cost', 2000000.00, 0.00, 0.00, 'Nag inquire gusto Nia baliuag Bulacan area', NULL, '2025-09-29 13:07:33', '2025-09-29 13:07:33'),
(349, 97, 'Reynolds Numbers', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Nag inquire at lunch break', NULL, '2025-09-30 04:25:12', '2025-09-30 04:25:12'),
(350, 20, 'Bpk Nagiab', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'Ask rfo townhouse alice or thea', NULL, '2025-09-30 06:57:28', '2025-09-30 06:57:28'),
(351, 4, 'Emz Moraleta', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, '', NULL, '2025-09-30 08:28:32', '2025-09-30 08:28:32'),
(352, 4, 'Rico Toring', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', '', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, '', NULL, '2025-09-30 08:30:16', '2025-09-30 08:30:16'),
(353, 97, 'Jheff Generoso', '', '', '', '', NULL, 'Hot', 'Negotiation', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Want to RFO/Pre-seling', NULL, '2025-09-30 13:24:38', '2025-09-30 13:24:38'),
(354, 4, 'Richard Jandog', '09176372441', 'richardmecias@gmail.com', '', '', NULL, 'Warm', 'Presentation Stage', 'Manning', 'Locally/Internationally Employed', 'Villas De Trece', 'RCD villas', 1689000.00, 0.00, 0.00, '', NULL, '2025-09-30 14:21:02', '2025-10-01 09:28:05'),
(355, 94, 'Moises Platon', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2700.00, 0.00, 0.00, '', NULL, '2025-10-01 01:44:45', '2025-10-01 01:44:45'),
(356, 94, 'Claire Pantilo', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2700000.00, 0.00, 0.00, '', NULL, '2025-10-01 01:46:39', '2025-10-01 01:46:39'),
(357, 94, 'Cherry Ablaza', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2700000.00, 0.00, 0.00, '', NULL, '2025-10-01 01:48:29', '2025-10-01 01:48:29'),
(358, 94, 'Alexandra Marie', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2700000.00, 0.00, 0.00, '', NULL, '2025-10-01 01:50:22', '2025-10-01 01:50:22'),
(359, 97, 'Bai Jhen', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, '2:45 pm inquiry ofw dh', NULL, '2025-10-01 06:27:20', '2025-10-01 06:27:20'),
(360, 20, 'Siegfred Imperial', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-01 07:01:27', '2025-10-01 07:01:27'),
(361, 4, 'ALi Quitalan', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-01 09:29:49', '2025-10-01 09:29:49'),
(362, 4, 'Reymark  Sabuero', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-01 09:32:19', '2025-10-01 09:32:19'),
(363, 97, 'Nylebuj Gnotgolubgam', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'JuSt inquire at 9:30 pm', NULL, '2025-10-02 00:08:07', '2025-10-02 00:09:42'),
(364, 94, 'Laura Manloro', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Google Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2700000.00, 0.00, 0.00, '', NULL, '2025-10-02 07:24:47', '2025-10-02 07:24:47'),
(365, 8, 'Judai Malana', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook live', 'OFW', 'Lancaster', 'LIA', 3661000.00, 0.00, 0.00, '- co borrower husband', NULL, '2025-10-02 23:28:05', '2025-10-02 23:28:05'),
(366, 8, 'ANALYN GATPOLINTAN', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-02 23:46:07', '2025-10-02 23:46:07'),
(367, 8, 'ROBERT MEDALLE', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, '', NULL, '2025-10-03 00:01:54', '2025-10-03 00:01:54'),
(368, 8, 'REYNALYN CONSORTE', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Thea', 4000000.00, 0.00, 0.00, '', NULL, '2025-10-03 00:05:34', '2025-10-03 00:05:34'),
(369, 8, 'PRINCESS CALUNGCAGUIN', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2880000.00, 0.00, 0.00, '', NULL, '2025-10-03 00:07:18', '2025-10-03 00:07:18'),
(370, 8, 'IRENE DASAG', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2880000.00, 0.00, 0.00, '', NULL, '2025-10-03 00:08:53', '2025-10-03 00:08:53'),
(371, 8, 'WELLZ AZUNAL', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2880000.00, 0.00, 0.00, '', NULL, '2025-10-03 00:10:23', '2025-10-03 00:10:23'),
(372, 8, 'MANUEL OLIVEROS', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2880000.00, 0.00, 0.00, '', NULL, '2025-10-03 00:11:48', '2025-10-03 00:11:48'),
(373, 8, 'JERRYAYUN ABUYEN', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'AIRA', 8100000.00, 0.00, 0.00, '', NULL, '2025-10-03 00:13:50', '2025-10-03 00:13:50'),
(374, 8, 'MA CRYSTAL CATURA', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Thea', 4600000.00, 0.00, 0.00, '', NULL, '2025-10-03 00:15:56', '2025-10-03 00:15:56'),
(375, 8, 'RAMIL BAUTISTA', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'UNDECIDED YET', 1000000.00, 0.00, 0.00, '', NULL, '2025-10-03 00:17:32', '2025-10-03 00:17:32'),
(376, 8, 'KATHERINE CORPUZ', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', '', 'Lancaster', 'Alice', 2880000.00, 0.00, 0.00, 'FIRST TIME TO AVAIL HOUSING LOAN', NULL, '2025-10-03 00:22:21', '2025-10-03 00:22:21'),
(377, 97, 'Samantha Kristina', '09763120770', 'samkriscon.business@gmail.com', '', '', NULL, 'Cold', 'Presentation Stage', 'Facebook Ads', 'Self employed', 'Lancaster', 'Thea', 3900000.00, 0.00, 0.00, 'Give good response but from iloilo residence', NULL, '2025-10-03 06:07:53', '2025-10-03 06:11:05'),
(378, 97, 'JC JC', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry 8-9pm', NULL, '2025-10-03 16:42:42', '2025-10-03 16:42:42'),
(379, 97, 'George Mayor', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry 12 am midnight', NULL, '2025-10-03 16:44:11', '2025-10-04 06:56:59'),
(380, 97, 'Nhur Tahir', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry 6pm', NULL, '2025-10-03 16:48:40', '2025-10-03 16:48:40'),
(381, 4, 'Msm Tmags', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-04 00:57:43', '2025-10-04 00:57:43'),
(382, 4, 'Lemuel Jasa Eglip', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-04 00:59:48', '2025-10-04 00:59:48'),
(383, 4, 'Angela Perez', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-04 01:03:00', '2025-10-04 01:03:00'),
(384, 4, 'Jea Galler', '09310057370', 'gallejea93@gmail.com', '', '', NULL, 'Hot', 'Site Tour', 'KKK', 'Self employed', 'Berde Ville', 'lot only', 1000000.00, 0.00, 0.00, 'schedule for tripping and booking', NULL, '2025-10-04 01:08:23', '2025-10-04 01:08:23'),
(385, 4, 'Christian Viado', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Manning', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-04 01:15:16', '2025-10-04 01:15:16'),
(386, 97, 'Marlon Dagundon', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Nah inquire 12:30 pm', NULL, '2025-10-04 06:58:52', '2025-10-04 06:58:52'),
(387, 20, 'Wilven Maravilla', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, '', NULL, '2025-10-04 08:42:53', '2025-10-04 08:42:53'),
(388, 20, 'Gilbert Buhia', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, '', NULL, '2025-10-04 08:45:26', '2025-10-04 08:45:26'),
(389, 20, 'Nino Ngalonggalo', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, '', NULL, '2025-10-04 08:47:52', '2025-10-04 08:47:52'),
(390, 20, 'Zazezay Tibus', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, '', NULL, '2025-10-04 08:49:39', '2025-10-04 08:49:39'),
(391, 97, 'Jimbo Elcano', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry almost 5pm', NULL, '2025-10-04 08:52:04', '2025-10-04 08:52:04'),
(392, 20, 'Mharben Ostia', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, '', NULL, '2025-10-04 08:52:26', '2025-10-04 08:52:26'),
(393, 20, 'Maii Quiray', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, '', NULL, '2025-10-04 08:57:33', '2025-10-04 08:57:33'),
(394, 20, 'Dyese Nueve', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-05 01:49:29', '2025-10-05 01:49:29'),
(395, 97, 'Antonio Bani', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry at 6: 02 am', NULL, '2025-10-05 08:21:03', '2025-10-05 08:21:03'),
(396, 97, 'Ruth Balmores', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, '4:30 pm inquire mula', NULL, '2025-10-05 08:22:56', '2025-10-05 08:22:56'),
(397, 20, 'Ghen Ohx', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, '', NULL, '2025-10-05 11:46:14', '2025-10-05 11:46:14'),
(398, 97, 'Wengel Rosaroso Cabardo', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry 10 :30 pm', NULL, '2025-10-05 14:36:38', '2025-10-05 14:36:38'),
(399, 20, 'JL Carcallas', '+639159515760', '', '', '', NULL, 'Warm', 'Inquiry', 'Organic Posting', 'Locally/Internationally Employed', 'Pasinaya Homes', 'Pasinaya homes', 775000.00, 0.00, 0.00, 'Tripping October 17 or 18,2025\r\nMorning', NULL, '2025-10-06 07:00:51', '2025-10-06 07:00:51'),
(400, 97, 'Jhun Mercy', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Nag inquire 6-7pm Ng gabi', NULL, '2025-10-06 23:44:02', '2025-10-06 23:44:02'),
(401, 97, 'Flores Luzvi', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Nag inquire lang', NULL, '2025-10-06 23:47:35', '2025-10-06 23:47:35'),
(402, 97, 'Tatskie cantilado', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Nag inquire lang6 am Ng umaga', NULL, '2025-10-06 23:48:33', '2025-10-06 23:48:33'),
(403, 32, 'ALLEN CLANE REYES', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Facebook live', 'Locally/Internationally Employed', 'Sapphire Residences', 'DANNA', 2560000.00, 0.00, 0.00, '', NULL, '2025-10-07 02:45:47', '2025-10-07 02:53:02'),
(404, 97, 'Cristina De Castro', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Nag inquire 4pm', NULL, '2025-10-07 12:05:45', '2025-10-07 12:05:45'),
(405, 97, 'Keryanelly Ozasanam', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, '', NULL, '2025-10-07 23:43:59', '2025-10-07 23:43:59'),
(406, 20, 'NHING VERDADERO', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2833600.00, 0.00, 0.00, 'Ask ng pag ibig financing', NULL, '2025-10-08 07:57:58', '2025-10-08 07:57:58'),
(407, 94, 'Karen Melody', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2700000.00, 0.00, 0.00, '', NULL, '2025-10-08 08:26:00', '2025-10-08 08:26:00'),
(408, 94, 'Anrym Zhanyne', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2700000.00, 0.00, 0.00, '', NULL, '2025-10-08 08:27:49', '2025-10-08 08:27:49'),
(409, 94, 'Elmera Bamba', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2700000.00, 0.00, 0.00, '', NULL, '2025-10-08 08:29:33', '2025-10-08 08:29:33'),
(410, 94, 'Mel Yu', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2700000.00, 0.00, 0.00, '', NULL, '2025-10-08 08:31:10', '2025-10-08 08:31:10'),
(411, 94, 'Alyanna Gayta', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2700000.00, 0.00, 0.00, '', NULL, '2025-10-08 08:32:58', '2025-10-08 08:32:58'),
(412, 94, 'Fynn GernGernmie', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 270000.00, 0.00, 0.00, '', NULL, '2025-10-08 08:34:35', '2025-10-08 08:34:35'),
(413, 97, 'Mario del Valle', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry 6am', NULL, '2025-10-08 13:33:16', '2025-10-08 13:33:16'),
(414, 97, 'Ann', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Groups', '', 'Savia Parkway', 'Town homes', 2115666.50, 0.00, 0.00, 'Inquiry bon my post', NULL, '2025-10-08 13:37:14', '2025-10-08 13:37:14'),
(415, 97, 'Recad Alas', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Town homes', 2000000.00, 0.00, 0.00, 'Inquiry 10pm', NULL, '2025-10-08 14:18:17', '2025-10-08 14:18:17'),
(416, 20, 'Leonard Jusgado', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, '', NULL, '2025-10-09 05:16:52', '2025-10-09 05:16:52'),
(417, 97, 'Alice Pagliawan', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Like and comments lang po', NULL, '2025-10-19 12:34:08', '2025-10-19 12:34:08'),
(418, 97, 'Alice paglinawan', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Town homes lost cost', 2828000.00, 0.00, 0.00, 'Inquiry and comment only', NULL, '2025-10-19 21:59:06', '2025-10-19 21:59:06'),
(419, 97, 'Sunshine Patrocenio', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry send details', NULL, '2025-10-19 22:00:17', '2025-10-19 22:00:17'),
(420, 97, 'Edward Macase', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry', NULL, '2025-10-19 22:01:06', '2025-10-19 22:01:06'),
(421, 97, 'Salvador Bare', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry', NULL, '2025-10-19 22:02:23', '2025-10-19 22:02:23'),
(422, 97, 'Noemi Emadem', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', '', 'Lancaster', 'Alice', 2828000.00, 0.00, 0.00, 'Inquiry send details', NULL, '2025-10-19 22:04:28', '2025-10-19 22:04:28'),
(423, 97, 'Dexter Tiongco', '', '', '', '', NULL, 'Cold', 'Inquiry', 'KKK', '', 'Lancaster', 'Town homes lost cost', 1800000.00, 0.00, 0.00, 'Inquiry', NULL, '2025-10-19 22:06:52', '2025-10-19 22:06:52'),
(425, 97, 'Ken Bondaco', '+639616404464', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Thea', 3900000.00, 0.00, 0.00, 'nov 30 need to finalize by the parent and sister who are the one to stay', NULL, '2025-11-03 05:12:24', '2025-11-10 01:42:37'),
(426, 31, 'Japhet So', '+639761618340', 'japhetos73@gmail.com', '', '', NULL, 'Hot', 'Closed Deal', 'Chat messaging', 'OFW', 'Monte Royale (Parc Royal)', 'chesca expanded', 2965000.00, 0.00, 0.00, 'inquiry from laverne since 2020', NULL, '2025-11-09 06:15:24', '2025-11-15 13:13:09'),
(427, 13, 'Thea Joyce Del Rosario', '+639398321740', '', '', '', NULL, 'Hot', 'Housing Loan Application', 'Organic Posting', 'OFW', 'Deca Homes Hampton – Imus Cavite', 'Alice', 2345000.00, 0.00, 0.00, 'DECAHOMES 2-BEDROOM CONDO UNIT FOR PROJECT.WAITING FOR LOAN APPROVAL, NO DP. STRAIGHT PAYMENT MONTHLY AMORTIZATION UPON LOAN TAKE-OUT', NULL, '2025-11-12 04:17:15', '2025-11-12 04:35:13'),
(428, 13, 'Marites Lastimoso', '', '', '', '', NULL, 'Cold', 'Presentation Stage', 'Facebook Ads', 'Locally/Internationally Employed', 'Kaia Homes', 'Helena', 1595493.00, 0.00, 0.00, 'Both age 43, husband and wife total income 40k monthly. Looking for a house near manila. No project can be offered near Manila to suits their monthly income. waiting for confirmation for Naic project as suggested to fit their budget.', NULL, '2025-11-12 04:33:21', '2025-11-12 04:33:21'),
(429, 13, 'Ar Tee', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Landing Page', 'OFW', 'Minami Residence', 'Hana', 4695800.00, 0.00, 0.00, 'Ongoing conversation, waiting for final confirmation since the principal buyer is his brother, an OFW.  No information given.', NULL, '2025-11-12 04:57:38', '2025-11-12 04:57:38'),
(430, 13, 'Joana Marie', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Landing Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2805000.00, 0.00, 0.00, 'Follow up', NULL, '2025-11-12 05:00:27', '2025-11-12 05:00:27'),
(431, 13, 'Wan Ju', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Landing Page', 'Locally/Internationally Employed', 'Lancaster', 'Thea', 4054000.00, 0.00, 0.00, 'Confirmation for tripping schedule.', NULL, '2025-11-12 05:03:16', '2025-11-12 05:03:16'),
(432, 13, 'Cecille Galura', '+639255051218', 'teejay.dime@gmail.com', '', '', NULL, 'Warm', 'Site Tour', 'Landing Page', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, 'for tripping', NULL, '2025-11-12 05:06:08', '2025-11-12 05:06:08'),
(433, 13, 'Julian Bryle Menor', '+639189091024', '', '', '', NULL, 'Warm', 'Site Tour', 'Landing Page', 'Locally/Internationally Employed', 'COMELEC Village (Parc Royal)', 'Chesca', 2964500.00, 0.00, 0.00, 'Follow up for final decision.', NULL, '2025-11-12 05:24:37', '2025-11-12 05:24:37'),
(434, 8, 'Nino Viola', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'KKK', 'Locally/Internationally Employed', 'Monterra Verde 2', '.', 2334644.00, 0.00, 0.00, '* Schedule for site tour', NULL, '2025-11-15 00:29:02', '2025-11-15 00:29:02'),
(435, 8, 'Christina Bacani', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Organic Posting', 'Locally/Internationally Employed', 'Monterra Verde 2', '.', 2334644.00, 0.00, 0.00, 'Looking for 2 options : Monterra Verde or Pagsibol in Muzon Naic. \r\n- possible double booking with sibling', NULL, '2025-11-15 00:32:00', '2025-11-15 00:32:00'),
(436, 94, 'Joe ann Gardose', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Facebook Ads', '', 'Lancaster', 'Alice', 2800500.00, 0.00, 0.00, '', NULL, '2025-11-16 01:53:52', '2025-11-16 01:53:52'),
(437, 94, 'Eunice Poncedeleon cuenca', '', '', '', '', NULL, 'Hot', 'Presentation Stage', 'Facebook Ads', '', 'Lancaster', 'Thea', 800000.00, 0.00, 0.00, '', NULL, '2025-11-20 12:03:15', '2025-11-20 12:03:15'),
(438, 94, 'Carol Urbano', '', '', '', '', NULL, 'Hot', 'Site Tour', 'Referral', 'Locally/Internationally Employed', 'Beverly Homes', 'Beverly', 2500000.00, 0.00, 0.00, '', NULL, '2025-11-20 12:06:55', '2025-11-20 12:06:55'),
(439, 8, 'Ina Lim - Sangrador', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Organic Posting', 'Locally/Internationally Employed', 'Lanello Heights', 'Brenda', 4999761.20, 0.00, 0.00, '- combined income is 130k \r\n- looking for below 5M', NULL, '2025-12-03 00:43:44', '2025-12-03 00:43:44'),
(440, 94, 'Anthony Martin', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Referral', 'Locally/Internationally Employed', 'Lancaster', 'Merrydale st Joseph', 819840.00, 0.00, 0.00, '', NULL, '2025-12-22 11:40:43', '2025-12-22 11:40:43'),
(441, 94, 'Babylou patilan Tabada', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Facebook live', 'Locally/Internationally Employed', 'Axeia Dasmariñas', 'The palm residences', 3200000.00, 0.00, 0.00, 'Waiting Po doc\'s', NULL, '2025-12-22 12:10:58', '2025-12-30 11:59:13'),
(442, 94, 'Robbie Andra Aglibot', '+639950193582', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 3129280.00, 0.00, 0.00, 'On going dp', NULL, '2025-12-22 12:26:53', '2025-12-22 12:26:53'),
(443, 31, 'JAPHET A. SO', '+639766755958', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Organic Posting', 'OFW', 'COMELEC Village (Parc Royal)', 'Chesca', 2964500.00, 0.00, 0.00, '', NULL, '2025-12-25 02:55:08', '2025-12-25 02:59:20'),
(444, 31, 'RYAN JAY  DABU', '', '', '', '', NULL, 'Hot', 'Requirement Stage', 'Organic Posting', 'OFW', 'Southdale Villas', 'DANNA', 1880000.00, 0.00, 0.00, '', NULL, '2025-12-25 02:58:23', '2025-12-25 02:58:23'),
(445, 94, 'John Ralf Quijano', '', 'johnralfquijano@gmail.com', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'Locally/Internationally Employed', 'Southdale Villas', 'Others', 1880000.00, 0.00, 0.00, '', NULL, '2025-12-30 11:54:09', '2025-12-30 11:54:09'),
(446, 94, 'Erickson pancolit', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'OFW', 'Antipolo Heights', 'Antipolo Heights Model A', 1000000.00, 0.00, 0.00, '', NULL, '2025-12-30 12:08:16', '2025-12-30 12:08:16'),
(447, 94, 'Alyanna Katipunan', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'Locally/Internationally Employed', 'Lancaster', 'Merry dale st Joseph', 819840.00, 0.00, 0.00, '', NULL, '2025-12-30 12:13:10', '2025-12-30 12:13:10'),
(448, 94, 'Kervie Abueva', '+639604060339', '', '', '', NULL, 'Hot', 'Site Tour', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2800000.00, 0.00, 0.00, '', NULL, '2025-12-30 12:20:47', '2025-12-30 12:20:47'),
(449, 94, 'Mimi San Jose', '', '', '', '', NULL, 'Hot', 'Presentation Stage', 'Facebook live', 'OFW', 'Kathleen Place 5', 'Kathleen Place Model A', 5200000.00, 0.00, 0.00, '', NULL, '2025-12-30 12:33:14', '2025-12-30 12:33:14'),
(450, 94, 'Mylene Ofianza', '', '', '', '', NULL, 'Hot', 'Site Tour', 'Facebook live', 'Locally/Internationally Employed', 'Neuville Townhomes', 'Others', 4000000.00, 0.00, 0.00, '', NULL, '2025-12-30 12:38:59', '2025-12-30 12:38:59'),
(451, 31, 'Aurilio  ochinang', '', '', '', '', NULL, 'Cold', 'Inquiry', '', 'OFW', 'Sapphire Residences', 'Danna', 1880000.00, 0.00, 0.00, '', NULL, '2025-12-30 13:51:15', '2025-12-30 13:51:15'),
(452, 31, 'Lhan Mendoza', '', '', '', '', NULL, 'Cold', 'Inquiry', '', 'Locally/Internationally Employed', 'Kaia Homes', 'Helena', 1500000.00, 0.00, 0.00, '', NULL, '2025-12-30 13:52:59', '2025-12-30 13:52:59'),
(453, 31, 'Kevin Del Argente', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', 'Locally/Internationally Employed', 'Neuville Townhomes', 'astrid', 4180000.00, 0.00, 0.00, '', NULL, '2025-12-30 13:55:25', '2025-12-30 13:55:25'),
(454, 31, 'May Serviano', '+639051273715', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Page', 'OFW', 'Neuville Townhomes', 'astrid', 4180000.00, 0.00, 0.00, '', NULL, '2025-12-30 13:57:40', '2025-12-30 13:57:40'),
(455, 18, 'Jerico Blanca', '', '', '', '', NULL, 'Hot', 'Closed Deal', 'KKK', '', 'Antipolo Heights', 'Lot only', 1200000.00, 0.00, 0.00, 'Monthly amortization', NULL, '2026-01-05 21:50:53', '2026-01-05 21:50:53'),
(456, 18, 'Patrisha Azul and Gian Geronimo', '', '', '', '', NULL, 'Hot', 'Closed Deal', 'KKK', 'Locally/Internationally Employed', 'Antipolo Heights', 'Lot only', 1000000.00, 0.00, 0.00, 'Monthly amortization on going', NULL, '2026-01-05 21:52:18', '2026-01-05 21:52:18'),
(457, 94, 'Leo Comanda', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'OFW', 'Lanello Heights', 'Chelsea', 5097160.00, 0.00, 0.00, 'Tnx mark ok na lms ko', NULL, '2026-01-15 05:18:39', '2026-02-12 06:20:11'),
(458, 13, 'ELENA PALISOC', '+639634377307', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Organic Posting', '', 'Merrydale St. Joseph', 'Parking lot', 283136.00, 0.00, 0.00, '', NULL, '2026-01-19 06:35:19', '2026-01-19 06:35:19'),
(459, 13, 'Jacky Arsenal Paragua', '', '', '', '', NULL, 'Hot', 'Negotiation', 'Organic Posting', 'Locally/Internationally Employed', 'Lancaster', 'Thea', 3922000.00, 0.00, 0.00, 'Ongoing negotiations, requesting a site tour.Waiting for schedule\r\nClient scheduled a tripping. Did not pursue since it is very far from their work', NULL, '2026-01-19 06:40:37', '2026-04-08 17:35:17'),
(460, 13, 'Mirasol Deligero', '', '', '', '', NULL, 'Warm', 'Negotiation', 'Organic Posting', '', 'Savia Parkway', 'NA', 3500000.00, 0.00, 0.00, '', NULL, '2026-01-19 07:07:23', '2026-01-19 07:07:23'),
(461, 29, 'Dondemer D. De Asis', '+639380688195', 'vitoriademier16@gmail.com', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'Locally/Internationally Employed', 'Southdale Villas', 'inner townhouse', 2143000.00, 0.00, 0.00, 'Bllk 42 Lot 2 ( corner lot )', NULL, '2026-01-24 08:41:47', '2026-02-16 14:36:55'),
(462, 29, 'Ruby Jasper Aspera', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'Referral', 'Locally/Internationally Employed', 'Southdale Villas', 'inner townhouse', 1880000.00, 0.00, 0.00, 'Blk 38 Lot 25', NULL, '2026-01-24 08:42:56', '2026-02-16 14:13:06'),
(463, 32, 'MARIA JULA INFANTE', '', '', '', '', NULL, 'Hot', 'Closed Deal', 'Referral', 'OFW', 'Elisa Homes', 'Canalily', 4681200.00, 0.00, 0.00, '', NULL, '2026-01-24 13:35:21', '2026-01-24 13:35:21'),
(464, 4, 'Leonard trambulo', '', '', '', '', NULL, 'Hot', 'Housing Loan Application', 'Facebook Ads', 'Self employed', 'Lancaster', 'Thea', 5563200.00, 0.00, 0.00, 'Full cash dp', NULL, '2026-02-12 03:55:18', '2026-02-12 04:07:19'),
(465, 4, 'Janica mae Resus', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, '', NULL, '2026-02-12 03:57:26', '2026-02-12 03:57:26'),
(466, 4, 'Izza Sibayan', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', 'Self employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, '', NULL, '2026-02-12 03:59:24', '2026-02-12 03:59:24'),
(467, 4, 'Angelica Padilla', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Facebook Ads', 'Self employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, '', NULL, '2026-02-12 04:02:34', '2026-02-12 04:02:34'),
(468, 4, 'Myka Sy', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, '', NULL, '2026-02-12 04:03:56', '2026-02-12 04:03:56'),
(469, 4, 'Ma.Glocel Ann Villaflores', '', '', '', '', NULL, 'Hot', 'Negotiation', 'Facebook Ads', 'Self employed', 'Pineview', 'Lot Only', 3019600.00, 0.00, 0.00, 'Schedule for tripping', NULL, '2026-02-12 04:14:56', '2026-02-12 04:14:56'),
(470, 4, 'Helen Dy', '', '', '', '', NULL, 'Warm', 'Site Tour', 'KKK', 'Locally/Internationally Employed', 'Tarragona Place', 'Highland res.', 2283350.00, 0.00, 0.00, '', NULL, '2026-02-12 04:19:34', '2026-02-12 04:19:34'),
(471, 94, 'Ronalyn Castro Yumol', '+639933606445', 'ronyumol@gmail.com', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'Locally/Internationally Employed', 'Southdale Villas', 'Gentri villas', 1821000.00, 0.00, 0.00, '', NULL, '2026-02-12 10:33:35', '2026-02-12 10:33:35'),
(472, 4, 'Ma.clarisa martinez', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', 'Locally/Internationally Employed', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, '', NULL, '2026-02-15 06:30:37', '2026-02-15 06:30:37'),
(473, 8, 'MJ Jajalla', '', '', '', '', NULL, 'Warm', 'Presentation Stage', 'Organic Posting', 'Locally/Internationally Employed', 'Lancaster', 'Thea', 4000000.00, 0.00, 0.00, '- will discuss with husband and possible site tour on Saturday.', NULL, '2026-02-16 14:51:01', '2026-02-16 14:51:01'),
(474, 8, 'Oliver Santos', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Referral', 'OFW', 'Kaia Homes', 'Helena', 1600000.00, 0.00, 0.00, 'wife will be the AIF', NULL, '2026-02-16 14:53:09', '2026-02-16 14:53:09'),
(475, 8, 'Jem Medina', '', '', '', '', NULL, 'Cold', 'Inquiry', 'KKK', 'Locally/Internationally Employed', 'Axeia Batangas', 'Linnea', 2700000.00, 0.00, 0.00, 'follow up !', NULL, '2026-02-16 14:56:37', '2026-02-16 14:56:37'),
(476, 15, 'Mark Faraon', '+111111111111111', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'TikTok ads', 'OFW', 'Antel', 'California', 1000000.00, 0.00, 0.00, 'This is my first lead :) ayaw ng bumili . darating ung asawa. bukas na lang daw tawqgan.', NULL, '2026-02-21 17:02:03', '2026-02-23 06:58:11'),
(477, 4, 'Fatima Joy Ladoing', '', '', '', '', NULL, 'Cold', 'Inquiry', 'Facebook Ads', 'OFW', 'Lancaster', 'Alice', 2838000.00, 0.00, 0.00, '', NULL, '2026-02-24 22:35:28', '2026-02-24 22:35:28'),
(478, 4, 'Kevin isidro', '', '', '', '', NULL, 'Warm', 'Inquiry', 'Facebook Ads', 'OFW', 'Lancaster', 'Aira', 8076000.00, 0.00, 0.00, '', NULL, '2026-02-26 07:47:31', '2026-02-26 07:47:31'),
(479, 19, 'Mabelle Mante', '', '', '', '', NULL, 'Hot', 'Negotiation', 'Facebook Page', 'Locally/Internationally Employed', 'Southdale Villas', 'Southdale', 1800000.00, 0.00, 0.00, 'for tripping., nag inquire noong feb 2026. single with kids. taga gentri. call center. may sasakyan , preferred may car port. naic, tanza at gentri ang preference nya. open sya sa lessandra.', NULL, '2026-04-10 06:36:39', '2026-04-10 06:36:39'),
(480, 4, 'Clark william Lim', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'Locally/Internationally Employed', 'Lessandra by Bria', 'Bettina', 2300000.00, 0.00, 0.00, '', NULL, '2026-04-21 22:58:17', '2026-04-21 22:58:17'),
(481, 4, 'Jhonrey Diana', '', '', '', '', NULL, 'Hot', 'Downpayment Stage', 'KKK', 'Locally/Internationally Employed', 'Lessandra by Bria', 'Bettina', 2500000.00, 0.00, 0.00, '', NULL, '2026-04-21 23:00:30', '2026-04-21 23:00:30');

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
(16, 27, 58, 'Lead Update', 'Lead details updated:\n- Changed status from \'Loan Takeout\' to \'Closed Deal\'\n', NULL, NULL, 0, '2025-06-09 07:12:01'),
(38, 46, 9, 'Lead Update', 'Lead details updated:\n- Changed email from \'\' to \'alvinllaneta8@gmail.com\'\n- Changed temperature from \'Hot\' to \'Warm\'\n', NULL, NULL, 0, '2025-07-04 08:53:35'),
(39, 46, 9, 'Lead Update', 'Lead details updated:\n- Changed status from \'Inquiry\' to \'Presentation Stage\'\n', NULL, NULL, 0, '2025-07-04 09:01:17'),
(60, 78, 55, 'Lead Update', 'Lead details updated:\n- Changed temperature from \'Warm\' to \'Cold\'\n', NULL, NULL, 0, '2025-07-15 07:35:41'),
(61, 123, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2833600\'\n', NULL, NULL, 0, '2025-07-15 12:59:25'),
(62, 122, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2833600\'\n', NULL, NULL, 0, '2025-07-15 12:59:53'),
(63, 121, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2833600\'\n', NULL, NULL, 0, '2025-07-15 13:01:20'),
(64, 120, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2833600\'\n', NULL, NULL, 0, '2025-07-15 13:01:50'),
(65, 119, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2833600\'\n', NULL, NULL, 0, '2025-07-15 13:02:03'),
(66, 118, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2833600\'\n', NULL, NULL, 0, '2025-07-15 13:02:19'),
(67, 117, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2833600\'\n', NULL, NULL, 0, '2025-07-15 13:02:53'),
(68, 116, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2833600\'\n', NULL, NULL, 0, '2025-07-15 13:03:25'),
(69, 115, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2833600\'\n', NULL, NULL, 0, '2025-07-15 13:06:03'),
(71, 27, 58, 'Lead Update', 'Lead details updated:\n- Changed status from \'Closed Deal\' to \'Negotiation\'\n', NULL, NULL, 0, '2025-07-18 01:46:41'),
(72, 27, 58, 'Lead Update', 'Lead details updated:\n- Changed source from \'Facebook Groups\' to \'Facebook Page\'\n', NULL, NULL, 0, '2025-07-18 01:51:27'),
(74, 130, 55, 'Lead Update', 'Lead details updated:\n- Changed temperature from \'Cold\' to \'Warm\'\n', NULL, NULL, 0, '2025-07-18 09:13:55'),
(75, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed status from \'Inquiry\' to \'Downpayment Stage\'\n', NULL, NULL, 0, '2025-07-18 09:20:18'),
(76, 128, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-07-18 09:20:51'),
(77, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed status from \'Downpayment Stage\' to \'House Turn Over\'\n', NULL, NULL, 0, '2025-07-18 09:21:59'),
(78, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed temperature from \'Cold\' to \'Hot\'\n', NULL, NULL, 0, '2025-07-18 09:23:11'),
(79, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed project_model from \'Kennedy\' to \'Nixonnn\'\n', NULL, NULL, 0, '2025-07-18 09:24:07'),
(80, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed status from \'House Turn Over\' to \'Downpayment Stage\'\n', NULL, NULL, 0, '2025-07-18 09:25:05'),
(81, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed project_model from \'Nixonnn\' to \'Kennedy\'\n', NULL, NULL, 0, '2025-07-18 09:26:58'),
(82, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed temperature from \'Hot\' to \'Cold\'\n', NULL, NULL, 0, '2025-07-18 09:27:14'),
(83, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed temperature from \'Cold\' to \'Hot\'\n', NULL, NULL, 0, '2025-07-18 09:27:46'),
(84, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed status from \'Downpayment Stage\' to \'Housing Loan Application\'\n', NULL, NULL, 0, '2025-07-18 09:28:39'),
(85, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed temperature from \'Hot\' to \'Cold\'\n- Changed status from \'Housing Loan Application\' to \'Closed Deal\'\n', NULL, NULL, 0, '2025-07-18 09:29:18'),
(86, 128, 32, 'Lead Update', 'Lead details updated:\n- Changed temperature from \'Cold\' to \'Hot\'\n- Changed status from \'Closed Deal\' to \'Downpayment Stage\'\n', NULL, NULL, 0, '2025-07-18 09:29:54'),
(87, 124, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'2833600.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:19:16'),
(88, 123, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'2833600.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:19:30'),
(89, 122, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'2833600.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:19:41'),
(90, 121, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'2833600.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:19:57'),
(91, 114, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:27:35'),
(92, 115, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'2833600.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:27:50'),
(93, 116, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'2833600.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:28:07'),
(94, 117, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'2833600.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:28:58'),
(95, 118, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'2833600.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:29:23'),
(96, 120, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'2833600.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:29:39'),
(97, 105, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:30:19'),
(98, 106, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:34:34'),
(99, 107, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:34:56'),
(100, 108, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:35:10'),
(101, 110, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:35:22'),
(102, 111, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:35:46'),
(103, 112, 56, 'Lead Update', 'Lead details updated:\n- Changed price from \'3900000.00\' to \'2783000\'\n', NULL, NULL, 0, '2025-07-21 01:36:04'),
(119, 48, 24, 'Lead Update', 'Lead details updated:\n- Changed status from \'Negotiation\' to \'Lost\'\n- Changed developer from \'Liora Homes\' to \'Kaia Homes\'\n- Changed project_model from \'Amora\' to \'Helena\'\n- Changed remarks from \'Pa close na\' to \'Nag iba ng plan, kukuha na lang ng lot para sama sama sa isang compound\'\n', NULL, NULL, 0, '2025-07-22 05:00:10'),
(133, 202, 105, 'Status Change', 'Status changed from \'Presentation Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-08-04 07:11:43'),
(134, 202, 105, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-08-04 07:13:21'),
(135, 202, 105, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Closed Deal\'', NULL, NULL, 0, '2025-08-04 07:13:54'),
(137, 128, 32, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Inquiry\'', NULL, NULL, 0, '2025-08-14 11:35:23'),
(138, 209, 32, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Lost\'', NULL, NULL, 0, '2025-08-14 11:40:08'),
(139, 209, 32, 'Status Change', 'Status changed from \'Lost\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-08-14 11:54:01'),
(140, 214, 20, 'Call', 'follow up', NULL, NULL, 0, '2025-09-01 06:36:02'),
(141, 214, 20, 'Status Change', 'Status changed from \'Inquiry\' to \'Presentation Stage\'', NULL, NULL, 0, '2025-09-01 06:36:51'),
(142, 214, 20, 'Status Change', 'Status changed from \'Presentation Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-01 06:40:12'),
(143, 214, 20, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-01 06:41:34'),
(144, 214, 20, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-01 06:42:15'),
(145, 218, 30, 'Status Change', 'Status changed from \'Inquiry\' to \'Negotiation\'', NULL, NULL, 0, '2025-09-04 09:37:20'),
(146, 210, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-06 11:05:45'),
(147, 208, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-06 11:08:22'),
(148, 209, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-06 11:17:40'),
(149, 222, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-06 11:25:54'),
(150, 209, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-06 11:26:32'),
(151, 216, 8, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-07 08:16:28'),
(152, 216, 8, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Requirement Stage\'', NULL, NULL, 0, '2025-09-07 08:17:30'),
(153, 49, 13, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-10 23:20:30'),
(154, 228, 13, 'Status Change', 'Status changed from \'Closed Deal\' to \'Lost\'', NULL, NULL, 0, '2025-09-11 05:42:07'),
(155, 235, 97, 'Status Change', 'Status changed from \'Inquiry\' to \'Negotiation\'', NULL, NULL, 0, '2025-09-13 02:30:50'),
(195, 225, 13, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Lost\'', NULL, NULL, 0, '2025-09-18 06:18:26'),
(196, 251, 97, 'Status Change', 'Status changed from \'Presentation Stage\' to \'Negotiation\'', NULL, NULL, 0, '2025-09-22 08:17:13'),
(197, 216, 8, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-23 08:45:12'),
(198, 264, 15, 'Status Change', 'Status changed from \'Negotiation\' to \'Site Tour\'', NULL, NULL, 0, '2025-09-23 08:59:33'),
(199, 264, 15, 'Status Change', 'Status changed from \'Site Tour\' to \'Requirement Stage\'', NULL, NULL, 0, '2025-09-23 08:59:44'),
(200, 264, 15, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-23 09:01:47'),
(201, 259, 4, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-23 09:03:03'),
(202, 259, 4, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Requirement Stage\'', NULL, NULL, 0, '2025-09-23 09:04:00'),
(203, 259, 4, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-23 09:06:47'),
(204, 266, 20, 'Status Change', 'Status changed from \'Inquiry\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-23 09:08:42'),
(205, 208, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-23 09:08:59'),
(206, 257, 12, 'Status Change', 'Status changed from \'Site Tour\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-23 09:09:27'),
(207, 209, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-23 09:10:51'),
(208, 257, 12, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Negotiation\'', NULL, NULL, 0, '2025-09-23 09:10:57'),
(209, 209, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-23 09:11:13'),
(210, 210, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-23 09:11:32'),
(211, 263, 3, 'Status Change', 'Status changed from \'Negotiation\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-23 09:13:15'),
(212, 264, 15, 'Other', 'From Lancaster to Kaia', NULL, NULL, 0, '2025-09-23 09:27:41'),
(213, 278, 7, 'Initial Contact', 'nag inquire sa lancaster page', NULL, NULL, 0, '2025-09-24 05:21:45'),
(214, 278, 7, 'Call', 'tinawagan ni sir erwin to qualify', NULL, NULL, 0, '2025-09-24 05:21:58'),
(215, 278, 7, 'Site Tour', 'scheduled for site tour sa kaia homes this weekend', NULL, NULL, 0, '2025-09-24 05:22:17'),
(216, 283, 7, 'Other', 'inquired sa lancaster page on sep 21 sunday, hindi daw prefer lancaster', NULL, NULL, 0, '2025-09-24 06:32:57'),
(217, 283, 7, 'Follow-up', 'sep 24 wednesday\r\n\r\nfollow up, sent pics and computations for masaito projects', NULL, NULL, 0, '2025-09-24 06:33:29'),
(218, 284, 7, 'Other', 'inquired on sep 20 sa LNC page', NULL, NULL, 0, '2025-09-24 06:34:51'),
(219, 284, 7, 'Follow-up', 'sep 24 wed\r\n\r\nfollow up', NULL, NULL, 0, '2025-09-24 06:35:05'),
(220, 285, 7, 'Other', 'inquired in LNC page on sep 18 thurs', NULL, NULL, 0, '2025-09-24 06:36:42'),
(221, 285, 7, 'Follow-up', 'sep 24 wed\r\n\r\nfollow up', NULL, NULL, 0, '2025-09-24 06:36:52'),
(222, 286, 7, 'Other', 'sep 17 wed\r\n\r\ninquired in LNC page', NULL, NULL, 0, '2025-09-24 06:39:08'),
(223, 286, 7, 'Follow-up', 'sep 24 wed\r\n\r\nfollow up', NULL, NULL, 0, '2025-09-24 06:39:19'),
(224, 287, 7, 'Other', 'sep 17 wed\r\n\r\ninquired in LNC page', NULL, NULL, 0, '2025-09-24 06:40:42'),
(225, 287, 7, 'Follow-up', 'sep 24 wed\r\n\r\nfollow up', NULL, NULL, 0, '2025-09-24 06:40:52'),
(226, 288, 7, 'Other', 'sep 16 tue\r\n\r\ninquired in LNC page', NULL, NULL, 0, '2025-09-24 06:42:11'),
(227, 288, 7, 'Follow-up', 'sep 24 wed\r\n\r\nfollow up', NULL, NULL, 0, '2025-09-24 06:42:26'),
(228, 278, 7, 'Status Change', 'Status changed from \'Inquiry\' to \'Site Tour\'', NULL, NULL, 0, '2025-09-24 06:45:29'),
(229, 291, 7, 'Other', 'sep 15 mon\r\n\r\ninquired in LNC page: Where is your location?', NULL, NULL, 0, '2025-09-24 06:55:27'),
(230, 291, 7, 'Follow-up', 'sep 24 wed\r\n\r\nfollow up', NULL, NULL, 0, '2025-09-24 06:55:39'),
(231, 279, 14, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Closed Deal\'', NULL, NULL, 0, '2025-09-24 09:17:00'),
(232, 260, 32, 'Status Change', 'Status changed from \'Negotiation\' to \'Site Tour\'', NULL, NULL, 0, '2025-09-25 02:29:50'),
(233, 210, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-25 02:39:04'),
(234, 208, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-25 02:41:08'),
(235, 314, 32, 'Status Change', 'Status changed from \'Negotiation\' to \'Closed Deal\'', NULL, NULL, 0, '2025-09-25 02:46:55'),
(236, 314, 32, 'Status Change', 'Status changed from \'Closed Deal\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-09-25 03:04:12'),
(237, 208, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-25 03:05:41'),
(238, 208, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-25 03:06:06'),
(239, 208, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-09-25 03:06:47'),
(240, 205, 32, 'Status Change', 'Status changed from \'Site Tour\' to \'Requirement Stage\'', NULL, NULL, 0, '2025-09-25 03:10:34'),
(241, 251, 97, 'Status Change', 'Status changed from \'Negotiation\' to \'Site Tour\'', NULL, NULL, 0, '2025-09-25 08:52:29'),
(246, 332, 7, 'Other', 'sep 26 2025\r\n\r\ninquired on LNC page', NULL, NULL, 0, '2025-09-26 06:22:16'),
(247, 332, 7, 'Follow-up', 'sep 26 2025 fri\r\n\r\nfollow up', NULL, NULL, 0, '2025-09-26 06:22:32'),
(248, 259, 4, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Presentation Stage\'', NULL, NULL, 0, '2025-09-27 06:31:28'),
(249, 274, 4, 'Status Change', 'Fullypaid september 2,2025', NULL, NULL, 0, '2025-09-27 06:36:05'),
(250, 346, 32, 'Status Change', 'Status changed from \'Site Tour\' to \'Closed Deal\'', NULL, NULL, 0, '2025-09-30 23:06:31'),
(251, 330, 8, 'Status Change', 'Status changed from \'Presentation Stage\' to \'Negotiation\'', NULL, NULL, 0, '2025-10-02 23:24:27'),
(252, 379, 97, 'Status Change', 'Status changed from \'Inquiry\' to \'Presentation Stage\'', NULL, NULL, 0, '2025-10-04 06:56:59'),
(253, 209, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-10-07 02:21:09'),
(254, 209, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-10-07 02:21:10'),
(255, 208, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-10-07 02:24:51'),
(256, 205, 32, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-10-07 02:32:35'),
(257, 403, 32, 'Status Change', 'Status changed from \'Closed Deal\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-10-07 02:53:02'),
(258, 264, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-10-08 08:59:48'),
(259, 264, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-10-08 09:18:54'),
(260, 403, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-10-10 07:15:17'),
(261, 210, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-10-10 07:15:44'),
(262, 205, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-10-10 07:17:38'),
(263, 314, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-10-21 06:34:16'),
(264, 222, 32, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Lost\'', NULL, NULL, 0, '2025-10-21 06:39:06'),
(266, 42, 31, 'Status Change', 'Status changed from \'Site Tour\' to \'Lost\'', NULL, NULL, 0, '2025-11-09 07:04:37'),
(267, 425, 97, 'Status Change', 'Status changed from \'Site Tour\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-11-10 01:42:35'),
(268, 227, 13, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-11-12 04:20:57'),
(269, 205, 32, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Housing Loan Application\'', NULL, NULL, 0, '2025-12-25 00:58:43'),
(270, 403, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:06:15'),
(271, 403, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:13:51'),
(272, 403, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:14:51'),
(273, 314, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:15:45'),
(274, 314, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:16:31'),
(275, 314, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:16:34'),
(276, 209, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:17:40'),
(277, 210, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:24:35'),
(278, 210, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:30:26'),
(279, 210, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:31:16'),
(280, 209, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 01:32:46'),
(281, 208, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 02:22:39'),
(282, 208, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 02:23:09'),
(283, 443, 31, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2025-12-25 02:59:17'),
(284, 443, 31, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-25 03:01:21'),
(285, 441, 94, 'Status Change', 'Status changed from \'Closed Deal\' to \'Negotiation\'', NULL, NULL, 0, '2025-12-30 11:59:09'),
(286, 268, 94, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Site Tour\'', NULL, NULL, 0, '2025-12-30 12:01:57'),
(287, 403, 32, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2025-12-30 13:42:23'),
(288, 227, 13, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Housing Loan Application\'', NULL, NULL, 0, '2026-01-19 06:26:59'),
(289, 464, 4, 'Site Tour', 'Booking done', NULL, NULL, 0, '2026-02-12 04:06:51'),
(290, 464, 4, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Housing Loan Application\'', NULL, NULL, 0, '2026-02-12 04:07:19'),
(291, 216, 8, 'Status Change', 'Status changed from \'Downpayment Stage\' to \'Lost\'', NULL, NULL, 0, '2026-02-16 14:12:08'),
(292, 462, 8, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2026-02-16 14:13:07'),
(293, 461, 8, 'Status Change', 'Status changed from \'Requirement Stage\' to \'Downpayment Stage\'', NULL, NULL, 0, '2026-02-16 14:13:59'),
(294, 476, 15, 'Call', 'Call him and give all the materials', NULL, NULL, 0, '2026-02-21 17:04:00'),
(295, 476, 15, 'Status Change', 'Status changed from \'Inquiry\' to \'Site Tour\'', NULL, NULL, 0, '2026-02-21 17:04:29'),
(296, 476, 15, 'Status Change', 'Status changed from \'Site Tour\' to \'Closed Deal\'', NULL, NULL, 0, '2026-02-21 17:05:34'),
(297, 476, 15, 'Status Change', 'Status changed from \'Closed Deal\' to \'Downpayment Stage\'', NULL, NULL, 0, '2026-02-21 17:06:20'),
(298, 476, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2026-02-21 17:08:23'),
(299, 476, 15, 'Downpayment Tracker', 'Updated downpayment tracker information', NULL, NULL, 0, '2026-02-21 17:10:02');

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
(11, 27, 58, 'status_change', 'Loan Takeout', 'Closed Deal', 16, '2025-06-09 07:12:01'),
(18, 46, 9, 'email_change', '', 'alvinllaneta8@gmail.com', 38, '2025-07-04 08:53:35'),
(19, 46, 9, 'temperature_change', 'Hot', 'Warm', 38, '2025-07-04 08:53:35'),
(20, 46, 9, 'status_change', 'Inquiry', 'Presentation Stage', 39, '2025-07-04 09:01:17'),
(31, 78, 55, 'temperature_change', 'Warm', 'Cold', 60, '2025-07-15 07:35:41'),
(32, 123, 56, 'price_change', '3900000.00', '2833600', 61, '2025-07-15 12:59:25'),
(33, 122, 56, 'price_change', '3900000.00', '2833600', 62, '2025-07-15 12:59:53'),
(34, 121, 56, 'price_change', '3900000.00', '2833600', 63, '2025-07-15 13:01:20'),
(35, 120, 56, 'price_change', '3900000.00', '2833600', 64, '2025-07-15 13:01:50'),
(36, 119, 56, 'price_change', '3900000.00', '2833600', 65, '2025-07-15 13:02:03'),
(37, 118, 56, 'price_change', '3900000.00', '2833600', 66, '2025-07-15 13:02:19'),
(38, 117, 56, 'price_change', '3900000.00', '2833600', 67, '2025-07-15 13:02:53'),
(39, 116, 56, 'price_change', '3900000.00', '2833600', 68, '2025-07-15 13:03:25'),
(40, 115, 56, 'price_change', '3900000.00', '2833600', 69, '2025-07-15 13:06:03'),
(42, 27, 58, 'status_change', 'Closed Deal', 'Negotiation', 71, '2025-07-18 01:46:41'),
(43, 27, 58, 'source_change', 'Facebook Groups', 'Facebook Page', 72, '2025-07-18 01:51:27'),
(46, 130, 55, 'temperature_change', 'Cold', 'Warm', 74, '2025-07-18 09:13:55'),
(47, 128, 32, 'status_change', 'Inquiry', 'Downpayment Stage', 75, '2025-07-18 09:20:18'),
(48, 128, 32, 'status_change', 'Downpayment Stage', 'House Turn Over', 77, '2025-07-18 09:21:59'),
(49, 128, 32, 'temperature_change', 'Cold', 'Hot', 78, '2025-07-18 09:23:11'),
(50, 128, 32, 'project_model_change', 'Kennedy', 'Nixonnn', 79, '2025-07-18 09:24:07'),
(51, 128, 32, 'status_change', 'House Turn Over', 'Downpayment Stage', 80, '2025-07-18 09:25:05'),
(52, 128, 32, 'project_model_change', 'Nixonnn', 'Kennedy', 81, '2025-07-18 09:26:58'),
(53, 128, 32, 'temperature_change', 'Hot', 'Cold', 82, '2025-07-18 09:27:14'),
(54, 128, 32, 'temperature_change', 'Cold', 'Hot', 83, '2025-07-18 09:27:46'),
(55, 128, 32, 'status_change', 'Downpayment Stage', 'Housing Loan Application', 84, '2025-07-18 09:28:39'),
(56, 128, 32, 'temperature_change', 'Hot', 'Cold', 85, '2025-07-18 09:29:18'),
(57, 128, 32, 'status_change', 'Housing Loan Application', 'Closed Deal', 85, '2025-07-18 09:29:18'),
(58, 128, 32, 'temperature_change', 'Cold', 'Hot', 86, '2025-07-18 09:29:54'),
(59, 128, 32, 'status_change', 'Closed Deal', 'Downpayment Stage', 86, '2025-07-18 09:29:54'),
(60, 124, 56, 'price_change', '2833600.00', '2783000', 87, '2025-07-21 01:19:16'),
(61, 123, 56, 'price_change', '2833600.00', '2783000', 88, '2025-07-21 01:19:30'),
(62, 122, 56, 'price_change', '2833600.00', '2783000', 89, '2025-07-21 01:19:41'),
(63, 121, 56, 'price_change', '2833600.00', '2783000', 90, '2025-07-21 01:19:57'),
(64, 114, 56, 'price_change', '3900000.00', '2783000', 91, '2025-07-21 01:27:35'),
(65, 115, 56, 'price_change', '2833600.00', '2783000', 92, '2025-07-21 01:27:50'),
(66, 116, 56, 'price_change', '2833600.00', '2783000', 93, '2025-07-21 01:28:07'),
(67, 117, 56, 'price_change', '2833600.00', '2783000', 94, '2025-07-21 01:28:58'),
(68, 118, 56, 'price_change', '2833600.00', '2783000', 95, '2025-07-21 01:29:23'),
(69, 120, 56, 'price_change', '2833600.00', '2783000', 96, '2025-07-21 01:29:39'),
(70, 105, 56, 'price_change', '3900000.00', '2783000', 97, '2025-07-21 01:30:19'),
(71, 106, 56, 'price_change', '3900000.00', '2783000', 98, '2025-07-21 01:34:34'),
(72, 107, 56, 'price_change', '3900000.00', '2783000', 99, '2025-07-21 01:34:56'),
(73, 108, 56, 'price_change', '3900000.00', '2783000', 100, '2025-07-21 01:35:10'),
(74, 110, 56, 'price_change', '3900000.00', '2783000', 101, '2025-07-21 01:35:22'),
(75, 111, 56, 'price_change', '3900000.00', '2783000', 102, '2025-07-21 01:35:46'),
(76, 112, 56, 'price_change', '3900000.00', '2783000', 103, '2025-07-21 01:36:04'),
(90, 48, 24, 'status_change', 'Negotiation', 'Lost', 119, '2025-07-22 05:00:10'),
(91, 48, 24, 'developer_change', 'Liora Homes', 'Kaia Homes', 119, '2025-07-22 05:00:10'),
(92, 48, 24, 'project_model_change', 'Amora', 'Helena', 119, '2025-07-22 05:00:10'),
(93, 48, 24, 'remarks_change', 'Pa close na', 'Nag iba ng plan, kukuha na lang ng lot para sama sama sa isang compound', 119, '2025-07-22 05:00:10'),
(96, 214, 20, 'activity_added', NULL, 'Call', 140, '2025-09-01 06:36:02'),
(98, 264, 15, 'activity_added', NULL, 'Other', 212, '2025-09-23 09:27:41'),
(99, 278, 7, 'activity_added', NULL, 'Initial Contact', 213, '2025-09-24 05:21:45'),
(100, 278, 7, 'activity_added', NULL, 'Call', 214, '2025-09-24 05:21:58'),
(101, 278, 7, 'activity_added', NULL, 'Site Tour', 215, '2025-09-24 05:22:17'),
(102, 283, 7, 'activity_added', NULL, 'Other', 216, '2025-09-24 06:32:57'),
(103, 283, 7, 'activity_added', NULL, 'Follow-up', 217, '2025-09-24 06:33:29'),
(104, 284, 7, 'activity_added', NULL, 'Other', 218, '2025-09-24 06:34:51'),
(105, 284, 7, 'activity_added', NULL, 'Follow-up', 219, '2025-09-24 06:35:05'),
(106, 285, 7, 'activity_added', NULL, 'Other', 220, '2025-09-24 06:36:42'),
(107, 285, 7, 'activity_added', NULL, 'Follow-up', 221, '2025-09-24 06:36:52'),
(108, 286, 7, 'activity_added', NULL, 'Other', 222, '2025-09-24 06:39:08'),
(109, 286, 7, 'activity_added', NULL, 'Follow-up', 223, '2025-09-24 06:39:19'),
(110, 287, 7, 'activity_added', NULL, 'Other', 224, '2025-09-24 06:40:42'),
(111, 287, 7, 'activity_added', NULL, 'Follow-up', 225, '2025-09-24 06:40:52'),
(112, 288, 7, 'activity_added', NULL, 'Other', 226, '2025-09-24 06:42:11'),
(113, 288, 7, 'activity_added', NULL, 'Follow-up', 227, '2025-09-24 06:42:26'),
(114, 291, 7, 'activity_added', NULL, 'Other', 229, '2025-09-24 06:55:27'),
(115, 291, 7, 'activity_added', NULL, 'Follow-up', 230, '2025-09-24 06:55:39'),
(117, 332, 7, 'activity_added', NULL, 'Other', 246, '2025-09-26 06:22:16'),
(118, 332, 7, 'activity_added', NULL, 'Follow-up', 247, '2025-09-26 06:22:32'),
(119, 274, 4, 'activity_added', NULL, 'Status Change', 249, '2025-09-27 06:36:05'),
(120, 464, 4, 'activity_added', NULL, 'Site Tour', 289, '2026-02-12 04:06:51'),
(121, 476, 15, 'activity_added', NULL, 'Call', 294, '2026-02-21 17:04:00');

-- --------------------------------------------------------

--
-- Table structure for table `manual_data`
--

CREATE TABLE `manual_data` (
  `id` int NOT NULL,
  `datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `team` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `toggle` enum('1','0','','2') COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manual_data`
--

INSERT INTO `manual_data` (`id`, `datetime`, `name`, `team`, `toggle`) VALUES
(257, '2025-01-29 17:02:54', 'NICO PESINO DE LOS SANTOS', 'Feisty Heroine', '1'),
(258, '2025-01-29 17:13:10', 'ALVIN LLANETA', 'Feisty Heroine', '1'),
(259, '2025-01-30 10:48:21', 'Rize Lagrimas', 'Blazing SPARCS', '1'),
(260, '2025-01-30 11:21:58', 'Verlyn Vesagas', 'Blazing SPARCS', '1'),
(261, '2025-01-31 09:32:46', 'EFREN LISONDRA BUALAT JR.', 'Fiery Achievers', '0'),
(262, '2025-02-03 07:17:28', 'Ma. Carla Baltazar', 'Feisty Heroine', '1'),
(263, '2025-02-03 09:36:42', 'Michelle Pacuan', 'Fiery Achievers', '0'),
(264, '2025-02-03 11:47:52', 'Sheryl C Sablon', 'Feisty Heroine', '0'),
(265, '2025-02-05 11:17:43', 'Daven Yurong', 'Blazing SPARCS', '0'),
(266, '2025-02-05 13:40:37', 'Rhaynon G Amonggo', 'Feisty Heroine', '0'),
(267, '2025-02-05 14:32:30', 'LIEZEL SOLIS', 'Feisty Heroine', '0'),
(268, '2025-02-05 17:07:37', 'Michael Keanu Valdivia', 'Blazing SPARCS', '1'),
(269, '2025-02-10 21:12:48', 'Ailyn L. De Torres', 'Feisty Heroine', '0'),
(270, '2025-02-11 13:39:46', 'EMILYN CANTUBA', 'Feisty Heroine', '1'),
(271, '2025-02-12 08:50:55', 'Ma Cristina', 'Feisty Heroine', '0'),
(272, '2025-02-12 09:09:48', 'Jhon Sherwin Jayme', 'Fiery Achievers', '0'),
(273, '2025-02-12 19:26:47', 'Perlita S. Go', 'Blazing SPARCS', '1'),
(274, '2025-02-17 07:15:21', 'Jesselie Abayon', 'Shining Phoenix', '1'),
(275, '2025-02-17 12:56:45', 'Dennisa Anne Legaspi Lizano', 'Shining Phoenix', '1'),
(276, '2025-02-19 13:23:27', 'Crisalyn', 'Shining Phoenix', '0'),
(277, '2025-02-20 14:58:46', 'Krizel Apan', 'Shining Phoenix', '1'),
(278, '2025-02-20 15:49:18', 'Michele C. Abejero', 'Shining Phoenix', '0'),
(279, '2025-02-21 07:15:33', 'Geraldine Maurat', 'Shining Phoenix', '0'),
(280, '2025-02-25 16:09:44', 'Jhune mark abello', 'Fiery Achievers', '1'),
(281, '2025-03-05 12:20:42', 'Marco Arellano', 'Blazing SPARCS', '1'),
(282, '2025-03-06 13:55:24', 'Emilyn Cantuba', 'Feisty Heroine', '0'),
(283, '2025-03-09 08:44:54', 'EFREN LISONDRA BUALAT', 'Fiery Achievers', '1'),
(284, '2025-03-11 08:50:17', 'Lee Edison Laysa', 'Blazing SPARCS', '0'),
(285, '2025-03-12 07:54:20', 'SHILA MARIE Q. PACUAN', 'Blazing SPARCS', '0'),
(286, '2025-03-18 17:30:28', 'KRISTINA MARIS N. PINEDA', 'Fiery Achievers', '0'),
(287, '2025-03-18 17:43:21', 'Charita Basbas', 'Shining Phoenix', '0'),
(288, '2025-03-18 19:11:49', 'Rosalinda Tilos', 'Shining Phoenix', '0'),
(289, '2025-03-18 21:07:11', 'RHODORA RIO B. SANTOS', 'Shining Phoenix', '1'),
(290, '2025-03-18 23:17:18', 'Jean Paula Bacus', 'Shining Phoenix', '1'),
(291, '2025-03-18 23:58:32', 'Annabelle Alonzo', 'Shining Phoenix', '0'),
(292, '2025-03-19 07:36:03', 'Rionel Amonggo', 'Feisty Heroine', '1'),
(293, '2025-03-19 13:18:41', 'Lorenzo Gayoso Jr.B', 'Blazing SPARCS', '0'),
(294, '2025-03-19 17:40:36', 'Mercy Tubania', 'Shining Phoenix', '1'),
(295, '2025-03-19 17:44:48', 'Joan c dabalos', 'Shining Phoenix', '1'),
(296, '2025-03-21 18:57:24', 'Ailyn L. De Torres', 'Feisty Heroine', '1'),
(297, '2025-03-27 13:54:37', 'Michelle Pacuan', 'Fiery Achievers', '1'),
(298, '2025-03-28 17:52:49', 'John Alvin N. Villagene', 'Blazing SPARCS', '1'),
(299, '2025-03-29 19:38:52', 'Dorina', 'Blazing SPARCS', '0'),
(300, '2025-03-30 23:00:32', 'Jullie ann  castillo', 'Feisty Heroine', '0'),
(301, '2025-04-01 12:49:02', 'Michael John Agenar', 'Feisty Heroine', '1'),
(302, '2025-04-03 07:52:46', 'Monica Nidea', 'Feisty Heroine', '1'),
(303, '2025-04-03 15:13:15', 'JovenVillanueva', 'Feisty Heroine', '0'),
(304, '2025-04-04 14:16:53', 'Michael John Agenar', 'Feisty Heroine', '0'),
(305, '2025-04-05 15:28:25', 'Richmond m rili', 'Feisty Heroine', '1'),
(306, '2025-04-16 12:29:56', 'Arian F. Ramos', 'Blazing SPARCS', '1'),
(307, '2025-04-21 17:29:32', 'Liezle Vista', 'Feisty Heroine', '1'),
(308, '2025-04-21 22:35:27', 'Arlene L Umali', 'Flameborn Champions', '1'),
(309, '2025-04-30 17:50:39', 'Annabelle Alonzo', 'Shining Phoenix', '1'),
(310, '2025-05-07 08:58:47', 'Cynthia M Martin', 'Fiery Achievers', '1'),
(311, '2025-05-16 09:48:29', 'Natalie Joy A. Rubio', 'Feisty Heroine', '0'),
(312, '2025-05-24 20:14:11', 'Rhea', 'Shining Phoenix', '0'),
(313, '2025-05-24 20:14:55', 'Natalie Joy A. Rubio', 'Feisty Heroine', '0'),
(314, '2025-07-02 17:43:22', 'Gavrie', 'Feisty Heroine', '2'),
(316, '2025-07-03 13:42:00', 'Piolo Pascual', 'Blazing SPARCS', '1'),
(317, '2025-07-04 17:44:00', 'Mark Christian Patigayon', 'Feisty Heroine', '1');

-- --------------------------------------------------------

--
-- Table structure for table `memos`
--

CREATE TABLE `memos` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `memo_when` datetime NOT NULL,
  `memo_where` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') COLLATE utf8mb4_general_ci DEFAULT 'Medium',
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
(7, 'REVISED COMMISSION RATES AND ADDITIONAL VAT DEDUCTION', 'uploads/memos/1750233308_MEMO  FOR ALL JUNE 18, 2025.docx', 'As discussed, and agreed during our meeting on June 16, 2025, please be informed of the new commission rates and additional deductions to be implemented effective immediately for all sales under partner developers where VAT invoices are issued.\r\n', '2025-06-18 00:55:08', NULL, 'Urgent', 1, 8, 8, 0, '2025-06-18 07:55:08', '2025-06-18 07:55:08'),
(8, 'Adjustment in Commission rate and New Computation Format', 'uploads/memos/1751290124_MEMORANDUM_20250628_193528_0000.pdf', 'This is to formally inform everyone of the updated commission computation and adjusted commission\r\nrates for affected developers. These changes were approved during the managers and supervisors’ meeting\r\nheld on June 26, 2025.', '2025-06-30 06:28:44', NULL, 'Urgent', 1, 8, 8, 0, '2025-06-30 13:28:44', '2025-06-30 13:28:44'),
(9, 'Follow up team', NULL, 'Hello team Blazing Sparc\r\nPlease! Musta inquiry natin \r\nGawin natin sales para Saturday for booking reservation \r\nHappy selling everyone 🫶🏡', '2025-09-23 02:33:46', NULL, 'Medium', 1, 20, 1, 0, '2025-09-23 09:33:46', '2025-09-23 09:33:46'),
(10, 'Updated Policies on Sales Awards, Training, and Agent Development', 'uploads/memos/1759455731_Company-Memo.docx', 'In line with our management meeting held on September 29, 2025, the following policies and programs have been agreed upon and will take effect starting October 2025. These initiatives are designed to motivate performance, strengthen competencies, and support agent activation across our teams.\r\n\r\nPlease see the attachment for details. Thanks!', '2025-10-02 18:42:11', NULL, 'High', 1, 8, 8, 0, '2025-10-03 01:42:11', '2025-10-03 01:42:11');

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
-- Table structure for table `memo_person_visibility`
--

CREATE TABLE `memo_person_visibility` (
  `id` int NOT NULL,
  `memo_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `memo_person_visibility`
--

INSERT INTO `memo_person_visibility` (`id`, `memo_id`, `user_id`, `created_at`) VALUES
(1, 7, 8, '2025-06-18 07:55:08'),
(2, 7, 20, '2025-06-18 07:55:08'),
(3, 7, 19, '2025-06-18 07:55:08'),
(4, 7, 7, '2025-06-18 07:55:08'),
(5, 7, 3, '2025-06-18 07:55:08'),
(6, 8, 8, '2025-06-30 13:28:44'),
(8, 8, 20, '2025-06-30 13:28:44'),
(9, 8, 19, '2025-06-30 13:28:44'),
(10, 8, 24, '2025-06-30 13:28:44'),
(11, 8, 31, '2025-06-30 13:28:44'),
(12, 8, 7, '2025-06-30 13:28:44'),
(13, 8, 3, '2025-06-30 13:28:44'),
(14, 10, 8, '2025-10-03 01:42:11');

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
(17, 7, 7, 1, '2025-06-18 10:58:56', '2025-06-18 07:58:56'),
(18, 7, 8, 1, '2025-06-18 08:04:28', '2025-06-18 08:04:28'),
(19, 8, 8, 1, '2025-06-30 13:29:18', '2025-06-30 13:29:18'),
(20, 8, 15, 1, '2025-07-03 08:20:22', '2025-07-03 05:20:22'),
(21, 7, 15, 1, '2025-07-04 12:29:41', '2025-07-04 09:29:41'),
(22, 9, 20, 1, '2025-09-24 00:34:09', '2025-09-23 09:34:09'),
(23, 9, 13, 1, '2025-09-24 00:40:03', '2025-09-23 09:40:03'),
(24, 9, 18, 1, '2025-09-25 08:59:31', '2025-09-25 08:59:31');

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

--
-- Dumping data for table `memo_team_visibility`
--

INSERT INTO `memo_team_visibility` (`id`, `memo_id`, `team_id`, `created_at`) VALUES
(3, 9, 1, '2025-09-23 09:33:46');

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
-- Table structure for table `problem_reports`
--

CREATE TABLE `problem_reports` (
  `id` int NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','medium','high') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `browser_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('open','in-progress','resolved','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `assigned_to` int DEFAULT NULL,
  `resolution_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `problem_reports`
--

INSERT INTO `problem_reports` (`id`, `username`, `phone`, `email`, `issue_type`, `priority`, `description`, `browser_info`, `status`, `assigned_to`, `resolution_notes`, `created_at`, `updated_at`, `resolved_at`) VALUES
(1, 'test', '(096) 549-33939', 'test3@gmail.com', 'performance', 'high', 'test', 'Safari Unknown Version | Windows 10/11 (64-bit) | Screen: 1536x864 | 24-bit color | 12 cores | 4GB RAM | en-PH | Asia/Manila | Cookies: Enabled | Online: Yes', 'resolved', 63, '', '2025-07-15 10:08:55', '2025-08-04 03:41:12', '2025-08-04 03:41:12'),
(2, 'davidcasil.intern', '(965) 329-8954', 'cdavidangelo3@gmail.com', 'feature-bug', 'medium', 'Test', 'Safari Unknown Version | Windows 10/11 (64-bit) | Screen: 1536x864 | 24-bit color | 12 cores | 8GB RAM | en-PH | Asia/Manila | Cookies: Enabled | Online: Yes', 'in-progress', 63, '', '2025-07-29 01:20:30', '2025-08-04 03:40:46', NULL),
(9, 'gavrietalaboc.intern', '(099) 999-99999', 'gavrietalaboc@gmail.com', 'login-failed', 'high', 'efqfqfafewfw', 'Safari Unknown Version | Windows 10/11 (64-bit) | Screen: 1280x720 | 24-bit color | 8 cores | 8GB RAM | en-US | Asia/Singapore | Cookies: Enabled | Online: Yes', 'open', NULL, NULL, '2025-07-30 08:39:37', '2025-07-30 08:39:37', NULL),
(10, 'test report', '(091) 238-29382', 'test@mail.com', 'page-error', 'high', '123', 'Safari Unknown Version | Windows 10/11 (64-bit) | Screen: 1536x864 | 24-bit color | 12 cores | 8GB RAM | en-PH | Asia/Manila | Cookies: Enabled | Online: Yes', 'closed', 63, '', '2025-07-30 09:44:35', '2025-08-04 03:41:55', '2025-08-04 02:43:28'),
(11, 'Marielle (test)', '(096) 532-91002', 'testmail@yahoo.com', 'performance', 'high', '(TEST) The project listing is not working, pakiayos naman po! ', 'Safari Unknown Version | Windows 10/11 (64-bit) | Screen: 1536x864 | 24-bit color | 12 cores | 8GB RAM | en-PH | Asia/Manila | Cookies: Enabled | Online: Yes', 'in-progress', 63, '', '2025-08-04 02:46:54', '2025-08-04 02:59:29', NULL),
(12, 'markpatigayon.itadmin', '(091) 293-93939', '', 'data-issue', 'low', 'Hindi tama yung info', 'Safari Unknown Version | Windows 10/11 (64-bit) | Screen: 1093x615 | 24-bit color | 4 cores | 8GB RAM | en-US | Asia/Taipei | Cookies: Enabled | Online: Yes', 'open', 15, NULL, '2025-09-01 07:09:32', '2025-09-22 09:15:34', NULL);

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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `total_contract_price` decimal(15,2) DEFAULT NULL,
  `reservation_fee` decimal(15,2) DEFAULT NULL,
  `bank_amortization` decimal(15,2) DEFAULT NULL,
  `required_salary` decimal(15,2) DEFAULT NULL,
  `downpayment_percentage` decimal(5,2) DEFAULT NULL,
  `downpayment_amount` decimal(15,2) DEFAULT NULL,
  `downpayment_term` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `house_model`, `status`, `developer`, `price_min`, `price_max`, `commission`, `priority`, `city_id`, `province_id`, `exact_location`, `image1`, `image2`, `image3`, `image4`, `drive_link`, `messenger_link`, `created_at`, `updated_at`, `total_contract_price`, `reservation_fee`, `bank_amortization`, `required_salary`, `downpayment_percentage`, `downpayment_amount`, `downpayment_term`) VALUES
(14, 'Hana South', 'Townhouse', 'Lyca, Erica, Rosanna', 'preselling', 'ACM Homes', 2200000.00, 2200000.00, 3.25, 'medium', 16, 11, 'Brgy. Cabuco Sitio Ilaya, Trece Martires Cavite', 'project_14_1_1750060210.jpg', 'project_14_2_1750055980.jpg', 'project_14_3_1750055980.jpg', 'project_14_4_1750055980.jpg', '', '', '2025-06-16 03:47:28', '2025-07-18 09:25:37', 2284000.00, 15000.00, 20000.00, NULL, 10.00, 8936.00, 24),
(15, 'New Leaf', 'Single Detached, Attached, Townhouse', 'Bernice', 'preselling', 'Filinvest', 2700000.00, 3200000.00, 2.50, 'medium', 16, 11, 'NEW LEAF Osorio Rd, Brgy Hugo Perez, Trece Martirez Cavite', 'project_15_1_1750055649.jpg', 'project_15_2_1751510271.jpg', 'project_15_3_1751510271.jpg', 'project_15_4_1751510271.jpg', 'https://drive.google.com/drive/u/0/folders/1Cpc0F6PA3EX6Ufzx854J2nb3fhlFfrmP', '', '2025-06-16 05:10:57', '2025-07-21 07:58:02', 2161000.00, 5000.00, NULL, NULL, NULL, 19241.00, 12),
(16, 'Pilila Heights', 'Lot Only', '', 'preselling', 'MCR Realty Ventures OPC', 500000.00, 10000000.00, 5.00, 'low', 20, 12, 'Sitio Matagbak, Daang Mulawin Brgy. Bagumbayan Pililla Rizal', 'project_16_1_1750059138.jpeg', 'project_16_2_1750059138.jpeg', 'project_16_3_1750059138.jpeg', 'project_16_4_1750059138.jpeg', 'https://drive.google.com/drive/folders/1zeimM42UrSuAMFGp7PNlji1D-LkXOBhh', '', '2025-06-16 05:21:29', '2025-07-07 09:13:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'Antipolo Heights', 'Lot Only', '', 'preselling', 'MCR Realty Ventures OPC', 560000.00, 10000000.00, 5.00, 'medium', 21, 12, '', 'project_17_1_1750057050.jpg', 'project_17_2_1750057050.jpg', 'project_17_3_1750057050.jpg', NULL, '', '', '2025-06-16 05:23:40', '2025-07-03 05:25:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'Golden Montana', 'Lot Only', '', 'preselling', 'MCR Realty Ventures OPC', 500000.00, 10000000.00, 5.00, 'medium', 21, 12, 'Barangay Lanang Maybangkal Morong Rizal', 'project_18_1_1750059235.jpg', 'project_18_2_1750059235.jpg', 'project_18_3_1750059235.jpg', NULL, 'https://drive.google.com/drive/folders/1zeimM42UrSuAMFGp7PNlji1D-LkXOBhh', '', '2025-06-16 05:24:27', '2025-07-07 09:05:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'Pacific Ace Village', 'Lot Only', '', 'preselling', 'Pacific Ace Real Estate', 1100000.00, 1300000.00, 2.50, 'low', 17, 11, 'Capt. E-Bocalan St, Amaya II, Tanza', 'project_19_1_1750059458.jpg', 'project_19_2_1750059458.jpg', 'project_19_3_1750059458.jpg', 'project_19_4_1750059458.png', 'https://drive.google.com/drive/mobile/folders/1WSd5M9CNBdfyVwAPtYvAchFHUQp-5YMq?usp=drive_link', '', '2025-06-16 05:26:05', '2025-06-16 07:37:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'Westdale 2', 'Townhouse', 'Elena, Stefania, Marquesa', 'rfo', 'Homemark Peakland', 1800000.00, 2300000.00, 5.00, 'high', 17, 11, 'Punta Dos, Tanza, Cavite', 'project_21_1_1750059694.JPG', 'project_21_2_1750059694.JPG', 'project_21_3_1750059694.JPG', 'project_21_4_1750059694.JPG', '', '', '2025-06-16 05:26:46', '2025-07-29 05:19:05', 1830000.00, 5000.00, 12720.00, 32102.56, NULL, 15000.00, 12),
(22, 'Istana Tanza', 'Townhouse', '', 'rfo', 'Charles Builders Group of Companies', 2300000.00, 2471000.00, 3.00, 'high', 17, 11, 'Barangay Biga, Tanza, Cavite', 'image1_1753771039_7573.jpg', 'image2_1753771039_7481.jpg', 'project_22_3_1750054213.jpg', 'project_22_4_1750054213.jpg', '', '', '2025-06-16 05:29:39', '2025-08-08 06:45:15', 2471000.00, 5000.00, NULL, 41212.00, 10.00, 8556.00, 18),
(23, 'Southdale', 'Townhouse', 'Selena', 'preselling', 'Homemark Peakland', 1700000.00, 1900000.00, 3.00, 'medium', 17, 11, 'Brgy Santol Tanza Cavite', 'project_23_1_1750059849.png', 'project_23_2_1750059614.JPG', 'project_23_3_1750059614.JPG', 'project_23_4_1750059614.JPG', '', '', '2025-06-16 05:29:46', '2025-07-29 05:19:22', 1880000.00, 5000.00, 181700.00, 47000.00, NULL, NULL, NULL),
(24, 'Northdale Estate', 'Townhouse', 'Selena', 'preselling', 'Homemark Peakland', 1300000.00, 1580000.00, 3.00, 'high', 23, 11, 'Brgy San Roque Naic, Cavite', 'image1_1753838633_1813.jpg', 'project_24_2_1750059581.JPG', 'project_24_3_1750059581.JPG', 'project_24_4_1750059581.JPG', 'https://drive.google.com/drive/u/0/folders/14LoSzJI0FvXoZTMgLk74K4dyhmsnGCxh', '', '2025-06-16 05:34:32', '2025-07-30 01:23:53', 1580000.00, 5000.00, NULL, 29102.00, NULL, NULL, NULL),
(25, 'Pagsikat Place', 'Townhouse', '', 'rfo', 'Raemulan', 1200000.00, 1300000.00, 2.50, 'low', 23, 11, 'Brgy. Labac Muzon, Naic, Cavite', 'project_25_1_1750056021.jpg', 'project_25_2_1750056021.jpg', 'project_25_3_1750056021.jpg', 'project_25_4_1750056021.jpg', '', '', '2025-06-16 05:36:27', '2025-07-21 08:25:07', 1250000.00, 10000.00, NULL, NULL, NULL, NULL, NULL),
(26, 'Estanzia Enclave', 'Twinhome', '', 'rfo', 'Raemulan Lands', 2600000.00, 3600000.00, 3.00, 'low', 17, 11, 'Barangay Sahud-Ulan, Antero Soriano Hwy, Tanza, Cavite', 'project_26_1_1750060160.jpg', 'project_26_2_1750058746.jpg', 'project_26_3_1750058746.jpg', 'project_26_4_1750058746.jpg', '', '', '2025-06-16 05:37:07', '2025-07-11 09:03:13', 2688000.00, 20000.00, NULL, NULL, 1.90, 21000.00, 2),
(27, 'Pineview', 'Single Detached', 'Molave, Wallnut', 'rfo', 'Filinvest', 3400000.00, 4700000.00, 2.50, 'medium', 17, 11, 'Remulla Drive Tanza-Naic Road, Barangay Sahud-Ulan, Tanza, Cavite', 'project_27_1_1750060573.jpg', 'project_27_2_1750059297.jpg', 'project_27_3_1751511970.jpg', 'project_27_4_1751511970.JPG', 'https://drive.google.com/drive/u/0/folders/1EEzf52loZijgS3K-drGplkesWo1Uz2gh', '', '2025-06-16 05:37:47', '2025-07-21 08:04:56', 4504600.00, 20000.00, NULL, NULL, 5.00, 12512.00, 18),
(28, '3 Verde Rosa', 'Single Attached, Duplex', 'Rose', 'rfo', 'CRC Realty', 3600000.00, 3800000.00, 3.00, 'medium', 17, 11, 'Brgy. Sanja Major Tanza Cavite.', 'project_28_1_1750056869.jpg', 'project_28_2_1750056869.jpg', NULL, NULL, 'https://drive.google.com/drive/folders/1sRguAzxLsRXuZBjxieHlzTjimOFaWk_I', '', '2025-06-16 05:38:32', '2025-07-21 07:20:06', 3852000.00, 20000.00, NULL, 70598.00, 20.00, 30458.00, 12),
(29, 'Anyana', 'Single Detached/ Lot Only', 'Paris, Sydney, Tokyo, Florida', 'preselling', 'Antel Land', 8000000.00, 16000000.00, 3.00, 'medium', 17, 11, 'Barangay, Sanja Mayor, Tanza, 4108 Cavite', 'project_29_1_1750056066.jpg', 'project_29_2_1750056066.jpg', 'project_29_3_1750056066.jpg', 'project_29_4_1750056066.jpg', 'https://drive.google.com/drive/mobile/folders/13kk8inizpPzUfpTLTlx-sWZbwMVMyaSt?usp=sharing', '', '2025-06-16 05:39:05', '2025-07-10 02:39:25', 16000000.00, 30000.00, NULL, 19000.00, 15.00, NULL, NULL),
(30, 'Rosepointe Subdivision', 'Single Attached', '', 'rfo', 'CCRC REALTY', 3600000.00, 5800000.00, 3.00, 'medium', 22, 13, 'Brgy. Tagapo, Santa Rosa City, Laguna', 'image1_1753838422_6555.jpg', 'project_30_2_1750056323.jpg', 'project_30_3_1750056323.jpg', 'project_30_4_1750056323.jpg', '', '', '2025-06-16 05:40:34', '2025-07-30 01:20:22', 2545000.00, 20000.00, NULL, NULL, NULL, 29006.46, 24),
(31, 'Golden Vista', 'Lot Only', '', 'preselling', 'MCR Realty Ventures OPC', 500000.00, 10000000.00, 5.00, 'medium', 21, 12, '', 'project_31_1_1750059393.jpeg', 'project_31_2_1750059393.jpeg', 'project_31_3_1750059393.jpeg', 'project_31_4_1750059393.jpeg', 'https://drive.google.com/drive/folders/1zeimM42UrSuAMFGp7PNlji1D-LkXOBhh', '', '2025-06-16 05:41:37', '2025-07-07 09:06:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 'Pagsibol Village', 'Townhouse', 'Santan, Aubergine, Turqoise', 'preselling', 'Raemulan', 1700000.00, 1900000.00, 2.50, 'low', 23, 11, 'Pagsibol 3, Dynamism St, Naic, Cavite', 'project_32_1_1750055909.jpg', 'project_32_2_1750055909.jpg', 'project_32_3_1750055909.jpg', NULL, '', '', '2025-06-16 05:42:12', '2025-07-11 08:55:45', 1750000.00, 10000.00, 11453.00, 33000.00, NULL, NULL, NULL),
(34, 'Liora Homes', 'Townhouse', 'Amora', 'rfo', 'MyCitiHomes', 2300000.00, 2700000.00, 3.50, 'high', 23, 11, 'Brgy. Malainen Bago, Naic, Cavite', 'project_34_1_1750055942.jpg', 'project_34_2_1750055942.jpg', 'project_34_3_1750055942.jpg', NULL, 'https://drive.google.com/drive/folders/1NtyOFRM8i8-kiQ9vkuestBr7xWO-0p8_?usp=sharing', '', '2025-06-16 05:59:38', '2025-07-15 07:28:21', 2700000.00, 12000.00, NULL, 39000.00, 10.00, NULL, NULL),
(35, 'Kaia Homes', 'Townhouse, Row House', 'Helena', 'rfo', 'KAIA Homes', 1400000.00, 2300000.00, 3.50, 'high', 23, 11, 'Brgy. Palangue 2 & 3, Naic, Cavite.', 'project_35_1_1750060644.jpg', 'project_35_2_1750055889.jpg', 'project_35_3_1750055889.jpg', 'project_35_4_1750055889.jpg', 'https://drive.google.com/drive/folders/1EbkVjmOzxSqckfp83xRFowuAZrUE9Q1j?usp=drive_link', '', '2025-06-16 06:01:09', '2025-07-18 09:26:32', 1505047.00, 7500.00, NULL, 25297.00, NULL, 59547.00, 6),
(36, 'Comelec VIllage', 'Single Attached, Detached, Townhouse', 'Chesca, Audrey, Felicity, Danna, Era', 'rfo', 'Masaito', 2700000.00, 6600000.00, 3.50, 'medium', 24, 11, 'Advincula Road Brgy.Alapan 2A Imus ,Cavite', 'project_36_1_1750055861.jpg', 'project_36_2_1750055861.jpg', 'project_36_3_1750055861.jpg', NULL, 'https://drive.google.com/drive/folders/1-82LBN7LaLOvCNWcvdJwh3WXXm4jnufj?usp=drive_link', '', '2025-06-16 06:03:44', '2025-07-14 03:05:32', 4487160.00, 10000.00, NULL, NULL, NULL, 29247.00, 15),
(37, 'Lancaster New City', 'Single Attached, Detached, Townhouse, Condo', 'Chessa, Gabrielle, Margareth, Thea, Aira, Alice, Alexandra, Briana', 'rfo', 'Profriends', 2700000.00, 8500000.00, 2.50, 'high', 19, 11, 'Advincula Avenue, Alapan II-B, Imus City Cavite', 'project_37_1_1750060697.jpg', 'project_37_2_1750055554.jpg', 'project_37_3_1750055554.jpg', 'project_37_4_1750055554.jpg', 'https://drive.google.com/drive/u/0/folders/18u7cwWbwON-PGZJBTiceAbpATc6OLTlz', '', '2025-06-16 06:05:32', '2025-07-29 05:12:02', 2807000.00, 15000.00, NULL, 60000.00, 6.50, 17125.00, 30),
(38, 'Lanello Heights', 'Single Attached, Detached, Townhouse', 'Abbie, Brenda, Chelsea', 'rfo', 'Masaito', 3700000.00, 5300000.00, 3.50, 'high', 25, 11, 'Barangay Pasong Camachile II, General Trias, Cavite', 'project_38_1_1750059938.jpg', 'project_38_2_1750055533.jpg', 'project_38_3_1750055533.jpg', 'project_38_4_1750055533.jpg', 'https://drive.google.com/drive/mobile/folders/12JmMKDVdV8EjcrOdzDVEmwvEcgkciNhM', '', '2025-06-16 06:07:50', '2025-07-18 09:19:32', 3946800.00, 20000.00, NULL, 83664.00, 15.00, NULL, NULL),
(39, 'Minami Residences', 'Quadruplex', 'Hanna', 'preselling', 'Profriends', 4300000.00, 4700000.00, 3.00, 'high', 25, 11, 'Barangay Santiago, General Trias, Cavite', 'project_39_1_1750059980.jpg', 'project_39_2_1750055499.jpg', 'project_39_3_1750055499.jpg', NULL, 'https://drive.google.com/drive/mobile/folders/12JmMKDVdV8EjcrOdzDVEmwvEcgkciNhM', '', '2025-06-16 06:10:12', '2025-07-29 05:13:14', 4422230.00, 25000.00, 33447.00, 110000.00, NULL, 25000.00, 21),
(40, 'Masaito Homes', '', 'Abela, Bailey, Daniella', 'rfo', 'Masaito', 1000000.00, 4578000.00, 3.50, 'medium', 18, 11, '𝘔𝘰𝘭𝘪𝘯𝘰 𝘉𝘭𝘷𝘥., 𝘉𝘢𝘤𝘰𝘰𝘳, 𝘊𝘢𝘷𝘪𝘵𝘦', 'project_40_1_1750055464.jpg', 'project_40_2_1750055464.jpg', 'project_40_3_1750055464.jpg', 'project_40_4_1750055464.jpg', 'https://drive.google.com/drive/mobile/folders/12JmMKDVdV8EjcrOdzDVEmwvEcgkciNhM', '', '2025-06-16 06:17:23', '2025-07-11 09:09:32', 4578000.00, 20000.00, NULL, 78000.00, 15.00, NULL, NULL),
(41, 'Meridian Place', 'Single Detached, Attached, Townhouse', 'Danessa, Bernice, Caroline', 'rfo', 'Filinvest', 1080000.00, 1940000.00, 2.50, 'medium', 25, 11, 'Brgy. Pasong Kawayan II, General Trias, Cavite', 'project_41_1_1750059463.jpg', 'project_41_2_1751512317.jpg', 'project_41_3_1751512317.jpg', 'project_41_4_1751512317.jpg', 'https://drive.google.com/drive/folders/14W0bTsdP8Ipa9cQbMuvDNCq5EfCEJU7u?usp=sharing', '', '2025-06-16 06:19:50', '2025-07-21 07:44:08', 2023000.00, 5000.00, NULL, NULL, NULL, 32883.00, 6),
(42, 'Pleasantfields', 'Townhouse', 'Kennedy, Lincoln, Nyxon', 'rfo', '650 Homes', 2700000.00, 3500000.00, 3.50, 'high', 17, 11, 'Barangay, Purok 3 Bukal Rd, Tanza, Cavite', 'project_42_1_1750056102.jpg', 'project_42_2_1750056102.jpeg', 'project_42_3_1750056102.jpeg', 'project_42_4_1750056102.jpeg', 'https://docs.google.com/document/d/1JIacA1FfzsT3WbwzSGqN0SZxC2AsvW9_U7H10VfIfwM/edit?tab=t.0', 'https://m.me/j/AbbyYHgjvFMCa_U3/', '2025-06-16 06:21:43', '2025-07-21 10:03:07', 2989000.00, 20000.00, NULL, 75000.00, NULL, 99000.00, NULL),
(43, 'Elisa Homes', 'Single Attached, Townhouse', 'Sapphire, Pearl, Dahlia, Canalily', 'rfo', 'F&E De Castro', 3225145.00, 10000000.00, 3.00, 'medium', 18, 11, 'Molino Rd, Molino 4, Bacoor Cavite', 'project_43_1_1750056162.jpg', 'project_43_2_1750056162.jpg', 'project_43_3_1750056162.jpg', 'project_43_4_1750056162.jpg', 'https://drive.google.com/drive/folders/1-0gjfMpjNVZgcUQkz2hyV7SS21Mth38P', '', '2025-06-16 06:24:55', '2025-07-18 09:22:12', 3225145.00, 20000.00, NULL, NULL, NULL, 35554.00, 36),
(44, 'LaVerne', 'Single Attached', 'Isabelle, Megan', 'ogc', '650 Homes', 7799999.00, 8500000.00, 3.50, 'medium', 18, 11, 'Habay 2, Bacoor, Cavite', 'project_44_1_1750060507.jpg', 'project_44_2_1750058945.jpg', 'project_44_3_1750058945.jpg', 'project_44_4_1750058945.jpg', 'https://docs.google.com/document/d/1ZLJbSblgKsWIaDE74qhOsUNH_1YtHwUcuA_SI7boD-U/edit?tab=t.0#heading=h.19hku0byct89', 'https://m.me/j/AbbyYHgjvFMCa_U3/', '2025-06-16 06:32:28', '2025-07-21 07:43:02', 4800000.00, 50000.00, NULL, NULL, NULL, 41875.00, 16),
(45, 'Kathleen Place 5', 'Townhouse', '', 'preselling', 'JKY', 5900000.00, 9000000.00, 2.50, 'medium', 18, 11, 'Gawaran Avenue Brgy. Molino 7, Bacoor, Cavite', 'project_45_1_1750060017.jpeg', 'project_45_2_1750060017.jpeg', 'project_45_3_1750056135.jpeg', 'project_45_4_1750056135.jpeg', 'https://drive.google.com/drive/folders/14lKiVhu0GIqE-itWkkHupejj49dTRK_Q', '', '2025-06-16 06:33:58', '2025-07-11 03:03:27', 5995554.00, 50000.00, NULL, NULL, NULL, 30530.00, 18),
(48, 'The Granary', '', '', 'rfo', 'Haus Talk Inc.', 3200000.00, 5400000.00, 3.25, 'medium', 30, 13, 'San Antonio, Biñan City, Laguna', 'image1_1753771145_1728.png', 'project_6865ecceb3be3_2.avif', 'project_6865ecceb3ebb_3.avif', NULL, '', '', '2025-07-03 02:37:02', '2025-07-29 06:39:05', 3200000.00, 10000.00, NULL, 44000.00, NULL, NULL, NULL),
(49, 'Beverly Homes', 'Townhouse, Duplex, Rowhouse', 'Catherine, Althea, Nicolette, Neo Catherine', 'rfo', 'Newhall Realty Group Corp', 1600000.00, 6200000.00, 5.00, 'medium', 31, 15, '', 'project_49_1_1751540987.jpg', 'project_49_2_1751541449.jpg', 'project_49_3_1751541449.jpg', 'project_49_4_1751541449.jpg', '', '', '2025-07-03 02:48:56', '2025-07-11 09:00:04', 1970000.00, 10000.00, NULL, NULL, NULL, 16645.00, 24),
(50, 'Parc Royal', '2-Full Storey', 'Audrey, Chesca', 'rfo', 'Masaito', 2299000.00, 4870000.00, 3.50, 'medium', 24, 11, 'San Sebastian Advincula Avenue Kawit, Cavite', 'project_6865f1780cfab_1.jpg', 'project_50_2_1751512984.jpg', 'project_50_3_1751512984.jpg', NULL, '', '', '2025-07-03 02:56:56', '2025-07-21 08:00:30', 4870000.00, NULL, NULL, 75514.00, NULL, NULL, NULL),
(53, 'Monte Royale', '', 'Felicity', 'rfo', 'Masaito', 2000000.00, 4700000.00, 3.50, 'high', 24, 11, '', 'project_68661190a45a5_1.webp', 'project_68661190a6468_2.jpg', NULL, NULL, '', '', '2025-07-03 05:13:52', '2025-07-18 09:25:25', 1840000.00, 10000.00, NULL, NULL, 10.00, 8700.00, 20),
(54, 'El Palazzo Heights', '', 'Daniella', 'rfo', 'Masaito', 3000000.00, 3259300.00, 3.50, 'medium', 16, 11, '', 'project_686612a14e427_1.jpg', NULL, 'project_686612a14ecad_3.jpg', NULL, '', '', '2025-07-03 05:18:25', '2025-07-21 07:34:06', 2495900.00, 10000.00, NULL, 40566.00, NULL, 15972.00, 15),
(55, 'Treelane', '', '', 'rfo', 'Charles Builders', 3645000.00, 4416000.00, 5.00, 'medium', 19, 11, '', 'image1_1753770898_6572.png', 'project_686613a447b70_2.avif', 'image3_1753770898_3555.png', NULL, '', '', '2025-07-03 05:22:44', '2025-07-29 06:34:58', 228000.00, 5000.00, NULL, NULL, 15.00, 52434.00, 12),
(56, 'Amaia Scapes', 'Townhouse, 2–3 bedrooms typical', 'Single Home, Twin Home, Bungalow Pod, Twin Pod, Multi Pod', 'rfo', 'Amaia Land Corp', 2500000.00, 6700000.00, 5.00, 'medium', 16, 11, 'Conchu Rd, Trece Martires City, Cavite', 'project_68665f3f6fbe4_1.jpg', 'project_68665f3f7008a_2.jpg', 'project_68665f3f70359_3.jpg', 'project_68665f3f7059b_4.jpg', '', '', '2025-07-03 10:45:19', '2025-07-21 07:24:10', 4725678.00, 25000.00, NULL, NULL, NULL, 25468.00, 6),
(57, 'Erinville Homes', 'Duplex, Single Attached', '', 'rfo', '1988 Devt Corp', 2500000.00, 2700000.00, 3.50, 'medium', 16, 11, 'Brgy De Ocampo, Market Road, Trece Martires Cavite', 'project_6866632cbe616_1.jpg', 'project_6866632cbea5a_2.jpg', 'project_6866632cbecd4_3.jpg', 'project_6866632cbef3c_4.jpg', '', '', '2025-07-03 11:02:04', '2025-07-21 07:39:06', 2497000.00, 10000.00, NULL, 40034.00, NULL, 38767.00, 12),
(58, 'Naic Country Homes', 'Townhouse, Rowhouses, Single‑Attached, Duplex', 'Jasmine', 'rfo', 'Axeia', 1660000.00, 2060000.00, 5.00, 'medium', 23, 11, 'Barangay Malainen-Luma, Naic, Cavite', 'project_68666b0d25ffb_1.jpg', 'project_68666b0d262a5_2.jpg', 'project_68666b0d26522_3.jpg', 'project_68666b0d267c6_4.jpg', 'https://drive.google.com/drive/folders/1f_zD-bAhP0dJgarLHL6NnWHd1QzWT37d', '', '2025-07-03 11:35:41', '2025-07-21 07:55:33', 3181000.00, 12500.00, NULL, NULL, NULL, 12982.00, 28),
(59, 'Mozzafiato', 'Lot-only (no houses)', 'Lot', 'preselling', 'ActiveLand', 1000000.00, 7000000.00, 5.00, 'medium', 27, 14, 'Bgy Alangilan, Balete, Batangas', 'project_68667087b7efd_1.jpg', 'project_68667087b8328_2.jpg', 'project_68667087b877f_3.jpg', 'project_68667087b8e3a_4.jpg', '', '', '2025-07-03 11:59:03', '2025-07-11 08:48:27', 6000000.00, 50000.00, NULL, NULL, 25.00, 69812.00, 24),
(60, 'Porto Laiya', 'Residential, Commercial', 'Lot-only', '', 'Active Realty Development Corp', 8320000.00, 17300000.00, 5.00, 'medium', 32, 14, 'Brgy. Laiya, San Juan, Batangas', 'project_68667503a63bf_1.jpg', 'project_68667503a6698_2.jpg', 'project_68667503a6c40_3.jpg', 'project_68667503a6fb5_4.jpg', '', '', '2025-07-03 12:18:11', '2025-07-29 09:07:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(61, 'Paseo de Lipa', 'Single-attached, Town Homes', 'Larissa (Madeira Phase 2), Levana (Estella Phase 1)', 'rfo', 'Citihomes Builder & Development Inc', 3630000.00, 6100000.00, 3.50, 'medium', 29, 14, 'Brgy. Mabini, Quezon, and Adja, Lipa City, Batangas', 'project_686679d4286de_1.jpg', 'project_686679d428ca0_2.jpg', 'project_686679d429049_3.jpg', 'project_686679d42949c_4.jpg', '', '', '2025-07-03 12:38:44', '2025-07-21 08:03:46', 5860750.00, NULL, NULL, NULL, NULL, 90000.00, NULL),
(62, 'Idesia LIPA', 'Single Detached, Townhouse', 'Aria', 'rfo', 'P.A. Properties and Hankyu Hanshin Properties Corp.', 2850000.00, 7040000.00, 5.00, 'medium', 29, 14, 'Brgy. Inosluban, Lipa City Batangas', 'project_68667cf189655_1.jpg', 'project_68667cf1899ad_2.jpg', 'project_68667cf189d05_3.jpg', 'project_68667cf189fb2_4.jpg', '', '', '2025-07-03 12:52:01', '2025-07-21 07:41:39', 2856008.00, 57000.00, NULL, NULL, 10.00, 16841.00, 18),
(63, 'Monde Residences', '2-Storey Detached and Attached Homes', 'Alona, Bea, Clarissa, Elirah', 'rfo', 'Maaba Group', 10130000.00, 15100000.00, 5.00, 'medium', 33, 11, 'Congressional Road, Brgy. Salitran 3, Dasmariñas City, Cavite', 'project_68679b58c68bc_1.jpg', 'project_68679b58c6cb2_2.jpg', 'project_68679b58c6f4d_3.jpg', 'project_68679b58c7178_4.jpg', '', '', '2025-07-04 09:14:00', '2025-07-21 07:49:31', 7200000.00, 100000.00, NULL, 165000.00, NULL, 113750.00, 12),
(64, 'Avida Verra Settings Vermosa', 'Townhouses, Single-Detached, House and Lot', 'Macy, Trista', 'preselling', 'Avida Land', 8262000.00, 14682000.00, 5.00, 'medium', 19, 11, 'Brgy. Pasong Buaya II, Imus City, Cavite', 'project_6867a112cd254_1.jpg', 'project_6867a112cd63b_2.jpg', 'project_6867a112cd971_3.jpg', 'project_6867a112cdfa8_4.jpg', '', '', '2025-07-04 09:38:26', '2025-07-21 07:30:08', 9740268.00, 25000.00, NULL, NULL, NULL, 39568.00, 16),
(65, 'Micara Estates', 'Townhouse', 'Portia, Felicia', 'rfo', 'Profriends', 1492000.00, 1963000.00, 5.00, 'medium', 17, 11, 'Antero Soriano Highway, Brgy. Sahud Ulan, Tanza, Cavite 4108', 'project_6867a528346df_1.jpg', 'project_6867a528349cd_2.jpg', 'project_6867a52834bcd_3.jpg', 'project_6867a52834dc8_4.jpg', '', '', '2025-07-04 09:55:52', '2025-07-29 05:12:49', 1721800.00, 10000.00, NULL, NULL, NULL, 13729.00, 18),
(66, 'Emerald Residences', 'Two-Storey Townhouses', '', 'rfo', 'Charles Builders', 1330000.00, 2052000.00, 5.00, 'medium', 17, 11, 'Along Cesa Road, Brgy. Sahud Ulan, Tanza, Cavite', 'project_6867a921a7fa7_1.jpg', 'project_6867a921a848e_2.jpg', 'project_6867a921a88de_3.jpg', 'project_6867a921a8d00_4.jpg', '', '', '2025-07-04 10:12:49', '2025-07-29 05:04:40', 2052000.00, 5000.00, NULL, 33000.00, NULL, 11167.00, 18),
(67, 'Shore Residences', '', '', 'rfo', 'SMDC', 6500000.00, 24800000.00, 5.00, 'low', 34, 16, 'Seaside Blvd. and Sunrise Drive, Mall of Asia Complex, Pasay City', 'project_686b23965f58a_1.jpg', 'project_686b23965f9c7_2.jpg', NULL, NULL, '', '', '2025-07-07 01:32:06', '2025-07-07 01:43:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(68, 'Glam Residences', '', '', 'rfo', 'SMDC', 5700000.00, 13900000.00, 5.00, 'low', 35, 16, '', 'project_686b262fdc273_1.jpg', 'project_686b262fdca78_2.jpg', NULL, NULL, '', '', '2025-07-07 01:43:11', '2025-07-07 01:43:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(69, 'Jazz Residences', '', '', 'rfo', 'SMDC', 5800000.00, 9600000.00, 5.00, 'low', 36, 16, 'Jupiter Corner N. Garcia Sts., Bel-Air Makati City', 'project_686b284a7ec8d_1.png', 'project_686b284a7f476_2.webp', 'project_686b284a7f6bd_3.jpg', NULL, '', '', '2025-07-07 01:52:10', '2025-07-07 02:12:49', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(70, 'The Celandine', '', '', 'rfo', 'DMCI', 4009000.00, 7067000.00, 5.00, 'low', 35, 16, '', 'project_686b2a25d2397_1.jpg', 'project_686b2a25d2721_2.jpg', 'project_686b2a25d2a89_3.jpg', NULL, '', '', '2025-07-07 02:00:05', '2025-07-07 04:55:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(71, 'Mirea Residences', '', '', 'rfo', 'DMCI', 5827000.00, 9274000.00, 5.00, 'medium', 38, 16, 'Amang Rodriquez Ave., Brgy Santolan, Pasig City', 'project_686b2ab406a70_1.webp', 'project_686b2ab406f33_2.jpg', 'project_686b2ab4071dc_3.jpg', NULL, '', '', '2025-07-07 02:02:28', '2025-07-07 02:30:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(72, 'Lumiere Residences', '', '', 'rfo', 'DMCI', 5010000.00, 13986000.00, 5.00, 'low', 38, 16, 'Pasig Blvd., cor. Shaw Blvd., Pasig City', 'project_686b2b527bbc0_1.webp', 'project_686b2b527c036_2.jpeg', 'project_686b2b527c3cf_3.webp', NULL, '', '', '2025-07-07 02:05:06', '2025-07-07 04:54:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(73, 'Tivoli Garden Residences', '', '', 'rfo', 'DMCI', 5272000.00, 15941000.00, 5.00, 'low', 39, 16, 'Coronado Street, Brgy. Hulo, Mandaluyong City', 'project_686b2cce4e166_1.webp', 'project_686b2cce4e4e4_2.jpg', 'project_686b2cce4e84a_3.jpg', NULL, '', '', '2025-07-07 02:11:26', '2025-07-07 09:11:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(74, '(UPCOMING) Savia Parkway', '', '', 'preselling', '650 Homes', -0.01, -0.01, 2.50, 'low', 17, 11, '', 'project_74_1_1751854820.jpg', NULL, NULL, NULL, '', '', '2025-07-07 02:19:38', '2025-07-07 03:53:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(75, 'Amaia Scapes', '', '', 'rfo', 'AyalaLand', 4000000.00, 4800000.00, 5.00, 'medium', 25, 11, 'Arnaldo Hwy, General Trias, Cavite', 'project_686b368f86453_1.jpg', NULL, NULL, NULL, '', '', '2025-07-07 02:53:03', '2025-07-21 07:21:53', 3198000.00, 20000.00, NULL, NULL, 10.00, 14015.00, 24),
(76, 'Antel Grand Village', 'Brgy. Bacao 2, General Trias, Cavite', 'Audrey', 'rfo', 'Antel Land', 4100000.00, 21900000.00, 5.00, 'medium', 25, 11, 'Brgy. Bacao 2, General Trias, Cavite', 'project_686b39d87af3d_1.webp', 'project_686b39d87b24f_2.jpg', NULL, NULL, '', '', '2025-07-07 03:07:04', '2025-07-21 07:28:40', 2525050.00, 50000.00, NULL, NULL, 20.00, 19783.00, 23),
(77, 'Sapphire Residences', '', '', 'preselling', 'Charles Builders Group of Companies', 2200000.00, 2400000.00, 5.00, 'medium', 17, 11, 'Brgy. Santol, Tanza Cavite', 'project_686b3c398eb1c_1.jpg', 'project_686b3c398ee58_2.jpg', NULL, NULL, '', '', '2025-07-07 03:17:13', '2025-07-21 08:09:37', 2456000.00, 5000.00, NULL, 42000.00, NULL, 14462.00, 18),
(78, 'Lancaster New City', 'Townhouse', 'Thea', 'rfo', 'Profriends', 3700000.00, 3900000.00, 5.00, 'medium', 24, 11, '', 'project_686b3d9a71236_1.jpg', 'project_686b3d9a7188c_2.jpg', 'project_686b3d9a71cc8_3.jpg', 'project_686b3d9a7211b_4.jpg', '', '', '2025-07-07 03:23:06', '2025-07-29 05:12:21', 3708600.00, 15000.00, NULL, NULL, NULL, 22500.00, 21),
(80, '(UPCOMING) Berde Ville', '', '', 'preselling', 'Four Js', -0.01, -0.01, 6.00, 'low', 20, 12, '', 'project_686b4088ecb41_1.jpg', NULL, NULL, NULL, '', '', '2025-07-07 03:35:36', '2025-07-07 09:16:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(81, 'The Peak at Le Moubreza', '', '', 'rfo', 'Axeia', 2828000.00, 2879000.00, 5.00, 'medium', 40, 14, 'Brgy. San Antonio, Sto. Tomas, Batangas', 'project_686b4243721aa_1.webp', NULL, NULL, NULL, '', '', '2025-07-07 03:42:59', '2025-07-21 08:15:07', 2873000.00, 10500.00, NULL, 45563.00, NULL, 10490.00, 25),
(82, 'Tanauan Park Place Phase 3 & 4', '', 'Linea', 'rfo', 'Axeia', 1716000.00, 2592000.00, 5.00, 'medium', 41, 14, '', 'project_686b4456bdb5e_1.jpg', 'project_686b4456bdef9_2.jpg', NULL, NULL, '', '', '2025-07-07 03:51:50', '2025-07-21 08:12:08', 1784000.00, 6000.00, NULL, 34042.00, NULL, 7050.00, 20),
(83, 'Vista Rosa at San Francisco', '', '', 'rfo', 'Royale Properties Inc.', 5236000.00, 7015488.00, 5.00, 'medium', 30, 13, 'San Francisco Road, Brgy. San Francisco, Biñan City, Laguna', 'project_686b678ae46fd_1.webp', 'project_686b678ae4973_2.webp', NULL, NULL, '', '', '2025-07-07 06:22:02', '2025-07-21 08:22:29', 5236000.00, 20000.00, NULL, NULL, NULL, 20983.00, 24),
(84, 'Idesia Dasmariñas', '', '', 'preselling', 'P.A Properties', 3000000.00, 6631041.00, 5.00, 'high', 33, 11, 'Governor’s Drive Brgy. San Agustin, Dasmariñas, Cavite', 'project_686b6c7507c32_1.jpg', NULL, NULL, NULL, '', '', '2025-07-07 06:43:01', '2025-07-11 09:10:39', 6631041.00, 30000.00, NULL, 135706.00, NULL, 82282.00, 8),
(85, 'Axeia Dasmariñas', '', '', 'preselling', 'Axeia', 2300000.00, 2320000.00, 5.00, 'medium', 33, 11, '', 'project_686b6f12393f3_1.jpg', 'project_686b6f123968e_2.jpg', NULL, NULL, '', '', '2025-07-07 06:54:10', '2025-07-21 07:32:34', 2270000.00, 14500.00, NULL, NULL, NULL, 14500.00, 25),
(86, 'Green 2 Residences', '', '', 'rfo', 'SMDC', 3200000.00, 6200000.00, 5.00, 'low', 33, 11, '', 'project_686b7de33b6c8_1.jpg', 'project_686b7de33c002_2.jpg', NULL, NULL, '', '', '2025-07-07 07:57:23', '2025-07-07 07:57:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(87, '(UPCOMING) Newtowne Dasmariñas', '', '', 'preselling', 'Newtowne', -0.01, -0.01, 5.00, 'low', 33, 11, '', 'project_686b878058e30_1.jpg', NULL, NULL, NULL, '', '', '2025-07-07 08:38:24', '2025-07-07 08:38:40', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(88, 'test', '', '', 'rfo', 'test', 2000000.00, 25000000.00, 5.00, 'medium', 37, 16, '', 'image1_1752050366_7575.jpg', 'image2_1752050366_5300.jpg', 'image3_1752050366_4207.jpg', NULL, '', '', '2025-07-09 08:37:03', '2025-07-10 09:46:41', 25000000.00, 3000000.00, 4000000.00, 400000.00, 12.00, 50000.00, 12),
(89, 'Neuville', '', 'Astrid', 'rfo', 'Duraville Realty and Development Corporation', 3880000.00, 3960000.00, 5.00, 'high', 17, 11, '', 'image1_1752224923_5102.png', 'image2_1752224923_1001.jpg', NULL, NULL, '', '', '2025-07-10 03:01:44', '2025-07-14 06:40:06', 2000000.00, 27777.00, 2314.00, 12134.00, 12.00, 12144.00, 12),
(90, 'Southwind', 'Single-attached, Two-storey House, Bungalow', 'Banyan, Walnut', 'rfo', 'Filinvest Land', 4900000.00, 12100000.00, 5.00, 'medium', 42, 13, 'Brgy. San Antonio, San Pedro, Laguna', 'image1_1752124263_3263.gif', 'image2_1752124263_5816.jpg', 'image3_1752124263_2962.jpg', 'image4_1752124263_7068.jpg', '', '', '2025-07-10 05:11:03', '2025-07-21 08:11:00', 3700000.00, 30000.00, NULL, NULL, NULL, 47333.00, 15),
(91, 'Ventura Real', '2-Storey Single-attached', 'Sapphire', 'rfo', 'Filinvest Land Inc.', 2580000.00, 4680000.00, 5.00, 'medium', 43, 13, 'Brgy. Bubuyan, Calamba (Pueblo Solana), Laguna', 'image1_1752126030_1533.JPG', 'image2_1752126030_2447.JPG', 'image3_1752126030_2629.JPG', 'image4_1752126030_9853.JPG', '', '', '2025-07-10 05:40:30', '2025-07-21 08:20:38', 4962100.00, 20000.00, NULL, NULL, 10.00, 79369.00, 6),
(92, 'One Lancaster Park', 'Condominium', 'Studio, One-Bedroom, Two-Bedroom', 'preselling', 'Famtech Properties Inc.', 4480000.00, 5310000.00, 5.00, 'medium', 19, 11, 'Brgy. Alapan II‑B, Lancaster New City, Imus, Cavite', 'image1_1752130549_8930.jpg', 'image2_1752130549_3422.jpg', 'image3_1752130549_2969.jpg', 'image4_1752130549_4130.jpg', '', '', '2025-07-10 06:55:49', '2025-07-10 06:55:49', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(93, 'Richdale West Residences', 'Single Attached, Bungalow, Townhouse', 'Adeline, Carnation, Linnea, Jasmine (TH)', 'preselling', 'Axeia Development Corp', 2000000.00, 4299999.99, 5.00, 'medium', 25, 11, 'Barangay Panungyanan, General Trias, Cavite', 'image1_1752137822_3384.png', 'image2_1752137822_3494.png', 'image3_1752137822_4111.png', 'image4_1752137823_7468.png', '', '', '2025-07-10 08:57:03', '2025-07-21 08:07:22', 4995000.00, 20000.00, NULL, 76753.00, NULL, 22000.00, 26),
(94, 'Test project 2', '', '', 'rfo', 'Test', 920000.00, 1400000.00, 5.00, 'medium', 19, 11, '', 'image1_1752141515_6097.jpg', 'image2_1752141515_5540.jpg', NULL, NULL, '', '', '2025-07-10 09:58:35', '2025-07-10 09:59:16', 1400000.00, 50000.00, 400000.00, 500000.00, 12.00, 27400.00, 12);

-- --------------------------------------------------------

--
-- Table structure for table `project_models`
--

CREATE TABLE `project_models` (
  `id` int NOT NULL,
  `developer_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `base_price` decimal(12,2) DEFAULT '0.00',
  `floor_area` decimal(8,2) DEFAULT NULL,
  `lot_area` decimal(8,2) DEFAULT NULL,
  `bedrooms` int DEFAULT NULL,
  `bathrooms` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_models`
--

INSERT INTO `project_models` (`id`, `developer_id`, `name`, `description`, `base_price`, `floor_area`, `lot_area`, `bedrooms`, `bathrooms`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Alice', 'Premium residential unit', 2900000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(2, 1, 'Alexandra', 'Luxury family home', 8500000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(3, 1, 'Briana', 'Modern townhouse', 2700000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(4, 2, 'Antipolo Heights Model A', 'Scenic hillside property', 3200000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(5, 3, 'Kennedy', 'Family-oriented townhouse', 2700000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(6, 3, 'Lincoln', 'Spacious family home', 3500000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(8, 4, 'Bellefort Estate Model A', 'Luxury gated community home', 4500000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(9, 6, 'Sapphire', 'Affordable housing solution', 10000000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(10, 6, 'Pearl', 'Family townhouse', 10000000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(11, 7, 'Hana', 'Japanese-inspired modern living', 3100000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(12, 8, 'Paris', 'Contemporary urban development', 8000000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(13, 8, 'Sydney', 'Modern city living', 12000000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(14, 8, 'Tokyo', 'Urban lifestyle home', 14000000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(16, 9, 'Kathleen Place Model A', 'Mid-rise condominium', 5900000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(17, 10, 'Amora', 'Sustainable eco-friendly housing', 2300000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(18, 11, 'Way', 'Trusted quality development', 100000.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:18:08', '2025-06-18 07:18:08'),
(19, 1, 'Thea', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:25:03', '2025-06-18 07:25:03'),
(20, 3, 'Nixon', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-06-18 07:25:19', '2025-06-18 07:25:19'),
(21, 12, 'asdkl', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-03 05:55:08', '2025-07-03 05:55:08'),
(22, 12, 'One Way', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-03 05:55:08', '2025-07-03 05:55:08'),
(23, 11, 'Verra Settings', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-07 09:32:18', '2025-07-07 09:32:18'),
(24, 13, 'Helena', 'Townhouse Up and down', 1505000.00, NULL, NULL, NULL, NULL, 1, '2025-07-15 07:33:56', '2025-07-15 07:33:56'),
(25, 13, 'Halie', 'Rowhouse', 1200000.00, NULL, NULL, NULL, NULL, 1, '2025-07-15 07:33:56', '2025-07-15 07:33:56'),
(26, 6, 'Dahlia', ' 2-story townhouse', 3200000.00, NULL, NULL, NULL, NULL, 1, '2025-07-15 07:37:45', '2025-07-15 07:37:45'),
(27, 6, 'Canalily', '2-story townhouse end unit', 3200000.00, NULL, NULL, NULL, NULL, 1, '2025-07-15 07:37:45', '2025-07-15 07:37:45'),
(28, 30, 'Tokyo', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:04:45', '2025-07-18 10:04:45'),
(29, 30, 'New York', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:05:55', '2025-07-18 10:05:55'),
(30, 30, 'Paris', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:05:55', '2025-07-18 10:05:55'),
(31, 30, 'California', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:05:55', '2025-07-18 10:05:55'),
(32, 30, 'Nevada', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:05:55', '2025-07-18 10:05:55'),
(33, 30, 'Florida', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:05:55', '2025-07-18 10:05:55'),
(34, 30, 'Sofia', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:05:55', '2025-07-18 10:05:55'),
(35, 30, 'Daniella', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:05:55', '2025-07-18 10:05:55'),
(36, 30, 'Isabel', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:05:55', '2025-07-18 10:05:55'),
(37, 8, 'New York', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:07:58', '2025-07-18 10:07:58'),
(38, 54, 'Abbie', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:15:18', '2025-07-18 10:15:18'),
(39, 54, 'Brenda', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:15:18', '2025-07-18 10:15:18'),
(40, 54, 'Chelsea', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:15:18', '2025-07-18 10:15:18'),
(41, 55, 'Gabby', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:20:26', '2025-07-18 10:20:26'),
(42, 55, 'Felicity', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:20:26', '2025-07-18 10:20:26'),
(43, 56, 'Era', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:03', '2025-07-18 10:21:03'),
(44, 56, 'Danna', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:03', '2025-07-18 10:21:03'),
(45, 56, 'Bea', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:03', '2025-07-18 10:21:03'),
(46, 58, 'Chesca', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:22', '2025-07-18 10:21:22'),
(47, 58, 'Audrey', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:22', '2025-07-18 10:21:22'),
(48, 57, 'Chesca', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:53', '2025-07-18 10:21:53'),
(49, 57, 'Audrey', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:53', '2025-07-18 10:21:53'),
(50, 57, 'Era', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:53', '2025-07-18 10:21:53'),
(51, 57, 'Danna', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:53', '2025-07-18 10:21:53'),
(52, 57, 'Felicity', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-07-18 10:21:53', '2025-07-18 10:21:53'),
(53, 19, 'Lot Only', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-09-24 02:20:01', '2025-09-24 02:20:01'),
(54, 59, 'Unit', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-11-12 04:18:17', '2025-11-12 04:18:17'),
(55, 60, '.', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2025-11-15 00:24:26', '2025-11-15 00:24:26'),
(56, 62, 'Bettina', NULL, 0.00, NULL, NULL, NULL, NULL, 1, '2026-04-10 06:34:15', '2026-04-10 06:34:15');

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`id`, `name`, `region`, `created_at`) VALUES
(11, 'Cavite', NULL, '2025-06-16 03:46:11'),
(12, 'Rizal', NULL, '2025-06-16 05:13:20'),
(13, 'Laguna', NULL, '2025-06-16 05:39:43'),
(14, 'Batangas', NULL, '2025-07-03 01:58:43'),
(15, 'Bulacan', NULL, '2025-07-03 01:59:27'),
(16, 'NCR', NULL, '2025-07-07 01:27:05');

-- --------------------------------------------------------

--
-- Table structure for table `raffle_tickets`
--

CREATE TABLE `raffle_tickets` (
  `ticket_id` int NOT NULL,
  `user_id` int NOT NULL,
  `lead_id` int NOT NULL,
  `ticket_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `email_address` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `team_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `stage_source` varchar(100) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `raffle_tickets`
--

INSERT INTO `raffle_tickets` (`ticket_id`, `user_id`, `lead_id`, `ticket_number`, `full_name`, `phone_number`, `email_address`, `team_id`, `created_at`, `stage_source`) VALUES
(1, 58, 27, 'LMS001', 'Zjosev Atienza', '09171520934', 'ginine.innersparc@gmail.com', 13, '2025-09-20 07:58:03', 'Lead Status: Negotiation'),
(2, 31, 42, 'LMS002', 'Manny Alberto Violenta', '09622113506', 'violentamanny@gmail.com', 12, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(3, 9, 46, 'LMS003', 'Alvin  Llaneta', '09613825054', 'alvinllaneta8@gmail.com', 2, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(4, 24, 48, 'LMS004', 'Joan Mahinay Barceta', '09649589052', 'jobarceta@gmail.com', 2, '2025-09-20 07:58:03', 'Lead Status: Lost'),
(5, 13, 49, 'LMS005', 'Leniza Flores pasion', '09560149128', '', 1, '2025-09-20 07:58:03', 'Lead Status: Requirement Stage'),
(6, 12, 50, 'LMS006', 'Gabcyrose B. Del Socorro', '09605666637', '', 2, '2025-09-20 07:58:03', 'Lead Status: Negotiation'),
(7, 55, 57, 'LMS007', 'John Paul Perfecto', '09602531452', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(8, 58, 58, 'LMS008', 'Zjosev Atienza', '09933892608', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(9, 55, 62, 'LMS009', 'John Paul Perfecto', '09063770476', 'mafatimacapada@gmail.com', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(10, 55, 63, 'LMS010', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(11, 56, 64, 'LMS011', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(12, 58, 65, 'LMS012', 'Zjosev Atienza', '09933892608', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(13, 55, 66, 'LMS013', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(14, 55, 67, 'LMS014', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(15, 55, 68, 'LMS015', 'John Paul Perfecto', '09610176827', 'mttvicente.sbcm@gmail.com', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(16, 56, 69, 'LMS016', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(17, 55, 70, 'LMS017', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(18, 58, 71, 'LMS018', 'Zjosev Atienza', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(19, 55, 72, 'LMS019', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(20, 55, 73, 'LMS020', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(21, 55, 74, 'LMS021', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(22, 55, 75, 'LMS022', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(23, 56, 76, 'LMS023', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(24, 56, 77, 'LMS024', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(25, 55, 78, 'LMS025', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(26, 56, 79, 'LMS026', 'Marielle Pablea', '09171149002', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(27, 58, 81, 'LMS027', 'Zjosev Atienza', '09933892608', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(28, 58, 82, 'LMS028', 'Zjosev Atienza', '09933892608', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(29, 56, 83, 'LMS029', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(30, 58, 84, 'LMS030', 'Zjosev Atienza', '09933892608', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(31, 56, 85, 'LMS031', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(32, 56, 86, 'LMS032', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(33, 58, 87, 'LMS033', 'Zjosev Atienza', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(34, 56, 88, 'LMS034', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(35, 58, 89, 'LMS035', 'Zjosev Atienza', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(36, 56, 90, 'LMS036', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(37, 58, 91, 'LMS037', 'Zjosev Atienza', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(38, 56, 92, 'LMS038', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(39, 56, 93, 'LMS039', 'Marielle Pablea', '09725875509', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(40, 56, 94, 'LMS040', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(41, 56, 95, 'LMS041', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(42, 56, 96, 'LMS042', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(43, 56, 97, 'LMS043', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(44, 56, 98, 'LMS044', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(45, 56, 99, 'LMS045', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(46, 56, 100, 'LMS046', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(47, 56, 101, 'LMS047', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(48, 56, 102, 'LMS048', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(49, 56, 103, 'LMS049', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(50, 56, 104, 'LMS050', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(51, 56, 105, 'LMS051', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(52, 56, 106, 'LMS052', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(53, 56, 107, 'LMS053', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(54, 56, 108, 'LMS054', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(55, 56, 109, 'LMS055', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(56, 56, 110, 'LMS056', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(57, 56, 111, 'LMS057', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(58, 56, 112, 'LMS058', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(59, 56, 113, 'LMS059', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Negotiation'),
(60, 56, 114, 'LMS060', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(61, 56, 115, 'LMS061', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(62, 56, 116, 'LMS062', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(63, 56, 117, 'LMS063', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(64, 56, 118, 'LMS064', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(65, 56, 119, 'LMS065', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(66, 56, 120, 'LMS066', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(67, 56, 121, 'LMS067', 'Marielle Pablea', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(68, 56, 122, 'LMS068', 'Marielle Pablea', '09569331552', 'rachelronelle@gmail.com', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(69, 56, 123, 'LMS069', 'Marielle Pablea', '09569331552', 'rachelronelle@gmail.com', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(70, 56, 124, 'LMS070', 'Marielle Pablea', '09569331552', 'rachelronelle@gmail.com', 13, '2025-09-20 07:58:03', 'Lead Status: Negotiation'),
(71, 32, 126, 'LMS071', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: House Turn Over'),
(72, 32, 127, 'LMS072', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: House Turn Over'),
(73, 32, 128, 'LMS073', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(74, 55, 130, 'LMS074', 'John Paul Perfecto', '09922930323', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(75, 55, 131, 'LMS075', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(76, 55, 132, 'LMS076', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(77, 55, 133, 'LMS077', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(78, 55, 134, 'LMS078', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(79, 55, 135, 'LMS079', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(80, 55, 136, 'LMS080', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(81, 55, 137, 'LMS081', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(82, 55, 138, 'LMS082', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(83, 13, 151, 'LMS083', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(84, 13, 152, 'LMS084', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(85, 13, 153, 'LMS085', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(86, 13, 154, 'LMS086', 'Leniza Flores pasion', '09150610183', '', 1, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(87, 13, 155, 'LMS087', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(88, 13, 156, 'LMS088', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Negotiation'),
(89, 13, 157, 'LMS089', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(90, 13, 158, 'LMS090', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(91, 13, 159, 'LMS091', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(92, 13, 160, 'LMS092', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(93, 13, 161, 'LMS093', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(94, 13, 162, 'LMS094', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(95, 13, 163, 'LMS095', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(96, 13, 164, 'LMS096', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(97, 13, 165, 'LMS097', 'Leniza Flores pasion', '', '', 1, '2025-09-20 07:58:03', 'Lead Status: Negotiation'),
(98, 55, 169, 'LMS098', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(99, 55, 172, 'LMS099', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(100, 55, 173, 'LMS100', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(101, 55, 174, 'LMS101', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(102, 55, 175, 'LMS102', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(103, 55, 176, 'LMS103', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(104, 55, 177, 'LMS104', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(105, 55, 178, 'LMS105', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(106, 55, 179, 'LMS106', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(107, 55, 180, 'LMS107', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(108, 55, 181, 'LMS108', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(109, 55, 182, 'LMS109', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(110, 55, 183, 'LMS110', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(111, 55, 184, 'LMS111', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(112, 55, 185, 'LMS112', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(113, 55, 186, 'LMS113', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(114, 55, 187, 'LMS114', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(115, 55, 188, 'LMS115', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(116, 55, 189, 'LMS116', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(117, 55, 190, 'LMS117', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(118, 55, 191, 'LMS118', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(119, 55, 192, 'LMS119', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(120, 55, 193, 'LMS120', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(121, 55, 194, 'LMS121', 'John Paul Perfecto', '09150334080', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(122, 55, 195, 'LMS122', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(123, 55, 196, 'LMS123', 'John Paul Perfecto', '', '', 13, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(124, 24, 197, 'LMS124', 'Joan Mahinay Barceta', '', '', 2, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(125, 24, 198, 'LMS125', 'Joan Mahinay Barceta', '', '', 2, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(126, 24, 199, 'LMS126', 'Joan Mahinay Barceta', '', '', 2, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(127, 105, 202, 'LMS127', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Lead Status: Closed Deal'),
(128, 15, 203, 'LMS128', 'Mark Christian Patigayon', '', '', 8, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(129, 15, 204, 'LMS129', 'Mark Christian Patigayon', '', '', 8, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(130, 32, 205, 'LMS130', 'Annalyn Salting Violenta', '', 'vincetamio52@gmail.com', 12, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(131, 32, 206, 'LMS131', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(132, 32, 207, 'LMS132', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(133, 32, 208, 'LMS133', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: Downpayment Stage'),
(134, 32, 209, 'LMS134', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: Downpayment Stage'),
(135, 32, 210, 'LMS135', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: Downpayment Stage'),
(136, 32, 211, 'LMS136', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: Site Tour'),
(137, 8, 212, 'LMS137', 'Charlene Dellosa', '', '', 8, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(138, 8, 213, 'LMS138', 'Charlene Dellosa', '', '', 8, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(140, 20, 215, 'LMS140', 'Erwin Gonzales Baguioan', '09669633188', 'irwindgonzales6@gmail.com', 1, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(141, 8, 216, 'LMS141', 'Charlene Dellosa', '09215700895', 'jerome.padolina1998@gmail.com', 8, '2025-09-20 07:58:03', 'Lead Status: Requirement Stage'),
(142, 8, 217, 'LMS142', 'Charlene Dellosa', '', '', 8, '2025-09-20 07:58:03', 'Lead Status: Presentation Stage'),
(143, 30, 218, 'LMS143', 'Arlene Umali', '', '', NULL, '2025-09-20 07:58:03', 'Lead Status: Negotiation'),
(144, 30, 219, 'LMS144', 'Arlene Umali', '', '', NULL, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(145, 30, 220, 'LMS145', 'Arlene Umali', '', '', NULL, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(146, 30, 221, 'LMS146', 'Arlene Umali', '', '', NULL, '2025-09-20 07:58:03', 'Lead Status: Inquiry'),
(147, 32, 222, 'LMS147', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Lead Status: Downpayment Stage'),
(151, 32, 128, 'LMS151', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'DP Stage: 1'),
(152, 32, 128, 'LMS152', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Requirement: Requirements Complete'),
(153, 32, 128, 'LMS153', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Requirement: Pag-IBIG Bank Approval'),
(154, 32, 128, 'LMS154', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Requirement: Loan Takeout'),
(155, 32, 128, 'LMS155', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Requirement: Turnover'),
(156, 32, 128, 'LMS156', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Spot DP: 1/6 months'),
(157, 32, 128, 'LMS157', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Spot DP: 2/6 months'),
(158, 32, 128, 'LMS158', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Spot DP: 3/6 months'),
(159, 32, 128, 'LMS159', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Spot DP: 4/6 months'),
(160, 32, 128, 'LMS160', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Spot DP: 5/6 months'),
(161, 32, 128, 'LMS161', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Spot DP: 6/6 months'),
(162, 105, 202, 'LMS162', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'DP Stage: 1'),
(163, 105, 202, 'LMS163', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Requirement: Requirements Complete'),
(164, 105, 202, 'LMS164', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Requirement: Pag-IBIG Bank Approval'),
(165, 105, 202, 'LMS165', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Requirement: Loan Takeout'),
(166, 105, 202, 'LMS166', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Requirement: Turnover'),
(167, 105, 202, 'LMS167', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Spot DP: 1/6 months'),
(168, 105, 202, 'LMS168', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Spot DP: 2/6 months'),
(169, 105, 202, 'LMS169', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Spot DP: 3/6 months'),
(170, 105, 202, 'LMS170', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Spot DP: 4/6 months'),
(171, 105, 202, 'LMS171', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Spot DP: 5/6 months'),
(172, 105, 202, 'LMS172', 'Sample Agent', '09999990990', 'juansantos@gmail.com', 1, '2025-09-20 07:58:03', 'Spot DP: 6/6 months'),
(173, 32, 208, 'LMS173', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'DP Stage: 1'),
(174, 32, 209, 'LMS174', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'DP Stage: 1'),
(175, 32, 209, 'LMS175', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Requirement: Requirements Complete'),
(176, 32, 210, 'LMS176', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'DP Stage: 1'),
(177, 32, 210, 'LMS177', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Requirement: Requirements Complete'),
(188, 32, 222, 'LMS188', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'DP Stage: 1'),
(189, 32, 222, 'LMS189', 'Annalyn Salting Violenta', '', '', 12, '2025-09-20 07:58:03', 'Requirement: Requirements Complete');

-- --------------------------------------------------------

--
-- Table structure for table `recruitment_leads`
--

CREATE TABLE `recruitment_leads` (
  `id` int NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `contact_number` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recruiter_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('Inquiry','Accreditation','Assessment','Product Knowledge System','Site tour','Monday 9am Meeting','Onboarding','Active','Inactive') COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `agent_onboarding_status` enum('Recruitment','Pre-Recruitment') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `recruiter_id` int DEFAULT NULL,
  `recruiter_team_id` int DEFAULT NULL,
  `pre_assessment` tinyint(1) DEFAULT '0',
  `accreditation` tinyint(1) DEFAULT '0',
  `assessment` tinyint(1) DEFAULT '0',
  `sales_training` tinyint(1) DEFAULT '0',
  `site_tour` tinyint(1) DEFAULT '0',
  `onboarding` tinyint(1) DEFAULT '0',
  `habit_forming` tinyint(1) DEFAULT '0',
  `digital_training` tinyint(1) DEFAULT '0',
  `sales_training_materials` tinyint(1) DEFAULT '0',
  `objection_handling` tinyint(1) DEFAULT '0',
  `VAST` tinyint(1) DEFAULT '0',
  `sales_monitoring` tinyint(1) DEFAULT '0',
  `LMS` tinyint(1) DEFAULT '0',
  `comm_structure` tinyint(1) DEFAULT '0',
  `terminologies` tinyint(1) DEFAULT '0',
  `focus_projects` tinyint(1) DEFAULT '0',
  `onboarding_status` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recruitment_leads`
--

INSERT INTO `recruitment_leads` (`id`, `timestamp`, `full_name`, `contact_number`, `email`, `recruiter_name`, `status`, `source`, `agent_onboarding_status`, `remarks`, `created_at`, `updated_at`, `recruiter_id`, `recruiter_team_id`, `pre_assessment`, `accreditation`, `assessment`, `sales_training`, `site_tour`, `onboarding`, `habit_forming`, `digital_training`, `sales_training_materials`, `objection_handling`, `VAST`, `sales_monitoring`, `LMS`, `comm_structure`, `terminologies`, `focus_projects`, `onboarding_status`) VALUES
(26, '2025-07-22 13:03:21', 'Jericho Santiago', '0960-367-2857', 'sjericho2822@gmail.com', 'Charlene Dellosa', 'Active', 'Charlene Dellosa', NULL, '', '2025-07-22 13:03:21', '2025-07-31 05:14:13', 8, 8, 1, 1, 1, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(27, '2025-07-22 13:09:52', 'Estela Caayaman', '0906-480-9826', 'estelacaayaman10@gmail.com', 'Charlene Dellosa', 'Active', 'Arlene L Umali', NULL, '', '2025-07-22 07:00:00', '2025-10-03 00:46:56', 8, 8, 1, 1, 1, 1, 1, 0, 1, 1, 1, 0, 0, 0, 1, 0, 0, 1, 0),
(30, '2025-07-29 08:30:32', 'Cynthia Martin', '09649080029', 'martincynthia9210@gmail.com', 'Manny Alberto Violenta', 'Active', 'Annalyn Salting Violenta', NULL, 'Endorsed by Annalyn Salting', '2025-05-07 07:00:00', '2025-09-23 09:42:02', 31, 12, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 0),
(31, '2025-07-29 08:46:30', 'Marco Arellano', '09999999999', 'marxjaoarellano@gmail.com', 'Gabriel Jr. Villamor Libacao', 'Active', 'Gabriel Jr. Villamor Libacao', NULL, '', '2025-03-05 19:39:00', '2025-07-31 03:42:46', 19, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(32, '2025-07-29 08:46:44', 'Emilyn Cantuba', '0999-999-9999', 'cantubaemhie@gmail.com', 'Joan Mahinay Barceta', 'Active', 'Joan Mahinay Barceta', NULL, '', '2025-03-06 19:39:00', '2025-07-31 03:43:37', 24, 2, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(33, '2025-07-29 08:48:13', 'Jhune Mark Abello', '09999999999', 'jmarkabello@gmail.com', 'Manny Alberto Violenta', 'Active', 'Manny Alberto Violenta', NULL, 'Referred by sir Gab', '2025-02-25 19:39:00', '2025-07-31 03:41:55', 31, 12, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(34, '2025-07-29 08:48:18', 'Efren Lisondra Bualat', '0999-999-9999', 'efrenbualat.22@gmail.com', 'Manny Alberto Violenta', 'Active', 'Novelyn Macalam  Bualat', NULL, '', '2025-03-09 18:39:00', '2025-07-31 03:44:25', 31, 12, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(35, '2025-07-29 08:50:39', 'Rionel Amonggo', '0999-999-9999', 'rionelamonggo6@gmail.com', 'Joan Mahinay Barceta', 'Active', 'Joan Mahinay Barceta', NULL, 'Endorsed by Rhaynon Amonggo', '2025-03-09 18:39:00', '2025-07-31 03:46:27', 24, 2, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(36, '2025-07-29 08:50:57', 'Delrica G Lagumbay ', '0926-749-2714', 'lagumbayrica@gmail.com', 'Erwin Gonzales Baguioan', 'Active', 'Leniza Flores pasion', NULL, '', '2025-07-11 08:50:57', '2025-07-31 05:06:34', 20, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(37, '2025-07-29 08:51:53', 'Arron Flores', '09972506239', 'arronflores1106@gmail.com', 'Leniza Flores pasion', 'Active', 'Leniza Flores pasion', NULL, '', '2025-07-11 08:51:53', '2025-07-31 05:05:52', 13, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(38, '2025-07-29 08:52:05', 'Ailyn L. De Torres', '09501409792', 'ailyndetorres8@gmail.com', 'Joan Mahinay Barceta', 'Active', 'Alvin  Llaneta', NULL, '', '2025-02-10 19:37:00', '2025-07-31 03:38:31', 24, 2, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(39, '2025-07-29 08:52:32', 'Janine Ricafrente ', '0992-737-2736 ', 'jhadenricafrente22@gmail.com', 'Gabriel Jr. Villamor Libacao', 'Active', 'Gabriel Jr. Villamor Libacao', NULL, '', '2025-07-05 08:52:32', '2025-07-31 05:05:03', 19, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(40, '2025-07-29 08:52:47', 'Reymark Rubio', '09554809710', 'reymarkrubio1011@gmail.com', 'Manny Alberto Violenta', 'Active', 'Manny Alberto Violenta', NULL, 'Endorsed by Jhoy M. Santos', '2025-03-27 07:00:00', '2025-09-23 09:53:14', 31, 12, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(41, '2025-07-29 08:54:01', 'Joy Tugano', '09512843796', 'joytugano1984@gmail.com', 'Manny Alberto Violenta', 'Active', 'Manny Alberto Violenta', NULL, '', '2025-06-28 08:54:01', '2025-07-31 05:04:29', 31, 12, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(42, '2025-07-29 08:54:03', 'John Alvin N. Villagene', '09177020896', 'johnalvin.villagen@benilde.edu.ph', 'Erwin Gonzales Baguioan', 'Active', 'Leniza Flores pasion', NULL, '', '2025-03-28 18:39:00', '2025-07-31 03:48:43', 20, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(43, '2025-07-29 08:54:43', 'Michael John Agenar', '09953279441', 'michaeljohnsantosagenar@gmail.com', 'Joan Mahinay Barceta', 'Inactive', 'Joan Mahinay Barceta', NULL, '', '2025-07-29 07:00:00', '2025-09-20 07:06:01', 24, 2, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(44, '2025-07-29 08:55:13', 'Reynante Tugano', '09507366004', 'reynantevargastugano13@gmail.com', 'Manny Alberto Violenta', 'Active', 'Manny Alberto Violenta', NULL, '', '2025-06-28 08:55:13', '2025-07-31 05:03:48', 31, 12, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(45, '2025-07-29 08:55:19', 'Monica Nidea', '09553847344', 'nideamonica010@gmail.com', 'Joan Mahinay Barceta', 'Active', 'Alvin  Llaneta', NULL, '', '2025-04-03 18:39:00', '2025-07-31 03:50:29', 24, 2, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(46, '2025-07-29 08:57:48', 'Richmond M Rili', '09560997659', 'manuelrichmond22@gmail.com', 'Joan Mahinay Barceta', 'Active', 'Joan Mahinay Barceta', NULL, '', '2025-04-05 18:50:00', '2025-07-31 03:51:02', 24, 2, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(47, '2025-07-29 08:59:01', 'Arian F. Ramos', '09157483117', 'ramos.arianfabila@gmail.com', 'Erwin Gonzales Baguioan', 'Active', 'Erwin Gonzales Baguioan', NULL, 'Endorsed by Eden Rose R. Ramos', '2025-04-16 18:50:00', '2025-07-31 03:51:51', 20, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0),
(50, '2025-07-30 13:45:47', 'Jhon Sherwin Jayme', '0999-999-9999', 'jhonsherwinjayme@gmail.com', 'Manny Alberto Violenta', 'Inactive', 'Manny Alberto Violenta', NULL, '', '2025-02-12 17:09:00', '2025-07-30 13:45:47', 31, 12, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(51, '2025-07-30 13:48:48', 'Lee Edison Laysa', '0999-999-9999', 'leeedison01@gmail.com', 'Erwin Gonzales Baguioan', 'Inactive', 'Romeo Cerna Cobreta Jr.', NULL, '', '2025-03-12 04:46:00', '2025-07-30 13:48:48', 20, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(53, '2025-07-31 03:30:29', 'Alvin Llaneta', '0999-999-9999', 'noemail@gmail.com', 'Joan Mahinay Barceta', 'Active', 'Nephele Telmo Panganiban', NULL, '', '2025-01-29 19:30:00', '2025-07-31 03:30:29', 24, 2, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(54, '2025-07-31 03:31:31', 'Rize Lagrimas', '0999-999-9999', 'noemail@gmail.com', 'Erwin Gonzales Baguioan', 'Active', 'Sarah Jean Lagatic Lopez', NULL, '', '2025-01-30 19:31:00', '2025-07-31 03:31:31', 20, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(55, '2025-07-31 03:32:32', 'Verlyn Vesagas', '0999-999-9999', 'noemail@gmail.com', 'Erwin Gonzales Baguioan', 'Active', 'Sarah Jean Lagatic Lopez', NULL, '', '2025-01-30 19:32:00', '2025-07-31 03:32:32', 20, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(56, '2025-07-31 03:33:27', 'Ma. Carla Baltazar', '0999-999-9999', 'noemail@gmail.com', 'Joan Mahinay Barceta', 'Active', 'Joan Mahinay Barceta', NULL, '', '2025-02-03 19:33:00', '2025-07-31 03:33:27', 24, 2, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(57, '2025-07-31 03:34:57', 'Michelle Pacuan', '0999-999-9999', 'noemail@gmail.com', 'Manny Alberto Violenta', 'Active', 'Manny Alberto Violenta', NULL, 'Endorsed by Jhoy Santos', '2025-02-03 19:34:00', '2025-07-31 03:34:57', 31, 12, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(58, '2025-07-31 03:36:47', 'Rhaynon G Amonggo', '0999-999-9999', 'noemail@gmail.com', 'Joan Mahinay Barceta', 'Active', 'Joan Mahinay Barceta', NULL, '', '2025-02-05 19:36:00', '2025-07-31 03:36:47', 24, 2, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(59, '2025-07-31 03:37:28', 'Michael Keanu Valdivia', '0999-999-9999', 'noemail@gmail.com', 'Erwin Gonzales Baguioan', 'Active', 'Gabriel Jr. Villamor Libacao', NULL, '', '2025-02-05 19:37:00', '2025-07-31 03:37:28', 20, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(60, '2025-07-31 03:40:08', 'Perlita Go', '0999-999-9999', 'gopearl43@yahoo.com', 'Erwin Gonzales Baguioan', 'Active', 'Erwin Gonzales Baguioan', NULL, '', '2025-02-13 04:40:08', '2025-07-31 16:11:17', 20, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0),
(61, '2025-07-31 03:53:19', 'Liezle Vista', '0929-395-2538', 'liezlekulot@gmail.com', 'Erwin Gonzales Baguioan', 'Active', 'Erwin Gonzales Baguioan', NULL, '', '2025-04-21 18:53:00', '2025-07-31 03:53:19', 20, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(62, '2025-07-31 03:57:07', 'Arlene L Umali', '0915-929-3382', 'umaliarlene45@gmail.com', 'Charlene Dellosa', 'Active', 'Charlene Dellosa', NULL, '', '2025-04-21 07:00:00', '2025-10-03 00:47:33', 8, 8, 1, 1, 1, 1, 1, 0, 1, 1, 1, 0, 0, 0, 1, 0, 0, 1, 0),
(63, '2025-07-31 05:07:28', 'Jobelle Bania', '0997-812-7775', 'jobellebania03@gmail.com', 'Manny Alberto Violenta', 'Active', 'Annalyn Salting Violenta', NULL, '', '2025-07-15 05:07:28', '2025-07-31 05:11:15', 31, 12, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(73, '2025-09-23 09:39:12', 'GENESIS', '0912-345-6678', 'genesis@gmail.com', 'Mark Christian Patigayon', 'Active', 'Mark Christian Patigayon', '', '', '2025-09-23 07:00:00', '2025-09-23 09:39:12', 15, 8, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 0),
(74, '2025-10-03 00:49:55', 'NARLYN SORIANO', '0927-586-4084', 'snarlyn3@gmail.com', 'Charlene Dellosa', 'Active', 'Charlene Dellosa', '', '', '2025-10-03 07:00:00', '2025-10-03 00:49:55', 8, 8, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(75, '2025-10-03 00:51:21', 'VIEN LLOYD STO. DOMINGO ', '0968-720-3487', 'svienlloyd@gmail.com', 'Charlene Dellosa', 'Active', 'Charlene Dellosa', '', '', '2025-10-03 07:00:00', '2025-10-03 00:51:21', 8, 8, 1, 1, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0),
(76, '2026-02-21 17:20:21', 'Patigayon, Mark Christian', '0912-828-2832', 'markpatigayon440@gmail.com', 'Mark Christian Patigayon', 'Active', 'Mark Christian Patigayon', '', 'Done ', '2026-02-21 08:00:00', '2026-02-21 17:20:21', 15, 8, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 0),
(77, '2026-03-09 05:47:07', 'Czar Florante', '0911-111-1111', 'innersparc@gmail.com', 'Mark Christian Patigayon', 'Active', 'Mark Christian Patigayon', '', '', '2026-03-09 07:00:00', '2026-03-09 11:15:27', 15, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1);

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
(1, 'company_name', 'Inner SPARC Realty Corporation', 'Company name displayed in the system', 'general', 1, '2025-06-09 06:54:53', '2025-07-23 03:06:37'),
(2, 'company_email', 'innersparcrealtyservices@gmail.com', 'Default company email address', 'general', 1, '2025-06-09 06:54:53', '2025-07-23 03:06:26'),
(3, 'company_phone', '(046) 458-0706', 'Company contact phone number', 'general', 1, '2025-06-09 06:54:53', '2025-07-23 03:06:26'),
(4, 'company_address', 'Block 26, Lot 4, Avida Catalina, Salawag, Dasmariñas, Cavite', 'Company physical address', 'general', 1, '2025-06-09 06:54:53', '2025-07-16 16:19:14'),
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
(33, 'enable_developer_tools', '1', 'Enable developer tools and debugging (0=off, 1=on)', 'developer', 0, '2025-06-09 06:54:53', '2025-07-16 14:36:39'),
(34, 'log_level', 'error', 'Log level (error, warning, info, debug)', 'developer', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53'),
(35, 'maintenance_mode', '1', 'Put system in maintenance mode (0=off, 1=on)', 'developer', 0, '2025-06-09 06:54:53', '2025-06-09 08:36:52'),
(36, 'maintenance_message', 'System is currently under maintenance. Please check back later.', 'Message displayed during maintenance mode', 'developer', 0, '2025-06-09 06:54:53', '2025-06-09 06:54:53');

-- --------------------------------------------------------

--
-- Table structure for table `stage_receipts`
--

CREATE TABLE `stage_receipts` (
  `id` int NOT NULL,
  `lead_id` int NOT NULL,
  `stage_type` varchar(50) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `stage_receipts`
--

INSERT INTO `stage_receipts` (`id`, `lead_id`, `stage_type`, `filename`, `original_name`, `file_path`, `file_size`, `mime_type`, `uploaded_at`, `created_by`) VALUES
(19, 223, 'downpayment', '223_downpayment_1757428278_0.png', 'Sample Receipts.png', 'uploads/receipts/223_downpayment_1757428278_0.png', NULL, NULL, '2025-09-09 14:31:18', NULL),
(20, 224, 'downpayment', '224_downpayment_1757657276_0.png', 'Sample Receipts.png', 'uploads/receipts/224_downpayment_1757657276_0.png', NULL, NULL, '2025-09-12 06:07:56', NULL),
(26, 224, 'downpayment', '224_downpayment_1757663752_0.png', 'Sample receipt 2.png', 'uploads/receipts/224_downpayment_1757663752_0.png', NULL, NULL, '2025-09-12 07:55:52', NULL),
(27, 224, 'downpayment', '224_downpayment_1757667180_0.png', 'Sample3.png', 'uploads/receipts/224_downpayment_1757667180_0.png', NULL, NULL, '2025-09-12 08:53:00', NULL),
(28, 224, 'downpayment', '224_downpayment_1757920851_0.png', '3E243028-1963-409F-86D5-3716050C0FA8.png', 'uploads/receipts/224_downpayment_1757920851_0.png', NULL, NULL, '2025-09-15 07:20:51', NULL),
(29, 225, 'downpayment', '225_downpayment_1758091711_0.png', 'B54EA5BC-62C5-472E-877A-FA9EA3528231.png', 'uploads/receipts/225_downpayment_1758091711_0.png', NULL, NULL, '2025-09-17 06:48:31', NULL),
(30, 225, 'downpayment', '225_downpayment_1758091733_0.png', 'Sample3.png', 'uploads/receipts/225_downpayment_1758091733_0.png', NULL, NULL, '2025-09-17 06:48:53', NULL),
(32, 209, 'downpayment', '209_downpayment_1758618651_0.jpg', 'image002.jpg', 'uploads/receipts/209_downpayment_1758618651_0.jpg', NULL, NULL, '2025-09-23 09:10:51', NULL),
(34, 209, 'downpayment', '209_downpayment_1758618673_0.jpg', 'image002 (1).jpg', 'uploads/receipts/209_downpayment_1758618673_0.jpg', NULL, NULL, '2025-09-23 09:11:13', NULL),
(35, 210, 'downpayment', '210_downpayment_1758618692_0.jpg', '1000047513.jpg', 'uploads/receipts/210_downpayment_1758618692_0.jpg', NULL, NULL, '2025-09-23 09:11:32', NULL),
(36, 216, 'downpayment', '216_downpayment_1758618745_0.jpg', '1000029172.jpg', 'uploads/receipts/216_downpayment_1758618745_0.jpg', NULL, NULL, '2025-09-23 09:12:25', NULL),
(37, 216, 'downpayment', '216_downpayment_1758618749_0.jpg', '1000029172.jpg', 'uploads/receipts/216_downpayment_1758618749_0.jpg', NULL, NULL, '2025-09-23 09:12:29', NULL),
(38, 266, 'downpayment', '266_downpayment_1758618779_0.jpg', '1000029880.jpg', 'uploads/receipts/266_downpayment_1758618779_0.jpg', NULL, NULL, '2025-09-23 09:12:59', NULL),
(39, 266, 'downpayment', '266_downpayment_1758618932_0.jpg', '1000029880.jpg', 'uploads/receipts/266_downpayment_1758618932_0.jpg', NULL, NULL, '2025-09-23 09:15:32', NULL),
(40, 229, 'downpayment', '229_downpayment_1758620542_0.png', 'Messenger_creation_B62AC091-30E5-476C-B160-D1929581FB07.png', 'uploads/receipts/229_downpayment_1758620542_0.png', NULL, NULL, '2025-09-23 09:42:22', NULL),
(41, 271, 'downpayment', '271_downpayment_1758670200_0.jpg', '1000001916.jpg', 'uploads/receipts/271_downpayment_1758670200_0.jpg', NULL, NULL, '2025-09-23 23:30:00', NULL),
(42, 272, 'downpayment', '272_downpayment_1758670358_0.jpg', '1000001915.jpg', 'uploads/receipts/272_downpayment_1758670358_0.jpg', NULL, NULL, '2025-09-23 23:32:38', NULL),
(43, 210, 'downpayment', '210_downpayment_1758767944_0.jpg', 'CamScanner 12-09-2025 14.43.jpg', 'uploads/receipts/210_downpayment_1758767944_0.jpg', NULL, NULL, '2025-09-25 02:39:04', NULL),
(44, 208, 'downpayment', '208_downpayment_1758768068_0.jpg', 'photo_6179438829560250751_y.jpg', 'uploads/receipts/208_downpayment_1758768068_0.jpg', NULL, NULL, '2025-09-25 02:41:08', NULL),
(45, 208, 'downpayment', '208_downpayment_1758769541_0.jpg', 'photo_6179438829560250751_y.jpg', 'uploads/receipts/208_downpayment_1758769541_0.jpg', NULL, NULL, '2025-09-25 03:05:41', NULL),
(46, 208, 'downpayment', '208_downpayment_1758769566_0.jpg', 'photo_6179438829560250751_y.jpg', 'uploads/receipts/208_downpayment_1758769566_0.jpg', NULL, NULL, '2025-09-25 03:06:06', NULL),
(47, 208, 'downpayment', '208_downpayment_1758769607_0.jpg', 'photo_6179438829560250751_y.jpg', 'uploads/receipts/208_downpayment_1758769607_0.jpg', NULL, NULL, '2025-09-25 03:06:47', NULL),
(55, 209, 'downpayment', '209_downpayment_1759803669_0.jpg', 'image002.jpg', 'uploads/receipts/209_downpayment_1759803669_0.jpg', 196315, 'image/jpeg', '2025-10-07 02:21:09', NULL),
(56, 209, 'downpayment', '209_downpayment_1759803670_0.jpg', 'image002.jpg', 'uploads/receipts/209_downpayment_1759803670_0.jpg', 196315, 'image/jpeg', '2025-10-07 02:21:10', NULL),
(57, 208, 'downpayment', '208_downpayment_1759803891_0.jpeg', 'received_1588858122462963.jpeg', 'uploads/receipts/208_downpayment_1759803891_0.jpeg', 207662, 'image/jpeg', '2025-10-07 02:24:51', NULL),
(58, 264, 'downpayment', '264_downpayment_1759913988_0.png', 'Receipt 1.png', 'uploads/receipts/264_downpayment_1759913988_0.png', 54009, 'image/png', '2025-10-08 08:59:48', NULL),
(59, 210, 'downpayment', '210_downpayment_1760080544_0.jpg', 'CamScanner 10-10-2025 15.01.jpg', 'uploads/receipts/210_downpayment_1760080544_0.jpg', 412088, 'image/jpeg', '2025-10-10 07:15:44', NULL),
(60, 314, 'downpayment', '314_downpayment_1761028456_0.jpeg', 'received_1301248201302864.jpeg', 'uploads/receipts/314_downpayment_1761028456_0.jpeg', 74388, 'image/jpeg', '2025-10-21 06:34:16', NULL),
(61, 403, 'downpayment', '403_downpayment_1766624772_0.jpg', '7df1e8fb-c86c-4531-a368-abec6b4e946e.jpg', 'uploads/receipts/403_downpayment_1766624772_0.jpg', 359252, 'image/jpeg', '2025-12-25 01:06:15', NULL),
(62, 403, 'downpayment', '403_downpayment_1766625288_0.jpg', '7df1e8fb-c86c-4531-a368-abec6b4e946e.jpg', 'uploads/receipts/403_downpayment_1766625288_0.jpg', 359252, 'image/jpeg', '2025-12-25 01:14:51', NULL),
(63, 314, 'downpayment', '314_downpayment_1766625342_0.jpg', '3rd payment.jpg', 'uploads/receipts/314_downpayment_1766625342_0.jpg', 291770, 'image/jpeg', '2025-12-25 01:15:45', NULL),
(64, 314, 'downpayment', '314_downpayment_1766625388_0.jpg', '3rd payment.jpg', 'uploads/receipts/314_downpayment_1766625388_0.jpg', 291770, 'image/jpeg', '2025-12-25 01:16:31', NULL),
(65, 314, 'downpayment', '314_downpayment_1766625391_0.jpg', '3rd payment.jpg', 'uploads/receipts/314_downpayment_1766625391_0.jpg', 291770, 'image/jpeg', '2025-12-25 01:16:34', NULL),
(66, 209, 'downpayment', '209_downpayment_1766625457_0.jpg', 'Screenshot_20251225_090310_com.google.android.gm.jpg', 'uploads/receipts/209_downpayment_1766625457_0.jpg', 612005, 'image/jpeg', '2025-12-25 01:17:40', NULL),
(67, 210, 'downpayment', '210_downpayment_1766626223_0.jpg', 'CamScanner 10-10-2025 15.01.jpg', 'uploads/receipts/210_downpayment_1766626223_0.jpg', 412088, 'image/jpeg', '2025-12-25 01:30:26', NULL),
(68, 210, 'downpayment', '210_downpayment_1766626273_0.jpg', 'CamScanner 10-10-2025 15.01.jpg', 'uploads/receipts/210_downpayment_1766626273_0.jpg', 412088, 'image/jpeg', '2025-12-25 01:31:16', NULL),
(69, 209, 'downpayment', '209_downpayment_1766626364_0.jpg', 'Screenshot_20251225_090311_com.google.android.gm.jpg', 'uploads/receipts/209_downpayment_1766626364_0.jpg', 612005, 'image/jpeg', '2025-12-25 01:32:46', NULL),
(70, 208, 'downpayment', '208_downpayment_1766629356_0.jpg', 'Screenshot_20251225_102144_com.google.android.gm.jpg', 'uploads/receipts/208_downpayment_1766629356_0.jpg', 1027218, 'image/jpeg', '2025-12-25 02:22:39', NULL),
(71, 208, 'downpayment', '208_downpayment_1766629387_0.jpg', 'Screenshot_20251225_102144_com.google.android.gm.jpg', 'uploads/receipts/208_downpayment_1766629387_0.jpg', 1027218, 'image/jpeg', '2025-12-25 02:23:09', NULL),
(72, 443, 'downpayment', '443_downpayment_1766631678_0.jpeg', 'received_1950905849103460.jpeg', 'uploads/receipts/443_downpayment_1766631678_0.jpeg', 121770, 'image/jpeg', '2025-12-25 03:01:21', NULL),
(73, 403, 'downpayment', '403_downpayment_1767102140_0.jpg', 'Screenshot_20251230_213946_com.google.android.gm.jpg', 'uploads/receipts/403_downpayment_1767102140_0.jpg', 1479672, 'image/jpeg', '2025-12-30 13:42:23', NULL),
(74, 476, 'downpayment', '476_downpayment_1771693802_0.jpg', 'allen1srt.jpg', 'uploads/receipts/476_downpayment_1771693802_0.jpg', 359252, 'image/jpeg', '2026-02-21 17:10:02', NULL);

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
-- Table structure for table `tour_targets`
--

CREATE TABLE `tour_targets` (
  `id` int NOT NULL,
  `tour_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `destination` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `agent_target` decimal(15,2) DEFAULT '500000.00',
  `supervisor_target` decimal(15,2) DEFAULT '800000.00',
  `manager_target` decimal(15,2) DEFAULT '1000000.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tour_targets`
--

INSERT INTO `tour_targets` (`id`, `tour_type`, `destination`, `agent_target`, `supervisor_target`, `manager_target`, `created_at`) VALUES
(1, 'Local Tour', 'Boracay', 500000.00, 800000.00, 1000000.00, '2025-06-19 12:04:15'),
(2, 'Local Tour', 'Baguio', 400000.00, 600000.00, 800000.00, '2025-06-19 12:04:15'),
(3, 'International Tour', 'Malaysia/Indonesia', 800000.00, 1200000.00, 1500000.00, '2025-06-19 12:04:15'),
(4, 'International Tour', 'Singapore', 600000.00, 900000.00, 1200000.00, '2025-06-19 12:04:15');

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
  `cover_photo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Path to user cover photo image',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `position` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Agent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `team_id`, `username`, `password`, `name`, `email`, `phone`, `role`, `profile_picture`, `cover_photo`, `is_active`, `last_login`, `created_at`, `updated_at`, `position`) VALUES
(3, 8, 'shielamaefajutagana.accounting', '$2y$10$TYNY41eE08/ovGUDyEz2wOIuGNf0UA4ZI89xCS28Iv4stgHvI9z6W', 'Sheila Mae Fontelo Fajutagana', 'francisheila05@gmail.com', '09151082974', 'agent', NULL, NULL, 1, NULL, '2025-05-16 08:45:21', '2026-01-14 05:54:26', 'Agent'),
(4, 2, 'luzvimindalim.innersparc', '$2y$10$McWWaS3tbEcs6JiXgSUJjObv97/fGa9GjMN/A3eFtNTf5QK.fSul2', 'Luzviminda Labrado Lim', 'luz032069@gmail.com', '09062717602', 'supervisor', NULL, NULL, 1, NULL, '2025-05-16 08:45:21', '2026-02-09 12:41:21', 'Agent'),
(6, 1, 'mikegabrielescarilla.innersparc', '$2y$10$/0ZNhltbQkQ9G9cqyNhMF.4CvV3V9a0Gz4zrPmF8yrGxwkTMCnGKi', 'Mike Gabriel Bedion Escarilla', 'escarilla.mikegabriel@gmail.com', '09269979145', 'agent', NULL, NULL, 1, NULL, '2025-05-16 08:45:21', '2025-07-09 04:53:23', 'Agent'),
(7, 1, 'romeocobreta.itdept', '$2y$10$4kClU8KiDduzFopON0JwzOmorNZ2UgRvAUKL4tqnpe9IeSdD6KBpK', 'Romeo Cerna Cobreta Jr.', 'romxcob.innersparc@gmail.com', '09090326945', 'admin', NULL, NULL, 1, NULL, '2025-05-16 08:45:21', '2025-09-23 09:45:39', 'Agent'),
(8, 8, 'charlenedellosa.opsman', '$2y$10$7BvBgpFnDmhhX3/zSVikjebJCWefKLpsg36RYdkM0KhZDmjE/Zmmu', 'Charlene Dellosa', 'dellosacharlene1317@gmail.com', '09169994124', 'manager', 'uploads/profile_pictures/user_8_1747501769.png', NULL, 1, NULL, '2019-05-16 08:45:23', '2025-06-06 07:31:50', 'Agent'),
(9, 2, 'alvinllaneta.innersparc', '$2y$10$lKOqkDq7wAUzCo.XVJSrr.zc2xyE97PUFtOpSpbQ56I.fBuXPE6Aa', 'Alvin  Llaneta', 'alvinllaneta8@gmail.com', '09613825054', 'agent', NULL, NULL, 1, NULL, '2025-05-16 08:45:23', '2025-07-04 10:10:35', 'Agent'),
(11, 2, 'clarencedanielleserdon.innersparc', '$2y$10$uV4X7.qKkNm7gOCFNi2hQOZSTaX8swqpfmzF/eIEp2E.ZaKy8WEUi', 'Clarence Danielle Lim Serdon', 'clarencedanielle98@gmail.com', '09996916107', 'agent', NULL, NULL, 1, NULL, '2025-05-16 08:45:23', '2025-07-09 04:44:19', 'Agent'),
(12, 2, 'gabcyrosebenson.innersparc', '$2y$10$e2uj.FtpU1X15hswbpLQh.hh33ZuIPJhtJ6z/mjPN.3C4fz6NKySK', 'Gabcyrose B. Del Socorro', 'innersparcgabcy@gmail.com', '09263939124', 'agent', NULL, NULL, 1, NULL, '2025-05-16 08:45:23', '2025-09-26 06:02:28', 'Agent'),
(13, 1, 'lenizapasion.innersparc', '$2y$10$.xkUKC8tbTEK2V9xrPx6WOr5qkGrRspusLQRh6MR2o.lFi0jLxIzC', 'Leniza Flores pasion', 'lenizapasion51@gmail.com', '09177179863', 'agent', NULL, NULL, 1, NULL, '2025-05-16 08:45:23', '2025-07-30 01:44:10', 'Agent'),
(14, 1, 'perlitago.innersparc', '$2y$10$DDN/jVTCj3K5mAdTpIJ/yu21IZoU3rydeRW.fiyKpE1AwNDtcUcDy', 'Perlita Santiago Go', 'gopearl43@yahoo.com', '09777261123', 'agent', NULL, NULL, 1, NULL, '2025-05-16 08:45:23', '2025-07-09 04:54:00', 'Agent'),
(15, 1, 'markpatigayon.itadmin', '$2y$10$wq55gm6Quwch1tGTwcaFDuMxyKW1RzmKHi5deLViULq8RUi655GVK', 'Mark Christian Patigayon', 'markpatigayon440@gmail.com', '09194620030', 'admin', 'uploads/profile_pictures/profile_68e3ca26e87a5.jpg', 'uploads/cover_photos/cover_68be889419f0f.jpg', 1, NULL, '2019-05-22 08:45:23', '2026-03-09 05:46:18', 'Agent'),
(16, 1, 'verlynvesagas.innersparc', '$2y$10$D5DAFfVFeoOHXiN/OhOtNO4jW1BCifKfnMFI8xWZDncum79erE8LW', 'Verlyn Bizconde Vesagas', 'vverlyn@gmail.com', '09915573606', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:08:49', '2025-07-09 04:54:38', 'Agent'),
(17, 1, 'rizelagrimas.innersparc', '$2y$10$iZEmwMZVpxVWmrW7cswplu2jpH3iZ0e.Alz.burmhYE2n5gH9sbQa', 'Rize OwogOwog Lagrimas', 'rizielagrimas18@gmail.com', '09202474501', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:09:39', '2025-07-09 04:54:04', 'Agent'),
(18, 1, 'ireneblanca.innersparc', '$2y$10$Qhjl1q5sfPdqn19vyFNIL.L8IZo7mjhPWSRb1cCIXqb8GU0WFzSam', 'Irene Noble Blanca', 'ireneblanca1909@gmail.com', '09943843721', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:10:13', '2025-07-09 04:51:30', 'Agent'),
(19, 1, 'gabriellibacao.founder', '$2y$10$0A.MhdXz2UAcy4bUGR6EBOsQ82F9GtlZLgCw0a0n46KXTUuoM8t8a', 'Gabriel Jr. Villamor Libacao', 'libacaoga@gmail.com', '09178534875', 'admin', NULL, NULL, 1, NULL, '2025-05-19 08:10:58', '2025-06-06 07:31:02', 'Agent'),
(20, 1, 'erwingonzales.cofounder', '$2y$10$/I4c/Yn7lWXRlraQvK0m2ueNwa2vgAfJ1NSKVG9wNfXJUu51fjoeq', 'Erwin Gonzales Baguioan', 'irwindgonzales6@gmail.com', '09669533188', 'manager', NULL, NULL, 1, NULL, '2025-05-19 08:11:50', '2025-06-06 07:40:01', 'Agent'),
(21, 1, 'nelynortega.innersparc', '$2y$10$XY1vl5BHsJlD/roNpcTGCOA4XAtto0QuH60k5Ee0xdAzcgwfQuWtO', 'Nelyn Serad Ortega', 'orteganelyn18@gmail.com', '09650984075', 'supervisor', NULL, NULL, 1, NULL, '2025-05-19 08:12:41', '2025-07-09 04:53:25', 'Agent'),
(22, 1, 'sarahlopez.innersparc', '$2y$10$n3BwlyzlLY6kVcmo92jNgufr5iaqL4YJSrBbT39Vx8/QSesNpb8d.', 'Sarah Jean Lagatic Lopez', 'sarahjeanlopez07@gmail.com', '09329757344', 'supervisor', NULL, NULL, 1, NULL, '2025-05-19 08:13:10', '2025-07-09 04:54:07', 'Agent'),
(23, 2, 'nephelepanganiban.innersparc', '$2y$10$a8V0BI7ainZ13/S/XAqJBeMYJJiS2zW0rQGkbJ2..n/Ioa8qXCBlC', 'Nephele Telmo Panganiban', 'nephelepanganiban@gmail.com', '09662974629', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:18:33', '2025-07-09 04:53:29', 'Agent'),
(24, 2, 'joanbarceta.innersparc', '$2y$10$E.XU5PTwBNn6BryqVpHLTOGGmPOz9V1slWHXBZjzg4YeKC00K9o6K', 'Joan Mahinay Barceta', 'jobarceta22@gmail.com', '09649589052', 'manager', 'uploads/profile_pictures/user_24_1747622923.jpg', NULL, 1, NULL, '2025-05-19 08:19:01', '2025-06-04 22:01:31', 'Agent'),
(25, 2, 'teresasandoval.innersparc', '$2y$10$/60.BrY05vIHWr1LOeVQJuc8hAKnPe7KbG7q567xCBHRtsw6z1z26', 'Teresa Rosanto Sandoval', 'trscyl@yahoo.com', '09932967582', 'supervisor', NULL, NULL, 1, NULL, '2025-05-19 08:20:20', '2025-07-09 04:54:15', 'Agent'),
(26, 2, 'ailyndetorres.innersparc', '$2y$10$tKqj8NqpXT8VwvRTUa1MEONXwFFi0W4pbp02LR9bjgynRdu1a0may', 'Ailyn Llaneta De Torres', 'ailyndetorres8@gmail.com', '09501409792', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:21:11', '2025-07-09 04:42:58', 'Agent'),
(27, 2, 'emilyncantuba.innersparc', '$2y$10$MhY2/Tz1.fra7GD1glif..ztXQQMmKv1wmZaC66iP948QDjAbdtmu', 'Emilyn Marcelo Cantuba', 'cantubaemhie@gmail.com', '09362898373', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:21:46', '2025-07-09 04:51:19', 'Agent'),
(28, 2, 'novelitatabudlong.innersparc', '$2y$10$i1en7V7RM77tsypCAVFjV.i5pQxJDbWxeixqt22Vde6kAmbqw95lq', 'Novelita  Letran Tabudlong', 'novzpretty@gmail.com', '09366512502', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:22:15', '2025-07-09 04:53:32', 'Agent'),
(29, 8, 'leodellosa.innersparc', '$2y$10$AXilucmLDho58ZWv4JBuquqJse/BeOXt8nOmk2BZ2yffrVSnwyNd2', 'Leonardo  Dellosa', 'korndellosa@gmail.com', '09605661999', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:22:59', '2025-07-09 13:23:34', 'Agent'),
(30, NULL, 'arleneumali.innersparc', '$2y$10$W.oXRUenaxeO/2d.esR4kucP7xOSTaowA4srEzROtwSmXwgX72VTq', 'Arlene Umali', 'arleneumali@example.com', '09159293382', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:24:33', '2025-07-31 08:54:27', 'Agent'),
(31, 12, 'mannyviolenta.innersparc', '$2y$10$gwzsRQ.Q741vLypWDpgz4eGWZXjljClzoaifk3BDZEofmhREaIepS', 'Manny Alberto Violenta', 'violentamanny@gmail.com', '09380326931', 'manager', NULL, NULL, 1, NULL, '2025-05-19 08:28:42', '2025-07-09 04:52:47', 'Agent'),
(32, 12, 'annalynviolenta.innersparc', '$2y$10$UZBJ3Wb37vKSlIu7PHjpQeaXgYCZs3o1a5bv3g7YvqmqsHIhYpbpK', 'Annalyn Salting Violenta', 'anniemazing2@gmail.com', '09084776982', 'agent', 'uploads/profile_pictures/profile_6879b2dec9642.jpg', NULL, 1, NULL, '2025-05-19 08:30:01', '2025-07-18 02:35:10', 'Agent'),
(33, 12, 'anelatabuyan.innersparc', '$2y$10$E6RHvywovVrrP9ZT1NG/Se8iBJIEzq9pQ5WIZo7NexvfTtCGj2Fye', 'Anela Dela Cruz Tabuyan', 'nela.tab5@gmail.com', '09356088954', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:30:31', '2025-07-09 04:43:22', 'Agent'),
(34, 12, 'jocelynsantos.innersparc', '$2y$10$G53taXRFOcu2PNFCwnUZZ.W3GufXEgjLxFKx7oFx3DxVt0TfYpxFu', 'Jocelyn Santos', 'jhoymsantos15@gmail.com', '09694569711', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:30:57', '2025-07-09 04:52:07', 'Agent'),
(35, 12, 'lenilyntimajo.innersparc', '$2y$10$DFP7LJlXVrgZyKdWbbFdvuLIGAIrpoxFgk1c5mT5q2ranbJgvJ7.u', 'Lenily  Rana Timajo', 'timajolenily@gmail.com', '09129988330', 'supervisor', NULL, NULL, 1, NULL, '2025-05-19 08:31:24', '2025-07-09 04:52:13', 'Agent'),
(36, 12, 'jerusalinosantos.innersparc', '$2y$10$hYIses3VZ0VyVq9iFYUYeOrjIwkvzoeZlN9spuoOv6bgXHRZzDGwW', 'Jerusalino Tan Santos', 'jerometsantos28@gmail.com', '09516319674', 'supervisor', NULL, NULL, 1, NULL, '2025-05-19 08:32:16', '2025-06-04 22:01:31', 'Agent'),
(37, 12, 'novelynbualat.innersparc', '$2y$10$a9Sc336Pi2uf45wVmP05COvhMyzUq8m.1CgnR./f4ltKigBRLmOma', 'Novelyn Macalam  Bualat', 'novelynbualat01@gmail.com', '09281505191', 'agent', NULL, NULL, 1, NULL, '2025-05-19 08:33:04', '2025-07-09 04:53:56', 'Agent'),
(38, 12, 'edenrosedemerin.innersparc', '$2y$10$bUZpkw8VtYlmisaX5y.LLe6K5aUTG5APwogUG2gdwdfFfPrCXGpLS', 'Eden Rose Ramos Demerin', 'apostolerogalapino@gmail.com0', '09380196696', 'supervisor', NULL, NULL, 1, NULL, '2025-05-19 08:33:27', '2025-07-09 04:49:58', 'Agent'),
(39, 13, 'markbacli.intern', '$2y$10$niHLEGNqlqxrDNGyWTow4eTlk1DoGKYIDvdzDQpjMrtjzivkE4jJC', 'Mark Vincent Bacli', 'markvincentbacli@gmail.com', '09953009113', 'admin', NULL, NULL, 1, NULL, '2025-06-02 04:31:33', '2025-06-06 07:10:33', 'Agent'),
(41, NULL, 'charitopalonson.innersparc', '$2y$10$o9XCcRdXfkINgEtgnS2HBuBlCTcRQKctogxtRugQlzNOlI6gYT1sq', 'Charito Palonson', 'charitabasbas@gmail.com', '09664380890', 'supervisor', NULL, NULL, 1, NULL, '2025-06-02 05:43:02', '2025-07-09 04:46:39', 'Agent'),
(54, 13, 'genesiscontreras.intern', '$2y$10$cuttoE5l6bH0tAIUdDq8J.IQgrvoYVrz5Oe2WmNffdPkuUSlkhNuu', 'Genesis Contreras', 'genesiscontreras@gmail.com', '09129382938', 'admin', NULL, NULL, 1, NULL, '2025-06-06 07:04:06', '2025-07-09 04:51:27', 'Agent'),
(55, 13, 'johnpaulperfecto.intern', '$2y$10$W9DkpAWXXLHcuf7mEGkNwOcDXOR8v8WwsaGmxOEIM0Eaa0B1ADbwK', 'John Paul Perfecto', 'jperfecto.innersparc@gmail.com', '09189283283', 'agent', 'uploads/profile_pictures/profile_6846881844a1d.jpg', NULL, 1, NULL, '2025-06-06 07:04:25', '2025-07-04 01:58:23', 'Agent'),
(56, 13, 'mariellepablea.intern', '$2y$10$kte3t7c0rwWe12xvVFJXHORgHPMUucPAylzzpDivKgiKARwgBlZXe', 'Marielle Pablea', 'mariellep.innersparc@gmail.com', '09123829382', 'agent', NULL, NULL, 1, NULL, '2025-06-06 07:04:40', '2025-07-09 04:52:50', 'Agent'),
(58, 13, 'zjosev.intern', '$2y$10$9VCK3TcohU7bMNWijMp92ewOOnov23b4YME3qS4XWUoosCkZmw0A.', 'Zjosev Atienza', 'maksxim.innersparc@gmail.com', '09812398129', 'agent', 'uploads/profile_pictures/profile_68673884a9004.jpg', NULL, 1, NULL, '2025-06-06 07:06:22', '2025-07-04 02:12:20', 'Agent'),
(59, 13, 'danielpagilagan.innersparc', '$2y$10$cFm.oaARNgZRi60DQUJSp.juOikayA5WOSK1qd4J2sgODY8LJ3TLi', 'Daniel Boni V. Pagilagan', 'daniel@gmail.com', '09122938298', 'admin', 'uploads/profile_pictures/profile_68468695826b4.jpg', NULL, 1, NULL, '2025-06-09 06:57:55', '2025-08-04 04:31:39', 'Agent'),
(61, 1, 'juandelacruz.innersparc', '$2y$10$IcX.QpMAIA84BPCqcHjn7uIwOpGoLfoP00sUjck2nxDYkKTVWNl7.', 'juandelacruz', 'markkksjd@gmail.com', '09182382828', 'agent', NULL, NULL, 1, NULL, '2025-06-16 08:39:45', '2025-06-19 12:04:15', 'Agent'),
(62, 13, 'gavrietalaboc.intern', '$2y$10$eomO.MnmsPQ0BWH0IA0w9OVFKPOyJeJivE6lHxtgXzSKf09eXxAHC', 'Yerik Yves Gavrie F. Talaboc', 'example@gmail.com', '00911111111', 'admin', 'uploads/profile_pictures/profile_686f1ede9dfd5.jpg', NULL, 1, NULL, '2025-06-23 06:50:04', '2025-07-10 02:01:02', 'Agent'),
(63, 13, 'davidcasil.intern', '$2y$10$32QKB16RXYYLPQWrDE69OerdwwPp4WqqX1kGEDlUmQdK8ONmmwcHG', 'David Casil', 'ex@gmail.com', '09111111111', 'admin', 'uploads/profile_pictures/profile_687096a3a8cbc.jpg', 'uploads/cover_photos/cover_68904b3a4a9f7.jpeg', 1, NULL, '2025-06-23 06:51:33', '2025-08-04 05:55:06', 'Agent'),
(91, 2, 'gabcy.rose.innersparc', '$2y$10$HH8P7Fq5Ixmd3hLFqoAmxOFWKgaXSiEjQCq4ZiSmI8WyOS.mGHb6K', 'Gabcy Rose', 'gabcy@gmail.com', '09643212345', 'supervisor', NULL, NULL, 1, NULL, '2025-07-04 09:37:37', '2025-07-04 09:37:37', 'Agent'),
(94, 12, 'cynthiamartin.innersparc', '$2y$10$teMhxqU//wa7TuyAsP38Ned.aE1.KuHgqkxmrHK5BwjWQK2Pyer5e', 'Cynthia M Martin', 'martincynthia9210@gmail.com', '09649080029', 'agent', NULL, NULL, 1, NULL, '2025-07-29 08:31:13', '2026-02-12 02:51:25', 'Agent'),
(96, 8, 'arlenelumali.innersparc', '$2y$10$PVswhV0U4qTVFyA.Z0r/J.ZlCDQjZLnn.Yme2oS3M/Xx74V82LyiS', 'Arlene L Umali', 'umaliarlene45@gmail.com', '0915-929-3382', 'agent', NULL, NULL, 1, NULL, '2025-07-31 08:42:51', '2025-07-31 08:42:51', 'Agent'),
(97, 8, 'estelacaayaman.innersparc', '$2y$10$7SyklJZZHPZhOLUxV9zkU.fwlDJAVnWc1G62hewLYLCY1a7RguqQ.', 'Estela Caayaman', 'estelacaayaman10@gmail.com', '0906-480-9826', 'agent', NULL, NULL, 1, NULL, '2025-07-31 08:49:56', '2025-07-31 08:49:56', 'Agent'),
(100, 8, 'jeromebadua.innersparc', '$2y$10$1UARwrp3Yob6l5KFsTxOyuvmJndW8s5ENmPn0ZgbI2B3YenbA3ZQe', 'Jerome N. Badua', 'jeromebadua@gmail.com', '09111111111', 'agent', NULL, NULL, 1, NULL, '2025-08-04 04:27:11', '2025-08-04 04:28:55', 'Agent'),
(101, 8, 'genesiscontreras.innersparc', '$2y$10$95AZwPYtApSeiFNW/yfjf.vFXMliyRTrt9Vpa4o4N7NOoBdyNylCC', 'Genessis Contreras', 'genesis@gmail.com', '09111111111', 'admin', NULL, NULL, 1, NULL, '2025-08-04 04:27:35', '2025-08-04 04:29:34', 'Agent'),
(102, 8, 'yenzogervacio.innersparc', '$2y$10$l0xvF6Ugw9RFsY6bricEhOUayTRZmpZR9dBKc5FDlm5yqCmIC.CRK', 'Yenzo Teo Gervacio', 'yenz@gmail.com', '01111111111', 'admin', 'uploads/profile_pictures/profile_68e76b838ea1b.jpg', NULL, 1, NULL, '2025-08-04 04:27:48', '2025-10-09 08:00:03', 'Agent'),
(103, 8, 'leonardpistano.innersparc', '$2y$10$frT9OLTVilcg7V1BNeRstuHbL9Y8nBol7Ihadp.E.X8XkkJ8Dk2E.', 'Leonard M. Pistaño', 'leonard@gmail.com', '09111111111', 'agent', NULL, NULL, 1, NULL, '2025-08-04 04:28:23', '2025-08-04 04:30:38', 'Agent'),
(105, 1, 'sampleagent.innersparc', '$2y$10$1FFOJK0WcB76KSDOAPgwW.24gv7YrZXPrNaPQWeqEf3obUixrYElK', 'Sample Agent', 'romeo.cobreta@gmail.com', '0909-032-6945', 'agent', 'uploads/profile_pictures/profile_6890478c6dc46.png', NULL, 1, NULL, '2025-08-04 05:37:43', '2025-08-04 05:39:24', 'Agent'),
(106, NULL, 'guest', '$2y$10$XMu7w.oTncvnyTncxvlO1u834SVR89/EKyT2p2Iaw./icNiMAlgUi', 'Guest User', 'guestuser@gmail.com', '09194620030', 'agent', NULL, NULL, 1, NULL, '2025-08-26 10:15:04', '2025-08-26 10:19:25', 'Agent'),
(108, 8, 'estela.l.caayaman.innersparc', '$2y$10$txl9YaFXsKw3.sKtt09O.O3mOjAug25hNrlWdoMyZj0.ChuGGi2ui', 'Estela L. Caayaman', 'estelacaayaman@gmail.com', '09064809826', 'agent', NULL, NULL, 1, NULL, '2025-09-05 02:42:54', '2025-09-05 02:42:54', 'Agent'),
(109, 13, 'johnstephen.intern', '$2y$10$EroObdzK7X0VHjAJg/soieqGd9y2Ld02pBTQ9kwt1ox8ualMo1cO.', 'John Stephen', 'johnstephen@gmail.com', '09234567891', 'admin', NULL, NULL, 1, NULL, '2025-09-25 02:45:11', '2025-09-25 02:45:32', 'Agent'),
(110, 2, 'rionelamonggo.innersparc', '$2y$10$V.st2C.DGBgPmgnn1iaNyePbQu8jAG1U8RA1zTT07KFp//cc8Ay.e', 'Rionel Amonggo', 'rionelamonggo6@gmail.com', '09384532595', 'agent', NULL, NULL, 1, NULL, '2025-10-02 12:08:55', '2025-10-02 12:13:15', 'Agent'),
(111, 8, 'vienstodomingo.innersparc', '$2y$10$6SVKUKTJ109pUXq6z3d5Wes4aEwmGbt07PEaOiUYQANCR3C7TwhdK', 'Vien Lloyd Sto. Domingo', 'svienlloyd@gmail.com', '09687203487', 'agent', NULL, NULL, 1, NULL, '2025-10-02 12:10:20', '2025-10-02 12:12:21', 'Agent'),
(112, 8, 'narlynsoriano.innersparc', '$2y$10$zNNzSIe9kxBWGgsSlt6dK.jcm78NaagzSxLUEho2/rG12WD2Rf.p2', 'Narlyn A. Soriano', 'snarlyn3@gmail.com', '09275864084', 'agent', NULL, NULL, 1, NULL, '2025-10-02 12:11:45', '2025-10-02 12:12:02', 'Agent'),
(113, 1, 'czarflorante.innersparc', '$2y$10$R43zw.1A2x45qgojBLd0xen2Z5Kbpjsnfwk3EFsCGT5McfKJHAfdW', 'Czar Florante', 'innersparc@gmail.com', '0911-111-1111', 'agent', NULL, NULL, 1, NULL, '2026-03-09 11:15:27', '2026-03-09 11:15:27', 'Agent');

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
  ADD KEY `reservation_date` (`reservation_date`),
  ADD KEY `next_payment_date` (`next_payment_date`),
  ADD KEY `idx_lead_id` (`lead_id`);

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
-- Indexes for table `incentives`
--
ALTER TABLE `incentives`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_incentive` (`user_id`,`incentive_type`,`destination`);

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
-- Indexes for table `manual_data`
--
ALTER TABLE `manual_data`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `memo_person_visibility`
--
ALTER TABLE `memo_person_visibility`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `memo_user_unique` (`memo_id`,`user_id`),
  ADD KEY `memo_id` (`memo_id`),
  ADD KEY `user_id` (`user_id`);

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
-- Indexes for table `problem_reports`
--
ALTER TABLE `problem_reports`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `project_models`
--
ALTER TABLE `project_models`
  ADD PRIMARY KEY (`id`),
  ADD KEY `developer_id` (`developer_id`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `raffle_tickets`
--
ALTER TABLE `raffle_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `stage_source` (`stage_source`);

--
-- Indexes for table `recruitment_leads`
--
ALTER TABLE `recruitment_leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `source` (`source`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `stage_receipts`
--
ALTER TABLE `stage_receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lead_stage` (`lead_id`,`stage_type`),
  ADD KEY `idx_uploaded_at` (`uploaded_at`),
  ADD KEY `idx_stage_type` (`stage_type`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `tour_targets`
--
ALTER TABLE `tour_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tour_destination` (`tour_type`,`destination`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `developers`
--
ALTER TABLE `developers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `downpayment_tracker`
--
ALTER TABLE `downpayment_tracker`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

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
-- AUTO_INCREMENT for table `incentives`
--
ALTER TABLE `incentives`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=482;

--
-- AUTO_INCREMENT for table `lead_activities`
--
ALTER TABLE `lead_activities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=300;

--
-- AUTO_INCREMENT for table `lead_modifications`
--
ALTER TABLE `lead_modifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT for table `manual_data`
--
ALTER TABLE `manual_data`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=318;

--
-- AUTO_INCREMENT for table `memos`
--
ALTER TABLE `memos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `memo_images`
--
ALTER TABLE `memo_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `memo_person_visibility`
--
ALTER TABLE `memo_person_visibility`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `memo_read_status`
--
ALTER TABLE `memo_read_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `memo_team_visibility`
--
ALTER TABLE `memo_team_visibility`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `memo_visibility`
--
ALTER TABLE `memo_visibility`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `problem_reports`
--
ALTER TABLE `problem_reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `project_models`
--
ALTER TABLE `project_models`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `raffle_tickets`
--
ALTER TABLE `raffle_tickets`
  MODIFY `ticket_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=288;

--
-- AUTO_INCREMENT for table `recruitment_leads`
--
ALTER TABLE `recruitment_leads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `stage_receipts`
--
ALTER TABLE `stage_receipts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tour_targets`
--
ALTER TABLE `tour_targets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=527;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

-- --------------------------------------------------------

--
-- Structure for view `active_leads_summary`
--
DROP TABLE IF EXISTS `active_leads_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`managementleaddb`@`66.33.192.0/255.255.224.0` SQL SECURITY DEFINER VIEW `active_leads_summary`  AS SELECT `l`.`id` AS `id`, `l`.`client_name` AS `client_name`, `l`.`phone` AS `phone`, `l`.`email` AS `email`, `l`.`temperature` AS `temperature`, `l`.`status` AS `status`, `l`.`source` AS `source`, `l`.`developer` AS `developer`, `l`.`project_model` AS `project_model`, `l`.`price` AS `price`, `l`.`expected_commission` AS `expected_commission`, `u`.`name` AS `agent_name`, `t`.`name` AS `team_name`, `l`.`follow_up_date` AS `follow_up_date`, `l`.`created_at` AS `created_at` FROM ((`leads` `l` join `users` `u` on((`l`.`user_id` = `u`.`id`))) left join `teams` `t` on((`u`.`team_id` = `t`.`id`))) WHERE (`l`.`status` not in ('Closed Deal','Lost')) ORDER BY `l`.`follow_up_date` ASC, `l`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `monthly_sales_report`
--
DROP TABLE IF EXISTS `monthly_sales_report`;

CREATE ALGORITHM=UNDEFINED DEFINER=`managementleaddb`@`66.33.192.0/255.255.224.0` SQL SECURITY DEFINER VIEW `monthly_sales_report`  AS SELECT year(`l`.`updated_at`) AS `year`, month(`l`.`updated_at`) AS `month`, monthname(`l`.`updated_at`) AS `month_name`, count((case when (`l`.`status` = 'Closed Deal') then 1 end)) AS `deals_closed`, sum((case when (`l`.`status` = 'Closed Deal') then `l`.`price` else 0 end)) AS `total_sales`, sum((case when (`l`.`status` = 'Closed Deal') then `l`.`expected_commission` else 0 end)) AS `total_commission`, avg((case when (`l`.`status` = 'Closed Deal') then `l`.`price` else NULL end)) AS `average_deal_size` FROM `leads` AS `l` WHERE (`l`.`status` = 'Closed Deal') GROUP BY year(`l`.`updated_at`), month(`l`.`updated_at`) ORDER BY year(`l`.`updated_at`) DESC, month(`l`.`updated_at`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `team_performance_summary`
--
DROP TABLE IF EXISTS `team_performance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`managementleaddb`@`66.33.192.0/255.255.224.0` SQL SECURITY DEFINER VIEW `team_performance_summary`  AS SELECT `t`.`id` AS `team_id`, `t`.`name` AS `team_name`, count(distinct `u`.`id`) AS `total_agents`, count(`l`.`id`) AS `total_leads`, count((case when (`l`.`status` = 'Closed Deal') then 1 end)) AS `closed_deals`, sum((case when (`l`.`status` = 'Closed Deal') then `l`.`price` else 0 end)) AS `total_sales`, sum((case when (`l`.`status` = 'Closed Deal') then `l`.`expected_commission` else 0 end)) AS `total_commission`, round(((count((case when (`l`.`status` = 'Closed Deal') then 1 end)) * 100.0) / nullif(count(`l`.`id`),0)),2) AS `conversion_rate` FROM ((`teams` `t` left join `users` `u` on(((`t`.`id` = `u`.`team_id`) and (`u`.`role` in ('agent','supervisor','manager'))))) left join `leads` `l` on((`u`.`id` = `l`.`user_id`))) GROUP BY `t`.`id`, `t`.`name` ORDER BY sum((case when (`l`.`status` = 'Closed Deal') then `l`.`price` else 0 end)) DESC ;

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
-- Constraints for table `incentives`
--
ALTER TABLE `incentives`
  ADD CONSTRAINT `incentives_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `memo_person_visibility`
--
ALTER TABLE `memo_person_visibility`
  ADD CONSTRAINT `memo_person_visibility_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memo_person_visibility_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `project_models`
--
ALTER TABLE `project_models`
  ADD CONSTRAINT `project_models_ibfk_1` FOREIGN KEY (`developer_id`) REFERENCES `developers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `raffle_tickets`
--
ALTER TABLE `raffle_tickets`
  ADD CONSTRAINT `raffle_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `raffle_tickets_ibfk_2` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `raffle_tickets_ibfk_3` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
