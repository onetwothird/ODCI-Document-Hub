<?php
// extend_session.php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isLoggedIn()) {
            // Extend the session
            $_SESSION['last_activity'] = time();
            
            echo json_encode([
                'success' => true,
                'message' => 'Session extended successfully',
                'remaining_time' => getRemainingSessionTime()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'User not logged in'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method'
        ]);
    }
} catch (Exception $e) {
    error_log("Session extension error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}
?>