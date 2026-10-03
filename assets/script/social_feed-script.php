<?php

require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// --------------------------------------------------------
// POST FUNCTIONS
// --------------------------------------------------------


/**
 * Create a new post
 */
function createPost($pdo, $userId, $content, $contentType = 'text', $visibility = 'public', $targetDepartments = null, $targetUsers = null, $priority = 'normal', $isPinned = false) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, content, content_type, visibility, target_departments, target_users, priority, is_pinned) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $content,
            $contentType,
            $visibility,
            $targetDepartments ? json_encode($targetDepartments) : null,
            $targetUsers ? json_encode($targetUsers) : null,
            $priority,
            $isPinned ? 1 : 0
        ]);
        
        $postId = $pdo->lastInsertId();
        
        // Log activity
        logActivity($pdo, $userId, 'create_post', 'post', $postId, 'Created new post', [
            'content_type' => $contentType,
            'visibility' => $visibility,
            'priority' => $priority
        ]);
        
        // Send notifications for public posts or specific targets
        if ($visibility === 'public') {
            sendPostNotifications($pdo, $postId, $userId, 'new_post');
        } elseif ($visibility === 'department' && $targetDepartments) {
            sendDepartmentPostNotifications($pdo, $postId, $userId, $targetDepartments);
        } elseif ($visibility === 'custom' && $targetUsers) {
            sendCustomPostNotifications($pdo, $postId, $userId, $targetUsers);
        }
        
        return $postId;
    } catch(Exception $e) {
        error_log("Create post error: " . $e->getMessage());
        return false;
    }
}


// updatePost function
function updatePost($pdo, $postId, $userId, $content, $userRole = 'user') {
    try {
        // Check if user can edit this post
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        if (!$post || ($post['user_id'] != $userId && !in_array($userRole, ['admin', 'super_admin']))) {
            return false;
        }
        
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
    } catch(Exception $e) {
        error_log("Update post error: " . $e->getMessage());
        return false;
    }
}

