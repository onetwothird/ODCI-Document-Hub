<?php include 'script/register.php';?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="script/css/login_style.css">
</head>
<body class="auth-page">
    <div class="register-container">
        <div class="logo-container">
            <img src="img/cvsu-logo.png" alt="CVSU Naic Logo" class="img-logo">
        </div>
        
        <div class="register-header">
            <h3>Create Your Account</h3>
            <p>Join the ODCI document management system</p>
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
        
        <form method="POST" action="" id="registerForm">
            <!-- Account Information Section -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class='bx bx-user-circle'></i>
                    Account Information
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username" class="required">Username</label>
                        <i class='bx bx-user'></i>
                        <input type="text" id="username" name="username" class="form-input" 
                               placeholder="Choose a username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        <div class="field-error <?php echo isset($errors['username']) ? 'show' : ''; ?>">
                            <?php echo $errors['username'] ?? ''; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="required">Email Address</label>
                        <i class='bx bx-envelope'></i>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="your.email@cvsu.edu.ph" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <div class="field-error <?php echo isset($errors['email']) ? 'show' : ''; ?>">
                            <?php echo $errors['email'] ?? ''; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="required">Password</label>
                        <i class='bx bx-lock-alt'></i>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Create a strong password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class='bx bx-hide' id="password-icon"></i>
                        </button>
                        <div class="field-error <?php echo isset($errors['password']) ? 'show' : ''; ?>">
                            <?php echo $errors['password'] ?? ''; ?>
                        </div>
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <ul class="password-requirements">
                                <li id="req-length"><i class='bx bx-x'></i> At least 8 characters</li>
                                <li id="req-upper"><i class='bx bx-x'></i> Uppercase letter</li>
                                <li id="req-lower"><i class='bx bx-x'></i> Lowercase letter</li>
                                <li id="req-number"><i class='bx bx-x'></i> Number</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="required">Confirm Password</label>
                        <i class='bx bx-lock-alt'></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               placeholder="Confirm your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class='bx bx-hide' id="confirm_password-icon"></i>
                        </button>
                        <div class="field-error <?php echo isset($errors['confirm_password']) ? 'show' : ''; ?>">
                            <?php echo $errors['confirm_password'] ?? ''; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Personal Information Section -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class='bx bx-id-card'></i>
                    Personal Information
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="required">First Name</label>
                        <i class='bx bx-user'></i>
                        <input type="text" id="name" name="name" class="form-input" 
                               placeholder="First name" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        <div class="field-error <?php echo isset($errors['name']) ? 'show' : ''; ?>">
                            <?php echo $errors['name'] ?? ''; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mi">Middle Initial</label>
                        <i class='bx bx-user'></i>
                        <input type="text" id="mi" name="mi" class="form-input" maxlength="5"
                               placeholder="M.I." value="<?php echo htmlspecialchars($_POST['mi'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="surname" class="required">Last Name</label>
                        <i class='bx bx-user'></i>
                        <input type="text" id="surname" name="surname" class="form-input" 
                               placeholder="Last name" 
                               value="<?php echo htmlspecialchars($_POST['surname'] ?? ''); ?>" required>
                        <div class="field-error <?php echo isset($errors['surname']) ? 'show' : ''; ?>">
                            <?php echo $errors['surname'] ?? ''; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <i class='bx bx-calendar'></i>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-input"
                               value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                        <div class="field-error <?php echo isset($errors['date_of_birth']) ? 'show' : ''; ?>">
                            <?php echo $errors['date_of_birth'] ?? ''; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <i class='bx bx-phone'></i>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                               placeholder="+63 xxx xxx xxxx" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="address">Address</label>
                    <i class='bx bx-map'></i>
                    <input type="text" id="address" name="address" class="form-input" 
                           placeholder="Complete address" 
                           value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Employment Information Section -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class='bx bx-briefcase'></i>
                    Employment Information
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="employee_id" class="required">Employee ID</label>
                        <i class='bx bx-id-card'></i>
                        <input type="text" id="employee_id" name="employee_id" class="form-input" 
                               placeholder="Employee ID" 
                               value="<?php echo htmlspecialchars($_POST['employee_id'] ?? ''); ?>" required>
                        <div class="field-error <?php echo isset($errors['employee_id']) ? 'show' : ''; ?>">
                            <?php echo $errors['employee_id'] ?? ''; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="position" class="required">Position</label>
                        <i class='bx bx-briefcase'></i>
                        <input type="text" id="position" name="position" class="form-input" 
                               placeholder="Your position/job title" 
                               value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" required>
                        <div class="field-error <?php echo isset($errors['position']) ? 'show' : ''; ?>">
                            <?php echo $errors['position'] ?? ''; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="department_id" class="required">Department</label>
                    <select id="department_id" name="department_id" class="form-select" required>
                        <option value="">Select your department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-error <?php echo isset($errors['department_id']) ? 'show' : ''; ?>">
                        <?php echo $errors['department_id'] ?? ''; ?>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="register-button" id="registerBtn">
                <span class="spinner"></span>
                Create Account
            </button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="../login.php">Sign In</a>
        </div>
    </div>
    
    <script src="script/js/script.js" defer></script>
</body>
</html>