<?php
require_once '../../../includes/auth_check.php';
require_once '../../../includes/config.php';
require_once '../assets/script/social_feed-script.php';

header('Content-Type: application/json');

try {
    $filter = $_GET['filter'] ?? 'all';
    $page = intval($_GET['page'] ?? 0);
    $limit = intval($_GET['limit'] ?? 10);
    $offset = $page * $limit;

    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'] ?? 'user';

    // Get user's department
    $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userDept = $stmt->fetch();
    $departmentId = $userDept['department_id'] ?? 0;

    // Build query based on filter
    $whereConditions = ["p.is_deleted = 0"];
    $params = [];

    switch ($filter) {
        case 'my_posts':
            $whereConditions[] = "p.user_id = ?";
            $params[] = $userId;
            break;

        case 'department':
            $whereConditions[] = "(p.visibility = 'department' AND JSON_CONTAINS(p.target_departments, ?))";
            $params[] = '"' . $departmentId . '"';
            break;

        case 'pinned':
            $whereConditions[] = "p.is_pinned = 1";
            break;

        default: // all posts
            $whereConditions[] = "(
                p.visibility = 'public' OR
                p.user_id = ? OR
                (p.visibility = 'department' AND JSON_CONTAINS(p.target_departments, ?)) OR
                (p.visibility = 'custom' AND JSON_CONTAINS(p.target_users, ?))
            )";
            $params = array_merge($params, [$userId, '"' . $departmentId . '"', '"' . $userId . '"']);
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Simple order by - pinned posts first, then by creation date
    $orderBy = "p.is_pinned DESC, p.created_at DESC";

    $query = "
        SELECT p.*, u.username, u.name, u.mi, u.surname,
               CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
               u.profile_image, u.position, u.role, d.department_code, d.department_name,
               EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as user_liked,
               (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id AND pc.is_deleted = 0) as comment_count,
               (SELECT COUNT(*) FROM post_views pv WHERE pv.post_id = p.id) as view_count
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE {$whereClause}
        ORDER BY {$orderBy}
    ";

    // Build final query with LIMIT and OFFSET directly in SQL to avoid parameter issues
    $finalQuery = $query . " LIMIT {$limit} OFFSET {$offset}";

    $allParams = array_merge([$userId], $params);
    $stmt = $pdo->prepare($finalQuery);
    $stmt->execute($allParams);
    $posts = $stmt->fetchAll();

    // Process each post
    foreach ($posts as &$post) {
        // Get media for each post
        $post['media'] = getPostMedia($pdo, $post['id']);

        // Track view if user hasn't viewed this post before
        try {
            $viewStmt = $pdo->prepare("
                INSERT IGNORE INTO post_views (post_id, user_id, viewed_at, ip_address, user_agent) 
                VALUES (?, ?, NOW(), ?, ?)
            ");
            $viewStmt->execute([
                $post['id'],
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Ignore duplicate entry errors
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                error_log("View tracking error for post {$post['id']}: " . $e->getMessage());
            }
        }

        // Ensure counts are integers
        $post['like_count'] = (int) $post['like_count'];
        $post['comment_count'] = (int) $post['comment_count'];
        $post['view_count'] = (int) $post['view_count'];
    }

    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'has_more' => count($posts) === $limit
    ]);

} catch (Exception $e) {
    error_log("Get posts error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading posts: ' . $e->getMessage()
    ]);
}
?>