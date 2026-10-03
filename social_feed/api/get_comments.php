<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';
require_once '../social_feed-script.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $postId = intval($_GET['post_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);

    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'Post ID is required']);
        exit;
    }

    // Verify post exists and user can view it
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    // Get comments using the CommentManager
    $comments = CommentManager::getPostComments($pdo, $postId, $limit, $offset);

    // Get actual comment count for this post
    $actualCount = CommentManager::getActualCommentCount($pdo, $postId);

    // Check if user liked each comment and reply
    $userId = $_SESSION['user_id'];
    foreach ($comments as &$comment) {
        // Check if user liked this comment
        $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$comment['id'], $userId]);
        $comment['user_liked'] = $stmt->fetch() ? true : false;

        // Also check replies for likes
        if (isset($comment['replies']) && is_array($comment['replies'])) {
            foreach ($comment['replies'] as &$reply) {
                $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?");
                $stmt->execute([$reply['id'], $userId]);
                $reply['user_liked'] = $stmt->fetch() ? true : false;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'total_comments' => $actualCount,
        'has_more' => count($comments) === $limit,
        'comment_count' => count($comments),
        'offset' => $offset,
        'limit' => $limit
    ]);

} catch (Exception $e) {
    error_log("Get comments error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading comments: ' . $e->getMessage(),
        'comments' => [],
        'total_comments' => 0
    ]);
}
?>