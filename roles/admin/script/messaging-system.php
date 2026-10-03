<?php
// script/messaging-system.php
require_once '../../includes/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Handle different messaging actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'send_message':
        handleSendMessage();
        break;
    case 'get_conversations':
        handleGetConversations();
        break;
    case 'get_messages':
        handleGetMessages();
        break;
    case 'mark_as_read':
        handleMarkAsRead();
        break;
    case 'send_bulk_message':
        handleSendBulkMessage();
        break;
    case 'get_message_templates':
        handleGetMessageTemplates();
        break;
    case 'save_template':
        handleSaveTemplate();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleSendMessage() {
    global $pdo, $currentUser;
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }

    $recipientId = $_POST['recipient_id'] ?? null;
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';
    $messageType = $_POST['message_type'] ?? 'general';

    if (!$recipientId || !$message) {
        echo json_encode(['success' => false, 'message' => 'Recipient and message are required']);
        return;
    }

    try {
        // Verify recipient exists and is in the same department (for security)
        $stmt = $pdo->prepare("
            SELECT id, email, name, surname, department_id 
            FROM users 
            WHERE id = ? AND is_approved = 1
        ");
        $stmt->execute([$recipientId]);
        $recipient = $stmt->fetch();

        if (!$recipient) {
            echo json_encode(['success' => false, 'message' => 'Recipient not found']);
            return;
        }

        // Check if admin can message this user (same department or admin privileges)
        if ($currentUser['role'] === 'admin' && 
            $currentUser['department_id'] !== $recipient['department_id']) {
            echo json_encode(['success' => false, 'message' => 'Cannot send message to user in different department']);
            return;
        }

        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, recipient_id, subject, message, priority, message_type, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $currentUser['id'],
            $recipientId,
            $subject,
            $message,
            $priority,
            $messageType
        ]);

        $messageId = $pdo->lastInsertId();

        // Add notification
        addNotification(
            $pdo, 
            $recipientId, 
            $subject ?: 'New Message', 
            substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''),
            'info',
            "messages.php?id=$messageId"
        );

        // Log activity
        logActivity($pdo, $currentUser['id'], 'send_message', 'user', $recipientId, "Sent message: $subject");

        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully',
            'message_id' => $messageId
        ]);

    } catch (Exception $e) {
        error_log("Error sending message: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

function handleSendBulkMessage() {
    global $pdo, $currentUser;
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }

    $recipientIds = json_decode($_POST['recipient_ids'] ?? '[]', true);
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';
    $messageType = $_POST['message_type'] ?? 'announcement';

    if (empty($recipientIds) || !$message) {
        echo json_encode(['success' => false, 'message' => 'Recipients and message are required']);
        return;
    }

    try {
        $successCount = 0;
        $failedCount = 0;

        foreach ($recipientIds as $recipientId) {
            try {
                // Verify recipient
                $stmt = $pdo->prepare("
                    SELECT id, department_id 
                    FROM users 
                    WHERE id = ? AND is_approved = 1
                ");
                $stmt->execute([$recipientId]);
                $recipient = $stmt->fetch();

                if ($recipient && ($currentUser['role'] === 'super_admin' || 
                    $currentUser['department_id'] === $recipient['department_id'])) {
                    
                    // Insert message
                    $stmt = $pdo->prepare("
                        INSERT INTO messages (sender_id, recipient_id, subject, message, priority, message_type, sent_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $currentUser['id'],
                        $recipientId,
                        $subject,
                        $message,
                        $priority,
                        $messageType
                    ]);

                    // Add notification
                    addNotification(
                        $pdo, 
                        $recipientId, 
                        $subject ?: 'Bulk Message', 
                        substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''),
                        'info'
                    );

                    $successCount++;
                } else {
                    $failedCount++;
                }
            } catch (Exception $e) {
                error_log("Error sending bulk message to user $recipientId: " . $e->getMessage());
                $failedCount++;
            }
        }

        // Log activity
        logActivity($pdo, $currentUser['id'], 'send_bulk_message', 'system', null, 
                   "Sent bulk message to $successCount users. Failed: $failedCount");

        echo json_encode([
            'success' => true,
            'message' => "Message sent to $successCount recipients. $failedCount failed.",
            'success_count' => $successCount,
            'failed_count' => $failedCount
        ]);

    } catch (Exception $e) {
        error_log("Error sending bulk message: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to send bulk message']);
    }
}

function handleGetConversations() {
    global $pdo, $currentUser;

    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                CASE 
                    WHEN m.sender_id = ? THEN m.recipient_id 
                    ELSE m.sender_id 
                END as contact_id,
                CASE 
                    WHEN m.sender_id = ? THEN CONCAT(ru.name, ' ', ru.surname)
                    ELSE CONCAT(su.name, ' ', su.surname)
                END as contact_name,
                CASE 
                    WHEN m.sender_id = ? THEN ru.profile_image
                    ELSE su.profile_image
                END as contact_image,
                CASE 
                    WHEN m.sender_id = ? THEN ru.position
                    ELSE su.position
                END as contact_position,
                MAX(m.sent_at) as last_message_time,
                COUNT(CASE WHEN m.recipient_id = ? AND m.is_read = 0 THEN 1 END) as unread_count,
                (SELECT message FROM messages m2 
                 WHERE (m2.sender_id = ? AND m2.recipient_id = contact_id) 
                    OR (m2.recipient_id = ? AND m2.sender_id = contact_id)
                 ORDER BY m2.sent_at DESC LIMIT 1) as last_message
            FROM messages m
            LEFT JOIN users su ON m.sender_id = su.id
            LEFT JOIN users ru ON m.recipient_id = ru.id
            WHERE m.sender_id = ? OR m.recipient_id = ?
            GROUP BY contact_id, contact_name, contact_image, contact_position
            ORDER BY last_message_time DESC
        ");
        
        $userId = $currentUser['id'];
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
        $conversations = $stmt->fetchAll();

        // Add profile image URLs
        foreach ($conversations as &$conversation) {
            $conversation['contact_image_url'] = getProfileImageUrl($conversation['contact_image']);
            $conversation['last_message_time_formatted'] = date('M j, g:i A', strtotime($conversation['last_message_time']));
        }

        echo json_encode([
            'success' => true,
            'conversations' => $conversations
        ]);

    } catch (Exception $e) {
        error_log("Error getting conversations: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load conversations']);
    }
}

function handleGetMessages() {
    global $pdo, $currentUser;

    $contactId = $_GET['contact_id'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    if (!$contactId) {
        echo json_encode(['success' => false, 'message' => 'Contact ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                CONCAT(s.name, ' ', s.surname) as sender_name,
                s.profile_image as sender_image,
                s.position as sender_position
            FROM messages m
            LEFT JOIN users s ON m.sender_id = s.id
            WHERE (m.sender_id = ? AND m.recipient_id = ?) 
               OR (m.sender_id = ? AND m.recipient_id = ?)
            ORDER BY m.sent_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$currentUser['id'], $contactId, $contactId, $currentUser['id'], $limit, $offset]);
        $messages = $stmt->fetchAll();

        // Add profile image URLs and format dates
        foreach ($messages as &$message) {
            $message['sender_image_url'] = getProfileImageUrl($message['sender_image']);
            $message['sent_at_formatted'] = date('M j, g:i A', strtotime($message['sent_at']));
            $message['is_own_message'] = ($message['sender_id'] == $currentUser['id']);
        }

        // Mark messages as read
        if (!empty($messages)) {
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET is_read = 1, read_at = NOW() 
                WHERE sender_id = ? AND recipient_id = ? AND is_read = 0
            ");
            $stmt->execute([$contactId, $currentUser['id']]);
        }

        echo json_encode([
            'success' => true,
            'messages' => array_reverse($messages) // Reverse to show oldest first
        ]);

    } catch (Exception $e) {
        error_log("Error getting messages: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load messages']);
    }
}

function handleMarkAsRead() {
    global $pdo, $currentUser;

    $messageId = $_POST['message_id'] ?? null;

    if (!$messageId) {
        echo json_encode(['success' => false, 'message' => 'Message ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1, read_at = NOW() 
            WHERE id = ? AND recipient_id = ?
        ");
        $stmt->execute([$messageId, $currentUser['id']]);

        echo json_encode(['success' => true, 'message' => 'Message marked as read']);

    } catch (Exception $e) {
        error_log("Error marking message as read: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to mark message as read']);
    }
}

function handleGetMessageTemplates() {
    global $pdo, $currentUser;

    try {
        // Get system templates and user's custom templates
        $stmt = $pdo->prepare("
            SELECT * FROM message_templates 
            WHERE created_by IS NULL OR created_by = ?
            ORDER BY is_system DESC, template_name ASC
        ");
        $stmt->execute([$currentUser['id']]);
        $templates = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'templates' => $templates
        ]);

    } catch (Exception $e) {
        error_log("Error getting message templates: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load templates']);
    }
}

function handleSaveTemplate() {
    global $pdo, $currentUser;
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }

    $templateName = sanitizeInput($_POST['template_name'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $templateType = $_POST['template_type'] ?? 'general';

    if (!$templateName || !$message) {
        echo json_encode(['success' => false, 'message' => 'Template name and message are required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO message_templates (template_name, subject, message, template_type, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$templateName, $subject, $message, $templateType, $currentUser['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Template saved successfully',
            'template_id' => $pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        error_log("Error saving message template: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to save template']);
    }
}

// Create message_templates table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS message_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_name VARCHAR(100) NOT NULL,
            subject VARCHAR(255),
            message TEXT NOT NULL,
            template_type ENUM('general', 'reminder', 'announcement', 'warning') DEFAULT 'general',
            is_system BOOLEAN DEFAULT FALSE,
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            recipient_id INT NOT NULL,
            subject VARCHAR(255),
            message TEXT NOT NULL,
            priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
            message_type ENUM('general', 'reminder', 'announcement', 'warning', 'system') DEFAULT 'general',
            is_read BOOLEAN DEFAULT FALSE,
            read_at DATETIME,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_recipient_read (recipient_id, is_read),
            INDEX idx_sender_time (sender_id, sent_at),
            INDEX idx_conversation (sender_id, recipient_id, sent_at)
        )
    ");
} catch (Exception $e) {
    error_log("Error creating message tables: " . $e->getMessage());
}
?>