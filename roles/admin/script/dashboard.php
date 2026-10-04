<?php
/**
 * Data provider for roles/admin/dashboard.php.
 *
 * Every figure the dashboard renders is computed here from the live database, so
 * the page can never fall back to placeholder numbers. A department admin is
 * scoped to their own department (the same rule files.php and reports.php use);
 * a super admin sees the whole system.
 *
 * Nothing in this file emits output - it only prepares variables for the view.
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth_check.php';

// requireAdmin() redirects and exits anyone who is not an admin/super admin.
$currentUser = requireAdmin();
if (!$currentUser) {
    exit;
}

/* ---------------------------------------------------------------------------
 * Scope
 * ------------------------------------------------------------------------ */
$departmentId   = !empty($currentUser['department_id']) ? (int) $currentUser['department_id'] : null;
$departmentName = !empty($currentUser['department_name']) ? $currentUser['department_name'] : 'Unassigned';
$isScoped       = $currentUser['role'] === 'admin' && $departmentId !== null;
$scopeLabel     = $isScoped ? $departmentName : 'All departments';
$scopeParams    = $isScoped ? [$departmentId] : [];

// WHERE fragments that restrict a query to the admin's department.
$filesScope    = $isScoped ? ' AND fo.department_id = ?' : '';
$foldersScope  = $isScoped ? ' AND department_id = ?' : '';
$usersScope    = $isScoped ? ' AND department_id = ?' : '';
$activityScope = $isScoped ? ' AND u.department_id = ?' : '';

/* ---------------------------------------------------------------------------
 * Formatters
 * ------------------------------------------------------------------------ */

/** Renders a byte count as a short human readable string. */
function adminDash_formatBytes($bytes, $precision = 1)
{
    $bytes = (float) $bytes;
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
    $value = $bytes / (1024 ** $power);

    return ($power === 0 ? (string) $value : number_format($value, $precision)) . ' ' . $units[$power];
}

/** Renders a timestamp as "3 hours ago". */
function adminDash_timeAgo($datetime)
{
    $timestamp = empty($datetime) ? false : strtotime($datetime);
    if ($timestamp === false) {
        return 'unknown';
    }

    $seconds = max(0, time() - $timestamp);
    $units = [
        ['minute', 60],
        ['hour', 3600],
        ['day', 86400],
        ['month', 2592000],
        ['year', 31536000],
    ];

    foreach ($units as list($label, $span)) {
        if ($seconds < $span) {
            continue;
        }
        $count = (int) floor($seconds / $span);
        return $count . ' ' . $label . ($count === 1 ? '' : 's') . ' ago';
    }

    return 'just now';
}

/** Safe percentage, clamped to 0-100. Returns 0 when there is no denominator. */
function adminDash_percent($part, $whole)
{
    $whole = (float) $whole;
    if ($whole <= 0) {
        return 0.0;
    }

    return max(0.0, min(100.0, round(((float) $part / $whole) * 100, 1)));
}

/**
 * Builds the month-over-month delta shown under a statistic.
 *
 * @param bool $higherIsBetter Set false for metrics where growth is not good
 *                            news (storage consumption), so the trend is
 *                            coloured the way an admin reads it.
 */
function adminDash_change($current, $previous, $higherIsBetter = true)
{
    $current  = (float) $current;
    $previous = (float) $previous;

    if ($previous > 0) {
        $pct = (($current - $previous) / $previous) * 100;
    } elseif ($current > 0) {
        $pct = 100.0;
    } else {
        $pct = 0.0;
    }

    $pct = round($pct, 1);

    if ($pct === 0.0) {
        return [
            'label' => 'No change vs last month',
            'icon'  => 'bx-minus',
            'class' => 'neutral',
        ];
    }

    $rising = $pct > 0;

    return [
        'label' => ($rising ? '+' : '-') . number_format(abs($pct), 1) . '% vs last month',
        'icon'  => $rising ? 'bx-trending-up' : 'bx-trending-down',
        'class' => $rising === $higherIsBetter ? 'positive' : 'negative',
    ];
}

