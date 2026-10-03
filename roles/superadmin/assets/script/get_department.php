<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Require super admin access
requireSuperAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid department ID']);
    exit();
}

$deptId = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$deptId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        http_response_code(404);
        echo json_encode(['error' => 'Department not found']);
        exit();
    }
    
    echo json_encode($department);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>