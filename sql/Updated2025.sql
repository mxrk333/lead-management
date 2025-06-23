-- phpMyAdmin SQL Dump - COMPLETE WITH ALL DATA
-- Database: `real_estate_leads`
-- Fixed for import compatibility with all original data included

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Create database if it doesn't exist
--
CREATE DATABASE IF NOT EXISTS `real_estate_leads` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `real_estate_leads`;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Drop all tables if they exist (in correct order)
DROP TABLE IF EXISTS `memo_read_status`;
DROP TABLE IF EXISTS `memo_person_visibility`;
DROP TABLE IF EXISTS `memo_team_visibility`;
DROP TABLE IF EXISTS `memo_visibility`;
DROP TABLE IF EXISTS `memo_images`;
DROP TABLE IF EXISTS `memos`;
DROP TABLE IF EXISTS `lead_modifications`;
DROP TABLE IF EXISTS `lead_activities`;
DROP TABLE IF EXISTS `downpayment_tracker`;
DROP TABLE IF EXISTS `leads`;
DROP TABLE IF EXISTS `incentives`;
DROP TABLE IF EXISTS `handbook_pages`;
DROP TABLE IF EXISTS `handbooks`;
DROP TABLE IF EXISTS `project_models`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `cities`;
DROP TABLE IF EXISTS `provinces`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `teams`;
DROP TABLE IF EXISTS `developers`;
DROP TABLE IF EXISTS `tour_targets`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `audit_log`;

-- Drop views if they exist
DROP VIEW IF EXISTS `active_leads_summary`;
DROP VIEW IF EXISTS `monthly_sales_report`;
DROP VIEW IF EXISTS `team_performance_summary`;

-- --------------------------------------------------------
-- Table structure for table `audit_log`
-- --------------------------------------------------------

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `teams`
-- --------------------------------------------------------

CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
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
-- Table structure for table `provinces`
-- --------------------------------------------------------

