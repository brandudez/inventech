-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 26, 2026 at 02:42 AM
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
-- Database: `itms_inventech`
--

-- --------------------------------------------------------

--
-- Table structure for table `cameras`
--

CREATE TABLE `cameras` (
  `id` int(11) NOT NULL,
  `device_code` varchar(255) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_details` text DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` timestamp NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `desktops`
--

CREATE TABLE `desktops` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `device_name` varchar(150) NOT NULL,
  `division_id` int(11) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `is_os_licensed` tinyint(1) DEFAULT NULL,
  `os_license_key` varchar(255) DEFAULT NULL,
  `is_remote_acc` tinyint(1) DEFAULT NULL,
  `endpoint_security_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `no_of_installed_anti_virus` int(11) DEFAULT NULL,
  `date_installed` date DEFAULT NULL,
  `guid` varchar(100) DEFAULT NULL,
  `mac_address` varchar(100) DEFAULT NULL,
  `cpu_brand` varchar(100) DEFAULT NULL,
  `cpu_generation` int(11) DEFAULT NULL,
  `cpu_cores` int(11) DEFAULT NULL,
  `gb_ram` int(11) DEFAULT NULL,
  `monitor_brand` varchar(100) DEFAULT NULL,
  `monitor_size_inches` int(11) DEFAULT NULL,
  `no_of_user_accounts` int(11) DEFAULT NULL,
  `user_account_type` longtext DEFAULT NULL,
  `authorized_software` text DEFAULT NULL,
  `unauthorized_software` text DEFAULT NULL,
  `office_application` varchar(150) DEFAULT NULL,
  `is_office_licensed` tinyint(1) DEFAULT NULL,
  `office_license_key` varchar(255) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date DEFAULT current_timestamp(),
  `last_updated_at` date DEFAULT NULL,
  `par_serial_no` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `acquisition_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `desktops`
--

INSERT INTO `desktops` (`id`, `personnel_id`, `device_id`, `device_name`, `division_id`, `ip_address`, `os`, `is_os_licensed`, `os_license_key`, `is_remote_acc`, `endpoint_security_id`, `no_of_installed_anti_virus`, `date_installed`, `guid`, `mac_address`, `cpu_brand`, `cpu_generation`, `cpu_cores`, `gb_ram`, `monitor_brand`, `monitor_size_inches`, `no_of_user_accounts`, `user_account_type`, `authorized_software`, `unauthorized_software`, `office_application`, `is_office_licensed`, `office_license_key`, `previous_owners_id`, `created_date`, `last_updated_at`, `par_serial_no`, `is_active`, `acquisition_date`) VALUES
(1, 8, 0, 'ITMS-ITSD-0037', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"8\"]', 1, NULL, NULL, ' D4:61:37:01:52:39', 'Intel', 12, 6, 16, 'Acer KA242Y', 24, 4, '[{\"name\":\"Administrator\",\"type\":\"Admin\"},{\"name\":\"arren\",\"type\":\"Admin\"},{\"name\":\"SU\",\"type\":\"Admin\"},{\"name\":\"SAS ADMIN\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, '[]', '2026-06-24', NULL, NULL, 1, NULL),
(2, 9, 0, 'ITMS-ITSD-0030', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"3\",\"4\",\"8\",\"9\"]', 5, NULL, NULL, '6C:4B:90:22:89:50', 'Inter', 7, 4, 16, 'HP W2072a', 20, 4, '[{\"name\":\"macalapano\",\"type\":\"User\"},{\"name\":\"PNP-ITMS\",\"type\":\"Admin\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC 2024', 1, NULL, '[]', '2026-06-24', NULL, NULL, 1, NULL),
(3, 11, 0, 'ITMS-TSD-0036', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"4\",\"8\",\"9\"]', 4, NULL, NULL, 'D4:61:37:01:4F:A6', 'INTEL', 12, 6, 16, 'Acer KA242Y / AOC 1950', 24, 3, '[{\"name\":\"hrali\",\"type\":\"User\"},{\"name\":\"su\",\"type\":\"Admin\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, '[]', '2026-06-24', NULL, NULL, 1, NULL),
(4, 12, 0, 'ITMS-ITSD-148', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"4\",\"8\"]', 2, NULL, NULL, ' D4:61:37:01:4E:EB', 'Intel', 12, 6, 16, 'Acer KA242Y', 24, 3, '[{\"name\":\"acer\",\"type\":\"Admin\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft 365 Apps for Enterprise', 1, NULL, '[]', '2026-06-24', NULL, NULL, 1, NULL),
(5, 13, 0, 'ITMS-ITSD-0028', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"4\",\"8\",\"9\"]', 4, NULL, NULL, 'D4:61:37:01:51:4E', 'Inter', 12, 6, 16, 'Acer KA242Y', 24, 5, '[{\"name\":\"ragabriel\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"},{\"name\":\"ITSD\",\"type\":\"User\"},{\"name\":\"Guest\",\"type\":\"User\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, '[]', '2026-06-24', NULL, NULL, 1, NULL),
(6, 14, 0, 'ITMS-ITSD-0025', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"4\",\"8\",\"9\"]', 4, NULL, NULL, 'D4:61:37:01:4F:C0', 'Inter', 12, 6, 16, 'Acer KA242Y', 24, 3, '[{\"name\":\"admin\",\"type\":\"Admin\"},{\"name\":\"mddelacruz\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-24', NULL, NULL, 1, NULL),
(7, 15, 0, 'ITMS-ITSD-0031', 1, NULL, 'Windows 10 Pro', 1, NULL, 1, '[\"2\",\"4\",\"8\",\"10\"]', 4, NULL, NULL, 'D4:61:37:01:52:A1', 'Intel', 12, 6, 16, 'Acer KA242Y / Acer V196HQL', 24, 4, '[{\"name\":\"ebnavarro\",\"type\":\"Admin\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"},{\"name\":\"Guest\",\"type\":\"User\"}]', NULL, NULL, 'Microsoft 365 Apps for Business', 1, NULL, NULL, '2026-06-24', NULL, NULL, 1, NULL),
(8, 16, 0, 'ITMS-ITSD-0034', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"4\",\"8\"]', 2, NULL, NULL, 'D4:61:37:01:52:93', 'Inter', 12, 6, 16, 'Acer KA242Y', 24, 4, '[{\"name\":\"Administrator\",\"type\":\"Admin\"},{\"name\":\"joeym\",\"type\":\"\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-24', NULL, NULL, 1, NULL),
(9, 17, 0, 'ITMS-LO-221', 1, NULL, 'Windows 11 Home Single Language', 1, NULL, 1, '[\"2\",\"3\",\"8\"]', 3, NULL, NULL, '10:B1:DF:98:87:ED', 'Intel', 12, 6, 16, 'Acer VG240Y S', 24, 5, '[{\"name\":\"ITMS\",\"type\":\"Admin\"},{\"name\":\"ITMS Conference\",\"type\":\"Admin\"},{\"name\":\"Room\",\"type\":\"\"},{\"name\":\"SAS Admin\",\"type\":\"Admin\"},{\"name\":\"admin\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft 365 Personal', 1, NULL, NULL, '2026-06-24', NULL, NULL, 1, NULL),
(10, 18, 0, 'ITMS-ITSD-0033', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"6\",\"8\",\"9\"]', 3, NULL, NULL, 'D4:61:37:01:4F:81', 'Intel', 12, 6, 16, 'Acer KA242Y', 24, 3, '[{\"name\":\"mdpimentel\",\"type\":\"\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-24', NULL, NULL, 1, NULL),
(11, 19, 0, 'ITMS-ITSD-0022', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"8\",\"9\",\"10\",\"11\"]', 5, NULL, NULL, 'D4:61:37:01:4E:D6', 'Inter', 12, 6, 16, 'Acer KA242Y', 24, 5, '[{\"name\":\"mldimaculangan\",\"type\":\"User\"},{\"name\":\"su\",\"type\":\"Admin\"},{\"name\":\"ADMIN\",\"type\":\"Admin\"},{\"name\":\"ITSD GUEST\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-24', NULL, NULL, 1, NULL),
(12, 20, 0, 'ITMS-ITSD-0020', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"8\",\"9\"]', 3, NULL, NULL, 'D4:61:37:01:50:1C', 'Intel', 12, 6, 16, 'Acer KA242Y', 24, 3, '[{\"name\":\"pa.ramos\",\"type\":\"Admin\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"SU\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-24', NULL, NULL, 1, NULL),
(13, 21, 0, 'DESKTOP-055U73P', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"8\"]', NULL, NULL, NULL, NULL, 'Intel', 12, 6, 16, NULL, NULL, 0, '[]', NULL, NULL, '-', 1, NULL, NULL, '2026-06-24', NULL, NULL, 1, NULL),
(14, 22, 0, 'ITMS-ITSD-0027', 1, NULL, 'Windows 10 Pro', 1, NULL, 1, '[\"4\",\"8\"]', 2, NULL, NULL, '50:65:F3:2D:D8:A1', 'Inter', 4, 4, 6, 'Lenovo LEN LI2054A', 19, 3, '[{\"name\":\"lvseculles\",\"type\":\"Admin\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office Professional Plus 2013', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(15, 23, 0, 'ITMS-ITSD-0021', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"8\",\"9\"]', 3, NULL, NULL, ' D4:61:37:01:52:82', 'Inter', 12, 6, 16, 'Acer KA242Y', 24, 4, '[{\"name\":\"rn.rosete\",\"type\":\"Admin\"},{\"name\":\"Su\",\"type\":\"Admin\"},{\"name\":\"PNP-ITMS\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(16, 24, 0, 'ITMS-ITSD-0024', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"6\"]', 1, NULL, NULL, ' CC:96:E5:15:23:2B', 'Intel', 13, 16, 32, 'Acer KA242Y', 24, 3, '[{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"Shine\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office Professional 2021', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(17, 26, 0, 'ITMS_ITSD-OLCIM', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"4\",\"8\",\"9\",\"10\"]', 5, NULL, NULL, 'D4:61:37:01:51:4B', 'Intel', 12, 6, 16, 'Acer KA242Y', 24, 4, '[{\"name\":\"apruaro\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"ITSD\",\"type\":\"User\"},{\"name\":\"SU\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(18, 27, 0, 'ITMS-ITSD-0035', 1, NULL, 'Windows 10 Pro', 1, NULL, 1, '[\"1\",\"2\",\"4\",\"8\"]', 5, NULL, NULL, ' F0:A7:31:29:9B:ED', 'Intel', 7, 4, 8, 'Acer V196HQL', 19, 5, '[{\"name\":\"ITSD\",\"type\":\"Admin\"},{\"name\":\"OJT\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"su\",\"type\":\"Admin\"},{\"name\":\"gdbejarin\",\"type\":\"User\"}]', NULL, NULL, '-', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(19, 28, 0, 'ITMS-ITSD-0052', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"4\",\"7\",\"10\",\"11\"]', 4, NULL, NULL, '98:EE:CB:4E:BF:82', 'Intel', 6, 4, 8, 'BenQ G910WAL', 19, 4, '[{\"name\":\"LOT\",\"type\":\"User\"},{\"name\":\"rbpaulo\",\"type\":\"User\"},{\"name\":\"sgbinarao\",\"type\":\"User\"},{\"name\":\"SU\",\"type\":\"Admin\"}]', NULL, NULL, '-', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(20, 0, 0, 'NETWORK-MONITOR (SERVER FARM)', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"11\"]', 1, NULL, NULL, NULL, 'Intel', 10, NULL, 16, NULL, NULL, 0, '[]', NULL, NULL, '-', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(21, 29, 0, 'ITMS-ITSD-0040', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"11\"]', NULL, NULL, NULL, NULL, 'Intel', 14, 10, 16, 'Xitrix', NULL, 0, '[]', NULL, NULL, 'Microsoft Office Home 2024', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(22, 30, 0, 'ITMS-ITSD-0014', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"9\"]', 1, NULL, NULL, ' F4:B5:20:77:48:A6', 'Intel', 14, 10, 16, 'Xitrix', 24, 4, '[{\"name\":\"ctdelaperi\",\"type\":\"User\"},{\"name\":\"mbalde\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"SU\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office Home 2024', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(23, 31, 0, 'ITMS-ITSD-0017', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"4\",\"8\",\"9\"]', 4, NULL, NULL, ' D4:61:37:01:52:BF', 'Intel', 12, 6, 16, 'Acer KA242Y ', 24, 6, '[{\"name\":\"guguifayajr\",\"type\":\"User\"},{\"name\":\"admin\",\"type\":\"Admin\"},{\"name\":\"jaildefonso\",\"type\":\"User\"},{\"name\":\"SA\",\"type\":\"Admin\"},{\"name\":\"SU\",\"type\":\"Admin\"},{\"name\":\"PNP-ITMS\",\"type\":\"User\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(24, 32, 0, 'ITMS-ITSD-0013', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"8\",\"9\"]', 2, NULL, NULL, ' D4:61:37:01:52:5A', 'Intel', 12, 6, 16, 'Acer KA242Y/Lenovo LI2215sD', 24, 2, '[{\"name\":\"jbalegre\",\"type\":\"User\"},{\"name\":\"SU\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(25, 33, 0, 'ITMS-ITSD-0015', 1, NULL, 'Windows 10 Pro', 1, NULL, 1, '[\"2\",\"8\"]', 2, NULL, NULL, ' 94:C6:91:F9:7B:B1', 'AMD', 9, 4, 8, 'Acer V196HQL', 18, 4, '[{\"name\":\"cmhernandez\",\"type\":\"User\"},{\"name\":\"FOR ALL\",\"type\":\"Admin\"},{\"name\":\"jffloro\",\"type\":\"Admin\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office Professional Plus 2019', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(26, 34, 0, 'ITMS-ITSD-0038', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"6\",\"9\"]', 3, NULL, NULL, ' D4:61:37:00:88:C3', 'Intel', 12, 4, 8, 'Acer', NULL, 3, '[{\"name\":\"mdcbaclig\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"SU\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(27, 35, 0, 'ITMS-ITSD-0012', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"4\",\"8\",\"9\"]', 4, NULL, NULL, ' D4:61:37:01:52:BB', 'Intel ', 12, 6, 16, 'Acer KA242Y', 24, 5, '[{\"name\":\"mbalde\",\"type\":\"User\"},{\"name\":\"NMS\",\"type\":\"User\"},{\"name\":\"SU\",\"type\":\"Admin\"},{\"name\":\"acbilloso\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(28, 36, 0, 'ITMS-ITSD-0011', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\",\"4\",\"8\",\"9\",\"10\"]', 6, NULL, NULL, ' D4:61:37:01:52:AA', 'Intel', 12, 6, 16, 'Acer KA242Y', 24, 3, '[{\"name\":\"azmoslares\",\"type\":\"Admin\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"SU\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(29, 37, 0, 'ITMS-ITSD-0039', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"9\"]', 1, NULL, NULL, ' A8:59:5F:28:1E:3C', 'Intel', 14, 10, 16, 'Xitrix', 24, 3, '[{\"name\":\"SU\",\"type\":\"Admin\"},{\"name\":\"User-PC\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"}]', NULL, NULL, 'Microsoft Office Home 2024', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(30, 38, 0, 'ITMS-ITSD-0026', 1, NULL, 'Windows 11 Pro', 1, NULL, 1, '[\"2\"]', 2, NULL, NULL, ' D4:61:37:01:54:8F', 'Intel', 12, 6, 16, 'Acer KA242Y', 24, 8, '[{\"name\":\"azbayaua\",\"type\":\"User\"},{\"name\":\"JAguarin\",\"type\":\"User\"},{\"name\":\"MDavid\",\"type\":\"User\"},{\"name\":\"MTon-ogan\",\"type\":\"User\"},{\"name\":\"NSantos\",\"type\":\"User\"},{\"name\":\"RGTM\",\"type\":\"User\"},{\"name\":\"sasadmin\",\"type\":\"Admin\"},{\"name\":\"Helpdesk\",\"type\":\"User\"}]', NULL, NULL, 'Microsoft Office LTSC Professional Plus 2021', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL),
(31, 39, 0, 'ITMS-SAO-0022', 14, NULL, 'Windows 11 Home Single Language', 1, NULL, 1, '[\"2\",\"4\",\"8\"]', 3, NULL, NULL, '88:AE:DD:25:E1:5E', 'Intel ', 12, 12, 16, 'Acer K242HYL', 24, 2, '[{\"name\":\"63995\",\"type\":\"Admin\"},{\"name\":\"defaultuser100000\",\"type\":\"User\"}]', NULL, NULL, 'Microsoft 365 Apps for Enterprise', 1, NULL, NULL, '2026-06-25', NULL, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) DEFAULT NULL,
  `device_id` int(11) DEFAULT NULL,
  `device_code` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `device_types`
--

CREATE TABLE `device_types` (
  `id` int(11) NOT NULL,
  `type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `device_types`
--

INSERT INTO `device_types` (`id`, `type`) VALUES
(1, 'Desktop'),
(2, 'Laptop'),
(3, 'Printer'),
(4, 'Switch'),
(5, 'Router'),
(6, 'Firewall');

-- --------------------------------------------------------

--
-- Table structure for table `divisions`
--

CREATE TABLE `divisions` (
  `id` int(11) NOT NULL,
  `division` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `divisions`
--

INSERT INTO `divisions` (`id`, `division`) VALUES
(1, 'ITSD'),
(2, 'SMD'),
(3, 'ISSD'),
(4, 'ITPMD'),
(5, 'PTD'),
(6, 'DMD'),
(7, 'ARMD'),
(8, 'PTDLAB'),
(9, 'CI'),
(10, 'PCR'),
(11, 'LS'),
(12, 'IHSS'),
(13, 'BFS'),
(14, 'SAO'),
(15, 'SF'),
(16, 'PCC-SF'),
(17, 'TECHSUPP');

-- --------------------------------------------------------

--
-- Table structure for table `endpoint_security`
--

CREATE TABLE `endpoint_security` (
  `id` int(11) NOT NULL,
  `antivirus` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `endpoint_security`
--

INSERT INTO `endpoint_security` (`id`, `antivirus`) VALUES
(1, 'Trendmicro'),
(2, 'Sophos'),
(3, 'Cybereason'),
(4, 'Bitdefender'),
(5, 'UTMStack'),
(6, 'Qualys'),
(7, 'Avast'),
(8, 'Windows Defender'),
(9, 'eScan'),
(10, 'Cynet'),
(11, 'Others');

-- --------------------------------------------------------

--
-- Table structure for table `firewalls`
--

CREATE TABLE `firewalls` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `no_of_ports` int(11) DEFAULT NULL,
  `no_of_active_ports` int(11) DEFAULT NULL,
  `firmware_version` varchar(255) DEFAULT NULL,
  `management_interface_type` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL,
  `is_remotely_accessible` tinyint(1) DEFAULT NULL,
  `remote_connection_details` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `pnp_focal_person` varchar(255) DEFAULT NULL,
  `contact_details` int(11) DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_type` varchar(255) DEFAULT NULL,
  `acquisition_details` text DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `headsets`
--

CREATE TABLE `headsets` (
  `id` int(11) NOT NULL,
  `device_code` varchar(255) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_details` text DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` timestamp NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laptops`
--

CREATE TABLE `laptops` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `device_name` varchar(150) NOT NULL,
  `division_id` int(11) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `is_os_licensed` tinyint(1) DEFAULT NULL,
  `os_license_key` varchar(255) DEFAULT NULL,
  `is_remote_acc` tinyint(1) DEFAULT NULL,
  `endpoint_security_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `no_of_installed_anti_virus` int(11) DEFAULT NULL,
  `date_installed` date DEFAULT NULL,
  `guid` varchar(100) DEFAULT NULL,
  `mac_address` varchar(100) DEFAULT NULL,
  `cpu_brand` varchar(100) DEFAULT NULL,
  `cpu_generation` int(11) DEFAULT NULL,
  `cpu_cores` int(11) DEFAULT NULL,
  `gb_ram` int(11) DEFAULT NULL,
  `monitor_brand` varchar(100) DEFAULT NULL,
  `monitor_size_inches` int(11) DEFAULT NULL,
  `no_of_user_accounts` int(11) DEFAULT NULL,
  `user_account_type` longtext DEFAULT NULL,
  `authorized_software` text DEFAULT NULL,
  `unauthorized_software` text DEFAULT NULL,
  `office_application` varchar(150) DEFAULT NULL,
  `is_office_licensed` tinyint(1) DEFAULT NULL,
  `office_license_key` varchar(255) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date DEFAULT current_timestamp(),
  `last_updated_at` date DEFAULT NULL,
  `par_serial_no` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `acquisition_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `others`
--

CREATE TABLE `others` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `acquisition_details` text DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL,
  `device_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personnels`
--

CREATE TABLE `personnels` (
  `id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `rank_id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personnels`
--

INSERT INTO `personnels` (`id`, `division_id`, `rank_id`, `first_name`, `middle_name`, `last_name`, `created_by`, `is_active`) VALUES
(1, 1, 1, 'ITSD', 'ENCODER', '.', 2, 0),
(2, 1, 1, 'LAYLA', 'BALMOND', 'THAMUZ', 2, 0),
(3, 1, 14, 'NEW', 'NEW', 'NEW', 3, 0),
(4, 1, 1, 'NGUYEN', '', 'AMURAO', 3, 0),
(5, 1, 1, 'BRENON', '', 'BANAO', 3, 0),
(6, 17, 1, 'MARK', '', 'AQUINO', 3, 0),
(7, 1, 14, 'BRANDON JAKE', 'FERNANDEZ', 'DIAZ', 1, 0),
(8, 1, 1, 'ARREN LEE', 'C', 'ADULTA', 1, 1),
(9, 1, 4, 'MELVIN', 'A', 'CALAPANO', 6, 1),
(10, 1, 1, 'HUSSEIN', 'R', 'ALI', 6, 0),
(11, 1, 1, 'HUSSEIN', 'R', 'ALI', 7, 1),
(12, 1, 1, 'ELMAN', 'A', 'GAJO', 7, 1),
(13, 1, 1, 'ROSALYN', 'A.', 'GABRIEL', 7, 1),
(14, 1, 1, 'MARIA CRISTINA', 'D', 'DELA CRUZ', 7, 1),
(15, 1, 1, 'JOHN ERICK', 'M', 'RAMOS', 7, 1),
(16, 1, 1, 'JOEY', 'M', 'MARINTES', 7, 1),
(17, 1, 1, 'KHEN', '', 'FABIAN', 7, 1),
(18, 1, 1, 'MARGIE', 'D', 'PIMENTEL', 7, 1),
(19, 1, 1, 'MARLYN', 'L', 'DIMACULANGAN', 7, 1),
(20, 1, 1, 'PRINCESS SANNY', 'A', 'RAMOS', 7, 1),
(21, 1, 1, 'EDMUND', 'M', 'RIVERA', 7, 1),
(22, 1, 1, 'LILIBETH', 'V', 'SECULLES', 7, 1),
(23, 1, 1, 'ROSENA', 'N', 'ROSETE', 7, 1),
(24, 1, 1, 'SUNSHINE', 'G', 'BINARA', 1, 1),
(25, 1, 2, 'ERIC', 'M', 'FERNANDEZ', 7, 1),
(26, 1, 1, 'ANN MARGARETH', 'R', 'RUARO', 7, 1),
(27, 1, 1, 'PRINCE ANDREI', '', 'ZAFE', 7, 1),
(28, 1, 1, 'RUBY', 'B', 'PAULO', 7, 1),
(29, 1, 1, 'ALBERT', 'V', 'MANANON', 7, 1),
(30, 1, 1, 'CHRISTOPHER', 'T', 'DELA PERI', 7, 1),
(31, 1, 1, 'JANN ARLY', '', 'ILDEFONSO', 7, 1),
(32, 1, 1, 'JAYBEE', 'B', 'ALEGRE', 7, 1),
(33, 1, 1, 'JOVENCIO', 'F', 'FLORO', 7, 1),
(34, 1, 1, 'MICHAEL', 'DC', 'BACLIG', 7, 1),
(35, 1, 1, 'MILA', 'B', 'ALDE', 7, 1),
(36, 1, 1, 'RIZZA', 'Z', 'MORALES', 7, 1),
(37, 1, 2, 'AVELINO', 'M', 'REYES', 7, 1),
(38, 1, 10, 'ANGELO', 'Z', 'BAYAUA', 7, 1),
(39, 14, 1, 'RHIZA', 'A', 'DALLEGO', 8, 1);

-- --------------------------------------------------------

--
-- Table structure for table `printers`
--

CREATE TABLE `printers` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_details` text DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ranks`
--

CREATE TABLE `ranks` (
  `id` int(11) NOT NULL,
  `rank` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 999
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ranks`
--

INSERT INTO `ranks` (`id`, `rank`, `sort_order`) VALUES
(1, 'NUP', 14),
(2, 'PAT', 13),
(3, 'PCPL', 12),
(4, 'PSSG', 11),
(5, 'PMSG', 10),
(6, 'PSMS', 9),
(7, 'PCMS', 8),
(8, 'PEMS', 7),
(9, 'PLT', 6),
(10, 'PCPT', 5),
(11, 'PMAJ', 4),
(12, 'PLTCOL', 3),
(13, 'PCOL', 2),
(14, 'PBGEN', 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'superadmin'),
(2, 'admin'),
(3, 'encoder');

-- --------------------------------------------------------

--
-- Table structure for table `routers`
--

CREATE TABLE `routers` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `no_of_ports` int(11) DEFAULT NULL,
  `no_of_active_ports` int(11) DEFAULT NULL,
  `active_port_ip_address_range` varchar(255) DEFAULT NULL,
  `firmware_version` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL,
  `is_remotely_accessible` tinyint(1) DEFAULT NULL,
  `remote_connection_details` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `pnp_focal_person` varchar(255) DEFAULT NULL,
  `contact_details` int(11) DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_type` varchar(255) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL,
  `division_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `splitters`
--

CREATE TABLE `splitters` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `hdmi_in` int(11) DEFAULT NULL,
  `hdmi_out` int(11) DEFAULT NULL,
  `no_of_ports` int(11) DEFAULT NULL,
  `acquisition_details` text DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `switchers`
--

CREATE TABLE `switchers` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `hdmi_in` int(11) DEFAULT NULL,
  `hdmi_out` int(11) DEFAULT NULL,
  `no_of_ports` int(11) DEFAULT NULL,
  `acquisition_details` text DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `switches`
--

CREATE TABLE `switches` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `manufacturer` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `serial_no` varchar(255) NOT NULL,
  `no_of_ports` int(11) NOT NULL,
  `no_of_active_ports` int(11) NOT NULL,
  `no_of_managed` int(11) NOT NULL,
  `no_of_unmanaged` int(11) NOT NULL,
  `firmware_version` varchar(255) NOT NULL,
  `is_vlan_supported` tinyint(1) NOT NULL,
  `location` varchar(255) NOT NULL,
  `is_status` tinyint(1) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_remote_access` tinyint(1) NOT NULL,
  `remote_connection_details` text NOT NULL,
  `remarks` text NOT NULL,
  `pnp_focal_person` varchar(255) NOT NULL,
  `contact_details` varchar(50) NOT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_type` varchar(255) NOT NULL,
  `acquisition_details` text NOT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ups`
--

CREATE TABLE `ups` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `capacity_va` int(11) DEFAULT NULL,
  `capacity_watts` int(11) DEFAULT NULL,
  `battery_type` varchar(255) DEFAULT NULL,
  `backup_time` int(11) DEFAULT NULL,
  `input_voltage` int(11) DEFAULT NULL,
  `output_voltage` int(11) DEFAULT NULL,
  `acquisition_details` text DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rank_id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `creator_user_id` int(11) NOT NULL,
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `division_id`, `email`, `password`, `rank_id`, `first_name`, `middle_name`, `last_name`, `username`, `is_active`, `creator_user_id`, `created_date`, `last_update_at`) VALUES
(1, 1, 1, 'itsd.superadmin@itms.com', '$2y$10$aisUHgeqUkfpDVPYWE6KuOV4pzsD8twbv11LfwnUhpykBuh/Z9bXm', 1, 'ITSD', 'SUPER', 'ADMIN', 'superadmin', 1, 1, '2026-06-06', NULL),
(2, 3, 1, 'itsd.encoder@itms.com', '$2y$10$Wv7Z5Nzu7yDNol0cgfNQQOZ5nZ6dgLOY/A40gjbbI5/Vau6BF2li6', 1, 'BRANDON', 'JAKE', 'FERNANDEZ DIAZ', 'diazbf', 1, 1, '2026-06-08', NULL),
(3, 2, 1, 'itsd.admin@itms.com', '$2y$10$A0kyu1oQ6yoIOgBxiyJTH.Bz9f7/ztFqYBzc0DmNPc9f46tdlAuce', 1, 'ITSD', 'ADMIN', '01', '01ia', 1, 1, '2026-06-08', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cameras`
--
ALTER TABLE `cameras`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `desktops`
--
ALTER TABLE `desktops`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_code` (`device_code`);

--
-- Indexes for table `device_types`
--
ALTER TABLE `device_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `divisions`
--
ALTER TABLE `divisions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `endpoint_security`
--
ALTER TABLE `endpoint_security`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `firewalls`
--
ALTER TABLE `firewalls`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `headsets`
--
ALTER TABLE `headsets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `laptops`
--
ALTER TABLE `laptops`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `others`
--
ALTER TABLE `others`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `personnels`
--
ALTER TABLE `personnels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `printers`
--
ALTER TABLE `printers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ranks`
--
ALTER TABLE `ranks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `routers`
--
ALTER TABLE `routers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `splitters`
--
ALTER TABLE `splitters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `switchers`
--
ALTER TABLE `switchers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `switches`
--
ALTER TABLE `switches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ups`
--
ALTER TABLE `ups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cameras`
--
ALTER TABLE `cameras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `desktops`
--
ALTER TABLE `desktops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `divisions`
--
ALTER TABLE `divisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `endpoint_security`
--
ALTER TABLE `endpoint_security`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `firewalls`
--
ALTER TABLE `firewalls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `headsets`
--
ALTER TABLE `headsets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laptops`
--
ALTER TABLE `laptops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `others`
--
ALTER TABLE `others`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personnels`
--
ALTER TABLE `personnels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `printers`
--
ALTER TABLE `printers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ranks`
--
ALTER TABLE `ranks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `routers`
--
ALTER TABLE `routers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `splitters`
--
ALTER TABLE `splitters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `switchers`
--
ALTER TABLE `switchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `switches`
--
ALTER TABLE `switches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ups`
--
ALTER TABLE `ups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
