<?php
// CREATE: ajax/search_submissions.php
require_once '../../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

$searchTerm = trim($input['search_term'] ?? '');
$categoryFilter = $input['category_filter'] ?? null;
$year = (int)($input['year'] ?? date('Y'));
$semester = $input['semester'] ?? 'First Semester';

if (strlen($searchTerm) < 2) {
    echo json_encode(['success' => false, 'error' => 'Search term too short']);
    exit();
}

try {
    $sql = "
        SELECT 
            f.id,
            f.file_name,
            f.original_name,
            f.uploaded_at,
            f.file_size,
            f.file_extension,
            f.download_count,
            fo.category,
            fo.folder_name
        FROM files f
        JOIN folders fo ON f.folder_id = fo.id
        WHERE f.uploaded_by = ? 
        AND fo.department_id = ? 
        AND f.academic_year = ?
        AND f.semester = ?
        AND f.is_deleted = 0 
        AND fo.is_deleted = 0
        AND (
            f.file_name LIKE ? 
            OR f.original_name LIKE ? 
            OR fo.folder_name LIKE ?
        )
    ";
    
    $params = [
        $currentUser['id'],
        $currentUser['department_id'],
        $year,
        $semester,
        "%$searchTerm%",
        "%$searchTerm%",
        "%$searchTerm%"
    ];
    
    if ($categoryFilter) {
        $sql .= " AND fo.category = ?";
        $params[] = $categoryFilter;
    }
    
    $sql .= " ORDER BY f.uploaded_at DESC LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);
    
} catch(Exception $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
?>