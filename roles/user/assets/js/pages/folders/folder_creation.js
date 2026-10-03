// Enhanced Folder Creation and Management JavaScript

// Global variables
let isCreatingFolder = false;
let currentFolderView = {};
let selectedFolders = new Set();

// Initialize folder management system
document.addEventListener('DOMContentLoaded', function() {
    initializeFolderSystem();
});

function initializeFolderSystem() {
    initializeCreateFolderForm();
    initializeFolderContextMenu();
    initializeFolderDragDrop();
    
    // Load custom folders for user's department
    if (typeof userDepartmentId !== 'undefined' && userDepartmentId) {
        setTimeout(() => {
            loadCustomFolders(userDepartmentId);
        }, 1000);
    }
}

function initializeFolderContextMenu() {
    const closeContextMenu = () => {
        document.querySelectorAll('.folder-context-menu').forEach(menu => menu.remove());
    };

    document.addEventListener('click', closeContextMenu);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeContextMenu();
    });
    window.addEventListener('scroll', closeContextMenu, true);
}

// Modal Management
function openCreateFolderModal(parentId = null) {
    const modal = document.getElementById('createFolderModal');
    const container = modal.querySelector('.modal-container');
    
    // Set parent folder if specified
    if (parentId) {
        const parentInput = document.createElement('input');
        parentInput.type = 'hidden';
        parentInput.name = 'parent_id';
        parentInput.value = parentId;
        
        const form = document.getElementById('createFolderForm');
        // Remove existing parent_id input if any
        const existingParent = form.querySelector('input[name="parent_id"]');
        if (existingParent) {
            existingParent.remove();
        }
        form.appendChild(parentInput);
        
        // Update modal title to show parent folder
        const modalTitle = modal.querySelector('.modal-header h2');
        modalTitle.innerHTML = `<i class='bx bxs-folder-plus'></i> Create Subfolder`;
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Focus on folder name input
    setTimeout(() => {
        const nameInput = modal.querySelector('input[name="folder_name"]');
        if (nameInput) nameInput.focus();
    }, 100);
}

function closeCreateFolderModal() {
    if (isCreatingFolder) return;
    
    const modal = document.getElementById('createFolderModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
    
    setTimeout(() => {
        resetCreateFolderForm();
    }, 400);
}

function resetCreateFolderForm() {
    if (isCreatingFolder) return;
    
    const form = document.getElementById('createFolderForm');
    if (form) {
        form.reset();
        
        // Remove parent_id input
        const parentInput = form.querySelector('input[name="parent_id"]');
        if (parentInput) {
            parentInput.remove();
        }
        
        // Reset modal title
        const modal = document.getElementById('createFolderModal');
        const modalTitle = modal.querySelector('.modal-header h2');
        modalTitle.innerHTML = `<i class='bx bxs-folder-plus'></i> Create New Folder`;
        
        // Reset to defaults
        const defaultColor = document.getElementById('color-green');
        const defaultIcon = document.getElementById('icon-folder');
        
        if (defaultColor) defaultColor.checked = true;
        if (defaultIcon) defaultIcon.checked = true;
    }
}

// Form Initialization
function initializeCreateFolderForm() {
    const createFolderForm = document.getElementById('createFolderForm');
    if (createFolderForm) {
        createFolderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!isCreatingFolder) {
                handleFolderCreation();
            }
            return false;
        });
    }
    
    initializeCreateFolderModalEvents();
}

