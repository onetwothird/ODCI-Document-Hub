<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$folder_type_filter = isset($_GET['folder_type']) ? $_GET['folder_type'] : '';

// Build WHERE clause
$where_conditions = ["f.is_deleted = 0"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(f.folder_name LIKE ? OR f.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($department_filter)) {
    $where_conditions[] = "f.department_id = ?";
    $params[] = $department_filter;
}

if (!empty($folder_type_filter)) {
    $where_conditions[] = "f.folder_type = ?";
    $params[] = $folder_type_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM folders f 
                LEFT JOIN departments d ON f.department_id = d.id 
                LEFT JOIN users u ON f.created_by = u.id 
                WHERE $where_clause";

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_folders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_folders / $limit);

// Get folders with details
$folders_query = "SELECT f.*, 
                         d.department_name, d.department_code,
                         u.username, u.name as creator_name, u.surname,
                         CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as creator_full_name,
                         pf.folder_name as parent_folder_name
                  FROM folders f 
                  LEFT JOIN departments d ON f.department_id = d.id 
                  LEFT JOIN users u ON f.created_by = u.id 
                  LEFT JOIN folders pf ON f.parent_id = pf.id
                  WHERE $where_clause
                  ORDER BY f.created_at DESC 
                  LIMIT $limit OFFSET $offset";

$folders_stmt = $pdo->prepare($folders_query);
$folders_stmt->execute($params);
$folders = $folders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
$dept_stmt = $pdo->prepare($dept_query);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle folder actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $folder_id = $_POST['folder_id'] ?? 0;
    
    if ($action === 'delete' && $folder_id > 0) {
        // Check if folder has files or subfolders
        $check_query = "SELECT 
                           (SELECT COUNT(*) FROM files WHERE folder_id = ? AND is_deleted = 0) as file_count,
                           (SELECT COUNT(*) FROM folders WHERE parent_id = ? AND is_deleted = 0) as subfolder_count";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$folder_id, $folder_id]);
        $counts = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($counts['file_count'] > 0 || $counts['subfolder_count'] > 0) {
            $error_message = "Cannot delete folder. It contains " . $counts['file_count'] . " files and " . $counts['subfolder_count'] . " subfolders.";
        } else {
            $delete_query = "UPDATE folders SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?";
            $delete_stmt = $pdo->prepare($delete_query);
            if ($delete_stmt->execute([$_SESSION['user_id'], $folder_id])) {
                $success_message = "Folder deleted successfully.";
            } else {
                $error_message = "Failed to delete folder.";
            }
        }
    }
    
    if ($action === 'toggle_public' && $folder_id > 0) {
        $toggle_query = "UPDATE folders SET is_public = NOT is_public WHERE id = ?";
        $toggle_stmt = $pdo->prepare($toggle_query);
        if ($toggle_stmt->execute([$folder_id])) {
            $success_message = "Folder visibility updated.";
        } else {
            $error_message = "Failed to update folder visibility.";
        }
    }
    
    if ($action === 'change_status' && $folder_id > 0) {
        $status = $_POST['status'] ?? 'active';
        $status_query = "UPDATE folders SET folder_status = ? WHERE id = ?";
        $status_stmt = $pdo->prepare($status_query);
        if ($status_stmt->execute([$status, $folder_id])) {
            $success_message = "Folder status updated.";
        } else {
            $error_message = "Failed to update folder status.";
        }
    }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Folders - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        :root {
            --poppins: 'Poppins';
            
            /* Modern Color Palette */
            --primary-color: #10b981;
            --secondary-color: #059669;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --blue: #007bff;
            --secondary-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --warning-orange: #f59e0b;
            --danger-red: #ef4444;
            --info-cyan: #06b6d4;

    
                    
            /* Neutrals */
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            --light: #F9F9F9;
            --green: #28a745;
            --light-green: #cfffef;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --red: #DB504A;
            --yellow: #FFCE26;
            --light-yellow: #FFF2C6;
            --orange: #FD7238;
            --light-orange: #FFE0D3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fa;
            font-family: var(--poppins);
            overflow-x: hidden;
            min-height: 100vh;
        }

        #content main {
            width: 100%;
            padding: 40px 32px;
            font-family: var(--poppins);
            min-height: calc(100vh - 70px);
            overflow-y: auto;
            box-sizing: border-box;
        }

        /* Modern Header */
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 20px;
            color: white;
            padding: 35px;
            margin-bottom: 35px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
        }

        .header::before {
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

        .header .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .header h2 {
            font-size: 32px;
            font-weight: 800;
            margin: 0 0 8px 0;
        }

        .header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
            font-weight: 500;
        }
      

        .header-badge {
            background: rgba(255,255,255,0.2);
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
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
        /* Filter Card Enhancement */
        .filter-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .filter-card .card-body {
            padding: 30px;
            background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
        }

        .form-label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: var(--poppins);
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8fafc;
            font-weight: 500;
            font-family: var(--poppins);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
            font-family: var(--poppins);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        /* Enhanced Folder Cards */
        .folder-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: none;
            border-radius: 20px;
            background: white;
            box-shadow: 0 10px 35px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .folder-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .folder-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }

        .folder-card.system-folder::before {
            background: linear-gradient(90deg, var(--blue), var(--secondary-blue));
        }

        .folder-card.department-folder::before {
            background: linear-gradient(90deg, var(--success-color), #20c997);
        }
        .folder-icon {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            font-size: 1.5rem;
            margin-bottom: 15px;
            position: relative;
        }

        .folder-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
        }
     
        .card-title {
            font-weight: 700;
            color: #2d3748;
            font-size: 1.1rem;
            font-family: var(--poppins);
        }

        /* Enhanced Badges */
        .badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            font-family: var(--poppins);
        }

        .badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: badge-shine 3s infinite;
        }

        @keyframes badge-shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .public-badge { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb); 
            color: #155724; 
            border-color: rgba(21, 87, 36, 0.2);
        }
        .private-badge { 
            background: linear-gradient(135deg, #f8d7da, #f5c6cb); 
            color: #721c24; 
            border-color: rgba(114, 28, 36, 0.2);
        }

        /* Status Badges */
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
        }

        /* Action Buttons */
        .folder-actions {
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .folder-card:hover .folder-actions {
            opacity: 1;
        }

        .btn-outline-secondary {
            border: 2px solid #e2f0e8ff;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            font-family: var(--poppins);
        }

        .btn-outline-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* View Toggle Buttons */
        .btn-check:checked + .btn-outline-secondary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-color: var(--primary-color);
            color: white;
        }

        /* List View Table */
        .files-table {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 35px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 30px;
        }

        .table {
            margin-bottom: 0;
            font-family: var(--poppins);
        }

        .table th {
            background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
            border-bottom: 2px solid #e3e6f0;
            font-weight: 700;
            color: #2d3748;
            padding: 20px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .table td {
            padding: 20px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f4f8;
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .pagination-container {
            margin-top: 32px;
            padding: 24px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-100);
        }

        .pagination-info {
            color: var(--gray-600);
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pagination-info i {
            color: var(--primary-green);
        }

        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-link {
            border: 2px solid var(--gray-200);
            color: var(--gray-600);
            padding: 14px 18px;
            margin: 0 6px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 14px;
            min-width: 48px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            background: var(--gray-50);
            text-decoration: none;
            font-family: var(--poppins);
        }

        .pagination .page-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(40, 167, 69, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .pagination .page-link:hover::before {
            left: 100%;
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-dark) 100%);
            border-color: black;
            color: black;
            transform: translateY(-3px) scale(1.05);
            position: relative;
            z-index: 2;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }

        .pagination .page-item.active .page-link::before {
            display: none;
        }

        .pagination .page-item.active .page-link::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--primary-green-light), var(--primary-green), var(--primary-green-dark), var(--primary-green));
            border-radius: 16px;
            z-index: -1;
            animation: borderGlow 2s linear infinite;
            background-size: 400% 400%;
        }

        @keyframes borderGlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

          .pagination-container {
            margin-top: 32px;
            padding: 24px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-100);
        }

        .pagination-info {
            color: var(--gray-600);
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pagination-info i {
            color: var(--primary-green);
        }

        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-link {
            border: 2px solid var(--gray-200);
            color: black;
            padding: 14px 18px;
            margin: 0 6px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 14px;
            min-width: 48px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            background: var(--gray-50);
            text-decoration: none;
        }

        .pagination .page-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .pagination .page-link:hover::before {
            left: 100%;
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-dark) 100%);
            border-color: var(--primary-green);
            color: var(--primary-green-dark);
            transform: translateY(-3px) scale(1.05);
            position: relative;
            z-index: 2;
        }

        .pagination .page-item.active .page-link::before {
            display: none;
        }

        .pagination .page-item.active .page-link::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--primary-green-light), var(--primary-green), var(--primary-green-dark), var(--primary-green));
            border-radius: 16px;
            z-index: -1;
            animation: borderGlow 2s linear infinite;
        }

        @keyframes borderGlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .pagination .page-link:hover:not(.active) {
            background: linear-gradient(135deg, var(--primary-green-lightest) 0%, white 100%);
            border-color: var(--primary-green-light);
            color: var(--primary-green-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.2);
        }

        .pagination .page-item.disabled .page-link {
            background: var(--gray-100);
            border-color: var(--gray-200);
            color: var(--gray-400);
            cursor: not-allowed;
            transform: none;
        }

        .pagination .page-item.disabled .page-link:hover {
            background: var(--gray-100);
            border-color: var(--gray-200);
            color: var(--gray-400);
            transform: none;
            box-shadow: none;
        }

        /* Navigation arrows styling */
        .pagination .page-link i {
            font-size: 12px;
        }

        /* Ellipsis styling */
        .pagination .page-item.disabled .page-link {
            border: none;
            background: transparent;
            color: var(--gray-400);
            font-weight: 700;
            font-size: 16px;
            padding: 14px 8px;
        }

        /* First/Last page indicators */
        .pagination .page-item:first-child .page-link,
        .pagination .page-item:last-child .page-link {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-50) 100%);
        }

        .pagination .page-item:first-child .page-link:hover,
        .pagination .page-item:last-child .page-link:hover {
            background: linear-gradient(135deg, var(--primary-green-lightest) 0%, var(--primary-green-lightest) 100%);
        }

        /* Mobile pagination adjustments */
        @media (max-width: 768px) {
            .pagination-container {
                padding: 20px 16px;
            }
            
            .pagination .page-link {
                padding: 12px 14px;
                margin: 0 3px;
                min-width: 40px;
                font-size: 13px;
            }
            
            .pagination-info {
                font-size: 13px;
                text-align: center;
                margin-bottom: 16px;
            }
            
            .pagination-container .d-flex {
                flex-direction: column;
                gap: 16px;
            }
            
            .pagination {
                justify-content: center;
            }
        }
        /* Alerts Enhancement */
        .alert {
            border: none;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            font-weight: 500;
            box-shadow: 0 10px 35px rgba(0,0,0,0.1);
            border-left: 4px solid;
            font-family: var(--poppins);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #ffffff);
            color: #155724;
            border-left-color: var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #ffffff);
            color: #721c24;
            border-left-color: var(--danger-color);
        }

        /* Empty State Enhancement */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: #718096;
            background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
            border-radius: 20px;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 2rem;
            opacity: 0.3;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .empty-state h5 {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
            font-family: var(--poppins);
        }

        /* Stats Display */
        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            line-height: 1;
            font-family: var(--poppins);
        }

        .stat-label {
            font-size: 0.85rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin: 0;
            font-family: var(--poppins);
        }

        /* Dropdown Enhancements */
        .dropdown-menu {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 35px rgba(0,0,0,0.15);
            padding: 8px 0;
        }

        .dropdown-item {
            padding: 12px 20px;
            font-weight: 500;
            font-family: var(--poppins);
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .header .header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 25px 20px;
            }
            
            .header h2 {
                font-size: 24px;
            }
        }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
    <?php include 'components/sidebar.html'; ?>
    
    <!-- Content -->
    <section id="content">
        <?php include 'components/navbar.html'; ?>
        <div class="container-fluid">
            
          <main>
            <div class="head-title">
                <div class="left">
                    <h1>Folder Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Admin</li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="">All Folders</a></li>
                    </ul>
                </div>
            </div>
            <!-- Modern Header -->
            <div class="header">
                <div class="header-content">
                    <div>
                        <h2><i class="fas fa-folder me-3"></i>All Folders</h2>
                        <p>Manage and organize your folder structure</p>
                    </div>
                    <div class="header-badge">
                        <?php echo number_format($total_folders); ?> Folders
                    </div>
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

            <!-- Filters -->
            <div class="filter-card card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Folders</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by folder name or description...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Folder Type</label>
                            <select class="form-select" name="folder_type">
                                <option value="">All Types</option>
                                <option value="category" <?php echo $folder_type_filter == 'category' ? 'selected' : ''; ?>>Category</option>
                                <option value="custom" <?php echo $folder_type_filter == 'custom' ? 'selected' : ''; ?>>Custom</option>
                                <option value="system" <?php echo $folder_type_filter == 'system' ? 'selected' : ''; ?>>System</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="view" id="grid-view" autocomplete="off" checked>
                    <label class="btn btn-outline-secondary" for="grid-view">
                        <i class="fas fa-th-large me-2"></i>Grid View
                    </label>
                    <input type="radio" class="btn-check" name="view" id="list-view" autocomplete="off">
                    <label class="btn btn-outline-secondary" for="list-view">
                        <i class="fas fa-list me-2"></i>List View
                    </label>
                </div>
                
                <div class="text-muted">
                    <small><i class="fas fa-info-circle me-1"></i>Showing <?php echo count($folders); ?> of <?php echo $total_folders; ?> folders</small>
                </div>
            </div>

            <!-- Grid View -->
            <div id="grid-container">
                <div class="row g-4">
                    <?php foreach ($folders as $folder): ?>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="folder-card card h-100 position-relative <?php echo $folder['is_system_folder'] ? 'system-folder' : ($folder['department_id'] ? 'department-folder' : ''); ?>">
                                <!-- Status Badge -->
                                <div class="status-badge">
                                    <?php if ($folder['folder_status'] === 'archived'): ?>
                                        <span class="badge bg-warning">Archived</span>
                                    <?php elseif ($folder['folder_status'] === 'hidden'): ?>
                                        <span class="badge bg-secondary">Hidden</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </div>

                                <div class="card-body text-center">
                                    <div class="folder-icon mx-auto" style="background-color: <?php echo $folder['folder_color']; ?>; color: white;">
                                        <i class="<?php echo $folder['folder_icon']; ?>"></i>
                                    </div>
                                    
                                    <h6 class="card-title mb-2"><?php echo htmlspecialchars($folder['folder_name']); ?></h6>
                                    
                                    <div class="mb-2">
                                        <span class="badge <?php echo $folder['is_public'] ? 'public-badge' : 'private-badge'; ?> text-white">
                                            <i class="fas <?php echo $folder['is_public'] ? 'fa-globe' : 'fa-lock'; ?>"></i>
                                            <?php echo $folder['is_public'] ? 'Public' : 'Private'; ?>
                                        </span>
                                    </div>

                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <div class="stat-value text-primary"><?php echo number_format($folder['file_count']); ?></div>
                                            <div class="stat-label">Files</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-value text-info"><?php echo formatFileSize($folder['folder_size']); ?></div>
                                            <div class="stat-label">Size</div>
                                        </div>
                                    </div>

                                    <?php if ($folder['department_name']): ?>
                                        <div class="mb-2">
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($folder['department_code']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-muted small mb-3">
                                        <div><strong>Created by:</strong> <?php echo htmlspecialchars($folder['creator_full_name']); ?></div>
                                        <div><i class="fas fa-calendar-alt me-1"></i><?php echo date('M j, Y', strtotime($folder['created_at'])); ?></div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="folder-actions">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-cog me-2"></i>Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="folder_details.php?id=<?php echo $folder['id']; ?>" style="color: var(--info-cyan);">
                                                        <i class="fas fa-eye me-2"></i>View Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Toggle visibility?')">
                                                            <input type="hidden" name="action" value="toggle_public">
                                                            <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                                            <button type="submit" class="dropdown-item" style="color: var(--accent-purple); background: none; border: none; text-align: left; width: 100%;">
                                                                <i class="fas <?php echo $folder['is_public'] ? 'fa-lock' : 'fa-globe'; ?> me-2"></i>
                                                                Make <?php echo $folder['is_public'] ? 'Private' : 'Public'; ?>
                                                            </button>
                                                        </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li class="dropdown-submenu">
                                                    <a class="dropdown-item" href="#" style="color: var(--warning-orange);">
                                                            <i class="fas fa-cog me-2"></i>Change Status
                                                        </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this folder?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                                            <button type="submit" class="dropdown-item" style="color: var(--danger-red); background: none; border: none; text-align: left; width: 100%;">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </button>
                                                        </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($folders)): ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <h5>No folders found</h5>
                                <p>Try adjusting your search criteria or create a new folder.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- List View -->
            <div id="list-container" style="display: none;">
                <div class="files-table card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Folder</th>
                                        <th>Type</th>
                                        <th>Department</th>
                                        <th>Creator</th>
                                        <th>Files</th>
                                        <th>Size</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($folders as $folder): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="folder-icon me-3" style="background-color: <?php echo $folder['folder_color']; ?>; color: white; width: 40px; height: 40px; border-radius: 8px; font-size: 1rem;">
                                                        <i class="<?php echo $folder['folder_icon']; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-medium"><?php echo htmlspecialchars($folder['folder_name']); ?></div>
                                                        <span class="badge <?php echo $folder['is_public'] ? 'public-badge' : 'private-badge'; ?> text-white">
                                                            <i class="fas <?php echo $folder['is_public'] ? 'fa-globe' : 'fa-lock'; ?>"></i>
                                                            <?php echo $folder['is_public'] ? 'Public' : 'Private'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo ucfirst($folder['folder_type']); ?></span>
                                                <?php if ($folder['is_system_folder']): ?>
                                                    <span class="badge bg-primary">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($folder['department_name']): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($folder['department_code']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($folder['creator_full_name']); ?></div>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($folder['username']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo number_format($folder['file_count']); ?></span>
                                            </td>
                                            <td><?php echo formatFileSize($folder['folder_size']); ?></td>
                                            <td>
                                                <?php if ($folder['folder_status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($folder['folder_status'] === 'archived'): ?>
                                                    <span class="badge bg-warning">Archived</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Hidden</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?php echo date('M j, Y', strtotime($folder['created_at'])); ?></div>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($folder['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="folder_details.php?id=<?php echo $folder['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this folder?')">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                                                <button type="submit" class="text-danger">
                                                                    <i class="fas fa-trash me-2"></i>Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_files); ?> 
                        of <?php echo number_format($total_files); ?> files
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
        </div>
    </section>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View toggle functionality
        document.getElementById('grid-view').addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('grid-container').style.display = 'block';
                document.getElementById('list-container').style.display = 'none';
            }
        });

        document.getElementById('list-view').addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('grid-container').style.display = 'none';
                document.getElementById('list-container').style.display = 'block';
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            let alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                let bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 1800);

        // Add loading animation to form submissions
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                let submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                    submitBtn.disabled = true;
                }
            });
        });
    </script>
</body>
</html>