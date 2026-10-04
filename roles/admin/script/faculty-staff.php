<?php
// Enhanced script/faculty-staff.php with proper profile image handling
// This file sits two levels below the project root (roles/admin/script), so the
// bare relative path only resolved when PHP's working directory happened to be
// the page directory. It fataled on every direct/AJAX hit of this endpoint.
require_once __DIR__ . '/../../../includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current admin information with department details
$currentAdmin = getCurrentUser($pdo);

if (!$currentAdmin) {
    header('Location: logout.php');
    exit();
}

// Enhanced department validation with better error handling
$adminDepartmentId = $currentAdmin['department_id'] ?? null;

if (!$adminDepartmentId) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.department_name, d.department_code 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$currentAdmin['id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            $adminDepartmentId = $result['department_id'];
            $currentAdmin['department_name'] = $result['department_name'];
            $currentAdmin['department_code'] = $result['department_code'];
        }
    } catch (Exception $e) {
        error_log("Error fetching admin department: " . $e->getMessage());
    }
}

// Enhanced error handling for department assignment
if (!$adminDepartmentId) {
    $errorMessage = "Your account is not assigned to any department. Please contact the system administrator.";
    
    // Log this critical error
    error_log("Admin user {$currentAdmin['id']} ({$currentAdmin['username']}) has no department assignment");
    
    die("
        <div style='padding: 40px; text-align: center; font-family: Arial, sans-serif;'>
            <h2 style='color: #dc3545;'>Department Assignment Required</h2>
            <p style='color: #666; font-size: 16px;'>$errorMessage</p>
            <a href='dashboard.php' style='display: inline-block; margin-top: 20px; padding: 12px 24px; 
               background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>
               Return to Dashboard
            </a>
        </div>
    ");
}

// Get department details if not already fetched
if (!isset($currentAdmin['department_name'])) {
    try {
        $stmt = $pdo->prepare("SELECT department_name, department_code, description FROM departments WHERE id = ?");
        $stmt->execute([$adminDepartmentId]);
        $department = $stmt->fetch();
        
        if ($department) {
            $currentAdmin['department_name'] = $department['department_name'];
            $currentAdmin['department_code'] = $department['department_code'];
            $currentAdmin['department_description'] = $department['description'];
        } else {
            error_log("Department ID $adminDepartmentId referenced by user {$currentAdmin['id']} does not exist");
            $currentAdmin['department_name'] = 'Unknown Department';
            $currentAdmin['department_code'] = 'UNK';
        }
    } catch (Exception $e) {
        error_log("Error fetching department details: " . $e->getMessage());
        $currentAdmin['department_name'] = 'Department';
        $currentAdmin['department_code'] = 'DEPT';
    }
}

// Enhanced faculty staff query with better data validation and profile image handling
$facultyStaff = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.username, 
            u.email, 
            u.name, 
            u.mi, 
            u.surname, 
            CONCAT(
                TRIM(u.name), 
                CASE 
                    WHEN u.mi IS NOT NULL AND TRIM(u.mi) != '' 
                    THEN CONCAT(' ', TRIM(u.mi), '. ') 
                    ELSE ' ' 
                END,
                TRIM(u.surname)
            ) AS full_name,
            u.employee_id,
            u.position,
            u.profile_image,
            u.last_login,
            u.is_approved,
            u.phone,
            u.hire_date,
            u.created_at as account_created,
            u.email_verified,
            u.is_restricted,
            d.department_name,
            d.department_code,
            -- Calculate additional metrics
            (SELECT COUNT(*) FROM faculty_document_submissions fds 
             WHERE fds.faculty_id = u.id AND fds.academic_year = YEAR(CURDATE())) as submissions_this_year,
            (SELECT MAX(submitted_at) FROM faculty_document_submissions fds 
             WHERE fds.faculty_id = u.id) as last_submission
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role = 'user' 
        AND u.department_id = ?
        AND u.is_approved = 1
        AND u.id != ? -- Exclude current admin if they're also a user
        ORDER BY u.surname ASC, u.name ASC
    ");
    $stmt->execute([$adminDepartmentId, $currentAdmin['id']]);
    $facultyStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Validate and enhance faculty data
    foreach ($facultyStaff as &$faculty) {
        // Ensure full_name is not empty
        if (empty($faculty['full_name']) || trim($faculty['full_name']) == '') {
            $faculty['full_name'] = $faculty['username'] ?: 'Unknown User';
        }
        
        // Generate proper profile image URL using the config function
        $faculty['profile_image_url'] = getProfileImageUrl($faculty['profile_image']);
        
        // Determine online status (logged in within last hour)
        $faculty['is_online'] = $faculty['last_login'] && 
                               strtotime($faculty['last_login']) > strtotime('-1 hour');
        
        // Determine activity status (logged in within last 7 days)
        $faculty['recently_active'] = $faculty['last_login'] && 
                                    strtotime($faculty['last_login']) > strtotime('-7 days');
        
        // Ensure position is not null
        if (empty($faculty['position'])) {
            $faculty['position'] = 'Faculty Member';
        }
        
        // Format dates for display
        $faculty['last_login_formatted'] = $faculty['last_login'] ? 
            date('M j, Y \a\t g:i A', strtotime($faculty['last_login'])) : 'Never';
        
        $faculty['account_age_days'] = $faculty['account_created'] ? 
            floor((time() - strtotime($faculty['account_created'])) / 86400) : 0;
            
        // Add submission activity indicators
        $faculty['has_recent_submissions'] = $faculty['last_submission'] && 
            strtotime($faculty['last_submission']) > strtotime('-30 days');
    }
    
} catch(Exception $e) {
    error_log("Error fetching faculty staff: " . $e->getMessage());
    $facultyStaff = [];
}

