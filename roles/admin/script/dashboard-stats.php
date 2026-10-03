<?php
// script/dashboard-stats.php
require_once '../../includes/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Handle different statistics requests
$action = $_GET['action'] ?? 'get_all_stats';
$timeframe = $_GET['timeframe'] ?? '30'; // days

switch ($action) {
    case 'get_all_stats':
        echo json_encode(getAllDashboardStats($pdo, $currentUser, $timeframe));
        break;
    case 'get_faculty_performance':
        echo json_encode(getFacultyPerformanceStats($pdo, $currentUser));
        break;
    case 'get_submission_trends':
        echo json_encode(getSubmissionTrends($pdo, $currentUser, $timeframe));
        break;
    case 'get_recent_activities':
        echo json_encode(getRecentActivities($pdo, $currentUser));
        break;
    case 'get_department_comparison':
        echo json_encode(getDepartmentComparison($pdo, $currentUser));
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getAllDashboardStats($pdo, $currentUser, $timeframe) {
    try {
        $departmentId = $currentUser['department_id'];
        $stats = [];

        // Faculty Overview Stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_faculty,
                COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved_faculty,
                COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as active_faculty,
                COUNT(CASE WHEN email_verified = 1 THEN 1 END) as verified_faculty,
                COUNT(CASE WHEN is_restricted = 1 THEN 1 END) as restricted_faculty
            FROM users 
            WHERE role = 'user' AND department_id = ?
        ");
        $stmt->execute([$timeframe, $departmentId]);
        $facultyStats = $stmt->fetch();

        // Document Submission Stats
        $currentYear = date('Y');
        $currentSemester = (date('n') >= 6 && date('n') <= 11) ? '1st Semester' : '2nd Semester';
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT fds.faculty_id) as faculty_with_submissions,
                COUNT(*) as total_submissions,
                COUNT(CASE WHEN fds.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as recent_submissions
            FROM faculty_document_submissions fds
            JOIN users u ON fds.faculty_id = u.id
            WHERE u.department_id = ? AND fds.academic_year = ?
        ");
        $stmt->execute([$timeframe, $departmentId, $currentYear]);
        $submissionStats = $stmt->fetch();

        // Performance Distribution
        $performanceStats = getFacultyPerformanceDistribution($pdo, $departmentId);

        // Recent Activity Counts
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN action = 'login' THEN 1 END) as recent_logins,
                COUNT(CASE WHEN action LIKE '%document%' THEN 1 END) as document_activities,
                COUNT(CASE WHEN action LIKE '%message%' THEN 1 END) as message_activities
            FROM activity_logs al
            JOIN users u ON al.user_id = u.id
            WHERE u.department_id = ? 
            AND al.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$departmentId, $timeframe]);
        $activityStats = $stmt->fetch();

        // Completion Rate Trends
        $trendData = getCompletionTrends($pdo, $departmentId, $timeframe);

        $stats = [
            'success' => true,
            'faculty_overview' => $facultyStats,
            'submission_stats' => $submissionStats,
            'performance_distribution' => $performanceStats,
            'activity_stats' => $activityStats,
            'trend_data' => $trendData,
            'last_updated' => date('Y-m-d H:i:s')
        ];

        return $stats;

    } catch (Exception $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to load statistics'];
    }
}

function getFacultyPerformanceDistribution($pdo, $departmentId) {
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE department_id = ? AND role = 'user' AND is_approved = 1
        ");
        $stmt->execute([$departmentId]);
        $facultyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $distribution = [
            'excellent' => 0,  // 90%+
            'good' => 0,       // 70-89%
            'fair' => 0,       // 50-69%
            'poor' => 0        // <50%
        ];

        foreach ($facultyIds as $facultyId) {
            $stats = getFacultySubmissionStats($pdo, $facultyId);
            $rate = $stats['completion_rate'];
            
            if ($rate >= 90) $distribution['excellent']++;
            elseif ($rate >= 70) $distribution['good']++;
            elseif ($rate >= 50) $distribution['fair']++;
            else $distribution['poor']++;
        }

        return $distribution;

    } catch (Exception $e) {
        error_log("Error getting performance distribution: " . $e->getMessage());
        return ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0];
    }
}

