<?php
// handlers/upload_document.php - Handler for uploading documents with year-semester support
require_once '../../../includes/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || !$currentUser['is_approved']) {
    header('Location: ../login.php');
    exit();
}

// Get parameters
$docType = $_GET['doc_type'] ?? '';
$selectedYear = (int)($_GET['year'] ?? date('Y'));
$selectedSemester = $_GET['semester'] ?? (date('n') >= 6 && date('n') <= 11 ? '1st Semester' : '2nd Semester');

if (empty($docType)) {
    header('Location: ../folders.php?error=invalid_document_type');
    exit();
}

// Initialize variables
$error = null;
$message = null;
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    try {
        // Debug: Check if files were received
        if (!isset($_FILES['document_files'])) {
            throw new Exception('No files received by server. Check form encoding and file input name.');
        }

        // Check if any files were actually selected
        $hasFiles = false;
        if (is_array($_FILES['document_files']['name'])) {
            foreach ($_FILES['document_files']['name'] as $name) {
                if (!empty($name)) {
                    $hasFiles = true;
                    break;
                }
            }
        }

        if (!$hasFiles) {
            throw new Exception('Please select at least one file to upload.');
        }
        
        // Check upload directory and create if needed
        $uploadDir = '../../../uploads/documents/' . $selectedYear . '/' . str_replace(' ', '_', strtolower($selectedSemester)) . '/';
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Failed to create upload directory: ' . $uploadDir);
            }
        }

        if (!is_writable($uploadDir)) {
            throw new Exception('Upload directory is not writable: ' . $uploadDir);
        }
        
        $uploadedFiles = [];
        
        // Start database transaction
        $pdo->beginTransaction();
        
        try {
            // Get or create faculty document submission record
            $stmt = $pdo->prepare("
                SELECT id FROM faculty_document_submissions 
                WHERE faculty_id = ? AND document_type = ? AND semester = ? AND academic_year = ?
            ");
            $stmt->execute([$currentUser['id'], $docType, $selectedSemester, $selectedYear]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                // Create new submission record with current timestamp
                $stmt = $pdo->prepare("
                    INSERT INTO faculty_document_submissions 
                    (faculty_id, document_type, semester, academic_year, submitted_by, submitted_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$currentUser['id'], $docType, $selectedSemester, $selectedYear, $currentUser['id']]);
                $submissionId = $pdo->lastInsertId();
                
                // Also add to submission history
                $stmt = $pdo->prepare("
                    INSERT INTO document_submission_history 
                    (faculty_id, document_type, semester, status, created_at) 
                    VALUES (?, ?, ?, 'submitted', NOW())
                ");
                $stmt->execute([$currentUser['id'], $docType, $selectedSemester]);
            } else {
                $submissionId = $submission['id'];
                
                // Update submission timestamp if record already exists
                $stmt = $pdo->prepare("
                    UPDATE faculty_document_submissions 
                    SET submitted_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$submissionId]);
            }
            
            // Process each uploaded file
            $fileCount = count($_FILES['document_files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                $fileName = $_FILES['document_files']['name'][$i];
                
                if (empty($fileName)) continue;
                
                $fileSize = $_FILES['document_files']['size'][$i];
                $fileTmp = $_FILES['document_files']['tmp_name'][$i];
                $fileError = $_FILES['document_files']['error'][$i];
                
                // Check for upload errors
                switch ($fileError) {
                    case UPLOAD_ERR_OK:
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        continue 2; // Skip this file
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errors[] = "File too large: $fileName";
                        continue 2;
                    case UPLOAD_ERR_PARTIAL:
                        $errors[] = "File upload incomplete: $fileName";
                        continue 2;
                    default:
                        $errors[] = "Upload error for file: $fileName (Error code: $fileError)";
                        continue 2;
                }
                
                // Check file size (50MB limit)
                if ($fileSize > 50 * 1024 * 1024) {
                    $errors[] = "File too large: $fileName (Max 50MB, got " . round($fileSize / 1024 / 1024, 2) . "MB)";
                    continue;
                }
                
                // Get file extension and validate
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'txt'];
                
                if (!in_array($fileExt, $allowedTypes)) {
                    $errors[] = "Invalid file type for: $fileName (.$fileExt not allowed)";
                    continue;
                }
                
                // Generate unique filename
                $newFileName = $selectedYear . '_' . str_replace(' ', '_', $selectedSemester) . '_' . 
                              str_replace([' ', "'", '"', '/', '\\'], ['_', '', '', '_', '_'], $docType) . '_' . 
                              $currentUser['id'] . '_' . time() . '_' . uniqid() . '.' . $fileExt;
                
                $filePath = $uploadDir . $newFileName;
                
                // Check if temp file exists and is readable
                if (!is_uploaded_file($fileTmp)) {
                    $errors[] = "Invalid uploaded file: $fileName";
                    continue;
                }
                
                // Move uploaded file
                if (move_uploaded_file($fileTmp, $filePath)) {
                    // Verify file was moved successfully
                    if (!file_exists($filePath)) {
                        $errors[] = "File move verification failed for: $fileName";
                        continue;
                    }
                    
                    // Get actual file size after move
                    $actualSize = filesize($filePath);
                    
                    // Insert file record
                    $stmt = $pdo->prepare("
                        INSERT INTO document_files 
                        (submission_id, file_name, file_path, file_size, file_type, 
                         academic_year, semester_period, uploaded_by, description, uploaded_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $description = $_POST['file_descriptions'][$i] ?? '';
                    $success = $stmt->execute([
                        $submissionId, 
                        $fileName, 
                        $filePath, 
                        $actualSize, 
                        $docType,
                        $selectedYear, 
                        $selectedSemester, 
                        $currentUser['id'], 
                        $description
                    ]);
                    
                    if ($success) {
                        $uploadedFiles[] = $fileName;
                    } else {
                        $errors[] = "Database error for file: $fileName";
                        // Remove the uploaded file since DB insert failed
                        unlink($filePath);
                    }
                } else {
                    $errors[] = "Failed to move uploaded file: $fileName (Check permissions)";
                }
            }
            
            // If we have successfully uploaded files, commit the transaction
            if (!empty($uploadedFiles)) {
                // Log activity
                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, resource_type, description, created_at)
                    VALUES (?, 'document_upload', 'file', ?, NOW())
                ");
                $stmt->execute([
                    $currentUser['id'],
                    "Uploaded " . count($uploadedFiles) . " file(s) for $docType ($selectedYear - $selectedSemester)"
                ]);
                
                $pdo->commit();
                
                if (empty($errors)) {
                    // All files uploaded successfully
                    header("Location: ../folders.php?success=upload_complete&count=" . count($uploadedFiles));
                    exit();
                } else {
                    // Some files uploaded, but there were errors
                    $message = count($uploadedFiles) . " file(s) uploaded successfully: " . implode(', ', $uploadedFiles);
                }
            } else {
                // No files were uploaded
                $pdo->rollback();
                if (empty($errors)) {
                    $error = "No files were uploaded. Please check your file selection.";
                } else {
                    $error = "Failed to upload any files.";
                }
            }
            
        } catch (Exception $dbError) {
            $pdo->rollback();
            throw new Exception("Database error: " . $dbError->getMessage());
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollback();
        }
        $error = $e->getMessage();
        error_log("Upload error: " . $error);
    }
}

