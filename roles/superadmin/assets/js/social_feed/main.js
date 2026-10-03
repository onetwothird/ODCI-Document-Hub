// social-feed-main.js

// Main entry point that coordinates all modules and exposes global functions

// Global function wrappers for HTML onclick handlers
// These maintain compatibility with your existing HTML

// Post Composer Functions
function createPost(event) {
    return window.postComposer.createPost(event);
}

function handleImageUpload(input) {
    return window.postComposer.handleImageUpload(input);
}

function handleFileUpload(input) {
    return window.postComposer.handleFileUpload(input);
}

function toggleLinkInput() {
    return window.postComposer.toggleLinkInput();
}

function togglePrioritySelector() {
    return window.postComposer.togglePrioritySelector();
}

// Post Actions Functions
function togglePostMenu(postId) {
    return window.postActions.togglePostMenu(postId);
}

function editPost(postId) {
    return window.postActions.editPost(postId);
}

function deletePost(postId) {
    return window.postActions.deletePost(postId);
}

function toggleLike(postId) {
    return window.postActions.toggleLike(postId);
}

function reactToPost(postId, reactionType) {
    return window.postActions.reactToPost(postId, reactionType);
}

function showReactionPicker(postId) {
    return window.postActions.showReactionPicker(postId);
}

function hideReactionPicker(postId) {
    return window.postActions.hideReactionPicker(postId);
}

// Comment System Functions
function toggleComments(postId) {
    return window.commentSystem.toggleComments(postId);
}

function addComment(postId) {
    return window.commentSystem.addComment(postId);
}

function handleCommentKeyPress(event, postId) {
    return window.commentSystem.handleCommentKeyPress(event, postId);
}

function replyToComment(commentId) {
    return window.commentSystem.replyToComment(commentId);
}

function editComment(commentId) {
    return window.commentSystem.editComment(commentId);
}

function deleteComment(commentId) {
    return window.commentSystem.deleteComment(commentId);
}

function toggleCommentLike(commentId) {
    return window.commentSystem.toggleCommentLike(commentId);
}

// Media Handler Functions
function openImageModal(imagePath) {
    return window.mediaHandler.openImageModal(imagePath);
}

function downloadFile(filePath, fileName) {
    return window.mediaHandler.downloadFile(filePath, fileName);
}

function openLink(url) {
    return window.mediaHandler.openLink(url);
}

// Core Functions
function loadMorePosts() {
    return window.socialFeedCore.loadMorePosts();
}

function searchPosts(event) {
    return window.socialFeedCore.searchPosts(event);
}

// Application Initialization
class SocialFeedApp {
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
            console.error('Error initializing Social Feed App:', error);
        }
    }

    // Start the application
    async startApp() {
        try {
            // Initialize core first (this will create currentUser and other essential data)
            await window.socialFeedCore.init();
            
            // All other modules are already initialized as they depend on the core
            console.log('Social Feed App initialized successfully');
            
            this.initialized = true;
        } catch (error) {
            console.error('Error starting Social Feed App:', error);
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
const socialFeedApp = new SocialFeedApp();

// Auto-initialize when script loads
socialFeedApp.init();

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    socialFeedApp.destroy();
});

// Export for potential external use
window.socialFeedApp = socialFeedApp;