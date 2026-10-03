<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Ensure super admin access
$currentUser = requireSuperAdmin();
if (!$currentUser) {
    header('Location: ../../login.php');
    exit();
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['ajax']) {
            case 'get_logs':
                echo json_encode(getActivityLogs($pdo, $_GET));
                break;
                
            case 'get_stats':
                echo json_encode(getActivityStats($pdo));
                break;
                
            case 'export_logs':
                exportLogs($pdo, $_GET);
                break;
                
            case 'clear_old_logs':
                $days = intval($_GET['days'] ?? 30);
                echo json_encode(clearOldLogs($pdo, $days));
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Functions for handling activity logs
function getActivityLogs($pdo, $params) {
    $page = max(1, intval($params['page'] ?? 1));
    $limit = min(100, max(10, intval($params['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $conditions = ['1=1'];
    $bindParams = [];
    
    // Date range filter
    if (!empty($params['date_from'])) {
        $conditions[] = "al.created_at >= ?";
        $bindParams[] = $params['date_from'] . ' 00:00:00';
    }
    
    if (!empty($params['date_to'])) {
        $conditions[] = "al.created_at <= ?";
        $bindParams[] = $params['date_to'] . ' 23:59:59';
    }
    
    // User filter
    if (!empty($params['user_id'])) {
        $conditions[] = "al.user_id = ?";
        $bindParams[] = $params['user_id'];
    }
    
    // Action filter
    if (!empty($params['action'])) {
        $conditions[] = "al.action LIKE ?";
        $bindParams[] = '%' . $params['action'] . '%';
    }
    
    // Resource type filter
    if (!empty($params['resource_type'])) {
        $conditions[] = "al.resource_type = ?";
        $bindParams[] = $params['resource_type'];
    }
    
    // Search filter
    if (!empty($params['search'])) {
        $conditions[] = "(al.description LIKE ? OR al.action LIKE ? OR u.username LIKE ? OR u.name LIKE ? OR u.surname LIKE ?)";
        $searchTerm = '%' . $params['search'] . '%';
        $bindParams = array_merge($bindParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $conditions);
    
    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE $whereClause
    ";
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($bindParams);
    $total = $countStmt->fetch()['total'];
    
    // Get logs
    $query = "
        SELECT 
            al.*,
            u.username,
            u.name,
            u.surname,
            u.role,
            d.department_name,
            d.department_code
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE $whereClause
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(array_merge($bindParams, [$limit, $offset]));
    $logs = $stmt->fetchAll();
    
    // Format logs for display
    foreach ($logs as &$log) {
        $log['user_full_name'] = trim(($log['name'] ?? '') . ' ' . ($log['surname'] ?? ''));
        if (empty($log['user_full_name'])) {
            $log['user_full_name'] = $log['username'] ?? 'Unknown User';
        }
        
        $log['formatted_date'] = date('Y-m-d H:i:s', strtotime($log['created_at']));
        $log['time_ago'] = timeAgo($log['created_at']);
        
        // Parse metadata if it exists
        if (!empty($log['metadata'])) {
            $log['metadata_parsed'] = json_decode($log['metadata'], true);
        }
        
        // Get action badge class
        $log['action_badge'] = getActionBadgeClass($log['action']);
        
        // Get resource type icon
        $log['resource_icon'] = getResourceIcon($log['resource_type']);
    }
    
    return [
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => $total,
            'limit' => $limit
        ]
    ];
}

function getActivityStats($pdo) {
    // Get basic stats
    $stats = [];
    
    // Total activities today
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $stats['today_count'] = $stmt->fetch()['count'];
    
    // Total activities this week
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE WEEK(created_at) = WEEK(NOW())");
    $stmt->execute();
    $stats['week_count'] = $stmt->fetch()['count'];
    
    // Total activities this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $stmt->execute();
    $stats['month_count'] = $stmt->fetch()['count'];
    
    // Most active users (top 5)
    $stmt = $pdo->prepare("
        SELECT 
            u.username,
            u.name,
            u.surname,
            COUNT(*) as activity_count
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY al.user_id
        ORDER BY activity_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stats['top_users'] = $stmt->fetchAll();
    
    // Activity by resource type (last 7 days)
    $stmt = $pdo->prepare("
        SELECT 
            resource_type,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY resource_type
        ORDER BY count DESC
    ");
    $stmt->execute();
    $stats['resource_breakdown'] = $stmt->fetchAll();
    
    // Activity timeline (last 24 hours)
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    $stmt->execute();
    $stats['hourly_activity'] = $stmt->fetchAll();
    
    // Most common actions (last 7 days)
    $stmt = $pdo->prepare("
        SELECT 
            action,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY action
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['top_actions'] = $stmt->fetchAll();
    
    return $stats;
}

function clearOldLogs($pdo, $days) {
    try {
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        $affected = $stmt->rowCount();
        
        // Log this action
        logActivity($pdo, $_SESSION['user_id'], 'clear_old_logs', 'system', null, "Cleared $affected old log entries older than $days days");
        
        return [
            'success' => true,
            'message' => "Successfully cleared $affected old log entries",
            'affected_rows' => $affected
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error clearing logs: ' . $e->getMessage()
        ];
    }
}

function exportLogs($pdo, $params) {
    // Similar query as getActivityLogs but without pagination
    $conditions = ['1=1'];
    $bindParams = [];
    
    if (!empty($params['date_from'])) {
        $conditions[] = "al.created_at >= ?";
        $bindParams[] = $params['date_from'] . ' 00:00:00';
    }
    
    if (!empty($params['date_to'])) {
        $conditions[] = "al.created_at <= ?";
        $bindParams[] = $params['date_to'] . ' 23:59:59';
    }
    
    if (!empty($params['user_id'])) {
        $conditions[] = "al.user_id = ?";
        $bindParams[] = $params['user_id'];
    }
    
    if (!empty($params['resource_type'])) {
        $conditions[] = "al.resource_type = ?";
        $bindParams[] = $params['resource_type'];
    }
    
    if (!empty($params['search'])) {
        $conditions[] = "(al.description LIKE ? OR al.action LIKE ? OR u.username LIKE ? OR u.name LIKE ? OR u.surname LIKE ?)";
        $searchTerm = '%' . $params['search'] . '%';
        $bindParams = array_merge($bindParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $conditions);
    
    $query = "
        SELECT 
            al.id,
            al.created_at,
            u.username,
            CONCAT(u.name, ' ', COALESCE(u.surname, '')) as full_name,
            u.role,
            d.department_name,
            al.action,
            al.resource_type,
            al.resource_id,
            al.description,
            al.ip_address
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE $whereClause
        ORDER BY al.created_at DESC
        LIMIT 10000
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($bindParams);
    $logs = $stmt->fetchAll();
    
    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID', 'Date/Time', 'Username', 'Full Name', 'Role', 'Department',
        'Action', 'Resource Type', 'Resource ID', 'Description', 'IP Address'
    ]);
    
    // CSV data
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['created_at'],
            $log['username'],
            $log['full_name'],
            $log['role'],
            $log['department_name'],
            $log['action'],
            $log['resource_type'],
            $log['resource_id'],
            $log['description'],
            $log['ip_address']
        ]);
    }
    
    fclose($output);
}

// Helper functions
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 604800) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

function getActionBadgeClass($action) {
    $loginActions = ['login', 'logout', 'auto_login'];
    $createActions = ['create', 'upload', 'add'];
    $updateActions = ['update', 'edit', 'modify'];
    $deleteActions = ['delete', 'remove'];
    $securityActions = ['failed_login', 'account_locked', 'unauthorized_access'];
    
    if (in_array($action, $loginActions)) return 'login';
    if (in_array($action, $createActions)) return 'create';
    if (in_array($action, $updateActions)) return 'update';
    if (in_array($action, $deleteActions)) return 'delete';
    if (in_array($action, $securityActions)) return 'security';
    
    return 'default';
}

function getResourceIcon($resourceType) {
    $icons = [
        'file' => 'fas fa-file',
        'folder' => 'fas fa-folder',
        'user' => 'fas fa-user',
        'announcement' => 'fas fa-bullhorn',
        'department' => 'fas fa-building',
        'system' => 'fas fa-cogs'
    ];
    
    return $icons[$resourceType] ?? 'fas fa-question-circle';
}

// Get users for filter dropdown
$usersStmt = $pdo->prepare("
    SELECT id, username, name, surname 
    FROM users 
    WHERE is_approved = 1 
    ORDER BY username
");
$usersStmt->execute();
$users = $usersStmt->fetchAll();

// Get resource types
$resourceTypesStmt = $pdo->prepare("
    SELECT DISTINCT resource_type 
    FROM activity_logs 
    WHERE resource_type IS NOT NULL 
    ORDER BY resource_type
");
$resourceTypesStmt->execute();
$resourceTypes = $resourceTypesStmt->fetchAll();
?>