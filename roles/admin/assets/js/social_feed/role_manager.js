// role_manager.js - Handles role-based functionality and permissions

class RoleManager {
    constructor() {
        this.userRole = window.userRole || 'user';
        this.userId = window.userId || 0;
        this.capabilities = window.capabilities || {};
        this.config = window.roleConfig || {};
        this.initialized = false;
    }

    // Initialize role-based functionality
    init() {
        if (this.initialized) return;

        this.setupRoleBasedUI();
        this.setupRoleBasedListeners();
        this.updateComposerLimits();
        this.setupModerationFeatures();
        this.initialized = true;

        console.log(`Role Manager initialized for ${this.userRole}`);
    }

    // Setup role-based UI elements
    setupRoleBasedUI() {
        // Update composer placeholder based on role
        const textarea = document.getElementById('postContent');
        if (textarea) {
            const placeholders = {
                'super_admin': "What's happening across the organization?",
                'admin': "Share updates with your team or organization...",
                'user': "What's happening in your department?"
            };
            textarea.placeholder = placeholders[this.userRole] || placeholders['user'];
        }

        // Show/hide role-specific elements
        this.toggleRoleBasedElements();
        
        // Update visibility options based on role
        this.updateVisibilityOptions();
        
        // Setup announcement toggle if available
        this.setupAnnouncementToggle();
        
        // Setup pin toggle if available
        this.setupPinToggle();
    }

    // Toggle elements based on role
    toggleRoleBasedElements() {
        // Hide elements that users shouldn't see
        if (this.userRole === 'user') {
            // Hide admin-only tabs
            const reportedTab = document.querySelector('[data-filter="reported"]');
            if (reportedTab) reportedTab.style.display = 'none';
            
            // Hide analytics button
            const analyticsBtn = document.querySelector('.btn-analytics');
            if (analyticsBtn) analyticsBtn.style.display = 'none';
        }

        // Show moderation tools for admins
        if (['admin', 'super_admin'].includes(this.userRole)) {
            this.showModerationTools();
        }
    }

    // Update visibility options based on role
    updateVisibilityOptions() {
        const dropdown = document.getElementById('visibilityDropdown');
        if (!dropdown) return;

        // Remove admin-only option for regular users
        if (this.userRole === 'user') {
            const adminOption = dropdown.querySelector('[data-visibility="admin"]');
            if (adminOption) adminOption.remove();
        }
    }

    // Setup announcement toggle
    setupAnnouncementToggle() {
        const checkbox = document.getElementById('isAnnouncement');
        const hiddenInput = document.getElementById('postType');

        if (checkbox && hiddenInput) {
            checkbox.addEventListener('change', () => {
                hiddenInput.value = checkbox.checked ? 'announcement' : 'normal';
                
                // Visual feedback
                const composer = document.getElementById('postComposer');
                if (composer) {
                    composer.classList.toggle('announcement-mode', checkbox.checked);
                }
            });
        }
    }

    // Setup pin toggle
    setupPinToggle() {
        const pinToggle = document.getElementById('pinToggle');
        const hiddenInput = document.getElementById('isPinned');

        if (pinToggle && hiddenInput) {
            pinToggle.addEventListener('click', () => {
                const isPinned = hiddenInput.value === 'true';
                hiddenInput.value = isPinned ? 'false' : 'true';
                
                pinToggle.classList.toggle('active', !isPinned);
                pinToggle.title = isPinned ? 'Pin Post' : 'Unpin Post';
            });
        }
    }

    // Setup role-based event listeners
    setupRoleBasedListeners() {
        // Override file upload validation
        this.overrideFileValidation();
        
        // Setup moderation event listeners
        if (this.canModerate()) {
            this.setupModerationListeners();
        }
    }

    // Override file upload validation with role-based limits
    overrideFileValidation() {
        // Store original validation functions
        const originalHandleImageUpload = window.handleImageUpload;
        const originalHandleFileUpload = window.handleFileUpload;

        // Override image upload with role-based validation
        window.handleImageUpload = (input) => {
            if (this.validateImageUpload(input)) {
                originalHandleImageUpload.call(this, input);
            }
        };

        // Override file upload with role-based validation
        window.handleFileUpload = (input) => {
            if (this.validateFileUpload(input)) {
                originalHandleFileUpload.call(this, input);
            }
        };
    }

