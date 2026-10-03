<?php
require_once '../../../includes/config.php';

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
    header('Location: ../submission_tracker.php?error=invalid_document_type');
    exit();
}

// Get files for this document type and period
$files = [];
try {
    $stmt = $pdo->prepare("
        SELECT df.*, fds.semester, fds.academic_year, fds.submitted_at
        FROM document_files df
        INNER JOIN faculty_document_submissions fds ON df.submission_id = fds.id
        WHERE df.uploaded_by = ? AND df.file_type = ? AND fds.academic_year = ? AND fds.semester = ?
        ORDER BY df.uploaded_at DESC
    ");
    $stmt->execute([$currentUser['id'], $docType, $selectedYear, $selectedSemester]);
    $files = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching files: " . $e->getMessage());
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    try {
        $fileId = (int)$_POST['file_id'];
        
        // Get file info before deletion
        $stmt = $pdo->prepare("SELECT * FROM document_files WHERE id = ? AND uploaded_by = ?");
        $stmt->execute([$fileId, $currentUser['id']]);
        $fileToDelete = $stmt->fetch();
        
        if ($fileToDelete) {
            // Delete physical file
            if (file_exists($fileToDelete['file_path'])) {
                unlink($fileToDelete['file_path']);
            }
            
            // Delete database record
            $stmt = $pdo->prepare("DELETE FROM document_files WHERE id = ? AND uploaded_by = ?");
            $stmt->execute([$fileId, $currentUser['id']]);
            
            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, resource_type, description, created_at)
                VALUES (?, 'document_delete', 'file', ?, NOW())
            ");
            $stmt->execute([
                $currentUser['id'],
                "Deleted file: {$fileToDelete['file_name']} for $docType ($selectedYear - $selectedSemester)"
            ]);
            
            $successMessage = "File deleted successfully.";
            
            // Refresh files list
            $stmt = $pdo->prepare("
                SELECT df.*, fds.semester, fds.academic_year, fds.submitted_at
                FROM document_files df
                INNER JOIN faculty_document_submissions fds ON df.submission_id = fds.id
                WHERE df.uploaded_by = ? AND df.file_type = ? AND fds.academic_year = ? AND fds.semester = ?
                ORDER BY df.uploaded_at DESC
            ");
            $stmt->execute([$currentUser['id'], $docType, $selectedYear, $selectedSemester]);
            $files = $stmt->fetchAll();
        } else {
            $errorMessage = "File not found or you don't have permission to delete it.";
        }
        
    } catch (Exception $e) {
        $errorMessage = "Error deleting file: " . $e->getMessage();
    }
}

// Calculate statistics
$totalFiles = count($files);
$totalSize = array_sum(array_column($files, 'file_size'));
$latestUpload = $totalFiles > 0 ? $files[0]['uploaded_at'] : null;

function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $iconMap = [
        'pdf' => 'bxs-file-pdf',
        'doc' => 'bxs-file-doc',
        'docx' => 'bxs-file-doc',
        'xls' => 'bxs-spreadsheet',
        'xlsx' => 'bxs-spreadsheet',
        'ppt' => 'bxs-slideshow',
        'pptx' => 'bxs-slideshow',
        'jpg' => 'bxs-image',
        'jpeg' => 'bxs-image',
        'png' => 'bxs-image',
        'txt' => 'bxs-file-txt'
    ];
    return $iconMap[$ext] ?? 'bxs-file-blank';
}

