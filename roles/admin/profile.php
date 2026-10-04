
<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Default profile image path
$defaultProfileImage = 'uploads/profiles/default.png';

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - <?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/profile.css?v=<?= time() ?>">
    

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>
    
        <div class="profile-container" id="profile-container">
            <!-- Profile Header -->
            <div class="settings-header">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="profile-avatar">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($profileImagePath); ?>" 
                                    alt="Profile" class="avatar-image">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo getInitials($user['name'], $user['surname']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'admin' || $user['role'] === 'super_admin'): ?>
                                <div class="profile-badge" title="<?php echo ucfirst($user['role']); ?>">
                                    <i class="fas <?php echo $user['role'] === 'super_admin' ? 'fa-crown' : 'fa-shield-alt'; ?>"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($user['name'] . ' ' . ($user['mi'] ? $user['mi'] . ' ' : '') . $user['surname']); ?>
                                <?php if (!$user['email_verified']): ?>
                                    <span class="verification-badge unverified ms-2">
                                        <i class="fas fa-exclamation-triangle"></i> Unverified
                                    </span>
                                <?php else: ?>
                                    <span class="verification-badge verified ms-2">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php endif; ?>
                            </h2>
                            <p class="mb-2 fs-5"><?php echo htmlspecialchars($user['position'] ?? 'Administrator'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show alert-custom">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show alert-custom">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Security Alert -->
            <?php if ($user['failed_login_attempts'] > 0 || $user['account_locked_until']): ?>
                <div class="alert alert-warning alert-dismissible fade show alert-custom">
                    <i class="fas fa-shield-alt me-2"></i>
                    Security Notice: 
                    <?php if ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()): ?>
                        Your account was temporarily locked due to failed login attempts.
                    <?php else: ?>
                        <?php echo $user['failed_login_attempts']; ?> failed login attempt(s) recorded.
                    <?php endif; ?>
                    <form method="POST" class="d-inline ms-2">
                        <input type="hidden" name="action" value="clear_login_attempts">
                        <button type="submit" class="btn btn-sm btn-outline-warning">Clear Attempts</button>
                    </form>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="profile-cards">
                <!-- Sidebar Content -->
                <div class="profile-sidebar">
                    <!-- Profile Image -->
                    <div class="card profile-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-camera"></i>
                            <h5>Profile Image</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($profileImagePath); ?>" 
                                        alt="Profile" class="avatar-image mb-3" style="width: 150px; height: 150px;">
                                <?php else: ?>
                                    <div class="avatar-placeholder mb-3 mx-auto" style="width: 150px; height: 150px;">
                                        <?php echo getInitials($user['name'], $user['surname']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_image">
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="profile_image" accept="image/*">
                                </div>
                                <button type="submit" class="btn btn-gradient w-100 mb-2">
                                    <i class="fas fa-upload me-2"></i>Upload Image
                                </button>
                            </form>
                            
                            <?php if (!empty($user['profile_image'])): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="remove_image">
                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        <i class="fas fa-trash me-2"></i>Remove Image
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Main Profile Content -->
                <div class="profile-main">
                    <!-- Personal Information -->
                    <div class="card profile-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-user"></i>
                            <h5>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6 form-section">
                                        <h6 class="section-title">Basic Details</h6>
                                        <div class="mb-3">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" class="form-control" name="name" 
                                                value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Middle Initial</label>
                                            <input type="text" class="form-control" name="mi" maxlength="1"
                                                value="<?php echo htmlspecialchars($user['mi'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" name="surname" 
                                                value="<?php echo htmlspecialchars($user['surname']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" name="email" 
                                                value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 form-section">
                                        <h6 class="section-title">Professional Details</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Position</label>
                                            <input type="text" class="form-control" name="position" 
                                                value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Employee ID</label>
                                            <input type="text" class="form-control" name="employee_id" 
                                                value="<?php echo htmlspecialchars($user['employee_id'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Department</label>
                                            <select class="form-select" name="department_id">
                                                <option value="">Select Department</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo $dept['id']; ?>" 
                                                        <?php echo ($user['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" name="phone" 
                                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 form-section">
                                        <h6 class="section-title">Personal Details</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Date of Birth</label>
                                            <input type="date" class="form-control" name="date_of_birth" 
                                                value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Hire Date</label>
                                            <input type="date" class="form-control" name="hire_date" 
                                                value="<?php echo htmlspecialchars($user['hire_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 form-section">
                                        <h6 class="section-title">Address Information</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-gradient">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="card profile-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-lock"></i>
                            <h5>Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="security-score">
                                    <div class="score-circle" style="--score: <?php echo getSecurityScore($user); ?>%" data-score="<?php echo getSecurityScore($user); ?>"></div>
                                    <div>
                                        <h6 class="mb-1">Account Security Score</h6>
                                        <p class="mb-0 small">Complete your profile to improve security</p>
                                    </div>
                                </div>
                                
                                <h6 class="section-title">Change Password</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Current Password</label>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">New Password</label>
                                            <input type="password" class="form-control" name="new_password" required minlength="8">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Confirm Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-gradient">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle sidebar collapse
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const profileContainer = document.getElementById('profile-container');
            
            // Check initial state
            if (sidebar.classList.contains('collapsed')) {
                profileContainer.classList.add('sidebar-collapsed');
            }
            
            // Listen for sidebar toggle events
            document.addEventListener('sidebarToggled', function(e) {
                if (e.detail.collapsed) {
                    profileContainer.classList.add('sidebar-collapsed');
                } else {
                    profileContainer.classList.remove('sidebar-collapsed');
                }
            });
        });
    </script>
</body>
</html>