// Enhanced Folder Creation
async function handleFolderCreation() {
    const folderName = document.querySelector('input[name="folder_name"]')?.value?.trim();
    const folderDescription = document.querySelector('textarea[name="folder_description"]')?.value?.trim();
    const folderColor = document.querySelector('input[name="folder_color"]:checked')?.value;
    const folderIcon = document.querySelector('input[name="folder_icon"]:checked')?.value;
    const parentId = document.querySelector('input[name="parent_id"]')?.value;
    const isPublic = document.querySelector('input[name="is_public"]')?.checked || false;
    
    // Validation
    if (!folderName) {
        showNotification('Please enter a folder name.', 'error');
        return;
    }
    
    if (folderName.length > 100) {
        showNotification('Folder name is too long. Please use 100 characters or less.', 'error');
        return;
    }
    
    if (/[\u0000-\u001f\u007f]/.test(folderName)) {
        showNotification('Folder names cannot contain control characters.', 'error');
        return;
    }
    
    // Set creating state
    isCreatingFolder = true;
    disableCreateFolderForm(true);
    
    try {
        const formData = new FormData();
        formData.append('folder_name', folderName);
        formData.append('folder_description', folderDescription || '');
        formData.append('folder_color', folderColor || '#10b981');
        formData.append('folder_icon', folderIcon || 'bxs-folder');
        formData.append('department_id', userDepartmentId);
        formData.append('is_public', isPublic ? '1' : '0');
        
        if (parentId) {
            formData.append('parent_id', parentId);
        }
        
        const response = await fetch('handlers/create_folder.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Folder created successfully!', 'success');
            closeCreateFolderModal();
            
            // Refresh the folders view
            await loadCustomFolders(userDepartmentId, parentId);
            
            // Update folder counts in UI
            updateFolderCounts();
            
        } else {
            throw new Error(data.message || 'Failed to create folder');
        }
        
    } catch (error) {
        console.error('Folder creation error:', error);
        showNotification('Failed to create folder: ' + error.message, 'error');
    } finally {
        isCreatingFolder = false;
        disableCreateFolderForm(false);
    }
}

// Enhanced Custom Folders Loading
async function loadCustomFolders(departmentId, parentId = null) {
    try {
        const response = await fetch('handlers/custom_folders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                department_id: departmentId,
                user_department_id: userDepartmentId,
                parent_id: parentId,
                include_subfolders: false
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            renderCustomFolders(departmentId, data.folders || []);
            updateFolderStats(data);
        } else {
            console.error('Error loading custom folders:', data.message);
            showFoldersError(departmentId, data.message);
        }
        
    } catch (error) {
        console.error('Error loading custom folders:', error);
        showFoldersError(departmentId, 'Failed to load folders');
    }
}

// Enhanced Folder Rendering
function renderCustomFolders(departmentId, folders) {
    const container = document.getElementById(`categories-${departmentId}`);
    
    if (!container) return;

    container.querySelectorAll(':scope > .custom-folder-drawer').forEach(drawer => drawer.remove());
    let html = '';
    
    folders.forEach(folder => {
        const createdDate = new Date(folder.created_at).toLocaleDateString();
        const folderId = Number(folder.id);
        const fileCount = Number(folder.file_count) || 0;
        const subfolderCount = Number(folder.subfolder_count) || 0;
        const firstSemesterCount = Number(folder.first_semester_count) || 0;
        const secondSemesterCount = Number(folder.second_semester_count) || 0;
        
        html += `
            <article class="category-card custom-folder-drawer"
                     data-custom-folder-id="${folderId}"
                     data-folder-name="${escapeHtml(folder.folder_name)}">
                <button type="button" class="category-header custom-folder-trigger"
                        aria-expanded="false"
                        aria-controls="custom-folder-content-${folderId}"
                        onclick="toggleCustomFolderDrawer(${folderId})">
                    <div class="category-icon" style="background: linear-gradient(135deg, ${folder.folder_color}, ${adjustBrightness(folder.folder_color, -20)});">
                        <i class='bx ${folder.folder_icon}'></i>
                    </div>
                    <div class="category-info">
                        <span class="category-name">${escapeHtml(folder.folder_name)}</span>
                        <span class="custom-folder-summary">${fileCount} ${fileCount === 1 ? 'file' : 'files'} · ${subfolderCount} ${subfolderCount === 1 ? 'subfolder' : 'subfolders'}</span>
                    </div>
                    <i class="bx bx-chevron-right expand-icon"></i>
                </button>
                <div class="category-semester-content custom-folder-drawer-content" id="custom-folder-content-${folderId}">
                    <div class="custom-folder-semester-tabs" role="tablist" aria-label="Folder semesters">
                        <button type="button" class="custom-folder-semester-tab active" role="tab"
                                aria-selected="true"
                                onclick="showCustomFolderSemester(${folderId}, 'first', event)">
                            <i class="bx bxs-folder"></i> First Semester <span>${firstSemesterCount}</span>
                        </button>
                        <button type="button" class="custom-folder-semester-tab" role="tab"
                                aria-selected="false"
                                onclick="showCustomFolderSemester(${folderId}, 'second', event)">
                            <i class="bx bxs-folder"></i> Second Semester <span>${secondSemesterCount}</span>
                        </button>
                    </div>
                    <div class="custom-folder-semester-panel active" id="custom-folder-semester-${folderId}-first"
                         data-folder-id="${folderId}" data-semester="first"></div>
                    <div class="custom-folder-semester-panel" id="custom-folder-semester-${folderId}-second"
                         data-folder-id="${folderId}" data-semester="second" hidden></div>
                    <div class="custom-folder-details">
                        <span><i class="bx bx-file"></i> ${fileCount} ${fileCount === 1 ? 'file' : 'files'} total</span>
                        <span><i class="bx bx-time"></i> Created ${escapeHtml(createdDate)}</span>
                                        <button type="button" class="custom-folder-delete" onclick="deleteCustomFolder(event, ${folderId})">
                                            <i class="bx bx-trash"></i> Delete folder
                                        </button>
                    </div>
                </div>
            </article>
        `;
    });

    if (html) {
        container.insertAdjacentHTML('afterbegin', html);
    }
}

