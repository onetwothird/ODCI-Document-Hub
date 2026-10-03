<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Mailer.php';

$error = '';
$success = '';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Your session could not verify this request. Refresh the page and try again.';
    } elseif ($email === '' || !validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? AND is_approved = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $resetToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $resetToken);
                $resetExpiry = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

                $stmt = $pdo->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?');
                $stmt->execute([$tokenHash, $resetExpiry, $user['id']]);

                $resetUrl = rtrim(BASE_URL, '/') . '/login/reset_password.php?token=' . rawurlencode($resetToken);

                try {
                    sendPasswordResetEmail((string)$user['email'], $resetUrl);
                } catch (Throwable $mailError) {
                    $clearStmt = $pdo->prepare(
                        'UPDATE users SET password_reset_token = NULL, password_reset_expires = NULL WHERE id = ? AND password_reset_token = ?'
                    );
                    $clearStmt->execute([$user['id'], $tokenHash]);
                    throw $mailError;
                }

                logActivity($pdo, $user['id'], 'password_reset_request', 'user', $user['id'], 'Password reset email sent.');
            }

            $success = 'If an approved account uses that email address, password reset instructions have been sent.';
            $_POST = [];
        } catch (Throwable $e) {
            error_log('Password reset email request failed: ' . $e->getMessage());
            $error = 'We could not send password reset instructions right now. Please try again later.';
        }
    }
}
?>
