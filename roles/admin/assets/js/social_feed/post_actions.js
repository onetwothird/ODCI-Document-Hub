// admin-post-actions.js
// Module for handling admin post interactions

class AdminPostActions extends PostActions {
    constructor(core) {
        super(core);
    }

    // Delete post - Admin can delete any post
    async deletePost(postId) {
        if (!confirm('Are you sure you want to delete this post? This action cannot be undone.')) return;

        try {
            const response = await fetch('api/admin_delete_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: postId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.removePostFromUI(postId);
                window.utils.showNotification('Post deleted successfully!', 'success');
            } else {
                window.utils.showNotification(result.message || 'Error deleting post', 'error');
            }
        } catch (error) {
            console.error('Error deleting post:', error);
            window.utils.showNotification('Error deleting post', 'error');
        }
    }

    // Moderate post (hide/show)
    async moderatePost(postId, action) {
        const reason = action === 'hide' ? 
            prompt('Please provide a reason for hiding this post:') : 
            null;

        if (action === 'hide' && !reason) return;

        try {
            const response = await fetch('api/moderate_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    post_id: postId, 
                    action: action,
                    reason: reason 
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.updatePostModerationUI(postId, action);
                window.utils.showNotification(`Post ${action} successfully!`, 'success');
            } else {
                window.utils.showNotification(result.message || `Error ${action} post`, 'error');
            }
        } catch (error) {
            console.error(`Error ${action} post:`, error);
            window.utils.showNotification(`Error ${action} post`, 'error');
        }
    }

    // Update post UI after moderation
    updatePostModerationUI(postId, action) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (!postElement) return;

        if (action === 'hide') {
            postElement.style.opacity = '0.6';
            postElement.style.backgroundColor = '#fff3f3';
            postElement.querySelector('.post-admin-controls').innerHTML += 
                '<span class="moderation-badge">HIDDEN</span>';
        } else if (action === 'show') {
            postElement.style.opacity = '1';
            postElement.style.backgroundColor = '';
            const badge = postElement.querySelector('.moderation-badge');
            if (badge) badge.remove();
        }
    }

    // View post details
    async viewPostDetails(postId) {
        try {
            const response = await fetch(`api/get_post_details.php?id=${postId}`);
            const data = await response.json();
            
            if (data.success) {
                this.showPostDetailsModal(data.post);
            } else {
                window.utils.showNotification(data.message || 'Error loading post details', 'error');
            }
        } catch (error) {
            console.error('Error loading post details:', error);
            window.utils.showNotification('Error loading post details', 'error');
        }
    }

    // Show post details modal
    showPostDetailsModal(post) {
        const modalHtml = `
            <div class="modal" id="postDetailsModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Post Details</h3>
                        <span class="close" onclick="closeModal('postDetailsModal')">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="post-detail">
                            <strong>Author:</strong> ${post.author_full_name}
                        </div>
                        <div class="post-detail">
                            <strong>Department:</strong> ${post.department_code}
                        </div>
                        <div class="post-detail">
                            <strong>Created:</strong> ${new Date(post.created_at).toLocaleString()}
                        </div>
                        <div class="post-detail">
                            <strong>Visibility:</strong> ${post.visibility}
                        </div>
                        <div class="post-detail">
                            <strong>Likes:</strong> ${post.like_count}
                        </div>
                        <div class="post-detail">
                            <strong>Comments:</strong> ${post.comment_count}
                        </div>
                        <div class="post-detail">
                            <strong>Views:</strong> ${post.view_count}
                        </div>
                        ${post.is_hidden ? `
                        <div class="post-detail">
                            <strong>Status:</strong> <span style="color: red;">HIDDEN</span>
                        </div>
                        <div class="post-detail">
                            <strong>Moderation Reason:</strong> ${post.moderation_reason || 'N/A'}
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;

        // Add modal to document
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal
        document.getElementById('postDetailsModal').style.display = 'block';
        
        // Add click event to close when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('postDetailsModal');
            if (event.target === modal) {
                modal.remove();
            }
        };
    }
}

// Create global instance
window.postActions = new AdminPostActions(window.socialFeedCore);

// Global functions for HTML onclick handlers
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

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
}