function toggleCustomFolderDrawer(folderId) {
    const drawer = document.querySelector(`[data-custom-folder-id="${folderId}"]`);
    const trigger = drawer?.querySelector('.custom-folder-trigger');
    const content = drawer?.querySelector('.custom-folder-drawer-content');
    const icon = trigger?.querySelector('.expand-icon');

    if (!drawer || !trigger || !content || !icon) return;

    const isExpanded = content.classList.toggle('show');
    drawer.classList.toggle('active', isExpanded);
    trigger.setAttribute('aria-expanded', String(isExpanded));
    icon.classList.toggle('rotated', isExpanded);

    if (isExpanded) {
        loadCustomFolderSemester(folderId, 'first');
    }
}

function showCustomFolderSemester(folderId, semester, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    if (semester !== 'first' && semester !== 'second') return;

    const drawer = document.querySelector(`[data-custom-folder-id="${folderId}"]`);
    if (!drawer) return;

    drawer.querySelectorAll('.custom-folder-semester-tab').forEach(tab => {
        const selected = tab.textContent.toLowerCase().includes(semester);
        tab.classList.toggle('active', selected);
        tab.setAttribute('aria-selected', String(selected));
    });
    drawer.querySelectorAll('.custom-folder-semester-panel').forEach(panel => {
        const selected = panel.dataset.semester === semester;
        panel.hidden = !selected;
        panel.classList.toggle('active', selected);
    });

    loadCustomFolderSemester(folderId, semester);
}

async function loadCustomFolderSemester(folderId, semester, forceReload = false) {
    if (semester !== 'first' && semester !== 'second') return;

    const panel = document.getElementById(`custom-folder-semester-${folderId}-${semester}`);
    if (!panel || panel.dataset.loading === 'true') return;
    if (panel.dataset.loaded === 'true' && !forceReload) return;

    panel.dataset.loading = 'true';
    panel.innerHTML = '<div class="custom-folder-panel-state">Loading files...</div>';

    try {
        const response = await fetch('handlers/custom_folder_contents.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ folder_id: folderId, semester })
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || `Could not load files (HTTP ${response.status}).`);
        }

        const semesterName = semester === 'first' ? 'First Semester' : 'Second Semester';
        const filesMarkup = data.files.length
            ? `<div class="custom-folder-files">${data.files.map(file => `
                    <div class="custom-folder-file">
                        <span class="custom-folder-file-icon"><i class='bx ${getFileIcon(file.original_name)}'></i></span>
                        <span class="custom-folder-file-info">
                            <strong title="${escapeHtml(file.original_name)}">${escapeHtml(file.original_name)}</strong>
                            <small>${formatFileSize(Number(file.file_size) || 0)} · ${escapeHtml(file.uploaded_at || '')}</small>
                        </span>
                        <a href="handlers/download_file.php?id=${encodeURIComponent(file.id)}" aria-label="Download ${escapeHtml(file.original_name)}">
                            <i class="bx bx-download"></i>
                        </a>
                    </div>
                `).join('')}</div>`
            : `<div class="empty-state custom-folder-empty">
                    <i class='bx bx-folder-open'></i>
                    <p>No files in ${semesterName}</p>
                    <small>Files uploaded to this folder and semester will appear here</small>
                </div>`;

        panel.innerHTML = `
            <div class="custom-folder-semester-toolbar">
                <span>${semesterName}</span>
                <button type="button" onclick="openCustomFolderUpload(${Number(folderId)}, '${semester}')">
                    <i class="bx bx-upload"></i> Upload file
                </button>
            </div>
            ${filesMarkup}
        `;
        panel.dataset.loaded = 'true';
        panel.dataset.loading = 'false';
    } catch (error) {
        console.error('Custom folder contents error:', error);
        panel.innerHTML = `
            <div class="custom-folder-panel-error">
                <span>${escapeHtml(error.message || 'Unable to load folder files.')}</span>
                <button type="button" onclick="loadCustomFolderSemester(${Number(folderId)}, '${semester}', true)">Retry</button>
            </div>
        `;
        panel.dataset.loading = 'false';
    }
}

