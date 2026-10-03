let categoryStates = new Map(); // Track state for each category independently
let loadedCategories = new Set(); // Track which categories have been loaded
let categoryFileCounts = new Map(); // Store file counts persistently

// Category functionality with improved state management
function toggleCategory(deptId, categoryKey) {
    const uniqueId = `${deptId}-${categoryKey}`;
    const content = document.getElementById(`category-content-${uniqueId}`);
    const icon = document.getElementById(`icon-${uniqueId}`);
    const departmentCard = document.querySelector(`.department-card[data-department="${deptId}"]`);
    const categoryCard = departmentCard?.querySelector(`.category-card[data-category="${categoryKey}"]`);
    
    if (!content || !icon) {
        console.error(`Category elements not found for ${uniqueId}`);
        return;
    }
    
    // Get current state
    const currentState = categoryStates.get(uniqueId) || {
        isExpanded: false,
        activeSemester: 'first',
        isLoading: false
    };
    currentState.isExpanded = content.classList.contains('show');
    
    if (currentState.isExpanded) {
        // Collapse category
        content.classList.remove('show');
        icon.classList.remove('rotated');
        if (categoryCard) categoryCard.classList.remove('active');
        
        categoryStates.set(uniqueId, {
            ...currentState,
            isExpanded: false
        });
        
        // KEEP THE COUNT WHEN COLLAPSING
        // Don't reset count here - preserve it
        
    } else {
        // Expand category
        content.classList.add('show');
        icon.classList.add('rotated');
        if (categoryCard) categoryCard.classList.add('active');
        
        categoryStates.set(uniqueId, {
            ...currentState,
            isExpanded: true
        });
        
        // Load files for this category if not already loaded
        if (!loadedCategories.has(uniqueId) && !currentState.isLoading) {
            loadCategoryFiles(deptId, categoryKey);
        }
    }
}

function showCategorySemester(deptId, categoryKey, semester, event) {
    // Prevent event bubbling
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const uniqueId = `${deptId}-${categoryKey}`;
    
    // Update tab active state - find tabs within the specific category
    const categoryContent = document.getElementById(`category-content-${uniqueId}`);
    if (!categoryContent) return;
    
    const tabs = categoryContent.querySelectorAll('.semester-tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
        // Check if this is the clicked tab
        if (tab.textContent.toLowerCase().includes(semester)) {
            tab.classList.add('active');
        }
    });
    
    // Show/hide semester content
    const firstSemester = document.getElementById(`files-${uniqueId}-first`);
    const secondSemester = document.getElementById(`files-${uniqueId}-second`);
    
    if (semester === 'first') {
        if (firstSemester) {
            firstSemester.style.display = 'grid';
        }
        if (secondSemester) {
            secondSemester.style.display = 'none';
        }
    } else {
        if (firstSemester) {
            firstSemester.style.display = 'none';
        }
        if (secondSemester) {
            secondSemester.style.display = 'grid';
        }
    }
    
    // Update state
    const currentState = categoryStates.get(uniqueId) || {};
    categoryStates.set(uniqueId, {
        ...currentState,
        activeSemester: semester
    });
}

