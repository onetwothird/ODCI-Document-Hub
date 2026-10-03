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
                    $username = sanitizeInput($_POST['username']);
                    $email = sanitizeInput($_POST['email']);
                    $password = $_POST['password'];
                    $name = sanitizeInput($_POST['name']);
                    $mi = sanitizeInput($_POST['mi'] ?? '');
                    $surname = sanitizeInput($_POST['surname']);
                    $employee_id = sanitizeInput($_POST['employee_id']);
                    $position = sanitizeInput($_POST['position']);
                    $department_id = intval($_POST['department_id']);
                    $role = sanitizeInput($_POST['role']);
                    $phone = sanitizeInput($_POST['phone'] ?? '');
                    $address = sanitizeInput($_POST['address'] ?? '');
                    
                    // Validation
                    if (empty($username) || empty($email) || empty($password) || empty($name) || empty($surname)) {
                        throw new Exception('All required fields must be filled.');
                    }
                    
                    if (!validateEmail($email)) {
                        throw new Exception('Invalid email address.');
                    }
                    
                    if (!validatePassword($password)) {
                        throw new Exception('Password must be at least 8 characters with uppercase, lowercase, and number.');
                    }
                    
                    // Check if username or email already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        throw new Exception('Username or email already exists.');
                    }
                    
                    // Check if employee ID already exists (if provided)
                    if (!empty($employee_id)) {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
                        $stmt->execute([$employee_id]);
                        if ($stmt->fetch()) {
                            throw new Exception('Employee ID already exists.');
                        }
                    }
                    
                    // Create user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, name, mi, surname, employee_id, position, 
                                        department_id, role, phone, address, is_approved, email_verified, 
                                        created_by, approved_by, approved_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $username, $email, $hashedPassword, $name, $mi, $surname, $employee_id, 
                        $position, $department_id, $role, $phone, $address, 
                        $currentUser['id'], $currentUser['id']
                    ]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Log activity
                    logActivity($pdo, $currentUser['id'], 'create_user', 'user', $userId, 
                            "Created user: $name $surname", ['role' => $role, 'department_id' => $department_id]);
                    
                    $message = 'User created successfully!';
                    break;
                    
                case 'update':
                    $userId = intval($_POST['user_id']);
                    $name = sanitizeInput($_POST['name']);
                    $mi = sanitizeInput($_POST['mi'] ?? '');
                    $surname = sanitizeInput($_POST['surname']);
                    $employee_id = sanitizeInput($_POST['employee_id']);
                    $position = sanitizeInput($_POST['position']);
                    $department_id = intval($_POST['department_id']);
                    $role = sanitizeInput($_POST['role']);
                    $phone = sanitizeInput($_POST['phone'] ?? '');
                    $address = sanitizeInput($_POST['address'] ?? '');
                    $is_approved = isset($_POST['is_approved']) ? 1 : 0;
                    $is_restricted = isset($_POST['is_restricted']) ? 1 : 0;
                    
                    // Check if employee ID already exists for another user
                    if (!empty($employee_id)) {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ? AND id != ?");
                        $stmt->execute([$employee_id, $userId]);
                        if ($stmt->fetch()) {
                            throw new Exception('Employee ID already exists for another user.');
                        }
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE users SET name = ?, mi = ?, surname = ?, employee_id = ?, position = ?, 
                                        department_id = ?, role = ?, phone = ?, address = ?, 
                                        is_approved = ?, is_restricted = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $mi, $surname, $employee_id, $position, $department_id, 
                        $role, $phone, $address, $is_approved, $is_restricted, $userId
                    ]);
                    
                    // Log activity
                    logActivity($pdo, $currentUser['id'], 'update_user', 'user', $userId, 
                            "Updated user: $name $surname");
                    
                    $message = 'User updated successfully!';
                    break;
                    
                case 'delete':
                    $userId = intval($_POST['user_id']);
                    
                    // Get user info before deletion
                    $stmt = $pdo->prepare("SELECT name, mi, surname FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        throw new Exception('User not found.');
                    }
                    
                    // Don't allow deleting super admin
                    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $userRole = $stmt->fetch();
                    
                    if ($userRole['role'] === 'super_admin') {
                        throw new Exception('Cannot delete super admin account.');
                    }
                    
                    // Soft delete - update files and folders to system user
                    $pdo->beginTransaction();
                    
                    // Update files
                    $stmt = $pdo->prepare("UPDATE files SET uploaded_by = 1 WHERE uploaded_by = ?");
                    $stmt->execute([$userId]);
                    
                    // Update folders
                    $stmt = $pdo->prepare("UPDATE folders SET created_by = 1 WHERE created_by = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete user
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    $pdo->commit();
                    
                    // Log activity
                    $fullName = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
                    logActivity($pdo, $currentUser['id'], 'delete_user', 'user', $userId, 
                            "Deleted user: $fullName");
                    
                    $message = 'User deleted successfully!';
                    break;
                    
                case 'approve':
                    $userId = intval($_POST['user_id']);
                    
                    $stmt = $pdo->prepare("
                        UPDATE users SET is_approved = 1, approved_by = ?, approved_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUser['id'], $userId]);
                    
                    // Get user info for logging
                    $stmt = $pdo->prepare("SELECT name, mi, surname FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $fullName = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
                        logActivity($pdo, $currentUser['id'], 'approve_user', 'user', $userId, 
                                "Approved user: $fullName");
                    }
                    
                    $message = 'User approved successfully!';
                    break;
                    
                case 'reset_password':
                    $userId = intval($_POST['user_id']);
                    $newPassword = $_POST['new_password'];
                    
                    if (!validatePassword($newPassword)) {
                        throw new Exception('Password must be at least 8 characters with uppercase, lowercase, and number.');
                    }
                    
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    // Get user info for logging
                    $stmt = $pdo->prepare("SELECT name, mi, surname FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $fullName = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
                        logActivity($pdo, $currentUser['id'], 'reset_password', 'user', $userId, 
                                "Reset password for user: $fullName");
                    }
                    
                    $message = 'Password reset successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    // Get filters
    $search = $_GET['search'] ?? '';
    $filter = $_GET['filter'] ?? 'all';
    $department_filter = $_GET['department'] ?? '';
    $role_filter = $_GET['role'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Build query
    $where = ["1=1"];
    $params = [];

    if (!empty($search)) {
        $where[] = "(u.name LIKE ? OR u.surname LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    }

    if ($filter === 'pending') {
        $where[] = "u.is_approved = 0";
    } elseif ($filter === 'approved') {
        $where[] = "u.is_approved = 1";
    } elseif ($filter === 'restricted') {
        $where[] = "u.is_restricted = 1";
    }

    if (!empty($department_filter)) {
        $where[] = "u.department_id = ?";
        $params[] = $department_filter;
    }

    if (!empty($role_filter)) {
        $where[] = "u.role = ?";
        $params[] = $role_filter;
    }

    $whereClause = implode(' AND ', $where);

    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users u WHERE $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    $totalPages = ceil($total / $limit);

    // Get users
    $stmt = $pdo->prepare("
        SELECT u.*, d.department_name, d.department_code,
            approver.name as approver_name, approver.mi as approver_mi, approver.surname as approver_surname
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN users approver ON u.approved_by = approver.id
        WHERE $whereClause
        ORDER BY u.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Get departments for dropdowns
    $stmt = $pdo->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name");
    $departments = $stmt->fetchAll();

    // Get counts for filters
    $counts = [
        'all' => 0,
        'pending' => 0,
        'approved' => 0,
        'restricted' => 0
    ];

    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN is_restricted = 1 THEN 1 ELSE 0 END) as restricted
        FROM users
    ");
    $countData = $stmt->fetch();
    $counts['all'] = $countData['total'];
    $counts['pending'] = $countData['pending'];
    $counts['approved'] = $countData['approved'];
    $counts['restricted'] = $countData['restricted'];
?>