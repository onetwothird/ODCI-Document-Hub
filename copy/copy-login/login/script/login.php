<?php
    require_once 'includes/config.php';

    $error = '';
    $success = '';

    if (isLoggedIn()) {
        header('Location: dashboard.php');
        exit();
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
                    if ($user['account_locked_until'] && new DateTime() < new DateTime($user['account_locked_until'])) {
                        $error = 'Account is temporarily locked. Please try again later.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_name'] = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
                        $_SESSION['department_id'] = $user['department_id'];

                        if ($remember) {
                            $token = generateToken();
                            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);

                            $stmt = $pdo->prepare("UPDATE users SET password_reset_token = ? WHERE id = ?");
                            $stmt->execute([$token, $user['id']]);
                        }
                        logActivity($pdo, $user['id'], 'login', 'user', $user['id'], 'User logged in successfully');

                        if ($user['role'] === 'super_admin') {
                            header('Location: roles/superadmin/dashboard.php');
                        } elseif ($user['role'] === 'admin') {
                            header('Location: roles/admin/dashboard.php');
                        } elseif ($user['role'] === 'user') {
                            header('Location: roles/user/dashboard.php');
                        } else {
                            header('Location: login.php?error=invalid_role');
                        }
                        exit();
                    }
                } else {
                    if ($user) {
                        $newAttempts = $user['failed_login_attempts'] + 1;
                        $lockUntil = null;

                        if ($newAttempts >= 5) {
                            $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        }
                        
                        $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = ?, account_locked_until = ? WHERE id = ?");
                        $stmt->execute([$newAttempts, $lockUntil, $user['id']]);
                    }
                    
                    $error = 'Invalid username/email or password.';
                }
            } catch(Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    }

    if (isset($_GET['verify']) && isset($_GET['token'])) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email_verification_token = ?");
            $stmt->execute([$_GET['token']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, email_verification_token = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);
                $success = 'Email verified successfully! You can now log in.';
            } else {
                $error = 'Invalid verification token.';
            }
        } catch(Exception $e) {
            $error = 'Verification failed. Please try again.';
        }
    }
?>