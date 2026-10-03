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

$facultyId = $input['faculty_id'] ?? 0;
$docType = $input['doc_type'] ?? '';
$semester = $input['semester'] ?? '';
$note = trim($input['note'] ?? '');
$noteType = $input['note_type'] ?? 'general'; // general, urgent, follow_up, etc.

if (!$facultyId || !$docType || !$semester || empty($note)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate note length
if (strlen($note) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Note is too long (maximum 1000 characters)']);
    exit;
}

try {
    $admin_department_id = $currentUser['department_id'] ?? null;
    
    // Verify faculty exists and admin can access
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
    
    // Verify admin can add notes for this faculty
    if ($admin_department_id) {
        $can_add_note = false;
        
        if ($faculty['department_id'] == $admin_department_id || 
            $faculty['parent_id'] == $admin_department_id) {
            $can_add_note = true;
        }
        
        if (!$can_add_note) {
            throw new Exception('Access denied: Faculty not in your department');
        }
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert note into submission history
        $history_query = "
            INSERT INTO document_submission_history
            (faculty_id, document_type, semester, status, note, note_type, admin_id, created_at)
            VALUES (?, ?, ?, 'note_added', ?, ?, ?, NOW())
        ";
        
        $stmt = $pdo->prepare($history_query);
        $stmt->execute([$facultyId, $docType, $semester, $note, $noteType, $currentUser['id']]);
        $note_id = $pdo->lastInsertId();
        
        // Create notification for faculty if note is important
        if (in_array($noteType, ['urgent', 'follow_up', 'deadline'])) {
            $notification_query = "
                INSERT INTO notifications (user_id, title, message, type, created_at, is_read)
                VALUES (?, ?, ?, 'admin_note', NOW(), 0)
            ";
            
            $notification_title = "Admin Note - $docType";
            $notification_message = "Your department admin has added a note regarding your $docType submission for $semester: " . 
                                  (strlen($note) > 100 ? substr($note, 0, 100) . '...' : $note);
            
            $stmt = $pdo->prepare($notification_query);
            $stmt->execute([$facultyId, $notification_title, $notification_message]);
        }
        
        // Log the admin action
        $log_query = "
            INSERT INTO admin_action_log (admin_id, action_type, target_user_id, details, created_at)
            VALUES (?, 'add_note', ?, ?, NOW())
        ";
        
        $log_details = json_encode([
            'document_type' => $docType,
            'semester' => $semester,
            'note_type' => $noteType,
            'note_length' => strlen($note),
            'faculty_name' => $faculty['name'] . ' ' . $faculty['surname']
        ]);
        
        $stmt = $pdo->prepare($log_query);
        $stmt->execute([$currentUser['id'], $facultyId, $log_details]);
        
        $pdo->commit();
        
        // Get the created note details
        $get_note_query = "
            SELECT dsh.*, CONCAT(u.name, ' ', u.surname) as admin_name
            FROM document_submission_history dsh
            LEFT JOIN users u ON dsh.admin_id = u.id
            WHERE dsh.id = ?
        ";
        
        $stmt = $pdo->prepare($get_note_query);
        $stmt->execute([$note_id]);
        $created_note = $stmt->fetch();
        
        $response = [
            'success' => true,
            'message' => 'Note added successfully',
            'note' => $created_note,
            'details' => [
                'faculty_name' => $faculty['name'] . ' ' . $faculty['surname'],
                'document_type' => $docType,
                'semester' => $semester,
                'note_type' => $noteType,
                'admin_name' => $currentUser['name'] . ' ' . $currentUser['surname'],
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in add_submission_note.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>