<?php include 'script/forgot_password_function.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - CVSU Naic</title>
    <link rel="icon" type="image/png" href="../img/cvsu-logo.png">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="script/css/login.css">
    <link rel="stylesheet" href="../assets/css/cvsu-auth.css?v=1.8.6">
</head>

<body class="auth-page">
    <div class="login-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <img src="../img/cvsu-logo.png" alt="CVSU Naic Logo" class="logo">
        </div>

        <!-- Header Section -->
        <div class="header-section">
            <h1 class="main-title">Forgot Your Password?</h1>
            <h2 class="sub-title">Enter your email address and we'll send you instructions to reset your password.</h2>
            <p class="system-name">Document Management System</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle alert-icon'></i>
                <span class="alert-text"><?php echo htmlspecialchars($error); ?></span>
                <button type="button" class="alert-close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle alert-icon'></i>
                <span class="alert-text"><?php echo htmlspecialchars($success); ?></span>
                <button type="button" class="alert-close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Forgot Password Form -->
        <form method="POST" action="" class="login-form" id="forgotForm">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-container">
                    <i class='bx bx-envelope input-icon'></i>
                    <input type="email" id="email" name="email" class="form-input"
                        placeholder="Enter your email address"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email">
                </div>
                <div class="field-error" id="email-error">Please enter a valid email address</div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn" id="forgotBtn">
                <span class="btn-text">Send Reset Instructions</span>
                <div class="btn-loader" style="display: none;">
                    <div class="spinner"></div>
                </div>
            </button>
        </form>

        <!-- Divider -->
        <div class="divider">
            <span class="divider-text">Remember your password?</span>
        </div>

        <!-- Back to Login Link -->
        <a href="../login.php" class="register-link">
            <i class='bx bx-arrow-back'></i>
            <span>Back to Sign In</span>
        </a>
    </div>

    <!-- JavaScript Files -->
    <script src="script/js/script.js"></script>
</body>

</html>
