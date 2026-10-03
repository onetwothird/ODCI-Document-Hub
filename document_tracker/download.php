<?php
// download.php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Require authentication
$current_user = requireAuth();
if (!$current_user) {
    header('Location: login.php?error=unauthorized');
    exit();
}

if (!isset($_GET['file_id']) || !is_numeric($_GET['file_id'])) {
    header('HTTP/1.0 400 Bad Request');
    die('Invalid file ID');
}

$file_id = intval($_GET['file_id']);

try {
    // Get file information
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as uploader_name, u.surname as uploader_surname,
               fo.folder_name, fo.created_by as folder_owner
        FROM files f 
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN folders fo ON f.folder_id = fo.id
        WHERE f.id = ? AND f.is_deleted = 0
    ");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        header('HTTP/1.0 404 Not Found');
        die('File not found');
    }

    // Check access permissions
    $can_access = false;
    
    // Super admin can access all files
    if ($current_user['role'] === 'super_admin') {
        $can_access = true;
    }
    // File owner can access
    elseif ($file['uploaded_by'] == $current_user['id']) {
        $can_access = true;
    }
    // Admin can access files in their department
    elseif ($current_user['role'] === 'admin') {
        // Check if uploader is in same department
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$file['uploaded_by']]);
        $uploader = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($uploader && $uploader['department_id'] == $current_user['department_id']) {
            $can_access = true;
        }
    }
    // Check folder permissions
    else {
        $can_access = canAccess('file', $file_id, 'read');
    }

    if (!$can_access) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied');
    }

    // Construct file path
    $file_path = DOCUMENT_UPLOADS_DIR . $file['file_name'];
    
    if (!file_exists($file_path)) {
        header('HTTP/1.0 404 Not Found');
        die('File not found on server');
    }

    // Log download activity
    logActivity(
        $pdo, 
        $current_user['id'], 
        'file_downloaded', 
        'file', 
        $file_id, 
        "Downloaded file: {$file['original_filename']}"
    );

    // Set headers for download
    $file_size = filesize($file_path);
    $file_extension = pathinfo($file['original_filename'], PATHINFO_EXTENSION);
    
    // Determine MIME type
    $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    
    $mime_type = $mime_types[strtolower($file_extension)] ?? 'application/octet-stream';

    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Set download headers
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');

    // Output file
    if ($file_size > 10 * 1024 * 1024) { // Files larger than 10MB
        // Stream large files in chunks
        $handle = fopen($file_path, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                if (connection_aborted()) {
                    break;
                }
                flush();
            }
            fclose($handle);
        }
    } else {
        // Small files can be read at once
        readfile($file_path);
    }

    exit();

} catch (Exception $e) {
    error_log("File download error: " . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    die('Download failed');
}
?>