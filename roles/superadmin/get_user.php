<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, mi, surname, employee_id, position, department_id, 
               role, phone, address, is_approved, is_restricted, 
               profile_image, email, username
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    unset($user['password']);
    echo json_encode($user);
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
