<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $postId = intval($_GET['id'] ?? 0);
    $userId = $_SESSION['user_id'];
    
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'Post ID is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.name, u.mi, u.surname,
               CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
               u.profile_image, u.position, d.department_code
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE p.id = ? AND p.is_deleted = 0
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if ($post) {
        // Check if user has permission to view this post
        $canView = false;
        
        if ($post['visibility'] === 'public' || $post['user_id'] == $userId) {
            $canView = true;
        } elseif ($post['visibility'] === 'department') {
            // Check if user is in target department
            $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userDept = $stmt->fetch();
            
            if ($userDept && $post['target_departments']) {
                $targetDepts = json_decode($post['target_departments'], true);
                $canView = in_array($userDept['department_id'], $targetDepts);
            }
        } elseif ($post['visibility'] === 'custom') {
            // Check if user is in target users
            if ($post['target_users']) {
                $targetUsers = json_decode($post['target_users'], true);
                $canView = in_array($userId, $targetUsers);
            }
        }
        
        if ($canView) {
            echo json_encode([
                'success' => true,
                'post' => $post
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to view this post'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Post not found'
        ]);
    }
    
} catch(Exception $e) {
    error_log("Get post error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving post: ' . $e->getMessage()
    ]);
}
?>