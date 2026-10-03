<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

$error = '';
$success = '';

// Check if user is already logged in and redirect appropriately
checkAlreadyLoggedIn();

// Handle session expired message
if (isset($_GET['session_expired']) && $_GET['session_expired'] == '1') {
    $error = 'Your session has expired due to inactivity. Please log in again.';
}

// Handle logout message
if (isset($_GET['logged_out']) && $_GET['logged_out'] == '1') {
    $success = 'You have been successfully logged out.';
}

// Handle other messages
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'account_not_found':
            $error = 'Your account could not be found or has been deactivated. Please contact the administrator.';
            break;
        case 'access_denied':
            $error = 'Access denied. You do not have permission to access that resource.';
            break;
        case 'invalid_role':
            $error = 'Invalid user role detected. Please contact the administrator.';
            break;
        default:
            $error = htmlspecialchars($_GET['error']);
    }
}

if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_approved = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Check if account is locked
                if ($user['account_locked_until'] && new DateTime() < new DateTime($user['account_locked_until'])) {
                    $lockTime = new DateTime($user['account_locked_until']);
                    $now = new DateTime();
                    $remainingMinutes = $now->diff($lockTime)->i;
                    $error = "Account is temporarily locked for {$remainingMinutes} more minutes. Please try again later.";
                } else {
                    // Reset failed login attempts
                    $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);

                    // Set session variables with timestamps
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
                    $_SESSION['department_id'] = $user['department_id'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['last_activity'] = time();

                    // Handle remember me functionality
                    if ($remember) {
                        $token = generateToken();
                        // Set cookie for 30 days
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);

                        // Store token in database
                        $stmt = $pdo->prepare("UPDATE users SET password_reset_token = ? WHERE id = ?");
                        $stmt->execute([$token, $user['id']]);
                    }
                    
                    // Log successful login
                    logActivity($pdo, $user['id'], 'login', 'user', $user['id'], 'User logged in successfully', [
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                        'remember_me' => $remember ? 'yes' : 'no'
                    ]);

                    // Redirect based on role
                    $redirectUrl = getDashboardUrl($user['role']);
                    header('Location: ' . $redirectUrl);
                    exit();
                }
            } else {
                // Handle failed login attempt
                if ($user) {
                    $newAttempts = $user['failed_login_attempts'] + 1;
                    $lockUntil = null;

                    // Lock account after 5 failed attempts for 15 minutes
                    if ($newAttempts >= 5) {
                        $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        $error = 'Too many failed login attempts. Your account has been locked for 15 minutes.';
                    } else {
                        $remainingAttempts = 5 - $newAttempts;
                        $error = "Invalid username/email or password. {$remainingAttempts} attempts remaining.";
                    }

                    // Update failed attempts
                    $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = ?, account_locked_until = ? WHERE id = ?");
                    $stmt->execute([$newAttempts, $lockUntil, $user['id']]);
                    
                    // Log failed login attempt
                    logActivity($pdo, $user['id'], 'failed_login', 'user', $user['id'], 'Failed login attempt', [
                        'attempt_number' => $newAttempts,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'username_tried' => $username
                    ]);
                } else {
                    $error = 'Invalid username/email or password.';
                    
                    // Log failed login attempt for non-existent user
                    logActivity($pdo, null, 'failed_login_attempt', 'system', null, 'Failed login attempt for non-existent user', [
                        'username_tried' => $username,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'An error occurred during login. Please try again.';
        }
    }
}

// Handle email verification
if (isset($_GET['verify']) && isset($_GET['token'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email_verification_token = ?");
        $stmt->execute([$_GET['token']]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, email_verification_token = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Log email verification
            logActivity($pdo, $user['id'], 'email_verified', 'user', $user['id'], 'Email address verified successfully');
            
            $success = 'Email verified successfully! You can now log in once your account is approved.';
        } else {
            $error = 'Invalid or expired verification token.';
        }
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        $error = 'Email verification failed. Please try again or contact support.';
    }
}
?>