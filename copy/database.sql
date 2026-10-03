-- MyDrive Document Management System Database
-- Enhanced version with improved functionality
-- Compatible with MariaDB/MySQL

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Create database
CREATE DATABASE IF NOT EXISTS `myd_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `myd_db`;

-- --------------------------------------------------------
-- Table structure for table `departments`
-- --------------------------------------------------------

CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_code` varchar(10) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_of_department` varchar(100) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_code` (`department_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `fk_user_department` (`department_id`),
  KEY `idx_email_verified` (`email_verified`),
  KEY `idx_is_approved` (`is_approved`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `folders`
-- --------------------------------------------------------

CREATE TABLE `folders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folder_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parent_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `folder_path` varchar(500) DEFAULT NULL,
  `folder_level` int(11) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 0,
  `permissions` json DEFAULT NULL,
  `folder_size` bigint(20) DEFAULT 0,
  `file_count` int(11) DEFAULT 0,
  `folder_color` varchar(7) DEFAULT '#667eea',
  `folder_icon` varchar(50) DEFAULT 'fa-folder',
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `parent_id` (`parent_id`),
  KEY `department_id` (`department_id`),
  KEY `idx_is_deleted` (`is_deleted`),
  KEY `idx_folder_path` (`folder_path`),
  FULLTEXT KEY `folder_search` (`folder_name`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `files`
-- --------------------------------------------------------

CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_extension` varchar(10) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `last_downloaded` datetime DEFAULT NULL,
  `last_downloaded_by` int(11) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `public_token` varchar(255) DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  `parent_file_id` int(11) DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `description` text DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `is_favorite` tinyint(1) DEFAULT 0,
  `expiry_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `folder_id` (`folder_id`),
  KEY `parent_file_id` (`parent_file_id`),
  KEY `idx_is_deleted` (`is_deleted`),
  KEY `idx_file_type` (`file_type`),
  KEY `idx_public_token` (`public_token`),
  KEY `idx_file_hash` (`file_hash`),
  FULLTEXT KEY `file_search` (`original_name`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `announcements`
-- --------------------------------------------------------

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `announcement_type` enum('general','department','urgent','maintenance') DEFAULT 'general',
  `target_departments` json DEFAULT NULL,
  `target_roles` json DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `is_pinned` tinyint(1) DEFAULT 0,
  `allow_comments` tinyint(1) DEFAULT 1,
  `send_email` tinyint(1) DEFAULT 0,
  `email_sent` tinyint(1) DEFAULT 0,
  `email_sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_is_deleted` (`is_deleted`),
  KEY `idx_priority` (`priority`),
  KEY `idx_expires_at` (`expires_at`),
  FULLTEXT KEY `announcement_search` (`title`, `content`, `summary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `announcement_views`
-- --------------------------------------------------------

CREATE TABLE `announcement_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `viewed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_view` (`announcement_id`, `user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `file_shares`
-- --------------------------------------------------------

CREATE TABLE `file_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `shared_with` int(11) DEFAULT NULL,
  `share_token` varchar(255) NOT NULL,
  `share_type` enum('user','public','department') DEFAULT 'user',
  `permissions` json DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `password_protected` tinyint(1) DEFAULT 0,
  `share_password` varchar(255) DEFAULT NULL,
  `download_limit` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_accessed` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_token` (`share_token`),
  KEY `file_id` (`file_id`),
  KEY `shared_by` (`shared_by`),
  KEY `shared_with` (`shared_with`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `folder_permissions`
-- --------------------------------------------------------

CREATE TABLE `folder_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folder_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `permission_type` enum('read','write','admin') DEFAULT 'read',
  `granted_by` int(11) NOT NULL,
  `granted_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `folder_id` (`folder_id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `granted_by` (`granted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `activity_logs`
-- --------------------------------------------------------

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource_type` enum('file','folder','user','announcement','department','system') NOT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_resource` (`resource_type`, `resource_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `file_comments`
-- --------------------------------------------------------

CREATE TABLE `file_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_comment_id` (`parent_comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `system_settings`
-- --------------------------------------------------------

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `action_url` varchar(500) DEFAULT NULL,
  `action_text` varchar(100) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Add Foreign Key Constraints
-- --------------------------------------------------------

ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_department_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `folders_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `files_parent_fk` FOREIGN KEY (`parent_file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `files_downloaded_by_fk` FOREIGN KEY (`last_downloaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `announcements_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `announcement_views`
  ADD CONSTRAINT `announcement_views_announcement_fk` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_views_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `file_shares`
  ADD CONSTRAINT `file_shares_file_fk` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_shares_shared_by_fk` FOREIGN KEY (`shared_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_shares_shared_with_fk` FOREIGN KEY (`shared_with`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `folder_permissions`
  ADD CONSTRAINT `folder_permissions_folder_fk` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_permissions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_permissions_department_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_permissions_granted_by_fk` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `file_comments`
  ADD CONSTRAINT `file_comments_file_fk` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_comments_parent_fk` FOREIGN KEY (`parent_comment_id`) REFERENCES `file_comments` (`id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------
-- Insert Sample Data
-- --------------------------------------------------------

-- Insert Departments
INSERT INTO `departments` (`id`, `department_code`, `department_name`, `description`, `head_of_department`, `contact_email`, `contact_phone`, `is_active`) VALUES
(1, 'TED', 'Teacher Education Department', 'Responsible for teacher training and education programs', 'Dr. Maria Santos', 'ted@cvsu.edu.ph', '+63-2-1234-5671', 1),
(2, 'MD', 'Management Department', 'Business administration and management programs', 'Prof. Juan Dela Cruz', 'md@cvsu.edu.ph', '+63-2-1234-5672', 1),
(3, 'ITD', 'Information Technology Department', 'Computer science and information technology programs', 'Dr. Robert Garcia', 'itd@cvsu.edu.ph', '+63-2-1234-5673', 1),
(4, 'FASD', 'Fisheries and Aquatic Science Department', 'Marine biology and fisheries management programs', 'Dr. Ana Reyes', 'fasd@cvsu.edu.ph', '+63-2-1234-5674', 1),
(5, 'ASD', 'Arts and Science Department', 'Liberal arts and natural sciences programs', 'Prof. Carlos Mendoza', 'asd@cvsu.edu.ph', '+63-2-1234-5675', 1);

-- Insert Default Admin User
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_approved`, `name`, `mi`, `surname`, `employee_id`, `position`, `department_id`, `is_restricted`, `email_verified`, `created_at`) VALUES
(1, 'superadmin', 'admin@cvsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1, 'System', '', 'Administrator', 'ADMIN001', 'System Administrator', NULL, 0, 1, NOW());

-- Insert ITD Admin
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_approved`, `name`, `mi`, `surname`, `employee_id`, `position`, `department_id`, `is_restricted`, `email_verified`, `created_at`, `approved_by`, `approved_at`) VALUES
(27, 'itdadmin', 'itdadmin@cvsu.edu.ph', '$2y$10$bpEIbndhHjmVawTlc/brAO8q.o9the5fYlV5XHXMZ3AfqX43xwWw.', 'admin', 1, 'ITD', '', 'Administrator', 'ITD001', 'Department Administrator', 3, 0, 1, NOW(), 1, NOW());

-- Insert Sample Users
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_approved`, `name`, `mi`, `surname`, `employee_id`, `position`, `department_id`, `is_restricted`, `email_verified`, `created_at`, `approved_by`, `approved_at`) VALUES
(28, 'hbalanza', 'henry.balanza@cvsu.edu.ph', '$2y$10$bpEIbndhHjmVawTlc/brAO8q.o9the5fYlV5XHXMZ3AfqX43xwWw.', 'user', 1, 'Henry', 'R', 'Balanza', 'ITD002', 'Assistant Professor', 3, 0, 1, NOW(), 27, NOW()),
(29, 'mtimola', 'luigi.timola@cvsu.edu.ph', '$2y$10$IrcJneb3t//.O370AcJ75.N20qreX2nHkYp3eWnetOLgSHQ62txMq', 'user', 1, 'Marc Luigi', 'G', 'Timola', 'ITD003', 'Associate Professor', 3, 0, 1, NOW(), 27, NOW()),
(30, 'rriel', 'rj.riel@cvsu.edu.ph', '$2y$10$CTAcrRPYboonzGIiDpw8S.thwJ7ueM0nkVN4XdQo4vNXAmt6aXnYC', 'user', 1, 'Ricky Jay', 'A', 'Riel', 'ITD004', 'Instructor', 3, 0, 1, NOW(), 27, NOW()),
(31, 'pending_user', 'pending@cvsu.edu.ph', '$2y$10$9ldd1yiEVKJAyXubZ.GLc.ZFkMjkP.j4XqL7VRdBbAR8k2aKXKEI.', 'user', 0, 'John', 'A', 'Doe', 'ITD005', 'Instructor', 3, 0, 0, NOW(), NULL, NULL);

-- Insert Sample Folders
INSERT INTO `folders` (`id`, `folder_name`, `description`, `created_by`, `created_at`, `parent_id`, `is_deleted`, `department_id`, `folder_path`, `folder_level`, `is_public`, `folder_color`, `folder_icon`) VALUES
(44, 'IPCR', 'Individual Performance Commitment and Review documents', 27, NOW(), NULL, 0, 3, '/IPCR', 0, 0, '#667eea', 'fa-chart-line'),
(45, 'Minutes', 'Meeting minutes and proceedings', 27, NOW(), NULL, 0, 3, '/Minutes', 0, 0, '#38a169', 'fa-file-alt'),
(46, 'DTR', 'Daily Time Records', 27, NOW(), NULL, 0, 3, '/DTR', 0, 0, '#ed8936', 'fa-clock'),
(47, 'Attendance', 'Student and faculty attendance records', 27, NOW(), NULL, 0, 3, '/Attendance', 0, 0, '#9f7aea', 'fa-users'),
(48, 'Syllabus', 'Course syllabi and curriculum documents', 27, NOW(), NULL, 0, 3, '/Syllabus', 0, 1, '#4299e1', 'fa-book'),
(49, 'Lecture Notes', 'Teaching materials and lecture notes', 27, NOW(), NULL, 0, 3, '/Lecture Notes', 0, 1, '#48bb78', 'fa-graduation-cap'),
(50, 'Grading Sheets', 'Student grades and assessment records', 27, NOW(), NULL, 0, 3, '/Grading Sheets', 0, 0, '#e53e3e', 'fa-table'),
(51, 'Exams', 'Examination papers and answer keys', 27, NOW(), NULL, 0, 3, '/Exams', 0, 0, '#d69e2e', 'fa-file-signature'),
(52, 'Other Course Materials', 'Additional teaching and learning resources', 27, NOW(), NULL, 0, 3, '/Other Course Materials', 0, 1, '#805ad5', 'fa-folder-open');

-- Insert Sample Files
INSERT INTO `files` (`id`, `file_name`, `original_name`, `file_path`, `file_size`, `file_type`, `mime_type`, `file_extension`, `uploaded_by`, `folder_id`, `uploaded_at`, `is_deleted`, `file_hash`, `description`) VALUES
(18, '1719775504_summary_form.docx', 'NAIC_QF_xxxx_Summary-of-Comments-and-Action-Taken-Form.docx', 'uploads/1719775504_summary_form.docx', 45678, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 28, 47, '2025-06-30 23:25:04', 0, 'abc123def456', 'Quality assurance summary form'),
(19, '1719775992_users_backup.sql', 'users.sql', 'uploads/1719775992_users_backup.sql', 8945, 'database', 'application/sql', 'sql', 29, 47, '2025-06-30 23:33:12', 0, 'def456ghi789', 'Database backup file'),
(20, '1719832532_contribution_doc.docx', 'WITH CONTRIBUTION NUMBER.docx', 'uploads/1719832532_contribution_doc.docx', 234567, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 30, 51, '2025-07-01 11:55:32', 0, 'ghi789jkl012', 'Research contribution document'),
(21, '1719832561_admin_dashboard.php', 'admindashboard.php', 'uploads/1719832561_admin_dashboard.php', 15678, 'code', 'application/x-php', 'php', 30, 52, '2025-07-01 11:56:01', 0, 'jkl012mno345', 'Admin dashboard source code'),
(22, '1719832585_authorization_letter.docx', 'AUTHORIZATION LETTER.docx', 'uploads/1719832585_authorization_letter.docx', 67890, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 28, 46, '2025-07-01 11:56:25', 0, 'mno345pqr678', 'Official authorization letter');


-- Insert System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('site_name', 'MyDrive - CVSU Naic', 'string', 'Application name displayed in header', 1),
('site_description', 'Document Management System for CVSU Naic', 'string', 'Application description', 1),
('max_file_size', '104857600', 'integer', 'Maximum file upload size in bytes (100MB)', 0),
('allowed_file_types', '["pdf","doc","docx","xls","xlsx","ppt","pptx","txt","jpg","jpeg","png","gif","sql","php","js","html","css","zip","rar"]', 'json', 'Allowed file extensions for upload', 0),
('email_notifications', 'true', 'boolean', 'Enable email notifications', 0),
('user_registration', 'true', 'boolean', 'Allow new user registration', 0),
('auto_approve_users', 'false', 'boolean', 'Automatically approve new users', 0),
('session_timeout', '3600', 'integer', 'Session timeout in seconds', 0),
('backup_retention_days', '30', 'integer', 'Number of days to retain backups', 0),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode', 1),
('default_folder_permissions', '{"read": true, "write": false, "admin": false}', 'json', 'Default permissions for new folders', 0),
('password_policy', '{"min_length": 8, "require_uppercase": true, "require_lowercase": true, "require_numbers": true, "require_symbols": false}', 'json', 'Password complexity requirements', 0);

-- Insert Sample Activity Logs
INSERT INTO `activity_logs` (`user_id`, `action`, `resource_type`, `resource_id`, `description`, `metadata`, `ip_address`, `created_at`) VALUES
(28, 'upload_file', 'file', 18, 'Uploaded file: NAIC_QF_xxxx_Summary-of-Comments-and-Action-Taken-Form.docx', '{"file_size": 45678, "folder_name": "Attendance"}', '192.168.1.100', '2025-06-30 23:25:04'),
(29, 'upload_file', 'file', 19, 'Uploaded file: users.sql', '{"file_size": 8945, "folder_name": "Attendance"}', '192.168.1.101', '2025-06-30 23:33:12'),
(30, 'upload_file', 'file', 20, 'Uploaded file: WITH CONTRIBUTION NUMBER.docx', '{"file_size": 234567, "folder_name": "Exams"}', '192.168.1.102', '2025-07-01 11:55:32'),
(30, 'upload_file', 'file', 21, 'Uploaded file: admindashboard.php', '{"file_size": 15678, "folder_name": "Other Course Materials"}', '192.168.1.102', '2025-07-01 11:56:01'),
(28, 'upload_file', 'file', 22, 'Uploaded file: AUTHORIZATION LETTER.docx', '{"file_size": 67890, "folder_name": "DTR"}', '192.168.1.100', '2025-07-01 11:56:25'),
(27, 'create_announcement', 'announcement', 2, 'Created announcement: ITD Day Celebration 2025', '{"priority": "high", "type": "department"}', '192.168.1.50', '2025-06-13 08:59:03'),
(27, 'create_announcement', 'announcement', 3, 'Created announcement: Happy Birthday Francene!', '{"priority": "normal", "type": "department"}', '192.168.1.50', '2025-06-13 09:06:00'),
(27, 'approve_user', 'user', 28, 'Approved user: Henry R Balanza', '{"department": "ITD", "role": "user"}', '192.168.1.50', '2025-06-30 10:15:30'),
(27, 'approve_user', 'user', 29, 'Approved user: Marc Luigi G Timola', '{"department": "ITD", "role": "user"}', '192.168.1.50', '2025-06-30 10:16:45'),
(27, 'approve_user', 'user', 30, 'Approved user: Ricky Jay A Riel', '{"department": "ITD", "role": "user"}', '192.168.1.50', '2025-06-30 10:17:22');

-- Insert Sample Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `action_url`, `action_text`, `is_read`, `created_at`) VALUES
(28, 'File Upload Successful', 'Your file "AUTHORIZATION LETTER.docx" has been uploaded successfully to DTR folder.', 'success', '/files/view/22', 'View File', 1, '2025-07-01 11:56:25'),
(29, 'Welcome to MyDrive', 'Your account has been approved. You can now access all features of the document management system.', 'success', '/dashboard', 'Go to Dashboard', 1, '2025-06-30 10:16:45'),
(30, 'Account Approved', 'Your MyDrive account has been approved by the administrator. Welcome to the system!', 'success', '/profile', 'Complete Profile', 0, '2025-06-30 10:17:22'),
(31, 'Account Pending', 'Your account registration is pending approval. You will receive a notification once approved.', 'info', NULL, NULL, 0, NOW()),
(28, 'New Announcement', 'A new announcement "ITD Day Celebration 2025" has been posted.', 'info', '/announcements/view/2', 'Read More', 0, '2025-06-13 08:59:03');

-- Insert Sample File Shares
INSERT INTO `file_shares` (`file_id`, `shared_by`, `shared_with`, `share_token`, `share_type`, `permissions`, `expires_at`, `download_limit`, `is_active`, `created_at`) VALUES
(18, 28, 29, 'share_abc123def456', 'user', '{"read": true, "download": true}', '2025-12-31 23:59:59', 10, 1, NOW()),
(20, 30, NULL, 'public_ghi789jkl012', 'public', '{"read": true, "download": false}', '2025-08-31 23:59:59', NULL, 1, NOW());

-- Insert Sample Folder Permissions
INSERT INTO `folder_permissions` (`folder_id`, `user_id`, `permission_type`, `granted_by`, `granted_at`, `is_active`) VALUES
(48, 28, 'write', 27, NOW(), 1),
(49, 28, 'write', 27, NOW(), 1),
(48, 29, 'read', 27, NOW(), 1),
(49, 29, 'write', 27, NOW(), 1),
(50, 30, 'read', 27, NOW(), 1),
(51, 30, 'write', 27, NOW(), 1);

-- Insert Sample File Comments
INSERT INTO `file_comments` (`file_id`, `user_id`, `comment`, `created_at`) VALUES
(18, 29, 'This document looks comprehensive. Good work on the formatting.', NOW()),
(20, 28, 'Please review the contribution numbers in section 3 before final submission.', NOW()),
(22, 27, 'Authorization letter approved. Please proceed with the next steps.', NOW());

-- --------------------------------------------------------
-- Create Useful Views
-- --------------------------------------------------------

-- View for user details with department information
CREATE VIEW `v_users_detailed` AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    u.is_approved,
    u.name,
    u.mi,
    u.surname,
    CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) AS full_name,
    u.employee_id,
    u.position,
    u.is_restricted,
    u.last_login,
    u.created_at,
    d.department_code,
    d.department_name,
    approver.username AS approved_by_username,
    u.approved_at
FROM users u
LEFT JOIN departments d ON u.department_id = d.id
LEFT JOIN users approver ON u.approved_by = approver.id;

-- View for files with folder and user information
CREATE VIEW `v_files_detailed` AS
SELECT 
    f.id,
    f.original_name,
    f.file_name,
    f.file_size,
    f.file_type,
    f.mime_type,
    f.file_extension,
    f.uploaded_at,
    f.download_count,
    f.is_deleted,
    folder.folder_name,
    folder.folder_path,
    uploader.username AS uploaded_by_username,
    CONCAT(uploader.name, ' ', IFNULL(CONCAT(uploader.mi, '. '), ''), uploader.surname) AS uploader_full_name,
    dept.department_code AS folder_department
FROM files f
LEFT JOIN folders folder ON f.folder_id = folder.id
LEFT JOIN users uploader ON f.uploaded_by = uploader.id
LEFT JOIN departments dept ON folder.department_id = dept.id;

-- View for announcements with creator information
CREATE VIEW `v_announcements_detailed` AS
SELECT 
    a.id,
    a.title,
    a.content,
    a.summary,
    a.image_path,
    a.priority,
    a.announcement_type,
    a.is_published,
    a.published_at,
    a.expires_at,
    a.view_count,
    a.is_pinned,
    a.created_at,
    creator.username AS created_by_username,
    CONCAT(creator.name, ' ', IFNULL(CONCAT(creator.mi, '. '), ''), creator.surname) AS creator_full_name
FROM announcements a
LEFT JOIN users creator ON a.created_by = creator.id
WHERE a.is_deleted = 0;

-- View for folder hierarchy with permissions
CREATE VIEW `v_folders_hierarchy` AS
SELECT 
    f.id,
    f.folder_name,
    f.folder_path,
    f.folder_level,
    f.is_public,
    f.folder_color,
    f.folder_icon,
    f.file_count,
    f.folder_size,
    f.created_at,
    parent.folder_name AS parent_folder_name,
    creator.username AS created_by_username,
    CONCAT(creator.name, ' ', IFNULL(CONCAT(creator.mi, '. '), ''), creator.surname) AS creator_full_name,
    dept.department_code,
    dept.department_name
FROM folders f
LEFT JOIN folders parent ON f.parent_id = parent.id
LEFT JOIN users creator ON f.created_by = creator.id
LEFT JOIN departments dept ON f.department_id = dept.id
WHERE f.is_deleted = 0;

-- --------------------------------------------------------
-- Create Useful Stored Procedures
-- --------------------------------------------------------

DELIMITER //

-- Procedure to get folder statistics
CREATE PROCEDURE `sp_get_folder_stats`(IN folder_id INT)
BEGIN
    SELECT 
        COUNT(f.id) as file_count,
        COALESCE(SUM(f.file_size), 0) as total_size,
        COUNT(DISTINCT f.file_type) as file_types_count,
        MAX(f.uploaded_at) as last_upload
    FROM files f
    WHERE f.folder_id = folder_id AND f.is_deleted = 0;
END //

-- Procedure to get user activity summary
CREATE PROCEDURE `sp_get_user_activity`(IN user_id INT, IN days_back INT)
BEGIN
    SELECT 
        action,
        resource_type,
        COUNT(*) as action_count,
        MAX(created_at) as last_action
    FROM activity_logs
    WHERE user_id = user_id 
    AND created_at >= DATE_SUB(NOW(), INTERVAL days_back DAY)
    GROUP BY action, resource_type
    ORDER BY action_count DESC;
END //

-- Procedure to update folder statistics
CREATE PROCEDURE `sp_update_folder_stats`(IN folder_id INT)
BEGIN
    UPDATE folders f
    SET 
        file_count = (
            SELECT COUNT(*) 
            FROM files 
            WHERE folder_id = f.id AND is_deleted = 0
        ),
        folder_size = (
            SELECT COALESCE(SUM(file_size), 0) 
            FROM files 
            WHERE folder_id = f.id AND is_deleted = 0
        )
    WHERE f.id = folder_id;
END //

-- Procedure to clean up expired shares
CREATE PROCEDURE `sp_cleanup_expired_shares`()
BEGIN
    UPDATE file_shares 
    SET is_active = 0 
    WHERE expires_at < NOW() AND is_active = 1;
    
    SELECT ROW_COUNT() as expired_shares_count;
END //

-- Function to generate unique share token
CREATE FUNCTION `fn_generate_share_token`() 
RETURNS VARCHAR(255)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE token VARCHAR(255);
    DECLARE token_exists INT DEFAULT 1;
    
    WHILE token_exists > 0 DO
        SET token = CONCAT('share_', MD5(CONCAT(NOW(), RAND())));
        SELECT COUNT(*) INTO token_exists FROM file_shares WHERE share_token = token;
    END WHILE;
    
    RETURN token;
END //

DELIMITER ;

-- --------------------------------------------------------
-- Create Triggers
-- --------------------------------------------------------

DELIMITER //

-- Trigger to update folder statistics when file is added
CREATE TRIGGER `tr_file_insert_update_folder` 
AFTER INSERT ON `files`
FOR EACH ROW
BEGIN
    CALL sp_update_folder_stats(NEW.folder_id);
END //

-- Trigger to update folder statistics when file is deleted
CREATE TRIGGER `tr_file_update_folder_stats` 
AFTER UPDATE ON `files`
FOR EACH ROW
BEGIN
    IF OLD.is_deleted != NEW.is_deleted THEN
        CALL sp_update_folder_stats(NEW.folder_id);
    END IF;
END //

-- Trigger to log user activities
CREATE TRIGGER `tr_file_insert_log` 
AFTER INSERT ON `files`
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata)
    VALUES (NEW.uploaded_by, 'upload_file', 'file', NEW.id, 
            CONCAT('Uploaded file: ', NEW.original_name),
            JSON_OBJECT('file_size', NEW.file_size, 'file_type', NEW.file_type));
END //

-- Trigger to update announcement view count
CREATE TRIGGER `tr_announcement_view_insert` 
AFTER INSERT ON `announcement_views`
FOR EACH ROW
BEGIN
    UPDATE announcements 
    SET view_count = (
        SELECT COUNT(*) FROM announcement_views 
        WHERE announcement_id = NEW.announcement_id
    )
    WHERE id = NEW.announcement_id;
END //

DELIMITER ;

-- --------------------------------------------------------
-- Create Indexes for Performance
-- --------------------------------------------------------

-- Additional indexes for better query performance
CREATE INDEX `idx_files_uploaded_at` ON `files` (`uploaded_at`);
CREATE INDEX `idx_files_file_type_size` ON `files` (`file_type`, `file_size`);
CREATE INDEX `idx_folders_created_at` ON `folders` (`created_at`);
CREATE INDEX `idx_users_last_login` ON `users` (`last_login`);
CREATE INDEX `idx_activity_logs_user_action` ON `activity_logs` (`user_id`, `action`);
CREATE INDEX `idx_announcements_published` ON `announcements` (`is_published`, `published_at`);
CREATE INDEX `idx_file_shares_expires` ON `file_shares` (`expires_at`, `is_active`);

-- --------------------------------------------------------
-- Insert Auto-increment Values
-- --------------------------------------------------------

ALTER TABLE `announcements` AUTO_INCREMENT=7;
ALTER TABLE `departments` AUTO_INCREMENT=6;
ALTER TABLE `files` AUTO_INCREMENT=23;
ALTER TABLE `folders` AUTO_INCREMENT=53;
ALTER TABLE `users` AUTO_INCREMENT=32;
ALTER TABLE `activity_logs` AUTO_INCREMENT=1;
ALTER TABLE `notifications` AUTO_INCREMENT=1;
ALTER TABLE `file_shares` AUTO_INCREMENT=1;
ALTER TABLE `folder_permissions` AUTO_INCREMENT=1;
ALTER TABLE `file_comments` AUTO_INCREMENT=1;
ALTER TABLE `system_settings` AUTO_INCREMENT=1;
ALTER TABLE `announcement_views` AUTO_INCREMENT=1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------
-- End of MyDrive Enhanced Database Schema
-- --------------------------------------------------------
