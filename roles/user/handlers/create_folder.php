<?php
// create_folder.php - Handler for creating custom folders
require_once '../../../includes/config.php';

// Set JSON response header
header('Content-Type: application/json');
$physicalPaths = [];

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
    // Validate input
    if (!isset($_POST['folder_name']) || !isset($_POST['department_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    $folderName = trim($_POST['folder_name']);
    $folderDescription = trim($_POST['folder_description'] ?? '');
    $folderColor = $_POST['folder_color'] ?? '#10b981';
    $folderIcon = $_POST['folder_icon'] ?? 'bxs-folder';
    $departmentId = (int)$_POST['department_id'];
    $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] === '1' ? 1 : 0;

    // Validate folder name
    if (empty($folderName)) {
        echo json_encode(['success' => false, 'message' => 'Folder name is required']);
        exit();
    }

    if (strlen($folderName) > 100) {
        echo json_encode(['success' => false, 'message' => 'Folder name is too long (max 100 characters)']);
        exit();
    }

    // Keep display names flexible; the storage path is sanitized separately below.
    if (preg_match('/[\x00-\x1F\x7F]/', $folderName)) {
        echo json_encode(['success' => false, 'message' => 'Folder names cannot contain control characters']);
        exit();
    }

    // SECURITY CHECK: User can only create folders in their own department
    if ($departmentId != $userDepartmentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: Can only create folders in your department']);
        exit();
    }
    
    // Validate department exists
    $stmt = $pdo->prepare("SELECT id, department_name FROM departments WHERE id = ? AND is_active = 1");
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Invalid department']);
        exit();
    }

    // Validate color format
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $folderColor)) {
        $folderColor = '#10b981';
    }

    // Validate icon format
    $validIcons = [
        'bxs-folder', 'bxs-briefcase', 'bxs-book', 'bxs-file', 'bxs-heart', 'bxs-star',
        'bxs-cog', 'bxs-shield', 'bxs-archive', 'bxs-bookmark', 'bxs-calendar',
        'bxs-collection', 'bxs-graduation', 'bxs-notepad', 'bxs-bar-chart-alt-2', 'bxs-purchase-tag'
    ];
    if (!in_array($folderIcon, $validIcons)) {
        $folderIcon = 'bxs-folder';
    }

    $folderLevel = 1;
    $folderPath = "/departments/{$departmentId}/custom/" . sanitizeFileName($folderName);
    if ($parentId !== null) {
        $stmt = $pdo->prepare("
            SELECT id, folder_level, folder_path FROM folders
            WHERE id = ? AND department_id = ? AND category IS NULL AND is_deleted = 0
        ");
        $stmt->execute([$parentId, $departmentId]);
        $parentFolder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$parentFolder) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'The selected parent folder is invalid']);
            exit();
        }
        $folderLevel = (int)$parentFolder['folder_level'] + 1;
        $folderPath = rtrim((string)($parentFolder['folder_path'] ?: "/departments/{$departmentId}/custom"), '/') . '/' . sanitizeFileName($folderName);
    }

    // Check whether this folder name already exists at the selected level.
    $stmt = $pdo->prepare("
        SELECT id FROM folders 
        WHERE department_id = ? AND folder_name = ? AND is_deleted = 0
          AND category IS NULL AND parent_id <=> ?
    ");
    $stmt->execute([$departmentId, $folderName, $parentId]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'A folder with this name already exists in your department']);
        exit();
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO folders (
            folder_name, 
            description, 
            created_by, 
            department_id, 
            folder_path, 
            folder_level,
            folder_color,
            folder_icon,
            parent_id,
            is_public,
            created_at,
            file_count,
            folder_size,
            category
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0, NULL)
    ");
    $stmt->execute([
        $folderName,
        $folderDescription,
        $currentUser['id'],
        $departmentId,
        $folderPath,
        $folderLevel,
        $folderColor,
        $folderIcon,
        $parentId,
        $isPublic
    ]);

    $folderId = (int)$pdo->lastInsertId();
    $physicalPath = __DIR__ . "/../../uploads/departments/{$departmentId}/custom/{$folderId}";
    if (!is_dir($physicalPath) && !mkdir($physicalPath, 0755, true) && !is_dir($physicalPath)) {
        throw new RuntimeException('Could not create the folder storage directory');
    }
    $physicalPaths[] = $physicalPath;
    if (!is_writable($physicalPath)) {
        throw new RuntimeException('The folder storage directory is not writable');
    }

    $semesterFolders = [
        'first' => 'First Semester',
        'second' => 'Second Semester'
    ];
    $semesterInsert = $pdo->prepare("
        INSERT INTO folders (
            folder_name, description, created_by, department_id, folder_path,
            folder_level, folder_color, folder_icon, parent_id, is_public,
            created_at, file_count, folder_size, category
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'bxs-folder', ?, ?, NOW(), 0, 0, NULL)
    ");

    foreach ($semesterFolders as $semesterKey => $semesterName) {
        $semesterPath = rtrim($folderPath, '/') . '/' . $semesterKey;
        $semesterPhysicalPath = $physicalPath . DIRECTORY_SEPARATOR . $semesterKey;
        if (!is_dir($semesterPhysicalPath)
            && !mkdir($semesterPhysicalPath, 0755, true)
            && !is_dir($semesterPhysicalPath)) {
            throw new RuntimeException("Could not create the {$semesterName} storage directory");
        }
        $physicalPaths[] = $semesterPhysicalPath;
        if (!is_writable($semesterPhysicalPath)) {
            throw new RuntimeException("The {$semesterName} storage directory is not writable");
        }

        $semesterInsert->execute([
            $semesterName,
            "{$semesterName} files for {$folderName}",
            $currentUser['id'],
            $departmentId,
            $semesterPath,
            $folderLevel + 1,
            $folderColor,
            $folderId,
            $isPublic
        ]);
    }
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Folder created successfully',
        'folder' => [
            'id' => $folderId,
            'folder_name' => $folderName,
            'description' => $folderDescription,
            'folder_color' => $folderColor,
            'folder_icon' => $folderIcon,
            'department_id' => $departmentId,
            'file_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach (array_reverse($physicalPaths) as $physicalPath) {
        if (is_dir($physicalPath)) {
            @rmdir($physicalPath);
        }
    }
    error_log("Folder creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while creating the folder: ' . $e->getMessage()
    ]);
}

// Helper function to sanitize filename for directory creation
function sanitizeFileName($filename) {
    // Remove or replace problematic characters
    $sanitized = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
    
    // Remove multiple underscores
    $sanitized = preg_replace('/_+/', '_', $sanitized);
    
    // Remove leading/trailing underscores
    $sanitized = trim($sanitized, '_');
    
    // Ensure it's not empty
    if (empty($sanitized)) {
        $sanitized = 'folder_' . time();
    }
    
    return $sanitized;
}
?>