    // Validate image upload based on role
    validateImageUpload(input) {
        const files = Array.from(input.files);
        const maxImages = this.config.maxImages || 10;
        const maxFileSize = this.config.maxFileSize || (20 * 1024 * 1024);

        // Check number of images
        if (files.length > maxImages) {
            window.utils.showNotification(`Maximum ${maxImages} images allowed for ${this.userRole}`, 'error');
            input.value = '';
            return false;
        }

        // Check file sizes
        for (const file of files) {
            if (file.size > maxFileSize) {
                const maxSizeMB = Math.round(maxFileSize / (1024 * 1024));
                window.utils.showNotification(`File "${file.name}" exceeds ${maxSizeMB}MB limit for ${this.userRole}`, 'error');
                input.value = '';
                return false;
            }
        }

        return true;
    }

    // Validate file upload based on role
    validateFileUpload(input) {
        const files = Array.from(input.files);
        const maxFiles = this.config.maxFiles || 5;
        const maxFileSize = this.config.maxFileSize || (20 * 1024 * 1024);

        // Check number of files
        if (files.length > maxFiles) {
            window.utils.showNotification(`Maximum ${maxFiles} files allowed for ${this.userRole}`, 'error');
            input.value = '';
            return false;
        }

        // Check file sizes
        for (const file of files) {
            if (file.size > maxFileSize) {
                const maxSizeMB = Math.round(maxFileSize / (1024 * 1024));
                window.utils.showNotification(`File "${file.name}" exceeds ${maxSizeMB}MB limit for ${this.userRole}`, 'error');
                input.value = '';
                return false;
            }
        }

        return true;
    }

    // Update composer with role-based limits
    updateComposerLimits() {
        const imageLimit = document.querySelector('.composer-tool[title*="Images"] .tool-limit');
        const fileLimit = document.querySelector('.composer-tool[title*="Files"] .tool-limit');

        if (imageLimit) imageLimit.textContent = this.config.maxImages || 10;
        if (fileLimit) fileLimit.textContent = this.config.maxFiles || 5;

        // Update limits display
        const limitsDiv = document.querySelector('.composer-limits small');
        if (limitsDiv) {
            const maxSizeMB = Math.round((this.config.maxFileSize || (20 * 1024 * 1024)) / (1024 * 1024));
            limitsDiv.textContent = `Max file size: ${maxSizeMB}MB | Images: ${this.config.maxImages || 10} | Files: ${this.config.maxFiles || 5}`;
        }
    }

    // Setup moderation features
    setupModerationFeatures() {
        if (!this.canModerate()) return;

        // Add moderation styles
        this.addModerationStyles();
        
        // Setup quick moderation actions
        this.setupQuickModerationActions();
    }

    // Add moderation-specific CSS
    addModerationStyles() {
        const style = document.createElement('style');
        style.id = 'moderation-styles';
        style.textContent = `
            .post[data-reported="true"] {
                border-left: 4px solid #ff6b6b;
                background: #fff5f5;
            }
            
            .moderation-tools {
                display: flex;
                gap: 8px;
                margin-top: 8px;
                padding: 8px;
                background: #f8f9fa;
                border-radius: 6px;
            }
            
            .mod-action {
                padding: 4px 8px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                transition: all 0.2s;
            }
            
            .mod-action:hover {
                background: #f0f0f0;
            }
            
            .mod-action.danger {
                color: #dc3545;
                border-color: #dc3545;
            }
            
            .mod-action.warning {
                color: #fd7e14;
                border-color: #fd7e14;
            }
            
            .mod-action.success {
                color: #28a745;
                border-color: #28a745;
            }
            
            .announcement-mode {
                border: 2px solid #007bff;
                background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            }
            
            .announcement-mode .composer-header::before {
                content: "📢 Announcement Mode";
                display: block;
                background: #007bff;
                color: white;
                padding: 4px 8px;
                font-size: 12px;
                border-radius: 4px;
                margin-bottom: 8px;
                width: fit-content;
            }
        `;
        document.head.appendChild(style);
    }

    // Setup quick moderation actions
    setupQuickModerationActions() {
        // This will be called when posts are rendered
        document.addEventListener('postRendered', (e) => {
            const post = e.detail.postElement;
            const postData = e.detail.postData;
            
            if (this.canModerate()) {
                this.addModerationToolsToPost(post, postData);
            }
        });
    }

