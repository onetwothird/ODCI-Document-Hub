
<?php
session_start();
require '../../includes/config.php';
require '../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Default profile image path
$defaultProfileImage = 'cvsu-logo.png';

// Get current user data with additional stats
$user_query = "SELECT u.*, d.department_name, d.department_code, d.head_of_department,
               creator.name as created_by_name, creator.surname as created_by_surname,
               approver.name as approved_by_name, approver.surname as approved_by_surname
               FROM users u 
               LEFT JOIN departments d ON u.department_id = d.id 
               LEFT JOIN users creator ON u.created_by = creator.id
               LEFT JOIN users approver ON u.approved_by = approver.id
               WHERE u.id = ?";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: login.php");
    exit();
}

// Process profile image path
if (!empty($user['profile_image'])) {
    $profileImagePath = '../../' . ltrim($user['profile_image'], '/');
    // Check if file actually exists
    if (!file_exists(__DIR__ . '/../../' . $user['profile_image'])) {
        $profileImagePath = '../../' . $defaultProfileImage;
    }
} else {
    $profileImagePath = '../../' . $defaultProfileImage;
}

// Get departments for dropdown
$dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
$dept_stmt = $pdo->prepare($dept_query);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $name = trim($_POST['name'] ?? '');
            $mi = trim($_POST['mi'] ?? '');
            $surname = trim($_POST['surname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $employee_id = trim($_POST['employee_id'] ?? '');
            $department_id = $_POST['department_id'] ?? null;
            $date_of_birth = $_POST['date_of_birth'] ?? null;
            $hire_date = $_POST['hire_date'] ?? null;
            
            // Validation
            if (empty($name) || empty($surname) || empty($email)) {
                $error_message = "Name, surname, and email are required fields.";
            } else {
                // Check if email is already taken by another user
                $email_check = "SELECT id FROM users WHERE email = ? AND id != ?";
                $email_stmt = $pdo->prepare($email_check);
                $email_stmt->execute([$email, $user_id]);
                
                // Check if employee_id is already taken by another user
                $emp_id_error = false;
                if (!empty($employee_id)) {
                    $emp_check = "SELECT id FROM users WHERE employee_id = ? AND id != ?";
                    $emp_stmt = $pdo->prepare($emp_check);
                    $emp_stmt->execute([$employee_id, $user_id]);
                    if ($emp_stmt->fetch()) {
                        $emp_id_error = true;
                    }
                }
                
                if ($email_stmt->fetch()) {
                    $error_message = "Email address is already in use by another user.";
                } elseif ($emp_id_error) {
                    $error_message = "Employee ID is already in use by another user.";
                } else {
                    // Update profile
                    $update_query = "UPDATE users SET 
                                    name = ?, mi = ?, surname = ?, email = ?, phone = ?, 
                                    address = ?, position = ?, employee_id = ?, department_id = ?, 
                                    date_of_birth = ?, hire_date = ?, updated_at = NOW()
                                    WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_query);
                    
                    if ($update_stmt->execute([
                        $name, $mi, $surname, $email, $phone, $address, 
                        $position, $employee_id, $department_id, 
                        $date_of_birth ?: null, $hire_date ?: null, $user_id
                    ])) {
                        // Log activity
                        $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, description, ip_address, user_agent) 
                                     VALUES (?, 'profile_update', 'user', ?, ?, ?)";
                        $log_stmt = $pdo->prepare($log_query);
                        $log_stmt->execute([
                            $user_id, 
                            "Updated profile information",
                            $_SERVER['REMOTE_ADDR'] ?? '',
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        
                        $success_message = "Profile updated successfully!";
                        
                        // Refresh user data
                        $user_stmt->execute([$user_id]);
                        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Update profile image path after refresh
                        if (!empty($user['profile_image'])) {
                            $profileImagePath = '../../' . ltrim($user['profile_image'], '/');
                            if (!file_exists(__DIR__ . '/../../' . $user['profile_image'])) {
                                $profileImagePath = '../../' . $defaultProfileImage;
                            }
                        } else {
                            $profileImagePath = '../../' . $defaultProfileImage;
                        }
                    } else {
                        $error_message = "Failed to update profile. Please try again.";
                    }
                }
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = "All password fields are required.";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match.";
            } elseif (strlen($new_password) < 8) {
                $error_message = "New password must be at least 8 characters long.";
            } elseif (!password_verify($current_password, $user['password'])) {
                $error_message = "Current password is incorrect.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
                $password_stmt = $pdo->prepare($password_query);
                
                if ($password_stmt->execute([$hashed_password, $user_id])) {
                    // Log activity
                    $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, description, ip_address, user_agent) 
                                 VALUES (?, 'password_change', 'user', ?, ?, ?)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->execute([
                        $user_id, 
                        "Password changed successfully",
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Failed to change password. Please try again.";
                }
            }
            break;
            
        case 'upload_image':
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_image'];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    $error_message = "Only JPEG, PNG, and GIF images are allowed.";
                } elseif ($file['size'] > $max_size) {
                    $error_message = "Image size must be less than 5MB.";
                } else {
                    // Create upload directory if it doesn't exist
                    $upload_dir = __DIR__ . '/../../uploads/profiles/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    $db_path = 'uploads/profiles/' . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Delete old profile image if exists
                        if ($user['profile_image'] && file_exists(__DIR__ . '/../../' . $user['profile_image'])) {
                            unlink(__DIR__ . '/../../' . $user['profile_image']);
                        }
                        
                        // Update database
                        $image_query = "UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?";
                        $image_stmt = $pdo->prepare($image_query);
                        
                        if ($image_stmt->execute([$db_path, $user_id])) {
                            // Log activity
                            $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, description, ip_address, user_agent) 
                                         VALUES (?, 'profile_image_update', 'user', ?, ?, ?)";
                            $log_stmt = $pdo->prepare($log_query);
                            $log_stmt->execute([
                                $user_id, 
                                "Profile image updated",
                                $_SERVER['REMOTE_ADDR'] ?? '',
                                $_SERVER['HTTP_USER_AGENT'] ?? ''
                            ]);
                            
                            $success_message = "Profile image updated successfully!";
                            $user['profile_image'] = $db_path;
                            $profileImagePath = '../../' . $db_path;
                        } else {
                            unlink($upload_path);
                            $error_message = "Failed to save image to database.";
                        }
                    } else {
                        $error_message = "Failed to upload image. Please try again.";
                    }
                }
            } else {
                $error_message = "Please select an image to upload.";
            }
            break;
            
        case 'remove_image':
            if ($user['profile_image']) {
                // Delete file
                if (file_exists(__DIR__ . '/../../' . $user['profile_image'])) {
                    unlink(__DIR__ . '/../../' . $user['profile_image']);
                }
                
                // Update database
                $remove_query = "UPDATE users SET profile_image = NULL, updated_at = NOW() WHERE id = ?";
                $remove_stmt = $pdo->prepare($remove_query);
                
                if ($remove_stmt->execute([$user_id])) {
                    $success_message = "Profile image removed successfully!";
                    $user['profile_image'] = null;
                    $profileImagePath = '../../' . $defaultProfileImage;
                } else {
                    $error_message = "Failed to remove profile image.";
                }
            }
            break;
            
        case 'clear_login_attempts':
            $clear_query = "UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE id = ?";
            $clear_stmt = $pdo->prepare($clear_query);
            if ($clear_stmt->execute([$user_id])) {
                $success_message = "Login attempts cleared successfully!";
                $user['failed_login_attempts'] = 0;
                $user['account_locked_until'] = null;
            } else {
                $error_message = "Failed to clear login attempts.";
            }
            break;
    }
}

