// post-actions.js - Simplified without multiple reactions
// Module for handling post interactions (like, edit, delete, etc.)

class PostActions {
    constructor(core) {
        this.core = core;
    }

    // Toggle post menu
    togglePostMenu(postId) {
        const menu = document.getElementById(`postMenu${postId}`);
        if (!menu) return;

        // Close other menus
        document.querySelectorAll('.post-menu').forEach(m => {
            if (m.id !== `postMenu${postId}`) m.classList.remove('show');
        });
        
        menu.classList.toggle('show');
    }

    // Edit post
    async editPost(postId) {
        try {
            const response = await fetch(`api/get_post.php?id=${postId}`);
            const data = await response.json();
            
            if (data.success) {
                const post = data.post;
                const newContent = prompt('Edit your post:', post.content);
                
                if (newContent && newContent.trim() !== post.content) {
                    await this.updatePost(postId, newContent.trim());
                }
            } else {
                window.utils.showNotification(data.message || 'Error loading post', 'error');
            }
        } catch (error) {
            console.error('Error editing post:', error);
            window.utils.showNotification('Error editing post', 'error');
        }
    }

    // Update post content
    async updatePost(postId, newContent) {
        try {
            const updateResponse = await fetch('api/update_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    id: postId, 
                    content: newContent 
                })
            });
            
            const updateResult = await updateResponse.json();
            
            if (updateResult.success) {
                this.updatePostUI(postId, newContent);
                window.utils.showNotification('Post updated successfully!', 'success');
            } else {
                window.utils.showNotification(updateResult.message || 'Error updating post', 'error');
            }
        } catch (error) {
            console.error('Error updating post:', error);
            window.utils.showNotification('Error updating post', 'error');
        }
    }

    // Update post UI after edit
    updatePostUI(postId, newContent) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (!postElement) return;

        const contentElement = postElement.querySelector('.post-content');
        if (contentElement) {
            contentElement.textContent = newContent;
        }
        
        // Add edited indicator if not already present
        const metaElement = postElement.querySelector('.post-meta');
        if (metaElement && !postElement.querySelector('.edited-indicator')) {
            metaElement.innerHTML += ' <span class="edited-indicator" style="color: var(--text-secondary); font-size: 12px;">(edited)</span>';
        }
    }

    // Delete post
    async deletePost(postId) {
        if (!confirm('Are you sure you want to delete this post?')) return;

        try {
            const response = await fetch('api/delete_post.php', {
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

    // Remove post from UI
    removePostFromUI(postId) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (postElement) {
            postElement.remove();
        }
    }

    // Toggle like on post - simplified to just like/unlike
    async toggleLike(postId) {
        try {
            const response = await fetch('api/toggle_like.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.updatePostLikeUI(postId, result.liked, result.like_count);
            }
        } catch (error) {
            console.error('Error toggling like:', error);
        }
    }

    // Update post like UI
    updatePostLikeUI(postId, liked, likeCount) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (!postElement) return;
        
        const likeButton = postElement.querySelector('.post-action');
        const likeIcon = likeButton?.querySelector('i');
        const likeText = likeButton?.querySelector('span');
        const statsDiv = postElement.querySelector('.post-stats');
        
        // Update like button
        if (likeButton && likeIcon && likeText) {
            if (liked) {
                likeButton.classList.add('liked');
                likeIcon.className = 'bx bxs-heart';
                likeText.textContent = 'Liked';
            } else {
                likeButton.classList.remove('liked');
                likeIcon.className = 'bx bx-heart';
                likeText.textContent = 'Like';
            }
        }
        
        // Update like count in stats
        if (statsDiv) {
            this.updateLikeCountInStats(statsDiv, likeCount);
        }
    }

    // Update like count in stats section
    updateLikeCountInStats(statsDiv, likeCount) {
        let likeCountElement = statsDiv.querySelector('span:first-child');
        
        if (likeCount > 0) {
            if (likeCountElement && likeCountElement.innerHTML.includes('bx-heart')) {
                likeCountElement.innerHTML = `<i class='bx bx-heart'></i> ${likeCount}`;
            } else if (!likeCountElement) {
                statsDiv.insertAdjacentHTML('afterbegin', `<span><i class='bx bx-heart'></i> ${likeCount}</span>`);
            }
        } else {
            if (likeCountElement && likeCountElement.innerHTML.includes('bx-heart')) {
                likeCountElement.remove();
            }
        }
    }
}

// Create global instance
window.postActions = new PostActions(window.socialFeedCore);