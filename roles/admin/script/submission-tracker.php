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

// Get user's department ID
$userDepartmentId = null;

if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    $userDepartmentId = $currentUser['department_id'];
} elseif (isset($currentUser['id'])) {
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
            $userDepartmentId = $userDept['department_id'];
            $currentUser['department_id'] = $userDept['department_id'];
            $currentUser['department_name'] = $userDept['department_name'];
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

if (!$userDepartmentId) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=no_department_assigned');
    exit();
}

// File categories with submission requirements (updated to match database values)
$fileCategories = [
    'ipcr_accomplishment' => [
        'name' => 'IPCR Accomplishment',
        'db_name' => 'IPCR Accomplishment',
        'icon' => 'bxs-trophy',
        'color' => '#f59e0b',
        'deadline' => 'End of each semester',
        'required' => true,
        'frequency' => 'Per Semester'
    ],
    'ipcr_target' => [
        'name' => 'IPCR Target',
        'db_name' => 'IPCR Target',
        'icon' => 'bxs-bullseye',
        'color' => '#ef4444',
        'deadline' => 'Beginning of each semester',
        'required' => true,
        'frequency' => 'Per Semester'
    ],
    'workload' => [
        'name' => 'Workload',
        'db_name' => 'Workload',
        'icon' => 'bxs-briefcase',
        'color' => '#8b5cf6',
        'deadline' => 'Start of academic year',
        'required' => true,
        'frequency' => 'Per Academic Year'
    ],
    'course_syllabus' => [
        'name' => 'Course Syllabus',
        'db_name' => 'Course Syllabus',
        'icon' => 'bxs-book-content',
        'color' => '#06b6d4',
        'deadline' => '2 weeks before classes',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'syllabus_acceptance' => [
        'name' => 'Course Syllabus Acceptance Form',
        'db_name' => 'Course Syllabus Acceptance Form',
        'icon' => 'bxs-check-circle',
        'color' => '#10b981',
        'deadline' => '1 week after class starts',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'exam' => [
        'name' => 'Exam',
        'db_name' => 'Exam',
        'icon' => 'bxs-file-doc',
        'color' => '#dc2626',
        'deadline' => '1 week before exam',
        'required' => true,
        'frequency' => 'Per Exam Period'
    ],
    'tos' => [
        'name' => 'TOS',
        'db_name' => 'TOS',
        'icon' => 'bxs-spreadsheet',
        'color' => '#059669',
        'deadline' => 'With exam submission',
        'required' => true,
        'frequency' => 'Per Exam Period'
    ],
    'class_record' => [
        'name' => 'Class Record',
        'db_name' => 'Class Record',
        'icon' => 'bxs-data',
        'color' => '#7c3aed',
        'deadline' => 'End of semester',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'grading_sheet' => [
        'name' => 'Grading Sheet',
        'db_name' => 'Grading Sheet',
        'icon' => 'bxs-calculator',
        'color' => '#ea580c',
        'deadline' => '3 days after final exam',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'attendance_sheet' => [
        'name' => 'Attendance Sheet',
        'db_name' => 'Attendance Sheet',
        'icon' => 'bxs-user-check',
        'color' => '#0284c7',
        'deadline' => 'End of semester',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'stakeholder_feedback' => [
        'name' => 'Stakeholder\'s Feedback Form w/ Summary',
        'db_name' => 'Stakeholder\'s Feedback Form w/ Summary',
        'icon' => 'bxs-comment-detail',
        'color' => '#9333ea',
        'deadline' => 'Mid and end of semester',
        'required' => false,
        'frequency' => 'Twice per Semester'
    ],
    'consultation' => [
        'name' => 'Consultation',
        'db_name' => 'Consultation',
        'icon' => 'bxs-chat',
        'color' => '#0d9488',
        'deadline' => 'As scheduled',
        'required' => false,
        'frequency' => 'As Needed'
    ],
    'lecture' => [
        'name' => 'Lecture',
        'db_name' => 'Lecture',
        'icon' => 'bxs-chalkboard',
        'color' => '#7c2d12',
        'deadline' => 'Before each class',
        'required' => false,
        'frequency' => 'Per Class'
    ],
    'activities' => [
        'name' => 'Activities',
        'db_name' => 'Activities',
        'icon' => 'bxs-game',
        'color' => '#be185d',
        'deadline' => 'Before activity date',
        'required' => false,
        'frequency' => 'Per Activity'
    ],
    'exam_acknowledgement' => [
        'name' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form',
        'db_name' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form',
        'icon' => 'bxs-receipt',
        'color' => '#1e40af',
        'deadline' => 'After exam discussion',
        'required' => true,
        'frequency' => 'Per Exam Period'
    ],
    'consultation_log' => [
        'name' => 'Consultation Log Sheet Form',
        'db_name' => 'Consultation Log Sheet Form',
        'icon' => 'bxs-notepad',
        'color' => '#374151',
        'deadline' => 'End of semester',
        'required' => true,
        'frequency' => 'Per Semester'
    ]
];

// Get submission statistics from document_files table
function getSubmissionStatsFromDocumentFiles($pdo, $userId, $category = null, $semester = null, $academicYear = null) {
    try {
        $whereClause = "WHERE df.uploaded_by = ?";
        $params = [$userId];
        
        if ($category) {
            $whereClause .= " AND df.file_type = ?";
            $params[] = $category;
        }
        
        if ($semester) {
            $whereClause .= " AND df.semester_period = ?";
            $params[] = $semester;
        }
        
        if ($academicYear) {
            $whereClause .= " AND df.academic_year = ?";
            $params[] = $academicYear;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                df.file_type as category,
                COUNT(df.id) as file_count,
                MAX(df.uploaded_at) as last_submission,
                CONCAT(df.academic_year, ' - ', df.semester_period) as folder_name,
                df.academic_year,
                df.semester_period
            FROM document_files df
            {$whereClause}
            GROUP BY df.file_type, df.academic_year, df.semester_period
            ORDER BY last_submission DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching submission stats: " . $e->getMessage());
        return [];
    }
}

// Get available academic years from document_files table
function getAvailableAcademicYearsFromDocumentFiles($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT academic_year
            FROM document_files
            WHERE uploaded_by = ? 
            AND academic_year IS NOT NULL
            ORDER BY academic_year DESC
        ");
        
        $stmt->execute([$userId]);
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If no years found in database, generate current and next academic year
        if (empty($years)) {
            $currentYear = date('Y');
            $years = [
                $currentYear,
                $currentYear - 1,
                $currentYear + 1
            ];
        }
        
        return $years;
    } catch(Exception $e) {
        error_log("Error fetching academic years: " . $e->getMessage());
        // Fallback to current academic year
        $currentYear = date('Y');
        return [$currentYear, $currentYear - 1, $currentYear + 1];
    }
}

// Get recent submissions from document_files table
function getRecentSubmissionsFromDocumentFiles($pdo, $userId, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                df.file_name,
                df.uploaded_at,
                df.file_type as category,
                CONCAT(df.academic_year, ' - ', df.semester_period) as folder_name,
                df.file_size,
                df.mime_type,
                df.description
            FROM document_files df
            WHERE df.uploaded_by = ?
            ORDER BY df.uploaded_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching recent submissions: " . $e->getMessage());
        return [];
    }
}

// Get files for a specific category
function getCategoryFiles($pdo, $userId, $category) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                df.*,
                CONCAT(df.academic_year, ' - ', df.semester_period) as folder_name
            FROM document_files df
            WHERE df.uploaded_by = ? AND df.file_type = ?
            ORDER BY df.uploaded_at DESC
        ");
        
        $stmt->execute([$userId, $category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching category files: " . $e->getMessage());
        return [];
    }
}

// Get pending submissions - updated to work with actual database categories
function getPendingSubmissions($fileCategories, $submissionStats) {
    $pending = [];
    $currentYear = date('Y');
    $currentMonth = date('n');
    
    // Determine current semester
    $currentSemester = ($currentMonth >= 8 || $currentMonth <= 12) ? '1st Semester' : '2nd Semester';
    
    foreach ($fileCategories as $key => $category) {
        if ($category['required']) {
            $hasCurrentSemester = false;
            
            // Check against the database name (db_name)
            foreach ($submissionStats as $stat) {
                if ($stat['category'] === $category['db_name'] && 
                    $stat['academic_year'] == $currentYear && 
                    $stat['semester_period'] === $currentSemester) {
                    $hasCurrentSemester = true;
                    break;
                }
            }
            
            if (!$hasCurrentSemester) {
                $pending[] = [
                    'category' => $key,
                    'name' => $category['name'],
                    'semester' => $currentSemester,
                    'deadline' => $category['deadline'],
                    'priority' => 'high',
                    'academic_year' => $currentYear
                ];
            }
        }
    }
    
    return $pending;
}

// Format file size
function formatFileSize($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get file icon based on extension
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $iconMap = [
        'pdf' => 'bxs-file-pdf',
        'doc' => 'bxs-file-doc',
        'docx' => 'bxs-file-doc',
        'xls' => 'bxs-spreadsheet',
        'xlsx' => 'bxs-spreadsheet',
        'ppt' => 'bxs-file-blank',
        'pptx' => 'bxs-file-blank',
        'jpg' => 'bxs-file-image',
        'jpeg' => 'bxs-file-image',
        'png' => 'bxs-file-image',
        'gif' => 'bxs-file-image',
        'txt' => 'bxs-file-txt',
        'zip' => 'bxs-file-archive',
        'rar' => 'bxs-file-archive',
        'mp4' => 'bxs-videos',
        'avi' => 'bxs-videos',
        'mp3' => 'bxs-music',
        'wav' => 'bxs-music'
    ];
    
    return isset($iconMap[$ext]) ? $iconMap[$ext] : 'bxs-file';
}

// Get user's department
function getUserDepartment($pdo, $departmentId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ? AND is_active = 1");
        $stmt->execute([$departmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching user department: " . $e->getMessage());
        return null;
    }
}

// Map database file types back to category keys for JavaScript
function mapDatabaseCategoriesToKeys($submissionStats, $fileCategories) {
    $mappedStats = [];
    
    foreach ($submissionStats as $stat) {
        $categoryKey = null;
        
        // Find the category key that matches this database file type
        foreach ($fileCategories as $key => $category) {
            if ($category['db_name'] === $stat['category']) {
                $categoryKey = $key;
                break;
            }
        }
        
        if ($categoryKey) {
            $stat['category_key'] = $categoryKey;
            $mappedStats[] = $stat;
        }
    }
    
    return $mappedStats;
}

// Fetch data using the new functions
$userDepartment = getUserDepartment($pdo, $userDepartmentId);
$submissionStats = getSubmissionStatsFromDocumentFiles($pdo, $currentUser['id']);
$mappedSubmissionStats = mapDatabaseCategoriesToKeys($submissionStats, $fileCategories);
$recentSubmissions = getRecentSubmissionsFromDocumentFiles($pdo, $currentUser['id']);
$pendingSubmissions = getPendingSubmissions($fileCategories, $submissionStats);
$availableAcademicYears = getAvailableAcademicYearsFromDocumentFiles($pdo, $currentUser['id']);

// Department configuration
$departmentConfig = [
    'TED' => ['icon' => 'bxs-graduation', 'color' => '#f59e0b'],
    'MD' => ['icon' => 'bxs-business', 'color' => '#1e40af'],
    'FASD' => ['icon' => 'bx bx-water', 'color' => '#0284c7'],
    'ASD' => ['icon' => 'bxs-palette', 'color' => '#d946ef'],
    'ITD' => ['icon' => 'bxs-chip', 'color' => '#0f766e'],
    'NSTP' => ['icon' => 'bxs-user-check', 'color' => '#22c55e'],
    'OTHR' => ['icon' => 'bxs-file', 'color' => '#6b7280']
];

$departmentImage = null;
$departmentCode = null;

if ($userDepartment) {
    $departmentCode = $userDepartment['department_code'];
    $departmentImage = "../../img/{$departmentCode}.jpg";
}
?>