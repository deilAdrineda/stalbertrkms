-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 16, 2025 at 04:10 PM
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
-- Database: `samadc`
--

-- --------------------------------------------------------

--
-- Table structure for table `billing_tbl`
--

CREATE TABLE `billing_tbl` (
  `billing_id` int(11) NOT NULL,
  `avail_id` int(11) NOT NULL,
  `or_number` int(50) NOT NULL,
  `amount_total` decimal(10,2) NOT NULL,
  `discount_name` varchar(50) DEFAULT NULL,
  `discount_value` decimal(10,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `custom_discount_value` decimal(10,2) DEFAULT NULL,
  `billing_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_tbl`
--

INSERT INTO `billing_tbl` (`billing_id`, `avail_id`, `or_number`, `amount_total`, `discount_name`, `discount_value`, `discount_amount`, `custom_discount_value`, `billing_date`) VALUES
(150, 294, 1, 200.00, 'Senior Citizen', 20.00, 160.00, 0.00, '2025-11-16 22:33:59'),
(151, 295, 1, 775.00, 'Senior Citizen', 20.00, 755.00, 0.00, '2025-11-16 22:33:59'),
(152, 296, 2, 200.00, 'Custom', 50.00, 100.00, 50.00, '2025-11-16 22:37:18'),
(153, 297, 3, 200.00, 'CLINIC DISCOUNT', 90.00, 20.00, 0.00, '2025-11-16 23:06:30'),
(154, 298, 3, 975.00, 'CLINIC DISCOUNT', 90.00, 705.00, 0.00, '2025-11-16 23:06:30'),
(155, 299, 5, 200.00, 'CLINIC DISCOUNT', 90.00, 20.00, 0.00, '2025-11-17 01:02:55'),
(156, 300, 8, 675.00, '', 0.00, 675.00, 0.00, '2025-11-17 19:36:58'),
(157, 301, 9, 675.00, '', 0.00, 675.00, 0.00, '2025-11-17 19:41:20'),
(158, 302, 9, 100.00, '', 0.00, 100.00, 0.00, '2025-11-17 19:41:20'),
(159, 303, 1, 200.00, 'Custom', 25.00, 150.00, 25.00, '2025-12-12 11:48:17'),
(160, 304, 2, 1600.00, 'TESTING DISCOUNT', 50.00, 800.00, 0.00, '2025-12-13 03:16:11');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_packages`
--

CREATE TABLE `clinic_packages` (
  `package_id` int(11) NOT NULL,
  `package_name` varchar(100) NOT NULL,
  `discount_value` decimal(5,2) NOT NULL,
  `reg_price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) NOT NULL,
  `date_created` datetime NOT NULL,
  `date_archived` datetime DEFAULT NULL,
  `is_archived` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_packages`
--

INSERT INTO `clinic_packages` (`package_id`, `package_name`, `discount_value`, `reg_price`, `discount_price`, `date_created`, `date_archived`, `is_archived`) VALUES
(13, 'HYPERTENSION PACKAGE', 25.00, 900.00, 675.00, '2025-11-16 22:16:18', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `clinic_packages_procedures`
--

CREATE TABLE `clinic_packages_procedures` (
  `pack_proc_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `procedure_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_packages_procedures`
--

INSERT INTO `clinic_packages_procedures` (`pack_proc_id`, `package_id`, `procedure_id`) VALUES
(138, 13, 227),
(139, 13, 232),
(140, 13, 230),
(141, 13, 234);

-- --------------------------------------------------------

--
-- Table structure for table `clinic_service_tbl`
--

CREATE TABLE `clinic_service_tbl` (
  `service_id` int(11) NOT NULL,
  `service_code` varchar(50) NOT NULL,
  `service_name` varchar(50) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `service_added` datetime NOT NULL,
  `is_archived` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_service_tbl`
--

INSERT INTO `clinic_service_tbl` (`service_id`, `service_code`, `service_name`, `role_id`, `service_added`, `is_archived`) VALUES
(27, 'L', 'LABORATORY', 2, '2025-11-16 19:39:24', 0),
(28, 'X', 'X-RAY', 7, '2025-11-16 19:39:39', 0),
(29, 'DT', 'DRUG TEST', 2, '2025-11-16 19:39:49', 0),
(35, 'TS', 'TESTING SERVICE', 7, '2025-11-16 22:13:59', 1),
(36, 'TEST', 'DISCOUNTS', 5, '2025-11-16 22:53:27', 1),
(37, 'L2', 'Laboratory 2', 2, '2025-12-13 02:20:54', 0);

-- --------------------------------------------------------

--
-- Table structure for table `discount_tbl`
--

CREATE TABLE `discount_tbl` (
  `discount_id` int(11) NOT NULL,
  `discount_name` varchar(100) NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `discount_tbl`
--

INSERT INTO `discount_tbl` (`discount_id`, `discount_name`, `discount_value`, `description`, `is_archived`, `date_created`) VALUES
(36, 'Senior Citizen', 20.00, NULL, 0, '2025-11-16 19:47:35'),
(37, 'Person With Disability', 20.00, NULL, 0, '2025-11-16 19:47:43'),
(38, 'TESTING DISCOUNT', 60.00, NULL, 1, '2025-11-16 20:29:48'),
(39, 'TESTING DISCOUNT', 25.00, NULL, 1, '2025-11-16 21:04:49'),
(40, 'TESTING DISCOUNT', 50.00, NULL, 0, '2025-11-16 22:17:07'),
(41, 'CLINIC DISCOUNT', 90.00, NULL, 1, '2025-11-16 22:57:03'),
(42, 'CLINIC DISCOUNT', 1.00, NULL, 0, '2025-11-17 23:34:20');

-- --------------------------------------------------------

--
-- Table structure for table `patient_info_tbl`
--

CREATE TABLE `patient_info_tbl` (
  `patient_id` int(11) NOT NULL,
  `patient_code` varchar(20) DEFAULT NULL,
  `patient_fname` varchar(100) NOT NULL,
  `patient_mname` varchar(100) NOT NULL,
  `patient_lname` varchar(100) NOT NULL,
  `patient_sex` varchar(50) NOT NULL,
  `patient_home_add` varchar(100) NOT NULL,
  `patient_dob` date NOT NULL,
  `patient_phone` varchar(13) NOT NULL,
  `patient_added` datetime NOT NULL,
  `is_archived` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_info_tbl`
--

INSERT INTO `patient_info_tbl` (`patient_id`, `patient_code`, `patient_fname`, `patient_mname`, `patient_lname`, `patient_sex`, `patient_home_add`, `patient_dob`, `patient_phone`, `patient_added`, `is_archived`) VALUES
(128, '25-1', 'Michael', 'Boloy', 'Jordan', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '1998-02-02', '09123456789', '2025-11-16 22:33:58', 0),
(129, '25-2', 'John David', 'Reyes', 'Hipolito', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '2009-02-02', '09123456789', '2025-11-16 23:06:30', 0),
(130, '25-3', 'John Deil', 'Garcia', 'Adrineda', 'Male', 'Caridad Village, Cabanatuan City', '1998-05-05', '09231437552', '2025-12-12 11:48:17', 0);

-- --------------------------------------------------------

--
-- Table structure for table `patient_service_avail`
--

CREATE TABLE `patient_service_avail` (
  `avail_id` int(11) NOT NULL,
  `case_no` varchar(6) DEFAULT NULL,
  `patient_id` int(100) NOT NULL,
  `service_id` int(100) NOT NULL,
  `requested_by` varchar(50) DEFAULT NULL,
  `brief_history` varchar(50) DEFAULT NULL,
  `package_name` varchar(100) DEFAULT NULL,
  `date_availed` datetime NOT NULL,
  `billing_status` varchar(10) NOT NULL,
  `status` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_service_avail`
--

INSERT INTO `patient_service_avail` (`avail_id`, `case_no`, `patient_id`, `service_id`, `requested_by`, `brief_history`, `package_name`, `date_availed`, `billing_status`, `status`) VALUES
(294, 'DT25-1', 128, 29, 'Dr. Phil', NULL, NULL, '2025-11-16 22:33:59', 'Paid', 'Completed'),
(295, 'L25-1', 128, 27, 'Dr. McGregor', NULL, 'HYPERTENSION PACKAGE', '2025-11-16 22:33:59', 'Paid', 'Completed'),
(296, 'X25-1', 128, 28, NULL, NULL, NULL, '2025-11-16 22:37:18', 'Paid', 'Pending'),
(297, 'DT25-2', 129, 29, 'Dr. Null', NULL, NULL, '2025-11-16 23:06:30', 'Paid', 'Canceled'),
(298, 'L25-2', 129, 27, 'Dr. Mario', 'HELLO', 'HYPERTENSION PACKAGE', '2025-11-16 23:06:30', 'Paid', 'Completed'),
(299, 'DT25-1', 129, 29, 'Dr. No', NULL, NULL, '2025-11-17 01:02:55', 'Paid', 'Completed'),
(300, 'L25-1', 129, 27, NULL, NULL, 'HYPERTENSION PACKAGE', '2025-11-17 19:36:57', 'Paid', 'Pending'),
(301, 'L25-2', 128, 27, NULL, NULL, 'HYPERTENSION PACKAGE', '2025-11-17 19:41:20', 'Paid', 'Pending'),
(302, 'X25-1', 128, 28, NULL, NULL, NULL, '2025-11-17 19:41:20', 'Paid', 'Pending'),
(303, 'X25-1', 130, 28, NULL, NULL, NULL, '2025-12-12 11:48:17', 'Paid', 'Pending'),
(304, 'L25-1', 130, 27, NULL, NULL, NULL, '2025-12-13 03:16:10', 'Paid', 'Completed');

-- --------------------------------------------------------

--
-- Table structure for table `patient_service_proc`
--

CREATE TABLE `patient_service_proc` (
  `avail_proc_id` int(11) NOT NULL,
  `avail_id` int(11) NOT NULL,
  `procedure_id` int(11) DEFAULT NULL,
  `custom_proc` varchar(50) DEFAULT NULL,
  `custom_proc_price` decimal(10,2) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `custom_group_proc` varchar(50) DEFAULT NULL,
  `custom_group_price` decimal(10,2) DEFAULT NULL,
  `price_at_avail` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_service_proc`
--

INSERT INTO `patient_service_proc` (`avail_proc_id`, `avail_id`, `procedure_id`, `custom_proc`, `custom_proc_price`, `group_id`, `custom_group_proc`, `custom_group_price`, `price_at_avail`) VALUES
(684, 294, NULL, 'DRUG TEST', 200.00, NULL, NULL, NULL, NULL),
(685, 295, 227, NULL, NULL, 52, NULL, NULL, 200.00),
(686, 295, 232, NULL, NULL, 53, NULL, NULL, 300.00),
(687, 295, 230, NULL, NULL, 52, NULL, NULL, 200.00),
(688, 295, 234, NULL, NULL, 53, NULL, NULL, 200.00),
(689, 295, NULL, 'LABORATORY PROCEDURE', 100.00, NULL, NULL, NULL, NULL),
(690, 296, NULL, 'SKULL XRAY', 200.00, NULL, NULL, NULL, NULL),
(691, 297, NULL, 'DRUG TEST', 200.00, NULL, NULL, NULL, NULL),
(692, 298, 227, NULL, NULL, 52, NULL, NULL, 200.00),
(693, 298, 232, NULL, NULL, 53, NULL, NULL, 300.00),
(694, 298, 230, NULL, NULL, 52, NULL, NULL, 200.00),
(695, 298, 234, NULL, NULL, 53, NULL, NULL, 200.00),
(696, 298, 235, NULL, NULL, 54, NULL, NULL, 200.00),
(697, 298, NULL, NULL, NULL, 52, 'Blood Chem PROCEDURE', 100.00, NULL),
(698, 299, NULL, 'DRUG TEST', 200.00, NULL, NULL, NULL, NULL),
(699, 300, 227, NULL, NULL, 52, NULL, NULL, 200.00),
(700, 300, 232, NULL, NULL, 53, NULL, NULL, 300.00),
(701, 300, 230, NULL, NULL, 52, NULL, NULL, 200.00),
(702, 300, 234, NULL, NULL, 53, NULL, NULL, 200.00),
(703, 301, 227, NULL, NULL, 52, NULL, NULL, 200.00),
(704, 301, 232, NULL, NULL, 53, NULL, NULL, 300.00),
(705, 301, 230, NULL, NULL, 52, NULL, NULL, 200.00),
(706, 301, 234, NULL, NULL, 53, NULL, NULL, 200.00),
(707, 302, NULL, 'SKULL XRAY', 100.00, NULL, NULL, NULL, NULL),
(708, 303, NULL, 'X-RAY', 200.00, NULL, NULL, NULL, NULL),
(709, 304, 230, NULL, NULL, 52, NULL, NULL, 200.00),
(710, 304, 229, NULL, NULL, 52, NULL, NULL, 500.00),
(711, 304, 231, NULL, NULL, 52, NULL, NULL, 300.00),
(712, 304, 237, NULL, NULL, 54, NULL, NULL, 400.00),
(713, 304, 235, NULL, NULL, 54, NULL, NULL, 200.00);

-- --------------------------------------------------------

--
-- Table structure for table `procedure_group_tbl`
--

CREATE TABLE `procedure_group_tbl` (
  `group_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `group_name` varchar(50) NOT NULL,
  `group_added` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `procedure_group_tbl`
--

INSERT INTO `procedure_group_tbl` (`group_id`, `service_id`, `group_name`, `group_added`) VALUES
(52, 27, 'Blood Chemistry', '0000-00-00 00:00:00'),
(53, 27, 'HEMATOLOGY', '0000-00-00 00:00:00'),
(54, 27, 'MICROSCOPY', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `procedure_price_tbl`
--

CREATE TABLE `procedure_price_tbl` (
  `procedure_price_id` int(11) NOT NULL,
  `procedure_price` varchar(50) NOT NULL,
  `procedure_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `procedure_price_tbl`
--

INSERT INTO `procedure_price_tbl` (`procedure_price_id`, `procedure_price`, `procedure_id`) VALUES
(225, '200', 225),
(226, '100', 226),
(227, '200', 227),
(228, '400', 228),
(229, '500', 229),
(230, '200', 230),
(231, '300', 231),
(232, '300', 232),
(233, '300', 233),
(234, '200', 234),
(235, '200', 235),
(236, '200', 236),
(237, '400', 237),
(238, '231', 238),
(239, '214', 239),
(240, '400', 240),
(241, '231', 241),
(242, '131', 242),
(243, '123', 243),
(244, '1231', 244);

-- --------------------------------------------------------

--
-- Table structure for table `procedure_tbl`
--

CREATE TABLE `procedure_tbl` (
  `procedure_id` int(11) NOT NULL,
  `procedure_name` varchar(100) NOT NULL,
  `service_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `procedure_added` datetime NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `procedure_tbl`
--

INSERT INTO `procedure_tbl` (`procedure_id`, `procedure_name`, `service_id`, `group_id`, `procedure_added`, `is_archived`) VALUES
(225, 'FBS', 27, 52, '2025-11-16 22:02:09', 0),
(226, 'CHOLESTEROL', 27, 52, '2025-11-16 22:02:09', 0),
(227, 'BUN', 27, 52, '2025-11-16 22:02:09', 0),
(228, 'URIC ACID', 27, 52, '2025-11-16 22:02:09', 0),
(229, 'HbA1C', 27, 52, '2025-11-16 22:02:09', 0),
(230, 'CREATININE', 27, 52, '2025-11-16 22:02:09', 0),
(231, 'TRIGLYCERIDE', 27, 52, '2025-11-16 22:02:09', 0),
(232, 'CBC', 27, 53, '2025-11-16 22:02:09', 0),
(233, 'CBC w/APC', 27, 53, '2025-11-16 22:02:09', 0),
(234, 'PLATELET COUNT', 27, 53, '2025-11-16 22:02:09', 0),
(235, 'URINALYSIS', 27, 54, '2025-11-16 22:02:09', 0),
(236, 'FECALYSIS', 27, 54, '2025-11-16 22:02:09', 0),
(237, 'PREGNANCY TEST', 27, 54, '2025-11-16 22:02:09', 0),
(238, 'LABORATORY SINGLE', 27, NULL, '2025-11-16 22:05:33', 1),
(239, 'ANOTHER 1', 27, 55, '2025-11-16 22:05:33', 1),
(240, 'ANOTHER 2', 27, 55, '2025-11-16 22:05:33', 1),
(241, 'Laboratory Procedure RANDOM', 27, NULL, '2025-11-16 22:15:10', 1),
(242, 'te4sting', 27, 56, '2025-11-16 22:15:29', 1),
(243, 'LABORATORY PROCEDURE', 27, NULL, '2025-11-16 22:54:51', 1),
(244, 'GROUP PROCEDURE 1', 27, 57, '2025-11-16 22:54:51', 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(255) NOT NULL,
  `role_desc` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_desc`) VALUES
(1, 'Administrator', 'Administrator'),
(2, 'Laboratory Personnel', 'Laboratory Personnel'),
(3, 'Ultrasound Personnel', 'Ultrasound Personnel'),
(4, 'Receptionist', 'Receptionist'),
(5, '2D Echo Personnel', '2D Echo Personnel'),
(6, 'ECG Personnel', 'ECG Personnel'),
(7, 'X-RAY Personnel', 'X-RAY Personnel');

-- --------------------------------------------------------

--
-- Table structure for table `service_task`
--

CREATE TABLE `service_task` (
  `task_id` int(11) NOT NULL,
  `avail_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `user_account_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `assigned_on` datetime DEFAULT current_timestamp(),
  `actioned_on` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_task`
--

INSERT INTO `service_task` (`task_id`, `avail_id`, `service_id`, `user_account_id`, `status`, `assigned_on`, `actioned_on`) VALUES
(411, 294, 29, 20, 'Completed', '2025-11-16 22:45:19', '2025-11-16 22:45:19'),
(412, 295, 27, 20, 'Canceled', '2025-11-16 22:45:28', '2025-11-16 22:45:28'),
(413, 295, 27, 20, 'Pending', '2025-11-16 22:45:41', '2025-11-16 22:45:41'),
(414, 294, 29, 20, 'Pending', '2025-11-16 22:45:44', '2025-11-16 22:45:44'),
(415, 294, 29, 20, 'Completed', '2025-11-16 22:46:38', '2025-11-16 22:46:38'),
(416, 295, 27, 20, 'Canceled', '2025-11-16 22:46:56', '2025-11-16 22:46:56'),
(417, 295, 27, 20, 'Pending', '2025-11-16 22:47:11', '2025-11-16 22:47:11'),
(418, 295, 27, 20, 'Completed', '2025-11-16 22:47:22', '2025-11-16 22:47:22'),
(419, 297, 29, 20, 'Pending', '2025-11-16 23:06:30', NULL),
(420, 298, 27, 20, 'Pending', '2025-11-16 23:06:30', NULL),
(421, 297, 29, 20, 'Completed', '2025-11-16 23:07:54', '2025-11-16 23:07:54'),
(422, 297, 29, 20, 'Pending', '2025-11-16 23:08:09', '2025-11-16 23:08:09'),
(423, 297, 29, 20, 'Canceled', '2025-11-16 23:08:14', '2025-11-16 23:08:14'),
(424, 298, 27, 20, 'Completed', '2025-11-16 23:08:29', '2025-11-16 23:08:29'),
(425, 299, 29, 20, 'Pending', '2025-11-17 01:02:55', NULL),
(426, 299, 29, 20, 'Canceled', '2025-11-17 01:03:21', '2025-11-17 01:03:21'),
(427, 299, 29, 20, 'Pending', '2025-11-17 03:30:28', '2025-11-17 03:30:28'),
(428, 299, 29, 20, 'Canceled', '2025-11-17 03:30:31', '2025-11-17 03:30:31'),
(429, 299, 29, 20, 'Pending', '2025-11-17 03:30:34', '2025-11-17 03:30:34'),
(430, 299, 29, 20, 'Completed', '2025-11-17 03:30:37', '2025-11-17 03:30:37'),
(431, 299, 29, 20, 'Pending', '2025-11-17 04:39:25', '2025-11-17 04:39:25'),
(432, 299, 29, 20, 'Completed', '2025-11-17 04:39:28', '2025-11-17 04:39:28'),
(433, 300, 27, 20, 'Pending', '2025-11-17 19:36:57', NULL),
(434, 301, 27, 20, 'Pending', '2025-11-17 19:41:20', NULL),
(435, 302, 28, 27, 'Pending', '2025-11-17 19:41:20', NULL),
(436, 303, 28, 27, 'Pending', '2025-12-12 11:48:17', NULL),
(437, 304, 27, 20, 'Pending', '2025-12-13 03:16:10', NULL),
(438, 304, 27, 20, 'Completed', '2025-12-13 03:30:02', '2025-12-13 03:30:02');

-- --------------------------------------------------------

--
-- Table structure for table `user_account`
--

CREATE TABLE `user_account` (
  `user_account_id` int(11) NOT NULL,
  `username` varchar(45) NOT NULL,
  `role_id` int(11) NOT NULL,
  `user_password_id` int(11) NOT NULL,
  `user_info_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_account`
--

INSERT INTO `user_account` (`user_account_id`, `username`, `role_id`, `user_password_id`, `user_info_id`) VALUES
(14, 'johndeiladrineda', 1, 23, 23),
(19, 'johnmatthew', 4, 28, 28),
(20, 'michaeljordan', 2, 29, 29),
(25, 'jonathanreyes', 5, 34, 34),
(26, 'michaeljackson', 6, 35, 35),
(27, 'jacksonreyes', 7, 36, 36),
(28, 'janedwardreyes', 3, 37, 37),
(29, 'admin123', 1, 38, 38);

-- --------------------------------------------------------

--
-- Table structure for table `user_attempts`
--

CREATE TABLE `user_attempts` (
  `attempt_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `attempt_date_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_attempts`
--

INSERT INTO `user_attempts` (`attempt_id`, `username`, `attempt_date_time`) VALUES
(1, 'johndeiladrineda', '2025-08-13 20:53:41'),
(2, 'princessellieza', '2025-08-14 10:33:18'),
(3, 'johndeiladrineda', '2025-08-17 10:40:19'),
(4, 'hjjhjhjhjh', '2025-08-17 10:40:26'),
(5, 'johndeiladrineda', '2025-09-24 13:40:54'),
(6, 'as', '2025-09-24 13:41:02'),
(7, '123123', '2025-09-24 13:41:10'),
(8, 'camilleanne', '2025-09-24 13:41:17'),
(9, 'michaeldesanta', '2025-09-29 14:09:24'),
(10, 'princessellieza', '2025-09-29 14:15:47'),
(11, 'princessellieza', '2025-09-29 14:15:54'),
(12, 'johndavidauxillos', '2025-09-29 14:15:58'),
(13, 'princessellieza', '2025-09-29 14:18:16'),
(14, 'jokoyjockie', '2025-09-29 19:42:54'),
(15, 'jowardmanavia', '2025-10-02 11:33:15'),
(16, 'jowardmanavia', '2025-10-02 11:34:09'),
(17, 'jowardmanavia', '2025-10-02 11:34:12'),
(18, 'camilleanne', '2025-10-06 01:35:48'),
(19, 'janedward', '2025-10-11 14:12:41'),
(20, 'janedward', '2025-10-11 14:37:13'),
(21, 'sads', '2025-10-12 19:33:19'),
(22, 'camilleanne', '2025-10-18 18:42:51'),
(23, 'dsa', '2025-10-23 21:32:52'),
(24, 'elliezamarie', '2025-11-02 19:12:11'),
(25, 'deilAdrineda', '2025-11-11 02:19:50'),
(26, 'jokoyjockikie', '2025-11-14 13:34:38'),
(27, 'jokoyjockikie', '2025-11-14 13:37:10'),
(28, 'jokoyjockikie', '2025-11-14 13:37:28'),
(29, 'johndeiladrineda', '2025-11-14 14:36:17'),
(30, 'johndeiladrineda', '2025-11-14 14:46:16'),
(31, 'johndeiladrineda123', '2025-11-14 14:46:47'),
(32, 'johndeiladrineda123', '2025-11-14 14:46:59'),
(33, 'johndeiladrineda123', '2025-11-14 14:48:03'),
(34, 'dummydummy', '2025-11-14 14:54:43'),
(35, 'dummydummy123', '2025-11-14 14:54:52'),
(36, 'dummydummy123', '2025-11-14 14:55:31'),
(37, '2dechotest', '2025-11-14 14:56:41'),
(38, '2dechotest', '2025-11-14 14:56:47'),
(39, 'dummydummy123', '2025-11-14 15:02:00'),
(40, 'dummydummy123', '2025-11-14 15:18:44'),
(41, 'dummyfakee', '2025-11-14 15:18:53'),
(42, 'dummydummy123', '2025-11-14 15:26:48'),
(43, '2dechotest', '2025-11-14 15:27:49'),
(44, 'dummyyfakee222', '2025-11-14 15:32:02'),
(45, 'dummyyfakee', '2025-11-14 15:32:06'),
(46, 'dummyyfakee', '2025-11-14 15:32:26'),
(47, 'dummyyfakee222', '2025-11-14 15:32:43'),
(48, 'dummyyfakee222', '2025-11-14 15:32:46'),
(49, 'adsad', '2025-11-14 21:58:58'),
(50, 'janedward', '2025-11-16 01:06:58'),
(51, 'janedward', '2025-11-16 02:38:00'),
(52, '2dechotest', '2025-11-16 14:18:48'),
(53, 'elliezamarie', '2025-11-16 16:15:56'),
(54, 'camilleanne', '2025-11-16 16:49:39'),
(55, 'janedwardreyes', '2025-11-16 22:51:42'),
(56, 'camilleanne', '2025-12-12 11:46:47'),
(57, 'michaelreyes', '2025-12-13 03:23:34');

-- --------------------------------------------------------

--
-- Table structure for table `user_info`
--

CREATE TABLE `user_info` (
  `user_info_id` int(11) NOT NULL,
  `user_fname` varchar(100) NOT NULL,
  `user_mname` varchar(100) NOT NULL,
  `user_lname` varchar(100) NOT NULL,
  `user_sex` varchar(50) NOT NULL,
  `user_home_add` varchar(100) NOT NULL,
  `user_dob` date NOT NULL,
  `user_phone` varchar(13) NOT NULL,
  `user_created` datetime NOT NULL,
  `is_archived` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_info`
--

INSERT INTO `user_info` (`user_info_id`, `user_fname`, `user_mname`, `user_lname`, `user_sex`, `user_home_add`, `user_dob`, `user_phone`, `user_created`, `is_archived`) VALUES
(23, 'John Deil', 'Garcia', 'Adrineda', 'Male', '123 Sumacab Este, Cabanatuan City', '2003-11-11', '09123456789', '2025-11-14 07:51:09', 0),
(28, 'John Matthew', 'Rey', 'Reyes', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '1998-06-06', '09123456789', '2025-11-16 19:53:36', 0),
(29, 'Michael', 'Jordan', 'Reyes', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '1998-06-06', '09123456789', '2025-11-16 20:25:21', 0),
(30, 'Jonathan', 'Joseph', 'Reyes', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '0009-05-05', '09123456789', '2025-11-16 20:32:21', 0),
(31, 'Jopopoy', 'dsada', 'saddasdsa', 'Female', '123 Brgy Gen Tinio, Cabanatuan City', '2000-05-05', '09231437552', '2025-11-16 20:45:28', 0),
(32, 'Jopopoy', 'asdasd', 'asdsada', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '1998-05-05', '09231437552', '2025-11-16 20:46:01', 0),
(33, 'sadawda', 'sadwadas', 'wadadwa', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '2000-06-06', '09123456789', '2025-11-16 20:50:36', 0),
(34, 'Jonathan', 'Joseph', 'Reyes', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '2000-06-06', '09112345431', '2025-11-16 20:57:46', 0),
(35, 'Michael Luis', 'Jackson', 'Jacksonville', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '2000-06-06', '09123456789', '2025-11-16 21:07:31', 0),
(36, 'Jackson', 'Ju', 'Reyes', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '2000-04-04', '09123456781', '2025-11-16 22:13:03', 0),
(37, 'Jan Edward', 'Marciano', 'Reyes', 'Male', '123 Brgy Gen Tinio, Cabanatuan City', '2000-04-04', '09123456789', '2025-11-16 22:51:21', 0),
(38, 'admin', 'admin', 'admin', 'Male', 'Cabanatuan City', '2003-01-01', '09123456789', '2025-12-13 01:09:26', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `user_log_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `user_activity` varchar(100) NOT NULL,
  `user_log_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_logs`
--

INSERT INTO `user_logs` (`user_log_id`, `username`, `user_activity`, `user_log_date`) VALUES
(1930, 'johndeiladrineda', 'User Login', '2025-11-16 19:27:05'),
(1931, 'johndeiladrineda', 'User logout', '2025-11-16 19:32:53'),
(1932, 'johndeiladrineda', 'User Login', '2025-11-16 19:34:15'),
(1933, 'johndeiladrineda', 'User logout', '2025-11-16 19:34:42'),
(1934, 'johndeiladrineda', 'User Login', '2025-11-16 19:35:18'),
(1935, 'johndeiladrineda', 'User logout', '2025-11-16 19:36:43'),
(1936, 'johndeiladrineda', 'User Login', '2025-11-16 19:39:04'),
(1937, 'johndeiladrineda', 'User logout', '2025-11-16 19:49:31'),
(1938, 'johndeiladrineda', 'User Login', '2025-11-16 19:50:08'),
(1939, 'johndeiladrineda', 'User logout', '2025-11-16 19:51:52'),
(1940, 'johndeiladrineda', 'User Login', '2025-11-16 19:52:28'),
(1941, 'johndeiladrineda', 'User logout', '2025-11-16 19:54:10'),
(1942, 'johnmatthew', 'User Login', '2025-11-16 19:54:15'),
(1943, 'johnmatthew', 'User logout', '2025-11-16 19:54:25'),
(1944, 'johndeiladrineda', 'User Login', '2025-11-16 19:54:28'),
(1945, 'johndeiladrineda', 'User logout', '2025-11-16 20:23:14'),
(1946, 'johndeiladrineda', 'User Login', '2025-11-16 20:23:53'),
(1947, 'johndeiladrineda', 'User logout', '2025-11-16 20:30:43'),
(1948, 'johndeiladrineda', 'User Login', '2025-11-16 20:31:07'),
(1949, 'johndeiladrineda', 'User logout', '2025-11-16 20:52:19'),
(1950, 'johndeiladrineda', 'User Login', '2025-11-16 20:55:42'),
(1951, 'johndeiladrineda', 'User logout', '2025-11-16 21:05:13'),
(1952, 'johndeiladrineda', 'User Login', '2025-11-16 21:05:43'),
(1953, 'johndeiladrineda', 'User logout', '2025-11-16 21:36:02'),
(1954, 'johndeiladrineda', 'User Login', '2025-11-16 21:36:06'),
(1955, 'johndeiladrineda', 'User logout', '2025-11-16 22:06:15'),
(1956, 'johndeiladrineda', 'User Login', '2025-11-16 22:06:20'),
(1957, 'johndeiladrineda', 'User logout', '2025-11-16 22:09:44'),
(1958, 'johndeiladrineda', 'User Login', '2025-11-16 22:10:55'),
(1959, 'johndeiladrineda', 'User logout', '2025-11-16 22:22:45'),
(1960, 'johndeiladrineda', 'User Login', '2025-11-16 22:22:49'),
(1961, 'johndeiladrineda', 'User logout', '2025-11-16 22:22:55'),
(1962, 'johnmatthew', 'User Login', '2025-11-16 22:23:02'),
(1963, 'johnmatthew', 'User logout', '2025-11-16 22:23:58'),
(1964, 'johnmatthew', 'User Login', '2025-11-16 22:24:03'),
(1965, 'johnmatthew', 'User logout', '2025-11-16 22:39:27'),
(1966, 'johndeiladrineda', 'User Login', '2025-11-16 22:39:31'),
(1967, 'johndeiladrineda', 'User logout', '2025-11-16 22:39:43'),
(1968, 'michaeljordan', 'User Login', '2025-11-16 22:39:48'),
(1969, 'michaeljordan', 'User logout', '2025-11-16 22:48:47'),
(1970, 'johndeiladrineda', 'User Login', '2025-11-16 22:49:26'),
(1971, 'johndeiladrineda', 'User logout', '2025-11-16 22:51:37'),
(1972, 'johndeiladrineda', 'User Login', '2025-11-16 22:51:46'),
(1973, 'johndeiladrineda', 'User logout', '2025-11-16 22:51:54'),
(1974, 'janedwardreyes', 'User Login', '2025-11-16 22:52:00'),
(1975, 'janedwardreyes', 'User logout', '2025-11-16 22:52:07'),
(1976, 'johndeiladrineda', 'User Login', '2025-11-16 22:52:10'),
(1977, 'johndeiladrineda', 'User logout', '2025-11-16 23:00:26'),
(1978, 'johndeiladrineda', 'User Login', '2025-11-16 23:00:31'),
(1979, 'johndeiladrineda', 'User logout', '2025-11-16 23:00:37'),
(1980, 'johnmatthew', 'User Login', '2025-11-16 23:00:42'),
(1981, 'johnmatthew', 'User logout', '2025-11-16 23:07:00'),
(1982, 'johndeiladrineda', 'User Login', '2025-11-16 23:07:02'),
(1983, 'johndeiladrineda', 'User logout', '2025-11-16 23:07:07'),
(1984, 'michaeljordan', 'User Login', '2025-11-16 23:07:18'),
(1985, 'michaeljordan', 'User logout', '2025-11-16 23:09:43'),
(1986, 'michaeljordan', 'User Login', '2025-11-16 23:09:54'),
(1987, 'michaeljordan', 'User logout', '2025-11-16 23:10:45'),
(1988, 'johndeiladrineda', 'User Login', '2025-11-17 00:49:49'),
(1989, 'johndeiladrineda', 'User logout', '2025-11-17 00:56:40'),
(1990, 'johnmatthew', 'User Login', '2025-11-17 00:56:46'),
(1991, 'johnmatthew', 'User logout', '2025-11-17 01:01:36'),
(1992, 'michaeljordan', 'User Login', '2025-11-17 01:01:39'),
(1993, 'michaeljordan', 'User logout', '2025-11-17 01:02:19'),
(1994, 'johnmatthew', 'User Login', '2025-11-17 01:02:23'),
(1995, 'johnmatthew', 'User logout', '2025-11-17 01:02:59'),
(1996, 'michaeljordan', 'User Login', '2025-11-17 01:03:03'),
(1997, 'michaeljordan', 'User logout', '2025-11-17 03:13:51'),
(1998, 'michaeljordan', 'User Login', '2025-11-17 03:13:57'),
(1999, 'michaeljordan', 'User logout', '2025-11-17 03:30:59'),
(2000, 'johndeiladrineda', 'User Login', '2025-11-17 03:31:04'),
(2001, 'johndeiladrineda', 'User logout', '2025-11-17 04:37:15'),
(2002, 'johndeiladrineda', 'User Login', '2025-11-17 04:37:19'),
(2003, 'johndeiladrineda', 'User logout', '2025-11-17 04:39:04'),
(2004, 'michaeljordan', 'User Login', '2025-11-17 04:39:07'),
(2005, 'michaeljordan', 'User logout', '2025-11-17 12:30:37'),
(2006, 'johndeiladrineda', 'User Login', '2025-11-17 12:30:45'),
(2007, 'johndeiladrineda', 'User logout', '2025-11-17 12:30:54'),
(2008, 'michaeljordan', 'User Login', '2025-11-17 12:30:57'),
(2009, 'michaeljordan', 'User logout', '2025-11-17 13:01:06'),
(2010, 'johndeiladrineda', 'User Login', '2025-11-17 13:01:10'),
(2011, 'johndeiladrineda', 'User logout', '2025-11-17 13:02:41'),
(2012, 'michaeljordan', 'User Login', '2025-11-17 19:34:26'),
(2013, 'michaeljordan', 'User logout', '2025-11-17 19:36:06'),
(2014, 'johnmatthew', 'User Login', '2025-11-17 19:36:11'),
(2015, 'johnmatthew', 'User logout', '2025-11-17 23:33:42'),
(2016, 'johndeiladrineda', 'User Login', '2025-11-17 23:33:48'),
(2017, 'johndeiladrineda', 'User logout', '2025-11-17 23:34:33'),
(2018, 'johndeiladrineda', 'User Login', '2025-12-12 11:27:21'),
(2019, 'johndeiladrineda', 'User logout', '2025-12-12 11:45:26'),
(2020, 'johndeiladrineda', 'User Login', '2025-12-12 11:45:31'),
(2021, 'johndeiladrineda', 'User logout', '2025-12-12 11:45:40'),
(2022, 'janedwardreyes', 'User Login', '2025-12-12 11:46:07'),
(2023, 'janedwardreyes', 'User logout', '2025-12-12 11:46:13'),
(2024, 'johndeiladrineda', 'User Login', '2025-12-12 11:46:16'),
(2025, 'johndeiladrineda', 'User logout', '2025-12-12 11:46:22'),
(2026, 'jacksonreyes', 'User Login', '2025-12-12 11:46:27'),
(2027, 'michaeljordan', 'User Login', '2025-12-12 11:46:53'),
(2028, 'michaeljordan', 'User logout', '2025-12-12 11:46:57'),
(2029, 'johndeiladrineda', 'User Login', '2025-12-12 11:47:06'),
(2030, 'johndeiladrineda', 'User logout', '2025-12-12 11:47:14'),
(2031, 'jacksonreyes', 'User logout', '2025-12-12 11:47:22'),
(2032, 'johnmatthew', 'User Login', '2025-12-12 11:47:26'),
(2033, 'johnmatthew', 'User logout', '2025-12-12 11:48:20'),
(2034, 'jacksonreyes', 'User Login', '2025-12-12 11:48:26'),
(2035, 'johndeiladrineda', 'User Login', '2025-12-13 01:06:14'),
(2036, 'johndeiladrineda', 'User logout', '2025-12-13 01:10:00'),
(2037, 'admin123', 'User Login', '2025-12-13 01:13:09'),
(2038, 'admin123', 'User logout', '2025-12-13 02:20:00'),
(2039, 'admin123', 'User Login', '2025-12-13 02:20:06'),
(2040, 'admin123', 'User logout', '2025-12-13 02:51:07'),
(2041, 'johnmatthew', 'User Login', '2025-12-13 02:52:15'),
(2042, 'johnmatthew', 'User logout', '2025-12-13 02:52:18'),
(2043, 'johnmatthew', 'User Login', '2025-12-13 02:52:22'),
(2044, 'johnmatthew', 'User logout', '2025-12-13 02:54:35'),
(2045, 'johnmatthew', 'User Login', '2025-12-13 02:54:45'),
(2046, 'johnmatthew', 'User logout', '2025-12-13 02:54:47'),
(2047, 'johnmatthew', 'User Login', '2025-12-13 02:55:08'),
(2048, 'johnmatthew', 'User logout', '2025-12-13 03:20:08'),
(2049, 'johnmatthew', 'User Login', '2025-12-13 03:23:03'),
(2050, 'johnmatthew', 'User logout', '2025-12-13 03:23:07'),
(2051, 'johndeiladrineda', 'User Login', '2025-12-13 03:23:18'),
(2052, 'johndeiladrineda', 'User logout', '2025-12-13 03:23:23'),
(2053, 'michaeljordan', 'User Login', '2025-12-13 03:23:50'),
(2054, 'michaeljordan', 'User logout', '2025-12-13 03:23:51'),
(2055, 'michaeljordan', 'User Login', '2025-12-13 03:24:10'),
(2056, 'michaeljordan', 'User logout', '2025-12-13 04:01:22');

-- --------------------------------------------------------

--
-- Table structure for table `user_passwords`
--

CREATE TABLE `user_passwords` (
  `user_password_id` int(11) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_token_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_passwords`
--

INSERT INTO `user_passwords` (`user_password_id`, `user_password`, `user_token_id`) VALUES
(23, '$2y$10$Y4mytIZwz50pLFniJujr4OC67LBVC/qWG9UW43Vd3TNAvlBA6biNa', NULL),
(28, '$2y$10$ti.gQyCjt/QF0jZqyLl1muoiatMKU23GAw2K.wiCFGoMJozVYZVBK', NULL),
(29, '$2y$10$mvgixQ5fCA44XAi3ulGGTu2YBRdne5Me7OeJ01BIrNLxu.CO1DEfq', NULL),
(34, '$2y$10$uBsX5Vl3rlp5omSnu1RaeuWlsKrcibHwthd48z/Malcd.biEA9rBu', NULL),
(35, '$2y$10$3SLlJjcBAXQcBfsVB7fqWu7cA6EDh.GfXsjHbwQ0WOtRauvfwZg3S', NULL),
(36, '$2y$10$sa7BWPR5DAwVIsa2VJB.LuDAM56Xx0OiZNahbNdvyHw1Qh1gLWAqq', 1198),
(37, '$2y$10$JAnzHIVCaOhvvSqXMOiauumDDURnBrTq3kEC/uDJhrW33NnbOMn3a', NULL),
(38, '$2y$10$ceaw5DNi57bz3qmAujVQ9uXNkDieLEhXNlzu82eZfV.ArOT5lxqcS', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `user_token_id` int(11) NOT NULL,
  `user_token` varchar(255) NOT NULL,
  `expiration` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_tokens`
--

INSERT INTO `user_tokens` (`user_token_id`, `user_token`, `expiration`) VALUES
(1198, '6361c3c2924968545cea540046a54b4e6c67530bd1f1e8b304eb6a0a66065b44', '2025-12-12 12:18:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `billing_tbl`
--
ALTER TABLE `billing_tbl`
  ADD PRIMARY KEY (`billing_id`),
  ADD KEY `avail_id` (`avail_id`),
  ADD KEY `payment_tbl_ibfk_2` (`discount_value`);

--
-- Indexes for table `clinic_packages`
--
ALTER TABLE `clinic_packages`
  ADD PRIMARY KEY (`package_id`);

--
-- Indexes for table `clinic_packages_procedures`
--
ALTER TABLE `clinic_packages_procedures`
  ADD PRIMARY KEY (`pack_proc_id`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `procedure_id` (`procedure_id`);

--
-- Indexes for table `clinic_service_tbl`
--
ALTER TABLE `clinic_service_tbl`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `discount_tbl`
--
ALTER TABLE `discount_tbl`
  ADD PRIMARY KEY (`discount_id`);

--
-- Indexes for table `patient_info_tbl`
--
ALTER TABLE `patient_info_tbl`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `patient_code` (`patient_code`);

--
-- Indexes for table `patient_service_avail`
--
ALTER TABLE `patient_service_avail`
  ADD PRIMARY KEY (`avail_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `patient_service_avail_ibfk_1` (`patient_id`);

--
-- Indexes for table `patient_service_proc`
--
ALTER TABLE `patient_service_proc`
  ADD PRIMARY KEY (`avail_proc_id`),
  ADD KEY `avail_id` (`avail_id`),
  ADD KEY `procedure_id` (`procedure_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `procedure_group_tbl`
--
ALTER TABLE `procedure_group_tbl`
  ADD PRIMARY KEY (`group_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `procedure_price_tbl`
--
ALTER TABLE `procedure_price_tbl`
  ADD PRIMARY KEY (`procedure_price_id`),
  ADD KEY `procedure_id` (`procedure_id`);

--
-- Indexes for table `procedure_tbl`
--
ALTER TABLE `procedure_tbl`
  ADD PRIMARY KEY (`procedure_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `service_task`
--
ALTER TABLE `service_task`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `avail_id` (`avail_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `user_account_id` (`user_account_id`);

--
-- Indexes for table `user_account`
--
ALTER TABLE `user_account`
  ADD PRIMARY KEY (`user_account_id`),
  ADD KEY `user_info_id` (`user_info_id`),
  ADD KEY `user_account_ibfk_2` (`user_password_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_attempts`
--
ALTER TABLE `user_attempts`
  ADD PRIMARY KEY (`attempt_id`);

--
-- Indexes for table `user_info`
--
ALTER TABLE `user_info`
  ADD PRIMARY KEY (`user_info_id`);

--
-- Indexes for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`user_log_id`);

--
-- Indexes for table `user_passwords`
--
ALTER TABLE `user_passwords`
  ADD PRIMARY KEY (`user_password_id`),
  ADD KEY `user_passwords_ibfk_1` (`user_token_id`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`user_token_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billing_tbl`
--
ALTER TABLE `billing_tbl`
  MODIFY `billing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `clinic_packages`
--
ALTER TABLE `clinic_packages`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `clinic_packages_procedures`
--
ALTER TABLE `clinic_packages_procedures`
  MODIFY `pack_proc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT for table `clinic_service_tbl`
--
ALTER TABLE `clinic_service_tbl`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `discount_tbl`
--
ALTER TABLE `discount_tbl`
  MODIFY `discount_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `patient_info_tbl`
--
ALTER TABLE `patient_info_tbl`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `patient_service_avail`
--
ALTER TABLE `patient_service_avail`
  MODIFY `avail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=305;

--
-- AUTO_INCREMENT for table `patient_service_proc`
--
ALTER TABLE `patient_service_proc`
  MODIFY `avail_proc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=714;

--
-- AUTO_INCREMENT for table `procedure_group_tbl`
--
ALTER TABLE `procedure_group_tbl`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `procedure_price_tbl`
--
ALTER TABLE `procedure_price_tbl`
  MODIFY `procedure_price_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=245;

--
-- AUTO_INCREMENT for table `procedure_tbl`
--
ALTER TABLE `procedure_tbl`
  MODIFY `procedure_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=245;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `service_task`
--
ALTER TABLE `service_task`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=439;

--
-- AUTO_INCREMENT for table `user_account`
--
ALTER TABLE `user_account`
  MODIFY `user_account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `user_attempts`
--
ALTER TABLE `user_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `user_info`
--
ALTER TABLE `user_info`
  MODIFY `user_info_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `user_log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2057;

--
-- AUTO_INCREMENT for table `user_passwords`
--
ALTER TABLE `user_passwords`
  MODIFY `user_password_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `user_token_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1210;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billing_tbl`
--
ALTER TABLE `billing_tbl`
  ADD CONSTRAINT `billing_tbl_ibfk_1` FOREIGN KEY (`avail_id`) REFERENCES `patient_service_avail` (`avail_id`) ON UPDATE CASCADE;

--
-- Constraints for table `clinic_packages_procedures`
--
ALTER TABLE `clinic_packages_procedures`
  ADD CONSTRAINT `clinic_packages_procedures_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `clinic_packages` (`package_id`),
  ADD CONSTRAINT `clinic_packages_procedures_ibfk_2` FOREIGN KEY (`procedure_id`) REFERENCES `procedure_tbl` (`procedure_id`);

--
-- Constraints for table `clinic_service_tbl`
--
ALTER TABLE `clinic_service_tbl`
  ADD CONSTRAINT `clinic_service_tbl_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE;

--
-- Constraints for table `patient_service_avail`
--
ALTER TABLE `patient_service_avail`
  ADD CONSTRAINT `patient_service_avail_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient_info_tbl` (`patient_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `patient_service_avail_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `clinic_service_tbl` (`service_id`) ON UPDATE CASCADE;

--
-- Constraints for table `patient_service_proc`
--
ALTER TABLE `patient_service_proc`
  ADD CONSTRAINT `patient_service_proc_ibfk_1` FOREIGN KEY (`avail_id`) REFERENCES `patient_service_avail` (`avail_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `patient_service_proc_ibfk_2` FOREIGN KEY (`procedure_id`) REFERENCES `procedure_tbl` (`procedure_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `patient_service_proc_ibfk_3` FOREIGN KEY (`group_id`) REFERENCES `procedure_group_tbl` (`group_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `procedure_group_tbl`
--
ALTER TABLE `procedure_group_tbl`
  ADD CONSTRAINT `procedure_group_tbl_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `clinic_service_tbl` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `procedure_price_tbl`
--
ALTER TABLE `procedure_price_tbl`
  ADD CONSTRAINT `procedure_price_tbl_ibfk_1` FOREIGN KEY (`procedure_id`) REFERENCES `procedure_tbl` (`procedure_id`);

--
-- Constraints for table `procedure_tbl`
--
ALTER TABLE `procedure_tbl`
  ADD CONSTRAINT `procedure_tbl_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `clinic_service_tbl` (`service_id`);

--
-- Constraints for table `service_task`
--
ALTER TABLE `service_task`
  ADD CONSTRAINT `service_task_ibfk_1` FOREIGN KEY (`avail_id`) REFERENCES `patient_service_avail` (`avail_id`),
  ADD CONSTRAINT `service_task_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `clinic_service_tbl` (`service_id`),
  ADD CONSTRAINT `service_task_ibfk_3` FOREIGN KEY (`user_account_id`) REFERENCES `user_account` (`user_account_id`);

--
-- Constraints for table `user_account`
--
ALTER TABLE `user_account`
  ADD CONSTRAINT `user_account_ibfk_1` FOREIGN KEY (`user_info_id`) REFERENCES `user_info` (`user_info_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_account_ibfk_2` FOREIGN KEY (`user_password_id`) REFERENCES `user_passwords` (`user_password_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_account_ibfk_3` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_passwords`
--
ALTER TABLE `user_passwords`
  ADD CONSTRAINT `user_passwords_ibfk_1` FOREIGN KEY (`user_token_id`) REFERENCES `user_tokens` (`user_token_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
