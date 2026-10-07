<?php
require_once '../../includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentUser = getCurrentUser($pdo);

if (!$currentUser) {
    header('Location: logout.php');
    exit();
}

// Ensure all expected fields exist with default values
$currentUser = array_merge([
    'name' => '',
    'mi' => '',
    'surname' => '',
    'email' => '',
    'employee_id' => '',
    'position' => '',
    'phone' => '',
    'address' => '',
    'date_of_birth' => null,
    'hire_date' => null,
    'profile_image' => null,
    'department_id' => null,
    'department_name' => ''
], $currentUser);

// Verify user is super admin
if ($currentUser['role'] !== 'super_admin') {
    header('Location: dashboard.php?error=access_denied');
    exit();
}

if (!$currentUser['is_approved']) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_not_approved');
    exit();
}

// Aliases used by settings.php markup / inline script
$user     = $currentUser;
$userName = $currentUser['name'];

// Live statistics for the Statistics tab (real tables only - no placeholder data)
$stats = [
    'total_users'      => 0,
    'approved_users'   => 0,
    'pending_users'    => 0,
    'total_departments'=> 0,
    'active_departments'=> 0,
    'total_files'      => 0,
    'file_downloads'   => 0,
    'total_announcements' => 0,
    'published_announcements' => 0,
    'total_requests'   => 0,
    'pending_requests' => 0,
    'completed_requests' => 0,
    'super_admins'     => 0,
    'admins'           => 0,
];

try {
    $row = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(is_approved = 1), 0) AS approved,
                               COALESCE(SUM(is_approved = 0), 0) AS pending
                        FROM users")->fetch(PDO::FETCH_ASSOC);
    $stats['total_users']    = (int) $row['total'];
    $stats['approved_users'] = (int) $row['approved'];
    $stats['pending_users']  = (int) $row['pending'];

    $row = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(is_active = 1), 0) AS active
                        FROM departments")->fetch(PDO::FETCH_ASSOC);
    $stats['total_departments']  = (int) $row['total'];
    $stats['active_departments'] = (int) $row['active'];

    $row = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(download_count), 0) AS downloads
                        FROM files WHERE is_deleted = 0")->fetch(PDO::FETCH_ASSOC);
    $stats['total_files']    = (int) $row['total'];
    $stats['file_downloads'] = (int) $row['downloads'];

    $row = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(is_published = 1), 0) AS published
                        FROM announcements WHERE is_deleted = 0")->fetch(PDO::FETCH_ASSOC);
    $stats['total_announcements']     = (int) $row['total'];
    $stats['published_announcements'] = (int) $row['published'];

    $row = $pdo->query("SELECT COUNT(*) AS total,
                               COALESCE(SUM(status = 'pending'), 0) AS pending,
                               COALESCE(SUM(status = 'completed'), 0) AS completed
                        FROM document_requests WHERE is_deleted = 0")->fetch(PDO::FETCH_ASSOC);
    $stats['total_requests']     = (int) $row['total'];
    $stats['pending_requests']   = (int) $row['pending'];
    $stats['completed_requests'] = (int) $row['completed'];

    $row = $pdo->query("SELECT COALESCE(SUM(role = 'super_admin'), 0) AS super_admins,
                               COALESCE(SUM(role = 'admin'), 0) AS admins
                        FROM users")->fetch(PDO::FETCH_ASSOC);
    $stats['super_admins'] = (int) $row['super_admins'];
    $stats['admins']       = (int) $row['admins'];
} catch (Exception $e) {
    error_log('Settings statistics query failed: ' . $e->getMessage());
}

$success_message = '';
$error_message   = '';
$cacheClearedAt  = null;

$departmentImage = null;
$departmentCode  = null;

// Default profile image path
$defaultProfileImage = '../../img/default-avatar.png';

// Profile image handling
$profileImage = $defaultProfileImage;

if (!empty($currentUser['profile_image'])) {
    $dbImagePath = $currentUser['profile_image'];
    $webImagePath = '../../' . ltrim($dbImagePath, '/');
    $fileSystemPath = '../../' . ltrim($dbImagePath, '/');
    
    if (file_exists($fileSystemPath)) {
        $profileImage = $webImagePath . '?v=' . filemtime($fileSystemPath);
    } else {
        error_log("Profile image not found at: " . $fileSystemPath);
    }
}

// Get department information
if (!empty($currentUser['department_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT department_code, department_name FROM departments WHERE id = ?");
        $stmt->execute([$currentUser['department_id']]);
        $department = $stmt->fetch();

        if ($department) {
            $departmentCode = $department['department_code'];
            $departmentImage = "../../img/{$departmentCode}.jpg";
            $currentUser['department_name'] = $department['department_name'] ?? null;
        }
    } catch (Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
} elseif (isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.department_code, d.department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        $userDept = $stmt->fetch();

        if ($userDept && $userDept['department_id']) {
            $currentUser['department_id']   = $userDept['department_id'];
            $currentUser['department_name'] = $userDept['department_name'];
            $departmentCode                 = $userDept['department_code'];
            $departmentImage                 = "../../img/{$departmentCode}.jpg";
        }
    } catch (Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

// Function to delete old profile image
function deleteOldProfileImage($oldImagePath) {
    if (!empty($oldImagePath)) {
        $fullPath = '../../' . ltrim($oldImagePath, '/');
        
        if (file_exists($fullPath) && $fullPath !== '../../img/default-avatar.png') {
            if (unlink($fullPath)) {
                return "Old image deleted: " . $fullPath;
            } else {
                return "Failed to delete old image: " . $fullPath;
            }
        }
    }
    return "No old image to delete";
}

// Function to handle profile image upload
function handleProfileImageUpload($userId, $oldImagePath = null) {
    if (!isset($_FILES['profile_picture'])) {
        return null;
    }
    
    $file = $_FILES['profile_picture'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_NO_FILE:
                return null;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('File too large. Maximum size is 5MB.');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('File upload was interrupted.');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception('Missing temporary folder.');
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception('Failed to write file to disk.');
            case UPLOAD_ERR_EXTENSION:
                throw new Exception('File upload blocked by server extension.');
            default:
                throw new Exception('Unknown upload error occurred.');
        }
    }

    // Enhanced file validation with WEBP support and 5MB limit
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Enhanced MIME type validation using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Validate file type using both detected and reported MIME types
    if (!in_array($detectedMimeType, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
    }

    // Validate file extension
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Invalid file extension. Only jpg, jpeg, png, gif, and webp are allowed.');
    }

    // Validate file size (5MB)
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = '../../uploads/profile_images/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory.');
        }
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory is not writable.');
    }

    // Delete old profile image before uploading new one
    if ($oldImagePath) {
        deleteOldProfileImage($oldImagePath);
    }

    // Generate unique filename with timestamp
    $fileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $fileName;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Verify file was actually moved
        if (file_exists($uploadPath)) {
            // Return relative path for database storage
            $relativePath = 'uploads/profile_images/' . $fileName;
            return $relativePath;
        } else {
            throw new Exception('File was moved but cannot be found.');
        }
    } else {
        throw new Exception('Failed to upload file.');
    }
}