    // Add moderation tools to post
    addModerationToolsToPost(postElement, postData) {
        const existingTools = postElement.querySelector('.moderation-tools');
        if (existingTools) return; // Already added

        const moderationTools = document.createElement('div');
        moderationTools.className = 'moderation-tools';
        
        const tools = [];
        
        // Pin/Unpin
        if (this.canPin()) {
            const pinned = postData.is_pinned;
            tools.push(`
                <button class="mod-action" onclick="togglePin(${postData.id})">
                    <i class='bx bx${pinned ? 's' : ''}-pin'></i> ${pinned ? 'Unpin' : 'Pin'}
                </button>
            `);
        }
        
        // Report resolution (if reported)
        if (postData.is_reported) {
            tools.push(`
                <button class="mod-action success" onclick="resolveReport(${postData.id})">
                    <i class='bx bx-check'></i> Resolve
                </button>
            `);
        }
        
        // Hide/Show
        tools.push(`
            <button class="mod-action warning" onclick="toggleHidePost(${postData.id})">
                <i class='bx bx-hide'></i> ${postData.is_hidden ? 'Show' : 'Hide'}
            </button>
        `);
        
        // Delete (for admins)
        if (this.canDeleteAny() || postData.user_id == this.userId) {
            tools.push(`
                <button class="mod-action danger" onclick="deletePost(${postData.id})">
                    <i class='bx bx-trash'></i> Delete
                </button>
            `);
        }

        moderationTools.innerHTML = tools.join('');
        
        // Add to post (after post actions)
        const postActions = postElement.querySelector('.post-actions');
        if (postActions) {
            postActions.parentNode.insertBefore(moderationTools, postActions.nextSibling);
        }
    }

    // Setup moderation event listeners
    setupModerationListeners() {
        // Global moderation functions
        window.togglePin = (postId) => this.togglePin(postId);
        window.resolveReport = (postId) => this.resolveReport(postId);
        window.toggleHidePost = (postId) => this.toggleHidePost(postId);
    }

    // Show moderation tools
    showModerationTools() {
        const style = document.createElement('style');
        style.textContent = `
            .moderation-panel {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 15px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                z-index: 1000;
                display: none;
            }
            
            .moderation-panel.show {
                display: block;
            }
            
            .mod-toggle {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                font-size: 18px;
                cursor: pointer;
                box-shadow: 0 2px 10px rgba(220,53,69,0.3);
                z-index: 1001;
            }
        `;
        document.head.appendChild(style);

        // Add moderation toggle button
        const modButton = document.createElement('button');
        modButton.className = 'mod-toggle';
        modButton.innerHTML = '<i class="bx bx-shield"></i>';
        modButton.title = 'Moderation Tools';
        modButton.onclick = () => this.toggleModerationPanel();
        document.body.appendChild(modButton);
    }

    // Toggle moderation panel
    toggleModerationPanel() {
        let panel = document.querySelector('.moderation-panel');
        
        if (!panel) {
            panel = document.createElement('div');
            panel.className = 'moderation-panel';
            panel.innerHTML = `
                <h4>Moderation Tools</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <button onclick="window.roleManager.bulkModeration()">Bulk Actions</button>
                    <button onclick="window.roleManager.exportReports()">Export Reports</button>
                </div>
            `;
            document.body.appendChild(panel);
        }
        
        panel.classList.toggle('show');
    }

