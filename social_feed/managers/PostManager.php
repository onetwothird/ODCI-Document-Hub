<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config.php';

class PostManager
{
    /**
     * Create a new post
     */
    public static function createPost($pdo, $userId, $content, $contentType = 'text', $visibility = 'public', $targetDepartments = null, $targetUsers = null, $isPinned = false)
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO posts (user_id, content, content_type, visibility, target_departments, target_users, is_pinned)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $content,
                $contentType,
                $visibility,
                $targetDepartments ? json_encode($targetDepartments) : null,
                $targetUsers ? json_encode($targetUsers) : null,
                $isPinned ? 1 : 0
            ]);

            $postId = $pdo->lastInsertId();

            // Log activity
            if (function_exists('logActivity')) {
                logActivity($pdo, $userId, 'create_post', 'post', $postId, 'Created new post', [
                    'content_type' => $contentType,
                    'visibility' => $visibility
                ]);
            }

            // Send notifications for public posts or specific targets
            if ($visibility === 'public') {
                NotificationManager::sendPostNotifications($pdo, $postId, $userId, 'new_post');
            } elseif ($visibility === 'department' && $targetDepartments) {
                NotificationManager::sendDepartmentPostNotifications($pdo, $postId, $userId, $targetDepartments);
            } elseif ($visibility === 'custom' && $targetUsers) {
                NotificationManager::sendCustomPostNotifications($pdo, $postId, $userId, $targetUsers);
            }

            return $postId;
        } catch (Exception $e) {
            error_log("Create post error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user can edit a specific post
     */
    public static function canUserEditPost($pdo, $postId, $userId, $userRole = 'user')
    {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();

            if (!$post) {
                return false;
            }

            // User can edit if they own the post OR they have admin privileges
            return ($post['user_id'] == $userId) || in_array($userRole, ['admin', 'super_admin']);
        } catch (Exception $e) {
            error_log("Check edit post permission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update post - Enhanced with better authorization
     */
    public static function updatePost($pdo, $postId, $userId, $content, $userRole = 'user')
    {
        try {
            // Enhanced authorization check
            if (!self::canUserEditPost($pdo, $postId, $userId, $userRole)) {
                error_log("Unauthorized edit attempt - Post ID: $postId, User ID: $userId, Role: $userRole");
                return false;
            }

            $stmt = $pdo->prepare("
                UPDATE posts 
                SET content = ?, is_edited = 1, edited_at = NOW(), updated_at = NOW()
                WHERE id = ? AND is_deleted = 0
            ");

            $result = $stmt->execute([$content, $postId]);

            if ($result && function_exists('logActivity')) {
                logActivity($pdo, $userId, 'edit_post', 'post', $postId, 'Edited post');
            }

            return $result;
        } catch (Exception $e) {
            error_log("Update post error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete post
     */
    public static function deletePost($pdo, $postId, $userId, $userRole = 'user')
    {
        try {
            // Use the same authorization check for consistency
            if (!self::canUserEditPost($pdo, $postId, $userId, $userRole)) {
                error_log("Unauthorized delete attempt - Post ID: $postId, User ID: $userId, Role: $userRole");
                return false;
            }

            $stmt = $pdo->prepare("
                UPDATE posts 
                SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                WHERE id = ? AND is_deleted = 0
            ");

            $result = $stmt->execute([$userId, $postId]);

            if ($result && function_exists('logActivity')) {
                logActivity($pdo, $userId, 'delete_post', 'post', $postId, 'Deleted post');
            }

            return $result;
        } catch (Exception $e) {
            error_log("Delete post error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get post engagement stats
     */
    public static function getPostStats($pdo, $postId)
    {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    like_count,
                    comment_count,
                    view_count,
                    (SELECT COUNT(DISTINCT reaction_type) FROM post_likes WHERE post_id = ?) as reaction_types
                FROM posts 
                WHERE id = ?
            ");
            $stmt->execute([$postId, $postId]);

            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get post stats error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Search posts
     */
    public static function searchPosts($pdo, $query, $userId, $departmentId, $limit = 20, $offset = 0)
    {
        try {
            $searchQuery = "
                SELECT p.*, u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
                       u.profile_image, u.position, d.department_code,
                       MATCH(p.content) AGAINST(? IN BOOLEAN MODE) as relevance,
                       EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as user_liked
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE p.is_deleted = 0
                AND MATCH(p.content) AGAINST(? IN BOOLEAN MODE)
                AND (
                    p.visibility = 'public' OR
                    p.user_id = ? OR
                    (p.visibility = 'department' AND JSON_CONTAINS(p.target_departments, CAST(? AS JSON))) OR
                    (p.visibility = 'custom' AND JSON_CONTAINS(p.target_users, CAST(? AS JSON)))
                )
                ORDER BY relevance DESC, p.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $pdo->prepare($searchQuery);
            $stmt->execute([$query, $userId, $query, $userId, $departmentId, $userId, $limit, $offset]);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Search posts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark post as viewed
     */
    public static function markPostAsViewed($pdo, $postId, $userId)
    {
        try {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO post_views (post_id, user_id, ip_address, user_agent) 
                VALUES (?, ?, ?, ?)
            ");

            return $stmt->execute([
                $postId,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Mark post as viewed error: " . $e->getMessage());
            return false;
        }
    }
}

?>