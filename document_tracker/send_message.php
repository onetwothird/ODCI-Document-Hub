<?php
// send_message.php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Require authentication
$current_user = requireAuth();
if (!$current_user) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $recipient_id = intval($_POST['recipient_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate input
    if (empty($recipient_id) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    if (strlen($subject) > 255) {
        echo json_encode(['success' => false, 'message' => 'Subject too long (max 255 characters)']);
        exit();
    }
    
    if (strlen($message) > 5000) {
        echo json_encode(['success' => false, 'message' => 'Message too long (max 5000 characters)']);
        exit();
    }
    
    // Check if recipient exists and is approved
    $stmt = $pdo->prepare("SELECT id, name, surname, email, role FROM users WHERE id = ? AND is_approved = 1");
    $stmt->execute([$recipient_id]);
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recipient) {
        echo json_encode(['success' => false, 'message' => 'Recipient not found']);
        exit();
    }
    
    // Check permissions - super admin can message anyone, admin can message in their department
    $can_send = false;
    
    if ($current_user['role'] === 'super_admin') {
        $can_send = true;
    } elseif ($current_user['role'] === 'admin') {
        // Check if recipient is in same department
        if ($recipient['department_id'] == $current_user['department_id']) {
            $can_send = true;
        }
    } elseif ($current_user['id'] == $recipient_id) {
        // Users can't send messages to themselves
        echo json_encode(['success' => false, 'message' => 'Cannot send message to yourself']);
        exit();
    }
    
    if (!$can_send) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit();
    }
    
    // Insert message into database
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, recipient_id, subject, message, sent_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $message_sent = $stmt->execute([
        $current_user['id'],
        $recipient_id,
        $subject,
        $message
    ]);
    
    if ($message_sent) {
        $message_id = $pdo->lastInsertId();
        
        // Add notification to recipient
        $notification_title = "New Message: " . $subject;
        $notification_message = "You have received a new message from " . $current_user['name'] . " " . $current_user['surname'];
        
        addNotification(
            $pdo, 
            $recipient_id, 
            $notification_title, 
            $notification_message, 
            'info',
            "/ODCI/messages.php?id=" . $message_id
        );
        
        // Log activity
        logActivity(
            $pdo, 
            $current_user['id'], 
            'message_sent', 
            'user', 
            $recipient_id, 
            "Sent message to {$recipient['name']} {$recipient['surname']}: {$subject}"
        );
        
        echo json_encode([
            'success' => true, 
            'message' => 'Message sent successfully',
            'message_id' => $message_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
    
} catch (PDOException $e) {
    error_log("Message sending error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("General message error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>