<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$postId = $input['post_id'] ?? null;
$action = $input['action'] ?? null;
$reason = $input['reason'] ?? null;

if (!$postId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

try {
    if ($action === 'hide') {
        $stmt = $pdo->prepare("
            UPDATE posts 
            SET is_hidden = 1, moderated_by = ?, moderated_at = NOW(), moderation_reason = ?
            WHERE id = ?
        ");
        $result = $stmt->execute([$_SESSION['user_id'], $reason, $postId]);
    } elseif ($action === 'show') {
        $stmt = $pdo->prepare("
            UPDATE posts 
            SET is_hidden = 0, moderated_by = NULL, moderated_at = NULL, moderation_reason = NULL
            WHERE id = ?
        ");
        $result = $stmt->execute([$postId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
    }

    if ($result) {
        // Log activity
        logActivity($pdo, $_SESSION['user_id'], 'moderate_post', 'post', $postId, 
                   ucfirst($action) . ' post', ['reason' => $reason]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to moderate post']);
    }

} catch (Exception $e) {
    error_log("Error moderating post: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error moderating post']);
}
?>