// admin-main.js

// Main entry point that coordinates all admin modules

// Global function wrappers for HTML onclick handlers
// These maintain compatibility with your existing HTML

// Admin specific functions
function moderatePost(postId, action) {
    return window.postActions.moderatePost(postId, action);
}

function viewPostDetails(postId) {
    return window.postActions.viewPostDetails(postId);
}

function toggleAdminMenu(postId) {
    const menu = document.getElementById(`adminMenu${postId}`);
    if (menu) {
        document.querySelectorAll('.admin-menu').forEach(m => {
            if (m.id !== `adminMenu${postId}`) m.classList.remove('show');
        });
        menu.classList.toggle('show');
    }
}

// Application Initialization
class AdminSocialFeedApp {
    constructor() {
        this.initialized = false;
    }

    // Initialize the entire application
    async init() {
        if (this.initialized) return;

        try {
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.startApp());
            } else {
                this.startApp();
            }
        } catch (error) {
            console.error('Error initializing Admin Social Feed App:', error);
        }
    }

    // Start the application
    async startApp() {
        try {
            // Initialize core first (this will create currentUser and other essential data)
            await window.socialFeedCore.init();
            
            console.log('Admin Social Feed App initialized successfully');
            
            this.initialized = true;
        } catch (error) {
            console.error('Error starting Admin Social Feed App:', error);
            window.utils.showNotification('Error initializing application', 'error');
        }
    }

    // Cleanup when leaving page
    destroy() {
        if (window.socialFeedCore) {
            window.socialFeedCore.destroy();
        }
        this.initialized = false;
    }
}

// Create and initialize the app
const adminSocialFeedApp = new AdminSocialFeedApp();

// Auto-initialize when script loads
adminSocialFeedApp.init();

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    adminSocialFeedApp.destroy();
});

// Export for potential external use
window.adminSocialFeedApp = adminSocialFeedApp;