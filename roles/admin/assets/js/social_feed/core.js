class AdminSocialFeedCore extends SocialFeedCore {
    constructor() {
        super();
        this.adminStats = {
            totalPosts: 0,
            hiddenPosts: 0,
            activeUsers: 0
        };
    }

    // Initialize the entire feed system for admin
    async init() {
        await this.loadUserInfo();
        this.initializeFeed();
        this.setupEventListeners();
        this.loadAdminStats();
    }

    // Load admin statistics
    async loadAdminStats() {
        try {
            const response = await fetch('api/admin_get_stats.php');
            const data = await response.json();
            
            if (data.success) {
                this.adminStats = data;
                this.updateAdminStatsUI();
            }
        } catch (error) {
            console.error('Error loading admin stats:', error);
        }
    }

    // Update admin stats UI
    updateAdminStatsUI() {
        const elements = {
            totalPosts: document.getElementById('totalPosts'),
            hiddenPosts: document.getElementById('hiddenPosts'),
            activeUsers: document.getElementById('activeUsers')
        };

        if (elements.totalPosts) elements.totalPosts.textContent = this.adminStats.total_posts;
        if (elements.hiddenPosts) elements.hiddenPosts.textContent = this.adminStats.hidden_posts;
        if (elements.activeUsers) elements.activeUsers.textContent = this.adminStats.active_users;
    }

    // Load posts with admin filters
    async loadPosts(page = 0, filter = 'all') {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        try {
            const response = await fetch(`api/admin_get_posts.php?filter=${filter}&page=${page}&limit=10`);
            
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

    // Create post element with admin controls
    createPostElement(post) {
        const postDiv = super.createPostElement(post);
        
        // Add admin controls to the post
        if (this.canModeratePost(post)) {
            const adminControls = this.createAdminControls(post);
            postDiv.querySelector('.post-header').appendChild(adminControls);
        }
        
        return postDiv;
    }

    // Check if admin can moderate this post
    canModeratePost(post) {
        // Admin can moderate any post except their own (or can they?)
        return this.currentUser && this.currentUser.id !== post.user_id;
    }

    // Create admin controls for post
    createAdminControls(post) {
        const controlsDiv = document.createElement('div');
        controlsDiv.className = 'post-admin-controls';
        
        controlsDiv.innerHTML = `
            <div class="post-actions-menu">
                <button class="post-menu-btn" onclick="toggleAdminMenu(${post.id})">
                    <i class='bx bx-dots-horizontal-rounded'></i>
                </button>
                <div class="post-menu admin-menu" id="adminMenu${post.id}">
                    <div class="post-menu-item" onclick="moderatePost(${post.id}, 'hide')">
                        <i class='bx bx-hide'></i> Hide Post
                    </div>
                    <div class="post-menu-item" onclick="moderatePost(${post.id}, 'show')">
                        <i class='bx bx-show'></i> Show Post
                    </div>
                    <div class="post-menu-item danger" onclick="deletePost(${post.id})">
                        <i class='bx bx-trash'></i> Delete Post
                    </div>
                    <div class="post-menu-item" onclick="viewPostDetails(${post.id})">
                        <i class='bx bx-detail'></i> View Details
                    </div>
                </div>
            </div>
        `;
        
        return controlsDiv;
    }
}

// Create global instance
window.socialFeedCore = new AdminSocialFeedCore();