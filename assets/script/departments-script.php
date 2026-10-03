<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Require super admin access
$currentUser = requireSuperAdmin();

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'create':
                $department_code = strtoupper(sanitizeInput($_POST['department_code']));
                $department_name = sanitizeInput($_POST['department_name']);
                $description = sanitizeInput($_POST['description'] ?? '');
                $head_of_department = sanitizeInput($_POST['head_of_department'] ?? '');
                $contact_email = sanitizeInput($_POST['contact_email'] ?? '');
                $contact_phone = sanitizeInput($_POST['contact_phone'] ?? '');
                
                // Validation
                if (empty($department_code) || empty($department_name)) {
                    throw new Exception('Department code and name are required.');
                }
                
                if (strlen($department_code) > 10) {
                    throw new Exception('Department code must be 10 characters or less.');
                }
                
                if (!empty($contact_email) && !validateEmail($contact_email)) {
                    throw new Exception('Invalid email address.');
                }
                
                // Check if department code already exists
                $stmt = $pdo->prepare("SELECT id FROM departments WHERE department_code = ?");
                $stmt->execute([$department_code]);
                if ($stmt->fetch()) {
                    throw new Exception('Department code already exists.');
                }
                
                // Create department
                $stmt = $pdo->prepare("
                    INSERT INTO departments (department_code, department_name, description, 
                                           head_of_department, contact_email, contact_phone) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $department_code, $department_name, $description, 
                    $head_of_department, $contact_email, $contact_phone
                ]);
                
                $deptId = $pdo->lastInsertId();
                
                // Log activity
                logActivity($pdo, $currentUser['id'], 'create_department', 'department', $deptId, 
                          "Created department: $department_name ($department_code)");
                
                $message = 'Department created successfully!';
                break;
                
            case 'update':
                $deptId = intval($_POST['department_id']);
                $department_code = strtoupper(sanitizeInput($_POST['department_code']));
                $department_name = sanitizeInput($_POST['department_name']);
                $description = sanitizeInput($_POST['description'] ?? '');
                $head_of_department = sanitizeInput($_POST['head_of_department'] ?? '');
                $contact_email = sanitizeInput($_POST['contact_email'] ?? '');
                $contact_phone = sanitizeInput($_POST['contact_phone'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // Validation
                if (empty($department_code) || empty($department_name)) {
                    throw new Exception('Department code and name are required.');
                }
                
                if (!empty($contact_email) && !validateEmail($contact_email)) {
                    throw new Exception('Invalid email address.');
                }
                
                // Check if department code already exists for another department
                $stmt = $pdo->prepare("SELECT id FROM departments WHERE department_code = ? AND id != ?");
                $stmt->execute([$department_code, $deptId]);
                if ($stmt->fetch()) {
                    throw new Exception('Department code already exists for another department.');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE departments SET department_code = ?, department_name = ?, description = ?, 
                                         head_of_department = ?, contact_email = ?, contact_phone = ?, 
                                         is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $department_code, $department_name, $description, 
                    $head_of_department, $contact_email, $contact_phone, 
                    $is_active, $deptId
                ]);
                
                // Log activity
                logActivity($pdo, $currentUser['id'], 'update_department', 'department', $deptId, 
                          "Updated department: $department_name ($department_code)");
                
                $message = 'Department updated successfully!';
                break;
                
            case 'delete':
                $deptId = intval($_POST['department_id']);
                
                // Get department info before deletion
                $stmt = $pdo->prepare("SELECT department_name, department_code FROM departments WHERE id = ?");
                $stmt->execute([$deptId]);
                $dept = $stmt->fetch();
                
                if (!$dept) {
                    throw new Exception('Department not found.');
                }
                
                // Check if department has users
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE department_id = ?");
                $stmt->execute([$deptId]);
                $userCount = $stmt->fetch()['count'];
                
                if ($userCount > 0) {
                    throw new Exception("Cannot delete department. It has $userCount user(s) assigned to it.");
                }
                
                // Check if department has folders
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM folders WHERE department_id = ? AND is_deleted = 0");
                $stmt->execute([$deptId]);
                $folderCount = $stmt->fetch()['count'];
                
                if ($folderCount > 0) {
                    throw new Exception("Cannot delete department. It has $folderCount folder(s) assigned to it.");
                }
                
                // Delete department
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                $stmt->execute([$deptId]);
                
                // Log activity
                logActivity($pdo, $currentUser['id'], 'delete_department', 'department', $deptId, 
                          "Deleted department: {$dept['department_name']} ({$dept['department_code']})");
                
                $message = 'Department deleted successfully!';
                break;
                
            case 'toggle_status':
                $deptId = intval($_POST['department_id']);
                
                // Get current status
                $stmt = $pdo->prepare("SELECT is_active, department_name FROM departments WHERE id = ?");
                $stmt->execute([$deptId]);
                $dept = $stmt->fetch();
                
                if (!$dept) {
                    throw new Exception('Department not found.');
                }
                
                $newStatus = $dept['is_active'] ? 0 : 1;
                
                $stmt = $pdo->prepare("UPDATE departments SET is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $deptId]);
                
                $statusText = $newStatus ? 'activated' : 'deactivated';
                logActivity($pdo, $currentUser['id'], 'toggle_department_status', 'department', $deptId, 
                          "Department {$dept['department_name']} $statusText");
                
                $message = "Department $statusText successfully!";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get departments with statistics
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$page = intval($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(d.department_name LIKE ? OR d.department_code LIKE ? OR d.head_of_department LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($status_filter === 'active') {
    $where[] = "d.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where[] = "d.is_active = 0";
}

$whereClause = implode(' AND ', $where);

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM departments d WHERE $whereClause");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$totalPages = ceil($total / $limit);

// Get departments with statistics
$stmt = $pdo->prepare("
    SELECT d.*, 
           COUNT(DISTINCT u.id) as user_count,
           COUNT(DISTINCT f.id) as folder_count,
           COALESCE(SUM(CASE WHEN fi.is_deleted = 0 THEN 1 ELSE 0 END), 0) as file_count,
           COALESCE(SUM(CASE WHEN fi.is_deleted = 0 THEN fi.file_size ELSE 0 END), 0) as total_size
    FROM departments d
    LEFT JOIN users u ON d.id = u.department_id AND u.is_approved = 1
    LEFT JOIN folders f ON d.id = f.department_id AND f.is_deleted = 0
    LEFT JOIN files fi ON f.id = fi.folder_id AND fi.is_deleted = 0
    WHERE $whereClause
    GROUP BY d.id
    ORDER BY d.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$departments = $stmt->fetchAll();

// Get counts for filters
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM departments
");
$counts = $stmt->fetch();

// Format file size function
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>