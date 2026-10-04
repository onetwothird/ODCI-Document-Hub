<?php
header('Content-Type: application/json');
require_once '../../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid user session']);
    exit();
}

// Check if category and user_id are provided
if (!isset($_GET['category']) || !isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$category = $_GET['category'];
$userId = $_GET['user_id'];

// Verify that the user is requesting their own files
if ($currentUser['id'] != $userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

try {
    // Map the category keys to the actual file_type values stored in database
    $categoryMap = [
        'ipcr_accomplishment' => 'IPCR Accomplishment',
        'ipcr_target' => 'IPCR Target',
        'workload' => 'Workload',
        'course_syllabus' => 'Course Syllabus',
        'syllabus_acceptance' => 'Course Syllabus Acceptance Form',
        'exam' => 'Exam',
        'tos' => 'TOS',
        'class_record' => 'Class Record',
        'grading_sheet' => 'Grading Sheet',
        'attendance_sheet' => 'Attendance Sheet',
        'stakeholder_feedback' => 'Stakeholder\'s Feedback Form w/ Summary',
        'consultation' => 'Consultation',
        'lecture' => 'Lecture',
        'activities' => 'Activities',
        'consultation_log' => 'Consultation Log Sheet Form',
        'exam_acknowledgement' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form'
        
    ];
    
    // Get the actual file_type value
    $fileType = isset($categoryMap[$category]) ? $categoryMap[$category] : $category;
    
    // Fetch files for the specific category and user
    $stmt = $pdo->prepare("
        SELECT 
            df.id,
            df.file_name,
            df.file_path,
            df.file_size,
            df.file_type,
            df.academic_year,
            df.semester_period,
            df.description,
            df.uploaded_at,
            df.mime_type,
            CONCAT(df.academic_year, ' - ', df.semester_period) as folder_name
        FROM document_files df
        WHERE df.uploaded_by = ? AND df.file_type = ?
        ORDER BY df.uploaded_at DESC
    ");
    
    $stmt->execute([$userId, $fileType]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'files' => $files,
        'count' => count($files)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching category files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>