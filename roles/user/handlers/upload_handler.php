<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

function upload_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    upload_response(405, ['success' => false, 'message' => 'Upload requests must use POST.']);
}

if (!isLoggedIn()) {
    upload_response(401, ['success' => false, 'message' => 'Please sign in before uploading files.']);
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || empty($currentUser['is_approved'])) {
    upload_response(403, ['success' => false, 'message' => 'Your account is not approved for uploads.']);
}

$userDepartmentId = (int)($currentUser['department_id'] ?? 0);
if ($userDepartmentId < 1 && !empty($currentUser['id'])) {
    $departmentStmt = $pdo->prepare('SELECT department_id FROM users WHERE id = ?');
    $departmentStmt->execute([$currentUser['id']]);
    $userDepartmentId = (int)$departmentStmt->fetchColumn();
}
if ($userDepartmentId < 1) {
    upload_response(403, ['success' => false, 'message' => 'Assign your account to a department before uploading files.']);
}

try {
    $requestedDepartmentId = filter_var($_POST['department'] ?? null, FILTER_VALIDATE_INT);
    if (!$requestedDepartmentId || (int)$requestedDepartmentId !== $userDepartmentId) {
        upload_response(403, ['success' => false, 'message' => 'You can only upload files to your assigned department.']);
    }

    $categories = [
        'ipcr_accomplishment', 'ipcr_target', 'workload', 'course_syllabus',
        'syllabus_acceptance', 'exam', 'tos', 'class_record', 'grading_sheet',
        'attendance_sheet', 'stakeholder_feedback', 'consultation', 'lecture',
        'activities', 'exam_acknowledgement', 'consultation_log'
    ];
    $customFolderId = null;
    $customFolder = null;
    $rawCustomFolderId = trim((string)($_POST['custom_folder_id'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    if ($rawCustomFolderId !== '') {
        $validatedFolderId = filter_var($rawCustomFolderId, FILTER_VALIDATE_INT);
        if (!$validatedFolderId || $validatedFolderId < 1) {
            upload_response(400, ['success' => false, 'message' => 'Select a valid custom folder.']);
        }
        $customFolderId = (int)$validatedFolderId;
        $customFolderStmt = $pdo->prepare("
            SELECT id, folder_name, folder_path, folder_level, folder_color, folder_icon, is_public, created_by
            FROM folders
            WHERE id = ? AND department_id = ? AND category IS NULL
              AND parent_id IS NULL AND is_deleted = 0
              AND (is_public = 1 OR created_by = ?)
            LIMIT 1
        ");
        $customFolderStmt->execute([$customFolderId, $userDepartmentId, $currentUser['id']]);
        $customFolder = $customFolderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$customFolder) {
            upload_response(403, ['success' => false, 'message' => 'You do not have access to upload to that custom folder.']);
        }
        $category = '';
    } elseif (!in_array($category, $categories, true)) {
        upload_response(400, ['success' => false, 'message' => 'Select a valid document category.']);
    } else {
        $deletedCategoryStmt = $pdo->prepare("
            SELECT id
            FROM folders
            WHERE department_id = ? AND category = ?
              AND folder_name = CONCAT('__deleted_category__:', category)
              AND parent_id IS NULL
            LIMIT 1
        ");
        $deletedCategoryStmt->execute([$userDepartmentId, $category]);
        if ($deletedCategoryStmt->fetchColumn()) {
            upload_response(410, ['success' => false, 'message' => 'This department folder has been deleted and can no longer receive uploads.']);
        }
    }

    $academicYear = trim((string)($_POST['academic_year'] ?? ''));
    if (!preg_match('/^(\d{4})-(\d{4})$/', $academicYear, $yearParts)
        || (int)$yearParts[2] !== (int)$yearParts[1] + 1) {
        upload_response(400, ['success' => false, 'message' => 'Select a valid academic year.']);
    }

    $semester = (string)($_POST['semester'] ?? '');
    if (!in_array($semester, ['first', 'second'], true)) {
        upload_response(400, ['success' => false, 'message' => 'Select a valid semester.']);
    }

    if (!isset($_FILES['files']) || !is_array($_FILES['files']['name'] ?? null)) {
        upload_response(400, ['success' => false, 'message' => 'Choose at least one file to upload.']);
    }

    $allowedExtensions = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar'
    ];
    $files = [];
    foreach ($_FILES['files']['name'] as $index => $submittedName) {
        if ($submittedName === '') {
            continue;
        }

        $error = (int)($_FILES['files']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'A selected file exceeds the server upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'A selected file exceeds the form upload limit.',
                UPLOAD_ERR_PARTIAL => 'A file upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server upload directory is unavailable.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not save an uploaded file.',
                UPLOAD_ERR_EXTENSION => 'The server blocked one of the selected file types.'
            ];
            throw new InvalidArgumentException($messages[$error] ?? 'A selected file could not be uploaded.');
        }

        $originalName = basename(str_replace('\\', '/', (string)$submittedName));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $size = (int)($_FILES['files']['size'][$index] ?? 0);
        $temporaryPath = (string)($_FILES['files']['tmp_name'][$index] ?? '');

        if ($originalName === '' || strlen($originalName) > 255) {
            throw new InvalidArgumentException('Each file name must be 1 to 255 characters long.');
        }
        if ($size < 1 || $size > 50 * 1024 * 1024) {
            throw new InvalidArgumentException('Each file must be smaller than 50 MB and cannot be empty.');
        }
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException(
                'Unsupported file type for ' . $originalName . '. Use PDF, Office documents, images, text, ZIP, or RAR files.'
            );
        }
        if (!is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('The server did not receive a valid uploaded file.');
        }

        $files[] = [
            'original_name' => $originalName,
            'extension' => $extension,
            'size' => $size,
            'temporary_path' => $temporaryPath
        ];
    }

    if (!$files) {
        upload_response(400, ['success' => false, 'message' => 'Choose at least one file to upload.']);
    }

    $description = trim((string)($_POST['description'] ?? ''));
    if (strlen($description) > 2000) {
        upload_response(400, ['success' => false, 'message' => 'The description must be 2,000 characters or fewer.']);
    }

    $tagsInput = json_decode((string)($_POST['tags'] ?? '[]'), true);
    if (!is_array($tagsInput)) {
        upload_response(400, ['success' => false, 'message' => 'The selected tags are invalid.']);
    }
    $tags = [];
    foreach (array_slice($tagsInput, 0, 20) as $tag) {
        if (is_string($tag) && trim($tag) !== '') {
            $tags[] = mb_substr(trim($tag), 0, 50);
        }
    }
    $tagsJson = json_encode(array_values(array_unique($tags)), JSON_UNESCAPED_UNICODE);

    $semesterName = $semester === 'first' ? 'First Semester' : 'Second Semester';
    $folderName = $academicYear . ' - ' . $semesterName;
    if ($customFolderId !== null) {
        $uploadDirectory = __DIR__ . "/../../uploads/departments/{$userDepartmentId}/custom/{$customFolderId}/{$semester}/{$academicYear}";
    } else {
        $uploadDirectory = __DIR__ . "/../../uploads/departments/{$userDepartmentId}/{$category}/{$semester}/{$academicYear}";
    }
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The server could not prepare the department upload directory.');
    }
    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException('The department upload directory is not writable.');
    }

    $movedPaths = [];
    $pdo->beginTransaction();
    try {
        if ($customFolderId !== null && $customFolder !== null) {
            $rootLock = $pdo->prepare("
                SELECT id, folder_name, folder_path, folder_level, folder_color, folder_icon, is_public, created_by
                FROM folders
                WHERE id = ? AND department_id = ? AND category IS NULL
                  AND parent_id IS NULL AND is_deleted = 0
                  AND (is_public = 1 OR created_by = ?)
                LIMIT 1 FOR UPDATE
            ");
            $rootLock->execute([$customFolderId, $userDepartmentId, $currentUser['id']]);
            $customFolder = $rootLock->fetch(PDO::FETCH_ASSOC);
            if (!$customFolder) {
                throw new InvalidArgumentException('The selected custom folder is no longer available.');
            }

            $semesterFolderStmt = $pdo->prepare("
                SELECT id FROM folders
                WHERE parent_id = ? AND department_id = ? AND folder_name = ?
                  AND category IS NULL AND is_deleted = 0
                LIMIT 1 FOR UPDATE
            ");
            $semesterFolderStmt->execute([$customFolderId, $userDepartmentId, $semesterName]);
            $folderId = (int)$semesterFolderStmt->fetchColumn();

            if (!$folderId) {
                $semesterFolderStmt = $pdo->prepare("
                    INSERT INTO folders (
                        folder_name, description, created_by, department_id, category,
                        folder_path, folder_level, folder_color, folder_icon, parent_id, is_public
                    ) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)
                ");
                $semesterFolderStmt->execute([
                    $semesterName,
                    "{$semesterName} files for {$customFolder['folder_name']}",
                    $customFolder['created_by'],
                    $userDepartmentId,
                    rtrim((string)$customFolder['folder_path'], '/') . '/' . $semester,
                    (int)$customFolder['folder_level'] + 1,
                    $customFolder['folder_color'],
                    $customFolder['folder_icon'],
                    $customFolderId,
                    $customFolder['is_public']
                ]);
                $folderId = (int)$pdo->lastInsertId();
            }
        } else {
            $folderStmt = $pdo->prepare("
                SELECT id FROM folders
                WHERE department_id = ? AND category = ? AND folder_name = ? AND is_deleted = 0
                LIMIT 1 FOR UPDATE
            ");
            $folderStmt->execute([$userDepartmentId, $category, $folderName]);
            $folderId = (int)$folderStmt->fetchColumn();

            if (!$folderId) {
                $folderPath = "/departments/{$userDepartmentId}/{$category}/{$semester}/{$academicYear}";
                $folderStmt = $pdo->prepare("
                    INSERT INTO folders (
                        folder_name, description, created_by, department_id, category,
                        folder_path, folder_level, folder_color, folder_icon
                    ) VALUES (?, ?, ?, ?, ?, ?, 2, '#10b981', 'bxs-folder')
                ");
                $folderStmt->execute([
                    $folderName,
                    "Academic files for {$semesterName} {$academicYear}",
                    $currentUser['id'],
                    $userDepartmentId,
                    $category,
                    $folderPath
                ]);
                $folderId = (int)$pdo->lastInsertId();
            }
        }

        $insertFile = $pdo->prepare("
            INSERT INTO files (
                file_name, original_name, file_path, file_size, file_type,
                mime_type, file_extension, uploaded_by, folder_id, tags,
                description, academic_year, semester
            ) VALUES (?, ?, ?, ?, 'document', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertedFiles = [];

        foreach ($files as $file) {
            $storedName = bin2hex(random_bytes(16)) . '.' . $file['extension'];
            $absolutePath = $uploadDirectory . DIRECTORY_SEPARATOR . $storedName;
            if ($customFolderId !== null) {
                $relativePath = "uploads/departments/{$userDepartmentId}/custom/{$customFolderId}/{$semester}/{$academicYear}/{$storedName}";
            } else {
                $relativePath = "uploads/departments/{$userDepartmentId}/{$category}/{$semester}/{$academicYear}/{$storedName}";
            }

            if (!move_uploaded_file($file['temporary_path'], $absolutePath)) {
                throw new RuntimeException('The server could not save ' . $file['original_name'] . '.');
            }
            $movedPaths[] = $absolutePath;

            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: 'application/octet-stream';
            $insertFile->execute([
                $storedName,
                $file['original_name'],
                $relativePath,
                $file['size'],
                $mimeType,
                $file['extension'],
                $currentUser['id'],
                $folderId,
                $tagsJson,
                $description,
                $academicYear,
                $semester
            ]);
            $fileId = (int)$pdo->lastInsertId();

            $insertedFiles[] = [
                'id' => $fileId,
                'name' => $file['original_name'],
                'size' => $file['size']
            ];
        }

        $pdo->commit();
        upload_response(200, [
            'success' => true,
            'message' => count($insertedFiles) === 1 ? 'File uploaded successfully.' : count($insertedFiles) . ' files uploaded successfully.',
            'files' => $insertedFiles,
            'category' => $category ?: null,
            'custom_folder_id' => $customFolderId,
            'custom_folder_name' => $customFolder['folder_name'] ?? null,
            'academic_year' => $academicYear,
            'semester' => $semester
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($movedPaths as $movedPath) {
            if (is_file($movedPath)) {
                unlink($movedPath);
            }
        }
        throw $e;
    }
} catch (InvalidArgumentException $e) {
    upload_response(400, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Department file upload failed: ' . $e->getMessage());
    upload_response(500, ['success' => false, 'message' => 'Upload failed because of a server error. Please try again.']);
}
