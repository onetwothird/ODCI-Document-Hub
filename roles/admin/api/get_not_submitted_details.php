<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || $currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$facultyId = $_GET['faculty_id'] ?? 0;
$docType = $_GET['doc_type'] ?? '';
$semester = $_GET['semester'] ?? '';

if (!$facultyId || !$docType || !$semester) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $admin_department_id = $currentUser['department_id'] ?? null;
    
    // Get faculty details with department verification
    $faculty_query = "
        SELECT u.*, d.department_name, d.department_code, d.parent_id
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.id = ? AND u.role = 'user'
    ";
    
    $stmt = $pdo->prepare($faculty_query);
    $stmt->execute([$facultyId]);
    $faculty = $stmt->fetch();
    
    if (!$faculty) {
        throw new Exception('Faculty member not found');
    }
    
    // Verify admin can access this faculty
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
    
    // Check if document is actually not submitted
    $check_query = "
        SELECT COUNT(*) as file_count 
        FROM document_files 
        WHERE uploaded_by = ? AND file_type = ?
    ";
    
    $stmt = $pdo->prepare($check_query);
    $stmt->execute([$facultyId, $docType]);
    $file_count = $stmt->fetch()['file_count'];
    
    if ($file_count > 0) {
        throw new Exception('Document has been submitted. Please refresh the page.');
    }
    
    // Get submission history for this document type and faculty
    $history_query = "
        SELECT dsh.*, 
               CONCAT(admin.name, ' ', admin.surname) as admin_name
        FROM document_submission_history dsh
        LEFT JOIN users admin ON dsh.admin_id = admin.id
        WHERE dsh.faculty_id = ? AND dsh.document_type = ?
        ORDER BY dsh.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($history_query);
    $stmt->execute([$facultyId, $docType]);
    $history = $stmt->fetchAll();
    
    // Get related document types that are also missing
    $document_types = [
        'IPCR Accomplishment', 'IPCR Target', 'Workload', 'Course Syllabus',
        'Course Syllabus Acceptance Form', 'Exam', 'TOS', 'Class Record',
        'Grading Sheets', 'Attendance Sheet', "Stakeholder's Feedback Form w/ Summary",
        'Consultation', 'Lecture', 'Activities', 'CEIT-QF-03 Discussion Form', 'Others'
    ];
    
    $missing_docs = [];
    foreach ($document_types as $type) {
        if ($type !== $docType) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM document_files WHERE uploaded_by = ? AND file_type = ?");
            $stmt->execute([$facultyId, $type]);
            if ($stmt->fetch()['count'] == 0) {
                $missing_docs[] = $type;
            }
        }
    }
    
    // Get deadline information if exists
    $deadline_query = "
        SELECT deadline, note 
        FROM document_deadlines 
        WHERE faculty_id = ? AND document_type = ? AND semester = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($deadline_query);
    $stmt->execute([$facultyId, $docType, $semester]);
    $deadline_info = $stmt->fetch();
    
    // Get last reminder sent
    $reminder_query = "
        SELECT MAX(created_at) as last_reminder
        FROM document_submission_history
        WHERE faculty_id = ? AND document_type = ? AND status = 'reminder_sent'
    ";
    
    $stmt = $pdo->prepare($reminder_query);
    $stmt->execute([$facultyId, $docType]);
    $last_reminder = $stmt->fetch()['last_reminder'];
    
    // Calculate urgency level
    $urgency_level = 'normal';
    if ($deadline_info && $deadline_info['deadline']) {
        $deadline_date = new DateTime($deadline_info['deadline']);
        $now = new DateTime();
        $diff = $deadline_date->diff($now);
        
        if ($deadline_date < $now) {
            $urgency_level = 'overdue';
        } elseif ($diff->days <= 3) {
            $urgency_level = 'urgent';
        } elseif ($diff->days <= 7) {
            $urgency_level = 'high';
        }
    }
    
    $response = [
        'success' => true,
        'faculty' => $faculty,
        'document_type' => $docType,
        'semester' => $semester,
        'history' => $history,
        'missing_docs' => $missing_docs,
        'deadline_info' => $deadline_info,
        'last_reminder' => $last_reminder,
        'urgency_level' => $urgency_level,
        'statistics' => [
            'total_missing' => count($missing_docs) + 1, // +1 for current doc
            'total_required' => count($document_types),
            'history_count' => count($history)
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_not_submitted_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>