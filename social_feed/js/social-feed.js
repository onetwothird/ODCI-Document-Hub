// Global variables
let currentFilter = 'all';
let currentPage = 0;
let isLoading = false;
let hasMorePosts = true;
let currentUser = null;

// Initialize the feed
document.addEventListener('DOMContentLoaded', function() {
    initializeFeed();
    setupEventListeners();
    loadUserInfo();
});

// Load user information
async function loadUserInfo() {
    try {
        const response = await fetch('api/get_user_info.php');
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            updateUserInterface();
        }
    } catch (error) {
        console.error('Error loading user info:', error);
    }
}

// Update user interface with user data
function updateUserInterface() {
    if (currentUser) {
        const composerName = document.getElementById('composerName');
        const composerAvatar = document.getElementById('composerAvatar');
        const userAvatar = document.getElementById('userAvatar');
        
        if (composerName) composerName.textContent = currentUser.full_name;
        if (composerAvatar) composerAvatar.src = currentUser.profile_image || 'assets/img/default-avatar.png';
        if (userAvatar) userAvatar.src = currentUser.profile_image || 'assets/img/default-avatar.png';
    }
}

// Initialize feed
function initializeFeed() {
    loadPosts();
    setInterval(checkNotifications, 30000); // Check notifications every 30 seconds
}

