<?php
    require_once 'includes/config.php';

    if (isLoggedIn()) {
        logActivity($pdo, $_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');

        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);

            try {
                $stmt = $pdo->prepare("UPDATE users SET password_reset_token = NULL WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            } catch(Exception $e) {
                error_log("Error clearing remember token: " . $e->getMessage());
            }
        }
    }

    session_unset();
    session_destroy();

    header('Location: login.php?logged_out=1');
    exit();
?>