    // Moderation action methods
    async togglePin(postId) {
        if (!this.canPin()) {
            window.utils.showNotification('You do not have permission to pin posts', 'error');
            return;
        }

        try {
            const response = await fetch('api/toggle_pin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId })
            });

            const result = await response.json();
            
            if (result.success) {
                window.utils.showNotification(result.pinned ? 'Post pinned' : 'Post unpinned', 'success');
                // Refresh the post or update UI
                this.updatePostPinStatus(postId, result.pinned);
            } else {
                window.utils.showNotification(result.message || 'Error toggling pin', 'error');
            }
        } catch (error) {
            console.error('Error toggling pin:', error);
            window.utils.showNotification('Error toggling pin', 'error');
        }
    }

    async resolveReport(postId) {
        if (!this.canModerate()) {
            window.utils.showNotification('You do not have moderation permissions', 'error');
            return;
        }

        if (!confirm('Mark this report as resolved?')) return;

        try {
            const response = await fetch('api/resolve_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId })
            });

            const result = await response.json();
            
            if (result.success) {
                window.utils.showNotification('Report resolved', 'success');
                // Remove the post from reported view or update status
                this.updatePostReportStatus(postId, false);
            } else {
                window.utils.showNotification(result.message || 'Error resolving report', 'error');
            }
        } catch (error) {
            console.error('Error resolving report:', error);
            window.utils.showNotification('Error resolving report', 'error');
        }
    }

    async toggleHidePost(postId) {
        if (!this.canModerate()) {
            window.utils.showNotification('You do not have moderation permissions', 'error');
            return;
        }

        try {
            const response = await fetch('api/toggle_hide_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId })
            });

            const result = await response.json();
            
            if (result.success) {
                window.utils.showNotification(result.hidden ? 'Post hidden' : 'Post shown', 'success');
                this.updatePostVisibility(postId, result.hidden);
            } else {
                window.utils.showNotification(result.message || 'Error toggling post visibility', 'error');
            }
        } catch (error) {
            console.error('Error toggling post visibility:', error);
            window.utils.showNotification('Error toggling post visibility', 'error');
        }
    }

    // UI update methods
    updatePostPinStatus(postId, isPinned) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (postElement) {
            const pinButton = postElement.querySelector('[onclick*="togglePin"]');
            if (pinButton) {
                const icon = pinButton.querySelector('i');
                const text = pinButton.querySelector('span') || pinButton.childNodes[2];
                
                if (icon) icon.className = `bx bx${isPinned ? 's' : ''}-pin`;
                if (text) text.textContent = isPinned ? 'Unpin' : 'Pin';
            }
            
            // Add/remove pinned indicator
            postElement.classList.toggle('pinned', isPinned);
        }
    }

    updatePostReportStatus(postId, isReported) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (postElement) {
            postElement.setAttribute('data-reported', isReported);
            if (!isReported) {
                // Remove from reported view if currently viewing reported posts
                const currentFilter = window.socialFeedCore?.currentFilter;
                if (currentFilter === 'reported') {
                    postElement.remove();
                }
            }
        }
    }

    updatePostVisibility(postId, isHidden) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (postElement) {
            postElement.classList.toggle('hidden-post', isHidden);
            const hideButton = postElement.querySelector('[onclick*="toggleHidePost"]');
            if (hideButton) {
                const text = hideButton.querySelector('span') || hideButton.childNodes[2];
                if (text) text.textContent = isHidden ? 'Show' : 'Hide';
            }
        }
    }

    // Additional moderation methods
    showReportedPosts() {
        // Switch to reported filter
        if (window.socialFeedCore) {
            window.socialFeedCore.switchTab('reported');
        }
        this.toggleModerationPanel(); // Close panel
    }

    showHiddenPosts() {
        // Load hidden posts
        window.utils.showNotification('Loading hidden posts...', 'info');
        // Implementation would depend on your API
    }

    bulkModeration() {
        window.utils.showNotification('Bulk moderation feature coming soon!', 'info');
        // Implementation for bulk actions
    }

    exportReports() {
        window.utils.showNotification('Exporting reports...', 'info');
        // Implementation for exporting reports
    }

    // Permission check methods
    canModerate() {
        return this.config.canModerate === 'true' || ['admin', 'super_admin'].includes(this.userRole);
    }

    canPin() {
        return this.config.canPin === 'true' || ['admin', 'super_admin'].includes(this.userRole);
    }

    canViewAll() {
        return this.config.canViewAll === 'true' || ['admin', 'super_admin'].includes(this.userRole);
    }

    canDeleteAny() {
        return this.capabilities.can_delete_any_post || this.userRole === 'super_admin';
    }

    canEditAny() {
        return this.capabilities.can_edit_any_post || ['admin', 'super_admin'].includes(this.userRole);
    }

    canCreateAnnouncements() {
        return this.config.canAnnounce === 'true' || ['admin', 'super_admin'].includes(this.userRole);
    }

    // Filter posts based on role permissions
    filterPostsForRole(posts) {
        if (this.canViewAll()) {
            return posts; // Admins see everything
        }

        // Regular users see limited posts
        return posts.filter(post => {
            // Always show own posts
            if (post.user_id == this.userId) return true;
            
            // Show public posts
            if (post.visibility === 'public') return true;
            
            // Show department posts if same department
            if (post.visibility === 'department' && post.department_id === window.userDepartmentId) {
                return true;
            }
            
            // Hide admin-only posts from regular users
            if (post.visibility === 'admin') return false;
            
            // Hide hidden posts from regular users
            if (post.is_hidden && !this.canModerate()) return false;
            
            return false;
        });
    }

    // Override post actions based on role
    setupRoleBasedPostActions() {
        // Override the original post actions with role-based versions
        const originalToggleLike = window.toggleLike;
        const originalEditPost = window.editPost;
        const originalDeletePost = window.deletePost;

        // Enhanced like with role-based features
        window.toggleLike = async (postId) => {
            // Add user tracking for admins
            if (this.canModerate()) {
                console.log(`User ${this.userId} (${this.userRole}) liked post ${postId}`);
            }
            return originalToggleLike(postId);
        };

        // Enhanced edit with role-based permissions
        window.editPost = async (postId) => {
            const post = await this.getPostData(postId);
            
            if (!post) {
                window.utils.showNotification('Post not found', 'error');
                return;
            }

            // Check permissions
            if (post.user_id != this.userId && !this.canEditAny()) {
                window.utils.showNotification('You can only edit your own posts', 'error');
                return;
            }

            return originalEditPost(postId);
        };

        // Enhanced delete with role-based permissions
        window.deletePost = async (postId) => {
            const post = await this.getPostData(postId);
            
            if (!post) {
                window.utils.showNotification('Post not found', 'error');
                return;
            }

            // Check permissions
            if (post.user_id != this.userId && !this.canDeleteAny()) {
                window.utils.showNotification('You can only delete your own posts', 'error');
                return;
            }

            // Enhanced confirmation for admins
            if (this.canDeleteAny() && post.user_id != this.userId) {
                const confirmed = confirm(
                    `Are you sure you want to delete this post by ${post.author_full_name}?\n\n` +
                    `This action will be logged for moderation purposes.`
                );
                if (!confirmed) return;
            }

            return originalDeletePost(postId);
        };
    }

    // Get post data for permission checks
    async getPostData(postId) {
        try {
            const response = await fetch(`api/get_post.php?id=${postId}`);
            const result = await response.json();
            return result.success ? result.post : null;
        } catch (error) {
            console.error('Error getting post data:', error);
            return null;
        }
    }

    // Role-based comment system enhancements
    setupRoleBasedComments() {
        if (window.commentSystem) {
            const originalAddComment = window.commentSystem.submitModalComment;
            
            window.commentSystem.submitModalComment = async function() {
                const result = await originalAddComment.call(this);
                
                // Log comment activity for moderation
                if (window.roleManager.canModerate() && result) {
                    console.log(`Comment added by ${window.roleManager.userRole} user ${window.roleManager.userId}`);
                }
                
                return result;
            };
        }
    }

    // Initialize role-based features after other systems load
    initializeAfterSystems() {
        this.setupRoleBasedPostActions();
        this.setupRoleBasedComments();
        
        // Custom event for when role manager is fully ready
        document.dispatchEvent(new CustomEvent('roleManagerReady', {
            detail: { 
                role: this.userRole, 
                capabilities: this.capabilities 
            }
        }));
    }

    // Get role display information
    getRoleInfo() {
        const roleInfo = {
            'user': {
                display: 'User',
                color: '#28a745',
                description: 'Regular user with basic posting privileges'
            },
            'admin': {
                display: 'Administrator',
                color: '#fd7e14', 
                description: 'Administrator with moderation privileges'
            },
            'super_admin': {
                display: 'Super Administrator',
                color: '#dc3545',
                description: 'Full system administrator'
            }
        };

        return roleInfo[this.userRole] || roleInfo['user'];
    }

    // Debug information for developers
    getDebugInfo() {
        return {
            userRole: this.userRole,
            userId: this.userId,
            capabilities: this.capabilities,
            config: this.config,
            initialized: this.initialized
        };
    }
}

// Create global instance
window.roleManager = new RoleManager();

// Auto-initialize after DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait for other systems to initialize first
    setTimeout(() => {
        if (window.roleManager && !window.roleManager.initialized) {
            window.roleManager.init();
            
            // Initialize after other systems are ready
            setTimeout(() => {
                window.roleManager.initializeAfterSystems();
            }, 500);
        }
    }, 100);
});

