-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 30, 2026 at 08:51 AM
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
  `acquisition_date` date NOT NULL,
  `acquisition_details` text NOT NULL,
  `brand` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cameras`
--

INSERT INTO `cameras` (`id`, `device_code`, `personnel_id`, `device_id`, `division_id`, `acquisition_date`, `acquisition_details`, `brand`, `model`, `serial_no`, `previous_owners_id`, `created_date`, `last_update_at`, `is_active`) VALUES
(3, 'CAM-003', 4, 103, 3, '2026-05-27', 'Procured via bidding', 'Nikon', 'D3500', 'SN-NIK-003', '[\"7\",\"8\"]', '2026-05-28 16:00:00', NULL, 1),
(4, 'CAM-004', 1, 104, 1, '2026-05-27', 'Office deployment', 'Canon', 'EOS 2000D', 'SN-CAN-004', '[\"7\",\"8\"]', '2026-05-26 16:00:00', NULL, 1);

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
  `ip_address` varchar(50) NOT NULL,
  `os` varchar(100) NOT NULL,
  `is_os_licensed` tinyint(1) NOT NULL,
  `is_remote_acc` tinyint(1) NOT NULL,
  `endpoint_security_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `no_of_installed_anti_virus` int(11) NOT NULL,
  `date_installed` date NOT NULL,
  `guid` varchar(100) NOT NULL,
  `mac_address` varchar(100) NOT NULL,
  `cpu_brand` varchar(100) NOT NULL,
  `cpu_cores` int(11) NOT NULL,
  `gb_ram` int(11) NOT NULL,
  `monitor_brand` varchar(100) NOT NULL,
  `monitor_size_inches` int(11) NOT NULL,
  `no_of_user_accounts` int(11) NOT NULL,
  `user_account_type` varchar(100) NOT NULL,
  `authorized_software` text DEFAULT NULL,
  `unauthorized_software` text DEFAULT NULL,
  `office_application` varchar(150) NOT NULL,
  `is_office_licensed` tinyint(1) NOT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_updated_at` date DEFAULT NULL,
  `os_license_key` varchar(255) NOT NULL,
  `office_license_key` varchar(255) NOT NULL,
  `par_serial_no` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `acquisition_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `desktops`
--

