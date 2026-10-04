<?php include 'script/dashboard.php'; ?>

<?php
$firstName = trim((string) $currentUser['name']);
$greetingName = $firstName !== '' ? $firstName : $currentUser['surname'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">

    <!-- Modular CSS - Base & Components -->
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
    
    <!-- Page-specific CSS -->
    <link rel="stylesheet" href="assets/css/pages/dashboard/stats_card.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/pages/dashboard/grid_layout.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/pages/dashboard/file_table.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/pages/dashboard/responsive.css?v=<?= time() ?>">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="admin-dashboard-page">
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>
        
        <!-- Enhanced Main Content -->
        <main>
            <!-- Enhanced Header -->
            <div class="head-title">
                <div class="left">
                    <h1>Welcome Back, <?= htmlspecialchars($greetingName, ENT_QUOTES, 'UTF-8') ?>!</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Overview</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><span class="scope-note"><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></span></li>
                    </ul>
                </div>
                <div class="right">
                    <button class="btn btn-secondary" title="Refresh" type="button" onclick="window.location.reload()">
                        <i class='bx bx-refresh'></i>
                        <span>Refresh</span>
                    </button>
                    <a class="btn btn-primary" title="Upload File" href="files.php">
                        <i class='bx bxs-cloud-upload'></i>
                        <span>Upload File</span>
                    </a>
                </div>
            </div>

            <!-- System Status Overview: only the tiles an admin can act on. The database
     health and storage figures live in Storage Analytics below. -->
            <ul class="box-info stats-overview">
                <li class="status-item <?= $summary['users_active'] > 0 ? 'healthy' : 'warning' ?>">
                    <div class="stat-head">
                        <i class='bx bxs-user-account'></i>
                        <h3><?= number_format($summary['users_active']) ?></h3>
                    </div>
                    <p class="stat-label">Active Users &middot; last 30 days</p>
                </li>
                <li class="status-item <?= $summary['users_pending'] > 0 ? 'warning' : 'healthy' ?>">
                    <div class="stat-head">
                        <i class='bx bxs-user-plus'></i>
                        <h3><?= number_format($summary['users_pending']) ?></h3>
                    </div>
                    <p class="stat-label">Pending Approval<?= $summary['users_total'] > 0 ? ' &middot; ' . number_format($summary['users_pending']) . ' of ' . number_format($summary['users_total']) . ' accounts' : '' ?></p>
                </li>
            </ul>

<!-- Enhanced Statistics Cards -->
            <ul class="box-info">
                <li>
                    <div class="stat-head">
                        <i class='bx bxs-file'></i>
                        <h3><?= number_format($summary['files_total']) ?></h3>
                    </div>
                    <p class="stat-label">Total Files</p>
                    <div class="change <?= $changes['files']['class'] ?>">
                        <i class='bx <?= $changes['files']['icon'] ?>'></i>
                        <span><?= htmlspecialchars($changes['files']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="progress-bar" title="<?= number_format($bars['files'], 1) ?>% of all files in the system">
                        <div class="progress-fill" style="width: <?= $bars['files'] ?>%;"></div>
                    </div>
                    <div class="bar-note"><?= number_format($bars['files'], 1) ?>% of all system files</div>
                </li>

                <li>
                    <div class="stat-head">
                        <i class='bx bxs-folder'></i>
                        <h3><?= number_format($summary['folders_total']) ?></h3>
                    </div>
                    <p class="stat-label">Total Folders</p>
                    <div class="change <?= $changes['folders']['class'] ?>">
                        <i class='bx <?= $changes['folders']['icon'] ?>'></i>
                        <span><?= htmlspecialchars($changes['folders']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="progress-bar" title="<?= number_format($bars['folders'], 1) ?>% of all folders in the system">
                        <div class="progress-fill" style="width: <?= $bars['folders'] ?>%;"></div>
                    </div>
                    <div class="bar-note"><?= number_format($bars['folders'], 1) ?>% of all system folders</div>
                </li>

                <li>
                    <div class="stat-head">
                        <i class='bx bxs-cloud'></i>
                        <h3><?= htmlspecialchars(adminDash_formatBytes($summary['storage_bytes']), ENT_QUOTES, 'UTF-8') ?></h3>
                    </div>
                    <p class="stat-label">Storage Used</p>
                    <div class="change <?= $changes['storage']['class'] ?>">
                        <i class='bx <?= $changes['storage']['icon'] ?>'></i>
                        <span><?= htmlspecialchars($changes['storage']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="progress-bar" title="<?= number_format($bars['storage'], 1) ?>% of the uploads volume">
                        <div class="progress-fill" style="width: <?= $bars['storage'] ?>%;"></div>
                    </div>
                    <div class="bar-note"><?= number_format($bars['storage'], 1) ?>% of the uploads volume</div>
                </li>

                <li>
                    <div class="stat-head">
                        <i class='bx bxs-user-account'></i>
                        <h3><?= number_format($summary['users_active']) ?></h3>
                    </div>
                    <p class="stat-label">Active Users</p>
                    <div class="change neutral">
                        <i class='bx bx-calendar'></i>
                        <span>of <?= number_format($summary['users_approved']) ?> approved</span>
                    </div>
                    <div class="progress-bar" title="<?= number_format($bars['users'], 1) ?>% of approved users signed in within 30 days">
                        <div class="progress-fill" style="width: <?= $bars['users'] ?>%;"></div>
                    </div>
                    <div class="bar-note">Signed in within the last 30 days</div>
                </li>
            </ul>

            <!-- Main Dashboard Content -->
            <div class="dashboard-grid">
                <!-- Recent Activity -->
                <div class="dashboard-card fade-in">
                    <div class="card-header">
                        <h3>
                            <i class='bx bxs-time'></i>
                            Recent Activity
                        </h3>
                        <div class="card-actions">
                            <button class="btn-icon" title="Filter">
                                <i class='bx bx-filter'></i>
                            </button>
                            <button class="btn-icon" title="Refresh" type="button" onclick="window.location.reload()">
                                <i class='bx bx-refresh'></i>
                            </button>
                            <button class="btn-icon" title="Export">
                                <i class='bx bx-download'></i>
                            </button>
                        </div>
                    </div>

                    <table class="files-table enhanced-table">
                        <thead>
                            <tr>
                                <th>Activity</th>
                                <th>User</th>
                                <th>Department</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivity)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class='bx bx-history'></i>
                                            <p>No activity recorded in the last 7 days.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <tr>
                                        <td>
                                            <div class="file-info">
                                                <div class="file-icon">
                                                    <i class='bx <?= $activity['icon'] ?>'></i>
                                                </div>
                                                <div class="file-details">
                                                    <p><?= htmlspecialchars($activity['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <div class="file-meta">
                                                        <?php if ($activity['occurrences'] > 1): ?>
                                                            <?= (int) $activity['occurrences'] ?> times in the last 7 days
                                                        <?php else: ?>
                                                            Once in the last 7 days
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($activity['user'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($activity['department'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($activity['when'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="status-badge <?= $activity['badge'] ?>">
                                                <i class='bx <?= $activity['badgeIcon'] ?>'></i>
                                                <?= htmlspecialchars($activity['badgeText'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Enhanced Quick Actions -->
                <div class="dashboard-card slide-up">
                    <div class="card-header">
                        <h3>
                            <i class='bx bxs-zap'></i>
                            Quick Actions
                        </h3>
                    </div>

                    <div class="quick-actions">
                        <a href="files.php" class="action-item">
                            <div class="action-content">
                                <div class="action-icon">
                                    <i class='bx bx-upload'></i>
                                </div>
                                <p>Upload Files</p>
                            </div>
                            <i class='bx bx-chevron-right action-arrow'></i>
                        </a>

                        <a href="folders.php" class="action-item">
                            <div class="action-content">
                                <div class="action-icon">
                                    <i class='bx bx-folder-plus'></i>
                                </div>
                                <p>Create Folder</p>
                            </div>
                            <i class='bx bx-chevron-right action-arrow'></i>
                        </a>

                        <a href="view-faculty-staff.php" class="action-item">
                            <div class="action-content">
                                <div class="action-icon">
                                    <i class='bx bx-user-plus'></i>
                                </div>
                                <p>Manage Users</p>
                            </div>
                            <i class='bx bx-chevron-right action-arrow'></i>
                        </a>

                        <a href="reports.php" class="action-item">
                            <div class="action-content">
                                <div class="action-icon">
                                    <i class='bx bx-bar-chart-alt-2'></i>
                                </div>
                                <p>Generate Reports</p>
                            </div>
                            <i class='bx bx-chevron-right action-arrow'></i>
                        </a>

                        <a href="profile.php" class="action-item">
                            <div class="action-content">
                                <div class="action-icon">
                                    <i class='bx bx-cog'></i>
                                </div>
                                <p>System Settings</p>
                            </div>
                            <i class='bx bx-chevron-right action-arrow'></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Additional Information Cards -->
            <div class="dashboard-grid" style="margin-top: 24px;">
                <!-- Storage & Activity -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>
                            <i class='bx bxs-pie-chart-alt-2'></i>
                            Storage Analytics
                        </h3>
                        <div class="card-actions">
                            <a class="btn-icon" href="reports.php" title="View Details">
                                <i class='bx bx-show'></i>
                            </a>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 16px;">
                        <div class="info-item">
                            <div class="info-label">Documents Stored</div>
                            <div class="info-value" style="color: var(--cvsu-green-700); font-size: 24px; font-weight: 700;"><?= htmlspecialchars(adminDash_formatBytes($summary['storage_bytes']), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="progress-bar" style="margin-top: 8px;">
                                <div class="progress-fill" style="width: <?= $bars['storage'] ?>%;"></div>
                            </div>
                            <div class="bar-note"><?= number_format($bars['storage'], 1) ?>% of the uploads volume</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Uploads Volume</div>
                            <div class="info-value" style="color: var(--cvsu-gold-700); font-size: 24px; font-weight: 700;"><?= $quotaBytes > 0 ? htmlspecialchars(adminDash_formatBytes($quotaBytes, 0), ENT_QUOTES, 'UTF-8') : 'Unknown' ?></div>
                            <div class="bar-note" style="margin-top: 8px;">
                                <?= $freeBytes > 0 ? htmlspecialchars(adminDash_formatBytes($freeBytes, 0), ENT_QUOTES, 'UTF-8') . ' free' : 'Free space unavailable' ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Growth Rate</div>
                            <div class="info-value" style="color: <?= $changes['storage']['class'] === 'negative' ? 'var(--cvsu-gold-700)' : 'var(--cvsu-green-700)' ?>; font-size: 24px; font-weight: 700;"><?= $changes['storage']['label'] ?></div>
                            <div style="color: var(--cvsu-grey-500); font-size: 12px; margin-top: 8px;">
                                <i class='bx <?= $changes['storage']['icon'] ?>'></i> Last 30 days vs previous 30
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Recent Activity</div>
                            <div class="info-value" style="font-size: 24px; font-weight: 700;"><?= number_format($summary['uploads_this_week']) ?></div>
                            <div class="bar-note" style="margin-top: 8px;">
                                <?= $summary['uploads_this_week'] === 1 ? 'upload' : 'uploads' ?> in the last 7 days
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="dashboard-card slide-up">
                    <div class="card-header">
                        <h3>
                            <i class='bx bxs-user-detail'></i>
                            Account Information
                        </h3>
                        <div class="card-actions">
                            <a class="btn-icon" href="profile.php" title="Edit Profile">
                                <i class='bx bx-edit'></i>
                            </a>
                        </div>
                    </div>

                    <div class="account-info">
                        <div class="info-item">
                            <div class="info-label">Department</div>
                            <div class="info-value"><?= htmlspecialchars($account['department'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value"><?= htmlspecialchars($account['employeeId'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Login</div>
                            <div class="info-value"><?= htmlspecialchars($account['lastLogin'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Member Since</div>
                            <div class="info-value"><?= htmlspecialchars($account['memberSince'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications and Alerts -->
            <div class="dashboard-card fade-in" style="margin-top: 24px;">
                <div class="card-header">
                    <h3>
                        <i class='bx bxs-bell'></i>
                        Recent Notifications
                    </h3>
                    <div class="card-actions">
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="unread-chip" title="Unread notifications">
                                <?= (int) $unreadNotifications ?> unread
                            </span>
                        <?php endif; ?>
                        <button class="btn-icon" title="Mark all as read">
                            <i class='bx bx-check-double'></i>
                        </button>
                        <button class="btn-icon" title="Settings">
                            <i class='bx bx-cog'></i>
                        </button>
                    </div>
                </div>

                <div class="notif-list">
                    <?php if (empty($recentNotifications)): ?>
                        <div class="notif-item">
                            <div class="notif-icon info">
                                <i class='bx bx-bell-off'></i>
                            </div>
                            <div class="notif-body">
                                <h4>No notifications</h4>
                                <p>You have no notifications right now. New alerts appear here as they arrive.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentNotifications as $notification): ?>
                            <div class="notif-item<?= $notification['isRead'] ? '' : ' unread' ?>">
                                <div class="notif-icon <?= $notification['tone'] ?>">
                                    <i class='bx <?= $notification['icon'] ?>'></i>
                                </div>
                                <div class="notif-body">
                                    <h4><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                                    <p><?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <span><?= htmlspecialchars($notification['when'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </section>

    <script src="assets/js/components/sidebar.js?v=<?= time() ?>"></script>
    <script src="assets/js/components/navbar.js?v=<?= time() ?>"></script>
    <script src="assets/js/pages/dashboard.js?v=<?= time() ?>"></script>
</body>
</html>
