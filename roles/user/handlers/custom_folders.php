<?php
// get_custom_folders.php - Handler for fetching custom folders
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
    echo json_encode(['success' => false, 'message' => 'No department assigned']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['department_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing department ID']);
        exit();
    }

    $departmentId = (int)$input['department_id'];
    $requestedUserDepartmentId = (int)($input['user_department_id'] ?? 0);

    // SECURITY CHECK: User can only view folders from their own department
    if ($departmentId != $userDepartmentId || $requestedUserDepartmentId != $userDepartmentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: Can only view folders from your department']);
        exit();
    }
    
    // Validate department exists and user has access
    $stmt = $pdo->prepare("
        SELECT d.id, d.department_name 
        FROM departments d 
        WHERE d.id = ? AND d.is_active = 1
    ");
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Department not found or inactive']);
        exit();
    }

    // Fetch custom folders for the department
    // Custom folders are those without a category (category IS NULL)
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.folder_name,
            f.description,
            f.folder_color,
            f.folder_icon,
            f.created_at,
            f.updated_at,
            (
                SELECT COUNT(*)
                FROM files files_in_folder
                INNER JOIN folders file_folder ON file_folder.id = files_in_folder.folder_id
                WHERE (file_folder.id = f.id OR file_folder.parent_id = f.id)
                  AND file_folder.is_deleted = 0
                  AND files_in_folder.is_deleted = 0
            ) AS file_count,
            (
                SELECT COALESCE(SUM(files_in_folder.file_size), 0)
                FROM files files_in_folder
                INNER JOIN folders file_folder ON file_folder.id = files_in_folder.folder_id
                WHERE (file_folder.id = f.id OR file_folder.parent_id = f.id)
                  AND file_folder.is_deleted = 0
                  AND files_in_folder.is_deleted = 0
            ) AS folder_size,
            (
                SELECT COUNT(*)
                FROM folders child
                WHERE child.parent_id = f.id
                  AND child.is_deleted = 0
                  AND child.category IS NULL
                  AND child.folder_name NOT IN ('First Semester', 'Second Semester')
            ) AS subfolder_count,
            (
                SELECT COUNT(*)
                FROM folders semester_folder
                INNER JOIN files semester_files ON semester_files.folder_id = semester_folder.id
                WHERE semester_folder.parent_id = f.id
                  AND semester_folder.folder_name = 'First Semester'
                  AND semester_folder.is_deleted = 0
                  AND semester_files.is_deleted = 0
            ) AS first_semester_count,
            (
                SELECT COUNT(*)
                FROM folders semester_folder
                INNER JOIN files semester_files ON semester_files.folder_id = semester_folder.id
                WHERE semester_folder.parent_id = f.id
                  AND semester_folder.folder_name = 'Second Semester'
                  AND semester_folder.is_deleted = 0
                  AND semester_files.is_deleted = 0
            ) AS second_semester_count,
            f.created_by,
            f.is_public,
            u.name AS creator_name,
            u.surname AS creator_surname
        FROM folders f
        LEFT JOIN users u ON f.created_by = u.id
        WHERE f.department_id = ? 
        AND f.is_deleted = 0 
        AND f.category IS NULL
        AND f.parent_id IS NULL
        AND (f.is_public = 1 OR f.created_by = ?)
        ORDER BY f.created_at DESC
    ");
    
    $stmt->execute([$departmentId, $currentUser['id']]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process folders data
    $processedFolders = [];
    foreach ($folders as $folder) {
        $creatorName = trim(($folder['creator_name'] ?? '') . ' ' . ($folder['creator_surname'] ?? ''));
        if (empty($creatorName)) {
            $creatorName = 'Unknown User';
        }
        
        $processedFolders[] = [
            'id' => $folder['id'],
            'folder_name' => $folder['folder_name'],
            'description' => $folder['description'],
            'folder_color' => $folder['folder_color'] ?: '#10b981',
            'folder_icon' => $folder['folder_icon'] ?: 'bxs-folder',
            'created_at' => $folder['created_at'],
            'updated_at' => $folder['updated_at'],
            'file_count' => (int)$folder['file_count'],
            'folder_size' => (int)$folder['folder_size'],
            'formatted_size' => formatFolderSize((int)$folder['folder_size']),
            'is_public' => (bool)$folder['is_public'],
            'permission_level' => !empty($folder['is_public']) ? 'Department' : 'Private',
            'subfolder_count' => (int)$folder['subfolder_count'],
            'first_semester_count' => (int)$folder['first_semester_count'],
            'second_semester_count' => (int)$folder['second_semester_count'],
            'can_delete' => true,
            'created_by' => $creatorName,
            'created_by_id' => $folder['created_by']
        ];
    }

    echo json_encode([
        'success' => true,
        'folders' => $processedFolders,
        'total_folders' => count($processedFolders),
        'department' => [
            'id' => $department['id'],
            'name' => $department['department_name']
        ]
    ]);

} catch (Exception $e) {
    error_log("Get custom folders error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching folders: ' . $e->getMessage()
    ]);
}

function formatFolderSize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $size = (float)$bytes / 1024;
    foreach ($units as $unit) {
        if ($size < 1024 || $unit === 'TB') {
            return round($size, 1) . ' ' . $unit;
        }
        $size /= 1024;
    }

    return round($size, 1) . ' TB';
}
?>