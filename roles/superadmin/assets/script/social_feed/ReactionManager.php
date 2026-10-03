<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/ODCI/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ODCI/includes/config.php';

class ReactionManager
{
    /**
     * Toggle like on post or comment
     */
    public static function toggleLike($pdo, $userId, $postId = null, $commentId = null)
    {
        try {
            $target = $postId ? 'post_id' : 'comment_id';
            $targetValue = $postId ?: $commentId;

            // Check if user already liked/reacted
            $stmt = $pdo->prepare("SELECT id, reaction_type FROM post_likes WHERE {$target} = ? AND user_id = ?");
            $stmt->execute([$targetValue, $userId]);
            $existingLike = $stmt->fetch();

            if ($existingLike) {
                // Just remove like (no reaction type checking needed)
                $stmt = $pdo->prepare("DELETE FROM post_likes WHERE id = ?");
                $result = $stmt->execute([$existingLike['id']]);
                $action = 'unliked';
            } else {
                // Add new like (always use 'like')
                $stmt = $pdo->prepare("INSERT INTO post_likes ({$target}, user_id, reaction_type) VALUES (?, ?, 'like')");
                $result = $stmt->execute([$targetValue, $userId]);
                $action = 'liked';

                // Send notification
                if ($result && $postId) {
                    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
                    $stmt->execute([$postId]);
                    $post = $stmt->fetch();

                    if ($post && $post['user_id'] != $userId) {
                        NotificationManager::sendLikeNotification($pdo, $postId, null, $userId, $post['user_id'], 'like');
                    }
                } elseif ($result && $commentId) {
                    $stmt = $pdo->prepare("SELECT user_id, post_id FROM post_comments WHERE id = ?");
                    $stmt->execute([$commentId]);
                    $comment = $stmt->fetch();

                    if ($comment && $comment['user_id'] != $userId) {
                        NotificationManager::sendLikeNotification($pdo, $comment['post_id'], $commentId, $userId, $comment['user_id'], 'like');
                    }
                }
            }

            if ($result) {
                logActivity(
                    $pdo,
                    $userId,
                    $action,
                    $postId ? 'post' : 'comment',
                    $targetValue,
                    "User {$action} " . ($postId ? 'post' : 'comment')
                );
            }

            return $result;
        } catch (Exception $e) {
            error_log("Toggle like error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get post/comment likes
     */
    public static function getLikes($pdo, $postId = null, $commentId = null)
    {
        try {
            $target = $postId ? 'post_id' : 'comment_id';
            $targetValue = $postId ?: $commentId;

            $query = "
                SELECT pl.*, u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as user_full_name,
                       u.profile_image
                FROM post_likes pl
                LEFT JOIN users u ON pl.user_id = u.id
                WHERE pl.{$target} = ?
                ORDER BY pl.created_at DESC
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$targetValue]);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get likes error: " . $e->getMessage());
            return [];
        }
    }
}

?>