CREATE TABLE `provinces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `region` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`id`, `name`, `region`, `created_at`) VALUES
(11, 'Cavite', NULL, '2025-06-16 03:46:11'),
(12, 'Rizal', NULL, '2025-06-16 05:13:20'),
(13, 'Laguna', NULL, '2025-06-16 05:39:43');

-- --------------------------------------------------------
-- Table structure for table `cities`
-- --------------------------------------------------------

CREATE TABLE `cities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `province_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `province_id` (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) DEFAULT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `position` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Agent',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `team_id` (`team_id`),
  KEY `role` (`role`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `team_id`, `username`, `password`, `name`, `email`, `phone`, `role`, `profile_picture`, `is_active`, `last_login`, `created_at`, `updated_at`, `position`) VALUES
(1, 1, 'admin', '$2y$10$W5MMTTxFbaz/aT8Jc5pH8.NqiRgBvufr4MhDq5eMSTM4.vjE5259C', 'Administrator', 'innersparcservices@gmail.com', NULL, 'admin', NULL, 1, NULL, '2025-05-16 08:45:20', '2025-06-06 07:19:11', 'Agent'),
(3, 8, 'shielamaefajutagana.accounting', '$2y$10$gbLWkM8Lc//nUt5oexmLXOkZSG6PVoIaSMntaSqlgqCB1mlLFRVeG', 'Sheila Mae Fontelo Fajutagana', 'francisheila05@gmail.com', '09151082974', 'agent', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-06 07:36:59', 'Agent'),
(4, 2, 'luzvimindalim.innersparc', '$2y$10$PUV7H0RlWs.dJZ6x8SqmvOFy3x9MlJIo0m7otynHJfAxHnktLhC4u', 'Luzviminda Labrado Lim', 'luz032069@gmail.com', '09062717602', 'supervisor', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-04 22:01:31', 'Agent'),
(5, 3, 'eduardalizatorres.innersparc', '$2y$10$vlTHTcCwPNcRnoG1NVMyO.FjL9e8rnpVuLsgmzZkr3iJriC8UHEw.', 'Eduardaliza Dulay Torres', 'edtorres797426@gmail.com', '09367465749', 'agent', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-17 07:25:19', 'Agent'),
(6, 1, 'mikegabrielescarilla.innersparc', '$2y$10$wQ4nzPYxWM.8smNno/0vIOfwSvYWxq3yphH3y8QCXxK68d3i8uSPG', 'Mike Gabriel Bedion Escarilla', 'escarilla.mikegabriel@gmail.com', '09269979145', 'agent', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-06 07:00:52', 'Agent'),
(7, 1, 'romeocobreta.itdept', '$2y$10$3mHr7EgZpsGkqROQUT6QHOSbSeKcXsgzi/kcbm0yudmRebEfW5d26', 'Romeo Cerna Cobreta Jr.', 'romxcob.innersparc@gmail.com', '09090326945', 'admin', NULL, 1, NULL, '2025-05-16 08:45:21', '2025-06-18 07:01:57', 'Agent'),
(8, 8, 'charlenedellosa.opsman', '$2y$10$7BvBgpFnDmhhX3/zSVikjebJCWefKLpsg36RYdkM0KhZDmjE/Zmmu', 'Charlene Dellosa', 'dellosacharlene1317@gmail.com', '09169994124', 'manager', 'uploads/profile_pictures/user_8_1747501769.png', 1, NULL, '2019-05-16 08:45:23', '2025-06-06 07:31:50', 'Agent'),
(9, 2, 'alvinllaneta.innersparc', '$2y$10$.w9.aPUWHSS5GifQHPn9Fu2.JKn817TupAc0xAEDSekCnwFDH1UiW', 'Alvin  Llaneta', 'alvinllaneta8@gmail.com', '09613825054', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-04 22:01:31', 'Agent'),
(11, 2, 'clarencedanielleserdon.innersparc', '$2y$10$tRBIk1jyaXGFbpAveJE8gOqFpGW.O/hJInU3qJ/mnqr3k/RBneuQK', 'Clarence Danielle Lim Serdon', 'clarencedanielle98@gmail.com', '09996916107', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-04 22:01:31', 'Agent'),
(12, 2, 'gabcyrosebenson.innersparc', '$2y$10$GnAbo83uu0hltK474fV1B.It7N5iLugZXtDFd.5vDRuFqso62qcXS', 'Gabcyrose Samsona Benson', 'gabcyrose@gmail.com', '09263939124', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-04 22:01:31', 'Agent'),
(13, 1, 'lenizapasion.innersparc', '$2y$10$t/pbiM/TxOEK7suqW.8/ruSPdj77rekzZpzLTubXSytzBC5QDiPXa', 'Leniza Flores pasion', 'lenizapasion51@gmail.com', '09177179863', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-06 07:01:28', 'Agent'),
(14, 1, 'perlitago.innersparc', '$2y$10$p4kmD3n.wjSirRlDZpwRaOz69x2uytmxIQcBsgRTPpJynvZ5o1I76', 'Perlita Santiago Go', 'gopearl43@yahoo.com', '09777261123', 'agent', NULL, 1, NULL, '2025-05-16 08:45:23', '2025-06-06 07:01:35', 'Agent'),
(15, 13, 'markpatigayon.intern', '$2y$10$Tbq4hLp0VyDFdxogyElwm.A52Fh.OLnPqgayZTWrAlY4s.V7XCPdi', 'Mark Christian Patigayon', 'markpatigayon440@gmail.com', '09194620030', 'admin', 'uploads/profile_pictures/user_15_1747499626.jpg', 1, NULL, '2019-05-22 08:45:23', '2025-06-06 07:12:38', 'Agent'),
(16, 1, 'verlynvesagas.innersparc', '$2y$10$PEX7IJamKPjc2Mg5859AZuLQjqB/anHrK9ty37.dejDgGTEVazAwy', 'Verlyn Bizconde Vesagas', 'vverlyn@gmail.com', '09915573606', 'agent', NULL, 1, NULL, '2025-05-19 08:08:49', '2025-06-06 07:00:25', 'Agent'),
(17, 1, 'rizelagrimas.innersparc', '$2y$10$ipV2yK499HzPJRz4Bw8G0OMKotbJFfC37LzMtCn1qPimCph6f9Dmy', 'Rize OwogOwog Lagrimas', 'rizielagrimas18@gmail.com', '09202474501', 'agent', NULL, 1, NULL, '2025-05-19 08:09:39', '2025-06-06 07:13:53', 'Agent'),
(18, 1, 'ireneblanca.innersparc', '$2y$10$aEQppVOJLQ3050pCQEKrwepJCT8rw3frtf.AqkgMPqw7TjisrYmli', 'Irene Noble Blanca', 'ireneblanca1909@gmail.com', '09943843721', 'agent', NULL, 1, NULL, '2025-05-19 08:10:13', '2025-06-04 22:01:31', 'Agent'),
(19, 1, 'gabriellibacao.founder', '$2y$10$0A.MhdXz2UAcy4bUGR6EBOsQ82F9GtlZLgCw0a0n46KXTUuoM8t8a', 'Gabriel Jr. Villamor Libacao', 'libacaoga@gmail.com', '09178534875', 'admin', NULL, 1, NULL, '2025-05-19 08:10:58', '2025-06-06 07:31:02', 'Agent'),
(20, 1, 'erwingonzales.cofounder', '$2y$10$/I4c/Yn7lWXRlraQvK0m2ueNwa2vgAfJ1NSKVG9wNfXJUu51fjoeq', 'Erwin Gonzales Baguioan', 'irwindgonzales6@gmail.com', '09669533188', 'manager', NULL, 1, NULL, '2025-05-19 08:11:50', '2025-06-06 07:40:01', 'Agent'),
(21, 1, 'nelynortega.innersparc', '$2y$10$pPrIuezkcJ9THUg5IHmFMuo68tv5W5MhSeLarPb4HIFc2rt5Jj8u.', 'Nelyn Serad Ortega', 'orteganelyn18@gmail.com', '09650984075', 'supervisor', NULL, 1, NULL, '2025-05-19 08:12:41', '2025-06-04 22:01:31', 'Agent'),
(22, 1, 'sarahlopez.innersparc', '$2y$10$HFq2/p7EOrkbn0gFTWoCJOC.0jkJX3rAZj0Rg65xuEfvqa7K3cw7K', 'Sarah Jean Lagatic Lopez', 'sarahjeanlopez07@gmail.com', '09329757344', 'supervisor', NULL, 1, NULL, '2025-05-19 08:13:10', '2025-06-04 22:01:31', 'Agent'),
(23, 2, 'nephelepanganiban', '$2y$10$uZrVk47hzppnbZsFYK/SWOAhQ5e04n/rqm8ti4Hkp7FgvWjq9WrW6', 'Nephele Telmo Panganiban', 'nephelepanganiban@gmail.com', '09662974629', 'agent', NULL, 1, NULL, '2025-05-19 08:18:33', '2025-06-04 22:01:31', 'Agent'),
(24, 2, 'joanbarceta.innersparc', '$2y$10$E.XU5PTwBNn6BryqVpHLTOGGmPOz9V1slWHXBZjzg4YeKC00K9o6K', 'Joan Mahinay Barceta', 'jobarceta22@gmail.com', '09649589052', 'manager', 'uploads/profile_pictures/user_24_1747622923.jpg', 1, NULL, '2025-05-19 08:19:01', '2025-06-04 22:01:31', 'Agent'),
(25, 2, 'teresasandoval.innersparc', '$2y$10$WYxXqgw6uC.9r4ukTgt.SOApN.eDGquy0nbDiJpAkfUHGrMQd2bx2', 'Teresa Rosanto Sandoval', 'trscyl@yahoo.com', '09932967582', 'supervisor', NULL, 1, NULL, '2025-05-19 08:20:20', '2025-06-04 22:01:31', 'Agent'),
(26, 2, 'ailyndetorres.innersparc', '$2y$10$lSuCUikZuhPTgOE3iChWz.ISA0/A91g7LZPbO0ts/CGhagzFId00e', 'Ailyn Llaneta De Torres', 'ailyndetorres8@gmail.com', '09501409792', 'agent', NULL, 1, NULL, '2025-05-19 08:21:11', '2025-06-06 08:09:08', 'Agent'),
(27, 2, 'emilyncantuba.innersparc', '$2y$10$AL8RtPNTg4fhzeCoe8paGuFoFKQspNTe4WuvQmieLbw0BW06OyW/W', 'Emilyn Marcelo Cantuba', 'cantubaemhie@gmail.com', '09362898373', 'agent', NULL, 1, NULL, '2025-05-19 08:21:46', '2025-06-04 22:01:31', 'Agent'),
(28, 2, 'novelitatabudlong.innersparc', '$2y$10$VzOywJiBE7vbcCCgyXyqOO8r5qMiKWJfqXZmINuqXCvyziXMjM3wi', 'Novelita  Letran Tabudlong', 'novzpretty@gmail.com', '09366512502', 'agent', NULL, 1, NULL, '2025-05-19 08:22:15', '2025-06-04 22:01:31', 'Agent'),
(29, 8, 'leodellosa.innersparc', '$2y$10$Cv6hsi.jSxoikgaDi275KO6HQstKWsWIOLA.vIQUfBVCW8BJIX8.i', 'Leonardo  Dellosa', 'kornkulz@gmail.com', '09605661999', 'agent', NULL, 1, NULL, '2025-05-19 08:22:59', '2025-06-18 06:02:47', 'Agent'),
(30, 8, 'arleneumali.innersparc', '$2y$10$bnwqqAGYP9wCmWeFh6ykNO8Nn78VQ5w.7Owzuxy9ES7HTxAubjRQi', 'Arlene Umali', 'arleneumali@example.com', '09159293382', 'agent', NULL, 1, NULL, '2025-05-19 08:24:33', '2025-06-19 13:26:18', 'Agent'),
(31, 12, 'mannyviolenta.innersparc', '$2y$10$q4AysMNRb0HqK6DV.BSHV.rTaEl86RZI2d1SG/24Sgnkg/G7QUfBi', 'Manny Alberto Violenta', 'violentamanny@gmail.com', '09380326931', 'manager', NULL, 1, NULL, '2025-05-19 08:28:42', '2025-06-04 22:01:31', 'Agent'),
(32, 12, 'annalynviolenta.innersparc', '$2y$10$V/Znwb0nP.g1kKNUqZdMyOhPNKu2Z7A2FEmCReSfVeUy5ogANgND2', 'Annalyn Salting Violenta', 'anniemazing2@gmail.com', '09084776982', 'agent', NULL, 1, NULL, '2025-05-19 08:30:01', '2025-06-04 22:01:31', 'Agent'),
(33, 12, 'anelatabuyan.innersparc', '$2y$10$6ZN3s6dR/KaXtstCjmUpuO.4zc4pcfp9sXCCyelzuENqygUA4FxDa', 'Anela Dela Cruz Tabuyan', 'nela.tab5@gmail.com', '09356088954', 'agent', NULL, 1, NULL, '2025-05-19 08:30:31', '2025-06-04 22:01:31', 'Agent'),
(34, 12, 'jocelynsantos.innersparc', '$2y$10$NiykEjnqVjwx5muaon0Jj.EeIC9shVUcnTvUJk.gk.OB3iEQEAdSO', 'Jocelyn Santos', 'jhoymsantos15@gmail.com', '09694569711', 'agent', NULL, 1, NULL, '2025-05-19 08:30:57', '2025-06-04 22:01:31', 'Agent'),
(35, 12, 'lenilyntimajo.innersparc', '$2y$10$bEn5TV2cX/RhHX28meGlK.OY.XDhKJ2FKhQDCPyW8Urc9V4G2ciZm', 'Lenily  Rana Timajo', 'timajolenily@gmail.com', '09129988330', 'supervisor', NULL, 1, NULL, '2025-05-19 08:31:24', '2025-06-04 22:01:31', 'Agent'),
(36, 12, 'jerusalinosantos.innersparc', '$2y$10$hYIses3VZ0VyVq9iFYUYeOrjIwkvzoeZlN9spuoOv6bgXHRZzDGwW', 'Jerusalino Tan Santos', 'jerometsantos28@gmail.com', '09516319674', 'supervisor', NULL, 1, NULL, '2025-05-19 08:32:16', '2025-06-04 22:01:31', 'Agent'),
(37, 12, 'novelynbualat.innersparc', '$2y$10$9QLMoE7w01X5z81AVAx6Bu2DX/AmlL8oJM8d4IhN75ZB4LwTWXn0K', 'Novelyn Macalam  Bualat', 'novelynbualat01@gmail.com', '09281505191', 'agent', NULL, 1, NULL, '2025-05-19 08:33:04', '2025-06-04 22:01:31', 'Agent'),
(38, 12, 'edenrosedemerin.innersparc', '$2y$10$H75lWP/UOgcRwFOWNafMqONdmHhER8ZmXIenG3PTgPrXJocXQYYya', 'Eden Rose Ramos Demerin', 'apostolerogalapino@gmail.com0', '09380196696', 'supervisor', NULL, 1, NULL, '2025-05-19 08:33:27', '2025-06-04 22:01:31', 'Agent'),
(39, 13, 'markbacli.intern', '$2y$10$niHLEGNqlqxrDNGyWTow4eTlk1DoGKYIDvdzDQpjMrtjzivkE4jJC', 'Mark Vincent Bacli', 'markvincentbacli@gmail.com', '09953009113', 'admin', NULL, 1, NULL, '2025-06-02 04:31:33', '2025-06-06 07:10:33', 'Agent'),
(40, 13, 'jeromebadua.intern', '$2y$10$SpXkbMJAeMxZzO3tBAhTcuD9aR1GsSgwtEu52ZU7JKcYfU2AxAhJa', 'Jerome Badua', 'jeromebadua@gmail.com', '09239203920', 'admin', 'uploads/profile_pictures/profile_68467168bda0c.jpg', 1, NULL, '2025-06-02 05:29:54', '2025-06-16 05:43:44', 'Agent'),
(41, 3, 'charitopalonson.innersparc', '$2y$10$ReD4hQktqiS8J3Q1rPmh..jks5ESi/XHHldh6uWKLIAVFrQb9GCje', 'Charito Palonson', 'charitabasbas@gmail.com', '09664380890', 'supervisor', NULL, 1, NULL, '2025-06-02 05:43:02', '2025-06-17 07:22:46', 'Agent'),
(42, 3, 'jesselieabayon.innersparc', '$2y$10$K9vFRytBXD2Obz7wrW3o0e3AHbqp/c01KlAkzXA/oIIsw.O9SZX3O', 'Jesselie Abayon', 'ajesselie44@gmail.com', '09398151934', 'agent', NULL, 1, NULL, '2025-06-02 05:46:27', '2025-06-04 22:01:31', 'Agent'),
(43, 3, 'janaerrolretuya.innersparc', '$2y$10$JWUgo7SPSP7Z1HwIyw99XOxUxiosQTdoiXRyhTFYzuUUOUl0edOda', 'jan Aerrol Retuya', 'janaerrol14@gmail.com', '09062161114', 'agent', NULL, 1, NULL, '2025-06-02 05:49:20', '2025-06-04 22:01:31', 'Agent'),
(44, 3, 'dennisalizano.innersparc', '$2y$10$gj1SxsNFSPmk4FC/fSvoEOjBCJpT3FpmrdWRCRjPEqccxQKW5w0HO', 'Dennisa Anne  Lizano', 'dennisaannelegaspi0721@gmail.com', '09705213040', 'agent', NULL, 1, NULL, '2025-06-02 05:50:45', '2025-06-04 22:01:31', 'Agent'),
(45, 3, 'mercytubania.innersparc', '$2y$10$0i7ymO6rShlh0m4t8IJwnO0MvZcEpXnWXCnjR6LQ8kDMu4MNeb7/G', 'Mercy  Tubania', 'cyro19@yahoo.com', '09988511450', 'agent', NULL, 1, NULL, '2025-06-02 05:52:03', '2025-06-04 22:01:31', 'Agent'),
(46, 3, 'myramagbagay.innersparc', '$2y$10$4.YXvOLH7qdnox0eF2gJOeHhiN5k0el80J92v/qK93GxILc5TjsLK', 'Myra  Magbagay', 'm56249180@gmail.com', '09285751285', 'supervisor', NULL, 1, NULL, '2025-06-02 05:52:59', '2025-06-04 22:01:31', 'Agent'),
(47, 3, 'edselcaraballo.innersparc', '$2y$10$q3wvYP6M3K.DkUI01GWgw.OT9A/aQzqreIKbf.alAIEqNO.sCKIp6', 'Edsel  Caraballo', 'caraballoedsel1@gmail.com', '09816607650', 'agent', NULL, 1, NULL, '2025-06-02 05:55:08', '2025-06-04 22:01:31', 'Agent'),
(48, 3, 'cynthiacaballes.innersparc', '$2y$10$7.JJRwpWIQCThF7jFL2GIOKLqRsAbXTj6KAwljr7Sdd0lXM0JIPRe', 'Cynthia Caballes', 'cynthia.p.caballes@gmail.com', '09177214309', 'manager', NULL, 1, NULL, '2025-06-02 05:56:55', '2025-06-17 11:18:08', 'Agent'),
(49, 3, 'rebeccaresurreccion.innersparc', '$2y$10$Wjr0icOiBYuPWlveaj1Iiu603Zn5TcCHTD5De7bOXaHSTzRydzKMa', 'Rebecca   Resurreccion', 'omrehacceber@gmail.com', '09918715817', 'supervisor', NULL, 1, NULL, '2025-06-02 05:59:34', '2025-06-04 22:01:31', 'Agent'),
(50, 3, 'johnpalonson.innersparc', '$2y$10$XQ16kAT7oCWuboHFAUZSXOXOWCV5BaBnNY6NfvonX.UuDiGq4BoM6', 'John   Palonson', 'mendrosjohn@gmail.com', '09696093699', 'agent', NULL, 1, NULL, '2025-06-02 06:01:56', '2025-06-04 22:01:31', 'Agent'),
(51, 3, 'desireejacosalem.innersparc', '$2y$10$iQXCvPSSPbrPc.4PI7E2L.T51D6cmiPFfmFnP2rjzBa1GptDR7rYG', 'Desiree   Jacosalem', 'dhez_sanchez@yahoo.com', '09567857546', 'agent', NULL, 1, NULL, '2025-06-02 06:03:34', '2025-06-04 22:01:31', 'Agent'),
(52, 3, 'marycorullo.innersparc', '$2y$10$oF2jZFQhw8XVTqCK9FkAu.erWDXuVln/xMtC8Tgc4HChCvSjKTiRW', 'Mary Angeli    Corullo', 'angelicorullo1@gmail.com', '09984721802', 'agent', NULL, 1, NULL, '2025-06-02 06:04:52', '2025-06-04 22:01:31', 'Agent'),
(53, 13, 'yenzogervacio.intern', '$2y$10$MuVoIUcNqQzYT1QEZ2ixpOlF4mUzbo.NZ94ln0QL.ZvER6cFrP1b6', 'Yenzo Teo Gervacio', 'marverygervacio@gmail.com', '09128288333', 'admin', 'uploads/profile_pictures/profile_6846824f5b3d9.jpg', 1, NULL, '2025-06-06 07:03:48', '2025-06-09 06:42:23', 'Agent'),
(54, 13, 'genesiscontreras.intern', '$2y$10$MMjHP.aYMF1IR30LhXIKvughT6jdx1h8JjG7zWNQz8FD9Rb9ZuVRy', 'Genesis Contreras', 'genesiscontreras@gmail.com', '09129382938', 'admin', NULL, 1, NULL, '2025-06-06 07:04:06', '2025-06-06 07:11:40', 'Agent'),
(55, 13, 'angelicarubrico.intern', '$2y$10$K.8eonEZAPaE.SlESF36O./AQX1bCyy2dp04Mi3KxEYSkDt9AoW6K', 'Angelica Rubrico', 'angelica@gmail.com', '09189283283', 'admin', 'uploads/profile_pictures/profile_6846881844a1d.jpg', 1, NULL, '2025-06-06 07:04:25', '2025-06-09 07:07:04', 'Agent'),
(56, 13, 'jerichosantiago.intern', '$2y$10$/ZFXTP37oQPnmQt72u7u8OVC7Or9CItyK99KlzmO1ErPu9CGYSWia', 'Jericho jericho', 'jericho@gmail.com', '09123829382', 'agent', NULL, 1, NULL, '2025-06-06 07:04:40', '2025-06-09 03:35:42', 'Agent'),
(57, 13, 'leonardpistano.intern', '$2y$10$w81317xjdkeDDONYBsL3neTeH6RQgmQB8Bvcq7Ys/r..LN1eMZ3Xa', 'Leonard Pistano', 'leonardpistano@gmail.com', '09827328731', 'admin', 'uploads/profile_pictures/profile_684672216b847.jpg', 1, NULL, '2025-06-06 07:04:59', '2025-06-09 05:33:21', 'Agent'),
(58, 13, 'ginineangelique.intern', '$2y$10$mU/Bxb/5gAq7FgdmcPvZ.u4jTvlF3U480IoSGIOB2d2f2w5TEGP3.', 'Ginine Angelique', 'ginine.innersparc@gmail.com', '09812398129', 'agent', 'uploads/profile_pictures/profile_6846559bbd931.png', 1, NULL, '2025-06-06 07:06:22', '2025-06-09 07:08:35', 'Agent'),
(59, 13, 'danielpagilagan.intern', '$2y$10$cFm.oaARNgZRi60DQUJSp.juOikayA5WOSK1qd4J2sgODY8LJ3TLi', 'Daniel Pagilagan', 'daniel@gmail.com', '09122938298', 'admin', 'uploads/profile_pictures/profile_68468695826b4.jpg', 1, NULL, '2025-06-09 06:57:55', '2025-06-09 07:00:37', 'Agent'),
(61, 1, 'juandelacruz.innersparc', '$2y$10$IcX.QpMAIA84BPCqcHjn7uIwOpGoLfoP00sUjck2nxDYkKTVWNl7.', 'juandelacruz', 'markkksjd@gmail.com', '09182382828', 'agent', NULL, 1, NULL, '2025-06-16 08:39:45', '2025-06-19 12:04:15', 'Agent'),
(62, 13, 'gavrietalaboc.intern', '$2y$10$eomO.MnmsPQ0BWH0IA0w9OVFKPOyJeJivE6lHxtgXzSKf09eXxAHC', 'Yerik Yves Gavrie F. Talaboc', 'example@gmail.com', '00911111111', 'admin', NULL, 1, NULL, '2025-06-23 06:50:04', '2025-06-23 06:50:24', 'Agent'),
(63, 13, 'davidcasil.intern', '$2y$10$i4t/t6koKLRYKzGTOZW3X.8frgiA3Fro6H/9KWVlrdzwM9rdPyyyu', 'David Casil', 'ex@gmail.com', '09111111111', 'admin', NULL, 1, NULL, '2025-06-23 06:51:33', '2025-06-23 06:51:45', 'Agent');

-- --------------------------------------------------------
-- Table structure for table `developers`
-- --------------------------------------------------------

CREATE TABLE `developers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `contact_person` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `is_active` (`is_active`)
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
-- Table structure for table `projects`
-- --------------------------------------------------------

CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `house_model` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('rfo','preselling','ogc') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'preselling',
  `developer` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price_min` decimal(15,2) NOT NULL,
  `price_max` decimal(15,2) NOT NULL,
  `commission` decimal(5,2) NOT NULL DEFAULT '5.00',
  `priority` enum('high','medium','low') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'medium',
  `city_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `exact_location` text COLLATE utf8mb4_general_ci,
  `image1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image3` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image4` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `drive_link` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `messenger_link` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  KEY `price_min` (`price_min`),
  KEY `price_max` (`price_max`),
  KEY `city_id` (`city_id`),
  KEY `province_id` (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `house_model`, `status`, `developer`, `price_min`, `price_max`, `commission`, `priority`, `city_id`, `province_id`, `exact_location`, `image1`, `image2`, `image3`, `image4`, `drive_link`, `messenger_link`, `created_at`, `updated_at`) VALUES
(1, 'Lancaster New City Cavite', 'Premium residential development with modern amenities and strategic location', 'Alice', 'preselling', 'Lancaster', 2500000.00, 4500000.00, 3.00, 'high', 18, 11, 'General Trias, Cavite', 'uploads/projects/lancaster_alice_1.jpg', 'uploads/projects/lancaster_alice_2.jpg', 'uploads/projects/lancaster_alice_3.jpg', 'uploads/projects/lancaster_alice_4.jpg', 'https://drive.google.com/lancaster-alice', 'https://m.me/lancaster.alice', '2025-05-16 09:45:20', '2025-06-16 05:13:45'),
(2, 'Lancaster New City Cavite', 'Premium residential development with modern amenities and strategic location', 'Alexandra', 'preselling', 'Lancaster', 2800000.00, 4800000.00, 3.00, 'high', 18, 11, 'General Trias, Cavite', 'uploads/projects/lancaster_alexandra_1.jpg', 'uploads/projects/lancaster_alexandra_2.jpg', 'uploads/projects/lancaster_alexandra_3.jpg', 'uploads/projects/lancaster_alexandra_4.jpg', 'https://drive.google.com/lancaster-alexandra', 'https://m.me/lancaster.alexandra', '2025-05-16 09:45:20', '2025-06-16 05:13:45'),
(3, 'Antipolo Heights Residences', 'Scenic hillside properties with panoramic city views', 'Hillside Villa', 'rfo', 'Antipolo Heights', 3200000.00, 5500000.00, 4.00, 'medium', 21, 12, 'Antipolo City, Rizal', 'uploads/projects/antipolo_villa_1.jpg', 'uploads/projects/antipolo_villa_2.jpg', 'uploads/projects/antipolo_villa_3.jpg', 'uploads/projects/antipolo_villa_4.jpg', 'https://drive.google.com/antipolo-villa', 'https://m.me/antipolo.villa', '2025-05-16 09:45:20', '2025-06-16 05:22:50'),
(4, 'Pleasantfields Subdivision', 'Family-oriented community with green spaces and recreational facilities', 'Kennedy', 'preselling', 'Pleasantfields', 2200000.00, 3800000.00, 3.50, 'medium', 22, 13, 'Sta. Rosa, Laguna', 'uploads/projects/pleasantfields_kennedy_1.jpg', 'uploads/projects/pleasantfields_kennedy_2.jpg', 'uploads/projects/pleasantfields_kennedy_3.jpg', 'uploads/projects/pleasantfields_kennedy_4.jpg', 'https://drive.google.com/pleasantfields-kennedy', 'https://m.me/pleasantfields.kennedy', '2025-05-19 08:30:11', '2025-06-16 05:39:58'),
(5, 'Bellefort Estate', 'Luxury gated community with world-class facilities and security', 'Bellefort Estate Model A', 'ogc', 'Bellefort Estate', 4500000.00, 8000000.00, 5.00, 'high', 16, 11, 'Trece Martires, Cavite', 'uploads/projects/bellefort_a_1.jpg', 'uploads/projects/bellefort_a_2.jpg', 'uploads/projects/bellefort_a_3.jpg', 'uploads/projects/bellefort_a_4.jpg', 'https://drive.google.com/bellefort-a', 'https://m.me/bellefort.a', '2025-05-19 08:32:10', '2025-06-16 03:46:32'),
(6, 'Elisa Homes Tanza', 'Affordable housing solutions for growing families', 'Elisa Model B', 'preselling', 'Elisa Homes', 1800000.00, 2800000.00, 2.50, 'low', 17, 11, 'Tanza, Cavite', 'uploads/projects/elisa_b_1.jpg', 'uploads/projects/elisa_b_2.jpg', 'uploads/projects/elisa_b_3.jpg', 'uploads/projects/elisa_b_4.jpg', 'https://drive.google.com/elisa-b', 'https://m.me/elisa.b', '2025-05-19 08:45:53', '2025-06-16 03:46:35'),
(7, 'Minami Residence Pilila', 'Japanese-inspired modern living spaces with zen gardens', 'Sakura', 'preselling', 'Minami Residence', 3500000.00, 5200000.00, 4.50, 'medium', 20, 12, 'Pilila, Rizal', 'uploads/projects/minami_sakura_1.jpg', 'uploads/projects/minami_sakura_2.jpg', 'uploads/projects/minami_sakura_3.jpg', 'uploads/projects/minami_sakura_4.jpg', 'https://drive.google.com/minami-sakura', 'https://m.me/minami.sakura', '2025-05-19 08:47:16', '2025-06-16 05:19:30'),
(8, 'Anyana Urban Development', 'Contemporary urban developments with modern architecture', 'Sydney', 'rfo', 'Anyana', 2900000.00, 4200000.00, 3.50, 'medium', 19, 11, 'Imus, Cavite', 'uploads/projects/anyana_sydney_1.jpg', 'uploads/projects/anyana_sydney_2.jpg', 'uploads/projects/anyana_sydney_3.jpg', 'uploads/projects/anyana_sydney_4.jpg', 'https://drive.google.com/anyana-sydney', 'https://m.me/anyana.sydney', '2025-05-19 08:50:22', '2025-06-16 03:46:43'),
(9, 'Anyana Urban Development', 'Contemporary urban developments with modern architecture', 'Florida', 'preselling', 'Anyana', 2600000.00, 3900000.00, 3.50, 'medium', 19, 11, 'Imus, Cavite', 'uploads/projects/anyana_florida_1.jpg', 'uploads/projects/anyana_florida_2.jpg', 'uploads/projects/anyana_florida_3.jpg', 'uploads/projects/anyana_florida_4.jpg', 'https://drive.google.com/anyana-florida', 'https://m.me/anyana.florida', '2025-05-19 08:50:37', '2025-06-16 03:46:43'),
(10, 'Kathleen Place 5', 'Mid-rise condominium developments with urban convenience', 'Tower A', 'ogc', 'Kathleen Place 5', 3800000.00, 6500000.00, 4.00, 'high', 23, 11, 'Naic, Cavite', 'uploads/projects/kathleen_tower_a_1.jpg', 'uploads/projects/kathleen_tower_a_2.jpg', 'uploads/projects/kathleen_tower_a_3.jpg', 'uploads/projects/kathleen_tower_a_4.jpg', 'https://drive.google.com/kathleen-tower-a', 'https://m.me/kathleen.tower.a', '2025-05-19 09:50:31', '2025-06-16 05:47:23'),
(11, 'Liora Homes Bacoor', 'Sustainable and eco-friendly housing with green technology', 'Amora', 'preselling', 'Liora Homes', 2400000.00, 3600000.00, 3.00, 'medium', 18, 11, 'Bacoor, Cavite', 'uploads/projects/liora_amora_1.jpg', 'uploads/projects/liora_amora_2.jpg', 'uploads/projects/liora_amora_3.jpg', 'uploads/projects/liora_amora_4.jpg', 'https://drive.google.com/liora-amora', 'https://m.me/liora.amora', '2025-05-19 09:50:31', '2025-06-16 03:46:39'),
(12, 'Avida Settings Nuvali', 'Trusted name in quality residential developments', 'Way', 'preselling', 'Avida', 3100000.00, 4700000.00, 3.50, 'high', 22, 13, 'Sta. Rosa, Laguna', 'uploads/projects/avida_way_1.jpg', 'uploads/projects/avida_way_2.jpg', 'uploads/projects/avida_way_3.jpg', 'uploads/projects/avida_way_4.jpg', 'https://drive.google.com/avida-way', 'https://m.me/avida.way', '2025-05-19 09:50:31', '2025-06-16 05:39:58');

-- --------------------------------------------------------
-- Table structure for table `project_models`
-- --------------------------------------------------------

CREATE TABLE `project_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `model_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `price` decimal(15,2) NOT NULL,
  `floor_area` decimal(8,2) DEFAULT NULL,
  `lot_area` decimal(8,2) DEFAULT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` int(11) DEFAULT NULL,
  `parking` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `leads`
