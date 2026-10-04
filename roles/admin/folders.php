<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

/* ==========================================================================
   REQUEST STATE
   --------------------------------------------------------------------------
   Every filter is whitelisted before it reaches SQL. The values go into the
   query as bound parameters, but an unvalidated ?folder_type=whatever would
   still be echoed straight back into the <option selected> comparison and into
   pagination links, so the whitelist is what keeps the page honest.
   ========================================================================== */

const FOLDER_TYPES  = ['category', 'custom', 'system'];
const FOLDER_STATUS = ['active', 'archived', 'hidden'];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$search = trim((string)($_GET['search'] ?? ''));
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$department_filter = trim((string)($_GET['department'] ?? ''));
$folder_type_filter = trim((string)($_GET['folder_type'] ?? ''));
$status_filter      = trim((string)($_GET['status'] ?? ''));

if (!in_array($folder_type_filter, FOLDER_TYPES, true)) {
    $folder_type_filter = '';
}
if (!in_array($status_filter, FOLDER_STATUS, true)) {
    $status_filter = '';
}

$has_active_filters = $search !== ''
    || $department_filter !== ''
    || $folder_type_filter !== ''
    || $status_filter !== '';


/* ==========================================================================
   ACTIONS
   --------------------------------------------------------------------------
   The page now redirects after a POST instead of rendering straight back. That
   is not cosmetic: toggle_public used to run on every request, so pressing F5
   after changing a folder's visibility flipped it straight back. A
   POST-redirect-GET makes each action happen exactly once.

   Flash messages travel in the session so the message survives the redirect.
   ========================================================================== */

if (!empty($_SESSION['folders_flash'])) {
    $flash = $_SESSION['folders_flash'];
    unset($_SESSION['folders_flash']);

    if (($flash['type'] ?? '') === 'success') {
        $success_message = $flash['text'];
    } else {
        $error_message = $flash['text'];
    }
}

/** Rebuild the current filter state as a querystring, dropping empty values. */
function folders_query(array $state): string
{
    $params = array_filter([
        'search'      => $state['search']      ?? '',
        'department'  => $state['department']  ?? '',
        'folder_type' => $state['folder_type'] ?? '',
        'status'      => $state['status']      ?? '',
        'page'        => (int)($state['page'] ?? 1) > 1 ? (int)$state['page'] : '',
    ], static fn($v) => $v !== '' && $v !== null);

    return $params ? '?' . http_build_query($params) : '';
}

