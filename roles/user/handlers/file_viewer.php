<?php
// file_viewer.php - Secure file viewer for preview modal
require_once '../../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    die('Unauthorized');
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || !$currentUser['is_approved']) {
    http_response_code(403);
    die('User not approved');
}

// Get user's department ID for security
$userDepartmentId = null;
if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    $userDepartmentId = $currentUser['department_id'];
} elseif (isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $user = $stmt->fetch();
        if ($user && $user['department_id']) {
            $userDepartmentId = $user['department_id'];
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

if (!$userDepartmentId) {
    http_response_code(403);
    die('No department assigned');
}

try {
    // Get file ID from URL parameters
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        die('Invalid file ID');
    }
    
    $fileId = (int)$_GET['id'];
    
    // Get file information from database with security check
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            fo.department_id,
            d.department_name,
            d.department_code
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        INNER JOIN departments d ON fo.department_id = d.id
        WHERE f.id = ? AND f.is_deleted = 0 AND fo.is_deleted = 0
    ");
    
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        die('File not found');
    }
    
    // Security check: Ensure file belongs to user's department
    if ($file['department_id'] != $userDepartmentId) {
        http_response_code(403);
        die('Access denied: File not in your department');
    }
    
    // Construct full file path
    $filePath = '../../../' . ltrim($file['file_path'], '/');
    
    // Check if physical file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('Physical file not found');
    }
    
    // Get file info
    $fileSize = filesize($filePath);
    $mimeType = $file['mime_type'] ?: 'application/octet-stream';
    $fileName = $file['original_name'] ?: $file['file_name'];
    
    // Handle different file types for viewing
    $extension = strtolower($file['file_extension'] ?: '');
    
    // Set appropriate headers for inline viewing
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    
    // For images and PDFs, display inline. For text files, also inline.
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'txt', 'csv', 'json', 'xml', 'html', 'css', 'js', 'md'])) {
        header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
    } else {
        // For other files, force download
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    }
    
    // Output file content
    readfile($filePath);
    
    // Log file access (optional)
    try {
        $stmt = $pdo->prepare("
            UPDATE files 
            SET last_accessed = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$fileId]);
    } catch (Exception $e) {
        // Don't fail if logging fails
        error_log("File access logging error: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("File viewer error for file ID {$fileId}: " . $e->getMessage());
    http_response_code(500);
    die('Error viewing file');
}
?>