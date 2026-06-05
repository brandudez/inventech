-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 05, 2026 at 09:45 PM
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
  `is_remote_acc` tinyint(1) DEFAULT NULL,
  `endpoint_security_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `no_of_installed_anti_virus` int(11) DEFAULT NULL,
  `date_installed` date DEFAULT NULL,
  `guid` varchar(100) DEFAULT NULL,
  `mac_address` varchar(100) DEFAULT NULL,
  `cpu_brand` varchar(100) DEFAULT NULL,
  `cpu_cores` int(11) DEFAULT NULL,
  `gb_ram` int(11) DEFAULT NULL,
  `monitor_brand` varchar(100) DEFAULT NULL,
  `monitor_size_inches` int(11) DEFAULT NULL,
  `no_of_user_accounts` int(11) DEFAULT NULL,
  `user_account_type` varchar(100) DEFAULT NULL,
  `authorized_software` text DEFAULT NULL,
  `unauthorized_software` text DEFAULT NULL,
  `office_application` varchar(150) DEFAULT NULL,
  `is_office_licensed` tinyint(1) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date DEFAULT current_timestamp(),
  `last_updated_at` date DEFAULT NULL,
  `os_license_key` varchar(255) DEFAULT NULL,
  `office_license_key` varchar(255) DEFAULT NULL,
  `par_serial_no` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `acquisition_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `is_remote_acc` tinyint(1) DEFAULT 1,
  `endpoint_security_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `no_of_installed_anti_virus` int(11) DEFAULT NULL,
  `date_installed` date DEFAULT NULL,
  `guid` varchar(100) DEFAULT NULL,
  `mac_address` varchar(100) DEFAULT NULL,
  `cpu_brand` varchar(100) DEFAULT NULL,
  `cpu_cores` int(11) DEFAULT NULL,
  `gb_ram` int(11) DEFAULT NULL,
  `monitor_brand` varchar(100) DEFAULT NULL,
  `monitor_size_inches` int(11) DEFAULT NULL,
  `no_of_user_accounts` int(11) DEFAULT NULL,
  `user_account_type` varchar(100) DEFAULT NULL,
  `authorized_software` text DEFAULT NULL,
  `unauthorized_software` text DEFAULT NULL,
  `office_application` varchar(150) DEFAULT NULL,
  `is_office_licensed` tinyint(1) DEFAULT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`)),
  `created_date` date DEFAULT current_timestamp(),
  `last_updated_at` date DEFAULT NULL,
  `os_license_key` varchar(255) DEFAULT NULL,
  `office_license_key` varchar(255) DEFAULT NULL,
  `par_serial_no` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `acquisition_date` date DEFAULT NULL
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
(1, 1, 1, 'itsd.superadmin@itms.com', '$2y$10$aisUHgeqUkfpDVPYWE6KuOV4pzsD8twbv11LfwnUhpykBuh/Z9bXm', 1, 'ITSD', 'SUPER', 'ADMIN', 'superadmin', 1, 1, '2026-06-06', NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `desktops`
--
ALTER TABLE `desktops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laptops`
--
ALTER TABLE `laptops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personnels`
--
ALTER TABLE `personnels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `switches`
--
ALTER TABLE `switches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