// Add this new function
function deletePost($pdo, $postId, $userId, $userRole = 'user') {
    try {
        // Check if user can delete this post
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        if (!$post || ($post['user_id'] != $userId && !in_array($userRole, ['admin', 'super_admin']))) {
            return false;
        }
        
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
    } catch(Exception $e) {
        error_log("Delete post error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update comment
 */
function updateComment($pdo, $commentId, $userId, $content, $userRole = 'user') {
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
        
        if ($result) {
            logActivity($pdo, $userId, 'edit_comment', 'comment', $commentId, 'Edited comment');
        }
        
        return $result;
    } catch(Exception $e) {
        error_log("Update comment error: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete comment
 */
function deleteComment($pdo, $commentId, $userId, $userRole = 'user') {
    try {
        // Check if user can delete this comment
        $stmt = $pdo->prepare("SELECT user_id, post_id FROM post_comments WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        if (!$comment || ($comment['user_id'] != $userId && !in_array($userRole, ['admin', 'super_admin']))) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            UPDATE post_comments 
            SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$userId, $commentId]);
        
        if ($result) {
            logActivity($pdo, $userId, 'delete_comment', 'comment', $commentId, 'Deleted comment');
        }
        
        return $result;
    } catch(Exception $e) {
        error_log("Delete comment error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get post comments with pagination
 */
function getPostComments($pdo, $postId, $limit = 20, $offset = 0) {
    try {
        $query = "
            SELECT c.*, u.username, u.name, u.mi, u.surname,
                   CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                   u.profile_image, u.position, d.department_code,
                   (SELECT COUNT(*) FROM post_comments replies WHERE replies.parent_comment_id = c.id AND replies.is_deleted = 0) as reply_count
            FROM post_comments c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE c.post_id = ? AND c.is_deleted = 0 AND c.parent_comment_id IS NULL
            ORDER BY c.created_at ASC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$postId, $limit, $offset]);
        
        $comments = $stmt->fetchAll();
        
        // Get replies for each comment
        foreach ($comments as &$comment) {
            $comment['replies'] = getCommentReplies($pdo, $comment['id'], 5);
        }
        
        return $comments;
    } catch(Exception $e) {
        error_log("Get post comments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get comment replies
 */
function getCommentReplies($pdo, $parentCommentId, $limit = 5) {
    try {
        $query = "
            SELECT c.*, u.username, u.name, u.mi, u.surname,
                   CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                   u.profile_image, u.position
            FROM post_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.parent_comment_id = ? AND c.is_deleted = 0
            ORDER BY c.created_at ASC
            LIMIT ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$parentCommentId, $limit]);
        
        return $stmt->fetchAll();
    } catch(Exception $e) {
        error_log("Get comment replies error: " . $e->getMessage());
        return [];
    }
}

// --------------------------------------------------------
// LIKE/REACTION FUNCTIONS
// --------------------------------------------------------

/**
 * Toggle like on post or comment
 */
function toggleLike($pdo, $userId, $postId = null, $commentId = null, $reactionType = 'like') {
    try {
        $target = $postId ? 'post_id' : 'comment_id';
        $targetValue = $postId ?: $commentId;
        
        // Check if user already liked/reacted
        $stmt = $pdo->prepare("SELECT id, reaction_type FROM post_likes WHERE {$target} = ? AND user_id = ?");
        $stmt->execute([$targetValue, $userId]);
        $existingLike = $stmt->fetch();
        
        if ($existingLike) {
            if ($existingLike['reaction_type'] === $reactionType) {
                // Remove like
                $stmt = $pdo->prepare("DELETE FROM post_likes WHERE id = ?");
                $result = $stmt->execute([$existingLike['id']]);
                $action = 'unliked';
            } else {
                // Update reaction type
                $stmt = $pdo->prepare("UPDATE post_likes SET reaction_type = ? WHERE id = ?");
                $result = $stmt->execute([$reactionType, $existingLike['id']]);
                $action = 'changed_reaction';
            }
        } else {
            // Add new like
            $stmt = $pdo->prepare("INSERT INTO post_likes ({$target}, user_id, reaction_type) VALUES (?, ?, ?)");
            $result = $stmt->execute([$targetValue, $userId, $reactionType]);
            $action = 'liked';
            
            // Send notification
            if ($result && $postId) {
                $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
                $stmt->execute([$postId]);
                $post = $stmt->fetch();
                
                if ($post && $post['user_id'] != $userId) {
                    sendLikeNotification($pdo, $postId, null, $userId, $post['user_id'], $reactionType);
                }
            } elseif ($result && $commentId) {
                $stmt = $pdo->prepare("SELECT user_id, post_id FROM post_comments WHERE id = ?");
                $stmt->execute([$commentId]);
                $comment = $stmt->fetch();
                
                if ($comment && $comment['user_id'] != $userId) {
                    sendLikeNotification($pdo, $comment['post_id'], $commentId, $userId, $comment['user_id'], $reactionType);
                }
            }
        }
        
        if ($result) {
            logActivity($pdo, $userId, $action, $postId ? 'post' : 'comment', $targetValue, 
                       "User {$action} " . ($postId ? 'post' : 'comment'));
        }
        
        return $result;
    } catch(Exception $e) {
        error_log("Toggle like error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get post/comment likes
 */
function getLikes($pdo, $postId = null, $commentId = null) {
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
    } catch(Exception $e) {
        error_log("Get likes error: " . $e->getMessage());
        return [];
    }
}

// --------------------------------------------------------
// MEDIA ATTACHMENT FUNCTIONS
// --------------------------------------------------------

/**
 * Add media attachment to post
 */
function addPostMedia($pdo, $postId, $mediaType, $filePath = null, $fileName = null, $originalName = null, $fileSize = null, $mimeType = null, $url = null, $urlTitle = null, $urlDescription = null, $sortOrder = 0) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO post_media (post_id, media_type, file_path, file_name, original_name, file_size, mime_type, url, url_title, url_description, sort_order) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $postId, $mediaType, $filePath, $fileName, $originalName, $fileSize, $mimeType, $url, $urlTitle, $urlDescription, $sortOrder
        ]);
    } catch(Exception $e) {
        error_log("Add post media error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get post media attachments
 */
function getPostMedia($pdo, $postId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM post_media 
            WHERE post_id = ? 
            ORDER BY sort_order ASC, created_at ASC
        ");
        $stmt->execute([$postId]);
        
        return $stmt->fetchAll();
    } catch(Exception $e) {
        error_log("Get post media error: " . $e->getMessage());
        return [];
    }
}

// --------------------------------------------------------
// VIEW TRACKING FUNCTIONS
// --------------------------------------------------------

/**
 * Mark post as viewed
 */
function markPostAsViewed($pdo, $postId, $userId) {
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
    } catch(Exception $e) {
        error_log("Mark post as viewed error: " . $e->getMessage());
        return false;
    }
}

// --------------------------------------------------------
// NOTIFICATION FUNCTIONS
// --------------------------------------------------------

/**
 * Send post notifications to followers/department members
 */
function sendPostNotifications($pdo, $postId, $authorId, $type = 'new_post') {
    try {
        // Get post details
        $stmt = $pdo->prepare("SELECT content, visibility, target_departments FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        if (!$post) return false;
        
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
                insertPostNotification($pdo, $user['id'], $postId, null, $authorId, $type, $message);
            }
        }
        
        return true;
    } catch(Exception $e) {
        error_log("Send post notifications error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send department-specific post notifications
 */
function sendDepartmentPostNotifications($pdo, $postId, $authorId, $targetDepartments) {
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
            insertPostNotification($pdo, $user['id'], $postId, null, $authorId, 'new_post', $message);
        }
        
        return true;
    } catch(Exception $e) {
        error_log("Send department post notifications error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send custom post notifications to specific users
 */
function sendCustomPostNotifications($pdo, $postId, $authorId, $targetUsers) {
    try {
        $message = "You've been mentioned in a post";
        
        foreach ($targetUsers as $userId) {
            if ($userId != $authorId) {
                insertPostNotification($pdo, $userId, $postId, null, $authorId, 'new_post', $message);
            }
        }
        
        return true;
    } catch(Exception $e) {
        error_log("Send custom post notifications error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send comment notification
 */
function sendCommentNotification($pdo, $postId, $commentId, $commenterId, $postAuthorId) {
    try {
        $message = "Someone commented on your post";
        return insertPostNotification($pdo, $postAuthorId, $postId, $commentId, $commenterId, 'post_comment', $message);
    } catch(Exception $e) {
        error_log("Send comment notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send reply notification
 */
function sendReplyNotification($pdo, $postId, $commentId, $replierId, $parentCommenterId) {
    try {
        $message = "Someone replied to your comment";
        return insertPostNotification($pdo, $parentCommenterId, $postId, $commentId, $replierId, 'comment_reply', $message);
    } catch(Exception $e) {
        error_log("Send reply notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send like notification
 */
function sendLikeNotification($pdo, $postId, $commentId, $likerId, $targetUserId, $reactionType) {
    try {
        $message = $commentId ? "Someone liked your comment" : "Someone liked your post";
        $type = $commentId ? 'comment_like' : 'post_like';
        
        return insertPostNotification($pdo, $targetUserId, $postId, $commentId, $likerId, $type, $message);
    } catch(Exception $e) {
        error_log("Send like notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Insert post notification
 */
function insertPostNotification($pdo, $userId, $postId, $commentId, $triggeredBy, $type, $message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO post_notifications (user_id, post_id, comment_id, triggered_by, notification_type, message) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$userId, $postId, $commentId, $triggeredBy, $type, $message]);
    } catch(Exception $e) {
        error_log("Insert post notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's post notifications
 */
function getPostNotifications($pdo, $userId, $limit = 20, $offset = 0, $unreadOnly = false) {
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
    } catch(Exception $e) {
        error_log("Get post notifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE post_notifications 
            SET is_read = 1, read_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$notificationId, $userId]);
    } catch(Exception $e) {
        error_log("Mark notification as read error: " . $e->getMessage());
        return false;
    }
}

// --------------------------------------------------------
// UTILITY FUNCTIONS
// --------------------------------------------------------

/**
 * Format time ago
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31104000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31104000) . ' years ago';
}

/**
 * Get post engagement stats
 */
function getPostStats($pdo, $postId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                like_count,
                comment_count,
                view_count,
                share_count,
                (SELECT COUNT(DISTINCT reaction_type) FROM post_likes WHERE post_id = ?) as reaction_types
            FROM posts 
            WHERE id = ?
        ");
        $stmt->execute([$postId, $postId]);
        
        return $stmt->fetch();
    } catch(Exception $e) {
        error_log("Get post stats error: " . $e->getMessage());
        return null;
    }
}

/**
 * Search posts
 */
function searchPosts($pdo, $query, $userId, $departmentId, $limit = 20, $offset = 0) {
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
    } catch(Exception $e) {
        error_log("Search posts error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get trending posts (most liked/commented in last 7 days)
 */
function getTrendingPosts($pdo, $userId, $departmentId, $limit = 10) {
    try {
        $query = "
            SELECT p.*, u.username, u.name, u.mi, u.surname,
                   CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
                   u.profile_image, u.position, d.department_code,
                   (p.like_count * 2 + p.comment_count * 3 + p.view_count * 0.1) as engagement_score,
                   EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as user_liked
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE p.is_deleted = 0
            AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND (
                p.visibility = 'public' OR
                p.user_id = ? OR
                (p.visibility = 'department' AND JSON_CONTAINS(p.target_departments, CAST(? AS JSON))) OR
                (p.visibility = 'custom' AND JSON_CONTAINS(p.target_users, CAST(? AS JSON)))
            )
            ORDER BY engagement_score DESC, p.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $userId, $departmentId, $userId, $limit]);
        
        return $stmt->fetchAll();
    } catch(Exception $e) {
        error_log("Get trending posts error: " . $e->getMessage());
        return [];
    }
}

?>
