document.addEventListener('DOMContentLoaded', function() {
    // Initialize all functionality when DOM is loaded
    initializeAlertHandlers();
    initializeFormValidation();
    initializeFormSubmission();
    initializeInputAnimations();
    initializeWizard();
    initializePasswordStrength();
});

/**
 * Password toggle functionality - Enhanced for multiple fields
 */
function togglePassword(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const passwordIcon = document.getElementById(fieldId + '-icon');
    
    if (passwordInput && passwordIcon) {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            passwordIcon.classList.remove('bx-hide');
            passwordIcon.classList.add('bx-show');
        } else {
            passwordInput.type = 'password';
            passwordIcon.classList.remove('bx-show');
            passwordIcon.classList.add('bx-hide');
        }
    }
}

/**
 * Initialize alert close handlers
 */
function initializeAlertHandlers() {
    const alertCloses = document.querySelectorAll('.alert-close');
    alertCloses.forEach(btn => {
        btn.addEventListener('click', function() {
            const alert = this.parentElement;
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        });
    });
}

/**
 * Initialize form validation - Enhanced for registration fields
 */
function initializeFormValidation() {
    // Login form validation
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    
    if (usernameInput && passwordInput) {
        // Username validation
        usernameInput.addEventListener('blur', function() {
            const value = this.value.trim();
            const errorElement = document.getElementById('username-error') || 
                               this.parentElement.nextElementSibling;
            
            if (!value) {
                showFieldError(errorElement, 'Username or email is required');
                this.classList.add('error');
            } else {
                hideFieldError(errorElement);
                this.classList.remove('error');
            }
        });
        
        // Password validation for login
        passwordInput.addEventListener('blur', function() {
            const value = this.value;
            const errorElement = document.getElementById('password-error') || 
                               this.parentElement.nextElementSibling;
            
            if (!value) {
                showFieldError(errorElement, 'Password is required');
                this.classList.add('error');
            } else if (value.length < 6) {
                showFieldError(errorElement, 'Password must be at least 6 characters');
                this.classList.add('error');
            } else {
                hideFieldError(errorElement);
                this.classList.remove('error');
            }
        });
        
        // Clear errors on input
        usernameInput.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                const errorElement = document.getElementById('username-error') || 
                                   this.parentElement.nextElementSibling;
                hideFieldError(errorElement);
                this.classList.remove('error');
            }
        });
        
        passwordInput.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                const errorElement = document.getElementById('password-error') || 
                                   this.parentElement.nextElementSibling;
                hideFieldError(errorElement);
                this.classList.remove('error');
            }
        });
    }
    
    // Registration form validation
    initializeRegistrationValidation();
}

/**
 * Initialize registration-specific validation
 */
function initializeRegistrationValidation() {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return;
    
    // Email validation
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const value = this.value.trim();
            const errorElement = this.parentElement.nextElementSibling;
            
            if (!value) {
                showFieldError(errorElement, 'Email is required');
                this.classList.add('error');
            } else if (!isValidEmail(value)) {
                showFieldError(errorElement, 'Please enter a valid email address');
                this.classList.add('error');
            } else {
                hideFieldError(errorElement);
                this.classList.remove('error');
            }
        });
        
        emailInput.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                const errorElement = this.parentElement.nextElementSibling;
                hideFieldError(errorElement);
                this.classList.remove('error');
            }
        });
    }
    
    // Confirm password validation
    const confirmPasswordInput = document.getElementById('confirm_password');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('blur', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const errorElement = this.parentElement.nextElementSibling;
            
            if (!confirmPassword) {
                showFieldError(errorElement, 'Please confirm your password');
                this.classList.add('error');
            } else if (password !== confirmPassword) {
                showFieldError(errorElement, 'Passwords do not match');
                this.classList.add('error');
            } else {
                hideFieldError(errorElement);
                this.classList.remove('error');
            }
        });
        
        confirmPasswordInput.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                const errorElement = this.parentElement.nextElementSibling;
                hideFieldError(errorElement);
                this.classList.remove('error');
            }
        });
    }
    
    // Required fields validation
    const requiredFields = ['name', 'surname', 'employee_id', 'position'];
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field) {
            field.addEventListener('blur', function() {
                const value = this.value.trim();
                const errorElement = this.parentElement.nextElementSibling;
                
                if (!value) {
                    showFieldError(errorElement, `${this.labels[0]?.textContent || 'This field'} is required`);
                    this.classList.add('error');
                } else {
                    hideFieldError(errorElement);
                    this.classList.remove('error');
                }
            });
            
            field.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    const errorElement = this.parentElement.nextElementSibling;
                    hideFieldError(errorElement);
                    this.classList.remove('error');
                }
            });
        }
    });
}

