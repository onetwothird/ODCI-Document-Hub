<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM post_notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'count' => intval($result['count'] ?? 0)
    ]);
    
} catch(Exception $e) {
    error_log("Get notification count error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error getting notification count'
    ]);
}
?>