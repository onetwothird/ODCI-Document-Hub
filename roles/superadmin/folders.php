<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Ensure super admin access
$currentUser = requireSuperAdmin();
if (!$currentUser) {
    header('Location: ../../login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_folder':
                $stmt = $pdo->prepare("
                    INSERT INTO folders (folder_name, created_by, parent_id, department_id, category, 
                                       folder_type, description, folder_color, folder_icon, is_public, permissions) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $permissions = json_encode(['read' => [], 'write' => [], 'admin' => [$currentUser['id']]]);
                $stmt->execute([
                    $_POST['folder_name'],
                    $currentUser['id'],
                    $_POST['parent_id'] ?: null,
                    $_POST['department_id'] ?: null,
                    $_POST['category'] ?: null,
                    $_POST['folder_type'],
                    $_POST['description'] ?: null,
                    $_POST['folder_color'] ?: '#006b2e',
                    $_POST['folder_icon'] ?: 'fa-folder',
                    isset($_POST['is_public']) ? 1 : 0,
                    $permissions
                ]);
                echo json_encode(['success' => true, 'message' => 'Folder created successfully']);
                break;

            case 'update_folder':
                $stmt = $pdo->prepare("
                    UPDATE folders 
                    SET folder_name = ?, department_id = ?, category = ?, description = ?, 
                        folder_color = ?, folder_icon = ?, is_public = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['folder_name'],
                    $_POST['department_id'] ?: null,
                    $_POST['category'] ?: null,
                    $_POST['description'] ?: null,
                    $_POST['folder_color'],
                    $_POST['folder_icon'],
                    isset($_POST['is_public']) ? 1 : 0,
                    $_POST['folder_id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Folder updated successfully']);
                break;

            case 'delete_folder':
                $stmt = $pdo->prepare("
                    UPDATE folders 
                    SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$currentUser['id'], $_POST['folder_id']]);
                echo json_encode(['success' => true, 'message' => 'Folder deleted successfully']);
                break;

            case 'restore_folder':
                $stmt = $pdo->prepare("
                    UPDATE folders 
                    SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL 
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['folder_id']]);
                echo json_encode(['success' => true, 'message' => 'Folder restored successfully']);
                break;

            case 'set_permissions':
                $permissions = [
                    'read' => $_POST['read_users'] ?? [],
                    'write' => $_POST['write_users'] ?? [],
                    'admin' => $_POST['admin_users'] ?? []
                ];
                $stmt = $pdo->prepare("UPDATE folders SET permissions = ? WHERE id = ?");
                $stmt->execute([json_encode($permissions), $_POST['folder_id']]);
                echo json_encode(['success' => true, 'message' => 'Permissions updated successfully']);
                break;

            case 'move_folder':
                $stmt = $pdo->prepare("UPDATE folders SET parent_id = ? WHERE id = ?");
                $stmt->execute([$_POST['new_parent_id'] ?: null, $_POST['folder_id']]);
                echo json_encode(['success' => true, 'message' => 'Folder moved successfully']);
                break;

            case 'import_folder':
                if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
                    break;
                }

                $fileContent = file_get_contents($_FILES['import_file']['tmp_name']);
                $importData = json_decode($fileContent, true);

                if (!$importData || !isset($importData['folders'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid import file format']);
                    break;
                }

                $targetParentId = $_POST['target_parent_id'] ?: null;
                $preserveIds = isset($_POST['preserve_ids']) && $_POST['preserve_ids'] == '1';
                $importedCount = 0;

                // Recursive function to import folders
                function importFoldersRecursive($folders, $parentId, $pdo, $currentUser, &$importedCount, $preserveIds) {
                    foreach ($folders as $folder) {
                        $folderId = $preserveIds && isset($folder['id']) ? $folder['id'] : null;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO folders (folder_name, created_by, parent_id, department_id, category, 
                                               folder_type, description, folder_color, folder_icon, is_public, permissions) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $permissions = json_encode(['read' => [], 'write' => [], 'admin' => [$currentUser['id']]]);
                        $stmt->execute([
                            $folder['folder_name'],
                            $currentUser['id'],
                            $parentId,
                            $folder['department_id'] ?: null,
                            $folder['category'] ?: null,
                            $folder['folder_type'] ?? 'custom',
                            $folder['description'] ?: null,
                            $folder['folder_color'] ?? '#006b2e',
                            $folder['folder_icon'] ?? 'fa-folder',
                            $folder['is_public'] ?? 0,
                            $permissions
                        ]);
                        
                        $newFolderId = $preserveIds && $folderId ? $folderId : $pdo->lastInsertId();
                        $importedCount++;

                        // Import subfolders
                        if (isset($folder['children']) && is_array($folder['children'])) {
                            importFoldersRecursive($folder['children'], $newFolderId, $pdo, $currentUser, $importedCount, $preserveIds);
                        }
                    }
                }

                importFoldersRecursive($importData['folders'], $targetParentId, $pdo, $currentUser['id'], $importedCount, $preserveIds);
                
                echo json_encode(['success' => true, 'message' => "Successfully imported {$importedCount} folders"]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get folders with detailed information
$folderQuery = "
    SELECT f.*, 
           d.department_name, d.department_code,
           creator.username as creator_username,
           CONCAT(creator.name, ' ', IFNULL(creator.mi, ''), ' ', creator.surname) as creator_full_name,
           (SELECT COUNT(*) FROM files WHERE folder_id = f.id AND is_deleted = 0) as file_count,
           (SELECT IFNULL(SUM(file_size), 0) FROM files WHERE folder_id = f.id AND is_deleted = 0) as total_size
    FROM folders f
    LEFT JOIN departments d ON f.department_id = d.id
    LEFT JOIN users creator ON f.created_by = creator.id
    WHERE f.is_deleted = 0
    ORDER BY f.folder_level, f.folder_name
";
$folders = $pdo->query($folderQuery)->fetchAll();

// Get deleted folders for trash view
$deletedFoldersQuery = "
    SELECT f.*, 
           d.department_name, d.department_code,
           creator.username as creator_username,
           CONCAT(creator.name, ' ', IFNULL(creator.mi, ''), ' ', creator.surname) as creator_full_name,
           deleter.username as deleted_by_username
    FROM folders f
    LEFT JOIN departments d ON f.department_id = d.id
    LEFT JOIN users creator ON f.created_by = creator.id
    LEFT JOIN users deleter ON f.deleted_by = deleter.id
    WHERE f.is_deleted = 1
    ORDER BY f.deleted_at DESC
";
$deletedFolders = $pdo->query($deletedFoldersQuery)->fetchAll();

// Get departments for dropdown
$departments = $pdo->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();

// Get users for permissions
$users = $pdo->query("
    SELECT id, username, CONCAT(name, ' ', IFNULL(mi, ''), ' ', surname) as full_name, 
           position, department_id 
    FROM users 
    WHERE is_approved = 1 
    ORDER BY name, surname
")->fetchAll();

// Folder statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_folders,
        COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_folders,
        COUNT(CASE WHEN folder_type = 'system' THEN 1 END) as system_folders,
        IFNULL(SUM(folder_size), 0) as total_size,
        (SELECT COUNT(*) FROM files WHERE is_deleted = 0) as total_files
    FROM folders 
    WHERE is_deleted = 0
")->fetch();
// Normalise a stored folder icon so it always renders. Values saved by the
// icon picker are bare names (e.g. "fa-folder"), which need the Font Awesome
// base class to actually draw - without it the icon silently disappears.
function folderIconClass($icon) {
    $icon = trim((string) $icon);
    if ($icon === '') {
        return 'fa-solid fa-folder';
    }
    if (strpos($icon, ' ') !== false) {
        return $icon; // already carries a base class, e.g. "fa fa-folder" / "bx bx-folder"
    }
    if (strpos($icon, 'fa-') === 0) {
        return 'fa-solid ' . $icon;
    }
    if (strpos($icon, 'bx-') === 0) {
        return 'bx ' . $icon;
    }
    return $icon;
}

// A folder with no stored colour falls back to the CVSU green.
function folderIconColor($color) {
    $color = trim((string) $color);
    return $color !== '' ? $color : '#006b2e';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folder Management - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .folder-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #e1e5e9;
            position: relative;
        }

        .folder-card:hover {
            box-shadow: 0 4px 20px rgba(24,47,31,0.12);
            transform: none;
        }

        .folder-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .folder-icon {
            font-size: 2.5rem;
            margin-right: 1rem;
            opacity: 0.8;
        }

        .folder-info h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
        }

        .folder-meta {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 0.25rem;
        }

        .folder-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin: 1rem 0;
            padding: 0.75rem;
            background: #f7fafc;
            border: 1px solid #e6ebe7;
            border-radius: 10px;
            text-align: center;
        }

        .stat-item {
            text-align: center;
            min-width: 0;
            padding: 4px 2px;
            border-right: 1px solid #e6ebe7;
        }

        /* The shared theme (loaded later) paints a rail + padding on generic
           .stat-item tiles; inside this compact strip we want plain cells. */
        .superadmin-management-page .folder-stats .stat-item {
            background: transparent !important;
            border: none !important;
            border-right: 1px solid #e6ebe7 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 4px 2px !important;
            display: block !important;
            gap: 0 !important;
        }

        .superadmin-management-page .folder-stats .stat-item:last-child {
            border-right: none !important;
        }

        .superadmin-management-page .folder-stats {
            background: #f7fafc !important;
            border: 1px solid #e6ebe7 !important;
            box-shadow: none !important;
            border-radius: 10px !important;
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-value {
            font-weight: 700;
            color: #1e7e34;
            display: block;
            font-size: 0.95rem;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
        }

        .folder-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-primary {
            background: #006b2e;
            color: white;
        }

        .btn-secondary {
            background: #fff;
            color: #004d24;
            border: 1px solid #e2e9e3;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-success {
            background: #006b2e;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            color: #16241c;
            padding: 1.1rem 1.25rem;
            border-radius: 12px;
            border: 1px solid #e6ebe7;
            display: flex;
            align-items: center;
            gap: 0.9rem;
            min-width: 0;
            box-shadow: 0 1px 3px rgba(24,47,31,.045);
        }

        .stat-card .stat-icon {
            width: 46px;
            height: 46px;
            min-width: 46px;
            border-radius: 12px;
            background: rgba(30, 126, 52, 0.10);
            border: 1px solid rgba(30, 126, 52, 0.16);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e7e34;
            font-size: 22px;
        }

        .stat-card .stat-icon.gold {
            background: rgba(240, 192, 0, 0.16);
            border-color: rgba(240, 192, 0, 0.35);
            color: #8a6300;
        }

        .stat-card .stat-info {
            min-width: 0;
        }

        .stat-card h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.15;
            color: #1e7e34;
            overflow-wrap: anywhere;
        }

        .stat-card p {
            margin: 2px 0 0;
            font-size: 0.8rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .controls-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-box {
            flex: 1;
            max-width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .view-toggle {
            display: flex;
            background: #f7fafc;
            border-radius: 8px;
            padding: 0.25rem;
        }

        .view-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .view-btn.active {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(3px);
            z-index: 2500;
            align-items: center;
            justify-content: center;
            padding: 32px;
            animation: fadeIn 0.25s ease;
        }

        /* Center the dialog within the content area (right of the sidebar) */
        @media (min-width: 769px) {
            .modal { padding-left: calc(60px + 32px); }
            body:has(#sidebar.expanded) .modal { padding-left: calc(280px + 32px); }
        }

        .modal.show {
            display: flex;
        }

        @media (max-width: 1024px) {
            .modal { padding: 20px 16px; }
        }

        .modal-content {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.75rem;
            max-width: 520px;
            width: 100%;
            max-height: calc(100vh - 64px);
            overflow-y: auto;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
            animation: modalSlide 0.28s ease;
        }

        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(14px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .color-picker {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .color-option.selected {
            border-color: #2d3748;
            transform: scale(1.1);
        }

        .icon-picker {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .icon-option {
            padding: 0.75rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .icon-option.selected {
            background: #006b2e;
            color: white;
        }

        .permission-group {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .permission-group h4 {
            margin: 0 0 0.75rem 0;
            color: #2d3748;
        }

        .user-select {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.5rem;
        }

        .user-checkbox {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .user-checkbox:hover {
            background: #f7fafc;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #718096;
        }

        .breadcrumb a {
            color: #006b2e;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .folders-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .folders-table th,
        .folders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .folders-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #2d3748;
        }

        /* Views are exclusive: only the selected one renders. */
        .grid-view,
        .table-view,
        .tree-view,
        .trash-view {
            display: none;
        }

        .grid-view.active,
        .table-view.active,
        .tree-view.active,
        .trash-view.active {
            display: block;
        }

        .table-view {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .folder-tree {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e6ebe7;
            margin-top: 1.25rem;
        }

        .tree-item {
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.2s;
        }

        .tree-item:hover {
            background: #f7fafc;
        }

        .tree-item.level-1 { padding-left: 2rem; }
        .tree-item.level-2 { padding-left: 3rem; }
        .tree-item.level-3 { padding-left: 4rem; }

        .folder-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-public { background: #c6f6d5; color: #22543d; }
        .badge-private { background: #fed7d7; color: #742a2a; }
        .badge-system { background: #fbb6ce; color: #702459; }

        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
            color: #718096;
        }

        .trash-view {
            display: none;
        }

        .trash-view.active {
            display: block;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .folder-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .stat-card {
                padding: 0.9rem 1rem;
                gap: 0.65rem;
            }

            .stat-card h3 {
                font-size: 1.25rem;
            }
            
            .controls-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .folder-stats {
                gap: 4px;
                padding: 0.625rem;
            }

            .stat-value {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 380px) {
            .folder-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 2px;
            }

            .stat-value {
                font-size: 0.85rem;
            }

            .stat-label {
                font-size: 0.65rem;
            }
        }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
    <link rel="stylesheet" href="assets/css/management-pages.css?v=1.0">
</head>

<body class="superadmin-management-page folders-management-page">
    <!-- Sidebar -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Folder Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li class="active">Folders</li>
                    </ul>
                </div>
                <div class="head-actions" style="display: flex; gap: 10px; align-items: center;">
                    <button class="btn-action btn-secondary" onclick="showImportModal()" title="Import Folder Structure">
                        <i class='bx bx-import'></i>
                        <span class="text">Import</span>
                    </button>
                    <button class="btn btn-primary" onclick="showCreateModal()">
                        <i class='bx bx-plus'></i>
                        <span class="text">New Folder</span>
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class='bx bxs-folder'></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['total_folders']) ?></h3>
                        <p>Total Folders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class='bx bxs-file-doc'></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['total_files']) ?></h3>
                        <p>Total Files</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon gold"><i class='bx bxs-show'></i></div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['public_folders']) ?></h3>
                        <p>Public Folders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class='bx bxs-cloud'></i></div>
                    <div class="stat-info">
                        <h3><?= formatFileSize($stats['total_size']) ?></h3>
                        <p>Total Storage</p>
                    </div>
                </div>
            </div>

            <!-- Controls Bar -->
            <div class="controls-bar">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search folders..." onkeyup="filterFolders()">
                </div>
                
                <div class="view-toggle">
                    <button class="view-btn active" onclick="setView('grid')" data-view="grid">
                        <i class='bx bx-grid-alt'></i> Grid
                    </button>
                    <button class="view-btn" onclick="setView('table')" data-view="table">
                        <i class='bx bx-list-ul'></i> Table
                    </button>
                    <button class="view-btn" onclick="setView('tree')" data-view="tree">
                        <i class='bx bx-sitemap'></i> Tree
                    </button>
                    <button class="view-btn" onclick="setView('trash')" data-view="trash">
                        <i class='bx bx-trash'></i> Trash
                    </button>
                </div>

                <select id="departmentFilter" onchange="filterFolders()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="typeFilter" onchange="filterFolders()">
                    <option value="">All Types</option>
                    <option value="category">Category</option>
                    <option value="custom">Custom</option>
                    <option value="system">System</option>
                </select>
            </div>

            <!-- Grid View -->
            <div id="gridView" class="grid-view active">
                <div class="folder-grid" id="folderGrid">
                    <?php foreach ($folders as $folder): ?>
                        <div class="folder-card" 
                             data-department="<?= $folder['department_id'] ?>" 
                             data-type="<?= $folder['folder_type'] ?>"
                             data-name="<?= strtolower($folder['folder_name']) ?>">
                            
                            <div class="folder-header">
                                <i class="<?= folderIconClass($folder['folder_icon']) ?> folder-icon" 
                                   style="color: <?= folderIconColor($folder['folder_color']) ?>"></i>
                                <div class="folder-info">
                                    <h3><?= htmlspecialchars($folder['folder_name']) ?></h3>
                                    <div class="folder-meta">
                                        <span class="folder-badge badge-<?= $folder['is_public'] ? 'public' : 'private' ?>">
                                            <?= $folder['is_public'] ? 'Public' : 'Private' ?>
                                        </span>
                                        <?php if ($folder['folder_type'] === 'system'): ?>
                                            <span class="folder-badge badge-system">System</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($folder['description']): ?>
                                <p style="font-size: 0.85rem; color: #718096; margin-bottom: 1rem;">
                                    <?= htmlspecialchars($folder['description']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="folder-stats">
                                <div class="stat-item">
                                    <span class="stat-value"><?= $folder['file_count'] ?></span>
                                    <span class="stat-label">Files</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?= formatFileSize($folder['total_size']) ?></span>
                                    <span class="stat-label">Size</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?= $folder['access_count'] ?></span>
                                    <span class="stat-label">Views</span>
                                </div>
                            </div>

                            <div style="font-size: 0.8rem; color: #718096; margin-bottom: 1rem;">
                                <div>Created by: <?= htmlspecialchars($folder['creator_full_name']) ?></div>
                                <div>Department: <?= $folder['department_name'] ?: 'None' ?></div>
                                <div>Created: <?= date('M j, Y', strtotime($folder['created_at'])) ?></div>
                            </div>

                            <div class="folder-actions">
                                <button class="btn-action btn-primary" onclick="viewFolderContents(<?= $folder['id'] ?>)">
                                    <i class='bx bx-folder-open'></i> Open
                                </button>
                                <button class="btn-action btn-secondary" onclick="editFolder(<?= $folder['id'] ?>)">
                                    <i class='bx bx-edit'></i> Edit
                                </button>
                                <button class="btn-action btn-secondary" onclick="manageFolderPermissions(<?= $folder['id'] ?>)">
                                    <i class='bx bx-lock'></i> Permissions
                                </button>
                                <button class="btn-action btn-danger" onclick="deleteFolder(<?= $folder['id'] ?>)">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Table View -->
            <div id="tableView" class="table-view">
                <table class="folders-table">
                    <thead>
                        <tr>
                            <th>Folder</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th>Files</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="foldersTableBody">
                        <?php foreach ($folders as $folder): ?>
                            <tr data-department="<?= $folder['department_id'] ?>" 
                                data-type="<?= $folder['folder_type'] ?>"
                                data-name="<?= strtolower($folder['folder_name']) ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <i class="<?= folderIconClass($folder['folder_icon']) ?>" 
                                           style="color: <?= folderIconColor($folder['folder_color']) ?>; font-size: 1.5rem;"></i>
                                        <div>
                                            <strong><?= htmlspecialchars($folder['folder_name']) ?></strong>
                                            <?php if ($folder['description']): ?>
                                                <div style="font-size: 0.8rem; color: #718096;">
                                                    <?= htmlspecialchars(substr($folder['description'], 0, 50)) ?>...
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="folder-badge badge-<?= $folder['folder_type'] ?>">
                                        <?= ucfirst($folder['folder_type']) ?>
                                    </span>
                                    <?php if ($folder['is_public']): ?>
                                        <span class="folder-badge badge-public">Public</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $folder['department_name'] ?: 'None' ?></td>
                                <td><?= $folder['file_count'] ?></td>
                                <td><?= formatFileSize($folder['total_size']) ?></td>
                                <td><?= date('M j, Y', strtotime($folder['created_at'])) ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn-action btn-primary" onclick="viewFolderContents(<?= $folder['id'] ?>)">
                                            <i class='bx bx-folder-open'></i>
                                        </button>
                                        <button class="btn-action btn-secondary" onclick="editFolder(<?= $folder['id'] ?>)">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                        <button class="btn-action btn-danger" onclick="deleteFolder(<?= $folder['id'] ?>)">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tree View -->
            <div id="treeView" class="tree-view">
                <div class="folder-tree">
                    <h3 style="margin-bottom: 1rem;">Folder Hierarchy</h3>
                    <div id="folderTree">
                        <?php 
                        function renderFolderTree($folders, $parentId = null, $level = 0) {
                            foreach ($folders as $folder) {
                                if ($folder['parent_id'] == $parentId) {
                                    echo '<div class="tree-item level-' . $level . '" data-folder-id="' . $folder['id'] . '">';
                                    echo '<div style="display: flex; align-items: center; gap: 0.75rem;">';
                                    echo '<i class="' . folderIconClass($folder['folder_icon']) . '" style="color: ' . folderIconColor($folder['folder_color']) . ';"></i>';
                                    echo '<span><strong>' . htmlspecialchars($folder['folder_name']) . '</strong></span>';
                                    echo '<span class="folder-badge badge-' . ($folder['is_public'] ? 'public' : 'private') . '">';
                                    echo $folder['is_public'] ? 'Public' : 'Private';
                                    echo '</span>';
                                    if ($folder['department_name']) {
                                        echo '<span style="font-size: 0.8rem; color: #718096;">(' . $folder['department_name'] . ')</span>';
                                    }
                                    echo '</div>';
                                    echo '<div style="display: flex; gap: 0.5rem;">';
                                    echo '<span style="font-size: 0.8rem; color: #718096;">' . $folder['file_count'] . ' files</span>';
                                    echo '<button class="btn-action btn-primary" onclick="viewFolderContents(' . $folder['id'] . ')"><i class="bx bx-folder-open"></i></button>';
                                    echo '<button class="btn-action btn-secondary" onclick="editFolder(' . $folder['id'] . ')"><i class="bx bx-edit"></i></button>';
                                    echo '</div>';
                                    echo '</div>';
                                    
                                    // Recursively render child folders
                                    renderFolderTree($folders, $folder['id'], $level + 1);
                                }
                            }
                        }
                        renderFolderTree($folders);
                        ?>
                    </div>
                </div>
            </div>

            <!-- Trash View -->
            <div id="trashView" class="trash-view">
                <div class="folder-tree">
                    <h3 style="margin-bottom: 1rem;">Deleted Folders</h3>
                    <?php if (empty($deletedFolders)): ?>
                        <div class="empty-state">
                            <i class='bx bx-trash'></i>
                            <h3>No deleted folders</h3>
                            <p>All folders are active</p>
                        </div>
                    <?php else: ?>
                        <div class="folder-grid">
                            <?php foreach ($deletedFolders as $folder): ?>
                                <div class="folder-card" style="opacity: 0.7; border-color: #f56565;">
                                    <div class="folder-header">
                                        <i class="<?= folderIconClass($folder['folder_icon']) ?> folder-icon" 
                                           style="color: <?= folderIconColor($folder['folder_color']) ?>"></i>
                                        <div class="folder-info">
                                            <h3><?= htmlspecialchars($folder['folder_name']) ?></h3>
                                            <div class="folder-meta">
                                                Deleted by: <?= htmlspecialchars($folder['deleted_by_username']) ?><br>
                                                On: <?= date('M j, Y g:i A', strtotime($folder['deleted_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="folder-actions">
                                        <button class="btn-action btn-success" onclick="restoreFolder(<?= $folder['id'] ?>)">
                                            <i class='bx bx-undo'></i> Restore
                                        </button>
                                        <button class="btn-action btn-danger" onclick="permanentDeleteFolder(<?= $folder['id'] ?>)">
                                            <i class='bx bx-trash'></i> Permanent Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Loading indicator -->
            <div id="loading" class="loading">
                <i class='bx bx-loader-alt bx-spin' style="font-size: 2rem;"></i>
                <p>Loading...</p>
            </div>
        </main>
    </section>

    <!-- Create/Edit Folder Modal -->
    <div id="folderModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 id="modalTitle">Create New Folder</h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">
                    <i class='bx bx-x'></i>
                </button>
            </div>

            <form id="folderForm">
                <input type="hidden" id="folderId" name="folder_id">
                <input type="hidden" id="actionType" name="action" value="create_folder">

                <div class="form-group">
                    <label for="folderName">Folder Name *</label>
                    <input type="text" id="folderName" name="folder_name" required>
                </div>

                <div class="form-group">
                    <label for="parentFolder">Parent Folder</label>
                    <select id="parentFolder" name="parent_id">
                        <option value="">Root Level</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?= $folder['id'] ?>">
                                <?= str_repeat('— ', $folder['folder_level']) ?><?= htmlspecialchars($folder['folder_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="departmentSelect">Department</label>
                    <select id="departmentSelect" name="department_id">
                        <option value="">No Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="folderType">Folder Type</label>
                    <select id="folderType" name="folder_type">
                        <option value="category">Category</option>
                        <option value="custom">Custom</option>
                        <option value="system">System</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" placeholder="e.g., Academic, Administrative">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Brief description of folder purpose..."></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="isPublic" name="is_public"> Make this folder public
                    </label>
                </div>

                <div class="form-group">
                    <label>Folder Color</label>
                    <div class="color-picker">
                        <div class="color-option selected" data-color="#006b2e" style="background: #006b2e;"></div>
                        <div class="color-option" data-color="#0a8f3c" style="background: #0a8f3c;"></div>
                        <div class="color-option" data-color="#16a34a" style="background: #16a34a;"></div>
                        <div class="color-option" data-color="#d4a72c" style="background: #d4a72c;"></div>
                        <div class="color-option" data-color="#ecc94b" style="background: #ecc94b;"></div>
                        <div class="color-option" data-color="#b8860b" style="background: #b8860b;"></div>
                        <div class="color-option" data-color="#7d8f83" style="background: #7d8f83;"></div>
                        <div class="color-option" data-color="#a0aec0" style="background: #a0aec0;"></div>
                    </div>
                    <input type="hidden" id="folderColor" name="folder_color" value="#006b2e">
                </div>

                <div class="form-group">
                    <label>Folder Icon</label>
                    <div class="icon-picker">
                        <div class="icon-option selected" data-icon="fa-folder"><i class="fa fa-folder"></i></div>
                        <div class="icon-option" data-icon="fa-folder-open"><i class="fa fa-folder-open"></i></div>
                        <div class="icon-option" data-icon="fa-archive"><i class="fa fa-archive"></i></div>
                        <div class="icon-option" data-icon="fa-briefcase"><i class="fa fa-briefcase"></i></div>
                        <div class="icon-option" data-icon="fa-book"><i class="fa fa-book"></i></div>
                        <div class="icon-option" data-icon="fa-graduation-cap"><i class="fa fa-graduation-cap"></i></div>
                        <div class="icon-option" data-icon="fa-cog"><i class="fa fa-cog"></i></div>
                        <div class="icon-option" data-icon="fa-users"><i class="fa fa-users"></i></div>
                        <div class="icon-option" data-icon="fa-chart-bar"><i class="fa fa-chart-bar"></i></div>
                        <div class="icon-option" data-icon="fa-file-alt"><i class="fa fa-file-alt"></i></div>
                        <div class="icon-option" data-icon="fa-image"><i class="fa fa-image"></i></div>
                        <div class="icon-option" data-icon="fa-video"><i class="fa fa-video"></i></div>
                    </div>
                    <input type="hidden" id="folderIcon" name="folder_icon" value="fa-folder">
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" onclick="closeModal()" class="btn-action btn-secondary">Cancel</button>
                    <button type="submit" class="btn-action btn-primary">
                        <span id="submitText">Create Folder</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Permissions Modal -->
    <div id="permissionsModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Manage Folder Permissions</h2>
                <button onclick="closePermissionsModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">
                    <i class='bx bx-x'></i>
                </button>
            </div>

            <form id="permissionsForm">
                <input type="hidden" id="permissionsFolderId" name="folder_id">
                <input type="hidden" name="action" value="set_permissions">

                <div class="permission-group">
                    <h4><i class='bx bx-show'></i> Read Access</h4>
                    <p style="font-size: 0.85rem; color: #718096; margin-bottom: 0.75rem;">Users who can view and download files</p>
                    <div class="user-select" id="readUsers">
                        <?php foreach ($users as $user): ?>
                            <label class="user-checkbox">
                                <input type="checkbox" name="read_users[]" value="<?= $user['id'] ?>">
                                <span style="margin-left: 0.5rem;">
                                    <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                                    <?php if ($user['position']): ?>
                                        <br><small style="color: #718096;"><?= htmlspecialchars($user['position']) ?></small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="permission-group">
                    <h4><i class='bx bx-edit'></i> Write Access</h4>
                    <p style="font-size: 0.85rem; color: #718096; margin-bottom: 0.75rem;">Users who can upload and modify files</p>
                    <div class="user-select" id="writeUsers">
                        <?php foreach ($users as $user): ?>
                            <label class="user-checkbox">
                                <input type="checkbox" name="write_users[]" value="<?= $user['id'] ?>">
                                <span style="margin-left: 0.5rem;">
                                    <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                                    <?php if ($user['position']): ?>
                                        <br><small style="color: #718096;"><?= htmlspecialchars($user['position']) ?></small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="permission-group">
                    <h4><i class='bx bx-shield'></i> Admin Access</h4>
                    <p style="font-size: 0.85rem; color: #718096; margin-bottom: 0.75rem;">Users who can manage folder settings and permissions</p>
                    <div class="user-select" id="adminUsers">
                        <?php foreach ($users as $user): ?>
                            <label class="user-checkbox">
                                <input type="checkbox" name="admin_users[]" value="<?= $user['id'] ?>">
                                <span style="margin-left: 0.5rem;">
                                    <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                                    <?php if ($user['position']): ?>
                                        <br><small style="color: #718096;"><?= htmlspecialchars($user['position']) ?></small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" onclick="closePermissionsModal()" class="btn-action btn-secondary">Cancel</button>
                    <button type="submit" class="btn-action btn-primary">Save Permissions</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Move Folder Modal -->
    <div id="moveModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Move Folder</h2>
                <button onclick="closeMoveModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">
                    <i class='bx bx-x'></i>
                </button>
            </div>

            <form id="moveForm">
                <input type="hidden" id="moveFolderId" name="folder_id">
                <input type="hidden" name="action" value="move_folder">

                <div class="form-group">
                    <label for="newParentFolder">New Parent Folder</label>
                    <select id="newParentFolder" name="new_parent_id">
                        <option value="">Root Level</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?= $folder['id'] ?>">
                                <?= str_repeat('— ', $folder['folder_level']) ?><?= htmlspecialchars($folder['folder_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" onclick="closeMoveModal()" class="btn-action btn-secondary">Cancel</button>
                    <button type="submit" class="btn-action btn-primary">Move Folder</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Folder Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Import Folder Structure</h2>
                <button onclick="closeImportModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">
                    <i class='bx bx-x'></i>
                </button>
            </div>

            <form id="importForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_folder">

                <div class="form-group">
                    <label for="importFile">Import File (JSON) *</label>
                    <input type="file" id="importFile" name="import_file" accept=".json" required>
                    <small style="color: #718096;">Select a JSON file exported from the folder structure export feature.</small>
                </div>

                <div class="form-group">
                    <label for="importTargetParent">Import Under Folder</label>
                    <select id="importTargetParent" name="target_parent_id">
                        <option value="">Root Level</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?= $folder['id'] ?>">
                                <?= str_repeat('— ', $folder['folder_level']) ?><?= htmlspecialchars($folder['folder_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="importPreserveIds" name="preserve_ids" value="1">
                        Preserve original folder IDs (use with caution)
                    </label>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" onclick="closeImportModal()" class="btn-action btn-secondary">Cancel</button>
                    <button type="submit" class="btn-action btn-primary">
                        <i class='bx bx-import'></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script>
        let currentView = 'grid';
        let foldersData = <?= json_encode($folders) ?>;

        // Set view mode
        function setView(viewType) {
            // Update button states
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-view="${viewType}"]`).classList.add('active');

            // Hide all views
            document.querySelectorAll('.grid-view, .table-view, .tree-view, .trash-view').forEach(view => {
                view.classList.remove('active');
            });

            // Show selected view
            document.getElementById(viewType + 'View').classList.add('active');
            currentView = viewType;
        }

        // Filter folders
        function filterFolders() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const departmentFilter = document.getElementById('departmentFilter').value;
            const typeFilter = document.getElementById('typeFilter').value;

            const folderCards = document.querySelectorAll('.folder-card');
            const tableRows = document.querySelectorAll('#foldersTableBody tr');

            // Filter grid and table views
            [...folderCards, ...tableRows].forEach(element => {
                const name = element.getAttribute('data-name') || '';
                const department = element.getAttribute('data-department') || '';
                const type = element.getAttribute('data-type') || '';

                const matchesSearch = name.includes(searchTerm);
                const matchesDepartment = !departmentFilter || department === departmentFilter;
                const matchesType = !typeFilter || type === typeFilter;

                element.style.display = (matchesSearch && matchesDepartment && matchesType) ? '' : 'none';
            });
        }

        // Show create modal
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Folder';
            document.getElementById('submitText').textContent = 'Create Folder';
            document.getElementById('actionType').value = 'create_folder';
            document.getElementById('folderForm').reset();
            document.getElementById('folderId').value = '';
            
            // Reset color and icon selection
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            document.querySelector('.color-option[data-color="#006b2e"]').classList.add('selected');
            document.getElementById('folderColor').value = '#006b2e';
            
            document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
            document.querySelector('.icon-option[data-icon="fa-folder"]').classList.add('selected');
            document.getElementById('folderIcon').value = 'fa-folder';
            
            document.getElementById('folderModal').classList.add('show');
        }

        // Show import modal
        function showImportModal() {
            document.getElementById('importModal').classList.add('show');
        }

        // Edit folder
        function editFolder(folderId) {
            const folder = foldersData.find(f => f.id == folderId);
            if (!folder) return;

            document.getElementById('modalTitle').textContent = 'Edit Folder';
            document.getElementById('submitText').textContent = 'Update Folder';
            document.getElementById('actionType').value = 'update_folder';
            document.getElementById('folderId').value = folderId;
            document.getElementById('folderName').value = folder.folder_name;
            document.getElementById('departmentSelect').value = folder.department_id || '';
            document.getElementById('folderType').value = folder.folder_type;
            document.getElementById('category').value = folder.category || '';
            document.getElementById('description').value = folder.description || '';
            document.getElementById('isPublic').checked = folder.is_public == 1;

            // Set color selection
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            document.querySelector(`.color-option[data-color="${folder.folder_color}"]`)?.classList.add('selected');
            document.getElementById('folderColor').value = folder.folder_color;

            // Set icon selection
            document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
            document.querySelector(`.icon-option[data-icon="${folder.folder_icon}"]`)?.classList.add('selected');
            document.getElementById('folderIcon').value = folder.folder_icon;

            document.getElementById('folderModal').classList.add('show');
        }

        // Delete folder
        function deleteFolder(folderId) {
            if (!confirm('Are you sure you want to delete this folder? Files inside will not be deleted but will become unorganized.')) {
                return;
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_folder&folder_id=${folderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred', 'error');
                console.error('Error:', error);
            });
        }

        // Restore folder
        function restoreFolder(folderId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=restore_folder&folder_id=${folderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            });
        }

        // Permanent delete folder
        function permanentDeleteFolder(folderId) {
            if (!confirm('Are you sure you want to permanently delete this folder? This action cannot be undone!')) {
                return;
            }

            // This would require additional backend logic for permanent deletion
            showNotification('Permanent deletion not implemented yet', 'warning');
        }

        // Manage folder permissions
        function manageFolderPermissions(folderId) {
            const folder = foldersData.find(f => f.id == folderId);
            if (!folder) return;

            document.getElementById('permissionsFolderId').value = folderId;
            
            // Reset all checkboxes
            document.querySelectorAll('#permissionsForm input[type="checkbox"]').forEach(cb => cb.checked = false);

            // Set current permissions if they exist
            if (folder.permissions) {
                try {
                    const permissions = JSON.parse(folder.permissions);
                    
                    if (permissions.read) {
                        permissions.read.forEach(userId => {
                            const checkbox = document.querySelector(`input[name="read_users[]"][value="${userId}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                    
                    if (permissions.write) {
                        permissions.write.forEach(userId => {
                            const checkbox = document.querySelector(`input[name="write_users[]"][value="${userId}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                    
                    if (permissions.admin) {
                        permissions.admin.forEach(userId => {
                            const checkbox = document.querySelector(`input[name="admin_users[]"][value="${userId}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                } catch (e) {
                    console.error('Error parsing permissions:', e);
                }
            }

            document.getElementById('permissionsModal').classList.add('show');
        }

        // View folder contents
        function viewFolderContents(folderId) {
            window.location.href = `files.php?folder_id=${folderId}`;
        }

        // Color picker functionality
        document.querySelectorAll('.color-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('folderColor').value = this.getAttribute('data-color');
            });
        });

        // Icon picker functionality
        document.querySelectorAll('.icon-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('folderIcon').value = this.getAttribute('data-icon');
            });
        });

        // Form submissions
        document.getElementById('folderForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            document.getElementById('loading').style.display = 'block';

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                showNotification('An error occurred', 'error');
                console.error('Error:', error);
            });
        });

        document.getElementById('permissionsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closePermissionsModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred', 'error');
                console.error('Error:', error);
            });
        });

        // Import form submission
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            document.getElementById('loading').style.display = 'block';

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeImportModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                showNotification('An error occurred', 'error');
                console.error('Error:', error);
            });
        });

        // Modal functions
        function closeModal() {
            document.getElementById('folderModal').classList.remove('show');
        }

        function closePermissionsModal() {
            document.getElementById('permissionsModal').classList.remove('show');
        }

        function closeMoveModal() {
            document.getElementById('moveModal').classList.remove('show');
        }

        function closeImportModal() {
            document.getElementById('importModal').classList.remove('show');
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                background: ${type === 'success' ? '#006b2e' : type === 'error' ? '#dc2626' : '#006b2e'};
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1001;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 1800);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                showCreateModal();
            }
            if (e.key === 'Escape') {
                closeModal();
                closePermissionsModal();
                closeMoveModal();
                closeImportModal();
            }
        });

        // Initialize tooltips and other UI enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh folder data every 30 seconds
            setInterval(() => {
                // You could implement real-time updates here
                console.log('Auto-refresh triggered');
            }, 30000);
        });

        // Drag and drop functionality for moving folders
        let draggedFolder = null;

        document.querySelectorAll('.folder-card').forEach(card => {
            card.draggable = true;
            
            card.addEventListener('dragstart', function(e) {
                draggedFolder = this.querySelector('.folder-actions button').onclick.toString().match(/\d+/)[0];
                this.style.opacity = '0.5';
                e.dataTransfer.effectAllowed = 'move';
            });

            card.addEventListener('dragend', function(e) {
                this.style.opacity = '1';
                draggedFolder = null;
            });

            card.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.style.borderColor = '#006b2e';
            });

            card.addEventListener('dragleave', function(e) {
                this.style.borderColor = '#e1e5e9';
            });

            card.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#e1e5e9';
                
                if (draggedFolder && draggedFolder !== this.querySelector('.folder-actions button').onclick.toString().match(/\d+/)[0]) {
                    const targetFolderId = this.querySelector('.folder-actions button').onclick.toString().match(/\d+/)[0];
                    moveFolder(draggedFolder, targetFolderId);
                }
            });
        });

        function moveFolder(folderId, newParentId) {
            if (confirm('Move this folder to the selected parent folder?')) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=move_folder&folder_id=${folderId}&new_parent_id=${newParentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(data.message, 'error');
                    }
                });
            }
        }

        // Bulk operations
        let selectedFolders = new Set();

        function toggleFolderSelection(folderId) {
            if (selectedFolders.has(folderId)) {
                selectedFolders.delete(folderId);
            } else {
                selectedFolders.add(folderId);
            }
            updateBulkActionsBar();
        }

        function selectAllFolders() {
            const visibleFolders = document.querySelectorAll('.folder-card:not([style*="display: none"])');
            visibleFolders.forEach(card => {
                const folderId = card.querySelector('.folder-actions button').onclick.toString().match(/\d+/)[0];
                selectedFolders.add(folderId);
                card.classList.add('selected');
            });
            updateBulkActionsBar();
        }

        function clearSelection() {
            selectedFolders.clear();
            document.querySelectorAll('.folder-card').forEach(card => card.classList.remove('selected'));
            updateBulkActionsBar();
        }

        function updateBulkActionsBar() {
            const bulkBar = document.getElementById('bulkActionsBar');
            if (selectedFolders.size > 0) {
                if (!bulkBar) {
                    createBulkActionsBar();
                }
                document.getElementById('selectedCount').textContent = selectedFolders.size;
                bulkBar.style.display = 'flex';
            } else if (bulkBar) {
                bulkBar.style.display = 'none';
            }
        }

        function createBulkActionsBar() {
            const bulkBar = document.createElement('div');
            bulkBar.id = 'bulkActionsBar';
            bulkBar.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: white;
                padding: 1rem 2rem;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                display: none;
                align-items: center;
                gap: 1rem;
                z-index: 999;
                border: 1px solid #e2e8f0;
            `;
            
            bulkBar.innerHTML = `
                <span><strong id="selectedCount">0</strong> folders selected</span>
                <button class="btn-action btn-secondary" onclick="clearSelection()">
                    <i class='bx bx-x'></i> Clear
                </button>
                <button class="btn-action btn-danger" onclick="bulkDeleteFolders()">
                    <i class='bx bx-trash'></i> Delete Selected
                </button>
                <button class="btn-action btn-primary" onclick="bulkMoveFolders()">
                    <i class='bx bx-move'></i> Move Selected
                </button>
            `;
            
            document.body.appendChild(bulkBar);
        }

        function bulkDeleteFolders() {
            if (selectedFolders.size === 0) return;
            
            if (!confirm(`Delete ${selectedFolders.size} selected folders?`)) return;

            Promise.all([...selectedFolders].map(folderId => 
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_folder&folder_id=${folderId}`
                }).then(r => r.json())
            )).then(results => {
                const successCount = results.filter(r => r.success).length;
                showNotification(`${successCount} folders deleted successfully`, 'success');
                clearSelection();
                setTimeout(() => location.reload(), 1000);
            });
        }

        // Export functionality
        function exportFolderStructure() {
            const data = foldersData.map(folder => ({
                name: folder.folder_name,
                type: folder.folder_type,
                department: folder.department_name,
                files: folder.file_count,
                size: folder.total_size,
                created: folder.created_at,
                public: folder.is_public ? 'Yes' : 'No'
            }));

            const csv = [
                ['Folder Name', 'Type', 'Department', 'Files', 'Size (bytes)', 'Created', 'Public'],
                ...data.map(row => Object.values(row))
            ].map(row => row.join(',')).join('\n');

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `folder_structure_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Add export button to controls
        document.querySelector('.controls-bar').insertAdjacentHTML('beforeend', `
            <button class="btn-action btn-secondary" onclick="exportFolderStructure()">
                <i class='bx bx-download'></i> Export
            </button>
        `);

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case '1': setView('grid'); break;
                    case '2': setView('table'); break;
                    case '3': setView('tree'); break;
                    case '4': setView('trash'); break;
                    case 'a': 
                        e.preventDefault();
                        selectAllFolders();
                        break;
                    case 'e':
                        e.preventDefault();
                        exportFolderStructure();
                        break;
                }
            }
        });

        // Real-time search
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterFolders, 300);
        });

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            .folder-card.selected {
                border-color: #006b2e;
                box-shadow: 0 0 0 2px rgba(0, 107, 46, 0.25);
            }
            
            .folder-card {
                cursor: pointer;
            }
        `;
        document.head.appendChild(style);

        // Add selection functionality to folder cards
        document.querySelectorAll('.folder-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    const folderId = this.querySelector('.folder-actions button').onclick.toString().match(/\d+/)[0];
                    toggleFolderSelection(folderId);
                    this.classList.toggle('selected');
                }
            });
        });

        // Add context menu for right-click actions
        document.addEventListener('contextmenu', function(e) {
            if (e.target.closest('.folder-card')) {
                e.preventDefault();
                const card = e.target.closest('.folder-card');
                const folderId = card.querySelector('.folder-actions button').onclick.toString().match(/\d+/)[0];
                showContextMenu(e.pageX, e.pageY, folderId);
            }
        });

        function showContextMenu(x, y, folderId) {
            // Remove existing context menu
            const existingMenu = document.getElementById('contextMenu');
            if (existingMenu) existingMenu.remove();

            const menu = document.createElement('div');
            menu.id = 'contextMenu';
            menu.style.cssText = `
                position: fixed;
                top: ${y}px;
                left: ${x}px;
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 0.5rem 0;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1002;
                min-width: 150px;
            `;

            menu.innerHTML = `
                <div class="context-item" onclick="viewFolderContents(${folderId}); removeContextMenu();">
                    <i class='bx bx-folder-open'></i> Open
                </div>
                <div class="context-item" onclick="editFolder(${folderId}); removeContextMenu();">
                    <i class='bx bx-edit'></i> Edit
                </div>
                <div class="context-item" onclick="manageFolderPermissions(${folderId}); removeContextMenu();">
                    <i class='bx bx-lock'></i> Permissions
                </div>
                <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid #e2e8f0;">
                <div class="context-item" onclick="deleteFolder(${folderId}); removeContextMenu();" style="color: #f56565;">
                    <i class='bx bx-trash'></i> Delete
                </div>
            `;

            // Add context item styles
            const contextStyle = document.createElement('style');
            contextStyle.textContent = `
                .context-item {
                    padding: 0.75rem 1rem;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    transition: background 0.2s;
                }
                .context-item:hover {
                    background: #f7fafc;
                }
            `;
            document.head.appendChild(contextStyle);

            document.body.appendChild(menu);

            // Remove menu when clicking elsewhere
            setTimeout(() => {
                document.addEventListener('click', removeContextMenu, { once: true });
            }, 100);
        }

        function removeContextMenu() {
            const menu = document.getElementById('contextMenu');
            if (menu) menu.remove();
        }

        // Add help tooltip
        function showKeyboardShortcuts() {
            showNotification('Ctrl+N: New Folder | Ctrl+1-4: Switch Views | Ctrl+A: Select All | Ctrl+E: Export', 'info');
        }

        // Add help button
        document.querySelector('.head-title .left').insertAdjacentHTML('afterend', `
            <button class="btn-action btn-secondary" onclick="showKeyboardShortcuts()" title="Keyboard Shortcuts">
                <i class='bx bx-help-circle'></i>
            </button>
        `);
    </script>
</body>
</html>

<?php
// Helper function for file size formatting
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $exp = floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $exp), 2) . ' ' . $units[$exp];
}
?>
