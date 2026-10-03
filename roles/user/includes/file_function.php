<?php
// File handling functions
// This file contains all file-related operations and utilities

// Get files for a specific department and semester
function getDepartmentFiles($pdo, $department, $semester = null) {
    try {
        $query = "
            SELECT f.*, fo.folder_name, u.full_name as uploader_name
            FROM files f
            JOIN folders fo ON f.folder_id = fo.id
            JOIN users u ON f.uploaded_by = u.id
            WHERE fo.department = ? AND f.is_deleted = 0
        ";
        
        $params = [$department];
        
        if ($semester) {
            $query .= " AND fo.semester = ?";
            $params[] = $semester;
        }
        
        $query .= " ORDER BY f.uploaded_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch(Exception $e) {
        error_log("Error fetching department folders: " . $e->getMessage());
        return [];
    }
}

// Format file size function
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get file icon based on extension
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $iconMap = [
        'pdf' => 'bxs-file-pdf',
        'doc' => 'bxs-file-doc',
        'docx' => 'bxs-file-doc',
        'xls' => 'bxs-spreadsheet',
        'xlsx' => 'bxs-spreadsheet',
        'ppt' => 'bxs-file-blank',
        'pptx' => 'bxs-file-blank',
        'jpg' => 'bxs-file-image',
        'jpeg' => 'bxs-file-image',
        'png' => 'bxs-file-image',
        'gif' => 'bxs-file-image',
        'txt' => 'bxs-file-txt',
        'zip' => 'bxs-file-archive',
        'rar' => 'bxs-file-archive',
        'mp4' => 'bxs-videos',
        'avi' => 'bxs-videos',
        'mp3' => 'bxs-music',
        'wav' => 'bxs-music'
    ];
    
    return isset($iconMap[$ext]) ? $iconMap[$ext] : 'bxs-file';
}
?>