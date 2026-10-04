<?php include 'script/faculty-staff.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Faculty Staff - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
<!-- assets/css/view-staff.css was dropped here: it was a verbatim copy of
     base.css that re-declared :root and overrode the shared theme tokens for
     the whole page. base.css above is the single source for that shell. -->
<link rel="stylesheet" href="assets/css/faculty-staff.css?v=<?= time() ?>">


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

            <?php
    // Compute each faculty's stats once and reuse. The previous layout called
    // getFacultySubmissionStats() again inside the render loop, so every row ran
    // the same ~5 queries twice and getDepartmentStats() ran them a third time.
    // Done before the markup because both the department banner and the summary
    // cards read from these totals.
    $facultyStats = [];
    $totalFaculty = count($facultyStaff);
    $highCompletion = 0;
    $mediumCompletion = 0;
    $lowCompletion = 0;
    $activeCount = 0;
    $verifiedCount = 0;

    foreach ($facultyStaff as $faculty) {
        $facultyStats[$faculty['id']] = getFacultySubmissionStats($pdo, $faculty['id']);

        $completionRate = $facultyStats[$faculty['id']]['completion_rate'];
        if ($completionRate >= 80) {
            $highCompletion++;
        } elseif ($completionRate >= 50) {
            $mediumCompletion++;
        } else {
            $lowCompletion++;
        }

        if ($faculty['recently_active']) {
            $activeCount++;
        }
        if ($faculty['email_verified']) {
            $verifiedCount++;
        }
    }
    ?>

            <?php /* Department banner: one line of identity (icon, name, code chip)
                     beside a row of inline metric chips, so the whole thing reads
                     as a single statement instead of a stack of fragments. */ ?>
            <div class="department-header">
                <div class="department-info">
                    <div class="department-icon">
                        <i class='bx bx-building'></i>
                    </div>
                    <h2 class="department-title"><?php echo htmlspecialchars($currentAdmin['department_name'] ?? 'Department'); ?></h2>
                    <span class="department-code"><?php echo htmlspecialchars($currentAdmin['department_code'] ?? 'DEPT'); ?></span>
                </div>

                <div class="department-stats">
                    <span class="dept-stat">
                        <i class='bx bx-group'></i>
                        <b><?php echo $totalFaculty; ?></b> Faculty
                    </span>
                    <span class="dept-stat">
                        <i class='bx bx-badge-check'></i>
                        <b><?php echo $verifiedCount; ?></b> Verified
                    </span>
                    <span class="dept-stat">
                        <i class='bx bx-pulse'></i>
                        <b><?php echo $activeCount; ?></b> Active
                    </span>
                </div>
            </div>

            <?php if (!empty($facultyStaff)): ?>
                <?php /* At-a-glance metrics. The decorative top bar that used to sit
                         on these cards is gone - the icon tile and the figure carry
                         the colour now, and a fifth repeated stripe across a row of
                         five cards read as decoration rather than information. */ ?>
                <div class="summary-cards">
                    <div class="summary-card">
                        <span class="summary-card-figure">
                            <span class="summary-card-icon tone-green"><i class='bx bx-group'></i></span>
                            <span class="summary-card-value"><?php echo $totalFaculty; ?></span>
                        </span>
                        <span class="summary-card-label">Total Faculty</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card-figure">
                            <span class="summary-card-icon tone-green"><i class='bx bx-check-circle'></i></span>
                            <span class="summary-card-value tone-green"><?php echo $highCompletion; ?></span>
                        </span>
                        <span class="summary-card-label">High Performance</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card-figure">
                            <span class="summary-card-icon tone-gold"><i class='bx bx-time-five'></i></span>
                            <span class="summary-card-value tone-gold"><?php echo $mediumCompletion; ?></span>
                        </span>
                        <span class="summary-card-label">Medium Performance</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card-figure">
                            <span class="summary-card-icon tone-green"><i class='bx bx-pulse'></i></span>
                            <span class="summary-card-value"><?php echo $activeCount; ?></span>
                        </span>
                        <span class="summary-card-label">Recently Active</span>
                    </div>
                    <div class="summary-card">
                        <span class="summary-card-figure">
                            <span class="summary-card-icon tone-gold"><i class='bx bx-badge-check'></i></span>
                            <span class="summary-card-value tone-gold"><?php echo $verifiedCount; ?></span>
                        </span>
                        <span class="summary-card-label">Email Verified</span>
                    </div>
                </div>

                <div class="bulk-actions" id="bulkActions" role="region" aria-label="Bulk actions">
                    <label class="bulk-select-all">
                        <input type="checkbox" id="selectAll">
                        <span class="bulk-count" id="selectedCount">0</span>
                        <span>selected</span>
                    </label>
                    <div class="bulk-actions-buttons">
                        <button class="btn btn-primary btn-sm" onclick="openBulkMessageModal()">
                            <i class='bx bx-message'></i> Send Message
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="exportSelected()">
                            <i class='bx bx-download'></i> Export
                        </button>
                    </div>
                </div>

                <div class="filters-section">
                    <div class="filters-row">
                        <?php /* The icon is a real <i> (the old ::before used a raw
                                 Boxicons codepoint and rendered as a blank box) and the
                                 clear button only appears once there is a query. */ ?>
                        <div class="search-box">
                            <i class='bx bx-search'></i>
                            <input type="text" id="facultySearch" class="search-input" placeholder="Search by name, position or employee ID" aria-label="Search faculty" autocomplete="off">
                            <button type="button" class="search-clear" id="searchClear" onclick="clearSearch()" aria-label="Clear search" hidden>
                                <i class='bx bx-x-circle'></i>
                            </button>
                        </div>
                        
                        <select id="completionFilter" class="filter-select" aria-label="Filter by performance level">
                            <option value="">All Performance Levels</option>
                            <option value="high">High (80%+)</option>
                            <option value="medium">Medium (50-79%)</option>
                            <option value="low">Low (<50%)</option>
                        </select>
                        
                        <select id="activityFilter" class="filter-select" aria-label="Filter by activity status">
                            <option value="">All Activity Status</option>
                            <option value="recent">Recently Active</option>
                            <option value="inactive">Not Recently Active</option>
                        </select>

                        <select id="verificationFilter" class="filter-select" aria-label="Filter by verification status">
                            <option value="">All Verification Status</option>
                            <option value="verified">Email Verified</option>
                            <option value="unverified">Not Verified</option>
                        </select>
                        
                        <div class="view-toggle" role="group" aria-label="Change card layout">
                            <button class="view-btn active" data-view="detailed" type="button" aria-label="Detailed view" aria-pressed="true">
                                <i class='bx bx-list-ul'></i>
                            </button>
                            <button class="view-btn" data-view="compact" type="button" aria-label="Compact view" aria-pressed="false">
                                <i class='bx bx-grid-alt'></i>
                            </button>
                        </div>
                    </div>
                    <div class="filters-foot">
                        <span class="filters-result" id="resultCount"></span>
                        <button type="button" class="filters-reset" id="resetFilters" onclick="resetFilters()" hidden>
                            <i class='bx bx-reset'></i> Reset filters
                        </button>
                    </div>
                </div>
                

                <div class="faculty-list" id="facultyList">
                    <?php foreach ($facultyStaff as $faculty): ?>
                        <?php 
                        // Reuse the stats computed once above instead of
                        // re-running five queries per row.
                        $stats = $facultyStats[$faculty['id']];
                        $completionRate = $stats['completion_rate'];
                        $completionClass = $completionRate >= 80 ? 'high' : ($completionRate >= 50 ? 'medium' : 'low');
                        $isRecentlyActive = $faculty['recently_active'];
                        $isOnline = $faculty['is_online'];
                        $isEmailVerified = $faculty['email_verified'];
                        
                        $profileImageUrl = getProfileImageUrl($faculty['profile_image']);
                        ?>
                        
                         <div class="faculty-card <?php echo $completionClass; ?>-completion" 
                            data-name="<?php echo htmlspecialchars(strtolower($faculty['full_name'])); ?>"
                            data-position="<?php echo htmlspecialchars(strtolower($faculty['position'] ?? '')); ?>"
                            data-employee-id="<?php echo htmlspecialchars(strtolower($faculty['employee_id'] ?? '')); ?>"
                            data-completion="<?php echo $completionClass; ?>"
                            data-activity="<?php echo $isRecentlyActive ? 'recent' : 'inactive'; ?>"
                            data-verification="<?php echo $isEmailVerified ? 'verified' : 'unverified'; ?>"
                            data-faculty-id="<?php echo $faculty['id']; ?>">
                            
                            
                            <input type="checkbox" class="faculty-checkbox" value="<?php echo $faculty['id']; ?>" aria-label="Select <?php echo htmlspecialchars($faculty['full_name']); ?>">
                            
                            <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" 
                                alt="<?php echo htmlspecialchars($faculty['full_name']); ?>" 
                                class="faculty-avatar <?php echo $isOnline ? 'online' : ($isRecentlyActive ? 'away' : 'offline'); ?>"
                                onerror="handleImageError(this)">

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
                                <?php /* The tracker's search box matches against
                                         CONCAT(name, ' ', surname) LIKE %term%, so the
                                         surname alone is the term that isolates one
                                         person. Passing "Surname, Name" matched
                                         nothing.

                                         These carry the shared .btn classes so their
                                         colour, height and radius come from the design
                                         system rather than a private palette here. */ ?>
                                <a href="document-tracker.php?search=<?= urlencode($faculty['surname']); ?>" class="btn btn-primary btn-sm btn-view">
                                    <i class='bx bx-show'></i> View Submissions
                                </a>
                                <button type="button" class="btn btn-secondary btn-sm btn-message" onclick="openMessageModal(<?php echo $faculty['id']; ?>, '<?php echo htmlspecialchars($faculty['full_name'], ENT_QUOTES); ?>')">
                                    <i class='bx bx-message'></i> Send Message
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm btn-stats" onclick="showDetailedStats(<?php echo $faculty['id']; ?>)">
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
    <div class="modal-backdrop" id="messageModal">
        <div class="modal-shell" role="dialog" aria-modal="true" aria-labelledby="messageModalTitle">
            <div class="modal-titlebar">
                <h3 id="messageModalTitle"><i class='bx bxs-message-rounded'></i> Send Message</h3>
                <button type="button" class="modal-close" onclick="closeMessageModal()" aria-label="Close message dialog">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <form id="messageForm">
                <input type="hidden" id="recipientId" name="recipient_id">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <div class="form-field">
                    <label for="recipientName">To</label>
                    <input type="text" id="recipientName" readonly>
                </div>

                <div class="form-field">
                    <label for="messageSubject">Subject <span class="form-hint">(optional)</span></label>
                    <input type="text" id="messageSubject" name="subject" placeholder="Enter subject">
                </div>

                <div class="form-field">
                    <label for="messageBody">Message</label>
                    <textarea id="messageBody" name="message" rows="6" placeholder="Enter your message..." required></textarea>
                </div>

                <div class="form-field">
                    <label for="messagePriority">Priority</label>
                    <select id="messagePriority" name="priority">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeMessageModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class='bx bxs-paper-plane'></i> Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk message dialog. Replaces two window.prompt() calls, which blocked the
     page, lost the subject if the second prompt was dismissed, and rendered with
     browser chrome instead of the system's own dialog styling. -->
    <div class="modal-backdrop" id="bulkMessageModal">
        <div class="modal-shell" role="dialog" aria-modal="true" aria-labelledby="bulkMessageModalTitle">
            <div class="modal-titlebar">
                <h3 id="bulkMessageModalTitle"><i class='bx bxs-message-rounded'></i> Message Selected Faculty</h3>
                <button type="button" class="modal-close" onclick="closeBulkMessageModal()" aria-label="Close bulk message dialog">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <form id="bulkMessageForm">
                <p class="bulk-recipient-count" id="bulkRecipientCount"></p>

                <div class="form-field">
                    <label for="bulkSubject">Subject <span class="form-hint">(optional)</span></label>
                    <input type="text" id="bulkSubject" name="subject" placeholder="Enter subject">
                </div>

                <div class="form-field">
                    <label for="bulkMessageBody">Message</label>
                    <textarea id="bulkMessageBody" name="message" rows="6" placeholder="Enter your message..." required></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeBulkMessageModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class='bx bxs-paper-plane'></i> Send to <span id="bulkSubmitCount">0</span></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Faculty stats modal -->
    <div class="modal-backdrop" id="statsModal">
        <div class="modal-shell" role="dialog" aria-modal="true" aria-labelledby="statsModalTitle">
            <div class="modal-titlebar">
                <h3 id="statsModalTitle"><i class='bx bxs-chart'></i> Submission Statistics</h3>
                <button type="button" class="modal-close" onclick="closeStatsModal()" aria-label="Close statistics dialog">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="stats-body" id="statsBody">
                <div class="stats-loading">
                    <span class="spinner"></span> Loading statistics...
                </div>
            </div>
            <div class="modal-actions">
                <a href="#" class="btn btn-primary" id="statsOpenTracker"><i class='bx bx-show'></i> Open in Tracker</a>
                <button type="button" class="btn btn-secondary" onclick="closeStatsModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/view-staff.js?v=<?= time() ?>"></script>
</body>
</html>
