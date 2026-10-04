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

// Drives the "Clear" control and the empty-state wording: if nothing is
// filtered, an empty table means "nothing uploaded yet", not "nothing matched".
$has_active_filters = $search !== ''
    || $department_filter !== ''
    || $file_type_filter !== ''
    || $user_filter !== ''
    || $date_filter !== ''
    || $academic_year_filter !== ''
    || $semester_filter !== '';

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
    <!-- No Bootstrap. This page used to load Bootstrap 5 and build its filter
         grid, table, badges, pagination and modal out of Bootstrap classes,
         which put a second design system on the page and left the layout at the
         mercy of whichever rule won the cascade. It now uses the same
         components as the rest of the admin panel - the shared CVSU design
         system plus the local classes in files1.css - so nothing here depends
         on an external framework. -->
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/files1.css?v=<?= time() ?>">

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
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

            <!-- Statistics Cards. Class-driven, matching the tracker summary row.
                 The previous version set every colour inline, which pulled in
                 Bootstrap blue (#007bff) and cyan accents inside the green /
                 gold / white theme and made the row impossible to restyle. -->
            <div class="stats-cards">
                <article class="stat-card">
                    <span class="stat-icon"><i class="bx bxs-file"></i></span>
                    <div class="stat-body">
                        <p class="stat-label">Total Documents</p>
                        <div class="stat-value"><?php echo number_format($stats['total_files']); ?></div>
                    </div>
                </article>

                <article class="stat-card accent-gold">
                    <span class="stat-icon"><i class="bx bxs-calendar"></i></span>
                    <div class="stat-body">
                        <p class="stat-label">This Week</p>
                        <div class="stat-value"><?php echo number_format($stats['week_uploads']); ?></div>
                    </div>
                </article>

                <article class="stat-card">
                    <span class="stat-icon"><i class="bx bx-upload"></i></span>
                    <div class="stat-body">
                        <p class="stat-label">Uploaded Today</p>
                        <div class="stat-value"><?php echo number_format($stats['today_uploads']); ?></div>
                    </div>
                </article>

                <article class="stat-card accent-gold">
                    <span class="stat-icon"><i class="bx bx-hdd"></i></span>
                    <div class="stat-body">
                        <p class="stat-label">Total Storage</p>
                        <div class="stat-value"><?php echo formatFileSize($stats['total_size'] ?? 0); ?></div>
                    </div>
                </article>

                <article class="stat-card">
                    <span class="stat-icon"><i class="bx bxs-group"></i></span>
                    <div class="stat-body">
                        <p class="stat-label">Contributors</p>
                        <div class="stat-value"><?php echo number_format($stats['unique_uploaders']); ?></div>
                    </div>
                </article>
            </div>

            <!-- Alerts. Bootstrap's .alert markup needed Bootstrap's JS to
                 dismiss; these are now self-contained so the panel does not
                 carry a framework for one interaction. The tint, rail and
                 radius come from the shared theme's .alert rules. -->
            <?php if (isset($success_message)): ?>
                <div class="alert files-alert is-success" role="status">
                    <i class='bx bxs-check-circle'></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                    <button type="button" class="btn-close" data-dismiss-alert aria-label="Dismiss"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert files-alert is-error" role="alert">
                    <i class='bx bxs-error-circle'></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                    <button type="button" class="btn-close" data-dismiss-alert aria-label="Dismiss"></button>
                </div>
            <?php endif; ?>

            <!-- Filters. Was a Bootstrap .row.g-3 / .col-lg-* grid; it is now a
                 CSS grid on the shared spacing scale (see .filter-grid), so the
                 panel no longer needs a second layout system to line up. -->
            <section class="files-section">
                <div class="files-section-head">
                    <h2><i class='bx bx-filter'></i> Refine Results</h2>
                    <span class="files-section-note"><?php echo number_format($total_files); ?> document<?php echo $total_files === 1 ? '' : 's'; ?> in view</span>
                </div>
                <div class="files-section-body">
                    <form method="GET" class="filter-grid">
                        <div class="filter-field filter-search-field">
                            <label for="filterSearch">Search</label>
                            <div class="filter-search">
                                <i class='bx bx-search'></i>
                                <input type="text" id="filterSearch" class="form-control" name="search"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Name, description or uploader">
                            </div>
                        </div>

                        <div class="filter-field">
                            <label for="filterDepartment">Department</label>
                            <select id="filterDepartment" class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"
                                            <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_code'] ?? $dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="filterType">Document Type</label>
                            <select id="filterType" class="form-select" name="file_type">
                                <option value="">All Types</option>
                                <?php foreach ($file_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['file_type']); ?>"
                                            <?php echo $file_type_filter == $type['file_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['file_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="filterYear">Academic Year</label>
                            <select id="filterYear" class="form-select" name="academic_year">
                                <option value="">All Years</option>
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year['academic_year']); ?>"
                                            <?php echo $academic_year_filter == $year['academic_year'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['academic_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="filterSemester">Semester</label>
                            <select id="filterSemester" class="form-select" name="semester">
                                <option value="">All Semesters</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?php echo htmlspecialchars($sem['semester_period']); ?>"
                                            <?php echo $semester_filter == $sem['semester_period'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sem['semester_period']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="filterUser">Uploaded By</label>
                            <select id="filterUser" class="form-select" name="user_filter">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"
                                            <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="filterDate">Date Range</label>
                            <select id="filterDate" class="form-select" name="date_filter">
                                <option value="">All Time</option>
                                <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="quarter" <?php echo $date_filter == 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            </select>
                        </div>

                        <?php /* Clear only appears when something is actually
                                 filtered. It is a link rather than a submit
                                 button so it cannot be mistaken for a second
                                 way to apply the form. */ ?>
                        <div class="filter-field filter-actions-field">
                            <span class="filter-actions-label" aria-hidden="true">Actions</span>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class='bx bx-filter'></i> Apply
                                </button>
                                <a href="files.php" class="btn btn-reset"
                                   <?php echo $has_active_filters ? '' : 'hidden'; ?>>
                                    <i class='bx bx-reset'></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Files table. The card frame comes from .files-section and the
                 header row, zebra striping and badges come from the shared
                 theme; the nested Bootstrap card is gone so there is a single
                 frame instead of a card inside a card.

                 data-responsive="stack" opts the table into the theme's phone
                 layout, where each row becomes a labelled card instead of a
                 six-column scroller. -->
            <section class="files-section files-section--table">
                <div class="files-section-head">
                    <h2><i class='bx bxs-file'></i> Documents</h2>
                    <span class="files-section-note">
                        <?php echo number_format(count($files)); ?> shown<?php echo $total_files > count($files) ? ' of ' . number_format($total_files) : ''; ?>
                    </span>
                </div>

                <p class="table-scroll-hint">
                    <i class='bx bx-move-horizontal'></i>
                    Swipe the table sideways to see every column
                </p>

                <div class="table-responsive table-scroll files-table-scroll">
                    <table class="table files-table">
                        <colgroup>
                            <col>
                            <col style="width: 19%">
                            <col style="width: 15%">
                            <col style="width: 9%">
                            <col style="width: 12%">
                            <col style="width: 152px">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Document Details</th>
                                <th>Uploader</th>
                                <th>Department</th>
                                <th>Size</th>
                                <th>Academic Info</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td class="files-doc-cell">
                                        <div class="file-item">
                                            <div class="file-icon <?php echo getFileIconClass($file['file_extension'] ?? ''); ?>">
                                                <?php
                                                /* Boxicons only ships per-type document glyphs in their
                                                   solid (bxs-) set - there is no bx-file-pdf,
                                                   bx-file-doc, bx-file-excel or bx-file-ppt. The old
                                                   map pointed at those non-existent names, so every
                                                   PDF / Word / Excel / PowerPoint row rendered an
                                                   empty tile. Everything below is a real glyph, and
                                                   all of them are solid so the row of tiles keeps a
                                                   consistent weight. */
                                                $iconMap = [
                                                    'pdf' => 'bxs-file-pdf',
                                                    'doc' => 'bxs-file-doc',
                                                    'xls' => 'bxs-grid',        // spreadsheet
                                                    'ppt' => 'bxs-movie-play',  // slide deck
                                                    'img' => 'bxs-file-image',
                                                    'zip' => 'bxs-file-archive',
                                                ];
                                                $icon = $iconMap[getFileIconClass($file['file_extension'] ?? '')] ?? 'bxs-file';
                                                ?>
                                                <i class="bx <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="file-details">
                                                <h6 class="file-name" title="<?php echo htmlspecialchars($file['file_name']); ?>">
                                                    <?php echo htmlspecialchars(strlen($file['file_name']) > 50 ? substr($file['file_name'], 0, 47) . '...' : $file['file_name']); ?>
                                                </h6>
                                                <div class="file-meta">
                                                    <span>
                                                        <i class="bx bxs-purchase-tag"></i>
                                                        <?php echo htmlspecialchars($file['file_type'] ?? 'Document'); ?>
                                                    </span>
                                                    <span>
                                                        <i class="bx bx-time"></i>
                                                        <?php echo timeAgo($file['uploaded_at']); ?>
                                                    </span>
                                                    <?php if ($file['mime_type']): ?>
                                                        <span class="is-mime"><?php echo htmlspecialchars($file['mime_type']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($file['description'])): ?>
                                                    <small class="file-desc" title="<?php echo htmlspecialchars($file['description']); ?>">
                                                        <?php echo htmlspecialchars(strlen($file['description']) > 110 ? substr($file['description'], 0, 107) . '...' : $file['description']); ?>
                                                    </small>
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
                                                    alt="" class="user-avatar" loading="lazy"
                                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
                                                <div class="user-avatar" style="display: none;" aria-hidden="true">
                                                    <?php echo strtoupper(substr($file['user_name'] ?? 'U', 0, 1)); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="user-avatar" aria-hidden="true">
                                                    <?php echo strtoupper(substr($file['user_name'] ?? 'U', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="user-details">
                                                <h6 class="user-name"><?php echo htmlspecialchars($file['uploader_full_name']); ?></h6>
                                                <small class="user-sub">
                                                    @<?php echo htmlspecialchars($file['username']); ?>
                                                    <?php if ($file['employee_id']): ?>
                                                        &middot; <?php echo htmlspecialchars($file['employee_id']); ?>
                                                    <?php endif; ?>
                                                </small>
                                                <?php if ($file['position']): ?>
                                                    <small class="user-sub"><?php echo htmlspecialchars($file['position']); ?></small>
                                                <?php endif; ?>
                                                <?php if ($file['last_login']): ?>
                                                    <small class="user-sub is-last-seen">
                                                        <i class="bx bxs-circle"></i>
                                                        Last seen <?php echo timeAgo($file['last_login']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($file['department_name']): ?>
                                            <span class="badge">
                                                <?php echo htmlspecialchars($file['department_code'] ?? substr($file['department_name'], 0, 8)); ?>
                                            </span>
                                            <small class="dept-name"><?php echo htmlspecialchars($file['department_name']); ?></small>
                                            <?php if ($file['head_of_department']): ?>
                                                <small class="dept-head">Head: <?php echo htmlspecialchars($file['head_of_department']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="cell-empty">No department</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="cell-figure"><?php echo formatFileSize($file['file_size'] ?? 0); ?></div>
                                        <small class="cell-sub"><?php echo strtoupper($file['file_extension'] ?? 'FILE'); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($file['academic_year']): ?>
                                            <div class="cell-figure is-green"><?php echo htmlspecialchars($file['academic_year']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($file['semester']): ?>
                                            <small class="cell-sub"><?php echo htmlspecialchars($file['semester']); ?></small>
                                        <?php endif; ?>
                                        <?php if (!$file['academic_year'] && !$file['semester']): ?>
                                            <span class="cell-empty">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- The three controls stay on one line at every
                                         width: the cell is white-space:nowrap and
                                         the group never wraps, so the row height
                                         does not jump between documents. -->
                                    <td class="files-actions-cell">
                                        <div class="row-actions">
                                            <button type="button" class="row-action is-view"
                                                    title="View details" aria-label="View details"
                                                    onclick="viewFileDetails(<?php echo (int)$file['id']; ?>)">
                                                <i class="bx bx-show"></i>
                                            </button>

                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>"
                                               class="row-action is-download"
                                               title="Download" aria-label="Download" download>
                                                <i class="bx bx-download"></i>
                                            </a>

                                            <?php /* display:contents on the form lets the button sit in the
                                                     same flex row as the two links, so all three
                                                     stay aligned without a wrapper div.

                                                     The confirmation is attached in JS from
                                                     data-file-name rather than inlined here.
                                                     Building it inline meant running
                                                     addslashes() before htmlspecialchars(), so a
                                                     quote in a filename was encoded to &#039;,
                                                     decoded back to ' by the HTML parser, and
                                                     then broke out of the JS string literal. */ ?>
                                            <form method="POST" class="row-action-form"
                                                  data-delete-file
                                                  data-file-name="<?php echo htmlspecialchars($file['file_name']); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="file_id" value="<?php echo (int)$file['id']; ?>">
                                                <button type="submit" class="row-action is-delete"
                                                        title="Delete" aria-label="Delete">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($files)): ?>
                                <tr class="files-empty-row">
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bx bxs-file"></i>
                                            <h3>No Documents Found</h3>
                                            <p>
                                                <?php if ($has_active_filters): ?>
                                                    No documents match your current filters. Try widening your search.
                                                <?php else: ?>
                                                    No documents have been uploaded yet within your administrative scope.
                                                <?php endif; ?>
                                            </p>
                                            <?php if ($has_active_filters): ?>
                                                <a href="files.php" class="btn btn-primary">
                                                    <i class="bx bx-reset"></i> Clear filters
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Pagination. The theme owns .pagination / .page-link / .active;
                 only the surrounding bar and the result caption are local. -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-bar">
                    <p class="pagination-info">
                        Showing <b><?php echo $offset + 1; ?>&ndash;<?php echo min($offset + $limit, (int)$total_files); ?></b>
                        of <b><?php echo number_format((int)$total_files); ?></b> documents
                    </p>
                    <nav aria-label="Document pages">
                        <ul class="pagination">
                            <?php
                            $query_params = [];
                            $url_parts = parse_url($_SERVER['REQUEST_URI']);
                            parse_str($url_parts['query'] ?? '', $query_params);
                            ?>

                            <?php if ($page > 1): ?>
                                <?php $query_params['page'] = $page - 1; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>"
                                       aria-label="Previous page">
                                        <i class="bx bx-chevron-left"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true"><i class="bx bx-chevron-left"></i></span>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            ?>

                            <?php if ($start_page > 1): ?>
                                <?php $query_params['page'] = 1; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php $query_params['page'] = $i; ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php if ($i === $page): ?>
                                        <span class="page-link" aria-current="page"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a class="page-link" href="?<?php echo http_build_query($query_params); ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                <?php endif; ?>
                                <?php $query_params['page'] = $total_pages; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>"><?php echo $total_pages; ?></a>
                                </li>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <?php $query_params['page'] = $page + 1; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>"
                                       aria-label="Next page">
                                        <i class="bx bx-chevron-right"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true"><i class="bx bx-chevron-right"></i></span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <!-- File details dialog. Was a Bootstrap .modal opened by data-bs-toggle; it
         now uses the same .modal-backdrop / .modal-shell shell as the rest of
         the admin panel, so no framework is needed for one dialog. -->
    <div class="modal-backdrop" id="fileModal" role="dialog" aria-modal="true"
         aria-labelledby="fileModalLabel" hidden>
        <div class="modal-shell" role="document">
            <div class="modal-titlebar">
                <h3 id="fileModalLabel"><i class='bx bx-file'></i> Document Details</h3>
                <button type="button" class="modal-close" data-modal-close aria-label="Close dialog">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <div class="modal-body" id="fileModalBody"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
                <button type="button" class="btn btn-primary" id="downloadFileBtn">
                    <i class="bx bx-download"></i> Download
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script>
        // The document rows are already rendered server-side, so the dialog is
        // populated from the same payload rather than a second request.
        const FILES_PAYLOAD = <?php echo json_encode($files); ?>;

        const fileModal = document.getElementById('fileModal');
        const fileModalBody = document.getElementById('fileModalBody');
        const downloadFileBtn = document.getElementById('downloadFileBtn');
        let lastFocused = null;
        let currentFile = null;

        function escapeHtml(value) {
            if (value === null || value === undefined) { return ''; }
            const div = document.createElement('div');
            div.textContent = String(value);
            return div.innerHTML;
        }

        function openFileModal() {
            lastFocused = document.activeElement;
            fileModal.classList.add('active');
            fileModal.removeAttribute('hidden');
            document.body.style.overflow = 'hidden';
            const closeBtn = fileModal.querySelector('.modal-close');
            if (closeBtn) { closeBtn.focus(); }
        }

        function closeFileModal() {
            fileModal.classList.remove('active');
            fileModal.setAttribute('hidden', '');
            document.body.style.overflow = '';
            currentFile = null;
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        function viewFileDetails(fileId) {
            currentFile = FILES_PAYLOAD.find(function (file) {
                return String(file.id) === String(fileId);
            });

            if (!currentFile) {
                fileModalBody.innerHTML = '<p class="cell-empty">That document is no longer available. '
                    + 'Refresh the page to see the current list.</p>';
            } else {
                const initial = escapeHtml((currentFile.user_name || 'U').charAt(0).toUpperCase());
                const profileSrc = getProfileImageUrl(currentFile.profile_image);
                const avatar = profileSrc
                    ? '<img src="' + escapeHtml(profileSrc) + '" alt="" class="user-avatar" '
                      + 'data-initial="' + initial + '">'
                    : '<div class="user-avatar" aria-hidden="true">' + initial + '</div>';

                const pairs = [
                    ['File type',    currentFile.file_type || 'Document'],
                    ['File size',    formatFileSize(currentFile.file_size)],
                    ['Uploaded',     new Date(currentFile.uploaded_at).toLocaleString()],
                    ['Academic year', currentFile.academic_year || 'Not specified'],
                    ['Semester',     currentFile.semester || 'Not specified'],
                    ['MIME type',    currentFile.mime_type || 'Not specified']
                ];

                const detailItems = pairs.map(function (pair) {
                    return '<div class="detail-item">'
                        + '<span class="detail-label">' + escapeHtml(pair[0]) + '</span>'
                        + '<p class="detail-value">' + escapeHtml(pair[1]) + '</p>'
                        + '</div>';
                }).join('');

                const wideItems = [
                    ['Description', currentFile.description || 'No description provided.', ''],
                    ['File path',   currentFile.file_path, ' is-path']
                ].map(function (row) {
                    return '<div class="detail-item is-wide">'
                        + '<span class="detail-label">' + escapeHtml(row[0]) + '</span>'
                        + '<p class="detail-value' + row[2] + '">' + escapeHtml(row[1]) + '</p>'
                        + '</div>';
                }).join('');

                const uploaderRows = [
                    ['Employee ID', currentFile.employee_id || 'Not specified'],
                    ['Position',    currentFile.position || 'Not specified'],
                    ['Department',  currentFile.department_name || 'Not specified']
                ];
                if (currentFile.last_login) {
                    uploaderRows.push(['Last seen', timeAgo(currentFile.last_login)]);
                }

                fileModalBody.innerHTML =
                    '<div class="detail-uploader">'
                    + avatar
                    + '<div class="user-details">'
                    + '<h4 class="user-name">' + escapeHtml(currentFile.uploader_full_name) + '</h4>'
                    + '<small class="user-sub">@' + escapeHtml(currentFile.username) + '</small>'
                    + '</div>'
                    + '</div>'
                    + '<div class="detail-grid">' + detailItems + '</div>'
                    + '<div class="detail-grid">' + wideItems + '</div>'
                    + '<div class="detail-aside">' + uploaderRows.map(function (row) {
                        return '<div class="detail-item">'
                            + '<span class="detail-label">' + escapeHtml(row[0]) + '</span>'
                            + '<p class="detail-value">' + escapeHtml(row[1]) + '</p>'
                            + '</div>';
                    }).join('') + '</div>';
            }

            downloadFileBtn.disabled = !currentFile;
            openFileModal();
        }

        // Close on the close buttons, on a click that lands on the backdrop
        // itself, and on Escape. Anything inside the dialog is exempt.
        fileModal.addEventListener('click', function (event) {
            if (event.target === fileModal || event.target.closest('[data-modal-close]')) {
                closeFileModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && fileModal.classList.contains('active')) {
                closeFileModal();
            }
        });

        downloadFileBtn.addEventListener('click', function () {
            if (currentFile && currentFile.file_path) {
                window.open(currentFile.file_path, '_blank', 'noopener');
            }
        });

        // A profile image that 404s falls back to the initials tile rather than
        // leaving a broken-image glyph in the dialog.
        fileModalBody.addEventListener('error', function (event) {
            const img = event.target;
            if (img.tagName !== 'IMG' || !img.dataset.initial) { return; }
            const fallback = document.createElement('div');
            fallback.className = 'user-avatar';
            fallback.setAttribute('aria-hidden', 'true');
            fallback.textContent = img.dataset.initial;
            img.replaceWith(fallback);
        }, true);

        // Delete is guarded by a confirmation that names the file. Delegated
        // rather than inline so the filename travels as an HTML-escaped data
        // attribute and never has to be escaped for a JS string literal.
        Array.prototype.forEach.call(
            document.querySelectorAll('form[data-delete-file]'),
            function (form) {
                form.addEventListener('submit', function (event) {
                    var name = form.dataset.fileName || 'this document';
                    if (!window.confirm('Delete "' + name + '"? This cannot be undone.')) {
                        event.preventDefault();
                    }
                });
            }
        );

        // Alerts used to be dismissed by Bootstrap's data-bs-dismiss.
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-dismiss-alert]'),
            function (button) {
                button.addEventListener('click', function () {
                    const alert = button.closest('.alert');
                    if (alert) { alert.remove(); }
                });
            }
        );

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
            bytes = Number(bytes) || 0;
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + (bytes === 1 ? ' byte' : ' bytes');
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
    </script>
</body>
</html>