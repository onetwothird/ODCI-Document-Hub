<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/ODCI/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ODCI/includes/config.php';

class NotificationManager
{
    /**
     * Send post notifications to followers/department members
     */
    public static function sendPostNotifications($pdo, $postId, $authorId, $type = 'new_post')
    {
        try {
            // Get post details
            $stmt = $pdo->prepare("SELECT content, visibility, target_departments FROM posts WHERE id = ?");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();

            if (!$post)
                return false;

            $message = "New post has been shared";

            // For public posts, notify department members or all users based on role
            if ($post['visibility'] === 'public') {
                $stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE id != ? AND is_approved = 1 
                    LIMIT 100
                ");
                $stmt->execute([$authorId]);
                $users = $stmt->fetchAll();

                foreach ($users as $user) {
                    self::insertPostNotification($pdo, $user['id'], $postId, null, $authorId, $type, $message);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Send post notifications error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send department-specific post notifications
     */
    public static function sendDepartmentPostNotifications($pdo, $postId, $authorId, $targetDepartments)
    {
        try {
            $deptList = implode(',', array_map('intval', $targetDepartments));
            $stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE department_id IN ({$deptList}) AND id != ? AND is_approved = 1
            ");
            $stmt->execute([$authorId]);
            $users = $stmt->fetchAll();

            $message = "New department post has been shared";

            foreach ($users as $user) {
                self::insertPostNotification($pdo, $user['id'], $postId, null, $authorId, 'new_post', $message);
            }

            return true;
        } catch (Exception $e) {
            error_log("Send department post notifications error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send custom post notifications to specific users
     */
    public static function sendCustomPostNotifications($pdo, $postId, $authorId, $targetUsers)
    {
        try {
            $message = "You've been mentioned in a post";

            foreach ($targetUsers as $userId) {
                if ($userId != $authorId) {
                    self::insertPostNotification($pdo, $userId, $postId, null, $authorId, 'new_post', $message);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Send custom post notifications error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send comment notification
     */
    public static function sendCommentNotification($pdo, $postId, $commentId, $commenterId, $postAuthorId)
    {
        try {
            $message = "Someone commented on your post";
            return self::insertPostNotification($pdo, $postAuthorId, $postId, $commentId, $commenterId, 'post_comment', $message);
        } catch (Exception $e) {
            error_log("Send comment notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send reply notification
     */
    public static function sendReplyNotification($pdo, $postId, $commentId, $replierId, $parentCommenterId)
    {
        try {
            $message = "Someone replied to your comment";
            return self::insertPostNotification($pdo, $parentCommenterId, $postId, $commentId, $replierId, 'comment_reply', $message);
        } catch (Exception $e) {
            error_log("Send reply notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send like notification
     */
    public static function sendLikeNotification($pdo, $postId, $commentId, $likerId, $targetUserId, $reactionType)
    {
        try {
            $message = $commentId ? "Someone liked your comment" : "Someone liked your post";
            $type = $commentId ? 'comment_like' : 'post_like';

            return self::insertPostNotification($pdo, $targetUserId, $postId, $commentId, $likerId, $type, $message);
        } catch (Exception $e) {
            error_log("Send like notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert post notification
     */
    public static function insertPostNotification($pdo, $userId, $postId, $commentId, $triggeredBy, $type, $message)
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO post_notifications (user_id, post_id, comment_id, triggered_by, notification_type, message) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            return $stmt->execute([$userId, $postId, $commentId, $triggeredBy, $type, $message]);
        } catch (Exception $e) {
            error_log("Insert post notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's post notifications
     */
    public static function getPostNotifications($pdo, $userId, $limit = 20, $offset = 0, $unreadOnly = false)
    {
        try {
            $whereClause = "pn.user_id = ?";
            $params = [$userId];

            if ($unreadOnly) {
                $whereClause .= " AND pn.is_read = 0";
            }

            $query = "
                SELECT pn.*, 
                       trigger_user.username as trigger_username,
                       CONCAT(trigger_user.name, ' ', IFNULL(CONCAT(trigger_user.mi, '. '), ''), trigger_user.surname) as trigger_full_name,
                       trigger_user.profile_image as trigger_profile_image,
                       p.content as post_content,
                       LEFT(p.content, 100) as post_preview
                FROM post_notifications pn
                LEFT JOIN users trigger_user ON pn.triggered_by = trigger_user.id
                LEFT JOIN posts p ON pn.post_id = p.id
                WHERE {$whereClause}
                ORDER BY pn.created_at DESC
                LIMIT ? OFFSET ?
            ";

            array_push($params, $limit, $offset);

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get post notifications error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark notification as read
     */
    public static function markNotificationAsRead($pdo, $notificationId, $userId)
    {
        try {
            $stmt = $pdo->prepare("
                UPDATE post_notifications 
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND user_id = ?
            ");

            return $stmt->execute([$notificationId, $userId]);
        } catch (Exception $e) {
            error_log("Mark notification as read error: " . $e->getMessage());
            return false;
        }
    }
}

?>