/**
 * Resolve a stored `users.profile_image` to a URL the browser can load.
 *
 * The previous version hard-coded a '../' prefix, which from roles/admin
 * resolved to roles/uploads/... instead of ODCI/uploads/..., so every avatar
 * missed and fell back to the logo. The shared helper derives the web prefix
 * from the caller's own directory and probes both the project root and roles/.
 *
 * @deprecated Kept as a thin wrapper because roles/admin/script/messaging-system.php
 *             and the AJAX branch of this file still call this name.
 */
function getProfileImageUrl($dbImagePath) {
    // Fallback is given project-root relative; the helper adds the '../' depth.
    return odci_profile_image_url($dbImagePath, __DIR__ . '/..', 'img/cvsu-logo.png');
}

// Enhanced function to get document submission stats for a faculty member
function getFacultySubmissionStats($pdo, $userId) {
    $stats = [
        'total_required' => 0,
        'total_submitted' => 0,
        'completion_rate' => 0,
        'latest_submission' => null,
        'overdue_count' => 0,
        'pending_count' => 0,
        'on_time_submissions' => 0,
        'submission_streak' => 0,
        'total_files' => 0,
        'average_submission_time' => null,
        'academic_year' => null,
        'semester' => null,
        'document_types' => []
    ];

    try {
        // Period being reported. Defaults to the newest period that actually has
        // uploads, because document_requirements is stale (last row is AY 2025)
        // and a calendar-derived period would report 0% for everyone.
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n');
        $currentSemester = ($currentMonth >= 6 && $currentMonth <= 11) ? 'first' : 'second';

        $latest = odci_latest_submission_period($pdo, (int)$userId);
        $reportYear = $latest['start_year'] ?: $currentYear;
        $reportSemester = $latest['semester'] ?: $currentSemester;

        $stats['academic_year'] = odci_academic_year_range($reportYear);
        $stats['semester'] = $reportSemester;

        // Tracked document types for the period: prefer the requirement rows that
        // exist for it, otherwise fall back to the canonical category list so an
        // empty period does not divide by zero.
        $requirementTypes = odci_period_required_document_types($pdo, $reportYear, $reportSemester);
        $stats['total_required'] = count($requirementTypes);
        if ($stats['total_required'] === 0) {
            $stats['total_required'] = count(odci_default_document_types());
        }

        // Real submissions: files joined to the folder that carries the category.
        $categoryMap = odci_category_to_document_type();
        $stmt = $pdo->prepare("
            SELECT fo.category AS category,
                   COUNT(f.id) AS file_count,
                   SUM(f.file_size) AS total_size,
                   MAX(f.uploaded_at) AS latest_upload,
                   MIN(f.uploaded_at) AS first_upload
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            WHERE f.uploaded_by = ?
              AND " . odci_academic_year_sql('f.academic_year') . "
              AND f.semester = ?
              AND f.is_deleted = 0
              AND fo.is_deleted = 0
              AND fo.category IS NOT NULL
            GROUP BY fo.category
        ");
        $stmt->execute([(int)$userId, $reportYear, $reportSemester]);

        $latestUpload = null;
        $totalFiles = 0;
        $totalSize = 0;
        $submittedTypes = [];

        foreach ($stmt->fetchAll() as $row) {
            $matchedType = $categoryMap[$row['category']] ?? $row['category'];
            $submittedTypes[$matchedType] = [
                'files' => (int)$row['file_count'],
                'size' => (int)$row['total_size'],
                'latest' => $row['latest_upload']
            ];
            $totalFiles += (int)$row['file_count'];
            $totalSize += (int)$row['total_size'];
            if ($row['latest_upload'] && (!$latestUpload || $row['latest_upload'] > $latestUpload)) {
                $latestUpload = $row['latest_upload'];
            }
        }

        $stats['document_types'] = $submittedTypes;
        $stats['total_submitted'] = count($submittedTypes);
        $stats['total_files'] = $totalFiles;
        $stats['total_size'] = $totalSize;
        $stats['latest_submission'] = $latestUpload;

        if ($stats['total_required'] > 0) {
            $stats['completion_rate'] = round(($stats['total_submitted'] / $stats['total_required']) * 100, 1);
        }

        // Deadlines only exist on document_requirements; without one there is
        // nothing to be late against, so both counts stay at zero.
        $deadlineMap = odci_period_deadlines($pdo, $reportYear, $reportSemester, (int)$userId);
        if (!empty($deadlineMap)) {
            $overdue = 0;
            foreach ($deadlineMap as $type => $deadline) {
                if ($deadline === null) {
                    continue;
                }
                if (!isset($submittedTypes[$type]) && strtotime($deadline) < strtotime(date('Y-m-d'))) {
                    $overdue++;
                }
            }
            $stats['overdue_count'] = $overdue;
            $stats['pending_count'] = max(0, $stats['total_required'] - $stats['total_submitted'] - $overdue);

            $onTime = 0;
            foreach ($submittedTypes as $type => $info) {
                if (isset($deadlineMap[$type]) && $deadlineMap[$type] !== null
                    && $info['latest'] && strtotime($info['latest']) <= strtotime($deadlineMap[$type])) {
                    $onTime++;
                }
            }
            $stats['on_time_submissions'] = $onTime;
        } else {
            $stats['pending_count'] = max(0, $stats['total_required'] - $stats['total_submitted']);
        }

        // Consecutive months (most recent first) in which this user uploaded.
        $stmt = $pdo->prepare("
            SELECT DISTINCT DATE_FORMAT(uploaded_at, '%Y-%m') AS ym
            FROM files
            WHERE uploaded_by = ?
              AND is_deleted = 0
              AND uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            ORDER BY ym DESC
        ");
        $stmt->execute([(int)$userId]);
        $months = array_column($stmt->fetchAll(), 'ym');
        $stats['submission_streak'] = odci_count_month_streak($months);

        // Additional metrics
        $stats['submission_percentage'] = $stats['completion_rate'];
        $stats['status'] = getSubmissionStatus($stats['completion_rate']);
        $stats['performance_indicator'] = getPerformanceIndicator($stats);
        
    } catch(Exception $e) {
        error_log("Error getting faculty stats for user $userId: " . $e->getMessage());
        // Return default stats on error
    }

    return $stats;
}

// Helper function to determine submission status
function getSubmissionStatus($completionRate) {
    if ($completionRate >= 90) return 'excellent';
    if ($completionRate >= 75) return 'good';
    if ($completionRate >= 50) return 'fair';
    return 'needs_attention';
}

// Helper function to get performance indicator
function getPerformanceIndicator($stats) {
    $score = 0;
    
    // Completion rate (40% weight)
    $score += ($stats['completion_rate'] / 100) * 40;
    
    // On-time submissions (30% weight)
    if ($stats['total_submitted'] > 0) {
        $score += ($stats['on_time_submissions'] / $stats['total_submitted']) * 30;
    }
    
    // Recent activity (20% weight)
    if ($stats['submission_streak'] >= 3) {
        $score += 20;
    } elseif ($stats['submission_streak'] >= 1) {
        $score += 10;
    }
    
    // No overdue items (10% weight)
    if ($stats['overdue_count'] == 0) {
        $score += 10;
    }
    
    if ($score >= 85) return 'outstanding';
    if ($score >= 70) return 'proficient';
    if ($score >= 50) return 'developing';
    return 'needs_improvement';
}

// Function to get department statistics with enhanced metrics
function getDepartmentStats($pdo, $departmentId) {
    $stats = [
        'total_faculty' => 0,
        'active_faculty' => 0,
        'online_faculty' => 0,
        'high_performers' => 0,
        'low_performers' => 0,
        'avg_completion_rate' => 0,
        'total_submissions_this_month' => 0,
        'overdue_submissions' => 0,
        'email_verified_count' => 0
    ];

    try {
        // Get total faculty count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_faculty,
                   COUNT(CASE WHEN email_verified = 1 THEN 1 END) as email_verified_count,
                   COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as online_faculty,
                   COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_faculty
            FROM users 
            WHERE department_id = ? 
            AND role = 'user' 
            AND is_approved = 1
        ");
        $stmt->execute([$departmentId]);
        $result = $stmt->fetch();
        
        $stats['total_faculty'] = (int)($result['total_faculty'] ?? 0);
        $stats['active_faculty'] = (int)($result['active_faculty'] ?? 0);
        $stats['online_faculty'] = (int)($result['online_faculty'] ?? 0);
        $stats['email_verified_count'] = (int)($result['email_verified_count'] ?? 0);

        // Get submissions this month
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as monthly_submissions
            FROM faculty_document_submissions fds
            INNER JOIN users u ON fds.faculty_id = u.id
            WHERE u.department_id = ?
            AND fds.submitted_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $stmt->execute([$departmentId]);
        $result = $stmt->fetch();
        $stats['total_submissions_this_month'] = (int)($result['monthly_submissions'] ?? 0);

        // Calculate performance distribution and completion rates
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE department_id = ? 
            AND role = 'user' 
            AND is_approved = 1
        ");
        $stmt->execute([$departmentId]);
        $facultyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $totalCompletionRate = 0;
        $highPerformers = 0;
        $lowPerformers = 0;
        $totalOverdue = 0;

        foreach ($facultyIds as $facultyId) {
            $facultyStats = getFacultySubmissionStats($pdo, $facultyId);
            $completionRate = $facultyStats['completion_rate'];
            
            $totalCompletionRate += $completionRate;
            $totalOverdue += $facultyStats['overdue_count'];
            
            if ($completionRate >= 80) {
                $highPerformers++;
            } elseif ($completionRate < 50) {
                $lowPerformers++;
            }
        }

        $stats['high_performers'] = $highPerformers;
        $stats['low_performers'] = $lowPerformers;
        $stats['overdue_submissions'] = $totalOverdue;
        
        if (count($facultyIds) > 0) {
            $stats['avg_completion_rate'] = round($totalCompletionRate / count($facultyIds), 1);
        }

    } catch(Exception $e) {
        error_log("Error getting department stats: " . $e->getMessage());
    }

    return $stats;
}

// Function to log admin actions (for audit trail)
function logAdminAction($pdo, $adminId, $action, $targetUserId = null, $details = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, resource_type, resource_id, description, ip_address, user_agent, created_at)
            VALUES (?, ?, 'user', ?, ?, ?, ?, NOW())
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt->execute([
            $adminId, 
            $action, 
            $targetUserId, 
            $details, 
            $ipAddress, 
            $userAgent
        ]);
        
    } catch(Exception $e) {
        error_log("Error logging admin action: " . $e->getMessage());
    }
}

