// Enhanced File Preview and Detail Modal System
let currentFileData = null;
let currentActiveTab = 'info';

// Initialize modal system
document.addEventListener('DOMContentLoaded', function() {
    // Add click handler to close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('file-preview-modal') && e.target.classList.contains('show')) {
            closeFilePreview();
        }
        if (e.target.classList.contains('file-detail-modal') && e.target.classList.contains('show')) {
            closeFileDetailModal();
        }
        if (e.target.classList.contains('delete-confirm-modal') && e.target.classList.contains('show')) {
            closeDeleteConfirm();
        }
    });

    // Add escape key handler
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilePreview();
            closeFileDetailModal();
            closeDeleteConfirm();
        }
    });
});

// Modified previewFile function to show preview modal
function previewFile(fileId) {
    if (!fileId) {
        console.error('No file ID provided');
        return;
    }

    // Show preview modal
    const modal = document.getElementById('filePreviewModal');
    if (!modal) {
        console.error('Preview modal not found');
        return;
    }

    // Show modal with loading state
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    // Reset to loading state
    resetPreviewToLoading();

    // Load file data
    loadFileData(fileId)
        .then(fileData => {
            currentFileData = fileData;
            populatePreviewModal(fileData);
        })
        .catch(error => {
            console.error('Error loading file data:', error);
            showPreviewError('Failed to load file information');
        });
}

// Reset preview modal to loading state
function resetPreviewToLoading() {
    document.getElementById('previewFileName').textContent = 'Loading...';
    document.getElementById('previewFileStats').textContent = 'Loading file information...';
    document.getElementById('previewUploader').textContent = 'Uploaded by: Loading...';
    document.getElementById('previewDate').textContent = 'Date: Loading...';
    
    const previewArea = document.getElementById('previewArea');
    previewArea.innerHTML = `
        <div class="preview-loading">
            <div class="loading-spinner"></div>
            <p>Loading preview...</p>
        </div>
    `;
}

// Load file data from server
async function loadFileData(fileId) {
    try {
        const response = await fetch('handlers/get_file_info.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ file_id: fileId })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to load file data');
        }

        return data.file;
    } catch (error) {
        console.error('Error fetching file data:', error);
        throw error;
    }
}

// Populate preview modal with file data
function populatePreviewModal(fileData) {
    // Update file icon
    const iconElement = document.getElementById('previewFileIcon');
    const iconClass = getFileIconClass(fileData.file_extension);
    const colorClass = getFileTypeClass(fileData.file_extension);
    
    iconElement.className = `preview-file-icon ${colorClass}`;
    iconElement.innerHTML = `<i class='bx ${iconClass}'></i>`;

    // Update file name and stats
    document.getElementById('previewFileName').textContent = fileData.original_name || fileData.file_name;
    document.getElementById('previewFileStats').textContent = `${fileData.formatted_size} • ${fileData.file_extension.toUpperCase()} • ${fileData.time_ago}`;

    // Update footer info
    document.getElementById('previewUploader').textContent = `Uploaded by: ${fileData.uploaded_by}`;
    document.getElementById('previewDate').textContent = `Date: ${formatDateTime(fileData.uploaded_at)}`;

    // Load preview content with blur background effect
    loadPreviewContentWithBlur(fileData);
}

