// FIXED: comment_system.js - Proper comment ownership control with improved animations and profile image fixes

class CommentSystem {
    constructor(core) {
        this.core = core;
        this.currentlyEditing = null;
        this.replyingTo = null;
        this.replyingToData = null;
        this.currentModalPostId = null;
        this.modalOverlay = null;
        this.modal = null;
        this.currentUserId = null; // Store current user ID for easier access
        this.isClosing = false; // Track closing state
    }

    // FIXED: Initialize with proper user ID detection
    init() {
        // Method 1: Get from core system
        if (window.socialFeedCore && window.socialFeedCore.getCurrentUser) {
            const currentUser = window.socialFeedCore.getCurrentUser();
            this.currentUserId = currentUser ? parseInt(currentUser.id) : null;
        }
        
        // Method 2: Get from global variable
        if (!this.currentUserId && window.currentUserId) {
            this.currentUserId = parseInt(window.currentUserId);
        }
        
        // Method 3: Get from session data in DOM
        if (!this.currentUserId) {
            const userIdElement = document.querySelector('[data-user-id]') || 
                                  document.getElementById('composerAvatar') ||
                                  document.querySelector('.composer-avatar');
            if (userIdElement && userIdElement.dataset.userId) {
                this.currentUserId = parseInt(userIdElement.dataset.userId);
            }
        }

        // Method 4: Try to extract from any existing post element
        if (!this.currentUserId) {
            const postElement = document.querySelector('.post[data-user-id]');
            if (postElement) {
                // Look for a post that belongs to current user (has edit menu)
                const postWithMenu = document.querySelector('.post .post-actions-menu');
                if (postWithMenu) {
                    const parentPost = postWithMenu.closest('.post');
                    if (parentPost && parentPost.dataset.userId) {
                        this.currentUserId = parseInt(parentPost.dataset.userId);
                    }
                }
            }
        }

        // Method 5: Make an AJAX call to get current user ID if all else fails
        if (!this.currentUserId) {
            this.fetchCurrentUserId();
        }
        
        console.log('CommentSystem initialized with user ID:', this.currentUserId);
    }

    // Fetch current user ID from server if not available
    async fetchCurrentUserId() {
        try {
            const response = await fetch('/ODCI/social_feed/api/get_user_info.php');
            const data = await response.json();
            if (data.success && data.user) {
                this.currentUserId = parseInt(data.user.id);
                console.log('Fetched current user ID:', this.currentUserId);
            }
        } catch (error) {
            console.error('Error fetching current user ID:', error);
        }
    }

    // Main function to open comments modal
    async openCommentsModal(postId) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (!postElement) return;
        
        this.currentModalPostId = postId;
        
        // Create modal if it doesn't exist
        this.createModalStructure();
        
        // Clone and display the post in modal
        this.displayPostInModal(postElement);
        
        // Load comments
        await this.loadCommentsInModal(postId);
        
