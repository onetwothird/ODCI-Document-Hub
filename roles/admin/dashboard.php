<?php include 'script/dashboard.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    
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
                    <h1>Welcome Back, Admin!</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Overview</a></li>
                    </ul>
                </div>
                <div class="right">
                    <button class="btn btn-secondary" title="Refresh">
                        <i class='bx bx-refresh'></i>
                        <span>Refresh</span>
                    </button>
                    <button class="btn btn-primary" title="Upload File">
                        <i class='bx bxs-cloud-upload'></i>
                        <span>Upload File</span>
                    </button>
                </div>
            </div>

            <!-- System Status Overview -->
            <ul class="box-info stats-overview">
                <li class="status-item healthy">
                    <i class='bx bx-server'></i>
                    <span class="text">
                        <h3>99.9%</h3>
                        <p>Server Uptime</p>
                    </span>
                </li>
                <li class="status-item healthy">
                    <i class='bx bx-data'></i>
                    <span class="text">
                        <h3>Healthy</h3>
                        <p>Database Status</p>
                    </span>
                </li>
                <li class="status-item warning">
                    <i class='bx bx-hard-drive'></i>
                    <span class="text">
                        <h3>78%</h3>
                        <p>Storage Used</p>
                    </span>
                </li>
                <li class="status-item healthy">
                    <i class='bx bxs-user-account'></i>
                    <span class="text">
                        <h3>1,247</h3>
                        <p>Active Users</p>
                    </span>
                </li>
            </ul>

            <!-- Enhanced Statistics Cards -->
            <ul class="box-info">
                <li>
                    <i class='bx bxs-file'></i>
                    <span class="text">
                        <h3>12,847</h3>
                        <p>Total Files</p>
                        <div class="change positive">
                            <i class='bx bx-trending-up'></i>
                            <span>+12.5% from last month</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 75%;"></div>
                        </div>
                    </span>
                </li>

                <li>
                    <i class='bx bxs-folder'></i>
                    <span class="text">
                        <h3>3,564</h3>
                        <p>Total Folders</p>
                        <div class="change positive">
                            <i class='bx bx-trending-up'></i>
                            <span>+8.2% from last month</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 60%;"></div>
                        </div>
                    </span>
                </li>

                <li>
                    <i class='bx bxs-cloud'></i>
                    <span class="text">
                        <h3>2.4 TB</h3>
                        <p>Storage Used</p>
                        <div class="change negative">
                            <i class='bx bx-trending-down'></i>
                            <span>-3.1% from last month</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 45%;"></div>
                        </div>
                    </span>
                </li>

                <li>
                    <i class='bx bxs-user-account'></i>
                    <span class="text">
                        <h3>8,291</h3>
                        <p>Active Users</p>
                        <div class="change positive">
                            <i class='bx bx-trending-up'></i>
                            <span>+15.3% from last month</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 82%;"></div>
                        </div>
                    </span>
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
                            <button class="btn-icon" title="Refresh">
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
                                <th>File/Action</th>
                                <th>User</th>
                                <th>Department</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="file-info">
                                        <div class="file-icon">
                                            <i class='bx bxs-file-pdf'></i>
                                        </div>
                                        <div class="file-details">
                                            <p>Research_Proposal_2024.pdf</p>
                                            <div class="file-meta">File uploaded</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Dr. Maria Santos</td>
                                <td>Computer Science</td>
                                <td>2 minutes ago</td>
                                <td>
                                    <span class="status-badge status-completed">
                                        <i class='bx bx-check'></i> Complete
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="file-info">
                                        <div class="file-icon">
                                            <i class='bx bxs-folder'></i>
                                        </div>
                                        <div class="file-details">
                                            <p>Q1_Reports</p>
                                            <div class="file-meta">Folder created</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Prof. Juan Cruz</td>
                                <td>Mathematics</td>
                                <td>15 minutes ago</td>
                                <td>
                                    <span class="status-badge status-public">
                                        <i class='bx bx-globe'></i> New
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="file-info">
                                        <div class="file-icon">
                                            <i class='bx bxs-file-doc'></i>
                                        </div>
                                        <div class="file-details">
                                            <p>Curriculum_Update.docx</p>
                                            <div class="file-meta">File shared</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Dr. Ana Reyes</td>
                                <td>Engineering</td>
                                <td>1 hour ago</td>
                                <td>
                                    <span class="status-badge status-private">
                                        <i class='bx bx-share'></i> Shared
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="file-info">
                                        <div class="file-icon">
                                            <i class='bx bxs-user-plus'></i>
                                        </div>
                                        <div class="file-details">
                                            <p>New User Registration</p>
                                            <div class="file-meta">System action</div>
                                        </div>
                                    </div>
                                </td>
                                <td>System</td>
                                <td>Admin</td>
                                <td>2 hours ago</td>
                                <td>
                                    <span class="status-badge status-favorite">
                                        <i class='bx bx-cog'></i> System
                                    </span>
                                </td>
                            </tr>
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
                                <div>
                                    <h4>Upload Files</h4>
                                    <p>Add new documents</p>
                                </div>
                            </div>
                            <i class='bx bx-chevron-right action-arrow'></i>
                        </a>

                        <a href="folders.php" class="action-item">
                            <div class="action-content">
                                <div class="action-icon">
                                    <i class='bx bx-folder-plus'></i>
                                </div>
                                <div>
                                    <h4>Create Folder</h4>
                                    <p>Organize your files</p>
                                </div>
                            </div>
                            <i class='bx bx-chevron-right action-arrow'></i>
                        </a>

                        <a href="view-faculty-staff.php" class="action-item">
                            <div class="action-content">
                                <div class="action-icon">
                                    <i class='bx bx-user-plus'></i>
                                </div>
                                <div>
                                    <h4>Manage Users</h4>
                                    <p>Add or edit users</p>
                                </div>
                            </div>
                            <i class='bx bx-chevron-right action-arrow'></i>
                        </a>

                        <a href="reports.php" class="action-item">
                            <div class="action-content">
                                <div class="action-icon">
                                    <i class='bx bx-bar-chart-alt-2'></i>
                                </div>
                                <div>
                                    <h4>Generate Reports</h4>
                                    <p>View analytics</p>
                                </div>
                            </div>
                            <i class='bx bx-chevron-right action-arrow'></i>
                        </a>

                        <a href="settings.php" class="action-item">
                            <div class="action-content">
                                <div class="action-icon">
                                    <i class='bx bx-cog'></i>
                                </div>
                                <div>
                                    <h4>System Settings</h4>
                                    <p>Configure system</p>
                                </div>
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
                            <button class="btn-icon" title="View Details">
                                <i class='bx bx-show'></i>
                            </button>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 16px;">
                        <div class="info-item">
                            <div class="info-label">Used Storage</div>
                            <div class="info-value" style="color: var(--blue); font-size: 24px; font-weight: 700;">2.4 TB</div>
                            <div class="progress-bar" style="margin-top: 8px;">
                                <div class="progress-fill" style="width: 78%;"></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Available</div>
                            <div class="info-value" style="color: var(--orange); font-size: 24px; font-weight: 700;">845 GB</div>
                            <div class="progress-bar" style="margin-top: 8px;">
                                <div class="progress-fill" style="width: 22%; background: var(--orange);"></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Growth Rate</div>
                            <div class="info-value" style="color: var(--green-primary); font-size: 24px; font-weight: 700;">12.5%</div>
                            <div style="color: var(--green-primary); font-size: 12px; margin-top: 8px;">
                                <i class='bx bx-trending-up'></i> Monthly
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Recent Activity</div>
                            <div class="info-value">12 uploads this week</div>
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
                            <button class="btn-icon" title="Edit Profile">
                                <i class='bx bx-edit'></i>
                            </button>
                        </div>
                    </div>

                    <div class="account-info">
                        <div class="info-item">
                            <div class="info-label">Department</div>
                            <div class="info-value">Information Technology</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value">ADM-001</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Login</div>
                            <div class="info-value">Today at 9:30 AM</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Member Since</div>
                            <div class="info-value">Jan 15, 2024</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; background: var(--green-light); border-radius: 12px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 14px; font-weight: 500; color: var(--dark);">Account Status</span>
                            <span style="background: var(--green-primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                <i class='bx bx-check-circle'></i> Active
                            </span>
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
                        <button class="btn-icon" title="Mark all as read">
                            <i class='bx bx-check-double'></i>
                        </button>
                        <button class="btn-icon" title="Settings">
                            <i class='bx bx-cog'></i>
                        </button>
                    </div>
                </div>

                <div style="display: grid; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: rgba(40, 167, 69, 0.05); border-radius: 12px; border-left: 4px solid var(--green-primary);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--green-primary); display: flex; align-items: center; justify-content: center; color: white;">
                            <i class='bx bx-check'></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 14px; font-weight: 600; color: var(--dark); margin-bottom: 4px;">System Backup Completed</h4>
                            <p style="color: var(--dark-grey); font-size: 13px;">Daily backup successfully completed at 3:00 AM</p>
                            <span style="color: var(--dark-grey); font-size: 12px;">2 hours ago</span>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: rgba(249, 115, 22, 0.05); border-radius: 12px; border-left: 4px solid var(--orange);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--orange); display: flex; align-items: center; justify-content: center; color: white;">
                            <i class='bx bx-error'></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 14px; font-weight: 600; color: var(--dark); margin-bottom: 4px;">Storage Usage Warning</h4>
                            <p style="color: var(--dark-grey); font-size: 13px;">Storage usage has reached 78%. Consider cleaning up old files.</p>
                            <span style="color: var(--dark-grey); font-size: 12px;">5 hours ago</span>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: rgba(6, 182, 212, 0.05); border-radius: 12px; border-left: 4px solid var(--info-cyan);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--info-cyan); display: flex; align-items: center; justify-content: center; color: white;">
                            <i class='bx bx-user-plus'></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 14px; font-weight: 600; color: var(--dark); margin-bottom: 4px;">New User Registration</h4>
                            <p style="color: var(--dark-grey); font-size: 13px;">3 new users have registered and are pending approval</p>
                            <span style="color: var(--dark-grey); font-size: 12px;">1 day ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script src="assets/js/components/sidebar.js?v=<?= time() ?>"></script>
    <script src="assets/js/components/navbar.js?v=<?= time() ?>"></script>
    <script src="assets/js/pages/dashboard.js?v=<?= time() ?>"></script>
</body>
</html>