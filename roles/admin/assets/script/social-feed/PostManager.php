<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/ODCI/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ODCI/includes/config.php';

class AdminPostManager
{
    /**
     * Create a new post - Admin version
     */
    public static function createPost($pdo, $userId, $content, $contentType = 'text', $visibility = 'public', $targetDepartments = null, $targetUsers = null, $isPinned = false)
    {
        try {
            // Admins can pin posts
            $isPinned = ($isPinned && self::canPinPosts($pdo, $userId));
            
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
            logActivity($pdo, $userId, 'create_post', 'post', $postId, 'Created new post', [
                'content_type' => $contentType,
                'visibility' => $visibility
            ]);

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
     * Update post - Admin can update any post
     */
    public static function updatePost($pdo, $postId, $userId, $content, $userRole = 'admin')
    {
        try {
            // Admin can edit any post
            $stmt = $pdo->prepare("
                UPDATE posts 
                SET content = ?, is_edited = 1, edited_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");

            $result = $stmt->execute([$content, $postId]);

            if ($result) {
                logActivity($pdo, $userId, 'edit_post', 'post', $postId, 'Edited post');
            }

            return $result;
        } catch (Exception $e) {
            error_log("Update post error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete post - Admin can delete any post
     */
    public static function deletePost($pdo, $postId, $userId, $userRole = 'admin')
    {
        try {
            $stmt = $pdo->prepare("
                UPDATE posts 
                SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([$userId, $postId]);

            if ($result) {
                logActivity($pdo, $userId, 'delete_post', 'post', $postId, 'Deleted post');
            }

            return $result;
        } catch (Exception $e) {
            error_log("Delete post error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if admin can pin posts
     */
    private static function canPinPosts($pdo, $userId)
    {
        // Admins can pin posts but with limits (e.g., max 5 pinned posts)
        $stmt = $pdo->prepare("SELECT COUNT(*) as pinned_count FROM posts WHERE is_pinned = 1 AND is_deleted = 0");
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result['pinned_count'] < 5; // Limit to 5 pinned posts
    }

    /**
     * Get posts for moderation - Admin specific
     */
    public static function getPostsForModeration($pdo, $userId, $departmentId = null, $limit = 20, $offset = 0)
    {
        try {
            $query = "
                SELECT p.*, u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
                       u.profile_image, u.position, d.department_code,
                       EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as user_liked
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE p.is_deleted = 0
            ";

            $params = [$userId];

            // If admin is department-specific, filter by department
            if ($departmentId) {
                $query .= " AND (u.department_id = ? OR JSON_CONTAINS(p.target_departments, CAST(? AS JSON)))";
                $params[] = $departmentId;
                $params[] = $departmentId;
            }

            $query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get posts for moderation error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Moderate post (hide/show)
     */
    public static function moderatePost($pdo, $postId, $action, $userId, $reason = null)
    {
        try {
            $isHidden = ($action === 'hide') ? 1 : 0;
            
            $stmt = $pdo->prepare("
                UPDATE posts 
                SET is_hidden = ?, moderated_by = ?, moderated_at = NOW(), moderation_reason = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([$isHidden, $userId, $reason, $postId]);

            if ($result) {
                $actionText = ($action === 'hide') ? 'Hidden' : 'Shown';
                logActivity($pdo, $userId, 'moderate_post', 'post', $postId, $actionText . ' post', [
                    'reason' => $reason
                ]);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Moderate post error: " . $e->getMessage());
            return false;
        }
    }
}

?>