// Get system statistics for super admin dashboard
function getSystemStats($pdo) {
    $stats = [];
    
    // User statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total_users, 
                        SUM(is_approved = 1) as approved_users,
                        SUM(is_approved = 0) as pending_users,
                        SUM(role = 'admin') as admin_users,
                        SUM(role = 'super_admin') as super_admin_users
                        FROM users");
    $stats['users'] = $stmt->fetch();
    
    // Department statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total_departments, 
                        SUM(is_active = 1) as active_departments
                        FROM departments");
    $stats['departments'] = $stmt->fetch();
    
    // File statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total_files, 
                        SUM(is_deleted = 0) as active_files,
                        SUM(download_count) as total_downloads
                        FROM files");
    $stats['files'] = $stmt->fetch();
    
    // Announcement statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total_announcements, 
                        SUM(is_published = 1) as published_announcements
                        FROM announcements");
    $stats['announcements'] = $stmt->fetch();
    
    // Document request statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total_requests,
                        SUM(status = 'pending') as pending_requests,
                        SUM(status = 'in_progress') as in_progress_requests,
                        SUM(status = 'completed') as completed_requests
                        FROM document_requests");
    $stats['requests'] = $stmt->fetch();
    
    return $stats;
}

$systemStats = getSystemStats($pdo);

// Get recent activities
function getRecentActivities($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT al.*, u.username, u.name, u.surname 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

$recentActivities = getRecentActivities($pdo);

// Get system settings
function getSystemSettings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type, description FROM system_settings");
    $settings = $stmt->fetchAll();
    
    $result = [];
    foreach ($settings as $setting) {
        $result[$setting['setting_key']] = [
            'value' => $setting['setting_value'],
            'type' => $setting['setting_type'],
            'description' => $setting['description']
        ];
    }
    return $result;
}

