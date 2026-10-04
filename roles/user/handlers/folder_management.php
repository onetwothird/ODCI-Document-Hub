<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

function folder_management_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    folder_management_response(405, ['success' => false, 'message' => 'Use POST for folder actions.']);
}

if (!isLoggedIn()) {
    folder_management_response(401, ['success' => false, 'message' => 'Please sign in before managing folders.']);
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || empty($currentUser['is_approved'])) {
    folder_management_response(403, ['success' => false, 'message' => 'Your account is not approved for folder actions.']);
}

$departmentId = (int)($currentUser['department_id'] ?? 0);
if ($departmentId < 1 && !empty($currentUser['id'])) {
    $departmentStmt = $pdo->prepare('SELECT department_id FROM users WHERE id = ?');
    $departmentStmt->execute([$currentUser['id']]);
    $departmentId = (int)$departmentStmt->fetchColumn();
}
if ($departmentId < 1) {
    folder_management_response(403, ['success' => false, 'message' => 'Assign your account to a department before managing folders.']);
}

$action = trim((string)($_POST['action'] ?? ''));
$folderId = filter_var($_POST['folder_id'] ?? null, FILTER_VALIDATE_INT);
if (!$folderId || $folderId < 1) {
    folder_management_response(400, ['success' => false, 'message' => 'Choose a valid folder.']);
}

/**
 * Load a folder the current user is allowed to act on.
 * Department folders (category based) are manageable by every member of the
 * department; custom folders only by their owner or when shared publicly.
 */
function folder_management_load(PDO $pdo, int $folderId, int $departmentId, int $userId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT id, folder_name, description, created_by, department_id, category,
               folder_path, folder_level, folder_color, folder_icon, parent_id,
               is_public, is_deleted, COALESCE(is_favorite, 0) AS is_favorite,
               COALESCE(access_count, 0) AS access_count,
               COALESCE(folder_size, 0) AS folder_size,
               COALESCE(file_count, 0) AS file_count
        FROM folders
        WHERE id = ? AND department_id = ? AND is_deleted = 0
    ";

    $stmt = $pdo->prepare($sql . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$folderId, $departmentId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$folder) {
        return null;
    }

    if ($folder['category'] !== null) {
        return $folder;
    }

    $isOwner = (int)$folder['created_by'] === $userId;
    if (!$isOwner && (int)$folder['is_public'] !== 1) {
        return null;
    }

    return $folder;
}

/** Collect a folder and every descendant folder id. */
function folder_management_descendants(PDO $pdo, int $folderId, int $departmentId): array
{
    $ids = [$folderId];
    $frontier = [$folderId];

    while ($frontier) {
        $placeholders = implode(',', array_fill(0, count($frontier), '?'));
        $stmt = $pdo->prepare("
            SELECT id FROM folders
            WHERE parent_id IN ({$placeholders}) AND department_id = ? AND is_deleted = 0
        ");
        $stmt->execute(array_merge($frontier, [$departmentId]));
        $frontier = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $ids = array_merge($ids, $frontier);
    }

    return array_values(array_unique($ids));
}

function folder_management_counts(PDO $pdo, array $folderIds): array
{
    if (!$folderIds) {
        return ['file_count' => 0, 'total_size' => 0];
    }

    $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS file_count, COALESCE(SUM(file_size), 0) AS total_size
        FROM files
        WHERE folder_id IN ({$placeholders}) AND is_deleted = 0
    ");
    $stmt->execute($folderIds);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'file_count' => (int)($row['file_count'] ?? 0),
        'total_size' => (int)($row['total_size'] ?? 0)
    ];
}

