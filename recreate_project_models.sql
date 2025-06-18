-- Drop and recreate project_models table with correct structure
DROP TABLE IF EXISTS `project_models`;

CREATE TABLE `project_models` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `developer_id` (`developer_id`),
  CONSTRAINT `project_models_ibfk_1` FOREIGN KEY (`developer_id`) REFERENCES `developers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample project models based on existing developers
INSERT INTO `project_models` (`developer_id`, `name`, `description`, `base_price`) VALUES
(1, 'Alice', 'Premium residential unit', 2900000.00),
(1, 'Alexandra', 'Luxury family home', 8500000.00),
(1, 'Briana', 'Modern townhouse', 2700000.00),
(2, 'Antipolo Heights Model A', 'Scenic hillside property', 3200000.00),
(3, 'Kennedy', 'Family-oriented townhouse', 2700000.00),
(3, 'Lincoln', 'Spacious family home', 3500000.00),
(3, 'Nyxon', 'Modern residential unit', 3200000.00),
(4, 'Bellefort Estate Model A', 'Luxury gated community home', 4500000.00),
(6, 'Sapphire', 'Affordable housing solution', 10000000.00),
(6, 'Pearl', 'Family townhouse', 10000000.00),
(7, 'Hana', 'Japanese-inspired modern living', 3100000.00),
(8, 'Paris', 'Contemporary urban development', 8000000.00),
(8, 'Sydney', 'Modern city living', 12000000.00),
(8, 'Tokyo', 'Urban lifestyle home', 14000000.00),
(8, 'Florida', 'Spacious family residence', 16000000.00),
(9, 'Kathleen Place Model A', 'Mid-rise condominium', 5900000.00),
(10, 'Amora', 'Sustainable eco-friendly housing', 2300000.00),
(11, 'Way', 'Trusted quality development', 100000.00); 