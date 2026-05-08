-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 08, 2026 at 03:14 AM
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
-- Table structure for table `antiviruses`
--

CREATE TABLE `antiviruses` (
  `id` int(11) NOT NULL,
  `antivirus` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `antiviruses`
--

INSERT INTO `antiviruses` (`id`, `antivirus`) VALUES
(1, 'Trendmicro'),
(2, 'Sophos'),
(3, 'Cybereason'),
(4, 'Bitdefender'),
(5, 'UTMStack'),
(6, 'Qualys'),
(7, 'Others');

-- --------------------------------------------------------

--
-- Table structure for table `desktops`
--

CREATE TABLE `desktops` (
  `id` int(11) NOT NULL,
  `device_code` varchar(100) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `device_name` varchar(150) NOT NULL,
  `division_id` int(11) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `os` varchar(100) NOT NULL,
  `is_os_licensed` tinyint(1) NOT NULL,
  `is_remote_acc` tinyint(1) NOT NULL,
  `antivirus_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
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
  `last_updated_at` date DEFAULT NULL
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
(14, 'SAO');

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
-- Table structure for table `laptops`
--

CREATE TABLE `laptops` (
  `id` int(11) NOT NULL,
  `device_code` varchar(100) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `device_name` varchar(150) NOT NULL,
  `division_id` int(11) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `os` varchar(100) NOT NULL,
  `is_os_licensed` tinyint(1) NOT NULL,
  `is_remote_acc` tinyint(1) NOT NULL,
  `antivirus_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
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
  `last_updated_at` date DEFAULT NULL
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
  `encoded_by_user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `printers`
--

CREATE TABLE `printers` (
  `id` int(11) NOT NULL,
  `device_code` varchar(255) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `acquisition_date` date NOT NULL,
  `acquisition_details` text NOT NULL,
  `brand` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `device_code` varchar(255) NOT NULL,
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
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `switches`
--

CREATE TABLE `switches` (
  `id` int(11) NOT NULL,
  `device_code` varchar(255) NOT NULL,
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
  `is_remote_access` tinyint(1) NOT NULL,
  `remote_connection_details` text NOT NULL,
  `remarks` text NOT NULL,
  `pnp_focal_person` varchar(255) NOT NULL,
  `contact_details` int(11) NOT NULL,
  `acquisition_date` date NOT NULL,
  `acquisition_type` varchar(255) NOT NULL,
  `acquisition_details` text NOT NULL,
  `previous_owners_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_owners_id`))
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
  `creator_user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `division_id`, `email`, `password`, `rank_id`, `first_name`, `middle_name`, `last_name`, `username`, `is_active`, `creator_user_id`) VALUES
(1, 1, 3, 'itsd.superadmin@itms.com', '$2y$10$81t/AvPPOV5wT2fweoiJPes7QOpyuTekr1i8rz/dSL3QkMXlq3ww.', 1, 'Super', 'Admin', 'Superadmin', 'superadmin', 1, 1),
(10, 2, 1, 'admin.itsd@itms.com', '$2y$10$umcGPtWn6lZn5XbBXm0tSupmRqk.YtNvmJuOFMkI/hdgxj8XUOH3K', 0, 'admin', 'admin', 'admin', 'admin,AA', 1, 0),
(11, 2, 1, 'itsd.admin@itms.com', '$2y$10$LdO/uqleUh3/p.32f1qePeZJE1G7TKs0pgskhg9bUDPiOSnfxX.Ri', 0, 'Paul Kenneth', 'De Guzman', 'Agripa', 'Agripa,PD', 1, 0),
(12, 3, 1, 'itsd.encoder@itms.com', '$2y$10$6ou2A0ieaQOIXEfEqRhBH.XXb3zZeKLTqWyfJJstCzWLUdTsX7bLO', 0, 'Brandon Jake', 'Pogi', 'Lang', 'Lang,BP', 1, 0),
(13, 2, 3, 'issd.admin@itms.com', '$2y$10$cHihMBlxeL/ljgmMGQxGieWWY3Gq8gFYqF2lqwueBapi45Or87zBW', 0, 'Ken', 'Zake', 'Fabian', 'Fabian,KZ', 1, 0),
(14, 2, 2, 'smd.admin@itms.com', '$2y$10$yiXwHc/gdkZlornq1HUIfu5aEnGjEkIG73c8XXvdZ.TtSKQBhdTiC', 0, 'Princess', 'Cruz', 'Santa Mesa', 'Santa Mesa,PC', 1, 0),
(15, 2, 9, 'ci.admin@itms.com', '$2y$10$SVfdCN4qRe1BvlVIXObHtegsb8VrnU9QnPUTZrnC8duTzhbVmOg4u', 2, 'Alejandro Jay', 'Fernandez', 'Diaz', 'diazaf', 1, 1),
(16, 2, 14, 'sao.admin@itms.com', '$2y$10$Ur9ukvl2a9Fme9NU.Vd5ku0.TYxRO3Ya.FVTCGHjZcyFRc0RBVh2a', 14, 'Balmond', 'Valentina', 'Fanny', 'fannybv', 1, 1),
(17, 2, 6, 'dmd.admin@itms.com', '$2y$10$k.edIMBh1YhoY1OLpLqz2.ZnegDNafM5MVo9PbECOv3L5evPjN8.S', 3, 'ALUCARD', 'SELENA', 'GOMEZ', 'gomezas', 1, 1),
(18, 2, 8, 'ptdlab.admin@itms.com', '$2y$10$g8FRMAFXTDzK5KZtgbpLFOAruvQlPErL8Jed2qZbS33NJWbRFOAVG', 13, 'LAYLA', 'LESLY', 'VALENTINA', 'valentinall', 1, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `antiviruses`
--
ALTER TABLE `antiviruses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `desktops`
--
ALTER TABLE `desktops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_code` (`device_code`);

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
-- Indexes for table `firewalls`
--
ALTER TABLE `firewalls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_code` (`device_code`);

--
-- Indexes for table `laptops`
--
ALTER TABLE `laptops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_code` (`device_code`);

--
-- Indexes for table `personnels`
--
ALTER TABLE `personnels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `printers`
--
ALTER TABLE `printers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_code` (`device_code`);

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_code` (`device_code`);

--
-- Indexes for table `switches`
--
ALTER TABLE `switches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_code` (`device_code`);

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
-- AUTO_INCREMENT for table `antiviruses`
--
ALTER TABLE `antiviruses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `firewalls`
--
ALTER TABLE `firewalls`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
