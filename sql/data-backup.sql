CREATE TABLE `project_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `developer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `developer_id` (`developer_id`),
  CONSTRAINT `project_models_ibfk_1` FOREIGN KEY (`developer_id`) REFERENCES `developers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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