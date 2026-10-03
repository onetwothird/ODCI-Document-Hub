<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Cancelled - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="script/css/cancelled.css">
    <link rel="stylesheet" href="../assets/css/cvsu-auth.css?v=1.8.6">
</head>

<body class="auth-page">
    <div class="cancelled-container">
        <!-- Main Content -->
        <div class="content-section">
            <div class="status-icon">
                <i class='bx bx-x-circle'></i>
            </div>
            <h3 class="status-title">Authentication Cancelled</h3>
            <p class="status-message">
                You have cancelled the Google authentication process. 
                Don't worry, you can try again anytime!
            </p>
        </div>

        <!-- Redirect Info -->
        <div class="redirect-info">
            <div class="spinner"></div>
            <span>Redirecting to login page in <span class="countdown" id="countdown">5</span> seconds...</span>
        </div>
        
        <!-- Action Button -->
        <a href="../login.php" class="submit-btn">
            <i class='bx bx-log-in'></i>
            <span>Go to Login Now</span>
        </a>
    </div>

    <script>
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = '../login.php';
            }
        }, 1000);
    </script>
</body>
</html>