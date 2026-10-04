<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

function custom_folder_contents_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    custom_folder_contents_response(405, ['success' => false, 'message' => 'Use POST to load folder contents.']);
}

if (!isLoggedIn()) {
    custom_folder_contents_response(401, ['success' => false, 'message' => 'Please sign in to view folder contents.']);
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || empty($currentUser['is_approved'])) {
    custom_folder_contents_response(403, ['success' => false, 'message' => 'Your account is not approved to access folders.']);
}

$departmentId = (int)($currentUser['department_id'] ?? 0);
if ($departmentId < 1 && !empty($currentUser['id'])) {
    $departmentStmt = $pdo->prepare('SELECT department_id FROM users WHERE id = ?');
    $departmentStmt->execute([$currentUser['id']]);
    $departmentId = (int)$departmentStmt->fetchColumn();
}
if ($departmentId < 1) {
    custom_folder_contents_response(403, ['success' => false, 'message' => 'Assign your account to a department before viewing folders.']);
}

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];
$folderId = filter_var($input['folder_id'] ?? null, FILTER_VALIDATE_INT);
$semester = (string)($input['semester'] ?? '');
if (!$folderId || !in_array($semester, ['first', 'second'], true)) {
    custom_folder_contents_response(400, ['success' => false, 'message' => 'Choose a valid custom folder and semester.']);
}

try {
    $rootStmt = $pdo->prepare("
        SELECT id, folder_name, folder_path, folder_level, folder_color, is_public, created_by, created_at
        FROM folders
        WHERE id = ? AND department_id = ? AND category IS NULL
          AND parent_id IS NULL AND is_deleted = 0
          AND (is_public = 1 OR created_by = ?)
        LIMIT 1
    ");
    $rootStmt->execute([$folderId, $departmentId, $currentUser['id']]);
    $root = $rootStmt->fetch(PDO::FETCH_ASSOC);
    if (!$root) {
        custom_folder_contents_response(404, ['success' => false, 'message' => 'The folder was not found or is not available to your account.']);
    }

    $semesterName = $semester === 'first' ? 'First Semester' : 'Second Semester';
    $semesterStmt = $pdo->prepare("
        SELECT id, created_at
        FROM folders
        WHERE parent_id = ? AND department_id = ? AND category IS NULL
          AND folder_name = ? AND is_deleted = 0
        LIMIT 1
    ");
    $semesterStmt->execute([$folderId, $departmentId, $semesterName]);
    $semesterFolder = $semesterStmt->fetch(PDO::FETCH_ASSOC);
    $semesterFolderId = (int)($semesterFolder['id'] ?? 0);

    if (!$semesterFolderId) {
        $semesterPath = rtrim((string)$root['folder_path'], '/') . '/' . $semester;
        $physicalPath = __DIR__ . "/../../uploads/departments/{$departmentId}/custom/{$folderId}/{$semester}";
        if (!is_dir($physicalPath) && !mkdir($physicalPath, 0755, true) && !is_dir($physicalPath)) {
            throw new RuntimeException('Could not prepare the semester storage directory.');
        }
        if (!is_writable($physicalPath)) {
            throw new RuntimeException('The semester storage directory is not writable.');
        }

        $pdo->beginTransaction();
        try {
            $insertSemester = $pdo->prepare("
                INSERT INTO folders (
                    folder_name, description, created_by, department_id, folder_path,
                    folder_level, folder_color, folder_icon, parent_id, is_public,
                    created_at, file_count, folder_size, category
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'bxs-folder', ?, ?, NOW(), 0, 0, NULL)
            ");
            $insertSemester->execute([
                $semesterName,
                "{$semesterName} files for {$root['folder_name']}",
                $root['created_by'],
                $departmentId,
                $semesterPath,
                (int)$root['folder_level'] + 1,
                $root['folder_color'],
                $folderId,
                $root['is_public']
            ]);
            $semesterFolderId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    $filesStmt = $pdo->prepare("
        SELECT id, original_name, file_size, uploaded_at
        FROM files
        WHERE folder_id = ? AND is_deleted = 0
        ORDER BY uploaded_at DESC, id DESC
    ");
    $filesStmt->execute([$semesterFolderId]);
    $files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

    custom_folder_contents_response(200, [
        'success' => true,
        'folder_id' => (int)$root['id'],
        'folder_name' => $root['folder_name'],
        'semester' => $semester,
        'semester_label' => $semesterName,
        'created_at' => $semesterFolder['created_at'] ?? $root['created_at'],
        'file_count' => count($files),
        'files' => $files
    ]);
} catch (Throwable $e) {
    error_log('Custom folder contents error: ' . $e->getMessage());
    custom_folder_contents_response(500, ['success' => false, 'message' => 'Could not load the folder contents. Please try again.']);
}
