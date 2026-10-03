<?php include 'script/dashboard.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Dashboard - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">

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
                    <button class="btn-secondary">
                        <i class='bx bx-refresh'></i>
                        <span>Refresh</span>
                    </button>
                    <button class="btn-download">
                        <i class='bx bxs-cloud-upload'></i>
                        <span>Upload File</span>
                    </button>
                </div>
            </div>

            <!-- System Status Overview -->
            <div class="stats-overview slide up">
                <div class="status-item healthy">
                    <h4>Server Status</h4>
                    <div class="status-value">99.9%</div>
                    <p>Uptime</p>
                </div>
                <div class="status-item healthy">
                    <h4>Database</h4>
                    <div class="status-value">Healthy</div>
                    <p>All systems operational</p>
                </div>
                <div class="status-item warning">
                    <h4>Storage</h4>
                    <div class="status-value">78%</div>
                    <p>Used capacity</p>
                </div>
                <div class="status-item healthy">
                    <h4>Active Users</h4>
                    <div class="status-value">1,247</div>
                    <p>Currently online</p>
                </div>
            </div>

            <!-- Enhanced Statistics Cards -->
            <div class="stats-overview slide-up">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>12,847</h3>
                        <p>Total Files</p>
                        <div class="change positive">
                            <i class='bx bx-trending-up'></i>
                            <span>+12.5% from last month</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 75%;"></div>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class='bx bxs-file'></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>3,564</h3>
                        <p>Total Folders</p>
                        <div class="change positive">
                            <i class='bx bx-trending-up'></i>
                            <span>+8.2% from last month</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 60%;"></div>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class='bx bxs-folder'></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>2.4 TB</h3>
                        <p>Storage Used</p>
                        <div class="change negative">
                            <i class='bx bx-trending-down'></i>
                            <span>-3.1% from last month</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 45%;"></div>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class='bx bxs-cloud'></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>8,291</h3>
                        <p>Active Users</p>
                        <div class="change positive">
                            <i class='bx bx-trending-up'></i>
                            <span>+15.3% from last month</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 82%;"></div>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class='bx bxs-user-account'></i>
                    </div>
                </div>
            </div>

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

                    <table class="enhanced-table">
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
                                    <div class="file-item">
                                        <div class="file-icon">
                                            <i class='bx bxs-file-pdf'></i>
                                        </div>
                                        <div class="file-info">
                                            <h4>Research_Proposal_2024.pdf</h4>
                                            <p>File uploaded</p>
                                        </div>
                                    </div>
                                </td>
                                <td>Dr. Maria Santos</td>
                                <td>Computer Science</td>
                                <td>2 minutes ago</td>
                                <td><span style="background: var(--success); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Complete</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="file-item">
                                        <div class="file-icon">
                                            <i class='bx bxs-folder'></i>
                                        </div>
                                        <div class="file-info">
                                            <h4>Q1_Reports</h4>
                                            <p>Folder created</p>
                                        </div>
                                    </div>
                                </td>
                                <td>Prof. Juan Cruz</td>
                                <td>Mathematics</td>
                                <td>15 minutes ago</td>
                                <td><span style="background: var(--info); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">New</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="file-item">
                                        <div class="file-icon">
                                            <i class='bx bxs-file-doc'></i>
                                        </div>
                                        <div class="file-info">
                                            <h4>Curriculum_Update.docx</h4>
                                            <p>File shared</p>
                                        </div>
                                    </div>
                                </td>
                                <td>Dr. Ana Reyes</td>
                                <td>Engineering</td>
                                <td>1 hour ago</td>
                                <td><span style="background: var(--warning); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Shared</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="file-item">
                                        <div class="file-icon">
                                            <i class='bx bxs-user-plus'></i>
                                        </div>
                                        <div class="file-info">
                                            <h4>New User Registration</h4>
                                            <p>System action</p>
                                        </div>
                                    </div>
                                </td>
                                <td>System</td>
                                <td>Admin</td>
                                <td>2 hours ago</td>
                                <td><span style="background: var(--blue); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">System</span></td>
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
                        <div class="action-item" onclick="location.href='upload.php'">
                            <div class="action-info">
                                <div class="action-icon">
                                    <i class='bx bx-upload'></i>
                                </div>
                                <div>
                                    <h4>Upload Files</h4>
                                    <p>Add new documents</p>
                                </div>
                            </div>
                            <i class='bx bx-chevron-right'></i>
                        </div>

                        <div class="action-item" onclick="location.href='folders.php'">
                            <div class="action-info">
                                <div class="action-icon">
                                    <i class='bx bx-folder-plus'></i>
                                </div>
                                <div>
                                    <h4>Create Folder</h4>
                                    <p>Organize your files</p>
                                </div>
                            </div>
                            <i class='bx bx-chevron-right'></i>
                        </div>

                        <div class="action-item" onclick="location.href='view-faculty-staff.php'">
                            <div class="action-info">
                                <div class="action-icon">
                                    <i class='bx bx-user-plus'></i>
                                </div>
                                <div>
                                    <h4>Manage Users</h4>
                                    <p>Add or edit users</p>
                                </div>
                            </div>
                            <i class='bx bx-chevron-right'></i>
                        </div>

                        <div class="action-item" onclick="location.href='reports.php'">
                            <div class="action-info">
                                <div class="action-icon">
                                    <i class='bx bx-bar-chart-alt-2'></i>
                                </div>
                                <div>
                                    <h4>Generate Reports</h4>
                                    <p>View analytics</p>
                                </div>
                            </div>
                            <i class='bx bx-chevron-right'></i>
                        </div>

                        <div class="action-item" onclick="location.href='settings.php'">
                            <div class="action-info">
                                <div class="action-icon">
                                    <i class='bx bx-cog'></i>
                                </div>
                                <div>
                                    <h4>System Settings</h4>
                                    <p>Configure system</p>
                                </div>
                            </div>
                            <i class='bx bx-chevron-right'></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information Cards -->
            <div class="dashboard-grid">
                <div class="dashboard-card fade-in">
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

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                        <div style="text-align: center;">
                            <div style="font-size: 28px; font-weight: 700; color: var(--blue); margin-bottom: 8px;">2.4TB</div>
                            <div style="font-size: 14px; color: var(--dark-grey); margin-bottom: 12px;">Used Storage</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 78%;"></div>
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 28px; font-weight: 700; color: var(--orange); margin-bottom: 8px;">845GB</div>
                            <div style="font-size: 14px; color: var(--dark-grey); margin-bottom: 12px;">Available</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 22%; background: var(--orange);"></div>
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 28px; font-weight: 700; color: var(--success); margin-bottom: 8px;">12.5%</div>
                            <div style="font-size: 14px; color: var(--dark-grey); margin-bottom: 12px;">Growth Rate</div>
                            <div style="color: var(--success); font-size: 12px;">
                                <i class='bx bx-trending-up'></i> Monthly
                            </div>
                        </div>
                    </div>
                </div>

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

                    <div style="display: grid; gap: 20px;">
                        <div style="display: flex; align-items: center; gap: 16px;">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--blue), #20c997); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700;">
                                A
                            </div>
                            <div>
                                <h4 style="font-size: 18px; font-weight: 600; color: var(--dark); margin-bottom: 4px;">Administrator</h4>
                                <p style="color: var(--dark-grey); font-size: 14px;">Super Admin</p>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 14px;">
                            <div>
                                <strong style="color: var(--dark);">Department:</strong><br>
                                <span style="color: var(--dark-grey);">Information Technology</span>
                            </div>
                            <div>
                                <strong style="color: var(--dark);">Employee ID:</strong><br>
                                <span style="color: var(--dark-grey);">ADM-001</span>
                            </div>
                            <div>
                                <strong style="color: var(--dark);">Last Login:</strong><br>
                                <span style="color: var(--dark-grey);">Today at 9:30 AM</span>
                            </div>
                            <div>
                                <strong style="color: var(--dark);">Member Since:</strong><br>
                                <span style="color: var(--dark-grey);">Jan 15, 2024</span>
                            </div>
                        </div>

                        <div style="background: var(--grey); border-radius: 12px; padding: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 14px; font-weight: 500; color: var(--dark);">Account Status</span>
                                <span style="background: var(--success); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                    <i class='bx bx-check-circle'></i> Active
                                </span>
                            </div>
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
                    <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: rgba(40, 167, 69, 0.05); border-radius: 12px; border-left: 4px solid var(--success);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--success); display: flex; align-items: center; justify-content: center; color: white;">
                            <i class='bx bx-check'></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 14px; font-weight: 600; color: var(--dark); margin-bottom: 4px;">System Backup Completed</h4>
                            <p style="color: var(--dark-grey); font-size: 13px;">Daily backup successfully completed at 3:00 AM</p>
                            <span style="color: var(--dark-grey); font-size: 12px;">2 hours ago</span>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: rgba(255, 193, 7, 0.05); border-radius: 12px; border-left: 4px solid var(--warning);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--warning); display: flex; align-items: center; justify-content: center; color: white;">
                            <i class='bx bx-error'></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 14px; font-weight: 600; color: var(--dark); margin-bottom: 4px;">Storage Usage Warning</h4>
                            <p style="color: var(--dark-grey); font-size: 13px;">Storage usage has reached 78%. Consider cleaning up old files.</p>
                            <span style="color: var(--dark-grey); font-size: 12px;">5 hours ago</span>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: rgba(23, 162, 184, 0.05); border-radius: 12px; border-left: 4px solid var(--info);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--info); display: flex; align-items: center; justify-content: center; color: white;">
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

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/dashboard.js?v=<?= time() ?>"></script>
</body>
</html>