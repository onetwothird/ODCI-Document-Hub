<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = 'my_reports.php';

// Get user info
$stmt = $pdo->prepare("SELECT u.*, d.department_name, d.department_code FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get report type filter
$report_type = $_GET['type'] ?? 'file_uploads';
$allowed_report_types = ['file_uploads', 'document_submissions', 'activity_summary', 'storage_usage'];
if (!in_array($report_type, $allowed_report_types, true)) {
    $report_type = 'file_uploads';
}

// Default to "All Years" so the report is never blank on first load.
// Historical uploads are backfilled from their upload date, so the current
// calendar year would otherwise exclude them.
$academic_year = $_GET['academic_year'] ?? '';
$semester = $_GET['semester'] ?? '';

// Get available academic years from files
$stmt = $pdo->prepare("SELECT DISTINCT academic_year FROM files WHERE uploaded_by = ? AND academic_year IS NOT NULL ORDER BY academic_year DESC");
$stmt->execute([$user_id]);
$academic_years = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($academic_years)) {
    $academic_years = [date('Y')];
}

$reports_data = [];

switch ($report_type) {
    case 'file_uploads':
        // File upload statistics, aggregated per folder / semester / year.
        // NOTE: only the grouping columns and aggregates may be selected.
        // Selecting "f.*" here breaks under MySQL's ONLY_FULL_GROUP_BY mode.
        $query = "
            SELECT 
                f.folder_id,
                fo.folder_name,
                f.semester,
                f.academic_year,
                COUNT(*) as file_count,
                COALESCE(SUM(f.file_size), 0) as total_size,
                MIN(f.uploaded_at) as first_upload,
                MAX(f.uploaded_at) as latest_upload
            FROM files f
            LEFT JOIN folders fo ON f.folder_id = fo.id
            WHERE f.uploaded_by = ? AND f.is_deleted = 0
        ";
        
        $params = [$user_id];
        
        if ($academic_year !== '') {
            $query .= " AND f.academic_year = ?";
            $params[] = $academic_year;
        }
        
        if ($semester !== '') {
            $query .= " AND f.semester = ?";
            $params[] = $semester;
        }
        
        $query .= " GROUP BY f.folder_id, fo.folder_name, f.semester, f.academic_year
                    ORDER BY latest_upload DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reports_data = $stmt->fetchAll();
        break;
        
    case 'document_submissions':
        // Document submission tracker.
        // File totals are pre-aggregated in a derived table: joining
        // document_requirements directly would multiply the file counts
        // whenever a requirement row matches more than once.
        $query = "
            SELECT 
                ds.id,
                ds.document_type,
                ds.semester,
                ds.academic_year,
                ds.submitted_at,
                (SELECT MAX(dr.deadline_date) FROM document_requirements dr
                  WHERE dr.document_type = ds.document_type
                    AND dr.semester      = ds.semester
                    AND dr.academic_year = ds.academic_year) as deadline_date,
                (SELECT MAX(dr.is_required) FROM document_requirements dr
                  WHERE dr.document_type = ds.document_type
                    AND dr.semester      = ds.semester
                    AND dr.academic_year = ds.academic_year) as is_required,
                COALESCE(df.file_count, 0) as file_count,
                COALESCE(df.total_size, 0) as total_size
            FROM faculty_document_submissions ds
            LEFT JOIN (
                SELECT submission_id,
                       COUNT(*) as file_count,
                       COALESCE(SUM(file_size), 0) as total_size
                FROM document_files
                GROUP BY submission_id
            ) df ON df.submission_id = ds.id
            WHERE ds.faculty_id = ?
        ";
        
        $params = [$user_id];
        
        if ($academic_year !== '') {
            $query .= " AND ds.academic_year = ?";
            $params[] = $academic_year;
        }
        
        if ($semester !== '') {
            $query .= " AND ds.semester = ?";
            $params[] = $semester;
        }
        
        $query .= " ORDER BY ds.submitted_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reports_data = $stmt->fetchAll();
        break;
        
    case 'activity_summary':
        // Activity summary
        $query = "
            SELECT 
                DATE(created_at) as activity_date,
                action,
                resource_type,
                COUNT(*) as count,
                GROUP_CONCAT(DISTINCT description SEPARATOR '; ') as descriptions
            FROM activity_logs 
            WHERE user_id = ?
        ";
        
        $params = [$user_id];
        
        if ($academic_year !== '') {
            $query .= " AND YEAR(created_at) = ?";
            $params[] = $academic_year;
        }
        
        $query .= " GROUP BY DATE(created_at), action, resource_type ORDER BY activity_date DESC LIMIT 50";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reports_data = $stmt->fetchAll();
        break;
        
    case 'storage_usage':
        // Storage usage by folder and file type
        $query = "
            SELECT 
                fo.id as folder_id,
                fo.folder_name,
                f.file_extension,
                COUNT(*) as file_count,
                COALESCE(SUM(f.file_size), 0) as total_size,
                COALESCE(AVG(f.file_size), 0) as avg_size,
                MAX(f.uploaded_at) as latest_upload
            FROM files f
            LEFT JOIN folders fo ON f.folder_id = fo.id
            WHERE f.uploaded_by = ? AND f.is_deleted = 0
        ";
        
        $params = [$user_id];
        
        if ($academic_year !== '') {
            $query .= " AND f.academic_year = ?";
            $params[] = $academic_year;
        }
        
        $query .= " GROUP BY fo.id, fo.folder_name, f.file_extension ORDER BY total_size DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reports_data = $stmt->fetchAll();
        break;
}

