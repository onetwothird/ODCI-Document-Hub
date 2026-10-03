<?php
require_once '../../../includes/config.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get current user information
$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $departmentId = $input['department_id'] ?? null;
    
    if (!$departmentId) {
        echo json_encode(['success' => false, 'message' => 'Department ID required']);
        exit();
    }
    
    // Verify user has access to this department (optional security check)
    // You can add logic here to check if user belongs to this department
    
    // Get files for both semesters
    $firstSemesterFiles = getFilesBySemester($pdo, $departmentId, 'first');
    $secondSemesterFiles = getFilesBySemester($pdo, $departmentId, 'second');
    
    echo json_encode([
        'success' => true,
        'first_semester' => $firstSemesterFiles,
        'second_semester' => $secondSemesterFiles
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching department files: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

function getFilesBySemester($pdo, $departmentId, $semester) {
    try {
        // First, get or find the semester folder
        $semesterName = ($semester === 'first') ? 'First Semester' : 'Second Semester';
        $academicYear = date('Y') . '-' . (date('Y') + 1);
        $folderName = $academicYear . ' - ' . $semesterName;
        
        // Get folder ID for this semester
        $stmt = $pdo->prepare("
            SELECT id FROM folders 
            WHERE department_id = ? AND folder_name = ? AND is_deleted = 0
        ");
        $stmt->execute([$departmentId, $folderName]);
        $folder = $stmt->fetch();
        
        if (!$folder) {
            // If folder doesn't exist, return empty array
            return [];
        }
        
        $folderId = $folder['id'];
        
        // Get files in this folder with uploader information
        $stmt = $pdo->prepare("
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
                COALESCE(CONCAT(u.name, ' ', COALESCE(u.misurname, '')), u.username) as uploader_name
            FROM files f
            LEFT JOIN users u ON f.uploaded_by = u.id
            WHERE f.folder_id = ? AND f.is_deleted = 0
            ORDER BY f.uploaded_at DESC
        ");
        $stmt->execute([$folderId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process files to ensure proper data format
        foreach ($files as &$file) {
            // Parse tags if they exist
            if (!empty($file['tags'])) {
                $file['tags'] = json_decode($file['tags'], true) ?: [];
            } else {
                $file['tags'] = [];
            }
            
            // Ensure uploader name is not empty
            if (empty($file['uploader_name'])) {
                $file['uploader_name'] = 'Unknown User';
            }
        }
        
        return $files;
        
    } catch (Exception $e) {
        error_log("Error getting files for semester {$semester}: " . $e->getMessage());
        return [];
    }
}
?>