<?php
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current user information
$currentUser = getCurrentUser($pdo);

if (!$currentUser) {
    // User not found, logout
    header('Location: logout.php');
    exit();
}

// Check if user is approved
if (!$currentUser['is_approved']) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_not_approved');
    exit();
}

// Get user's department ID - CRITICAL for filtering
$userDepartmentId = null;

// First, try to get department_id from currentUser
if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    $userDepartmentId = $currentUser['department_id'];
} elseif (isset($currentUser['id'])) {
    // If not available, fetch from database
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.department_code, d.department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        $userDept = $stmt->fetch();
        
        if ($userDept && $userDept['department_id']) {
            $userDepartmentId = $userDept['department_id'];
            $currentUser['department_id'] = $userDept['department_id'];
            $currentUser['department_name'] = $userDept['department_name'];
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

// If user has no department assigned, restrict access
if (!$userDepartmentId) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=no_department_assigned');
    exit();
}

// Define file categories (existing predefined categories)
$fileCategories = [
    'ipcr_accomplishment' => [
        'name' => 'IPCR Accomplishment',
        'icon' => 'bxs-trophy',
        'color' => '#f59e0b'
    ],
    'ipcr_target' => [
        'name' => 'IPCR Target',
        'icon' => 'bxs-bullseye',
        'color' => '#ef4444'
    ],
    'workload' => [
        'name' => 'Workload',
        'icon' => 'bxs-briefcase',
        'color' => '#8b5cf6'
    ],
    'course_syllabus' => [
        'name' => 'Course Syllabus',
        'icon' => 'bxs-book-content',
        'color' => '#06b6d4'
    ],
    'syllabus_acceptance' => [
        'name' => 'Course Syllabus Acceptance Form',
        'icon' => 'bxs-check-circle',
        'color' => '#10b981'
    ],
    'exam' => [
        'name' => 'Exam',
        'icon' => 'bxs-file-doc',
        'color' => '#dc2626'
    ],
    'tos' => [
        'name' => 'TOS',
        'icon' => 'bxs-spreadsheet',
        'color' => '#059669'
    ],
    'class_record' => [
        'name' => 'Class Record',
        'icon' => 'bxs-data',
        'color' => '#7c3aed'
    ],
    'grading_sheet' => [
        'name' => 'Grading Sheet',
        'icon' => 'bxs-calculator',
        'color' => '#ea580c'
    ],
    'attendance_sheet' => [
        'name' => 'Attendance Sheet',
        'icon' => 'bxs-user-check',
        'color' => '#0284c7'
    ],
    'stakeholder_feedback' => [
        'name' => 'Stakeholder\'s Feedback Form w/ Summary',
        'icon' => 'bxs-comment-detail',
        'color' => '#9333ea'
    ],
    'consultation' => [
        'name' => 'Consultation',
        'icon' => 'bxs-chat',
        'color' => '#0d9488'
    ],
    'lecture' => [
        'name' => 'Lecture',
        'icon' => 'bxs-chalkboard',
        'color' => '#7c2d12'
    ],
    'activities' => [
        'name' => 'Activities',
        'icon' => 'bxs-game',
        'color' => '#be185d'
    ],
    'consultation_log' => [
        'name' => 'Consultation Log Sheet Form',
        'icon' => 'bxs-notepad',
        'color' => '#374151'
    ],
     'exam_acknowledgement' => [
        'name' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form',
        'icon' => 'bxs-receipt',
        'color' => '#1e40af'
    ]
];

try {
    $deletedCategoryStmt = $pdo->prepare("
        SELECT category
        FROM folders
        WHERE department_id = ?
          AND category IS NOT NULL
          AND folder_name = CONCAT('__deleted_category__:', category)
          AND parent_id IS NULL
    ");
    $deletedCategoryStmt->execute([$userDepartmentId]);
    foreach ($deletedCategoryStmt->fetchAll(PDO::FETCH_COLUMN) as $deletedCategory) {
        unset($fileCategories[$deletedCategory]);
    }
} catch (Throwable $e) {
    error_log('Deleted category lookup error: ' . $e->getMessage());
}

// Get user's department only (remove OTHR functionality)
function getUserDepartment($pdo, $userDepartmentId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM departments 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$userDepartmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching department: " . $e->getMessage());
        return null;
    }
}

// Get user's department
$userDepartment = getUserDepartment($pdo, $userDepartmentId);
$departments = $userDepartment ? [$userDepartment] : [];

// For upload modal, get all departments
function getAllDepartments($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

$allDepartments = getAllDepartments($pdo);

// Department configuration with colors and icons
$departmentConfig = [
    'TED' => ['icon' => 'bxs-graduation', 'color' => '#f59e0b'],
    'MD' => ['icon' => 'bxs-business', 'color' => '#1e40af'],
    'FASD' => ['icon' => 'bx bx-water', 'color' => '#0284c7'],
    'ASD' => ['icon' => 'bxs-palette', 'color' => '#d946ef'],
    'ITD' => ['icon' => 'bxs-chip', 'color' => '#0f766e'],
    'NSTP' => ['icon' => 'bxs-user-check', 'color' => '#22c55e']
];

// Get department information for profile image
$departmentImage = null;
$departmentCode = null;

if ($userDepartment) {
    $departmentCode = $userDepartment['department_code'];
    $departmentImage = "../../img/{$departmentCode}.jpg";
}

// Get or create category folder
function getOrCreateCategoryFolder($pdo, $departmentId, $category, $semester, $userId) {
    try {
        $semesterName = ($semester === 'first') ? 'First Semester' : 'Second Semester';
        $academicYear = date('Y') . '-' . (date('Y') + 1);
        $folderName = $academicYear . ' - ' . $semesterName;
        
        // Check if folder exists
        $stmt = $pdo->prepare("
            SELECT id FROM folders 
            WHERE department_id = ? AND folder_name = ? AND category = ? AND is_deleted = 0
        ");
        $stmt->execute([$departmentId, $folderName, $category]);
        $folder = $stmt->fetch();
        
        if ($folder) {
            return $folder['id'];
        }
        
        // Create new folder
        $stmt = $pdo->prepare("
            INSERT INTO folders (folder_name, description, created_by, department_id, category, folder_path, folder_level, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "Academic files for {$semesterName} {$academicYear}";
        $folderPath = "/departments/{$departmentId}/{$category}/{$semester}";
        
        $stmt->execute([$folderName, $description, $userId, $departmentId, $category, $folderPath, 2]);
        return $pdo->lastInsertId();
        
    } catch(Exception $e) {
        error_log("Error creating category folder: " . $e->getMessage());
        return false;
    }
}

// Format file size function
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get file icon based on extension
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $iconMap = [
        'pdf' => 'bxs-file-pdf',
        'doc' => 'bxs-file-doc',
        'docx' => 'bxs-file-doc',
        'xls' => 'bxs-spreadsheet',
        'xlsx' => 'bxs-spreadsheet',
        'ppt' => 'bxs-file-blank',
        'pptx' => 'bxs-file-blank',
        'jpg' => 'bxs-file-image',
        'jpeg' => 'bxs-file-image',
        'png' => 'bxs-file-image',
        'gif' => 'bxs-file-image',
        'txt' => 'bxs-file-txt',
        'zip' => 'bxs-file-archive',
        'rar' => 'bxs-file-archive',
        'mp4' => 'bxs-videos',
        'avi' => 'bxs-videos',
        'mp3' => 'bxs-music',
        'wav' => 'bxs-music'
    ];
    
    return isset($iconMap[$ext]) ? $iconMap[$ext] : 'bxs-file';
}

// Get user initials helper function
function getUserInitials($fullName) {
    $names = explode(' ', $fullName);
    $initials = '';
    foreach($names as $name) {
        if(!empty($name)) {
            $initials .= strtoupper($name[0]);
        }
    }
    return substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Folders - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/pages/folders/category_system.css">
    <link rel="stylesheet" href="assets/css/pages/folders/file_system.css">
    <link rel="stylesheet" href="assets/css/pages/folders/modal.css">
    <link rel="stylesheet" href="assets/css/pages/folders/ui_components.css">
    <link rel="stylesheet" href="assets/css/pages/folders/department_structure.css">
    <link rel="stylesheet" href="assets/css/pages/folders/responsive.css">
    <link rel="stylesheet" href="assets/css/pages/folders/semester_selection.css">
    <link rel="stylesheet" href="assets/css/components/file_preview_modal.css">

    <style>
        .profile::after {
            content: '<?php echo $departmentCode; ?>';
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: var(--blue);
            color: white;
            font-size: 8px;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: 500;
            min-width: 20px;
            text-align: center;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="user-folders-page">
    <!-- Create Folder Modal -->
    <div id="createFolderModal" class="modal-overlay">
        <div class="modal-container">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-header-content">
                    <div>
                        <h2>
                            <i class='bx bxs-folder-plus'></i>
                            Create New Folder
                        </h2>
                        <p>Organize your files with custom folders</p>
                    </div>
                    <button onclick="closeCreateFolderModal()" class="modal-close-btn">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <form id="createFolderForm" class="modal-form">
                <!-- Folder Name -->
                <div class="form-group">
                    <label>
                        <i class='bx bxs-folder'></i>
                        Folder Name
                    </label>
                    <input type="text" name="folder_name" placeholder="e.g., IPCR Accomplishment" required maxlength="100">
                    <small>Choose any name that fits your documents, such as "IPCR Accomplishment".</small>
                </div>

                <!-- Folder Description -->
                <div class="form-group">
                    <label>
                        <i class='bx bxs-note'></i>
                        Description (Optional)
                    </label>
                    <textarea name="folder_description" placeholder="Describe the purpose of this folder..." rows="3"></textarea>
                </div>

                <!-- Folder Color -->
                <div class="form-group">
                    <label>
                        <i class='bx bxs-color-fill'></i>
                        Folder Color
                    </label>
                    <div class="color-picker" role="radiogroup" aria-label="Choose a folder color">
                        <input type="radio" name="folder_color" value="#3b82f6" id="color-blue">
                        <label for="color-blue" title="Blue" aria-label="Blue" style="background-color: #3b82f6;"></label>
                        
                        <input type="radio" name="folder_color" value="#10b981" id="color-green" checked>
                        <label for="color-green" title="Green" aria-label="Green" style="background-color: #10b981;"></label>
                        
                        <input type="radio" name="folder_color" value="#f59e0b" id="color-yellow">
                        <label for="color-yellow" title="Amber" aria-label="Amber" style="background-color: #f59e0b;"></label>
                        
                        <input type="radio" name="folder_color" value="#ef4444" id="color-red">
                        <label for="color-red" title="Red" aria-label="Red" style="background-color: #ef4444;"></label>
                        
                        <input type="radio" name="folder_color" value="#8b5cf6" id="color-purple">
                        <label for="color-purple" title="Purple" aria-label="Purple" style="background-color: #8b5cf6;"></label>
                        
                        <input type="radio" name="folder_color" value="#06b6d4" id="color-cyan">
                        <label for="color-cyan" title="Cyan" aria-label="Cyan" style="background-color: #06b6d4;"></label>
                        
                        <input type="radio" name="folder_color" value="#f97316" id="color-orange">
                        <label for="color-orange" title="Orange" aria-label="Orange" style="background-color: #f97316;"></label>
                        
                        <input type="radio" name="folder_color" value="#84cc16" id="color-lime">
                        <label for="color-lime" title="Lime" aria-label="Lime" style="background-color: #84cc16;"></label>

                        <input type="radio" name="folder_color" value="#14532d" id="color-forest">
                        <label for="color-forest" title="Forest Green" aria-label="Forest Green" style="background-color: #14532d;"></label>

                        <input type="radio" name="folder_color" value="#d4a017" id="color-gold">
                        <label for="color-gold" title="Gold" aria-label="Gold" style="background-color: #d4a017;"></label>

                        <input type="radio" name="folder_color" value="#0f766e" id="color-teal">
                        <label for="color-teal" title="Teal" aria-label="Teal" style="background-color: #0f766e;"></label>

                        <input type="radio" name="folder_color" value="#64748b" id="color-slate">
                        <label for="color-slate" title="Slate" aria-label="Slate" style="background-color: #64748b;"></label>
                    </div>
                </div>

                <!-- Folder Icon -->
                <div class="form-group">
                    <label>
                        <i class='bx bxs-star'></i>
                        Folder Icon
                    </label>
                    <div class="icon-picker" role="radiogroup" aria-label="Choose a folder icon">
                        <input type="radio" name="folder_icon" value="bxs-folder" id="icon-folder" checked>
                        <label for="icon-folder" title="Folder" aria-label="Folder"><i class='bx bxs-folder'></i></label>
                        
                        <input type="radio" name="folder_icon" value="bxs-briefcase" id="icon-briefcase">
                        <label for="icon-briefcase" title="Briefcase" aria-label="Briefcase"><i class='bx bxs-briefcase'></i></label>
                        
                        <input type="radio" name="folder_icon" value="bxs-book" id="icon-book">
                        <label for="icon-book" title="Book" aria-label="Book"><i class='bx bxs-book'></i></label>
                        
                        <input type="radio" name="folder_icon" value="bxs-file" id="icon-file">
                        <label for="icon-file" title="File" aria-label="File"><i class='bx bxs-file'></i></label>
                        
                        <input type="radio" name="folder_icon" value="bxs-heart" id="icon-heart">
                        <label for="icon-heart" title="Favorite" aria-label="Favorite"><i class='bx bxs-heart'></i></label>
                        
                        <input type="radio" name="folder_icon" value="bxs-star" id="icon-star">
                        <label for="icon-star" title="Star" aria-label="Star"><i class='bx bxs-star'></i></label>
                        
                        <input type="radio" name="folder_icon" value="bxs-cog" id="icon-cog">
                        <label for="icon-cog" title="Settings" aria-label="Settings"><i class='bx bxs-cog'></i></label>
                        
                        <input type="radio" name="folder_icon" value="bxs-shield" id="icon-shield">
                        <label for="icon-shield" title="Shield" aria-label="Shield"><i class='bx bxs-shield'></i></label>

                        <input type="radio" name="folder_icon" value="bxs-archive" id="icon-archive">
                        <label for="icon-archive" title="Archive" aria-label="Archive"><i class='bx bxs-archive'></i></label>

                        <input type="radio" name="folder_icon" value="bxs-bookmark" id="icon-bookmark">
                        <label for="icon-bookmark" title="Bookmark" aria-label="Bookmark"><i class='bx bxs-bookmark'></i></label>

                        <input type="radio" name="folder_icon" value="bxs-calendar" id="icon-calendar">
                        <label for="icon-calendar" title="Calendar" aria-label="Calendar"><i class='bx bxs-calendar'></i></label>

                        <input type="radio" name="folder_icon" value="bxs-collection" id="icon-collection">
                        <label for="icon-collection" title="Collection" aria-label="Collection"><i class='bx bxs-collection'></i></label>

                        <input type="radio" name="folder_icon" value="bxs-graduation" id="icon-graduation">
                        <label for="icon-graduation" title="Graduation" aria-label="Graduation"><i class='bx bxs-graduation'></i></label>

                        <input type="radio" name="folder_icon" value="bxs-notepad" id="icon-notepad">
                        <label for="icon-notepad" title="Notes" aria-label="Notes"><i class='bx bxs-notepad'></i></label>

                        <input type="radio" name="folder_icon" value="bxs-pie-chart" id="icon-chart">
                        <label for="icon-chart" title="Chart" aria-label="Chart"><i class='bx bxs-pie-chart'></i></label>

                        <input type="radio" name="folder_icon" value="bxs-purchase-tag" id="icon-tag">
                        <label for="icon-tag" title="Tag" aria-label="Tag"><i class='bx bxs-purchase-tag'></i></label>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" onclick="closeCreateFolderModal()" class="btn-cancel">
                        Cancel
                    </button>
                    <button type="submit" class="btn-create">
                        <i class='bx bxs-folder-plus'></i>
                        Create Folder
                    </button>
                </div>
                
                <div class="modal-info">
                    <p>
                        <i class='bx bx-info-circle'></i>
                        Your folder will be created in your department section for better organization.
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s ease;">
        <div id="modalContainer" style="background: white; border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: scale(0.9) translateY(20px); transition: all 0.3s ease; font-family: 'Poppins', sans-serif;">
        
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 24px; border-radius: 16px 16px 0 0; position: relative; overflow: hidden; font-family: 'Poppins', sans-serif;">
                <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255, 255, 255, 0.1); border-radius: 50%;"></div>
                <div style="position: absolute; bottom: -30px; left: -30px; width: 80px; height: 80px; background: rgba(255, 255, 255, 0.1); border-radius: 50%;"></div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 1;">
                    <div>
                        <h2 style="margin: 0; font-size: 24px; font-weight: 600; margin-bottom: 4px;">
                            <i class='bx bxs-cloud-upload' style="margin-right: 8px; font-size: 28px;"></i>
                            Upload File
                        </h2>
                        <p style="margin: 0; opacity: 0.9; font-size: 14px;">Share your documents with your department</p>
                    </div>
                    <button onclick="closeUploadModal()" style="background: rgba(255, 255, 255, 0.2); border: none; border-radius: 8px; padding: 8px; color: white; cursor: pointer; transition: all 0.3s ease; font-size: 20px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <form id="uploadForm" enctype="multipart/form-data" style="padding: 0;">
                <input type="hidden" name="custom_folder_id" value="">
                
                <!-- Department Selection -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-building' style="color: #10b981; margin-right: 8px;"></i>
                        Department
                    </label>
                    <select name="department" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e5e7eb'">
                        <option value="<?php echo $userDepartmentId; ?>" selected>
                            <?php echo htmlspecialchars($userDepartment['department_name'] ?? 'Your Department') . ' (' . ($userDepartment['department_code'] ?? '') . ')'; ?>
                        </option>
                    </select>
                </div>

                <!-- Category Selection -->
                <div id="uploadCategorySelection" style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-category' style="color: #10b981; margin-right: 8px;"></i>
                        File Category
                    </label>
                    <select name="category" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e5e7eb'">
                        <option value="">Select a category...</option>
                        <?php foreach ($fileCategories as $key => $category): ?>
                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="customFolderUploadContext" hidden style="padding: 16px 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; margin-bottom: 6px;">Folder</label>
                    <div id="customFolderUploadName"></div>
                </div>

                <!-- Academic Year Selection -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-calendar-alt' style="color: #10b981; margin-right: 8px;"></i>
                        Academic Year
                    </label>
                    <select name="academic_year" required 
                        style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease;" 
                        onfocus="this.style.borderColor='#10b981'" 
                        onblur="this.style.borderColor='#e5e7eb'">
                        <option value="">Select academic year...</option>
                    </select>
                    <small style="display: flex; align-items: center; gap: 4px; margin-top: 8px; color: #6b7280; font-size: 12px;">
                        <i class='bx bx-info-circle' style="color: #6b7280; font-size: 14px;"></i>
                        Academic year follows the format: Start Year - End Year (e.g., 2024-2025)
                    </small>
                </div>

                <!-- Semester Selection -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-calendar' style="color: #10b981; margin-right: 8px;"></i>
                        Academic Semester
                    </label>
                    <div class="semester-selection-container">
                        <!-- First Semester Option -->
                        <input type="radio" name="semester" value="first" id="semester-first" required>
                        <label for="semester-first">
                            <div class="semester-option">
                                <i class='bx bxs-calendar-check semester-icon'></i>
                                <div class="semester-info">
                                    <h4>First Semester</h4>
                                </div>
                            </div>
                        </label>
                        
                        <!-- Second Semester Option -->
                        <input type="radio" name="semester" value="second" id="semester-second" required>
                        <label for="semester-second">
                            <div class="semester-option">
                                <i class='bx bxs-calendar-star semester-icon'></i>
                                <div class="semester-info">
                                    <h4>Second Semester</h4>
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Academic Period Summary -->
                    <div id="academicPeriodSummary" class="academic-period-summary"></div>
                </div>

                <!-- File Upload Area -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-file-plus' style="color: #10b981; margin-right: 8px;"></i>
                        Upload File
                    </label>
                    
                    <div id="dropZone" style="border: 2px dashed #10b981; border-radius: 12px; padding: 40px 20px; text-align: center; background: linear-gradient(135deg, #f0fdf4, #ecfdf5); cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden;">
                        <input type="file" id="fileInput" name="files[]" multiple accept="*" style="display: none;">
                        
                        <div id="uploadPrompt">
                            <i class='bx bxs-cloud-upload' style="font-size: 48px; color: #10b981; margin-bottom: 16px; display: block;"></i>
                            <h3 style="margin: 0 0 8px 0; color: #065f46; font-size: 18px; font-weight: 600;">Drop files here or click to browse</h3>
                            <p style="margin: 0; color: #059669; font-size: 14px; opacity: 0.8;">Support for PDF, DOC, XLS, PPT, Images and more</p>
                            <div style="margin-top: 16px; display: inline-flex; align-items: center; background: rgba(16, 185, 129, 0.1); padding: 8px 16px; border-radius: 20px; font-size: 12px; color: #065f46; font-weight: 500;">
                                <i class='bx bx-info-circle' style="margin-right: 4px; margin-top: 0px;"></i>
                                Max file size: 50MB per file
                            </div>
                        </div>
                        
                        <div id="filePreview" style="display: none; text-align: left;"></div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div id="uploadProgress" style="display: none; margin-top: 16px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 14px; font-weight: 500; color: #374151;">Uploading files...</span>
                            <span id="progressPercent" style="font-size: 14px; font-weight: 500; color: #10b981;">0%</span>
                        </div>
                        <div style="width: 100%; background: #f3f4f6; border-radius: 8px; height: 8px; overflow: hidden;">
                            <div id="progressBar" style="width: 0%; background: linear-gradient(90deg, #10b981, #059669); height: 100%; border-radius: 8px; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                </div>

                <!-- File Description -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-note' style="color: #10b981; margin-right: 8px;"></i>
                        Description (Optional)
                    </label>
                    <textarea id="fileDescription" name="description" placeholder="Add a description for your files..." style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; resize: vertical; min-height: 80px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 3px rgba(16, 185, 129, 0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'"></textarea>
                </div>

                <!-- File Tags -->
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px;">
                        <i class='bx bxs-tag' style="color: #10b981; margin-right: 8px;"></i>
                        Tags
                    </label>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                        <button type="button" onclick="addTag('Curriculum')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Curriculum</button>
                        <button type="button" onclick="addTag('Research')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Research</button>
                        <button type="button" onclick="addTag('Guidelines')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Guidelines</button>
                        <button type="button" onclick="addTag('Reports')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Reports</button>
                    </div>
                    <div id="selectedTags" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;"></div>
                    <input type="text" id="customTag" placeholder="Add custom tag..." style="width: 100%; padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e5e7eb'">
                    <input type="hidden" id="tagsInput" name="tags" value="">
                </div>

                <!-- Modal Footer -->
                <div style="padding: 24px; background: #f9fafb; border-radius: 0 0 16px 16px;">
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" onclick="closeUploadModal()" style="background: #f3f4f6; color: #374151; border: none; border-radius: 8px; padding: 12px 24px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                            Cancel
                        </button>
                        <button type="submit" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 8px; padding: 12px 24px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <i class='bx bxs-cloud-upload' style="margin-right: 8px;"></i>
                            Upload Files
                        </button>
                    </div>
                    
                    <div style="margin-top: 16px; padding: 12px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border-left: 4px solid #10b981;">
                        <p style="margin: 0; font-size: 12px; color: #065f46; line-height: 1.4;">
                            <i class='bx bx-shield-check' style="margin-right: 4px;"></i>
                            Your files will be securely stored and organized in your department for easy access.
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include 'components/sidebar.html'; ?>
    <?php include 'components/file_preview_modal.html'; ?>

    <!-- Content -->
    <section id="content">
        <?php include 'components/navbar.html'; ?>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Department Folders</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="dashboard.php">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                        <a class="" href="#" style="color: #28a745;">Department Folders</a>
                        </li>
                    </ul>
                </div>
                <div class="action-buttons">
                    <button onclick="openCreateFolderModal()" class="create-folder-btn">
                        <i class='bx bxs-folder-plus'></i>
                        <span class="text">Create Folder</span>
                    </button>
                    <button onclick="openUploadModal()" class="upload-btn">
                        <i class='bx bxs-cloud-upload'></i>
                        <span class="text">Upload File</span>
                    </button>
                </div>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="search-bar">
                    <input type="text" placeholder="Search in departments..." id="departmentSearch">
                    <i class='bx bx-search'></i>
                </div>
            </div>

<?php if (!empty($departments)): ?>
    <?php foreach ($departments as $dept): 
        $config = $departmentConfig[$dept['department_code']] ?? ['icon' => 'bxs-building', 'color' => '#6b7280'];
    ?>
        <div class="department-card" data-department="<?php echo $dept['id']; ?>">
            <div class="department-header" onclick="toggleDepartment('<?php echo $dept['id']; ?>')">
                <div class="department-icon" style="background: linear-gradient(135deg, <?php echo $config['color']; ?>, <?php echo adjustBrightness($config['color'], -20); ?>);">
                    <i class='bx <?php echo $config['icon']; ?>'></i>
                </div>
                <div class="department-info">
                    <div class="department-name">
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </div>
                    <div class="department-code"><?php echo $dept['department_code']; ?></div>
                    <div class="department-stats">
                        <span><i class='bx bx-folder'></i> 0 folders</span>
                        <span><i class='bx bx-file'></i> 0 files</span>
                    </div>
                </div>
                <div class="expand-controls">
                    <i class='bx bx-chevron-right expand-icon' id="icon-<?php echo $dept['id']; ?>"></i>
                </div>
            </div>
            
            <div class="department-content" id="content-<?php echo $dept['id']; ?>">
                <!-- Document categories and user-created folder drawers share one workspace. -->
                <div class="predefined-categories-section">
                    <div class="section-header">
                        <h3>
                            <i class='bx bxs-category'></i>
                            Documents
                        </h3>
                    </div>
                    <div class="categories-grid" id="categories-<?php echo $dept['id']; ?>">
                        <?php foreach ($fileCategories as $categoryKey => $category): 
                            // CRITICAL: Create unique IDs for each category in each department
                            $uniqueId = $dept['id'] . '-' . $categoryKey;
                        ?>
                            <div class="category-card" data-category="<?php echo $categoryKey; ?>" onclick="toggleCategory('<?php echo $dept['id']; ?>', '<?php echo $categoryKey; ?>')">
                                <div class="category-header">
                                    <div class="category-icon" style="background: linear-gradient(135deg, <?php echo $category['color']; ?>, <?php echo adjustBrightness($category['color'], -20); ?>);">
                                        <i class='bx <?php echo $category['icon']; ?>'></i>
                                    </div>
                                    <div class="category-info">
                                        <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                                        <div class="category-count">0 files</div>
                                    </div>
                                    <!-- FIXED: Each category has its own unique expand icon -->
                                    <i class='bx bx-chevron-right expand-icon' id="icon-<?php echo $uniqueId; ?>"></i>
                                </div>
                                
                                <!-- FIXED: Each category has its own unique semester content -->
                                <div class="category-semester-content" id="category-content-<?php echo $uniqueId; ?>">
                                    <div class="semester-tabs">
                                        <!-- FIXED: Each semester tab has unique onclick and proper event handling -->
                                        <button class="semester-tab active" 
                                                type="button"
                                                onclick="showCategorySemester('<?php echo $dept['id']; ?>', '<?php echo $categoryKey; ?>', 'first', event)">
                                            <i class='bx bxs-folder'></i> First Semester
                                        </button>
                                        <button class="semester-tab" 
                                                type="button"
                                                onclick="showCategorySemester('<?php echo $dept['id']; ?>', '<?php echo $categoryKey; ?>', 'second', event)">
                                            <i class='bx bxs-folder'></i> Second Semester
                                        </button>
                                    </div>
                                    
                                    <!-- FIXED: Each files grid has unique ID for independent state -->
                                    <div class="files-grid" id="files-<?php echo $uniqueId; ?>-first">
                                        <div class="empty-state">
                                            <i class='bx bx-folder-open'></i>
                                            <p>No files in First Semester</p>
                                            <small>Files uploaded to this category and semester will appear here</small>
                                        </div>
                                    </div>
                                    
                                    <!-- FIXED: Second semester with unique ID and hidden by default -->
                                    <div class="files-grid" id="files-<?php echo $uniqueId; ?>-second" style="display: none;">
                                        <div class="empty-state">
                                            <i class='bx bx-folder-open'></i>
                                            <p>No files in Second Semester</p>
                                            <small>Files uploaded to this category and semester will appear here</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <i class='bx bx-error-circle'></i>
        <h3>No Department Assigned</h3>
        <p>Please contact your administrator to assign you to a department.</p>
    </div>
<?php endif; ?>
            </div>
        </main>
    </section>

    <script>
        window.userDepartmentId = <?php echo json_encode($userDepartmentId); ?>;
        window.fileCategories = <?php echo json_encode($fileCategories); ?>;
        window.userId = <?php echo json_encode($currentUser['id']); ?>;
    </script>
    <script src="assets/js/pages/folders/academic_period.js"></script>
    <script src="assets/js/pages/folders/category.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/folders/category.js'); ?>"></script>
    <script src="assets/js/pages/folders/department.js"></script>
    <script src="assets/js/pages/folders/file_rendering.js"></script>
    <script src="assets/js/pages/folders/main.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/folders/main.js'); ?>"></script>
    <script src="assets/js/pages/folders/ui_components.js"></script>
    <script src="assets/js/pages/folders/upload_handler.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/folders/upload_handler.js'); ?>"></script>
    <script src="assets/js/pages/folders/search_functionality.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/folders/search_functionality.js'); ?>"></script>
    <script src="assets/js/pages/folders/modal.js"></script>
    <script src="assets/js/pages/folders/folder_creation.js?v=<?php echo filemtime(__DIR__ . '/assets/js/pages/folders/folder_creation.js'); ?>"></script>
    <script src="assets/js/components/navbar.js?v=<?php echo filemtime(__DIR__ . '/assets/js/components/navbar.js'); ?>"></script>
    <script src="assets/js/components/file_preview_modal.js"></script>

</body>
</html>

<?php
// Helper function to adjust color brightness
function adjustBrightness($hex, $percent) {
    // Remove the hash if present
    $hex = ltrim($hex, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    // Convert back to hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}
?>
