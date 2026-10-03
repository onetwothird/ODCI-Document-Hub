<?php
// Enhanced script/faculty-staff.php with proper profile image handling
require_once '../../includes/config.php';

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

function getProfileImageUrl($dbImagePath) {

    $defaultProfileImage = 'assets/img/cvsu-logo.png';

    if (empty($dbImagePath)) {
        return $defaultProfileImage;
    }
    $webImagePath = '../' . ltrim($dbImagePath, '/');
    $fileSystemPath = '../' . ltrim($dbImagePath, '/');
    
    if (file_exists($fileSystemPath)) {
        return $webImagePath . '?v=' . filemtime($fileSystemPath);
    } else {
        error_log("Profile image not found at: " . $fileSystemPath);
        return $defaultProfileImage;
    }
}

// Alternative function if you need more debugging
function getProfileImageUrlWithDebug($dbImagePath, $facultyName = '') {
    $defaultProfileImage = 'assets/img/default-avatar.png';
    
    if (empty($dbImagePath)) {
        error_log("No image path for faculty: " . $facultyName);
        return $defaultProfileImage;
    }
    
    $webImagePath = '../' . ltrim($dbImagePath, '/');
    $fileSystemPath = '../' . ltrim($dbImagePath, '/');
    
    error_log("Faculty: $facultyName | DB Path: $dbImagePath | Web Path: $webImagePath | File System Path: $fileSystemPath | Exists: " . (file_exists($fileSystemPath) ? 'Yes' : 'No'));
    
    if (file_exists($fileSystemPath)) {
        return $webImagePath . '?v=' . filemtime($fileSystemPath);
    } else {
        return $defaultProfileImage;
    }
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
        'average_submission_time' => null
    ];

    try {
        // Get current academic year and semester
        $currentYear = date('Y');
        $currentMonth = (int)date('n');
        
        // Determine semester based on month (adjust according to your academic calendar)
        $currentSemester = ($currentMonth >= 6 && $currentMonth <= 11) ? '1st Semester' : '2nd Semester';

        // Count total required documents for current period
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT document_type) as total_required
            FROM document_requirements
            WHERE academic_year = ? 
            AND semester = ?
            AND (department_id IS NULL OR department_id = (
                SELECT department_id FROM users WHERE id = ?
            ))
            AND is_required = 1
        ");
        $stmt->execute([$currentYear, $currentSemester, $userId]);
        $required = $stmt->fetch();
        $stats['total_required'] = (int)($required['total_required'] ?? 0);

        // Count submitted documents for current period with timing analysis
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT fds.document_type) as total_submitted, 
                MAX(fds.submitted_at) as latest_submission,
                COUNT(*) as total_files,
                AVG(DATEDIFF(fds.submitted_at, dr.deadline_date)) as avg_submission_timing
            FROM faculty_document_submissions fds
            LEFT JOIN document_requirements dr ON (
                fds.document_type = dr.document_type 
                AND fds.academic_year = dr.academic_year 
                AND fds.semester = dr.semester
            )
            WHERE fds.faculty_id = ?
            AND fds.academic_year = ?
            AND fds.semester = ?
        ");
        $stmt->execute([$userId, $currentYear, $currentSemester]);
        $submitted = $stmt->fetch();
        
        $stats['total_submitted'] = (int)($submitted['total_submitted'] ?? 0);
        $stats['latest_submission'] = $submitted['latest_submission'];
        $stats['total_files'] = (int)($submitted['total_files'] ?? 0);
        $stats['average_submission_time'] = $submitted['avg_submission_timing'];

        // Calculate completion rate
        if ($stats['total_required'] > 0) {
            $stats['completion_rate'] = round(($stats['total_submitted'] / $stats['total_required']) * 100, 1);
        }

        // Get overdue documents (past deadline)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as overdue_count
            FROM document_requirements dr
            LEFT JOIN faculty_document_submissions fds ON (
                dr.document_type = fds.document_type 
                AND fds.faculty_id = ? 
                AND fds.academic_year = dr.academic_year
                AND fds.semester = dr.semester
            )
            WHERE dr.academic_year = ?
            AND dr.semester = ?
            AND (dr.department_id IS NULL OR dr.department_id = (
                SELECT department_id FROM users WHERE id = ?
            ))
            AND dr.is_required = 1
            AND dr.deadline_date < CURDATE()
            AND fds.id IS NULL
        ");
        $stmt->execute([$userId, $currentYear, $currentSemester, $userId]);
        $overdue = $stmt->fetch();
        $stats['overdue_count'] = (int)($overdue['overdue_count'] ?? 0);

        // Get pending documents (not yet submitted, not overdue)
        $stats['pending_count'] = $stats['total_required'] - $stats['total_submitted'] - $stats['overdue_count'];
        $stats['pending_count'] = max(0, $stats['pending_count']); // Ensure non-negative

        // Calculate on-time submissions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as on_time_count
            FROM faculty_document_submissions fds
            INNER JOIN document_requirements dr ON (
                fds.document_type = dr.document_type 
                AND fds.academic_year = dr.academic_year 
                AND fds.semester = dr.semester
            )
            WHERE fds.faculty_id = ?
            AND fds.academic_year = ?
            AND fds.semester = ?
            AND fds.submitted_at <= dr.deadline_date
        ");
        $stmt->execute([$userId, $currentYear, $currentSemester]);
        $onTime = $stmt->fetch();
        $stats['on_time_submissions'] = (int)($onTime['on_time_count'] ?? 0);

        // Calculate submission streak (consecutive months with submissions)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT DATE_FORMAT(submitted_at, '%Y-%m')) as streak_months
            FROM faculty_document_submissions 
            WHERE faculty_id = ? 
            AND submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            ORDER BY submitted_at DESC
        ");
        $stmt->execute([$userId]);
        $streak = $stmt->fetch();
        $stats['submission_streak'] = (int)($streak['streak_months'] ?? 0);

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
        addNotification($pdo, $facultyId, $notificationTitle, $message, 'info', 'submission_tracker.php');
        
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