// Enhanced preview content loading with blur background
function loadPreviewContentWithBlur(fileData) {
    const previewArea = document.getElementById('previewArea');
    const extension = fileData.file_extension.toLowerCase();

    // Clear loading state
    previewArea.innerHTML = '';

    // Create container with blurred background
    const previewContainer = document.createElement('div');
    previewContainer.className = 'preview-with-blur';
    previewContainer.style.cssText = `
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 12px;
    `;

    // Create blurred background
    const blurredBg = document.createElement('div');
    blurredBg.className = 'blurred-background';
    blurredBg.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        filter: blur(20px) brightness(0.7);
        opacity: 0.6;
        z-index: 1;
    `;

    // Create content overlay
    const contentOverlay = document.createElement('div');
    contentOverlay.className = 'content-overlay';
    contentOverlay.style.cssText = `
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 40px;
        color: white;
        text-shadow: 0 2px 8px rgba(0,0,0,0.8);
    `;

    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(extension)) {
        // Image preview with blurred background
        const bgImg = document.createElement('img');
        bgImg.src = `handlers/file_viewer.php?id=${fileData.id}`;
        bgImg.style.cssText = `
            width: 100%;
            height: 100%;
            object-fit: cover;
        `;
        bgImg.onerror = () => showPreviewUnsupported(fileData, extension);
        blurredBg.appendChild(bgImg);

        contentOverlay.innerHTML = `
            <div class="preview-icon-large">
                <i class='bx bx-image' style="font-size: 80px; margin-bottom: 20px; color: rgba(255,255,255,0.9);"></i>
            </div>
            <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; color: white;">
                ${escapeHtml(fileData.original_name || fileData.file_name)}
            </h3>
            <p style="font-size: 16px; margin-bottom: 30px; color: rgba(255,255,255,0.8);">
                ${fileData.formatted_size} • ${extension.toUpperCase()} Image
            </p>
            <div class="preview-actions" style="display: flex; gap: 15px;">
                <button class="preview-btn download-btn" onclick="downloadFileFromPreview()" 
                        style="background: rgba(16, 185, 129, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-download'></i>
                    Download
                </button>
                ${fileData.can_delete ? `
                <button class="preview-btn delete-btn" onclick="confirmDeleteFile()" 
                        style="background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-trash'></i>
                    Delete
                </button>
                ` : ''}
            </div>
        `;
        
    } else if (extension === 'pdf') {
        // PDF preview with document icon background
        blurredBg.style.background = 'linear-gradient(135deg, #dc2626, #991b1b)';
        blurredBg.innerHTML = `
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 200px; color: rgba(255,255,255,0.1);">
                <i class='bx bxs-file-pdf'></i>
            </div>
        `;

        contentOverlay.innerHTML = `
            <div class="preview-icon-large">
                <i class='bx bxs-file-pdf' style="font-size: 80px; margin-bottom: 20px; color: rgba(255,255,255,0.9);"></i>
            </div>
            <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; color: white;">
                ${escapeHtml(fileData.original_name || fileData.file_name)}
            </h3>
            <p style="font-size: 16px; margin-bottom: 30px; color: rgba(255,255,255,0.8);">
                ${fileData.formatted_size} • PDF Document
            </p>
            <div class="preview-actions" style="display: flex; gap: 15px;">
                <button class="preview-btn download-btn" onclick="downloadFileFromPreview()" 
                        style="background: rgba(16, 185, 129, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-download'></i>
                    Download
                </button>
                ${fileData.can_delete ? `
                <button class="preview-btn delete-btn" onclick="confirmDeleteFile()" 
                        style="background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-trash'></i>
                    Delete
                </button>
                ` : ''}
            </div>
        `;
        
    } else if (['txt', 'csv', 'json', 'xml', 'html', 'css', 'js', 'php', 'py'].includes(extension)) {
        // Text file preview with code background
        blurredBg.style.background = 'linear-gradient(135deg, #374151, #1f2937)';
        blurredBg.innerHTML = `
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 200px; color: rgba(255,255,255,0.1);">
                <i class='bx bx-code-alt'></i>
            </div>
        `;

        contentOverlay.innerHTML = `
            <div class="preview-icon-large">
                <i class='bx bx-code-alt' style="font-size: 80px; margin-bottom: 20px; color: rgba(255,255,255,0.9);"></i>
            </div>
            <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; color: white;">
                ${escapeHtml(fileData.original_name || fileData.file_name)}
            </h3>
            <p style="font-size: 16px; margin-bottom: 30px; color: rgba(255,255,255,0.8);">
                ${fileData.formatted_size} • ${extension.toUpperCase()} File
            </p>
            <div class="preview-actions" style="display: flex; gap: 15px;">
                <button class="preview-btn download-btn" onclick="downloadFileFromPreview()" 
                        style="background: rgba(16, 185, 129, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-download'></i>
                    Download
                </button>
                ${fileData.can_delete ? `
                <button class="preview-btn delete-btn" onclick="confirmDeleteFile()" 
                        style="background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-trash'></i>
                    Delete
                </button>
                ` : ''}
            </div>
        `;
        
    } else if (['doc', 'docx'].includes(extension)) {
        // Word document preview
        blurredBg.style.background = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
        blurredBg.innerHTML = `
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 200px; color: rgba(255,255,255,0.1);">
                <i class='bx bxs-file-doc'></i>
            </div>
        `;

        contentOverlay.innerHTML = `
            <div class="preview-icon-large">
                <i class='bx bxs-file-doc' style="font-size: 80px; margin-bottom: 20px; color: rgba(255,255,255,0.9);"></i>
            </div>
            <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; color: white;">
                ${escapeHtml(fileData.original_name || fileData.file_name)}
            </h3>
            <p style="font-size: 16px; margin-bottom: 30px; color: rgba(255,255,255,0.8);">
                ${fileData.formatted_size} • Word Document
            </p>
            <div class="preview-actions" style="display: flex; gap: 15px;">
                <button class="preview-btn download-btn" onclick="downloadFileFromPreview()" 
                        style="background: rgba(16, 185, 129, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-download'></i>
                    Download
                </button>
                ${fileData.can_delete ? `
                <button class="preview-btn delete-btn" onclick="confirmDeleteFile()" 
                        style="background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-trash'></i>
                    Delete
                </button>
                ` : ''}
            </div>
        `;
        
    } else if (['xls', 'xlsx'].includes(extension)) {
        // Excel preview
        blurredBg.style.background = 'linear-gradient(135deg, #059669, #047857)';
        blurredBg.innerHTML = `
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 200px; color: rgba(255,255,255,0.1);">
                <i class='bx bxs-spreadsheet'></i>
            </div>
        `;

        contentOverlay.innerHTML = `
            <div class="preview-icon-large">
                <i class='bx bxs-spreadsheet' style="font-size: 80px; margin-bottom: 20px; color: rgba(255,255,255,0.9);"></i>
            </div>
            <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; color: white;">
                ${escapeHtml(fileData.original_name || fileData.file_name)}
            </h3>
            <p style="font-size: 16px; margin-bottom: 30px; color: rgba(255,255,255,0.8);">
                ${fileData.formatted_size} • Excel Spreadsheet
            </p>
            <div class="preview-actions" style="display: flex; gap: 15px;">
                <button class="preview-btn download-btn" onclick="downloadFileFromPreview()" 
                        style="background: rgba(16, 185, 129, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-download'></i>
                    Download
                </button>
                ${fileData.can_delete ? `
                <button class="preview-btn delete-btn" onclick="confirmDeleteFile()" 
                        style="background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-trash'></i>
                    Delete
                </button>
                ` : ''}
            </div>
        `;
        
    } else {
        // Generic file type
        const fileTypeColors = {
            'zip': 'linear-gradient(135deg, #f59e0b, #d97706)',
            'rar': 'linear-gradient(135deg, #f59e0b, #d97706)',
            'mp4': 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
            'avi': 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
            'mp3': 'linear-gradient(135deg, #ec4899, #db2777)',
            'wav': 'linear-gradient(135deg, #ec4899, #db2777)',
        };
        
        blurredBg.style.background = fileTypeColors[extension] || 'linear-gradient(135deg, #6b7280, #4b5563)';
        blurredBg.innerHTML = `
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 200px; color: rgba(255,255,255,0.1);">
                <i class='bx ${getFileIconClass(extension)}'></i>
            </div>
        `;

        contentOverlay.innerHTML = `
            <div class="preview-icon-large">
                <i class='bx ${getFileIconClass(extension)}' style="font-size: 80px; margin-bottom: 20px; color: rgba(255,255,255,0.9);"></i>
            </div>
            <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; color: white;">
                ${escapeHtml(fileData.original_name || fileData.file_name)}
            </h3>
            <p style="font-size: 16px; margin-bottom: 30px; color: rgba(255,255,255,0.8);">
                ${fileData.formatted_size} • ${extension.toUpperCase()} File
            </p>
            <div class="preview-actions" style="display: flex; gap: 15px;">
                <button class="preview-btn download-btn" onclick="downloadFileFromPreview()" 
                        style="background: rgba(16, 185, 129, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-download'></i>
                    Download
                </button>
                ${fileData.can_delete ? `
                <button class="preview-btn delete-btn" onclick="confirmDeleteFile()" 
                        style="background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class='bx bx-trash'></i>
                    Delete
                </button>
                ` : ''}
            </div>
        `;
    }

    // Assemble the preview container
    previewContainer.appendChild(blurredBg);
    previewContainer.appendChild(contentOverlay);
    previewArea.appendChild(previewContainer);

    // Add hover effects to buttons
    setTimeout(() => {
        addButtonHoverEffects();
    }, 100);
}

// Add hover effects to preview buttons
function addButtonHoverEffects() {
    const buttons = document.querySelectorAll('.preview-btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
            if (this.classList.contains('download-btn')) {
                this.style.background = 'rgba(16, 185, 129, 1)';
                this.style.boxShadow = '0 8px 25px rgba(16, 185, 129, 0.4)';
            } else if (this.classList.contains('delete-btn')) {
                this.style.background = 'rgba(239, 68, 68, 1)';
                this.style.boxShadow = '0 8px 25px rgba(239, 68, 68, 0.4)';
            }
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            if (this.classList.contains('download-btn')) {
                this.style.background = 'rgba(16, 185, 129, 0.9)';
                this.style.boxShadow = 'none';
            } else if (this.classList.contains('delete-btn')) {
                this.style.background = 'rgba(239, 68, 68, 0.9)';
                this.style.boxShadow = 'none';
            }
        });
    });
}

// Show preview error
function showPreviewError(message) {
    const previewArea = document.getElementById('previewArea');
    previewArea.innerHTML = `
        <div class="preview-unsupported">
            <i class='bx bx-error-circle' style="font-size: 72px; color: #ef4444; margin-bottom: 20px;"></i>
            <h3 style="font-size: 22px; font-weight: 700; color: #dc2626; margin-bottom: 8px;">Error loading preview</h3>
            <p style="font-size: 16px; color: #991b1b; margin-bottom: 20px;">${message}</p>
            <button class="quick-action-btn" onclick="closeFilePreview()" 
                    style="background: linear-gradient(145deg, #6b7280, #4b5563); color: white; border: none; border-radius: 12px; padding: 12px 20px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class='bx bx-x'></i>
                Close
            </button>
        </div>
    `;
}

// Close file preview modal
function closeFilePreview() {
    const modal = document.getElementById('filePreviewModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Clear content after transition
        setTimeout(() => {
            resetPreviewToLoading();
        }, 400);
    }
}

// Open file detail modal
function openFileDetailModal() {
    if (!currentFileData) {
        console.error('No current file data available');
        return;
    }

    // Hide preview modal first
    closeFilePreview();

    setTimeout(() => {
        const modal = document.getElementById('fileDetailModal');
        if (!modal) {
            console.error('Detail modal not found');
            return;
        }

        // Show modal
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Populate detail modal
        populateDetailModal(currentFileData);
    }, 200);
}

// Enhanced back to preview function with loading state
function backToPreview() {
    if (!currentFileData) {
        closeFileDetailModal();
        return;
    }

    // Hide detail modal first
    closeFileDetailModal();

    // Show preview modal again after a short delay
    setTimeout(() => {
        const modal = document.getElementById('filePreviewModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Show loading state first
            resetPreviewToLoading();
            
            // Then populate with data after a brief moment
            setTimeout(() => {
                populatePreviewModal(currentFileData);
            }, 300);
        }
    }, 200);
}

// Populate detail modal with file data
function populateDetailModal(fileData) {
    // Update header
    const iconElement = document.getElementById('detailFileIcon');
    const iconClass = getFileIconClass(fileData.file_extension);
    const colorClass = getFileTypeClass(fileData.file_extension);
    
    iconElement.className = `detail-file-icon ${colorClass}`;
    iconElement.innerHTML = `<i class='bx ${iconClass}'></i>`;

    document.getElementById('detailFileName').textContent = fileData.original_name || fileData.file_name;
    document.getElementById('detailFilePath').textContent = fileData.file_path || '/path/to/file';

    // Populate information panel
    document.getElementById('infoFileName').textContent = fileData.original_name || fileData.file_name;
    document.getElementById('infoFileSize').textContent = fileData.formatted_size || 'Unknown';
    document.getElementById('infoFileType').textContent = fileData.file_extension ? fileData.file_extension.toUpperCase() : 'Unknown';
    document.getElementById('infoMimeType').textContent = fileData.mime_type || 'Unknown';
    
    document.getElementById('infoAcademicYear').textContent = fileData.academic_year || 'N/A';
    document.getElementById('infoSemester').textContent = fileData.semester ? 
        (fileData.semester === 'first' ? 'First Semester' : 'Second Semester') : 'N/A';
    document.getElementById('infoCategory').textContent = getCategoryDisplayName(fileData.category);
    document.getElementById('infoDepartment').textContent = fileData.department_name || 'N/A';
    
    document.getElementById('infoUploader').textContent = fileData.uploaded_by || 'Unknown';
    document.getElementById('infoUploadDate').textContent = formatDateTime(fileData.uploaded_at);
    document.getElementById('infoDownloadCount').textContent = fileData.download_count || '0';
    
    // Description
    const descElement = document.getElementById('infoDescription');
    if (fileData.description && fileData.description.trim()) {
        descElement.textContent = fileData.description;
        descElement.style.fontStyle = 'normal';
        descElement.style.color = '#374151';
    } else {
        descElement.textContent = 'No description available.';
        descElement.style.fontStyle = 'italic';
        descElement.style.color = '#9ca3af';
    }
    
    // Tags
    const tagsSection = document.getElementById('tagsSection');
    const tagsContainer = document.getElementById('infoTags');
    
    if (fileData.tags && Array.isArray(fileData.tags) && fileData.tags.length > 0) {
        tagsSection.style.display = 'block';
        tagsContainer.innerHTML = fileData.tags.map(tag => 
            `<span class="info-tag">${escapeHtml(tag)}</span>`
        ).join('');
    } else {
        tagsSection.style.display = 'none';
    }
    
    // Activity log
    document.getElementById('activityUploadTime').textContent = formatDateTime(fileData.uploaded_at);
    
    // Update delete confirmation modal data
    updateDeleteConfirmModal(fileData);
    
    // Add back button functionality to close button
    const closeBtn = document.querySelector('.detail-close-btn');
    if (closeBtn) {
        closeBtn.setAttribute('onclick', 'backToPreview()');
        closeBtn.innerHTML = '<i class="bx bx-arrow-back"></i>';
        closeBtn.setAttribute('title', 'Back to Preview');
    }
}

// Switch detail tabs
function switchDetailTab(tabName) {
    // Update tab active state
    document.querySelectorAll('.detail-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Update panel active state
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    document.getElementById(`${tabName}-panel`).classList.add('active');

    currentActiveTab = tabName;

    // Load preview in detail modal if preview tab is selected
    if (tabName === 'preview' && currentFileData) {
        loadDetailPreview(currentFileData);
    }
}

// Load preview in detail modal
function loadDetailPreview(fileData) {
    const previewArea = document.getElementById('detailPreviewArea');
    const extension = fileData.file_extension.toLowerCase();

    previewArea.innerHTML = `
        <div class="preview-loading">
            <div class="loading-spinner"></div>
            <p>Loading preview...</p>
        </div>
    `;

    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(extension)) {
        const img = document.createElement('img');
        img.className = 'preview-image';
        img.src = `handlers/file_viewer.php?id=${fileData.id}`;
        img.alt = fileData.original_name;
        img.style.maxHeight = '100%';
        img.style.maxWidth = '100%';
        img.style.objectFit = 'contain';
        img.onerror = () => {
            showDetailUnsupported(fileData, extension);
        };
        previewArea.innerHTML = '';
        previewArea.appendChild(img);
        
    } else if (extension === 'pdf') {
        const iframe = document.createElement('iframe');
        iframe.src = `handlers/file_viewer.php?id=${fileData.id}`;
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.style.borderRadius = '12px';
        previewArea.innerHTML = '';
        previewArea.appendChild(iframe);
        
    } else if (['txt', 'csv', 'json', 'xml', 'html', 'css', 'js', 'php', 'py'].includes(extension)) {
        // Text file preview in detail modal
        fetch(`handlers/file_viewer.php?id=${fileData.id}`)
            .then(response => response.text())
            .then(text => {
                const textDiv = document.createElement('div');
                textDiv.className = 'preview-text-content';
                textDiv.style.height = '100%';
                textDiv.textContent = text.substring(0, 5000); // Limit to first 5000 chars
                if (text.length > 5000) {
                    textDiv.textContent += '\n\n... (truncated)';
                }
                previewArea.innerHTML = '';
                previewArea.appendChild(textDiv);
            })
            .catch(() => showDetailUnsupported(fileData, extension));
        
    } else {
        showDetailUnsupported(fileData, extension);
    }
}

// Show unsupported preview in detail modal
function showDetailUnsupported(fileData, extension) {
    const previewArea = document.getElementById('detailPreviewArea');
    previewArea.innerHTML = `
        <div class="preview-unsupported" style="height: 100%; display: flex; align-items: center; justify-content: center;">
            <div style="text-align: center;">
                <i class='bx bx-file' style="font-size: 72px; color: #10b981; opacity: 0.8; margin-bottom: 20px;"></i>
                <h3 style="font-size: 22px; font-weight: 700; color: #047857; margin-bottom: 8px;">Preview not available</h3>
                <p style="font-size: 16px; color: #065f46; opacity: 0.8; margin-bottom: 20px;">Preview is not supported for ${extension.toUpperCase()} files</p>
                <p style="font-size: 14px; color: #6b7280;">Use the download button to view the file</p>
            </div>
        </div>
    `;
}

// Close file detail modal
function closeFileDetailModal() {
    const modal = document.getElementById('fileDetailModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Reset to info tab and restore close button
        setTimeout(() => {
            switchDetailTab('info');
            const closeBtn = document.querySelector('.detail-close-btn');
            if (closeBtn) {
                closeBtn.setAttribute('onclick', 'closeFileDetailModal()');
                closeBtn.innerHTML = '<i class="bx bx-x"></i>';
                closeBtn.setAttribute('title', 'Close');
            }
        }, 400);
    }
}

// Download file from preview
function downloadFileFromPreview() {
    if (currentFileData) {
        downloadFile(currentFileData.id);
    }
}

// Download file from detail modal
function downloadFileFromDetail() {
    if (currentFileData) {
        downloadFile(currentFileData.id);
    }
}

// Enhanced download function
function downloadFile(fileId) {
    if (!fileId) {
        showNotification('Invalid file ID', 'error');
        return;
    }

    // Create a temporary link and trigger download
    const link = document.createElement('a');
    link.href = `handlers/download_file.php?id=${fileId}`;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Show success message
    showNotification('Download started...', 'success');
}

// Share file function (placeholder)
function shareFile() {
    if (currentFileData) {
        // Create shareable link or copy to clipboard
        const shareUrl = `${window.location.origin}/shared/${currentFileData.id}`;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(shareUrl).then(() => {
                showNotification('Share link copied to clipboard!', 'success');
            }).catch(() => {
                showNotification('Failed to copy share link', 'error');
            });
        } else {
            showNotification('Sharing not supported in this browser', 'warning');
        }
    }
}

// Edit file info function (placeholder)
function editFileInfo() {
    showNotification('Edit functionality coming soon!', 'info');
}

// Move file function (placeholder)
function moveFile() {
    showNotification('Move functionality coming soon!', 'info');
}

// Confirm delete file
function confirmDeleteFile() {
    if (!currentFileData) return;
    
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.classList.add('show');
        updateDeleteConfirmModal(currentFileData);
    }
}

// Update delete confirmation modal
function updateDeleteConfirmModal(fileData) {
    const iconElement = document.getElementById('deleteFileIcon');
    const iconClass = getFileIconClass(fileData.file_extension);
    const colorClass = getFileTypeClass(fileData.file_extension);
    
    iconElement.className = `delete-file-icon ${colorClass}`;
    iconElement.innerHTML = `<i class='bx ${iconClass}'></i>`;
    
    document.getElementById('deleteFileName').textContent = fileData.original_name || fileData.file_name;
    document.getElementById('deleteFileDetails').textContent = 
        `${fileData.formatted_size} • ${fileData.file_extension.toUpperCase()} • ${fileData.time_ago}`;
}

// Close delete confirmation
function closeDeleteConfirm() {
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

// Execute file deletion
async function executeDeleteFile() {
    if (!currentFileData) return;
    
    try {
        const response = await fetch('handlers/delete_file.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ file_id: currentFileData.id })
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification('File deleted successfully!', 'success');
            
            // Close all modals
            closeDeleteConfirm();
            closeFileDetailModal();
            closeFilePreview();
            
            // Refresh the file list if functions exist
            if (typeof loadCategoryFiles === 'function') {
                const categoryKey = currentFileData.category;
                const deptId = currentFileData.department_id || (typeof userDepartmentId !== 'undefined' ? userDepartmentId : null);
                
                if (categoryKey && deptId) {
                    // Clear the loaded flag to force refresh if loadedCategories exists
                    if (typeof loadedCategories !== 'undefined') {
                        const uniqueId = `${deptId}-${categoryKey}`;
                        loadedCategories.delete(uniqueId);
                    }
                    loadCategoryFiles(deptId, categoryKey);
                }
            }
            
        } else {
            showNotification('Failed to delete file: ' + data.message, 'error');
        }
        
    } catch (error) {
        console.error('Error deleting file:', error);
        showNotification('Error deleting file: Network error', 'error');
    }
}

// Enhanced notification function
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        z-index: 10003;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        font-weight: 600;
        max-width: 350px;
        word-wrap: break-word;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 1800);
}

// Helper functions
function getFileIconClass(extension) {
    const iconMap = {
        'pdf': 'bxs-file-pdf',
        'doc': 'bxs-file-doc',
        'docx': 'bxs-file-doc',
        'xls': 'bxs-spreadsheet',
        'xlsx': 'bxs-spreadsheet',
        'ppt': 'bxs-file-blank',
        'pptx': 'bxs-file-blank',
        'jpg': 'bxs-file-image',
        'jpeg': 'bxs-file-image',
        'png': 'bxs-file-image',
        'gif': 'bxs-file-image',
        'webp': 'bxs-file-image',
        'svg': 'bxs-file-image',
        'txt': 'bxs-file-txt',
        'csv': 'bxs-file-txt',
        'json': 'bx-code-alt',
        'xml': 'bx-code-alt',
        'html': 'bx-code-alt',
        'css': 'bx-code-alt',
        'js': 'bx-code-alt',
        'php': 'bx-code-alt',
        'py': 'bx-code-alt',
        'zip': 'bxs-file-archive',
        'rar': 'bxs-file-archive',
        'mp4': 'bxs-videos',
        'avi': 'bxs-videos',
        'mp3': 'bxs-music',
        'wav': 'bxs-music'
    };
    return iconMap[extension?.toLowerCase()] || 'bxs-file';
}

function getFileTypeClass(extension) {
    const typeMap = {
        'pdf': 'file-pdf',
        'doc': 'file-doc', 'docx': 'file-doc',
        'xls': 'file-excel', 'xlsx': 'file-excel',
        'ppt': 'file-powerpoint', 'pptx': 'file-powerpoint',
        'jpg': 'file-image', 'jpeg': 'file-image', 'png': 'file-image', 'gif': 'file-image', 'webp': 'file-image', 'svg': 'file-image',
        'txt': 'file-text', 'csv': 'file-text',
        'json': 'file-code', 'xml': 'file-code', 'html': 'file-code', 'css': 'file-code', 'js': 'file-code', 'php': 'file-code', 'py': 'file-code',
        'zip': 'file-archive', 'rar': 'file-archive',
        'mp4': 'file-video', 'avi': 'file-video',
        'mp3': 'file-audio', 'wav': 'file-audio'
    };
    return typeMap[extension?.toLowerCase()] || 'file-default';
}

function getCategoryDisplayName(categoryKey) {
    const categoryNames = {
        'ipcr_accomplishment': 'IPCR Accomplishment',
        'ipcr_target': 'IPCR Target',
        'workload': 'Workload',
        'course_syllabus': 'Course Syllabus',
        'syllabus_acceptance': 'Course Syllabus Acceptance Form',
        'exam': 'Exam',
        'tos': 'TOS',
        'class_record': 'Class Record',
        'grading_sheet': 'Grading Sheet',
        'attendance_sheet': 'Attendance Sheet',
        'stakeholder_feedback': 'Stakeholder\'s Feedback Form w/ Summary',
        'consultation': 'Consultation',
        'lecture': 'Lecture',
        'activities': 'Activities',
        'exam_acknowledgement': 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form',
        'consultation_log': 'Consultation Log Sheet Form'
    };
    return categoryNames[categoryKey] || categoryKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

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

// Export functions for global access
window.previewFile = previewFile;
window.closeFilePreview = closeFilePreview;
window.openFileDetailModal = openFileDetailModal;
window.closeFileDetailModal = closeFileDetailModal;
window.backToPreview = backToPreview;
window.switchDetailTab = switchDetailTab;
window.downloadFileFromPreview = downloadFileFromPreview;
window.downloadFileFromDetail = downloadFileFromDetail;
window.downloadFile = downloadFile;
window.shareFile = shareFile;
window.editFileInfo = editFileInfo;
window.moveFile = moveFile;
window.confirmDeleteFile = confirmDeleteFile;
window.closeDeleteConfirm = closeDeleteConfirm;
window.executeDeleteFile = executeDeleteFile;
window.showNotification = showNotification;