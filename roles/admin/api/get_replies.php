// get_replies.php
<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $parentCommentId = intval($_GET['parent_comment_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 10);
    $offset = intval($_GET['offset'] ?? 0);

    if (!$parentCommentId) {
        echo json_encode(['success' => false, 'message' => 'Parent comment ID is required']);
        exit;
    }

    // Verify parent comment exists
    $stmt = $pdo->prepare("SELECT id, post_id FROM post_comments WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$parentCommentId]);
    $parentComment = $stmt->fetch();

    if (!$parentComment) {
        echo json_encode(['success' => false, 'message' => 'Parent comment not found']);
        exit;
    }

    // Get replies
    $query = "
        SELECT c.*, u.username, u.name, u.mi, u.surname,
               CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
               u.profile_image, u.position
        FROM post_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.parent_comment_id = ? AND c.is_deleted = 0
        ORDER BY c.created_at ASC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$parentCommentId, $limit, $offset]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if user liked each reply
    $userId = $_SESSION['user_id'];
    foreach ($replies as &$reply) {
        $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$reply['id'], $userId]);
        $reply['user_liked'] = $stmt->fetch() ? true : false;
    }

    echo json_encode([
        'success' => true,
        'replies' => $replies,
        'has_more' => count($replies) === $limit
    ]);

} catch (Exception $e) {
    error_log("Get replies error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading replies: ' . $e->getMessage()
    ]);
}
?>