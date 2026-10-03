<?php include 'script/tracker.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Tracker - <?= htmlspecialchars($admin_department_name) ?></title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/doc-track.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/document-tracker.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/document.css?v=<?= time() ?>">

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Document Submission Tracker</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Admin</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Document Tracker</a></li>
                    </ul>
                </div>
            </div>

            <!-- Department Info -->
            <div class="department-info">
                <h2><?= htmlspecialchars($admin_department_name) ?></h2>
                <p>Document Submission Tracking System</p>
                <?php if ($admin_department_id): ?>
                    <small>Department ID: <?= htmlspecialchars($admin_department_id) ?></small>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= $total_faculty ?></h3>
                    <p>Total Faculty</p>
                    <small><?= $admin_department_id ? 'In Department' : 'System Wide' ?></small>
                </div>
                <div class="stat-card">
                    <h3><?= $submitted_count ?></h3>
                    <p>Documents Submitted</p>
                    <small>Out of <?= $total_possible ?> required</small>
                </div>
                <div class="stat-card">
                    <h3><?= $complete_faculty ?></h3>
                    <p>Complete Submissions</p>
                    <small><?= $faculty_completion_rate ?>% of faculty</small>
                </div>
                <div class="stat-card completion">
                    <h3><?= $completion_rate ?>%</h3>
                    <p>Overall Completion</p>
                    <small>Document submission rate</small>
                </div>
            </div>

            <!-- Simplified Period Filter -->
            <div class="period-filter">
                <div class="filter-content">
                    <div class="filter-label">
                        <i class="bx bx-calendar"></i>
                        <span>Academic Period</span>
                    </div>
                    
                    <form method="GET" id="periodFilterForm" class="filter-form">
                        <div class="select-wrapper">
                            <select name="semester" class="period-select" id="semesterSelect">
                                <?php
                                // Get available periods from document_files table
                                $period_query = "
                                    SELECT DISTINCT 
                                        df.academic_year, 
                                        df.semester_period,
                                        COUNT(df.id) as file_count
                                    FROM document_files df
                                    INNER JOIN users u ON df.uploaded_by = u.id
                                    WHERE u.role = 'user' AND u.is_approved = 1
                                ";
                                
                                $period_params = [];
                                if ($admin_department_id) {
                                    $period_query .= " AND u.department_id = ?";
                                    $period_params[] = $admin_department_id;
                                }
                                
                                $period_query .= " 
                                    GROUP BY df.academic_year, df.semester_period 
                                    ORDER BY df.academic_year DESC, 
                                    CASE df.semester_period 
                                        WHEN '1st Semester' THEN 1 
                                        WHEN '2nd Semester' THEN 2 
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
                                        $period_value = $period['semester_period'] . ' AY ' . $period['academic_year'] . '-' . ($period['academic_year'] + 1);
                                        $period_display = $period['semester_period'] . ' AY ' . $period['academic_year'] . '-' . ($period['academic_year'] + 1) . ' (' . $period['file_count'] . ' files)';
                                        $selected = ($selectedSemester == $period_value) ? 'selected' : '';
                                        echo "<option value=\"{$period_value}\" {$selected}>{$period_display}</option>";
                                    }
                                }
                                ?>
                            </select>
                            <i class="bx bx-chevron-down select-arrow"></i>
                        </div>
                        
                        <!-- Preserve other filters -->
                        <input type="hidden" name="department" value="<?= htmlspecialchars($department_filter) ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                        
                        <button type="submit" class="filter-btn" id="filterBtn">
                            <i class="bx bx-refresh"></i>
                            <span>Update</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Clean Table Header -->
            <div class="table-header">
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
                        <table class="doc-table" id="documentTable">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="faculty-cell" style="min-width: 200px;">
                                        FACULTY STAFF
                                        <small style="display: block; font-weight: normal; text-transform: none; margin-top: 5px;">Click to view details</small>
                                    </th>
                                    <th colspan="<?= count($document_types) ?>" style="text-align: center;">
                                        DOCUMENTS (Auto-tracked by File Uploads)
                                    </th>
                                    <th rowspan="2" style="min-width: 100px; text-align: center;">PROGRESS</th>
                                </tr>
                                <tr>
                                    <?php foreach ($document_types as $doc_type): ?>
                                        <th title="<?= htmlspecialchars($doc_type) ?>" style="writing-mode: vertical-lr; text-orientation: mixed; min-width: 60px; text-align: center;">
                                            <span style="writing-mode: horizontal-tb; font-size: 0.7rem;">
                                                <?= strlen($doc_type) > 15 ? substr($doc_type, 0, 15) . '...' : $doc_type ?>
                                            </span>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Updated HTML section for faculty table row -->
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
                                            <div class="faculty-avatar-small" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; margin-right: 15px; flex-shrink: 0; position: relative; border: 3px solid #e9ecef;">
                                                <?php if ($has_profile_image): ?>
                                                    <img src="<?= htmlspecialchars($staff['profile_image_url']) ?>" 
                                                        alt="<?= htmlspecialchars($staff['name'] . ' ' . $staff['surname']) ?>" 
                                                        style="width: 100%; height: 100%; object-fit: cover;"
                                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="fallback-avatar" style="display: none; width: 100%; height: 100%; 
                                                        background: linear-gradient(135deg, var(--primary-color, #007bff), #4a90e2); 
                                                        color: white; font-size: 18px; font-weight: bold; 
                                                        display: flex; align-items: center; justify-content: center;">
                                                        <?= $first_letter ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="letter-avatar" style="width: 100%; height: 100%; 
                                                        background: linear-gradient(135deg, var(--primary-color, #007bff), #4a90e2); 
                                                        color: white; font-size: 18px; font-weight: bold; 
                                                        display: flex; align-items: center; justify-content: center;">
                                                        <?= $first_letter ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Status indicator -->
                                                <div class="status-indicator" style="position: absolute; bottom: 2px; right: 2px; 
                                                    width: 2px; height: 2px; background: #28a745; border: 2px solid white; 
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
                                            onclick="<?= $has_files ? 
                                                'viewDetails('.$staff['id'].',\''.htmlspecialchars($doc_type, ENT_QUOTES).'\',\''.htmlspecialchars($normalizedSemester, ENT_QUOTES).'\','.$selectedYear.')' : 
                                                'showNotSubmittedDetails('.$staff['id'].',\''.htmlspecialchars($doc_type, ENT_QUOTES).'\',\''.htmlspecialchars($normalizedSemester, ENT_QUOTES).'\','.$selectedYear.')' 
                                            ?>"
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
                                    
                                    <td style="text-align: center; padding: 15px;">
                                        <div class="progress-circle" data-progress="<?= $staff_progress ?>">
                                            <svg width="60" height="60">
                                                <circle cx="30" cy="30" r="24" fill="none" stroke="#e0e0e0" stroke-width="4"/>
                                                <circle cx="30" cy="30" r="24" fill="none" 
                                                        stroke="<?= $staff_progress == 100 ? '#28a745' : ($staff_progress >= 50 ? '#ffc107' : '#dc3545') ?>" 
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

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <?php include "script/script-docu.php"; ?>
</body>
</html>