function loadCategoryFiles(deptId, categoryKey) {
    // Security check: Only load files for user's department
    if (deptId != userDepartmentId) {
        console.error('Access denied: Cannot load files from different department');
        return;
    }
    
    const uniqueId = `${deptId}-${categoryKey}`;
    
    // Set loading state
    const currentState = categoryStates.get(uniqueId) || {};
    categoryStates.set(uniqueId, {
        ...currentState,
        isLoading: true
    });
    
    // Show loading states
    showLoadingState(uniqueId, 'first');
    showLoadingState(uniqueId, 'second');
    
    // Update category count to show loading (but preserve if we have a cached count)
    const cachedCount = categoryFileCounts.get(`${deptId}-${categoryKey}`);
    if (cachedCount === undefined) {
        updateCategoryCount(categoryKey, 'Loading...');
    }
    
    // AJAX call to load files from database for specific category
    fetch('handlers/category_files.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            department_id: deptId,
            category: categoryKey,
            user_department_id: userDepartmentId
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Mark as loaded
            loadedCategories.add(uniqueId);
            
            // Render files
            renderCategoryFiles(deptId, categoryKey, 'first', data.first_semester || []);
            renderCategoryFiles(deptId, categoryKey, 'second', data.second_semester || []);
            
            // STORE AND UPDATE COUNT PERSISTENTLY
            const totalFiles = data.total_files || 0;
            categoryFileCounts.set(`${deptId}-${categoryKey}`, totalFiles);
            updateCategoryCount(categoryKey, totalFiles);
            
            // Update state
            categoryStates.set(uniqueId, {
                ...currentState,
                isLoading: false,
                filesLoaded: true,
                lastLoaded: Date.now(),
                fileCount: totalFiles
            });
            
            console.log(`Successfully loaded ${totalFiles} files for category ${categoryKey}`);
            
        } else {
            console.error('Error loading category files:', data.message);
            if (data.message.includes('Access denied')) {
                showNotification('Access denied: You can only view files from your department.', 'error');
            } else {
                showNotification('Failed to load files: ' + data.message, 'error');
            }
            
            // Show error states
            showErrorState(uniqueId, 'first', data.message);
            showErrorState(uniqueId, 'second', data.message);
            
            // Reset loading state but keep cached count
            categoryStates.set(uniqueId, {
                ...currentState,
                isLoading: false
            });
            
            // Reset category count only if we don't have a cached count
            const cachedCount = categoryFileCounts.get(`${deptId}-${categoryKey}`);
            if (cachedCount === undefined) {
                updateCategoryCount(categoryKey, 0);
            } else {
                updateCategoryCount(categoryKey, cachedCount);
            }
        }
    })
    .catch(error => {
        console.error('Error loading category files:', error);
        showNotification('Failed to load files: Network error', 'error');
        
        // Show error states
        showErrorState(uniqueId, 'first', 'Network error');
        showErrorState(uniqueId, 'second', 'Network error');
        
        // Reset loading state but keep cached count
        categoryStates.set(uniqueId, {
            ...currentState,
            isLoading: false
        });
        
        // Reset category count only if we don't have a cached count
        const cachedCount = categoryFileCounts.get(`${deptId}-${categoryKey}`);
        if (cachedCount === undefined) {
            updateCategoryCount(categoryKey, 0);
        } else {
            updateCategoryCount(categoryKey, cachedCount);
        }
    });
}

