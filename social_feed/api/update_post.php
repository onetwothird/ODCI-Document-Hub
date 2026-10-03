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
    $content = trim($input['content'] ?? '');
    
    if (!$postId || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Post ID and content are required']);
        exit;
    }
    
    $result = updatePost($pdo, $postId, $userId, $content, $userRole);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Post updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update post or insufficient permissions'
        ]);
    }
    
} catch(Exception $e) {
    error_log("Update post error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating post: ' . $e->getMessage()
    ]);
}
?>