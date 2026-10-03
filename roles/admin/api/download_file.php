<?php
require_once '../../includes/config.php';

$file_id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        SELECT file_name, file_path 
        FROM document_files 
        WHERE id = ?
    ");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch();
    
    if (!$file) {
        throw new Exception('File not found');
    }
    
    $file_path = '../../uploads/documents/' . $file['file_path'];
    
    if (!file_exists($file_path)) {
        throw new Exception('File not found on server');
    }
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Error: ' . $e->getMessage();
}
?>