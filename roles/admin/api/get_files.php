<?php
require_once '../../includes/config.php';

$submission_id = $_GET['submission_id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        SELECT f.*, 
               CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as uploaded_by_name
        FROM document_files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        WHERE f.submission_id = ?
        ORDER BY f.uploaded_at DESC
    ");
    $stmt->execute([$submission_id]);
    $files = $stmt->fetchAll();

    if (empty($files)) {
        echo '<p>No files uploaded for this submission</p>';
    } else {
        foreach ($files as $file) {
            echo '<div class="file-item">';
            echo '<div>';
            echo '<strong>' . htmlspecialchars($file['file_name']) . '</strong><br>';
            echo '<small>' . formatFileSize($file['file_size']) . ' - ' . 
                 date('m/d/Y h:i A', strtotime($file['uploaded_at'])) . '</small><br>';
            echo '<small>Uploaded by: ' . htmlspecialchars($file['uploaded_by_name']) . '</small>';
            echo '</div>';
            echo '<div class="file-actions">';
            echo '<a href="download_file.php?id=' . $file['id'] . '" target="_blank"><i class="bx bx-download"></i></a>';
            echo '<a href="#" onclick="deleteFile(' . $file['id'] . ')"><i class="bx bx-trash"></i></a>';
            echo '</div>';
            echo '</div>';
        }
    }
} catch (Exception $e) {
    echo '<p>Error loading files</p>';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
?>