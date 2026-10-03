-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 07, 2025 at 04:15 AM
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

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_expired_shares` ()   BEGIN
    UPDATE file_shares 
    SET is_active = 0 
    WHERE expires_at < NOW() AND is_active = 1;
    
    SELECT ROW_COUNT() as expired_shares_count;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_folder_stats` (IN `folder_id` INT)   BEGIN
    SELECT 
        COUNT(f.id) as file_count,
        COALESCE(SUM(f.file_size), 0) as total_size,
        COUNT(DISTINCT f.file_type) as file_types_count,
        MAX(f.uploaded_at) as last_upload
    FROM files f
    WHERE f.folder_id = folder_id AND f.is_deleted = 0;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_user_activity` (IN `user_id` INT, IN `days_back` INT)   BEGIN
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_folder_stats` (IN `folder_id` INT)   BEGIN
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
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_generate_share_token` () RETURNS VARCHAR(255) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE token VARCHAR(255);
    DECLARE token_exists INT DEFAULT 1;
    
    WHILE token_exists > 0 DO
        SET token = CONCAT('share_', MD5(CONCAT(NOW(), RAND())));
        SELECT COUNT(*) INTO token_exists FROM file_shares WHERE share_token = token;
    END WHILE;
    
    RETURN token;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource_type` enum('file','folder','user','announcement','department','system') NOT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `resource_type`, `resource_id`, `description`, `metadata`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 28, 'upload_file', 'file', 18, 'Uploaded file: NAIC_QF_xxxx_Summary-of-Comments-and-Action-Taken-Form.docx', '{\"file_size\": 45678, \"folder_name\": \"Attendance\"}', '192.168.1.100', NULL, '2025-06-30 23:25:04'),