/** Hidden inputs that carry the filters through an action form and the redirect. */
function folders_state_fields(array $state, string $csrf): string
{
    $out = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf, ENT_QUOTES) . '">';
    foreach (['search', 'department', 'folder_type', 'status', 'page'] as $key) {
        $out .= '<input type="hidden" name="' . $key . '" value="'
              . htmlspecialchars((string)($state[$key] ?? ''), ENT_QUOTES) . '">';
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf   = (string)($_POST['csrf_token'] ?? '');
    $action = (string)($_POST['action'] ?? '');
    $folder_id = (int)($_POST['folder_id'] ?? 0);

    // Filters travel with the POST so the redirect can put the user back where
    // they were instead of dumping them on page 1 with no filters.
    $state = [
        'search'      => substr((string)($_POST['search'] ?? ''), 0, 100),
        'department'  => (string)($_POST['department'] ?? ''),
        'folder_type' => (string)($_POST['folder_type'] ?? ''),
        'status'      => (string)($_POST['status'] ?? ''),
        'page'        => (int)($_POST['page'] ?? 1),
    ];

    // Reject the request before touching the database.
    if (!hash_equals((string)($_SESSION['folders_csrf'] ?? ''), $csrf)) {
        $_SESSION['folders_flash'] = [
            'type' => 'error',
            'text' => 'Your session expired while the page was open. Please try again.',
        ];
        header('Location: folders.php' . folders_query($state));
        exit;
    }

    if ($action === 'delete' && $folder_id > 0) {
        // Refuse to orphan anything: a folder holding files or subfolders is
        // not deletable, and the UI says so rather than failing silently.
        $check_stmt = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM files   WHERE folder_id = ? AND is_deleted = 0) AS file_count,
                (SELECT COUNT(*) FROM folders WHERE parent_id = ? AND is_deleted = 0) AS subfolder_count'
        );
        $check_stmt->execute([$folder_id, $folder_id]);
        $counts = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$counts['file_count'] > 0 || (int)$counts['subfolder_count'] > 0) {
            $parts = [];
            if ((int)$counts['file_count'] > 0) {
                $parts[] = number_format((int)$counts['file_count']) . ' file'
                        . ((int)$counts['file_count'] === 1 ? '' : 's');
            }
            if ((int)$counts['subfolder_count'] > 0) {
                $parts[] = number_format((int)$counts['subfolder_count']) . ' subfolder'
                        . ((int)$counts['subfolder_count'] === 1 ? '' : 's');
            }

            $_SESSION['folders_flash'] = [
                'type' => 'error',
                'text' => 'Cannot delete this folder - it still contains ' . implode(' and ', $parts)
                        . '. Empty or move them first.',
            ];
        } else {
            $delete_stmt = $pdo->prepare(
                'UPDATE folders SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND is_deleted = 0'
            );
            $delete_stmt->execute([$_SESSION['user_id'], $folder_id]);

            $_SESSION['folders_flash'] = $delete_stmt->rowCount()
                ? ['type' => 'success', 'text' => 'Folder deleted.']
                : ['type' => 'error', 'text' => 'That folder no longer exists.'];
        }
    }

    if ($action === 'toggle_public' && $folder_id > 0) {
        $toggle_stmt = $pdo->prepare('UPDATE folders SET is_public = NOT is_public WHERE id = ? AND is_deleted = 0');
        $toggle_stmt->execute([$folder_id]);

        if ($toggle_stmt->rowCount()) {
            $now_stmt = $pdo->prepare('SELECT is_public FROM folders WHERE id = ?');
            $now_stmt->execute([$folder_id]);
            $is_public = (int)$now_stmt->fetchColumn();

            $_SESSION['folders_flash'] = [
                'type' => 'success',
                'text' => 'Folder is now ' . ($is_public ? 'public' : 'private') . '.',
            ];
        } else {
            $_SESSION['folders_flash'] = ['type' => 'error', 'text' => 'That folder no longer exists.'];
        }
    }

    if ($action === 'change_status' && $folder_id > 0) {
        $status = (string)($_POST['status'] ?? '');

        // Whitelist. Without this the column accepted any string, so a crafted
        // POST could put the enum into an empty string and every status badge on
        // the page would fall through to its default branch.
        if (!in_array($status, FOLDER_STATUS, true)) {
            $_SESSION['folders_flash'] = ['type' => 'error', 'text' => 'Unknown folder status.'];
        } else {
            $status_stmt = $pdo->prepare(
                'UPDATE folders SET folder_status = ? WHERE id = ? AND is_deleted = 0'
            );
            $status_stmt->execute([$status, $folder_id]);

            $_SESSION['folders_flash'] = $status_stmt->rowCount()
                ? ['type' => 'success', 'text' => 'Folder status set to ' . $status . '.']
                : ['type' => 'error', 'text' => 'That folder no longer exists, or it already had that status.'];
        }
    }

    header('Location: folders.php' . folders_query($state));
    exit;
}

