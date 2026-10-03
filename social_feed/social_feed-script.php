<?php

// Include all manager classes
require_once __DIR__ . '/managers/PostManager.php';
require_once __DIR__ . '/managers/CommentManager.php';
require_once __DIR__ . '/managers/ReactionManager.php';
require_once __DIR__ . '/managers/MediaManager.php';
require_once __DIR__ . '/managers/NotificationManager.php';
require_once __DIR__ . '/managers/UtilityManager.php';

// Backward compatibility wrapper functions
// These functions maintain the original function names while delegating to the new classes

// --------------------------------------------------------
// POST FUNCTIONS - Backward Compatibility Wrappers
// --------------------------------------------------------

/**
 * Create a new post
 */
function createPost($pdo, $userId, $content, $contentType = 'text', $visibility = 'public', $targetDepartments = null, $targetUsers = null, $isPinned = false)
{
    return PostManager::createPost($pdo, $userId, $content, $contentType, $visibility, $targetDepartments, $targetUsers, $isPinned);
}

/**
 * Update post
 */
function updatePost($pdo, $postId, $userId, $content, $userRole = 'user')
{
    return PostManager::updatePost($pdo, $postId, $userId, $content, $userRole);
}

/**
 * Delete post
 */
function deletePost($pdo, $postId, $userId, $userRole = 'user')
{
    return PostManager::deletePost($pdo, $postId, $userId, $userRole);
}

/**
 * Get post engagement stats
 */
function getPostStats($pdo, $postId)
{
    return PostManager::getPostStats($pdo, $postId);
}

/**
 * Search posts
 */
function searchPosts($pdo, $query, $userId, $departmentId, $limit = 20, $offset = 0)
{
    return PostManager::searchPosts($pdo, $query, $userId, $departmentId, $limit, $offset);
}

/**
 * Mark post as viewed
 */
function markPostAsViewed($pdo, $postId, $userId)
{
    return PostManager::markPostAsViewed($pdo, $postId, $userId);
}

// --------------------------------------------------------
// COMMENT FUNCTIONS - Backward Compatibility Wrappers
// --------------------------------------------------------

/**
 * Get post comments with pagination
 */
function getPostComments($pdo, $postId, $limit = 20, $offset = 0)
{
    return CommentManager::getPostComments($pdo, $postId, $limit, $offset);
}

/**
 * Get comment replies
 */
function getCommentReplies($pdo, $parentCommentId, $limit = 5)
{
    return CommentManager::getCommentReplies($pdo, $parentCommentId, $limit);
}

/**
 * Update comment
 */
function updateComment($pdo, $commentId, $userId, $content, $userRole = 'user')
{
    return CommentManager::updateComment($pdo, $commentId, $userId, $content, $userRole);
}

/**
 * Delete comment
 */
function deleteComment($pdo, $commentId, $userId, $userRole = 'user')
{
    return CommentManager::deleteComment($pdo, $commentId, $userId, $userRole);
}

// --------------------------------------------------------
// LIKE/REACTION FUNCTIONS - Backward Compatibility Wrappers
// --------------------------------------------------------

/**
 * Toggle like on post or comment
 */
function toggleLike($pdo, $userId, $postId = null, $commentId = null)
{
    return ReactionManager::toggleLike($pdo, $userId, $postId, $commentId);
}

/**
 * Get post/comment likes
 */
function getLikes($pdo, $postId = null, $commentId = null)
{
    return ReactionManager::getLikes($pdo, $postId, $commentId);
}

// --------------------------------------------------------
// MEDIA ATTACHMENT FUNCTIONS - Backward Compatibility Wrappers
// --------------------------------------------------------

/**
 * Add media attachment to post
 */
function addPostMedia($pdo, $postId, $mediaType, $filePath = null, $fileName = null, $originalName = null, $fileSize = null, $mimeType = null, $sortOrder = 0)
{
    return MediaManager::addPostMedia($pdo, $postId, $mediaType, $filePath, $fileName, $originalName, $fileSize, $mimeType, $sortOrder);
}

/**
 * Get post media attachments
 */
function getPostMedia($pdo, $postId)
{
    return MediaManager::getPostMedia($pdo, $postId);
}

// --------------------------------------------------------
// NOTIFICATION FUNCTIONS - Backward Compatibility Wrappers
// --------------------------------------------------------

/**
 * Send post notifications to followers/department members
 */
function sendPostNotifications($pdo, $postId, $authorId, $type = 'new_post')
{
    return NotificationManager::sendPostNotifications($pdo, $postId, $authorId, $type);
}

/**
 * Send department-specific post notifications
 */
function sendDepartmentPostNotifications($pdo, $postId, $authorId, $targetDepartments)
{
    return NotificationManager::sendDepartmentPostNotifications($pdo, $postId, $authorId, $targetDepartments);
}

/**
 * Send custom post notifications to specific users
 */
function sendCustomPostNotifications($pdo, $postId, $authorId, $targetUsers)
{
    return NotificationManager::sendCustomPostNotifications($pdo, $postId, $authorId, $targetUsers);
}

/**
 * Send comment notification
 */
function sendCommentNotification($pdo, $postId, $commentId, $commenterId, $postAuthorId)
{
    return NotificationManager::sendCommentNotification($pdo, $postId, $commentId, $commenterId, $postAuthorId);
}

/**
 * Send reply notification
 */
function sendReplyNotification($pdo, $postId, $commentId, $replierId, $parentCommenterId)
{
    return NotificationManager::sendReplyNotification($pdo, $postId, $commentId, $replierId, $parentCommenterId);
}

/**
 * Send like notification
 */
function sendLikeNotification($pdo, $postId, $commentId, $likerId, $targetUserId, $reactionType)
{
    return NotificationManager::sendLikeNotification($pdo, $postId, $commentId, $likerId, $targetUserId, $reactionType);
}

/**
 * Insert post notification
 */
function insertPostNotification($pdo, $userId, $postId, $commentId, $triggeredBy, $type, $message)
{
    return NotificationManager::insertPostNotification($pdo, $userId, $postId, $commentId, $triggeredBy, $type, $message);
}

/**
 * Get user's post notifications
 */
function getPostNotifications($pdo, $userId, $limit = 20, $offset = 0, $unreadOnly = false)
{
    return NotificationManager::getPostNotifications($pdo, $userId, $limit, $offset, $unreadOnly);
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($pdo, $notificationId, $userId)
{
    return NotificationManager::markNotificationAsRead($pdo, $notificationId, $userId);
}

// --------------------------------------------------------
// UTILITY FUNCTIONS - Backward Compatibility Wrappers
// --------------------------------------------------------

/**
 * Format time ago
 */
function timeAgo($datetime)
{
    return UtilityManager::timeAgo($datetime);
}

?>