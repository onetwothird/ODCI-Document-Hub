<?php

require_once __DIR__ . '/../../../../../includes/config.php';
require_once __DIR__ . '/../../../../../includes/auth_check.php';

class CommentManager
{
    /**
     * Get post comments with pagination - FIXED VERSION
     */
    public static function getPostComments($pdo, $postId, $limit = 20, $offset = 0)
    {
        try {
            error_log("getPostComments called with postId: $postId, limit: $limit, offset: $offset");

            $postId = (int) $postId;
            $limit = (int) $limit;
            $offset = (int) $offset;

            // Get top-level comments (no parent)
            $query = "
                SELECT c.id, c.post_id, c.user_id, c.parent_comment_id, c.content,
                       c.is_edited, c.edited_at, c.is_deleted, c.deleted_at, c.deleted_by,
                       c.created_at, c.updated_at, c.like_count,
                       u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                       u.profile_image, u.position, 
                       d.department_code,
                       deleter.username as deleted_by_username
                FROM post_comments c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN users deleter ON c.deleted_by = deleter.id
                WHERE c.post_id = ? AND c.is_deleted = 0 AND c.parent_comment_id IS NULL
                ORDER BY c.created_at ASC
                LIMIT $limit OFFSET $offset
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$postId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Found " . count($comments) . " top-level comments for post $postId");

            // Get replies for each comment
            foreach ($comments as &$comment) {
                $comment['replies'] = self::getCommentReplies($pdo, $comment['id'], 10);
                $comment['reply_count'] = count($comment['replies']);
                $comment['has_more_replies'] = false; // Could implement pagination for replies later
                error_log("Comment {$comment['id']} has " . count($comment['replies']) . " replies");
            }

            return $comments;
        } catch (Exception $e) {
            error_log("Get post comments error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get comment replies with proper nesting
     */
    public static function getCommentReplies($pdo, $parentCommentId, $limit = 10)
    {
        try {
            $parentCommentId = (int) $parentCommentId;
            $limit = (int) $limit;

            $query = "
                SELECT c.id, c.post_id, c.user_id, c.parent_comment_id, c.content,
                       c.is_edited, c.edited_at, c.is_deleted, c.deleted_at, c.deleted_by,
                       c.created_at, c.updated_at, c.like_count,
                       u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                       u.profile_image, u.position,
                       d.department_code
                FROM post_comments c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE c.parent_comment_id = ? AND c.is_deleted = 0
                ORDER BY c.created_at ASC
                LIMIT $limit
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$parentCommentId]);
            $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Note: We're keeping replies flat for now (no nested replies to replies)
            // This prevents infinite nesting complexity
            foreach ($replies as &$reply) {
                $reply['replies'] = []; // Replies don't have sub-replies in this implementation
                $reply['reply_count'] = 0;
            }

            return $replies;
        } catch (Exception $e) {
            error_log("Get comment replies error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add a new comment or reply
     */
    public static function addComment($pdo, $postId, $userId, $content, $parentCommentId = null)
    {
        try {
            $postId = (int) $postId;
            $userId = (int) $userId;
            $parentCommentId = $parentCommentId ? (int) $parentCommentId : null;
            $content = trim($content);

            if (empty($content)) {
                return false;
            }

            // Verify post exists
            $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$postId]);
            if (!$stmt->fetch()) {
                error_log("Post $postId not found or deleted");
                return false;
            }

            // If it's a reply, verify parent comment exists and belongs to the same post
            if ($parentCommentId) {
                $stmt = $pdo->prepare("SELECT post_id FROM post_comments WHERE id = ? AND is_deleted = 0");
                $stmt->execute([$parentCommentId]);
                $parentComment = $stmt->fetch();
                
                if (!$parentComment || $parentComment['post_id'] != $postId) {
                    error_log("Parent comment $parentCommentId not found or doesn't belong to post $postId");
                    return false;
                }
            }

            // Insert the comment
            $stmt = $pdo->prepare("
                INSERT INTO post_comments (post_id, user_id, parent_comment_id, content, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([$postId, $userId, $parentCommentId, $content]);

            if ($result) {
                $commentId = $pdo->lastInsertId();
                error_log("Successfully added comment $commentId for post $postId" . 
                         ($parentCommentId ? " as reply to comment $parentCommentId" : ""));
                return $commentId;
            }

            return false;
        } catch (Exception $e) {
            error_log("Add comment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the actual comment count for a post
     */
    public static function getActualCommentCount($pdo, $postId)
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM post_comments WHERE post_id = ? AND is_deleted = 0");
            $stmt->execute([$postId]);
            $result = $stmt->fetch();
            return $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            error_log("Get comment count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update comment
     */
    public static function updateComment($pdo, $commentId, $userId, $content, $userRole = 'user')
    {
        try {
            // Check if user can edit this comment
            $stmt = $pdo->prepare("SELECT user_id FROM post_comments WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();

            if (!$comment || ($comment['user_id'] != $userId && !in_array($userRole, ['admin', 'super_admin']))) {
                return false;
            }

            $stmt = $pdo->prepare("
                UPDATE post_comments 
                SET content = ?, is_edited = 1, edited_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");

            $result = $stmt->execute([$content, $commentId]);

            if ($result && function_exists('logActivity')) {
                logActivity($pdo, $userId, 'edit_comment', 'comment', $commentId, 'Edited comment');
            }

            return $result;
        } catch (Exception $e) {
            error_log("Update comment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete comment
     */
    public static function deleteComment($pdo, $commentId, $userId, $userRole = 'user')
    {
        try {
            // Check if user can delete this comment
            $stmt = $pdo->prepare("SELECT user_id, post_id FROM post_comments WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();

            if (!$comment || ($comment['user_id'] != $userId && !in_array($userRole, ['admin', 'super_admin']))) {
                return false;
            }

            // Soft delete the comment
            $stmt = $pdo->prepare("
                UPDATE post_comments 
                SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([$userId, $commentId]);

            if ($result) {
                // Also soft delete any replies to this comment
                $stmt = $pdo->prepare("
                    UPDATE post_comments 
                    SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                    WHERE parent_comment_id = ?
                ");
                $stmt->execute([$userId, $commentId]);

                if (function_exists('logActivity')) {
                    logActivity($pdo, $userId, 'delete_comment', 'comment', $commentId, 'Deleted comment');
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Delete comment error: " . $e->getMessage());
            return false;
        }
    }
}

?>