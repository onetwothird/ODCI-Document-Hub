<?php
/**
 * Folders endpoint for the superadmin file manager.
 *
 * GET  api/folders.php?action=tree
 *      -> nested array of { id, folder_name, folder_color, file_count, children[] }
 * GET  api/folders.php?action=list
 *      -> flat array of { id, folder_name, folder_path, parent_id, ... }
 * POST api/folders.php?action=create   (JSON)
 *      body: { folder_name, parent_id, department_id, description, is_public }
 *      -> { success, message }
 */

require_once __DIR__ . '/_bootstrap.php';

api_require_superadmin();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

/** Fetch every active folder with its live file count. */
function fetch_folders(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT f.id, f.folder_name, f.folder_path, f.parent_id, f.department_id,
                f.folder_color, f.folder_icon, f.folder_type, f.is_public, f.created_at,
                d.department_name,
                (SELECT COUNT(*) FROM files fl
                  WHERE fl.folder_id = f.id AND fl.is_deleted = 0) AS file_count
           FROM folders f
           LEFT JOIN departments d ON d.id = f.department_id
          WHERE f.is_deleted = 0
       ORDER BY f.folder_level, f.folder_name"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Turn a flat folder list into the nested structure the tree renderer expects. */
function build_tree(array $folders, $parentId = null): array
{
    $branch = [];
    foreach ($folders as $folder) {
        $isRoot = $parentId === null
            ? ($folder['parent_id'] === null || $folder['parent_id'] === '' || (int)$folder['parent_id'] === 0)
            : ((int)$folder['parent_id'] === (int)$parentId);

        if ($isRoot) {
            $children = build_tree($folders, $folder['id']);
            if ($children) {
                $folder['children'] = $children;
            }
            $branch[] = $folder;
        }
    }
    return $branch;
}

switch ($action) {
    case 'tree':
        try {
            api_json(build_tree(fetch_folders($pdo)));
        } catch (Throwable $e) {
            error_log('superadmin api/folders tree: ' . $e->getMessage());
            api_json([]);
        }
        break;

    case 'list':
        try {
            api_json(fetch_folders($pdo));
        } catch (Throwable $e) {
            error_log('superadmin api/folders list: ' . $e->getMessage());
            api_json([]);
        }
        break;

    case 'create':
        $input = api_input();

        $name = trim((string)($input['folder_name'] ?? ''));
        if ($name === '') {
            api_fail('Folder name is required');
        }

        $parentId      = !empty($input['parent_id'])      ? (int)$input['parent_id']      : null;
        $departmentId  = !empty($input['department_id'])  ? (int)$input['department_id']  : null;
        $description   = trim((string)($input['description'] ?? '')) ?: null;
        $isPublic      = !empty($input['is_public']) ? 1 : 0;

        // Refuse to nest a folder inside itself.
        if ($parentId !== null) {
            $check = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND is_deleted = 0');
            $check->execute([$parentId]);
            if (!$check->fetch()) {
                api_fail('Parent folder not found');
            }
        }

        $level = 0;
        $path  = '/' . $name;
        if ($parentId !== null) {
            $pStmt = $pdo->prepare('SELECT folder_level, folder_path, folder_name FROM folders WHERE id = ?');
            $pStmt->execute([$parentId]);
            if ($parent = $pStmt->fetch()) {
                $level = (int)$parent['folder_level'] + 1;
                $path  = rtrim((string)$parent['folder_path'], '/') . '/' . $parent['folder_name'] . '/' . $name;
            }
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO folders
                    (folder_name, description, created_by, parent_id, department_id,
                     folder_path, folder_level, is_public, folder_color, folder_icon)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                $description,
                $_SESSION['user_id'],
                $parentId,
                $departmentId,
                $path,
                $level,
                $isPublic,
                '#1f7a3d',
                'fa-folder',
            ]);

            logActivity($pdo, $_SESSION['user_id'], 'create_folder', 'folder', $pdo->lastInsertId(),
                'Created folder: ' . $name);

            api_ok(['id' => (int)$pdo->lastInsertId()], 'Folder created successfully');
        } catch (Throwable $e) {
            error_log('superadmin api/folders create: ' . $e->getMessage());
            api_fail('Could not create folder: ' . $e->getMessage(), 500);
        }
        break;

    default:
        api_fail('Unknown action', 400);
}