// Get recent activity for this user with more details
$activity_query = "SELECT al.*, u.name, u.surname 
                   FROM activity_logs al
                   LEFT JOIN users u ON al.user_id = u.id
                   WHERE al.user_id = ? 
                   ORDER BY al.created_at DESC 
                   LIMIT 15";
$activity_stmt = $pdo->prepare($activity_query);
$activity_stmt->execute([$user_id]);
$recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get comprehensive account statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM files WHERE uploaded_by = ? AND is_deleted = 0) as total_files,
    (SELECT COUNT(*) FROM activity_logs WHERE user_id = ?) as total_activities,
    (SELECT SUM(file_size) FROM files WHERE uploaded_by = ? AND is_deleted = 0) as total_storage,
    (SELECT COUNT(*) FROM users WHERE created_by = ?) as users_created,
    (SELECT COUNT(*) FROM users WHERE approved_by = ?) as users_approved,
    (SELECT COUNT(DISTINCT DATE(created_at)) FROM activity_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_days_month";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get login statistics
$login_stats_query = "SELECT 
    COUNT(*) as total_logins,
    MAX(created_at) as last_login_log,
    MIN(created_at) as first_login_log
    FROM activity_logs 
    WHERE user_id = ? AND action = 'login'";
$login_stats_stmt = $pdo->prepare($login_stats_query);
$login_stats_stmt->execute([$user_id]);
$login_stats = $login_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get department colleagues
$colleagues_query = "SELECT id, name, surname, position, profile_image, last_login
                     FROM users 
                     WHERE department_id = ? AND id != ? AND is_approved = 1
                     ORDER BY name, surname
                     LIMIT 5";
$colleagues_stmt = $pdo->prepare($colleagues_query);
$colleagues_stmt->execute([$user['department_id'], $user_id]);
$colleagues = $colleagues_stmt->fetchAll(PDO::FETCH_ASSOC);

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function getInitials($name, $surname) {
    return strtoupper(substr($name, 0, 1) . substr($surname, 0, 1));
}

function getAccountAge($created_at) {
    $created = new DateTime($created_at);
    $now = new DateTime();
    $diff = $now->diff($created);
    
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
    } else {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
    }
}

function getSecurityScore($user) {
    $score = 0;
    if ($user['profile_image']) $score += 10;
    if ($user['phone']) $score += 10;
    if ($user['date_of_birth']) $score += 10;
    if ($user['hire_date']) $score += 10;
    if ($user['employee_id']) $score += 15;
    if ($user['department_id']) $score += 15;
    if ($user['email_verified']) $score += 20;
    if ($user['failed_login_attempts'] == 0) $score += 10;
    return $score;
}
?>