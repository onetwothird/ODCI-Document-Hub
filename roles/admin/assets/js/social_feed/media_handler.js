// media-handler.js
// Module for handling media files and interactions

class MediaHandler {
    constructor() {
        // No dependencies needed for this module
    }

    // Open image in modal
    openImageModal(imagePath) {
        const modal = this.createImageModal(imagePath);
        document.body.appendChild(modal);
        
        // Close modal when clicked
        modal.onclick = () => {
            document.body.removeChild(modal);
        };
    }

    // Create image modal element
    createImageModal(imagePath) {
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
        return modal;
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