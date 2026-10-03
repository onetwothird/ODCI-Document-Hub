<?php
    require_once '../includes/config.php';

    $error = '';
    $success = '';
    $departments = [];

    if (isLoggedIn()) {
        header('Location: dashboard.php');
        exit();
    }

    try {
        $stmt = $pdo->query("SELECT id, department_code, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
        $departments = $stmt->fetchAll();
    } catch(Exception $e) {
        error_log("Error fetching departments: " . $e->getMessage());
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $name = sanitizeInput($_POST['name'] ?? '');
        $mi = sanitizeInput($_POST['mi'] ?? '');
        $surname = sanitizeInput($_POST['surname'] ?? '');
        $employee_id = sanitizeInput($_POST['employee_id'] ?? '');
        $position = sanitizeInput($_POST['position'] ?? '');
        $department_id = (int)($_POST['department_id'] ?? 0);
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? '';

        $errors = [];
        
        if (empty($username) || strlen($username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters long.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores.';
        }
        
        if (empty($email) || !validateEmail($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (!validatePassword($password)) {
            $errors['password'] = 'Password must be at least 8 characters with uppercase, lowercase, and number.';
        }
        
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        
        if (empty($name)) {
            $errors['name'] = 'First name is required.';
        }
        
        if (empty($surname)) {
            $errors['surname'] = 'Last name is required.';
        }
        
        if (empty($employee_id)) {
            $errors['employee_id'] = 'Employee ID is required.';
        }
        
        if (empty($position)) {
            $errors['position'] = 'Position is required.';
        }
        
        if ($department_id <= 0) {
            $errors['department_id'] = 'Please select a department.';
        }
        
        if (!empty($date_of_birth)) {
            $birthDate = new DateTime($date_of_birth);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            if ($age < 18) {
                $errors['date_of_birth'] = 'You must be at least 18 years old.';
            }
        }

        if (empty($errors['username'])) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $errors['username'] = 'Username already exists.';
                }
            } catch(Exception $e) {
                error_log("Error checking username: " . $e->getMessage());
            }
        }
        
        if (empty($errors['email'])) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'Email already registered.';
                }
            } catch(Exception $e) {
                error_log("Error checking email: " . $e->getMessage());
            }
        }
        
        if (empty($errors['employee_id'])) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
                $stmt->execute([$employee_id]);
                if ($stmt->fetch()) {
                    $errors['employee_id'] = 'Employee ID already exists.';
                }
            } catch(Exception $e) {
                error_log("Error checking employee ID: " . $e->getMessage());
            }
        }

        if (empty($errors)) {
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $verificationToken = generateToken();
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        username, email, password, name, mi, surname, employee_id, 
                        position, department_id, phone, address, date_of_birth,
                        email_verification_token, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $username, $email, $hashedPassword, $name, $mi, $surname,
                    $employee_id, $position, $department_id, $phone, $address,
                    $date_of_birth ?: null, $verificationToken
                ]);
                
                $userId = $pdo->lastInsertId();

                logActivity($pdo, $userId, 'register', 'user', $userId, 'User registered successfully', [
                    'username' => $username,
                    'email' => $email,
                    'department_id' => $department_id
                ]);
                
                $success = 'Registration successful! Your account is pending approval. You will receive an email once approved.';
                $_POST = [];
                
            } catch(Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = 'Registration failed. Please try again.';
            }
        } else {
            $error = 'Please correct the errors below.';
        }
    }
?>