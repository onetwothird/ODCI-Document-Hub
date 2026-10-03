<?php
// TEMPORARY QA helper - creates a real PHP session for a given role so the
// headless-browser probe can load authenticated pages.
// Mirrors exactly what login/script/login_function.php writes.
// Delete before finishing.
require_once __DIR__ . '/includes/config.php';

$role = isset($_GET['role']) ? $_GET['role'] : 'user';
$uids = ['super_admin' => 1, 'admin' => 27, 'user' => 28];

if (!isset($uids[$role])) {
    header('Content-Type: text/plain');
    echo "usage: ?role=super_admin|admin|user\n";
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$uid = $uids[$role];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

$_SESSION['user_id']       = $u['id'];
$_SESSION['username']      = $u['username'];
$_SESSION['user_role']     = $u['role'];
$_SESSION['user_name']     = $u['name'] . ' ' . ($u['mi'] ? $u['mi'] . '. ' : '') . $u['surname'];
$_SESSION['department_id'] = $u['department_id'];
$_SESSION['login_time']    = time();
$_SESSION['last_activity'] = time();

header('Content-Type: text/plain');
echo 'SID=' . session_id() . "\n";
echo "role={$u['role']} uid={$u['id']}\n";
echo 'isLoggedIn=' . (isLoggedIn() ? 'yes' : 'no') . "\n";
$cur = getCurrentUser($pdo);
echo 'currentUser=' . ($cur ? $cur['username'] : 'NULL') . "\n";
