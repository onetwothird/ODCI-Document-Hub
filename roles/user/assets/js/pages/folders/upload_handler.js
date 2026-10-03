function initializeUploadForm() {
    const uploadForm = document.getElementById('uploadForm');
    if (!uploadForm || uploadForm.dataset.uploadInitialized === 'true') return;
    uploadForm.dataset.uploadInitialized = 'true';

    uploadForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (isUploading) return;

        const category = uploadForm.querySelector('select[name="category"]')?.value;
        const academicYear = uploadForm.querySelector('select[name="academic_year"]')?.value;
        const semester = uploadForm.querySelector('input[name="semester"]:checked')?.value;
        const customFolderId = uploadForm.querySelector('input[name="custom_folder_id"]')?.value;

        if (!category && !customFolderId) {
            notifyUpload('Please select a file category.', 'error');
            return;
        }
        if (!validateAcademicPeriod()) return;
        if (selectedFiles.length === 0) {
            notifyUpload('Please select at least one file to upload.', 'error');
            return;
        }

        isUploading = true;
        disableFormElements(true);
        const progress = document.getElementById('uploadProgress');
        if (progress) progress.style.display = 'block';

        const formData = new FormData();
        formData.append('department', userDepartmentId);
        if (customFolderId) {
            formData.append('custom_folder_id', customFolderId);
        } else {
            formData.append('category', category);
        }
        formData.append('academic_year', academicYear);
        formData.append('semester', semester);
        formData.append('description', document.getElementById('fileDescription')?.value || '');
        formData.append('tags', JSON.stringify(selectedTags));
        selectedFiles.forEach(file => formData.append('files[]', file));

        try {
            const data = await sendUploadRequest(formData);
            notifyUpload(data.message || 'Files uploaded successfully.', 'success');
            closeUploadModal();

            if (customFolderId) {
                await loadCustomFolders(userDepartmentId);
                await loadCustomFolderSemester(customFolderId, semester, true);
            } else if (userDepartmentId) {
                loadDepartmentCategories(userDepartmentId);
                if (typeof loadedCategories !== 'undefined') {
                    loadedCategories.delete(`${userDepartmentId}-${category}`);
                }
                if (typeof loadCategoryFiles === 'function') {
                    loadCategoryFiles(userDepartmentId, category);
                }
            }
        } catch (error) {
            console.error('Upload error:', error);
            notifyUpload(error.message || 'Upload failed. Please try again.', 'error');
        } finally {
            isUploading = false;
            disableFormElements(false);
            if (progress) progress.style.display = 'none';
        }
    });
}

function sendUploadRequest(formData) {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');

        request.open('POST', 'handlers/upload_handler.php');
        request.upload.addEventListener('progress', event => {
            if (!event.lengthComputable) return;
            const percent = Math.round((event.loaded / event.total) * 100);
            if (progressBar) progressBar.style.width = `${percent}%`;
            if (progressPercent) progressPercent.textContent = `${percent}%`;
        });
        request.addEventListener('load', () => {
            let response;
            try {
                response = JSON.parse(request.responseText);
            } catch {
                reject(new Error('The server returned an invalid response. Please try again.'));
                return;
            }

            if (request.status < 200 || request.status >= 300 || !response.success) {
                reject(new Error(response.message || `Upload failed (HTTP ${request.status}).`));
                return;
            }
            resolve(response);
        });
        request.addEventListener('error', () => reject(new Error('Network error while uploading.')));
        request.addEventListener('abort', () => reject(new Error('Upload was cancelled.')));
        request.send(formData);
    });
}

function notifyUpload(message, type) {
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        alert(message);
    }
}

function disableFormElements(disabled) {
    const formElements = document.querySelectorAll('#uploadForm input, #uploadForm select, #uploadForm textarea, #uploadForm button');
    formElements.forEach(element => {
        element.disabled = disabled;
        element.style.opacity = disabled ? '0.6' : '1';
        element.style.cursor = disabled ? 'not-allowed' : '';
    });
    if (selectedFiles.length > 0) {
        displayFilePreview();
    }
}