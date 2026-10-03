<?php
/**
 * TEMPORARY screenshot / QA helper. DELETE BEFORE FINISHING.
 *
 * Establishes a session for a given user id and redirects to a target page,
 * so a headless browser can be pointed straight at an authenticated screen.
 *
 *   /_shot.php?uid=28&to=roles/user/dashboard.php
 */
require_once __DIR__ . '/includes/config.php';

$uid   = (int)($_GET['uid'] ?? 0);
$to    = (string)($_GET['to'] ?? 'roles/user/dashboard.php');
$doNew = !empty($_GET['new']);

if ($uid > 0) {
    if ($doNew || empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_approved = 1 LIMIT 1");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            exit("no such user: $uid");
        }

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['user_role']    = $user['role'];
        $_SESSION['user_name']    = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
        $_SESSION['department_id'] = $user['department_id'];
        $_SESSION['login_time']   = time();
        $_SESSION['last_activity'] = time();
    }
}

// Only ever redirect within this project.
if (preg_match('~^/?[A-Za-z0-9_./-]+\.php$~', $to) !== 1) {
    $to = 'roles/user/dashboard.php';
}

header('Location: /ODCI/' . ltrim($to, '/'));
exit;