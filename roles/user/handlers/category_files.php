<?php
// ODCI/roles/user/handlers/category_files.php
require_once '../../../includes/config.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get current user information
$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Get user's department ID
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

// Ensure user has a department
if (!$userDepartmentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No department assigned']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['department_id']) || !isset($input['category'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Department ID and category required']);
    exit();
}

$requestedDepartmentId = intval($input['department_id']);
$category = $input['category'];
$requestedUserDepartmentId = intval($input['user_department_id'] ?? 0);

// SECURITY CHECK: User can only access files from their own department
if ($requestedDepartmentId != $userDepartmentId || $requestedUserDepartmentId != $userDepartmentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: Can only view files from your department']);
    exit();
}

try {
    $deletedCategoryStmt = $pdo->prepare("
        SELECT id
        FROM folders
        WHERE department_id = ? AND category = ?
          AND folder_name = CONCAT('__deleted_category__:', category)
          AND parent_id IS NULL
        LIMIT 1
    ");
    $deletedCategoryStmt->execute([$requestedDepartmentId, $category]);
    if ($deletedCategoryStmt->fetchColumn()) {
        http_response_code(410);
        echo json_encode(['success' => false, 'message' => 'This department folder has been deleted.']);
        exit();
    }

    // Get files for first semester - only current user's files
    $firstSemesterFiles = getFilesByCategorySemester($pdo, $requestedDepartmentId, $category, 'first', $currentUser['id']);
    
    // Get files for second semester - only current user's files
    $secondSemesterFiles = getFilesByCategorySemester($pdo, $requestedDepartmentId, $category, 'second', $currentUser['id']);
    
    // Calculate total files count - THIS WAS MISSING
    $totalFiles = count($firstSemesterFiles) + count($secondSemesterFiles);
    
    echo json_encode([
        'success' => true,
        'first_semester' => $firstSemesterFiles,
        'second_semester' => $secondSemesterFiles,
        'total_files' => $totalFiles,  // ADD THIS LINE
        'created_at' => [
            'first' => getCategorySemesterCreatedAt($pdo, $requestedDepartmentId, $category, 'first'),
            'second' => getCategorySemesterCreatedAt($pdo, $requestedDepartmentId, $category, 'second')
        ]
    ]);

} catch(Throwable $e) {
    error_log("Error fetching category files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

function getCategorySemesterCreatedAt($pdo, $departmentId, $category, $semester) {
    try {
        $semesterPattern = '%' . ($semester === 'first' ? 'First Semester' : 'Second Semester') . '%';
        $stmt = $pdo->prepare("
            SELECT MIN(fo.created_at) AS created_at
            FROM folders fo
            WHERE fo.department_id = ?
              AND fo.category = ?
              AND fo.folder_name LIKE ?
              AND fo.is_deleted = 0
        ");
        $stmt->execute([$departmentId, $category, $semesterPattern]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row['created_at']) ? $row['created_at'] : null;
    } catch (Throwable $e) {
        error_log("Error reading category created_at for {$category}: " . $e->getMessage());
        return null;
    }
}

function getFilesByCategorySemester($pdo, $departmentId, $category, $semester, $userId = null) {
    try {
        // Get all academic years, not just current year
        $sql = "
            SELECT 
                f.id,
                f.file_name,
                f.original_name,
                f.file_path,
                f.file_size,
                f.file_type,
                f.uploaded_at,
                f.description,
                f.tags,
                f.download_count,
                COALESCE(CONCAT(u.name, ' ', COALESCE(u.surname, '')), u.username, 'Unknown User') as uploaded_by,
                LOWER(SUBSTRING_INDEX(f.file_name, '.', -1)) as file_extension,
                f.file_size as raw_file_size
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            LEFT JOIN users u ON f.uploaded_by = u.id
            WHERE fo.department_id = ? 
            AND fo.category = ?
            AND fo.folder_name LIKE ?
            AND f.is_deleted = 0 
            AND fo.is_deleted = 0
        ";
        
        $semesterPattern = '%' . ($semester === 'first' ? 'First Semester' : 'Second Semester') . '%';
        $params = [$departmentId, $category, $semesterPattern];
        
        // Filter by user ID if provided (for privacy - users only see their own files)
        if ($userId !== null) {
            $sql .= " AND f.uploaded_by = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY f.uploaded_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process files to ensure proper data format
        foreach ($files as $index => $file) {
            // Parse tags if they exist
            if (!empty($file['tags'])) {
                $decoded = json_decode($file['tags'], true);
                $file['tags'] = is_array($decoded) ? $decoded : [];
            } else {
                $file['tags'] = [];
            }
            
            // Ensure uploader name is not empty
            if (empty($file['uploaded_by'])) {
                $file['uploaded_by'] = 'Unknown User';
            }
            
            // Ensure download count is set
            $file['download_count'] = intval($file['download_count'] ?? 0);
            
            // Add formatted file size
            $file['formatted_size'] = formatFileSize($file['raw_file_size']);
            
            // Add time ago
            $file['time_ago'] = timeAgo($file['uploaded_at']);
            
            // Add file icon based on extension
            $file['file_icon'] = getFileIcon($file['file_extension']);
            
            $files[$index] = $file;
        }
        
        return $files;
        
    } catch (Throwable $e) {
        error_log("Error getting files for category {$category}, semester {$semester}: " . $e->getMessage());
        return [];
    }
}

function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    if ($time < 31536000) return floor($time/2592000) . 'mo ago';
    return floor($time/31536000) . 'y ago';
}

function getFileIcon($extension) {
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
    
    return isset($iconMap[$extension]) ? $iconMap[$extension] : 'bxs-file';
}
?>