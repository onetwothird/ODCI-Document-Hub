<?php
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current user information
$currentUser = getCurrentUser($pdo);

if (!$currentUser) {
    // User not found, logout
    header('Location: logout.php');
    exit();
}

// Check if user is approved
if (!$currentUser['is_approved']) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_not_approved');
    exit();
}

// Get department information for profile image - SAFE ACCESS
$departmentImage = null;
$departmentCode = null;

// Check if department_id exists and is not null
if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    try {
        $stmt = $pdo->prepare("SELECT department_code FROM departments WHERE id = ?");
        $stmt->execute([$currentUser['department_id']]);
        $department = $stmt->fetch();
        
        if ($department) {
            $departmentCode = $department['department_code'];
            $departmentImage = "../../img/{$departmentCode}.jpg";
        }
    } catch(Exception $e) {
        error_log("Department image error: " . $e->getMessage());
    }
}

// If getCurrentUser() doesn't include department info, get it separately
if (!isset($currentUser['department_id']) && isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.department_code, d.department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        $userDept = $stmt->fetch();
        
        if ($userDept && $userDept['department_id']) {
            $currentUser['department_id'] = $userDept['department_id'];
            $currentUser['department_name'] = $userDept['department_name'];
            $departmentCode = $userDept['department_code'];
            $departmentImage = "../../img/{$departmentCode}.jpg";
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

// Get comprehensive statistics for the dashboard
$stats = [
    'total_files' => 0,
    'total_folders' => 0,
    'storage_used' => 0,
    'recent_uploads' => [],
    'public_files' => 0,
    'favorite_files' => 0,
    'department_files' => 0,
    'downloads_today' => 0
];

try {
    // Get user's file count and storage - Updated query to handle files without folders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as file_count, 
               COALESCE(SUM(file_size), 0) as total_size,
               SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END) as public_count,
               SUM(CASE WHEN is_favorite = 1 THEN 1 ELSE 0 END) as favorite_count
        FROM files f 
        WHERE f.uploaded_by = ? AND f.is_deleted = 0
    ");
    $stmt->execute([$currentUser['id']]);
    $fileStats = $stmt->fetch();
    
    $stats['total_files'] = $fileStats['file_count'];
    $stats['storage_used'] = $fileStats['total_size'];
    $stats['public_files'] = $fileStats['public_count'] ?? 0;
    $stats['favorite_files'] = $fileStats['favorite_count'] ?? 0;
    
    // Get user's folder count
    $stmt = $pdo->prepare("SELECT COUNT(*) as folder_count FROM folders WHERE created_by = ? AND is_deleted = 0");
    $stmt->execute([$currentUser['id']]);
    $folderStats = $stmt->fetch();
    
    $stats['total_folders'] = $folderStats['folder_count'];
    
    // Get department files count
    if ($currentUser['department_id']) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as dept_files 
            FROM files f 
            LEFT JOIN folders fo ON f.folder_id = fo.id 
            WHERE (fo.department_id = ? OR f.uploaded_by IN (
                SELECT id FROM users WHERE department_id = ?
            )) AND f.is_deleted = 0
        ");
        $stmt->execute([$currentUser['department_id'], $currentUser['department_id']]);
        $deptStats = $stmt->fetch();
        $stats['department_files'] = $deptStats['dept_files'];
    }
    
    // Get downloads today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as downloads_today
        FROM files f
        WHERE f.uploaded_by = ? 
        AND DATE(f.last_downloaded) = CURDATE()
        AND f.is_deleted = 0
    ");
    $stmt->execute([$currentUser['id']]);
    $downloadStats = $stmt->fetch();
    $stats['downloads_today'] = $downloadStats['downloads_today'] ?? 0;
    
    // Get recent uploads with more details - LEFT JOIN to handle files without folders
    $stmt = $pdo->prepare("
        SELECT f.original_name, f.file_size, f.uploaded_at, 
               COALESCE(fo.folder_name, 'Uncategorized') as folder_name, 
               f.file_type, f.file_extension, 
               COALESCE(f.download_count, 0) as download_count, 
               COALESCE(f.is_public, 0) as is_public, 
               COALESCE(f.is_favorite, 0) as is_favorite
        FROM files f
        LEFT JOIN folders fo ON f.folder_id = fo.id
        WHERE f.uploaded_by = ? AND f.is_deleted = 0
        ORDER BY f.uploaded_at DESC
        LIMIT 8
    ");
    $stmt->execute([$currentUser['id']]);
    $stats['recent_uploads'] = $stmt->fetchAll();
    
} catch(Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    // Add debug information
    error_log("SQL Error details: " . print_r($pdo->errorInfo(), true));
}

// Format file size function
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get user's initials for fallback
function getUserInitials($fullName) {
    $names = explode(' ', trim($fullName));
    $initials = '';
    foreach ($names as $name) {
        if (!empty($name)) {
            $initials .= strtoupper($name[0]);
        }
    }
    return $initials ?: 'U';
}

// Get file type icon
function getFileTypeIcon($fileType) {
    if (empty($fileType)) {
        return 'bxs-file';
    }
    
    // Convert to lowercase for comparison
    $fileType = strtolower($fileType);
    
    $icons = [
        // Documents
        'pdf' => 'bxs-file-pdf',
        'doc' => 'bxs-file-doc',
        'docx' => 'bxs-file-doc',
        'txt' => 'bxs-file-txt',
        'rtf' => 'bxs-file-doc',
        
        // Spreadsheets
        'xls' => 'bxs-spreadsheet',
        'xlsx' => 'bxs-spreadsheet',
        'csv' => 'bxs-spreadsheet',
        
        // Presentations
        'ppt' => 'bxs-file-doc',
        'pptx' => 'bxs-file-doc',
        
        // Images
        'jpg' => 'bxs-image',
        'jpeg' => 'bxs-image',
        'png' => 'bxs-image',
        'gif' => 'bxs-image',
        'bmp' => 'bxs-image',
        'svg' => 'bxs-image',
        'webp' => 'bxs-image',
        
        // Videos
        'mp4' => 'bxs-videos',
        'avi' => 'bxs-videos',
        'mov' => 'bxs-videos',
        'wmv' => 'bxs-videos',
        'flv' => 'bxs-videos',
        'webm' => 'bxs-videos',
        
        // Audio
        'mp3' => 'bxs-music',
        'wav' => 'bxs-music',
        'flac' => 'bxs-music',
        'aac' => 'bxs-music',
        
        // Archives
        'zip' => 'bxs-file-archive',
        'rar' => 'bxs-file-archive',
        '7z' => 'bxs-file-archive',
        'tar' => 'bxs-file-archive',
        'gz' => 'bxs-file-archive',
        
        // Code
        'html' => 'bxs-file-html',
        'css' => 'bxs-file-css',
        'js' => 'bxs-file-js',
        'php' => 'bxs-file-doc',
        'py' => 'bxs-file-doc',
        'java' => 'bxs-file-doc',
        'cpp' => 'bxs-file-doc',
        'c' => 'bxs-file-doc'
    ];
    
    return $icons[$fileType] ?? 'bxs-file';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/pages/dashboard/file_table.css">
    <link rel="stylesheet" href="assets/css/pages/dashboard/grid_layout.css">
    <link rel="stylesheet" href="assets/css/pages/dashboard/profile_system.css">
    <link rel="stylesheet" href="assets/css/pages/dashboard/responsive.css">
    <link rel="stylesheet" href="assets/css/pages/dashboard/stats_card.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <?php if ($departmentCode): ?>
    <style>
        .profile::after {
            content: '<?php echo $departmentCode; ?>';
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: var(--blue);
            color: white;
            font-size: 8px;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: 500;
            min-width: 20px;
            text-align: center;
        }
    </style>
    <?php endif; ?>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="user-dashboard-page">
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <!-- Welcome Section -->
            <div class="head-title">
                <div class="left">
                    <h1>Welcome, <?php echo $userName; ?>!</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="#">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                            <a class="active" href="#">Overview</a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Enhanced Statistics Cards -->
            <ul class="box-info">
                <li>
                    <i class='bx bxs-file'></i>
                    <span class="text">
                        <h3><?php echo number_format($stats['total_files']); ?></h3>
                        <p>Total Files</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-folder'></i>
                    <span class="text">
                        <h3><?php echo number_format($stats['total_folders']); ?></h3>
                        <p>Total Folders</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-cloud'></i>
                    <span class="text">
                        <h3><?php echo formatFileSize($stats['storage_used']); ?></h3>
                        <p>Storage Used</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-download'></i>
                    <span class="text">
                        <h3><?php echo number_format($stats['downloads_today']); ?></h3>
                        <p>Downloads Today</p>
                    </span>
                </li>
            </ul>

            <!-- Dashboard Grid Layout -->
            <div class="dashboard-grid">
                <!-- Recent Files -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Recent Files</h3>
                        <div class="card-actions">
                            <i class='bx bx-search' title="Search Files"></i>
                            <i class='bx bx-filter' title="Filter Files"></i>
                            <i class='bx bx-refresh' title="Refresh"></i>
                        </div>
                    </div>
                    
                    <?php if (!empty($stats['recent_uploads'])): ?>
                        <div style="overflow-x: auto;">
                            <table class="files-table">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Folder</th>
                                        <th>Size</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($stats['recent_uploads'], 0, 6) as $file): ?>
                                        <tr>
                                            <td>
                                                <div class="file-info">
                                                    <div class="file-icon">
                                                        <i class='bx <?php echo getFileTypeIcon($file['file_type'] ?? $file['file_extension'] ?? pathinfo($file['original_name'], PATHINFO_EXTENSION)); ?>'></i>
                                                    </div>
                                                    <div class="file-details">
                                                        <p><?php echo htmlspecialchars(strlen($file['original_name']) > 30 ? substr($file['original_name'], 0, 30) . '...' : $file['original_name']); ?></p>
                                                        <div class="file-meta"><?php echo number_format($file['download_count'] ?? 0); ?> downloads</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($file['folder_name']); ?></td>
                                            <td><?php echo formatFileSize($file['file_size']); ?></td>
                                            <td>
                                                <?php if (isset($file['is_favorite']) && $file['is_favorite']): ?>
                                                    <span class="status-badge status-favorite">★ Favorite</span>
                                                <?php elseif (isset($file['is_public']) && $file['is_public']): ?>
                                                    <span class="status-badge status-public">🌐 Public</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-private">🔒 Private</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($stats['recent_uploads']) > 6): ?>
                            <div style="text-align: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(0,0,0,0.06);">
                                <a href="files.php" style="color: var(--blue); text-decoration: none; font-weight: 500; font-size: 14px;">
                                    View All Files (<?php echo count($stats['recent_uploads']); ?> total) →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-file'></i>
                            <p>No files uploaded yet</p>
                            <a href="folders.php">Upload your first file</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions & Info -->
                <div>
                    <!-- Quick Actions -->
                    <div class="dashboard-card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="quick-actions">
                            <a href="folders.php" class="action-item">
                                <div class="action-content">
                                    <div class="action-icon">
                                        <i class='bx bx-upload'></i>
                                    </div>
                                    <p>Upload Files</p>
                                </div>
                                <i class='bx bx-chevron-right action-arrow'></i>
                            </a>
                            
                            <a href="folders.php?action=create" class="action-item">
                                <div class="action-content">
                                    <div class="action-icon">
                                        <i class='bx bx-folder-plus'></i>
                                    </div>
                                    <p>Create Folder</p>
                                </div>
                                <i class='bx bx-chevron-right action-arrow'></i>
                            </a>
                            
                            <a href="folders.php" class="action-item">
                                <div class="action-content">
                                    <div class="action-icon">
                                    <i class='bx bx-folder-open'></i>
                                    </div>
                                    <p>Organize Files</p>
                                </div>
                                <i class='bx bx-chevron-right action-arrow'></i>
                            </a>
                            
                            <a href="folders.php" class="action-item">
                                <div class="action-content">
                                    <div class="action-icon">
                                        <i class='bx bx-share'></i>
                                    </div>
                                    <p>Share Documents</p>
                                </div>
                                <i class='bx bx-chevron-right action-arrow'></i>
                            </a>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Account Information</h3>
                            <div class="card-actions">
                                <i class='bx bx-edit' title="Edit Profile"></i>
                            </div>
                        </div>
                        <div class="account-info">
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?php echo htmlspecialchars($currentUser['department_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Employee ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($currentUser['employee_id'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Position</div>
                                <div class="info-value"><?php echo htmlspecialchars($currentUser['position'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($currentUser['created_at'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Last Login</div>
                                <div class="info-value">
                                    <?php 
                                    if ($currentUser['last_login']) {
                                        echo date('M j, Y g:i A', strtotime($currentUser['last_login']));
                                    } else {
                                        echo 'First login';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Account Status</div>
                                <div class="info-value" style="color: <?php echo $currentUser['is_approved'] ? '#059669' : '#dc2626'; ?>; font-weight: 600;">
                                    <?php echo $currentUser['is_approved'] ? '✓ Approved' : '⏳ Pending'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Statistics Row -->
            <div class="dashboard-grid" style="margin-top: 24px;">
                <!-- Storage & Activity -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Storage & Activity Overview</h3>
                        <div class="card-actions">
                            <i class='bx bx-bar-chart' title="View Analytics"></i>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 16px;">
                        <div class="info-item">
                            <div class="info-label">Public Files</div>
                            <div class="info-value" style="color: #059669;"><?php echo number_format($stats['public_files']); ?> files</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Favorite Files</div>
                            <div class="info-value" style="color: #d97706;"><?php echo number_format($stats['favorite_files']); ?> files</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Department Files</div>
                            <div class="info-value" style="color: var(--blue);"><?php echo number_format($stats['department_files']); ?> files</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Recent Activity</div>
                            <div class="info-value">
                                <?php 
                                $recentCount = count($stats['recent_uploads']);
                                echo $recentCount > 0 ? "$recentCount uploads this week" : "No recent activity";
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>System Status</h3>
                        <div class="card-actions">
                            <i class='bx bx-info-circle' title="System Information"></i>
                        </div>
                    </div>
                    <div class="account-info">
                        <div class="info-item">
                            <div class="info-label">Server Status</div>
                            <div class="info-value" style="color: #059669;">🟢 Online</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Backup Status</div>
                            <div class="info-value" style="color: #059669;">✓ Up to date</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Storage Health</div>
                            <div class="info-value" style="color: #059669;">Optimal</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Sync</div>
                            <div class="info-value"><?php echo date('g:i A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/pages/dashboard.js?v=<?= time() ?>"></script>
    <script src="assets/js/components/navbar.js?v=<?= time() ?>"></script>
</body>
</html>
