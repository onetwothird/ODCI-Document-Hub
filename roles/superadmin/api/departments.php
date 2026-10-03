<?php
/**
 * Departments endpoint for the superadmin file manager.
 * GET api/departments.php?action=list
 *   -> [ { id, department_code, department_name }, ... ]
 */

require_once __DIR__ . '/_bootstrap.php';

api_require_superadmin();

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        try {
            $stmt = $pdo->query(
                'SELECT id, department_code, department_name
                   FROM departments
                  WHERE is_active = 1
               ORDER BY department_name'
            );
            // The client expects a bare array, not an envelope.
            api_json($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            error_log('superadmin api/departments list: ' . $e->getMessage());
            api_json([]);
        }
        break;

    default:
        api_json([]);
}
