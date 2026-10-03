<?php include 'script/forgot_password.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="script/css/login_style.css">
</head>

<body class="auth-page">
    <div class="login-container">
        <div class="logo-container">
            <img src="img/cvsu-logo.png" alt="CVSU Naic Logo" class="img-logo">
        </div>

        <div class="login-header">
            <h3>Forgot Your Password?</h3>
            <p>Enter your email address and we'll send you instructions to reset your password.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle'></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="forgotForm">
            <div class="form-group">
                <label for="email" class="required">Email Address</label>
                <i class='bx bx-envelope'></i>
                <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email address"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <button type="submit" class="login-button" id="forgotBtn">
                <span class="spinner"></span>
                Send Reset Instructions
            </button>
        </form>

        <div class="divider">
            <span>Remember your password?</span>
        </div>

        <div class="register-link">
            <a href="../login.php">Back to Sign In</a>
        </div>
    </div>

    <script src="script/js/script.js" defer></script>
</body>

</html>