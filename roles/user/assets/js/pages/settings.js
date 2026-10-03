// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');
    if (successAlert) {
        successAlert.style.transition = 'all 0.3s ease';
        successAlert.style.opacity = '0';
        successAlert.style.transform = 'translateY(-10px)';
        setTimeout(() => successAlert.remove(), 300);
    }
    if (errorAlert) {
        errorAlert.style.transition = 'all 0.3s ease';
        errorAlert.style.opacity = '0';
        errorAlert.style.transform = 'translateY(-10px)';
        setTimeout(() => errorAlert.remove(), 300);
    }
}, 1800);

// Tab functionality
function showTab(tabName) {
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tab => {
        tab.style.display = 'none';
    });
    
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => {
        btn.classList.remove('active');
        btn.style.background = 'transparent';
        btn.style.color = 'var(--dark)';
    });
    
    document.getElementById(tabName).style.display = 'block';
    
    event.target.classList.add('active');
    event.target.style.background = 'linear-gradient(135deg, var(--blue) 0%, #2980b9 100%)';
    event.target.style.color = 'white';
}

// Profile image preview with validation
function previewImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File too large. Maximum size is 5MB.');
            input.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('profilePreview');
            preview.src = e.target.result;
            preview.classList.add('image-preview-animation');
            
            // Update button text
            const uploadBtn = document.querySelector('button[onclick*="profilePicture"]');
            if (uploadBtn) {
                uploadBtn.innerHTML = '<i class="bx bx-upload" style="font-size: 16px;"></i> Change Photo';
            }
        }
        
        reader.onerror = function() {
            alert('Error reading the selected file. Please try again.');
            input.value = '';
        }
        
        reader.readAsDataURL(file);
    }
}

// Remove profile image with confirmation
function removeProfileImage() {
    if (confirm('Are you sure you want to remove your profile image?')) {
        document.getElementById('removeImageForm').submit();
    }
}

// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    let feedback = [];

    if (password.length >= 8) strength++;
    else feedback.push('At least 8 characters');

    if (password.match(/[a-z]/)) strength++;
    else feedback.push('Lowercase letter');

    if (password.match(/[A-Z]/)) strength++;
    else feedback.push('Uppercase letter');

    if (password.match(/[0-9]/)) strength++;
    else feedback.push('Number');

    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    else feedback.push('Special character');

    return { strength, feedback };
}

