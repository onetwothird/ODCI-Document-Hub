<?php
header('Content-Type: application/json');
require_once '../../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid user session']);
    exit();
}

// Check if user is approved
if (!$currentUser['is_approved']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Account not approved']);
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Validate required fields
    if (!isset($_POST['category']) || !isset($_POST['academic_year']) || !isset($_POST['semester'])) {
        throw new Exception('Missing required fields');
    }

    $category = trim($_POST['category']);
    $academicYear = trim($_POST['academic_year']);
    $semester = trim($_POST['semester']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Validate file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['file'];
    $originalFileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmpName = $file['tmp_name'];
    $mimeType = $file['type'];

    // Validate file size (50MB max)
    $maxFileSize = 50 * 1024 * 1024; // 50MB
    if ($fileSize > $maxFileSize) {
        throw new Exception('File size exceeds 50MB limit');
    }

    // Validate file extension
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar'];
    $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions));
    }

    // Map category keys to proper file types (matching your database)
    $categoryMap = [
        'ipcr_accomplishment' => 'IPCR Accomplishment',
        'ipcr_target' => 'IPCR Target',
        'workload' => 'Workload',
        'course_syllabus' => 'Course Syllabus',
        'syllabus_acceptance' => 'Course Syllabus Acceptance Form',
        'exam' => 'Exam',
        'tos' => 'TOS',
        'class_record' => 'Class Record',
        'grading_sheet' => 'Grading Sheet',
        'attendance_sheet' => 'Attendance Sheet',
        'stakeholder_feedback' => 'Stakeholder\'s Feedback Form w/ Summary',
        'consultation' => 'Consultation',
        'lecture' => 'Lecture',
        'activities' => 'Activities',
        'consultation_log' => 'Consultation Log Sheet Form',
        'exam_acknowledgement' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form' 
    ];

    // Get proper file type
    $fileType = isset($categoryMap[$category]) ? $categoryMap[$category] : $category;

    // Validate academic year format
    if (!is_numeric($academicYear) || $academicYear < 2020 || $academicYear > 2030) {
        throw new Exception('Invalid academic year');
    }

    // Validate semester
    $validSemesters = ['1st Semester', '2nd Semester'];
    if (!in_array($semester, $validSemesters)) {
        throw new Exception('Invalid semester selected');
    }

    // Create upload directory structure
    $uploadBasePath = '../../../uploads/documents';
    $yearPath = $uploadBasePath . '/' . $academicYear;
    $semesterFolder = strtolower(str_replace(' ', '_', $semester));
    $fullUploadPath = $yearPath . '/' . $semesterFolder;

    // Create directories if they don't exist
    if (!file_exists($uploadBasePath)) {
        mkdir($uploadBasePath, 0755, true);
    }
    if (!file_exists($yearPath)) {
        mkdir($yearPath, 0755, true);
    }
    if (!file_exists($fullUploadPath)) {
        mkdir($fullUploadPath, 0755, true);
    }

    // Generate unique filename
    $timestamp = time();
    $randomString = bin2hex(random_bytes(8));
    $newFileName = $academicYear . '_' . $semesterFolder . '_' . $category . '_' . $currentUser['id'] . '_' . $timestamp . '_' . $randomString . '.' . $fileExtension;
    
    $filePath = $fullUploadPath . '/' . $newFileName;
    $relativeFilePath = '../../../uploads/documents/' . $academicYear . '/' . $semesterFolder . '/' . $newFileName;

    // Move uploaded file
    if (!move_uploaded_file($fileTmpName, $filePath)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Handle submission_id - we need to ensure this is never null
    $submissionId = null;

    // Check if faculty_document_submissions table exists and handle accordingly
    try {
        $checkSubmissionTable = $pdo->query("SHOW TABLES LIKE 'faculty_document_submissions'");
        if ($checkSubmissionTable->rowCount() > 0) {
            // Table exists, try to find or create submission
            $stmt = $pdo->prepare("
                SELECT id FROM faculty_document_submissions 
                WHERE faculty_id = ? AND document_type = ? AND academic_year = ? AND semester = ?
                LIMIT 1
            ");
            $stmt->execute([$currentUser['id'], $fileType, $academicYear, $semester]);
            $existingSubmission = $stmt->fetch();
            
            if ($existingSubmission) {
                $submissionId = $existingSubmission['id'];
            } else {
                // Create new submission record
                $stmt = $pdo->prepare("
                    INSERT INTO faculty_document_submissions (
                        faculty_id, document_type, semester, academic_year, 
                        submitted_by, submitted_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                if ($stmt->execute([$currentUser['id'], $fileType, $semester, $academicYear, $currentUser['id']])) {
                    $submissionId = $pdo->lastInsertId();
                } else {
                    throw new Exception('Failed to create submission record');
                }
            }
        } else {
            // If faculty_document_submissions table doesn't exist, create a default submission record
            throw new Exception('Faculty document submissions table not found. Please contact administrator.');
        }
    } catch (Exception $e) {
        // If we can't get a submission_id, we can't proceed
        if ($submissionId === null) {
            throw new Exception('Unable to create submission record: ' . $e->getMessage());
        }
    }

    // Ensure we have a valid submission_id before proceeding
    if ($submissionId === null) {
        throw new Exception('Failed to obtain valid submission ID');
    }

    // Insert file record into document_files table
    $stmt = $pdo->prepare("
        INSERT INTO document_files (
            submission_id, file_name, file_path, file_size, file_type,
            academic_year, semester_period, uploaded_by, description,
            uploaded_at, mime_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");

    $success = $stmt->execute([
        $submissionId,
        $originalFileName,
        $relativeFilePath,
        $fileSize,
        $fileType,
        $academicYear,
        $semester,
        $currentUser['id'],
        $description,
        $mimeType
    ]);

    if (!$success) {
        // If database insert fails, remove the uploaded file
        unlink($filePath);
        throw new Exception('Failed to save file information to database');
    }

    // Log the successful upload
    error_log("File uploaded successfully: {$originalFileName} by user {$currentUser['id']}");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'data' => [
            'file_id' => $pdo->lastInsertId(),
            'file_name' => $originalFileName,
            'file_size' => $fileSize,
            'category' => $category,
            'file_type' => $fileType,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'uploaded_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Upload error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>