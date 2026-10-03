<?php include 'login/script/login.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="login/script/css/login_style.css">
</head>
<body class="auth-page">
    <div class="login-container">
        <!-- Logo Section with Enhanced Animation -->
        <div class="logo-container">
            <div class="logo-wrapper">
                <img src="login/img/cvsu-logo.png" alt="CVSU Naic Logo" class="img-logo">
                <div class="logo-glow"></div>
            </div>
        </div>
        
        <!-- Header Section with Better Typography -->
        <div class="login-header">
            <div class="header-content">
                <h2>Office of the Director</h2>
                <h2>for Curriculum and Instruction</h2>
                <p class="system-title">Document Management System</p>
                <div class="header-divider"></div>
            </div>
        </div>
        
        <!-- Alert Messages with Enhanced Styling -->
        <?php if ($error): ?>
            <div class="alert alert-error slide-down">
                <div class="alert-content">
                    <i class='bx bx-error-circle'></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success slide-down">
                <div class="alert-content">
                    <i class='bx bx-check-circle'></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        <?php endif; ?>
        
        <!-- Enhanced Form -->
        <form method="POST" action="" id="loginForm" class="login-form">
            <div class="form-group floating-label">
                <div class="input-wrapper">
                    <i class='bx bx-user input-icon'></i>
                    <input type="text" id="username" name="username" class="form-input" 
                           placeholder=" " 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           required autocomplete="username">
                    <label for="username" class="floating-label-text">Username or Email</label>
                    <div class="input-highlight"></div>
                </div>
                <div class="field-error" id="username-error">Please enter a valid username or email</div>
            </div>
            
            <div class="form-group floating-label">
                <div class="input-wrapper">
                    <i class='bx bx-lock-alt input-icon'></i>
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder=" " required autocomplete="current-password">
                    <label for="password" class="floating-label-text">Password</label>
                    <button type="button" class="password-toggle" onclick="togglePassword('password')" tabindex="-1">
                        <i class='bx bx-hide' id="password-icon"></i>
                    </button>
                    <div class="input-highlight"></div>
                </div>
                <div class="field-error" id="password-error">Password is required</div>
            </div>
            
            <!-- Enhanced Remember/Forgot Section -->
            <div class="form-options">
                <div class="remember-me-wrapper">
                    <label class="custom-checkbox">
                        <input type="checkbox" id="remember" name="remember" 
                               <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                        <span class="checkmark">
                            <i class='bx bx-check'></i>
                        </span>
                        <span class="checkbox-text">Remember me</span>
                    </label>
                </div>
                <a href="login/forgot_password.php" class="forgot-password">
                    <i class='bx bx-help-circle'></i>
                    Forgot Password?
                </a>
            </div>
            
            <!-- Enhanced Login Button -->
            <div class="button-wrapper">
                <button type="submit" class="login-button" id="loginBtn">
                    <div class="button-content">
                        <span class="button-text">Sign In</span>
                        <div class="button-loader">
                            <div class="loader-spinner"></div>
                        </div>
                        <i class='bx bx-right-arrow-alt button-arrow'></i>
                    </div>
                    <div class="button-ripple"></div>
                </button>
            </div>
        </form>
        
        <!-- Enhanced Divider -->
        <div class="auth-divider">
            <div class="divider-line"></div>
            <span class="divider-text">Don't have an account?</span>
            <div class="divider-line"></div>
        </div>
        
        <!-- Enhanced Register Link -->
        <div class="register-section">
            <a href="login/register.php" class="register-link-button">
                <i class='bx bx-user-plus'></i>
                <span>Create New Account</span>
                <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>
        
        <!-- Footer Info -->
        <div class="login-footer">
            <p>Secure login protected by CVSU Naic</p>
        </div>
    </div>
    
    
    <script src="login/script/js/script.js" defer></script>
    
</body>
</html>