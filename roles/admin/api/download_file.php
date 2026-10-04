<?php
require_once '../../../includes/config.php';

// Serves files that were uploaded from roles/user/folders.php. Those rows live
// in the `files` table (with the category on the parent `folders` row), not in
// the legacy `document_files` table, and `files.file_path` is relative to
// roles/ for department uploads.

function tracker_download_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
}

if (!isLoggedIn()) {
    tracker_download_response(401, ['status' => 'error', 'message' => 'You must be logged in to download files.']);
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || empty($currentUser['is_approved'])) {
    tracker_download_response(403, ['status' => 'error', 'message' => 'Your account is not approved.']);
}

$fileId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$fileId || $fileId < 1) {
    tracker_download_response(400, ['status' => 'error', 'message' => 'A valid file id is required.']);
}

$disposition = (($_GET['disposition'] ?? '') === 'inline') ? 'inline' : 'attachment';

try {
    $stmt = $pdo->prepare("
        SELECT
            f.id, f.file_name, f.original_name, f.file_path, f.file_size,
            f.mime_type, f.file_extension, f.download_count,
            f.academic_year, f.semester,
            fo.department_id AS folder_department_id,
            u.department_id AS uploader_department_id
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        INNER JOIN users u ON f.uploaded_by = u.id
        WHERE f.id = ? AND f.is_deleted = 0 AND fo.is_deleted = 0
        LIMIT 1
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        tracker_download_response(404, ['status' => 'error', 'message' => 'File not found or deleted.']);
    }

    // Department scoping: an admin may only reach files belonging to their own
    // department (or a sub-department of it). System-wide admins may reach all.
    $adminDepartmentId = (int)($currentUser['department_id'] ?? 0);
    $isSystemAdmin = $currentUser['role'] === 'super_admin' || $adminDepartmentId < 1;

    if (!$isSystemAdmin) {
        $fileDepartmentId = (int)($file['folder_department_id'] ?: $file['uploader_department_id']);
        if ($fileDepartmentId !== $adminDepartmentId) {
            $scopeStmt = $pdo->prepare("
                SELECT id FROM departments
                WHERE id = ? AND parent_id = ? AND is_active = 1
                LIMIT 1
            ");
            $scopeStmt->execute([$fileDepartmentId, $adminDepartmentId]);
            if (!$scopeStmt->fetchColumn()) {
                tracker_download_response(403, ['status' => 'error', 'message' => 'Access denied for files outside your department.']);
            }
        }
    }

    $absolutePath = odci_resolve_upload_path($file['file_path']);
    if ($absolutePath === null || !is_readable($absolutePath)) {
        error_log("Tracker download: stored path not found on disk for files.id={$fileId}: {$file['file_path']}");
        tracker_download_response(404, ['status' => 'error', 'message' => 'The stored file is missing from the server.']);
    }

    // Download audit trail (never blocks the download).
    try {
        $pdo->prepare("
            UPDATE files
            SET download_count = COALESCE(download_count, 0) + 1,
                last_downloaded = NOW(),
                last_downloaded_by = ?
            WHERE id = ?
        ")->execute([$currentUser['id'], $fileId]);

        $pdo->prepare("
            INSERT INTO file_downloads (file_id, user_id, downloaded_at, user_ip)
            VALUES (?, ?, NOW(), ?)
        ")->execute([$fileId, $currentUser['id'], $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        error_log('Tracker download logging failed: ' . $e->getMessage());
    }

    $downloadName = $file['original_name'] ?: $file['file_name'];
    $downloadName = preg_replace('/[^A-Za-z0-9._()\- ]/', '_', $downloadName);

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($absolutePath));
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: private, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');

    if ($disposition === 'inline' && strtolower((string)$file['file_extension']) === 'pdf') {
        header('Content-Security-Policy: sandbox');
    }

    readfile($absolutePath);
    exit;
} catch (Throwable $e) {
    error_log('Tracker download error for files.id=' . $fileId . ': ' . $e->getMessage());
    tracker_download_response(500, ['status' => 'error', 'message' => 'The file could not be served.']);
}