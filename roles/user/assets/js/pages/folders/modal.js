
// Modal functionality
function openUploadModal(customFolderId = null, semester = null, folderName = '') {
    const modal = document.getElementById('uploadModal');
    const container = document.getElementById('modalContainer');
    
    // Populate academic years when modal opens
    populateAcademicYears();
    
    // Auto-select current semester
    autoSelectCurrentSemester();

    const categorySelection = document.getElementById('uploadCategorySelection');
    const categorySelect = document.querySelector('#uploadForm select[name="category"]');
    const folderInput = document.querySelector('#uploadForm input[name="custom_folder_id"]');
    const folderContext = document.getElementById('customFolderUploadContext');
    const folderNameLabel = document.getElementById('customFolderUploadName');
    const isCustomFolderUpload = customFolderId !== null && customFolderId !== '';

    if (folderInput) folderInput.value = isCustomFolderUpload ? String(customFolderId) : '';
    if (categorySelection) categorySelection.hidden = isCustomFolderUpload;
    if (categorySelect) categorySelect.required = !isCustomFolderUpload;
    if (folderContext) folderContext.hidden = !isCustomFolderUpload;
    if (folderNameLabel) folderNameLabel.textContent = folderName;

    if (isCustomFolderUpload && (semester === 'first' || semester === 'second')) {
        const semesterInput = document.querySelector(`#uploadForm input[name="semester"][value="${semester}"]`);
        if (semesterInput) semesterInput.checked = true;
    }
    
    modal.style.opacity = '1';
    modal.style.visibility = 'visible';
    setTimeout(() => {
        container.style.transform = 'scale(1) translateY(0)';
    }, 10);
    document.body.style.overflow = 'hidden';
}

function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    const container = document.getElementById('modalContainer');
    container.style.transform = 'scale(0.9) translateY(20px)';
    setTimeout(() => {
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
        document.body.style.overflow = 'auto';
        resetForm();
    }, 300);
}

function handleFileSelect(files) {
    if (isUploading) return; // FIX: Prevent file selection during upload
    selectedFiles = Array.from(files);
    displayFilePreview();
}

function handleDrop(event) {
    event.preventDefault();
    if (isUploading) return; // FIX: Prevent drops during upload
    
    const dropZone = event.target;
    dropZone.style.borderColor = '#10b981';
    dropZone.style.background = 'linear-gradient(135deg, #f0fdf4, #ecfdf5)';
    
    const files = Array.from(event.dataTransfer.files);
    selectedFiles = files;
    displayFilePreview();
}

function displayFilePreview() {
    const uploadPrompt = document.getElementById('uploadPrompt');
    const filePreview = document.getElementById('filePreview');
    
    if (selectedFiles.length > 0) {
        uploadPrompt.style.display = 'none';
        filePreview.style.display = 'block';
        
        let html = '<h4 style="margin: 0 0 16px 0; color: #065f46; font-size: 16px;">Selected Files:</h4>';
        
        selectedFiles.forEach((file, index) => {
            const fileIcon = getFileIcon(file.name);
            html += `
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: white; border-radius: 8px; margin-bottom: 8px; border: 1px solid #e5e7eb;">
                    <div style="display: flex; align-items: center;">
                        <i class='bx ${fileIcon}' style="font-size: 24px; color: #10b981; margin-right: 12px;"></i>
                        <div>
                            <div style="font-weight: 500; color: #374151; font-size: 14px;">${file.name}</div>
                            <div style="font-size: 12px; color: #6b7280;">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" onclick="removeFile(${index})" ${isUploading ? 'disabled' : ''} style="background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; padding: 6px; cursor: ${isUploading ? 'not-allowed' : 'pointer'}; transition: all 0.3s ease; opacity: ${isUploading ? '0.5' : '1'};" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                        <i class='bx bx-x' style="font-size: 16px;"></i>
                    </button>
                </div>
            `;
        });
        
        filePreview.innerHTML = html;
    } else {
        uploadPrompt.style.display = 'block';
        filePreview.style.display = 'none';
    }
}

function removeFile(index) {
    if (isUploading) return; // FIX: Prevent file removal during upload
    selectedFiles.splice(index, 1);
    displayFilePreview();
}