/**
 * Initialize wizard functionality
 */
function initializeWizard() {
    const wizardSteps = document.querySelectorAll('.wizard-step');
    const progressSteps = document.querySelectorAll('.progress-step');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    if (!wizardSteps.length || !progressSteps.length) return;
    
    let currentStep = 1;
    const totalSteps = wizardSteps.length;
    
    // Next button handler
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            if (validateCurrentStep(currentStep)) {
                if (currentStep < totalSteps) {
                    goToStep(currentStep + 1);
                }
            }
        });
    }
    
    // Previous button handler
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (currentStep > 1) {
                goToStep(currentStep - 1);
            }
        });
    }
    
    function goToStep(stepNumber) {
        // Hide all steps
        wizardSteps.forEach(step => {
            step.classList.remove('active');
        });
        
        // Show current step
        const targetStep = document.querySelector(`.wizard-step[data-step="${stepNumber}"]`);
        if (targetStep) {
            targetStep.classList.add('active');
        }
        
        // Update progress
        progressSteps.forEach((step, index) => {
            const stepNum = index + 1;
            if (stepNum < stepNumber) {
                step.classList.add('completed');
                step.classList.remove('active');
            } else if (stepNum === stepNumber) {
                step.classList.add('active');
                step.classList.remove('completed');
            } else {
                step.classList.remove('active', 'completed');
            }
        });
        
        // Update buttons
        if (prevBtn) {
            prevBtn.style.display = stepNumber > 1 ? 'flex' : 'none';
        }
        
        if (stepNumber === totalSteps) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'flex';
        } else {
            if (nextBtn) nextBtn.style.display = 'flex';
            if (submitBtn) submitBtn.style.display = 'none';
        }
        
        currentStep = stepNumber;
    }
    
    function validateCurrentStep(stepNumber) {
        const currentStepElement = document.querySelector(`.wizard-step[data-step="${stepNumber}"]`);
        if (!currentStepElement) return true;
        
        const requiredInputs = currentStepElement.querySelectorAll('input[required], select[required]');
        let isValid = true;
        
        requiredInputs.forEach(input => {
            const value = input.value.trim();
            const errorElement = input.parentElement.nextElementSibling;
            
            if (!value) {
                const fieldName = input.labels?.[0]?.textContent || input.placeholder || 'This field';
                showFieldError(errorElement, `${fieldName} is required`);
                input.classList.add('error');
                isValid = false;
            } else {
                hideFieldError(errorElement);
                input.classList.remove('error');
                
                // Additional validation for specific fields
                if (input.type === 'email' && !isValidEmail(value)) {
                    showFieldError(errorElement, 'Please enter a valid email address');
                    input.classList.add('error');
                    isValid = false;
                }
                
                if (input.id === 'confirm_password') {
                    const password = document.getElementById('password').value;
                    if (value !== password) {
                        showFieldError(errorElement, 'Passwords do not match');
                        input.classList.add('error');
                        isValid = false;
                    }
                }
            }
        });
        
        // Password strength validation for step 1
        if (stepNumber === 1) {
            const passwordInput = document.getElementById('password');
            if (passwordInput && passwordInput.value) {
                const strength = calculatePasswordStrength(passwordInput.value);
                if (strength.score < 2) {
                    const errorElement = passwordInput.parentElement.nextElementSibling;
                    showFieldError(errorElement, 'Please create a stronger password');
                    passwordInput.classList.add('error');
                    isValid = false;
                }
            }
        }
        
        return isValid;
    }
}

