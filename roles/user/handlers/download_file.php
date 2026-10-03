<?php
// ODCI/roles/user/handlers/download_file.php
require_once '../../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    header('Location: ../../logout.php');
    exit();
}

// Get user's department ID for security check
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
    // Get file ID and department ID from URL parameters
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        die('Invalid file ID');
    }
    
    $fileId = (int)$_GET['id'];
    $requestedDeptId = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
    
    // Security check: Ensure user is downloading from their department
    if ($requestedDeptId && $requestedDeptId != $userDepartmentId) {
        http_response_code(403);
        die('Access denied: Can only download files from your department');
    }
    
    // Get file information from database
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            fo.department_id,
            fo.folder_name,
            fo.category,
            d.department_name,
            d.department_code,
            COALESCE(CONCAT(u.name, ' ', COALESCE(u.surname, '')), u.username, 'Unknown User') as uploader_name
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        INNER JOIN departments d ON fo.department_id = d.id
        LEFT JOIN users u ON f.uploaded_by = u.id
        WHERE f.id = ? AND f.is_deleted = 0 AND fo.is_deleted = 0
    ");
    
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        die('File not found or deleted');
    }
    
    // Final security check: Ensure file belongs to user's department
    if ($file['department_id'] != $userDepartmentId) {
        http_response_code(403);
        die('Access denied: File belongs to different department');
    }
    
    // Construct full file path
    $filePath = '../../' . $file['file_path'];
    
    // Check if physical file exists
    if (!file_exists($filePath)) {
        error_log("Physical file not found: " . $filePath);
        http_response_code(404);
        die('Physical file not found on server');
    }
    
    // Update download statistics
    try {
        $updateStmt = $pdo->prepare("
            UPDATE files 
            SET 
                download_count = COALESCE(download_count, 0) + 1,
                last_downloaded = NOW(),
                last_downloaded_by = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$currentUser['id'], $fileId]);
        
        // Log download activity
        $logStmt = $pdo->prepare("
            INSERT INTO file_downloads (file_id, user_id, downloaded_at, user_ip) 
            VALUES (?, ?, NOW(), ?)
        ");
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $logStmt->execute([$fileId, $currentUser['id'], $userIP]);
        
    } catch (Exception $e) {
        // Don't fail the download if logging fails
        error_log("Download logging error: " . $e->getMessage());
    }
    
    // Set headers for file download
    $fileSize = filesize($filePath);
    $fileName = $file['original_name'] ?: $file['file_name'];
    
    // Clean the filename for safe downloading
    $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    
    // Clean the output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set appropriate headers
    header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    header('Content-Length: ' . $fileSize);
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    // Add security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent any output before file
    flush();
    
    // Read and output the file
    if ($fileSize > 8192) {
        // For larger files, read in chunks to avoid memory issues
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            die('Cannot open file for reading');
        }
        
        while (!feof($handle) && connection_status() == 0) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    } else {
        // For smaller files, read all at once
        readfile($filePath);
    }
    
    exit();
    
} catch (Exception $e) {
    error_log("Download error for file ID {$fileId}: " . $e->getMessage());
    http_response_code(500);
    die('An error occurred during download. Please try again.');
}
?>