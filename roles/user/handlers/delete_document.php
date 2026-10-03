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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: submission_tracker.php');
    exit();
}

$documentId = $_POST['document_id'] ?? 0;
$redirectUrl = $_POST['redirect_url'] ?? 'submission_tracker.php';

if (!$documentId) {
    header('Location: ' . $redirectUrl . '?error=invalid_document');
    exit();
}

try {
    // Get document info and verify ownership
    $stmt = $pdo->prepare("
        SELECT file_name, file_path
        FROM document_files 
        WHERE id = ? AND uploaded_by = ?
    ");
    $stmt->execute([$documentId, $currentUser['id']]);
    $document = $stmt->fetch();
    
    if (!$document) {
        header('Location: ' . $redirectUrl . '?error=document_not_found');
        exit();
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM document_files WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$documentId, $currentUser['id']]);
    
    // Delete physical file if it exists
    if (file_exists($document['file_path'])) {
        unlink($document['file_path']);
    }
    
    header('Location: ' . $redirectUrl . '?success=document_deleted');
    exit();
    
} catch (Exception $e) {
    error_log("Delete error: " . $e->getMessage());
    header('Location: ' . $redirectUrl . '?error=delete_failed');
    exit();
}
?>