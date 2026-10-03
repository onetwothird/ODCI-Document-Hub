<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';
require_once '../social_feed-script.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    $postId = $input['post_id'] ?? null;
    $commentId = $input['comment_id'] ?? null;
    $reactionType = $input['reaction_type'] ?? 'like';
    
    if (!$postId && !$commentId) {
        echo json_encode(['success' => false, 'message' => 'Post ID or Comment ID required']);
        exit;
    }
    
    $result = toggleLike($pdo, $userId, $postId, $commentId, $reactionType);
    
    if ($result) {
        // Get updated like count
        if ($postId) {
            $stmt = $pdo->prepare("SELECT like_count FROM posts WHERE id = ?");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();
            $likeCount = $post['like_count'] ?? 0;
            
            // Update like count
            $stmt = $pdo->prepare("UPDATE posts SET like_count = (SELECT COUNT(*) FROM post_likes WHERE post_id = ?) WHERE id = ?");
            $stmt->execute([$postId, $postId]);
            
            // Check if user liked
            $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);
            $liked = $stmt->fetch() ? true : false;
            
        } else {
            $stmt = $pdo->prepare("SELECT like_count FROM post_comments WHERE id = ?");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();
            $likeCount = $comment['like_count'] ?? 0;
            
            // Update like count
            $stmt = $pdo->prepare("UPDATE post_comments SET like_count = (SELECT COUNT(*) FROM post_likes WHERE comment_id = ?) WHERE id = ?");
            $stmt->execute([$commentId, $commentId]);
            
            // Check if user liked
            $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?");
            $stmt->execute([$commentId, $userId]);
            $liked = $stmt->fetch() ? true : false;
        }
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'like_count' => $likeCount,
            'reacted' => $liked
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to toggle like']);
    }
    
} catch(Exception $e) {
    error_log("Toggle like error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error toggling like: ' . $e->getMessage()
    ]);
}
?>