function openCustomFolderUpload(folderId, semester) {
    const drawer = document.querySelector(`[data-custom-folder-id="${folderId}"]`);
    if (!drawer || typeof openUploadModal !== 'function') return;
    openUploadModal(folderId, semester, drawer.dataset.folderName || '');
}

async function deleteCustomFolder(event, folderId) {
    event.preventDefault();
    event.stopPropagation();

    const drawer = document.querySelector(`[data-custom-folder-id="${folderId}"]`);
    const folderName = drawer?.dataset.folderName || 'this folder';
    if (!window.confirm(`Delete "${folderName}" and move its files and semester folders to the deleted state? This folder will no longer be available in your department.`)) {
        return;
    }

    try {
        await sendFolderDeleteRequest({ type: 'custom', folder_id: folderId });
        await loadCustomFolders(userDepartmentId);
    } catch (error) {
        console.error('Custom folder delete error:', error);
        showNotification(error.message || 'Folder could not be deleted.', 'error');
    }
}

async function deleteDocumentCategory(event, category) {
    event.preventDefault();
    event.stopPropagation();

    const card = event.currentTarget.closest('.category-card');
    const categoryName = card?.querySelector('.category-name')?.textContent?.trim() || 'this department folder';
    if (!window.confirm(`Delete "${categoryName}" for the entire department? All files in this category will be moved to the deleted state, and this folder will be removed from the Documents list.`)) {
        return;
    }

    try {
        await sendFolderDeleteRequest({ type: 'category', category });
        card?.remove();
        if (window.fileCategories) {
            delete window.fileCategories[category];
        }
    } catch (error) {
        console.error('Department category delete error:', error);
        showNotification(error.message || 'Department folder could not be deleted.', 'error');
    }
}

async function sendFolderDeleteRequest(payload) {
    const response = await fetch('handlers/delete_custom_folder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
        throw new Error(data.message || `Folder could not be deleted (HTTP ${response.status}).`);
    }
    showNotification(data.message || 'Folder deleted.', 'success');
}

// Folder Operations
async function openCustomFolder(folderId, folderName) {
    try {
        // Log folder access
        await fetch('handlers/folder_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=access&folder_id=${folderId}`
        });
        
        // Show folder contents (you can implement a modal or redirect)
        showFolderContents(folderId, folderName);
        
    } catch (error) {
        console.error('Error opening folder:', error);
        showNotification('Failed to open folder', 'error');
    }
}

function showFolderContents(folderId, folderName) {
    // This can be implemented as a modal or navigation to a new page
    showNotification(`Opening folder: ${folderName}`, 'info');
    
    // Example implementation - you can enhance this
    console.log(`Loading contents for folder ID: ${folderId}`);
    
    // You could implement:
    // 1. A modal showing folder contents
    // 2. Navigation to a dedicated folder view page
    // 3. Expand the folder inline to show subfolders and files
}