// Setup event listeners
function setupEventListeners() {
    // Tab switching
    document.querySelectorAll('.feed-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            switchTab(this.dataset.filter);
        });
    });

    // Visibility selector
    const visibilityBtn = document.getElementById('visibilityBtn');
    if (visibilityBtn) {
        visibilityBtn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleVisibilityDropdown();
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.visibility-selector')) {
            const dropdown = document.getElementById('visibilityDropdown');
            if (dropdown) dropdown.classList.remove('show');
        }
        
        // Close post menus
        if (!e.target.closest('.post-actions-menu')) {
            document.querySelectorAll('.post-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });

    // Character count for post content
    const postContent = document.getElementById('postContent');
    if (postContent) {
        postContent.addEventListener('input', function() {
            const length = this.value.length;
            const button = document.getElementById('postBtn');
            if (button) button.disabled = length === 0;
        });
    }
}

// Switch tabs
function switchTab(filter) {
    document.querySelectorAll('.feed-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    const activeTab = document.querySelector(`[data-filter="${filter}"]`);
    if (activeTab) activeTab.classList.add('active');
    
    currentFilter = filter;
    currentPage = 0;
    hasMorePosts = true;
    
    const feedContent = document.getElementById('feedContent');
    if (feedContent) {
        feedContent.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading posts...</p></div>';
    }
    
    loadPosts();
}

// Toggle visibility dropdown
function toggleVisibilityDropdown() {
    const dropdown = document.getElementById('visibilityDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
        
        // Setup visibility option handlers
        document.querySelectorAll('.visibility-option').forEach(option => {
            option.onclick = function() {
                selectVisibility(this.dataset.visibility);
            };
        });
    }
}

// Select visibility option
function selectVisibility(visibility) {
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

// Create new post
async function createPost(event) {
    event.preventDefault();
    
    const contentInput = document.getElementById('postContent');
    if (!contentInput) return;
    
    const content = contentInput.value.trim();
    if (!content) {
        showNotification('Please enter some content for your post', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('content', content);
    
    const visibilityInput = document.getElementById('postVisibility');
    const priorityInput = document.getElementById('postPriority');
    
    formData.append('visibility', visibilityInput ? visibilityInput.value : 'public');
    formData.append('priority', priorityInput ? priorityInput.value : 'normal');
    
    // Handle image upload
    const imageFile = document.getElementById('imageInput');
    if (imageFile && imageFile.files[0]) {
        formData.append('image', imageFile.files[0]);
    }
    
    // Handle file attachment
    const attachFile = document.getElementById('fileInput');
    if (attachFile && attachFile.files[0]) {
        formData.append('file', attachFile.files[0]);
    }
    
    try {
        const button = document.getElementById('postBtn');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<div class="spinner"></div> Posting...';
        }
        
        const response = await fetch('api/create_post.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reset form
            contentInput.value = '';
            if (imageFile) imageFile.value = '';
            if (attachFile) attachFile.value = '';
            
            // Remove any image previews
            const existingPreviews = document.querySelectorAll('.image-preview');
            existingPreviews.forEach(preview => preview.remove());
            
            // Refresh the feed
            await refreshFeed();
            
            showNotification('Post created successfully!', 'success');
        } else {
            showNotification(result.message || 'Error creating post', 'error');
        }
    } catch (error) {
        console.error('Error creating post:', error);
        showNotification('Error creating post', 'error');
    } finally {
        const button = document.getElementById('postBtn');
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="bx bx-send"></i> Post';
        }
    }
}

// Load posts
async function loadPosts(page = 0) {
    if (isLoading) return;
    
    isLoading = true;
    
    try {
        const response = await fetch(`api/get_posts.php?filter=${currentFilter}&page=${page}&limit=10`);
        const data = await response.json();
        
        if (data.success) {
            const feedContent = document.getElementById('feedContent');
            
            if (page === 0) {
                feedContent.innerHTML = '';
            }
            
            if (data.posts.length === 0 && page === 0) {
                feedContent.innerHTML = `
                    <div class="no-posts">
                        <i class='bx bx-message-dots'></i>
                        <h3>No posts yet</h3>
                        <p>Be the first to share something with your colleagues!</p>
                    </div>
                `;
                hasMorePosts = false;
            } else {
                data.posts.forEach(post => {
                    feedContent.appendChild(createPostElement(post));
                });
                
                hasMorePosts = data.posts.length === 10;
                currentPage = page;
            }
            
            // Show/hide load more button
            const loadMoreSection = document.getElementById('loadMoreSection');
            if (loadMoreSection) {
                loadMoreSection.style.display = hasMorePosts ? 'block' : 'none';
            }
        } else {
            throw new Error(data.message || 'Failed to load posts');
        }
    } catch (error) {
        console.error('Error loading posts:', error);
        const feedContent = document.getElementById('feedContent');
        if (feedContent) {
            feedContent.innerHTML = `
                <div class="no-posts">
                    <i class='bx bx-error'></i>
                    <h3>Error loading posts</h3>
                    <p>Please try again later</p>
                </div>
            `;
        }
    } finally {
        isLoading = false;
    }
}

async function refreshFeed() {
    const feedContent = document.getElementById('feedContent');
    if (feedContent) {
        feedContent.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading posts...</p></div>';
    }
    
    currentPage = 0;
    hasMorePosts = true;
    await loadPosts();
}
        
// Create post element
function createPostElement(post) {
    const postDiv = document.createElement('div');
    postDiv.className = 'post';
    postDiv.dataset.postId = post.id;
    
    const timeAgo = getTimeAgo(post.created_at);
    const priorityBadge = post.priority !== 'normal' ? 
        `<span class="post-badge ${post.priority}">${post.priority.toUpperCase()}</span>` : '';
    const visibilityBadge = post.visibility !== 'public' ? 
        `<span class="post-badge ${post.visibility}">${post.visibility.toUpperCase()}</span>` : '';
    const editedText = post.is_edited ? 
        '<span style="color: var(--text-secondary); font-size: 12px;">(edited)</span>' : '';

    postDiv.innerHTML = `
        <div class="post-header">
            <img src="${post.profile_image || 'assets/img/default-avatar.png'}" alt="${post.author_full_name}" class="post-avatar">
            <div class="post-author-info">
                <div class="post-author-name">${post.author_full_name}</div>
                <div class="post-meta">
                    <span>${post.position || 'Staff'}</span>
                    <span>•</span>
                    <span>${post.department_code || 'N/A'}</span>
                    <span>•</span>
                    <span>${timeAgo}</span>
                    ${editedText}
                    ${priorityBadge}
                    ${visibilityBadge}
                </div>
            </div>
            ${canEditPost(post) ? `
                <div class="post-actions-menu">
                    <button class="post-menu-btn" onclick="togglePostMenu(${post.id})">
                        <i class='bx bx-dots-horizontal-rounded'></i>
                    </button>
                    <div class="post-menu" id="postMenu${post.id}">
                        <div class="post-menu-item" onclick="editPost(${post.id})">
                            <i class='bx bx-edit'></i> Edit
                        </div>
                        <div class="post-menu-item danger" onclick="deletePost(${post.id})">
                            <i class='bx bx-trash'></i> Delete
                        </div>
                    </div>
                </div>
            ` : ''}
        </div>
        
        <div class="post-content">${escapeHtml(post.content)}</div>
        
        ${createPostMediaHTML(post)}
        
        <div class="post-engagement">
            <div class="post-stats">
                ${post.like_count > 0 ? `<span><i class='bx bx-heart'></i> ${post.like_count}</span>` : ''}
                ${post.comment_count > 0 ? `<span><i class='bx bx-message'></i> ${post.comment_count}</span>` : ''}
                ${post.view_count > 0 ? `<span><i class='bx bx-show'></i> ${post.view_count}</span>` : ''}
            </div>
        </div>
        
        <div class="post-actions">
            <div style="position: relative;">
                <button class="post-action ${post.user_liked ? 'liked' : ''}" 
                        onmouseenter="showReactionPicker(${post.id})" 
                        onmouseleave="hideReactionPicker(${post.id})"
                        onclick="toggleLike(${post.id})">
                    <i class='bx ${post.user_liked ? 'bxs-heart' : 'bx-heart'}'></i>
                    <span>${post.user_liked ? 'Liked' : 'Like'}</span>
                </button>
                <div class="reaction-picker" id="reactionPicker${post.id}">
                    <button class="reaction-option" onclick="reactToPost(${post.id}, 'like')" title="Like">❤️</button>
                    <button class="reaction-option" onclick="reactToPost(${post.id}, 'love')" title="Love">😍</button>
                    <button class="reaction-option" onclick="reactToPost(${post.id}, 'laugh')" title="Laugh">😂</button>
                    <button class="reaction-option" onclick="reactToPost(${post.id}, 'wow')" title="Wow">😮</button>
                    <button class="reaction-option" onclick="reactToPost(${post.id}, 'sad')" title="Sad">😢</button>
                    <button class="reaction-option" onclick="reactToPost(${post.id}, 'angry')" title="Angry">😠</button>
                </div>
            </div>
            
            <button class="post-action" onclick="toggleComments(${post.id})">
                <i class='bx bx-message'></i>
                <span>Comment</span>
            </button>
            
            <button class="post-action" onclick="sharePost(${post.id})">
                <i class='bx bx-share'></i>
                <span>Share</span>
            </button>
        </div>
        
        <div class="comment-section" id="comments${post.id}" style="display: none;">
            <div class="comments-loading" id="commentsLoading${post.id}" style="display: none;">
                <div class="spinner"></div>
            </div>
            <div class="comments-container" id="commentsContainer${post.id}">
                <!-- Comments will be loaded here -->
            </div>
            <div class="comment-form">
                <img src="${currentUser?.profile_image || 'assets/img/default-avatar.png'}" alt="Your avatar" class="comment-avatar">
                <input type="text" class="comment-input" placeholder="Write a comment..." 
                       onkeypress="handleCommentKeyPress(event, ${post.id})">
                <button class="comment-submit" onclick="addComment(${post.id})">
                    <i class='bx bx-send'></i>
                </button>
            </div>
        </div>
    `;
    
    return postDiv;
}

// Create post media HTML
function createPostMediaHTML(post) {
    if (!post.media || post.media.length === 0) return '';
    
    let mediaHTML = '<div class="post-media">';
    
    post.media.forEach(media => {
        switch(media.media_type) {
            case 'image':
                mediaHTML += `
                    <img src="${media.file_path}" alt="Post image" class="post-image" 
                         onclick="openImageModal('${media.file_path}')">
                `;
                break;
            case 'file':
                mediaHTML += `
                    <div class="post-file" onclick="downloadFile('${media.file_path}', '${media.original_name}')">
                        <i class='bx bx-file post-file-icon'></i>
                        <div class="post-file-info">
                            <div class="post-file-name">${media.original_name}</div>
                            <div class="post-file-size">${formatFileSize(media.file_size)}</div>
                        </div>
                        <i class='bx bx-download'></i>
                    </div>
                `;
                break;
            case 'link':
                mediaHTML += `
                    <div class="post-link-preview" onclick="openLink('${media.url}')">
                        ${media.url_image ? `<img src="${media.url_image}" alt="Link preview" class="post-link-image">` : ''}
                        <div class="post-link-content">
                            <div class="post-link-title">${media.url_title || media.url}</div>
                            ${media.url_description ? `<div class="post-link-description">${media.url_description}</div>` : ''}
                        </div>
                    </div>
                `;
                break;
        }
    });
    
    mediaHTML += '</div>';
    return mediaHTML;
}

// Check if user can edit post
function canEditPost(post) {
    return currentUser && (currentUser.id == post.user_id || ['admin', 'super_admin'].includes(currentUser.role));
}

// Toggle post menu
function togglePostMenu(postId) {
    const menu = document.getElementById(`postMenu${postId}`);
    if (menu) {
        document.querySelectorAll('.post-menu').forEach(m => {
            if (m.id !== `postMenu${postId}`) m.classList.remove('show');
        });
        menu.classList.toggle('show');
    }
}

// Edit post
async function editPost(postId) {
    try {
        const response = await fetch(`api/get_post.php?id=${postId}`);
        const data = await response.json();
        
        if (data.success) {
            const post = data.post;
            const newContent = prompt('Edit your post:', post.content);
            
            if (newContent && newContent.trim() !== post.content) {
                const updateResponse = await fetch('api/update_post.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        id: postId, 
                        content: newContent.trim() 
                    })
                });
                
                const updateResult = await updateResponse.json();
                
                if (updateResult.success) {
                    // Update UI directly without full reload
                    const postElement = document.querySelector(`[data-post-id="${postId}"]`);
                    if (postElement) {
                        const contentElement = postElement.querySelector('.post-content');
                        if (contentElement) {
                            contentElement.textContent = newContent.trim();
                        }
                        
                        // Add edited indicator if not already present
                        const metaElement = postElement.querySelector('.post-meta');
                        if (metaElement && !postElement.querySelector('.edited-indicator')) {
                            metaElement.innerHTML += ' <span class="edited-indicator" style="color: var(--text-secondary); font-size: 12px;">(edited)</span>';
                        }
                    }
                    
                    showNotification('Post updated successfully!', 'success');
                } else {
                    showNotification(updateResult.message || 'Error updating post', 'error');
                }
            }
        } else {
            showNotification(data.message || 'Error loading post', 'error');
        }
    } catch (error) {
        console.error('Error editing post:', error);
        showNotification('Error editing post', 'error');
    }
}

