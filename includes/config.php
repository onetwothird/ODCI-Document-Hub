<?php
// Load environment variables from .env file (suppress phpdotenv deprecation warnings)
$previousErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Also populate getenv() for legacy code compatibility
foreach ($_ENV as $key => $value) {
    putenv("$key=$value");
}

error_reporting($previousErrorReporting);

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'odci_db');

// File Upload Configuration
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt']);

// Directory Configuration
define('BASE_URL', getenv('APP_URL') ?: 'http://localhost/ODCI');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('PROFILE_IMAGES_DIR', __DIR__ . '/../uploads/profile_images/');
define('DOCUMENT_UPLOADS_DIR', __DIR__ . '/../uploads/documents/');
define('TEMP_DIR', __DIR__ . '/../temp/');

// Security Configuration (from env with defaults)
define('HASH_ALGORITHM', getenv('HASH_ALGORITHM') ?: 'sha256');
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 28800));
define('MAX_LOGIN_ATTEMPTS', (int)(getenv('MAX_LOGIN_ATTEMPTS') ?: 5));
define('LOCKOUT_TIME', (int)(getenv('LOCKOUT_TIME') ?: 1800));

// Image type constants (if not already defined by PHP)
if (!defined('IMAGETYPE_JPEG')) define('IMAGETYPE_JPEG', 2);
if (!defined('IMAGETYPE_PNG')) define('IMAGETYPE_PNG', 3);
if (!defined('IMAGETYPE_GIF')) define('IMAGETYPE_GIF', 1);
if (!defined('IMAGETYPE_WEBP')) define('IMAGETYPE_WEBP', 18);

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Self-healing schema guard.
// The application was written against a handful of columns/tables that were
// never migrated into the database, and a database restore would remove them
// again. This repairs the schema automatically (idempotent, cached, fail-safe).
require_once __DIR__ . '/schema.php';
odci_ensure_schema($pdo);

// Create upload directories if they don't exist
$directories = [
    UPLOAD_DIR,
    PROFILE_IMAGES_DIR,
    DOCUMENT_UPLOADS_DIR,
    TEMP_DIR
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Helper functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    // Password must be at least 8 characters, contain uppercase, lowercase, and number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Authentication Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['user_role']) && 
           (!isset($_SESSION['session_token']) || !isSessionExpired());
}

function isSessionExpired() {
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    return (time() - $_SESSION['last_activity']) > SESSION_LIFETIME;
}

function updateLastActivity() {
    $_SESSION['last_activity'] = time();
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.*, d.department_name, d.department_code, d.description as department_description
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ? AND u.is_approved = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            updateLastActivity();
            
            // Update last login timestamp
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return $user;
        }
    } catch (Exception $e) {
        error_log("Error fetching current user: " . $e->getMessage());
    }

    return null;
}

function logout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    
    // Destroy the session
    session_destroy();
}

// Check if user has specific role
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'] ?? '', $roles);
}

function uploadProfileImage($file, $userId) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error.'];
    }

    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    // Validate file type
    if (!in_array($extension, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Invalid file type. Only ' . implode(', ', ALLOWED_IMAGE_TYPES) . ' are allowed.'];
    }

    // Validate file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'File size too large. Maximum size is ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.'];
    }

    // Validate image dimensions and type
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'Invalid image file.'];
    }

    // Generate unique filename
    $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
    $uploadPath = PROFILE_IMAGES_DIR . $filename;
    $relativePath = 'uploads/profile_images/' . $filename;

    // Create thumbnail-sized version
    if (createThumbnail($file['tmp_name'], $uploadPath, 300, 300, $imageInfo[2])) {
        return [
            'success' => true, 
            'path' => $relativePath,
            'filename' => $filename
        ];
    }

    return ['success' => false, 'message' => 'Failed to process image.'];
}

function createThumbnail($sourcePath, $destinationPath, $maxWidth, $maxHeight, $imageType) {
    // Create image resource based on type
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if (!$source) {
        return false;
    }

    // Get original dimensions
    $originalWidth = imagesx($source);
    $originalHeight = imagesy($source);

    // Calculate new dimensions while maintaining aspect ratio
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = round($originalWidth * $ratio);
    $newHeight = round($originalHeight * $ratio);

    // Create new image
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
    }

    // Resize image
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    // Save thumbnail
    $result = false;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($thumbnail, $destinationPath, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($thumbnail, $destinationPath, 9);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($thumbnail, $destinationPath);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($thumbnail, $destinationPath, 90);
            break;
    }

    // Clean up memory
    imagedestroy($source);
    imagedestroy($thumbnail);

    return $result;
}

function deleteProfileImage($imagePath) {
    if (!empty($imagePath)) {
        $fullPath = __DIR__ . '/../' . $imagePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}

// Security Helper Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Notification Functions
function addNotification($pdo, $userId, $title, $message, $type = 'info', $actionUrl = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, action_url) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $title, $message, $type, $actionUrl]);
    } catch (Exception $e) {
        error_log("Error adding notification: " . $e->getMessage());
        return false;
    }
}

// Activity Logging
function logActivity($pdo, $userId, $action, $resourceType, $resourceId = null, $description = null, $metadata = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, metadata, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $description,
            $metadata ? json_encode($metadata) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

// Error Handling
set_error_handler(function($severity, $message, $file, $line) {
    error_log("PHP Error: $message in $file on line $line");
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        error_log("Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}");
    }
});
?>