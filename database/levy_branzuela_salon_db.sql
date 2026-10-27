-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 16, 2026 at 03:49 PM
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
-- Database: `salon_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_accounts`
--

CREATE TABLE `admin_accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_accounts`
--

INSERT INTO `admin_accounts` (`id`, `username`, `password`, `full_name`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'Salon Administrator', 1, '2026-03-13 13:33:27');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_reference` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `service_name` varchar(500) NOT NULL,
  `services_json` text DEFAULT NULL,
  `service_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `service_duration` int(11) NOT NULL DEFAULT 0,
  `total_duration` int(11) NOT NULL DEFAULT 0,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `room_type` varchar(50) NOT NULL DEFAULT 'salon',
  `room_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `therapist_id` varchar(50) DEFAULT 'tba',
  `payment_method` varchar(50) NOT NULL DEFAULT 'salon',
  `health_info` text DEFAULT NULL,
  `email_sent` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_reference`, `user_id`, `full_name`, `email`, `phone`, `service_name`, `services_json`, `service_price`, `service_duration`, `total_duration`, `booking_date`, `booking_time`, `room_type`, `room_price`, `total_price`, `therapist_id`, `payment_method`, `health_info`, `email_sent`, `status`, `created_at`, `updated_at`) VALUES
(44, 'LBS20261005', 4, 'Lawrence Fortin', 'lawrencefortin2002@gmail.com', '09922127374', 'Hair Coloring, Hair Cutting', '[{\"id\":\"15\",\"name\":\"Hair Coloring\",\"price\":1800,\"duration\":120},{\"id\":\"16\",\"name\":\"Hair Cutting\",\"price\":350,\"duration\":60}]', 2150.00, 180, 180, '2026-08-25', '14:00:00', 'salon', 0.00, 2150.00, '18', 'salon', NULL, 1, 'completed', '2026-08-24 16:09:24', '2026-08-24 16:10:23'),
(45, 'LBS20269451', 4, 'Lawrence Fortin', 'lawrencefortin2002@gmail.com', '09922127374', 'Hair Cutting, Hair Spa Treatments', '[{\"id\":\"16\",\"name\":\"Hair Cutting\",\"price\":350,\"duration\":60},{\"id\":\"20\",\"name\":\"Hair Spa Treatments\",\"price\":900,\"duration\":60}]', 1250.00, 120, 120, '2026-08-25', '15:00:00', 'salon', 0.00, 1250.00, 'tba', 'salon', NULL, 1, 'cancelled', '2026-08-24 16:11:48', '2026-08-24 16:13:17'),
(46, 'LBS20266961', 5, 'jericho', 'lawrencefortin2002@gmail.com', '09667258616', 'Micro Blading, Hair &amp; Make-up, Hair Coloring, Nail Design, Waxing', '[{\"id\":\"22\",\"name\":\"Micro Blading\",\"price\":3500,\"duration\":60},{\"id\":\"14\",\"name\":\"Hair &amp; Make-up\",\"price\":3500,\"duration\":90},{\"id\":\"15\",\"name\":\"Hair Coloring\",\"price\":1800,\"duration\":120},{\"id\":\"24\",\"name\":\"Nail Design\",\"price\":200,\"duration\":60},{\"id\":\"11\",\"name\":\"Waxing\",\"price\":400,\"duration\":60}]', 9400.00, 390, 390, '2026-08-25', '09:00:00', 'salon', 0.00, 9400.00, '18', 'salon', NULL, 1, 'completed', '2026-08-25 09:56:34', '2026-08-25 09:59:53'),
(47, 'LBS20269311', 5, 'jericho', 'lawrencefortin2002@gmail.com', '09667258616', 'Waxing', '[{\"id\":\"11\",\"name\":\"Waxing\",\"price\":400,\"duration\":60}]', 400.00, 60, 60, '2026-08-25', '09:00:00', 'salon', 0.00, 400.00, '17', 'salon', NULL, 1, 'confirmed', '2026-08-25 10:02:38', '2026-08-25 10:04:26');

-- --------------------------------------------------------

--
-- Table structure for table `customer_accounts`
--

CREATE TABLE `customer_accounts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_accounts`
--

