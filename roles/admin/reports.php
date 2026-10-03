<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin or super_admin
requireAdmin();

// Get current user's department for filtering (if not super_admin)
$current_user_dept = null;
if ($_SESSION['role'] !== 'super_admin') {
    $user_stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $current_user_dept = $user_data['department_id'];
    
    // If admin has no department, they can't access reports
    if (!$current_user_dept) {
        header('Location: dashboard.php?error=no_department');
        exit;
    }
}

// Handle report generation
$report_type = $_GET['type'] ?? 'overview';
$date_range = $_GET['range'] ?? '30';

// Date range calculation
$end_date = date('Y-m-d');
switch ($date_range) {
    case '7':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $range_label = 'Last 7 Days';
        break;
    case '30':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $range_label = 'Last 30 Days';
        break;
    case '90':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $range_label = 'Last 90 Days';
        break;
    case '365':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        $range_label = 'Last Year';
        break;
    case 'custom':
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $range_label = 'Custom Range';
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $range_label = 'Last 30 Days';
}

// Get overview statistics (filtered by department for admins)
function getOverviewStats($pdo, $start_date, $end_date, $department_filter = null) {
    $stats = [];
    
    // Files statistics
    if ($department_filter) {
        $file_query = "SELECT 
                          COUNT(*) as total_files,
                          COUNT(CASE WHEN DATE(f.uploaded_at) >= ? AND DATE(f.uploaded_at) <= ? THEN 1 END) as new_files,
                          SUM(f.file_size) as total_size,
                          COUNT(CASE WHEN f.is_deleted = 1 THEN 1 END) as deleted_files
                       FROM files f
                       INNER JOIN folders fo ON f.folder_id = fo.id
                       WHERE fo.department_id = ?";
        $stmt = $pdo->prepare($file_query);
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $file_query = "SELECT 
                          COUNT(*) as total_files,
                          COUNT(CASE WHEN DATE(uploaded_at) >= ? AND DATE(uploaded_at) <= ? THEN 1 END) as new_files,
                          SUM(file_size) as total_size,
                          COUNT(CASE WHEN is_deleted = 1 THEN 1 END) as deleted_files
                       FROM files";
        $stmt = $pdo->prepare($file_query);
        $stmt->execute([$start_date, $end_date]);
    }
    $stats['files'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Folders statistics
    if ($department_filter) {
        $folder_query = "SELECT 
                            COUNT(*) as total_folders,
                            COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_folders,
                            COUNT(CASE WHEN is_deleted = 1 THEN 1 END) as deleted_folders
                         FROM folders
                         WHERE department_id = ?";
        $stmt = $pdo->prepare($folder_query);
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $folder_query = "SELECT 
                            COUNT(*) as total_folders,
                            COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_folders,
                            COUNT(CASE WHEN is_deleted = 1 THEN 1 END) as deleted_folders
                         FROM folders";
        $stmt = $pdo->prepare($folder_query);
        $stmt->execute([$start_date, $end_date]);
    }
    $stats['folders'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Users statistics
    if ($department_filter) {
        $user_query = "SELECT 
                          COUNT(*) as total_users,
                          COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_users,
                          COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved_users,
                          COUNT(CASE WHEN last_login >= ? AND last_login <= ? THEN 1 END) as active_users
                       FROM users
                       WHERE department_id = ?";
        $stmt = $pdo->prepare($user_query);
        $stmt->execute([$start_date, $end_date, $start_date . ' 00:00:00', $end_date . ' 23:59:59', $department_filter]);
    } else {
        $user_query = "SELECT 
                          COUNT(*) as total_users,
                          COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_users,
                          COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved_users,
                          COUNT(CASE WHEN last_login >= ? AND last_login <= ? THEN 1 END) as active_users
                       FROM users";
        $stmt = $pdo->prepare($user_query);
        $stmt->execute([$start_date, $end_date, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    }
    $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Document requests statistics
    if ($department_filter) {
        $doc_query = "SELECT 
                         COUNT(*) as total_requests,
                         COUNT(CASE WHEN DATE(dr.created_at) >= ? AND DATE(dr.created_at) <= ? THEN 1 END) as new_requests,
                         COUNT(CASE WHEN dr.status = 'completed' THEN 1 END) as completed_requests,
                         COUNT(CASE WHEN dr.status = 'pending' THEN 1 END) as pending_requests
                      FROM document_requests dr
                      INNER JOIN users u ON dr.user_id = u.id
                      WHERE u.department_id = ?";
        $stmt = $pdo->prepare($doc_query);
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $doc_query = "SELECT 
                         COUNT(*) as total_requests,
                         COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_requests,
                         COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                         COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests
                      FROM document_requests";
        $stmt = $pdo->prepare($doc_query);
        $stmt->execute([$start_date, $end_date]);
    }
    $stats['documents'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Shares statistics
    if ($department_filter) {
        $share_query = "SELECT 
                           COUNT(*) as total_shares,
                           COUNT(CASE WHEN DATE(fs.created_at) >= ? AND DATE(fs.created_at) <= ? THEN 1 END) as new_shares,
                           COUNT(CASE WHEN fs.is_active = 1 THEN 1 END) as active_shares,
                           SUM(fs.download_count) as total_downloads
                        FROM file_shares fs
                        INNER JOIN files f ON fs.file_id = f.id
                        INNER JOIN folders fo ON f.folder_id = fo.id
                        WHERE fo.department_id = ?";
        $stmt = $pdo->prepare($share_query);
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $share_query = "SELECT 
                           COUNT(*) as total_shares,
                           COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_shares,
                           COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_shares,
                           SUM(download_count) as total_downloads
                        FROM file_shares";
        $stmt = $pdo->prepare($share_query);
        $stmt->execute([$start_date, $end_date]);
    }
    $stats['shares'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $stats;
}

// Get department-wise statistics (filtered for admins)
function getDepartmentStats($pdo, $start_date, $end_date, $department_filter = null) {
    if ($department_filter) {
        // For regular admins, only show their department
        $query = "SELECT 
                     d.department_name, d.department_code,
                     COUNT(DISTINCT u.id) as user_count,
                     COUNT(DISTINCT f.id) as file_count,
                     COUNT(DISTINCT fo.id) as folder_count,
                     COALESCE(SUM(f.file_size), 0) as total_size
                  FROM departments d
                  LEFT JOIN users u ON d.id = u.department_id AND u.created_at BETWEEN ? AND ?
                  LEFT JOIN folders fo ON d.id = fo.department_id
                  LEFT JOIN files f ON fo.id = f.folder_id AND f.uploaded_at BETWEEN ? AND ?
                  WHERE d.is_active = 1 AND d.id = ?
                  GROUP BY d.id, d.department_name, d.department_code
                  ORDER BY file_count DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $start_date . ' 00:00:00', $end_date . ' 23:59:59', 
            $start_date . ' 00:00:00', $end_date . ' 23:59:59',
            $department_filter
        ]);
    } else {
        // For super admins, show all departments
        $query = "SELECT 
                     d.department_name, d.department_code,
                     COUNT(DISTINCT u.id) as user_count,
                     COUNT(DISTINCT f.id) as file_count,
                     COUNT(DISTINCT fo.id) as folder_count,
                     COALESCE(SUM(f.file_size), 0) as total_size
                  FROM departments d
                  LEFT JOIN users u ON d.id = u.department_id AND u.created_at BETWEEN ? AND ?
                  LEFT JOIN folders fo ON d.id = fo.department_id
                  LEFT JOIN files f ON fo.id = f.folder_id AND f.uploaded_at BETWEEN ? AND ?
                  WHERE d.is_active = 1
                  GROUP BY d.id, d.department_name, d.department_code
                  ORDER BY file_count DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59', $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get activity logs (filtered for admins)
function getActivityLogs($pdo, $start_date, $end_date, $limit = 50, $department_filter = null) {
    if ($department_filter) {
        $query = "SELECT 
                     al.*, u.username, u.name, u.surname,
                     CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name
                  FROM activity_logs al
                  LEFT JOIN users u ON al.user_id = u.id
                  WHERE DATE(al.created_at) BETWEEN ? AND ? 
                  AND (u.department_id = ? OR u.department_id IS NULL)
                  ORDER BY al.created_at DESC";
    } else {
        $query = "SELECT 
                     al.*, u.username, u.name, u.surname,
                     CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name
                  FROM activity_logs al
                  LEFT JOIN users u ON al.user_id = u.id
                  WHERE DATE(al.created_at) BETWEEN ? AND ?
                  ORDER BY al.created_at DESC";
    }
    
    // Add LIMIT clause only if limit is provided
    if ($limit > 0) {
        $query .= " LIMIT " . (int)$limit;
    }
    
    $stmt = $pdo->prepare($query);
    if ($department_filter) {
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $stmt->execute([$start_date, $end_date]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get top users by activity (filtered for admins)
function getTopUsers($pdo, $start_date, $end_date, $department_filter = null) {
    if ($department_filter) {
        $query = "SELECT 
                     u.username, u.name, u.surname, d.department_name,
                     CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name,
                     COUNT(DISTINCT f.id) as files_uploaded,
                     COUNT(DISTINCT al.id) as activities,
                     COALESCE(SUM(f.file_size), 0) as total_uploaded_size
                  FROM users u
                  LEFT JOIN files f ON u.id = f.uploaded_by AND f.uploaded_at BETWEEN ? AND ?
                  LEFT JOIN activity_logs al ON u.id = al.user_id AND al.created_at BETWEEN ? AND ?
                  LEFT JOIN departments d ON u.department_id = d.id
                  WHERE u.role IN ('user', 'admin') AND u.department_id = ?
                  GROUP BY u.id
                  HAVING files_uploaded > 0 OR activities > 0
                  ORDER BY activities DESC, files_uploaded DESC
                  LIMIT 20";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $start_date . ' 00:00:00', $end_date . ' 23:59:59',
            $start_date . ' 00:00:00', $end_date . ' 23:59:59',
            $department_filter
        ]);
    } else {
        $query = "SELECT 
                     u.username, u.name, u.surname, d.department_name,
                     CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name,
                     COUNT(DISTINCT f.id) as files_uploaded,
                     COUNT(DISTINCT al.id) as activities,
                     COALESCE(SUM(f.file_size), 0) as total_uploaded_size
                  FROM users u
                  LEFT JOIN files f ON u.id = f.uploaded_by AND f.uploaded_at BETWEEN ? AND ?
                  LEFT JOIN activity_logs al ON u.id = al.user_id AND al.created_at BETWEEN ? AND ?
                  LEFT JOIN departments d ON u.department_id = d.id
                  WHERE u.role IN ('user', 'admin')
                  GROUP BY u.id
                  HAVING files_uploaded > 0 OR activities > 0
                  ORDER BY activities DESC, files_uploaded DESC
                  LIMIT 20";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $start_date . ' 00:00:00', $end_date . ' 23:59:59',
            $start_date . ' 00:00:00', $end_date . ' 23:59:59'
        ]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Generate data based on report type
$data = [];
switch ($report_type) {
    case 'overview':
        $data['stats'] = getOverviewStats($pdo, $start_date, $end_date, $current_user_dept);
        $data['departments'] = getDepartmentStats($pdo, $start_date, $end_date, $current_user_dept);
        $data['activities'] = getActivityLogs($pdo, $start_date, $end_date, 20, $current_user_dept);
        $data['top_users'] = getTopUsers($pdo, $start_date, $end_date, $current_user_dept);
        break;
    case 'departments':
        $data['departments'] = getDepartmentStats($pdo, $start_date, $end_date, $current_user_dept);
        break;
    case 'users':
        $data['top_users'] = getTopUsers($pdo, $start_date, $end_date, $current_user_dept);
        break;
    case 'activities':
        $data['activities'] = getActivityLogs($pdo, $start_date, $end_date, 100, $current_user_dept);
        break;
}

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

// Get current department name for display
$department_name = 'All Departments';
if ($current_user_dept) {
    $dept_stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
    $dept_stmt->execute([$current_user_dept]);
    $dept_data = $dept_stmt->fetch(PDO::FETCH_ASSOC);
    $department_name = $dept_data['department_name'] ?? 'Unknown Department';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        :root {
            --poppins: 'Poppins', sans-serif;
            --lato: 'Lato', sans-serif;
            
            /* Primary Green (for navbar/sidebar compatibility) */
            --primary-green: #28a745;
            --light-green: #d4edda;
            --dark-green: #1e7e34;
            
            /* Complementary Color Palette */
            --teal: #20c997;
            --green: #28a745;
            --cyan: #17a2b8;
            --blue: #007bff;
            --indigo: #6f42c1;
            --purple: #6f42c1;
            --pink: #e83e8c;
            --orange: #fd7e14;
            --yellow: #ffc107;
            --amber: #ffb347;
            
            /* Neutral Colors */
            --light: #f8f9fa;
            --grey-light: #e9ecef;
            --grey: #6c757d;
            --dark-grey: #495057;
            --dark: #2d3748;
            --white: #ffffff;
            --red: #dc3545;
            
            /* Gradient combinations */
            --gradient-blue: linear-gradient(135deg, #10b981, #059669);
            --gradient-orange: linear-gradient(135deg, #f093fb, #f5576c);
            --gradient-teal: linear-gradient(135deg, #4facfe, #00f2fe);
            --gradient-purple: linear-gradient(135deg, #a8edea, #fed6e3);
        }

        body {
            font-family: var(--poppins);
            background: #f5f7fa
            min-height: 100vh;
            color: var(--dark);
        }
             #content main {
                width: 100%;
                padding: 36px 24px;
                font-family: var(--poppins);
                max-height: calc(100vh - 56px);
                overflow-y: auto;
            }
            #content main .head-title {
                display: flex;
                align-items: center;
                justify-content: space-between;
                grid-gap: 16px;
                flex-wrap: wrap;
            }
            #content main .head-title .left h1 {
                font-size: 36px;
                font-weight: 600;
                margin-bottom: 10px;
                color: var(--dark);
            }
            #content main .head-title .left .breadcrumb {
                display: flex;
                align-items: center;
                grid-gap: 16px;
            }
            #content main .head-title .left .breadcrumb li {
                color: var(--dark);
            }
            #content main .head-title .left .breadcrumb li a {
                color: var(--dark-grey);
                pointer-events: none;
                text-decoration: none; /* removes underline */
            }

            #content main .head-title .left .breadcrumb li a.active {
                color: var(--green);
                pointer-events: unset;
                text-decoration: none; /* also removes underline on active */
            }
            #content main .head-title .btn-download {
                height: 36px;
                padding: 0 16px;
                border-radius: 36px;
                background: var(--green);
                color: var(--light);
                display: flex;
                justify-content: center;
                align-items: center;
                grid-gap: 10px;
                font-weight: 500;
            }

        /* Header Styling */
        .page-header {
            background: var(--gradient-blue);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin: 20px 0;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .page-header .header-content {
            position: relative;
            z-index: 2;
        }

        .department-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
        }

        /* Cards and Components */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            background: white;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 35px rgba(0,0,0,0.12);
        }

        /* Statistics Cards with Different Colors */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-green);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-green), var(--teal));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-card h4 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            color: var(--dark-grey);
            font-weight: 500;
        }

        .stat-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
            display: inline-block;
        }

        /* Individual stat card colors */
        .stat-card.files { 
            border-left-color: var(--primary-green);
        }
        .stat-card.files::before { 
            background: linear-gradient(90deg, var(--primary-green), var(--teal));
        }
        .stat-card.files i { color: var(--primary-green); }
        .stat-card.files .stat-badge { 
            background: rgba(40, 167, 69, 0.1); 
            color: var(--dark-green); 
        }
        
        .stat-card.folders { 
            border-left-color: var(--cyan);
        }
        .stat-card.folders::before { 
            background: linear-gradient(90deg, var(--cyan), var(--blue));
        }
        .stat-card.folders i { color: var(--cyan); }
        .stat-card.folders .stat-badge { 
            background: rgba(23, 162, 184, 0.1); 
            color: #0c5460; 
        }
        
        .stat-card.users { 
            border-left-color: var(--indigo);
        }
        .stat-card.users::before { 
            background: linear-gradient(90deg, var(--indigo), var(--purple));
        }
        .stat-card.users i { color: var(--indigo); }
        .stat-card.users .stat-badge { 
            background: rgba(111, 66, 193, 0.1); 
            color: #4a2c7a; 
        }
        
        .stat-card.documents { 
            border-left-color: var(--orange);
        }
        .stat-card.documents::before { 
            background: linear-gradient(90deg, var(--orange), var(--amber));
        }
        .stat-card.documents i { color: var(--orange); }
        .stat-card.documents .stat-badge { 
            background: rgba(253, 126, 20, 0.1); 
            color: #cc5500; 
        }
        
        .stat-card.shares { 
            border-left-color: var(--pink);
        }
        .stat-card.shares::before { 
            background: linear-gradient(90deg, var(--pink), #ff6b9d);
        }
        .stat-card.shares i { color: var(--pink); }
        .stat-card.shares .stat-badge { 
            background: rgba(232, 62, 140, 0.1); 
            color: #a91e5c; 
        }
        
        .stat-card.storage { 
            border-left-color: var(--grey);
        }
        .stat-card.storage::before { 
            background: linear-gradient(90deg, var(--grey), var(--dark-grey));
        }
        .stat-card.storage i { color: var(--grey); }
        .stat-card.storage .stat-badge { 
            background: rgba(108, 117, 125, 0.1); 
            color: #495057; 
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
            background: white;
        }

        /* Buttons */
        .btn {
            border-radius: 12px;
            font-weight: 600;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-green);
            color: var(--primary-green);
        }

        .btn-outline-primary:hover {
            background: var(--primary-green);
            transform: translateY(-2px);
        }

        /* Navigation Pills */
        .nav-pills .nav-link {
            border-radius: 12px;
            font-weight: 600;
            margin-right: 8px;
            padding: 12px 20px;
            color: var(--dark-grey);
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(10px);
        }

        .nav-pills .nav-link:hover {
            background: rgba(40, 167, 69, 0.1);
            color: var(--primary-green);
            transform: translateY(-2px);
        }

        .nav-pills .nav-link.active {
            background: var(--gradient-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        /* Tables */
        .table {
            border-radius: 12px;
            overflow: hidden;
        }

        .table thead th {
            background: var(--gradient-blue);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.05), rgba(32, 201, 151, 0.05));
            transform: scale(1.01);
        }

        /* Badges with varied colors */
        .badge {
            border-radius: 50px;
            font-weight: 600;
            padding: 6px 12px;
        }

        .badge.bg-success {
            background: var(--primary-green) !important;
        }

        .badge.bg-info {
            background: var(--cyan) !important;
        }

        .badge.bg-warning {
            background: var(--orange) !important;
            color: white !important;
        }

        .badge.bg-secondary {
            background: var(--grey) !important;
        }

        .badge.bg-primary {
            background: var(--blue) !important;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 350px;
            padding: 1rem;
        }

        /* Alert Styling */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 500;
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(0, 123, 255, 0.1));
            color: #0c5460;
            border-left: 4px solid var(--cyan);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(253, 126, 20, 0.1));
            color: #856404;
            border-left: 4px solid var(--orange);
        }

        /* Loading Animation */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading-shimmer 1.5s infinite;
        }

        @keyframes loading-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--dark-grey);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--grey);
            margin-bottom: 1.5rem;
        }

        .empty-state h5 {
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        /* Activity Item Styling */
        .activity-item {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(248,250,252,0.9));
            border-radius: 12px;
            padding: 1rem;
            border-left: 4px solid var(--teal);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 20px rgba(32, 201, 151, 0.15);
        }

        .activity-item:nth-child(even) {
            border-left-color: var(--blue);
        }

        .activity-item:nth-child(3n) {
            border-left-color: var(--purple);
        }

        .activity-item:nth-child(4n) {
            border-left-color: var(--orange);
        }

        /* Enhanced form styling */
        .card-body form {
            background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(248,250,252,0.8));
            border-radius: 12px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
        }

        /* Ranking badges for top users */
        .rank-badge-gold {
            background: linear-gradient(135deg, #ffd700, #ffed4a);
            color: #8b7000;
            border: 2px solid #ffd700;
        }

        .rank-badge-silver {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
            color: #6c6c6c;
            border: 2px solid #c0c0c0;
        }

        .rank-badge-bronze {
            background: linear-gradient(135deg, #cd7f32, #daa520);
            color: #5d3a0a;
            border: 2px solid #cd7f32;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
                margin: 10px 0;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .chart-container {
                height: 250px;
            }
        }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'components/sidebar.html'; ?>
    
    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <?php include 'components/navbar.html'; ?>
         
       <main>
            <div class="head-title">
                <div class="left">
                    <h1>Reports Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Admin</li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="">All Reports</a></li>
                    </ul>
                </div>
            </div>


        <div class="container-fluid">
            <!-- Header -->
             <div class="page-header">
                <div class="header-content d-flex justify-content-between align-items-center">
                    <div>
                        <?php if ($_SESSION['role'] !== 'super_admin'): ?>
                            <div class="mt-3">
                                <div class="department-badge">
                                    <i class="fas fa-building"></i>
                                    <?php echo htmlspecialchars($department_name); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-3">
                        <button class="btn btn-outline-light" onclick="window.print()" style="backdrop-filter: blur(10px); border: 2px solid rgba(255,255,255,0.3);">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                        <button class="btn btn-light" onclick="exportReport()" style="color: var(--indigo); font-weight: 600;">
                            <i class="fas fa-download me-2"></i>Export Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-dark">
                                <i class="fas fa-calendar-alt me-2" style="color: var(--cyan);"></i>Date Range
                            </label>
                            <select class="form-select" name="range" onchange="toggleCustomDates(this.value)">
                                <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="365" <?php echo $date_range == '365' ? 'selected' : ''; ?>>Last Year</option>
                                <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="start-date-col" style="display: <?php echo $date_range == 'custom' ? 'block' : 'none'; ?>;">
                            <label class="form-label fw-semibold text-dark">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3" id="end-date-col" style="display: <?php echo $date_range == 'custom' ? 'block' : 'none'; ?>;">
                            <label class="form-label fw-semibold text-dark">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sync-alt me-2"></i>Update Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Period Info -->
            <div class="alert alert-info">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle me-3" style="font-size: 1.2rem; color: var(--cyan);"></i>
                    <div>
                        <strong>Report Period:</strong> <?php echo $range_label; ?> 
                        (<?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?>)
                        <?php if ($_SESSION['role'] !== 'super_admin'): ?>
                            <br><i class="fas fa-filter me-2" style="color: var(--primary-green);"></i>
                            <strong>Department Filter:</strong> <?php echo htmlspecialchars($department_name); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Report Type Navigation -->
            <div class="card mb-4">
                <div class="card-body">
                    <ul class="nav nav-pills" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type === 'overview' ? 'active' : ''; ?>" 
                               href="?type=overview&range=<?php echo $date_range; ?>">
                                <i class="fas fa-tachometer-alt me-2"></i>Overview Dashboard
                            </a>
                        </li>
                        <?php if ($_SESSION['role'] === 'super_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type === 'departments' ? 'active' : ''; ?>" 
                               href="?type=departments&range=<?php echo $date_range; ?>">
                                <i class="fas fa-building me-2"></i>Department Analysis
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type === 'users' ? 'active' : ''; ?>" 
                               href="?type=users&range=<?php echo $date_range; ?>">
                                <i class="fas fa-users me-2"></i>User Analytics
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Overview Report -->
            <?php if ($report_type === 'overview'): ?>
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card files">
                            <i class="fas fa-file-alt"></i>
                            <h4><?php echo number_format($data['stats']['files']['total_files']); ?></h4>
                            <div class="stat-label">Total Files</div>
                            <div class="stat-badge">+<?php echo number_format($data['stats']['files']['new_files']); ?> new</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card folders">
                            <i class="fas fa-folder-open"></i>
                            <h4><?php echo number_format($data['stats']['folders']['total_folders']); ?></h4>
                            <div class="stat-label">Total Folders</div>
                            <div class="stat-badge">+<?php echo number_format($data['stats']['folders']['new_folders']); ?> new</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card users">
                            <i class="fas fa-users"></i>
                            <h4><?php echo number_format($data['stats']['users']['total_users']); ?></h4>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-badge">+<?php echo number_format($data['stats']['users']['new_users']); ?> new</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card documents">
                            <i class="fas fa-file-contract"></i>
                            <h4><?php echo number_format($data['stats']['documents']['total_requests']); ?></h4>
                            <div class="stat-label">Doc Requests</div>
                            <div class="stat-badge">+<?php echo number_format($data['stats']['documents']['new_requests']); ?> new</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card shares">
                            <i class="fas fa-share-nodes"></i>
                            <h4><?php echo number_format($data['stats']['shares']['total_shares']); ?></h4>
                            <div class="stat-label">File Shares</div>
                            <div class="stat-badge">+<?php echo number_format($data['stats']['shares']['new_shares']); ?> new</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stat-card storage">
                            <i class="fas fa-database"></i>
                            <h4><?php echo formatFileSize($data['stats']['files']['total_size']); ?></h4>
                            <div class="stat-label">Storage Used</div>
                            <div class="stat-badge">Total Space</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(248,250,252,0.95)); backdrop-filter: blur(10px);">
                            <div class="card-header bg-transparent border-0 pb-0">
                                <h5 class="mb-0 fw-bold text-dark">
                                    <i class="fas fa-chart-pie me-2" style="color: var(--pink);"></i>File Distribution Overview
                                </h5>
                                <small class="text-muted">Distribution of files by status</small>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="fileDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100" style="background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(248,250,252,0.95)); backdrop-filter: blur(10px);">
                            <div class="card-header bg-transparent border-0 pb-0">
                                <h5 class="mb-0 fw-bold text-dark">
                                    <i class="fas fa-chart-bar me-2" style="color: var(--indigo);"></i>
                                    <?php echo $_SESSION['role'] === 'super_admin' ? 'Department Activity' : 'Top User Activity'; ?>
                                </h5>
                                <small class="text-muted">
                                    <?php echo $_SESSION['role'] === 'super_admin' ? 'File uploads by department' : 'Most active users in your department'; ?>
                                </small>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="departmentActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <?php if (!empty($data['activities'])): ?>
                <div class="card mb-4" style="background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(248,250,252,0.95)); backdrop-filter: blur(10px);">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-clock me-2" style="color: var(--teal);"></i>Recent Activity
                        </h5>
                        <small class="text-muted">Latest system activities in your scope</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach (array_slice($data['activities'], 0, 8) as $index => $activity): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="activity-item">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3">
                                                <i class="fas fa-circle" style="font-size: 0.6rem; color: var(--teal);"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold text-dark mb-1">
                                                    <?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?>
                                                </div>
                                                <div class="text-muted small mb-1">
                                                    <?php echo htmlspecialchars($activity['action']); ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.8rem;">
                                                    <i class="fas fa-clock me-1" style="color: var(--orange);"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>

            <!-- Department Report (For regular admins - shows only their department) -->
            <?php if ($report_type === 'departments' && $_SESSION['role'] !== 'super_admin'): ?>
                <div class="alert alert-warning">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle me-3" style="font-size: 1.2rem; color: var(--orange);"></i>
                        <div>
                            <strong>Department Access Notice:</strong> As a department administrator, you can only view statistics for your own department. 
                            Use the <strong>Overview</strong> tab to see comprehensive statistics for <?php echo htmlspecialchars($department_name); ?>.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Users Report -->
            <?php if ($report_type === 'users'): ?>
                <div class="card" style="background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(248,250,252,0.95)); backdrop-filter: blur(10px);">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-trophy me-2" style="color: var(--yellow);"></i>Top Active Users
                            <?php if ($_SESSION['role'] !== 'super_admin'): ?>
                                <span class="badge" style="background: var(--primary-green); color: white;"><?php echo htmlspecialchars($department_name); ?></span>
                            <?php endif; ?>
                        </h5>
                        <small class="text-muted">Most active users based on file uploads and system activities</small>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($data['top_users'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="padding: 1.2rem;">Rank & User</th>
                                            <th style="padding: 1.2rem;">Department</th>
                                            <th style="padding: 1.2rem;">Files Uploaded</th>
                                            <th style="padding: 1.2rem;">Total Size</th>
                                            <th style="padding: 1.2rem;">Activities</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['top_users'] as $index => $user): ?>
                                            <tr>
                                                <td style="padding: 1rem;">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <?php if ($index === 0): ?>
                                                                <span class="badge rank-badge-gold fw-bold" style="font-size: 0.9rem;">
                                                                    <i class="fas fa-crown me-1"></i>#1
                                                                </span>
                                                            <?php elseif ($index === 1): ?>
                                                                <span class="badge rank-badge-silver fw-bold" style="font-size: 0.9rem;">
                                                                    <i class="fas fa-medal me-1"></i>#2
                                                                </span>
                                                            <?php elseif ($index === 2): ?>
                                                                <span class="badge rank-badge-bronze fw-bold" style="font-size: 0.9rem;">
                                                                    <i class="fas fa-award me-1"></i>#3
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary fw-bold">
                                                                    #<?php echo $index + 1; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold text-dark">
                                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                            </div>
                                                            <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <?php if ($user['department_name']): ?>
                                                        <span class="badge" style="background: var(--cyan); color: white;"><?php echo htmlspecialchars($user['department_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <span class="badge fw-bold" style="font-size: 0.9rem; background: var(--primary-green); color: white;">
                                                        <?php echo number_format($user['files_uploaded']); ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <span class="text-dark fw-semibold">
                                                        <?php echo formatFileSize($user['total_uploaded_size']); ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <span class="badge fw-bold" style="font-size: 0.9rem; background: var(--indigo); color: white;">
                                                        <?php echo number_format($user['activities']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-users-slash"></i>
                                <h5>No Active Users Found</h5>
                                <p class="mb-0">No user activity has been recorded for the selected time period in your department scope.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>
    
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleCustomDates(value) {
            const startDateCol = document.getElementById('start-date-col');
            const endDateCol = document.getElementById('end-date-col');
            
            if (value === 'custom') {
                startDateCol.style.display = 'block';
                endDateCol.style.display = 'block';
            } else {
                startDateCol.style.display = 'none';
                endDateCol.style.display = 'none';
            }
        }

        function exportReport() {
            // Show a styled notification
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, var(--indigo), var(--purple));
                color: white;
                padding: 15px 25px;
                border-radius: 12px;
                box-shadow: 0 8px 25px rgba(111, 66, 193, 0.3);
                z-index: 1000;
                font-family: var(--poppins);
                font-weight: 600;
                transform: translateX(400px);
                transition: all 0.3s ease;
            `;
            notification.innerHTML = '<i class="fas fa-download me-2"></i>Export feature will be implemented soon!';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => document.body.removeChild(notification), 300);
            }, 1800);
        }

        // Initialize charts for overview report
        <?php if ($report_type === 'overview'): ?>
        // File Distribution Chart with colorful palette
        const fileCtx = document.getElementById('fileDistributionChart').getContext('2d');
        new Chart(fileCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active Files', 'New Files', 'Deleted Files'],
                datasets: [{
                    data: [
                        <?php echo $data['stats']['files']['total_files'] - $data['stats']['files']['deleted_files']; ?>,
                        <?php echo $data['stats']['files']['new_files']; ?>,
                        <?php echo $data['stats']['files']['deleted_files']; ?>
                    ],
                    backgroundColor: ['#20c997', '#667eea', '#e83e8c'],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                family: 'Poppins',
                                size: 12,
                                weight: '500'
                            }
                        }
                    }
                }
            }
        });

        <?php if ($_SESSION['role'] === 'super_admin'): ?>
        // Department Activity Chart (for super admin) with colorful bars
        const deptCtx = document.getElementById('departmentActivityChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo '"' . implode('","', array_column($data['departments'], 'department_code')) . '"'; ?>],
                datasets: [{
                    label: 'Files Uploaded',
                    data: [<?php echo implode(',', array_column($data['departments'], 'file_count')); ?>],
                    backgroundColor: [
                        '#20c997', '#667eea', '#fd7e14', '#6f42c1', 
                        '#e83e8c', '#17a2b8', '#ffc107', '#28a745'
                    ],
                    borderColor: [
                        '#1aa085', '#5a67d8', '#e8690b', '#5a2d91', 
                        '#d91a72', '#138496', '#e0a800', '#1e7e34'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    hoverBackgroundColor: [
                        '#1aa085', '#5a67d8', '#e8690b', '#5a2d91', 
                        '#d91a72', '#138496', '#e0a800', '#1e7e34'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                family: 'Poppins',
                                size: 12,
                                weight: '500'
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        }
                    }
                }
            }
        });
        <?php else: ?>
        // User Activity Chart (for department admin) with gradient colors
        const userCtx = document.getElementById('departmentActivityChart').getContext('2d');
        <?php 
        // Get top 5 users for chart
        $top_chart_users = array_slice($data['top_users'] ?? [], 0, 5);
        ?>
        new Chart(userCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo '"' . implode('","', array_map(function($u) { return substr($u['name'] . ' ' . $u['surname'], 0, 15); }, $top_chart_users)) . '"'; ?>],
                datasets: [{
                    label: 'Activities',
                    data: [<?php echo implode(',', array_column($top_chart_users, 'activities')); ?>],
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    borderRadius: 8
                }, {
                    label: 'Files Uploaded',
                    data: [<?php echo implode(',', array_column($top_chart_users, 'files_uploaded')); ?>],
                    backgroundColor: 'rgba(32, 201, 151, 0.8)',
                    borderColor: '#20c997',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                family: 'Poppins',
                                size: 12,
                                weight: '500'
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Poppins'
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>