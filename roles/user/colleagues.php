<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = 'colleagues.php';

// Get filters
$department_filter = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

// Get all departments for filter
$stmt = $pdo->prepare("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name");
$stmt->execute();
$departments = $stmt->fetchAll();

// Get colleagues with filters
$query = "
    SELECT u.*, d.department_name, d.department_code
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.is_approved = 1 AND u.id != ?
";

$params = [$user_id];

if ($department_filter) {
    $query .= " AND u.department_id = ?";
    $params[] = $department_filter;
}

if ($search) {
    $query .= " AND (u.name LIKE ? OR u.surname LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.position LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

$query .= " ORDER BY u.surname, u.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$colleagues = $stmt->fetchAll();

foreach ($colleagues as &$colleague) {
    $colleague['full_name'] = trim(
        $colleague['name'] . ' ' .
        (!empty($colleague['mi']) ? $colleague['mi'] . '. ' : '') .
        $colleague['surname']
    );

    $imagePath = trim((string)($colleague['profile_image'] ?? ''));
    $imageFile = $imagePath !== ''
        ? __DIR__ . '/../../' . ltrim(str_replace('\\', '/', $imagePath), '/')
        : '';
    $colleague['profile_image_url'] = $imageFile !== '' && is_file($imageFile)
        ? '../../' . ltrim(str_replace('\\', '/', $imagePath), '/') . '?v=' . filemtime($imageFile)
        : '';

    $nameParts = preg_split('/\s+/', $colleague['full_name'], -1, PREG_SPLIT_NO_EMPTY);
    $colleague['initials'] = strtoupper(
        mb_substr($nameParts[0] ?? '?', 0, 1) .
        (count($nameParts) > 1 ? mb_substr($nameParts[count($nameParts) - 1], 0, 1) : '')
    );
}
unset($colleague);

// Get user's department colleagues count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE department_id = (SELECT department_id FROM users WHERE id = ?) 
    AND is_approved = 1 AND id != ?
");
$stmt->execute([$user_id, $user_id]);
$dept_colleagues_count = $stmt->fetch()['count'];

// Get total colleagues count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE is_approved = 1 AND id != ?");
$stmt->execute([$user_id]);
$total_colleagues = $stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colleagues - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">

    <!-- styles moved out of the body so the shared theme can win -->
<style>
    .filter-form {
        margin-bottom: 2rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }

    .form-group input, .form-group select {
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }

    .colleagues-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 1.5rem;
    }

    .colleague-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        background: white;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .colleague-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: #667eea;
    }

    .colleague-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
        position: relative;
    }

    .colleague-avatar {
        position: relative;
        flex-shrink: 0;
    }

    .colleague-avatar img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #f1f5f9;
    }

    .avatar-initials {
        display: grid;
        width: 60px;
        height: 60px;
        place-items: center;
        border: 1px solid #d9e8dd;
        border-radius: 50%;
        background: #edf5ef;
        color: #14532d;
        font-size: 19px;
        font-weight: 700;
        letter-spacing: .02em;
    }

    .profile-dialog {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        right: auto !important;
        bottom: auto !important;
        margin: 0 !important;
        transform: translate(-50%, -50%) !important;
        width: min(460px, calc(100vw - 32px));
        max-width: none;
        max-height: calc(100vh - 32px);
        padding: 0;
        overflow: auto;
        border: 1px solid #e4ebe6;
        border-radius: 16px;
        color: #16241c;
        box-shadow: 0 24px 70px rgba(11, 31, 18, .24);
    }

    .profile-dialog::backdrop {
        background: rgba(14, 28, 19, .48);
        backdrop-filter: blur(2px);
    }

    .profile-dialog-content {
        position: relative;
        padding: 30px;
    }

    .profile-dialog-close {
        position: absolute;
        top: 14px;
        right: 14px;
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border: 0;
        border-radius: 9px;
        background: #f1f5f2;
        color: #46564b;
        cursor: pointer;
        font-size: 20px;
    }

    .profile-dialog-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-right: 28px;
        margin-bottom: 24px;
    }

    .profile-dialog-header .avatar-initials,
    .profile-dialog-header img {
        width: 72px;
        height: 72px;
        flex: 0 0 72px;
    }

    .profile-dialog-header h2 {
        margin: 0 0 5px;
        color: #16241c;
        font-size: 20px;
        line-height: 1.3;
    }

    .profile-dialog-header p {
        margin: 0;
        color: #68766c;
        font-size: 14px;
    }

    .profile-dialog-details {
        display: grid;
        gap: 13px;
        margin: 0;
    }

    .profile-dialog-details div {
        display: grid;
        grid-template-columns: 112px minmax(0, 1fr);
        gap: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #edf1ee;
    }

    .profile-dialog-details div[hidden] {
        display: none;
    }

    .profile-dialog-details div:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .profile-dialog-details dt {
        color: #748078;
        font-size: 13px;
        font-weight: 600;
    }

    .profile-dialog-details dd {
        min-width: 0;
        margin: 0;
        overflow-wrap: anywhere;
        color: #25362b;
        font-size: 14px;
    }

    .profile-dialog-details a {
        color: #176b39;
        text-decoration: none;
    }

    .colleague-info {
        flex: 1;
        min-width: 0;
    }

    .colleague-info h4 {
        margin: 0 0 0.25rem 0;
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
        word-wrap: break-word;
    }

    .colleague-info .username {
        margin: 0 0 0.25rem 0;
        font-size: 14px;
        color: #667eea;
        font-weight: 500;
    }

    .colleague-info .position {
        margin: 0;
        font-size: 14px;
        color: #64748b;
        font-style: italic;
    }

    .colleague-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        position: absolute;
        top: 0;
        right: 0;
    }

    .status-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }

    .status-indicator.online {
        background: #22c55e;
        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.3);
        animation: pulse 2s infinite;
    }

    .status-indicator.recent {
        background: #f59e0b;
    }

    .status-indicator.today {
        background: #3b82f6;
    }

    .status-indicator.offline {
        background: #9ca3af;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .status-text {
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
    }

    .colleague-details {
        margin-bottom: 1rem;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
        font-size: 14px;
        color: #64748b;
    }

    .detail-item:last-child {
        margin-bottom: 0;
    }

    .detail-item i {
        color: #94a3b8;
        font-size: 16px;
        width: 16px;
        flex-shrink: 0;
    }

    .detail-item span {
        word-wrap: break-word;
        min-width: 0;
    }

    .colleague-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #f1f5f9;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #f8fafc;
        color: #64748b;
        text-decoration: none;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        cursor: pointer;
        font-size: 16px;
    }

    .btn-action:hover {
        background: #667eea;
        color: white;
        transform: scale(1.1);
        border-color: #667eea;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #64748b;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state p {
        font-size: 16px;
        margin: 0;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .colleagues-grid {
            grid-template-columns: 1fr;
        }
        
        .colleague-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .colleague-status {
            position: static;
            margin-top: 0.5rem;
        }
        
        .colleague-info {
            text-align: center;
        }
    }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="user-colleagues-page">
    <?php include 'components/sidebar.html'; ?>

    <section id="content">
        <?php include 'components/navbar.html'; ?>
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Colleagues</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Colleagues</a></li>
                    </ul>
                </div>
            </div>

            <!-- Statistics Cards -->
            <ul class="box-info">
                <li>
                    <i class='bx bxs-group'></i>
                    <span class="text">
                        <h3><?= $total_colleagues ?></h3>
                        <p>Total Colleagues</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-building'></i>
                    <span class="text">
                        <h3><?= $dept_colleagues_count ?></h3>
                        <p>Department Colleagues</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-user-check'></i>
                    <span class="text">
                        <h3><?= count(array_filter($colleagues, fn($c) => $c['last_login'] && strtotime($c['last_login']) > strtotime('-7 days'))) ?></h3>
                        <p>Active This Week</p>
                    </span>
                </li>
            </ul>

            <!-- Search and Filter -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Search & Filter</h3>
                    </div>
                    <form method="GET" class="filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Search:</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, username, email, or position...">
                            </div>
                            
                            <div class="form-group">
                                <label>Department:</label>
                                <select name="department">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= $department_filter == $dept['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Role:</label>
                                <select name="role">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="user" <?= $role_filter == 'user' ? 'selected' : '' ?>>User</option>
                                    <option value="super_admin" <?= $role_filter == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <a href="colleagues.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Colleagues List -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Colleagues (<?= count($colleagues) ?> found)</h3>
                    </div>
                    
                    <?php if (empty($colleagues)): ?>
                        <div class="empty-state">
                            <i class='bx bx-user-x'></i>
                            <p>No colleagues found matching your criteria</p>
                        </div>
                    <?php else: ?>
                        <div class="colleagues-grid">
                            <?php foreach ($colleagues as $colleague): ?>
                                <div class="colleague-card">
                                    <div class="colleague-header">
                                        <div class="colleague-avatar">
                                            <?php if ($colleague['profile_image_url']): ?>
                                                <img src="<?= htmlspecialchars($colleague['profile_image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($colleague['full_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?php else: ?>
                                                <span class="avatar-initials" aria-label="<?= htmlspecialchars($colleague['full_name'], ENT_QUOTES, 'UTF-8') ?> profile photo not provided"><?= htmlspecialchars($colleague['initials'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="colleague-info">
                                            <h4><?= htmlspecialchars($colleague['full_name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                            <p class="username">@<?= htmlspecialchars($colleague['username']) ?></p>
                                            
                                            <?php if ($colleague['position']): ?>
                                                <p class="position"><?= htmlspecialchars($colleague['position']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="colleague-status">
                                            <?php
                                            $status_class = 'offline';
                                            $status_text = 'Offline';
                                            
                                            if ($colleague['last_login']) {
                                                $last_login = strtotime($colleague['last_login']);
                                                $now = time();
                                                $diff = $now - $last_login;
                                                
                                                if ($diff < 300) { // 5 minutes
                                                    $status_class = 'online';
                                                    $status_text = 'Online';
                                                } elseif ($diff < 3600) { // 1 hour
                                                    $status_class = 'recent';
                                                    $status_text = 'Recently Active';
                                                } elseif ($diff < 86400) { // 1 day
                                                    $status_class = 'today';
                                                    $status_text = 'Active Today';
                                                }
                                            }
                                            ?>
                                            <span class="status-indicator <?= $status_class ?>"></span>
                                            <span class="status-text"><?= $status_text ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="colleague-details">
                                        <div class="detail-item">
                                            <i class='bx bx-envelope'></i>
                                            <span><?= htmlspecialchars($colleague['email']) ?></span>
                                        </div>
                                        
                                        <?php if ($colleague['department_name']): ?>
                                            <div class="detail-item">
                                                <i class='bx bx-building'></i>
                                                <span><?= htmlspecialchars($colleague['department_name']) ?> (<?= htmlspecialchars($colleague['department_code']) ?>)</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($colleague['employee_id']): ?>
                                            <div class="detail-item">
                                                <i class='bx bx-id-card'></i>
                                                <span>ID: <?= htmlspecialchars($colleague['employee_id']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($colleague['phone']): ?>
                                            <div class="detail-item">
                                                <i class='bx bx-phone'></i>
                                                <span><?= htmlspecialchars($colleague['phone']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="detail-item">
                                            <i class='bx bx-time'></i>
                                            <span>Joined <?= date('M Y', strtotime($colleague['created_at'])) ?></span>
                                        </div>
                                        
                                        <?php if ($colleague['last_login']): ?>
                                            <div class="detail-item">
                                                <i class='bx bx-log-in'></i>
                                                <span>Last seen <?= date('M d, Y g:i A', strtotime($colleague['last_login'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="colleague-actions">
                                        <a href="mailto:<?= htmlspecialchars($colleague['email']) ?>" class="btn-action" title="Send Email">
                                            <i class='bx bx-envelope'></i>
                                        </a>
                                        
                                        <?php if ($colleague['phone']): ?>
                                            <a href="tel:<?= htmlspecialchars($colleague['phone']) ?>" class="btn-action" title="Call">
                                                <i class='bx bx-phone'></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn-action" title="View Profile" aria-label="View <?= htmlspecialchars($colleague['full_name'], ENT_QUOTES, 'UTF-8') ?> profile"
                                            data-profile-name="<?= htmlspecialchars($colleague['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-username="<?= htmlspecialchars($colleague['username'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-position="<?= htmlspecialchars($colleague['position'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-email="<?= htmlspecialchars($colleague['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-department="<?= htmlspecialchars(trim(($colleague['department_name'] ?? '') . (!empty($colleague['department_code']) ? ' (' . $colleague['department_code'] . ')' : '')), ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-employee-id="<?= htmlspecialchars($colleague['employee_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-phone="<?= htmlspecialchars($colleague['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-joined="<?= htmlspecialchars(date('M Y', strtotime($colleague['created_at'])), ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-last-seen="<?= htmlspecialchars($colleague['last_login'] ? date('M d, Y g:i A', strtotime($colleague['last_login'])) : '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-photo="<?= htmlspecialchars($colleague['profile_image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-profile-initials="<?= htmlspecialchars($colleague['initials'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class='bx bx-user' aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </section>

    <dialog class="profile-dialog" id="colleagueProfileDialog" aria-labelledby="profileDialogName">
        <div class="profile-dialog-content">
            <form method="dialog">
                <button class="profile-dialog-close" type="submit" aria-label="Close profile">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </form>
            <div class="profile-dialog-header">
                <span class="avatar-initials" id="profileDialogInitials" aria-hidden="true"></span>
                <img id="profileDialogPhoto" alt="" hidden>
                <div>
                    <h2 id="profileDialogName"></h2>
                    <p id="profileDialogUsername"></p>
                </div>
            </div>
            <dl class="profile-dialog-details">
                <div id="profileDialogPositionRow"><dt>Position</dt><dd id="profileDialogPosition"></dd></div>
                <div id="profileDialogDepartmentRow"><dt>Department</dt><dd id="profileDialogDepartment"></dd></div>
                <div id="profileDialogEmailRow"><dt>Email</dt><dd id="profileDialogEmail"></dd></div>
                <div id="profileDialogEmployeeIdRow"><dt>Employee ID</dt><dd id="profileDialogEmployeeId"></dd></div>
                <div id="profileDialogPhoneRow"><dt>Phone</dt><dd id="profileDialogPhone"></dd></div>
                <div><dt>Joined</dt><dd id="profileDialogJoined"></dd></div>
                <div id="profileDialogLastSeenRow"><dt>Last seen</dt><dd id="profileDialogLastSeen"></dd></div>
            </dl>
        </div>
    </dialog>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/components/navbar.js?v=<?= time() ?>"></script>
    <script>
    (() => {
        const dialog = document.getElementById('colleagueProfileDialog');
        const photo = document.getElementById('profileDialogPhoto');
        const initials = document.getElementById('profileDialogInitials');

        if (!dialog || !photo || !initials || typeof dialog.showModal !== 'function') {
            console.error('Colleague profile dialog is not supported in this browser.');
            return;
        }

        const profileFields = [
            ['Position', 'profileDialogPosition', 'profileDialogPositionRow'],
            ['Department', 'profileDialogDepartment', 'profileDialogDepartmentRow'],
            ['Email', 'profileDialogEmail', 'profileDialogEmailRow'],
            ['EmployeeId', 'profileDialogEmployeeId', 'profileDialogEmployeeIdRow'],
            ['Phone', 'profileDialogPhone', 'profileDialogPhoneRow'],
            ['Joined', 'profileDialogJoined', null],
            ['LastSeen', 'profileDialogLastSeen', 'profileDialogLastSeenRow']
        ];

        document.addEventListener('click', event => {
            const button = event.target instanceof Element
                ? event.target.closest('[data-profile-name]')
                : null;
            if (!button) return;

            document.getElementById('profileDialogName').textContent = button.dataset.profileName;
            document.getElementById('profileDialogUsername').textContent = '@' + button.dataset.profileUsername;
            initials.textContent = button.dataset.profileInitials;

            for (const [field, elementId, rowId] of profileFields) {
                const value = button.dataset['profile' + field] || '';
                const element = document.getElementById(elementId);
                if (field === 'Email' && value) {
                    const link = document.createElement('a');
                    link.href = 'mailto:' + value;
                    link.textContent = value;
                    element.replaceChildren(link);
                } else {
                    element.textContent = value;
                }
                if (rowId) document.getElementById(rowId).hidden = !value;
            }

            const imageUrl = button.dataset.profilePhoto || '';
            photo.hidden = !imageUrl;
            initials.hidden = Boolean(imageUrl);
            if (imageUrl) {
                photo.src = imageUrl;
                photo.onerror = () => {
                    photo.hidden = true;
                    initials.hidden = false;
                };
                photo.onload = () => {
                    photo.hidden = false;
                    initials.hidden = true;
                };
            } else {
                photo.removeAttribute('src');
            }

            dialog.showModal();
        });

        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });
    })();
    </script>
</body>
</html>
