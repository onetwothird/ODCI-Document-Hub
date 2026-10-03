// media-handler.js
// Enhanced module for handling media files with Facebook-like image gallery

class MediaHandler {
    constructor() {
        this.currentGallery = null;
        this.currentIndex = 0;
        this.images = [];
        this.modal = null;
        this.isZoomed = false;
        this.zoomLevel = 1;
        this.startX = 0;
        this.startY = 0;
        this.isDragging = false;
        this.lastTouchX = 0;
    }

    // FIXED: Get all images from a post using the new data structure
    getPostImages(postId) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (!postElement) return [];

        // First, try to get images from the hidden data element (contains ALL images)
        const dataElement = postElement.querySelector('.post-images-data');
        if (dataElement && dataElement.dataset.images) {
            try {
                const imagesData = JSON.parse(dataElement.dataset.images.replace(/&apos;/g, "'"));
                return imagesData.map((img, index) => ({
                    src: img.src,
                    alt: img.alt || `Image ${index + 1}`,
                    index: index
                }));
            } catch (e) {
                console.error('Error parsing images data:', e);
            }
        }

        // Fallback: Get images from visible post-image elements (limited to displayed images)
        const imageElements = postElement.querySelectorAll('.post-image');
        return Array.from(imageElements).map((img, index) => ({
            src: img.src,
            alt: img.alt || `Image ${index + 1}`,
            index: index
        }));
    }

    // Open image gallery modal (Facebook-like)
    openImageGallery(postId, initialImageIndex = 0) {
        // Get all images from the post
        this.images = this.getPostImages(postId);
        
        if (this.images.length === 0) {
            console.warn('No images found for post:', postId);
            return;
        }

        console.log(`Opening gallery with ${this.images.length} images, starting at index ${initialImageIndex}`);

        this.currentIndex = Math.max(0, Math.min(initialImageIndex, this.images.length - 1));
        this.createGalleryModal();
        this.loadCurrentImage();
        this.setupEventListeners();
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    // Create the gallery modal
    createGalleryModal() {
        this.modal = document.createElement('div');
        this.modal.className = 'image-gallery-modal';
        this.modal.innerHTML = `
            <div class="gallery-overlay"></div>
            <div class="gallery-content">
                <div class="gallery-header">
                    <div class="gallery-counter">
                        <span id="galleryCounter">${this.currentIndex + 1} of ${this.images.length}</span>
                    </div>
                    <button class="gallery-close" id="galleryClose">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                
                <div class="gallery-main">
                    <button class="gallery-nav gallery-prev" id="galleryPrev" ${this.images.length <= 1 ? 'style="display:none"' : ''}>
                        <i class='bx bx-chevron-left'></i>
                    </button>
                    
                    <div class="gallery-image-container" id="galleryImageContainer">
                        <img class="gallery-image" id="galleryImage" alt="" />
                        <div class="gallery-loading" id="galleryLoading">
                            <div class="spinner"></div>
                        </div>
                    </div>
                    
                    <button class="gallery-nav gallery-next" id="galleryNext" ${this.images.length <= 1 ? 'style="display:none"' : ''}>
                        <i class='bx bx-chevron-right'></i>
                    </button>
                </div>
                
                <div class="gallery-footer" ${this.images.length <= 1 ? 'style="display:none"' : ''}>
                    <div class="gallery-thumbnails" id="galleryThumbnails"></div>
                </div>
            </div>
        `;

        document.body.appendChild(this.modal);
        
        // Create thumbnails if multiple images
        if (this.images.length > 1) {
            this.createThumbnails();
        }

        // Animate in
        requestAnimationFrame(() => {
            this.modal.classList.add('active');
        });
    }

    // Create thumbnail navigation
    createThumbnails() {
        const thumbnailContainer = document.getElementById('galleryThumbnails');
        if (!thumbnailContainer) return;

        this.images.forEach((image, index) => {
            const thumb = document.createElement('div');
            thumb.className = `gallery-thumbnail ${index === this.currentIndex ? 'active' : ''}`;
            thumb.innerHTML = `<img src="${image.src}" alt="${image.alt}" />`;
            thumb.onclick = () => this.goToImage(index);
            thumbnailContainer.appendChild(thumb);
        });
    }

    // Load current image
    loadCurrentImage() {
        const imageEl = document.getElementById('galleryImage');
        const loadingEl = document.getElementById('galleryLoading');
        const counterEl = document.getElementById('galleryCounter');
        
        if (!imageEl || !this.images[this.currentIndex]) return;

        // Show loading
        loadingEl.style.display = 'flex';
        imageEl.style.opacity = '0';

        // Reset zoom
        this.resetZoom();

        // Update counter
        if (counterEl) {
            counterEl.textContent = `${this.currentIndex + 1} of ${this.images.length}`;
        }

        // Load image
        const img = new Image();
        img.onload = () => {
            imageEl.src = img.src;
            imageEl.alt = this.images[this.currentIndex].alt;
            loadingEl.style.display = 'none';
            imageEl.style.opacity = '1';
            
            // Update thumbnail active state
            this.updateThumbnailActive();
            
            // Preload adjacent images
            this.preloadAdjacentImages();
        };
        
        img.onerror = () => {
            loadingEl.style.display = 'none';
            imageEl.src = '/social_feed/assets/img/image-placeholder.png';
            imageEl.style.opacity = '1';
        };

        img.src = this.images[this.currentIndex].src;
    }

    // Update active thumbnail
    updateThumbnailActive() {
        const thumbnails = document.querySelectorAll('.gallery-thumbnail');
        thumbnails.forEach((thumb, index) => {
            thumb.classList.toggle('active', index === this.currentIndex);
        });
    }

    // Preload adjacent images for smoother navigation
    preloadAdjacentImages() {
        const preloadIndexes = [];
        
        // Preload previous image
        if (this.currentIndex > 0) {
            preloadIndexes.push(this.currentIndex - 1);
        }
        
        // Preload next image
        if (this.currentIndex < this.images.length - 1) {
            preloadIndexes.push(this.currentIndex + 1);
        }

        preloadIndexes.forEach(index => {
            if (this.images[index] && !document.querySelector(`img[src="${this.images[index].src}"]`)) {
                const img = new Image();
                img.src = this.images[index].src;
            }
        });
    }

    // Navigation methods
    goToImage(index) {
        if (index >= 0 && index < this.images.length) {
            this.currentIndex = index;
            this.loadCurrentImage();
        }
    }

    nextImage() {
        this.goToImage(this.currentIndex + 1);
    }

    prevImage() {
        this.goToImage(this.currentIndex - 1);
    }

    // Zoom functionality
    toggleZoom(event) {
        const imageEl = document.getElementById('galleryImage');
        if (!imageEl) return;

        if (this.isZoomed) {
            this.resetZoom();
        } else {
            this.zoomLevel = 2;
            imageEl.style.transform = `scale(${this.zoomLevel})`;
            imageEl.style.cursor = 'grab';
            this.isZoomed = true;
        }
    }

    resetZoom() {
        const imageEl = document.getElementById('galleryImage');
        if (!imageEl) return;

        this.zoomLevel = 1;
        this.isZoomed = false;
        imageEl.style.transform = 'scale(1)';
        imageEl.style.cursor = 'pointer';
        imageEl.style.transformOrigin = 'center center';
    }

    // Setup event listeners
    setupEventListeners() {
        if (!this.modal) return;

        // Close button
        const closeBtn = document.getElementById('galleryClose');
        if (closeBtn) {
            closeBtn.onclick = () => this.closeGallery();
        }

        // Navigation buttons
        const prevBtn = document.getElementById('galleryPrev');
        const nextBtn = document.getElementById('galleryNext');
        
        if (prevBtn) {
            prevBtn.onclick = () => this.prevImage();
        }
        
        if (nextBtn) {
            nextBtn.onclick = () => this.nextImage();
        }

        // Overlay click to close
        const overlay = this.modal.querySelector('.gallery-overlay');
        if (overlay) {
            overlay.onclick = () => this.closeGallery();
        }

        // Image click to zoom
        const imageEl = document.getElementById('galleryImage');
        if (imageEl) {
            imageEl.onclick = (e) => {
                e.stopPropagation();
                this.toggleZoom(e);
            };
        }

        // Keyboard navigation
        this.keyboardHandler = (e) => {
            if (!this.modal || !this.modal.classList.contains('active')) return;
            
            switch(e.key) {
                case 'Escape':
                    this.closeGallery();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.prevImage();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.nextImage();
                    break;
            }
        };
        
        document.addEventListener('keydown', this.keyboardHandler);

        // Touch/swipe support for mobile
        this.setupTouchEvents();
    }

    // Setup touch events for mobile swipe
    setupTouchEvents() {
        const container = document.getElementById('galleryImageContainer');
        if (!container) return;

        let startX = 0;
        let startTime = 0;

        container.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startTime = Date.now();
        }, { passive: true });

        container.addEventListener('touchend', (e) => {
            if (!e.changedTouches[0]) return;
            
            const endX = e.changedTouches[0].clientX;
            const deltaX = startX - endX;
            const deltaTime = Date.now() - startTime;
            
            // Only register swipe if it's fast enough and far enough
            if (deltaTime < 300 && Math.abs(deltaX) > 50) {
                if (deltaX > 0 && this.currentIndex < this.images.length - 1) {
                    this.nextImage();
                } else if (deltaX < 0 && this.currentIndex > 0) {
                    this.prevImage();
                }
            }
        }, { passive: true });
    }

    // Close gallery
    closeGallery() {
        if (!this.modal) return;

        // Animate out
        this.modal.classList.remove('active');
        
        setTimeout(() => {
            if (this.modal && this.modal.parentNode) {
                document.body.removeChild(this.modal);
            }
            this.cleanup();
        }, 300);
    }

    // Cleanup
    cleanup() {
        // Remove event listeners
        if (this.keyboardHandler) {
            document.removeEventListener('keydown', this.keyboardHandler);
            this.keyboardHandler = null;
        }

        // Reset properties
        this.modal = null;
        this.currentIndex = 0;
        this.images = [];
        this.isZoomed = false;
        this.zoomLevel = 1;

        // Restore body scroll
        document.body.style.overflow = '';
    }

    // Legacy method - now opens gallery for single images
    openImageModal(imagePath) {
        // Find the post that contains this image
        const imageElement = document.querySelector(`img[src="${imagePath}"]`);
        if (!imageElement) {
            // Fallback to simple modal for unknown images
            this.openSimpleImageModal(imagePath);
            return;
        }

        // Find the post element
        const postElement = imageElement.closest('[data-post-id]');
        if (!postElement) {
            this.openSimpleImageModal(imagePath);
            return;
        }

        // Get post ID and find image index
        const postId = postElement.dataset.postId;
        const allImages = this.getPostImages(postId);
        let imageIndex = 0;
        
        // Find the index of the clicked image
        for (let i = 0; i < allImages.length; i++) {
            if (allImages[i].src === imagePath) {
                imageIndex = i;
                break;
            }
        }

        // Open gallery
        this.openImageGallery(postId, imageIndex);
    }

    // Simple modal fallback for unknown images
    openSimpleImageModal(imagePath) {
        const modal = document.createElement('div');
        modal.className = 'image-gallery-modal active';
        modal.innerHTML = `
            <div class="gallery-overlay"></div>
            <div class="gallery-content">
                <div class="gallery-header">
                    <button class="gallery-close">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
                <div class="gallery-main">
                    <div class="gallery-image-container">
                        <img class="gallery-image" src="${imagePath}" alt="Image" />
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        
        // Close handlers
        const closeBtn = modal.querySelector('.gallery-close');
        const overlay = modal.querySelector('.gallery-overlay');
        
        const closeModal = () => {
            modal.classList.remove('active');
            setTimeout(() => {
                if (modal.parentNode) {
                    document.body.removeChild(modal);
                }
                document.body.style.overflow = '';
            }, 300);
        };
        
        closeBtn.onclick = closeModal;
        overlay.onclick = closeModal;
        
        // ESC key
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', escHandler);
                closeModal();
            }
        };
        document.addEventListener('keydown', escHandler);
    }

    // Download file
    downloadFile(filePath, fileName) {
        const a = document.createElement('a');
        a.href = filePath;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Open link in new tab
    openLink(url) {
        window.open(url, '_blank');
    }
}

// Create global instance
window.mediaHandler = new MediaHandler();

// FIXED: New global function for opening image gallery at specific index
function openImageGalleryAtIndex(imageElement, imageIndex) {
    // Find the post element
    const postElement = imageElement.closest('[data-post-id]');
    if (!postElement) {
        console.error('Could not find post element');
        return;
    }

    const postId = postElement.dataset.postId;
    window.mediaHandler.openImageGallery(postId, imageIndex);
}

// Legacy function for opening image gallery (used by post renderer)
function openImageGallery(postId, imageIndex = 0) {
    window.mediaHandler.openImageGallery(postId, imageIndex);
}