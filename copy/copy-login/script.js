function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    const button = icon.parentElement;
            
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bx-hide');
        icon.classList.add('bx-show');
        button.classList.add('active');
    } else {
        field.type = 'password';
        icon.classList.remove('bx-show');
        icon.classList.add('bx-hide');
        button.classList.remove('active');
    }
    
    button.style.transform = 'translateY(-50%) scale(0.95)';
    setTimeout(() => {
        button.style.transform = '';
    }, 150);
}

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentElement) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    });

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        initializeLoginForm();
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        initializeRegisterForm();
    }

    const forgotForm = document.getElementById('forgotForm');
    if (forgotForm) {
        initializeForgotForm();
    }
});

function initializeLoginForm() {
    const form = document.getElementById('loginForm');
    const inputs = form.querySelectorAll('.form-input');
    
    inputs.forEach(input => {
        const wrapper = input.closest('.input-wrapper');
        const label = wrapper.querySelector('.floating-label-text');
        
        if (input.value.trim() !== '') {
            wrapper.classList.add('has-content');
        }
        
        input.addEventListener('focus', () => {
            wrapper.classList.add('focused');
        });
        
        input.addEventListener('blur', () => {
            wrapper.classList.remove('focused');
            if (input.value.trim() !== '') {
                wrapper.classList.add('has-content');
            } else {
                wrapper.classList.remove('has-content');
            }
        });
        
        input.addEventListener('input', () => {
            if (input.value.trim() !== '') {
                wrapper.classList.add('has-content');
            } else {
                wrapper.classList.remove('has-content');
            }
        });
    });
    
    form.addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('loginBtn');
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        
        const ripple = submitBtn.querySelector('.button-ripple');
        if (ripple) {
            ripple.style.animation = 'ripple-effect 0.6s linear';
            setTimeout(() => {
                ripple.style.animation = '';
            }, 600);
        }
    });
}

function initializeRegisterForm() {
    const form = document.getElementById('registerForm');
    const inputs = form.querySelectorAll('.form-input');
    
    inputs.forEach(input => {
        const wrapper = input.closest('.input-wrapper');
        
        if (input.value.trim() !== '') {
            wrapper.classList.add('has-content');
        }
        
        input.addEventListener('focus', () => {
            wrapper.classList.add('focused');
        });
        
        input.addEventListener('blur', () => {
            wrapper.classList.remove('focused');
            if (input.value.trim() !== '') {
                wrapper.classList.add('has-content');
            } else {
                wrapper.classList.remove('has-content');
            }
        });
        
        input.addEventListener('input', () => {
            if (input.value.trim() !== '') {
                wrapper.classList.add('has-content');
            } else {
                wrapper.classList.remove('has-content');
            }
        });
    });

    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            const username = this.value;
            const errorDiv = this.nextElementSibling.nextElementSibling;
                    
            if (username.length > 0 && username.length < 3) {
                errorDiv.textContent = 'Username must be at least 3 characters long';
                errorDiv.classList.add('show');
            } else if (username.length > 0 && !/^[a-zA-Z0-9_]+$/.test(username)) {
                errorDiv.textContent = 'Username can only contain letters, numbers, and underscores';
                errorDiv.classList.add('show');
            } else {
                errorDiv.classList.remove('show');
            }
        });
    }

    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const email = this.value;
            const errorDiv = this.nextElementSibling.nextElementSibling;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
            if (email.length > 0 && !emailRegex.test(email)) {
                errorDiv.textContent = 'Please enter a valid email address';
                errorDiv.classList.add('show');
            } else {
                errorDiv.classList.remove('show');
            }
        });
    }

    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');

            if (strengthBar && strengthFill) {
                const requirements = {
                    length: password.length >= 8,
                    upper: /[A-Z]/.test(password),
                    lower: /[a-z]/.test(password),
                    number: /\d/.test(password)
                };

                Object.keys(requirements).forEach(req => {
                    const element = document.getElementById(`req-${req}`);
                    if (element) {
                        const icon = element.querySelector('i');
                                
                        if (requirements[req]) {
                            element.classList.add('valid');
                            if (icon) icon.className = 'bx bx-check';
                        } else {
                            element.classList.remove('valid');
                            if (icon) icon.className = 'bx bx-x';
                        }
                    }
                });

                const validCount = Object.values(requirements).filter(Boolean).length;
                const strength = validCount / 4;

                strengthFill.style.width = (strength * 100) + '%';

                strengthBar.className = 'password-strength';
                if (strength < 0.5) {
                    strengthBar.classList.add('strength-weak');
                } else if (strength < 1) {
                    strengthBar.classList.add('strength-medium');
                } else {
                    strengthBar.classList.add('strength-strong');
                }
            }
        });
    }

    const confirmPasswordInput = document.getElementById('confirm_password');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const errorDiv = this.nextElementSibling.nextElementSibling;
                    
            if (confirmPassword && password !== confirmPassword) {
                errorDiv.textContent = 'Passwords do not match';
                errorDiv.classList.add('show');
            } else {
                errorDiv.classList.remove('show');
            }
        });
    }
        
    form.addEventListener('submit', function(e) {
        const btn = document.getElementById('registerBtn');
        btn.classList.add('loading');
        btn.disabled = true;
    });
}

function initializeForgotForm() {
    const form = document.getElementById('forgotForm');
    const inputs = form.querySelectorAll('.form-input');
    
    inputs.forEach(input => {
        const wrapper = input.closest('.input-wrapper');
        
        if (input.value.trim() !== '') {
            wrapper.classList.add('has-content');
        }
        
        input.addEventListener('focus', () => {
            wrapper.classList.add('focused');
        });
        
        input.addEventListener('blur', () => {
            wrapper.classList.remove('focused');
            if (input.value.trim() !== '') {
                wrapper.classList.add('has-content');
            } else {
                wrapper.classList.remove('has-content');
            }
        });
        
        input.addEventListener('input', () => {
            if (input.value.trim() !== '') {
                wrapper.classList.add('has-content');
            } else {
                wrapper.classList.remove('has-content');
            }
        });
    });

    form.addEventListener('submit', function(e) {
        const btn = document.getElementById('forgotBtn');
        btn.classList.add('loading');
        btn.disabled = true;
    });
}