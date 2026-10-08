<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, department_name, department_code, description FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return array directly for compatibility with existing JS
    echo json_encode($departments);
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
