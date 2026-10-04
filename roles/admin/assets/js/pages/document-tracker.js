// Document Tracker JavaScript - Matching Dashboard Functionality

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize progress circles
    initProgressCircles();
    
    // Initialize table interactions
    initTableInteractions();
    
    // Auto-hide scroll hint after interaction
    initScrollHint();
});

/* ============================================================
   Tooltips
   ============================================================ */
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[title]');
    
    tooltipElements.forEach(el => {
        el.addEventListener('mouseenter', showTooltip);
        el.addEventListener('mouseleave', hideTooltip);
    });
}

let tooltip = null;

function showTooltip(e) {
    const text = this.getAttribute('title');
    if (!text) return;
    
    this.removeAttribute('title');
    this.dataset.tooltip = text;
    
    tooltip = document.createElement('div');
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: fixed;
        background: #1e293b;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        z-index: 9999;
        pointer-events: none;
        white-space: nowrap;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    document.body.appendChild(tooltip);
    positionTooltip(e);
}

function hideTooltip() {
    if (this.dataset.tooltip) {
        this.setAttribute('title', this.dataset.tooltip);
        delete this.dataset.tooltip;
    }
    
    if (tooltip) {
        tooltip.remove();
        tooltip = null;
    }
}

function positionTooltip(e) {
    if (!tooltip) return;
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
}

/* ============================================================
   Progress Circles
   ============================================================ */
function initProgressCircles() {
    const circles = document.querySelectorAll('.progress-circle[data-progress]');
    
    circles.forEach(circle => {
        const progress = parseInt(circle.dataset.progress, 10);
        const textEl = circle.querySelector('text');
        
        if (textEl) {
            // Animate the number
            animateValue(textEl, 0, progress, 1000);
        }
    });
}

function animateValue(element, start, end, duration) {
    const startTime = performance.now();
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.round(start + (end - start) * eased);
        
        element.textContent = current + '%';
        
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    
    requestAnimationFrame(update);
}

/* ============================================================
   Table Interactions
   ============================================================ */
function initTableInteractions() {
    const table = document.getElementById('documentTable');
    if (!table) {
        console.warn('documentTable not found');
        return;
    }
    
    // Event delegation for status cells (click to view details)
    const tbody = table.querySelector('tbody');
    if (tbody) {
        tbody.addEventListener('click', function(e) {
            const cell = e.target.closest('td.status-cell');
            if (cell) {
                const facultyId = cell.dataset.facultyId;
                const docType = cell.dataset.docType;
                const semester = cell.dataset.semester;
                const year = cell.dataset.year;
                const hasFiles = cell.dataset.hasFiles === '1';
                
                console.log('Status cell clicked:', { facultyId, docType, semester, year, hasFiles });
                
                if (facultyId && docType && semester && year) {
                    if (hasFiles) {
                        viewDetails(facultyId, docType, semester, year);
                    } else {
                        showNotSubmittedDetails(facultyId, docType, semester, year);
                    }
                }
            }
        });
        console.log('Table click handler attached');
    } else {
        console.warn('Table tbody not found');
    }
    
    // Highlight row on hover (CSS handles this, but we can add keyboard support)
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
        
        // Make rows focusable for keyboard navigation
        row.setAttribute('tabindex', '0');
    });
    
    // Sticky column shadow on scroll
    const wrapper = document.querySelector('.table-scroll-wrapper');
    if (wrapper) {
        wrapper.addEventListener('scroll', function() {
            const scrollLeft = this.scrollLeft;
            const facultyCells = this.querySelectorAll('.faculty-cell');
            
            facultyCells.forEach(cell => {
                if (scrollLeft > 0) {
                    cell.style.boxShadow = '4px 0 12px -4px rgba(0,0,0,0.15)';
                } else {
                    cell.style.boxShadow = 'none';
                }
            });
        });
    }
}

/* ============================================================
   Scroll Hint
   ============================================================ */
