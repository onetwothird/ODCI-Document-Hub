-- ============================================================================
-- Social Media Feed Addition to MyDrive Database (myd_db)
-- This script adds social media/feed functionality to your existing database
-- Execute this script on your existing myd_db database
-- ============================================================================

USE `myd_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for posts
-- --------------------------------------------------------
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `content_type` enum('text','image','file','link','mixed') DEFAULT 'text',
  `visibility` enum('public','department','private','custom') DEFAULT 'public',
  `target_departments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_departments`)),
  `target_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_users`)),
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `like_count` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `share_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `posts_user_fk` (`user_id`),
  KEY `posts_deleted_by_fk` (`deleted_by`),
  KEY `idx_posts_created_at` (`created_at`),
  KEY `idx_posts_visibility` (`visibility`),
  KEY `idx_posts_pinned` (`is_pinned`),
  KEY `idx_posts_deleted` (`is_deleted`),
  CONSTRAINT `posts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `posts_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for post media attachments
-- --------------------------------------------------------
CREATE TABLE `post_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `media_type` enum('image','file','link') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `url` varchar(1000) DEFAULT NULL,
  `url_title` varchar(255) DEFAULT NULL,
  `url_description` text DEFAULT NULL,
  `url_image` varchar(500) DEFAULT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_media_post_fk` (`post_id`),
  KEY `idx_post_media_type` (`media_type`),
  CONSTRAINT `post_media_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for post comments
-- --------------------------------------------------------
CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `like_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `post_comments_post_fk` (`post_id`),
  KEY `post_comments_user_fk` (`user_id`),
  KEY `post_comments_parent_fk` (`parent_comment_id`),
  KEY `post_comments_deleted_by_fk` (`deleted_by`),
  KEY `idx_post_comments_created_at` (`created_at`),
  KEY `idx_post_comments_deleted` (`is_deleted`),
  CONSTRAINT `post_comments_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_comments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_comments_parent_fk` FOREIGN KEY (`parent_comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_comments_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for post likes/reactions
-- --------------------------------------------------------
CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) DEFAULT NULL,
  `comment_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `reaction_type` enum('like','love','laugh','angry','sad','wow') DEFAULT 'like',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_like` (`post_id`,`user_id`),
  UNIQUE KEY `unique_comment_like` (`comment_id`,`user_id`),
  KEY `post_likes_post_fk` (`post_id`),
  KEY `post_likes_comment_fk` (`comment_id`),
  KEY `post_likes_user_fk` (`user_id`),
  KEY `idx_post_likes_created_at` (`created_at`),
  CONSTRAINT `post_likes_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_likes_comment_fk` FOREIGN KEY (`comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_likes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for post views/reads
-- --------------------------------------------------------
CREATE TABLE `post_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `viewed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_view` (`post_id`,`user_id`),
  KEY `post_views_post_fk` (`post_id`),
  KEY `post_views_user_fk` (`user_id`),
  CONSTRAINT `post_views_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_views_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for post shares
-- --------------------------------------------------------
CREATE TABLE `post_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `share_type` enum('internal','external','copy_link') DEFAULT 'internal',
  `shared_with` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shared_with`)),
  `message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_shares_post_fk` (`post_id`),
  KEY `post_shares_user_fk` (`user_id`),
  KEY `idx_post_shares_created_at` (`created_at`),
  CONSTRAINT `post_shares_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_shares_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for post notifications
-- --------------------------------------------------------
CREATE TABLE `post_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `comment_id` int(11) DEFAULT NULL,
  `triggered_by` int(11) NOT NULL,
  `notification_type` enum('new_post','post_comment','post_like','comment_like','comment_reply','post_mention','comment_mention') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_notifications_user_fk` (`user_id`),
  KEY `post_notifications_post_fk` (`post_id`),
  KEY `post_notifications_comment_fk` (`comment_id`),
  KEY `post_notifications_triggered_by_fk` (`triggered_by`),
  KEY `idx_post_notifications_read` (`is_read`),
  KEY `idx_post_notifications_created_at` (`created_at`),
  CONSTRAINT `post_notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_notifications_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_notifications_comment_fk` FOREIGN KEY (`comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_notifications_triggered_by_fk` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Views for efficient data retrieval
-- --------------------------------------------------------

-- View for detailed posts with user information
CREATE VIEW `v_posts_detailed` AS
SELECT 
    p.*,
    u.username,
    u.name,
    u.mi,
    u.surname,
    CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
    u.profile_image,
    u.position,
    d.department_code,
    d.department_name,
    deleter.username as deleted_by_username
FROM posts p
LEFT JOIN users u ON p.user_id = u.id
LEFT JOIN departments d ON u.department_id = d.id
LEFT JOIN users deleter ON p.deleted_by = deleter.id;

-- View for detailed comments with user information
CREATE VIEW `v_comments_detailed` AS
SELECT 
    c.*,
    u.username,
    u.name,
    u.mi,
    u.surname,
    CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
    u.profile_image,
    u.position,
    d.department_code,
    deleter.username as deleted_by_username
FROM post_comments c
LEFT JOIN users u ON c.user_id = u.id
LEFT JOIN departments d ON u.department_id = d.id
LEFT JOIN users deleter ON c.deleted_by = deleter.id;

-- --------------------------------------------------------
-- Triggers to maintain counters
-- --------------------------------------------------------

DELIMITER $$

-- Update post like count when like is added/removed
CREATE TRIGGER `tr_post_like_insert` AFTER INSERT ON `post_likes` FOR EACH ROW 
BEGIN
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
END$$

CREATE TRIGGER `tr_post_like_delete` AFTER DELETE ON `post_likes` FOR EACH ROW 
BEGIN
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
END$$

-- Update post comment count when comment is added/removed
CREATE TRIGGER `tr_post_comment_insert` AFTER INSERT ON `post_comments` FOR EACH ROW 
BEGIN
    UPDATE posts 
    SET comment_count = (
        SELECT COUNT(*) FROM post_comments 
        WHERE post_id = NEW.post_id AND is_deleted = 0
    )
    WHERE id = NEW.post_id;
END$$

CREATE TRIGGER `tr_post_comment_update` AFTER UPDATE ON `post_comments` FOR EACH ROW 
BEGIN
    IF OLD.is_deleted != NEW.is_deleted THEN
        UPDATE posts 
        SET comment_count = (
            SELECT COUNT(*) FROM post_comments 
            WHERE post_id = NEW.post_id AND is_deleted = 0
        )
        WHERE id = NEW.post_id;
    END IF;
END$$

-- Update post view count when view is added
CREATE TRIGGER `tr_post_view_insert` AFTER INSERT ON `post_views` FOR EACH ROW 
BEGIN
    UPDATE posts 
    SET view_count = (
        SELECT COUNT(*) FROM post_views 
        WHERE post_id = NEW.post_id
    )
    WHERE id = NEW.post_id;
END$$

-- Update post share count when share is added
CREATE TRIGGER `tr_post_share_insert` AFTER INSERT ON `post_shares` FOR EACH ROW 
BEGIN
    UPDATE posts 
    SET share_count = (
        SELECT COUNT(*) FROM post_shares 
        WHERE post_id = NEW.post_id
    )
    WHERE id = NEW.post_id;
END$$

DELIMITER ;

-- --------------------------------------------------------
-- Stored Procedures for common operations
-- --------------------------------------------------------

DELIMITER $$

-- Get user's feed posts with pagination
CREATE PROCEDURE `sp_get_user_feed` (
    IN p_user_id INT,
    IN p_department_id INT,
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT p.*, u.username, u.name, u.mi, u.surname,
           CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
           u.profile_image, u.position, d.department_code,
           EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = p_user_id) as user_liked,
           EXISTS(SELECT 1 FROM post_views pv WHERE pv.post_id = p.id AND pv.user_id = p_user_id) as user_viewed
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE p.is_deleted = 0
    AND (
        p.visibility = 'public' OR
        (p.visibility = 'department' AND (
            p.target_departments IS NULL AND u.department_id = p_department_id OR
            p.target_departments IS NOT NULL AND JSON_CONTAINS(p.target_departments, CAST(p_department_id AS JSON))
        )) OR
        (p.visibility = 'custom' AND JSON_CONTAINS(p.target_users, CAST(p_user_id AS JSON))) OR
        p.user_id = p_user_id
    )
    ORDER BY p.is_pinned DESC, p.created_at DESC
    LIMIT p_limit OFFSET p_offset;
END$

-- Get post comments with pagination
CREATE PROCEDURE `sp_get_post_comments` (
    IN p_post_id INT,
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT c.*, u.username, u.name, u.mi, u.surname,
           CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
           u.profile_image, u.position
    FROM post_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = p_post_id AND c.is_deleted = 0
    ORDER BY c.created_at ASC
    LIMIT p_limit OFFSET p_offset;
END$$

-- Get user's post statistics
CREATE PROCEDURE `sp_get_user_post_stats` (IN p_user_id INT)
BEGIN
    SELECT 
        COUNT(*) as total_posts,
        COALESCE(SUM(like_count), 0) as total_likes,
        COALESCE(SUM(comment_count), 0) as total_comments,
        COALESCE(SUM(view_count), 0) as total_views,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as posts_last_30_days
    FROM posts
    WHERE user_id = p_user_id AND is_deleted = 0;
END$$

DELIMITER ;

-- Enhanced version of social feed procedures with additional features
-- Add these to your database for even better functionality

DELIMITER $$

-- Enhanced user feed with filtering options
CREATE PROCEDURE `sp_get_user_feed_enhanced` (
    IN p_user_id INT,
    IN p_department_id INT,
    IN p_limit INT,
    IN p_offset INT,
    IN p_filter_type VARCHAR(20), -- 'all', 'following', 'department', 'own'
    IN p_content_type VARCHAR(20) -- 'all', 'text', 'image', 'file', 'link'
)
BEGIN
    SELECT p.*, u.username, u.name, u.mi, u.surname,
           CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
           u.profile_image, u.position, d.department_code, d.department_name,
           EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = p_user_id) as user_liked,
           EXISTS(SELECT 1 FROM post_views pv WHERE pv.post_id = p.id AND pv.user_id = p_user_id) as user_viewed,
           (SELECT COUNT(*) FROM post_media pm WHERE pm.post_id = p.id) as media_count,
           (SELECT reaction_type FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = p_user_id) as user_reaction
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE p.is_deleted = 0
    AND (
        p.visibility = 'public' OR
        (p.visibility = 'department' AND (
            p.target_departments IS NULL AND u.department_id = p_department_id OR
            p.target_departments IS NOT NULL AND JSON_CONTAINS(p.target_departments, CAST(p_department_id AS JSON))
        )) OR
        (p.visibility = 'custom' AND JSON_CONTAINS(p.target_users, CAST(p_user_id AS JSON))) OR
        p.user_id = p_user_id
    )
    AND (p_filter_type = 'all' OR 
         (p_filter_type = 'department' AND u.department_id = p_department_id) OR
         (p_filter_type = 'own' AND p.user_id = p_user_id))
    AND (p_content_type = 'all' OR p.content_type = p_content_type)
    ORDER BY p.is_pinned DESC, p.created_at DESC
    LIMIT p_limit OFFSET p_offset;
END$$

-- Get trending posts (most liked/commented in last 7 days)
CREATE PROCEDURE `sp_get_trending_posts` (
    IN p_user_id INT,
    IN p_department_id INT,
    IN p_limit INT,
    IN p_days_back INT
)
BEGIN
    SELECT p.*, u.username, u.name, u.mi, u.surname,
           CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
           u.profile_image, u.position, d.department_code,
           (p.like_count + p.comment_count + p.view_count/10) as trending_score,
           EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = p_user_id) as user_liked,
           EXISTS(SELECT 1 FROM post_views pv WHERE pv.post_id = p.id AND pv.user_id = p_user_id) as user_viewed
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE p.is_deleted = 0
    AND p.created_at >= DATE_SUB(NOW(), INTERVAL p_days_back DAY)
    AND (
        p.visibility = 'public' OR
        (p.visibility = 'department' AND (
            p.target_departments IS NULL AND u.department_id = p_department_id OR
            p.target_departments IS NOT NULL AND JSON_CONTAINS(p.target_departments, CAST(p_department_id AS JSON))
        )) OR
        (p.visibility = 'custom' AND JSON_CONTAINS(p.target_users, CAST(p_user_id AS JSON))) OR
        p.user_id = p_user_id
    )
    AND (p.like_count > 0 OR p.comment_count > 0 OR p.view_count > 5)
    ORDER BY trending_score DESC, p.created_at DESC
    LIMIT p_limit;
END$$

-- Search posts with full-text search
CREATE PROCEDURE `sp_search_posts` (
    IN p_user_id INT,
    IN p_department_id INT,
    IN p_search_query VARCHAR(255),
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT p.*, u.username, u.name, u.mi, u.surname,
           CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
           u.profile_image, u.position, d.department_code,
           MATCH(p.content) AGAINST(p_search_query IN NATURAL LANGUAGE MODE) as relevance_score,
           EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = p_user_id) as user_liked,
           EXISTS(SELECT 1 FROM post_views pv WHERE pv.post_id = p.id AND pv.user_id = p_user_id) as user_viewed
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE p.is_deleted = 0
    AND MATCH(p.content) AGAINST(p_search_query IN NATURAL LANGUAGE MODE)
    AND (
        p.visibility = 'public' OR
        (p.visibility = 'department' AND (
            p.target_departments IS NULL AND u.department_id = p_department_id OR
            p.target_departments IS NOT NULL AND JSON_CONTAINS(p.target_departments, CAST(p_department_id AS JSON))
        )) OR
        (p.visibility = 'custom' AND JSON_CONTAINS(p.target_users, CAST(p_user_id AS JSON))) OR
        p.user_id = p_user_id
    )
    ORDER BY relevance_score DESC, p.created_at DESC
    LIMIT p_limit OFFSET p_offset;
END$$

-- Get post details with media and recent comments
CREATE PROCEDURE `sp_get_post_details` (
    IN p_post_id INT,
    IN p_user_id INT
)
BEGIN
    -- Get post details
    SELECT p.*, u.username, u.name, u.mi, u.surname,
           CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
           u.profile_image, u.position, d.department_code, d.department_name,
           EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = p_user_id) as user_liked,
           EXISTS(SELECT 1 FROM post_views pv WHERE pv.post_id = p.id AND pv.user_id = p_user_id) as user_viewed,
           (SELECT reaction_type FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = p_user_id) as user_reaction
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE p.id = p_post_id AND p.is_deleted = 0;
    
    -- Get media attachments
    SELECT * FROM post_media 
    WHERE post_id = p_post_id 
    ORDER BY sort_order, created_at;
    
    -- Get recent comments (last 5)
    SELECT c.*, u.username, u.name, u.mi, u.surname,
           CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
           u.profile_image, u.position,
           EXISTS(SELECT 1 FROM post_likes pl WHERE pl.comment_id = c.id AND pl.user_id = p_user_id) as user_liked_comment,
           (SELECT reaction_type FROM post_likes pl WHERE pl.comment_id = c.id AND pl.user_id = p_user_id) as user_reaction
    FROM post_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.post_id = p_post_id AND c.is_deleted = 0
    ORDER BY c.created_at DESC
    LIMIT 5;
    
    -- Record the view if not already viewed
    INSERT IGNORE INTO post_views (post_id, user_id, viewed_at)
    VALUES (p_post_id, p_user_id, NOW());
END$$

-- Get activity feed (likes, comments, shares on user's posts)
CREATE PROCEDURE `sp_get_user_activity_feed` (
    IN p_user_id INT,
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    (SELECT 'like' as activity_type, pl.created_at as activity_time,
            pl.post_id, NULL as comment_id,
            u.username, u.name, u.mi, u.surname,
            CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as actor_full_name,
            u.profile_image,
            pl.reaction_type,
            p.content as post_content
     FROM post_likes pl
     LEFT JOIN users u ON pl.user_id = u.id
     LEFT JOIN posts p ON pl.post_id = p.id
     WHERE p.user_id = p_user_id AND pl.user_id != p_user_id
     AND pl.post_id IS NOT NULL)
    
    UNION ALL
    
    (SELECT 'comment' as activity_type, pc.created_at as activity_time,
            pc.post_id, pc.id as comment_id,
            u.username, u.name, u.mi, u.surname,
            CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as actor_full_name,
            u.profile_image,
            NULL as reaction_type,
            p.content as post_content
     FROM post_comments pc
     LEFT JOIN users u ON pc.user_id = u.id
     LEFT JOIN posts p ON pc.post_id = p.id
     WHERE p.user_id = p_user_id AND pc.user_id != p_user_id
     AND pc.is_deleted = 0)
    
    UNION ALL
    
    (SELECT 'share' as activity_type, ps.created_at as activity_time,
            ps.post_id, NULL as comment_id,
            u.username, u.name, u.mi, u.surname,
            CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as actor_full_name,
            u.profile_image,
            ps.share_type as reaction_type,
            p.content as post_content
     FROM post_shares ps
     LEFT JOIN users u ON ps.user_id = u.id
     LEFT JOIN posts p ON ps.post_id = p.id
     WHERE p.user_id = p_user_id AND ps.user_id != p_user_id)
     
    ORDER BY activity_time DESC
    LIMIT p_limit OFFSET p_offset;
END$$

DELIMITER ;

-- --------------------------------------------------------
-- Add full-text search indexes
-- --------------------------------------------------------
ALTER TABLE posts ADD FULLTEXT(content);
ALTER TABLE post_comments ADD FULLTEXT(content);

-- --------------------------------------------------------
-- Insert sample data for testing
-- --------------------------------------------------------

-- Add some sample posts
INSERT INTO posts (user_id, content, content_type, visibility, priority, created_at) VALUES
(1, 'Welcome to the new social feed feature! Share your thoughts, files, and collaborate with your colleagues.', 'text', 'public', 'high', NOW()),
(27, 'ITD Department meeting scheduled for tomorrow at 2 PM. Please review the agenda attached.', 'text', 'department', 'normal', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(28, 'Just finished uploading the new course materials. Check them out in the Syllabus folder!', 'text', 'public', 'normal', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Add some sample comments
INSERT INTO post_comments (post_id, user_id, content, created_at) VALUES
(1, 27, 'Great addition to the platform! Looking forward to using this feature.', NOW()),
(1, 28, 'This will definitely improve our communication and collaboration.', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, 29, 'I will be there. Thanks for the reminder!', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Add some sample likes
INSERT INTO post_likes (post_id, user_id, reaction_type, created_at) VALUES
(1, 27, 'like', NOW()),
(1, 28, 'love', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(2, 28, 'like', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 29, 'like', DATE_SUB(NOW(), INTERVAL 45 MINUTE));

-- --------------------------------------------------------
-- Update system settings to include social feed settings
-- --------------------------------------------------------
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public, updated_by) VALUES
('enable_social_feed', 'true', 'boolean', 'Enable social media feed functionality', 1, 1),
('max_post_length', '5000', 'integer', 'Maximum character limit for posts', 0, 1),
('allow_post_media', 'true', 'boolean', 'Allow media attachments in posts', 0, 1),
('post_auto_approval', 'true', 'boolean', 'Automatically approve new posts', 0, 1),
('enable_post_reactions', 'true', 'boolean', 'Enable reactions (like, love, etc.) on posts', 1, 1),
('posts_per_page', '10', 'integer', 'Number of posts to display per page in feed', 0, 1);

COMMIT;

-- ============================================================================
-- Installation Complete!
-- 
-- The social media feed functionality has been successfully added to your 
-- MyDrive database. This includes:
--
-- New Tables:
-- - posts: Main posts table
-- - post_media: Media attachments for posts  
-- - post_comments: Comments on posts
-- - post_likes: Likes/reactions on posts and comments
-- - post_views: Track post views
-- - post_shares: Post sharing functionality
-- - post_notifications: Social notifications
--
-- New Views:
-- - v_posts_detailed: Posts with user information
-- - v_comments_detailed: Comments with user information  
--
-- New Stored Procedures:
-- - sp_get_user_feed(): Get user's personalized feed
-- - sp_get_post_comments(): Get comments for a post
-- - sp_get_user_post_stats(): Get user's posting statistics
--
-- New Triggers:
-- - Automatic counter updates for likes, comments, views, shares
--
-- Sample Data:
-- - 3 sample posts with comments and likes for testing
-- - New system settings for social feed configuration
--
-- You can now implement the frontend to interact with these new tables
-- and procedures to create a full social media feed experience!
-- ============================================================================