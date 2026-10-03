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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$action = $input['action'] ?? '';
$faculty_ids = $input['faculty_ids'] ?? [];
$document_types = $input['document_types'] ?? [];
$semester = $input['semester'] ?? '';
$message = $input['message'] ?? '';

if (empty($action) || empty($faculty_ids)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

if (!is_array($faculty_ids)) {
    echo json_encode(['success' => false, 'message' => 'Faculty IDs must be an array']);
    exit;
}

try {
    $admin_department_id = $currentUser['department_id'] ?? null;
    
    // Verify all faculty belong to admin's department
    if ($admin_department_id) {
        $placeholders = implode(',', array_fill(0, count($faculty_ids), '?'));
        $verify_query = "
            SELECT u.id, u.name, u.surname, u.department_id, d.parent_id
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id IN ($placeholders) AND u.role = 'user'
        ";
        
        $stmt = $pdo->prepare($verify_query);
        $stmt->execute($faculty_ids);
        $faculty_list = $stmt->fetchAll();
        
        if (count($faculty_list) !== count($faculty_ids)) {
            throw new Exception('Some faculty members not found');
        }
        
        // Check department access
        foreach ($faculty_list as $faculty) {
            if ($faculty['department_id'] != $admin_department_id && 
                $faculty['parent_id'] != $admin_department_id) {
                throw new Exception("Access denied for faculty: {$faculty['name']} {$faculty['surname']}");
            }
        }
    } else {
        // Super admin - just verify faculty exist
        $placeholders = implode(',', array_fill(0, count($faculty_ids), '?'));
        $verify_query = "SELECT id, name, surname FROM users WHERE id IN ($placeholders) AND role = 'user'";
        
        $stmt = $pdo->prepare($verify_query);
        $stmt->execute($faculty_ids);
        $faculty_list = $stmt->fetchAll();
        
        if (count($faculty_list) !== count($faculty_ids)) {
            throw new Exception('Some faculty members not found');
        }
    }
    
    $results = [];
    $pdo->beginTransaction();
    
    try {
        switch ($action) {
            case 'send_bulk_reminders':
                $results = handleBulkReminders($pdo, $faculty_list, $document_types, $semester, $message, $currentUser);
                break;
                
            case 'add_bulk_notes':
                $results = handleBulkNotes($pdo, $faculty_list, $document_types, $semester, $message, $currentUser);
                break;
                
            case 'set_bulk_deadlines':
                $deadline = $input['deadline'] ?? '';
                $results = handleBulkDeadlines($pdo, $faculty_list, $document_types, $semester, $deadline, $message, $currentUser);
                break;
                
            case 'export_faculty_data':
                $results = handleBulkExport($pdo, $faculty_list, $document_types, $semester);
                break;
                
            default:
                throw new Exception('Unknown bulk action');
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Bulk operation completed successfully',
            'results' => $results
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in bulk_operations.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleBulkReminders($pdo, $faculty_list, $document_types, $semester, $message, $currentUser) {
    $results = ['sent' => 0, 'skipped' => 0, 'errors' => []];
    
    // Get all document types if none specified
    if (empty($document_types)) {
        $document_types = [
            'IPCR Accomplishment', 'IPCR Target', 'Workload', 'Course Syllabus',
            'Course Syllabus Acceptance Form', 'Exam', 'TOS', 'Class Record',
            'Grading Sheets', 'Attendance Sheet', "Stakeholder's Feedback Form w/ Summary",
            'Consultation', 'Lecture', 'Activities', 'CEIT-QF-03 Discussion Form', 'Others'
        ];
    }
    
    foreach ($faculty_list as $faculty) {
        $faculty_reminders = 0;
        
        foreach ($document_types as $doc_type) {
            try {
                // Check if document is already submitted
                $check_query = "SELECT COUNT(*) as count FROM document_files WHERE uploaded_by = ? AND file_type = ? AND is_deleted = FALSE";
                $stmt = $pdo->prepare($check_query);
                $stmt->execute([$faculty['id'], $doc_type]);
                
                if ($stmt->fetch()['count'] > 0) {
                    continue; // Skip if already submitted
                }
                
                // Check for recent reminders
                $recent_query = "
                    SELECT COUNT(*) as count 
                    FROM document_submission_history 
                    WHERE faculty_id = ? AND document_type = ? AND status = 'reminder_sent' 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ";
                $stmt = $pdo->prepare($recent_query);
                $stmt->execute([$faculty['id'], $doc_type]);
                
                if ($stmt->fetch()['count'] > 0) {
                    continue; // Skip if reminder sent recently
                }
                
                // Record reminder
                $reminder_query = "
                    INSERT INTO document_submission_history
                    (faculty_id, document_type, semester, status, note, admin_id, created_at)
                    VALUES (?, ?, ?, 'reminder_sent', ?, ?, NOW())
                ";
                
                $reminder_note = $message ?: "Bulk reminder for missing document: $doc_type ($semester)";
                $stmt = $pdo->prepare($reminder_query);
                $stmt->execute([$faculty['id'], $doc_type, $semester, $reminder_note, $currentUser['id']]);
                
                // Create notification
                $notification_query = "
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    VALUES (?, ?, ?, 'reminder', NOW())
                ";
                
                $notification_title = "Document Submission Reminder";
                $notification_message = "Please submit your $doc_type document for $semester";
                
                $stmt = $pdo->prepare($notification_query);
                $stmt->execute([$faculty['id'], $notification_title, $notification_message]);
                
                $faculty_reminders++;
                
            } catch (Exception $e) {
                $results['errors'][] = "Error sending reminder to {$faculty['name']} {$faculty['surname']} for $doc_type: " . $e->getMessage();
            }
        }
        
        if ($faculty_reminders > 0) {
            $results['sent']++;
            
            // Update faculty reminder count
            $update_query = "UPDATE users SET reminder_count = COALESCE(reminder_count, 0) + ?, last_reminder_sent = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([$faculty_reminders, $faculty['id']]);
        } else {
            $results['skipped']++;
        }
    }
    
    return $results;
}

function handleBulkNotes($pdo, $faculty_list, $document_types, $semester, $note, $currentUser) {
    $results = ['added' => 0, 'errors' => []];
    
    if (empty($note)) {
        throw new Exception('Note text is required for bulk note operation');
    }
    
    if (empty($document_types)) {
        $document_types = [
            'IPCR Accomplishment', 'IPCR Target', 'Workload', 'Course Syllabus',
            'Course Syllabus Acceptance Form', 'Exam', 'TOS', 'Class Record',
            'Grading Sheets', 'Attendance Sheet', "Stakeholder's Feedback Form w/ Summary",
            'Consultation', 'Lecture', 'Activities', 'CEIT-QF-03 Discussion Form', 'Others'
        ];
    }
    
    foreach ($faculty_list as $faculty) {
        try {
            foreach ($document_types as $doc_type) {
                $note_query = "
                    INSERT INTO document_submission_history
                    (faculty_id, document_type, semester, status, note, admin_id, created_at)
                    VALUES (?, ?, ?, 'note_added', ?, ?, NOW())
                ";
                
                $stmt = $pdo->prepare($note_query);
                $stmt->execute([$faculty['id'], $doc_type, $semester, $note, $currentUser['id']]);
            }
            
            $results['added']++;
            
        } catch (Exception $e) {
            $results['errors'][] = "Error adding note for {$faculty['name']} {$faculty['surname']}: " . $e->getMessage();
        }
    }
    
    return $results;
}

function handleBulkDeadlines($pdo, $faculty_list, $document_types, $semester, $deadline, $note, $currentUser) {
    $results = ['set' => 0, 'errors' => []];
    
    if (empty($deadline)) {
        throw new Exception('Deadline is required for bulk deadline operation');
    }
    
    // Validate deadline format
    $deadline_date = DateTime::createFromFormat('Y-m-d', $deadline);
    if (!$deadline_date) {
        throw new Exception('Invalid deadline format. Use YYYY-MM-DD');
    }
    
    if ($deadline_date < new DateTime()) {
        throw new Exception('Deadline cannot be in the past');
    }
    
    if (empty($document_types)) {
        $document_types = [
            'IPCR Accomplishment', 'IPCR Target', 'Workload', 'Course Syllabus',
            'Course Syllabus Acceptance Form', 'Exam', 'TOS', 'Class Record',
            'Grading Sheets', 'Attendance Sheet', "Stakeholder's Feedback Form w/ Summary",
            'Consultation', 'Lecture', 'Activities', 'CEIT-QF-03 Discussion Form', 'Others'
        ];
    }
    
    foreach ($faculty_list as $faculty) {
        try {
            foreach ($document_types as $doc_type) {
                // Insert or update deadline
                $deadline_query = "
                    INSERT INTO document_deadlines 
                    (faculty_id, document_type, semester, deadline, note, admin_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    deadline = VALUES(deadline), 
                    note = VALUES(note),
                    admin_id = VALUES(admin_id),
                    updated_at = NOW()
                ";
                
                $deadline_note = $note ?: "Bulk deadline set for $doc_type";
                $stmt = $pdo->prepare($deadline_query);
                $stmt->execute([$faculty['id'], $doc_type, $semester, $deadline, $deadline_note, $currentUser['id']]);
                
                // Create notification
                $notification_query = "
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    VALUES (?, ?, ?, 'deadline', NOW())
                ";
                
                $notification_title = "New Deadline Set - $doc_type";
                $notification_message = "A deadline of $deadline has been set for your $doc_type submission ($semester)";
                
                $stmt = $pdo->prepare($notification_query);
                $stmt->execute([$faculty['id'], $notification_title, $notification_message]);
                
                // Record in history
                $history_query = "
                    INSERT INTO document_submission_history
                    (faculty_id, document_type, semester, status, note, admin_id, created_at)
                    VALUES (?, ?, ?, 'deadline_set', ?, ?, NOW())
                ";
                
                $history_note = "Deadline set: $deadline" . ($note ? " - $note" : "");
                $stmt = $pdo->prepare($history_query);
                $stmt->execute([$faculty['id'], $doc_type, $semester, $history_note, $currentUser['id']]);
            }
            
            $results['set']++;
            
        } catch (Exception $e) {
            $results['errors'][] = "Error setting deadline for {$faculty['name']} {$faculty['surname']}: " . $e->getMessage();
        }
    }
    
    return $results;
}

function handleBulkExport($pdo, $faculty_list, $document_types, $semester) {
    $results = ['faculty_data' => [], 'summary' => []];
    
    if (empty($document_types)) {
        $document_types = [
            'IPCR Accomplishment', 'IPCR Target', 'Workload', 'Course Syllabus',
            'Course Syllabus Acceptance Form', 'Exam', 'TOS', 'Class Record',
            'Grading Sheets', 'Attendance Sheet', "Stakeholder's Feedback Form w/ Summary",
            'Consultation', 'Lecture', 'Activities', 'CEIT-QF-03 Discussion Form', 'Others'
        ];
    }
    
    foreach ($faculty_list as $faculty) {
        $faculty_data = [
            'id' => $faculty['id'],
            'name' => $faculty['name'] . ' ' . $faculty['surname'],
            'employee_id' => $faculty['employee_id'] ?? '',
            'email' => $faculty['email'] ?? '',
            'department' => $faculty['department_name'] ?? '',
            'documents' => [],
            'submission_stats' => [
                'submitted' => 0,
                'missing' => 0,
                'total_files' => 0,
                'completion_rate' => 0
            ]
        ];
        
        foreach ($document_types as $doc_type) {
            $doc_query = "
                SELECT COUNT(*) as file_count, 
                       MAX(uploaded_at) as latest_upload,
                       SUM(file_size) as total_size
                FROM document_files 
                WHERE uploaded_by = ? AND file_type = ? AND is_deleted = FALSE
            ";
            
            $stmt = $pdo->prepare($doc_query);
            $stmt->execute([$faculty['id'], $doc_type]);
            $doc_data = $stmt->fetch();
            
            $is_submitted = $doc_data['file_count'] > 0;
            
            $faculty_data['documents'][$doc_type] = [
                'submitted' => $is_submitted,
                'file_count' => (int)$doc_data['file_count'],
                'latest_upload' => $doc_data['latest_upload'],
                'total_size' => (int)$doc_data['total_size']
            ];
            
            if ($is_submitted) {
                $faculty_data['submission_stats']['submitted']++;
                $faculty_data['submission_stats']['total_files'] += (int)$doc_data['file_count'];
            } else {
                $faculty_data['submission_stats']['missing']++;
            }
        }
        
        $faculty_data['submission_stats']['completion_rate'] = 
            round(($faculty_data['submission_stats']['submitted'] / count($document_types)) * 100, 1);
        
        $results['faculty_data'][] = $faculty_data;
    }
    
    // Generate summary
    $total_faculty = count($faculty_list);
    $total_possible_submissions = $total_faculty * count($document_types);
    $total_actual_submissions = 0;
    $complete_submissions = 0;
    
    foreach ($results['faculty_data'] as $faculty_data) {
        $total_actual_submissions += $faculty_data['submission_stats']['submitted'];
        if ($faculty_data['submission_stats']['completion_rate'] == 100) {
            $complete_submissions++;
        }
    }
    
    $results['summary'] = [
        'total_faculty' => $total_faculty,
        'total_document_types' => count($document_types),
        'total_possible_submissions' => $total_possible_submissions,
        'total_actual_submissions' => $total_actual_submissions,
        'complete_submissions' => $complete_submissions,
        'overall_completion_rate' => round(($total_actual_submissions / $total_possible_submissions) * 100, 1),
        'faculty_completion_rate' => round(($complete_submissions / $total_faculty) * 100, 1),
        'semester' => $semester,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    return $results;
}

// Additional helper functions for the bulk operations

function validateDocumentTypes($document_types) {
    $valid_types = [
        'IPCR Accomplishment', 'IPCR Target', 'Workload', 'Course Syllabus',
        'Course Syllabus Acceptance Form', 'Exam', 'TOS', 'Class Record',
        'Grading Sheets', 'Attendance Sheet', "Stakeholder's Feedback Form w/ Summary",
        'Consultation', 'Lecture', 'Activities', 'CEIT-QF-03 Discussion Form', 'Others'
    ];
    
    foreach ($document_types as $type) {
        if (!in_array($type, $valid_types)) {
            throw new Exception("Invalid document type: $type");
        }
    }
    
    return true;
}

function logBulkAction($pdo, $admin_id, $action, $faculty_count, $details) {
    $log_query = "
        INSERT INTO admin_action_log (admin_id, action_type, details, created_at)
        VALUES (?, ?, ?, NOW())
    ";
    
    $log_details = json_encode([
        'bulk_action' => $action,
        'faculty_count' => $faculty_count,
        'details' => $details
    ]);
    
    $stmt = $pdo->prepare($log_query);
    $stmt->execute([$admin_id, 'bulk_operation', $log_details]);
}

// Rate limiting for bulk operations
function checkBulkRateLimit($pdo, $admin_id, $action) {
    $rate_query = "
        SELECT COUNT(*) as count
        FROM admin_action_log
        WHERE admin_id = ? AND action_type = 'bulk_operation' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    
    $stmt = $pdo->prepare($rate_query);
    $stmt->execute([$admin_id]);
    $recent_operations = $stmt->fetch()['count'];
    
    // Allow max 10 bulk operations per hour
    if ($recent_operations >= 10) {
        throw new Exception('Bulk operation rate limit exceeded. Please wait before performing another bulk operation.');
    }
    
    return true;
}

// Email notification helper (if email system is available)
function sendBulkNotificationEmails($faculty_list, $subject, $message) {
    $sent_count = 0;
    $failed_count = 0;
    
    foreach ($faculty_list as $faculty) {
        if (!empty($faculty['email']) && filter_var($faculty['email'], FILTER_VALIDATE_EMAIL)) {
            try {
                // Here you would integrate with your email system
                // For now, we'll just log it
                error_log("Bulk email would be sent to: " . $faculty['email'] . " - Subject: $subject");
                $sent_count++;
            } catch (Exception $e) {
                error_log("Failed to send bulk email to {$faculty['email']}: " . $e->getMessage());
                $failed_count++;
            }
        }
    }
    
    return ['sent' => $sent_count, 'failed' => $failed_count];
}
?>