// Get existing files for this document type and period
$existingFiles = [];
try {
    $stmt = $pdo->prepare("
        SELECT df.*, fds.semester, fds.academic_year 
        FROM document_files df
        INNER JOIN faculty_document_submissions fds ON df.submission_id = fds.id
        WHERE df.uploaded_by = ? AND fds.document_type = ? AND fds.academic_year = ? AND fds.semester = ?
        ORDER BY df.uploaded_at DESC
    ");
    $stmt->execute([$currentUser['id'], $docType, $selectedYear, $selectedSemester]);
    $existingFiles = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching existing files: " . $e->getMessage());
}

// Get PHP configuration for debugging
$maxFileSize = ini_get('upload_max_filesize');
$maxPostSize = ini_get('post_max_size');
$maxFiles = ini_get('max_file_uploads');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload <?php echo htmlspecialchars($docType); ?> - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        .upload-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .period-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: border-color 0.3s ease;
            cursor: pointer;
            background: #fafafa;
        }
        
        .file-upload-area:hover,
        .file-upload-area.drag-over {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .file-upload-area i {
            font-size: 3em;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .file-input {
            display: none;
        }
        
        .selected-files {
            margin-top: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .file-info i {
            font-size: 1.5em;
            margin-right: 10px;
            color: #667eea;
        }
        
        .file-details {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            color: #333;
        }
        
        .file-size {
            font-size: 12px;
            color: #666;
        }
        
        .file-description {
            width: 200px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 0 10px;
            font-size: 12px;
        }
        
        .remove-file {
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
            border-radius: 3px;
            transition: background 0.3s;
        }
        
        .remove-file:hover {
            background: rgba(220, 53, 69, 0.1);
        }
        
        .existing-files {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .existing-file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .existing-file-item:last-child {
            border-bottom: none;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .upload-guidelines {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .upload-guidelines h4 {
            color: #17a2b8;
            margin-bottom: 10px;
        }
        
        .upload-guidelines ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .upload-guidelines li {
            margin: 5px 0;
            color: #0c5460;
        }

        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 12px;
            color: #6c757d;
        }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
    <div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="margin: 0; color: #333;">Upload Document</h1>
                <p style="margin: 5px 0 0 0; color: #666;">Upload files for <?php echo htmlspecialchars($docType); ?></p>
            </div>
            <a href="../folders.php" class="btn btn-secondary">
                <i class='bx bx-arrow-back'></i> Back to Folders
            </a>
        </div>
        
        <!-- Period Information -->
        <div class="period-info">
            <h3 style="margin: 0 0 10px 0;">
                <i class='bx bx-calendar'></i> 
                <?php echo htmlspecialchars($selectedYear . ' - ' . $selectedSemester); ?>
            </h3>
            <p style="margin: 0; opacity: 0.9;">
                Document Type: <strong><?php echo htmlspecialchars($docType); ?></strong>
            </p>
        </div>

        <!-- Debug Information -->
        <div class="debug-info">
            <strong>Server Configuration:</strong>
            Max file size: <?php echo $maxFileSize; ?> | 
            Max POST size: <?php echo $maxPostSize; ?> | 
            Max files: <?php echo $maxFiles; ?> | 
            Upload directory: <?php echo realpath('../../../uploads/') ?: 'uploads/'; ?>
            <?php if (isset($_POST['upload_document'])): ?>
                <br><strong>Debug:</strong> Form submitted, processing...
            <?php endif; ?>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-warning">
                <i class='bx bx-error-circle'></i> <strong>Some files had issues:</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Upload Guidelines -->
        <div class="upload-guidelines">
            <h4><i class='bx bx-info-circle'></i> Upload Guidelines</h4>
            <ul>
                <li><strong>File Types:</strong> PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, TXT</li>
                <li><strong>Maximum Size:</strong> 50MB per file (Server limit: <?php echo $maxFileSize; ?>)</li>
                <li><strong>Multiple Files:</strong> You can upload up to <?php echo $maxFiles; ?> files at once</li>
                <li><strong>File Names:</strong> Use descriptive names for easy identification</li>
                <li><strong>Descriptions:</strong> Add optional descriptions to help organize your files</li>
            </ul>
        </div>

        <!-- Upload Form -->
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="upload_document" value="1">
            <div class="upload-container">
                <h3 style="margin-bottom: 20px; color: #333;">
                    <i class='bx bx-cloud-upload'></i> Select Files to Upload
                </h3>
                
                <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                    <i class='bx bx-cloud-upload'></i>
                    <h4>Click to select files or drag and drop</h4>
                    <p style="color: #666; margin: 10px 0 0 0;">
                        Select multiple files to upload for <?php echo htmlspecialchars($docType); ?>
                    </p>
                </div>
                
                <input type="file" id="fileInput" name="document_files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt" class="file-input">
                
                <div id="selectedFiles" class="selected-files" style="display: none;">
                    <h4 style="margin-bottom: 15px; color: #333;">Selected Files:</h4>
                    <div id="filesList"></div>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="clearFiles()" class="btn btn-secondary" id="clearBtn" style="display: none;">
                        <i class='bx bx-trash'></i> Clear All
                    </button>
                    <button type="submit" class="btn btn-primary" id="uploadBtn" style="display: none;">
                        <i class='bx bx-upload'></i> Upload Files
                    </button>
                </div>
            </div>
        </form>

        <!-- Existing Files -->
        <?php if (!empty($existingFiles)): ?>
        <div class="existing-files">
            <h3 style="margin-bottom: 20px; color: #333;">
                <i class='bx bx-file'></i> Previously Uploaded Files 
                <span style="font-size: 14px; color: #666; font-weight: normal;">
                    (<?php echo count($existingFiles); ?> files)
                </span>
            </h3>
            
            <?php foreach ($existingFiles as $file): ?>
            <div class="existing-file-item">
                <div class="file-info">
                    <i class='bx bx-file-blank'></i>
                    <div class="file-details">
                        <div class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></div>
                        <div class="file-size">
                            <?php echo formatFileSize($file['file_size']); ?> • 
                            Uploaded: <?php echo date('M j, Y g:i A', strtotime($file['uploaded_at'])); ?>
                            <?php if ($file['description']): ?>
                                <br><em><?php echo htmlspecialchars($file['description']); ?></em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div>
                    <a href="download_file.php?id=<?php echo $file['id']; ?>" class="btn" style="padding: 5px 10px; font-size: 12px; margin-right: 5px;">
                        <i class='bx bx-download'></i>
                    </a>
                    <a href="delete_file.php?id=<?php echo $file['id']; ?>&return=upload" class="btn" style="padding: 5px 10px; font-size: 12px; background: #dc3545; color: white;" 
                       onclick="return confirm('Are you sure you want to delete this file?')">
                        <i class='bx bx-trash'></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const filesList = document.getElementById('filesList');
        const selectedFiles = document.getElementById('selectedFiles');
        const uploadBtn = document.getElementById('uploadBtn');
        const clearBtn = document.getElementById('clearBtn');
        const uploadArea = document.querySelector('.file-upload-area');
        
        let selectedFilesList = [];

        // File input change event
        fileInput.addEventListener('change', handleFileSelect);
        
        // Drag and drop functionality
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleDrop);
        
        function handleFileSelect(e) {
            const files = Array.from(e.target.files);
            console.log('Files selected:', files.length);
            addFiles(files);
        }
        
        function handleDragOver(e) {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        }
        
        function handleDragLeave(e) {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
        }
        
        function handleDrop(e) {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            const files = Array.from(e.dataTransfer.files);
            console.log('Files dropped:', files.length);
            // Clear the file input and use only dropped files
            fileInput.value = '';
            selectedFilesList = [];
            addFiles(files);
        }
        
        function addFiles(files) {
            files.forEach(file => {
                // Check if file already exists
                if (!selectedFilesList.find(f => f.name === file.name && f.size === file.size)) {
                    selectedFilesList.push(file);
                }
            });
            
            console.log('Total selected files:', selectedFilesList.length);
            updateFilesList();
            updateFormState();
            updateFileInput();
        }
        
        function updateFileInput() {
            // Update the file input to match our selected files list
            const dt = new DataTransfer();
            selectedFilesList.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
            console.log('File input updated with', fileInput.files.length, 'files');
        }
        
        function updateFilesList() {
            if (selectedFilesList.length === 0) {
                selectedFiles.style.display = 'none';
                return;
            }
            
            selectedFiles.style.display = 'block';
            filesList.innerHTML = '';
            
            selectedFilesList.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <div class="file-info">
                        <i class='bx ${getFileIcon(file.name)}'></i>
                        <div class="file-details">
                            <div class="file-name">${file.name}</div>
                            <div class="file-size">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                    <input type="text" class="file-description" placeholder="Optional description..." 
                           name="file_descriptions[${index}]" maxlength="255">
                    <div class="remove-file" onclick="removeFile(${index})">
                        <i class='bx bx-x'></i>
                    </div>
                `;
                filesList.appendChild(fileItem);
            });
        }
        
        function removeFile(index) {
            console.log('Removing file at index:', index);
            selectedFilesList.splice(index, 1);
            updateFilesList();
            updateFormState();
            updateFileInput();
        }
        
        function clearFiles() {
            console.log('Clearing all files');
            selectedFilesList = [];
            fileInput.value = '';
            updateFilesList();
            updateFormState();
        }
        
        function updateFormState() {
            const hasFiles = selectedFilesList.length > 0;
            uploadBtn.style.display = hasFiles ? 'inline-block' : 'none';
            clearBtn.style.display = hasFiles ? 'inline-block' : 'none';
        }
        
        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'pdf': 'bxs-file-pdf',
                'doc': 'bxs-file-doc',
                'docx': 'bxs-file-doc',
                'xls': 'bxs-spreadsheet',
                'xlsx': 'bxs-spreadsheet',
                'ppt': 'bxs-slideshow',
                'pptx': 'bxs-slideshow',
                'jpg': 'bxs-image',
                'jpeg': 'bxs-image',
                'png': 'bxs-image',
                'txt': 'bxs-file-txt'
            };
            return iconMap[ext] || 'bxs-file-blank';
        }
        
        function formatFileSize(bytes) {
            const sizes = ['B', 'KB', 'MB', 'GB'];
            if (bytes === 0) return '0 B';
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Form validation and submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            console.log('Form submission started');
            console.log('Selected files count:', selectedFilesList.length);
            console.log('File input files count:', fileInput.files.length);
            
            if (selectedFilesList.length === 0) {
                e.preventDefault();
                alert('Please select at least one file to upload.');
                return false;
            }
            
            // Ensure file input matches our selected files
            updateFileInput();
            
            // Show loading state
            uploadBtn.innerHTML = '<i class="bx bx-loader bx-spin"></i> Uploading...';
            uploadBtn.disabled = true;
            clearBtn.disabled = true;
            
            // Log form data for debugging
            const formData = new FormData(this);
            console.log('FormData contents:');
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) {
                    console.log(key + ':', value.name, '(' + value.size + ' bytes)');
                } else {
                    console.log(key + ':', value);
                }
            }
            
            return true;
        });

        // Add some debugging for file input changes
        fileInput.addEventListener('change', function() {
            console.log('File input changed, files:', this.files.length);
        });
    </script>
</body>
</html>

<?php
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