INSERT INTO `customer_accounts` (`id`, `name`, `email`, `phone`, `password`, `created_at`) VALUES
(2, 'Levy', 'levybranzuela@yahoo.com', '09175143849', '$2y$10$zMiVz/DNaX1uhmaZ87dgxuudYn7QtN0Lv3u6nb.BqV9ycIPEl4.tS', '2026-04-21 03:54:56'),
(5, 'jericho', 'lawrencefortin2002@gmail.com', '09667258616', '$2y$10$ZY.CnltVGEt3WRDDuYh/XOlcC.boomf8dsQ0ijEX7Quk6kTbnuVym', '2026-08-25 09:51:04');

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `daily_sales_summary` (
`sale_date` date
,`total_transactions` bigint(21)
,`total_sales` decimal(32,2)
,`staff_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `low_stock_products`
-- (See below for the actual view)
--
CREATE TABLE `low_stock_products` (
`product_id` int(11)
,`product_name` varchar(100)
,`category_name` varchar(50)
,`quantity` int(11)
,`low_stock_threshold` int(11)
,`price` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('sale_pending','sale_approved','sale_rejected','info') DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `title`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(1, 1, 'sale_pending', 'New Sale Pending Approval', 'Lawrence Fortin submitted a sale of ₱1,500.00 for Product', 5, 0, '2026-02-27 03:40:45'),
(2, 1, 'sale_pending', 'New Sale Pending Approval', 'Lawrence Fortin submitted a sale of ₱120.00 for Product', 8, 0, '2026-02-27 04:11:29'),
(3, 1, 'sale_pending', 'New Sale Pending Approval', 'Lawrence Fortin submitted a sale of ₱350.00 for Hair color', 9, 0, '2026-02-27 04:14:29'),
(4, 1, 'sale_pending', 'New Sale Pending Approval', 'Lawrence Fortin submitted a sale of ₱450.00 for Product', 10, 0, '2026-02-27 04:22:42'),
(5, 1, 'sale_pending', 'New Sale Pending Approval', 'Lawrence Fortin submitted a sale of ₱350.00 for Hair color', 12, 0, '2026-02-27 04:46:08'),
(6, 1, 'sale_pending', 'New Sale Pending Approval', 'Lawrence Fortin submitted a sale of ₱250.00 for Product', 13, 0, '2026-02-27 04:50:59'),
(7, 1, 'sale_pending', 'New Sale Pending Approval', 'Lawrence Fortin submitted a sale of ₱800.00 for Product', 15, 0, '2026-02-27 05:06:00'),
(8, 1, 'sale_pending', 'New Sale Pending Approval', 'Chloe Nabos submitted a sale of ₱250.00 for Product', 16, 0, '2026-02-27 05:12:03'),
(9, 1, 'sale_pending', 'New Sale Pending Approval', 'Tanjiro Kamado submitted a sale of ₱500.00 for Fire breathing hair color', 17, 0, '2026-02-27 06:35:39'),
(10, 1, 'sale_pending', 'New Sale Pending Approval', 'Tanjiro Kamado submitted a sale of ₱1,500.00 for Fire breathing hair color', 18, 0, '2026-03-03 11:10:36');

-- --------------------------------------------------------

--
-- Stand-in structure for view `pending_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `pending_sales_summary` (
`pending_count` bigint(21)
,`pending_total` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 10,
  `status` enum('available','out_of_stock') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `category_id`, `price`, `quantity`, `low_stock_threshold`, `status`, `created_at`, `updated_at`) VALUES
(1, 'LOréal Vitamino Color Resveratrol Shampoo (300ml)', 1, 999.00, 20, 5, 'available', '2026-02-09 04:16:53', '2026-08-12 06:35:55'),
(2, 'LOréal Professionnel Serioxyl Advanced Denser Hair Serum (90ml)', 1, 2179.00, 20, 5, 'available', '2026-02-09 04:16:53', '2026-08-11 18:03:50'),
(13, 'LOréal Vitamino Color Spectrum Sulfate-Free Shampoo (300ml)', 1, 1479.00, 20, 5, 'available', '2026-08-11 18:06:46', '2026-08-11 18:06:46'),
(14, 'LOréal Vitamino Color Shampoo (Large Salon Size, 1500ml)', 1, 1995.00, 10, 5, 'available', '2026-08-11 18:07:48', '2026-08-11 18:07:48'),
(15, 'L Oréal Professionnel Serie Expert Silver Shampoo', 1, 999.00, 9, 5, 'available', '2026-08-11 18:12:50', '2026-08-25 10:09:16');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`category_id`, `category_name`, `created_at`) VALUES
(1, 'Hair Products', '2026-02-09 04:16:52'),
(2, 'Skin Products', '2026-02-09 04:16:52'),
(3, 'Nail Products', '2026-02-09 04:16:52');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `manual_staff_name` varchar(150) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `receipt_no` varchar(30) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','online') DEFAULT 'cash',
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `sale_date`, `staff_id`, `manual_staff_name`, `customer_name`, `receipt_no`, `product_id`, `service_name`, `quantity`, `unit_price`, `total_amount`, `payment_method`, `status`, `approved_by`, `approved_at`, `created_at`) VALUES
(91, '2026-08-25', 18, NULL, 'jericho', 'BK-LBS20266961', NULL, 'Micro Blading, Hair &amp; Make-up, Hair Coloring, Nail Design, Waxing', 1, 9400.00, 9400.00, 'cash', 'approved', NULL, '2026-08-25 09:59:53', '2026-08-25 09:59:53'),
(92, '2026-08-25', NULL, NULL, NULL, 'RCP-20260825-5CC24', 15, 'L Oréal Professionnel Serie Expert Silver Shampoo x1', 1, 999.00, 999.00, 'cash', 'approved', NULL, '2026-08-25 10:09:16', '2026-08-25 10:09:16');

