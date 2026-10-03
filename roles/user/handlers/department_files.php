<?php
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

if (!isset($input['department_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Department ID required']);
    exit();
}

$requestedDepartmentId = intval($input['department_id']);

// SECURITY CHECK: User can only access files from their own department
// Exception: Admin users can access any department
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

if (!$isAdmin && $requestedDepartmentId != $userDepartmentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: Can only view files from your department']);
    exit();
}

try {
    // Get semester folders for the department
    $stmt = $pdo->prepare("
        SELECT id, folder_name, folder_path
        FROM folders 
        WHERE department_id = ? 
        AND is_deleted = 0 
        AND (folder_name LIKE '%First Semester%' OR folder_name LIKE '%Second Semester%')
        ORDER BY folder_name
    ");
    $stmt->execute([$requestedDepartmentId]);
    $semesterFolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $firstSemesterFolderId = null;
    $secondSemesterFolderId = null;

    foreach ($semesterFolders as $folder) {
        if (strpos($folder['folder_name'], 'First Semester') !== false) {
            $firstSemesterFolderId = $folder['id'];
        } elseif (strpos($folder['folder_name'], 'Second Semester') !== false) {
            $secondSemesterFolderId = $folder['id'];
        }
    }

    $response = [
        'success' => true,
        'first_semester' => [],
        'second_semester' => []
    ];

    // Get files for first semester
    if ($firstSemesterFolderId) {
        $stmt = $pdo->prepare("
            SELECT 
                f.id,
                f.file_name,
                f.original_name,
                f.file_size,
                f.file_type,
                f.uploaded_at,
                f.description,
                u.name as uploader_name
            FROM files f
            LEFT JOIN users u ON f.uploaded_by = u.id
            WHERE f.folder_id = ? 
            AND f.is_deleted = 0
            ORDER BY f.uploaded_at DESC
        ");
        $stmt->execute([$firstSemesterFolderId]);
        $response['first_semester'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get files for second semester
    if ($secondSemesterFolderId) {
        $stmt = $pdo->prepare("
            SELECT 
                f.id,
                f.file_name,
                f.original_name,
                f.file_size,
                f.file_type,
                f.uploaded_at,
                f.description,
                u.name as uploader_name
            FROM files f
            LEFT JOIN users u ON f.uploaded_by = u.id
            WHERE f.folder_id = ? 
            AND f.is_deleted = 0
            ORDER BY f.uploaded_at DESC
        ");
        $stmt->execute([$secondSemesterFolderId]);
        $response['second_semester'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($response);

} catch(Exception $e) {
    error_log("Error fetching department files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>