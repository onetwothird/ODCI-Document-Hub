<?php
    require_once 'includes/config.php';

    $userId = null;
    $wasLoggedIn = false;

    if (isLoggedIn()) {
        $userId = $_SESSION['user_id'];
        $wasLoggedIn = true;
        
        // Log logout activity before clearing session
        logActivity($pdo, $userId, 'logout', 'user', $userId, 'User logged out manually', [
            'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        // Clear remember token cookie and database
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);

            try {
                $stmt = $pdo->prepare("UPDATE users SET password_reset_token = NULL WHERE id = ?");
                $stmt->execute([$userId]);
            } catch (Exception $e) {
                error_log("Error clearing remember token during logout: " . $e->getMessage());
            }
        }
    }

    // Clear all session data
    session_unset();
    session_destroy();

    // Regenerate session ID to prevent session fixation
    session_start();
    session_regenerate_id(true);

    // Redirect with appropriate message
    if ($wasLoggedIn) {
        header('Location: login.php?logged_out=1');
    } else {
        header('Location: login.php');
    }
    exit();
?>