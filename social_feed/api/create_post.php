<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';
require_once '../social_feed-script.php';

// Ensure clean JSON output
ob_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $content = trim($_POST['content'] ?? '');
    $visibility = $_POST['visibility'] ?? 'public';
    
    // Allow empty content if there are images or files
    $hasImages = isset($_FILES['images']) && !empty($_FILES['images']);
    $hasFiles = isset($_FILES['files']) && !empty($_FILES['files']);
    
    if (empty($content) && !$hasImages && !$hasFiles) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Content, images, or files are required']);
        exit;
    }
    
    // Validate inputs
    $allowedVisibility = ['public', 'department', 'custom'];
    
    if (!in_array($visibility, $allowedVisibility)) {
        $visibility = 'public';
    }
    
    // Enhanced department handling
    $targetDepartments = null;
    if ($visibility === 'department') {
        // Check if specific departments were selected
        $selectedDepartments = $_POST['selectedDepartments'] ?? '';
        
        if (!empty($selectedDepartments)) {
            // Use the selected departments from the enhanced selector
            try {
                $selectedDepts = json_decode($selectedDepartments, true);
                if (is_array($selectedDepts) && count($selectedDepts) > 0) {
                    // Map department codes to department IDs
                    $targetDepartments = getDepartmentIdsByCodes($pdo, $selectedDepts);
                    
                    if (empty($targetDepartments)) {
                        ob_end_clean();
                        echo json_encode(['success' => false, 'message' => 'Invalid departments selected']);
                        exit;
                    }
                } else {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'No departments selected']);
                    exit;
                }
            } catch (Exception $e) {
                error_log("Error parsing selected departments: " . $e->getMessage());
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid department selection format']);
                exit;
            }
        } else {
            // Fallback to user's own department if no specific selection
            $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userDept = $stmt->fetch();
            if ($userDept && $userDept['department_id']) {
                $targetDepartments = [$userDept['department_id']];
            } else {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'No department found for user']);
                exit;
            }
        }
    }
    
    // Create post
    $postId = createPost($pdo, $userId, $content, 'text', $visibility, $targetDepartments, null);
    
    if (!$postId) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to create post']);
        exit;
    }
    
    // UPDATED: Define upload directories with correct paths
    $socialFeedBaseDir = $_SERVER['DOCUMENT_ROOT'] . '/ODCI/social_feed/uploads/';
    $imagesUploadDir = $socialFeedBaseDir . 'images/';
    $filesUploadDir = $socialFeedBaseDir . 'files/';
    
    // Create directories if they don't exist
    if (!is_dir($socialFeedBaseDir)) {
        mkdir($socialFeedBaseDir, 0755, true);
    }
    if (!is_dir($imagesUploadDir)) {
        mkdir($imagesUploadDir, 0755, true);
    }
    if (!is_dir($filesUploadDir)) {
        mkdir($filesUploadDir, 0755, true);
    }
    
    $uploadedImages = 0;
    $uploadedFiles = 0;
    $uploadErrors = [];
    
    // Handle multiple image uploads
    if (isset($_FILES['images']) && is_array($_FILES['images'])) {
        $imageFiles = $_FILES['images'];
        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxImageSize = 20 * 1024 * 1024; // 20MB per image
        $maxImages = 100; // Maximum 100 images per post
        
        // Handle the case where images are sent as images[0], images[1], etc.
        if (isset($imageFiles['name']) && is_array($imageFiles['name'])) {
            $imageCount = count($imageFiles['name']);
            
            if ($imageCount > $maxImages) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => "Maximum {$maxImages} images allowed"]);
                exit;
            }
            
            for ($i = 0; $i < $imageCount; $i++) {
                // Check if this image slot has a file
                if (empty($imageFiles['name'][$i]) || $imageFiles['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                $imageName = $imageFiles['name'][$i];
                $imageType = $imageFiles['type'][$i];
                $imageSize = $imageFiles['size'][$i];
                $imageTmpName = $imageFiles['tmp_name'][$i];
                
                // Validate image
                if (!in_array($imageType, $allowedImageTypes)) {
                    $uploadErrors[] = "{$imageName}: Invalid image type";
                    continue;
                }
                
                if ($imageSize > $maxImageSize) {
                    $uploadErrors[] = "{$imageName}: Image too large (max 20MB)";
                    continue;
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($imageName, PATHINFO_EXTENSION);
                $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
                $physicalPath = $imagesUploadDir . $fileName;
                
                if (move_uploaded_file($imageTmpName, $physicalPath)) {
                    // UPDATED: Store path relative to ODCI root for database
                    $webPath = 'social_feed/uploads/images/' . $fileName;
                    
                    $mediaAdded = addPostMedia($pdo, $postId, 'image', $webPath, $fileName, $imageName, $imageSize, $imageType);
                    
                    if ($mediaAdded) {
                        $uploadedImages++;
                        error_log("Image uploaded successfully: {$webPath}");
                    } else {
                        $uploadErrors[] = "{$imageName}: Failed to save to database";
                        // Clean up the uploaded file
                        if (file_exists($physicalPath)) {
                            unlink($physicalPath);
                        }
                    }
                } else {
                    $uploadErrors[] = "{$imageName}: Failed to upload";
                }
            }
        }
    }
    
    // Handle multiple file uploads
    if (isset($_FILES['files']) && is_array($_FILES['files'])) {
        $files = $_FILES['files'];
        $maxFileSize = 100 * 1024 * 1024; // 100MB per file
        $maxFiles = 50; // Maximum 50 files per post
        
        // Blocked image types (should use image upload instead)
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Handle the case where files are sent as files[0], files[1], etc.
        if (isset($files['name']) && is_array($files['name'])) {
            $fileCount = count($files['name']);
            
            if ($fileCount > $maxFiles) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => "Maximum {$maxFiles} files allowed"]);
                exit;
            }
            
            for ($i = 0; $i < $fileCount; $i++) {
                // Check if this file slot has a file
                if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                $fileName = $files['name'][$i];
                $fileType = $files['type'][$i];
                $fileSize = $files['size'][$i];
                $fileTmpName = $files['tmp_name'][$i];
                
                // Check if it's an image type (should use image upload)
                if (in_array($fileType, $imageTypes)) {
                    $uploadErrors[] = "{$fileName}: Image files should use image upload button";
                    continue;
                }
                
                // Validate file size
                if ($fileSize > $maxFileSize) {
                    $uploadErrors[] = "{$fileName}: File too large (max 100MB)";
                    continue;
                }
                
                // Generate unique filename while preserving extension
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
                $physicalPath = $filesUploadDir . $uniqueFileName;
                
                if (move_uploaded_file($fileTmpName, $physicalPath)) {
                    // UPDATED: Store path relative to ODCI root for database
                    $webPath = 'social_feed/uploads/files/' . $uniqueFileName;
                    
                    $mediaAdded = addPostMedia($pdo, $postId, 'file', $webPath, $uniqueFileName, $fileName, $fileSize, $fileType);
                    
                    if ($mediaAdded) {
                        $uploadedFiles++;
                        error_log("File uploaded successfully: {$webPath}");
                    } else {
                        $uploadErrors[] = "{$fileName}: Failed to save to database";
                        // Clean up the uploaded file
                        if (file_exists($physicalPath)) {
                            unlink($physicalPath);
                        }
                    }
                } else {
                    $uploadErrors[] = "{$fileName}: Failed to upload";
                }
            }
        }
    }
    
    // Clean output buffer and prepare response
    ob_end_clean();
    
    // Prepare response message
    $messageParts = ['Post created successfully'];
    
    if ($uploadedImages > 0) {
        $messageParts[] = "{$uploadedImages} image(s) uploaded";
    }
    
    if ($uploadedFiles > 0) {
        $messageParts[] = "{$uploadedFiles} file(s) uploaded";
    }
    
    $message = implode(' with ', $messageParts);
    
    if (!empty($uploadErrors)) {
        $message .= '. Some files had issues: ' . implode(', ', $uploadErrors);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'post_id' => $postId,
        'uploaded_images' => $uploadedImages,
        'uploaded_files' => $uploadedFiles,
        'upload_errors' => $uploadErrors,
        'visibility' => $visibility,
        'target_departments' => $targetDepartments
    ]);
    
} catch(Exception $e) {
    ob_end_clean();
    error_log("Create post error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating post: ' . $e->getMessage()
    ]);
}

/**
 * Helper function to get department IDs by department codes
 */
function getDepartmentIdsByCodes($pdo, $departmentCodes) {
    if (empty($departmentCodes) || !is_array($departmentCodes)) {
        return [];
    }
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($departmentCodes) - 1) . '?';
    
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE department_code IN ({$placeholders}) AND is_active = 1");
    $stmt->execute($departmentCodes);
    
    $departmentIds = [];
    while ($row = $stmt->fetch()) {
        $departmentIds[] = $row['id'];
    }
    
    return $departmentIds;
}
?>