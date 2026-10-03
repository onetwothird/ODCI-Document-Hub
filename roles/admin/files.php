<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

// Get current admin's department and role for hierarchical access
$admin_id = $_SESSION['user_id'];
$admin_role = $_SESSION['role'];

// Determine admin's scope - Super admins see everything, regular admins see their department + subordinates
$admin_scope_query = "SELECT department_id, role FROM users WHERE id = ?";
$admin_scope_stmt = $pdo->prepare($admin_scope_query);
$admin_scope_stmt->execute([$admin_id]);
$admin_info = $admin_scope_stmt->fetch(PDO::FETCH_ASSOC);

// Get current page and search parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$file_type_filter = isset($_GET['file_type']) ? $_GET['file_type'] : '';
$user_filter = isset($_GET['user_filter']) ? $_GET['user_filter'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$academic_year_filter = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';
$semester_filter = isset($_GET['semester']) ? $_GET['semester'] : '';

// Build WHERE clause based on admin privileges
$where_conditions = ["1=1"]; // document_files doesn't have is_deleted column based on your schema
$params = [];

// Admin scope filtering - only if department filtering is needed
if ($admin_role !== 'super_admin' && $admin_info['department_id']) {
    $where_conditions[] = "u.department_id = ?";
    $params[] = $admin_info['department_id'];
}

// Search filtering
if (!empty($search)) {
    $where_conditions[] = "(df.file_name LIKE ? OR df.description LIKE ? OR u.name LIKE ? OR u.surname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Department filtering
if (!empty($department_filter)) {
    $where_conditions[] = "u.department_id = ?";
    $params[] = $department_filter;
}

// File type filtering
if (!empty($file_type_filter)) {
    $where_conditions[] = "df.file_type = ?";
    $params[] = $file_type_filter;
}

// User filtering
if (!empty($user_filter)) {
    $where_conditions[] = "df.uploaded_by = ?";
    $params[] = $user_filter;
}

// Academic year filtering
if (!empty($academic_year_filter)) {
    $where_conditions[] = "df.academic_year = ?";
    $params[] = $academic_year_filter;
}

// Semester filtering
if (!empty($semester_filter)) {
    $where_conditions[] = "df.semester_period = ?";
    $params[] = $semester_filter;
}

// Date filtering
if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(df.uploaded_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "df.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "df.uploaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'quarter':
            $where_conditions[] = "df.uploaded_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM document_files df 
                LEFT JOIN users u ON df.uploaded_by = u.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE $where_clause";

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_files = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_files / $limit);

// Main files query - updated to match document_files table structure
$files_query = "SELECT df.*, 
                       u.username, u.name as user_name, u.surname, u.mi, u.employee_id,
                       u.position, u.profile_image, u.last_login,
                       d.department_name, d.department_code, d.head_of_department,
                       CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as uploader_full_name,
                       df.academic_year, df.semester_period as semester,
                       -- Extract file extension from file_name
                       SUBSTRING_INDEX(df.file_name, '.', -1) as file_extension,
                       df.file_name as original_name,
                       df.uploaded_at,
                       df.file_size,
                       df.file_type,
                       df.description,
                       df.mime_type
                FROM document_files df 
                LEFT JOIN users u ON df.uploaded_by = u.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE $where_clause
                ORDER BY df.uploaded_at DESC 
                LIMIT $limit OFFSET $offset";

$files_stmt = $pdo->prepare($files_query);
$files_stmt->execute($params);
$files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter (based on admin scope)
if ($admin_role === 'super_admin') {
    $dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $pdo->prepare($dept_query);
    $dept_stmt->execute();
} else {
    $dept_query = "SELECT * FROM departments WHERE is_active = 1 AND id = ? ORDER BY department_name";
    $dept_stmt = $pdo->prepare($dept_query);
    $dept_stmt->execute([$admin_info['department_id']]);
}
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get file types for filter
$types_query = "SELECT DISTINCT df.file_type FROM document_files df 
                LEFT JOIN users u ON df.uploaded_by = u.id
                WHERE df.file_type IS NOT NULL";
if ($admin_role !== 'super_admin' && $admin_info['department_id']) {
    $types_query .= " AND u.department_id = ?";
}
$types_query .= " ORDER BY df.file_type";

$types_stmt = $pdo->prepare($types_query);
if ($admin_role !== 'super_admin' && $admin_info['department_id']) {
    $types_stmt->execute([$admin_info['department_id']]);
} else {
    $types_stmt->execute();
}
$file_types = $types_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter (within admin scope)
$users_query = "SELECT u.id, u.username, u.name, u.surname, u.mi, u.employee_id, u.position, 
                       CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name,
                       d.department_code
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.is_approved = 1";
if ($admin_role !== 'super_admin' && $admin_info['department_id']) {
    $users_query .= " AND u.department_id = ?";
}
$users_query .= " ORDER BY u.name, u.surname";

$users_stmt = $pdo->prepare($users_query);
if ($admin_role !== 'super_admin' && $admin_info['department_id']) {
    $users_stmt->execute([$admin_info['department_id']]);
} else {
    $users_stmt->execute();
}
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get academic years for filter
$years_query = "SELECT DISTINCT academic_year FROM document_files WHERE academic_year IS NOT NULL ORDER BY academic_year DESC";
$years_stmt = $pdo->prepare($years_query);
$years_stmt->execute();
$academic_years = $years_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get semesters for filter
$semesters_query = "SELECT DISTINCT semester_period FROM document_files WHERE semester_period IS NOT NULL ORDER BY semester_period";
$semesters_stmt = $pdo->prepare($semesters_query);
$semesters_stmt->execute();
$semesters = $semesters_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $file_id = $_POST['file_id'] ?? 0;
    
    // Verify admin has permission to perform action on this file
    $permission_check = "SELECT df.*, u.department_id as uploader_dept FROM document_files df 
                        JOIN users u ON df.uploaded_by = u.id WHERE df.id = ?";
    $perm_stmt = $pdo->prepare($permission_check);
    $perm_stmt->execute([$file_id]);
    $file_to_modify = $perm_stmt->fetch(PDO::FETCH_ASSOC);
    
    $has_permission = false;
    if ($admin_role === 'super_admin') {
        $has_permission = true;
    } elseif ($file_to_modify && ($file_to_modify['uploader_dept'] == $admin_info['department_id'])) {
        $has_permission = true;
    }
    
    if ($has_permission && $file_to_modify) {
        switch ($action) {
            case 'delete':
                // Since document_files doesn't have is_deleted, we'll actually delete the record
                // But first, let's check if the actual file exists and delete it
                if (file_exists($file_to_modify['file_path'])) {
                    unlink($file_to_modify['file_path']);
                }
                
                $delete_query = "DELETE FROM document_files WHERE id = ?";
                $delete_stmt = $pdo->prepare($delete_query);
                if ($delete_stmt->execute([$file_id])) {
                    // Log activity if activity_logs table exists
                    try {
                        $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, ip_address, user_agent) 
                                     VALUES (?, 'delete', 'document_file', ?, ?, ?, ?)";
                        $log_stmt = $pdo->prepare($log_query);
                        $log_stmt->execute([
                            $_SESSION['user_id'], 
                            $file_id, 
                            "Admin deleted file: " . $file_to_modify['file_name'],
                            $_SERVER['REMOTE_ADDR'] ?? '',
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                    } catch (PDOException $e) {
                        // Activity logs table might not exist, continue without logging
                    }
                    $success_message = "File deleted successfully.";
                } else {
                    $error_message = "Failed to delete file.";
                }
                break;
                
            case 'update_description':
                $new_description = $_POST['description'] ?? '';
                $update_query = "UPDATE document_files SET description = ? WHERE id = ?";
                $update_stmt = $pdo->prepare($update_query);
                if ($update_stmt->execute([$new_description, $file_id])) {
                    $success_message = "File description updated.";
                } else {
                    $error_message = "Failed to update file description.";
                }
                break;
        }
    } else {
        $error_message = "You don't have permission to perform this action.";
    }
}

// Get admin statistics
$stats_query = "SELECT 
    COUNT(*) as total_files,
    SUM(CASE WHEN DATE(df.uploaded_at) = CURDATE() THEN 1 ELSE 0 END) as today_uploads,
    SUM(CASE WHEN df.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_uploads,
    SUM(df.file_size) as total_size,
    COUNT(DISTINCT df.uploaded_by) as unique_uploaders
    FROM document_files df 
    LEFT JOIN users u ON df.uploaded_by = u.id 
    WHERE 1=1";

if ($admin_role !== 'super_admin' && $admin_info['department_id']) {
    $stats_query .= " AND u.department_id = ?";
}

$stats_stmt = $pdo->prepare($stats_query);
if ($admin_role !== 'super_admin' && $admin_info['department_id']) {
    $stats_stmt->execute([$admin_info['department_id']]);
} else {
    $stats_stmt->execute();
}
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function getFileIconClass($extension) {
    $ext = strtolower($extension ?? '');
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg'])) {
        return 'img';
    } elseif (in_array($ext, ['pdf'])) {
        return 'pdf';
    } elseif (in_array($ext, ['doc', 'docx'])) {
        return 'doc';
    } elseif (in_array($ext, ['xls', 'xlsx', 'csv'])) {
        return 'xls';
    } elseif (in_array($ext, ['ppt', 'pptx'])) {
        return 'ppt';
    } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
        return 'zip';
    } else {
        return 'default';
    }
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}

function getProfileImageUrl($profile_image) {
    if (empty($profile_image)) {
        return null;
    }
    
    // Check if it's a relative path that needs to be made absolute
    if (strpos($profile_image, 'uploads/') === 0) {
        return '../../' . $profile_image;
    } elseif (strpos($profile_image, '../') === 0) {
        return $profile_image;
    } else {
        return '../../uploads/profile_images/' . $profile_image;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Files Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/files1.css?v=<?= time() ?>">

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="bg-light">
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>
        
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Document Files Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Admin</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="">Document Files</a></li>
                    </ul>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card" style="color: #ae9cc0ff;">
                    <div class="stat-value"><?php echo number_format($stats['total_files']); ?></div>
                    <div class="stat-label" style="color: #764ba2;">Total Documents</div>
                    <i class="fas fa-file-alt stat-icon" style="color: #764ba2;"></i>
                </div>
                <div class="stat-card success" style="color: #b1d3b9ff;">
                    <div class="stat-value"><?php echo number_format($stats['today_uploads']); ?></div>
                    <div class="stat-label" style="color: #28a745;">Uploaded Today</div>
                    <i class="fas fa-upload stat-icon" style="color: #28a745;"></i>
                </div>
                <div class="stat-card warning" style="color: #ccc4acff;">
                    <div class="stat-value"><?php echo number_format($stats['week_uploads']); ?></div>
                    <div class="stat-label" style="color: #ffc107;">This Week</div>
                    <i class="fas fa-calendar-week stat-icon" style="color: #ffc107;"></i>
                </div>
                <div class="stat-card info" style="color: #accbcfff;">
                    <div class="stat-value"><?php echo formatFileSize($stats['total_size'] ?? 0); ?></div>
                    <div class="stat-label" style="color: #17a2b8;">Total Storage</div>
                    <i class="fas fa-hdd stat-icon" style="color: #17a2b8;"></i>
                </div>
                <div class="stat-card dark" style="color: #9aacc0ff;">
                    <div class="stat-value"><?php echo number_format($stats['unique_uploaders']); ?></div>
                    <div class="stat-label" style="color: #007bff;">Contributors</div>
                    <i class="fas fa-users stat-icon" style="color: #007bff;"></i>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Enhanced Filter Card -->
            <div class="card filter-card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" 
                                    value="<?php echo htmlspecialchars($search); ?>" 
                                    placeholder="Search documents...">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_code'] ?? $dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">Document Type</label>
                            <select class="form-select" name="file_type">
                                <option value="">All Types</option>
                                <?php foreach ($file_types as $type): ?>
                                    <option value="<?php echo $type['file_type']; ?>" 
                                            <?php echo $file_type_filter == $type['file_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['file_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-6">
                            <label class="form-label fw-semibold">Academic Year</label>
                            <select class="form-select" name="academic_year">
                                <option value="">All Years</option>
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?php echo $year['academic_year']; ?>" 
                                            <?php echo $academic_year_filter == $year['academic_year'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['academic_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-6">
                            <label class="form-label fw-semibold">Semester</label>
                            <select class="form-select" name="semester">
                                <option value="">All Semesters</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?php echo $sem['semester_period']; ?>" 
                                            <?php echo $semester_filter == $sem['semester_period'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sem['semester_period']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-6">
                            <label class="form-label fw-semibold">User</label>
                            <select class="form-select" name="user_filter">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">Date Range</label>
                            <select class="form-select" name="date_filter">
                                <option value="">All Time</option>
                                <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="quarter" <?php echo $date_filter == 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Files Table -->
            <div class="card files-table">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Document Details</th>
                                <th style="width: 20%;">Uploader</th>
                                <th style="width: 15%;">Department</th>
                                <th style="width: 10%;">Size</th>
                                <th style="width: 10%;">Academic Info</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td>
                                        <div class="file-item">
                                            <div class="file-icon <?php echo getFileIconClass($file['file_extension'] ?? ''); ?>">
                                                <?php
                                                $icon = 'fa-file';
                                                switch(getFileIconClass($file['file_extension'] ?? '')) {
                                                    case 'pdf': $icon = 'fa-file-pdf'; break;
                                                    case 'doc': $icon = 'fa-file-word'; break;
                                                    case 'xls': $icon = 'fa-file-excel'; break;
                                                    case 'ppt': $icon = 'fa-file-powerpoint'; break;
                                                    case 'img': $icon = 'fa-file-image'; break;
                                                    case 'zip': $icon = 'fa-file-archive'; break;
                                                    default: $icon = 'fa-file';
                                                }
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="file-details flex-grow-1">
                                                <h6 title="<?php echo htmlspecialchars($file['file_name']); ?>">
                                                    <?php echo htmlspecialchars(strlen($file['file_name']) > 50 ? substr($file['file_name'], 0, 47) . '...' : $file['file_name']); ?>
                                                </h6>
                                                <div class="file-meta">
                                                    <span class="me-3">
                                                        <i class="fas fa-tag me-1"></i>
                                                        <?php echo htmlspecialchars($file['file_type'] ?? 'Document'); ?>
                                                    </span>
                                                    <span class="me-3">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo timeAgo($file['uploaded_at']); ?>
                                                    </span>
                                                    <?php if (!empty($file['description'])): ?>
                                                    <span class="me-3" title="<?php echo htmlspecialchars($file['description']); ?>">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Description
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($file['mime_type']): ?>
                                                    <span class="badge bg-light text-dark me-2">
                                                        <?php echo htmlspecialchars($file['mime_type']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($file['description'])): ?>
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars(strlen($file['description']) > 100 ? substr($file['description'], 0, 97) . '...' : $file['description']); ?>
                                                    </small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <?php 
                                            $profile_url = getProfileImageUrl($file['profile_image']);
                                            if ($profile_url && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($profile_url, '/'))): 
                                            ?>
                                                <img src="<?php echo htmlspecialchars($profile_url); ?>" 
                                                    alt="Profile" class="user-avatar" 
                                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="user-avatar" style="display: none;">
                                                    <?php echo strtoupper(substr($file['user_name'] ?? 'U', 0, 1)); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($file['user_name'] ?? 'U', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="user-details">
                                                <h6><?php echo htmlspecialchars($file['uploader_full_name']); ?></h6>
                                                <small>
                                                    @<?php echo htmlspecialchars($file['username']); ?>
                                                    <?php if ($file['employee_id']): ?>
                                                        • <?php echo htmlspecialchars($file['employee_id']); ?>
                                                    <?php endif; ?>
                                                </small>
                                                <?php if ($file['position']): ?>
                                                    <small class="d-block text-muted">
                                                        <?php echo htmlspecialchars($file['position']); ?>
                                                    </small>
                                                <?php endif; ?>
                                                <?php if ($file['last_login']): ?>
                                                    <small class="d-block text-success">
                                                        <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
                                                        Last seen <?php echo timeAgo($file['last_login']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($file['department_name']): ?>
                                            <div class="text-center">
                                                <span class="badge badge-department">
                                                    <?php echo htmlspecialchars($file['department_code'] ?? substr($file['department_name'], 0, 8)); ?>
                                                </span>
                                                <small class="d-block text-muted mt-1">
                                                    <?php echo htmlspecialchars($file['department_name']); ?>
                                                </small>
                                                <?php if ($file['head_of_department']): ?>
                                                    <small class="d-block text-muted">
                                                        Head: <?php echo htmlspecialchars($file['head_of_department']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No Department</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold"><?php echo formatFileSize($file['file_size'] ?? 0); ?></div>
                                        <small class="text-muted">
                                            <?php echo strtoupper($file['file_extension'] ?? 'FILE'); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($file['academic_year']): ?>
                                            <div class="fw-bold text-primary">
                                                AY <?php echo htmlspecialchars($file['academic_year']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($file['semester']): ?>
                                            <span class="badge bg-info text-white">
                                                <?php echo htmlspecialchars($file['semester']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!$file['academic_year'] && !$file['semester']): ?>
                                            <small class="text-muted">Not specified</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" 
                                               class="action-btn download" title="Download File" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                            
                                            <button type="button" class="action-btn view" title="View Details"
                                                    data-bs-toggle="modal" data-bs-target="#fileModal"
                                                    onclick="viewFileDetails(<?php echo $file['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this file? This action cannot be undone.')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                <button type="submit" class="action-btn delete" title="Delete File">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($files)): ?>
                                <tr>
                                    <td colspan="6" class="text-center empty-state">
                                        <i class="fas fa-file-alt"></i>
                                        <h5 class="mt-3">No Documents Found</h5>
                                        <p class="text-muted">
                                            <?php if (!empty($search) || !empty($department_filter) || !empty($user_filter)): ?>
                                                No documents match your current filters. Try adjusting your search criteria.
                                            <?php else: ?>
                                                No documents have been uploaded yet in your administrative scope.
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($search) || !empty($department_filter) || !empty($user_filter)): ?>
                                            <a href="?" class="btn btn-outline-primary">
                                                <i class="fas fa-times me-2"></i>Clear Filters
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_files); ?> 
                        of <?php echo number_format($total_files); ?> documents
                    </div>
                    <ul class="pagination mb-0">
                        <?php
                        $current_url = $_SERVER['REQUEST_URI'];
                        $url_parts = parse_url($current_url);
                        parse_str($url_parts['query'] ?? '', $query_params);
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <?php $query_params['page'] = $page - 1; ?>
                                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <?php $query_params['page'] = 1; ?>
                                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php $query_params['page'] = $i; ?>
                                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <?php $query_params['page'] = $total_pages; ?>
                                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <?php $query_params['page'] = $page + 1; ?>
                                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <!-- File Details Modal -->
    <div class="modal fade" id="fileModal" tabindex="-1" aria-labelledby="fileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fileModalLabel">Document Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="fileModalBody">
                    <!-- Content will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="downloadFileBtn">
                        <i class="fas fa-download me-2"></i>Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // File details modal functionality
        function viewFileDetails(fileId) {
            // Find the file data from PHP
            const fileData = <?php echo json_encode($files); ?>.find(file => file.id == fileId);
            
            if (!fileData) {
                document.getElementById('fileModalBody').innerHTML = '<p class="text-danger">File not found.</p>';
                return;
            }
            
            const profileImageHtml = fileData.profile_image ? 
                `<img src="${getProfileImageUrl(fileData.profile_image)}" alt="Profile" class="rounded-circle me-2" width="40" height="40">` :
                `<div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;">${fileData.user_name.charAt(0).toUpperCase()}</div>`;
            
            const modalContent = `
                <div class="row">
                    <div class="col-md-8">
                        <h6 class="fw-bold mb-3">${fileData.file_name}</h6>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label text-muted">File Type</label>
                                <p class="fw-semibold">${fileData.file_type || 'Document'}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted">File Size</label>
                                <p class="fw-semibold">${formatFileSize(fileData.file_size)}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted">Academic Year</label>
                                <p class="fw-semibold">${fileData.academic_year || 'Not specified'}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted">Semester</label>
                                <p class="fw-semibold">${fileData.semester || 'Not specified'}</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted">Description</label>
                                <p class="fw-semibold">${fileData.description || 'No description provided'}</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted">File Path</label>
                                <p class="text-monospace small text-break">${fileData.file_path}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted">MIME Type</label>
                                <p class="fw-semibold">${fileData.mime_type || 'Not specified'}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted">Uploaded At</label>
                                <p class="fw-semibold">${new Date(fileData.uploaded_at).toLocaleString()}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <h6 class="fw-bold mb-3">Uploaded By</h6>
                            <div class="d-flex align-items-center mb-3">
                                ${profileImageHtml}
                                <div>
                                    <div class="fw-semibold">${fileData.uploader_full_name}</div>
                                    <small class="text-muted">@${fileData.username}</small>
                                </div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Employee ID:</small>
                                <div class="fw-semibold">${fileData.employee_id || 'Not specified'}</div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Position:</small>
                                <div class="fw-semibold">${fileData.position || 'Not specified'}</div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Department:</small>
                                <div class="fw-semibold">${fileData.department_name || 'Not specified'}</div>
                            </div>
                            ${fileData.last_login ? `
                            <div class="mb-2">
                                <small class="text-muted">Last Login:</small>
                                <div class="fw-semibold">${timeAgo(fileData.last_login)}</div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('fileModalBody').innerHTML = modalContent;
            document.getElementById('downloadFileBtn').onclick = function() {
                window.open(fileData.file_path, '_blank');
            };
        }
        
        // Helper function to get profile image URL
        function getProfileImageUrl(profileImage) {
            if (!profileImage) return null;
            
            if (profileImage.startsWith('uploads/')) {
                return '../../' + profileImage;
            } else if (profileImage.startsWith('../')) {
                return profileImage;
            } else {
                return '../../uploads/profile_images/' + profileImage;
            }
        }
        
        // Helper function to format file size in JavaScript
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }
        
        // Helper function for time ago in JavaScript
        function timeAgo(dateString) {
            const now = new Date();
            const past = new Date(dateString);
            const diffInSeconds = Math.floor((now - past) / 1000);
            
            if (diffInSeconds < 60) return 'just now';
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
            if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + 'd ago';
            
            return past.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        // Handle sidebar state
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('#sidebar');
            const contentSection = document.querySelector('#content');
            
            function updateLayout() {
                if (sidebar && sidebar.classList.contains('collapsed')) {
                    contentSection.classList.add('sidebar-collapsed');
                } else {
                    contentSection.classList.remove('sidebar-collapsed');
                }
            }
            
            updateLayout();
            
            // Watch for sidebar changes
            if (sidebar) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            updateLayout();
                        }
                    });
                });
                observer.observe(sidebar, { attributes: true });
            }
        });
    </script>
</body>
</html>