<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_page = 'my_files.php';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_file'])) {
    $folder_id = $_POST['folder_id'];
    $description = $_POST['description'] ?? '';
    $academic_year = $_POST['academic_year'] ?? date('Y');
    $semester = $_POST['semester'] ?? 'first';
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $file = $_FILES['file'];
        $original_name = $file['name'];
        $file_size = $file['size'];
        $mime_type = $file['type'];
        $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $file_name = uniqid() . '_' . time() . '.' . $file_extension;
        $upload_path = "../../uploads/user_files/" . $file_name;
        
        // Create directory if it doesn't exist
        if (!file_exists("../../uploads/user_files/")) {
            mkdir("../../uploads/user_files/", 0777, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Insert file record
            $stmt = $pdo->prepare("
                INSERT INTO files (file_name, original_name, file_path, file_size, file_type, 
                                 mime_type, file_extension, uploaded_by, folder_id, description, 
                                 academic_year, semester) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $file_name, $original_name, $upload_path, $file_size, 
                $mime_type, $mime_type, $file_extension, $user_id, 
                $folder_id, $description, $academic_year, $semester
            ]);
            
            $success_message = "File uploaded successfully!";
        } else {
            $error_message = "Failed to upload file.";
        }
    }
}

// Handle file deletion
if (isset($_GET['delete_file'])) {
    $file_id = $_GET['delete_file'];
    
    // Check if user owns the file
    $stmt = $pdo->prepare("SELECT file_path FROM files WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$file_id, $user_id]);
    $file = $stmt->fetch();
    
    if ($file) {
        // Soft delete
        $stmt = $pdo->prepare("UPDATE files SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?");
        $stmt->execute([$user_id, $file_id]);
        
        $success_message = "File deleted successfully!";
    }
}

// Get user's folders
$stmt = $pdo->prepare("
    SELECT * FROM folders 
    WHERE created_by = ? AND is_deleted = 0 
    ORDER BY folder_name
");
$stmt->execute([$user_id]);
$folders = $stmt->fetchAll();

// Get user's files
$folder_filter = $_GET['folder'] ?? '';
$search = $_GET['search'] ?? '';

$query = "
    SELECT f.*, fo.folder_name 
    FROM files f
    LEFT JOIN folders fo ON f.folder_id = fo.id
    WHERE f.uploaded_by = ? AND f.is_deleted = 0
";

$params = [$user_id];

if ($folder_filter) {
    $query .= " AND f.folder_id = ?";
    $params[] = $folder_filter;
}

if ($search) {
    $query .= " AND (f.original_name LIKE ? OR f.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY f.uploaded_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$files = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Files - ODCI</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">

    <!-- styles moved out of the body so the shared theme can win -->
<style>
    .upload-form, .filter-form {
        display: grid;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        font-weight: 600;
        color: #333;
    }

    .form-group input, .form-group select, .form-group textarea {
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    .files-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
    }

    .file-card {
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        transition: all 0.3s ease;
    }

    .file-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .file-icon i {
        font-size: 2rem;
        color: #667eea;
    }

    .file-info {
        flex: 1;
    }

    .file-info h4 {
        margin: 0 0 0.5rem 0;
        font-size: 14px;
        font-weight: 600;
    }

    .file-details {
        font-size: 12px;
        color: #666;
        margin: 0 0 0.5rem 0;
        line-height: 1.4;
    }

    .file-description {
        font-size: 12px;
        color: #888;
        margin: 0;
        font-style: italic;
    }

    .file-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 4px;
        background: #f8f9fa;
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .btn-icon:hover {
        background: #e9ecef;
        transform: scale(1.1);
    }

    .btn-icon.btn-danger:hover {
        background: #dc3545;
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #666;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .alert {
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .files-grid {
            grid-template-columns: 1fr;
        }
        
        .file-card {
            flex-direction: column;
            text-align: center;
        }
    }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body class="user-my-files-page">
    <?php include 'components/sidebar.html'; ?>

    <section id="content">
        <?php include 'components/navbar.html'; ?>
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>My Files</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">My Files</a></li>
                    </ul>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <!-- File Filters -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>Filter Files</h3>
                    </div>
                    <form method="GET" class="filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Folder:</label>
                                <select name="folder">
                                    <option value="">All Folders</option>
                                    <?php foreach ($folders as $folder): ?>
                                        <option value="<?= $folder['id'] ?>" <?= $folder_filter == $folder['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($folder['folder_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Search:</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search files...">
                            </div>
                            
                            <div class="form-group filter-actions">
                                <button type="submit" class="btn btn-secondary">Filter</button>
                                <a href="my_files.php" class="btn btn-outline">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Files List -->
            <div class="table-data">
                <div class="order">
                    <div class="head">
                        <h3>My Files (<?= count($files) ?>)</h3>
                    </div>
                    
                    <?php if (empty($files)): ?>
                        <div class="empty-state">
                            <i class='bx bx-file'></i>
                            <p>No files found</p>
                        </div>
                    <?php else: ?>
                        <div class="files-grid">
                            <?php foreach ($files as $file): ?>
                                <div class="file-card">
                                    <div class="file-icon">
                                        <?php
                                        $extension = strtolower($file['file_extension']);
                                        $icon = match($extension) {
                                            'pdf' => 'bx-file-pdf',
                                            'doc', 'docx' => 'bx-file-doc',
                                            'xls', 'xlsx' => 'bx-file-excel',
                                            'ppt', 'pptx' => 'bx-file-ppt',
                                            'jpg', 'jpeg', 'png', 'gif' => 'bx-image',
                                            'zip', 'rar' => 'bx-archive',
                                            default => 'bx-file'
                                        };
                                        ?>
                                        <i class='bx <?= $icon ?>'></i>
                                    </div>
                                    
                                    <div class="file-info">
                                        <h4><?= htmlspecialchars($file['original_name']) ?></h4>
                                        <p class="file-details">
                                            <span><i class='bx bx-folder'></i><?= htmlspecialchars($file['folder_name'] ?? 'No folder') ?></span>
                                            <span><i class='bx bx-data'></i><?= number_format($file['file_size'] / 1024, 2) ?> KB</span>
                                            <span><i class='bx bx-calendar'></i><?= date('M d, Y', strtotime($file['uploaded_at'])) ?></span>
                                        </p>
                                        
                                        <?php if ($file['description']): ?>
                                            <p class="file-description" title="<?= htmlspecialchars($file['description'], ENT_QUOTES) ?>"><?= htmlspecialchars($file['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="file-actions">
                                        <a href="../../<?= $file['file_path'] ?>" class="btn-icon" title="Download" download>
                                            <i class='bx bx-download'></i>
                                        </a>
                                        <a href="?delete_file=<?= $file['id'] ?>" class="btn-icon btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this file?')" title="Delete">
                                            <i class='bx bx-trash'></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </section>

    
    <script src="assets/js/components/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/components/navbar.js?v=<?= time() ?>"></script>
</body>
</html>