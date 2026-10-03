<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.name, u.mi, u.surname, u.email, u.position, 
               u.profile_image, u.role, u.department_id, d.department_name, d.department_code,
               CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as full_name
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Remove sensitive information
        unset($user['password']);
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }
} catch(Exception $e) {
    error_log("Get user info error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
?>