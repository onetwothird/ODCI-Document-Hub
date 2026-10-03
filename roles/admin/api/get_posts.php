<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get filter and pagination parameters
$filter = $_GET['filter'] ?? 'all';
$page = intval($_GET['page'] ?? 0);
$limit = intval($_GET['limit'] ?? 10);
$offset = $page * $limit;

try {
    $query = "
        SELECT p.*, u.username, u.name, u.mi, u.surname,
               CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
               u.profile_image, u.position, d.department_code,
               (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as like_count,
               (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.is_deleted = 0) as comment_count,
               EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = :user_id) as user_liked
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE p.is_deleted = 0
    ";

    $params = [':user_id' => $_SESSION['user_id']];

    // Apply filters
    if ($filter === 'reported') {
        $query .= " AND p.report_count > 0";
    } elseif ($filter === 'department') {
        $query .= " AND (u.department_id = :dept_id OR JSON_CONTAINS(p.target_departments, CAST(:dept_id_json AS JSON)))";
        $params[':dept_id'] = $_SESSION['department_id'];
        $params[':dept_id_json'] = json_encode([$_SESSION['department_id']]);
    } elseif ($filter === 'pinned') {
        $query .= " AND p.is_pinned = 1";
    } elseif ($filter === 'my_posts') {
        $query .= " AND p.user_id = :user_id";
    }

    $query .= " ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $posts = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'page' => $page,
        'has_more' => count($posts) === $limit
    ]);

} catch (Exception $e) {
    error_log("Error fetching posts: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching posts']);
}
?>