/**
 * Initialize password strength indicator
 */
function initializePasswordStrength() {
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.getElementById('passwordStrength');
    
    if (!passwordInput || !strengthIndicator) return;
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        
        if (password.length > 0) {
            strengthIndicator.classList.add('show');
            updatePasswordStrength(password);
        } else {
            strengthIndicator.classList.remove('show');
        }
    });
    
    passwordInput.addEventListener('focus', function() {
        if (this.value.length > 0) {
            strengthIndicator.classList.add('show');
        }
    });
    
    function updatePasswordStrength(password) {
        const strength = calculatePasswordStrength(password);
        const strengthFill = document.getElementById('strengthFill');
        const requirements = {
            'req-length': password.length >= 8,
            'req-upper': /[A-Z]/.test(password),
            'req-lower': /[a-z]/.test(password),
            'req-number': /\d/.test(password)
        };
        
        // Update strength bar
        if (strengthFill) {
            strengthFill.className = `strength-fill ${strength.class}`;
        }
        
        // Update requirements
        Object.keys(requirements).forEach(reqId => {
            const reqElement = document.getElementById(reqId);
            if (reqElement) {
                const icon = reqElement.querySelector('i');
                if (requirements[reqId]) {
                    reqElement.classList.add('valid');
                    if (icon) {
                        icon.className = 'bx bx-check';
                    }
                } else {
                    reqElement.classList.remove('valid');
                    if (icon) {
                        icon.className = 'bx bx-x';
                    }
                }
            }
        });
    }
}

/**
 * Calculate password strength
 */
function calculatePasswordStrength(password) {
    let score = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 8) score++;
    else feedback.push('Use at least 8 characters');
    
    // Uppercase check
    if (/[A-Z]/.test(password)) score++;
    else feedback.push('Add uppercase letters');
    
    // Lowercase check
    if (/[a-z]/.test(password)) score++;
    else feedback.push('Add lowercase letters');
    
    // Number check
    if (/\d/.test(password)) score++;
    else feedback.push('Add numbers');
    
    // Special character bonus
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    // Determine strength level
    let strengthClass = 'weak';
    let strengthText = 'Weak';
    
    if (score >= 4) {
        strengthClass = 'strong';
        strengthText = 'Strong';
    } else if (score >= 3) {
        strengthClass = 'good';
        strengthText = 'Good';
    } else if (score >= 2) {
        strengthClass = 'fair';
        strengthText = 'Fair';
    }
    
    return {
        score: score,
        class: strengthClass,
        text: strengthText,
        feedback: feedback
    };
}

/**
 * Initialize form submission with loading state
 */
function initializeFormSubmission() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const loginBtn = document.getElementById('loginBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    // Login form submission
    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return;
            }
            showLoadingState(loginBtn);
        });
    }
    
    // Registration form submission
    if (registerForm && submitBtn) {
        registerForm.addEventListener('submit', function(e) {
            if (!validateAllSteps()) {
                e.preventDefault();
                return;
            }
            showLoadingState(submitBtn);
        });
    }
}

/**
 * Validate entire form (for login)
 */
