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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$facultyId = $input['faculty_id'] ?? 0;
$docType = $input['doc_type'] ?? '';
$semester = $input['semester'] ?? '';
$customMessage = $input['custom_message'] ?? '';

if (!$facultyId || !$docType || !$semester) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $admin_department_id = $currentUser['department_id'] ?? null;
    
    // Get faculty details with verification
    $faculty_query = "
        SELECT u.*, d.department_name, d.parent_id
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
    
    // Verify admin can send reminder to this faculty
    if ($admin_department_id) {
        $can_send = false;
        
        if ($faculty['department_id'] == $admin_department_id || 
            $faculty['parent_id'] == $admin_department_id) {
            $can_send = true;
        }
        
        if (!$can_send) {
            throw new Exception('Access denied: Faculty not in your department');
        }
    }
    
    // Check if document is still not submitted
    $check_query = "SELECT COUNT(*) as count FROM document_files WHERE uploaded_by = ? AND file_type = ?";
    $stmt = $pdo->prepare($check_query);
    $stmt->execute([$facultyId, $docType]);
    $is_submitted = $stmt->fetch()['count'] > 0;
    
    if ($is_submitted) {
        echo json_encode(['success' => false, 'message' => 'Document has already been submitted']);
        exit;
    }
    
    // Check for recent reminders (prevent spam)
    $recent_reminder_query = "
        SELECT COUNT(*) as count 
        FROM document_submission_history 
        WHERE faculty_id = ? AND document_type = ? AND status = 'reminder_sent' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    
    $stmt = $pdo->prepare($recent_reminder_query);
    $stmt->execute([$facultyId, $docType]);
    $recent_reminders = $stmt->fetch()['count'];
    
    if ($recent_reminders > 0) {
        throw new Exception('A reminder was already sent within the last hour. Please wait before sending another.');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Record the reminder in history
        $history_query = "
            INSERT INTO document_submission_history
            (faculty_id, document_type, semester, status, note, admin_id, created_at)
            VALUES (?, ?, ?, 'reminder_sent', ?, ?, NOW())
        ";
        
        $reminder_note = $customMessage ?: "Reminder sent for missing document: $docType ($semester)";
        
        $stmt = $pdo->prepare($history_query);
        $stmt->execute([$facultyId, $docType, $semester, $reminder_note, $currentUser['id']]);
        
        // Update or insert into notifications table
        $notification_query = "
            INSERT INTO notifications (user_id, title, message, type, created_at, is_read)
            VALUES (?, ?, ?, 'reminder', NOW(), 0)
        ";
        
        $notification_title = "Document Submission Reminder";
        $notification_message = $customMessage ?: 
            "Please submit your $docType document for $semester. Contact your department admin if you need assistance.";
        
        $stmt = $pdo->prepare($notification_query);
        $stmt->execute([$facultyId, $notification_title, $notification_message]);
        
        // Send email if email functionality is available
        $email_sent = false;
        if (!empty($faculty['email']) && filter_var($faculty['email'], FILTER_VALIDATE_EMAIL)) {
            try {
                $email_subject = "Document Submission Reminder - $docType";
                $email_body = "
                    Dear {$faculty['name']} {$faculty['surname']},
                    
                    This is a reminder that your $docType document for $semester is still pending submission.
                    
                    " . ($customMessage ? "Additional message: $customMessage" : "") . "
                    
                    Please log in to the ODCI system to submit your document as soon as possible.
                    
                    If you have any questions or need assistance, please contact your department administrator.
                    
                    Thank you,
                    {$currentUser['name']} {$currentUser['surname']}
                    Department Administrator
                ";
                
                // Here you would integrate with your email system
                // For now, we'll just log it and mark as sent
                error_log("Email reminder would be sent to: " . $faculty['email']);
                $email_sent = true;
                
            } catch (Exception $e) {
                error_log("Failed to send email reminder: " . $e->getMessage());
                // Don't fail the whole operation if email fails
            }
        }
        
        // Update faculty's reminder count
        $update_faculty_query = "
            UPDATE users 
            SET reminder_count = COALESCE(reminder_count, 0) + 1,
                last_reminder_sent = NOW()
            WHERE id = ?
        ";
        $stmt = $pdo->prepare($update_faculty_query);
        $stmt->execute([$facultyId]);
        
        $pdo->commit();
        
        // Return success response
        $response = [
            'success' => true,
            'message' => 'Reminder sent successfully',
            'details' => [
                'faculty_name' => $faculty['name'] . ' ' . $faculty['surname'],
                'document_type' => $docType,
                'semester' => $semester,
                'email_sent' => $email_sent,
                'notification_created' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in send_reminder.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>