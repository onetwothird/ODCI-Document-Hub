<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || $currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$facultyId = $_GET['id'] ?? 0;

if (!$facultyId) {
    echo json_encode(['success' => false, 'message' => 'Faculty ID required']);
    exit;
}

try {
    // Get admin's department for access control
    $admin_department_id = $currentUser['department_id'] ?? null;
    
    // Get faculty details with department verification
    $faculty_query = "
        SELECT u.*, d.department_name, d.department_code, d.parent_id,
               COUNT(DISTINCT df.id) as total_files,
               COUNT(DISTINCT df.file_type) as document_types_submitted,
               MAX(df.uploaded_at) as last_upload
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN document_files df ON u.id = df.uploaded_by
        WHERE u.id = ? AND u.role = 'user'
        GROUP BY u.id
    ";
    
    $stmt = $pdo->prepare($faculty_query);
    $stmt->execute([$facultyId]);
    $faculty = $stmt->fetch();
    
    if (!$faculty) {
        throw new Exception('Faculty member not found');
    }
    
    // Verify admin can view this faculty (department access control)
    if ($admin_department_id) {
        $can_view = false;
        
        if ($faculty['department_id'] == $admin_department_id || 
            $faculty['parent_id'] == $admin_department_id) {
            $can_view = true;
        }
        
        if (!$can_view) {
            throw new Exception('Access denied: Faculty not in your department');
        }
    }
    
    // Get document submission summary
    $document_types = [
        'IPCR Accomplishment', 'IPCR Target', 'Workload', 'Course Syllabus',
        'Course Syllabus Acceptance Form', 'Exam', 'TOS', 'Class Record',
        'Grading Sheets', 'Attendance Sheet', "Stakeholder's Feedback Form w/ Summary",
        'Consultation', 'Lecture', 'Activities', 'CEIT-QF-03 Discussion Form', 'Others'
    ];
    
    $submission_summary = [];
    $placeholders = implode(',', array_fill(0, count($document_types), '?'));
    
    $summary_query = "
        SELECT file_type, 
               COUNT(*) as file_count,
               MAX(uploaded_at) as latest_upload,
               SUM(file_size) as total_size
        FROM document_files 
        WHERE uploaded_by = ? AND file_type IN ($placeholders)
        GROUP BY file_type
    ";
    
    $params = array_merge([$facultyId], $document_types);
    $stmt = $pdo->prepare($summary_query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        $submission_summary[$row['file_type']] = [
            'file_count' => $row['file_count'],
            'latest_upload' => $row['latest_upload'],
            'total_size' => $row['total_size']
        ];
    }
    
    // Get recent activity
    $activity_query = "
        SELECT df.file_name, df.file_type, df.uploaded_at, df.file_size,
               'file_upload' as activity_type
        FROM document_files df
        WHERE df.uploaded_by = ?
        ORDER BY df.uploaded_at DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($activity_query);
    $stmt->execute([$facultyId]);
    $recent_activity = $stmt->fetchAll();
    
    // Calculate completion percentage
    $submitted_types = count($submission_summary);
    $total_types = count($document_types);
    $completion_percentage = $total_types > 0 ? round(($submitted_types / $total_types) * 100, 1) : 0;
    
    // Format response
    $response = [
        'success' => true,
        'faculty' => $faculty,
        'submission_summary' => $submission_summary,
        'recent_activity' => $recent_activity,
        'statistics' => [
            'total_files' => (int)$faculty['total_files'],
            'document_types_submitted' => $submitted_types,
            'total_document_types' => $total_types,
            'completion_percentage' => $completion_percentage,
            'last_upload' => $faculty['last_upload']
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_faculty_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>