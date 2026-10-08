<?php include 'script/submission-tracker.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Tracker - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/submission-tracker.css?v=<?= time() ?>">
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

     
        :root {
            --poppins: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --lato: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

            /* Primary Green (for navbar/sidebar compatibility) */
            --primary-green: #28a745;
            --light-green: #f0fdf4;
            --dark-green: #1e7e34;

            /* Complementary Color Palette */
            --teal: #14b8a6;
            --green: #28a745;
            --cyan: #0891b2;
            --blue: #28a745;
            --indigo: #1e7e34;
            --purple: #1e7e34;
            --pink: #db2777;
            --orange: #f97316;
            --yellow: #eab308;
            --amber: #f59e0b;

            /* Neutral Colors */
            --light: #f8fafc;
            --grey-light: #e2e8f0;
            --grey: #f1f5f9;
            --dark-grey: #94a3b8;
            --dark: #1f2937;
            --white: #ffffff;
            --red: #dc2626;

            /* Text ramp */
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;

            /* Gradient combinations */
            --gradient-blue: linear-gradient(135deg, #28a745, #1e7e34);
            --gradient-orange: linear-gradient(135deg, #f97316, #ea580c);
            --gradient-teal: linear-gradient(135deg, #14b8a6, #0d9488);
            --gradient-purple: linear-gradient(135deg, #34c759, #1e7e34);
        }

        body {
            font-family: var(--poppins);
            background: var(--grey);
            min-height: 100vh;
            color: var(--dark);
        }
           #content main .head-title .left .breadcrumb {
            display: flex;
            align-items: center;
            grid-gap: 8px; /* tighter spacing */
            list-style: none;
            padding: 0;
            margin: 0;
        }

        #content main {
            width: 100%;
            padding: 36px 24px;
            font-family: var(--poppins);
            max-height: calc(100vh - 56px);
            overflow-y: auto;
        }
        #content main .head-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            grid-gap: 16px;
            flex-wrap: wrap;
        }
        #content main .head-title .left h1 {
            font-size: 36px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        #content main .head-title .left .breadcrumb li a {
            color: var(--dark);
            text-decoration: none;
            pointer-events: unset; /* allow clicking */
        }

        #content main .head-title .left .breadcrumb li a.active {
            color: #28a745; /* your green */
            font-weight: 500;
        }



        /* Upload Section */
        .upload-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        .upload-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            backdrop-filter: blur(10px);
        }

        .upload-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .upload-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .upload-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 16px 32px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
        }

        .upload-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
        }

        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-card.completed { border-left-color: #10b981; }
        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.total { border-left-color: #3b82f6; }
        .stat-card.progress { border-left-color: #8b5cf6; }

        .stat-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
        }

        .stat-trend.positive { color: #10b981; }
        .stat-trend.negative { color: #ef4444; }
        .stat-trend.neutral { color: #6b7280; }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card.completed .stat-icon { background: #10b981; }
        .stat-card.pending .stat-icon { background: #f59e0b; }
        .stat-card.total .stat-icon { background: #3b82f6; }
        .stat-card.progress .stat-icon { background: #8b5cf6; }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 12px 20px;
            border: none;
            background: #f3f4f6;
            color: #6b7280;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-tab.active {
            background: #3b82f6;
            color: white;
        }

        .filter-tab:hover:not(.active) {
            background: #e5e7eb;
        }

        .badge {
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }

        /* Categories Grid */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .category-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            border-color: #3b82f6;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .category-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .status-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }

        .status-badge.completed {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-badge.empty {
            background: #f1f5f9;
            color: #64748b;
        }

        .category-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
        }

        .category-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
            color: #6b7280;
        }

        .meta-item.required {
            color: #ef4444;
            font-weight: 500;
        }

        .category-deadline {
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 16px;
        }

        .category-actions {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        /* Content sections */
        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        /* Modals */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 30px 30px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .modal-subtitle {
            color: #6b7280;
            font-size: 14px;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 25px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            transition: all 0.2s;
            padding: 8px;
            border-radius: 50%;
        }

        .modal-close:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .modal-body {
            padding: 30px;
        }

        /* File display */
        .files-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .file-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .file-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .file-header {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .file-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .file-pdf { background: #ef4444; }
        .file-doc { background: #2563eb; }
        .file-docx { background: #2563eb; }
        .file-xls { background: #10b981; }
        .file-xlsx { background: #10b981; }
        .file-default { background: #6b7280; }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .file-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            color: #6b7280;
            flex-wrap: wrap;
        }

        .file-description {
            margin-top: 12px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            font-size: 14px;
            color: #374151;
            border-left: 3px solid #3b82f6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }

            .stats-overview {
                grid-template-columns: 1fr;
            }

            .upload-section {
                padding: 30px 20px;
            }

            .modal-content {
                width: 95%;
                margin: 10px;
            }
        }
    </style>

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
    <?php include 'components/sidebar.html'; ?>

    <section id="content">
        <?php include 'components/navbar.html'; ?>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>My Submission</h1>
                    <ul class="breadcrumb">
                        <li>Admin</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="" href="#" style="color: #28a745;">My Submission</a></li>
                    </ul>
                </div>
            </div>

            <br>

            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stat-card completed">
                    <div class="stat-info">
                        <div class="stat-content">
                            <div class="stat-number"><?php echo count($submissionStats); ?></div>
                            <div class="stat-label">Completed Submissions</div>
                            <div class="stat-trend positive">
                                <i class='bx bx-trending-up'></i>
                                <span>This semester</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class='bx bxs-check-circle'></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-info">
                        <div class="stat-content">
                            <div class="stat-number"><?php echo count($pendingSubmissions); ?></div>
                            <div class="stat-label">Pending Submissions</div>
                            <div class="stat-trend <?php echo count($pendingSubmissions) > 0 ? 'negative' : 'neutral'; ?>">
                                <i class='bx <?php echo count($pendingSubmissions) > 0 ? 'bx-trending-down' : 'bx-minus'; ?>'></i>
                                <span>Required files</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class='bx bxs-time-five'></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card total">
                    <div class="stat-info">
                        <div class="stat-content">
                            <div class="stat-number"><?php echo array_sum(array_column($submissionStats, 'file_count')); ?></div>
                            <div class="stat-label">Total Files Uploaded</div>
                            <div class="stat-trend positive">
                                <i class='bx bx-trending-up'></i>
                                <span>All time</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class='bx bxs-folder'></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card progress">
                    <div class="stat-info">
                        <div class="stat-content">
                            <?php 
                            $requiredCategories = array_filter($fileCategories, function($cat) { return $cat['required']; });
                            $completedRequired = 0;
                            foreach ($requiredCategories as $key => $cat) {
                                foreach ($submissionStats as $stat) {
                                    if ($stat['category'] === $key && $stat['file_count'] > 0) {
                                        $completedRequired++;
                                        break;
                                    }
                                }
                            }
                            $progressPercentage = count($requiredCategories) > 0 ? round(($completedRequired / count($requiredCategories)) * 100) : 100;
                            ?>
                            <div class="stat-number"><?php echo $progressPercentage; ?>%</div>
                            <div class="stat-label">Completion Rate</div>
                            <div class="stat-trend <?php echo $progressPercentage >= 80 ? 'positive' : ($progressPercentage >= 50 ? 'neutral' : 'negative'); ?>">
                                <i class='bx <?php echo $progressPercentage >= 80 ? 'bx-trending-up' : ($progressPercentage >= 50 ? 'bx-minus' : 'bx-trending-down'); ?>'></i>
                                <span>Required submissions</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class='bx bxs-pie-chart-alt-2'></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-tabs">
                    <button class="filter-tab active" onclick="showSection('categories')">
                        <i class='bx bx-grid-alt'></i> All Categories
                    </button>
                    <button class="filter-tab" onclick="showSection('pending')">
                        <i class='bx bx-time-five'></i> Pending
                        <?php if (count($pendingSubmissions) > 0): ?>
                            <span class="badge"><?php echo count($pendingSubmissions); ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="filter-tab" onclick="showSection('completed')">
                        <i class='bx bx-check-circle'></i> Completed
                    </button>
                </div>
            </div>

            <!-- Categories Section -->
            <div id="categories-section" class="content-section active">
                <div class="categories-grid">
                    <?php foreach ($fileCategories as $key => $category): ?>
                        <?php
                        // Find matching statistics using the database file type name
                        $categoryStats = array_filter($submissionStats, function($stat) use ($category) {
                            return $stat['category'] === $category['db_name'];
                        });
                        $totalFiles = array_sum(array_column($categoryStats, 'file_count'));
                        $lastSubmission = null;
                        if (!empty($categoryStats)) {
                            $lastSubmission = max(array_column($categoryStats, 'last_submission'));
                        }
                        ?>
                        <div class="category-card" data-category="<?php echo $key; ?>" onclick="showCategoryDetails('<?php echo $key; ?>')">
                            <div class="category-header">
                                <div class="category-icon" style="background-color: <?php echo $category['color']; ?>">
                                    <i class='bx <?php echo $category['icon']; ?>'></i>
                                </div>
                                <div class="status-badge <?php echo $totalFiles > 0 ? 'completed' : ($category['required'] ? 'pending' : 'empty'); ?>">
                                    <i class='bx <?php echo $totalFiles > 0 ? 'bx-check' : ($category['required'] ? 'bx-time' : 'bx-minus'); ?>'></i>
                                </div>
                            </div>
                            
                            <div class="category-title"><?php echo htmlspecialchars($category['name']); ?></div>
                            
                            <div class="category-meta">
                                <div class="meta-item">
                                    <i class='bx bx-file'></i>
                                    <span><?php echo $totalFiles; ?> files</span>
                                </div>
                                <div class="meta-item">
                                    <i class='bx bx-calendar'></i>
                                    <span><?php echo $category['frequency']; ?></span>
                                </div>
                                <?php if ($category['required']): ?>
                                    <div class="meta-item required">
                                        <i class='bx bx-star'></i>
                                        <span>Required</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="category-deadline">
                                <i class='bx bx-time-five'></i>
                                <span><?php echo $category['deadline']; ?></span>
                            </div>
                            
                            <?php if ($lastSubmission): ?>
                                <div class="category-deadline" style="background: #ecfdf5; color: #059669;">
                                    <i class='bx bx-check-circle'></i>
                                    <span>Last: <?php echo date('M j, Y', strtotime($lastSubmission)); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="category-actions">
                                <button class="btn-small btn-secondary" onclick="event.stopPropagation(); showCategoryDetails('<?php echo $key; ?>')">
                                    <i class='bx bx-show'></i>
                                    View Files
                                </button>
                                <button class="btn-small btn-primary" onclick="event.stopPropagation(); openUploadModal('<?php echo $key; ?>')">
                                    <i class='bx bx-plus'></i>
                                    Upload
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Pending Section -->
            <div id="pending-section" class="content-section">
                <?php if (empty($pendingSubmissions)): ?>
                    <div class="empty-state">
                        <i class='bx bx-check-double empty-icon'></i>
                        <h3>All caught up!</h3>
                        <p>You have no pending required submissions at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="categories-grid">
                        <?php foreach ($pendingSubmissions as $pending): ?>
                            <div class="category-card" style="border-color: #f59e0b;">
                                <div class="category-header">
                                    <div class="category-icon" style="background-color: <?php echo $fileCategories[$pending['category']]['color']; ?>">
                                        <i class='bx <?php echo $fileCategories[$pending['category']]['icon']; ?>'></i>
                                    </div>
                                    <div class="status-badge pending">
                                        <i class='bx bx-time'></i>
                                    </div>
                                </div>
                                
                                <div class="category-title"><?php echo htmlspecialchars($pending['name']); ?></div>
                                
                                <div class="category-meta">
                                    <div class="meta-item">
                                        <i class='bx bx-calendar'></i>
                                        <span><?php echo $pending['semester']; ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class='bx bx-calendar-alt'></i>
                                        <span><?php echo $pending['academic_year']; ?></span>
                                    </div>
                                    <div class="meta-item required">
                                        <i class='bx bx-star'></i>
                                        <span>Required</span>
                                    </div>
                                </div>
                                
                                <div class="category-deadline">
                                    <i class='bx bx-time-five'></i>
                                    <span><?php echo $pending['deadline']; ?></span>
                                </div>
                                
                                <div class="category-actions">
                                    <button class="btn-small btn-primary" onclick="openUploadModal('<?php echo $pending['category']; ?>')">
                                        <i class='bx bx-upload'></i>
                                        Upload Now
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completed Section -->
            <div id="completed-section" class="content-section">
                <?php if (empty($submissionStats)): ?>
                    <div class="empty-state">
                        <i class='bx bx-folder-open empty-icon'></i>
                        <h3>No submissions yet</h3>
                        <p>Your completed submissions will appear here once you start uploading files.</p>
                    </div>
                <?php else: ?>
                    <div class="categories-grid">
                        <?php 
                        $groupedStats = [];
                        foreach ($submissionStats as $stat) {
                            // Find the category key for this database file type
                            $categoryKey = null;
                            foreach ($fileCategories as $key => $category) {
                                if ($category['db_name'] === $stat['category']) {
                                    $categoryKey = $key;
                                    break;
                                }
                            }
                            
                            if ($categoryKey) {
                                if (!isset($groupedStats[$categoryKey])) {
                                    $groupedStats[$categoryKey] = [];
                                }
                                $groupedStats[$categoryKey][] = $stat;
                            }
                        }
                        ?>
                        <?php foreach ($groupedStats as $categoryKey => $categoryStats): ?>
                            <?php if (isset($fileCategories[$categoryKey])): ?>
                                <div class="category-card" onclick="showCategoryDetails('<?php echo $categoryKey; ?>')">
                                    <div class="category-header">
                                        <div class="category-icon" style="background-color: <?php echo $fileCategories[$categoryKey]['color']; ?>">
                                            <i class='bx <?php echo $fileCategories[$categoryKey]['icon']; ?>'></i>
                                        </div>
                                        <div class="status-badge completed">
                                            <i class='bx bx-check'></i>
                                        </div>
                                    </div>
                                    
                                    <div class="category-title"><?php echo htmlspecialchars($fileCategories[$categoryKey]['name']); ?></div>
                                    
                                    <div class="category-meta">
                                        <div class="meta-item">
                                            <i class='bx bx-file'></i>
                                            <span><?php echo array_sum(array_column($categoryStats, 'file_count')); ?> files</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class='bx bx-calendar'></i>
                                            <span><?php echo count($categoryStats); ?> periods</span>
                                        </div>
                                    </div>
                                    
                                    <div class="category-deadline" style="background: #ecfdf5; color: #059669;">
                                        <i class='bx bx-check-circle'></i>
                                        <span>Last: <?php echo date('M j, Y', strtotime(max(array_column($categoryStats, 'last_submission')))); ?></span>
                                    </div>
                                    
                                    <div class="category-actions">
                                        <button class="btn-small btn-secondary" onclick="event.stopPropagation(); showCategoryDetails('<?php echo $categoryKey; ?>')">
                                            <i class='bx bx-show'></i>
                                            View All Files
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Category Details Modal -->
            <div class="modal" id="categoryModal">
                <div class="modal-content">
                    <button class="modal-close" onclick="closeCategoryModal()">
                        <i class='bx bx-x'></i>
                    </button>
                    
                    <div class="modal-header">
                        <h2 class="modal-title" id="modalTitle">
                            <i class='bx bxs-trophy'></i>
                            Category Files
                        </h2>
                        <p class="modal-subtitle" id="modalSubtitle">View and manage your uploaded files for this category</p>
                    </div>
                    
                    <div class="modal-body">
                        <div id="categoryFiles" class="files-list">
                            <!-- Files will be loaded here via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Modal -->
            <div class="modal" id="uploadModal">
                <div class="modal-content">
                    <button class="modal-close" onclick="closeUploadModal()">
                        <i class='bx bx-x'></i>
                    </button>
                    
                    <div class="modal-header">
                        <h2 class="modal-title">
                            <i class='bx bx-upload'></i>
                            Upload Document
                        </h2>
                        <p class="modal-subtitle">Upload your files for submission review and tracking</p>
                    </div>
                    
                    <div class="modal-body">
                        <form class="upload-form" id="uploadForm" enctype="multipart/form-data">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <label style="display: block; font-weight: 500; color: #374151; font-size: 14px; margin-bottom: 8px;">
                                        Document Category <span style="color: #ef4444;">*</span>
                                    </label>
                                    <select id="documentCategory" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 14px; background: white;">
                                        <option value="">Select document category</option>
                                        <?php foreach ($fileCategories as $key => $category): ?>
                                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label style="display: block; font-weight: 500; color: #374151; font-size: 14px; margin-bottom: 8px;">
                                        Academic Year <span style="color: #ef4444;">*</span>
                                    </label>
                                    <select id="academicYear" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 14px; background: white;">
                                        <option value="">Select academic year</option>
                                        <?php 
                                        $currentYear = date('Y');
                                        for ($i = -2; $i <= 2; $i++): 
                                            $year = $currentYear + $i;
                                        ?>
                                            <option value="<?php echo $year; ?>" <?php echo $i === 0 ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; font-size: 14px; margin-bottom: 8px;">
                                    Semester Period <span style="color: #ef4444;">*</span>
                                </label>
                                <select id="semesterPeriod" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 14px; background: white;">
                                    <option value="">Select semester</option>
                                    <option value="1st Semester">1st Semester</option>
                                    <option value="2nd Semester">2nd Semester</option>
                                </select>
                            </div>

                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; font-size: 14px; margin-bottom: 8px;">Description (Optional)</label>
                                <textarea id="description" placeholder="Add any additional notes..." rows="3" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 14px; background: white; resize: vertical;"></textarea>
                            </div>

                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; font-size: 14px; margin-bottom: 8px;">
                                    Upload File <span style="color: #ef4444;">*</span>
                                </label>
                                <div id="uploadArea" style="border: 3px dashed #d1d5db; border-radius: 16px; padding: 40px 20px; text-align: center; background: #fafbfc; transition: all 0.3s; cursor: pointer;">
                                    <i class='bx bx-cloud-upload' style="font-size: 48px; color: #9ca3af; margin-bottom: 16px;"></i>
                                    <div style="font-size: 16px; font-weight: 500; color: #374151; margin-bottom: 8px;">Drag and drop your file here</div>
                                    <div style="font-size: 14px; color: #6b7280; margin-bottom: 16px;">or click to browse files</div>
                                    <button type="button" style="background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                                        <i class='bx bx-upload'></i>
                                        Browse Files
                                    </button>
                                    <input type="file" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt,.zip,.rar" style="display: none;">
                                </div>
                            </div>

                            <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                                <button type="button" onclick="closeUploadModal()" style="padding: 12px 24px; background: #f3f4f6; color: #374151; border: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                    <i class='bx bx-x'></i>
                                    Cancel
                                </button>
                                <button type="submit" id="submitUpload" disabled style="padding: 12px 24px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; opacity: 0.5;">
                                    <i class='bx bx-upload'></i>
                                    Upload File
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </section>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script>
        window.userDepartmentId = <?php echo json_encode($userDepartmentId); ?>;
        window.fileCategories = <?php echo json_encode($fileCategories); ?>;
        window.submissionStats = <?php echo json_encode($mappedSubmissionStats); ?>;
        window.currentUserId = <?php echo json_encode($currentUser['id']); ?>;

        let selectedFile = null;
        let currentCategory = null;

        // Show section
        function showSection(section) {
            document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');

            document.querySelectorAll('.content-section').forEach(sec => sec.classList.remove('active'));
            document.getElementById(section + '-section').classList.add('active');
        }

        // Show category details
        function showCategoryDetails(category) {
            const modal = document.getElementById('categoryModal');
            const title = document.getElementById('modalTitle');
            const subtitle = document.getElementById('modalSubtitle');
            const filesContainer = document.getElementById('categoryFiles');

            const categoryInfo = window.fileCategories[category];
            title.innerHTML = `<i class='bx ${categoryInfo.icon}'></i> ${categoryInfo.name}`;
            subtitle.textContent = `Files uploaded for ${categoryInfo.name}`;

            // Show loading state
            filesContainer.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #6b7280;">
                    <i class='bx bx-loader-alt bx-spin' style="font-size: 32px; margin-bottom: 16px;"></i>
                    <div>Loading files...</div>
                </div>
            `;

            // Fetch files for this category
            fetch(`script/get_category_files.php?category=${category}&user_id=${window.currentUserId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.files.length > 0) {
                        filesContainer.innerHTML = data.files.map(file => {
                            const fileExt = file.file_name.split('.').pop().toLowerCase();
                            const fileClass = getFileClass(fileExt);
                            const fileIcon = getFileIcon(fileExt);
                            
                            return `
                                <div class="file-item">
                                    <div class="file-header">
                                        <div class="file-icon ${fileClass}">
                                            <i class='bx ${fileIcon}'></i>
                                        </div>
                                        <div class="file-details">
                                            <div class="file-name">${file.file_name}</div>
                                            <div class="file-meta">
                                                <span><i class='bx bx-calendar'></i> ${file.academic_year} - ${file.semester_period}</span>
                                                <span><i class='bx bx-file'></i> ${formatFileSize(file.file_size)}</span>
                                                <span><i class='bx bx-time'></i> ${formatDate(file.uploaded_at)}</span>
                                            </div>
                                        </div>
                                    </div>
                                    ${file.description ? `<div class="file-description">${file.description}</div>` : ''}
                                </div>
                            `;
                        }).join('');
                    } else {
                        filesContainer.innerHTML = `
                            <div class="empty-state">
                                <i class='bx bx-folder-open empty-icon'></i>
                                <h3>No files uploaded yet</h3>
                                <p>Upload your first file for this category to get started.</p>
                                <button class="upload-btn" onclick="closeCategoryModal(); openUploadModal('${category}')">
                                    <i class='bx bx-plus'></i>
                                    Upload File
                                </button>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching files:', error);
                    filesContainer.innerHTML = `
                        <div class="empty-state">
                            <i class='bx bx-error empty-icon'></i>
                            <h3>Error loading files</h3>
                            <p>Please try again later.</p>
                        </div>
                    `;
                });

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Utility functions
        function getFileClass(ext) {
            const classMap = {
                'pdf': 'file-pdf',
                'doc': 'file-doc', 'docx': 'file-docx',
                'xls': 'file-xls', 'xlsx': 'file-xlsx'
            };
            return classMap[ext] || 'file-default';
        }

        function getFileIcon(ext) {
            const iconMap = {
                'pdf': 'bxs-file-pdf',
                'doc': 'bxs-file-doc', 'docx': 'bxs-file-doc',
                'xls': 'bxs-spreadsheet', 'xlsx': 'bxs-spreadsheet',
                'ppt': 'bxs-file-blank', 'pptx': 'bxs-file-blank',
                'jpg': 'bxs-file-image', 'jpeg': 'bxs-file-image',
                'png': 'bxs-file-image', 'gif': 'bxs-file-image',
                'txt': 'bxs-file-txt',
                'zip': 'bxs-file-archive', 'rar': 'bxs-file-archive',
                'mp4': 'bxs-videos', 'avi': 'bxs-videos',
                'mp3': 'bxs-music', 'wav': 'bxs-music'
            };
            return iconMap[ext] || 'bxs-file';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }

        // Close modals
        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('active');
            resetUploadForm();
            document.body.style.overflow = 'auto';
        }

        // Open upload modal
        function openUploadModal(category = null) {
            const modal = document.getElementById('uploadModal');
            if (category) {
                document.getElementById('documentCategory').value = category;
                currentCategory = category;
            }
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Reset upload form
        function resetUploadForm() {
            document.getElementById('uploadForm').reset();
            selectedFile = null;
            document.getElementById('submitUpload').disabled = true;
            document.getElementById('submitUpload').style.opacity = '0.5';
            
            // Reset upload area
            const uploadArea = document.getElementById('uploadArea');
            uploadArea.innerHTML = `
                <i class='bx bx-cloud-upload' style="font-size: 48px; color: #9ca3af; margin-bottom: 16px;"></i>
                <div style="font-size: 16px; font-weight: 500; color: #374151; margin-bottom: 8px;">Drag and drop your file here</div>
                <div style="font-size: 14px; color: #6b7280; margin-bottom: 16px;">or click to browse files</div>
                <button type="button" style="background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class='bx bx-upload'></i>
                    Browse Files
                </button>
            `;
        }

        // Setup drag and drop and form handling
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            
            uploadArea.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileSelect(e.target.files[0]);
                }
            });

            // Drag and drop functionality
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.style.background = '#e0f2fe';
                uploadArea.style.borderColor = '#0284c7';
            });

            uploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadArea.style.background = '#fafbfc';
                uploadArea.style.borderColor = '#d1d5db';
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.style.background = '#fafbfc';
                uploadArea.style.borderColor = '#d1d5db';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelect(files[0]);
                }
            });

            // Form submission
            document.getElementById('uploadForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const category = document.getElementById('documentCategory').value;
                const academicYear = document.getElementById('academicYear').value;
                const semester = document.getElementById('semesterPeriod').value;
                
                if (!category || !academicYear || !semester || !selectedFile) {
                    alert('Please fill in all required fields and select a file');
                    return;
                }
                
                const submitBtn = document.getElementById('submitUpload');
                const originalContent = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Uploading...';
                
                try {
                    const formData = new FormData();
                    formData.append('file', selectedFile);
                    formData.append('category', category);
                    formData.append('academic_year', academicYear);
                    formData.append('semester', semester);
                    formData.append('description', document.getElementById('description').value);
                    
                    const response = await fetch('script/upload_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('File uploaded successfully!');
                        closeUploadModal();
                        location.reload(); // Refresh to show new data
                    } else {
                        throw new Error(result.message || 'Upload failed');
                    }
                    
                } catch (error) {
                    console.error('Upload error:', error);
                    alert('Upload failed: ' + error.message);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalContent;
                }
            });
        });

        function handleFileSelect(file) {
            const maxSize = 50 * 1024 * 1024; // 50MB
            const allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar'];
            const fileExt = file.name.split('.').pop().toLowerCase();
            
            if (file.size > maxSize) {
                alert('File size exceeds 50MB limit');
                return;
            }
            
            if (!allowedTypes.includes(fileExt)) {
                alert('File type not allowed. Allowed types: ' + allowedTypes.join(', '));
                return;
            }
            
            selectedFile = file;
            const submitBtn = document.getElementById('submitUpload');
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            
            // Update upload area
            const uploadArea = document.getElementById('uploadArea');
            const fileIcon = getFileIcon(fileExt);
            const fileClass = getFileClass(fileExt);
            
            uploadArea.innerHTML = `
                <div class="file-icon ${fileClass}" style="width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; margin: 0 auto 16px;">
                    <i class='bx ${fileIcon}'></i>
                </div>
                <div style="font-size: 16px; font-weight: 500; color: #374151; margin-bottom: 8px; word-break: break-word;">${file.name}</div>
                <div style="font-size: 14px; color: #6b7280; margin-bottom: 16px;">${formatFileSize(file.size)} - Click to change file</div>
                <button type="button" style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class='bx bx-edit'></i>
                    Change File
                </button>
            `;
        }

        // Close modals on outside click
        document.getElementById('categoryModal').addEventListener('click', (e) => {
            if (e.target.id === 'categoryModal') {
                closeCategoryModal();
            }
        });
        
        document.getElementById('uploadModal').addEventListener('click', (e) => {
            if (e.target.id === 'uploadModal') {
                closeUploadModal();
            }
        });

        // Close modals on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCategoryModal();
                closeUploadModal();
            }
        });
    </script>
</body>
</html>
