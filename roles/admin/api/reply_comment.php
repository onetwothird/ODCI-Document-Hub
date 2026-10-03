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
    $parentCommentId = intval($input['parent_comment_id'] ?? 0);
    $content = trim($input['content'] ?? '');
    
    if (!$parentCommentId || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Parent comment ID and content are required']);
        exit;
    }
    
    // Get post ID from parent comment
    $stmt = $pdo->prepare("SELECT post_id FROM post_comments WHERE id = ?");
    $stmt->execute([$parentCommentId]);
    $parentComment = $stmt->fetch();
    
    if (!$parentComment) {
        echo json_encode(['success' => false, 'message' => 'Parent comment not found']);
        exit;
    }
    
    $postId = $parentComment['post_id'];
    
    // Create reply
    $stmt = $pdo->prepare("
        INSERT INTO post_comments (post_id, user_id, parent_comment_id, content) 
        VALUES (?, ?, ?, ?)
    ");

    $result = $stmt->execute([$postId, $userId, $parentCommentId, $content]);

    if ($result) {
        $commentId = $pdo->lastInsertId();

        // Update comment count
        $stmt = $pdo->prepare("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?");
        $stmt->execute([$postId]);

        // Log activity
        logActivity($pdo, $userId, 'add_reply', 'comment', $commentId, 'Added reply to comment', [
            'post_id' => $postId,
            'parent_comment_id' => $parentCommentId
        ]);
        
        // Send notification to parent comment author
        $stmt = $pdo->prepare("SELECT user_id FROM post_comments WHERE id = ?");
        $stmt->execute([$parentCommentId]);
        $parentCommentAuthor = $stmt->fetch();
        
        if ($parentCommentAuthor && $parentCommentAuthor['user_id'] != $userId) {
            sendReplyNotification($pdo, $postId, $commentId, $userId, $parentCommentAuthor['user_id']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Reply added successfully',
            'comment_id' => $commentId,
            'post_id' => $postId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add reply']);
    }

} catch (Exception $e) {
    error_log("Add reply error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error adding reply: ' . $e->getMessage()
    ]);
}
?>