<?php
// delete_file.php - Secure file deletion handler
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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
    
    // Get file information with security checks
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            fo.department_id as folder_department_id,
            fo.folder_name,
            d.department_name
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        INNER JOIN departments d ON fo.department_id = d.id
        WHERE f.id = ? AND f.is_deleted = 0 AND fo.is_deleted = 0
    ");
    
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        echo json_encode(['success' => false, 'message' => 'File not found or already deleted']);
        exit();
    }
    
    // Security check 1: User can only delete files from their department
    if (!$file['folder_department_id'] || $file['folder_department_id'] != $userDepartmentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: File not in your department']);
        exit();
    }
    
    // Security check 2: Only the uploader can delete their own files (unless admin)
    $canDelete = false;
    if ($file['uploaded_by'] == $currentUser['id']) {
        $canDelete = true;
    } elseif (isset($currentUser['role']) && in_array($currentUser['role'], ['admin', 'super_admin'])) {
        $canDelete = true;
    }
    
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: You can only delete your own files']);
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Mark file as deleted in database (soft delete)
        $deleteStmt = $pdo->prepare("
            UPDATE files 
            SET 
                is_deleted = 1,
                deleted_at = NOW(),
                deleted_by = ?
            WHERE id = ?
        ");
        $deleteStmt->execute([$currentUser['id'], $fileId]);
        
        // Log the deletion activity
        $logStmt = $pdo->prepare("
            INSERT INTO file_activity_log 
            (file_id, user_id, action, action_details, created_at) 
            VALUES (?, ?, 'deleted', ?, NOW())
        ");
        $logDetails = json_encode([
            'deleted_by' => $currentUser['username'],
            'file_name' => $file['original_name'] ?: $file['file_name'],
            'file_size' => $file['file_size'],
            'department' => $file['department_name']
        ]);
        $logStmt->execute([$fileId, $currentUser['id'], 'file_deleted', $logDetails]);
        
        // Update folder file count
        $updateFolderStmt = $pdo->prepare("
            UPDATE folders 
            SET 
                file_count = GREATEST(0, file_count - 1),
                folder_size = GREATEST(0, folder_size - ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateFolderStmt->execute([$file['file_size'], $file['folder_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        // Try to delete physical file (but don't fail if it doesn't exist)
        $physicalFilePath = '../../../' . ltrim($file['file_path'], '/');
        if (file_exists($physicalFilePath)) {
            try {
                unlink($physicalFilePath);
                error_log("Physical file deleted: " . $physicalFilePath);
            } catch (Exception $e) {
                // Log but don't fail - file is already marked as deleted in DB
                error_log("Warning: Could not delete physical file {$physicalFilePath}: " . $e->getMessage());
            }
        }
        
        // Try to delete thumbnail if it exists
        if (!empty($file['thumbnail_path'])) {
            $thumbnailPath = '../../../' . ltrim($file['thumbnail_path'], '/');
            if (file_exists($thumbnailPath)) {
                try {
                    unlink($thumbnailPath);
                } catch (Exception $e) {
                    error_log("Warning: Could not delete thumbnail {$thumbnailPath}: " . $e->getMessage());
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'File deleted successfully',
            'data' => [
                'file_id' => $fileId,
                'file_name' => $file['original_name'] ?: $file['file_name'],
                'deleted_by' => $currentUser['username'],
                'deleted_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Delete file error for file ID {$fileId}: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the file: ' . $e->getMessage()
    ]);
}
?>