-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 31, 2025 at 09:29 PM
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
-- Database: `paylink_bd`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) UNSIGNED NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_movements`
--

CREATE TABLE `bank_movements` (
  `id` int(11) UNSIGNED NOT NULL,
  `account_id` int(11) NOT NULL,
  `client_id` int(11) UNSIGNED DEFAULT NULL,
  `movement_type` enum('deposit','withdrawal','transfer_out','transfer_in') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `old_client_balance` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `new_client_balance` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `beneficiary_details` text DEFAULT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_movements`
--

INSERT INTO `bank_movements` (`id`, `account_id`, `client_id`, `movement_type`, `amount`, `currency`, `transaction_date`, `description`, `reference_number`, `old_client_balance`, `new_client_balance`, `beneficiary_details`, `created_by_user_id`, `created_at`) VALUES
(7, 17, NULL, 'deposit', 100.00, '', '2025-12-20', '', '', 0.0000, 0.0000, '', 1, '0000-00-00 00:00:00'),
(8, 17, NULL, 'withdrawal', 240.00, '', '2025-12-20', '', '', 0.0000, 0.0000, '', 1, '0000-00-00 00:00:00'),
(9, 17, NULL, 'withdrawal', 820.00, '', '2025-12-20', '', '', 0.0000, 0.0000, '', 1, '0000-00-00 00:00:00'),
(10, 17, NULL, 'withdrawal', 60.00, '', '2025-12-20', '', '', 0.0000, 0.0000, '', 1, '0000-00-00 00:00:00'),
(11, 18, NULL, 'withdrawal', 60.00, '', '2025-12-22', '', '', 0.0000, 0.0000, '', 1, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `account_id` int(11) UNSIGNED NOT NULL,
  `transaction_type` enum('DEPOSIT','WITHDRAWAL','INTERNAL_TRANSFER_IN','INTERNAL_TRANSFER_OUT','EXTERNAL_TRANSFER_OUT','EXTERNAL_TRANSFER_IN') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `related_account_id` int(11) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `location`, `is_active`, `created_at`) VALUES
(1, 'بونواس', 'بومواس', 1, '2025-12-17 05:10:34'),
(12, 'فرع السياحيه', 'سياحيه', 1, '2025-12-20 18:51:47'),
(13, 'فرع قرجي', 'قرجي شارع الغربي', 1, '2025-12-20 18:51:55'),
(14, 'فرع جنزور', 'جنزور', 1, '2025-12-22 22:17:16');

-- --------------------------------------------------------

--
-- Table structure for table `branch_serials`
--