// Context Menu for Folders
function showFolderContextMenu(event, folderId) {
    event.preventDefault();
    event.stopPropagation();
    
    const existingMenu = document.querySelector('.folder-context-menu');
    if (existingMenu) existingMenu.remove();
    
    const contextMenu = document.createElement('div');
    contextMenu.className = 'folder-context-menu';
    contextMenu.style.cssText = `
        position: fixed;
        top: ${event.clientY}px;
        left: ${event.clientX}px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        padding: 8px 0;
        min-width: 180px;
        font-family: 'Poppins', sans-serif;
    `;
    
    const menuItems = [
        { icon: 'bx-folder-open', text: 'Open', action: () => openCustomFolder(folderId, 'Folder') },
        { icon: 'bx-info-circle', text: 'Properties', action: () => showFolderProperties(folderId) },
        { separator: true },
        { icon: 'bx-edit', text: 'Rename', action: () => renameFolderDialog(folderId) },
        { icon: 'bx-star', text: 'Add to Favorites', action: () => toggleFolderFavorite(folderId) },
        { icon: 'bx-share', text: 'Share', action: () => shareFolderDialog(folderId) },
        { separator: true },
        { icon: 'bx-trash', text: 'Delete', action: () => deleteFolderDialog(folderId), danger: true }
    ];
    
    menuItems.forEach(item => {
        if (item.separator) {
            const separator = document.createElement('div');
            separator.style.cssText = 'height: 1px; background: #e5e7eb; margin: 4px 0;';
            contextMenu.appendChild(separator);
        } else {
            const menuItem = document.createElement('div');
            menuItem.className = 'context-menu-item';
            menuItem.style.cssText = `
                padding: 8px 16px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 14px;
                color: ${item.danger ? '#ef4444' : '#374151'};
                transition: background-color 0.2s;
            `;
            
            menuItem.innerHTML = `<i class='bx ${item.icon}'></i> ${item.text}`;
            
            menuItem.addEventListener('mouseenter', () => {
                menuItem.style.backgroundColor = item.danger ? '#fef2f2' : '#f9fafb';
            });
            
            menuItem.addEventListener('mouseleave', () => {
                menuItem.style.backgroundColor = 'transparent';
            });
            
            menuItem.addEventListener('click', () => {
                item.action();
                contextMenu.remove();
            });
            
            contextMenu.appendChild(menuItem);
        }
    });
    
    document.body.appendChild(contextMenu);
    
    // Remove menu when clicking elsewhere
    setTimeout(() => {
        document.addEventListener('click', function removeMenu(e) {
            if (!contextMenu.contains(e.target)) {
                contextMenu.remove();
                document.removeEventListener('click', removeMenu);
            }
        });
    }, 10);
}

// Folder Management Functions
async function toggleFolderFavorite(folderId) {
    try {
        const response = await fetch('handlers/folder_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=toggle_favorite&folder_id=${folderId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            // Update UI
            const folderElement = document.querySelector(`[data-folder-id="${folderId}"]`);
            if (folderElement) {
                if (data.is_favorite) {
                    folderElement.classList.add('is-favorite');
                } else {
                    folderElement.classList.remove('is-favorite');
                }
            }
        } else {
            showNotification(data.message || 'Failed to update favorite status', 'error');
        }
    } catch (error) {
        console.error('Error toggling favorite:', error);
        showNotification('Failed to update favorite status', 'error');
    }
}

function renameFolderDialog(folderId) {
    const folderElement = document.querySelector(`[data-folder-id="${folderId}"]`);
    const currentName = folderElement?.querySelector('.folder-name')?.textContent || 'Folder';
    
    const newName = prompt('Enter new folder name:', currentName);
    
    if (newName && newName.trim() && newName.trim() !== currentName) {
        renameFolder(folderId, newName.trim());
    }
}

async function renameFolder(folderId, newName) {
    try {
        const response = await fetch('handlers/folder_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=rename&folder_id=${folderId}&new_name=${encodeURIComponent(newName)}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Folder renamed successfully', 'success');
            // Update UI
            const folderElement = document.querySelector(`[data-folder-id="${folderId}"]`);
            const nameElement = folderElement?.querySelector('.folder-name');
            if (nameElement) {
                nameElement.textContent = newName;
                nameElement.title = newName;
            }
        } else {
            showNotification(data.message || 'Failed to rename folder', 'error');
        }
    } catch (error) {
        console.error('Error renaming folder:', error);
        showNotification('Failed to rename folder', 'error');
    }
}

function deleteFolderDialog(folderId) {
    if (confirm('Are you sure you want to delete this folder? This action cannot be undone.')) {
        deleteFolder(folderId);
    }
}

async function deleteFolder(folderId, forceDelete = false) {
    try {
        const response = await fetch('handlers/folder_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&folder_id=${folderId}&force_delete=${forceDelete ? '1' : '0'}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Folder deleted successfully', 'success');
            // Remove from UI
            const folderElement = document.querySelector(`[data-folder-id="${folderId}"]`);
            if (folderElement) {
                folderElement.remove();
            }
            updateFolderCounts();
        } else if (data.requires_confirmation) {
            const message = `This folder contains ${data.file_count} files and ${data.subfolder_count} subfolders. Are you sure you want to delete everything?`;
            if (confirm(message)) {
                deleteFolder(folderId, true);
            }
        } else {
            showNotification(data.message || 'Failed to delete folder', 'error');
        }
    } catch (error) {
        console.error('Error deleting folder:', error);
        showNotification('Failed to delete folder', 'error');
    }
}

