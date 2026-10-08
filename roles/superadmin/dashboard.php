<?php 
    include 'assets/script/dashboard-script.php'; 
    require_once '../../includes/config.php';
    require_once '../../includes/auth_check.php';
    $user = requireRole('super_admin');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <!-- Component Stylesheets -->
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>

<body class="superadmin-dashboard-page">
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Super Admin Dashboard</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Overview</a></li>
                    </ul>
                </div>
                <div class="right">
                    <button class="btn btn-secondary" type="button" title="Refresh" onclick="window.location.reload()">
                        <i class='bx bx-refresh'></i>
                        <span>Refresh</span>
                    </button>
                    <a href="users.php" class="btn btn-primary" title="Manage users">
                        <i class='bx bx-user-plus'></i>
                        <span>Add User</span>
                    </a>
                </div>
            </div>

            <!-- Alert for pending users -->
            <?php if ($stats['pending_users'] > 0): ?>
                <div class="alert alert-warning">
                    <i class='bx bxs-error-circle'></i>
                    <div>
                        <strong>Pending Approvals:</strong> <?php echo $stats['pending_users']; ?> user
                        registration(s) are waiting for approval.
                        <a href="users.php?filter=pending">Review Now</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- System Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class='bx bxs-group'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon files">
                        <i class='bx bxs-file'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_files']); ?></h3>
                        <p>Total Files</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon folders">
                        <i class='bx bxs-folder'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_folders']); ?></h3>
                        <p>Total Folders</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon departments">
                        <i class='bx bxs-buildings'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_departments']); ?></h3>
                        <p>Departments</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon announcements">
                        <i class='bx bxs-megaphone'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_announcements']); ?></h3>
                        <p>Announcements</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon storage">
                        <i class='bx bxs-cloud'></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatFileSize($stats['storage_used']); ?></h3>
                        <p>Storage Used</p>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Content -->
            <div class="table-data">
                <!-- Recent System Activities -->
                <div class="order" style="flex: 2;">
                    <div class="head">
                        <h3>Recent System Activities</h3>
                        <i class='bx bx-refresh'></i>
                        <i class='bx bx-filter'></i>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php if (!empty($stats['recent_activities'])): ?>
                            <?php foreach ($stats['recent_activities'] as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php
                                        $icons = [
                                            // Authentication
                                            'login' => 'bxs-log-in',
                                            'logout' => 'bxs-log-out',
                                            'register' => 'bxs-user-rectangle',
                                            // Users
                                            'create_user' => 'bxs-user-plus',
                                            'approve_user' => 'bxs-check-circle',
                                            'reject_user' => 'bxs-x-circle',
                                            'delete_user' => 'bxs-user-minus',
                                            'update_profile' => 'bxs-user',
                                            'update_role' => 'bxs-shield',
                                            'reset_password' => 'bxs-lock-alt',
                                            // Files & folders
                                            'upload_file' => 'bxs-up-arrow-alt',
                                            'delete_file' => 'bxs-trash',
                                            'download_file' => 'bxs-download',
                                            'create_folder' => 'bxs-folder-plus',
                                            'update_folder' => 'bxs-folder',
                                            'delete_folder' => 'bxs-folder-minus',
                                            // Departments
                                            'create_department' => 'bxs-buildings',
                                            'update_department' => 'bxs-building-house',
                                            'delete_department' => 'bxs-buildings',
                                            // Content & system
                                            'create_announcement' => 'bxs-megaphone',
                                            'update_announcement' => 'bxs-megaphone',
                                            'delete_announcement' => 'bxs-message-x',
                                            'create_post' => 'bxs-news',
                                            'submit_document' => 'bxs-file',
                                            'update_settings' => 'bxs-cog',
                                            'system' => 'bxs-bolt'
                                        ];
                                        $icon = $icons[$activity['action'] ?? ''] ?? 'bxs-bell';
                                        ?>
                                        <i class='bx <?php echo $icon; ?>'></i>
                                    </div>
                                    <div class="activity-content">
                                        <h4>
                                            <?php
                                            if ($activity['name']) {
                                                echo htmlspecialchars($activity['name'] . ' ' . ($activity['mi'] ? $activity['mi'] . '. ' : '') . $activity['surname']);
                                            } else {
                                                echo 'System';
                                            }
                                            ?>
                                        </h4>
                                        <p><?php echo getActionText($activity['action']); ?></p>
                                        <?php if ($activity['description']): ?>
                                            <p style="margin-top: 3px; font-size: 11px;">
                                                <?php echo htmlspecialchars($activity['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class='bx bx-time' style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                                <p>No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="activity_logs.php" style="color: var(--blue); text-decoration: none; font-size: 14px;">
                            View All Activities →
                        </a>
                    </div>
                </div>

                <!-- Quick Actions - one tile per module that actually exists in
                     the superadmin sidebar, so nothing here can dead-end. -->
                <div class="todo" style="flex: 1;">
                    <div class="head">
                        <h3>Quick Actions</h3>
                        <i class='bx bx-plus'></i>
                    </div>
                    <div style="display: grid; gap: 12px;">
                        <a href="users.php?action=create" class="quick-action">
                            <i class='bx bx-user-plus'></i>
                            <div>Add User</div>
                        </a>
                        <a href="departments.php?action=create" class="quick-action">
                            <i class='bx bx-buildings'></i>
                            <div>Add Department</div>
                        </a>
                        <a href="files.php" class="quick-action">
                            <i class='bx bxs-file'></i>
                            <div>Manage Files</div>
                        </a>
                        <a href="document-tracker.php" class="quick-action">
                            <i class='bx bx-check-square'></i>
                            <div>Document Tracker</div>
                        </a>
                        <a href="reports.php" class="quick-action">
                            <i class='bx bx-bar-chart-alt'></i>
                            <div>Generate Report</div>
                        </a>
                        <a href="settings.php" class="quick-action">
                            <i class='bx bx-cog'></i>
                            <div>System Settings</div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Department Statistics -->
            <div class="table-data" style="margin-top: 20px;">
                <div class="order" style="flex: 1;">
                    <div class="head">
                        <h3>Department Statistics</h3>
                        <i class='bx bx-bar-chart'></i>
                    </div>
                    <?php if (!empty($stats['department_stats'])): ?>
                        <div class="table-scroll">
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="text-align: left;">Department</th>
                                    <th style="text-align: center;">Users</th>
                                    <th style="text-align: center;">Folders</th>
                                    <th style="text-align: center;">Files</th>
                                    <th style="text-align: right;">Storage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['department_stats'] as $dept): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($dept['department_code']); ?></strong>
                                                <br><small
                                                    style="color: #666;"><?php echo htmlspecialchars($dept['department_name']); ?></small>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge primary"><?php echo $dept['user_count']; ?></span>
                                        </td>
                                        <td style="text-align: center;"><?php echo $dept['folder_count']; ?></td>
                                        <td style="text-align: center;"><?php echo $dept['file_count']; ?></td>
                                        <td style="text-align: right;">
                                            <strong><?php echo formatFileSize($dept['total_size']); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-buildings' style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                            <p>No department data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Information -->
            <div class="table-data" style="margin-top: 20px;">
                <div class="order" style="flex: 1;">
                    <div class="head">
                        <h3>System Information</h3>
                        <i class='bx bx-info-circle'></i>
                    </div>
                    <div style="padding: 20px 0;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: 20px; font-size: 14px;">
                            <div>
                                <strong>Application</strong><br>
                                <span style="color: var(--text-secondary);">ODCI Document Hub</span>
                            </div>
                            <div>
                                <strong>Campus</strong><br>
                                <span style="color: var(--text-secondary);">CVSU Naic</span>
                            </div>
                            <div>
                                <strong>PHP Version</strong><br>
                                <span style="color: var(--text-secondary);"><?php echo htmlspecialchars(phpversion()); ?></span>
                            </div>
                            <div>
                                <strong>Database</strong><br>
                                <span style="color: var(--text-secondary);"><?php
                                    try {
                                        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                                        echo htmlspecialchars(strtoupper($driver) === 'MYSQL' ? 'MySQL / MariaDB' : strtoupper($driver));
                                    } catch (Exception $e) {
                                        echo 'MySQL / MariaDB';
                                    }
                                ?></span>
                            </div>
                            <div>
                                <strong>Storage Used</strong><br>
                                <span style="color: var(--text-secondary);"><?php echo formatFileSize($stats['storage_used']); ?></span>
                            </div>
                            <div>
                                <strong>Server Time</strong><br>
                                <span style="color: var(--text-secondary);"><?php echo date('M j, Y g:i A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <!-- Scripts -->
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/dashboard.js?v=<?= time() ?>"></script>

    <!-- Page-specific initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set active sidebar item and page title for dashboard
            if (window.AppUtils) {
                window.AppUtils.setActiveSidebarItem('dashboard.php');
                window.AppUtils.updateNavbarTitle('Dashboard');
            }
        });
    </script>

    <?php echo getSessionManagementScript(); ?>
</body>

</html>
