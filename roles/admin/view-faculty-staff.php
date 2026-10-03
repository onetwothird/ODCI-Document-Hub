<?php include 'script/faculty-staff.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Faculty Staff - CVSU Naic</title>
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/view-staff.css">
    <link rel="stylesheet" href="assets/css/faculty-staff.css">


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
                    <h1>Faculty Staff Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Admin</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Faculty Staff</a></li>
                    </ul>
                </div>
            </div>

            <div class="department-header">
                <div class="department-info">
                    <div class="department-icon">
                        <?php echo strtoupper(substr($currentAdmin['department_code'] ?? 'DEPT', 0, 2)); ?>
                    </div>
                    <div>
                        <h2 class="department-title"><?php echo htmlspecialchars($currentAdmin['department_name'] ?? 'Department'); ?></h2>
                        <p class="department-code"><?php echo htmlspecialchars($currentAdmin['department_code'] ?? 'DEPT'); ?> Department</p>
                    </div>
                </div>
                <div class="department-stats">
                    <span class="dept-stat-value"><?php echo count($facultyStaff); ?></span>
                    <span class="dept-stat-label">Faculty Members</span>
                </div>
            </div>

            <?php if (!empty($facultyStaff)): ?>
                <?php
                // Calculate summary statistics
                $totalFaculty = count($facultyStaff);
                $highCompletion = 0;
                $mediumCompletion = 0;
                $lowCompletion = 0;
                $recentlyActive = 0;
                $emailVerified = 0;
                
                foreach ($facultyStaff as $faculty) {
                    $stats = getFacultySubmissionStats($pdo, $faculty['id']);
                    $completionRate = $stats['completion_rate'];
                    
                    if ($completionRate >= 80) $highCompletion++;
                    elseif ($completionRate >= 50) $mediumCompletion++;
                    else $lowCompletion++;
                    
                    if ($faculty['recently_active']) $recentlyActive++;
                    if ($faculty['email_verified']) $emailVerified++;
                }
                ?>

                <div class="summary-cards">
                    <div class="summary-card">
                        <span class="summary-card-value"><?php echo $totalFaculty; ?></span>
                        <span class="summary-card-label">Total Faculty</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card-value" style="color: var(--success-color);"><?php echo $highCompletion; ?></span>
                        <span class="summary-card-label">High Performance</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card-value" style="color: var(--warning-color);"><?php echo $mediumCompletion; ?></span>
                        <span class="summary-card-label">Medium Performance</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card-value" style="color: var(--info-color);"><?php echo $recentlyActive; ?></span>
                        <span class="summary-card-label">Recently Active</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card-value" style="color: var(--primary-color);"><?php echo $emailVerified; ?></span>
                        <span class="summary-card-label">Email Verified</span>
                    </div>
                </div>

                <div class="bulk-actions" id="bulkActions">
                    <label class="bulk-select-all">
                        <input type="checkbox" id="selectAll"> 
                        <span id="selectedCount">0</span> selected
                    </label>
                    <div class="bulk-actions-buttons">
                        <button class="btn btn-primary" onclick="bulkSendMessage()">
                            <i class='bx bx-message'></i> Send Message
                        </button>
                        <button class="btn btn-secondary" onclick="exportSelected()">
                            <i class='bx bx-download'></i> Export
                        </button>
                    </div>
                </div>

                <div class="filters-section">
                    <div class="filters-row">
                        <div class="search-box">
                            <input type="text" id="facultySearch" class="search-input" placeholder="Search faculty by name, position, or employee ID...">
                        </div>
                        
                        <select id="completionFilter" class="filter-select">
                            <option value="">All Performance Levels</option>
                            <option value="high">High (80%+)</option>
                            <option value="medium">Medium (50-79%)</option>
                            <option value="low">Low (<50%)</option>
                        </select>
                        
                        <select id="activityFilter" class="filter-select">
                            <option value="">All Activity Status</option>
                            <option value="recent">Recently Active</option>
                            <option value="inactive">Not Recently Active</option>
                        </select>

                        <select id="verificationFilter" class="filter-select">
                            <option value="">All Verification Status</option>
                            <option value="verified">Email Verified</option>
                            <option value="unverified">Not Verified</option>
                        </select>
                        
                        <div class="view-toggle">
                            <button class="view-btn active" data-view="detailed">
                                <i class='bx bx-list-ul'></i>
                            </button>
                            <button class="view-btn" data-view="compact">
                                <i class='bx bx-grid-alt'></i>
                            </button>
                        </div>
                    </div>
                </div>
                

                <div class="faculty-list" id="facultyList">
                    <?php foreach ($facultyStaff as $faculty): ?>
                        <?php 
                        $stats = getFacultySubmissionStats($pdo, $faculty['id']);
                        $completionRate = $stats['completion_rate'];
                        $completionClass = $completionRate >= 80 ? 'high' : ($completionRate >= 50 ? 'medium' : 'low');
                        $isRecentlyActive = $faculty['recently_active'];
                        $isOnline = $faculty['is_online'];
                        $isEmailVerified = $faculty['email_verified'];
                        
                        // FIXED - Handle profile image with proper error handling
                        $profileImageUrl = getProfileImageUrl($faculty['profile_image']);
                        $originalPath = $faculty['profile_image'];
                        ?>
                        
                         <div class="faculty-card <?php echo $completionClass; ?>-completion" 
                            data-name="<?php echo htmlspecialchars(strtolower($faculty['full_name'])); ?>"
                            data-position="<?php echo htmlspecialchars(strtolower($faculty['position'] ?? '')); ?>"
                            data-employee-id="<?php echo htmlspecialchars(strtolower($faculty['employee_id'] ?? '')); ?>"
                            data-completion="<?php echo $completionClass; ?>"
                            data-activity="<?php echo $isRecentlyActive ? 'recent' : 'inactive'; ?>"
                            data-verification="<?php echo $isEmailVerified ? 'verified' : 'unverified'; ?>"
                            data-faculty-id="<?php echo $faculty['id']; ?>">
                            
                            
                            <input type="checkbox" class="faculty-checkbox" value="<?php echo $faculty['id']; ?>">
                            
                            <!-- FIXED - Enhanced image handling with multiple fallbacks -->
                            <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" 
                                alt="<?php echo htmlspecialchars($faculty['full_name']); ?>" 
                                class="faculty-avatar <?php echo $isOnline ? 'online' : ($isRecentlyActive ? 'away' : 'offline'); ?>"
                                data-original-path="<?php echo htmlspecialchars($originalPath ?: ''); ?>"
                                onerror="handleImageError(this)"
                                onload="console.log('Image loaded successfully: ' + this.src)">

                            <div class="faculty-details">
                                <h3 class="faculty-name">
                                    <?php echo htmlspecialchars($faculty['full_name']); ?>
                                    <?php if ($isEmailVerified): ?>
                                        <i class='bx bx-badge-check verified-badge' title="Email Verified"></i>
                                    <?php endif; ?>
                                </h3>
                                
                                <p class="faculty-position"><?php echo htmlspecialchars($faculty['position'] ?? 'Faculty Member'); ?></p>
                                
                                <div class="faculty-meta">
                                    <?php if ($faculty['employee_id']): ?>
                                        <span class="meta-item">
                                            <i class='bx bx-id-card'></i>
                                            <?php echo htmlspecialchars($faculty['employee_id']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="meta-item">
                                        <i class='bx bx-envelope'></i>
                                        <?php echo htmlspecialchars($faculty['email']); ?>
                                    </span>
                                    
                                    <?php if ($faculty['phone']): ?>
                                        <span class="meta-item">
                                            <i class='bx bx-phone'></i>
                                            <?php echo htmlspecialchars($faculty['phone']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="meta-item">
                                        <i class='bx bx-time'></i>
                                        <?php echo $faculty['last_login_formatted']; ?>
                                    </span>
                                </div>
                                
                                <div class="faculty-stats">
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo $stats['total_submitted']; ?>/<?php echo $stats['total_required']; ?></span>
                                        <span class="stat-label">Documents</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo $stats['completion_rate']; ?>%</span>
                                        <span class="stat-label">Complete</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo $stats['submission_streak']; ?></span>
                                        <span class="stat-label">Streak</span>
                                    </div>
                                </div>
                                
                                <div class="progress-container">
                                    <div class="progress-header">
                                        <span class="progress-label">Completion Progress</span>
                                        <span class="progress-percentage"><?php echo $stats['completion_rate']; ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill <?php echo $completionClass; ?>" 
                                             style="width: <?php echo $stats['completion_rate']; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="status-indicators">
                                    <?php if ($completionRate >= 90): ?>
                                        <span class="status-badge complete">
                                            <i class='bx bx-check-circle'></i> Excellent
                                        </span>
                                    <?php elseif ($completionRate >= 70): ?>
                                        <span class="status-badge partial">
                                            <i class='bx bx-time-five'></i> Good
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge pending">
                                            <i class='bx bx-error-circle'></i> Needs Attention
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($isRecentlyActive): ?>
                                        <span class="status-badge active">
                                            <i class='bx bx-pulse'></i> Active
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="status-badge <?php echo $isEmailVerified ? 'verified' : 'unverified'; ?>">
                                        <i class='bx <?php echo $isEmailVerified ? 'bx-check-shield' : 'bx-shield-x'; ?>'></i>
                                        <?php echo $isEmailVerified ? 'Verified' : 'Unverified'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="submission_tracker.php?user_id=<?php echo $faculty['id']; ?>" class="btn-view">
                                    <i class='bx bx-show'></i> View Submissions
                                </a>
                                <button class="btn-message" onclick="openMessageModal(<?php echo $faculty['id']; ?>, '<?php echo htmlspecialchars($faculty['full_name'], ENT_QUOTES); ?>')">
                                    <i class='bx bx-message'></i> Send Message
                                </button>
                                <button class="btn-stats" onclick="showDetailedStats(<?php echo $faculty['id']; ?>)">
                                    <i class='bx bx-bar-chart'></i> View Stats
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-faculty">
                    <i class='bx bx-user-x'></i>
                    <h3>No Faculty Staff Found</h3>
                    <p>There are currently no approved faculty members in the <strong><?php echo htmlspecialchars($currentAdmin['department_name'] ?? 'Department'); ?></strong> department.</p>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <!-- Message Modal -->
    <div class="message-modal" id="messageModal">
        <div class="message-modal-content">
            <div class="modal-header">
                <h3>Send Message</h3>
                <button onclick="closeMessageModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <form id="messageForm">
                <input type="hidden" id="recipientId" name="recipient_id">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">To:</label>
                    <input type="text" id="recipientName" readonly style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Subject:</label>
                    <input type="text" name="subject" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px;" placeholder="Enter subject (optional)">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Message:</label>
                    <textarea name="message" rows="6" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; resize: vertical;" placeholder="Enter your message..." required></textarea>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Priority:</label>
                    <select name="priority" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px;">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeMessageModal()" style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
                    <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer;">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/view-staff.js"></script>
</body>
</html>