async function showFolderProperties(folderId) {
    try {
        const response = await fetch('handlers/folder_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_info&folder_id=${folderId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showFolderPropertiesModal(data.folder, data.stats, data.recent_files);
        } else {
            showNotification('Failed to load folder information', 'error');
        }
    } catch (error) {
        console.error('Error loading folder properties:', error);
        showNotification('Failed to load folder information', 'error');
    }
}

// UI Helper Functions
function disableCreateFolderForm(disabled) {
    const formElements = document.querySelectorAll('#createFolderForm input, #createFolderForm textarea, #createFolderForm button');
    formElements.forEach(element => {
        element.disabled = disabled;
        element.style.opacity = disabled ? '0.6' : '1';
        element.style.cursor = disabled ? 'not-allowed' : '';
    });
    
    const createBtn = document.querySelector('.btn-create');
    if (createBtn) {
        if (disabled) {
            createBtn.classList.add('loading');
            createBtn.innerHTML = '<div class="loading-spinner"></div> Creating...';
        } else {
            createBtn.classList.remove('loading');
            createBtn.innerHTML = '<i class="bx bxs-folder-plus"></i> Create Folder';
        }
    }
}

function initializeCreateFolderModalEvents() {
    const modal = document.getElementById('createFolderModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this && !isCreatingFolder) {
                closeCreateFolderModal();
            }
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !isCreatingFolder) {
            const modal = document.getElementById('createFolderModal');
            if (modal && modal.classList.contains('show')) {
                closeCreateFolderModal();
            }
        }
    });
    
    // Enhanced folder name input validation
    const folderNameInput = document.querySelector('input[name="folder_name"]');
    if (folderNameInput) {
        folderNameInput.addEventListener('input', function() {
            const value = this.value;
            const maxLength = 100;
            
            if (value.length > maxLength) {
                this.value = value.substring(0, maxLength);
            }
            
            // Show character count
            const remaining = maxLength - this.value.length;
            let small = this.parentElement.querySelector('.char-count');
            if (!small) {
                small = document.createElement('small');
                small.className = 'char-count';
                this.parentElement.appendChild(small);
            }
            
            small.textContent = `${remaining} characters remaining`;
            small.style.color = remaining < 20 ? '#ef4444' : '#6b7280';
            
            // Validate characters
            const invalidChars = value.match(/[^a-zA-Z0-9\s\-_().]/g);
            if (invalidChars) {
                this.setCustomValidity('Invalid characters: ' + invalidChars.join(', '));
                this.style.borderColor = '#ef4444';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '';
            }
        });
    }
}

