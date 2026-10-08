<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Ensure super admin access
$currentUser = requireSuperAdmin();
if (!$currentUser) {
    header('Location: ../../login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        :root {
            --primary: #004d24;
            --secondary: #006b2e;
            --success: #1e7e34;
            --warning: #b8860b;
            --danger: #dc2626;
            --info: #006b2e;
            --light: #f4f8f5;
            --dark: #16241c;
            --line: #e6ebe7;
            --muted: #6b7280;
        }
        
        body {
            background-color: #f4f8f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark);
        }

        .reports-management-page #content main {
            padding-top: 8px;
        }
        
        /* Stat cards: white, bordered, tinted icon chip - no gradient floods. */
        .stat-card {
            transition: box-shadow .18s ease, border-color .18s ease;
            border-radius: 12px;
            border: 1px solid var(--line);
            box-shadow: 0 1px 3px rgba(24,47,31,.045);
            background: #fff;
            color: var(--dark);
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: none;
            border-color: #c8dccb;
            box-shadow: 0 6px 18px rgba(24,47,31,.09);
        }

        .stat-card .card-body {
            padding: 1.25rem 1rem;
        }
        
        .stat-card h3 {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 0 0 4px;
            line-height: 1.1;
        }

        .stat-card p {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .stat-card .badge {
            font-weight: 600;
            border: 1px solid var(--line);
        }
        
        .card {
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(24,47,31,.045);
            border: 1px solid var(--line);
            margin-bottom: 24px;
            background: #fff;
        }
        
        .card-header {
            background: #fff;
            color: var(--dark);
            border-radius: 11px 11px 0 0 !important;
            border-bottom: 1px solid var(--line);
            padding: 15px 20px;
            font-weight: 650;
        }

        .card-header h5,
        .card-header h5.mb-0 {
            color: var(--dark);
            font-weight: 650;
        }

        .card-header .btn-outline {
            color: var(--secondary);
            border: 1px solid var(--line);
            background: #fff;
        }

        .card-header .btn-outline:hover {
            background: var(--secondary);
            border-color: var(--secondary);
            color: #fff;
        }
        
        .report-nav {
            background: white;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px;
            box-shadow: 0 1px 3px rgba(24,47,31,.045);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
        }
        
        .report-nav .nav-link {
            border-radius: 8px;
            padding: 9px 16px;
            margin: 0;
            color: var(--dark);
            font-weight: 600;
            font-size: .9rem;
            transition: background-color .16s ease, color .16s ease;
            border: 1px solid transparent;
        }
        
        .report-nav .nav-link:hover {
            background-color: rgba(0, 107, 46, .07);
            color: var(--secondary);
        }
        
        .report-nav .nav-link.active {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
            box-shadow: none;
        }

        .report-nav .nav-link i {
            margin-right: 6px;
        }
        
        /* Activity entries: bordered rows with an icon, no thick left bar. */
        .activity-item {
            border: 1px solid var(--line);
            padding: 14px 16px;
            background: #fbfcfb;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: none;
            transition: box-shadow .18s ease, border-color .18s ease;
            height: 100%;
        }
        
        .activity-item:hover {
            border-color: #c8dccb;
            box-shadow: 0 4px 12px rgba(24,47,31,.07);
            transform: none;
        }

        .activity-item .badge.bg-primary {
            background: rgba(0, 107, 46, .10) !important;
            color: var(--secondary) !important;
            border: 1px solid rgba(0, 107, 46, .18);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .filter-section {
            background: white;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(24,47,31,.045);
            margin-bottom: 20px;
        }

        .filter-section h5 {
            font-weight: 650;
            color: var(--dark);
        }

        .filter-section .alert {
            background: #f2f8f3;
            border: 1px solid #d5e9d8;
            color: #1e5631;
            border-radius: 8px;
        }
        
        /* Page header: white card with a slim brand rule instead of a gradient slab */
        .page-header {
            background: #fff;
            color: var(--dark);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 22px 24px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(24,47,31,.045);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary) 0%, #34c759 55%, #f0c000 100%);
        }
        
        .page-header h2 {
            color: var(--dark);
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0 0 4px;
        }

        .page-header p {
            color: var(--muted);
        }

        .page-header .btn-light {
            background: #fff;
            border: 1px solid var(--line);
            color: var(--dark);
            font-weight: 600;
            border-radius: 8px;
        }

        .page-header .btn-light:hover {
            background: var(--secondary);
            border-color: var(--secondary);
            color: #fff;
        }
        
        .badge {
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 20px;
        }

        /* Rebrand Bootstrap's blue utility badges to the CVSU green. */
        .badge.bg-primary {
            background: #006b2e !important;
            color: #fff !important;
        }

        .badge.bg-warning {
            background: #d9a507 !important;
            color: #2b2100 !important;
        }
        
        .table {
            color: var(--dark);
            margin-bottom: 0;
        }

        .table > :not(caption) > * > * {
            background: transparent;
            border-color: #edf1ed;
            padding: .8rem .95rem;
        }
        
        .table th {
            border-top: none;
            font-weight: 700;
            font-size: 11px;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #647168;
        }

        .table thead.table-dark,
        .table thead.table-dark th {
            background: #f6f8f6 !important;
            color: #647168 !important;
            border-color: var(--line) !important;
        }

        .table tbody tr:hover td {
            background: rgba(0, 107, 46, .04);
        }
        
        .progress {
            height: 8px;
            border-radius: 10px;
            background: #e8efe9;
        }

        .progress-bar.bg-success {
            background: var(--secondary) !important;
        }
        
        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: background-color .16s ease, border-color .16s ease, color .16s ease;
        }
        
        .btn-primary {
            background: var(--secondary);
            border: 1px solid var(--secondary);
            color: #fff;
        }
        
        .btn-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: none;
            transform: none;
        }

        .form-select,
        .form-control {
            border-color: var(--line);
            border-radius: 8px;
            padding: .55rem .8rem;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 .18rem rgba(0, 107, 46, .12);
        }
        
        .custom-range-inputs {
            background: var(--light);
            border: 1px solid var(--line);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 12px;
            background: rgba(0, 107, 46, .09);
            color: var(--secondary);
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            border: 1px solid rgba(0, 107, 46, .14);
        }

        .card.stat-card.text-white .stat-icon {
            background: rgba(0, 107, 46, .09);
            color: var(--secondary);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 15px;
            opacity: 0.35;
            color: var(--secondary);
        }

        .avatar-placeholder {
            font-weight: 700;
            font-size: .85rem;
        }
        
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }

            .stat-card h3 {
                font-size: 1.4rem;
            }
            
            .chart-container {
                height: 250px;
            }

            .page-header {
                padding: 18px;
            }
        }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
    <link rel="stylesheet" href="assets/css/management-pages.css?v=1.0">