<script>
    // Enhanced image error handling function
    function handleImageError(img) {
        console.log('Image failed to load:', img.src);
        console.log('Original path from data attribute:', img.dataset.originalPath);
        
        // List of fallback options
        const fallbacks = [
            'assets/img/default-avatar.png',
            '../assets/img/default-avatar.png',
            '../../assets/img/default-avatar.png',
            'assets/img/user-placeholder.png',
            'https://via.placeholder.com/90x90/667eea/ffffff?text=User'
        ];
        
        // Try each fallback
        let fallbackIndex = parseInt(img.dataset.fallbackIndex || '0');
        
        if (fallbackIndex < fallbacks.length) {
            img.dataset.fallbackIndex = (fallbackIndex + 1).toString();
            img.src = fallbacks[fallbackIndex];
            console.log('Trying fallback:', fallbacks[fallbackIndex]);
        } else {
            // Last resort: create a colored placeholder
            img.style.display = 'none';
            const placeholder = document.createElement('div');
            placeholder.style.cssText = `
                width: 90px; 
                height: 90px; 
                border-radius: 50%; 
                background: linear-gradient(135deg, #667eea, #764ba2);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 24px;
                margin-right: 28px;
                border: 4px solid #e8ecf4;
            `;
            
            // Extract initials from alt text
            const name = img.alt || 'User';
            const initials = name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            placeholder.textContent = initials;
            placeholder.className = img.className.replace('faculty-avatar', '');
            
            img.parentNode.insertBefore(placeholder, img);
            console.log('Used placeholder with initials:', initials);
        }
    }

    // Function to test image loading and provide debug info
    function debugImageLoading() {
        document.querySelectorAll('.faculty-avatar').forEach((img, index) => {
            console.log(`Image ${index}:`, {
                src: img.src,
                originalPath: img.dataset.originalPath,
                alt: img.alt,
                complete: img.complete,
                naturalWidth: img.naturalWidth,
                naturalHeight: img.naturalHeight
            });
            
            // Test if image actually loads
            if (img.complete && img.naturalHeight === 0) {
                console.warn(`Image ${index} appears to have failed to load`);
            }
        });
    }

    // Call debug function after page loads (remove this in production)
    setTimeout(debugImageLoading, 1000);
</script>