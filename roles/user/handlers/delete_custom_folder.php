<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

function delete_custom_folder_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    delete_custom_folder_response(405, ['success' => false, 'message' => 'Use POST to delete a folder.']);
}

if (!isLoggedIn()) {
    delete_custom_folder_response(401, ['success' => false, 'message' => 'Please sign in before deleting folders.']);
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || empty($currentUser['is_approved'])) {
    delete_custom_folder_response(403, ['success' => false, 'message' => 'Your account is not approved to delete folders.']);
}

$departmentId = (int)($currentUser['department_id'] ?? 0);
if ($departmentId < 1 && !empty($currentUser['id'])) {
    $departmentStmt = $pdo->prepare('SELECT department_id FROM users WHERE id = ?');
    $departmentStmt->execute([$currentUser['id']]);
    $departmentId = (int)$departmentStmt->fetchColumn();
}
if ($departmentId < 1) {
    delete_custom_folder_response(403, ['success' => false, 'message' => 'Assign your account to a department before deleting folders.']);
}

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];
$type = (string)($input['type'] ?? 'custom');
$folderId = filter_var($input['folder_id'] ?? null, FILTER_VALIDATE_INT);
$category = (string)($input['category'] ?? '');
$validCategories = [
    'ipcr_accomplishment', 'ipcr_target', 'workload', 'course_syllabus',
    'syllabus_acceptance', 'exam', 'tos', 'class_record', 'grading_sheet',
    'attendance_sheet', 'stakeholder_feedback', 'consultation', 'lecture',
    'activities', 'exam_acknowledgement', 'consultation_log'
];

if ($type === 'custom' && (!$folderId || $folderId < 1)) {
    delete_custom_folder_response(400, ['success' => false, 'message' => 'Choose a valid custom folder to delete.']);
}
if ($type === 'category' && !in_array($category, $validCategories, true)) {
    delete_custom_folder_response(400, ['success' => false, 'message' => 'Choose a valid document category to delete.']);
}
if (!in_array($type, ['custom', 'category'], true)) {
    delete_custom_folder_response(400, ['success' => false, 'message' => 'Choose a valid folder type to delete.']);
}

try {
    $pdo->beginTransaction();
    if ($type === 'custom') {
        $rootStmt = $pdo->prepare("
            SELECT id, folder_name
            FROM folders
            WHERE id = ? AND department_id = ? AND category IS NULL
              AND parent_id IS NULL AND is_deleted = 0
              AND (is_public = 1 OR created_by = ?)
            LIMIT 1 FOR UPDATE
        ");
        $rootStmt->execute([$folderId, $departmentId, $currentUser['id']]);
        $root = $rootStmt->fetch(PDO::FETCH_ASSOC);
        if (!$root) {
            $pdo->rollBack();
            delete_custom_folder_response(404, ['success' => false, 'message' => 'The custom folder is not available to your account.']);
        }
        $folderIds = [(int)$root['id']];
    } else {
        $markerName = '__deleted_category__:' . $category;
        $markerStmt = $pdo->prepare("
            SELECT id FROM folders
            WHERE department_id = ? AND category = ? AND folder_name = ?
              AND parent_id IS NULL
            LIMIT 1 FOR UPDATE
        ");
        $markerStmt->execute([$departmentId, $category, $markerName]);
        if ($markerStmt->fetchColumn()) {
            $pdo->rollBack();
            delete_custom_folder_response(404, ['success' => false, 'message' => 'This department folder has already been deleted.']);
        }

        $categoryFoldersStmt = $pdo->prepare("
            SELECT id
            FROM folders
            WHERE department_id = ? AND category = ? AND is_deleted = 0
            FOR UPDATE
        ");
        $categoryFoldersStmt->execute([$departmentId, $category]);
        $folderIds = array_map('intval', $categoryFoldersStmt->fetchAll(PDO::FETCH_COLUMN));

        $insertMarker = $pdo->prepare("
            INSERT INTO folders (
                folder_name, description, created_by, department_id, category,
                folder_path, folder_level, parent_id, is_public, is_deleted,
                deleted_at, deleted_by
            ) VALUES (?, ?, ?, ?, ?, '', 0, NULL, 1, 1, NOW(), ?)
        ");
        $insertMarker->execute([
            $markerName,
            'Deleted department document category',
            $currentUser['id'],
            $departmentId,
            $category,
            $currentUser['id']
        ]);
    }

    $frontier = $folderIds;
    while ($frontier) {
        $placeholders = implode(',', array_fill(0, count($frontier), '?'));
        $childrenStmt = $pdo->prepare("
            SELECT id
            FROM folders
            WHERE parent_id IN ({$placeholders}) AND department_id = ? AND is_deleted = 0
            FOR UPDATE
        ");
        $childrenStmt->execute(array_merge($frontier, [$departmentId]));
        $frontier = array_map('intval', $childrenStmt->fetchAll(PDO::FETCH_COLUMN));
        $folderIds = array_merge($folderIds, $frontier);
    }

    if ($folderIds) {
        $folderPlaceholders = implode(',', array_fill(0, count($folderIds), '?'));
        $deleteFiles = $pdo->prepare("
            UPDATE files
            SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
            WHERE folder_id IN ({$folderPlaceholders}) AND is_deleted = 0
        ");
        $deleteFiles->execute(array_merge([(int)$currentUser['id']], $folderIds));

        $deleteFolders = $pdo->prepare("
            UPDATE folders
            SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
            WHERE id IN ({$folderPlaceholders}) AND is_deleted = 0
        ");
        $deleteFolders->execute(array_merge([(int)$currentUser['id']], $folderIds));
    }
    $pdo->commit();

    delete_custom_folder_response(200, [
        'success' => true,
        'message' => $type === 'category'
            ? 'Department folder and all of its files were moved to the deleted state.'
            : 'Folder and its contents were moved to the deleted state.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Custom folder delete error: ' . $e->getMessage());
    delete_custom_folder_response(500, ['success' => false, 'message' => 'The folder could not be deleted. Please try again.']);
}