function initializeFolderInteractions() {
    // Add loading spinner styles if not exists
    if (!document.querySelector('#folder-styles')) {
        const style = document.createElement('style');
        style.id = 'folder-styles';
        style.textContent = `
            .loading-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid #ffffff;
                border-radius: 50%;
                border-top-color: transparent;
                animation: spin 1s linear infinite;
            }
            
            .loading-state {
                text-align: center;
                padding: 40px 20px;
                color: #6b7280;
            }
            
            .folders-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 16px;
                margin-top: 16px;
            }
            
            .custom-folder-item {
                background: white;
                border-radius: 12px;
                padding: 20px;
                border: 2px solid #f1f5f9;
                transition: all 0.3s ease;
                cursor: pointer;
                position: relative;
                overflow: hidden;
            }
            
            .custom-folder-item:hover {
                border-color: #e2e8f0;
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            }
            
            .custom-folder-item.is-favorite::before {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                width: 0;
                height: 0;
                border-left: 20px solid transparent;
                border-top: 20px solid #fbbf24;
            }
            
            .folder-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 16px;
            }
            
            .folder-icon {
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
            
            .folder-actions {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .favorite-indicator {
                color: #fbbf24;
                font-size: 16px;
            }
            
            .folder-menu {
                padding: 4px;
                border-radius: 6px;
                cursor: pointer;
                transition: background-color 0.2s;
            }
            
            .folder-menu:hover {
                background-color: #f3f4f6;
            }
            
            .folder-name {
                margin: 0 0 8px 0;
                font-size: 16px;
                font-weight: 600;
                color: #1f2937;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .folder-description {
                margin: 0 0 16px 0;
                font-size: 14px;
                color: #6b7280;
                line-height: 1.4;
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }
            
            .folder-description.no-description {
                font-style: italic;
                opacity: 0.7;
            }
            
            .folder-stats {
                display: flex;
                gap: 16px;
                margin-bottom: 16px;
            }
            
            .stat-item {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 13px;
                color: #64748b;
            }
            
            .stat-item i {
                font-size: 16px;
            }
            
            .folder-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 12px;
                color: #9ca3af;
                margin-bottom: 12px;
            }
            
            .folder-permissions {
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 12px;
            }
            
            .permission-icon {
                font-size: 14px;
                opacity: 0.7;
            }
            
            .permission-level {
                background: #f3f4f6;
                padding: 2px 8px;
                border-radius: 12px;
                font-weight: 500;
                text-transform: capitalize;
            }
            
            .recent-files-section {
                margin-top: 24px;
                padding-top: 16px;
                border-top: 1px solid #f1f5f9;
            }
            
            .recent-files-section h5 {
                margin: 0 0 12px 0;
                font-size: 14px;
                font-weight: 600;
                color: #374151;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .recent-file-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 8px 12px;
                border-radius: 8px;
                transition: background-color 0.2s;
                cursor: pointer;
            }
            
            .recent-file-item:hover {
                background-color: #f9fafb;
            }
            
            .recent-file-item i {
                font-size: 20px;
                color: #64748b;
            }
            
            .file-info {
                flex: 1;
                min-width: 0;
            }
            
            .file-name {
                font-size: 13px;
                font-weight: 500;
                color: #374151;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .file-meta {
                font-size: 11px;
                color: #9ca3af;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
}

// Initialize drag and drop (placeholder for future implementation)
function initializeFolderDragDrop() {
    // TODO: Implement drag and drop functionality for folders and files
}

// Additional utility functions
function updateFolderCounts() {
    // Update folder counts in the main navigation if needed
    // This function can be enhanced to update various UI counters
}

function updateFolderStats(data) {
    // Update department statistics if available
    if (data.user_permissions) {
        // Update UI based on user permissions
    }
}

function showFoldersError(departmentId, message) {
    const container = document.getElementById(`categories-${departmentId}`);
    if (container) {
        container.querySelectorAll(':scope > .custom-folder-drawer').forEach(drawer => drawer.remove());
        container.insertAdjacentHTML('afterbegin', `
            <div class="custom-folder-load-error">
                <span>${escapeHtml(message)}</span>
                <button type="button" onclick="loadCustomFolders(${Number(departmentId)})">Retry</button>
            </div>
        `);
    }
}

// File icon helper
function getFileIcon(filename) {
    const ext = filename.split('.').pop()?.toLowerCase();
    const iconMap = {
        'pdf': 'bxs-file-pdf',
        'doc': 'bxs-file-doc', 'docx': 'bxs-file-doc',
        'xls': 'bxs-spreadsheet', 'xlsx': 'bxs-spreadsheet',
        'ppt': 'bxs-slideshow', 'pptx': 'bxs-slideshow',
        'jpg': 'bxs-image', 'jpeg': 'bxs-image', 'png': 'bxs-image', 'gif': 'bxs-image',
        'txt': 'bxs-file-txt',
        'zip': 'bxs-archive', 'rar': 'bxs-archive',
        'mp4': 'bxs-videos', 'avi': 'bxs-videos',
        'mp3': 'bxs-music', 'wav': 'bxs-music'
    };
    return iconMap[ext] || 'bxs-file';
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    
    return `${Math.round(size * 10) / 10} ${units[unitIndex]}`;
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

function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function adjustBrightness(hex, percent) {
    hex = hex.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    
    const newR = Math.max(0, Math.min(255, r + (r * percent / 100)));
    const newG = Math.max(0, Math.min(255, g + (g * percent / 100)));
    const newB = Math.max(0, Math.min(255, b + (b * percent / 100)));
    
    const toHex = (n) => Math.round(n).toString(16).padStart(2, '0');
    return `#${toHex(newR)}${toHex(newG)}${toHex(newB)}`;
}

// Export functions for global access
window.openCreateFolderModal = openCreateFolderModal;
window.closeCreateFolderModal = closeCreateFolderModal;
window.loadCustomFolders = loadCustomFolders;
window.openCustomFolder = openCustomFolder;
window.showNotification = showNotification;
window.toggleFolderFavorite = toggleFolderFavorite;
window.showFolderContextMenu = showFolderContextMenu;