function getFileIcon(filename) {
    const ext = filename.split('.').pop().toLowerCase();
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
        'txt': 'bxs-file-txt',
        'zip': 'bxs-file-archive',
        'rar': 'bxs-file-archive',
        'mp4': 'bxs-videos',
        'avi': 'bxs-videos',
        'mp3': 'bxs-music',
        'wav': 'bxs-music'
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

function addTag(tag) {
    if (isUploading) return; // FIX: Prevent tag changes during upload
    if (!selectedTags.includes(tag)) {
        selectedTags.push(tag);
        displaySelectedTags();
        updateTagsInput();
    }
}

function addCustomTag() {
    if (isUploading) return; // FIX: Prevent tag changes during upload
    const input = document.getElementById('customTag');
    const tag = input.value.trim();
    if (tag && !selectedTags.includes(tag)) {
        selectedTags.push(tag);
        displaySelectedTags();
        updateTagsInput();
        input.value = '';
    }
}

function displaySelectedTags() {
    const container = document.getElementById('selectedTags');
    let html = '';
    selectedTags.forEach((tag, index) => {
        html += `
            <div style="background: #10b981; color: white; border-radius: 16px; padding: 6px 12px; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                ${tag}
                <button type="button" onclick="removeTag(${index})" ${isUploading ? 'disabled' : ''} style="background: none; border: none; color: white; cursor: ${isUploading ? 'not-allowed' : 'pointer'}; font-size: 14px; padding: 0; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; opacity: ${isUploading ? '0.5' : '1'};">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        `;
    });
    container.innerHTML = html;
}

function removeTag(index) {
    if (isUploading) return; // FIX: Prevent tag removal during upload
    selectedTags.splice(index, 1);
    displaySelectedTags();
    updateTagsInput();
}

function updateTagsInput() {
    const tagsInput = document.getElementById('tagsInput');
    if (tagsInput) {
        tagsInput.value = JSON.stringify(selectedTags);
    }
}

function resetForm() {
    if (isUploading) return; // FIX: Prevent form reset during upload
    
    selectedFiles = [];
    selectedTags = [];
    isUploading = false; // FIX: Reset upload state
    
    const form = document.getElementById('uploadForm');
    if (form) form.reset();

    const categorySelection = document.getElementById('uploadCategorySelection');
    const categorySelect = document.querySelector('#uploadForm select[name="category"]');
    const folderContext = document.getElementById('customFolderUploadContext');
    if (categorySelection) categorySelection.hidden = false;
    if (categorySelect) categorySelect.required = true;
    if (folderContext) folderContext.hidden = true;
    
    const uploadPrompt = document.getElementById('uploadPrompt');
    const filePreview = document.getElementById('filePreview');
    const selectedTagsContainer = document.getElementById('selectedTags');
    const uploadProgress = document.getElementById('uploadProgress');
    
    if (uploadPrompt) uploadPrompt.style.display = 'block';
    if (filePreview) filePreview.style.display = 'none';
    if (selectedTagsContainer) selectedTagsContainer.innerHTML = '';
    if (uploadProgress) uploadProgress.style.display = 'none';
    updateTagsInput();
}

function simulateUpload() {
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    let progress = 0;
    
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 100) progress = 100;
        
        if (progressBar) progressBar.style.width = progress + '%';
        if (progressPercent) progressPercent.textContent = Math.round(progress) + '%';
        
        if (progress >= 100) {
            clearInterval(interval);
        }
    }, 200);
}
// Modal event listeners
function initializeModalEvents() {
    // Close modal when clicking outside
    const uploadModal = document.getElementById('uploadModal');
    if (uploadModal) {
        uploadModal.addEventListener('click', function(e) {
            if (e.target === this && !isUploading) { // FIX: Prevent closing during upload
                closeUploadModal();
            }
        });
    }

    // Handle keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !isUploading) { // FIX: Prevent closing during upload
            closeUploadModal();
        }
    });

    // File input change handler
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (!isUploading) { // FIX: Prevent file selection during upload
                handleFileSelect(this.files);
            }
        });
    }

    // Drop zone events
    const dropZone = document.getElementById('dropZone');
    if (dropZone) {
        dropZone.addEventListener('click', function() {
            if (!isUploading) { // FIX: Prevent file selection during upload
                const fileInput = document.getElementById('fileInput');
                if (fileInput) fileInput.click();
            }
        });

        dropZone.addEventListener('dragover', function(event) {
            if (!isUploading) { // FIX: Prevent drag operations during upload
                event.preventDefault();
                this.style.borderColor = '#059669';
                this.style.background = '#d1fae5';
            }
        });

        dropZone.addEventListener('dragleave', function() {
            if (!isUploading) {
                this.style.borderColor = '#10b981';
                this.style.background = 'linear-gradient(135deg, #f0fdf4, #ecfdf5)';
            }
        });

        dropZone.addEventListener('drop', handleDrop);
    }

    // Custom tag input
    const customTagInput = document.getElementById('customTag');
    if (customTagInput) {
        customTagInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter' && !isUploading) { // FIX: Prevent tag addition during upload
                event.preventDefault();
                addCustomTag();
            }
        });
    }
}