-- --------------------------------------------------------

CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `client_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `facebook` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `linkedin` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `temperature` enum('Hot','Warm','Cold') COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('Inquiry','Presentation Stage','Negotiation','Closed','Lost','Site Tour','Closed Deal','Requirement Stage','Downpayment Stage','Housing Loan Application','Loan Approval','Loan Takeout','House Inspection','House Turn Over') COLL
ATE utf8mb4_general_ci NOT NULL,
  `source` enum('Facebook Groups','KKK','Facebook Ads','TikTok ads','Google Ads','Facebook live','Referral','Teleprospecting','Video Message','Organic Posting','Email Marketing','Follow up','Manning','Walk in','Flyering','Chat messaging','Property Listing','Landing Page','Networking Events','Organic Sharing','Youtube Marketing','LinkedIn','Open House') COLLATE utf8mb4_general_ci NOT NULL,
  `developer` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `project_model` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT '0.00',
  `expected_commission` decimal(12,2) DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_general_ci,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `temperature` (`temperature`),
  KEY `source` (`source`),
  KEY `follow_up_date` (`follow_up_date`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `user_id`, `client_name`, `phone`, `email`, `facebook`, `linkedin`, `address`, `temperature`, `status`, `source`, `developer`, `project_model`, `price`, `commission_rate`, `expected_commission`, `remarks`, `follow_up_date`, `created_at`, `updated_at`) VALUES
(21, 25, 'Elena Villanueva', '09371234586', 'elena.villanueva@email.com', 'facebook.com/elena.villanueva', 'linkedin.com/in/elena-villanueva', '1717 Katipunan Ave, QC', 'Warm', 'Loan Takeout', 'LinkedIn', 'Liora Homes', 'Amora', 2750000.00, 0.03, 68750.00, 'This is not an accurate data; this is for testing only.', '2025-06-21', '2025-06-21 15:00:00', '2025-06-06 08:05:58'),
(22, 26, 'Rafael Mendoza', '09471234587', 'rafael.mendoza@email.com', 'facebook.com/rafael.mendoza', 'linkedin.com/in/rafael-mendoza', '1818 España St, Manila', 'Cold', 'Loan Approval', 'Referral', 'Lancaster', 'Alice', 2900000.00, 0.03, 87000.00, 'This is not an accurate data; this is for testing only.', '2025-06-24', '2025-06-22 16:30:00', '2025-06-06 08:09:23'),
(23, 27, 'Beatriz Santos', '09571234588', 'beatriz.santos@email.com', 'facebook.com/beatriz.santos', 'linkedin.com/in/beatriz-santos', '1919 Quezon Ave, QC', 'Hot', 'Negotiation', 'Facebook Ads', 'Anyana', 'Sydney', 3200000.00, 0.03, 96000.00, 'This is not an accurate data; this is for testing only.', '2025-06-23', '2025-06-23 17:15:00', '2025-06-06 08:05:15'),
(24, 28, 'Carlos Diaz', '09671234589', 'carlos.diaz@email.com', 'facebook.com/carlos.diaz', 'linkedin.com/in/carlos-diaz', '2020 Magsaysay Blvd, Mandaluyong', 'Warm', 'Negotiation', 'Facebook Ads', 'Lancaster', 'Alice', 2600000.00, 0.03, 65000.00, 'This is not an accurate data; this is for testing only.', '2025-06-25', '2025-06-24 15:45:00', '2025-06-20 05:42:11'),
(27, 58, 'Phoenix Zeta', '09171520934', 'ginine.innersparc@gmail.com', 'https://www.facebook.com/phoenix.zeta', NULL, 'Bacoor, Cavite', 'Hot', 'Inquiry', 'Facebook Groups', 'Lancaster', 'Alice', 2500000.00, 0.03, 75000.00, 'Interested in Lancaster Alice model', '2025-06-30', '2025-06-09 07:09:15', '2025-06-09 07:09:15'),
(28, 59, 'Maria Clara Santos', '09281234567', 'maria.santos@email.com', 'facebook.com/maria.santos', 'linkedin.com/in/maria-santos', '123 Rizal St, Makati', 'Warm', 'Presentation Stage', 'Facebook Ads', 'Antipolo Heights', 'Hillside Villa', 4200000.00, 0.04, 168000.00, 'Scheduled for site visit next week', '2025-07-01', '2025-06-10 08:30:00', '2025-06-10 08:30:00'),
(29, 61, 'Juan Carlos Rodriguez', '09391234568', 'juan.rodriguez@email.com', 'facebook.com/juan.rodriguez', NULL, '456 Bonifacio Ave, Taguig', 'Hot', 'Site Tour', 'Referral', 'Pleasantfields', 'Kennedy', 3000000.00, 0.035, 105000.00, 'Very interested, bringing family for tour', '2025-06-28', '2025-06-11 09:15:00', '2025-06-11 09:15:00'),
(30, 61, 'Ana Beatriz Cruz', '09401234569', 'ana.cruz@email.com', 'facebook.com/ana.cruz', 'linkedin.com/in/ana-cruz', '789 Quezon Blvd, QC', 'Cold', 'Inquiry', 'Google Ads', 'Bellefort Estate', 'Bellefort Estate Model A', 6500000.00, 0.05, 325000.00, 'Initial inquiry, needs more information', '2025-07-05', '2025-06-12 10:00:00', '2025-06-12 10:00:00'),
(31, 62, 'Roberto Miguel Torres', '09511234570', 'roberto.torres@email.com', 'facebook.com/roberto.torres', NULL, '321 Dela Rosa St, Pasig', 'Warm', 'Requirement Stage', 'Facebook live', 'Elisa Homes', 'Elisa Model B', 2300000.00, 0.025, 57500.00, 'Gathering required documents', '2025-06-29', '2025-06-13 11:30:00', '2025-06-13 11:30:00'),
(32, 63, 'Carmen Isabella Lopez', '09621234571', 'carmen.lopez@email.com', 'facebook.com/carmen.lopez', 'linkedin.com/in/carmen-lopez', '654 Maginhawa St, QC', 'Hot', 'Downpayment Stage', 'Teleprospecting', 'Minami Residence', 'Sakura', 4100000.00, 0.045, 184500.00, 'Ready to pay downpayment', '2025-06-26', '2025-06-14 12:45:00', '2025-06-14 12:45:00'),
(33, 3, 'Diego Fernando Reyes', '09731234572', 'diego.reyes@email.com', 'facebook.com/diego.reyes', NULL, '987 Katipunan Ext, QC', 'Warm', 'Housing Loan Application', 'Video Message', 'Anyana', 'Florida', 3200000.00, 0.035, 112000.00, 'Loan application in progress', '2025-07-02', '2025-06-15 13:20:00', '2025-06-15 13:20:00'),
(34, 4, 'Sofia Gabriela Morales', '09841234573', 'sofia.morales@email.com', 'facebook.com/sofia.morales', 'linkedin.com/in/sofia-morales', '147 Ortigas Ave, Pasig', 'Hot', 'Loan Approval', 'Organic Posting', 'Kathleen Place 5', 'Tower A', 5200000.00, 0.04, 208000.00, 'Loan approved, waiting for takeout', '2025-06-27', '2025-06-16 14:10:00', '2025-06-16 14:10:00'),
(35, 5, 'Luis Antonio Fernandez', '09951234574', 'luis.fernandez@email.com', 'facebook.com/luis.fernandez', NULL, '258 Shaw Blvd, Mandaluyong', 'Warm', 'House Inspection', 'Email Marketing', 'Liora Homes', 'Amora', 2900000.00, 0.03, 87000.00, 'House inspection scheduled', '2025-06-25', '2025-06-17 15:00:00', '2025-06-17 15:00:00'),
(36, 6, 'Isabella Carmen Gutierrez', '09061234575', 'isabella.gutierrez@email.com', 'facebook.com/isabella.gutierrez', 'linkedin.com/in/isabella-gutierrez', '369 EDSA, Makati', 'Hot', 'House Turn Over', 'Follow up', 'Avida', 'Way', 3800000.00, 0.035, 133000.00, 'Ready for house turnover', '2025-06-24', '2025-06-18 16:30:00', '2025-06-18 16:30:00'),
(37, 7, 'Miguel Angel Herrera', '09171234576', 'miguel.herrera@email.com', 'facebook.com/miguel.herrera', NULL, '741 Commonwealth Ave, QC', 'Cold', 'Lost', 'Manning', 'Lancaster', 'Alexandra', 3500000.00, 0.03, 105000.00, 'Client decided not to proceed', '2025-07-10', '2025-06-19 17:15:00', '2025-06-19 17:15:00'),
(38, 8, 'Valentina Rosa Jimenez', '09281234577', 'valentina.jimenez@email.com', 'facebook.com/valentina.jimenez', 'linkedin.com/in/valentina-jimenez', '852 Taft Ave, Manila', 'Warm', 'Closed Deal', 'Walk in', 'Antipolo Heights', 'Hillside Villa', 4800000.00, 0.04, 192000.00, 'Successfully closed deal', '2025-06-22', '2025-06-20 18:00:00', '2025-06-20 18:00:00'),
(39, 9, 'Alejandro Jose Martinez', '09391234578', 'alejandro.martinez@email.com', 'facebook.com/alejandro.martinez', NULL, '963 Roxas Blvd, Manila', 'Hot', 'Negotiation', 'Flyering', 'Pleasantfields', 'Kennedy', 2800000.00, 0.035, 98000.00, 'Negotiating final terms', '2025-06-26', '2025-06-21 19:30:00', '2025-06-21 19:30:00'),
(40, 11, 'Camila Esperanza Vargas', '09501234579', 'camila.vargas@email.com', 'facebook.com/camila.vargas', 'linkedin.com/in/camila-vargas', '159 Ayala Ave, Makati', 'Warm', 'Presentation Stage', 'Chat messaging', 'Bellefort Estate', 'Bellefort Estate Model A', 7200000.00, 0.05, 360000.00, 'Interested in luxury features', '2025-07-03', '2025-06-22 20:15:00', '2025-06-22 20:15:00');

-- --------------------------------------------------------
-- Table structure for table `lead_activities`
-- --------------------------------------------------------

CREATE TABLE `lead_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('call','email','meeting','note','status_change','follow_up') COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `activity_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  KEY `user_id` (`user_id`),
  KEY `activity_type` (`activity_type`),
  KEY `activity_date` (`activity_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `lead_modifications`
