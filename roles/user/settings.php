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
    'department_name' => '',
    'role' => 'user',
    'email_verified' => 0,
    'failed_login_attempts' => 0,
    'account_locked_until' => null
], $currentUser);

if (!$currentUser['is_approved']) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_not_approved');
    exit();
}

$success_message = '';
$error_message   = '';


$defaultProfileImage = '../../img/cvsu-logo.png';

// Process profile image path - fixed logic to match navbar.html
if (!empty($currentUser['profile_image'])) {
    $dbImagePath = $currentUser['profile_image'];
    $webImagePath = '../../' . ltrim($dbImagePath, '/');
    $fileSystemPath = __DIR__ . '/../../' . ltrim($dbImagePath, '/');
    
    if (file_exists($fileSystemPath)) {
        $profileImagePath = $webImagePath . '?v=' . filemtime($fileSystemPath);
    } else {
        // Log error for debugging
        error_log("Profile image not found at: " . $fileSystemPath);
        $profileImagePath = $defaultProfileImage;
    }
} else {
    $profileImagePath = $defaultProfileImage;
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
            
            // Validation
            if (empty($name) || empty($surname) || empty($email)) {
                $error_message = "Name, surname, and email are required fields.";
            } else {
                // Check if email is already taken by another user
                $email_check = "SELECT id FROM users WHERE email = ? AND id != ?";
                $email_stmt = $pdo->prepare($email_check);
                $email_stmt->execute([$email, $currentUser['id']]);
                
                // Check if employee_id is already taken by another user
                $emp_id_error = false;
                if (!empty($employee_id)) {
                    $emp_check = "SELECT id FROM users WHERE employee_id = ? AND id != ?";
                    $emp_stmt = $pdo->prepare($emp_check);
                    $emp_stmt->execute([$employee_id, $currentUser['id']]);
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
                                    date_of_birth = ?, updated_at = NOW()
                                    WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_query);
                    
                    if ($update_stmt->execute([
                        $name, $mi, $surname, $email, $phone, $address, 
                        $position, $employee_id, $department_id, 
                        $date_of_birth ?: null, $currentUser['id']
                    ])) {
                        // Log activity
                        $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, description, ip_address, user_agent) 
                                     VALUES (?, 'profile_update', 'user', ?, ?, ?)";
                        $log_stmt = $pdo->prepare($log_query);
                        $log_stmt->execute([
                            $currentUser['id'], 
                            "Updated profile information",
                            $_SERVER['REMOTE_ADDR'] ?? '',
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        
                        $success_message = "Profile updated successfully!";
                        
                        // Refresh user data
                        $currentUser = getCurrentUser($pdo);
                        
                        // Update profile image path after refresh
                        if (!empty($currentUser['profile_image'])) {
                            $dbImagePath = $currentUser['profile_image'];
                            $webImagePath = '../../' . ltrim($dbImagePath, '/');
                            $fileSystemPath = __DIR__ . '/../../' . ltrim($dbImagePath, '/');
                            
                            if (file_exists($fileSystemPath)) {
                                $profileImagePath = $webImagePath . '?v=' . filemtime($fileSystemPath);
                            } else {
                                $profileImagePath = $defaultProfileImage;
                            }
                        } else {
                            $profileImagePath = $defaultProfileImage;
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
            } elseif (!password_verify($current_password, $currentUser['password'])) {
                $error_message = "Current password is incorrect.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
                $password_stmt = $pdo->prepare($password_query);
                
                if ($password_stmt->execute([$hashed_password, $currentUser['id']])) {
                    // Log activity
                    $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, description, ip_address, user_agent) 
                                 VALUES (?, 'password_change', 'user', ?, ?, ?)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->execute([
                        $currentUser['id'], 
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
                    $new_filename = 'profile_' . $currentUser['id'] . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    $db_path = 'uploads/profiles/' . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Delete old profile image if exists
                        if ($currentUser['profile_image'] && file_exists(__DIR__ . '/../../' . $currentUser['profile_image'])) {
                            unlink(__DIR__ . '/../../' . $currentUser['profile_image']);
                        }
                        
                        // Update database
                        $image_query = "UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?";
                        $image_stmt = $pdo->prepare($image_query);
                        
                        if ($image_stmt->execute([$db_path, $currentUser['id']])) {
                            // Log activity
                            $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, description, ip_address, user_agent) 
                                         VALUES (?, 'profile_image_update', 'user', ?, ?, ?)";
                            $log_stmt = $pdo->prepare($log_query);
                            $log_stmt->execute([
                                $currentUser['id'], 
                                "Profile image updated",
                                $_SERVER['REMOTE_ADDR'] ?? '',
                                $_SERVER['HTTP_USER_AGENT'] ?? ''
                            ]);
                            
                            $success_message = "Profile image updated successfully!";
                            $currentUser['profile_image'] = $db_path;
                            
                            // Update profile image path
                            $profileImagePath = '../../' . $db_path . '?v=' . time();
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
            if ($currentUser['profile_image']) {
                // Delete file
                if (file_exists(__DIR__ . '/../../' . $currentUser['profile_image'])) {
                    unlink(__DIR__ . '/../../' . $currentUser['profile_image']);
                }
                
                // Update database
                $remove_query = "UPDATE users SET profile_image = NULL, updated_at = NOW() WHERE id = ?";
                $remove_stmt = $pdo->prepare($remove_query);
                
                if ($remove_stmt->execute([$currentUser['id']])) {
                    $success_message = "Profile image removed successfully!";
                    $currentUser['profile_image'] = null;
                    $profileImagePath = $defaultProfileImage;
                } else {
                    $error_message = "Failed to remove profile image.";
                }
            }
            break;
            
        case 'clear_login_attempts':
            $clear_query = "UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE id = ?";
            $clear_stmt = $pdo->prepare($clear_query);
            if ($clear_stmt->execute([$currentUser['id']])) {
                $success_message = "Login attempts cleared successfully!";
                $currentUser['failed_login_attempts'] = 0;
                $currentUser['account_locked_until'] = null;
            } else {
                $error_message = "Failed to clear login attempts.";
            }
            break;
    }
}

// Helper functions
function getInitials($name, $surname) {
    return strtoupper(substr($name, 0, 1) . substr($surname, 0, 1));
}

function getSecurityScore($user) {
    $score = 0;
    if ($user['profile_image']) $score += 10;
    if ($user['phone']) $score += 10;
    if ($user['date_of_birth']) $score += 10;
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
    <title>User Settings - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
    <style>
        .settings-container {
            padding: 20px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 76px);
            background: transparent;
            max-width: 1400px;
            margin: 0 auto;
        }

        .settings-container.sidebar-collapsed {
            margin-left: 80px;
        }

        .settings-header {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            border-radius: 20px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .settings-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }

        .settings-header .row {
            align-items: center;
            text-align: center;
        }

        .profile-avatar {
            position: relative;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: center;
        }

        .avatar-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .profile-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border: 3px solid white;
        }

        .settings-info {
            text-align: center;
        }

        .settings-info h2 {
            margin-bottom: 0.5rem;
            font-weight: 700;
            font-size: 2.2rem;
        }

        .settings-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            opacity: 0.95;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            text-align: left;
        }

        .meta-icon {
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .settings-cards {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            max-width: 1400px;
            margin: 0 auto;
            gap: 2rem;
        }

        .settings-main {
            flex: 1;
            min-width: 0;
            max-width: calc(100% - 370px);
        }

        .settings-sidebar {
            flex: 0 0 350px;
            max-width: 350px;
            margin-top: 0;
        }

        @media (max-width: 1100px) {
            .settings-cards {
                flex-direction: column;
                align-items: stretch;
            }
            
            .settings-main {
                max-width: 100%;
            }
            
            .settings-sidebar {
                max-width: 100%;
                flex: none;
            }
        }

        .settings-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
        }

        .settings-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #f8f9ff 0%, #e3e6f0 100%);
            border-bottom: 1px solid #e3e6f0;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .card-header-custom h5 {
            margin: 0;
            color: #2d3436;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .card-header-custom i {
            background: #667eea;
            color: white;
            padding: 8px;
            border-radius: 10px;
            font-size: 1rem;
        }
        
        .form-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #667eea;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 50px;
            height: 3px;
            background: #764ba2;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .security-score {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .score-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: conic-gradient(#4caf50 var(--score), #e0e0e0 0);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .score-circle::before {
            content: attr(data-score) '%';
            position: absolute;
            background: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: #4caf50;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .verification-badge.verified {
            background: #d4edda;
            color: #155724;
        }
        
        .verification-badge.unverified {
            background: #f8d7da;
            color: #721c24;
        }
        
        .alert-custom {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .settings-container {
                margin-left: 0;
                padding: 15px;
            }
            
            .settings-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>

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
    
        <div class="settings-container" id="settings-container">
            <!-- Settings Header -->
            <div class="settings-header">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="profile-avatar">
                            <?php if ($profileImagePath !== $defaultProfileImage): ?>
                                <img src="<?php echo htmlspecialchars($profileImagePath); ?>" 
                                    alt="Profile" class="avatar-image">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo getInitials($currentUser['name'], $currentUser['surname']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin'): ?>
                                <div class="profile-badge" title="<?php echo ucfirst($currentUser['role']); ?>">
                                    <i class="fas <?php echo $currentUser['role'] === 'super_admin' ? 'fa-crown' : 'fa-shield-alt'; ?>"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="settings-info">
                            <h2><?php echo htmlspecialchars($currentUser['name'] . ' ' . ($currentUser['mi'] ? $currentUser['mi'] . ' ' : '') . $currentUser['surname']); ?>
                                <?php if (!$currentUser['email_verified']): ?>
                                    <span class="verification-badge unverified ms-2">
                                        <i class="fas fa-exclamation-triangle"></i> Unverified
                                    </span>
                                <?php else: ?>
                                    <span class="verification-badge verified ms-2">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php endif; ?>
                            </h2>
                            <p class="mb-2 fs-5"><?php echo htmlspecialchars($currentUser['position'] ?? 'User'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show alert-custom<?php echo $success_message === 'Profile image updated successfully!' ? ' profile-image-success' : ''; ?>" role="status" aria-live="polite">
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
            <?php if ($currentUser['failed_login_attempts'] > 0 || $currentUser['account_locked_until']): ?>
                <div class="alert alert-warning alert-dismissible fade show alert-custom">
                    <i class="fas fa-shield-alt me-2"></i>
                    Security Notice: 
                    <?php if ($currentUser['account_locked_until'] && strtotime($currentUser['account_locked_until']) > time()): ?>
                        Your account was temporarily locked due to failed login attempts.
                    <?php else: ?>
                        <?php echo $currentUser['failed_login_attempts']; ?> failed login attempt(s) recorded.
                    <?php endif; ?>
                    <form method="POST" class="d-inline ms-2">
                        <input type="hidden" name="action" value="clear_login_attempts">
                        <button type="submit" class="btn btn-sm btn-outline-warning">Clear Attempts</button>
                    </form>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="settings-cards">
                <!-- Sidebar Content -->
                <div class="settings-sidebar">
                    <!-- Profile Image -->
                    <div class="card settings-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-camera"></i>
                            <h5>Profile Image</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php if ($profileImagePath !== $defaultProfileImage): ?>
                                    <img src="<?php echo htmlspecialchars($profileImagePath); ?>" 
                                        alt="Profile" class="avatar-image mb-3" style="width: 150px; height: 150px;">
                                <?php else: ?>
                                    <div class="avatar-placeholder mb-3 mx-auto" style="width: 150px; height: 150px;">
                                        <?php echo getInitials($currentUser['name'], $currentUser['surname']); ?>
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
                            
                            <?php if ($profileImagePath !== $defaultProfileImage): ?>
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
                <!-- Main Settings Content -->
                <div class="settings-main">
                    <!-- Personal Information -->
                    <div class="card settings-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-user"></i>
                            <h5>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row g-4">
                                    <div class="col-md-6 form-section">
                                        <h6 class="section-title">Basic Details</h6>
                                        <div class="mb-3">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" class="form-control" name="name" 
                                                value="<?php echo htmlspecialchars($currentUser['name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Middle Initial</label>
                                            <input type="text" class="form-control" name="mi" maxlength="1"
                                                value="<?php echo htmlspecialchars($currentUser['mi'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" name="surname" 
                                                value="<?php echo htmlspecialchars($currentUser['surname']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" name="email" 
                                                value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 form-section">
                                        <h6 class="section-title">Professional Details</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Position</label>
                                            <input type="text" class="form-control" name="position" 
                                                value="<?php echo htmlspecialchars($currentUser['position'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Employee ID</label>
                                            <input type="text" class="form-control" name="employee_id" 
                                                value="<?php echo htmlspecialchars($currentUser['employee_id'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Department</label>
                                            <select class="form-select" name="department_id">
                                                <option value="">Select Department</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo $dept['id']; ?>" 
                                                        <?php echo ($currentUser['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" name="phone" 
                                                value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-4">
                                    <div class="col-md-6 form-section">
                                        <h6 class="section-title">Personal Details</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Date of Birth</label>
                                            <input type="date" class="form-control" name="date_of_birth" 
                                                value="<?php echo htmlspecialchars($currentUser['date_of_birth'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 form-section">
                                        <h6 class="section-title">Address Information</h6>
                                        <div class="mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($currentUser['address'] ?? ''); ?></textarea>
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
                    <div class="card settings-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-lock"></i>
                            <h5>Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="security-score">
                                    <div class="score-circle" style="--score: <?php echo getSecurityScore($currentUser); ?>%" data-score="<?php echo getSecurityScore($currentUser); ?>"></div>
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
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required minlength="8">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/components/navbar.js?v=<?= time() ?>"></script>
    <script>
        // Handle sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const settingsContainer = document.getElementById('settings-container');
            
            if (sidebarToggle && settingsContainer) {
                sidebarToggle.addEventListener('click', function() {
                    settingsContainer.classList.toggle('sidebar-collapsed');
                });
            }

            const profileImageSuccess = document.querySelector('.profile-image-success');
            if (profileImageSuccess) {
                window.setTimeout(function() {
                    profileImageSuccess.classList.remove('show');
                    window.setTimeout(function() {
                        profileImageSuccess.remove();
                    }, 350);
                }, 3500);
            }
        });
    </script>
</body>
</html>
