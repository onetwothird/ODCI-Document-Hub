<?php include 'assets/script/activity_logs.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/activity_logs.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
    <link rel="stylesheet" href="assets/css/management-pages.css?v=1.0">
</head>

<body class="superadmin-management-page activity-logs-page">
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>
     <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>

        <div class="main-container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <div class="icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h1>System Activity Logs</h1>
                </div>
                <div class="header-actions">
                    <button class="btn-modern btn-success-modern" onclick="exportLogs()">
                        <i class="fas fa-download"></i>
                        Export CSV
                    </button>
                    <button class="btn-modern btn-danger-modern" onclick="showClearLogsModal()">
                        <i class="fas fa-trash-alt"></i>
                        Clear Old Logs
                    </button>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="todayCount">-</h3>
                            <p>Today's Activities</p>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card success">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="weekCount">-</h3>
                            <p>This Week</p>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3 id="monthCount">-</h3>
                            <p>This Month</p>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card info">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Analytics</h3>
                            <p>View Detailed Charts</p>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                    <button class="btn-modern btn-primary-modern w-100 mt-3" onclick="showAnalytics()" style="margin-top: 1rem !important; width: 100% !important;">
                        <i class="fas fa-chart-line"></i>
                        View Analytics
                    </button>
                </div>
            </div>

            <!-- Filters Card -->
            <div class="filters-card">
                <div class="filters-header">
                    <h5>
                        <i class="fas fa-filter"></i>
                        Filters
                    </h5>
                </div>

                <div class="filter-row">
                    <div class="form-group">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control-modern" id="dateFrom">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control-modern" id="dateTo">
                    </div>
                    <div class="form-group">
                        <label class="form-label">User</label>
                        <select class="form-control-modern" id="userFilter">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Resource Type</label>
                        <select class="form-control-modern" id="resourceTypeFilter">
                            <option value="">All Types</option>
                            <?php foreach ($resourceTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['resource_type']); ?>">
                                    <?php echo ucfirst($type['resource_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-row">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control-modern" id="searchFilter" placeholder="Search activities, users, or descriptions...">
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                    <div class="quick-filters">
                        <button class="btn-outline" onclick="setQuickFilter('today')">Today</button>
                        <button class="btn-outline" onclick="setQuickFilter('week')">This Week</button>
                        <button class="btn-outline" onclick="setQuickFilter('month')">This Month</button>
                        <button class="btn-outline" onclick="clearFilters()">Clear All</button>
                    </div>
                    <button class="btn-modern btn-primary-modern" onclick="loadLogs()">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                </div>
            </div>

            <!-- Activity Logs Card -->
            <div class="logs-card">
                <div class="logs-header">
                    <h5>
                        <i class="fas fa-list-alt"></i>
                        Recent Activity
                    </h5>
                    <select class="form-control-modern" id="recordsPerPage" style="width: auto;">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>

                <div class="logs-body">
                    <div class="loading-state">
                        <div class="loading-spinner"></div>
                        <p>Loading activity logs...</p>
                    </div>

                    <div id="logsContainer"></div>
                    
                    <div id="paginationContainer" class="pagination-modern" style="display: none;">
                        <div class="pagination-info">
                            <span id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                        </div>
                        <div class="pagination-nav" id="paginationNav">
                            <!-- Pagination buttons will be generated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clear Logs Modal -->
        <div class="modal fade" id="clearLogsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            Clear Old Activity Logs
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-3"></i>
                            <div>
                                <strong>Warning!</strong> This action will permanently delete activity logs and cannot be undone.
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Delete logs older than:</label>
                            <select class="form-control-modern" id="daysOld">
                                <option value="30">30 days</option>
                                <option value="60">60 days</option>
                                <option value="90">90 days</option>
                                <option value="180">6 months</option>
                                <option value="365">1 year</option>
                            </select>
                        </div>

                        <div style="margin-top: 1rem;">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                This will help maintain system performance by removing old activity records.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modern btn-outline" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="button" class="btn-modern btn-danger-modern" onclick="clearOldLogs()">
                            <i class="fas fa-trash"></i>
                            Delete Logs
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Modal -->
        <div class="modal fade" id="analyticsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-chart-bar text-primary me-2"></i>
                            Activity Analytics Dashboard
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-4">
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-clock me-2"></i>
                                            Activity by Hour (Last 24 Hours)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="hourlyChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-pie-chart me-2"></i>
                                            Resource Distribution (Last 7 Days)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="resourceChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-users me-2"></i>
                                            Top Active Users (Last 7 Days)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="topUsersContainer">
                                            <!-- Dynamic content will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-bolt me-2"></i>
                                            Most Common Actions (Last 7 Days)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="topActionsContainer">
                                            <!-- Dynamic content will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Session management script -->
    <?php echo getSessionManagementScript(); ?>
    
    <script>
        let currentPage = 1;
        let currentLimit = 25;
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadLogs();
            
            // Set up event listeners
            document.getElementById('recordsPerPage').addEventListener('change', function() {
                currentLimit = parseInt(this.value);
                currentPage = 1;
                loadLogs();
            });
            
            // Auto-refresh every 30 seconds
            setInterval(function() {
                loadStats();
                if (currentPage === 1) {
                    loadLogs();
                }
            }, 30000);

            // Setup filter event listeners
            setupFilterListeners();
        });

        function setupFilterListeners() {
            // Search on enter
            document.getElementById('searchFilter').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    currentPage = 1;
                    loadLogs();
                }
            });

            // Auto-search on filter changes with debounce
            let filterTimeout;
            ['dateFrom', 'dateTo', 'userFilter', 'resourceTypeFilter'].forEach(id => {
                document.getElementById(id).addEventListener('change', function() {
                    clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(() => {
                        currentPage = 1;
                        loadLogs();
                    }, 300);
                });
            });
        }
        
        function loadStats() {
            fetch('?ajax=get_stats')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('todayCount').textContent = data.today_count || 0;
                    document.getElementById('weekCount').textContent = data.week_count || 0;
                    document.getElementById('monthCount').textContent = (data.month_count || 0).toLocaleString();
                })
                .catch(error => {
                    console.error('Error loading stats:', error);
                });
        }
        
        function loadLogs(page = 1) {
            currentPage = page;
            
            const params = new URLSearchParams({
                ajax: 'get_logs',
                page: currentPage,
                limit: currentLimit,
                date_from: document.getElementById('dateFrom').value,
                date_to: document.getElementById('dateTo').value,
                user_id: document.getElementById('userFilter').value,
                resource_type: document.getElementById('resourceTypeFilter').value,
                search: document.getElementById('searchFilter').value
            });
            
            // Show loading
            document.querySelector('.loading-state').style.display = 'block';
            document.getElementById('logsContainer').innerHTML = '';
            document.getElementById('paginationContainer').style.display = 'none';
            
            fetch('?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    document.querySelector('.loading-state').style.display = 'none';
                    
                    if (data.success) {
                        displayLogs(data.data);
                        displayPagination(data.pagination);
                    } else {
                        document.getElementById('logsContainer').innerHTML = 
                            '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading logs: ' + data.error + '</p></div>';
                    }
                })
                .catch(error => {
                    document.querySelector('.loading-state').style.display = 'none';
                    document.getElementById('logsContainer').innerHTML = 
                        '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Error loading logs: ' + error.message + '</p></div>';
                });
        }
        
        function displayLogs(logs) {
            const container = document.getElementById('logsContainer');
            
            if (logs.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-info-circle"></i><p>No activity logs found for the selected criteria.</p></div>';
                return;
            }
            
            let html = '';
            logs.forEach(log => {
                const userInitials = getUserInitials(log.user_full_name);
                const avatarColor = getAvatarColor(log.user_id);
                
                html += `
                    <div class="log-item">
                        <div class="log-avatar" style="background: ${avatarColor}">
                            ${userInitials}
                        </div>
                        <div class="log-icon">
                            <i class="${log.resource_icon}"></i>
                        </div>
                        <div class="log-content">
                            <div class="log-header">
                                <span class="log-user">${log.user_full_name}</span>
                                <span class="log-badge ${log.action_badge}">${log.action.toUpperCase()}</span>
                                <span class="log-time">${log.time_ago}</span>
                            </div>
                            <div class="log-description">
                                ${log.description || 'No description available'}
                            </div>
                            <div class="log-meta">
                                <span><strong>Resource:</strong> ${log.resource_type || 'N/A'}</span>
                                ${log.resource_id ? `<span><strong>ID:</strong> ${log.resource_id}</span>` : ''}
                                ${log.department_name ? `<span><strong>Department:</strong> ${log.department_name}</span>` : ''}
                                ${log.ip_address ? `<span><strong>IP:</strong> ${log.ip_address}</span>` : ''}
                                ${log.metadata_parsed ? `
                                    <span class="metadata-toggle" onclick="toggleMetadata(${log.id})">
                                        <i class="fas fa-eye"></i> View Details
                                    </span>
                                ` : ''}
                            </div>
                            ${log.metadata_parsed ? `
                                <div class="metadata-content" id="metadata-${log.id}">
                                    <pre>${JSON.stringify(log.metadata_parsed, null, 2)}</pre>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function displayPagination(pagination) {
            const container = document.getElementById('paginationContainer');
            const infoElement = document.getElementById('paginationInfo');
            const navElement = document.getElementById('paginationNav');
            
            if (pagination.total_pages <= 1) {
                container.style.display = 'none';
                return;
            }
            
            // Update pagination info
            const start = ((pagination.current_page - 1) * pagination.limit) + 1;
            const end = Math.min(pagination.current_page * pagination.limit, pagination.total_records);
            infoElement.textContent = `Showing ${start} to ${end} of ${pagination.total_records.toLocaleString()} entries`;
            
            // Build pagination navigation
            let navHtml = '';
            
            // Previous button
            navHtml += `
                <button class="page-btn ${pagination.current_page === 1 ? 'disabled' : ''}" 
                        onclick="loadLogs(${pagination.current_page - 1})" 
                        ${pagination.current_page === 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
            
            // Page numbers
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            if (startPage > 1) {
                navHtml += `<button class="page-btn" onclick="loadLogs(1)">1</button>`;
                if (startPage > 2) {
                    navHtml += `<span class="px-2">...</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                navHtml += `
                    <button class="page-btn ${i === pagination.current_page ? 'active' : ''}" 
                            onclick="loadLogs(${i})">${i}</button>
                `;
            }
            
            if (endPage < pagination.total_pages) {
                if (endPage < pagination.total_pages - 1) {
                    navHtml += `<span class="px-2">...</span>`;
                }
                navHtml += `<button class="page-btn" onclick="loadLogs(${pagination.total_pages})">${pagination.total_pages}</button>`;
            }
            
            // Next button
            navHtml += `
                <button class="page-btn ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}" 
                        onclick="loadLogs(${pagination.current_page + 1})" 
                        ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
            
            navElement.innerHTML = navHtml;
            container.style.display = 'flex';
        }
        
        function getUserInitials(name) {
            if (!name || name === 'Unknown User') return 'U';
            return name.split(' ')
                      .map(word => word.charAt(0))
                      .join('')
                      .substring(0, 2)
                      .toUpperCase();
        }
        
        function getAvatarColor(userId) {
            const colors = [
                'linear-gradient(135deg, #6366f1, #4f46e5)',
                'linear-gradient(135deg, #22c55e, #16a34a)',
                'linear-gradient(135deg, #f59e0b, #d97706)',
                'linear-gradient(135deg, #ef4444, #dc2626)',
                'linear-gradient(135deg, #8b5cf6, #7c3aed)',
                'linear-gradient(135deg, #06b6d4, #0891b2)',
                'linear-gradient(135deg, #f97316, #ea580c)',
                'linear-gradient(135deg, #84cc16, #65a30d)',
                'linear-gradient(135deg, #ec4899, #db2777)',
                'linear-gradient(135deg, #64748b, #475569)'
            ];
            return colors[userId % colors.length];
        }
        
        function toggleMetadata(logId) {
            const element = document.getElementById(`metadata-${logId}`);
            const toggle = element.previousElementSibling.querySelector('.metadata-toggle');
            
            if (element.style.display === 'none' || !element.style.display) {
                element.style.display = 'block';
                toggle.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Details';
            } else {
                element.style.display = 'none';
                toggle.innerHTML = '<i class="fas fa-eye"></i> View Details';
            }
        }
        
        function clearFilters() {
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('userFilter').value = '';
            document.getElementById('resourceTypeFilter').value = '';
            document.getElementById('searchFilter').value = '';
            
            // Remove active state from quick filter buttons
            document.querySelectorAll('.btn-outline').forEach(btn => {
                btn.classList.remove('active');
            });
            
            currentPage = 1;
            loadLogs();
        }
        
        function setQuickFilter(period) {
            const today = new Date();
            const dateFrom = new Date();
            
            // Remove active state from all buttons
            document.querySelectorAll('.btn-outline').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active state to clicked button
            event.target.classList.add('active');
            
            switch(period) {
                case 'today':
                    dateFrom.setDate(today.getDate());
                    break;
                case 'week':
                    dateFrom.setDate(today.getDate() - 7);
                    break;
                case 'month':
                    dateFrom.setMonth(today.getMonth() - 1);
                    break;
            }
            
            document.getElementById('dateFrom').value = dateFrom.toISOString().split('T')[0];
            document.getElementById('dateTo').value = today.toISOString().split('T')[0];
            currentPage = 1;
            loadLogs();
        }
        
        function exportLogs() {
            const params = new URLSearchParams({
                ajax: 'export_logs',
                date_from: document.getElementById('dateFrom').value,
                date_to: document.getElementById('dateTo').value,
                user_id: document.getElementById('userFilter').value,
                resource_type: document.getElementById('resourceTypeFilter').value,
                search: document.getElementById('searchFilter').value
            });
            
            window.open('?' + params.toString(), '_blank');
        }
        
        function showClearLogsModal() {
            const modal = new bootstrap.Modal(document.getElementById('clearLogsModal'));
            modal.show();
        }
        
        function clearOldLogs() {
            const days = document.getElementById('daysOld').value;
            
            if (!confirm(`Are you sure you want to delete all activity logs older than ${days} days? This action cannot be undone.`)) {
                return;
            }
            
            fetch(`?ajax=clear_old_logs&days=${days}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Old logs cleared successfully: ' + data.message);
                        loadStats();
                        loadLogs();
                        bootstrap.Modal.getInstance(document.getElementById('clearLogsModal')).hide();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error clearing logs: ' + error.message);
                });
        }
        
        function showAnalytics() {
            const modal = new bootstrap.Modal(document.getElementById('analyticsModal'));
            modal.show();
            
            // Load analytics data
            fetch('?ajax=get_stats')
                .then(response => response.json())
                .then(data => {
                    createHourlyChart(data.hourly_activity);
                    createResourceChart(data.resource_breakdown);
                    displayTopUsers(data.top_users);
                    displayTopActions(data.top_actions);
                })
                .catch(error => {
                    console.error('Error loading analytics:', error);
                });
        }
        
        function createHourlyChart(hourlyData) {
            const ctx = document.getElementById('hourlyChart').getContext('2d');
            
            // Prepare data for 24 hours
            const hours = Array.from({length: 24}, (_, i) => i);
            const data = hours.map(hour => {
                const found = hourlyData.find(item => item.hour == hour);
                return found ? found.count : 0;
            });
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: hours.map(h => h + ':00'),
                    datasets: [{
                        label: 'Activity Count',
                        data: data,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Activity by Hour (Last 24 Hours)'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        function createResourceChart(resourceData) {
            const ctx = document.getElementById('resourceChart').getContext('2d');
            
            const labels = resourceData.map(item => item.resource_type.charAt(0).toUpperCase() + item.resource_type.slice(1));
            const data = resourceData.map(item => item.count);
            const colors = [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
            ];
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.slice(0, labels.length),
                        hoverBackgroundColor: colors.slice(0, labels.length)
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Activity by Resource Type (Last 7 Days)'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        function displayTopUsers(topUsers) {
            const container = document.getElementById('topUsersContainer');
            let html = '<div class="list-group">';
            
            topUsers.forEach((user, index) => {
                const fullName = (user.name + ' ' + (user.surname || '')).trim();
                const displayName = fullName || user.username;
                
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>#${index + 1}</strong>
                            ${displayName}
                            <small class="text-muted d-block">@${user.username}</small>
                        </div>
                        <span class="badge bg-primary rounded-pill">${user.activity_count}</span>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function displayTopActions(topActions) {
            const container = document.getElementById('topActionsContainer');
            let html = '<div class="list-group">';
            
            topActions.forEach((action, index) => {
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>#${index + 1}</strong>
                            <span class="badge ${getActionBadgeClass(action.action)} ms-2">${action.action}</span>
                        </div>
                        <span class="badge bg-secondary rounded-pill">${action.count}</span>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function getActionBadgeClass(action) {
            const loginActions = ['login', 'logout', 'auto_login'];
            const createActions = ['create', 'upload', 'add'];
            const updateActions = ['update', 'edit', 'modify'];
            const deleteActions = ['delete', 'remove'];
            const securityActions = ['failed_login', 'account_locked', 'unauthorized_access'];
            
            if (loginActions.includes(action)) return 'badge-info';
            if (createActions.includes(action)) return 'badge-success';
            if (updateActions.includes(action)) return 'badge-warning';
            if (deleteActions.includes(action)) return 'badge-danger';
            if (securityActions.includes(action)) return 'badge-danger';
            
            return 'badge-secondary';
        }
        
        // Search on Enter key
        document.getElementById('searchFilter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                currentPage = 1;
                loadLogs();
            }
        });
        
        // Auto-search on filter changes (with debounce)
        let filterTimeout;
        ['dateFrom', 'dateTo', 'userFilter', 'resourceTypeFilter'].forEach(id => {
            document.getElementById(id).addEventListener('change', function() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadLogs();
                }, 300);
            });
        });
    </script>
</body>
</html>