/** Presentation details for an activity_logs row. */
function adminDash_activityMeta($action, $resourceType)
{
    // Boxicons only ships a solid (bxs-) cut of several glyphs, so the badge
    // icon always comes from that set. Every class here is verified against
    // boxicons 2.0.9.
    static $actions = [
        'login'                  => ['Signed in',                     'bxs-user',         'status-public',   'bx-right-arrow-alt'],
        'login_google'           => ['Signed in with Google',         'bxs-user',         'status-public',   'bx-right-arrow-alt'],
        'auto_login'             => ['Signed in automatically',      'bxs-user',         'status-public',   'bx-right-arrow-alt'],
        'logout'                 => ['Signed out',                    'bxs-log-out',      'status-private',  'bx-right-arrow-alt'],
        'failed_login'           => ['Failed sign-in attempt',        'bxs-error-circle', 'status-favorite', 'bx-error'],
        'session_expired'        => ['Session expired',               'bxs-time',         'status-private',  'bx-time'],
        'upload_file'            => ['Uploaded a file',               'bxs-file-pdf',     'status-completed', 'bx-check'],
        'document_upload'        => ['Uploaded a document',           'bxs-file-doc',     'status-completed', 'bx-check'],
        'file_download'          => ['Downloaded a file',             'bxs-download',     'status-completed', 'bx-check'],
        'create_folder'          => ['Created a folder',              'bxs-folder',       'status-completed', 'bx-check'],
        'create_announcement'    => ['Published an announcement',     'bxs-megaphone',    'status-public',   'bxs-megaphone'],
        'approve_user'           => ['Approved a user',               'bxs-user-plus',    'status-completed', 'bx-check'],
        'register'               => ['Registered an account',         'bxs-user-plus',    'status-favorite', 'bx-user-plus'],
        'register_google'        => ['Registered with Google',        'bxs-user-plus',    'status-favorite', 'bx-user-plus'],
        'view_faculty_list'      => ['Viewed the faculty list',       'bxs-group',        'status-private',  'bx-show'],
        'update_profile'         => ['Updated their profile',         'bxs-user-detail',  'status-private',  'bx-edit'],
        'profile_image_update'   => ['Updated their profile photo',   'bxs-camera',       'status-private',  'bx-edit'],
        'password_reset_request' => ['Requested a password reset',    'bxs-key',          'status-favorite', 'bx-error'],
        'send_message'           => ['Sent a message',                'bxs-message',      'status-private',  'bx-share'],
    ];

    if (isset($actions[$action])) {
        return $actions[$action];
    }

    // Unknown action: fall back to the resource it touched so the row still
    // reads sensibly instead of showing a raw enum value.
    static $resources = [
        'file'         => ['File activity',       'bxs-file'],
        'folder'       => ['Folder activity',     'bxs-folder'],
        'user'         => ['User activity',       'bxs-user'],
        'announcement' => ['Announcement',        'bxs-megaphone'],
        'department'   => ['Department activity', 'bxs-buildings'],
        'system'       => ['System activity',     'bxs-cog'],
    ];

    return [
        ucfirst(str_replace('_', ' ', (string) $action)),
        $resources[$resourceType][1] ?? 'bxs-cog',
        'status-private',
        'bx-cog',
    ];
}

/** Presentation details for a notification, covering both notification feeds. */
function adminDash_notificationMeta($type)
{
    static $meta = [
        // notifications.type
        'success' => ['ok',    'bx-check'],
        'warning' => ['warn',  'bx-error'],
        'error'   => ['error', 'bx-error-circle'],
        'info'    => ['info',  'bx-info-circle'],
        // post_notifications.notification_type
        'new_post'        => ['info', 'bx-news'],
        'post_comment'    => ['info', 'bx-comment'],
        'post_like'       => ['ok',   'bx-heart'],
        'comment_like'    => ['ok',   'bx-heart'],
        'comment_reply'   => ['info', 'bx-reply'],
        'post_mention'    => ['info', 'bx-at'],
        'comment_mention' => ['info', 'bx-at'],
    ];

    return $meta[$type] ?? ['info', 'bx-bell'];
}

/* ---------------------------------------------------------------------------
 * Aggregates
 * ------------------------------------------------------------------------ */
$summary = [
    'files_total'        => 0,
    'files_total_system' => 0,
    'folders_total'      => 0,
    'folders_total_system' => 0,
    'storage_bytes'      => 0,
    'users_total'        => 0,
    'users_approved'     => 0,
    'users_pending'      => 0,
    'users_active'       => 0,
    'uploads_this_week'  => 0,
];

$current = ['files' => 0, 'folders' => 0, 'storage' => 0, 'users' => 0];
$previous = ['files' => 0, 'folders' => 0, 'storage' => 0, 'users' => 0];

