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
    $content = trim($input['content'] ?? '');
    
    if (!$commentId || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Comment ID and content are required']);
        exit;
    }
    
    $result = updateComment($pdo, $commentId, $userId, $content, $userRole);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Comment updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update comment or insufficient permissions'
        ]);
    }
    
} catch(Exception $e) {
    error_log("Edit comment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating comment: ' . $e->getMessage()
    ]);
}
?>