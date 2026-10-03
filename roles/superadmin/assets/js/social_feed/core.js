class SocialFeedCore {
    constructor() {
        this.currentFilter = 'all';
        this.currentPage = 0;
        this.isLoading = false;
        this.hasMorePosts = true;
        this.currentUser = null;
        this.notificationInterval = null;
    }

    // Initialize the entire feed system
    async init() {
        await this.loadUserInfo();
        this.initializeFeed();
        this.setupEventListeners();
    }

    // Load user information
    async loadUserInfo() {
        try {
            const response = await fetch('api/get_user_info.php');
            const data = await response.json();
            
            if (data.success) {
                this.currentUser = data.user;
                this.updateUserInterface();
            }
        } catch (error) {
            console.error('Error loading user info:', error);
        }
    }

    // Update UI with user data
    updateUserInterface() {
        if (!this.currentUser) return;

        const elements = {
            composerName: document.getElementById('composerName'),
            composerAvatar: document.getElementById('composerAvatar'),
            userAvatar: document.getElementById('userAvatar')
        };

        const defaultAvatar = 'assets/img/default-avatar.png';
        const avatar = this.currentUser.profile_image || defaultAvatar;

        if (elements.composerName) elements.composerName.textContent = this.currentUser.full_name;
        if (elements.composerAvatar) elements.composerAvatar.src = avatar;
        if (elements.userAvatar) elements.userAvatar.src = avatar;
    }

    // Initialize feed components
    initializeFeed() {
        this.loadPosts();
        this.startNotificationCheck();
    }

    // Start notification checking
    startNotificationCheck() {
        this.notificationInterval = setInterval(() => {
            this.checkNotifications();
        }, 30000); // Check every 30 seconds
    }

    // Stop notification checking
    stopNotificationCheck() {
        if (this.notificationInterval) {
            clearInterval(this.notificationInterval);
            this.notificationInterval = null;
        }
    }

    // Check for notifications
    async checkNotifications() {
        try {
            const response = await fetch('api/get_notification_count.php');
            const data = await response.json();
            
            if (data.success) {
                const countElement = document.getElementById('notificationCount');
                if (countElement) {
                    countElement.textContent = data.count;
                    countElement.style.display = data.count > 0 ? 'block' : 'none';
                }
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }

    // Setup all event listeners
    setupEventListeners() {
        this.setupTabListeners();
        this.setupVisibilityListeners();
        this.setupDocumentListeners();
        this.setupPostContentListener();
    }

    // Setup tab switching listeners
    setupTabListeners() {
        document.querySelectorAll('.feed-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                this.switchTab(tab.dataset.filter);
            });
        });
    }

    // Setup visibility dropdown listeners
    setupVisibilityListeners() {
        const visibilityBtn = document.getElementById('visibilityBtn');
        if (visibilityBtn) {
            visibilityBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleVisibilityDropdown();
            });
        }
    }

    // Setup document-level listeners
    setupDocumentListeners() {
        document.addEventListener('click', (e) => {
            // Close visibility dropdown when clicking outside
            if (!e.target.closest('.visibility-selector')) {
                const dropdown = document.getElementById('visibilityDropdown');
                if (dropdown) dropdown.classList.remove('show');
            }
            
            // Close post menus when clicking outside
            if (!e.target.closest('.post-actions-menu')) {
                document.querySelectorAll('.post-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
    }

    // Setup post content input listener
    setupPostContentListener() {
        const postContent = document.getElementById('postContent');
        if (postContent) {
            postContent.addEventListener('input', () => {
                const length = postContent.value.length;
                const button = document.getElementById('postBtn');
                if (button) button.disabled = length === 0;
            });
        }
    }

    // Switch between feed tabs
    switchTab(filter) {
        // Update tab UI
        document.querySelectorAll('.feed-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        const activeTab = document.querySelector(`[data-filter="${filter}"]`);
        if (activeTab) activeTab.classList.add('active');
        
        // Update state
        this.currentFilter = filter;
        this.currentPage = 0;
        this.hasMorePosts = true;
        
        // Show loading and reload posts
        const feedContent = document.getElementById('feedContent');
        if (feedContent) {
            feedContent.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading posts...</p></div>';
        }
        
        this.loadPosts();
    }

    // Toggle visibility dropdown
    toggleVisibilityDropdown() {
        const dropdown = document.getElementById('visibilityDropdown');
        if (!dropdown) return;

        dropdown.classList.toggle('show');
        
        // Setup visibility option handlers
        document.querySelectorAll('.visibility-option').forEach(option => {
            option.onclick = () => {
                this.selectVisibility(option.dataset.visibility);
            };
        });
    }

    // Select visibility option
    selectVisibility(visibility) {
        const btn = document.getElementById('visibilityBtn');
        const dropdown = document.getElementById('visibilityDropdown');
        
        const visibilityMap = {
            'public': { icon: 'bx-globe', text: 'Everyone' },
            'department': { icon: 'bx-buildings', text: 'Department' },
            'custom': { icon: 'bx-group', text: 'Specific Users' }
        };
        
        const selected = visibilityMap[visibility];
        if (btn && selected) {
            btn.innerHTML = `<i class='bx ${selected.icon}'></i> ${selected.text} <i class='bx bx-chevron-down'></i>`;
        }
        
        const visibilityInput = document.getElementById('postVisibility');
        if (visibilityInput) visibilityInput.value = visibility;
        
        if (dropdown) dropdown.classList.remove('show');
    }

    // Load posts from server
    async loadPosts(page = 0) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        try {
            const response = await fetch(`api/get_posts.php?filter=${this.currentFilter}&page=${page}&limit=10`);
            
            // Log the response for debugging
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('JSON Parse Error:', jsonError);
                console.error('Response was:', responseText);
                throw new Error(`Server returned invalid JSON. Response: ${responseText.substring(0, 200)}...`);
            }
            
            if (data.success) {
                this.handlePostsLoaded(data.posts, page);
            } else {
                throw new Error(data.message || 'Failed to load posts');
            }
        } catch (error) {
            console.error('Error loading posts:', error);
            this.showError(`Error loading posts: ${error.message}`);
        } finally {
            this.isLoading = false;
        }
    }

    // Handle loaded posts
    handlePostsLoaded(posts, page) {
        const feedContent = document.getElementById('feedContent');
        if (!feedContent) return;

        if (page === 0) {
            feedContent.innerHTML = '';
        }
        
        if (posts.length === 0 && page === 0) {
            this.showEmptyState();
        } else {
            posts.forEach(post => {
                feedContent.appendChild(window.postRenderer.createPostElement(post));
            });
            
            this.hasMorePosts = posts.length === 10;
            this.currentPage = page;
        }
        
        this.updateLoadMoreButton();
    }

    // Show empty state
    showEmptyState() {
        const feedContent = document.getElementById('feedContent');
        if (feedContent) {
            feedContent.innerHTML = `
                <div class="no-posts">
                    <i class='bx bx-message-dots'></i>
                    <h3>No posts yet</h3>
                    <p>Be the first to share something with your colleagues!</p>
                </div>
            `;
        }
        this.hasMorePosts = false;
    }

    // Show error state
    showError(message) {
        const feedContent = document.getElementById('feedContent');
        if (feedContent) {
            feedContent.innerHTML = `
                <div class="no-posts">
                    <i class='bx bx-error'></i>
                    <h3>Error loading posts</h3>
                    <p>${message || 'Please try again later'}</p>
                </div>
            `;
        }
    }

    // Update load more button visibility
    updateLoadMoreButton() {
        const loadMoreSection = document.getElementById('loadMoreSection');
        if (loadMoreSection) {
            loadMoreSection.style.display = this.hasMorePosts ? 'block' : 'none';
        }
    }

    // Refresh the entire feed
    async refreshFeed() {
        const feedContent = document.getElementById('feedContent');
        if (feedContent) {
            feedContent.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading posts...</p></div>';
        }
        
        this.currentPage = 0;
        this.hasMorePosts = true;
        await this.loadPosts();
    }

    // Load more posts
    loadMorePosts() {
        this.loadPosts(this.currentPage + 1);
    }

    // Search posts
    searchPosts(event) {
        event.preventDefault();
        const query = document.getElementById('searchInput')?.value.trim();
        
        if (query) {
            console.log('Searching for:', query);
            // TODO: Implement search API call
        }
    }

    // Check if user can perform admin actions on post
    canEditPost(post) {
        return this.currentUser && 
               (this.currentUser.id == post.user_id || 
                ['admin', 'super_admin'].includes(this.currentUser.role));
    }

    // Check if user can perform admin actions on comment
    canEditComment(comment) {
        return this.currentUser && 
               (this.currentUser.id == comment.user_id || 
                ['admin', 'super_admin'].includes(this.currentUser.role));
    }

    // Get current user
    getCurrentUser() {
        return this.currentUser;
    }

    // Cleanup when page unloads
    destroy() {
        this.stopNotificationCheck();
    }
}

// Create global instance
window.socialFeedCore = new SocialFeedCore();