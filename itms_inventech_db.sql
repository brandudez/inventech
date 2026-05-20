-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 19, 2026 at 10:06 AM
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
  `last_update_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cameras`
--

INSERT INTO `cameras` (`id`, `device_code`, `personnel_id`, `device_id`, `division_id`, `acquisition_date`, `acquisition_details`, `brand`, `model`, `serial_no`, `previous_owners_id`, `created_date`, `last_update_at`) VALUES
(1, 'CAM-001', 1, 101, 1, '2024-01-10', 'Purchased from local supplier', 'Canon', 'EOS 1500D', 'SN-CAN-001', NULL, '2026-05-18 12:18:37', NULL),
(2, 'CAM-002', 4, 102, 2, '2024-02-15', 'Donated equipment', 'Sony', 'Alpha A6000', 'SN-SON-002', NULL, '2026-05-18 12:18:37', NULL),
(3, 'CAM-003', 4, 103, 3, '2024-03-20', 'Procured via bidding', 'Nikon', 'D3500', 'SN-NIK-003', NULL, '2026-05-18 12:18:37', NULL),
(4, 'CAM-004', 1, 104, 1, '2024-04-05', 'Office deployment', 'Canon', 'EOS 2000D', 'SN-CAN-004', NULL, '2026-05-18 12:18:37', NULL),
(5, 'CAM-005', 1, 105, 2, '2024-05-01', 'Replacement unit', 'Fujifilm', 'X-T200', 'SN-FUJ-005', NULL, '2026-05-18 12:18:37', NULL);

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
  `par_serial_no` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `desktops`
--

INSERT INTO `desktops` (`id`, `personnel_id`, `device_id`, `device_name`, `division_id`, `ip_address`, `os`, `is_os_licensed`, `is_remote_acc`, `endpoint_security_id`, `no_of_installed_anti_virus`, `date_installed`, `guid`, `mac_address`, `cpu_brand`, `cpu_cores`, `gb_ram`, `monitor_brand`, `monitor_size_inches`, `no_of_user_accounts`, `user_account_type`, `authorized_software`, `unauthorized_software`, `office_application`, `is_office_licensed`, `previous_owners_id`, `created_date`, `last_updated_at`, `os_license_key`, `office_license_key`, `par_serial_no`) VALUES
(3, 4, 1, 'ITMS-ITSD-35', 1, '192.168.43.1', 'Windows 11 Pro', 1, 1, '1', 3, '2026-01-25', 'AAAAA123-OOOO-4567-890C-123DEF456789', '2b:fc:f3:f3:f3:2b', 'Intel', 12, 12, 'Acer', 16, 1, 'Admin', 'Google Chrome, Office and AnyDesk', 'Crack Software', 'Microsoft Office 2021 Professional', 1, '1', '2026-04-14', NULL, '8DNJ37-89SJF8-89HSDF-9DUGHS', '8D3457-89SJF8-8934DF-9D67HS', 'PAR-001-01');

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
  `device_code` varchar(255) NOT NULL,
  `personnel_id` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `headsets`
--

