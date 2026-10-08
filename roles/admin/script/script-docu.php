<script>
        // Store faculty data for modal display
        const facultyData = <?= json_encode($faculty) ?>;
        
        // Enhanced modal functions
        function showModal(modal) {
            modal.style.display = 'block';
            modal.offsetHeight; // Force reflow
            modal.classList.add('show');
        }

        function hideModal(modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Enhanced showFacultyDetails function with profile image support
        function showFacultyDetails(facultyId) {
            const modal = document.getElementById('facultyDetailsModal');
            const content = document.getElementById('facultyDetailsContent');
            
            // Find faculty data
            const faculty = facultyData.find(f => f.id == facultyId);
            
            if (!faculty) {
                content.innerHTML = '<p>Faculty data not found.</p>';
                showModal(modal);
                return;
            }
            
            // Get first letter for fallback avatar
            const firstLetter = faculty.name.charAt(0).toUpperCase();
            const fullName = `${faculty.surname}, ${faculty.name}${faculty.mi ? ' ' + faculty.mi + '.' : ''}`;
            
            // Use the processed profile image URL
            let avatarContent = '';
            const hasProfileImage = faculty.profile_image_url && faculty.profile_image_url.trim() !== '';
            
            if (hasProfileImage) {
                // Use the already processed image URL
                avatarContent = `
                    <img src="${faculty.profile_image_url}" 
                        alt="${fullName}" 
                        style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; transition: transform 0.3s ease;"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                        onload="this.style.opacity='1';"
                        onmouseover="this.style.transform='scale(1.05)'"
                        onmouseout="this.style.transform='scale(1)'">
                    <div class="fallback-avatar" 
                        style="display: none; width: 100%; height: 100%; 
                                background: linear-gradient(135deg, #007bff, #4a90e2); 
                                color: white; font-size: 32px; font-weight: bold; 
                                display: flex; align-items: center; justify-content: center; 
                                border-radius: 50%; box-shadow: 0 4px 15px rgba(0,123,255,0.3);">
                        ${firstLetter}
                    </div>
                `;
            } else {
                // Enhanced letter avatar with gradient and shadow
                avatarContent = `
                    <div class="letter-avatar" 
                        style="width: 100%; height: 100%; 
                                background: linear-gradient(135deg, #007bff, #4a90e2); 
                                color: white; font-size: 32px; font-weight: bold; 
                                display: flex; align-items: center; justify-content: center; 
                                border-radius: 50%; box-shadow: 0 4px 15px rgba(0,123,255,0.3);
                                transition: transform 0.3s ease;"
                        onmouseover="this.style.transform='scale(1.05)'"
                        onmouseout="this.style.transform='scale(1)'">
                        ${firstLetter}
                    </div>
                `;
            }
            
            // Rest of the modal content generation remains the same...
            content.innerHTML = `
                <div class="faculty-detail-card" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); 
                    border-radius: 15px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    
                    <div class="faculty-detail-header" style="display: flex; align-items: center; gap: 20px; 
                        margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #e9ecef;">
                        
                        <div class="faculty-avatar-container" style="position: relative;">
                            <div class="faculty-avatar" 
                                style="width: 80px; height: 80px; position: relative; border-radius: 50%; 
                                        overflow: hidden; border: 4px solid #ffffff; 
                                        box-shadow: 0 8px 25px rgba(0,0,0,0.15);">
                                ${avatarContent}
                            </div>
                            
                            <!-- Status indicator -->
                            <div class="status-dot" 
                                style="position: absolute; bottom: 5px; right: 5px; 
                                        width: 20px; height: 20px; background: #28a745; 
                                        border: 3px solid white; border-radius: 50%; 
                                        box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                            </div>
                            
                            <!-- Hover effect overlay -->
                            <div class="avatar-overlay" 
                                style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                                        background: rgba(0,123,255,0.1); border-radius: 50%; 
                                        opacity: 0; transition: opacity 0.3s ease; cursor: pointer;"
                                onclick="enlargeProfileImage('${hasProfileImage ? faculty.profile_image_url : ''}', '${fullName}', '${firstLetter}')"
                                onmouseover="this.style.opacity='1'"
                                onmouseout="this.style.opacity='0'">
                                <i class='bx bx-expand' style="position: absolute; top: 50%; left: 50%; 
                                transform: translate(-50%, -50%); font-size: 24px; color: white;"></i>
                            </div>
                        </div>
                        
                        <!-- Rest of the faculty detail content remains the same... -->
                        <div class="faculty-detail-info" style="flex: 1;">
                            <h2 class="faculty-detail-name" 
                                style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700; 
                                    color: #2c3e50; line-height: 1.2;">
                                ${fullName}
                            </h2>
                            <p class="faculty-detail-position" 
                            style="margin: 0 0 5px 0; font-size: 16px; color: #6c757d; 
                                    font-weight: 500;">
                                ${faculty.position || 'Faculty Member'}
                            </p>
                            <div class="department-badge" 
                                style="display: inline-block; background: linear-gradient(135deg, #007bff, #4a90e2); 
                                        color: white; padding: 4px 12px; border-radius: 20px; 
                                        font-size: 12px; font-weight: 600;">
                                ${faculty.department_name || 'No Department'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-grid" 
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
                                gap: 20px; margin-bottom: 25px;">
                        
                        <div class="info-item" 
                            style="background: white; padding: 15px; border-radius: 10px; 
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
                            onmouseover="this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.transform='translateY(0)'">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-building' style="font-size: 24px; color: #007bff;"></i>
                                <div>
                                    <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">Department</strong>
                                    <span style="color: #6c757d;">${faculty.department_name || 'No Department'}</span>
                                </div>
                            </div>
                        </div>
                        
                        ${faculty.employee_id ? `
                        <div class="info-item" 
                            style="background: white; padding: 15px; border-radius: 10px; 
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
                            onmouseover="this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.transform='translateY(0)'">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-id-card' style="font-size: 24px; color: #28a745;"></i>
                                <div>
                                    <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">Employee ID</strong>
                                    <span style="color: #6c757d;">${faculty.employee_id}</span>
                                </div>
                            </div>
                        </div>` : ''}
                        
                        ${faculty.email ? `
                        <div class="info-item" 
                            style="background: white; padding: 15px; border-radius: 10px; 
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
                            onmouseover="this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.transform='translateY(0)'">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-envelope' style="font-size: 24px; color: #ffc107;"></i>
                                <div>
                                    <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">Email</strong>
                                    <a href="mailto:${faculty.email}" 
                                    style="color: #007bff; text-decoration: none;"
                                    onmouseover="this.style.textDecoration='underline'"
                                    onmouseout="this.style.textDecoration='none'">
                                        ${faculty.email}
                                    </a>
                                </div>
                            </div>
                        </div>` : ''}
                        
                        <div class="info-item" 
                            style="background: white; padding: 15px; border-radius: 10px; 
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
                            onmouseover="this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.transform='translateY(0)'">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-user-circle' style="font-size: 24px; color: #17a2b8;"></i>
                                <div>
                                    <strong style="color: #2c3e50; display: block; margin-bottom: 4px;">Status</strong>
                                    <span style="color: #28a745; font-weight: 600;">
                                        <i class='bx bx-check-circle' style="margin-right: 4px;"></i>Active Faculty
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons" 
                        style="display: flex; gap: 12px; flex-wrap: wrap; padding-top: 15px; 
                                border-top: 1px solid #e9ecef;">
                        <button class="btn btn-primary" 
                                onclick="viewAllSubmissions(${faculty.id})"
                                style="background: linear-gradient(135deg, #007bff, #4a90e2); 
                                    border: none; padding: 12px 20px; border-radius: 8px; 
                                    color: white; font-weight: 600; cursor: pointer; 
                                    transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(0,123,255,0.3)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <i class='bx bx-file'></i> View All Documents
                        </button>
                        <button class="btn btn-secondary" 
                                onclick="sendMessage(${faculty.id})"
                                style="background: linear-gradient(135deg, #6c757d, #8e9aaf); 
                                    border: none; padding: 12px 20px; border-radius: 8px; 
                                    color: white; font-weight: 600; cursor: pointer; 
                                    transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(108,117,125,0.3)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <i class='bx bx-message'></i> Send Message
                        </button>
                        <button class="btn btn-info" 
                                onclick="exportFacultyData(${faculty.id})"
                                style="background: linear-gradient(135deg, #17a2b8, #20c997); 
                                    border: none; padding: 12px 20px; border-radius: 8px; 
                                    color: white; font-weight: 600; cursor: pointer; 
                                    transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(23,162,184,0.3)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <i class='bx bx-export'></i> Export Data
                        </button>
                    </div>
                </div>
            `;
            
            showModal(modal);
        }

        // Function to enlarge profile image
        function enlargeProfileImage(imageUrl, fullName, firstLetter) {
            const enlargeModal = document.createElement('div');
            enlargeModal.className = 'modal image-modal';
            enlargeModal.style.cssText = `
                display: block;
                position: fixed;
                z-index: 2000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.8);
                backdrop-filter: blur(5px);
            `;
            
            const hasImage = imageUrl && imageUrl.trim() !== '';
            
            enlargeModal.innerHTML = `
                <div class="modal-content" 
                    style="background: white; margin: 5% auto; padding: 0; 
                            border-radius: 15px; max-width: 500px; overflow: hidden;
                            animation: modalSlideIn 0.3s ease-out;">
                    <div class="modal-header" 
                        style="padding: 20px; border-bottom: 1px solid #e9ecef; 
                                background: linear-gradient(135deg, #f8f9fa, #ffffff);">
                        <h3 style="margin: 0; color: #2c3e50;">Profile Image</h3>
                        <button class="close-btn" 
                                onclick="this.closest('.modal').remove()"
                                style="background: none; border: none; font-size: 24px; 
                                    cursor: pointer; float: right; color: #6c757d;">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 30px; text-align: center;">
                        <div class="enlarged-avatar" 
                            style="width: 200px; height: 200px; margin: 0 auto 20px; 
                                    border-radius: 50%; overflow: hidden; 
                                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
                            ${hasImage ? `
                                <img src="${imageUrl}" 
                                    alt="${fullName}" 
                                    style="width: 100%; height: 100%; object-fit: cover;"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div style="display: none; width: 100%; height: 100%; 
                                        background: linear-gradient(135deg, #007bff, #4a90e2); 
                                        color: white; font-size: 80px; font-weight: bold; 
                                        display: flex; align-items: center; justify-content: center;">
                                    ${firstLetter}
                                </div>
                            ` : `
                                <div style="width: 100%; height: 100%; 
                                        background: linear-gradient(135deg, #007bff, #4a90e2); 
                                        color: white; font-size: 80px; font-weight: bold; 
                                        display: flex; align-items: center; justify-content: center;">
                                    ${firstLetter}
                                </div>
                            `}
                        </div>
                        <h4 style="color: #2c3e50; margin: 0 0 10px 0;">${fullName}</h4>
                        <p style="color: #6c757d; margin: 0;">
                            ${hasImage ? 'Profile Image' : 'Default Avatar (No image uploaded)'}
                        </p>
                    </div>
                </div>
            `;
            
            document.body.appendChild(enlargeModal);
            
            // Add click outside to close
            enlargeModal.addEventListener('click', function(e) {
                if (e.target === enlargeModal) {
                    enlargeModal.remove();
                }
            });
        }

        // Additional helper function for export
        function exportFacultyData(facultyId) {
            showNotification('Exporting faculty data...', 'info');
            
            // Simulate export process
            setTimeout(() => {
                showNotification('Faculty data exported successfully!', 'success');
            }, 1500);
        }

        // Add CSS for modal animations
        const additionalStyles = `
        <style>
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .faculty-compact:hover {
            background-color: rgba(0, 123, 255, 0.05);
            transform: translateX(5px);
            transition: all 0.3s ease;
        }

        .faculty-avatar-small:hover {
            transform: scale(1.1);
            transition: transform 0.3s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        `;

        // Inject additional styles
        document.head.insertAdjacentHTML('beforeend', additionalStyles);

        function closeFacultyDetailsModal() {
            const modal = document.getElementById('facultyDetailsModal');
            hideModal(modal);
        }

        // Enhanced filter form with loading state
        document.getElementById('periodFilterForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('filterBtn');
            const originalContent = btn.innerHTML;
            
            btn.innerHTML = '<div class="loading-spinner"></div> Applying...';
            btn.disabled = true;
            
            // Allow form to submit normally
            setTimeout(() => {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            }, 2000);
        });

        // Enhanced viewDetails function
        function viewDetails(facultyId, docType, semester, academicYear) {
            const modal = document.getElementById('detailsModal');
            const title = document.getElementById('detailsModalTitle');
            const content = document.getElementById('detailsContent');
            
            title.textContent = `Files for ${docType}`;
            content.innerHTML = `
                <div style="text-align: center; padding: 30px;">
                    <div class="loading-spinner" style="margin: 0 auto 15px;"></div>
                    <p style="color: #6c757d;">Loading file details...</p>
                </div>
            `;
            
            showModal(modal);
            
            // Build query parameters
            const params = new URLSearchParams({
                action: 'get_file_details',
                faculty_id: facultyId,
                document_type: docType,
                semester: semester,
                academic_year: academicYear
            });
            
            fetch(`?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.files && data.files.length > 0) {
                    let html = '<div class="files-container">';
                    data.files.forEach(file => {
                        html += `
                            <div class="file-item" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f9f9f9;">
                                <div class="file-info">
                                    <h4 style="margin: 0 0 10px 0; color: #333; display: flex; align-items: center; gap: 8px;">
                                        <i class='bx bx-file'></i> ${file.file_name}
                                    </h4>
                                    <div style="margin-bottom: 5px;"><strong>Size:</strong> ${formatFileSize(file.file_size || 0)}</div>
                                    <div style="margin-bottom: 5px;"><strong>Uploaded:</strong> ${new Date(file.uploaded_at).toLocaleString()}</div>
                                    <div style="margin-bottom: 10px;"><strong>Type:</strong> ${file.file_type || docType}</div>
                                    ${file.description ? `<div style="margin-bottom: 10px;"><strong>Description:</strong> ${file.description}</div>` : ''}
                                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                                        <a href="handler/download_file.php?id=${file.id}" 
                                           class="btn btn-primary" target="_blank" style="text-decoration: none;">
                                           <i class='bx bx-download'></i> Download
                                        </a>
                                        <button class="btn btn-secondary" onclick="previewFile(${file.id}, '${file.file_name}')">
                                           <i class='bx bx-show'></i> Preview
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    content.innerHTML = html;
                } else {
                    content.innerHTML = `
                        <div style="text-align: center; padding: 30px;">
                            <i class='bx bx-error' style="font-size: 48px; color: #dc3545; margin-bottom: 15px;"></i>
                            <p style="color: #dc3545; font-weight: 600;">No files found for this document type.</p>
                            <p style="color: #6c757d;">The faculty member hasn't uploaded any files for "${docType}" in the selected period.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = `
                    <div style="text-align: center; padding: 30px;">
                        <i class='bx bx-wifi-off' style="font-size: 48px; color: #dc3545; margin-bottom: 15px;"></i>
                        <p style="color: #dc3545; font-weight: 600;">Network error occurred</p>
                        <p style="color: #6c757d;">Please check your connection and try again.</p>
                    </div>
                `;
            });
        }

        function showNotSubmittedDetails(facultyId, docType, semester, academicYear) {
            const modal = document.getElementById('detailsModal');
            const title = document.getElementById('detailsModalTitle');
            const content = document.getElementById('detailsContent');
            
            // Find faculty data for personalized message
            const faculty = facultyData.find(f => f.id == facultyId);
            const facultyName = faculty ? `${faculty.name} ${faculty.surname}` : 'Faculty Member';
            
            title.textContent = `Not Submitted: ${docType}`;
            content.innerHTML = `
                <div style="text-align: center; padding: 30px;">
                    <i class='bx bx-info-circle' style="font-size: 48px; color: #17a2b8; margin-bottom: 15px;"></i>
                    <h4 style="color: #2c3e50; margin-bottom: 10px;">Document Not Submitted</h4>
                    <p style="color: #6c757d; margin-bottom: 15px;">
                        <strong>${facultyName}</strong> has not submitted any files for <strong>"${docType}"</strong> 
                        in the selected period (${semester} ${academicYear}).
                    </p>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <h5 style="color: #495057; margin-bottom: 10px;">Actions you can take:</h5>
                        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                            <button class="btn btn-primary" onclick="sendReminder(${facultyId}, '${docType}')">
                                <i class='bx bx-bell'></i> Send Reminder
                            </button>
                            <button class="btn btn-secondary" onclick="viewFacultyProfile(${facultyId})">
                                <i class='bx bx-user'></i> View Profile
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            showModal(modal);
        }

        function closeDetailsModal() {
            const modal = document.getElementById('detailsModal');
            hideModal(modal);
        }

        function viewAllSubmissions(facultyId) {
            showNotification('Loading all submissions...', 'info');
            // Close current modal
            closeFacultyDetailsModal();
            
            // You can implement this to show all documents for a faculty member
            setTimeout(() => {
                showNotification('Feature coming soon: View all submissions', 'info');
            }, 1000);
        }

        function sendMessage(facultyId) {
            const message = prompt('Enter message to send to faculty:');
            if (!message || message.trim() === '') return;
            
            showNotification('Sending message...', 'info');
            
            // Simulate sending message
            setTimeout(() => {
                showNotification('Message sent successfully!', 'success');
                closeFacultyDetailsModal();
            }, 1500);
        }

        function sendReminder(facultyId, docType) {
            const faculty = facultyData.find(f => f.id == facultyId);
            const facultyName = faculty ? `${faculty.name} ${faculty.surname}` : 'Faculty Member';
            
            if (confirm(`Send reminder to ${facultyName} about "${docType}"?`)) {
                showNotification('Sending reminder...', 'info');
                
                setTimeout(() => {
                    showNotification('Reminder sent successfully!', 'success');
                    closeDetailsModal();
                }, 1500);
            }
        }

        function viewFacultyProfile(facultyId) {
            closeDetailsModal();
            showFacultyDetails(facultyId);
        }

        function previewFile(fileId, fileName) {
            showNotification('Loading file preview...', 'info');
            
            // You can implement file preview functionality here
            setTimeout(() => {
                showNotification(`Preview for "${fileName}" - Feature coming soon`, 'info');
            }, 1000);
        }

        // Utility functions
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else if (bytes > 1) {
                return bytes + ' bytes';
            } else if (bytes == 1) {
                return '1 byte';
            } else {
                return '0 bytes';
            }
        }

        function showNotification(message, type = 'info') {
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            const colors = {
                success: { bg: 'rgba(212, 237, 218, 0.95)', color: '#155724', border: '#28a745' },
                error: { bg: 'rgba(248, 215, 218, 0.95)', color: '#721c24', border: '#dc3545' },
                info: { bg: 'rgba(209, 236, 241, 0.95)', color: '#0c5460', border: '#17a2b8' }
            };
            
            const style = colors[type] || colors.info;
            notification.style.cssText = `
                position: fixed;
                top: 30px;
                right: 30px;
                z-index: 1001;
                padding: 15px 20px;
                border-radius: 10px;
                font-weight: 600;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                backdrop-filter: blur(10px);
                background: ${style.bg};
                color: ${style.color};
                transform: translateX(400px);
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 400);
            }, 1800);
        }

        // Event listeners
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                hideModal(event.target);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => hideModal(modal));
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Enhanced Document Tracker initialized');
            console.log(`Faculty data loaded: ${facultyData.length} members`);
            
            // Add smooth scrolling to horizontal table scroll
            const tableWrapper = document.querySelector('.table-scroll-wrapper');
            if (tableWrapper) {
                tableWrapper.style.scrollBehavior = 'smooth';
            }

            // Initialize progress circles animation
            const progressCircles = document.querySelectorAll('.progress-circle');
            progressCircles.forEach(circle => {
                const progress = parseInt(circle.dataset.progress);
                const progressCircle = circle.querySelector('circle:last-child');
                if (progressCircle) {
                    const radius = 24;
                    const circumference = 2 * Math.PI * radius;
                    const offset = circumference * (1 - progress / 100);
                    
                    progressCircle.style.strokeDasharray = circumference;
                    progressCircle.style.strokeDashoffset = circumference;
                    
                    setTimeout(() => {
                        progressCircle.style.transition = 'stroke-dashoffset 1s ease-in-out';
                        progressCircle.style.strokeDashoffset = offset;
                    }, 500);
                }
            });
        });
    </script>