$systemSettings = getSystemSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        try {
            $profileImagePath = null;
            
            // Handle profile image upload if provided
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                $oldImagePath = isset($currentUser['profile_image']) ? $currentUser['profile_image'] : null;
                $profileImagePath = handleProfileImageUpload($currentUser['id'], $oldImagePath);
            }
            
            // Sanitize and validate input data
            $name = trim($_POST['name'] ?? $currentUser['name']);
            $mi = trim($_POST['mi'] ?? $currentUser['mi']);
            $surname = trim($_POST['surname'] ?? $currentUser['surname']);
            $employee_id = trim($_POST['employee_id'] ?? $currentUser['employee_id']);
            $position = trim($_POST['position'] ?? $currentUser['position']);
            $phone = trim($_POST['phone'] ?? $currentUser['phone']);
            $address = trim($_POST['address'] ?? $currentUser['address']);
            $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : $currentUser['date_of_birth'];
            
            // Validate required fields
            if (empty($name) || empty($surname)) {
                throw new Exception('First name and last name are required.');
            }
            
            // Validate phone number format (optional)
            if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
                throw new Exception('Please enter a valid phone number.');
            }
            
            // Validate date of birth (optional)
            if (!empty($date_of_birth)) {
                $birthDate = new DateTime($date_of_birth);
                $today = new DateTime();
                $age = $today->diff($birthDate)->y;
                if ($age > 120 || $age < 16) {
                    throw new Exception('Please enter a valid date of birth.');
                }
            }
            
            // Prepare SQL based on whether image was uploaded
            if ($profileImagePath) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, mi = ?, surname = ?, employee_id = ?, position = ?, phone = ?, address = ?, date_of_birth = ?, profile_image = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $result = $stmt->execute([
                    $name, $mi, $surname, $employee_id, $position, $phone, $address, $date_of_birth, $profileImagePath, $currentUser['id']
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, mi = ?, surname = ?, employee_id = ?, position = ?, phone = ?, address = ?, date_of_birth = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $result = $stmt->execute([
                    $name, $mi, $surname, $employee_id, $position, $phone, $address, $date_of_birth, $currentUser['id']
                ]);
            }
            
            $success_message = 'Profile updated successfully!';
            
            // Refresh current user data from database
            $currentUser = getCurrentUser($pdo);
            
            // Update profile image for display with fresh data
            if (!empty($currentUser['profile_image'])) {
                $dbImagePath = $currentUser['profile_image'];
                $webImagePath = '../../' . ltrim($dbImagePath, '/');
                $fileSystemPath = '../../' . ltrim($dbImagePath, '/');
                
                if (file_exists($fileSystemPath)) {
                    $profileImage = $webImagePath . '?v=' . filemtime($fileSystemPath);
                }
            } else {
                $profileImage = $defaultProfileImage;
            }
        } catch (Exception $e) {
            $error_message = 'Error updating profile: ' . $e->getMessage();
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate password requirements
        if (strlen($newPassword) < 8) {
            $error_message = 'New password must be at least 8 characters long!';
        } elseif ($newPassword !== $confirmPassword) {
            $error_message = 'New passwords do not match!';
        } elseif (!password_verify($currentPassword, $currentUser['password'])) {
            $error_message = 'Current password is incorrect!';
        } else {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$hashedPassword, $currentUser['id']]);
                $success_message = 'Password changed successfully!';
                
                // Log password change for security
                error_log("Password changed for user ID: " . $currentUser['id'] . " at " . date('Y-m-d H:i:s'));
            } catch (Exception $e) {
                $error_message = 'Error changing password: ' . $e->getMessage();
                error_log("Password change error for user ID " . $currentUser['id'] . ": " . $e->getMessage());
            }
        }
    }

    if (isset($_POST['remove_profile_image'])) {
        try {
            // Delete the current profile image file
            $oldImagePath = isset($currentUser['profile_image']) ? $currentUser['profile_image'] : null;
            deleteOldProfileImage($oldImagePath);
            
            // Update database to remove profile image reference
            $stmt = $pdo->prepare("UPDATE users SET profile_image = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            
            $success_message = 'Profile image removed successfully!';
            $currentUser = getCurrentUser($pdo);
            $profileImage = $defaultProfileImage;
        } catch (Exception $e) {
            $error_message = 'Error removing profile image: ' . $e->getMessage();
        }
    }
    
    // Update system settings
    if (isset($_POST['update_system_settings'])) {
        try {
            $updatedSettings = $_POST['system_settings'] ?? [];
            
            foreach ($updatedSettings as $key => $value) {
                // Validate setting exists
                if (isset($systemSettings[$key])) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP(), updated_by = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $currentUser['id'], $key]);
                }
            }
            
            $success_message = 'System settings updated successfully!';
            // Refresh system settings
            $systemSettings = getSystemSettings($pdo);
        } catch (Exception $e) {
            $error_message = 'Error updating system settings: ' . $e->getMessage();
        }
    }
    
    // Clear system cache
    if (isset($_POST['clear_cache'])) {
        try {
            // Clear opcache if enabled
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // Clear specific cache directories if they exist
            $cacheDirs = ['../../cache/', '../../uploads/cache/'];
            foreach ($cacheDirs as $dir) {
                if (is_dir($dir)) {
                    // This is a simplified example - in production, you'd want a more robust cache clearing mechanism
                    array_map('unlink', glob("$dir*.cache"));
                }
            }
            
            $success_message = 'System cache cleared successfully!';
            $cacheClearedAt  = date('F j, Y, g:i A');
            
            // Log cache clearing
            error_log("System cache cleared by Super Admin: " . $currentUser['id'] . " at " . date('Y-m-d H:i:s'));
        } catch (Exception $e) {
            $error_message = 'Error clearing cache: ' . $e->getMessage();
        }
    }
}

// Build full name for display
$fullName = trim($currentUser['name'] . ' ' . (!empty($currentUser['mi']) ? $currentUser['mi'] . '. ' : '') . $currentUser['surname']);
?>