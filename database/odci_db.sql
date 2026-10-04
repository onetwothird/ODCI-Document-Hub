-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 04, 2026 at 02:31 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `odci_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `InitializeAcademicPeriod` (IN `p_academic_year` YEAR, IN `p_semester` VARCHAR(20))   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_user_id INT;
    DECLARE v_doc_type VARCHAR(100);
    
    -- Cursor for all active users
    DECLARE user_cursor CURSOR FOR 
        SELECT id FROM users WHERE is_approved = 1 AND role = 'user';
        
    -- Cursor for required document types
    DECLARE doc_cursor CURSOR FOR
        SELECT DISTINCT document_type FROM document_requirements 
        WHERE academic_year = p_academic_year AND semester = p_semester AND is_required = 1;
        
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Create submission records for all users and document types
    OPEN user_cursor;
    user_loop: LOOP
        FETCH user_cursor INTO v_user_id;
        IF done THEN
            LEAVE user_loop;
        END IF;
        
        SET done = FALSE;
        OPEN doc_cursor;
        doc_loop: LOOP
            FETCH doc_cursor INTO v_doc_type;
            IF done THEN
                LEAVE doc_loop;
            END IF;
            
            -- Insert submission record if it doesn't exist
            INSERT IGNORE INTO faculty_document_submissions 
            (faculty_id, document_type, semester, academic_year, submitted_by, submitted_at)
            VALUES (v_user_id, v_doc_type, p_semester, p_academic_year, v_user_id, NOW());
            
        END LOOP;
        CLOSE doc_cursor;
        SET done = FALSE;
        
    END LOOP;
    CLOSE user_cursor;
    
    SELECT CONCAT('Academic period ', p_academic_year, ' - ', p_semester, ' initialized successfully') as result;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_expired_shares` ()   BEGIN
    UPDATE file_shares 
    SET is_active = 0 
    WHERE expires_at < NOW() AND is_active = 1;
    
    SELECT ROW_COUNT() as expired_shares_count;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_document_workflow` (IN `request_id` INT, IN `document_type` VARCHAR(50))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Default workflow steps based on document type
    CASE document_type
        WHEN 'certificate' THEN
            INSERT INTO document_workflows (request_id, step_number, step_name, step_description, expected_duration_hours) VALUES
            (request_id, 1, 'Initial Review', 'Review request and verify requirements', 24),
            (request_id, 2, 'Document Preparation', 'Prepare certificate document', 48),
            (request_id, 3, 'Approval', 'Get necessary approvals and signatures', 24),
            (request_id, 4, 'Final Review', 'Final quality check and review', 12),
            (request_id, 5, 'Completion', 'Document ready for pickup/delivery', 6);
            
        WHEN 'clearance' THEN
            INSERT INTO document_workflows (request_id, step_number, step_name, step_description, expected_duration_hours) VALUES
            (request_id, 1, 'Request Validation', 'Validate clearance request', 12),
            (request_id, 2, 'Background Check', 'Perform necessary background verification', 72),
            (request_id, 3, 'Department Clearance', 'Get clearance from relevant departments', 48),
            (request_id, 4, 'Final Approval', 'Final approval and document generation', 24),
            (request_id, 5, 'Release', 'Document ready for release', 6);
            
        WHEN 'permit' THEN
            INSERT INTO document_workflows (request_id, step_number, step_name, step_description, expected_duration_hours) VALUES
            (request_id, 1, 'Application Review', 'Review permit application', 24),
            (request_id, 2, 'Requirements Check', 'Verify all requirements are met', 48),
            (request_id, 3, 'Site Inspection', 'Conduct necessary inspections if required', 72),
            (request_id, 4, 'Approval Process', 'Process approval through relevant authorities', 48),
            (request_id, 5, 'Permit Issuance', 'Issue permit document', 12);
            
        ELSE -- Default workflow for other document types
            INSERT INTO document_workflows (request_id, step_number, step_name, step_description, expected_duration_hours) VALUES
            (request_id, 1, 'Initial Review', 'Review request and requirements', 24),
            (request_id, 2, 'Processing', 'Process the document request', 48),
            (request_id, 3, 'Review and Approval', 'Review and approve the document', 24),
            (request_id, 4, 'Finalization', 'Finalize and prepare document', 12);
    END CASE;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_document_stats` (IN `user_id` INT, IN `is_admin` BOOLEAN)   BEGIN
    IF is_admin THEN
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            AVG(CASE WHEN status = 'completed' AND actual_completion IS NOT NULL 
                THEN DATEDIFF(actual_completion, created_at) ELSE NULL END) as avg_completion_days
        FROM document_requests 
        WHERE is_deleted = 0;
    ELSE
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            AVG(CASE WHEN status = 'completed' AND actual_completion IS NOT NULL 
                THEN DATEDIFF(actual_completion, created_at) ELSE NULL END) as avg_completion_days
        FROM document_requests 
        WHERE user_id = user_id AND is_deleted = 0;
    END IF;
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_workflow_step` (IN `workflow_id` INT, IN `new_status` VARCHAR(20), IN `user_id` INT, IN `step_notes` TEXT)   BEGIN
    DECLARE request_id INT;
    DECLARE step_name VARCHAR(100);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Update workflow step
    UPDATE document_workflows 
    SET 
        status = new_status,
        completed_at = CASE WHEN new_status = 'completed' THEN NOW() ELSE completed_at END,
        started_at = CASE WHEN new_status = 'in_progress' AND started_at IS NULL THEN NOW() ELSE started_at END,
        actual_duration_minutes = CASE 
            WHEN new_status = 'completed' AND started_at IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, started_at, NOW()) 
            ELSE actual_duration_minutes 
        END,
        notes = COALESCE(step_notes, notes),
        updated_at = NOW()
    WHERE id = workflow_id;
    
    -- Get request info for logging
    SELECT dw.request_id, dw.step_name INTO request_id, step_name
    FROM document_workflows dw WHERE dw.id = workflow_id;
    
    -- Log the workflow update
    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata)
    VALUES (user_id, 'update_workflow_step', 'document', request_id, 
            CONCAT('Updated workflow step "', step_name, '" to ', new_status),
            JSON_OBJECT('workflow_id', workflow_id, 'new_status', new_status, 'step_name', step_name));
    
    -- If step is completed, check if we should auto-start the next step
    IF new_status = 'completed' THEN
        UPDATE document_workflows 
        SET status = 'pending', updated_at = NOW()
        WHERE request_id = request_id 
        AND step_number = (SELECT step_number + 1 FROM document_workflows WHERE id = workflow_id)
        AND status = 'pending';
    END IF;
    
    COMMIT;
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

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_generate_tracking_code` () RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE next_number INT;
    DECLARE tracking_code VARCHAR(20);
    DECLARE current_month VARCHAR(6);
    
    SET current_month = DATE_FORMAT(NOW(), '%Y%m');
    
    -- Get the next number for this month
    SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_code, -4) AS UNSIGNED)), 0) + 1 
    INTO next_number
    FROM document_requests 
    WHERE tracking_code LIKE CONCAT('DOC-', current_month, '-%');
    
    SET tracking_code = CONCAT('DOC-', current_month, '-', LPAD(next_number, 4, '0'));
    
    RETURN tracking_code;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetCurrentSemester` () RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE current_month INT;
    SET current_month = MONTH(CURDATE());
    
    -- Assuming 1st semester is June to November (months 6-11)
    -- and 2nd semester is December to May (months 12,1-5)
    IF current_month >= 6 AND current_month <= 11 THEN
        RETURN '1st Semester';
    ELSE
        RETURN '2nd Semester';
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `resource_type` enum('file','folder','user','announcement','department','system') COLLATE utf8mb4_general_ci NOT NULL,
  `resource_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ;

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
(18, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 10:15:13'),
(19, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 13:15:09'),
(20, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 13:15:19'),
(21, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 13:26:19'),
(22, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:04:59'),
(23, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:05:28'),
(24, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:21:11'),
(25, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:21:23'),
(26, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:21:26'),
(27, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:21:58'),
(28, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:22:00'),
(29, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:22:14'),
(30, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:35:20'),
(31, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:35:36'),
(32, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:35:38'),
(33, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:40:22'),
(34, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:40:25'),
(35, 32, 'register', 'user', 32, 'User registered successfully', '{\"username\":\"asd\",\"email\":\"asd@gmail.com\",\"department_id\":3}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:15:15'),
(36, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:15:53'),
(37, 1, 'approve_user', 'user', 32, 'Approved user: asd s. asd', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:16:00'),
(38, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:16:43'),
(39, 32, 'login', 'user', 32, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:16:50'),
(40, 32, 'logout', 'user', 32, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:17:58'),
(41, 32, 'login', 'user', 32, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:18:56'),
(42, 32, 'logout', 'user', 32, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:41:45'),
(43, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:41:50'),
(44, 1, 'logout', 'user', 1, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:44:59'),
(45, 32, 'login', 'user', 32, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:45:04'),
(46, 32, 'logout', 'user', 32, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 15:48:56'),
(47, 32, 'login', 'user', 32, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 15:50:29'),
(48, 32, 'logout', 'user', 32, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 15:50:46'),
(49, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 15:51:46'),
(50, 1, 'login', 'user', 1, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 17:00:19'),
(51, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 08:35:37'),
(52, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 09:08:46'),
(53, 27, 'logout', 'user', 27, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 11:25:07'),
(54, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 11:25:13'),
(55, 27, 'logout', 'user', 27, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 11:25:53'),
(56, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 11:26:07'),
(57, 27, 'logout', 'user', 27, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 11:28:37'),
(58, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 11:29:34'),
(59, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 09:04:11'),
(60, 27, 'logout', 'user', 27, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 13:07:01'),
(61, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 13:07:07'),
(62, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 23:49:52'),
(63, 27, 'logout', 'user', 27, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 00:00:59'),
(64, 28, 'login', 'user', 28, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 00:01:15'),
(65, 28, 'logout', 'user', 28, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 01:38:11'),
(66, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 01:38:17'),
(67, 27, 'logout', 'user', 27, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 01:41:30'),
(68, 28, 'login', 'user', 28, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 01:41:39'),
(69, 28, 'document_upload', 'file', NULL, 'Uploaded 1 file(s) for IPCR Accomplishment (2025 - 1st Semester)', NULL, NULL, NULL, '2025-08-18 02:30:45'),
(70, 28, 'logout', 'user', 28, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 02:41:27'),
(71, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 02:41:32'),
(72, 27, 'logout', 'user', 27, 'User logged out', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 03:13:07'),
(73, 27, 'login', 'user', 27, 'User logged in successfully', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 03:13:14'),
(74, 27, 'file_download', '', 4, 'Downloaded IPCR Accomplishment file: Linkages-Script.docx', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 05:16:52'),
(75, 30, 'upload_file', 'file', 23, 'Uploaded file: receipt_JYS-261001-9543 (2).pdf', '{\"file_size\": 46021, \"file_type\": \"document\"}', NULL, NULL, '2026-10-04 08:20:57'),
(76, 30, 'upload_file', 'file', 24, 'Uploaded file: receipt_JYS-261001-9543 (2).pdf', '{\"file_size\": 46021, \"file_type\": \"document\"}', NULL, NULL, '2026-10-04 08:21:54'),
(77, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":440,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:22:19'),
(78, 29, 'login', 'user', 29, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:22:23'),
(79, 29, 'logout', 'user', 29, 'User logged out manually', '{\"session_duration\":4,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:22:27'),
(80, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:22:32'),
(81, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:22:33'),
(82, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:22:40'),
(83, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":16,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:22:48'),
(84, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:22:53'),
(85, 30, 'upload_file', 'file', 25, 'Uploaded file: receipt_JYS-261001-9543 (2).pdf', '{\"file_size\": 46021, \"file_type\": \"document\"}', NULL, NULL, '2026-10-04 08:24:31'),
(86, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":244,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:26:57'),
(87, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:27:00'),
(88, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":264,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:31:24'),
(89, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:31:27'),
(90, 30, 'upload_file', 'file', 26, 'Uploaded file: Angelito_Decatoria_Resume_A4 (1).pdf', '{\"file_size\": 55835, \"file_type\": \"document\"}', NULL, NULL, '2026-10-04 08:32:59'),
(91, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":226,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:35:13'),
(92, 29, 'login', 'user', 29, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:35:16'),
(93, 29, 'logout', 'user', 29, 'User logged out manually', '{\"session_duration\":20,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:35:36'),
(94, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:35:41'),
(95, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":146,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:38:07'),
(96, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:38:17'),
(97, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:38:18'),
(98, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:39:49'),
(99, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":128,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:40:25'),
(100, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:40:28'),
(101, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":211,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:43:59'),
(102, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:44:04'),
(103, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:44:07'),
(104, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":133,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:46:17'),
(105, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:46:20'),
(106, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":407,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:53:07'),
(107, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:53:10'),
(108, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:54:03'),
(109, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":102,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:54:52'),
(110, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:54:55'),
(111, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":104,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:56:39'),
(112, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:56:46'),
(113, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":8,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:56:54'),
(114, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:56:58'),
(115, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":132,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:59:10'),
(116, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:59:13'),
(117, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:00:13'),
(118, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":63,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:00:16'),
(119, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:00:19'),
(120, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":161,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:00'),
(121, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:04'),
(122, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:07'),
(123, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:21'),
(124, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":19,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:23'),
(125, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:26'),
(126, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":16,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:42'),
(127, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:46'),
(128, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":6,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:52'),
(129, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:03:54'),
(130, 30, 'profile_image_update', 'user', NULL, 'Profile image updated', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:05:38'),
(131, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":135,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:06:09'),
(132, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:06:15'),
(133, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:06:16'),
(134, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:09:04'),
(135, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:09:47'),
(136, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:09:47'),
(137, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:09:48'),
(138, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:10:56'),
(139, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:10:57'),
(140, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '127.0.0.1', NULL, '2026-10-04 09:18:34'),
(141, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '127.0.0.1', NULL, '2026-10-04 09:18:50'),
(142, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '127.0.0.1', NULL, '2026-10-04 09:19:03'),
(143, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '127.0.0.1', NULL, '2026-10-04 09:19:20'),
(144, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:20:56'),
(145, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '127.0.0.1', NULL, '2026-10-04 09:21:01'),
(146, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:25:16'),
(147, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:27:45'),
(148, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:27:46'),
(149, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:27:46'),
(150, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:27:46'),
(151, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:02'),
(152, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:10'),
(153, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:27'),
(154, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:28'),
(155, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:28'),
(156, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:29'),
(157, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:29'),
(158, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:29'),
(159, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:30'),
(160, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:30'),
(161, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:30'),
(162, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:36'),
(163, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:42'),
(164, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:46'),
(165, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:53'),
(166, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:28:54'),
(167, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:29:29'),
(168, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:30:22'),
(169, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:30:59'),
(170, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:00'),
(171, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:00'),
(172, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:48'),
(173, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:55'),
(174, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:56'),
(175, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:57'),
(176, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:57'),
(177, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:57'),
(178, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:57'),
(179, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:57'),
(180, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:58'),
(181, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:58'),
(182, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:31:58'),
(183, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:32:34'),
(184, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:32:34'),
(185, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:32:35'),
(186, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:32:35'),
(187, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:32:36'),
(188, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:24'),
(189, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:25'),
(190, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:25'),
(191, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:26'),
(192, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:26'),
(193, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:26'),
(194, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:29'),
(195, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:29'),
(196, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:29'),
(197, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:30'),
(198, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:30'),
(199, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:30'),
(200, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:30'),
(201, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:31'),
(202, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:33'),
(203, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:34'),
(204, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:34'),
(205, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:34'),
(206, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:34'),
(207, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:34'),
(208, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:35'),
(209, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:35'),
(210, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:33:35');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `resource_type`, `resource_id`, `description`, `metadata`, `ip_address`, `user_agent`, `created_at`) VALUES
(211, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:34:17'),
(212, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:34:19'),
(213, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:34:19'),
(214, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:34:20'),
(215, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:34:24'),
(216, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:34:26'),
(217, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '127.0.0.1', NULL, '2026-10-04 09:34:39'),
(218, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:07'),
(219, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:12'),
(220, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:12'),
(221, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:12'),
(222, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:13'),
(223, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:13'),
(224, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:14'),
(225, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:24'),
(226, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:25'),
(227, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:25'),
(228, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:35:25'),
(229, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:36:48'),
(230, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:36:52'),
(231, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:36:52'),
(232, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:36:53'),
(233, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:37:26'),
(234, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:37:41'),
(235, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:37:53'),
(236, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:37:54'),
(237, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:37:54'),
(238, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:37:54'),
(239, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:03'),
(240, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:03'),
(241, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:03'),
(242, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:03'),
(243, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:04'),
(244, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:04'),
(245, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:04'),
(246, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:04'),
(247, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:04'),
(248, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:04'),
(249, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:26'),
(250, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:48'),
(251, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:49'),
(252, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:49'),
(253, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:49'),
(254, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:38:50'),
(255, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:39:52'),
(256, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:39:53'),
(257, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:39:53'),
(258, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:39:53'),
(259, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:39:53'),
(260, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:40:53'),
(261, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:40:54'),
(262, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:40:54'),
(263, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:42:25'),
(264, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:42:25'),
(265, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:42:26'),
(266, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:42:26'),
(267, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:42:26'),
(268, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '127.0.0.1', NULL, '2026-10-04 09:42:27'),
(269, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:42:29'),
(270, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:42:35'),
(271, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:42:35'),
(272, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:43:29'),
(273, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:43:42'),
(274, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:43:53'),
(275, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:43:53'),
(276, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:43:53'),
(277, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:43:54'),
(278, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:43:54'),
(279, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:44:51'),
(280, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:46:50'),
(281, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:46:51'),
(282, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:46:51'),
(283, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:46:59'),
(284, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:00'),
(285, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:00'),
(286, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:00'),
(287, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:01'),
(288, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:16'),
(289, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:17'),
(290, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:17'),
(291, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:17'),
(292, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:22'),
(293, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:23'),
(294, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:37'),
(295, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:47:37'),
(296, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:48:09'),
(297, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:48:18'),
(298, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:48:18'),
(299, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:48:56'),
(300, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:49:53'),
(301, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:49:59'),
(302, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:50:07'),
(303, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:50:14'),
(304, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:50:15'),
(305, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:50:16'),
(306, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:50:16'),
(307, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:52:46'),
(308, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:53:19'),
(309, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:53:43'),
(310, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:54:39'),
(311, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:54:39'),
(312, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:54:40'),
(313, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:54:40'),
(314, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:54:40'),
(315, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:54:45'),
(316, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:54:56'),
(317, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:54:57'),
(318, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:15'),
(319, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:16'),
(320, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:16'),
(321, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:16'),
(322, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:16'),
(323, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:16'),
(324, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:51'),
(325, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:52'),
(326, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:56'),
(327, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:55:56'),
(328, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:58:42'),
(329, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:58:52'),
(330, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:02:01'),
(331, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:07:03'),
(332, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:11:25'),
(333, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:11:39'),
(334, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:12:00'),
(335, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:13:30'),
(336, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":4137,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:15:12'),
(337, 30, 'login', 'user', 30, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:15:15'),
(338, 30, 'logout', 'user', 30, 'User logged out manually', '{\"session_duration\":10,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:15:25'),
(339, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:15:30'),
(340, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":373,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', '2026-10-04 10:21:43'),
(341, 29, 'login', 'user', 29, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 16; Pixel 10) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Mobile Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', '2026-10-04 10:21:48'),
(342, 29, 'logout', 'user', 29, 'User logged out manually', '{\"session_duration\":87,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', '2026-10-04 10:23:15'),
(343, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 16; Pixel 10) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Mobile Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Mobile Safari/537.36', '2026-10-04 10:23:21'),
(344, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:25:52'),
(345, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:25:56'),
(346, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:27:12'),
(347, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:27:24'),
(348, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:27:32'),
(349, 27, 'logout', 'user', 27, 'User logged out manually', '{\"session_duration\":288,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:28:09'),
(350, 1, 'failed_login', 'user', 1, 'Failed login attempt', '{\"attempt_number\":1,\"ip_address\":\"::1\",\"username_tried\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:28:25'),
(351, 1, 'login', 'user', 1, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:28:29'),
(352, 1, 'logout', 'user', 1, 'User logged out manually', '{\"session_duration\":27,\"ip_address\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:28:56'),
(353, 27, 'login', 'user', 27, 'User logged in successfully', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/154.0.0.0 Safari\\/537.36\",\"remember_me\":\"no\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:29:00'),
(354, 27, 'view_faculty_list', 'user', NULL, 'Viewed faculty staff page', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:29:26');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_general_ci DEFAULT 'normal',
  `announcement_type` enum('general','department','urgent','maintenance') COLLATE utf8mb4_general_ci DEFAULT 'general',
  `target_departments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `target_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `is_published` tinyint(1) DEFAULT '0',
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int NOT NULL,
  `updated_by` int DEFAULT NULL,
  `view_count` int DEFAULT '0',
  `is_pinned` tinyint(1) DEFAULT '0',
  `allow_comments` tinyint(1) DEFAULT '1',
  `send_email` tinyint(1) DEFAULT '0',
  `email_sent` tinyint(1) DEFAULT '0',
  `email_sent_at` datetime DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_views`
--

CREATE TABLE `announcement_views` (
  `id` int NOT NULL,
  `announcement_id` int NOT NULL,
  `user_id` int NOT NULL,
  `viewed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci
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
  `id` int NOT NULL,
  `department_code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `department_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `head_of_department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_code`, `department_name`, `description`, `head_of_department`, `contact_email`, `contact_phone`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'TED', 'Teacher Education Department', 'Responsible for teacher training and education programs', 'Dr. TED', 'ted@cvsu.edu.ph', '+63-2-1234-5671', '2025-08-07 01:55:15', '2025-08-07 15:26:18', 1),
(2, 'MD', 'Management Department', 'Business administration and management programs', 'Prof. MD', 'md@cvsu.edu.ph', '+63-2-1234-5672', '2025-08-07 01:55:15', '2025-08-07 15:26:18', 1),
(3, 'ITD', 'Information Technology Department', 'Computer science and information technology programs', 'Dr. Michelle C. Tanega', 'itd@cvsu.edu.ph', '+63-2-1234-5673', '2025-08-07 01:55:15', '2025-08-07 15:25:30', 1),
(4, 'FASD', 'Fisheries and Aquatic Science Department', 'Marine biology and fisheries management programs', 'Dr. FASD', 'fasd@cvsu.edu.ph', '+63-2-1234-5674', '2025-08-07 01:55:15', '2025-08-07 15:26:18', 1),
(5, 'ASD', 'Arts and Science Department', 'Liberal arts and natural sciences programs', 'Prof. ASD', 'asd@cvsu.edu.ph', '+63-2-1234-5675', '2025-08-07 01:55:15', '2025-08-07 15:26:18', 1),
(6, 'NSTP', 'National Service Training Program', 'Civic education and defense preparedness program for tertiary students', 'Dr. NSTP', 'nstp@cvsu.edu.ph', '+63-2-1234-5676', '2025-08-07 15:27:33', '2025-08-07 15:27:33', 1),
(7, 'OTHR', 'Others', 'For students or faculty not assigned to a specific academic department', NULL, NULL, NULL, '2025-08-07 15:27:33', '2025-08-07 15:27:33', 1);

-- --------------------------------------------------------

--
-- Table structure for table `document_attachments`
--

CREATE TABLE `document_attachments` (
  `id` int NOT NULL,
  `request_id` int NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_type` enum('requirement','supporting','output','other') COLLATE utf8mb4_general_ci DEFAULT 'supporting',
  `uploaded_by` int NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `description` text COLLATE utf8mb4_general_ci,
  `is_deleted` tinyint(1) DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_comments`
--

CREATE TABLE `document_comments` (
  `id` int NOT NULL,
  `request_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci NOT NULL,
  `comment_type` enum('general','status_update','requirement','internal') COLLATE utf8mb4_general_ci DEFAULT 'general',
  `parent_comment_id` int DEFAULT NULL,
  `is_internal` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_edited` tinyint(1) DEFAULT '0',
  `edited_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_files`
--

CREATE TABLE `document_files` (
  `id` int NOT NULL,
  `submission_id` int NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_size` int NOT NULL,
  `file_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `academic_year` year DEFAULT NULL,
  `semester_period` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uploaded_by` int NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mime_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `download_count` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_files`
--

INSERT INTO `document_files` (`id`, `submission_id`, `file_name`, `file_path`, `file_size`, `file_type`, `academic_year`, `semester_period`, `uploaded_by`, `description`, `uploaded_at`, `mime_type`, `download_count`) VALUES
(4, 28, 'Linkages-Script.docx', '../../../uploads/documents/2025/1st_semester/2025_1st_Semester_IPCR_Accomplishment_28_1755455445_68a21fd5e7365.docx', 3520212, 'IPCR Accomplishment', '2025', '1st Semester', 28, '', '2025-08-18 02:30:45', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `document_notifications`
--

CREATE TABLE `document_notifications` (
  `id` int NOT NULL,
  `request_id` int NOT NULL,
  `user_id` int NOT NULL,
  `notification_type` enum('status_change','assignment','deadline','comment','completion') COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `action_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
) ;

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int NOT NULL,
  `tracking_code` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `document_type` enum('certificate','clearance','permit','report','form','other') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'other',
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_general_ci DEFAULT 'normal',
  `status` enum('pending','in_progress','under_review','completed','rejected','cancelled') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `target_department` int DEFAULT NULL,
  `expected_completion` date DEFAULT NULL,
  `actual_completion` datetime DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL
) ;

--
-- Triggers `document_requests`
--
DELIMITER $$
CREATE TRIGGER `tr_document_request_insert` BEFORE INSERT ON `document_requests` FOR EACH ROW BEGIN
    DECLARE next_id INT;
    DECLARE tracking_code VARCHAR(20);
    
    -- Generate tracking code
    SELECT AUTO_INCREMENT INTO next_id FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requests';
    
    SET tracking_code = CONCAT('DOC-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(next_id, 4, '0'));
    SET NEW.tracking_code = tracking_code;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_document_request_log` AFTER INSERT ON `document_requests` FOR EACH ROW BEGIN
    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata)
    VALUES (NEW.user_id, 'create_document_request', 'document', NEW.id, 
            CONCAT('Created document request: ', NEW.title, ' (', NEW.tracking_code, ')'),
            JSON_OBJECT('tracking_code', NEW.tracking_code, 'document_type', NEW.document_type, 'priority', NEW.priority));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `document_requirements`
--

CREATE TABLE `document_requirements` (
  `id` int NOT NULL,
  `academic_year` year NOT NULL,
  `semester` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `department_id` int DEFAULT NULL,
  `document_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `is_required` tinyint(1) DEFAULT '1',
  `deadline_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_requirements`
--

INSERT INTO `document_requirements` (`id`, `academic_year`, `semester`, `department_id`, `document_type`, `is_required`, `deadline_date`, `created_at`, `updated_at`, `created_by`) VALUES
(1, '2025', '1st Semester', NULL, 'IPCR Accomplishment', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(2, '2025', '1st Semester', NULL, 'IPCR Target', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(3, '2025', '1st Semester', NULL, 'Workload', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(4, '2025', '1st Semester', NULL, 'Course Syllabus', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(5, '2025', '1st Semester', NULL, 'Course Syllabus Acceptance Form', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(6, '2025', '1st Semester', NULL, 'Exam', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(7, '2025', '1st Semester', NULL, 'TOS', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(8, '2025', '1st Semester', NULL, 'Class Record', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(9, '2025', '1st Semester', NULL, 'Grading Sheets', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(10, '2025', '1st Semester', NULL, 'Attendance Sheet', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(11, '2025', '1st Semester', NULL, 'Stakeholder\'s Feedback Form w/ Summary', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(12, '2025', '1st Semester', NULL, 'Consultation', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(13, '2025', '1st Semester', NULL, 'Lecture', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(14, '2025', '1st Semester', NULL, 'Activities', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(15, '2025', '1st Semester', NULL, 'CEIT-QF-03 Discussion Form', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(16, '2025', '1st Semester', NULL, 'Others', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(17, '2025', '2nd Semester', NULL, 'IPCR Accomplishment', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(18, '2025', '2nd Semester', NULL, 'IPCR Target', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(19, '2025', '2nd Semester', NULL, 'Workload', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(20, '2025', '2nd Semester', NULL, 'Course Syllabus', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(21, '2025', '2nd Semester', NULL, 'Course Syllabus Acceptance Form', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(22, '2025', '2nd Semester', NULL, 'Exam', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(23, '2025', '2nd Semester', NULL, 'TOS', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(24, '2025', '2nd Semester', NULL, 'Class Record', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(25, '2025', '2nd Semester', NULL, 'Grading Sheets', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(26, '2025', '2nd Semester', NULL, 'Attendance Sheet', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(27, '2025', '2nd Semester', NULL, 'Stakeholder\'s Feedback Form w/ Summary', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(28, '2025', '2nd Semester', NULL, 'Consultation', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(29, '2025', '2nd Semester', NULL, 'Lecture', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(30, '2025', '2nd Semester', NULL, 'Activities', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(31, '2025', '2nd Semester', NULL, 'CEIT-QF-03 Discussion Form', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1),
(32, '2025', '2nd Semester', NULL, 'Others', 1, NULL, '2025-08-18 00:53:41', '2025-08-18 00:53:41', 1);

-- --------------------------------------------------------

--
-- Table structure for table `document_status_history`
--

CREATE TABLE `document_status_history` (
  `id` int NOT NULL,
  `request_id` int NOT NULL,
  `status` enum('pending','in_progress','under_review','completed','rejected','cancelled') COLLATE utf8mb4_general_ci NOT NULL,
  `changed_by` int NOT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
) ;

--
-- Triggers `document_status_history`
--
DELIMITER $$
CREATE TRIGGER `tr_document_status_log` AFTER INSERT ON `document_status_history` FOR EACH ROW BEGIN
    INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata)
    VALUES (NEW.changed_by, 'update_document_status', 'document', NEW.request_id, 
            CONCAT('Updated document status to: ', NEW.status),
            JSON_OBJECT('new_status', NEW.status, 'notes', NEW.notes));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `document_submission_history`
--

CREATE TABLE `document_submission_history` (
  `id` int NOT NULL,
  `faculty_id` int NOT NULL,
  `document_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `semester` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('submitted','not_submitted','reminder_sent','note_added') COLLATE utf8mb4_general_ci NOT NULL,
  `note` text COLLATE utf8mb4_general_ci,
  `reminder_sent` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_submission_history`
--

INSERT INTO `document_submission_history` (`id`, `faculty_id`, `document_type`, `semester`, `status`, `note`, `reminder_sent`, `created_at`) VALUES
(3, 28, 'IPCR Accomplishment', '1st Semester', 'submitted', NULL, NULL, '2025-08-18 02:30:45');

-- --------------------------------------------------------

--
-- Table structure for table `document_templates`
--

CREATE TABLE `document_templates` (
  `id` int NOT NULL,
  `template_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `document_type` enum('certificate','clearance','permit','report','form','other') COLLATE utf8mb4_general_ci NOT NULL,
  `template_description` text COLLATE utf8mb4_general_ci,
  `required_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `workflow_steps` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `estimated_completion_hours` int DEFAULT '24',
  `department_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL
) ;

--
-- Dumping data for table `document_templates`
--

INSERT INTO `document_templates` (`id`, `template_name`, `document_type`, `template_description`, `required_fields`, `workflow_steps`, `estimated_completion_hours`, `department_id`, `is_active`, `created_by`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 'Student Certificate', 'certificate', 'Certificate for student records and achievements', '[\"student_id\", \"full_name\", \"course\", \"certificate_type\", \"academic_year\"]', '[\"initial_review\", \"verification\", \"document_preparation\", \"approval\", \"release\"]', 72, NULL, 1, 1, '2025-08-12 09:30:05', '2025-08-12 09:30:05', NULL),
(2, 'Employment Clearance', 'clearance', 'Clearance certificate for employment purposes', '[\"employee_id\", \"full_name\", \"position\", \"department\", \"clearance_type\", \"employment_dates\"]', '[\"request_validation\", \"background_check\", \"department_clearance\", \"final_approval\", \"release\"]', 96, NULL, 1, 1, '2025-08-12 09:30:05', '2025-08-12 09:30:05', NULL),
(3, 'Research Permit', 'permit', 'Permit for conducting research within the institution', '[\"researcher_name\", \"research_title\", \"research_duration\", \"participants\", \"methodology\", \"ethical_approval\"]', '[\"application_review\", \"requirements_check\", \"ethics_review\", \"approval_process\", \"permit_issuance\"]', 120, NULL, 1, 1, '2025-08-12 09:30:05', '2025-08-12 09:30:05', NULL),
(4, 'Transcript of Records', 'certificate', 'Official academic transcript', '[\"student_id\", \"full_name\", \"course\", \"academic_years\", \"purpose\"]', '[\"request_verification\", \"record_compilation\", \"verification\", \"approval\", \"release\"]', 48, NULL, 1, 1, '2025-08-12 09:30:05', '2025-08-12 09:30:05', NULL),
(5, 'Faculty Clearance', 'clearance', 'Clearance for faculty members', '[\"faculty_id\", \"full_name\", \"department\", \"position\", \"clearance_purpose\"]', '[\"request_review\", \"department_verification\", \"library_clearance\", \"property_clearance\", \"final_approval\"]', 72, NULL, 1, 1, '2025-08-12 09:30:05', '2025-08-12 09:30:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_workflows`
--

CREATE TABLE `document_workflows` (
  `id` int NOT NULL,
  `request_id` int NOT NULL,
  `step_number` int NOT NULL,
  `step_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `step_description` text COLLATE utf8mb4_general_ci,
  `assigned_to` int DEFAULT NULL,
  `assigned_department` int DEFAULT NULL,
  `status` enum('pending','in_progress','completed','skipped','failed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `expected_duration_hours` int DEFAULT NULL,
  `actual_duration_minutes` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_document_submissions`
--

CREATE TABLE `faculty_document_submissions` (
  `id` int NOT NULL,
  `faculty_id` int NOT NULL,
  `document_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `semester` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `academic_year` year NOT NULL DEFAULT (year(curdate())),
  `submitted_by` int NOT NULL,
  `submitted_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty_document_submissions`
--

INSERT INTO `faculty_document_submissions` (`id`, `faculty_id`, `document_type`, `semester`, `academic_year`, `submitted_by`, `submitted_at`) VALUES
(28, 28, 'IPCR Accomplishment', '1st Semester', '2025', 28, '2025-08-18 02:30:45');

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_size` bigint DEFAULT NULL,
  `file_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_extension` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uploaded_by` int NOT NULL,
  `folder_id` int NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `file_hash` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `download_count` int DEFAULT '0',
  `last_downloaded` datetime DEFAULT NULL,
  `last_downloaded_by` int DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT '0',
  `public_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `version` int DEFAULT '1',
  `parent_file_id` int DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `description` text COLLATE utf8mb4_general_ci,
  `academic_year` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Academic year, e.g. 2026',
  `semester` enum('first','second') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'first',
  `thumbnail_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_favorite` tinyint(1) DEFAULT '0',
  `expiry_date` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `file_name`, `original_name`, `file_path`, `file_size`, `file_type`, `mime_type`, `file_extension`, `uploaded_by`, `folder_id`, `uploaded_at`, `updated_at`, `is_deleted`, `deleted_at`, `deleted_by`, `file_hash`, `download_count`, `last_downloaded`, `last_downloaded_by`, `is_public`, `public_token`, `permissions`, `version`, `parent_file_id`, `tags`, `description`, `academic_year`, `semester`, `thumbnail_path`, `is_favorite`, `expiry_date`) VALUES
(18, '1719775504_summary_form.docx', 'NAIC_QF_xxxx_Summary-of-Comments-and-Action-Taken-Form.docx', 'uploads/1719775504_summary_form.docx', 45678, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 28, 47, '2025-06-30 23:25:04', '2026-10-04 00:20:08', 0, NULL, NULL, 'abc123def456', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Quality assurance summary form', '2025', 'first', NULL, 0, NULL),
(19, '1719775992_users_backup.sql', 'users.sql', 'uploads/1719775992_users_backup.sql', 8945, 'database', 'application/sql', 'sql', 29, 47, '2025-06-30 23:33:12', '2026-10-04 00:20:08', 0, NULL, NULL, 'def456ghi789', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Database backup file', '2025', 'first', NULL, 0, NULL),
(20, '1719832532_contribution_doc.docx', 'WITH CONTRIBUTION NUMBER.docx', 'uploads/1719832532_contribution_doc.docx', 234567, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 30, 51, '2025-07-01 11:55:32', '2026-10-04 00:20:08', 0, NULL, NULL, 'ghi789jkl012', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Research contribution document', '2025', 'first', NULL, 0, NULL),
(21, '1719832561_admin_dashboard.php', 'admindashboard.php', 'uploads/1719832561_admin_dashboard.php', 15678, 'code', 'application/x-php', 'php', 30, 52, '2025-07-01 11:56:01', '2026-10-04 08:41:15', 1, '2026-10-04 08:41:15', 30, 'jkl012mno345', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Admin dashboard source code', '2025', 'first', NULL, 0, NULL),
(22, '1719832585_authorization_letter.docx', 'AUTHORIZATION LETTER.docx', 'uploads/1719832585_authorization_letter.docx', 67890, 'document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 28, 46, '2025-07-01 11:56:25', '2026-10-04 00:20:08', 0, NULL, NULL, 'mno345pqr678', 0, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, 'Official authorization letter', '2025', 'first', NULL, 0, NULL),
(23, '6ebe041c9bb42fa261aed59cdf4bebbb.pdf', 'receipt_JYS-261001-9543 (2).pdf', 'uploads/departments/3/ipcr_accomplishment/first/2026-2027/6ebe041c9bb42fa261aed59cdf4bebbb.pdf', 46021, 'document', 'application/pdf', 'pdf', 30, 53, '2026-10-04 08:20:57', '2026-10-04 08:20:57', 0, NULL, NULL, NULL, 0, NULL, NULL, 0, NULL, NULL, 1, NULL, '[\"Curriculum\"]', '', '2026-2027', 'first', NULL, 0, NULL),
(24, 'cbe0774f3b868de7d996e342a751993c.pdf', 'receipt_JYS-261001-9543 (2).pdf', 'uploads/departments/3/ipcr_accomplishment/first/2026-2027/cbe0774f3b868de7d996e342a751993c.pdf', 46021, 'document', 'application/pdf', 'pdf', 30, 53, '2026-10-04 08:21:54', '2026-10-04 08:21:54', 0, NULL, NULL, NULL, 0, NULL, NULL, 0, NULL, NULL, 1, NULL, '[\"Curriculum\"]', '', '2026-2027', 'first', NULL, 0, NULL),
(25, '2deda2e2041a5f014de33e1554464cd1.pdf', 'receipt_JYS-261001-9543 (2).pdf', 'uploads/departments/3/ipcr_accomplishment/first/2023-2024/2deda2e2041a5f014de33e1554464cd1.pdf', 46021, 'document', 'application/pdf', 'pdf', 30, 57, '2026-10-04 08:24:31', '2026-10-04 08:24:31', 0, NULL, NULL, NULL, 0, NULL, NULL, 0, NULL, NULL, 1, NULL, '[\"Curriculum\",\"Research\"]', '', '2023-2024', 'first', NULL, 0, NULL),
(26, '9ff16f71ffda9f43e950dd5d81acf49f.pdf', 'Angelito_Decatoria_Resume_A4 (1).pdf', 'uploads/departments/3/exam_acknowledgement/first/2026-2027/9ff16f71ffda9f43e950dd5d81acf49f.pdf', 55835, 'document', 'application/pdf', 'pdf', 30, 61, '2026-10-04 08:32:59', '2026-10-04 09:03:13', 0, NULL, NULL, NULL, 1, '2026-10-04 09:03:13', 27, 0, NULL, NULL, 1, NULL, '[\"Curriculum\"]', '', '2026-2027', 'first', NULL, 0, NULL);

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
  `id` int NOT NULL,
  `file_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci NOT NULL,
  `parent_comment_id` int DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
-- Table structure for table `file_downloads`
--

CREATE TABLE `file_downloads` (
  `id` bigint UNSIGNED NOT NULL,
  `file_id` int NOT NULL,
  `user_id` int NOT NULL,
  `downloaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `file_downloads`
--

INSERT INTO `file_downloads` (`id`, `file_id`, `user_id`, `downloaded_at`, `user_ip`) VALUES
(1, 26, 27, '2026-10-04 01:03:13', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `file_shares`
--

CREATE TABLE `file_shares` (
  `id` int NOT NULL,
  `file_id` int NOT NULL,
  `shared_by` int NOT NULL,
  `shared_with` int DEFAULT NULL,
  `share_token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `share_type` enum('user','public','department') COLLATE utf8mb4_general_ci DEFAULT 'user',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `expires_at` datetime DEFAULT NULL,
  `password_protected` tinyint(1) DEFAULT '0',
  `share_password` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `download_limit` int DEFAULT NULL,
  `download_count` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_accessed` datetime DEFAULT NULL
) ;

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
  `id` int NOT NULL,
  `folder_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `parent_id` int DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `folder_path` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `folder_level` int DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '0',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `folder_size` bigint DEFAULT '0',
  `file_count` int DEFAULT '0',
  `folder_color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#667eea',
  `folder_icon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'fa-folder',
  `category` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `folder_type` enum('category','custom','system') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'custom',
  `folder_status` enum('active','archived','hidden') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `access_count` int NOT NULL DEFAULT '0',
  `is_favorite` tinyint(1) NOT NULL DEFAULT '0'
) ;

--
-- Dumping data for table `folders`
--

INSERT INTO `folders` (`id`, `folder_name`, `description`, `created_by`, `created_at`, `updated_at`, `parent_id`, `is_deleted`, `deleted_at`, `deleted_by`, `department_id`, `folder_path`, `folder_level`, `is_public`, `permissions`, `folder_size`, `file_count`, `folder_color`, `folder_icon`, `category`, `folder_type`, `folder_status`, `access_count`, `is_favorite`) VALUES
(44, 'IPCR', 'Individual Performance Commitment and Review documents', 27, '2025-08-07 01:55:15', '2026-10-04 10:31:03', NULL, 0, NULL, NULL, 3, '/IPCR', 0, 0, NULL, 0, 0, '#667eea', 'fa-chart-line', NULL, 'category', 'active', 0, 0),
(45, 'Minutes', 'Meeting minutes and proceedings', 27, '2025-08-07 01:55:15', '2026-10-04 00:20:08', NULL, 0, NULL, NULL, 3, '/Minutes', 0, 0, NULL, 0, 0, '#38a169', 'fa-file-alt', NULL, 'category', 'active', 0, 0),
(46, 'DTR', 'Daily Time Records', 27, '2025-08-07 01:55:15', '2026-10-04 00:20:08', NULL, 0, NULL, NULL, 3, '/DTR', 0, 0, NULL, 0, 0, '#ed8936', 'fa-clock', NULL, 'category', 'active', 0, 0),
(47, 'Attendance', 'Student and faculty attendance records', 27, '2025-08-07 01:55:15', '2026-10-04 00:20:08', NULL, 0, NULL, NULL, 3, '/Attendance', 0, 0, NULL, 0, 0, '#9f7aea', 'fa-users', NULL, 'category', 'active', 0, 0),
(48, 'Syllabus', 'Course syllabi and curriculum documents', 27, '2025-08-07 01:55:15', '2026-10-04 08:41:05', NULL, 1, '2026-10-04 08:41:05', 30, 3, '/Syllabus', 0, 1, NULL, 0, 0, '#4299e1', 'fa-book', NULL, 'category', 'active', 0, 0),
(49, 'Lecture Notes', 'Teaching materials and lecture notes', 27, '2025-08-07 01:55:15', '2026-10-04 08:41:02', NULL, 1, '2026-10-04 08:41:02', 30, 3, '/Lecture Notes', 0, 1, NULL, 0, 0, '#48bb78', 'fa-graduation-cap', NULL, 'category', 'active', 0, 0),
(50, 'Grading Sheets', 'Student grades and assessment records', 27, '2025-08-07 01:55:15', '2026-10-04 00:20:08', NULL, 0, NULL, NULL, 3, '/Grading Sheets', 0, 0, NULL, 0, 0, '#e53e3e', 'fa-table', NULL, 'category', 'active', 0, 0),
(51, 'Exams', 'Examination papers and answer keys', 27, '2025-08-07 01:55:15', '2026-10-04 00:20:08', NULL, 0, NULL, NULL, 3, '/Exams', 0, 0, NULL, 0, 0, '#d69e2e', 'fa-file-signature', NULL, 'category', 'active', 0, 0),
(52, 'Other Course Materials', 'Additional teaching and learning resources', 27, '2025-08-07 01:55:15', '2026-10-04 08:41:15', NULL, 1, '2026-10-04 08:41:15', 30, 3, '/Other Course Materials', 0, 1, NULL, 550978, 8, '#805ad5', 'fa-folder-open', NULL, 'category', 'active', 0, 0),
(53, '2026-2027 - First Semester', 'Academic files for First Semester 2026-2027', 30, '2026-10-04 08:20:57', '2026-10-04 08:33:39', NULL, 0, NULL, NULL, 3, '/departments/3/ipcr_accomplishment/first/2026-2027', 2, 0, NULL, 464800, 7, '#10b981', 'bxs-folder', 'ipcr_accomplishment', 'category', 'active', 0, 0),
(54, 'First Semester', 'First Semester files for Lecture Notes', 27, '2026-10-04 08:21:16', '2026-10-04 08:41:02', 49, 1, '2026-10-04 08:41:02', 30, 3, '/Lecture Notes/first', 1, 1, NULL, 0, 0, '#48bb78', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(55, 'First Semester', 'First Semester files for Syllabus', 27, '2026-10-04 08:21:20', '2026-10-04 08:41:05', 48, 1, '2026-10-04 08:41:05', 30, 3, '/Syllabus/first', 1, 1, NULL, 0, 0, '#4299e1', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(56, 'Second Semester', 'Second Semester files for Lecture Notes', 27, '2026-10-04 08:23:35', '2026-10-04 08:41:02', 49, 1, '2026-10-04 08:41:02', 30, 3, '/Lecture Notes/second', 1, 1, NULL, 0, 0, '#48bb78', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(57, '2023-2024 - First Semester', 'Academic files for First Semester 2023-2024', 30, '2026-10-04 08:24:31', '2026-10-04 08:33:39', NULL, 0, NULL, NULL, 3, '/departments/3/ipcr_accomplishment/first/2023-2024', 2, 0, NULL, 510821, 8, '#10b981', 'bxs-folder', 'ipcr_accomplishment', 'category', 'active', 0, 0),
(58, 'Second Semester', 'Second Semester files for Syllabus', 27, '2026-10-04 08:24:41', '2026-10-04 08:41:05', 48, 1, '2026-10-04 08:41:05', 30, 3, '/Syllabus/second', 1, 1, NULL, 0, 0, '#4299e1', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(59, 'First Semester', 'First Semester files for Other Course Materials', 27, '2026-10-04 08:31:58', '2026-10-04 08:41:15', 52, 1, '2026-10-04 08:41:15', 30, 3, '/Other Course Materials/first', 1, 1, NULL, 0, 0, '#805ad5', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(60, 'Second Semester', 'Second Semester files for Other Course Materials', 27, '2026-10-04 08:31:59', '2026-10-04 08:41:15', 52, 1, '2026-10-04 08:41:15', 30, 3, '/Other Course Materials/second', 1, 1, NULL, 0, 0, '#805ad5', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(61, '2026-2027 - First Semester', 'Academic files for First Semester 2026-2027', 30, '2026-10-04 08:32:59', '2026-10-04 08:33:39', NULL, 0, NULL, NULL, 3, '/departments/3/exam_acknowledgement/first/2026-2027', 2, 0, NULL, 566656, 9, '#10b981', 'bxs-folder', 'exam_acknowledgement', 'category', 'active', 0, 0),
(62, 'First Semester', 'First Semester files for IPCR', 27, '2026-10-04 08:36:38', '2026-10-04 08:45:28', 44, 0, NULL, NULL, 3, '/IPCR/first', 1, 0, NULL, 0, 0, '#667eea', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(63, 'Second Semester', 'Second Semester files for IPCR', 27, '2026-10-04 08:36:39', '2026-10-04 08:45:28', 44, 0, NULL, NULL, 3, '/IPCR/second', 1, 0, NULL, 0, 0, '#667eea', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(64, 'First Semester', 'First Semester files for Attendance', 27, '2026-10-04 08:36:40', '2026-10-04 08:45:28', 47, 0, NULL, NULL, 3, '/Attendance/first', 1, 0, NULL, 0, 0, '#9f7aea', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(65, 'First Semester', 'First Semester files for Minutes', 27, '2026-10-04 08:36:43', '2026-10-04 08:45:28', 45, 0, NULL, NULL, 3, '/Minutes/first', 1, 0, NULL, 0, 0, '#38a169', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(66, 'First Semester', 'First Semester files for DTR', 27, '2026-10-04 08:36:43', '2026-10-04 08:45:28', 46, 0, NULL, NULL, 3, '/DTR/first', 1, 0, NULL, 0, 0, '#ed8936', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(67, 'First Semester', 'First Semester files for Grading Sheets', 27, '2026-10-04 08:36:45', '2026-10-04 08:45:28', 50, 0, NULL, NULL, 3, '/Grading Sheets/first', 1, 0, NULL, 0, 0, '#e53e3e', 'bxs-folder', NULL, 'category', 'active', 0, 0),
(68, 'First Semester', 'First Semester files for Exams', 27, '2026-10-04 08:36:46', '2026-10-04 08:45:28', 51, 0, NULL, NULL, 3, '/Exams/first', 1, 0, NULL, 0, 0, '#d69e2e', 'bxs-folder', NULL, 'category', 'active', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `folder_permissions`
--

CREATE TABLE `folder_permissions` (
  `id` int NOT NULL,
  `folder_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `role` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `permission_type` enum('read','write','admin') COLLATE utf8mb4_general_ci DEFAULT 'read',
  `granted_by` int NOT NULL,
  `granted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1'
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
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `sender_id` int NOT NULL,
  `recipient_id` int NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `message_type` enum('general','reminder','announcement','warning','system') DEFAULT 'general',
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_templates`
--

CREATE TABLE `message_templates` (
  `id` int NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `template_type` enum('general','reminder','announcement','warning') DEFAULT 'general',
  `is_system` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `type` enum('info','success','warning','error') COLLATE utf8mb4_general_ci DEFAULT 'info',
  `action_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action_text` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
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
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `content_type` enum('text','image','file','link','mixed') COLLATE utf8mb4_general_ci DEFAULT 'text',
  `visibility` enum('public','department','private','custom') COLLATE utf8mb4_general_ci DEFAULT 'public',
  `target_departments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `target_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_general_ci DEFAULT 'normal',
  `is_pinned` tinyint(1) DEFAULT '0',
  `is_edited` tinyint(1) DEFAULT '0',
  `edited_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `like_count` int DEFAULT '0',
  `comment_count` int DEFAULT '0',
  `view_count` int DEFAULT '0',
  `share_count` int DEFAULT '0'
) ;

-- --------------------------------------------------------

--
-- Table structure for table `post_comments`
--

CREATE TABLE `post_comments` (
  `id` int NOT NULL,
  `post_id` int NOT NULL,
  `user_id` int NOT NULL,
  `parent_comment_id` int DEFAULT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_edited` tinyint(1) DEFAULT '0',
  `edited_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `like_count` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `post_comments`
--
DELIMITER $$
CREATE TRIGGER `tr_post_comment_insert` AFTER INSERT ON `post_comments` FOR EACH ROW BEGIN
    UPDATE posts 
    SET comment_count = (
        SELECT COUNT(*) FROM post_comments 
        WHERE post_id = NEW.post_id AND is_deleted = 0
    )
    WHERE id = NEW.post_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_post_comment_update` AFTER UPDATE ON `post_comments` FOR EACH ROW BEGIN
    IF OLD.is_deleted != NEW.is_deleted THEN
        UPDATE posts 
        SET comment_count = (
            SELECT COUNT(*) FROM post_comments 
            WHERE post_id = NEW.post_id AND is_deleted = 0
        )
        WHERE id = NEW.post_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `post_likes`
--

CREATE TABLE `post_likes` (
  `id` int NOT NULL,
  `post_id` int DEFAULT NULL,
  `comment_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `reaction_type` enum('like','love','laugh','angry','sad','wow') COLLATE utf8mb4_general_ci DEFAULT 'like',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `post_likes`
--
DELIMITER $$
CREATE TRIGGER `tr_post_like_delete` AFTER DELETE ON `post_likes` FOR EACH ROW BEGIN
    IF OLD.post_id IS NOT NULL THEN
        UPDATE posts 
        SET like_count = (
            SELECT COUNT(*) FROM post_likes 
            WHERE post_id = OLD.post_id
        )
        WHERE id = OLD.post_id;
    ELSEIF OLD.comment_id IS NOT NULL THEN
        UPDATE post_comments 
        SET like_count = (
            SELECT COUNT(*) FROM post_likes 
            WHERE comment_id = OLD.comment_id
        )
        WHERE id = OLD.comment_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_post_like_insert` AFTER INSERT ON `post_likes` FOR EACH ROW BEGIN
    IF NEW.post_id IS NOT NULL THEN
        UPDATE posts 
        SET like_count = (
            SELECT COUNT(*) FROM post_likes 
            WHERE post_id = NEW.post_id
        )
        WHERE id = NEW.post_id;
    ELSEIF NEW.comment_id IS NOT NULL THEN
        UPDATE post_comments 
        SET like_count = (
            SELECT COUNT(*) FROM post_likes 
            WHERE comment_id = NEW.comment_id
        )
        WHERE id = NEW.comment_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `post_media`
--

CREATE TABLE `post_media` (
  `id` int NOT NULL,
  `post_id` int NOT NULL,
  `media_type` enum('image','file','link') COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `url` varchar(1000) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `url_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `url_description` text COLLATE utf8mb4_general_ci,
  `url_image` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `thumbnail_path` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_notifications`
--

CREATE TABLE `post_notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `post_id` int DEFAULT NULL,
  `comment_id` int DEFAULT NULL,
  `triggered_by` int NOT NULL,
  `notification_type` enum('new_post','post_comment','post_like','comment_like','comment_reply','post_mention','comment_mention') COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_reports`
--

CREATE TABLE `post_reports` (
  `id` int NOT NULL,
  `post_id` int DEFAULT NULL,
  `comment_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `report_reason` enum('spam','harassment','inappropriate','other') COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `status` enum('pending','reviewed','resolved','dismissed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_saves`
--

CREATE TABLE `post_saves` (
  `id` int NOT NULL,
  `post_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_shares`
--

CREATE TABLE `post_shares` (
  `id` int NOT NULL,
  `post_id` int NOT NULL,
  `user_id` int NOT NULL,
  `share_type` enum('internal','external','copy_link') COLLATE utf8mb4_general_ci DEFAULT 'internal',
  `shared_with` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `message` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Triggers `post_shares`
--
DELIMITER $$
CREATE TRIGGER `tr_post_share_insert` AFTER INSERT ON `post_shares` FOR EACH ROW BEGIN
    UPDATE posts 
    SET share_count = (
        SELECT COUNT(*) FROM post_shares 
        WHERE post_id = NEW.post_id
    )
    WHERE id = NEW.post_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `post_views`
--

CREATE TABLE `post_views` (
  `id` int NOT NULL,
  `post_id` int NOT NULL,
  `user_id` int NOT NULL,
  `viewed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `post_views`
--
DELIMITER $$
CREATE TRIGGER `tr_post_view_insert` AFTER INSERT ON `post_views` FOR EACH ROW BEGIN
    UPDATE posts 
    SET view_count = (
        SELECT COUNT(*) FROM post_views 
        WHERE post_id = NEW.post_id
    )
    WHERE id = NEW.post_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci,
  `setting_type` enum('string','integer','boolean','json') COLLATE utf8mb4_general_ci DEFAULT 'string',
  `description` text COLLATE utf8mb4_general_ci,
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL
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
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','user','super_admin') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user',
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `mi` varchar(5) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `surname` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `employee_id` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `is_restricted` tinyint(1) DEFAULT '0',
  `profile_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `date_of_birth` date DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_login_attempts` int DEFAULT '0',
  `account_locked_until` datetime DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT '0',
  `email_verification_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_reset_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_approved`, `name`, `mi`, `surname`, `employee_id`, `position`, `department_id`, `is_restricted`, `profile_image`, `phone`, `address`, `date_of_birth`, `hire_date`, `last_login`, `failed_login_attempts`, `account_locked_until`, `email_verified`, `email_verification_token`, `password_reset_token`, `password_reset_expires`, `created_at`, `updated_at`, `created_by`, `approved_by`, `approved_at`) VALUES
(1, 'superadmin', 'admin@cvsu.edu.ph', '$2y$10$CND6crKqAbFCjdqd47cQnOdXZ.j5SAoyCBAgL3vSj14oEKAvXcqfe', 'super_admin', 1, 'System', '', 'Administrator', 'ADMIN001', 'System Administrator', NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-10-04 10:28:46', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2026-10-04 10:28:46', NULL, NULL, NULL),
(27, 'itdadmin', 'itdadmin@cvsu.edu.ph', '$2y$10$XybfWU2n.DqnWT8NVcZ7P.ynFH83OaXuf.f315vA93hEq8GIQQHGm', 'admin', 1, 'ITD', '', 'Administrator', 'ITD001', 'Department Administrator', 3, 0, NULL, NULL, NULL, NULL, NULL, '2026-10-04 10:30:03', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2026-10-04 10:30:03', NULL, 1, '2025-08-07 01:55:15'),
(28, 'hbalanza', 'henry.balanza@cvsu.edu.ph', '$2y$10$kRVE4pnSIX6YvPrVGbMrX.x.0P1pnPsGTpr4P65xChwbJe4Mwedey', 'user', 1, 'Henry', 'R', 'Balanza', 'ITD002', 'Assistant Professor', 3, 0, NULL, NULL, NULL, NULL, NULL, '2025-08-18 01:41:39', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-18 01:41:39', NULL, 27, '2025-08-07 01:55:15'),
(29, 'mtimola', 'luigi.timola@cvsu.edu.ph', '$2y$10$MsFgHnS.lnHydgR59AS6ue.L5CtPU7w86jwkyo.C21/dqCFnSgblm', 'user', 1, 'Marc Luigi', 'G', 'Timola', 'ITD003', 'Associate Professor', 3, 0, NULL, NULL, NULL, NULL, NULL, '2026-10-04 10:22:43', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2026-10-04 10:22:43', NULL, 27, '2025-08-07 01:55:15'),
(30, 'rriel', 'rj.riel@cvsu.edu.ph', '$2y$10$gnCATAg8c7Extvgzj5DIceeFIWQguzwx0/g0tVjAnz0GoMn1/1J4m', 'user', 1, 'Ricky Jay', 'A', 'Riel', 'ITD004', 'Instructor', 3, 0, 'uploads/profiles/profile_30_1791075938.jpg', NULL, NULL, NULL, NULL, '2026-10-04 10:15:15', 0, NULL, 1, NULL, NULL, NULL, '2025-08-07 01:55:15', '2026-10-04 10:15:15', NULL, 27, '2025-08-07 01:55:15'),
(31, 'pending_user', 'pending@cvsu.edu.ph', '$2y$10$9ldd1yiEVKJAyXubZ.GLc.ZFkMjkP.j4XqL7VRdBbAR8k2aKXKEI.', 'user', 0, 'John', 'A', 'Doe', 'ITD005', 'Instructor', 3, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, '2025-08-07 01:55:15', '2025-08-07 01:55:15', NULL, NULL, NULL),
(32, 'asd', 'asd@gmail.com', '$2y$10$6YqvkgGXksgDvO5jsbBxv.ahCkx0vx4oyoTsKDqJoSOHKLgFxgnxO', 'user', 1, 'asd', 's', 'asd', 'ITD123', '123wsd', 3, 0, NULL, '9626091407', 'asdasdasd', '2003-12-09', NULL, '2025-08-07 15:50:29', 0, NULL, 0, '71e2fb099ead6dc85a091d2d06e754f5446a6a874357dca410d3e572a1496398', NULL, NULL, '2025-08-07 15:15:15', '2025-08-07 15:50:29', NULL, 1, '2025-08-07 15:16:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_follows`
--

CREATE TABLE `user_follows` (
  `id` int NOT NULL,
  `follower_id` int NOT NULL,
  `following_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_admin_submission_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_admin_submission_stats` (
`academic_year` year
,`completion_percentage` decimal(26,2)
,`department_code` varchar(10)
,`department_name` varchar(100)
,`required_doc_types` bigint
,`semester` varchar(50)
,`submitted_docs` bigint
,`total_faculty` bigint
,`total_required_submissions` bigint
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_announcements_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_announcements_detailed` (
`announcement_type` enum('general','department','urgent','maintenance')
,`content` text
,`created_at` datetime
,`created_by_username` varchar(50)
,`creator_full_name` varchar(208)
,`expires_at` datetime
,`id` int
,`image_path` varchar(255)
,`is_pinned` tinyint(1)
,`is_published` tinyint(1)
,`priority` enum('low','normal','high','urgent')
,`published_at` datetime
,`summary` varchar(500)
,`title` varchar(255)
,`view_count` int
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_comments_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_comments_detailed` (
`commenter_full_name` varchar(208)
,`content` text
,`created_at` datetime
,`deleted_at` datetime
,`deleted_by` int
,`deleted_by_username` varchar(50)
,`department_code` varchar(10)
,`edited_at` datetime
,`id` int
,`is_deleted` tinyint(1)
,`is_edited` tinyint(1)
,`like_count` int
,`mi` varchar(5)
,`name` varchar(100)
,`parent_comment_id` int
,`position` varchar(100)
,`post_id` int
,`profile_image` varchar(255)
,`surname` varchar(100)
,`updated_at` datetime
,`user_id` int
,`username` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_document_requests_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_document_requests_detailed` (
`actual_completion` datetime
,`assigned_to` int
,`assigned_to_name` varchar(208)
,`attachment_count` bigint
,`comment_count` bigint
,`created_at` datetime
,`deleted_at` datetime
,`deleted_by` int
,`description` text
,`document_type` enum('certificate','clearance','permit','report','form','other')
,`expected_completion` date
,`id` int
,`is_deleted` tinyint(1)
,`metadata` longtext
,`priority` enum('low','normal','high','urgent')
,`requester_department` varchar(100)
,`requester_email` varchar(255)
,`requester_full_name` varchar(208)
,`requester_name` varchar(50)
,`status` enum('pending','in_progress','under_review','completed','rejected','cancelled')
,`target_department` int
,`target_dept_code` varchar(10)
,`target_dept_name` varchar(100)
,`title` varchar(255)
,`tracking_code` varchar(20)
,`updated_at` datetime
,`updated_by` int
,`updated_by_name` varchar(208)
,`user_id` int
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_files_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_files_detailed` (
`download_count` int
,`file_extension` varchar(10)
,`file_name` varchar(255)
,`file_size` bigint
,`file_type` varchar(100)
,`folder_department` varchar(10)
,`folder_name` varchar(100)
,`folder_path` varchar(500)
,`id` int
,`is_deleted` tinyint(1)
,`mime_type` varchar(100)
,`original_name` varchar(255)
,`uploaded_at` datetime
,`uploaded_by_username` varchar(50)
,`uploader_full_name` varchar(208)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_folders_hierarchy`
-- (See below for the actual view)
--
CREATE TABLE `v_folders_hierarchy` (
`created_at` datetime
,`created_by_username` varchar(50)
,`creator_full_name` varchar(208)
,`department_code` varchar(10)
,`department_name` varchar(100)
,`file_count` int
,`folder_color` varchar(7)
,`folder_icon` varchar(50)
,`folder_level` int
,`folder_name` varchar(100)
,`folder_path` varchar(500)
,`folder_size` bigint
,`id` int
,`is_public` tinyint(1)
,`parent_folder_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_posts_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_posts_detailed` (
`author_full_name` varchar(208)
,`comment_count` int
,`content` text
,`content_type` enum('text','image','file','link','mixed')
,`created_at` datetime
,`deleted_at` datetime
,`deleted_by` int
,`deleted_by_username` varchar(50)
,`department_code` varchar(10)
,`department_name` varchar(100)
,`edited_at` datetime
,`id` int
,`is_deleted` tinyint(1)
,`is_edited` tinyint(1)
,`is_pinned` tinyint(1)
,`like_count` int
,`mi` varchar(5)
,`name` varchar(100)
,`position` varchar(100)
,`priority` enum('low','normal','high','urgent')
,`profile_image` varchar(255)
,`share_count` int
,`surname` varchar(100)
,`target_departments` longtext
,`target_users` longtext
,`updated_at` datetime
,`user_id` int
,`username` varchar(50)
,`view_count` int
,`visibility` enum('public','department','private','custom')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_submission_tracker`
-- (See below for the actual view)
--
CREATE TABLE `v_submission_tracker` (
`academic_year` year
,`department_code` varchar(10)
,`department_id` int
,`department_name` varchar(100)
,`document_type` varchar(100)
,`file_count` bigint
,`first_upload` datetime
,`latest_upload` datetime
,`name` varchar(100)
,`semester` varchar(50)
,`status` varchar(12)
,`submitted_at` datetime
,`submitted_by` int
,`surname` varchar(100)
,`total_size` decimal(32,0)
,`user_id` int
,`username` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_users_detailed`
-- (See below for the actual view)
--
CREATE TABLE `v_users_detailed` (
`approved_at` datetime
,`approved_by_username` varchar(50)
,`created_at` datetime
,`department_code` varchar(10)
,`department_name` varchar(100)
,`email` varchar(255)
,`employee_id` varchar(20)
,`full_name` varchar(208)
,`id` int
,`is_approved` tinyint(1)
,`is_restricted` tinyint(1)
,`last_login` datetime
,`mi` varchar(5)
,`name` varchar(100)
,`position` varchar(100)
,`role` enum('admin','user','super_admin')
,`surname` varchar(100)
,`username` varchar(50)
);

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
-- Indexes for table `document_attachments`
--
ALTER TABLE `document_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `deleted_by` (`deleted_by`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_uploaded_at` (`uploaded_at`);

--
-- Indexes for table `document_comments`
--
ALTER TABLE `document_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_comment_id` (`parent_comment_id`),
  ADD KEY `deleted_by` (`deleted_by`),
  ADD KEY `idx_comment_type` (`comment_type`),
  ADD KEY `idx_is_internal` (`is_internal`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `document_files`
--
ALTER TABLE `document_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_document_files_period` (`uploaded_by`,`academic_year`,`semester_period`),
  ADD KEY `idx_files_year_semester_type` (`academic_year`,`semester_period`,`file_type`),
  ADD KEY `idx_document_files_submission` (`submission_id`);

--
-- Indexes for table `document_notifications`
--
ALTER TABLE `document_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_notification_type` (`notification_type`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_code` (`tracking_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `target_department` (`target_department`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `deleted_by` (`deleted_by`),
  ADD KEY `idx_document_status` (`status`),
  ADD KEY `idx_document_type` (`document_type`),
  ADD KEY `idx_document_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_expected_completion` (`expected_completion`),
  ADD KEY `idx_is_deleted` (`is_deleted`);
ALTER TABLE `document_requests` ADD FULLTEXT KEY `document_search` (`title`,`description`);

--
-- Indexes for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_requirement` (`academic_year`,`semester`,`department_id`,`document_type`),
  ADD KEY `idx_year_semester` (`academic_year`,`semester`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `document_requirements_created_by_fk` (`created_by`);

--
-- Indexes for table `document_status_history`
--
ALTER TABLE `document_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_status_changed_at` (`changed_at`);

--
-- Indexes for table `document_submission_history`
--
ALTER TABLE `document_submission_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `document_type` (`document_type`),
  ADD KEY `semester` (`semester`);

--
-- Indexes for table `document_templates`
--
ALTER TABLE `document_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_document_type` (`document_type`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `document_workflows`
--
ALTER TABLE `document_workflows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `assigned_department` (`assigned_department`),
  ADD KEY `idx_step_number` (`step_number`),
  ADD KEY `idx_workflow_status` (`status`);

--
-- Indexes for table `faculty_document_submissions`
--
ALTER TABLE `faculty_document_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`faculty_id`,`document_type`,`semester`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `idx_faculty_year_semester` (`faculty_id`,`academic_year`,`semester`),
  ADD KEY `idx_submissions_year_semester_user` (`academic_year`,`semester`,`faculty_id`),
  ADD KEY `idx_faculty_submissions_composite` (`faculty_id`,`document_type`,`semester`,`academic_year`);

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
  ADD KEY `idx_files_file_type_size` (`file_type`,`file_size`),
  ADD KEY `idx_files_user_period` (`uploaded_by`,`academic_year`,`semester`,`is_deleted`);
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
-- Indexes for table `file_downloads`
--
ALTER TABLE `file_downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file_downloads_file` (`file_id`),
  ADD KEY `idx_file_downloads_user` (`user_id`),
  ADD KEY `idx_file_downloads_date` (`downloaded_at`);

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
  ADD KEY `idx_folders_created_at` (`created_at`),
  ADD KEY `idx_folders_type` (`folder_type`);
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
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient_read` (`recipient_id`,`is_read`),
  ADD KEY `idx_sender_time` (`sender_id`,`sent_at`),
  ADD KEY `idx_conversation` (`sender_id`,`recipient_id`,`sent_at`);

--
-- Indexes for table `message_templates`
--
ALTER TABLE `message_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posts_user_fk` (`user_id`),
  ADD KEY `posts_deleted_by_fk` (`deleted_by`),
  ADD KEY `idx_posts_created_at` (`created_at`),
  ADD KEY `idx_posts_visibility` (`visibility`),
  ADD KEY `idx_posts_pinned` (`is_pinned`),
  ADD KEY `idx_posts_deleted` (`is_deleted`);

--
-- Indexes for table `post_comments`
--
ALTER TABLE `post_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_comments_post_fk` (`post_id`),
  ADD KEY `post_comments_user_fk` (`user_id`),
  ADD KEY `post_comments_parent_fk` (`parent_comment_id`),
  ADD KEY `post_comments_deleted_by_fk` (`deleted_by`),
  ADD KEY `idx_post_comments_created_at` (`created_at`),
  ADD KEY `idx_post_comments_deleted` (`is_deleted`);

--
-- Indexes for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_post_like` (`post_id`,`user_id`),
  ADD UNIQUE KEY `unique_comment_like` (`comment_id`,`user_id`),
  ADD KEY `post_likes_post_fk` (`post_id`),
  ADD KEY `post_likes_comment_fk` (`comment_id`),
  ADD KEY `post_likes_user_fk` (`user_id`),
  ADD KEY `idx_post_likes_created_at` (`created_at`);

--
-- Indexes for table `post_media`
--
ALTER TABLE `post_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_media_post_fk` (`post_id`),
  ADD KEY `idx_post_media_type` (`media_type`);

--
-- Indexes for table `post_notifications`
--
ALTER TABLE `post_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_notifications_user_fk` (`user_id`),
  ADD KEY `post_notifications_post_fk` (`post_id`),
  ADD KEY `post_notifications_comment_fk` (`comment_id`),
  ADD KEY `post_notifications_triggered_by_fk` (`triggered_by`),
  ADD KEY `idx_post_notifications_read` (`is_read`),
  ADD KEY `idx_post_notifications_created_at` (`created_at`);

--
-- Indexes for table `post_reports`
--
ALTER TABLE `post_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `post_saves`
--
ALTER TABLE `post_saves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_save` (`post_id`,`user_id`);

--
-- Indexes for table `post_shares`
--
ALTER TABLE `post_shares`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_shares_post_fk` (`post_id`),
  ADD KEY `post_shares_user_fk` (`user_id`),
  ADD KEY `idx_post_shares_created_at` (`created_at`);

--
-- Indexes for table `post_views`
--
ALTER TABLE `post_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_post_view` (`post_id`,`user_id`),
  ADD KEY `post_views_post_fk` (`post_id`),
  ADD KEY `post_views_user_fk` (`user_id`);

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
-- Indexes for table `user_follows`
--
ALTER TABLE `user_follows`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_follow` (`follower_id`,`following_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcement_views`
--
ALTER TABLE `announcement_views`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `document_attachments`
--
ALTER TABLE `document_attachments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_comments`
--
ALTER TABLE `document_comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_files`
--
ALTER TABLE `document_files`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `document_notifications`
--
ALTER TABLE `document_notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_requirements`
--
ALTER TABLE `document_requirements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `document_status_history`
--
ALTER TABLE `document_status_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_submission_history`
--
ALTER TABLE `document_submission_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_templates`
--
ALTER TABLE `document_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_workflows`
--
ALTER TABLE `document_workflows`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty_document_submissions`
--
ALTER TABLE `faculty_document_submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `file_comments`
--
ALTER TABLE `file_comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `file_downloads`
--
ALTER TABLE `file_downloads`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `file_shares`
--
ALTER TABLE `file_shares`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folder_permissions`
--
ALTER TABLE `folder_permissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_templates`
--
ALTER TABLE `message_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_comments`
--
ALTER TABLE `post_comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_likes`
--
ALTER TABLE `post_likes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_media`
--
ALTER TABLE `post_media`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_notifications`
--
ALTER TABLE `post_notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_reports`
--
ALTER TABLE `post_reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_saves`
--
ALTER TABLE `post_saves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_shares`
--
ALTER TABLE `post_shares`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_views`
--
ALTER TABLE `post_views`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `user_follows`
--
ALTER TABLE `user_follows`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `v_admin_submission_stats`
--
DROP TABLE IF EXISTS `v_admin_submission_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_admin_submission_stats`  AS SELECT `st`.`academic_year` AS `academic_year`, `st`.`semester` AS `semester`, `st`.`department_code` AS `department_code`, `st`.`department_name` AS `department_name`, count(distinct `st`.`user_id`) AS `total_faculty`, count(distinct (case when (`st`.`status` = 'uploaded') then concat(`st`.`user_id`,'-',`st`.`document_type`) end)) AS `submitted_docs`, count(distinct `st`.`document_type`) AS `required_doc_types`, (count(distinct `st`.`user_id`) * count(distinct `st`.`document_type`)) AS `total_required_submissions`, round(((count(distinct (case when (`st`.`status` = 'uploaded') then concat(`st`.`user_id`,'-',`st`.`document_type`) end)) / (count(distinct `st`.`user_id`) * count(distinct `st`.`document_type`))) * 100),2) AS `completion_percentage` FROM `v_submission_tracker` AS `st` WHERE ((`st`.`academic_year` is not null) AND (`st`.`semester` is not null)) GROUP BY `st`.`academic_year`, `st`.`semester`, `st`.`department_code`, `st`.`department_name` ORDER BY `st`.`academic_year` DESC, `st`.`semester` ASC, `st`.`department_name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_announcements_detailed`
--
DROP TABLE IF EXISTS `v_announcements_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_announcements_detailed`  AS SELECT `a`.`id` AS `id`, `a`.`title` AS `title`, `a`.`content` AS `content`, `a`.`summary` AS `summary`, `a`.`image_path` AS `image_path`, `a`.`priority` AS `priority`, `a`.`announcement_type` AS `announcement_type`, `a`.`is_published` AS `is_published`, `a`.`published_at` AS `published_at`, `a`.`expires_at` AS `expires_at`, `a`.`view_count` AS `view_count`, `a`.`is_pinned` AS `is_pinned`, `a`.`created_at` AS `created_at`, `creator`.`username` AS `created_by_username`, concat(`creator`.`name`,' ',ifnull(concat(`creator`.`mi`,'. '),''),`creator`.`surname`) AS `creator_full_name` FROM (`announcements` `a` left join `users` `creator` on((`a`.`created_by` = `creator`.`id`))) WHERE (`a`.`is_deleted` = 0) ;

-- --------------------------------------------------------

--
-- Structure for view `v_comments_detailed`
--
DROP TABLE IF EXISTS `v_comments_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_comments_detailed`  AS SELECT `c`.`id` AS `id`, `c`.`post_id` AS `post_id`, `c`.`user_id` AS `user_id`, `c`.`parent_comment_id` AS `parent_comment_id`, `c`.`content` AS `content`, `c`.`is_edited` AS `is_edited`, `c`.`edited_at` AS `edited_at`, `c`.`is_deleted` AS `is_deleted`, `c`.`deleted_at` AS `deleted_at`, `c`.`deleted_by` AS `deleted_by`, `c`.`created_at` AS `created_at`, `c`.`updated_at` AS `updated_at`, `c`.`like_count` AS `like_count`, `u`.`username` AS `username`, `u`.`name` AS `name`, `u`.`mi` AS `mi`, `u`.`surname` AS `surname`, concat(`u`.`name`,' ',ifnull(concat(`u`.`mi`,'. '),''),`u`.`surname`) AS `commenter_full_name`, `u`.`profile_image` AS `profile_image`, `u`.`position` AS `position`, `d`.`department_code` AS `department_code`, `deleter`.`username` AS `deleted_by_username` FROM (((`post_comments` `c` left join `users` `u` on((`c`.`user_id` = `u`.`id`))) left join `departments` `d` on((`u`.`department_id` = `d`.`id`))) left join `users` `deleter` on((`c`.`deleted_by` = `deleter`.`id`))) ;

-- --------------------------------------------------------

--
-- Structure for view `v_document_requests_detailed`
--
DROP TABLE IF EXISTS `v_document_requests_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_document_requests_detailed`  AS SELECT `dr`.`id` AS `id`, `dr`.`tracking_code` AS `tracking_code`, `dr`.`user_id` AS `user_id`, `dr`.`document_type` AS `document_type`, `dr`.`title` AS `title`, `dr`.`description` AS `description`, `dr`.`priority` AS `priority`, `dr`.`status` AS `status`, `dr`.`target_department` AS `target_department`, `dr`.`expected_completion` AS `expected_completion`, `dr`.`actual_completion` AS `actual_completion`, `dr`.`assigned_to` AS `assigned_to`, `dr`.`metadata` AS `metadata`, `dr`.`created_at` AS `created_at`, `dr`.`updated_at` AS `updated_at`, `dr`.`updated_by` AS `updated_by`, `dr`.`is_deleted` AS `is_deleted`, `dr`.`deleted_at` AS `deleted_at`, `dr`.`deleted_by` AS `deleted_by`, `u`.`username` AS `requester_name`, concat(`u`.`name`,' ',ifnull(concat(`u`.`mi`,'. '),''),`u`.`surname`) AS `requester_full_name`, `u`.`email` AS `requester_email`, `ud`.`department_name` AS `requester_department`, `td`.`department_name` AS `target_dept_name`, `td`.`department_code` AS `target_dept_code`, concat(`assigned`.`name`,' ',ifnull(concat(`assigned`.`mi`,'. '),''),`assigned`.`surname`) AS `assigned_to_name`, concat(`updater`.`name`,' ',ifnull(concat(`updater`.`mi`,'. '),''),`updater`.`surname`) AS `updated_by_name`, (select count(0) from `document_comments` where ((`document_comments`.`request_id` = `dr`.`id`) and (`document_comments`.`is_deleted` = 0))) AS `comment_count`, (select count(0) from `document_attachments` where ((`document_attachments`.`request_id` = `dr`.`id`) and (`document_attachments`.`is_deleted` = 0))) AS `attachment_count` FROM (((((`document_requests` `dr` left join `users` `u` on((`dr`.`user_id` = `u`.`id`))) left join `departments` `ud` on((`u`.`department_id` = `ud`.`id`))) left join `departments` `td` on((`dr`.`target_department` = `td`.`id`))) left join `users` `assigned` on((`dr`.`assigned_to` = `assigned`.`id`))) left join `users` `updater` on((`dr`.`updated_by` = `updater`.`id`))) WHERE (`dr`.`is_deleted` = 0) ;

-- --------------------------------------------------------

--
-- Structure for view `v_files_detailed`
--
DROP TABLE IF EXISTS `v_files_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_files_detailed`  AS SELECT `f`.`id` AS `id`, `f`.`original_name` AS `original_name`, `f`.`file_name` AS `file_name`, `f`.`file_size` AS `file_size`, `f`.`file_type` AS `file_type`, `f`.`mime_type` AS `mime_type`, `f`.`file_extension` AS `file_extension`, `f`.`uploaded_at` AS `uploaded_at`, `f`.`download_count` AS `download_count`, `f`.`is_deleted` AS `is_deleted`, `folder`.`folder_name` AS `folder_name`, `folder`.`folder_path` AS `folder_path`, `uploader`.`username` AS `uploaded_by_username`, concat(`uploader`.`name`,' ',ifnull(concat(`uploader`.`mi`,'. '),''),`uploader`.`surname`) AS `uploader_full_name`, `dept`.`department_code` AS `folder_department` FROM (((`files` `f` left join `folders` `folder` on((`f`.`folder_id` = `folder`.`id`))) left join `users` `uploader` on((`f`.`uploaded_by` = `uploader`.`id`))) left join `departments` `dept` on((`folder`.`department_id` = `dept`.`id`))) ;

-- --------------------------------------------------------

--
-- Structure for view `v_folders_hierarchy`
--
DROP TABLE IF EXISTS `v_folders_hierarchy`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_folders_hierarchy`  AS SELECT `f`.`id` AS `id`, `f`.`folder_name` AS `folder_name`, `f`.`folder_path` AS `folder_path`, `f`.`folder_level` AS `folder_level`, `f`.`is_public` AS `is_public`, `f`.`folder_color` AS `folder_color`, `f`.`folder_icon` AS `folder_icon`, `f`.`file_count` AS `file_count`, `f`.`folder_size` AS `folder_size`, `f`.`created_at` AS `created_at`, `parent`.`folder_name` AS `parent_folder_name`, `creator`.`username` AS `created_by_username`, concat(`creator`.`name`,' ',ifnull(concat(`creator`.`mi`,'. '),''),`creator`.`surname`) AS `creator_full_name`, `dept`.`department_code` AS `department_code`, `dept`.`department_name` AS `department_name` FROM (((`folders` `f` left join `folders` `parent` on((`f`.`parent_id` = `parent`.`id`))) left join `users` `creator` on((`f`.`created_by` = `creator`.`id`))) left join `departments` `dept` on((`f`.`department_id` = `dept`.`id`))) WHERE (`f`.`is_deleted` = 0) ;

-- --------------------------------------------------------

--
-- Structure for view `v_posts_detailed`
--
DROP TABLE IF EXISTS `v_posts_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_posts_detailed`  AS SELECT `p`.`id` AS `id`, `p`.`user_id` AS `user_id`, `p`.`content` AS `content`, `p`.`content_type` AS `content_type`, `p`.`visibility` AS `visibility`, `p`.`target_departments` AS `target_departments`, `p`.`target_users` AS `target_users`, `p`.`priority` AS `priority`, `p`.`is_pinned` AS `is_pinned`, `p`.`is_edited` AS `is_edited`, `p`.`edited_at` AS `edited_at`, `p`.`is_deleted` AS `is_deleted`, `p`.`deleted_at` AS `deleted_at`, `p`.`deleted_by` AS `deleted_by`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, `p`.`like_count` AS `like_count`, `p`.`comment_count` AS `comment_count`, `p`.`view_count` AS `view_count`, `p`.`share_count` AS `share_count`, `u`.`username` AS `username`, `u`.`name` AS `name`, `u`.`mi` AS `mi`, `u`.`surname` AS `surname`, concat(`u`.`name`,' ',ifnull(concat(`u`.`mi`,'. '),''),`u`.`surname`) AS `author_full_name`, `u`.`profile_image` AS `profile_image`, `u`.`position` AS `position`, `d`.`department_code` AS `department_code`, `d`.`department_name` AS `department_name`, `deleter`.`username` AS `deleted_by_username` FROM (((`posts` `p` left join `users` `u` on((`p`.`user_id` = `u`.`id`))) left join `departments` `d` on((`u`.`department_id` = `d`.`id`))) left join `users` `deleter` on((`p`.`deleted_by` = `deleter`.`id`))) ;

-- --------------------------------------------------------

--
-- Structure for view `v_submission_tracker`
--
DROP TABLE IF EXISTS `v_submission_tracker`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_submission_tracker`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, `u`.`name` AS `name`, `u`.`surname` AS `surname`, `u`.`department_id` AS `department_id`, `d`.`department_name` AS `department_name`, `d`.`department_code` AS `department_code`, `fds`.`academic_year` AS `academic_year`, `fds`.`semester` AS `semester`, `fds`.`document_type` AS `document_type`, count(`df`.`id`) AS `file_count`, max(`df`.`uploaded_at`) AS `latest_upload`, min(`df`.`uploaded_at`) AS `first_upload`, sum(`df`.`file_size`) AS `total_size`, `fds`.`submitted_at` AS `submitted_at`, `fds`.`submitted_by` AS `submitted_by`, (case when (count(`df`.`id`) > 0) then 'uploaded' else 'not_uploaded' end) AS `status` FROM (((`users` `u` left join `departments` `d` on((`u`.`department_id` = `d`.`id`))) left join `faculty_document_submissions` `fds` on((`u`.`id` = `fds`.`faculty_id`))) left join `document_files` `df` on(((`fds`.`id` = `df`.`submission_id`) and (`df`.`file_type` = `fds`.`document_type`)))) WHERE ((`u`.`role` = 'user') AND (`u`.`is_approved` = 1)) GROUP BY `u`.`id`, `fds`.`academic_year`, `fds`.`semester`, `fds`.`document_type` ;

-- --------------------------------------------------------

--
-- Structure for view `v_users_detailed`
--
DROP TABLE IF EXISTS `v_users_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_users_detailed`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`role` AS `role`, `u`.`is_approved` AS `is_approved`, `u`.`name` AS `name`, `u`.`mi` AS `mi`, `u`.`surname` AS `surname`, concat(`u`.`name`,' ',ifnull(concat(`u`.`mi`,'. '),''),`u`.`surname`) AS `full_name`, `u`.`employee_id` AS `employee_id`, `u`.`position` AS `position`, `u`.`is_restricted` AS `is_restricted`, `u`.`last_login` AS `last_login`, `u`.`created_at` AS `created_at`, `d`.`department_code` AS `department_code`, `d`.`department_name` AS `department_name`, `approver`.`username` AS `approved_by_username`, `u`.`approved_at` AS `approved_at` FROM ((`users` `u` left join `departments` `d` on((`u`.`department_id` = `d`.`id`))) left join `users` `approver` on((`u`.`approved_by` = `approver`.`id`))) ;

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
-- Constraints for table `document_attachments`
--
ALTER TABLE `document_attachments`
  ADD CONSTRAINT `document_attachments_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_attachments_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_attachments_uploaded_by_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_comments`
--
ALTER TABLE `document_comments`
  ADD CONSTRAINT `document_comments_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_comments_parent_fk` FOREIGN KEY (`parent_comment_id`) REFERENCES `document_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_comments_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_files`
--
ALTER TABLE `document_files`
  ADD CONSTRAINT `document_files_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `faculty_document_submissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_notifications`
--
ALTER TABLE `document_notifications`
  ADD CONSTRAINT `document_notifications_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `document_requests_assigned_to_fk` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_requests_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_requests_target_dept_fk` FOREIGN KEY (`target_department`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_requests_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_requests_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD CONSTRAINT `document_requirements_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_requirements_dept_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_status_history`
--
ALTER TABLE `document_status_history`
  ADD CONSTRAINT `document_status_changed_by_fk` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_status_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_templates`
--
ALTER TABLE `document_templates`
  ADD CONSTRAINT `document_templates_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_templates_dept_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_templates_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_workflows`
--
ALTER TABLE `document_workflows`
  ADD CONSTRAINT `document_workflows_assigned_dept_fk` FOREIGN KEY (`assigned_department`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_workflows_assigned_to_fk` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_workflows_request_fk` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty_document_submissions`
--
ALTER TABLE `faculty_document_submissions`
  ADD CONSTRAINT `faculty_document_submissions_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `faculty_document_submissions_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_templates`
--
ALTER TABLE `message_templates`
  ADD CONSTRAINT `message_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `posts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_comments`
--
ALTER TABLE `post_comments`
  ADD CONSTRAINT `post_comments_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `post_comments_parent_fk` FOREIGN KEY (`parent_comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_comments_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD CONSTRAINT `post_likes_comment_fk` FOREIGN KEY (`comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_likes_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_likes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_media`
--
ALTER TABLE `post_media`
  ADD CONSTRAINT `post_media_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_notifications`
--
ALTER TABLE `post_notifications`
  ADD CONSTRAINT `post_notifications_comment_fk` FOREIGN KEY (`comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_notifications_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_notifications_triggered_by_fk` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_shares`
--
ALTER TABLE `post_shares`
  ADD CONSTRAINT `post_shares_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_shares_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_views`
--
ALTER TABLE `post_views`
  ADD CONSTRAINT `post_views_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_views_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