// Get summary statistics
$stats = [];

// Total files uploaded
$stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE uploaded_by = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$stats['total_files'] = $stmt->fetchColumn();

// Total storage used
$stmt = $pdo->prepare("SELECT COALESCE(SUM(file_size), 0) FROM files WHERE uploaded_by = ? AND is_deleted = 0");
$stmt->execute([$user_id]);
$stats['total_storage'] = $stmt->fetchColumn();

// Document submissions
$stmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_document_submissions WHERE faculty_id = ?");
$stmt->execute([$user_id]);
$stats['total_submissions'] = $stmt->fetchColumn();

// Recent activity count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute([$user_id]);
$stats['recent_activities'] = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - ODCI</title>
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

    .report-count {
        font-size: 14px;
        color: #666;
        background: #f8f9fa;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
    }

    .report-table-wrapper {
        overflow-x: auto;
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        margin: 1rem 0;
    }

    .report-table th {
        background: #f8f9fa;
        padding: 1rem 0.75rem;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
    }

    .report-table td {
        padding: 0.75rem;
        border-bottom: 1px solid #dee2e6;
        vertical-align: top;
    }

    .report-table tbody tr:hover {
        background: #f8f9fa;
    }

    .description-cell {
        max-width: 300px;
        word-wrap: break-word;
        font-size: 13px;
    }

    .file-extension {
        background: #667eea;
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-on-time {
        background: #d4edda;
        color: #155724;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-late {
        background: #f8d7da;
        color: #721c24;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
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

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .report-table {
            font-size: 14px;
        }
        
        .report-table th,
        .report-table td {
            padding: 0.5rem;
        }
    }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="user-my-reports-page">
    <?php include 'components/sidebar.html'; ?>

    <section id="content">
        <?php include 'components/navbar.html'; ?>
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>My Reports</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">My Reports</a></li>
                    </ul>
                </div>
            </div>

            <!-- Statistics Overview -->
            <ul class="box-info">
                <li>
                    <i class='bx bxs-file'></i>
                    <span class="text">
                        <h3><?= number_format($stats['total_files']) ?></h3>
                        <p>Total Files</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-data'></i>
                    <span class="text">
                        <h3><?= number_format($stats['total_storage'] / (1024*1024), 2) ?> MB</h3>
                        <p>Storage Used</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-check-square'></i>
                    <span class="text">
                        <h3><?= number_format($stats['total_submissions']) ?></h3>
                        <p>Submissions</p>
                    </span>
                </li>
                <li>
                    <i class='bx bxs-time'></i>
                    <span class="text">
                        <h3><?= number_format($stats['recent_activities']) ?></h3>
                        <p>Recent Activities</p>
                    </span>
                </li>
            </ul>

            <!-- Report Filters -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Report Filters</h3>
                    </div>
                    <form method="GET" class="filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Report Type:</label>
                                <select id="reportTypeFilter" name="type">
                                    <option value="file_uploads" <?= $report_type == 'file_uploads' ? 'selected' : '' ?>>File Uploads</option>
                                    <option value="document_submissions" <?= $report_type == 'document_submissions' ? 'selected' : '' ?>>Document Submissions</option>
                                    <option value="activity_summary" <?= $report_type == 'activity_summary' ? 'selected' : '' ?>>Activity Summary</option>
                                    <option value="storage_usage" <?= $report_type == 'storage_usage' ? 'selected' : '' ?>>Storage Usage</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Academic Year:</label>
                                <select id="academicYearFilter" name="academic_year">
                                    <option value="">All Years</option>
                                    <?php foreach ($academic_years as $year): ?>
                                        <option value="<?= $year ?>" <?= $academic_year == $year ? 'selected' : '' ?>><?= $year ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Semester:</label>
                                <select id="semesterFilter" name="semester">
                                    <option value="">All Semesters</option>
                                    <option value="first" <?= $semester == 'first' ? 'selected' : '' ?>>First Semester</option>
                                    <option value="second" <?= $semester == 'second' ? 'selected' : '' ?>>Second Semester</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Generate Report</button>
                                <button type="button" onclick="exportReport()" class="btn btn-secondary">Export CSV</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Results -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>
                            <?= ucwords(str_replace('_', ' ', $report_type)) ?> Report
                            <?php if ($academic_year): ?>(<?= $academic_year ?>)<?php endif; ?>
                            <?php if ($semester): ?>(<?= ucfirst($semester) ?> Semester)<?php endif; ?>
                        </h3>
                        <span class="report-count"><?= count($reports_data) ?> records</span>
                    </div>
                    
                    <?php if (empty($reports_data)): ?>
                        <div class="empty-state">
                            <i class='bx bx-chart'></i>
                            <p>No data available for the selected criteria</p>
                        </div>
                    <?php else: ?>
                        <div class="report-table-wrapper">
                            <?php if ($report_type == 'file_uploads'): ?>
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Folder</th>
                                            <th>Academic Year</th>
                                            <th>Semester</th>
                                            <th>File Count</th>
                                            <th>Total Size</th>
                                            <th>First Upload</th>
                                            <th>Latest Upload</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports_data as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['folder_name'] ?: 'No Folder') ?></td>
                                                <td><?= htmlspecialchars($row['academic_year']) ?></td>
                                                <td><?= htmlspecialchars(ucfirst($row['semester'])) ?></td>
                                                <td><?= number_format($row['file_count']) ?></td>
                                                <td><?= number_format($row['total_size'] / 1024, 2) ?> KB</td>
                                                <td><?= date('M d, Y', strtotime($row['first_upload'])) ?></td>
                                                <td><?= date('M d, Y', strtotime($row['latest_upload'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            
                            <?php elseif ($report_type == 'document_submissions'): ?>
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Document Type</th>
                                            <th>Academic Year</th>
                                            <th>Semester</th>
                                            <th>Deadline</th>
                                            <th>Submitted Date</th>
                                            <th>File Count</th>
                                            <th>Total Size</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports_data as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['document_type']) ?></td>
                                                <td><?= htmlspecialchars($row['academic_year']) ?></td>
                                                <td><?= htmlspecialchars($row['semester']) ?></td>
                                                <td><?= $row['deadline_date'] ? date('M d, Y', strtotime($row['deadline_date'])) : 'No deadline' ?></td>
                                                <td><?= date('M d, Y', strtotime($row['submitted_at'])) ?></td>
                                                <td><?= number_format($row['file_count']) ?></td>
                                                <td><?= number_format(($row['total_size'] ?: 0) / 1024, 2) ?> KB</td>
                                                <td>
                                                    <?php
                                                    $deadline = $row['deadline_date'] ? strtotime($row['deadline_date']) : null;
                                                    $submitted = strtotime($row['submitted_at']);
                                                    if ($deadline && $submitted > $deadline) {
                                                        echo '<span class="status-late">Late</span>';
                                                    } else {
                                                        echo '<span class="status-on-time">On Time</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            
                            <?php elseif ($report_type == 'activity_summary'): ?>
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Action</th>
                                            <th>Resource Type</th>
                                            <th>Count</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports_data as $row): ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($row['activity_date'])) ?></td>
                                                <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['action']))) ?></td>
                                                <td><?= htmlspecialchars(ucwords($row['resource_type'])) ?></td>
                                                <td><?= number_format($row['count']) ?></td>
                                                <td class="description-cell"><?= htmlspecialchars($row['descriptions']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            
                            <?php elseif ($report_type == 'storage_usage'): ?>
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Folder</th>
                                            <th>File Extension</th>
                                            <th>File Count</th>
                                            <th>Total Size</th>
                                            <th>Average Size</th>
                                            <th>Latest Upload</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports_data as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['folder_name'] ?: 'No Folder') ?></td>
                                                <td>
                                                    <span class="file-extension">.<?= htmlspecialchars($row['file_extension'] ?: 'unknown') ?></span>
                                                </td>
                                                <td><?= number_format($row['file_count']) ?></td>
                                                <td><?= number_format($row['total_size'] / (1024*1024), 2) ?> MB</td>
                                                <td><?= number_format($row['avg_size'] / 1024, 2) ?> KB</td>
                                                <td><?= date('M d, Y', strtotime($row['latest_upload'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </section>

    

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/components/navbar.js?v=<?= time() ?>"></script>
    <script>
    function exportReport() {
        const reportType = document.getElementById('reportTypeFilter')?.value || 'file_uploads';
        const academicYear = document.getElementById('academicYearFilter')?.value || '';
        const semester = document.getElementById('semesterFilter')?.value || '';
        const table = document.querySelector('.report-table');

        const fallbackHeaders = {
            file_uploads: ['Folder', 'Academic Year', 'Semester', 'File Count', 'Total Size', 'First Upload', 'Latest Upload'],
            document_submissions: ['Document Type', 'Academic Year', 'Semester', 'Deadline', 'Submitted Date', 'File Count', 'Total Size', 'Status'],
            activity_summary: ['Date', 'Action', 'Resource Type', 'Count', 'Description'],
            storage_usage: ['Folder', 'File Extension', 'File Count', 'Total Size', 'Average Size', 'Latest Upload']
        };

        const headers = table
            ? Array.from(table.querySelectorAll('thead th'), cell => cell.textContent.trim())
            : fallbackHeaders[reportType];

        if (!headers) {
            console.error('Cannot export an unsupported report type:', reportType);
            return;
        }

        const rows = table
            ? Array.from(table.querySelectorAll('tbody tr'))
                .filter(row => !row.querySelector('.empty-state'))
                .map(row => Array.from(row.querySelectorAll('td'), cell => cell.textContent.trim()))
            : [];
        const escapeCsv = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
        const csvContent = [headers, ...rows]
            .map(row => row.map(escapeCsv).join(','))
            .join('\r\n');
        const blob = new Blob(['\uFEFF', csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        const safeYear = academicYear.replace(/[^a-zA-Z0-9-]/g, '') || 'all';
        const safeSemester = semester.replace(/[^a-zA-Z0-9-]/g, '') || 'all';
        link.href = url;
        link.download = `${reportType}_report_${safeYear}_${safeSemester}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => window.URL.revokeObjectURL(url), 1000);
    }
    </script>
</body>
</html>