<?php
    require_once '../../includes/config.php';
    require_once '../../includes/auth_check.php';

    // Require super admin access
    requireSuperAdmin();

    header('Content-Type: application/json');

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user ID']);
        exit();
    }

    $userId = intval($_GET['id']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit();
        }
        
        // Remove sensitive data
        unset($user['password']);
        unset($user['password_reset_token']);
        unset($user['email_verification_token']);
        
        echo json_encode($user);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
?>