CREATE TABLE `branch_serials` (
  `branch_id` int(11) NOT NULL,
  `last_invoice_serial` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branch_serials`
--

INSERT INTO `branch_serials` (`branch_id`, `last_invoice_serial`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `branch_id` int(11) NOT NULL DEFAULT 0,
  `phone` varchar(50) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `passport_image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1:Active, 0:Inactive',
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `daily_withdrawal_limit` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `full_name`, `branch_id`, `phone`, `id_number`, `passport_image_path`, `is_active`, `phone_number`, `address`, `created_at`, `bank_name`, `account_number`, `email`, `password`, `daily_withdrawal_limit`) VALUES
(2, 'عبد المنعم', 0, NULL, '354151', '', 1, '0931300371', '', '2025-12-10 21:12:13', '', '', NULL, NULL, 0.00),
(3, 'عبد المؤمن', 0, NULL, '6516846', '', 1, '0910288830', '', '2025-12-10 21:37:17', 'يمسنر', '615684', NULL, NULL, 0.00),
(5, 'عبد الرحيم سامي زغدون', 0, NULL, '120060201518', '', 1, '0915851762', '', '2025-12-12 12:09:20', 'نوران', '186153', 'zagdon01@gmail.com', NULL, 500.00),
(7, 'عبد المنعم', 0, NULL, '156151', '', 1, '0910288830', '', '2025-12-17 11:50:45', '', '', 'zagdon01@gmail.com', NULL, 0.00),
(10, 'علي', 0, NULL, '12005641', '', 1, '0910288850', '', '2025-12-20 16:55:45', NULL, NULL, 'zagdon21@gmail.com', '123456', 0.00),
(12, 'عبد الرحيم', 0, NULL, '35413545', '', 1, '0910288830', '', '2025-12-22 19:01:37', NULL, NULL, 'zagdon01@gmail.com', NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `client_balances`
--

CREATE TABLE `client_balances` (
  `id` int(11) NOT NULL,
  `client_id` int(11) UNSIGNED NOT NULL,
  `branch_id` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `currency_code` varchar(3) NOT NULL COMMENT 'USD, EUR, LYD',
  `current_balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_balances`
--

INSERT INTO `client_balances` (`id`, `client_id`, `branch_id`, `currency_code`, `current_balance`, `updated_at`) VALUES
(1, 2, 1, 'LYD', 1000.0000, '2025-12-10 23:27:56'),
(2, 3, 1, 'LYD', 900.0000, '2025-12-11 00:02:26'),
(4, 5, 1, 'LYD', 1000.0000, '2025-12-28 19:04:22'),
(20, 10, 1, 'LYD', 1000.0000, '2025-12-20 18:56:07');

-- --------------------------------------------------------

--
-- Table structure for table `client_bank_accounts`
--

CREATE TABLE `client_bank_accounts` (
  `id` int(11) NOT NULL,
  `client_id` int(11) UNSIGNED NOT NULL,
  `bank_name` varchar(100) NOT NULL COMMENT 'اسم المصرف',
  `account_number` varchar(50) NOT NULL COMMENT 'رقم الحساب البنكي',
  `iban` varchar(50) DEFAULT NULL COMMENT 'رقم الآيبان (اختياري)',
  `currency_id` int(11) NOT NULL,
  `balance` decimal(15,4) DEFAULT 0.0000 COMMENT 'الرصيد الحالي المسجل يدوياً',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='حسابات العملاء المصرفية';

-- --------------------------------------------------------

--
-- Table structure for table `client_transactions`
--

CREATE TABLE `client_transactions` (
  `id` int(11) NOT NULL,
  `client_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'المستخدم الذي أجرى العملية',
  `transaction_type` enum('DEPOSIT','WITHDRAW','SETTLEMENT') NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `amount` decimal(18,4) NOT NULL,
  `balance_before` decimal(18,4) NOT NULL,
  `balance_after` decimal(18,4) NOT NULL,
  `notes` text DEFAULT NULL,
  `invoice_path` varchar(255) DEFAULT NULL COMMENT 'مسار الفاتورة المصدرة',
  `created_at` datetime DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:Not Deleted, 1:Soft Deleted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_transactions`
--

INSERT INTO `client_transactions` (`id`, `client_id`, `user_id`, `transaction_type`, `currency_code`, `amount`, `balance_before`, `balance_after`, `notes`, `invoice_path`, `created_at`, `is_deleted`) VALUES
(1, 2, 1, 'DEPOSIT', 'LYD', 1000.0000, 0.0000, 1000.0000, '', NULL, '2025-12-10 23:27:56', 0),
(2, 3, 1, 'DEPOSIT', 'LYD', 1000.0000, 0.0000, 1000.0000, '', NULL, '2025-12-10 23:39:33', 0),
(3, 3, 1, 'WITHDRAW', 'LYD', 100.0000, 1000.0000, 900.0000, '', NULL, '2025-12-11 00:02:26', 0),
(4, 5, 1, 'DEPOSIT', 'LYD', 1000.0000, 0.0000, 1000.0000, 'ضفتلك 1000 جني في حسابك', NULL, '2025-12-12 14:15:40', 0),
(5, 5, 1, 'WITHDRAW', 'LYD', 100.0000, 1000.0000, 900.0000, '', NULL, '2025-12-12 14:34:02', 0),
(6, 5, 1, 'WITHDRAW', 'LYD', 100.0000, 900.0000, 800.0000, '', NULL, '2025-12-12 14:39:28', 0),
(7, 5, 1, 'WITHDRAW', 'LYD', 100.0000, 800.0000, 700.0000, '', NULL, '2025-12-12 14:39:37', 0),
(8, 5, 1, 'DEPOSIT', 'LYD', 300.0000, 700.0000, 1000.0000, '', NULL, '2025-12-12 14:44:45', 0),
(9, 5, 1, 'WITHDRAW', 'LYD', 100.0000, 1000.0000, 900.0000, '', NULL, '2025-12-12 14:45:32', 0),
(10, 5, 1, 'DEPOSIT', 'LYD', 100.0000, 900.0000, 1000.0000, '', NULL, '2025-12-12 14:47:24', 0),
(20, 10, 1, 'DEPOSIT', 'LYD', 1000.0000, 0.0000, 1000.0000, 'يوم الاحد', NULL, '2025-12-20 18:56:07', 0),
(21, 5, 1, 'WITHDRAW', 'LYD', 2000.0000, 1000.0000, -1000.0000, '', NULL, '2025-12-28 19:04:13', 0),
(22, 5, 1, 'DEPOSIT', 'LYD', 2000.0000, -1000.0000, 1000.0000, '', NULL, '2025-12-28 19:04:22', 0);

-- --------------------------------------------------------

--
-- Table structure for table `company_bank_accounts`
--

CREATE TABLE `company_bank_accounts` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL COMMENT 'اسم المصرف',
  `account_number` varchar(50) NOT NULL COMMENT 'رقم الحساب البنكي للشركة',
  `currency_id` int(11) NOT NULL COMMENT 'ربط بجدول currencies لتحديد العملة',
  `branch_id` int(11) DEFAULT 0 COMMENT 'الفرع المسؤول عن هذا الحساب (0 للفرع الرئيسي/الادارة)',
  `current_balance` decimal(15,4) DEFAULT 0.0000 COMMENT 'الرصيد الحالي في هذا الحساب',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='حسابات الشركة المصرفية عبر البنوك';

--
-- Dumping data for table `company_bank_accounts`
--

INSERT INTO `company_bank_accounts` (`id`, `bank_name`, `account_number`, `currency_id`, `branch_id`, `current_balance`, `is_active`, `last_updated`) VALUES
(17, 'مصرف الامان', '065165165', 17, NULL, 180.0000, 1, '2025-12-22 19:46:52'),
(18, 'مصرف نوران', '158605', 18, NULL, 0.0000, 1, '2025-12-22 20:19:36');

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` int(11) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `currency_name` varchar(100) NOT NULL,
  `currency_name_ar` varchar(100) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `is_base_currency` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_base` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `currency_code`, `currency_name`, `currency_name_ar`, `symbol`, `is_base_currency`, `is_active`, `created_at`, `is_base`) VALUES
(17, 'USD', '', 'دولار امريكي', NULL, 0, 1, '2025-12-17 22:07:39', 0),
(18, 'LYD', '', 'دينار ليبي', NULL, 1, 1, '2025-12-17 22:08:02', 1),
(19, 'EUR', '', 'يورو', NULL, 0, 1, '2025-12-17 22:08:36', 0),
(20, 'LYD', '', 'دينار ليبي', NULL, 1, 1, '2025-12-18 01:30:39', 1),
(21, 'USD', '', 'دولار امريكي', NULL, 0, 1, '2025-12-18 01:30:47', 0),
(22, 'SAR', '', 'جني استرليني', NULL, 0, 1, '2025-12-19 12:11:11', 0),
(24, 'FUB', '', 'باوند', NULL, 0, 1, '2025-12-20 16:58:27', 0);

-- --------------------------------------------------------

--
-- Table structure for table `currencies_balances`
--

CREATE TABLE `currencies_balances` (
  `id` int(11) UNSIGNED NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `balance` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `initial_capital` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currency_rates_history`
--

CREATE TABLE `currency_rates_history` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `from_currency_id` int(11) NOT NULL,
  `to_currency_id` int(11) NOT NULL,
  `old_rate` decimal(15,6) NOT NULL,
  `new_rate` decimal(15,6) NOT NULL,
  `user_id` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_closures`
--

CREATE TABLE `daily_closures` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `closure_date` date NOT NULL,
  `closure_time` datetime DEFAULT current_timestamp(),
  `starting_balance` decimal(10,2) DEFAULT 0.00,
  `ending_balance` decimal(10,2) DEFAULT 0.00,
  `status` enum('CLOSED','ERROR') DEFAULT 'CLOSED'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_reports`
--

CREATE TABLE `daily_reports` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `branch_name` varchar(255) NOT NULL,
  `report_date` date NOT NULL,
  `closure_user_id` int(11) NOT NULL,
  `summary_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`summary_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `end_of_day_reports`
--

CREATE TABLE `end_of_day_reports` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','sent','reviewed') NOT NULL DEFAULT 'sent',
  `summary_text` text DEFAULT NULL,
  `attached_file_path` varchar(255) DEFAULT NULL,
  `sent_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `end_of_day_reports`
--

INSERT INTO `end_of_day_reports` (`id`, `branch_id`, `report_date`, `user_id`, `status`, `summary_text`, `attached_file_path`, `sent_at`) VALUES
(1, 1, '0000-00-00', 1, 'sent', '', NULL, '2025-11-29 00:12:56');

-- --------------------------------------------------------

--
-- Table structure for table `exchange_rates`
--

CREATE TABLE `exchange_rates` (
  `id` int(11) NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `currency_name_ar` varchar(50) NOT NULL,
  `buy_rate` decimal(10,5) NOT NULL,
  `sell_rate` decimal(10,5) NOT NULL,
  `commission_percentage` decimal(5,2) DEFAULT 0.00,
  `is_display_active` tinyint(1) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exchange_rates`
--

INSERT INTO `exchange_rates` (`id`, `currency_code`, `currency_name_ar`, `buy_rate`, `sell_rate`, `commission_percentage`, `is_display_active`, `last_updated`) VALUES
(1, 'RUB', 'جنيه استرليني', 8.00000, 7.00000, 0.00, 1, '2025-12-16 22:12:48'),
(2, 'USD', 'دولار أمريكي', 6.00000, 5.00000, 0.00, 1, '2025-12-16 22:12:48'),
(3, 'LYD', 'دينار ليبي', 1.00000, 1.00000, 0.00, 1, '2025-12-16 22:12:48'),
(4, 'EUR', 'يورو', 1.00000, 1.00000, 0.00, 1, '2025-12-16 22:12:48'),
(20, 'RUB', 'جنيه استرليني', 1.00000, 1.00000, 0.00, 0, '2025-12-17 11:50:24'),
(21, 'USD', 'دولار أمريكي', 1.00000, 1.00000, 0.00, 0, '2025-12-17 11:50:24'),
(22, 'LYD', 'دينار ليبي', 1.00000, 1.00000, 0.00, 0, '2025-12-17 11:50:24'),
(23, 'EUR', 'يورو', 1.00000, 1.00000, 0.00, 0, '2025-12-17 11:50:24'),
(36, 'LYD', 'دينار ليبي', 6.00000, 7.00000, 0.00, 1, '2025-12-20 17:06:05'),
(37, 'EUR', 'يورو', 8.00000, 9.00000, 0.00, 1, '2025-12-20 17:06:05'),
(38, 'SAR', 'جني استرليني', 5.00000, 7.00000, 0.00, 1, '2025-12-20 17:06:05'),
(39, 'USD', 'دولار امريكي', 6.00000, 6.00000, 0.00, 1, '2025-12-20 17:06:05'),
(40, 'FUB', 'باوند', 1.00000, 1.00000, 0.00, 0, '2025-12-20 17:06:05');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`email`, `token`, `created_at`, `expires_at`) VALUES
('zagdonx@gmail.com', '5444', '2025-11-15 05:02:28', '2025-11-15 04:12:28');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_key` varchar(50) NOT NULL,
  `permission_name_ar` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `permission_key`, `permission_name_ar`) VALUES
(1, 'CAN_TRANSACT', 'إجراء عملية صرف'),
(2, 'CAN_MANAGE_BRANCHES', 'إدارة الفروع'),
(3, 'CAN_MANAGE_CURRENCIES', 'إدارة العملات والأرصدة'),
(4, 'CAN_MANAGE_CLIENTS', 'إدارة العملاء'),
(5, 'CAN_MANAGE_USERS', 'إدارة المستخدمين'),
(6, 'CAN_UPDATE_RATES', 'تحديث أسعار الصرف'),
(7, 'CAN_MANAGE_TREASURY', 'إدارة أرصدة الخزينة'),
(8, 'CAN_VIEW_WHATIF', 'تحليل \"ماذا لو\"'),
(9, 'CAN_VIEW_REPORTS', 'التقارير الشاملة'),
(10, 'CAN_CLOSE_DAILY', 'إغلاق اليومية والترحيل');

-- --------------------------------------------------------

--
-- Table structure for table `reconciliations`
--

CREATE TABLE `reconciliations` (
  `id` int(11) NOT NULL,
  `reconciliation_date` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('PENDING','COMPLETED','FAILED') DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reconciliation_details`
--

CREATE TABLE `reconciliation_details` (
  `id` int(11) NOT NULL,
  `reconciliation_id` int(11) NOT NULL,
  `currency_id` int(11) NOT NULL,
  `system_balance` decimal(15,4) NOT NULL,
  `physical_balance` decimal(15,4) NOT NULL,
  `difference` decimal(15,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reset_tokens`
--

CREATE TABLE `reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL COMMENT 'اسم الدور (مدير عام، مدير فرع، موظف)',
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`) VALUES
(1, 'مدير عام', NULL),
(2, 'مدير فرع', NULL),
(3, 'موظف', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL COMMENT 'رقم تعريف الدور (يربط بجدول roles)',
  `permission_key` varchar(50) NOT NULL COMMENT 'مفتاح الصلاحية النصي (مثل CAN_PROCESS_TRANSACTION)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_key`) VALUES
(2, 'CAN_ANALYZE_WHATIF'),
(2, 'CAN_CLOSE_DAILY'),
(2, 'CAN_MANAGE_BALANCES'),
(2, 'CAN_MANAGE_BRANCHES'),
(2, 'CAN_MANAGE_CLIENTS'),
(2, 'CAN_MANAGE_TREASURY_BALANCES'),
(2, 'CAN_MANAGE_USERS'),
(2, 'CAN_PROCESS_TRANSACTION'),
(2, 'CAN_UPDATE_RATES'),
(2, 'CAN_VIEW_REPORTS'),
(3, 'CAN_ANALYZE_WHATIF'),
(3, 'CAN_CLOSE_DAILY'),
(3, 'CAN_MANAGE_ACCOUNTS_BALANCES'),
(3, 'CAN_MANAGE_BRANCHES'),
(3, 'CAN_MANAGE_CLIENTS'),
(3, 'CAN_MANAGE_TREASURY_BALANCES'),
(3, 'CAN_MANAGE_USERS'),
(3, 'CAN_PROCESS_TRANSACTION'),
(3, 'CAN_UPDATE_RATES'),
(3, 'CAN_VIEW_REPORTS');

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL,
  `license_status` varchar(50) NOT NULL DEFAULT 'TEMP_LOCK' COMMENT 'حالة ترخيص النظام'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`id`, `license_status`) VALUES
(1, 'FULLY_PAID_2025'),
(2, 'TEMP_LOCK');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `transaction_type` enum('شراء','بيع') NOT NULL,
  `from_currency_id` int(11) NOT NULL,
  `to_currency_id` int(11) NOT NULL,
  `amount_foreign` decimal(15,4) NOT NULL,
  `amount_LYD` decimal(15,4) NOT NULL,
  `amount_in` decimal(15,4) DEFAULT NULL,
  `rate_used` decimal(15,6) NOT NULL,
  `commission_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `discount_amount` decimal(15,4) DEFAULT 0.0000,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `serial_number` varchar(20) NOT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `branch_id` int(11) DEFAULT NULL,
  `payment_method` enum('نقدي','حوالة','شيك','رصيد إلكتروني') NOT NULL DEFAULT 'نقدي',
  `is_finalized` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0: عملية مؤقتة لم ترحل بعد، 1: تم ترحيلها وإضافتها للرصيد النهائي',
  `amount_from` decimal(10,4) DEFAULT NULL,
  `amount_to` decimal(10,4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `transaction_type`, `from_currency_id`, `to_currency_id`, `amount_foreign`, `amount_LYD`, `amount_in`, `rate_used`, `commission_percentage`, `commission_amount`, `net_amount`, `document_path`, `discount_amount`, `client_id`, `client_name`, `user_id`, `serial_number`, `transaction_date`, `branch_id`, `payment_method`, `is_finalized`, `amount_from`, `amount_to`) VALUES
(1, 'شراء', 2, 1, 10.0000, 50.0000, NULL, 5.000000, 0.00, 0.00, 50.00, NULL, 0.0000, NULL, 'عبد الرحيم', 14, '2025121704091941158', '2025-12-17 05:09:19', 0, '', 0, NULL, NULL),
(17, 'شراء', 0, 0, 10.0000, 40.0000, NULL, 4.000000, 0.00, 0.00, 40.00, NULL, 0.0000, NULL, 'علي', 1, '2025122018011885525', '2025-12-20 19:01:18', 0, '', 0, NULL, NULL),
(18, 'بيع', 0, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, 'علي', 1, '2025122018033730460', '2025-12-20 19:03:37', 0, '', 0, NULL, NULL),
(19, 'بيع', 0, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, 'علي', 1, '2025122018040939488', '2025-12-20 19:04:09', 0, '', 0, NULL, NULL),
(20, 'بيع', 0, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, 'علي', 1, '2025122018054745051', '2025-12-20 19:05:47', 0, '', 0, NULL, NULL),
(21, 'شراء', 0, 0, 100.0000, 600.0000, NULL, 6.000000, 0.00, 0.00, 600.00, NULL, 0.0000, NULL, 'علي', 1, '2025122018095954605', '2025-12-20 19:09:59', 12, '', 0, NULL, NULL),
(22, 'شراء', 0, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, 'عبد الرحيم', 1, '2025122220430804037', '2025-12-22 21:43:08', 13, '', 0, NULL, NULL),
(23, 'بيع', 0, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, 'عبد المؤمن', 1, '2025122220460301444', '2025-12-22 21:46:03', 0, '', 0, NULL, NULL),
(24, 'بيع', 0, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, 'عبد المؤمن', 1, '2025122220465235510', '2025-12-22 21:46:52', 13, 'شيك', 0, NULL, NULL),
(25, 'شراء', 17, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, '0', 1, '2025122221175558902', '2025-12-22 22:17:55', 14, '', 0, NULL, NULL),
(26, 'بيع', 17, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, '0', 1, '2025122221181693295', '2025-12-22 22:18:16', 14, '', 0, NULL, NULL),
(27, 'شراء', 17, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, '0', 1, '2025122221190971557', '2025-12-22 22:19:09', 14, '', 0, NULL, NULL),
(28, 'بيع', 17, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, '0', 1, '2025122221193603257', '2025-12-22 22:19:36', 14, '', 0, NULL, NULL),
(29, 'شراء', 21, 0, 10.0000, 60.0000, NULL, 6.000000, 0.00, 0.00, 60.00, NULL, 0.0000, NULL, '0', 1, '2025122816383016218', '2025-12-28 17:38:30', 14, '', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `treasury_balances`
--

CREATE TABLE `treasury_balances` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `currency_id` int(11) NOT NULL,
  `current_balance` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `last_updated` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treasury_balances`
--

INSERT INTO `treasury_balances` (`id`, `branch_id`, `currency_id`, `current_balance`, `last_updated`) VALUES
(1, 0, 2, 110.0000, '0000-00-00 00:00:00'),
(2, 0, 1, -50.0000, '0000-00-00 00:00:00'),
(3, 1, 1, 10000.0000, '2025-12-17 05:11:00'),
(4, 1, 8, 1000.0000, '2025-12-17 05:11:08'),
(5, 1, 2, 1000.0000, '2025-12-17 05:11:16'),
(6, 1, 4, 1000.0000, '2025-12-17 05:11:23'),
(24, 12, 18, 1000.0000, '2025-12-20 19:00:51'),
(25, 12, 17, 200.0000, '2025-12-20 19:09:26'),
(26, 14, 18, 40.0000, '2025-12-28 17:38:30'),
(27, 14, 17, 0.0000, '2025-12-22 22:19:36'),
(33, 14, 21, 10.0000, '2025-12-28 17:38:30');

-- --------------------------------------------------------

--
-- Table structure for table `treasury_log`
--

CREATE TABLE `treasury_log` (
  `id` int(11) NOT NULL,
  `currency_id` int(11) NOT NULL,
  `movement_type` varchar(50) NOT NULL,
  `amount` decimal(15,4) NOT NULL,
  `current_balance` decimal(15,4) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `reconciliation_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treasury_transactions`
--

CREATE TABLE `treasury_transactions` (
  `id` int(11) UNSIGNED NOT NULL,
  `branch_id` int(11) NOT NULL DEFAULT 0,
  `currency_id` int(11) NOT NULL DEFAULT 0,
  `transaction_type` enum('BUY','SELL','CAPITAL_IN','EXPENSE') NOT NULL,
  `amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `currency_in_code` varchar(3) NOT NULL,
  `amount_in` decimal(15,4) NOT NULL,
  `currency_out_code` varchar(3) NOT NULL,
  `amount_out` decimal(15,4) NOT NULL,
  `expense_description` varchar(255) DEFAULT NULL,
  `exchange_rate` decimal(10,6) DEFAULT NULL,
  `profit_loss` decimal(15,4) DEFAULT 0.0000,
  `notes` varchar(255) DEFAULT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_finalized` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0: عملية مؤقتة لم ترحل بعد، 1: تم ترحيلها وإضافتها للرصيد النهائي'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treasury_transactions`
--

INSERT INTO `treasury_transactions` (`id`, `branch_id`, `currency_id`, `transaction_type`, `amount`, `currency_in_code`, `amount_in`, `currency_out_code`, `amount_out`, `expense_description`, `exchange_rate`, `profit_loss`, `notes`, `user_id`, `transaction_date`, `is_finalized`) VALUES
(1, 1, 0, 'CAPITAL_IN', 0.0000, '0', 10000.0000, '0', 0.0000, NULL, NULL, 0.0000, NULL, 14, '2025-12-17 03:11:00', 0),
(2, 1, 0, 'CAPITAL_IN', 0.0000, '0', 1000.0000, '0', 0.0000, NULL, NULL, 0.0000, NULL, 14, '2025-12-17 03:11:08', 0),
(3, 1, 0, 'CAPITAL_IN', 0.0000, '0', 1000.0000, '0', 0.0000, NULL, NULL, 0.0000, NULL, 14, '2025-12-17 03:11:16', 0),
(4, 1, 0, 'CAPITAL_IN', 0.0000, '0', 1000.0000, '0', 0.0000, NULL, NULL, 0.0000, NULL, 14, '2025-12-17 03:11:23', 0),
(16, 12, 0, 'CAPITAL_IN', 0.0000, '0', 1000.0000, '0', 0.0000, NULL, NULL, 0.0000, NULL, 1, '2025-12-20 17:00:51', 0),
(17, 12, 0, 'CAPITAL_IN', 0.0000, '0', 200.0000, '0', 0.0000, NULL, NULL, 0.0000, NULL, 1, '2025-12-20 17:09:26', 0),
(18, 14, 0, 'CAPITAL_IN', 0.0000, '0', 100.0000, '0', 0.0000, NULL, NULL, 0.0000, NULL, 1, '2025-12-22 20:17:34', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `permissions_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_role` enum('مدير عام','مدير فرع','موظف') NOT NULL DEFAULT 'موظف',
  `branch_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `phone_number`, `is_active`, `permissions_json`, `created_at`, `user_role`, `branch_id`, `role_id`) VALUES
(1, 'admin', 'zagdon01@gmail.com', '$2y$10$msHBI9QIqmVozlAU7ZX9GuHD8HykZZfC30DdimABO1d3SiDjBzHum', 'مدير العام', '0931300371', 1, '[]', '2025-10-09 14:11:16', 'مدير عام', 0, 1),
(14, 'مصدق', 'zagdonx@gmail.com', '$2y$10$nbX2fmeQVJKO.MG0Izk/i.GHEvvnDqAreiC1q6rrmsgCZXsteZQgq', 'مدير عام', '0910288830', 1, NULL, '2025-12-16 22:55:19', 'مدير عام', NULL, NULL),
(19, 'منعم', 'zagdonx@gmail.com', '$2y$10$fmQKsO.yNiRLj/0hdb8/eesRvZ6JMeg4bsld/nBdA9/hh1ptZjs8y', 'عبد المنعم', '0910288830', 1, '[\"exchange_process\"]', '2025-12-17 11:49:59', 'موظف', 0, NULL),
(22, 'عبدو', 'zagdon01@gmail.com', '$2y$10$FMsJsS8hu0x.e7irthGI3eTu7Y/S.rz6pZEQcdvWRseD3Ky7uKWQ.', 'عبد الرحيم', '0910288830', 1, '[\"exchange_process\",\"invoices_log\",\"currency_balance_management\",\"company_treasury\",\"clients_management\",\"users_management\",\"exchange_rate_settings\",\"treasury_balance_management\"]', '2025-12-20 16:53:03', 'مدير فرع', 12, NULL),
(23, 'منعم', 'zagdonx@gmail.com', '$2y$10$yNgMVK1jo4rphjHZA7EOgePP8iiODwdO2.79bULIK1Fs6EuYgUIra', 'عبد المنعم', '0911300371', 1, '[\"exchange_process\",\"invoices_log\",\"currency_balance_management\",\"company_treasury\",\"clients_management\",\"users_management\",\"exchange_rate_settings\",\"treasury_balance_management\"]', '2025-12-20 16:53:39', 'مدير فرع', 13, NULL),
(24, 'علي', 'zagdo8x@gmail.com', '$2y$10$hu60KzGu3GIdejosYHxIOuizOP3sNFBMRew.qTaUbVCgvAgEb3S9K', 'علي', '091521355', 1, '[\"exchange_process\",\"invoices_log\",\"clients_management\",\"exchange_rate_settings\"]', '2025-12-20 17:20:31', 'موظف', 12, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`user_id`, `permission_id`) VALUES
(6, 1),
(6, 3),
(6, 4),
(6, 8),
(6, 9),
(6, 10);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_number` (`account_number`);

--
-- Indexes for table `bank_movements`
--
ALTER TABLE `bank_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `fk_client_link` (`client_id`);

--
-- Indexes for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branch_serials`
--
ALTER TABLE `branch_serials`
  ADD PRIMARY KEY (`branch_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`);

--
-- Indexes for table `client_balances`
--
ALTER TABLE `client_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_client_currency` (`client_id`,`currency_code`);

--
-- Indexes for table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_client_bank_client` (`client_id`),
  ADD KEY `fk_client_bank_currency` (`currency_id`);

--
-- Indexes for table `client_transactions`
--
ALTER TABLE `client_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `company_bank_accounts`
--
ALTER TABLE `company_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_company_bank_currency` (`currency_id`),
  ADD KEY `fk_company_bank_branch` (`branch_id`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `currencies_balances`
--
ALTER TABLE `currencies_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `currency_code` (`currency_code`);

--
-- Indexes for table `currency_rates_history`
--
ALTER TABLE `currency_rates_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `from_currency_id` (`from_currency_id`),
  ADD KEY `to_currency_id` (`to_currency_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `daily_closures`
--
ALTER TABLE `daily_closures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_closure` (`branch_id`,`closure_date`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `daily_reports`
--
ALTER TABLE `daily_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_report_per_day` (`branch_id`,`report_date`);

--
-- Indexes for table `end_of_day_reports`
--
ALTER TABLE `end_of_day_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_report_per_day` (`branch_id`,`report_date`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `exchange_rates`
--
ALTER TABLE `exchange_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key` (`permission_key`);

--
-- Indexes for table `reconciliations`
--
ALTER TABLE `reconciliations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reconciliation_date` (`reconciliation_date`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reconciliation_details`
--
ALTER TABLE `reconciliation_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reconciliation_id` (`reconciliation_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `reset_tokens`
--
ALTER TABLE `reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_key`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_currency_id` (`from_currency_id`),
  ADD KEY `to_currency_id` (`to_currency_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `treasury_balances`
--
ALTER TABLE `treasury_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_branch_currency` (`branch_id`,`currency_id`);

--
-- Indexes for table `treasury_log`
--
ALTER TABLE `treasury_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `reconciliation_id` (`reconciliation_id`);

--
-- Indexes for table `treasury_transactions`
--
ALTER TABLE `treasury_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_movements`
--
ALTER TABLE `bank_movements`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `client_balances`
--
ALTER TABLE `client_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `client_bank_accounts`
--
ALTER TABLE `client_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `client_transactions`
--
ALTER TABLE `client_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `company_bank_accounts`
--
ALTER TABLE `company_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `currencies_balances`
--
ALTER TABLE `currencies_balances`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currency_rates_history`
--
ALTER TABLE `currency_rates_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_closures`
--
ALTER TABLE `daily_closures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_reports`
--
ALTER TABLE `daily_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `end_of_day_reports`
--
ALTER TABLE `end_of_day_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `exchange_rates`
--
ALTER TABLE `exchange_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `reconciliations`
--
ALTER TABLE `reconciliations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reconciliation_details`
--
ALTER TABLE `reconciliation_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reset_tokens`
--
ALTER TABLE `reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_config`
--
ALTER TABLE `system_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `treasury_balances`
--
ALTER TABLE `treasury_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `treasury_log`
--
ALTER TABLE `treasury_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treasury_transactions`
--
ALTER TABLE `treasury_transactions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bank_movements`
--
ALTER TABLE `bank_movements`
  ADD CONSTRAINT `bank_movements_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `company_bank_accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_client_link` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `bank_transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `bank_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `client_balances`
--
ALTER TABLE `client_balances`
  ADD CONSTRAINT `client_balances_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
