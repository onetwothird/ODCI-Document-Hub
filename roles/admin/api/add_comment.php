<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';
require_once '../assets/script/social_feed-script.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    $postId = intval($input['post_id'] ?? 0);
    $content = trim($input['content'] ?? '');
    $parentCommentId = isset($input['parent_comment_id']) && $input['parent_comment_id'] ? intval($input['parent_comment_id']) : null;
    
    if (!$postId || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Post ID and content are required']);
        exit;
    }
    
    // Verify post exists and user can access it
    $stmt = $pdo->prepare("SELECT user_id, visibility FROM posts WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    
    // If it's a reply, verify parent comment exists and belongs to the same post
    if ($parentCommentId) {
        $stmt = $pdo->prepare("SELECT post_id, user_id FROM post_comments WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$parentCommentId]);
        $parentComment = $stmt->fetch();
        
        if (!$parentComment || $parentComment['post_id'] != $postId) {
            echo json_encode(['success' => false, 'message' => 'Parent comment not found or invalid']);
            exit;
        }
    }
    
    // Use CommentManager to add the comment
    $commentId = CommentManager::addComment($pdo, $postId, $userId, $content, $parentCommentId);
    
    if ($commentId) {
        // Update post comment count
        $stmt = $pdo->prepare("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?");
        $stmt->execute([$postId]);
        
        // Get the actual current comment count
        $actualCount = CommentManager::getActualCommentCount($pdo, $postId);
        
        // Log activity
        if (function_exists('logActivity')) {
            $activityMessage = $parentCommentId ? 'Added reply to comment' : 'Added comment to post';
            logActivity($pdo, $userId, 'add_comment', 'comment', $commentId, $activityMessage, [
                'post_id' => $postId,
                'parent_comment_id' => $parentCommentId
            ]);
        }
        
        // Send notifications
        try {
            // Send notification to post author (if not commenting on own post)
            if ($post['user_id'] != $userId && function_exists('sendCommentNotification')) {
                sendCommentNotification($pdo, $postId, $commentId, $userId, $post['user_id']);
            }
            
            // If replying to a comment, notify the parent comment author
            if ($parentCommentId && function_exists('sendReplyNotification')) {
                $parentCommentAuthor = $parentComment['user_id'];
                if ($parentCommentAuthor != $userId) {
                    sendReplyNotification($pdo, $postId, $commentId, $userId, $parentCommentAuthor);
                }
            }
        } catch (Exception $notifError) {
            error_log("Notification error: " . $notifError->getMessage());
            // Don't fail the entire operation for notification errors
        }
        
        // Get the newly created comment data for response
        $stmt = $pdo->prepare("
            SELECT c.*, u.username, u.name, u.mi, u.surname,
                   CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                   u.profile_image, u.position,
                   parent.id as parent_id,
                   CONCAT(parent_user.name, ' ', IFNULL(CONCAT(parent_user.mi, '. '), ''), parent_user.surname) as parent_author_name
            FROM post_comments c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN post_comments parent ON c.parent_comment_id = parent.id
            LEFT JOIN users parent_user ON parent.user_id = parent_user.id
            WHERE c.id = ?
        ");
        $stmt->execute([$commentId]);
        $commentData = $stmt->fetch();
        
        $responseMessage = $parentCommentId ? 'Reply added successfully' : 'Comment added successfully';
        
        echo json_encode([
            'success' => true,
            'message' => $responseMessage,
            'comment_id' => $commentId,
            'actual_comment_count' => $actualCount,
            'is_reply' => $parentCommentId !== null,
            'parent_comment_id' => $parentCommentId,
            'comment_data' => $commentData
        ]);
    } else {
        $errorMessage = $parentCommentId ? 'Failed to add reply' : 'Failed to add comment';
        echo json_encode(['success' => false, 'message' => $errorMessage]);
    }

} catch (Exception $e) {
    error_log("Add comment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error adding comment: ' . $e->getMessage()
    ]);
}
?>