-- --------------------------------------------------------

--
-- Table structure for table `salon_services`
--

CREATE TABLE `salon_services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category` varchar(100) DEFAULT 'General',
  `duration` int(11) NOT NULL DEFAULT 60,
  `icon` varchar(10) DEFAULT '?',
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salon_services`
--

INSERT INTO `salon_services` (`service_id`, `service_name`, `price`, `category`, `duration`, `icon`, `description`, `status`, `created_at`) VALUES
(11, 'Waxing', 400.00, 'Skin', 60, '', NULL, 'active', '2026-03-07 06:58:56'),
(14, 'Hair & Make-up', 3500.00, 'Hair Services', 90, '?', NULL, 'active', '2026-03-10 04:54:51'),
(15, 'Hair Coloring', 1800.00, 'Hair Services', 120, '?', NULL, 'active', '2026-03-10 04:54:51'),
(16, 'Hair Cutting', 350.00, 'Hair Services', 60, '?', NULL, 'active', '2026-03-10 04:54:51'),
(20, 'Hair Spa Treatments', 900.00, 'Hair Services', 60, '?', NULL, 'active', '2026-03-10 06:49:53'),
(22, 'Micro Blading', 3500.00, 'Eyebrows', 60, '?', NULL, 'active', '2026-03-10 06:51:36'),
(23, 'Shading', 2500.00, 'Eyebrows', 60, '?', NULL, 'active', '2026-03-10 06:51:48'),
(24, 'Nail Design', 200.00, 'Nails', 60, '?', NULL, 'active', '2026-03-10 06:52:19'),
(25, 'Eye Lash Extensions', 1500.00, 'Eyebrows', 60, '?', NULL, 'active', '2026-03-10 06:52:48'),
(26, 'Manicure', 250.00, 'Nails', 60, '?', NULL, 'active', '2026-03-10 07:18:11'),
(27, 'Pedicure', 250.00, 'Nails', 60, '?', NULL, 'active', '2026-03-10 07:18:20');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `job_title` varchar(50) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `full_name`, `contact_number`, `address`, `job_title`, `rate`, `status`, `created_at`, `updated_at`) VALUES
(17, 'Ruby Rose Santos', '09669917830', 'Lot 4 Palanyag Road, Gatchalian 2, Brgy. San Dionisio, Paranaque City', 'Hair Stylist', 50.00, 'active', '2026-05-11 12:03:24', '2026-05-11 12:03:24'),
(18, 'Jenalyn Barin', '093792016961', 'Lot 4 Palanyag Road, Gatchalian 2, Brgy. San Dionisio, Paranaque City', 'Nail Technician', 50.00, 'active', '2026-05-11 12:24:56', '2026-05-11 12:24:56');

-- --------------------------------------------------------

--
-- Table structure for table `staff_duties`
--

CREATE TABLE `staff_duties` (
  `duty_id` int(11) NOT NULL,
  `duty_date` date NOT NULL,
  `staff_id` int(11) NOT NULL,
  `customers_treated` int(11) DEFAULT 0,
  `additional_earnings` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_duties`