try {
    // Documents in scope. files.folder_id is mandatory, so the join never drops
    // a live row.
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_files,
               COALESCE(SUM(f.file_size), 0) AS total_bytes
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        WHERE f.is_deleted = 0
        $filesScope
    ");
    $stmt->execute($scopeParams);
    $row = $stmt->fetch();
    $summary['files_total']   = (int) ($row['total_files'] ?? 0);
    $summary['storage_bytes'] = (float) ($row['total_bytes'] ?? 0);

    // System-wide file count, used as the denominator for the scope share bar.
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM files WHERE is_deleted = 0");
    $stmt->execute();
    $summary['files_total_system'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_folders
        FROM folders
        WHERE is_deleted = 0
        $foldersScope
    ");
    $stmt->execute($scopeParams);
    $summary['folders_total'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM folders WHERE is_deleted = 0");
    $stmt->execute();
    $summary['folders_total_system'] = (int) $stmt->fetchColumn();

    // Faculty accounts in scope.
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_users,
               COALESCE(SUM(is_approved = 1), 0) AS approved_users,
               COALESCE(SUM(is_approved = 0), 0) AS pending_users,
               COALESCE(SUM(is_approved = 1
                            AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS active_users
        FROM users
        WHERE role = 'user'
        $usersScope
    ");
    $stmt->execute($scopeParams);
    $row = $stmt->fetch();
    $summary['users_total']    = (int) ($row['total_users'] ?? 0);
    $summary['users_approved'] = (int) ($row['approved_users'] ?? 0);
    $summary['users_pending']  = (int) ($row['pending_users'] ?? 0);
    $summary['users_active']   = (int) ($row['active_users'] ?? 0);

    /* Month-over-month deltas: last 30 days against the 30 days before that. */
    $windows = [
        'files' => [
            'table' => 'files f INNER JOIN folders fo ON f.folder_id = fo.id',
            'base'  => 'f.is_deleted = 0',
            'scope' => $filesScope,
            'date'  => 'f.uploaded_at',
            'agg'   => '1',
        ],
        'folders' => [
            'table' => 'folders',
            'base'  => 'is_deleted = 0',
            'scope' => $foldersScope,
            'date'  => 'created_at',
            'agg'   => '1',
        ],
        'users' => [
            'table' => 'users',
            'base'  => "role = 'user'",
            'scope' => $usersScope,
            'date'  => 'created_at',
            'agg'   => '1',
        ],
        'storage' => [
            'table' => 'files f INNER JOIN folders fo ON f.folder_id = fo.id',
            'base'  => 'f.is_deleted = 0',
            'scope' => $filesScope,
            'date'  => 'f.uploaded_at',
            'agg'   => 'f.file_size',
        ],
    ];

    foreach ($windows as $key => $window) {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN $window[date] >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                  THEN $window[agg] ELSE 0 END), 0) AS recent,
                COALESCE(SUM(CASE WHEN $window[date] >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                                   AND $window[date] < DATE_SUB(NOW(), INTERVAL 30 DAY)
                                  THEN $window[agg] ELSE 0 END), 0) AS prior
            FROM {$window['table']}
            WHERE $window[base]
            $window[scope]
        ");
        $stmt->execute($scopeParams);
        $row = $stmt->fetch();

        $current[$key]  = (float) ($row['recent'] ?? 0);
        $previous[$key] = (float) ($row['prior'] ?? 0);
    }

    // Recent uploads are only counted for files that still exist.
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        WHERE f.is_deleted = 0
          AND f.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        $filesScope
    ");
    $stmt->execute($scopeParams);
    $summary['uploads_this_week'] = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Admin dashboard aggregates failed: ' . $e->getMessage());
}

/* ---------------------------------------------------------------------------
 * Recent activity
 *
 * The application logs an entry on every page view, so a raw row-per-event feed
 * renders as the same line repeated. Events are grouped by action and actor
 * over the last 7 days instead: each row is the most recent occurrence and
 * carries the number of times it happened, so nothing is hidden.
 * ------------------------------------------------------------------------ */
$recentActivity = [];

try {
    $stmt = $pdo->prepare("
        SELECT al.action,
               al.resource_type,
               MAX(al.created_at) AS last_at,
               COUNT(*) AS occurrences,
               u.name,
               u.mi,
               u.surname,
               d.department_name
        FROM activity_logs al
        INNER JOIN users u ON al.user_id = u.id
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        $activityScope
        GROUP BY al.action, al.resource_type, u.id, u.name, u.mi, u.surname, d.department_name
        ORDER BY last_at DESC
        LIMIT 8
    ");
    $stmt->execute($scopeParams);

    foreach ($stmt->fetchAll() as $row) {
        [$label, $icon, $badgeClass, $badgeIcon] = adminDash_activityMeta($row['action'], $row['resource_type']);

        $recentActivity[] = [
            'label'      => $label,
            'icon'       => $icon,
            'user'       => trim($row['name'] . ' ' . (!empty($row['mi']) ? $row['mi'] . '. ' : '') . $row['surname']),
            'department' => !empty($row['department_name']) ? $row['department_name'] : 'No department',
            'when'       => adminDash_timeAgo($row['last_at']),
            'badge'      => $badgeClass,
            'badgeIcon'  => $badgeIcon,
            'badgeText'  => ucwords(str_replace('_', ' ', (string) $row['resource_type'])),
            'occurrences'=> (int) $row['occurrences'],
        ];
    }
} catch (PDOException $e) {
    error_log('Admin dashboard activity feed failed: ' . $e->getMessage());
}

/* ---------------------------------------------------------------------------
 * Recent notifications
 *
 * Both feeds are merged, matching the navbar dropdown
 * (includes/notifications_endpoint.php) so the card and the bell agree.
 * ------------------------------------------------------------------------ */
$recentNotifications = [];
$unreadNotifications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT id, 'system' AS source, title, message, type, is_read, created_at
        FROM notifications
        WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
        UNION ALL
        SELECT id, 'social' AS source,
               CASE notification_type
                   WHEN 'new_post'        THEN 'New post'
                   WHEN 'post_comment'    THEN 'Post comment'
                   WHEN 'post_like'       THEN 'Post reaction'
                   WHEN 'comment_like'    THEN 'Comment reaction'
                   WHEN 'comment_reply'   THEN 'Comment reply'
                   WHEN 'post_mention'    THEN 'Post mention'
                   WHEN 'comment_mention' THEN 'Comment mention'
                   ELSE 'Social activity'
               END AS title,
               message, notification_type AS type, is_read, created_at
        FROM post_notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->bindValue(1, (int) $currentUser['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, (int) $currentUser['id'], PDO::PARAM_INT);
    $stmt->execute();

    foreach ($stmt->fetchAll() as $row) {
        [$tone, $icon] = adminDash_notificationMeta($row['type']);

        if (empty($row['is_read'])) {
            $unreadNotifications++;
        }

        $recentNotifications[] = [
            'title'   => $row['title'],
            'message' => $row['message'],
            'when'    => adminDash_timeAgo($row['created_at']),
            'tone'    => $tone,
            'icon'    => $icon,
            'isRead'  => !empty($row['is_read']),
        ];
    }
} catch (PDOException $e) {
    error_log('Admin dashboard notifications failed: ' . $e->getMessage());
}

/* ---------------------------------------------------------------------------
 * Derived values for the view
 * ------------------------------------------------------------------------ */
$dbStatus   = 'Healthy';
$dbLatency  = null;
$dbStart    = microtime(true);
try {
    $pdo->query('SELECT 1');
    $dbLatency = max(1, (int) round((microtime(true) - $dbStart) * 1000));
} catch (PDOException $e) {
    $dbStatus = 'Unavailable';
    error_log('Admin dashboard database health check failed: ' . $e->getMessage());
}

// The uploads volume is the real storage budget for stored documents.
$quotaBytes = (float) (@disk_total_space(UPLOAD_DIR) ?: 0);
$freeBytes  = (float) (@disk_free_space(UPLOAD_DIR) ?: 0);
$storagePct = $quotaBytes > 0
    ? adminDash_percent($summary['storage_bytes'], $quotaBytes)
    : 0.0;

$changes = [
    'files'   => adminDash_change($current['files'], $previous['files']),
    'folders' => adminDash_change($current['folders'], $previous['folders']),
    'storage' => adminDash_change($current['storage'], $previous['storage'], false),
];

$bars = [
    'files'   => adminDash_percent($summary['files_total'], $summary['files_total_system']),
    'folders' => adminDash_percent($summary['folders_total'], $summary['folders_total_system']),
    'storage' => $storagePct,
    'users'   => adminDash_percent($summary['users_active'], $summary['users_approved']),
];

/* ---------------------------------------------------------------------------
 * Account information
 *
 * getCurrentUser() refreshes users.last_login on every request, so the column
 * tracks the last page load rather than the last sign-in. $_SESSION['login_time']
 * is written once at authentication and is therefore the honest "last login".
 * ------------------------------------------------------------------------ */
$loginTimestamp = !empty($_SESSION['login_time'])
    ? (int) $_SESSION['login_time']
    : strtotime((string) $currentUser['last_login']);

$account = [
    'department'  => $departmentName,
    'employeeId'  => !empty($currentUser['employee_id']) ? $currentUser['employee_id'] : 'Not assigned',
    'lastLogin'   => $loginTimestamp ? date('M j, Y, g:i A', $loginTimestamp) : 'No record',
    'memberSince' => !empty($currentUser['created_at']) ? date('M j, Y', strtotime($currentUser['created_at'])) : 'Unknown',
];

if (!empty($currentUser['account_locked_until']) && strtotime($currentUser['account_locked_until']) > time()) {
    $account['status'] = ['Locked', 'bxs-lock', 'critical'];
} elseif (empty($currentUser['is_approved'])) {
    $account['status'] = ['Pending Approval', 'bxs-time', 'warning'];
} elseif (!empty($currentUser['is_restricted'])) {
    $account['status'] = ['Restricted', 'bxs-error-circle', 'warning'];
} else {
    $account['status'] = ['Active', 'bx-check-circle', 'success'];
}