// Real-time validation
document.addEventListener('DOMContentLoaded', function() {
    // Profile form validation
    const nameInput = document.getElementById('name');
    const surnameInput = document.getElementById('surname');
    const phoneInput = document.getElementById('phone');
    const dobInput = document.getElementById('date_of_birth');

    // Name validation
    nameInput.addEventListener('input', function() {
        const validation = document.getElementById('nameValidation');
        if (this.value.trim().length < 2) {
            validation.textContent = 'Name must be at least 2 characters';
            validation.className = 'field-validation invalid';
        } else if (!/^[a-zA-Z\s]+$/.test(this.value)) {
            validation.textContent = 'Name should only contain letters';
            validation.className = 'field-validation invalid';
        } else {
            validation.textContent = '✓ Valid';
            validation.className = 'field-validation valid';
        }
    });

    // Surname validation
    surnameInput.addEventListener('input', function() {
        const validation = document.getElementById('surnameValidation');
        if (this.value.trim().length < 2) {
            validation.textContent = 'Surname must be at least 2 characters';
            validation.className = 'field-validation invalid';
        } else if (!/^[a-zA-Z\s]+$/.test(this.value)) {
            validation.textContent = 'Surname should only contain letters';
            validation.className = 'field-validation invalid';
        } else {
            validation.textContent = '✓ Valid';
            validation.className = 'field-validation valid';
        }
    });

    // Phone validation with formatting
    phoneInput.addEventListener('input', function() {
        const validation = document.getElementById('phoneValidation');
        let value = this.value.replace(/\D/g, '');
        
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        
        // Format: 912 345 6789
        if (value.length >= 6) {
            value = value.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3');
        } else if (value.length >= 3) {
            value = value.replace(/(\d{3})(\d+)/, '$1 $2');
        }
        
        this.value = value;
        
        if (value.replace(/\s/g, '').length === 10 && /^9/.test(value)) {
            validation.textContent = '✓ Valid Philippine mobile number';
            validation.className = 'field-validation valid';
        } else if (value.length === 0) {
            validation.textContent = '';
            validation.className = 'field-validation';
        } else {
            validation.textContent = 'Enter valid 10-digit mobile number starting with 9';
            validation.className = 'field-validation invalid';
        }
    });

    // Date of birth validation
    dobInput.addEventListener('change', function() {
        const validation = document.getElementById('dobValidation');
        if (this.value) {
            const birthDate = new Date(this.value);
            const today = new Date();
            const age = Math.floor((today - birthDate) / (365.25 * 24 * 60 * 60 * 1000));
            
            if (age < 16) {
                validation.textContent = 'Must be at least 16 years old';
                validation.className = 'field-validation invalid';
            } else if (age > 120) {
                validation.textContent = 'Please enter a valid birth date';
                validation.className = 'field-validation invalid';
            } else {
                validation.textContent = `✓ Age: ${age} years`;
                validation.className = 'field-validation valid';
            }
        }
    });

    // Password form validation
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const currentPasswordInput = document.getElementById('current_password');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const strengthBar = document.getElementById('passwordStrengthBar');

    function validatePasswordForm() {
        const currentPassword = currentPasswordInput.value;
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        const isValid = currentPassword.length > 0 && 
                        newPassword.length >= 8 && 
                        newPassword === confirmPassword;
        
        changePasswordBtn.disabled = !isValid;
    }

    // New password validation with strength indicator
    newPasswordInput.addEventListener('input', function() {
        const { strength, feedback } = checkPasswordStrength(this.value);
        const validation = document.getElementById('newPasswordValidation');
        
        // Update strength bar
        strengthBar.className = 'password-strength-bar';
        if (strength >= 4) {
            strengthBar.classList.add('strength-strong');
            validation.textContent = '✓ Strong password';
            validation.className = 'field-validation valid';
        } else if (strength >= 3) {
            strengthBar.classList.add('strength-good');
            validation.textContent = 'Good password. Missing: ' + feedback.join(', ');
            validation.className = 'field-validation';
        } else if (strength >= 2) {
            strengthBar.classList.add('strength-fair');
            validation.textContent = 'Fair password. Missing: ' + feedback.join(', ');
            validation.className = 'field-validation invalid';
        } else if (this.value.length > 0) {
            strengthBar.classList.add('strength-weak');
            validation.textContent = 'Weak password. Missing: ' + feedback.join(', ');
            validation.className = 'field-validation invalid';
        } else {
            validation.textContent = 'Password must be at least 8 characters long';
            validation.className = 'field-validation';
        }
        
        validatePasswordForm();
    });

    // Confirm password validation
    confirmPasswordInput.addEventListener('input', function() {
        const validation = document.getElementById('confirmPasswordValidation');
        const newPassword = newPasswordInput.value;
        
        if (this.value === newPassword && newPassword.length > 0) {
            validation.textContent = '✓ Passwords match';
            validation.className = 'field-validation valid';
        } else if (this.value.length > 0) {
            validation.textContent = 'Passwords do not match';
            validation.className = 'field-validation invalid';
        } else {
            validation.textContent = '';
            validation.className = 'field-validation';
        }
        
        validatePasswordForm();
    });

    currentPasswordInput.addEventListener('input', validatePasswordForm);

    // Form submission validation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        const surname = document.getElementById('surname').value.trim();
        
        if (!name || !surname) {
            e.preventDefault();
            alert('First name and last name are required!');
            return false;
        }

        if (name.length < 2 || surname.length < 2) {
            e.preventDefault();
            alert('Name and surname must be at least 2 characters long!');
            return false;
        }
    });

    // Reset form functionality
    document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) {
            e.preventDefault();
        } else {
            // Clear validation messages
            document.querySelectorAll('.field-validation').forEach(el => {
                el.textContent = '';
                el.className = 'field-validation';
            });
            
            // Reset profile image preview
            document.getElementById('profilePreview').src = '<?php echo addslashes($profileImage); ?>';
        }
    });
});
// Tab functionality
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.style.display = 'none';
        content.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => {
        btn.classList.remove('active');
        btn.style.background = 'transparent';
        btn.style.color = 'var(--dark)';
        btn.style.boxShadow = 'none';
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabName);
    if (selectedTab) {
        selectedTab.style.display = 'block';
        selectedTab.classList.add('active');
    }
    
    // Add active class to clicked button
    event.target.classList.add('active');
    event.target.style.background = 'linear-gradient(135deg, var(--blue) 0%, #2980b9 100%)';
    event.target.style.color = 'white';
    event.target.style.boxShadow = '0 4px 8px rgba(52, 152, 219, 0.3)';
}

