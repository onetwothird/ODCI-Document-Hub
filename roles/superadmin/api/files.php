<?php
/**
 * Files endpoint for the superadmin file manager.
 *
 * GET  api/files.php?action=list      -> { success, files[], pagination, current_folder[] }
 * GET  api/files.php?action=stats     -> { success, data: { total_files, total_folders, total_size, total_downloads } }
 * GET  api/files.php?action=details&id=
 * GET  api/files.php?action=download&id=      (also accepts ?token= for public share links)
 * GET  api/files.php?action=export&...         -> CSV download
 * POST api/files.php?action=upload            (multipart: files[], folder_id, description, is_public)
 * POST api/files.php?action=delete   (JSON { id })
 * POST api/files.php?action=restore  (JSON { id })
 * POST api/files.php?action=bulk     (JSON { action, file_ids[] })
 * POST api/files.php?action=cleanup
 * POST api/files.php?action=share    (?id=)
 */

require_once __DIR__ . '/_bootstrap.php';

$action    = $_GET['action'] ?? $_POST['action'] ?? '';
$publicTkn = $_GET['token'] ?? null;

/*
 * A share link must work without a session, so resolve it before the usual
 * superadmin check. A valid token only ever grants read/download access.
 */
$sharedFileId = 0;
if ($publicTkn) {
    $t = $pdo->prepare('SELECT id FROM files WHERE public_token = ? AND is_deleted = 0 LIMIT 1');
    $t->execute([$publicTkn]);
    $sharedFileId = (int)$t->fetchColumn();
    if (!$sharedFileId) {
        api_fail('This share link is invalid or has been revoked', 404);
    }
} else {
    api_require_superadmin();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

/** Build the WHERE fragment shared by list/export. */
function files_filter(PDO $pdo, array $q, array &$params): string
{
    $where = [];

    // Folder navigation. folder_id 0 means "all folders" on the client.
    if (!empty($q['folder_id']) && (int)$q['folder_id'] > 0) {
        $where[] = 'f.folder_id = ?';
        $params[] = (int)$q['folder_id'];
    }

    if (!empty($q['search'])) {
        $where[] = '(f.original_name LIKE ? OR f.description LIKE ?)';
        $like = '%' . $q['search'] . '%';
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($q['type'])) {
        $where[] = 'f.file_type = ?';
        $params[] = $q['type'];
    }

    if (!empty($q['department'])) {
        $where[] = 'fo.department_id = ?';
        $params[] = (int)$q['department'];
    }

    if (!empty($q['date'])) {
        // Values: today | week | month | year
        $intervals = [
            'today'  => 'DATE(f.uploaded_at) = CURDATE()',
            'week'   => 'f.uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            'month'  => 'f.uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            'year'   => 'YEAR(f.uploaded_at) = YEAR(CURDATE())',
        ];
        if (isset($intervals[$q['date']])) {
            $where[] = $intervals[$q['date']];
        }
    }

    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

/** Ancestor chain for a folder, used to render the breadcrumb. */
function folder_chain(PDO $pdo, int $folderId): array
{
    $chain = [];
    $guard = 0;
    while ($folderId > 0 && $guard++ < 20) {
        $s = $pdo->prepare('SELECT id, folder_name, parent_id FROM folders WHERE id = ?');
        $s->execute([$folderId]);
        $row = $s->fetch();
        if (!$row) {
            break;
        }
        array_unshift($chain, ['id' => (int)$row['id'], 'folder_name' => $row['folder_name']]);
        $folderId = (int)$row['parent_id'];
    }
    return $chain;
}

switch ($action) {

    // -----------------------------------------------------------------------
    case 'list':
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 25)));

        $sortMap = [
            'name'       => 'f.original_name',
            'date'       => 'f.uploaded_at',
            'size'       => 'f.file_size',
            'downloads'  => 'f.download_count',
            'type'       => 'f.file_type',
        ];
        $sort = $sortMap[$_GET['sort'] ?? 'date'] ?? 'f.uploaded_at';
        $dir  = strtolower($_GET['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';

        $params = [];
        $where  = files_filter($pdo, $_GET, $params);

        try {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM files f LEFT JOIN folders fo ON f.folder_id = fo.id $where"
            );
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $totalPages = max(1, (int)ceil($total / $limit));
            $offset     = ($page - 1) * $limit;

            $listParams = $params;
            $listParams[] = $limit;
            $listParams[] = $offset;

            $stmt = $pdo->prepare(
                "SELECT f.*,
                        fo.folder_name,
                        fo.department_id,
                        d.department_name,
                        CONCAT_WS(' ', u.name, NULLIF(u.mi, ''), u.surname) AS uploader_full_name,
                        u.username AS uploader_username
                   FROM files f
                   LEFT JOIN folders fo    ON f.folder_id = fo.id
                   LEFT JOIN departments d ON fo.department_id = d.id
                   LEFT JOIN users u       ON f.uploaded_by = u.id
                   $where
               ORDER BY $sort $dir, f.id DESC
                  LIMIT ? OFFSET ?"
            );
            $stmt->execute($listParams);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

            api_json([
                'success'        => true,
                'files'          => $files,
                'pagination'     => [
                    'current_page' => $page,
                    'total_pages'  => $totalPages,
                    'total'        => $total,
                    'limit'        => $limit,
                ],
                'current_folder' => folder_chain($pdo, (int)($_GET['folder_id'] ?? 0)),
            ]);
        } catch (Throwable $e) {
            error_log('superadmin api/files list: ' . $e->getMessage());
            api_fail('Could not load files: ' . $e->getMessage(), 500);
        }
        break;

    // -----------------------------------------------------------------------
    case 'stats':
        try {
            $row = $pdo->query(
                "SELECT
                    (SELECT COUNT(*) FROM files WHERE is_deleted = 0) AS total_files,
                    (SELECT COUNT(*) FROM folders WHERE is_deleted = 0) AS total_folders,
                    (SELECT COALESCE(SUM(file_size), 0) FROM files WHERE is_deleted = 0) AS total_size,
                    (SELECT COALESCE(SUM(download_count), 0) FROM files WHERE is_deleted = 0) AS total_downloads"
            )->fetch(PDO::FETCH_ASSOC);

            api_json(['success' => true, 'data' => $row]);
        } catch (Throwable $e) {
            error_log('superadmin api/files stats: ' . $e->getMessage());
            api_fail('Could not load statistics', 500);
        }
        break;

    // -----------------------------------------------------------------------
    case 'details':
        $id = (int)($_GET['id'] ?? 0);
        try {
            $stmt = $pdo->prepare(
                "SELECT f.*, fo.folder_name, d.department_name,
                        CONCAT_WS(' ', u.name, NULLIF(u.mi, ''), u.surname) AS uploader_full_name
                   FROM files f
                   LEFT JOIN folders fo    ON f.folder_id = fo.id
                   LEFT JOIN departments d ON fo.department_id = d.id
                   LEFT JOIN users u       ON f.uploaded_by = u.id
                  WHERE f.id = ?"
            );
            $stmt->execute([$id]);
            $file = $stmt->fetch();
            if (!$file) {
                api_fail('File not found', 404);
            }
            api_json(['success' => true, 'data' => $file]);
        } catch (Throwable $e) {
            error_log('superadmin api/files details: ' . $e->getMessage());
            api_fail('Could not load file details', 500);
        }
        break;

    // -----------------------------------------------------------------------
    case 'download':
        $id = (int)($_GET['id'] ?? 0);

        // Without a share token this is an authenticated download.
        if (!$sharedFileId) {
            if ($id <= 0) {
                api_fail('Missing file id');
            }
        } else {
            $id = $sharedFileId;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ?');
            $stmt->execute([$id]);
            $file = $stmt->fetch();

            if (!$file) {
                api_fail('File not found', 404);
            }
            if ($file['is_deleted'] && !$sharedFileId) {
                api_fail('This file has been deleted', 404);
            }

            $real = api_safe_realpath((string)$file['file_path']);
            if ($real === null || !is_file($real)) {
                api_fail('The stored file is missing from disk', 404);
            }

            // Record the download (best effort - never block the transfer).
            try {
                $pdo->prepare(
                    'UPDATE files
                        SET download_count = COALESCE(download_count, 0) + 1,
                            last_downloaded = NOW(),
                            last_downloaded_by = ?
                      WHERE id = ?'
                )->execute([$currentUserId ?: null, $id]);

                $pdo->prepare(
                    'INSERT INTO file_downloads (file_id, user_id, downloaded_at, user_ip)
                     VALUES (?, ?, NOW(), ?)'
                )->execute([$id, $currentUserId ?: null, $_SERVER['REMOTE_ADDR'] ?? null]);
            } catch (Throwable $ignored) {
                // file_downloads may not exist on a very old database.
            }

            $name = $file['original_name'] ?: $file['file_name'];
            $name = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $name);

            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
            header('Content-Length: ' . filesize($real));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, must-revalidate');
            readfile($real);
            exit;
        } catch (Throwable $e) {
            error_log('superadmin api/files download: ' . $e->getMessage());
            api_fail('Download failed', 500);
        }
        break;

    // -----------------------------------------------------------------------
    case 'upload':
        if (empty($_FILES['files']) || !is_array($_FILES['files']['name'])) {
            api_fail('No files were uploaded');
        }

        $folderId = (int)($_POST['folder_id'] ?? 0);
        if ($folderId <= 0) {
            api_fail('Please choose a destination folder');
        }

        $dest = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND is_deleted = 0');
        $dest->execute([$folderId]);
        if (!$dest->fetch()) {
            api_fail('Destination folder not found');
        }

        $description = trim((string)($_POST['description'] ?? '')) ?: null;
        $isPublic    = !empty($_POST['is_public']) ? 1 : 0;

        // Match the convention used by the rest of the app:
        // uploads/documents/<academic_year>/<semester>/<filename>
        $academicYear = trim((string)($_POST['academic_year'] ?? ''));
        if ($academicYear === '') {
            $academicYear = date('Y');
        }
        if (!preg_match('/^\d{4}$/', $academicYear)) {
            api_fail('Invalid academic year');
        }

        $semester = strtolower(trim((string)($_POST['semester'] ?? '')));
        $semester = str_replace(' ', '_', $semester);
        if (!in_array($semester, ['first', 'second', 'first_semester', 'second_semester', 'summer'], true)) {
            $semester = ((int)date('n') >= 6) ? 'second' : 'first';
        }

        // Map the friendly names onto the values the files table stores.
        $storedSemester = [
            'first_semester'  => 'first',
            'second_semester' => 'second',
        ][$semester] ?? $semester;

        $relativeDir = 'uploads/documents/' . $academicYear . '/' . $semester;
        $uploadDir   = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt',
                       'csv', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar'];

        $insert = $pdo->prepare(
            'INSERT INTO files
                (file_name, original_name, file_path, file_size, file_type, mime_type,
                 file_extension, uploaded_by, folder_id, description, is_public,
                 academic_year, semester)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $saved = 0;
        $errors = [];

        foreach ($_FILES['files']['name'] as $i => $originalName) {
            $err = $_FILES['files']['error'][$i];
            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = "$originalName: upload error code $err";
                continue;
            }

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = "$originalName: file type .$ext is not allowed";
                continue;
            }

            if ($_FILES['files']['size'][$i] > MAX_UPLOAD_SIZE) {
                $errors[] = "$originalName: exceeds the " . (MAX_UPLOAD_SIZE / 1048576) . 'MB limit';
                continue;
            }

            $storedName = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $target     = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

            if (!move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
                $errors[] = "$originalName: could not be written to disk";
                continue;
            }

            $insert->execute([
                $storedName,
                $originalName,
                $relativeDir . '/' . $storedName,
                $_FILES['files']['size'][$i],
                $ext,
                $_FILES['files']['type'][$i] ?: null,
                $ext,
                $currentUserId,
                $folderId,
                $description,
                $isPublic,
                $academicYear,
                $storedSemester,
            ]);
            $saved++;
        }

        if ($saved > 0) {
            logActivity($pdo, $currentUserId, 'upload_file', 'file', null,
                "Uploaded $saved file(s) to folder #$folderId");
        }

        if ($saved === 0) {
            api_fail($errors ? implode('; ', $errors) : 'No files could be saved', 400);
        }

        api_ok(
            ['uploaded' => $saved, 'errors' => $errors],
            $saved . ($saved === 1 ? ' file uploaded' : ' files uploaded') . ' successfully'
        );
        break;

    // -----------------------------------------------------------------------
    case 'delete':
    case 'restore':
        $input = api_input();
        $id    = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            api_fail('Missing file id');
        }

        $isDelete = $action === 'delete';
        try {
            if ($isDelete) {
                $pdo->prepare(
                    'UPDATE files SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?'
                )->execute([$currentUserId, $id]);
            } else {
                $pdo->prepare(
                    'UPDATE files SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE id = ?'
                )->execute([$id]);
            }

            logActivity($pdo, $currentUserId, $isDelete ? 'delete_file' : 'restore_file', 'file', $id,
                ($isDelete ? 'Deleted' : 'Restored') . " file #$id");

            api_ok([], $isDelete ? 'File moved to trash' : 'File restored');
        } catch (Throwable $e) {
            error_log("superadmin api/files $action: " . $e->getMessage());
            api_fail('Operation failed', 500);
        }
        break;

    // -----------------------------------------------------------------------
    case 'bulk':
        $input    = api_input();
        $bulkAct  = strtolower((string)($input['action'] ?? ''));
        $fileIds  = array_map('intval', (array)($input['file_ids'] ?? []));

        if (!$fileIds) {
            api_fail('No files selected');
        }
        if (!in_array($bulkAct, ['delete', 'restore'], true)) {
            api_fail('Unsupported bulk action');
        }

        $in   = implode(',', array_fill(0, count($fileIds), '?'));
        $args = $fileIds;

        if ($bulkAct === 'delete') {
            array_unshift($args, $currentUserId);
            $sql = "UPDATE files SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id IN ($in)";
        } else {
            $sql = "UPDATE files SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE id IN ($in)";
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($args);

            logActivity($pdo, $currentUserId, 'bulk_' . $bulkAct, 'file', null,
                'Bulk ' . $bulkAct . ' on ' . count($fileIds) . ' file(s)');

            api_ok(['affected' => $stmt->rowCount()], 'Bulk ' . $bulkAct . ' completed');
        } catch (Throwable $e) {
            error_log('superadmin api/files bulk: ' . $e->getMessage());
            api_fail('Bulk operation failed', 500);
        }
        break;

    // -----------------------------------------------------------------------
    case 'cleanup':
        try {
            $stmt = $pdo->prepare(
                'SELECT id, file_path FROM files WHERE is_deleted = 1 AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $real = api_safe_realpath((string)$row['file_path']);
                if ($real !== null && is_file($real)) {
                    @unlink($real);
                }
            }

            if ($rows) {
                $in = implode(',', array_fill(0, count($rows), '?'));
                $del = $pdo->prepare("DELETE FROM files WHERE id IN ($in)");
                $del->execute(array_column($rows, 'id'));
            }

            logActivity($pdo, $currentUserId, 'cleanup_files', 'file', null,
                'Permanently removed ' . count($rows) . ' file(s)');

            api_ok(['cleaned_count' => count($rows)], 'Cleanup complete');
        } catch (Throwable $e) {
            error_log('superadmin api/files cleanup: ' . $e->getMessage());
            api_fail('Cleanup failed', 500);
        }
        break;

    // -----------------------------------------------------------------------
    case 'share':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            api_fail('Missing file id');
        }

        try {
            $stmt = $pdo->prepare('SELECT id, public_token FROM files WHERE id = ? AND is_deleted = 0');
            $stmt->execute([$id]);
            $file = $stmt->fetch();
            if (!$file) {
                api_fail('File not found', 404);
            }

            $token = $file['public_token'];
            if (empty($token)) {
                $token = bin2hex(random_bytes(24));
                $pdo->prepare('UPDATE files SET public_token = ?, is_public = 1 WHERE id = ?')
                    ->execute([$token, $id]);
            }

            api_ok([
                'share_url' => BASE_URL . '/roles/superadmin/api/files.php?action=download&token=' . $token,
            ], 'Share link generated');
        } catch (Throwable $e) {
            error_log('superadmin api/files share: ' . $e->getMessage());
            api_fail('Could not generate share link', 500);
        }
        break;

    // -----------------------------------------------------------------------
    case 'export':
        $params = [];
        $where  = files_filter($pdo, $_GET, $params);

        try {
            $stmt = $pdo->prepare(
                "SELECT f.original_name, f.file_type, f.file_size, f.uploaded_at,
                        f.download_count, fo.folder_name, d.department_name,
                        CONCAT_WS(' ', u.name, NULLIF(u.mi, ''), u.surname) AS uploader_full_name
                   FROM files f
                   LEFT JOIN folders fo    ON f.folder_id = fo.id
                   LEFT JOIN departments d ON fo.department_id = d.id
                   LEFT JOIN users u       ON f.uploaded_by = u.id
                   $where
               ORDER BY f.uploaded_at DESC"
            );
            $stmt->execute($params);

            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['Original Name', 'Type', 'Size', 'Uploaded', 'Downloads',
                              'Folder', 'Department', 'Uploaded By']);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($handle, [
                    $row['original_name'],
                    $row['file_type'],
                    api_format_size($row['file_size']),
                    $row['uploaded_at'],
                    $row['download_count'],
                    $row['folder_name'],
                    $row['department_name'],
                    $row['uploader_full_name'],
                ]);
            }

            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="files_export_' . date('Y-m-d') . '.csv"');
            header('X-Content-Type-Options: nosniff');
            echo $csv;
            exit;
        } catch (Throwable $e) {
            error_log('superadmin api/files export: ' . $e->getMessage());
            api_fail('Export failed', 500);
        }
        break;

    // -----------------------------------------------------------------------
    default:
        api_fail('Unknown action', 400);
}
