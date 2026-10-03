// post-renderer.js - Simplified without reaction picker
// Module for rendering posts and their components

class PostRenderer {
    constructor(core) {
        this.core = core;
    }

    // Create post element
    createPostElement(post) {
        const postDiv = document.createElement('div');
        postDiv.className = 'post';
        postDiv.dataset.postId = post.id;
        
        postDiv.innerHTML = this.buildPostHTML(post);
        return postDiv;
    }

    // Build complete post HTML
    buildPostHTML(post) {
        const timeAgo = window.utils.getTimeAgo(post.created_at);
        const priorityBadge = this.createPriorityBadge(post.priority);
        const visibilityBadge = this.createVisibilityBadge(post.visibility);
        const editedText = this.createEditedText(post.is_edited);
        const actionMenu = this.createActionMenu(post);

        return `
            ${this.createPostHeader(post, timeAgo, editedText, priorityBadge, visibilityBadge, actionMenu)}
            ${this.createPostContent(post)}
            ${this.createPostMediaHTML(post)}
            ${this.createPostEngagement(post)}
            ${this.createPostActions(post)}
        `;
    }

    // Create post header section
    createPostHeader(post, timeAgo, editedText, priorityBadge, visibilityBadge, actionMenu) {
        return `
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
                ${actionMenu}
            </div>
        `;
    }

    // Create post content section
    createPostContent(post) {
        return `<div class="post-content">${window.utils.escapeHtml(post.content)}</div>`;
    }

    // Create post engagement section
    createPostEngagement(post) {
        const stats = [];
        
        if (post.like_count > 0) {
            stats.push(`<span><i class='bx bx-heart'></i> ${post.like_count}</span>`);
        }
        if (post.comment_count > 0) {
            stats.push(`<span><i class='bx bx-message'></i> ${post.comment_count}</span>`);
        }
        if (post.view_count > 0) {
            stats.push(`<span><i class='bx bx-show'></i> ${post.view_count}</span>`);
        }

        return `
            <div class="post-engagement">
                <div class="post-stats">
                    ${stats.join('')}
                </div>
            </div>
        `;
    }

    // Create post actions section - simplified without reactions
    createPostActions(post) {
        return `
            <div class="post-actions">
                <button class="post-action ${post.user_liked ? 'liked' : ''}" onclick="toggleLike(${post.id})">
                    <i class='bx ${post.user_liked ? 'bxs-heart' : 'bx-heart'}'></i>
                    <span>${post.user_liked ? 'Liked' : 'Like'}</span>
                </button>
                
                <button class="post-action" onclick="toggleComments(${post.id})">
                    <i class='bx bx-message'></i>
                    <span>Comment</span>
                </button>
            </div>
        `;
    }

    // Create priority badge
    createPriorityBadge(priority) {
        return priority !== 'normal' ? 
            `<span class="post-badge ${priority}">${priority.toUpperCase()}</span>` : '';
    }

    // Create visibility badge
    createVisibilityBadge(visibility) {
        return visibility !== 'public' ? 
            `<span class="post-badge ${visibility}">${visibility.toUpperCase()}</span>` : '';
    }

    // Create edited text indicator
    createEditedText(isEdited) {
        return isEdited ? 
            '<span style="color: var(--text-secondary); font-size: 12px;">(edited)</span>' : '';
    }

    // Create action menu
    createActionMenu(post) {
        if (!this.core.canEditPost(post)) return '';

        return `
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
        `;
    }

    // Create post media HTML - Updated for multiple images
    createPostMediaHTML(post) {
        if (!post.media || post.media.length === 0) return '';
        
        // Separate images from other media
        const images = post.media.filter(media => media.media_type === 'image');
        const otherMedia = post.media.filter(media => media.media_type !== 'image');
        
        let mediaHTML = '<div class="post-media">';
        
        // Render images with gallery layout
        if (images.length > 0) {
            mediaHTML += this.createImageGallery(images);
        }
        
        // Render other media (files only, no links)
        otherMedia.forEach(media => {
            if (media.media_type === 'file') {
                mediaHTML += this.createFileMedia(media);
            }
        });
        
        mediaHTML += '</div>';
        return mediaHTML;
    }

    // Create image gallery for multiple images
    createImageGallery(images) {
        const imageCount = images.length;
        let galleryHTML = `<div class="post-image-gallery" data-count="${imageCount}">`;
        
        if (imageCount === 1) {
            // Single image - full width
            galleryHTML += this.createImageMedia(images[0], 'single');
        } else if (imageCount === 2) {
            // Two images - side by side
            images.forEach(image => {
                galleryHTML += this.createImageMedia(image, 'double');
            });
        } else if (imageCount === 3) {
            // Three images - first one large, other two stacked
            galleryHTML += this.createImageMedia(images[0], 'triple-main');
            galleryHTML += '<div class="triple-side">';
            galleryHTML += this.createImageMedia(images[1], 'triple-side-item');
            galleryHTML += this.createImageMedia(images[2], 'triple-side-item');
            galleryHTML += '</div>';
        } else if (imageCount === 4) {
            // Four images - 2x2 grid
            images.forEach(image => {
                galleryHTML += this.createImageMedia(image, 'quad');
            });
        } else {
            // More than 4 - show first 4 with "+X more" overlay on last
            for (let i = 0; i < Math.min(4, imageCount); i++) {
                const layoutClass = i === 3 && imageCount > 4 ? 'quad-more' : 'quad';
                galleryHTML += this.createImageMedia(images[i], layoutClass, i === 3 ? imageCount - 4 : 0);
            }
        }
        
        galleryHTML += '</div>';
        return galleryHTML;
    }

