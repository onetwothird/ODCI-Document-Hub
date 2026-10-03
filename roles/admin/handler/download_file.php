<?php
require_once '../../../includes/config.php';

// Enable detailed error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the start of the download process
error_log("Download request initiated. Request URI: " . $_SERVER['REQUEST_URI']);
error_log("GET parameters: " . print_r($_GET, true));
error_log("POST parameters: " . print_r($_POST, true));

// Verify user is logged in
if (!isLoggedIn()) {
    error_log("Download failed: User not logged in");
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['status' => 'error', 'message' => 'You must be logged in to download files']));
}

// Get current user info
try {
    $currentUser = getCurrentUser($pdo);
    if (!$currentUser) {
        throw new Exception("Could not retrieve user information");
    }
} catch (Exception $e) {
    error_log("User info error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit(json_encode(['status' => 'error', 'message' => 'User information unavailable']));
}

// Verify user permissions
if (!$currentUser['is_approved'] || $currentUser['role'] !== 'admin') {
    error_log("Download failed: User not authorized. User ID: {$currentUser['id']}, Role: {$currentUser['role']}");
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['status' => 'error', 'message' => 'You do not have permission to download this file']));
}

// Get file ID from multiple possible sources
$fileId = null;
$paramSources = [
    'GET' => $_GET,
    'POST' => $_POST,
    'REQUEST' => $_REQUEST
];

foreach ($paramSources as $source => $params) {
    foreach (['id', 'file_id', 'fileid', 'fid', 'file'] as $paramName) {
        if (isset($params[$paramName]) && !empty($params[$paramName])) {
            $fileId = filter_var($params[$paramName], FILTER_VALIDATE_INT);
            if ($fileId !== false && $fileId > 0) {
                error_log("Found valid file ID in $source.$paramName: $fileId");
                break 2;
            }
        }
    }
}

if ($fileId === null) {
    $errorDetails = [
        'message' => 'File ID is required',
        'received_parameters' => [
            'GET' => $_GET,
            'POST' => $_POST,
            'SERVER' => [
                'REQUEST_URI' => $_SERVER['REQUEST_URI'],
                'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? ''
            ]
        ]
    ];
    error_log("Missing file ID: " . print_r($errorDetails, true));
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['status' => 'error', 'details' => $errorDetails]));
}