/* CSRF token for the action forms. Generated once per session. */
if (empty($_SESSION['folders_csrf'])) {
    $_SESSION['folders_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['folders_csrf'];

$state = [
    'search'      => $search,
    'department'  => $department_filter,
    'folder_type' => $folder_type_filter,
    'status'      => $status_filter,
    'page'        => $page,
];


/* ==========================================================================
   QUERIES
   ========================================================================== */

$where_conditions = ['f.is_deleted = 0'];
$params = [];

if ($search !== '') {
    $where_conditions[] = '(f.folder_name LIKE ? OR f.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($department_filter !== '') {
    $where_conditions[] = 'f.department_id = ?';
    $params[] = $department_filter;
}

if ($folder_type_filter !== '') {
    $where_conditions[] = 'f.folder_type = ?';
    $params[] = $folder_type_filter;
}

// Only filter on status if the column is actually there. It was added by
// database/migrations/002_folders_add_status.sql; if a deployment has not run
// that migration the page still works, it just loses this one filter instead of
// fataling on an unknown column.
$has_status_column = (bool)$pdo->query(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'folders' AND COLUMN_NAME = 'folder_status'"
)->fetchColumn();

if ($has_status_column && $status_filter !== '') {
    $where_conditions[] = 'f.folder_status = ?';
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Pagination needs the LEFT JOINs to stay identical to the list query,
// otherwise the count can disagree with the rows actually shown.
$from_clause = ' FROM folders f
                 LEFT JOIN departments d ON f.department_id = d.id
                 LEFT JOIN users u       ON f.created_by = u.id';

$count_stmt = $pdo->prepare('SELECT COUNT(*)' . $from_clause . ' WHERE ' . $where_clause);
$count_stmt->execute($params);
$total_folders = (int)$count_stmt->fetchColumn();
$total_pages   = (int)ceil($total_folders / $limit);

// A filter change can leave the visitor on a page number that no longer exists,
// e.g. page 4 of an unfiltered list. Nudge them back onto a real page.
if ($total_pages > 0 && $page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

$folders_stmt = $pdo->prepare(
    'SELECT f.*,
            d.department_name,
            d.department_code,
            u.username,
            u.name  AS creator_name,
            u.surname,
            CONCAT(COALESCE(u.name, ""), " ", COALESCE(u.mi, ""), " ", COALESCE(u.surname, "")) AS creator_full_name,
            pf.folder_name AS parent_folder_name' . $from_clause . '
            LEFT JOIN folders pf ON f.parent_id = pf.id
            WHERE ' . $where_clause . '
            ORDER BY f.created_at DESC
            LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
);
$folders_stmt->execute($params);
$folders = $folders_stmt->fetchAll(PDO::FETCH_ASSOC);

$dept_stmt = $pdo->query('SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name');
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

/* One grouped pass for the summary row. Computing these from $folders would
   make the tiles describe the current page rather than the current filter,
   which is the kind of quiet lie that makes a dashboard untrustworthy. */
$summary_stmt = $pdo->prepare(
    'SELECT
        COUNT(*)                                                   AS total,
        COALESCE(SUM(is_public), 0)                                AS public_count,
        COALESCE(SUM(CASE WHEN is_public = 0 THEN 1 ELSE 0 END), 0) AS private_count,
        COALESCE(SUM(file_count), 0)                               AS total_files,
        COALESCE(SUM(folder_size), 0)                              AS total_size
     FROM folders f
     WHERE ' . $where_clause
);
$summary_stmt->execute($params);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];


/* ==========================================================================
   HELPERS
   ========================================================================== */

function formatFileSize($bytes)
{
    $bytes = (int)$bytes;
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Map whatever is in folders.folder_icon onto a glyph that actually exists.
 *
 * The column is inconsistent by history, not by accident:
 *
 *   - Most rows were seeded with Font Awesome 5 names ("fa-chart-line",
 *     "fa-clock", "fa-users"). The old page rendered them as
 *     `class="fas fa-chart-line"`, which happened to work under Font Awesome 6
 *     for most of them but not for "fa-file-alt" (renamed to fa-file-lines).
 *   - Newer rows were already seeded with a Boxicons name ("bxs-folder"), and
 *     the old page still prefixed them with "fas", so every one of those tiles
 *     rendered as a blank coloured square.
 *
 * So this page uses Boxicons like the rest of the admin area, and translates the
 * stored names. Every target below was checked against boxicons@2.0.9; an
 * unknown name falls back to a plain folder rather than an empty tile.
 */
function folder_icon_class(?string $stored): string
{
    $stored = strtolower(trim((string)$stored));

    // Already a Boxicons name - pass it through untouched.
    if (preg_match('/^bxs?-[a-z0-9-]+$/', $stored)) {
        return $stored;
    }

    static $map = [
        'fa-folder'         => 'bxs-folder',
        'fa-folder-open'    => 'bxs-folder-open',
        'fa-folder-plus'    => 'bxs-folder-plus',
        'fa-chart-line'     => 'bxs-chart',
        'fa-chart-bar'      => 'bxs-chart',
        'fa-line-chart'     => 'bxs-chart',
        'fa-file'           => 'bxs-file',
        'fa-file-alt'       => 'bxs-file-doc',
        'fa-file-lines'     => 'bxs-file-doc',
        'fa-file-signature' => 'bxs-file-doc',
        'fa-file-invoice'   => 'bxs-file-doc',
        'fa-clock'          => 'bxs-time',
        'fa-users'          => 'bxs-group',
        'fa-user'           => 'bxs-user',
        'fa-book'           => 'bxs-book',
        'fa-graduation-cap' => 'bxs-magic-hat',
        'fa-award'          => 'bxs-award',
        'fa-medal'          => 'bxs-medal',
        'fa-table'          => 'bxs-spreadsheet',
        'fa-th'             => 'bxs-grid',
        'fa-th-large'       => 'bxs-grid',
        'fa-calendar'       => 'bxs-calendar',
        'fa-archive'        => 'bxs-archive',
        'fa-clipboard'      => 'bxs-clipboard',
        'fa-sticky-note'    => 'bxs-note',
        'fa-envelope'       => 'bxs-envelope',
        'fa-paperclip'      => 'bxs-attachment',
        'fa-star'           => 'bxs-star',
        'fa-heart'          => 'bxs-heart',
        'fa-bookmark'       => 'bxs-bookmark',
        'fa-tag'            => 'bxs-tag',
        'fa-cog'            => 'bx-cog',
        'fa-wrench'         => 'bxs-wrench',
        'fa-cube'           => 'bxs-cube',
        'fa-home'           => 'bxs-home',
        'fa-building'       => 'bxs-building',
        'fa-briefcase'      => 'bxs-briefcase',
        'fa-image'          => 'bxs-image',
        'fa-camera'         => 'bxs-camera',
        'fa-video'          => 'bxs-video',
        'fa-music'          => 'bxs-music',
    ];

    return $map[$stored] ?? 'bxs-folder';
}

/**
 * Validate folders.folder_color before it goes into a style attribute.
 *
 * The value is user-supplied and lands inside `style="background-color: ..."`,
 * so anything that is not a plain hex colour is discarded rather than escaped -
 * escaping would not help, because the injection point is CSS, not HTML.
 */
function folder_hex_color(?string $stored, string $fallback = '#0f6b3d'): string
{
    $stored = trim((string)$stored);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $stored) ? $stored : $fallback;
}

/** Whitelist a status for use as a CSS modifier class. */
function folder_status_class(?string $status): string
{
    return in_array($status, FOLDER_STATUS, true) ? (string)$status : 'active';
}

function folder_status_label(string $status): string
{
    return ucfirst($status);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folder Management - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">

    <!-- No Bootstrap and no Font Awesome.

         This page used to load both. Bootstrap supplied the grid, badges,
         pagination, dropdowns and form styling, which put a second design
         system on a page the shared CVSU theme already styles, and its dropdown
         menus were clipped by the cards they lived in (overflow:hidden).

         Font Awesome was worse than useless here: the shared sidebar and navbar
         are built entirely from Boxicons (34 icon references between them) and
         Boxicons was never loaded here, so every icon in the sidebar, the
         navbar and the breadcrumb was an empty <i> element. The folder icons had
         the mirror-image problem - folders.folder_icon is seeded with a mix of
         Font Awesome 5 names and Boxicons names, so each kind broke the other.
         One icon system, loaded once, fixes all of it. -->
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/folders.css?v=<?= time() ?>">

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Folder Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Admin</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="folders.php">All Folders</a></li>
                    </ul>
                </div>
            </div>

            <!-- Summary tiles. One grouped query, so these describe the current
                 filter rather than the current page of results. -->
            <div class="folders-stats">
                <article class="folders-stat">
                    <span class="folders-stat-icon"><i class="bx bxs-folder"></i></span>
                    <div class="folders-stat-body">
                        <p class="folders-stat-label">Folders</p>
                        <div class="folders-stat-value"><?php echo number_format((int)($summary['total'] ?? 0)); ?></div>
                    </div>
                </article>

                <article class="folders-stat accent-gold">
                    <span class="folders-stat-icon"><i class="bx bx-globe"></i></span>
                    <div class="folders-stat-body">
                        <p class="folders-stat-label">Public</p>
                        <div class="folders-stat-value"><?php echo number_format((int)($summary['public_count'] ?? 0)); ?></div>
                    </div>
                </article>

                <article class="folders-stat">
                    <span class="folders-stat-icon"><i class="bx bxs-lock"></i></span>
                    <div class="folders-stat-body">
                        <p class="folders-stat-label">Private</p>
                        <div class="folders-stat-value"><?php echo number_format((int)($summary['private_count'] ?? 0)); ?></div>
                    </div>
                </article>

                <article class="folders-stat accent-gold">
                    <span class="folders-stat-icon"><i class="bx bxs-file"></i></span>
                    <div class="folders-stat-body">
                        <p class="folders-stat-label">Files Held</p>
                        <div class="folders-stat-value"><?php echo number_format((int)($summary['total_files'] ?? 0)); ?></div>
                    </div>
                </article>

                <article class="folders-stat">
                    <span class="folders-stat-icon"><i class="bx bxs-hdd"></i></span>
                    <div class="folders-stat-body">
                        <p class="folders-stat-label">Total Size</p>
                        <div class="folders-stat-value"><?php echo formatFileSize($summary['total_size'] ?? 0); ?></div>
                    </div>
                </article>
            </div>

            <!-- Alerts. Dismissing these used to need Bootstrap's JS
                 (data-bs-dismiss + new bootstrap.Alert). The dismissal is now a
                 few lines of local script, so the panel does not carry a
                 framework for one interaction. -->
            <?php if (isset($success_message)): ?>
                <div class="alert folders-alert is-success" role="status">
                    <i class="bx bxs-check-circle"></i>
                    <span><?php echo htmlspecialchars((string)$success_message); ?></span>
                    <button type="button" class="btn-close" data-dismiss-alert aria-label="Dismiss"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert folders-alert is-error" role="alert">
                    <i class="bx bxs-error-circle"></i>
                    <span><?php echo htmlspecialchars((string)$error_message); ?></span>
                    <button type="button" class="btn-close" data-dismiss-alert aria-label="Dismiss"></button>
                </div>
            <?php endif; ?>

            <!-- Filters. Was Bootstrap .row.g-3 / .col-md-*; now a CSS grid on
                 the shared spacing scale. -->
            <section class="folders-section">
                <div class="folders-section-head">
                    <h2><i class="bx bx-filter"></i> Refine Results</h2>
                    <span class="folders-section-note">
                        <?php echo number_format($total_folders); ?> folder<?php echo $total_folders === 1 ? '' : 's'; ?> in view
                    </span>
                </div>
                <div class="folders-section-body">
                    <form method="GET" action="folders.php" class="folders-filter-grid">
                        <div class="folders-field folders-search-field">
                            <label for="filterSearch">Search</label>
                            <div class="folders-search">
                                <i class="bx bx-search"></i>
                                <input type="text" id="filterSearch" class="form-control" name="search"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Folder name or description">
                            </div>
                        </div>

                        <div class="folders-field">
                            <label for="filterType">Folder Type</label>
                            <select id="filterType" class="form-select" name="folder_type">
                                <option value="">All Types</option>
                                <?php foreach (FOLDER_TYPES as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"
                                        <?php echo $folder_type_filter === $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="folders-field">
                            <label for="filterDepartment">Department</label>
                            <select id="filterDepartment" class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars((string)$dept['id']); ?>"
                                        <?php echo $department_filter === (string)$dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)$dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php /* The status filter only appears once the column
                                 exists, so a deployment that has not run
                                 002_folders_add_status.sql is not offered a
                                 filter that would error. */ ?>
                        <?php if ($has_status_column): ?>
                            <div class="folders-field">
                                <label for="filterStatus">Status</label>
                                <select id="filterStatus" class="form-select" name="status">
                                    <option value="">All Statuses</option>
                                    <?php foreach (FOLDER_STATUS as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status); ?>"
                                            <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(folder_status_label($status)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="folders-field folders-actions-field">
                            <span class="folders-field-label" aria-hidden="true">Actions</span>
                            <div class="folders-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-filter"></i> Apply
                                </button>
                                <?php /* A link, not a second submit, so it cannot
                                         be mistaken for another way to apply the
                                         form. Hidden when nothing is filtered. */ ?>
                                <a href="folders.php" class="btn btn-reset"
                                   <?php echo $has_active_filters ? '' : 'hidden'; ?>>
                                    <i class="bx bx-reset"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <!-- View toggle + result count -->
            <div class="folders-toolbar">
                <div class="view-toggle" role="group" aria-label="Choose how folders are displayed">
                    <input type="radio" name="view" id="view-grid" value="grid" autocomplete="off" checked>
                    <label for="view-grid"><i class="bx bxs-grid"></i> Grid</label>

                    <input type="radio" name="view" id="view-list" value="list" autocomplete="off">
                    <label for="view-list"><i class="bx bx-list-ul"></i> List</label>
                </div>

                <p class="folders-count">
                    <i class="bx bx-info-circle"></i>
                    Showing <?php echo count($folders); ?> of <?php echo number_format($total_folders); ?> folders
                </p>
            </div>

            <!-- ================= GRID VIEW ================= -->
            <section id="view-grid-panel" class="folders-view" aria-labelledby="view-grid">
                <?php if ($folders): ?>
                    <div class="folder-grid">
                        <?php foreach ($folders as $folder): ?>
                            <?php
                            $status = folder_status_class($has_status_column ? ($folder['folder_status'] ?? 'active') : 'active');
                            $icon   = folder_icon_class($folder['folder_icon'] ?? '');
                            $colour = folder_hex_color($folder['folder_color'] ?? '');
                            ?>
                            <article class="folder-tile">
                                <div class="folder-tile-top">
                                    <span class="folder-tile-icon" style="background-color: <?php echo htmlspecialchars($colour); ?>;">
                                        <i class="bx <?php echo htmlspecialchars($icon); ?>"></i>
                                    </span>
                                    <div class="folder-tile-heading">
                                        <h3 class="folder-tile-name"><?php echo htmlspecialchars((string)$folder['folder_name']); ?></h3>
                                        <?php if (!empty($folder['description'])): ?>
                                            <span class="folder-tile-sub"><?php echo htmlspecialchars((string)$folder['description']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="folder-tile-chips">
                                    <span class="folder-chip is-<?php echo $status; ?>">
                                        <i class="bx <?php echo $status === 'active' ? 'bxs-check-circle' : ($status === 'archived' ? 'bxs-archive' : 'bx-eye'); ?>"></i>
                                        <?php echo htmlspecialchars(folder_status_label($status)); ?>
                                    </span>
                                    <span class="folder-chip <?php echo $folder['is_public'] ? 'is-public' : ''; ?>">
                                        <i class="bx <?php echo $folder['is_public'] ? 'bx-globe' : 'bxs-lock'; ?>"></i>
                                        <?php echo $folder['is_public'] ? 'Public' : 'Private'; ?>
                                    </span>
                                    <?php if (!empty($folder['department_code'])): ?>
                                        <span class="folder-chip">
                                            <i class="bx bxs-building"></i>
                                            <?php echo htmlspecialchars((string)$folder['department_code']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="folder-tile-figures">
                                    <div class="folder-tile-figure">
                                        <div class="folder-tile-figure-value"><?php echo number_format((int)$folder['file_count']); ?></div>
                                        <div class="folder-tile-figure-label">Files</div>
                                    </div>
                                    <div class="folder-tile-figure">
                                        <div class="folder-tile-figure-value"><?php echo htmlspecialchars(formatFileSize($folder['folder_size'] ?? 0)); ?></div>
                                        <div class="folder-tile-figure-label">Size</div>
                                    </div>
                                </div>

                                <div class="folder-tile-meta">
                                    <div class="folder-tile-meta-row">
                                        <i class="bx bxs-user"></i>
                                        <span>
                                            <span class="folder-tile-meta-key">Created by</span>
                                            <?php echo htmlspecialchars(trim((string)($folder['creator_full_name'] ?? '')) ?: 'Unknown'); ?>
                                        </span>
                                    </div>
                                    <div class="folder-tile-meta-row">
                                        <i class="bx bxs-calendar"></i>
                                        <span>
                                            <span class="folder-tile-meta-key">Created</span>
                                            <?php echo htmlspecialchars(date('M j, Y', strtotime((string)$folder['created_at']))); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($folder['parent_folder_name'])): ?>
                                        <div class="folder-tile-meta-row">
                                            <i class="bx bx-folder"></i>
                                            <span>
                                                <span class="folder-tile-meta-key">Inside</span>
                                                <?php echo htmlspecialchars((string)$folder['parent_folder_name']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php /* Actions. Always visible: the old version
                                         faded them in on :hover, which made the
                                         whole set unreachable by touch. There is
                                         no dropdown menu either - the previous one
                                         opened inside a card with overflow:hidden
                                         and was clipped by its own card.

                                         The status select ships with a visible
                                         apply button so the form still works
                                         with scripting off; the script below adds
                                         auto-submit on change as a convenience. */ ?>
                                <div class="folder-tile-actions">
                                    <?php if ($has_status_column): ?>
                                        <form method="POST" action="folders.php" class="folder-status-form">
                                            <?= folders_state_fields($state, $csrf) ?>
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="folder_id" value="<?php echo (int)$folder['id']; ?>">
                                            <label class="folders-sr-only" for="status-<?php echo (int)$folder['id']; ?>">Status for <?php echo htmlspecialchars((string)$folder['folder_name']); ?></label>
                                            <select id="status-<?php echo (int)$folder['id']; ?>"
                                                    class="folder-status-select"
                                                    name="status"
                                                    data-folder-status>
                                                <?php foreach (FOLDER_STATUS as $option): ?>
                                                    <option value="<?php echo htmlspecialchars($option); ?>"
                                                        <?php echo $status === $option ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars(folder_status_label($option)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="folder-icon-btn is-neutral" title="Apply status" aria-label="Apply status">
                                                <i class="bx bx-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" action="folders.php">
                                        <?= folders_state_fields($state, $csrf) ?>
                                        <input type="hidden" name="action" value="toggle_public">
                                        <input type="hidden" name="folder_id" value="<?php echo (int)$folder['id']; ?>">
                                        <button type="submit" class="folder-icon-btn is-neutral"
                                                title="<?php echo $folder['is_public'] ? 'Make private' : 'Make public'; ?>"
                                                aria-label="<?php echo $folder['is_public'] ? 'Make private' : 'Make public'; ?>">
                                            <i class="bx <?php echo $folder['is_public'] ? 'bxs-lock' : 'bx-globe'; ?>"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="folders.php" data-confirm="Delete this folder? This cannot be undone.">
                                        <?= folders_state_fields($state, $csrf) ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="folder_id" value="<?php echo (int)$folder['id']; ?>">
                                        <button type="submit" class="folder-icon-btn is-danger"
                                                title="Delete folder" aria-label="Delete folder">
                                            <i class="bx bxs-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="folders-section">
                        <div class="folders-empty">
                            <i class="bx bxs-folder-open"></i>
                            <h3><?php echo $has_active_filters ? 'No folders match these filters' : 'No folders yet'; ?></h3>
                            <p>
                                <?php echo $has_active_filters
                                    ? 'Try a different search term, or clear the filters to see everything.'
                                    : 'Folders created for a department will appear here.'; ?>
                            </p>
                            <?php if ($has_active_filters): ?>
                                <a href="folders.php" class="btn btn-primary"><i class="bx bx-reset"></i> Clear filters</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ================= LIST VIEW ================= -->
            <section id="view-list-panel" class="folders-view folders-section folders-section--table"
                     aria-labelledby="view-list" hidden>
                <p class="table-scroll-hint">
                    <i class="bx bx-move-horizontal"></i>
                    Swipe the table sideways to see every column
                </p>

                <div class="table-responsive table-scroll folders-table-scroll">
                    <table class="table folders-table">
                        <thead>
                            <tr>
                                <th>Folder</th>
                                <th>Type</th>
                                <th>Department</th>
                                <th>Creator</th>
                                <th>Files</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($folders as $folder): ?>
                                <?php
                                $status = folder_status_class($has_status_column ? ($folder['folder_status'] ?? 'active') : 'active');
                                $icon   = folder_icon_class($folder['folder_icon'] ?? '');
                                $colour = folder_hex_color($folder['folder_color'] ?? '');
                                $fid    = (int)$folder['id'];
                                ?>
                                <tr>
                                    <td class="folders-name-cell">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <span class="folder-tile-icon" style="background-color: <?php echo htmlspecialchars($colour); ?>; width:38px; height:38px; font-size:19px;">
                                                <i class="bx <?php echo htmlspecialchars($icon); ?>"></i>
                                            </span>
                                            <span style="min-width:0;">
                                                <span class="folders-cell-name"><?php echo htmlspecialchars((string)$folder['folder_name']); ?></span>
                                                <span class="folder-chip <?php echo $folder['is_public'] ? 'is-public' : ''; ?>" style="margin-top:5px;">
                                                    <i class="bx <?php echo $folder['is_public'] ? 'bx-globe' : 'bxs-lock'; ?>"></i>
                                                    <?php echo $folder['is_public'] ? 'Public' : 'Private'; ?>
                                                </span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="folder-chip"><?php echo htmlspecialchars(ucfirst((string)$folder['folder_type'])); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($folder['department_code'])): ?>
                                            <span class="folder-chip">
                                                <i class="bx bxs-building"></i>
                                                <?php echo htmlspecialchars((string)$folder['department_code']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="folders-cell-sub">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="folders-cell-name"><?php echo htmlspecialchars(trim((string)($folder['creator_full_name'] ?? '')) ?: 'Unknown'); ?></span>
                                        <?php if (!empty($folder['username'])): ?>
                                            <span class="folders-cell-sub">@<?php echo htmlspecialchars((string)$folder['username']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="folders-cell-figure"><?php echo number_format((int)$folder['file_count']); ?></span>
                                    </td>
                                    <td>
                                        <span class="folders-cell-figure"><?php echo htmlspecialchars(formatFileSize($folder['folder_size'] ?? 0)); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($has_status_column): ?>
                                            <form method="POST" action="folders.php" class="folder-status-form">
                                                <?= folders_state_fields($state, $csrf) ?>
                                                <input type="hidden" name="action" value="change_status">
                                                <input type="hidden" name="folder_id" value="<?php echo $fid; ?>">
                                                <label class="folders-sr-only" for="list-status-<?php echo $fid; ?>">Status for <?php echo htmlspecialchars((string)$folder['folder_name']); ?></label>
                                                <select id="list-status-<?php echo $fid; ?>" class="folder-status-select" name="status" data-folder-status>
                                                    <?php foreach (FOLDER_STATUS as $option): ?>
                                                        <option value="<?php echo htmlspecialchars($option); ?>"
                                                            <?php echo $status === $option ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(folder_status_label($option)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="folder-icon-btn is-neutral" title="Apply status" aria-label="Apply status">
                                                    <i class="bx bx-check"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="folder-chip is-<?php echo $status; ?>"><?php echo htmlspecialchars(folder_status_label($status)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="folders-cell-name"><?php echo htmlspecialchars(date('M j, Y', strtotime((string)$folder['created_at']))); ?></span>
                                        <span class="folders-cell-sub"><?php echo htmlspecialchars(date('g:i A', strtotime((string)$folder['created_at']))); ?></span>
                                    </td>
                                    <td>
                                        <div class="folders-row-actions">
                                            <form method="POST" action="folders.php">
                                                <?= folders_state_fields($state, $csrf) ?>
                                                <input type="hidden" name="action" value="toggle_public">
                                                <input type="hidden" name="folder_id" value="<?php echo $fid; ?>">
                                                <button type="submit" class="folder-icon-btn is-neutral"
                                                        title="<?php echo $folder['is_public'] ? 'Make private' : 'Make public'; ?>"
                                                        aria-label="<?php echo $folder['is_public'] ? 'Make private' : 'Make public'; ?>">
                                                    <i class="bx <?php echo $folder['is_public'] ? 'bxs-lock' : 'bx-globe'; ?>"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="folders.php" data-confirm="Delete this folder? This cannot be undone.">
                                                <?= folders_state_fields($state, $csrf) ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="folder_id" value="<?php echo $fid; ?>">
                                                <button type="submit" class="folder-icon-btn is-danger"
                                                        title="Delete folder" aria-label="Delete folder">
                                                    <i class="bx bxs-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ================= PAGINATION =================
                 The old version read $total_files, which does not exist on this
                 page, so the sentence rendered as "Showing 1- of  files". -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-bar">
                    <p class="pagination-info">
                        Showing <b><?php echo $offset + 1; ?>&ndash;<?php echo min($offset + $limit, $total_folders); ?></b>
                        of <b><?php echo number_format($total_folders); ?></b> folders
                    </p>
                    <nav aria-label="Folder pages">
                        <ul class="pagination">
                            <?php /* array_merge, not `$state + [...]`: the union
                                     operator keeps the LEFT operand's value for a
                                     key that appears on both sides, and 'page'
                                     already exists in $state - so every link here
                                     would have pointed back at the current page. */
                                  $qs = static fn(int $p): string => folders_query(array_merge($state, ['page' => $p])); ?>

                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo htmlspecialchars($qs($page - 1)); ?>" aria-label="Previous page">
                                        <i class="bx bx-chevron-left"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true"><i class="bx bx-chevron-left"></i></span>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page   = min($total_pages, $page + 2);
                            ?>

                            <?php if ($start_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo htmlspecialchars($qs(1)); ?>">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php if ($i === $page): ?>
                                        <span class="page-link" aria-current="page"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a class="page-link" href="<?php echo htmlspecialchars($qs($i)); ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo htmlspecialchars($qs($total_pages)); ?>"><?php echo $total_pages; ?></a>
                                </li>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo htmlspecialchars($qs($page + 1)); ?>" aria-label="Next page">
                                        <i class="bx bx-chevron-right"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true"><i class="bx bx-chevron-right"></i></span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script>
        (function () {
            'use strict';

            /* ---- view toggle -------------------------------------------------
               Replaces the previous pair of change listeners that set
               inline display:block/none directly on the containers. Toggling the
               `hidden` attribute instead means the panels keep whatever styling
               the stylesheet gives them, and [hidden] is honoured by the user
               agent, so the page is not relying on inline styles to decide which
               view is showing.

               The choice is remembered in localStorage, so it survives a
               navigation - previously switching to List and then applying a
               filter silently threw you back to Grid. Storage access can throw
               in private browsing modes, hence the try/catch. */
            var STORAGE_KEY = 'folders.view';
            var gridRadio  = document.getElementById('view-grid');
            var listRadio  = document.getElementById('view-list');
            var gridPanel  = document.getElementById('view-grid-panel');
            var listPanel  = document.getElementById('view-list-panel');

            function readStoredView() {
                try {
                    return window.localStorage.getItem(STORAGE_KEY);
                } catch (e) {
                    return null;
                }
            }

            function storeView(value) {
                try {
                    window.localStorage.setItem(STORAGE_KEY, value);
                } catch (e) { /* nothing to do - the choice just will not persist */ }
            }

            function applyView(value) {
                var showList = value === 'list';
                gridPanel.hidden = showList;
                listPanel.hidden = !showList;
                gridRadio.checked = !showList;
                listRadio.checked = showList;
            }

            gridRadio.addEventListener('change', function () {
                if (this.checked) { applyView('grid'); storeView('grid'); }
            });
            listRadio.addEventListener('change', function () {
                if (this.checked) { applyView('list'); storeView('list'); }
            });

            applyView(readStoredView() === 'list' ? 'list' : 'grid');

            /* ---- alert dismissal -------------------------------------------
               Bootstrap's data-bs-dismiss="alert" no longer applies, since
               Bootstrap is no longer loaded. */
            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-dismiss-alert]');
                if (!trigger) { return; }

                var alert = trigger.closest('.alert');
                if (!alert) { return; }

                alert.style.opacity = '0';
                alert.style.transition = 'opacity .2s ease';
                window.setTimeout(function () {
                    if (alert.parentNode) { alert.parentNode.removeChild(alert); }
                }, 200);
            });

            /* ---- destructive-action confirmation -----------------------------
               The old page used an inline onsubmit="return confirm(...)" on
               every delete form, which put a JavaScript string literal inside
               HTML for each row. Reading the message off data-confirm keeps the
               markup clean and means the wording can be edited in one place.

               The handler is delegated, so it works for the forms in both views
               and for any that are added later. */
            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (!form.matches || !form.matches('[data-confirm]')) { return; }

                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });

            /* ---- pending-state on submit ------------------------------------
               Every action on this page is a POST that ends in a redirect, so the
               button is only ever needed for the fraction of a second before the
               page navigates. Marking it busy stops a double tap from firing the
               same delete twice.

               The previous version did this to *every* form on the page and
               rewrote the button's HTML, which also swallowed the icon. This
               only touches forms that carry an action, and only adds a class. */
            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (!form.matches || !form.matches('form[action="folders.php"]')) { return; }

                var submit = form.querySelector('button[type="submit"]');
                if (submit) { submit.classList.add('is-busy'); }
            });

            /* ---- status auto-submit -----------------------------------------
               The status select ships with a visible apply button so the form
               works with scripting off. On top of that, changing the select
               submits immediately, which is what most people expect from a
               one-field form. The apply button stays either way. */
            document.addEventListener('change', function (event) {
                var select = event.target;
                if (!select.matches || !select.matches('[data-folder-status]')) { return; }
                if (select.form) { select.form.submit(); }
            });
        })();
    </script>
</body>
</html>