// Delete post
async function deletePost(postId) {
    if (confirm('Are you sure you want to delete this post?')) {
        try {
            const response = await fetch('api/delete_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: postId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Remove post from DOM
                const postElement = document.querySelector(`[data-post-id="${postId}"]`);
                if (postElement) {
                    postElement.remove();
                }
                showNotification('Post deleted successfully!', 'success');
            } else {
                showNotification(result.message || 'Error deleting post', 'error');
            }
        } catch (error) {
            console.error('Error deleting post:', error);
            showNotification('Error deleting post', 'error');
        }
    }
}

// Toggle like
async function toggleLike(postId) {
    try {
        const response = await fetch('api/toggle_like.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update UI
            updatePostLikeUI(postId, result.liked, result.like_count);
        }
    } catch (error) {
        console.error('Error toggling like:', error);
    }
}

// React to post
async function reactToPost(postId, reactionType) {
    hideReactionPicker(postId);
    
    try {
        const response = await fetch('api/toggle_like.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId, reaction_type: reactionType })
        });
        
        const result = await response.json();
        
        if (result.success) {
            updatePostLikeUI(postId, result.reacted || result.liked, result.like_count);
        }
    } catch (error) {
        console.error('Error reacting to post:', error);
    }
}

// Show reaction picker
function showReactionPicker(postId) {
    const picker = document.getElementById(`reactionPicker${postId}`);
    if (picker) {
        picker.classList.add('show');
    }
}