-- --------------------------------------------------------

CREATE TABLE `lead_modifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `field_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `old_value` text COLLATE utf8mb4_general_ci,
  `new_value` text COLLATE utf8mb4_general_ci,
  `modification_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  KEY `user_id` (`user_id`),
  KEY `modification_date` (`modification_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `downpayment_tracker`
-- --------------------------------------------------------

CREATE TABLE `downpayment_tracker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `reservation_date` date DEFAULT NULL,
  `requirements_complete` tinyint(1) DEFAULT '0',
  `spot_dp` tinyint(1) DEFAULT '0',
  `spot_dp_amount` decimal(12,2) DEFAULT '0.00',
  `dp_terms` enum('6','9','12','15','18','24','36') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `monthly_dp_amount` decimal(12,2) DEFAULT '0.00',
  `current_dp_stage` int(11) DEFAULT '1',
  `total_dp_stages` int(11) DEFAULT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_id` (`lead_id`),
  KEY `reservation_date` (`reservation_date`),
  KEY `next_payment_date` (`next_payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `handbooks`
-- --------------------------------------------------------

CREATE TABLE `handbooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Uncategorized',
  `cover_image` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `pdf_file` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `handbook_pages`
-- --------------------------------------------------------

CREATE TABLE `handbook_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `handbook_id` int(11) NOT NULL,
  `page_number` int(11) NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `caption` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `handbook_id` (`handbook_id`),
  KEY `page_number` (`page_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `incentives`
-- --------------------------------------------------------

CREATE TABLE `incentives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `position` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `total_sales` decimal(15,2) DEFAULT '0.00',
  `incentive_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `destination` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `memos`
-- --------------------------------------------------------

CREATE TABLE `memos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_by` int(11) NOT NULL,
  `visibility_type` enum('all','teams','individuals') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'all',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `visibility_type` (`visibility_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `memo_images`
-- --------------------------------------------------------

CREATE TABLE `memo_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `memo_id` int(11) NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `image_order` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `memo_id` (`memo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `memo_person_visibility`
-- --------------------------------------------------------

CREATE TABLE `memo_person_visibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `memo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `memo_user_unique` (`memo_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `memo_read_status`
-- --------------------------------------------------------

CREATE TABLE `memo_read_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `memo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `memo_user_read_unique` (`memo_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `memo_team_visibility`
-- --------------------------------------------------------

CREATE TABLE `memo_team_visibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `memo_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `memo_team_unique` (`memo_id`,`team_id`),
  KEY `team_id` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `memo_visibility`
-- --------------------------------------------------------

CREATE TABLE `memo_visibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `memo_id` int(11) NOT NULL,
  `visibility_type` enum('team','person') COLLATE utf8mb4_general_ci NOT NULL,
  `target_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `memo_id` (`memo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `settings`
-- --------------------------------------------------------

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `tour_targets`
-- --------------------------------------------------------

CREATE TABLE `tour_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `position` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `total_sales` decimal(15,2) DEFAULT '0.00',
  `target_amount` decimal(15,2) NOT NULL,
  `destination` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `tour_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints
ALTER TABLE `audit_log` ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `cities` ADD CONSTRAINT `cities_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE SET NULL;
ALTER TABLE `users` ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL;
ALTER TABLE `projects` ADD CONSTRAINT `projects_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL;
ALTER TABLE `projects` ADD CONSTRAINT `projects_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE SET NULL;
ALTER TABLE `project_models` ADD CONSTRAINT `project_models_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;
ALTER TABLE `leads` ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `lead_activities` ADD CONSTRAINT `lead_activities_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;
ALTER TABLE `lead_activities` ADD CONSTRAINT `lead_activities_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `lead_modifications` ADD CONSTRAINT `lead_modifications_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;
ALTER TABLE `lead_modifications` ADD CONSTRAINT `lead_modifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `downpayment_tracker` ADD CONSTRAINT `downpayment_tracker_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;
ALTER TABLE `handbooks` ADD CONSTRAINT `handbooks_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `handbook_pages` ADD CONSTRAINT `handbook_pages_ibfk_1` FOREIGN KEY (`handbook_id`) REFERENCES `handbooks` (`id`) ON DELETE CASCADE;
ALTER TABLE `incentives` ADD CONSTRAINT `incentives_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `memos` ADD CONSTRAINT `memos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `memo_images` ADD CONSTRAINT `memo_images_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE;
ALTER TABLE `memo_person_visibility` ADD CONSTRAINT `memo_person_visibility_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE;
ALTER TABLE `memo_person_visibility` ADD CONSTRAINT `memo_person_visibility_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `memo_read_status` ADD CONSTRAINT `memo_read_status_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE;
ALTER TABLE `memo_read_status` ADD CONSTRAINT `memo_read_status_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `memo_team_visibility` ADD CONSTRAINT `memo_team_visibility_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE;
ALTER TABLE `memo_team_visibility` ADD CONSTRAINT `memo_team_visibility_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;
ALTER TABLE `memo_visibility` ADD CONSTRAINT `memo_visibility_ibfk_1` FOREIGN KEY (`memo_id`) REFERENCES `memos` (`id`) ON DELETE CASCADE;
ALTER TABLE `tour_targets` ADD CONSTRAINT `tour_targets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Create views for reporting
CREATE VIEW `active_leads_summary` AS 
SELECT 
  `l`.`id` AS `id`,
  `l`.`client_name` AS `client_name`,
  `l`.`phone` AS `phone`,
  `l`.`email` AS `email`,
  `l`.`temperature` AS `temperature`,
  `l`.`status` AS `status`,
  `l`.`source` AS `source`,
  `l`.`developer` AS `developer`,
  `l`.`project_model` AS `project_model`,
  `l`.`price` AS `price`,
  `l`.`expected_commission` AS `expected_commission`,
  `u`.`name` AS `agent_name`,
  `t`.`name` AS `team_name`,
  `l`.`follow_up_date` AS `follow_up_date`,
  `l`.`created_at` AS `created_at` 
FROM ((`leads` `l` 
  JOIN `users` `u` ON((`l`.`user_id` = `u`.`id`))) 
  LEFT JOIN `teams` `t` ON((`u`.`team_id` = `t`.`id`))) 
WHERE (`l`.`status` NOT IN ('Closed Deal','Lost')) 
ORDER BY `l`.`follow_up_date` ASC, `l`.`created_at` DESC;

CREATE VIEW `monthly_sales_report` AS 
SELECT 
  YEAR(`l`.`updated_at`) AS `year`,
  MONTH(`l`.`updated_at`) AS `month`,
  MONTHNAME(`l`.`updated_at`) AS `month_name`,
  COUNT(CASE WHEN (`l`.`status` = 'Closed Deal') THEN 1 END) AS `deals_closed`,
  SUM(CASE WHEN (`l`.`status` = 'Closed Deal') THEN `l`.`price` ELSE 0 END) AS `total_sales`,
  SUM(CASE WHEN (`l`.`status` = 'Closed Deal') THEN `l`.`expected_commission` ELSE 0 END) AS `total_commission`,
  AVG(CASE WHEN (`l`.`status` = 'Closed Deal') THEN `l`.`price` ELSE NULL END) AS `average_deal_size` 
FROM `leads` AS `l` 
WHERE (`l`.`status` = 'Closed Deal') 
GROUP BY YEAR(`l`.`updated_at`), MONTH(`l`.`updated_at`) 
ORDER BY `year` DESC, `month` DESC;

CREATE VIEW `team_performance_summary` AS 
SELECT 
  `t`.`id` AS `team_id`,
  `t`.`name` AS `team_name`,
  COUNT(DISTINCT `u`.`id`) AS `total_agents`,
  COUNT(`l`.`id`) AS `total_leads`,
  COUNT(CASE WHEN (`l`.`status` = 'Closed Deal') THEN 1 END) AS `closed_deals`,
  SUM(CASE WHEN (`l`.`status` = 'Closed Deal') THEN `l`.`price` ELSE 0 END) AS `total_sales`,
  SUM(CASE WHEN (`l`.`status` = 'Closed Deal') THEN `l`.`expected_commission` ELSE 0 END) AS `total_commission`,
  ROUND(((COUNT(CASE WHEN (`l`.`status` = 'Closed Deal') THEN 1 END) * 100.0) / NULLIF(COUNT(`l`.`id`),0)),2) AS `conversion_rate` 
FROM ((`teams` `t` 
  LEFT JOIN `users` `u` ON(((`t`.`id` = `u`.`team_id`) AND (`u`.`role` IN ('agent','supervisor','manager'))))) 
  LEFT JOIN `leads` `l` ON((`u`.`id` = `l`.`user_id`))) 
GROUP BY `t`.`id`, `t`.`name` 
ORDER BY `total_sales` DESC;

-- Set AUTO_INCREMENT values for tables with data
ALTER TABLE `teams` AUTO_INCREMENT = 14;
ALTER TABLE `provinces` AUTO_INCREMENT = 14;
ALTER TABLE `cities` AUTO_INCREMENT = 26;
ALTER TABLE `users` AUTO_INCREMENT = 64;
ALTER TABLE `developers` AUTO_INCREMENT = 12;
ALTER TABLE `projects` AUTO_INCREMENT = 13;
ALTER TABLE `leads` AUTO_INCREMENT = 41;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
  