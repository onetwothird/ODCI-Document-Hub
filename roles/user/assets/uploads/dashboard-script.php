<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Require super admin access
$currentUser = requireSuperAdmin();

// Get comprehensive statistics for super admin dashboard
$stats = [
    'total_users' => 0,
    'pending_users' => 0,
    'total_files' => 0,
    'total_folders' => 0,
    'total_departments' => 0,
    'total_announcements' => 0,
    'storage_used' => 0,
    'recent_activities' => [],
    'department_stats' => [],
    'monthly_uploads' => [],
    'user_registrations' => []
];

try {
    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role != 'super_admin'");
    $stats['total_users'] = $stmt->fetch()['count'];
    
    // Get pending users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
    $stats['pending_users'] = $stmt->fetch()['count'];
    
    // Get total files
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as size FROM files WHERE is_deleted = 0");
    $fileData = $stmt->fetch();
    $stats['total_files'] = $fileData['count'];
    $stats['storage_used'] = $fileData['size'];
    
    // Get total folders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM folders WHERE is_deleted = 0");
    $stats['total_folders'] = $stmt->fetch()['count'];
    
    // Get total departments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments WHERE is_active = 1");
    $stats['total_departments'] = $stmt->fetch()['count'];
    
    // Get total announcements
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM announcements WHERE is_deleted = 0");
    $stats['total_announcements'] = $stmt->fetch()['count'];
    
    // Get recent activities
    $stmt = $pdo->query("
        SELECT al.*, u.name, u.mi, u.surname, u.username 
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stats['recent_activities'] = $stmt->fetchAll();
    
    // Get department statistics
    $stmt = $pdo->query("
        SELECT d.department_name, d.department_code,
               COUNT(DISTINCT u.id) as user_count,
               COUNT(DISTINCT f.id) as folder_count,
               COUNT(DISTINCT fi.id) as file_count,
               COALESCE(SUM(fi.file_size), 0) as total_size
        FROM departments d
        LEFT JOIN users u ON d.id = u.department_id AND u.is_approved = 1
        LEFT JOIN folders f ON d.id = f.department_id AND f.is_deleted = 0
        LEFT JOIN files fi ON f.id = fi.folder_id AND fi.is_deleted = 0
        WHERE d.is_active = 1
        GROUP BY d.id
        ORDER BY user_count DESC
    ");
    $stats['department_stats'] = $stmt->fetchAll();
    
    // Get monthly upload statistics (last 6 months)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(uploaded_at, '%Y-%m') as month,
               COUNT(*) as file_count,
               SUM(file_size) as total_size
        FROM files 
        WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND is_deleted = 0
        GROUP BY DATE_FORMAT(uploaded_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stats['monthly_uploads'] = $stmt->fetchAll();
    
    // Get user registration trends (last 30 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 7
    ");
    $stats['user_registrations'] = $stmt->fetchAll();
    
} catch(Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Format file size function
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get action display text
function getActionText($action) {
    $actions = [
        'login' => 'Logged in',
        'logout' => 'Logged out',
        'upload_file' => 'Uploaded file',
        'delete_file' => 'Deleted file',
        'create_folder' => 'Created folder',
        'delete_folder' => 'Deleted folder',
        'create_announcement' => 'Created announcement',
        'approve_user' => 'Approved user',
        'create_user' => 'Created user',
        'update_profile' => 'Updated profile'
    ];
    
    return $actions[$action] ?? ucfirst(str_replace('_', ' ', $action));
}
?>