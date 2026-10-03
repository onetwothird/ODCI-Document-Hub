// post-composer.js - Updated with link and priority removal
// Module for handling post creation and composition

class PostComposer {
    constructor(core) {
        this.core = core;
        this.selectedImages = []; // Store multiple selected images
        this.selectedFiles = []; // Store multiple selected files
    }

    // Create a new post
    async createPost(event) {
        event.preventDefault();
        
        const contentInput = document.getElementById('postContent');
        if (!contentInput) return;
        
        const content = contentInput.value.trim();
        if (!content && this.selectedImages.length === 0 && this.selectedFiles.length === 0) {
            window.utils.showNotification('Please enter some content, select images, or attach files for your post', 'error');
            return;
        }
        
        const formData = this.buildFormData(content);
        await this.submitPost(formData);
    }

    // Build form data for post submission
    buildFormData(content) {
        const formData = new FormData();
        formData.append('content', content);
        
        // Add visibility (removed priority)
        const visibilityInput = document.getElementById('postVisibility');
        formData.append('visibility', visibilityInput ? visibilityInput.value : 'public');
        
        // Add media files
        this.addMediaToFormData(formData);
        
        return formData;
    }

    // Add media files to form data - Updated for multiple images and files
    addMediaToFormData(formData) {
        // Handle multiple images
        this.selectedImages.forEach((imageFile, index) => {
            formData.append(`images[${index}]`, imageFile);
        });
        
        // Handle multiple files
        this.selectedFiles.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });
    }

    // Submit post to server
    async submitPost(formData) {
        const button = document.getElementById('postBtn');
        
        try {
            this.setButtonLoading(button, true);
            
            const response = await fetch('api/create_post.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.handlePostSuccess();
            } else {
                window.utils.showNotification(result.message || 'Error creating post', 'error');
            }
        } catch (error) {
            console.error('Error creating post:', error);
            window.utils.showNotification('Error creating post', 'error');
        } finally {
            this.setButtonLoading(button, false);
        }
    }

    // Handle successful post creation
    async handlePostSuccess() {
        this.resetForm();
        await this.core.refreshFeed();
        window.utils.showNotification('Post created successfully!', 'success');
    }

    // Set button loading state
    setButtonLoading(button, isLoading) {
        if (!button) return;
        
        if (isLoading) {
            button.disabled = true;
            button.innerHTML = '<div class="spinner"></div> Posting...';
        } else {
            button.disabled = false;
            button.innerHTML = '<i class="bx bx-send"></i> Post';
        }
    }

    // Reset the form after successful post
    resetForm() {
        const elements = {
            contentInput: document.getElementById('postContent'),
            imageFile: document.getElementById('imageInput'),
            attachFile: document.getElementById('fileInput')
        };

        if (elements.contentInput) elements.contentInput.value = '';
        if (elements.imageFile) elements.imageFile.value = '';
        if (elements.attachFile) elements.attachFile.value = '';
        
        // Clear selected images and files and previews
        this.selectedImages = [];
        this.selectedFiles = [];
        this.removeAllImagePreviews();
        this.removeAllFilePreviews();
        
        // Update post button state
        this.updatePostButtonState();
    }

    // Handle multiple image upload
    handleImageUpload(input) {
        const files = Array.from(input.files);
        if (!files.length) return;

        // Limit to maximum 100 images
        const maxImages = 100;
        const totalImages = this.selectedImages.length + files.length;
        
        if (totalImages > maxImages) {
            window.utils.showNotification(`Maximum ${maxImages} images allowed. Please select fewer images.`, 'error');
            input.value = ''; // Clear the input
            return;
        }

        // Add new images to selected images array
        files.forEach(file => {
            if (this.isValidImageFile(file)) {
                this.selectedImages.push(file);
                this.createImagePreview(file);
            }
        });
        
        // Update post button state
        this.updatePostButtonState();
        
        console.log(`Selected ${this.selectedImages.length} images`);
        window.utils.showNotification(`${files.length} image(s) added. Total: ${this.selectedImages.length}`, 'success');
    }

    // Handle multiple file upload
    handleFileUpload(input) {
        const files = Array.from(input.files);
        if (!files.length) return;

        // Limit to maximum 50 files
        const maxFiles = 50;
        const totalFiles = this.selectedFiles.length + files.length;
        
        if (totalFiles > maxFiles) {
            window.utils.showNotification(`Maximum ${maxFiles} files allowed. Please select fewer files.`, 'error');
            input.value = ''; // Clear the input
            return;
        }

        // Add new files to selected files array
        files.forEach(file => {
            if (this.isValidFile(file)) {
                this.selectedFiles.push(file);
                this.createFilePreview(file);
            }
        });
        
        // Update post button state
        this.updatePostButtonState();
        
        console.log(`Selected ${this.selectedFiles.length} files`);
        window.utils.showNotification(`${files.length} file(s) added. Total: ${this.selectedFiles.length}`, 'success');
    }

    // Validate image file
    isValidImageFile(file) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 20 * 1024 * 1024; // 20MB per image
        
        if (!allowedTypes.includes(file.type)) {
            window.utils.showNotification(`${file.name} is not a valid image format`, 'error');
            return false;
        }
        
        if (file.size > maxSize) {
            window.utils.showNotification(`${file.name} is too large. Max size is 5MB for images`, 'error');
            return false;
        }
        
        return true;
    }

    // Validate file
    isValidFile(file) {
        const maxSize = 100 * 1024 * 1024; // 100MB per file
        
        // Check for image files that should go through image upload instead
        const imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (imageTypes.includes(file.type)) {
            window.utils.showNotification(`${file.name} is an image file. Please use the image upload button instead.`, 'error');
            return false;
        }
        
        if (file.size > maxSize) {
            window.utils.showNotification(`${file.name} is too large. Max size is 100MB`, 'error');
            return false;
        }
        
        return true;
    }

    // Create image preview element
    createImagePreview(imageFile) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const previewContainer = this.getOrCreateImagePreviewContainer();
            
            const previewDiv = document.createElement('div');
            previewDiv.className = 'image-preview-item';
            previewDiv.style.cssText = 'position: relative; display: inline-block; margin: 5px;';
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'image-preview';
            img.style.cssText = 'width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #ddd;';
            
            const removeBtn = document.createElement('button');
            removeBtn.innerHTML = '&times;';
            removeBtn.className = 'remove-image-btn';
            removeBtn.style.cssText = `
                position: absolute; top: -5px; right: -5px; 
                background: #ff4757; color: white; border: none; 
                border-radius: 50%; width: 20px; height: 20px; 
                font-size: 12px; cursor: pointer; line-height: 1;
                display: flex; align-items: center; justify-content: center;
            `;
            removeBtn.onclick = () => this.removeImagePreview(imageFile, previewDiv);
            
            previewDiv.appendChild(img);
            previewDiv.appendChild(removeBtn);
            previewContainer.appendChild(previewDiv);
        };
        reader.readAsDataURL(imageFile);
    }

    // Create file preview element
    createFilePreview(file) {
        const previewContainer = this.getOrCreateFilePreviewContainer();
        
        const previewDiv = document.createElement('div');
        previewDiv.className = 'file-preview-item';
        previewDiv.style.cssText = `
            display: flex; align-items: center; padding: 8px; 
            background: #f5f5f5; border-radius: 8px; margin: 5px 0;
            border: 1px solid #ddd;
        `;
        
        const fileIcon = document.createElement('i');
        fileIcon.className = `bx ${this.getFileIcon(file.type)}`;
        fileIcon.style.cssText = 'font-size: 24px; margin-right: 10px; color: #666;';
        
        const fileInfo = document.createElement('div');
        fileInfo.style.cssText = 'flex: 1;';
        fileInfo.innerHTML = `
            <div style="font-weight: 500; font-size: 14px;">${file.name}</div>
            <div style="font-size: 12px; color: #666;">${window.utils.formatFileSize(file.size)}</div>
        `;
        
        const removeBtn = document.createElement('button');
        removeBtn.innerHTML = '&times;';
        removeBtn.style.cssText = `
            background: #ff4757; color: white; border: none; 
            border-radius: 50%; width: 24px; height: 24px; 
            font-size: 16px; cursor: pointer; margin-left: 10px;
            display: flex; align-items: center; justify-content: center;
        `;
        removeBtn.onclick = () => this.removeFilePreview(file, previewDiv);
        
        previewDiv.appendChild(fileIcon);
        previewDiv.appendChild(fileInfo);
        previewDiv.appendChild(removeBtn);
        previewContainer.appendChild(previewDiv);
    }

    // Get file icon based on file type
    getFileIcon(mimeType) {
        if (mimeType.includes('pdf')) return 'bx-file-pdf';
        if (mimeType.includes('word') || mimeType.includes('document')) return 'bx-file-doc';
        if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'bx-spreadsheet';
        if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'bx-file';
        if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('7z')) return 'bx-archive';
        if (mimeType.includes('text')) return 'bx-file-text';
        if (mimeType.includes('video')) return 'bx-video';
        if (mimeType.includes('audio')) return 'bx-music';
        return 'bx-file';
    }

    // Get or create image preview container
    getOrCreateImagePreviewContainer() {
        let container = document.querySelector('.images-preview-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'images-preview-container';
            container.style.cssText = 'margin: 10px 0; padding: 10px; border: 1px dashed #ddd; border-radius: 8px; min-height: 50px;';
            
            const composerActions = document.querySelector('.composer-actions');
            if (composerActions) {
                composerActions.parentNode.insertBefore(container, composerActions);
            }
        }
        return container;
    }

    // Get or create file preview container
    getOrCreateFilePreviewContainer() {
        let container = document.querySelector('.files-preview-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'files-preview-container';
            container.style.cssText = 'margin: 10px 0; padding: 10px; border: 1px dashed #ddd; border-radius: 8px;';
            
            // Insert before composer actions or after images container
            const composerActions = document.querySelector('.composer-actions');
            const imagesContainer = document.querySelector('.images-preview-container');
            const insertBefore = composerActions || (imagesContainer ? imagesContainer.nextSibling : null);
            
            if (insertBefore && insertBefore.parentNode) {
                insertBefore.parentNode.insertBefore(container, insertBefore);
            } else {
                const postForm = document.getElementById('postForm');
                if (postForm) {
                    postForm.appendChild(container);
                }
            }
        }
        return container;
    }

    // Remove specific image preview
    removeImagePreview(imageFile, previewDiv) {
        // Remove from selected images array
        const index = this.selectedImages.indexOf(imageFile);
        if (index > -1) {
            this.selectedImages.splice(index, 1);
        }
        
        // Remove preview element
        previewDiv.remove();
        
        // If no more images, remove container
        const container = document.querySelector('.images-preview-container');
        if (container && this.selectedImages.length === 0) {
            container.remove();
        }
        
        // Update post button state
        this.updatePostButtonState();
        
        console.log(`Removed image. Remaining: ${this.selectedImages.length}`);
    }

    // Remove specific file preview
    removeFilePreview(file, previewDiv) {
        // Remove from selected files array
        const index = this.selectedFiles.indexOf(file);
        if (index > -1) {
            this.selectedFiles.splice(index, 1);
        }
        
        // Remove preview element
        previewDiv.remove();
        
        // If no more files, remove container
        const container = document.querySelector('.files-preview-container');
        if (container && this.selectedFiles.length === 0) {
            container.remove();
        }
        
        // Update post button state
        this.updatePostButtonState();
        
        console.log(`Removed file. Remaining: ${this.selectedFiles.length}`);
    }

    // Remove all image previews
    removeAllImagePreviews() {
        const container = document.querySelector('.images-preview-container');
        if (container) {
            container.remove();
        }
    }

    // Remove all file previews
    removeAllFilePreviews() {
        const container = document.querySelector('.files-preview-container');
        if (container) {
            container.remove();
        }
    }

    // Update post button state based on content
    updatePostButtonState() {
        const contentInput = document.getElementById('postContent');
        const postBtn = document.getElementById('postBtn');
        
        if (!postBtn) return;
        
        const hasContent = contentInput && contentInput.value.trim().length > 0;
        const hasImages = this.selectedImages.length > 0;
        const hasFiles = this.selectedFiles.length > 0;
        
        postBtn.disabled = !hasContent && !hasImages && !hasFiles;
    }
}

// Create global instance
window.postComposer = new PostComposer(window.socialFeedCore);