(2, 29, 'upload_file', 'file', 19, 'Uploaded file: users.sql', '{\"file_size\": 8945, \"folder_name\": \"Attendance\"}', '192.168.1.101', NULL, '2025-06-30 23:33:12'),
(3, 30, 'upload_file', 'file', 20, 'Uploaded file: WITH CONTRIBUTION NUMBER.docx', '{\"file_size\": 234567, \"folder_name\": \"Exams\"}', '192.168.1.102', NULL, '2025-07-01 11:55:32'),
(4, 30, 'upload_file', 'file', 21, 'Uploaded file: admindashboard.php', '{\"file_size\": 15678, \"folder_name\": \"Other Course Materials\"}', '192.168.1.102', NULL, '2025-07-01 11:56:01'),
(5, 28, 'upload_file', 'file', 22, 'Uploaded file: AUTHORIZATION LETTER.docx', '{\"file_size\": 67890, \"folder_name\": \"DTR\"}', '192.168.1.100', NULL, '2025-07-01 11:56:25'),
(6, 27, 'create_announcement', 'announcement', 2, 'Created announcement: ITD Day Celebration 2025', '{\"priority\": \"high\", \"type\": \"department\"}', '192.168.1.50', NULL, '2025-06-13 08:59:03'),
(7, 27, 'create_announcement', 'announcement', 3, 'Created announcement: Happy Birthday Francene!', '{\"priority\": \"normal\", \"type\": \"department\"}', '192.168.1.50', NULL, '2025-06-13 09:06:00'),
(8, 27, 'approve_user', 'user', 28, 'Approved user: Henry R Balanza', '{\"department\": \"ITD\", \"role\": \"user\"}', '192.168.1.50', NULL, '2025-06-30 10:15:30'),
(9, 27, 'approve_user', 'user', 29, 'Approved user: Marc Luigi G Timola', '{\"department\": \"ITD\", \"role\": \"user\"}', '192.168.1.50', NULL, '2025-06-30 10:16:45'),
(10, 27, 'approve_user', 'user', 30, 'Approved user: Ricky Jay A Riel', '{\"department\": \"ITD\", \"role\": \"user\"}', '192.168.1.50', NULL, '2025-06-30 10:17:22'),
(11, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 09:55:59'),
(12, 28, 'login', 'user', 28, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 10:12:38'),
(13, 28, 'logout', 'user', 28, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 10:13:37'),
(14, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 10:13:45'),
(15, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 10:14:46'),
(16, 28, 'login', 'user', 28, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 10:14:53'),
(17, 28, 'logout', 'user', 28, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 10:15:04'),
(18, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 10:15:13');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `announcement_type` enum('general','department','urgent','maintenance') DEFAULT 'general',
  `target_departments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_departments`)),
  `target_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_roles`)),
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
  `email_sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_views`
--

CREATE TABLE `announcement_views` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `viewed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `announcement_views`
--
DELIMITER $$
CREATE TRIGGER `tr_announcement_view_insert` AFTER INSERT ON `announcement_views` FOR EACH ROW BEGIN
    UPDATE announcements 
    SET view_count = (
        SELECT COUNT(*) FROM announcement_views 
        WHERE announcement_id = NEW.announcement_id
    )
    WHERE id = NEW.announcement_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_code` varchar(10) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_of_department` varchar(100) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_code`, `department_name`, `description`, `head_of_department`, `contact_email`, `contact_phone`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'TED', 'Teacher Education Department', 'Responsible for teacher training and education programs', 'Dr. Maria Santos', 'ted@cvsu.edu.ph', '+63-2-1234-5671', '2025-08-07 01:55:15', '2025-08-07 01:55:15', 1),
(2, 'MD', 'Management Department', 'Business administration and management programs', 'Prof. Juan Dela Cruz', 'md@cvsu.edu.ph', '+63-2-1234-5672', '2025-08-07 01:55:15', '2025-08-07 01:55:15', 1),
(3, 'ITD', 'Information Technology Department', 'Computer science and information technology programs', 'Dr. Robert Garcia', 'itd@cvsu.edu.ph', '+63-2-1234-5673', '2025-08-07 01:55:15', '2025-08-07 01:55:15', 1),
(4, 'FASD', 'Fisheries and Aquatic Science Department', 'Marine biology and fisheries management programs', 'Dr. Ana Reyes', 'fasd@cvsu.edu.ph', '+63-2-1234-5674', '2025-08-07 01:55:15', '2025-08-07 01:55:15', 1),
(5, 'ASD', 'Arts and Science Department', 'Liberal arts and natural sciences programs', 'Prof. Carlos Mendoza', 'asd@cvsu.edu.ph', '+63-2-1234-5675', '2025-08-07 01:55:15', '2025-08-07 01:55:15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
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
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `version` int(11) DEFAULT 1,
  `parent_file_id` int(11) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `description` text DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `is_favorite` tinyint(1) DEFAULT 0,
  `expiry_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `file_name`, `original_name`, `file_path`, `file_size`, `file_type`, `mime_type`, `file_extension`, `uploaded_by`, `folder_id`, `uploaded_at`, `updated_at`, `is_deleted`, `deleted_at`, `deleted_by`, `file_hash`, `download_count`, `last_downloaded`, `last_downloaded_by`, `is_public`, `public_token`, `permissions`, `version`, `parent_file_id`, `tags`, `description`, `thumbnail_path`, `is_favorite`, `expiry_date`) VALUES
(18, '1719775504_summary_form.docx', 'NAIC_QF_xxxx_Summary-of-Comments-and-Action-Taken-Form.docx', 'uploads/1719775504_summary_form.docx', 45678, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 28, 47, '2025-06-30 23:25:04', '2025-08-07 01:55:15', 0, NULL, NULL, 'abc123def456', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Quality assurance summary form', NULL, 0, NULL),
(19, '1719775992_users_backup.sql', 'users.sql', 'uploads/1719775992_users_backup.sql', 8945, 'database', 'application/sql', 'sql', 29, 47, '2025-06-30 23:33:12', '2025-08-07 01:55:15', 0, NULL, NULL, 'def456ghi789', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Database backup file', NULL, 0, NULL),
(20, '1719832532_contribution_doc.docx', 'WITH CONTRIBUTION NUMBER.docx', 'uploads/1719832532_contribution_doc.docx', 234567, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 30, 51, '2025-07-01 11:55:32', '2025-08-07 01:55:15', 0, NULL, NULL, 'ghi789jkl012', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Research contribution document', NULL, 0, NULL),
(21, '1719832561_admin_dashboard.php', 'admindashboard.php', 'uploads/1719832561_admin_dashboard.php', 15678, 'code', 'application/x-php', 'php', 30, 52, '2025-07-01 11:56:01', '2025-08-07 01:55:15', 0, NULL, NULL, 'jkl012mno345', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Admin dashboard source code', NULL, 0, NULL),
(22, '1719832585_authorization_letter.docx', 'AUTHORIZATION LETTER.docx', 'uploads/1719832585_authorization_letter.docx', 67890, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 28, 46, '2025-07-01 11:56:25', '2025-08-07 01:55:15', 0, NULL, NULL, 'mno345pqr678', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Official authorization letter', NULL, 0, NULL);

--
-- Triggers `files`
--
DELIMITER $$
CREATE TRIGGER `tr_file_insert_log` AFTER INSERT ON `files` FOR EACH ROW BEGIN
    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata)
    VALUES (NEW.uploaded_by, 'upload_file', 'file', NEW.id, 
            CONCAT('Uploaded file: ', NEW.original_name),
            JSON_OBJECT('file_size', NEW.file_size, 'file_type', NEW.file_type));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_file_insert_update_folder` AFTER INSERT ON `files` FOR EACH ROW BEGIN
    CALL sp_update_folder_stats(NEW.folder_id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_file_update_folder_stats` AFTER UPDATE ON `files` FOR EACH ROW BEGIN
    IF OLD.is_deleted != NEW.is_deleted THEN
        CALL sp_update_folder_stats(NEW.folder_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `file_comments`
--

CREATE TABLE `file_comments` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `file_comments`
--

INSERT INTO `file_comments` (`id`, `file_id`, `user_id`, `comment`, `parent_comment_id`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, 18, 29, 'This document looks comprehensive. Good work on the formatting.', NULL, 0, '2025-08-07 01:55:16', '2025-08-07 01:55:16'),
(2, 20, 28, 'Please review the contribution numbers in section 3 before final submission.', NULL, 0, '2025-08-07 01:55:16', '2025-08-07 01:55:16'),
(3, 22, 27, 'Authorization letter approved. Please proceed with the next steps.', NULL, 0, '2025-08-07 01:55:16', '2025-08-07 01:55:16');

-- --------------------------------------------------------

--
-- Table structure for table `file_shares`
--

CREATE TABLE `file_shares` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `shared_with` int(11) DEFAULT NULL,
  `share_token` varchar(255) NOT NULL,
  `share_type` enum('user','public','department') DEFAULT 'user',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `expires_at` datetime DEFAULT NULL,
  `password_protected` tinyint(1) DEFAULT 0,
  `share_password` varchar(255) DEFAULT NULL,
  `download_limit` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_accessed` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `file_shares`
--

INSERT INTO `file_shares` (`id`, `file_id`, `shared_by`, `shared_with`, `share_token`, `share_type`, `permissions`, `expires_at`, `password_protected`, `share_password`, `download_limit`, `download_count`, `is_active`, `created_at`, `last_accessed`) VALUES
(1, 18, 28, 29, 'share_abc123def456', 'user', '{\"read\": true, \"download\": true}', '2025-12-31 23:59:59', 0, NULL, 10, 0, 1, '2025-08-07 01:55:16', NULL),
(2, 20, 30, NULL, 'public_ghi789jkl012', 'public', '{\"read\": true, \"download\": false}', '2025-08-31 23:59:59', 0, NULL, NULL, 0, 1, '2025-08-07 01:55:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
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
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `folder_size` bigint(20) DEFAULT 0,
  `file_count` int(11) DEFAULT 0,
  `folder_color` varchar(7) DEFAULT '#667eea',
  `folder_icon` varchar(50) DEFAULT 'fa-folder'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `folders`
--

INSERT INTO `folders` (`id`, `folder_name`, `description`, `created_by`, `created_at`, `updated_at`, `parent_id`, `is_deleted`, `deleted_at`, `deleted_by`, `department_id`, `folder_path`, `folder_level`, `is_public`, `permissions`, `folder_size`, `file_count`, `folder_color`, `folder_icon`) VALUES
(44, 'IPCR', 'Individual Performance Commitment and Review documents', 27, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 0, NULL, NULL, 3, '/IPCR', 0, 0, NULL, 0, 0, '#667eea', 'fa-chart-line'),
(45, 'Minutes', 'Meeting minutes and proceedings', 27, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 0, NULL, NULL, 3, '/Minutes', 0, 0, NULL, 0, 0, '#38a169', 'fa-file-alt'),
(46, 'DTR', 'Daily Time Records', 27, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 0, NULL, NULL, 3, '/DTR', 0, 0, NULL, 0, 0, '#ed8936', 'fa-clock'),
(47, 'Attendance', 'Student and faculty attendance records', 27, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 0, NULL, NULL, 3, '/Attendance', 0, 0, NULL, 0, 0, '#9f7aea', 'fa-users'),
(48, 'Syllabus', 'Course syllabi and curriculum documents', 27, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 0, NULL, NULL, 3, '/Syllabus', 0, 1, NULL, 0, 0, '#4299e1', 'fa-book'),
(49, 'Lecture Notes', 'Teaching materials and lecture notes', 27, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 0, NULL, NULL, 3, '/Lecture Notes', 0, 1, NULL, 0, 0, '#48bb78', 'fa-graduation-cap'),
(50, 'Grading Sheets', 'Student grades and assessment records', 27, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 0, NULL, NULL, 3, '/Grading Sheets', 0, 0, NULL, 0, 0, '#e53e3e', 'fa-table'),
(51, 'Exams', 'Examination papers and answer keys', 27, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 0, NULL, NULL, 3, '/Exams', 0, 0, NULL, 0, 0, '#d69e2e', 'fa-file-signature'),
(52, 'Other Course Materials', 'Additional teaching and learning resources', 27, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 0, NULL, NULL, 3, '/Other Course Materials', 0, 1, NULL, 0, 0, '#805ad5', 'fa-folder-open');

-- --------------------------------------------------------

--
-- Table structure for table `folder_permissions`
--

CREATE TABLE `folder_permissions` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `permission_type` enum('read','write','admin') DEFAULT 'read',
  `granted_by` int(11) NOT NULL,
  `granted_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `folder_permissions`
--

INSERT INTO `folder_permissions` (`id`, `folder_id`, `user_id`, `department_id`, `role`, `permission_type`, `granted_by`, `granted_at`, `expires_at`, `is_active`) VALUES
(1, 48, 28, NULL, NULL, 'write', 27, '2025-08-07 01:55:16', NULL, 1),
(2, 49, 28, NULL, NULL, 'write', 27, '2025-08-07 01:55:16', NULL, 1),
(3, 48, 29, NULL, NULL, 'read', 27, '2025-08-07 01:55:16', NULL, 1),
(4, 49, 29, NULL, NULL, 'write', 27, '2025-08-07 01:55:16', NULL, 1),
(5, 50, 30, NULL, NULL, 'read', 27, '2025-08-07 01:55:16', NULL, 1),
(6, 51, 30, NULL, NULL, 'write', 27, '2025-08-07 01:55:16', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `action_url` varchar(500) DEFAULT NULL,
  `action_text` varchar(100) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `action_url`, `action_text`, `is_read`, `read_at`, `created_at`, `expires_at`) VALUES
(1, 28, 'File Upload Successful', 'Your file \"AUTHORIZATION LETTER.docx\" has been uploaded successfully to DTR folder.', 'success', '/files/view/22', 'View File', 1, NULL, '2025-07-01 11:56:25', NULL),
(2, 29, 'Welcome to MyDrive', 'Your account has been approved. You can now access all features of the document management system.', 'success', '/dashboard', 'Go to Dashboard', 1, NULL, '2025-06-30 10:16:45', NULL),
(3, 30, 'Account Approved', 'Your MyDrive account has been approved by the administrator. Welcome to the system!', 'success', '/profile', 'Complete Profile', 0, NULL, '2025-06-30 10:17:22', NULL),
(4, 31, 'Account Pending', 'Your account registration is pending approval. You will receive a notification once approved.', 'info', NULL, NULL, 0, NULL, '2025-08-07 01:55:15', NULL),
(5, 28, 'New Announcement', 'A new announcement \"ITD Day Celebration 2025\" has been posted.', 'info', '/announcements/view/2', 'Read More', 0, NULL, '2025-06-13 08:59:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 'site_name', 'MyDrive - CVSU Naic', 'string', 'Application name displayed in header', 1, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(2, 'site_description', 'Document Management System for CVSU Naic', 'string', 'Application description', 1, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(3, 'max_file_size', '104857600', 'integer', 'Maximum file upload size in bytes (100MB)', 0, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(4, 'allowed_file_types', '[\"pdf\",\"doc\",\"docx\",\"xls\",\"xlsx\",\"ppt\",\"pptx\",\"txt\",\"jpg\",\"jpeg\",\"png\",\"gif\",\"sql\",\"php\",\"js\",\"html\",\"css\",\"zip\",\"rar\"]', 'json', 'Allowed file extensions for upload', 0, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(5, 'email_notifications', 'true', 'boolean', 'Enable email notifications', 0, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(6, 'user_registration', 'true', 'boolean', 'Allow new user registration', 0, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(7, 'auto_approve_users', 'false', 'boolean', 'Automatically approve new users', 0, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(8, 'session_timeout', '3600', 'integer', 'Session timeout in seconds', 0, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(9, 'backup_retention_days', '30', 'integer', 'Number of days to retain backups', 0, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(10, 'maintenance_mode', 'false', 'boolean', 'Enable maintenance mode', 1, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(11, 'default_folder_permissions', '{\"read\": true, \"write\": false, \"admin\": false}', 'json', 'Default permissions for new folders', 0, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL),
(12, 'password_policy', '{\"min_length\": 8, \"require_uppercase\": true, \"require_lowercase\": true, \"require_numbers\": true, \"require_symbols\": false}', 'json', 'Password complexity requirements', 0, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL);

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
(1, 'superadmin', 'admin@cvsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1, 'System', '', 'Administrator', 'ADMIN001', 'System Administrator', NULL, 0, NULL, NULL, NULL, NULL, NULL, '2025-08-07 10:15:13', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-07 10:15:13', NULL, NULL, NULL),
(27, 'itdadmin', 'itdadmin@cvsu.edu.ph', '$2y$10$bpEIbndhHjmVawTlc/brAO8q.o9the5fYlV5XHXMZ3AfqX43xwWw.', 'admin', 1, 'ITD', '', 'Administrator', 'ITD001', 'Department Administrator', 3, 0, NULL, NULL, NULL, NULL, NULL, NULL, 2, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-07 10:12:31', NULL, 1, '2025-08-07 01:55:15'),
(28, 'hbalanza', 'henry.balanza@cvsu.edu.ph', '$2y$10$kRVE4pnSIX6YvPrVGbMrX.x.0P1pnPsGTpr4P65xChwbJe4Mwedey', 'user', 1, 'Henry', 'R', 'Balanza', 'ITD002', 'Assistant Professor', 3, 0, NULL, NULL, NULL, NULL, NULL, '2025-08-07 10:14:53', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-07 10:14:53', NULL, 27, '2025-08-07 01:55:15'),
(29, 'mtimola', 'luigi.timola@cvsu.edu.ph', '$2y$10$IrcJneb3t//.O370AcJ75.N20qreX2nHkYp3eWnetOLgSHQ62txMq', 'user', 1, 'Marc Luigi', 'G', 'Timola', 'ITD003', 'Associate Professor', 3, 0, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-07 10:10:32', NULL, 27, '2025-08-07 01:55:15'),
(30, 'rriel', 'rj.riel@cvsu.edu.ph', '$2y$10$CTAcrRPYboonzGIiDpw8S.thwJ7ueM0nkVN4XdQo4vNXAmt6aXnYC', 'user', 1, 'Ricky Jay', 'A', 'Riel', 'ITD004', 'Instructor', 3, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, 27, '2025-08-07 01:55:15'),
(31, 'pending_user', 'pending@cvsu.edu.ph', '$2y$10$9ldd1yiEVKJAyXubZ.GLc.ZFkMjkP.j4XqL7VRdBbAR8k2aKXKEI.', 'user', 0, 'John', 'A', 'Doe', 'ITD005', 'Instructor', 3, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_announcements_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_announcements_detailed` (
`id` int(11)
,`title` varchar(255)
,`content` text
,`summary` varchar(500)
,`image_path` varchar(255)
,`priority` enum('low','normal','high','urgent')
,`announcement_type` enum('general','department','urgent','maintenance')
,`is_published` tinyint(1)
,`published_at` datetime
,`expires_at` datetime
,`view_count` int(11)
,`is_pinned` tinyint(1)
,`created_at` datetime
,`created_by_username` varchar(50)
,`creator_full_name` varchar(208)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_files_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_files_detailed` (
`id` int(11)
,`original_name` varchar(255)
,`file_name` varchar(255)
,`file_size` bigint(20)
,`file_type` varchar(100)
,`mime_type` varchar(100)
,`file_extension` varchar(10)
,`uploaded_at` datetime
,`download_count` int(11)
,`is_deleted` tinyint(1)
,`folder_name` varchar(100)
,`folder_path` varchar(500)
,`uploaded_by_username` varchar(50)
,`uploader_full_name` varchar(208)
,`folder_department` varchar(10)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_folders_hierarchy`
-- (See below for the actual view)
--
CREATE TABLE `v_folders_hierarchy` (
`id` int(11)
,`folder_name` varchar(100)
,`folder_path` varchar(500)
,`folder_level` int(11)
,`is_public` tinyint(1)
,`folder_color` varchar(7)
,`folder_icon` varchar(50)
,`file_count` int(11)
,`folder_size` bigint(20)
,`created_at` datetime
,`parent_folder_name` varchar(100)
,`created_by_username` varchar(50)
,`creator_full_name` varchar(208)
,`department_code` varchar(10)
,`department_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_users_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_users_detailed` (
`id` int(11)
,`username` varchar(50)
,`email` varchar(255)
,`role` enum('admin','user','super_admin')
,`is_approved` tinyint(1)
,`name` varchar(100)
,`mi` varchar(5)
,`surname` varchar(100)
,`full_name` varchar(208)
,`employee_id` varchar(20)
,`position` varchar(100)
,`is_restricted` tinyint(1)
,`last_login` datetime
,`created_at` datetime
,`department_code` varchar(10)
,`department_name` varchar(100)
,`approved_by_username` varchar(50)
,`approved_at` datetime
);

-- --------------------------------------------------------

--
-- Structure for view `v_announcements_detailed`
--
DROP TABLE IF EXISTS `v_announcements_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_announcements_detailed`  AS SELECT `a`.`id` AS `id`, `a`.`title` AS `title`, `a`.`content` AS `content`, `a`.`summary` AS `summary`, `a`.`image_path` AS `image_path`, `a`.`priority` AS `priority`, `a`.`announcement_type` AS `announcement_type`, `a`.`is_published` AS `is_published`, `a`.`published_at` AS `published_at`, `a`.`expires_at` AS `expires_at`, `a`.`view_count` AS `view_count`, `a`.`is_pinned` AS `is_pinned`, `a`.`created_at` AS `created_at`, `creator`.`username` AS `created_by_username`, concat(`creator`.`name`,' ',ifnull(concat(`creator`.`mi`,'. '),''),`creator`.`surname`) AS `creator_full_name` FROM (`announcements` `a` left join `users` `creator` on(`a`.`created_by` = `creator`.`id`)) WHERE `a`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `v_files_detailed`
--
DROP TABLE IF EXISTS `v_files_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_files_detailed`  AS SELECT `f`.`id` AS `id`, `f`.`original_name` AS `original_name`, `f`.`file_name` AS `file_name`, `f`.`file_size` AS `file_size`, `f`.`file_type` AS `file_type`, `f`.`mime_type` AS `mime_type`, `f`.`file_extension` AS `file_extension`, `f`.`uploaded_at` AS `uploaded_at`, `f`.`download_count` AS `download_count`, `f`.`is_deleted` AS `is_deleted`, `folder`.`folder_name` AS `folder_name`, `folder`.`folder_path` AS `folder_path`, `uploader`.`username` AS `uploaded_by_username`, concat(`uploader`.`name`,' ',ifnull(concat(`uploader`.`mi`,'. '),''),`uploader`.`surname`) AS `uploader_full_name`, `dept`.`department_code` AS `folder_department` FROM (((`files` `f` left join `folders` `folder` on(`f`.`folder_id` = `folder`.`id`)) left join `users` `uploader` on(`f`.`uploaded_by` = `uploader`.`id`)) left join `departments` `dept` on(`folder`.`department_id` = `dept`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_folders_hierarchy`
--
DROP TABLE IF EXISTS `v_folders_hierarchy`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_folders_hierarchy`  AS SELECT `f`.`id` AS `id`, `f`.`folder_name` AS `folder_name`, `f`.`folder_path` AS `folder_path`, `f`.`folder_level` AS `folder_level`, `f`.`is_public` AS `is_public`, `f`.`folder_color` AS `folder_color`, `f`.`folder_icon` AS `folder_icon`, `f`.`file_count` AS `file_count`, `f`.`folder_size` AS `folder_size`, `f`.`created_at` AS `created_at`, `parent`.`folder_name` AS `parent_folder_name`, `creator`.`username` AS `created_by_username`, concat(`creator`.`name`,' ',ifnull(concat(`creator`.`mi`,'. '),''),`creator`.`surname`) AS `creator_full_name`, `dept`.`department_code` AS `department_code`, `dept`.`department_name` AS `department_name` FROM (((`folders` `f` left join `folders` `parent` on(`f`.`parent_id` = `parent`.`id`)) left join `users` `creator` on(`f`.`created_by` = `creator`.`id`)) left join `departments` `dept` on(`f`.`department_id` = `dept`.`id`)) WHERE `f`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `v_users_detailed`
--
DROP TABLE IF EXISTS `v_users_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_users_detailed`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`role` AS `role`, `u`.`is_approved` AS `is_approved`, `u`.`name` AS `name`, `u`.`mi` AS `mi`, `u`.`surname` AS `surname`, concat(`u`.`name`,' ',ifnull(concat(`u`.`mi`,'. '),''),`u`.`surname`) AS `full_name`, `u`.`employee_id` AS `employee_id`, `u`.`position` AS `position`, `u`.`is_restricted` AS `is_restricted`, `u`.`last_login` AS `last_login`, `u`.`created_at` AS `created_at`, `d`.`department_code` AS `department_code`, `d`.`department_name` AS `department_name`, `approver`.`username` AS `approved_by_username`, `u`.`approved_at` AS `approved_at` FROM ((`users` `u` left join `departments` `d` on(`u`.`department_id` = `d`.`id`)) left join `users` `approver` on(`u`.`approved_by` = `approver`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_resource` (`resource_type`,`resource_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_activity_logs_user_action` (`user_id`,`action`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_is_published` (`is_published`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `announcements_updated_by_fk` (`updated_by`),
  ADD KEY `announcements_deleted_by_fk` (`deleted_by`),
  ADD KEY `idx_announcements_published` (`is_published`,`published_at`);
ALTER TABLE `announcements` ADD FULLTEXT KEY `announcement_search` (`title`,`content`,`summary`);

--
-- Indexes for table `announcement_views`
--
ALTER TABLE `announcement_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_view` (`announcement_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `parent_file_id` (`parent_file_id`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_public_token` (`public_token`),
  ADD KEY `idx_file_hash` (`file_hash`),
  ADD KEY `files_deleted_by_fk` (`deleted_by`),
  ADD KEY `files_downloaded_by_fk` (`last_downloaded_by`),
  ADD KEY `idx_files_uploaded_at` (`uploaded_at`),
  ADD KEY `idx_files_file_type_size` (`file_type`,`file_size`);
ALTER TABLE `files` ADD FULLTEXT KEY `file_search` (`original_name`,`description`);

--
-- Indexes for table `file_comments`
--
ALTER TABLE `file_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_comment_id` (`parent_comment_id`);

--
-- Indexes for table `file_shares`
--
ALTER TABLE `file_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `share_token` (`share_token`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `shared_by` (`shared_by`),
  ADD KEY `shared_with` (`shared_with`),
  ADD KEY `idx_file_shares_expires` (`expires_at`,`is_active`);

--
-- Indexes for table `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `idx_folder_path` (`folder_path`),
  ADD KEY `folders_deleted_by_fk` (`deleted_by`),
  ADD KEY `idx_folders_created_at` (`created_at`);
ALTER TABLE `folders` ADD FULLTEXT KEY `folder_search` (`folder_name`,`description`);

--
-- Indexes for table `folder_permissions`
--
ALTER TABLE `folder_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `granted_by` (`granted_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

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
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `announcement_views`
--
ALTER TABLE `announcement_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `file_comments`
--
ALTER TABLE `file_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `file_shares`
--
ALTER TABLE `file_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `folder_permissions`
--
ALTER TABLE `folder_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `announcements_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `announcement_views`
--
ALTER TABLE `announcement_views`
  ADD CONSTRAINT `announcement_views_announcement_fk` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_views_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `files_downloaded_by_fk` FOREIGN KEY (`last_downloaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_parent_fk` FOREIGN KEY (`parent_file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `file_comments`
--
ALTER TABLE `file_comments`
  ADD CONSTRAINT `file_comments_file_fk` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_comments_parent_fk` FOREIGN KEY (`parent_comment_id`) REFERENCES `file_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `file_shares`
--
ALTER TABLE `file_shares`
  ADD CONSTRAINT `file_shares_file_fk` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_shares_shared_by_fk` FOREIGN KEY (`shared_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_shares_shared_with_fk` FOREIGN KEY (`shared_with`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `folders_department_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `folder_permissions`
--
ALTER TABLE `folder_permissions`
  ADD CONSTRAINT `folder_permissions_department_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_permissions_folder_fk` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_permissions_granted_by_fk` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_permissions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
