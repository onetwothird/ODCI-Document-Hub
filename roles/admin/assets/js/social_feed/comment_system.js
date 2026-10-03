// Updated comment_system.js - Modal-based comments like Facebook

class CommentSystem {
    constructor(core) {
        this.core = core;
        this.currentlyEditing = null;
        this.replyingTo = null;
        this.replyingToData = null;
        this.currentModalPostId = null;
        this.modalOverlay = null;
        this.modal = null;
    }

    // Main function to open comments modal (replaces toggleComments)
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
        
        // Show modal
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
            if (e.key === 'Escape' && overlay.classList.contains('show')) {
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

        // Re-bind like functionality for the modal post
        const likeBtn = modalPost.querySelector('.post-action[onclick*="toggleLike"]');
        if (likeBtn) {
            likeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const postId = this.currentModalPostId;
                if (postId && window.postActions) {
                    window.postActions.toggleLike(postId);
                }
            });
        }

        // Update user avatar in comment form
        const userAvatar = document.getElementById('composerAvatar');
        const modalAvatar = document.getElementById('modalCommentAvatar');
        if (userAvatar && modalAvatar) {
            modalAvatar.src = userAvatar.src;
        }
    }

    // Show modal with animation
    showModal() {
        if (!this.modalOverlay) return;
        
        this.modalOverlay.style.display = 'flex';
        // Force reflow
        this.modalOverlay.offsetHeight;
        this.modalOverlay.classList.add('show');
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Focus comment input
        setTimeout(() => {
            const input = document.getElementById('modalCommentInput');
            if (input) input.focus();
        }, 300);
    }

    // Close modal
    closeModal() {
        if (!this.modalOverlay) return;
        
        this.modalOverlay.classList.remove('show');
        
        setTimeout(() => {
            this.modalOverlay.style.display = 'none';
            // Restore body scroll
            document.body.style.overflow = '';
            
            // Clean up
            this.currentModalPostId = null;
            this.cancelReply();
            this.cancelEdit();
            
            // Clear input
            const input = document.getElementById('modalCommentInput');
            if (input) {
                input.value = '';
                this.autoResizeTextarea(input);
            }
            this.toggleSubmitButton();
            
        }, 300);
    }

    // Load comments in modal
    async loadCommentsInModal(postId) {
        const loadingDiv = document.getElementById('commentsModalLoading');
        const container = document.getElementById('commentsModalCommentsContainer');

        if (loadingDiv) loadingDiv.style.display = 'block';
        if (container) container.innerHTML = '';

        try {
            const response = await fetch(`api/get_comments.php?post_id=${postId}&limit=50`);

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

    // Create comment element for modal
    createModalCommentElement(comment, isReply = false, parentAuthor = null) {
        const commentDiv = document.createElement('div');
        commentDiv.className = 'modal-comment' + (isReply ? ' modal-comment-reply' : '');
        commentDiv.dataset.commentId = comment.id;

        const timeAgo = window.utils ? window.utils.getTimeAgo(comment.created_at) : 'recently';
        const editedText = comment.is_edited ?
            '<span style="color: var(--text-secondary); font-size: 11px; margin-left: 8px;">(edited)</span>' : '';

        // Create action buttons
        const actions = [];
        actions.push(`<span class="comment-action" onclick="window.commentSystem.startModalReply(${comment.id})">Reply</span>`);

        if (window.socialFeedCore && window.socialFeedCore.canEditComment(comment)) {
            actions.push(`<span class="comment-action" onclick="window.commentSystem.startModalEdit(${comment.id})">Edit</span>`);
            actions.push(`<span class="comment-action" onclick="window.commentSystem.deleteModalComment(${comment.id})">Delete</span>`);
        }

        const likeText = comment.user_liked ? 'Unlike' : 'Like';
        const likeCount = comment.like_count > 0 ? ` · ${comment.like_count}` : '';
        actions.push(`<span class="comment-action" onclick="window.commentSystem.toggleModalCommentLike(${comment.id})">${likeText}${likeCount}</span>`);

        commentDiv.innerHTML = `
            <img src="${comment.profile_image || 'assets/img/default-avatar.png'}" 
                alt="${comment.commenter_full_name}" class="comment-avatar">
            <div class="comment-content">
                <div class="comment-header">
                    <span class="comment-author">${comment.commenter_full_name}</span>
                    <span class="comment-time">${timeAgo}</span>
                    ${editedText}
                </div>
                <div class="comment-text">${window.utils ? window.utils.escapeHtml(comment.content) : comment.content}</div>
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
                const success = await this.submitReplyFromModal(this.currentModalPostId, content);
                if (success) {
                    input.value = '';
                    this.autoResizeTextarea(input);
                    await this.loadCommentsInModal(this.currentModalPostId);
                }
                return;
            }

            const response = await fetch('api/add_comment.php', {
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

    // Submit reply from modal
    async submitReplyFromModal(postId, content) {
        if (!this.replyingTo || !content.trim()) {
            return false;
        }

        try {
            const response = await fetch('api/add_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    post_id: postId,
                    content: content,
                    parent_comment_id: this.replyingTo
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
                if (result.actual_comment_count !== undefined) {
                    this.updateCommentCountFromServer(postId, result.actual_comment_count);
                }

                this.cancelReply();
                this.showNotification(result.message || 'Reply added successfully!', 'success');
                return true;
            } else {
                throw new Error(result.message || 'Failed to add reply');
            }
        } catch (error) {
            console.error('Error adding reply:', error);
            this.showNotification(error.message || 'Error adding reply', 'error');
            return false;
        }
    }

    // Modal comment editing, deletion, and like functionality
    startModalEdit(commentId) {
        // Implementation similar to existing edit functionality
        console.log('Modal edit functionality - to be implemented');
    }

    async deleteModalComment(commentId) {
        if (!confirm('Are you sure you want to delete this comment?')) return;
        
        try {
            const response = await fetch('api/delete_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ comment_id: commentId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (result.post_id) {
                    this.updateCommentCountFromServer(result.post_id, result.new_count || 0);
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
            const response = await fetch('api/toggle_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ comment_id: commentId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.updateModalCommentLikeUI(commentId, result.liked, result.like_count);
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

        const likeAction = commentElement.querySelector('.comment-actions span:last-child');
        if (likeAction) {
            const likeText = liked ? 'Unlike' : 'Like';
            const countDisplay = likeCount > 0 ? ` · ${likeCount}` : '';
            likeAction.textContent = `${likeText}${countDisplay}`;
        }
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
            commentCountElement.innerHTML = `<i class='bx bx-message'></i> ${serverCount}`;
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

    // Placeholder for side panel functions to prevent errors
    startReply() { /* Deprecated - using modal */ }
    cancelEdit() { /* Keep for compatibility */ }
    submitReplyFromPanel() { return false; }
    startEdit() { /* Deprecated - using modal */ }
    submitEdit() { /* Deprecated - using modal */ }
    deleteComment() { /* Deprecated - using modal */ }
    toggleCommentLike() { /* Deprecated - using modal */ }
    showReplyIndicator() { /* Deprecated - using modal */ }
}

// Create global instance
if (typeof window !== 'undefined') {
    if (window.socialFeedCore) {
        window.commentSystem = new CommentSystem(window.socialFeedCore);
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.commentSystem = new CommentSystem(window.socialFeedCore);
            }, 100);
        });
    }
}