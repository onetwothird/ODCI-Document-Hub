<?php
// ODCI/roles/user/handlers/category_counts.php
require_once '../../../includes/config.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get current user information
$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Get user's department ID
$userDepartmentId = null;
if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    $userDepartmentId = $currentUser['department_id'];
} elseif (isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $user = $stmt->fetch();
        if ($user && $user['department_id']) {
            $userDepartmentId = $user['department_id'];
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

// Ensure user has a department
if (!$userDepartmentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No department assigned']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['department_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Department ID required']);
    exit();
}

$requestedDepartmentId = intval($input['department_id']);
$requestedUserDepartmentId = intval($input['user_department_id'] ?? 0);

// SECURITY CHECK: User can only access files from their own department
if ($requestedDepartmentId != $userDepartmentId || $requestedUserDepartmentId != $userDepartmentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: Can only view files from your department']);
    exit();
}

try {
    // Define file categories (same as in your main file)
    $fileCategories = [
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
        'exam_acknowledgement' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form',
        'consultation_log' => 'Consultation Log Sheet Form'
    ];
    
    $counts = [];
    
    // Get file counts for each category
    foreach ($fileCategories as $categoryKey => $categoryName) {
        $stmt = $pdo->prepare("
            SELECT COUNT(f.id) as file_count
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            WHERE fo.department_id = ? 
            AND fo.category = ?
            AND f.is_deleted = 0 
            AND fo.is_deleted = 0
        ");
        $stmt->execute([$requestedDepartmentId, $categoryKey]);
        $result = $stmt->fetch();
        $counts[$categoryKey] = intval($result['file_count'] ?? 0);
    }
    
    echo json_encode([
        'success' => true,
        'counts' => $counts
    ]);

} catch(Exception $e) {
    error_log("Error fetching category counts: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>