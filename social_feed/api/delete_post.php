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
    $userRole = $_SESSION['user_role'] ?? 'user';
    $postId = intval($input['id'] ?? 0);
    
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'Post ID is required']);
        exit;
    }
    
    $result = deletePost($pdo, $postId, $userId, $userRole);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Post deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to delete post or insufficient permissions'
        ]);
    }
    
} catch(Exception $e) {
    error_log("Delete post error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting post: ' . $e->getMessage()
    ]);
}
?>