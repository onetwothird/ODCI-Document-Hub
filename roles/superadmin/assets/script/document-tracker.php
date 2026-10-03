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
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_file_details':
            $faculty_id = intval($_GET['faculty_id']);
            $document_type = $_GET['document_type'];
            $semester = $_GET['semester'];
            $academic_year = intval($_GET['academic_year']);
            
            try {
                // Normalize semester for database query
                $semester_db = (strpos($semester, 'First') !== false || strpos($semester, '1st') !== false) ? 'first' : 'second';
                
                $stmt = $pdo->prepare("
                    SELECT f.*, u.name, u.surname, u.mi,
                           fo.folder_name
                    FROM files f 
                    JOIN users u ON f.uploaded_by = u.id 
                    LEFT JOIN folders fo ON f.folder_id = fo.id
                    WHERE f.uploaded_by = :faculty_id 
                    AND f.academic_year = :academic_year 
                    AND f.semester = :semester
                    AND (f.description LIKE :document_type OR f.original_filename LIKE :document_type)
                    AND f.is_deleted = 0
                    ORDER BY f.uploaded_at DESC
                ");
                
                $stmt->execute([
                    ':faculty_id' => $faculty_id,
                    ':academic_year' => $academic_year,
                    ':semester' => $semester_db,
                    ':document_type' => '%' . $document_type . '%'
                ]);
                
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'files' => $files]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
            
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
                $semester_db = (strpos($semester, 'First') !== false || strpos($semester, '1st') !== false) ? 'first' : 'second';
                
                $stmt = $pdo->prepare("
                    SELECT f.*, fo.folder_name
                    FROM files f 
                    LEFT JOIN folders fo ON f.folder_id = fo.id
                    WHERE f.uploaded_by = :faculty_id 
                    AND f.academic_year = :academic_year 
                    AND f.semester = :semester
                    AND f.is_deleted = 0
                    ORDER BY f.uploaded_at DESC
                ");
                
                $stmt->execute([
                    ':faculty_id' => $faculty_id,
                    ':academic_year' => $academic_year,
                    ':semester' => $semester_db
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
$selectedSemester = isset($_GET['semester']) ? $_GET['semester'] : '1st sem AY 2024-2025';

// Semester options
$semester_options = [
    '1st sem AY 2024-2025' => 'First Semester AY 2024-2025',
    '2nd sem AY 2024-2025' => 'Second Semester AY 2024-2025',
    '1st sem AY 2025-2026' => 'First Semester AY 2025-2026',
    '2nd sem AY 2025-2026' => 'Second Semester AY 2025-2026'
];

// Normalize semester for database query
$normalizedSemester = str_replace(['1st sem ', '2nd sem '], ['First Semester ', 'Second Semester '], $selectedSemester);
$selectedYear = intval(substr($selectedSemester, -9, 4));
$semester_db = (strpos($selectedSemester, '1st') !== false) ? 'first' : 'second';

// Get document types from requirements table or use defaults
try {
    $document_types_stmt = $pdo->prepare("SELECT DISTINCT document_type FROM document_requirements ORDER BY document_type");
    $document_types_stmt->execute();
    $document_types = $document_types_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    // Use default document types if table doesn't exist
    $document_types = [];
}

// If no document types found, use default set
if (empty($document_types)) {
    $document_types = [
        'Syllabus',
        'Course Outline', 
        'Lesson Plan',
        'Test Questions',
        'Grade Sheet',
        'Class Record',
        'Attendance Record',
        'Course Evaluation'
    ];
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
                SELECT u.*, d.department_name 
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE u.department_id = :department_id 
                AND u.role IN ('admin', 'user') 
                AND u.is_approved = 1
                ORDER BY u.surname, u.name
            ");
            $stmt->execute([':department_id' => $selected_department]);
            $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_faculty = count($faculty);
            
            // Get file submissions for this department and semester
            foreach ($faculty as $staff) {
                $file_submissions[$staff['id']] = [];
                
                foreach ($document_types as $doc_type) {
                    try {
                        $stmt = $pdo->prepare("
                            SELECT COUNT(f.id) as file_count, 
                                   MAX(f.uploaded_at) as latest_upload,
                                   SUM(f.file_size) as total_size
                            FROM files f 
                            WHERE f.uploaded_by = :user_id 
                            AND f.academic_year = :academic_year 
                            AND f.semester = :semester
                            AND (f.description LIKE :document_type OR f.original_filename LIKE :document_type)
                            AND f.is_deleted = 0
                        ");
                        
                        $stmt->execute([
                            ':user_id' => $staff['id'],
                            ':academic_year' => $selectedYear,
                            ':semester' => $semester_db,
                            ':document_type' => '%' . $doc_type . '%'
                        ]);
                        
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result && $result['file_count'] > 0) {
                            $file_submissions[$staff['id']][$doc_type] = $result;
                            $submitted_count++;
                        }
                    } catch(PDOException $e) {
                        error_log("Error fetching file submissions for {$doc_type}: " . $e->getMessage());
                    }
                }
            }
            
            // Calculate statistics
            $total_possible = $total_faculty * count($document_types);
            $completion_rate = $total_possible > 0 ? round(($submitted_count / $total_possible) * 100, 2) : 0;
            
            // Count faculty with complete submissions
            foreach ($faculty as $staff) {
                $staff_submitted = 0;
                foreach ($document_types as $doc_type) {
                    if (isset($file_submissions[$staff['id']][$doc_type])) {
                        $staff_submitted++;
                    }
                }
                if ($staff_submitted == count($document_types)) {
                    $complete_faculty++;
                }
            }
            
            $faculty_completion_rate = $total_faculty > 0 ? round(($complete_faculty / $total_faculty) * 100, 2) : 0;
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