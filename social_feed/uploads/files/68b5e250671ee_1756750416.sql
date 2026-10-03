-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 30, 2025 at 08:15 PM
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
-- Database: `myd_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user','super_admin') NOT NULL DEFAULT 'user',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL,
  `mi` varchar(5) DEFAULT NULL,
  `surname` varchar(100) NOT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `is_restricted` tinyint(1) DEFAULT 0,
  `profile_image` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `account_locked_until` datetime DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_approved`, `name`, `mi`, `surname`, `employee_id`, `position`, `department_id`, `is_restricted`, `profile_image`, `phone`, `address`, `date_of_birth`, `hire_date`, `last_login`, `failed_login_attempts`, `account_locked_until`, `email_verified`, `email_verification_token`, `password_reset_token`, `password_reset_expires`, `created_at`, `updated_at`, `created_by`, `approved_by`, `approved_at`) VALUES
(1, 'superadmin', 'admin@cvsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1, 'System', '', 'Administrator', 'ADMIN001', 'System Administrator', NULL, 0, 'uploads/profile_images/profile_1_1756380279.jpg', '', '', NULL, NULL, '2025-08-31 01:14:07', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-31 01:14:07', NULL, NULL, NULL),
(27, 'itdadmin', 'itdadmin@cvsu.edu.ph', '$2y$10$ZM.zHjOD1jFImsnyAJRwCeN2amu/f6YBl6yub49Y71fl0ViHLPtOO', 'admin', 1, 'ITD', '', 'Administrator', 'ITD001', 'Department Administrator', 3, 0, NULL, NULL, NULL, NULL, NULL, '2025-08-31 02:14:21', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-31 02:14:21', NULL, 1, '2025-08-07 01:55:15'),
(28, 'hbalanza', 'henry.balanza@cvsu.edu.ph', '$2y$10$flq.H6gNOJYOvWcNOVayzeC46wMftEjQdmJvyjGaiUdaLwjNWGfAe', 'user', 1, 'atip', 'R', 'Balanza', 'ITD002', 'Assistant Professor', 3, 0, 'uploads/profile_images/profile_28_1756533176.jpg', '', '', NULL, NULL, '2025-08-30 23:44:57', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-30 23:44:57', NULL, 27, '2025-08-07 01:55:15'),
(29, 'mtimola', 'luigi.timola@cvsu.edu.ph', '$2y$10$txws1CRC6Ssi7rEkC8UYounZPtSL4C2Wagbrw7nzs5BYvZG89ZM4m', 'user', 1, 'Marc Luigi', 'G', 'Timola', 'ITD003', 'Associate Professor', 3, 0, NULL, NULL, NULL, NULL, NULL, NULL, 6, '2025-08-28 13:51:38', 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-28 19:37:29', NULL, 27, '2025-08-07 01:55:15'),
(30, 'rriel', 'rj.riel@cvsu.edu.ph', '$2y$10$lm8KWDjBaewftbGNUy8rKOHM3u6qPNDZYE4/pZ040mS/XaYZIUHui', 'user', 1, 'Ricky Jay', 'A', 'Riel', 'ITD004', 'Instructor', 3, 0, 'uploads/profile_images/profile_30_1756577645.jpg', '', '', NULL, NULL, '2025-08-31 02:14:05', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-31 02:14:05', NULL, 27, '2025-08-07 01:55:15'),
(31, 'pending_user', 'pending@cvsu.edu.ph', '$2y$10$9ldd1yiEVKJAyXubZ.GLc.ZFkMjkP.j4XqL7VRdBbAR8k2aKXKEI.', 'user', 0, 'John', 'A', 'Doe', 'ITD005', 'Instructor', 3, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, NULL, NULL),
(32, 'asd', 'asd@gmail.com', '$2y$10$6YqvkgGXksgDvO5jsbBxv.ahCkx0vx4oyoTsKDqJoSOHKLgFxgnxO', 'user', 1, 'asd', 's', 'asd', 'ITD123', '123wsd', 3, 0, NULL, '9626091407', 'asdasdasd', '2003-12-09', NULL, '2025-08-07 15:50:29', 0, NULL, 0, '71e2fb099ead6dc85a091d2d06e754f5446a6a874357dca410d3e572a1496398', NULL, NULL, '2025-08-07 15:15:15', '2025-08-07 15:50:29', NULL, 1, '2025-08-07 15:16:00'),
(33, 'third', 'third@gmail.com', '$2y$10$JAAr9waMRp18p6sL6LYN9OKIjXo4RWMAPpKrEK9c/Kpd/Q4fdRckW', 'user', 1, 'third', 'P.', 'third', 'ASD1001', 'Instructor', 5, 0, NULL, '09385100460', 'third', '2007-06-13', NULL, '2025-08-25 09:02:03', 0, NULL, 0, '12b098c1665cd364c841513e1a9b5c7db3943b6a569f06658209347ff4a3b801', NULL, NULL, '2025-08-25 08:48:07', '2025-08-25 09:02:03', NULL, 1, '2025-08-25 09:01:47'),
(34, 'angelitodecatoria', 'angelitodecatoriaa@gmail.com', '$2y$10$w/DFKp2TuTYkUQ3MuSv4QehNGc.ihMzToP.vCtaJBd9fl63K/wgaC', 'user', 0, 'Angelito', NULL, 'Decatoria', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, '2025-08-28 18:41:38', '2025-08-28 18:41:38', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `fk_user_department` (`department_id`),
  ADD KEY `idx_email_verified` (`email_verified`),
  ADD KEY `idx_is_approved` (`is_approved`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `fk_user_created_by` (`created_by`),
  ADD KEY `fk_user_approved_by` (`approved_by`),
  ADD KEY `idx_users_last_login` (`last_login`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
