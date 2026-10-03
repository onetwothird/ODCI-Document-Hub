<?php
require_once 'config.php';

// Session timeout configuration (6 hours = 21600 seconds)
define('SESSION_TIMEOUT', 21600); // 6 hours
define('SESSION_WARNING_TIME', 1800); // 30 minutes before timeout

// Function to check and update session timeout
function checkSessionTimeout() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $currentTime = time();
    
    // Check if session has timed out
    if (isset($_SESSION['last_activity']) && ($currentTime - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Session has expired
        sessionExpired();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = $currentTime;
    
    // Set initial login time if not set
    if (!isset($_SESSION['login_time'])) {
        $_SESSION['login_time'] = $currentTime;
    }
    
    return true;
}

// Function to handle session expiration
function sessionExpired() {
    global $pdo;
    
    if (isset($_SESSION['user_id'])) {
        // Log session expiration
        logActivity($pdo, $_SESSION['user_id'], 'session_expired', 'user', $_SESSION['user_id'], 'Session expired due to inactivity');
    }
    
    // Clear remember token cookie if exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        
        // Clear remember token from database
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET password_reset_token = NULL WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            } catch (Exception $e) {
                error_log("Error clearing remember token on session expiry: " . $e->getMessage());
            }
        }
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Redirect to login with session expired message
    header('Location: /ODCI/login.php?session_expired=1');
    exit();
}

// Enhanced function to check remember me token
function checkRememberToken($pdo) {
    if (isset($_COOKIE['remember_token']) && !isLoggedIn()) {
        $token = $_COOKIE['remember_token'];

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE password_reset_token = ? AND is_approved = 1");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Auto-login the user
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'] . ' ' . ($user['mi'] ? $user['mi'] . '. ' : '') . $user['surname'];
                $_SESSION['department_id'] = $user['department_id'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();

                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Log auto-login
                logActivity($pdo, $user['id'], 'auto_login', 'user', $user['id'], 'Auto-login via remember token');

                return true;
            } else {
                // Invalid token, clear cookie
                setcookie('remember_token', '', time() - 3600, '/', '', false, true);
            }
        } catch (Exception $e) {
            error_log("Remember token check error: " . $e->getMessage());
        }
    }

    return false;
}

// Function to get user's dashboard URL based on role
function getDashboardUrl($role) {
    switch ($role) {
        case 'super_admin':
            return '/ODCI/roles/superadmin/dashboard.php';
        case 'admin':
            return '/ODCI/roles/admin/dashboard.php';
        case 'user':
            return '/ODCI/roles/user/dashboard.php';
        default:
            return '/ODCI/login.php?error=invalid_role';
    }
}

// Enhanced function to require authentication
function requireAuth($redirectTo = '/ODCI/login.php') {
    global $pdo;

    // Check remember me token first
    checkRememberToken($pdo);
    
    // Check if user is logged in
    if (!isLoggedIn()) {
        header('Location: ' . $redirectTo);
        exit();
    }
    
    // Check session timeout
    if (!checkSessionTimeout()) {
        return false; // Session expired, user will be redirected by checkSessionTimeout()
    }

    // Check if user still exists and is approved
    $currentUser = getCurrentUser($pdo);
    if (!$currentUser || !$currentUser['is_approved']) {
        session_unset();
        session_destroy();
        header('Location: /ODCI/login.php?error=account_not_found');
        exit();
    }
    
    return $currentUser;
}

// Function to check if already logged in and redirect appropriately
function checkAlreadyLoggedIn() {
    global $pdo;
    
    // Check remember me token first
    checkRememberToken($pdo);
    
    if (isLoggedIn()) {
        // Check session timeout
        if (!checkSessionTimeout()) {
            return false; // Session expired, user will be redirected
        }
        
        // Verify user still exists and is approved
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || !$currentUser['is_approved']) {
            session_unset();
            session_destroy();
            return false;
        }
        
        // Redirect to appropriate dashboard
        $dashboardUrl = getDashboardUrl($currentUser['role']);
        header('Location: ' . $dashboardUrl);
        exit();
    }
    
    return false;
}