    // Create individual image media element - Updated for gallery layouts
    createImageMedia(media, layout = 'single', moreCount = 0) {
        let imagePath = media.file_path;
        
        // Fix path as before
        if (imagePath.startsWith('uploads/posts/')) {
            imagePath = '../../' + imagePath;
        } else if (imagePath.startsWith('../../../uploads/posts/')) {
            imagePath = imagePath.replace('../../../', '../../');
        } else if (!imagePath.startsWith('/') && !imagePath.startsWith('http') && !imagePath.startsWith('../../')) {
            imagePath = '../../uploads/posts/' + imagePath.split('/').pop();
        }
        
        const moreOverlay = moreCount > 0 ? 
            `<div class="image-more-overlay">+${moreCount} more</div>` : '';
        
        return `
            <div class="post-image-container ${layout}" onclick="openImageModal('${imagePath}')">
                <img src="${imagePath}" alt="Post image" class="post-image" 
                    onerror="this.onerror=null; this.src='../../assets/img/image-placeholder.png';">
                ${moreOverlay}
            </div>
        `;
    }

    // Create file media element
    createFileMedia(media) {
        let filePath = media.file_path;
        
        if (filePath.startsWith('uploads/posts/')) {
            filePath = '../../' + filePath;
        } else if (filePath.startsWith('../../../uploads/posts/')) {
            filePath = filePath.replace('../../../', '../../');
        } else if (!filePath.startsWith('/') && !filePath.startsWith('http') && !filePath.startsWith('../../')) {
            filePath = '../../uploads/posts/' + filePath.split('/').pop();
        }
        
        // Get file icon and color based on file type
        const fileInfo = this.getFileTypeInfo(media.mime_type, media.original_name);
        
        return `
            <div class="post-file" onclick="downloadFile('${filePath}', '${media.original_name}')" 
                style="cursor: pointer; transition: all 0.2s ease;" 
                onmouseover="this.style.backgroundColor='#f0f0f0'" 
                onmouseout="this.style.backgroundColor='transparent'">
                <i class='bx ${fileInfo.icon} post-file-icon' style="color: ${fileInfo.color}; font-size: 28px;"></i>
                <div class="post-file-info">
                    <div class="post-file-name" style="font-weight: 500;">${media.original_name}</div>
                    <div class="post-file-details" style="font-size: 12px; color: #666;">
                        ${fileInfo.type} • ${window.utils.formatFileSize(media.file_size)}
                    </div>
                </div>
                <i class='bx bx-download' style="color: #007bff; font-size: 20px;"></i>
            </div>
        `;
    }

    getFileTypeInfo(mimeType, fileName) {
        const extension = fileName.split('.').pop().toLowerCase();
        
        // Document types
        if (mimeType.includes('pdf') || extension === 'pdf') {
            return { icon: 'bx-file-pdf', color: '#e74c3c', type: 'PDF' };
        }
        
        // Microsoft Office - Word
        if (mimeType.includes('word') || mimeType.includes('document') || 
            ['doc', 'docx'].includes(extension)) {
            return { icon: 'bx-file-doc', color: '#2b7cd3', type: 'Word Document' };
        }
        
        // Microsoft Office - Excel
        if (mimeType.includes('excel') || mimeType.includes('spreadsheet') || 
            ['xls', 'xlsx', 'csv'].includes(extension)) {
            return { icon: 'bx-spreadsheet', color: '#107c41', type: 'Spreadsheet' };
        }
        
        // Microsoft Office - PowerPoint
        if (mimeType.includes('powerpoint') || mimeType.includes('presentation') || 
            ['ppt', 'pptx'].includes(extension)) {
            return { icon: 'bx-file', color: '#d24726', type: 'Presentation' };
        }
        
        // Archive files
        if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('7z') || 
            ['zip', 'rar', '7z', 'tar', 'gz'].includes(extension)) {
            return { icon: 'bx-archive', color: '#8e44ad', type: 'Archive' };
        }
        
        // Text files
        if (mimeType.includes('text') || ['txt', 'rtf'].includes(extension)) {
            return { icon: 'bx-file-text', color: '#34495e', type: 'Text File' };
        }
        
        // Code files
        if (['js', 'html', 'css', 'php', 'py', 'java', 'cpp', 'c', 'json', 'xml'].includes(extension)) {
            return { icon: 'bx-code', color: '#f39c12', type: 'Code File' };
        }
        
        // Video files
        if (mimeType.includes('video') || ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'].includes(extension)) {
            return { icon: 'bx-video', color: '#e67e22', type: 'Video' };
        }
        
        // Audio files
        if (mimeType.includes('audio') || ['mp3', 'wav', 'ogg', 'flac', 'aac'].includes(extension)) {
            return { icon: 'bx-music', color: '#9b59b6', type: 'Audio' };
        }
        
        // Image files (fallback, shouldn't normally appear here)
        if (mimeType.includes('image') || ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(extension)) {
            return { icon: 'bx-image', color: '#27ae60', type: 'Image' };
        }
        
        // Default file
        return { icon: 'bx-file', color: '#7f8c8d', type: 'File' };
    }
}

// Create global instance
window.postRenderer = new PostRenderer(window.socialFeedCore);