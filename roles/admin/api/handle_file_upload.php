<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $submission_id = $_POST['submission_id'] ?? 0;
    
    if (empty($_FILES['document_file']['name'])) {
        throw new Exception('No file uploaded');
    }
    
    $file_name = $_FILES['document_file']['name'];
    $file_tmp = $_FILES['document_file']['tmp_name'];
    $file_size = $_FILES['document_file']['size'];
    $file_type = $_FILES['document_file']['type'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validate file type and size
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    if (!in_array($file_ext, $allowed_extensions)) {
        throw new Exception('File type not allowed');
    }
    
    if ($file_size > 10 * 1024 * 1024) { // 10MB max
        throw new Exception('File size exceeds 10MB limit');
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '.' . $file_ext;
    $upload_path = '../../uploads/documents/' . $new_filename;
    
    if (!move_uploaded_file($file_tmp, $upload_path)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Save to database
    $stmt = $pdo->prepare("
        INSERT INTO document_files
        (submission_id, file_name, file_path, file_size, file_type, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $submission_id,
        $file_name,
        $new_filename,
        $file_size,
        $file_type,
        $_SESSION['user_id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>