function renderCategoryFiles(deptId, categoryKey, semester, files) {
    const uniqueId = `${deptId}-${categoryKey}`;
    const container = document.getElementById(`files-${uniqueId}-${semester}`);
    
    if (!container) {
        console.error(`Container not found: files-${uniqueId}-${semester}`);
        return;
    }
    
    if (files.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class='bx bx-folder-open'></i>
                <p>No files in ${semester === 'first' ? 'First' : 'Second'} Semester</p>
                <small>Files uploaded to this category and semester will appear here</small>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    files.forEach(file => {
        const uploaderInitials = getUserInitials(file.uploaded_by);
        const tags = Array.isArray(file.tags) ? file.tags : [];
        
        html += `
            <div class="file-card" data-file-id="${file.id}">
                <div class="file-header">
                    <div class="file-icon ${getFileTypeClass(file.file_extension)}">
                        <i class='bx ${file.file_icon}'></i>
                    </div>
                    <div class="file-actions">
                        <button class="action-btn download-btn" onclick="downloadFile('${file.id}')" title="Download">
                            <i class='bx bx-download'></i>
                        </button>
                        <button class="action-btn more-btn" onclick="showFileMenu('${file.id}')" title="More options">
                            <i class='bx bx-dots-vertical-rounded'></i>
                        </button>
                    </div>
                </div>
                
                <div class="file-info">
                    <h4 class="file-name" title="${escapeHtml(file.original_name || file.file_name)}">
                        ${escapeHtml(file.original_name || file.file_name)}
                    </h4>
                    
                    ${file.description ? `
                        <p class="file-description" title="${escapeHtml(file.description)}">
                            ${escapeHtml(file.description)}
                        </p>
                    ` : ''}
                </div>
                
                <div class="file-meta">
                    <div class="meta-row">
                        <span class="file-size">${file.formatted_size}</span>
                        <span class="file-type">${file.file_extension.toUpperCase()}</span>
                    </div>
                    <div class="meta-row">
                        <span class="upload-date" title="${formatDateTime(file.uploaded_at)}">
                            ${file.time_ago}
                        </span>
                        <span class="download-count">
                            <i class='bx bx-download'></i> ${file.download_count}
                        </span>
                    </div>
                </div>
                
                <div class="file-uploader">
                    <div class="uploader-info">
                        <div class="uploader-avatar">${uploaderInitials}</div>
                        <span class="uploader-name" title="${escapeHtml(file.uploaded_by)}">${escapeHtml(file.uploaded_by)}</span>
                    </div>
                </div>
                
                ${tags.length > 0 ? `
                    <div class="file-tags">
                        ${tags.map(tag => `<span class="file-tag">${escapeHtml(tag)}</span>`).join('')}
                    </div>
                ` : ''}
                
                <div class="file-overlay" onclick="previewFile('${file.id}')">
                    <div class="overlay-content">
                        <i class='bx bx-show'></i>
                        <span>Preview</span>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function showLoadingState(uniqueId, semester) {
    const container = document.getElementById(`files-${uniqueId}-${semester}`);
    if (container) {
        container.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p>Loading ${semester === 'first' ? 'First' : 'Second'} Semester files...</p>
            </div>
        `;
    }
}

function showErrorState(uniqueId, semester, errorMessage) {
    const container = document.getElementById(`files-${uniqueId}-${semester}`);
    if (container) {
        container.innerHTML = `
            <div class="error-state">
                <i class='bx bx-error-circle'></i>
                <p>Failed to load ${semester === 'first' ? 'First' : 'Second'} Semester files</p>
                <small>${escapeHtml(errorMessage)}</small>
                <button class="retry-btn" onclick="retryLoadCategory('${uniqueId}')">
                    <i class='bx bx-refresh'></i> Try Again
                </button>
            </div>
        `;
    }
}

function updateCategoryCount(categoryKey, count) {
    const countElements = document.querySelectorAll(`[data-category="${categoryKey}"] .category-count`);
    countElements.forEach(element => {
        if (typeof count === 'number') {
            element.textContent = `${count} file${count !== 1 ? 's' : ''}`;
        } else {
            element.textContent = count; // For loading states
        }
    });
}

function retryLoadCategory(uniqueId) {
    const [deptId, categoryKey] = uniqueId.split('-');
    loadedCategories.delete(uniqueId);
    
    // Clear cached count so it shows loading
    categoryFileCounts.delete(`${deptId}-${categoryKey}`);
    
    loadCategoryFiles(parseInt(deptId), categoryKey);
}

// Enhanced function to update category counts from department.js
function updateCategoryCounts(deptId, counts) {
    Object.keys(fileCategories || {}).forEach(categoryKey => {
        const count = counts[categoryKey] || 0;
        
        // Store the count persistently
        categoryFileCounts.set(`${deptId}-${categoryKey}`, count);
        
        // Update UI
        updateCategoryCount(categoryKey, count);
    });
}

// File operation functions
function downloadFile(fileId) {
    // Create a temporary form to download the file
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'handlers/download_file.php';
    form.style.display = 'none';
    
    const fileIdInput = document.createElement('input');
    fileIdInput.type = 'hidden';
    fileIdInput.name = 'file_id';
    fileIdInput.value = fileId;
    
    form.appendChild(fileIdInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}


function showFileMenu(fileId) {
    // Show context menu for file operations
    console.log(`Show menu for file: ${fileId}`);
    // Implement file context menu here
}

// Enhanced UI helper functions
function getFileTypeClass(extension) {
    const typeMap = {
        'pdf': 'file-pdf',
        'doc': 'file-doc', 'docx': 'file-doc',
        'xls': 'file-excel', 'xlsx': 'file-excel',
        'ppt': 'file-powerpoint', 'pptx': 'file-powerpoint',
        'jpg': 'file-image', 'jpeg': 'file-image', 'png': 'file-image', 'gif': 'file-image',
        'txt': 'file-text',
        'zip': 'file-archive', 'rar': 'file-archive',
        'mp4': 'file-video', 'avi': 'file-video',
        'mp3': 'file-audio', 'wav': 'file-audio'
    };
    return typeMap[extension.toLowerCase()] || 'file-default';
}

function getUserInitials(fullName) {
    if (!fullName) return 'U';
    const names = fullName.split(' ');
    let initials = '';
    for (let i = 0; i < Math.min(names.length, 2); i++) {
        if (names[i].length > 0) {
            initials += names[i][0].toUpperCase();
        }
    }
    return initials || 'U';
}

function formatDateTime(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleString();
    } catch (e) {
        return dateString;
    }
}

function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Enhanced notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    document.querySelectorAll('.notification').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        success: 'bx-check-circle',
        error: 'bx-error-circle',
        warning: 'bx-error',
        info: 'bx-info-circle'
    };
    
    const colors = {
        success: { bg: '#10b981', text: 'white' },
        error: { bg: '#ef4444', text: 'white' },
        warning: { bg: '#f59e0b', text: 'white' },
        info: { bg: '#3b82f6', text: 'white' }
    };
    
    const color = colors[type] || colors.info;
    const icon = icons[type] || icons.info;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10001;
        background: ${color.bg};
        color: ${color.text};
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 12px;
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
        max-width: 400px;
        animation: slideInRight 0.3s ease;
        backdrop-filter: blur(10px);
    `;
    
    notification.innerHTML = `
        <i class='bx ${icon}' style="font-size: 20px;"></i>
        <span>${escapeHtml(message)}</span>
        <button onclick="this.parentElement.remove()" style="
            background: none;
            border: none;
            color: inherit;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            margin-left: 8px;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        ">
            <i class='bx bx-x'></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }
    }, 1800);
}