INSERT INTO `desktops` (`id`, `personnel_id`, `device_id`, `device_name`, `division_id`, `ip_address`, `os`, `is_os_licensed`, `is_remote_acc`, `endpoint_security_id`, `no_of_installed_anti_virus`, `date_installed`, `guid`, `mac_address`, `cpu_brand`, `cpu_cores`, `gb_ram`, `monitor_brand`, `monitor_size_inches`, `no_of_user_accounts`, `user_account_type`, `authorized_software`, `unauthorized_software`, `office_application`, `is_office_licensed`, `previous_owners_id`, `created_date`, `last_updated_at`, `os_license_key`, `office_license_key`, `par_serial_no`, `is_active`, `acquisition_date`) VALUES
(3, 1, 1, 'ITMS-ITSD-35', 1, '192.168.43.1', 'Windows 11 Pro', 1, 1, '[1,2,3]', 3, '2026-01-25', 'AAAAA123-OOOO-4567-890C-123DEF456789', '2b:fc:f3:f3:f3:2b', 'Intel', 12, 12, 'Acer', 16, 1, 'Admin', 'Google Chrome, Office and AnyDesk', 'Crack Software', 'Microsoft Office 2021 Professional', 1, '[1,4,6]', '2026-04-14', NULL, '8DNJ37-89SJF8-89HSDF-9DUGHS', '8D3457-89SJF8-8934DF-9D67HS', 'PAR-001-01', 1, NULL),
(4, 1, 1001, 'DESKTOP-001', 2, '192.168.1.10', 'Windows 11 Pro', 1, 1, '[1,2]', 2, '2026-05-22', 'GUID-001', 'AA:BB:CC:DD:EE:01', 'Intel i5', 4, 16, 'Dell', 24, 1, 'admin', NULL, NULL, 'Microsoft 365', 1, '[1,7,8]', '2026-05-22', NULL, 'OSKEY-001', 'OFFICEKEY-001', 'PAR-001', 1, NULL),
(5, 4, 0, 'DESKTOP-005', 8, '192.168.1.20', 'Windows 11 Enterprise N', 0, 0, '[\"2\",\"5\"]', 2, '2026-05-27', 'GUID-123', 'AA:BB:CC:DD:EE:01', 'Intel', 8, 16, 'Dell', 15, 1, 'Admin', 'fff', 'ff', 'Microsoft Office Home & Business 2019', 0, '[\"6\"]', '2026-05-27', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, '0000-00-00'),
(6, 8, 0, 'DESKTOP-005', 2, '192.168.1.20', 'Windows 10 Home Single Language', 0, 1, '[\"2\",\"5\"]', 2, '2026-05-27', 'GUID-123', 'AA:BB:CC:DD:EE:01', 'Intel i5', 8, 16, 'Dell', 15, 1, 'Admin', 'asdfasd', 'agasdrgwer', 'Microsoft Office Professional Plus 2019', 0, '[\"4\"]', '2026-05-27', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, NULL),
(7, 8, 0, 'DESKTOP-0017', 4, '192.168.1.20', 'Windows 10 Education', 0, 1, '[\"2\",\"5\"]', 2, '2026-05-27', 'GUID-123', 'AA:BB:CC:DD:EE:01', 'Intel i5', 8, 16, 'Dell', 15, 1, 'Admin', 'eryr', 'er', 'Microsoft Office Professional 2021', 0, '[\"6\"]', '2026-05-28', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, NULL),
(23, 7, 0, 'A-', 1, '192.168.1.20', 'Windows 10 Home Single Language', 0, 1, '[\"2\",\"6\"]', 2, '2026-05-27', 'GUID-123', 'AA:BB:CC:DD:EE:01', 'Intel', 8, 16, 'Dell', 15, 1, 'Admin', 'qwerty', 'qwerty', 'Microsoft Excel', 1, '[\"4\"]', '2026-05-30', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, NULL),
(24, 4, 0, 'A-1', 2, '192.168.1.20', 'Windows 10 Home Single Language', 1, 1, '[\"3\",\"6\"]', 2, '2026-05-27', 'GUID-123', 'AA:BB:CC:DD:EE:01', 'Intel', 8, 16, 'Dell', 15, 1, 'Admin', 'sdefqew', 'qsfqewfqew', 'Microsoft PowerPoint', 1, '[\"6\",\"4\"]', '2026-05-30', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, NULL);

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
(7, 'Others');

-- --------------------------------------------------------

--
-- Table structure for table `firewalls`
--

CREATE TABLE `firewalls` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `manufacturer` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `serial_no` varchar(255) NOT NULL,
  `no_of_ports` int(11) NOT NULL,
  `no_of_active_ports` int(11) NOT NULL,
  `firmware_version` varchar(255) NOT NULL,
  `management_interface_type` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `is_remotely_accessible` tinyint(1) NOT NULL,
  `remote_connection_details` text NOT NULL,
  `remarks` text NOT NULL,
  `pnp_focal_person` varchar(255) NOT NULL,
  `contact_details` int(11) NOT NULL,
  `acquisition_date` date NOT NULL,
  `acquisition_type` varchar(255) NOT NULL,
  `acquisition_details` text NOT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `firewalls`
--

INSERT INTO `firewalls` (`id`, `personnel_id`, `division_id`, `device_id`, `manufacturer`, `model`, `serial_no`, `no_of_ports`, `no_of_active_ports`, `firmware_version`, `management_interface_type`, `location`, `is_active`, `is_remotely_accessible`, `remote_connection_details`, `remarks`, `pnp_focal_person`, `contact_details`, `acquisition_date`, `acquisition_type`, `acquisition_details`, `previous_owners_id`, `created_date`, `last_updated_at`) VALUES
(1, 6, 3, 3, 'Cisco', 'L350', 'PAR1231232', 12, 12, '123RFWQEFWE', 'N/A', 'SMD', 1, 1, '123124124', 'N/A', 'Juan', 1243124124, '2026-05-30', '12412', 'Office deployment', '[8,7]', '2026-05-29', '2026-05-30');

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
  `acquisition_date` date NOT NULL,
  `acquisition_details` text NOT NULL,
  `brand` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `headsets`
--

