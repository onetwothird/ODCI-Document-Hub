<?php
require_once '../../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentUser = getCurrentUser($pdo);

if (!$currentUser || !$currentUser['is_approved']) {
    header('Location: logout.php');
    exit();
}

$documentId = $_GET['id'] ?? 0;

if (!$documentId) {
    header('Location: submission_tracker.php?error=invalid_document');
    exit();
}

try {
    // Get document info and verify ownership
    $stmt = $pdo->prepare("
        SELECT file_name, file_path, file_size, file_type
        FROM document_files 
        WHERE id = ? AND uploaded_by = ?
    ");
    $stmt->execute([$documentId, $currentUser['id']]);
    $document = $stmt->fetch();
    
    if (!$document) {
        header('Location: submission_tracker.php?error=document_not_found');
        exit();
    }
    
    $filePath = $document['file_path'];
    
    // Check if file exists on server
    if (!file_exists($filePath)) {
        header('Location: submission_tracker.php?error=file_not_found');
        exit();
    }
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($filePath);
    exit();
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    header('Location: submission_tracker.php?error=download_failed');
    exit();
}
?>