function validateForm() {
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    
    if (!usernameInput || !passwordInput) return true;
    
    let isValid = true;
    
    // Validate username
    const usernameValue = usernameInput.value.trim();
    const usernameError = document.getElementById('username-error') || 
                         usernameInput.parentElement.nextElementSibling;
    
    if (!usernameValue) {
        showFieldError(usernameError, 'Username or email is required');
        usernameInput.classList.add('error');
        isValid = false;
    } else {
        hideFieldError(usernameError);
        usernameInput.classList.remove('error');
    }
    
    // Validate password
    const passwordValue = passwordInput.value;
    const passwordError = document.getElementById('password-error') || 
                         passwordInput.parentElement.nextElementSibling;
    
    if (!passwordValue) {
        showFieldError(passwordError, 'Password is required');
        passwordInput.classList.add('error');
        isValid = false;
    } else if (passwordValue.length < 6) {
        showFieldError(passwordError, 'Password must be at least 6 characters');
        passwordInput.classList.add('error');
        isValid = false;
    } else {
        hideFieldError(passwordError);
        passwordInput.classList.remove('error');
    }
    
    return isValid;
}

/**
 * Validate all registration steps
 */
function validateAllSteps() {
    const wizardSteps = document.querySelectorAll('.wizard-step');
    let isValid = true;
    
    wizardSteps.forEach((step, index) => {
        const stepNumber = index + 1;
        const requiredInputs = step.querySelectorAll('input[required], select[required]');
        
        requiredInputs.forEach(input => {
            const value = input.value.trim();
            const errorElement = input.parentElement.nextElementSibling;
            
            if (!value) {
                const fieldName = input.labels?.[0]?.textContent || input.placeholder || 'This field';
                showFieldError(errorElement, `${fieldName} is required`);
                input.classList.add('error');
                isValid = false;
            }
        });
    });
    
    return isValid;
}

/**
 * Utility functions
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function showFieldError(errorElement, message) {
    if (errorElement && errorElement.classList.contains('field-error')) {
        errorElement.textContent = message;
        errorElement.classList.add('show');
    }
}

function hideFieldError(errorElement) {
    if (errorElement && errorElement.classList.contains('field-error')) {
        errorElement.classList.remove('show');
    }
}

function showLoadingState(button) {
    if (!button) return;
    
    const btnText = button.querySelector('.btn-text');
    const btnLoader = button.querySelector('.btn-loader');
    
    if (btnText && btnLoader) {
        btnText.style.display = 'none';
        btnLoader.style.display = 'inline-flex';
        button.disabled = true;
        button.style.opacity = '0.8';
    }
}

function hideLoadingState(button) {
    if (!button) return;
    
    const btnText = button.querySelector('.btn-text');
    const btnLoader = button.querySelector('.btn-loader');
    
    if (btnText && btnLoader) {
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
        button.disabled = false;
        button.style.opacity = '1';
    }
}

/**
 * Show success/error messages
 */
function showSuccessMessage(message) {
    showAlert('success', message);
}

function showErrorMessage(message) {
    showAlert('error', message);
}

function showAlert(type, message) {
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.style.opacity = '0';
    alertDiv.style.transform = 'translateY(-10px)';
    
    const iconClass = type === 'success' ? 'bx-check-circle' : 'bx-error-circle';
    
    alertDiv.innerHTML = `
        <i class='bx ${iconClass} alert-icon'></i>
        <span class="alert-text">${message}</span>
        <button type="button" class="alert-close">
            <i class='bx bx-x'></i>
        </button>
    `;
    
    const headerSection = document.querySelector('.header-section');
    if (headerSection) {
        headerSection.insertAdjacentElement('afterend', alertDiv);
        
        setTimeout(() => {
            alertDiv.style.opacity = '1';
            alertDiv.style.transform = 'translateY(0)';
        }, 100);
        
        const closeBtn = alertDiv.querySelector('.alert-close');
        closeBtn.addEventListener('click', function() {
            alertDiv.style.opacity = '0';
            alertDiv.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                alertDiv.remove();
            }, 300);
        });
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.style.opacity = '0';
                alertDiv.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alertDiv.remove();
                }, 300);
            }
        }, 5000);
    }
}

/**
 * Form input animations and interactions
 */
function initializeInputAnimations() {
    const inputs = document.querySelectorAll('.form-input');
    
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
        
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.add('has-value');
            } else {
                this.classList.remove('has-value');
            }
        });
    });
}