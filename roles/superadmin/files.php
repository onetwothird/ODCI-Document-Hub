<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Ensure super admin access
$currentUser = requireSuperAdmin();
if (!$currentUser) {
    header('Location: ../../login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files Management - Super Admin</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        /* Files Management Styles */
        .files-container {
            padding: 20px;
            background: #f8f9fa;
            min-height: calc(100vh - 80px);
        }

        .files-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .files-header h1 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }

        .files-header p {
            color: #666;
            margin: 0;
            font-size: 16px;
        }

        .files-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .search-filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .breadcrumb {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb-separator {
            color: #6c757d;
        }

        .files-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
        }

        .folders-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            height: fit-content;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .folders-sidebar h3 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }

        .folder-tree {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .folder-item {
            margin-bottom: 5px;
        }

        .folder-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            color: #555;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .folder-link:hover,
        .folder-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
        }

        .folder-link i {
            font-size: 18px;
        }

        .folder-info {
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .folder-name {
            font-size: 14px;
        }

        .folder-stats {
            font-size: 12px;
            opacity: 0.8;
        }

        .files-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .files-toolbar {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: between;
            align-items: center;
            gap: 15px;
        }

        .view-toggle {
            display: flex;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e9ecef;
        }

        .view-toggle button {
            padding: 8px 12px;
            border: none;
            background: white;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-toggle button.active {
            background: #667eea;
            color: white;
        }

        .files-table {
            width: 100%;
            border-collapse: collapse;
        }

        .files-table th,
        .files-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .files-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .files-table tr:hover {
            background: #f8f9fa;
        }

        .file-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            margin-right: 12px;
            font-size: 18px;
            color: white;
        }

        .file-icon.folder {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .file-icon.document {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .file-icon.image {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .file-icon.video {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .file-icon.audio {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .file-icon.archive {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        .file-info {
            display: flex;
            align-items: center;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 2px;
        }

        .file-meta {
            font-size: 12px;
            color: #6c757d;
        }

        .file-size {
            font-weight: 500;
            color: #6c757d;
        }

        .file-actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            padding: 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            transform: translateY(-1px);
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: #f8f9fa;
            color: #333;
        }

        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            background: #f8f9ff;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-area:hover {
            border-color: #764ba2;
            background: #f0f2ff;
        }

        .upload-area.dragover {
            border-color: #28a745;
            background: #f0fff4;
        }

        .upload-icon {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-active {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-deleted {
            background: #f8d7da;
            color: #721c24;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }

        .pagination button {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            background: white;
            color: #6c757d;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination button:hover:not(:disabled) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #e9ecef;
        }

        .storage-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .storage-bar {
            background: rgba(255,255,255,0.2);
            height: 8px;
            border-radius: 4px;
            margin-top: 10px;
            overflow: hidden;
        }

        .storage-progress {
            background: white;
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .files-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .files-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .folders-sidebar {
                order: 2;
            }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
    <link rel="stylesheet" href="assets/css/management-pages.css?v=1.0">
</head>

<body class="superadmin-management-page files-management-page">
    <!-- Sidebar -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <?php include 'components/navbar.html'; ?>

        <!-- Files Management Container -->
        <div class="files-container">
            
            <!-- Files Header -->
            <div class="files-header">
                <h1><i class='bx bx-folder'></i> Files Management</h1>
                <p>Manage all files and folders across the system. Upload, organize, and monitor file usage.</p>
                
                <div class="files-actions">
                    <button class="btn btn-primary" onclick="openUploadModal()">
                        <i class='bx bx-cloud-upload'></i> Upload Files
                    </button>
                    <button class="btn btn-secondary" onclick="openFolderModal()">
                        <i class='bx bx-folder-plus'></i> Create Folder
                    </button>
                    <button class="btn btn-success" onclick="exportFilesList()">
                        <i class='bx bx-download'></i> Export List
                    </button>
                    <button class="btn btn-warning" onclick="bulkActions()">
                        <i class='bx bx-check-square'></i> Bulk Actions
                    </button>
                    <button class="btn btn-danger" onclick="cleanupFiles()">
                        <i class='bx bx-trash'></i> Cleanup Deleted
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number" id="totalFiles">0</div>
                    <div class="stat-label">Total Files</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="totalFolders">0</div>
                    <div class="stat-label">Total Folders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="totalSize">0 MB</div>
                    <div class="stat-label">Storage Used</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="totalDownloads">0</div>
                    <div class="stat-label">Total Downloads</div>
                </div>
            </div>

            <!-- Storage Information -->
            <div class="storage-info">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="margin: 0 0 5px 0;">Storage Usage</h4>
                        <p style="margin: 0; opacity: 0.9;">Monitor system storage consumption</p>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 18px; font-weight: 600;">2.3 GB / 10 GB</div>
                        <div style="opacity: 0.8;">23% Used</div>
                    </div>
                </div>
                <div class="storage-bar">
                    <div class="storage-progress" style="width: 23%;"></div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="searchFiles">Search Files</label>
                        <input type="text" id="searchFiles" class="form-control" placeholder="Search by name, type, or uploader...">
                    </div>
                    <div class="form-group">
                        <label for="filterType">File Type</label>
                        <select id="filterType" class="form-control">
                            <option value="">All Types</option>
                            <option value="document">Documents</option>
                            <option value="image">Images</option>
                            <option value="video">Videos</option>
                            <option value="audio">Audio</option>
                            <option value="archive">Archives</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filterDepartment">Department</label>
                        <select id="filterDepartment" class="form-control">
                            <option value="">All Departments</option>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filterDate">Upload Date</label>
                        <select id="filterDate" class="form-control">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class='bx bx-search'></i> Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb" id="breadcrumb">
                <a href="#" onclick="navigateToFolder(0)"><i class='bx bx-home'></i> Root</a>
            </div>

            <!-- Files Grid -->
            <div class="files-grid">
                <!-- Folders Sidebar -->
                <div class="folders-sidebar">
                    <h3>Folder Structure</h3>
                    <ul class="folder-tree" id="folderTree">
                        <!-- Folders will be populated here -->
                    </ul>
                </div>

                <!-- Files Content -->
                <div class="files-content">
                    <div class="files-toolbar">
                        <div>
                            <span style="font-weight: 500; color: #333;">View:</span>
                            <div class="view-toggle">
                                <button class="active" data-view="list"><i class='bx bx-list-ul'></i></button>
                                <button data-view="grid"><i class='bx bx-grid-alt'></i></button>
                            </div>
                        </div>
                        <div>
                            <select class="form-control" style="width: auto;" onchange="changeSort(this.value)">
                                <option value="name_asc">Name (A-Z)</option>
                                <option value="name_desc">Name (Z-A)</option>
                                <option value="date_desc">Newest First</option>
                                <option value="date_asc">Oldest First</option>
                                <option value="size_desc">Largest First</option>
                                <option value="size_asc">Smallest First</option>
                            </select>
                        </div>
                    </div>

                    <div id="filesTableContainer">
                        <table class="files-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th>Name</th>
                                    <th>Size</th>
                                    <th>Type</th>
                                    <th>Uploaded By</th>
                                    <th>Upload Date</th>
                                    <th>Downloads</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="filesTableBody">
                                <!-- Files will be populated here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination" id="pagination">
                        <!-- Pagination will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Upload Files</h3>
                <button class="close-btn" onclick="closeModal('uploadModal')">&times;</button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                    <i class='bx bx-cloud-upload upload-icon'></i>
                    <h4>Drop files here or click to browse</h4>
                    <p>Supports all file types. Maximum file size: 100MB</p>
                    <input type="file" id="fileInput" multiple style="display: none;" onchange="handleFileSelect(this.files)">
                </div>
                
                <div class="form-group">
                    <label for="uploadFolder">Upload to Folder</label>
                    <select id="uploadFolder" class="form-control" required>
                        <option value="">Select Folder</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fileDescription">Description (Optional)</label>
                    <textarea id="fileDescription" class="form-control" rows="3" placeholder="Add a description for these files..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="makePublic"> Make files publicly accessible
                    </label>
                </div>
                
                <div id="selectedFiles"></div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-upload'></i> Upload Files
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div class="modal" id="folderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create New Folder</h3>
                <button class="close-btn" onclick="closeModal('folderModal')">&times;</button>
            </div>
            <form id="folderForm">
                <div class="form-group">
                    <label for="folderName">Folder Name</label>
                    <input type="text" id="folderName" class="form-control" required placeholder="Enter folder name">
                </div>
                
                <div class="form-group">
                    <label for="parentFolder">Parent Folder</label>
                    <select id="parentFolder" class="form-control">
                        <option value="0">Root Directory</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="folderDepartment">Department Access</label>
                    <select id="folderDepartment" class="form-control">
                        <option value="">All Departments</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="folderDescription">Description</label>
                    <textarea id="folderDescription" class="form-control" rows="3" placeholder="Describe this folder's purpose..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="makeFolderPublic"> Make folder publicly accessible
                    </label>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('folderModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-folder-plus'></i> Create Folder
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- File Details Modal -->
    <div class="modal" id="fileDetailsModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">File Details</h3>
                <button class="close-btn" onclick="closeModal('fileDetailsModal')">&times;</button>
            </div>
            <div id="fileDetailsContent">
                <!-- File details will be populated here -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentFolder = 0;
        let currentPage = 1;
        let itemsPerPage = 20;
        let currentSort = 'name_asc';
        let selectedFiles = [];

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadFolderTree();
            loadFiles();
            loadDepartments();
            loadStatistics();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            // Search input
            document.getElementById('searchFiles').addEventListener('input', debounce(applyFilters, 300));
            
            // View toggle
            document.querySelectorAll('.view-toggle button').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.view-toggle button').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    toggleView(this.dataset.view);
                });
            });

            // Drag and drop
            const uploadArea = document.querySelector('.upload-area');
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                handleFileSelect(e.dataTransfer.files);
            });

            // Form submissions
            document.getElementById('uploadForm').addEventListener('submit', handleFileUpload);
            document.getElementById('folderForm').addEventListener('submit', handleFolderCreate);
        }

        // Load folder tree
        async function loadFolderTree() {
            try {
                const response = await fetch('api/folders.php?action=tree');
                const folders = await response.json();
                
                const folderTree = document.getElementById('folderTree');
                folderTree.innerHTML = buildFolderTree(folders);
                
                // Also populate folder selects
                populateFolderSelects(folders);
            } catch (error) {
                console.error('Error loading folder tree:', error);
            }
        }

        // Build folder tree HTML
        function buildFolderTree(folders, level = 0) {
            let html = '';
            folders.forEach(folder => {
                const indent = 'padding-left: ' + (level * 20 + 10) + 'px;';
                const isActive = folder.id == currentFolder ? 'active' : '';
                
                html += `
                    <li class="folder-item">
                        <a href="#" class="folder-link ${isActive}" style="${indent}" onclick="navigateToFolder(${folder.id})">
                            <i class='bx bx-folder' style="color: ${folder.folder_color || '#667eea'}"></i>
                            <div class="folder-info">
                                <div class="folder-name">${folder.folder_name}</div>
                                <div class="folder-stats">${folder.file_count || 0} files</div>
                            </div>
                        </a>
                        ${folder.children ? buildFolderTree(folder.children, level + 1) : ''}
                    </li>
                `;
            });
            return html;
        }

        // Load files
        async function loadFiles() {
            try {
                showLoading();
                
                const params = new URLSearchParams({
                    folder_id: currentFolder,
                    page: currentPage,
                    limit: itemsPerPage,
                    sort: currentSort,
                    search: document.getElementById('searchFiles').value,
                    type: document.getElementById('filterType').value,
                    department: document.getElementById('filterDepartment').value,
                    date: document.getElementById('filterDate').value
                });

                const response = await fetch(`api/files.php?action=list&${params}`);
                const data = await response.json();
                
                if (data.success) {
                    displayFiles(data.files);
                    updatePagination(data.pagination);
                    updateBreadcrumb(data.current_folder);
                } else {
                    showError('Failed to load files: ' + data.message);
                }
            } catch (error) {
                console.error('Error loading files:', error);
                showError('Error loading files. Please try again.');
            }
        }

        // Display files in table
        function displayFiles(files) {
            const tbody = document.getElementById('filesTableBody');
            
            if (files.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class='bx bx-folder-open'></i>
                            <h4>No files found</h4>
                            <p>This folder is empty or no files match your search criteria.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = files.map(file => `
                <tr data-file-id="${file.id}">
                    <td>
                        <input type="checkbox" class="file-checkbox" value="${file.id}" onchange="updateSelection()">
                    </td>
                    <td>
                        <div class="file-info">
                            ${getFileIcon(file)}
                            <div class="file-details">
                                <div class="file-name">${file.original_name}</div>
                                <div class="file-meta">
                                    ${file.description || 'No description'}
                                    ${file.is_deleted ? '<span class="status-badge status-deleted">Deleted</span>' : '<span class="status-badge status-active">Active</span>'}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="file-size">${formatFileSize(file.file_size)}</td>
                    <td>${file.file_type || 'Unknown'}</td>
                    <td>${file.uploader_full_name}</td>
                    <td>${formatDate(file.uploaded_at)}</td>
                    <td>${file.download_count || 0}</td>
                    <td>
                        <div class="file-actions">
                            <button class="btn-icon btn-primary" onclick="downloadFile(${file.id})" title="Download">
                                <i class='bx bx-download'></i>
                            </button>
                            <button class="btn-icon btn-secondary" onclick="viewFileDetails(${file.id})" title="Details">
                                <i class='bx bx-info-circle'></i>
                            </button>
                            <button class="btn-icon btn-warning" onclick="shareFile(${file.id})" title="Share">
                                <i class='bx bx-share'></i>
                            </button>
                            ${!file.is_deleted ? 
                                `<button class="btn-icon btn-danger" onclick="deleteFile(${file.id})" title="Delete">
                                    <i class='bx bx-trash'></i>
                                </button>` :
                                `<button class="btn-icon btn-success" onclick="restoreFile(${file.id})" title="Restore">
                                    <i class='bx bx-refresh'></i>
                                </button>`
                            }
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        // Get file icon based on type
        function getFileIcon(file) {
            let iconClass = 'bx-file';
            let iconType = 'document';
            
            if (file.mime_type) {
                if (file.mime_type.startsWith('image/')) {
                    iconClass = 'bx-image';
                    iconType = 'image';
                } else if (file.mime_type.startsWith('video/')) {
                    iconClass = 'bx-video';
                    iconType = 'video';
                } else if (file.mime_type.startsWith('audio/')) {
                    iconClass = 'bx-music';
                    iconType = 'audio';
                } else if (file.mime_type.includes('pdf')) {
                    iconClass = 'bx-file-pdf';
                    iconType = 'document';
                } else if (file.mime_type.includes('zip') || file.mime_type.includes('rar')) {
                    iconClass = 'bx-archive';
                    iconType = 'archive';
                }
            }
            
            return `<div class="file-icon ${iconType}"><i class='bx ${iconClass}'></i></div>`;
        }

        // Load departments for filters
        async function loadDepartments() {
            try {
                const response = await fetch('api/departments.php?action=list');
                const departments = await response.json();
                
                const select = document.getElementById('filterDepartment');
                departments.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.id;
                    option.textContent = `${dept.department_code} - ${dept.department_name}`;
                    select.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading departments:', error);
            }
        }

        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch('api/files.php?action=stats');
                const stats = await response.json();
                
                if (stats.success) {
                    document.getElementById('totalFiles').textContent = stats.data.total_files.toLocaleString();
                    document.getElementById('totalFolders').textContent = stats.data.total_folders.toLocaleString();
                    document.getElementById('totalSize').textContent = formatFileSize(stats.data.total_size);
                    document.getElementById('totalDownloads').textContent = stats.data.total_downloads.toLocaleString();
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }

        // Navigate to folder
        function navigateToFolder(folderId) {
            currentFolder = folderId;
            currentPage = 1;
            loadFiles();
            
            // Update active folder in sidebar
            document.querySelectorAll('.folder-link').forEach(link => {
                link.classList.remove('active');
            });
            event?.target.closest('.folder-link')?.classList.add('active');
        }

        // Update breadcrumb
        function updateBreadcrumb(folderPath) {
            const breadcrumb = document.getElementById('breadcrumb');
            let html = '<a href="#" onclick="navigateToFolder(0)"><i class="bx bx-home"></i> Root</a>';
            
            if (folderPath && folderPath.length > 0) {
                folderPath.forEach((folder, index) => {
                    html += ` <span class="breadcrumb-separator">/</span> `;
                    html += `<a href="#" onclick="navigateToFolder(${folder.id})">${folder.folder_name}</a>`;
                });
            }
            
            breadcrumb.innerHTML = html;
        }

        // Handle file upload
        async function handleFileUpload(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const files = document.getElementById('fileInput').files;
            const folderId = document.getElementById('uploadFolder').value;
            const description = document.getElementById('fileDescription').value;
            const makePublic = document.getElementById('makePublic').checked;
            
            if (!folderId) {
                showError('Please select a folder');
                return;
            }
            
            if (files.length === 0) {
                showError('Please select files to upload');
                return;
            }
            
            // Add files to form data
            for (let file of files) {
                formData.append('files[]', file);
            }
            formData.append('folder_id', folderId);
            formData.append('description', description);
            formData.append('is_public', makePublic ? '1' : '0');
            
            try {
                const response = await fetch('api/files.php?action=upload', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('Files uploaded successfully!');
                    closeModal('uploadModal');
                    loadFiles();
                    loadStatistics();
                    
                    // Reset form
                    document.getElementById('uploadForm').reset();
                    document.getElementById('selectedFiles').innerHTML = '';
                } else {
                    showError('Upload failed: ' + result.message);
                }
            } catch (error) {
                console.error('Upload error:', error);
                showError('Upload failed. Please try again.');
            }
        }

        // Handle folder creation
        async function handleFolderCreate(e) {
            e.preventDefault();
            
            const formData = {
                folder_name: document.getElementById('folderName').value,
                parent_id: document.getElementById('parentFolder').value || null,
                department_id: document.getElementById('folderDepartment').value || null,
                description: document.getElementById('folderDescription').value,
                is_public: document.getElementById('makeFolderPublic').checked
            };
            
            try {
                const response = await fetch('api/folders.php?action=create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('Folder created successfully!');
                    closeModal('folderModal');
                    loadFolderTree();
                    loadFiles();
                    
                    // Reset form
                    document.getElementById('folderForm').reset();
                } else {
                    showError('Failed to create folder: ' + result.message);
                }
            } catch (error) {
                console.error('Error creating folder:', error);
                showError('Failed to create folder. Please try again.');
            }
        }

        // File selection handling
        function handleFileSelect(files) {
            const selectedFilesDiv = document.getElementById('selectedFiles');
            selectedFilesDiv.innerHTML = '';
            
            if (files.length > 0) {
                selectedFilesDiv.innerHTML = `
                    <div style="margin-top: 20px;">
                        <h5>Selected Files (${files.length}):</h5>
                        <div style="max-height: 150px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 8px; padding: 10px;">
                            ${Array.from(files).map(file => `
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid #f1f3f4;">
                                    <span>${file.name}</span>
                                    <span style="color: #6c757d; font-size: 12px;">${formatFileSize(file.size)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
        }

        // Apply filters
        async function applyFilters() {
            currentPage = 1;
            loadFiles();
        }

        // Change sort order
        function changeSort(sortValue) {
            currentSort = sortValue;
            currentPage = 1;
            loadFiles();
        }

        // Pagination
        function updatePagination(pagination) {
            const paginationDiv = document.getElementById('pagination');
            
            if (pagination.total_pages <= 1) {
                paginationDiv.innerHTML = '';
                return;
            }
            
            let html = '';
            
            // Previous button
            html += `<button ${pagination.current_page <= 1 ? 'disabled' : ''} onclick="changePage(${pagination.current_page - 1})">
                        <i class='bx bx-chevron-left'></i> Previous
                    </button>`;
            
            // Page numbers
            for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
                html += `<button class="${i === pagination.current_page ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
            }
            
            // Next button
            html += `<button ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''} onclick="changePage(${pagination.current_page + 1})">
                        Next <i class='bx bx-chevron-right'></i>
                    </button>`;
            
            paginationDiv.innerHTML = html;
        }

        // Change page
        function changePage(page) {
            currentPage = page;
            loadFiles();
        }

        // File actions
        async function downloadFile(fileId) {
            try {
                const response = await fetch(`api/files.php?action=download&id=${fileId}`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = '';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    // Refresh files to update download count
                    loadFiles();
                } else {
                    showError('Failed to download file');
                }
            } catch (error) {
                console.error('Download error:', error);
                showError('Download failed');
            }
        }

        async function deleteFile(fileId) {
            if (!confirm('Are you sure you want to delete this file?')) return;
            
            try {
                const response = await fetch('api/files.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: fileId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('File deleted successfully');
                    loadFiles();
                    loadStatistics();
                } else {
                    showError('Failed to delete file: ' + result.message);
                }
            } catch (error) {
                console.error('Delete error:', error);
                showError('Failed to delete file');
            }
        }

        async function restoreFile(fileId) {
            try {
                const response = await fetch('api/files.php?action=restore', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: fileId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('File restored successfully');
                    loadFiles();
                    loadStatistics();
                } else {
                    showError('Failed to restore file: ' + result.message);
                }
            } catch (error) {
                console.error('Restore error:', error);
                showError('Failed to restore file');
            }
        }

        async function viewFileDetails(fileId) {
            try {
                const response = await fetch(`api/files.php?action=details&id=${fileId}`);
                const result = await response.json();
                
                if (result.success) {
                    const file = result.data;
                    document.getElementById('fileDetailsContent').innerHTML = `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <h5>File Information</h5>
                                <p><strong>Original Name:</strong> ${file.original_name}</p>
                                <p><strong>File Size:</strong> ${formatFileSize(file.file_size)}</p>
                                <p><strong>File Type:</strong> ${file.file_type}</p>
                                <p><strong>MIME Type:</strong> ${file.mime_type}</p>
                                <p><strong>Uploaded:</strong> ${formatDate(file.uploaded_at)}</p>
                                <p><strong>Downloads:</strong> ${file.download_count}</p>
                            </div>
                            <div>
                                <h5>Upload Details</h5>
                                <p><strong>Uploaded By:</strong> ${file.uploader_full_name}</p>
                                <p><strong>Folder:</strong> ${file.folder_name}</p>
                                <p><strong>Department:</strong> ${file.folder_department || 'N/A'}</p>
                                <p><strong>Public:</strong> ${file.is_public ? 'Yes' : 'No'}</p>
                                <p><strong>Status:</strong> ${file.is_deleted ? 'Deleted' : 'Active'}</p>
                            </div>
                        </div>
                        ${file.description ? `
                            <div style="margin-top: 20px;">
                                <h5>Description</h5>
                                <p>${file.description}</p>
                            </div>
                        ` : ''}
                    `;
                    
                    document.getElementById('fileDetailsModal').classList.add('show');
                } else {
                    showError('Failed to load file details');
                }
            } catch (error) {
                console.error('Error loading file details:', error);
                showError('Failed to load file details');
            }
        }

        // Utility functions
        function formatFileSize(bytes) {
            if (!bytes) return '0 B';
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Modal functions
        function openUploadModal() {
            populateFolderSelect(document.getElementById('uploadFolder'));
            document.getElementById('uploadModal').classList.add('show');
        }

        function openFolderModal() {
            populateFolderSelect(document.getElementById('parentFolder'));
            document.getElementById('folderModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Populate folder selects
        async function populateFolderSelect(selectElement) {
            try {
                const response = await fetch('api/folders.php?action=list');
                const folders = await response.json();
                
                selectElement.innerHTML = selectElement.id === 'parentFolder' ? 
                    '<option value="0">Root Directory</option>' : 
                    '<option value="">Select Folder</option>';
                
                folders.forEach(folder => {
                    const option = document.createElement('option');
                    option.value = folder.id;
                    option.textContent = folder.folder_path || folder.folder_name;
                    selectElement.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading folders:', error);
            }
        }

        function populateFolderSelects(folders) {
            // This would be called from loadFolderTree
            const uploadSelect = document.getElementById('uploadFolder');
            const parentSelect = document.getElementById('parentFolder');
            
            // Clear existing options (except default)
            uploadSelect.innerHTML = '<option value="">Select Folder</option>';
            parentSelect.innerHTML = '<option value="0">Root Directory</option>';
            
            function addFolderOptions(folders, prefix = '') {
                folders.forEach(folder => {
                    const optionText = prefix + folder.folder_name;
                    
                    const uploadOption = document.createElement('option');
                    uploadOption.value = folder.id;
                    uploadOption.textContent = optionText;
                    uploadSelect.appendChild(uploadOption);
                    
                    const parentOption = document.createElement('option');
                    parentOption.value = folder.id;
                    parentOption.textContent = optionText;
                    parentSelect.appendChild(parentOption);
                    
                    if (folder.children) {
                        addFolderOptions(folder.children, prefix + '  ');
                    }
                });
            }
            
            addFolderOptions(folders);
        }

        // Selection functions
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.file-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelection();
        }

        function updateSelection() {
            selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);
            
            // Update select all checkbox
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.file-checkbox');
            selectAll.checked = checkboxes.length > 0 && selectedFiles.length === checkboxes.length;
            selectAll.indeterminate = selectedFiles.length > 0 && selectedFiles.length < checkboxes.length;
        }

        // Bulk actions
        async function bulkActions() {
            if (selectedFiles.length === 0) {
                showError('Please select files first');
                return;
            }
            
            const action = prompt('Enter action (delete/restore/move):');
            if (!action) return;
            
            try {
                const response = await fetch('api/files.php?action=bulk', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: action,
                        file_ids: selectedFiles
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess(`Bulk ${action} completed successfully`);
                    loadFiles();
                    loadStatistics();
                    selectedFiles = [];
                    document.getElementById('selectAll').checked = false;
                } else {
                    showError('Bulk action failed: ' + result.message);
                }
            } catch (error) {
                console.error('Bulk action error:', error);
                showError('Bulk action failed');
            }
        }

        // Export files list
        async function exportFilesList() {
            try {
                const params = new URLSearchParams({
                    folder_id: currentFolder,
                    search: document.getElementById('searchFiles').value,
                    type: document.getElementById('filterType').value,
                    department: document.getElementById('filterDepartment').value,
                    date: document.getElementById('filterDate').value
                });

                const response = await fetch(`api/files.php?action=export&${params}`);
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `files_export_${new Date().toISOString().split('T')[0]}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    showSuccess('Files list exported successfully');
                } else {
                    showError('Export failed');
                }
            } catch (error) {
                console.error('Export error:', error);
                showError('Export failed');
            }
        }

        // Cleanup deleted files
        async function cleanupFiles() {
            if (!confirm('This will permanently delete all soft-deleted files. This action cannot be undone. Continue?')) {
                return;
            }
            
            try {
                const response = await fetch('api/files.php?action=cleanup', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess(`${result.data.cleaned_count} files permanently deleted`);
                    loadFiles();
                    loadStatistics();
                } else {
                    showError('Cleanup failed: ' + result.message);
                }
            } catch (error) {
                console.error('Cleanup error:', error);
                showError('Cleanup failed');
            }
        }

        // Share file
        async function shareFile(fileId) {
            try {
                const response = await fetch(`api/files.php?action=share&id=${fileId}`, {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const shareUrl = result.data.share_url;
                    navigator.clipboard.writeText(shareUrl).then(() => {
                        showSuccess('Share link copied to clipboard!');
                    });
                } else {
                    showError('Failed to generate share link');
                }
            } catch (error) {
                console.error('Share error:', error);
                showError('Failed to generate share link');
            }
        }

        // View toggle
        function toggleView(view) {
            // Implementation for grid/list view toggle
            console.log('Switching to', view, 'view');
        }

        // Utility notification functions
        function showLoading() {
            document.getElementById('filesTableBody').innerHTML = `
                <tr>
                    <td colspan="8" class="loading">
                        <i class='bx bx-loader-alt bx-spin'></i> Loading files...
                    </td>
                </tr>
            `;
        }

        function showSuccess(message) {
            showAlert(message, 'success');
        }

        function showError(message) {
            showAlert(message, 'danger');
        }

        function showAlert(message, type) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <strong>${type === 'success' ? 'Success!' : 'Error!'}</strong> ${message}
                <button style="float: right; background: none; border: none; font-size: 18px; cursor: pointer;" onclick="this.parentElement.remove()">&times;</button>
            `;
            
            document.querySelector('.files-container').insertBefore(alert, document.querySelector('.files-header').nextSibling);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 1800);
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    </script>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
</body>

</html>