try {
    // Get file information with extended security checks
    $stmt = $pdo->prepare("
        SELECT 
            df.id, df.file_name, df.file_path, df.file_size,
            df.uploaded_at, df.file_type, df.mime_type,
            fds.faculty_id, fds.document_type, fds.semester, fds.academic_year,
            u.department_id, u.username, u.name, u.surname,
            d.department_name, d.department_code
        FROM document_files df
        INNER JOIN faculty_document_submissions fds ON df.submission_id = fds.id
        INNER JOIN users u ON fds.faculty_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE df.id = ?
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        error_log("File not found in database. ID: $fileId");
        header('HTTP/1.1 404 Not Found');
        exit(json_encode(['status' => 'error', 'message' => 'File not found in system records']));
    }
    
    error_log("File found in database: " . print_r($file, true));

    // Permission verification
    $can_access = false;
    
    // System admin (no department or super admin) can access all files
    if (empty($currentUser['department_id']) || $currentUser['role'] === 'super_admin') {
        $can_access = true;
        error_log("Access granted: System admin override");
    } else {
        // Department admin can access files from their department
        if ($file['department_id'] == $currentUser['department_id']) {
            $can_access = true;
            error_log("Access granted: Same department");
        } else {
            // Check if faculty's department is a sub-department
            $stmt = $pdo->prepare("
                WITH RECURSIVE dept_hierarchy AS (
                    SELECT id, parent_id, department_name 
                    FROM departments WHERE id = ?
                    UNION
                    SELECT d.id, d.parent_id, d.department_name
                    FROM departments d
                    JOIN dept_hierarchy dh ON d.parent_id = dh.id
                )
                SELECT id FROM dept_hierarchy WHERE id = ?
            ");
            $stmt->execute([$file['department_id'], $currentUser['department_id']]);
            $hierarchyMatch = $stmt->fetch();
            
            if ($hierarchyMatch) {
                $can_access = true;
                error_log("Access granted: Sub-department hierarchy match");
            }
        }
    }
    
    if (!$can_access) {
        error_log("Access denied for user {$currentUser['id']} to file $fileId. User dept: {$currentUser['department_id']}, File dept: {$file['department_id']}");
        header('HTTP/1.1 403 Forbidden');
        exit(json_encode([
            'status' => 'error',
            'message' => 'Access denied',
            'details' => [
                'required_department' => $file['department_id'],
                'your_department' => $currentUser['department_id']
            ]
        ]));
    }

    // Verify file exists on disk
    $realFilePath = realpath($file['file_path']);
    if (!$realFilePath || !file_exists($realFilePath)) {
        error_log("File not found on disk: {$file['file_path']}. Real path: " . ($realFilePath ?: 'NOT FOUND'));
        header('HTTP/1.1 404 Not Found');
        exit(json_encode([
            'status' => 'error',
            'message' => 'File not found on server',
            'details' => [
                'database_path' => $file['file_path'],
                'real_path' => $realFilePath,
                'file_exists' => file_exists($realFilePath)
            ]
        ]));
    }

    // Verify file is readable
    if (!is_readable($realFilePath)) {
        error_log("File not readable: $realFilePath");
        header('HTTP/1.1 403 Forbidden');
        exit(json_encode(['status' => 'error', 'message' => 'File exists but is not readable']));
    }

    // Log the download activity
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, resource_type, resource_id, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([
            $currentUser['id'],
            'file_download',
            'document_file',
            $fileId,
            "Downloaded {$file['file_type']} file: {$file['file_name']}",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Update download count
        $pdo->prepare("UPDATE document_files SET download_count = download_count + 1 WHERE id = ?")
            ->execute([$fileId]);
            
    } catch (Exception $e) {
        error_log("Failed to log download activity: " . $e->getMessage());
    }

    // Determine content type
    $contentType = $file['mime_type'] ?? 'application/octet-stream';
    $knownMimeTypes = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // Add more as needed
    ];
    
    if (empty($contentType)) {
        $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
        $contentType = $knownMimeTypes[$ext] ?? 'application/octet-stream';
    }

    // Clean output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
    header('Content-Length: ' . filesize($realFilePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($realFilePath)) . ' GMT');
    
    // Handle range requests for large files
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        $size = filesize($realFilePath);
        list($param, $range) = explode('=', $range);
        
        if (strtolower(trim($param)) != 'bytes') {
            header('HTTP/1.1 400 Invalid Request');
            exit;
        }
        
        $range = explode(',', $range);
        $range = explode('-', $range[0]);
        
        if (count($range) != 2) {
            header('HTTP/1.1 400 Invalid Request');
            exit;
        }
        
        if ($range[0] === '') {
            $end = $size - 1;
            $start = $end - intval($range[0]);
        } elseif ($range[1] === '') {
            $start = intval($range[0]);
            $end = $size - 1;
        } else {
            $start = intval($range[0]);
            $end = intval($range[1]);
            if ($end >= $size || $start > $end) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                exit;
            }
        }
        
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        header('Content-Length: ' . ($end - $start + 1));
        
        $fp = fopen($realFilePath, 'rb');
        fseek($fp, $start);
        $bufferSize = 8192;
        $position = $start;
        
        while (!feof($fp) && $position <= $end) {
            if ($position + $bufferSize > $end) {
                $bufferSize = $end - $position + 1;
            }
            echo fread($fp, $bufferSize);
            flush();
            $position += $bufferSize;
        }
        fclose($fp);
    } else {
        // Standard file output
        readfile($realFilePath);
    }
    
    exit;

} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    header('HTTP/1.1 500 Internal Server Error');
    exit(json_encode([
        'status' => 'error',
        'message' => 'File download failed',
        'error' => $e->getMessage(),
        'trace' => $e->getTrace()
    ]));
}