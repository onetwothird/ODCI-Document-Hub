<?php
require_once '../includes/config.php';

$error = '';
$success = '';

if (isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');

    if (empty($email) || !validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, name, surname FROM users WHERE email = ? AND is_approved = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $resetToken = generateToken();
                $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                $stmt->execute([$resetToken, $resetExpiry, $user['id']]);

                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $resetToken;

                logActivity($pdo, $user['id'], 'password_reset_request', 'user', $user['id'], 'Password reset requested');

                $success = 'Password reset instructions have been sent to your email address. Please check your inbox and follow the instructions to reset your password.';
                $_POST = [];
            } else {
                $success = 'If an account with that email exists, password reset instructions have been sent.';
            }
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>