// Function to require specific role with enhanced checking
function requireRole($roles, $redirectTo = null) {
    $user = requireAuth();
    
    if (!$user) {
        return false; // Auth failed, user already redirected
    }

    if (is_string($roles)) {
        $roles = [$roles];
    }

    if (!in_array($user['role'], $roles)) {
        // Redirect to their own dashboard instead of generic redirect
        $dashboardUrl = getDashboardUrl($user['role']);
        header('Location: ' . $dashboardUrl . '?error=access_denied');
        exit();
    }
    
    return $user;
}

// Function to require admin access
function requireAdmin() {
    return requireRole(['admin', 'super_admin']);
}

// Function to require super admin access
function requireSuperAdmin() {
    return requireRole(['super_admin']);
}

// Function to prevent cross-role access
function preventCrossRoleAccess($allowedRoles) {
    global $pdo;
    
    $user = requireAuth();
    if (!$user) {
        return false;
    }
    
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    if (!in_array($user['role'], $allowedRoles)) {
        // Log unauthorized access attempt
        logActivity(
            $pdo, 
            $user['id'], 
            'unauthorized_access_attempt', 
            'system', 
            null, 
            'Attempted to access ' . $_SERVER['REQUEST_URI'] . ' without proper role permissions',
            ['requested_page' => $_SERVER['REQUEST_URI'], 'user_role' => $user['role'], 'allowed_roles' => $allowedRoles]
        );
        
        // Redirect to their appropriate dashboard
        $dashboardUrl = getDashboardUrl($user['role']);
        header('Location: ' . $dashboardUrl . '?error=access_denied');
        exit();
    }
    
    return $user;
}

// Function to get remaining session time
function getRemainingSessionTime() {
    if (!isLoggedIn() || !isset($_SESSION['last_activity'])) {
        return 0;
    }
    
    $elapsed = time() - $_SESSION['last_activity'];
    $remaining = SESSION_TIMEOUT - $elapsed;
    
    return max(0, $remaining);
}

// Function to check if session is about to expire
function isSessionNearExpiry() {
    $remaining = getRemainingSessionTime();
    return $remaining > 0 && $remaining <= SESSION_WARNING_TIME;
}

