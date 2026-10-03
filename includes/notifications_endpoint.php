<?php
header('Content-Type: application/json; charset=utf-8');

function notificationJsonResponse($statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
    notificationJsonResponse(401, ['success' => false, 'message' => 'Authentication is required.']);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $limit = 12;
        $stmt = $pdo->prepare("
            SELECT id, 'system' AS source, title, message, type, is_read, created_at
            FROM notifications
            WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
            UNION ALL
            SELECT id, 'social' AS source,
                   CASE notification_type
                       WHEN 'new_post' THEN 'New post'
                       WHEN 'post_comment' THEN 'Post comment'
                       WHEN 'post_like' THEN 'Post reaction'
                       WHEN 'comment_like' THEN 'Comment reaction'
                       WHEN 'comment_reply' THEN 'Comment reply'
                       WHEN 'post_mention' THEN 'Post mention'
                       WHEN 'comment_mention' THEN 'Comment mention'
                       ELSE 'Social activity'
                   END AS title,
                   message, notification_type AS type, is_read, created_at
            FROM post_notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM notifications
                 WHERE user_id = ? AND is_read = 0
                   AND (expires_at IS NULL OR expires_at > NOW())) +
                (SELECT COUNT(*) FROM post_notifications
                 WHERE user_id = ? AND is_read = 0) AS unread_count
        ");
        $stmt->execute([$userId, $userId]);
        $unreadCount = (int)$stmt->fetchColumn();

        notificationJsonResponse(200, [
            'success' => true,
            'items' => $items,
            'unreadCount' => $unreadCount
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        notificationJsonResponse(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !verifyCSRFToken($csrfToken)) {
        notificationJsonResponse(403, ['success' => false, 'message' => 'The request could not be verified. Refresh the page and try again.']);
    }

    $action = $_POST['action'] ?? '';
    $pdo->beginTransaction();

    if ($action === 'mark_all_read') {
        $systemStmt = $pdo->prepare("
            UPDATE notifications SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND is_read = 0
        ");
        $systemStmt->execute([$userId]);

        $socialStmt = $pdo->prepare("
            UPDATE post_notifications SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND is_read = 0
        ");
        $socialStmt->execute([$userId]);
    } elseif ($action === 'mark_read') {
        $notificationId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $source = $_POST['source'] ?? '';
        if (!$notificationId || !in_array($source, ['system', 'social'], true)) {
            $pdo->rollBack();
            notificationJsonResponse(400, ['success' => false, 'message' => 'A valid notification is required.']);
        }

        $table = $source === 'system' ? 'notifications' : 'post_notifications';
        $stmt = $pdo->prepare("
            UPDATE {$table} SET is_read = 1, read_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
    } else {
        $pdo->rollBack();
        notificationJsonResponse(400, ['success' => false, 'message' => 'Invalid notification action.']);
    }

    $pdo->commit();
    notificationJsonResponse(200, ['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Notification request failed: ' . $e->getMessage());
    notificationJsonResponse(500, ['success' => false, 'message' => 'Notifications are temporarily unavailable.']);
}