function getCompletionTrends($pdo, $departmentId, $days) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(fds.submitted_at) as submission_date,
                COUNT(*) as daily_submissions,
                COUNT(DISTINCT fds.faculty_id) as active_faculty
            FROM faculty_document_submissions fds
            JOIN users u ON fds.faculty_id = u.id
            WHERE u.department_id = ? 
            AND fds.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(fds.submitted_at)
            ORDER BY submission_date ASC
        ");
        $stmt->execute([$departmentId, $days]);
        $trends = $stmt->fetchAll();

        // Fill in missing dates with zero values
        $startDate = new DateTime("-$days days");
        $endDate = new DateTime();
        $trendData = [];

        while ($startDate <= $endDate) {
            $dateStr = $startDate->format('Y-m-d');
            $found = false;
            
            foreach ($trends as $trend) {
                if ($trend['submission_date'] === $dateStr) {
                    $trendData[] = [
                        'date' => $dateStr,
                        'submissions' => (int)$trend['daily_submissions'],
                        'active_faculty' => (int)$trend['active_faculty']
                    ];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $trendData[] = [
                    'date' => $dateStr,
                    'submissions' => 0,
                    'active_faculty' => 0
                ];
            }
            
            $startDate->add(new DateInterval('P1D'));
        }

        return $trendData;

    } catch (Exception $e) {
        error_log("Error getting completion trends: " . $e->getMessage());
        return [];
    }
}