// Initialize category system
function initializeCategorySystem() {
    // Add CSS for enhanced file cards if not exists
    if (!document.querySelector('#category-styles')) {
        const style = document.createElement('style');
        style.id = 'category-styles';
        style.textContent = `
            .file-card {
                background: white;
                border-radius: 16px;
                border: 2px solid #f1f5f9;
                padding: 20px;
                transition: all 0.3s ease;
                cursor: pointer;
                position: relative;
                overflow: hidden;
            }

            .file-card:hover {
                border-color: #e2e8f0;
                transform: translateY(-4px);
                box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
            }

            .file-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
            }

            .file-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                color: white;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }

            .file-pdf { background: linear-gradient(135deg, #dc2626, #b91c1c); }
            .file-doc { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
            .file-excel { background: linear-gradient(135deg, #059669, #047857); }
            .file-powerpoint { background: linear-gradient(135deg, #ea580c, #dc2626); }
            .file-image { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
            .file-text { background: linear-gradient(135deg, #64748b, #475569); }
            .file-archive { background: linear-gradient(135deg, #f59e0b, #d97706); }
            .file-video { background: linear-gradient(135deg, #ec4899, #db2777); }
            .file-audio { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
            .file-default { background: linear-gradient(135deg, #6b7280, #4b5563); }

            .file-actions {
                display: flex;
                gap: 8px;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .file-card:hover .file-actions {
                opacity: 1;
            }

            .action-btn {
                width: 32px;
                height: 32px;
                border: none;
                border-radius: 8px;
                background: rgba(0, 0, 0, 0.05);
                color: #64748b;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;    

            }

            .action-btn:hover {
                background: var(--blue);
                color: white;
                transform: scale(1.1);
            }

            .file-name {
                margin: 0 0 8px 0;
                font-size: 16px;
                font-weight: 600;
                color: #1f2937;
                line-height: 1.4;
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }

            .file-description {
                margin: 0 0 16px 0;
                font-size: 14px;
                color: #6b7280;
                line-height: 1.4;
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }

            .file-meta {
                margin-bottom: 16px;
            }

            .meta-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 8px;
                font-size: 12px;
                color: #9ca3af;
            }

            .meta-row:last-child {
                margin-bottom: 0;
            }

            .file-size {
                font-weight: 600;
                color: #374151;
                margin-right: 8px;
            }

            .file-type {
                background: #f3f4f6;
                padding: 2px 8px;
                border-radius: 12px;
                font-weight: 500;
            }

            .download-count {
                display: flex;
                align-items: center;
                gap: 1px;
                margin-left: 6px;
            }

            .file-uploader {
                display: flex;
                align-items: center;
                margin-bottom: 16px;
            }

            .uploader-info {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .uploader-avatar {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: var(--blue);
                color: white;
                font-size: 10px;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .uploader-name {
                font-size: 12px;
                color: #64748b;
                font-weight: 500;
            }

            .file-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-bottom: 16px;
            }

            .file-tag {
                background: #e0f2fe;
                color: #0369a1;
                font-size: 10px;
                padding: 4px 8px;
                border-radius: 12px;
                font-weight: 500;
            }

            .file-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(39, 107, 50, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.3s ease;
                backdrop-filter: blur(2px);
            }

            .file-card:hover .file-overlay {
                opacity: 1;
            }

            .overlay-content {
                color: white;
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                font-weight: 600;
            }

            .overlay-content i {
                font-size: 32px;
            }

            .loading-state {
                grid-column: 1 / -1;
                text-align: center;
                padding: 40px 20px;
                color: #6b7280;
            }

            .loading-spinner {
                display: inline-block;
                width: 32px;
                height: 32px;
                border: 3px solid #e2e8f0;
                border-radius: 50%;
                border-top-color: #3b82f6;
                animation: spin 1s ease-in-out infinite;
                margin-bottom: 16px;
            }

            .error-state {
                grid-column: 1 / -1;
                text-align: center;
                padding: 40px 20px;
                color: #ef4444;
            }

            .error-state i {
                font-size: 48px;
                margin-bottom: 16px;
                opacity: 0.5;
            }

            .retry-btn {
                background: var(--blue);
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 500;
                margin-top: 12px;
                display: flex;
                align-items: center;
                gap: 8px;
                margin-left: auto;
                margin-right: auto;
            }

            .retry-btn:hover {
                background: #2563eb;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Initialize semester tab event handlers
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('semester-tab')) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeCategorySystem();
});

// Export functions for global access
window.toggleCategory = toggleCategory;
window.showCategorySemester = showCategorySemester;
window.loadCategoryFiles = loadCategoryFiles;
window.downloadFile = downloadFile;
window.showFileMenu = showFileMenu;
window.retryLoadCategory = retryLoadCategory;
window.updateCategoryCounts = updateCategoryCounts; // Export this function





