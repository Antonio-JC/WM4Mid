

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";




CREATE TABLE `contract_items` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `daily_rate_at_checkout` decimal(10,2) NOT NULL,
  `returned` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



INSERT INTO `contract_items` (`id`, `contract_id`, `inventory_id`, `daily_rate_at_checkout`, `returned`) VALUES
(1, 1, 1, 1200.00, 1),
(2, 2, 1, 1200.00, 0);



CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



INSERT INTO `customers` (`id`, `name`, `id_number`, `phone`, `created_at`) VALUES
(1, 'JC antonio', '12345', '09123456789', '2026-09-25 11:46:59'),
(2, 'rere', '12346', '09123456780', '2026-09-25 12:07:02');



CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `daily_rate` decimal(10,2) NOT NULL,
  `status` enum('Available','Rented','Maintenance') NOT NULL DEFAULT 'Available',
  `serial_number` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



INSERT INTO `inventory` (`id`, `item_name`, `daily_rate`, `status`, `serial_number`, `created_at`) VALUES
(1, 'tsikot', 1200.00, 'Rented', 'w7123', '2026-09-25 11:55:48'),
(6, 'tormots', 1599.00, 'Available', 'w7124', '2026-09-25 12:02:26');



CREATE TABLE `rental_contracts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `checkout_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Active','Returned','Overdue') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



INSERT INTO `rental_contracts` (`id`, `customer_id`, `checkout_date`, `due_date`, `return_date`, `deposit_amount`, `status`, `created_at`) VALUES
(1, 1, '2026-09-25', '2026-09-30', '2026-09-25', 1200.00, 'Returned', '2026-09-25 12:03:44'),
(2, 2, '2026-09-24', '2026-09-24', NULL, 1000.00, 'Active', '2026-09-25 12:07:36');


ALTER TABLE `contract_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_contract_inventory` (`contract_id`,`inventory_id`),
  ADD KEY `fk_items_inventory` (`inventory_id`);


ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_customers_id_number` (`id_number`);


ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_serial` (`serial_number`),
  ADD KEY `idx_inventory_status` (`status`);


ALTER TABLE `rental_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_contracts_customer` (`customer_id`),
  ADD KEY `idx_contracts_status` (`status`),
  ADD KEY `idx_contracts_due_date` (`due_date`);


ALTER TABLE `contract_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;


ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;


ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;


ALTER TABLE `rental_contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;


ALTER TABLE `contract_items`
  ADD CONSTRAINT `fk_items_contract` FOREIGN KEY (`contract_id`) REFERENCES `rental_contracts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`);


ALTER TABLE `rental_contracts`
  ADD CONSTRAINT `fk_contracts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);
COMMIT;