function getFacultyPerformanceStats($pdo, $currentUser) {
    try {
        $departmentId = $currentUser['department_id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name,
                u.position,
                u.profile_image,
                u.last_login
            FROM users u
            WHERE u.department_id = ? AND u.role = 'user' AND u.is_approved = 1
            ORDER BY u.surname, u.name
        ");
        $stmt->execute([$departmentId]);
        $faculty = $stmt->fetchAll();

        $performanceData = [];
        
        foreach ($faculty as $member) {
            $stats = getFacultySubmissionStats($pdo, $member['id']);
            
            $performanceData[] = [
                'id' => $member['id'],
                'name' => $member['full_name'],
                'position' => $member['position'],
                'profile_image_url' => getProfileImageUrl($member['profile_image']),
                'completion_rate' => $stats['completion_rate'],
                'total_submitted' => $stats['total_submitted'],
                'total_required' => $stats['total_required'],
                'overdue_count' => $stats['overdue_count'],
                'performance_indicator' => getPerformanceIndicator($stats),
                'last_login' => $member['last_login'],
                'is_active' => $member['last_login'] && strtotime($member['last_login']) > strtotime('-7 days')
            ];
        }

        // Sort by completion rate descending
        usort($performanceData, function($a, $b) {
            return $b['completion_rate'] - $a['completion_rate'];
        });

        return [
            'success' => true,
            'faculty_performance' => $performanceData
        ];

    } catch (Exception $e) {
        error_log("Error getting faculty performance stats: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to load performance data'];
    }
}

function getSubmissionTrends($pdo, $currentUser, $timeframe) {
    try {
        $departmentId = $currentUser['department_id'];
        
        // Get submission trends by document type
        $stmt = $pdo->prepare("
            SELECT 
                fds.document_type,
                COUNT(*) as submission_count,
                COUNT(DISTINCT fds.faculty_id) as faculty_count,
                AVG(CASE 
                    WHEN dr.deadline_date IS NOT NULL 
                    THEN DATEDIFF(fds.submitted_at, dr.deadline_date) 
                    ELSE NULL 
                END) as avg_days_from_deadline
            FROM faculty_document_submissions fds
            JOIN users u ON fds.faculty_id = u.id
            LEFT JOIN document_requirements dr ON (
                fds.document_type = dr.document_type 
                AND fds.academic_year = dr.academic_year 
                AND fds.semester = dr.semester
            )
            WHERE u.department_id = ? 
            AND fds.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY fds.document_type
            ORDER BY submission_count DESC
        ");
        $stmt->execute([$departmentId, $timeframe]);
        $documentTrends = $stmt->fetchAll();

        // Get weekly submission patterns
        $stmt = $pdo->prepare("
            SELECT 
                DAYOFWEEK(fds.submitted_at) as day_of_week,
                COUNT(*) as submission_count
            FROM faculty_document_submissions fds
            JOIN users u ON fds.faculty_id = u.id
            WHERE u.department_id = ? 
            AND fds.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DAYOFWEEK(fds.submitted_at)
            ORDER BY day_of_week
        ");
        $stmt->execute([$departmentId, $timeframe]);
        $weeklyPatterns = $stmt->fetchAll();

        // Convert day numbers to names
        $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($weeklyPatterns as &$pattern) {
            $pattern['day_name'] = $dayNames[$pattern['day_of_week']];
        }

        return [
            'success' => true,
            'document_trends' => $documentTrends,
            'weekly_patterns' => $weeklyPatterns
        ];

    } catch (Exception $e) {
        error_log("Error getting submission trends: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to load submission trends'];
    }
}

function getRecentActivities($pdo, $currentUser) {
    try {
        $departmentId = $currentUser['department_id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                al.*,
                CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as user_name,
                u.profile_image,
                u.position
            FROM activity_logs al
            JOIN users u ON al.user_id = u.id
            WHERE u.department_id = ? 
            AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY al.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$departmentId]);
        $activities = $stmt->fetchAll();

        foreach ($activities as &$activity) {
            $activity['profile_image_url'] = getProfileImageUrl($activity['profile_image']);
            $activity['formatted_time'] = timeAgo($activity['created_at']);
            $activity['action_description'] = formatActionDescription($activity['action'], $activity['description']);
        }

        return [
            'success' => true,
            'recent_activities' => $activities
        ];

    } catch (Exception $e) {
        error_log("Error getting recent activities: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to load recent activities'];
    }
}

function getDepartmentComparison($pdo, $currentUser) {
    try {
        // Only show if user is super admin
        if ($currentUser['role'] !== 'super_admin') {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }

        $stmt = $pdo->prepare("
            SELECT 
                d.id,
                d.department_name,
                d.department_code,
                COUNT(u.id) as faculty_count,
                COUNT(CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_faculty
            FROM departments d
            LEFT JOIN users u ON d.id = u.department_id AND u.role = 'user' AND u.is_approved = 1
            GROUP BY d.id, d.department_name, d.department_code
            ORDER BY faculty_count DESC
        ");
        $stmt->execute();
        $departments = $stmt->fetchAll();

        // Get average completion rates per department
        foreach ($departments as &$dept) {
            if ($dept['faculty_count'] > 0) {
                $stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE department_id = ? AND role = 'user' AND is_approved = 1
                ");
                $stmt->execute([$dept['id']]);
                $facultyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $totalCompletion = 0;
                foreach ($facultyIds as $facultyId) {
                    $stats = getFacultySubmissionStats($pdo, $facultyId);
                    $totalCompletion += $stats['completion_rate'];
                }

                $dept['avg_completion_rate'] = count($facultyIds) > 0 ? 
                    round($totalCompletion / count($facultyIds), 1) : 0;
            } else {
                $dept['avg_completion_rate'] = 0;
            }
        }

        return [
            'success' => true,
            'department_comparison' => $departments
        ];

    } catch (Exception $e) {
        error_log("Error getting department comparison: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to load department comparison'];
    }
}

// Helper functions
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

function formatActionDescription($action, $description) {
    $actionMap = [
        'login' => 'Logged in',
        'logout' => 'Logged out',
        'upload_document' => 'Uploaded a document',
        'view_submissions' => 'Viewed submissions',
        'send_message' => 'Sent a message',
        'update_profile' => 'Updated profile',
        'view_faculty_list' => 'Viewed faculty list'
    ];

    $baseDescription = $actionMap[$action] ?? ucfirst(str_replace('_', ' ', $action));
    
    if ($description && strlen($description) > 0) {
        return $baseDescription . ': ' . substr($description, 0, 50) . (strlen($description) > 50 ? '...' : '');
    }
    
    return $baseDescription;
}

// Include the getFacultySubmissionStats function from faculty-staff.php
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
        $currentYear = date('Y');
        $currentMonth = (int)date('n');
        $currentSemester = ($currentMonth >= 6 && $currentMonth <= 11) ? '1st Semester' : '2nd Semester';

        // Count total required documents
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

        // Count submitted documents
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT fds.document_type) as total_submitted, 
                MAX(fds.submitted_at) as latest_submission,
                COUNT(*) as total_files
            FROM faculty_document_submissions fds
            WHERE fds.faculty_id = ?
            AND fds.academic_year = ?
            AND fds.semester = ?
        ");
        $stmt->execute([$userId, $currentYear, $currentSemester]);
        $submitted = $stmt->fetch();
        
        $stats['total_submitted'] = (int)($submitted['total_submitted'] ?? 0);
        $stats['latest_submission'] = $submitted['latest_submission'];

        // Calculate completion rate
        if ($stats['total_required'] > 0) {
            $stats['completion_rate'] = round(($stats['total_submitted'] / $stats['total_required']) * 100, 1);
        }

        // Get overdue documents
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

        $stats['pending_count'] = $stats['total_required'] - $stats['total_submitted'] - $stats['overdue_count'];
        $stats['pending_count'] = max(0, $stats['pending_count']);

        // Calculate submission streak
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT DATE_FORMAT(submitted_at, '%Y-%m')) as streak_months
            FROM faculty_document_submissions 
            WHERE faculty_id = ? 
            AND submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        ");
        $stmt->execute([$userId]);
        $streak = $stmt->fetch();
        $stats['submission_streak'] = (int)($streak['streak_months'] ?? 0);

    } catch(Exception $e) {
        error_log("Error getting faculty stats for user $userId: " . $e->getMessage());
    }

    return $stats;
}

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
?>