switch ($action) {
    case 'access':
        try {
            $folder = folder_management_load($pdo, $folderId, $departmentId, (int)$currentUser['id']);
            if (!$folder) {
                folder_management_response(404, ['success' => false, 'message' => 'Folder not found.']);
            }

            $pdo->prepare('UPDATE folders SET access_count = COALESCE(access_count, 0) + 1 WHERE id = ?')
                ->execute([$folderId]);

            folder_management_response(200, ['success' => true, 'message' => 'Folder access recorded.']);
        } catch (Throwable $e) {
            error_log('Folder access error: ' . $e->getMessage());
            folder_management_response(500, ['success' => false, 'message' => 'The folder could not be opened.']);
        }
        // no break

    case 'toggle_favorite':
        try {
            $folder = folder_management_load($pdo, $folderId, $departmentId, (int)$currentUser['id'], true);
            if (!$folder) {
                folder_management_response(404, ['success' => false, 'message' => 'Folder not found.']);
            }

            $next = (int)$folder['is_favorite'] === 1 ? 0 : 1;
            $pdo->prepare('UPDATE folders SET is_favorite = ? WHERE id = ?')->execute([$next, $folderId]);

            folder_management_response(200, [
                'success' => true,
                'is_favorite' => $next === 1,
                'message' => $next === 1 ? 'Added to favorites.' : 'Removed from favorites.'
            ]);
        } catch (Throwable $e) {
            error_log('Folder favorite error: ' . $e->getMessage());
            folder_management_response(500, ['success' => false, 'message' => 'The favorite status could not be updated.']);
        }
        // no break

    case 'rename':
        $newName = trim((string)($_POST['new_name'] ?? ''));
        if ($newName === '' || mb_strlen($newName) > 100) {
            folder_management_response(400, ['success' => false, 'message' => 'Folder names must be 1 to 100 characters long.']);
        }

        try {
            $pdo->beginTransaction();

            $folder = folder_management_load($pdo, $folderId, $departmentId, (int)$currentUser['id'], true);
            if (!$folder) {
                $pdo->rollBack();
                folder_management_response(404, ['success' => false, 'message' => 'Folder not found.']);
            }

            // Category folders are named "<academic year> - <semester>" and drive
            // the report groupings, so they cannot be renamed freely.
            if ($folder['category'] !== null) {
                $pdo->rollBack();
                folder_management_response(403, ['success' => false, 'message' => 'Department document folders cannot be renamed.']);
            }

            $duplicateStmt = $pdo->prepare("
                SELECT id FROM folders
                WHERE department_id = ? AND parent_id <=> ? AND folder_name = ?
                  AND id <> ? AND is_deleted = 0
                LIMIT 1
            ");
            $duplicateStmt->execute([$departmentId, $folder['parent_id'], $newName, $folderId]);
            if ($duplicateStmt->fetchColumn()) {
                $pdo->rollBack();
                folder_management_response(409, ['success' => false, 'message' => 'A folder with that name already exists here.']);
            }

            $pdo->prepare('UPDATE folders SET folder_name = ? WHERE id = ?')->execute([$newName, $folderId]);
            $pdo->commit();

            folder_management_response(200, ['success' => true, 'message' => 'Folder renamed successfully.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Folder rename error: ' . $e->getMessage());
            folder_management_response(500, ['success' => false, 'message' => 'The folder could not be renamed.']);
        }
        // no break

    case 'delete':
        $forceDelete = (string)($_POST['force_delete'] ?? '0') === '1';

        try {
            $pdo->beginTransaction();

            $folder = folder_management_load($pdo, $folderId, $departmentId, (int)$currentUser['id'], true);
            if (!$folder) {
                $pdo->rollBack();
                folder_management_response(404, ['success' => false, 'message' => 'Folder not found.']);
            }

            $allFolderIds = folder_management_descendants($pdo, $folderId, $departmentId);
            $stats = folder_management_counts($pdo, $allFolderIds);

            $subfolderStmt = $pdo->prepare("
                SELECT COUNT(*) FROM folders
                WHERE parent_id = ? AND department_id = ? AND is_deleted = 0
            ");
            $subfolderStmt->execute([$folderId, $departmentId]);
            $subfolderCount = (int)$subfolderStmt->fetchColumn();

            if (!$forceDelete && ($stats['file_count'] > 0 || $subfolderCount > 0)) {
                $pdo->rollBack();
                folder_management_response(200, [
                    'success' => false,
                    'requires_confirmation' => true,
                    'file_count' => $stats['file_count'],
                    'subfolder_count' => $subfolderCount,
                    'message' => 'This folder is not empty.'
                ]);
            }

            $placeholders = implode(',', array_fill(0, count($allFolderIds), '?'));

            $pdo->prepare("
                UPDATE files
                SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                WHERE folder_id IN ({$placeholders}) AND is_deleted = 0
            ")->execute(array_merge([(int)$currentUser['id']], $allFolderIds));

            $pdo->prepare("
                UPDATE folders
                SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                WHERE id IN ({$placeholders}) AND is_deleted = 0
            ")->execute(array_merge([(int)$currentUser['id']], $allFolderIds));

            // The parent folder counters must no longer include the removed files.
            $parentStmt = $pdo->prepare('SELECT parent_id FROM folders WHERE id = ?');
            $parentStmt->execute([$folderId]);
            $parentId = $parentStmt->fetchColumn();
            if ($parentId) {
                $pdo->prepare("
                    UPDATE folders parent
                    SET parent.file_count = (
                            SELECT COUNT(*) FROM files f
                            WHERE f.folder_id = parent.id AND f.is_deleted = 0
                        ),
                        parent.folder_size = (
                            SELECT COALESCE(SUM(f.file_size), 0) FROM files f
                            WHERE f.folder_id = parent.id AND f.is_deleted = 0
                        )
                    WHERE parent.id = ?
                ")->execute([$parentId]);
            }

            $pdo->commit();

            folder_management_response(200, ['success' => true, 'message' => 'Folder deleted successfully.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Folder delete error: ' . $e->getMessage());
            folder_management_response(500, ['success' => false, 'message' => 'The folder could not be deleted.']);
        }
        // no break

    case 'get_info':
        try {
            $folder = folder_management_load($pdo, $folderId, $departmentId, (int)$currentUser['id']);
            if (!$folder) {
                folder_management_response(404, ['success' => false, 'message' => 'Folder not found.']);
            }

            $allFolderIds = folder_management_descendants($pdo, $folderId, $departmentId);
            $stats = folder_management_counts($pdo, $allFolderIds);

            $subfolderStmt = $pdo->prepare("
                SELECT id, folder_name, folder_color, folder_icon, COALESCE(is_favorite, 0) AS is_favorite
                FROM folders
                WHERE parent_id = ? AND department_id = ? AND is_deleted = 0
                ORDER BY folder_name
            ");
            $subfolderStmt->execute([$folderId, $departmentId]);
            $subfolders = $subfolderStmt->fetchAll(PDO::FETCH_ASSOC);

            $placeholders = implode(',', array_fill(0, count($allFolderIds), '?'));
            $recentStmt = $pdo->prepare("
                SELECT f.id, f.original_name, f.file_name, f.file_size, f.mime_type,
                       f.uploaded_at, f.academic_year, f.semester
                FROM files f
                WHERE f.folder_id IN ({$placeholders}) AND f.is_deleted = 0
                ORDER BY f.uploaded_at DESC
                LIMIT 10
            ");
            $recentStmt->execute($allFolderIds);
            $recentFiles = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

            unset($folder['department_id'], $folder['created_by'], $folder['is_deleted']);

            folder_management_response(200, [
                'success' => true,
                'folder' => $folder,
                'stats' => [
                    'file_count' => $stats['file_count'],
                    'subfolder_count' => count($subfolders),
                    'total_size' => $stats['total_size'],
                    'access_count' => (int)$folder['access_count']
                ],
                'subfolders' => $subfolders,
                'recent_files' => $recentFiles
            ]);
        } catch (Throwable $e) {
            error_log('Folder info error: ' . $e->getMessage());
            folder_management_response(500, ['success' => false, 'message' => 'Folder information could not be loaded.']);
        }
        // no break

    default:
        folder_management_response(400, ['success' => false, 'message' => 'Unknown folder action.']);
}