function initScrollHint() {
    const hint = document.querySelector('.table-scroll-hint');
    const wrapper = document.querySelector('.table-scroll-wrapper');
    
    if (!hint || !wrapper) return;
    
    // Hide hint after user scrolls
    wrapper.addEventListener('scroll', function() {
        if (this.scrollLeft > 50) {
            hint.style.opacity = '0';
            hint.style.pointerEvents = 'none';
            setTimeout(() => {
                hint.style.display = 'none';
            }, 300);
        }
    }, { once: true });
}

/* ============================================================
   Modal Functions (using global facultyData from inline script)
   ============================================================ */
function showFacultyDetails(facultyId) {
    const modal = document.getElementById('facultyDetailsModal');
    const content = document.getElementById('facultyDetailsContent');
    
    if (!modal || !content) return;
    
    // Find faculty data from global variable
    const faculty = window.facultyData ? window.facultyData.find(f => f.id == facultyId) : null;
    
    if (!faculty) {
        content.innerHTML = `
            <div class="error-alert">
                <i class='bx bx-error-circle'></i>
                Faculty data not found.
            </div>
        `;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        return;
    }
    
    // Render faculty details directly from global data
    renderFacultyDetails(faculty, content);
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeFacultyDetailsModal() {
    const modal = document.getElementById('facultyDetailsModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

function viewDetails(facultyId, documentType, semester, academicYear) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('detailsContent');
    const title = document.getElementById('detailsModalTitle');

    if (!modal || !content || !title) return;

    const faculty = (window.facultyData || []).find(f => String(f.id) === String(facultyId));
    const facultyName = faculty
        ? `${faculty.surname}, ${faculty.name}${faculty.mi ? ' ' + faculty.mi + '.' : ''}`
        : 'Faculty member';
    const periodLabel = `${semester} AY ${academicYear}-${parseInt(academicYear, 10) + 1}`;

    title.textContent = documentType;

    content.innerHTML = `
        <div class="file-modal-header">
            <div class="file-modal-header-main">
                <span class="file-modal-faculty">${escapeHtml(facultyName)}</span>
                <span class="file-modal-doc-type">${escapeHtml(documentType)}</span>
            </div>
            <span class="file-modal-period"><i class='bx bx-time'></i> ${escapeHtml(periodLabel)}</span>
        </div>
        <div class="file-modal-loading">
            <div class="loading-spinner"></div>
            <p>Loading submitted files...</p>
        </div>
    `;

    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    const params = new URLSearchParams({
        action: 'get_file_details',
        faculty_id: facultyId,
        document_type: documentType,
        semester: semester,
        academic_year: academicYear
    });

    fetch(`script/tracker.php?${params.toString()}`)
        .then(response => response.text().then(body => {
            let parsed = null;
            try { parsed = JSON.parse(body); } catch { parsed = null; }
            if (!response.ok || !parsed) {
                throw new Error((parsed && parsed.message) || `Request failed (HTTP ${response.status}).`);
            }
            return parsed;
        }))
        .then(data => {
            if (data.success) {
                renderFileDetails(data.files, content, { facultyName, documentType, periodLabel });
            } else {
                renderFileModalError(content, data.message || 'The files could not be loaded.');
            }
        })
        .catch(error => {
            renderFileModalError(content, error.message || 'The files could not be loaded.');
            console.error('File details error:', error);
        });
}

function renderFileModalError(container, message) {
    container.innerHTML = `
        ${container.querySelector('.file-modal-header') ? container.querySelector('.file-modal-header').outerHTML : ''}
        <div class="error-alert">
            <i class='bx bx-error-circle'></i>
            ${escapeHtml(message)}
        </div>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="closeDetailsModal()">
                <i class='bx bx-x'></i> Close
            </button>
        </div>
    `;
}

function showNotSubmittedDetails(facultyId, documentType, semester, academicYear) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('detailsContent');
    const title = document.getElementById('detailsModalTitle');
    
    if (!modal || !content || !title) return;
    
    const faculty = (window.facultyData || []).find(f => String(f.id) === String(facultyId));
    const facultyName = faculty
        ? `${faculty.surname}, ${faculty.name}${faculty.mi ? ' ' + faculty.mi + '.' : ''}`
        : 'Faculty member';
    const periodLabel = `${semester} AY ${academicYear}-${parseInt(academicYear, 10) + 1}`;

    title.textContent = documentType;

    content.innerHTML = `
        <div class="file-modal-header">
            <div class="file-modal-header-main">
                <span class="file-modal-faculty">${escapeHtml(facultyName)}</span>
                <span class="file-modal-doc-type">${escapeHtml(documentType)}</span>
            </div>
            <span class="file-modal-period"><i class='bx bx-time'></i> ${escapeHtml(periodLabel)}</span>
        </div>
        <div class="empty-state">
            <i class='bx bx-file-blank'></i>
            <h3>Not Submitted</h3>
            <p>No files were uploaded for <strong>${escapeHtml(documentType)}</strong> in ${escapeHtml(periodLabel)}.</p>
        </div>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="closeDetailsModal()">
                <i class='bx bx-x'></i> Close
            </button>
        </div>
    `;
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDetailsModal() {
    const modal = document.getElementById('detailsModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

function renderFacultyDetails(faculty, container) {
    const avatarLetter = faculty.name ? faculty.name.charAt(0).toUpperCase() : '?';
    const avatarUrl = faculty.profile_image_url || null;
    const fullName = `${faculty.surname}, ${faculty.name}${faculty.mi ? ' ' + faculty.mi + '.' : ''}`;
    
    container.innerHTML = `
        <div class="faculty-detail-card">
            <div class="faculty-detail-header">
                <div class="faculty-avatar" style="${avatarUrl ? `background-image: url('${avatarUrl}'); background-size: cover; background-position: center;` : `background: linear-gradient(135deg, var(--cvsu-green-700), var(--cvsu-green-600));`}">
                    ${!avatarUrl ? avatarLetter : ''}
                </div>
                <div class="faculty-detail-info">
                    <h3 class="faculty-detail-name">${fullName}</h3>
                    <p class="faculty-detail-position">${faculty.position || 'Faculty Member'}</p>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <i class='bx bx-id-card'></i>
                    <span><strong>Employee ID:</strong> ${faculty.employee_id || 'N/A'}</span>
                </div>
                <div class="info-item">
                    <i class='bx bx-envelope'></i>
                    <span><strong>Email:</strong> ${faculty.email}</span>
                </div>
                <div class="info-item">
                    <i class='bx bx-building'></i>
                    <span><strong>Department:</strong> ${faculty.department_name || 'N/A'}</span>
                </div>
                <div class="info-item">
                    <i class='bx bx-calendar'></i>
                    <span><strong>Status:</strong> ${faculty.is_approved ? 'Active' : 'Pending'}</span>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button class="btn btn-secondary" onclick="closeFacultyDetailsModal()">
                <i class='bx bx-x'></i> Close
            </button>
        </div>
    `;
}

function renderFileDetails(files, container, context) {
    const meta = context || {};
    const safeFiles = Array.isArray(files) ? files : [];

    const header = `
        <div class="file-modal-header">
            <div class="file-modal-header-main">
                <span class="file-modal-faculty">${escapeHtml(meta.facultyName || '')}</span>
                <span class="file-modal-doc-type">${escapeHtml(meta.documentType || '')}</span>
            </div>
            <span class="file-modal-period"><i class='bx bx-time'></i> ${escapeHtml(meta.periodLabel || '')}</span>
        </div>
    `;

    if (safeFiles.length === 0) {
        container.innerHTML = header + `
            <div class="empty-state">
                <i class='bx bx-file-blank'></i>
                <h3>No Files Found</h3>
                <p>The submission is recorded, but the files are no longer available.</p>
            </div>
            <div class="action-buttons">
                <button class="btn btn-secondary" onclick="closeDetailsModal()">
                    <i class='bx bx-x'></i> Close
                </button>
            </div>
        `;
        return;
    }

    const totalSize = safeFiles.reduce((sum, file) => sum + (Number(file.file_size) || 0), 0);
    const totalDownloads = safeFiles.reduce((sum, file) => sum + (Number(file.download_count) || 0), 0);
    const latest = safeFiles.reduce((acc, file) => {
        const stamp = new Date(file.uploaded_at).getTime() || 0;
        return stamp > acc ? stamp : acc;
    }, 0);

    let html = header + `
        <div class="file-modal-summary">
            <div class="file-modal-stat">
                <i class='bx bx-file'></i>
                <span><strong>${safeFiles.length}</strong> file${safeFiles.length !== 1 ? 's' : ''}</span>
            </div>
            <div class="file-modal-stat">
                <i class='bx bx-hdd'></i>
                <span><strong>${formatFileSize(totalSize)}</strong> total</span>
            </div>
            <div class="file-modal-stat">
                <i class='bx bx-download'></i>
                <span><strong>${totalDownloads}</strong> download${totalDownloads !== 1 ? 's' : ''}</span>
            </div>
            <div class="file-modal-stat">
                <i class='bx bx-calendar'></i>
                <span>${latest ? new Date(latest).toLocaleDateString() : 'Unknown'}</span>
            </div>
        </div>
        <div class="file-list">
    `;

    safeFiles.forEach(file => {
        const fileName = file.original_name || file.file_name || 'Unnamed file';
        const extension = (file.file_extension || fileName.split('.').pop() || '').toLowerCase();
        const uploadedAt = file.uploaded_at ? new Date(file.uploaded_at) : null;
        const description = file.description ? escapeHtml(file.description) : '';

        html += `
            <div class="file-item">
                <div class="file-icon file-icon-${escapeHtml(extension || 'default')}">
                    <i class='bx ${getFileIcon(extension)}'></i>
                </div>
                <div class="file-info">
                    <h4 title="${escapeHtml(fileName)}">${escapeHtml(fileName)}</h4>
                    <div class="file-meta">
                        <span>${formatFileSize(file.file_size)}</span>
                        <span>${escapeHtml((extension || 'file').toUpperCase())}</span>
                        <span>${uploadedAt && !isNaN(uploadedAt.getTime()) ? uploadedAt.toLocaleString() : 'Unknown date'}</span>
                        ${Number(file.download_count) > 0 ? `<span><i class='bx bx-download'></i> ${Number(file.download_count)}</span>` : ''}
                    </div>
                    ${description ? `<p class="file-description">${description}</p>` : ''}
                </div>
                <div class="file-actions">
                    <a href="api/download_file.php?id=${encodeURIComponent(file.id)}" class="btn btn-secondary btn-sm" title="Download">
                        <i class='bx bx-download'></i>
                    </a>
                    <a href="api/download_file.php?id=${encodeURIComponent(file.id)}&disposition=inline" target="_blank" rel="noopener" class="btn btn-primary btn-sm" title="View">
                        <i class='bx bx-show'></i>
                    </a>
                </div>
            </div>
        `;
    });

    html += `
        </div>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="closeDetailsModal()">
                <i class='bx bx-x'></i> Close
            </button>
        </div>
    `;

    container.innerHTML = html;
}

function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    if (typeof unsafe !== 'string') return String(unsafe);

    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function getFileIcon(ext) {
    const icons = {
        'pdf': 'bxs-file-pdf',
        'doc': 'bxs-file-doc',
        'docx': 'bxs-file-doc',
        'xls': 'bxs-file-excel',
        'xlsx': 'bxs-file-excel',
        'ppt': 'bxs-file-ppt',
        'pptx': 'bxs-file-ppt',
        'jpg': 'bxs-file-image',
        'jpeg': 'bxs-file-image',
        'png': 'bxs-file-image',
        'txt': 'bxs-file-txt',
        'zip': 'bxs-file-archive',
        'rar': 'bxs-file-archive'
    };
    return icons[ext] || 'bxs-file';
}

function formatFileSize(bytes) {
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    } else {
        return bytes + ' bytes';
    }
}

/* ============================================================
   Export Functionality
   ============================================================ */
function exportTable(format = 'csv') {
    const table = document.getElementById('documentTable');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        let rowData = [];
        
        cols.forEach((col, index) => {
            // Skip progress column in export (optional)
            if (col.classList.contains('progress-cell') || col.classList.contains('progress-header')) {
                return;
            }
            
            let text = col.innerText || col.textContent || '';
            text = text.replace(/"/g, '""');
            rowData.push('"' + text + '"');
        });
        
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (format === 'csv') {
        link.href = URL.createObjectURL(blob);
        link.download = `document_tracker_${new Date().toISOString().split('T')[0]}.csv`;
    } else {
        // For Excel, we'll use CSV with .xls extension
        link.href = URL.createObjectURL(blob);
        link.download = `document_tracker_${new Date().toISOString().split('T')[0]}.xls`;
    }
    
    link.click();
    URL.revokeObjectURL(link.href);
}

/* ============================================================
   Keyboard Navigation
   ============================================================ */
document.addEventListener('keydown', function(e) {
    // Escape to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
});

/* ============================================================
   Touch Support for Table
   ============================================================ */
let touchStartX = 0;
const tableWrapper = document.querySelector('.table-scroll-wrapper');

if (tableWrapper) {
    tableWrapper.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    
    tableWrapper.addEventListener('touchend', function(e) {
        const touchEndX = e.changedTouches[0].clientX;
        const diff = touchStartX - touchEndX;
        
        // If significant horizontal swipe, hint that table is scrollable
        if (Math.abs(diff) > 50) {
            const hint = document.querySelector('.table-scroll-hint');
            if (hint && hint.style.display !== 'none') {
                hint.style.opacity = '1';
            }
        }
    }, { passive: true });
}

/* ============================================================
   Notification System (from script-docu.php)
   ============================================================ */
function showNotification(message, type = 'info') {
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    const colors = {
        success: { bg: 'rgba(212, 237, 218, 0.95)', color: '#155724', border: '#28a745' },
        error: { bg: 'rgba(248, 215, 218, 0.95)', color: '#721c24', border: '#dc3545' },
        info: { bg: 'rgba(209, 236, 241, 0.95)', color: '#0c5460', border: '#17a2b8' }
    };
    
    const style = colors[type] || colors.info;
    notification.style.cssText = `
        position: fixed;
        top: 30px;
        right: 30px;
        z-index: 1001;
        padding: 15px 20px;
        border-radius: 10px;
        font-weight: 600;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        backdrop-filter: blur(10px);
        background: ${style.bg};
        color: ${style.color};
        border-left: 5px solid ${style.border};
        transform: translateX(400px);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 400);
    }, 1800);
}

/* ============================================================
   Modal Click Outside to Close
   ============================================================ */
document.addEventListener('click', function(e) {
    // Close modal when clicking on backdrop
    if (e.target.classList.contains('modal')) {
        const modal = e.target;
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
});

/* ============================================================
   Initialize on DOMContentLoaded
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Document Tracker initialized');
    
    // Add smooth scrolling to horizontal table scroll
    const tableWrapper = document.querySelector('.table-scroll-wrapper');
    if (tableWrapper) {
        tableWrapper.style.scrollBehavior = 'smooth';
    }
    
    // Initialize progress circles animation
    const progressCircles = document.querySelectorAll('.progress-circle');
    progressCircles.forEach(circle => {
        const progress = parseInt(circle.dataset.progress);
        const progressCircle = circle.querySelector('circle:last-child');
        if (progressCircle) {
            const radius = 24;
            const circumference = 2 * Math.PI * radius;
            const offset = circumference * (1 - progress / 100);
            
            progressCircle.style.strokeDasharray = circumference;
            progressCircle.style.strokeDashoffset = circumference;
            
            setTimeout(() => {
                progressCircle.style.transition = 'stroke-dashoffset 1s ease-in-out';
                progressCircle.style.strokeDashoffset = offset;
            }, 500);
        }
    });
});