// Hide reaction picker
function hideReactionPicker(postId) {
    setTimeout(() => {
        const picker = document.getElementById(`reactionPicker${postId}`);
        if (picker && !picker.matches(':hover')) {
            picker.classList.remove('show');
        }
    }, 100);
}

// Update post like UI
function updatePostLikeUI(postId, liked, likeCount) {
    const postElement = document.querySelector(`[data-post-id="${postId}"]`);
    if (!postElement) return;
    
    const likeButton = postElement.querySelector('.post-action');
    const likeIcon = likeButton?.querySelector('i');
    const likeText = likeButton?.querySelector('span');
    const statsDiv = postElement.querySelector('.post-stats');
    
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

// Toggle comments
async function toggleComments(postId) {
    const commentsSection = document.getElementById(`comments${postId}`);
    
    if (commentsSection) {
        if (commentsSection.style.display === 'none') {
            commentsSection.style.display = 'block';
            await loadComments(postId);
        } else {
            commentsSection.style.display = 'none';
        }
    }
}

// Load comments
async function loadComments(postId) {
    const loadingDiv = document.getElementById(`commentsLoading${postId}`);
    const container = document.getElementById(`commentsContainer${postId}`);
    
    if (loadingDiv) loadingDiv.style.display = 'block';
    
    try {
        const response = await fetch(`api/get_comments.php?post_id=${postId}`);
        const data = await response.json();
        
        if (data.success && container) {
            container.innerHTML = '';
            
            if (data.comments.length === 0) {
                container.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 20px;">No comments yet</p>';
            } else {
                data.comments.forEach(comment => {
                    container.appendChild(createCommentElement(comment));
                });
            }
            
            // Update comment count with server data if available
            if (data.total_comments !== undefined) {
                updateCommentCount(postId, data.total_comments);
            }
        } else {
            if (container) {
                container.innerHTML = '<p style="color: var(--red-primary); text-align: center; padding: 20px;">Error loading comments</p>';
            }
        }
    } catch (error) {
        console.error('Error loading comments:', error);
        if (container) {
            container.innerHTML = '<p style="color: var(--red-primary); text-align: center; padding: 20px;">Error loading comments</p>';
        }
    } finally {
        if (loadingDiv) loadingDiv.style.display = 'none';
    }
}

function updateCommentCount(postId, commentCount) {
    const postElement = document.querySelector(`[data-post-id="${postId}"]`);
    if (!postElement) return;

    const statsDiv = postElement.querySelector('.post-stats');
    if (!statsDiv) return;

    let commentCountElement = null;
    
    // Find existing comment count element
    const spans = statsDiv.querySelectorAll('span');
    spans.forEach(span => {
        if (span.innerHTML.includes('bx-message')) {
            commentCountElement = span;
        }
    });
    
    // Update or create comment count element
    if (commentCount > 0) {
        if (commentCountElement) {
            commentCountElement.innerHTML = `<i class='bx bx-message'></i> ${commentCount}`;
        } else {
            statsDiv.insertAdjacentHTML('beforeend', `<span><i class='bx bx-message'></i> ${commentCount}</span>`);
        }
    } else {
        // Remove comment count if no comments
        if (commentCountElement) {
            commentCountElement.remove();
        }
    }
}


// Create comment element
function createCommentElement(comment) {
    const commentDiv = document.createElement('div');
    commentDiv.className = 'comment';
    commentDiv.dataset.commentId = comment.id;
    
    const timeAgo = getTimeAgo(comment.created_at);
    const editedText = comment.is_edited ? '<span style="color: var(--text-secondary); font-size: 11px;">(edited)</span>' : '';
    
    commentDiv.innerHTML = `
        <img src="${comment.profile_image || 'assets/img/default-avatar.png'}" alt="${comment.commenter_full_name}" class="comment-avatar">
        <div class="comment-content">
            <div class="comment-header">
                <span class="comment-author">${comment.commenter_full_name}</span>
                <span class="comment-time">${timeAgo}</span>
                ${editedText}
            </div>
            <div class="comment-text">${escapeHtml(comment.content)}</div>
            <div class="comment-actions">
                <span class="comment-action" onclick="replyToComment(${comment.id})">Reply</span>
                ${canEditComment(comment) ? `
                    <span class="comment-action" onclick="editComment(${comment.id})">Edit</span>
                    <span class="comment-action" onclick="deleteComment(${comment.id})">Delete</span>
                ` : ''}
                <span class="comment-action" onclick="toggleCommentLike(${comment.id})">
                    ${comment.user_liked ? 'Unlike' : 'Like'}
                    ${comment.like_count > 0 ? `(${comment.like_count})` : ''}
                </span>
            </div>
        </div>
    `;
    
    return commentDiv;
}

// Add comment
async function addComment(postId) {
    const input = document.querySelector(`#comments${postId} .comment-input`);
    if (!input) return;
    
    const content = input.value.trim();
    
    if (!content) return;
    
    try {
        const response = await fetch('api/add_comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId, content: content })
        });
        
        const result = await response.json();
        
        if (result.success) {
            input.value = '';
            // Just reload comments - this will get the correct count from server
            await loadComments(postId);
            // Don't manually increment the count here - let the server handle it
        } else {
            showNotification(result.message || 'Error adding comment', 'error');
        }
    } catch (error) {
        console.error('Error adding comment:', error);
        showNotification('Error adding comment', 'error');
    }
}

