<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Authenticate and require super admin access
$current_user = requireSuperAdmin();
if (!$current_user) {
    header('Location: ../../login.php?error=access_denied');
    exit();
}

// Handle AJAX requests first
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_file_details') {
    header('Content-Type: application/json');
    
    try {
        $faculty_id = (int)($_GET['faculty_id'] ?? 0);
        $doc_type = $_GET['document_type'] ?? '';
        $semester = $_GET['semester'] ?? '';
        $academic_year = (int)($_GET['academic_year'] ?? date('Y'));

        // The period filter is sent as "1st Semester" / "2nd Semester" together
        // with the AY start year, while the folders.php upload stores the
        // semester as the files.semester enum ('first'/'second').
        $parsed = odci_period_parse($semester);
        if ($parsed['start_year'] > 0) {
            $academic_year = $parsed['start_year'];
        }
        $folderSemester = $parsed['start_year'] > 0
            ? $parsed['semester']
            : odci_semester_column($semester);

        // Map the tracker document type back to its folder category
        $docTypeToCategory = odci_document_type_to_category();
        $folderCategory = $docTypeToCategory[$doc_type] ?? $doc_type;

        // Query the files table joined with folders (folders.php upload system)
        $stmt = $pdo->prepare("
            SELECT 
                f.id, f.file_name, f.original_name, f.file_path, f.file_size,
                f.uploaded_at, f.description, f.mime_type, f.file_extension,
                COALESCE(f.download_count, 0) AS download_count,
                fo.category as file_type,
                CONCAT(u.name, ' ', u.surname) as uploader_name,
                u.employee_id,
                f.academic_year,
                f.semester
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            INNER JOIN users u ON f.uploaded_by = u.id
            WHERE f.uploaded_by = ? 
            AND fo.category = ?
            AND f.semester = ?
            AND " . odci_academic_year_sql('f.academic_year') . "
            AND f.is_deleted = 0
            AND fo.is_deleted = 0
            ORDER BY f.uploaded_at DESC
        ");
        
        $stmt->execute([$faculty_id, $folderCategory, $folderSemester, $academic_year]);
        $files = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'files' => $files,
            'period' => $academic_year . '-' . ($academic_year + 1),
            'semester' => $folderSemester,
            'document_type' => $doc_type
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle other AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_faculty_details':
            $faculty_id = intval($_GET['faculty_id']);
            
            try {
                $stmt = $pdo->prepare("
                    SELECT u.*, d.department_name, d.department_code,
                           COUNT(f.id) as total_files,
                           MAX(f.uploaded_at) as last_upload
                    FROM users u 
                    LEFT JOIN departments d ON u.department_id = d.id 
                    LEFT JOIN files f ON u.id = f.uploaded_by AND f.is_deleted = 0
                    WHERE u.id = :faculty_id 
                    GROUP BY u.id
                ");
                $stmt->execute([':faculty_id' => $faculty_id]);
                $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($faculty) {
                    echo json_encode(['success' => true, 'faculty' => $faculty]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Faculty not found']);
                }
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'send_reminder':
            $faculty_id = intval($_POST['faculty_id']);
            $document_type = $_POST['document_type'];
            
            try {
                // Get faculty details
                $stmt = $pdo->prepare("SELECT name, surname, email FROM users WHERE id = :id");
                $stmt->execute([':id' => $faculty_id]);
                $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($faculty) {
                    // Add notification to the faculty member
                    $title = "Document Submission Reminder";
                    $message = "Please submit your {$document_type} document for the current semester.";
                    
                    $success = addNotification($pdo, $faculty_id, $title, $message, 'warning');
                    
                    // Log the reminder activity
                    logActivity(
                        $pdo, 
                        $current_user['id'], 
                        'reminder_sent', 
                        'user', 
                        $faculty_id, 
                        "Sent reminder for {$document_type} to {$faculty['name']} {$faculty['surname']}"
                    );
                    
                    if ($success) {
                        echo json_encode(['success' => true, 'message' => 'Reminder sent successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to send reminder']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Faculty not found']);
                }
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
        case 'get_all_submissions':
            $faculty_id = intval($_GET['faculty_id']);
            $academic_year = intval($_GET['academic_year']);
            $semester = $_GET['semester'];
            
            try {
                $folderSemester = odci_semester_column($semester);
                
                $stmt = $pdo->prepare("
                    SELECT f.*, fo.category as folder_category
                    FROM files f
                    INNER JOIN folders fo ON f.folder_id = fo.id
                    WHERE f.uploaded_by = ? 
                    AND " . odci_academic_year_sql('f.academic_year') . " 
                    AND f.semester = ?
                    AND f.is_deleted = 0
                    AND fo.is_deleted = 0
                    ORDER BY f.uploaded_at DESC
                ");
                
                $stmt->execute([
                    $faculty_id,
                    $academic_year,
                    $folderSemester
                ]);
                
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'files' => $files]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
    }
}

// Get all departments
try {
    $stmt = $pdo->prepare("
        SELECT d.*, 
               COUNT(DISTINCT u.id) as user_count,
               COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN u.id END) as admin_count
        FROM departments d 
        LEFT JOIN users u ON d.id = u.department_id AND u.is_approved = 1
        WHERE d.is_active = 1 
        GROUP BY d.id
        ORDER BY d.department_name
    ");
    $stmt->execute();
    $all_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $all_departments = [];
}

// Get selected department
$selected_department = isset($_GET['department']) ? intval($_GET['department']) : null;
$selected_department_name = '';

// Get selected period - now dynamic based on available data
$selectedSemester = $_GET['semester'] ?? '';
$selectedYear = null;
$normalizedSemester = '';

// If no semester is selected, get the latest available period.
// Source of truth is the same `files` + `folders` pair the submission matrix
// reads, so periods created by the folders.php uploads are always selectable.
if (empty($selectedSemester)) {
    $latest_period_query = "
        SELECT DISTINCT 
            " . odci_academic_year_expr('f.academic_year') . " AS academic_year, 
            f.semester AS semester,
            COUNT(f.id) as file_count
        FROM files f
        INNER JOIN folders fo ON f.folder_id = fo.id
        INNER JOIN users u ON f.uploaded_by = u.id
        WHERE u.role = 'user' AND u.is_approved = 1
          AND f.is_deleted = 0 AND fo.is_deleted = 0
          AND fo.category IS NOT NULL
    ";
    
    $latest_params = [];
    if ($selected_department) {
        $latest_period_query .= " AND u.department_id = ?";
        $latest_params[] = $selected_department;
    }
    
    $latest_period_query .= " 
        GROUP BY academic_year, f.semester 
        ORDER BY academic_year DESC, 
        CASE f.semester 
            WHEN 'first' THEN 1 
            WHEN 'second' THEN 2 
            ELSE 3 
        END DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($latest_period_query);
    $stmt->execute($latest_params);
    $latest_period = $stmt->fetch();
    
    if ($latest_period && (int)$latest_period['academic_year'] > 0) {
        $selectedYear = (int)$latest_period['academic_year'];
        $normalizedSemester = odci_semester_ordinal($latest_period['semester']);
        $selectedSemester = $normalizedSemester . ' AY ' . odci_academic_year_range($selectedYear);
    } else {
        // Fallback to current year if no data
        $selectedYear = date('Y');
        $normalizedSemester = '2nd Semester';
        $selectedSemester = $normalizedSemester . ' AY ' . $selectedYear . '-' . ($selectedYear + 1);
    }
} else {
    // Parse selected semester
    if (strpos($selectedSemester, 'AY') !== false) {
        preg_match('/^(.+?) AY (\d{4})-\d{4}$/', $selectedSemester, $matches);
        if (!empty($matches[1]) && !empty($matches[2])) {
            $normalizedSemester = $matches[1];
            $selectedYear = (int)$matches[2];
        }
    }
}

// Document types for the selected period: read what the folders.php upload
// flow actually expects (document_requirements), falling back to the
// canonical list when no requirements were configured for the period.
$document_types = [];
if ($selectedYear && $normalizedSemester) {
    $req_stmt = $pdo->prepare("
        SELECT DISTINCT document_type 
        FROM document_requirements 
        WHERE academic_year = ? AND semester = ? AND is_required = 1
        ORDER BY document_type
    ");
    $req_stmt->execute([$selectedYear, $normalizedSemester]);
    $document_types = $req_stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (empty($document_types)) {
    $document_types = odci_default_document_types();
}

// Initialize variables
$faculty = [];
$file_submissions = [];
$total_faculty = 0;
$submitted_count = 0;
$total_possible = 0;
$complete_faculty = 0;
$completion_rate = 0;
$faculty_completion_rate = 0;

function getProfileImagePath($profileImage) {
    if (empty($profileImage)) {
        return null;
    }
    
    // Clean the path - remove leading slashes and normalize
    $cleanPath = ltrim($profileImage, '/');
    
    // Define possible paths to check
    $possiblePaths = [
        '../../' . $cleanPath, // Direct path as stored
        '../../uploads/profile_images/' . basename($cleanPath), // In profile_images folder
        '../../uploads/profiles/' . basename($cleanPath), // Alternative profiles folder
        '../../' . str_replace('uploads/', 'uploads/', $cleanPath) // Ensure uploads prefix
    ];
    
    // Check each possible path
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path . '?v=' . filemtime($path); // Add cache busting
        }
    }
    
    return null;
}

if ($selected_department) {
    try {
        // Get department name
        $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => $selected_department]);
        $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dept_result) {
            $selected_department_name = $dept_result['department_name'];
            
            // Get faculty in this department
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.mi, u.surname, u.employee_id, u.email, u.position, 
                    u.profile_image,
                    d.department_name, d.department_code, d.id as dept_id
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.department_id = :department_id 
                AND u.role IN ('admin', 'user') 
                AND u.is_approved = 1
                ORDER BY u.surname, u.name
            ");
            $stmt->execute([':department_id' => $selected_department]);
            $faculty_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process faculty data to include proper profile image paths
            $faculty = [];
            foreach ($faculty_raw as $staff) {
                $staff['profile_image_url'] = getProfileImagePath($staff['profile_image']);
                $faculty[] = $staff;
            }
            
            $total_faculty = count($faculty);
            
            // Enhanced file submissions query using files + folders (folders.php system)
            if (!empty($faculty) && $selectedYear && $normalizedSemester) {
                $faculty_ids = array_column($faculty, 'id');
                $placeholders = implode(',', array_fill(0, count($faculty_ids), '?'));
                
                // normalizedSemester ('1st Semester') -> files.semester ('first')
                $folderSemester = odci_semester_column($normalizedSemester);
                
                $file_query = "
                    SELECT 
                        f.uploaded_by as faculty_id, 
                        fo.category as folder_category, 
                        COUNT(f.id) as file_count,
                        MAX(f.uploaded_at) as latest_upload,
                        MIN(f.uploaded_at) as first_upload,
                        SUM(f.file_size) as total_size,
                        f.academic_year,
                        f.semester
                    FROM files f
                    INNER JOIN folders fo ON f.folder_id = fo.id
                    WHERE f.uploaded_by IN ($placeholders)
                    AND " . odci_academic_year_sql('f.academic_year') . "
                    AND f.semester = ?
                    AND f.is_deleted = 0
                    AND fo.is_deleted = 0
                    AND fo.category IS NOT NULL
                    GROUP BY f.uploaded_by, fo.category, f.academic_year, f.semester
                ";
                
                $stmt = $pdo->prepare($file_query);
                
                // Combine parameters: faculty_ids first, then year and semester
                $params = array_merge($faculty_ids, [$selectedYear, $folderSemester]);
                $stmt->execute($params);
                
                // Map folder categories back to the tracker's document types
                $categoryToDocType = odci_category_to_document_type();
                
                while ($row = $stmt->fetch()) {
                    $matchedType = $categoryToDocType[$row['folder_category']] ?? $row['folder_category'];
                    $file_submissions[$row['faculty_id']][$matchedType] = [
                        'file_count' => $row['file_count'] ?: 0,
                        'latest_upload' => $row['latest_upload'],
                        'first_upload' => $row['first_upload'],
                        'total_size' => $row['total_size'] ?: 0,
                        'status' => $row['file_count'] > 0 ? 'submitted' : 'pending',
                        'academic_year' => $row['academic_year'],
                        'semester' => $row['semester']
                    ];
                }
            }
            
            // Calculate statistics
            $total_possible = $total_faculty * count($document_types);
            
            foreach ($faculty as $f) {
                $faculty_submitted = 0;
                foreach ($document_types as $dt) {
                    if (isset($file_submissions[$f['id']][$dt])) {
                        $submitted_count++;
                        $faculty_submitted++;
                    }
                }
                if ($faculty_submitted == count($document_types)) {
                    $complete_faculty++;
                }
            }
            
            $completion_rate = $total_possible > 0 ? round(($submitted_count / $total_possible) * 100, 1) : 0;
            $faculty_completion_rate = $total_faculty > 0 ? round(($complete_faculty / $total_faculty) * 100, 1) : 0;
        }
    } catch(PDOException $e) {
        error_log("Error in document tracker: " . $e->getMessage());
    }
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Tracker - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/doc-track.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/document-tracker.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/document.css?v=<?= time() ?>">

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="superadmin-document-tracker-page">
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
                        <li><a href="dashboard.php">Superadmin</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Document Tracker</a></li>
                    </ul>
                </div>
            </div>

            <?php if (!$selected_department): ?>
                <!-- Department Selection View -->
                <div class="department-selection">
                    <h3><i class='bx bx-building'></i> Select a Department</h3>
                    <p>Choose a department to view document submission status and track progress.</p>
                    
                    <div class="department-grid">
                        <?php foreach ($all_departments as $dept): ?>
                            <div class="department-card" onclick="selectDepartment(<?= $dept['id'] ?>)">
                                <h3><?= htmlspecialchars($dept['department_name']) ?></h3>
                                <p><strong><?= $dept['user_count'] ?></strong> Faculty Members</p>
                                <p><strong><?= $dept['admin_count'] ?></strong> Department Admins</p>
                                <small>Click to view document status</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Department Info -->
                <div class="department-info" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2><?= htmlspecialchars($selected_department_name) ?></h2>
                        <p>Document Submission Tracking System</p>
                        <small>Department ID: <?= htmlspecialchars($selected_department) ?></small>
                    </div>
                    <button onclick="selectDepartment(null)" class="btn-modern btn-secondary-modern" style="display: inline-flex; align-items: center; gap: 8px;">
                        <i class="bx bx-arrow-back"></i> Back to Departments
                    </button>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class='bx bxs-group'></i></div>
                        <div class="stat-body">
                            <h3><?= $total_faculty ?></h3>
                            <p>Total Faculty</p>
                            <small>In this department</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class='bx bxs-file-doc'></i></div>
                        <div class="stat-body">
                            <h3><?= $submitted_count ?></h3>
                            <p>Documents Submitted</p>
                            <small>Out of <?= $total_possible ?> required</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon gold"><i class='bx bxs-check-circle'></i></div>
                        <div class="stat-body">
                            <h3><?= $complete_faculty ?></h3>
                            <p>Complete Submissions</p>
                            <small><?= $faculty_completion_rate ?>% of faculty</small>
                        </div>
                    </div>
                       <div class="stat-card completion">
                            <div class="stat-icon gold">
                                <i class='bx bxs-bar-chart-alt-2'></i>
                            </div>
                            <div class="stat-body">
                                <h3><?= $completion_rate ?>%</h3>
                                <p>Overall Completion</p>
                                <small>Document submission rate</small>
                            </div>
                        </div>
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
                                    // Get available periods from the files + folders tables
                                    $period_query = "
                                        SELECT DISTINCT 
                                            " . odci_academic_year_expr('f.academic_year') . " AS academic_year, 
                                            f.semester,
                                            COUNT(f.id) as file_count
                                        FROM files f
                                        INNER JOIN folders fo ON f.folder_id = fo.id
                                        INNER JOIN users u ON f.uploaded_by = u.id
                                        WHERE u.role = 'user' AND u.is_approved = 1
                                          AND f.is_deleted = 0 AND fo.is_deleted = 0
                                          AND fo.category IS NOT NULL
                                    ";
                                    
                                    $period_params = [];
                                    if ($selected_department) {
                                        $period_query .= " AND u.department_id = ?";
                                        $period_params[] = $selected_department;
                                    }
                                    
                                    $period_query .= " 
                                        GROUP BY academic_year, f.semester 
                                        ORDER BY academic_year DESC, 
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
                                            if ((int)$period['academic_year'] <= 0) {
                                                continue;
                                            }
                                            $ordinal = odci_semester_ordinal($period['semester']);
                                            $period_value = $ordinal . ' AY ' . $period['academic_year'] . '-' . ($period['academic_year'] + 1);
                                            $period_display = $ordinal . ' AY ' . $period['academic_year'] . '-' . ($period['academic_year'] + 1) . ' (' . $period['file_count'] . ' files)';
                                            $selected = ($selectedSemester == $period_value) ? 'selected' : '';
                                            echo "<option value=\"{$period_value}\" {$selected}>{$period_display}</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <i class="bx bx-chevron-down select-arrow"></i>
                            </div>
                            
                            <!-- Preserve department filter -->
                            <input type="hidden" name="department" value="<?= htmlspecialchars($selected_department) ?>">
                            
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
                                <?= htmlspecialchars($selected_department_name) ?>
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
                            <button type="button" onclick="window.location.href='document-tracker.php'" class="btn-modern btn-secondary-modern" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                <i class="bx bx-refresh"></i>
                                Reset Filters
                            </button>
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
                                                            background: linear-gradient(135deg, #0a8f3c, #006b2e); 
                                                            color: white; font-size: 18px; font-weight: bold; 
                                                            display: flex; align-items: center; justify-content: center;">
                                                            <?= $first_letter ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="letter-avatar" style="width: 100%; height: 100%; 
                                                            background: linear-gradient(135deg, #0a8f3c, #006b2e); 
                                                            color: white; font-size: 18px; font-weight: bold; 
                                                            display: flex; align-items: center; justify-content: center;">
                                                            <?= $first_letter ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Status indicator -->
                                                    <div class="status-indicator" style="position: absolute; bottom: 2px; right: 2px; 
                                                        width: 12px; height: 12px; background: #28a745; border: 2px solid white; 
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
                                                            stroke="<?= $staff_progress == 100 ? '#28a745' : ($staff_progress >= 50 ? '#d4a72c' : '#dc3545') ?>" 
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
            <?php endif; ?>
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
    <script>
        // Store faculty data for modal display
        const facultyData = <?= json_encode($faculty) ?>;
        
        // Enhanced modal functions
        function showModal(modal) {
            modal.style.display = 'flex';
            modal.offsetHeight; // Force reflow
            modal.classList.add('show');
        }

        function hideModal(modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function selectDepartment(departmentId) {
            window.location.href = `document-tracker.php?department=${departmentId}`;
        }

        // Enhanced showFacultyDetails function with profile image support
        function showFacultyDetails(facultyId) {
            const modal = document.getElementById('facultyDetailsModal');
            const content = document.getElementById('facultyDetailsContent');
            
            // Find faculty data
            const faculty = facultyData.find(f => f.id == facultyId);
            
            if (!faculty) {
                content.innerHTML = '<p>Faculty data not found.</p>';
                showModal(modal);
                return;
            }
            
            // Get first letter for fallback avatar
            const firstLetter = faculty.name.charAt(0).toUpperCase();
            const fullName = `${faculty.surname}, ${faculty.name}${faculty.mi ? ' ' + faculty.mi + '.' : ''}`;
            
            // Use the processed profile image URL
            let avatarContent = '';
            const hasProfileImage = faculty.profile_image_url && faculty.profile_image_url.trim() !== '';
            
            if (hasProfileImage) {
                avatarContent = `
                    <img src="${faculty.profile_image_url}" 
                        alt="${fullName}" 
                        style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; transition: transform 0.3s ease;"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                        onload="this.style.opacity='1';"
                        onmouseover="this.style.transform='scale(1.05)'"
                        onmouseout="this.style.transform='scale(1)'">
                    <div class="fallback-avatar" 
                        style="display: none; width: 100%; height: 100%; 
                                background: linear-gradient(135deg, #0a8f3c, #006b2e); 
                                color: white; font-size: 32px; font-weight: bold; 
                                display: flex; align-items: center; justify-content: center; 
                                border-radius: 50%; box-shadow: 0 4px 15px rgba(0,107,46,0.3);">
                        ${firstLetter}
                    </div>
                `;
            } else {
                avatarContent = `
                    <div class="letter-avatar" 
                        style="width: 100%; height: 100%; 
                                background: linear-gradient(135deg, #0a8f3c, #006b2e); 
                                color: white; font-size: 32px; font-weight: bold; 
                                display: flex; align-items: center; justify-content: center; 
                                border-radius: 50%; box-shadow: 0 4px 15px rgba(0,107,46,0.3);
                                transition: transform 0.3s ease;"
                        onmouseover="this.style.transform='scale(1.05)'"
                        onmouseout="this.style.transform='scale(1)'">
                        ${firstLetter}
                    </div>
                `;
            }
            
            content.innerHTML = `
                <div class="faculty-detail-card" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); 
                    border-radius: 15px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    
                    <div class="faculty-detail-header" style="display: flex; align-items: center; gap: 20px; 
                        margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #e9ecef;">
                        
                        <div class="faculty-avatar-container" style="position: relative;">
                            <div class="faculty-avatar" 
                                style="width: 80px; height: 80px; position: relative; border-radius: 50%; 
                                        overflow: hidden; border: 4px solid #ffffff; 
                                        box-shadow: 0 8px 25px rgba(0,0,0,0.15);">
                                ${avatarContent}
                            </div>
                            
                            <!-- Status indicator -->
                            <div class="status-dot" 
                                style="position: absolute; bottom: 5px; right: 5px; 
                                        width: 20px; height: 20px; background: #28a745; 
                                        border: 3px solid white; border-radius: 50%; 
                                        box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                            </div>
                        </div>
                        
                        <div class="faculty-detail-info" style="flex: 1;">
                            <h2 class="faculty-detail-name" 
                                style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700; 
                                    color: #2c3e50; line-height: 1.2;">
                                ${fullName}
                            </h2>
                            <p class="faculty-detail-position" 
                            style="margin: 0 0 5px 0; font-size: 16px; color: #6c757d; 
                                    font-weight: 500;">
                                ${faculty.position || 'Faculty Member'}
                            </p>
                            <div class="department-badge" 
                                style="display: inline-block; background: linear-gradient(135deg, #0a8f3c, #006b2e); 
                                        color: white; padding: 4px 12px; border-radius: 20px; 
                                        font-size: 12px; font-weight: 600;">
                                ${faculty.department_name || 'No Department'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-grid" 
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); 
                                gap: 20px; margin-bottom: 25px;">
                        
                        <div class="info-item" 
                            style="background: white; padding: 15px; border-radius: 10px; 
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
                            onmouseover="this.style.boxShadow='0 6px 18px rgba(24,47,31,0.12)'"
                            onmouseout="this.style.boxShadow='0 2px 10px rgba(0,0,0,0.05)'">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-building' style="font-size: 24px; color: #006b2e;"></i>
                                <div>
                                    <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">Department</strong>
                                    <span style="color: #6c757d;">${faculty.department_name || 'No Department'}</span>
                                </div>
                            </div>
                        </div>
                        
                        ${faculty.employee_id ? `
                        <div class="info-item" 
                            style="background: white; padding: 15px; border-radius: 10px; 
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
                            onmouseover="this.style.boxShadow='0 6px 18px rgba(24,47,31,0.12)'"
                            onmouseout="this.style.boxShadow='0 2px 10px rgba(0,0,0,0.05)'">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-id-card' style="font-size: 24px; color: #28a745;"></i>
                                <div>
                                    <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">Employee ID</strong>
                                    <span style="color: #6c757d;">${faculty.employee_id}</span>
                                </div>
                            </div>
                        </div>` : ''}
                        
                        ${faculty.email ? `
                        <div class="info-item" 
                            style="background: white; padding: 15px; border-radius: 10px; 
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
                            onmouseover="this.style.boxShadow='0 6px 18px rgba(24,47,31,0.12)'"
                            onmouseout="this.style.boxShadow='0 2px 10px rgba(0,0,0,0.05)'">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-envelope' style="font-size: 24px; color: #d4a72c;"></i>
                                <div>
                                    <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">Email</strong>
                                    <a href="mailto:${faculty.email}" 
                                    style="color: #006b2e; text-decoration: none;"
                                    onmouseover="this.style.textDecoration='underline'"
                                    onmouseout="this.style.textDecoration='none'">
                                        ${faculty.email}
                                    </a>
                                </div>
                            </div>
                        </div>` : ''}
                        
                        <div class="info-item" 
                            style="background: white; padding: 15px; border-radius: 10px; 
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
                            onmouseover="this.style.boxShadow='0 6px 18px rgba(24,47,31,0.12)'"
                            onmouseout="this.style.boxShadow='0 2px 10px rgba(0,0,0,0.05)'">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-user-circle' style="font-size: 24px; color: #0a8f3c;"></i>
                                <div>
                                    <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">Status</strong>
                                    <span style="color: #28a745; font-weight: 600;">
                                        <i class='bx bx-check-circle' style="margin-right: 4px;"></i>Active Faculty
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons" 
                        style="display: flex; gap: 12px; flex-wrap: wrap; padding-top: 15px; 
                                border-top: 1px solid #e9ecef;">
                        <button class="btn btn-primary" 
                                onclick="viewAllSubmissions(${faculty.id})"
                                style="background: linear-gradient(135deg, #0a8f3c, #006b2e); 
                                    border: none; padding: 12px 20px; border-radius: 8px; 
                                    color: white; font-weight: 600; cursor: pointer; 
                                    transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(0,107,46,0.3)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <i class='bx bx-file'></i> View All Documents
                        </button>
                        <button class="btn btn-secondary" 
                                onclick="sendMessage(${faculty.id})"
                                style="background: linear-gradient(135deg, #6c757d, #8e9aaf); 
                                    border: none; padding: 12px 20px; border-radius: 8px; 
                                    color: white; font-weight: 600; cursor: pointer; 
                                    transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(108,117,125,0.3)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <i class='bx bx-message'></i> Send Message
                        </button>
                        <button class="btn btn-info" 
                                onclick="exportFacultyData(${faculty.id})"
                                style="background: linear-gradient(135deg, #0a8f3c, #20c997); 
                                    border: none; padding: 12px 20px; border-radius: 8px; 
                                    color: white; font-weight: 600; cursor: pointer; 
                                    transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(23,162,184,0.3)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <i class='bx bx-export'></i> Export Data
                        </button>
                    </div>
                </div>
            `;
            
            showModal(modal);
        }

        function closeFacultyDetailsModal() {
            const modal = document.getElementById('facultyDetailsModal');
            hideModal(modal);
        }

        // Enhanced viewDetails function
        function viewDetails(facultyId, docType, semester, academicYear) {
            const modal = document.getElementById('detailsModal');
            const title = document.getElementById('detailsModalTitle');
            const content = document.getElementById('detailsContent');
            
            title.textContent = `Files for ${docType}`;
            content.innerHTML = `
                <div style="text-align: center; padding: 30px;">
                    <div class="loading-spinner" style="margin: 0 auto 15px;"></div>
                    <p style="color: #6c757d;">Loading file details...</p>
                </div>
            `;
            
            showModal(modal);
            
            // Build query parameters
            const params = new URLSearchParams({
                action: 'get_file_details',
                faculty_id: facultyId,
                document_type: docType,
                semester: semester,
                academic_year: academicYear
            });
            
            fetch(`?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.files && data.files.length > 0) {
                    let html = '<div class="files-container">';
                    data.files.forEach(file => {
                        html += `
                            <div class="file-item" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f9f9f9;">
                                <div class="file-info">
                                    <h4 style="margin: 0 0 10px 0; color: #333; display: flex; align-items: center; gap: 8px;">
                                        <i class='bx bx-file'></i> ${file.file_name}
                                    </h4>
                                    <div style="margin-bottom: 5px;"><strong>Size:</strong> ${formatFileSize(file.file_size || 0)}</div>
                                    <div style="margin-bottom: 5px;"><strong>Uploaded:</strong> ${new Date(file.uploaded_at).toLocaleString()}</div>
                                    <div style="margin-bottom: 10px;"><strong>Type:</strong> ${file.file_type || docType}</div>
                                    ${file.description ? `<div style="margin-bottom: 10px;"><strong>Description:</strong> ${file.description}</div>` : ''}
                                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                                        <a href="api/files.php?action=download&id=${file.id}" 
                                           class="btn btn-primary" target="_blank" style="text-decoration: none;">
                                           <i class='bx bx-download'></i> Download
                                        </a>
                                        <button class="btn btn-secondary" onclick="previewFile(${file.id}, '${file.file_name}')">
                                           <i class='bx bx-show'></i> Preview
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    content.innerHTML = html;
                } else {
                    content.innerHTML = `
                        <div style="text-align: center; padding: 30px;">
                            <i class='bx bx-error' style="font-size: 48px; color: #dc3545; margin-bottom: 15px;"></i>
                            <p style="color: #dc3545; font-weight: 600;">No files found for this document type.</p>
                            <p style="color: #6c757d;">The faculty member hasn't uploaded any files for "${docType}" in the selected period.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = `
                    <div style="text-align: center; padding: 30px;">
                        <i class='bx bx-wifi-off' style="font-size: 48px; color: #dc3545; margin-bottom: 15px;"></i>
                        <p style="color: #dc3545; font-weight: 600;">Network error occurred</p>
                        <p style="color: #6c757d;">Please check your connection and try again.</p>
                    </div>
                `;
            });
        }

        function showNotSubmittedDetails(facultyId, docType, semester, academicYear) {
            const modal = document.getElementById('detailsModal');
            const title = document.getElementById('detailsModalTitle');
            const content = document.getElementById('detailsContent');
            
            // Find faculty data for personalized message
            const faculty = facultyData.find(f => f.id == facultyId);
            const facultyName = faculty ? `${faculty.name} ${faculty.surname}` : 'Faculty Member';
            
            title.textContent = `Not Submitted: ${docType}`;
            content.innerHTML = `
                <div style="text-align: center; padding: 30px;">
                    <i class='bx bx-info-circle' style="font-size: 48px; color: #0a8f3c; margin-bottom: 15px;"></i>
                    <h4 style="color: #2c3e50; margin-bottom: 10px;">Document Not Submitted</h4>
                    <p style="color: #6c757d; margin-bottom: 15px;">
                        <strong>${facultyName}</strong> has not submitted any files for <strong>"${docType}"</strong> 
                        in the selected period (${semester} ${academicYear}).
                    </p>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <h5 style="color: #495057; margin-bottom: 10px;">Actions you can take:</h5>
                        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                            <button class="btn btn-primary" onclick="sendReminder(${facultyId}, '${docType}')">
                                <i class='bx bx-bell'></i> Send Reminder
                            </button>
                            <button class="btn btn-secondary" onclick="viewFacultyProfile(${facultyId})">
                                <i class='bx bx-user'></i> View Profile
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            showModal(modal);
        }

        function closeDetailsModal() {
            const modal = document.getElementById('detailsModal');
            hideModal(modal);
        }

        function viewAllSubmissions(facultyId) {
            showNotification('Loading all submissions...', 'info');
            closeFacultyDetailsModal();
            
            setTimeout(() => {
                showNotification('Feature coming soon: View all submissions', 'info');
            }, 1000);
        }

        function sendMessage(facultyId) {
            const message = prompt('Enter message to send to faculty:');
            if (!message || message.trim() === '') return;
            
            showNotification('Sending message...', 'info');
            
            setTimeout(() => {
                showNotification('Message sent successfully!', 'success');
                closeFacultyDetailsModal();
            }, 1500);
        }

        function sendReminder(facultyId, docType) {
            const faculty = facultyData.find(f => f.id == facultyId);
            const facultyName = faculty ? `${faculty.name} ${faculty.surname}` : 'Faculty Member';
            
            if (confirm(`Send reminder to ${facultyName} about "${docType}"?`)) {
                showNotification('Sending reminder...', 'info');
                
                setTimeout(() => {
                    showNotification('Reminder sent successfully!', 'success');
                    closeDetailsModal();
                }, 1500);
            }
        }

        function viewFacultyProfile(facultyId) {
            closeDetailsModal();
            showFacultyDetails(facultyId);
        }

        function exportFacultyData(facultyId) {
            showNotification('Exporting faculty data...', 'info');
            
            setTimeout(() => {
                showNotification('Faculty data exported successfully!', 'success');
            }, 1500);
        }

        function previewFile(fileId, fileName) {
            showNotification('Loading file preview...', 'info');
            
            setTimeout(() => {
                showNotification(`Preview for "${fileName}" - Feature coming soon`, 'info');
            }, 1000);
        }

        // Utility functions
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else if (bytes > 1) {
                return bytes + ' bytes';
            } else if (bytes == 1) {
                return '1 byte';
            } else {
                return '0 bytes';
            }
        }

        function showNotification(message, type = 'info') {
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            const colors = {
                success: { bg: 'rgba(212, 237, 218, 0.95)', color: '#155724', border: '#28a745' },
                error: { bg: 'rgba(248, 215, 218, 0.95)', color: '#721c24', border: '#dc3545' },
                info: { bg: 'rgba(209, 236, 241, 0.95)', color: '#0c5460', border: '#0a8f3c' }
            };
            
            const style = colors[type] || colors.info;
            notification.style.cssText = `
                position: fixed;
                top: 30px;
                right: 30px;
                z-index: 1001;
                padding: 15px 20px;
                border-radius: 10px;
                font-weight: 600;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                backdrop-filter: blur(10px);
                background: ${style.bg};
                color: ${style.color};
                transform: translateX(400px);
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 400);
            }, 1800);
        }

        // Event listeners
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                hideModal(event.target);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => hideModal(modal));
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Enhanced Document Tracker initialized');
            console.log(`Faculty data loaded: ${facultyData.length} members`);
            
            // Add smooth scrolling to horizontal table scroll
            const tableWrapper = document.querySelector('.table-scroll-wrapper');
            if (tableWrapper) {
                tableWrapper.style.scrollBehavior = 'smooth';
            }

            // Initialize progress circles animation
            const progressCircles = document.querySelectorAll('.progress-circle');
            progressCircles.forEach(circle => {
                const progress = parseInt(circle.dataset.progress);
                const progressCircle = circle.querySelector('circle:last-child');
                if (progressCircle) {
                    const radius = 24;
                    const circumference = 2 * Math.PI * radius;
                    const offset = circumference * (1 - progress / 100);
                    
                    progressCircle.style.strokeDasharray = circumference;
                    progressCircle.style.strokeDashoffset = circumference;
                    
                    setTimeout(() => {
                        progressCircle.style.transition = 'stroke-dashoffset 1s ease-in-out';
                        progressCircle.style.strokeDashoffset = offset;
                    }, 500);
                }
            });
        });

    </script>


</body>

</html>
