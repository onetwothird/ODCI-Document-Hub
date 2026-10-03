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
        // Add null/undefined checks and fallbacks for all file properties
        const fileName = file.original_name || file.file_name || 'Unknown File';
        const description = file.description || '';
        const uploadedBy = file.uploaded_by || 'Unknown User';
        const fileExtension = file.file_extension || 'unknown';
        const fileIcon = file.file_icon || 'bx-file';
        const formattedSize = file.formatted_size || '0 B';
        const timeAgo = file.time_ago || 'Unknown';
        const downloadCount = file.download_count || 0;
        const uploadedAt = file.uploaded_at || new Date().toISOString();
        const fileId = file.id || '';
        
        const uploaderInitials = getUserInitials(uploadedBy);
        const tags = Array.isArray(file.tags) ? file.tags : [];
        
        html += `
            <div class="file-card" data-file-id="${fileId}">
                <div class="file-header">
                    <div class="file-icon ${getFileTypeClass(fileExtension)}">
                        <i class='bx ${fileIcon}'></i>
                    </div>
                    <div class="file-actions">
                        <button class="action-btn download-btn" onclick="downloadFile('${fileId}')" title="Download">
                            <i class='bx bx-download'></i>
                        </button>
                        <button class="action-btn more-btn" onclick="showFileMenu('${fileId}')" title="More options">
                            <i class='bx bx-dots-vertical-rounded'></i>
                        </button>
                    </div>
                </div>
                
                <div class="file-info">
                    <h4 class="file-name" title="${escapeHtml(fileName)}">
                        ${escapeHtml(fileName)}
                    </h4>
                    
                    ${description ? `
                        <p class="file-description" title="${escapeHtml(description)}">
                            ${escapeHtml(description)}
                        </p>
                    ` : ''}
                </div>
                
                <div class="file-meta">
                    <div class="meta-row">
                        <span class="file-size">${formattedSize}</span>
                        <span class="file-type">${fileExtension.toUpperCase()}</span>
                    </div>
                    <div class="meta-row">
                        <span class="upload-date" title="${formatDateTime(uploadedAt)}">
                            ${timeAgo}
                        </span>
                        <span class="download-count">
                            <i class='bx bx-download'></i> ${downloadCount}
                        </span>
                    </div>
                </div>
                
                <div class="file-uploader">
                    <div class="uploader-info">
                        <div class="uploader-avatar">${uploaderInitials}</div>
                        <span class="uploader-name" title="${escapeHtml(uploadedBy)}">${escapeHtml(uploadedBy)}</span>
                    </div>
                </div>
                
                ${tags.length > 0 ? `
                    <div class="file-tags">
                        ${tags.map(tag => `<span class="file-tag">${escapeHtml(tag || '')}</span>`).join('')}
                    </div>
                ` : ''}
                
                <div class="file-overlay" onclick="previewFile('${fileId}')">
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

// Enhanced escapeHtml function to handle null/undefined values
function escapeHtml(unsafe) {
    // Handle null, undefined, or non-string values
    if (unsafe === null || unsafe === undefined) return '';
    if (typeof unsafe !== 'string') return String(unsafe);
    
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Enhanced getUserInitials function
function getUserInitials(fullName) {
    if (!fullName || typeof fullName !== 'string') return 'U';
    const names = fullName.trim().split(' ');
    let initials = '';
    for (let i = 0; i < Math.min(names.length, 2); i++) {
        if (names[i] && names[i].length > 0) {
            initials += names[i][0].toUpperCase();
        }
    }
    return initials || 'U';
}

// Enhanced getFileTypeClass function
function getFileTypeClass(extension) {
    if (!extension || typeof extension !== 'string') return 'file-default';
    
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

// Enhanced formatDateTime function
function formatDateTime(dateString) {
    if (!dateString) return 'Unknown date';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid date';
        return date.toLocaleString();
    } catch (e) {
        return dateString || 'Unknown date';
    }
}