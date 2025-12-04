-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 04, 2025 at 03:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kawdu_bill_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `created_at`) VALUES
(3, 'computer parts', '2025-11-12 19:36:19'),
(12, 'dddd', '2025-11-12 20:16:24'),
(13, 'sfsdf', '2025-11-12 20:17:59'),
(14, 'ddasd', '2025-11-12 20:30:28'),
(15, 'sfdasdfjhfdg', '2025-11-12 20:45:44'),
(17, 'softwear', '2025-11-12 21:24:13'),
(18, 'sffasf', '2025-11-12 21:31:52'),
(19, 'computer partsff', '2025-11-12 21:40:00'),
(20, 'dgfdfg', '2025-11-12 21:46:02'),
(21, 'er', '2025-11-12 21:54:37'),
(22, 'vvf', '2025-11-12 21:59:20'),
(23, 'fdg', '2025-11-12 22:04:20'),
(24, 'dsfsd', '2025-11-12 22:05:33'),
(27, 'dgfg', '2025-11-12 22:36:38'),
(28, 'parts', '2025-11-13 17:15:59'),
(30, 'reert', '2025-11-15 17:51:29');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `whatsapp` varchar(15) DEFAULT NULL,
  `rating` int(1) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `client_name`, `address`, `phone`, `email`, `whatsapp`, `rating`) VALUES
(166, 'SAMINDA NANAYAKKARA', '323,Waduweliwitiya (North)1', '0776228943', 'saminda2n@gmail.com', '0776228943', 5),
(168, 'SAMAN KUMARA', '323,Waduweliwitiya (North)', '0776228589', 'saminda2n@gmail.com', '0776228589', 5),
(178, 'Kasun Lakmal', '323,Waduweliwitiya (North)', '0726228943', 'saminda2n@gmail.com', '0726228943', 5),
(180, 'SAMINDA NANAYAKKARAyujh', '323,Waduweliwitiya (North)', '07762283333', 'saminda2n@gmail.com', '077622894388888', 3);

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `client_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `sub_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Paid','Pending','Unpaid') NOT NULL DEFAULT 'Pending',
  `invoice_terms` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `item_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL COMMENT 'Product name at time of sale',
  `serial_number` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Sell price at time of sale',
  `buy_price` decimal(10,2) NOT NULL COMMENT 'Buy price at time of sale'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `buy_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sell_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_code`, `product_name`, `description`, `buy_price`, `sell_price`, `stock_quantity`, `image_path`, `category_id`, `supplier_id`, `created_at`) VALUES
(1, 'KWP-248853', 'ram', 'dfsdfsdf', 0.00, 1950.00, 0, 'uploads/products/prod_69169ddb64662.jpg', 28, 17, '2025-11-12 19:17:32'),
(7, 'KWP-785406', 'Hikvision 1000 VA UPS', '1000 VA / 600 W, 140 VAC to 290 VAC; 85 VAC to 150 VAC, Charging Mode (DC mode): Sounding every 8 seconds\r\n\r\nLow Battery: Sounding every second\r\n\r\nOverloading Mode: Sounding every 0.5 seconds\r\n\r\nFault: Continuously sounding', 12200.00, 14500.00, 96, 'uploads/products/prod_6914f9ca9f9d1.jpg', NULL, 17, '2025-11-12 21:19:06'),
(10, 'KWP-534232', 'dgdfgdfg', 'dfsdfsdf', 0.00, 5500.00, 0, 'uploads/products/prod_69150bc5aeb49.jpg', 17, NULL, '2025-11-12 22:04:39'),
(12, 'KWP-141285', 'Windows Install', 'Windows Install', 2500.00, 1500.00, 9, 'uploads/products/prod_69150df7957fa.png', 17, 17, '2025-11-12 22:21:44'),
(14, 'KWP-284383', 'ytryy', 'yrtyry', 3500.00, 4500.00, 10, 'uploads/products/prod_69150f3fc0a4f.jpg', 19, NULL, '2025-11-12 22:50:27'),
(15, 'KWP-273566', 'fdgdfg', 'yy8', 4500.00, 5500.00, 8, 'uploads/products/prod_69161266227cc.png', 28, NULL, '2025-11-13 17:16:22');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_code` varchar(50) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sell_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_code`, `service_name`, `description`, `sell_price`, `category_id`, `created_at`, `image_path`) VALUES
(1, 'KWS-184292', 'Windows Install', 'sfasfsf', 1500.00, 17, '2025-12-04 14:28:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `contact_no` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_no`, `address`, `created_at`) VALUES
(17, 'SELEX COMPUTER (PVT) LTD', '0714956571', 'KITHULAMPITIYA,GALLE', '2025-11-12 21:19:48'),
(23, 'ORICMO COMPUTERS (PVT) LTD', '0760114298', 'No 75A, Sri Hemanandha Mawatha, Bataganvila, Galle,', '2025-11-14 03:19:13'),
(24, 'ert', '5255', 'erert', '2025-11-15 17:51:40');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('default_warranty_terms', '1. වගකීම් කාලය (Warranty Period) අදාළ භාණ්ඩය සඳහා ඉන්වොයිසියේ සඳහන් දින සිට වලංගු වේ.\r\n2. වගකීම් සේවාව ලබාගැනීම සඳහා මෙම ඉන්වොයිස්පත (Original Invoice) ඉදිරිපත් කිරීම අනිවාර්ය වේ.\r\n3. භෞතික හානි (Physical Damages), දියර හානි (Liquid Damages), විදුලි දෝෂ (Power Issues), සහ අකුණු ගැසීම් (Lightning) සඳහා වගකීම් සේවාව අදාළ නොවේ.\r\n4. මෘදුකාංග (Software) සහ මෙහෙයුම් පද්ධති (OS) සඳහා වගකීම් සේවාව හිමි නොවේ.\r\n5. කිසිඳු හේතුවක් මත මුදල් ආපසු ගෙවීමක් (Refund) සිදු නොකරනු ලැබේ.\r\n6. වගකීම් කාලය තුළ අලුත්වැඩියා කරනු ලබන භාණ්ඩ නැවත භාරදීමේදී සිදුවන ප්‍රමාද දෝෂ සඳහා ආයතනය වගකියනු නොලැබේ.'),
('invoice_footer_credit', '© software developed by : KAWDU TECHNOLOGY - 0776 228 943 | 0786 228 943'),
('show_footer_credit', '1'),
('show_warranty_on_invoice', '1');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `user_type` enum('Admin','User') NOT NULL DEFAULT 'Admin',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `name`, `user_type`, `status`, `created_at`) VALUES
(2, 'admin', '$2y$10$Rn4WaXMhzr3WlCyuaD/cOONF3gDCMsdhz52as2x0VZL6Hd8UUYLpi', 'SAMINDA NANAYAKKARA', 'Admin', 'Active', '2025-11-10 19:27:17'),
(3, 'user', '$2y$10$Zis/zfPCGsPc2ptk.NW1geJo7wwqSBweNPOuX1ilK6kI21/kFEZTe', 'user', 'User', 'Active', '2025-11-10 19:39:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`),
  ADD UNIQUE KEY `UQ_Client_Name_New` (`client_name`),
  ADD UNIQUE KEY `uq_phone_constraint` (`phone`),
  ADD UNIQUE KEY `uq_whatsapp_number` (`whatsapp`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `service_code` (`service_code`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD UNIQUE KEY `supplier_name` (`supplier_name`),
  ADD UNIQUE KEY `contact_no` (`contact_no`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
