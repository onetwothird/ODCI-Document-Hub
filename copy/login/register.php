<?php include 'script/registerFunction.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="script/css/register.css">
</head>

<body class="auth-page">
    <div class="login-container">
        <!-- Fixed Header Section -->
        <div class="container-header">
            <!-- Logo Section -->
            <div class="logo-section">
                <img src="img/cvsu-logo.png" alt="CVSU Naic Logo" class="logo">
            </div>

            <!-- Header Section -->
            <div class="header-section">
                <h1 class="main-title">ODCI Document Management System</h1>
                <p class="system-name">Registration Portal</p>
            </div>

            <!-- Progress Indicator -->
            <div class="wizard-progress">
                <div class="progress-step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Account</div>
                </div>
                <div class="progress-step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Personal</div>
                </div>
                <div class="progress-step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Employment</div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle alert-icon'></i>
                    <span class="alert-text"><?php echo htmlspecialchars($error); ?></span>
                    <button type="button" class="alert-close">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle alert-icon'></i>
                    <span class="alert-text"><?php echo htmlspecialchars($success); ?></span>
                    <button type="button" class="alert-close">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Scrollable Content Section -->
        <div class="container-content">
            <!-- Registration Form -->
            <form method="POST" action="" class="login-form" id="registerForm">

                <!-- Step 1: Account Information -->
                <div class="wizard-step active" data-step="1">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-container">
                            <i class='bx bx-user input-icon'></i>
                            <input type="text" id="username" name="username" class="form-input"
                                placeholder="Choose a username"
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                        <div class="field-error <?php echo isset($errors['username']) ? 'show' : ''; ?>">
                            <?php echo $errors['username'] ?? 'Please enter a username'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-container">
                            <i class='bx bx-envelope input-icon'></i>
                            <input type="email" id="email" name="email" class="form-input"
                                placeholder="your.email@cvsu.edu.ph"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <div class="field-error <?php echo isset($errors['email']) ? 'show' : ''; ?>">
                            <?php echo $errors['email'] ?? 'Please enter a valid email'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-container">
                            <i class='bx bx-lock-alt input-icon'></i>
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Create a strong password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class='bx bx-hide' id="password-icon"></i>
                            </button>
                        </div>
                        <div class="field-error <?php echo isset($errors['password']) ? 'show' : ''; ?>">
                            <?php echo $errors['password'] ?? 'Password is required'; ?>
                        </div>

                        <!-- Improved Password Strength Indicator -->
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-header">
                                <span class="strength-label">Password Strength</span>
                                <span class="strength-text" id="strengthText">Very Weak</span>
                            </div>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-container">
                            <i class='bx bx-lock-alt input-icon'></i>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                                placeholder="Confirm your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class='bx bx-hide' id="confirm_password-icon"></i>
                            </button>
                        </div>
                        <div class="field-error <?php echo isset($errors['confirm_password']) ? 'show' : ''; ?>">
                            <?php echo $errors['confirm_password'] ?? 'Please confirm your password'; ?>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Personal Information -->
                <div class="wizard-step" data-step="2">
                    <div class="form-group">
                        <label for="name" class="form-label">First Name</label>
                        <div class="input-container">
                            <i class='bx bx-user input-icon'></i>
                            <input type="text" id="name" name="name" class="form-input" placeholder="First name"
                                value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                        <div class="field-error <?php echo isset($errors['name']) ? 'show' : ''; ?>">
                            <?php echo $errors['name'] ?? 'First name is required'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="mi" class="form-label">Middle Initial</label>
                        <div class="input-container">
                            <i class='bx bx-user input-icon'></i>
                            <input type="text" id="mi" name="mi" class="form-input" maxlength="5" placeholder="M.I."
                                value="<?php echo htmlspecialchars($_POST['mi'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="surname" class="form-label">Last Name</label>
                        <div class="input-container">
                            <i class='bx bx-user input-icon'></i>
                            <input type="text" id="surname" name="surname" class="form-input" placeholder="Last name"
                                value="<?php echo htmlspecialchars($_POST['surname'] ?? ''); ?>" required>
                        </div>
                        <div class="field-error <?php echo isset($errors['surname']) ? 'show' : ''; ?>">
                            <?php echo $errors['surname'] ?? 'Last name is required'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <div class="input-container">
                            <i class='bx bx-calendar input-icon'></i>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-input"
                                value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                        </div>
                        <div class="field-error <?php echo isset($errors['date_of_birth']) ? 'show' : ''; ?>">
                            <?php echo $errors['date_of_birth'] ?? ''; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-container">
                            <i class='bx bx-phone input-icon'></i>
                            <input type="tel" id="phone" name="phone" class="form-input" placeholder="+63 xxx xxx xxxx"
                                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address" class="form-label">Address</label>
                        <div class="input-container">
                            <i class='bx bx-map input-icon'></i>
                            <input type="text" id="address" name="address" class="form-input"
                                placeholder="Complete address"
                                value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Step 3: Employment Information -->
                <div class="wizard-step" data-step="3">
                    <div class="form-group">
                        <label for="employee_id" class="form-label">Employee ID</label>
                        <div class="input-container">
                            <i class='bx bx-id-card input-icon'></i>
                            <input type="text" id="employee_id" name="employee_id" class="form-input"
                                placeholder="Employee ID"
                                value="<?php echo htmlspecialchars($_POST['employee_id'] ?? ''); ?>" required>
                        </div>
                        <div class="field-error <?php echo isset($errors['employee_id']) ? 'show' : ''; ?>">
                            <?php echo $errors['employee_id'] ?? 'Employee ID is required'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="position" class="form-label">Position</label>
                        <div class="input-container">
                            <i class='bx bx-briefcase input-icon'></i>
                            <input type="text" id="position" name="position" class="form-input" placeholder="Position"
                                value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" required>
                        </div>
                        <div class="field-error <?php echo isset($errors['position']) ? 'show' : ''; ?>">
                            <?php echo $errors['position'] ?? 'Position is required'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="department_id" class="form-label">Department</label>
                        <div class="input-container">
                            <i class='bx bx-buildings input-icon'></i>
                            <select id="department_id" name="department_id" class="form-input" required>
                                <option value="">Select your department</option>
                                <?php if (isset($departments)): ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="field-error <?php echo isset($errors['department_id']) ? 'show' : ''; ?>">
                            <?php echo $errors['department_id'] ?? 'Please select a department'; ?>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="wizard-navigation">
                    <button type="button" class="wizard-btn prev-btn" id="prevBtn" style="display: none;">
                        <i class='bx bx-chevron-left'></i>
                        <span>Previous</span>
                    </button>

                    <button type="button" class="wizard-btn next-btn" id="nextBtn">
                        <span>Next</span>
                        <i class='bx bx-chevron-right'></i>
                    </button>

                    <button type="submit" class="submit-btn" id="submitBtn" style="display: none;">
                        <span class="btn-text">Register</span>
                        <div class="btn-loader" style="display: none;">
                            <div class="spinner"></div>
                        </div>
                    </button>
                </div>
            </form>

            <!-- Divider -->
            <div class="divider">
                <span class="divider-text">Already have an account?</span>
            </div>

            <!-- Login Link -->
            <a href="../login.php" class="register-link">
                <i class='bx bx-log-in'></i>
                <span>Sign In</span>
            </a>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="script/js/script.js"></script>
</body>

</html>