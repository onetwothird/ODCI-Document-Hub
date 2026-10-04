<?php include 'assets/script/settings.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/settings.css?v=<?= time() ?>">
    

    <!-- Shared CVSU design system (green / gold / white) - loaded last on purpose -->
    <?php include __DIR__ . '/../../includes/theme.php'; ?>
</head>
<body>
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class='bx bx-cog'></i>
                    System Settings
                    <span class="super-admin-badge">SUPER ADMIN</span>
                </h1>
            </div>

            <!-- Alert Messages -->
            <div id="alertContainer">
                <!-- Success Alert Template -->
                <div class="alert success" id="successAlert" style="display: none;">
                    <i class='bx bx-check-circle'></i>
                    <span id="successMessage"></span>
                </div>
                
                <!-- Error Alert Template -->
                <div class="alert error" id="errorAlert" style="display: none;">
                    <i class='bx bx-error-circle'></i>
                    <span id="errorMessage"></span>
                </div>
            </div>

            <!-- Settings Container -->
            <div class="settings-container">
                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="showTab('profile')">
                        <i class='bx bx-user'></i> Profile
                    </button>
                    <button class="tab-btn" onclick="showTab('security')">
                        <i class='bx bx-shield'></i> Security
                    </button>
                    <button class="tab-btn" onclick="showTab('system')">
                        <i class='bx bx-cog'></i> System
                    </button>
                    <button class="tab-btn" onclick="showTab('stats')">
                        <i class='bx bx-stats'></i> Statistics
                    </button>
                </div>

                <!-- Profile Settings Tab -->
                <div id="profile" class="tab-content active">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class='bx bx-user'></i> Profile Information
                        </h3>
                        
                        <form method="POST" enctype="multipart/form-data" id="profileForm">
                            <!-- Profile Picture Section -->
                            <div class="profile-upload-area">
                                <div class="profile-image-container">
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" 
                                        alt="Profile Picture" 
                                        id="profilePreview" 
                                        class="profile-image"
                                        loading="lazy">
                                    
                                    <button type="button" 
                                            onclick="removeProfileImage()" 
                                            class="remove-image-btn"
                                            title="Remove profile image"
                                            style="<?php echo (!empty($user['profile_image'])) ? 'display: flex;' : 'display: none;' ?>"
                                            id="removeImageBtn">
                                        <i class='bx bx-x'></i>
                                    </button>
                                </div>
                                
                                <div class="upload-controls">
                                    <input type="file" 
                                        name="profile_picture" 
                                        id="profilePicture" 
                                        accept="image/*" 
                                        class="file-input" 
                                        onchange="previewImage(this)">
                                    <button type="button" 
                                            onclick="document.getElementById('profilePicture').click();" 
                                            class="btn btn-outline">
                                        <i class='bx bx-upload'></i> 
                                        Upload Photo
                                    </button>
                                    <p class="upload-hint">JPG, PNG, GIF, or WEBP (max 5MB)</p>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="field-group">
                                    <label for="name" class="field-label">
                                        First Name <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                        name="name" 
                                        id="name" 
                                        value="<?php echo !empty($user['name']) ? htmlspecialchars($user['name']) : 'System'; ?>" 
                                        required 
                                        class="form-input"
                                        oninput="validateField(this)">
                                    <div class="field-validation" id="nameValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="mi" class="field-label">Middle Initial</label>
                                    <input type="text" 
                                        name="mi" 
                                        id="mi" 
                                        value="<?php echo !empty($user['mi']) ? htmlspecialchars($user['mi']) : ''; ?>" 
                                        maxlength="5" 
                                        class="form-input"
                                        oninput="validateField(this)">
                                    <div class="field-validation" id="miValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="surname" class="field-label">
                                        Last Name <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                        name="surname" 
                                        id="surname" 
                                        value="<?php echo !empty($user['surname']) ? htmlspecialchars($user['surname']) : 'Administrator'; ?>" 
                                        required 
                                        class="form-input"
                                        oninput="validateField(this)">
                                    <div class="field-validation" id="surnameValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="employee_id" class="field-label">Employee ID</label>
                                    <input type="text" 
                                        name="employee_id" 
                                        id="employee_id" 
                                        value="ADMIN001" 
                                        class="form-input"
                                        oninput="validateField(this)">
                                    <div class="field-validation" id="employeeIdValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="position" class="field-label">Position</label>
                                    <input type="text" 
                                        name="position" 
                                        id="position" 
                                        value="System Administrator" 
                                        class="form-input"
                                        oninput="validateField(this)">
                                    <div class="field-validation" id="positionValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="phone" class="field-label">Phone Number</label>
                                    <div class="phone-input">
                                        <span class="phone-prefix">+63</span>
                                        <input type="tel" 
                                            name="phone" 
                                            id="phone" 
                                            value="" 
                                            placeholder="9123456789" 
                                            class="form-input"
                                            pattern="9[0-9]{9}"
                                            maxlength="10"
                                            oninput="validatePhone(this)">
                                    </div>
                                    <div class="field-validation" id="phoneValidation">Format: 9XXXXXXXXX</div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="date_of_birth" class="field-label">Date of Birth</label>
                                    <input type="date" 
                                        name="date_of_birth" 
                                        id="date_of_birth" 
                                        value="" 
                                        max="2007-01-01"
                                        min="1930-01-01"
                                        class="form-input"
                                        onchange="validateField(this)">
                                    <div class="field-validation" id="dobValidation">Must be at least 16 years old</div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="hire_date" class="field-label">Hire Date</label>
                                    <input type="date" 
                                        value="" 
                                        readonly 
                                        class="form-input form-input-readonly">
                                    <div class="field-validation">Set by administrator</div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="email" class="field-label">Email Address</label>
                                    <input type="email" 
                                        name="email" 
                                        id="email" 
                                        value="admin@cvsu.edu.ph" 
                                        readonly 
                                        class="form-input form-input-readonly">
                                    <div class="field-validation">Contact administrator to change email</div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="department" class="field-label">Department</label>
                                    <input type="text" 
                                        value="Information Technology Department" 
                                        readonly 
                                        class="form-input form-input-readonly">
                                    <div class="field-validation">Set by administrator</div>
                                </div>
                                
                                <div class="field-group form-grid-full">
                                    <label for="address" class="field-label">Address</label>
                                    <textarea name="address" 
                                            id="address" 
                                            rows="3" 
                                            placeholder="Enter your complete address..." 
                                            class="form-textarea"
                                            oninput="validateField(this)"></textarea>
                                    <div class="field-validation" id="addressValidation"></div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary" id="saveProfileBtn">
                                    <i class='bx bx-save'></i> Save Changes
                                </button>
                                <button type="reset" class="btn btn-secondary" onclick="resetProfileForm()">
                                    <i class='bx bx-reset'></i> Reset Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Settings Tab -->
                <div id="security" class="tab-content">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class='bx bx-shield'></i> Change Password
                        </h3>
                        
                        <form method="POST" id="passwordForm">
                            <div class="password-form-grid">
                                <div class="field-group">
                                    <label for="current_password" class="field-label">
                                        Current Password <span class="required">*</span>
                                    </label>
                                    <input type="password" 
                                        name="current_password" 
                                        id="current_password" 
                                        required 
                                        class="form-input"
                                        oninput="validateCurrentPassword(this)">
                                    <div class="field-validation" id="currentPasswordValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="new_password" class="field-label">
                                        New Password <span class="required">*</span>
                                    </label>
                                    <input type="password" 
                                        name="new_password" 
                                        id="new_password" 
                                        required 
                                        minlength="8" 
                                        class="form-input"
                                        oninput="validateNewPassword(this)">
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                    </div>
                                    <div class="field-validation" id="newPasswordValidation">Password must be at least 8 characters long</div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="confirm_password" class="field-label">
                                        Confirm New Password <span class="required">*</span>
                                    </label>
                                    <input type="password" 
                                        name="confirm_password" 
                                        id="confirm_password" 
                                        required 
                                        minlength="8" 
                                        class="form-input"
                                        oninput="validateConfirmPassword(this)">
                                    <div class="field-validation" id="confirmPasswordValidation"></div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" 
                                        name="change_password" 
                                        class="btn btn-primary" 
                                        id="changePasswordBtn" 
                                        disabled>
                                    <i class='bx bx-key'></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- System Settings Tab -->
                <div id="system" class="tab-content">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class='bx bx-cog'></i> System Configuration
                        </h3>
                        
                        <form method="POST" id="systemSettingsForm">
                            <div class="system-settings-grid">
                                <div class="setting-item">
                                    <label class="setting-label">Maintenance Mode</label>
                                    <p class="setting-description">Enable maintenance mode to temporarily disable user access</p>
                                    
                                    <div class="toggle-switch">
                                        <input type="checkbox" 
                                            name="system_settings[maintenance_mode]" 
                                            id="setting_maintenance_mode" 
                                            value="1">
                                        <label for="setting_maintenance_mode" class="toggle-label"></label>
                                    </div>
                                </div>

                                <div class="setting-item">
                                    <label class="setting-label">Max File Size</label>
                                    <p class="setting-description">Maximum file upload size in MB</p>
                                    
                                    <input type="number" 
                                        name="system_settings[max_file_size]" 
                                        value="10" 
                                        min="1" 
                                        max="100"
                                        class="form-input">
                                </div>

                                <div class="setting-item">
                                    <label class="setting-label">Session Timeout</label>
                                    <p class="setting-description">Session timeout in minutes</p>
                                    
                                    <input type="number" 
                                        name="system_settings[session_timeout]" 
                                        value="60" 
                                        min="5" 
                                        max="480"
                                        class="form-input">
                                </div>

                                <div class="setting-item">
                                    <label class="setting-label">Email Notifications</label>
                                    <p class="setting-description">Enable email notifications for system events</p>
                                    
                                    <div class="toggle-switch">
                                        <input type="checkbox" 
                                            name="system_settings[email_notifications]" 
                                            id="setting_email_notifications" 
                                            value="1" 
                                            checked>
                                        <label for="setting_email_notifications" class="toggle-label"></label>
                                    </div>
                                </div>

                                <div class="setting-item">
                                    <label class="setting-label">Auto Backup</label>
                                    <p class="setting-description">Enable automatic database backups</p>
                                    
                                    <div class="toggle-switch">
                                        <input type="checkbox" 
                                            name="system_settings[auto_backup]" 
                                            id="setting_auto_backup" 
                                            value="1" 
                                            checked>
                                        <label for="setting_auto_backup" class="toggle-label"></label>
                                    </div>
                                </div>

                                <div class="setting-item">
                                    <label class="setting-label">Registration Approval</label>
                                    <p class="setting-description">Require admin approval for new user registrations</p>
                                    
                                    <div class="toggle-switch">
                                        <input type="checkbox" 
                                            name="system_settings[registration_approval]" 
                                            id="setting_registration_approval" 
                                            value="1" 
                                            checked>
                                        <label for="setting_registration_approval" class="toggle-label"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_system_settings" class="btn btn-primary">
                                    <i class='bx bx-save'></i> Save System Settings
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="security-section">
                        <h3 class="section-title">
                            <i class='bx bx-wrench'></i> Admin Actions
                        </h3>
                        
                        <div class="admin-actions">
                            <form method="POST" 
                                class="admin-action-btn" 
                                onsubmit="return confirm('Are you sure you want to clear the system cache?');"
                                style="border: none; padding: 0; background: none;">
                                <button type="submit" 
                                        name="clear_cache" 
                                        class="admin-action-btn">
                                    <div class="admin-action-icon">
                                        <i class='bx bx-trash'></i>
                                    </div>
                                    <div class="admin-action-label">Clear System Cache</div>
                                </button>
                            </form>
                            
                            <a href="system_logs.php" class="admin-action-btn">
                                <div class="admin-action-icon">
                                    <i class='bx bx-file'></i>
                                </div>
                                <div class="admin-action-label">View System Logs</div>
                            </a>
                            
                            <a href="user_management.php" class="admin-action-btn">
                                <div class="admin-action-icon">
                                    <i class='bx bx-user-pin'></i>
                                </div>
                                <div class="admin-action-label">Manage Users</div>
                            </a>
                            
                            <a href="backup.php" class="admin-action-btn">
                                <div class="admin-action-icon">
                                    <i class='bx bx-data'></i>
                                </div>
                                <div class="admin-action-label">Backup Database</div>
                            </a>
                        </div>
                        
                        <div class="cache-info">
                            <p><strong>Cache Information:</strong> Clearing the cache will remove temporary files and may improve system performance.</p>
                            <p>Last cleared: September 2, 2025, 3:15 PM</p>
                        </div>
                    </div>
                </div>

                <!-- Statistics Tab -->
                <div id="stats" class="tab-content">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class='bx bx-stats'></i> System Statistics
                        </h3>
                        
                        <div class="system-stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: rgba(52, 152, 219, 0.1); color: #3498db;">
                                    <i class='bx bx-user'></i>
                                </div>
                                <div class="stat-value">34</div>
                                <div class="stat-label">Total Users</div>
                                <div class="stat-details">
                                    <span class="stat-detail-item">32 Approved</span>
                                    <span class="stat-detail-item">2 Pending</span>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                                    <i class='bx bx-building'></i>
                                </div>
                                <div class="stat-value">8</div>
                                <div class="stat-label">Departments</div>
                                <div class="stat-details">
                                    <span class="stat-detail-item">8 Active</span>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                                    <i class='bx bx-file'></i>
                                </div>
                                <div class="stat-value">156</div>
                                <div class="stat-label">Files</div>
                                <div class="stat-details">
                                    <span class="stat-detail-item">148 Active</span>
                                    <span class="stat-detail-item">1,234 Downloads</span>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                                    <i class='bx bx-megaphone'></i>
                                </div>
                                <div class="stat-value">12</div>
                                <div class="stat-label">Announcements</div>
                                <div class="stat-details">
                                    <span class="stat-detail-item">8 Published</span>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: rgba(230, 126, 34, 0.1); color: #e67e22;">
                                    <i class='bx bx-task'></i>
                                </div>
                                <div class="stat-value">89</div>
                                <div class="stat-label">Document Requests</div>
                                <div class="stat-details">
                                    <span class="stat-detail-item">23 Pending</span>
                                    <span class="stat-detail-item">66 Completed</span>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: rgba(231, 76, 60, 0.1); color: #e74c3c;">
                                    <i class='bx bx-shield'></i>
                                </div>
                                <div class="stat-value">1</div>
                                <div class="stat-label">Super Admins</div>
                                <div class="stat-details">
                                    <span class="stat-detail-item">2 Admins</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Form for removing profile image -->
    <form method="POST" id="removeImageForm" style="display: none;">
        <input type="hidden" name="remove_profile_image" value="1">
    </form>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script>
        // Global variables
        let currentUser = {
            name: '<?php echo $userName; ?>',
            mi: '<?php echo !empty($user['mi']) ? $user['mi'] : ''; ?>',
            surname: '<?php echo !empty($user['surname']) ? $user['surname'] : ''; ?>',
            employee_id: '<?php echo !empty($user['employee_id']) ? $user['employee_id'] : ''; ?>',
            position: '<?php echo !empty($user['position']) ? $user['position'] : ''; ?>',
            phone: '<?php echo !empty($user['phone']) ? $user['phone'] : ''; ?>',
            address: '<?php echo !empty($user['address']) ? $user['address'] : ''; ?>',
            date_of_birth: '<?php echo !empty($user['date_of_birth']) ? $user['date_of_birth'] : ''; ?>',
            email: '<?php echo !empty($user['email']) ? $user['email'] : ''; ?>',
            profile_image: '<?php echo !empty($user['profile_image']) ? $user['profile_image'] : ''; ?>'
        };

        // Tab navigation
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Update active tab button
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.querySelector(`.tab-btn[onclick="showTab('${tabId}')"]`).classList.add('active');
            
            // Save active tab to localStorage
            localStorage.setItem('activeTab', tabId);
        }

        // Show alerts
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertElement = document.getElementById(type + 'Alert');
            const messageElement = document.getElementById(type + 'Message');
            
            messageElement.textContent = message;
            alertElement.style.display = 'flex';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertElement.style.opacity = '0';
                setTimeout(() => {
                    alertElement.style.display = 'none';
                    alertElement.style.opacity = '1';
                }, 300);
            }, 1800);
        }

        // Profile image preview with enhanced validation
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                if (!file.type.match(/^image\/(jpeg|jpg|png|gif|webp)$/)) {
                    showAlert('error', 'Please select a valid image file (JPG, PNG, GIF, or WEBP)');
                    input.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    showAlert('error', 'File size must be less than 5MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePreview');
                    preview.src = e.target.result;
                    
                    // Force consistent sizing for the preview
                    preview.style.width = '120px';
                    preview.style.height = '120px';
                    preview.style.objectFit = 'cover';
                    
                    document.getElementById('removeImageBtn').style.display = 'flex';
                }
                reader.readAsDataURL(file);
            }
        }

        // Remove profile image
        function removeProfileImage() {
            if (confirm('Are you sure you want to remove your profile image?')) {
                document.getElementById('removeImageForm').submit();
            }
        }

        // Field validation functions
        function validateField(input) {
            const fieldName = input.name;
            const value = input.value.trim();
            const validationElement = document.getElementById(fieldName + 'Validation');
            
            switch(fieldName) {
                case 'name':
                case 'surname':
                    if (value.length === 0) {
                        setValidation(validationElement, 'This field is required', 'error');
                        return false;
                    } else if (value.length < 2) {
                        setValidation(validationElement, 'Must be at least 2 characters', 'error');
                        return false;
                    } else {
                        setValidation(validationElement, 'Valid', 'success');
                        return true;
                    }
                
                case 'mi':
                    if (value.length > 5) {
                        setValidation(validationElement, 'Maximum 5 characters', 'error');
                        return false;
                    } else {
                        setValidation(validationElement, '', '');
                        return true;
                    }
                
                case 'employee_id':
                    if (value.length > 0 && value.length < 3) {
                        setValidation(validationElement, 'Must be at least 3 characters', 'error');
                        return false;
                    } else if (value.length > 20) {
                        setValidation(validationElement, 'Maximum 20 characters', 'error');
                        return false;
                    } else {
                        setValidation(validationElement, '', '');
                        return true;
                    }
                
                case 'date_of_birth':
                    if (value) {
                        const birthDate = new Date(value);
                        const today = new Date();
                        const age = today.getFullYear() - birthDate.getFullYear();
                        
                        if (age < 16) {
                            setValidation(validationElement, 'Must be at least 16 years old', 'error');
                            return false;
                        } else if (age > 100) {
                            setValidation(validationElement, 'Please enter a valid birth date', 'error');
                            return false;
                        } else {
                            setValidation(validationElement, 'Valid', 'success');
                            return true;
                        }
                    } else {
                        setValidation(validationElement, 'Must be at least 16 years old', '');
                        return true;
                    }
                
                default:
                    setValidation(validationElement, '', '');
                    return true;
            }
        }

        function validatePhone(input) {
            const value = input.value.trim();
            const validationElement = document.getElementById('phoneValidation');
            
            if (value.length === 0) {
                setValidation(validationElement, 'Format: 9XXXXXXXXX', '');
                return true;
            }
            
            if (!/^9[0-9]{0,9}$/.test(value)) {
                setValidation(validationElement, 'Must start with 9 and contain only numbers', 'error');
                return false;
            }
            
            if (value.length < 10) {
                setValidation(validationElement, 'Must be exactly 10 digits', 'error');
                return false;
            }
            
            if (value.length === 10) {
                setValidation(validationElement, 'Valid phone number', 'success');
                return true;
            }
            
            return false;
        }

        function setValidation(element, message, type) {
            if (!element) return;
            
            element.textContent = message;
            element.className = 'field-validation';
            
            if (type) {
                element.classList.add(type);
            }
        }

        // Password validation
        function validateCurrentPassword(input) {
            const value = input.value;
            const validationElement = document.getElementById('currentPasswordValidation');
            
            if (value.length === 0) {
                setValidation(validationElement, 'Current password is required', 'error');
                return false;
            } else {
                setValidation(validationElement, '', '');
                return true;
            }
        }

        function validateNewPassword(input) {
            const password = input.value;
            const validationElement = document.getElementById('newPasswordValidation');
            const strengthBar = document.getElementById('passwordStrengthBar');
            
            if (password.length === 0) {
                setValidation(validationElement, 'Password must be at least 8 characters long', '');
                strengthBar.style.width = '0%';
                updatePasswordButton();
                return false;
            }
            
            if (password.length < 8) {
                setValidation(validationElement, 'Password must be at least 8 characters long', 'error');
                strengthBar.style.width = '25%';
                strengthBar.style.backgroundColor = '#ef4444';
                updatePasswordButton();
                return false;
            }
            
            // Calculate password strength
            let strength = 0;
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            // Update strength bar
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.style.backgroundColor = '#ef4444';
                setValidation(validationElement, 'Weak password', 'error');
            } else if (strength < 75) {
                strengthBar.style.backgroundColor = '#f59e0b';
                setValidation(validationElement, 'Medium strength', '');
            } else {
                strengthBar.style.backgroundColor = '#10b981';
                setValidation(validationElement, 'Strong password', 'success');
            }
            
            updatePasswordButton();
            return strength >= 50;
        }

        function validateConfirmPassword(input) {
            const password = document.getElementById('new_password').value;
            const confirm = input.value;
            const validationElement = document.getElementById('confirmPasswordValidation');
            
            if (confirm.length === 0) {
                setValidation(validationElement, '', '');
                updatePasswordButton();
                return false;
            }
            
            if (password !== confirm) {
                setValidation(validationElement, 'Passwords do not match', 'error');
                updatePasswordButton();
                return false;
            } else {
                setValidation(validationElement, 'Passwords match', 'success');
                updatePasswordButton();
                return true;
            }
        }

        function updatePasswordButton() {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const changePasswordBtn = document.getElementById('changePasswordBtn');
            
            const isValid = currentPassword.length > 0 && 
                          newPassword.length >= 8 && 
                          newPassword === confirmPassword;
            
            changePasswordBtn.disabled = !isValid;
        }

        // Reset profile form
        function resetProfileForm() {
            const form = document.getElementById('profileForm');
            form.reset();
            
            // Reset profile image
            document.getElementById('profilePreview').src = 'assets/img/cvsu-logo.png';
            document.getElementById('removeImageBtn').style.display = 'none';
            
            // Clear all validation messages
            document.querySelectorAll('.field-validation').forEach(element => {
                element.textContent = '';
                element.className = 'field-validation';
            });
            
            // Set default validation messages
            document.getElementById('phoneValidation').textContent = 'Format: 9XXXXXXXXX';
            document.getElementById('dobValidation').textContent = 'Must be at least 16 years old';
        }

        // Enhanced form submission handling with profile image fixes
        document.addEventListener('DOMContentLoaded', function() {
            // Restore active tab
            const activeTab = localStorage.getItem('activeTab') || 'profile';
            showTab(activeTab);
            
            // Force consistent profile image sizing on page load
            const profilePreview = document.getElementById('profilePreview');
            if (profilePreview) {
                profilePreview.style.width = '120px';
                profilePreview.style.height = '120px';
                profilePreview.style.objectFit = 'cover';
            }
            
            // Add form submission handlers
            document.getElementById('profileForm').addEventListener('submit', function(e) {
                // Validate all fields before submission
                let isValid = true;
                const requiredFields = ['name', 'surname'];
                
                requiredFields.forEach(fieldName => {
                    const field = document.getElementById(fieldName);
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });
                
                const phoneField = document.getElementById('phone');
                if (phoneField.value.trim() && !validatePhone(phoneField)) {
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    showAlert('error', 'Please fix the validation errors before submitting');
                }
            });
            
            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                const currentPassword = document.getElementById('current_password').value;
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (!currentPassword || !newPassword || !confirmPassword) {
                    e.preventDefault();
                    showAlert('error', 'All password fields are required');
                    return;
                }
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    showAlert('error', 'New password must be at least 8 characters long');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showAlert('error', 'New passwords do not match');
                    return;
                }
            });
            
            // Add real-time validation to all form fields
            document.querySelectorAll('.form-input, .form-textarea').forEach(input => {
                if (input.name && input.name !== 'profile_picture') {
                    input.addEventListener('blur', function() {
                        if (this.name === 'phone') {
                            validatePhone(this);
                        } else {
                            validateField(this);
                        }
                    });
                }
            });
            
            // Phone number formatting
            document.getElementById('phone').addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^0-9]/g, '');
                if (value.length > 10) {
                    value = value.substr(0, 10);
                }
                e.target.value = value;
                validatePhone(e.target);
            });
            
            // Additional fix for navbar profile image synchronization
            const navbarProfileImg = document.querySelector('.profile-image img, nav .profile img');
            if (navbarProfileImg) {
                navbarProfileImg.style.width = '40px';
                navbarProfileImg.style.height = '40px';
                navbarProfileImg.style.objectFit = 'cover';
            }
        });

        // Function to update navbar profile image when settings profile is updated
        function updateNavbarProfile(newImageSrc) {
            const navbarProfileImg = document.querySelector('.profile-image img, nav .profile img');
            if (navbarProfileImg) {
                navbarProfileImg.src = newImageSrc;
                // Ensure consistent sizing
                navbarProfileImg.style.width = '40px';
                navbarProfileImg.style.height = '40px';
                navbarProfileImg.style.objectFit = 'cover';
            }
        }

        // Enhanced image loading error handling
        document.addEventListener('DOMContentLoaded', function() {
            const allProfileImages = document.querySelectorAll('img[src*="profile"], img[class*="profile"]');
            
            allProfileImages.forEach(img => {
                img.addEventListener('error', function() {
                    this.src = 'assets/img/cvsu-lgo.png';
                });
                
                img.addEventListener('load', function() {
                    // Force consistent sizing after image loads
                    if (this.closest('.profile-image-container')) {
                        // Settings page profile image
                        this.style.width = '120px';
                        this.style.height = '120px';
                    } else if (this.closest('.profile-image, .profile')) {
                        // Navbar profile image
                        this.style.width = '40px';
                        this.style.height = '40px';
                    }
                    this.style.objectFit = 'cover';
                });
            });
        });
    </script>
</body>
</html>
