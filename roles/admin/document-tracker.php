<?php include 'script/tracker.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Tracker - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    
    <!-- Modular CSS - Base & Components -->
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
    
    <!-- Page-specific CSS (matching dashboard structure) -->
    <link rel="stylesheet" href="assets/css/pages/document-tracker/stats_card.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/pages/document-tracker/grid_layout.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/pages/document-tracker/table.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/pages/document-tracker/responsive.css?v=<?= time() ?>">
    
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
                    <h1>Document Submission Tracker</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Document Tracker</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><span class="scope-note"><?= htmlspecialchars($admin_department_name) ?></span></li>
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

            <!-- System Status Overview -->
            <ul class="box-info stats-overview">
                <li class="status-item <?= $total_faculty > 0 ? 'healthy' : 'warning' ?>">
                    <div class="stat-head">
                        <i class='bx bxs-user-account'></i>
                        <h3><?= number_format($total_faculty) ?></h3>
                    </div>
                    <p class="stat-label">Total Faculty &middot; in Department</p>
                </li>
                <li class="status-item <?= $complete_faculty > 0 ? 'healthy' : 'warning' ?>">
                    <div class="stat-head">
                        <i class='bx bxs-check-circle'></i>
                        <h3><?= number_format($complete_faculty) ?></h3>
                    </div>
                    <p class="stat-label">Complete Submissions &middot; <?= $total_faculty > 0 ? number_format($complete_faculty) . ' of ' . number_format($total_faculty) . ' faculty' : '' ?></p>
                </li>
            </ul>

            <!-- Enhanced Statistics Cards -->
            <ul class="box-info">
                <li>
                    <div class="stat-head">
                        <i class='bx bx-cloud-upload'></i>
                        <h3><?= number_format($submitted_count) ?></h3>
                    </div>
                    <p class="stat-label">Documents Submitted</p>
                    <div class="change <?= $total_possible > 0 ? 'positive' : 'neutral' ?>">
                        <i class='bx bx-up-arrow-alt'></i>
                        <span>Out of <?= number_format($total_possible) ?> required</span>
                    </div>
                    <div class="progress-bar" title="<?= $total_possible > 0 ? number_format(($submitted_count / $total_possible) * 100, 1) : 0 ?>% completion rate">
                        <div class="progress-fill" style="width: <?= $total_possible > 0 ? ($submitted_count / $total_possible) * 100 : 0 ?>%;"></div>
                    </div>
                    <div class="bar-note"><?= $total_possible > 0 ? number_format(($submitted_count / $total_possible) * 100, 1) : 0 ?>% of all required documents</div>
                </li>

                <li>
                    <div class="stat-head">
                        <i class='bx bxs-pie-chart-alt'></i>
                        <h3><?= number_format($completion_rate, 1) ?>%</h3>
                    </div>
                    <p class="stat-label">Overall Completion</p>
                    <div class="change positive">
                        <i class='bx bx-trending-up'></i>
                        <span>Document submission rate</span>
                    </div>
                    <div class="progress-bar" title="<?= number_format($completion_rate, 1) ?>% overall completion">
                        <div class="progress-fill" style="width: <?= $completion_rate ?>%;"></div>
                    </div>
                    <div class="bar-note"><?= $faculty_completion_rate ?>% of faculty have complete submissions</div>
                </li>

                <li>
                    <div class="stat-head">
                        <i class='bx bxs-file'></i>
                        <h3><?= count($document_types) ?></h3>
                    </div>
                    <p class="stat-label">Document Types Required</p>
                    <div class="change neutral">
                        <i class='bx bx-list-ul'></i>
                        <span>Tracked per faculty per period</span>
                    </div>
                    <div class="progress-bar" title="100% of document types configured">
                        <div class="progress-fill" style="width: 100%;"></div>
                    </div>
                    <div class="bar-note">All <?= count($document_types) ?> types active for tracking</div>
                </li>

                <li>
                    <div class="stat-head">
                        <i class='bx bxs-calendar'></i>
                        <h3><?= htmlspecialchars($selectedSemester) ?></h3>
                    </div>
                    <p class="stat-label">Active Period</p>
                    <div class="change neutral">
                        <i class='bx bx-time'></i>
                        <span>Academic Year Tracking</span>
                    </div>
                    <div class="progress-bar" title="Current tracking period">
                        <div class="progress-fill" style="width: 100%;"></div>
                    </div>
                    <div class="bar-note">Filter by period above to change view</div>
                </li>
            </ul>

            <!-- Main Dashboard Content -->
            <div class="dashboard-grid">
                <!-- Document Matrix Table -->
                <div class="dashboard-card fade-in">
                    <div class="card-header">
                        <h3>
                            <i class='bx bxs-spreadsheet'></i>
                            Faculty Document Tracker
                        </h3>
                        <div class="card-actions">
                            <button class="btn-icon" title="Filter" onclick="document.getElementById('periodFilterForm').scrollIntoView({behavior: 'smooth'})">
                                <i class='bx bx-filter'></i>
                            </button>
                            <button class="btn-icon" title="Refresh" type="button" onclick="window.location.reload()">
                                <i class='bx bx-refresh'></i>
                            </button>
                            <button class="btn-icon" title="Export" onclick="exportTable()">
                                <i class='bx bx-download'></i>
                            </button>
                        </div>
                    </div>

                    <!-- Period Filter (simplified, inline with dashboard style) -->
                    <div class="period-filter-inline">
                        <form method="GET" id="periodFilterForm" class="filter-form-inline">
                            <div class="filter-group">
                                <label for="semesterSelect" class="visually-hidden">Academic Period</label>
                                <div class="select-wrapper">
                                    <select name="semester" class="period-select" id="semesterSelect" onchange="this.form.submit()">
                                        <?php
                                        // Periods are derived from the same `files` + `folders`
                                        // pair the submission matrix reads, so periods that only
                                        // exist because of a roles/user/folders.php upload are
                                        // listed here as well.
                                        $period_query = "
                                            SELECT DISTINCT
                                                " . odci_academic_year_expr('f.academic_year') . " AS start_year,
                                                f.semester AS semester,
                                                COUNT(f.id) AS file_count
                                            FROM files f
                                            INNER JOIN folders fo ON f.folder_id = fo.id
                                            INNER JOIN users u ON f.uploaded_by = u.id
                                            WHERE u.role = 'user' AND u.is_approved = 1
                                              AND f.is_deleted = 0 AND fo.is_deleted = 0
                                              AND fo.category IS NOT NULL
                                        ";
                                        
                                        $period_params = [];
                                        if ($admin_department_id) {
                                            $period_query .= " AND u.department_id = ?";
                                            $period_params[] = $admin_department_id;
                                        }
                                        
                                        $period_query .= " 
                                            GROUP BY start_year, f.semester 
                                            ORDER BY start_year DESC, 
                                            CASE f.semester
                                                WHEN 'first' THEN 1
                                                WHEN 'second' THEN 2
                                                ELSE 3
                                            END ASC
                                        ";
                                        
                                        $stmt = $pdo->prepare($period_query);
                                        $stmt->execute($period_params);
                                        $available_periods = $stmt->fetchAll();
                                        
                                        if (empty($available_periods)) {
                                            echo '<option value="">No data available</option>';
                                        } else {
                                            foreach ($available_periods as $period) {
                                                if ((int)$period['start_year'] < 1) {
                                                    continue;
                                                }
                                                $period_value = odci_period_key($period['start_year'], $period['semester']);
                                                $period_display = $period_value . ' (' . $period['file_count'] . ' files)';
                                                $selected = ($selectedSemester == $period_value) ? 'selected' : '';
                                                echo "<option value=\"" . htmlspecialchars($period_value, ENT_QUOTES) . "\" {$selected}>" . htmlspecialchars($period_display) . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                    <i class="bx bx-chevron-down select-arrow"></i>
                                </div>
                            </div>
                            
                            <!-- Preserve other filters -->
                            <input type="hidden" name="department" value="<?= htmlspecialchars($department_filter) ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                            
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bx bx-refresh"></i>
                                <span>Update</span>
                            </button>
                        </form>
                    </div>

                    <!-- Table Header Info -->
                    <div class="table-header-info">
                        <div class="header-content">
                            <h2>CAVITE STATE UNIVERSITY - NAIC CAMPUS</h2>
                            <div class="header-details">
                                <div class="department-badge">
                                    <i class="bx bx-building"></i>
                                    <?= htmlspecialchars($admin_department_name) ?>
                                </div>
                                <div class="period-badge">
                                    <i class="bx bx-time"></i>
                                    <?= htmlspecialchars($selectedSemester) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Document Table -->
                    <div class="table-container">
                        <?php if (empty($faculty)): ?>
                            <div class="empty-state">
                                <i class='bx bx-user-x'></i>
                                <h3>No Faculty Members Found</h3>
                                <p>No faculty members match the selected period or filters.</p>
                                <a href="document-tracker.php" class="btn btn-primary">Reset Filters</a>
                            </div>
                        <?php else: ?>
                            <div class="table-scroll-wrapper">
                                <p class="table-scroll-hint">
                                    <i class='bx bx-move-horizontal'></i>
                                    Swipe the table sideways to see every document type
                                </p>
                                <table class="doc-table enhanced-table" id="documentTable">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="faculty-cell" style="min-width: 260px;">
                                                FACULTY STAFF
                                                <small style="display: block; font-weight: normal; text-transform: none; margin-top: 5px;">Click to view details</small>
                                            </th>
                                            <th colspan="<?= count($document_types) ?>" style="text-align: center;">
                                                DOCUMENTS (Auto-tracked by File Uploads)
                                            </th>
                                            <th rowspan="2" class="progress-header" style="min-width: 120px; text-align: center;">PROGRESS</th>
                                        </tr>
                                        <tr>
                                            <?php foreach ($document_types as $doc_type): ?>
                                                <th title="<?= htmlspecialchars($doc_type) ?>" style="writing-mode: vertical-lr; text-orientation: mixed; min-width: 70px; text-align: center; height: 132px; padding: 10px 6px; vertical-align: bottom;">
                                                    <span style="writing-mode: horizontal-tb; font-size: 11px; font-weight: 600;">
                                                        <?= strlen($doc_type) > 18 ? substr($doc_type, 0, 18) . '...' : $doc_type ?>
                                                    </span>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($faculty as $staff): 
                                            $staff_submitted = 0;
                                            foreach ($document_types as $dt) {
                                                if (isset($file_submissions[$staff['id']][$dt])) {
                                                    $staff_submitted++;
                                                }
                                            }
                                            $staff_progress = count($document_types) > 0 ? round(($staff_submitted / count($document_types)) * 100) : 0;
                                            
                                            // Get first letter for avatar fallback
                                            $first_letter = strtoupper(substr($staff['name'], 0, 1));
                                            
                                            // Use the processed profile image URL
                                            $has_profile_image = !empty($staff['profile_image_url']);
                                        ?>
                                        <tr data-faculty-id="<?= $staff['id'] ?>" data-progress="<?= $staff_progress ?>">
                                            <td class="faculty-cell" onclick="showFacultyDetails(<?= $staff['id'] ?>)" style="cursor: pointer;">
                                                <div class="faculty-compact">
                                                    <div class="faculty-avatar-small" style="width: 44px; height: 44px; border-radius: 50%; overflow: hidden; margin-right: 12px; flex-shrink: 0; position: relative; border: 3px solid #e9ecef;">
                                                        <?php if ($has_profile_image): ?>
                                                            <img src="<?= htmlspecialchars($staff['profile_image_url']) ?>" 
                                                                alt="<?= htmlspecialchars($staff['name'] . ' ' . $staff['surname']) ?>" 
                                                                style="width: 100%; height: 100%; object-fit: cover;"
                                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                            <div class="fallback-avatar" style="display: none; width: 100%; height: 100%; 
                                                                background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); 
                                                                color: white; font-size: 16px; font-weight: bold; 
                                                                display: flex; align-items: center; justify-content: center;">
                                                                <?= $first_letter ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="letter-avatar" style="width: 100%; height: 100%; 
                                                                background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); 
                                                                color: white; font-size: 16px; font-weight: bold; 
                                                                display: flex; align-items: center; justify-content: center;">
                                                                <?= $first_letter ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Status indicator -->
                                                        <div class="status-indicator" style="position: absolute; bottom: 2px; right: 2px; 
                                                            width: 12px; height: 12px; background: #28a745; border: 2px solid #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,.25); 
                                                            border-radius: 50%;"></div>
                                                    </div>
                                                    
                                                    <div class="faculty-basic-info" style="flex: 1; min-width: 0;">
                                                        <div class="faculty-name" style="font-weight: 600; font-size: 14px; color: #2c3e50; 
                                                            margin-bottom: 4px; line-height: 1.2;">
                                                            <?= htmlspecialchars($staff['surname'] . ', ' . $staff['name']) ?>
                                                            <?= !empty($staff['mi']) ? ' ' . htmlspecialchars($staff['mi']) . '.' : '' ?>
                                                        </div>
                                                        
                                                        <?php if (!empty($staff['employee_id'])): ?>
                                                            <div class="faculty-id" style="font-size: 12px; color: #6c757d; 
                                                                display: flex; align-items: center; gap: 4px; margin-bottom: 2px;">
                                                                <i class='bx bx-id-card' style="font-size: 12px;"></i>
                                                                <?= htmlspecialchars($staff['employee_id']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    
                                                    </div>
                                                    
                                                    <div class="faculty-expand-icon" style="margin-left: 10px; color: #adb5bd;">
                                                        <i class='bx bx-chevron-right' style="font-size: 18px;"></i>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <?php foreach ($document_types as $doc_type): ?>
                                                <?php 
                                                    $file_submission = $file_submissions[$staff['id']][$doc_type] ?? null;
                                                    $has_files = !empty($file_submission);
                                                    
                                                    $cell_class = 'status-cell';
                                                    if ($has_files) {
                                                        $cell_class .= ' submitted';
                                                    } else {
                                                        $cell_class .= ' not-submitted';
                                                    }
                                                ?>
                                                <td class="<?= $cell_class ?>" 
                                                    data-faculty-id="<?= $staff['id'] ?>"
                                                    data-doc-type="<?= htmlspecialchars($doc_type, ENT_QUOTES) ?>"
                                                    data-semester="<?= htmlspecialchars($selectedSemester, ENT_QUOTES) ?>"
                                                    data-year="<?= $selectedYear ?>"
                                                    data-has-files="<?= $has_files ? '1' : '0' ?>"
                                                    style="cursor: pointer; text-align: center; padding: 12px;">
                                                    
                                                    <?php if ($has_files): ?>
                                                        <div class="file-count-badge" title="<?= $file_submission['file_count'] ?> file(s) uploaded">
                                                            <?= $file_submission['file_count'] ?>
                                                        </div>
                                                        
                                                        <div class="status-indicator submitted">
                                                            <i class='bx bx-check'></i>
                                                        </div>
                                                        
                                                        <div class="submission-info">
                                                            <small title="Latest upload: <?= date('M d, Y h:i A', strtotime($file_submission['latest_upload'])) ?>">
                                                                <?= date('m/d/Y', strtotime($file_submission['latest_upload'])) ?>
                                                            </small>
                                                            <?php if ($file_submission['total_size'] > 0): ?>
                                                                <small style="display: block;"><?= formatFileSize($file_submission['total_size']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="status-indicator not-submitted">
                                                            <i class='bx bx-x'></i>
                                                        </div>
                                                        
                                                        <div class="submission-info">
                                                            <small>Not Submitted</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            
                                            <td class="progress-cell" style="text-align: center; padding: 15px;">
                                                <div class="progress-circle" data-progress="<?= $staff_progress ?>">
                                                    <svg width="60" height="60">
                                                        <circle cx="30" cy="30" r="24" fill="none" stroke="#e2e8f0" stroke-width="4"/>
                                                        <circle cx="30" cy="30" r="24" fill="none" 
                                                                stroke="<?= $staff_progress == 100 ? '#28a745' : ($staff_progress >= 50 ? '#eab308' : '#dc2626') ?>" 
                                                                stroke-width="4" 
                                                                stroke-dasharray="<?= 2 * M_PI * 24 ?>" 
                                                                stroke-dashoffset="<?= 2 * M_PI * 24 * (1 - $staff_progress / 100) ?>"
                                                                transform="rotate(-90 30 30)"/>
                                                        <text x="30" y="30" text-anchor="middle" dy="0.3em" font-size="12" font-weight="bold">
                                                            <?= $staff_progress ?>%
                                                        </text>
                                                    </svg>
                                                </div>
                                                <small style="display: block; margin-top: 5px; font-weight: 600;"><?= $staff_submitted ?>/<?= count($document_types) ?></small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar Cards -->
                <div class="dashboard-card slide-up">
                    <div class="card-header">
                        <h3>
                            <i class='bx bxs-filter-alt'></i>
                            Filters & Actions
                        </h3>
                    </div>

                    <div class="sidebar-filters">
                        <div class="filter-section">
                            <h4><i class='bx bx-search-alt'></i> Search Faculty</h4>
                            <form method="GET" class="search-form">
                                <input type="hidden" name="semester" value="<?= htmlspecialchars($selectedSemester) ?>">
                                <input type="hidden" name="department" value="<?= htmlspecialchars($department_filter) ?>">
                                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-input" placeholder="Search by name, ID, email..." value="<?= htmlspecialchars($search) ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class='bx bx-search'></i>
                                    </button>
                                </div>
                                <?php if (!empty($search)): ?>
                                    <a href="document-tracker.php?semester=<?= urlencode($selectedSemester) ?>&department=<?= urlencode($department_filter) ?>&status=<?= urlencode($status_filter) ?>" class="btn btn-secondary btn-sm" style="width: 100%; margin-top: 8px; justify-content: center;">
                                        <i class='bx bx-x'></i> Clear Search
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div class="filter-section">
                            <h4><i class='bx bx-building'></i> Department</h4>
                            <form method="GET" class="filter-form">
                                <input type="hidden" name="semester" value="<?= htmlspecialchars($selectedSemester) ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                                <select name="department" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= $department_filter == $dept['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>

                        <div class="filter-section">
                            <h4><i class='bx bx-flag'></i> Status Filter</h4>
                            <form method="GET" class="filter-form">
                                <input type="hidden" name="semester" value="<?= htmlspecialchars($selectedSemester) ?>">
                                <input type="hidden" name="department" value="<?= htmlspecialchars($department_filter) ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="complete" <?= $status_filter === 'complete' ? 'selected' : '' ?>>Complete (100%)</option>
                                    <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                                    <option value="none" <?= $status_filter === 'none' ? 'selected' : '' ?>>Not Started (0%)</option>
                                </select>
                            </form>
                        </div>

                        <div class="filter-section">
                            <h4><i class='bx bx-download'></i> Export Options</h4>
                            <div class="export-buttons">
                                <button class="action-item export-btn" onclick="exportTable('csv')" title="Export as CSV">
                                    <div class="action-content">
                                        <div class="action-icon" style="background: #28a745;">
                                            <i class='bx bx-file'></i>
                                        </div>
                                        <p>Export CSV</p>
                                    </div>
                                </button>
                                <button class="action-item export-btn" onclick="exportTable('excel')" title="Export as Excel">
                                    <div class="action-content">
                                        <div class="action-icon" style="background: #1e7e34;">
                                            <i class='bx bx-export'></i>
                                        </div>
                                        <p>Export Excel</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information Cards -->
            <div class="dashboard-grid" style="margin-top: 24px;">
                <!-- Period Summary -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>
                            <i class='bx bxs-calendar-alt'></i>
                            Period Summary
                        </h3>
                        <div class="card-actions">
                            <a class="btn-icon" href="reports.php" title="View Reports">
                                <i class='bx bx-bar-chart-alt-2'></i>
                            </a>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 16px;">
                        <div class="info-item">
                            <div class="info-label">Academic Year</div>
                            <div class="info-value" style="color: var(--cvsu-green-700); font-size: 20px; font-weight: 700;"><?= $selectedYear ?>-<?= $selectedYear + 1 ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Semester</div>
                            <div class="info-value" style="color: var(--cvsu-gold-700); font-size: 20px; font-weight: 700;"><?= htmlspecialchars($normalizedSemester) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Faculty Tracked</div>
                            <div class="info-value" style="font-size: 20px; font-weight: 700;"><?= number_format($total_faculty) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Total Documents</div>
                            <div class="info-value" style="font-size: 20px; font-weight: 700;"><?= number_format($total_possible) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="dashboard-card slide-up">
                    <div class="card-header">
                        <h3>
                            <i class='bx bx-bar-chart-alt-2'></i>
                            Submission Overview
                        </h3>
                    </div>

                    <div class="account-info" style="margin-top: 16px;">
                        <div class="info-item">
                            <div class="info-label">Submitted</div>
                            <div class="info-value" style="color: var(--cvsu-green-700); font-size: 24px; font-weight: 700;"><?= number_format($submitted_count) ?></div>
                            <div class="progress-bar" style="margin-top: 8px;">
                                <div class="progress-fill" style="width: <?= $total_possible > 0 ? ($submitted_count / $total_possible) * 100 : 0 ?>%;"></div>
                            </div>
                            <div class="bar-note"><?= $total_possible > 0 ? number_format(($submitted_count / $total_possible) * 100, 1) : 0 ?>% submitted</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Missing</div>
                            <div class="info-value" style="color: var(--cvsu-gold-700); font-size: 24px; font-weight: 700;"><?= number_format($total_possible - $submitted_count) ?></div>
                            <div class="bar-note" style="margin-top: 8px;">
                                <?= number_format($total_possible - $submitted_count) ?> documents still needed
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Complete Faculty</div>
                            <div class="info-value" style="color: var(--cvsu-green-700); font-size: 24px; font-weight: 700;"><?= number_format($complete_faculty) ?></div>
                            <div class="bar-note" style="margin-top: 8px;">
                                <?= $faculty_completion_rate ?>% of all faculty
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Incomplete Faculty</div>
                            <div class="info-value" style="color: #dc2626; font-size: 24px; font-weight: 700;"><?= number_format($total_faculty - $complete_faculty) ?></div>
                            <div class="bar-note" style="margin-top: 8px;">
                                Need follow-up
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

                <div class="notif-list">
                    <div class="notif-item">
                        <div class="notif-icon info">
                            <i class='bx bx-info-circle'></i>
                        </div>
                        <div class="notif-body">
                            <h4>Document Tracker Active</h4>
                            <p>Tracking <?= number_format($total_faculty) ?> faculty across <?= count($document_types) ?> document types for <?= htmlspecialchars($selectedSemester) ?>.</p>
                            <span>Current period</span>
                        </div>
                    </div>
                    <?php if ($complete_faculty < $total_faculty): ?>
                    <div class="notif-item unread">
                        <div class="notif-icon ok">
                            <i class='bx bxs-bell'></i>
                        </div>
                        <div class="notif-body">
                            <h4>Incomplete Submissions</h4>
                            <p><?= number_format($total_faculty - $complete_faculty) ?> faculty member<?= $total_faculty - $complete_faculty !== 1 ? 's' : '' ?> have incomplete submissions. Consider sending reminders.</p>
                            <span>Action recommended</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="notif-item">
                        <div class="notif-icon ok">
                            <i class='bx bx-check-circle'></i>
                        </div>
                        <div class="notif-body">
                            <h4>System Status</h4>
                            <p>All document uploads are being tracked automatically. Files uploaded by faculty appear in real-time.</p>
                            <span>Auto-tracking enabled</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <!-- Faculty Details Modal -->
    <div id="facultyDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Faculty Details</h3>
                <button class="close-btn" onclick="closeFacultyDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="facultyDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- File Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="detailsModalTitle">Document Files</h3>
                <button class="close-btn" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="detailsContent"></div>
            </div>
        </div>
    </div>

    <script src="assets/js/components/sidebar.js?v=<?= time() ?>"></script>
    <script src="assets/js/components/navbar.js?v=<?= time() ?>"></script>
    
    <!-- Faculty data for modal functions (passed from PHP) -->
    <script>
        // Store faculty data for modal display
        window.facultyData = <?= json_encode($faculty) ?>;

        // Current period data for modals
        window.currentPeriod = {
            semester: <?= json_encode($normalizedSemester) ?>,
            academicYear: <?= json_encode($selectedYear) ?>
        };
    </script>
    
    <script src="assets/js/pages/document-tracker.js?v=<?= time() ?>"></script>
</body>
</html>