        // Show modal with smooth animation
        this.showModal();
    }

    // Create the modal HTML structure
    createModalStructure() {
        // Remove existing modal if present
        const existingOverlay = document.getElementById('commentsModalOverlay');
        if (existingOverlay) {
            existingOverlay.remove();
        }

        const modalHTML = `
            <div class="comments-modal-overlay" id="commentsModalOverlay">
                <div class="comments-modal" id="commentsModal">
                    <div class="comments-modal-header">
                        <h3 class="comments-modal-title">Post</h3>
                        <button class="comments-modal-close" id="commentsModalClose">
                            <i class='bx bx-x'></i>
                        </button>
                    </div>
                    
                    <div class="comments-modal-content" id="commentsModalContent">
                        <div class="comments-modal-post" id="commentsModalPost">
                            <!-- Post content will be inserted here -->
                        </div>
                        
                        <div class="comments-modal-comments-section">
                            <div class="comments-modal-loading" id="commentsModalLoading" style="display: none;">
                                <div class="spinner"></div>
                                <p>Loading comments...</p>
                            </div>
                            
                            <div class="comments-modal-comments-container" id="commentsModalCommentsContainer">
                                <!-- Comments will be loaded here -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="comments-modal-comment-form" id="commentsModalCommentForm">
                        <div class="modal-comment-form-wrapper">
                            <img src="assets/img/default-avatar.png" alt="Your avatar" class="modal-comment-avatar" id="modalCommentAvatar">
                            <div class="modal-comment-input-wrapper">
                                <textarea class="modal-comment-input" id="modalCommentInput" placeholder="Write a comment..." rows="1"></textarea>
                            </div>
                            <button class="modal-comment-submit" id="modalCommentSubmit" disabled>
                                <i class='bx bx-send'></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Store references
        this.modalOverlay = document.getElementById('commentsModalOverlay');
        this.modal = document.getElementById('commentsModal');
        
        // Bind events
        this.bindModalEvents();
    }

    // Bind modal events
    bindModalEvents() {
        const closeBtn = document.getElementById('commentsModalClose');
        const overlay = this.modalOverlay;
        const input = document.getElementById('modalCommentInput');
        const submitBtn = document.getElementById('modalCommentSubmit');

        // Close button
        closeBtn.addEventListener('click', () => this.closeModal());

        // Close on overlay click (not on modal click)
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                this.closeModal();
            }
        });

        // ESC key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay.classList.contains('show') && !this.isClosing) {
                this.closeModal();
            }
        });

        // Input events
        input.addEventListener('input', () => {
            this.autoResizeTextarea(input);
            this.toggleSubmitButton();
        });

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.submitModalComment();
            }
        });

        // Submit button
        submitBtn.addEventListener('click', () => this.submitModalComment());
        
        // FIXED: Update modal comment avatar with proper path handling
        this.updateModalCommentAvatar();
    }

    // FIXED: Update modal comment avatar with proper path handling
    updateModalCommentAvatar() {
        const modalAvatar = document.getElementById('modalCommentAvatar');
        if (!modalAvatar) return;
        
        // Try multiple methods to get current user's avatar
        let avatarSrc = 'assets/img/default-avatar.png';
        
        // Method 1: Check if core system has user info
        if (window.socialFeedCore && window.socialFeedCore.getCurrentUser) {
            const currentUser = window.socialFeedCore.getCurrentUser();
            if (currentUser && currentUser.profile_image) {
                avatarSrc = this.getProperImagePath(currentUser.profile_image);
            }
        }
        
        // Method 2: Check composer avatar in DOM
        const composerAvatar = document.getElementById('composerAvatar') || 
                              document.querySelector('.composer-avatar');
        if (composerAvatar && composerAvatar.src) {
            avatarSrc = composerAvatar.src;
        }
        
        // Method 3: Check navbar profile image
        const navbarAvatar = document.querySelector('.profile-image img');
        if (navbarAvatar && navbarAvatar.src) {
            avatarSrc = navbarAvatar.src;
        }
        
        // Set the avatar with error fallback
        modalAvatar.src = avatarSrc;
        modalAvatar.onerror = function() {
            this.src = 'assets/img/default-avatar.png';
        };
    }

    // FIXED: Helper function to get proper image path
    getProperImagePath(imagePath) {
        if (!imagePath) return 'assets/img/default-avatar.png';
        
        if (imagePath.startsWith('http')) {
            return imagePath; // Full URL - use as is
        } else if (imagePath.startsWith('/')) {
            return imagePath; // Absolute path from root
        } else if (imagePath.startsWith('assets/') || imagePath.startsWith('img/')) {
            return imagePath; // Relative to current directory
        } else if (imagePath.startsWith('uploads/')) {
            return '/' + imagePath; // Relative to root - add leading slash
        } else {
            return imagePath; // Assume it's relative to current directory
        }
    }

    // Auto-resize textarea
    autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    // Toggle submit button state
    toggleSubmitButton() {
        const input = document.getElementById('modalCommentInput');
        const submitBtn = document.getElementById('modalCommentSubmit');
        
        if (input && submitBtn) {
            const hasContent = input.value.trim().length > 0;
            submitBtn.disabled = !hasContent;
        }
    }

    // Display post content in modal
    displayPostInModal(originalPostElement) {
        const modalPost = document.getElementById('commentsModalPost');
        if (!modalPost || !originalPostElement) return;

        // Clone the post element
        const postClone = originalPostElement.cloneNode(true);
        
        // Remove any existing click handlers from actions in the clone
        const postActions = postClone.querySelectorAll('.post-action');
        postActions.forEach(action => {
            action.removeAttribute('onclick');
        });

        // Clear modal post container and add cloned content
        modalPost.innerHTML = postClone.innerHTML;

        // Re-bind all post functionality for the modal post
        this.bindModalPostActions();

        // FIXED: Update user avatar in comment form with proper path handling
        this.updateModalCommentAvatar();
    }

    // Bind post actions in modal
    bindModalPostActions() {
        const modalPost = document.getElementById('commentsModalPost');
        if (!modalPost) return;

        // Bind like functionality
        const likeBtn = modalPost.querySelector('.post-action');
        if (likeBtn && likeBtn.innerHTML.includes('bx-heart')) {
            likeBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                if (this.currentModalPostId && window.postActions) {
                    try {
                        const result = await window.postActions.toggleLike(this.currentModalPostId);
                        if (result && result.success) {
                            // Update modal post like UI
                            this.updateModalPostLikeUI(result.liked, result.like_count);
                            // Update original post like UI
                            this.updateOriginalPostLikeUI(this.currentModalPostId, result.liked, result.like_count);
                        }
                    } catch (error) {
                        console.error('Error toggling post like in modal:', error);
                    }
                } else if (typeof toggleLike === 'function') {
                    // Fallback to global function
                    toggleLike(this.currentModalPostId);
                }
            });
        }

        // Bind other post actions if needed (share, etc.)
        const allActions = modalPost.querySelectorAll('.post-action');
        allActions.forEach(action => {
            if (action.innerHTML.includes('bx-message')) {
                // Comment button - should be disabled in modal or do nothing
                action.style.pointerEvents = 'none';
                action.style.opacity = '0.6';
            }
        });
    }

    // Update modal post like UI
    updateModalPostLikeUI(liked, likeCount) {
        const modalPost = document.getElementById('commentsModalPost');
        if (!modalPost) return;

        const likeBtn = modalPost.querySelector('.post-action');
        if (likeBtn && likeBtn.innerHTML.includes('bx-heart')) {
            const icon = likeBtn.querySelector('i');
            const text = likeBtn.querySelector('span');
            
            if (icon) {
                icon.className = liked ? 'bx bxs-heart' : 'bx bx-heart';
            }
            
            if (text) {
                text.textContent = liked ? 'Liked' : 'Like';
            }
            
            likeBtn.className = liked ? 'post-action liked' : 'post-action';
        }

        // Update like count in stats
        const statsDiv = modalPost.querySelector('.post-stats');
        if (statsDiv) {
            let likeCountElement = null;
            const spans = statsDiv.querySelectorAll('span');
            spans.forEach(span => {
                if (span.innerHTML.includes('bx-heart')) {
                    likeCountElement = span;
                }
            });

            if (likeCountElement) {
                if (likeCount > 0) {
                    likeCountElement.innerHTML = `<i class='bx bx-heart'></i> ${likeCount}`;
                } else {
                    likeCountElement.remove();
                }
            } else if (likeCount > 0) {
                statsDiv.insertAdjacentHTML('afterbegin', `<span><i class='bx bx-heart'></i> ${likeCount}</span>`);
            }
        }
    }

    // Update original post like UI
    updateOriginalPostLikeUI(postId, liked, likeCount) {
        const originalPost = document.querySelector(`[data-post-id="${postId}"]`);
        if (!originalPost) return;

        const likeBtn = originalPost.querySelector('.post-action');
        if (likeBtn && likeBtn.innerHTML.includes('bx-heart')) {
            const icon = likeBtn.querySelector('i');
            const text = likeBtn.querySelector('span');
            
            if (icon) {
                icon.className = liked ? 'bx bxs-heart' : 'bx bx-heart';
            }
            
            if (text) {
                text.textContent = liked ? 'Liked' : 'Like';
            }
            
            likeBtn.className = liked ? 'post-action liked' : 'post-action';
        }

        // Update like count in original post stats
        const statsDiv = originalPost.querySelector('.post-stats');
        if (statsDiv) {
            let likeCountElement = null;
            const spans = statsDiv.querySelectorAll('span');
            spans.forEach(span => {
                if (span.innerHTML.includes('bx-heart')) {
                    likeCountElement = span;
                }
            });

            if (likeCountElement) {
                if (likeCount > 0) {
                    likeCountElement.innerHTML = `<i class='bx bx-heart'></i> ${likeCount}`;
                } else {
                    likeCountElement.remove();
                }
            } else if (likeCount > 0) {
                statsDiv.insertAdjacentHTML('afterbegin', `<span><i class='bx bx-heart'></i> ${likeCount}</span>`);
            }
        }
    }

    // Show modal with smooth animation
    showModal() {
        if (!this.modalOverlay) return;
        
        this.isClosing = false;
        this.modalOverlay.style.display = 'flex';
        
        // Remove any closing class
        this.modalOverlay.classList.remove('closing');
        
        // Force reflow to ensure the display change is applied
        this.modalOverlay.offsetHeight;
        
        // Add show class for animation
        this.modalOverlay.classList.add('show');
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Focus comment input after animation
        setTimeout(() => {
            const input = document.getElementById('modalCommentInput');
            if (input) input.focus();
        }, 450); // Wait for animation to complete
    }

    // Close modal with smooth animation
    closeModal() {
        if (!this.modalOverlay || this.isClosing) return;
        
        this.isClosing = true;
        
        // Add closing class for animation
        this.modalOverlay.classList.add('closing');
        this.modalOverlay.classList.remove('show');
        
        // Wait for animation to complete before hiding
        setTimeout(() => {
            if (this.modalOverlay) {
                this.modalOverlay.style.display = 'none';
                this.modalOverlay.classList.remove('closing');
                
                // Restore body scroll
                document.body.style.overflow = '';
                
                // Clean up
                this.currentModalPostId = null;
                this.cancelReply();
                this.cancelEdit();
                this.isClosing = false;
                
                // Clear input
                const input = document.getElementById('modalCommentInput');
                if (input) {
                    input.value = '';
                    this.autoResizeTextarea(input);
                }
                this.toggleSubmitButton();
            }
        }, 350); // Match the closing animation duration
    }

    // Load comments in modal
    async loadCommentsInModal(postId) {
        const loadingDiv = document.getElementById('commentsModalLoading');
        const container = document.getElementById('commentsModalCommentsContainer');

        if (loadingDiv) loadingDiv.style.display = 'block';
        if (container) container.innerHTML = '';

        try {
            const response = await fetch(`/ODCI/social_feed/api/get_comments.php?post_id=${postId}&limit=50`);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                throw new Error('Server returned invalid response');
            }

            if (data.success && container) {
                this.renderCommentsInModal(container, data.comments);
            } else {
                this.showModalError(container, data.message || 'Failed to load comments');
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            this.showModalError(container, error.message);
        } finally {
            if (loadingDiv) loadingDiv.style.display = 'none';
        }
    }

    // Render comments in modal
    renderCommentsInModal(container, comments) {
        container.innerHTML = '';

        if (comments.length === 0) {
            container.innerHTML = `
                <div class="comments-modal-empty">
                    <i class='bx bx-message-dots'></i>
                    <h3>No comments yet</h3>
                    <p>Be the first to share what you think!</p>
                </div>
            `;
            return;
        }

        comments.forEach(comment => {
            const commentElement = this.createModalCommentElement(comment);
            container.appendChild(commentElement);

            // Add replies if they exist
            if (comment.replies && comment.replies.length > 0) {
                const repliesContainer = document.createElement('div');
                repliesContainer.className = 'modal-replies-container';

                comment.replies.forEach(reply => {
                    const replyElement = this.createModalCommentElement(reply, true, comment.commenter_full_name);
                    repliesContainer.appendChild(replyElement);
                });

                container.appendChild(repliesContainer);
            }
        });
    }

    // FIXED: Create comment element for modal - STRICT ownership check with proper profile image handling
    createModalCommentElement(comment, isReply = false, parentAuthor = null) {
        const commentDiv = document.createElement('div');
        commentDiv.className = 'modal-comment' + (isReply ? ' modal-comment-reply' : '');
        commentDiv.dataset.commentId = comment.id;

        const timeAgo = window.utils ? window.utils.getTimeAgo(comment.created_at) : 'recently';
        const editedText = comment.is_edited ?
            '<span style="color: var(--text-secondary); font-size: 11px; margin-left: 8px;">(edited)</span>' : '';

        // FIXED: Proper profile image path handling
        let profileImageSrc = 'assets/img/default-avatar.png'; // Default fallback
        
        if (comment.profile_image) {
            // Handle different profile image path formats
            if (comment.profile_image.startsWith('http')) {
                // Full URL - use as is
                profileImageSrc = comment.profile_image;
            } else if (comment.profile_image.startsWith('/')) {
                // Absolute path from root
                profileImageSrc = comment.profile_image;
            } else if (comment.profile_image.startsWith('assets/') || comment.profile_image.startsWith('img/')) {
                // Relative to current directory
                profileImageSrc = comment.profile_image;
            } else if (comment.profile_image.startsWith('uploads/')) {
                // Relative to root - add leading slash
                profileImageSrc = '/' + comment.profile_image;
            } else {
                // Assume it's relative to current directory
                profileImageSrc = comment.profile_image;
            }
            
            // Add cache busting if needed
            profileImageSrc += (profileImageSrc.includes('?') ? '&' : '?') + 'v=' + new Date().getTime();
        }

        // Create action buttons
        const actions = [];
        
        // Always show Reply
        actions.push(`<span class="comment-action" onclick="window.commentSystem.startModalReply(${comment.id})">Reply</span>`);

        // FIXED: STRICT ownership check - only show edit/delete if user owns this specific comment
        const commentOwnerId = parseInt(comment.user_id);
        const currentUserId = parseInt(this.currentUserId);
        const isOwner = currentUserId && (currentUserId === commentOwnerId);
        
        console.log('STRICT Comment ownership check:', {
            commentId: comment.id,
            commentOwnerId: commentOwnerId,
            currentUserId: currentUserId,
            isOwner: isOwner,
            strictComparison: currentUserId === commentOwnerId
        });

        // ONLY add edit/delete actions if user owns this comment
        if (isOwner) {
            actions.push(`<span class="comment-action" onclick="window.commentSystem.startModalEdit(${comment.id})">Edit</span>`);
            actions.push(`<span class="comment-action comment-delete-action" onclick="window.commentSystem.deleteModalComment(${comment.id})">Delete</span>`);
        }

        // Always show Like
        const likeText = comment.user_liked ? 'Unlike' : 'Like';
        const likeCount = comment.like_count > 0 ? ` · ${comment.like_count}` : '';
        actions.push(`<span class="comment-action comment-like-btn" onclick="window.commentSystem.toggleModalCommentLike(${comment.id})">${likeText}${likeCount}</span>`);

        commentDiv.innerHTML = `
            <img src="${profileImageSrc}" 
                alt="${comment.commenter_full_name}" class="comment-avatar" 
                onerror="this.src='assets/img/default-avatar.png'">
            <div class="comment-content">
                <div class="comment-header">
                    <span class="comment-author">${comment.commenter_full_name}</span>
                    <span class="comment-time">${timeAgo}</span>
                    ${editedText}
                </div>
                <div class="comment-text" ${isOwner ? `id="commentText${comment.id}"` : ''}>${this.escapeHtml(comment.content)}</div>
                <div class="comment-actions">
                    ${actions.join(' · ')}
                </div>
            </div>
        `;

        return commentDiv;
    }

    // Show modal error
    showModalError(container, message = 'Error loading comments') {
        if (container) {
            container.innerHTML = `
                <div class="comments-modal-empty">
                    <i class='bx bx-error'></i>
                    <h3>Error</h3>
                    <p>${message}</p>
                </div>
            `;
        }
    }

    // Submit comment from modal
    async submitModalComment() {
        if (!this.currentModalPostId) return;

        const input = document.getElementById('modalCommentInput');
        if (!input) return;

        const content = input.value.trim();
        if (!content) {
            this.showNotification('Please enter a comment', 'warning');
            return;
        }

        // Handle edit mode
        if (this.currentlyEditing) {
            await this.submitModalEdit(content);
            return;
        }

        const submitButton = document.getElementById('modalCommentSubmit');
        const originalHTML = submitButton ? submitButton.innerHTML : '';
        
        if (submitButton) {
            submitButton.innerHTML = '<div class="spinner"></div>';
            submitButton.disabled = true;
        }

        try {
            const requestData = {
                post_id: this.currentModalPostId,
                content: content
            };

            // Handle reply
            if (this.replyingTo) {
                requestData.parent_comment_id = this.replyingTo;
            }

            const response = await fetch('/ODCI/social_feed/api/add_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            });

            const responseText = await response.text();

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${responseText}`);
            }

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Invalid JSON response');
            }

            if (result.success) {
                input.value = '';
                this.autoResizeTextarea(input);
                this.cancelReply();

                // Update comment count in original post
                if (result.actual_comment_count !== undefined) {
                    this.updateCommentCountFromServer(this.currentModalPostId, result.actual_comment_count);
                }

                // Reload comments
                await this.loadCommentsInModal(this.currentModalPostId);
                
                this.showNotification(result.message || 'Comment added successfully!', 'success');
            } else {
                throw new Error(result.message || 'Failed to add comment');
            }
        } catch (error) {
            console.error('Error adding comment:', error);
            this.showNotification(error.message || 'Error adding comment', 'error');
        } finally {
            if (submitButton) {
                submitButton.innerHTML = originalHTML;
            }
            this.toggleSubmitButton();
        }
    }

    // Start reply in modal
    startModalReply(commentId) {
        this.cancelEdit();
        this.cancelReply();
        
        const commentElement = document.querySelector(`#commentsModalCommentsContainer [data-comment-id="${commentId}"]`);
        if (!commentElement) return;
        
        const authorElement = commentElement.querySelector('.comment-author');
        const authorName = authorElement ? authorElement.textContent : 'Unknown User';
        
        this.replyingTo = commentId;
        this.replyingToData = {
            id: commentId,
            authorName: authorName
        };
        
        commentElement.classList.add('replying-to');
        this.showModalReplyIndicator(authorName, commentId);
        
        const input = document.getElementById('modalCommentInput');
        if (input) {
            input.placeholder = `Replying to ${authorName}...`;
            input.focus();
        }
    }

    // Show reply indicator in modal
    showModalReplyIndicator(authorName, commentId) {
        const existingIndicator = document.getElementById('modalReplyIndicator');
        if (existingIndicator) {
            existingIndicator.remove();
        }

        const indicator = document.createElement('div');
        indicator.id = 'modalReplyIndicator';
        indicator.className = 'modal-reply-indicator';
        indicator.innerHTML = `
            <i class='bx bx-reply'></i>
            <span>Replying to <strong>${authorName}</strong></span>
            <button type="button" class="modal-reply-cancel" onclick="window.commentSystem.cancelReply()">
                <i class='bx bx-x'></i>
            </button>
        `;

        const commentForm = document.getElementById('commentsModalCommentForm');
        if (commentForm) {
            commentForm.parentNode.insertBefore(indicator, commentForm);
        }
    }

    // Cancel reply
    cancelReply() {
        if (!this.replyingTo) return;
        
        const replyIndicator = document.getElementById('modalReplyIndicator');
        if (replyIndicator) {
            replyIndicator.remove();
        }
        
        const commentElement = document.querySelector(`#commentsModalCommentsContainer [data-comment-id="${this.replyingTo}"]`);
        if (commentElement) {
            commentElement.classList.remove('replying-to');
        }
        
        const input = document.getElementById('modalCommentInput');
        if (input) {
            input.placeholder = 'Write a comment...';
        }
        
        this.replyingTo = null;
        this.replyingToData = null;
    }

    // Start edit in modal - ONLY if user owns the comment
    startModalEdit(commentId) {
        // Double-check ownership before allowing edit
        const commentElement = document.querySelector(`#commentsModalCommentsContainer [data-comment-id="${commentId}"]`);
        if (!commentElement) return;

        // Find the comment data to verify ownership
        const commentTextElement = commentElement.querySelector(`#commentText${commentId}`);
        if (!commentTextElement) {
            console.error('Cannot edit comment - not owned by current user');
            this.showNotification('You can only edit your own comments', 'error');
            return;
        }

        this.cancelReply();
        this.cancelEdit();
        
        const currentContent = commentTextElement.textContent.trim();
        
        this.currentlyEditing = commentId;
        
        // Show edit indicator
        this.showModalEditIndicator(commentId);
        
        // Set input to edit mode
        const input = document.getElementById('modalCommentInput');
        if (input) {
            input.value = currentContent;
            input.placeholder = 'Edit your comment...';
            input.focus();
            this.autoResizeTextarea(input);
        }
        
        this.toggleSubmitButton();
    }

    // Show edit indicator in modal
    showModalEditIndicator(commentId) {
        const existingIndicator = document.getElementById('modalEditIndicator');
        if (existingIndicator) {
            existingIndicator.remove();
        }

        const indicator = document.createElement('div');
        indicator.id = 'modalEditIndicator';
        indicator.className = 'modal-reply-indicator'; // Reuse styling
        indicator.innerHTML = `
            <i class='bx bx-edit'></i>
            <span>Editing comment</span>
            <button type="button" class="modal-reply-cancel" onclick="window.commentSystem.cancelEdit()">
                <i class='bx bx-x'></i>
            </button>
        `;

        const commentForm = document.getElementById('commentsModalCommentForm');
        if (commentForm) {
            commentForm.parentNode.insertBefore(indicator, commentForm);
        }
    }

    // Cancel edit
    cancelEdit() {
        if (!this.currentlyEditing) return;
        
        const editIndicator = document.getElementById('modalEditIndicator');
        if (editIndicator) {
            editIndicator.remove();
        }
        
        const input = document.getElementById('modalCommentInput');
        if (input) {
            input.value = '';
            input.placeholder = 'Write a comment...';
            this.autoResizeTextarea(input);
        }
        
        this.currentlyEditing = null;
        this.toggleSubmitButton();
    }

    // Submit edit in modal
    async submitModalEdit(content) {
        if (!this.currentlyEditing || !content.trim()) {
            return;
        }

        const submitButton = document.getElementById('modalCommentSubmit');
        const originalHTML = submitButton ? submitButton.innerHTML : '';
        
        if (submitButton) {
            submitButton.innerHTML = '<div class="spinner"></div>';
            submitButton.disabled = true;
        }

        try {
            const response = await fetch('/ODCI/social_feed/api/edit_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    comment_id: this.currentlyEditing,
                    content: content
                })
            });

            const responseText = await response.text();

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${responseText}`);
            }

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Invalid JSON response');
            }

            if (result.success) {
                const input = document.getElementById('modalCommentInput');
                if (input) {
                    input.value = '';
                    this.autoResizeTextarea(input);
                }

                this.cancelEdit();

                // Reload comments to show updated content
                await this.loadCommentsInModal(this.currentModalPostId);
                
                this.showNotification(result.message || 'Comment updated successfully!', 'success');
            } else {
                throw new Error(result.message || 'Failed to update comment');
            }
        } catch (error) {
            console.error('Error updating comment:', error);
            this.showNotification(error.message || 'Error updating comment', 'error');
        } finally {
            if (submitButton) {
                submitButton.innerHTML = originalHTML;
            }
            this.toggleSubmitButton();
        }
    }

    async deleteModalComment(commentId) {
        // Double-check ownership before allowing delete
        const commentElement = document.querySelector(`#commentsModalCommentsContainer [data-comment-id="${commentId}"]`);
        if (!commentElement) return;

        // Verify this comment has edit controls (meaning user owns it)
        const editAction = commentElement.querySelector('.comment-action[onclick*="startModalEdit"]');
        if (!editAction) {
            console.error('Cannot delete comment - not owned by current user');
            this.showNotification('You can only delete your own comments', 'error');
            return;
        }

        if (!confirm('Are you sure you want to delete this comment?')) return;
        
        try {
            const response = await fetch('/ODCI/social_feed/api/delete_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ comment_id: commentId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update comment count in original post
                if (result.post_id) {
                    const currentCount = this.getCurrentCommentCount(result.post_id);
                    this.updateCommentCountFromServer(result.post_id, Math.max(0, currentCount - 1));
                }
                
                if (this.currentModalPostId) {
                    await this.loadCommentsInModal(this.currentModalPostId);
                }
                
                this.showNotification('Comment deleted successfully', 'success');
            } else {
                throw new Error(result.message || 'Failed to delete comment');
            }
        } catch (error) {
            console.error('Error deleting comment:', error);
            this.showNotification(error.message || 'Error deleting comment', 'error');
        }
    }

    async toggleModalCommentLike(commentId) {
        try {
            const response = await fetch('/ODCI/social_feed/api/toggle_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ comment_id: commentId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.updateModalCommentLikeUI(commentId, result.liked, result.like_count || 0);
            } else {
                throw new Error(result.message || 'Failed to toggle like');
            }
        } catch (error) {
            console.error('Error toggling comment like:', error);
            this.showNotification(error.message || 'Error toggling like', 'error');
        }
    }

    updateModalCommentLikeUI(commentId, liked, likeCount) {
        const commentElement = document.querySelector(`#commentsModalCommentsContainer [data-comment-id="${commentId}"]`);
        if (!commentElement) return;

        const likeAction = commentElement.querySelector('.comment-like-btn');
        if (likeAction) {
            const likeText = liked ? 'Unlike' : 'Like';
            const countDisplay = likeCount > 0 ? ` · ${likeCount}` : '';
            likeAction.textContent = `${likeText}${countDisplay}`;
        }
    }

    // Get current comment count from DOM
    getCurrentCommentCount(postId) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (!postElement) return 0;

        const statsDiv = postElement.querySelector('.post-stats');
        if (!statsDiv) return 0;

        const spans = statsDiv.querySelectorAll('span');
        for (let span of spans) {
            if (span.innerHTML.includes('bx-message')) {
                const match = span.textContent.match(/\d+/);
                return match ? parseInt(match[0]) : 0;
            }
        }
        return 0;
    }

    // Keep existing utility and legacy functions
    updateCommentCountFromServer(postId, serverCount) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (!postElement) return;

        const statsDiv = postElement.querySelector('.post-stats');
        if (!statsDiv) return;

        let commentCountElement = null;
        const spans = statsDiv.querySelectorAll('span');
        spans.forEach(span => {
            if (span.innerHTML.includes('bx-message')) {
                commentCountElement = span;
            }
        });

        if (commentCountElement) {
            if (serverCount > 0) {
                commentCountElement.innerHTML = `<i class='bx bx-message'></i> ${serverCount}`;
            } else {
                commentCountElement.remove();
            }
        } else if (serverCount > 0) {
            statsDiv.insertAdjacentHTML('beforeend', `<span><i class='bx bx-message'></i> ${serverCount}</span>`);
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showNotification(message, type = 'info') {
        if (window.utils && window.utils.showNotification) {
            window.utils.showNotification(message, type);
        } else {
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    }

    // Legacy compatibility - redirect to modal
    async toggleComments(postId) {
        await this.openCommentsModal(postId);
    }

    async addComment(postId) {
        await this.openCommentsModal(postId);
    }

    handleCommentKeyPress(event, postId) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.openCommentsModal(postId);
        }
    }

    // Placeholder for compatibility
    startReply() { /* Deprecated - using modal */ }
    submitReplyFromPanel() { return false; }
    startEdit() { /* Deprecated - using modal */ }
    submitEdit() { /* Deprecated - using modal */ }
    deleteComment() { /* Deprecated - using modal */ }
    toggleCommentLike() { /* Deprecated - using modal */ }
    showReplyIndicator() { /* Deprecated - using modal */ }
}

// Create global instance
if (typeof window !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize the comment system with current user ID
        setTimeout(() => {
            window.commentSystem = new CommentSystem(window.socialFeedCore);
            
            // Initialize with current user ID - try multiple methods to get it
            if (window.commentSystem) {
                window.commentSystem.init();
                
                // Additional method: try to get from PHP session if available
                if (!window.commentSystem.currentUserId) {
                    // Try to make a quick AJAX call to get user info if needed
                    window.commentSystem.fetchCurrentUserId();
                }
                
                console.log('CommentSystem ready with user ID:', window.commentSystem.currentUserId);
            }
        }, 100);
    });
}