INSERT INTO `headsets` (`id`, `device_code`, `personnel_id`, `device_id`, `division_id`, `acquisition_date`, `acquisition_details`, `brand`, `model`, `serial_no`, `previous_owners_id`, `created_date`, `last_update_at`, `is_active`) VALUES
(5, '', 1, 205, 2, '2026-05-27', 'New batch procurement', 'Razer', 'Kraken X', 'HS-RAZ-005', '[\"4\"]', '2026-05-26 16:00:00', NULL, 1),
(6, '', 7, 0, 7, '2026-05-27', 'Purchased', 'Acer', 'L350', 'APSDEF123', '[\"1\",\"4\"]', '2026-05-26 16:00:00', NULL, 1),
(7, '', 7, 0, 4, '2026-05-27', 'Purchased', 'Acer', 'L350', 'APSDEF123', '[\"8\"]', '2026-05-26 16:00:00', NULL, 1);

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
  `ip_address` varchar(50) NOT NULL,
  `os` varchar(100) NOT NULL,
  `is_os_licensed` tinyint(1) NOT NULL,
  `is_remote_acc` tinyint(1) NOT NULL DEFAULT 1,
  `endpoint_security_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `no_of_installed_anti_virus` int(11) NOT NULL,
  `date_installed` date NOT NULL,
  `guid` varchar(100) NOT NULL,
  `mac_address` varchar(100) NOT NULL,
  `cpu_brand` varchar(100) NOT NULL,
  `cpu_cores` int(11) NOT NULL,
  `gb_ram` int(11) NOT NULL,
  `monitor_brand` varchar(100) NOT NULL,
  `monitor_size_inches` int(11) NOT NULL,
  `no_of_user_accounts` int(11) NOT NULL,
  `user_account_type` varchar(100) NOT NULL,
  `authorized_software` text DEFAULT NULL,
  `unauthorized_software` text DEFAULT NULL,
  `office_application` varchar(150) NOT NULL,
  `is_office_licensed` tinyint(1) NOT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_updated_at` date DEFAULT NULL,
  `os_license_key` varchar(255) NOT NULL,
  `office_license_key` varchar(255) NOT NULL,
  `par_serial_no` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `acquisition_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laptops`
--

