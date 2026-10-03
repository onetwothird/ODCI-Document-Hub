<?php
// get_file_info.php - Get detailed file information for preview modal
require_once '../../../includes/config.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || !$currentUser['is_approved']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not approved']);
    exit();
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
    echo json_encode(['success' => false, 'message' => 'No department assigned']);
    exit();
}

try {
    // Get the request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['file_id'])) {
        echo json_encode(['success' => false, 'message' => 'File ID is required']);
        exit();
    }
    
    $fileId = (int)$input['file_id'];
    
    // Get file information with department and user details
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            COALESCE(CONCAT(u.name, ' ', COALESCE(u.surname, '')), u.username, 'Unknown User') as uploaded_by_name,
            d.department_name,
            d.department_code,
            fo.folder_name,
            fo.department_id as folder_department_id
        FROM files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN folders fo ON f.folder_id = fo.id
        LEFT JOIN departments d ON fo.department_id = d.id
        WHERE f.id = ? AND f.is_deleted = 0
    ");
    
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit();
    }
    
    // Security check: User can only access files from their department
    if (!$file['folder_department_id'] || $file['folder_department_id'] != $userDepartmentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: File not in your department']);
        exit();
    }
    
    // Parse tags if they exist
    $tags = [];
    if ($file['tags']) {
        $decodedTags = json_decode($file['tags'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTags)) {
            $tags = $decodedTags;
        }
    }
    
    // Format file data
    $fileData = [
        'id' => $file['id'],
        'file_name' => $file['file_name'],
        'original_name' => $file['original_name'],
        'file_path' => $file['file_path'],
        'file_size' => $file['file_size'],
        'formatted_size' => formatFileSize($file['file_size']),
        'file_type' => $file['file_type'],
        'mime_type' => $file['mime_type'],
        'file_extension' => $file['file_extension'],
        'uploaded_by' => $file['uploaded_by_name'] ?: 'Unknown User',
        'uploaded_at' => $file['uploaded_at'],
        'download_count' => $file['download_count'] ?: 0,
        'description' => $file['description'],
        'tags' => $tags,
        'academic_year' => $file['academic_year'],
        'semester' => $file['semester'],
        'category' => $file['category'] ?? 'uncategorized',
        'department_id' => $file['folder_department_id'],
        'department_name' => $file['department_name'],
        'department_code' => $file['department_code'],
        'folder_name' => $file['folder_name'],
        'time_ago' => timeAgo($file['uploaded_at']),
        'file_icon' => getFileIcon($file['original_name']),
        'can_edit' => ($file['uploaded_by'] == $currentUser['id']), // Only uploader can edit
        'can_delete' => ($file['uploaded_by'] == $currentUser['id']) // Only uploader can delete
    ];
    
    echo json_encode([
        'success' => true,
        'file' => $fileData
    ]);

} catch (Exception $e) {
    error_log("Get file info error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching file information'
    ]);
}

// Helper function to format file size
function formatFileSize($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Helper function to get time ago
function timeAgo($datetime) {
    if (!$datetime) return 'Unknown time';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time / 60) . ' min ago';
    if ($time < 86400) return floor($time / 3600) . ' hr ago';
    if ($time < 2592000) return floor($time / 86400) . ' days ago';
    if ($time < 31536000) return floor($time / 2592000) . ' months ago';
    return floor($time / 31536000) . ' years ago';
}

// Helper function to get file icon
function getFileIcon($filename) {
    if (!$filename) return 'bxs-file';
    
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