--

INSERT INTO `staff_duties` (`duty_id`, `duty_date`, `staff_id`, `customers_treated`, `additional_earnings`, `notes`, `created_at`, `updated_at`) VALUES
(6, '2026-06-10', 18, 10, 350.00, '', '2026-06-10 11:24:43', '2026-06-10 11:24:43');

-- --------------------------------------------------------

--
-- Table structure for table `staff_notifications`
--

CREATE TABLE `staff_notifications` (
  `notif_id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_notifications`
--

INSERT INTO `staff_notifications` (`notif_id`, `submission_id`, `staff_id`, `staff_name`, `message`, `is_read`, `created_at`) VALUES
(1, 3, 2, 'Lawrence Fortin', 'Lawrence Fortin submitted a report on Mar 09, 2026 at 5:38 AM. 1 entries totalling ₱100.00. Items: haircut (₱100.00)', 1, '2026-03-09 12:38:40'),
(2, 2, 2, 'Lawrence Fortin', 'Lawrence Fortin sent a completed report (dated Mar 09, 2026). 2 entries totalling ₱350.00. Items: Haircut (₱100.00); Pedicure (₱250.00)', 1, '2026-03-09 12:45:41'),
(3, 4, 2, 'Lawrence Fortin', 'Lawrence Fortin sent a completed report (dated Mar 09, 2026). 1 entries totalling ₱1,000.00. Items: Rebonding (₱1,000.00)', 1, '2026-03-09 13:15:50'),
(4, 5, 2, 'Lawrence Fortin', 'Lawrence Fortin sent a completed report (dated Mar 09, 2026). 1 entries totalling ₱1,500.00. Items: Haircut with Pedicure and Facial Treatment (₱1,500.00)', 1, '2026-03-09 13:32:16'),
(5, 6, 2, 'Lawrence Fortin', 'Lawrence Fortin sent a completed report (dated Mar 09, 2026). 1 entries totalling ₱1,500.00. Items: Haircut with Spa and Pedicure (₱1,500.00)', 1, '2026-03-09 13:44:55'),
(6, 7, 2, 'Lawrence Fortin', 'Lawrence Fortin sent a completed report (dated Mar 09, 2026). 1 entries totalling ₱1,500.00. Items: Hair color with facial wash and buying premium shampoo (₱1,500.00)', 1, '2026-03-09 13:54:57'),
(7, 8, 2, 'Lawrence Fortin', 'Lawrence Fortin sent a completed report (dated Mar 10, 2026). 1 entries totalling ₱600.00. Items: Haircut, Pedicure (₱600.00)', 1, '2026-03-10 19:37:01');

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff_performance`
-- (See below for the actual view)
--
CREATE TABLE `staff_performance` (
`staff_id` int(11)
,`full_name` varchar(100)
,`job_title` varchar(50)
,`days_worked` bigint(21)
,`total_customers` decimal(32,0)
,`total_additional_earnings` decimal(32,2)
,`total_sales_transactions` bigint(21)
,`total_sales_amount` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `staff_reports`
--

CREATE TABLE `staff_reports` (
  `report_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(150) NOT NULL,
  `service_item` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `entry_date` date NOT NULL,
  `entry_time` time NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `submission_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_reports`
--

INSERT INTO `staff_reports` (`report_id`, `staff_id`, `staff_name`, `service_item`, `price`, `entry_date`, `entry_time`, `notes`, `created_at`, `submission_id`, `customer_name`) VALUES
(3, 2, 'Renzz', 'Haircut', 100.00, '2026-03-09', '11:57:00', 'none', '2026-03-09 12:09:28', NULL, ''),
(4, 2, 'Chloe', 'Pedicure', 250.00, '2026-03-09', '11:58:00', 'none', '2026-03-09 12:09:28', NULL, ''),
(5, 2, 'Renzz', 'Haircut', 100.00, '2026-03-09', '00:17:00', 'none', '2026-03-09 12:18:05', 2, ''),
(6, 2, 'Maine', 'Pedicure', 250.00, '2026-03-09', '00:17:00', 'none', '2026-03-09 12:18:05', 2, ''),
(8, 2, 'Renzz', 'Rebonding', 1000.00, '2026-03-09', '13:15:00', 'sdsdasdasdasdsd', '2026-03-09 13:15:42', 4, ''),
(9, 2, 'Renzz', 'Haircut with Pedicure and Facial Treatment', 1500.00, '2026-03-09', '06:31:00', '', '2026-03-09 13:32:05', 5, ''),
(10, 2, 'Lawrence Fortin', 'Haircut with Spa and Pedicure', 1500.00, '2026-03-09', '06:43:00', 'none', '2026-03-09 13:44:47', 6, 'Chloe');

-- --------------------------------------------------------

--
-- Table structure for table `staff_report_drafts`
--

CREATE TABLE `staff_report_drafts` (
  `draft_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `row_order` int(11) NOT NULL DEFAULT 0,
  `service_item` varchar(255) NOT NULL DEFAULT '',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `entry_date` date DEFAULT NULL,
  `entry_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `staff_name` varchar(150) NOT NULL DEFAULT '',
  `customer_name` varchar(150) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_report_submissions`
--

CREATE TABLE `staff_report_submissions` (
  `submission_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(150) NOT NULL,
  `report_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_entries` int(11) NOT NULL DEFAULT 0,
  `status` enum('completed','sent','seen') NOT NULL DEFAULT 'completed',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff_with_accounts`
-- (See below for the actual view)
--
CREATE TABLE `staff_with_accounts` (
`staff_id` int(11)
,`full_name` varchar(100)
,`contact_number` varchar(20)
,`job_title` varchar(50)
,`status` enum('active','inactive')
,`account_id` int(11)
,`username` varchar(50)
,`role` varchar(20)
,`account_active` tinyint(1)
,`has_account` varchar(3)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'staff',
  `job_title` varchar(50) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `contact_number`, `address`, `role`, `job_title`, `rate`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$hHQylAnIyxiOalLjELVOMOB7FWixhsT6gmQdDuSVXghVOHHZCbDvW', 'Admin', NULL, NULL, 'admin', NULL, 0.00, 'active', '2026-02-08 20:24:42', '2026-02-16 05:48:01'),
(2, 'staff1', '$2y$10$d1XQazNqbOEU/J1l/CCkgOHi1CO0vcAR2KAmsb2SwyPLH1214EbMG', 'Lawrence Fortin', '0915519977', 'Moonwalk, Paranaque City', 'staff', 'Hair Specialist', 50.00, 'active', '2026-02-08 20:28:16', '2026-02-15 01:37:43');

-- --------------------------------------------------------

--
-- Table structure for table `user_accounts`
--

CREATE TABLE `user_accounts` (
  `account_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_accounts`
--

INSERT INTO `user_accounts` (`account_id`, `staff_id`, `username`, `password`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(6, 17, 'staff1', '$2y$10$ap2BdG1k3vbALG2Cr9ZgX.13lLwGUXY0e0BWxJpxTr77GPvi6ZLm2', 'staff', 1, '2026-08-11 10:55:45', '2026-05-11 12:07:35', '2026-08-11 10:55:45');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `permission_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `can_access_inventory` tinyint(1) DEFAULT 0,
  `can_view_reports` tinyint(1) DEFAULT 0,
  `can_view_my_reports` tinyint(1) NOT NULL DEFAULT 0,
  `can_access_pos` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`permission_id`, `account_id`, `can_access_inventory`, `can_view_reports`, `can_view_my_reports`, `can_access_pos`) VALUES
(5, 6, 1, 0, 1, 0);

-- --------------------------------------------------------

--
-- Structure for view `daily_sales_summary`
--
DROP TABLE IF EXISTS `daily_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_sales_summary`  AS SELECT `sales`.`sale_date` AS `sale_date`, count(0) AS `total_transactions`, sum(`sales`.`total_amount`) AS `total_sales`, count(distinct `sales`.`staff_id`) AS `staff_count` FROM `sales` GROUP BY `sales`.`sale_date` ORDER BY `sales`.`sale_date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `low_stock_products`
--
DROP TABLE IF EXISTS `low_stock_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `low_stock_products`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `c`.`category_name` AS `category_name`, `p`.`quantity` AS `quantity`, `p`.`low_stock_threshold` AS `low_stock_threshold`, `p`.`price` AS `price` FROM (`products` `p` left join `product_categories` `c` on(`p`.`category_id` = `c`.`category_id`)) WHERE `p`.`quantity` <= `p`.`low_stock_threshold` ORDER BY `p`.`quantity` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `pending_sales_summary`
--
DROP TABLE IF EXISTS `pending_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `pending_sales_summary`  AS SELECT count(0) AS `pending_count`, coalesce(sum(`sales`.`total_amount`),0) AS `pending_total` FROM `sales` WHERE `sales`.`status` = 'pending' ;

-- --------------------------------------------------------

--
-- Structure for view `staff_performance`
--
DROP TABLE IF EXISTS `staff_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `staff_performance`  AS SELECT `s`.`staff_id` AS `staff_id`, `s`.`full_name` AS `full_name`, `s`.`job_title` AS `job_title`, count(distinct `sd`.`duty_date`) AS `days_worked`, sum(`sd`.`customers_treated`) AS `total_customers`, sum(`sd`.`additional_earnings`) AS `total_additional_earnings`, count(`sa`.`sale_id`) AS `total_sales_transactions`, coalesce(sum(`sa`.`total_amount`),0) AS `total_sales_amount` FROM ((`staff` `s` left join `staff_duties` `sd` on(`s`.`staff_id` = `sd`.`staff_id`)) left join `sales` `sa` on(`s`.`staff_id` = `sa`.`staff_id`)) WHERE `s`.`status` = 'active' GROUP BY `s`.`staff_id`, `s`.`full_name`, `s`.`job_title` ;

-- --------------------------------------------------------

--
-- Structure for view `staff_with_accounts`
--
DROP TABLE IF EXISTS `staff_with_accounts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `staff_with_accounts`  AS SELECT `s`.`staff_id` AS `staff_id`, `s`.`full_name` AS `full_name`, `s`.`contact_number` AS `contact_number`, `s`.`job_title` AS `job_title`, `s`.`status` AS `status`, `ua`.`account_id` AS `account_id`, `ua`.`username` AS `username`, `ua`.`role` AS `role`, `ua`.`is_active` AS `account_active`, CASE WHEN `ua`.`account_id` is not null THEN 'Yes' ELSE 'No' END AS `has_account` FROM (`staff` `s` left join `user_accounts` `ua` on(`s`.`staff_id` = `ua`.`staff_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `idx_booking_date` (`booking_date`),
  ADD KEY `idx_booking_status` (`status`),
  ADD KEY `idx_booking_user` (`user_id`);

--
-- Indexes for table `customer_accounts`
--
ALTER TABLE `customer_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `idx_sale_date` (`sale_date`),
  ADD KEY `idx_receipt_no` (`receipt_no`);

--
-- Indexes for table `salon_services`
--
ALTER TABLE `salon_services`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `uq_service_name` (`service_name`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_full_name` (`full_name`);

--
-- Indexes for table `staff_duties`
--
ALTER TABLE `staff_duties`
  ADD PRIMARY KEY (`duty_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `idx_duty_date` (`duty_date`);

--
-- Indexes for table `staff_notifications`
--
ALTER TABLE `staff_notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `idx_notif_read` (`is_read`);

--
-- Indexes for table `staff_reports`
--
ALTER TABLE `staff_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `idx_staff_id` (`staff_id`);

--
-- Indexes for table `staff_report_drafts`
--
ALTER TABLE `staff_report_drafts`
  ADD PRIMARY KEY (`draft_id`),
  ADD KEY `idx_draft_staff` (`staff_id`);

--
-- Indexes for table `staff_report_submissions`
--
ALTER TABLE `staff_report_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `idx_sub_staff` (`staff_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `username_2` (`username`),
  ADD UNIQUE KEY `username_3` (`username`);

--
-- Indexes for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD KEY `account_id` (`account_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `customer_accounts`
--
ALTER TABLE `customer_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `salon_services`
--
ALTER TABLE `salon_services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `staff_duties`
--
ALTER TABLE `staff_duties`
  MODIFY `duty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff_notifications`
--
ALTER TABLE `staff_notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `staff_reports`
--
ALTER TABLE `staff_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `staff_report_drafts`
--
ALTER TABLE `staff_report_drafts`
  MODIFY `draft_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `staff_report_submissions`
--
ALTER TABLE `staff_report_submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_accounts`
--
ALTER TABLE `user_accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_duties`
--
ALTER TABLE `staff_duties`
  ADD CONSTRAINT `staff_duties_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD CONSTRAINT `user_accounts_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `user_accounts` (`account_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