// Handle comment keypress
function handleCommentKeyPress(event, postId) {
    if (event.key === 'Enter') {
        addComment(postId);
    }
}

// Check if user can edit comment
function canEditComment(comment) {
    return currentUser && (currentUser.id == comment.user_id || ['admin', 'super_admin'].includes(currentUser.role));
}

// Load more posts
function loadMorePosts() {
    loadPosts(currentPage + 1);
}

// Search posts
function searchPosts(event) {
    event.preventDefault();
    const query = document.getElementById('searchInput')?.value.trim();
    
    if (query) {
        // Implement search functionality
        console.log('Searching for:', query);
        // You can add search API call here
    }
}

// Check notifications
async function checkNotifications() {
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

// Utility functions
function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
    
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 1000;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        max-width: 300px;
        word-wrap: break-word;
    `;
    notification.textContent = message;
    
    // Add animation keyframes if not already added
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            @keyframes slideInRight {
                from { opacity: 0; transform: translateX(100%); }
                to { opacity: 1; transform: translateX(0); }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// File upload handlers
function handleImageUpload(input) {
    const file = input.files[0];
    if (file) {
        // Remove existing preview
        const existingPreview = document.querySelector('.image-preview');
        if (existingPreview) {
            existingPreview.remove();
        }
        
        // Create preview
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.createElement('img');
            preview.src = e.target.result;
            preview.className = 'image-preview';
            preview.style.cssText = 'max-width: 200px; max-height: 200px; border-radius: 8px; margin: 10px 0; display: block;';
            
            const composerActions = document.querySelector('.composer-actions');
            if (composerActions) {
                composerActions.parentNode.insertBefore(preview, composerActions);
            }
        };
        reader.readAsDataURL(file);
        
        console.log('Image selected:', file.name);
    }
}

function handleFileUpload(input) {
    const file = input.files[0];
    if (file) {
        console.log('File selected:', file.name);
        showNotification(`File selected: ${file.name}`, 'info');
    }
}

function toggleLinkInput() {
    const url = prompt('Enter URL:');
    if (url && isValidUrl(url)) {
        console.log('URL added:', url);
        showNotification('URL added to post', 'success');
        // You can add logic to handle link preview here
    } else if (url) {
        showNotification('Please enter a valid URL', 'error');
    }
}

function togglePrioritySelector() {
    const priority = prompt('Set priority (normal, high, urgent):', 'normal');
    if (['normal', 'high', 'urgent'].includes(priority)) {
        const priorityInput = document.getElementById('postPriority');
        if (priorityInput) {
            priorityInput.value = priority;
            showNotification(`Priority set to: ${priority}`, 'success');
        }
    } else if (priority) {
        showNotification('Invalid priority. Use: normal, high, or urgent', 'error');
    }
}

function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

// Media handlers
function openImageModal(imagePath) {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
        cursor: pointer;
    `;
    
    const img = document.createElement('img');
    img.src = imagePath;
    img.style.cssText = `
        max-width: 90%;
        max-height: 90%;
        object-fit: contain;
        border-radius: 8px;
    `;
    
    modal.appendChild(img);
    document.body.appendChild(modal);
    
    modal.onclick = function() {
        document.body.removeChild(modal);
    };
}

function downloadFile(filePath, fileName) {
    const a = document.createElement('a');
    a.href = filePath;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function openLink(url) {
    window.open(url, '_blank');
}

// Placeholder functions for features to be implemented
function replyToComment(commentId) {
    console.log('Reply to comment:', commentId);
    showNotification('Reply feature coming soon!', 'info');
}

function editComment(commentId) {
    console.log('Edit comment:', commentId);
    showNotification('Edit comment feature coming soon!', 'info');
}

function deleteComment(commentId) {
    console.log('Delete comment:', commentId);
    showNotification('Delete comment feature coming soon!', 'info');
}

function toggleCommentLike(commentId) {
    console.log('Toggle comment like:', commentId);
    showNotification('Comment like feature coming soon!', 'info');
}

function sharePost(postId) {
    console.log('Share post:', postId);
    showNotification('Share feature coming soon!', 'info');
}