CREATE TABLE `headsets` (
  `id` int(11) NOT NULL,
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
  `last_update_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `headsets`
--

INSERT INTO `headsets` (`id`, `personnel_id`, `device_id`, `division_id`, `acquisition_date`, `acquisition_details`, `brand`, `model`, `serial_no`, `previous_owners_id`, `created_date`, `last_update_at`) VALUES
(1, 1, 201, 1, '2024-01-05', 'Purchased for office use', 'Jabra', 'Evolve 20', 'HS-JAB-001', NULL, '2026-05-18 12:25:08', NULL),
(2, 4, 202, 2, '2024-02-10', 'IT department deployment', 'Logitech', 'H390', 'HS-LOG-002', NULL, '2026-05-18 12:25:08', NULL),
(3, 1, 203, 3, '2024-03-15', 'Procured via supplier', 'HyperX', 'Cloud Stinger', 'HS-HYP-003', NULL, '2026-05-18 12:25:08', NULL),
(4, 4, 204, 1, '2024-04-20', 'Replacement headset', 'Sony', 'WH-CH520', 'HS-SON-004', NULL, '2026-05-18 12:25:08', NULL),
(5, 1, 205, 2, '2024-05-01', 'New batch procurement', 'Razer', 'Kraken X', 'HS-RAZ-005', NULL, '2026-05-18 12:25:08', NULL);

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
  `par_serial_no` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laptops`
--

INSERT INTO `laptops` (`id`, `personnel_id`, `device_id`, `device_name`, `division_id`, `ip_address`, `os`, `is_os_licensed`, `is_remote_acc`, `endpoint_security_id`, `no_of_installed_anti_virus`, `date_installed`, `guid`, `mac_address`, `cpu_brand`, `cpu_cores`, `gb_ram`, `monitor_brand`, `monitor_size_inches`, `no_of_user_accounts`, `user_account_type`, `authorized_software`, `unauthorized_software`, `office_application`, `is_office_licensed`, `previous_owners_id`, `created_date`, `last_updated_at`, `os_license_key`, `office_license_key`, `par_serial_no`) VALUES
(1, 1, 0, 'LAPTOP-001', 1, '192.168.1.20', 'Windows 11 Pro', 1, 1, '1', 2, '2026-01-01', 'GUID-123', 'AA:BB:CC:DD', 'Intel', 8, 16, 'Dell', 15, 1, 'Admin', 'Chrome, Zoom', 'None', 'Microsoft Office 2021', 1, '1', '2026-01-01', NULL, 'XXXXX-KEY', 'OFFICE-KEY', 'PAR-001');

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
(1, 2, 1, 'Brandon', 'Jake', 'Fernandez Diaz', 1, 1),
(4, 2, 2, 'Mark', 'Naruto', 'Uzumaki', 12, 1);

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
  `serial_no` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `printers`
--

INSERT INTO `printers` (`id`, `personnel_id`, `device_id`, `division_id`, `acquisition_date`, `acquisition_details`, `brand`, `model`, `previous_owners_id`, `created_date`, `last_update_at`, `serial_no`) VALUES
(1, 1, 1, 1, '2026-05-19', 'Purchased', 'EPSON', 'L360', '1', '2026-05-18', NULL, '0C78H0C84HF804');

-- --------------------------------------------------------

--
-- Table structure for table `ranks`
--

CREATE TABLE `ranks` (
  `id` int(11) NOT NULL,
  `rank` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ranks`
--

INSERT INTO `ranks` (`id`, `rank`) VALUES
(1, 'NUP'),
(2, 'PAT'),
(3, 'PCPL'),
(4, 'PSSG'),
(5, 'PMSG'),
(6, 'PSMS'),
(7, 'PCMS'),
(8, 'PEMS'),
(9, 'PLT'),
(10, 'PCPT'),
(11, 'PMAJ'),
(12, 'PLTCOL'),
(13, 'PCOL'),
(14, 'PBGEN');

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
  `last_update_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `routers`
--

INSERT INTO `routers` (`id`, `personnel_id`, `device_id`, `manufacturer`, `model`, `serial_no`, `no_of_ports`, `no_of_active_ports`, `active_port_ip_address_range`, `firmware_version`, `location`, `is_active`, `is_remotely_accessible`, `remote_connection_details`, `remarks`, `pnp_focal_person`, `contact_details`, `acquisition_date`, `acquisition_type`, `previous_owners_id`, `created_date`, `last_update_at`) VALUES
(4, 1, 101, 'Cisco', 'ISR 4321', 'RTR-001-ABC', 8, 6, '192.168.1.1-192.168.1.254', '16.09.05', 'Server Room A', 1, 1, 'SSH enabled on port 22', 'Main router for ITSD', 'John Doe', 2147483647, '2024-01-10', 'Purchase', '[1,2]', '2026-05-19', NULL),
(5, 1, 102, 'Huawei', 'AR2220', 'RTR-002-XYZ', 12, 10, '10.0.0.1-10.0.0.254', 'V200R010C00', 'Building B', 1, 1, 'Web UI access enabled', 'Backup router', 'Jane Smith', 2147483647, '2023-11-05', 'Donation', '[3]', '2026-05-19', NULL),
(6, 4, 103, 'Mikrotik', 'RB3011', 'RTR-003-DEF', 10, 8, '172.16.0.1-172.16.0.254', '6.49.10', 'Data Center', 0, 0, 'Disabled for security upgrade', 'Under maintenance', 'Mark Lee', 2147483647, '2022-08-15', 'Purchase', '[2,4]', '2026-05-19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `switches`
--

CREATE TABLE `switches` (
  `id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
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
  `contact_details` int(11) NOT NULL,
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

INSERT INTO `switches` (`id`, `personnel_id`, `device_id`, `manufacturer`, `model`, `serial_no`, `no_of_ports`, `no_of_active_ports`, `no_of_managed`, `no_of_unmanaged`, `firmware_version`, `is_vlan_supported`, `location`, `is_status`, `is_active`, `is_remote_access`, `remote_connection_details`, `remarks`, `pnp_focal_person`, `contact_details`, `acquisition_date`, `acquisition_type`, `acquisition_details`, `previous_owners_id`, `created_date`, `last_update_at`) VALUES
(1, 1, 1, 'Cisco', 'SG350-28', 'SW-1001', 28, 24, 20, 8, '1.0.0', 1, 'Main Office', 1, 1, 1, 'SSH Enabled', 'Good Condition', 'Juan Dela Cruz', 2147483647, '2025-01-10', 'Purchased', 'Government Procurement', '[1,2]', '2026-05-19', NULL),
(2, 4, 1, 'TP-Link', 'TL-SG1024', 'SW-1002', 24, 20, 12, 12, '2.5.1', 1, 'Server Room', 1, 1, 0, 'N/A', 'Operational', 'Maria Santos', 2147483647, '2025-02-15', 'Donated', 'LGU Donation', '[3]', '2026-05-19', NULL),
(3, 1, 1, 'Netgear', 'GS308', 'SW-1003', 8, 5, 0, 8, '3.0.2', 0, 'Training Room', 0, 0, 0, 'N/A', 'Needs Maintenance', 'Pedro Reyes', 2147483647, '2025-03-20', 'Purchased', 'Direct Supplier', '[]', '2026-05-19', NULL),
(4, 4, 1, 'D-Link', 'DGS-1210', 'SW-1004', 16, 15, 10, 6, '4.1.0', 1, 'Data Center', 1, 1, 1, 'Web GUI Enabled', 'Excellent', 'Ana Cruz', 2147483647, '2025-04-01', 'Purchased', 'IT Procurement', '[2,4]', '2026-05-19', NULL);

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
(1, 1, 8, 'itsd.superadmin@itms.com', '$2y$10$81t/AvPPOV5wT2fweoiJPes7QOpyuTekr1i8rz/dSL3QkMXlq3ww.', 14, 'Brandon', 'Jake', 'Fernandez Diaz', 'superadmin', 1, 1, '2026-05-12', NULL),
(10, 2, 1, 'admin.itsd@itms.com', '$2y$10$umcGPtWn6lZn5XbBXm0tSupmRqk.YtNvmJuOFMkI/hdgxj8XUOH3K', 2, 'admin', 'admin', 'admin', 'admin,AA', 1, 0, '2026-05-12', NULL),
(11, 2, 1, 'itsd.admin@itms.com', '$2y$10$LdO/uqleUh3/p.32f1qePeZJE1G7TKs0pgskhg9bUDPiOSnfxX.Ri', 8, 'Paul', 'Kenneth', 'De Guzman Agripa', 'Agripa,PD', 1, 0, '2026-05-12', NULL),
(12, 3, 1, 'itsd.encoder@itms.com', '$2y$10$6ou2A0ieaQOIXEfEqRhBH.XXb3zZeKLTqWyfJJstCzWLUdTsX7bLO', 13, 'Brandon', 'Jake', 'Pogi Lang', 'Lang,BP', 1, 0, '2026-05-12', NULL),
(13, 2, 3, 'issd.admin@itms.com', '$2y$10$cHihMBlxeL/ljgmMGQxGieWWY3Gq8gFYqF2lqwueBapi45Or87zBW', 12, 'Ken', 'Zake', 'Fabian', 'Fabian,KZ', 1, 0, '2026-05-12', NULL),
(14, 2, 2, 'smd.admin@itms.com', '$2y$10$yiXwHc/gdkZlornq1HUIfu5aEnGjEkIG73c8XXvdZ.TtSKQBhdTiC', 1, 'Princess', 'Cruz', 'Santa Mesa', 'Santa Mesa,PC', 1, 0, '2026-05-12', NULL),
(15, 2, 9, 'ci.admin@itms.com', '$2y$10$SVfdCN4qRe1BvlVIXObHtegsb8VrnU9QnPUTZrnC8duTzhbVmOg4u', 2, 'Alejandro Jay', 'Fernandez', 'Diaz', 'diazaf', 1, 1, '2026-05-12', NULL),
(16, 2, 14, 'sao.admin@itms.com', '$2y$10$Ur9ukvl2a9Fme9NU.Vd5ku0.TYxRO3Ya.FVTCGHjZcyFRc0RBVh2a', 13, 'Balmond', 'Valentina', 'Fanny', 'fannybv', 1, 1, '2026-05-12', NULL),
(17, 3, 6, 'dmd.admin@itms.com', '$2y$10$k.edIMBh1YhoY1OLpLqz2.ZnegDNafM5MVo9PbECOv3L5evPjN8.S', 3, 'ALUCARD', 'SELENA', 'GOMEZ', 'gomezas', 1, 1, '2026-05-12', NULL),
(18, 3, 15, 'ptdlab.admin@itms.com', '$2y$10$g8FRMAFXTDzK5KZtgbpLFOAruvQlPErL8Jed2qZbS33NJWbRFOAVG', 13, 'LAYLA', 'LESLY', 'VALENTINA', 'valentinall', 1, 1, '2026-05-12', NULL),
(19, 2, 9, 'pems.admin@itms.com', '$2y$10$zJTvR/CuQ.Imk.liQkXg8eASz4cZnM3jEFxefpYipQK907Q/6rFHC', 8, 'JAKE', 'FERNAN', 'DEZ', 'dezjf', 1, 1, '2026-05-19', NULL);

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_code` (`device_code`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `desktops`
--
ALTER TABLE `desktops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `headsets`
--
ALTER TABLE `headsets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `laptops`
--
ALTER TABLE `laptops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `personnels`
--
ALTER TABLE `personnels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `printers`
--
ALTER TABLE `printers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ranks`
--
ALTER TABLE `ranks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `routers`
--
ALTER TABLE `routers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `switches`
--
ALTER TABLE `switches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