// Handle AJAX requests with enhanced functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Verify CSRF token for POST requests
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit();
    }
    
    switch ($_POST['action']) {
        case 'get_faculty_stats':
            if (isset($_POST['faculty_id'])) {
                $stats = getFacultySubmissionStats($pdo, $_POST['faculty_id']);
                echo json_encode(['success' => true, 'data' => $stats]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Faculty ID required']);
            }
            break;
            
        case 'send_reminder':
            if (isset($_POST['faculty_id']) && isset($_POST['message'])) {
                $success = sendFacultyReminder($pdo, $_POST['faculty_id'], $_POST['message'], $currentAdmin['id']);
                if ($success) {
                    logAdminAction($pdo, $currentAdmin['id'], 'send_reminder', $_POST['faculty_id'], $_POST['message']);
                    echo json_encode(['success' => true, 'message' => 'Reminder sent successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to send reminder']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Required fields missing']);
            }
            break;
            
        case 'bulk_reminder':
            if (isset($_POST['faculty_ids']) && isset($_POST['message'])) {
                $facultyIds = json_decode($_POST['faculty_ids'], true);
                $successCount = 0;
                
                foreach ($facultyIds as $facultyId) {
                    if (sendFacultyReminder($pdo, $facultyId, $_POST['message'], $currentAdmin['id'])) {
                        $successCount++;
                        logAdminAction($pdo, $currentAdmin['id'], 'send_bulk_reminder', $facultyId, $_POST['message']);
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Reminder sent to $successCount faculty members"
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Required fields missing']);
            }
            break;
            
        case 'update_faculty_status':
            if (isset($_POST['faculty_id']) && isset($_POST['status'])) {
                $success = updateFacultyStatus($pdo, $_POST['faculty_id'], $_POST['status'], $currentAdmin['id']);
                if ($success) {
                    logAdminAction($pdo, $currentAdmin['id'], 'update_faculty_status', $_POST['faculty_id'], "Status changed to: " . $_POST['status']);
                    echo json_encode(['success' => true, 'message' => 'Faculty status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to update faculty status']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Required fields missing']);
            }
            break;
            
        case 'export_faculty_data':
            $exportData = exportFacultyData($pdo, $adminDepartmentId);
            echo json_encode([
                'success' => true,
                'data' => $exportData,
                'filename' => 'faculty_data_' . date('Y-m-d') . '.csv'
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit();
}

// Additional helper functions

function sendFacultyReminder($pdo, $facultyId, $message, $senderId) {
    try {
        // Get faculty email
        $stmt = $pdo->prepare("SELECT email, name, surname FROM users WHERE id = ?");
        $stmt->execute([$facultyId]);
        $faculty = $stmt->fetch();
        
        if (!$faculty) {
            return false;
        }
        
        // Add notification to database
        $notificationTitle = "Document Submission Reminder";
        addNotification($pdo, $facultyId, $notificationTitle, $message, 'info', 'folders.php');
        
        // You can implement email sending here
        // sendEmail($faculty['email'], $notificationTitle, $message);
        
        return true;
    } catch (Exception $e) {
        error_log("Error sending reminder: " . $e->getMessage());
        return false;
    }
}

function updateFacultyStatus($pdo, $facultyId, $status, $adminId) {
    try {
        $allowedStatuses = ['approved', 'restricted', 'suspended'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }
        
        $isRestricted = ($status === 'restricted' || $status === 'suspended') ? 1 : 0;
        $isApproved = ($status === 'approved') ? 1 : 0;
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET is_restricted = ?, is_approved = ?, updated_at = NOW() 
            WHERE id = ? AND role = 'user'
        ");
        
        return $stmt->execute([$isRestricted, $isApproved, $facultyId]);
    } catch (Exception $e) {
        error_log("Error updating faculty status: " . $e->getMessage());
        return false;
    }
}

function exportFacultyData($pdo, $departmentId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.employee_id,
                CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name,
                u.position,
                u.email,
                u.phone,
                u.last_login,
                u.created_at,
                CASE WHEN u.is_restricted = 1 THEN 'Restricted' ELSE 'Active' END as status
            FROM users u
            WHERE u.department_id = ? AND u.role = 'user' AND u.is_approved = 1
            ORDER BY u.surname, u.name
        ");
        $stmt->execute([$departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error exporting faculty data: " . $e->getMessage());
        return [];
    }
}

// Get department statistics for display
$departmentStats = getDepartmentStats($pdo, $adminDepartmentId);

// Log page view
logAdminAction($pdo, $currentAdmin['id'], 'view_faculty_list', null, 'Viewed faculty staff page');

// Generate CSRF token for forms
$csrfToken = generateCSRFToken();

?>