INSERT INTO `laptops` (`id`, `personnel_id`, `device_id`, `device_name`, `division_id`, `ip_address`, `os`, `is_os_licensed`, `is_remote_acc`, `endpoint_security_id`, `no_of_installed_anti_virus`, `date_installed`, `guid`, `mac_address`, `cpu_brand`, `cpu_cores`, `gb_ram`, `monitor_brand`, `monitor_size_inches`, `no_of_user_accounts`, `user_account_type`, `authorized_software`, `unauthorized_software`, `office_application`, `is_office_licensed`, `previous_owners_id`, `created_date`, `last_updated_at`, `os_license_key`, `office_license_key`, `par_serial_no`, `is_active`, `acquisition_date`) VALUES
(1, 1, 1, 'LAPTOP-001', 2, '192.168.1.20', 'Windows 11 Pro', 1, 1, '[\"1\",\"4\"]', 2, '2026-01-01', 'GUID-123', 'AA:BB:CC:DD', 'Intel', 8, 16, 'Dell', 15, 1, 'Admin', 'Chrome, Zoom', 'None', 'Microsoft Office 2021', 1, '[\"8\",\"7\"]', '2026-01-01', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, '0000-00-00'),
(2, 7, 0, 'DESKTOP-002', 3, '192.168.1.20', 'Windows 10 Pro', 0, 1, '[\"1\",\"4\"]', 2, '2026-05-27', 'GUID-123', 'AA:BB:CC:DD:EE:01', 'Intel i5', 8, 16, 'Dell', 15, 1, 'Admin', 'N/A', 'N/A', 'Microsoft Office Home & Business 2019', 0, '[\"6\",\"7\"]', '2026-05-27', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, '0000-00-00'),
(3, 1, 0, 'DESKTOP-002', 3, '192.168.1.20', 'Windows 10 Enterprise N', 0, 0, '[\"2\",\"5\"]', 2, '2026-05-27', 'GUID-123', 'AA:BB:CC:DD:EE:01', 'Intel i5', 8, 16, 'Dell', 15, 1, 'Admin', 'n/a', 'n/a', 'Microsoft Word', 0, '[\"4\"]', '2026-05-27', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, NULL),
(4, 4, 0, 'DESKTOP-002', 9, '192.168.1.20', 'Windows 10 IoT Enterprise', 0, 1, '[\"1\",\"3\",\"6\"]', 2, '2026-05-27', 'GUID-123', 'AA:BB:CC:DD:EE:01', 'Intel i5', 8, 16, 'Dell', 15, 1, 'Admin', 'n/a', 'n/a', 'Microsoft Office LTSC 2021', 0, '[\"7\"]', '2026-05-27', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, NULL),
(5, 1, 0, 'DESKTOP-005', 2, '192.168.1.20', 'Windows 10 Home Single Language', 0, 1, '[\"2\",\"5\"]', 2, '2026-05-27', 'GUID-123', 'AA:BB:CC:DD:EE:01', 'Intel i5', 8, 16, 'Dell', 15, 1, 'Admin', ' ///', '////', 'Microsoft Excel', 0, '[\"8\"]', '2026-05-27', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001', 1, NULL);

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
(1, 1, 14, 'Brandon', 'Jake', 'Fernandez Diaz', 1, 1),
(4, 2, 2, 'Mark', 'Naruto', 'Uzumaki', 12, 1),
(6, 17, 4, 'Zandro', 'James', 'Fernandez Diaz', 19, 1),
(7, 12, 1, 'Gian', 'Sabiniano', 'Diaz', 1, 1),
(8, 5, 2, 'Alejandro', 'Castro', 'Aquino', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `printers`
--

CREATE TABLE `printers` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `acquisition_date` date NOT NULL,
  `acquisition_details` text NOT NULL,
  `brand` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL,
  `serial_no` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `printers`
--

INSERT INTO `printers` (`id`, `personnel_id`, `device_id`, `division_id`, `acquisition_date`, `acquisition_details`, `brand`, `model`, `previous_owners_id`, `created_date`, `last_update_at`, `serial_no`, `is_active`) VALUES
(1, 1, 1, 1, '2026-05-27', 'Purchased', 'EPSON', 'L360', '[\"6\",\"7\"]', '2026-05-18', NULL, '0C78H0C84HF804', 1),
(3, 8, 0, 12, '2026-05-27', 'Purchased', 'Acer', 'L350', '[\"6\",\"7\"]', '2026-05-27', NULL, 'PAR1231232', 1),
(4, 1, 0, 11, '2026-05-27', 'Purchased form local suppliers', 'Acer', 'L350', '[]', '2026-05-27', NULL, 'PAR1231232', 1),
(5, 7, 0, 3, '2026-05-27', 'Purchased', 'Acer', 'L350', '[]', '2026-05-27', NULL, 'PAR1231232', 1),
(6, 4, 0, 2, '2026-05-27', 'Purchased', 'Acer', 'L350', '[\"4\"]', '2026-05-27', NULL, 'PAR1231232', 1);

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
  `manufacturer` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `serial_no` varchar(255) NOT NULL,
  `no_of_ports` int(11) NOT NULL,
  `no_of_active_ports` int(11) NOT NULL,
  `active_port_ip_address_range` varchar(255) NOT NULL,
  `firmware_version` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `is_remotely_accessible` tinyint(1) NOT NULL,
  `remote_connection_details` text NOT NULL,
  `remarks` text NOT NULL,
  `pnp_focal_person` varchar(255) NOT NULL,
  `contact_details` int(11) NOT NULL,
  `acquisition_date` date NOT NULL,
  `acquisition_type` varchar(255) NOT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL,
  `division_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `routers`
--

INSERT INTO `routers` (`id`, `personnel_id`, `device_id`, `manufacturer`, `model`, `serial_no`, `no_of_ports`, `no_of_active_ports`, `active_port_ip_address_range`, `firmware_version`, `location`, `is_active`, `is_remotely_accessible`, `remote_connection_details`, `remarks`, `pnp_focal_person`, `contact_details`, `acquisition_date`, `acquisition_type`, `previous_owners_id`, `created_date`, `last_update_at`, `division_id`) VALUES
(1, 6, 0, 'Cisco', 'EOS 2000D', 'PAR1231232', 12, 12, '21312412', 'IOS XE 17.1.1', 'SMD', 1, 1, 'SSH access via VPN', 'Core switch for all departments', 'Juan Cruz Dela Rosa', 2147483647, '0000-00-00', 'Purchased', '[4]', '2026-05-29', NULL, 1);

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
  `acquisition_date` date NOT NULL,
  `acquisition_type` varchar(255) NOT NULL,
  `acquisition_details` text NOT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date NOT NULL DEFAULT current_timestamp(),
  `last_update_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `switches`
--

INSERT INTO `switches` (`id`, `personnel_id`, `division_id`, `device_id`, `manufacturer`, `model`, `serial_no`, `no_of_ports`, `no_of_active_ports`, `no_of_managed`, `no_of_unmanaged`, `firmware_version`, `is_vlan_supported`, `location`, `is_status`, `is_active`, `is_remote_access`, `remote_connection_details`, `remarks`, `pnp_focal_person`, `contact_details`, `acquisition_date`, `acquisition_type`, `acquisition_details`, `previous_owners_id`, `created_date`, `last_update_at`) VALUES
(1, 1, 1, 0, 'Cisco', 'Catalyst', 'FOC12312ABC', 48, 40, 1, 1, 'IOS XE 17.1.1', 1, 'ITSD', 0, 1, 1, 'SSH access via VPN', 'Core switch for all departments', 'Juan Cruz Dela Rosa', '09837284092', '2026-05-28', 'Purchased', 'Purchased from Cisco', '[\"6\"]', '2026-05-28', '2026-05-28'),
(2, 6, 3, 0, 'Cisco', 'L350', 'PAR-001', 34, 34, 33, 1, 'IOS XE 17.1.1', 1, 'SMD', 0, 1, 1, 'wewvsfdfbh', 'Core switch for all departments', 'Juan Cruz Dela Rosa', '09837284092', '2026-05-28', 'Purchased', 'Purchased from Cisco', '[\"8\"]', '0000-00-00', '2026-05-28'),
(3, 6, 2, 0, 'Cisco', 'EOS 2000D', 'FOC12312ABC', 12, 12, 0, 0, 'IOS XE 17.1.1', 0, 'SMD', 0, 1, 0, 'SSH access via VPN', 'Core switch for all departments', 'Juan Cruz Dela Rosa', '09837284092', '2026-05-29', 'Purchased', 'Purchased from Cisco', '[\"4\",\"6\"]', '2026-05-29', '2026-05-29'),
(4, 7, 4, 0, 'Cisco', 'EOS 2000D', 'FOC12312ABC', 12, 12, 0, 0, 'IOS XE 17.1.1', 1, 'SMD', 0, 1, 1, 'SSH access via VPN', 'Core switch for all departments', 'Juan Cruz Dela Rosa', '09837284092', '2026-05-29', 'Purchased', 'Purchased from Cisco', '[\"6\"]', '2026-05-29', '2026-05-29'),
(5, 1, 9, 0, 'Cisco', 'EOS 2000D', 'FOC12312ABC', 12, 12, 12, 0, '0', 1, 'SMD', 0, 1, 1, 'SSH access via VPN', 'Core switch for all departments', 'Juan Cruz Dela Rosa', '09837284092', '2026-05-29', 'Purchased', 'Purchased from Cisco', '[\"6\"]', '2026-05-29', NULL),
(6, 4, 14, 0, 'Cisco', 'EOS 2000D', 'FOC12312ABC', 12, 12, 12, 0, '0', 1, 'SMD', 0, 1, 1, 'SSH access via VPN', 'Core switch for all departments', 'Juan Cruz Dela Rosa', '09837284092', '2026-05-29', 'Purchased', 'Purchased from Cisco', '[\"6\"]', '2026-05-29', NULL),
(7, 1, 1, 0, 'Cisco', 'EOS 2000D', 'FOC12312ABC', 111, 110, 111, 0, '0', 1, 'ITSD', 0, 1, 1, 'wewvsfdfbh', 'Core switch for all departments', 'Juan Cruz Dela Rosa', '09837284092', '0000-00-00', 'Purchased', 'Purchased from Cisco', '[\"4\",\"8\"]', '2026-05-30', NULL);

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
(1, 1, 1, 'itsd.superadmin@itms.com', '$2y$10$2K4FP/lLrYDK2ccClPbKr.3wMOb8I9oaenJZzpNaGDUEhhitc1kHO', 14, 'Brandon', 'Jake', 'Fernandez Diaz', 'superadmin', 1, 1, '2026-05-12', NULL),
(10, 2, 1, 'admin.itsd@itms.com', '$2y$10$umcGPtWn6lZn5XbBXm0tSupmRqk.YtNvmJuOFMkI/hdgxj8XUOH3K', 2, 'admin', 'admin', 'admin', 'admin,AA', 1, 0, '2026-05-12', NULL),
(11, 2, 1, 'itsd.admin@itms.com', '$2y$10$LdO/uqleUh3/p.32f1qePeZJE1G7TKs0pgskhg9bUDPiOSnfxX.Ri', 8, 'Paul', 'Kenneth', 'De Guzman Agripa', 'Agripa,PD', 1, 0, '2026-05-12', NULL),
(12, 3, 1, 'itsd.encoder@itms.com', '$2y$10$6ou2A0ieaQOIXEfEqRhBH.XXb3zZeKLTqWyfJJstCzWLUdTsX7bLO', 13, 'Brandon', 'Jake', 'Pogi Lang', 'Lang,BP', 0, 0, '2026-05-12', NULL),
(13, 2, 3, 'issd.admin@itms.com', '$2y$10$cHihMBlxeL/ljgmMGQxGieWWY3Gq8gFYqF2lqwueBapi45Or87zBW', 12, 'Ken', 'Zake', 'Fabian', 'Fabian,KZ', 1, 0, '2026-05-12', NULL),
(14, 2, 2, 'smd.admin@itms.com', '$2y$10$yiXwHc/gdkZlornq1HUIfu5aEnGjEkIG73c8XXvdZ.TtSKQBhdTiC', 1, 'Princess', 'Cruz', 'Santa Mesa', 'Santa Mesa,PC', 1, 0, '2026-05-12', NULL),
(15, 2, 9, 'ci.admin@itms.com', '$2y$10$SVfdCN4qRe1BvlVIXObHtegsb8VrnU9QnPUTZrnC8duTzhbVmOg4u', 2, 'Alejandro Jay', 'Fernandez', 'Diaz', 'diazaf', 1, 1, '2026-05-12', NULL),
(16, 2, 14, 'sao.admin@itms.com', '$2y$10$gSbtzDihiFla7JkPb4WT1ucJy83Iii7gCUzMBW2AInEZGRIePMALm', 13, 'Balmond', 'Valentina', 'Fanny', 'fannybv', 1, 1, '2026-05-12', NULL),
(17, 2, 6, 'dmd.admin@itms.com', '$2y$10$3KySSBrx.RAG9P1cUnPrnObh5bThevzWTSfLVKVoDp3v0hv/v8gz2', 3, 'ALUCARD', 'SELENA', 'GOMEZ', 'gomezas', 1, 1, '2026-05-12', NULL),
(18, 2, 8, 'ptdlab.admin@itms.com', '$2y$10$g8FRMAFXTDzK5KZtgbpLFOAruvQlPErL8Jed2qZbS33NJWbRFOAVG', 13, 'LAYLA', 'LESLY', 'VALENTINA', 'valentinall', 1, 1, '2026-05-12', NULL),
(19, 2, 11, 'ls.admin@itms.com', '$2y$10$sq5WA7sGauUNC6GPiHNn..nNDHYjy26pfUtVg1tqjfIAr7aQF8O8C', 13, 'ZANDRO JAMES', 'FERNANDEZ', 'DIAZ', 'diazzf', 1, 1, '2026-05-21', NULL);

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
-- Indexes for table `switches`
--
ALTER TABLE `switches`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `desktops`
--
ALTER TABLE `desktops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `firewalls`
--
ALTER TABLE `firewalls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `headsets`
--
ALTER TABLE `headsets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `laptops`
--
ALTER TABLE `laptops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `personnels`
--
ALTER TABLE `personnels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `printers`
--
ALTER TABLE `printers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ranks`
--
ALTER TABLE `ranks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `routers`
--
ALTER TABLE `routers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `switches`
--
ALTER TABLE `switches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