// Function to extend session (for AJAX calls)
function extendSession() {
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

// Enhanced function to check if current user can access resource
function canAccess($resourceType, $resourceId, $requiredPermission = 'read') {
    global $pdo;

    $user = requireAuth();
    if (!$user) {
        return false;
    }

    $userId = $user['id'];
    $userRole = $user['role'];

    // Super admin can access everything
    if ($userRole === 'super_admin') {
        return true;
    }
    
    try {
        switch ($resourceType) {
            case 'file':
                // Check if user owns the file or has folder permissions
                $stmt = $pdo->prepare("
                    SELECT f.uploaded_by, fo.created_by, fo.department_id, fo.is_public
                    FROM files f 
                    JOIN folders fo ON f.folder_id = fo.id 
                    WHERE f.id = ?
                ");
                $stmt->execute([$resourceId]);
                $resource = $stmt->fetch();

                if (!$resource) return false;

                // Owner has full access
                if ($resource['uploaded_by'] == $userId) return true;

                // Public files can be read by anyone
                if ($resource['is_public'] && $requiredPermission === 'read') return true;

                // Check folder permissions
                return canAccess('folder', $resource['folder_id'], $requiredPermission);

            case 'folder':
                // Check if user owns the folder or has explicit permissions
                $stmt = $pdo->prepare("SELECT created_by, department_id, is_public FROM folders WHERE id = ?");
                $stmt->execute([$resourceId]);
                $resource = $stmt->fetch();

                if (!$resource) return false;

                // Owner has full access
                if ($resource['created_by'] == $userId) return true;

                // Public folders can be read by anyone
                if ($resource['is_public'] && $requiredPermission === 'read') return true;

                // Check explicit folder permissions
                $stmt = $pdo->prepare("
                    SELECT permission_type 
                    FROM folder_permissions 
                    WHERE folder_id = ? AND user_id = ? AND is_active = 1 
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $stmt->execute([$resourceId, $userId]);
                $permission = $stmt->fetch();
                if ($permission) {
                    $permissionLevels = ['read' => 1, 'write' => 2, 'admin' => 3];
                    $required = $permissionLevels[$requiredPermission] ?? 1;
                    $granted = $permissionLevels[$permission['permission_type']] ?? 0;

                    return $granted >= $required;
                }

                // Check department access for admin users
                if ($userRole === 'admin' && $resource['department_id'] == $user['department_id']) {
                    return true;
                }

                break;

            case 'announcement':
                // Check if user can view announcement based on target departments/roles
                $stmt = $pdo->prepare("
                    SELECT target_departments, target_roles, created_by 
                    FROM announcements 
                    WHERE id = ? AND is_published = 1 AND is_deleted = 0
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $stmt->execute([$resourceId]);
                $announcement = $stmt->fetch();

                if (!$announcement) return false;

                // Creator has full access
                if ($announcement['created_by'] == $userId) return true;

                // Check if announcement targets user's department
                if ($announcement['target_departments']) {
                    $targetDepts = json_decode($announcement['target_departments'], true);
                    if (in_array($user['department_id'], $targetDepts)) return true;
                }

                // Check if announcement targets user's role
                if ($announcement['target_roles']) {
                    $targetRoles = json_decode($announcement['target_roles'], true);
                    if (in_array($userRole, $targetRoles)) return true;
                }

                // General announcements are visible to all
                return empty($announcement['target_departments']) && empty($announcement['target_roles']);
        }
    } catch (Exception $e) {
        error_log("Access check error: " . $e->getMessage());
        return false;
    }

    return false;
}

// Function to get user's accessible folders with session validation
function getUserFolders($pdo, $userId, $includePublic = true) {
    // Ensure user is authenticated
    if (!requireAuth()) {
        return [];
    }
    
    try {
        $query = "
            SELECT DISTINCT f.* 
            FROM folders f
            LEFT JOIN folder_permissions fp ON f.id = fp.folder_id AND fp.user_id = ? AND fp.is_active = 1
            WHERE f.is_deleted = 0 AND (
                f.created_by = ? OR
                (f.is_public = 1 AND ?) OR
                fp.id IS NOT NULL
            )
            ORDER BY f.folder_name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $userId, $includePublic ? 1 : 0]);

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get user folders error: " . $e->getMessage());
        return [];
    }
}

// JavaScript function to include in pages for session management
function getSessionManagementScript() {
    $remainingTime = getRemainingSessionTime();
    $warningTime = SESSION_WARNING_TIME * 1000; // Convert to milliseconds
    
    return "
    <script>
    // Session Management
    let sessionTimeout = {$remainingTime};
    let warningShown = false;
    
    // Update session timeout every minute
    setInterval(function() {
        sessionTimeout -= 60;
        
        // Show warning when 30 minutes left
        if (sessionTimeout <= " . SESSION_WARNING_TIME . " && sessionTimeout > 0 && !warningShown) {
            warningShown = true;
            if (confirm('Your session will expire in 30 minutes. Would you like to extend it?')) {
                extendSession();
            }
        }
        
        // Session expired
        if (sessionTimeout <= 0) {
            alert('Your session has expired. You will be redirected to the login page.');
            window.location.href = '/ODCI/login.php?session_expired=1';
        }
    }, 60000); // Check every minute
    
    // Extend session via AJAX
    function extendSession() {
        fetch('/ODCI/extend_session.php', {
            method: 'POST',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                sessionTimeout = " . SESSION_TIMEOUT . ";
                warningShown = false;
                console.log('Session extended');
            }
        })
        .catch(error => {
            console.error('Error extending session:', error);
        });
    }
    
    // Extend session on user activity
    let activityTimer;
    document.addEventListener('mousemove', function() {
        clearTimeout(activityTimer);
        activityTimer = setTimeout(function() {
            if (sessionTimeout > 0) {
                extendSession();
            }
        }, 300000); // Extend after 5 minutes of activity
    });
    </script>";
}
?>