</head>
<body class="bg-light superadmin-management-page reports-management-page">
    <!-- Sidebar -->
    <?php include 'components/sidebar.html'; ?>
    
    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <?php include 'components/navbar.html'; ?>

        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h2>
                        <p class="mb-0 text-muted">Comprehensive system insights and statistics</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-light" onclick="window.print()">
                            <i class="fas fa-print me-2"></i> Print
                        </button>
                        <button class="btn btn-light" onclick="exportReport()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Report Type Navigation -->
            <ul class="nav nav-pills report-nav justify-content-center">
                <li class="nav-item">
                    <a class="nav-link <?php echo $report_type === 'overview' ? 'active' : ''; ?>" href="?type=overview&range=<?php echo $date_range; ?>">
                        <i class="fas fa-home me-2"></i>Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $report_type === 'departments' ? 'active' : ''; ?>" href="?type=departments&range=<?php echo $date_range; ?>">
                        <i class="fas fa-building me-2"></i>Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $report_type === 'users' ? 'active' : ''; ?>" href="?type=users&range=<?php echo $date_range; ?>">
                        <i class="fas fa-users me-2"></i>Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $report_type === 'activities' ? 'active' : ''; ?>" href="?type=activities&range=<?php echo $date_range; ?>">
                        <i class="fas fa-history me-2"></i>Activities
                    </a>
                </li>
            </ul>

            <!-- Date Range Filter -->
            <div class="filter-section">
                <h5 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>Filter Reports</h5>
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Date Range</label>
                        <select class="form-select" name="range" onchange="toggleCustomDates(this.value)">
                            <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="365" <?php echo $date_range == '365' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div id="custom-date-fields" class="col-md-5" style="display: <?php echo $date_range == 'custom' ? 'flex' : 'none'; ?>; gap: 15px;">
                        <div class="flex-grow-1">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-<?php echo $date_range == 'custom' ? '4' : '3'; ?>">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sync-alt me-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
                
                <!-- Report Period Info -->
                <div class="alert alert-info mt-3 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Showing data for: <strong><?php echo $range_label; ?></strong> 
                    (<?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?>)
                </div>
            </div>

            <!-- Overview Report -->
            <?php if ($report_type === 'overview'): ?>
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2 col-sm-6">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon">
                                    <i class="fas fa-file"></i>
                                </div>
                                <h3><?php echo number_format($data['stats']['files']['total_files']); ?></h3>
                                <p class="mb-1">Total Files</p>
                                <span class="badge bg-light text-dark">+<?php echo number_format($data['stats']['files']['new_files']); ?> new</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 col-sm-6">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <h3><?php echo number_format($data['stats']['folders']['total_folders']); ?></h3>
                                <p class="mb-1">Total Folders</p>
                                <span class="badge bg-light text-dark">+<?php echo number_format($data['stats']['folders']['new_folders']); ?> new</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 col-sm-6">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3><?php echo number_format($data['stats']['users']['total_users']); ?></h3>
                                <p class="mb-1">Total Users</p>
                                <span class="badge bg-light text-dark">+<?php echo number_format($data['stats']['users']['new_users']); ?> new</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 col-sm-6">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h3><?php echo number_format($data['stats']['documents']['total_requests']); ?></h3>
                                <p class="mb-1">Document Requests</p>
                                <span class="badge bg-light text-dark">+<?php echo number_format($data['stats']['documents']['new_requests']); ?> new</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 col-sm-6">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon">
                                    <i class="fas fa-share-alt"></i>
                                </div>
                                <h3><?php echo number_format($data['stats']['shares']['total_shares']); ?></h3>
                                <p class="mb-1">File Shares</p>
                                <span class="badge bg-light text-dark">+<?php echo number_format($data['stats']['shares']['new_shares']); ?> new</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 col-sm-6">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon">
                                    <i class="fas fa-hdd"></i>
                                </div>
                                <h3><?php echo formatFileSize($data['stats']['files']['total_size']); ?></h3>
                                <p class="mb-1">Storage Used</p>
                                <span class="badge bg-light text-dark">Total</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>File Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="fileDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Department Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="departmentActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h5>
                        <a href="?type=activities&range=<?php echo $date_range; ?>" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($data['activities'])): ?>
                            <div class="row">
                                <?php foreach (array_slice($data['activities'], 0, 6) as $activity): ?>
                                    <div class="col-lg-6 mb-3">
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></strong>
                                                    <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($activity['action']); ?></span>
                                                </div>
                                                <small class="text-muted"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($activity['description'] ?? 'No description available'); ?></p>
                                            <?php if ($activity['ip_address']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($activity['ip_address']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h5>No activities found</h5>
                                <p>No activities recorded for the selected period</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Department Report -->
            <?php if ($report_type === 'departments'): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Department Statistics</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Department</th>
                                        <th class="text-center">Users</th>
                                        <th class="text-center">Folders</th>
                                        <th class="text-center">Files</th>
                                        <th class="text-center">Storage Used</th>
                                        <th class="text-center">Activity Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data['departments'])): ?>
                                        <?php foreach ($data['departments'] as $dept): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($dept['department_code']); ?></small>
                                                    </div>
                                                </td>
                                                <td class="text-center"><span class="badge bg-primary"><?php echo number_format($dept['user_count']); ?></span></td>
                                                <td class="text-center"><span class="badge bg-warning"><?php echo number_format($dept['folder_count']); ?></span></td>
                                                <td class="text-center"><span class="badge bg-success"><?php echo number_format($dept['file_count']); ?></span></td>
                                                <td class="text-center"><?php echo formatFileSize($dept['total_size']); ?></td>
                                                <td>
                                                    <?php $activity_percent = min(100, ($dept['file_count'] / max(1, array_sum(array_column($data['departments'], 'file_count'))) * 100)); ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2">
                                                            <div class="progress-bar bg-success" style="width: <?php echo $activity_percent; ?>%"></div>
                                                        </div>
                                                        <small class="text-muted"><?php echo round($activity_percent); ?>%</small>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="fas fa-building"></i>
                                                    <h5>No department data</h5>
                                                    <p>No department statistics available for the selected period</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Users Report -->
            <?php if ($report_type === 'users'): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Top Active Users</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>User</th>
                                        <th class="text-center">Department</th>
                                        <th class="text-center">Files Uploaded</th>
                                        <th class="text-center">Total Size</th>
                                        <th class="text-center">Activities</th>
                                        <th class="text-center">Activity Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data['top_users'])): ?>
                                        <?php foreach ($data['top_users'] as $index => $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-placeholder me-3 rounded-circle text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background:#006b2e;">
                                                            <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1) . substr($user['surname'] ?? 'S', 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                            <br><small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($user['department_name']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($user['department_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><span class="badge bg-success"><?php echo number_format($user['files_uploaded']); ?></span></td>
                                                <td class="text-center"><?php echo formatFileSize($user['total_uploaded_size']); ?></td>
                                                <td class="text-center"><span class="badge bg-primary"><?php echo number_format($user['activities']); ?></span></td>
                                                <td>
                                                    <?php $score = ($user['files_uploaded'] * 2) + $user['activities']; ?>
                                                    <?php $max_score = max(array_map(function($u) { return ($u['files_uploaded'] * 2) + $u['activities']; }, $data['top_users'])); ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2">
                                                            <div class="progress-bar bg-success" style="width: <?php echo $max_score > 0 ? ($score / $max_score) * 100 : 0; ?>%"></div>
                                                        </div>
                                                        <span class="badge bg-warning"><?php echo $score; ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="fas fa-users"></i>
                                                    <h5>No user data</h5>
                                                    <p>No user statistics available for the selected period</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Activities Report -->
            <?php if ($report_type === 'activities'): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>System Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($data['activities'])): ?>
                            <div class="row">
                                <?php foreach ($data['activities'] as $activity): ?>
                                    <div class="col-lg-6 mb-3">
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></strong>
                                                    <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($activity['action']); ?></span>
                                                </div>
                                                <small class="text-muted"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($activity['description'] ?? 'No description available'); ?></p>
                                            <?php if ($activity['ip_address']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($activity['ip_address']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h5>No activities found</h5>
                                <p>No activities recorded for the selected period</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleCustomDates(value) {
            const customDateFields = document.getElementById('custom-date-fields');
            
            if (value === 'custom') {
                customDateFields.style.display = 'flex';
            } else {
                customDateFields.style.display = 'none';
            }
        }

        function exportReport() {
            // Implementation for PDF/Excel export
            alert('Export functionality would be implemented here. This could export the current report as PDF or Excel.');
        }

        // Initialize charts for overview report
        <?php if ($report_type === 'overview'): ?>
        // File Distribution Chart
        const fileCtx = document.getElementById('fileDistributionChart').getContext('2d');
        new Chart(fileCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active Files', 'New Files', 'Deleted Files'],
                datasets: [{
                    data: [
                        <?php echo $data['stats']['files']['total_files'] - $data['stats']['files']['deleted_files'] - $data['stats']['files']['new_files']; ?>,
                        <?php echo $data['stats']['files']['new_files']; ?>,
                        <?php echo $data['stats']['files']['deleted_files']; ?>
                    ],
                    backgroundColor: ['#006b2e', '#d4a72c', '#dc2626'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Department Activity Chart
        const deptCtx = document.getElementById('departmentActivityChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo '"' . implode('","', array_column($data['departments'], 'department_code')) . '"'; ?>],
                datasets: [{
                    label: 'Files',
                    data: [<?php echo implode(',', array_column($data['departments'], 'file_count')); ?>],
                    backgroundColor: '#0a8f3c',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
