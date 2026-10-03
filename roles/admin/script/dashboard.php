<?php
    require_once '../../includes/config.php';

    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    $currentUser = getCurrentUser($pdo);

    if (!$currentUser) {
        header('Location: logout.php');
        exit();
    }

    if (!$currentUser['is_approved']) {
        session_unset();
        session_destroy();
        header('Location: login.php?error=account_not_approved');
        exit();
    }

    $stats = [
        'total_files' => 0,
        'total_folders' => 0,
        'storage_used' => 0,
        'recent_uploads' => []
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as file_count, COALESCE(SUM(file_size), 0) as total_size
            FROM files f 
            JOIN folders fo ON f.folder_id = fo.id 
            WHERE f.uploaded_by = ? AND f.is_deleted = 0
        ");
        $stmt->execute([$currentUser['id']]);
        $fileStats = $stmt->fetch();
        
        $stats['total_files'] = $fileStats['file_count'];
        $stats['storage_used'] = $fileStats['total_size'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as folder_count FROM folders WHERE created_by = ? AND is_deleted = 0");
        $stmt->execute([$currentUser['id']]);
        $folderStats = $stmt->fetch();
        
        $stats['total_folders'] = $folderStats['folder_count'];

        $stmt = $pdo->prepare("
            SELECT f.original_name, f.file_size, f.uploaded_at, fo.folder_name
            FROM files f
            JOIN folders fo ON f.folder_id = fo.id
            WHERE f.uploaded_by = ? AND f.is_deleted = 0
            ORDER BY f.uploaded_at DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id']]);
        $stats['recent_uploads'] = $stmt->fetchAll();
        
    } catch(Exception $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
    }

    function formatFileSize($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
?>