// Profile picture preview
document.getElementById('profilePicture').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        // Check file size (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
        this.style.borderColor = '#dc3545';
    } else {
        this.setCustomValidity('');
        this.style.borderColor = '#28a745';
    }
});

// Password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strength = getPasswordStrength(password);
    
    // You can add a visual password strength indicator here
    if (password.length < 8) {
        this.style.borderColor = '#dc3545';
    } else if (strength < 3) {
        this.style.borderColor = '#ffc107';
    } else {
        this.style.borderColor = '#28a745';
    }
});

function getPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    return strength;
}

// Modal functions
function showDeletionModal() {
    document.getElementById('deletionModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function hideDeletionModal() {
    document.getElementById('deletionModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('deletionForm').reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deletionModal');
    if (event.target == modal) {
        hideDeletionModal();
    }
}

// Data export request
function requestDataExport() {
    if (confirm('Do you want to export all your account data? You will receive a download link via email within 24 hours.')) {
        // Show loading state
        event.target.innerHTML = '<i class="bx bx-loader-alt" style="animation: spin 1s linear infinite;"></i> Processing...';
        event.target.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            alert('Data export request submitted successfully! You will receive a download link via email within 24 hours.');
            event.target.innerHTML = '<i class="bx bx-download"></i> Export Data';
            event.target.disabled = false;
        }, 2000);
    }
}

// Form reset function
function resetForm() {
    if (confirm('Are you sure you want to reset all changes?')) {
        location.reload();
    }
}

// Toggle switches functionality
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('input[type="checkbox"]');
    
    toggles.forEach(toggle => {
        if (toggle.id !== 'switch-mode') { // Exclude the main dark mode toggle
            toggle.addEventListener('change', function() {
                const slider = this.nextElementSibling;
                const knob = slider.nextElementSibling;
                
                if (this.checked) {
                    slider.style.background = 'linear-gradient(135deg, var(--blue), #2980b9)';
                    slider.style.boxShadow = '0 4px 12px rgba(52, 152, 219, 0.3)';
                    knob.style.transform = 'translateX(30px)';
                } else {
                    slider.style.background = '#ccc';
                    slider.style.boxShadow = 'none';
                    knob.style.transform = 'translateX(0)';
                }
            });
        }
    });
});

// Sidebar functionality (from original dashboard)
const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

allSideMenu.forEach(item=> {
    const li = item.parentElement;

    item.addEventListener('click', function () {
        allSideMenu.forEach(i=> {
            i.parentElement.classList.remove('active');
        })
        li.classList.add('active');
    })
});

// Toggle sidebar
const menuBar = document.querySelector('#content nav .toggle-sidebar');
const sidebar = document.getElementById('sidebar');

menuBar.addEventListener('click', function () {
    sidebar.classList.toggle('hide');
})

// Search functionality
const searchButton = document.querySelector('#content nav form .form-input button');
const searchButtonIcon = document.querySelector('#content nav form .form-input button .bx');
const searchForm = document.querySelector('#content nav form');

searchButton.addEventListener('click', function (e) {
    if(window.innerWidth < 576) {
        e.preventDefault();
        searchForm.classList.toggle('show');
        if(searchForm.classList.contains('show')) {
            searchButtonIcon.classList.replace('bx-search', 'bx-x');
        } else {
            searchButtonIcon.classList.replace('bx-x', 'bx-search');
        }
    }
})

if(window.innerWidth < 768) {
    sidebar.classList.add('hide');
} else if(window.innerWidth > 576) {
    searchButtonIcon.classList.replace('bx-x', 'bx-search');
    searchForm.classList.remove('show');
}

window.addEventListener('resize', function () {
    if(this.innerWidth > 576) {
        searchButtonIcon.classList.replace('bx-x', 'bx-search');
        searchForm.classList.remove('show');
    }
})

// Dark mode toggle
const switchMode = document.getElementById('switch-mode');

switchMode.addEventListener('change', function () {
    if(this.checked) {
        document.body.classList.add('dark');
    } else {
        document.body.classList.remove('dark');
    }
})

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('[style*="animation: slideIn"]');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    });
}, 1800);

// Smooth scrolling for form validation errors
function scrollToError(element) {
    element.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
    element.focus();
}

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#dc3545';
                if (isValid) {
                    scrollToError(field);
                    isValid = false;
                }
            } else {
                field.style.borderColor = '#28a745';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});

 // Add spin animation for loading states
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);