function getFileTypeColor($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $colorMap = [
        'pdf' => '#dc3545',
        'doc' => '#007bff',
        'docx' => '#007bff',
        'xls' => '#28a745',
        'xlsx' => '#28a745',
        'ppt' => '#fd7e14',
        'pptx' => '#fd7e14',
        'jpg' => '#6f42c1',
        'jpeg' => '#6f42c1',
        'png' => '#6f42c1',
        'txt' => '#6c757d'
    ];
    return $colorMap[$ext] ?? '#6c757d';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View <?php echo htmlspecialchars($docType); ?> Files - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        .view-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-info h1 {
            margin: 0;
            color: #333;
            font-size: 28px;
        }
        
        .header-info p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 16px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .period-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card i {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-card h3 {
            margin: 10px 0 5px 0;
            font-size: 1.8em;
            color: #333;
        }
        
        .stat-card p {
            color: #666;
            margin: 0;
        }
        
        .files-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .files-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .files-header h3 {
            margin: 0;
            color: #333;
        }
        
        .view-options {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .view-toggle {
            display: flex;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .view-toggle button {
            padding: 8px 12px;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .view-toggle button.active {
            background: #667eea;
            color: white;
        }
        
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .file-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
            background: white;
        }
        
        .file-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .file-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .file-icon {
            font-size: 2.5em;
            margin-right: 15px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            word-break: break-word;
        }
        
        .file-details {
            color: #666;
            font-size: 14px;
        }
        
        .file-description {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-style: italic;
            color: #555;
            border-left: 3px solid #667eea;
        }
        
        .file-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        
        .files-list {
            display: none;
        }
        
        .files-list.active {
            display: block;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s;
        }
        
        .file-item:hover {
            background: #f8f9fa;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-align: center;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #dee2e6;
            color: #666;
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .empty-state h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.25);
        }
        
        .search-box i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        .sort-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .sort-dropdown select {
            padding: 8px 30px 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            font-size: 14px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .files-grid {
                grid-template-columns: 1fr;
            }
            
            .file-actions {
                flex-wrap: wrap;
            }
        }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
    <div class="view-container">
        <!-- Header -->
        <div class="header-section">
            <div class="header-info">
                <h1><i class='bx bx-file'></i> <?php echo htmlspecialchars($docType); ?> Files</h1>
                <p>View and manage your uploaded documents</p>
            </div>
            <div class="header-actions">
                <a href="upload_document.php?doc_type=<?php echo urlencode($docType); ?>&year=<?php echo $selectedYear; ?>&semester=<?php echo urlencode($selectedSemester); ?>" class="btn btn-success">
                    <i class='bx bx-upload'></i> Upload More Files
                </a>
                <a href="../submission_tracker.php?year=<?php echo $selectedYear; ?>&semester=<?php echo urlencode($selectedSemester); ?>" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Back to Tracker
                </a>
            </div>
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

        <!-- Messages -->
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i> 
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger">
                <i class='bx bx-error-circle'></i> 
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class='bx bxs-file' style="color: #667eea;"></i>
                <h3><?php echo $totalFiles; ?></h3>
                <p>Total Files</p>
            </div>
            <div class="stat-card">
                <i class='bx bxs-data' style="color: #28a745;"></i>
                <h3><?php echo formatFileSize($totalSize); ?></h3>
                <p>Total Size</p>
            </div>
            <div class="stat-card">
                <i class='bx bxs-time' style="color: #ffc107;"></i>
                <h3><?php echo $latestUpload ? date('M j', strtotime($latestUpload)) : 'N/A'; ?></h3>
                <p>Latest Upload</p>
            </div>
            <div class="stat-card">
                <i class='bx bxs-check-shield' style="color: #17a2b8;"></i>
                <h3><?php echo $totalFiles > 0 ? 'Complete' : 'Empty'; ?></h3>
                <p>Status</p>
            </div>
        </div>

        <?php if (!empty($files)): ?>
        <!-- Files Container -->
        <div class="files-container">
            <div class="files-header">
                <h3><i class='bx bx-folder-open'></i> Your Files (<?php echo count($files); ?>)</h3>
                <div class="view-options">
                    <div class="sort-dropdown">
                        <select id="sortSelect" onchange="sortFiles()">
                            <option value="date_desc">Newest First</option>
                            <option value="date_asc">Oldest First</option>
                            <option value="name_asc">Name A-Z</option>
                            <option value="name_desc">Name Z-A</option>
                            <option value="size_desc">Largest First</option>
                            <option value="size_asc">Smallest First</option>
                        </select>
                    </div>
                    <div class="view-toggle">
                        <button class="active" onclick="toggleView('grid')" id="gridBtn">
                            <i class='bx bx-grid-alt'></i>
                        </button>
                        <button onclick="toggleView('list')" id="listBtn">
                            <i class='bx bx-list-ul'></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Search Box -->
            <div style="padding: 20px; border-bottom: 1px solid #dee2e6;">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search files by name or description..." onkeyup="searchFiles()">
                    <i class='bx bx-search'></i>
                </div>
            </div>
            
            <!-- Grid View -->
            <div class="files-grid active" id="gridView">
                <?php foreach ($files as $file): ?>
                    <div class="file-card" data-name="<?php echo strtolower($file['file_name']); ?>" 
                         data-description="<?php echo strtolower($file['description'] ?? ''); ?>"
                         data-date="<?php echo strtotime($file['uploaded_at']); ?>"
                         data-size="<?php echo $file['file_size']; ?>">
                        <div class="file-card-header">
                            <i class='bx <?php echo getFileIcon($file['file_name']); ?> file-icon' 
                               style="color: <?php echo getFileTypeColor($file['file_name']); ?>;"></i>
                            <div class="file-info">
                                <div class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></div>
                                <div class="file-details">
                                    <?php echo formatFileSize($file['file_size']); ?> • 
                                    <?php echo date('M j, Y g:i A', strtotime($file['uploaded_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($file['description']): ?>
                            <div class="file-description">
                                <?php echo htmlspecialchars($file['description']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="file-actions">
                            <a href="download_file.php?id=<?php echo $file['id']; ?>" class="btn btn-primary btn-sm">
                                <i class='bx bx-download'></i> Download
                            </a>
                            <a href="preview_file.php?id=<?php echo $file['id']; ?>" class="btn btn-outline btn-sm" target="_blank">
                                <i class='bx bx-show'></i> Preview
                            </a>
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>')">
                                <i class='bx bx-trash'></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- List View -->
            <div class="files-list" id="listView">
                <?php foreach ($files as $file): ?>
                    <div class="file-item" data-name="<?php echo strtolower($file['file_name']); ?>" 
                         data-description="<?php echo strtolower($file['description'] ?? ''); ?>"
                         data-date="<?php echo strtotime($file['uploaded_at']); ?>"
                         data-size="<?php echo $file['file_size']; ?>">
                        <i class='bx <?php echo getFileIcon($file['file_name']); ?>' 
                           style="color: <?php echo getFileTypeColor($file['file_name']); ?>; font-size: 2em; margin-right: 15px;"></i>
                        
                        <div class="file-info" style="flex: 1;">
                            <div class="file-name" style="margin-bottom: 5px;"><?php echo htmlspecialchars($file['file_name']); ?></div>
                            <div class="file-details">
                                <?php echo formatFileSize($file['file_size']); ?> • 
                                <?php echo date('M j, Y g:i A', strtotime($file['uploaded_at'])); ?>
                                <?php if ($file['description']): ?>
                                    <br><em style="color: #666;"><?php echo htmlspecialchars($file['description']); ?></em>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="file-actions" style="margin-left: 15px;">
                            <a href="download_file.php?id=<?php echo $file['id']; ?>" class="btn btn-primary btn-sm">
                                <i class='bx bx-download'></i>
                            </a>
                            <a href="preview_file.php?id=<?php echo $file['id']; ?>" class="btn btn-outline btn-sm" target="_blank">
                                <i class='bx bx-show'></i>
                            </a>
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_name'], ENT_QUOTES); ?>')">
                                <i class='bx bx-trash'></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Empty State -->
        <div class="files-container">
            <div class="empty-state">
                <i class='bx bx-file'></i>
                <h4>No files uploaded yet</h4>
                <p>You haven't uploaded any files for <?php echo htmlspecialchars($docType); ?> in <?php echo htmlspecialchars($selectedYear . ' - ' . $selectedSemester); ?>.</p>
                <a href="upload_document.php?doc_type=<?php echo urlencode($docType); ?>&year=<?php echo $selectedYear; ?>&semester=<?php echo urlencode($selectedSemester); ?>" class="btn btn-primary">
                    <i class='bx bx-upload'></i> Upload Your First File
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class='bx bx-trash'></i> Confirm Delete</h3>
                <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div id="deleteModalBody">
                <p>Are you sure you want to delete this file?</p>
                <p><strong id="deleteFileName"></strong></p>
                <p style="color: #dc3545; font-size: 14px;">
                    <i class='bx bx-error-circle'></i> This action cannot be undone.
                </p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="file_id" id="deleteFileId">
                    <button type="submit" name="delete_file" class="btn btn-danger">
                        <i class='bx bx-trash'></i> Delete File
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentView = 'grid';
        let currentSort = 'date_desc';
        
        function toggleView(view) {
            currentView = view;
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const gridBtn = document.getElementById('gridBtn');
            const listBtn = document.getElementById('listBtn');
            
            if (view === 'grid') {
                gridView.classList.add('active');
                listView.classList.remove('active');
                gridBtn.classList.add('active');
                listBtn.classList.remove('active');
            } else {
                gridView.classList.remove('active');
                listView.classList.add('active');
                gridBtn.classList.remove('active');
                listBtn.classList.add('active');
            }
        }
        
        function sortFiles() {
            const sortValue = document.getElementById('sortSelect').value;
            currentSort = sortValue;
            
            const containers = [document.getElementById('gridView'), document.getElementById('listView')];
            
            containers.forEach(container => {
                const items = Array.from(container.children);
                
                items.sort((a, b) => {
                    switch (sortValue) {
                        case 'date_desc':
                            return parseInt(b.dataset.date) - parseInt(a.dataset.date);
                        case 'date_asc':
                            return parseInt(a.dataset.date) - parseInt(b.dataset.date);
                        case 'name_asc':
                            return a.dataset.name.localeCompare(b.dataset.name);
                        case 'name_desc':
                            return b.dataset.name.localeCompare(a.dataset.name);
                        case 'size_desc':
                            return parseInt(b.dataset.size) - parseInt(a.dataset.size);
                        case 'size_asc':
                            return parseInt(a.dataset.size) - parseInt(b.dataset.size);
                        default:
                            return 0;
                    }
                });
                
                items.forEach(item => container.appendChild(item));
            });
        }
        
        function searchFiles() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const containers = [document.getElementById('gridView'), document.getElementById('listView')];
            
            containers.forEach(container => {
                const items = container.children;
                
                for (let item of items) {
                    const name = item.dataset.name;
                    const description = item.dataset.description;
                    
                    if (name.includes(searchTerm) || description.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                }
            });
        }
        
        function confirmDelete(fileId, fileName) {
            document.getElementById('deleteFileId').value = fileId;
            document.getElementById('deleteFileName').textContent = fileName;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // ESC to close modal
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
            
            // Ctrl/Cmd + F for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial sort
            sortFiles();
        });
    </script>
</body>
</html>