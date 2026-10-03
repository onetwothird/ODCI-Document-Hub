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
    $userRole = $_SESSION['user_role'] ?? 'user';
    $commentId = intval($input['comment_id'] ?? 0);
    
    if (!$commentId) {
        echo json_encode(['success' => false, 'message' => 'Comment ID is required']);
        exit;
    }
    
    $result = deleteComment($pdo, $commentId, $userId, $userRole);
    
    if ($result) {
        // Get post ID to update comment count
        $stmt = $pdo->prepare("SELECT post_id FROM post_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        if ($comment) {
            // Update comment count for the post
            $stmt = $pdo->prepare("UPDATE posts SET comment_count = comment_count - 1 WHERE id = ?");
            $stmt->execute([$comment['post_id']]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted successfully',
            'post_id' => $comment['post_id'] ?? null
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to delete comment or insufficient permissions'
        ]);
    }
    
} catch(Exception $e) {
    error_log("Delete comment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting comment: ' . $e->getMessage()
    ]);
}
?>